/* global Finix, finix_params,wc_checkout_params */

/**
 * Finix payment form configuration object.
 */
const FinixFormOptions = {
	showAddress: !! finix_params.finix_form_options.showAddress,
	showLabels: !! finix_params.finix_form_options.showLabels,
	labels: finix_params.finix_form_options.labels || {
		name: finix_params.text.full_name,
	},
	showPlaceholders: !! finix_params.finix_form_options.showPlaceholders,
	placeholders: finix_params.finix_form_options.placeholders || {
		name: finix_params.text.full_name,
	},
	hideFields: finix_params.finix_form_options.hideFields || [ 'address_line1', 'address_line2', 'address_city', 'address_state' ],
	requiredFields: finix_params.finix_form_options.requiredFields || [ 'name', 'address_country', 'address_postal_code' ],
	hideErrorMessages: !! finix_params.finix_form_options.hideErrorMessages,
	errorMessages: finix_params.finix_form_options.errorMessages || {
		name: finix_params.text.error_messages.name,
		address_city: finix_params.text.error_messages.address_city,
	},
	styles: {
		default: {},
		success: {},
		error: {
			color: '#e2401c',
			boxShadow: 'inset 2px 0 0 #e2401c',
		},
	},
	// Callback function that will trigger when form state changes (can be called frequently).
	onUpdate( state, binInformation, formHasErrors ) {},
	// Optional callback function that will trigger after the form has loaded.
	onLoad() {},
};

/**
 * Copy the styles from the billing city input inthe checkout form
 * to the on-the-fly generated Finix form, so that they match.
 */
function copyWooInputStylesToFinix() {
	const referenceInput = jQuery( 'input#billing_city, input#shipping_city' );

	FinixFormOptions.styles.default = {
		color: referenceInput.css( 'color' ),
		backgroundColor: referenceInput.css( 'background-color' ),
		border: `${ referenceInput.css( 'border-width' ) } ${ referenceInput.css( 'border-style' ) } ${ referenceInput.css( 'border-color' ) }`,
		borderRadius: `${ referenceInput.css( 'border-top-left-radius' ) } ${ referenceInput.css( 'border-top-right-radius' ) } ${ referenceInput.css( 'border-bottom-right-radius' ) } ${ referenceInput.css( 'border-bottom-left-radius' ) }`,
		fontFamily: referenceInput.css( 'font-family' ),
		// fontSize: referenceInput.css( 'font-size' ),
		fontWeight: referenceInput.css( 'font-weight' ),
		lineHeight: referenceInput.css( 'line-height' ),
		boxShadow: referenceInput.css( 'box-shadow' ),
		// padding: `${ referenceInput.css( 'padding-bottom' ) } ${ referenceInput.css( 'padding-right' ) } ${ referenceInput.css( 'padding-bottom' ) } ${ referenceInput.css( 'padding-left' ) }`,
		maxHeight: '100%',
		height: '100%',
		appearance: 'auto',
	};

	return FinixFormOptions;
}

/**
 * Display a nice error message on the checkout page.
 *
 * @param {string} errorMessage
 */
