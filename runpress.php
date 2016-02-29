<?php
/*
 * File Name:		runpress.php
 * 
 * Plugin Name: 	RunPress
 * Plugin URI: 		http://markusfrenzel.de/wordpress/?page_id=2247
 * 
 * Description: 	Imports your sports activities (running, nordicwalking, cycling, mountainbiking, racecycling, hiking, treadmill, ergometer) from the Runtastic website. Displays the data via shortcodes on your webpage. Widget included.
 * 
 * Version: 		1.3.0
 * 
 * Author: 			Markus Frenzel
 * Author URI: 		http://www.markusfrenzel.de
 * E-Mail:			wordpressplugins@markusfrenzel.de
 * 
 * Text Domain:		runpress
 * Domain Path:		/languages
 * 
 * License: 		GPLv3
 * 
 * Donate link: 	http://markusfrenzel.de/wordpress/?page_id=2336
 * 
 */

/*
 * Copyright (C) 2014, 2015 Markus Frenzel
 * 
 * This program is free software; you can redistribute it and/or 
 * modify it under the terms of the GNU General Public License as 
 * published by the Free Software Foundation; either version 3 of 
 * the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful, 
 * but WITHOUT ANY WARRANTY; without even the implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the 
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see <http://www.gnu.org/licenses/>. 
 */

/* Prevent direct access to the plugin */
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

/* Globals and needed vars */
global $wpdb;
global $runpress_db_version;
global $runpress_db_name;

// runpress_db_versions
// 1.0.0 - Initial Release
// 1.0.1 - Accept NULL Values on the map_url column
// 1.0.2 - Changed behaviour to support multisites
$runpress_db_version = "1.0.2";
$runpress_db_name = $wpdb->prefix . "runpress_db";

/* Definitions */
define( 'RUNPRESS_PLUGIN_PATH', plugin_dir_path(__FILE__) );	// Used to find the plugin dir fast

/* Required scripts */
require_once( RUNPRESS_PLUGIN_PATH . 'inc/widget/runpress-widget.php' );	// Load the code for the runpress widget
require_once( RUNPRESS_PLUGIN_PATH . 'inc/class.runtastic.php' );			// Load the runtastic class by Timo Schlueter (timo.schlueter@me.com / www.timo.in)

/* Hooks */
register_activation_hook( __FILE__, 'runpress_activate' );		// Create the local DB and so on
register_deactivation_hook( __FILE__, 'runpress_deactivate' );	// If the plugin is deactivated this function starts

/* Actions */
add_action( 'plugins_loaded', 'runpress_autoupdate_db_check' );	// Check for updates if autoupdate has run before
add_action( 'plugins_loaded', 'runpress_load_textdomain' );		// Load the translations
add_action( 'widgets_init', 'runpress_register_widget' );		// Register the runpress widget
add_action( 'admin_menu', 'runpress_admin_menu' );				// Add the admin menu structure
add_action( 'runpress_event_hook', 'runpress_cronjob_event' );	// The scheduled WP-Cron Job (if any)
add_action( 'wp_enqueue_scripts', 'runpress_enqueue_google_api' );
add_action( 'wp_dashboard_setup', 'runpress_add_dashboard_widget' );

/* Actions for multisite environmente */
add_action( 'wpmu_new_blog', 'runpress_create_subscribe_table_mu' );

/* Filters */
add_filter( 'cron_schedules', 'runpress_add_cronjob_definitions' );
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'runpress_action_links' );

/* Filters for multisite environments */
add_filter( 'wpmu_drob_tables', 'runpress_delete_subscribe_table_mu' );

/* Shortcodes */
add_shortcode( 'runpress', 'runpress_shortcode' );

/* Normal code */
if( get_option( 'runpress_option_username' ) == false ) {
	add_action( 'admin_notices', 'runpress_admin_notices' );	// Checks if RunPress is configured yet. If not - display a message.
}

/* Special words which need to be translated and which always get lost in my translation tool if i do not save them the way i do now */
/* feelings */
$runpress_awesome = __( 'awesome', 'runpress' );
$runpress_good = __( 'good', 'runpress' );
$runpress_so_so = __( 'so-so', 'runpress' );
$runpress_sluggish = __( 'sluggish', 'runpress' );
$runpress_injured = __( 'injured', 'runpress' );
/* weather conditions */
$runpress_sunny = __( 'sunny', 'runpress' );
$runpress_cloudy = __( 'cloudy', 'runpress' );
$runpress_rainy = __( 'rainy', 'runpress' );
$runpress_snowy = __( 'snowy', 'runpress' );
$runpress_night = __( 'night', 'runpress' );
/* surfaces */
$runpress_road = __( 'road', 'runpress' );
$runpress_trail = __( 'trail', 'runpress' );
$runpress_offroad = __( 'offroad', 'runpress' );
$runpress_mixed = __( 'mixed', 'runpress' );
$runpress_beach = __( 'beach', 'runpress' );
/* types of activities */
$runpress_running = __( 'running', 'runpress' );
$runpress_hiking = __( 'hiking', 'runpress' );
$runpress_racecycling = __( 'racecycling', 'runpress' );
$runpress_mountainbiking = __( 'mountainbiking', 'runpress' );
$runpress_cycling = __( 'cycling', 'runpress' );
$runpress_nordicwalking = __( 'nordicwalking', 'runpress' );
$runpress_ergometer = __( 'ergometer', 'runpress' );
$runpress_treadmill = __( 'treadmill', 'runpress' );
/* plugin description */
$runpress_plugin_description = __( 'Imports your sports activities (running, nordicwalking, cycling, mountainbiking, racecycling, hiking, treadmill, ergometer) from the Runtastic website. Displays the data via shortcodes on your webpage. Widget included.', 'runpress' );

/*********************
 ***               ***
 ***   FUNCTIONS   ***
 ***               ***
 *********************/

/*
 * Function:   runpress_activate
 * Attributes: none
 * 
 * Needed steps to create a local DB to store the runtastic entries
 * 
 * @since 1.0.0
 */
 
function runpress_activate() {
	global $wpdb;					// Needed wpdb functions
	global $runpress_db_version; 	// Version number of the runpress DB for further DB changes needed
	global $runpress_db_name;		// Name of the local DB
	
	if( function_exists( 'is_multisite' ) && is_multisite() ) {
		// check if it is network activation. if so run the activation function for ech id
		if( $networkwide ) {
			$old_blog = $wpdb->blogid;
			// get all blog ids
			$blogids = $wbdp->get_col( "SELECT blog_id FROM $wpdb->blogs" );
			
			foreach( $blogids as $blog_id ) {
				switch_to_blog( $blog_id );
				// create runpress database table if not exists
				runpress_create_table();
			}
			switch_to_blog( $old_blog );
			return;
		}
	}
	// create database table if not exists
	runpress_create_table();
 }

/*
 * Function:   runpress_create_table()
 * Attributes: none
 * 
 * Function to create the needed table to store the runtastic entries
 * 
 * @since 1.3.0
 */
function runpress_create_table() {
	global $wpdb;
	global $runpress_db_version;
	global $runpress_db_name;
	
	if($wpdb->get_var( "SHOW TABLES LIKE '$runpress_db_name'" ) != $runpress_db_name ) {
			
			$sql = "CREATE TABLE $runpress_db_name (
					id INT (10) NOT NULL AUTO_INCREMENT,
					type VARCHAR(20) NOT NULL,
					type_id INT(3) NOT NULL,
					duration INT(10) NOT NULL,
					distance INT(10) NOT NULL,
					pace FLOAT(10,2) NOT NULL,
					speed VARCHAR(20) NOT NULL,
					kcal INT(10) NOT NULL,
					heartrate_avg INT(10) NOT NULL,
					heartrate_max INT(10) NOT NULL,
					elevation_gain INT(10) NOT NULL,
					elevation_loss INT(10) NOT NULL,
					surface VARCHAR(20) NOT NULL,
					weather VARCHAR(20) NOT NULL,
					feeling VARCHAR(20) NOT NULL,
					weather_id INT(10) NOT NULL,
					feeling_id INT(10) NOT NULL,
					surface_id INT(10) NOT NULL,
					notes TEXT NOT NULL,
					page_url VARCHAR(200) NOT NULL,
					create_route_url_class VARCHAR(200) NOT NULL,
					create_route_url VARCHAR(200) NOT NULL,
					map_url TEXT NULL,
					date_year INT(4) NOT NULL,
					date_month INT(2) NOT NULL,
					date_day INT(2) NOT NULL,
					date_hour INT(2) NOT NULL,
					date_minutes INT(2) NOT NULL,
					date_seconds INT(2) NOT NULL,
					UNIQUE KEY id(id)
					);";
				
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
		add_option( "runpress_option_db_version", $runpress_db_version );
		
		$installed_ver = get_option( "runpress_option_db_version" );
		
		if( $installed_ver != $runpress_db_version ) {
			/* If there will be database changes in the future... */
			
			$sql="ALTER TABLE `$runpress_db_name` CHANGE `map_url` `map_url` text NULL";		
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			update_option( "runpress_option_db_version", $runpress_db_version );
			
			/* $sql = "";
			 * require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			 * dbDelta( $sql );
			 * update_option( "runpress_option_db_version", $runpress_db_version );
			 */
		 }
}
 
/*
 * Function:   runpress_deactivate
 * Attributes: none
 * 
 * If the plugin is deactivated the following steps are taken
 * 
 * @since 1.0.0
 */ 
function runpress_deactivate() {
	global $wpdb;
	global $runpress_db_name;
	
	// check if its running in a multisite environment
	if( function_exists( 'is_multisite' ) && is_multisite() ) {
		$old_blog = $wpdb->blogid;
		// get all blog ids
		$blogids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
		
		foreach( $blogids as $blog_id ) {
			switch_to_blog( $blog_id );
			runpress_delete_options();
			runpress_delete_table();
		}
		switch_to_blog( $old_blog );
	} else {
		$runpress_delete_options();
		$runpress_delete_table();
	}
	/* Delete the scheduled WP-Cron if it is there */
	wp_clear_scheduled_hook( 'runpress_event_hook' );
}

/*
 * Function:   runpress_delete_options
 * Attributes: none
 * 
 * Deletes option on deactivation of the plugin if a user wants to
 * 
 * @since 1.3.0
 */
function runpress_delete_options() {
	if( get_option( 'runpress_option_delete_options' ) == 1 ) {
		delete_option( 'runpress_option_db_version' );
		delete_option( 'runpress_option_username' );
		delete_option( 'runpress_option_userpass' );
		delete_option( 'runpress_option_unittype' );
		delete_option( 'runpress_option_delete_options' );
		delete_option( 'runpress_option_cronjobtime' );
		delete_option( 'runpress_option_runtastic_username' );
		delete_option( 'runpress_runtastic_uid' );
	}
}

