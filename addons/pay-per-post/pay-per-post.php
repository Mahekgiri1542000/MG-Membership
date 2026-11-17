<?php
/**
 * Add-on: Pay-per-post.
 *
 * "Sell just this item" on any post / page / lesson creates a hidden one-time membership linked to it
 * (meta _mgm_ppp_post). Buying it grants access to that one item through the normal checkout.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Pay_Per_Post {

	public static function init() {
		add_filter( 'mgm_rules_for_post', array( __CLASS__, 'rules' ), 20, 2 );
		add_filter( 'mgm_unauthorized_message', array( __CLASS__, 'offer' ), 20, 2 );
		add_filter( 'mgm_is_public_membership', array( __CLASS__, 'is_public' ), 10, 2 );
		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ), 20 );
			add_action( 'save_post', array( __CLASS__, 'save' ), 20, 2 );
		}
	}

	public static function membership_for( $post_id ) {
		$mid = (int) get_post_meta( $post_id, '_mgm_ppp_membership', true );
		return $mid && 'publish' === get_post_status( $mid ) ? $mid : 0;
	}

	public static function is_public( $is, $membership_id ) {
		return $is && ! get_post_meta( $membership_id, '_mgm_ppp_post', true );
	}

	public static function rules( $rules, $post_id ) {
		$mid = self::membership_for( $post_id );
		if ( ! $mid ) {
			return $rules;
		}
		$rules[] = array(
			'id'           => 'ppp-' . $post_id,
			'type'         => 'post_ids',
			'target'       => (string) $post_id,
			'exclude'      => array(),
			'memberships'  => array( $mid ),
			'message'      => '',
			'drip_enabled' => '',
			'drip_amount'  => 0,
			'drip_unit'    => 'day',
			'drip_date'    => '',
			'exp_enabled'  => '',
			'exp_amount'   => 0,
			'exp_unit'     => 'day',
		);
		return $rules;
	}

	public static function offer( $message, $post_id ) {
		$mid = $post_id ? self::membership_for( $post_id ) : 0;
		if ( ! $mid ) {
			return $message;
		}
		$m    = new MGM_Membership( $mid );
		$type = get_post_type_object( get_post_type( $post_id ) );
		$btn  = sprintf(
			'<p class="mgm-ppp-offer"><a class="mgm-button" href="%s">%s</a></p>',
			esc_url( $m->url() ),
			/* translators: 1: post type label, 2: price */
			esc_html( sprintf( __( 'Buy this %1$s – %2$s', 'mg-membership' ), $type ? strtolower( $type->labels->singular_name ) : __( 'item', 'mg-membership' ), $m->price_terms() ) )
		);
		return $message . $btn;
	}

	public static function metabox() {
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			return;
		}
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'], $types['mgm_membership'], $types['mgm_course'], $types['mgm_event'] );
		foreach ( $types as $pt ) {
			add_meta_box( 'mgm_ppp', __( 'Sell just this item', 'mg-membership' ), array( __CLASS__, 'box' ), $pt, 'side', 'default' );
		}
	}

	public static function box( $post ) {
		wp_nonce_field( 'mgm_ppp_save', '_mgm_ppp_nonce' );
		$mid  = (int) get_post_meta( $post->ID, '_mgm_ppp_membership', true );
		$on   = $mid && 'publish' === get_post_status( $mid );
		$m    = $mid ? new MGM_Membership( $mid ) : null;
		$days = $m && 'fixed-period' === $m->get( 'access_type' ) ? (int) $m->get( 'access_period' ) : 0;
		echo '<p><label><input type="checkbox" name="mgm_ppp[enabled]" value="1" ' . checked( $on, true, false ) . '> ' . esc_html__( 'Sell access to this item on its own', 'mg-membership' ) . '</label></p>';
		echo '<p><label>' . esc_html( sprintf( __( 'Price (%s)', 'mg-membership' ), mgm_currency() ) ) . '<br><input type="number" min="0.01" step="0.01" name="mgm_ppp[price]" value="' . esc_attr( $m ? $m->get( 'price' ) : '' ) . '" class="widefat"></label></p>';
		echo '<p><label>' . esc_html__( 'Access lasts (days, 0 = forever)', 'mg-membership' ) . '<br><input type="number" min="0" name="mgm_ppp[days]" value="' . esc_attr( $days ) . '" class="widefat"></label></p>';
		if ( $on ) {
			echo '<p class="description">' . esc_html__( 'Buy link:', 'mg-membership' ) . ' <a href="' . esc_url( get_permalink( $mid ) ) . '" target="_blank">' . esc_html( get_permalink( $mid ) ) . '</a></p>';
		}
		echo '<p class="description">' . esc_html__( 'Members who already have access through a rule or membership still get in for free.', 'mg-membership' ) . '</p>';
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['_mgm_ppp_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_ppp_nonce'] ), 'mgm_ppp_save' ) || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( mgm_admin_cap() ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$in  = isset( $_POST['mgm_ppp'] ) ? (array) wp_unslash( $_POST['mgm_ppp'] ) : array(); // phpcs:ignore
		self::configure( $post_id, ! empty( $in['enabled'] ), (float) ( $in['price'] ?? 0 ), absint( $in['days'] ?? 0 ) );
	}

	/**
	 * Create / update / disable the hidden membership for a post.
	 */
	public static function configure( $post_id, $enabled, $price, $days = 0 ) {
		$mid = (int) get_post_meta( $post_id, '_mgm_ppp_membership', true );
		if ( ! $enabled || $price <= 0 ) {
			if ( $mid ) {
				wp_update_post( array( 'ID' => $mid, 'post_status' => 'draft' ) );
			}
			MGM_Access::flush_rules();
			return 0;
		}
		$title = sprintf( __( 'Single access: %s', 'mg-membership' ), get_the_title( $post_id ) );
		if ( $mid && get_post( $mid ) ) {
			wp_update_post( array( 'ID' => $mid, 'post_title' => $title, 'post_status' => 'publish' ) );
		} else {
			$mid = wp_insert_post( array( 'post_type' => 'mgm_membership', 'post_title' => $title, 'post_status' => 'publish', 'post_content' => '' ) );
			update_post_meta( $post_id, '_mgm_ppp_membership', $mid );
		}
		$meta = array(
			'billing_type'       => 'one-time',
			'price'              => (string) round( $price, 2 ),
			'access_type'        => $days > 0 ? 'fixed-period' : 'lifetime',
			'access_period'      => (string) max( 1, $days ),
			'access_period_unit' => 'day',
			'thankyou_url'       => get_permalink( $post_id ),
			'signup_button_text' => __( 'Buy now', 'mg-membership' ),
			'allow_multiple'     => '1',
		);
		foreach ( $meta as $k => $v ) {
			update_post_meta( $mid, '_mgm_' . $k, $v );
		}
		update_post_meta( $mid, '_mgm_ppp_post', $post_id );
		MGM_Access::flush_rules();
		return $mid;
	}
}

MGM_Pay_Per_Post::init();
