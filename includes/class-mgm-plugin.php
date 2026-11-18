<?php
/**
 * Core plugin bootstrap.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

final class MGM_Plugin {

	/** @var MGM_Plugin */
	private static $instance;

	/** @var MGM_Gateway[] */
	public $gateways = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->includes();
		add_action( 'plugins_loaded', array( $this, 'init' ), 5 );
	}

	private function includes() {
		$inc = MGM_PATH . 'includes/';

		require_once $inc . 'functions.php';
		require_once $inc . 'class-mgm-install.php';
		require_once $inc . 'class-mgm-settings.php';
		require_once $inc . 'class-mgm-addons.php';
		require_once $inc . 'class-mgm-post-types.php';
		require_once $inc . 'class-mgm-membership.php';
		require_once $inc . 'class-mgm-subscription.php';
		require_once $inc . 'class-mgm-transaction.php';
		require_once $inc . 'class-mgm-member.php';
		require_once $inc . 'class-mgm-access.php';
		require_once $inc . 'class-mgm-emails.php';
		require_once $inc . 'class-mgm-checkout.php';
		require_once $inc . 'class-mgm-account.php';
		require_once $inc . 'class-mgm-shortcodes.php';
		require_once $inc . 'class-mgm-cron.php';
		require_once $inc . 'class-mgm-rest.php';
		require_once $inc . 'class-mgm-security.php';
		require_once $inc . 'class-mgm-privacy.php';
		require_once $inc . 'class-mgm-appearance.php';
		require_once $inc . 'class-mgm-custom-fields.php';
		require_once $inc . 'class-mgm-forms.php';

		require_once $inc . 'gateways/abstract-mgm-gateway.php';
		require_once $inc . 'gateways/class-mgm-gateway-offline.php';
		require_once $inc . 'gateways/class-mgm-gateway-stripe.php';
		require_once $inc . 'gateways/class-mgm-gateway-paypal.php';

		if ( is_admin() ) {
			require_once $inc . 'admin/class-mgm-admin.php';
			require_once $inc . 'admin/class-mgm-admin-dashboard.php';
			require_once $inc . 'admin/class-mgm-admin-metaboxes.php';
			require_once $inc . 'admin/class-mgm-admin-lists.php';
			require_once $inc . 'admin/class-mgm-demo.php';
		}
	}

	public function init() {
		load_plugin_textdomain( 'mg-membership', false, dirname( MGM_BASENAME ) . '/languages' );

		MGM_Install::maybe_upgrade();

		// Gateways.
		$this->gateways = apply_filters(
			'mgm_gateways',
			array(
				'offline' => new MGM_Gateway_Offline(),
				'stripe'  => new MGM_Gateway_Stripe(),
				'paypal'  => new MGM_Gateway_PayPal(),
			)
		);

		MGM_Post_Types::init();
		MGM_Access::init();
		MGM_Emails::init();
		MGM_Checkout::init();
		MGM_Account::init();
		MGM_Shortcodes::init();
		MGM_Cron::init();
		MGM_REST::init();
		MGM_Security_Core::init();
		MGM_Privacy::init();
		MGM_Appearance::init();
		MGM_Custom_Fields::init();
		MGM_Forms::init();

		// Add-ons (Courses, Events/Seminars, Coupons, Certificates, Reports, Page builders...).
		MGM_Addons::load_active();

		if ( is_admin() ) {
			MGM_Admin::init();
			MGM_Admin_Metaboxes::init();
			MGM_Admin_Lists::init();
			MGM_Demo::init();
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );

		do_action( 'mgm_loaded' );
	}

	public function frontend_assets() {
		wp_enqueue_style( 'mgm-frontend', MGM_URL . 'assets/css/frontend.css', array(), MGM_VERSION );
		wp_enqueue_script( 'mgm-frontend', MGM_URL . 'assets/js/frontend.js', array( 'jquery' ), MGM_VERSION, true );
		wp_localize_script(
			'mgm-frontend',
			'MGM',
			array(
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'rest'     => esc_url_raw( rest_url( 'mgm/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'currency' => mgm_currency_symbol(),
				'i18n'     => array(
					'invalidCoupon' => __( 'Invalid coupon code.', 'mg-membership' ),
					'applied'       => __( 'Coupon applied.', 'mg-membership' ),
					'processing'    => __( 'Processing…', 'mg-membership' ),
				),
			)
		);
	}

	/**
	 * @param string $id Gateway id.
	 * @return MGM_Gateway|null
	 */
	public function gateway( $id ) {
		return isset( $this->gateways[ $id ] ) ? $this->gateways[ $id ] : null;
	}

	/** @return MGM_Gateway[] */
	public function enabled_gateways() {
		return array_filter(
			$this->gateways,
			function ( $g ) {
				return $g->is_enabled();
			}
		);
	}
}
