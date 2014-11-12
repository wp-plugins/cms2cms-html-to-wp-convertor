=== CMS2CMS: HTML to WordPress Convertor  ===
Contributors: cms2cms
Tags: html to wordpress, html to wordpress migration, convert html to wordpress, migrate html to wordpress, pages, images, plugin
Requires at least: 3.0.0
Tested up to: 4.0
Stable tag: 3.6.2
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin will help to convert your static HTML website to WordPress in a matter of a few minutes.

== Description ==

This plugin will help to convert your static HTML website to WordPress in a matter of a few minutes. It migrates all pages and images from your HTML site totally automatedly, safely and accurately.

**Learn more how to generate sitemap.xml for free here - http://www.cms2cms.com/faqs/how-do-i-create-xml-sitemap-for-my-html-website/?utm_source=HTML&utm_medium=FAQ-link&utm_campaign=WordPress_plugins**

*Supported WordPress versions:* 4.x (new software versions are constantly being added).

* *There is Free Demo available (migration of limited number of pages).*
* *Full Migration starts from $9.*


= Features of Automated Migration with CMS2CMS  =
* **simple** - no need for coding skills to complete it.
* **saves time** - it takes 15 minutes on average, depending on the size of your site.
* **free Demo migration** - move up to 10 pages to see it in action.
* **24/7 live chat support** - no need to search for support on forums.

**Note. It supports content migration only, the theme won’t be converted into WordPress theme.**

This plugin installs the connection bridge on your WordPress, which is needed for data interaction between the HTML and WordPress websites. Upon activation, you’ll be redirected to CMS2CMS website in order to launch the conversion.

For more details on automated conversion from HTML to WordPress, see here: http://www.cms2cms.com/supported-cms/html-to-wordpress-migration/?utm_source=HTML&utm_medium=Link&utm_campaign=WordPress_plugins

**Website design (styles, themes) isn’t migrated.**

= Steps to Take before Migration =
1. Install WordPress and make sure that Html and WordPress websites are available online.
1. Have Html website FTP access details at hand (host, username, password) - you use them to install connection bridge on Html website.
1. Set up custom URL structure for WordPress (it should be set before the migration for internal links to work correctly, if you change them after migration, site navigation will be broken).
1. Install WordPress plugins supported by CMS2CMS (if you want to migrate metadata or image galleries).

= Video =
[youtube https://www.youtube.com/watch?v=u2o7s6aI-k4]

More info: http://www.cms2cms.com/supported-cms/html-to-wordpress-migration/?utm_source=HTML&utm_medium=Link&utm_campaign=WordPress_plugins

== Installation ==

1. Download the plugin zip file
1. Extract plugin zip file to your PC
1. Upload extracted file to wp-content/plugin directory
1. Go to Admin -> Plugins, find “CMS2CMS Html to WordPress Migration” and click Activate
1. You’ll be redirected to CMS2CMS website in order to complete your migration

== Frequently Asked Questions ==

= How do I сreate XML sitemap for my HTML website? =
You can easily create a sitemap for your site, here are the steps to follow:
1. Use a free sitemap generator like these ones:
 * http://www.xml-sitemaps.com/ (up to 500 pages)
 * http://www.web-site-map.com/xml_sitemap.php (number of pages unlimited). (Provide your website URL to generate your sitemap. We recommend to leave other parameters set by default).
2. Download the sitemap file.
3. Upload the file to your website.

= Your website is unreachable =
If your website cannot be reached, pay attention to the following points:
1. Make sure your site is available online at the moment.
2. It’s possible that your firewall blocks certain IP requests. Contact your system administrator or hosting provider support for details about this issue.

= Your server responds with 401 Unauthorized =
If you get this error, try the following solutions:
1. Ensure that access to your site content is not blocked by HTTP Basic Authentication (http://en.wikipedia.org/wiki/Basic_access_authentication). HTTP Basic Authentication is a protection method which requests additional login and password to access webpage or other resource.
2. Make sure that your website content is available on the Internet during the Migration process.

= Your server responds with 403 Forbidden =
This error means that access to certain files or folders is limited. Find below the possible solutions:
1. Your firewall may be causing this by blocking access to the server for our IP addresses. Please, contact your hosting provider and ask them to add the following IPs to the white-list:
92.52.129.82
204.62.12.42
Port 80.
This is done to enable data exchange between your websites. After the migration is complete, you’ll be able to remove our IPs from the white list.
2. Check the access permissions. For ‘cms2cms’ folder specify the file permissions 755. For files in the ‘cms2cms’ folder specify permissions 644.
3. Find out whether there are access restrictions for bridge file. Usually, restrictions are specified in .htaccess file. Contact your system administrator for details.

= Your server responds with 413 Request Entity Too Large =
It indicates that the request is too large for your server. These are possible solutions:
Increase values for the following parameters: ‘memory_limit’ and ‘post_max_size’ in PHP configuration.
If the module suhosin for PHP is installed on the server, increase the parameter ‘suhosin.post.max_value_length’. Usually, the value of 32 Mb is enough.

= Your server responds with 500 Server Error =
Incorrect permissions for bridge folder are the most common reason of this internal server error.
1. for \'cms2cms\' folder, specify the file permissions 755
2. for \'index.php\', \'bridge.php\' and \'key.php\' files in \'cms2cms\' folder, specify the permissions 644
3. If it won’t help, contact your system administrator who can provide you with server logs access for further error detection. You can also request technical assistance from your hosting provider.

= Failed to connect to host / Operation timed out / Nothing was returned from the server / The connection to your server has timed out =
Each of these errors indicates that your website cannot be reached online. Solutions are as follows:
1. Make sure your site is available online at the moment.
2. It’s possible that your firewall blocks certain IP requests. Contact your system administrator or hosting provider support for details about this issue.

= POST Method Not Allowed =
This is a server error. Contact your system administrator or your hosting provider support to have POST method allowed for your server.

= Site is connected already by another account =
Each CMS2CMS user has the unique key which can be found in the Bridge file. So, the Bridge of user A is different from the Bridge of user B. If you have downloaded a bridge using one account, but you try to migrate with this bridge under another account, you get this error.

To fix it, you should either download the bridge again under the account you are going to use for migration or login to the account you used previously to download the Bridge file.

= Invalid response received =
Сontact us at support@cms2cms.com.

= An error occurred when trying to connect to your site =
Сontact us at support@cms2cms.com.

= An unknown error occurred =
Сontact us at support@cms2cms.com

== Screenshots ==

1. /assets/screenshot-1.png
2. /assets/screenshot-2.png
3. /assets/screenshot-3.png
4. /assets/screenshot-4.png
5. /assets/screenshot-5.png

== Changelog ==

= 2.0.2 =
* Bug fixes

= 2.0.1 =
* Improved Connection Bridge

= 1.0.3 =
* Bug fixes

= 1.0.2 =
* Bug fixes

= 1.0.1 =
* Added German Language
* Minor fixes of html

= 1.0 =
* Initial commit

