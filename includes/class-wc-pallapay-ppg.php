<?php
/**
 * WC_Pallapay_PPG class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  Pallapay Crypto Payment Gateway
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pallapay Gateway.
 *
 * @class    WC_Pallapay_PPG
 */
class WC_Pallapay_PPG extends WC_Payment_Gateway {

	/**
	 * Payment gateway instructions.
	 * @var string
	 *
	 */
	protected $instructions;

	/**
	 * Unique id for the gateway.
	 * @var string
	 *
	 */
	public $id = 'pallapay';

    public $payment_base_url = 'https://app.pallapay.com';
    public $payment_path = '/api/v1/api/payments';
    public $webhook_url;
    public $api_key;
    public $secret_key;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->icon               = apply_filters( 'woocommerce_pallapay_gateway_icon', '' );
		$this->has_fields         = false;
		$this->supports           = array(
			'pre-orders',
			'products',
		);

		$this->method_title       = _x( 'Crypto Payment', 'Crypto payment method', 'woocommerce-pallapay-ppg' );
		$this->method_description = __( 'Allows crypto payments.', 'woocommerce-pallapay-ppg' );
        $this->webhook_url = add_query_arg('wc-api', 'wc_pallapay', trailingslashit(get_home_url()));

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title                    = $this->get_option( 'title' );
		$this->description              = $this->get_option( 'description' );
		$this->instructions             = $this->get_option( 'instructions', $this->description );
        $this->api_key                  = $this->get_option( 'api_key' );
        $this->secret_key               = $this->get_option( 'secret_key' );

		// Actions.
        add_action( 'init', array( &$this, 'wpg_check_pallapay_response' ) );
        add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'wpg_check_pallapay_response' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // Customer Emails
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
	}

    function generate_pallapay_payment($order_id)
    {
        $order = new WC_Order($order_id);

        if ( '' == $this->redirect_page || 0 == $this->redirect_page ) {
            $redirect_url = $this->get_return_url( $order );
        } else {
            $redirect_url = get_permalink( $this->redirect_page );
        }

        $webhook_url = $redirect_url;
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            $webhook_url = add_query_arg('wc-api', get_class($this), $webhook_url);
        }

        $timestamp = time();
        $approvalString = 'POST' . $this->payment_path . $timestamp;
        $signature = hash_hmac('sha256', $approvalString, $this->secret_key);

        $params = json_encode([
            'symbol' => get_woocommerce_currency(),
            'amount' => strval($order->get_total()),
            'webhook_url' => $webhook_url,
            'ipn_success_url' => $order->get_checkout_order_received_url(),
            'ipn_failed_url' => $order->get_cancel_order_url(),
            'payer_email_address' => $order->get_billing_email(),
            'payer_first_name' => $order->get_billing_first_name(),
            'payer_last_name' => $order->get_billing_last_name(),
            'note' => $order_id,
        ]);

        $curlCli = curl_init($this->payment_base_url . $this->payment_path);
        curl_setopt($curlCli, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Palla-Api-Key: ' . $this->api_key,
            'X-Palla-Sign: ' . $signature,
            'X-Palla-Timestamp: ' . $timestamp,
        ]);
        curl_setopt($curlCli, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curlCli, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($curlCli, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curlCli, CURLOPT_POST, TRUE);
        curl_setopt($curlCli, CURLOPT_POSTFIELDS, $params);
        $result = curl_exec($curlCli);
        curl_close($curlCli);
        $resultData = json_decode($result, TRUE);

        if ($resultData['is_successful']) {
            return $resultData['data']['payment_link'];
        } else {
            throw new Exception( json_encode($resultData) );
        }
    }

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-pallapay-ppg' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Crypto Payments', 'woocommerce-pallapay-ppg' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-pallapay-ppg' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-pallapay-ppg' ),
				'default'     => _x( 'Crypto Payment', 'Crypto payment method', 'woocommerce-pallapay-ppg' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-pallapay-ppg' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce-pallapay-ppg' ),
				'default'     => __( 'Pay using cryptocurrencies.', 'woocommerce-pallapay-ppg' ),
				'desc_tip'    => true,
			),
            'api_key' => array(
                'title'       => __('Api Key', 'woocommerce-pallapay-ppg'),
                'description' => __('If you do not have api key, sign up in pallapay.com and get one.', 'woocommerce-pallapay-ppg'),
                'type'        => 'text',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'secret_key' => array(
                'title'       => __('Secret Key', 'woocommerce-pallapay-ppg'),
                'description' => __('Merchant secret key to sign your payment requests.', 'woocommerce-pallapay-ppg'),
                'type'        => 'password',
                'default'     => '',
                'desc_tip'    => true,
            ),
		);
	}

    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {

        if ($this->instructions && !$sent_to_admin && $this->id === $order->payment_method && $order->has_status('on-hold')) {
            echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
        }
    }

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int  $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
        $order = new WC_Order($order_id);
        try {
            $result = $this->generate_pallapay_payment($order_id);
            return array(
                'result'   => 'success',
                'redirect' => $result
            );
        } catch (Exception $e) {
            if (WP_DEBUG) {
                $message = $e;
            } else {
                $message = __( 'Order payment failed. Please try again later.', 'woocommerce-pallapay-ppg' );
            }

            $order->update_status( 'failed', $message );
            throw new Exception( $message );
        }
	}

    public function wpg_check_pallapay_response()
    {
        $json = file_get_contents('php://input');
        $requestData = json_decode($json, true);

        /* Change IPN URL */
        if (isset($requestData['data']) && isset($requestData['approval_hash'])) {
            $data = $requestData['data'];
            $requestApprovalHash = $requestData['approval_hash'];

            $order_id = $data['note'];
            if ($order_id != '') {
                try {
                    $order = new WC_Order($order_id);
                    $status = $data['status'];
                    $paymentAmount = (float) $data['payment_amount'];

                    if ($order->get_status() == 'pending' || $order->get_status() == 'failed') {
                        ksort($data);

                        $approvalString = implode('', $data);
                        $approvalHash = hash_hmac('sha256', $approvalString, $this->secret_key);

                        if ($approvalHash == $requestApprovalHash) {
                            if ($status == 'PAID' && $order->get_total() == $paymentAmount) {
                                $this->msg['message'] = "Thank you for the order. Your transaction is successful.";
                                $this->msg['class'] = 'success';
                                $order->add_order_note('Pallapay payment successful.<br/>Pallapay Payment Ref ID: ' . $data['ref_id']);
                                $order->payment_complete();
                                WC()->cart->empty_cart();
                            } else {
                                $order->payment_failed();
                                $this->msg['class'] = 'error';
                                $this->msg['message'] = "The transaction has been declined.";
                                $order->add_order_note('Transaction Fail');
                            }
                            echo "OK";
                        } else {
                            echo "Invalid Request";
                        }
                    }
                } catch (Exception $e) {
                    echo "Error";
                }
            }
            exit;
        }
    }
}
