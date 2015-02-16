<?php
/*
 * Plugin Name: RunPress
 * Plugin URI: http://markusfrenzel.de/wordpress/?page_id=2247
 * Description: A plugin to query the Runtastic website. Returns the data of your running activities.
 * Version: 1.0.0
 * Author: Markus Frenzel
 * Author URI: http://www.markusfrenzel.de
 * License: GPL2
 */

/*
 * Copyright 2014 - actual year Markus Frenzel (email: wordpressplugins@markusfrenzel.de)
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License 
 * along with this program; ir not, write to the Free Software 
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  
 * 02110-1301 USA
 */

/* Globals and needed vars */
global $wpdb;
global $runpress_db_version;
global $runpress_db_name;

$runpress_db_version = "1.0.0";
$runpress_db_name = $wpdb->prefix . "runpress_db";

/* Definitions */
define( 'RUNPRESS_PLUGIN_PATH', plugin_dir_path(__FILE__) );	// Used to find the plugin dir fast
define( 'ENCRYPTION_KEY', 'd0a7e7997b6d5fcd55f4b5c32611b87cd923e88837b63bf2941ef819dc8ca282' ); 	// Needed key for the crypt script to securely store data in the wordpress database

/* Required scripts */
require_once( RUNPRESS_PLUGIN_PATH . 'inc/widget/runpress-widget.php' );	// Load the code for the runpress widget
require_once( RUNPRESS_PLUGIN_PATH . 'inc/class.runtastic.php' );			// Load the runtastic class by Timo Schlueter (timo.schlueter@me.com / www.timo.in)
require_once( RUNPRESS_PLUGIN_PATH . 'inc/class.crypt.php' );				// Load the class to crypt the userpassword (Original from: http://gist.github.com/joshhartman/10342187#file-crypt-class-php)

/* Hooks */
register_activation_hook( __FILE__, 'runpress_activate' );		// Create the local DB and so on
register_deactivation_hook( __FILE__, 'runpress_deactivate' );	// If the plugin is deactivated this function starts

/* Actions */
add_action( 'plugins_loaded', 'runpress_autoupdate_db_check' );	// Check for updates if autoupdate has run before
add_action( 'plugins_loaded', 'runpress_load_textdomain' );		// Load the translations
add_action( 'widgets_init', 'runpress_register_widget' );		// Register the runpress widget
add_action( 'admin_menu', 'runpress_admin_menu' );				// Add the admin menu structure
add_action( 'runpress_event_hook', 'runpress_cronjob_event' );	// The scheduled WP-Cron Job (if any)

/* Filters */
add_filter( 'cron_schedules', 'runpress_add_cronjob_definitions' );

/* Shortcodes */
add_shortcode( 'runpress', 'runpress_shortcode' );

