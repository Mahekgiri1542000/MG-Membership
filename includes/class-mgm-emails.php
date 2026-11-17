<?php
/**
 * Transactional emails with editable templates & merge tags.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Emails {

	public static function init() {
		add_action( 'mgm_transaction_completed', array( __CLASS__, 'on_payment' ) );
		add_action( 'mgm_subscription_status_changed', array( __CLASS__, 'on_sub_status' ), 10, 3 );
		add_action( 'mgm_membership_expiring', array( __CLASS__, 'on_expiring' ), 10, 3 );
		add_action( 'mgm_new_member_signup', array( __CLASS__, 'on_signup' ), 10, 2 );
	}

	public static function templates() {
		return apply_filters(
			'mgm_email_templates',
			array(
				'welcome'        => array(
					'label'   => __( 'Welcome email (member)', 'mg-membership' ),
					'subject' => __( 'Welcome to {site_name}!', 'mg-membership' ),
					'body'    => __( "Hi {first_name},\n\nThanks for joining {membership}. You can access your account here: {account_url}\n\nUsername: {username}\n\n— {site_name}", 'mg-membership' ),
				),
				'receipt'        => array(
					'label'   => __( 'Payment receipt (member)', 'mg-membership' ),
					'subject' => __( 'Your payment receipt from {site_name}', 'mg-membership' ),
					'body'    => __( "Hi {first_name},\n\nWe received your payment of {amount} for {membership}.\nInvoice #: {trans_num}\nDate: {date}\n\nThank you!\n{site_name}", 'mg-membership' ),
				),
				'admin_signup'   => array(
					'label'   => __( 'New signup (admin)', 'mg-membership' ),
					'subject' => __( '[{site_name}] New member: {username}', 'mg-membership' ),
					'body'    => __( "A new member signed up.\n\nName: {full_name}\nEmail: {email}\nMembership: {membership}\nAmount: {amount}", 'mg-membership' ),
				),
				'cancelled'      => array(
					'label'   => __( 'Subscription cancelled (member)', 'mg-membership' ),
					'subject' => __( 'Your {membership} subscription was cancelled', 'mg-membership' ),
					'body'    => __( "Hi {first_name},\n\nYour subscription to {membership} has been cancelled. You will keep access until {expires}.\n\n{site_name}", 'mg-membership' ),
				),
				'expiring'       => array(
					'label'   => __( 'Membership expiring reminder (member)', 'mg-membership' ),
					'subject' => __( 'Your {membership} access expires soon', 'mg-membership' ),
					'body'    => __( "Hi {first_name},\n\nYour access to {membership} expires on {expires}. Renew here: {membership_url}\n\n{site_name}", 'mg-membership' ),
				),
				'payment_failed' => array(
					'label'   => __( 'Payment failed (member)', 'mg-membership' ),
					'subject' => __( 'Payment failed for {membership}', 'mg-membership' ),
					'body'    => __( "Hi {first_name},\n\nWe could not process your payment for {membership}. Please update your payment details in your account: {account_url}\n\n{site_name}", 'mg-membership' ),
				),
			)
		);
	}

	public static function send( $key, $to, array $vars = array() ) {
		$templates = self::templates();
		if ( ! isset( $templates[ $key ] ) || ! $to ) {
			return false;
		}
		if ( '0' === (string) mgm_option( 'email_' . $key . '_enabled', '1' ) ) {
			return false;
		}
		$subject = mgm_option( 'email_' . $key . '_subject', $templates[ $key ]['subject'] );
		$body    = mgm_option( 'email_' . $key . '_body', $templates[ $key ]['body'] );

		$vars = array_merge(
			array(
				'site_name'   => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'site_url'    => home_url( '/' ),
				'account_url' => mgm_page_url( 'account' ),
				'login_url'   => mgm_page_url( 'login' ),
			),
			$vars
		);
		// Only links the plugin generates become clickable. Values typed by people (names, event names…)
		// are inserted as plain text, so nobody can make the site email a link of their choosing.
		$link_vars = apply_filters( 'mgm_email_link_vars', array( 'site_url', 'account_url', 'login_url', 'membership_url', 'event_url', 'ics_url', 'login_link', 'review_url', 'courses_url', 'certificate_line', 'password_note', 'join_link' ) );
		$body_html = wpautop( make_clickable( esc_html( $body ) ) );
		foreach ( $vars as $k => $v ) {
			$v         = (string) $v;
			$subject   = str_replace( '{' . $k . '}', str_replace( array( "\r", "\n" ), ' ', $v ), $subject );
			$body_html = str_replace( '{' . $k . '}', in_array( $k, $link_vars, true ) ? make_clickable( esc_html( $v ) ) : esc_html( $v ), $body_html );
		}

		$html = mgm_get_template(
			'emails/layout.php',
			array(
				'subject' => $subject,
				'body'    => $body_html,
			)
		);

		$from_name  = mgm_option( 'email_from_name', get_bloginfo( 'name' ) );
		$from_email = mgm_option( 'email_from_address', get_option( 'admin_email' ) );
		$headers    = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		);
		return wp_mail( $to, $subject, $html, apply_filters( 'mgm_email_headers', $headers, $key ) );
	}

	public static function user_vars( $user_id, $membership_id = 0 ) {
		$user = get_userdata( $user_id );
		$vars = array();
		if ( $user ) {
			$vars = array(
				'username'   => $user->user_login,
				'email'      => $user->user_email,
				'first_name' => $user->first_name ? $user->first_name : $user->display_name,
				'last_name'  => $user->last_name,
				'full_name'  => trim( $user->first_name . ' ' . $user->last_name ) ? trim( $user->first_name . ' ' . $user->last_name ) : $user->display_name,
			);
		}
		if ( $membership_id ) {
			$m                      = new MGM_Membership( $membership_id );
			$vars['membership']     = $m->title();
			$vars['membership_url'] = $m->url();
			$exp                    = MGM_Member::membership_expires( $user_id, $membership_id );
			$vars['expires']        = $exp ? mgm_date( $exp ) : __( 'never', 'mg-membership' );
		}
		return $vars;
	}

	public static function admin_email() {
		return mgm_option( 'admin_notify_email', get_option( 'admin_email' ) );
	}

	public static function on_payment( $txn ) {
		if ( (float) $txn->total <= 0 ) {
			return;
		}
		$user = get_userdata( $txn->user_id );
		if ( ! $user ) {
			return;
		}
		$vars = array_merge(
			self::user_vars( $txn->user_id, $txn->membership_id ),
			array(
				'amount'    => mgm_price( $txn->total ),
				'trans_num' => $txn->trans_num,
				'date'      => mgm_date( $txn->created_at ),
			)
		);
		self::send( 'receipt', $user->user_email, $vars );
	}

	public static function on_signup( $user_id, $txn_id ) {
		$txn  = MGM_Transaction::get( $txn_id );
		$user = get_userdata( $user_id );
		if ( ! $txn || ! $user ) {
			return;
		}
		$vars = array_merge( self::user_vars( $user_id, $txn->membership_id ), array( 'amount' => mgm_price( $txn->total ) ) );
		self::send( 'welcome', $user->user_email, $vars );
		self::send( 'admin_signup', self::admin_email(), $vars );
	}

	public static function on_sub_status( $sub_id, $new, $old ) {
		$sub = MGM_Subscription::get( $sub_id );
		if ( ! $sub ) {
			return;
		}
		$user = get_userdata( $sub->user_id );
		if ( ! $user ) {
			return;
		}
		if ( 'cancelled' === $new ) {
			self::send( 'cancelled', $user->user_email, self::user_vars( $sub->user_id, $sub->membership_id ) );
		} elseif ( 'suspended' === $new && 'active' === $old ) {
			self::send( 'payment_failed', $user->user_email, self::user_vars( $sub->user_id, $sub->membership_id ) );
		}
	}

	public static function on_expiring( $user_id, $membership_id, $expires ) {
		$user = get_userdata( $user_id );
		if ( $user ) {
			self::send( 'expiring', $user->user_email, self::user_vars( $user_id, $membership_id ) );
		}
	}
}
