<?php
/**
 * Adds Runpress widget.
 */
class runpress_widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
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

		$title = apply_filters( 'widget_title', $instance['title'] );

		echo $args['before_widget'];
		if ( ! empty( $title ) )
			echo $args['before_title'] . $title . $args['after_title'];

			/* Select the last activity from the db and post its data into the widget */
			/* this is only a test to implement my ideas into the widget area */
			global $wpdb;
			global $runpress_db_name;

		$query = $wpdb->get_results( "SELECT * FROM $runpress_db_name ORDER BY id desc LIMIT 1", OBJECT );
		
		foreach( $query as $row ) {
			echo "My latest running activity was " . $row->feeling;
			echo "<br><img src='http:" . str_replace( 'width=50&height=70', 'width=200&height=280', $row->map_url ) . "'>";
			echo "<br>ID: ". $row->id;
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
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = __( 'New title', 'runpress' );
		}
		?>
		<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
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

		return $instance;
	}

} // class Foo_Widget
?>