/*
 * Function:   runpress_delete_table
 * Attributes: none
 * 
 * Truncates and drops the runpress table on deactivation of the plugin
 * 
 * @since 1.3.0
 */
function runpress_delete_table() {
	global $wpdb;
	global $runpress_db_name;
	
	/* Truncate the database */
	$delete = $wpdb->query( "TRUNCATE TABLE $runpress_db_name" );
	/* Drop the table*/
	$drop = $wpdb-> query( "DROP TABLE IF EXISTS $runpress_db_name" );
}
 
/*
 * Function:   runpress_autoupdate_db_check
 * Attributes: none
 * 
 * Since auto update is active in wordpress use this way of checking updates
 * 
 * @since 1.0.0
 */ 
function runpress_autoupdate_db_check() {
	global $wpdb;
	global $runpress_db_version;
	global $runpress_db_name;
	if( get_site_option( 'runpress_option_db_version' ) != $runpress_db_version ) {
		
		if( get_option( 'runpress_option_db_version' ) < '1.0.1' ) {
			$wpdb->query("ALTER TABLE `$runpress_db_name` MODIFY COLUMN map_url TEXT NULL");
			update_option( 'runpress_option_db_version', $runpress_db_version );
			/* Check if there are entries in the db... update the existing entries */
			$empty_check = $wpdb->get_var( "SELECT COUNT(*) FROM $runpress_db_name" );
			if( $empty_check > 0 ) { runpress_sync_database_manually(); }
		} /* Update V1.0.1 */
		
	}
}

/*
 * Function:   runpress_create_subscribe_table_mu
 * Attributes: Wordpress pregiven
 * 
 * Creates a subscribe table if a new blog is added to a multisite environment
 * 
 * @since 1.3.0
 */
function runpress_create_subscribe_table_mu( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
	if( is_plugin_active_for_network( 'runpress/runpress.php' ) ) {
		switch_to_blog( $blog_id );
		create_subscribe_table();
		restore_current_blog();
	}
}

/*
 * Function:   runpress_delete_subscribe_table_mu
 * Attributes: Wordpress pregiven
 * 
 * Deletes a subscribe table if a blog is deleted from a multisite environment
 * 
 * @since 1.3.0
 */
function runpress_delete_subscribe_table_mu( $tables ) {
	global $wpdb;
	global $runpress_db_name;
	
	$tables[] = $runpress_db_name;
	return $tables;
}
		

/*
 * Function:   runpress_load_textdomain
 * Attributes: none
 * 
 * This function loads the correct translation
 * 
 * @since 1.0.0
 */ 
function runpress_load_textdomain() {
	load_plugin_textdomain( 'runpress', false, dirname(plugin_basename(__FILE__)). '/languages/' );
}

/*
 * Function:   runpress_add_dashboard_widget
 * Attributes: none
 * 
 * Implements the dashboard widget of RunPress
 * 
 * @since 1.3.0
 * 
 */
function runpress_add_dashboard_widget() {
	wp_add_dashboard_widget( 'runpress_dashboard_widget', __( 'RunPress Statistics', 'runpress' ), 'runpress_dashboard_widget_function' );
}

/*
 * Function:   runpress_dashboard_widget_function
 * Attributes: none
 * 
 * Shows the statistics information in the dashboard
 * 
 * @since 1.3.0
 * 
 */
function runpress_dashboard_widget_function() {
	global $wpdb;
	global $runpress_db_name;
	
	$query = $wpdb->get_row( "SELECT type, date_day, date_month, date_year, distance, duration, pace, feeling, map_url FROM $runpress_db_name ORDER BY id desc LIMIT 1" );
	if( $query ) {
		echo __( 'Your latest ', 'runpress' ) . __( $query->type, 'runpress' ) . __( ' activity was ', 'runpress' ) . __( $query->feeling, 'runpress' ) . '.<br /><br />';
	} else {
		echo __( 'Nothing to show here yet.<br /><br />', 'runpress' );
	}
	
	$query_sums = $wpdb->get_results( "SELECT type, COUNT(id) as activitycount FROM $runpress_db_name GROUP BY type" );
	if( $query_sums ) {
		$yearfrom = $wpdb->get_var( "SELECT MIN(date_year) FROM $runpress_db_name" );
		$yearuntil = $wpdb->get_var( "SELECT MAX(date_year) FROM $runpress_db_name" );
		?>
		<div id="da2" style="display:inline"><h2><?php echo __( 'Overall Statistics', 'runpress' ) . '</h2><h3>(' . __( 'Period: ', 'runpress' ) . $yearfrom . ' - ' . $yearuntil . ')</h3>'; ?><a href="javascript:showall()"><?php echo __( 'Show', 'runpress' ); ?></a></div>
		<div id="nd2" style="display:none"><h2><?php echo __( 'Overall Statistics', 'runpress' ) . '</h2><h3>(' . __( 'Period: ', 'runpress' ) . $yearfrom . ' - ' . $yearuntil . ')</h3>'; ?><a href="javascript:hideall()"><?php echo __( 'Show', 'runpress' ); ?></a></div>
		<div id="ve2" style="display:none">
			<div class="runpressTable">
				<div class="runpressTableRow">
					<div class="runpressTableHead">
						<strong><?php echo __( 'Activity', 'runpress' ); ?></strong>
					</div>
					<div class="runpressTableHead">
						<strong><?php echo __( 'Count', 'runpress' ); ?></strong>
					</div>
				</div>
			<?php
			foreach( $query_sums as $query_sum ) {
				?>
				<div class="runpressTableRow">
					<div class="runpressTableCell">
						<?php echo __( $query_sum->type, 'runpress' ); ?>
					</div>
					<div class="runpressTableCell">
						<?php echo $query_sum->activitycount; ?>
					</div>
				</div>
				<?php
			}
			?>
			</div>
		</div>
		<?php
	}
	
	$actual_year = date( "Y" );
	$query_sums_year = $wpdb->get_results( "SELECT type, COUNT(id) as activitycountyear FROM $runpress_db_name WHERE date_year=$actual_year GROUP BY type" );
	if( $query_sums_year ) {
		?>
		<div id="da1" style="display:inline"><h2><?php echo __( 'Statistics', 'runpress' ) . ' ' . $actual_year; ?></h2><a href="javascript:showyear()"><?php echo __( 'Show', 'runpress' ); ?></a></div>
		<div id="nd1" style="display:none"><h2><?php echo __( 'Statistics', 'runpress' ). ' ' . $actual_year; ?></h2><a href="javascript:hideyear()"><?php echo __( 'Hide', 'runpress' ); ?></a></div>
		<div id="ve1" style="display:none">
			<div class="runpressTable">
				<div class="runpressTableRow">
					<div class="runpressTableHead">
						<strong><?php echo __( 'Activity', 'runpress' ); ?></strong>
					</div>
					<div class="runpressTableHead">
						<strong><?php echo __( 'Count', 'runpress' ); ?></strong>
					</div>
				</div>
			<?php
			foreach( $query_sums_year as $query_sum_year ) {
				?>
				<div class="runpressTableRow">
					<div class="runpressTableCell">
						<?php echo __( $query_sum_year->type, 'runpress' ); ?>
					</div>
					<div class="runpressTableCell">
						<?php echo $query_sum_year->activitycountyear; ?>
					</div>
				</div>
				<?php
			}
			?>
			</div>
		</div>
		<?php		
	}
	$path = 'admin.php?page=runpress-donate';
	$url = admin_url( $path );
	$link = "<a href='{$url}'>Donate here</a>";
	echo '<br /><br />';
	echo __( 'Please consider a donation to keep the further development of RunPress up and running.', 'runpress' ) . ' | ' . $link;
	?>
	<script type="text/javascript">
		function showyear() {
			if( document.getElementById ) document.getElementById("ve1").style.display = "inline";
			if( document.getElementById ) document.getElementById("da1").style.display = "none";
			if( document.getElementById ) document.getElementById("nd1").style.display = "inline";
		}
		function hideyear() {
			if( document.getElementById ) document.getElementById("ve1").style.display = "none";
			if( document.getElementById ) document.getElementById("da1").style.display = "inline";
			if( document.getElementById ) document.getElementById("nd1").style.display = "none";
		}
		function showall() {
			if( document.getElementById ) document.getElementById("ve2").style.display = "inline";
			if( document.getElementById ) document.getElementById("da2").style.display = "none";
			if( document.getElementById ) document.getElementById("nd2").style.display = "inline";
		}
		function hideall() {
			if( document.getElementById ) document.getElementById("ve2").style.display = "none";
			if( document.getElementById ) document.getElementById("da2").style.display = "inline";
			if( document.getElementById ) document.getElementById("nd2").style.display = "none";
		}
	</script>
	<style type="text/css">
		.runpressTable {
		    	display: table;
		    	width: 100%;
		}
		.runpressTableRow {
		    	display: table-row;
		}
		.runpressTableHeading {
		    	display: table-header-group;
		    	background-color: #ddd;
		}
		.runpressTableCell, .runpressTableHead {
		    	display: table-cell;
		    	padding: 3px 10px;
		    	border: 1px solid #999999;
		}
		.runpressTableHeading {
		    	display: table-header-group;
		    	background-color: #ddd;
		    	font-weight: bold;
		}
		.runpressTableFoot {
		    	display: table-footer-group;
		    	font-weight: bold;
		    	background-color: #ddd;
		}
		.runpressTableBody {
		    	display: table-row-group;
		}
	</style>
	<?php
}

/*
 * Function:   runpress_register_widget
 * Attributes: none
 * 
 * Register the RunPress widget
 * 
 * @since 1.0.0
 */ 
function runpress_register_widget() {
	register_widget( 'runpress_widget' );
}

/*
 * Function:   runpress_admin_menu
 * Attributes: none
 * 
 * The admin menu plus submenues to setup the plugin for the user
 * 
 * @since 1.0.0
 */ 
function runpress_admin_menu() {
	$hook_suffix = add_menu_page( 'RunPress', 'RunPress', 'manage_options', 'runpress', 'runpress_options', 'dashicons-chart-line', 76 );
	add_submenu_page( 'runpress', __( 'RunPress Local DB', 'runpress' ), __( 'Local DB', 'runpress' ), 'manage_options', 'runpress-local-db', 'runpress_local_db' );
	add_submenu_page( 'runpress', __( 'RunPress Sync', 'runpress' ), __( 'Sync', 'runpress' ), 'manage_options', 'runpress-sync', 'runpress_sync' );
	add_submenu_page( 'runpress', __( 'RunPress Shortcode Generator', 'runpress' ), __( 'Shortcode Generator', 'runpress' ), 'manage_options', 'runpress-shortcode-generator', 'runpress_shortcode_generator' );
	add_submenu_page( 'runpress', __( 'RunPress Donation', 'runpress' ), __( 'Donate!', 'runpress' ), 'manage_options', 'runpress-donate', 'runpress_donate' );
	add_action( 'load-' . $hook_suffix, 'runpress_load_function' );
	add_action( 'load-' . $hook_suffix, 'runpress_help_tab' );
}

