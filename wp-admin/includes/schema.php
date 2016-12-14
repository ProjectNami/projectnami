<?php
/**
 * WordPress Administration Scheme API
 *
 * Here we keep the DB structure and option values.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Declare these as global in case schema.php is included from a function.
 *
 * @global wpdb   $wpdb
 * @global array  $wp_queries
 * @global string $charset_collate
 */
global $wpdb, $wp_queries, $charset_collate;

/**
 * The database character collate.
 */
$charset_collate = $wpdb->get_charset_collate();

/**
 * Retrieve the SQL for creating database tables.
 *
 * @since 3.3.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $scope Optional. The tables for which to retrieve SQL. Can be all, global, ms_global, or blog tables. Defaults to all.
 * @param int $blog_id Optional. The site ID for which to retrieve SQL. Default is the current site ID.
 * @return string The SQL needed to create the requested tables.
 */
function wp_get_db_schema( $scope = 'all', $blog_id = null ) {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	if ( $blog_id && $blog_id != $wpdb->blogid )
		$old_blog_id = $wpdb->set_blog_id( $blog_id );

	// Engage multisite if in the middle of turning it on from network.php.
	$is_multisite = is_multisite() || ( defined( 'WP_INSTALLING_NETWORK' ) && WP_INSTALLING_NETWORK );

	/*
	 * Indexes have a maximum size of 767 bytes. Historically, we haven't need to be concerned about that.
	 * As of 4.2, however, we moved to utf8mb4, which uses 4 bytes per character. This means that an index which
	 * used to have room for floor(767/3) = 255 characters, now only has room for floor(767/4) = 191 characters.
	 */
	$max_index_length = 191;

	// Blog specific tables.
	$blog_tables = "CREATE TABLE $wpdb->termmeta (
  meta_id int NOT NULL identity(1,1),
  term_id int NOT NULL default 0,
  meta_key nvarchar(255) default NULL,
  meta_value nvarchar(max),
  CONSTRAINT $wpdb->termmeta" . "_PK PRIMARY KEY NONCLUSTERED  (meta_id)
)
GO
CREATE CLUSTERED INDEX $wpdb->termmeta" . "_CLU1 on $wpdb->termmeta (term_id)
GO
CREATE INDEX $wpdb->termmeta" . "_IDX2 on $wpdb->termmeta (meta_key)
GO
CREATE TABLE $wpdb->terms (
 term_id int NOT NULL identity(1,1),
 name nvarchar(200) NOT NULL default '',
 slug nvarchar(200) NOT NULL default '',
 term_group int NOT NULL default 0,
 constraint $wpdb->terms" . "_PK PRIMARY KEY (term_id)
)
GO
CREATE INDEX $wpdb->terms" . "_IDX1 on $wpdb->terms (slug)
GO
CREATE INDEX $wpdb->terms" . "_IDX2 on $wpdb->terms (name)
GO
CREATE TABLE $wpdb->term_taxonomy (
 term_taxonomy_id int NOT NULL identity(1,1),
 term_id int NOT NULL default 0,
 taxonomy nvarchar(32) NOT NULL default '',
 description nvarchar(max) NOT NULL,
 parent int NOT NULL default 0,
 count int NOT NULL default 0,
 constraint $wpdb->term_taxonomy" . "_PK PRIMARY KEY NONCLUSTERED (term_taxonomy_id)
)

GO
CREATE UNIQUE CLUSTERED INDEX $wpdb->term_taxonomy" . "_CLU1 on $wpdb->term_taxonomy (term_id,taxonomy)
GO
CREATE INDEX $wpdb->term_taxonomy" . "_IDX2 on $wpdb->term_taxonomy (taxonomy)
GO

CREATE TABLE $wpdb->term_relationships (
 object_id int NOT NULL default 0,
 term_taxonomy_id int NOT NULL default 0,
 term_order int NOT NULL default 0,
 CONSTRAINT $wpdb->term_relationships" . "_PK PRIMARY KEY NONCLUSTERED (object_id,term_taxonomy_id)
)
GO
CREATE CLUSTERED INDEX $wpdb->term_relationships" . "_CLU1 on $wpdb->term_relationships (term_taxonomy_id)
GO

CREATE TABLE $wpdb->commentmeta (
  meta_id int NOT NULL identity(1,1),
  comment_id int NOT NULL default 0,
  meta_key nvarchar(255) default NULL,
  meta_value nvarchar(max),
  CONSTRAINT $wpdb->commentmeta" . "_PK PRIMARY KEY NONCLUSTERED  (meta_id)
)
GO
CREATE CLUSTERED INDEX $wpdb->commentmeta" . "_CLU1 on $wpdb->commentmeta (comment_id)
GO
CREATE INDEX $wpdb->commentmeta" . "_IDX2 on $wpdb->commentmeta (meta_key)
GO

CREATE TABLE $wpdb->comments (
  comment_ID int NOT NULL identity(1,1),
  comment_post_ID int NOT NULL default '0',
  comment_author nvarchar(255) NOT NULL,
  comment_author_email nvarchar(100) NOT NULL default '',
  comment_author_url nvarchar(200) NOT NULL default '',
  comment_author_IP nvarchar(100) NOT NULL default '',
  comment_date datetime2(0) NOT NULL default '0001-01-01 00:00:00',
  comment_date_gmt datetime2(0) NOT NULL default '0001-01-01 00:00:00',
  comment_content nvarchar(max) NOT NULL,
  comment_karma int NOT NULL default 0,
  comment_approved nvarchar(20) NOT NULL default '1',
  comment_agent nvarchar(255) NOT NULL default '',
  comment_type nvarchar(20) NOT NULL default '',
  comment_parent int NOT NULL default '0',
  user_id int NOT NULL default '0',
  constraint $wpdb->comments" . "_PK PRIMARY KEY NONCLUSTERED (comment_ID)
 )
