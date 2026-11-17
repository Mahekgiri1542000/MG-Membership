<?php
/**
 * Generic Elementor widget driven by the MG Membership element registry.
 * One class, one instance per element (the element tag is carried in the widget args / widgetType).
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class MGM_Elementor_Widget extends Widget_Base {

	/** @var string */
	protected $mgm_tag = '';

	public function __construct( $data = array(), $args = null ) {
		if ( ! empty( $data['widgetType'] ) ) {
			$this->mgm_tag = $data['widgetType'];
		} elseif ( ! empty( $args['mgm_tag'] ) ) {
			$this->mgm_tag = $args['mgm_tag'];
		}
		parent::__construct( $data, $args );
	}

	private function el() {
		$registry = MGM_Page_Builders::elements();
		return isset( $registry[ $this->mgm_tag ] ) ? $registry[ $this->mgm_tag ] : array( 'label' => $this->mgm_tag, 'atts' => array(), 'icon' => 'eicon-lock-user' );
	}

	public function get_name() {
		return $this->mgm_tag;
	}

	public function get_title() {
		return $this->el()['label'];
	}

	public function get_icon() {
		return $this->el()['icon'] ?? 'eicon-lock-user';
	}

	public function get_categories() {
		return array( 'mg-membership' );
	}

	public function get_keywords() {
		return array( 'membership', 'member', 'mg', 'course', 'event', 'seminar', 'pricing', 'login', 'protect' );
	}

	public function get_style_depends() {
		return array( 'mgm-frontend' );
	}

	protected function register_controls() {
		$el = $this->el();
		$this->start_controls_section(
			'mgm_content',
			array(
				'label' => $el['label'],
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		if ( ! $el['atts'] ) {
			$this->add_control(
				'mgm_note',
				array(
					'type' => Controls_Manager::RAW_HTML,
					'raw'  => esc_html__( 'No settings needed. This element adapts to the visitor automatically.', 'mg-membership' ),
				)
			);
		}
		foreach ( $el['atts'] as $key => $att ) {
			$control = array(
				'label'   => $att['label'],
				'default' => $att['default'],
			);
			switch ( $att['type'] ) {
				case 'select':
					$control['type']    = Controls_Manager::SELECT;
					$control['options'] = $att['options'];
					break;
				case 'memberships':
					$control['type']        = Controls_Manager::SELECT2;
					$control['multiple']    = true;
					$control['label_block'] = true;
					$control['options']     = mgm_membership_options();
					$control['default']     = array();
					break;
				case 'number':
					$control['type'] = Controls_Manager::NUMBER;
					break;
				case 'editor':
					$control['type'] = Controls_Manager::WYSIWYG;
					break;
				case 'textarea':
					$control['type'] = Controls_Manager::TEXTAREA;
					break;
				default:
					$control['type']        = Controls_Manager::TEXT;
					$control['label_block'] = true;
			}
			$this->add_control( 'mgm_att_' . $key, $control );
		}
		$this->end_controls_section();

		// Basic style controls shared by all elements.
		$this->start_controls_section(
			'mgm_style',
			array(
				'label' => __( 'Colors', 'mg-membership' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'mgm_accent',
			array(
				'label'     => __( 'Accent / button color', 'mg-membership' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--mgm-accent: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'mgm_text',
			array(
				'label'     => __( 'Text color', 'mg-membership' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--mgm-text: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'mgm_surface',
			array(
				'label'     => __( 'Card background', 'mg-membership' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--mgm-surface: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'mgm_radius',
			array(
				'label'      => __( 'Corner radius', 'mg-membership' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--mgm-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$el       = $this->el();
		$atts     = array();
		foreach ( $el['atts'] as $key => $att ) {
			$v            = $settings[ 'mgm_att_' . $key ] ?? $att['default'];
			$atts[ $key ] = 'memberships' === $att['type'] ? MGM_Page_Builders::list_value( $v ) : (string) $v;
		}
		$content = '';
		if ( isset( $atts['content'] ) ) {
			$content = $atts['content'];
			unset( $atts['content'] );
		}
		$html = MGM_Shortcodes::render( $this->mgm_tag, $atts, $content );
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$html = MGM_Page_Builders::editor_placeholder( $el['label'], $html );
		}
		echo $html; // phpcs:ignore
	}
}