/* Normal code */
if( get_option( 'runpress_option_username' ) == false ) {
	add_action( 'admin_notices', 'runpress_admin_notices' );	// Checks if RunPress is configured yet. If not - display a message.
}

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
			map_url VARCHAR(200) NOT NULL,
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
	
	add_option( "runpress_option_db_version", $runpress_db_version );
	
	$installed_ver = get_option( "runpress_db_version" );
	
	if( $installed_ver != $runpress_db_version ) {
		/* If there will be database changes in the future... */
		
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
	/* Check if the user wants to delete all options... if so.. do it! */
	if( get_option( 'runpress_option_delete_options' ) == 1 ) {
		delete_option( 'runpress_option_db_version' );
		delete_option( 'runpress_option_username' );
		delete_option( 'runpress_option_userpass' );
		delete_option( 'runpress_option_unittype' );
		delete_option( 'runpress_option_delete_options' );
		delete_option( 'runpress_option_cronjobtime' );
		delete_option( 'runpress_runtastic_username' );
		delete_option( 'runpress_runtastic_uid' );
	}
	/* Delete the scheduled WP-Cron if it is there */
	wp_clear_scheduled_hook( 'runpress_event_hook' );
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
	global $runpress_db_version;
	if( get_site_option( 'runpress_option_db_version' ) != $runpress_db_version ) {
		runpress_install();
	}
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
	load_plugin_textdomain( 'runpress', false, RUNPRESS_PLUGIN_PATH . 'languages/' );
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
	/* Deactivated the following two lines because this feature are not ready at the moment... Coming soon... */
	add_submenu_page( 'runpress', __( 'RunPress Sync', 'runpress' ), __( 'Sync', 'runpress' ), 'manage_options', 'runpress-sync', 'runpress_sync' );
	// add_submenu_page( 'runpress', __( 'RunPress Shortcode Generator', 'runpress' ), __( 'Shortcode Generator', 'runpress' ), 'manage_options', 'runpress-shortcode-generator', 'runpress_shortcode_generator' );
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
		'content' => __( 'This is only a placeholder for my upcoming help text.', 'runpress' )
	) );
	$screen->add_help_tab( array( 
		'id' => '2',
		'title' => __( 'Info', 'runpress' ),
		'content' => __( 'This is the place where the information about the plugin should be placed.', 'runpress' ) . '\n\n&copy 2014 - ' . date("Y") . ' Markus Frenzel'
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
	$crypt = new Crypt( ENCRYPTION_KEY );
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
	$opt_val_deleteoptions = get_option( $opt_deleteoptions, '0' );
	$opt_val_runtastic_username = get_option( $opt_runtastic_username, '' );
	$opt_val_runtastic_uid = get_option( $opt_runtastic_uid, '' );
	/* Check if the runtastic username is already in the db */
	if( get_option( $opt_runtastic_username ) != false ) {
		echo "<div id='notice' class='updated'><p>" . __( 'Your Runtastic Username: ', 'runpress' ) . $opt_val_runtastic_username . " / UID: " . $opt_val_runtastic_uid . "</p></div>\n";
	}
	/* Lets see if the user has posted some information. If so, the hidden field will be set to 'Y' */
	if( isset( $_POST[ $hidden_field_name ] ) && $_POST[ $hidden_field_name ] == 'Y' ) {
		/* Read the posted values */
		$opt_val_name = $_POST[ $data_field_name ];
		/* Encrypt the password so that it is safe in the wordpress database */
		$opt_val_pass = $crypt->encrypt( $_POST[ $data_field_pass ] );
		$opt_val_unittype = $_POST[ $data_field_unittype ];
		$opt_val_deleteoptions = $_POST[ $data_field_deleteoptions ];
		/* Save the posted values in the database */
		update_option( $opt_name, $opt_val_name );
		update_option( $opt_pass, $opt_val_pass );
		update_option( $opt_unittype, $opt_val_unittype );
		update_option( $opt_deleteoptions, $opt_val_deleteoptions );
		/* Query the runtastic website to get the runtastic username and uid */
		$runtastic = new Runtastic();
		$runtastic->setUsername( $opt_val_name );
		/* Decrypt the password on the fly */
		$runtastic->setPassword( $crypt->decrypt( $opt_val_pass ) );
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
		/* Show an 'settings updated' mesage on the screen */
		echo "<div id='notice' class='updated' onclick='remove(this)'><p><strong>" . _e( 'Settings saved.', 'runpress' ) . "</strong></p></div>";
	}
	/* Now show the settings editing screen */
	?>
	<div class="wrap">
	<h2><?php _e( 'RunPress Plugin Settings', 'runpress' ); ?></h2>
	<form name="form1" method="post" action="">
	<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
	<table border="0">
	<tr>
	<td><?php _e( 'Runtastic Username:', 'runpress' ); ?></td>
	<td><input type="text" name="<?php echo $data_field_name; ?>" value="<?php echo $opt_val_name; ?>" size="20"></td>
	</tr>
	<tr>
	<td><?php _e( 'Runtastic Password:', 'runpress' ); ?></td>
	<td><input type="password" name="<?php echo $data_field_pass; ?>" value="<?php echo $crypt->decrypt( $opt_val_pass ); ?>" size="20"></td>
	</tr>
	<tr>
	<td colspan="2"><hr /></td></tr>
	<tr>
	<td><?php _e( 'Activitytype:', 'runpress' ); ?></td>
	<td>Running only</td>
	</tr>
	<tr>
	<td><?php _e( 'Unit Type:', 'runpress' ); ?></td>
	<td><select name="<?php echo $data_field_unittype; ?>" size="1"><option <?php if( $opt_val_unittype=="Metric Units") { echo "selected"; } ?>><?php echo __( 'Metric Units', 'runpress' ); ?></option><option <?php if( $opt_val_unittype=="Imperial Units") { echo "selected"; } ?>><?php echo __( 'Imperial Units', 'runpress' ); ?></option></select></td>
	</tr>
	<tr>
	<td colspan="2"><hr /></td>
	</tr>
	<tr>
	<td><?php _e( 'Delete Options:', 'runpress' ); ?></td>
	<td><input type="checkbox" name="<?php echo $data_field_deleteoptions; ?>" value="1" <?php if ( $opt_val_deleteoptions == 1 ) { echo 'checked="checked"'; } ?>><?php _e( 'Deletes all options on deactivation of the plugin.', 'runpress' ); ?></td>
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
	/* enqueue the needed scripts */
	wp_register_script( 'jquery_datatables_js', plugins_url() . '/runpress/inc/js/jquery.dataTables.min.js', array(), null, true );
	wp_enqueue_script( 'jquery_datatables_js' );
	wp_register_style( 'jquery_datatables_css', plugins_url() . '/runpress/inc/css/jquery.dataTables.css' );
	wp_enqueue_style( 'jquery_datatables_css' );
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
		  <th align='left'>" . __( 'Date', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Start', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Duration', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Distance', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Pace', 'runpress' ) . "</th>
		  <th align='left'>" . __( 'Speed', 'runpress' ) . "</th>
		  </tr></thead>
		  <tfoot>
		  <tr>
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
		$date = sprintf( "%02s", $row->date_day ) . "." . sprintf( "%02s", $row->date_month ) . "." . sprintf( "%04s", $row->date_year );
		$time = sprintf( "%02s", $row->date_hour ) . ":" . sprintf( "%02s", $row->date_minutes ) . ":" . sprintf( "%02s", $row->date_seconds );
		$duration = date( 'H:i:s', ( $row->duration/1000 ) );
		$distance = round( $row->distance/1000, 2 );
		$pace = date( 'i:s', ( $row->pace*60 ) );
		$speed = round( $row->speed, 2 );
		echo "<tr>
		      <td>" . $date . "</td>
		      <td>" . $time . "</td>
		      <td>" . $duration . "</td>
		      <td>" . $distance . "</td>
		      <td>" . $pace . "</td>
		      <td>" . $speed . " km/h</td>
		      </tr>";
	}
	?>
	</tbody>
	</table>
	<script type="text/javascript">
		jQuery(document).ready(function() {
			/* Init dataTable */
			jQuery('#backend_results').dataTable( {
				"ordering": false,
				"language" : {
					"lengthMenu": "Display _MENU_ records per page",
					"zeroRecords": "Nothing found - sorry",
					"info": "Showing page _PAGE_ of _PAGES_",
					"infoEmpty": "No records available",
					"infoFiltered": "(filtered from _MAX_ total records)",
					"decimal": ",",
					"thousands": ".",
					"paginate": {
						"first":		"First",
						"last":		"Last",
						"next":		"Next",
						"previous":	"Previous"
					}
				},
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
	$crypt = new Crypt( ENCRYPTION_KEY );
	/* query the runtastic website */
	$runtastic = new Runtastic();
	$runtastic->setUsername( get_option( 'runpress_option_username' ) );
	/* decrypt the password on the fly */
	$runtastic->setPassword( $crypt->decrypt( get_option( 'runpress_option_userpass' ) ) );
	$runtastic->setTimeout( 20 );
	if( $runtastic->login() ) {
		$activities = $runtastic->getActivities();
		foreach( $activities as $activity ) {
			if( $activity->type=="running" ) {
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
		<div id="notice" class="error" onclick="remove(this)"><p><?php _e( 'DB was not successfully truncated. Please try again.' ); ?> <?php _e( '| <strong>Dismiss</strong>', 'runpress' ); ?></p></div>
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
		'sortorder' => 'asc',
		'display' => 'table',
		), $atts );
	
	if( ( $a[ 'year' ] > 999 ) and $a[ 'year' ] < 10000 ) {
		$query = $wpdb->get_results( "SELECT * FROM $runpress_db_name WHERE date_year=" . $a[ 'year' ] . "ORDER BY date_year " . $a[ 'sortorder' ], OBJECT );
	}
	else
	{
		$query = $wpdb->get_results( "SELECT * FROM $runpress_db_name ORDER BY date_year " . $a[ 'sortorder' ], OBJECT );
	}
	/* The core table which is used to display the data native and through JQuery Datatables */
	if( $a[ 'display' ] == "table" || $a[ 'display' ] == "datatable" ) {
		$header = "";
		$body = "";
		$footer = "";
		/* Define the header of the table */
		$header .= "<table id='{$a['display']}_results' class='display' cellspacing='0' width='100%'>";
		$header .= "<thead>";
		$header .= "<tr>";
		$header .= "<th align='left'>" . _e( 'Date', 'runpress' ) . "</th>";
		$header .= "<th align='left'>" . _e( 'Start', 'runpress' ) . "</th>";
		$header .= "<th align='left'>" . _e( 'Duration', 'runpress' ) . "</th>";
		$header .= "<th align='left'>" . _e( 'Distance', 'runpress' ) . "</th>";
		$header .= "<th align='left'>" . _e( 'Pace', 'runpress' ) . "</th>";
		$header .= "<th align='left'>" . _e( 'Speed', 'runpress' ) . "</th>";
		$header .= "</tr>";
		$header .= "</thead>";
		/* Define the footer of the table */
		$footer .= "<tfoot>";
		$footer .= "<tr>";
		$footer .= "<th align='left'>" . _e( 'Date', 'runpress' ) . "</th>";
		$footer .= "<th align='left'>" . _e( 'Start', 'runpress' ) . "</th>";
		$footer .= "<th align='left'>" . _e( 'Duration', 'runpress' ) . "</th>";
		$footer .= "<th align='left'>" . _e( 'Distance', 'runpress' ) . "</th>";
		$footer .= "<th align='left'>" . _e( 'Pace', 'runpress' ) . "</th>";
		$footer .= "<th align='left'>" . _e( 'Speed', 'runpress' ) . "</th>";
		$footer .= "</tr>";
		$footer .= "</tfoot>";
		/* Define the body of the table */
		$body .= "<tbody>";
		foreach( $query as $row ) {
			$date = sprintf( "%02s", $row->date_day ) . "." . sprintf( "%02s", $row->date_month ) . "." . sprintf( "%04s", $row->date_year );
			$time = sprintf( "%02s", $row->date_hour ) . ":" . sprintf( "%02s", $row->date_minutes ) . ":" . sprintf( "%02s", $row->date_seconds );
			$duration = date( 'H:i:s', ( $row->duration/1000 ) );
			$distance = round( $row->distance/1000, 2 );
			$pace = date( 'i:s', ( $row->pace*60 ) );
			$speed = round( $row->speed, 2 );
			$body .= "<tr>";
			$body .= "<td>" . $date . "</td>";
			$body .= "<td>" . $time . "</td>";
			$body .= "<td>" . $duration . "</td>";
			$body .= "<td>" . $distance . "</td>";
			$body .= "<td>" . $pace . "</td>";
			$body .= "<td>" . $speed . "</td>";
			$body .= "</tr>";
		}
		$body .= "</tbody>";
		$footer .= "</table>";
		$returncontent = $header . $body . $footer;
	}
	/* Display the data with the use of JQuery Datatables */
	if( $a[ 'display' ] == "datatable" ) {
		/* enqueue the needed scripts */
		wp_register_script( 'jquery_datatables_js', plugins_url() . '/runpress/inc/js/jquery.dataTables.min.js', array(), null, true );
		wp_enqueue_script( 'jquery_datatables_js' );
		wp_register_style( 'jquery_datatables.css', plugins.url() . '/runpress/inc/css/jquery.dataTables.css' );
		wp_enqueue_style( 'jquery_datatables_css' );
		?>
		<script type="text/javascript">
		jQuery(document).ready(function(){
			/* Init dataTable */
			jQuery('#datatable_results').dataTable( {
				"language": {
					"lengthMenu": "Display _MENU_ records per page",
					"zeroRecords": "Nothing found - sorry",
					"info": "Showing page _PAGE_ of _PAGES_",
					"infoEmpty": "No records available",
					"infoFiltered": "filtered from _MAX_ total records)",
					"decimal": ",",
					"thousands": ".",
					"paginate": {
						"first":	"First",
						"last":		"Last",
						"next":		"Next",
						"previous":	"Previous"
					}
				},
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
		<script type="text/javascript" src="https://www.google.com/jsapi"></script>
		<script type="text/javascript">
			google.load("visualization", "1", {packages:["corechart"]});
			google.setOnLoadCallback(drawChart);
			function drawChart() {
				var data = google.visualization.arrayToDataTAble([
					['<?php _e( 'Month', 'runpress' ) ?>', '<?php _e( 'Distance', 'runpress' ) ?>'],
					['01', <?php echo ($sumkm_jan == 0) ? 'null' : $sumkm_jan; ?>],
					['02', <?php echo ($sumkm_feb == 0) ? 'null' : $sumkm_feb; ?>],
					['03', <?php echo ($sumkm_mar == 0) ? 'null' : $sumkm_mar; ?>],
					['04', <?php echo ($sumkm_apr == 0) ? 'null' : $sumkm_apr; ?>],
					['05', <?php echo ($sumkm_may == 0) ? 'null' : $sumkm_may; ?>],
					['06', <?php echo ($sumkm_jun == 0) ? 'null' : $sumkm_jun; ?>],
					['07', <?php echo ($sumkm_jul == 0) ? 'null' : $sumkm_jul; ?>],
					['08', <?php echo ($sumkm_aug == 0) ? 'null' : $sumkm_aug; ?>],
					['09', <?php echo ($sumkm_sep == 0) ? 'null' : $sumkm_sep; ?>],
					['10', <?php echo ($sumkm_oct == 0) ? 'null' : $sumkm_oct; ?>],
					['11', <?php echo ($sumkm_nov == 0) ? 'null' : $sumkm_nov; ?>],
					['12', <?php echo ($sumkm_dec == 0) ? 'null' : $sumkm_dec; ?>],
				]);
				
				var option = {
					title: '<?php _e( 'Results', 'runpress' ) . " {$a [ 'year'] }"; ?>',
					titlePosition: 'out',
					legend: { Position: 'bottom' },
					width: '100%',
					height: 500,
					curveType: 'none',
					chartArea: { left:50, top:20 },
					hAxis: { title: '<?php _e( 'Month', 'runpress' ) ?>' },
					vAxis: { title: '<?php _e( 'Distance', 'runpress' ) ?>', minValue: '0', maxValue: '100' },
				};
				
				var chart = new google.visualization.LineChart(document.getElementById('chart_div<?php echo "_{$a[ 'year' ] }" ?>') );
				chart.draw(data, options);
			}
		</script>
		<?php
		$returncontent = "<div id=\"charf_div_{$a[ 'year' ] }\"></div>";
	}
	return returncontent;
}
	
/*
 * Function:   runpress_enqueu_scripts
 * Attributes: none
 *  
 * Enqueues needed scripts
 * 
 * @since 1.0.0
 */
function runpress_enqueue_scripts() {
	wp_register_script( 'jquery_datatables_js', plugins_url() . 'runpress/inc/js/jquery.dataTables.js', array(), null, false );
	wp_enqueue_script( 'jquery_datatables_js' );
	wp_register_style( 'jquery_datatables_css', plugins_url() . 'runpress/inc/css/jquery.dataTables.css' );
	wp_enqueue_style( 'jquery_datatables_css' );
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
	}
	/* see if the user wants to delete the cron job */
	if( isset( $_POST[ $hidden_field_name5 ] ) && $_POST[ $hidden_field_name5 ] == 'Y' ) {
		wp_clear_scheduled_hook( 'runpress_event_hook' );
		delete_option( 'runpress_option_cronjobtime' );
		$opt_val_cronjobtime = '';
	}
	/* now display the local db entry count */
	echo "<h2>" . __( 'Runpress Sync Settings', 'runpress' ) . "</h2>";
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
	<?php _e( 'If your want tot delete the entries in your local db, click the following button. Only the entries in your local db will be deleted. It does not affect the entries in the runtastic db!', 'runpress' ); ?>
	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Delete all entries in local db' ) ?>" />
	</p>
	</form>
	</div>
	<div class="wrap">
	<h3><?php _e( 'Schedule a Wordpress Cron Job', 'runpress' ) ?></h3>
	<form name="form4" method="post" action="">
	<input type="hidden" name="<?php echo $hidden_field_name4; ?>" value="Y">
	<?php
	if( wp_next_scheduled( 'runpress_even_hook' ) ) {
		_e( 'Your have scheduled a WP Cron job to run at the following basis ', 'runpress' );
	}
	else
	{
		_e( 'Define a WP Cron job to start the sync of your local db automatically.' );
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
	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Schedule Cron job', 'runpress' ); ?>" />
	</p>
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
 * Function:   runpress_schortcode_generator
 * Attributes: none
 *  
 * The shortcode might not be easy to understand. So I offer some kind of generator for that.
 * 
 * @since 1.0.0
 */
function runpress_shortcode_generator() {
	echo "<h2>" . _e( 'Runpress Shortcode Generator', 'runpress' ) . "</h2>";
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
?>