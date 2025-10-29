<?php

namespace FinixWC\Events;

/**
 * Event to register the domain.
 */
class ApplePayRegisterDomain {

	/**
	 * Process the event.
	 */
	public function process() { // phpcs:ignore Generic.Metrics.NestingLevel.MaxExceeded

		$merchant_info = finixwc()->finix_api->get_merchant_info();

		if ( $merchant_info['status'] !== 200 ) {
			// Return a JSON with custom message and errors received from Finix.
			wp_send_json_error(
				[
					'message' => __( 'Unable to retrieve merchant information, please do a manual registration.', 'finix-for-woocommerce' ),
					'errors'  => wp_json_encode( $merchant_info['response']->_embedded->errors ),
				]
			);

			die();
		}

		if ( empty( $merchant_info['response']->id ) ) {
			// Return a JSON with custom message and errors received from Finix.
			wp_send_json_error(
				[
					'message' => __( 'Unable to retrieve merchant information, please do a manual registration.', 'finix-for-woocommerce' ),
					'errors'  => wp_json_encode( $merchant_info['response']->_embedded->errors ),
				]
			);

			die();
		}

		if ( ! empty( $merchant_info['response'] ) && ! empty( $merchant_info['response']->identity ) ) {
			$merchant_identity  = $merchant_info['response']->identity;
			$apple_pay_register = finixwc()->finix_api->register_apple_pay_domain( $merchant_identity );

			if ( $apple_pay_register['status'] !== 201 && $apple_pay_register['status'] !== 200 ) {
				// Validate if errors contains the domain name and "already enabled".
				if (
					! empty( $apple_pay_register['response']->_embedded->errors ) &&
					is_array( $apple_pay_register['response']->_embedded->errors )
				) {
					foreach ( $apple_pay_register['response']->_embedded->errors as $error ) {
						if ( strpos( $error->message, 'already enabled' ) !== false ) {
							// Return a JSON with custom message and errors received from Finix.
							wp_send_json_success(
								[
									'message' => __( 'Domain already registered.', 'finix-for-woocommerce' ),
									'errors'  => wp_json_encode( $apple_pay_register['response']->_embedded->errors ),
								]
							);

							die();
						}
					}
				}
				// Return a JSON with custom message and errors received from Finix.
				wp_send_json_error(
					[
						'message' => __( 'Unable to register domain, please do a manual registration.', 'finix-for-woocommerce' ),
						'errors'  => wp_json_encode( $apple_pay_register['response']->_embedded->errors ),
					]
				);

				die();

			} elseif ( ! empty( $apple_pay_register['response'] ) && ! empty( $apple_pay_register['response']->id ) ) {
				// Return a JSON with custom message and errors received from Finix.
				wp_send_json_success(
					[
						'message' => __( 'Domain was registered successfully.', 'finix-for-woocommerce' ),
						'domain'  => $apple_pay_register['response']->domain,
					]
				);

				die();
			} else {
				// Return a JSON with custom message and errors received from Finix.
				wp_send_json_error(
					[
						'message' => __( 'Unable to register domain, please do a manual registration.', 'finix-for-woocommerce' ),
						'errors'  => wp_json_encode( $apple_pay_register['response']->_embedded->errors ),
					]
				);

				die();
			}
		} else {
			// Return a JSON with custom message and errors received from Finix.
			wp_send_json_error(
				[
					'message' => __( 'Unable to retrieve merchant information, please do a manual registration.', 'finix-for-woocommerce' ),
					'errors'  => wp_json_encode( $merchant_info['response']->_embedded->errors ),
				]
			);

			die();
		}
	}
}
