<?php
/**
 * Scheduled jobs: expirations, reminders, subscription status housekeeping.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Cron {

	public static function init() {
		add_action( 'mgm_daily_cron', array( __CLASS__, 'run' ) );
		add_action( 'mgm_transaction_completed', array( __CLASS__, 'activate_subscription' ), 1 );
		if ( ! wp_next_scheduled( 'mgm_daily_cron' ) ) {
			wp_schedule_event( time() + 60, 'hourly', 'mgm_daily_cron' );
		}
	}

	/**
	 * Mark a pending subscription active once its first transaction completes (e.g. offline approval).
	 */
	public static function activate_subscription( $txn ) {
		if ( ! $txn->subscription_id ) {
			return;
		}
		$sub = MGM_Subscription::get( $txn->subscription_id );
		if ( $sub && 'pending' === $sub->status ) {
			MGM_Subscription::update(
				$sub->id,
				array(
					'status'          => $sub->trial_days > 0 ? 'trialing' : 'active',
					'next_payment_at' => $txn->expires_at,
				)
			);
		}
	}

	public static function run() {
		global $wpdb;
		$t   = MGM_Transaction::table();
		$s   = MGM_Subscription::table();
		$now = mgm_now();

		// 1. Expire subscriptions whose access ended and that are cancelled or past due.
		$subs = $wpdb->get_results( "SELECT * FROM {$s} WHERE status IN ('cancelled','suspended','trialing','active')" ); // phpcs:ignore
		foreach ( $subs as $sub ) {
			$latest = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(expires_at) FROM {$t} WHERE subscription_id = %d AND status = 'complete'", $sub->id ) ); // phpcs:ignore
			if ( $latest && $latest < $now ) {
				if ( 'cancelled' === $sub->status || 'suspended' === $sub->status ) {
					MGM_Subscription::update( $sub->id, array( 'status' => 'expired' ) );
				} elseif ( 'offline' === $sub->gateway || 'manual' === $sub->gateway ) {
					// Offline renewals: create pending renewal invoice once.
					$pending = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE subscription_id = %d AND status = 'pending'", $sub->id ) ); // phpcs:ignore
					if ( ! $pending ) {
						$tax = MGM_Checkout::tax_for( (float) $sub->price );
						MGM_Transaction::create(
							array(
								'user_id'         => $sub->user_id,
								'membership_id'   => $sub->membership_id,
								'subscription_id' => $sub->id,
								'amount'          => $tax['subtotal'],
								'tax_amount'      => $tax['tax'],
								'total'           => $tax['total'],
								'gateway'         => $sub->gateway,
								'status'          => 'pending',
								'expires_at'      => gmdate( 'Y-m-d H:i:s', mgm_add_period( time(), $sub->period, $sub->period_unit ) ),
							)
						);
					}
				}
				MGM_Member::sync_role( (int) $sub->user_id );
			}
		}

		// 2. Expiring reminders (7 days before, one-time & cancelled subs).
		$soon = gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE status = 'complete' AND expires_at IS NOT NULL AND expires_at > %s AND expires_at < %s", $now, $soon ) ); // phpcs:ignore
		foreach ( $rows as $row ) {
			if ( $row->subscription_id ) {
				$sub = MGM_Subscription::get( $row->subscription_id );
				if ( $sub && in_array( $sub->status, array( 'active', 'trialing' ), true ) ) {
					continue; // Will auto-renew.
				}
			}
			$key = '_mgm_expiring_sent_' . $row->id;
			if ( get_user_meta( $row->user_id, $key, true ) ) {
				continue;
			}
			// Skip if the user has a later transaction for the same membership.
			$later = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE user_id = %d AND membership_id = %d AND status = 'complete' AND ( expires_at IS NULL OR expires_at > %s )", $row->user_id, $row->membership_id, $row->expires_at ) ); // phpcs:ignore
			if ( $later ) {
				continue;
			}
			update_user_meta( $row->user_id, $key, 1 );
			do_action( 'mgm_membership_expiring', (int) $row->user_id, (int) $row->membership_id, $row->expires_at );
		}

		// 3. Role cleanup for recently expired.
		// Window starts at the previous run (WP-cron can be delayed for days on quiet sites).
		$last = (int) get_option( 'mgm_cron_last_run', 0 );
		$from = gmdate( 'Y-m-d H:i:s', $last ? $last - HOUR_IN_SECONDS : time() - 30 * DAY_IN_SECONDS );
		update_option( 'mgm_cron_last_run', time(), false );
		$expired = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$t} WHERE status='complete' AND expires_at BETWEEN %s AND %s", $from, $now ) ); // phpcs:ignore
		foreach ( $expired as $uid ) {
			MGM_Member::sync_role( (int) $uid );
			do_action( 'mgm_membership_expired_check', (int) $uid );
		}

		// 4. Upcoming renewal reminders (3 days ahead, once per billing period).
		$ahead = (int) apply_filters( 'mgm_renewal_reminder_days', 3 );
		$subs  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$s} WHERE status IN ('active','trialing') AND gateway IN ('stripe','paypal','offline') AND next_payment_at IS NOT NULL AND next_payment_at BETWEEN %s AND %s", $now, gmdate( 'Y-m-d H:i:s', time() + $ahead * DAY_IN_SECONDS ) ) ); // phpcs:ignore
		foreach ( $subs as $sub ) {
			$key = '_mgm_renew_notice_' . $sub->id;
			if ( get_user_meta( $sub->user_id, $key, true ) === $sub->next_payment_at ) {
				continue;
			}
			$user = get_userdata( $sub->user_id );
			if ( ! $user ) {
				continue;
			}
			update_user_meta( $sub->user_id, $key, $sub->next_payment_at );
			MGM_Emails::send(
				'renewal_reminder',
				$user->user_email,
				array_merge(
					MGM_Emails::user_vars( $sub->user_id, $sub->membership_id ),
					array(
						'renewal_date' => mgm_date( $sub->next_payment_at ),
						'amount'       => mgm_price( MGM_Checkout::tax_for( (float) $sub->price )['total'] ),
					)
				)
			);
		}

		do_action( 'mgm_cron' );
	}
}
