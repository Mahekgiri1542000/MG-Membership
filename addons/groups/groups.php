<?php
/**
 * Add-on: Corporate / Group accounts.
 *
 * A membership with "seats" makes the buyer a group owner. The owner invites
 * team members, who inherit the membership while the owner's access is active.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Groups {

	public static function init() {
		add_action( 'mgm_membership_options_box', array( __CLASS__, 'field' ) );
		add_action( 'mgm_save_membership', array( __CLASS__, 'save' ) );
		add_filter( 'mgm_active_membership_ids', array( __CLASS__, 'inherit' ), 10, 2 );
		add_filter( 'mgm_account_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'mgm_account_tab_team', array( __CLASS__, 'tab_content' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 4 );
		add_filter( 'mgm_email_templates', array( __CLASS__, 'emails' ) );
		add_filter( 'mgm_membership_start', array( __CLASS__, 'seat_start' ), 10, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'accept_invite' ), 3 );
	}

	/**
	 * Team seats have no payment of their own: drip counts from the day they joined the team.
	 */
	public static function seat_start( $start, $user_id, $membership_id ) {
		if ( $start ) {
			return $start;
		}
		if ( (int) get_user_meta( $user_id, '_mgm_group_membership', true ) === (int) $membership_id ) {
			return (int) get_user_meta( $user_id, '_mgm_group_joined', true );
		}
		return $start;
	}

	/**
	 * Existing accounts must accept an invitation (logged in as themselves) before joining a team.
	 */
	public static function accept_invite() {
		if ( empty( $_GET['mgm_team_invite'] ) ) { // phpcs:ignore
			return;
		}
		$token = sanitize_key( wp_unslash( $_GET['mgm_team_invite'] ) ); // phpcs:ignore
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( add_query_arg( 'mgm_redirect', rawurlencode( add_query_arg( 'mgm_team_invite', $token, home_url( '/' ) ) ), mgm_page_url( 'login' ) ) );
			exit;
		}
		$uid    = get_current_user_id();
		$invite = get_user_meta( $uid, '_mgm_group_invite', true );
		if ( is_array( $invite ) && ! empty( $invite['token'] ) && hash_equals( $invite['token'], $token ) && time() < (int) $invite['exp'] && ! get_user_meta( $uid, '_mgm_group_parent', true ) ) {
			$owner = (int) $invite['owner'];
			$mid   = (int) $invite['membership'];
			delete_user_meta( $uid, '_mgm_group_invite' );
			if ( in_array( $mid, self::owned_groups( $owner ), true ) && count( self::children( $owner, $mid ) ) < self::seats( $mid ) ) {
				update_user_meta( $uid, '_mgm_group_parent', $owner );
				update_user_meta( $uid, '_mgm_group_membership', $mid );
				update_user_meta( $uid, '_mgm_group_joined', time() );
				MGM_Member::sync_role( $uid );
				mgm_add_notice( sprintf( __( 'You joined the team and now have access to %s.', 'mg-membership' ), get_the_title( $mid ) ), 'success' );
			}
		}
		wp_safe_redirect( mgm_page_url( 'account' ) );
		exit;
	}

	public static function seats( $membership_id ) {
		return (int) get_post_meta( $membership_id, '_mgm_group_seats', true );
	}

	public static function field( $post ) {
		echo '<p class="mgm-field"><label>' . esc_html__( 'Group seats (0 = individual membership). Buyer can invite this many team members.', 'mg-membership' ) . '</label><input type="number" min="0" name="mgm_group_seats" value="' . esc_attr( self::seats( $post->ID ) ) . '" style="width:100px"></p>';
	}

	public static function save( $post_id ) {
		update_post_meta( $post_id, '_mgm_group_seats', absint( $_POST['mgm_group_seats'] ?? 0 ) ); // phpcs:ignore -- nonce verified in membership save.
	}

	/**
	 * Group memberships the user owns (active, with seats).
	 *
	 * @return int[]
	 */
	public static function owned_groups( $user_id ) {
		static $guard = false;
		if ( $guard ) {
			return array();
		}
		$guard = true;
		$ids   = MGM_Member::active_membership_ids( $user_id );
		$guard = false;
		return array_values(
			array_filter(
				$ids,
				function ( $mid ) {
					return self::seats( $mid ) > 0;
				}
			)
		);
	}

	public static function children( $owner_id, $membership_id ) {
		return get_users(
			array(
				'meta_query' => array( // phpcs:ignore
					array( 'key' => '_mgm_group_parent', 'value' => $owner_id ),
					array( 'key' => '_mgm_group_membership', 'value' => $membership_id ),
				),
			)
		);
	}

	public static function inherit( $ids, $user_id ) {
		static $depth = 0;
		if ( $depth > 0 ) {
			return $ids;
		}
		$parent = (int) get_user_meta( $user_id, '_mgm_group_parent', true );
		$mid    = (int) get_user_meta( $user_id, '_mgm_group_membership', true );
		if ( $parent && $mid ) {
			++$depth;
			$parent_ids = MGM_Member::active_membership_ids( $parent );
			--$depth;
			if ( in_array( $mid, $parent_ids, true ) ) {
				$ids[] = $mid;
			}
		}
		return $ids;
	}

	public static function tab( $tabs ) {
		if ( is_user_logged_in() && self::owned_groups( get_current_user_id() ) ) {
			$tabs['team'] = __( 'Team', 'mg-membership' );
		}
		return $tabs;
	}

	public static function emails( $t ) {
		$t['group_invite'] = array(
			'label'   => __( 'Team invitation', 'mg-membership' ),
			'subject' => __( '{owner} added you to {membership}', 'mg-membership' ),
			'body'    => __( "Hi,\n\n{owner} has given you access to {membership} on {site_name}.\n\nLog in here: {login_url}\n{password_note}\n\n{site_name}", 'mg-membership' ),
		);
		return $t;
	}

	public static function tab_content( $user ) {
		echo '<h2>' . esc_html__( 'Team members', 'mg-membership' ) . '</h2>';
		foreach ( self::owned_groups( $user->ID ) as $mid ) {
			$kids  = self::children( $user->ID, $mid );
			$seats = self::seats( $mid );
			echo '<div class="mgm-panel-front"><h3>' . esc_html( get_the_title( $mid ) ) . ' <small>(' . esc_html( sprintf( __( '%1$d of %2$d seats used', 'mg-membership' ), count( $kids ), $seats ) ) . ')</small></h3>';
			echo '<table class="mgm-table"><tbody>';
			foreach ( $kids as $k ) {
				echo '<tr><td>' . esc_html( $k->display_name ) . '</td><td>' . esc_html( $k->user_email ) . '</td><td><form method="post">';
				wp_nonce_field( 'mgm_team', '_mgm_nonce' );
				echo '<input type="hidden" name="membership" value="' . (int) $mid . '"><input type="hidden" name="member" value="' . (int) $k->ID . '"><button class="mgm-link mgm-link-danger" name="mgm_team_action" value="remove">' . esc_html__( 'Remove', 'mg-membership' ) . '</button></form></td></tr>';
			}
			if ( ! $kids ) {
				echo '<tr><td>' . esc_html__( 'No team members yet.', 'mg-membership' ) . '</td></tr>';
			}
			echo '</tbody></table>';
			if ( count( $kids ) < $seats ) {
				echo '<form method="post" class="mgm-form mgm-inline">';
				wp_nonce_field( 'mgm_team', '_mgm_nonce' );
				echo '<input type="hidden" name="membership" value="' . (int) $mid . '"><input type="email" name="email" required placeholder="' . esc_attr__( 'colleague@company.com', 'mg-membership' ) . '"> <button class="mgm-button" name="mgm_team_action" value="add">' . esc_html__( 'Add member', 'mg-membership' ) . '</button></form>';
			}
			echo '</div>';
		}
	}

	public static function handle() {
		if ( empty( $_POST['mgm_team_action'] ) || ! is_user_logged_in() || ! isset( $_POST['_mgm_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_nonce'] ), 'mgm_team' ) ) {
			return;
		}
		$owner = get_current_user_id();
		$mid   = absint( $_POST['membership'] ?? 0 );
		if ( ! in_array( $mid, self::owned_groups( $owner ), true ) ) {
			return;
		}
		$action = sanitize_key( $_POST['mgm_team_action'] );

		if ( 'add' === $action ) {
			if ( count( self::children( $owner, $mid ) ) >= self::seats( $mid ) ) {
				mgm_add_notice( __( 'All seats are in use.', 'mg-membership' ) );
				return;
			}
			$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
			if ( ! is_email( $email ) ) {
				mgm_add_notice( __( 'Please enter a valid email.', 'mg-membership' ) );
				return;
			}
			$user = get_user_by( 'email', $email );
			$note = '';
			if ( ! $user ) {
				$uid = wp_insert_user(
					array(
						'user_login' => sanitize_user( current( explode( '@', $email ) ) . wp_rand( 100, 999 ), true ),
						'user_email' => $email,
						'user_pass'  => wp_generate_password( 20 ),
					)
				);
				if ( is_wp_error( $uid ) ) {
					mgm_add_notice( $uid->get_error_message() );
					return;
				}
				$user = get_userdata( $uid );
				$key  = get_password_reset_key( $user );
				$note = is_wp_error( $key ) ? '' : sprintf( __( 'Set your password: %s', 'mg-membership' ), network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' ) );
			} else {
				if ( (int) get_user_meta( $user->ID, '_mgm_group_parent', true ) || user_can( $user, 'edit_posts' ) || $user->ID === $owner ) {
					mgm_add_notice( __( 'This person can’t be added to your team.', 'mg-membership' ) );
					return;
				}
				// Existing account: send an invitation they must accept while logged in.
				$token = strtolower( wp_generate_password( 32, false ) );
				update_user_meta( $user->ID, '_mgm_group_invite', array( 'token' => $token, 'owner' => $owner, 'membership' => $mid, 'exp' => time() + 7 * DAY_IN_SECONDS ) );
				MGM_Emails::send(
					'group_invite',
					$email,
					array(
						'owner'         => wp_get_current_user()->display_name,
						'membership'    => get_the_title( $mid ),
						'password_note' => sprintf( __( 'Accept the invitation: %s', 'mg-membership' ), add_query_arg( 'mgm_team_invite', $token, home_url( '/' ) ) ),
					)
				);
				mgm_add_notice( __( 'Invitation sent. They will join your team once they accept it.', 'mg-membership' ), 'success' );
				return;
			}
			update_user_meta( $user->ID, '_mgm_group_parent', $owner );
			update_user_meta( $user->ID, '_mgm_group_membership', $mid );
			update_user_meta( $user->ID, '_mgm_group_joined', time() );
			MGM_Member::sync_role( $user->ID );
			$o = wp_get_current_user();
			MGM_Emails::send(
				'group_invite',
				$email,
				array(
					'owner'         => $o->display_name,
					'membership'    => get_the_title( $mid ),
					'password_note' => $note,
				)
			);
			mgm_add_notice( __( 'Team member added and notified.', 'mg-membership' ), 'success' );
		}

		if ( 'remove' === $action ) {
			$kid = absint( $_POST['member'] ?? 0 );
			if ( (int) get_user_meta( $kid, '_mgm_group_parent', true ) === $owner ) {
				delete_user_meta( $kid, '_mgm_group_parent' );
				delete_user_meta( $kid, '_mgm_group_membership' );
				MGM_Member::sync_role( $kid );
				mgm_add_notice( __( 'Team member removed.', 'mg-membership' ), 'success' );
			}
		}
	}
}

MGM_Groups::init();
