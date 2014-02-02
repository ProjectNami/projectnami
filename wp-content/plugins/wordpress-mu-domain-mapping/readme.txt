=== WordPress MU Domain Mapping ===
Contributors: donncha, wpmuguru, automattic
Tags: wordpressmu, domain-mapping, multisite
Tested up to: 3.5.1
Stable tag: 0.5.4.3
Requires at least: 3.1

Map any blog/site on a WordPressMU or WordPress 3.X network to an external domain.

== Description ==
This plugin allows users of a WordPress MU site or WordPress 3.0 network to map their blog/site to another domain.

It requires manual installation as one file must be copied to wp-content/. When upgrading the plugin, remember to update domain_mapping.php and sunrise.php. Full instructions are on the Installation page and are quite easy to follow. You should also read [this page](http://ottopress.com/2010/wordpress-3-0-multisite-domain-mapping-tutorial/) too.

Super administrators must configure the plugin in Super Admin->Domain Mapping. You must enter the IP or IP addresses (comma deliminated) of your server on this page. The addresses are purely for documentation purposes so the user knows what they are (so users can set up their DNS correctly). They do nothing special in the plugin, they're only printed for the user to see.

You may also define a CNAME on this page. It will most likely be the domain name of your network. See below for some restrictions and warnings.

Your users should go to Tools->Domain Mapping where they can add or delete domains. One domain must be set as the primary domain for the blog. When mapping a domain, (like 'example.com') your users must create an A record in their DNS pointing at that IP address. They should use multiple A records if your server uses more than one IP address.
If your user is mapping a hostname of a domain (sometimes called a "subdomain") like www.example.com or blog.example.com it's sufficient to create a CNAME record pointing at their blog url (NOT IP address).

The login page will almost always redirect back to the blog's original domain for login to ensure the user is logged in on the original network as well as the domain mapped one. For security reasons remote login is disabled if you allow users to use their Dashboard on the mapped domain.

Super admins can now choose to either allow users to setup DNS ANAME records by supplying an IP (or list of IP addresses) or set a CNAME but not both (entering a CNAME for the end user voids the use of IP's)

There is a lot of debate on the handling of DNS using CNAME and ANAME so both methods are available depending on your preference and setup.

Things to remember:

* CNAME records that point to other CNAME records should be avoided (RFC 1034 section 5.2.2) so only tell your end users to use your chosen domain name as their CNAME DNS entry if your domain name is an ANAME to an IP address (or addresses)
* Only use the CNAME method if your main domain is an ANAME of an IP address. This is very important. How do you know? Check your dns or ask your hosting company.
* Giving your users the option to just use your chosen domain name and not an IP (or list of IP's) to set as their CNAME will make administration of your WordPressMU blog platform or WordPress 3.0 network easier, an example of this would be purchasing/deploying a new server or indeed adding more servers to use in a round robin scenario. Your end users have no need to worry about IP address changes.
* Finally, telling your end users to use an ANAME IP or CNAME domain name is up to you and how your systems are deployed.
* Further Reading: http://www.faqs.org/rfcs/rfc2219.html
* For localization: place translation files (ie. wordpress-mu-domain-mapping-xx_XX.mo) in the directory wp-content/plugins/wordpress-mu-domain-mapping/languages. You will probably have to create that directory yourself.

== Upgrade Notice ==

= 0.5.4.2 =
WordPress 3.3 compatibility, bugfixes
Set $current_site->site_name

= 0.5.4.1 =
WordPress 3.2 compatibility, bugfixes

= 0.5.4 =
WordPress 3.1 compatibility, localization, IDN warnings, settings page updates, support for SSL and lots of bugfixes

== Changelog ==

= 0.5.4 =
* WordPress 3.1 compatibility.
* Localization fixes.
* IDN warnings about using punycode format.
* Fixed delete domain redirect.
* Better looking setup page.
* Better support for sites using SSL backends.


= 0.5.3 =
* Check input on admin page.
* Allow the primary domain to be ignored.
* Lots more bug fixes.

= 0.5.2 =
* Added www support
* Now supports WordPress 3.0
* Updated readme/instructions

= 0.5.1 =
* Added Cpanel instructions, props Marty Martin (http://mearis.com)
* Filter more urls, props Ryan Waggoner (http://ryanwaggoner.com)
* Added CNAME support, props Martin Guppy
* Added check for COOKIE_DOMAIN, props Scott, http://ocaoimh.ie/wordpress-mu-domain-mapping-05/comment-page-1/#comment-671821

= 0.5 =
* Works in VHOST or folder based installs now.
* Remote login added.
* Admin backend redirects to mapped domain by default but can redirect to original blog url.
* Domain redirect can be 301 or 302.
* List multiple mapped domains on site admin blogs page if mapped
* Bug fixes: set blog_id of the current site's main blog in $current_site
* Bug fixes: cache domain maps correctly, blogid, not blog_id in $wpdb.

= 0.4.3 =
* Fixed bug in content filtering, VHOST check done in admin page, not sunrise.php now.

= 0.4.2 =
* Some actions are actually filters
* Change blog url in posts to mapped domain
* Don't redirect the dashboard to the domain mapped url
* Handle multiple domain mappings correctly.
* Don't let someone map an existing blog
* Only redirect the siteurl and home options if DOMAIN MAPPING set
* Delete domain mapping record if blog deleted
* Show mapping on blog's admin page.

= 0.4.1 =
* The admin pagesnon domain mapped blogs were redirected to an invalid url

= 0.4 =
* Redirect admin pages to the domain mapped url. Avoids problems with writing posts and image urls showing at the wrong url. Updated documentation on IP addresses for site admins.

== Installation ==
1. Install the plugin in the usual way into the regular WordPress plugins folder. Network activate the plugin.
2. Move sunrise.php into wp-content/. If there is a sunrise.php there already, you'll just have to merge them as best you can.
3. Edit wp-config.php and uncomment or add the SUNRISE definition line. If it does not exist please ensure it's on the line above the last "require_once" command.
    `define( 'SUNRISE', 'on' );`
4. As a "super admin", visit Super Admin->Domain Mapping to create the domain mapping database table and set the server IP address or a domain to point CNAME records at.
5. Make sure the default Apache virtual host points at your WordPress MU site or WordPress 3.0 network so it will handle unknown domains correctly. On some hosts you may be required to get a dedicated IP address. A quick check: in a web broswer, type in the IP address of your install. If you are using CPanel, use the Park a Domain menu to set the mapped domain to your main installtion.
6. Do not define COOKIE_DOMAIN in your wp-config.php as it conflicts with logins on your mapped domains.

Illustrated installation instructions can be found [here](http://ottopress.com/2010/wordpress-3-0-multisite-domain-mapping-tutorial/) but you can ignore the instructions to place domain_mapping.php in mu-plugins. Thanks Otto.

= Configuration =

On Super Admin->Domain Mapping you can configure the following settings:

1. "Remote Login" can be disabled. Useful if you're hosting totally separate websites.
2. "Permanent redirect" uses a 301 redirect rather than 302 to send visitors to your domain mapped site.
3. "User domain mapping page" allows you to disable Settings->Domain Mapping that the user uses.
4. "Redirect administration pages to network's original domain (remote login disabled if this redirect is disabled)" - with this checked, if a user visits their dashboard on a mapped domain it will redirect to the dashboard on the non mapped domain. If you don't want this, remote login will be disabled for security reasons.
5. "Disable primary domain check. Sites will not redirect to one domain name. May cause duplicate content issues." - ignore the primary domain setting on your sites. The same content will be available on multiple domains and may cause problems with Google because of duplicate content issues.

Super Admin->Domains allows you to edit the domains mapped to any sites on your network.

= For Cpanel users =

If your domain uses the nameservers on your hosting account you should follow these instructions. If the nameservers are elsewhere change the A record or CNAME as documented above.
Add a wildcard subdomain/virtual host record to your site's DNS record in Web Host Manager (WHM).  If you do not have access to WHM, you must email your web host and ask them to make this one change for you.  Should be no problem:

* Go to "Edit DNS Zone" and select the domain of your WPMU installation and click "Edit".
* Below "Add New Entries Below This Line", enter in the first box (Domain) an asterisk: "*".
* The second box, TTL, should be "14400".
* The third box should be "IN".
* Select A Record Type from the drop down "A".
* And in the last box, paste in the IP address of your website/network.

From Cpanel, click on the "Parked Domains" under the "Domains" section:

* Under "Create a New Parked Domain" enter the domain name you want to add to your network.
* Click the "Add Domain" button.
* It should add the domain to the list of parked domains and under "Redirect to" it will say "not redirected".  That is OKAY.

Now you're ready to do your domain mapping.

== Action Hooks ==
You can now hook into certain parts of the domain_mapping mu-plugin to enable you to extend the features that are built in without the need to modify this plugin. To do this, just create a script within the mu-plugins dir that is called AFTER this plugin (eg, call it domain_mapping_extended.php).
	
* `dm_echo_updated_msg`
		
This hook is for when you want to add extra messages for your end users when they update their settings, the current usage would be to remove the existing action and replace it with your own as shown within the example further down the page.
		
* `dm_handle_actions_init`
	
Before we add our own handlers for our users wanting to update their mappings, we can use this action to maybe connect to your datacenter API ready for communication.  An example would be to load an API class and connect before we send and recieve XML queries. Once again, see the example down the page. Your function can make use of the $domain variable.
	
* `dm_handle_actions_add`
		
When an user adds a domain name to their mappings, this action can be used to perform other tasks on your server.  An example would be to check the user has already set up the DNS records for the name to correctly point to your server.  Your function can make use of the $domain variable.
		
* `dm_handle_actions_primary`
	
This may or may not be commonly used in most cases but is there just in case. Your function can make use of the $domain variable.
		
* `dm_handle_actions_del`
	
When an user deletes one of their domain mappings, this action can be used to reverse what dm_handle_actions_add has done.  An example would be to remove the domain mapping from your server using your datacenter's API.  Your function can make use of the $domain variable.
		
* `dm_delete_blog_domain_mappings`
	
If the blog is ever deleted and you make use of handle_actions, then this action will enable you to tidy up.  An example would be when an user has set up multiple domain mappings and the blog is deleted, your function can remove any mess left behind.  Your function can make use of the $domains array (numbered array of domains).  NOTE, this will also be called if the user has no mappings, care should be taken in the case of an empty array.
		
= EXAMPLE USAGE =
		
`<?php // Filename: mu-plugins/domain_mapping_extended.php


// NOTE,
// 	This example will not 'just work' for anyone, it is to show basic
//	usage of dm_echo_updated_msg, dm_handle_actions_init and
//	dm_handle_actions_add


// We do not need translations but would really like a message when a
// users DNS entry is not ready

function dm_echo_extended_updated_msg() {
	switch( $_GET[ 'updated' ] ) {
		case "add":
			$msg = 'You have added a new domain name.';
			break;
		case "exists":
			$msg = 'That domain name already exists, please try again.';
			break;
		case "primary":
			$msg = 'New primary domain created - enjoy!';
			break;
		case "del":
			$msg = 'The domain name has been removed.';
			break;
		case "dnslookup":
			$msg = 'The domain is not pointing to ' .
			get_site_option( 'dm_cname' ) . ', please set up your DNS.';
		break;
	}
	echo "<div class='updated fade'><p>$msg</p></div>";
}
// REMOVE THE ORIGINAL MESSAGES
remove_action('dm_echo_updated_msg','dm_echo_default_updated_msg');
// REPLACE WITH OUR OWN
add_action('dm_echo_updated_msg','dm_echo_extended_updated_msg');
	

// Prepare to use our Datacenter API

function dm_extended_handle_actions_init($domain) {
	global $datacenter_api;
	// There is a good chance we will be accessing the datacentre API
	require_once(dirname(__FILE__) ."/classes/xmlAbstract.class.php");
	require_once(dirname(__FILE__) ."/classes/xmlParserClass.php");
	require_once(dirname(__FILE__) ."/classes/datacenterAPIClass.php");
	$datacenter_api = new datacenter_api('MY_API_KEY', 'API_TYPE');
}
$datacenter_api = null;
add_action('dm_handle_actions_init', 'dm_extended_handle_actions_init');


// Check the users DNS is ready to go (we are using the CNAME option)
// then add the mapping to the datacenter API

function dm_extended_handle_actions_add($domain) {
	global $datacenter_api;
	// Check the domain has the correct CNAME/ANAME record
	$dmip = gethostbyname(get_site_option( 'dm_cname' ));
	$urip = gethostbyname($domain);
	if ($dmip != $urip) {
		wp_redirect( '?page=domainmapping&updated=dnslookup' );
		exit;
	}
	$datacenter_api->add_mapping($domain);
}
add_action('dm_handle_actions_add', 'dm_extended_handle_actions_add');
?>`