function triggerFinixSubmitError( errorMessage ) {
	const checkoutForm = jQuery( 'form.woocommerce-checkout, form#order_review' );

	// Provide a default error message.
	if ( typeof errorMessage === 'undefined' || errorMessage.length === 0 ) {
		errorMessage = '<div class="woocommerce-error">' + wc_checkout_params.i18n_checkout_error + '</div>';
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

// Modify the tokenRequest function to accept successCallback and errorCallback as parameters.
async function tokenRequest( form, selected_payment_method ) {
	// Disable the place_order button to prevent multiple submissions.
	document.getElementById( 'place_order' ).disabled = true;

	// Wrap the form submission in a promise.
	return new Promise( ( resolve, reject ) => {
		form.submit( finix_params.environment, finix_params.application, function ( err, res ) {
			if ( err ) {
				// Handle error.
				triggerFinixSubmitError();
				document.getElementById( selected_payment_method + '_token' ).value = '';
				document.getElementById( 'place_order' ).disabled = false;

				// Reject the promise with the error.
				reject( err );
			} else {
				// Get token ID from response.
				const tokenData = res.data || {};
				const token = tokenData.id;

				// Handle success.
				document.getElementById( selected_payment_method + '_token' ).value = token;
				document.getElementById( 'place_order' ).disabled = false;

				// Resolve the promise with the token.
				resolve( token );
			}
		} );
	} );
}

/**
 * Request from the Finix JS library to generate and embed the Finix payment form.
 *
 * @param {string} identifier - The identifier of the payment method.
 *
 * @returns {object|null}
 */
function initFinixForm( identifier ) {
	const formContent = document.getElementById( identifier + '-form' ).innerHTML;

	if ( formContent === '' ) {
		switch ( identifier ) {
			case 'finix_gateway':
				finix_params.finixForms.finix_gateway = window.Finix.CardTokenForm( identifier + '-form', FinixFormOptions );
				return finix_params.finixForms.finix_gateway;

			case 'finix_bank_gateway':
				finix_params.finixForms.finix_bank_gateway = window.Finix.BankTokenForm( identifier + '-form', FinixFormOptions );
				return finix_params.finixForms.finix_bank_gateway;
		}
	}

	return null;
}

/**
 * Process the payment on "Place order" button click:
 * - Prevent default form submission.
 * - Request token from Finix.
 * - Submit the form if token is received.
 *
 * @param {object} checkoutForm jQuery-fused object of the checkout form.
 */
function processPlaceOrder( checkoutForm ) {
	jQuery( document ).on( 'click', '#place_order', async function ( event ) {
		const selected_payment_method = jQuery( 'input[name="payment_method"]:checked', checkoutForm ).val();

		if ( ! [ 'finix_gateway', 'finix_bank_gateway' ].includes( selected_payment_method ) ) {
			return true;
		}

		let currentFinixForm;

		switch ( selected_payment_method ) {
			case 'finix_gateway':
				currentFinixForm = finix_params.finixForms.finix_gateway;
				break;
			case 'finix_bank_gateway':
				currentFinixForm = finix_params.finixForms.finix_bank_gateway;
				break;
		}

		// Prevent the default form submission until our token is ready.
		event.preventDefault();
		event.stopImmediatePropagation();

		// We don't have a token.
		if ( ! checkoutForm.find( '#' + selected_payment_method + '_token' ).val() ) {
			try {
				// Wait until the token request finishes.
				let token = await tokenRequest( currentFinixForm, selected_payment_method );

				if ( token ) {
					checkoutForm.trigger( 'submit' );
					return true;
				}
			} catch ( error ) {
				// Make sure the token is empty.
				checkoutForm.find( '#' + selected_payment_method + '_token' ).val( '' );

				triggerFinixSubmitError();
				document.getElementById( 'place_order' ).disabled = false;

				return false;
			}
		} else {
			// We have the token, so submit the form.
			checkoutForm.trigger( 'submit' );

			return true;
		}
	} );
}

/**
 * Main logic with specific call order.
 * Initialize the Finix form and process the checkout form submission.
 */
jQuery( function () {
	// The Woo checkout form is different on the /checkout/ and /checkout/pay-order/ pages.
	const checkoutForm = jQuery( 'form.woocommerce-checkout, form#order_review' );

	// This code is also loaded on the /checkout/order-received/ page
	// where there is no checkout form already.
	if ( ! checkoutForm.length ) {
		return;
	}

	// Update global FinixFormOptions with the styles from the WooCommerce input.
	copyWooInputStylesToFinix();

	// Initialize the Finix Auth session together with our nonce.
	window.Finix.Auth( finix_params.environment, finix_params.merchant, ( sessionKey ) => {
		const fraudInput = jQuery( '#finix_fraud_session_id', checkoutForm );
		if ( ! fraudInput.length ) {
			checkoutForm.prepend( '<input type="hidden" id="finix_fraud_session_id" name="finix_fraud_session_id" value=""/>' );
		}
		checkoutForm.find( '#finix_fraud_session_id' ).val( sessionKey );

		const nonceInput = jQuery( '#finix_nonce', checkoutForm );
		if ( ! nonceInput.length ) {
			checkoutForm.prepend( '<input type="hidden" id="finix_nonce" name="finix_nonce" value=""/>' );
		}
		checkoutForm.find( '#finix_nonce' ).val( finix_params.nonce );
	} );

	finix_params.finixForms = finix_params.finixForms || {};

	const customIframeHeight = `
			<style id="test">
				#finix_gateway-form .finix-form-container iframe,
				#finix_bank_gateway-form .finix-form-container iframe {
					height: ${ checkoutForm.find( '#billing_city' ).outerHeight() }px !important;
			</style>
			`;

	checkoutForm.prepend( customIframeHeight );

	/*
	 * Here we are generating Finix forms separately for Card and Bank payment gateways.
	 * We do that only if the relevant payment gateway is enabled and thus - present in DOM.
	 */
	if ( document.getElementById( 'finix_gateway-form' ) ) {
		let cardFormLoaded = false;

		// Set the timer to retrieve the finixForm up until we successfully get it.
		const cardFormTimer = setInterval( () => {
			initFinixForm( 'finix_gateway' );

			if ( finix_params.finixForms.finix_gateway !== null && typeof finix_params.finixForms.finix_gateway.submit === 'function' ) {
				processPlaceOrder( checkoutForm );

				// If the Finix form is already loaded, we can stop the timer in the next step
				// (that gives some time in case the checkout is reloaded).
				cardFormLoaded = true;
			} else if ( cardFormLoaded ) {
				// If the form is already loaded, we can stop the timer.
				clearInterval( cardFormTimer );
			}
		}, 2000 );
	}
	if ( document.getElementById( 'finix_bank_gateway-form' ) ) {
		let bankFormLoaded = false;

		// Set the timer to retrieve the finixForm up until we successfully get it.
		const bankFormTimer = setInterval( () => {
			initFinixForm( 'finix_bank_gateway' );

			if ( finix_params.finixForms.finix_bank_gateway !== null && typeof finix_params.finixForms.finix_bank_gateway.submit === 'function' ) {
				processPlaceOrder( checkoutForm );

				// If the Finix form is already loaded, we can stop the timer in the next step
				// (that gives some time in case the checkout is reloaded).
				bankFormLoaded = true;
			} else if ( bankFormLoaded ) {
				// If the form is already loaded, we can stop the timer.
				clearInterval( bankFormTimer );
			}
		}, 2000 );
	}

	// The form submission may fail, we need to listen to that event and clear the token,
	// so Finix will re-request it.
	jQuery( document.body ).on( 'checkout_error', function ( event, data ) {
		document.getElementById( 'finix_gateway_token' ).value = '';
		document.getElementById( 'finix_bank_gateway_token' ).value = '';
	} );
} );
