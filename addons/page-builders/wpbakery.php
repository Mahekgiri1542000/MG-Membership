<?php
/**
 * WPBakery Page Builder integration.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_WPBakery {

	public static function init() {
		foreach ( MGM_Page_Builders::elements() as $tag => $el ) {
			$params = array();
			foreach ( $el['atts'] as $key => $att ) {
				$p = array(
					'heading'    => $att['label'],
					'param_name' => $key,
					'value'      => (string) $att['default'],
				);
				switch ( $att['type'] ) {
					case 'select':
						$p['type']  = 'dropdown';
						$p['value'] = array_flip( $att['options'] );
						$p['std']   = (string) $att['default'];
						break;
					case 'memberships':
						$p['type']  = 'checkbox';
						$p['value'] = array_flip( mgm_membership_options() );
						break;
					case 'editor':
						$p['type']       = 'textarea_html';
						$p['param_name'] = 'content';
						break;
					case 'textarea':
						$p['type'] = 'textarea';
						break;
					default:
						$p['type'] = 'textfield';
				}
				$params[] = $p;
			}
			vc_map(
				array(
					'name'                    => $el['label'],
					'base'                    => $tag,
					'category'                => __( 'MG Membership', 'mg-membership' ),
					'icon'                    => 'icon-wpb-ui-custom_heading',
					'description'             => __( 'MG Membership element', 'mg-membership' ),
					'params'                  => $params,
					'show_settings_on_create' => ! empty( $params ),
				)
			);
		}
	}
}
