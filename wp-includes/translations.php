<?php
require_once(dirname(__FILE__) . '/fields_map.php');

/**
 * SQL Dialect Translations
 *
 * original authors A.Garcia & A.Gentile
 * 
 * extended by Project Nami team
 * */
class SQL_Translations extends wpdb
{
    /**
     * Field Mapping
     *
     * @since 2.7.1
     * @access private
     * @var array
     */
    var $fields_map = null;

    /**
     * Was this query prepared?
     *
     * @since 2.7.1
     * @access private
     * @var bool
     */
    var $prepared = false;

    /**
     * Update query?
     *
     * @since 2.7.1
     * @access private
     * @var bool
     */
    var $update_query = false;
    
    /**
     * Select query?
     *
     * @since 2.7.1
     * @access private
     * @var bool
     */
    var $select_query = false;
    
    /**
     * Create query?
     *
     * @since 2.7.1
     * @access private
     * @var bool
     */
    var $create_query = false;
    
    /**
     * Alter query?
     *
     * @since 2.7.1
     * @access private
     * @var bool
     */
    var $alter_query = false;

    /**
     * Insert query?
     *
     * @since 2.7.1
     * @access private
     * @var bool
     */
    var $insert_query = false;
    
    /**
     * Delete query?
     *
     * @since 2.7.1
     * @access private
     * @var bool
     */
    var $delete_query = false;

    /**
     * Prepare arguments
     *
     * @since 2.7.1
     * @access private
     * @var array
     */
    var $prepare_args = array();

    /**
     * Update Data
     *
     * @since 2.7.1
     * @access private
     * @var array
     */
    var $update_data = array();
    
    /**
     * Limit Info
     *
     * @since 2.7.1
     * @access private
     * @var array
     */
    var $limit = array();

    /**
     * Update Data
     *
     * @since 2.7.1
     * @access private
     * @var array
     */
    var $translation_changes = array();

    /**
     * Azure
     * Are we dealing with a SQL Azure DB?
     *
     * @since 2.7.1
     * @access public
     * @var bool
     */
    var $azure = true;
    
    /**
     * Preceeding query
     * Sometimes we need to issue a query
     * before the original query 
     *
     * @since 2.8.5
     * @access public
     * @var mixed
     */
    var $preceeding_query = false;
    
    /**
     * Following query
     * Sometimes we need to issue a query
     * right after the original query 
     *
     * @since 2.8.5
     * @access public
     * @var mixed
     */
    var $following_query = false;

    /**
     * For our very evil callback loop
     */
    var $preg_location = 1;

    /**
     * For our very evil callback loop
     */
    var $preg_data = array();

    /**
     * Original query string
     */
    var $preg_original;

    /**
     * Reserved words in t-sql
     */
    var $reserved_words = array('add', 'exists', 'precision', 'all', 'exit', 'primary', 'alter',
                                'external', 'print', 'and', 'fetch', 'proc', 'any', 'file',
                                'procedure', 'as', 'fillfactor', 'public', 'asc', 'for', 'raiserror',
                                'authorization', 'foreign', 'read', 'backup', 'freetext', 'readtext',
                                'begin', 'freetexttable', 'reconfigure', 'between', 'from', 'references',
                                'break', 'full', 'replication', 'browse', 'function',
                                'restore', 'bulk', 'goto', 'restrict', 'by', 'grant', 'return',
                                'cascade', 'group', 'revert', 'case', 'having', 'revoke',
                                'check', 'holdlock', 'right', 'checkpoint', 'identity',
                                'rollback', 'close', 'identity_insert', 'rowcount',
                                'clustered', 'identitycol', 'rowguidcol', 'coalesce',
                                'if', 'rule', 'collate', 'in', 'save', 'column', 'index',
                                'schema', 'commit', 'inner', 'securityaudit', 'compute',
                                'insert', 'select', 'constraint', 'intersect', 'session_user',
                                'contains', 'into', 'set', 'containstable', 'is', 'setuser',
                                'continue', 'join', 'shutdown', 'convert', 'key', 'some',
                                'create', 'kill', 'statistics', 'cross', 'left', 'system_user',
                                'current', 'like', 'table', 'current_date', 'lineno',
                                'tablesample', 'current_time', 'load', 'textsize',
                                'current_timestamp', 'merge', 'then', 'current_user',
                                'national', 'to', 'cursor', 'nocheck', 'top',
                                'database', 'nonclustered', 'tran', 'dbcc', 'not',
                                'transaction', 'deallocate', 'null', 'trigger', 'declare',
                                'nullif', 'truncate', 'default', 'of', 'tsequal', 'delete',
                                'off', 'union', 'deny', 'offsets', 'unique', 'desc',
                                'on', 'unpivot', 'disk', 'open', 'update', 'distinct',
                                'opendatasource', 'updatetext', 'distributed', 'openquery',
                                'use', 'double', 'openrowset', 'user', 'drop', 'openxml',
                                'values', 'dump', 'option', 'varying', 'else', 'or',
                                'view', 'end', 'order', 'waitfor', 'errlvl', 'outer',
                                'when', 'escape', 'over', 'where', 'except', 'percent',
                                'while', 'exec', 'pivot', 'with', 'execute', 'plan',
                                'writetext');

    /**
     * Sets blog id.
     *
     * @since 3.0.0
     * @access public
     * @param int $blog_id
     * @param int $site_id Optional.
     * @return string previous blog id
     */
    function set_blog_id( $blog_id, $site_id = 0 ) {
        $this->fields_map = new Fields_map($blog_id);
        return parent::set_blog_id($blog_id, $site_id);
    }

    /**
     * Helper function used with preg_replace_callback to store strings
     * we strip out and replace with a sprintf compatible placeholder
     */
    function strip_strings($matches)
    {
        $location = $this->preg_location;
        $this->preg_location++;
        $this->preg_data[$location] = $matches[0]; // store with quotes
        $string = '%' . $location . '$s';

        return $string;
    }

