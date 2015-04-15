<?php

/**
 * WPdonations_Form_Submit_Donation class.
 */
class WPdonations_Form_Submit_donation extends WPdonations_Form {

	public    static $form_name = 'submit-donation';
	protected static $donation_id;
	protected static $preview_donation;
	protected static $steps;
	protected static $step;

	/**
	 * Init form
	 */
	public static function init() {
		add_action( 'wp', array( __CLASS__, 'process' ) );

		// Get step/donation
		self::$step   		= ! empty( $_REQUEST['step'] ) ? max( absint( $_REQUEST['step'] ), 0 ) : 0;
		self::$donation_id  = ! empty( $_REQUEST['donation_id'] ) ? absint( $_REQUEST[ 'donation_id' ] ) : 0;

		self::$steps  = (array) apply_filters( 'submit_donation_steps', array(
			'submit' => array(
				'name'     => __( 'Submit Donation', 'wpdonations' ),
				'view'     => array( __CLASS__, 'submit' ),
				'handler'  => array( __CLASS__, 'submit_handler' ),
				'priority' => 10
				),
			'preview' => array(
				'name'     => __( 'Confirmation', 'wpdonations' ),
				'view'     => array( __CLASS__, 'preview' ),
				'handler'  => array( __CLASS__, 'preview_handler' ),
				'priority' => 20
			),
			'done' => array(
				'name'     => __( 'Done', 'wpdonations' ),
				'view'     => array( __CLASS__, 'done' ),
				'priority' => 30
			)
		) );

		usort( self::$steps, array( __CLASS__, 'sort_by_priority' ) );

		// Validate donation ID if set
		if ( self::$donation_id && ! in_array( get_post_status( self::$donation_id ), apply_filters( 'wpdonations_valid_submit_donation_statuses', array( 'preview' ) ) ) ) {
			self::$donation_id = 0;
			self::$step   = 0;
		}
	}

	/**
	 * Increase step from outside of the class
	 */
	public static function next_step() {
		self::$step ++;
	}

	/**
	 * Decrease step from outside of the class
	 */
	public static function previous_step() {
		self::$step --;
	}

	/**
	 * Sort array by priority value
	 */
	private static function sort_by_priority( $a, $b ) {
		return $a['priority'] - $b['priority'];
	}

	/**
	 * Get the submitted donation ID
	 * @return int
	 */
	public static function get_donation_id() {
		return absint( self::$donation_id );
	}

