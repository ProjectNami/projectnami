Project Nami
===============

### Version: `3.0.4` ###

### Description: ###
[![Deploy to Azure](https://aka.ms/deploytoazurebutton)](https://portal.azure.com/#create/Microsoft.Template/uri/https%3A%2F%2Fraw.githubusercontent.com%2FProjectNami%2Fprojectnami%2Fmaster%2Farmtemplates%2Fazuredeploy.json)


In its current form, Project Nami is basically WordPress powered by Microsoft SQL Server. **All** WordPress core features and functions are supported.

### Why was Project Nami created? ###
The short answer here is, **WordPress doesn't work with SQL Server**. It's a sad story, but a true one. You may be so overjoyed this exists that you have no further questions on the matter. If so, that's great. Proceed to enjoy Project Nami. However, you may be asking yourself, why not just use some sort of database abstraction plugin, or simply write my own ( or use someone elses ) `wp-db.php` drop-in. After all, WordPress already supports replacing the database class with a custom one.

While I would agree with all of this, writing ( or using ) a custom `wp-db.php` drop-in is not enough to get WordPress running on SQL Server. In reality, WP Core is littered with MySQL-specific queries, which means a custom db class won't cover all your bases. WP will remain broken and unsuable.

So, what about using only a translation plugin? This can work, but it's hardly optimal. Every query that comes in needs to be parsed and converted to SQL Server style syntax before it's executed. **Yikes!**

We needed a version of WordPress powered by SQL Server in the cloud on Microsoft Azure. So, we rewrote WP Core to do this very thing. Porting WordPress may seem extreme, but the software simply isn't to the point where true database abstraction is feasible. Maybe someday it will be. In the meantime, Project Nami is an alternative. :)

### A few things to note: ###
* See our post on Plugin Compatibility for more information http://projectnami.org/plugin-compatibility/

* Project Nami requires ***SQL Server 2012 or later*** in order to function properly. Until this version was released, there wasn't really a SQL Server native method of handling the MySQL `LIMIT` when using an offset. However, `OFFSET FETCH` can now be used in conjunction with `ORDER BY` to achieve the equivalent of a MySQL `LIMIT` with an offset.

* Compatible with SQL Server 2017 on Linux platforms


### Thank you for your interest in supporting our work! ###

Project Nami is an open source project derived from WordPress and available freely under the GPL. Your contributions help support our team and their expenses as they continue to support, maintain and extend the project.


[![Become a patron](https://projectnami.blob.core.windows.net/siteimages/2020/02/become_a_patron_button.png)](https://patreon.com/projectnami)


### CURRENT PATRONS ###

#### FRIENDS ####
Wagner Abilio
Julio Moraes
