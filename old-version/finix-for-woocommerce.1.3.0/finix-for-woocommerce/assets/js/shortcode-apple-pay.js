/* global finix_apple_pay_params, ApplePaySession */

function nonEmptyValue( value ) {
	return typeof value !== 'undefined' && value !== null && value !== '' && value.trim() !== '';
}

/**
 * Trigger an error message when something fails.
 *
 * @param {string} errorMessage
 */
function triggerApplePaySubmitError( errorMessage ) {
	const checkoutForm = jQuery( 'form.woocommerce-checkout, form#order_review' );

	// Provide a default error message.
	if ( typeof errorMessage === 'undefined' || errorMessage.length === 0 ) {
		errorMessage = '<div class="woocommerce-error">' + finix_params.text.error_processing + '</div>';
	}

	// Cleanup current notices.
	jQuery( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error, .is-success' ).remove();

	checkoutForm.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + errorMessage + '</div>' );
	checkoutForm.removeClass( 'processing' ).unblock();
	checkoutForm.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).trigger( 'blur' );

	let scrollElement = jQuery( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );

	if ( ! scrollElement.length ) {
		scrollElement = checkoutForm;
	}
	jQuery.scroll_to_notices( scrollElement );

	checkoutForm.find( '.woocommerce-error[tabindex="-1"], .wc-block-components-notice-banner.is-error[tabindex="-1"]' ).trigger( 'focus' );
	jQuery( document.body ).trigger( 'checkout_error', [ errorMessage ] );
}

function validateWhetherApplePaySelected() {
	const selectedPaymentMethod = jQuery( 'input[name="payment_method"]:checked' ).val();

	if ( selectedPaymentMethod !== finix_apple_pay_params.gateway ) {
		return;
	}

	const applePayButton = document.querySelector( '#finix-apple-pay-button' );

	if ( applePayButton && applePayButton.style.display !== 'block' ) {
		applePayButton.style.display = 'block';
	}

	const placeOrderButton = document.querySelector( '#place_order' );

	if ( placeOrderButton && ! placeOrderButton.classList.contains( 'finix-hide-place-order' ) ) {
		placeOrderButton.classList.add( 'finix-hide-place-order' );
	}
}

/**
 * Manage the Apple Pay payment.
 *
 * @param {object} applePaySession
 */
function beginFinixApplePaySession( applePaySession ) {
	/*
	 * @see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaysession/1778021-onvalidatemerchant
	 */
	applePaySession.onvalidatemerchant = function ( event ) {
		try {
			if ( ! event.validationURL ) {
				triggerApplePaySubmitError();

				return;
			}

			const merchantRequest = {
				provider: 'APPLE_PAY',
				validation_url: event.validationURL,
				merchant_identity: finix_apple_pay_params.merchant_identity,
				domain_name: window.location.hostname,
				display_name: finix_apple_pay_params.merchant_name,
				session_request: true,
				wp_nonce: finix_apple_pay_params.nonce,
			};

			fetch( finix_apple_pay_params.url.webhook, {
				method: 'POST',
				body: JSON.stringify( merchantRequest ),
				headers: {
					'Content-Type': 'application/json',
				},
			} )
				.then( ( response ) => response.json() )
				.then( ( data ) => {
					const sessionDetails = data?.response?.session_details || '';

					if ( ! sessionDetails ) {
						triggerApplePaySubmitError();
						return;
					}

					const merchantSession = JSON.parse( sessionDetails );

					/*
					 * @see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaysession/1778015-completemerchantvalidation
					 */
					applePaySession.completeMerchantValidation( merchantSession );
				} );
		} catch ( err ) {
			throw err;
		}
	};

	/*
	 * @see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaysession/1778020-onpaymentauthorized/
	 */
	applePaySession.onpaymentauthorized = function ( event ) {
		try {
			// Token received from Apple Pay.
			const paymentToken = event.payment;

			if ( ! paymentToken ) {
				applePaySession.completePayment( applePaySession.STATUS_FAILURE );

				return;
			}

			// Send token to Finix API.
			const tokenRequest = {
				provider: 'APPLE_PAY',
				payment_token: JSON.stringify( paymentToken ),
				process_payment: true,
				merchant_identity: finix_apple_pay_params.merchant_identity,
				wp_nonce: finix_apple_pay_params.nonce,
				billing_info: retrieveBillingData(),
			};

			/*
			 * On the Checkout Pay Order page we need to pass the order ID and order key
			 * to validate them on the server and retrieve the correct amount to pay.
			 */
			if ( document.querySelector( '#finix_apple_pay_order_id' ) && document.querySelector( '#finix_apple_pay_order_key' ) ) {
				tokenRequest.order_id = document.querySelector( '#finix_apple_pay_order_id' ).value;
				tokenRequest.order_key = document.querySelector( '#finix_apple_pay_order_key' ).value;
			}

			// Send a POST request to the server.
			fetch( finix_apple_pay_params.url.webhook, {
				method: 'POST',
				body: JSON.stringify( tokenRequest ),
				headers: {
					'Content-Type': 'application/json',
				},
			} )
				.then( ( response ) => response.json() )
				.then( ( data ) => {
					if ( data.status === 201 ) {
						applePaySession.completePayment( applePaySession.STATUS_SUCCESS );
						document.querySelector( '#finix_apple_pay_success' ).value = 'true';
						document.querySelector( '#finix_apple_pay_transaction_id' ).value = data.response.id;

						// Finally, submit the form.
						jQuery( 'form.woocommerce-checkout, form#order_review' ).trigger( 'submit' );
					} else {
						triggerApplePaySubmitError();

						applePaySession.completePayment( applePaySession.STATUS_FAILURE );
						document.querySelector( '#finix_apple_pay_success' ).value = false;
					}
				} )
				.catch( ( data ) => {
					triggerApplePaySubmitError();

					applePaySession.completePayment( applePaySession.STATUS_FAILURE );
					document.querySelector( '#finix_apple_pay_success' ).value = false;
				} );
		} catch ( err ) {
			throw err;
		}
	};

	/*
	 * @see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaysession/1778029-oncancel/
	 */
	applePaySession.oncancel = function ( event ) {};

	/*
	 * @see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaysession/1778001-begin
	 */
	applePaySession.begin();
}