GO
CREATE CLUSTERED INDEX $wpdb->comments" . "_CLU1 on $wpdb->comments (comment_post_ID)
GO
CREATE INDEX $wpdb->comments" . "_IDX2 on $wpdb->comments (comment_approved,comment_date_gmt)
GO
CREATE INDEX $wpdb->comments" . "_IDX3 on $wpdb->comments (comment_date_gmt)
GO
CREATE INDEX $wpdb->comments" . "_IDX4 on $wpdb->comments (comment_parent)
GO
CREATE INDEX $wpdb->comments" . "_IDX5 on $wpdb->comments (comment_author_email)
GO

CREATE TABLE $wpdb->links (
  link_id int NOT NULL identity(1,1),
  link_url nvarchar(255) NOT NULL default '',
  link_name nvarchar(255) NOT NULL default '',
  link_image nvarchar(255) NOT NULL default '',
  link_target nvarchar(25) NOT NULL default '',
  link_description nvarchar(255) NOT NULL default '',
  link_visible nvarchar(20) NOT NULL default 'Y',
  link_owner int NOT NULL default 1,
  link_rating int NOT NULL default 0,
  link_updated datetime2(0) NOT NULL default '0001-01-01 00:00:00',
  link_rel nvarchar(255) NOT NULL default '',
  link_notes nvarchar(max) NOT NULL,
  link_rss nvarchar(255) NOT NULL default '',
  constraint $wpdb->links" . "_PK PRIMARY KEY  (link_id)
)
GO
CREATE INDEX $wpdb->links" . "_IDX1 on $wpdb->links (link_visible)
GO

CREATE TABLE $wpdb->options (
  option_id int NOT NULL identity(1,1),
  option_name nvarchar(64) NOT NULL default '',
  option_value nvarchar(max) NOT NULL,
  autoload nvarchar(20) NOT NULL default 'yes',
  constraint $wpdb->options" . "_PK PRIMARY KEY  (option_id)
)
GO
CREATE UNIQUE INDEX $wpdb->options" . "_UK1 on $wpdb->options (option_name)
GO

CREATE TABLE $wpdb->postmeta (
  meta_id int NOT NULL identity(1,1),
  post_id int NOT NULL default 0,
  meta_key nvarchar(255) default NULL,
  meta_value nvarchar(max),
  constraint $wpdb->postmeta" . "_PK PRIMARY KEY NONCLUSTERED (meta_id)
)
GO
CREATE CLUSTERED INDEX $wpdb->postmeta" . "_CLU1 on $wpdb->postmeta (post_id)
GO
CREATE INDEX $wpdb->postmeta" . "_IDX2 on $wpdb->postmeta (meta_key)
GO

CREATE TABLE $wpdb->posts (
  ID int NOT NULL identity(1,1),
  post_author int NOT NULL default 0,
  post_date datetime2(0) NOT NULL default '0001-01-01 00:00:00',
  post_date_gmt datetime2(0) NOT NULL default '0001-01-01 00:00:00',
  post_content nvarchar(max) NOT NULL,
  post_title nvarchar(max) NOT NULL,
  post_excerpt nvarchar(max) NOT NULL,
  post_status nvarchar(20) NOT NULL default 'publish',
  comment_status nvarchar(20) NOT NULL default 'open',
  ping_status nvarchar(20) NOT NULL default 'open',
  post_password nvarchar(255) NOT NULL default '',
  post_name nvarchar(200) NOT NULL default '',
  to_ping nvarchar(max) NOT NULL,
  pinged nvarchar(max) NOT NULL,
  post_modified datetime2(0) NOT NULL default '0001-01-01 00:00:00',
  post_modified_gmt datetime2(0) NOT NULL default '0001-01-01 00:00:00',
  post_content_filtered nvarchar(max) NOT NULL,
  post_parent int NOT NULL default 0,
  guid nvarchar(255) NOT NULL default '',
  menu_order int NOT NULL default 0,
  post_type nvarchar(20) NOT NULL default 'post',
  post_mime_type nvarchar(100) NOT NULL default '',
  comment_count int NOT NULL default 0,
  constraint $wpdb->posts" . "_PK PRIMARY KEY  (ID)
)
GO
CREATE INDEX $wpdb->posts" . "_IDX1 on $wpdb->posts (post_name)
GO
CREATE INDEX $wpdb->posts" . "_IDX2 on $wpdb->posts (post_type,post_status,post_date,ID)
GO
CREATE INDEX $wpdb->posts" . "_IDX3 on $wpdb->posts (post_parent)
GO
CREATE INDEX $wpdb->posts" . "_IDX4 on $wpdb->posts (post_author)
GO\n";

	// Users table
	$users_table = "CREATE TABLE $wpdb->users (
  ID int NOT NULL identity(1,1),
  user_login nvarchar(60) NOT NULL default '',
  user_pass nvarchar(64) NOT NULL default '',
  user_nicename nvarchar(50) NOT NULL default '',
  user_email nvarchar(100) NOT NULL default '',
  user_url nvarchar(100) NOT NULL default '',
  user_registered datetime2(0) NOT NULL default '0001-01-01 00:00:00',
  user_activation_key nvarchar(60) NOT NULL default '',
  user_status int NOT NULL default 0,
  display_name nvarchar(250) NOT NULL default '',
  spam tinyint NOT NULL default 0,
  deleted tinyint NOT NULL default 0,
  constraint $wpdb->users" . "_PK PRIMARY KEY  (ID)
)
GO
CREATE INDEX $wpdb->users" . "_IDX1 on $wpdb->users (user_login)
GO
CREATE INDEX $wpdb->users" . "_IDX2 on $wpdb->users (user_nicename)
GO\n";

	// Usermeta.
	$usermeta_table = "CREATE TABLE $wpdb->usermeta (
  umeta_id int NOT NULL identity(1,1),
  user_id int NOT NULL default 0,
  meta_key nvarchar(255) default NULL,
  meta_value nvarchar(max),
  constraint $wpdb->usermeta" . "_PK PRIMARY KEY NONCLUSTERED (umeta_id)
)
GO
CREATE CLUSTERED INDEX $wpdb->usermeta" . "_CLU1 on $wpdb->usermeta (user_id)
GO
CREATE INDEX $wpdb->usermeta" . "_IDX2 on $wpdb->usermeta (meta_key)
GO\n";

	// Global tables
	$global_tables = $users_table . $usermeta_table;

	// Multisite global tables.
	$ms_global_tables = "CREATE TABLE $wpdb->blogs (
  blog_id int NOT NULL identity(1,1),
  site_id int NOT NULL default 0,
  domain nvarchar(200) NOT NULL default '',
  path nvarchar(100) NOT NULL default '',
  registered datetime2(0) NOT NULL default '0001-01-01 00:00:00',
  last_updated datetime2(0) NOT NULL default '0001-01-01 00:00:00',
  [public] tinyint NOT NULL default 1,
  archived tinyint NOT NULL default 0,
  mature tinyint NOT NULL default 0,
  spam tinyint NOT NULL default 0,
  deleted tinyint NOT NULL default 0,
  lang_id int NOT NULL default 0,
  constraint $wpdb->blogs" . "_PK PRIMARY KEY  (blog_id)
)
GO
CREATE INDEX $wpdb->blogs" . "_IDX1 on $wpdb->blogs (domain,path)
GO
CREATE INDEX $wpdb->blogs" . "_IDX2 on $wpdb->blogs (lang_id)
GO

