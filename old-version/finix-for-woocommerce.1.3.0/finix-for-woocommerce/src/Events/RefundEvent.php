<?php

namespace FinixWC\Events;

use WC_Order;
use WP_Error;

/**
 * Refund event for both Card/Bank and Apple Pay gateways.
 */
class RefundEvent extends Event {

	/**
	 * Order ID.
	 *
	 * @var int
	 */
	private int $order_id;

	/**
	 * Order object.
	 *
	 * @var WC_Order
	 */
	private WC_Order $order;

	/**
	 * Refund reason.
	 *
	 * @var string
	 */
	private string $reason;

	/**
	 * Refund amount.
	 *
	 * @var int
	 */
	private int $amount;

	/**
	 * Init the event.
	 */
	public function __construct( $order_id ) {

		$this->order_id = absint( $order_id );
		$this->order    = wc_get_order( $this->order_id );
	}

	/**
	 * Pass the refund amount to the event.
	 */
	public function set_amount( $amount ): RefundEvent {

		// phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$amount = $amount ?: $this->order->get_total();

		$this->amount = (int) (string) ( $amount * 100 );

		return $this;
	}

	/**
	 * Pass the refund reason to the event.
	 */
	public function set_reason( string $reason ): RefundEvent {

		$this->reason = $reason;

		return $this;
	}

	/**
	 * Process the refund.
	 *
	 * @return bool|WP_Error True or false based on success, or a WP_Error object.
	 */
	public function process() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$transaction_id = $this->order->get_meta( 'finix_transaction_id' );

		if ( ! $transaction_id ) {
			return new WP_Error(
				'finix_refund_error',
				esc_html__( 'No transaction ID found for this order', 'finix-for-woocommerce' )
			);
		}

		$response = finixwc()->finix_api->refund_payment( $transaction_id, $this->amount, $this->order_id, $this->reason );

		if ( $response['status'] !== 201 ) {
			return new WP_Error(
				'finix_refund_error',
				sprintf( /* translators: %s: Error message from Finix. */
					esc_html__( 'Finix Refund failed: %s', 'finix-for-woocommerce' ),
					wp_json_encode( $response['response']->_embedded->errors )
				)
			);
		}

		$response_amount = ( $response['response'] && ! empty( $response['response']->amount ) ) ? ( $response['response']->amount / 100 ) : $this->amount;

		$this->order->add_order_note(
			sprintf( /* translators: %1$s: Refund amount, %2$s: Transaction ID. */
				esc_html__( 'Refund processed successfully. Amount: %1$s. Transaction ID: %2$s.', 'finix-for-woocommerce' ),
				$response_amount,
				$response['response']->id
			)
		);

		$this->order->update_meta_data( 'finix_refund_id:' . time(), sanitize_text_field( $response['response']->id ) );
		$this->order->save();

		return true;
	}
}
