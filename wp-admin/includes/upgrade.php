<?php
/**
 * WordPress Upgrade API
 *
 * Most of the functions are pluggable and can be overwritten.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** Include user installation customization script. */
if ( file_exists( WP_CONTENT_DIR . '/install.php' ) ) {
	require WP_CONTENT_DIR . '/install.php';
}

/** WordPress Administration API */
require_once ABSPATH . 'wp-admin/includes/admin.php';

/** WordPress Schema API */
require_once ABSPATH . 'wp-admin/includes/schema.php';

if ( !function_exists('wp_install') ) :
	/**
	 * Installs the site.
	 *
	 * Runs the required functions to set up and populate the database,
	 * including primary admin user and initial options.
	 *
	 * @since 2.1.0
	 *
	 * @param string $blog_title    Site title.
	 * @param string $user_name     User's username.
	 * @param string $user_email    User's email.
	 * @param bool   $is_public     Whether the site is public.
	 * @param string $deprecated    Optional. Not used.
	 * @param string $user_password Optional. User's chosen password. Default empty (random password).
	 * @param string $language      Optional. Language chosen. Default empty.
	 * @return array {
	 *     Data for the newly installed site.
	 *
	 *     @type string $url              The URL of the site.
	 *     @type int    $user_id          The ID of the site owner.
	 *     @type string $password         The password of the site owner, if their user account didn't already exist.
	 *     @type string $password_message The explanatory message regarding the password.
	 * }
	 */
	function wp_install(
		$blog_title,
		$user_name,
		$user_email,
		$is_public,
		$deprecated = '',
		#[\SensitiveParameter]
		$user_password = '',
		$language = ''
	) {
		if ( !empty( $deprecated ) ) {
			_deprecated_argument( __FUNCTION__, '2.6.0' );
		}

		wp_check_mysql_version();
		wp_cache_flush();
		make_db_current_silent();

		/*
		 * Ensure update checks are delayed after installation.
		 *
		 * This prevents users being presented with a maintenance mode screen
		 * immediately after installation.
		 */
		wp_unschedule_hook( 'wp_version_check' );
		wp_unschedule_hook( 'wp_update_plugins' );
		wp_unschedule_hook( 'wp_update_themes' );

		wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'wp_version_check' );
		wp_schedule_event( time() + ( 1.5 * HOUR_IN_SECONDS ), 'twicedaily', 'wp_update_plugins' );
		wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), 'twicedaily', 'wp_update_themes' );

		populate_options();
		populate_roles();

		update_option('blogname', $blog_title);
		update_option('admin_email', $user_email);
		update_option( 'blog_public', $is_public );

		// Freshness of site - in the future, this could get more specific about actions taken, perhaps.
		update_option( 'fresh_site', 1, false );

		if ( $language ) {
			update_option( 'WPLANG', $language );
		}

		$guessurl = wp_guess_url();

		update_option('siteurl', $guessurl);

		// If not a public site, don't ping.
		if ( ! $is_public ) {
			update_option('default_pingback_flag', 0);
		}

		/*
		 * Create default user. If the user already exists, the user tables are
		 * being shared among sites. Just set the role in that case.
		 */
		$user_id = username_exists($user_name);
		$user_password = trim($user_password);
		$email_password = false;
		$user_created   = false;

		if ( !$user_id && empty($user_password) ) {
			$user_password = wp_generate_password( 12, false );
			$message = __('<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you.');
			$user_id = wp_create_user($user_name, $user_password, $user_email);
			update_user_meta( $user_id, 'default_password_nag', true );
			$email_password = true;
			$user_created   = true;
		} elseif ( ! $user_id ) {
			// Password has been provided.
			$message      = '<em>' . __( 'Your chosen password.' ) . '</em>';
			$user_id      = wp_create_user( $user_name, $user_password, $user_email );
			$user_created = true;
		} else {
			$message = __('User already exists. Password inherited.');
		}

		$user = new WP_User($user_id);
		$user->set_role('administrator');

		if ( $user_created ) {
			$user->user_url = $guessurl;
			wp_update_user( $user );
		}

		wp_install_defaults($user_id);

		wp_install_maybe_enable_pretty_permalinks();

		flush_rewrite_rules();

		wp_new_blog_notification($blog_title, $guessurl, $user_id, ($email_password ? $user_password : __('The password you chose during installation.') ) );

		wp_cache_flush();

		/**
		 * Fires after a site is fully installed.
		 *
		 * @since 3.9.0
		 *
		 * @param WP_User $user The site owner.
		 */
		do_action( 'wp_install', $user );

		return array(
			'url'              => $guessurl,
			'user_id'          => $user_id,
			'password'         => $user_password,
			'password_message' => $message,
		);
	}
endif;

