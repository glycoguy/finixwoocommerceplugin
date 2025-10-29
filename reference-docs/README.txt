=== Finix WooCommerce Subscriptions ===
Contributors: Your Name
Tags: finix, payments, subscriptions, woocommerce, canadian, blocks
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.6.2
License: GPLv2 or later

Accept subscription payments through Finix for Canadian customers with WooCommerce Blocks support, client-side tokenization, and custom receipt descriptions.

== Description ==

**Version 1.6.2 - Complete WooCommerce Blocks Support!**

This plugin integrates Finix payment processing with WooCommerce and WooCommerce Subscriptions, featuring:

**NEW in 1.6.2:**
* ✅ Full WooCommerce Blocks checkout support (`/checkout-new`)
* ✅ Client-side tokenization with Finix.js (PCI compliant)
* ✅ Application ID configuration (Test & Live)
* ✅ Gateway ID changed to `finix_subscriptions` (no conflicts with other Finix gateways)
* ✅ AJAX handlers for token association
* ✅ Canadian bank account (EFT) support
* ✅ Enhanced error handling and validation

**Core Features:**
* Full subscription lifecycle management through Finix API
* Custom receipt descriptions - Customers can add notes that appear on monthly receipts
* Customer self-service portal - View, suspend, resume, and cancel subscriptions
* Automatic recurring billing with smart retries
* Webhook integration for real-time updates
* Support for both Credit Card and Bank Account payments
* Classic checkout compatibility maintained
* Receipt description editing in customer account

**Payment Methods:**
* Credit cards (Visa, Mastercard, Amex) via Finix.js tokenization
* Canadian bank accounts (EFT) with PAD agreement
* Secure, PCI-compliant tokenization

**Requirements:**
* WordPress 5.8+
* WooCommerce 6.0+
* WooCommerce Subscriptions plugin
* PHP 7.4+
* Active Finix merchant account with Application ID
* SSL certificate (HTTPS required)
* WooCommerce Blocks (for blocks checkout support)

== Installation ==

**Standard Installation:**

