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
		$classes = array(
			'mgm-account',
			'mgm-account--' . ( 'tabs' === MGM_Appearance::get( 'acc_layout' ) ? 'tabs' : 'sidebar' ),
			'mgm-tab-' . $current,
		);
		if ( MGM_Appearance::on( 'acc_wide' ) && (int) MGM_Appearance::get( 'ui_width' ) > 0 ) {
			$classes[] = 'is-wide';
		}
		if ( ! MGM_Appearance::on( 'acc_show_icons' ) ) {
			$classes[] = 'no-icons';
		}

		ob_start();
		echo '<div class="' . esc_attr( implode( ' ', apply_filters( 'mgm_account_classes', $classes, $current ) ) ) . '">';
		echo '<aside class="mgm-account-side">';
		if ( MGM_Appearance::on( 'acc_show_header' ) ) {
			self::user_card( $user );
		}
		echo '<nav class="mgm-account-nav" aria-label="' . esc_attr__( 'Account menu', 'mg-membership' ) . '">';
		foreach ( $tabs as $key => $label ) {
			printf(
				'<a href="%1$s" class="mgm-nav-%2$s%3$s"%4$s>%5$s<span>%6$s</span></a>',
				esc_url( self::tab_url( $key ) ),
				esc_attr( $key ),
				$key === $current ? ' is-active' : '',
				$key === $current ? ' aria-current="page"' : '',
				self::icon( $key ), // phpcs:ignore -- static SVG markup.
				esc_html( $label )
			);
		}
		printf(
			'<a href="%1$s" class="mgm-logout">%2$s<span>%3$s</span></a>',
			esc_url( wp_logout_url( home_url( '/' ) ) ),
			self::icon( 'logout' ), // phpcs:ignore
			esc_html( MGM_Appearance::get( 'acc_logout_label' ) )
		);
		echo '</nav></aside><div class="mgm-account-content">';
		echo mgm_print_notices(); // phpcs:ignore

		// Section header: greeting on the dashboard, the menu label everywhere else.
		if ( 'home' === $current ) {
			$title = MGM_Appearance::text( 'acc_greeting', $user );
			$desc  = MGM_Appearance::text( 'acc_intro', $user );
		} else {
			$title = $tabs[ $current ];
			$desc  = self::tab_description( $current );
		}
		echo '<header class="mgm-account-head"><h2 class="mgm-account-title">' . esc_html( $title ) . '</h2>';
		if ( '' !== trim( (string) $desc ) ) {
			echo '<p class="mgm-account-desc">' . esc_html( $desc ) . '</p>';
		}
		echo '</header>';

		$template = 'account/' . $current . '.php';
		if ( file_exists( mgm_locate_template( $template ) ) ) {
			echo mgm_get_template( $template, array( 'user' => $user ) ); // phpcs:ignore
		}
		do_action( 'mgm_account_tab_' . $current, $user );
		echo '</div></div>';
		return ob_get_clean();
	}

	/** Avatar, name, email and membership badge shown above the menu. */
	public static function user_card( WP_User $user ) {
		$active = MGM_Member::active_membership_ids( $user->ID );
		echo '<div class="mgm-account-user">';
		echo get_avatar( $user->ID, 96, '', '', array( 'class' => 'mgm-account-avatar' ) ); // phpcs:ignore
		echo '<div class="mgm-account-user-info"><strong>' . esc_html( $user->display_name ) . '</strong><span>' . esc_html( $user->user_email ) . '</span>';
		if ( $active ) {
			echo '<em class="mgm-account-plan">' . esc_html( get_the_title( $active[0] ) ) . ( count( $active ) > 1 ? ' +' . ( count( $active ) - 1 ) : '' ) . '</em>';
		}
		echo '</div></div>';
	}

	/** Short line under each section title. Filter: mgm_account_tab_descriptions. */
	public static function tab_description( $tab ) {
		$d = apply_filters(
			'mgm_account_tab_descriptions',
			array(
				'courses'       => __( 'Pick up where you left off.', 'mg-membership' ),
				'events'        => __( 'Seminars and events you have registered for.', 'mg-membership' ),
				'subscriptions' => __( 'Manage your plans, renewals and payment details.', 'mg-membership' ),
				'payments'      => __( 'Your payment history and receipts.', 'mg-membership' ),
				'downloads'     => __( 'Files included with your membership.', 'mg-membership' ),
				'profile'       => __( 'Update your personal details and password.', 'mg-membership' ),
				'privacy'       => __( 'Download or delete the personal data we hold about you.', 'mg-membership' ),
				'team'          => __( 'Invite people to share your membership.', 'mg-membership' ),
			)
		);
		return $d[ $tab ] ?? '';
	}

	/** Menu icons (inline SVG, inherit the text colour). Filter: mgm_account_icons. */
	public static function icon( $key ) {
		$paths = apply_filters(
			'mgm_account_icons',
			array(
				'home'          => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
				'courses'       => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/>',
				'events'        => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
				'subscriptions' => '<path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 12a9 9 0 0 1 15.5-6.2L21 8"/><path d="M21 3v5h-5M3 21v-5h5"/>',
				'payments'      => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
				'downloads'     => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/>',
				'profile'       => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
				'privacy'       => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
				'team'          => '<circle cx="9" cy="8" r="3.5"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M22 21a7 7 0 0 0-4.5-6.5"/>',
				'logout'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
				'membership'    => '<path d="M12 2l3 6.5 7 .9-5.1 4.8 1.3 7L12 17.8 5.8 21.2l1.3-7L2 9.4l7-.9z"/>',
				'calendar'      => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
				'clock'         => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
				'default'       => '<circle cx="12" cy="12" r="3.5"/>',
			)
		);
		$path = $paths[ $key ] ?? $paths['default'];
		return '<svg class="mgm-ico" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}

	/** Friendly empty state used by account sections. */
	public static function empty_state( $title, $text = '', $button = '', $url = '', $icon = 'default' ) {
		$out = '<div class="mgm-empty"><span class="mgm-empty-icon">' . self::icon( $icon ) . '</span><h3>' . esc_html( $title ) . '</h3>';
		if ( $text ) {
			$out .= '<p>' . esc_html( $text ) . '</p>';
		}
		if ( $button && $url ) {
			$out .= '<a class="mgm-button" href="' . esc_url( $url ) . '">' . esc_html( $button ) . '</a>';
		}
		return $out . '</div>';
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
			// Required fields set in MG Membership → Forms.
			$invalid = MGM_Forms::validate( 'profile' );
			if ( $invalid->has_errors() ) {
				foreach ( $invalid->get_error_messages() as $m ) {
					mgm_add_notice( $m );
				}
				return;
			}
			// Only fields that are on the form are updated (a hidden field must not wipe saved data).
			$data = array( 'ID' => $user_id );
			foreach ( array( 'first_name', 'last_name', 'display_name' ) as $k ) {
				if ( isset( $_POST[ $k ] ) ) {
					$data[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ) );
				}
			}
			if ( isset( $data['display_name'] ) && '' === $data['display_name'] ) {
				unset( $data['display_name'] );
			}
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
