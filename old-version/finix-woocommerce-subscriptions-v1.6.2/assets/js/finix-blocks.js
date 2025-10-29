/**
 * Finix Blocks Checkout Integration - FIXED v1.6.1
 * React component for WooCommerce Blocks checkout with Finix.js tokenization
 * 
 * CRITICAL FIX: Added onPaymentSetup event handler for payment processing
 */

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement, useState, useEffect } = window.wp.element;
const { getSetting } = window.wc.wcSettings;
const { decodeEntities } = window.wp.htmlEntities;
const { __ } = window.wp.i18n;

// Get payment method data
const settings = getSetting('finix_subscriptions_data', {});

const defaultLabel = __('Credit Card or Bank Transfer', 'finix-wc-subs');
const label = decodeEntities(settings.title || '') || defaultLabel;

// Global Finix instance
let finixInstance = null;
let finixInitialized = false;

/**
 * Initialize Finix.js
 */
function initializeFinix(applicationId, environment) {
    return new Promise((resolve, reject) => {
        // Check if Finix is available
        if (typeof Finix === 'undefined') {
            console.error('Finix.js library not loaded!');
            reject(new Error('Finix.js library not loaded'));
            return;
        }

        // Check if already initialized
        if (finixInstance && finixInitialized) {
            resolve(finixInstance);
            return;
        }

        try {
            finixInstance = Finix(applicationId, environment);
            finixInitialized = true;
            console.log('Finix.js initialized successfully', {
                applicationId: applicationId,
                environment: environment
            });
            resolve(finixInstance);
        } catch (error) {
            console.error('Failed to initialize Finix.js:', error);
            reject(error);
        }
    });
}

/**
 * Content component
 */
