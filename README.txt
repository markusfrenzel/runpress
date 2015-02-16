=== RunPress ===
Contributors: markusfrenzel
Tags: runpress,runtastic,running,tracking,sport,sport,gps
Donate link: http://markusfrenzel.de/wordpress/?page_id=2336
Requires at least: 3.1
Tested up to: 4.1
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html

A plugin to query the Runtastic website. Returns the data of your running activities.

== Description ==
This plugin gives you the opportunity to query the Runtastic website by using your Runtastic username and password (the password is securely stored in your wordpress database).

Only running activities can be queried and shown on your wordpress site.

Widget included! DataTables are used to display your data. Charts (Google Charts) ready.

ATTENTION: You MUST have CURL-Support in your PHP.INI active!

== Installation ==
Just copy the whole RunPress folder into your plugins folder and activate it in your admin area. Have a look at the settings page of RunPress to configure it.

== Frequently Asked Questions ==
Q: I get a fatal error: Call to undefined function curl_init() 
A: This plugin uses the PHP curl library. Ask your provider to install / activate the curl library if it is not available on your system.

== Changelog ==
1.0.0 - initial version