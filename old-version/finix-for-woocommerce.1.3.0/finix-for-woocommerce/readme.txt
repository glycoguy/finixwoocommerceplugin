=== Finix for WooCommerce ===
Contributors: finixpayments, slaFFik
Tags: finix, credit card, ach, apple pay, woocommerce
Requires at least: 6.7
Tested up to: 6.8
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 9.9
WC tested up to: 10.2
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Credit & Debit Cards, Bank/ACH, Apple Pay, and Google Pay with Finix. Fast, secure, and seamless payments to power your online store!

== Description ==

Power your WooCommerce store with a fast, flexible, and reliable payments experience using the Finix plugin. Whether you're launching a new shop or scaling an existing one, Finix makes it easy to accept payments and manage orders without the complexity.
Built with modern commerce in mind, this plugin supports multiple payment methods and includes everything you need for secure, seamless checkout experiences.

= Key Features =

* <strong>Flexible Payment Methods:</strong> Accept major credit and debit cards, Apple Pay, and bank transfers. Offer flexibility customers expect and reduce checkout friction.
* <strong>Transparent Pricing:</strong> Finix uses interchange-plus pricing for clear, detailed fee breakdowns, ideal for high-volume merchants.
* <strong>Apple Pay Integration:</strong> Enable Apple Pay on supported browsers like Safari and Chrome, with customizable button styles and types that blend seamlessly into your storefront.
* <strong>Customizable Checkout Display:</strong> Match your brand’s voice by tailoring the look and language of each payment method for a more intuitive customer experience.
* <strong>WooCommerce Blocks Checkout Compatible</strong> Fully supports WooCommerce’s new block-based checkout and the classic flow, keeping your store aligned with the latest updates.
* <strong>Automated Dispute & Bank Return Handling</strong> Reduce operational overhead with automatic order status updates triggered by webhook events.

= Sign Up for a Finix Account =

