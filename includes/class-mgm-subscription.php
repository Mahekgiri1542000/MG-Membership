<?php
/**
 * Recurring subscriptions.
 *
 * Statuses: pending, trialing, active, suspended (paused), cancelled, expired.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Subscription {

	public static function table() {
		return MGM_Install::table( 'subscriptions' );
	}

	public static function create( array $data ) {
		global $wpdb;
		$data = wp_parse_args(
			$data,
			array(
				'user_id'         => 0,
				'membership_id'   => 0,
				'gateway'         => 'manual',
				'gateway_sub_id'  => '',
				'status'          => 'pending',
				'price'           => 0,
				'period'          => 1,
				'period_unit'     => 'month',
				'trial_days'      => 0,
				'trial_amount'    => 0,
				'limit_cycles'    => 0,
				'coupon_id'       => 0,
				'created_at'      => mgm_now(),
				'next_payment_at' => null,
			)
		);
		$wpdb->insert( self::table(), $data ); // phpcs:ignore
		$id = (int) $wpdb->insert_id;
		do_action( 'mgm_subscription_created', $id, $data );
		return $id;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	}

	public static function get_by_gateway_id( $gateway_sub_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE gateway_sub_id = %s', $gateway_sub_id ) ); // phpcs:ignore
	}

	public static function update( $id, array $data ) {
		global $wpdb;
		$before = self::get( $id );
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) ); // phpcs:ignore
		if ( $before && isset( $data['status'] ) && $data['status'] !== $before->status ) {
			do_action( 'mgm_subscription_status_changed', $id, $data['status'], $before->status );
			do_action( 'mgm_subscription_' . $data['status'], $id );
			MGM_Member::sync_role( (int) $before->user_id );
		}
	}

	public static function for_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY id DESC', $user_id ) ); // phpcs:ignore
	}

	/**
	 * Record a successful renewal payment & extend access.
	 */
	public static function record_payment( $sub_id, $amount, $trans_num = '', $period_end = null ) {
		$sub = self::get( $sub_id );
		if ( ! $sub ) {
			return 0;
		}
		if ( $trans_num && MGM_Transaction::get_by_trans_num( $trans_num ) ) {
			return 0; // Already recorded (webhook retry).
		}
		$expires = $period_end ? gmdate( 'Y-m-d H:i:s', $period_end ) : gmdate( 'Y-m-d H:i:s', mgm_add_period( time(), $sub->period, $sub->period_unit ) );
		// Grace period of 1 day for gateway retries.
		$expires = gmdate( 'Y-m-d H:i:s', strtotime( $expires ) + DAY_IN_SECONDS );

		$tax = MGM_Checkout::tax_for( (float) $amount );
		$id  = MGM_Transaction::create(
			array(
				'user_id'         => $sub->user_id,
				'membership_id'   => $sub->membership_id,
				'subscription_id' => $sub->id,
				'amount'          => $tax['subtotal'],
				'tax_amount'      => $tax['tax'],
				'total'           => $tax['total'],
				'coupon_id'       => $sub->coupon_id,
				'gateway'         => $sub->gateway,
				'trans_num'       => $trans_num,
				'status'          => 'complete',
				'expires_at'      => $expires,
			)
		);
		self::update(
			$sub_id,
			array(
				'status'          => 'active',
				'next_payment_at' => gmdate( 'Y-m-d H:i:s', strtotime( $expires ) - DAY_IN_SECONDS ),
			)
		);

		// Limit cycles: cancel when reached.
		if ( (int) $sub->limit_cycles > 0 ) {
			global $wpdb;
			$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . MGM_Transaction::table() . " WHERE subscription_id = %d AND status = 'complete' AND total > 0", $sub_id ) ); // phpcs:ignore
			if ( $count >= (int) $sub->limit_cycles ) {
				self::cancel( $sub_id, false );
			}
		}
		do_action( 'mgm_subscription_renewed', $sub_id, $id );
		return $id;
	}

	/**
	 * Cancel a subscription (stops future billing; access remains until paid period ends).
	 */
	public static function cancel( $sub_id, $at_gateway = true ) {
		$sub = self::get( $sub_id );
		if ( ! $sub || in_array( $sub->status, array( 'cancelled', 'expired' ), true ) ) {
			return false;
		}
		if ( $at_gateway ) {
			$gateway = mgm()->gateway( $sub->gateway );
			if ( $gateway ) {
				$result = $gateway->cancel_subscription( $sub );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}
		self::update(
			$sub_id,
			array(
				'status'       => 'cancelled',
				'cancelled_at' => mgm_now(),
			)
		);
		return true;
	}

	public static function query( array $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'status'   => '',
				'search'   => '',
				'per_page' => 20,
				'page'     => 1,
				'count'    => false,
			)
		);
		$where = array( '1=1' );
		if ( $args['status'] ) {
			$where[] = $wpdb->prepare( 's.status = %s', $args['status'] );
		}
		if ( $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = $wpdb->prepare( '(s.gateway_sub_id LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)', $like, $like, $like );
		}
		$w    = implode( ' AND ', $where );
		$from = self::table() . " s LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id";
		if ( $args['count'] ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$from} WHERE {$w}" ); // phpcs:ignore
		}
		$limit = $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['per_page'], ( max( 1, $args['page'] ) - 1 ) * $args['per_page'] );
		return $wpdb->get_results( "SELECT s.*, u.user_email, u.display_name FROM {$from} WHERE {$w} ORDER BY s.id DESC {$limit}" ); // phpcs:ignore
	}

	public static function status_label( $status ) {
		$labels = array(
			'pending'   => __( 'Pending', 'mg-membership' ),
			'trialing'  => __( 'Trial', 'mg-membership' ),
			'active'    => __( 'Active', 'mg-membership' ),
			'suspended' => __( 'Paused', 'mg-membership' ),
			'cancelled' => __( 'Cancelled', 'mg-membership' ),
			'expired'   => __( 'Expired', 'mg-membership' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
	}
}