/**
 * Retrieve billing data from the checkout form.
 */
function retrieveBillingData() {
	let billing = {};

	// On-page available billing data has higher priority then what is passed from the server.
	if ( document.querySelector( '#billing_country' ) && document.querySelector( '#billing_email' ) ) {
		billing = {
			first_name: document.querySelector( '#billing_first_name' )?.value || '',
			last_name: document.querySelector( '#billing_last_name' )?.value || '',
			address1: document.querySelector( '#billing_address_1' )?.value || '',
			city: document.querySelector( '#billing_city' )?.value || '',
			postcode: document.querySelector( '#billing_postcode' )?.value || '',
			country: document.querySelector( '#billing_country' )?.value || '',
			state: document.querySelector( '#billing_state' )?.type === 'hidden' ? '' : document.querySelector( '#billing_state' )?.value || '',
			email: document.querySelector( '#billing_email' )?.value || '',
		};

		/*
		 * May be an optional field. Validate if parent p.form-row has class 'validate-required'.
		 * Include into billing only if required.
		 */
		if ( document.querySelector( '#billing_phone' )?.closest( 'p.form-row' )?.classList.contains( 'validate-required' ) ) {
			billing.phone = document.querySelector( '#billing_phone' )?.value || '';
		}
	} else {
		billing = {
			first_name: finix_apple_pay_params.billing_data.first_name,
			last_name: finix_apple_pay_params.billing_data.last_name,
			address1: finix_apple_pay_params.billing_data.address_1,
			city: finix_apple_pay_params.billing_data.city,
			postcode: finix_apple_pay_params.billing_data.postcode,
			country: finix_apple_pay_params.billing_data.country,
			state: finix_apple_pay_params.billing_data.state,
			email: finix_apple_pay_params.billing_data.email,
			phone: finix_apple_pay_params.billing_data.phone,
		};
	}

	return billing;
}

/**
 * Retrieve shipping data from the checkout form.
 * We use billing data as default as shipping may actually be hidden.
 */
