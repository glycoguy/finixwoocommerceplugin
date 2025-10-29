<?php

namespace FinixWC\Finix;

use FinixWC\Helpers\Convert;
use stdClass;
use WC_Abstract_Order;

/**
 * BuyerIdentity handles the creation of buyer identities.
 */
class BuyerIdentity {

	protected $entity;
	protected Tags $tags;

	protected WC_Abstract_Order $order;

	/**
	 * Constructor for the class.
	 */
	public function __construct() {

		$this->entity = new stdClass();
		$this->tags   = new Tags();
	}

	/**
	 * Inject a WooCommerce order to get all the data from.
	 *
	 * @param WC_Abstract_Order $order WooCommerce order object.
	 *
	 * @see CardAchPaymentEvent::process
	 */
	public function with_order( WC_Abstract_Order $order ): BuyerIdentity {

		$this->order = $order;

		$this->entity->email                         = $order->get_billing_email();
		$this->entity->first_name                    = $order->get_billing_first_name();
		$this->entity->last_name                     = $order->get_billing_last_name();
		$this->entity->phone                         = $order->get_billing_phone();
		$this->entity->personal_address              = new stdClass();
		$this->entity->personal_address->city        = $order->get_billing_city();
		$this->entity->personal_address->country     = Convert::country_code_2_to_3( $order->get_billing_country() );
		$this->entity->personal_address->line1       = $order->get_billing_address_1();
		$this->entity->personal_address->line2       = $order->get_billing_address_2();
		$this->entity->personal_address->postal_code = $order->get_billing_postcode();
		$this->entity->personal_address->region      = $order->get_billing_state();

		$this->tags->add_bulk(
			[
				'order_id'   => $this->order->get_id(),
				'order_date' => $this->order->get_date_created(),
				'user_id'    => $this->order->get_customer_id(),
			]
		);

		return $this;
	}

	/**
	 * Inject raw data to get all the data from.
	 *
	 * @param array $data Raw data array, mainly billing information.
	 */
	public function with_raw_data( array $data ): BuyerIdentity {

		$this->entity->email                         = $data['email'] ?? '';
		$this->entity->first_name                    = $data['first_name'] ?? '';
		$this->entity->last_name                     = $data['last_name'] ?? '';
		$this->entity->phone                         = $data['phone'] ?? '';
		$this->entity->personal_address              = new stdClass();
		$this->entity->personal_address->city        = $data['city'] ?? '';
		$this->entity->personal_address->country     = Convert::country_code_2_to_3( $data['country'] );
		$this->entity->personal_address->line1       = $data['address_1'] ?? '';
		$this->entity->personal_address->line2       = $data['address_2'] ?? '';
		$this->entity->personal_address->postal_code = $data['postcode'] ?? '';
		$this->entity->personal_address->region      = $data['state'] ?? '';

		$this->tags->add_bulk(
			[
				'order_id'   => '',
				'order_date' => gmdate( 'Y-m-d H:i:s' ),
				'user_id'    => $this->get_user_id_from_email( $this->entity->email ),
			]
		);

		return $this;
	}

	/**
	 * Create a buyer identity using Finix API.
	 */
	public function create(): array {

		$token = finixwc()->finix_api->get_token();

		if ( empty( $token ) ) {
			return [
				'status'   => 401,
				'response' => null,
				'error'    => 'Unauthorized',
			];
		}

		// phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$this->tags->add( 'user_id', $this->tags->get( 'user_id' ) ?: get_current_user_id() );
		$this->tags->add( 'source', finixwc()->finix_api->custom_source_tag );

		$this->tags = apply_filters( 'finixwc_api_create_buyeridentity_tags', $this->tags, $this->entity );

		$data = new stdClass();

		$data->entity = $this->entity;
		$data->tags   = $this->tags->prepare();

		$response = wp_remote_post(
			finixwc()->finix_api->endpoint::identities(),
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

		$status   = (int) wp_remote_retrieve_response_code( $response );
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
	 *  Get user ID using email address from billing info.
	 */
	private function get_user_id_from_email( string $email ): int {

		if ( empty( $email ) ) {
			return 0;
		}

		$user = get_user_by( 'email', $email );

		if ( ! $user || ! $user->ID ) {
			return 0;
		}

		return $user->ID;
	}
}
