<?php

namespace FinixWC\Events;

use FinixWC\Helpers\Convert;

/**
 * GooglePayPaymentEvent processes the actual payment via Finix and Google Pay.
 * It's triggered on the checkout page before the order is created.
 */
class GooglePayPaymentEvent extends Event {

	private array $billing;
	private int $order_id;
	private string $order_key;
	private string $payment_token;
	private string $merchant_identity;

	/**
	 * Set the billing data used to create a Finix Buyer.
	 */
	public function set_billing( array $billing ): GooglePayPaymentEvent {

		$this->billing = $billing;

		return $this;
	}

	/**
	 * Set the billing data used to create a Finix Buyer.
	 */
	public function set_token( string $token ): GooglePayPaymentEvent {

		$this->payment_token = $token;

		return $this;
	}

	/**
	 * Set the billing data used to create a Finix Buyer.
	 */
	public function set_merchant( string $identity ): GooglePayPaymentEvent {

		$this->merchant_identity = $identity;

		return $this;
	}

	/**
	 * Set the order information which is present only on the Checkout Pay Order page.
	 * So it may be empty on a regular /checkout/.
	 */
	public function set_order( $order_id, $order_key ): GooglePayPaymentEvent {

		$this->order_id  = ! empty( $order_id ) && is_numeric( $order_id ) ? absint( $order_id ) : 0;
		$this->order_key = ! empty( $order_key ) && is_string( $order_key ) ? wp_unslash( $order_key ) : '';

		return $this;
	}

	/**
	 * Process the event.
	 */
	public function process(): void {

		// Create a new Buyer based on the billing info provided.
		$buyer = finixwc()->finix_api
			->new_buyer()
			->with_raw_data( $this->billing )
			->create();

		if (
			$buyer['status'] !== 201 ||
			! is_object( $buyer['response'] ) ||
			empty( $buyer['response']->id )
		) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'There was an error while processing your payment.', 'finix-for-woocommerce' ),
					'errors'  => wp_json_encode( $buyer['response']->_embedded->errors ),
				]
			);
			die();
		}

		$buyer_id                 = $buyer['response']->id;
		$payment_instrument_token = $this->payment_token;
		$merchant_identity        = $this->merchant_identity;

		// We might not have a cart over here if on the Checkout Order Pay page,
		// so retrieve the data from the current order page.
		if (
			! empty( $this->order_id ) &&
			! empty( $this->order_key )
		) {
			$order = wc_get_order( $this->order_id );

			if ( $this->order_id === $order->get_id() && hash_equals( $order->get_order_key(), $this->order_key ) ) {
				$order_amount = $order->get_total();
			} else {
				wp_send_json_error(
					[
						'message' => esc_html__( 'There was an error processing the payment. Please try again later.', 'finix-for-woocommerce' ),
					]
				);
				die();
			}
		} else {
			$order_amount = WC()->cart->get_total( '' );
		}

		$payment_instrument_feedback = finixwc()->finix_api->create_instrument_token()
															->set_token( $payment_instrument_token )
															->set_merchant_identity( $merchant_identity )
															->set_buyer_identity( $buyer_id )
															->set_buyer_name( $this->billing['first_name'] . ' ' . $this->billing['last_name'] )
															->set_gateway( $this->gateway::SLUG )
															->set_order( $this->order_id )
															->create();

		if (
			$payment_instrument_feedback['status'] !== 201 ||
			! is_object( $payment_instrument_feedback['response'] ) ||
			empty( $payment_instrument_feedback['response']->id )
		) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'There was an error processing the payment.', 'finix-for-woocommerce' ),
					'errors'  => wp_json_encode( $payment_instrument_feedback['response']->_embedded->errors ),
				]
			);
			die();
		}

		$response = finixwc()->finix_api->make_payment(
			(int) ( Convert::amount_to_number( $order_amount ) * 100 ),
			get_woocommerce_currency(),
			$payment_instrument_feedback['response']->id
		);

		if ( $response['status'] !== 201 ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'There was an error while processing the payment. Please try again later.', 'finix-for-woocommerce' ),
					'errors'  => wp_json_encode( $response['response']->_embedded->errors ),
				]
			);
			die();
		}

		// No need to pass the whole response to the client - only the required data.
		wp_send_json(
			[
				'status'   => $response['status'],
				'response' => [
					'id' => $response['response']->id,
				],
			],
			$response['status']
		);
	}
}
