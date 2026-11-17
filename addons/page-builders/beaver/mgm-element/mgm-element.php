<?php
/**
 * Beaver Builder module class.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Beaver_Element extends FLBuilderModule {

	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'MG Membership Element', 'mg-membership' ),
				'description'     => __( 'Pricing tables, checkout, login, account, courses, events and protected content.', 'mg-membership' ),
				'category'        => __( 'MG Membership', 'mg-membership' ),
				'dir'             => __DIR__ . '/',
				'url'             => MGM_URL . 'addons/page-builders/beaver/mgm-element/',
				'partial_refresh' => true,
			)
		);
	}

	/**
	 * Build the HTML for the selected element.
	 */
	public function mgm_html() {
		$tag      = isset( $this->settings->element ) ? $this->settings->element : '';
		$registry = MGM_Page_Builders::elements();
		if ( ! isset( $registry[ $tag ] ) ) {
			return '';
		}
		$atts = array();
		foreach ( $registry[ $tag ]['atts'] as $key => $att ) {
			$prop         = $tag . '__' . $key;
			$v            = isset( $this->settings->$prop ) ? $this->settings->$prop : $att['default'];
			$atts[ $key ] = 'memberships' === $att['type'] ? MGM_Page_Builders::list_value( $v ) : (string) $v;
		}
		$content = '';
		if ( isset( $atts['content'] ) ) {
			$content = $atts['content'];
			unset( $atts['content'] );
		}
		$html = MGM_Shortcodes::render( $tag, $atts, $content );
		if ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() ) {
			$html = MGM_Page_Builders::editor_placeholder( $registry[ $tag ]['label'], $html );
		}
		return $html;
	}
}