CREATE TABLE $wpdb->blog_versions (
  blog_id int NOT NULL default 0,
  db_version nvarchar(20) NOT NULL default '',
  last_updated datetime2(0) NOT NULL default '0001-01-01 00:00:00',
  constraint $wpdb->blog_versions" . "_PK PRIMARY KEY  (blog_id)
)
GO
CREATE INDEX $wpdb->blog_versions" . "_IDX1 on $wpdb->blog_versions (db_version)
GO

CREATE TABLE $wpdb->registration_log (
  ID int NOT NULL identity(1,1),
  email nvarchar(255) NOT NULL default '',
  IP nvarchar(30) NOT NULL default '',
  blog_id int NOT NULL default 0,
  date_registered datetime2(0) NOT NULL default '0001-01-01 00:00:00',
  constraint $wpdb->registration_log" . "_PK PRIMARY KEY  (ID)
)
GO
CREATE INDEX $wpdb->registration_log" . "_IDX1 on $wpdb->registration_log (IP)
GO

CREATE TABLE $wpdb->site (
  id int NOT NULL identity(1,1),
  domain nvarchar(200) NOT NULL default '',
  path nvarchar(100) NOT NULL default '',
  constraint $wpdb->site" . "_PK PRIMARY KEY  (id)
)
GO
CREATE INDEX $wpdb->site" . "_IDX1 on $wpdb->site (domain,path)
GO

CREATE TABLE $wpdb->sitemeta (
  meta_id int NOT NULL identity(1,1),
  site_id int NOT NULL default 0,
  meta_key nvarchar(255) default NULL,
  meta_value nvarchar(max),
  constraint $wpdb->sitemeta" . "_PK PRIMARY KEY NONCLUSTERED (meta_id)
)
GO
CREATE INDEX $wpdb->sitemeta" . "_IDX1 on $wpdb->sitemeta (meta_key)
GO
CREATE CLUSTERED INDEX $wpdb->sitemeta" . "_CLU2 on $wpdb->sitemeta (site_id)
GO

CREATE TABLE $wpdb->signups (
  signup_id int NOT NULL identity(1,1),
  domain nvarchar(200) NOT NULL default '',
  path nvarchar(100) NOT NULL default '',
  title nvarchar(max) NOT NULL,
  user_login nvarchar(60) NOT NULL default '',
  user_email nvarchar(100) NOT NULL default '',
  registered datetime2(0) NOT NULL default '0001-01-01 00:00:00',
  activated datetime2(0) NOT NULL default '0001-01-01 00:00:00',
  active tinyint NOT NULL default 0,
  activation_key nvarchar(50) NOT NULL default '',
  meta nvarchar(max) NULL,
  constraint $wpdb->signups" . "_PK PRIMARY KEY  (signup_id)
)

GO
CREATE INDEX $wpdb->signups" . "_IDX1 on $wpdb->signups (activation_key)
GO
CREATE INDEX $wpdb->signups" . "_IDX2 on $wpdb->signups (domain,path)
GO
CREATE INDEX $wpdb->signups" . "_IDX3 on $wpdb->signups (user_email)
GO
CREATE INDEX $wpdb->signups" . "_IDX4 on $wpdb->signups (user_login,user_email)
GO";

	switch ( $scope ) {
		case 'blog' :
			$queries = $blog_tables;
			break;
		case 'global' :
			$queries = $global_tables;
			if ( $is_multisite )
				$queries .= $ms_global_tables;
			break;
		case 'ms_global' :
			$queries = $ms_global_tables;
			break;
		case 'all' :
		default:
			$queries = $global_tables . $blog_tables;
			if ( $is_multisite )
				$queries .= $ms_global_tables;
			break;
	}

	if ( isset( $old_blog_id ) )
		$wpdb->set_blog_id( $old_blog_id );

	return $queries;
}

