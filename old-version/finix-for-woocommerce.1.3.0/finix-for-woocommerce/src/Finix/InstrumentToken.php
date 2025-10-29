<?php

namespace FinixWC\Finix;

use FinixWC\Gateways\ApplePayGateway;
use FinixWC\Gateways\BankGateway;
use FinixWC\Gateways\CardGateway;
use FinixWC\Gateways\GooglePayGateway;
use stdClass;

/**
 * Create a Payment Instrument resource using a card or bank account.
 *
 * @see https://docs.finix.com/api/payment-instruments/createpaymentinstrument
 */
class InstrumentToken {

	private string $type;
	private string $token;
	private string $buyer_identity;
	private string $buyer_name;
	private string $merchant_identity;
	private int $order_id;

	/**
	 * Set the type of Payment Instrument.
	 */
	public function set_gateway( string $gateway ): InstrumentToken {

		switch ( sanitize_key( $gateway ) ) {
			case BankGateway::SLUG:
			case CardGateway::SLUG:
				$this->type = 'TOKEN';
				break;

			case ApplePayGateway::SLUG:
				$this->type = 'APPLE_PAY';
				break;

			case GooglePayGateway::SLUG:
				$this->type = 'GOOGLE_PAY';
				break;
		}

		return $this;
	}

	/**
	 * Set the stringified token provided by payment gateway.
	 */
	public function set_token( string $token ): InstrumentToken {

		$this->token = sanitize_text_field( $token );

		return $this;
	}

	/**
	 * Set the Identity#id of the buyer and owner of the card.
	 */
	public function set_buyer_identity( string $buyer_identity ): InstrumentToken {

		$this->buyer_identity = sanitize_text_field( $buyer_identity );

		return $this;
	}

	/**
	 * Set the name of the bank account or card owner.
	 */
	public function set_buyer_name( string $buyer_name ): InstrumentToken {

		$this->buyer_name = sanitize_text_field( $buyer_name );

		return $this;
	}

	/**
	 * Set the Identity#id of the merchant and owner of the Finix account.
	 */
	public function set_merchant_identity( string $merchant_identity ): InstrumentToken {

		$this->merchant_identity = sanitize_text_field( $merchant_identity );

		return $this;
	}

	/**
	 * Set the WooCommerce order ID.
	 */
	public function set_order( $order_id ): InstrumentToken {

		$this->order_id = (int) $order_id;

		return $this;
	}

	/**
	 * Create the token.
	 */
	public function create(): array {

		if ( empty( $this->type ) ) {
			return [
				'status'   => 401,
				'response' => null,
				'error'    => 'Unauthorized',
			];
		}

		$data = new stdClass();

		if ( $this->type === 'TOKEN' ) {
			$data->token = $this->token;
		} else {
			$data->third_party_token = $this->token;
			$data->merchant_identity = $this->merchant_identity;
		}

		$data->identity = $this->buyer_identity;
		$data->type     = $this->type;
		$data->name     = $this->buyer_name;

		$tags = new Tags();

		$tags->add_bulk(
			[
				'order_id'   => $this->order_id,
				'order_date' => gmdate( 'Y-m-d H:i:s' ),
				'source'     => finixwc()->finix_api->custom_source_tag,
			]
		);

		$tags = apply_filters( 'finixwc_api_create_instrumenttoken_tags', $tags );

		$data->tags = $tags->prepare();

		$token = finixwc()->finix_api->get_token();

		if ( empty( $token ) ) {
			return [
				'status'   => 401,
				'response' => null,
				'error'    => 'Unauthorized',
			];
		}

		$response = wp_remote_post(
			finixwc()->finix_api->endpoint::payment_instruments(),
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

		$status   = (int) wp_remote_retrieve_response_code( $response );
		$errors   = wp_remote_retrieve_response_message( $response );
		$response = wp_remote_retrieve_body( $response );

		$return = [
			'status'   => $status,
			'response' => json_decode( $response ),
		];

		if ( $status !== 201 && $errors ) {
			$return['error'] = $errors;
		}

		return $return;
	}
}
