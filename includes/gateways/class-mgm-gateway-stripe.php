<?php
/**
 * Stripe – hosted Stripe Checkout (cards, Apple Pay, Google Pay, Link, SEPA…) + webhooks.
 * Uses the Stripe REST API directly; no SDK required.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Gateway_Stripe extends MGM_Gateway {

	const API = 'https://api.stripe.com/v1/';

	public function __construct() {
		$this->id                 = 'stripe';
		$this->title              = __( 'Stripe (Credit card, Apple Pay, Google Pay)', 'mg-membership' );
		$this->supports_recurring = true;
		add_action( 'template_redirect', array( $this, 'handle_return' ), 2 );
	}

	public function settings_fields() {
		$p = 'gateway_stripe_';
		return array_merge(
			parent::settings_fields(),
			array(
				$p . 'test_mode'      => array( 'type' => 'checkbox', 'label' => __( 'Test mode', 'mg-membership' ) ),
				$p . 'test_secret'    => array( 'type' => 'password', 'label' => __( 'Test secret key (sk_test_…)', 'mg-membership' ) ),
				$p . 'live_secret'    => array( 'type' => 'password', 'label' => __( 'Live secret key (sk_live_…)', 'mg-membership' ) ),
				$p . 'webhook_secret' => array(
					'type'  => 'password',
					'label' => __( 'Webhook signing secret (whsec_…)', 'mg-membership' ),
					'desc'  => sprintf(
						/* translators: %s webhook URL */
						__( 'Webhook URL: %s — events: checkout.session.completed, invoice.paid, invoice.payment_failed, customer.subscription.deleted, customer.subscription.updated, charge.refunded', 'mg-membership' ),
						'<code>' . esc_html( $this->webhook_url() ) . '</code>'
					),
				),
			)
		);
	}

	private function secret() {
		return $this->opt( 'test_mode' ) ? $this->opt( 'test_secret' ) : $this->opt( 'live_secret' );
	}

	/**
	 * Call the Stripe API.
	 *
	 * @return array|WP_Error
	 */
	public function request( $endpoint, array $body = array(), $method = 'POST' ) {
		$args = array(
			'method'  => $method,
			'timeout' => 45,
			'headers' => array(
				'Authorization'  => 'Bearer ' . $this->secret(),
				'Stripe-Version' => '2024-06-20',
			),
		);
		if ( $body ) {
			$args['body'] = $body;
		}
		$res = wp_remote_request( self::API . ltrim( $endpoint, '/' ), $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( wp_remote_retrieve_response_code( $res ) >= 400 ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Stripe error.', 'mg-membership' );
			mgm_log( 'Stripe error: ' . $msg );
			return new WP_Error( 'mgm_stripe', $msg );
		}
		return is_array( $data ) ? $data : array();
	}

	private function customer_id( $user_id ) {
		$key = $this->opt( 'test_mode' ) ? '_mgm_stripe_customer_test' : '_mgm_stripe_customer';
		$cid = get_user_meta( $user_id, $key, true );
		if ( $cid ) {
			return $cid;
		}
		$user = get_userdata( $user_id );
		$res  = $this->request(
			'customers',
			array(
				'email'              => $user->user_email,
				'name'               => trim( $user->first_name . ' ' . $user->last_name ) ? trim( $user->first_name . ' ' . $user->last_name ) : $user->display_name,
				'metadata[user_id]'  => $user_id,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		update_user_meta( $user_id, $key, $res['id'] );
		return $res['id'];
	}

	public function process_payment( array $order ) {
		if ( ! $this->secret() ) {
			return new WP_Error( 'mgm_stripe', __( 'Stripe is not configured.', 'mg-membership' ) );
		}
		/** @var MGM_Membership $m */
		$m        = $order['membership'];
		$customer = $this->customer_id( $order['user_id'] );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}
		$txn     = MGM_Transaction::get( $order['txn_id'] );
		$success = add_query_arg(
			array(
				'mgm_stripe_return' => 1,
				'mgm_txn'           => rawurlencode( $txn->trans_num ),
			),
			home_url( '/' )
		) . '&session_id={CHECKOUT_SESSION_ID}';

		$body = array(
			'customer'                                   => $customer,
			'success_url'                                => $success,
			'cancel_url'                                 => $m->url(),
			'client_reference_id'                        => $order['txn_id'],
			'metadata[mgm_txn_id]'                       => $order['txn_id'],
			'metadata[mgm_sub_id]'                       => $order['sub_id'],
			'line_items[0][quantity]'                    => 1,
			'line_items[0][price_data][currency]'        => strtolower( mgm_currency() ),
			'line_items[0][price_data][product_data][name]' => $m->title(),
			'line_items[0][price_data][unit_amount]'     => mgm_minor_units( $order['tax']['total'] ),
		);

		if ( $m->is_recurring() ) {
			$body['mode']                                             = 'subscription';
			$body['line_items[0][price_data][recurring][interval]']   = $m->get( 'period_unit' );
			$body['line_items[0][price_data][recurring][interval_count]'] = max( 1, (int) $m->get( 'period' ) );
			$body['subscription_data[metadata][mgm_sub_id]']          = $order['sub_id'];
			$body['subscription_data[metadata][mgm_txn_id]']          = $order['txn_id'];
			if ( $order['trial_days'] > 0 ) {
				$body['subscription_data[trial_period_days]'] = (int) $order['trial_days'];
				if ( $order['trial_amount'] > 0 ) {
					// Paid trial: add one-off line item charged on the first invoice.
					$body['subscription_data[add_invoice_items][0][price_data][currency]']     = strtolower( mgm_currency() );
					$body['subscription_data[add_invoice_items][0][price_data][product]']      = $this->product_id( $m );
					$body['subscription_data[add_invoice_items][0][price_data][unit_amount]']  = mgm_minor_units( $order['first']['total'] );
				}
			}
		} else {
			$body['mode']                                    = 'payment';
			$body['payment_intent_data[metadata][mgm_txn_id]'] = $order['txn_id'];
		}

		$body    = apply_filters( 'mgm_stripe_checkout_args', $body, $order );
		$session = $this->request( 'checkout/sessions', $body );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		return array( 'redirect' => $session['url'] );
	}

	private function product_id( MGM_Membership $m ) {
		$key = $this->opt( 'test_mode' ) ? '_mgm_stripe_product_test' : '_mgm_stripe_product';
		$pid = get_post_meta( $m->id, $key, true );
		if ( $pid ) {
			return $pid;
		}
		$res = $this->request( 'products', array( 'name' => $m->title() ) );
		if ( is_wp_error( $res ) ) {
			return '';
		}
		update_post_meta( $m->id, $key, $res['id'] );
		return $res['id'];
	}

	/**
	 * Member returns from Stripe Checkout – verify session and complete immediately.
	 */
	public function handle_return() {
		if ( empty( $_GET['mgm_stripe_return'] ) || empty( $_GET['session_id'] ) ) { // phpcs:ignore
			return;
		}
		$session_id = sanitize_text_field( wp_unslash( $_GET['session_id'] ) ); // phpcs:ignore
		$session    = $this->request( 'checkout/sessions/' . rawurlencode( $session_id ), array(), 'GET' );
		$txn        = null;
		if ( ! is_wp_error( $session ) ) {
			$this->complete_session( $session );
			$txn = MGM_Transaction::get( (int) $session['metadata']['mgm_txn_id'] );
		}
		if ( ! $txn ) {
			$txn = MGM_Transaction::get_by_trans_num( sanitize_text_field( wp_unslash( $_GET['mgm_txn'] ?? '' ) ) ); // phpcs:ignore
		}
		if ( $txn ) {
			wp_safe_redirect( MGM_Checkout::thankyou_url( $txn ) );
			exit;
		}
	}

	private function complete_session( array $session ) {
		$txn_id = isset( $session['metadata']['mgm_txn_id'] ) ? (int) $session['metadata']['mgm_txn_id'] : 0;
		$sub_id = isset( $session['metadata']['mgm_sub_id'] ) ? (int) $session['metadata']['mgm_sub_id'] : 0;
		$txn    = MGM_Transaction::get( $txn_id );
		if ( ! $txn || 'complete' === $txn->status ) {
			return;
		}
		$paid = in_array( $session['payment_status'] ?? '', array( 'paid', 'no_payment_required' ), true );
		if ( ! $paid || 'complete' !== ( $session['status'] ?? '' ) ) {
			return;
		}
		// Defence in depth: the amount & currency Stripe collected must match what we asked for.
		if ( isset( $session['amount_total'] ) && 'paid' === $session['payment_status'] && (int) $session['amount_total'] < mgm_minor_units( $txn->total ) ) {
			mgm_log( 'Stripe session amount too low for txn ' . $txn->id );
			return;
		}
		if ( ! empty( $session['currency'] ) && strtolower( $session['currency'] ) !== strtolower( mgm_currency() ) ) {
			mgm_log( 'Stripe session currency mismatch for txn ' . $txn->id );
			return;
		}
		$ref = ! empty( $session['payment_intent'] ) ? $session['payment_intent'] : ( ! empty( $session['invoice'] ) ? $session['invoice'] : $session['id'] );
		if ( is_array( $ref ) ) {
			$ref = $ref['id'];
		}
		if ( $sub_id && ! empty( $session['subscription'] ) ) {
			$stripe_sub = is_array( $session['subscription'] ) ? $session['subscription']['id'] : $session['subscription'];
			$sub        = MGM_Subscription::get( $sub_id );
			MGM_Subscription::update(
				$sub_id,
				array(
					'gateway_sub_id' => $stripe_sub,
					'status'         => ( $sub && $sub->trial_days > 0 ) ? 'trialing' : 'active',
				)
			);
		}
		MGM_Transaction::complete( $txn_id, $ref );
	}

	private function verify_signature( $payload, $header ) {
		$secret = $this->opt( 'webhook_secret' );
		if ( ! $secret ) {
			// Never trust unsigned events: anyone could forge "payment succeeded".
			// Without a secret, one-time payments still complete through the verified return URL.
			mgm_log( 'Stripe webhook rejected: no webhook signing secret configured.' );
			return false;
		}
		$parts = array();
		foreach ( explode( ',', (string) $header ) as $item ) {
			$kv = explode( '=', $item, 2 );
			if ( 2 === count( $kv ) ) {
				$parts[ trim( $kv[0] ) ][] = trim( $kv[1] );
			}
		}
		if ( empty( $parts['t'][0] ) || empty( $parts['v1'] ) ) {
			return false;
		}
		if ( abs( time() - (int) $parts['t'][0] ) > 600 ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $parts['t'][0] . '.' . $payload, $secret );
		foreach ( $parts['v1'] as $sig ) {
			if ( hash_equals( $expected, $sig ) ) {
				return true;
			}
		}
		return false;
	}

	public function handle_webhook( $request ) {
		$payload = $request->get_body();
		if ( ! $this->verify_signature( $payload, $request->get_header( 'stripe-signature' ) ) ) {
			return new WP_REST_Response( array( 'error' => 'bad signature' ), 400 );
		}
		$event = json_decode( $payload, true );
		$obj   = $event['data']['object'] ?? array();
		mgm_log( 'Stripe webhook: ' . ( $event['type'] ?? '' ) );

		switch ( $event['type'] ?? '' ) {
			case 'checkout.session.completed':
			case 'checkout.session.async_payment_succeeded':
				$this->complete_session( $obj );
				break;

			case 'invoice.paid':
				$stripe_sub = $obj['subscription'] ?? '';
				if ( ! $stripe_sub ) {
					break;
				}
				$sub = MGM_Subscription::get_by_gateway_id( $stripe_sub );
				if ( ! $sub ) {
					break;
				}
				// First invoice is handled by checkout.session.completed.
				if ( 'subscription_create' === ( $obj['billing_reason'] ?? '' ) ) {
					break;
				}
				$period_end = $obj['lines']['data'][0]['period']['end'] ?? null;
				MGM_Subscription::record_payment( $sub->id, ( (int) $obj['amount_paid'] ) / 100, $obj['id'], $period_end );
				break;

			case 'invoice.payment_failed':
				$sub = MGM_Subscription::get_by_gateway_id( $obj['subscription'] ?? '' );
				if ( $sub ) {
					MGM_Subscription::update( $sub->id, array( 'status' => 'suspended' ) );
				}
				break;

			case 'customer.subscription.deleted':
				$sub = MGM_Subscription::get_by_gateway_id( $obj['id'] ?? '' );
				if ( $sub && 'cancelled' !== $sub->status ) {
					MGM_Subscription::update(
						$sub->id,
						array(
							'status'       => 'cancelled',
							'cancelled_at' => mgm_now(),
						)
					);
				}
				break;

			case 'customer.subscription.updated':
				$sub = MGM_Subscription::get_by_gateway_id( $obj['id'] ?? '' );
				if ( $sub && 'active' === ( $obj['status'] ?? '' ) && 'suspended' === $sub->status ) {
					MGM_Subscription::update( $sub->id, array( 'status' => 'active' ) );
				}
				break;

			case 'charge.refunded':
			case 'charge.dispute.created':
				$charge = 'charge.dispute.created' === ( $event['type'] ?? '' ) ? array( 'payment_intent' => $obj['payment_intent'] ?? '', 'invoice' => $obj['invoice'] ?? '', 'refunded' => true ) : $obj;
				$txn    = null;
				// One-time payments are stored under the PaymentIntent, subscription payments under the invoice.
				foreach ( array( $charge['payment_intent'] ?? '', $charge['invoice'] ?? '' ) as $ref ) {
					if ( ! $txn && is_string( $ref ) && '' !== $ref ) {
						$txn = MGM_Transaction::get_by_trans_num( $ref );
					}
				}
				if ( $txn && ! empty( $charge['refunded'] ) ) {
					MGM_Transaction::update( $txn->id, array( 'status' => 'refunded' ) );
				}
				break;
		}
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	public function cancel_subscription( $sub ) {
		if ( ! $sub->gateway_sub_id ) {
			return true;
		}
		$res = $this->request( 'subscriptions/' . rawurlencode( $sub->gateway_sub_id ), array( 'cancel_at_period_end' => 'true' ) );
		return is_wp_error( $res ) ? $res : true;
	}

	public function update_payment_url( $sub ) {
		$cid = get_user_meta( $sub->user_id, $this->opt( 'test_mode' ) ? '_mgm_stripe_customer_test' : '_mgm_stripe_customer', true );
		if ( ! $cid ) {
			return '';
		}
		return add_query_arg(
			array(
				'mgm_action' => 'stripe_portal',
				'_wpnonce'   => wp_create_nonce( 'mgm_stripe_portal' ),
			),
			mgm_page_url( 'account' )
		);
	}

	/**
	 * Redirect to Stripe customer portal (update card, invoices).
	 */
	public function portal_redirect( $user_id ) {
		$cid = get_user_meta( $user_id, $this->opt( 'test_mode' ) ? '_mgm_stripe_customer_test' : '_mgm_stripe_customer', true );
		if ( ! $cid ) {
			return;
		}
		$res = $this->request(
			'billing_portal/sessions',
			array(
				'customer'   => $cid,
				'return_url' => mgm_page_url( 'account' ),
			)
		);
		if ( ! is_wp_error( $res ) && ! empty( $res['url'] ) ) {
			wp_redirect( $res['url'] ); // phpcs:ignore
			exit;
		}
	}
}