// Populate for back compat.
$wp_queries = wp_get_db_schema( 'all' );

/**
 * Create WordPress options and set the default values.
 *
 * @since 1.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @global int  $wp_db_version
 * @global int  $wp_current_db_version
 */
function populate_options() {
	global $wpdb, $wp_db_version, $wp_current_db_version;

	$guessurl = wp_guess_url();
	/**
	 * Fires before creating WordPress options and populating their default values.
	 *
	 * @since 2.6.0
	 */
	do_action( 'populate_options' );

	if ( ini_get('safe_mode') ) {
		// Safe mode can break mkdir() so use a flat structure by default.
		$uploads_use_yearmonth_folders = 0;
	} else {
		$uploads_use_yearmonth_folders = 1;
	}

	$template = WP_DEFAULT_THEME;
	// If default theme is a child theme, we need to get its template
	$theme = wp_get_theme( $template );
	if ( ! $theme->errors() )
		$template = $theme->get_template();

	$timezone_string = '';
	$gmt_offset = 0;
	/* translators: default GMT offset or timezone string. Must be either a valid offset (-12 to 14)
	   or a valid timezone string (America/New_York). See https://secure.php.net/manual/en/timezones.php
	   for all timezone strings supported by PHP.
	*/
	$offset_or_tz = _x( '0', 'default GMT offset or timezone string' );
	if ( is_numeric( $offset_or_tz ) )
		$gmt_offset = $offset_or_tz;
	elseif ( $offset_or_tz && in_array( $offset_or_tz, timezone_identifiers_list() ) )
			$timezone_string = $offset_or_tz;

	$options = array(
	'siteurl' => $guessurl,
	'home' => $guessurl,
	'blogname' => __('My Site'),
	/* translators: site tagline */
	'blogdescription' => __('Just another WordPress site'),
	'users_can_register' => 0,
	'admin_email' => 'you@example.com',
	/* translators: default start of the week. 0 = Sunday, 1 = Monday */
	'start_of_week' => _x( '1', 'start of week' ),
	'use_balanceTags' => 0,
	'use_smilies' => 1,
	'require_name_email' => 1,
	'comments_notify' => 1,
	'posts_per_rss' => 10,
	'rss_use_excerpt' => 0,
	'mailserver_url' => 'mail.example.com',
	'mailserver_login' => 'login@example.com',
	'mailserver_pass' => 'password',
	'mailserver_port' => 110,
	'default_category' => 1,
	'default_comment_status' => 'open',
	'default_ping_status' => 'open',
	'default_pingback_flag' => 1,
	'posts_per_page' => 10,
	/* translators: default date format, see https://secure.php.net/date */
	'date_format' => __('F j, Y'),
	/* translators: default time format, see https://secure.php.net/date */
	'time_format' => __('g:i a'),
	/* translators: links last updated date format, see https://secure.php.net/date */
	'links_updated_date_format' => __('F j, Y g:i a'),
	'comment_moderation' => 0,
	'moderation_notify' => 1,
	'permalink_structure' => '',
	'rewrite_rules' => '',
	'gzipcompression' => 0,
	'hack_file' => 0,
	'blog_charset' => 'UTF-8',
	'moderation_keys' => '',
	'active_plugins' => array(),
	'category_base' => '',
	'ping_sites' => 'http://rpc.pingomatic.com/',
	'advanced_edit' => 0,
	'comment_max_links' => 2,
	'gmt_offset' => $gmt_offset,

	// 1.5
	'default_email_category' => 1,
	'recently_edited' => '',
	'template' => $template,
	'stylesheet' => WP_DEFAULT_THEME,
	'comment_whitelist' => 1,
	'blacklist_keys' => '',
	'comment_registration' => 0,
	'html_type' => 'text/html',

	// 1.5.1
	'use_trackback' => 0,

	// 2.0
	'default_role' => 'subscriber',
	'db_version' => $wp_db_version,

	// 2.0.1
	'uploads_use_yearmonth_folders' => $uploads_use_yearmonth_folders,
	'upload_path' => '',

	// 2.1
	'blog_public' => '1',
	'default_link_category' => 2,
	'show_on_front' => 'posts',

	// 2.2
	'tag_base' => '',

	// 2.5
	'show_avatars' => '1',
	'avatar_rating' => 'G',
	'upload_url_path' => '',
	'thumbnail_size_w' => 150,
	'thumbnail_size_h' => 150,
	'thumbnail_crop' => 1,
	'medium_size_w' => 300,
	'medium_size_h' => 300,

	// 2.6
	'avatar_default' => 'mystery',

	// 2.7
	'large_size_w' => 1024,
	'large_size_h' => 1024,
	'image_default_link_type' => 'file',
	'image_default_size' => '',
	'image_default_align' => '',
	'close_comments_for_old_posts' => 0,
	'close_comments_days_old' => 14,
	'thread_comments' => 1,
	'thread_comments_depth' => 5,
	'page_comments' => 0,
	'comments_per_page' => 50,
	'default_comments_page' => 'newest',
	'comment_order' => 'asc',
	'sticky_posts' => array(),
	'widget_categories' => array(),
	'widget_text' => array(),
	'widget_rss' => array(),
	'uninstall_plugins' => array(),

	// 2.8
	'timezone_string' => $timezone_string,

	// 3.0
	'page_for_posts' => 0,
	'page_on_front' => 0,

	// 3.1
	'default_post_format' => 0,

	// 3.5
	'link_manager_enabled' => 0,

	// 4.3.0
	'finished_splitting_shared_terms' => 1,
	);

	// 3.3
	if ( ! is_multisite() ) {
		$options['initial_db_version'] = ! empty( $wp_current_db_version ) && $wp_current_db_version < $wp_db_version
			? $wp_current_db_version : $wp_db_version;
	}

	// 3.0 multisite
	if ( is_multisite() ) {
		/* translators: site tagline */
		$options[ 'blogdescription' ] = sprintf(__('Just another %s site'), get_network()->site_name );
		$options[ 'permalink_structure' ] = '/%year%/%monthnum%/%day%/%postname%/';
	}

	// Set autoload to no for these options
	$fat_options = array( 'moderation_keys', 'recently_edited', 'blacklist_keys', 'uninstall_plugins' );

	$keys = "'" . implode( "', '", array_keys( $options ) ) . "'";
	$existing_options = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name in ( $keys )" );

	$insert = '';
	foreach ( $options as $option => $value ) {
		if ( in_array($option, $existing_options) )
			continue;
		if ( in_array($option, $fat_options) )
			$autoload = 'no';
		else
			$autoload = 'yes';

		if ( is_array($value) )
			$value = serialize($value);
		if ( !empty($insert) )
			$insert .= ', ';
		$insert .= $wpdb->prepare( "(%s, %s, %s)", $option, $value, $autoload );
	}

	if ( !empty($insert) )
		$wpdb->query("INSERT INTO $wpdb->options (option_name, option_value, autoload) VALUES " . $insert);

	// In case it is set, but blank, update "home".
	if ( !__get_option('home') ) update_option('home', $guessurl);

	// Delete unused options.
	$unusedoptions = array(
		'blodotgsping_url', 'bodyterminator', 'emailtestonly', 'phoneemail_separator', 'smilies_directory',
		'subjectprefix', 'use_bbcode', 'use_blodotgsping', 'use_phoneemail', 'use_quicktags', 'use_weblogsping',
		'weblogs_cache_file', 'use_preview', 'use_htmltrans', 'smilies_directory', 'fileupload_allowedusers',
		'use_phoneemail', 'default_post_status', 'default_post_category', 'archive_mode', 'time_difference',
		'links_minadminlevel', 'links_use_adminlevels', 'links_rating_type', 'links_rating_char',
		'links_rating_ignore_zero', 'links_rating_single_image', 'links_rating_image0', 'links_rating_image1',
		'links_rating_image2', 'links_rating_image3', 'links_rating_image4', 'links_rating_image5',
		'links_rating_image6', 'links_rating_image7', 'links_rating_image8', 'links_rating_image9',
		'links_recently_updated_time', 'links_recently_updated_prepend', 'links_recently_updated_append',
		'weblogs_cacheminutes', 'comment_allowed_tags', 'search_engine_friendly_urls', 'default_geourl_lat',
		'default_geourl_lon', 'use_default_geourl', 'weblogs_xml_url', 'new_users_can_blog', '_wpnonce',
		'_wp_http_referer', 'Update', 'action', 'rich_editing', 'autosave_interval', 'deactivated_plugins',
		'can_compress_scripts', 'page_uris', 'update_core', 'update_plugins', 'update_themes', 'doing_cron',
		'random_seed', 'rss_excerpt_length', 'secret', 'use_linksupdate', 'default_comment_status_page',
		'wporg_popular_tags', 'what_to_show', 'rss_language', 'language', 'enable_xmlrpc', 'enable_app',
		'embed_autourls', 'default_post_edit_rows',
	);
	foreach ( $unusedoptions as $option )
		delete_option($option);

	/*
	 * Note ( Project Nami ): We aren't upgrading anything so we shouldn't have to worry about deleting old values.
	 * Will remove line after confirmation it's useless.
	 */

	// Delete obsolete magpie stuff.
	// $wpdb->query("DELETE FROM $wpdb->options WHERE option_name REGEXP '^rss_[0-9a-f]{32}(_ts)?$'");

	/*
	 * Deletes all expired transients. The multi-table delete syntax is used
	 * to delete the transient record from table a, and the corresponding
	 * transient_timeout record from table b.
	 */

    /* PN -- Disable multi-table delete until we can work through the SQL
	$time = time();
	$sql = "DELETE a, b FROM $wpdb->options a, $wpdb->options b
		WHERE a.option_name LIKE %s
		AND a.option_name NOT LIKE %s
		AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
		AND b.option_value < %d";
	$wpdb->query( $wpdb->prepare( $sql, $wpdb->esc_like( '_transient_' ) . '%', $wpdb->esc_like( '_transient_timeout_' ) . '%', $time ) );

	if ( is_main_site() && is_main_network() ) {
		$sql = "DELETE a, b FROM $wpdb->options a, $wpdb->options b
			WHERE a.option_name LIKE %s
			AND a.option_name NOT LIKE %s
			AND b.option_name = CONCAT( '_site_transient_timeout_', SUBSTRING( a.option_name, 17 ) )
			AND b.option_value < %d";
		$wpdb->query( $wpdb->prepare( $sql, $wpdb->esc_like( '_site_transient_' ) . '%', $wpdb->esc_like( '_site_transient_timeout_' ) . '%', $time ) );
	}
    */
}

