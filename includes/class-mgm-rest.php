<?php
/**
 * REST API: webhooks, coupon validation, membership data, member check.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_REST {

	const NS = 'mgm/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			self::NS,
			'/webhook/(?P<gateway>[a-z0-9_-]+)',
			array(
				'methods'             => array( 'POST', 'GET' ),
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $request ) {
					$gateway = mgm()->gateway( $request['gateway'] );
					if ( ! $gateway ) {
						return new WP_REST_Response( array( 'error' => 'unknown gateway' ), 404 );
					}
					return $gateway->handle_webhook( $request );
				},
			)
		);

		register_rest_route(
			self::NS,
			'/coupon',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'args'                => array(
					'code'          => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'membership_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
				'callback'            => function ( WP_REST_Request $request ) {
					if ( ! MGM_Security_Core::hit( 'coupon' ) ) {
						return MGM_Security_Core::rest_limited();
					}
					$m = new MGM_Membership( $request['membership_id'] );
					if ( ! $m->exists() || 'publish' !== get_post_status( $m->id ) ) {
						return new WP_Error( 'mgm_invalid', __( 'Invalid membership.', 'mg-membership' ), array( 'status' => 400 ) );
					}
					$res = MGM_Checkout::apply_coupon( $request['code'], $m );
					if ( is_wp_error( $res ) ) {
						return new WP_REST_Response( array( 'valid' => false, 'message' => $res->get_error_message() ), 200 );
					}
					$tax = MGM_Checkout::tax_for( $res['price'] );
					return array(
						'valid'       => true,
						'price'       => $res['price'],
						'tax'         => $tax['tax'],
						'total'       => $tax['total'],
						'total_html'  => mgm_price( $tax['total'] ),
						'terms'       => $m->price_terms( $res['price'] ),
						'trial_days'  => $res['trial_days'],
						'free'        => $res['price'] <= 0 && ( ! $m->is_recurring() || $res['trial_amount'] <= 0 ),
					);
				},
			)
		);

		register_rest_route(
			self::NS,
			'/memberships',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					$out = array();
					foreach ( mgm_membership_options() as $id => $title ) {
						if ( ! mgm_is_public_membership( $id ) ) {
							continue;
						}
						$m     = new MGM_Membership( $id );
						$out[] = array(
							'id'        => $id,
							'title'     => $title,
							'price'     => $m->price(),
							'terms'     => $m->price_terms(),
							'recurring' => $m->is_recurring(),
							'url'       => $m->url(),
						);
					}
					return $out;
				},
			)
		);

		register_rest_route(
			self::NS,
			'/me',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'is_user_logged_in',
				'callback'            => function () {
					$uid = get_current_user_id();
					return array(
						'user_id'     => $uid,
						'memberships' => MGM_Member::active_membership_ids( $uid ),
					);
				},
			)
		);
	}
}
