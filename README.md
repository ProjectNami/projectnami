Project Nami
===============

###Version: `1.5.5`###

###Description:###
[![Deploy to Azure](http://azuredeploy.net/deploybutton.png)](https://deploy.azure.com/?repository=https://github.com/ProjectNami/projectnami/tree/latest)

In its current form, Project Nami is basically WordPress powered by Microsoft SQL Server. **All** WordPress features and functions are supported.

Essentials supported include ( but are not limited to ) the following:

* **import**
* **export**
* **media upload**
* **posts**
* **pages**
* **user management**
* **comments**
* **multisite**

###Why was Project Nami created?###
The short answer here is, **WordPress doesn't work with SQL Server**. It's a sad story, but a true one. You may be so overjoyed this exists that you have no further questions on the matter. If so, that's great. Proceed to enjoy Project Nami. However, you may be asking yourself, why not just use some sort of database abstraction plugin, or simply write my own ( or use someone elses ) `wp-db.php` drop-in. After all, WordPress already supports replacing the database class with a custom one.

While I would agree with all of this, writing ( or using ) a custom `wp-db.php` drop-in is not enough to get WordPress running on SQL Server. In reality, WP Core is littered with MySQL-specific queries, which means a custom db class won't cover all your bases. WP will remain broken and unsuable.

So, what about using only a translation plugin? This can work, but it's hardly optimal. Every query that comes in needs to be parsed and converted to SQL Server style syntax before it's executed. **Yikes!**

We needed a version of WordPress powered by SQL Server in the cloud on Microsoft Azure. So, we rewrote WP Core to do this very thing. Porting WordPress may seem extreme, but the software simply isn't to the point where true database abstraction is feasible. Maybe someday it will be. In the meantime, Project Nami is an alternative. :)

###A few things to note:###
* Project Nami will work with any WordPress plugins/themes that utilize WordPress specific APIs. However, custom SQL queries will most likely fail if they use MySQL-specific syntax. In most cases, these issues can be easily resolved by using a WordPress API. If you absolutely have to use custom SQL, make sure it's MSSQL Server 2012 compliant. **We highly recommend using WordPress APIs everywhere you can.** Among other things, it makes your code portable across Project Nami and WordPress.

* As of version 0.10.0, a fallback translation layer has been added to help with MySQL-specific syntax used by plugins.

* Project Nami requires ***SQL Server 2012 or later*** in order to function properly. Until this version was released, there wasn't really a SQL Server native method of handling the MySQL `LIMIT` when using an offset. However, `OFFSET FETCH` can now be used in conjunction with `ORDER BY` to achieve the equivalent of a MySQL `LIMIT` with an offset.

* Compatible with SQL Server vNext on Linux platforms