    /**
     * MySQL > MSSQL Query Translation
     * Processes smaller translation sub-functions
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate($query)
    {
	// Give this class/plugins a chance to process the query string before any translations are done.
        $query = $this->pre_translate_query( $query );

        $this->preg_original = $query = trim($query);

        if (empty($this->fields_map)) {
            // we have multisite going so we'll use the site specific mapper
            if ($this->blogid > 1) {
                $this->fields_map = new Fields_map($this->blogid);
            } else {
                $this->fields_map = new Fields_map();
            }
            
        }

        $this->limit = array();

        $this->set_query_type($query);

        $this->preceeding_query = false;
        $this->following_query = false;

        // Was this query prepared?
        if ( strripos($query, '--PREPARE') !== FALSE ) {
            $query = str_replace('--PREPARE', '', $query);
            $this->prepared = TRUE;
        } else {
            $this->prepared = FALSE;
        }

        // strip out any quoted strings and store them, replace with sprintf placeholders
        $query = preg_replace_callback("!'([^'\\\]*(\\\'[^'\\\]*)*)'!", array($this, 'strip_strings'), $query);
        $this->preg_location = 1;

        // Do we have serialized arguments?
        if ( strripos($query, '--SERIALIZED') !== FALSE ) {
            $query = str_replace('--SERIALIZED', '', $query);
            if ($this->insert_query) {
                // $query = $this->on_duplicate_key($query);
				$query = $this->on_update_to_merge($query);
            }
            $query = $this->translate_general($query);
            $query = vsprintf($query, $this->preg_data);
            $this->preg_data = array();
            return $query;
        }

        $sub_funcs = array(
            'translate_general',
            'translate_date_add',
            'translate_if_stmt',
            'translate_sqlcalcrows',
            'translate_limit',
            'translate_findinset',
            'translate_now_datetime',
            'translate_distinct_orderby',
            'translate_replace_casting',
            'translate_sort_casting',
            'translate_column_type',
            'translate_remove_groupby',
            'translate_insert_nulltime',
            'translate_incompat_data_type',
            'translate_create_queries',
            'translate_specific',
			'translate_if_not_exists_insert_merge',
        );

        // Perform translations and record query changes.
        $this->translation_changes = array();
        foreach ( $sub_funcs as $sub_func ) {
            $old_query = $query;
            $query = $this->$sub_func($query);
            if ( $old_query !== $query ) {
                $this->translation_changes[] = $sub_func;
                $this->translation_changes[] = $query;
                $this->translation_changes[] = $old_query;
            }
        }

        if (!empty($this->preg_data)) {
            $query = vsprintf($query, $this->preg_data);
        }
        $this->preg_data = array();

        if ( $this->insert_query ) {
            // $query = $this->on_duplicate_key($query);
            // $query = $this->split_insert_values($query);
			/* on_duplicate_key() and split_insert_values() functions may be deleted if on_update_to_merge() works properly. */
			$query = $this->on_update_to_merge($query);
        }

        return $query;
    }
    
    function set_query_type($query)
    {
        $this->insert_query = false;
        $this->delete_query = false;
        $this->update_query = false;
        $this->select_query = false;
        $this->alter_query  = false;
        $this->create_query = false;
        
        if ( stripos($query, 'INSERT') === 0 ) {
            $this->insert_query = true;
        } else if ( stripos($query, 'SELECT') === 0 ) {
            $this->select_query = true;
        } else if ( stripos($query, 'DELETE') === 0 ) {
            $this->delete_query = true;
        } else if ( stripos($query, 'UPDATE') === 0 ) {
            $this->update_query = true;
        } else if ( stripos($query, 'ALTER') === 0 ) {
            $this->alter_query = true;
        } else if ( stripos($query, 'CREATE') === 0 ) {
            $this->create_query = true;
        }
    }

    
	/**
	* Additions made by the PN team to the translators.
	*
	* This function is called before any other translations
	* are performed so have access to previously unhandled data.
	*
	* @param string $query Query coming in
	*
	* @return string Translate query
	*/
        function pre_translate_query( $query ) {
		
		// Handle zeroed out dates from MySQL. SQL Server chokes on these.
        	$query = str_replace( "0000-00-00 00:00:00", "0001-01-01 00:00:00", $query );

		// Handle NULL-safe equal to operator.
        	$query = str_replace( "<=>", "=", $query );
         
		/**        
		* Symposium Pro
		*/
		if ($start_pos = stripos($query, '(t.topic_parent = 0 || p.topic_parent = 0)')) {
		 $query = substr_replace($query, '(t.topic_parent = 0 OR p.topic_parent = 0)', $start_pos, 42);
		}

		/* Detect plugin. For use on Front End only. */
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		
        /**
         * Akismet
         */
		if ( is_plugin_active( 'akismet/akismet.php' ) ) {
			if (stristr($query, " as c USING(comment_id) WHERE m.meta_key = 'akismet_as_submitted'") !== FALSE) {
				$query = str_ireplace(
					'USING(comment_id)', 
					'ON c.comment_id = m.comment_id', $query);
			}
		}

        /**
         * Broken Link Checker
         */
		if ( is_plugin_active( 'broken-link-checker/broken-link-checker.php' ) ) {
			if (stristr($query, " INNER JOIN wp_blc_instances AS instances USING(link_id)") !== FALSE) {
				$query = str_ireplace('USING(link_id)', 'ON links.link_id = instances.link_id', $query);
			}
			$query = str_ireplace('GROUP BY links.link_id', '', $query);
            $query = preg_replace('/(where\s*1\s*and)/is', 'WHERE 1=1 AND', $query);
            $query = preg_replace('/(where\s*1\s*and)/is', 'WHERE 1=1 AND', $query);
			
			$pattern = '/((select)\s*(\d*)\s*(from))/is';
			preg_match($pattern, $query, $match);
			if (sizeof($match) == 5) {
				$query = preg_replace($pattern, $match[2] . ' ' . $match[3] . ' AS zero ' . $match[4], $query);
			}
		}

        /**
         * Jetpack
         */
		if ( is_plugin_active( 'jetpack/jetpack.php' ) ) {
			if (stristr($query, " AS UNSIGNED") !== FALSE) {
				$query = str_ireplace(
					' AS UNSIGNED', 
					' AS BIGINT', $query);
			}
		}

        /**
         * Yoast SEO
         */
		if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {
			if (stristr($query, " && meta_key = ") !== FALSE) {
				$query = str_ireplace(
					' && meta_key = ', 
					' AND meta_key = ', $query);
			}
		
			if (stristr($query, "ORDER BY wp_posts.menu_order, wp_posts.post_date") !== FALSE) {
				$query = str_ireplace(
					'ORDER BY wp_posts.menu_order, wp_posts.post_date', 
					'ORDER BY wp_posts.post_date', $query);
			}
	
			$searchstr = '/(SELECT\s+post_type\s*,\s*MAX\(post_modified_gmt\)\s+as\s+date\s+from)/is';
			preg_match( $searchstr, $query, $groups );
	
			/* You should an array of size 2 */
			if (sizeof($groups) == 2) {
				/* Get groupings */
				preg_match( '/(GROUP\s+BY\s+post_type\s+ORDER\s+BY\s+post_modified_gmt)/is', $query, $groups );
			
				/* You should an array of size 2 */
				if (sizeof($groups) == 2) {
					$query = str_ireplace(
					'ORDER BY post_modified_gmt', 
					'ORDER BY max(post_modified_gmt)', $query);
				}
			}
		}

        /**
         * The Events Calendar
         */
		if ( is_plugin_active( 'the-events-calendar/the-events-calendar.php' ) ) {
			if (stristr($query, "DATE(tribe_event_start.meta_value) ASC, TIME(tribe_event_start.meta_value) ASC") !== FALSE) {
				$query = str_ireplace(
					'DATE(tribe_event_start.meta_value) ASC, TIME(tribe_event_start.meta_value) ASC', 
					'CONVERT(VARCHAR(19),tribe_event_start.meta_value,120) ASC', $query);
			}
			if (stristr($query, "SELECT DISTINCT wp_posts.*, MIN(wp_postmeta.meta_value) as EventStartDate, MIN(tribe_event_end_date.meta_value) as EventEndDate") !== FALSE) {
				$query = str_ireplace(
					'WHERE 1=1  AND (((wp_posts.post_title', 
					'WHERE 1=1  AND (wp_posts.post_title', $query);
			}
		}
		
        /**
         * Booking
         */
        if (stristr($query, "COLLATE utf8_general_ci") !== FALSE) {
            $query = str_ireplace(
                'COLLATE utf8_general_ci', 
                ' ', $query);
        }

        if (stristr($query, "ORDER BY dt, bkBY dt.booking_date") !== FALSE) {
            $query = str_ireplace(
                'ORDER BY dt, bkBY dt.booking_date', 
                'ORDER BY dt.booking_date', $query);
        }

        if (stristr($query, "bookingdates WHERE Key_name = 'booking_id_dates'") !== FALSE) {
            $query = str_ireplace(
                "bookingdates WHERE Key_name = 'booking_id_dates'", 
                "bookingdates' and ind.name = 'booking_id_dates", $query);
        }

        /**
         * CURDATE handling test
         */
        if (stristr($query, "CURDATE()+ INTERVAL 2 day") !== FALSE) {
            $query = str_ireplace(
                'CURDATE()+ INTERVAL 2 day', 
                'CAST(dateadd(d,2,GETDATE()) AS DATE)', $query);
        }

        if (stristr($query, "CURDATE()+ INTERVAL 3 day") !== FALSE) {
            $query = str_ireplace(
                'CURDATE()+ INTERVAL 3 day', 
                'CAST(dateadd(d,3,GETDATE()) AS DATE)', $query);
        }

        if (stristr($query, "CURDATE()+ INTERVAL 4 day") !== FALSE) {
            $query = str_ireplace(
                'CURDATE()+ INTERVAL 4 day', 
                'CAST(dateadd(d,4,GETDATE()) AS DATE)', $query);
        }

        if (stristr($query, "CURDATE() - INTERVAL 1 day") !== FALSE) {
            $query = str_ireplace(
                'CURDATE() - INTERVAL 1 day', 
                'CAST(dateadd(d,-1,GETDATE()) AS DATE)', $query);
        }

        if (stristr($query, "CURDATE()") !== FALSE) {
            $query = str_ireplace(
                'CURDATE()', 
                'CAST(GETDATE() AS DATE)', $query);
        }

        /**
         * W3 Total Cache
         */
        if (stristr($query, "COMMENT '1 - Upload, 2 - Delete, 3 - Purge'") !== FALSE) {
            $query = str_ireplace(
                "COMMENT '1 - Upload, 2 - Delete, 3 - Purge'", 
                '', $query);
        }

        if ( (stristr($query, "REPLACE INTO") !== FALSE) && (stristr($query, "w3tc_cdn_queue") !== FALSE) ) {
            $query = str_ireplace(
                'REPLACE INTO', 
                'INSERT', $query);
        }

        if (stristr($query, "w3tc_cdn_queue") !== FALSE) {
            $query = str_ireplace(
                '"', 
                "'", $query);
        }

        if ( (stristr($query, 'pm.meta_value AS file') !== FALSE) && (stristr($query, '"_wp_attachment_metadata"') !== FALSE) ) {
            $query = str_ireplace(
                'pm.meta_value AS file', 
                "pm.meta_value AS [file]", $query);
            $query = $query . ", pm.meta_value, pm2.meta_value";
        }

        if ( (stristr($query, '"_wp_attached_file"') !== FALSE) || (stristr($query, '"_wp_attachment_metadata"') !== FALSE) ) {
            $query = str_ireplace(
                '"', 
                "'", $query);
        }

        if ( (stristr($query, "CREATE TABLE") !== FALSE) && (stristr($query, "w3tc_cdn_queue") !== FALSE) ) {
            $query = "IF NOT EXISTS (select * from sysobjects WHERE name = '" . $this->get_blog_prefix() . "w3tc_cdn_queue')" . $query;
        }

        /**
         * WooCommerce
         */
		if (stristr($query, "ORDER BY tm.meta_value+0") !== FALSE) {
			$query = str_ireplace(
				'tm.meta_value+0', 
				'CAST(tm.meta_value as numeric)', $query);
		}

        /**
         * Comments
         */
        $query = str_ireplace("WHERE ( post_status = 'publish' OR ( post_status = 'inherit' && post_type = 'attachment' ) )", 
		"WHERE ( post_status = 'publish' OR ( post_status = 'inherit' AND post_type = 'attachment' ) )", $query);
			
        /**
         * Misc Queries
         */
        $query = str_ireplace('WHERE 1=1 AND 0', '', $query);

		$searchstr = '/(SELECT\s*YEAR\(p\.post_date_gmt\)\s*AS\s*`year`,\s*MONTH\(p\.post_date_gmt\)\s*AS\s*`month`,\s*COUNT\(p\.ID\)\s*AS\s*`numposts`,\s*MAX\(p\.post_modified_gmt\)\s*as\s*`last_mod`\s*FROM\s*\w*\s*p)/is';
		preg_match( $searchstr, $query, $groups );

		/* You should an array of size 2 */
		if (sizeof($groups) == 2) {
			$pattern =  '/(ORDER\s*BY\s*p\.post_date_gmt\s*DESC)/is';
			preg_match( $pattern, $query, $groups );
		
			/* You should an array of size 2 */
			if (sizeof($groups) == 2) {
				$query = preg_replace($pattern, 'ORDER BY YEAR(p.post_date_gmt), MONTH(p.post_date_gmt) DESC', $query);
			}
		}
		 

        /**
         * End Project Nami specific translations
         */

		return apply_filters( 'pre_translate_query', $query );
	}

    /**
     * More generalized information gathering queries
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_general($query)
    {
        // SERVER VERSION
        if ( stripos($query, 'SELECT VERSION()' ) === 0) {
            $query = substr_replace($query, 'SELECT @@VERSION', 0, 16);
        }
        // SQL_MODE NO EQUIV
        if ( stripos($query, "SHOW VARIABLES LIKE 'sql_mode'" ) === 0) {
            $query = '';
        }
        // LAST INSERT ID
        if ( stripos($query, 'LAST_INSERT_ID()') > 0 ) {
            $start_pos = stripos($query, 'LAST_INSERT_ID()');
            $query = substr_replace($query, '@@IDENTITY', $start_pos, 16);
        }
        // SHOW TABLES
        if ( strtolower($query) === 'show tables;' or strtolower($query) === 'show tables' ) {
            $query = str_ireplace('show tables',"select name from SYSOBJECTS where TYPE = 'U' order by NAME",$query);
        }
        if ( stripos($query, 'show tables like ') === 0 ) {
            $end_pos = strlen($query);
            $param = substr($query, 17, $end_pos - 17);
            // quoted with double quotes instead of single?
            // $param = trim($param, '"');
            $param = str_ireplace('"', "'", $param);
            /*
            if($param[0] !== "'") {
                $param = "'$param'";
            }
            */
            $query = 'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE ' . $param;
        }
        // DESCRIBE - this is pretty darn close to mysql equiv, however it will need to have a flag to modify the result set
        // this and SHOW INDEX FROM are used in WP upgrading. The problem is that WP will see the different data types and try
        // to alter the table thinking an upgrade is necessary. So the result set from this query needs to be modified using
        // the field_mapping to revert column types back to their mysql equiv to fool WP.
        if ( stripos($query, 'DESCRIBE ') === 0 ) {
            $table = rtrim(substr($query, 9), ';');
            $query = $this->describe($table);
        }

        // SET NAMES doesn't exist in T-SQL
        if ( stristr($query, "set names 'utf8'") !== FALSE ) {
            $query = "";
        }
        // SHOW COLUMNS
        if ( stripos($query, 'SHOW COLUMNS FROM ') === 0 ) {
            $like_matched = preg_match("/ like '(.*?)'/i", $query, $like_match);
            if ($like_matched) {
                $query = str_ireplace($like_match[0], '', $query);
            }
            $end_pos = strlen($query);
            $param = substr($query, 18, $end_pos - 18);
            $param = "'". trim($param, "'") . "'";
            $query = 'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ' . $param;
            if ($like_matched) {
                $query = $query . " AND COLUMN_NAME " . $like_match[0];
            }
        }
        
        // SHOW INDEXES - issue with sql azure trying to fix....sys.sysindexes is coming back as invalid onject name
        if ( stripos($query, 'SHOW INDEXES FROM ') === 0 ) {
            return $query;
            $table = substr($query, 18);
            $query = "SELECT sys.sysindexes.name AS IndexName
                      FROM sysobjects
                       JOIN sys.key_constraints ON parent_object_id = sys.sysobjects.id
                       JOIN sys.sysindexes ON sys.sysindexes.id = sys.sysobjects.id and sys.key_constraints.unique_index_id = sys.sysindexes.indid
                       JOIN sys.index_columns ON sys.index_columns.object_id = sys.sysindexes.id  and sys.index_columns.index_id = sys.sysindexes.indid
                       JOIN sys.syscolumns ON sys.syscolumns.id = sys.sysindexes.id AND sys.index_columns.column_id = sys.syscolumns.colid
                      WHERE sys.sysobjects.type = 'u'   
                       AND sys.sysobjects.name = '{$table}'";
        }

        // SHOW INDEX FROM tablename
        if ( stripos($query, 'SHOW INDEX FROM ') === 0 ) {
            $table = rtrim(substr($query, 16), ';');
            $query = "select
                    t.name AS [Table],
                    CASE 
                                WHEN ind.is_unique = 1 THEN 0
                                ELSE 1
                        END AS Non_unique,
                        CASE 
                            WHEN ind.is_primary_key = 1 THEN 'PRIMARY'
                            ELSE ind.name
                        END AS Key_name,
                        col.name AS Column_name,
                   NULL as Sub_part
                from 
                    sys.indexes ind
                inner join 
                    sys.index_columns ic on 
                      ind.object_id = ic.object_id and ind.index_id = ic.index_id
                inner join
                    sys.columns col on
                      ic.object_id = col.object_id and ic.column_id = col.column_id 
                inner join
                    sys.tables t on 
                      ind.object_id = t.object_id
                where
                    t.name = '{$table}'
                order by
                    t.name, ind.name, ind.index_id, ic.index_column_id";
        }

        // USE INDEX
        if ( stripos($query, 'USE INDEX (') !== FALSE) {
            $start_pos = stripos($query, 'USE INDEX (');
            $end_pos = $this->get_matching_paren($query, $start_pos + 11);
            $params = substr($query, $start_pos + 11, $end_pos - ($start_pos + 11));
            $params = explode(',', $params);
            foreach ($params as $k => $v) {
                $params[$k] = trim($v);
                foreach ($this->fields_map->read() as $table => $fields) {
                    if ( is_array($fields) ) {
                        foreach ($fields as $field_name => $field_meta) {
                            if ( $field_name == $params[$k] ) {
                                $params[$k] = $table . '_' . $params[$k];
                            }
                        }
                    }
                }
            }
            $params = implode(',', $params);
            $query = substr_replace($query, 'WITH (INDEX(' . $params . '))', $start_pos, ($end_pos + 1) - $start_pos);
        }

        // DROP TABLES
        if ( stripos($query, 'DROP TABLE IF EXISTS ') === 0 ) {
            $table = substr($query, 21, strlen($query) - 21);
            $query = 'DROP TABLE ' . $table;
        } elseif ( stripos($query, 'DROP TABLE ') === 0 ) {
            $table = substr($query, 11, strlen($query) - 11);
            $query = 'DROP TABLE ' . $table;
        }

        // REGEXP - not supported in TSQL
        if ( stripos($query, 'REGEXP') > 0 ) {
            if ( $this->delete_query && stripos($this->preg_original, '^rss_[0-9a-f]{32}(_ts)?$') > 0 ) {
                $start_pos = stripos($this->preg_original, 'REGEXP');
                $query = substr_replace($this->preg_original, "LIKE 'rss_'", $start_pos);
                $this->preg_data = array();
            }
        }

        // REMEMBER you must replace char_length first to avoid overwriting datalength

        // LEN == sql servers length == length in characters
        $query = str_replace('CHAR_LENGTH(', 'LEN(', $query);
        $query = str_replace('CHAR_LENGTH (', 'LEN(', $query);

        // DATALENGTH == sql servers length == length in bytes
        $query = str_replace('LENGTH(', 'DATALENGTH(', $query);
        $query = str_replace('LENGTH (', 'DATALENGTH(', $query); 

        // TICKS
        $query = str_replace('`', '', $query);

        // avoiding some nested as Computed issues
        if (stristr($query, 'SELECT COUNT(DISTINCT(' . $this->prefix . 'users.ID))') !== FALSE) {
            $query = str_ireplace(
                'SELECT COUNT(DISTINCT(' . $this->prefix . 'users.ID))', 
                'SELECT COUNT(DISTINCT(' . $this->prefix . 'users.ID)) as Computed ', $query);
        }

        // Computed
        // This is done as the SQLSRV driver doesn't seem to set a property value for computed
        // selected columns, thus WP doesn't have anything to work with.
        if (!preg_match('/COUNT\((.*?)\) as/i', $query)) {
            $query = preg_replace('/COUNT\((.*?)\)/i', 'COUNT(\1) as Computed ', $query);
        }

        // Replace RAND() with NEWID() since RAND() generates the same result for the same query
        if (preg_match('/(ORDER BY RAND\(\))(.*)$/i', $query)) {
            $query = preg_replace('/(ORDER BY RAND\(\))(.*)$/i', 'ORDER BY NEWID()\2', $query);
        }

        // Remove uncessary ORDER BY clauses as ORDER BY fields needs to 
        // be contained in either an aggregate function or the GROUP BY clause.
        // something that isn't enforced in mysql
        if (preg_match('/SELECT COUNT\((.*?)\) as Computed FROM/i', $query)) {
            $order_pos = stripos($query, 'ORDER BY');
            if ($order_pos !== false) {
                $query = substr($query, 0, $order_pos);
            }
        }
        
        // Project Nami
        // Remove ORDER BY clauses from DELETE commands
        if ( $this->delete_query ) {
            $order_pos = stripos($query, 'ORDER BY');
            if ($order_pos !== false) {
                $query = substr($query, 0, $order_pos);
            }
        }

        // Turn on IDENTITY_INSERT for Importing inserts or category/tag adds that are 
        // trying to explicitly set an IDENTITY column
        if ($this->insert_query) {
            $tables = array(
                $this->get_blog_prefix() . 'posts' => 'id', 
                $this->get_blog_prefix() . 'terms' => 'term_id', 
            );
            foreach ($tables as $table => $pid) {
                if (stristr($query, 'INTO ' . $table) !== FALSE) {
                    $strlen = strlen($table);
                    $start_pos = stripos($query, $table) + $strlen;
                    $start_pos = stripos($query, '(', $start_pos);
                    $end_pos = $this->get_matching_paren($query, $start_pos + 1);
                    $params = substr($query, $start_pos + 1, $end_pos - ($start_pos + 1));
                    $params = explode(',', $params);
                    $found = false;
                    foreach ($params as $k => $v) {
                        if (strtolower($v) === $pid) {
                            $found = true;
                        }   
                    }
                    
                    if ($found) {
                        $this->preceeding_query = "SET IDENTITY_INSERT $table ON";
                        $this->following_query = "SET IDENTITY_INSERT $table OFF";
                    }
                }
            }
        }
        
        // UPDATE queries trying to change an IDENTITY column this happens
        // for cat/tag adds (WPMU) e.g. UPDATE wp_1_terms SET term_id = 5 WHERE term_id = 3330
        if ($this->update_query) {
            $tables = array(
                $this->prefix . 'terms' => 'term_id', 
            );
            foreach ($tables as $table => $pid) {
                if (stristr($query, $table . ' SET ' . $pid) !== FALSE) {
                    preg_match_all("^=\s\d+^", $query, $matches);
                    if (!empty($matches) && count($matches[0]) == 2) {
                        $to = trim($matches[0][0], '= ');
                        $from = trim($matches[0][1], '= ');
                        $this->preceeding_query = "SET IDENTITY_INSERT $table ON";
                        // find a better way to get columns (field mapping doesn't grab all)
                        $query = "INSERT INTO $table (term_id,name,slug,term_group) SELECT $to,name,slug,term_group FROM $table WHERE $pid = $from";
                        $this->following_query = array("DELETE $table WHERE $pid = $from","SET IDENTITY_INSERT $table OFF");
                    }
                }
            }
        }
        
        return $query;
    }

    /**
     * Changes for DATE_ADD and INTERVAL
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_date_add($query)
    {
        $query = preg_replace('/date_add\((.*?),.*?([0-9]+?) (.*?)\)/i', 'DATEADD(\3,\2,\1)', $query);
        $query = preg_replace('/date_sub\((.*?),.*?([0-9]+?) (.*?)\)/i', 'DATEADD(\3,-\2,\1)', $query);

        return $query;
    }


    /**
     * Removing Unnecessary IF statement that T-SQL doesn't play nice with
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_if_stmt($query)
    {
        if ( stripos($query, 'IF (DATEADD(') > 0 ) {
            $start_pos = stripos($query, 'DATEADD(');
            $end_pos = $this->get_matching_paren($query, $start_pos + 8);
            $stmt = substr($query, $start_pos, ($end_pos - $start_pos)) . ') >= getdate() THEN 1 ELSE 0 END)';

            $start_pos = stripos($query, 'IF (');
            $end_pos = $this->get_matching_paren($query, ($start_pos+6))+1;
            $query = substr_replace($query, '(CASE WHEN ' . $stmt, $start_pos, ($end_pos - $start_pos));
        }
		
		/* IF in SELECT statement */
		if ($this->select_query) {
			$pattern = '/(IF\s*\(*((.*),(.*),(.*))\)\s*(AS\s*\w*))/is';
			preg_match($pattern, $query, $limit_matches);
			if (count($limit_matches) == 7) {
				$case_stmt = ' CASE WHEN ' . $limit_matches[3] . ' THEN ' . $limit_matches[4] . ' ELSE ' . $limit_matches[5] . ' END ' . $limit_matches[6];
				$query = preg_replace($pattern, $case_stmt, $query);
			}
		}
        return $query;
    }

    /**
     * SQL_CALC_FOUND_ROWS does not exist in T-SQL
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_sqlcalcrows($query)
    {
        if (stripos($query, 'SQL_CALC_FOUND_ROWS') > 0 ) {
            $sql_calc_pos = stripos($query, 'SQL_CALC_FOUND_ROWS');
            $from_pos = stripos($query, 'FROM');
            $query = substr_replace($query,'* ', $sql_calc_pos, ($from_pos - $sql_calc_pos));
        }
        // catch the next query.
        if ( stripos($query, 'FOUND_ROWS()') > 0 ) {
            $from_pos = stripos($this->previous_query, 'FROM');
            $where_pos = stripos($this->previous_query, 'WHERE');
            $from_str = trim(substr($this->previous_query, $from_pos, ($where_pos - $from_pos)));
            $order_by_pos = stripos($this->previous_query, 'ORDER BY');
            $where_str = trim(substr($this->previous_query, $where_pos, ($order_by_pos - $where_pos)));
            $query = str_ireplace('FOUND_ROWS()', 'COUNT(1) as Computed ' . $from_str . ' ' . $where_str, $query);
        }
        return $query;
    }
    
    /**
     * Translate specific queries
     *
     * @since 3.0
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_specific($query)
    {
        if ($this->preg_original == "SELECT COUNT(NULLIF(`meta_value` LIKE '%administrator%', FALSE)), "
                        . "COUNT(NULLIF(`meta_value` LIKE '%editor%', FALSE)), "
                        . "COUNT(NULLIF(`meta_value` LIKE '%author%', FALSE)), "
                        . "COUNT(NULLIF(`meta_value` LIKE '%contributor%', FALSE)), "
                        . "COUNT(NULLIF(`meta_value` LIKE '%subscriber%', FALSE)), "
                        . "COUNT(*) FROM " . $this->prefix . "usermeta WHERE meta_key = '" . $this->prefix . "capabilities'") {
            $query = "SELECT 
    (SELECT COUNT(*) FROM " . $this->prefix . "usermeta WHERE meta_key = '" . $this->prefix . "capabilities' AND meta_value LIKE '%administrator%') as ca, 
    (SELECT COUNT(*) FROM " . $this->prefix . "usermeta WHERE meta_key = '" . $this->prefix . "capabilities' AND meta_value LIKE '%editor%') as cb, 
    (SELECT COUNT(*) FROM " . $this->prefix . "usermeta WHERE meta_key = '" . $this->prefix . "capabilities' AND meta_value LIKE '%author%') as cc, 
    (SELECT COUNT(*) FROM " . $this->prefix . "usermeta WHERE meta_key = '" . $this->prefix . "capabilities' AND meta_value LIKE '%contributor%') as cd, 
    (SELECT COUNT(*) FROM " . $this->prefix . "usermeta WHERE meta_key = '" . $this->prefix . "capabilities' AND meta_value LIKE '%subscriber%') as ce, 
    COUNT(*) as c FROM " . $this->prefix . "usermeta WHERE meta_key = '" . $this->prefix . "capabilities'";
            $this->preg_data = array();
        }

        if (stristr($query, "SELECT DISTINCT TOP 50 (" . $this->prefix . "users.ID) FROM " . $this->prefix . "users") !== FALSE) {
            $query = str_ireplace(
                "SELECT DISTINCT TOP 50 (" . $this->prefix . "users.ID) FROM", 
                "SELECT DISTINCT TOP 50 (" . $this->prefix . "users.ID), user_login FROM", $query);
        }
        
        if (stristr($query, 'INNER JOIN ' . $this->prefix . 'terms USING (term_id)') !== FALSE) {
            $query = str_ireplace(
                'USING (term_id)', 
                'ON ' . $this->prefix . 'terms.term_id = ' . $this->prefix . 'term_taxonomy.term_id', $query);
        }

        return $query;
    }

    /**
     * Changing LIMIT to TOP...mimicking offset while possible with rownum, it has turned
     * out to be very problematic as depending on the original query, the derived table
     * will have a lot of problems with columns names, ordering and what not. 
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_limit($query)
    {
        if ( (stripos($query,'SELECT') !== 0 && stripos($query,'SELECT') !== FALSE)
            && (stripos($query,'UPDATE') !== 0  && stripos($query,'UPDATE') !== FALSE) )
            return $query;
		
		/* Search for LIMIT OFFSET first */
		$pattern = '/LIMIT\s*(\d+)((\s*offset?\s*)(\d+)*);{0,1}/is';
		preg_match($pattern, $query, $limit_matches);
		if ( count($limit_matches) == 5 ) {
			if ( $this->delete_query ) {
				return $query;
			}
			
			// Check for true offset
			if ($limit_matches[4] > 0 ) {
				$true_offset = true;
			} else {
				$true_offset = false;
			}

			/* Rewrite the query. */
			if ( $true_offset === false ) {
				/* Get position of LIMIT Statement */
				$limit_pos = stripos($query, $limit_matches[0]);
				
				if ( stripos($query, 'DISTINCT') > 0 ) {
					$query = $this->strReplaceNearest('DISTINCT ', 'DISTINCT TOP ' . $limit_matches[1] . ' ', $query, $limit_pos);
				} else {
					$query = $this->strReplaceNearest('DELETE ', 'DELETE TOP ' . $limit_matches[1] . ' ', $query, $limit_pos);
					$query = $this->strReplaceNearest('SELECT ', 'SELECT TOP ' . $limit_matches[1] . ' ', $query, $limit_pos);
				}

				/* Remove the LIMIT statement */
				 $query = preg_replace($pattern, '', $query);

			} else {
				$limit_matches[1] = (int) $limit_matches[1];
				$limit_matches[4] = (int) $limit_matches[4];

				/* Replace the OFFSET command in its current location. */
				$pretranslate = $query;
				$query = preg_replace($pattern, " OFFSET " . $limit_matches[4] . " ROWS FETCH NEXT " . $limit_matches[1] . " ROWS ONLY", $query);
				
				$this->limit = array(
					'from' => $limit_matches[4], 
					'to' => $limit_matches[1]
				);
			}
			
		} else {
			/* Search for LIMIT without OFFSET. */
			$pattern = '/LIMIT\s*(\d+)((\s*,?\s*)(\d+)*);{0,1}/is';
			$matched = preg_match($pattern, $query, $limit_matches);
			if ( $matched == 1 ) {
				$true_offset = false;
				if ( $this->delete_query ) {
					return $query;
				}
				// Check for true offset
				if ( count($limit_matches) == 5 ) {
					$true_offset = true;
				} elseif ( count($limit_matches) >= 5 && $limit_matches[1] == '0' ) {
					$limit_matches[1] = $limit_matches[4];
				}
	
				// Rewrite the query.
				if ( $true_offset === false ) {
					
					/* Get position of LIMIT Statement */
					$limit_pos = stripos($query, $limit_matches[0]);
					
					if ( stripos($query, 'DISTINCT') > 0 ) {
						$query = $this->strReplaceNearest('DISTINCT ', 'DISTINCT TOP ' . $limit_matches[1] . ' ', $query, $limit_pos);
					} else {
						$query = $this->strReplaceNearest('DELETE ', 'DELETE TOP ' . $limit_matches[1] . ' ', $query, $limit_pos);
						$query = $this->strReplaceNearest('SELECT ', 'SELECT TOP ' . $limit_matches[1] . ' ', $query, $limit_pos);
					}
	
					/* Remove the LIMIT statement */
					 $query = preg_replace($pattern, '', $query);

				} else {
					$limit_matches[1] = (int) $limit_matches[1];
					$limit_matches[4] = (int) $limit_matches[4];
	
					/* Replace the OFFSET command in its current location. */
					$pretranslate = $query;
					$query = preg_replace($pattern, " OFFSET " . $limit_matches[1] . " ROWS FETCH NEXT " . $limit_matches[4] . " ROWS ONLY", $query);
	
					$this->limit = array(
						'from' => $limit_matches[1], 
						'to' => $limit_matches[4]
					);
				}
			}
		}
		
        return $query;
    }


    /**
     * Changing FIND_IN_SET to PATINDEX 
     *
     * @since PN 0.10.3
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_findinset($query)
    {
        if ( (stripos($query,'SELECT') !== 0 && stripos($query,'SELECT') !== FALSE)
            && (stripos($query,'UPDATE') !== 0  && stripos($query,'UPDATE') !== FALSE) ) {
            return $query;
        }
        $pattern = "/FIND_IN_SET\((.*),(.*)\)/";
        $matched = preg_match($pattern, $query, $matches);
        if ( $matched == 0 ) {
            return $query;
        }
        // Replace the FIND_IN_SET
        $query = preg_replace($pattern, "PATINDEX(','+" . $matches[1] . "+',', ','+" . $matches[2] . "+',')", $query);

        return $query;
    }


    /**
     * Replace From UnixTime, utc_timestamp and now()
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_now_datetime($query)
    {
        $replacement = 'getdate()';
        $query = preg_replace('/(from_unixtime|unix_timestamp)\s*\(([^\)]*)\)/i', $replacement, $query);
        $query = str_ireplace('NOW()', $replacement, $query);
        $query = str_ireplace('utc_timestamp()', 'getutcdate()', $query);

        // REPLACE dayofmonth which doesn't exist in T-SQL
        $check = $query;
        $query = preg_replace('/dayofmonth\((.*?)\)/i', 'DATEPART(DD,\1)',$query);
        if ($check !== $query) {
            $as_array = $this->get_as_fields($query);
            if (empty($as_array)) {
                $query = str_ireplace('FROM','as dom FROM',$query);
                $query = str_ireplace('* as dom','*',$query);
            }
        }
        return $query;
    }

    /**
     * Order By within a Select Distinct needs to have an field for every alias
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_distinct_orderby($query)
    {
        if ( stripos($query, 'tribe_event_' ) ) {
            if ( stripos($query, 'post_date ASC' ) ){
                $query = str_replace('.ID  FROM', '.ID, post_date, menu_order  FROM', $query);
            }
            if ( stripos($query, '.*, MIN(' ) ){
                $query = str_replace('ORDER BY', 'group by ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count ORDER BY', $query);
            }
        } else {
            if (preg_match('/^\s*SELECT\s*DISTINCT/i', $query)) {
                if ( stripos($query, 'ORDER') > 0 ) {
                    $ord = '';
                    $order_pos = stripos($query, 'ORDER');
                    $ob = stripos($query, 'BY', $order_pos);
                    if ( $ob > $order_pos ) {
                        $fields = $this->get_as_fields($query);
                        if ( stripos($query, ' ASC', $ob) > 0 ) {
                            $ord = stripos($query, ' ASC', $ob);
                        }
                        if ( stripos($query, ' DESC', $ob) > 0 ) {
                            $ord = stripos($query, ' DESC', $ob);
                        }
						if (sizeof($fields) > 0) {
							$str = 'BY ';
							$str .= implode(', ',$fields);
							$query = substr_replace($query, $str, $ob, ($ord-$ob));
						}
                        $query = str_replace('ORDER BY BY', 'ORDER BY', $query);
                    }
                }
            }
        }
        return $query;
    }

    /**
     * To use REPLACE() fields need to be cast as varchar
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
     function translate_replace_casting($query)
     {
        $query = preg_replace('/REPLACE\((.*?),.*?(.*?),.*?(.*?)\)/i', 'REPLACE(cast(\1 as nvarchar(max)),cast(\2 as nvarchar(max)),cast(\3 as nvarchar(max)))', $query);
        return $query;
     }

    /**
     * To sort text fields they need to be first cast as varchar
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_sort_casting($query)
    {
        if ( stripos($query, 'ORDER') > 0 ) {
            $ord = '';
            $order_pos = stripos($query, 'ORDER');
            if ( stripos($query, 'BY', $order_pos) == ($order_pos + 6) && stripos($query, 'OVER(', $order_pos - 5) != ($order_pos - 5)) {
                $ob = stripos($query, 'BY', $order_pos);
                if ( stripos($query,' ASC', $ob) > 0 ) {
                    $ord = stripos($query, ' ASC', $ob);
                }
                if ( stripos($query,' DESC', $ob) > 0 ) {
                    $ord = stripos($query, ' DESC', $ob);
                }

                $params = substr($query, ($ob + 3), ($ord - ($ob + 3)));
                $params = preg_split('/[\s,]+/', $params);
                $p = array();
                foreach ( $params as $value ) {
                    $value = str_replace(',', '', $value);
                    if ( !empty($value) ) {
                        $p[] = $value;
                    }
                }
                $str = '';

                foreach ($p as $v ) {
                    $match = false;
                    foreach( $this->fields_map->read() as $table => $table_fields ) {
                        if ( is_array($table_fields) ) {
                            foreach ( $table_fields as $field => $field_meta) {
                                if ($field_meta['type'] == 'ntext' || $field_meta['type'] == 'text') {
                                    if ( $v == $table . '.' . $field || $v == $field) {
                                        $match = true;
                                    }
                                }
                            }
                        }
                    }
                    if ( $match ) {
                        $str .= 'cast(' . $v . ' as nvarchar(255)), ';
                    } else {
                        $str .= $v . ', ';
                    }
                }
                $str = rtrim($str, ', ');
                $query = substr_replace($query, $str, ($ob + 3), ($ord - ($ob + 3)));
            }
        }
        return $query;
    }

    /**
     * Meta key fix. \_%  to  [_]%
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_column_type($query)
    {
        if ( stripos($query, "LIKE '\_%'") > 0 ) {
            $start_pos = stripos($query, "LIKE '\_%'");
            $end_pos = $start_pos + 10;
            $str = "LIKE '[_]%'";
            $query = substr_replace($query, $str, $start_pos, ($end_pos - $start_pos));
        }
        return $query;
    }


    /**
     * Remove group by stmt in certain queries as T-SQL will
     * want all column names to execute query properly
     *
     * FIXES: Column 'wp_posts.post_author' is invalid in the select list because
     * it is not contained in either an aggregate function or the GROUP BY clause.
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_remove_groupby($query)
    {
        $query = str_ireplace("GROUP BY {$this->prefix}posts.ID ", ' ', $query);
        // Fixed query for archives widgets.
        $query = str_ireplace(
            'GROUP BY YEAR(post_date), MONTH(post_date) ORDER BY post_date DESC',
            'GROUP BY YEAR(post_date), MONTH(post_date) ORDER BY year DESC, month DESC',
            $query
        );
        return $query;
    }


    /**
     * When INSERTING 0001-01-01 00:00:00 or '' for datetime SQL Server says wtf
     * because it's null value begins at 1900-01-01...so lets change this to current time.
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_insert_nulltime($query)
    {

        if ( !$this->insert_query ) {
            return $query;
        }

        // Lets grab the fields to be inserted into and their position
        // based on the csv.
        $first_paren = stripos($query, '(', 11) + 1;
        $last_paren = $this->get_matching_paren($query, $first_paren);
        $fields = explode(',',substr($query, $first_paren, ($last_paren - $first_paren)));
        $date_fields = array();
        $date_fields_map = $this->fields_map->by_type('date');
        foreach ($fields as $key => $field ) {
            $field = trim($field);

            if ( in_array($field, $date_fields_map) ) {
                $date_fields[] = array('pos' => $key + 1, 'field' => $field); // increment position because preg_data is 1 indexed
            }
        }

        // we have date fields to check
        if ( count($date_fields) > 0 ) {
            // values are in the preg_data array, we'll fix them there
            foreach ( $date_fields as $df ) {
                $v = $this->preg_data[$df['pos']];
                $quote = ( stripos($v, "'0001-01-01 00:00:00'") === 0 || $v === "''" ) ? "'" : '';
                if ( stripos($v, '0001-01-01 00:00:00') === 0
                    || stripos($v, "'0001-01-01 00:00:00'") === 0
                    || $v === "''" ) {
                    if ( stripos($df['field'], 'gmt') > 0 ) {
                        $v = $quote . gmdate('Y-m-d H:i:s') . $quote;
                    } else {
                        $v = $quote . date('Y-m-d H:i:s') . $quote;
                    }
                }
                $this->preg_data[$df['pos']] = $v;
            }
        }
 
        return $query;
    }

    /**
     * When INSERTING into an identity column - DEFAULT and NULL should be used
     * the identity column will be stripped out of the query
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_insert_identitynull($query)
    {

        if ( !$this->insert_query ) {
            return $query;
        }

        // find the primary_id field for the table
        $first_paren = stripos($query, '(', 11) + 1;
        $last_paren = $this->get_matching_paren($query, $first_paren);
        $fields = explode(',',substr($query, $first_paren, ($last_paren - $first_paren)));
        $identity_field = null;
        $identity_fields_map = $this->fields_map->by_type('primary_id');
        foreach ($fields as $key => $field ) {
            $field = trim($field);

            if ( in_array($field, $identity_fields_map) ) {
               $identity_field = array('pos' => $key + 1, 'field' => $field); // increment position because preg_data is 1 indexed

                // so we have an identity field, let's see if it has NULL or DEFAULT inserted
                $value = trim($this->preg_data[$identity_field['pos']]);

                if (stripos($value, 'NULL') === 0 ||
                    stripos($value, 'DEFAULT') === 0) {
                    // remove column from query
                    $query = str_replace($identity_field['field'] . ',', '', $query);
                    // remove preg replacement from query
                    $query = str_replace('%' . $identity_field['pos'] . '$s,', '', $query);
                    // note we don't remove the preg_data, this is because it will screw up the sprintf later on if we do
                }
            }
        }
 
        return $query;
    }

    /**
     * The data types text and varchar are incompatible in the equal to operator.
     * TODO: Have a check for the appropriate table of the field to avoid collision
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_incompat_data_type($query)
    {
        if ( !$this->select_query && !$this->delete_query ) {
            return $query;
        }
        
        $operators = array(
            '='  => 'LIKE',
            '!=' => 'NOT LIKE',
            '<>' => 'NOT LIKE'
        );
        
        $field_types = array('ntext', 'nvarchar', 'text', 'varchar');

        foreach($this->fields_map->read() as $table => $table_fields) {
            if (!is_array($table_fields)) {
                continue;
            }
            foreach ($table_fields as $field => $field_meta) {
                if ( !in_array($field_meta['type'], $field_types) ) {
                    continue;
                }
                foreach($operators as $oper => $val) {
                    $query = preg_replace('/\s+'.$table . '.' . $field.'\s*'.$oper.'/i', ' '.$table . '.' . $field . ' ' . $val, $query);
                    $query = preg_replace('/\s+'.$field.'\s*'.$oper.'/i', ' ' . $field . ' ' . $val, $query);
                    // check for integers to cast.
                    $query = preg_replace('/\s+LIKE\s*(-?\d+)/i', " {$val} cast($1 as nvarchar(max))", $query);
                }
            }
            
        }
        
        return $query;
    }

    /**
     * General create/alter query translations
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_create_queries($query)
    {
        if ( !$this->create_query && !$this->alter_query) {
            return $query;
        }

        // This needs all the data to work with
        if (!empty($this->preg_data)) {
            $query = vsprintf($query, $this->preg_data);
        }
        $this->preg_data = array();

        // fix enum as it doesn't exist in T-SQL
        if (stripos($query, 'enum(') !== false) {
            $enums = array_reverse($this->stripos_all($query, 'enum('));
            foreach ($enums as $start_pos) {
                $end = $this->get_matching_paren($query, $start_pos + 5);
                // get values inside enum
                $values = substr($query, $start_pos + 5, ($end - ($start_pos + 5)));
                $values = explode(',', $values);
                $all_int = true;
                foreach ($values as $value) {
                    $val = trim(str_replace("'", '', $value));
                    if (!is_numeric($val) || (int) $val != $val) {
                        $all_int = false;
                    }
                }
                // if enum of ints create an appropriate int column otherwise create a varchar
                if ($all_int) {
                    $query = substr_replace($query, 'smallint', $start_pos, ($end + 1) - $start_pos);
                } else {
                    $query = substr_replace($query, 'nvarchar(255)', $start_pos, ($end + 1) - $start_pos);
                }
            }
        }
        
        // remove IF NOT EXISTS as that doesn't exist in T-SQL
        $query = str_ireplace(' IF NOT EXISTS', '', $query);
    
        // save array to file_maps
        $this->fields_map->update_for($query);

        // change auto increment to indentity
        $start_positions = array_reverse($this->stripos_all($query, 'auto_increment'));
        if( stripos($query, 'auto_increment') > 0 ) {
            foreach ($start_positions as $start_pos) {
                $query = substr_replace($query, 'IDENTITY(1,1)', $start_pos, 14);
            }
        }
        if(stripos($query, 'AFTER') > 0) {
            $start_pos = stripos($query, 'AFTER');
            $query = substr($query, 0, $start_pos);
        }
        // replacement of certain data types and functions
        $fields = array(
            'int (',
            'int(',
            'index (',
            'index(',
        );

        // if alter table query - ADD COLUMN needs to become simply ADD
        if ($this->alter_query) {
            if (( stripos($query, 'ALTER COLUMN') > 0) ||
                 (stripos($query, 'CHANGE COLUMN') > 0) ||
                 (stripos($query, 'ADD KEY') > 0)) {
                $query = '';
            }
            $query = str_replace('ADD COLUMN', 'ADD', $query);
        }

        foreach ( $fields as $field ) {
            // reverse so that when we make changes it wont effect the next change.
            $start_positions = array_reverse($this->stripos_all($query, $field));
            foreach ($start_positions as $start_pos) {
                $first_paren = stripos($query, '(', $start_pos);
                $end_pos = $this->get_matching_paren($query, $first_paren + 1) + 1;
                if( $field == 'index(' || $field == 'index (' ) {
                    $query = substr_replace($query, '', $start_pos, $end_pos - $start_pos);
                } else {
                    $query = substr_replace($query, rtrim(rtrim($field,'('), ' '), $start_pos, ($end_pos - $start_pos));
                }
            }
        }

        $query = str_ireplace(" bool NOT NULL DEFAULT 0,", ' bit NOT NULL DEFAULT 0,', $query);
        $query = str_ireplace(" bool DEFAULT 0,", ' bit DEFAULT 0,', $query);
        $query = str_ireplace("'0001-01-01 00:00:00'", 'getdate()', $query);
        $query = str_ireplace("'0000-00-00 00:00:00'", 'getdate()', $query);
        $query = str_ireplace("default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP", '', $query);

        // strip unsigned
        $query = str_ireplace("unsigned ", '', $query);
        $query = str_ireplace("unsigned,", ',', $query);

        // change mediumint
        $query = str_ireplace("mediumint", "int", $query);

        if ($this->create_query) {
            // strip collation, engine type, etc from end of query
            $pos = stripos($query, '(', stripos($query, 'TABLE '));
            $end = $this->get_matching_paren($query, $pos + 1);
            $query = substr_replace($query, ');', $end);
        }

        /* We remove character set and collation stuff because that information is per table in sql server */
        $query = str_ireplace("DEFAULT CHARACTER SET utf8", '', $query);
        $query = str_ireplace("CHARACTER SET utf8", '', $query);

        if ( ! empty($this->charset) ) {
            $query = str_ireplace("DEFAULT CHARACTER SET {$this->charset}", '', $query);
        }
        if ( ! empty($this->collate) ) {
            $query = str_ireplace("COLLATE {$this->collate}", '', $query);
        }
        
        // add collation
        $ac_types = array('tinytext', 'longtext', 'mediumtext', 'text', 'varchar');
        foreach ($ac_types as $ac_type) {
            $start_positions = array_reverse($this->stripos_all($query, $ac_type));
            foreach ($start_positions as $start_pos) {
                if ($ac_type == 'varchar') {
                    if (substr($query, $start_pos - 1, 8) == 'NVARCHAR') {
                        continue;
                    }
                    $query = substr_replace($query, 'NVARCHAR', $start_pos, strlen($ac_type));
                    $end = $this->get_matching_paren($query, $start_pos + 9);
                    $sub = substr($query, $end + 2, 7);
                    $end_pos = $end + 1;
                } else {
                    if ($ac_type == 'text' && substr($query, $start_pos - 1, strlen($ac_type) + 1) == 'NTEXT') {
                        continue;
                    }
                    $query = substr_replace($query, 'NVARCHAR(MAX)', $start_pos, strlen($ac_type));
                    $sub = substr($query, $start_pos + 14, 7);
                    $end_pos = $start_pos + 13;
                }

        /*      if ($sub !== 'COLLATE') {
                    $query = $this->add_collation($query, $end_pos);
                } */
            }
        }

        $keys = array();
        if ($this->create_query) {
            $table_pos = stripos($query, ' TABLE ') + 6;
            $table = substr($query, $table_pos, stripos($query, '(', $table_pos) - $table_pos);
            $table = trim($table);

            // get column names to check for reserved words to encapsulate with [ ]
            foreach($this->fields_map->read() as $table_name => $table_fields) {
                if ($table_name == $table && is_array($table_fields)) {
                    foreach ($table_fields as $field => $field_meta) {
                        if (in_array($field, $this->reserved_words)) {
                            $query = preg_replace('/(?<!NOT NULL)\s(' . $field . ')/', "[{$field}]", $query);
                        }
                    }
                }
            }
        }

        // get primary key constraints
        if ( stripos($query, 'PRIMARY KEY') > 0) {
            $start_positions = $this->stripos_all($query, 'PRIMARY KEY');
            foreach ($start_positions as $start_pos) {
                $start = stripos($query, '(', $start_pos);
                $end_paren = $this->get_matching_paren($query, $start + 1);
                $field = explode(',', substr($query, $start + 1, $end_paren - ($start + 1)));
                foreach ($field as $k => $v) {
                    if (stripos($v, '(') !== false) {
                        $field[$k] = preg_replace('/\(.*\)/', '', $v);
                    }
                }
                $keys[] = array('type' => 'PRIMARY KEY', 'pos' => $start_pos, 'field' => $field);
            }
        }
        // get unique key constraints
        if ( stripos($query, 'UNIQUE KEY') > 0) {
            $start_positions = $this->stripos_all($query, 'UNIQUE KEY');
            foreach ($start_positions as $start_pos) {
                $start = stripos($query, '(', $start_pos);
                $end_paren = $this->get_matching_paren($query, $start + 1);
                $field = explode(',', substr($query, $start + 1, $end_paren - ($start + 1)));
                foreach ($field as $k => $v) {
                    if (stripos($v, '(') !== false) {
                        $field[$k] = preg_replace('/\(.*\)/', '', $v);
                    }
                }
                $keys[] = array('type' => 'UNIQUE KEY', 'pos' => $start_pos, 'field' => $field);
            }
        }
        // get key constraints
        if ( stripos($query, 'KEY') > 0) {
            $start_positions = $this->stripos_all($query, 'KEY');
            foreach ($start_positions as $start_pos) {
                if (substr($query, $start_pos - 7, 6) !== 'UNIQUE'
                    && substr($query, $start_pos - 8, 7) !== 'PRIMARY'
                    && (substr($query, $start_pos - 1, 1) == ' ' || substr($query, $start_pos - 1, 1) == "\n")) {
                    $start = stripos($query, '(', $start_pos);
                    $end_paren = $this->get_matching_paren($query, $start + 1);
                    $field = explode(',', substr($query, $start + 1, $end_paren - ($start + 1)));
                    foreach ($field as $k => $v) {
                        if (stripos($v, '(') !== false) {
                            $field[$k] = preg_replace('/\(.*\)/', '', $v);
                        }
                    }
                    $keys[] = array('type' => 'KEY', 'pos' => $start_pos, 'field' => $field);
                }
            }
        }

        $count = count($keys);
        $add_primary = false;
        $key_str = '';
        $lowest_start_pos = false;
        $unwanted = array(
            'slug',
            'name',
            'term_id',
            'taxonomy',
            'term_taxonomy_id',
            'comment_approved',
            'comment_post_ID',
            'comment_approved',
            'link_visible',
            'post_id',
            'meta_key',
            'post_type',
            'post_status',
            'post_date',
            'ID',
            'post_name',
            'post_parent',
            'user_login',
            'user_nicename',
            'user_id',
        );
        $primary_key_found = FALSE;
        for ($i = 0; $i < $count; $i++) {
            if ($keys[$i]['pos'] < $lowest_start_pos || $lowest_start_pos === false) {
                $lowest_start_pos = $keys[$i]['pos'];
            }
            if ($keys[$i]['type'] == 'PRIMARY KEY') {
                $add_primary = true;
            }
            switch ($keys[$i]['type']) {
                case 'PRIMARY KEY':
                    $str = "CONSTRAINT [" . $table . "_" . implode('_', $keys[$i]['field']) . "] PRIMARY KEY CLUSTERED (" . implode(',', $keys[$i]['field']) . ") WITH (IGNORE_DUP_KEY = OFF)";
                    $primary_key_found = TRUE;
                    /*
                    if (!$this->azure ) {
                        $str .= " ON [PRIMARY]";
                    }
                    */
                break;
                case 'UNIQUE KEY':
                    $check = true;
                    foreach ($keys[$i]['field'] as $field) {
                        if (in_array($field, $unwanted)) {
                            $check = false;
                        }
                    }
                    if ($check) {
                        if ( $primary_key_found ) {
                            $str = 'CONSTRAINT [' . $table . '_' . implode('_', $keys[$i]['field']) . '] UNIQUE NONCLUSTERED (' . implode(',', $keys[$i]['field']) . ')';
                        } else {
                            $str = 'CONSTRAINT [' . $table . '_' . implode('_', $keys[$i]['field']) . '] PRIMARY KEY CLUSTERED (' . implode(',', $keys[$i]['field']) . ')';
                            $primary_key_found = TRUE;
                        }
                    } else {
                        $str = '';
                    }
                break;
                case 'KEY':
                    // CREATE NONCLUSTERED INDEX index_name ON table(col1,col2)
                    $check = true;
                    $str = '';
                    foreach ($keys[$i]['field'] as $field) {
                        if (in_array($field, $unwanted)) {
                            $check = false;
                        }
                    }
                    if ($check) {
                        if (!is_array($this->following_query) && $this->following_query === false) {
                            $this->following_query = array();
                        } elseif (!is_array($this->following_query)) {
                            $this->following_query = array($this->following_query);
                        }
                        if ( $this->azure && !$primary_key_found ) {
                            $this->following_query[] = 'CREATE CLUSTERED INDEX ' . 
                            $table . '_' . implode('_', $keys[$i]['field']) . 
                            ' ON '.$table.'('.implode(',', $keys[$i]['field']).')';
                            $primary_key_found = TRUE;
                        } else {
                            $this->following_query[] = 'CREATE NONCLUSTERED INDEX ' . 
                            $table . '_' . implode('_', $keys[$i]['field']) . 
                            ' ON '.$table.'('.implode(',', $keys[$i]['field']).')';
                        }
                    }
                break;
            }
            if ($i !== $count - 1 && $str !== '') {
                $str .= ',';
            }
            $key_str .= $str . "\n";
        }
        if ($key_str !== '') {
            if ($add_primary && !$this->azure) {
                //$query = substr_replace($query, $key_str . ") ON [PRIMARY];", $lowest_start_pos);
            } else {
                $query = substr_replace($query, $key_str . ");", $lowest_start_pos);
            }
        }

        return $query;
    }

    /**
     * Given a first parenthesis ( ...will find its matching closing paren )
     *
     * @since 2.7.1
     *
     * @param string $str given string
     * @param int $start_pos position of where desired starting paren begins+1
     *
     * @return int position of matching ending parenthesis
     */
    function get_matching_paren($str, $start_pos)
    {
        $count = strlen($str);
        $bracket = 1;
        for ( $i = $start_pos; $i < $count; $i++ ) {
            if ( $str[$i] == '(' ) {
                $bracket++;
            } elseif ( $str[$i] == ')' ) {
                $bracket--;
            }
            if ( $bracket == 0 ) {
                return $i;
            }
        }
    }

    /**
     * Get the Aliases in a query
     * E.G. Field1 AS yyear, Field2 AS mmonth
     * will return array with yyear and mmonth
     *
     * @since 2.7.1
     *
     * @param string $str a query
     *
     * @return array array of aliases in a query
     */
    function get_as_fields($query)
    {
		/* Strip out any CAST conversion functions */
		$query = preg_replace('/cast\s*\(.*as\s*\w+\s*\)/is','',$query);
        $arr = array();
        $tok = preg_split('/[\s,]+/', $query);
        $count = count($tok);
        for ( $i = 0; $i < $count; $i++ ) {
            if ( strtolower($tok[$i]) === 'as' ) {
                $arr[] = $tok[($i + 1)];
            }
        }
        return $arr;
    }

    /**
    * Fix for SQL Server returning null values with one space.
    * Fix for SQL Server returning datetime fields with milliseconds.
    * Fix for SQL Server returning integer fields as integer (mysql returns as string)
    *
    * @since 2.7.1
    *
    * @param array $result_set result set array of an executed query
    *
    * @return array result set array with modified fields
    */
    function fix_results($result_set)
    {
        // If empty bail early.
        if ( is_null($result_set)) {
            return false;
        }
        if (is_array($result_set) && empty($result_set)) {
            return array();
        }
        $map_fields = $this->fields_map->by_type('date');
        $fields = array_keys(get_object_vars(current($result_set)));
        foreach ( $result_set as $key => $result ) {
            // Remove milliseconds
            foreach ( $map_fields as $date_field ) {
                if ( isset($result->$date_field) ) {
                    // date_format is a PHP5 function. sqlsrv is only PHP5 compat
                    // the result set for datetime columns is a PHP DateTime object, to extract
                    // the string we need to use date_format().
                    if (is_object($result->$date_field)) {
                        $result_set[$key]->$date_field = date_format($result->$date_field, 'Y-m-d H:i:s');
                    }
                }
            }
            // Check for null values being returned as space and change integers to strings (to mimic mysql results)
            foreach ( $fields as $field ) {
                if ($field == 'crdate' || $field == 'refdate') {
                    $result_set[$key]->$field = date_format($result->$field, 'Y-m-d H:i:s');
                }
                if ( $result->$field === ' ' ) {
                    $result->$field = '';
                }
                if ( is_int($result->$field) ) {
                    $result->$field = (string) $result->$field;
                }
            }
        }
        
        $map_fields = $this->fields_map->by_type('ntext');
        foreach ( $result_set as $key => $result ) {
            foreach ( $map_fields as $text_field ) {
                if ( isset($result->$text_field) ) {
                    $result_set[$key]->$text_field = str_replace("''", "'", $result->$text_field);
                }
            }
        }
        return $result_set;
    }
    
    /**
     * Check to see if INSERT has an ON DUPLICATE KEY statement
     * This is MySQL specific and will be removed and put into 
     * a following_query MERGE STATEMENT
     *
     * @param string $query Query coming in
     * @return string query without ON DUPLICATE KEY statement
     */
	function on_update_to_merge($query) {
	
		if (strpos($query, 'ON DUPLICATE KEY UPDATE') === false) 
			return $query;
			
		/* Get groupings before 'ON DUPLICATE KEY UPDATE' */
		preg_match( '/insert\s+into([\[\]\s0-9,a-z$_]*)\s*\(*([\[\]\s0-9,a-z$_]*)\s*\)*\s*VALUES\s*\((.*?)\)/is', $query, $insertgroups );
		
		/* You should get something like this:
			array(4) {
			  [0]=>
			  string(130) "INSERT INTO wp_blc_synch( container_id, container_type, synched, last_synch)	VALUES( 17839, 'post', 0, '0001-01-01 00:00:00' )"
			  [1]=>
			  string(13) " wp_blc_synch"
			  [2]=>
			  string(50) " container_id, container_type, synched, last_synch"
			  [3]=>
			  string(41) " 17839, 'post', 0, '0001-01-01 00:00:00' "
			}	
		*/
		
		if (sizeof($insertgroups) < 4)
			return $query;
		
		$newsql = 'MERGE INTO ' . $insertgroups[1] . ' WITH (HOLDLOCK) AS target USING ';
	
		$insertfieldlist = $insertgroups[2];
		$insertfields = explode(",", $insertfieldlist);
		$insertvalueslist = $insertgroups[3];
		$insertvalues = explode(",", $insertvalueslist);
		
		/* Get groupings after 'ON DUPLICATE KEY UPDATE' */
		preg_match( '/ON DUPLICATE KEY UPDATE(\s*.*)/is', $query, $updatefields );
		
		/* You should get something like this:
			array(2) {
			  [0]=>
			  string(83) "ON DUPLICATE KEY UPDATE synched = VALUES(synched), last_synch = VALUES(last_synch))"
			  [1]=>
			  string(60) " synched = VALUES(1234), last_synch = VALUES(5678))"
			}
		*/
		
		if (sizeof($updatefields) < 2)
			return $query;
		
		preg_match_all( '/([\[\]0-9a-z$_]*)\s*=\s*VALUES\s*\((.*?)\)/is', $updatefields[1], $updatefieldvalues );
		
		/* You should get something like this:
			array(3) {
			  [0]=>
			  array(2) {
				[0]=>
				string(22) "synched = VALUES(1234)"
				[1]=>
				string(25) "last_synch = VALUES(5678)"
			  }
			  [1]=>
			  array(2) {
				[0]=>
				string(7) "synched"
				[1]=>
				string(10) "last_synch"
			  }
			  [2]=>
			  array(2) {
				[0]=>
				string(4) "1234"
				[1]=>
				string(4) "5678"
			  }
			}
		*/
		
		if (sizeof($updatefieldvalues) < 3)
			return $query;
	
		$fieldnamessize = sizeof($insertfields);
		$valuessize = sizeof($insertvalues);
		$updatefieldssize = sizeof($updatefieldvalues[1]);
		
		/* Create Insert part of command. */
		$insertcmd = '';
		for ($i=0; $i<$fieldnamessize; $i++) {
			if ($insertcmd == '')
				$insertcmd  .= trim($insertvalues[$i]) . " as " . trim($insertfields[$i]);
			else
				$insertcmd  .= "," . trim($insertvalues[$i]) . " as " . trim($insertfields[$i]);
			
		}
		$newsql .= "(SELECT " . $insertcmd . ") AS source (" . trim($insertgroups[2]) . ")";
		
		/* Create ON part of command. */
		$on = '';
		for ($i=0; $i<$fieldnamessize; $i++) {
			if ($on == '')
				$on  .= "source." . trim($insertfields[$i]) . "=target." . trim($insertfields[$i]);
			else
				$on  .= " AND source." . trim($insertfields[$i]) . "=target." . trim($insertfields[$i]);
			
		}
		
		$on = ' ON (' . $on . ')';
		$newsql .= $on . ' WHEN MATCHED THEN UPDATE SET ';
	
		
		/* Create UPDATE part of command. */
		$update = '';
		for ($i=0; $i<$updatefieldssize; $i++) {
			if ($update == '') {
				/* Does the value contain the actual fieldname?  If so, use the insert value. */
				if (trim($updatefieldvalues[1][$i]) == trim($updatefieldvalues[2][$i])) {
						for ($j=0; $j<$fieldnamessize; $j++) {
							if (trim($insertfields[$j]) == trim($updatefieldvalues[1][$i]))

								$update  .= trim($updatefieldvalues[1][$i]) . "=" . trim($insertvalues[$j]);
			
						}
				} else
					$update  .= trim($updatefieldvalues[1][$i]) . "=" . trim($updatefieldvalues[2][$i]);
			} else {
				/* Does the value contain the actual fieldname?  If so, use the insert value. */
				if (trim($updatefieldvalues[1][$i]) == trim($updatefieldvalues[2][$i])) {
						for ($j=0; $j<$fieldnamessize; $j++) {
							if (trim($insertfields[$j]) == trim($updatefieldvalues[1][$i]))
								$update  .= "," . trim($updatefieldvalues[1][$i]) . "=" . trim($insertvalues[$j]);
			
						}
				} else
					$update  .= "," . trim($updatefieldvalues[1][$i]) . "=" . trim($updatefieldvalues[2][$i]);
				
			}
			
		}
		$newsql .= $update . ' WHEN NOT MATCHED THEN INSERT (' . $insertgroups[2] . ') VALUES(' . $insertgroups[3] . ');';
		return $newsql;
	}
	
	/**
	* Replace the last occurrence of a string nearest to $lenOfSubject position
	*
	* @param string $search
	* @param string $replace
	* @param string $subject
	* @param string $lenOfSubject
	* @return string
	*/
	function strReplaceNearest ( $search, $replace, $subject, $lenOfSubject = null ) {
	 
		$lenOfSearch = strlen( $search );
		$posOfSearch = strpos( $subject, $search );
		
		if ($posOfSearch === false)
			return $subject;
			
		if ($lenOfSubject === null)
			$lenOfSubject = strlen($subject);
	 
	 	$valid_pos = $posOfSearch;
	 	while ($posOfSearch <= $lenOfSubject) {
			$valid_pos = $posOfSearch;
			$posOfSearch = strpos( $subject, $search, $valid_pos + 1);
			
			if ($posOfSearch === false)
				break;
		}
		
		return substr_replace( $subject, $replace, $valid_pos, $lenOfSearch );	 
	}	

    /**
     * Check to see if INSERT has an IF NOT EXISTS statement
     * This is MySQL specific and will be removed and put into 
     * a following_query MERGE STATEMENT
     *
     * @param string $query Query coming in
     * @return string query without ON DUPLICATE KEY statement
	 *
	 *  Example:
	 *  IF NOT EXISTS (SELECT * FROM [wp_options] WHERE [option_name] = '_transient_doing_cron') 
	 *  INSERT INTO [wp_options] ([option_name], [option_value], [autoload]) VALUES ('_transient_doing_cron', '1465291530.3025040626525878906250', 'yes') 
	 *  else UPDATE [wp_options] set [option_value] = '1465291530.3025040626525878906250', [autoload] = 'yes' 
	 *  where [option_name] = '_transient_doing_cron'

     */
	function translate_if_not_exists_insert_merge($query) {
	
		if (strpos($query, 'IF NOT EXISTS') === false)
			return $query;
			
		/* Get groupings for INSERT INTO */
		preg_match( '/insert\s+into([\[\]\s0-9,a-z$_]*)\s*\(*([\[\]\s0-9,a-z$_]*)\s*\)*\s*VALUES\s*\((.*?)\)/is', $query, $insertgroups );
		
		/* You should get something like this:
			array(4) {
			  [0]=>
			  string(130) "INSERT INTO wp_blc_synch( container_id, container_type, synched, last_synch)	VALUES( 17839, 'post', 0, '0001-01-01 00:00:00' )"
			  [1]=>
			  string(13) " wp_blc_synch"
			  [2]=>
			  string(50) " container_id, container_type, synched, last_synch"
			  [3]=>
			  string(41) " 17839, 'post', 0, '0001-01-01 00:00:00' "
			}	
		*/
		
		if (sizeof($insertgroups) < 4) 
			return $query;
			
		$insertfieldlist = $insertgroups[2];
		$insertfields = explode(",", $insertfieldlist);
		$insertvalueslist = $insertgroups[3];
		$insertvalues = explode(",", $insertvalueslist);
		
		/* Get groupings for WHERE clause  */
		preg_match( '/WHERE(.*)INSERT\s*INTO/is', $query, $whereclause );
		
		/* You should get something like this:
			array(2) {
			  [0]=>
			  string(83) "WHERE [option_name] = '_transient_doing_cron') INSERT INTO"
			  [1]=>
			  string(60) " [option_name] = '_transient_doing_cron')"
			}
		*/
		
		if (sizeof($whereclause) < 2)
			return $query;
		
		/* strip out the last right paren. */
		$whereclause[1] = $this->strReplaceNearest(')', '', $whereclause[1]);
		$whereclause[1] = trim($whereclause[1]);
		$fieldnamessize = sizeof($insertfields);
		$valuessize = sizeof($insertvalues);
		
		$insertcmd = '';
		$update = '';
		for ($i=0; $i<$fieldnamessize; $i++) {
			/* Create Insert part of command. */
			if ($insertcmd == '')
				$insertcmd  .= trim($insertvalues[$i]) . " as " . trim($insertfields[$i]);
			else
				$insertcmd  .= "," . trim($insertvalues[$i]) . " as " . trim($insertfields[$i]);
			
			/* Create UPDATE part of command. */
			if ($update == '') {
				$update  .= trim($insertfields[$i]) . "=" . trim($insertvalues[$i]);
			} else {
				$update  .= "," . trim($insertfields[$i]) . "=" . trim($insertvalues[$i]);
			}
		}
		
		$newsql = 'MERGE INTO ' . $insertgroups[1] . ' WITH (HOLDLOCK) AS target USING ';
		$newsql .= "(SELECT " . $insertcmd . ") AS source (" . trim($insertgroups[2]) . ")";
		$newsql .= ' ON (target.' . $whereclause[1] . ') WHEN MATCHED THEN UPDATE SET ';
		$newsql .= $update . ' WHEN NOT MATCHED THEN INSERT (' . $insertgroups[2] . ') VALUES(' . $insertgroups[3] . ');';

		return $newsql;
	}
	
    /**
     * Check to see if INSERT has an ON DUPLICATE KEY statement
     * This is MySQL specific and will be removed and put into 
     * a following_query UPDATE STATEMENT
     *
     * @param string $query Query coming in
     * @return string query without ON DUPLICATE KEY statement
     */
     function on_duplicate_key($query)
     {
        if ( stripos($query, 'ON DUPLICATE KEY UPDATE') > 0 ) {
            $table = substr($query, 12, (strpos($query, ' ', 12) - 12));
            // currently just deal with wp_options table
            if (stristr($table, 'options') !== FALSE) {
                $start_pos = stripos($query, 'ON DUPLICATE KEY UPDATE');
                $query = substr_replace($query, '', $start_pos);
                $values_pos = stripos($query, 'VALUES');
                $first_paren = stripos($query, '(', $values_pos);
                $last_paren = $this->get_matching_paren($query, $first_paren + 1);
                $values = explode(',', substr($query, ($first_paren + 1), ($last_paren-($first_paren + 1))));
                if (!isset($values[1])) {
                    $values[1] = '';
                }
                if (!isset($values[2])) {
                    $values[2] = 'no';
                }
                // change this to use mapped fields
                $update = 'UPDATE ' . $table . ' SET option_value = ' . $values[1] . ', autoload = ' . $values[2] . 
                    ' WHERE option_name = ' . $values[0];
                $this->following_query = $update;
            }
        }
        return $query;
     }

    /**
     * Check to see if an INSERT query has multiple VALUES blocks. If so we need create
     * seperate queries for each.
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return array array of insert queries
     */
    function split_insert_values($query)
    {
        $arr = array();
        if (stripos($query, 'INSERT') === 0) {
            $first = substr($query, 0, (stripos($query, 'VALUES') + 7));
            $values = substr($query, (stripos($query, 'VALUES') + 7));
            $arr = preg_split('/\),\s+\(/', $values);
            foreach ($arr as $k => $v) {
                if (substr($v, -1) !== ')') {
                    $v = $v . ')';
                }
                
                if (substr($v, 0, 1) !== '(') {
                    $v = '(' . $v;
                }
                
                $arr[$k] = $first . $v;
            }
        }
        if (count($arr) < 2) {
            return $query;
        }
        return $arr;
    }

    /**
     * Add collation for a field definition within a CREATE/ALTER query
     *
     * @since 2.8
     * @param $type
     *
     * @return string
     */
    function add_collation($query, $pos)
    {
        if (empty($this->collate)) {
            $collation = 'database_default';
        } else {
            $collation = $this->collate;
        }

        $query = substr_replace($query, " COLLATE $collation", $pos, 0);
        return $query;
    }
    
    /**
     * Describe wrapper
     *
     * @since 2.8.5
     * @param $table
     *
     * @return string
     */
    function describe($table)
    {
        $sql = "SELECT
            c.name AS Field
            ,t.name + t.length_string AS Type
            ,CASE c.is_nullable WHEN 1 THEN 'YES' ELSE 'NO' END AS [Null]
            ,CASE
                WHEN EXISTS (SELECT * FROM sys.key_constraints AS kc
                               INNER JOIN sys.index_columns AS ic ON kc.unique_index_id = ic.index_id AND kc.parent_object_id = ic.object_id
                               WHERE kc.type = 'PK' AND ic.column_id = c.column_id AND c.object_id = ic.object_id)
                               THEN 'PRI'
                WHEN EXISTS (SELECT * FROM sys.key_constraints AS kc
                               INNER JOIN sys.index_columns AS ic ON kc.unique_index_id = ic.index_id AND kc.parent_object_id = ic.object_id
                               WHERE kc.type <> 'PK' AND ic.column_id = c.column_id AND c.object_id = ic.object_id)
                               THEN 'UNI'
                ELSE ''
            END AS [Key]
            ,CASE
                WHEN '(getdate())' = ISNULL((
                                        SELECT TOP(1)
                                            dc.definition
                                        FROM sys.default_constraints AS dc
                                        WHERE dc.parent_column_id = c.column_id AND c.object_id = dc.parent_object_id)
                                    , '') THEN '0001-01-01 00:00:00'
                ELSE
                    ISNULL((
                        SELECT TOP(1)
                            dc.definition
                        FROM sys.default_constraints AS dc
                        WHERE dc.parent_column_id = c.column_id AND c.object_id = dc.parent_object_id)
                    , '')
            END AS [Default]
            ,CASE
                WHEN EXISTS (
                    SELECT
                        *
                    FROM sys.identity_columns AS ic
                    WHERE ic.column_id = c.column_id AND c.object_id = ic.object_id)
                        THEN 'auto_increment'
                ELSE ''
            END AS Extra
        FROM sys.columns AS c
        CROSS APPLY (
            SELECT
                t.name AS n1
                ,CASE
                    WHEN c.max_length > 0 AND t.name IN ('varchar', 'char', 'varbinary', 'binary') THEN '(' + CAST(c.max_length AS VARCHAR) + ')'
                    WHEN c.max_length > 0 AND t.name IN ('nvarchar', 'nchar') THEN '(' + CAST(c.max_length/2 AS VARCHAR) + ')'
                    WHEN c.max_length < 0 AND t.name IN ('nvarchar', 'varchar', 'varbinary') THEN '(max)'
                    WHEN t.name IN ('decimal', 'numeric') THEN '(' + CAST(c.precision AS VARCHAR) + ',' + CAST(c.scale AS VARCHAR) + ')'
                    WHEN t.name IN ('float') THEN '(' + CAST(c.precision AS VARCHAR) + ')'
                    WHEN t.name IN ('datetime2', 'time', 'datetimeoffset') THEN '(' + CAST(c.scale AS VARCHAR) + ')'
                    ELSE ''
                END AS length_string
                ,*
            FROM sys.types AS t
            WHERE t.system_type_id = c.system_type_id AND t.system_type_id = t.user_type_id
        ) AS t
        WHERE object_id = OBJECT_ID('{$table}');";
        return $sql;
    }

    /**
     * Get all occurrences(positions) of a string within a string
     *
     * @since 2.8
     * @param $type
     *
     * @return array
     */
    function stripos_all($haystack, $needle, $offset = 0)
    {
        $arr = array();
        while ($offset !== false) {
            $pos = stripos($haystack, $needle, $offset);
            if ($pos !== false) {
                $arr[] = $pos;
                $pos = $pos + strlen($needle);
            }
            $offset = $pos;
        }
        return $arr;
    }
}

