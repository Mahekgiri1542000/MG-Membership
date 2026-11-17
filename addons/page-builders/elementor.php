<?php
/**
 * Elementor integration: widgets + membership visibility on every element.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class MGM_Elementor {

	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ) );
		add_action( 'elementor/element/after_section_end', array( __CLASS__, 'visibility_controls' ), 10, 3 );
		foreach ( array( 'widget', 'section', 'column', 'container' ) as $type ) {
			add_filter( "elementor/frontend/{$type}/should_render", array( __CLASS__, 'should_render' ), 10, 2 );
		}
		add_action( 'elementor/editor/after_enqueue_styles', array( __CLASS__, 'editor_styles' ) );

		// Elementor (3.22+) caches the HTML of "static" elements such as containers and serves it to every
		// visitor. Anything with membership visibility depends on who is viewing, so it must render live.
		add_filter( 'elementor/element/is_dynamic_content', array( __CLASS__, 'is_dynamic' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'purge_stale_cache' ), 30 );
	}

	public static function is_dynamic( $dynamic, $raw_data ) {
		return $dynamic || ! empty( $raw_data['settings']['mgm_condition'] );
	}

	/**
	 * One-time purge of element caches built before visibility elements were marked dynamic.
	 */
	public static function purge_stale_cache() {
		if ( 'v1' === get_option( 'mgm_elementor_cache_purged' ) ) {
			return;
		}
		delete_post_meta_by_key( '_elementor_element_cache' );
		update_option( 'mgm_elementor_cache_purged', 'v1', false );
	}

	public static function category( $manager ) {
		$manager->add_category(
			'mg-membership',
			array(
				'title' => __( 'MG Membership', 'mg-membership' ),
				'icon'  => 'eicon-lock-user',
			)
		);
	}

	public static function widgets( $widgets_manager ) {
		require_once __DIR__ . '/class-mgm-elementor-widget.php';
		foreach ( MGM_Page_Builders::elements() as $tag => $el ) {
			$widgets_manager->register( new MGM_Elementor_Widget( array(), array( 'mgm_tag' => $tag ) ) );
		}
	}

	public static function editor_styles() {
		wp_enqueue_style( 'mgm-frontend', MGM_URL . 'assets/css/frontend.css', array(), MGM_VERSION );
	}

	/**
	 * Add "MG Membership" visibility section to the Advanced tab of every element.
	 */
	public static function visibility_controls( $element, $section_id, $args ) {
		if ( '_section_responsive' !== $section_id ) {
			return;
		}
		$element->start_controls_section(
			'mgm_visibility_section',
			array(
				'label' => __( 'MG Membership Visibility', 'mg-membership' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);
		$element->add_control(
			'mgm_condition',
			array(
				'label'   => __( 'Show to', 'mg-membership' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''          => __( 'Everyone', 'mg-membership' ),
					'member'    => __( 'Members only', 'mg-membership' ),
					'nonmember' => __( 'Non-members only', 'mg-membership' ),
					'logged_in' => __( 'Logged-in users', 'mg-membership' ),
					'guest'     => __( 'Guests (logged out)', 'mg-membership' ),
				),
			)
		);
		$element->add_control(
			'mgm_memberships',
			array(
				'label'       => __( 'Memberships', 'mg-membership' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => mgm_membership_options(),
				'description' => __( 'Leave empty for any membership.', 'mg-membership' ),
				'condition'   => array( 'mgm_condition' => array( 'member', 'nonmember' ) ),
			)
		);
		$element->add_control(
			'mgm_show_message',
			array(
				'label'        => __( 'Show "members only" message instead', 'mg-membership' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'condition'    => array( 'mgm_condition' => array( 'member', 'logged_in' ) ),
			)
		);
		$element->end_controls_section();
	}

	public static function should_render( $should, $element ) {
		if ( ! $should ) {
			return $should;
		}
		$cond = $element->get_settings( 'mgm_condition' );
		if ( ! $cond ) {
			return $should;
		}
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return $should;
		}
		$ok = MGM_Access::check_condition( $cond, $element->get_settings( 'mgm_memberships' ) );
		if ( ! $ok && 'yes' === $element->get_settings( 'mgm_show_message' ) ) {
			static $printed = array();
			$id = $element->get_id();
			if ( empty( $printed[ $id ] ) ) {
				$printed[ $id ] = true;
				echo MGM_Access::unauthorized_html( get_the_ID() ); // phpcs:ignore
			}
		}
		return $ok;
	}
}