/**
 * Execute WordPress role creation for the various WordPress versions.
 *
 * @since 2.0.0
 */
function populate_roles() {
	populate_roles_160();
	populate_roles_210();
	populate_roles_230();
	populate_roles_250();
	populate_roles_260();
	populate_roles_270();
	populate_roles_280();
	populate_roles_300();
}

/**
 * Create the roles for WordPress 2.0
 *
 * @since 2.0.0
 */
function populate_roles_160() {
	// Add roles

	// Dummy gettext calls to get strings in the catalog.
	/* translators: user role */
	_x('Administrator', 'User role');
	/* translators: user role */
	_x('Editor', 'User role');
	/* translators: user role */
	_x('Author', 'User role');
	/* translators: user role */
	_x('Contributor', 'User role');
	/* translators: user role */
	_x('Subscriber', 'User role');

	add_role('administrator', 'Administrator');
	add_role('editor', 'Editor');
	add_role('author', 'Author');
	add_role('contributor', 'Contributor');
	add_role('subscriber', 'Subscriber');

	// Add caps for Administrator role
	$role =& get_role('administrator');
	$role->add_cap('switch_themes');
	$role->add_cap('edit_themes');
	$role->add_cap('activate_plugins');
	$role->add_cap('edit_plugins');
	$role->add_cap('edit_users');
	$role->add_cap('edit_files');
	$role->add_cap('manage_options');
	$role->add_cap('moderate_comments');
	$role->add_cap('manage_categories');
	$role->add_cap('manage_links');
	$role->add_cap('upload_files');
	$role->add_cap('import');
	$role->add_cap('unfiltered_html');
	$role->add_cap('edit_posts');
	$role->add_cap('edit_others_posts');
	$role->add_cap('edit_published_posts');
	$role->add_cap('publish_posts');
	$role->add_cap('edit_pages');
	$role->add_cap('read');
	$role->add_cap('level_10');
	$role->add_cap('level_9');
	$role->add_cap('level_8');
	$role->add_cap('level_7');
	$role->add_cap('level_6');
	$role->add_cap('level_5');
	$role->add_cap('level_4');
	$role->add_cap('level_3');
	$role->add_cap('level_2');
	$role->add_cap('level_1');
	$role->add_cap('level_0');

	// Add caps for Editor role
	$role =& get_role('editor');
	$role->add_cap('moderate_comments');
	$role->add_cap('manage_categories');
	$role->add_cap('manage_links');
	$role->add_cap('upload_files');
	$role->add_cap('unfiltered_html');
	$role->add_cap('edit_posts');
	$role->add_cap('edit_others_posts');
	$role->add_cap('edit_published_posts');
	$role->add_cap('publish_posts');
	$role->add_cap('edit_pages');
	$role->add_cap('read');
	$role->add_cap('level_7');
	$role->add_cap('level_6');
	$role->add_cap('level_5');
	$role->add_cap('level_4');
	$role->add_cap('level_3');
	$role->add_cap('level_2');
	$role->add_cap('level_1');
	$role->add_cap('level_0');

	// Add caps for Author role
	$role =& get_role('author');
	$role->add_cap('upload_files');
	$role->add_cap('edit_posts');
	$role->add_cap('edit_published_posts');
	$role->add_cap('publish_posts');
	$role->add_cap('read');
	$role->add_cap('level_2');
	$role->add_cap('level_1');
	$role->add_cap('level_0');

	// Add caps for Contributor role
	$role =& get_role('contributor');
	$role->add_cap('edit_posts');
	$role->add_cap('read');
	$role->add_cap('level_1');
	$role->add_cap('level_0');

	// Add caps for Subscriber role
	$role =& get_role('subscriber');
	$role->add_cap('read');
	$role->add_cap('level_0');
}

