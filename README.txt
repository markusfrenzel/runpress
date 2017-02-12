=== RunPress ===
Contributors: markusfrenzel
Tags: runpress,runtastic,running,nordicwalking,cycling,mountainbiking,racecycling,hiking,treadmill,ergometer,tracking,sport,sports,gps,import runtastic,database,import,run,export,export runtastic,
Donate link: http://markusfrenzel.de/wordpress/?page_id=2336
Requires at least: 3.3.1
Tested up to: 4.7
Stable tag: 1.4.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html

Imports your sports activities (have a look at the readme for details) from the Runtastic website. Displays the data via shortcodes on your webpage. Widget included.

== Description ==
Do you ever want to display your sports activities from Runtastic.com on your own Website? This is no problem any longer.

RunPress gives you the opportunity to query the Runtastic website by using your Runtastic username and password. It imports your Runtastic sports activities into a table of your wordpress installation. 

You will be capable of viewing your runtastic sports activities in the admin backend. By the use of shortcodes you're able to show your activities on non-admin pages of your site like posts and pages. Use the shortcode generator to easily create the needed shortcodes without any programming knowledge.

A widget is included to show your very latest sport activity, your high-scores or the last 5 sports activities in a table.

RunPress is continuously under development. Just share your wishes with me and I'll have a look at them.

You want to see it in action? Have a look at the plugin homepage <a href="http://markusfrenzel.de/wordpress/?page_id=2247" target="_blank">here</a>.

Do you think RunPress is useful for you? Then please consider to <a href="http://markusfrenzel.de/wordpress/?page_id=2336" target="_blank">donate</a> to keep the developer motivated and the plugin up and running.

ATTENTION: You MUST have CURL-Support in your PHP.INI active!

RunPress is now multisite capable (implemented February 2016).

Please consider a donation if you like RunPress. Every (even the smallest) donation is going to motivate the developer of RunPress to create new functions and additions to the plugin without splitting it up into a free and a buyable edition.

== Installation ==
1. Make sure that you've registered with Runtastic.com and that you've got your username and password in place.
2. PHP5 or better required on your Wordpress Site. CURL-Support in your PHP.INI must be active!
3. Simply install it from the plugins page in your admin area or download and unzip it into your wordpress directory.
4. Activate it from the plugins page.
5. Go to the RunPress Settings Page and configure it by typing in your Runtastic username and password. That's all

== Frequently Asked Questions ==

= I like RunPress very much. Is it possible to donate to you to show my appreciation to your development? =
Sure. Just place your <a href="http://markusfrenzel.de/wordpress/?page_id=2336" target="_blank">donation</a> here. Even the smallest value will motivate me to keep the development up and running.

= I get a fatal error: Call to undefined function curl_init() =
This plugin uses the PHP curl library. Ask your provider to install / activate the curl library if it is not available on your system.

= Is there some kind of tutorial? =
Have a look at the plugins webpage <a href="http://markusfrenzel.de/wordpress/?page_id=2247" target="_blank">here</a>

= Where can I find documentation on the plugin? =
Refer to the <a href="http://markusfrenzel.de/wordpress/?page_id=2247" target="_blank">Plugin Webpage</a>

= I've installed and configured the plugin but I do not see any sports activities on my webpage! =
You've got to import your activities from Runtastic into your wordpress database and setup a shortcode to display your data in your webpage. Have a look at the admin page, "RunPress", "Local DB" or check the documentation.

= How do I display my activities on my website? =
It is possible in post and pages (via shortcodes) and there is also a widget to display your very latest sport activity, your High-Scores or the last 5 sports activities in the sidebar of your wordpress page.

= How do I create a shortcode? =
The easiest way is to use the integrated shortcode generator. It is located in your admin page, "RunPress", "Shortcode Generator". There a also some examples mentioned.

= What will happen to the data if I uninstall the plugin? =
It depends on how you configured RunPress! I've implemented an option which allows you to deactivate or deinstall the plugin without the loss of your data.

= I've forgotten to set the option of deleting all data and uninstalled your plugin. Which table do I have to delete on my database to get rid of the runtastic data? =
The table is labeled with your MySQL table prefix of your wordpress installation (default: wp_) plus "runpress_db" 

== Importable Activities ==
The following Activities are importable from the Runtastic Website to your local Wordpress Database

* running
* nordicwalking
* cycling
* mountainbiking
* racecycling
* hiking
* treadmill
* ergometer
* elliptical
* spinning
* handbike
* skating
* skateboarding
* rowing
* swimming
* crosscountryskiing
* skiing
* snowboarding
* backcountryskiing
* sledding
* motorbiking
* walking
* riding

== Changelog ==

= 1.4.2 =
* switched back to the correct versioning for the plugin
* maps are now implemented via https (thanks to daniel for the idea)
* added a few more activity types to use with the plugin
* updated the translation

= 1.4.1 =
* fixed a huge bug which prevents users of the plugin to save their runtastic credentials
* fixed some small bugs regarding admin notices and error messages
* fixed a huge bug regarding the ability to disable the plugin and delete the options
* fixed some typos
* added a small help function for fresh installs of the plugin so that the user knows what to do first
* added some text about the differences between premium and the free version of the plugin
* added actual high score table of donations
* fixed a bug which causes error messages even if the user enters correct login data for the runtastic website
* added an option to stop bothering the user about sending a donation
* added some background images

= 1.4.0 =
* added ability to click single entries in the shortcode generator withour using copy and paste of activity id numbers
* changed the way tables are build in the runpress widget and every other table used with runpress to fix a bug with various themes
* optimized runpress tables to fit in screens of different resolutions via css
* fixed some small bugs in the main code which loads needed css files
* fixed a small bug in the runpress shortcode generator
* updated the main import script to the latest version
* updated the jquery-datatables css and js scripts to the latest versions
* fixed a small bug regarding translations of the plugin
* preparing to release a premium version (fee-based) of the plugin

= 1.3.0 =
* added support for multisite wordpress installations
* updated datatable scripts to fix a bug
* added dashboard widget to show simple statistics

= 1.2.0 =
* added a few more types of activities to display: running, hiking, racecycling, mountainbiking, cycling, nordicwalking, ergometer and treadmill.
* added support for those new activity types to use in shortcodes
* widget edited to show the activity type

= 1.1.0 =
* added a new shortcode to display single entries
* some minor changes to the shortcode generator

= 1.0.0 =
* initial version

== Upgrade Notice ==

== Screenshots ==

1. The Settings Page
2. Local Database
3. Synchronization Options
4. Shortcode Generator
5. Widget Output of the last activity
6. Chart which can be displayed in pages/posts
7. Single activity in a post/page