if ( !function_exists('wp_install_defaults') ) :
	/**
	 * Creates the initial content for a newly-installed site.
	 *
	 * Adds the default "Uncategorized" category, the first post (with comment),
	 * first page, and default widgets for default theme for the current version.
	 *
	 * @since 2.1.0
	 *
		 * @global wpdb       $wpdb         WordPress database abstraction object.
		 * @global WP_Rewrite $wp_rewrite   WordPress rewrite component.
	 * @global string     $table_prefix The database table prefix.
	 *
	 * @param int $user_id User ID.
	 */
	function wp_install_defaults( $user_id ) {
		global $wpdb, $wp_rewrite, $table_prefix;

		// Default category.
		$cat_name = __('Uncategorized');
		/* translators: Default category slug. */
		$cat_slug = sanitize_title(_x('Uncategorized', 'Default category slug'));

		$cat_id = 1;

		$wpdb->insert(
			$wpdb->terms,
			array(
				//'term_id'    => $cat_id,
				'name'       => $cat_name,
				'slug'       => $cat_slug,
				'term_group' => 0,
			)
		);
		$term_id = $wpdb->insert_id;
		$wpdb->insert(
			$wpdb->term_taxonomy,
			array(
				'term_id'     => $term_id,
				'taxonomy'    => 'category',
				'description' => '',
				'parent'      => 0,
				'count'       => 1,
			)
		);
		$cat_tt_id = $wpdb->insert_id;

		// First post.
		$now = current_time( 'mysql' );
		$now_gmt = current_time( 'mysql', 1 );
		$first_post_guid = get_option( 'home' ) . '/?p=1';

		if ( is_multisite() ) {
			$first_post = get_site_option( 'first_post' );

			if ( ! $first_post ) {
				$first_post = "<!-- wp:paragraph -->\n<p>" .
					/* translators: First post content. %s: Site link. */
					__( 'Welcome to %s. This is your first post. Edit or delete it, then start writing!' ) .
					"</p>\n<!-- /wp:paragraph -->";
			}

			$first_post = sprintf(
				$first_post,
				sprintf( '<a href="%s">%s</a>', esc_url( network_home_url() ), get_network()->site_name )
			);

			// Back-compat for pre-4.4.
			$first_post = str_replace( 'SITE_URL', esc_url( network_home_url() ), $first_post );
			$first_post = str_replace( 'SITE_NAME', get_network()->site_name, $first_post );
		} else {
			$first_post = "<!-- wp:paragraph -->\n<p>" .
				/* translators: First post content. %s: Site link. */
				__( 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!' ) .
				"</p>\n<!-- /wp:paragraph -->";
		}

		$wpdb->insert(
			$wpdb->posts,
			array(
				'post_author' => $user_id,
				'post_date' => $now,
				'post_date_gmt' => $now_gmt,
				'post_content' => $first_post,
				'post_excerpt' => '',
				'post_title' => __('Hello world!'),
				/* translators: Default post slug. */
				'post_name' => sanitize_title( _x('hello-world', 'Default post slug') ),
				'post_modified' => $now,
				'post_modified_gmt' => $now_gmt,
				'guid' => $first_post_guid,
				'comment_count' => 1,
				'to_ping' => '',
				'pinged' => '',
				'post_content_filtered' => '',
			)
		);

		if ( is_multisite() ) {
			update_posts_count();
		}

		$wpdb->insert(
			$wpdb->term_relationships,
			array(
				'term_taxonomy_id' => $cat_tt_id,
				'object_id'        => 1,
			)
		);

		// Default comment.
		if ( is_multisite() ) {
			$first_comment_author = get_site_option( 'first_comment_author' );
			$first_comment_email = get_site_option( 'first_comment_email' );
			$first_comment_url = get_site_option( 'first_comment_url', network_home_url() );
			$first_comment = get_site_option( 'first_comment' );
		}

		$first_comment_author = ! empty( $first_comment_author ) ? $first_comment_author : __( 'A WordPress Commenter' );
		$first_comment_email = ! empty( $first_comment_email ) ? $first_comment_email : 'wapuu@wordpress.example';
		$first_comment_url    = ! empty( $first_comment_url ) ? $first_comment_url : esc_url( __( 'https://wordpress.org/' ) );
		$first_comment        = ! empty( $first_comment ) ? $first_comment : sprintf(
			/* translators: %s: Gravatar URL. */
			__(
				'Hi, this is a comment.
	To get started with moderating, editing, and deleting comments, please visit the Comments screen in the dashboard.
Commenter avatars come from <a href="%s">Gravatar</a>.'
			),
			/* translators: The localized Gravatar URL. */
			esc_url( __( 'https://gravatar.com/' ) )
 		);
		$wpdb->insert( $wpdb->comments, array(
			'comment_post_ID' => 1,
			'comment_author' => $first_comment_author,
			'comment_author_email' => $first_comment_email,
			'comment_author_url' => $first_comment_url,
			'comment_date' => $now,
			'comment_date_gmt' => $now_gmt,
			'comment_content' => $first_comment,
			'comment_type'         => 'comment',
		));

		// First page.
		if ( is_multisite() )
			$first_page = get_site_option( 'first_page' );

		if ( empty( $first_page ) ) {
			$first_page = "<!-- wp:paragraph -->\n<p>";
				/* translators: First page content. */
			$first_page .= __( "This is an example page. It's different from a blog post because it will stay in one place and will show up in your site navigation (in most themes). Most people start with an About page that introduces them to potential site visitors. It might say something like this:" );
			$first_page .= "</p>\n<!-- /wp:paragraph -->\n\n";

			$first_page .= "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>";
				/* translators: First page content. */
			$first_page .= __( "Hi there! I'm a bike messenger by day, aspiring actor by night, and this is my website. I live in Los Angeles, have a great dog named Jack, and I like pi&#241;a coladas. (And gettin' caught in the rain.)" );
			$first_page .= "</p></blockquote>\n<!-- /wp:quote -->\n\n";

			$first_page .= "<!-- wp:paragraph -->\n<p>";
				/* translators: First page content. */
			$first_page .= __( '...or something like this:' );
			$first_page .= "</p>\n<!-- /wp:paragraph -->\n\n";

			$first_page .= "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>";
				/* translators: First page content. */
			$first_page .= __( 'The XYZ Doohickey Company was founded in 1971, and has been providing quality doohickeys to the public ever since. Located in Gotham City, XYZ employs over 2,000 people and does all kinds of awesome things for the Gotham community.' );
			$first_page .= "</p></blockquote>\n<!-- /wp:quote -->\n\n";

			$first_page .= "<!-- wp:paragraph -->\n<p>";
			$first_page .= sprintf(
					/* translators: First page content. %s: Site admin URL. */
				__( 'As a new WordPress user, you should go to <a href="%s">your dashboard</a> to delete this page and create new pages for your content. Have fun!' ),
				admin_url()
			);
			$first_page .= "</p>\n<!-- /wp:paragraph -->";
		}

		$first_post_guid = get_option('home') . '/?page_id=2';
		$wpdb->insert( $wpdb->posts, array(
			'post_author' => $user_id,
			'post_date' => $now,
			'post_date_gmt' => $now_gmt,
			'post_content' => $first_page,
			'post_excerpt' => '',
			'comment_status' => 'closed',
			'post_title' => __( 'Sample Page' ),
					/* translators: Default page slug. */
			'post_name' => __( 'sample-page' ),
			'post_modified' => $now,
			'post_modified_gmt' => $now_gmt,
			'guid' => $first_post_guid,
			'post_type' => 'page',
			'to_ping' => '',
			'pinged' => '',
			'post_content_filtered' => ''
		));
		$wpdb->insert( $wpdb->postmeta, array( 'post_id' => 2, 'meta_key' => '_wp_page_template', 'meta_value' => 'default' ) );

		// Privacy Policy page.
		if ( is_multisite() ) {
			// Disable by default unless the suggested content is provided.
			$privacy_policy_content = get_site_option( 'default_privacy_policy_content' );
		} else {
			if ( ! class_exists( 'WP_Privacy_Policy_Content' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
			}

			$privacy_policy_content = WP_Privacy_Policy_Content::get_default_content();
		}

		if ( ! empty( $privacy_policy_content ) ) {
			$privacy_policy_guid = get_option( 'home' ) . '/?page_id=3';

			$wpdb->insert(
				$wpdb->posts, array(
					'post_author'           => $user_id,
					'post_date'             => $now,
					'post_date_gmt'         => $now_gmt,
					'post_content'          => $privacy_policy_content,
					'post_excerpt'          => '',
					'comment_status'        => 'closed',
					'post_title'            => __( 'Privacy Policy' ),
						/* translators: Privacy Policy page slug. */
					'post_name'             => __( 'privacy-policy' ),
					'post_modified'         => $now,
					'post_modified_gmt'     => $now_gmt,
					'guid'                  => $privacy_policy_guid,
					'post_type'             => 'page',
					'post_status'           => 'draft',
					'to_ping'               => '',
					'pinged'                => '',
					'post_content_filtered' => '',
				)
			);
			$wpdb->insert(
				$wpdb->postmeta, array(
					'post_id'    => 3,
					'meta_key'   => '_wp_page_template',
					'meta_value' => 'default',
				)
			);
			update_option( 'wp_page_for_privacy_policy', 3 );
		}

		// Set up default widgets for default theme.
 		update_option(
			'widget_block',
 			array(
				2              => array( 'content' => '<!-- wp:search /-->' ),
				3              => array( 'content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading --><h2>' . __( 'Recent Posts' ) . '</h2><!-- /wp:heading --><!-- wp:latest-posts /--></div><!-- /wp:group -->' ),
				4              => array( 'content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading --><h2>' . __( 'Recent Comments' ) . '</h2><!-- /wp:heading --><!-- wp:latest-comments {"displayAvatar":false,"displayDate":false,"displayExcerpt":false} /--></div><!-- /wp:group -->' ),
				5              => array( 'content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading --><h2>' . __( 'Archives' ) . '</h2><!-- /wp:heading --><!-- wp:archives /--></div><!-- /wp:group -->' ),
				6              => array( 'content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading --><h2>' . __( 'Categories' ) . '</h2><!-- /wp:heading --><!-- wp:categories /--></div><!-- /wp:group -->' ),
 				'_multiwidget' => 1,
 			)
 		);
		update_option(
			'sidebars_widgets', 
 			array(
 				'wp_inactive_widgets' => array(),
 				'sidebar-1'           => array(
					0 => 'block-2',
					1 => 'block-3',
					2 => 'block-4',
 				),
 				'sidebar-2'           => array(
					0 => 'block-5',
					1 => 'block-6',
 				),
 				'array_version'       => 3,
 			)
 		);

		if ( ! is_multisite() )
			update_user_meta( $user_id, 'show_welcome_panel', 1 );
		elseif ( ! is_super_admin( $user_id ) && ! metadata_exists( 'user', $user_id, 'show_welcome_panel' ) )
			update_user_meta( $user_id, 'show_welcome_panel', 2 );

		if ( is_multisite() ) {
			// Flush rules to pick up the new page.
			$wp_rewrite->init();
			$wp_rewrite->flush_rules();

			$user = new WP_User($user_id);
			$wpdb->update( $wpdb->options, array('option_value' => $user->user_email), array('option_name' => 'admin_email') );

			// Remove all perms except for the login user.
			$wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix.'user_level') );
			$wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix.'capabilities') );

			/*
			 * Delete any caps that snuck into the previously active blog. (Hardcoded to blog 1 for now.)
			 * TODO: Get previous_blog_id.
			 */
			if ( ! is_super_admin( $user_id ) && 1 !== $user_id ) {
				$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id , 'meta_key' => $wpdb->base_prefix.'1_capabilities' ) );
			}
		}
	}
