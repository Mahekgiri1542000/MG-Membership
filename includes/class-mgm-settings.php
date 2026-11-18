<?php
/**
 * Settings storage helper.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Settings {

	public static function all() {
		return (array) get_option( 'mgm_settings', array() );
	}

	public static function update( array $values ) {
		$opts = self::all();
		foreach ( $values as $k => $v ) {
			$opts[ sanitize_key( $k ) ] = $v;
		}
		update_option( 'mgm_settings', $opts );
	}

	/** Delete saved values so their defaults apply again. */
	public static function remove( array $keys ) {
		$opts = self::all();
		foreach ( $keys as $k ) {
			unset( $opts[ sanitize_key( $k ) ] );
		}
		update_option( 'mgm_settings', $opts );
	}

	/**
	 * Settings tabs & fields, used to build the settings screen.
	 */
	public static function fields() {
		$pages = array( 0 => __( '— Select —', 'mg-membership' ) );
		foreach ( get_pages() as $p ) {
			$pages[ $p->ID ] = $p->post_title;
		}

		$fields = array(
			'general'  => array(
				'label'  => __( 'General', 'mg-membership' ),
				'fields' => array(
					'currency'          => array( 'type' => 'select', 'label' => __( 'Currency', 'mg-membership' ), 'options' => array_combine( $c = array( 'USD', 'EUR', 'GBP', 'INR', 'AUD', 'CAD', 'JPY', 'NZD', 'CHF', 'SGD', 'BRL', 'ZAR', 'AED', 'MXN' ), $c ) ),
					'currency_position' => array( 'type' => 'select', 'label' => __( 'Currency position', 'mg-membership' ), 'options' => array( 'before' => __( 'Before amount', 'mg-membership' ), 'after' => __( 'After amount', 'mg-membership' ) ) ),
					'tax_rate'          => array( 'type' => 'number', 'label' => __( 'Tax rate (%)', 'mg-membership' ), 'step' => '0.01' ),
					'tax_inclusive'     => array( 'type' => 'checkbox', 'label' => __( 'Prices include tax', 'mg-membership' ) ),
					'hide_admin_bar'    => array( 'type' => 'checkbox', 'label' => __( 'Hide admin bar for members', 'mg-membership' ) ),
					'block_wp_admin'    => array( 'type' => 'checkbox', 'label' => __( 'Block /wp-admin for members (non-admins)', 'mg-membership' ) ),
					'magic_link_login'  => array( 'type' => 'checkbox', 'label' => __( 'Let members log in with an emailed one-time link (not available for staff accounts)', 'mg-membership' ), 'default' => '1' ),
					'require_terms'     => array( 'type' => 'checkbox', 'label' => __( 'Require Terms acceptance at checkout', 'mg-membership' ) ),
					'terms_url'         => array( 'type' => 'text', 'label' => __( 'Terms page URL', 'mg-membership' ) ),
					'debug_log'         => array( 'type' => 'checkbox', 'label' => __( 'Enable debug logging (error_log)', 'mg-membership' ) ),
				),
			),
			'pages'    => array(
				'label'  => __( 'Pages', 'mg-membership' ),
				'fields' => array(
					'page_account'  => array( 'type' => 'select', 'label' => __( 'Account page', 'mg-membership' ), 'options' => $pages ),
					'page_login'    => array( 'type' => 'select', 'label' => __( 'Login page', 'mg-membership' ), 'options' => $pages ),
					'page_thankyou' => array( 'type' => 'select', 'label' => __( 'Thank you page', 'mg-membership' ), 'options' => $pages ),
					'page_pricing'  => array( 'type' => 'select', 'label' => __( 'Pricing / plans page', 'mg-membership' ), 'options' => $pages ),
					'page_courses'  => array( 'type' => 'select', 'label' => __( 'Courses page', 'mg-membership' ), 'options' => $pages ),
					'page_events'   => array( 'type' => 'select', 'label' => __( 'Events page', 'mg-membership' ), 'options' => $pages ),
					'login_redirect' => array( 'type' => 'select', 'label' => __( 'After login redirect to', 'mg-membership' ), 'options' => array( 'account' => __( 'Account page', 'mg-membership' ), 'referer' => __( 'Previous page', 'mg-membership' ), 'home' => __( 'Home page', 'mg-membership' ) ) ),
				),
			),
			'access'   => array(
				'label'  => __( 'Content Protection', 'mg-membership' ),
				'fields' => array(
					'unauthorized_message'  => array( 'type' => 'editor', 'label' => __( 'Unauthorized message', 'mg-membership' ) ),
					'excerpt_on_protected'  => array( 'type' => 'checkbox', 'label' => __( 'Show excerpt before the unauthorized message', 'mg-membership' ) ),
					'show_login_form'       => array( 'type' => 'checkbox', 'label' => __( 'Show login form on protected content', 'mg-membership' ) ),
					'redirect_unauthorized' => array( 'type' => 'checkbox', 'label' => __( 'Redirect unauthorized visitors instead of showing message', 'mg-membership' ) ),
					'unauthorized_redirect_url' => array( 'type' => 'text', 'label' => __( 'Redirect URL (blank = pricing page)', 'mg-membership' ) ),
					'protect_feeds'         => array( 'type' => 'checkbox', 'label' => __( 'Hide protected content from RSS feeds', 'mg-membership' ) ),
					'protect_rest'          => array( 'type' => 'checkbox', 'label' => __( 'Hide protected content from the public REST API', 'mg-membership' ) ),
				),
			),
			'payments' => array(
				'label'  => __( 'Payments', 'mg-membership' ),
				'fields' => array(),
			),
			'emails'   => array(
				'label'  => __( 'Emails', 'mg-membership' ),
				'fields' => array(
					'email_from_name'    => array( 'type' => 'text', 'label' => __( 'From name', 'mg-membership' ) ),
					'email_from_address' => array( 'type' => 'text', 'label' => __( 'From email', 'mg-membership' ) ),
					'admin_notify_email' => array( 'type' => 'text', 'label' => __( 'Admin notification email (blank = site admin)', 'mg-membership' ) ),
				),
			),
		);

		foreach ( mgm()->gateways as $gateway ) {
			$fields['payments']['fields'] = array_merge( $fields['payments']['fields'], $gateway->settings_fields() );
		}
		foreach ( MGM_Emails::templates() as $key => $tpl ) {
			$fields['emails']['fields'][ 'email_' . $key . '_enabled' ] = array( 'type' => 'checkbox', 'label' => sprintf( __( 'Send: %s', 'mg-membership' ), $tpl['label'] ), 'default' => '1' );
			$fields['emails']['fields'][ 'email_' . $key . '_subject' ] = array( 'type' => 'text', 'label' => __( 'Subject', 'mg-membership' ), 'placeholder' => $tpl['subject'] );
			$fields['emails']['fields'][ 'email_' . $key . '_body' ]    = array( 'type' => 'textarea', 'label' => __( 'Body', 'mg-membership' ), 'placeholder' => $tpl['body'] );
		}

		return apply_filters( 'mgm_settings_fields', $fields );
	}
}
