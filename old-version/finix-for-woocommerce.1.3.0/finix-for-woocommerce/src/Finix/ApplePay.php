<?php

namespace FinixWC\Finix;

use stdClass;

trait ApplePay {

	/**
	 * Register domain for Apple Pay.
	 *
	 * @param string $merchant_id Unique merchant identifier.
	 */
	public function register_apple_pay_domain( string $merchant_id ) {

		// Get the domain from the site, validating and unslashing input.
		if ( isset( $_SERVER['SERVER_NAME'] ) && ! empty( $_SERVER['SERVER_NAME'] ) ) {
			$domain = sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) );
		} elseif ( isset( $_SERVER['HTTP_HOST'] ) && ! empty( $_SERVER['HTTP_HOST'] ) ) {
			$domain = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		} else {
			// Default or error handling if neither is available.
			$domain = '';
		}

		// Sanitize the domain to remove unwanted characters.
		$domain = sanitize_text_field( $domain );

		// Clean domain to avoid adding http, https, or other parameters.
		$domain = preg_replace( '/^http(s)?:\/\//', '', $domain );
		$domain = preg_replace( '/\/.*$/', '', $domain );
		$domain = preg_replace( '/:\d+$/', '', $domain );

		if ( empty( $domain ) ) {
			return [
				'status' => 400,
				'error'  => __( 'Domain not found', 'finix-for-woocommerce' ),
			];
		}

		$data                      = new stdClass();
		$data->type                = 'APPLE_PAY';
		$data->merchant_identity   = $merchant_id;
		$data->domains             = [];
		$data->domains[0]          = new stdClass();
		$data->domains[0]->name    = $domain;
		$data->domains[0]->enabled = true;

		// TODO: Isolate this to a constant in Endpoint class. Can't find it here https://docs.finix.com/api.
		$url  = $this->is_sandbox_mode ? self::SANDBOX_URL : self::LIVE_URL;
		$url .= '/payment_method_configurations';

		$token = $this->get_token();

		if ( empty( $token ) ) {
			return [
				'status'   => 401,
				'response' => null,
				'error'    => 'Unauthorized',
			];
		}

		$response = wp_remote_post(
			$url,
			[
				'headers' => [
					'Accept'        => 'application/hal+json',
					'Content-Type'  => 'application/json',
					'Finix-Version' => API::FINIX_API_VERSION,
					'Authorization' => 'Basic ' . $token,
				],
				'body'    => wp_json_encode( $data ),
				'timeout' => 15,
			]
		);

		$status   = wp_remote_retrieve_response_code( $response );
		$errors   = wp_remote_retrieve_response_message( $response );
		$response = wp_remote_retrieve_body( $response );

		$return = [
			'status'   => $status,
			'response' => json_decode( $response ),
		];

		if ( $status !== 200 && $status !== 201 && $errors ) {
			$return['error'] = $errors;
		}

		return $return;
	}

	/**
	 * Get the Apple Pay session.
	 */
	public function get_apple_pay_session( $merchant_identity, $domain, $validation_url, $merchant_name ): array {

		$data                    = new stdClass();
		$data->display_name      = $merchant_name;
		$data->domain            = $domain;
		$data->merchant_identity = $merchant_identity;
		$data->validation_url    = $validation_url;

		$token = $this->get_token();

		if ( empty( $token ) ) {
			return [
				'status'   => 401,
				'response' => null,
				'error'    => 'Unauthorized',
			];
		}

		$response = wp_remote_post(
			finixwc()->finix_api->endpoint::apple_pay_sessions(),
			[
				'headers' => [
					'Content-Type'  => 'application/json',
					'Finix-Version' => API::FINIX_API_VERSION,
					'Authorization' => 'Basic ' . $token,
				],
				'body'    => wp_json_encode( $data ),
				'timeout' => 15,
			]
		);

		$status   = wp_remote_retrieve_response_code( $response );
		$errors   = wp_remote_retrieve_response_message( $response );
		$response = wp_remote_retrieve_body( $response );
		$return   = [
			'status'   => $status,
			'response' => json_decode( $response ),
		];

		if ( $status !== 200 && $errors ) {
			$return['error'] = $errors;
		}

		return $return;
	}
}