endif;

/**
 * Maybe enable pretty permalinks on installation.
 *
 * If after enabling pretty permalinks don't work, fallback to query-string permalinks.
 *
 * @since 4.2.0
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @return bool Whether pretty permalinks are enabled. False otherwise.
 */
function wp_install_maybe_enable_pretty_permalinks() {
	global $wp_rewrite;

	// Bail if a permalink structure is already enabled.
	if ( get_option( 'permalink_structure' ) ) {
		return true;
	}

	/*
	 * The Permalink structures to attempt.
	 *
	 * The first is designed for mod_rewrite or nginx rewriting.
	 *
	 * The second is PATHINFO-based permalinks for web server configurations
	 * without a true rewrite module enabled.
	 */
	$permalink_structures = array(
		'/%year%/%monthnum%/%day%/%postname%/',
		'/index.php/%year%/%monthnum%/%day%/%postname%/',
	);

	foreach ( (array) $permalink_structures as $permalink_structure ) {
		$wp_rewrite->set_permalink_structure( $permalink_structure );

		/*
		 * Flush rules with the hard option to force refresh of the web-server's
		 * rewrite config file (e.g. .htaccess or web.config).
		 */
		$wp_rewrite->flush_rules( true );

		$test_url = '';

		// Test against a real WordPress post.
		$first_post = get_page_by_path( sanitize_title( _x( 'hello-world', 'Default post slug' ) ), OBJECT, 'post' );
		if ( $first_post ) {
			$test_url = get_permalink( $first_post->ID );
		}

		/*
		 * Send a request to the site, and check whether
		 * the 'X-Pingback' header is returned as expected.
		 *
		 * Uses wp_remote_get() instead of wp_remote_head() because web servers
		 * can block head requests.
		 */
		$response          = wp_remote_get( $test_url, array( 'timeout' => 5 ) );
		$x_pingback_header = wp_remote_retrieve_header( $response, 'X-Pingback' );
		$pretty_permalinks = $x_pingback_header && get_bloginfo( 'pingback_url' ) === $x_pingback_header;

		if ( $pretty_permalinks ) {
			return true;
		}
	}

	/*
	 * If it makes it this far, pretty permalinks failed.
	 * Fallback to query-string permalinks.
	 */
	$wp_rewrite->set_permalink_structure( '' );
	$wp_rewrite->flush_rules( true );

	return false;
}

if ( !function_exists('wp_new_blog_notification') ) :
/**
 * Notifies the site admin that the installation of WordPress is complete.
 *
 * Sends an email to the new administrator that the installation is complete
 * and provides them with a record of their login credentials.
 *
 * @since 2.1.0
 *
 * @param string $blog_title Site title.
 * @param string $blog_url   Site url.
 * @param int    $user_id    User ID.
 * @param string $password   User's Password.
 * @param string $blog_url   Site URL.
 * @param int    $user_id    Administrator's user ID.
 * @param string $password   Administrator's password. Note that a placeholder message is
 *                           usually passed instead of the actual password.
 */
function wp_new_blog_notification(
	$blog_title,
	$blog_url,
	$user_id,
	#[\SensitiveParameter]
	$password
) {
	$user = new WP_User( $user_id );
	$email = $user->user_email;
	$name = $user->user_login;
	$login_url = wp_login_url();

	/* translators: New site notification email. 1: New site URL, 2: User login, 3: User password or password reset link, 4: Login URL. */
	$message = sprintf( __( "Your new WordPress site has been successfully set up at:

%1\$s

You can log in to the administrator account with the following information:

Username: %2\$s
Password: %3\$s
Log in here: %4\$s

We hope you enjoy your new site. Thanks!

--The WordPress Team
https://wordpress.org/
"), $blog_url, $name, $password, $login_url );

	wp_mail( $email, __( 'New WordPress Site' ), $message );
	$installed_email = array(
		'to'      => $email,
		'subject' => __( 'New WordPress Site' ),
		'message' => $message,
		'headers' => '',
	);

	/**
	 * Filters the contents of the email sent to the site administrator when WordPress is installed.
	 *
	 * @since 5.6.0
	 *
	 * @param array $installed_email {
	 *     Used to build wp_mail().
	 *
	 *     @type string $to      The email address of the recipient.
	 *     @type string $subject The subject of the email.
	 *     @type string $message The content of the email.
	 *     @type string $headers Headers.
	 * }
	 * @param WP_User $user          The site administrator user object.
	 * @param string  $blog_title    The site title.
	 * @param string  $blog_url      The site URL.
	 * @param string  $password      The site administrator's password. Note that a placeholder message
	 *                               is usually passed instead of the user's actual password.
	 */
	$installed_email = apply_filters( 'wp_installed_email', $installed_email, $user, $blog_title, $blog_url, $password );

	wp_mail(
		$installed_email['to'],
		$installed_email['subject'],
		$installed_email['message'],
		$installed_email['headers']
	);
}
endif;

if ( !function_exists('wp_upgrade') ) :
/**
 * Runs WordPress Upgrade functions.
 *
 * Upgrades the database if needed during a site update.
 *
 * @since 2.1.0
 *
 * @global int $wp_current_db_version The old (current) database version.
 * @global int $wp_db_version         The new database version.
 */
function wp_upgrade() {
	global $wp_current_db_version, $wp_db_version;

	$wp_current_db_version = (int) __get_option( 'db_version' );

	// We are up to date. Nothing to do.
	if ( $wp_db_version === $wp_current_db_version )
		return;

	if ( ! is_blog_installed() )
		return;

	wp_cache_flush();
	upgrade_all();
	if ( is_multisite() && is_main_site() )
		upgrade_network();
	wp_cache_flush();

	if ( is_multisite() ) {
			update_site_meta( get_current_blog_id(), 'db_version', $wp_db_version );
			update_site_meta( get_current_blog_id(), 'db_last_updated', microtime() );
	}

		delete_transient( 'wp_core_block_css_files' );

	/**
	 * Fires after a site is fully upgraded.
	 *
	 * @since 3.9.0
	 *
	 * @param int $wp_db_version         The new $wp_db_version.
	 * @param int $wp_current_db_version The old (current) $wp_db_version.
	 */
	do_action( 'wp_upgrade', $wp_db_version, $wp_current_db_version );
}
endif;

/**
 * Functions to be called in installation and upgrade scripts.
 *
 * Contains conditional checks to determine which upgrade scripts to run,
 * based on database version and WP version being updated-to.
 *
 * @ignore
 * @since 1.0.1
 *
 * @global int $wp_current_db_version The old (current) database version.
 * @global int $wp_db_version         The new database version.
 */
function upgrade_all() {
	global $wp_current_db_version, $wp_db_version;

	$wp_current_db_version = (int) __get_option( 'db_version' );

	// We are up to date. Nothing to do.
	if ( $wp_db_version === $wp_current_db_version )
		return;

	if ( empty($wp_current_db_version) )
		$wp_current_db_version = 0;

	populate_options();

	if ( $wp_current_db_version < 25824 )
		upgrade_370();

	if ( $wp_current_db_version < 26148 )
		upgrade_372();

	if ( $wp_current_db_version < 26691 )
		upgrade_380();

	if ( $wp_current_db_version < 29630 )
		upgrade_400();

	if ( $wp_current_db_version < 30133 )
		upgrade_410();

	if ( $wp_current_db_version < 30134 )
		upgrade_410a();

	if ( $wp_current_db_version < 33055 )
		upgrade_430();

	if ( $wp_current_db_version < 33056 )
		upgrade_431();

	if ( $wp_current_db_version < 35700 )
		upgrade_440();

	if ( $wp_current_db_version < 36686 )
		upgrade_450();

	if ( $wp_current_db_version < 37965 )
		upgrade_460();

	if ( $wp_current_db_version < 38590 )
		upgrade_470();

	if ( $wp_current_db_version < 38592 )
		upgrade_474a();

	if ( $wp_current_db_version < 44719 ) {
		upgrade_510();
	}

	if ( $wp_current_db_version < 45744 ) {
		upgrade_530();
	}

	if ( $wp_current_db_version < 48575 ) {
		upgrade_550();
	}

	if ( $wp_current_db_version < 49752 ) {
		upgrade_560();
	}

	if ( $wp_current_db_version < 51917 ) {
		upgrade_590();
	}

	if ( $wp_current_db_version < 53011 ) {
		upgrade_600();
	}

	if ( $wp_current_db_version < 55853 ) {
		upgrade_630();
	}

	if ( $wp_current_db_version < 56657 ) {
		upgrade_640();
	}

	if ( $wp_current_db_version < 57155 ) {
		upgrade_650();
	}

	if ( $wp_current_db_version < 58975 ) {
		upgrade_670();
	}
	maybe_disable_link_manager();

	maybe_disable_automattic_widgets();

	update_option( 'db_version', $wp_db_version );
	update_option( 'db_upgraded', true );
}

/**
 * Execute changes made in WordPress 3.7.
 *
 * @since 3.7.0
 *
 * @global int $wp_current_db_version The old (current) database version.
 */
function upgrade_370() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 25824 ) {
		wp_clear_scheduled_hook( 'wp_auto_updates_maybe_update' );
	}
}

