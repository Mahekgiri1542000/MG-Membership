<?php
/**
 * Base payment gateway.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

abstract class MGM_Gateway {

	/** @var string */
	public $id = '';

	/** @var string */
	public $title = '';

	/** @var bool Supports automatic recurring billing. */
	public $supports_recurring = false;

	public function opt( $key, $default = '' ) {
		return mgm_option( 'gateway_' . $this->id . '_' . $key, $default );
	}

	public function is_enabled() {
		return '1' === (string) $this->opt( 'enabled' );
	}

	public function label() {
		return $this->opt( 'label', $this->title );
	}

	public function description() {
		return $this->opt( 'description', '' );
	}

	/**
	 * Settings fields (keys must be prefixed gateway_{id}_).
	 */
	public function settings_fields() {
		$p = 'gateway_' . $this->id . '_';
		return array(
			$p . 'heading'     => array( 'type' => 'heading', 'label' => $this->title ),
			$p . 'enabled'     => array( 'type' => 'checkbox', 'label' => sprintf( __( 'Enable %s', 'mg-membership' ), $this->title ) ),
			$p . 'label'       => array( 'type' => 'text', 'label' => __( 'Checkout label', 'mg-membership' ), 'placeholder' => $this->title ),
			$p . 'description' => array( 'type' => 'textarea', 'label' => __( 'Checkout description', 'mg-membership' ) ),
		);
	}

	public function webhook_url() {
		return rest_url( 'mgm/v1/webhook/' . $this->id );
	}

	/**
	 * Start a payment.
	 *
	 * @param array $order See MGM_Checkout::process().
	 * @return array|WP_Error ['redirect' => url] or [] when completed inline.
	 */
	abstract public function process_payment( array $order );

	/**
	 * Cancel a recurring subscription at the gateway.
	 *
	 * @return true|WP_Error
	 */
	public function cancel_subscription( $sub ) {
		return true;
	}

	/**
	 * Handle an incoming webhook/IPN.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function handle_webhook( $request ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Optional: URL where the member can update their payment method.
	 */
	public function update_payment_url( $sub ) {
		return '';
	}
}
