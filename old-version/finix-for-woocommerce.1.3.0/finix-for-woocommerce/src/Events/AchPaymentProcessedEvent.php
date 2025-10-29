<?php

namespace FinixWC\Events;

use Automattic\WooCommerce\Enums\OrderInternalStatus;
use WC_Order;

/**
 * AchPaymentReceivedEvent handles the notification from a webhook
 * that bank/ACH payment was processed by Finix.
 *
 * @see https://finix.com/docs/guides/developers/webhooks/webhook-events/#transfer-created-2
 */
class AchPaymentProcessedEvent extends Event {

	public const STATES = [
		'PENDING',
		'SUCCEEDED',
		'FAILED',
	];

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
	public function set_state( $state ): AchPaymentProcessedEvent {

		$this->state = sanitize_text_field( $state );

		return $this;
	}

	/**
	 * Process the payment.
	 */
	public function process(): void { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		if ( ! in_array( $this->state, self::STATES, true ) ) {
			exit;
		}

		// Do not process the webhook if it's locked by a different webhook event.
		if ( $this->order->get_meta( 'finix_webhook_lock' ) ) {
			exit;
		}

		if ( $this->order->is_paid() ) {
			exit;
		}

		if ( empty( $this->transaction_id ) ) {
			$this->state = 'FAILED';
		}

		$this->order->update_meta_data( 'finix_transaction_id', $this->transaction_id );
		$this->order->save();

		switch ( $this->state ) {
			case 'SUCCEEDED':
				$this->order->add_order_note(
					esc_html__( 'Finix payment confirmed.', 'finix-for-woocommerce' )
				);
				$this->order->payment_complete( $this->transaction_id );
				wc_reduce_stock_levels( $this->order->get_id() );
				break;

			case 'FAILED':
				$this->order->update_status(
					OrderInternalStatus::FAILED,
					esc_html__( 'Finix response: payment failed.', 'finix-for-woocommerce' )
				);
				break;

			case 'PENDING':
			default:
				$this->order->update_status(
					OrderInternalStatus::ON_HOLD,
					esc_html__( 'Finix response: payment confirmation pending.', 'finix-for-woocommerce' )
				);
				break;
		}
	}
}
