<?php
/*
  * Plugin Name: Project Nami Full Text Search
  * Plugin URI: http://projectnami.org
  * Description: Search using MSSQL Full Text (requires either Azure SQL V12 or greater, or SQL Server with Full Text installed)
  * Author: Patrick Bates
  * Version: 1.0
  * Author URI: http://projectnami.org
  * Copyright (c) 2015 Patrick Bates
  * Licensed under GPLv3
  *
  * This program is free software: you can redistribute it and/or modify
  * it under the terms of the GNU General Public License as published by
  * the Free Software Foundation, either version 3 of the License, or 
  * any later version.
  * 
  * This program is distributed in the hope that it will be useful,
  * but WITHOUT ANY WARRANTY; without even the implied warranty of
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  * GNU General Public License for more details.
  * 
  * See http://www.gnu.org/licenses/ for a copy of the GNU General Public 
  * License.
  *
*/
class PN_Fulltext_Search
{

	var $plugin_page_name = 'pn-fulltext-search';

	function __construct() 
    {
		add_action( 'init', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( 'PN_Fulltext_Search', 'activate' ) );
        register_deactivation_hook( __FILE__, array( 'PN_Fulltext_Search', 'deactivate' ) );
	}

    function activate()
    {
        global $wpdb;

        // Die if we are on Azure SQL V11 or earlier
        if (( $wpdb->get_var( "select CAST(SERVERPROPERTY('edition') as VARCHAR)" ) == 'SQL Azure' ) && !(version_compare( $wpdb->db_version(), "12.0", '>=' ))) {
            wp_die('PN Full Text Search requires Azure SQL V12 or greater. Please upgrade your DB server and try again.');
        }

        // Create the full text catalog if it does not exist
        $wpdb->query( "if not exists (select * from sys.dm_fts_active_catalogs where name = 'ftCatalog' and database_id = DB_ID()) create fulltext catalog ftCatalog" );

        // Create the full text view if it does not exist
        $wpdb->query( "if not exists (select * from INFORMATION_SCHEMA.TABLES where table_name = '{$wpdb->get_blog_prefix()}fulltext_search') exec('CREATE VIEW [dbo].[{$wpdb->get_blog_prefix()}fulltext_search] WITH SCHEMABINDING AS select {$wpdb->get_blog_prefix()}posts.ID, display_name + '' '' + post_title + '' '' + post_excerpt + '' '' + post_content as search_text from dbo.{$wpdb->get_blog_prefix()}posts inner join dbo.wp_users on {$wpdb->get_blog_prefix()}posts.post_author = wp_users.ID')" );

        // Create the clustered index if it does not exist
        $wpdb->query( "if not exists (select * from sys.indexes where name = 'CLU_{$wpdb->get_blog_prefix()}fulltext_search') CREATE UNIQUE CLUSTERED INDEX [CLU_{$wpdb->get_blog_prefix()}fulltext_search] ON [dbo].[{$wpdb->get_blog_prefix()}fulltext_search] ([ID] ASC)" );
        
        // Create the full text index if it does not exist
        $wpdb->query( "if not exists (select * from sys.fulltext_indexes where object_id = object_id('{$wpdb->get_blog_prefix()}fulltext_search')) CREATE FULLTEXT INDEX ON {$wpdb->get_blog_prefix()}fulltext_search(search_text) KEY INDEX CLU_{$wpdb->get_blog_prefix()}fulltext_search ON ftCatalog" );

        // Enable the full text index
        $wpdb->query( "ALTER FULLTEXT INDEX ON {$wpdb->get_blog_prefix()}fulltext_search ENABLE" );
    }

    function deactivate()
    {
        global $wpdb;

        // Drop the full text view if it exists
        $wpdb->query( "if exists (select * from INFORMATION_SCHEMA.TABLES where table_name = '{$wpdb->get_blog_prefix()}fulltext_search') exec('DROP VIEW [dbo].[{$wpdb->get_blog_prefix()}fulltext_search]')" );

    }

	public function init() 
    {
		    add_filter( 'posts_join', array( $this, 'modify_posts_join' ) );   
		    add_filter( 'posts_orderby', array( $this, 'modify_posts_order_by' ) );   
		    add_filter( 'posts_search', array( $this, 'modify_posts_search_query' ) );   
	}

    public function modify_posts_join( $join ) 
    {
		global $wpdb, $pnftsearch;

        if( ! is_search() || ! is_main_query() )
        {
            return $join;   
        }			

        if( ! $pnftsearch )
        {
            return $join;   
        }			
		
		$join .= " INNER JOIN CONTAINSTABLE({$wpdb->get_blog_prefix()}fulltext_search, *, '$pnftsearch') as pnftsearch on pnftsearch.[KEY] = $wpdb->posts.ID ";
		
		return $join;
	}

    public function modify_posts_order_by( $orderby ) 
    {
		global $wpdb, $pnftsearch;

        if( ! is_search() || ! is_main_query() )
        {
            return $orderby;   
        }			

        if( ! $pnftsearch )
        {
            return $orderby;   
        }			
		
		$orderby = " pnftsearch.[RANK] desc ";
		
		return $orderby;
	}

    public function modify_posts_search_query( $search ) 
    {
		global $wpdb, $pnftsearch;

        if( ! is_search()  || empty( $search ) || ! is_main_query() )
        {
            return $search;   
        }			

		$matches = array();

        $search = $wpdb->remove_placeholder_escape( $search );

		$search_freetext = preg_replace( "/[^A-Za-z0-9_%'\[\]\/ ]/", ' ', $search );

		$search_terms = preg_match_all( "/'\%(.*?)\%'/", $search_freetext, $matches );

		$search_terms = array_unique( $matches[1] );

        $search_terms = array_map( 'strtolower', $search_terms );

		$search_terms = '"' . implode( ' ', $search_terms) . '"';
		$filtered_terms = $wpdb->get_var( "SELECT substring(STUFF((SELECT ' ~ ' + display_term FROM sys.dm_fts_parser (' " . $search_terms . " ', convert(int, SERVERPROPERTY('lcid')), 0, 0) WHERE special_term<>'Noise Word' FOR XML PATH('')), 1,1,''), 3, 255)" );
		$pnftsearch = $filtered_terms;

		return '';
	}
}

new PN_Fulltext_Search;
?>