	/**
	 * init_fields function.
	 *
	 * @access public
	 * @return void
	 */
	public static function init_fields() {
		if ( self::$fields )
			return;

		self::$fields = apply_filters( 'submit_donation_form_fields', array(
			'donation' => array(
				'donation_amount' => array(
					'label'       => __( 'Donation Amount', 'wpdonations' ),
					'type'        => 'donation_amount',
					'required'    => true,
					'options'     => self::available_donation_amounts(),
					'placeholder' => __( 'Or enter your donation amount, e.g: 50.00', 'wpdonations' ),
					'priority'    => 1
				),
				'donation_payment_type' => array(
					'label'       => __( 'Payment Type', 'wpdonations' ),
					'description' => __( 'Select the payment method', 'wpdonations' ),
					'required'    => true,
					'type'        => 'select',
					'options'     => self::payment_types(),
					'placeholder' => '',
					'priority'    => 2
				),
				'donation_campaign' => array(
					'label'       => __( 'Campaigns', 'wpdonations' ),
					'description' => __( 'Select the campaign you would like to contribute to', 'wpdonations' ),
					'type'        => 'select',
					'required'    => false,
					'options'     => self::donation_campaigns(),
					'placeholder' => '',
					'priority'    => 3
				),
				'donation_category' => array(
					'label'       => __( 'Donation category', 'wpdonations' ),
					'type'        => 'select',
					'required'    => false,
					'options'     => self::donation_categories(),
					'placeholder' => '',
					'priority'    => 4
				),
				'donation_message' => array(
					'label'       => __( 'Custom Message', 'wpdonations' ),
					'type'        => 'donation-message',
					'required'    => true,
					'placeholder' => '',
					'priority'    => 5
				),
				'recurring_donation' => array(
					'label'       => __( 'Recurring?', 'wpdonations' ),
					'description' => __( 'Select the payment interval', 'wpdonations' ),
					'type'        => 'select',
					'required'    => true,
					'options'     => self::donation_recurring_periods(),
					'placeholder' => '',
					'priority'    => 6
				),
				'anonymous_donation' => array(
					'label'       => __( 'Anonymous donation?', 'wpdonations' ),
					'type'        => 'checkbox',
					'required'    => false,
					'description' => __( 'Check this box to hide your name in our donors list', 'wpdonations' ),
					'priority'    => 7,
					'value' 	  => 0
				),
			),
			'donor' => array(
				'donor_firstname' => array(
					'label'       => __( 'Your firstname', 'wpdonations' ),
					'type'        => 'text',
					'required'    => true,
					'placeholder' => __( 'Enter your firstname', 'wpdonations' ),
					'priority'    => 1
				),
				'donor_lastname' => array(
					'label'       => __( 'Your lastname', 'wpdonations' ),
					'type'        => 'text',
					'required'    => true,
					'placeholder' => __( 'Enter your lastname', 'wpdonations' ),
					'priority'    => 2
				),
				'donor_address' => array(
					'label'       => __( 'Address', 'wpdonations' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( 'Enter your address', 'wpdonations' ),
					'maxlength'   => 64,
					'priority'    => 3
				),
				'donor_zip' => array(
					'label'       => __( 'Postcode / Zip', 'wpdonations' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( 'Enter your postcode / Zip', 'wpdonations' ),
					'priority'    => 4
				),
				'donor_town' => array(
					'label'       => __( 'Town / City', 'wpdonations' ),
					//'description' => __( 'Enter the town your living in', 'wpdonations' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( 'Enter the town your living in', 'wpdonations' ),
					'priority'    => 5
				),
				'donor_website' => array(
					'label'       => __( 'Website', 'wpdonations' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( 'http://', 'wpdonations' ),
					'priority'    => 6
				),
				'donor_logo' => array(
					'label'       => __( 'Profile Picture', 'wpdonations' ),
					'type'        => 'file',
					'required'    => false,
					'placeholder' => '',
					'priority'    => 7
				)
			)
		) );

		if ( ! get_option( 'wpdonations_enable_categories' ) || wp_count_terms( 'donation_category' ) == 0 )
			unset( self::$fields['donation']['donation_category'] );
			
		if ( ! get_option( 'wpdonations_enable_campaigns' ) || wp_count_terms( 'donation_campaign' ) == 0 )
			unset( self::$fields['donation']['donation_campaign'] );
		
		if ( 'stripe' == get_option( 'wpdonations_gateway' ) )
			unset( self::$fields['donation']['recurring_donation'] );
	}

	/**
	 * Get post data for fields
	 *
	 * @return array of data
	 */
	protected static function get_posted_fields() {
		
		self::init_fields();

		$values = array();

		foreach ( self::$fields as $group_key => $fields ) {
			foreach ( $fields as $key => $field ) {
				// Get the value
				if ( method_exists( __CLASS__, "get_posted_{$field['type']}_field" ) )
					$values[ $group_key ][ $key ] = call_user_func( __CLASS__ . "::get_posted_{$field['type']}_field", $key, $field );
				else
					$values[ $group_key ][ $key ] = self::get_posted_field( $key, $field );

				// Set fields value
				self::$fields[ $group_key ][ $key ]['value'] = $values[ $group_key ][ $key ];
			}
		}

		return $values;
	}

	/**
	 * Get the value of a posted field
	 * @param  string $key
	 * @param  array $field
	 * @return string
	 */
	protected static function get_posted_field( $key, $field ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( trim( stripslashes( $_POST[ $key ] ) ) ) : '';
	}

	/**
	 * Get the value of a posted multiselect field
	 * @param  string $key
	 * @param  array $field
	 * @return array
	 */
	protected static function get_posted_multiselect_field( $key, $field ) {
		return isset( $_POST[ $key ] ) ? array_map( 'sanitize_text_field',  $_POST[ $key ] ) : array();
	}

	/**
	 * Get the value of a posted file field
	 * @param  string $key
	 * @param  array $field
	 * @return string
	 */
	protected static function get_posted_file_field( $key, $field ) {
		return self::upload_image( $key );
	}

	/**
	 * Get the value of a posted textarea field
	 * @param  string $key
	 * @param  array $field
	 * @return string
	 */
	protected static function get_posted_textarea_field( $key, $field ) {
		return isset( $_POST[ $key ] ) ? wp_kses_post( trim( stripslashes( $_POST[ $key ] ) ) ) : '';
	}

	/**
	 * Get the value of a posted textarea field
	 * @param  string $key
	 * @param  array $field
	 * @return string
	 */
	protected static function get_posted_wp_editor_field( $key, $field ) {
		return self::get_posted_textarea_field( $key, $field );
	}

	/**
	 * Validate the posted fields
	 *
	 * @return bool on success, WP_ERROR on failure
	 */
	protected static function validate_fields( $values ) {
		foreach ( self::$fields as $group_key => $fields ) {
			foreach ( $fields as $key => $field ) {
				if ( $field['type'] == 'donation_amount' && ! is_numeric( $values[ $group_key ][ $key ]) )
					return new WP_Error( 'validation-error', sprintf( __( '%s must be a valid number. Do not include currency symbol.', 'wpdonations' ), $field['label'] ) );
					
				if ( $field['required'] && empty( $values[ $group_key ][ $key ] ) ) {
					return new WP_Error( 'validation-error', sprintf( __( '%s is a required field', 'wpdonations' ), $field['label'] ) );
				}
			}
		}

		return apply_filters( 'submit_donation_form_validate_fields', true, self::$fields, $values );
	}
	
	/**
	 * payment_types function.
	 *
	 * @access private
	 * @return void
	 */
	private static function payment_types() {
		$options = array( 
					'online' => __( 'Online payment', 'wpdonations' ),
					'offline' => __( 'Offline payment', 'wpdonations' ) 
				);
		return $options;
	}
	
	/**
	 * available_donation_amounts function.
	 *
	 * @access private
	 * @return void
	 */
	private static function available_donation_amounts() {
		$options = array( 
					'10' => '10',
					'25' => '25',
					'50' => '50',
					'100' => '100',
					'200' => '200',
					'500' => '500'
				);
		return apply_filters( 'donation_available_amounts', $options );
	}

	/**
	 * donation_campaigns function.
	 *
	 * @access private
	 * @return void
	 */
	private static function donation_campaigns() {
		$options = array(  '' => '- '. __( 'no specific campaign', 'wpdonations' ). ' -'  );
		$terms   = get_donation_campaigns();
		
		foreach ( $terms as $term )
			$options[ $term->slug ] = $term->name . ' - (' . __( 'Target:', 'wpdonations' ) . ' '. the_campaign_target( '', '', false, $term->term_id) . ')';
		return $options;
	}

	/**
	 * donation_categories function.
	 *
	 * @access private
	 * @return void
	 */
	private static function donation_categories() {
		$options = array(  '' => '- '. __( 'no specific category', 'wpdonations' ). ' -'  );
		$terms   = get_donation_categories();
		foreach ( $terms as $term )
			$options[ $term->slug ] = $term->name;
		return $options;
	}
	
	/**
	 * donation_recurring_periods function.
	 *
	 * @access private
	 * @return void
	 */
	private static function donation_recurring_periods() {
		$options = array();
		$terms   = array( 
						array( 'oneshot', '- '. __( 'not recurring', 'wpdonations' ). ' -' ), 
						array( 'weekly', __( 'recurring: weekly', 'wpdonations' ) ), 
						array( 'monthly', __( 'recurring: monthly', 'wpdonations' ) ),
						array( 'yearly', __( 'recurring: yearly', 'wpdonations' ) ),
						);
						
		$terms = apply_filters( 'donation_recurring_periods', $terms );
		foreach ( $terms as $term )
			$options[ $term[0] ] = $term[1] ;
		return $options;
	}

	/**
	 * Process function. all processing code if needed - can also change view if step is complete
	 */
	public static function process() {
		$keys = array_keys( self::$steps );

		if ( isset( $keys[ self::$step ] ) && is_callable( self::$steps[ $keys[ self::$step ] ]['handler'] ) ) {
			call_user_func( self::$steps[ $keys[ self::$step ] ]['handler'] );
		}
	}

	/**
	 * output function. Call the view handler.
	 */
	public static function output() {
		$keys = array_keys( self::$steps );

		self::show_errors();

		if ( isset( $keys[ self::$step ] ) && is_callable( self::$steps[ $keys[ self::$step ] ]['view'] ) ) {
			call_user_func( self::$steps[ $keys[ self::$step ] ]['view'] );
		}
	}

	/**
	 * Submit Step
	 */
	public static function submit() {
		global $wpdonations, $post;

		self::init_fields();

		// Load data if neccessary
		if ( ! empty( $_POST['edit_donation'] ) && self::$donation_id ) {
			$donation = get_post( self::$donation_id );
			foreach ( self::$fields as $group_key => $fields ) {
				foreach ( $fields as $key => $field ) {
					switch ( $key ) {
/*
						case 'donation_title' :
							self::$fields[ $group_key ][ $key ]['value'] = $donation->post_title;
						break;
*/
						case 'donation_message' :
							self::$fields[ $group_key ][ $key ]['value'] = $donation->post_content;
						break;
						case 'donation_campaign' :
							self::$fields[ $group_key ][ $key ]['value'] = current( wp_get_object_terms( $donation->ID, 'donation_campaign', array( 'fields' => 'slugs' ) ) );
						break;
						case 'donation_category' :
							self::$fields[ $group_key ][ $key ]['value'] = current( wp_get_object_terms( $donation->ID, 'donation_category', array( 'fields' => 'slugs' ) ) );
						break;
						default:
							self::$fields[ $group_key ][ $key ]['value'] = get_post_meta( $donation->ID, '_' . $key, true );
						break;
					}
				}
			}

			self::$fields = apply_filters( 'submit_donation_form_fields_get_donation_data', self::$fields, $donation );

		// Get user meta
		} elseif ( is_user_logged_in() && empty( $_POST ) ) {
			if ( is_user_logged_in() ) {
				foreach ( self::$fields[ 'donor' ] as $key => $field ) {
					self::$fields[ 'donor' ][ $key ]['value'] = get_user_meta( get_current_user_id(), '_' . $key, true );
				}

				self::$fields = apply_filters( 'submit_donation_form_fields_get_user_data', self::$fields, get_current_user_id() );
			}
		}

		get_wpdonations_template( 'donation-submit.php', array(
			'form'               => self::$form_name,
			'donation_id'        => self::get_donation_id(),
			'action'             => self::get_action(),
			'donation_fields'    => self::get_fields( 'donation' ),
			'donor_fields'       => self::get_fields( 'donor' ),
			'submit_button_text' => __( 'Confirm donation &rarr;', 'wpdonations' )
			) );
	}

	/**
	 * Submit Step is posted
	 */
	public static function submit_handler() {
		try {

			// Get posted values
			$values = self::get_posted_fields();

			if ( empty( $_POST['submit_donation'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'submit_form_posted' ) )
				return;

			// Validate required
			if ( is_wp_error( ( $return = self::validate_fields( $values ) ) ) )
				throw new Exception( $return->get_error_message() );

			// Account creation
			if ( ! is_user_logged_in() ) {
				$create_account = false;

				if ( wpdonations_enable_registration() && ! empty( $_POST['create_account_email'] ) )
					$create_account = WPdonations_create_account( $_POST['create_account_email'] );

				if ( is_wp_error( $create_account ) )
					throw new Exception( $create_account->get_error_message() );
			}

			if ( wpdonations_user_requires_account() && ! is_user_logged_in() )
				throw new Exception( __( 'You must be signed in to post a new donation.' ) );

			// Update the donation
			self::save_donation( $values['donation']['donation_message'] );
			self::update_donation_data( $values );

			// Successful, show next step
			self::$step ++;

		} catch ( Exception $e ) {
			self::add_error( $e->getMessage() );
			return;
		}
	}

	/**
	 * Update or create a donation from posted data
	 *
	 * @param  string $post_title
	 * @param  string $post_content
	 * @param  string $status
	 */
	protected static function save_donation( $post_content, $status = 'preview' ) {
		$donation_data = array(
			'post_title'     => __( 'New Donation', 'wpdonations' ),
			'post_content'   => $post_content,
			'post_status'    => $status,
			'post_type'      => 'donation',
			'comment_status' => 'closed'
		);

		if ( self::$donation_id ) {
			$donation_data['ID'] = self::$donation_id;
			wp_update_post( $donation_data );
		} else {
			self::$donation_id = wp_insert_post( $donation_data );
		}
		
		$donation_title = array(
			'ID'         => self::$donation_id,
			'post_title' => __( 'Donation #', 'wpdonations' ).self::$donation_id,
		);
		
		// Update title
		wp_update_post( $donation_title );
	}

	/**
	 * Set donation meta + terms based on posted values
	 *
	 * @param  array $values
	 */
	protected static function update_donation_data( $values ) {

		if ( get_option( 'wpdonations_enable_categories' ) && isset( $values['donation']['donation_category'] ) ) {
			wp_set_object_terms( self::$donation_id, ( is_array( $values['donation']['donation_category'] ) ? $values['donation']['donation_category'] : array( $values['donation']['donation_category'] ) ), 'donation_category', false );
		}
		
		if ( get_option( 'wpdonations_enable_campaigns' ) && isset( $values['donation']['donation_campaign'] ) ) {
			wp_set_object_terms( self::$donation_id, ( is_array( $values['donation']['donation_campaign'] ) ? $values['donation']['donation_campaign'] : array( $values['donation']['donation_campaign'] ) ), 'donation_campaign', false );
		}
		
		if( $values['donation']['anonymous_donation'] == 1 ) {
			update_post_meta( self::$donation_id, '_anonymous_donation', true );
		} else {
			delete_post_meta( self::$donation_id, '_anonymous_donation' );
		}
		
		if( $values['donation']['recurring_donation'] != 'oneshot' ) {
			update_post_meta( self::$donation_id, '_recurring_donation', true );
			update_post_meta( self::$donation_id, '_recurrence_period',  $values['donation']['recurring_donation']);
		} else {
			delete_post_meta( self::$donation_id, '_recurring_donation' );
		}

		update_post_meta( self::$donation_id, '_donation_amount', $values['donation']['donation_amount'] );
		update_post_meta( self::$donation_id, '_donation_payment_type', $values['donation']['donation_payment_type'] );
		
		update_post_meta( self::$donation_id, '_donor_email', $values['donor']['donor_email'] ); // WPT add email
		update_post_meta( self::$donation_id, '_donor_firstname', $values['donor']['donor_firstname'] );
		update_post_meta( self::$donation_id, '_donor_lastname', $values['donor']['donor_lastname'] );
		update_post_meta( self::$donation_id, '_donor_website', $values['donor']['donor_website'] );
		update_post_meta( self::$donation_id, '_donor_address', $values['donor']['donor_address'] );
		update_post_meta( self::$donation_id, '_donor_zip', $values['donor']['donor_zip'] );
		update_post_meta( self::$donation_id, '_donor_town', $values['donor']['donor_town'] );
		update_post_meta( self::$donation_id, '_donor_logo', $values['donor']['donor_logo'] );
		update_post_meta( self::$donation_id, '_donor_country', $values['donor']['donor_country'] ); // WPT add country

		// And user meta to save time in future
		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), '_donor_email', $values['donor']['donor_email'] ); // WPT get email
			update_user_meta( get_current_user_id(), '_donor_firstname', $values['donor']['donor_firstname'] );
			update_user_meta( get_current_user_id(), '_donor_lastname', $values['donor']['donor_lastname'] );
			update_user_meta( get_current_user_id(), '_donor_website', $values['donor']['donor_website'] );
			update_user_meta( get_current_user_id(), '_donor_address', $values['donor']['donor_address'] );
			update_user_meta( get_current_user_id(), '_donor_zip', $values['donor']['donor_zip'] );
			update_user_meta( get_current_user_id(), '_donor_town', $values['donor']['donor_town'] );
			update_user_meta( get_current_user_id(), '_donor_logo', $values['donor']['donor_logo'] );
			update_post_meta( get_current_user_id(), '_donor_country', $values['donor']['donor_country'] ); // WPT get country
		}

		do_action( 'wpdonations_update_donation_data', self::$donation_id, $values );
	}

	/**
	 * Preview Step
	 */
	public static function preview() {
		global $wpdonations, $post;

		if ( self::$donation_id ) {

			$post = get_post( self::$donation_id );
			setup_postdata( $post );
			?>
			<form method="post" id="donation_preview">
				<input type="submit" name="edit_donation" class="button" value="<?php _e( '&larr; Edit donation', 'wpdonations' ); ?>" />
				<input type="submit" name="continue" id="donation_preview_submit_button" class="button" value="<?php echo apply_filters( 'submit_donation_step_preview_submit_text', __( 'Submit Donation &rarr;', 'wpdonations' ) ); ?>" />
				<input type="hidden" name="donation_id" value="<?php echo esc_attr( self::$donation_id ); ?>" />
				<input type="hidden" name="step" value="<?php echo esc_attr( self::$step ); ?>" />
				<input type="hidden" name="wpdonations_form" value="<?php echo self::$form_name; ?>" />

				<div class="donation_preview single_donation">
					<h1><?php _e( 'Confirm your', 'wpdonations' ); ?> <?php the_title(); ?></h1>
					<?php get_wpdonations_template_part( 'content-single', 'donation' ); ?>
				</div>
			</form>
			<?php
			
		if ( isset( $_GET['cancel'] ) && $_GET['cancel'] == 'true' ) {
			$update_donation                = array();
			$update_donation['ID']          = $_GET['donation_id'];
			$update_donation['post_status'] = 'preview';
			wp_update_post( $update_donation );
		}

			wp_reset_postdata();
		}
	}

	/**
	 * Preview Step Form handler
	 */
	public static function preview_handler() {
		if ( ! $_POST )
			return;

		// Edit = show submit form again
		if ( ! empty( $_POST['edit_donation'] ) ) {
			self::$step --;
		}
		// Continue = change donation status then show next screen
		if ( ! empty( $_POST['continue'] ) ) {

			$donation = get_post( self::$donation_id );

			if ( $donation->post_status == 'preview' ) {
				$update_donation                = array();
				$update_donation['ID']          = $donation->ID;
				$update_donation['post_status'] = 'pending';
				wp_update_post( $update_donation );
			}

			self::$step ++;
		}
	}

	/**
	 * Done Step
	 */
	public static function done() {
		
		get_wpdonations_template( 'donation-submitted.php', array( 'donation' => get_post( self::$donation_id ) ) );
	}

	/**
	 * Upload Image
	 */
	public static function upload_image( $field_key ) {

		/** WordPress Administration File API */
		include_once( ABSPATH . 'wp-admin/includes/file.php' );

		/** WordPress Media Administration API */
		include_once( ABSPATH . 'wp-admin/includes/media.php' );

		if ( isset( $_FILES[ $field_key ] ) && ! empty( $_FILES[ $field_key ] ) && ! empty( $_FILES[ $field_key ]['name'] ) ) {
			$file   = $_FILES[ $field_key ];

			if ( $_FILES[ $field_key ]["type"] != "image/jpeg" && $_FILES[ $field_key ]["type"] != "image/gif" && $_FILES[ $field_key ]["type"] != "image/png" )
    			throw new Exception( __( 'Logo needs to be jpg, gif or png.', 'wpdonations' ) );

			add_filter( 'upload_dir',  array( __CLASS__, 'upload_dir' ) );

			$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

			remove_filter('upload_dir', array( __CLASS__, 'upload_dir' ) );

			if ( ! empty( $upload['error'] ) ) {
				throw new Exception( $upload['error'] );
			} else {
				return $upload['url'];
			}
		}
	}

	/**
	 * Filter the upload directory
	 */
	public static function upload_dir( $pathdata ) {
		$subdir             = '/donation_images';
		$pathdata['path']   = str_replace( $pathdata['subdir'], $subdir, $pathdata['path'] );
		$pathdata['url']    = str_replace( $pathdata['subdir'], $subdir, $pathdata['url'] );
		$pathdata['subdir'] = str_replace( $pathdata['subdir'], $subdir, $pathdata['subdir'] );
		return $pathdata;
	}
}
