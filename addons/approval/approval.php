<?php
/**
 * Add-on: Admin approval of new members.
 *
 * A membership with "Require approval" grants nothing until an admin approves the member.
 * Pending membership IDs are stored in user meta `_mgm_pending_approval`.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Approval {

	public static function init() {
		add_action( 'mgm_membership_options_box', array( __CLASS__, 'field' ) );
		add_action( 'mgm_save_membership', array( __CLASS__, 'save' ) );
		add_action( 'mgm_membership_granted', array( __CLASS__, 'on_granted' ), 1, 3 );
		add_filter( 'mgm_active_membership_ids', array( __CLASS__, 'filter_active' ), 20, 2 );
		add_filter( 'mgm_txn_awaiting_approval', array( __CLASS__, 'txn_pending' ), 10, 2 );
		add_filter( 'mgm_email_templates', array( __CLASS__, 'emails' ) );
		add_action( 'mgm_account_dashboard', array( __CLASS__, 'account_notice' ), 1 );
		add_filter( 'mgm_dashboard_alerts', array( __CLASS__, 'dashboard_alert' ) );
		if ( is_admin() ) {
			add_action( 'mgm_admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'admin_post_mgm_approval', array( __CLASS__, 'handle' ) );
			add_action( 'mgm_admin_member_detail', array( __CLASS__, 'member_detail' ), 5 );
		}
	}

	public static function requires( $membership_id ) {
		return '1' === (string) get_post_meta( $membership_id, '_mgm_require_approval', true );
	}

	public static function pending( $user_id ) {
		return mgm_int_list( get_user_meta( $user_id, '_mgm_pending_approval', true ) );
	}

	private static function set_pending( $user_id, array $ids ) {
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( $ids ) {
			update_user_meta( $user_id, '_mgm_pending_approval', $ids );
		} else {
			delete_user_meta( $user_id, '_mgm_pending_approval' );
		}
		MGM_Member::flush_cache( $user_id );
	}

	public static function is_approved( $user_id, $membership_id ) {
		return (bool) get_user_meta( $user_id, '_mgm_approved_' . (int) $membership_id, true );
	}

	public static function field( $post ) {
		printf(
			'<p class="mgm-field"><label><input type="checkbox" name="mgm_require_approval" value="1" %s> %s</label></p>',
			checked( self::requires( $post->ID ), true, false ),
			esc_html__( 'New members need admin approval before they get access', 'mg-membership' )
		);
	}

	public static function save( $post_id ) {
		update_post_meta( $post_id, '_mgm_require_approval', empty( $_POST['mgm_require_approval'] ) ? '0' : '1' ); // phpcs:ignore -- nonce checked in membership save.
	}

	/**
	 * A payment / signup completed: hold it if the membership needs approval.
	 */
	public static function on_granted( $user_id, $membership_id, $txn ) {
		if ( ! self::requires( $membership_id ) || self::is_approved( $user_id, $membership_id ) ) {
			return;
		}
		// An admin granting access manually is an approval in itself.
		if ( $txn && in_array( $txn->txn_type, array( 'manual' ), true ) && current_user_can( mgm_admin_cap() ) ) {
			update_user_meta( $user_id, '_mgm_approved_' . $membership_id, time() );
			return;
		}
		$pending = self::pending( $user_id );
		if ( in_array( (int) $membership_id, $pending, true ) ) {
			return;
		}
		$pending[] = (int) $membership_id;
		self::set_pending( $user_id, $pending );
		MGM_Member::sync_role( $user_id );
		$vars = MGM_Emails::user_vars( $user_id, $membership_id );
		$vars['review_url'] = admin_url( 'admin.php?page=mgm-approvals' );
		$user = get_userdata( $user_id );
		if ( $user ) {
			MGM_Emails::send( 'approval_pending', $user->user_email, $vars );
		}
		MGM_Emails::send( 'admin_approval_needed', MGM_Emails::admin_email(), $vars );
		do_action( 'mgm_approval_pending', $user_id, $membership_id );
	}

	public static function filter_active( $ids, $user_id ) {
		$pending = self::pending( $user_id );
		return $pending ? array_values( array_diff( $ids, $pending ) ) : $ids;
	}

	public static function txn_pending( $is, $txn ) {
		return $is || in_array( (int) $txn->membership_id, self::pending( (int) $txn->user_id ), true );
	}

	public static function approve( $user_id, $membership_id ) {
		self::set_pending( $user_id, array_diff( self::pending( $user_id ), array( (int) $membership_id ) ) );
		update_user_meta( $user_id, '_mgm_approved_' . (int) $membership_id, time() );
		MGM_Member::sync_role( $user_id );
		$user = get_userdata( $user_id );
		if ( $user ) {
			MGM_Emails::send( 'approved', $user->user_email, MGM_Emails::user_vars( $user_id, $membership_id ) );
		}
		do_action( 'mgm_approval_approved', $user_id, $membership_id );
	}

	public static function deny( $user_id, $membership_id ) {
		self::set_pending( $user_id, array_diff( self::pending( $user_id ), array( (int) $membership_id ) ) );
		$vars = MGM_Emails::user_vars( $user_id, $membership_id );
		MGM_Member::revoke( $user_id, $membership_id );
		$user = get_userdata( $user_id );
		if ( $user ) {
			MGM_Emails::send( 'denied', $user->user_email, $vars );
		}
		do_action( 'mgm_approval_denied', $user_id, $membership_id );
	}

	public static function emails( $t ) {
		$t['approval_pending']      = array(
			'label'   => __( 'Membership awaiting approval (member)', 'mg-membership' ),
			'subject' => __( 'Your {membership} application was received', 'mg-membership' ),
			'body'    => __( "Hi {first_name},\n\nThanks for applying for {membership}. An administrator will review your application shortly and we will email you as soon as it is approved.\n\n{site_name}", 'mg-membership' ),
		);
		$t['admin_approval_needed'] = array(
			'label'   => __( 'New member needs approval (admin)', 'mg-membership' ),
			'subject' => __( '[{site_name}] {full_name} is waiting for approval', 'mg-membership' ),
			'body'    => __( "{full_name} ({email}) signed up for {membership} and is waiting for approval.\n\nReview: {review_url}", 'mg-membership' ),
		);
		$t['approved']              = array(
			'label'   => __( 'Membership approved (member)', 'mg-membership' ),
			'subject' => __( 'You are approved! Welcome to {membership}', 'mg-membership' ),
			'body'    => __( "Hi {first_name},\n\nGood news – your {membership} membership has been approved. Log in here: {account_url}\n\n{site_name}", 'mg-membership' ),
		);
		$t['denied']                = array(
			'label'   => __( 'Membership not approved (member)', 'mg-membership' ),
			'subject' => __( 'Update on your {membership} application', 'mg-membership' ),
			'body'    => __( "Hi {first_name},\n\nUnfortunately your application for {membership} was not approved. If you have questions, just reply to this email.\n\n{site_name}", 'mg-membership' ),
		);
		return $t;
	}

	public static function account_notice( $user ) {
		foreach ( self::pending( $user->ID ) as $mid ) {
			echo '<div class="mgm-notice mgm-notice-info">' . esc_html( sprintf( __( 'Your %s membership is awaiting approval. We will email you when it is approved.', 'mg-membership' ), get_the_title( $mid ) ) ) . '</div>';
		}
	}

	/* ------------------------------ Admin ------------------------------ */

	public static function all_pending() {
		global $wpdb;
		return $wpdb->get_results( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_mgm_pending_approval'" ); // phpcs:ignore
	}

	public static function dashboard_alert( $alerts ) {
		$n = 0;
		foreach ( self::all_pending() as $row ) {
			$n += count( mgm_int_list( maybe_unserialize( $row->meta_value ) ) );
		}
		if ( $n ) {
			$alerts[] = array( 'warning', sprintf( _n( '%d new member is waiting for approval', '%d new members are waiting for approval', $n, 'mg-membership' ), $n ), admin_url( 'admin.php?page=mgm-approvals' ), __( 'Review', 'mg-membership' ) );
		}
		return $alerts;
	}

	public static function menu( $cap ) {
		$n     = count( self::all_pending() );
		$label = __( 'Approvals', 'mg-membership' ) . ( $n ? ' <span class="awaiting-mod">' . (int) $n . '</span>' : '' );
		add_submenu_page( 'mgm', __( 'Approvals', 'mg-membership' ), $label, $cap, 'mgm-approvals', array( __CLASS__, 'page' ) );
	}

	public static function action_url( $do, $user_id, $membership_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=mgm_approval&do=' . $do . '&user=' . (int) $user_id . '&membership=' . (int) $membership_id ), 'mgm_approval' );
	}

	public static function page() {
		MGM_Admin::header( __( 'Approvals', 'mg-membership' ), array( 'desc' => __( 'Members who signed up for a membership that needs approval. They have no access until you approve them.', 'mg-membership' ) ) );
		echo '<div class="mgm-table-card"><table class="widefat mgm-table"><thead><tr><th>' . esc_html__( 'Member', 'mg-membership' ) . '</th><th>' . esc_html__( 'Membership', 'mg-membership' ) . '</th><th>' . esc_html__( 'Signed up', 'mg-membership' ) . '</th><th></th></tr></thead><tbody>';
		$rows = 0;
		foreach ( self::all_pending() as $row ) {
			$u = get_userdata( $row->user_id );
			if ( ! $u ) {
				continue;
			}
			foreach ( mgm_int_list( maybe_unserialize( $row->meta_value ) ) as $mid ) {
				++$rows;
				printf(
					'<tr><td><a class="mgm-person" href="%1$s">%2$s<span><strong>%3$s</strong><small>%4$s</small></span></a></td><td>%5$s</td><td>%6$s</td><td class="mgm-row-actions"><a class="mgm-btn mgm-btn-sm mgm-btn-primary" href="%7$s">%8$s</a> <a class="mgm-btn mgm-btn-sm mgm-btn-danger" href="%9$s" onclick="return confirm(\'%10$s\')">%11$s</a></td></tr>',
					esc_url( admin_url( 'admin.php?page=mgm-members&member=' . $u->ID ) ),
					get_avatar( $u->ID, 36, '', '', array( 'class' => 'mgm-avatar' ) ),
					esc_html( $u->display_name ),
					esc_html( $u->user_email ),
					esc_html( get_the_title( $mid ) ),
					esc_html( mysql2date( get_option( 'date_format' ), $u->user_registered ) ),
					esc_url( self::action_url( 'approve', $u->ID, $mid ) ),
					esc_html__( 'Approve', 'mg-membership' ),
					esc_url( self::action_url( 'deny', $u->ID, $mid ) ),
					esc_js( __( 'Deny this application? Their access will be removed.', 'mg-membership' ) ),
					esc_html__( 'Deny', 'mg-membership' )
				);
			}
		}
		if ( ! $rows ) {
			echo '<tr class="mgm-empty-row"><td colspan="4"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><strong>' . esc_html__( 'Nobody is waiting', 'mg-membership' ) . '</strong><span>' . esc_html__( 'New applications for memberships that need approval appear here.', 'mg-membership' ) . '</span></td></tr>';
		}
		echo '</tbody></table></div>';
		MGM_Admin::footer();
	}

	public static function member_detail( $user ) {
		$p = self::pending( $user->ID );
		if ( ! $p ) {
			return;
		}
		echo '<div class="mgm-card" style="border-color:#fedf89;background:#fffaeb"><strong>' . esc_html__( 'Awaiting approval:', 'mg-membership' ) . '</strong> ';
		foreach ( $p as $mid ) {
			echo esc_html( get_the_title( $mid ) ) . ' <a class="mgm-btn mgm-btn-sm mgm-btn-primary" href="' . esc_url( self::action_url( 'approve', $user->ID, $mid ) ) . '">' . esc_html__( 'Approve', 'mg-membership' ) . '</a> <a class="mgm-btn mgm-btn-sm mgm-btn-danger" href="' . esc_url( self::action_url( 'deny', $user->ID, $mid ) ) . '">' . esc_html__( 'Deny', 'mg-membership' ) . '</a> ';
		}
		echo '</div>';
	}

	public static function handle() {
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mg-membership' ) );
		}
		check_admin_referer( 'mgm_approval' );
		$uid = absint( $_GET['user'] ?? 0 );
		$mid = absint( $_GET['membership'] ?? 0 );
		$do  = sanitize_key( $_GET['do'] ?? '' );
		if ( 'approve' === $do ) {
			self::approve( $uid, $mid );
			$msg = __( 'Member approved and notified.', 'mg-membership' );
		} else {
			self::deny( $uid, $mid );
			$msg = __( 'Application denied and member notified.', 'mg-membership' );
		}
		wp_safe_redirect( add_query_arg( 'mgm_msg', rawurlencode( $msg ), wp_get_referer() ? remove_query_arg( 'mgm_msg', wp_get_referer() ) : admin_url( 'admin.php?page=mgm-approvals' ) ) );
		exit;
	}
}

MGM_Approval::init();