/*
 * Function:   runpress_admin_notices
 * Attributes: none
 * 
 * Display a message in the admin menu if the important options of the plugin are not configured yet
 * 
 * @since 1.0.0
 */ 
function runpress_admin_notices() {
	echo "<div id='notice' class='update-nag'><p>" . __( 'RunPress is not configured yet. Please do it now.', 'runpress' ) . "</p></div>\n";
}

/*
 * Function:   runpress_load_function
 * Attributes: none
 * 
 * The load function to surpress the admin notice if we are on our options page
 * 
 * @since 1.0.0
 */ 
function runpress_load_function() {
	remove_action( 'admin_notices', 'runpress_admin_notices' );
}

/*
 * Function:   runpress_help_tab
 * Attributes: none
 * 
 * Register the help page for the settings page
 * 
 * @since 1.0.0
 * 
 */ 
function runpress_help_tab() {
	$screen = get_current_screen();
	$screen->add_help_tab( array( 
		'id' => '1',															
		'title' => __( 'Settings', 'runpress' ),													
		'content' => __( '<br />Add your Runtastic Username and Password here. The Plugin will store your password into the wordpress database. Please make sure that your database is secure!<br /><br />Only running, nordicwalking, cycling, mountainbiking, racecycling, hiking, treadmill and ergometer activities are displayable via RunPress. Maybe other activities will get available in future updates.<br /><br />Select the unit types to show. You can choose beween Metric (European) and Imperial (UK and US) unit types.<br /><br />If you select the last option, all options and the local database will be deleted in case of deactivation of the plugin.<br /><br />This does not change anything in your Runtastic database.', 'runpress' )
	) );
	$screen->add_help_tab( array( 
		'id' => '2',
		'title' => __( 'Info', 'runpress' ),
		'content' => __( '<br /><h2>RunPress - A Wordpress Plugin to display your Runtastic Activities.</h2>Author: Markus Frenzel<br />URL: http://www.markusfrenzel.de<br /><br />If you like RunPress you might donate to its future development. <a href="http://markusfrenzel.de/wordpress/?page_id=2336">Donate here</a>', 'runpress' ) . '<br /><br />&copy 2014 - ' . date("Y") . ' Markus Frenzel.'
	) );
}

/*
 * Function:   runpress_options
 * Attributes: none
 * 
 * The main settings page
 * 
 * @since 1.0.0
 */
function runpress_options() {
	$error_name = '';
	$error_pass = '';
	$error_unittype = '';
	$error_deleteoptions = '';
	/* Variables for the field and option names */
	$opt_name = 'runpress_option_username';
	$opt_pass = 'runpress_option_userpass';
	$opt_unittype = 'runpress_option_unittype';
	$opt_deleteoptions = 'runpress_option_delete_options';
	$opt_runtastic_username = 'runpress_runtastic_username';
	$opt_runtastic_uid = 'runpress_runtastic_uid';
	$hidden_field_name = 'runpress_hidden';
	$data_field_name = 'runpress_username';
	$data_field_pass = 'runpress_userpass';
	$data_field_unittype = 'runpress_unittype';
	$data_field_deleteoptions = 'runpress_delete_options';
	/* Read the existing option values from the database */
	$opt_val_name = get_option( $opt_name, '' );
	$opt_val_pass = get_option( $opt_pass, '' );
	$opt_val_unittype = get_option( $opt_unittype, 'Metric Units' );
	$opt_val_deleteoptions = get_option( $opt_deleteoptions, 0 );
	$opt_val_runtastic_username = get_option( $opt_runtastic_username, '' );
	$opt_val_runtastic_uid = get_option( $opt_runtastic_uid, '' );
	/* Check if the runtastic username is already in the db */
	if( get_option( $opt_runtastic_username ) != false ) {
		echo "<div id='notice' class='updated'><p>" . __( 'Your Runtastic Username: ', 'runpress' ) . get_option( $opt_runtastic_username) . " / UID: " . get_option( $opt_runtastic_uid ) . "</p></div>\n";
	}
	/* Lets see if the user has posted some information. If so, the hidden field will be set to 'Y' */
	if( isset( $_POST[ $hidden_field_name ] ) && $_POST[ $hidden_field_name ] == 'Y' ) {
		/* Validate the data */
		if( is_email( $_POST[ $data_field_name ] ) ) {
			/* Read the posted value and save it into the option */
			$opt_val_name = sanitize_email( $_POST[ $data_field_name ] );
			update_option( $opt_name, $opt_val_name );
		}
		else
		{
			/* Throw an error message */
			$error_name = __( 'This is not a correct email address!', 'runpress' );
		}
		
		if( isset( $_POST[ $data_field_pass ] ) && strlen( $_POST[ $data_field_pass ] ) <= 50 ) {
			$opt_val_pass = sanitize_text_field( $_POST[ $data_field_pass ] );
			update_option( $opt_pass, $opt_val_pass );
		}
		else
		{
			if( !isset( $_POST[ $data_field_pass ] ) ) {
				$error_pass = __( 'Password must be set!', 'runpress' );
			}
			if( strlen( $_POST[ $data_field_pass ] > 50 ) ) {
				$error_pass = __( 'Password must be shorter than 50 character!', 'runpress' );
			}
		}
			
					
		$save_values_unittype = array( "Metric Units", "Imperial Units" );
		if( in_array( $_POST[ $data_field_unittype ], $save_values_unittype, true ) ) {
			/* Read the posted value and save it into the option */
			$opt_val_unittype = $_POST[ $data_field_unittype ];
			update_option( $opt_unittype, $opt_val_unittype );
		}
		else
		{
			/* Save the default value */
			update_option( $opt_unittype, "Metric Units" );
			/* Throw a note to the user */
			$error_unittype = __( 'Value was set to the default value!', 'runpress' );
		}
		
		$save_values_deleteoptions = array( "0","1" );
		if( in_array( $_POST[ $data_field_deleteoptions ], $save_values_deleteoptions, true ) ) {
			$opt_val_deleteoptions = $_POST[ $data_field_deleteoptions ];
			update_option( $opt_deleteoptions, $opt_val_deleteoptions );
		}
		else
		{
			/* Save the default value */
			update_option( $opt_deleteoptions, 0 );
			/* Throw a note to the user */
			$error_deleteoptions = __( 'Value was set to the default value!', 'runpress' );
		}

		if( isset( $opt_val_name ) && isset( $opt_val_pass ) ) {
			/* Query the runtastic website to get the runtastic username and uid */
			$runtastic = new RunPress_Runtastic();
			$runtastic->setUsername( $opt_val_name );
			$runtastic->setPassword( $opt_val_pass );
			$runtastic->setTimeout( 20 );
			if( $runtastic->login() ) {
				update_option( $opt_runtastic_username, $runtastic->getUsername() );
				update_option( $opt_runtastic_uid, $runtastic->getUid() );
			}
			else
			{
				echo "<div id='notice' class='error' onclick='remove(this)'><p><strong>" . _e( 'An error occured. Please check your user credentials and try again!', 'runpress' ) . "</strong></p></div>";
				update_option( $opt_runtastic_username, NULL );
				update_option( $opt_runtastic_uid, NULL);
			}
		}
		/* Show an 'settings updated' mesage on the screen */
		echo "<div id='notice' class='updated' onclick='remove(this)'><p><strong>" . __( 'Settings saved.', 'runpress' ) . "</strong></p></div>";
	}
	/* Now show the settings editing screen */
	?>
	<div class="wrap">
	<h2><?php _e( 'RunPress Plugin Settings', 'runpress' ); ?></h2>
	<form name="form1" method="post" action="">
	<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
	<table border="0">
	<tr>
	<td><?php _e( 'Runtastic E-Mail Address:', 'runpress' ); ?></td>
	<td><input type="text" name="<?php echo $data_field_name; ?>" value="<?php echo $opt_val_name; ?>" size="20"></td>
	<td><font color="red"><?php echo $error_name; ?></font></td>
	</tr>
	<tr>
	<td><?php _e( 'Runtastic Password:', 'runpress' ); ?></td>
	<td><input type="password" name="<?php echo $data_field_pass; ?>" value="<?php echo $opt_val_pass; ?>" size="20"></td>
	<td><font color="red"><?php echo $error_pass; ?></font></td>
	</tr>
	<tr>
	<td colspan="2"><hr /></td></tr>
	<tr>
	<td><?php _e( 'Unit Type:', 'runpress' ); ?></td>
	<td><select name="<?php echo $data_field_unittype; ?>" size="1"><option value="Metric Units" <?php if( $opt_val_unittype=="Metric Units") { echo "selected"; } ?>><?php echo __( 'Metric Units', 'runpress' ); ?></option><option value="Imperial Units" <?php if( $opt_val_unittype=="Imperial Units") { echo "selected"; } ?>><?php echo __( 'Imperial Units', 'runpress' ); ?></option></select></td>
	<td><font color="red"><?php echo $error_unittype; ?></font></td>
	</tr>
	<tr>
	<td colspan="2"><hr /></td>
	</tr>
	<tr>
	<td><?php _e( 'Delete Options:', 'runpress' ); ?></td>
	<td><input type="hidden" name="<?php echo $data_field_deleteoptions; ?>" value="0"><input type="checkbox" name="<?php echo $data_field_deleteoptions; ?>" value="1" <?php if ( $opt_val_deleteoptions == 1 ) { echo 'checked="checked"'; } ?>><?php _e( 'Deletes all options on deactivation of the plugin.', 'runpress' ); ?></td>
	<td><font color="red"><?php echo $error_deleteoptions; ?></font></td>
	</tr>
	</table>
	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'runpress' ) ?>" />
	</p>
	</form>
	</div>
	<?php
}

/*
 * Function:   runpress_local_db
 * Attributes: none
 * 
 * Write the Runtastic DB into our local DB
 * 
 * @since 1.0.0
 */
