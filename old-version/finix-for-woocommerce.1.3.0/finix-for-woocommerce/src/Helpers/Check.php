<?php

namespace FinixWC\Helpers;

/**
 * Helper methods to perform various checks across the plugin.
 */
class Check {

	/**
	 * Check whether the string is json-encoded.
	 *
	 * @param string $json A string.
	 *
	 * @return bool
	 */
	public static function is_json( $json ): bool {

		return (
			is_string( $json ) &&
			is_array( json_decode( $json, true ) ) &&
			json_last_error() === JSON_ERROR_NONE
		);
	}

	/**
     * Check whether the site is in the debug mode.
	 */
	public static function is_debug(): bool {

		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Check whether the site is in the script debug mode.
	 */
	public static function is_script_debug(): bool {

		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
	}

	/**
	 * Check whether the site is in the heartbeat mode.
	 */
	public static function is_heartbeat(): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return wp_doing_ajax() && isset( $_POST['action'] ) && $_POST['action'] === 'heartbeat';
	}
}