If you do not have a Finix account, visit our [website](https://finix.com?utm_campaign=WordPress&utm_source=FinixForWooCommerce&utm_medium=PluginRepository&utm_term=link&utm_content=homepage) to talk to our sales team or create a [sandbox account](https://finix.payments-dashboard.com/signup?utm_campaign=WordPress&utm_source=FinixForWooCommerce&utm_medium=PluginRepository&utm_term=link&utm_content=sandbox).

== Documentation ==

For step-by-step directions on how to enable Finix for WooCommerce, view our [guide](https://docs.finix.com/additional-resources/plugins/woocommerce-plugin?utm_campaign=WordPress&utm_source=FinixForWooCommerce&utm_medium=PluginRepository&utm_term=link&utm_content=wooguide).

== Frequently Asked Questions ==

= How do I integrate Finix with my WooCommerce store? =

To integrate Finix with your WooCommerce store, first, create a Finix account. Then, install and activate the Finix WooCommerce Payment Gateway plugin. Navigate to WooCommerce settings, select the Payments tab, and enable Finix as a payment method. Enter your Finix API credentials to complete the setup.

= Is there a Sandbox mode? =

You can enable a Sandbox mode to use test cards and bank accounts.

Create your [Finix Sandbox Account](https://finix.payments-dashboard.com/signup?utm_campaign=WordPress&utm_source=FinixForWooCommerce&utm_medium=PluginRepository&utm_term=link&utm_content=sandbox).

You can test your integration with different amounts and card numbers. [Read more](https://docs.finix.com/additional-resources/developers/implementation-and-testing/testing-your-integration?utm_campaign=WordPress&utm_source=FinixForWooCommerce&utm_medium=PluginRepository&utm_term=link&utm_content=testing)

= Are there any fees associated with using the Finix WooCommerce Payment Gateway? =

Finix does not charge any fees for using the plugin itself. However, standard transaction fees apply based on your Finix account and pricing plan. Please refer to your Finix account for specific fee details.

= How does the Finix plugin handle payment security? =

Finix utilizes advanced encryption and security protocols to ensure all transactions are secure.

= How can I issue refunds through the Finix plugin? =

You can issue refunds directly through the WooCommerce order management interface. Simply navigate to the order you wish to refund, click the "Refund" button and follow the prompts to process the refund.

Once the WooCommerce processes the refund, the plugin will notify Finix to perform the same action in the Finix system.

You can also initiate the refund procedure in the Finix Dashboard. Finix will notify WooCommerce about the refund status and the order in WooCommerce will be updated accordingly.

= Which external services are used in the plugin? =

The plugin connects to Finix API, Apple Pay, and Google Pay APIs to charge the customers of your WooCommerce shop.

Only the minimally required information is passed, like: order ID and amount, store name, customer billing information, as well as any API keys needed to perform the remote connection.

You can read more on [Finix Terms & Policies](https://finix.com/terms-and-policies?utm_campaign=WordPress&utm_source=FinixForWooCommerce&utm_medium=PluginRepository&utm_term=link&utm_content=terms), [Apple Pay Terms & Conditions](https://www.apple.com/legal/internet-services/apple-pay-wallet/us/) and [Apple Privacy Policy](https://www.apple.com/legal/privacy/en-ww/), [Google Pay Privacy, Terms & Policies](https://support.google.com/googlepay/answer/9039712?hl=en) how the data will be processed.

The domains we connect to or request information from are: finix.live-payments-api.com, finix.sandbox-payments-api.com, js.finix.com, applepay.cdn-apple.com, apple-pay-gateway-cert.apple.com, apple-pay-gateway.apple.com, pay.google.com.

= What should I do if I encounter issues with the Finix plugin? =

If you experience any problems, ensure that your Finix API credentials are correctly entered and that your Finix account is active.

For further assistance, contact [Finix Support](https://docs.finix.com/guides/getting-started/support-at-finix?utm_campaign=WordPress&utm_source=FinixForWooCommerce&utm_medium=PluginRepository&utm_term=link&utm_content=support).

= Is Finix for WooCommerce translation ready? =

Yes, Finix has full translation and localization support via the `finix-for-woocommerce` textdomain. If translations are available on translate.wordpress.org, then based on your site language, required translation files will be automatically downloaded and placed into the default WordPress languages directory.

== Installation ==

= Minimum Requirements =

* PHP version 7.4 or greater
* WordPress 6.7 or greater
* WooCommerce 9.9 or greater
* Finix account

= Automatic installation =

1. Install the plugin via Plugins -> New plugin. Search for "Finix" or "Finix for WooCommerce".
2. Activate the 'Finix for WooCommerce' plugin through the 'Plugins' menu in WordPress.
3. Set your Finix API credentials key at WooCommerce -> Settings -> Payments -> Finix Gateway -> Plugin Options (or use the *Settings* link in the Plugins overview).
4. You're done, the active payment methods should be visible on the checkout page of your shop.
5. You can configure payment methods in the Finix "Card and ACH" and "Apple Pay" settings pages.

= Manual installation =

1. Unpack the downloaded .zip file.
2. Upload the directory 'finix-for-woocommerce' to the `/wp-content/plugins/` directory.
3. Activate the 'Finix for WooCommerce' plugin through the 'Plugins' menu in the WordPress admin area.
3. Set your Finix API credentials key at WooCommerce -> Settings -> Payments -> Finix Gateway -> Plugin Options (or use the *Settings* link in the Plugins overview).
4. You're done, the active payment methods should be visible on the checkout page of your shop.
5. You can configure payment methods in the Finix settings pages.

Please contact [Finix Support](https://docs.finix.com/guides/getting-started/support-at-finix?utm_campaign=WordPress&utm_source=FinixForWooCommerce&utm_medium=PluginRepository&utm_term=link&utm_content=support). if you need help installing the Finix for WooCommerce plugin.

== Screenshots ==

1. Payment form
1. Card payment method settings
1. Bank payment method settings
1. Apple Pay payment method settings
1. Google Pay payment method settings
1. Plugin Options

== Changelog ==

= 1.3.0 =
* Added: Coupon codes that were applied to the order are now passed to the Finix API as transaction tags.
* Added: Display a lot more relevant order information in the Apple Pay popup on Apple devices, not just the total amount, but also all the products, discounts/tax/shipping if present.
* Changed: Much more robust system to manage tags for orders and buyers: validated and filterable.
* Changed: Plugin was tested to be fully compatible with WooCommerce v10.2.
* Changed: The minimum compatible and tested WordPress version is now 6.7 - to be aligned with WooCommerce strategy.
* Changed: Split a currently unified Card/Bank gateway into separate Finix Cards and Finix Bank/ACH gateways for easier control and configuration.
* Fixed: Finix form styles should now blend even better with your current theme.
* Fixed: Sometimes the payment forms by Finix were not loading properly on the checkout page. 

= 1.2.0 =
* Added: New Google Pay payment method, with custom title and description.
* Added: New payment currency is now supported - Canadian Dollar (CAD). Make sure to configure it in plugin settings.
* Added: Google Pay button can be configured how it will look on the checkout pages (button type, color, and corner radius).
* Added: When creating a new Buyer via Finix API, the plugin now passes the WordPress user ID to the Buyer entity. For logged out or guest users, that value is 0.
* Added: For the Card/ACH payment form rendered by Finix API, you can now redefine labels, placeholders, displayed and required fields, and error messages (among other things) using a filter.
* Changed: The minimum supported WooCommerce version is raised to v9.7.
* Changed: Tested compatibility with the latest WooCommerce version to v9.9.
* Changed: The minimum supported WordPress version is raised to v6.6.
* Changed: Update the Finix JS library to v1.3.2 to use its latest features.
* Fixed: In some cases, the Card/ACH payment form was rerendering unnecessarily.
* Fixed: Payments via Apple Pay were sometimes not going through due to the way Apple Pay sessions were created.
* Fixed: New filter `finixwc_cardbankgateway_finix_form_params` to modify the look of the Finix payment form for Card and Bank payment methods. You can show/hide various fields, change labels and placeholders.

= 1.1.1 =
* Added: New WooCommerce-specific plugin headers which provide additional information to WooCommerce for plugin compatibility.
* Changed: Update the Finix JS library to v1.3.1 to use its latest features (see below).
* Changed: "Bank Code" in the payment form was renamed to "Routing Number" to reflect more common U.S. terminology.
* Changed: Improved Routing Number validation - ensures 9-digit numeric input only.
* Fixed: There was an incorrect translation domain used in the Apple Pay block preventing the string from properly being translated.
* Fixed: Incorrect value of the shipping country was used instead of the shipping state when validating the address when using Apple Pay.

= 1.1.0 =
* Added: Credit Card payment method, with custom title and description.
* Added: ACH or Bank payment method, with custom title and description.
* Added: Apple Pay payment method - Safari, Chrome and other browsers are supported, with custom title and description.
* Added: Apple Pay button can be configured how it will look on the checkout pages (button type and style).
* Added: Live and Sandbox accounts support for all payment methods.
* Added: Webhooks processing support for payments, refunds, disputes and ACH returns.
* Added: Disputes support - automatically change the order status when receiving a dispute.
* Added: ACH Returns support - automatically change the order status when receiving a notification about ACH Return.
