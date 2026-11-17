<?php
/**
 * Beaver Builder integration: one "MG Membership Element" module whose
 * settings switch per element (pricing, checkout, courses, events…).
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Beaver {

	public static function init() {
		require_once __DIR__ . '/mgm-element/mgm-element.php';

		$elements = MGM_Page_Builders::elements();
		$options  = array();
		$toggle   = array();
		$sections = array();
		foreach ( $elements as $tag => $el ) {
			$options[ $tag ] = $el['label'];
			$fields          = array();
			foreach ( $el['atts'] as $key => $att ) {
				$name = $tag . '__' . $key;
				$f    = array(
					'label'   => $att['label'],
					'default' => (string) $att['default'],
				);
				switch ( $att['type'] ) {
					case 'select':
						$f['type']    = 'select';
						$f['options'] = $att['options'];
						break;
					case 'memberships':
						$f['type']         = 'select';
						$f['options']      = mgm_membership_options();
						$f['multi-select'] = true;
						$f['default']      = '';
						break;
					case 'editor':
						$f['type']          = 'editor';
						$f['media_buttons'] = false;
						break;
					case 'textarea':
						$f['type'] = 'textarea';
						break;
					default:
						$f['type'] = 'text';
				}
				$fields[ $name ] = $f;
			}
			if ( $fields ) {
				$sections[ 'sec_' . $tag ] = array(
					'title'  => $el['label'],
					'fields' => $fields,
				);
				$toggle[ $tag ] = array( 'sections' => array( 'sec_' . $tag ) );
			} else {
				$toggle[ $tag ] = array();
			}
		}

		FLBuilder::register_module(
			'MGM_Beaver_Element',
			array(
				'general' => array(
					'title'    => __( 'Element', 'mg-membership' ),
					'sections' => array_merge(
						array(
							'main' => array(
								'title'  => '',
								'fields' => array(
									'element' => array(
										'type'    => 'select',
										'label'   => __( 'Element', 'mg-membership' ),
										'default' => 'mgm_pricing',
										'options' => $options,
										'toggle'  => $toggle,
									),
								),
							),
						),
						$sections
					),
				),
			)
		);

		// Membership visibility for any row/column/module via its CSS class (same classes as Divi).
		add_filter( 'fl_builder_is_node_visible', array( __CLASS__, 'visible' ), 10, 2 );
	}

	public static function visible( $is_visible, $node ) {
		if ( ! $is_visible || empty( $node->settings->class ) || ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() ) ) {
			return $is_visible;
		}
		foreach ( preg_split( '/\s+/', $node->settings->class ) as $c ) {
			if ( 'mgm-members-only' === $c && ! MGM_Access::check_condition( 'member' ) ) {
				return false;
			}
			if ( 'mgm-nonmembers-only' === $c && ! MGM_Access::check_condition( 'nonmember' ) ) {
				return false;
			}
			if ( 'mgm-logged-in-only' === $c && ! is_user_logged_in() ) {
				return false;
			}
			if ( 'mgm-guests-only' === $c && is_user_logged_in() ) {
				return false;
			}
			if ( preg_match( '/^mgm-members-(\d+)$/', $c, $m ) && ! MGM_Access::check_condition( 'member', $m[1] ) ) {
				return false;
			}
		}
		return $is_visible;
	}
}
