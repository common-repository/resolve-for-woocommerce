<?php

defined( 'ABSPATH' ) || exit;

/**
 * RFW_Ajax_Interface class
 */
class RFW_Ajax_Interface {

	/**
	 * Hook all the needed methods.
	 */
	public function register() {
		add_action( 'wp_ajax_rfw_get_checkout_data', [ $this, 'get_checkout_data_via_ajax' ] );
		add_action( 'wp_ajax_nopriv_rfw_get_checkout_data', [ $this, 'get_checkout_data_via_ajax' ] );
	}

	/**
	 * Fetches checkout data required to execute a Resolve checkout request.
	 *
	 * @return  json  Data required for request.
	 */
	public function get_checkout_data_via_ajax() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'rfw_checkout_action' ) ) {
			RFW_Logger::log( 'Failed verifying nonce when fetching checkout data.', 'critical' );
			wp_send_json_error( [ 'message' => 'Invalid nonce provided.' ] );
		}

		$order_id = filter_input( INPUT_POST, 'order_id', FILTER_SANITIZE_NUMBER_INT );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			RFW_Logger::log( 'Order ID: ' . $order_id . ' does not match any orders when fetching checkout data.', 'critical' );
			wp_send_json_error( [ 'message' => 'Invalid order ID provided.' ] );
		}

		$gateway_settings = get_option( 'woocommerce_' . RFW_PLUGIN_ID . '_settings', [] );
		if ( ! $gateway_settings ) {
			RFW_Logger::log( 'Failed fetching payment gateway settings when fetching checkout data.', 'critical' );
			wp_send_json_error( [ 'message' => 'Unable to obtain gateway settings.' ] );
		}

		$data = RFW_Data::get_checkout_data( $order, $gateway_settings );

		if ( ! $data ) {
			RFW_Logger::log( 'Failed fetching checkout data.', 'critical' );
			wp_send_json_error( [ 'message' => 'Failed fetching checkout data.' ] );
		}

		if ( RFW_Data::test_mode() ) {
			$data['sandbox'] = true;
		}

		$data['customer'] = RFW_Data::get_customer_data( $order );
		if ( ! $data['customer'] ) {
			RFW_Logger::log( 'Failed fetching customer data.', 'critical' );
			wp_send_json_error( [ 'message' => 'Failed fetching customer data.' ] );
		}

		$data['items'] = RFW_Data::get_items_data( $order );
		if ( ! $data['items'] ) {
			RFW_Logger::log( 'Failed fetching items data.', 'critical' );
			wp_send_json_error( [ 'message' => 'Failed fetching items data.' ] );
		}

		wp_send_json_success( $data );
	}

}
