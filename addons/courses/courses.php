<?php
/**
 * Add-on: Courses (LMS) – courses, sections, lessons, quizzes & progress.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-mgm-quiz.php';

class MGM_Courses {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 6 );
		add_filter( 'mgm_rules_for_post', array( __CLASS__, 'lesson_rules' ), 10, 2 );
		add_filter( 'mgm_user_meets_rule', array( __CLASS__, 'open_rule' ), 5, 2 );
		add_filter( 'the_content', array( __CLASS__, 'content' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ), 4 );
		add_filter( 'mgm_shortcodes_registry', array( __CLASS__, 'shortcodes' ) );
		add_filter( 'mgm_account_tabs', array( __CLASS__, 'account_tab' ) );
		add_action( 'mgm_account_tab_courses', array( __CLASS__, 'account_tab_content' ) );
		add_action( 'mgm_account_dashboard', array( __CLASS__, 'dashboard_widget' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'mgm_email_templates', array( __CLASS__, 'email_templates' ) );
		add_action( 'mgm_course_completed', array( __CLASS__, 'send_completed_email' ), 20, 2 );

		if ( is_admin() ) {
			require_once __DIR__ . '/class-mgm-courses-admin.php';
			MGM_Courses_Admin::init();
		}
		MGM_Quiz::init();
	}

	public static function register() {
		register_post_type(
			'mgm_course',
			array(
				'labels'       => array(
					'name'          => __( 'Courses', 'mg-membership' ),
					'singular_name' => __( 'Course', 'mg-membership' ),
					'add_new_item'  => __( 'Add New Course', 'mg-membership' ),
					'edit_item'     => __( 'Edit Course', 'mg-membership' ),
					'all_items'     => __( 'All Courses', 'mg-membership' ),
					'menu_name'     => __( 'Courses', 'mg-membership' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-welcome-learn-more',
				'menu_position' => 27,
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'elementor', 'comments' ),
				'rewrite'      => array( 'slug' => apply_filters( 'mgm_course_slug', 'courses' ) ),
			)
		);
		register_post_type(
			'mgm_lesson',
			array(
				'labels'       => array(
					'name'          => __( 'Lessons', 'mg-membership' ),
					'singular_name' => __( 'Lesson', 'mg-membership' ),
					'add_new_item'  => __( 'Add New Lesson', 'mg-membership' ),
					'edit_item'     => __( 'Edit Lesson', 'mg-membership' ),
					'all_items'     => __( 'Lessons & Quizzes', 'mg-membership' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'show_in_menu' => 'edit.php?post_type=mgm_course',
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'elementor', 'comments' ),
				'rewrite'      => array( 'slug' => apply_filters( 'mgm_lesson_slug', 'lesson' ) ),
			)
		);
		register_taxonomy(
			'mgm_course_cat',
			'mgm_course',
			array(
				'label'        => __( 'Course Categories', 'mg-membership' ),
				'hierarchical' => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'course-category' ),
			)
		);
	}

	public static function email_templates( $t ) {
		$t['course_completed'] = array(
			'label'   => __( 'Course completed (student)', 'mg-membership' ),
			'subject' => __( 'Congratulations on completing {course}!', 'mg-membership' ),
			'body'    => __( "Hi {first_name},\n\nYou completed {course} – well done!\n{certificate_line}\nKeep learning: {courses_url}\n\n{site_name}", 'mg-membership' ),
		);
		return $t;
	}

	public static function send_completed_email( $user_id, $course_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$line = '';
		if ( class_exists( 'MGM_Certificates' ) && MGM_Certificates::enabled( $course_id ) ) {
			$code = MGM_Certificates::issue( $user_id, $course_id );
			$line = sprintf( __( 'Your certificate: %s', 'mg-membership' ), MGM_Certificates::url( $code ) );
		}
		MGM_Emails::send(
			'course_completed',
			$user->user_email,
			array_merge(
				MGM_Emails::user_vars( $user_id ),
				array(
					'course'           => get_the_title( $course_id ),
					'certificate_line' => $line,
					'courses_url'      => mgm_page_url( 'courses' ),
				)
			)
		);
	}

	/* --------------------------- Data helpers -------------------------- */

	/**
	 * Curriculum: [ ['title' => 'Section', 'lessons' => [ids]], ... ]
	 */
	public static function curriculum( $course_id ) {
		$c = get_post_meta( $course_id, '_mgm_curriculum', true );
		$c = is_array( $c ) ? $c : array();
		foreach ( $c as $i => $section ) {
			$c[ $i ]['lessons'] = array_values(
				array_filter(
					mgm_int_list( $section['lessons'] ?? array() ),
					function ( $id ) {
						return 'publish' === get_post_status( $id );
					}
				)
			);
		}
		return $c;
	}

	public static function lesson_ids( $course_id ) {
		$ids = array();
		foreach ( self::curriculum( $course_id ) as $s ) {
			$ids = array_merge( $ids, $s['lessons'] );
		}
		return array_values( array_unique( $ids ) );
	}

	public static function lesson_course( $lesson_id ) {
		return (int) get_post_meta( $lesson_id, '_mgm_course_id', true );
	}

	public static function course_meta( $course_id, $key, $default = '' ) {
		$v = get_post_meta( $course_id, '_mgm_course_' . $key, true );
		return '' === $v ? $default : $v;
	}

	/**
	 * Access type: free (public), open (any logged-in user), members (selected memberships).
	 */
	public static function access_type( $course_id ) {
		return self::course_meta( $course_id, 'access', 'members' );
	}

	public static function course_memberships( $course_id ) {
		return mgm_int_list( self::course_meta( $course_id, 'memberships', array() ) );
	}

	public static function can_access_course( $course_id, $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( $user_id && user_can( $user_id, 'edit_post', $course_id ) ) {
			return true;
		}
		switch ( self::access_type( $course_id ) ) {
			case 'free':
				return true;
			case 'open':
				return (bool) $user_id;
		}
		$ms = self::course_memberships( $course_id );
		return $ms ? mgm_user_has_membership( $ms, $user_id ) : (bool) $user_id && mgm_user_has_membership( 'any', $user_id );
	}

	/**
	 * Protect lessons through the core access engine (works with Elementor/Divi/etc.).
	 */
	public static function lesson_rules( $rules, $post_id ) {
		if ( 'mgm_lesson' !== get_post_type( $post_id ) ) {
			return $rules;
		}
		if ( get_post_meta( $post_id, '_mgm_preview', true ) ) {
			return array(); // Free preview lesson.
		}
		$course = self::lesson_course( $post_id );
		if ( ! $course || 'free' === self::access_type( $course ) ) {
			return $rules;
		}
		$rules[] = array(
			'id'           => 'course-' . $course,
			'type'         => 'course',
			'target'       => (string) $course,
			'exclude'      => array(),
			'memberships'  => self::course_memberships( $course ),
			'message'      => self::course_meta( $course, 'locked_message' ),
			'course_open'  => 'open' === self::access_type( $course ),
			'course_any'   => ! self::course_memberships( $course ),
			'drip_enabled' => '',
			'drip_amount'  => 0,
			'drip_unit'    => 'day',
			'exp_enabled'  => '',
			'exp_amount'   => 0,
			'exp_unit'     => 'day',
		);
		return $rules;
	}

	public static function open_rule( $ok, $rule ) {
		if ( ! empty( $rule['course_open'] ) ) {
			return is_user_logged_in();
		}
		if ( ! empty( $rule['course_any'] ) ) {
			return mgm_user_has_membership( 'any' );
		}
		return $ok;
	}

	/* ----------------------------- Progress ---------------------------- */

	public static function completed_lessons( $user_id, $course_id ) {
		global $wpdb;
		return array_map(
			'intval',
			$wpdb->get_col( $wpdb->prepare( 'SELECT lesson_id FROM ' . MGM_Install::table( 'course_progress' ) . ' WHERE user_id = %d AND course_id = %d', $user_id, $course_id ) ) // phpcs:ignore
		);
	}

	public static function is_completed( $user_id, $lesson_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . MGM_Install::table( 'course_progress' ) . ' WHERE user_id = %d AND lesson_id = %d', $user_id, $lesson_id ) ); // phpcs:ignore
	}

	public static function progress( $user_id, $course_id ) {
		$all = self::lesson_ids( $course_id );
		if ( ! $all || ! $user_id ) {
			return 0;
		}
		$done = array_intersect( $all, self::completed_lessons( $user_id, $course_id ) );
		return (int) round( count( $done ) / count( $all ) * 100 );
	}

	public static function complete_lesson( $user_id, $lesson_id ) {
		global $wpdb;
		$course = self::lesson_course( $lesson_id );
		if ( ! $course || self::is_completed( $user_id, $lesson_id ) ) {
			return;
		}
		$wpdb->insert( // phpcs:ignore
			MGM_Install::table( 'course_progress' ),
			array(
				'user_id'      => $user_id,
				'course_id'    => $course,
				'lesson_id'    => $lesson_id,
				'completed_at' => mgm_now(),
			)
		);
		self::enroll( $user_id, $course );
		do_action( 'mgm_lesson_completed', $user_id, $lesson_id, $course );
		if ( 100 === self::progress( $user_id, $course ) && ! get_user_meta( $user_id, '_mgm_course_completed_' . $course, true ) ) {
			update_user_meta( $user_id, '_mgm_course_completed_' . $course, time() );
			do_action( 'mgm_course_completed', $user_id, $course );
		}
	}

	public static function reset_progress( $user_id, $course_id ) {
		global $wpdb;
		$wpdb->delete( MGM_Install::table( 'course_progress' ), array( 'user_id' => $user_id, 'course_id' => $course_id ) ); // phpcs:ignore
		delete_user_meta( $user_id, '_mgm_course_completed_' . $course_id );
	}

	public static function enroll( $user_id, $course_id ) {
		if ( ! get_user_meta( $user_id, '_mgm_enrolled_' . $course_id, true ) ) {
			update_user_meta( $user_id, '_mgm_enrolled_' . $course_id, time() );
			do_action( 'mgm_course_enrolled', $user_id, $course_id );
		}
	}

	public static function enrolled_courses( $user_id ) {
		global $wpdb;
		$keys = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s", $user_id, $wpdb->esc_like( '_mgm_enrolled_' ) . '%' ) ); // phpcs:ignore
		return array_values(
			array_filter(
				array_map(
					function ( $k ) {
						return (int) str_replace( '_mgm_enrolled_', '', $k );
					},
					$keys
				),
				function ( $id ) {
					return 'publish' === get_post_status( $id );
				}
			)
		);
	}

	/**
	 * Sequential mode: lesson locked until all previous lessons are complete.
	 */
	public static function is_sequence_locked( $user_id, $lesson_id ) {
		$course = self::lesson_course( $lesson_id );
		if ( ! $course || ! self::course_meta( $course, 'sequential' ) || user_can( $user_id, 'edit_post', $course ) ) {
			return false;
		}
		$ids  = self::lesson_ids( $course );
		$pos  = array_search( (int) $lesson_id, $ids, true );
		$done = self::completed_lessons( $user_id, $course );
		if ( false === $pos ) {
			return false;
		}
		foreach ( array_slice( $ids, 0, $pos ) as $prev ) {
			if ( ! in_array( $prev, $done, true ) ) {
				return $prev;
			}
		}
		return false;
	}

	public static function next_lesson( $user_id, $course_id ) {
		$ids  = self::lesson_ids( $course_id );
		$done = self::completed_lessons( $user_id, $course_id );
		foreach ( $ids as $id ) {
			if ( ! in_array( $id, $done, true ) ) {
				return $id;
			}
		}
		return $ids ? $ids[0] : 0;
	}

	public static function adjacent( $lesson_id ) {
		$ids = self::lesson_ids( self::lesson_course( $lesson_id ) );
		$pos = array_search( (int) $lesson_id, $ids, true );
		if ( false === $pos ) {
			return array( 0, 0 );
		}
		return array( $ids[ $pos - 1 ] ?? 0, $ids[ $pos + 1 ] ?? 0 );
	}

	/* ---------------------------- Rendering ---------------------------- */

	public static function body_class( $classes ) {
		if ( is_singular( 'mgm_lesson' ) ) {
			$classes[] = 'mgm-lesson-page';
		}
		return $classes;
	}

	public static function content( $content ) {
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( is_singular( 'mgm_course' ) && ! get_post_meta( get_the_ID(), '_mgm_custom_layout', true ) ) {
			return mgm_get_template( 'courses/single-course.php', array( 'course_id' => get_the_ID(), 'content' => $content ) );
		}
		if ( is_singular( 'mgm_lesson' ) ) {
			$lesson_id = get_the_ID();
			if ( ! MGM_Access::can_access_post( $lesson_id ) ) {
				return $content; // Core engine replaces it with the unauthorized message.
			}
			$blocker = self::is_sequence_locked( get_current_user_id(), $lesson_id );
			return mgm_get_template(
				'courses/lesson.php',
				array(
					'lesson_id' => $lesson_id,
					'course_id' => self::lesson_course( $lesson_id ),
					'content'   => $content,
					'blocker'   => $blocker,
				)
			);
		}
		return $content;
	}

	public static function video_embed( $url ) {
		if ( ! $url ) {
			return '';
		}
		$embed = wp_oembed_get( $url );
		if ( $embed ) {
			return '<div class="mgm-video">' . $embed . '</div>';
		}
		if ( preg_match( '/\.(mp4|webm|ogg)(\?.*)?$/i', $url ) ) {
			return '<div class="mgm-video">' . wp_video_shortcode( array( 'src' => $url ) ) . '</div>';
		}
		return '<div class="mgm-video"><iframe src="' . esc_url( $url ) . '" allowfullscreen loading="lazy"></iframe></div>';
	}

	public static function curriculum_html( $course_id, $current = 0 ) {
		return mgm_get_template(
			'courses/curriculum.php',
			array(
				'course_id' => $course_id,
				'current'   => $current,
			)
		);
	}

	public static function handle_actions() {
		if ( empty( $_POST['mgm_course_action'] ) || ! is_user_logged_in() ) {
			return;
		}
		if ( ! isset( $_POST['_mgm_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_nonce'] ), 'mgm_course_action' ) ) {
			return;
		}
		$user_id = get_current_user_id();
		$action  = sanitize_key( $_POST['mgm_course_action'] );

		if ( 'complete' === $action ) {
			$lesson = absint( $_POST['lesson_id'] ?? 0 );
			if ( MGM_Access::can_access_post( $lesson ) && ! self::is_sequence_locked( $user_id, $lesson ) && 'quiz' !== get_post_meta( $lesson, '_mgm_lesson_type', true ) ) {
				self::complete_lesson( $user_id, $lesson );
				list( , $next ) = self::adjacent( $lesson );
				wp_safe_redirect( $next ? get_permalink( $next ) : get_permalink( self::lesson_course( $lesson ) ) );
				exit;
			}
		}

		if ( 'start' === $action ) {
			$course = absint( $_POST['course_id'] ?? 0 );
			if ( self::can_access_course( $course ) ) {
				self::enroll( $user_id, $course );
				$next = self::next_lesson( $user_id, $course );
				wp_safe_redirect( $next ? get_permalink( $next ) : get_permalink( $course ) );
				exit;
			}
		}

		if ( 'reset' === $action ) {
			$course = absint( $_POST['course_id'] ?? 0 );
			if ( self::course_meta( $course, 'allow_reset' ) ) {
				self::reset_progress( $user_id, $course );
				wp_safe_redirect( get_permalink( $course ) );
				exit;
			}
		}
	}

	/* ---------------------------- Shortcodes --------------------------- */

	public static function shortcodes( $registry ) {
		$courses = array( '' => __( 'Current course', 'mg-membership' ) );
		foreach ( get_posts( array( 'post_type' => 'mgm_course', 'numberposts' => 200, 'post_status' => 'publish' ) ) as $p ) {
			$courses[ $p->ID ] = $p->post_title;
		}
		$cats = array( '' => __( 'All categories', 'mg-membership' ) );
		$terms = get_terms( array( 'taxonomy' => 'mgm_course_cat', 'hide_empty' => false ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$cats[ $t->slug ] = $t->name;
			}
		}

		$registry['mgm_courses']           = array(
			'label'    => __( 'Courses Grid', 'mg-membership' ),
			'icon'     => 'eicon-gallery-grid',
			'callback' => array( __CLASS__, 'sc_courses' ),
			'atts'     => array(
				'category' => array( 'type' => 'select', 'label' => __( 'Category', 'mg-membership' ), 'default' => '', 'options' => $cats ),
				'columns'  => array( 'type' => 'select', 'label' => __( 'Columns', 'mg-membership' ), 'default' => '3', 'options' => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ) ),
				'limit'    => array( 'type' => 'number', 'label' => __( 'Number of courses', 'mg-membership' ), 'default' => '12' ),
				'orderby'  => array( 'type' => 'select', 'label' => __( 'Order by', 'mg-membership' ), 'default' => 'date', 'options' => array( 'date' => __( 'Newest', 'mg-membership' ), 'title' => __( 'Title', 'mg-membership' ), 'menu_order' => __( 'Custom order', 'mg-membership' ) ) ),
			),
		);
		$registry['mgm_my_courses']        = array(
			'label'    => __( 'My Courses', 'mg-membership' ),
			'icon'     => 'eicon-library-open',
			'callback' => array( __CLASS__, 'sc_my_courses' ),
			'atts'     => array(),
		);
		$registry['mgm_course_curriculum'] = array(
			'label'    => __( 'Course Curriculum', 'mg-membership' ),
			'icon'     => 'eicon-bullet-list',
			'callback' => array( __CLASS__, 'sc_curriculum' ),
			'atts'     => array(
				'id' => array( 'type' => 'select', 'label' => __( 'Course', 'mg-membership' ), 'default' => '', 'options' => $courses ),
			),
		);
		$registry['mgm_course_progress']   = array(
			'label'    => __( 'Course Progress Bar', 'mg-membership' ),
			'icon'     => 'eicon-skill-bar',
			'callback' => array( __CLASS__, 'sc_progress' ),
			'atts'     => array(
				'id' => array( 'type' => 'select', 'label' => __( 'Course', 'mg-membership' ), 'default' => '', 'options' => $courses ),
			),
		);
		$registry['mgm_course_button']     = array(
			'label'    => __( 'Course Start / Continue Button', 'mg-membership' ),
			'icon'     => 'eicon-button',
			'callback' => array( __CLASS__, 'sc_button' ),
			'atts'     => array(
				'id' => array( 'type' => 'select', 'label' => __( 'Course', 'mg-membership' ), 'default' => '', 'options' => $courses ),
			),
		);
		return $registry;
	}

	private static function course_from_atts( $atts ) {
		$id = absint( $atts['id'] ?? 0 );
		if ( ! $id && is_singular( 'mgm_course' ) ) {
			$id = get_the_ID();
		}
		if ( ! $id && is_singular( 'mgm_lesson' ) ) {
			$id = self::lesson_course( get_the_ID() );
		}
		return $id;
	}

	public static function sc_courses( $atts ) {
		$atts = shortcode_atts( MGM_Shortcodes::defaults( 'mgm_courses' ), (array) $atts );
		$args = array(
			'post_type'      => 'mgm_course',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $atts['limit'] ),
			'orderby'        => sanitize_key( $atts['orderby'] ),
			'order'          => 'title' === $atts['orderby'] || 'menu_order' === $atts['orderby'] ? 'ASC' : 'DESC',
		);
		if ( $atts['category'] ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'mgm_course_cat', 'field' => 'slug', 'terms' => sanitize_title( $atts['category'] ) ) ); // phpcs:ignore
		}
		return mgm_get_template(
			'courses/grid.php',
			array(
				'courses' => get_posts( $args ),
				'columns' => max( 1, min( 4, (int) $atts['columns'] ) ),
			)
		);
	}

	public static function sc_my_courses() {
		if ( ! is_user_logged_in() ) {
			return MGM_Account::render_login();
		}
		return mgm_get_template( 'courses/my-courses.php', array( 'user_id' => get_current_user_id() ) );
	}

	public static function sc_curriculum( $atts ) {
		$id = self::course_from_atts( $atts );
		return $id ? self::curriculum_html( $id, is_singular( 'mgm_lesson' ) ? get_the_ID() : 0 ) : '';
	}

	public static function sc_progress( $atts ) {
		$id = self::course_from_atts( $atts );
		if ( ! $id || ! is_user_logged_in() ) {
			return '';
		}
		$p = self::progress( get_current_user_id(), $id );
		return '<div class="mgm-progress" title="' . esc_attr( $p ) . '%"><div class="mgm-progress-bar" style="width:' . (int) $p . '%"></div></div><div class="mgm-progress-label">' . esc_html( sprintf( __( '%d%% complete', 'mg-membership' ), $p ) ) . '</div>';
	}

	public static function sc_button( $atts ) {
		$id = self::course_from_atts( $atts );
		return $id ? mgm_get_template( 'courses/button.php', array( 'course_id' => $id ) ) : '';
	}

	/* ----------------------------- Account ----------------------------- */

	public static function account_tab( $tabs ) {
		$new = array();
		foreach ( $tabs as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'home' === $k ) {
				$new['courses'] = __( 'My Courses', 'mg-membership' );
			}
		}
		return $new;
	}

	public static function account_tab_content( $user ) {
		echo '<h2>' . esc_html__( 'My Courses', 'mg-membership' ) . '</h2>';
		echo mgm_get_template( 'courses/my-courses.php', array( 'user_id' => $user->ID ) ); // phpcs:ignore
	}

	public static function dashboard_widget( $user ) {
		$courses = self::enrolled_courses( $user->ID );
		if ( ! $courses ) {
			return;
		}
		echo '<h3>' . esc_html__( 'Continue learning', 'mg-membership' ) . '</h3>';
		echo mgm_get_template( 'courses/my-courses.php', array( 'user_id' => $user->ID, 'limit' => 3 ) ); // phpcs:ignore
	}
}

MGM_Courses::init();
