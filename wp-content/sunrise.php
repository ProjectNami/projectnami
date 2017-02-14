<?php
if ( !defined( 'SUNRISE_LOADED' ) )
	define( 'SUNRISE_LOADED', 1 );

if ( defined( 'COOKIE_DOMAIN' ) ) {
	die( 'The constant "COOKIE_DOMAIN" is defined (probably in wp-config.php). Please remove or comment out that define() line.' );
}

// let the site admin page catch the VHOST == 'no'
$wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
$dm_domain = $wpdb->escape( $_SERVER[ 'HTTP_HOST' ] );

if( ( $nowww = preg_replace( '|^www\.|', '', $dm_domain ) ) != $dm_domain )
	$where = $wpdb->prepare( 'domain IN (%s,%s)', $dm_domain, $nowww );
else
	$where = $wpdb->prepare( 'domain = %s', $dm_domain );

$wpdb->suppress_errors();
$domain_mapping_id = $wpdb->get_var( "SELECT TOP 1 blog_id FROM {$wpdb->dmtable} WHERE {$where} ORDER BY LEN(domain) DESC" );
$wpdb->suppress_errors( false );
if( $domain_mapping_id ) {
	$current_blog = $wpdb->get_row("SELECT TOP 1 * FROM {$wpdb->blogs} WHERE blog_id = '$domain_mapping_id'");
	$current_blog->domain = $_SERVER[ 'HTTP_HOST' ];
	$current_blog->path = '/';
	$blog_id = $domain_mapping_id;
	$site_id = $current_blog->site_id;

	define( 'COOKIE_DOMAIN', $_SERVER[ 'HTTP_HOST' ] );

	$current_site = $wpdb->get_row( "SELECT TOP 1 * from {$wpdb->site} WHERE id = '{$current_blog->site_id}'" );
	$current_site->blog_id = $wpdb->get_var( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain='{$current_site->domain}' AND path='{$current_site->path}'" );
	if( function_exists( 'get_current_site_name' ) )
		$current_site = get_current_site_name( $current_site );

	define( 'DOMAIN_MAPPING', 1 );
}
?>
