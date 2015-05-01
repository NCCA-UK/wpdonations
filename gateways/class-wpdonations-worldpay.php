<?php
// Exit if accessed directly
if( !defined( 'ABSPATH' ) )
	exit;

/**
 * WPdonations_WorldPay
 *
 * @extends WPdonations_Gateway
 *
 */
class WPdonations_WorldPay extends WPdonations_Gateway {

	/*
	 * Get all the options and constants
	 *
	 * [__construct description]
	 */
	public function __construct() {
		$this->gateway_id  			= 'worldpay';
		$this->gateway_name 		= __( 'WorldPay Form', 'wpdonations' );
		$this->icon 				= apply_filters( 'wpd_worldpay_icon', '' );
		$this->has_fields 			= false;
		$this->liveurl 				= 'https://secure.worldpay.com/wcc/purchase';
		$this->testurl 				= 'https://secure-test.worldpay.com/wcc/purchase';

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values
		$this->enabled				= get_option( 'wpdonations_worldpay_enabled' );
		$this->title 				= get_option( 'wpdonations_worldpay_title' );
		$this->description  		= get_option( 'wpdonations_worldpay_description' );
		$this->status				= get_option( 'wpdonations_worldpay_status' );
		$this->wplogo				= get_option( 'wpdonations_worldpay_wplogo' );
		$this->cardtypes			= get_option( 'wpdonations_worldpay_cardtypes' );
		$this->instId				= get_option( 'wpdonations_worldpay_instId' );
		$this->callbackPW			= get_option( 'wpdonations_worldpay_callbackPW' );
		$this->orderDesc			= get_option( 'wpdonations_worldpay_orderDesc' );
		$this->accid				= get_option( 'wpdonations_worldpay_accid' );
		$this->authMode				= get_option( 'wpdonations_worldpay_authMode' );
		$this->fixContact			= get_option( 'wpdonations_worldpay_fixContact' );
		$this->hideContact			= get_option( 'wpdonations_worldpay_hideContact' );
		$this->hideCurrency			= get_option( 'wpdonations_worldpay_hideCurrency' );
		$this->lang					= get_option( 'wpdonations_worldpay_lang' );
		$this->noLanguageMenu		= get_option( 'wpdonations_worldpay_noLanguageMenu' );
		$this->worldpaydebug		= get_option( 'wpdonations_worldpay_worldpaydebug' );
		$this->worldpayurlnotice	= get_option( 'wpdonations_worldpay_worldpayurlnotice' );
		$this->remoteid				= get_option( 'wpdonations_worldpay_remoteid' );
		$this->remotepw				= get_option( 'wpdonations_worldpay_remotepw' );
		$this->worldpaydebugemail	= get_option( 'wpdonations_worldpay_worldpaydebugemail' );
		
		// Hooks
		add_action( 'wpdonations_api_' . strtolower( get_class( $this ) ), array( $this, 'check_worldpay_response' ) );
		add_action( 'valid-worldpay-request', array( $this, 'successful_request' ) );
		add_action( 'wpdonations_receipt_worldpay', array( $this, 'receipt_page' ) );
		
		/**
		 * Subscriptions
		 * Check if the URL notice has been set to yes, 
		 * If it is not set then no subscriptions for you!
		 */
		if ( $this->worldpayurlnotice == 'yes' ) :
			if ( !isset( $this->remoteid ) || $this->remoteid == '' ) :
				$this->supports = array(
					'products',
					'subscriptions',
					'subscription_cancellation'
				);
			else :
				$this->supports = array(
					'products',
					'subscriptions',
					'subscription_cancellation'
				);

				// When a subscriber or store manager changes a subscription's status in the store, change the status with WorldPay
				add_action( 'cancelled_subscription_worldpay', array( $this, 'cancel_subscription_with_worldpay'), 10, 2 );

			endif;
		endif;
		
		/**
		 * We must notify the admin if Subscriptions and WorldPay are active
		 * The current instructions tell customers to set the return to be a dynamic value, 
		 * this won't work with subscriptions, the value at WorldPay has to be fixed.
		 */
		add_action( 'admin_notices', array( $this, 'worldpay_subscription_admin_notice' ) );
		
		parent::__construct();
	} // END __construct


