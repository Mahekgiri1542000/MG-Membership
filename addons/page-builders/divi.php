<?php
/**
 * Divi Builder integration (Divi 4 module API; Divi 5 loads these through its D4 compatibility layer).
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Divi {

	public static function init() {
		require_once __DIR__ . '/class-mgm-divi-module.php';
		foreach ( MGM_Page_Builders::elements() as $tag => $el ) {
			MGM_Divi_Module::$next_tag = $tag;
			new MGM_Divi_Module();
		}
		MGM_Divi_Module::$next_tag = '';

		// Membership visibility on every Divi section/row/module via the "CSS Class" field:
		// add  mgm-members-only  or  mgm-members-12  (membership ID) / mgm-guests-only / mgm-nonmembers-only.
		add_filter( 'et_module_shortcode_output', array( __CLASS__, 'visibility' ), 10, 3 );
	}

	public static function visibility( $output, $render_slug, $module = null ) {
		if ( ! is_string( $output ) || ! $module || empty( $module->props['module_class'] ) ) {
			return $output;
		}
		if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
			return $output;
		}
		$classes = preg_split( '/\s+/', $module->props['module_class'] );
		foreach ( $classes as $c ) {
			if ( 'mgm-members-only' === $c && ! MGM_Access::check_condition( 'member' ) ) {
				return '';
			}
			if ( 'mgm-nonmembers-only' === $c && ! MGM_Access::check_condition( 'nonmember' ) ) {
				return '';
			}
			if ( 'mgm-logged-in-only' === $c && ! is_user_logged_in() ) {
				return '';
			}
			if ( 'mgm-guests-only' === $c && is_user_logged_in() ) {
				return '';
			}
			if ( preg_match( '/^mgm-members-(\d+)$/', $c, $m ) && ! MGM_Access::check_condition( 'member', $m[1] ) ) {
				return '';
			}
		}
		return $output;
	}
}
