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
			first_name: finix_google_pay_params.billing_data.first_name,
			last_name: finix_google_pay_params.billing_data.last_name,
			address1: finix_google_pay_params.billing_data.address_1,
			city: finix_google_pay_params.billing_data.city,
			postcode: finix_google_pay_params.billing_data.postcode,
			country: finix_google_pay_params.billing_data.country,
			state: finix_google_pay_params.billing_data.state,
			email: finix_google_pay_params.billing_data.email,
			phone: finix_google_pay_params.billing_data.phone,
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
		triggerGooglePaySubmitError( finix_google_pay_params.text.error_billing );
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
 * Trigger an error message when something fails.
 *
 * @param {string} errorMessage
 */
function triggerGooglePaySubmitError( errorMessage ) {
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

function onGooglePayLoaded() {
	var paymentsClient = FinixGooglePay.getPaymentsClient();
	paymentsClient
		.isReadyToPay( FinixGooglePay.getIsReadyToPayRequest() )
		.then( function ( response ) {
			if ( response.result ) {
				addGooglePayButton();
				FinixGooglePay.prefetch();
			}
		} )
		.catch( function ( err ) {} );
}

function validateWhetherGooglePaySelected() {
	const selectedPaymentMethod = jQuery( 'input[name="payment_method"]:checked' ).val();

	if ( selectedPaymentMethod !== finix_google_pay_params.gateway ) {
		return;
	}

	const googlePayButton = document.getElementById( 'wc-finix_google_pay_gateway-form' );

	if ( googlePayButton && googlePayButton.style.display !== 'block' ) {
		onGooglePayLoaded();
		googlePayButton.style.display = 'block';
	}
}

function addGooglePayButton() {
	const paymentsClient = FinixGooglePay.getPaymentsClient();
	const button = paymentsClient.createButton( {
		onClick: onGooglePaymentButtonClicked,
		allowedPaymentMethods: [ FinixGooglePay.getCardMethod() ],
		buttonColor: finix_google_pay_params.button_color,
		buttonType: finix_google_pay_params.button_type,
		buttonRadius: finix_google_pay_params.button_radius,
		buttonLocale: finix_google_pay_params.button_locale,
	} );
	if ( finix_google_pay_params.google_merchant_id && finix_google_pay_params.google_merchant_id !== '' && finix_google_pay_params.google_merchant_id !== undefined ) {
		document.getElementById( 'wc-finix_google_pay_gateway-form' ).appendChild( button );
	}
}

function onGooglePaymentButtonClicked() {
	if ( ! finix_google_pay_params.google_merchant_id || finix_google_pay_params.google_merchant_id === '' || finix_google_pay_params.google_merchant_id === undefined ) {
		triggerGooglePaySubmitError( finix_google_pay_params.text.error_merchant_id );
		return;
	}

	const billingData = retrieveBillingData();

	if ( ! billingData.first_name || ! billingData.last_name || ! billingData.address1 || ! billingData.city || ! billingData.state || ! billingData.postcode || ! billingData.country || ! billingData.email ) {
		triggerGooglePaySubmitError( finix_google_pay_params.text.error_billing );
		return;
	}

	let shippingData = retrieveShippingData();

	if ( nonEmptyValue( shippingData.shipping_country ) && nonEmptyValue( shippingData.shipping_state ) && nonEmptyValue( shippingData.shipping_postcode ) && nonEmptyValue( shippingData.shipping_city ) && nonEmptyValue( shippingData.shipping_address_1 ) ) {
		// Use shipping data that is already there
	} else {
		shippingData = {
			shipping_country: billingData.country,
			shipping_state: billingData.state,
			shipping_postcode: billingData.postcode,
			shipping_city: billingData.city,
			shipping_address_1: billingData.address1,
		};
	}

	const paymentsClient = FinixGooglePay.getPaymentsClient();

	shippingData.nonce = finix_google_pay_params.nonce;

	// Validate shipping details via AJAX
	fetch( finix_google_pay_params.url.ajax + '?action=validate_shipping_method', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify( shippingData ),
	} )
		.then( ( response ) => response.json() )
		.then( ( data ) => {
			if ( data.success === true ) {
				// Continue with Google Pay process if shipping validation passes.
				const paymentDataRequest = FinixGooglePay.getPaymentDataRequest();
				paymentDataRequest.transactionInfo = FinixGooglePay.getTransactionInfo();
				paymentsClient
					.loadPaymentData( paymentDataRequest )
					.then( ( paymentData ) => {
						processPaymentData( paymentData );
					} )
					.catch( ( err ) => {} );
			} else {
				triggerGooglePaySubmitError( finix_google_pay_params.text.error_shipping );
			}
		} )
		.catch( ( err ) => {} );
}