	/**
	 * Initialise Gateway Settings
	 *
	 * (do we need to load defaults?)
	 */
	public function init_settings() {

		// Load form_field settings		
		$this->settings = $this->form_fields;
	}


	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * [init_form_fields description]
	 * @return [type]
	 */
	function init_form_fields() {
		$this->form_fields = array(
			array(
				'name'		=> 'wpdonations_worldpay_enabled',
				'label'		=> __( 'Enable/Disable', 'wpdonations_worldpay' ),
				'cb_label' 	=> __( 'Enable WorldPay', 'wpdonations_worldpay' ),
				'type' 		=> 'checkbox',
				'desc' 		=> '',
				'std' 		=> 'no',
				'class'     => 'gateway-settings gateway-settings-worldpay'
				),
			
			array(
				'name'		=> 'wpdonations_worldpay_title',
				'label'		=> __( 'Title', 'wpdonations_worldpay' ),
				'type' 		=> 'text',
				'desc' 		=> __( 'This controls the title which the user sees during checkout.', 'wpdonations_worldpay' ),
				'std' 		=> __( 'Credit Card via WorldPay', 'wpdonations_worldpay' ),
				'class'     => 'gateway-settings gateway-settings-worldpay'
				),
			
			array(
				'name'		=> 'wpdonations_worldpay_description',
				'label'		=> __( 'Description', 'wpdonations_worldpay' ),
				'type' 		=> 'textarea',
				'desc' 		=> __( 'This controls the description which the user sees during checkout.', 'wpdonations_worldpay' ),
				'std' 		=> 'Pay via Credit / Debit Card with WorldPay secure card processing.',
				'class'     => 'gateway-settings gateway-settings-worldpay'
				),

			array(
				'name'		=> 'wpdonations_worldpay_status',
				'label' 	=> __( 'Status', 'wpdonations_worldpay' ),
				'type' 		=> 'select',
				'options' 	=> array('live'=>'Live','testing'=>'Testing'),
				'desc' 		=> __( 'Set WorldPay Live/Testing Status.', 'wpdonations_worldpay' ),
				'std' 		=> 'testing',
				'class'     => 'gateway-settings gateway-settings-worldpay'
				),

			array(
				'name'		=> 'wpdonations_worldpay_wplogo',
				'label' 	=> __( 'WorldPay Logo', 'wpdonations_worldpay' ),
				'type' 		=> 'select',
				'options' 	=> array('yes'=>'Yes','no'=>'No'),
				'desc' 		=> __( 'Include the "Payments Powered by WorldPay" logo on the checkout.', 'wpdonations_worldpay' ),
				'std' 		=> 'no',
				'class'     => 'gateway-settings gateway-settings-worldpay'
				),

			array(
				'name'		=> 'wpdonations_worldpay_cardtypes',
				'label' 	=> __( 'Accepted Cards', 'wpdonations_worldpay' ), 
				'type' 		=> 'multiselect',
				'desc' 		=> __( 'Select which card types to accept.', 'wpdonations_worldpay' ), 
				'std' 		=> '',
				'options' 	=> array(
									'visa'				=> __( 'Visa', 'donation_manager' ),
									'mastercard'		=> __( 'MasterCard', 'donation_manager' ),
									'visa-debit'		=> __( 'Visa Debit', 'donation_manager' ),
									'visa-electron'		=> __( 'Visa Electron', 'donation_manager' ),
									'maestro'			=> __( 'Maestro', 'donation_manager' ),
									'american-express' 	=> __( 'American Express', 'donation_manager' ),
									'diners' 			=> __( 'Diners', 'donation_manager' ),
								),
				'class'     => 'gateway-settings gateway-settings-worldpay'
				),

			array(
				'name'		=> 'wpdonations_worldpay_instId',
				'label' 	=> __( 'Installation ID', 'wpdonations_worldpay' ),
				'type' 		=> 'text',
				'desc' 		=> __( 'This should have been supplied by WorldPay when you created your account.', 'wpdonations_worldpay' ),
				'std' 		=> '',
				'class'		=> 'gateway-settings gateway-settings-worldpay'
			),
				
			array(
				'name'		=> 'wpdonations_worldpay_callbackPW',
				'label' 	=> __( 'Payment Response password', 'wpdonations_worldpay' ),
				'type' 		=> 'text',
				'desc' 		=> __( 'You MUST set this value here and in your WorldPay Installation.', 'wpdonations_worldpay' ),
				'std' 		=> '',
				'class'     => 'gateway-settings gateway-settings-worldpay'
			),
				
			array(
				'name'		=> 'wpdonations_worldpay_remoteid',
				'label' 	=> __( 'Remote Administration Installation ID', 'wpdonations_worldpay' ),
				'type' 		=> 'text',
				'desc' 		=> __( '', 'wpdonations_worldpay' ),
				'std' 		=> '',
				'class'    	=> 'gateway-settings gateway-settings-worldpay'
			),
				
			array(
				'name'		=> 'wpdonations_worldpay_remotepw',
				'label' 	=> __( 'Remote Administration Installation Password', 'wpdonations_worldpay' ),
				'type' 		=> 'text',
				'desc' 		=> __( '', 'wpdonations_worldpay' ),
				'std' 		=> '',
				'class'     => 'gateway-settings gateway-settings-worldpay'
				),
				
			array(
				'name'		=> 'wpdonations_worldpay_orderDesc',
				'label' 	=> __( 'Donation Description', 'wpdonations_worldpay' ),
				'type' 		=> 'text',
				'desc' 		=> __( 'This is what appears on the payment screen when the customer lands at WorldPay and is also shown on statements and emails between your store and the shopper.', 'wpdonations_worldpay' ),
				'std' 		=> 'Donate to ' .  get_bloginfo('name'),
				'class'     => 'gateway-settings gateway-settings-worldpay'
			),
				
			array(
				'name'		=> 'wpdonations_worldpay_accid',
				'label' 	=> __( 'Payment Account ID', 'wpdonations_worldpay' ),
				'type' 		=> 'text',
				'desc' 		=> __( 'This specifies which account will receive the funds. Only add account details here if you are not using the default account to receive money, most people will leave this blank.', 'wpdonations_worldpay' ),
				'std' 		=> '',
				'class'     => 'gateway-settings gateway-settings-worldpay'
			),
				
			array(
				'name'		=> 'wpdonations_worldpay_authMode',
				'label' 	=> __( 'Authorisation Mode', 'wpdonations_worldpay' ),
				'type' 		=> 'select',
				'options' 	=> array('A'=>'Full Auth','E'=>'Pre Auth'),
				'desc' 		=> __( 'Enable Full Auth or Pre Auth, only change this if you know what you are doing - Pre Auth preauthorises the card but DOES NOT take the funds!', 'wpdonations_worldpay' ),
				'std' 		=> 'A',
				'class'     => 'gateway-settings gateway-settings-worldpay'
			),
				
			array(
				'name'		=> 'wpdonations_worldpay_fixContact',
				'label' 	=> __( 'Fix Customer Info', 'wpdonations_worldpay' ),
				'type' 		=> 'select',
				'options' 	=> array('yes'=>'Yes','no'=>'No'),
				'desc' 		=> __( 'If this is set to yes then the customer will not be able to change the information they entered on your site when they get to WorldPay', 'wpdonations_worldpay' ),
				'std' 		=> 'yes',
				'class'     => 'gateway-settings gateway-settings-worldpay'
			),
				
			array(
				'name'		=> 'wpdonations_worldpay_hideContact',
				'label' 	=> __( 'Hide Customer Info', 'wpdonations_worldpay' ),
				'type' 		=> 'select',
				'options' 	=> array('yes'=>'Yes','no'=>'No'),
				'desc' 		=> __( 'If this is set to yes then the customer will not be able to see the information they entered on your site when they get to WorldPay', 'wpdonations_worldpay' ),
				'std' 		=> 'yes',
				'class'     => 'gateway-settings gateway-settings-worldpay'
			),
				
			array(
				'name'		=> 'wpdonations_worldpay_hideCurrency',
				'label' 	=> __( 'Hide Currency', 'wpdonations_worldpay' ),
				'type' 		=> 'select',
				'options' 	=> array('yes'=>'Yes','no'=>'No'),
				'desc' 		=> __( 'If this is set to no then the customer will be able to change the currency at WorldPay. Exchange rates are set by WorldPay.', 'wpdonations_worldpay' ),
				'std' 		=> 'yes',
				'class'     => 'gateway-settings gateway-settings-worldpay'
			),
				
			array(
				'name'		=> 'wpdonations_worldpay_lang',
				'label' 	=> __( 'Language', 'wpdonations_worldpay' ),
				'type' 		=> 'select',
				'options' 	=> array('yes'=>'Yes','no'=>'No'),
				'desc' 		=> __( 'Set a default language shown at WorldPay. If you set the \'Remove Language Menu\' option to NO then this setting determines the Worldpay language.', 'wpdonations_worldpay' ),
				'std' 		=> 'yes',
				'class'     => 'gateway-settings gateway-settings-worldpay'
			),
				
			array(
				'name'		=> 'wpdonations_worldpay_noLanguageMenu',
				'label' 	=> __( 'Remove Language Menu', 'wpdonations_worldpay' ),
				'type' 		=> 'select',
				'options' 	=> array('yes'=>'Yes','no'=>'No'),
				'desc' 		=> __( 'This suppresses the display of the language menu at WorldPay', 'wpdonations_worldpay' ),
				'std' 		=> 'yes',
				'class'     => 'gateway-settings gateway-settings-worldpay'
			),
				
			array(
				'name'		=> 'wpdonations_worldpay_worldpaydebug',
				'label' 	=> __( 'Enable Debugging Email', 'wpdonations_worldpay' ),
				'type' 		=> 'select',
				'options' 	=> array('yes'=>'Yes','no'=>'No'),
				'desc' 		=> __( 'Send the site admin a debugging email with the form contents', 'wpdonations_worldpay' ),
				'std' 		=> 'yes',
				'class'     => 'gateway-settings gateway-settings-worldpay'
			),
				
			array(
				'name'		=> 'wpdonations_worldpay_worldpaydebugemail',
				'label' 	=> __( 'Debugging Email Address', 'wpdonations_worldpay' ),
				'type' 		=> 'text',
				'desc' 		=> __( 'Email address to send the debugging info to, uses the site admin address by default.', 'wpdonations_worldpay' ),
				'std' 		=> get_bloginfo( 'admin_email' ),
				'class'     => 'gateway-settings gateway-settings-worldpay'
			),
				
			array(
				'name'		=> 'wpdonations_worldpay_worldpayurlnotice',
				'label' 	=> __( 'WorldPay URL Notice', 'wpdonations_worldpay' ),
				'type' 		=> 'select',
				'options' 	=> array('yes'=>'Yes','no'=>'No'),
				'desc' 		=> __( 'In previous versions of the WorldPay gateway the instructions were to set the return url to a dynamic value, with subscriptions this is no longer possible. If the subscriptions plugin is active then this MUST be set to yes and the Payment Response URL must be set to ' .get_bloginfo( 'url' ). '/wp-content/plugins/wpdonations/gateways/worldpay/wpcallback.php in your WorldPay Installation Administration. Once you have made the changes you should place a test transaction to confirm everything is working.', 'wpdonations_worldpay' ),
				'std' 		=> 'no',
				'class'     => 'gateway-settings gateway-settings-worldpay'
			)
		);
	} // END init_form_fields
	
	
	/**
	 * frontend_scripts function.
	 *
	 * @access public
	 * @return void
	 */
	public function frontend_scripts() {
		wp_enqueue_script(
			'wpdonations-worldpay',
			WPDONATIONS_PLUGIN_URL . '/assets/js/worldpay-form.js',
			array( 'jquery' ),
			'1.0',
			false
		);
	}


