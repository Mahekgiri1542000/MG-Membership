<?php
/**
 * Content protection engine (rules, per-post protection, partial content).
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Access {

	private static $rules        = null;
	private static $access_cache = array();

	public static function rule_types() {
		$types = array(
			'all_content'  => __( 'All content (posts, pages & custom post types)', 'mg-membership' ),
			'all_posts'    => __( 'All posts', 'mg-membership' ),
			'all_pages'    => __( 'All pages', 'mg-membership' ),
			'post_ids'     => __( 'Specific posts/pages (IDs)', 'mg-membership' ),
			'child_pages'  => __( 'Child pages of (page ID)', 'mg-membership' ),
			'category'     => __( 'Posts in categories (IDs or slugs)', 'mg-membership' ),
			'tag'          => __( 'Posts with tags (IDs or slugs)', 'mg-membership' ),
			'post_type'    => __( 'All items of a post type', 'mg-membership' ),
			'uri'          => __( 'URLs containing text', 'mg-membership' ),
			'uri_regex'    => __( 'URLs matching regular expression', 'mg-membership' ),
		);
		return apply_filters( 'mgm_rule_types', $types );
	}

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 999 );
		add_filter( 'get_the_excerpt', array( __CLASS__, 'filter_excerpt' ), 999, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ), 5 );
		add_filter( 'comments_open', array( __CLASS__, 'filter_comments_open' ), 20, 2 );
		add_filter( 'comments_array', array( __CLASS__, 'filter_comments' ), 20, 2 );
		// Block themes (Comments block), REST and widgets query comments directly – filter at query level.
		add_filter( 'comments_pre_query', array( __CLASS__, 'filter_comment_query' ), 10, 2 );
		add_filter( 'the_comments', array( __CLASS__, 'filter_comment_list' ), 10, 2 );
		add_filter( 'rest_prepare_comment', array( __CLASS__, 'filter_rest_comment' ), 10, 2 );
		add_action( 'save_post_mgm_rule', array( __CLASS__, 'flush_rules' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_rules' ) );

		if ( mgm_option( 'protect_feeds' ) ) {
			add_filter( 'the_content_feed', array( __CLASS__, 'filter_feed' ), 999 );
			add_filter( 'the_excerpt_rss', array( __CLASS__, 'filter_feed' ), 999 );
		}
		if ( mgm_option( 'protect_rest' ) ) {
			foreach ( array( 'post', 'page' ) as $pt ) {
				add_filter( 'rest_prepare_' . $pt, array( __CLASS__, 'filter_rest' ), 10, 3 );
			}
		}

		// Members: admin bar / wp-admin.
		add_action( 'after_setup_theme', array( __CLASS__, 'admin_bar' ) );
		add_action( 'admin_init', array( __CLASS__, 'block_admin' ) );
	}

	public static function flush_rules() {
		self::$rules        = null;
		self::$access_cache = array();
		delete_transient( 'mgm_rules' );
	}

	/**
	 * All rules (cached).
	 */
	public static function rules() {
		if ( null !== self::$rules ) {
			return self::$rules;
		}
		$cached = get_transient( 'mgm_rules' );
		if ( is_array( $cached ) ) {
			self::$rules = $cached;
			return $cached;
		}
		$rules = array();
		$posts = get_posts(
			array(
				'post_type'   => 'mgm_rule',
				'post_status' => 'publish',
				'numberposts' => -1,
			)
		);
		foreach ( $posts as $p ) {
			$rules[ $p->ID ] = array(
				'id'           => $p->ID,
				'type'         => get_post_meta( $p->ID, '_mgm_rule_type', true ),
				'target'       => get_post_meta( $p->ID, '_mgm_rule_target', true ),
				'exclude'      => mgm_int_list( get_post_meta( $p->ID, '_mgm_rule_exclude', true ) ),
				'memberships'  => mgm_int_list( get_post_meta( $p->ID, '_mgm_rule_memberships', true ) ),
				'message'      => get_post_meta( $p->ID, '_mgm_rule_message', true ),
				'drip_enabled' => get_post_meta( $p->ID, '_mgm_rule_drip_enabled', true ),
				'drip_amount'  => (int) get_post_meta( $p->ID, '_mgm_rule_drip_amount', true ),
				'drip_unit'    => get_post_meta( $p->ID, '_mgm_rule_drip_unit', true ),
				'drip_date'    => get_post_meta( $p->ID, '_mgm_rule_drip_date', true ),
				'exp_enabled'  => get_post_meta( $p->ID, '_mgm_rule_exp_enabled', true ),
				'exp_amount'   => (int) get_post_meta( $p->ID, '_mgm_rule_exp_amount', true ),
				'exp_unit'     => get_post_meta( $p->ID, '_mgm_rule_exp_unit', true ),
			);
		}
		set_transient( 'mgm_rules', $rules, DAY_IN_SECONDS );
		self::$rules = $rules;
		return $rules;
	}

	private static function list_values( $target ) {
		return array_filter( array_map( 'trim', explode( ',', (string) $target ) ) );
	}

	/**
	 * Does a rule match a given post?
	 */
	public static function rule_matches_post( array $rule, $post ) {
		$post = get_post( $post );
		if ( ! $post || in_array( (int) $post->ID, $rule['exclude'], true ) ) {
			return false;
		}
		// Never protect plugin core pages.
		$core = array( 'account', 'login', 'thankyou', 'pricing' );
		foreach ( $core as $c ) {
			if ( (int) mgm_option( 'page_' . $c ) === (int) $post->ID ) {
				return false;
			}
		}
		if ( in_array( $post->post_type, array( 'mgm_membership', 'mgm_rule', 'mgm_coupon' ), true ) ) {
			return false;
		}

		$match = false;
		switch ( $rule['type'] ) {
			case 'all_content':
				$match = true;
				break;
			case 'all_posts':
				$match = 'post' === $post->post_type;
				break;
			case 'all_pages':
				$match = 'page' === $post->post_type;
				break;
			case 'post_ids':
				$match = in_array( (int) $post->ID, mgm_int_list( $rule['target'] ), true );
				break;
			case 'child_pages':
				$ancestors = get_post_ancestors( $post );
				$match     = (bool) array_intersect( array_map( 'intval', $ancestors ), mgm_int_list( $rule['target'] ) );
				break;
			case 'category':
				$match = 'post' === $post->post_type && has_category( self::terms_arg( $rule['target'] ), $post );
				break;
			case 'tag':
				$match = 'post' === $post->post_type && has_tag( self::terms_arg( $rule['target'] ), $post );
				break;
			case 'post_type':
				$match = in_array( $post->post_type, self::list_values( $rule['target'] ), true );
				break;
			case 'uri':
			case 'uri_regex':
				// Resolve the post's pretty URL so URL rules protect it on every route, not only its permalink.
				$path  = (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH );
				$match = '' !== $path && self::rule_matches_uri( $rule, $path );
				break;
		}
		return (bool) apply_filters( 'mgm_rule_matches_post', $match, $rule, $post );
	}

	private static function terms_arg( $target ) {
		return array_map(
			function ( $v ) {
				return is_numeric( $v ) ? (int) $v : $v;
			},
			self::list_values( $target )
		);
	}

	public static function rule_matches_uri( array $rule, $uri ) {
		if ( 'uri' === $rule['type'] ) {
			foreach ( self::list_values( $rule['target'] ) as $needle ) {
				if ( false !== stripos( $uri, $needle ) ) {
					return true;
				}
			}
		} elseif ( 'uri_regex' === $rule['type'] && $rule['target'] ) {
			$pattern = '#' . str_replace( '#', '\#', $rule['target'] ) . '#i';
			return (bool) @preg_match( $pattern, $uri ); // phpcs:ignore
		}
		return false;
	}

	/**
	 * Rules protecting a post (including per-post membership lock from the edit screen).
	 */
	public static function rules_for_post( $post_id ) {
		$out = array();
		foreach ( self::rules() as $rule ) {
			if ( self::rule_matches_post( $rule, $post_id ) ) {
				$out[] = $rule;
			}
		}
		$direct = mgm_int_list( get_post_meta( $post_id, '_mgm_post_memberships', true ) );
		if ( $direct ) {
			$out[] = array(
				'id'           => 'post-' . $post_id,
				'type'         => 'post_ids',
				'target'       => (string) $post_id,
				'exclude'      => array(),
				'memberships'  => $direct,
				'message'      => get_post_meta( $post_id, '_mgm_post_message', true ),
				'drip_enabled' => '',
				'drip_amount'  => 0,
				'drip_unit'    => 'day',
				'exp_enabled'  => '',
				'exp_amount'   => 0,
				'exp_unit'     => 'day',
			);
		}
		return apply_filters( 'mgm_rules_for_post', $out, $post_id );
	}

	public static function is_protected( $post_id ) {
		return ! empty( self::rules_for_post( $post_id ) );
	}

	/**
	 * Does the user meet a single rule?
	 */
	public static function user_meets_rule( array $rule, $user_id ) {
		if ( ! $user_id ) {
			return false;
		}
		$active  = MGM_Member::active_membership_ids( $user_id );
		$matched = array_values( array_intersect( $rule['memberships'], $active ) );
		$ok      = ! empty( $matched );
		// Drip & expiration add-on hooks in here.
		return (bool) apply_filters( 'mgm_user_meets_rule', $ok, $rule, $user_id, $matched );
	}

	/**
	 * Main check: can a user view a post?
	 */
	public static function can_access_post( $post_id, $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		$post_id = (int) $post_id;
		$key     = $user_id . ':' . $post_id;
		if ( isset( self::$access_cache[ $key ] ) ) {
			return self::$access_cache[ $key ];
		}
		$result = true;
		if ( ! ( $user_id && user_can( $user_id, mgm_admin_cap() ) ) ) {
			$rules = self::rules_for_post( $post_id );
			if ( $rules ) {
				$result = false;
				foreach ( $rules as $rule ) {
					if ( self::user_meets_rule( $rule, $user_id ) ) {
						$result = true;
						break;
					}
				}
			}
			// Editors/authors who can edit this item may always preview it.
			if ( ! $result && $user_id && user_can( $user_id, 'edit_post', $post_id ) ) {
				$result = true;
			}
		}
		$result                      = (bool) apply_filters( 'mgm_can_access_post', $result, $post_id, $user_id );
		self::$access_cache[ $key ] = $result;
		return $result;
	}

	public static function can_access_uri( $uri, $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( $user_id && user_can( $user_id, mgm_admin_cap() ) ) {
			return true;
		}
		$protected = false;
		foreach ( self::rules() as $rule ) {
			if ( self::rule_matches_uri( $rule, $uri ) ) {
				$protected = true;
				if ( self::user_meets_rule( $rule, $user_id ) ) {
					return true;
				}
			}
		}
		return ! $protected;
	}

	/**
	 * Unauthorized markup.
	 */
	public static function unauthorized_html( $post_id = 0, $custom_message = '' ) {
		$message = $custom_message;
		if ( ! $message && $post_id ) {
			foreach ( self::rules_for_post( $post_id ) as $rule ) {
				if ( ! empty( $rule['message'] ) ) {
					$message = $rule['message'];
					break;
				}
			}
		}
		if ( ! $message ) {
			$message = mgm_option( 'unauthorized_message', __( 'This content is for members only.', 'mg-membership' ) );
		}
		$message = apply_filters( 'mgm_unauthorized_message', $message, $post_id );

		$memberships = array();
		if ( $post_id ) {
			foreach ( self::rules_for_post( $post_id ) as $rule ) {
				$memberships = array_merge( $memberships, $rule['memberships'] );
			}
		}

		return mgm_get_template(
			'unauthorized.php',
			array(
				'message'     => $message,
				'post_id'     => $post_id,
				'memberships' => array_values( array_filter( array_unique( $memberships ), 'mgm_is_public_membership' ) ),
				'show_login'  => mgm_option( 'show_login_form' ) && ! is_user_logged_in(),
			)
		);
	}

	public static function filter_content( $content ) {
		$post_id = get_the_ID();
		if ( ! $post_id || is_admin() || self::can_access_post( $post_id ) ) {
			return $content;
		}
		// Page builders render their own content – this prevents leaking it.
		$out    = '';
		$teaser = mgm_option( 'excerpt_on_protected' ) ? self::teaser( $post_id ) : '';
		if ( '' !== $teaser ) {
			$out .= '<div class="mgm-excerpt">' . wpautop( esc_html( $teaser ) ) . '</div>';
		}
		return $out . self::unauthorized_html( $post_id );
	}

	/**
	 * Safe teaser for locked content.
	 *
	 * - A hand-written excerpt is always used as-is (the author chose to publish it).
	 * - Otherwise an automatic teaser shows at most 40% of the content, capped at 55 words, so short
	 *   posts are never revealed in full. Below 10 words, no teaser is shown.
	 * - Course lessons get no automatic teaser at all (filter: mgm_auto_teaser).
	 */
	public static function teaser( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		if ( '' !== trim( (string) $post->post_excerpt ) ) {
			return $post->post_excerpt;
		}
		if ( ! apply_filters( 'mgm_auto_teaser', 'mgm_lesson' !== $post->post_type, $post ) ) {
			return '';
		}
		$words = preg_split( '/\s+/u', trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ), -1, PREG_SPLIT_NO_EMPTY );
		$limit = (int) min( 55, floor( count( $words ) * 0.4 ) );
		if ( $limit < 10 ) {
			return '';
		}
		return implode( ' ', array_slice( $words, 0, $limit ) ) . '…';
	}

	public static function filter_excerpt( $excerpt, $post = null ) {
		if ( is_admin() || ! $post || self::can_access_post( $post->ID ) ) {
			return $excerpt;
		}
		// Never let WordPress auto-build an excerpt from protected content.
		return mgm_option( 'excerpt_on_protected' ) ? self::teaser( $post->ID ) : '';
	}

	public static function filter_feed( $content ) {
		$post_id = get_the_ID();
		if ( $post_id && self::is_protected( $post_id ) ) {
			return __( 'This content is for members only.', 'mg-membership' );
		}
		return $content;
	}

	public static function filter_rest( $response, $post, $request ) {
		if ( ! self::can_access_post( $post->ID ) ) {
			$data = $response->get_data();
			if ( isset( $data['content']['rendered'] ) ) {
				$data['content']['rendered']  = '';
				$data['content']['protected'] = true;
			}
			$response->set_data( $data );
		}
		return $response;
	}

	public static function filter_comments_open( $open, $post_id ) {
		return $open && self::can_access_post( $post_id );
	}

	/**
	 * Recent-comments widgets, multi-post queries: remove comments on content the viewer can't access.
	 */
	public static function filter_comment_list( $comments, $query ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $comments;
		}
		return array_values(
			array_filter(
				(array) $comments,
				function ( $c ) {
					return ! is_object( $c ) || ! isset( $c->comment_post_ID ) || self::can_access_post( (int) $c->comment_post_ID );
				}
			)
		);
	}

	public static function filter_rest_comment( $response, $comment ) {
		if ( ! self::can_access_post( (int) $comment->comment_post_ID ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to read this comment.', 'mg-membership' ), array( 'status' => 403 ) );
		}
		return $response;
	}

	public static function filter_comments( $comments, $post_id ) {
		return self::can_access_post( $post_id ) ? $comments : array();
	}

	/**
	 * Return no comments for protected posts the viewer cannot access (front-end & REST only).
	 *
	 * @param array|int|null   $result Null to run the query normally.
	 * @param WP_Comment_Query $query  Query.
	 */
	public static function filter_comment_query( $result, $query ) {
		if ( null !== $result || ( is_admin() && ! wp_doing_ajax() ) ) {
			return $result;
		}
		$post_id = (int) ( $query->query_vars['post_id'] ?? 0 );
		if ( ! $post_id && ! empty( $query->query_vars['post__in'] ) && 1 === count( (array) $query->query_vars['post__in'] ) ) {
			$post_id = (int) current( (array) $query->query_vars['post__in'] );
		}
		if ( ! $post_id || self::can_access_post( $post_id ) ) {
			return $result;
		}
		return ! empty( $query->query_vars['count'] ) ? 0 : array();
	}

	public static function template_redirect() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore
		// URL rules match the path only – never the query string (e.g. ?mgm_redirect=…/protected-page).
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		// The plugin's own pages (login, account, thank-you, pricing) are never locked by URL rules.
		$core_page = false;
		if ( is_singular() ) {
			foreach ( array( 'account', 'login', 'thankyou', 'pricing' ) as $c ) {
				if ( (int) mgm_option( 'page_' . $c ) === (int) get_queried_object_id() ) {
					$core_page = true;
				}
			}
		}

		$blocked = ! $core_page && ! self::can_access_uri( $path );
		if ( ! $blocked && is_singular() && mgm_option( 'redirect_unauthorized' ) ) {
			$blocked = ! self::can_access_post( get_queried_object_id() );
		}
		if ( ! $blocked ) {
			return;
		}
		if ( mgm_option( 'redirect_unauthorized' ) || ! is_singular() ) {
			$url = mgm_option( 'unauthorized_redirect_url' );
			$url = $url ? $url : ( is_user_logged_in() ? mgm_page_url( 'pricing' ) : mgm_page_url( 'login' ) );
			$url = add_query_arg( 'mgm_redirect', rawurlencode( home_url( $uri ) ), $url );
			wp_safe_redirect( apply_filters( 'mgm_unauthorized_redirect', $url, $uri ) );
			exit;
		}
		// URI rule matched a singular page – content filter shows the message.
		if ( is_singular() ) {
			add_filter(
				'mgm_can_access_post',
				function ( $ok, $post_id ) {
					return get_queried_object_id() === (int) $post_id ? false : $ok;
				},
				10,
				2
			);
		}
	}

	public static function admin_bar() {
		if ( mgm_option( 'hide_admin_bar' ) && is_user_logged_in() && ! current_user_can( 'edit_posts' ) ) {
			show_admin_bar( false );
		}
	}

	public static function block_admin() {
		if ( mgm_option( 'block_wp_admin' ) && ! wp_doing_ajax() && ! current_user_can( 'edit_posts' ) ) {
			wp_safe_redirect( mgm_page_url( 'account' ) );
			exit;
		}
	}

	/**
	 * Generic check used by shortcodes & page builder visibility controls.
	 *
	 * @param array $args {
	 *   @type string $condition  member|nonmember|logged_in|guest|any_member.
	 *   @type int[]  $memberships Membership IDs.
	 * }
	 */
	public static function check_condition( $condition, $memberships = array(), $user_id = null ) {
		$user_id     = null === $user_id ? get_current_user_id() : (int) $user_id;
		$memberships = mgm_int_list( $memberships );
		switch ( $condition ) {
			case 'logged_in':
				return (bool) $user_id;
			case 'guest':
				return ! $user_id;
			case 'nonmember':
				return $memberships ? ! mgm_user_has_membership( $memberships, $user_id ) : ! mgm_user_has_membership( 'any', $user_id );
			case 'member':
			case 'any_member':
			default:
				if ( $user_id && user_can( $user_id, mgm_admin_cap() ) ) {
					return true;
				}
				return $memberships ? mgm_user_has_membership( $memberships, $user_id ) : mgm_user_has_membership( 'any', $user_id );
		}
	}
}