function runpress_local_db() {
	global $wpdb;
	global $runpress_db_name;
	$language = get_locale();
	$opt_val_unittype = get_option( 'runpress_option_unittype', 'Metric Units' );
	/* new way of enqueuing scripts... use a function ;-) */
	runpress_enqueue_scripts();
	/* variables for the field and option names */
	$hidden_field_name2 = 'runpress_db_sync';
	$hidden_field_name3 = 'runpress_db_delete';
	/* See if the user has clicked the button to sync the local database with the runtastic database */
	if( isset( $_POST[ $hidden_field_name2 ] ) && $_POST[ $hidden_field_name2 ] == 'Y' ) {
		runpress_sync_database_manually();
	}
	/* See if the user wants to delete all entries in den local DB */
	if( isset( $_POST[ $hidden_field_name3 ] ) && $_POST[ $hidden_field_name3 ] == 'Y' ) {
		runpress_delete_database_manually();
	}
	/* Now display the local DB screen */
	echo "<h2>" . __( 'RunPress Local DB', 'runpress' ) . "</h2>";
	$entry_count = $wpdb->get_var( "SELECT COUNT(*) FROM $runpress_db_name" );
	echo "<h3>" . __( 'Entries in local database:', 'runpress' ) . " {$entry_count}</h3>";
	$query = $wpdb->get_results( "SELECT * FROM $runpress_db_name ORDER BY id desc", OBJECT );
	echo "<table id='backend_results' class='cell-border' cellspacing='0' width='100%'>
		  <thead>
		  <tr>
		  <th align='left'>ID</th>
		  <th align='left'>" . __( 'Type', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Date', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Start', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Duration', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Distance', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Pace', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Speed', 'runpress' ) . "</th>
		  </tr></thead>
		  <tfoot>
		  <tr>
		  <th align='left'>ID</th>
		  <th align='left'>" . __( 'Type', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Date', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Start', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Duration', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Distance', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Pace', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Speed', 'runpress' ) . "</th>
		  </tr>
		  </tfoot>
		  <tbody>";
	foreach( $query as $row) {
		$backendresult = "";
		( $opt_val_unittype == "Metric Units" ? $date = sprintf( "%02s", $row->date_day ) . "." . sprintf( "%02s", $row->date_month ) . "." . sprintf( "%04s", $row->date_year ) : $date = sprintf( "%04s", $row->date_year ) . "/" . sprintf( "%02s", $row->date_month ) . "/" . sprintf( "%02s", $row->date_day ) );
		( $opt_val_unittype == "Metric Units" ? $distance = round( $row->distance/1000, 2 ) : $distance = round( ( $row->distance/1000)/1.609344, 2 ) );
		( $opt_val_unittype == "Metric Units" ? $pace = date( 'i:s', $row->pace*60 ) : $pace = date( 'i:s', ( $row->pace*1.609344 )*60 ) );
		$time = sprintf( "%02s", $row->date_hour ) . ":" . sprintf( "%02s", $row->date_minutes ) . ":" . sprintf( "%02s", $row->date_seconds );
		$duration = date( 'H:i:s', ( $row->duration/1000 ) );
		( $opt_val_unittype == "Metric Units" ? $speed = round( $row->speed, 2 ) : $speed = round( $row->speed/1.609344, 2 ) );
		$backendresult .= "<tr>";
		$backendresult .= "<td>" . $row->id . "</td>";
		$backendresult .= "<td>" . __( $row->type, 'runpress' ) . "</td>";
		( $opt_val_unittype == "Metric Units" ? $backendresult .= "<td title='" . $date . " (" . __( 'Format: DD.MM.YYYY', 'runpress' ) . ")'>" . $date . "</td>" : $backendresult .= "<td title='" . $date . " (" . __( 'Format: YYYY/MM/DD', 'runpress' ) . ")'>" . $date . "</td>" );
		$backendresult .= "<td title='" . $time . "(" . __( 'Format: hh:mm:ss', 'runpress' ) . ")'>" . $time . "</td>";
		$backendresult .= "<td title='" . $duration . "(" . __( 'Format: hh:mm:ss', 'runpress' ) . ")'>" . $duration . "</td>";
		( $opt_val_unittype == "Metric Units" ? $backendresult .= "<td title='" . $distance . " km'>" . $distance . "</td>" : $backendresult .= "<td title='" . $distance . " mi.'>" . $distance . "</td>" );
		( $opt_val_unittype == "Metric Units" ? $backendresult .= "<td title='" . $pace . " min./km'>" . $pace . "</td>" : $backendresult .= "<td title='" . $pace . " min./mi.'>" . $pace . "</td>" );
		( $opt_val_unittype == "Metric Units" ? $backendresult .= "<td title='" . $speed . " km/h'>" . $speed . "</td>" : $backendresult .= "<td title='" . $speed . " mi./h'>" . $speed . "</td>" );
		$backendresult .= "</tr>";
		echo $backendresult;
	}
	?>
	</tbody>
	</table>
	<?php
	$dt_translation = runpress_get_dt_translation();
	?>
	<script type="text/javascript">
		jQuery(document).ready(function() {
			/* Init dataTable */
			jQuery('#backend_results').dataTable( {
				"ordering": false,
				"aLengthMenu": [[10,25,50,75,100,-1],[10,25,50,75,100,"All"]],
				"iDisplayLength": 10,
				<?php
				if( $dt_translation ) {
					echo "\"language\": { \"url\":  \"$dt_translation\" },";
				}
				?>
				"order": []
			} );
		} );
	</script>
	<div class="wrap">
	<form name="form2" method="post" action ="">
	<input type="hidden" name="<?php echo $hidden_field_name2; ?>" value="Y">
	<?php _e( 'Please click the following button once to synchronize your local wordpress database with the entries in Runtastic.', 'runpress' ); ?>
	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Read Entries from Runtastic', 'runpress' ) ?>" />
	</p>
	</form>
	</div>
	<div class="wrap">
	<form name="form3" method="post" action="">
	<input type="hidden" name="<?php echo $hidden_field_name3; ?>" value="Y">
	<?php _e( 'If you want to delete the entries in your local db, click the following button. Only the entries in your local db will be deleted. It does not affect the entries in the runtastic db!', 'runpress' ); ?>
	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Delete all entries in local DB', 'runpress' ) ?>" />
	</p>
	</form>
	</div>
	<?php
}

/*
 * Function:   runpress_sync_database_manually
 * Attributes: none
 * 
 * Manually sync the local DB with the runtastic DB
 * 
 * @since 1.0.0
 */
function runpress_sync_database_manually() {
	global $wpdb;
	global $runpress_db_name;
	/* query the runtastic website */
	$runtastic = new RunPress_Runtastic();
	$runtastic->setUsername( get_option( 'runpress_option_username' ) );
	$runtastic->setPassword( get_option( 'runpress_option_userpass' ) );
	$runtastic->setTimeout( 20 );
	if( $runtastic->login() ) {
		$activities = $runtastic->getActivities();
		foreach( $activities as $activity ) {
			switch( $activity->type) {
				case "running":
				case "hiking":
				case "racecycling":
				case "mountainbiking":
				case "cycling":
				case "nordicwalking":
				case "ergometer":
				case "treadmill":
				$wpdb->replace(
				$runpress_db_name,
				array(
				'id' => $activity->id,
				'type' => $activity->type,
				'type_id' => $activity->type_id,
				'duration' => $activity->duration,
				'distance' => $activity->distance,
				'pace' => $activity->pace,
				'speed' => $activity->speed,
				'kcal' => $activity->kcal,
				'heartrate_avg' => $activity->heartrate_avg,
				'heartrate_max' => $activity->heartrate_max,
				'elevation_gain' => $activity->elevation_gain,
				'elevation_loss' => $activity->elevation_loss,
				'surface' => $activity->surface,
				'weather' => $activity->weather,
				'feeling' => $activity->feeling,
				'weather_id' => $activity->weather_id,
				'feeling_id' => $activity->feeling_id,
				'surface_id' => $activity->surface_id,
				'notes' => $activity->notes,
				'page_url' => $activity->page_url,
				'create_route_url' => $activity->create_route_url,
				'create_route_url_class' => $activity->create_route_url_class,
				'map_url' => $activity->map_url,
				'date_year' => $activity->date->year,
				'date_month' => $activity->date->month,
				'date_day' => $activity->date->day,
				'date_hour' => $activity->date->hour,
				'date_minutes' => $activity->date->minutes,
				'date_seconds' => $activity->date->seconds
				),
				array(
				'%d',
				'%s',
				'%d',
				'%d',
				'%d',
				'%f',
				'%s',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d'
				)
				);
			}
		}
		/* on completion we show an 'db sync successful' message on the screen */
		?>
		<div id="notice" class="updated" onclick="remove(this)"><p><?php _e( 'DB sync successful.', 'runpress' ); ?> <?php _e( '| <strong>Dismiss</strong>', 'runpress' ) ; ?></p></div>
		<?php
	}
	else
	{
		/* show an errow message if the sync fail */
		?>
		<div id="notice" class="error" onclick="remove(this)"><p><?php _e( 'DB sync failed! Please check the error message (if any) or try again.', 'runpress' ); ?> <?php _e( '| <strong>Dismiss</strong>', 'runpress' ); ?></p></div>
		<?php
	}
}

/*
 * Function:   runpress_delete_database_manually() {
 * Attributes: none
 *  
 * Deletes the entries in the local DB
 * 
 * @since 1.0.0
 */
function runpress_delete_database_manually() {
	global $wpdb;
	global $runpress_db_name;
	$delete = $wpdb->query( "TRUNCATE TABLE $runpress_db_name" );
	if( $delete==TRUE ) {
		?>
		<div id="notice" class="updated" onclick="remove(this)"><p><?php _e( 'DB successfully truncated.', 'runpress' ); ?> <?php _e( '| <strong>Dismiss</strong>', 'runpress' );?></p></div>
		<?php
	}
	else
	{
		?>
		<div id="notice" class="error" onclick="remove(this)"><p><?php _e( 'DB was not successfully truncated. Please try again.', 'runpress' ); ?> <?php _e( '| <strong>Dismiss</strong>', 'runpress' ); ?></p></div>
		<?php
	}
}

/*
 * Function:   runpress_shortcode
 * Attributes: array of attributes which can be used to specify a year, the sortorder and the chart type
 * 			   defaults are:	year		= the actual year
 * 								sortorder 	= asc
 * 								display		= table
 * 
 * @since 1.0.0
 */
