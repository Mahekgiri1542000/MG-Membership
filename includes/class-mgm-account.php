<?php
/**
 * Member account area, login, password reset, account actions.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Account {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ), 3 );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 20, 3 );
	}

	/**
	 * Account tabs – add-ons can register tabs (Courses, Events, Certificates…).
	 */
	public static function tabs() {
		return apply_filters(
			'mgm_account_tabs',
			array(
				'home'          => __( 'Dashboard', 'mg-membership' ),
				'subscriptions' => __( 'Subscriptions', 'mg-membership' ),
				'payments'      => __( 'Payments', 'mg-membership' ),
				'profile'       => __( 'Profile', 'mg-membership' ),
			)
		);
	}

	public static function tab_url( $tab ) {
		return add_query_arg( 'tab', $tab, mgm_page_url( 'account' ) );
	}

	public static function render() {
		if ( ! is_user_logged_in() ) {
			return self::render_login();
		}
		$tabs    = self::tabs();
		$current = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'home'; // phpcs:ignore
		$current = isset( $tabs[ $current ] ) ? $current : 'home';
		$user    = wp_get_current_user();

		ob_start();
		echo mgm_print_notices(); // phpcs:ignore
		echo '<div class="mgm-account"><nav class="mgm-account-nav">';
		foreach ( $tabs as $key => $label ) {
			printf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( self::tab_url( $key ) ),
				$key === $current ? 'is-active' : '',
				esc_html( $label )
			);
		}
		printf( '<a href="%s" class="mgm-logout">%s</a>', esc_url( wp_logout_url( home_url( '/' ) ) ), esc_html__( 'Log out', 'mg-membership' ) );
		echo '</nav><div class="mgm-account-content">';

		$template = 'account/' . $current . '.php';
		if ( file_exists( mgm_locate_template( $template ) ) ) {
			echo mgm_get_template( $template, array( 'user' => $user ) ); // phpcs:ignore
		}
		do_action( 'mgm_account_tab_' . $current, $user );
		echo '</div></div>';
		return ob_get_clean();
	}

	public static function render_login( $redirect = '' ) {
		if ( is_user_logged_in() ) {
			return '<p>' . sprintf(
				/* translators: 1: name 2: account url 3: logout url */
				wp_kses_post( __( 'You are logged in as %1$s. <a href="%2$s">My account</a> · <a href="%3$s">Log out</a>', 'mg-membership' ) ),
				esc_html( wp_get_current_user()->display_name ),
				esc_url( mgm_page_url( 'account' ) ),
				esc_url( wp_logout_url( home_url( '/' ) ) )
			) . '</p>';
		}
		if ( ! $redirect ) {
			$redirect = isset( $_GET['mgm_redirect'] ) ? esc_url_raw( wp_unslash( $_GET['mgm_redirect'] ) ) : ''; // phpcs:ignore
		}
		$mode = isset( $_GET['mgm_lost'] ) ? 'lost' : 'login'; // phpcs:ignore
		return mgm_print_notices() . mgm_get_template(
			'login.php',
			array(
				'redirect' => $redirect,
				'mode'     => $mode,
			)
		);
	}

	public static function login_redirect( $redirect_to, $requested, $user ) {
		if ( is_wp_error( $user ) || ! $user || user_can( $user, 'edit_posts' ) ) {
			return $redirect_to;
		}
		if ( $requested && false === strpos( $requested, 'wp-admin' ) ) {
			return $redirect_to;
		}
		switch ( mgm_option( 'login_redirect', 'account' ) ) {
			case 'home':
				return home_url( '/' );
			case 'referer':
				return $redirect_to;
		}
		return mgm_page_url( 'account' );
	}

	public static function handle_actions() {
		// Login form.
		if ( ! empty( $_POST['mgm_login'] ) && isset( $_POST['_mgm_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_mgm_nonce'] ), 'mgm_login' ) ) {
			$creds = array(
				'user_login'    => sanitize_user( wp_unslash( $_POST['log'] ?? '' ) ),
				'user_password' => (string) wp_unslash( $_POST['pwd'] ?? '' ), // phpcs:ignore
				'remember'      => ! empty( $_POST['rememberme'] ),
			);
			$user  = wp_signon( $creds, is_ssl() );
			if ( is_wp_error( $user ) ) {
				mgm_add_notice( 'mgm_locked' === $user->get_error_code() ? $user->get_error_message() : __( 'Invalid username/email or password.', 'mg-membership' ) );
				return;
			}
			$redirect = ! empty( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : self::login_redirect( mgm_page_url( 'account' ), '', $user );
			wp_safe_redirect( $redirect );
			exit;
		}

		// Lost password.
		if ( ! empty( $_POST['mgm_lost_password'] ) && isset( $_POST['_mgm_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_mgm_nonce'] ), 'mgm_lost' ) ) {
			$login = sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) );
			$user  = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
			// Same neutral message either way; silently stop sending after too many requests.
			if ( $user && MGM_Security_Core::hit( 'lost_pw' ) && MGM_Security_Core::hit( 'lost_pw', 'user:' . $user->ID ) ) {
				retrieve_password( $user->user_login );
			}
			mgm_add_notice( __( 'If an account exists, a password reset link has been emailed.', 'mg-membership' ), 'success' );
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}
		$user_id = get_current_user_id();

		// Profile update.
		if ( ! empty( $_POST['mgm_profile'] ) && isset( $_POST['_mgm_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_mgm_nonce'] ), 'mgm_profile' ) ) {
			$data  = array(
				'ID'           => $user_id,
				'first_name'   => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
				'last_name'    => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
				'display_name' => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
			);
			$email   = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
			$current = wp_get_current_user();
			$pass    = (string) wp_unslash( $_POST['new_pass'] ?? '' ); // phpcs:ignore
			// Changing the email or password needs the current password (protects against hijacked sessions).
			$sensitive = ( $email && strtolower( $email ) !== strtolower( $current->user_email ) ) || '' !== $pass;
			if ( $sensitive ) {
				$check = (string) wp_unslash( $_POST['current_pass'] ?? '' ); // phpcs:ignore
				if ( '' === $check || ! wp_check_password( $check, $current->user_pass, $current->ID ) ) {
					mgm_add_notice( __( 'Please enter your current password to change your email or password.', 'mg-membership' ) );
					return;
				}
			}
			if ( $email && is_email( $email ) ) {
				$owner = email_exists( $email );
				if ( $owner && (int) $owner !== $user_id ) {
					mgm_add_notice( __( 'That email is already used by another account.', 'mg-membership' ) );
					return;
				}
				$data['user_email'] = $email;
			}
			if ( $pass ) {
				if ( strlen( $pass ) < 8 ) {
					mgm_add_notice( __( 'Password must be at least 8 characters.', 'mg-membership' ) );
					return;
				}
				$data['user_pass'] = $pass;
			}
			$res = wp_update_user( $data );
			if ( is_wp_error( $res ) ) {
				mgm_add_notice( $res->get_error_message() );
			} else {
				if ( $pass ) {
					wp_set_auth_cookie( $user_id, true );
				}
				do_action( 'mgm_profile_updated', $user_id, $_POST ); // phpcs:ignore
				mgm_add_notice( __( 'Profile updated.', 'mg-membership' ), 'success' );
			}
			return;
		}

		$action = isset( $_GET['mgm_action'] ) ? sanitize_key( $_GET['mgm_action'] ) : ''; // phpcs:ignore
		if ( ! $action ) {
			return;
		}

		if ( 'cancel_sub' === $action && isset( $_GET['_wpnonce'], $_GET['sub'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'mgm_cancel_sub' ) ) {
			$sub = MGM_Subscription::get( absint( $_GET['sub'] ) );
			if ( $sub && (int) $sub->user_id === $user_id ) {
				$res = MGM_Subscription::cancel( $sub->id );
				if ( is_wp_error( $res ) ) {
					mgm_add_notice( $res->get_error_message() );
				} else {
					mgm_add_notice( __( 'Your subscription has been cancelled. You keep access until the end of the paid period.', 'mg-membership' ), 'success' );
					if ( 'paypal' === $sub->gateway ) {
						mgm_add_notice( __( 'Please also cancel the automatic payment inside your PayPal account.', 'mg-membership' ), 'warning' );
					}
				}
			}
		}

		if ( 'stripe_portal' === $action && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'mgm_stripe_portal' ) ) {
			$stripe = mgm()->gateway( 'stripe' );
			if ( $stripe ) {
				$stripe->portal_redirect( $user_id );
			}
		}

		do_action( 'mgm_account_action', $action, $user_id );
	}

	public static function cancel_url( $sub ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'mgm_action' => 'cancel_sub',
					'sub'        => $sub->id,
					'tab'        => 'subscriptions',
				),
				mgm_page_url( 'account' )
			),
			'mgm_cancel_sub'
		);
	}
}
