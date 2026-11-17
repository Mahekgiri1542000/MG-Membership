<?php
/**
 * Core post types: Memberships and Access Rules.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Post_Types {

	/**
	 * Who may create/edit each plugin content type. Plans, rules, coupons and downloads control money and
	 * access, so they are admin-only. Courses, lessons and events are content: editors and up.
	 */
	public static function capability_for( $post_type ) {
		$admin_only = array( 'mgm_membership', 'mgm_rule', 'mgm_coupon', 'mgm_download' );
		$content    = array( 'mgm_course', 'mgm_lesson', 'mgm_event' );
		if ( in_array( $post_type, $admin_only, true ) ) {
			return mgm_admin_cap();
		}
		if ( in_array( $post_type, $content, true ) ) {
			return apply_filters( 'mgm_content_capability', 'edit_others_posts', $post_type );
		}
		return '';
	}

	public static function caps_args( $args, $post_type ) {
		$cap = self::capability_for( $post_type );
		if ( ! $cap ) {
			return $args;
		}
		$args['map_meta_cap'] = true;
		$args['capabilities'] = array(
			'edit_posts'             => $cap,
			'edit_others_posts'      => $cap,
			'edit_private_posts'     => $cap,
			'edit_published_posts'   => $cap,
			'publish_posts'          => $cap,
			'read_private_posts'     => $cap,
			'delete_posts'           => $cap,
			'delete_private_posts'   => $cap,
			'delete_published_posts' => $cap,
			'delete_others_posts'    => $cap,
			'create_posts'           => $cap,
		);
		return $args;
	}

	/**
	 * Roles a membership may grant: never roles that can publish for others, run code or manage users/site.
	 */
	public static function safe_role( $role ) {
		$obj = get_role( (string) $role );
		if ( ! $obj ) {
			return false;
		}
		$dangerous = apply_filters( 'mgm_dangerous_caps', array( 'manage_options', 'edit_others_posts', 'publish_pages', 'unfiltered_html', 'edit_users', 'promote_users', 'create_users', 'delete_users', 'activate_plugins', 'install_plugins', 'edit_plugins', 'edit_theme_options', 'switch_themes', 'edit_files', 'manage_network' ) );
		foreach ( $dangerous as $c ) {
			if ( ! empty( $obj->capabilities[ $c ] ) ) {
				return false;
			}
		}
		return true;
	}

	public static function init() {
		add_filter( 'register_post_type_args', array( __CLASS__, 'caps_args' ), 5, 2 );
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
		add_filter( 'the_content', array( __CLASS__, 'membership_single_content' ), 20 );
	}

	public static function register() {
		register_post_type(
			'mgm_membership',
			array(
				'labels'       => array(
					'name'          => __( 'Memberships', 'mg-membership' ),
					'singular_name' => __( 'Membership', 'mg-membership' ),
					'add_new_item'  => __( 'Add New Membership', 'mg-membership' ),
					'edit_item'     => __( 'Edit Membership', 'mg-membership' ),
					'all_items'     => __( 'Memberships', 'mg-membership' ),
				),
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => 'mgm',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'elementor' ),
				'rewrite'      => array( 'slug' => apply_filters( 'mgm_membership_slug', 'register' ) ),
				'has_archive'  => false,
				'menu_icon'    => 'dashicons-id-alt',
			)
		);

		register_post_type(
			'mgm_rule',
			array(
				'labels'       => array(
					'name'          => __( 'Access Rules', 'mg-membership' ),
					'singular_name' => __( 'Access Rule', 'mg-membership' ),
					'add_new_item'  => __( 'Add New Rule', 'mg-membership' ),
					'edit_item'     => __( 'Edit Rule', 'mg-membership' ),
					'all_items'     => __( 'Rules', 'mg-membership' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'mgm',
				'supports'     => array( 'title' ),
			)
		);

		do_action( 'mgm_register_post_types' );
	}

	public static function maybe_flush() {
		if ( get_option( 'mgm_flush_rewrite' ) ) {
			delete_option( 'mgm_flush_rewrite' );
			flush_rewrite_rules();
		}
	}

	/**
	 * Show pricing + checkout form on the single membership page (like a registration page).
	 */
	public static function membership_single_content( $content ) {
		if ( ! is_singular( 'mgm_membership' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( get_post_meta( get_the_ID(), '_mgm_custom_page', true ) ) {
			// Built with a page builder and uses the [mgm_checkout] shortcode/widget itself.
			return $content;
		}
		return $content . MGM_Checkout::render_form( get_the_ID() );
	}
}
