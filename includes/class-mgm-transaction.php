<?php
/**
 * Transactions (payments). A completed, non-expired transaction grants access.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Transaction {

	const STATUSES = array( 'pending', 'complete', 'failed', 'refunded' );

	public static function table() {
		return MGM_Install::table( 'transactions' );
	}

	public static function create( array $data ) {
		global $wpdb;
		$data = wp_parse_args(
			$data,
			array(
				'user_id'         => 0,
				'membership_id'   => 0,
				'subscription_id' => 0,
				'amount'          => 0,
				'tax_amount'      => 0,
				'total'           => null,
				'coupon_id'       => 0,
				'gateway'         => 'manual',
				'trans_num'       => '',
				'txn_type'        => 'payment',
				'status'          => 'pending',
				'created_at'      => mgm_now(),
				'expires_at'      => null,
			)
		);
		if ( null === $data['total'] ) {
			$data['total'] = (float) $data['amount'] + (float) $data['tax_amount'];
		}
		if ( '' === $data['trans_num'] ) {
			$data['trans_num'] = 'mgm-' . strtolower( wp_generate_password( 12, false ) );
		}
		$wpdb->insert( self::table(), $data ); // phpcs:ignore
		$id = (int) $wpdb->insert_id;
		if ( $id ) {
			do_action( 'mgm_transaction_created', $id, $data );
			if ( 'complete' === $data['status'] ) {
				self::on_complete( $id );
			}
		}
		return $id;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	}

	public static function get_by_trans_num( $num ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE trans_num = %s', $num ) ); // phpcs:ignore
	}

	public static function update( $id, array $data ) {
		global $wpdb;
		$before = self::get( $id );
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) ); // phpcs:ignore
		if ( $before && isset( $data['status'] ) && $data['status'] !== $before->status ) {
			do_action( 'mgm_transaction_status_changed', $id, $data['status'], $before->status );
			if ( 'complete' === $data['status'] ) {
				self::on_complete( $id );
			} elseif ( 'refunded' === $data['status'] ) {
				do_action( 'mgm_transaction_refunded', $id );
				MGM_Member::sync_role( (int) $before->user_id );
			}
		}
	}

	public static function complete( $id, $trans_num = '' ) {
		$data = array( 'status' => 'complete' );
		if ( $trans_num ) {
			$data['trans_num'] = $trans_num;
		}
		self::update( $id, $data );
	}

	private static function on_complete( $id ) {
		$txn = self::get( $id );
		if ( ! $txn ) {
			return;
		}
		MGM_Member::sync_role( (int) $txn->user_id );
		if ( $txn->coupon_id ) {
			do_action( 'mgm_coupon_used', (int) $txn->coupon_id, $txn );
		}
		do_action( 'mgm_transaction_completed', $txn );
		do_action( 'mgm_membership_granted', (int) $txn->user_id, (int) $txn->membership_id, $txn );
	}

	public static function delete( $id ) {
		global $wpdb;
		$txn = self::get( $id );
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) ); // phpcs:ignore
		if ( $txn ) {
			MGM_Member::sync_role( (int) $txn->user_id );
		}
	}

	/**
	 * Query transactions.
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'user_id'       => 0,
				'membership_id' => 0,
				'status'        => '',
				'search'        => '',
				'per_page'      => 20,
				'page'          => 1,
				'orderby'       => 'id',
				'order'         => 'DESC',
				'count'         => false,
			)
		);
		$where = array( '1=1' );
		if ( $args['user_id'] ) {
			$where[] = $wpdb->prepare( 't.user_id = %d', $args['user_id'] );
		}
		if ( $args['membership_id'] ) {
			$where[] = $wpdb->prepare( 't.membership_id = %d', $args['membership_id'] );
		}
		if ( $args['status'] ) {
			$where[] = $wpdb->prepare( 't.status = %s', $args['status'] );
		}
		if ( $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = $wpdb->prepare( '(t.trans_num LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)', $like, $like, $like );
		}
		$sql_where = implode( ' AND ', $where );
		$from      = self::table() . " t LEFT JOIN {$wpdb->users} u ON u.ID = t.user_id";

		if ( $args['count'] ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$from} WHERE {$sql_where}" ); // phpcs:ignore
		}
		$orderby = in_array( $args['orderby'], array( 'id', 'created_at', 'total', 'status', 'expires_at' ), true ) ? $args['orderby'] : 'id';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit   = $args['per_page'] > 0 ? $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['per_page'], ( max( 1, $args['page'] ) - 1 ) * $args['per_page'] ) : '';
		return $wpdb->get_results( "SELECT t.*, u.user_email, u.display_name FROM {$from} WHERE {$sql_where} ORDER BY t.{$orderby} {$order} {$limit}" ); // phpcs:ignore
	}
}