const Content = (props) => {
    const [paymentType, setPaymentType] = useState('card');
    const [isInitialized, setIsInitialized] = useState(false);
    const { eventRegistration, emitResponse } = props;
    const { onPaymentSetup } = eventRegistration;

    // Initialize Finix.js
    useEffect(() => {
        const init = async () => {
            try {
                await initializeFinix(settings.applicationId, settings.environment);
                setIsInitialized(true);
            } catch (error) {
                console.error('Finix initialization failed:', error);
                setIsInitialized(false);
            }
        };

        // Wait a bit for Finix.js to load
        const timer = setTimeout(init, 500);
        return () => clearTimeout(timer);
    }, [settings.applicationId, settings.environment]);

    // Register payment processing event
    useEffect(() => {
        const unsubscribe = onPaymentSetup(async () => {
            console.log('Payment setup started');
            
            try {
                // Get payment type
                const selectedPaymentType = document.querySelector('input[name="finix_payment_type"]:checked')?.value || 'card';
                
                if (selectedPaymentType === 'card') {
                    // Process credit card
                    return await processCreditCard();
                } else {
                    // Process bank account
                    return await processBankAccount();
                }
            } catch (error) {
                console.error('Payment processing error:', error);
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: error.message || 'Payment processing failed'
                };
            }
        });

        return unsubscribe;
    }, [onPaymentSetup, emitResponse, isInitialized]);

    /**
     * Process credit card payment
     */
    const processCreditCard = async () => {
        console.log('Processing credit card payment');
        
        // Check if Finix is initialized
        if (!finixInstance || !finixInitialized) {
            console.error('Finix.js not initialized');
            throw new Error('Payment system not ready. Please refresh and try again.');
        }
        
        // Get card data from form
        const cardNumber = document.getElementById('finix-card-number')?.value.replace(/\s/g, '') || '';
        const cardholderName = document.getElementById('finix-cardholder-name')?.value || '';
        const expiry = document.getElementById('finix-card-expiry')?.value || '';
        const cvv = document.getElementById('finix-card-cvc')?.value || '';
        
        // Validate card data
        if (!cardNumber || cardNumber.length < 13) {
            throw new Error('Please enter a valid card number');
        }
        
        if (!cardholderName) {
            throw new Error('Please enter the cardholder name');
        }
        
        const expiryParts = expiry.split('/');
        if (expiryParts.length !== 2) {
            throw new Error('Please enter a valid expiry date (MM/YY)');
        }
        
        const expMonth = expiryParts[0].trim();
        const expYear = expiryParts[1].trim();
        
        if (!cvv || cvv.length < 3) {
            throw new Error('Please enter a valid CVV');
        }
        
        // Tokenize with Finix.js
        console.log('Tokenizing card...');
        
        return new Promise((resolve) => {
            finixInstance.tokenize({
                type: 'PAYMENT_CARD',
                number: cardNumber,
                security_code: cvv,
                expiration_month: expMonth,
                expiration_year: expYear.length === 2 ? '20' + expYear : expYear,
                name: cardholderName
            }, async (error, response) => {
                if (error) {
                    console.error('Tokenization error:', error);
                    resolve({
                        type: emitResponse.responseTypes.ERROR,
                        message: error.message || 'Card tokenization failed'
                    });
                    return;
                }
                
                console.log('Card tokenized successfully:', response.data.id);
                
                // Get billing data
                const billingData = getBillingData();
                
                // Associate token with identity via AJAX
                try {
                    const result = await associateToken(response.data.id, billingData);
                    
                    // Get receipt description if present
                    const receiptDescription = document.getElementById('finix-custom-description')?.value || '';
                    
                    // Return success with payment data
                    resolve({
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: {
                                finix_payment_token: result.instrument_id,
                                finix_identity_id: result.identity_id,
                                finix_instrument_id: result.instrument_id,
                                finix_custom_description: receiptDescription,
                                finix_payment_type: 'card'
                            }
                        }
                    });
                } catch (ajaxError) {
                    console.error('Token association error:', ajaxError);
                    resolve({
                        type: emitResponse.responseTypes.ERROR,
                        message: ajaxError.message || 'Failed to process payment'
                    });
                }
            });
        });
    };

    /**
     * Process bank account payment
     */
    const processBankAccount = async () => {
        console.log('Processing bank account payment');
        
        // Get bank data from form
        const accountNumber = document.getElementById('finix-account-number')?.value || '';
        const institutionNumber = document.getElementById('finix-institution-number')?.value || '';
        const transitNumber = document.getElementById('finix-transit-number')?.value || '';
        const accountType = document.getElementById('finix-account-type')?.value || '';
        const accountHolderName = document.getElementById('finix-account-holder-name')?.value || '';
        
        // Validate bank data
        if (!accountNumber || !institutionNumber || !transitNumber) {
            throw new Error('Please fill in all bank account fields');
        }
        
        if (!accountHolderName) {
            throw new Error('Please enter the account holder name');
        }
        
        // Check PAD agreement
        const padAgreed = document.getElementById('finix-pad-agreement')?.checked;
        if (!padAgreed) {
            throw new Error('You must agree to pre-authorized debits');
        }
        
        // Get billing data
        const billingData = getBillingData();
        
        // Create bank instrument via AJAX
        const bankData = {
            ...billingData,
            account_number: accountNumber,
            institution_number: institutionNumber,
            transit_number: transitNumber,
            account_type: accountType,
            account_holder_name: accountHolderName,
            country: 'CAN'
        };
        
        try {
            const result = await createBankInstrument(bankData);
            
            // Get receipt description if present
            const receiptDescription = document.getElementById('finix-custom-description')?.value || '';
            
            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        finix_payment_token: result.instrument_id,
                        finix_identity_id: result.identity_id,
                        finix_instrument_id: result.instrument_id,
                        finix_custom_description: receiptDescription,
                        finix_payment_type: 'bank',
                        finix_pad_agreement: 'yes'
                    }
                }
            };
        } catch (error) {
            console.error('Bank instrument creation error:', error);
            return {
                type: emitResponse.responseTypes.ERROR,
                message: error.message || 'Failed to process bank account'
            };
        }
    };

    /**
     * Get billing data from checkout form
     */
    const getBillingData = () => {
        return {
            first_name: document.getElementById('billing-first_name')?.value || 
                       document.querySelector('input[name="billing_first_name"]')?.value || '',
            last_name: document.getElementById('billing-last_name')?.value || 
                      document.querySelector('input[name="billing_last_name"]')?.value || '',
            email: document.getElementById('email')?.value || 
                  document.querySelector('input[name="email"]')?.value || '',
            phone: document.getElementById('billing-phone')?.value || 
                  document.querySelector('input[name="billing_phone"]')?.value || '',
            address_line1: document.getElementById('billing-address_1')?.value || 
                          document.querySelector('input[name="billing_address_1"]')?.value || '',
            address_line2: document.getElementById('billing-address_2')?.value || 
                          document.querySelector('input[name="billing_address_2"]')?.value || '',
            city: document.getElementById('billing-city')?.value || 
                 document.querySelector('input[name="billing_city"]')?.value || '',
            state: document.getElementById('billing-state')?.value || 
                  document.querySelector('select[name="billing_state"]')?.value || '',
            postal_code: document.getElementById('billing-postcode')?.value || 
                        document.querySelector('input[name="billing_postcode"]')?.value || '',
            country: document.getElementById('billing-country')?.value || 
                    document.querySelector('select[name="billing_country"]')?.value || 'CA'
        };
    };

    /**
     * Associate token with identity via AJAX
     */
    const associateToken = (token, billingData) => {
        return new Promise((resolve, reject) => {
            jQuery.ajax({
                url: settings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'finix_associate_token',
                    token: token,
                    billing_data: billingData,
                    nonce: settings.nonce
                },
                success: (response) => {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data.message || 'Token association failed'));
                    }
                },
                error: (xhr, status, error) => {
                    reject(new Error('AJAX error: ' + error));
                }
            });
        });
    };

    /**
     * Create bank instrument via AJAX
     */
    const createBankInstrument = (bankData) => {
        return new Promise((resolve, reject) => {
            jQuery.ajax({
                url: settings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'finix_create_bank_instrument',
                    bank_data: bankData,
                    nonce: settings.nonce
                },
                success: (response) => {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data.message || 'Bank instrument creation failed'));
                    }
                },
                error: (xhr, status, error) => {
                    reject(new Error('AJAX error: ' + error));
                }
            });
        });
    };

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
                { key: 'payment-type', className: 'form-row form-row-wide' },
                [
                    createElement('label', { key: 'label', className: 'finix-payment-type-label' }, __('Payment Method', 'finix-wc-subs')),
                    createElement(
                        'div',
                        { key: 'selector', className: 'finix-payment-type-selector' },
                        [
                            createElement(
                                'label',
                                { key: 'card-label', className: 'finix-radio-label' },
                                [
                                    createElement('input', {
                                        key: 'card-input',
                                        type: 'radio',
                                        name: 'finix_payment_type',
                                        value: 'card',
                                        checked: paymentType === 'card',
                                        onChange: () => handlePaymentTypeChange('card')
                                    }),
                                    ' ' + __('Credit Card', 'finix-wc-subs')
                                ]
                            ),
                            createElement(
                                'label',
                                { key: 'bank-label', className: 'finix-radio-label', style: { marginLeft: '15px' } },
                                [
                                    createElement('input', {
                                        key: 'bank-input',
                                        type: 'radio',
                                        name: 'finix_payment_type',
                                        value: 'bank',
                                        checked: paymentType === 'bank',
                                        onChange: () => handlePaymentTypeChange('bank')
                                    }),
                                    ' ' + __('Bank Account (EFT)', 'finix-wc-subs')
                                ]
                            )
                        ]
                    )
                ]
            ),

            // Credit Card Fields (shown when card is selected)
            paymentType === 'card' && createElement(
                'div',
                { key: 'card-fields', className: 'finix-card-fields' },
                [
                    // Cardholder Name
                    createElement(
                        'div',
                        { key: 'name', className: 'form-row form-row-wide' },
                        [
                            createElement('label', { key: 'label' }, [
                                __('Cardholder Name', 'finix-wc-subs'),
                                createElement('span', { key: 'required', className: 'required' }, ' *')
                            ]),
                            createElement('input', {
                                key: 'input',
                                id: 'finix-cardholder-name',
                                name: 'finix_cardholder_name',
                                type: 'text',
                                placeholder: __('John Doe', 'finix-wc-subs'),
                                required: true
                            })
                        ]
                    ),

                    // Card Number
                    createElement(
                        'div',
                        { key: 'number', className: 'form-row form-row-wide' },
                        [
                            createElement('label', { key: 'label' }, [
                                __('Card Number', 'finix-wc-subs'),
                                createElement('span', { key: 'required', className: 'required' }, ' *')
                            ]),
                            createElement('input', {
                                key: 'input',
                                id: 'finix-card-number',
                                type: 'text',
                                placeholder: '4111 1111 1111 1111',
                                maxLength: 19,
                                required: true
                            })
                        ]
                    ),

                    // Expiry and CVV
                    createElement(
                        'div',
                        { key: 'expiry-cvv', className: 'form-row form-row-first' },
                        [
                            createElement(
                                'div',
                                { key: 'expiry', className: 'form-row' },
                                [
                                    createElement('label', { key: 'label' }, [
                                        __('Expiry (MM/YY)', 'finix-wc-subs'),
                                        createElement('span', { key: 'required', className: 'required' }, ' *')
                                    ]),
                                    createElement('input', {
                                        key: 'input',
                                        id: 'finix-card-expiry',
                                        type: 'text',
                                        placeholder: 'MM/YY',
                                        maxLength: 5,
                                        required: true
                                    })
                                ]
                            ),
                            createElement(
                                'div',
                                { key: 'cvv', className: 'form-row' },
                                [
                                    createElement('label', { key: 'label' }, [
                                        __('CVV', 'finix-wc-subs'),
                                        createElement('span', { key: 'required', className: 'required' }, ' *')
                                    ]),
                                    createElement('input', {
                                        key: 'input',
                                        id: 'finix-card-cvc',
                                        type: 'text',
                                        placeholder: '123',
                                        maxLength: 4,
                                        required: true
                                    })
                                ]
                            )
                        ]
                    )
                ]
            ),

            // Bank Account Fields (shown when bank is selected)
            paymentType === 'bank' && createElement(
                'div',
                { key: 'bank-fields', className: 'finix-bank-fields' },
                [
                    // Account Holder Name
                    createElement(
                        'div',
                        { key: 'holder-name', className: 'form-row form-row-wide' },
                        [
                            createElement('label', { key: 'label' }, [
                                __('Account Holder Name', 'finix-wc-subs'),
                                createElement('span', { key: 'required', className: 'required' }, ' *')
                            ]),
                            createElement('input', {
                                key: 'input',
                                id: 'finix-account-holder-name',
                                type: 'text',
                                placeholder: __('John Doe', 'finix-wc-subs'),
                                required: true
                            })
                        ]
                    ),

                    // Institution Number
                    createElement(
                        'div',
                        { key: 'institution', className: 'form-row form-row-first' },
                        [
                            createElement('label', { key: 'label' }, [
                                __('Institution Number', 'finix-wc-subs'),
                                createElement('span', { key: 'required', className: 'required' }, ' *')
                            ]),
                            createElement('input', {
                                key: 'input',
                                id: 'finix-institution-number',
                                type: 'text',
                                placeholder: '001',
                                maxLength: 3,
                                required: true
                            }),
                            createElement('small', { key: 'help' }, __('3 digits', 'finix-wc-subs'))
                        ]
                    ),

                    // Transit Number
                    createElement(
                        'div',
                        { key: 'transit', className: 'form-row form-row-last' },
                        [
                            createElement('label', { key: 'label' }, [
                                __('Transit Number', 'finix-wc-subs'),
                                createElement('span', { key: 'required', className: 'required' }, ' *')
                            ]),
                            createElement('input', {
                                key: 'input',
                                id: 'finix-transit-number',
                                type: 'text',
                                placeholder: '12345',
                                maxLength: 5,
                                required: true
                            }),
                            createElement('small', { key: 'help' }, __('5 digits', 'finix-wc-subs'))
                        ]
                    ),

                    // Account Number
                    createElement(
                        'div',
                        { key: 'account', className: 'form-row form-row-wide' },
                        [
                            createElement('label', { key: 'label' }, [
                                __('Account Number', 'finix-wc-subs'),
                                createElement('span', { key: 'required', className: 'required' }, ' *')
                            ]),
                            createElement('input', {
                                key: 'input',
                                id: 'finix-account-number',
                                type: 'text',
                                placeholder: '1234567',
                                maxLength: 12,
                                required: true
                            })
                        ]
                    ),

                    // Account Type
                    createElement(
                        'div',
                        { key: 'type', className: 'form-row form-row-wide' },
                        [
                            createElement('label', { key: 'label' }, [
                                __('Account Type', 'finix-wc-subs'),
                                createElement('span', { key: 'required', className: 'required' }, ' *')
                            ]),
                            createElement(
                                'select',
                                {
                                    key: 'select',
                                    id: 'finix-account-type',
                                    required: true
                                },
                                [
                                    createElement('option', { key: 'checking', value: 'checking' }, __('Personal Checking', 'finix-wc-subs')),
                                    createElement('option', { key: 'savings', value: 'savings' }, __('Personal Savings', 'finix-wc-subs')),
                                    createElement('option', { key: 'business_checking', value: 'business_checking' }, __('Business Checking', 'finix-wc-subs')),
                                    createElement('option', { key: 'business_savings', value: 'business_savings' }, __('Business Savings', 'finix-wc-subs'))
                                ]
                            )
                        ]
                    ),

                    // PAD Agreement
                    createElement(
                        'div',
                        { key: 'pad', className: 'form-row form-row-wide finix-pad-agreement' },
                        [
                            createElement(
                                'label',
                                { key: 'label' },
                                [
                                    createElement('input', {
                                        key: 'input',
                                        type: 'checkbox',
                                        id: 'finix-pad-agreement',
                                        name: 'finix_pad_agreement',
                                        required: true
                                    }),
                                    ' ' + __('I authorize pre-authorized debits (PAD) from my bank account for recurring subscription payments.', 'finix-wc-subs'),
                                    createElement('span', { key: 'required', className: 'required' }, ' *')
                                ]
                            )
                        ]
                    )
                ]
            ),

            // Receipt description (subscriptions only)
            settings.isSubscription && createElement(
                'div',
                { key: 'description', className: 'form-row form-row-wide finix-receipt-description' },
                [
                    createElement('label', { key: 'label' }, __('Receipt Description (Optional)', 'finix-wc-subs')),
                    createElement('input', {
                        key: 'input',
                        id: 'finix-custom-description',
                        name: 'finix_custom_description',
                        type: 'text',
                        placeholder: __('e.g., Gym Membership, Monthly Software', 'finix-wc-subs'),
                        maxLength: 50
                    }),
                    createElement('small', { key: 'help' }, __('This description will appear on your monthly receipts and bank statements.', 'finix-wc-subs'))
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

console.log('Finix Blocks payment method registered successfully (v1.6.1 - FIXED)');

/**
 * Initialize field formatting
 */
jQuery(document).ready(function($) {
    // Wait for blocks checkout to be ready
    const initBlocksFormatting = () => {
        // Format card number
        $(document).on('input', '#finix-card-number', function() {
            let val = $(this).val().replace(/\s/g, '');
            let formatted = val.match(/.{1,4}/g);
            if (formatted) {
                $(this).val(formatted.join(' '));
            }
        });

        // Format expiry
        $(document).on('input', '#finix-card-expiry', function() {
            let val = $(this).val().replace(/\D/g, '');
            if (val.length >= 2) {
                val = val.substring(0, 2) + '/' + val.substring(2, 4);
            }
            $(this).val(val);
        });

        // Format CVV
        $(document).on('input', '#finix-card-cvc', function() {
            let val = $(this).val().replace(/\D/g, '').substring(0, 4);
            $(this).val(val);
        });

        // Restrict bank account fields to numbers
        $(document).on('keypress', '#finix-account-number, #finix-institution-number, #finix-transit-number', function(e) {
            const char = String.fromCharCode(e.which);
            if (!/[0-9]/.test(char)) {
                e.preventDefault();
            }
        });

        console.log('Finix blocks field formatting initialized');
    };

    // Initialize after a short delay to ensure blocks are loaded
    setTimeout(initBlocksFormatting, 1000);
});
