<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_RapidPayment_CC class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_RapidPayment_CC extends WC_Payment_Gateway {

	/**
	 * API credentials
	 *
	 * @var string
	 */
	public $username;
    public $password;

	/**
	 * Api access token
	 *
	 * @var string
	 */
	public $token;

    /**
     * Checkout widget enabled
     */
    public $checkout_enabled;

	/**
	 * Logging enabled?
	 *
	 * @var bool
	 */
	public $logging;

    /**
     * Url for IPN
     *
     * @var null|string
     */
	public $notifyUrl = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'rapidpayment_cc';
		$this->method_title         = __( 'Rapid Payments', 'woocommerce-rapidpayment-gateway-creditcard' );
		$this->method_description   = __( 'This plugin allows for Credit/Cheque Card Payments, Instant EFT Payments as well as Alternative Payment Methods (APM’s).', 'woocommerce-rapidpayment-gateway-creditcard' );
		$this->has_fields           = false;
		$this->supports             = array();

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title                  = $this->get_option( 'title' );
		$this->description            = $this->get_option( 'description' );
		$this->enabled                = $this->get_option( 'enabled' );
        // $this->checkout_enabled       = $this->get_option( 'checkout_enabled' );
		$this->username               = $this->get_option( 'username' );
		$this->password               = $this->get_option( 'password' );
        $this->logging                = 'yes' === $this->get_option( 'logging' );
        $this->checkout_enabled       = 'no';
        $this->order_button_text = __( 'Continue with payment', 'woocommerce-rapidpayment-gateway-creditcard' );
        $this->notifyUrl = add_query_arg( 'wc-api', 'WC_Gateway_RapidPayment_CC', home_url( '/' ) );

        if ( ! class_exists( 'WC_RapidPayment_CC_API' ) ) {
            include_once( dirname( __FILE__ ) . '/class-wc-rapidpayment-cc-api.php' );
        }

        WC_RapidPayment_CC_API::set_username( $this->username );
        WC_RapidPayment_CC_API::set_password( $this->password );

		// Hooks.
        // if ($this->checkout_enabled === 'yes') {
        //     add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        // }

        add_action( 'woocommerce_api_wc_gateway_rapidpayment_cc', array( $this, 'check_ipn_response' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options') );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Get_icon function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {

		$icon  = '<img src="'.WC_RAPID_CC_LOGO.'" alt="Rapid Payments Gateway" />';
		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Check if SSL is enabled and notify the user
	 */
	public function admin_notices() {
		if ( 'no' === $this->enabled ) {
			return;
		}
	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			if ( ! $this->username || ! $this->password ) {
				return false;
			}
			else if (get_woocommerce_currency() != 'ZAR') {
                return false;
            }
			return true;
		}
		return false;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = include( 'settings-rapidpayment-cc.php' );
	}

    public function validate_password_field($key, $value = NULL){

        $post_data = $_POST;
	    if ($value == NULL) {
            $value = $post_data['woocommerce_rapidpayment_cc_'.$key];
        }
        if( isset($post_data['woocommerce_rapidpayment_cc_username']) && empty($value)){
            //not validated
            //add_settings_error($key, 'settings_updated', 'Password is required', 'error');
            WC_Admin_Settings::add_error( __( 'Error: You must enter a API password.', 'woocommerce-rapidpayment-gateway-creditcard' ) );
            return false;
        }else{
            WC_RapidPayment_CC_API::set_username($post_data['woocommerce_rapidpayment_cc_username']);
            WC_RapidPayment_CC_API::set_password($value);
            try{
                WC_RapidPayment_CC_API::get_token_data();
            }
            catch(Exception $e) {
                WC_Admin_Settings::add_error( __( 'Error: Incorrect username and/or password.', 'woocommerce-rapidpayment-gateway-creditcard' ) );
                return false;
            }
        }
        return $value;
    }

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		echo '<div>';

		if ( $this->description ) {
			echo apply_filters( 'wc_rapidpayment_cc_description', wpautop( wp_kses_post( $this->description ) ) );
		}

		echo '</div>';
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for rapidpayment_cc payment
	 *
	 * @access public
	 */
	public function payment_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        add_action('wp_head', 'rapidPaymentCCEventJS');

        wp_enqueue_script( 'rapid_checkout', 'https://secure.rapidpayments.africa/ext/checkout/v2/checkout.js', '', '2.0', true );
        wp_enqueue_script( 'woocommerce_rapid_checkout', plugins_url( 'assets/js/eftsecure_checkout.js', WC_RAPIDPAYMENT_CC_MAIN_FILE ), array( 'rapid_checkout' ), WC_RAPIDPAYMENT_CC_VERSION, true );

        $appDomain = WC_RapidPayment_CC_API::getSetting('app_domain');
        $rapidpayment_cc_params = ['service_url' => $appDomain."/rpp-transaction/create-from-key"];

		wp_localize_script( 'woocommerce_rapid_checkout', 'wc_rapidpayment_cc_params', apply_filters( 'wc_rapidpayment_cc_params', $rapidpayment_cc_params ) );
	}

	/**
	 * Process the payment
	 *
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_customer Force user creation.
	 *
	 * @throws Exception If payment will not be accepted.
	 *
	 * @return mixed
	 */
	public function process_payment( $order_id, $retry = true, $force_customer = false ) {

        ini_set('display_errors','Off'); //notices breaking json

        /**
         * @var $order WC_Order
         */
        $order = wc_get_order( $order_id );

        // If order free, do nothing.
        if ($order->get_total() == 0) {
            $order->payment_complete();
            // Return thank you page redirect.
            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            ];
        }

        $transactionID = $this->get_rapidpayment_cc_transaction_id();
        //Result found, process it
        if ($transactionID != NULL) {

            if ($order->get_status() == 'pending') {

                try {

                    WC_RapidPayment_CC::log("Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}");
                    // Make sure the transaction is successful.
                    $response = WC_RapidPayment_CC_API::get_transaction_data($transactionID);
                    // Process valid response.
                    $this->process_response($response, $order);

                    // Remove cart.
                    WC()->cart->empty_cart();

                    do_action('wc_gateway_rapidpayment_cc_process_payment', $response, $order);

                    // Return thank you page redirect.
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );

                } catch (Exception $e) {
                    wc_add_notice($e->getMessage(), 'error');
                    WC_RapidPayment_CC::log(sprintf(__('Error: %s', 'woocommerce-rapidpayment-gateway-creditcard'), $e->getMessage()));

                    if ($order->has_status(array('pending', 'failed'))) {
                        $this->send_failed_order_email($order_id);
                    }

                    do_action('wc_gateway_rapidpayment_cc_process_payment_error', $e, $order);

                    return array(
                        'result' => 'fail',
                        'redirect' => ''
                    );
                }
            } else {
                // Return thank you page redirect already flagged as paid by IPN.
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            }
        }
        //Redirect if checkout not enabled
        else if ($this->checkout_enabled === 'no') {
            /**
             * @var $order WC_Order
             */
            $pkeyData = WC_RapidPayment_CC_API::get_payment_key_data([
                'amount' => $order->get_total(),
                'merchant_reference' => $this->get_order_id($order),
                'notify_url' => $this->notifyUrl."&order_id=".$order_id,
                'success_url' => $this->get_return_url( $order ),
                'error_url' => $this->notifyUrl."&order_id=".$order_id,
                'cancel_url' => $order->get_checkout_payment_url()
            ]);

            return array(
                'result' => 'success',
                'redirect' => $pkeyData->url
            );
        }
        else if ($this->checkout_enabled === 'yes') {
            /**
             * @var $order WC_Order
             */
            $pkeyData = WC_RapidPayment_CC_API::get_payment_key_data([
                'amount' => $order->get_total(),
                'merchant_reference' => $this->get_order_id($order),
                'notify_url' => $this->notifyUrl."&order_id=".$order_id,
                'success_url' => $this->get_return_url( $order ),
                'error_url' => $this->notifyUrl."&order_id=".$order_id,
                'cancel_url' => $order->get_checkout_payment_url()
            ]);

            return array(
                'result' => 'success',
                'paymentKey' => $pkeyData->key
            );
        }

        return;

	}


    /**
     * Store extra meta data for an order from a Rapid Payment Response.
     *
     * @param $response
     * @param $order WC_Order
     * @return mixed
     * @throws Exception
     */
	public function process_response( $response, $order ) {

        WC_RapidPayment_CC::log( "Processing response: " . print_r( $response, true ) );

	    if ($response->successful == 0) {
            $order->add_order_note( sprintf( __( 'Gateway Transaction Failed: (%s)', 'woocommerce-rapidpayment-gateway-creditcard' ), $response->reason ) );
            throw new Exception( __( 'Payment and order success do not correspond.', 'woocommerce-rapidpayment-gateway-creditcard' ) );
        }
        else if(floatval($response->amount) != floatval($order->get_total())) {
            WC_RapidPayment_CC::log( 'Order amount ('.floatval($order->get_total()).') and payment amount ('.floatval($response->amount).') do not correspond.' );
            throw new Exception( __( 'Order amount ('.floatval($order->get_total()).') and payment amount ('.floatval($response->amount).') do not correspond.', 'woocommerce-rapidpayment-gateway-creditcard' ) );
        }

        add_post_meta( $this->get_order_id($order), '_transaction_id', $response->id, true );

        $order->add_order_note(sprintf( __( 'Rapid Payment charge complete (Transaction ID: %s)', 'woocommerce-gateway-stripe' ), $response->id ));

        if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
            if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
                // new version code
                wc_reduce_stock_levels($order);
            } else {
                $order->reduce_order_stock();
            }
        }

        $order->update_status( 'wc-processing');
        WC_RapidPayment_CC::log( "Successful payment: $response->id" );

		return $response;
	}

	public function check_ipn_response() {
	    if (!empty($_GET['success']) && $_GET['success'] == 'false') {
            wc_add_notice( __( 'Unfortunately your order cannot be processed as transaction has failed. Please attempt your purchase again.', 'gateway' ), 'error' );
            wp_safe_redirect( wc_get_page_permalink( 'cart' ) );
            WC_RapidPayment_CC::log( __('Unfortunately your order cannot be processed as transaction has failed. Please attempt your purchase again.', 'woocommerce-rapidpayment-gateway-creditcard' ) );
        }
        WC_RapidPayment_CC::log( __( json_encode($_REQUEST), 'woocommerce-rapidpayment-gateway-creditcard' ) );
	    if(!isset($_REQUEST['order_id'])) {
            WC_RapidPayment_CC::log( __( 'OrderID was not specified in IPN response', 'woocommerce-rapidpayment-gateway-creditcard' ) );
            throw new Exception( __( 'OrderID was not specified in IPN response', 'woocommerce-rapidpayment-gateway-creditcard' ) );
        }

        WC_RapidPayment_CC::log( __( 'EFT IPN response received '.json_encode($_REQUEST), 'woocommerce-rapidpayment-gateway-creditcard' ) );

        $order_id = $_REQUEST['order_id'];
        /**
         * @var $order WC_Order
         */
        $order = wc_get_order( $_REQUEST['order_id'] );

        if ($order->get_status() == 'pending') {

            if (!$order) {
                WC_RapidPayment_CC::log(__('Invalid OrderID was specified in IPN response', 'woocommerce-rapidpayment-gateway-creditcard'));
                throw new Exception(__('Invalid OrderID was specified in IPN response', 'woocommerce-rapidpayment-gateway-creditcard'));
            }

            $transactionID = $this->get_rapidpayment_cc_transaction_id();

            if ($transactionID == NULL) {
                WC_RapidPayment_CC::log(__('No gateway transactionID specified in IPN response', 'woocommerce-rapidpayment-gateway-creditcard'));
                throw new Exception(__('No gateway transactionID specified in IPN response', 'woocommerce-rapidpayment-gateway-creditcard'));
            }

            // Make sure the transaction is successful.
            $response = WC_RapidPayment_CC_API::get_transaction_data($transactionID);


            WC_RapidPayment_CC::log("Info: Begin processing IPN for payment for order $order_id for the amount of {$order->get_total()}");
            $order->add_order_note(sprintf(__('Gateway IPN received : %s', 'woocommerce-gateway-stripe'), (($response->successful) ? 'SUCCESS' : 'FAILED')));

            WC_RapidPayment_CC::log("Processing response: " . print_r($response, true));

            if ($response->successful == 0 && $_REQUEST['success'] == 1) {
                $order->add_order_note(sprintf(__('Gateway Transaction Failed: (%s)', 'woocommerce-rapidpayment-gateway-creditcard'), $response->reason));
                throw new Exception(__('Payment and request success do not correspond.', 'woocommerce-rapidpayment-gateway-creditcard'));
            } else if (floatval($response->amount) != floatval($order->get_total())) {
                WC_RapidPayment_CC::log('Order amount (' . floatval($order->get_total()) . ') and payment amount (' . floatval($response->amount) . ') do not correspond.');
                throw new Exception(__('Order amount (' . floatval($order->get_total()) . ') and payment amount (' . floatval($response->amount) . ') do not correspond.', 'woocommerce-rapidpayment-gateway-creditcard'));
            }

            add_post_meta($this->get_order_id($order), '_transaction_id', $response->id, true);

            if ($response->successful == 1) {

                if ($order->has_status(array('pending', 'failed'))) {
                    if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
                        // new version code
                        wc_reduce_stock_levels($order);
                    } else {
                        $order->reduce_order_stock();
                    }
                }

                $order->payment_complete();
                $order->add_order_note(sprintf(__('Gateway charge complete (Transaction ID: %s)', 'woocommerce-rapidpayment-gateway-creditcard'), $response->id));

                WC_RapidPayment_CC::log("Successful payment: $response->id");

                do_action('wc_gateway_rapidpayment_cc_process_payment', $response, $order);
            } else {
                $order->add_order_note(sprintf(__('Gateway Transaction Failed: (%s)', 'woocommerce-rapidpayment-gateway-creditcard'), $response->reason));
            }
        } else {
            $order->add_order_note(sprintf(__('Transaction updated .. ignoring IPN.', 'woocommerce-rapidpayment-gateway-creditcard')));
        }

    }

	/**
	 * Sends the failed order email to admin
	 *
	 * @version 3.1.0
	 * @since 3.1.0
	 * @param int $order_id
	 * @return null
	 */
	public function send_failed_order_email( $order_id ) {
		$emails = WC()->mailer()->get_emails();
		if ( ! empty( $emails ) && ! empty( $order_id ) ) {
			$emails['WC_Email_Failed_Order']->trigger( $order_id );
		}
	}

	private function get_rapidpayment_cc_transaction_id() {
	    if (isset($_REQUEST['rapidpayment_cc_transaction_id'])) {
	        return $_REQUEST['rapidpayment_cc_transaction_id'];
        }
        else if (isset($_REQUEST['transaction_id'])) {
            return $_REQUEST['transaction_id'];
        }
        return null;
    }

    public function get_order_id($order) {
	    if (method_exists($order, 'get_id')) {
	        return $order->get_id();
        }
        else {
	        return $order->id;
        }
    }
}

function rapidPaymentCCEventJS() {
    echo '<script type="text/javascript">window.addEventListener("message", function(event) {
        eval(event.data);
    });</script>';
}
