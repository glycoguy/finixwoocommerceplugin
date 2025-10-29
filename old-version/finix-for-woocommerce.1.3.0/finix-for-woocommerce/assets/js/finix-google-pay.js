var FinixGooglePay = {
	paymentsClient: null,
	baseRequest: { apiVersion: 2, apiVersionMinor: 0 },
	environment: finix_google_pay_params.environment === 'sandbox' ? 'TEST' : 'PRODUCTION',
	allowedCardNetworks: [ 'AMEX', 'DISCOVER', 'INTERAC', 'JCB', 'MASTERCARD', 'VISA' ],
	allowedCardAuthMethods: [ 'PAN_ONLY', 'CRYPTOGRAM_3DS' ],
	tokenSpec: {
		type: 'PAYMENT_GATEWAY',
		parameters: {
			gateway: 'finix',
			gatewayMerchantId: finix_google_pay_params.merchant_identity,
		},
	},
	baseCardMethod: {
		type: 'CARD',
		parameters: {
			allowedAuthMethods: [ 'PAN_ONLY', 'CRYPTOGRAM_3DS' ],
			allowedCardNetworks: [ 'AMEX', 'DISCOVER', 'INTERAC', 'JCB', 'MASTERCARD', 'VISA' ],
		},
	},
	getCardMethod: function () {
		return Object.assign( {}, this.baseCardMethod, { tokenizationSpecification: this.tokenSpec } );
	},

	getPaymentsClient: function () {
		if ( ! this.paymentsClient ) {
			this.paymentsClient = new google.payments.api.PaymentsClient( { environment: this.environment } );
		}
		return this.paymentsClient;
	},

	getPaymentDataRequest: function () {
		var paymentDataRequest = Object.assign( {}, this.baseRequest );
		paymentDataRequest.allowedPaymentMethods = [ this.getCardMethod() ];
		paymentDataRequest.transactionInfo = this.getTransactionInfo();
		paymentDataRequest.merchantInfo = {
			// @todo a merchant ID is available for a production environment after approval by Google
			// See {@link https://developers.google.com/pay/api/web/guides/test-and-deploy/integration-checklist|Integration checklist}
			merchantId: finix_google_pay_params.google_merchant_id,
			merchantName: finix_google_pay_params.merchant_name,
		};
		return paymentDataRequest;
	},

	getIsReadyToPayRequest: function () {
		return Object.assign( {}, this.baseRequest, {
			allowedPaymentMethods: [ this.baseCardMethod ],
		} );
	},

	prefetch: function () {
		var req = Object.assign( {}, this.baseRequest, { allowedPaymentMethods: [ this.getCardMethod() ] } );
		req.transactionInfo = { totalPriceStatus: 'NOT_CURRENTLY_KNOWN', currencyCode: 'USD' };
		this.getPaymentsClient().prefetchPaymentData( req );
	},

	getTransactionInfo: function () {
		return {
			countryCode: finix_google_pay_params.merchant_country,
			currencyCode: finix_google_pay_params.currency_code,
			totalPriceStatus: 'FINAL',
			totalPrice: finix_google_pay_params.amount,
		};
	},

	async processPayment( paymentToken, billingInfo ) {
		let tokenRequest = {
			provider: 'GOOGLE_PAY',
			payment_token: paymentToken,
			process_payment: true,
			merchant_identity: finix_google_pay_params.merchant_identity,
			wp_nonce: finix_google_pay_params.nonce,
			billing_info: billingInfo,
		};

		/*
		 * On the Checkout Pay Order page we need to pass the order ID and order key
		 * to validate them on the server and retrieve the correct amount to pay.
		 */
		const orderIdEl = document.querySelector( '#finix_google_pay_order_id' );
		const orderKeyEl = document.querySelector( '#finix_google_pay_order_key' );
		if ( orderIdEl && orderKeyEl ) {
			tokenRequest.order_id = orderIdEl.value;
			tokenRequest.order_key = orderKeyEl.value;
		}

		return await fetch( finix_google_pay_params.url.webhook, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( tokenRequest ),
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				if ( data.status === 201 ) {
					return { success: true, transactionId: data.response[ 'id' ] };
				} else {
					return { success: false, error: finix_google_pay_params.text.error_processing };
				}
			} )
			.catch( ( error ) => {
				return { success: false, error: finix_google_pay_params.text.error_setting_payment };
			} );
	},
};
