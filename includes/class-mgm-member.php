<?php
/**
 * Member helpers: active memberships, join dates, roles, manual grants.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Member {

	private static $cache = array();

	public static function flush_cache( $user_id = 0 ) {
		if ( $user_id ) {
			unset( self::$cache[ $user_id ] );
		} else {
			self::$cache = array();
		}
	}

	/**
	 * Active membership IDs for a user, based on completed & unexpired transactions.
	 *
	 * @return int[]
	 */
	public static function active_membership_ids( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return array();
		}
		if ( isset( self::$cache[ $user_id ] ) ) {
			return self::$cache[ $user_id ];
		}
		global $wpdb;
		$ids = $wpdb->get_col( // phpcs:ignore
			$wpdb->prepare(
				'SELECT DISTINCT membership_id FROM ' . MGM_Transaction::table() . " WHERE user_id = %d AND status = 'complete' AND ( expires_at IS NULL OR expires_at = '0000-00-00 00:00:00' OR expires_at > %s )",
				$user_id,
				mgm_now()
			)
		);
		$ids = array_map( 'intval', $ids );

		// Group (corporate) accounts can inherit memberships.
		$ids = array_values( array_unique( apply_filters( 'mgm_active_membership_ids', $ids, $user_id ) ) );

		self::$cache[ $user_id ] = $ids;
		return $ids;
	}

	/**
	 * When did the user's current access to a membership start (GMT timestamp)? Used for drip.
	 */
	public static function membership_start( $user_id, $membership_id ) {
		global $wpdb;
		$date = $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				'SELECT MIN(created_at) FROM ' . MGM_Transaction::table() . " WHERE user_id = %d AND membership_id = %d AND status = 'complete'",
				$user_id,
				$membership_id
			)
		);
		return (int) apply_filters( 'mgm_membership_start', $date ? strtotime( $date . ' UTC' ) : 0, $user_id, $membership_id );
	}

	/**
	 * Expiration (GMT mysql or null for lifetime) of the user's current access.
	 */
	public static function membership_expires( $user_id, $membership_id ) {
		global $wpdb;
		$rows = $wpdb->get_col( // phpcs:ignore
			$wpdb->prepare(
				'SELECT expires_at FROM ' . MGM_Transaction::table() . " WHERE user_id = %d AND membership_id = %d AND status = 'complete'",
				$user_id,
				$membership_id
			)
		);
		$max = '';
		foreach ( $rows as $r ) {
			if ( empty( $r ) || '0000-00-00 00:00:00' === $r ) {
				return null;
			}
			$max = max( $max, $r );
		}
		return $max ? $max : null;
	}

	/**
	 * Manually grant membership (admin action, import, free signup).
	 */
	public static function grant( $user_id, $membership_id, $expires_at = 'auto', $gateway = 'manual' ) {
		$m = new MGM_Membership( $membership_id );
		if ( 'auto' === $expires_at ) {
			$expires_at = $m->is_recurring()
				? gmdate( 'Y-m-d H:i:s', mgm_add_period( time(), $m->get( 'period' ), $m->get( 'period_unit' ) ) )
				: $m->one_time_expiration();
		}
		$id = MGM_Transaction::create(
			array(
				'user_id'       => $user_id,
				'membership_id' => $membership_id,
				'amount'        => 0,
				'gateway'       => $gateway,
				'txn_type'      => 'manual',
				'status'        => 'complete',
				'expires_at'    => $expires_at,
			)
		);
		self::flush_cache( $user_id );
		return $id;
	}

	/**
	 * Revoke: expire all of the user's transactions for a membership and cancel subscriptions.
	 */
	public static function revoke( $user_id, $membership_id ) {
		global $wpdb;
		$wpdb->query( // phpcs:ignore
			$wpdb->prepare(
				'UPDATE ' . MGM_Transaction::table() . " SET expires_at = %s WHERE user_id = %d AND membership_id = %d AND status = 'complete' AND ( expires_at IS NULL OR expires_at > %s )",
				gmdate( 'Y-m-d H:i:s', time() - 1 ),
				$user_id,
				$membership_id,
				mgm_now()
			)
		);
		foreach ( MGM_Subscription::for_user( $user_id ) as $sub ) {
			if ( (int) $sub->membership_id === (int) $membership_id && in_array( $sub->status, array( 'active', 'trialing', 'pending', 'suspended' ), true ) ) {
				MGM_Subscription::cancel( $sub->id );
			}
		}
		self::flush_cache( $user_id );
		self::sync_role( $user_id );
		do_action( 'mgm_membership_revoked', $user_id, $membership_id );
	}

	/**
	 * Add membership-specific roles to the user & remove those no longer valid.
	 */
	public static function sync_role( $user_id ) {
		self::flush_cache( $user_id );
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}
		$active    = self::active_membership_ids( $user_id );
		$all_roles = array();
		foreach ( array_keys( mgm_membership_options() ) as $mid ) {
			$role = get_post_meta( $mid, '_mgm_user_role', true );
			if ( $role && MGM_Post_Types::safe_role( $role ) ) {
				$all_roles[ $mid ] = $role;
			}
		}
		$keep = array();
		foreach ( $active as $mid ) {
			if ( isset( $all_roles[ $mid ] ) ) {
				$keep[] = $all_roles[ $mid ];
			}
		}
		foreach ( array_unique( $all_roles ) as $role ) {
			if ( in_array( $role, $keep, true ) ) {
				if ( ! in_array( $role, (array) $user->roles, true ) ) {
					$user->add_role( $role );
				}
			} elseif ( in_array( $role, (array) $user->roles, true ) && count( $user->roles ) > 1 ) {
				$user->remove_role( $role );
			}
		}
		update_user_meta( $user_id, '_mgm_active_memberships', $active );
	}

	/**
	 * All users having at least one transaction (members), with paging.
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'search'        => '',
				'membership_id' => 0,
				'status'        => '', // active | inactive.
				'per_page'      => 20,
				'page'          => 1,
				'count'         => false,
			)
		);
		$t     = MGM_Transaction::table();
		$now   = esc_sql( mgm_now() );
		$where = array( '1=1' );
		if ( $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = $wpdb->prepare( '(u.user_email LIKE %s OR u.user_login LIKE %s OR u.display_name LIKE %s)', $like, $like, $like );
		}
		if ( $args['membership_id'] ) {
			$where[] = $wpdb->prepare( 't.membership_id = %d', $args['membership_id'] );
		}
		$active_expr = "SUM(CASE WHEN t.status='complete' AND (t.expires_at IS NULL OR t.expires_at='0000-00-00 00:00:00' OR t.expires_at > '{$now}') THEN 1 ELSE 0 END)";
		$having      = '';
		if ( 'active' === $args['status'] ) {
			$having = "HAVING {$active_expr} > 0";
		} elseif ( 'inactive' === $args['status'] ) {
			$having = "HAVING {$active_expr} = 0";
		}
		$w   = implode( ' AND ', $where );
		$sql = "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
				{$active_expr} AS active_count,
				SUM(CASE WHEN t.status='complete' THEN t.total ELSE 0 END) AS ltv,
				COUNT(t.id) AS txn_count,
				MAX(t.created_at) AS last_txn
				FROM {$t} t INNER JOIN {$wpdb->users} u ON u.ID = t.user_id
				WHERE {$w} GROUP BY u.ID {$having}";
		if ( $args['count'] ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM ({$sql}) x" ); // phpcs:ignore
		}
		$sql .= $wpdb->prepare( ' ORDER BY last_txn DESC LIMIT %d OFFSET %d', $args['per_page'], ( max( 1, $args['page'] ) - 1 ) * $args['per_page'] );
		return $wpdb->get_results( $sql ); // phpcs:ignore
	}
}