function runpress_shortcode( $atts ) {
	global $wpdb;
	global $runpress_db_name;
	
	/* read the attributes (if given) otherwise it will use its pregiven defaults */
	$a = shortcode_atts( array(
		'year' => date( "Y" ),
		'sortorder' => 'desc',
		'display' => 'table',
		'title' => '',
		'entry' => 'latest',
		'mapwidth' => '200',
		'mapheight' => '300',
		'showonly' => 'running'
		), $atts );
	
	if( $a[ 'showonly' ] ) {
		$a[ 'showonly' ] = str_replace( "'", "", $a[ 'showonly' ]);
		$a[ 'showonly' ] = str_replace( '"', '', $a[ 'showonly' ]);
		$parts_displayonly = explode( ",", $a[ 'showonly' ] );
		if( count( $parts_displayonly ) >= 1 ) {
			$displayonly = " AND type='" . $parts_displayonly[0] . "'";
		}
		if( count( $parts_displayonly ) > 1 ) {
			$first=true;
			foreach( $parts_displayonly as $build_displayonly ) {
				if( $first ) {
					$first = false;
					continue;
				}
				$displayonly .= " OR type='" . $build_displayonly . "'";
			}
		}
	}
	else {
		$displayonly = "";
	}
	
	if( $a[ 'display' ] == "single" ) {
		runpress_enqueue_scripts();
		if( $a[ 'entry' ] == "latest" ) {
			$query = $wpdb->get_row( "SELECT type, date_day, date_month, date_year, distance, duration, pace, feeling, map_url, speed, kcal, heartrate_avg, heartrate_max, elevation_gain, elevation_loss, surface, weather, feeling, notes, date_hour, date_minutes FROM $runpress_db_name WHERE date_year=" . $a[ 'year' ] . $displayonly . " ORDER BY id desc LIMIT 1" );
		}
		else
		{
			$query = $wpdb->get_row( "SELECT type, date_day, date_month, date_year, distance, duration, pace, feeling, map_url, speed, kcal, heartrate_avg, heartrate_max, elevation_gain, elevation_loss, surface, weather, feeling, notes, date_hour, date_minutes FROM $runpress_db_name WHERE id=" . $a[ 'entry' ] . $displayonly . " ORDER BY id desc LIMIT 1" );
		}
		
		if( $query ) {
			$opt_val_unittype = get_option( 'runpress_option_unittype', 'Metric Units' );
			$header = "";
			$body = "";
			$footer = "";
			( $opt_val_unittype == "Metric Units" ? $date = sprintf( "%02s", $query->date_day ) . "." . sprintf( "%02s", $query->date_month ) . "." . sprintf( "%04s", $query->date_year ) : $date = sprintf( "%04s", $query->date_year ) . "/" . sprintf( "%02s", $query->date_month ) . "/" . sprintf( "%02s", $query->date_day ) );
			( $opt_val_unittype == "Metric Units" ? $distance = round( $query->distance/1000, 2 ) . " km" : $distance = round( ( $query->distance/1000)/1.609344, 2 ) . " mi." );
			( $opt_val_unittype == "Metric Units" ? $pace = date( 'i:s', $query->pace*60 ) . " min./km" : $pace = date( 'i:s', ( $query->pace*1.609344 )*60 ) . " min/mi." );
			$duration = date( 'H:i:s', ( $query->duration/1000 ) ) . " (h:m:s)";
			( $opt_val_unittype == "Metric Units" ? $elevationgain = $query->elevation_gain . " m" : $elevationgain = round( ( $query->elevation_gain/1000 ) / 1.609344, 2 ) . " mi." );
			( $opt_val_unittype == "Metric Units" ? $elevationloss = $query->elevation_loss . " m" : $elevationloss = round( ( $query->elevation_loss/1000 ) / 1.609344, 2 ) . " mi." );
			$calories = $query->kcal;
			$heartrateavg = $query->heartrate_avg;
			$heartratemax = $query->heartrate_max;
			$weather = $query->weather;
			$surface = $query->surface;
			$feeling = $query->feeling;
			$starttime = sprintf( "%02s", $query->date_hour ) . ":" . sprintf( "%02s", $query->date_minutes );
			/* Define the title of the shortcode */
			$header .= "<p><h2>" . $a[ 'title' ] . "</h2>";
			$header .= "<div class='runpress_singletable'>";
			$header .= "<div class='runpress_singletablerow'>
						<div class='runpress_singletabledata'>" . __( 'Type', 'runpress' ) . "
						<br>
						" . __( $query->type, 'runpress' ). " 
						</div>
						<div class='runpress_singletabledata'>" . __( 'Distance', 'runpress' ) . "
						<br>
						" . $distance ."
						</div>
						<div class='runpress_singletabledata'>" . __( 'Date', 'runpress' ) . "
						<br>
						" . $date . ", " . $starttime . "
						</div>
						<div class='runpress_singletabledata'>" . __( 'Avg. Pace', 'runpress' ) . "
						<br>
						" . $pace . "
						</div>
						<div class='runpress_singletabledata'>" . __( 'Elevation', 'runpress' ) . "
						<br>
						<span class='alignleft'>+</span><span class='alignright'>" . $elevationgain . "</span><br>
						<span class='alignleft'>-</span><span class='alignright'>" . $elevationloss . "</span>
						</div>
						<div style='clear: both;'></div>					
						</div>
						</div>";
			$body .= "<div class='runpress_singletable'>
					  <div class='runpress_singletablerow'>
					  <div class='runpress_singletabledata'>";
			if( !$query->map_url ) {
				/* load the image with a translated string in it */
				$body .= "<img src='" . plugins_url() . "/runpress/inc/img/showjpg.php?image=nomapfound.jpg&text=" . __( 'No map found!', 'runpress' ) . "' />";
			}
			else
			{
				$body .= "<img src='http:" . str_replace( 'width=50&height=70', 'width=' . $a[ 'mapwidth' ] . '&height=' . $a[ 'mapheight' ], $query->map_url ) . "'>";
			}
			$body .= "</div></div></div>";
			$footer .= "<div class='runpress_singletable'>
						<div class='runpress_singletablerow'>
						<div class='runpress_singletabledata'>" . __( 'Calories', 'runpress' ) . "
						<br>
						" . $calories . " kcal
						</div>
						<div class='runpress_singletabledata'>" . __( 'Heartrate', 'runpress' ) .  "
						<br>
						<span class='alignleft'>" . __( 'Avg.', 'runpress' ) . "</span><span class='alignright'>" . $heartrateavg . "</span><br>
						<span class='alignleft'>" . __( 'Max.', 'runpress' ) . "</span><span class='alignright'>" . $heartratemax . "</span>
						</div>
						<div class='runpress_singletabledata'>" . __( 'Weather', 'runpress') . "
						<br>
						" . __( $weather, 'runpress' ) . "
						</div>
						<div class='runpress_singletabledata'>" . __( 'Surface', 'runpress' ) . "
						<br>
						" . __( $surface, 'runpress' ) . "
						</div>
						<div class='runpress_singletabledata'>" . __( 'Feeling', 'runpress') . "
						<br>
						" . __( $feeling, 'runpress' ) . "
						</div>
						</div>";
			$footer .= "</div></p>";
			$returncontent = "";
			$returncontent = $header . $body . $footer;
		}
		return $returncontent;
	}
	else
	{
		if( ( $a[ 'year' ] > 999 ) and $a[ 'year' ] < 10000 ) {
			$query = $wpdb->get_results( "SELECT * FROM $runpress_db_name WHERE date_year=" . $a[ 'year' ] . $displayonly . " ORDER BY id " . $a[ 'sortorder' ], OBJECT );
		}
		else
		{
			if( $displayonly ) {
				$query = $wpdb->get_results( "SELECT * FROM $runpress_db_name WHERE " . str_replace( " AND ", "", $displayonly ) . " ORDER BY id " . $a[ 'sortorder' ], OBJECT );
			} else {
				$query = $wpdb->get_results( "SELECT * FROM $runpress_db_name ORDER BY id " . $a[ 'sortorder' ], OBJECT );
			}
		}

	if( $query ) {
		/* The core table which is used to display the data native and through JQuery Datatables */
		if( $a[ 'display' ] == "table" || $a[ 'display' ] == "datatable" ) {
			$header = "";
			$body = "";
			$footer = "";
			/* Define the title of the shortcode */
			$header .= "<p><h2>" . $a[ 'title' ] . "</h2>";
			/* Define the header of the table */
			$header .= "<table id='{$a['display']}_results_{$a['year']}' class='cell-border' cellspacing='0' width='100%'>";
			$header .= "<thead>";
			$header .= "<tr>";
			$header .= "<th align='left'>" . __( 'Type', 'runpress' ) . "</th>";
			$header .= "<th align='left'>" . __( 'Date', 'runpress' ) . "</th>";
			$header .= "<th align='left'>" . __( 'Start', 'runpress' ) . "</th>";
			$header .= "<th align='left'>" . __( 'Duration', 'runpress' ) . "</th>";
			$header .= "<th align='left'>" . __( 'Distance', 'runpress' ) . "</th>";
			$header .= "<th align='left'>" . __( 'Pace', 'runpress' ) . "</th>";
			$header .= "<th align='left'>" . __( 'Speed', 'runpress' ) . "</th>";
			$header .= "</tr>";
			$header .= "</thead>";
			/* Define the footer of the table */
			$footer .= "<tfoot>";
			$footer .= "<tr>";
			$footer .= "<th align='left'>" . __( 'Type', 'runpress' ) . "</th>";
			$footer .= "<th align='left'>" . __( 'Date', 'runpress' ) . "</th>";
			$footer .= "<th align='left'>" . __( 'Start', 'runpress' ) . "</th>";
			$footer .= "<th align='left'>" . __( 'Duration', 'runpress' ) . "</th>";
			$footer .= "<th align='left'>" . __( 'Distance', 'runpress' ) . "</th>";
			$footer .= "<th align='left'>" . __( 'Pace', 'runpress' ) . "</th>";
			$footer .= "<th align='left'>" . __( 'Speed', 'runpress' ) . "</th>";
			$footer .= "</tr>";
			$footer .= "</tfoot>";
			/* Define the body of the table */
			$body .= "<tbody>";
			$opt_val_unittype = get_option( 'runpress_option_unittype', 'Metric Units' );
				foreach( $query as $row ) {
					( $opt_val_unittype == "Metric Units" ? $date = sprintf( "%02s", $row->date_day ) . "." . sprintf( "%02s", $row->date_month ) . "." . sprintf( "%04s", $row->date_year ) : $date = sprintf( "%04s", $row->date_year ) . "/" . sprintf( "%02s", $row->date_month ) . "/" . sprintf( "%02s", $row->date_day ) );
					( $opt_val_unittype == "Metric Units" ? $distance = round( $row->distance/1000, 2 ) : $distance = round( ( $row->distance/1000)/1.609344, 2 ) );
					( $opt_val_unittype == "Metric Units" ? $pace = date( 'i:s', $row->pace*60 ) : $pace = date( 'i:s', ( $row->pace*1.609344 )*60 ) );
					( $opt_val_unittype == "Metric Units" ? $duration = date( 'H:i:s', ( $row->duration/1000 ) ) : $duration = date( 'H:i:s', ( $row->duration/1000 ) ) );
				
				$time = sprintf( "%02s", $row->date_hour ) . ":" . sprintf( "%02s", $row->date_minutes ) . ":" . sprintf( "%02s", $row->date_seconds );
				( $opt_val_unittype == "Metric Units" ? $speed = round( $row->speed, 2 ) : $speed = round( $row->speed/1.609344, 2 ) );
				$body .= "<tr>";
				$body .= "<td title='" . __( $row->type, 'runpress' ) . "'>" . __( $row->type, 'runpress' ) . "</td>";
				( $opt_val_unittype == "Metric Units" ? $body .= "<td title='" . $date . " (" . __( 'Format: DD.MM.YYYY', 'runpress' ) . ")'>" . $date . "</td>" : $body .= "<td title='" . $date . " (" . __( 'Format: YYYY/MM/DD', 'runpress' ) . ")'>" . $date . "</td>" );
				$body .= "<td title='" . $time . " (" . __( 'Format: hh:mm:ss', 'runpress' ) . ")'>" . $time . "</td>";
				$body .= "<td title='" . $duration . " (" . __( 'Format: hh:mm:ss', 'runpress' ) . ")'>" . $duration . "</td>";
				( $opt_val_unittype == "Metric Units" ? $body .= "<td title='" . $distance . " km'>" . $distance . "</td>" : $body .= "<td title='" . $distance . " mi.'>" . $distance . "</td>" );
				( $opt_val_unittype == "Metric Units" ? $body .= "<td title='" . $pace . " min./km'>" . $pace . "</td>" : $body .= "<td title='" . $pace . " min./mi.'>" . $pace . "</td>" );
				( $opt_val_unittype == "Metric Units" ? $body .= "<td title='" . $speed . " km/h'>" . $speed . "</td>" : $body .= "<td title='" . $speed . " mi./h'>" . $speed . "</td>" );
				$body .= "</tr>";
			}
			$body .= "</tbody>";
			$footer .= "</table></p>";
			$returncontent = $header . $body . $footer;
		}
		/* Display the data with the use of JQuery Datatables */
		if( $a[ 'display' ] == "datatable" ) {
			/* new way of enqueuing scripts... use a function ;-) */
			runpress_enqueue_scripts();
		
			$dt_translation = runpress_get_dt_translation();
	
			?>
			<script type="text/javascript">
			jQuery(document).ready(function(){
				/* Init dataTable */
				<?php
				echo "jQuery('#datatable_results_{$a['year']}').dataTable( {";
				?>
					"ordering": false,
					<?php
					if( $dt_translation ) {
						echo "\"language\": { \"url\":  \"$dt_translation\" },";
					}
					?>
					"order": []
				} );
			} );
			</script>
			<?php
		}
		/* Display the data with Google Charts */
		if( $a[ 'display' ] == "chart" ) {
			$month = '';
			$sumkm_jan = 0;
			$sumkm_feb = 0;
			$sumkm_mar = 0;
			$sumkm_apr = 0;
			$sumkm_may = 0;
			$sumkm_jun = 0;
			$sumkm_jul = 0;
			$sumkm_aug = 0;
			$sumkm_sep = 0;
			$sumkm_oct = 0;
			$sumkm_nov = 0;
			$sumkm_dec = 0;
			$distance = 0;
			foreach( $query as $row ) {
				$month = $row->date_month;
				$distance = round( $row->distance/1000, 2 );
				switch( $month ) {
					case '01':
						$sumkm_jan += $distance;
						break;
					case '02':
						$sumkm_feb += $distance;
						break;
					case '03':
						$sumkm_mar += $distance;
						break;
					case '04':
						$sumkm_apr += $distance;
						break;
					case '05':
						$sumkm_may += $distance;
						break;
					case '06':
						$sumkm_jun += $distance;
						break;
					case '07':
						$sumkm_jul += $distance;
						break;
					case '08':
						$sumkm_aug += $distance;
						break;
					case '09':
						$sumkm_sep += $distance;
						break;
					case '10':
						$sumkm_oct += $distance;
						break;
					case '11':
						$sumkm_nov += $distance;
						break;
					case '12':
						$sumkm_dec += $distance;
						break;
				}
			}			

			?>
			
			<script type="text/javascript">
				google.load("visualization", "1", {packages:["corechart"]});
				google.setOnLoadCallback(drawChart);
				function drawChart() {
					var data = google.visualization.arrayToDataTable([
						['<?php _e( 'Month', 'runpress' ) ?>', '<?php _e( 'Distance', 'runpress' ) ?>'],
						[0, 0],
						['01', <?php echo ($sumkm_jan == 0) ? 0 : $sumkm_jan; ?>],
						['02', <?php echo ($sumkm_feb == 0) ? 0 : $sumkm_feb; ?>],
						['03', <?php echo ($sumkm_mar == 0) ? 0 : $sumkm_mar; ?>],
						['04', <?php echo ($sumkm_apr == 0) ? 0 : $sumkm_apr; ?>],
						['05', <?php echo ($sumkm_may == 0) ? 0 : $sumkm_may; ?>],
						['06', <?php echo ($sumkm_jun == 0) ? 0 : $sumkm_jun; ?>],
						['07', <?php echo ($sumkm_jul == 0) ? 0 : $sumkm_jul; ?>],
						['08', <?php echo ($sumkm_aug == 0) ? 0 : $sumkm_aug; ?>],
						['09', <?php echo ($sumkm_sep == 0) ? 0 : $sumkm_sep; ?>],
						['10', <?php echo ($sumkm_oct == 0) ? 0 : $sumkm_oct; ?>],
						['11', <?php echo ($sumkm_nov == 0) ? 0 : $sumkm_nov; ?>],
						['12', <?php echo ($sumkm_dec == 0) ? 0 : $sumkm_dec; ?>],
					]);
					
					var options = {
						title: '<?php _e( 'Results', 'runpress' ) . " {$a [ 'year'] }"; ?>',
						titlePosition: 'out',
						legend: { Position: 'bottom' },
						width: '100%',
						height: 500,
						curveType: 'function',
						chartArea: { left:50, top:20 },
						hAxis: { title: '<?php _e( 'Month', 'runpress' ) ?>', ticks: [1,2,3,4,5,6,7,8,9,10,11,12] },
						vAxis: { title: '<?php _e( 'Distance', 'runpress' ) ?>', minValue: '0', maxValue: '100' },
					};
					
					var chart = new google.visualization.LineChart(document.getElementById('chart_div<?php echo "_{$a[ 'year' ] }" ?>') );
					chart.draw(data, options);
				}
			</script>
			<?php
			$returncontent = "";
			$returncontent .= "<p><h2>" . $a[ 'title' ] . "</h2>";
			$returncontent .= "<div id=\"chart_div_{$a[ 'year' ] }\"></div></p>";
		}
		return $returncontent;
	}
}
	return __( 'Sorry, no data found!', 'runpress' );
}
	
