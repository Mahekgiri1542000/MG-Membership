<?php
/**
 * Generic Divi module driven by the MG Membership element registry.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Divi_Module extends ET_Builder_Module {

	/** @var string Tag used for the next instance. */
	public static $next_tag = '';

	/** @var string */
	public $mgm_tag = '';

	public $vb_support = 'partial';

	protected $module_credits = array(
		'module_uri' => '',
		'author'     => 'MG Membership',
		'author_uri' => '',
	);

	public function __construct( $setup_hooks = true ) {
		$this->mgm_tag = self::$next_tag;
		parent::__construct( $setup_hooks );
	}

	private function el() {
		$r = MGM_Page_Builders::elements();
		return $r[ $this->mgm_tag ] ?? array( 'label' => $this->mgm_tag, 'atts' => array() );
	}

	public function init() {
		$el                 = $this->el();
		$this->name         = 'MG – ' . $el['label'];
		$this->slug         = 'et_pb_' . $this->mgm_tag;
		$this->main_css_element = '%%order_class%%';
		$this->settings_modal_toggles = array(
			'general' => array(
				'toggles' => array(
					'main_content' => esc_html__( 'Settings', 'mg-membership' ),
				),
			),
		);
		$this->advanced_fields = array(
			'fonts'      => false,
			'text'       => false,
			'background' => array(),
			'borders'    => array( 'default' => array() ),
			'margin_padding' => array(),
			'box_shadow' => array( 'default' => array() ),
			'filters'    => false,
			'button'     => false,
		);
	}

	public function get_fields() {
		$fields = array();
		foreach ( $this->el()['atts'] as $key => $att ) {
			$f = array(
				'label'           => $att['label'],
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'default'         => (string) $att['default'],
			);
			switch ( $att['type'] ) {
				case 'select':
					$f['type']    = 'select';
					$f['options'] = $att['options'];
					break;
				case 'memberships':
					$f['type']    = 'multiple_checkboxes';
					$f['options'] = mgm_membership_options();
					$f['default'] = '';
					break;
				case 'editor':
					$f['type'] = 'tiny_mce';
					break;
				case 'textarea':
					$f['type'] = 'textarea';
					break;
				default:
					$f['type'] = 'text';
			}
			$fields[ 'content' === $key ? 'content' : $key ] = $f;
		}
		if ( ! $fields ) {
			$fields['mgm_note'] = array(
				'label'           => esc_html__( 'No settings needed', 'mg-membership' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'description'     => esc_html__( 'This element adapts to the visitor automatically.', 'mg-membership' ),
			);
		}
		return $fields;
	}

	/**
	 * Divi multiple_checkboxes value is "on|off|on" ordered like the options.
	 */
	private function checkbox_ids( $value ) {
		if ( '' === (string) $value ) {
			return '';
		}
		if ( preg_match( '/^[\d,\s]+$/', $value ) ) {
			return $value;
		}
		$ids   = array_keys( mgm_membership_options() );
		$flags = explode( '|', $value );
		$out   = array();
		foreach ( $flags as $i => $flag ) {
			if ( 'on' === $flag && isset( $ids[ $i ] ) ) {
				$out[] = $ids[ $i ];
			}
		}
		return implode( ',', $out );
	}

	public function render( $attrs, $content, $render_slug ) {
		$el   = $this->el();
		$atts = array();
		foreach ( $el['atts'] as $key => $att ) {
			if ( 'content' === $key ) {
				continue;
			}
			$v            = $this->props[ $key ] ?? $att['default'];
			$atts[ $key ] = 'memberships' === $att['type'] ? $this->checkbox_ids( $v ) : (string) $v;
		}
		$inner = isset( $el['atts']['content'] ) ? (string) ( isset( $this->content ) && '' !== $this->content ? $this->content : $content ) : '';
		$html  = MGM_Shortcodes::render( $this->mgm_tag, $atts, $inner );
		if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
			$html = MGM_Page_Builders::editor_placeholder( $el['label'], $html );
		}
		return $html;
	}
}