/**
 * Execute changes made in WordPress 3.7.2.
 *
 * @since 3.7.2
 *
 * @global int $wp_current_db_version The old (current) database version.
 */
function upgrade_372() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 26148 ) {
		wp_clear_scheduled_hook( 'wp_maybe_auto_update' );
	}
}

/**
 * Execute changes made in WordPress 3.8.0.
 *
 * @since 3.8.0
 *
 * @global int $wp_current_db_version The old (current) database version.
 */
function upgrade_380() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 26691 ) {
		deactivate_plugins( array( 'mp6/mp6.php' ), true );
	}
}

/**
 * Execute changes made in WordPress 4.0.0.
 *
 * @since 4.0.0
 *
 * @global int $wp_current_db_version The old (current) database version.
 */
function upgrade_400() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 29630 ) {
		if ( ! is_multisite() && false === get_option( 'WPLANG' ) ) {
			if ( defined( 'WPLANG' ) && ( '' !== WPLANG ) && in_array( WPLANG, get_available_languages(), true ) ) {
				update_option( 'WPLANG', WPLANG );
			} else {
				update_option( 'WPLANG', '' );
			}
		}
	}
}
/**
 * Execute changes made in WordPress 4.1.0 as required by PN.
 *
 * @since 4.1.0
 */
function upgrade_410() {
	global $wp_current_db_version, $wpdb;
	if ( $wp_current_db_version < 30133 ) {
		sqlsrv_query( $wpdb->dbh, "if exists (select * from sysindexes where name = '$wpdb->terms" . "_UK1') DROP INDEX $wpdb->terms" . "_UK1 ON $wpdb->terms" );
		sqlsrv_query( $wpdb->dbh, "if not exists (select * from sysindexes where name = '$wpdb->terms" . "_IDX1') CREATE INDEX $wpdb->terms" . "_IDX1 ON $wpdb->terms (slug)" );
	}
}

/**
 * Execute changes as required by PN post WP 4.1.0.
 *
 * @global int   $wp_current_db_version
 * @global wpdb  $wpdb
 */
function upgrade_410a() {
	global $wp_current_db_version, $wpdb;
	if ( $wp_current_db_version < 30134 ) {
		sqlsrv_query( $wpdb->dbh, "if not exists (select * from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME = '$wpdb->signups' and column_name = 'meta') alter table $wpdb->signups add meta nvarchar(max) NULL" );
	}
 }

/**
 * Executes changes made in WordPress 4.3.0.
 *
 * @since 4.3.0
 *
 * @global int  $wp_current_db_version The old (current) database version.
 * @global wpdb $wpdb                  WordPress database abstraction object.
 */
function upgrade_430() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 32364 ) {
		upgrade_430_fix_comments();
	}

	// Shared terms are split in a separate process.
	if ( $wp_current_db_version < 32814 ) {
		update_option( 'finished_splitting_shared_terms', 0 );
		wp_schedule_single_event( time() + ( 1 * MINUTE_IN_SECONDS ), 'wp_split_shared_term_batch' );
	}

}

/**
 * Executes comments changes made in WordPress 4.3.0.
 *
 * @since 4.3.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function upgrade_430_fix_comments() {
	global $wpdb;

	$comments = $wpdb->get_results(
			"SELECT comment_ID FROM {$wpdb->comments}
			WHERE comment_date_gmt > '2015-04-26'
			AND LEN( comment_content ) >= 65525
			AND ( comment_content LIKE '%<%' OR comment_content LIKE '%>%' )"
	);

	foreach ( $comments as $comment ) {
		wp_delete_comment( $comment->comment_ID, true );
	}
}

/**
 * Execute changes as required by PN post WP 4.3.0.
 *
 * @since 4.3.1
 */
function upgrade_431() {
	// Fix incorrect cron entries for term splitting.
	$cron_array = _get_cron_array();
	if ( isset( $cron_array['wp_batch_split_terms'] ) ) {
		unset( $cron_array['wp_batch_split_terms'] );
		_set_cron_array( $cron_array );
	}
	update_option( 'finished_splitting_shared_terms', false );
}

/**
 * Executes changes made in WordPress 4.4.0.
 *
 * @since 4.4.0
 *
 * @global int  $wp_current_db_version The old (current) database version.
 * @global wpdb $wpdb                  WordPress database abstraction object.
 */
function upgrade_440() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 34030 ) {
		$wpdb->query( "DROP INDEX $wpdb->options" . "_UK1 ON $wpdb->options" );
		$wpdb->query( "ALTER TABLE {$wpdb->options} ALTER COLUMN option_name NVARCHAR(191) NOT NULL" );
		$wpdb->query( "CREATE UNIQUE INDEX $wpdb->options" . "_UK1 on $wpdb->options (option_name)" );
		$wpdb->query( "CREATE TABLE $wpdb->termmeta (meta_id int NOT NULL identity(1,1), term_id int NOT NULL default 0, meta_key nvarchar(255) default NULL, meta_value nvarchar(max), CONSTRAINT $wpdb->termmeta" . "_PK PRIMARY KEY NONCLUSTERED (meta_id))" );
		$wpdb->query( "CREATE CLUSTERED INDEX $wpdb->termmeta" . "_CLU1 on $wpdb->termmeta (term_id)" );
		$wpdb->query( "CREATE INDEX $wpdb->termmeta" . "_IDX2 on $wpdb->termmeta (meta_key)" );
	}

	// Remove the unused 'add_users' role.
	$roles = wp_roles();
	foreach ( $roles->role_objects as $role ) {
		if ( $role->has_cap( 'add_users' ) ) {
			$role->remove_cap( 'add_users' );
		}
	}
}

/**
 * Executes changes made in WordPress 4.6.0.
 *
 * @ignore
 * @since 4.6.0
 *
 * @global int $wp_current_db_version The old (current) database version.
 */
function upgrade_460() {
	global $wp_current_db_version;

	// Remove unused post meta.
	if ( $wp_current_db_version < 37854 ) {
		delete_post_meta_by_key( '_post_restored_from' );
	}

	// Remove plugins with callback as an array object/method as the uninstall hook, see #13786.
	if ( $wp_current_db_version < 37965 ) {
		$uninstall_plugins = get_option( 'uninstall_plugins', array() );

		if ( ! empty( $uninstall_plugins ) ) {
			foreach ( $uninstall_plugins as $basename => $callback ) {
				if ( is_array( $callback ) && is_object( $callback[0] ) ) {
					unset( $uninstall_plugins[ $basename ] );
				}
			}

			update_option( 'uninstall_plugins', $uninstall_plugins );
		}
	}
}

