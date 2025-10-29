<?php

namespace FinixWC\Events;

use FinixWC\Gateways\FinixGateway;

/**
 * Base event class that has methods that may or may not be used by different Events.
 */
abstract class Event {

	protected FinixGateway $gateway;
	protected string $transaction_id;

	/**
	 * Pass the gateway to the event.
	 */
	public function set_gateway( FinixGateway $gateway ): Event {

		$this->gateway = $gateway;

		return $this;
	}

	/**
	 * Finix' transaction IDs are unique and contain strings and numbers.
	 * Example: TRhb7bDRw4TQwUrGj7zCetVG.
	 *
	 * @see https://finix.com/docs/guides/developers/webhooks/webhook-events/#transfer-created-2
	 */
	public function set_transaction_id( string $transaction_id ): Event {

		$this->transaction_id = sanitize_text_field( $transaction_id );

		return $this;
	}
}
