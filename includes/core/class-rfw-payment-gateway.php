<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * RFW_Payment_Gateway class
 */
class RFW_Payment_Gateway extends WC_Payment_Gateway {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id                 = RFW_PLUGIN_ID;
		$this->method_title       = __( 'Resolve', 'resolve' );
		$this->method_description = __( 'A payment gateway for Resolve.', 'resolve' );
		$this->has_fields         = true;

		$this->init_form_fields();

		$this->supports = [ 'products' ];

		$this->title = esc_attr( RFW_Data::get_settings( 'title' ) );
		$this->add_actions();

		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts' ] );
	}

	/**
	 * Register Resolve and plugin's JS script.
	 */
	public function register_scripts() {
		wp_enqueue_script( 'rfw-vendor-js', '//app.paywithresolve.com/js/resolve.js', [], RFW_PLUGIN_ID, true );

		wp_enqueue_script( 'rfw-client-js', RFW_DIR_URL . '/assets/dist/js/rfw-client.js', [ 'jquery' ], RFW_PLUGIN_ID, true );

		wp_localize_script(
			'rfw-client-js',
			'RFWPaymentGateway',
			[
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'merchant_ID' => RFW_Data::get_settings( 'webshop-merchant-id', true ),
				'test_mode'   => RFW_Data::test_mode(),
			]
		);
	}

	/**
	 * Register different actions.
	 */
	private function add_actions() {
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_receipt_' . $this->id, [ $this, 'do_receipt_page' ] );

		add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'show_confirmation_message' ] );
		add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'process_response' ] );

		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'maybe_disable' ], 90, 1 );

		add_action( 'woocommerce_order_item_add_action_buttons', [ $this, 'display_capture_button' ], 10, 1 );
		add_action( 'save_post', [ $this, 'process_capture' ] );
	}

	/**
	 * Load settings fields from a separate file.
	 *
	 * @return  array  Plugin setting fields.
	 */
	public function init_form_fields() {
		$this->form_fields = include RFW_DIR_PATH . '/includes/settings/rfw-settings-fields.php';
	}

	/**
	 * Load settings for the payment gateway plugin.
	 *
	 * @return  mixed  Settings HTML.
	 */
	public function admin_options() {
		?>
		<h2><?php esc_html_e( 'Resolve', 'resolve' ); ?></h2>

		<table class="form-table">
			<?php $this->generate_settings_html(); ?>
		</table>
		<?php
	}

	/**
	 * Show a custom description from plugin settings under payment name on a list of available payments.
	 *
	 * @return  mixed Description wrapped in HTML.
	 */
	public function payment_fields() {
		$hide_explainer = RFW_Data::get_settings( 'explainer-disable' );
		if ( ! $hide_explainer || 'no' === $hide_explainer ) {
			echo '<p>' . esc_html__( 'Hassle-free terms with no hidden fees. Sign up and get approved today. ', 'resolve' ) . wp_kses_post( RFW_Data::display_modal_link() ) . '</p>';
		}

		$desc = RFW_Data::get_settings( 'description-msg' );
		if ( $desc ) {
			echo '<p>' . wp_kses_post( $desc ) . '</p>';
		}

		if ( RFW_Data::test_mode() ) {
			$test_mode_notice = apply_filters( 'rfw_payment_description_test_mode_notice', __( 'Resolve Payment Gateway is currently in test mode, disable it for live web shop.', 'resolve' ) );

			if ( ! empty( $test_mode_notice ) ) {
				echo '<p><b>' . esc_html( $test_mode_notice ) . '</b></p>';
			}
		}
	}

	/**
	 * Show confirmation page custom message from plugin settings.
	 *
	 * @return  mixed  Message wrapped in HTML.
	 */
	public function show_confirmation_message() {
		$conf = RFW_Data::get_settings( 'confirmation-msg' );
		if ( $conf ) {
			echo '<p>' . wp_kses_post( $conf ) . '</p>';
		}
	}

	/**
	 * Show receipt page custom message from plugin settings.
	 *
	 * @return  mixed  Message wrapped in HTML.
	 */
	private function show_receipt_message() {
		$receipt = RFW_Data::get_settings( 'receipt-redirect-msg' );
		if ( $receipt ) {
			echo '<p>' . wp_kses_post( $receipt ) . '</p>';
		}
	}

	/**
	 * Display additional info on receipt page.
	 *
	 * @param   int   $order_id  WooCommerce order ID.
	 *
	 * @return  mixed            Receipt page additional HTML.
	 */
	public function do_receipt_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		do_action( 'rfw_before_receipt_message', $order );
		?>
		<form id="rfw-payment-form" action="" method="post">
			<input id="rfw-order-id" type="hidden" value="<?php echo esc_attr( $order_id ); ?>">
			<?php wp_nonce_field( 'rfw_checkout_action', 'rfw_nonce' ); ?>

			<?php if ( ! RFW_Data::is_auto_redirect() ) : ?>
				<?php $this->show_receipt_message(); ?>

				<button type="button" id="rfw-pay" class="button btn-primary">
					<?php esc_html_e( 'Pay', 'resolve' ); ?>
				</button>
			<?php endif; ?>

		</form>
		<?php

		do_action( 'rfw_after_receipt_message', $order );
	}


	/**
	 * Remove payment gateway from list of available ones depending on plugin settings.
	 *
	 * @param   array  $available_gateways  Array of available gateways.
	 *
	 * @return  array                       Edited array of available gateways.
	 */
	public function maybe_disable( $available_gateways ) {
		if ( is_admin() ) {
			return $available_gateways;
		}

		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return $available_gateways;
		}

		// Remove payment gateway if any of items in cart are on backorder.
		if ( RFW_Data::is_backorder_pay_disabled() ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( $cart_item['data']->is_on_backorder( $cart_item['quantity'] ) ) {
					unset( $available_gateways[ RFW_PLUGIN_ID ] );
					break;
				}
			}
		}

		// Remove payment gateway if order totals do not match limits from settings.
		$order_min = (float) RFW_Data::get_settings( 'order-limit-min' ) ?: null;
		$order_max = (float) RFW_Data::get_settings( 'order-limit-max' ) ?: null;

		if ( $order_min || $order_max ) {
			$total = (float) WC()->cart->get_total( 'raw' );

			if ( ( $order_min && $total < $order_min ) || ( $order_max && $total > $order_max ) ) {
				unset( $available_gateways[ RFW_PLUGIN_ID ] );
			}
		}

		return $available_gateways;
	}

	/**
	 * Manually handle payment processing.
	 *
	 * @param   int   $order_id  WooCommerce order ID.
	 *
	 * @return  array            Array of data required to process payment.
	 */
	public function process_payment( $order_id ) {
		$order = new WC_Order( $order_id );

		return [
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		];
	}


	/**
	 * When loading the thank you page handle information that was sent back in GET and maybe execute capture.
	 *
	 * @param   int  $order_id  WooCommerce order ID.
	 *
	 * @return  void
	 */
	public function process_response( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$loan_id   = $order->get_meta( 'rfw_loan_id', true );
		$charge_id = $order->get_meta( 'rfw_charge_id', true );
		if ( $loan_id || $charge_id ) {
			return; // Data aleady saved, bail early.
		}

		// As per official Resolve documentation if a customer arrives to this page the payment is successfully authorized.
		$note = __( 'Order successfully authorized by the Resolve payment system', 'resolve' );

		$charge_id = filter_input( INPUT_GET, 'charge_id', FILTER_SANITIZE_STRING );
		if ( $charge_id ) {
			// translators: charge ID.
			$note .= sprintf( __( ', charge ID: %s', 'resolve' ), '<b>' . $charge_id . '</b>' );
			$order->add_meta_data( 'rfw_charge_id', $charge_id, true );
		}

		$loan_id = filter_input( INPUT_GET, 'loan_id', FILTER_SANITIZE_STRING );
		if ( $loan_id ) {
			// translators: loan ID.
			$note .= sprintf( __( ', loan ID: %s', 'resolve' ), '<b>' . $loan_id . '</b>' );
			$order->add_meta_data( 'rfw_loan_id', $loan_id, true );
		}

		$order->add_order_note( $note );
		$order->save();

		do_action( 'rfw_order_payment_authorized', $order, $charge_id, $loan_id );

		if ( RFW_Data::is_mode_capture() ) {
			$charge_id = $charge_id ?: $loan_id;

			$this->send_capture_request( $charge_id, $order );
		}
	}


	/**
	 * Displays a button for capturing funds in the order admin view.
	 *
	 * @param   WC_Order  $order  Object of the WooCommerce order.
	 *
	 * @return  mixed             Capture button HTML.
	 */
	public function display_capture_button( $order ) {
		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}

		if ( ! $order->get_meta( 'rfw_charge_id', true ) ) {
			return;
		}

		if ( $order->get_meta( 'rfw_payment_captured', true ) === 'yes' ) {
			return;
		}

		wp_nonce_field( 'rfw_capture_payment', 'rfw_capture_nonce' );
		?>
		<button type="submit" name="rfw_capture_payment" id="rfw-capture-payment" class="button" value="yes">
			<?php esc_html_e( 'Capture', 'resolve' ); ?>
		</button>
		<?php
	}


	/**
	 * Process capture request when Capture button click in order admin view.
	 *
	 * @param   int  $post_id  Order ID
	 *
	 * @return  void
	 */
	public function process_capture( $post_id ) {
		if ( 'shop_order' !== OrderUtil::get_order_type( $post_id ) ) {
			return;
		}

		// Is the capture button clicked?
		if ( (string) filter_input( INPUT_POST, 'rfw_capture_payment', FILTER_SANITIZE_STRING ) !== 'yes' ) {
			return;
		}

		if ( ! wp_verify_nonce( filter_input( INPUT_POST, 'rfw_capture_nonce', FILTER_SANITIZE_STRING ), 'rfw_capture_payment' ) ) {
			RFW_Logger::log( 'Failed veryfing the nonce when trying to capture funds for order: ' . $post_id, 'critical' );
			return;
		}

		$order = wc_get_order( $post_id );
		if ( ! $order ) {
			RFW_Logger::log( 'Cannot find order with iD: ' . $post_id, 'critical' );
			return;
		}

		if ( $order->get_meta( 'rfw_payment_captured', true ) === 'yes' ) {
			RFW_Logger::log( 'Payment has already been captured for order: ' . $post_id, 'critical' );
			return;
		}

		$charge_id = $order->get_meta( 'rfw_charge_id', true );
		if ( ! $charge_id ) {
			$charge_id = $order->get_meta( 'rfw_loan_id', true );

			if ( ! $charge_id ) {

				RFW_Logger::log( 'Cannot find a valid charge ID for order: ' . $post_id, 'critical' );
				return;
			}
		}

		$this->send_capture_request( $charge_id, $order, true );
	}


	/**
	 * Formats the data and sends a capture request.
	 *
	 * @param   string    $charge_id  ID of the order in Resolve system, used to capture funds.
	 * @param   WC_Order  $order      Object of the order in WooCommerce.
	 * @param   bool      $manual     Is the capture automatically initiated or manually by webshop administrator.
	 *
	 * @return  void
	 */
	private function send_capture_request( $charge_id, $order, $manual = false ) {
		$url_format = 'https://%s:%s@%s.resolvepay.com/api/charges/%s/capture';

		$merchant_id = RFW_Data::get_settings( 'webshop-merchant-id', true );
		$api_key     = RFW_Data::get_settings( 'webshop-api-key', true );

		$captured_order_status = RFW_Data::get_captured_status();

		$mode = RFW_Data::test_mode() ? 'app-sandbox' : 'app';
		$url  = sprintf( $url_format, $merchant_id, $api_key, $mode, $charge_id );
		$args = [
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( $merchant_id . ':' . $api_key ), // @codingStandardsIgnoreLine - remove warning for base64_encode being unsafe.
			],
		];

		$response = wp_remote_post( $url, $args );

		RFW_Logger::log( 'Capturing order: ' . $order->get_id() . ' responded with: ' . stripslashes( wp_json_encode( $response ) ), 'info' );

		if ( is_wp_error( $response ) ) {
			RFW_Logger::log( 'Capturing order: ' . $order->get_id() . ' failed with message: ' . $response->get_error_message(), 'critical' );
			// translators: error message.
			$order->add_order_note( sprintf( __( 'Failed to capture the payment! Error message: %s', 'resolve' ), '<b>' . $response->get_error_message() . '</b>' ) );
		} else {
			try {
				$code = $response['response']['code'];
				$body = json_decode( $response['body'], true );

				if ( isset( $body['error'] ) ) {
					RFW_Logger::log( 'Capturing order: ' . $order->get_id() . ' failed with message: ' . $body['error']['message'], 'critical' );
					// translators: error message.
					$order->add_order_note( sprintf( __( 'Failed to capture the payment! Resolve returned an error message: %s', 'resolve' ), '<b>' . $body['error']['message'] . '</b>' ) );
				} else {
					do_action( 'rfw_order_payment_captured', $order );
					// translators: charge ID.
					$order->set_status( $captured_order_status, sprintf( __( 'The payment was successfully captured! Resolve ID: %s.', 'resolve' ), '<b>' . $body['number'] . '</b>' ), $manual );
					// Prevent post status update overriding order status with value submited in form.
					$_POST['order_status'] = 'wc-' . $captured_order_status;

					$order->payment_complete( $body['number'] );
					$order->add_meta_data( 'rfw_payment_captured', 'yes', true );
					$order->save();
				}
			} catch ( Exception $e ) {
				RFW_Logger::log( 'Saving data for captured order: ' . $order->get_id() . ' interrupted with message: ' . $e->getMessage(), 'critical' );
			}
		}
	}
}
