<?php
/**
 * Privacy (GDPR / CCPA) and passwordless login.
 *
 * - Personal-data exporter & eraser registered with WordPress (Tools → Export/Erase Personal Data).
 * - Account "Privacy" tab: members request a copy of their data or account deletion (WordPress confirms by email).
 * - Magic-link login: single-use, 15-minute link by email. Not available for staff accounts.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Privacy {

	const MAGIC_TTL = 15 * MINUTE_IN_SECONDS;

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy_text' ) );
		add_filter( 'mgm_account_tabs', array( __CLASS__, 'tab' ), 99 );
		add_action( 'mgm_account_tab_privacy', array( __CLASS__, 'tab_content' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 2 );
		add_filter( 'mgm_email_templates', array( __CLASS__, 'emails' ) );
		add_action( 'mgm_login_form_after', array( __CLASS__, 'magic_link_form' ) );
	}

	/* --------------------------- Exporter --------------------------- */

	public static function register_exporter( $exporters ) {
		$exporters['mg-membership'] = array(
			'exporter_friendly_name' => __( 'MG Membership', 'mg-membership' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['mg-membership'] = array(
			'eraser_friendly_name' => __( 'MG Membership', 'mg-membership' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	private static function table_exists( $name ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', MGM_Install::table( $name ) ) );
	}

	public static function export( $email, $page = 1 ) {
		global $wpdb;
		$user  = get_user_by( 'email', $email );
		$items = array();
		if ( $user ) {
			foreach ( MGM_Transaction::query( array( 'user_id' => $user->ID, 'per_page' => 0 ) ) as $t ) {
				$items[] = array(
					'group_id'    => 'mgm-payments',
					'group_label' => __( 'Membership payments', 'mg-membership' ),
					'item_id'     => 'mgm-txn-' . $t->id,
					'data'        => array(
						array( 'name' => __( 'Invoice', 'mg-membership' ), 'value' => $t->trans_num ),
						array( 'name' => __( 'Membership', 'mg-membership' ), 'value' => get_the_title( $t->membership_id ) ),
						array( 'name' => __( 'Total', 'mg-membership' ), 'value' => mgm_price( $t->total ) ),
						array( 'name' => __( 'Status', 'mg-membership' ), 'value' => $t->status ),
						array( 'name' => __( 'Date', 'mg-membership' ), 'value' => $t->created_at ),
						array( 'name' => __( 'Access until', 'mg-membership' ), 'value' => $t->expires_at ? $t->expires_at : __( 'Lifetime', 'mg-membership' ) ),
					),
				);
			}
			foreach ( MGM_Subscription::for_user( $user->ID ) as $sub ) {
				$items[] = array(
					'group_id'    => 'mgm-subscriptions',
					'group_label' => __( 'Subscriptions', 'mg-membership' ),
					'item_id'     => 'mgm-sub-' . $sub->id,
					'data'        => array(
						array( 'name' => __( 'Membership', 'mg-membership' ), 'value' => get_the_title( $sub->membership_id ) ),
						array( 'name' => __( 'Status', 'mg-membership' ), 'value' => $sub->status ),
						array( 'name' => __( 'Price', 'mg-membership' ), 'value' => mgm_price( $sub->price ) . ' / ' . $sub->period . ' ' . $sub->period_unit ),
						array( 'name' => __( 'Started', 'mg-membership' ), 'value' => $sub->created_at ),
					),
				);
			}
			$profile = array();
			foreach ( (array) get_option( 'mgm_custom_fields', array() ) as $f ) {
				$v = get_user_meta( $user->ID, 'mgm_field_' . $f['key'], true );
				if ( '' !== (string) $v ) {
					$profile[] = array( 'name' => $f['label'], 'value' => $v );
				}
			}
			if ( get_user_meta( $user->ID, '_mgm_directory_optin', true ) ) {
				$profile[] = array( 'name' => __( 'Listed in member directory', 'mg-membership' ), 'value' => __( 'Yes', 'mg-membership' ) );
			}
			if ( $profile ) {
				$items[] = array( 'group_id' => 'mgm-profile', 'group_label' => __( 'Membership profile', 'mg-membership' ), 'item_id' => 'mgm-profile-' . $user->ID, 'data' => $profile );
			}
			if ( self::table_exists( 'course_progress' ) ) {
				foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . MGM_Install::table( 'course_progress' ) . ' WHERE user_id = %d', $user->ID ) ) as $r ) { // phpcs:ignore
					$items[] = array( 'group_id' => 'mgm-learning', 'group_label' => __( 'Course progress', 'mg-membership' ), 'item_id' => 'mgm-lesson-' . $r->id, 'data' => array( array( 'name' => __( 'Lesson', 'mg-membership' ), 'value' => get_the_title( $r->lesson_id ) ), array( 'name' => __( 'Course', 'mg-membership' ), 'value' => get_the_title( $r->course_id ) ), array( 'name' => __( 'Completed', 'mg-membership' ), 'value' => $r->completed_at ) ) );
				}
			}
			if ( self::table_exists( 'activity' ) ) {
				foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . MGM_Install::table( 'activity' ) . ' WHERE user_id = %d ORDER BY id DESC LIMIT 500', $user->ID ) ) as $r ) { // phpcs:ignore
					$items[] = array( 'group_id' => 'mgm-activity', 'group_label' => __( 'Account activity', 'mg-membership' ), 'item_id' => 'mgm-act-' . $r->id, 'data' => array( array( 'name' => __( 'Activity', 'mg-membership' ), 'value' => $r->message ), array( 'name' => __( 'IP address', 'mg-membership' ), 'value' => $r->ip ), array( 'name' => __( 'Date', 'mg-membership' ), 'value' => $r->created_at ) ) );
				}
			}
		}
		if ( self::table_exists( 'event_registrations' ) ) {
			// Match by email AND by account – a member may have changed their email since registering.
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . MGM_Install::table( 'event_registrations' ) . ' WHERE email = %s OR ( user_id > 0 AND user_id = %d )', $email, $user ? $user->ID : 0 ) ) as $r ) { // phpcs:ignore
				$items[] = array( 'group_id' => 'mgm-events', 'group_label' => __( 'Event registrations', 'mg-membership' ), 'item_id' => 'mgm-reg-' . $r->id, 'data' => array( array( 'name' => __( 'Event', 'mg-membership' ), 'value' => get_the_title( $r->event_id ) ), array( 'name' => __( 'Name', 'mg-membership' ), 'value' => $r->name ), array( 'name' => __( 'Phone', 'mg-membership' ), 'value' => $r->phone ), array( 'name' => __( 'Status', 'mg-membership' ), 'value' => $r->status ), array( 'name' => __( 'Registered', 'mg-membership' ), 'value' => $r->created_at ) ) );
			}
		}
		return array( 'data' => $items, 'done' => true );
	}

	/**
	 * Erase personal data. Payment records are kept (legal / tax obligations) but no longer show personal details.
	 */
	public static function erase( $email, $page = 1 ) {
		global $wpdb;
		$removed  = false;
		$retained = false;
		$messages = array();
		$user     = get_user_by( 'email', $email );
		if ( $user ) {
			foreach ( (array) get_option( 'mgm_custom_fields', array() ) as $f ) {
				$removed = delete_user_meta( $user->ID, 'mgm_field_' . $f['key'] ) || $removed;
			}
			foreach ( array( '_mgm_directory_optin', 'description', '_mgm_admin_notes' ) as $k ) {
				$removed = delete_user_meta( $user->ID, $k ) || $removed;
			}
			foreach ( array( 'activity', 'course_progress', 'quiz_attempts' ) as $t ) {
				if ( self::table_exists( $t ) ) {
					$removed = (bool) $wpdb->delete( MGM_Install::table( $t ), array( 'user_id' => $user->ID ) ) || $removed; // phpcs:ignore
				}
			}
			if ( MGM_Transaction::query( array( 'user_id' => $user->ID, 'count' => true ) ) ) {
				$retained   = true;
				$messages[] = __( 'Membership payment records are kept for accounting and tax purposes.', 'mg-membership' );
			}
		}
		if ( self::table_exists( 'event_registrations' ) ) {
			$n = $wpdb->query( $wpdb->prepare( 'UPDATE ' . MGM_Install::table( 'event_registrations' ) . " SET name = %s, email = %s, phone = '' WHERE email = %s OR ( user_id > 0 AND user_id = %d )", __( 'Deleted user', 'mg-membership' ), 'deleted-' . wp_generate_password( 8, false ) . '@invalid', $email, $user ? $user->ID : 0 ) ); // phpcs:ignore
			$removed = $n > 0 || $removed;
		}
		return array( 'items_removed' => $removed, 'items_retained' => $retained, 'messages' => $messages, 'done' => true );
	}

	public static function policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			'MG Membership',
			wp_kses_post(
				'<p>' . __( 'When you join, we store your name, email address, chosen membership, payment records (amount, date, invoice number – not card details, which are handled by Stripe or PayPal), course progress, event registrations, any profile fields you fill in and a log of account activity including IP addresses. We use this to provide your membership. Payment records are kept as required for accounting. You can request a copy of your data or ask us to erase it from your account’s Privacy tab.', 'mg-membership' ) . '</p>'
			)
		);
	}

	/* ------------------------ Account tab ------------------------ */

	public static function tab( $tabs ) {
		$tabs['privacy'] = __( 'Privacy', 'mg-membership' );
		return $tabs;
	}

	public static function tab_content( $user ) {
		echo '<h2>' . esc_html__( 'Privacy', 'mg-membership' ) . '</h2>';
		echo '<p>' . esc_html__( 'You can get a copy of the personal data we hold about you, or ask us to delete your account data. For your security we will email you a confirmation link first.', 'mg-membership' ) . '</p>';
		foreach ( array( 'export_personal_data' => __( 'Email me a copy of my data', 'mg-membership' ), 'remove_personal_data' => __( 'Request deletion of my data', 'mg-membership' ) ) as $action => $label ) {
			echo '<form method="post" class="mgm-inline-form" style="display:inline-block;margin:0 .5em .5em 0">';
			wp_nonce_field( 'mgm_privacy', '_mgm_nonce' );
			echo '<input type="hidden" name="mgm_privacy_request" value="' . esc_attr( $action ) . '"><button class="mgm-button' . ( 'remove_personal_data' === $action ? ' mgm-button-secondary' : '' ) . '" ' . ( 'remove_personal_data' === $action ? 'onclick="return confirm(\'' . esc_js( __( 'Request deletion of your data? An administrator will process it after you confirm by email.', 'mg-membership' ) ) . '\')"' : '' ) . '>' . esc_html( $label ) . '</button></form>';
		}
	}

	/* ------------------------- Handlers -------------------------- */

	public static function handle() {
		// Privacy requests from the account tab.
		if ( ! empty( $_POST['mgm_privacy_request'] ) && is_user_logged_in() && isset( $_POST['_mgm_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_mgm_nonce'] ), 'mgm_privacy' ) ) {
			$action = sanitize_key( $_POST['mgm_privacy_request'] );
			if ( in_array( $action, array( 'export_personal_data', 'remove_personal_data' ), true ) ) {
				$user = wp_get_current_user();
				$rid  = wp_create_user_request( $user->user_email, $action );
				if ( is_wp_error( $rid ) ) {
					mgm_add_notice( 'duplicate_request' === $rid->get_error_code() ? __( 'You already have a pending request. Please check your email to confirm it.', 'mg-membership' ) : $rid->get_error_message() );
				} else {
					wp_send_user_request( $rid );
					mgm_add_notice( __( 'Check your email: we sent you a link to confirm the request.', 'mg-membership' ), 'success' );
				}
			}
			return;
		}

		// Magic link: request.
		if ( ! empty( $_POST['mgm_magic_request'] ) && isset( $_POST['_mgm_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_mgm_nonce'] ), 'mgm_magic' ) ) {
			$email = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
			$user  = is_email( $email ) ? get_user_by( 'email', $email ) : false;
			if ( $user && self::magic_allowed( $user ) && MGM_Security_Core::hit( 'magic_link' ) && MGM_Security_Core::hit( 'magic_link', 'user:' . $user->ID ) ) {
				self::send_magic_link( $user, isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '' );
			}
			mgm_add_notice( __( 'If an account exists for that email, a login link is on its way. It works once and expires in 15 minutes.', 'mg-membership' ), 'success' );
			return;
		}

		// Magic link: use.
		if ( isset( $_GET['mgm_magic'] ) ) { // phpcs:ignore
			$raw = sanitize_text_field( wp_unslash( $_GET['mgm_magic'] ) ); // phpcs:ignore
			$uid = self::use_magic_token( $raw );
			if ( $uid ) {
				wp_set_current_user( $uid );
				wp_set_auth_cookie( $uid, false, is_ssl() );
				do_action( 'wp_login', get_userdata( $uid )->user_login, get_userdata( $uid ) );
				$to = get_user_meta( $uid, '_mgm_magic_redirect', true );
				delete_user_meta( $uid, '_mgm_magic_redirect' );
				wp_safe_redirect( $to ? $to : mgm_page_url( 'account' ) );
				exit;
			}
			wp_safe_redirect( add_query_arg( 'mgm_magic_invalid', 1, mgm_page_url( 'login' ) ) );
			exit;
		}
		if ( isset( $_GET['mgm_magic_invalid'] ) ) { // phpcs:ignore
			mgm_add_notice( __( 'That login link is invalid or has expired. Please request a new one.', 'mg-membership' ) );
		}
	}

	public static function magic_allowed( $user ) {
		$on = '0' !== (string) mgm_option( 'magic_link_login', '1' );
		// Staff accounts must use their password (and any 2FA they have).
		return (bool) apply_filters( 'mgm_magic_link_allowed', $on && ! user_can( $user, 'edit_posts' ), $user );
	}

	public static function send_magic_link( $user, $redirect = '' ) {
		$token = bin2hex( random_bytes( 32 ) );
		update_user_meta(
			$user->ID,
			'_mgm_magic',
			array(
				'hash' => hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ),
				'exp'  => time() + self::MAGIC_TTL,
			)
		);
		$redirect = $redirect ? wp_validate_redirect( $redirect, '' ) : '';
		if ( $redirect ) {
			update_user_meta( $user->ID, '_mgm_magic_redirect', $redirect );
		}
		$url = add_query_arg( 'mgm_magic', $user->ID . '.' . $token, home_url( '/' ) );
		MGM_Emails::send( 'magic_link', $user->user_email, array_merge( MGM_Emails::user_vars( $user->ID ), array( 'login_link' => $url ) ) );
		return $url;
	}

	/**
	 * Validate and consume a token. Returns the user ID or 0.
	 */
	public static function use_magic_token( $raw ) {
		if ( ! preg_match( '/^(\d+)\.([a-f0-9]{64})$/', (string) $raw, $m ) ) {
			return 0;
		}
		$uid  = (int) $m[1];
		$data = get_user_meta( $uid, '_mgm_magic', true );
		$user = get_userdata( $uid );
		if ( ! $user || ! is_array( $data ) || empty( $data['hash'] ) || time() > (int) $data['exp'] || ! self::magic_allowed( $user ) ) {
			return 0;
		}
		if ( ! hash_equals( $data['hash'], hash_hmac( 'sha256', $m[2], wp_salt( 'auth' ) ) ) ) {
			return 0;
		}
		delete_user_meta( $uid, '_mgm_magic' ); // Single use.
		return $uid;
	}

	public static function magic_link_form( $redirect ) {
		if ( '0' === (string) mgm_option( 'magic_link_login', '1' ) ) {
			return;
		}
		echo '<details class="mgm-magic"><summary>' . esc_html__( 'Prefer not to type a password? Email me a login link', 'mg-membership' ) . '</summary><form method="post" class="mgm-form">';
		wp_nonce_field( 'mgm_magic', '_mgm_nonce' );
		echo '<input type="hidden" name="mgm_magic_request" value="1"><input type="hidden" name="redirect_to" value="' . esc_attr( $redirect ) . '"><p><label for="mgm-magic-email">' . esc_html__( 'Email', 'mg-membership' ) . '</label><input type="email" id="mgm-magic-email" name="user_email" required autocomplete="email"></p><p><button type="submit" class="mgm-button mgm-button-secondary mgm-button-block">' . esc_html__( 'Send me a login link', 'mg-membership' ) . '</button></p></form></details>';
	}

	public static function emails( $t ) {
		$t['magic_link'] = array(
			'label'   => __( 'Login link (member)', 'mg-membership' ),
			'subject' => __( 'Your login link for {site_name}', 'mg-membership' ),
			'body'    => __( "Hi {first_name},\n\nClick to log in: {login_link}\n\nThe link works once and expires in 15 minutes. If you didn't ask for it, you can ignore this email.\n\n{site_name}", 'mg-membership' ),
		);
		$t['renewal_reminder'] = array(
			'label'   => __( 'Upcoming renewal reminder (member)', 'mg-membership' ),
			'subject' => __( 'Your {membership} renews on {renewal_date}', 'mg-membership' ),
			'body'    => __( "Hi {first_name},\n\nJust a heads-up: your {membership} subscription renews on {renewal_date} for {amount}.\n\nNothing to do if you'd like to continue. To change or cancel, visit {account_url}\n\n{site_name}", 'mg-membership' ),
		);
		return $t;
	}
}