/*
 * Function:   runpress_enqueue_scripts
 * Attributes: none
 *  
 * Enqueues needed scripts
 * 
 * @since 1.0.0
 */
function runpress_enqueue_scripts() {
	wp_register_script( 'jquery_datatables_js', plugins_url() . '/runpress/inc/js/jquery.dataTables.js', array('jquery'), false, false );
	wp_enqueue_script( 'jquery_datatables_js' );
	wp_register_style( 'jquery_datatables_css', plugins_url() . '/runpress/inc/css/jquery.dataTables.css' );
	wp_enqueue_style( 'jquery_datatables_css' );
	wp_register_style( 'runpress_css', plugins_url() . '/runpress/inc/css/runpress.css' );
	wp_enqueue_style( 'runpress_css' );
}

/*
 * Function:   runpress_enqueue_google_api
 * Attributes: none
 *  
 * Enqueues needed google api
 * 
 * @since 1.0.0
 */
function runpress_enqueue_google_api() {
	wp_enqueue_script( 'google-jsapi', 'https://www.google.com/jsapi' );
}

/*
 * Function:   runpress_sync
 * Attributes: none
 *  
 * The function to configure the sync of the local db. Whether it is used manually or via cron job.
 * 
 * @since 1.0.0
 */
function runpress_sync() {
	global $wpdb;
	global $runpress_db_name;
	/* variables for the field and option names */
	$hidden_field_name2 = 'runpress_db_sync';
	$hidden_field_name3 = 'runpress_db_delete';
	$hidden_field_name4 = 'runpress_cronjob_add';
	$hidden_field_name5 = 'runpress_cronjob_delete';
	$data_field_cronjobtime = 'runpress_option_cronjobtime';
	$opt_val_cronjobtime = get_option( $data_field_cronjobtime, 'daily' );
	/* see if the user has clicked the button to sync the local database with the runtastic database */
	if( isset( $_POST[ $hidden_field_name2 ] ) && $_POST[ $hidden_field_name2 ] == 'Y' ) {
		runpress_sync_database_manually();
	}
	/* see if the user wants to delete all entries in the local db */
	if( isset( $_POST[ $hidden_field_name3 ] ) && $_POST[ $hidden_field_name3 ] == 'Y' ) {
		runpress_delete_database_manually();
	}
	/* see if the user want to save a cron job */
	if( isset( $_POST[ $hidden_field_name4 ] ) && $_POST[ $hidden_field_name4 ] == 'Y' ) {
		$opt_val_cronjobtime = $_POST[ $data_field_cronjobtime ];
		update_option( $data_field_cronjobtime, $opt_val_cronjobtime );
		if( !wp_next_scheduled( 'runpress_event_hook' ) ) {
			wp_schedule_event( time(), $opt_val_cronjobtime, 'runpress_event_hook' );
		}
		else
		{
			wp_clear_scheduled_hook( 'runpress_event_hook' );
			wp_schedule_event( time(), $opt_val_cronjobtime, 'runpress_event_hook' );
		}
		?>
		<div id="notice" class="updated" onclick="remove(this)"><p><?php _e( 'Cronjob scheduled.', 'runpress' ); ?> <?php _e( '| <strong>Dismiss</strong>', 'runpress' ) ; ?></p></div>
		<?php
	}
	/* see if the user wants to delete the cron job */
	if( isset( $_POST[ $hidden_field_name5 ] ) && $_POST[ $hidden_field_name5 ] == 'Y' ) {
		wp_clear_scheduled_hook( 'runpress_event_hook' );
		delete_option( 'runpress_option_cronjobtime' );
		$opt_val_cronjobtime = '';
		?>
		<div id="notice" class="updated" onclick="remove(this)"><p><?php _e( 'Cronjob deleted.', 'runpress' ); ?> <?php _e( '| <strong>Dismiss</strong>', 'runpress' ) ; ?></p></div>
		<?php
	}
	/* now display the local db entry count */
	echo "<h2>" . __( 'RunPress Sync Settings', 'runpress' ) . "</h2>";
	$entry_count = $wpdb->get_var( "SELECT COUNT(*) FROM $runpress_db_name" );
	echo "<h3>" . __( 'Entries in local database: ', 'runpress' ) . "{$entry_count}</h3>";
	?>
	<div class="wrap">
	<h3><?php _e( 'Manual sync of the local DB', 'runpress' ) ?></h3>
	<form name="form2" method="post" action="">
	<input type="hidden" name="<?php echo $hidden_field_name2; ?>" value="Y">
	<?php _e( 'Please click the following button once to synchronize your local wordpress database with the entries in Runtastic.', 'runpress' ); ?>
	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Read Entries from Runtastic', 'runpress' ) ?>" />
	</p>
	</form>
	</div>
	<div class="wrap">
	<h3><?php _e( 'Delete all entries from the local DB', 'runpress' ) ?></h3>
	<form name="form3" method="post" action="">
	<input type="hidden" name="<?php echo $hidden_field_name3; ?>" value="Y">
	<?php _e( 'If you want to delete the entries in your local db, click the following button. Only the entries in your local db will be deleted. It does not affect the entries in the runtastic db!', 'runpress' ); ?>
	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Delete all entries in local db', 'runpress' ) ?>" />
	</p>
	</form>
	</div>
	<div class="wrap">
	<h3><?php _e( 'Schedule a Wordpress Cron Job', 'runpress' ) ?></h3>
	<form name="form4" method="post" action="">
	<input type="hidden" name="<?php echo $hidden_field_name4; ?>" value="Y">
	<?php
	if( wp_next_scheduled( 'runpress_event_hook' ) ) {
		_e( 'Your have scheduled a WP Cron job to run at the following basis ', 'runpress' );
	}
	else
	{
		_e( 'Define a WP Cron job to start the sync of your local db automatically.', 'runpress' );
	}
	?>
	<table>
	<tr>
	<td><?php _e( 'Interval:', 'runpress' ); ?></td>
	<td><select name="<?php echo $data_field_cronjobtime; ?>" size="1">
	<option value="hourly" <?php if( $opt_val_cronjobtime=="hourly" ) { echo "selected"; } ?>><?php _e( 'Hourly', 'runpress' ); ?></option>
	<option value="fourtimesdaily" <?php if( $opt_val_cronjobtime=="fourtimesdaily" ) { echo "selected"; } ?>><?php _e( 'every 6 hours', 'runpress' ); ?></option>
	<option value="twicedaily" <?php if( $opt_val_cronjobtime=="twicedaily" ) { echo "selected"; } ?>><?php _e( 'every 12 hours', 'runpress' ); ?></option>
	<option value="daily" <?php if( $opt_val_cronjobtime=="daily" ) { echo "selected"; } ?>><?php _e( 'once a day', 'runpress' ); ?></option>
	<option value="weekly" <?php if( $opt_val_cronjobtime=="weekly" ) { echo "selected"; } ?>><?php _e( 'once a week', 'runpress' ); ?></option>
	</select></td>
	</tr>
	</table>
	<?php
	if( wp_next_scheduled( 'runpress_event_hook' ) ) {
		?>
		<p class="submit">
			<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Change scheduled Cron job', 'runpress' ); ?>" />
		</p>
		<?php
	}
	else
	{
		?>
		<p class="submit">
			<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Schedule Cron job', 'runpress' ); ?>" />
		</p>
	<?php
	}
	?>
	</form>
	</div>
	<?php
	if( wp_next_scheduled( 'runpress_event_hook' ) ) {
		?>
		<div class="wrap">
		<h3><?php _e( 'Delete the scheduled Wordpress Cron job', 'runpress') ?></h3>
		<form name="form5" method="post" action="">
		<input type="hidden" name="<?php echo $hidden_field_name5; ?>" value="Y">
		<?php _e( 'Click here to delete the scheduled Wordpress Cron job for RunPress.', 'runpress' ); ?>
		<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Delete Cron Job', 'runpress' ) ?>" />
		</p>
		</form>
		</div>
		<?php
	}
}