1. Upload the plugin files to `/wp-content/plugins/finix-woocommerce-subscriptions/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WooCommerce → Settings → Payments → Finix Payment Gateway (Subscriptions)
4. Configure your settings:
   - Enable the payment gateway
   - Enable Test Mode for testing
   - Enter your Test Application ID (get from Finix Dashboard)
   - Enter your Finix API credentials (sandbox for testing, live for production)
5. Configure webhook in your Finix Dashboard pointing to: https://yoursite.com/wc-api/finix_subscriptions_webhook/
6. Enter webhook username and password in plugin settings
7. Save changes and test with a subscription product

**Quick Installation:**

See QUICK-INSTALL-GUIDE.md for step-by-step 5-minute setup guide.

**Detailed Installation:**

See CRITICAL-FIX-v1.6.2.md for comprehensive installation and configuration guide.

== Frequently Asked Questions ==

= Does this work with the new WooCommerce blocks checkout? =

Yes! Version 1.6.2 fully supports both:
- Classic checkout (`/checkout`)
- WooCommerce blocks checkout (`/checkout-new`)

The payment form, receipt description field, and all features work on both checkout types.

= Can I show this gateway only for subscription products? =

Yes! Enable the "Subscriptions Only" setting. When enabled, the Finix gateway will only appear when the cart contains subscription products.

= How do I get my Application ID? =

Your Finix Application ID is available in your Finix Dashboard:
1. Log into Finix Dashboard
2. Go to API section
3. Find your Application ID
4. You'll have separate IDs for sandbox and live environments

= How do customers add custom receipt descriptions? =

When purchasing a subscription, customers will see an optional "Receipt Description" field in the payment form. Anything they enter will appear on their monthly Finix receipts and bank statements. They can also edit this later in their account.

= Can customers manage their own subscriptions? =

Yes! Customers can access My Account → Manage Subscriptions where they can:
- View all subscriptions
- See next payment dates
- View and edit custom descriptions
- Suspend/resume subscriptions
- Cancel subscriptions

All actions sync automatically with Finix.

= What about Canadian payment processing? =

This plugin is specifically built for Canadian merchants using Finix. Make sure to confirm with your Finix account manager that subscription features are enabled for Canadian accounts.

The plugin supports Canadian bank accounts (EFT) with proper institution and transit numbers.

= Is this PCI compliant? =

Yes! Version 1.6.2 uses Finix.js for client-side tokenization, meaning card data is tokenized in the browser before reaching your server. This significantly reduces PCI compliance scope.

= Will this conflict with other Finix plugins? =

No! Version 1.6.2 uses the gateway ID `finix_subscriptions` specifically to avoid conflicts with other Finix payment gateways. You can run this in parallel with other Finix integrations.

= What test cards can I use? =

For testing in sandbox mode, use these test cards:
- Visa: 4111 1111 1111 1111
- Mastercard: 5555 5555 5555 4444
- Amex: 3782 822463 10005

Use any future expiry date (e.g., 12/28) and any CVV (e.g., 123).

== Changelog ==

= 1.6.2 (2025-01-21) - CRITICAL FIX =
* **FIXED:** Added missing WooCommerce Blocks integration class
* **FIXED:** Finix.js now properly loads and initializes on blocks checkout
* **FIXED:** Added Application ID configuration fields (Test & Live)
* **CHANGED:** Gateway ID changed from `finix` to `finix_subscriptions` (prevents conflicts)
* **ADDED:** Complete blocks checkout payment processing
* **ADDED:** AJAX handlers for Finix.js token association
* **ADDED:** Canadian bank account (EFT) support with PAD agreement
* **ADDED:** API methods for token association and bank instruments
* **IMPROVED:** Error handling and initialization checks
* **IMPROVED:** Console logging for debugging
* **UPDATED:** Documentation and troubleshooting guides

= 1.6.1 (2025-01-20) =
* Attempted fix for blocks checkout (incomplete - missing integration class)
* Added payment processing logic to JavaScript
* Known issue: Finix.js not loading due to missing PHP integration

= 1.6.0 (2025-01-19) =
* Major update attempt with Finix.js
* Known issue: Blocks integration incomplete

= 1.3.4 (2025-10-15) =
* Fixed "Invalid country" error by converting 2-letter ISO country codes to 3-letter ISO codes for Finix API
* Added country code conversion map

= 1.3.3 (2025-10-15) =
* Added "Subscriptions Only" setting to show gateway only for subscription products
* Fixed receipt description field not appearing on /checkout-new (blocks checkout)
* Enhanced WooCommerce blocks checkout compatibility
* Improved JavaScript for dynamic field injection
* Better error handling for blocks checkout
* Updated documentation

= 1.3.2 (2025-10-10) =
* Fixed webhook authentication to use Basic Auth instead of HMAC
* Updated webhook event handling for Finix's actual event types
* Various bug fixes

= 1.0.0 (2025-10-05) =
* Initial release
* Full subscription support
* Custom receipt descriptions
* Customer self-service portal
* Webhook integration

== Upgrade Notice ==

= 1.6.2 =
CRITICAL UPDATE! Fixes blocks checkout payment processing. Complete WooCommerce Blocks support now working. Highly recommended for all users, especially those using blocks checkout. Gateway ID changed to avoid conflicts.

= 1.3.4 =
Important fix for country code validation. Resolves "Invalid country" errors with Finix API. Update recommended.

= 1.3.3 =
Important update! Fixes receipt description field on new checkout page and adds "Subscriptions Only" setting. Recommended for all users.

== Support ==

For issues, questions, or feature requests:
- Check the included TROUBLESHOOTING.md file
- Review the CRITICAL-FIX-v1.6.2.md for detailed information
- Check WordPress error logs at wp-content/debug.log
- Verify API credentials and settings in WooCommerce → Settings → Payments

== License ==

GPL v2 or later

== Screenshots ==

1. Payment gateway settings page with Application ID configuration
2. Blocks checkout with credit card form and receipt description
3. Classic checkout with payment options
4. Customer subscription management portal
5. Bank account payment form with PAD agreement
6. Receipt description field on checkout
