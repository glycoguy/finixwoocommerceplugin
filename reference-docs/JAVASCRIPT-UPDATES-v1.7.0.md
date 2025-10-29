# JAVASCRIPT UPDATES FOR v1.7.0

## Add Fraud Session ID to finix-payment.js

**Location:** `assets/js/finix-payment.js`

**ADD this code at the very beginning of the jQuery ready function:**

```javascript
jQuery(function($) {
    'use strict';

    // NEW v1.7.0: Initialize Finix Auth for fraud detection
    // This MUST be first, before any other code
    if (typeof Finix !== 'undefined' && typeof finix_params !== 'undefined') {
        try {
            Finix.Auth(
                finix_params.environment,
                finix_params.merchant_id,
                function(sessionKey) {
                    console.log('Finix fraud session initialized');
                    let fraudField = $('#finix_fraud_session_id');
                    if (!fraudField.length) {
                        $('form.checkout, form#order_review').prepend(
                            '<input type="hidden" id="finix_fraud_session_id" name="finix_fraud_session_id" value=""/>'
                        );
                        fraudField = $('#finix_fraud_session_id');
                    }
                    fraudField.val(sessionKey);
                }
            );
        } catch (error) {
            console.warn('Finix Auth initialization failed:', error);
        }
    }

    // ... rest of existing code ...
```

---

## Add Fraud Session ID to finix-blocks.js

**Location:** `assets/js/finix-blocks.js`

**FIND the Content component and ADD fraud session support in the useEffect:**

```javascript
const Content = (props) => {
    const [paymentType, setPaymentType] = useState('card');
    const [isInitialized, setIsInitialized] = useState(false);
    const [fraudSessionId, setFraudSessionId] = useState(''); // NEW v1.7.0
    const { eventRegistration, emitResponse } = props;
    const { onPaymentSetup } = eventRegistration;

    // Initialize Finix.js
    useEffect(() => {
        if (typeof Finix !== 'undefined' && !finixInstance) {
            try {
                finixInstance = Finix(settings.applicationId, settings.environment);
                setIsInitialized(true);
                console.log('Finix.js initialized in blocks checkout');
                
                // NEW v1.7.0: Initialize fraud detection
                try {
                    Finix.Auth(
                        settings.environment,
                        settings.merchantId,
                        function(sessionKey) {
                            console.log('Finix fraud session initialized (blocks)');
                            setFraudSessionId(sessionKey);
                        }
                    );
                } catch (authError) {
                    console.warn('Finix Auth initialization failed:', authError);
                }
            } catch (error) {
                console.error('Failed to initialize Finix.js:', error);
            }
        }
    }, []);
```

**THEN, in the processCreditCard and processBankAccount functions, include fraud session ID in the payment data:**

```javascript
// In processCreditCard success handler:
resolve({
    type: emitResponse.responseTypes.SUCCESS,
    meta: {
        paymentMethodData: {
            finix_payment_token: result.instrument_id,
            finix_identity_id: result.identity_id,
            finix_instrument_id: result.instrument_id,
            finix_custom_description: receiptDescription,
            finix_payment_type: 'card',
            finix_fraud_session_id: fraudSessionId // NEW v1.7.0
        }
    }
});

// In processBankAccount success handler:
return {
    type: emitResponse.responseTypes.SUCCESS,
    meta: {
        paymentMethodData: {
            finix_payment_token: result.instrument_id,
            finix_identity_id: result.identity_id,
            finix_instrument_id: result.instrument_id,
            finix_custom_description: receiptDescription,
            finix_payment_type: 'bank',
            finix_pad_agreement: 'yes',
            finix_fraud_session_id: fraudSessionId // NEW v1.7.0
        }
    }
};
```

**AND update the settings localization to include merchantId:**

In `class-finix-blocks-integration.php`, find the `get_payment_method_data()` method and ADD:

```php
return array(
    // ... existing settings ...
    'merchantId' => $gateway->get_option($gateway->testmode ? 'test_merchant_id' : 'live_merchant_id'), // NEW
    // ... rest of settings ...
);
```

---

## Why Fraud Session ID Matters

According to the official Finix plugin:
1. Increases payment approval rates
2. Reduces fraud false positives
3. Required for optimal payment processing

Without it, you may see:
- Lower approval rates
- More legitimate payments declined
- Suboptimal fraud detection

---

## Testing

After adding fraud session ID:

1. **Console Check:**
   - Look for: "Finix fraud session initialized"
   - Check: Hidden field `finix_fraud_session_id` exists and has value

2. **Network Check:**
   - In DevTools Network tab, check the order creation request
   - Verify: `finix_fraud_session_id` is included in POST data

3. **Payment Success:**
   - Test a payment
   - Check WooCommerce logs
   - Verify no fraud-related errors
