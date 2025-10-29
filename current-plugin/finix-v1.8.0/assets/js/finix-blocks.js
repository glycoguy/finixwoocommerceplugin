/**
 * Finix Blocks Checkout Integration - v1.7.2
 * Based on official Finix plugin approach with Subscription support
 * React component for WooCommerce Blocks checkout with Finix.CardTokenForm and Finix.BankTokenForm
 *
 * v1.7.2 - Fixed payment data field name mismatch (_payment_type_value)
 */

// Version check - ensure latest version is loaded
console.log('Finix Blocks JS Version: 1.7.2 - Field name fix applied');

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement, useState, useEffect } = window.wp.element;
const { getSetting } = window.wc.wcSettings;
const { decodeEntities } = window.wp.htmlEntities;
const { __ } = window.wp.i18n;

// Get payment method data
const settings = getSetting('finix_subscriptions_data', {});

const defaultLabel = __('Credit Card or Bank Transfer', 'finix-wc-subs');
const label = decodeEntities(settings.title || '') || defaultLabel;

// Global Finix form instances
let finixCardForm = null;
let finixBankForm = null;
let fraudSessionId = '';

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
 * Initialize Finix forms with retry logic
 */
function initializeFinixForms(gatewayId, retryCount = 0) {
    const maxRetries = 4;

    const cardFormContainer = document.getElementById(gatewayId + '-card-form');
    const bankFormContainer = document.getElementById(gatewayId + '-bank-form');

    if (retryCount > maxRetries) {
        console.error('Failed to initialize Finix forms after max retries');
        if (cardFormContainer) {
            cardFormContainer.innerHTML = '<p>' + __('Unfortunately, there was an error while loading the payment form. Please try again later.', 'finix-wc-subs') + '</p>';
        }
        return;
    }

    // Initialize Card form
    if (cardFormContainer && cardFormContainer.innerHTML === '' && !finixCardForm) {
        try {
            finixCardForm = window.Finix.CardTokenForm(gatewayId + '-card-form', getFinixFormOptions());
            console.log('Finix Card form initialized for blocks');
        } catch (error) {
            console.error('Failed to initialize Finix Card form:', error);
            setTimeout(() => initializeFinixForms(gatewayId, retryCount + 1), 500);
        }
    }

    // Initialize Bank form
    if (bankFormContainer && bankFormContainer.innerHTML === '' && !finixBankForm) {
        try {
            finixBankForm = window.Finix.BankTokenForm(gatewayId + '-bank-form', getFinixFormOptions());
            console.log('Finix Bank form initialized for blocks');
        } catch (error) {
            console.error('Failed to initialize Finix Bank form:', error);
        }
    }
}

/**
 * Content component
 */
const Content = (props) => {
    const [paymentType, setPaymentType] = useState('card');
    const [isInitialized, setIsInitialized] = useState(false);
    const { eventRegistration, emitResponse } = props;
    const { onPaymentSetup } = eventRegistration;

    const gatewayId = settings.gateway_id || 'finix_subscriptions';

    // Initialize Finix.Auth for fraud session ID
    useEffect(() => {
        if (typeof Finix !== 'undefined' && typeof Finix.Auth === 'function' && settings.merchant && settings.environment) {
            try {
                Finix.Auth(
                    settings.environment,
                    settings.merchant,
                    function(sessionKey) {
                        fraudSessionId = sessionKey;
                        console.log('Finix fraud session initialized for blocks checkout');
                    }
                );
            } catch (error) {
                console.error('Finix.Auth initialization failed:', error);
            }
        }
    }, []);

    // Initialize Finix forms
    useEffect(() => {
        if (typeof Finix === 'undefined') {
            console.error('Finix.js library not loaded!');
            return;
        }

        const timer = setTimeout(() => {
            initializeFinixForms(gatewayId);
            setIsInitialized(true);
        }, 500);

        return () => clearTimeout(timer);
    }, []);

    // Register payment processing event
    useEffect(() => {
        const unsubscribe = onPaymentSetup(async () => {
            console.log('Payment setup started');

            const form = paymentType === 'bank' ? finixBankForm : finixCardForm;

            if (!form) {
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: __('Payment form not initialized. Please refresh and try again.', 'finix-wc-subs')
                };
            }

            // Get token from Finix
            try {
                const token = await new Promise((resolve, reject) => {
                    form.submit(settings.environment, settings.application, function(err, res) {
                        if (err) {
                            console.error('Finix tokenization error:', err);
                            reject(err);
                        } else {
                            const tokenData = res.data || {};
                            const tokenId = tokenData.id;
                            console.log('Finix tokenization successful');
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
                    [gatewayId + '_payment_type_value']: paymentType,
                    finix_custom_description: receiptDescription,
                    finix_nonce: settings.nonce
                };

                // Debug: Log payment data being sent
                console.log('Finix payment data being sent:', paymentData);

                // Return success with payment data
                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: paymentData
                    }
                };
            } catch (error) {
                console.error('Payment processing error:', error);
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: error.message || __('Payment processing failed. Please try again.', 'finix-wc-subs')
                };
            }
        });

        return unsubscribe;
    }, [onPaymentSetup, emitResponse, paymentType]);

    // Handle payment type change
    const handlePaymentTypeChange = (type) => {
        setPaymentType(type);
    };

    return createElement(
        'div',
        { className: 'finix-payment-form finix-blocks-form' },
        [
            // Description
            settings.description && createElement(
                'p',
                { key: 'description', dangerouslySetInnerHTML: { __html: settings.description } }
            ),

            // Payment type selector
            createElement(
                'div',
                { key: 'payment-type', className: 'form-row form-row-wide finix-payment-type-selector' },
                [
                    createElement('label', { key: 'label', className: 'finix-payment-type-label' }, __('Payment Method', 'finix-wc-subs')),
                    createElement(
                        'div',
                        { key: 'selector', className: 'finix-payment-type-options', style: { marginTop: '10px' } },
                        [
                            createElement(
                                'label',
                                { key: 'card-label', className: 'finix-radio-label', style: { marginRight: '20px', display: 'inline-block' } },
                                [
                                    createElement('input', {
                                        key: 'card-input',
                                        type: 'radio',
                                        name: gatewayId + '_payment_type',
                                        value: 'card',
                                        checked: paymentType === 'card',
                                        onChange: () => handlePaymentTypeChange('card'),
                                        style: { marginRight: '5px' }
                                    }),
                                    __('Credit Card', 'finix-wc-subs')
                                ]
                            ),
                            createElement(
                                'label',
                                { key: 'bank-label', className: 'finix-radio-label', style: { display: 'inline-block' } },
                                [
                                    createElement('input', {
                                        key: 'bank-input',
                                        type: 'radio',
                                        name: gatewayId + '_payment_type',
                                        value: 'bank',
                                        checked: paymentType === 'bank',
                                        onChange: () => handlePaymentTypeChange('bank'),
                                        style: { marginRight: '5px' }
                                    }),
                                    __('Bank Account (EFT)', 'finix-wc-subs')
                                ]
                            )
                        ]
                    )
                ]
            ),

            // Card form container
            paymentType === 'card' && createElement(
                'div',
                {
                    key: 'card-form-container',
                    id: gatewayId + '-card-form',
                    className: 'finix-card-form-container',
                    style: { marginTop: '20px' }
                }
            ),

            // Bank form container
            paymentType === 'bank' && createElement(
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
    name: 'finix_subscriptions',
    label: createElement(Label),
    content: createElement(Content),
    edit: createElement(Content),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports || []
    }
});

console.log('Finix Blocks payment method registered successfully (v1.7.2)');