function retrieveShippingData() {
	// Validate if the form fields are filled.
	let billing = retrieveBillingData();

	if ( billing.address1 === '' || billing.city === '' || ( billing.country === 'US' && billing.state === '' ) || billing.postcode === '' || billing.country === '' ) {
		triggerApplePaySubmitError( '<div class="woocommerce-error">' + finix_apple_pay_params.text.error_billing + '</div>' );
		return false;
	}

	// vars for shipping details validating if field exists
	const shippingCountry = document.querySelector( '#shipping_country' )?.value || '';
	const shippingState = document.querySelector( '#shipping_state' )?.value || '';
	const shippingPostcode = document.querySelector( '#shipping_postcode' )?.value || '';
	const shippingCity = document.querySelector( '#shipping_city' )?.value || '';
	const shippingAddress1 = document.querySelector( '#shipping_address_1' )?.value || '';

	// Collect shipping address data if not empty.
	let shippingData = {
		shipping_country: billing.country,
		shipping_state: billing.state,
		shipping_postcode: billing.postcode,
		shipping_city: billing.city,
		shipping_address_1: billing.address1,
	};

	if ( nonEmptyValue( shippingCountry ) ) {
		shippingData.shipping_country = shippingCountry;
	}
	if ( nonEmptyValue( shippingState ) ) {
		shippingData.shipping_state = shippingState;
	}
	if ( nonEmptyValue( shippingPostcode ) ) {
		shippingData.shipping_postcode = shippingPostcode;
	}
	if ( nonEmptyValue( shippingCity ) ) {
		shippingData.shipping_city = shippingCity;
	}
	if ( nonEmptyValue( shippingAddress1 ) ) {
		shippingData.shipping_address_1 = shippingAddress1;
	}

	return shippingData;
}

/**
 * Validate shipping as a Promise.
 *
 * @param {object} shippingData
 */
function validateShippingData( shippingData ) {
	return new Promise( ( resolve ) => {
		shippingData.nonce = finix_apple_pay_params.nonce;

		fetch( finix_apple_pay_params.url.ajax + '?action=validate_shipping_method', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( shippingData ),
		} )
			.then( ( response ) => response.json() )
			.then( ( response ) => {
				if ( response.success === true ) {
					resolve( true );
				} else {
					triggerApplePaySubmitError( '<div class="woocommerce-error">' + response.data.message + '</div>' );
					resolve( false );
				}
			} )
			.catch( () => resolve( false ) );
	} );
}

