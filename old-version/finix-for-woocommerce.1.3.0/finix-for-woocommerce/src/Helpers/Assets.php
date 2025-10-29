<?php

namespace FinixWC\Helpers;

/**
 * Class Assets to help manage assets.
 */
class Assets {

	public const FINIX_SDK_JS_URL = 'https://js.finix.com/v/1/3/2/finix.js';
	public const APPLE_SDK_JS_URL = 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js';

	public const GOOGLE_SDK_JS_URL = 'https://pay.google.com/gp/p/js/pay.js';

	/**
	 * Based on the SCRIPT_DEBUG const adds or not the `.min` to the file name, without dir path modification.
	 * Usage: `Assets::min( 'script.js' );` => `script.min.js`.
	 *
	 * @param string $file Filename with an extension: alpine.js or tailwind.css, or jquery.plugin.js.
	 */
	public static function min( string $file ): string {

		$chunks = explode( '.', $file );
		$ext    = (array) array_pop( $chunks );
		$min    = Check::is_script_debug() ? [] : [ 'min' ];

		return implode( '.', array_merge( $chunks, $min, $ext ) );
	}

	/**
	 * Define the version of an asset.
	 *
	 * @param string $current Default value.
	 *
	 * @return string Either the defined version, plugin version if not provided, or time() when in SCRIPT_DEBUG mode.
	 */
	public static function ver( string $current = '' ): string {

		if ( empty( $current ) ) {
			$current = FINIXWC_VERSION;
		}

		return Check::is_script_debug() ? time() : $current;
	}

	/**
	 * Get the URL to a file by its name.
	 *
	 * @param string $file   File name relative to /assets/ directory in the plugin.
	 * @param bool   $minify Whether the file URL should lead to a minified file.
	 *
	 * @return string URL to the file.
	 */
	public static function url( string $file, bool $minify = true ): string {

		$file = trim( $file, '/\\' );

		if ( $minify ) {
			$file = self::min( $file );
		}

		return plugins_url( '/assets/' . $file, FINIXWC_PLUGIN_FILE );
	}

	/**
	 * Get the content of the SVG file.
	 *
	 * @param string $file SVG file content to retrieve.
	 */
	public static function svg( string $file ): string {

		$file = untrailingslashit( sanitize_file_name( $file ) );

		$path = FINIXWC_PLUGIN_DIR . 'assets/' . $file;

		if ( is_readable( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return (string) file_get_contents( $path );
		}

		return '';
	}
}
