<?php
defined( 'ABSPATH' ) || exit;

/**
 * RFW_Data class
 */
class RFW_Data {
	/**
	 * Whether or not logging is enabled.
	 *
	 * @var array
	 */
	private static $settings = [];

	/**
	 * Load gateway settings from the database.
	 *
	 * @return void
	 */
	public static function load_settings() {
		self::$settings = get_option( 'woocommerce_' . RFW_PLUGIN_ID . '_settings', [] );
	}

	/**
	 * Save gateway settings to the database.
	 *
	 * @return void
	 */
	public static function set_settings( $settings ) {
		update_option( 'woocommerce_' . RFW_PLUGIN_ID . '_settings', array_merge( self::get_settings(), $settings ) );
	}

	/**
	 * Returns if payment gateway is enabled.
	 *
	 * @return bool
	 */
	public static function enabled() {
		if ( empty( self::$settings ) ) {
			self::load_settings();
		}

		return isset( self::$settings['enabled'] ) && 'yes' === self::$settings['enabled'];
	}

	/**
	 * Returns true if test mode is turned on, false otherwise.
	 *
	 * @return bool
	 */
	public static function test_mode() {
		if ( empty( self::$settings ) ) {
			self::load_settings();
		}

		return isset( self::$settings['in-test-mode'] ) && 'yes' === self::$settings['in-test-mode'];
	}

	/**
	 * Returns true if order was created in test mode, false otherwise.
	 *
	 * @return bool
	 */
	public static function order_test_mode( $order ) {
		return 'yes' === $order->get_meta( 'resolve_test_mode' );
	}

