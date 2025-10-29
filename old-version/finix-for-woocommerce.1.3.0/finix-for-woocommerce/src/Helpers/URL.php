<?php

namespace FinixWC\Helpers;

/**
 * Class URL.
 */
class URL {

	/**
	 * Add UTM tags to a link that allows detecting traffic sources for our or partners' websites.
	 *
	 * @param string $url     Link to which you need to add UTM tags.
	 * @param string $medium  The page or location description. Example: "plugin_admin".
	 * @param string $content The feature's name, the button's content, the link's text. Example: "logo".
	 */
	public static function add_utm( string $url, string $medium, string $content = '' ): string {

		return add_query_arg(
			array_filter(
				[
					'utm_campaign' => 'WordPress',
					'utm_source'   => 'FinixForWooCommerce',
					'utm_medium'   => rawurlencode( $medium ),
					'utm_content'  => rawurlencode( $content ),
				]
			),
			$url
		);
	}
}