/*
 * Function:   runpress_donate
 * Attributes: none
 * 
 * Software development and motivation can be easily pushed: by a donation
 * 
 * @since 1.3.0
 */
function runpress_donate() {
	echo "<h2>" . __( 'RunPress Donation', 'runpress' ) . "</h2>";
	echo "<h3>" . __( 'Motivate the developer of this plugin', 'runpress' ). "</h3>";
	echo __( 'Please consider a small (or even a big) donation to the developer of the RunPress Plugin, Markus Frenzel.<br /><br /><b>I am not affiliated with nor am I working at Runtastic.</b> RunPress is a private project of me and is not supported by Runtastic or its affiliates.<br /><br />I appreciate every donation to keep my motivation level and the further development of RunPress up and running.<br /><br />', 'runpress' );
	echo __( 'German speaking RunPress user may use this icon to donate via paypal:<br /><br />', 'runpress' );
	?>
	<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top"><input name="cmd" type="hidden" value="_s-xclick" />
    <input name="hosted_button_id" type="hidden" value="MWKHFATNUJB5S" />
    <input alt="Jetzt einfach, schnell und sicher online bezahlen – mit PayPal." name="submit" src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donateCC_LG.gif" type="image" />
    <img src="https://www.paypalobjects.com/de_DE/i/scr/pixel.gif" alt="" width="1" height="1" border="0" /></form><br /><br />
	<?php
	echo __( 'English speaking RunPress User may use this icon to donate via paypal:<br /><br />', 'runpress' );
	?>
	<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top"><input name="cmd" type="hidden" value="_s-xclick" />
    <input name="hosted_button_id" type="hidden" value="H3Z6D9H6TTF8L" />
    <input alt="PayPal - The safer, easier way to pay online!" name="submit" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" type="image" />
    <img src="https://www.paypalobjects.com/de_DE/i/scr/pixel.gif" alt="" width="1" height="1" border="0" /></form>&nbsp;
	<?php
}

/*
 * Function:   runpress_shortcode_generator
 * Attributes: none
 *  
 * The shortcode might not be easy to understand. So I offer some kind of generator for that.
 * 
 * @since 1.0.0
 */
