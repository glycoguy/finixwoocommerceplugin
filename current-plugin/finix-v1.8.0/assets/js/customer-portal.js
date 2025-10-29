/**
 * Finix Customer Portal - Inline Description Editing
 * v1.7.1
 */

jQuery(document).ready(function($) {
    'use strict';

    // Safety check: Ensure finixCustomerPortal object exists
    if (typeof finixCustomerPortal === 'undefined') {
        console.error('Finix Customer Portal: finixCustomerPortal object not found. Script cannot initialize.');
        return;
    }

    // Handle Edit button click
    $(document).on('click', '.finix-edit-description-btn', function(e) {
        e.preventDefault();

        const $container = $(this).closest('.finix-description-container');
        const $display = $container.find('.finix-description-display');
        const $editForm = $container.find('.finix-description-edit-form');
        const $editBtn = $(this);

        // Hide display and edit button, show edit form
        $display.hide();
        $editBtn.hide();
        $editForm.show();
        $editForm.find('.finix-description-input').focus();
    });

    // Handle Save button click
    $(document).on('click', '.finix-save-description-btn', function(e) {
        e.preventDefault();

        const $container = $(this).closest('.finix-description-container');
        const $input = $container.find('.finix-description-input');
        const $message = $container.find('.finix-description-message');
        const $saveBtn = $(this);
        const $cancelBtn = $container.find('.finix-cancel-description-btn');
        const subscriptionId = $container.data('subscription-id');
        const newDescription = $input.val().trim();

        // Disable buttons during save
        $saveBtn.prop('disabled', true).text(finixCustomerPortal.i18n.saving);
        $cancelBtn.prop('disabled', true);
        $message.removeClass('success error').text('');

        // Send AJAX request
        $.ajax({
            url: finixCustomerPortal.ajaxUrl,
            type: 'POST',
            data: {
                action: 'finix_update_description',
                nonce: finixCustomerPortal.nonce,
                subscription_id: subscriptionId,
                description: newDescription
            },
            success: function(response) {
                if (response.success) {
                    // Update display
                    const displayText = response.data.description || '—';
                    $container.find('.finix-description-display').text(displayText);

                    // Show success message
                    $message.addClass('success').text(response.data.message);

                    // Hide form after 1 second
                    setTimeout(function() {
                        cancelEdit($container);
                    }, 1000);
                } else {
                    // Show error message
                    $message.addClass('error').text(response.data.message || finixCustomerPortal.i18n.error);

                    // Re-enable buttons
                    $saveBtn.prop('disabled', false).text($saveBtn.data('original-text') || 'Save');
                    $cancelBtn.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                $message.addClass('error').text(finixCustomerPortal.i18n.error);

                // Re-enable buttons
                $saveBtn.prop('disabled', false).text($saveBtn.data('original-text') || 'Save');
                $cancelBtn.prop('disabled', false);
            }
        });
    });

    // Handle Cancel button click
    $(document).on('click', '.finix-cancel-description-btn', function(e) {
        e.preventDefault();

        const $container = $(this).closest('.finix-description-container');
        cancelEdit($container);
    });

    // Handle Enter key in input
    $(document).on('keydown', '.finix-description-input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $(this).closest('.finix-description-edit-form').find('.finix-save-description-btn').click();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelEdit($(this).closest('.finix-description-container'));
        }
    });

    /**
     * Cancel edit and restore display
     */
    function cancelEdit($container) {
        const $display = $container.find('.finix-description-display');
        const $editForm = $container.find('.finix-description-edit-form');
        const $editBtn = $container.find('.finix-edit-description-btn');
        const $input = $container.find('.finix-description-input');
        const $message = $container.find('.finix-description-message');
        const $saveBtn = $container.find('.finix-save-description-btn');
        const $cancelBtn = $container.find('.finix-cancel-description-btn');

        // Restore original value
        const originalValue = $display.text().trim();
        if (originalValue !== '—') {
            $input.val(originalValue);
        } else {
            $input.val('');
        }

        // Hide form, show display
        $editForm.hide();
        $display.show();
        $editBtn.show();

        // Clear message
        $message.removeClass('success error').text('');

        // Re-enable buttons
        $saveBtn.prop('disabled', false).text($saveBtn.data('original-text') || 'Save');
        $cancelBtn.prop('disabled', false);
    }

    // Store original button text on page load
    $('.finix-save-description-btn').each(function() {
        $(this).data('original-text', $(this).text());
    });
});
