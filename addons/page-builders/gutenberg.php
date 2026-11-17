<?php
/**
 * Gutenberg (block editor) integration.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Gutenberg {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 30 );
		add_filter( 'block_categories_all', array( __CLASS__, 'category' ) );
		add_filter( 'register_block_type_args', array( __CLASS__, 'visibility_attributes' ), 10, 2 );
		add_filter( 'render_block', array( __CLASS__, 'render_block_visibility' ), 10, 2 );
	}

	public static function block_name( $tag ) {
		return 'mg-membership/' . str_replace( '_', '-', preg_replace( '/^mgm_/', '', $tag ) );
	}

	public static function category( $cats ) {
		array_unshift(
			$cats,
			array(
				'slug'  => 'mg-membership',
				'title' => __( 'MG Membership', 'mg-membership' ),
				'icon'  => null,
			)
		);
		return $cats;
	}

	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		wp_register_script(
			'mgm-blocks',
			MGM_URL . 'assets/js/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-hooks', 'wp-compose', 'wp-i18n' ),
			MGM_VERSION,
			true
		);
		wp_register_style( 'mgm-blocks-editor', MGM_URL . 'assets/css/frontend.css', array(), MGM_VERSION );

		$blocks = array();
		foreach ( MGM_Page_Builders::elements() as $tag => $el ) {
			if ( 'mgm_protect' === $tag ) {
				continue; // Replaced by the container block below.
			}
			$name  = self::block_name( $tag );
			$attrs = array(
				'mgmCondition'   => array( 'type' => 'string', 'default' => '' ),
				'mgmMemberships' => array( 'type' => 'string', 'default' => '' ),
			);
			$fields = array();
			foreach ( $el['atts'] as $key => $att ) {
				$attrs[ $key ] = array( 'type' => 'string', 'default' => (string) $att['default'] );
				$fields[]      = array(
					'key'     => $key,
					'type'    => $att['type'],
					'label'   => $att['label'],
					'options' => self::options_for( $att ),
				);
			}
			register_block_type(
				$name,
				array(
					'api_version'     => 2,
					'title'           => $el['label'],
					'category'        => 'mg-membership',
					'attributes'      => $attrs,
					'editor_script'   => 'mgm-blocks',
					'editor_style'    => 'mgm-blocks-editor',
					'render_callback' => function ( $attributes ) use ( $tag, $el ) {
						unset( $attributes['mgmCondition'], $attributes['mgmMemberships'] );
						$html = MGM_Shortcodes::render( $tag, $attributes );
						if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
							$html = MGM_Page_Builders::editor_placeholder( $el['label'], $html );
						}
						return $html;
					},
				)
			);
			$blocks[] = array(
				'name'   => $name,
				'title'  => $el['label'],
				'fields' => $fields,
			);
		}

		// Container block: show inner blocks only to selected audience.
		register_block_type(
			'mg-membership/protected',
			array(
				'api_version'     => 2,
				'title'           => __( 'Members-only content', 'mg-membership' ),
				'category'        => 'mg-membership',
				'editor_script'   => 'mgm-blocks',
				'attributes'      => array(
					'condition'      => array( 'type' => 'string', 'default' => 'member' ),
					'memberships'    => array( 'type' => 'string', 'default' => '' ),
					'showMessage'    => array( 'type' => 'boolean', 'default' => true ),
					'mgmCondition'   => array( 'type' => 'string', 'default' => '' ),
					'mgmMemberships' => array( 'type' => 'string', 'default' => '' ),
				),
				'render_callback' => function ( $attributes, $content ) {
					if ( MGM_Access::check_condition( $attributes['condition'] ?? 'member', $attributes['memberships'] ?? '' ) ) {
						return $content;
					}
					if ( ! empty( $attributes['showMessage'] ) && in_array( $attributes['condition'] ?? 'member', array( 'member', 'logged_in' ), true ) ) {
						return MGM_Access::unauthorized_html( 0 );
					}
					return '';
				},
			)
		);

		$memberships = array();
		foreach ( mgm_membership_options() as $id => $title ) {
			$memberships[] = array( 'value' => (string) $id, 'label' => $title );
		}
		wp_localize_script(
			'mgm-blocks',
			'MGMBlocks',
			array(
				'blocks'      => $blocks,
				'memberships' => $memberships,
				'conditions'  => array(
					array( 'value' => '', 'label' => __( 'Everyone (no restriction)', 'mg-membership' ) ),
					array( 'value' => 'member', 'label' => __( 'Members only', 'mg-membership' ) ),
					array( 'value' => 'nonmember', 'label' => __( 'Non-members only', 'mg-membership' ) ),
					array( 'value' => 'logged_in', 'label' => __( 'Logged-in users', 'mg-membership' ) ),
					array( 'value' => 'guest', 'label' => __( 'Guests (logged out)', 'mg-membership' ) ),
				),
				'i18n'        => array(
					'visibility'  => __( 'Membership visibility', 'mg-membership' ),
					'showTo'      => __( 'Show this block to', 'mg-membership' ),
					'memberships' => __( 'Memberships (none = any)', 'mg-membership' ),
					'settings'    => __( 'Settings', 'mg-membership' ),
					'protected'   => __( 'Members-only content', 'mg-membership' ),
					'showMessage' => __( 'Show "members only" message when hidden', 'mg-membership' ),
					'hint'        => __( 'Content inside this block is only shown to the selected audience.', 'mg-membership' ),
				),
			)
		);
	}

	private static function options_for( $att ) {
		if ( 'memberships' === $att['type'] ) {
			$out = array();
			foreach ( mgm_membership_options() as $id => $t ) {
				$out[] = array( 'value' => (string) $id, 'label' => $t );
			}
			return $out;
		}
		if ( empty( $att['options'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $att['options'] as $v => $l ) {
			$out[] = array( 'value' => (string) $v, 'label' => $l );
		}
		return $out;
	}

	/**
	 * Make the visibility attributes valid on every block (avoids SSR validation errors).
	 */
	public static function visibility_attributes( $args, $name ) {
		if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}
		$args['attributes']['mgmCondition']   = array( 'type' => 'string', 'default' => '' );
		$args['attributes']['mgmMemberships'] = array( 'type' => 'string', 'default' => '' );
		return $args;
	}

	public static function render_block_visibility( $html, $block ) {
		$cond = $block['attrs']['mgmCondition'] ?? '';
		// Only people who can edit content see everything (editor preview / block renderer). Everyone else –
		// including anonymous REST API calls – gets the filtered output.
		if ( ! $cond || ( ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) && current_user_can( 'edit_posts' ) ) ) {
			return $html;
		}
		return MGM_Access::check_condition( $cond, $block['attrs']['mgmMemberships'] ?? '' ) ? $html : '';
	}
}
