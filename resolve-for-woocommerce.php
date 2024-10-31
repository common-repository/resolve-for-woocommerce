<?php
/**
 * Plugin Name:       Resolve for WooCommerce
 * Plugin URI:        https://resolvepay.com/
 * Description:       A payment gateway for Resolve.
 * Author:            Resolve
 * Author URI:        https://resolvepay.com/about/
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       resolve
 * Domain Path:       /languages
 *
 * Version: 1.0.6
 *
 * Requires at least:    5.0
 * Requires PHP:         7.2
 * WC requires at least: 3.3
 * WC tested up to:      8.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return true if the Woocommerce plugin is active or false otherwise.
 *
 * @return boolean
 */
function rfw_is_woocommerce_active() {
	return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
}

/**
 * Echo admin notice HTML for missing WooCommerce plugin.
 *
 * @return void
 */
function rfw_admin_notice_missing_woocommerce() {
	global $current_screen;

	if ( $current_screen->parent_base === 'plugins' ) {
		?>
		<div class="notice notice-error">
			<?php // translators: anchor tags. ?>
			<p><?php echo wp_kses_post( sprintf( __( 'Please install and activate %1$s WooCommerce %2$s before activating Resolve payment gateway!', 'resolve' ), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">', '</a>' ) ); ?></p>
		</div>
		<?php
	}
}
if ( ! rfw_is_woocommerce_active() ) {
	add_action( 'admin_notices', 'rfw_admin_notice_missing_woocommerce' );
	return;
}

/**
 * Declare Resolve plugin compatible with HPOS.
 *
 * @return  void
 */
function rfw_hpos_compatible() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'rfw_hpos_compatible' );

/**
 * RFW_Main class
 */
if ( ! class_exists( 'RFW_Main' ) ) {
	class RFW_Main {

		/**
		 * Instance of the current class, null before first usage.
		 *
		 * @var RFW_Main
		 */
		protected static $instance = null;

		/**
		 * Instance of the RFW_Ajax_Interface class.
		 *
		 * @var RFW_Ajax_Interface
		 */
		private $ajax;

		/**
		 * Class constructor
		 *
		 * @return  void
		 */
		protected function __construct() {
			self::register_constants();

			// Utilites.
			require_once 'includes/utilities/class-rfw-data.php';
			require_once 'includes/utilities/class-rfw-logger.php';

			// Core.
			require_once 'includes/core/class-rfw-payment-gateway.php';
			require_once 'includes/core/class-rfw-ajax-interface.php';

			$this->ajax = new RFW_Ajax_Interface();
			$this->ajax->register();

			add_filter( 'woocommerce_payment_gateways', [ $this, 'register_gateway' ] );
			add_filter( 'plugin_action_links_' . RFW_PLUGIN_BASENAME, [ $this, 'add_settings_link' ] );

			add_action( 'admin_enqueue_scripts', [ $this, 'register_admin_script' ] );

			add_action( 'admin_init', [ $this, 'check_settings' ], 20 );
			add_action( 'admin_init', [ $this, 'check_for_other_resolve_gateways' ], 1 );
			add_action( 'activated_plugin', [ $this, 'set_resolve_plugins_check_required' ] );
			add_action( 'woocommerce_admin_field_payment_gateways', [ $this, 'set_resolve_plugins_check_required' ] );
		}

		/**
		 * Register constants
		 *
		 * @return  void
		 */
		public static function register_constants() {
			if ( ! defined( 'RFW_PLUGIN_ID' ) ) {
				define( 'RFW_PLUGIN_ID', 'resolve-pay-gateway' );
			}
			if ( ! defined( 'RFW_PLUGIN_VERSION' ) ) {
				define( 'RFW_PLUGIN_VERSION', '1.0.6' );
			}
			if ( ! defined( 'RFW_PLUGIN_BASENAME' ) ) {
				define( 'RFW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
			}
			if ( ! defined( 'RFW_DIR_PATH' ) ) {
				define( 'RFW_DIR_PATH', plugin_dir_path( __FILE__ ) );
			}
			if ( ! defined( 'RFW_DIR_URL' ) ) {
				define( 'RFW_DIR_URL', plugin_dir_url( __FILE__ ) );
			}
			if ( ! defined( 'RFW_ADMIN_SETTINGS_URL' ) ) {
				define( 'RFW_ADMIN_SETTINGS_URL', get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&section=' . RFW_PLUGIN_ID ) );
			}
		}

		/**
		 * Add Resolve payment gateway for registering in WooCommerce.
		 *
		 * @param   array  $methods  Array of payment methods registered.
		 *
		 * @return  array            Array of payment methods including Resolve.
		 */
		public function register_gateway( $methods ) {
			$methods[] = 'RFW_Payment_Gateway';
			return $methods;
		}

		/**
		 * Register admin scripts.
		 *
		 * @return  void
		 */
		public function register_admin_script() {
			wp_enqueue_script( 'rfw-admin-js', RFW_DIR_URL . '/assets/dist/js/rfw-admin.js', [ 'jquery' ], RFW_PLUGIN_VERSION, true );

			wp_localize_script(
				'rfw-admin-js',
				'RFWPaymentGateway',
				[
					'ajax_url'       => admin_url( 'admin-ajax.php' ),
					'capture_notice' => __( 'Are you sure you want to capture this payment?', 'resolve' ),
				]
			);
		}

		/**
		 * Adds the link to the settings page on the plugins WP page.
		 *
		 * @param   array  $links  Array of plugin action links.
		 *
		 * @return  array          Edited array of action link including setting link.
		 */
		public function add_settings_link( $links ) {
			$settings_link = '<a href="' . RFW_ADMIN_SETTINGS_URL . '">' . __( 'Settings', 'resolve' ) . '</a>';
			array_unshift( $links, $settings_link );

			return $links;
		}

		/**
		 * Check gateway settings and dispatch notice.
		 *
		 * @return  void
		 */
		public function check_settings() {
			// If payment gateway is not enabled bail.
			if ( ! RFW_Data::enabled() ) {
				return;
			}

			// Check if gateway is currently in test mode.
			if ( RFW_Data::test_mode() ) {
				self::admin_notice( __( 'Resolve Payment Gateway is currently in test mode, disable it for live web shop.', 'resolve' ), 'warning' );
			}

			// Check if all setting keys required for gateway to work are set.
			if ( ! RFW_Data::required_keys_set() ) {
				self::admin_notice( __( 'Resolve is currenly disabled, please check that you have Merchant ID and API key set in the plugin settings.', 'resolve' ), 'warning' );
			}
		}

		/**
		 * Check if there are other Resolve gateways.
		 *
		 * @return  void
		 */
		public static function check_for_other_resolve_gateways() {
			if ( ! get_option( 'resolve_plugins_check_required' ) ) {
				return;
			}

			delete_option( 'resolve_plugins_check_required' );

			// Check if there already is payment method with id "resolve".
			$payment_gateways = WC_Payment_Gateways::instance()->payment_gateways();

			if ( isset( $payment_gateways[ RFW_PLUGIN_ID ] ) && ! $payment_gateways[ RFW_PLUGIN_ID ] instanceof RFW_Payment_Gateway ) {
				self::admin_notice( __( 'You can only have one Resolve payment gateway active at the same time. Plugin "Resolve for WooCommerce" has been deactivated.', 'resolve' ) );

				self::deactivate_self();
			}
		}

		/**
		 * Set that the check for other plugins is required.
		 *
		 * @return  void
		 */
		public static function set_resolve_plugins_check_required() {
			update_option( 'resolve_plugins_check_required', 'yes' );
		}

		/**
		 * Deactivate plugin.
		 *
		 * @return  void
		 */
		public static function deactivate_self() {
			remove_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ self::get_instance(), 'add_settings_link' ] );
			remove_action( 'admin_init', [ self::get_instance(), 'check_settings' ], 20 );

			deactivate_plugins( plugin_basename( __FILE__ ) );
			unset( $_GET['activate'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		/**
		 * Add an admin notice
		 *
		 * @param  string  $notice  Notice content.
		 * @param  string  $type    Notice type.
		 *
		 * @return  void
		 */
		public static function admin_notice( $notice, $type = 'error' ) {
			add_action(
				'admin_notices',
				function() use ( $notice, $type ) {
					printf( '<div class="notice notice-%2$s"><p>%1$s</p></div>', $notice, $type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			);
		}

		/**
		 * Delete gateway settings. Return true if option is successfully deleted or
		 * false on failure or if option does not exist.
		 *
		 * @return bool
		 */
		public static function delete_settings() {
			return delete_option( 'woocommerce_' . RFW_PLUGIN_ID . '_settings' ) && delete_option( 'resolve_plugins_check_required' );
		}


		/**
		 * Install actions.
		 *
		 * @return  bool/void
		 */
		public static function install() {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return false;
			}

			self::set_resolve_plugins_check_required();
			self::register_constants();
		}

		/**
		 * Uninstall actions.
		 *
		 * @return  bool/void
		 */
		public static function uninstall() {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return false;
			}

			wp_cache_flush();
		}

		/**
		 * Deactivation function.
		 *
		 * @return  bool/void
		 */
		public static function deactivate() {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return false;
			}

			wp_cache_flush();
		}

		/**
		 * Return class instance.
		 *
		 * @return RFW_Main
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Cloning is forbidden.
		 *
		 * @return  void
		 */
		public function __clone() {
			return wp_die( 'Cloning is forbidden!' );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 *
		 * @return  void
		 */
		public function __wakeup() {
			return wp_die( 'Unserializing instances is forbidden!' );
		}

	}
}

register_activation_hook( __FILE__, [ 'RFW_Main', 'install' ] );
register_uninstall_hook( __FILE__, [ 'RFW_Main', 'uninstall' ] );
register_deactivation_hook( __FILE__, [ 'RFW_Main', 'deactivate' ] );

add_action( 'plugins_loaded', [ 'RFW_Main', 'get_instance' ], 0 );
