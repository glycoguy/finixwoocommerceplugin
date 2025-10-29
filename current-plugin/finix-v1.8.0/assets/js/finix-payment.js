/* global Finix, finix_params, wc_checkout_params */

/**
 * Finix Payment Form Handler - v1.7.1
 * Based on official Finix plugin approach
 * Supports both Card and Bank payments with Subscriptions
 */

jQuery(function($) {
    'use strict';

    // Global variables
    let finixCardForm = null;
    let finixBankForm = null;
    let fraudSessionId = '';
    const gatewayId = finix_params.gateway_id || 'finix_subscriptions';

    /**
     * Finix form options configuration
     */
    const getFinixFormOptions = function() {
        const referenceInput = $('input#billing_city, input#shipping_city');

        const options = {
            showAddress: finix_params.finix_form_options.showAddress || true,
            showLabels: finix_params.finix_form_options.showLabels || true,
            labels: finix_params.finix_form_options.labels || {
                name: finix_params.text.full_name
            },
            showPlaceholders: finix_params.finix_form_options.showPlaceholders || true,
            placeholders: finix_params.finix_form_options.placeholders || {
                name: finix_params.text.full_name
            },
            hideFields: finix_params.finix_form_options.hideFields || ['address_line1', 'address_line2', 'address_city', 'address_state'],
            requiredFields: finix_params.finix_form_options.requiredFields || ['name', 'address_country', 'address_postal_code'],
            hideErrorMessages: finix_params.finix_form_options.hideErrorMessages || false,
            errorMessages: finix_params.finix_form_options.errorMessages || {
                name: finix_params.text.error_messages.name,
                address_city: finix_params.text.error_messages.address_city
            },
            styles: {
                default: {
                    color: referenceInput.css('color'),
                    backgroundColor: referenceInput.css('background-color'),
                    border: referenceInput.css('border-width') + ' ' + referenceInput.css('border-style') + ' ' + referenceInput.css('border-color'),
                    borderRadius: referenceInput.css('border-top-left-radius') + ' ' + referenceInput.css('border-top-right-radius') + ' ' + referenceInput.css('border-bottom-right-radius') + ' ' + referenceInput.css('border-bottom-left-radius'),
                    fontFamily: referenceInput.css('font-family'),
                    fontWeight: referenceInput.css('font-weight'),
                    lineHeight: referenceInput.css('line-height'),
                    boxShadow: referenceInput.css('box-shadow'),
                    maxHeight: '100%',
                    height: '100%',
                    appearance: 'auto'
                },
                success: {},
                error: {
                    color: '#e2401c',
                    boxShadow: 'inset 2px 0 0 #e2401c'
                }
            },
            onUpdate: function(state, binInformation, formHasErrors) {},
            onLoad: function() {}
        };

        return options;
    };

    /**
     * Initialize Finix fraud session ID
     */
    const initFraudSession = function() {
        if (typeof Finix !== 'undefined' && typeof Finix.Auth === 'function') {
            try {
                Finix.Auth(
                    finix_params.environment,
                    finix_params.merchant,
                    function(sessionKey) {
                        fraudSessionId = sessionKey;
                        console.log('Finix fraud session initialized');
                    }
                );
            } catch (error) {
                console.error('Finix.Auth initialization failed:', error);
            }
        }
    };

    /**
     * Initialize Finix payment forms
     */
    const initFinixForms = function() {
        const cardFormContainer = document.getElementById(gatewayId + '-card-form');
        const bankFormContainer = document.getElementById(gatewayId + '-bank-form');

        if (cardFormContainer && cardFormContainer.innerHTML === '') {
            try {
                finixCardForm = window.Finix.CardTokenForm(gatewayId + '-card-form', getFinixFormOptions());
                console.log('Finix Card form initialized');
            } catch (error) {
                console.error('Failed to initialize Finix Card form:', error);
            }
        }

        if (bankFormContainer && bankFormContainer.innerHTML === '') {
            try {
                finixBankForm = window.Finix.BankTokenForm(gatewayId + '-bank-form', getFinixFormOptions());
                console.log('Finix Bank form initialized');
            } catch (error) {
                console.error('Failed to initialize Finix Bank form:', error);
            }
        }
    };

    /**
     * Display error message on checkout
     */
    const showError = function(errorMessage) {
        const checkoutForm = $('form.woocommerce-checkout, form#order_review');

        if (!errorMessage || errorMessage.length === 0) {
            errorMessage = wc_checkout_params.i18n_checkout_error;
        }

        $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
        checkoutForm.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><div class="woocommerce-error">' + errorMessage + '</div></div>');
        checkoutForm.removeClass('processing').unblock();
        checkoutForm.find('.input-text, select, input:checkbox').trigger('validate').trigger('blur');

        const scrollElement = $('.woocommerce-NoticeGroup-checkout');
        if (scrollElement.length) {
            $.scroll_to_notices(scrollElement);
        }

        $(document.body).trigger('checkout_error', [errorMessage]);
    };

    /**
     * Token request - submit form and get token
     */
    const tokenRequest = function(form, paymentType) {
        return new Promise(function(resolve, reject) {
            if (!form) {
                reject(new Error('Payment form not initialized'));
                return;
            }

            form.submit(finix_params.environment, finix_params.application, function(err, res) {
                if (err) {
                    console.error('Finix tokenization error:', err);
                    reject(err);
                } else {
                    const tokenData = res.data || {};
                    const token = tokenData.id;
                    console.log('Finix tokenization successful');
                    resolve(token);
                }
            });
        });
    };

    /**
     * Handle form submission
     */
    const processPayment = function() {
        const $form = $('form.woocommerce-checkout, form#order_review');
        const paymentType = $('input[name="' + gatewayId + '_payment_type"]:checked').val() || 'card';
        const form = paymentType === 'bank' ? finixBankForm : finixCardForm;

        // Prevent multiple submissions
        if ($form.hasClass('processing')) {
            return false;
        }

        $form.addClass('processing');

        // Get token from Finix
        tokenRequest(form, paymentType)
            .then(function(token) {
                // Set token in hidden field
                $('#' + gatewayId + '_token').val(token);

                // Set fraud session ID
                $('#' + gatewayId + '_fraud_session_id').val(fraudSessionId);

                // Set payment type
                $('#' + gatewayId + '_payment_type_value').val(paymentType);

                // Submit the form
                $form.removeClass('processing');
                $form.submit();
            })
            .catch(function(error) {
                $form.removeClass('processing');
                showError('Payment processing failed. Please try again.');
                return false;
            });

        return false; // Prevent default form submission
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Check if Finix is available
        if (typeof Finix === 'undefined') {
            console.error('Finix.js library not loaded!');
            return;
        }

        // Initialize fraud session
        initFraudSession();

        // Initialize forms after a short delay to ensure DOM is ready
        setTimeout(initFinixForms, 500);

        // Handle payment method selection changes
        $(document.body).on('updated_checkout payment_method_selected', function() {
            const selectedMethod = $('input[name="payment_method"]:checked').val();
            if (selectedMethod === gatewayId) {
                setTimeout(initFinixForms, 300);
            }
        });

        // Handle payment type toggle (card vs bank)
        $(document).on('change', 'input[name="' + gatewayId + '_payment_type"]', function() {
            const paymentType = $(this).val();
            if (paymentType === 'card') {
                $('#' + gatewayId + '-card-fields').show();
                $('#' + gatewayId + '-bank-fields').hide();
            } else {
                $('#' + gatewayId + '-card-fields').hide();
                $('#' + gatewayId + '-bank-fields').show();
            }
        });

        // Handle form submission
        $('form.woocommerce-checkout').on('checkout_place_order_' + gatewayId, processPayment);
        $('form#order_review').on('submit', function() {
            if ($('input[name="payment_method"]:checked').val() === gatewayId) {
                return processPayment();
            }
            return true;
        });
    });
});
