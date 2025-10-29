<?php

namespace FinixWC;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType as WooAbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry as WooPaymentMethodRegistry;
use Automattic\WooCommerce\Utilities\FeaturesUtil as WooUtilsFeaturesUtil;
use FinixWC\Admin\Admin;
use FinixWC\Finix\API;
use FinixWC\Gateways\PaymentMethods\BankMethod;
use FinixWC\Gateways\PaymentMethods\CardMethod;
use FinixWC\Gateways\PaymentMethods\ApplePay;
use FinixWC\Gateways\PaymentMethods\GooglePay;
use FinixWC\Gateways\ApplePayGateway;
use FinixWC\Gateways\BankGateway;
use FinixWC\Gateways\CardGateway;
use FinixWC\Gateways\GooglePayGateway;

/**
 * Main plugin class.
 */
class Plugin {

	/**
	 * The single instance of the class.
	 */
	private static $instance;

	/**
	 * Admin instance.
	 */
	public Admin $admin;

	/**
	 * Options instance.
	 */
	public Options $options;

	/**
	 * Payments instance.
	 */
	public Payments $payments;

	/**
	 * Interface to work with Finix API.
	 */
	public API $finix_api;

	/**
	 * Constructor.
	 */
	protected function __construct() {

		$this->admin     = new Admin();
		$this->options   = new Options();
		$this->payments  = new Payments();
		$this->finix_api = new API();
	}

	/**
	 * Main Extension Instance.
	 * Ensures only one instance of the extension is loaded or can be loaded.
	 */
	public static function instance(): Plugin {

		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialization logic.
	 */
	public function init(): void {

		if ( is_admin() ) {
			$this->admin->init();
		}
	}

	/**
	 * Hooks.
	 */
	public function hooks(): void {

		if ( is_admin() ) {
			$this->admin->hooks();
		}

		$this->payments->hooks();

		add_filter( 'script_loader_tag', [ $this, 'add_async_crossorigin_attributes' ], 10, 2 );

		// Gateways.
		add_filter( 'woocommerce_payment_gateways', [ $this, 'register_finix_gateways' ] );

		add_action( 'before_woocommerce_init', [ $this, 'declare_cart_checkout_blocks_compatibility' ] );

		add_action( 'woocommerce_blocks_loaded', [ $this, 'register_payment_method_types' ] );
	}

	/**
	 * Register Finix gateways.
	 *
	 * @param array $gateways Current list of WC gateways.
	 */
	public function register_finix_gateways( array $gateways ): array {

		$gateways[] = CardGateway::class;
		$gateways[] = BankGateway::class;
		$gateways[] = ApplePayGateway::class;
		$gateways[] = GooglePayGateway::class;

		return $gateways;
	}

	/**
	 * Custom function to declare compatibility with cart_checkout_blocks feature.
	 * TODO: check this.
	 */
	public function declare_cart_checkout_blocks_compatibility(): void {

		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			WooUtilsFeaturesUtil::declare_compatibility( 'cart_checkout_blocks', FINIXWC_PLUGIN_FILE, true );
			WooUtilsFeaturesUtil::declare_compatibility( 'custom_order_tables', FINIXWC_PLUGIN_FILE, true );
		}
	}

	/**
	 * Custom function to register a payment method type.
	 *
	 * @see https://developer.woocommerce.com/docs/cart-and-checkout-payment-method-integration-for-the-checkout-block/#4-server-side-integration
	 */
	public function register_payment_method_types(): void { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		if ( ! class_exists( WooAbstractPaymentMethodType::class ) ) {
			return;
		}

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( WooPaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new CardMethod() );
				$payment_method_registry->register( new BankMethod() );
				$payment_method_registry->register( new ApplePay() );
				$payment_method_registry->register( new GooglePay() );
			}
		);
	}

	/**
	 * Add async and cross-origin attributes.
	 *
	 * @param string $tag    The `<script>` tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 *
	 * @return string
	 */
	public function add_async_crossorigin_attributes( $tag, $handle ): string {

		// Modify only our own script.
		if ( $handle === 'finix-apple-pay' ) {
			return str_replace( ' src', ' async crossorigin="anonymous" src', $tag );
		}

		return $tag;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {

		wc_doing_it_wrong( __FUNCTION__, 'Cloning instances of this class is forbidden.', '1.1.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {

		wc_doing_it_wrong( __FUNCTION__, 'Unserializing instances of this class is forbidden.', '1.1.0' );
	}
}
