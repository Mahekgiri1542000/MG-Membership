<?php
/**
 * Shortcodes. The registry below also drives every page-builder integration
 * (Gutenberg, Elementor, Divi, WPBakery, Beaver Builder), so any element
 * added here automatically appears in all builders.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Shortcodes {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
	}

	/**
	 * Element registry.
	 *
	 * Each element: label, icon, callback, atts => [ key => [type, label, default, options] ], enclosing (bool).
	 */
	public static function registry() {
		static $cache = null;
		if ( null !== $cache && did_action( 'init' ) ) {
			return $cache;
		}
		$memberships = array( '' => __( 'All / any', 'mg-membership' ) ) + mgm_membership_options();

		$elements = array(
			'mgm_pricing'     => array(
				'label'    => __( 'Membership Pricing Table', 'mg-membership' ),
				'icon'     => 'eicon-price-table',
				'callback' => array( __CLASS__, 'pricing' ),
				'atts'     => array(
					'ids'         => array( 'type' => 'memberships', 'label' => __( 'Memberships (empty = all)', 'mg-membership' ), 'default' => '' ),
					'columns'     => array( 'type' => 'select', 'label' => __( 'Columns', 'mg-membership' ), 'default' => '3', 'options' => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ) ),
					'style'       => array( 'type' => 'select', 'label' => __( 'Style', 'mg-membership' ), 'default' => 'cards', 'options' => array( 'cards' => __( 'Cards', 'mg-membership' ), 'minimal' => __( 'Minimal', 'mg-membership' ), 'bold' => __( 'Bold', 'mg-membership' ) ) ),
					'button_text' => array( 'type' => 'text', 'label' => __( 'Button text (blank = per membership)', 'mg-membership' ), 'default' => '' ),
				),
			),
			'mgm_checkout'    => array(
				'label'    => __( 'Registration / Checkout Form', 'mg-membership' ),
				'icon'     => 'eicon-form-horizontal',
				'callback' => array( __CLASS__, 'checkout' ),
				'atts'     => array(
					'id' => array( 'type' => 'select', 'label' => __( 'Membership', 'mg-membership' ), 'default' => '', 'options' => $memberships ),
				),
			),
			'mgm_buy_button'  => array(
				'label'    => __( 'Membership Buy Button', 'mg-membership' ),
				'icon'     => 'eicon-button',
				'callback' => array( __CLASS__, 'buy_button' ),
				'atts'     => array(
					'id'   => array( 'type' => 'select', 'label' => __( 'Membership', 'mg-membership' ), 'default' => '', 'options' => $memberships ),
					'text' => array( 'type' => 'text', 'label' => __( 'Button text', 'mg-membership' ), 'default' => __( 'Join now', 'mg-membership' ) ),
				),
			),
			'mgm_account'     => array(
				'label'    => __( 'Member Account', 'mg-membership' ),
				'icon'     => 'eicon-person',
				'callback' => array( __CLASS__, 'account' ),
				'atts'     => array(),
			),
			'mgm_login'       => array(
				'label'    => __( 'Login Form', 'mg-membership' ),
				'icon'     => 'eicon-lock-user',
				'callback' => array( __CLASS__, 'login' ),
				'atts'     => array(
					'redirect' => array( 'type' => 'text', 'label' => __( 'Redirect URL after login', 'mg-membership' ), 'default' => '' ),
				),
			),
			'mgm_thankyou'    => array(
				'label'    => __( 'Thank You / Receipt', 'mg-membership' ),
				'icon'     => 'eicon-check-circle',
				'callback' => array( __CLASS__, 'thankyou' ),
				'atts'     => array(),
			),
			'mgm_protect'     => array(
				'label'     => __( 'Protected Content', 'mg-membership' ),
				'icon'      => 'eicon-lock',
				'callback'  => array( __CLASS__, 'protect' ),
				'enclosing' => true,
				'atts'      => array(
					'if'          => array( 'type' => 'select', 'label' => __( 'Show content to', 'mg-membership' ), 'default' => 'member', 'options' => array( 'member' => __( 'Members (of selected)', 'mg-membership' ), 'nonmember' => __( 'Non-members', 'mg-membership' ), 'logged_in' => __( 'Logged-in users', 'mg-membership' ), 'guest' => __( 'Guests', 'mg-membership' ) ) ),
					'memberships' => array( 'type' => 'memberships', 'label' => __( 'Memberships', 'mg-membership' ), 'default' => '' ),
					'message'     => array( 'type' => 'select', 'label' => __( 'When hidden', 'mg-membership' ), 'default' => 'show', 'options' => array( 'show' => __( 'Show unauthorized message', 'mg-membership' ), 'hide' => __( 'Show nothing', 'mg-membership' ) ) ),
					'content'     => array( 'type' => 'editor', 'label' => __( 'Content', 'mg-membership' ), 'default' => '' ),
				),
			),
			'mgm_member_info' => array(
				'label'    => __( 'Member Info', 'mg-membership' ),
				'icon'     => 'eicon-info-circle',
				'callback' => array( __CLASS__, 'member_info' ),
				'atts'     => array(
					'field' => array( 'type' => 'select', 'label' => __( 'Field', 'mg-membership' ), 'default' => 'display_name', 'options' => array( 'display_name' => __( 'Display name', 'mg-membership' ), 'first_name' => __( 'First name', 'mg-membership' ), 'last_name' => __( 'Last name', 'mg-membership' ), 'user_email' => __( 'Email', 'mg-membership' ), 'memberships' => __( 'Active memberships', 'mg-membership' ), 'registered' => __( 'Member since', 'mg-membership' ) ) ),
				),
			),
			'mgm_membership_price' => array(
				'label'    => __( 'Membership Price', 'mg-membership' ),
				'icon'     => 'eicon-product-price',
				'callback' => array( __CLASS__, 'membership_price' ),
				'atts'     => array(
					'id' => array( 'type' => 'select', 'label' => __( 'Membership', 'mg-membership' ), 'default' => '', 'options' => $memberships ),
				),
			),
		);
		$elements = apply_filters( 'mgm_shortcodes_registry', $elements );
		if ( did_action( 'init' ) ) {
			$cache = $elements;
		}
		return $elements;
	}

	public static function register() {
		foreach ( self::registry() as $tag => $el ) {
			add_shortcode( $tag, $el['callback'] );
		}
		// Aliases.
		add_shortcode( 'mgm_show', array( __CLASS__, 'protect' ) );
		add_shortcode( 'mgm_hide', array( __CLASS__, 'hide' ) );
	}

	/**
	 * Render a registry element with given attributes (used by page builders).
	 */
	public static function render( $tag, array $atts = array(), $content = '' ) {
		$registry = self::registry();
		if ( ! isset( $registry[ $tag ] ) ) {
			return '';
		}
		if ( isset( $atts['content'] ) && '' === $content ) {
			$content = $atts['content'];
			unset( $atts['content'] );
		}
		foreach ( $atts as $k => $v ) {
			if ( is_array( $v ) ) {
				$atts[ $k ] = implode( ',', $v );
			}
		}
		return call_user_func( $registry[ $tag ]['callback'], $atts, $content, $tag );
	}

	public static function defaults( $tag ) {
		$registry = self::registry();
		$out      = array();
		if ( isset( $registry[ $tag ] ) ) {
			foreach ( $registry[ $tag ]['atts'] as $k => $a ) {
				if ( 'content' !== $k ) {
					$out[ $k ] = $a['default'];
				}
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */

	public static function pricing( $atts ) {
		$atts = shortcode_atts( self::defaults( 'mgm_pricing' ), (array) $atts, 'mgm_pricing' );
		$ids  = mgm_int_list( $atts['ids'] );
		$args = array(
			'post_type'   => 'mgm_membership',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => $ids ? 'post__in' : 'menu_order',
			'order'       => 'ASC',
		);
		if ( $ids ) {
			$args['post__in'] = $ids;
		}
		$posts = array_filter(
			get_posts( apply_filters( 'mgm_pricing_query_args', $args, $atts ) ),
			function ( $p ) use ( $ids ) {
				return $ids || mgm_is_public_membership( $p->ID );
			}
		);
		$memberships = array_map(
			function ( $p ) {
				return new MGM_Membership( $p->ID );
			},
			array_values( $posts )
		);
		return mgm_get_template(
			'pricing.php',
			array(
				'memberships' => $memberships,
				'columns'     => max( 1, min( 4, (int) $atts['columns'] ) ),
				'style'       => sanitize_html_class( $atts['style'] ),
				'button_text' => $atts['button_text'],
			)
		);
	}

	public static function checkout( $atts ) {
		$atts = shortcode_atts( self::defaults( 'mgm_checkout' ), (array) $atts, 'mgm_checkout' );
		$id   = absint( $atts['id'] );
		if ( ! $id && is_singular( 'mgm_membership' ) ) {
			$id = get_the_ID();
		}
		if ( ! $id && isset( $_GET['membership'] ) ) { // phpcs:ignore
			$id = absint( $_GET['membership'] ); // phpcs:ignore
		}
		return $id ? MGM_Checkout::render_form( $id ) : '';
	}

	public static function buy_button( $atts ) {
		$atts = shortcode_atts( self::defaults( 'mgm_buy_button' ), (array) $atts, 'mgm_buy_button' );
		$m    = new MGM_Membership( absint( $atts['id'] ) );
		if ( ! $m->exists() ) {
			return '';
		}
		return sprintf( '<a class="mgm-button" href="%s">%s</a>', esc_url( $m->url() ), esc_html( $atts['text'] ) );
	}

	public static function account() {
		return MGM_Account::render();
	}

	public static function login( $atts ) {
		$atts = shortcode_atts( self::defaults( 'mgm_login' ), (array) $atts, 'mgm_login' );
		return MGM_Account::render_login( $atts['redirect'] );
	}

	public static function thankyou() {
		$num = isset( $_GET['mgm_txn'] ) ? sanitize_text_field( wp_unslash( $_GET['mgm_txn'] ) ) : ''; // phpcs:ignore
		$txn = $num ? MGM_Transaction::get_by_trans_num( $num ) : null;
		if ( $txn && (int) $txn->user_id !== get_current_user_id() && ! current_user_can( mgm_admin_cap() ) ) {
			$txn = null;
		}
		return mgm_get_template( 'thankyou.php', array( 'txn' => $txn ) );
	}

	public static function protect( $atts, $content = '' ) {
		$atts = shortcode_atts(
			array(
				'if'          => 'member',
				'memberships' => '',
				'message'     => 'show',
				'unauth'      => '', // Legacy alias of message.
			),
			(array) $atts
		);
		if ( MGM_Access::check_condition( $atts['if'], $atts['memberships'] ) ) {
			return do_shortcode( $content );
		}
		if ( 'hide' === $atts['message'] || 'hide' === $atts['unauth'] || in_array( $atts['if'], array( 'guest', 'nonmember' ), true ) ) {
			return '';
		}
		return MGM_Access::unauthorized_html( 0 );
	}

	public static function hide( $atts, $content = '' ) {
		$atts = shortcode_atts( array( 'memberships' => '' ), (array) $atts );
		return MGM_Access::check_condition( 'member', $atts['memberships'] ) ? '' : do_shortcode( $content );
	}

	public static function member_info( $atts ) {
		$atts = shortcode_atts( self::defaults( 'mgm_member_info' ), (array) $atts, 'mgm_member_info' );
		$user = wp_get_current_user();
		if ( ! $user->ID ) {
			return '';
		}
		// Never print secrets (password hash, session tokens, activation keys, arbitrary meta).
		$allowed = (array) apply_filters( 'mgm_member_info_allowed_fields', array() );
		if ( ! in_array( $atts['field'], $allowed, true ) ) {
			return '';
		}
		switch ( $atts['field'] ) {
			case 'memberships':
				$names = array_map(
					function ( $id ) {
						return get_the_title( $id );
					},
					MGM_Member::active_membership_ids( $user->ID )
				);
				return esc_html( implode( ', ', $names ) );
			case 'registered':
				return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) );
			default:
				return esc_html( (string) $user->get( sanitize_key( $atts['field'] ) ) );
		}
	}

	public static function membership_price( $atts ) {
		$atts = shortcode_atts( self::defaults( 'mgm_membership_price' ), (array) $atts, 'mgm_membership_price' );
		$m    = new MGM_Membership( absint( $atts['id'] ) );
		return $m->exists() ? '<span class="mgm-price-terms">' . esc_html( $m->price_terms() ) . '</span>' : '';
	}
}
