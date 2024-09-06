<?php
/**
 * Plugin Name: Pallapay Crypto Payment Gateway
 * Plugin URI: https://www.pallapay.com/
 * Description: Pallapay woocommerce plugin to accept cryptocurrency payments.
 * Version: 1.0.0
 *
 * Author: Pallapay
 * Author URI: https://www.pallapay.com/
 *
 * Text Domain: woocommerce-pallapay-ppg
 * Domain Path: /i18n/languages/
 *
 * Requires at least: 4.2
 * Tested up to: 4.9
 *
 * Copyright: © 2024 Pallapay.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Pallapay Payment gateway plugin class.
 *
 * @class WC_Pallapay_Payments
 */
class WC_Pallapay_Payments {

	/**
	 * Plugin bootstrapping.
	 */
	public static function init() {

		// Pallapay Payments gateway class.
		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 0 );

		// Make the Pallapay Payments gateway available to WC.
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );

		// Registers WooCommerce Blocks integration.
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'woocommerce_gateway_pallapay_woocommerce_block_support' ) );
	}

	/**
	 * Add the Pallapay Payment gateway to the list of available gateways.
	 *
	 * @param array
	 */
	public static function add_gateway( $gateways ) {
        $gateways[] = 'WC_Pallapay_PPG';
		return $gateways;
	}

	/**
	 * Plugin includes.
	 */
	public static function includes() {

		// Make the WC_Pallapay_PPG class available.
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			require_once 'includes/class-wc-pallapay-ppg.php';
		}
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_abspath() {
		return trailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 *
	 */
	public static function woocommerce_gateway_pallapay_woocommerce_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once 'includes/blocks/class-wc-pallapay-ppg-blocks.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Pallapay_PPG_Blocks_Support() );
				}
			);
		}
	}
}

WC_Pallapay_Payments::init();
