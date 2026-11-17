<?php
/**
 * Add-on: Membership automation.
 * - Give a membership to every newly registered user.
 * - Give a membership to everyone with chosen role(s); removed again when the role is removed.
 * Automated grants use gateway "automation" so they can be told apart from purchases.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Automation {

	public static function init() {
		add_action( 'mgm_membership_options_box', array( __CLASS__, 'fields' ) );
		add_action( 'mgm_save_membership', array( __CLASS__, 'save' ) );
		add_action( 'user_register', array( __CLASS__, 'on_register' ), 20 );
		add_action( 'set_user_role', array( __CLASS__, 'on_roles_changed' ), 20 );
		add_action( 'add_user_role', array( __CLASS__, 'on_roles_changed' ), 20 );
		add_action( 'remove_user_role', array( __CLASS__, 'on_roles_changed' ), 20 );
	}

	private static function auto_memberships( $key ) {
		return get_posts(
			array(
				'post_type'   => 'mgm_membership',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_query'  => array( array( 'key' => $key, 'compare' => 'EXISTS' ) ), // phpcs:ignore
			)
		);
	}

	public static function fields( $post ) {
		$on    = '1' === get_post_meta( $post->ID, '_mgm_auto_new_users', true );
		$roles = (array) get_post_meta( $post->ID, '_mgm_auto_roles', true );
		echo '<div class="mgm-subgroup"><p class="mgm-field"><strong>' . esc_html__( 'Automation', 'mg-membership' ) . '</strong></p>';
		echo '<p class="mgm-field"><label><input type="checkbox" name="mgm_auto_new_users" value="1" ' . checked( $on, true, false ) . '> ' . esc_html__( 'Give this membership to every new user who registers', 'mg-membership' ) . '</label></p>';
		echo '<p class="mgm-field"><label>' . esc_html__( 'Give this membership to everyone with these roles', 'mg-membership' ) . '</label>';
		foreach ( wp_roles()->get_names() as $k => $n ) {
			if ( 'administrator' === $k ) {
				continue; // Admins already see everything.
			}
			echo '<label style="display:inline-block;margin-right:12px"><input type="checkbox" name="mgm_auto_roles[]" value="' . esc_attr( $k ) . '" ' . checked( in_array( $k, $roles, true ), true, false ) . '> ' . esc_html( translate_user_role( $n ) ) . '</label>';
		}
		echo '</p></div>';
	}

	public static function save( $post_id ) {
		// Nonce verified by the membership save handler.
		if ( ! empty( $_POST['mgm_auto_new_users'] ) ) { // phpcs:ignore
			update_post_meta( $post_id, '_mgm_auto_new_users', '1' );
		} else {
			delete_post_meta( $post_id, '_mgm_auto_new_users' );
		}
		$roles = array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['mgm_auto_roles'] ?? array() ) ), array_keys( wp_roles()->get_names() ) ) ); // phpcs:ignore
		if ( $roles ) {
			update_post_meta( $post_id, '_mgm_auto_roles', $roles );
			self::backfill( $post_id, $roles );
		} else {
			delete_post_meta( $post_id, '_mgm_auto_roles' );
		}
	}

	/**
	 * Apply a role rule to existing users (capped for safety on very large sites).
	 */
	public static function backfill( $membership_id, array $roles ) {
		foreach ( get_users( array( 'role__in' => $roles, 'fields' => 'ID', 'number' => 2000 ) ) as $uid ) {
			self::grant( (int) $uid, $membership_id );
		}
	}

	private static function has( $user_id, $membership_id ) {
		MGM_Member::flush_cache( $user_id );
		return in_array( (int) $membership_id, MGM_Member::active_membership_ids( $user_id ), true );
	}

	private static function grant( $user_id, $membership_id ) {
		if ( self::has( $user_id, $membership_id ) ) {
			return;
		}
		MGM_Member::grant( $user_id, $membership_id, null, 'automation' );
		do_action( 'mgm_automation_granted', $user_id, $membership_id );
	}

	public static function on_register( $user_id ) {
		foreach ( self::auto_memberships( '_mgm_auto_new_users' ) as $mid ) {
			self::grant( $user_id, $mid );
		}
		self::on_roles_changed( $user_id );
	}

	public static function on_roles_changed( $user_id ) {
		static $running = array();
		// Granting a membership may itself add a role (membership "user role" setting) – don't recurse.
		if ( ! empty( $running[ $user_id ] ) ) {
			return;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$running[ $user_id ] = true;
		self::apply_roles( $user );
		unset( $running[ $user_id ] );
	}

	private static function apply_roles( $user ) {
		$user_id = (int) $user->ID;
		global $wpdb;
		foreach ( self::auto_memberships( '_mgm_auto_roles' ) as $mid ) {
			$roles = (array) get_post_meta( $mid, '_mgm_auto_roles', true );
			if ( array_intersect( $roles, (array) $user->roles ) ) {
				self::grant( $user_id, $mid );
			} else {
				// Remove only access that automation granted (never purchases).
				$auto = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . MGM_Transaction::table() . " WHERE user_id = %d AND membership_id = %d AND gateway = 'automation' AND status = 'complete' AND ( expires_at IS NULL OR expires_at > %s )", $user_id, $mid, mgm_now() ) ); // phpcs:ignore
				if ( $auto ) {
					$wpdb->query( $wpdb->prepare( 'UPDATE ' . MGM_Transaction::table() . " SET expires_at = %s WHERE user_id = %d AND membership_id = %d AND gateway = 'automation' AND status = 'complete'", gmdate( 'Y-m-d H:i:s', time() - 1 ), $user_id, $mid ) ); // phpcs:ignore
					MGM_Member::sync_role( $user_id );
					do_action( 'mgm_membership_revoked', $user_id, $mid );
				}
			}
		}
	}
}

MGM_Automation::init();
