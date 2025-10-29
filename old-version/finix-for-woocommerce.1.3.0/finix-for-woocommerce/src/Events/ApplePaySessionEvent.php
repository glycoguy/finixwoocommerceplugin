<?php

namespace FinixWC\Events;

/**
 * ApplePaySessionEvent processes the request for a new payment session from Apple Pay.
 */
class ApplePaySessionEvent extends Event {

	private array $params;

	/**
	 * Session Request requires some parameters to be passed.
	 */
	public function set_session_data( array $params ): ApplePaySessionEvent {

		$this->params['merchant_identity'] = sanitize_text_field( $params['merchant_identity'] );
		$this->params['domain_name']       = sanitize_text_field( $params['domain_name'] );
		$this->params['validation_url']    = sanitize_url( $params['validation_url'] );
		$this->params['display_name']      = sanitize_text_field( $params['display_name'] );

		return $this;
	}

	/**
	 * Process the event.
	 */
	public function process(): void {

		$response = finixwc()->finix_api->get_apple_pay_session(
			$this->params['merchant_identity'],
			$this->params['domain_name'],
			$this->params['validation_url'],
			$this->params['display_name']
		);

		if ( ! in_array( $response['status'], [ 200, 201 ], true ) ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'There was an error processing the payment.', 'finix-for-woocommerce' ),
					'errors'  => wp_json_encode( $response['response']->_embedded->errors ),
				]
			);
			die();
		}

		if ( empty( $response['response']->session_details ) ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'There was an error processing the payment.', 'finix-for-woocommerce' ),
					'errors'  => wp_json_encode( $response['response']->_embedded->errors ),
				]
			);
			die();
		}

		// All seems to be good, return response to the client.
		wp_send_json( $response );
	}
}
