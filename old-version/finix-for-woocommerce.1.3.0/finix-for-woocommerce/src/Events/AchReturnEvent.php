<?php

namespace FinixWC\Events;

use WC_Order;

/**
 * When ACH Direct Debit payment is returned due to a failure,
 * AchReturnEvent will be fired after receiving a webhook even from Finix.
 *
 * @see https://finix.com/docs/guides/payments/online-payments/getting-started/finix-api/ach-echeck/#ach-refund-returns
 */
class AchReturnEvent extends Event {

	private WC_Order $order;

	/**
	 * Init the event.
	 */
	public function __construct( WC_Order $order ) {

		$this->order = $order;
	}

	/**
	 * Process the event.
	 */
	public function process(): void {

		// Exit early if order is locked by a webhook.
		if ( $this->order->get_meta( 'finix_webhook_lock' ) ) {
			exit;
		}

		// Save previous order status before changing it. It will be reused in certain cases.
		$this->order->update_meta_data( 'finix_previous_order_status', $this->order->get_status() );
		$this->order->update_status(
			$this->gateway->ach_return_order_state,
			esc_html__( 'Finix Webhook response: ACH Return received.', 'finix-for-woocommerce' )
		);

		// Lock the order to prevent further duplicate webhook processing.
		$this->order->update_meta_data( 'finix_webhook_lock', true );
		$this->order->save();
	}
}
