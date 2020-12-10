<?php

/**
 * A place to store any miscellaneous config needed for Core or external plugin issues.
 */

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
