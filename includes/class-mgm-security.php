<?php
/**
 * Core security: rate limiting and login brute-force protection (always on).
 *
 * - Login: 5 failed attempts for one username from one IP within 15 minutes locks that pair for 15 minutes;
 *   30 failures from one IP (any usernames) locks the IP. Applies to wp-login.php and the MG login form.
 * - Rate limits: coupon checks, lost-password requests, guest event registrations, new-account signups.
 *
 * All limits are filterable: mgm_rate_limits.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Security_Core {

	public static function init() {
		add_filter( 'authenticate', array( __CLASS__, 'block_locked' ), 30, 3 );
		add_action( 'wp_login_failed', array( __CLASS__, 'login_failed' ), 10, 1 );
		add_action( 'wp_login', array( __CLASS__, 'login_ok' ), 10, 2 );
		add_filter( 'mgm_member_info_allowed_fields', array( __CLASS__, 'member_info_fields' ) );
	}

	/**
	 * Limits: [max hits, window seconds].
	 */
	public static function limits() {
		return apply_filters(
			'mgm_rate_limits',
			array(
				'login_user'   => array( 5, 15 * MINUTE_IN_SECONDS ),
				'login_ip'     => array( 30, 15 * MINUTE_IN_SECONDS ),
				'coupon'       => array( 10, 10 * MINUTE_IN_SECONDS ),
				'lost_pw'      => array( 5, 15 * MINUTE_IN_SECONDS ),
				'event_guest'  => array( 5, 10 * MINUTE_IN_SECONDS ),
				'signup'       => array( 15, HOUR_IN_SECONDS ), // Generous: offices/classrooms share one IP.
				'cert'         => array( 60, 10 * MINUTE_IN_SECONDS ),
				'magic_link'   => array( 5, 15 * MINUTE_IN_SECONDS ),
			)
		);
	}

	/**
	 * Client IP. REMOTE_ADDR only by default – proxies' X-Forwarded-For can be spoofed.
	 * Behind a trusted proxy/CDN, return the real IP via the mgm_client_ip filter.
	 */
	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		return (string) apply_filters( 'mgm_client_ip', $ip );
	}

	private static function key( $bucket, $id ) {
		return 'mgm_rl_' . substr( md5( $bucket . '|' . $id . '|' . wp_salt( 'nonce' ) ), 0, 24 );
	}

	/**
	 * Is a bucket over its limit (without counting a new hit)?
	 */
	public static function exceeded( $bucket, $id = '' ) {
		$limits = self::limits();
		if ( empty( $limits[ $bucket ] ) ) {
			return false;
		}
		$data = get_transient( self::key( $bucket, $id ? $id : self::ip() ) );
		return is_array( $data ) && $data['n'] >= $limits[ $bucket ][0];
	}

	/**
	 * Count a hit. Returns true when the request is still allowed.
	 */
	public static function hit( $bucket, $id = '' ) {
		$limits = self::limits();
		if ( empty( $limits[ $bucket ] ) ) {
			return true;
		}
		list( $max, $window ) = $limits[ $bucket ];
		$key  = self::key( $bucket, $id ? $id : self::ip() );
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			$data = array( 'n' => 0, 't' => time() );
		}
		++$data['n'];
		set_transient( $key, $data, max( 1, $window - ( time() - $data['t'] ) ) );
		if ( $data['n'] > $max ) {
			do_action( 'mgm_rate_limited', $bucket, $id, $data['n'] );
			return false;
		}
		return true;
	}

	public static function reset( $bucket, $id = '' ) {
		delete_transient( self::key( $bucket, $id ? $id : self::ip() ) );
	}

	/* ---------------------------- Login ---------------------------- */

	private static function user_key( $username ) {
		return strtolower( trim( (string) $username ) ) . '|' . self::ip();
	}

	public static function block_locked( $user, $username, $password ) {
		if ( '' === (string) $username ) {
			return $user;
		}
		if ( self::exceeded( 'login_user', self::user_key( $username ) ) || self::exceeded( 'login_ip' ) ) {
			return new WP_Error( 'mgm_locked', __( 'Too many failed login attempts. Please wait 15 minutes and try again, or reset your password.', 'mg-membership' ) );
		}
		return $user;
	}

	public static function login_failed( $username ) {
		self::hit( 'login_user', self::user_key( $username ) );
		self::hit( 'login_ip' );
	}

	public static function login_ok( $login, $user ) {
		self::reset( 'login_user', self::user_key( $login ) );
		self::reset( 'login_user', self::user_key( $user->user_email ) );
	}

	/**
	 * Fields [mgm_member_info] may print. Anything else (password hash, session tokens,
	 * activation keys, arbitrary user meta) is refused.
	 */
	public static function member_info_fields( $fields ) {
		return array_merge( $fields, array( 'display_name', 'first_name', 'last_name', 'nickname', 'user_email', 'user_login', 'description', 'memberships', 'registered' ) );
	}

	/**
	 * JSON 429 for REST endpoints.
	 */
	public static function rest_limited() {
		return new WP_Error( 'mgm_rate_limited', __( 'Too many requests. Please wait a few minutes and try again.', 'mg-membership' ), array( 'status' => 429 ) );
	}

	/**
	 * Neutralise spreadsheet formulas in CSV cells (CSV injection).
	 */
	public static function csv_cell( $value ) {
		$value = (string) $value;
		if ( is_numeric( $value ) ) {
			return $value;
		}
		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $value : $value;
	}

	public static function csv_row( array $row ) {
		return array_map( array( __CLASS__, 'csv_cell' ), $row );
	}
}