/**
 * Create and modify WordPress roles for WordPress 2.1.
 *
 * @since 2.1.0
 */
function populate_roles_210() {
	$roles = array('administrator', 'editor');
	foreach ($roles as $role) {
		$role =& get_role($role);
		if ( empty($role) )
			continue;

		$role->add_cap('edit_others_pages');
		$role->add_cap('edit_published_pages');
		$role->add_cap('publish_pages');
		$role->add_cap('delete_pages');
		$role->add_cap('delete_others_pages');
		$role->add_cap('delete_published_pages');
		$role->add_cap('delete_posts');
		$role->add_cap('delete_others_posts');
		$role->add_cap('delete_published_posts');
		$role->add_cap('delete_private_posts');
		$role->add_cap('edit_private_posts');
		$role->add_cap('read_private_posts');
		$role->add_cap('delete_private_pages');
		$role->add_cap('edit_private_pages');
		$role->add_cap('read_private_pages');
	}

	$role =& get_role('administrator');
	if ( ! empty($role) ) {
		$role->add_cap('delete_users');
		$role->add_cap('create_users');
	}

	$role =& get_role('author');
	if ( ! empty($role) ) {
		$role->add_cap('delete_posts');
		$role->add_cap('delete_published_posts');
	}

	$role =& get_role('contributor');
	if ( ! empty($role) ) {
		$role->add_cap('delete_posts');
	}
}

/**
 * Create and modify WordPress roles for WordPress 2.3.
 *
 * @since 2.3.0
 */
function populate_roles_230() {
	$role =& get_role( 'administrator' );

	if ( !empty( $role ) ) {
		$role->add_cap( 'unfiltered_upload' );
	}
}

/**
 * Create and modify WordPress roles for WordPress 2.5.
 *
 * @since 2.5.0
 */
function populate_roles_250() {
	$role =& get_role( 'administrator' );

	if ( !empty( $role ) ) {
		$role->add_cap( 'edit_dashboard' );
	}
}

/**
 * Create and modify WordPress roles for WordPress 2.6.
 *
 * @since 2.6.0
 */
function populate_roles_260() {
	$role =& get_role( 'administrator' );

	if ( !empty( $role ) ) {
		$role->add_cap( 'update_plugins' );
		$role->add_cap( 'delete_plugins' );
	}
}