function runpress_shortcode_generator() {
	global $wpdb;
	global $runpress_db_name;
	
	echo "<h2>" . __( 'RunPress Shortcode Generator', 'runpress' ) . "</h2>";
	echo "<h3>" . __( 'General Shortcode usage', 'runpress' ) . "</h3>";
	/* the shortcode should be as easy as an order at starbucks */
	echo __( 'You can choose between 4 possibilities to display your runtastic activities: <b>table</b>, <b>datatable</b>, <b>chart</b> and <b>single</b>.<br /><br />You might limit the data to display by declaring a specific <b>year</b>. <i>If you do not declare a year the actual year will be used!</i><br /><br />The data <b>sortorder</b> can be changed by declaring the specific variable.<br /><br />Use the <b>title</b> variable to label your data with a heading.<br /><h4>Examples:</h4>[runpress year="2014" display="table" sortorder="desc"]<br /><i>This shortcode will show your data from 2014, sorted descending by the runtastic id within a normal table</i><br /><br />[runpress display="datatable"]<br /><i>This shortcode will show your data from the actual year, sorted descending by the runtastic id within a special table called "DataTable".</i><br /><br />[runpress year="2015" display="chart" sortorder="desc"]<br /><i>This shortcode will show your data from 2015, ignoring the sortorder because it will only show the monthly sums of your running activities within a chart powered by Google Charts.</i><br /><br />[runpress display="single" entry="latest" mapwidth="500" mapheight="300"]<br /><i>This shortcode will show the single event specified by the "entry" variable with a lot of details including map!</i><br /><br /><h3>How to use this shortcode?</h3>Just copy the example shortcode (including the square brackets at the beginning and the end) or use the Generator to build a new one and paste it into the page where the data should be displayed. It runs also in posts... not only in pages!<br /><br />If you want to use the data in a widget area: please use the RunPress Widget which has been installed with the activation of this plugin.', 'runpress' );
	
	/* show the generator */
	echo "<h3>" . __( 'Runpress Shortcode Generator', 'runpress' ). "</h3>";
	/* check the possible years to display */
	$available_years = $wpdb->get_results( "SELECT DISTINCT( date_year ) FROM $runpress_db_name ORDER BY date_year DESC;" );
	?>
	
	<script type="text/javascript">
		
		jQuery(document).ready(function($){
			
			$('#tr_entry').hide();
			$('#tr_mapdimensions').hide();
			$('#tr_year').show();
			$('#tr_sortorder').show();
			$('#tr_availabletypes').show();
			$('#tr_availableonlyonetype').hide();
			$('#display').change(function(){
				if($('#display').val() == ' display=single') {
					$('#tr_entry').show();
					$('#tr_mapdimensions').show();
					$('#tr_year').hide();
					$('#tr_sortorder').hide();
					$('#tr_availabletypes').hide();
					$('#tr_availableonlyonetype').show();
					$('#tr_availabletypes input').removeAttr('checked');
					$('#tr_availableonlyonetype input').removeAttr('checked');
				}
				else
				{
					$('#tr_entry').hide();
					$('#tr_mapdimensions').hide();
					$('#tr_year').show();
					$('#tr_sortorder').show();
					$('#tr_availabletypes').show();
					$('#tr_availableonlyonetype').hide();
					$('#tr_availabletypes input').removeAttr('checked');
					$('#tr_availableonlyonetype input').removeAttr('checked');
				}
			});
		});
				
		function transferFields() {
			jQuery(document).ready(function($) {
				var yourArray = new Array();
				
				$('input[name="showtype"]:checked').each(function() {
					yourArray.push(this.value);
			});

			if( yourArray < 1) { 
				yourArray = 'running'; 
			}

			if( !document.getElementById( "title").value ) {
				if ( document.getElementById( "display" ).value==" display=single" ) {
					document.getElementById( "entry" ).value=' entry=' + document.getElementById( "entry" ).value;
					generatedshortcode = '[runpress ' + document.getElementById( "display" ).value + document.getElementById( "entry" ).value + ' mapwidth=' + document.getElementById( "mapwidth" ).value + ' mapheight=' + document.getElementById( "mapheight" ).value + ' showonly=' + yourArray + ']';
				}
				else
				{
					generatedshortcode = '[runpress ' + document.getElementById( "year" ).value + document.getElementById( "display" ).value + document.getElementById( "sortorder" ).value + ' showonly=' + yourArray + ']';
				}
			}
			else
			{
				if ( document.getElementById( "display" ).value==" display=single" ) {
					document.getElementById( "entry" ).value=' entry=' + document.getElementById( "entry" ).value;
					generatedshortcode = '[runpress ' + document.getElementById( "display" ).value + document.getElementById( "entry" ).value + ' mapwidth=' + document.getElementById( "mapwidth" ).value + ' mapheight=' + document.getElementById( "mapheight" ).value + ' title="' + document.getElementById( "title" ).value + '" showonly=' + yourArray + ']';
				}
				else
				{
					generatedshortcode = '[runpress ' + document.getElementById( "year" ).value + document.getElementById( "display" ).value + document.getElementById( "sortorder" ).value + ' title="' + document.getElementById( "title" ).value + '" showonly=' + yourArray + ']';
				}
			}
			document.runpressgenerator.shortcode.value = generatedshortcode.replace( "  "," " );
			document.getElementById( "entry" ).value = document.getElementById( "entry" ).value.replace( " entry=", "" );	
			});
		}
		
		function resetFields() {
			document.runpressgenerator.shortcode.value = "";
			document.getElementById( "display" ).value = document.getElementById( "display" );
		}
	</script>
	<form name="runpressgenerator">
	<input type="text" id="shortcode" value="" size=80>
	<!-- <input type="reset" value="<?php _e( 'Reset', 'runpress' ); ?>"> -->
	<input type="button" class="button-primary" onclick="resetFields()" value="<?php _e( 'Reset', 'runpress' ); ?>">
    <br />
    <br />
    <table>
	<tr>
		<td><?php _e( 'Display:', 'runpress' ) . ' '; ?></td>
		<td><select id="display" name="display" size="1">
			<option value=" display=table"><?php _e( 'Table', 'runpress' ); ?></option>
			<option value=" display=datatable">DataTable</option>
			<option value=" display=chart"><?php _e( 'Chart', 'runpress' ); ?></option>
			<option value=" display=single"><?php _e( 'Single', 'runpress' ); ?></option>
			<option value=""><?php _e( 'empty', 'runpress' ); ?></option>
			</select>
		</td>
		<td>
			<?php _e( '<i>If "empty" the default value (table) will be used.</i>', 'runpress' ); ?>
		</td>
	</tr>
		<tr id="tr_year">
			<td><?php _e( 'Year:', 'runpress' ) . ' '; ?></td>
			<td><select id="year" name="year" size="1">
				<?php
				foreach( $available_years as $years ) {
					echo "<option value=\"year=$years->date_year\">$years->date_year</option>";
				}
				?>
				<option value=""><?php _e( 'empty', 'runpress' ); ?></option>
			</select>
			</td>
			<td>
				<?php _e( '<i>If "empty" the default value (the actual year) will be used.</i>', 'runpress' ); ?>
			</td>
		</tr>
	<tr id="tr_entry">
		<td><?php _e( 'Entry:', 'runpress' ) . ' '; ?></td>
		<td><input type="text" id="entry" value="latest" size=30></td>
		<td><?php _e( '<i>Just copy and paste the ID value from your local RunPress Database or use the word "latest" for your latest run.</i>', 'runpress' ); ?></td>
	</tr>
	<tr id="tr_mapdimensions">
		<td><?php _e( 'Mapwidth / Mapheight:', 'runpress' ) . ' '; ?></td>
		<td><input type="number" id="mapheight" min=1 max=1000 step=1 value=500> / <input type="number" id="mapwidth" min=1 max=1000 step=1 value=350></td>
		<td><?php _e( '<i>Specifies the width and the height of the map which is shown in your post or page.</i>', 'runpress' ); ?></td>
	</tr>
	<tr id="tr_sortorder">
		<td><?php _e( 'Sortorder:', 'runpress' ) . ' '; ?></td>
		<td><select id="sortorder" name="sortorder" size="1">
			<option value=" sortorder=desc"><?php _e( 'Descending', 'runpress' ); ?></option>
			<option value=" sortorder=asc"><?php _e( 'Ascending', 'runpress' ); ?></option>
			<option value=""><?php _e( 'empty', 'runpress' ); ?></option>
			</select>
		</td>
		<td>
			<?php _e( '<i>If "empty" the default value (descending) will be used.</i>', 'runpress' ); ?>
		</td>
	</tr>
	<tr>
		<td><?php _e( ' Title:', 'runpress' ) . ' '; ?></td>
		<td><input type="text" id="title" value="RunPress" size=30></td>
		<td><?php _e( '<i>Leave the text field blank to show no title.</i>', 'runpress' ); ?></td>
	</tr>
	<tr id="tr_availabletypes">
		<td halign="left" valign="top"><?php _e( 'Type', 'runpress' ) . ': '; ?></td>
		<td>
			<input type="checkbox" name="showtype" value="running" id="type_running"> <?php _e( 'running', 'runpress' ); ?><br />
			<input type="checkbox" name="showtype" value="nordicwalking" id="type_nordicwalking"> <?php _e( 'nordicwalking', 'runpress' ); ?><br />
			<input type="checkbox" name="showtype" value="cycling" id="type_cycling"> <?php _e( 'cycling', 'runpress' ); ?><br />
			<input type="checkbox" name="showtype" value="mountainbiking" id="type_mountainbiking"> <?php _e( 'mountainbiking', 'runpress' ); ?><br />
			<input type="checkbox" name="showtype" value="racecycling" id="type_racecycling"> <?php _e( 'racecycling', 'runpress' ); ?><br />
			<input type="checkbox" name="showtype" value="hiking" id="type_hiking"> <?php _e( 'hiking', 'runpress' ); ?><br />
			<input type="checkbox" name="showtype" value="treadmill" id="type_treadmill"> <?php _e( 'treadmill', 'runpress' ); ?><br />
			<input type="checkbox" name="showtype" value="ergometer" id="type_ergometer"> <?php _e( 'ergometer', 'runpress' ); ?><br />
		</td>
		<td halign="left" valign="top"><?php _e( '<i>Leave the type field blank to show all activity types.</i>', 'runpress' ); ?></td>
	</tr>
	<tr id="tr_availableonlyonetype">
		<td halign="left" valign="top"><?php _e( 'Type', 'runpress' ). ': '; ?></td>
		<td>
			<input type="radio" id="type_running" name="showtype" value="running"><label for="type_running"> <?php _e( 'running', 'runpress' ); ?></label><br />
			<input type="radio" id="type_nordicwalking" name="showtype" value="nordicwalking"> <label for="type_nordicwalking"><?php _e( 'nordicwalking', 'runpress' ); ?></label><br />
			<input type="radio" id="type_cycling" name="showtype" value="cycling"> <label for="type_cycling"><?php _e( 'cycling', 'runpress' ); ?></label><br />
			<input type="radio" id="type_mountainbiking" name="showtype" value="mountainbiking"> <label for="type_mountainbiking"><?php _e( 'mountainbiking', 'runpress' ); ?></label><br />
			<input type="radio" id="type_racecycling" name="showtype" value="racecycling"> <label for="type_racecycling"><?php _e( 'racecycling', 'runpress' ); ?></label><br />
			<input type="radio" id="type_hiking" name="showtype" value="hiking"> <label for="type_hiking"><?php _e( 'hiking', 'runpress' ); ?></label><br />
			<input type="radio" id="type_treadmill" name="showtype" value="treadmill"> <label for="type_treadmill"><?php _e( 'treadmill', 'runpress' ); ?></label><br />
			<input type="radio" id="type_ergometer" name="showtype" value="ergometer"> <label for="type_ergometer"><?php _e( 'ergometer', 'runpress' ); ?></label><br />
		</td>
	</tr>
	<p id="tr_array">
	</p>
    </table>
	</form>
	<br />
	<br />
	<input type="button" class="button-primary" onclick="transferFields()" value="<?php _e( 'Generate Shortcode', 'runpress' ); ?>">
	<br />
	<?php
	_e( '<i>After clicking this button the shortcode will be generated and displayed above. Just click into the field which holds the shortcode an use the keyboard shortcut CTRL + C to copy it to your clipboard. Then edit or create a post or a page which should contain the shortcode, click into the editor and paste the copied shortcode by using the keyboard shortcut CTRL + V.</i>', 'runpress' );
}

/*
 * Function:   runpress_add_cronjob_definitions
 * Attributes: none
 *  
 * Adds cronjob definitions for time schedules which aren't available by default in wordpress
 * 
 * @since 1.0.0
 */
function runpress_add_cronjob_definitions( $schedules ) {
	/* Adds my own definitions to the schedules event. Valid values by default are: "hourly", "twicedaily" and "daily".
	 * I add "fourtimesdaily" (every 6 hours) and "weekly" */
	$schedules[ 'fourtimesdaily' ] = array(
		'interval' => 21600,
		'display' => __( 'four time daily', 'runpress' )
	);
	$schedules[ 'wekly' ] = array(
		'interval' => 604800,
		'display' => __( 'weekly', 'runpress' )
	);
	return $schedules;
}

/*
 * Function:   runpress_cronjob_event
 * Attributes: none
 *  
 * Function to start our configured wordpress internal cronjob to sync the db manually
 * 
 * @since 1.0.0
 */
function runpress_cronjob_event() {
	/* do something at the given time */
	runpress_sync_database_manually();
}

/*
 * Function:   runpress_get_dt_translation
 * Attributes: none
 *  
 * Function to get the url of the correct translation file for datatables.
 * 
 * Getting inspired by the function getDataTableTranslationUrl in the CF7DBPlugin by Michael Simpson
 * 
 * @since 1.0.0
 */
function runpress_get_dt_translation() {
	$url = null;
	$locale = get_locale();
	$dt_lang_files = dirname( __FILE__ ) . '/languages/dt_lang_files/';

	/* check if there is already a file with the correct locale code */
	if( is_readable( $dt_lang_files . $locale . '.json' ) ) {
		$url = plugin_dir_url( __FILE__ ) . "languages/dt_lang_files/$locale.json";
	}
	else
	{
		/* check if the language code of the file starts with 2 or 3 letter */
		$lang = null;
		if( substr( $locale, 2, 1 ) == '_' ) {
			/* 2-letter language code */
			$lang = substr( $locale, 0, 2 );
		}
		else if( substr( $locale, 3, 1 ) == '_' ) {
			/* 3-letter language code */
			$lang = substr( $locale, 0, 3 );
		}

		if( $lang && is_readable( $dt_lang_files . $lang . '.json' ) ) {
			$url = plugin_dir_url( __FILE__ ) . "languages/dt_lang_files/$lang.json";
		}
	}
	return $url;
}

/*
 * Function:   runpress_action_links
 * Attributes: none
 *  
 * Function to add the link to the settings to the plugin admin menu.
 * 
 * @since 1.0.0
 */
function runpress_action_links( $links ) { 
	$links[] = '<a href="'. get_admin_url(null, 'admin.php?page=runpress') .'">' . __( 'Settings', 'runpress' ) . '</a>';
	return $links;
}

?>
