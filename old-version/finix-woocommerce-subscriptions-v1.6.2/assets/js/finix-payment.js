/**
 * Finix Payment Form Handler
 * Handles card tokenization and payment form submission
 * Supports both classic and blocks checkout
 */

jQuery(function($) {
    'use strict';

    const FinixPaymentForm = {
        
        /**
         * Initialize
         */
        init: function() {
            this.form = $('form.checkout, form#order_review, form.wc-block-checkout__form');
            
            if (this.form.length === 0) {
                return;
            }

            this.attachEventHandlers();
            this.formatCardFields();
            this.initBlocksCheckout();
        },

        /**
         * Initialize blocks checkout support
         */
        initBlocksCheckout: function() {
            const self = this;
            
            // Check if this is the new blocks checkout
            if ($('.wc-block-checkout').length > 0) {
                // Monitor for payment method selection
                $(document).on('change', 'input[name="radio-control-wc-payment-method-options"]', function() {
                    if ($(this).val() === 'finix') {
                        self.initializeFinixFields();
                    }
                });
                
                // Also check on page load if Finix is pre-selected
                setTimeout(function() {
                    if ($('input[name="radio-control-wc-payment-method-options"]:checked').val() === 'finix') {
                        self.initializeFinixFields();
                    }
                }, 500);
            }
        },

        /**
         * Initialize Finix-specific fields in blocks checkout
         */
        initializeFinixFields: function() {
            // Add custom description field if it's a subscription and doesn't exist
            if (finix_params.is_subscription && $('#finix-custom-description').length === 0) {
                var descField = '<div class="wc-block-components-text-input finix-custom-description-wrapper" style="margin-top: 1rem;">' +
                    '<label for="finix-custom-description" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Receipt Description</label>' +
                    '<input type="text" id="finix-custom-description" name="finix_custom_description" ' +
                    'class="wc-block-components-text-input__input" ' +
                    'style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;" ' +
                    'placeholder="Optional: Add a note that will appear on your monthly receipts" />' +
                    '<small style="display: block; margin-top: 0.5rem; color: #666; font-size: 0.875rem;">This description will appear on your monthly subscription receipts.</small>' +
                    '</div>';
                
                // Try different selectors for where to append
                if ($('.wc-block-checkout__payment-method--finix').length > 0) {
                    $('.wc-block-checkout__payment-method--finix').append(descField);
                } else if ($('#finix-payment-form').length > 0) {
                    $('#finix-payment-form').append(descField);
                } else if ($('.wc-block-components-radio-control-accordion-option[value="finix"]').length > 0) {
                    $('.wc-block-components-radio-control-accordion-option[value="finix"]').closest('.wc-block-components-radio-control-accordion-option').find('.wc-block-components-radio-control__option-content').append(descField);
                }
                
                // Reinitialize formatting for any new fields
                this.formatCardFields();
            }
        },

        /**
         * Attach event handlers
         */
        attachEventHandlers: function() {
            const self = this;
            
            // Classic checkout
            this.form.on('checkout_place_order_finix', function() {
                return self.handleFormSubmission();
            });
            
            // Blocks checkout - listen for place order event
            $(document).on('checkout_place_order', function(e) {
                var paymentMethod = $('input[name="radio-control-wc-payment-method-options"]:checked').val();
                if (paymentMethod === 'finix') {
                    return self.handleFormSubmission();
                }
            });

            // Handle card input changes
            $(document).on('input', '#finix-card-number', function() {
                self.formatCardNumber($(this));
            });

            $(document).on('input', '#finix-card-expiry', function() {
                self.formatCardExpiry($(this));
            });

            $(document).on('input', '#finix-card-cvc', function() {
                self.formatCardCVC($(this));
            });
        },

        /**
         * Format card fields
         */
        formatCardFields: function() {
            // Restrict card number to digits and spaces
            $(document).on('keypress', '#finix-card-number', function(e) {
                const char = String.fromCharCode(e.which);
                if (!/[0-9\s]/.test(char)) {
                    e.preventDefault();
                }
            });

            // Restrict expiry to digits and /
            $(document).on('keypress', '#finix-card-expiry', function(e) {
                const char = String.fromCharCode(e.which);
                if (!/[0-9\/]/.test(char)) {
                    e.preventDefault();
                }
            });

            // Restrict CVC to digits
            $(document).on('keypress', '#finix-card-cvc', function(e) {
                const char = String.fromCharCode(e.which);
                if (!/[0-9]/.test(char)) {
                    e.preventDefault();
                }
            });
        },

        /**
         * Format card number with spaces
         */
        formatCardNumber: function($input) {
            let value = $input.val().replace(/\s/g, '');
            let formatted = value.match(/.{1,4}/g);
            
            if (formatted) {
                $input.val(formatted.join(' '));
            }
        },

        /**
         * Format card expiry as MM/YY
         */
        formatCardExpiry: function($input) {
            let value = $input.val().replace(/\D/g, '');
            
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            
            $input.val(value);
        },

        /**
         * Format CVC
         */
        formatCardCVC: function($input) {
            let value = $input.val().replace(/\D/g, '').substring(0, 4);
            $input.val(value);
        },

        /**
         * Handle form submission
         */
        handleFormSubmission: function() {
            // Check if Finix is selected
            var isFinixSelected = $('#payment_method_finix').is(':checked') || 
                                 $('input[name="radio-control-wc-payment-method-options"]:checked').val() === 'finix';
            
            if (!isFinixSelected) {
                return true;
            }

            // Check if we already have a token
            if ($('#finix-payment-token').val() !== '') {
                return true;
            }

            // Prevent multiple submissions
            if (this.form.data('finix-processing')) {
                return false;
            }

            // Get card details
            const cardData = this.getCardData();

            // Validate card data
            if (!this.validateCardData(cardData)) {
                return false;
            }

            // Mark as processing
            this.form.data('finix-processing', true);
            
            // Block the form
            if (typeof this.form.block === 'function') {
                this.form.block({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });
            }

            // Create identity and tokenize card
            this.createIdentityAndTokenize(cardData);

            return false;
        },

        /**
         * Get card data from form
         */
        getCardData: function() {
            const expiryParts = $('#finix-card-expiry').val().split('/');
            
            return {
                number: $('#finix-card-number').val().replace(/\s/g, ''),
                exp_month: $.trim(expiryParts[0]),
                exp_year: $.trim(expiryParts[1]),
                cvv: $('#finix-card-cvc').val(),
                name: $('#billing_first_name').val() + ' ' + $('#billing_last_name').val(),
                first_name: $('#billing_first_name').val(),
                last_name: $('#billing_last_name').val(),
                email: $('#billing_email').val(),
                phone: $('#billing_phone').val(),
                address_line1: $('#billing_address_1').val(),
                address_line2: $('#billing_address_2').val(),
                city: $('#billing_city').val(),
                state: $('#billing_state').val(),
                postal_code: $('#billing_postcode').val(),
                country: $('#billing_country').val()
            };
        },

        /**
         * Validate card data
         */
        validateCardData: function(cardData) {
            let errors = [];

            // Validate card number
            if (!cardData.number || cardData.number.length < 13) {
                errors.push('Please enter a valid card number.');
            }

            // Validate expiry
            if (!cardData.exp_month || !cardData.exp_year) {
                errors.push('Please enter a valid expiry date.');
            } else {
                const currentYear = new Date().getFullYear() % 100;
                const currentMonth = new Date().getMonth() + 1;
                const expYear = parseInt(cardData.exp_year);
                const expMonth = parseInt(cardData.exp_month);

                if (expMonth < 1 || expMonth > 12) {
                    errors.push('Invalid expiry month.');
                } else if (expYear < currentYear || (expYear === currentYear && expMonth < currentMonth)) {
                    errors.push('Card has expired.');
                }
            }

            // Validate CVV
            if (!cardData.cvv || cardData.cvv.length < 3) {
                errors.push('Please enter a valid CVV.');
            }

            if (errors.length > 0) {
                this.showError(errors.join('<br>'));
                return false;
            }

            return true;
        },

        /**
         * Create identity and tokenize card
         */
        createIdentityAndTokenize: function(cardData) {
            const self = this;
            
            $.ajax({
                url: wc_checkout_params.ajax_url || ajaxurl,
                type: 'POST',
                data: {
                    action: 'finix_create_payment_token',
                    card_data: cardData,
                    nonce: finix_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Set the token
                        $('#finix-payment-token').val(response.data.instrument_id);
                        $('#finix-identity-id').val(response.data.identity_id);
                        
                        // Remove processing flag
                        self.form.data('finix-processing', false);
                        
                        // Submit the form
                        self.form.submit();
                    } else {
                        self.showError(response.data.message);
                        self.form.data('finix-processing', false);
                        if (typeof self.form.unblock === 'function') {
                            self.form.unblock();
                        }
                    }
                },
                error: function() {
                    self.showError('An error occurred while processing your payment. Please try again.');
                    self.form.data('finix-processing', false);
                    if (typeof self.form.unblock === 'function') {
                        self.form.unblock();
                    }
                }
            });
        },

        /**
         * Show error message
         */
        showError: function(message) {
            // Remove existing errors
            $('.woocommerce-error, .woocommerce-message, .wc-block-components-notice-banner').remove();
            
            // Classic checkout
            if ($('form.checkout').length > 0) {
                this.form.prepend('<div class="woocommerce-error">' + message + '</div>');
                $('html, body').animate({
                    scrollTop: (this.form.offset().top - 100)
                }, 1000);
            } 
            // Blocks checkout
            else if ($('.wc-block-checkout').length > 0) {
                var errorHtml = '<div class="wc-block-components-notice-banner is-error" role="alert">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">' +
                    '<path d="M12 3.2c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16c-4 0-7.2-3.3-7.2-7.2C4.8 8 8 4.8 12 4.8s7.2 3.3 7.2 7.2c0 4-3.2 7.2-7.2 7.2zM11 17h2v-6h-2v6zm0-8h2V7h-2v2z"></path>' +
                    '</svg>' +
                    '<div class="wc-block-components-notice-banner__content">' + message + '</div>' +
                    '</div>';
                $('.wc-block-checkout').prepend(errorHtml);
                $('html, body').animate({
                    scrollTop: 0
                }, 1000);
            }
        }
    };

    // Initialize
    FinixPaymentForm.init();
});
