<?php
/**
 * Global helper functions.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get a plugin setting.
 */
function mgm_option( $key, $default = '' ) {
	$opts = get_option( 'mgm_settings', array() );
	return isset( $opts[ $key ] ) && '' !== $opts[ $key ] ? $opts[ $key ] : $default;
}

function mgm_currency() {
	return strtoupper( mgm_option( 'currency', 'USD' ) );
}

function mgm_currency_symbol( $currency = '' ) {
	$currency = $currency ? $currency : mgm_currency();
	$symbols  = array(
		'USD' => '$',
		'EUR' => '€',
		'GBP' => '£',
		'INR' => '₹',
		'AUD' => 'A$',
		'CAD' => 'C$',
		'JPY' => '¥',
		'NZD' => 'NZ$',
		'CHF' => 'CHF ',
		'SGD' => 'S$',
		'BRL' => 'R$',
		'ZAR' => 'R',
		'AED' => 'AED ',
		'MXN' => 'MX$',
	);
	return apply_filters( 'mgm_currency_symbol', isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : $currency . ' ', $currency );
}

function mgm_price( $amount ) {
	$amount = (float) $amount;
	$dec    = in_array( mgm_currency(), array( 'JPY' ), true ) ? 0 : 2;
	$out    = number_format_i18n( $amount, $dec );
	return 'after' === mgm_option( 'currency_position', 'before' ) ? $out . mgm_currency_symbol() : mgm_currency_symbol() . $out;
}

/**
 * Amount in the smallest currency unit (cents) for gateways.
 */
function mgm_minor_units( $amount ) {
	return in_array( mgm_currency(), array( 'JPY' ), true ) ? (int) round( $amount ) : (int) round( $amount * 100 );
}

/**
 * Get the URL of one of the plugin's core pages.
 *
 * @param string $page account|login|thankyou|checkout|courses|events.
 */
function mgm_page_url( $page ) {
	$id = (int) mgm_option( 'page_' . $page );
	return $id ? get_permalink( $id ) : home_url( '/' );
}

/**
 * Locate a template, allowing theme overrides in /mg-membership/.
 */
function mgm_locate_template( $name ) {
	$theme = locate_template( array( 'mg-membership/' . $name ) );
	return $theme ? $theme : MGM_PATH . 'templates/' . $name;
}

/**
 * Render a template and return the output.
 */
function mgm_get_template( $template_name, $args = array() ) {
	$__mgm_file = mgm_locate_template( $template_name );
	if ( ! file_exists( $__mgm_file ) ) {
		return '';
	}
	unset( $template_name );
	ob_start();
	// No local variable may share a name with a template argument (e.g. "name", "file"), or it would be skipped.
	extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
	include $__mgm_file;
	return ob_get_clean();
}

/**
 * Does user have any of the given memberships (active)?
 *
 * @param int|int[]|string $memberships Membership id(s) or 'any'.
 */
function mgm_user_has_membership( $memberships = 'any', $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	$active = MGM_Member::active_membership_ids( $user_id );
	if ( 'any' === $memberships ) {
		return ! empty( $active );
	}
	$memberships = array_map( 'intval', (array) $memberships );
	return (bool) array_intersect( $memberships, $active );
}

/**
 * Admin capability.
 */
function mgm_admin_cap() {
	return apply_filters( 'mgm_admin_capability', 'manage_options' );
}

/**
 * Billing period labels.
 */
function mgm_periods() {
	return array(
		'day'   => __( 'Day(s)', 'mg-membership' ),
		'week'  => __( 'Week(s)', 'mg-membership' ),
		'month' => __( 'Month(s)', 'mg-membership' ),
		'year'  => __( 'Year(s)', 'mg-membership' ),
	);
}

/**
 * Add an interval to a timestamp.
 */
function mgm_add_period( $timestamp, $interval, $unit ) {
	$interval = max( 1, (int) $interval );
	$unit     = in_array( $unit, array( 'day', 'week', 'month', 'year' ), true ) ? $unit : 'month';
	return strtotime( '+' . $interval . ' ' . $unit, $timestamp );
}

function mgm_now() {
	return current_time( 'mysql', true );
}

function mgm_date( $gmt_mysql ) {
	if ( empty( $gmt_mysql ) || '0000-00-00 00:00:00' === $gmt_mysql ) {
		return '—';
	}
	return get_date_from_gmt( $gmt_mysql, get_option( 'date_format' ) );
}

/**
 * Notices stored for the current request / next page load.
 */
function mgm_add_notice( $message, $type = 'error' ) {
	$GLOBALS['mgm_notices'][] = array(
		'message' => $message,
		'type'    => $type,
	);
}

function mgm_print_notices() {
	$out = '';
	if ( ! empty( $GLOBALS['mgm_notices'] ) ) {
		foreach ( $GLOBALS['mgm_notices'] as $n ) {
			$out .= '<div class="mgm-notice mgm-notice-' . esc_attr( $n['type'] ) . '">' . wp_kses_post( $n['message'] ) . '</div>';
		}
		$GLOBALS['mgm_notices'] = array();
	}
	return $out;
}

/**
 * List of memberships for select fields.
 */
function mgm_membership_options() {
	$posts = get_posts(
		array(
			'post_type'   => 'mgm_membership',
			'numberposts' => -1,
			'post_status' => 'publish',
			'orderby'     => 'menu_order title',
			'order'       => 'ASC',
		)
	);
	$out = array();
	foreach ( $posts as $p ) {
		$out[ $p->ID ] = $p->post_title;
	}
	return $out;
}

/**
 * Sanitize a list of ints from input.
 */
function mgm_int_list( $value ) {
	if ( is_string( $value ) ) {
		$value = explode( ',', $value );
	}
	return array_values( array_filter( array_map( 'absint', (array) $value ) ) );
}

/**
 * Should a membership be listed publicly (pricing tables, REST)? Add-ons can hide internal ones.
 */
function mgm_is_public_membership( $membership_id ) {
	return (bool) apply_filters( 'mgm_is_public_membership', true, (int) $membership_id );
}

function mgm_log( $message ) {
	if ( mgm_option( 'debug_log' ) ) {
		error_log( '[MG Membership] ' . ( is_scalar( $message ) ? $message : wp_json_encode( $message ) ) ); // phpcs:ignore
	}
}
