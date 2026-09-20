<?php
/**
 * Plugin Name: Project Nami Full Text Search
 * Plugin URI: http://projectnami.org
 * Description: Search using SQL Server / Azure SQL Full-Text Search. Replaces LIKE '%term%' on post_content with CONTAINSTABLE, with core WP term parsing and a LIKE fallback.
 * Author: Patrick Bates
 * Version: 2.0.6
 * Author URI: http://projectnami.org
 * License: GPLv3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PN_Fulltext_Search {

	const SCHEMA_VERSION = 2;
	const OPTION_SCHEMA  = 'pn_fts_schema_version';
	const OPTION_FLAVOR  = 'pn_fts_view_flavor';
	const CATALOG        = 'ftCatalog';

	/**
	 * @var bool|null Request-local "schema looks usable".
	 */
	protected $ready = null;

	/**
	 * @var bool|null Request-local split-column view (has post_title).
	 */
	protected $flavor_split = null;

	public function __construct() {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_initialize_site', array( __CLASS__, 'on_new_site' ), 100, 1 );
	}

	public function init() {
		add_filter( 'posts_search', array( $this, 'posts_search' ), 10, 2 );
		add_filter( 'posts_join', array( $this, 'posts_join' ), 10, 2 );
		add_filter( 'posts_orderby', array( $this, 'posts_orderby' ), 10, 2 );
	}

	/* ---------------------------------------------------------------------
	 * Schema
	 * ------------------------------------------------------------------ */

	public static function view_name() {
		return '[' . self::view_name_raw() . ']';
	}

	public static function view_name_raw() {
		global $wpdb;
		$prefix = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $wpdb->get_blog_prefix() );
		return $prefix . 'fulltext_search';
	}

	public static function index_name() {
		return 'CLU_' . self::view_name_raw();
	}

	public static function lcid() {
		$locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		$map    = array(
			'en_US' => 1033,
			'en_GB' => 2057,
			'en_AU' => 3081,
			'en_CA' => 4105,
			'de_DE' => 1031,
			'fr_FR' => 1036,
			'es_ES' => 3082,
			'es_MX' => 2058,
			'it_IT' => 1040,
			'pt_BR' => 1046,
			'pt_PT' => 2070,
			'nl_NL' => 1043,
			'ja'    => 1041,
			'ja_JP' => 1041,
			'zh_CN' => 2052,
			'zh_TW' => 1028,
			'ko_KR' => 1042,
			'ru_RU' => 1049,
			'pl_PL' => 1045,
			'sv_SE' => 1053,
			'da_DK' => 1030,
			'fi_FI' => 1035,
			'nb_NO' => 1044,
			'tr_TR' => 1055,
			'ar'    => 1025,
			'he_IL' => 1037,
			'cs_CZ' => 1029,
			'hu_HU' => 1038,
			'el'    => 1032,
			'th'    => 1054,
			'vi'    => 1066,
		);
		$lcid = $map[ $locale ] ?? $map[ substr( $locale, 0, 2 ) ] ?? 1033;
		return (int) apply_filters( 'pn_fts_lcid', $lcid, $locale );
	}

	/**
	 * Advisory only. Never used as an activation gate — 1.1 didn't either.
	 *
	 * FULLTEXTSERVICEPROPERTY('IsFullTextInstalled') is on-prem and returns 0 on
	 * Azure SQL even when FTS works.
	 *
	 * @return bool
	 */
	public static function fts_installed() {
		global $wpdb;

		$name = $wpdb->get_var( 'SELECT TOP 1 name FROM sys.fulltext_catalogs' );
		if ( $name ) {
			return true;
		}

		$edition = $wpdb->get_var( "select CAST(SERVERPROPERTY('edition') as VARCHAR) AS edition" );
		if ( is_string( $edition ) && false !== stripos( trim( $edition ), 'SQL Azure' ) ) {
			return true;
		}

		$engine = $wpdb->get_var( "select CAST(SERVERPROPERTY('EngineEdition') as VARCHAR) AS engine_edition" );
		if ( in_array( (int) $engine, array( 5, 8, 9, 12 ), true ) ) {
			return true;
		}

		$val = $wpdb->get_var( "select FULLTEXTSERVICEPROPERTY('IsFullTextInstalled') AS fts_installed" );
		return (int) $val === 1;
	}

	/**
	 * @param string $sql T-SQL.
	 * @return string last_error or ''.
	 */
	protected static function run_ddl( $sql ) {
		global $wpdb;
		$wpdb->last_error = '';
		$wpdb->query( $sql );
		return ( is_string( $wpdb->last_error ) && $wpdb->last_error !== '' ) ? $wpdb->last_error : '';
	}

	/**
	 * @param string $err Error text.
	 * @return bool
	 */
	protected static function is_exists_error( $err ) {
		return (bool) preg_match( '/already exists|There is already an object/i', $err );
	}

	/**
	 * Create or rebuild catalog, view, clustered index, and FT index for this blog.
	 *
	 * Tries split columns first (rank by title). If SQL Server rejects that indexed
	 * view, falls back to the 1.1 concatenated search_text view.
	 *
	 * @param bool $force Drop and recreate even if the view already exists.
	 * @return true|WP_Error
	 */
	public static function ensure_schema( $force = true ) {
		global $wpdb;

		$edition = $wpdb->get_var( "select CAST(SERVERPROPERTY('edition') as VARCHAR) AS edition" );
		if ( is_string( $edition ) && trim( $edition ) === 'SQL Azure' && ! version_compare( (string) $wpdb->db_version(), '12.0', '>=' ) ) {
			return new WP_Error( 'pn_fts_azure_old', 'PN Full Text Search requires Azure SQL V12 or greater.' );
		}

		$view_raw = self::view_name_raw();
		$view     = self::view_name();
		$idx      = self::index_name();
		$lcid     = self::lcid();
		$posts    = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $wpdb->posts );
		$users    = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $wpdb->users );
		$catalog  = self::CATALOG;

		if ( ! $posts || ! $users || ! $view_raw ) {
			return new WP_Error( 'pn_fts_ident', 'Could not build safe FTS identifiers from table prefix.' );
		}

		self::run_ddl( 'SET QUOTED_IDENTIFIER ON' );

		if ( ! $force && self::schema_present() ) {
			self::mark_ready( self::detect_flavor() );
			return true;
		}

		$err = self::run_ddl(
			"if not exists (select * from sys.fulltext_catalogs where name = '{$catalog}') create fulltext catalog [{$catalog}]"
		);
		if ( $err && ! self::is_exists_error( $err ) ) {
			$err = self::run_ddl(
				"if not exists (select * from sys.dm_fts_active_catalogs where name = '{$catalog}' and database_id = DB_ID()) create fulltext catalog [{$catalog}]"
			);
		}
		if ( $err && ! self::is_exists_error( $err ) ) {
			return new WP_Error( 'pn_fts_catalog', 'CREATE FULLTEXT CATALOG: ' . $err );
		}

		self::drop_view();

		$split_sql = "CREATE VIEW dbo.{$view} WITH SCHEMABINDING AS SELECT p.ID, p.post_title, p.post_excerpt, p.post_content, p.post_name, u.display_name FROM dbo.[{$posts}] AS p INNER JOIN dbo.[{$users}] AS u ON p.post_author = u.ID";

		$concat_sql = "CREATE VIEW dbo.{$view} WITH SCHEMABINDING AS SELECT p.ID, u.display_name + N' ' + p.post_title + N' ' + p.post_excerpt + N' ' + p.post_content AS search_text FROM dbo.[{$posts}] AS p INNER JOIN dbo.[{$users}] AS u ON p.post_author = u.ID";

		$flavor = 'split';
		$err    = self::create_view( $split_sql );
		if ( $err ) {
			self::drop_view();
			$flavor = 'concat';
			$err    = self::create_view( $concat_sql );
			if ( $err ) {
				return new WP_Error( 'pn_fts_view', 'CREATE VIEW: ' . $err );
			}
		}

		$err = self::run_ddl( "CREATE UNIQUE CLUSTERED INDEX [{$idx}] ON dbo.{$view} ([ID] ASC)" );
		if ( $err && ! self::is_exists_error( $err ) ) {
			if ( 'split' === $flavor ) {
				self::drop_view();
				$flavor = 'concat';
				$err    = self::create_view( $concat_sql );
				if ( $err ) {
					return new WP_Error( 'pn_fts_view', 'CREATE VIEW (concat fallback): ' . $err );
				}
				$err = self::run_ddl( "CREATE UNIQUE CLUSTERED INDEX [{$idx}] ON dbo.{$view} ([ID] ASC)" );
			}
			if ( $err && ! self::is_exists_error( $err ) ) {
				return new WP_Error( 'pn_fts_clu', 'CLUSTERED INDEX: ' . $err );
			}
		}

		if ( 'split' === $flavor ) {
			$err = self::run_ddl(
				"CREATE FULLTEXT INDEX ON dbo.{$view} (post_title LANGUAGE {$lcid}, post_excerpt LANGUAGE {$lcid}, post_content LANGUAGE {$lcid}, post_name LANGUAGE {$lcid}, display_name LANGUAGE {$lcid}) KEY INDEX [{$idx}] ON [{$catalog}] WITH CHANGE_TRACKING AUTO"
			);
			if ( $err ) {
				$err = self::run_ddl(
					"CREATE FULLTEXT INDEX ON dbo.{$view} (post_title, post_excerpt, post_content, post_name, display_name) KEY INDEX [{$idx}] ON [{$catalog}]"
				);
			}
		} else {
			$err = self::run_ddl(
				"CREATE FULLTEXT INDEX ON dbo.{$view} (search_text) KEY INDEX [{$idx}] ON [{$catalog}]"
			);
		}
		if ( $err && ! self::is_exists_error( $err ) ) {
			return new WP_Error( 'pn_fts_index', 'CREATE FULLTEXT INDEX: ' . $err );
		}

		self::run_ddl( "ALTER FULLTEXT INDEX ON dbo.{$view} ENABLE" );
		self::run_ddl( "ALTER FULLTEXT INDEX ON dbo.{$view} START FULL POPULATION" );

		update_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION, true );
		update_option( self::OPTION_FLAVOR, $flavor, true );
		delete_option( 'pn_fts_pending_build' );
		return true;
	}

	/**
	 * @return bool
	 */
	public static function schema_present() {
		global $wpdb;
		$view_raw = self::view_name_raw();
		$name     = $wpdb->get_var( $wpdb->prepare( 'SELECT TOP 1 name FROM sys.views WHERE name = %s', $view_raw ) );
		if ( empty( $name ) ) {
			return false;
		}
		$idx = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT TOP 1 name FROM sys.indexes WHERE object_id = OBJECT_ID(%s) AND type_desc = %s',
				'dbo.' . $view_raw,
				'CLUSTERED'
			)
		);
		return ! empty( $idx );
	}

	/**
	 * @return string split|concat
	 */
	public static function detect_flavor() {
		global $wpdb;
		$view_raw = self::view_name_raw();
		$col      = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT TOP 1 name FROM sys.columns WHERE object_id = OBJECT_ID(%s) AND name = %s',
				'dbo.' . $view_raw,
				'search_text'
			)
		);
		return $col ? 'concat' : 'split';
	}

	/**
	 * @param string $flavor split|concat
	 */
	protected static function mark_ready( $flavor ) {
		update_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION, true );
		update_option( self::OPTION_FLAVOR, $flavor, true );
		delete_option( 'pn_fts_pending_build' );
	}

	/**
	 * @param string $view_sql CREATE VIEW statement.
	 * @return string error or ''.
	 */
	protected static function create_view( $view_sql ) {
		$escaped = str_replace( "'", "''", $view_sql );
		return self::run_ddl( "exec('{$escaped}')" );
	}

	protected static function drop_view() {
		$view_raw = self::view_name_raw();
		$view     = self::view_name();
		self::run_ddl(
			"IF EXISTS (SELECT 1 FROM sys.fulltext_indexes WHERE object_id = OBJECT_ID(N'dbo.{$view_raw}')) DROP FULLTEXT INDEX ON dbo.{$view}"
		);
		self::run_ddl(
			"IF OBJECT_ID(N'dbo.{$view_raw}', N'V') IS NOT NULL DROP VIEW dbo.{$view}"
		);
		self::run_ddl(
			"if exists (select * from INFORMATION_SCHEMA.TABLES where table_name = '{$view_raw}') exec('DROP VIEW dbo.{$view}')"
		);
	}

	public static function activate() {
		if ( self::schema_present() ) {
			self::mark_ready( self::detect_flavor() );
			return;
		}
		update_option( 'pn_fts_pending_build', 1, true );
	}

	/**
	 * Do not DROP the indexed view on deactivate.
	 * Recreating it on a large Azure site exceeds the App Service request cap;
	 * deactivate/activate is not a rebuild path.
	 */
	public static function deactivate() {
		delete_option( 'pn_fts_pending_build' );
	}

	/**
	 * @param WP_Site $site Site.
	 */
	public static function on_new_site( $site ) {
		if ( ! $site || empty( $site->blog_id ) ) {
			return;
		}
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		self::ensure_schema();
		restore_current_blog();
	}

	/* ---------------------------------------------------------------------
	 * Query integration
	 * ------------------------------------------------------------------ */

	/**
	 * @param WP_Query $query Query.
	 * @return bool
	 */
	protected function should_apply( $query ) {
		if ( ! $query instanceof WP_Query ) {
			return false;
		}
		if ( $query->get( 'suppress_filters' ) ) {
			return false;
		}
		if ( apply_filters( 'pn_fts_disable', false, $query ) ) {
			return false;
		}
		if ( $query->get( 'pn_fts' ) === 'off' ) {
			return false;
		}
		if ( isset( $_GET['pn_fts'] ) && 'off' === $_GET['pn_fts'] && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		$s = $query->get( 's' );
		if ( ! is_string( $s ) || '' === $s ) {
			return false;
		}
		if ( ! $this->is_ready() ) {
			return false;
		}
		return (bool) apply_filters( 'pn_fts_should_apply', true, $query );
	}

	/**
	 * @return bool
	 */
	protected function is_ready() {
		if ( null !== $this->ready ) {
			return $this->ready;
		}
		global $wpdb;
		$view_raw    = self::view_name_raw();
		$exists      = $wpdb->get_var( $wpdb->prepare( 'SELECT TOP 1 name FROM sys.views WHERE name = %s', $view_raw ) );
		$this->ready = ! empty( $exists );
		return $this->ready;
	}

	/**
	 * True only if the live view has a post_title column.
	 * Do not trust pn_fts_view_flavor — 1.1 concat views and failed
	 * split builds leave that option on 'split' and 207 on CONTAINSTABLE(..., post_title).
	 *
	 * @return bool
	 */
	protected function view_is_split() {
		if ( null !== $this->flavor_split ) {
			return $this->flavor_split;
		}
		global $wpdb;
		$col = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT TOP 1 name FROM sys.columns WHERE object_id = OBJECT_ID(%s) AND name = %s',
				'dbo.' . self::view_name_raw(),
				'post_title'
			)
		);
		$this->flavor_split = ! empty( $col );
		$stored             = get_option( self::OPTION_FLAVOR, '' );
		$actual             = $this->flavor_split ? 'split' : 'concat';
		if ( $stored !== $actual ) {
			update_option( self::OPTION_FLAVOR, $actual, true );
		}
		return $this->flavor_split;
	}

	/**
	 * @param WP_Query $query Query.
	 * @return string
	 */
	protected function build_predicate( $query ) {
		$qv = $query->query_vars;
		$s  = isset( $qv['s'] ) ? (string) $qv['s'] : '';
		$s  = str_replace( array( "\r", "\n" ), '', $s );
		if ( '' === $s ) {
			return '';
		}

		$inflect  = (bool) apply_filters( 'pn_fts_inflectional', false, $query );
		$exact    = ! empty( $qv['exact'] );
		$sentence = ! empty( $qv['sentence'] );

		if ( $sentence || ( empty( $qv['search_terms'] ) && '' !== $s ) ) {
			$tokens = $this->fts_indexable_tokens( $s );
			if ( empty( $tokens ) ) {
				return '';
			}
			$atoms = array();
			foreach ( $tokens as $tok ) {
				$a = $this->fts_atom( $tok, false, $inflect );
				if ( $a ) {
					$atoms[] = $a;
				}
			}
			return implode( ' AND ', $atoms );
		}

		$terms = isset( $qv['search_terms'] ) ? (array) $qv['search_terms'] : array();
		if ( empty( $terms ) ) {
			return '';
		}

		$exclusion_prefix = apply_filters( 'wp_query_search_exclusion_prefix', '-' );
		$parts            = array();

		foreach ( $terms as $term ) {
			$term    = (string) $term;
			$exclude = $exclusion_prefix && $exclusion_prefix !== '' && str_starts_with( $term, $exclusion_prefix );
			if ( $exclude ) {
				$term = substr( $term, strlen( $exclusion_prefix ) );
			}
			$term = trim( $term );
			if ( '' === $term ) {
				continue;
			}

			$is_phrase = ( false !== strpos( $term, ' ' ) );
			$tokens    = $this->fts_indexable_tokens( $term );
			if ( empty( $tokens ) ) {
				continue;
			}
			$stripped = ( count( $tokens ) !== count( preg_split( '/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY ) ) );

			if ( $is_phrase && count( $tokens ) > 1 && ! $stripped ) {
				$atom = $this->fts_atom( implode( ' ', $tokens ), false, false );
			} else {
				$use_prefix = (bool) apply_filters( 'pn_fts_prefix', false, $term, $query );
				$use_prefix = $use_prefix && ! $exact && ! $inflect;
				$atoms      = array();
				foreach ( $tokens as $tok ) {
					$a = $this->fts_atom( $tok, $use_prefix, $inflect );
					if ( $a ) {
						$atoms[] = $a;
					}
				}
				$atom = implode( ' AND ', $atoms );
			}
			if ( '' === $atom ) {
				continue;
			}
			$parts[] = $exclude ? ( 'NOT (' . $atom . ')' ) : $atom;
		}

		$parts = array_slice( $parts, 0, 9 );
		if ( empty( $parts ) ) {
			return '';
		}

		return implode( ' AND ', $parts );
	}

	/**
	 * Drop tokens SQL Server FTS will treat as noise (1-letter English, WP stopwords).
	 * "vitamin C" → array( 'vitamin' ). "hello world" → both kept.
	 *
	 * @param string $term Term or phrase.
	 * @return string[]
	 */
	protected function fts_indexable_tokens( $term ) {
		$term  = trim( $term );
		$term  = trim( $term, "\"'" );
		$parts = preg_split( '/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY );
		$stop  = array(
			'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'c',
			'com', 'for', 'from', 'how', 'i', 'in', 'is', 'it', 'not', 'of',
			'on', 'or', 's', 't', 'that', 'the', 'this', 'to', 'was', 'what',
			'when', 'where', 'who', 'will', 'with', 'www',
		);
		$stop = apply_filters( 'pn_fts_noise_tokens', $stop );
		$out  = array();
		foreach ( $parts as $p ) {
			$p = trim( $p, "\"'" );
			if ( '' === $p ) {
				continue;
			}
			$low = function_exists( 'mb_strtolower' ) ? mb_strtolower( $p ) : strtolower( $p );
			if ( in_array( $low, $stop, true ) ) {
				continue;
			}
			if ( 1 === strlen( $p ) && ! preg_match( '/[0-9]/', $p ) ) {
				continue;
			}
			$out[] = $p;
		}
		return $out;
	}

	/**
	 * @param string $term    Raw term (no surrounding quotes).
	 * @param bool   $prefix  Append *.
	 * @param bool   $inflect FORMSOF.
	 * @return string
	 */
	protected function fts_atom( $term, $prefix, $inflect = false ) {
		$term = trim( $term );
		$term = trim( $term, "\"'" );
		if ( '' === $term ) {
			return '';
		}
		$term = str_replace( '"', '""', $term );
		if ( $inflect ) {
			return 'FORMSOF(INFLECTIONAL, "' . $term . '")';
		}
		if ( $prefix && false === strpos( $term, ' ' ) ) {
			return '"' . $term . '*"';
		}
		return '"' . $term . '"';
	}

	/**
	 * @param string $pred FTS predicate.
	 * @return bool
	 */
	protected function fts_has_hit( $pred ) {
		global $wpdb;
		$view             = self::view_name();
		$wpdb->last_error = '';
		$key              = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT TOP 1 [KEY] FROM CONTAINSTABLE(dbo.{$view}, *, %s) AS pnft_probe",
				$pred
			)
		);
		if ( $wpdb->last_error ) {
			return false;
		}
		return null !== $key && false !== $key && '' !== $key;
	}

	/**
	 * @param string   $search SQL.
	 * @param WP_Query $query  Query.
	 * @return string
	 */
	public function posts_search( $search, $query ) {
		if ( ! $this->should_apply( $query ) ) {
			return $search;
		}

		$pred = $this->build_predicate( $query );
		if ( '' === $pred ) {
			$query->pn_fts_pred = '';
			return $search;
		}

		$query->pn_fts_pred = $pred;

		global $wpdb;
		$out = '';
		if ( ! is_user_logged_in() ) {
			$out = " AND ({$wpdb->posts}.post_password = '') ";
		}
		return $out;
	}

	/**
	 * @param string   $join  JOIN.
	 * @param WP_Query $query Query.
	 * @return string
	 */
	public function posts_join( $join, $query ) {
		if ( empty( $query->pn_fts_pred ) ) {
			return $join;
		}
		if ( false !== strpos( $join, 'pnftsearch' ) ) {
			return $join;
		}

		global $wpdb;
		$view = self::view_name();
		$pred = $query->pn_fts_pred;

		$top = (int) apply_filters( 'pn_fts_top_n', 1000, $query );
		if ( $top < 1 ) {
			$top = 1000;
		}

		// Derived table so ORDER BY post_date (admin) cannot scan wp_posts
		// and probe FTS per row. CONTAINSTABLE runs first, at most $top keys.
		$join .= $wpdb->prepare(
			" INNER JOIN ( SELECT [KEY], [RANK] FROM CONTAINSTABLE(dbo.{$view}, *, %s, %d) AS pnft_src ) AS pnftsearch ON pnftsearch.[KEY] = {$wpdb->posts}.ID ",
			$pred,
			$top
		);

		if ( $this->view_is_split() ) {
			$join .= $wpdb->prepare(
				" LEFT JOIN ( SELECT [KEY], [RANK] FROM CONTAINSTABLE(dbo.{$view}, post_title, %s, %d) AS pnft_title_src ) AS pnft_title ON pnft_title.[KEY] = {$wpdb->posts}.ID ",
				$pred,
				$top
			);
		}

		return $join;
	}

	/**
	 * @param string   $orderby ORDER BY.
	 * @param WP_Query $query   Query.
	 * @return string
	 */
	public function posts_orderby( $orderby, $query ) {
		if ( empty( $query->pn_fts_pred ) ) {
			return $orderby;
		}

		$requested = $query->get( 'orderby' );
		if ( is_array( $requested ) ) {
			$requested = implode( ' ', array_keys( $requested ) );
		}
		$requested = strtolower( (string) $requested );
		$relevance = array( '', '0', 'none', 'relevance', 'rank' );
		if ( $requested !== '' && ! in_array( $requested, $relevance, true ) ) {
			return $orderby;
		}

		if ( $this->view_is_split() ) {
			// Addition, not * 2: translator sort-casting splits on spaces and turns
			// "RANK * 2" into "RANK, *, 2".
			return '(ISNULL(pnft_title.[RANK], 0) + ISNULL(pnft_title.[RANK], 0) + pnftsearch.[RANK]) DESC';
		}
		return 'pnftsearch.[RANK] DESC';
	}

	/* ---------------------------------------------------------------------
	 * Tools
	 * ------------------------------------------------------------------ */

	public function admin_menu() {
		add_management_page(
			'PN Full Text Search',
			'PN Full Text Search',
			'manage_options',
			'pn-fulltext-search',
			array( $this, 'render_tools' )
		);
	}

	public function admin_init() {
		if ( isset( $_POST['pn_fts_rebuild'] ) && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'pn_fts_rebuild' );
			$result   = self::ensure_schema();
			$redirect = add_query_arg(
				array(
					'page'         => 'pn-fulltext-search',
					'pn_fts_built' => is_wp_error( $result ) ? '0' : '1',
					'pn_fts_msg'   => is_wp_error( $result ) ? rawurlencode( $result->get_error_message() ) : '',
				),
				admin_url( 'tools.php' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}
	}

	public function admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_option( 'pn_fts_pending_build' ) ) {
			$url = admin_url( 'tools.php?page=pn-fulltext-search' );
			echo '<div class="notice notice-warning"><p>PN Full Text Search is active but the catalog still needs to be built. Do not use Plugins → Activate for that on a large Azure site. <a href="' . esc_url( $url ) . '">Tools → PN Full Text Search</a> or run the T-SQL in Azure Query Editor (no 230s cap).</p></div>';
		}
		$status = $this->catalog_status();
		if ( $status && isset( $status['populate'] ) && (int) $status['populate'] === 1 ) {
			echo '<div class="notice notice-info"><p>Project Nami Full Text Search is populating the catalog. Search will fall back to LIKE until that finishes.</p></div>';
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function catalog_status() {
		global $wpdb;
		$view_raw = self::view_name_raw();
		$view_ok  = $wpdb->get_var( $wpdb->prepare( 'SELECT TOP 1 name AS name FROM sys.views WHERE name = %s', $view_raw ) );
		$ft_ok    = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) AS cnt FROM sys.fulltext_indexes WHERE object_id = OBJECT_ID(%s)',
				'dbo.' . $view_raw
			)
		);
		$row      = array(
			'installed'  => self::fts_installed(),
			'view'       => $view_ok,
			'ft_index'   => $ft_ok,
			'rows'       => null,
			'populate'   => null,
			'item_count' => null,
			'flavor'     => get_option( self::OPTION_FLAVOR, '' ),
		);
		if ( $view_ok ) {
			$row['rows'] = $wpdb->get_var( 'SELECT COUNT_BIG(*) AS cnt FROM dbo.' . self::view_name() );
		}
		$row['populate']   = $wpdb->get_var( "SELECT FULLTEXTCATALOGPROPERTY('ftCatalog', 'PopulateStatus') AS populate_status" );
		$row['item_count'] = $wpdb->get_var( "SELECT FULLTEXTCATALOGPROPERTY('ftCatalog', 'ItemCount') AS item_count" );

		// Manual Query Editor rebuilds never write pn_fts_schema_version.
		if ( $view_ok && $ft_ok && (int) get_option( self::OPTION_SCHEMA, 0 ) !== self::SCHEMA_VERSION ) {
			self::mark_ready( self::detect_flavor() );
			$row['flavor'] = get_option( self::OPTION_FLAVOR, '' );
		}

		return $row;
	}

	public function render_tools() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$status = $this->catalog_status();
		$pop    = array(
			0 => 'Idle',
			1 => 'Full population in progress',
			2 => 'Paused',
			3 => 'Throttled',
			4 => 'Recovering',
			5 => 'Shutdown',
			6 => 'Incremental population in progress',
			7 => 'Building index',
			8 => 'Disk full. Paused.',
			9 => 'Change tracking',
		);
		$pop_l = $pop[ (int) $status['populate'] ] ?? (string) $status['populate'];
		if ( isset( $_GET['pn_fts_built'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( '1' === $_GET['pn_fts_built'] ) {
				echo '<div class="notice notice-success is-dismissible"><p>Full-text catalog rebuilt. Population has started.</p></div>';
			} else {
				$msg = isset( $_GET['pn_fts_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['pn_fts_msg'] ) ) : 'Rebuild failed.';
				echo '<div class="notice notice-error"><p>' . esc_html( $msg ) . '</p></div>';
			}
		}
		?>
		<div class="wrap">
			<h1>PN Full Text Search</h1>
			<table class="widefat striped" style="max-width:720px">
				<tbody>
					<tr><th>Full-Text engine (advisory)</th><td><?php echo $status['installed'] ? 'Yes' : 'Unknown / check SQL error on Rebuild'; ?></td></tr>
					<tr><th>View</th><td><?php echo $status['view'] ? esc_html( 'dbo.' . self::view_name_raw() ) : 'Missing'; ?></td></tr>
					<tr><th>View flavor</th><td><?php echo $status['flavor'] ? esc_html( (string) $status['flavor'] ) : '—'; ?></td></tr>
					<tr><th>FT index</th><td><?php echo $status['ft_index'] ? 'Present' : 'Missing'; ?></td></tr>
					<tr><th>Indexed rows (view)</th><td><?php echo null !== $status['rows'] ? esc_html( (string) $status['rows'] ) : '—'; ?></td></tr>
					<tr><th>Catalog item count</th><td><?php echo null !== $status['item_count'] ? esc_html( (string) $status['item_count'] ) : '—'; ?></td></tr>
					<tr><th>Populate status</th><td><?php echo esc_html( $pop_l ); ?></td></tr>
					<tr><th>Schema version</th><td><?php echo esc_html( (string) (int) get_option( self::OPTION_SCHEMA, 0 ) ); ?> / <?php echo esc_html( (string) self::SCHEMA_VERSION ); ?></td></tr>
				</tbody>
			</table>
			<p>Indexed view uses <code>INNER JOIN</code> to <code>users</code> (SQL Server indexed-view rule). Posts whose author is missing are not FTS-searchable; LIKE fallback still finds them.</p>
			<p>Force core LIKE on one request: add <code>?pn_fts=off</code> (administrators). Or <code>'pn_fts' => 'off'</code> on a WP_Query.</p>
			<form method="post">
				<?php wp_nonce_field( 'pn_fts_rebuild' ); ?>
				<?php submit_button( 'Rebuild catalog', 'secondary', 'pn_fts_rebuild' ); ?>
			</form>
		</div>
		<?php
	}
}

new PN_Fulltext_Search();
