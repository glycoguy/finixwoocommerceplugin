<?php

namespace FinixWC\Finix;

/**
 * Finix API endpoints.
 *
 * @see https://finix.com/docs/api/overview/
 */
class Endpoint {

	protected const TRANSFERS          = '/transfers';
	protected const TRANSFER           = '/transfers/%s';
	protected const TRANSFER_REVERSALS = '/transfers/%s/reversals';

	protected const IDENTITIES = '/identities';

	protected const PAYMENT_INSTRUMENTS = '/payment_instruments';

	protected const APPLE_PAY_SESSIONS = '/apple_pay_sessions';

	protected const MERCHANT = '/merchants/%s';

	/**
	 * Finix API base URL which depends on whether the plugin operates in sandbox or live mode.
	 */
	public static function base_url(): string {

		return finixwc()->finix_api->is_sandbox_mode ? API::SANDBOX_URL : API::LIVE_URL;
	}

	/**
	 * List Transfers.
	 *
	 * @see https://finix.com/docs/api/tag/Transfers/#tag/Transfers/operation/listTransfers
	 */
	public static function transfers(): string {

		return self::base_url() . self::TRANSFERS;
	}

	/**
	 * Fetch a Transfer.
	 *
	 * @see https://finix.com/docs/api/tag/Transfers/#tag/Transfers/operation/getTransfer
	 */
	public static function transfer( string $transfer_id ): string {

		return self::base_url() . sprintf( self::TRANSFER, $transfer_id );
	}

	/**
	 * List Reversals on a Transfer.
	 *
	 * @see https://finix.com/docs/api/tag/Transfers/#tag/Transfers/operation/listTransferReversals
	 */
	public static function transfer_reversals( string $transfer_id ): string {

		return self::base_url() . sprintf( self::TRANSFER_REVERSALS, $transfer_id );
	}

	/**
	 * List Identities.
	 *
	 * @see https://finix.com/docs/api/tag/Identities/#tag/Identities/operation/listIdentities
	 */
	public static function identities(): string {

		return self::base_url() . self::IDENTITIES;
	}

	/**
	 * Payment Instruments.
	 *
	 * @see https://finix.com/docs/api/tag/Payment-Instruments/#tag/Payment-Instruments/operation/listPaymentInstruments
	 */
	public static function payment_instruments(): string {

		return self::base_url() . self::PAYMENT_INSTRUMENTS;
	}

	/**
	 * Payment Instruments.
	 *
	 * @see https://finix.com/docs/api/tag/Payment-Instruments/#tag/Payment-Instruments/operation/createApplePaySession
	 */
	public static function apple_pay_sessions(): string {

		return self::base_url() . self::APPLE_PAY_SESSIONS;
	}

	/**
	 * Fetch a Merchant.
	 *
	 * @link https://finix.com/docs/api/tag/Merchants/#tag/Merchants/operation/getMerchant
	 */
	public static function merchant( string $merchant_id ): string {

		return self::base_url() . sprintf( self::MERCHANT, $merchant_id );
	}
}
