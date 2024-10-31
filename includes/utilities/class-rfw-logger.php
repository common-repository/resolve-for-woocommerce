<?php
defined( 'ABSPATH' ) || exit;

/**
 * RFW_Logger class
 */
class RFW_Logger {
	/**
	 * List of valid logger levels, from most to least urgent.
	 *
	 * @var array
	 */
	public static $log_levels = [
		'emergency',
		'alert',
		'critical',
		'error',
		'warning',
		'notice',
		'info',
		'debug',
	];

	/**
	 * Logs given message for given level and return true if successful, false
	 * otherwise.
	 *
	 * @param  string  $message
	 * @param  string  $level    Check $log_levels for valid level values, defaults to 'info'.
	 *
	 * @return bool
	 */
	public static function log( $message, $level = 'info' ) {
		if ( 'yes' !== RFW_Data::get_settings( 'use-logger' ) ) {
			return false;
		}

		// Check if WooCommerce logger function exists.
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
		} else {
			return false;
		}

		// Check if provided level is valid and fall back to 'notice' level if not.
		if ( ! in_array( $level, self::$log_levels, true ) ) {
			self::log( 'Invalid log level provided: ' . $level, 'debug' );
			$level = 'notice';
		}

		$logger->log( $level, 'Resolve v' . RFW_PLUGIN_VERSION . ' - ' . $message, [ 'source' => RFW_PLUGIN_ID ] );

		return true;
	}
}
