<?php
/**
 * Add-on: Page builder integrations.
 *
 * Every element in MGM_Shortcodes::registry() becomes:
 *  - a Gutenberg block (+ a "Members only" container block & visibility panel on every block)
 *  - an Elementor widget (+ "MG Membership" visibility controls on every section/container/column/widget)
 *  - a Divi module
 *  - a WPBakery element
 *  - a Beaver Builder module
 * Any other builder (Oxygen, Bricks, Breakdance, SiteOrigin, Thrive, Brizy…) can use the shortcodes.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Page_Builders {

	public static function init() {
		require_once __DIR__ . '/gutenberg.php';
		MGM_Gutenberg::init();

		add_action( 'elementor/init', array( __CLASS__, 'elementor' ) );
		add_action( 'et_builder_ready', array( __CLASS__, 'divi' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'wpbakery' ) );
		add_action( 'init', array( __CLASS__, 'beaver' ), 20 );
	}

	public static function elementor() {
		require_once __DIR__ . '/elementor.php';
		MGM_Elementor::init();
	}

	public static function divi() {
		if ( class_exists( 'ET_Builder_Module' ) ) {
			require_once __DIR__ . '/divi.php';
			MGM_Divi::init();
		}
	}

	public static function wpbakery() {
		if ( function_exists( 'vc_map' ) ) {
			require_once __DIR__ . '/wpbakery.php';
			MGM_WPBakery::init();
		}
	}

	public static function beaver() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once __DIR__ . '/beaver/beaver.php';
			MGM_Beaver::init();
		}
	}

	/**
	 * Registry elements usable as builder widgets.
	 */
	public static function elements() {
		return MGM_Shortcodes::registry();
	}

	/**
	 * Normalize a "memberships" value coming from any builder into a comma list.
	 */
	public static function list_value( $value ) {
		if ( is_array( $value ) ) {
			return implode( ',', array_filter( array_map( 'absint', $value ) ) );
		}
		return (string) $value;
	}

	/**
	 * Placeholder shown in builder editors when an element renders nothing (e.g. needs a logged-in user).
	 */
	public static function editor_placeholder( $label, $html ) {
		if ( '' !== trim( wp_strip_all_tags( $html, true ) ) || false !== strpos( $html, '<form' ) || false !== strpos( $html, '<img' ) ) {
			return $html;
		}
		return '<div class="mgm-builder-placeholder"><strong>' . esc_html( $label ) . '</strong><br><small>' . esc_html__( 'This element shows content on the live page (it may depend on the visitor being logged in or a member).', 'mg-membership' ) . '</small></div>';
	}
}

MGM_Page_Builders::init();
