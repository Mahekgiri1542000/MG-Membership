<?php
/**
 * Offline / manual payments (bank transfer, cash, invoice).
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Gateway_Offline extends MGM_Gateway {

	public function __construct() {
		$this->id                 = 'offline';
		$this->title              = __( 'Offline Payment (Bank transfer / Cash)', 'mg-membership' );
		$this->supports_recurring = true;
	}

	public function settings_fields() {
		$p = 'gateway_offline_';
		return array_merge(
			parent::settings_fields(),
			array(
				$p . 'instructions'    => array( 'type' => 'textarea', 'label' => __( 'Payment instructions (shown on thank you page)', 'mg-membership' ) ),
				$p . 'grant_immediately' => array( 'type' => 'checkbox', 'label' => __( 'Grant access immediately (before payment is confirmed)', 'mg-membership' ) ),
			)
		);
	}

	public function process_payment( array $order ) {
		if ( $this->opt( 'grant_immediately' ) ) {
			MGM_Transaction::complete( $order['txn_id'] );
			if ( $order['sub_id'] ) {
				MGM_Subscription::update(
					$order['sub_id'],
					array(
						'status'          => $order['trial_days'] > 0 ? 'trialing' : 'active',
						'next_payment_at' => MGM_Transaction::get( $order['txn_id'] )->expires_at,
					)
				);
			}
		}
		return array();
	}
}
