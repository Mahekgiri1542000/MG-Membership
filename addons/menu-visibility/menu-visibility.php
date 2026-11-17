<?php
/**
 * Add-on: Menu item visibility by membership (classic menus).
 * Block themes: use the "Membership visibility" panel on Navigation Link blocks.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Menu_Visibility {

	public static function init() {
		add_action( 'wp_nav_menu_item_custom_fields', array( __CLASS__, 'fields' ), 10, 2 );
		add_action( 'wp_update_nav_menu_item', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'filter' ), 20 );
	}

	public static function conditions() {
		return array(
			''          => __( 'Everyone', 'mg-membership' ),
			'member'    => __( 'Members', 'mg-membership' ),
			'nonmember' => __( 'Non-members', 'mg-membership' ),
			'logged_in' => __( 'Logged-in users', 'mg-membership' ),
			'guest'     => __( 'Guests (logged out)', 'mg-membership' ),
		);
	}

	public static function fields( $item_id, $item ) {
		$cond = get_post_meta( $item_id, '_mgm_menu_condition', true );
		$sel  = mgm_int_list( get_post_meta( $item_id, '_mgm_menu_memberships', true ) );
		echo '<p class="description description-wide mgm-menu-visibility"><label>' . esc_html__( 'MG Membership: show to', 'mg-membership' ) . '<br><select name="mgm_menu_condition[' . (int) $item_id . ']" class="widefat">';
		foreach ( self::conditions() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $cond, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		$opts = mgm_membership_options();
		if ( $opts ) {
			echo '<span class="mgm-muted" style="display:block;margin-top:4px">' . esc_html__( 'Limit "Members / Non-members" to these memberships (none = any):', 'mg-membership' ) . '</span>';
			foreach ( $opts as $id => $title ) {
				echo '<label style="display:inline-block;margin-right:10px"><input type="checkbox" name="mgm_menu_memberships[' . (int) $item_id . '][]" value="' . (int) $id . '" ' . checked( in_array( (int) $id, $sel, true ), true, false ) . '> ' . esc_html( $title ) . '</label>';
			}
		}
		echo '</p>';
	}

	/**
	 * Core verifies the "update-nav_menu" nonce before items are saved.
	 */
	public static function save( $menu_id, $item_id ) {
		if ( ! current_user_can( 'edit_theme_options' ) || ! isset( $_POST['mgm_menu_condition'] ) ) { // phpcs:ignore
			return;
		}
		$conds = (array) wp_unslash( $_POST['mgm_menu_condition'] ); // phpcs:ignore
		$mems  = isset( $_POST['mgm_menu_memberships'] ) ? (array) wp_unslash( $_POST['mgm_menu_memberships'] ) : array(); // phpcs:ignore
		if ( ! array_key_exists( $item_id, $conds ) ) {
			return;
		}
		$c = sanitize_key( $conds[ $item_id ] );
		update_post_meta( $item_id, '_mgm_menu_condition', array_key_exists( $c, self::conditions() ) ? $c : '' );
		update_post_meta( $item_id, '_mgm_menu_memberships', mgm_int_list( $mems[ $item_id ] ?? array() ) );
	}

	public static function visible( $item_id ) {
		$cond = get_post_meta( $item_id, '_mgm_menu_condition', true );
		if ( ! $cond || ( is_user_logged_in() && current_user_can( mgm_admin_cap() ) && ! apply_filters( 'mgm_menu_preview_as_visitor', false ) ) ) {
			return true;
		}
		return MGM_Access::check_condition( $cond, get_post_meta( $item_id, '_mgm_menu_memberships', true ) );
	}

	/**
	 * Remove hidden items and all of their descendants.
	 */
	public static function filter( $items ) {
		$hidden = array();
		foreach ( $items as $item ) {
			if ( in_array( (int) $item->menu_item_parent, $hidden, true ) || ! self::visible( $item->ID ) ) {
				$hidden[] = (int) $item->ID;
			}
		}
		if ( ! $hidden ) {
			return $items;
		}
		return array_values(
			array_filter(
				$items,
				function ( $item ) use ( $hidden ) {
					return ! in_array( (int) $item->ID, $hidden, true );
				}
			)
		);
	}
}

MGM_Menu_Visibility::init();
