<?php

/**
 * A place to store any miscellaneous config needed for Core or external plugin issues.
 */

/*
 * Azure App Service (and most reverse proxies) terminate TLS in front of PHP.
 * nginx listens on 8080, so is_ssl() is false unless HTTPS is forced. The
 * setup-config language screen then emits http:// CSS/JS and the browser
 * blocks mixed content — unstyled "Welcome to WordPress" + logo as text.
 *
 * This mu-plugin loads during setup-config.php (WP_SETUP_CONFIG still bootstraps
 * wp-settings.php). Keep the matching fastcgi_param HTTPS in nginx.azure.conf.
 */
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
	$_SERVER['HTTPS'] = 'on';
} elseif ( getenv( 'WEBSITE_SITE_NAME' ) ) {
	$_SERVER['HTTPS'] = 'on';
}

/*
 * Stop Jetpack from writing to options with direct DB calls.
 */
if ( ! defined( 'JETPACK_DISABLE_RAW_OPTIONS' ) ) {
	define( 'JETPACK_DISABLE_RAW_OPTIONS', true );
}

/*
* Disable All Automatic Updates
* 3.7+
*
* @author	sLa NGjI's @ slangji.wordpress.com
*/
add_filter('allow_minor_auto_core_updates', '__return_false');
add_filter('allow_major_auto_core_updates', '__return_false');
add_filter('allow_dev_auto_core_updates', '__return_false');
add_filter('auto_update_core', '__return_false');
add_filter('wp_auto_update_core', '__return_false');
add_filter('auto_core_update_send_email', '__return_false');
add_filter('send_core_update_notification_email', '__return_false');
add_filter('automatic_updates_send_debug_email', '__return_false');