/**
 * Create and modify WordPress roles for WordPress 2.7.
 *
 * @since 2.7.0
 */
function populate_roles_270() {
	$role =& get_role( 'administrator' );

	if ( !empty( $role ) ) {
		$role->add_cap( 'install_plugins' );
		$role->add_cap( 'update_themes' );
	}
}

/**
 * Create and modify WordPress roles for WordPress 2.8.
 *
 * @since 2.8.0
 */
function populate_roles_280() {
	$role =& get_role( 'administrator' );

	if ( !empty( $role ) ) {
		$role->add_cap( 'install_themes' );
	}
}

/**
 * Create and modify WordPress roles for WordPress 3.0.
 *
 * @since 3.0.0
 */
function populate_roles_300() {
	$role =& get_role( 'administrator' );

	if ( !empty( $role ) ) {
		$role->add_cap( 'update_core' );
		$role->add_cap( 'list_users' );
		$role->add_cap( 'remove_users' );

		/*
		 * Never used, will be removed. create_users or promote_users
		 * is the capability you're looking for.
		 */
		$role->add_cap( 'add_users' );

		$role->add_cap( 'promote_users' );
		$role->add_cap( 'edit_theme_options' );
		$role->add_cap( 'delete_themes' );
		$role->add_cap( 'export' );
	}
}

/**
 * Install Network.
 *
 * @since 3.0.0
 *
 */
if ( !function_exists( 'install_network' ) ) :
function install_network() {
	if ( ! defined( 'WP_INSTALLING_NETWORK' ) )
		define( 'WP_INSTALLING_NETWORK', true );

	dbDelta( wp_get_db_schema( 'global' ) );
}
endif;

/**
 * Populate network settings.
 *
 * @since 3.0.0
 *
 * @global wpdb       $wpdb
 * @global object     $current_site
 * @global int        $wp_db_version
 * @global WP_Rewrite $wp_rewrite
 *
 * @param int    $network_id        ID of network to populate.
 * @param string $domain            The domain name for the network (eg. "example.com").
 * @param string $email             Email address for the network administrator.
 * @param string $site_name         The name of the network.
 * @param string $path              Optional. The path to append to the network's domain name. Default '/'.
 * @param bool   $subdomain_install Optional. Whether the network is a subdomain install or a subdirectory install.
 *                                  Default false, meaning the network is a subdirectory install.
 * @return bool|WP_Error True on success, or WP_Error on warning (with the install otherwise successful,
 *                       so the error code must be checked) or failure.
 */
