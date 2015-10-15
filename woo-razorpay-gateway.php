<?php
/*
Plugin Name: WooCommerce Razorpay Gateway
Plugin URI: http://www.kdclabs.com/?p=181
Description: Online Payments for India. Razorpay is the simplest way to collect payments online. Extends WooCommerce with an Razorpay gateway.
Version: 1.0.0
Author: _KDC-Labs
Author URI: http://www.kdclabs.com
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Donate link: https://www.payumoney.com/webfront/index/kdclabs
Contributors: kdclabs, vachan
*/

add_action('plugins_loaded', 'woocommerce_gateway_razorpay_init', 0);
define('razorpay_img', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function woocommerce_gateway_razorpay_init() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	/**
 	 * Gateway class
 	 */
	class WC_Gateway_Razorpay extends WC_Payment_Gateway {

	     /**
         * Make __construct()
         **/	
		public function __construct(){
			
			$this->id 					= 'razorpay'; // ID for WC to associate the gateway values
			$this->method_title 			= 'Razorpay'; // Gateway Title as seen in Admin Dashboad
			$this->method_description	= 'Razorpay is the simplest way to collect payments online.'; // Gateway Description as seen in Admin Dashboad
			$this->has_fields 			= false; // Inform WC if any fileds have to be displayed to the visitor in Frontend 
			
			$this->init_form_fields();	// defines your settings to WC
			$this->init_settings();		// loads the Gateway settings into variables for WC
						
			// Special settigns if gateway is on Test Mode
			if ( $this->settings['test_mode'] == 'test' ) {
				$test_title 			= ' [TEST MODE]'.
				$test_description 	= '<br/><br/><u>Test Mode is <strong>ACTIVE</strong>, use following Credit Card details:-</u><br/>'."\n"
									.'Test Card Name: <strong><em>any name</em></strong><br/>'."\n"
									.'Test Card Number: <strong>4111 1111 1111 1111</strong><br/>'."\n"
									.'Test Card CVV: <strong><em>123</em></strong><br/>'."\n"
									.'Test Card Expiry: <strong><em>any valid date</em></strong><br/>';			
				$key_id				= $this->settings['key_id_test'];
				$key_secret			= $this->settings['key_secret_test'];
			} else {
				$test_ttitle		='';
				$test_description	='';
				$key_id				= $this->settings['key_id'];
				$key_secret			= $this->settings['key_secret'];
			} //END-{else}-testmode=yes

			$this->title 			= $this->settings['title'].$test_title; // Title as displayed on Frontend
			$this->description 		= $this->settings['description'].$test_description; // Description as displayed on Frontend
			if ( $this->settings['show_logo'] != "no" ) { // Check if Show-Logo has been allowed
				$this->icon 	= razorpay_img . $this->settings['show_logo'] . '.png';
			}
            $this->key_id 			= $key_id;
            $this->key_secret 		= $key_secret;
            $this->liveurl 			= 'https://checkout.razorpay.com/v1/checkout.js';

            $this->msg['message']	= '';
            $this->msg['class'] 		= '';
			
			add_action('init', array(&$this, 'check_razorpay_response'));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_razorpay_response')); //update for woocommerce >2.0

            if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) ); //update for woocommerce >2.0
                 } else {
                    add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) ); // WC-1.6.6
                }
            add_action('woocommerce_receipt_razorpay', array(&$this, 'receipt_page'));			

		} //END-__construct
		
        /**
         * Initiate Form Fields in the Admin Backend
         **/
		function init_form_fields(){

			$this->form_fields = array(
				// Activate the Gateway
				'enabled' => array(
					'title' 			=> __('Enable/Disable:', 'razorpay'),
					'type' 			=> 'checkbox',
					'label' 			=> __('Enable Razorpay', 'razorpay'),
					'default' 		=> 'no',
					'description' 	=> 'Show in the Payment List as a payment option'
				),
				// Title as displayed on Frontend
      			'title' => array(
					'title' 			=> __('Title:', 'razorpay'),
					'type'			=> 'text',
					'default' 		=> __('Online Payments', 'razorpay'),
					'description' 	=> __('This controls the title which the user sees during checkout.', 'razorpay'),
					'desc_tip' 		=> true
				),
				// Description as displayed on Frontend
      			'description' => array(
					'title' 			=> __('Description:', 'razorpay'),
					'type' 			=> 'textarea',
					'default' 		=> __('Pay securely by Credit or Debit card or internet banking through Razorpay.', 'razorpay'),
					'description' 	=> __('This controls the description which the user sees during checkout.', 'razorpay'),
					'desc_tip' 		=> true
				),
				// LIVE Key-ID
      			'key_id' => array(
					'title' 			=> __('Key ID:', 'razorpay'),
					'type' 			=> 'text',
					'description' 	=> __('Generated from "API Keys" section of Razorpay Dashboard. "LIVE" Key ID'),
					'desc_tip' 		=> true
				),
  				// LIVE Key-Secret
    			'key_secret' => array(
					'title' 			=> __('Key Secret:', 'razorpay'),
					'type' 			=> 'text',
					'description' 	=> __('Generated from "API Keys" section of Razorpay Dashboard. "LIVE" Key Secret'),
					'desc_tip' 		=> true
                ),
				// TEST Key-ID
      			'key_id_test' => array(
					'title' 			=> __('[TEST] Key ID:', 'razorpay'),
					'type' 			=> 'text',
					'description' 	=> __('Generated from "API Keys" section of Razorpay Dashboard. "TEST" Key ID'),
					'desc_tip' 		=> true
				),
  				// TEST Key-Secret
      			'key_secret_test' => array(
					'title' 			=> __('[TEST] Key Secret:', 'razorpay'),
					'type' 			=> 'text',
					'description' 	=> __('Generated from "API Keys" section of Razorpay Dashboard. "TEST" Key Secret'),
					'desc_tip' 		=> true
                ),
  				// Mode of Transaction
      			'test_mode' => array(
					'title' 			=> __('Mode:', 'kdc'),
					'type' 			=> 'select',
					'label' 			=> __('Razorpay Tranasction Mode.', 'kdc'),
					'options' 		=> array('test'=>'Test Mode','live'=>'Live Mode'),
					'default' 		=> 'test',
					'description' 	=> __('Mode of Razorpay activities'),
					'desc_tip' 		=> true
                ),
  				// Page for Redirecting after Transaction
      			'redirect_page' => array(
					'title' 			=> __('Return Page'),
					'type' 			=> 'select',
					'options' 		=> $this->razorpay_get_pages('Select Page'),
					'description' 	=> __('URL of success page', 'razorpay'),
					'desc_tip' 		=> true
                ),
  				// Show Logo on Frontend
      			'show_logo' => array(
					'title' 			=> __('Show Logo:', 'razorpay'),
					'type' 			=> 'select',
					'label' 			=> __('Enable Razorpay TEST Transactions.', 'razorpay'),
					'options' 		=> array('no'=>'No Logo','logo_dark'=>'Logo - Dark','logo_light'=>'Logo - light','logo_icon'=>'Only ICON'),
					'default' 		=> 'no',
					'description' 	=> __('Logo - Dark: <img src="'. razorpay_img . 'logo_dark.png" height="24px" /><br/>' . "\n"
										.'Logo - Light: <img src="'. razorpay_img . 'logo_light.png" height="24px" /><br/>' . "\n"
										.'Only ICON: <img src="'. razorpay_img . 'logo_icon.png" height="24px" /><br/>' . "\n", 'razorpay'),
					'desc_tip' 		=> false
                )
			);

		} //END-init_form_fields
		
        /**
         * Admin Panel Options
         * - Show info on Admin Backend
         **/
		public function admin_options(){
			echo '<h3>'.__('Razorpay', 'razorpay').'</h3>';
			echo '<p>'.__('Online Payments for India. Razorpay is the simplest way to collect payments online.').'</p>';
			echo '<p><small><strong>'.__('Confirm your Mode: Is it LIVE or TEST.').'</strong></small></p>';
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
			echo '</table>';
		} //END-admin_options

        /**
         *  There are no payment fields, but we want to show the description if set.
         **/
		function payment_fields(){
			if( $this->description ) {
				echo wpautop( wptexturize( $this->description ) );
			}
		} //END-payment_fields
		
        /**
         * Receipt Page
         **/
		function receipt_page($order){
			echo '<p><strong>' . __('Thank you for your order.', 'razorpay').'</strong>' . __('The payment page will open soon.', 'razorpay').'</p>';
			echo $this->generate_razorpay_form($order);
		} //END-receipt_page
    
        /**
         * Generate button link
         **/
		function generate_razorpay_form($order_id){
			global $woocommerce;
			$order = new WC_Order( $order_id );

			// Redirect URL
			if ( $this->redirect_page_id == '' || $this->redirect_page == 0 ) {
				$redirect_url = get_site_url() . "/";
			} else {
				$redirect_url = get_permalink( $this->redirect_page );
			}
			// Redirect URL : For WooCoomerce 2.0
			if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				$redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
			}

            $productinfo = "Order $order_id";

            $razorpay_args = array(
              'key' 			=> $this->key_id,
              'name' 		=> get_bloginfo('name'),
              'amount' 		=> $order->order_total*100,
              'currency'	=> get_woocommerce_currency(),
              'description' => $productinfo,
              'prefill' 		=> array(
			  					'name' 		=> $order->billing_first_name." ".$order->billing_last_name,
			  					'email' 		=> $order->billing_email,
			  					'contact' 	=> $order->billing_phone
			  					),
              'notes' 		=> array( 'woocommerce_order_id' => $order_id )
            );

            $json = json_encode($razorpay_args);

            $html = <<<EOT