/**
 * Executes changes made in WordPress 4.7.0.
 *
 * @ignore
 * @since 4.7.0
 *
 * @global int $wp_current_db_version Current database version.
 */
function upgrade_470() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 38590 ) {
		$wpdb->query( "ALTER TABLE {$wpdb->posts} ALTER COLUMN post_password NVARCHAR(255)" );
	}
}

/**
 * Execute changes as required by PN post WP 4.7.4.
 *
 * @global int   $wp_current_db_version
 * @global wpdb  $wpdb
 */
function upgrade_474a() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 38592 ) {
		$wpdb->query( "DROP INDEX $wpdb->options" . "_UK1 ON $wpdb->options" );
		$wpdb->query( "ALTER TABLE {$wpdb->options} DROP CONSTRAINT $wpdb->options" . "_PK" );
		$wpdb->query( "ALTER TABLE {$wpdb->options} ALTER COLUMN option_name NVARCHAR(191) NOT NULL" );
		$wpdb->query( "ALTER TABLE {$wpdb->options} ADD CONSTRAINT $wpdb->options" . "_PK PRIMARY KEY NONCLUSTERED (option_id ASC)" );
		$wpdb->query( "CREATE UNIQUE CLUSTERED INDEX $wpdb->options" . "_UK1 on $wpdb->options (option_name)" );
	}
}

/**
 * Executes changes made in WordPress 5.0.0.
 *
 * @ignore
 * @since 5.0.0
 * @deprecated 5.1.0
 */
function upgrade_500() {
}

/**
 * Executes changes made in WordPress 5.1.0.
 *
 * @ignore
 * @since 5.1.0
 */
function upgrade_510() {
	global $wpdb;

	delete_site_option( 'upgrade_500_was_gutenberg_active' );
	$wpdb->query( "CREATE TABLE $wpdb->blogmeta (meta_id int NOT NULL identity(1,1), blog_id int NOT NULL default 0, meta_key nvarchar(255) default NULL, meta_value nvarchar(max), constraint $wpdb->blogmeta" . "_PK PRIMARY KEY NONCLUSTERED (meta_id))" );
	$wpdb->query( "CREATE CLUSTERED INDEX $wpdb->blogmeta" . "_CLU1 on $wpdb->blogmeta (blog_id)" );
	$wpdb->query( "CREATE INDEX $wpdb->blogmeta" . "_IDX2 on $wpdb->blogmeta (meta_key)" );
}

/**
 * Executes changes made in WordPress 5.3.0.
 *
 * @ignore
 * @since 5.3.0
 */
function upgrade_530() {
	/*
	 * The `admin_email_lifespan` option may have been set by an admin that just logged in,
	 * saw the verification screen, clicked on a button there, and is now upgrading the db,
	 * or by populate_options() that is called earlier in upgrade_all().
	 * In the second case `admin_email_lifespan` should be reset so the verification screen
	 * is shown next time an admin logs in.
	 */
	if ( function_exists( 'current_user_can' ) && ! current_user_can( 'manage_options' ) ) {
		update_option( 'admin_email_lifespan', 0 );
	}
}

/**
 * Executes changes made in WordPress 5.5.0.
 *
 * @ignore
 * @since 5.5.0
 *
 * @global int $wp_current_db_version The old (current) database version.
 */
function upgrade_550() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 48121 ) {
		$comment_previously_approved = get_option( 'comment_whitelist', '' );
		update_option( 'comment_previously_approved', $comment_previously_approved );
		delete_option( 'comment_whitelist' );
	}

	if ( $wp_current_db_version < 48575 ) {
		// Use more clear and inclusive language.
		$disallowed_list = get_option( 'blacklist_keys' );

		/*
		 * This option key was briefly renamed `blocklist_keys`.
		 * Account for sites that have this key present when the original key does not exist.
		 */
		if ( false === $disallowed_list ) {
			$disallowed_list = get_option( 'blocklist_keys' );
		}

		update_option( 'disallowed_keys', $disallowed_list );
		delete_option( 'blacklist_keys' );
		delete_option( 'blocklist_keys' );
	}

	if ( $wp_current_db_version < 48748 ) {
		update_option( 'finished_updating_comment_type', 0 );
		wp_schedule_single_event( time() + ( 1 * MINUTE_IN_SECONDS ), 'wp_update_comment_type_batch' );
	}
}

/**
 * Executes changes made in WordPress 5.6.0.
 *
 * @ignore
 * @since 5.6.0
 *
 * @global int  $wp_current_db_version The old (current) database version.
 * @global wpdb $wpdb                  WordPress database abstraction object.
 */
function upgrade_560() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 49572 ) {
		/*
		 * When upgrading from WP < 5.6.0 set the core major auto-updates option to `unset` by default.
		 * This overrides the same option from populate_options() that is intended for new installs.
		 * See https://core.trac.wordpress.org/ticket/51742.
		 */
		update_option( 'auto_update_core_major', 'unset' );
	}

	if ( $wp_current_db_version < 49632 ) {
		/*
		 * Regenerate the .htaccess file to add the `HTTP_AUTHORIZATION` rewrite rule.
		 * See https://core.trac.wordpress.org/ticket/51723.
		 */
		save_mod_rewrite_rules();
	}

	if ( $wp_current_db_version < 49735 ) {
		delete_transient( 'dirsize_cache' );
	}

	if ( $wp_current_db_version < 49752 ) {
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT top 1 1 FROM {$wpdb->usermeta} WHERE meta_key = %s",
				WP_Application_Passwords::USERMETA_KEY_APPLICATION_PASSWORDS
			)
		);

		if ( ! empty( $results ) ) {
			$network_id = get_main_network_id();
			update_network_option( $network_id, WP_Application_Passwords::OPTION_KEY_IN_USE, 1 );
		}
	}
}

/**
 * Executes changes made in WordPress 5.9.0.
 *
 * @ignore
 * @since 5.9.0
 *
 * @global int $wp_current_db_version The old (current) database version.
 */
function upgrade_590() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 51917 ) {
		$crons = _get_cron_array();

		if ( $crons && is_array( $crons ) ) {
			// Remove errant `false` values, see #53950, #54906.
			$crons = array_filter( $crons );
			_set_cron_array( $crons );
		}
	}
}

/**
 * Executes changes made in WordPress 6.0.0.
 *
 * @ignore
 * @since 6.0.0
 *
 * @global int $wp_current_db_version The old (current) database version.
 */
function upgrade_600() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 53011 ) {
		wp_update_user_counts();
	}
}

/**
 * Executes changes made in WordPress 6.3.0.
 *
 * @ignore
 * @since 6.3.0
 *
 * @global int $wp_current_db_version The old (current) database version.
 */
function upgrade_630() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 55853 ) {
		if ( ! is_multisite() ) {
			// Replace non-autoload option can_compress_scripts with autoload option, see #55270
			$can_compress_scripts = get_option( 'can_compress_scripts', false );
			if ( false !== $can_compress_scripts ) {
				delete_option( 'can_compress_scripts' );
				add_option( 'can_compress_scripts', $can_compress_scripts, '', true );
			}
		}
	}
}

/**
 * Executes changes made in WordPress 6.4.0.
 *
 * @ignore
 * @since 6.4.0
 *
 * @global int $wp_current_db_version The old (current) database version.
 */
function upgrade_640() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 56657 ) {
		// Enable attachment pages.
		update_option( 'wp_attachment_pages_enabled', 1 );

		// Remove the wp_https_detection cron. Https status is checked directly in an async Site Health check.
		$scheduled = wp_get_scheduled_event( 'wp_https_detection' );
		if ( $scheduled ) {
			wp_clear_scheduled_hook( 'wp_https_detection' );
		}
	}
}

