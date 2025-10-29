<?php

namespace FinixWC;

/**
 * Options class.
 */
class Options {

	/**
	 * Used to store or prefix plugin options.
	 *
	 * @var string
	 */
	public const KEY = 'finix';

	/**
	 * Plugin options.
	 *
	 * @var array
	 */
	private $options = [];

	/**
	 * Retrieve a secret key of a certain type.
	 *
	 * @param string $type The type of secret key to retrieve.
	 */
	public function get_secret( string $type ): string {

		switch ( $type ) {
			case 'payments':
				$key = $this->generate_payments_secret_key();
				break;

			case 'webhooks':
				$key = $this->generate_webhooks_secret_key();
				break;

			default:
				$key = '';
		}

		return $key;
	}

	/**
	 * Generate and store secret used during payments processing.
	 */
	private function generate_payments_secret_key(): string {

		$secret = get_option( self::KEY . '_payments_secret_key', '' );

		if ( ! $secret ) {
			// Generate a 32-byte (256-bit) secret.
			$secret = bin2hex( random_bytes( 32 ) );

			update_option(
				self::KEY . '_payments_secret_key',
				$secret,
				false
			);
		}

		return $secret;
	}

	/**
	 * Generate and store secret used during payments processing.
	 */
	private function generate_webhooks_secret_key(): string {

		$secret = get_option( self::KEY . '_payments_webhooks_secret_key', '' );

		if ( ! $secret ) {
			// Generate a 24-byte (256-bit) secret.
			$secret = bin2hex( random_bytes( 24 ) );

			update_option(
				self::KEY . '_payments_webhooks_secret_key',
				$secret,
				false
			);
		}

		return $secret;
	}
}
