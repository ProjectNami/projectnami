<?php
/**
 * Project Nami × Yoast SEO — WP-level shims only.
 *
 * SQL dialect (CREATE TABLE, LIMIT, USE INDEX, INSERT IGNORE, …) is handled in
 * wp-includes/translations.php and the MySQL sniff in wpdb::query(). Do not
 * hook 'query' for general translation here — a second pass mangles T-SQL,
 * and a Yoast-table-name filter misses sitemap SQL on wp_posts.
 *
 * This file is for things the translator cannot see:
 *   1. SQL Server's 2100-parameter cap vs Yoast insert_many(100).
 *   2. Sitemap lastmod: PN stores 0001-01-01, Yoast only treats 0000-00-00 as empty.
 *   3. MySQL @rownum user-variables (Yoast's pre-8.0 sitemap path). Not generic.
 *   4. Admin notice if Yoast is active but yoast_migrations was never created.

 *
 * wordpress-seo/ stays stock. Safe to delete this file; SEO still runs, with
 * the four gaps above.
 *
 * @package ProjectNami
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * sqlsrv allows 2100 parameters per query. Yoast defaults to 100-row bulk
 * inserts; an indexable/link row is ~40 columns → 4000 params.
 *
 * Filter name is wpseo_chunk_bulk_insert_queries (the docblock in orm.php
 * says "bulked"; the apply_filters() call does not).
 */
add_filter( 'wpseo_chunk_bulk_insert_queries', 'pn_yoast_bulk_insert_chunk', 10, 1 );
function pn_yoast_bulk_insert_chunk( $chunk ) {
	$chunk = is_int( $chunk ) ? $chunk : 100;
	if ( $chunk > 25 ) {
		return 25;
	}
	return $chunk;
}

/**
 * Drop empty lastmod/mod values Yoast would otherwise emit as 0001-01-01.
 *
 * @param array  $url  Sitemap URL parts.
 * @param string $type Unused (post|term|user).
 * @param mixed  $obj  Unused.
 * @return array
 */
function pn_yoast_strip_zero_mod( $url, $type = '', $obj = null ) {
	if ( ! is_array( $url ) ) {
		return $url;
	}
	foreach ( array( 'mod', 'lastmod' ) as $key ) {
		if ( ! empty( $url[ $key ] ) && pn_yoast_is_zero_datetime( $url[ $key ] ) ) {
			unset( $url[ $key ] );
		}
	}
	return $url;
}
add_filter( 'wpseo_sitemap_entry', 'pn_yoast_strip_zero_mod', 10, 3 );

/**
 * Same zero-date strip for sitemap_index.xml <lastmod> values.
 *
 * @param array $links Index links.
 * @return array
 */
function pn_yoast_strip_zero_index_lastmod( $links ) {
	if ( ! is_array( $links ) ) {
		return $links;
	}
	foreach ( $links as $i => $link ) {
		if ( is_array( $link ) && ! empty( $link['lastmod'] ) && pn_yoast_is_zero_datetime( $link['lastmod'] ) ) {
			unset( $links[ $i ]['lastmod'] );
		}
	}
	return $links;
}
add_filter( 'wpseo_sitemap_index_links', 'pn_yoast_strip_zero_index_lastmod' );

/**
 * PN empty datetime, MySQL zero date, or W3C forms of either.
 *
 * @param string $value Datetime string.
 * @return bool
 */
function pn_yoast_is_zero_datetime( $value ) {
	if ( ! is_string( $value ) || $value === '' ) {
		return false;
	}
	return (bool) preg_match( '/^000[01]-01-01(?:[ T]00:00:00(?:\.0+)?(?:Z|\\+00:00)?)?$/', $value );
}

/**
 * Rewrite Yoast's MySQL @rownum sitemap pager into the CTE it already uses
 * when it thinks the server is MySQL 8. SQL Server productversion compares
 * >= 8.0 so this should not run; it is a guard if db_version() ever lies.
 *
 * Runs on 'query' at priority 1, before wpdb's dialect sniff.
 *
 * @param string $query SQL.
 * @return string
 */
function pn_yoast_rewrite_rownum_query( $query ) {
	if ( ! is_string( $query ) || false === strpos( $query, '@rownum' ) ) {
		return $query;
	}

	$matched = preg_match(
		'/SELECT\s+(.+?)\s+FROM\s+\(\s*SELECT\s+@rownum\s*:=\s*0\s*\)\s+\w+\s+JOIN\s+(\S+)(?:\s+USE\s+INDEX\s*\([^)]*\))?\s+WHERE\s+(.+?)\s+AND\s+\(\s*@rownum\s*:=\s*@rownum\s*\+\s*1\s*\)\s*%\s*(\d+)\s*=\s*0\s+ORDER\s+BY\s+(.+?)\s*;?\s*$/is',
		trim( $query ),
		$m
	);
	if ( ! $matched ) {
		error_log( 'PN Yoast: @rownum SQL was not rewritten. First 300 chars: ' . substr( $query, 0, 300 ) );
		return $query;
	}

	$select = trim( $m[1] );
	$table  = $m[2];
	$where  = trim( $m[3] );
	$mod    = $m[4];
	$order  = preg_replace( '/\s+(ASC|DESC)\s*$/i', '', trim( $m[5] ) );

	return "WITH _pn_yoast_ord AS (SELECT ROW_NUMBER() OVER (ORDER BY {$order}) AS n, {$select} FROM {$table} WHERE {$where}) SELECT {$select} FROM _pn_yoast_ord WHERE n % {$mod} = 0";
}
add_filter( 'query', 'pn_yoast_rewrite_rownum_query', 1 );

/**
 * Admin notice if Yoast is loaded but its schema table never appeared.
 * Migrations run on plugins_loaded of a normal request, not during
 * register_activation_hook, so this is checked here rather than on activate.
 *
 * @return void
 */
function pn_yoast_missing_schema_notice() {
	if ( ! is_admin() || wp_doing_ajax() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	if ( ! defined( 'WPSEO_VERSION' ) ) {
		return;
	}
	global $wpdb;
	static $missing = null;
	if ( $missing === null ) {
		$table   = $wpdb->prefix . 'yoast_migrations';
		$found   = $wpdb->get_var( $wpdb->prepare(
			'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = %s',
			$table
		) );
		$missing = empty( $found );
	}
	if ( ! $missing ) {
		return;
	}
	echo '<div class="notice notice-error"><p>Yoast SEO is active but <code>' . esc_html( $wpdb->prefix . 'yoast_migrations' ) . '</code> does not exist. Project Nami did not complete Yoast’s migrations. Set <code>ProjectNamiLogTranslate=1</code> and reactivate the plugin.</p></div>';
}
add_action( 'admin_notices', 'pn_yoast_missing_schema_notice' );
