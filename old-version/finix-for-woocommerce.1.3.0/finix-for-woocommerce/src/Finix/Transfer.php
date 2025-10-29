<?php

namespace FinixWC\Finix;

use stdClass;

trait Transfer {

	/**
	 * Retrieve Finix Transfer (payment) details by its ID.
	 *
	 * @see https://finix.com/docs/api/tag/Transfers/#tag/Transfers/operation/getTransfer
	 */
	public function get_transfer( string $transfer_id ): array {

		$token = $this->get_token();

		if ( empty( $token ) ) {
			return [
				'status'   => 401,
				'response' => null,
				'error'    => 'Unauthorized',
			];
		}

		$response = wp_remote_get(
			$this->endpoint::transfer( $transfer_id ),
			[
				'headers' => [
					'Content-Type'  => 'application/json',
					'Finix-Version' => self::FINIX_API_VERSION,
					'Authorization' => 'Basic ' . $token,
				],
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
	 * Update Finix Transfer (payment) with tags.
	 * The tags you passed are added to the existing tags.
	 *
	 * @param string $transfer_id Transfer ID for which we want to update the tags.
	 * @param array  $raw_tags    Passing the same key in the array will override an existing tag if it exists.
	 */
	public function update_transfer_with_tags( string $transfer_id, array $raw_tags ): array {

		if ( empty( $raw_tags ) ) {
			return [
				'status'   => 400,
				'response' => null,
				'error'    => 'Bad Request',
			];
		}

		$token = $this->get_token();

		if ( empty( $token ) ) {
			return [
				'status'   => 401,
				'response' => null,
				'error'    => 'Unauthorized',
			];
		}

		$tags = new Tags();

		$tags->add_bulk( $raw_tags );

		$tags = apply_filters( 'finixwc_api_update_transfer_tags', $tags, $transfer_id );

		$data       = new stdClass();
		$data->tags = $tags->prepare();

		$response = wp_safe_remote_request(
			$this->endpoint::transfer( $transfer_id ),
			[
				'method'  => 'PUT',
				'headers' => [
					'Content-Type'  => 'application/json',
					'Finix-Version' => self::FINIX_API_VERSION,
					'Authorization' => 'Basic ' . $token,
				],
				'body'    => wp_json_encode( $data ),
				'timeout' => 15,
			]
		);

		$status   = wp_remote_retrieve_response_code( $response );
		$response = wp_remote_retrieve_body( $response );

		return [
			'status'   => $status,
			'response' => json_decode( $response ),
		];
	}
}
