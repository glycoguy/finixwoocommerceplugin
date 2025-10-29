<?php

namespace FinixWC\Events;

use WC_Order;

/**
 * ApplePayPaymentCompletedEvent is fired after the actual payment is completed.
 * We are linking the Finix transaction to the newly created WooCommerce order.
 */
class ApplePayPaymentCompletedEvent extends Event {

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
	public function process(): array {

		// phpcs:disable WordPress.Security.NonceVerification.Missing

		// Unslash the post data and sanitize it.
		$is_success     = sanitize_text_field( wp_unslash( $_POST['finix_apple_pay_success'] ?? 'false' ) );
		$transaction_id = sanitize_text_field( wp_unslash( $_POST['finix_apple_pay_transaction_id'] ?? '' ) );

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( $is_success !== 'true' || empty( $transaction_id ) ) {
			wc_add_notice(
				esc_html__( 'Payment error: There was an error while processing your payment. Please try again.', 'finix-for-woocommerce' ),
				'error'
			);

			$this->gateway->log(
				'Finix Payment error: Could not validate payment. Apple Pay returned empty transaction ID.',
				'error',
				[
					'order_id'       => $this->order->get_id(),
					'transaction_id' => $transaction_id,
				]
			);

			return $this->processing_status();
		}

		$this->gateway->log( 'Finix Apple Pay Payment came with transaction ID: ' . $transaction_id, 'debug' );

		$transaction_details = finixwc()->finix_api->get_transfer( $transaction_id );

		if ( $transaction_details['status'] === 200 ) {
			$tags = [];

			$tags['order_id'] = $this->order->get_id();
			$coupons          = $this->order->get_coupon_codes();

			if ( ! empty( $coupons ) ) {
				$tags['order_coupons'] = implode( ',', $coupons );
			}

			finixwc()->finix_api->update_transfer_with_tags( $transaction_id, $tags );
		}

		if ( $transaction_details['status'] !== 200 ) {
			wc_add_notice(
				esc_html__( 'Payment error: There was an error while processing your payment. Please contact support if you were charged.', 'finix-for-woocommerce' ),
				'error'
			);

			$this->gateway->log( 'Payment error: Could not validate payment. Could not fetch transaction details.', 'debug' );

			return $this->processing_status();
		}

		if ( empty( $transaction_details['response']->id ) ) {
			wc_add_notice(
				esc_html__( 'Payment error: There was an error while processing your payment. Please contact support if you were charged.', 'finix-for-woocommerce' ),
				'error'
			);

			$this->gateway->log( 'Finix Apple Pay Payment error: Could not validate payment. Transaction details returned empty.', 'debug' );

			return $this->processing_status();
		}

		if ( $transaction_details['response']->state !== 'SUCCEEDED' ) {
			wc_add_notice(
				esc_html__( 'Payment error: There was an error while processing your payment. Please contact support if you were charged.', 'finix-for-woocommerce' ),
				'error'
			);

			$this->gateway->log( 'Payment error: Could not validate payment. Transaction state is: ' . esc_html( $transaction_details['response']->state ), 'debug' );

			return $this->processing_status();
		}

		// Do not allow tinkering with amounts on the front-end.
		if ( (int) $transaction_details['response']->amount !== (int) ( $this->order->get_total() * 100 ) ) {
			wc_add_notice(
				esc_html__( 'Payment error: There was an error while processing your payment. Please contact support if you were charged.', 'finix-for-woocommerce' ),
				'error'
			);

			$this->order->add_order_note(
				esc_html__( 'Payment error: Could not validate payment. Amounts do not match.', 'finix-for-woocommerce' )
			);

			return $this->processing_status();
		}

		$this->order->add_order_note(
			esc_html__( 'Finix Apple Pay payment SUCCEEDED', 'finix-for-woocommerce' )
		);

		$this->order->payment_complete( $transaction_id );

		wc_reduce_stock_levels( $this->order->get_id() );

		$this->order->update_meta_data( 'finix_transaction_id', sanitize_text_field( $transaction_details['response']->id ) );
		$this->order->save();

		WC()->cart->empty_cart();

		return $this->processing_status( 'success', $this->gateway->get_return_url( $this->order ) );
	}

	/**
	 * Return result status.
	 * Default status is an error without a redirect.
	 *
	 * @param string $status   Stats of the result.
	 * @param string $redirect URL to redirect.
	 */
	private function processing_status( string $status = 'failure', string $redirect = '' ): array {

		if ( ! in_array( $status, [ 'success', 'error' ], true ) ) {
			$status = 'failure';
		}

		if ( empty( $redirect ) ) {
			$status = '';
		}

		return [
			'result'   => $status,
			'redirect' => $redirect,
		];
	}
}