jQuery( function () {
	if ( ! window.ApplePaySession || ! window.ApplePaySession.canMakePayments() || ! window.ApplePaySession.supportsVersion( 10 ) ) {
		// The Apple Pay JS API is not available.
		const applePayfieldset = document.querySelector( 'fieldset#wc-finix_apple_pay_gateway-form' );
		// If applePayfieldset is not found, return.
		if ( ! applePayfieldset ) {
			return;
		}
		applePayfieldset.style.display = 'none';
		const applePayContainer = document.querySelector( '.wc_payment_method.payment_method_finix_apple_pay_gateway' );
		applePayContainer.style.display = 'none';
		const applePayButton = document.querySelector( '#finix-apple-pay-button' );
		if ( applePayButton ) {
			applePayButton.style.display = 'none';
		}

		return;
	}

	const checkoutForm = jQuery( 'form.woocommerce-checkout, form#order_review' );

	// This code is also loaded on the /checkout/order-received/ page
	// where there is no checkout form already.
	if ( ! checkoutForm.length ) {
		return;
	}

	/**
	 * Validate if Apple Pay is selected by default.
	 */
	let loopCountApplePay = 0;
	const intervalID = setInterval( function () {
		loopCountApplePay++;

		validateWhetherApplePaySelected();

		if ( loopCountApplePay >= 5 ) {
			clearInterval( intervalID );
		}
	}, 1000 );

	/**
	 * Show the Apple Pay button instead of the "Place Order"
	 * when Apple Pay isn't a default payment method and
	 * was manually selected by a user.
	 */
	jQuery( document ).on( 'change', 'input[name="payment_method"]', function () {
		validateApplePayButtonVisibility();
	} );

	/**
	 * When the coupon code is applied or removed, the checkout total can be updated.
	 * We need to make sure we keep in sync our finix_apple_pay_params.amount value.
	 */
	jQuery( document ).on( 'updated_checkout', function () {
		finix_apple_pay_params.amount = FinixHelpers.getUpdatedTotal();

		// Get order details via AJAX.
		fetch( finix_apple_pay_params.url.ajax + '?action=get_order_tracking_details', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( { nonce: finix_apple_pay_params.nonce } ),
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				if ( data.success === true ) {
					finix_apple_pay_params.subtotal = data.data.subtotal || finix_apple_pay_params.amount;
					finix_apple_pay_params.shipping_amount = data.data.shipping_amount || 0;
					finix_apple_pay_params.tax_amount = data.data.tax_amount || 0;
					finix_apple_pay_params.products = data.data.products || [];
					finix_apple_pay_params.coupons = data.data.coupons || [];
					finix_apple_pay_params.currency_code = data.data.currency_code || finix_apple_pay_params.currency_code;
				}
			} )
			.catch( ( err ) => {} );

		addApplePayEventListener();
	} );

	function validateApplePayButtonVisibility() {
		let placeOrderButton;
		let applePayButton;

		const selectedPaymentMethod = jQuery( 'input[name="payment_method"]:checked' ).val();

		if ( selectedPaymentMethod === finix_apple_pay_params.gateway ) {
			applePayButton = document.querySelector( '#finix-apple-pay-button' );
			applePayButton.style.display = 'block';

			placeOrderButton = document.querySelector( '#place_order' );
			placeOrderButton.classList.add( 'finix-hide-place-order' );
			placeOrderButton.style.display = 'none';
		} else {
			applePayButton = document.querySelector( '#finix-apple-pay-button' );
			applePayButton.style.display = 'none';

			placeOrderButton = document.querySelector( '#place_order' );
			placeOrderButton.classList.remove( 'finix-hide-place-order' );
			placeOrderButton.style.display = 'block';
		}
	}

	function addApplePayEventListener() {
		const button = document.getElementById( 'finix-apple-pay-button' );

		if ( ! button || button.dataset.finixBound ) return;

		button.addEventListener( 'click', () => {

			const shippingData = retrieveShippingData();

			/*
			 * In the future update to a newer API version.
			 * This interface is created specifically outside of the promise.
			 *
			 * @see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaysession
			 * @see https://developer.apple.com/documentation/apple_pay_on_the_web/apple_pay_on_the_web_version_history
			 */

			let lineItems = [];

			// Add product line items if value is not Zero.
			Object.values( finix_apple_pay_params.products ).forEach( ( product ) => {
				if ( parseFloat( product.total ) !== 0 ) {
					lineItems.push( {
						label: product.quantity > 1 ? `${ product.name } x${ product.quantity }` : product.name,
						type: 'final',
						amount: product.total,
					} );
				}
			} );

			lineItems.push( {
				label: finix_apple_pay_params.text.subtotal,
				type: 'final',
				amount: finix_apple_pay_params.subtotal || finix_apple_pay_params.amount,
			} );

			// Add any offer details to the transaction info.
			lineItems.push(
				...finix_apple_pay_params.coupons.map( ( offer ) => ( {
					label: finix_apple_pay_params.text.discount_code + ' ' + offer.code,
					type: 'final',
					amount: -1 * parseFloat( offer.total ).toFixed( 2 ), // Ensure negative value for discounts.
				} ) )
			);

			// Add Shipping if it is not zero.
			if ( finix_apple_pay_params.shipping_amount > 0 ) {
				lineItems.push( {
					label: finix_apple_pay_params.text.shipping,
					type: 'final',
					amount: finix_apple_pay_params.shipping_amount,
				} );
			}

			// Add Tax if it is not zero.
			if ( finix_apple_pay_params.tax_amount > 0 ) {
				lineItems.push( {
					label: finix_apple_pay_params.text.tax,
					type: 'final',
					amount: finix_apple_pay_params.tax_amount,
				} );
			}

			const applePayParameters = {
				countryCode: finix_apple_pay_params.merchant_country,
				currencyCode: finix_apple_pay_params.currency_code,
				merchantCapabilities: [ 'supports3DS' ],
				supportedNetworks: [ 'visa', 'masterCard', 'amex', 'discover' ],
				total: {
					label: finix_apple_pay_params.merchant_name,
					amount: finix_apple_pay_params.amount,
				},
				lineItems: lineItems,
			};

			const applePaySession = new window.ApplePaySession( 10, applePayParameters );

			validateShippingData( shippingData ).then( ( validShippingResult ) => {
				if ( validShippingResult ) {
					beginFinixApplePaySession( applePaySession );
				}
			} );

			return false;
		} );

		button.dataset.finixBound = '1';
	}

	/**
	 * Initiate the payment process by listening to the Apple Pay button click.
	 */
	const applePayTimer = setTimeout( () => {
		validateApplePayButtonVisibility();
		addApplePayEventListener();
		clearTimeout( applePayTimer );
	}, 2000 );
} );
