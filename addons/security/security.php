<?php
/**
 * Add-on: Account sharing & privacy.
 * - Limit simultaneous logins per member (oldest sessions are signed out).
 * - Hide locked content from site search results for visitors who can't open it.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Security {

	public static function init() {
		add_filter( 'mgm_settings_fields', array( __CLASS__, 'settings' ) );
		add_action( 'wp_login', array( __CLASS__, 'limit_sessions' ), 20, 2 );
		add_filter( 'the_posts', array( __CLASS__, 'filter_search' ), 20, 2 );
	}

	public static function settings( $fields ) {
		$fields['access']['fields']['security_heading'] = array( 'type' => 'heading', 'label' => __( 'Account sharing & privacy', 'mg-membership' ) );
		$fields['access']['fields']['max_sessions']     = array(
			'type'  => 'number',
			'label' => __( 'Maximum simultaneous logins per member', 'mg-membership' ),
			'desc'  => __( '0 = unlimited. When a member logs in on one device too many, their oldest session is signed out. Administrators are never limited.', 'mg-membership' ),
		);
		$fields['access']['fields']['hide_from_search'] = array(
			'type'  => 'checkbox',
			'label' => __( 'Hide locked content from search results for visitors who cannot open it', 'mg-membership' ),
		);
		return $fields;
	}

	public static function limit_sessions( $login, $user ) {
		$max = (int) mgm_option( 'max_sessions', 0 );
		if ( $max < 1 || user_can( $user, mgm_admin_cap() ) ) {
			return;
		}
		$manager  = WP_Session_Tokens::get_instance( $user->ID );
		$sessions = $manager->get_all();
		if ( count( $sessions ) <= $max ) {
			return;
		}
		// get_all() loses the token keys, so read the raw meta to keep the newest $max sessions.
		$raw = get_user_meta( $user->ID, 'session_tokens', true );
		if ( ! is_array( $raw ) ) {
			return;
		}
		uasort(
			$raw,
			function ( $a, $b ) {
				return (int) ( $b['login'] ?? 0 ) - (int) ( $a['login'] ?? 0 );
			}
		);
		$keep = array_slice( $raw, 0, $max, true );
		update_user_meta( $user->ID, 'session_tokens', $keep );
		do_action( 'mgm_sessions_limited', $user->ID, count( $raw ) - count( $keep ) );
	}

	public static function filter_search( $posts, $query ) {
		$rest = defined( 'REST_REQUEST' ) && REST_REQUEST;
		if ( ( is_admin() && ! $rest ) || ! $query->is_search() || ( ! $query->is_main_query() && ! $rest ) || ! mgm_option( 'hide_from_search' ) ) {
			return $posts;
		}
		return array_values(
			array_filter(
				$posts,
				function ( $p ) {
					return MGM_Access::can_access_post( $p->ID );
				}
			)
		);
	}
}

MGM_Security::init();
