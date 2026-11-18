<?php
/**
 * Registration + checkout processing.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Checkout {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'process' ), 1 );
		add_action( 'mgm_transaction_completed', array( __CLASS__, 'handle_upgrades' ), 5 );
		add_action( 'mgm_transaction_completed', array( __CLASS__, 'maybe_welcome' ), 20 );
	}

	/**
	 * Welcome email for new users whose first payment completed asynchronously (webhook / offline approval).
	 */
	public static function maybe_welcome( $txn ) {
		$pending = (int) get_user_meta( $txn->user_id, '_mgm_pending_welcome', true );
		if ( $pending && $pending === (int) $txn->id ) {
			delete_user_meta( $txn->user_id, '_mgm_pending_welcome' );
			do_action( 'mgm_new_member_signup', (int) $txn->user_id, (int) $txn->id );
		}
	}

	/**
	 * Tax calculation.
	 *
	 * @return array{subtotal: float, tax: float, total: float}
	 */
	public static function tax_for( $amount ) {
		$amount = (float) $amount;
		$rate   = (float) mgm_option( 'tax_rate', 0 );
		$rate   = apply_filters( 'mgm_tax_rate', $rate );
		if ( $rate <= 0 || $amount <= 0 ) {
			return array( 'subtotal' => $amount, 'tax' => 0.0, 'total' => $amount );
		}
		if ( mgm_option( 'tax_inclusive' ) ) {
			$subtotal = round( $amount / ( 1 + $rate / 100 ), 2 );
			return array( 'subtotal' => $subtotal, 'tax' => round( $amount - $subtotal, 2 ), 'total' => $amount );
		}
		$tax = round( $amount * $rate / 100, 2 );
		return array( 'subtotal' => $amount, 'tax' => $tax, 'total' => round( $amount + $tax, 2 ) );
	}

	/**
	 * Apply a coupon (Coupons add-on hooks in).
	 *
	 * @return array|WP_Error { coupon_id, price, trial_amount, trial_days }
	 */
	public static function apply_coupon( $code, MGM_Membership $m ) {
		$base = array(
			'coupon_id'    => 0,
			'price'        => $m->price(),
			'trial_amount' => (float) $m->get( 'trial_amount' ),
			'trial_days'   => $m->has_trial() && ! self::trial_used( get_current_user_id(), $m->id ) ? (int) $m->get( 'trial_days' ) : 0,
		);
		if ( '' === trim( (string) $code ) ) {
			return $base;
		}
		return apply_filters( 'mgm_apply_coupon', new WP_Error( 'mgm_coupon', __( 'Invalid coupon code.', 'mg-membership' ) ), $code, $m, $base );
	}

	/**
	 * Has this member already had a trial of this membership?
	 */
	public static function trial_used( $user_id, $membership_id ) {
		if ( ! $user_id ) {
			return false;
		}
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . MGM_Subscription::table() . ' WHERE user_id = %d AND membership_id = %d AND trial_days > 0 LIMIT 1', $user_id, $membership_id ) ); // phpcs:ignore
	}

	public static function render_form( $membership_id ) {
		$m = new MGM_Membership( $membership_id );
		if ( ! $m->exists() || 'publish' !== $m->post->post_status ) {
			return '<p class="mgm-notice mgm-notice-error">' . esc_html__( 'Membership not found.', 'mg-membership' ) . '</p>';
		}
		$can = $m->can_purchase( get_current_user_id() );

		return mgm_get_template(
			'checkout.php',
			array(
				'membership' => $m,
				'can'        => $can,
				'gateways'   => mgm()->enabled_gateways(),
				'notices'    => mgm_print_notices(),
				'user'       => wp_get_current_user(),
				'tax_rate'   => (float) mgm_option( 'tax_rate', 0 ),
			)
		);
	}

	private static function field( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore
	}

	public static function process() {
		if ( empty( $_POST['mgm_checkout'] ) ) {
			return;
		}
		if ( ! isset( $_POST['_mgm_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_nonce'] ), 'mgm_checkout' ) ) {
			mgm_add_notice( __( 'Your session expired. Please try again.', 'mg-membership' ) );
			return;
		}

		$membership_id = absint( $_POST['mgm_membership_id'] ?? 0 );
		$m             = new MGM_Membership( $membership_id );
		// Only published memberships can be bought (drafts / private / trashed plans are not for sale).
		if ( ! $m->exists() || 'publish' !== get_post_status( $m->id ) ) {
			mgm_add_notice( __( 'Invalid membership.', 'mg-membership' ) );
			return;
		}

		$errors  = new WP_Error();
		$user_id = get_current_user_id();

		// Honeypot.
		if ( '' !== self::field( 'mgm_website' ) ) {
			return;
		}

		// 1. Account.
		if ( ! $user_id ) {
			$email    = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
			$login    = sanitize_user( wp_unslash( $_POST['user_login'] ?? '' ), true );
			$pass     = (string) wp_unslash( $_POST['user_pass'] ?? '' ); // phpcs:ignore
			$pass2    = (string) wp_unslash( $_POST['user_pass_confirm'] ?? '' ); // phpcs:ignore
			$login    = $login ? $login : sanitize_user( current( explode( '@', $email ) ), true );

			if ( ! is_email( $email ) ) {
				$errors->add( 'email', __( 'Please enter a valid email address.', 'mg-membership' ) );
			} elseif ( email_exists( $email ) ) {
				$errors->add( 'email', sprintf( __( 'This email is already registered. Please <a href="%s">log in</a> first.', 'mg-membership' ), esc_url( add_query_arg( 'mgm_redirect', rawurlencode( $m->url() ), mgm_page_url( 'login' ) ) ) ) );
			}
			if ( ! $login || username_exists( $login ) ) {
				if ( isset( $_POST['user_login'] ) && '' !== $_POST['user_login'] ) {
					$errors->add( 'login', __( 'That username is taken or invalid.', 'mg-membership' ) );
				} else {
					$login .= wp_rand( 100, 9999 );
				}
			}
			if ( strlen( $pass ) < 8 ) {
				$errors->add( 'pass', __( 'Password must be at least 8 characters.', 'mg-membership' ) );
			} elseif ( $pass !== $pass2 && MGM_Forms::visible( 'checkout', 'user_pass_confirm' ) ) {
				$errors->add( 'pass', __( 'Passwords do not match.', 'mg-membership' ) );
			}
		}

		if ( mgm_option( 'require_terms' ) && empty( $_POST['mgm_terms'] ) ) {
			$errors->add( 'terms', __( 'You must accept the terms.', 'mg-membership' ) );
		}

		$can = $m->can_purchase( $user_id );
		if ( is_wp_error( $can ) ) {
			$errors->add( $can->get_error_code(), $can->get_error_message() );
		}

		// Throttle automated account creation.
		if ( ! $user_id && ! $errors->has_errors() && ! MGM_Security_Core::hit( 'signup' ) ) {
			$errors->add( 'rate', __( 'Too many sign-ups from your network. Please try again later.', 'mg-membership' ) );
		}

		// 2. Pricing.
		$code = self::field( 'mgm_coupon' );
		if ( '' !== $code && MGM_Security_Core::exceeded( 'coupon' ) ) {
			$errors->add( 'coupon', __( 'Too many coupon attempts. Please wait a few minutes.', 'mg-membership' ) );
			$code = '';
		}
		$pricing = self::apply_coupon( $code, $m );
		if ( is_wp_error( $pricing ) ) {
			MGM_Security_Core::hit( 'coupon' );
		}
		if ( is_wp_error( $pricing ) ) {
			$errors->add( 'coupon', $pricing->get_error_message() );
		}

		$gateway_id = self::field( 'mgm_gateway' );
		$needs_pay  = ! is_wp_error( $pricing ) && ( $pricing['price'] > 0 || ( $m->is_recurring() && $pricing['trial_amount'] > 0 ) );
		$gateway    = $needs_pay ? mgm()->gateway( $gateway_id ) : null;
		if ( $needs_pay && ( ! $gateway || ! $gateway->is_enabled() ) ) {
			$errors->add( 'gateway', __( 'Please select a payment method.', 'mg-membership' ) );
		}

		$errors = apply_filters( 'mgm_checkout_validate', $errors, $m, $_POST ); // phpcs:ignore

		if ( $errors->has_errors() ) {
			foreach ( $errors->get_error_messages() as $msg ) {
				mgm_add_notice( $msg );
			}
			return;
		}

		// 3. Create user.
		$new_user = false;
		if ( ! $user_id ) {
			$user_id = wp_insert_user(
				array(
					'user_login' => $login,
					'user_email' => $email,
					'user_pass'  => $pass,
					'first_name' => self::field( 'first_name' ),
					'last_name'  => self::field( 'last_name' ),
					'role'       => get_option( 'default_role', 'subscriber' ),
				)
			);
			if ( is_wp_error( $user_id ) ) {
				mgm_add_notice( $user_id->get_error_message() );
				return;
			}
			$new_user = true;
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true );
		} else {
			$fn = self::field( 'first_name' );
			$ln = self::field( 'last_name' );
			if ( $fn || $ln ) {
				wp_update_user( array( 'ID' => $user_id, 'first_name' => $fn, 'last_name' => $ln ) );
			}
		}
		do_action( 'mgm_checkout_user_ready', $user_id, $m, $new_user );

		// 4. Build order.
		$tax   = self::tax_for( $pricing['price'] );
		$order = array(
			'user_id'      => $user_id,
			'membership'   => $m,
			'price'        => $pricing['price'],
			'tax'          => $tax,
			'coupon_id'    => $pricing['coupon_id'],
			'trial_days'   => $pricing['trial_days'],
			'trial_amount' => $pricing['trial_amount'],
			'sub_id'       => 0,
			'txn_id'       => 0,
		);

		// 5. Free membership / 100% discount.
		if ( ! $needs_pay ) {
			if ( $m->is_recurring() && $pricing['trial_days'] > 0 && $pricing['price'] > 0 ) {
				// Free trial but recurring after – gateway still required to save payment method.
				$gateway = mgm()->gateway( $gateway_id );
				if ( ! $gateway || ! $gateway->is_enabled() ) {
					mgm_add_notice( __( 'Please select a payment method.', 'mg-membership' ) );
					return;
				}
			} else {
				$expires         = $m->is_recurring() ? null : $m->one_time_expiration();
				$order['txn_id'] = MGM_Transaction::create(
					array(
						'user_id'       => $user_id,
						'membership_id' => $m->id,
						'amount'        => 0,
						'coupon_id'     => $pricing['coupon_id'],
						'gateway'       => 'free',
						'status'        => 'complete',
						'expires_at'    => $expires,
					)
				);
				self::finish( $order, $new_user );
				return;
			}
		}

		// 6. Paid: create pending records & hand off to gateway.
		if ( $m->is_recurring() ) {
			$order['sub_id'] = MGM_Subscription::create(
				array(
					'user_id'       => $user_id,
					'membership_id' => $m->id,
					'gateway'       => $gateway->id,
					'status'        => 'pending',
					'price'         => $pricing['price'],
					'period'        => (int) $m->get( 'period' ),
					'period_unit'   => $m->get( 'period_unit' ),
					'trial_days'    => $pricing['trial_days'],
					'trial_amount'  => $pricing['trial_amount'],
					'limit_cycles'  => (int) $m->get( 'limit_cycles' ),
					'coupon_id'     => $pricing['coupon_id'],
				)
			);
		}
		$first_amount    = ( $m->is_recurring() && $pricing['trial_days'] > 0 ) ? $pricing['trial_amount'] : $pricing['price'];
		$first_tax       = self::tax_for( $first_amount );
		$order['first']  = $first_tax;
		$order['txn_id'] = MGM_Transaction::create(
			array(
				'user_id'         => $user_id,
				'membership_id'   => $m->id,
				'subscription_id' => $order['sub_id'],
				'amount'          => $first_tax['subtotal'],
				'tax_amount'      => $first_tax['tax'],
				'total'           => $first_tax['total'],
				'coupon_id'       => $pricing['coupon_id'],
				'gateway'         => $gateway->id,
				'status'          => 'pending',
				'expires_at'      => self::first_expiration( $m, $pricing ),
			)
		);
		if ( $new_user ) {
			update_user_meta( $user_id, '_mgm_pending_welcome', $order['txn_id'] );
		}

		$result = $gateway->process_payment( $order );
		if ( is_wp_error( $result ) ) {
			mgm_add_notice( $result->get_error_message() );
			return;
		}
		if ( ! empty( $result['redirect'] ) ) {
			wp_redirect( $result['redirect'] ); // phpcs:ignore -- external gateway.
			exit;
		}
		self::finish( $order, $new_user );
	}

	/**
	 * Expiration of the first transaction.
	 */
	public static function first_expiration( MGM_Membership $m, array $pricing ) {
		if ( ! $m->is_recurring() ) {
			return $m->one_time_expiration();
		}
		if ( $pricing['trial_days'] > 0 ) {
			return gmdate( 'Y-m-d H:i:s', time() + ( $pricing['trial_days'] + 1 ) * DAY_IN_SECONDS );
		}
		return gmdate( 'Y-m-d H:i:s', mgm_add_period( time(), $m->get( 'period' ), $m->get( 'period_unit' ) ) + DAY_IN_SECONDS );
	}

	private static function finish( array $order, $new_user ) {
		$txn = MGM_Transaction::get( $order['txn_id'] );
		if ( $new_user ) {
			do_action( 'mgm_new_member_signup', $order['user_id'], $order['txn_id'] );
			delete_user_meta( $order['user_id'], '_mgm_pending_welcome' );
		}
		wp_safe_redirect( self::thankyou_url( $txn ) );
		exit;
	}

	public static function thankyou_url( $txn ) {
		$m   = new MGM_Membership( $txn->membership_id );
		$url = $m->get( 'thankyou_url' ) ? $m->get( 'thankyou_url' ) : mgm_page_url( 'thankyou' );
		return add_query_arg( 'mgm_txn', rawurlencode( $txn->trans_num ), $url );
	}

	/**
	 * When a member buys a plan in an upgrade group, end their other plans in that group.
	 */
	public static function handle_upgrades( $txn ) {
		$m        = new MGM_Membership( $txn->membership_id );
		$siblings = $m->group_siblings();
		if ( ! $siblings ) {
			return;
		}
		foreach ( MGM_Subscription::for_user( $txn->user_id ) as $sub ) {
			if ( in_array( (int) $sub->membership_id, $siblings, true ) && in_array( $sub->status, array( 'active', 'trialing', 'suspended' ), true ) ) {
				MGM_Subscription::cancel( $sub->id );
			}
		}
		foreach ( $siblings as $sid ) {
			if ( in_array( (int) $sid, MGM_Member::active_membership_ids( $txn->user_id ), true ) ) {
				MGM_Member::revoke( $txn->user_id, $sid );
			}
		}
	}
}