/**
 * Executes changes made in WordPress 6.5.0.
 *
 * @ignore
 * @since 6.5.0
 *
 * @global int  $wp_current_db_version The old (current) database version.
 * @global wpdb $wpdb                  WordPress database abstraction object.
 */
function upgrade_650() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 57155 ) {
		$stylesheet = get_stylesheet();

		// Set autoload=no for all themes except the current one.
		$theme_mods_options = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM $wpdb->options WHERE autoload = 'yes' AND option_name != %s AND option_name LIKE %s",
				"theme_mods_$stylesheet",
				$wpdb->esc_like( 'theme_mods_' ) . '%'
			)
		);

		$autoload = array_fill_keys( $theme_mods_options, false );
		wp_set_option_autoload_values( $autoload );
	}
}
/**
 * Executes changes made in WordPress 6.7.0.
 *
 * @ignore
 * @since 6.7.0
 *
 * @global int  $wp_current_db_version The old (current) database version.
 */
function upgrade_670() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 58975 ) {
		$options = array(
			'recently_activated',
			'_wp_suggested_policy_text_has_changed',
			'dashboard_widget_options',
			'ftp_credentials',
			'adminhash',
			'nav_menu_options',
			'wp_force_deactivated_plugins',
			'delete_blog_hash',
			'allowedthemes',
			'recovery_keys',
			'https_detection_errors',
			'fresh_site',
		);

		wp_set_options_autoload( $options, false );
	}
}
/**
 * Executes network-level upgrade routines.
 *
 * @since 3.0.0
 *
 * @global int  $wp_current_db_version The old (current) database version.
 * @global wpdb $wpdb                  WordPress database abstraction object.
 */
function upgrade_network() {
	global $wp_current_db_version, $wpdb;

	// Always clear expired transients.
	delete_expired_transients( true );

	// 2.8.0
	if ( $wp_current_db_version < 11549 ) {
		$wpmu_sitewide_plugins   = get_site_option( 'wpmu_sitewide_plugins' );
		$active_sitewide_plugins = get_site_option( 'active_sitewide_plugins' );
		if ( $wpmu_sitewide_plugins ) {
			if ( ! $active_sitewide_plugins ) {
				$sitewide_plugins = (array) $wpmu_sitewide_plugins;
			} else {
				$sitewide_plugins = array_merge( (array) $active_sitewide_plugins, (array) $wpmu_sitewide_plugins );
			}

			update_site_option( 'active_sitewide_plugins', $sitewide_plugins );
		}
		delete_site_option( 'wpmu_sitewide_plugins' );
		delete_site_option( 'deactivated_sitewide_plugins' );

		$start = 0;
		while( $rows = $wpdb->get_results( "SELECT meta_key, meta_value FROM {$wpdb->sitemeta} ORDER BY meta_id OFFSET $start ROWS FETCH NEXT 20 ROWS ONLY" ) ) {
			foreach( $rows as $row ) {
				$value = $row->meta_value;
				if ( !@unserialize( $value ) )
					$value = stripslashes( $value );
				if ( $value !== $row->meta_value ) {
					update_site_option( $row->meta_key, $value );
				}
			}
			$start += 20;
		}
	}

	// 3.0.0
	if ( $wp_current_db_version < 13576 ) {
		update_site_option( 'global_terms_enabled', '1' );
	}

	// 3.3.0
	if ( $wp_current_db_version < 19390 ) {
		update_site_option( 'initial_db_version', $wp_current_db_version );
	}

	if ( $wp_current_db_version < 19470 ) {
		if ( false === get_site_option( 'active_sitewide_plugins' ) ) {
			update_site_option( 'active_sitewide_plugins', array() );
		}
	}

	// 3.4.0
	if ( $wp_current_db_version < 20148 ) {
		// 'allowedthemes' keys things by stylesheet. 'allowed_themes' keyed things by name.
		$allowedthemes  = get_site_option( 'allowedthemes' );
		$allowed_themes = get_site_option( 'allowed_themes' );
		if ( false === $allowedthemes && is_array( $allowed_themes ) && $allowed_themes ) {
			$converted = array();
			$themes    = wp_get_themes();
			foreach ( $themes as $stylesheet => $theme_data ) {
				if ( isset( $allowed_themes[ $theme_data->get( 'Name' ) ] ) ) {
					$converted[ $stylesheet ] = true;
				}
			}
			update_site_option( 'allowedthemes', $converted );
			delete_site_option( 'allowed_themes' );
		}
	}

	// 3.5.0
	if ( $wp_current_db_version < 21823 ) {
		update_site_option( 'ms_files_rewriting', '1' );
	}

	// 3.5.2
	if ( $wp_current_db_version < 24448 ) {
		$illegal_names = get_site_option( 'illegal_names' );
		if ( is_array( $illegal_names ) && count( $illegal_names ) === 1 ) {
			$illegal_name  = reset( $illegal_names );
			$illegal_names = explode( ' ', $illegal_name );
			update_site_option( 'illegal_names', $illegal_names );
		}
	}

	// 5.1.0
	if ( $wp_current_db_version < 44467 ) {
		$network_id = get_main_network_id();
		delete_network_option( $network_id, 'site_meta_supported' );
		is_site_meta_supported();
	}
}

//
// General functions we use to actually do stuff.
//

/**
 * Creates a table in the database, if it doesn't already exist.
 *
 * This method checks for an existing database table and creates a new one if it's not
 * already present. It doesn't rely on MySQL's "IF NOT EXISTS" statement, but chooses
 * to query all tables first and then run the SQL statement creating the table.
 *
 * @ignore
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $table_name Database table name.
 * @param string $create_ddl SQL statement to create table.
 * @return bool True on success or if the table already exists. False on failure.
 */
function maybe_create_table($table_name, $create_ddl) {
	global $wpdb;

	$query = $wpdb->prepare( "SELECT name FROM sysobjects WHERE type='u' AND name = '$table_name'" );

	if ( $wpdb->get_var( $query ) === $table_name ) {
		return true;
	}

	// Didn't find it, so try to create it.
	$wpdb->query($create_ddl);

	// We cannot directly tell that whether this succeeded!
	if ( $wpdb->get_var( $query ) === $table_name ) {
		return true;
	}

	return false;
}

/**
 * Drops a specified index from a table.
 *
 * @since 1.0.1
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $table Database table name.
 * @param string $index Index name to drop.
 * @return true True, when finished.
 */
function drop_index($table, $index) {
	global $wpdb;

	$wpdb->hide_errors();

	$wpdb->query("ALTER TABLE [$table] DROP INDEX [$index]");

	// Now we need to take out all the extra ones we may have created.
	for ($i = 0; $i < 25; $i++) {
		$wpdb->query("ALTER TABLE [$table] DROP INDEX [{$index}_$i]");
	}

	$wpdb->show_errors();

	return true;
}

/**
 * Adds an index to a specified table.
 *
 * @ignore
 * @since 1.0.1
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $table Database table name.
 * @param string $index Database table index column.
 * @return true True, when done with execution.
 */
function add_clean_index($table, $index) {
	global $wpdb;

	drop_index($table, $index);
	$wpdb->query("ALTER TABLE [$table] ADD INDEX ( [$index] )");

	return true;
}

/**
 * Adds column to a database table, if it doesn't already exist.
 *
 * @since 1.3.0
 *
 * @ignore
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $table_name  Database table name.
 * @param string $column_name Table column name.
 * @param string $create_ddl  SQL statement to add column.
 * @return bool True on success or if the column already exists. False on failure.
 */
function maybe_add_column( $table_name, $column_name, $create_ddl ) {
	global $wpdb;
	
	foreach ( $wpdb->get_col( "DESC $table_name", 0 ) as $column ) {
		if ( $column === $column_name ) {
			return true;
		}
	}

	// Didn't find it, so try to create it.
	$wpdb->query( $create_ddl );

	// We cannot directly tell that whether this succeeded!
	foreach ( $wpdb->get_col( "DESC $table_name", 0 ) as $column ) {
		if ( $column === $column_name ) {
			return true;
		}
	}

	return false;
}

