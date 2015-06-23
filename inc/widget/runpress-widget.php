<?php
/*
 * File Name:		runpress-widget.php
 * 
 * Plugin Name: 	RunPress
 * Plugin URI: 		http://markusfrenzel.de/wordpress/?page_id=2247
 * 
 * Description: 	Imports your running activities from the Runtastic website. Displays the data via shortcodes on your webpage. Widget included. 
 * 
 * Version: 		same as runpress.php
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

/* Adds Runpress widget */
class runpress_widget extends WP_Widget {

	/* Register widget with WordPress */
	function __construct() {
		parent::__construct(
			'runpress_widget', // Base ID
			__('Runpress Widget', 'runpress'), // Name
			array( 'description' => __( 'A widget for the Runpress Wordpress Plugin to display your running activities from runtastic.com. Cached in your local DB.', 'runpress' ), ) // Args
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {

		global $wpdb;
		global $runpress_db_name;
		
		$opt_val_unittype = get_option( 'runpress_option_unittype', 'Metric Units' );
		
		$title = apply_filters( 'widget_title', $instance['title'] );

		$l = !empty( $instance['lasttrack'] ) ? '1' : '0';
		$o = !empty( $instance['onlyhighscores'] ) ? '1' : '0';
		$s = !empty( $instance['showtable'] ) ? '1' : '0';

		echo $args['before_widget'];
		if( ! empty( $title ) )
			echo $args['before_title'] . $title . $args['after_title'];

		if(( !$l ) && ( !$o ) && ( !$s )) {
			_e( 'No data selected!', 'runpress' );
		}
		
		if( $l ) {
			/* Select the last activity from the db and post its data into the widget */
			$query = $wpdb->get_row( "SELECT date_day, date_month, date_year, distance, duration, pace, feeling, map_url FROM $runpress_db_name ORDER BY id desc LIMIT 1" );
		    if( $query ) {

				( $opt_val_unittype == "Metric Units" ? $date = sprintf( "%02s", $query->date_day ) . "." . sprintf( "%02s", $query->date_month ) . "." . sprintf( "%04s", $query->date_year ) : $date = sprintf( "%04s", $query->date_year ) . "/" . sprintf( "%02s", $query->date_month ) . "/" . sprintf( "%02s", $query->date_day ) );
				( $opt_val_unittype == "Metric Units" ? $distance = round( $query->distance/1000, 2 ) . " km" : $distance = round( ( $query->distance/1000)/1.609344, 2 ) . " mi." );
				( $opt_val_unittype == "Metric Units" ? $pace = date( 'i:s', $query->pace*60 ) . " min./km" : $pace = date( 'i:s', ( $query->pace*1.609344 )*60 ) . " min/mi." );
				$duration = date( 'H:i:s', ( $query->duration/1000 ) ) . " (h:m:s)";
				if( !$query->map_url ) {
					/* load the image with a translated string in it */
					echo "<img src='" . plugins_url() . "/runpress/inc/img/showjpg.php?image=nomapfound.jpg&text=" . __( 'No map found!', 'runpress' ) . "' /><br />";
				}
				else
				{
					echo "<img src='http:" . str_replace( 'width=50&height=70', 'width=200&height=280', $query->map_url ) . "'><br />";
				}
				echo "<table>";
				echo "<tr><td>" . __( 'Date', 'runpress' ) . ": </td><td>" . $date . "</td></tr>";
				echo "<tr><td>" . __( 'Distance', 'runpress' ) . ": </td><td>" . $distance . "</td></tr>";
				echo "<tr><td>" . __( 'Duration', 'runpress' ) . ": </td><td>" . $duration . "</td></tr>";
				echo "<tr><td>" . __( 'Pace', 'runpress' ) . ": </td><td>" . $pace . "</td></tr>";
				echo "<tr><td>" . __( 'Feeling', 'runpress' ) . ": </td><td>" . __( $query->feeling, 'runpress' ) . "</td></tr>";
				echo "</table>";
				echo "<br />";
			}
			else
			{
				_e( 'Sorry, no data found!', 'runpress' );
			}
		}
		
		if( $o ) {
			/* Select only the highscore values */
			( $opt_val_unittype == "Metric Units" ? $distance = round( $wpdb->get_var( "SELECT distance FROM $runpress_db_name ORDER BY distance DESC LIMIT 1" )/1000, 2 ) . " km" : $distance = round( ( $wpdb->get_var( "SELECT distance FROM $runpress_db_name ORDER BY distance DESC LIMIT 1" )/1000 )/1.609344, 2 ) . " mi." );
			if ( $distance && $distance>0 ) {
				( $opt_val_unittype == "Metric Units" ? $duration = date( 'H:i:s', ($wpdb->get_var( "SELECT duration FROM $runpress_db_name ORDER BY duration DESC LIMIT 1" )/1000 ) ) . " (h:m:s)" : $duration = date( 'H:i:s', ($wpdb->get_var( "SELECT duration FROM $runpress_db_name ORDER BY duration DESC LIMIT 1" )/1000 ) ) . " (h:m:s)" ); 
				( $opt_val_unittype == "Metric Units" ? $pace = date( 'i:s', ($wpdb->get_var( "SELECT pace FROM $runpress_db_name WHERE pace>0 ORDER BY pace asc LIMIT 1" )*60 ) ) . " min./km" : $pace = date( 'i:s', ($wpdb->get_var( "SELECT pace FROM $runpress_db_name WHERE pace>0 ORDER BY pace asc LIMIT 1" )*1.609344 )*60 ) . " min./mi." );
				
				echo "<table>";
				echo "<tr><td>" . __( 'Longest Distance', 'runpress' ) . ": </td><td>" . $distance . "</td></tr>";
				echo "<tr><td>" . __( 'Longest Duration', 'runpress' ) . ": </td><td>" . $duration . "</td></tr>"; 
				echo "<tr><td>" . __( 'Fastest Pace', 'runpress' ) . ": </td><td>" . $pace . "</td></tr>";
				echo "</table>"; 
				echo "<br />";
			}
			else
			{
				_e( 'Sorry, no data found!', 'runpress' );
			}
		}
		
		if( $s ) {
			/* Enqueue the needed CSS parameter */
			wp_register_style( 'runpress_tables_css', plugins_url() . '/runpress/inc/css/runpress.tables.css' );
			wp_enqueue_style( 'runpress_tables_css' );
			/* Show a table with the last 5 activities */
			$query = $wpdb->get_results( "SELECT * FROM $runpress_db_name ORDER BY id DESC LIMIT 5", OBJECT );
			if( $query ) {
											
				echo "<table class='tableclass'>
					<thead>
					<tr>
					<th align='left'><strong>" . __( 'Date', 'runpress' ) . "</strong></th>
					<th align='left'><strong>" . __( 'Distance', 'runpress' ) . "</strong></th>
					<th align='left'><strong>" . __( 'Duration', 'runpress' ) . "</strong></th>
					<th align='left'><strong>" . __( 'Pace', 'runpress' ) . "</strong></th>
					</tr></thead>";
			
				foreach( $query as $row ) {
					/* Needed vars */
					$tablecontent = "";
					( $opt_val_unittype == "Metric Units" ? $date = sprintf( "%02s", $row->date_day ) . "." . sprintf( "%02s", $row->date_month ) . "." : $date = sprintf( "%02s", $row->date_month ) . "/" . sprintf( "%02s", $row->date_day ) );
					( $opt_val_unittype == "Metric Units" ? $distance = round( $row->distance/1000, 2 ) : $distance = round( ( $row->distance/1000)/1.609344, 2 ) );
					( $opt_val_unittype == "Metric Units" ? $pace = date( 'i:s', $row->pace*60 ) : $pace = date( 'i:s', ( $row->pace*1.609344 )*60 ) );
					( $opt_val_unittype == "Metric Units" ? $duration = date( 'H:i:s', ( $row->duration/1000 ) ) : $duration = date( 'H:i:s', ( $row->duration/1000 ) ) );
					
					echo "<tr>";
					( $opt_val_unittype == "Metric Units" ? $tablecontent .= "<td title='" . $date . " (" . __('Format: DD.MM.', 'runpress' ) . ")'>" . $date . "</td>" : $tablecontent .= "<td title='" . $date . " (" . __('Format: MM/DD', 'runpress' ) . ")'>" . $date . "</td>" );
					( $opt_val_unittype == "Metric Units" ? $tablecontent .= "<td title='" . $distance . " km'>" . $distance . "</td>" : $tablecontent .= "<td title='" . $distance . " mi.'>" . $distance . "</td>" );
					( $opt_val_unittype == "Metric Units" ? $tablecontent .= "<td title='" . $duration . " (" . __('Format: hh:mm:ss', 'runpress') . ")'>" . $duration . "</td>" : $tablecontent .= "<td title='" . $duration . " (" . __('Format: hh:mm:ss', 'runpress') . ")'>" . $duration . "</td>" );
					( $opt_val_unittype == "Metric Units" ? $tablecontent .= "<td title='" . $pace . " min./km'>" . $pace . "</td>" : $tablecontent .= "<td title='" . $pace . " min./mi.'>" . $pace . "</td>" );
					$tablecontent .= "</tr>";
					echo $tablecontent;
				}
				echo "</table>";
				echo "<br />";
			}
			else
			{
				_e( 'Sorry, no data found!', 'runpress' );
			}
		}
		
		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( ( array ) $instance, array( 'title' => '' ) );
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = __( 'RunPress Widget', 'runpress' );
		}
		$lasttrack = isset( $instance[ 'lasttrack' ] ) ? (bool) $instance[ 'lasttrack' ] : false;
		$onlyhighscores = isset( $instance[ 'onlyhighscores' ] ) ? (bool) $instance[ 'onlyhighscores'] : false;
		$showtable = isset( $instance[ 'showtable' ] ) ? (booL) $instance[ 'showtable' ] : false;
		?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' , 'runpress'); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>"></p>

		<p><input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'lasttrack' ); ?>" name="<?php echo $this->get_field_name( 'lasttrack' ); ?>"<?php checked( $lasttrack ); ?> />
		<label for="<?php echo $this->get_field_id( 'lasttrack' ); ?>"><?php _e( 'Show last activity', 'runpress' ); ?></label><br />

		<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'onlyhighscores' ); ?>" name="<?php echo $this->get_field_name( 'onlyhighscores'); ?>"<?php checked( $onlyhighscores ); ?> />
		<label for="<?php echo $this->get_field_id( 'onlyhighscores' ); ?>"><?php _e( 'Show highscores', 'runpress' ); ?></label><br />
		
		<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'showtable' ); ?>" name="<?php echo $this->get_field_name( 'showtable' ); ?>"<?php checked( $showtable ); ?> />
		<label for="<?php echo $this->get_field_id( 'showtable' ); ?>"><?php _e( 'Show last 5 entries', 'runpress' ); ?></label><br />
		
		</p>

		<?php 
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance[ 'lasttrack' ] = !empty( $new_instance[ 'lasttrack' ] ) ? 1 : 0;
		$instance[ 'onlyhighscores' ] = !empty( $new_instance[ 'onlyhighscores' ] ) ? 1 : 0;
		$instance[ 'showtable' ] = !empty( $new_instance[ 'showtable' ] ) ? 1 : 0;

		return $instance;
	}

} // class Foo_Widget
?>
