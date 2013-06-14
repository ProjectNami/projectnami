<?php

/*
 * WordPress Object Cache
 *
 * The WordPress Object Cache is used to save on trips to the database. The
 * Object Cache stores all of the cache data to memory and makes the cache
 * contents available by using a key, which is used to name and later retrieve
 * the cache contents.
 *
 * The Object Cache can be replaced by other caching mechanisms by placing files
 * in the wp-content folder which is looked at in wp-settings. If that file
 * exists, then this file will not be included.
 *
 * @package Project Nami
 * @subpackage Cache
 * @since 2.0
 */
class WP_Object_Cache {

	/**
	 * Holds the cached objects
	 *
	 * @var array
	 * @access private
	 * @since 2.0.0
	 */
	var $cache = array ();

	/**
	 * The amount of times the cache data was already stored in the cache.
	 *
	 * @since 2.5.0
	 * @access private
	 * @var int
	 */
	var $cache_hits = 0;

	/**
	 * Amount of times the cache did not have the request in cache
	 *
	 * @var int
	 * @access public
	 * @since 2.0.0
	 */
	var $cache_misses = 0;

	/**
	 * List of global groups
	 *
	 * @var array
	 * @access protected
	 * @since 3.0.0
	 */
	var $global_groups = array();

	/**
	 * The blog prefix to prepend to keys in non-global groups.
	 *
	 * @var int
	 * @access private
	 * @since 3.5.0
	 */
	var $blog_prefix;

	/**
	 * Sets up object properties; PHP 5 style constructor
	 *
	 * @since 2.0.8
	 * @return null|WP_Object_Cache If cache is disabled, returns null.
	 */
	function __construct() {
		global $blog_id;

		$this->multisite = is_multisite();
		$this->blog_prefix =  $this->multisite ? $blog_id . ':' : '';

		$this->remote_cache_endpoint = get_option( $blog_id . '-remote-cache-endpoint' );
		$this->remote_cache_secret = get_option( $blog_id . '-remote-cache-secret' );


		/**
		 * @todo This should be moved to the PHP4 style constructor, PHP5
		 * already calls __destruct()
		 */
		register_shutdown_function( array( $this, '__destruct' ) );
	}

	/**
	 * Will save the object cache before object is completely destroyed.
	 *
	 * Called upon object destruction, which should be when PHP ends.
	 *
	 * @since  2.0.8
	 *
	 * @return bool True value. Won't be used by PHP
	 */
	function __destruct() {
		return true;
	}

	/**
	 * Adds data to the cache if it doesn't already exist.
	 *
	 * @uses WP_Object_Cache::_exists Checks to see if the cache already has data.
	 * @uses WP_Object_Cache::set Sets the data after the checking the cache
	 *		contents existence.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key What to call the contents in the cache
	 * @param mixed $data The contents to store in the cache
	 * @param string $group Where to group the cache contents
	 * @param int $expire When to expire the cache contents
	 * @return bool False if cache key and group already exist, true on success
	 */
	function add( $key, $data, $group = 'default', $expire = '' ) {
		if ( wp_suspend_cache_addition() )
			return false;

		//public function cs_cache_set( $key, $data, $expire, $secret, $host_name ) {

		$request = wp_remote_post( $this->remote_cache_endpoint, array(
			'body' => array(
				'handler' => 'cache_add',
				'key' => $key,
				'data' => $data,
				'group' => $group,
				'expire' => $expire,
				'secret' => $this->remote_cache_secret,
				'host_name' => $this->remote_cache_host )
			)
		);

		if( is_wp_error( $request ) )
			return false;
		elseif( $request[ 'body' ] == '1' )
			return true;
		else
			return false;
	}

	/**
	 * Sets the list of global groups.
	 *
	 * @since 3.0.0
	 *
	 * @param array $groups List of groups that are global.
	 */
	function add_global_groups( $groups ) {
		$groups = (array) $groups;

		$groups = array_fill_keys( $groups, true );
		$this->global_groups = array_merge( $this->global_groups, $groups );
	}