if ( !function_exists('str_ireplace') ) {
    /**
     * PHP 4 Compatible str_ireplace function
     * found in php.net comments
     *
     * @since 2.7.1
     *
     * @param string $search what needs to be replaced
     * @param string $replace replacing value
     * @param string $subject string to perform replace on
     *
     * @return string the string with replacements
     */
    function str_ireplace($search, $replace, $subject)
    {
        $token = chr(1);
        $haystack = strtolower($subject);
        $needle = strtolower($search);
        while ( $pos = strpos($haystack, $needle) !== FALSE ) {
            $subject = substr_replace($subject, $token, $pos, strlen($search));
            $haystack = substr_replace($haystack, $token, $pos, strlen($search));
        }
        return str_replace($token, $replace, $subject);
    }
}

if ( !function_exists('stripos') ) {
    /**
     * PHP 4 Compatible stripos function
     * found in php.net comments
     *
     * @since 2.7.1
     *
     * @param string $str the string to search in
     * @param string $needle what we are looking for
     * @param int $offset starting position
     *
     * @return int position of needle if found. FALSE if not found.
     */
    function stripos($str, $needle, $offset = 0)
    {
        return strpos(strtolower($str), strtolower($needle), $offset);
    }
}

if ( !function_exists('strripos') ) {
    /**
     * PHP 4 Compatible strripos function
     * found in php.net comments
     *
     * @since 2.7.1
     *
     * @param string $haystack the string to search in
     * @param string $needle what we are looking for
     *
     * @return int position of needle if found. FALSE if not found.
     */
    function strripos($haystack, $needle, $offset=0)
    {
        if ( !is_string($needle) ) {
            $needle = chr(intval($needle));
        }
        if ( $offset < 0 ) {
            $temp_cut = strrev(substr($haystack, 0, abs($offset)));
        } else{
            $temp_cut = strrev(substr($haystack, 0, max((strlen($haystack) - $offset ), 0)));
        }
        if ( stripos($temp_cut, strrev($needle)) === false ) {
            return false;
        } else {
            $found = stripos($temp_cut, strrev($needle));
        }
        $pos = (strlen($haystack) - ($found + $offset + strlen($needle)));
        return $pos;
    }
}
