<?php
/**
 * PayPal Standard (one-time & subscriptions) with IPN.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Gateway_PayPal extends MGM_Gateway {

	public function __construct() {
		$this->id                 = 'paypal';
		$this->title              = __( 'PayPal', 'mg-membership' );
		$this->supports_recurring = true;
	}

	public function settings_fields() {
		$p = 'gateway_paypal_';
		return array_merge(
			parent::settings_fields(),
			array(
				$p . 'email'   => array( 'type' => 'text', 'label' => __( 'PayPal business email', 'mg-membership' ) ),
				$p . 'sandbox' => array(
					'type'  => 'checkbox',
					'label' => __( 'Sandbox mode', 'mg-membership' ),
					'desc'  => sprintf( __( 'IPN URL: %s', 'mg-membership' ), '<code>' . esc_html( $this->webhook_url() ) . '</code>' ),
				),
			)
		);
	}

	private function endpoint() {
		return $this->opt( 'sandbox' ) ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';
	}

	private function unit( $unit ) {
		$map = array(
			'day'   => 'D',
			'week'  => 'W',
			'month' => 'M',
			'year'  => 'Y',
		);
		return isset( $map[ $unit ] ) ? $map[ $unit ] : 'M';
	}

	public function process_payment( array $order ) {
		if ( ! $this->opt( 'email' ) ) {
			return new WP_Error( 'mgm_paypal', __( 'PayPal is not configured.', 'mg-membership' ) );
		}
		/** @var MGM_Membership $m */
		$m    = $order['membership'];
		$txn  = MGM_Transaction::get( $order['txn_id'] );
		$args = array(
			'business'      => $this->opt( 'email' ),
			'item_name'     => $m->title(),
			'item_number'   => $m->id,
			'currency_code' => mgm_currency(),
			'custom'        => $order['txn_id'] . '|' . $order['sub_id'],
			'invoice'       => $txn->trans_num,
			'notify_url'    => $this->webhook_url(),
			'return'        => MGM_Checkout::thankyou_url( $txn ),
			'cancel_return' => $m->url(),
			'no_shipping'   => 1,
			'charset'       => 'utf-8',
			'rm'            => 2,
		);
		if ( $m->is_recurring() ) {
			$args['cmd'] = '_xclick-subscriptions';
			$args['a3']  = number_format( $order['tax']['total'], 2, '.', '' );
			$args['p3']  = max( 1, (int) $m->get( 'period' ) );
			$args['t3']  = $this->unit( $m->get( 'period_unit' ) );
			$args['src'] = 1;
			$args['sra'] = 1;
			if ( (int) $m->get( 'limit_cycles' ) > 1 ) {
				$args['srt'] = (int) $m->get( 'limit_cycles' );
			}
			if ( $order['trial_days'] > 0 ) {
				$args['a1'] = number_format( $order['first']['total'], 2, '.', '' );
				$args['p1'] = (int) $order['trial_days'];
				$args['t1'] = 'D';
			}
		} else {
			$args['cmd']    = '_xclick';
			$args['amount'] = number_format( $order['tax']['total'], 2, '.', '' );
		}
		$args = apply_filters( 'mgm_paypal_args', $args, $order );
		return array( 'redirect' => add_query_arg( array_map( 'rawurlencode', $args ), $this->endpoint() ) );
	}

	private function verify_ipn( $raw ) {
		$res = wp_remote_post(
			$this->opt( 'sandbox' ) ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' : 'https://ipnpb.paypal.com/cgi-bin/webscr',
			array(
				'body'    => 'cmd=_notify-validate&' . $raw,
				'timeout' => 45,
				'headers' => array( 'User-Agent' => 'MG-Membership-IPN' ),
			)
		);
		return ! is_wp_error( $res ) && 'VERIFIED' === trim( wp_remote_retrieve_body( $res ) );
	}

	public function handle_webhook( $request ) {
		$raw = $request->get_body();
		if ( ! $this->verify_ipn( $raw ) ) {
			mgm_log( 'PayPal IPN not verified' );
			return new WP_REST_Response( array( 'error' => 'invalid' ), 400 );
		}
		parse_str( $raw, $ipn );
		$type   = $ipn['txn_type'] ?? '';
		$status = $ipn['payment_status'] ?? '';
		list( $txn_id, $sub_id ) = array_pad( array_map( 'intval', explode( '|', $ipn['custom'] ?? '' ) ), 2, 0 );
		mgm_log( 'PayPal IPN: ' . $type . ' ' . $status );

		if ( strtolower( $ipn['receiver_email'] ?? '' ) !== strtolower( $this->opt( 'email' ) ) && strtolower( $ipn['business'] ?? '' ) !== strtolower( $this->opt( 'email' ) ) ) {
			return new WP_REST_Response( array( 'error' => 'receiver' ), 400 );
		}
		// Payments must be in the shop currency (a verified IPN can still be a real payment in the wrong currency).
		$currency = strtoupper( $ipn['mc_currency'] ?? '' );
		if ( in_array( $type, array( 'web_accept', 'subscr_payment' ), true ) && $currency !== mgm_currency() ) {
			mgm_log( 'PayPal IPN currency mismatch: ' . $currency );
			return new WP_REST_Response( array( 'error' => 'currency' ), 400 );
		}

		// Refunds and chargebacks (any payment type): remove the access that payment granted.
		if ( in_array( $status, array( 'Refunded', 'Reversed' ), true ) && ! empty( $ipn['parent_txn_id'] ) ) {
			$txn = MGM_Transaction::get_by_trans_num( sanitize_text_field( $ipn['parent_txn_id'] ) );
			if ( $txn ) {
				MGM_Transaction::update( $txn->id, array( 'status' => 'refunded' ) );
			}
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		switch ( $type ) {
			case 'web_accept':
				if ( 'Completed' === $status ) {
					$txn = MGM_Transaction::get( $txn_id );
					if ( $txn && 'pending' === $txn->status && (float) $ipn['mc_gross'] + 0.005 >= (float) $txn->total ) {
						MGM_Transaction::complete( $txn_id, sanitize_text_field( $ipn['txn_id'] ) );
					}
				} elseif ( 'Refunded' === $status ) {
					$txn = MGM_Transaction::get_by_trans_num( sanitize_text_field( $ipn['parent_txn_id'] ?? '' ) );
					if ( $txn ) {
						MGM_Transaction::update( $txn->id, array( 'status' => 'refunded' ) );
					}
				}
				break;

			case 'subscr_signup':
				if ( $sub_id ) {
					$sub = MGM_Subscription::get( $sub_id );
					// The subscription the buyer set up at PayPal must charge at least our price, in our currency.
					$regular = MGM_Checkout::tax_for( $sub ? (float) $sub->price : 0 );
					if ( ! $sub || (float) ( $ipn['amount3'] ?? $ipn['mc_amount3'] ?? 0 ) + 0.005 < $regular['total'] || strtoupper( $ipn['mc_currency'] ?? mgm_currency() ) !== mgm_currency() ) {
						mgm_log( 'PayPal subscr_signup rejected: terms do not match subscription ' . $sub_id );
						break;
					}
					MGM_Subscription::update(
						$sub_id,
						array(
							'gateway_sub_id' => sanitize_text_field( $ipn['subscr_id'] ),
							'status'         => ( $sub && $sub->trial_days > 0 ) ? 'trialing' : 'active',
						)
					);
					// Free trial: no payment IPN is sent – grant trial access now.
					if ( $sub && $sub->trial_days > 0 && (float) $sub->trial_amount <= 0 ) {
						MGM_Transaction::complete( $txn_id, sanitize_text_field( $ipn['subscr_id'] ) . '-trial' );
					}
				}
				break;

			case 'subscr_payment':
				if ( 'Completed' !== $status ) {
					break;
				}
				$txn  = MGM_Transaction::get( $txn_id );
				$paid = (float) ( $ipn['mc_gross'] ?? 0 );
				if ( $txn && 'pending' === $txn->status ) {
					if ( $paid + 0.005 < (float) $txn->total ) {
						mgm_log( 'PayPal subscr_payment too low for txn ' . $txn_id );
						break;
					}
					MGM_Transaction::complete( $txn_id, sanitize_text_field( $ipn['txn_id'] ) );
					if ( $sub_id ) {
						MGM_Subscription::update(
							$sub_id,
							array(
								'gateway_sub_id' => sanitize_text_field( $ipn['subscr_id'] ),
								'status'         => 'active',
							)
						);
					}
				} else {
					$sub = MGM_Subscription::get_by_gateway_id( sanitize_text_field( $ipn['subscr_id'] ) );
					if ( $sub && $paid + 0.005 < MGM_Checkout::tax_for( (float) $sub->price )['total'] ) {
						mgm_log( 'PayPal renewal too low for subscription ' . $sub->id );
						break;
					}
					if ( $sub ) {
						MGM_Subscription::record_payment( $sub->id, (float) $ipn['mc_gross'], sanitize_text_field( $ipn['txn_id'] ) );
					}
				}
				break;

			case 'subscr_cancel':
			case 'subscr_eot':
				$sub = MGM_Subscription::get_by_gateway_id( sanitize_text_field( $ipn['subscr_id'] ?? '' ) );
				if ( $sub && ! in_array( $sub->status, array( 'cancelled', 'expired' ), true ) ) {
					MGM_Subscription::update(
						$sub->id,
						array(
							'status'       => 'subscr_eot' === $type ? 'expired' : 'cancelled',
							'cancelled_at' => mgm_now(),
						)
					);
				}
				break;

			case 'subscr_failed':
				$sub = MGM_Subscription::get_by_gateway_id( sanitize_text_field( $ipn['subscr_id'] ?? '' ) );
				if ( $sub ) {
					MGM_Subscription::update( $sub->id, array( 'status' => 'suspended' ) );
				}
				break;
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function cancel_subscription( $sub ) {
		// PayPal Standard does not allow API cancellation; the member must cancel in PayPal.
		// We stop access renewal on our side; PayPal will send subscr_cancel if done there.
		return true;
	}

	public function update_payment_url( $sub ) {
		return $this->opt( 'sandbox' ) ? 'https://www.sandbox.paypal.com/myaccount/autopay/' : 'https://www.paypal.com/myaccount/autopay/';
	}
}