	/**
	 * Decrement numeric cache item's value
	 *
	 * @since 3.3.0
	 *
	 * @param int|string $key The cache key to increment
	 * @param int $offset The amount by which to decrement the item's value. Default is 1.
	 * @param string $group The group the key is in.
	 * @return false|int False on failure, the item's new value on success.
	 */
	function decr( $key, $offset = 1, $group = 'default' ) {
		//public function cs_cache_set( $key, $data, $expire, $secret, $host_name ) {

		$request = wp_remote_post( $this->remote_cache_endpoint, array(
			'body' => array(
				'handler' => 'cache_decrement',
				'key' => $key,
				'data' => $data,
				'group' => $group,
				'secret' => $this->remote_cache_secret,
				'host_name' => $this->remote_cache_host )
			)
		);

		if( is_wp_error( $request ) )
			return false;
		elseif( ! empty( $request[ 'body' ] ) && $request[ 'body' ] != '0' )
			return $request[ 'body' ];
		else
			return false;

		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) )
			$key = $this->blog_prefix . $key;

	}

	/**
	 * Remove the contents of the cache key in the group
	 *
	 * If the cache key does not exist in the group and $force parameter is set
	 * to false, then nothing will happen. The $force parameter is set to false
	 * by default.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key What the contents in the cache are called
	 * @param string $group Where the cache contents are grouped
	 * @param bool $force Optional. Whether to force the unsetting of the cache
	 *		key in the group
	 * @return bool False if the contents weren't deleted and true on success
	 */
	function delete($key, $group = 'default', $force = false) {
		$request = wp_remote_post( $this->remote_cache_endpoint, array(
			'body' => array(
				'handler' => 'cache_delete',
				'key' => $this->create_unique_key( $key ),
				'key' => $group,
				'secret' => $this->remote_cache_secret,
				'host_name' => $this->remote_cache_host )
			)
		);

		if( is_wp_error( $request ) )
			return false;
		elseif( $request[ 'body' ] == '1' )
			return true;
		else
			return false;
	}

	/**
	 * Clears the object cache of all data
	 *
	 * @since 2.0.0
	 *
	 * @return bool Always returns true
	 */
	function flush() {

		if( $this->multisite )
			return false;

		$request = wp_remote_post( $this->remote_cache_endpoint, array(
			'body' => array(
				'handler' => 'cache_flush',
				'secret' => $this->remote_cache_secret,
				'host_name' => $this->remote_cache_host )
			)
		);

		if( is_wp_error( $request ) )
			return false;
		elseif( $request[ 'body' ] == '1' )
			return true;
		else
			return false;
	}

	/**
	 * Retrieves the cache contents, if it exists
	 *
	 * The contents will be first attempted to be retrieved by searching by the
	 * key in the cache group. If the cache is hit (success) then the contents
	 * are returned.
	 *
	 * On failure, the number of cache misses will be incremented.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key What the contents in the cache are called
	 * @param string $group Where the cache contents are grouped
	 * @param string $force Whether to force a refetch rather than relying on the local cache (default is false)
	 * @return bool|mixed False on failure to retrieve contents or the cache
	 *		contents on success
	 */
	function get( $key, $group = 'default', $force = false, &$found = null ) {
		$request = wp_remote_post( $this->remote_cache_endpoint, array(
			'body' => array(
				'handler' => 'cache_get',
				'key' => $this->create_unique_key( $key ),
				'group' => $group,
				'secret' => $this->remote_cache_secret,
				'host_name' => $this->remote_cache_host )
			)
		);

		$found = false;

		if( is_wp_error( $request ) )
			return false;
		elseif( ! empty( $request[ 'body' ] ) && $request[ 'body' ] != '0' ) {
			$found = true;
			return $request[ 'body' ];
		}
		else
			return false;
	}

	/**
	 * Increment numeric cache item's value
	 *
	 * @since 3.3.0
	 *
	 * @param int|string $key The cache key to increment
	 * @param int $offset The amount by which to increment the item's value. Default is 1.
	 * @param string $group The group the key is in.
	 * @return false|int False on failure, the item's new value on success.
	 */
	function incr( $key, $offset = 1, $group = 'default' ) {
		$request = wp_remote_post( $this->remote_cache_endpoint, array(
			'body' => array(
				'handler' => 'cache_increment',
				'key' => $this->create_unique_key( $key ),
				'value' => $offset,
				'initial_value' => 0,
				'group' => $group,
				'expire' => 0,
				'secret' => $this->remote_cache_secret,
				'host_name' => $this->remote_cache_host )
			)
		);

		if( is_wp_error( $request ) )
			return false;
		elseif( ! empty( $request[ 'body' ] ) && $request[ 'body' ] != '0' )
			$request[ 'body' ];
		else
			return false;
	}

	/**
	 * Replace the contents in the cache, if contents already exist
	 *
	 * @since 2.0.0
	 * @see WP_Object_Cache::set()
	 *
	 * @param int|string $key What to call the contents in the cache
	 * @param mixed $data The contents to store in the cache
	 * @param string $group Where to group the cache contents
	 * @param int $expire When to expire the cache contents
	 * @return bool False if not exists, true if contents were replaced
	 */
	function replace( $key, $data, $group = 'default', $expire = '' ) {
		$request = wp_remote_post( $this->remote_cache_endpoint, array(
			'body' => array(
				'handler' => 'cache_replace',
				'key' => $this->create_unique_key( $key ),
				'data' => $data,
				'group' => $group,
				'expire' => $expire,
				'secret' => $this->remote_cache_secret,
				'host_name' => $this->remote_cache_host )
			)
		);

		if( is_wp_error( $request ) )
			return false;
		elseif( $request[ 'body' ] == '1' )
			return true;
		else
			return false;
	}

	/**
	 * Sets the data contents into the cache
	 *
	 * The cache contents is grouped by the $group parameter followed by the
	 * $key. This allows for duplicate ids in unique groups. Therefore, naming of
	 * the group should be used with care and should follow normal function
	 * naming guidelines outside of core WordPress usage.
	 *
	 * The $expire parameter is not used, because the cache will automatically
	 * expire for each time a page is accessed and PHP finishes. The method is
	 * more for cache plugins which use files.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key What to call the contents in the cache
	 * @param mixed $data The contents to store in the cache
	 * @param string $group Where to group the cache contents
	 * @param int $expire Not Used
	 * @return bool Always returns true
	 */
	function set( $key, $data, $group = 'default', $expire = '' ) {
		$request = wp_remote_post( $this->remote_cache_endpoint, array(
			'body' => array(
				'handler' => 'cache_set',
				'key' => $this->create_unique_key( $key ),
				'data' => $data,
				'group' => $group,
				'expire' => $expire,
				'secret' => $this->remote_cache_secret,
				'host_name' => $this->remote_cache_host )
			)
		);

		if( is_wp_error( $request ) )
			return false;
		elseif( $request[ 'body' ] == '1' )
			return true;
		else
			return false;
	}

	private function create_unique_key( $key ) {
		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) )
			$key = $this->blog_prefix . $key;

		return $key;
	}

	/**
	 * Echoes the stats of the caching.
	 *
	 * Gives the cache hits, and cache misses. Also prints every cached group,
	 * key and the data.
	 *
	 * @since 2.0.0
	 */
	function stats() {
		return;

		echo "<p>";
		echo "<strong>Cache Hits:</strong> {$this->cache_hits}<br />";
		echo "<strong>Cache Misses:</strong> {$this->cache_misses}<br />";
		echo "</p>";
		echo '<ul>';
		foreach ($this->cache as $group => $cache) {
			echo "<li><strong>Group:</strong> $group - ( " . number_format( strlen( serialize( $cache ) ) / 1024, 2 ) . 'k )</li>';
		}
		echo '</ul>';
	}

	/**
	 * Switch the interal blog id.
	 *
	 * This changes the blog id used to create keys in blog specific groups.
	 *
	 * @since 3.5.0
	 *
	 * @param int $blog_id Blog ID
	 */
	function switch_to_blog( $blog_id ) {
		$blog_id = (int) $blog_id;
		$this->blog_prefix = $this->multisite ? $blog_id . ':' : '';
	}
}

global $wp_object_cache;

$wp_object_cache = new WP_Object_Cache;
