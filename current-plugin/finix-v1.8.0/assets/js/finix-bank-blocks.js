/**
 * Finix Bank Blocks Checkout Integration - v1.8.0
 * Separate bank gateway using Finix.BankTokenForm with subscription support
 */

(function() {
    'use strict';

    console.log('Finix Bank Blocks JS Version: 1.8.0 - Two-gateway architecture');

    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { createElement, useState, useEffect } = window.wp.element;
    const { getSetting } = window.wc.wcSettings;
    const { decodeEntities } = window.wp.htmlEntities;
    const { __ } = window.wp.i18n;

    // Get payment method data
    const settings = getSetting('finix_bank_gateway_data', {});

    const defaultLabel = __('Bank Account (EFT)', 'finix-wc-subs');
    const label = decodeEntities(settings.title || '') || defaultLabel;

    // Global Finix form instance (reset on each payment method switch)
    let finixBankForm = null;
    let fraudSessionId = '';
    let isFormInitialized = false;

    /**
     * Get Finix form options
     */
    function getFinixFormOptions() {
        const referenceInput = jQuery('input#billing-city, input#shipping-city');

        return {
            showAddress: settings.finix_form_options?.showAddress || true,
            showLabels: settings.finix_form_options?.showLabels || true,
            labels: settings.finix_form_options?.labels || {
                name: settings.text?.full_name || __('Full Name', 'finix-wc-subs')
            },
            showPlaceholders: settings.finix_form_options?.showPlaceholders || true,
            placeholders: settings.finix_form_options?.placeholders || {
                name: settings.text?.full_name || __('Full Name', 'finix-wc-subs')
            },
            hideFields: settings.finix_form_options?.hideFields || ['address_line1', 'address_line2', 'address_city', 'address_state'],
            requiredFields: settings.finix_form_options?.requiredFields || ['name', 'address_country', 'address_postal_code'],
            hideErrorMessages: settings.finix_form_options?.hideErrorMessages || false,
            errorMessages: settings.finix_form_options?.errorMessages || {
                name: __('Please enter a valid name', 'finix-wc-subs'),
                address_city: __('Please enter a valid city', 'finix-wc-subs')
            },
            styles: {
                default: {
                    color: referenceInput.css('color'),
                    backgroundColor: referenceInput.css('background-color'),
                    border: `${referenceInput.css('border-width')} ${referenceInput.css('border-style')} ${referenceInput.css('border-color')}`,
                    borderRadius: `${referenceInput.css('border-top-left-radius')} ${referenceInput.css('border-top-right-radius')} ${referenceInput.css('border-bottom-right-radius')} ${referenceInput.css('border-bottom-left-radius')}`,
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
                    color: '#d9534f',
                    border: '1px solid rgba(255,0,0, 0.3)'
                }
            },
            onUpdate(state, binInformation, formHasErrors) {},
            onLoad() {}
        };
    }

    /**
     * Initialize Finix bank form with retry logic
     */
    function initializeFinixBankForm(gatewayId, retryCount = 0) {
        const maxRetries = 4;

        const bankFormContainer = document.getElementById(gatewayId + '-bank-form');

        if (retryCount > maxRetries) {
            console.error('Failed to initialize Finix bank form after max retries');
            if (bankFormContainer) {
                bankFormContainer.innerHTML = '<p>' + __('Unfortunately, there was an error while loading the payment form. Please try again later.', 'finix-wc-subs') + '</p>';
            }
            return;
        }

        // Initialize Bank form (allow reinitialize if form was cleared)
        if (bankFormContainer && !isFormInitialized) {
            try {
                // Clear the container first to ensure clean state
                bankFormContainer.innerHTML = '';

                finixBankForm = window.Finix.BankTokenForm(gatewayId + '-bank-form', getFinixFormOptions());
                isFormInitialized = true;
                console.log('Finix Bank form initialized for blocks');
            } catch (error) {
                console.error('Failed to initialize Finix Bank form:', error);
                setTimeout(() => initializeFinixBankForm(gatewayId, retryCount + 1), 500);
            }
        }
    }

    /**
     * Cleanup form when payment method changes
     */
    function cleanupFinixBankForm() {
        finixBankForm = null;
        isFormInitialized = false;
        console.log('Finix Bank form cleaned up');
    }

    /**
     * Content component
     */
    const Content = (props) => {
        const [isInitialized, setIsInitialized] = useState(false);
        const { eventRegistration, emitResponse } = props;
        const { onPaymentSetup } = eventRegistration;

        const gatewayId = settings.gateway_id || 'finix_bank_gateway';

        // Initialize Finix.Auth for fraud session ID
        useEffect(() => {
            if (typeof Finix !== 'undefined' && typeof Finix.Auth === 'function' && settings.merchant && settings.environment) {
                try {
                    Finix.Auth(
                        settings.environment,
                        settings.merchant,
                        function(sessionKey) {
                            fraudSessionId = sessionKey;
                            console.log('Finix fraud session initialized for bank blocks checkout');
                        }
                    );
                } catch (error) {
                    console.error('Finix.Auth initialization failed:', error);
                }
            }
        }, []);

        // Initialize Finix bank form
        useEffect(() => {
            if (typeof Finix === 'undefined') {
                console.error('Finix.js library not loaded!');
                return;
            }

            const timer = setTimeout(() => {
                initializeFinixBankForm(gatewayId);
                setIsInitialized(true);
            }, 500);

            // Cleanup on unmount (when payment method changes)
            return () => {
                clearTimeout(timer);
                cleanupFinixBankForm();
            };
        }, []);

        // Register payment processing event
        useEffect(() => {
            const unsubscribe = onPaymentSetup(async () => {
                console.log('Bank payment setup started');

                if (!finixBankForm) {
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: __('Payment form not initialized. Please refresh and try again.', 'finix-wc-subs')
                    };
                }

                // Get token from Finix
                try {
                    const token = await new Promise((resolve, reject) => {
                        finixBankForm.submit(settings.environment, settings.application, function(err, res) {
                            if (err) {
                                console.error('Finix bank tokenization error:', err);
                                reject(err);
                            } else {
                                const tokenData = res.data || {};
                                const tokenId = tokenData.id;
                                console.log('Finix bank tokenization successful');
                                resolve(tokenId);
                            }
                        });
                    });

                    // Get receipt description if present
                    const receiptDescription = document.getElementById('finix-custom-description')?.value || '';

                    // Prepare payment data
                    const paymentData = {
                        [gatewayId + '_token']: token,
                        [gatewayId + '_fraud_session_id']: fraudSessionId,
                        finix_custom_description: receiptDescription,
                        finix_nonce: settings.nonce
                    };

                    // Debug: Log payment data being sent
                    console.log('Finix bank payment data being sent:', paymentData);

                    // Return success with payment data
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: paymentData
                        }
                    };
                } catch (error) {
                    console.error('Bank payment processing error:', error);
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: error.message || __('Payment processing failed. Please try again.', 'finix-wc-subs')
                    };
                }
            });

            return unsubscribe;
        }, [onPaymentSetup, emitResponse]);

        return createElement(
            'div',
            { className: 'finix-payment-form finix-bank-blocks-form' },
            [
                // Description
                settings.description && createElement(
                    'p',
                    { key: 'description', dangerouslySetInnerHTML: { __html: settings.description } }
                ),

                // Bank form container
                createElement(
                    'div',
                    {
                        key: 'bank-form-container',
                        id: gatewayId + '-bank-form',
                        className: 'finix-bank-form-container',
                        style: { marginTop: '20px' }
                    }
                ),

                // Receipt description (subscriptions only)
                settings.isSubscription && createElement(
                    'div',
                    { key: 'description-field', className: 'form-row form-row-wide finix-receipt-description', style: { marginTop: '20px' } },
                    [
                        createElement('label', { key: 'label' }, __('Receipt Description (Optional)', 'finix-wc-subs')),
                        createElement('input', {
                            key: 'input',
                            id: 'finix-custom-description',
                            name: 'finix_custom_description',
                            type: 'text',
                            className: 'input-text',
                            placeholder: __('e.g., Gym Membership, Monthly Software', 'finix-wc-subs'),
                            maxLength: 50,
                            style: { width: '100%', padding: '10px', marginTop: '5px' }
                        }),
                        createElement('small', { key: 'help', style: { display: 'block', marginTop: '5px', color: '#666' } },
                            __('This description will appear on your monthly receipts and bank statements.', 'finix-wc-subs')
                        )
                    ]
                )
            ]
        );
    };

    /**
     * Label component
     */
    const Label = (props) => {
        return createElement('span', { className: 'wc-block-components-payment-method-label' }, label);
    };

    /**
     * Register payment method
     */
    registerPaymentMethod({
        name: 'finix_bank_gateway',
        label: createElement(Label),
        content: createElement(Content),
        edit: createElement(Content),
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: settings.supports || []
        }
    });

    console.log('Finix Bank Blocks payment method registered successfully (v1.8.0)');

})(); // End IIFE