<script src="{$this->liveurl}"></script>
<script>
    var data = $json;
</script>
<form name='razorpayform' action="$redirect_url" method="POST">
    <input type="hidden" name="merchant_order_id" value="$order_id">
    <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
</form>
<script>
    data.backdropClose = false;
    data.handler = function(payment){
      document.getElementById('razorpay_payment_id').value = 
        payment.razorpay_payment_id;
      document.razorpayform.submit();
    };
    var razorpayCheckout = new Razorpay(data);
    razorpayCheckout.open();
</script>
<p>
<button class="button alt" onclick="razorpayCheckout.open();" id="btn-razorpay">Pay Now</button>
<button class="button alt" onclick="document.razorpayform.submit()">Cancel</button>
</p>

EOT;
            return $html;
		} //END-generate_razorpay_form

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
			global $woocommerce;
            $order = new WC_Order($order_id);
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) { // For WC 2.1.0
			  $checkout_payment_url = $order->get_checkout_payment_url( true );
			} else {
				$checkout_payment_url = get_permalink( get_option ( 'woocommerce_pay_page_id' ) );
			}

			return array(
				'result' => 'success', 
				'redirect' => add_query_arg(
					'order', 
					$order->id, 
					add_query_arg(
						'key', 
						$order->order_key, 
						$checkout_payment_url						
					)
				)
			);
		} //END-process_payment

        /**
         * Check for valid gateway server callback
         **/
        function check_razorpay_response(){
            global $woocommerce;

            if(isset($_REQUEST['merchant_order_id']) && isset($_REQUEST['razorpay_payment_id'])){
                $order_id = $_REQUEST['merchant_order_id'];
                $razorpay_payment_id = $_REQUEST['razorpay_payment_id'];
                

                $order = new WC_Order($order_id);
                $key_id = $this->key_id;
                $key_secret = $this->key_secret;
                $amount = $order->order_total*100;

                $success = false;
                $error = "";

                try {
                    $url = 'https://api.razorpay.com/v1/payments/'.$razorpay_payment_id.'/capture';
                    $fields_string="amount=$amount";

                    //cURL Request
                    $ch = curl_init();

                    //set the url, number of POST vars, POST data
                    curl_setopt($ch,CURLOPT_URL, $url);
                    curl_setopt($ch,CURLOPT_USERPWD, $key_id . ":" . $key_secret);
                    curl_setopt($ch,CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch,CURLOPT_POST, 1);
                    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
                    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch,CURLOPT_CAINFO, plugin_dir_path(__FILE__) . 'ca-bundle.crt');

                    //execute post
                    $result = curl_exec($ch);
                    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);


                    if($result === false) {
                        $success = false;
                        $error = 'Curl error: ' . curl_error($ch);
                    }
                    else {
                        $response_array = json_decode($result, true);
                        //Check success response
                        if($http_status === 200 and isset($response_array['error']) === false){
                            $success = true;    
                        }
                        else {
                            $success = false;

                            if(!empty($response_array['error']['code'])) {
                                $error = $response_array['error']['code'].":".$response_array['error']['description'];
                            }
                            else {
                                $error = "RAZORPAY_ERROR:Invalid Response <br/>".$result;
                            }
                        }
                    }
                    //close connection
                    curl_close($ch);
                }
                catch (Exception $e) {
                    $success = false;
                    $error ="WOOCOMMERCE_ERROR:Request to Razorpay Failed";
                }

                if($success === true){
                    $this->msg['message'] = "Thank you for the order. Your account has been charged and your transaction is successful. Order Id: ".$order_id;
                    $this->msg['class'] = 'success';
                    $order->payment_complete();
                    $order->add_order_note('Razorpay payment successful <br/>Razorpay Id: '.$razorpay_payment_id);
                    $order->add_order_note($this->msg['message']);
                    $woocommerce->cart->empty_cart();
                }
                else{
                    $this->msg['class'] = 'error';
                    $this->msg['message'] = "Thank you for the order. However, the payment failed.";
                    $order->add_order_note('Transaction Declined: '.$error);
                    $order->add_order_note('Payment Failed. Please check Razorpay Dashboard. <br/> Razorpay Id:'.$razorpay_payment_id);
                    $order->update_status('failed');
                }                
            }
            else {
                $this->msg['class'] = 'error';
                $this->msg['message'] = "An Error occured";
            }

            if (function_exists('wc_add_notice')) {
                wc_add_notice( $this->msg['message'], $this->msg['class'] );
            }
            else {
                if($this->msg['class']=='success'){
                    $woocommerce->add_message($this->msg['message']);
                }
                else{
                    $woocommerce->add_error($this->msg['message']);

                }
                $woocommerce->set_messages();
            }
            
            $redirect_url = get_permalink(woocommerce_get_page_id('myaccount'));
            wp_redirect( $redirect_url );
            exit;
        } //END-check_razorpay_response

        /**
         * Get Page list from WordPress
         **/
		function razorpay_get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				// show indented child pages?
				if ($indent) {
                	$has_parent = $page->post_parent;
                	while($has_parent) {
                    	$prefix .=  ' - ';
                    	$next_page = get_post($has_parent);
                    	$has_parent = $next_page->post_parent;
                	}
            	}
            	// add to page list array array
            	$page_list[$page->ID] = $prefix . $page->post_title;
        	}
        	return $page_list;
		} //END-razorpay_get_pages

	} //END-class
	
	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_gateway_razorpay_gateway($methods) {
		$methods[] = 'WC_Gateway_Razorpay';
		return $methods;
	}//END-wc_add_gateway
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_razorpay_gateway' );
} //END-init
