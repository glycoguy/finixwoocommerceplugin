<?php

namespace FinixWC\Gateways;

use Automattic\WooCommerce\Enums\OrderInternalStatus;
use FinixWC\Admin\Admin;
use FinixWC\Helpers\Assets;
use FinixWC\Helpers\URL;
use WC_HTTPS;
use WC_Logger;

/**
 * Abstract Finix Gateway containing common methods.
 */
abstract class FinixGateway extends \WC_Payment_Gateway {

	public const SLUG = '';

	public string $icon_checkout = '';

	protected string $webhook_user = 'wc_finix_webhook_user';

	protected string $webhook_password;
	public bool $is_sandbox_mode;
	protected string $password;
	protected string $username;
	public string $merchant_id;
	public string $merchant_id_cad;
	public string $application_id;
	protected string $custom_source_tag;

	private WC_Logger $logger;

	public array $plugin_options;

	/**
	 * Common constructor.
	 */
	public function __construct() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		$this->has_fields = true;
		$this->supports   = [ 'products', 'refunds' ];

		$this->init();

		$this->is_sandbox_mode   = ( $this->get_option( 'testmode' ) === 'sandbox' );
		$this->username          = $this->is_sandbox_mode ? $this->get_option( 'test_username' ) : $this->get_option( 'live_username' );
		$this->password          = $this->is_sandbox_mode ? $this->get_option( 'test_password' ) : $this->get_option( 'live_password' );
		$this->merchant_id       = $this->is_sandbox_mode ? $this->get_option( 'test_merchant_id' ) : $this->get_option( 'live_merchant_id' );
		$this->merchant_id_cad   = $this->is_sandbox_mode ? $this->get_option( 'test_merchant_id_cad' ) : $this->get_option( 'live_merchant_id_cad' );
		$this->application_id    = $this->is_sandbox_mode ? $this->get_option( 'test_application_id' ) : $this->get_option( 'live_application_id' );
		$this->custom_source_tag = $this->get_option( 'custom_source_tag' );
	}

	/**
	 * Initialize the gateway settings.
	 * Should be called before any $this->get_option() calls.
	 */
	protected function init(): void {

		$this->init_form_fields();
		$this->init_settings();
	}

	/**
	 * Initialize settings form fields.
	 */
	public function init_form_fields(): void {

		$environment = [
			'environment_settings' => [
				'title'       => __( 'Environment Settings', 'finix-for-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'testmode'             => [
				'title'       => __( 'Environment', 'finix-for-woocommerce' ),
				'label'       => __( 'Environment', 'finix-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Place the payment gateway in sandbox mode using test API keys.', 'finix-for-woocommerce' ),
				'options'     => [
					'sandbox' => __( 'Sandbox', 'finix-for-woocommerce' ),
					'live'    => __( 'Live', 'finix-for-woocommerce' ),
				],
				'default'     => 'sandbox',
				'desc_tip'    => true,
			],
			'custom_source_tag'    => [
				'title'       => __( 'Custom Source Tag', 'finix-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This tag will be used to identify the source of the payment. It will be used to tag the payment details send to Finix.', 'finix-for-woocommerce' ),
				'default'     => 'woocommerce',
				'desc_tip'    => true,
			],
		];

		$sandbox = [
			'sandbox_details'      => [
				'title'       => __( 'Sandbox Details', 'finix-for-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'test_application_id'  => [
				'title'       => __( 'Sandbox Application ID', 'finix-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This is the application ID provided on Finix dashboard -> developer menu by selecting sandbox environment', 'finix-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'test_username'        => [
				'title' => __( 'Sandbox Username', 'finix-for-woocommerce' ),
				'type'  => 'text',
			],
			'test_password'        => [
				'title' => __( 'Sandbox Password', 'finix-for-woocommerce' ),
				'type'  => 'password',
			],
			'test_merchant_id'     => [
				'title'       => __( 'Sandbox Merchant ID (USD)', 'finix-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This is the USD merchant ID that you can get on the Finix Merchant Accounts page', 'finix-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'test_merchant_id_cad' => [
				'title'       => __( 'Sandbox Merchant ID (CAD)', 'finix-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This is the CAD merchant ID that you can get on the Finix Merchant Accounts page', 'finix-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			],
		];

		$live = [
			'live_details'         => [
				'title'       => __( 'Production Details', 'finix-for-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'live_application_id'  => [
				'title'       => __( 'Live Application ID', 'finix-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This is the application ID provided on Finix dashboard -> developer menu by selecting live environment', 'finix-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'live_username'        => [
				'title' => __( 'Live Username', 'finix-for-woocommerce' ),
				'type'  => 'text',
			],
			'live_password'        => [
				'title' => __( 'Live Password', 'finix-for-woocommerce' ),
				'type'  => 'password',
			],
			'live_merchant_id'     => [
				'title'       => __( 'Live Merchant ID (USD)', 'finix-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This is the USD merchant ID that you can get on the Finix Merchant Accounts page', 'finix-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'live_merchant_id_cad' => [
				'title'       => __( 'Live Merchant ID (CAD)', 'finix-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This is the CAD merchant ID that you can get on the Finix Merchant Accounts page', 'finix-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			],
		];

		$advanced = [
			'advanced_settings'      => [
				'title'       => __( 'Advanced Settings', 'finix-for-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'manage_disputes'        => [
				'title'       => __( 'Dispute Created', 'finix-for-woocommerce' ),
				'label'       => __( 'Dispute Created', 'finix-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, the plugin will listen to the dispute.created webhook event and update the order state if a dispute is created.', 'finix-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			],
			'disputes_order_state'   => [
				'title'       => __( 'Disputes Order State', 'finix-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose an order status when a payment dispute is received.', 'finix-for-woocommerce' ),
				'options'     => wc_get_order_statuses(),
				'default'     => OrderInternalStatus::ON_HOLD,
				'desc_tip'    => true,
			],
			'manage_ach_returns'     => [
				'title'       => __( 'ACH Return Created', 'finix-for-woocommerce' ),
				'label'       => __( 'ACH Return Created', 'finix-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, the plugin will listen to ACH returns and update the order state if ACH return is created.', 'finix-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			],
			'ach_return_order_state' => [
				'title'       => __( 'ACH Return Order State', 'finix-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose an order status when an ACH Return is received.', 'finix-for-woocommerce' ),
				'options'     => wc_get_order_statuses(),
				'default'     => OrderInternalStatus::FAILED,
				'desc_tip'    => true,
			],
		];

		$this->form_fields = array_merge( $environment, $sandbox, $live, $advanced );
	}

	/**
	 * Extend WP & WC with custom hooks.
	 */
	public function hooks(): void {

		add_action( 'woocommerce_api_' . $this->id, [ $this, 'webhook' ] );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ], 20 );
	}

	/**
	 * Enqueue the plugin admin area styles.
	 */
	protected function enqueue_admin_styles() {

		wp_enqueue_style(
			'finix-admin-styles',
			Assets::url( 'css/finix-admin.css' ),
			[],
			Assets::ver()
		);
	}

	/**
	 * Admin options page.
	 */
	public function admin_options(): void { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['section'] ) && in_array( sanitize_key( $_GET['section'] ), Admin::SECTIONS, true ) ) {
			$this->enqueue_admin_styles();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_options_page = (bool) absint( $_GET['options'] ?? 0 );

		if ( $is_options_page ) {
			$current_section = 'finix_gateway_options';
		} else {
			$current_section = $this->id;
		}

		$subpages = [
			'finix_gateway_options' => __( 'Plugin Options', 'finix-for-woocommerce' ),
			CardGateway::SLUG       => __( 'Cards', 'finix-for-woocommerce' ),
			BankGateway::SLUG       => __( 'Bank/ACH', 'finix-for-woocommerce' ),
			ApplePayGateway::SLUG   => __( 'Apple Pay', 'finix-for-woocommerce' ),
			GooglePayGateway::SLUG  => __( 'Google Pay', 'finix-for-woocommerce' ),
		];
		?>

		<?php finixwc()->admin->maybe_notify_about_unsupported_currency(); ?>

		<div class="global-container">

			<div class="header-container">
				<div class="header-content">
					<a target="_blank" class="logo"
						href="<?php echo esc_url( URL::add_utm( 'https://finix.com', 'plugin_admin', 'logo' ) ); ?>"
						title="<?php esc_attr_e( 'Finix Payments', 'finix-for-woocommerce' ); ?>">
						<img width="200" height="61" alt="<?php esc_attr_e( 'Finix Payments', 'finix-for-woocommerce' ); ?>"
							src="<?php echo esc_url( Assets::url( 'images/finix-logo.svg', false ) ); ?>" />
					</a>
				</div>
			</div>

			<div class="finix-nav-container">
				<div class="finix-nav-content">
					<?php foreach ( $subpages as $id => $tab ) : ?>
						<?php if ( $id === 'finix_gateway_options' ) : ?>
							<a class="nav-tab <?php echo ( $current_section === $id ? 'nav-tab-active' : '' ); ?>"
								href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=finix_gateway&options=1' ) ); ?>">
								<?php echo esc_html( $tab ); ?>
							</a>
						<?php else : ?>
							<a class="nav-tab <?php echo ( $current_section === $id ? 'nav-tab-active' : '' ); ?>"
								href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $id ) ); ?>">
								<?php echo esc_html( $tab ); ?>
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="clear"></div>

			<?php if ( $is_options_page ) : ?>
				<?php $this->admin_options_plugin(); ?>
			<?php else : ?>
				<?php $this->admin_options_gateway(); ?>
			<?php endif; ?>

		</div>

		<?php
	}

	/**
	 * Plugin options page.
	 */
	protected function admin_options_plugin(): void {
		?>

		<div class="webhook-container">
			<div class="webhook-content">
				<h3><?php esc_html_e( 'Webhook Configuration', 'finix-for-woocommerce' ); ?></h3>
				<p>
					<strong>
						<?php
						printf(
							wp_kses( /* translators: %s - Finix Dashboard URL. */
								__( 'To enable webhook, please add the following details to your <a href="%s" target="_blank">Finix Dashboard</a>:', 'finix-for-woocommerce' ),
								[
									'a' => [
										'href'   => true,
										'target' => true,
									],
								]
							),
							'https://finix.payments-dashboard.com/Dashboard'
						);
						?>
					</strong>
				</p>

				<table>
					<tr>
						<td><?php esc_html_e( 'URL:', 'finix-for-woocommerce' ); ?></td>
						<td><code><?php echo esc_url( WC()->api_request_url( CardGateway::SLUG ) ); ?></code></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Authentication Type:', 'finix-for-woocommerce' ); ?></td>
						<td><code><?php esc_html_e( 'Basic', 'finix-for-woocommerce' ); ?></code></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Username:', 'finix-for-woocommerce' ); ?></td>
						<td><code><?php echo esc_html( $this->webhook_user ); ?></code></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Password:', 'finix-for-woocommerce' ); ?></td>
						<td><code><?php echo esc_html( $this->webhook_password ); ?></code></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Required Events:', 'finix-for-woocommerce' ); ?></td>
						<td><code>Dispute: Created, Updated</code>, <code>Transfer: Created, Updated</code></td>
					</tr>
				</table>
			</div>
		</div>

		<div class="settings-container">
			<div class="settings-content">
				<table class="form-table">
					<?php $this->generate_settings_html(); ?>
				</table>
			</div>
		</div>

		<?php
	}

	/**
	 * Set plugin options. Used for child classes to set the plugin options.
	 */
	public function set_plugin_options(): void { // phpcs:ignore Generic.Metrics.CyclomaticComplexity

		$this->plugin_options = get_option( 'woocommerce_' . CardGateway::SLUG . '_settings', [] );

		$this->is_sandbox_mode = ! isset( $this->plugin_options['testmode'] ) || $this->plugin_options['testmode'] === 'sandbox';

		$test_username  = $this->plugin_options['test_username'] ?? '';
		$live_username  = $this->plugin_options['live_username'] ?? '';
		$this->username = $this->is_sandbox_mode ? $test_username : $live_username;

		$test_password  = $this->plugin_options['test_password'] ?? '';
		$live_password  = $this->plugin_options['live_password'] ?? '';
		$this->password = $this->is_sandbox_mode ? $test_password : $live_password;

		$test_merchant_id  = $this->plugin_options['test_merchant_id'] ?? '';
		$live_merchant_id  = $this->plugin_options['live_merchant_id'] ?? '';
		$this->merchant_id = $this->is_sandbox_mode ? $test_merchant_id : $live_merchant_id;

		$test_application_id  = $this->plugin_options['test_application_id'] ?? '';
		$live_application_id  = $this->plugin_options['live_application_id'] ?? '';
		$this->application_id = $this->is_sandbox_mode ? $test_application_id : $live_application_id;

		$this->custom_source_tag = $this->plugin_options['custom_source_tag'] ?? 'woocommerce';
	}

	/**
	 * Return the gateway's icon used specifically
	 * on /checkout/ pages and with shortcode.
	 */
	public function get_icon(): string {

		if ( empty( $this->icon_checkout ) || ! is_checkout() ) {
			return parent::get_icon();
		}

		// phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
		$icon = '<img src="' . esc_url( WC_HTTPS::force_https_url( $this->icon_checkout ) ) . '" alt="' . esc_attr( $this->get_title() ) . '" />';

		/**
		 * Filter the gateway icon.
		 *
		 * @since 1.5.8
		 *
		 * @param string $icon Gateway icon.
		 * @param string $id   Gateway ID.
		 *
		 * @return string
		 */
		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'. Possible values: emergency|alert|critical|error|warning|notice|info|debug.
	 * @param array  $context Optional. Additional information for log handlers.
	 */
	public function log( string $message, string $level = 'info', array $context = [] ): void {

		if ( empty( $this->logger ) ) {
			$this->logger = wc_get_logger();
		}

		$this->logger->log(
			$level,
			$message,
			array_merge(
				$context,
				[ 'source' => $this->id ]
			)
		);
	}

	/**
	 * Gateway-specific options page.
	 */
	abstract protected function admin_options_gateway();

	/**
	 * Gateway-specific webhook catcher.
	 */
	abstract protected function webhook();
}