/**
 * Utility version of get_option that is private to installation/upgrade.
 *
 * @ignore
 * @since 1.5.1
 * @access private
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $setting Option name.
 * @return mixed
 */
function __get_option( $setting ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionDoubleUnderscore,PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.FunctionDoubleUnderscore
	global $wpdb;

	if ( 'home' === $setting && defined( 'WP_HOME' ) ) {
		return untrailingslashit( WP_HOME );
	}

	if ( 'siteurl' === $setting && defined( 'WP_SITEURL' ) ) {
		return untrailingslashit( WP_SITEURL );
	}

	$option = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", $setting ) );

	if ( 'home' === $setting && ! $option ) {
		return __get_option( 'siteurl' );
	}

	if ( in_array( $setting, array( 'siteurl', 'home', 'category_base', 'tag_base' ), true ) ) {
		$option = untrailingslashit( $option );
	}

	return maybe_unserialize( $option );
}

/**
 * Filters for content to remove unnecessary slashes.
 *
 * @ignore
 * @since 1.5.0
 *
 * @param string $content The content to modify.
 * @return string The de-slashed content.
 */
function deslash( $content ) {
	// Note: \\\ inside a regex denotes a single backslash.

	/*
	 * Replace one or more backslashes followed by a single quote with
	 * a single quote.
	 */
	$content = preg_replace( "/\\\+'/", "'", $content );

	/*
	 * Replace one or more backslashes followed by a double quote with
	 * a double quote.
	 */
	$content = preg_replace( '/\\\+"/', '"', $content );

	// Replace one or more backslashes with one backslash.
	$content = preg_replace( '/\\\+/', '\\', $content );

	return $content;
}

/**
 * Modifies the database based on specified SQL statements.
 *
 * Useful for creating new tables and updating existing tables to a new structure.
 *
 * @since 1.5.0
 * @since 6.1.0 Ignores display width for integer data types on MySQL 8.0.17 or later,
 *              to match MySQL behavior. Note: This does not affect MariaDB.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string|array $queries Optional. The query to run. Can be multiple queries
 *                              in an array, or a string of queries separated by
 *                              semicolons. Default empty.
 * @param bool         $execute Optional. Whether or not to execute the query right away.
 *                              Default true.
 * @return string[] Strings containing the results of the various update queries.
 */
function dbDelta( $queries = '', $execute = true ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	global $wpdb;

	if ( in_array( $queries, array( '', 'all', 'blog', 'global', 'ms_global' ), true ) )
		$queries = wp_get_db_schema( $queries );

	// Separate individual queries into an array.
	if ( !is_array($queries) ) {
		if (stristr($queries, "GO") !== FALSE) {
			$queries = explode( 'GO', $queries );
		} else {
			$queries = explode( ';', $queries );
		}
		$queries = array_filter( $queries );
	}

	foreach( $queries as $query ) {
		$wpdb->query($query);
	}
 
	return array();

}

/**
 * Updates the database tables to a new schema.
 *
 * By default, updates all the tables to use the latest defined schema, but can also
 * be used to update a specific set of tables in wp_get_db_schema().
 *
 * @since 1.5.0
 *
 * @uses dbDelta
 *
 * @param string $tables Optional. Which set of tables to update. Default is 'all'.
 */
function make_db_current( $tables = 'all' ) {
	$alterations = dbDelta( $tables );
	echo "<ol>\n";
	foreach ( $alterations as $alteration ) {
		echo "<li>$alteration</li>\n";
	}
	echo "</ol>\n";
}

/**
 * Updates the database tables to a new schema, but without displaying results.
 *
 * By default, updates all the tables to use the latest defined schema, but can
 * also be used to update a specific set of tables in wp_get_db_schema().
 *
 * @since 1.5.0
 *
 * @see make_db_current()
 *
 * @param string $tables Optional. Which set of tables to update. Default is 'all'.
 */
function make_db_current_silent( $tables = 'all' ) {
	dbDelta( $tables );
}

/**
 * Creates a site theme from an existing theme.
 *
 * {@internal Missing Long Description}}
 *
 * @since 1.5.0
 *
 * @param string $theme_name The name of the theme.
 * @param string $template   The directory name of the theme.
 * @return bool
 */
function make_site_theme_from_oldschool( $theme_name, $template ) {
	$home_path   = get_home_path();
	$site_dir    = WP_CONTENT_DIR . "/themes/$template";
	$default_dir = WP_CONTENT_DIR . '/themes/' . WP_DEFAULT_THEME;

	if ( ! file_exists( "$home_path/index.php" ) ) {
		return false;
	}

	/*
	 * Copy files from the old locations to the site theme.
	 * TODO: This does not copy arbitrary include dependencies. Only the standard WP files are copied.
	 */
	$files = array(
		'index.php'             => 'index.php',
		'wp-layout.css'         => 'style.css',
		'wp-comments.php'       => 'comments.php',
		'wp-comments-popup.php' => 'comments-popup.php',
	);

	foreach ( $files as $oldfile => $newfile ) {
		if ( 'index.php' === $oldfile ) {
			$oldpath = $home_path;
		} else {
			$oldpath = ABSPATH;
		}

		// Check to make sure it's not a new index.
		if ( 'index.php' === $oldfile ) {
			$index = implode( '', file( "$oldpath/$oldfile" ) );
			if ( str_contains( $index, 'WP_USE_THEMES' ) ) {
				if ( ! copy( "$default_dir/$oldfile", "$site_dir/$newfile" ) ) {
					return false;
				}

				// Don't copy anything.
				continue;
			}
		}

		if ( ! copy( "$oldpath/$oldfile", "$site_dir/$newfile" ) ) {
			return false;
		}

		chmod( "$site_dir/$newfile", 0777 );

		// Update the blog header include in each file.
		$lines = explode( "\n", implode( '', file( "$site_dir/$newfile" ) ) );
		if ( $lines ) {
			$f = fopen( "$site_dir/$newfile", 'w' );

			foreach ( $lines as $line ) {
				if ( preg_match( '/require.*wp-blog-header/', $line ) ) {
					$line = '//' . $line;
				}

				// Update stylesheet references.
				$line = str_replace(
					"<?php echo __get_option('siteurl'); ?>/wp-layout.css",
					"<?php bloginfo('stylesheet_url'); ?>",
					$line
				);

				// Update comments template inclusion.
				$line = str_replace(
					"<?php include(ABSPATH . 'wp-comments.php'); ?>",
					'<?php comments_template(); ?>',
					$line
				);

				fwrite( $f, "{$line}\n" );
			}
			fclose( $f );
		}
	}

	// Add a theme header.
	$header = "/*\n" .
		"Theme Name: $theme_name\n" .
		'Theme URI: ' . __get_option( 'siteurl' ) . "\n" .
		"Description: A theme automatically created by the update.\n" .
		"Version: 1.0\n" .
		"Author: Moi\n" .
		"*/\n";

	$stylelines = file_get_contents( "$site_dir/style.css" );
	if ( $stylelines ) {
		$f = fopen( "$site_dir/style.css", 'w' );

		fwrite( $f, $header );
		fwrite( $f, $stylelines );
		fclose( $f );
	}

	return true;
}

/**
 * Creates a site theme from the default theme.
 *
 * {@internal Missing Long Description}}
 *
 * @since 1.5.0
 *
 * @param string $theme_name The name of the theme.
 * @param string $template   The directory name of the theme.
 * @return void|false
 */