	/**
	 * Returns true if required keys in gateways settings are set, false otherwise.
	 *
	 * @return bool
	 */
	public static function required_keys_set() {
		if ( ! self::get_settings( 'webshop-merchant-id', true ) || ! self::get_settings( 'webshop-api-key', true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Fetch settings for use.
	 *
	 * @param string $name       Name of specific setting to fetch.
	 * @param bool   $test_check Whether to check if test mode is on to fetch test version of the setting.
	 *
	 * @return array/string
	 */
	public static function get_settings( $name = false, $test_check = false ) {
		if ( empty( self::$settings ) ) {
			self::load_settings();
		}

		if ( $name ) {
			if ( $test_check ) {
				$name = self::test_mode() ? 'test-' . $name : $name;
			}

			return isset( self::$settings[ $name ] ) ? self::$settings[ $name ] : null;
		}

		return self::$settings;
	}

	/**
	 * Return true if payment mode is capture.
	 *
	 * @return bool
	 */
	public static function is_mode_capture() {
		return 'capture' === self::get_settings( 'payment-mode' );
	}

	/**
	 * Return true if payment mode is capture.
	 *
	 * @return bool
	 */
	public static function is_auto_redirect() {
		return 'yes' === self::get_settings( 'auto-redirect' );
	}

	/**
	 * Return true if payment mode is capture.
	 *
	 * @return bool
	 */
	public static function is_backorder_pay_disabled() {
		return 'yes' === self::get_settings( 'backorder-disable' );
	}

	/**
	 * Return items data formatted for usage with Resolve JS script.
	 *
	 * @see https://app.paywithresolve.com/docs/direct
	 * @param  WC_Order $order
	 * @return array
	 */
	public static function get_items_data( $order ) {
		$data = [];

		foreach ( $order->get_items() as $key => $item ) {
			if ( is_a( $item, 'WC_Order_Item_Product' ) ) {
				$product = $item->get_product();
				if ( ! $product ) {
					continue;
				}
				$data[] = [
					'name'       => $product->get_name(),
					'sku'        => $product->get_sku(),
					'unit_price' => $product->get_price(),
					'quantity'   => $item->get_quantity(),
				];
			}
		}

		return $data;
	}

	/**
	 *  Return shipping and billing data from the given order, and merchant
	 *  credentials. All should be formatted according to the Resolve docs.
	 *
	 * @see https://app.paywithresolve.com/docs/direct
	 * @param  WC_Order $order
	 * @param  array    $gateway_settings
	 * @return array
	 */
	public static function get_checkout_data( $order, $gateway_settings ) {
		return [
			'merchant'        => [
				'id'          => self::get_settings( 'webshop-merchant-id', true ),
				'success_url' => $order->get_checkout_order_received_url(),
				'cancel_url'  => $order->get_cancel_order_url_raw(),
			],
			'shipping'        => [
				'name'            => $order->get_formatted_shipping_full_name(),
				'company_name'    => $order->get_shipping_company(),
				'phone'           => $order->get_billing_phone(),
				'address_line1'   => $order->get_shipping_address_1(),
				'address_line2'   => $order->get_shipping_address_2(),
				'address_city'    => $order->get_shipping_city(),
				'address_state'   => $order->get_shipping_state(),
				'address_postal'  => $order->get_shipping_postcode(),
				'address_country' => $order->get_shipping_country(),
			],
			'billing'         => [
				'name'            => $order->get_formatted_billing_full_name(),
				'company_name'    => $order->get_billing_company(),
				'phone'           => $order->get_billing_phone(),
				'address_line1'   => $order->get_billing_address_1(),
				'address_line2'   => $order->get_billing_address_2(),
				'address_city'    => $order->get_billing_city(),
				'address_state'   => $order->get_billing_state(),
				'address_postal'  => $order->get_billing_postcode(),
				'address_country' => $order->get_billing_country(),
			],
			'order_number'    => $order->get_order_number(),
			'po_number'       => '', // (optional) buyer purchase order number if required
			'shipping_amount' => $order->get_shipping_total(),
			'tax_amount'      => $order->get_total_tax(),
			'total_amount'    => $order->get_total(),
			'metadata'        => [
				'platform_resolve' => RFW_PLUGIN_VERSION,
				'platform_type'    => 'WooCommerce',
				'platform_version' => WC_VERSION,
			],
		];
	}

	/**
	 * Get customer data for usage with Resolve JS script.
	 *
	 * @see https://app.paywithresolve.com/docs/direct
	 * @param  WC_Order $order
	 * @return array
	 */
	public static function get_customer_data( $order ) {
		$customer_id = $order->get_customer_id();
		$customer    = new WC_Customer( $customer_id );

		if ( ! empty( $customer->get_email() ) ) {
			return [
				'first_name' => $customer->get_first_name(),
				'last_name'  => $customer->get_last_name(),
				'name'       => $customer->get_display_name(),
				'phone'      => $customer->get_billing_phone(),
				'email'      => $customer->get_email(),
			];
		} else {
			return [
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'name'       => $order->get_billing_company(),
				'phone'      => $order->get_billing_phone(),
				'email'      => $order->get_billing_email(),
			];
		}
	}

	/**
	 * Creates a link that will trigger Rsolve modal.
	 *
	 * @param   string  $label  Label for the link
	 *
	 * @return  mixed           HMTL of the link.
	 */
	public static function display_modal_link( $label = null ) {
		return '<a href="#" id="rfw-apply">' . ( $label ?: __( 'Learn more', 'resolve' ) ) . '</a>';
	}

	/**
	 * Allows changes to default capture status but double check if they are valid.
	 *
	 * @param   bool   $prefix  To return status with WC prefix or without.
	 *
	 * @return  string          Captured order status.
	 */
	public static function get_captured_status( $prefix = false ) {
		$captured_order_status = apply_filters( 'rfw_payment_captured_order_status', 'processing' );
		if ( 'processing' !== $captured_order_status ) {
			$order_statuses = wc_get_order_statuses();

			if ( ! isset( $order_statuses[ 'wc-' . $captured_order_status ] ) ) {
				RFW_Logger::log( 'Custom order status: "' . $captured_order_status . '" is not a valid WC Order status, default capture status will be used (Processing).', 'warning' );
				$captured_order_status = 'processing';
			}
		}

		return $prefix ? 'wc-' . $captured_order_status : $captured_order_status;
	}
}