	/**
	 * Returns the plugin's url without a trailing slash
	 *
	 * [get_plugin_url description]
	 * @return [type]
	 */
	public function get_plugin_url() {
		return str_replace( '/gateways', '', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
	}


	/**
	 * Add selected card icons to payment method label, defaults to Visa/MC/Amex/Discover
	 *
	 * [get_icon description]
	 * @return [type]
	 */
	public function get_icon() {
		$icon = '';
		
		if ( $this->icon ) :
			if ( get_option('wpdonations_force_ssl_checkout')=='no' ) :
				// use icon provided by filter
				$icon = '<img src="' . esc_url( $this->icon ) . '" alt="' . esc_attr( $this->title ) . '" />';			
			else :
				// use icon provided by filter
				$icon = '<img src="' . esc_url( $wpdonations->force_ssl( $this->icon ) ) . '" alt="' . esc_attr( $this->title ) . '" />';		
			endif;

		elseif ( ! empty( $this->cardtypes ) ) :
			if ( get_option('wpdonations_force_ssl_checkout')=='no' ) :
				// display icons for the selected card types
				foreach ( $this->cardtypes as $card_type ) {
					$icon .= '<img src="' . 
								esc_url( $this->get_plugin_url() . '/images/card-' . 
								strtolower( str_replace(' ','-',$card_type) ) . '.png' ) . '" alt="' . 
								esc_attr( strtolower( $card_type ) ) . '" />';
				}			
			else :
				// display icons for the selected card types
				foreach ( $this->cardtypes as $card_type ) {

					$icon .= '<img src="' . 
								esc_url( $wpdonations->force_ssl( $this->get_plugin_url() ) . '/images/card-' . 
								strtolower( str_replace(' ','-',$card_type) ) . '.png' ) . '" alt="' . 
								esc_attr( strtolower( $card_type ) ) . '" />';
				}	
			endif;
		else :
			if ( get_option('wpdonations_force_ssl_checkout')=='no' ) :
				// use icon provided by filter
				$icon = '<img src="' . esc_url( $this->get_plugin_url() . '/images/cards.png' ) . '" alt="' . esc_attr( $this->title ) . '" />';			
			else :
				// use icon provided by filter
				$icon = '<img src="' . esc_url( $wpdonations->force_ssl( $this->get_plugin_url() . '/images/cards.png' ) ) . '" alt="' . esc_attr( $this->title ) . '" />';		
			endif;
		endif;

		/**
		 * Add Payments Powered By WorldPay logo
		 */
		if ( $this->wplogo == 'yes' ) : 
			if ( get_option('wpdonations_force_ssl_checkout')=='no' ) :
				// use icon provided by filter
				$icon = '<img src="' . esc_url( $this->get_plugin_url() . '/images/poweredByWorldPay.png' ) . '" alt="Payments Powered By WorldPay" />' . $icon;			
			else :
				// use icon provided by filter
				$icon = '<img src="' . esc_url( $wpdonations->force_ssl( $this->get_plugin_url() . '/images/poweredByWorldPay.png' ) ) . '" alt="Payments Powered By WorldPay" />' . $icon;		
			endif;
		endif;

		return apply_filters( 'wpdonations_gateway_icon', $icon, $this->id );
	}


	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * [admin_options description]
	 * @return [type]
	 */
	public function admin_options() {
		?>
		<h3><?php _e('WorldPay Form', 'wpdonations_worldpay'); ?></h3>
		<p><?php _e('The WorldPay Form gateway works by sending the user to <a href="http://www.worldpay.com">WorldPay</a> to enter their payment information.', 'wpdonations_worldpay'); ?></p>
		<table class="form-table">
		<?php
			// Generate the HTML for the settings form.
			$this->generate_settings_html();
		?>
		</table><!--/.form-table-->
		<?php
	} // END admin_options


	/**
	 * There are no payment fields for WorldPay, but we want to show the description if set.
	 *
	 * [payment_fields description]
	 * @return [type]
	 */
	function payment_fields() {
		if( $this->description ) echo wpautop( wptexturize( $this->description ) );
	} // END payment_fields


	/**
	 * Generates a URL so that a customer can cancel their (unpaid - pending) donation.
	 *
	 * @return string
	 */
	public function get_cancel_donation_url() {
		$donation_id = $_REQUEST[ 'donation_id' ];

		return add_query_arg( array( 'cancel' => 'true', 'donation_id' => $donation_id, 'step' => $_REQUEST['step'] ), get_permalink() );
	}
	
	/**
	 * Generate the form button
	 *
	 * [generate_worldpay_form description]
	 * @param  [type] $order_id
	 * @return [type]
	 */
	public function generate_worldpay_form( $donation_id ) {

		// Check if donation is recurring
		if( get_post_meta( $donation_id, '_recurrence_period', true ) != null ) {
			$recurrence_period = get_post_meta( $donation_id, '_recurrence_period', true );
		}
		else {
			$recurrence_period = '';
		}
		
		// What mode are we in?
		if ( $this->status == 'testing' ):
			$worldpayform_adr = $this->testurl;
			$testMode		  = '<input type="hidden" name="testMode" value="100">' . "\r\n";
		else :
			$worldpayform_adr = $this->liveurl;
			$testMode		  = '';
		endif;
		
		// Get donation details
		$this->amount 		= get_post_meta( $donation_id, '_donation_amount', true );
		$this->currency		= get_option( 'wpdonations_currency' );
		$this->firstname 	= get_post_meta( $donation_id, '_donor_firstname', true );
		$this->lastname 	= get_post_meta( $donation_id, '_donor_lastname', true );
		$this->address 		= get_post_meta( $donation_id, '_donor_address', true );
		$this->town 		= get_post_meta( $donation_id, '_donor_town', true );
		$this->zip 			= get_post_meta( $donation_id, '_donor_zip', true );
		$this->country 		= get_post_meta( $donation_id, '_donor_country', true );
		$this->email 		= get_post_meta( $donation_id, '_donor_email', true );

		// Define payment URLs
		$callbackurl   = '';
		$callbackurl   = $this->get_plugin_url() . '/gateways/worldpay/wpcallback.php';
		//$httpreplace   = array( "http://", "https://" );
		//$callbackurl   = str_replace( $httpreplace, "", $callbackurl );
		$successurl    = add_query_arg( array( 'success' => 'true', 'donation_id' => $donation_id, 'step' => $_REQUEST['step'] + 1 ), get_permalink() );
		$failureurl    = $this->get_cancel_donation_url();
		
		// Build the form
		$worldpayform  = $testMode;
		$worldpayform .= '<input type="hidden" name="instId" value="'.$this->instId.'">' . "\r\n";
		$worldpayform .= '<input type="hidden" name="cartId" value="' .$donation_id.'">' . "\r\n";
		$worldpayform .= '<input type="hidden" name="amount" value="'.$this->amount.'">' . "\r\n";
		$worldpayform .= '<input type="hidden" name="currency" value="'.$this->currency.'">' . "\r\n";
		$worldpayform .= '<input type="hidden" name="desc" value="'.$this->description.'">' . "\r\n";
		$worldpayform .= '<input type="hidden" name="name" value="' .$this->firstname. ' ' .$this->lastname. '">' . "\r\n";
		$worldpayform .= '<input type="hidden" name="address1" value="' .$this->address. '">' . "\r\n";
		$worldpayform .= '<input type="hidden" name="town" value="' .$this->town. '">' . "\r\n";
		$worldpayform .= '<input type="hidden" name="postcode" value="' .$this->zip. '">' . "\r\n";
		$worldpayform .= '<input type="hidden" name="country" value="' .$this->country. '">' . "\r\n";
		$worldpayform .= '<input type="hidden" name="email" value="' .$this->email. '">' . "\r\n";

		if ( $this->fixContact == 'yes' ) :
			$worldpayform .= '<input type="hidden" name="fixContact" value="">' . "\r\n";
		endif;

		if ( $this->hideContact == 'yes' ) :
			$worldpayform .= '<input type="hidden" name="hideContact">' . "\r\n";
		endif;

		if ( $this->accid != '' || isset($this->accid) ) :
			$worldpayform .= '<input type="hidden" name="accId1" value="'.$this->accid.'">' . "\r\n";
		endif;

		if ( $this->authMode == 'A' || $this->authMode == 'E' ) :
			$worldpayform .= '<input type="hidden" name="authMode" value="'.$this->authMode.'">' . "\r\n";
		endif;

		if ( $this->hideCurrency == 'yes' ) :
			$worldpayform .= '<input type="hidden" name="hideCurrency">' . "\r\n";
		endif;

		if ( $this->lang != '' || isset($this->lang) ) :
			$worldpayform .= '<input type="hidden" name="lang" value="'.$this->lang.'">' . "\r\n";
		endif;

		if ( $this->noLanguageMenu == 'yes' ) :
			$worldpayform .= '<input type="hidden" name="noLanguageMenu">' . "\r\n";
		endif;
		
		$worldpayform .= '<input type="hidden" name="MC_callback" 			value="'.$callbackurl.'">' . "\r\n";
		$worldpayform .= '<input type="hidden" name="CM_SuccessURL" 		value="'.$successurl.'">' . "\r\n";
		$worldpayform .= '<input type="hidden" name="CM_FailureURL" 		value="'.$failureurl.'">' . "\r\n";
		$worldpayform .= '<input type="hidden" name="CM_order" 				value="'.$donation_id.'">' . "\r\n";
		$worldpayform .= '<input type="hidden" name="MC_transactionNumber" 	value="1">' . "\r\n";
		
		if ( $this->worldpaydebug == 'yes' ) :
			wp_mail( $this->worldpaydebugemail,'Worldpay Debug Message - Form Contents ' . $donation_id, $worldpayform );
		endif;
		
		// Enqueue script to submit form
		$this->frontend_scripts();
	
		// Submit the form
		return	'<form action="'.$worldpayform_adr.'" method="post" id="worldpay_payment_form">
					' . $worldpayform . '
					<input type="submit" class="button" id="submit_worldpay_payment_form" value="'.__('Pay via WorldPay', 'wpdonations_worldpay').'" /><a class="button right" href="'.$this->get_cancel_donation_url().'">'.__('Cancel donation','wpdonations_worldpay').'</a>
				</form>';

	} // END generate_worldpay_form


	/**
	 * Pay for a donation action
	 *
	 * [pay_for_donation description]
	 * @param  [type] $donation_id
	 * @return [type]
	 */
	public function pay_for_donation( $donation_id ) {

		// Output the form
		echo $this->generate_worldpay_form( $donation_id );

		// Process the payment
		return true;

	} // END process_payment


	/**
	 * receipt_page
	 *
	 * [receipt_page description]
	 * @param  [type] $donation
	 * @return [type]
	 */
	function receipt_page( $donation ) {

		echo '<p>'.__('Thank you for your donation, please click the button below to pay with WorldPay.', 'wpdonations_worldpay').'</p>';
		echo $this->generate_worldpay_form( $donation_id );

	} // END receipt_page

	/**
	 * Check for WorldPay Response
	 *
	 * [check_worldpay_response description]
	 * @return [type]
	 */
	function check_worldpay_response() {
		if ( isset($_REQUEST["donation"]) ) :	
			$donation 				= $_REQUEST["donation"];
			$worldpaycrypt_b64		= get_post_meta( $donation, '_worldpay_crypt', TRUE );
			$worldpaycrypt_b64 		= base64_decode( $worldpaycrypt_b64 );
			$worldpaycrypt_b64 		= $this->worldpaysimpleXor( $worldpaycrypt_b64, $this->callbackPW );
			$worldpay_return_values = $this->getTokens( $worldpaycrypt_b64 );

			if ( isset($worldpay_return_values['transId']) ) :
				do_action("valid-worldpay-request", $worldpay_return_values);
			endif;
		endif;

		wp_redirect( $this->get_return_url( $donation ) );
		exit;
	} // END check_worldpay_response


	/**
	 * Successful Payment!
	 *
	 * [successful_request description]
	 * @param  [type] $worldpay_return_values
	 * @return [type]
	 */
	function successful_request( $worldpay_return_values ) {
		global $wpdb;
		
		$donation = new WPdonation( (int) $worldpay_return_values['donation'] );
		
		/**
		 * Make sure the donation notes contain the FuturePayID
		 * and add it as post_meta so we can find it easily when WorldPay sends 
		 * updates about payments / cancellations etc
		 */
		$donationNotes  = ''; 
		if ( class_exists( 'WPD_Subscriptions' ) && WPD_Subscriptions_Donation::donation_contains_subscription( $donation->id ) ) :
			$donationNotes .=	'<br /><!-- FUTURE PAY-->';
			$donationNotes .=	'<br />FuturePayID : ' 	. $worldpay_return_values['futurePayId'];
			$donationNotes .=	'<br /><!-- FUTURE PAY-->';
			update_post_meta( $donation->id, '_futurepayid', $worldpay_return_values['futurePayId'] );
		endif;

		$donationNotes .=	'<br />transId : ' 			. $worldpay_return_values['transId'];
		$donationNotes .=	'<br />transStatus : ' 		. $worldpay_return_values['transStatus'];
		$donationNotes .=	'<br />transTime : '		. $worldpay_return_values['transTime'];
		$donationNotes .=	'<br />authAmount : ' 		. $worldpay_return_values['authAmount'];
		$donationNotes .=	'<br />authCurrency : ' 	. $worldpay_return_values['authCurrency'];
		$donationNotes .=	'<br />authAmountString : ' . $worldpay_return_values['authAmountString'];
		$donationNotes .=	'<br />rawAuthMessage : ' 	. $worldpay_return_values['rawAuthMessage'];
		$donationNotes .=	'<br />rawAuthCode : ' 		. $worldpay_return_values['rawAuthCode'];
		$donationNotes .=	'<br />cardType : ' 		. $worldpay_return_values['cardType'];
		$donationNotes .=	'<br />countryMatch : ' 	. $worldpay_return_values['countryMatch'];
		$donationNotes .=	'<br />AVS : ' 				. $worldpay_return_values['AVS'];
		
		$donation->add_donation_note( __('WorldPay payment completed.' . $donationNotes, 'wpdonations_worldpay') );
		
		if ( $this->worldpaydebug == 'yes' ) :
			$debugcontent = $donationNotes . '<br />transactionNumber' . 
							$worldpay_return_values['MC_transactionNumber'] . '<br />' .
							'futurePayStatusChange' . $worldpay_return_values['futurePayStatusChange'];
							
			wp_mail( $this->worldpaydebugemail,'Worldpay Debug Message - Returned Values',$debugcontent );
		endif;
		
		/**
		 * Check MC_transactionNumber
		 * if this is 1 then this is either the first transaction for a subscription
		 * or the only transction for a none subscription donation
		 */
		if ( $worldpay_return_values['MC_transactionNumber'] == '1' ) :
		
			// Normal transaction at the front end
			$donation->payment_complete();
			wp_redirect( $this->get_return_url( $donation ) );
			exit;
			
		endif;

	} // END successful_request

	function worldpay_subscription_admin_notice() {
		
		if ( $this->worldpayurlnotice != 'yes' && class_exists( 'WPD_Subscriptions' ) ) :
?>			
		<div id="message" class="updated warning">
		<p>You MUST confirm that you have set the WorldPay Payment Response URL to <?php echo get_bloginfo( 'url' ); ?>/wp-content/plugins/wpdonations/gateways/worldpay/wpcallback.php in your WorldPay Installation Administration.</p> 
		<p>Once the WorldPay Payment Response URL is set correctly then you should edit the WorldPay settings in WPDonations and change the WorldPay URL Notice to yes</p>
		<p>Once you have made the changes you should place a test transaction to confirm everything is working.</p>
		</div>
<?php				
		endif;
		
	}

	/**
	 * [base64Decode description]
	 * @param  [type] $scrambled [description]
	 * @return [type]            [description]
	 */
	function base64Decode($scrambled) {
		// Initialise output variable
		$output = "";

		// Fix plus to space conversion issue
		$scrambled = str_replace(" ", "+", $scrambled);

		// Do decoding
		$output = base64_decode($scrambled);

		// Return the result
		return $output;
	} // END base64Decode

	/**
	 * A Simple Xor encryption algorithm
	 *
	 * [worldpaysimpleXor description]
	 * @param  [type] $text [description]
	 * @param  [type] $key  [description]
	 * @return [type]       [description]
	 */
	function worldpaysimpleXor($text, $key) {
	// Initialise key array
		$key_ascii_array = array();
	
		// Initialise output variable
		$output = "";
	
		// Convert $key into array of ASCII values
		for($i = 0; $i < strlen($key); $i++){
			$key_ascii_array[$i] = ord(substr($key, $i, 1));
		}

		// Step through string a character at a time
		for($i = 0; $i < strlen($text); $i++) {
			// Get ASCII code from string, get ASCII code from key (loop through with MOD), XOR the
			// two, get the character from the result
			$output .= chr(ord(substr($text, $i, 1)) ^ ($key_ascii_array[$i % strlen($key)]));
		}

		// Return the result
		return $output;
	} // END simpleXor	

	/**
	 * A convenience function that extracts the values from the query string.
	 * Works even if one of the values is a URL containing the & or = signs.
	 */
	function getTokens($query_string) {
		// List the possible tokens
		$tokens = array(
				'donation',
				'transId',
				'transStatus',
				'transTime',
				'authAmount',
				'authCurrency',
				'authAmountString',
				'rawAuthMessage',
				'rawAuthCode',
				'callbackPW',
				'cardType',
				'countryMatch',
				'AVS',
				'MC_transactionNumber',
				'futurePayId',
				'futurePayStatusChange'
			);

		// Initialise arrays
		$output = array();
		$tokens_found = array();

		// Get the next token in the sequence
		for ($i = count($tokens) - 1; $i >= 0; $i--){
			// Find the position in the string
			$start = strpos($query_string, $tokens[$i]);

			// If token is present record its position and name
			if ($start !== false){
				$tokens_found[$i]->start = $start;
				$tokens_found[$i]->token = $tokens[$i];
			}
		}

		// Sort in order of position
		sort($tokens_found);

		// Go through the result array, getting the token values
		for ($i = 0; $i < count($tokens_found); $i++){
		// Get the start point of the value
			$valueStart = $tokens_found[$i]->start + strlen($tokens_found[$i]->token) + 1;

			// Get the length of the value
			if ($i == (count($tokens_found) - 1)) {
				$output[$tokens_found[$i]->token] = substr($query_string, $valueStart);
			} else {
				$valueLength = $tokens_found[$i +1 ]->start - $tokens_found[$i]->start - strlen($tokens_found[$i]->token) - 2;
				$output[$tokens_found[$i]->token] = substr($query_string, $valueStart, $valueLength);
			}
		}

		// Return the output array
		return $output;

	} // END getTokens

	/**
	 * Subscription Cancellation
	 * 
	 * When a store manager or user cancels a subscription in the store, also cancel the subscription with WorldPay. 
	 */
	function cancel_subscription_with_worldpay( $donation, $product_id ) {	
		$profile_id = get_post_meta( $donation->id, '_futurepayid', TRUE );

		// Make sure a subscriptions status is active with PayPal
		$response = $this->change_subscription_status( $profile_id, 'Cancel' );
		// $item = WPD_Subscriptions_Donation::get_item_by_product_id( $donation, $product_id );

		if ( isset( $response['ACK'] ) && $response['ACK'] == 'Success' )
			$donation->add_donation_note( sprintf( __( 'Subscription "%s" cancelled with PayPal', WPD_Subscriptions::$text_domain ), $item['name'] ) );
	}

	/**
	 * Cancel Subscription via iAdmin
	 */
	function change_subscription_status( $profile_id, $new_status ) {

		if ( $this->status = 'testing' ) :
			$curlurl = 'https://secure-test.worldpay.com/wcc/iadmin';
		else :
			$curlurl = 'https://secure.worldpay.com/wcc/iadmin';
		endif;

		switch( $new_status ) {
			case 'Cancel' :
				$new_status_string = __( 'cancelled', WPD_Subscriptions::$text_domain );

				$api_request = 'instId=' . urlencode( $this->remoteid )
							.  '&authPW=' . urlencode( $this->remotepw )
							.  '&futurePayId=' . $profile_id
							.  '&op-cancelFP=';

				break;
		}

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $curlurl );
		curl_setopt( $ch, CURLOPT_VERBOSE, 1 );

		// Set the API parameters for this transaction
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $api_request );

		// Request response from WorldPay
		$response = curl_exec( $ch );

		if( $response != 1 ) :

			$content = 'There was a problem cancelling the subscription with the FuturePay ID ' . $profile_id . '. WorldPay returned the error ' . print_r( $response,TRUE ) . '. Please contact WorldPay for more information about this error.';

			mail( $this->worldpaydebugemail ,'WorldPay FuturePay Cancellation Failure', $content );

		endif;

		curl_close( $ch );
	}

} // END CLASS

return new WPdonations_WorldPay();