function make_site_theme_from_default( $theme_name, $template ) {
	$site_dir    = WP_CONTENT_DIR . "/themes/$template";
	$default_dir = WP_CONTENT_DIR . '/themes/' . WP_DEFAULT_THEME;

	/*
	 * Copy files from the default theme to the site theme.
	 * $files = array( 'index.php', 'comments.php', 'comments-popup.php', 'footer.php', 'header.php', 'sidebar.php', 'style.css' );
	 */

	$theme_dir = @opendir( $default_dir );
	if ( $theme_dir ) {
		while ( ( $theme_file = readdir( $theme_dir ) ) !== false ) {
			if ( is_dir( "$default_dir/$theme_file" ) ) {
				continue;
			}

			if ( ! copy( "$default_dir/$theme_file", "$site_dir/$theme_file" ) ) {
				return;
			}

			chmod( "$site_dir/$theme_file", 0777 );
		}

		closedir( $theme_dir );
	}

	// Rewrite the theme header.
	$stylelines = explode( "\n", implode( '', file( "$site_dir/style.css" ) ) );
	if ( $stylelines ) {
		$f = fopen( "$site_dir/style.css", 'w' );

		$headers = array(
			'Theme Name:'  => $theme_name,
			'Theme URI:'   => __get_option( 'url' ),
			'Description:' => 'Your theme.',
			'Version:'     => '1',
			'Author:'      => 'You',
		);

		foreach ( $stylelines as $line ) {
			foreach ( $headers as $header => $value ) {
				if ( str_contains( $line, $header ) ) {
					$line = $header . ' ' . $value;
					break;
				}
			}

			fwrite( $f, $line . "\n" );
		}

		fclose( $f );
	}

	// Copy the images.
	umask( 0 );
	if ( ! mkdir( "$site_dir/images", 0777 ) ) {
		return false;
	}

	$images_dir = @opendir( "$default_dir/images" );
	if ( $images_dir ) {
		while ( ( $image = readdir( $images_dir ) ) !== false ) {
			if ( is_dir( "$default_dir/images/$image" ) ) {
				continue;
			}

			if ( ! copy( "$default_dir/images/$image", "$site_dir/images/$image" ) ) {
				return;
			}

			chmod( "$site_dir/images/$image", 0777 );
		}

		closedir( $images_dir );
	}
}

/**
 * Creates a site theme.
 *
 * {@internal Missing Long Description}}
 *
 * @since 1.5.0
 *
 * @return string|false
 */
function make_site_theme() {
	// Name the theme after the blog.
	$theme_name = __get_option( 'blogname' );
	$template   = sanitize_title( $theme_name );
	$site_dir   = WP_CONTENT_DIR . "/themes/$template";

	// If the theme already exists, nothing to do.
	if ( is_dir( $site_dir ) ) {
		return false;
	}

	// We must be able to write to the themes dir.
	if ( ! is_writable( WP_CONTENT_DIR . '/themes' ) ) {
		return false;
	}

	umask( 0 );
	if ( ! mkdir( $site_dir, 0777 ) ) {
		return false;
	}

	if ( file_exists( ABSPATH . 'wp-layout.css' ) ) {
		if ( ! make_site_theme_from_oldschool( $theme_name, $template ) ) {
			// TODO: rm -rf the site theme directory.
			return false;
		}
	} else {
		if ( ! make_site_theme_from_default( $theme_name, $template ) ) {
			// TODO: rm -rf the site theme directory.
			return false;
		}
	}

	// Make the new site theme active.
	$current_template = __get_option( 'template' );
	if ( WP_DEFAULT_THEME === $current_template ) {
		update_option( 'template', $template );
		update_option( 'stylesheet', $template );
	}
	return $template;
}

/**
 * Translate user level to user role name.
 *
 * @ignore
 * @since 2.0.0
 *
 * @param int $level User level.
 * @return string User role name.
 */
function translate_level_to_role( $level ) {
	switch ( $level ) {
		case 10:
		case 9:
		case 8:
			return 'administrator';
		case 7:
		case 6:
		case 5:
			return 'editor';
		case 4:
		case 3:
		case 2:
			return 'author';
		case 1:
			return 'contributor';
		case 0:
		default:
			return 'subscriber';
	}
}

/**
 * Checks the version of the installed MySQL binary.
 *
 * @ignore
 * @since 2.1.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function wp_check_mysql_version() {
	global $wpdb;
	$result = $wpdb->check_database_version();
	if ( is_wp_error( $result ) ) {
		wp_die( $result );
	}
}

/**
 * Disables the Automattic widgets plugin, which was merged into core.
 *
 * @since 2.2.0
 */
function maybe_disable_automattic_widgets() {
	$plugins = __get_option( 'active_plugins' );

	foreach ( (array) $plugins as $plugin ) {
		if ( 'widgets.php' === basename( $plugin ) ) {
			array_splice( $plugins, array_search( $plugin, $plugins, true ), 1 );
			update_option( 'active_plugins', $plugins );
			break;
		}
	}
}

/**
 * Disables the Link Manager on upgrade if, at the time of upgrade, no links exist in the DB.
 *
 * @since 3.5.0
 *
 * @global int  $wp_current_db_version The old (current) database version.
 * @global wpdb $wpdb                  WordPress database abstraction object.
 */
function maybe_disable_link_manager() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version >= 22006 && get_option( 'link_manager_enabled' ) && ! $wpdb->get_var( "SELECT TOP 1 link_id FROM $wpdb->links" ) ) {
		update_option( 'link_manager_enabled', 0 );
	}
}

/**
 * Runs before the schema is upgraded.
 *
 * @since 2.9.0
 * @ignore
 *
 * @global int  $wp_current_db_version The old (current) database version.
 * @global wpdb $wpdb                  WordPress database abstraction object.
 */
function pre_schema_upgrade() {
	global $wp_current_db_version, $wpdb;

	// Unused in Project Nami.
	// To be removed.

	// Multisite schema upgrades.
	if ( $wp_current_db_version < 25448 && is_multisite() && wp_should_upgrade_global_tables() ) {

		// Upgrade versions prior to 3.7.
		if ( $wp_current_db_version < 25179 ) {
			// New primary key for signups.
			$wpdb->query( "ALTER TABLE $wpdb->signups ADD signup_id INT NOT NULL IDENTITY(1,1)" );
		}

	}

	if ( $wp_current_db_version < 30133 ) {
		// dbDelta() can recreate but can't drop the index.
		$wpdb->query( "ALTER TABLE $wpdb->terms DROP INDEX slug" );
	}
}

/**
 * Determine if global tables should be upgraded.
 *
 * This function performs a series of checks to ensure the environment allows
 * for the safe upgrading of global WordPress database tables. It is necessary
 * because global tables will commonly grow to millions of rows on large
 * installations, and the ability to control their upgrade routines can be
 * critical to the operation of large networks.
 *
 * In a future iteration, this function may use `wp_is_large_network()` to more-
 * intelligently prevent global table upgrades. Until then, we make sure
 * WordPress is on the main site of the main network, to avoid running queries
 * more than once in multi-site or multi-network environments.
 *
 * @since 4.3.0
 *
 * @return bool Whether to run the upgrade routines on global tables.
 */
function wp_should_upgrade_global_tables() {

	// Return false early if explicitly not upgrading.
	if ( defined( 'DO_NOT_UPGRADE_GLOBAL_TABLES' ) ) {
		return false;
	}

	// Assume global tables should be upgraded.
	$should_upgrade = true;

	// Set to false if not on main network (does not matter if not multi-network).
	if ( ! is_main_network() ) {
		$should_upgrade = false;
	}

	// Set to false if not on main site of current network (does not matter if not multi-site).
	if ( ! is_main_site() ) {
		$should_upgrade = false;
	}

	/**
	 * Filters if upgrade routines should be run on global tables.
	 *
	 * @since 4.3.0
	 *
	 * @param bool $should_upgrade Whether to run the upgrade routines on global tables.
	 */
	return apply_filters( 'wp_should_upgrade_global_tables', $should_upgrade );
}

/**
 * Executes changes made in WordPress 4.5.0.
 *
 * @ignore
 * @since 4.5.0
 *
 * @global int  $wp_current_db_version The old (current) database version.
 * @global wpdb $wpdb                  WordPress database abstraction object.
 */
function upgrade_450() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 36180 ) {
		wp_clear_scheduled_hook( 'wp_maybe_auto_update' );
	}

	// Remove unused email confirmation options, moved to usermeta.
	if ( $wp_current_db_version < 36679 && is_multisite() ) {
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '[0-9]%[_]new[_]email'" );
	}

	// Remove unused user setting for wpLink.
	delete_user_setting( 'wplink' );
}