function populate_network( $network_id = 1, $domain = '', $email = '', $site_name = '', $path = '/', $subdomain_install = false ) {
	global $wpdb, $current_site, $wp_db_version, $wp_rewrite;

	$errors = new WP_Error();
	if ( '' == $domain )
		$errors->add( 'empty_domain', __( 'You must provide a domain name.' ) );
	if ( '' == $site_name )
		$errors->add( 'empty_sitename', __( 'You must provide a name for your network of sites.' ) );

	// Check for network collision.
	if ( $network_id == $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $wpdb->site WHERE id = %d", $network_id ) ) )
		$errors->add( 'siteid_exists', __( 'The network already exists.' ) );

	$site_user = get_user_by( 'email', $email );
	if ( ! is_email( $email ) )
		$errors->add( 'invalid_email', __( 'You must provide a valid e-mail address.' ) );

	if ( $errors->get_error_code() )
		return $errors;

	// Set up site tables.
	$template = get_option( 'template' );
	$stylesheet = get_option( 'stylesheet' );
	$allowed_themes = array( $stylesheet => true );
	if ( $template != $stylesheet )
		$allowed_themes[ $template ] = true;
	if ( WP_DEFAULT_THEME != $stylesheet && WP_DEFAULT_THEME != $template )
		$allowed_themes[ WP_DEFAULT_THEME ] = true;

	if ( 1 == $network_id ) {
		$wpdb->insert( $wpdb->site, array( 'domain' => $domain, 'path' => $path ) );
		$network_id = $wpdb->insert_id;
	} else {
		$wpdb->insert( $wpdb->site, array( 'domain' => $domain, 'path' => $path, 'id' => $network_id ) );
	}

	wp_cache_delete( 'networks_have_paths', 'site-options' );

	if ( !is_multisite() ) {
		$site_admins = array( $site_user->user_login );
		$users = get_users( array( 'fields' => array( 'ID', 'user_login' ) ) );
		if ( $users ) {
			foreach ( $users as $user ) {
				if ( is_super_admin( $user->ID ) && !in_array( $user->user_login, $site_admins ) )
					$site_admins[] = $user->user_login;
			}
		}
	} else {
		$site_admins = get_site_option( 'site_admins' );
	}

	/* translators: Do not translate USERNAME, SITE_NAME, BLOG_URL, PASSWORD: those are placeholders. */
	$welcome_email = __( 'Howdy USERNAME,

Your new SITE_NAME site has been successfully set up at:
BLOG_URL

You can log in to the administrator account with the following information:

Username: USERNAME
Password: PASSWORD
Log in here: BLOG_URLwp-login.php

We hope you enjoy your new site. Thanks!

--The Team @ SITE_NAME' );

	$misc_exts = array(
		// Images.
		'jpg', 'jpeg', 'png', 'gif',
		// Video.
		'mov', 'avi', 'mpg', '3gp', '3g2',
		// "audio".
		'midi', 'mid',
		// Miscellaneous.
		'pdf', 'doc', 'ppt', 'odt', 'pptx', 'docx', 'pps', 'ppsx', 'xls', 'xlsx', 'key',
	);
	$audio_exts = wp_get_audio_extensions();
	$video_exts = wp_get_video_extensions();
	$upload_filetypes = array_unique( array_merge( $misc_exts, $audio_exts, $video_exts ) );

	$sitemeta = array(
		'site_name' => $site_name,
		'admin_email' => $site_user->user_email,
		'admin_user_id' => $site_user->ID,
		'registration' => 'none',
		'upload_filetypes' => implode( ' ', $upload_filetypes ),
		'blog_upload_space' => 100,
		'fileupload_maxk' => 1500,
		'site_admins' => $site_admins,
		'allowedthemes' => $allowed_themes,
		'illegal_names' => array( 'www', 'web', 'root', 'admin', 'main', 'invite', 'administrator', 'files' ),
		'wpmu_upgrade_site' => $wp_db_version,
		'welcome_email' => $welcome_email,
		'first_post' => __( 'Welcome to <a href="SITE_URL">SITE_NAME</a>. This is your first post. Edit or delete it, then start blogging!' ),
		// @todo - network admins should have a method of editing the network siteurl (used for cookie hash)
		'siteurl' => get_option( 'siteurl' ) . '/',
		'add_new_users' => '0',
		'upload_space_check_disabled' => is_multisite() ? get_site_option( 'upload_space_check_disabled' ) : '1',
		'subdomain_install' => intval( $subdomain_install ),
		'global_terms_enabled' => global_terms_enabled() ? '1' : '0',
		'ms_files_rewriting' => is_multisite() ? get_site_option( 'ms_files_rewriting' ) : '0',
		'initial_db_version' => get_option( 'initial_db_version' ),
		'active_sitewide_plugins' => array(),
		'WPLANG' => get_locale(),
	);
	if ( ! $subdomain_install )
		$sitemeta['illegal_names'][] = 'blog';

	/**
	 * Filters meta for a network on creation.
	 *
	 * @since 3.7.0
	 *
	 * @param array $sitemeta   Associative array of network meta keys and values to be inserted.
	 * @param int   $network_id ID of network to populate.
	 */
	$sitemeta = apply_filters( 'populate_network_meta', $sitemeta, $network_id );

	$insert = '';
	foreach ( $sitemeta as $meta_key => $meta_value ) {
		if ( is_array( $meta_value ) )
			$meta_value = serialize( $meta_value );
		if ( !empty( $insert ) )
			$insert .= ', ';
		$insert .= $wpdb->prepare( "( %d, %s, %s)", $network_id, $meta_key, $meta_value );
	}
	$wpdb->query( "INSERT INTO $wpdb->sitemeta ( site_id, meta_key, meta_value ) VALUES " . $insert );

	/*
	 * When upgrading from single to multisite, assume the current site will
	 * become the main site of the network. When using populate_network()
	 * to create another network in an existing multisite environment, skip
	 * these steps since the main site of the new network has not yet been
	 * created.
	 */
	if ( ! is_multisite() ) {
		$current_site = new stdClass;
		$current_site->domain = $domain;
		$current_site->path = $path;
		$current_site->site_name = ucfirst( $domain );
		sqlsrv_query( $wpdb->dbh, "SET IDENTITY_INSERT $wpdb->blogs ON" );
		$wpdb->insert( $wpdb->blogs, array( 'site_id' => $network_id, 'blog_id' => 1, 'domain' => $domain, 'path' => $path, 'registered' => current_time( 'mysql' ) ) );
		$current_site->blog_id = $blog_id = $wpdb->insert_id;
		sqlsrv_query( $wpdb->dbh, "SET IDENTITY_INSERT $wpdb->blogs OFF" );
		update_user_meta( $site_user->ID, 'source_domain', $domain );
		update_user_meta( $site_user->ID, 'primary_blog', $blog_id );

		if ( $subdomain_install )
			$wp_rewrite->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		else
			$wp_rewrite->set_permalink_structure( '/blog/%year%/%monthnum%/%day%/%postname%/' );

		flush_rewrite_rules();
	}
 
	if ( $subdomain_install ) {
		if ( ! $subdomain_install )
			return true;

		$vhost_ok = false;
		$errstr = '';
		$hostname = substr( md5( time() ), 0, 6 ) . '.' . $domain; // Very random hostname!
		$page = wp_remote_get( 'http://' . $hostname, array( 'timeout' => 5, 'httpversion' => '1.1' ) );
		if ( is_wp_error( $page ) )
			$errstr = $page->get_error_message();
		elseif ( 200 == wp_remote_retrieve_response_code( $page ) )
				$vhost_ok = true;

		if ( ! $vhost_ok ) {
			$msg = '<p><strong>' . __( 'Warning! Wildcard DNS may not be configured correctly!' ) . '</strong></p>';
			$msg .= '<p>' . sprintf( __( 'The installer attempted to contact a random hostname (<code>%1$s</code>) on your domain.' ), $hostname );
			if ( ! empty ( $errstr ) )
				$msg .= ' ' . sprintf( __( 'This resulted in an error message: %s' ), '<code>' . $errstr . '</code>' );
			$msg .= '</p>';
			$msg .= '<p>' . __( 'To use a subdomain configuration, you must have a wildcard entry in your DNS. This usually means adding a <code>*</code> hostname record pointing at your web server in your DNS configuration tool.' ) . '</p>';
			$msg .= '<p>' . __( 'You can still use your site but any subdomain you create may not be accessible. If you know your DNS is correct, ignore this message.' ) . '</p>';
			return new WP_Error( 'no_wildcard_dns', $msg );
		}
	}

	return true;
}
