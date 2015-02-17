<?php
/*
 * File Name:		runpress-widget.php
 * 
 * Plugin Name: 	RunPress
 * Plugin URI: 		http://markusfrenzel.de/wordpress/?page_id=2247
 * 
 * Description: 	A plugin to query the Runtastic website. Returns 
 * 					the data of your running activities.
 * 
 * Version: 		same as runpress.php
 * 
 * Author: 			Markus Frenzel
 * Author URI: 		http://www.markusfrenzel.de
 * E-Mail:			wordpressplugins@markusfrenzel.de
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
			array( 'description' => __( 'A widget for the Runpress Wordpress Plugin to display your sport activities from runtastic.com.', 'runpress' ), ) // Args
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
		
		$title = apply_filters( 'widget_title', $instance['title'] );

		$l = !empty( $instance['lasttrack'] ) ? '1' : '0';
		$o = !empty( $instance['onlyhighscores'] ) ? '1' : '0';
		$s = !empty( $instance['showtable'] ) ? '1' : '0';

		echo $args['before_widget'];
		if( ! empty( $title ) )
			echo $args['before_title'] . $title . $args['after_title'];

		if($l) {
			/* Select the last activity from the db and post its data into the widget */
			$query = $wpdb->get_results( "SELECT * FROM $runpress_db_name ORDER BY id desc LIMIT 1", OBJECT );
		
			foreach( $query as $row ) {
				echo "My latest running activity was " . $row->feeling;
				echo "<br><img src='http:" . str_replace( 'width=50&height=70', 'width=200&height=280', $row->map_url ) . "'>";
				echo "<br>ID: ". $row->id;
			}
		}
		
		if($o) {
			/* Select only the highscore values */
			$query = $wpdb->get_results( "SELECT pace FROM $runpress_db_name WHERE pace>0 ORDER BY pace asc LIMIT 1", OBJECT );
			
			foreach( $query as $row ) {
				echo "Pace: " . $row->pace . "<br />";
			}
			$query = $wpdb->get_results( "SELECT duration FROM $runpress_db_name ORDER BY duration DESC LIMIT 1", OBJECT );
			
			foreach( $query as $row ) {
				echo "Duration: " . $row->duration . "<br />";
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