async function processPaymentData( paymentData ) {
	// @todo pass payment token to your gateway to process payment
	// @note DO NOT save the payment credentials for future transactions,
	// unless they're used for merchant-initiated transactions with user
	// consent in place.
	let paymentToken = paymentData.paymentMethodData.tokenizationData.token;

	if ( ! paymentToken ) {
		triggerGooglePaySubmitError( finix_google_pay_params.text.error_setting_payment );
		document.querySelector( '#finix_google_pay_success' ).value = false;
		return;
	}

	// Get billing information from wooCommerce
	let billingInfo = retrieveBillingData();
	// Try to get address2, if not leave it empty
	billingInfo.address2 = document.querySelector( '#billing_address_2' )?.value || '';
	FinixGooglePay.processPayment( paymentToken, billingInfo )
		.then( ( paymentResult ) => {
			if ( ! paymentResult || ! paymentResult.success ) {
				triggerGooglePaySubmitError( finix_google_pay_params.text.error_processing );
				document.querySelector( '#finix_google_pay_success' ).value = false;
				return;
			}
			document.querySelector( '#finix_google_pay_success' ).value = paymentResult.success;
			document.querySelector( '#finix_google_pay_transaction_id' ).value = paymentResult.transactionId || '';
			jQuery( 'form.woocommerce-checkout, form#order_review' ).trigger( 'submit' );
		} )
		.catch( ( err ) => {
			triggerGooglePaySubmitError( finix_google_pay_params.text.error_processing );
			document.querySelector( '#finix_google_pay_success' ).value = false;
		} );
}

function nonEmptyValue( value ) {
	return typeof value !== 'undefined' && value !== null && value !== '' && value !== 'undefined' && value.trim() !== '';
}

jQuery( function () {
	const checkoutForm = jQuery( 'form.woocommerce-checkout, form#order_review' );
	// This code is also loaded on the /checkout/order-received/ page
	// where there is no checkout form already.
	if ( ! checkoutForm.length ) {
		return;
	}
	let loopCountGooglePay = 0;
	let intervalID = setInterval( function () {
		validateWhetherGooglePaySelected();
		loopCountGooglePay++;
		// if loop count is more than 5, stop the loop also if #place_order contains finix-hide-place-order class
		if ( loopCountGooglePay >= 5 ) {
			clearInterval( intervalID );
		}
	}, 1000 );

	jQuery( document ).on( 'click', '#place_order', async function ( event ) {
		const selected_payment_method = jQuery( 'input[name="payment_method"]:checked', checkoutForm ).val();
		if ( selected_payment_method !== finix_google_pay_params.gateway ) {
			return true;
		}
		// Prevent the default form submission until our token is ready.
		event.preventDefault();
		event.stopImmediatePropagation();

		onGooglePaymentButtonClicked();
	} );

	/**
	 * When the coupon code is applied or removed, the checkout total can be updated.
	 * We need to make sure we keep in sync our finix_apple_pay_params.amount value.
	 */
	jQuery(document).on('updated_checkout', function () {

		finix_google_pay_params.amount = FinixHelpers.getUpdatedTotal();
		
	});

} );
