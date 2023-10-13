Project Nami
===============

### Version: `3.3.2` ###

### Description: ###
In its current form, Project Nami is basically WordPress powered by Microsoft SQL Server. **All** WordPress core features and functions are supported.

### Why was Project Nami created? ###
The short answer here is, **WordPress doesn't work with SQL Server**. It's a sad story, but a true one. You may be so overjoyed this exists that you have no further questions on the matter. If so, that's great. Proceed to enjoy Project Nami. However, you may be asking yourself, why not just use some sort of database abstraction plugin, or simply write my own ( or use someone elses ) `wp-db.php` drop-in. After all, WordPress already supports replacing the database class with a custom one.

While I would agree with all of this, writing ( or using ) a custom `wp-db.php` drop-in is not enough to get WordPress running on SQL Server. In reality, WP Core is littered with MySQL-specific queries, which means a custom db class won't cover all your bases. WP will remain broken and unsuable.

So, what about using only a translation plugin? This can work, but it's hardly optimal. Every query that comes in needs to be parsed and converted to SQL Server style syntax before it's executed. **Yikes!**

We needed a version of WordPress powered by SQL Server in the cloud on Microsoft Azure. So, we rewrote WP Core to do this very thing. Porting WordPress may seem extreme, but the software simply isn't to the point where true database abstraction is feasible. Maybe someday it will be. In the meantime, Project Nami is an alternative. :)

### Plugin Compatibility ###
We make no attempts to maintain an exhaustive list of WordPress plugins which do or do not work with Project Nami.  There are simply too many plugins available.  But we can offer some guidance on evaluating the plugins you want to use.

Plugins fall into four basic categories…

* Plugins which use the WordPress APIs.  These plugins should always work.  If something is broken, then it is most likely a bug in Project Nami that has not been seen before and needs to be corrected.
* Plugins which query the database directly.  These plugins may work.  Many times the SQL statement will execute successfully.  If it produces an error, we attempt to translate the query and resubmit it to the database.  Translation efforts are not guaranteed to work, and they may introduce slight performance reductions as the query will have to be resubmitted with every failed execution attempt.  We welcome pull requests which help to improve and expand the translation support.  The translation functions are found in /wp-includes/translations.php
* Plugins which create their own database tables.  These plugins are very likely to fail.  While translation functions exist for table creation, there are so many different ways to structure those statements in MySQL that we simply cannot support all of them.  It may be possible to work around some of them through the addition of plugin-specific translation updates as above, and we welcome such submissions.
* Plugins which call the MySQL objects directly.  These plugins are guaranteed to fail.  Obviously the point of Project Nami is to use SQL Server, so the MySQL objects are not configured and in some installations may not even exist.  An example of this type of plugin is WooCommerce.

The fastest way to determine if a plugin is attempting direct database access?  Search within the plugin files for $wpdb as it should only be referenced with DB queries.

### A few things to note: ###
* Project Nami requires ***SQL Server 2012 or later*** in order to function properly. Until this version was released, there wasn't really a SQL Server native method of handling the MySQL `LIMIT` when using an offset. However, `OFFSET FETCH` can now be used in conjunction with `ORDER BY` to achieve the equivalent of a MySQL `LIMIT` with an offset.

* Compatible with SQL Server 2017 on Linux platforms
