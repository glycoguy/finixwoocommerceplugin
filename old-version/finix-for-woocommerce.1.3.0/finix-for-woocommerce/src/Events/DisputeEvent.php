<?php

namespace FinixWC\Events;

use WC_Order;

/**
 * When a dispute is created, DisputeEvent will be fired after receiving a webhook event from Finix.
 *
 * @see https://finix.com/docs/guides/developers/webhooks/webhook-events/#dispute-created
 * @see https://finix.com/docs/guides/developers/webhooks/webhook-events/#dispute-updated
 */
class DisputeEvent extends Event {

	private WC_Order $order;
	private string $state;

	/**
	 * Init the event.
	 */
	public function __construct( WC_Order $order ) {

		$this->order = $order;
	}

	/**
	 * The current state of this event.
	 */
	public function set_state( $state ): DisputeEvent {

		$this->state = sanitize_text_field( $state );

		return $this;
	}

	/**
	 * Process the event.
	 */
	public function process(): void {

		switch ( $this->state ) {
			case 'PENDING':
				$this->process_state_pending();
				break;

			case 'WON':
				$this->process_state_won();
				break;
		}
	}

	/**
	 * Process the dispute when it is in the pending state.
	 * This includes dispute creation, dispute response submission, etc.
	 */
	private function process_state_pending(): void {

		// Avoid subsequent updates to the order status.
		if ( $this->order->get_meta( 'finix_webhook_lock' ) ) {
			exit;
		}

		// Save the previous order status before changing it.
		$this->order->update_meta_data(
			'finix_previous_order_status',
			sanitize_text_field( $this->order->get_status() )
		);

		$this->order->update_status(
			$this->gateway->disputes_order_state,
			esc_html__( 'Finix Webhook response: Dispute received.', 'finix-for-woocommerce' )
		);

		// Lock the order to prevent further duplicate webhook processing.
		$this->order->update_meta_data( 'finix_webhook_lock', true );
		$this->order->save();
	}

	/**
	 * Process the dispute when it is in the WON state.
	 */
	private function process_state_won(): void {

		$this->order->update_status(
			$this->order->get_meta( 'finix_previous_order_status' ),
			esc_html__( 'Finix Webhook response: Dispute won.', 'finix-for-woocommerce' )
		);

		// Remove the lock.
		$this->order->update_meta_data( 'finix_webhook_lock', false );
		$this->order->save();
	}
}
