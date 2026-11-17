<?php
/**
 * Admin menu, dashboard, settings & add-ons screens.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		add_action( 'admin_menu', array( __CLASS__, 'menu_late' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_mgm_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_mgm_save_addons', array( __CLASS__, 'save_addons' ) );
		add_filter( 'plugin_action_links_' . MGM_BASENAME, array( __CLASS__, 'action_links' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'all_admin_notices', array( __CLASS__, 'cpt_intro' ), 1 );
		add_filter( 'register_post_type_args', array( __CLASS__, 'empty_labels' ), 10, 2 );
	}

	public static function menu() {
		$cap = mgm_admin_cap();
		add_menu_page( __( 'MG Membership', 'mg-membership' ), __( 'MG Membership', 'mg-membership' ), $cap, 'mgm', array( __CLASS__, 'dashboard' ), 'dashicons-groups', 26 );
		add_submenu_page( 'mgm', __( 'Dashboard', 'mg-membership' ), __( 'Dashboard', 'mg-membership' ), $cap, 'mgm', array( __CLASS__, 'dashboard' ) );
	}

	public static function menu_late() {
		$cap = mgm_admin_cap();
		add_submenu_page( 'mgm', __( 'Members', 'mg-membership' ), __( 'Members', 'mg-membership' ), $cap, 'mgm-members', array( 'MGM_Admin_Lists', 'members_page' ) );
		add_submenu_page( 'mgm', __( 'Subscriptions', 'mg-membership' ), __( 'Subscriptions', 'mg-membership' ), $cap, 'mgm-subscriptions', array( 'MGM_Admin_Lists', 'subscriptions_page' ) );
		add_submenu_page( 'mgm', __( 'Transactions', 'mg-membership' ), __( 'Transactions', 'mg-membership' ), $cap, 'mgm-transactions', array( 'MGM_Admin_Lists', 'transactions_page' ) );
		do_action( 'mgm_admin_menu', $cap );
		add_submenu_page( 'mgm', __( 'Add-ons', 'mg-membership' ), __( 'Add-ons', 'mg-membership' ), $cap, 'mgm-addons', array( __CLASS__, 'addons_page' ) );
		add_submenu_page( 'mgm', __( 'Settings', 'mg-membership' ), __( 'Settings', 'mg-membership' ), $cap, 'mgm-settings', array( __CLASS__, 'settings_page' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=mgm-settings' ) ) . '">' . esc_html__( 'Settings', 'mg-membership' ) . '</a>' );
		return $links;
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		$ours   = false !== strpos( $hook, 'mgm' ) || ( $screen && 0 === strpos( (string) $screen->post_type, 'mgm_' ) ) || ( $screen && 'post' === $screen->base );
		if ( ! $ours ) {
			return;
		}
		wp_enqueue_style( 'mgm-admin', MGM_URL . 'assets/css/admin.css', array(), MGM_VERSION );
		wp_enqueue_script( 'mgm-admin', MGM_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), MGM_VERSION, true );
		if ( 'toplevel_page_mgm' === $hook ) {
			wp_enqueue_style( 'mgm-dashboard', MGM_URL . 'assets/css/dashboard.css', array(), MGM_VERSION );
			wp_enqueue_script( 'mgm-dashboard', MGM_URL . 'assets/js/dashboard.js', array(), MGM_VERSION, true );
		}
	}

	/**
	 * Is the current admin screen one of ours?
	 */
	public static function is_our_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}
		return false !== strpos( (string) $screen->id, 'mgm' ) || 0 === strpos( (string) $screen->post_type, 'mgm_' );
	}

	public static function body_class( $classes ) {
		return self::is_our_screen() ? $classes . ' mgm-admin' : $classes;
	}

	/**
	 * Button markup used in page headers.
	 *
	 * @param array $a { label, url, primary?, icon? }
	 */
	public static function button( array $a ) {
		return sprintf(
			'<a class="mgm-btn%s" href="%s">%s%s</a>',
			empty( $a['primary'] ) ? '' : ' mgm-btn-primary',
			esc_url( $a['url'] ),
			empty( $a['icon'] ) ? '' : '<span class="dashicons ' . esc_attr( $a['icon'] ) . '" aria-hidden="true"></span>',
			esc_html( $a['label'] )
		);
	}

	/**
	 * Page header: breadcrumb, title, description, actions.
	 *
	 * @param string $title Page title.
	 * @param array  $args  { desc, actions[], back => [label, url] }.
	 */
	public static function header( $title, array $args = array() ) {
		echo '<div class="wrap mgm-wrap"><div class="mgm-page-head"><div class="mgm-page-head-text">';
		if ( ! empty( $args['back'] ) ) {
			echo '<a class="mgm-eyebrow mgm-back" href="' . esc_url( $args['back'][1] ) . '"><span aria-hidden="true">←</span> ' . esc_html( $args['back'][0] ) . '</a>';
		} else {
			echo '<p class="mgm-eyebrow">' . esc_html__( 'MG Membership', 'mg-membership' ) . '</p>';
		}
		echo '<h1 class="mgm-page-title">' . esc_html( $title ) . '</h1>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="mgm-page-desc">' . esc_html( $args['desc'] ) . '</p>';
		}
		echo '</div>';
		if ( ! empty( $args['actions'] ) ) {
			echo '<div class="mgm-page-actions">';
			foreach ( $args['actions'] as $a ) {
				echo self::button( $a ); // phpcs:ignore -- escaped in button().
			}
			echo '</div>';
		}
		echo '</div><hr class="wp-header-end">';
		if ( isset( $_GET['mgm_msg'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-success is-dismissible mgm-notice"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['mgm_msg'] ) ) ) . '</p></div>'; // phpcs:ignore
		}
	}

	/**
	 * Intro strip above the core list tables of our post types (Memberships, Rules, Coupons, Courses…).
	 */
	public static function cpt_intro() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base || 0 !== strpos( (string) $screen->post_type, 'mgm_' ) ) {
			return;
		}
		$intros = apply_filters(
			'mgm_cpt_intros',
			array(
				'mgm_membership' => array( 'dashicons-id-alt', __( 'Plans people can buy. Each one has its own price, billing period, trial and registration page.', 'mg-membership' ) ),
				'mgm_rule'       => array( 'dashicons-lock', __( 'Rules decide who can see what. Protect all content, categories, post types or single pages, and optionally drip them over time.', 'mg-membership' ) ),
				'mgm_coupon'     => array( 'dashicons-tickets-alt', __( 'Discount codes for checkout. Share a link with ?coupon=CODE to apply one automatically.', 'mg-membership' ) ),
				'mgm_course'     => array( 'dashicons-welcome-learn-more', __( 'Courses group lessons and quizzes into sections. Access follows the memberships you choose on each course.', 'mg-membership' ) ),
				'mgm_lesson'     => array( 'dashicons-media-text', __( 'Lessons and quizzes. Arrange them inside a course with the Curriculum Builder.', 'mg-membership' ) ),
				'mgm_event'      => array( 'dashicons-calendar-alt', __( 'Seminars, webinars and live events with registration, capacity, waitlists and check-in.', 'mg-membership' ) ),
			)
		);
		if ( empty( $intros[ $screen->post_type ] ) ) {
			return;
		}
		$i = $intros[ $screen->post_type ];
		echo '<div class="mgm-intro"><span class="mgm-intro-icon"><span class="dashicons ' . esc_attr( $i[0] ) . '" aria-hidden="true"></span></span><p>' . esc_html( $i[1] ) . '</p></div>';
	}

	/**
	 * Friendlier "nothing found" text for our post types.
	 */
	public static function empty_labels( $args, $post_type ) {
		$texts = array(
			'mgm_membership' => __( 'No memberships yet. Click “Add New” to create your first plan.', 'mg-membership' ),
			'mgm_rule'       => __( 'No rules yet, so all content is public. Click “Add New” to protect something.', 'mg-membership' ),
			'mgm_coupon'     => __( 'No coupons yet. Click “Add New” to create a discount code.', 'mg-membership' ),
			'mgm_course'     => __( 'No courses yet. Click “Add New” to build your first course.', 'mg-membership' ),
			'mgm_lesson'     => __( 'No lessons yet. Create them here or inside a course’s Curriculum Builder.', 'mg-membership' ),
			'mgm_event'      => __( 'No events yet. Click “Add New” to schedule a seminar or webinar.', 'mg-membership' ),
		);
		if ( isset( $texts[ $post_type ] ) ) {
			$args['labels']['not_found'] = $texts[ $post_type ];
		}
		return $args;
	}

	public static function footer() {
		echo '</div>';
	}

	/* ---------------------------- Dashboard ---------------------------- */

	public static function stats() {
		global $wpdb;
		$t     = MGM_Transaction::table();
		$s     = MGM_Subscription::table();
		$now   = mgm_now();
		$month = gmdate( 'Y-m-01 00:00:00' );
		return array(
			'active_members' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$t} WHERE status='complete' AND (expires_at IS NULL OR expires_at > %s)", $now ) ), // phpcs:ignore
			'revenue_month'  => (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(total),0) FROM {$t} WHERE status='complete' AND created_at >= %s", $month ) ), // phpcs:ignore
			'revenue_total'  => (float) $wpdb->get_var( "SELECT COALESCE(SUM(total),0) FROM {$t} WHERE status='complete'" ), // phpcs:ignore
			'active_subs'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$s} WHERE status IN ('active','trialing')" ), // phpcs:ignore
			'new_month'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$t} WHERE status='complete' AND created_at >= %s", $month ) ), // phpcs:ignore
			'pending'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status='pending'" ), // phpcs:ignore
			'mrr'            => (float) $wpdb->get_var( "SELECT COALESCE(SUM(CASE period_unit WHEN 'day' THEN price*30/period WHEN 'week' THEN price*4.345/period WHEN 'month' THEN price/period WHEN 'year' THEN price/(12*period) END),0) FROM {$s} WHERE status='active'" ), // phpcs:ignore
		);
	}

	public static function dashboard() {
		MGM_Admin_Dashboard::render();
	}

	/* ---------------------------- Settings ----------------------------- */

	public static function settings_page() {
		$tabs    = MGM_Settings::fields();
		$current = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore
		$current = isset( $tabs[ $current ] ) ? $current : 'general';
		$meta    = array(
			'general'  => array( 'dashicons-admin-generic', __( 'Currency, tax and member experience', 'mg-membership' ) ),
			'pages'    => array( 'dashicons-admin-page', __( 'Account, login and pricing pages', 'mg-membership' ) ),
			'access'   => array( 'dashicons-lock', __( 'What visitors see on locked content', 'mg-membership' ) ),
			'payments' => array( 'dashicons-money-alt', __( 'Stripe, PayPal and offline payments', 'mg-membership' ) ),
			'emails'   => array( 'dashicons-email-alt', __( 'Sender details and email templates', 'mg-membership' ) ),
		);
		self::header(
			__( 'Settings', 'mg-membership' ),
			array( 'desc' => __( 'Configure payments, pages, content protection and emails.', 'mg-membership' ) )
		);

		echo '<div class="mgm-settings"><nav class="mgm-settings-nav" aria-label="' . esc_attr__( 'Settings sections', 'mg-membership' ) . '">';
		foreach ( $tabs as $key => $tab ) {
			$m = $meta[ $key ] ?? array( 'dashicons-admin-generic', '' );
			printf(
				'<a href="%1$s" class="%2$s" %3$s><span class="dashicons %4$s" aria-hidden="true"></span><span><strong>%5$s</strong><small>%6$s</small></span></a>',
				esc_url( admin_url( 'admin.php?page=mgm-settings&tab=' . $key ) ),
				$key === $current ? 'is-active' : '',
				$key === $current ? 'aria-current="page"' : '',
				esc_attr( $m[0] ),
				esc_html( $tab['label'] ),
				esc_html( $m[1] )
			);
		}
		echo '</nav><form class="mgm-settings-main" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'mgm_save_settings' );
		echo '<input type="hidden" name="action" value="mgm_save_settings"><input type="hidden" name="tab" value="' . esc_attr( $current ) . '">';

		// Split fields into cards at each "heading".
		$sections = array();
		$i        = -1;
		foreach ( $tabs[ $current ]['fields'] as $key => $f ) {
			if ( 'heading' === $f['type'] || -1 === $i ) {
				$sections[ ++$i ] = array(
					'title'  => 'heading' === $f['type'] ? $f['label'] : $tabs[ $current ]['label'],
					'fields' => array(),
				);
				if ( 'heading' === $f['type'] ) {
					continue;
				}
			}
			$sections[ $i ]['fields'][ $key ] = $f;
		}
		if ( 'payments' === $current && ! $sections ) {
			echo '<div class="mgm-card"><p>' . esc_html__( 'No payment gateways are registered.', 'mg-membership' ) . '</p></div>';
		}
		foreach ( $sections as $sec ) {
			echo '<section class="mgm-card mgm-settings-card"><h2 class="mgm-card-title">' . esc_html( $sec['title'] ) . '</h2><div class="mgm-fields-grid">';
			foreach ( $sec['fields'] as $key => $f ) {
				self::field_row( $key, $f );
			}
			echo '</div></section>';
		}
		echo '<div class="mgm-savebar"><span class="mgm-muted">' . esc_html__( 'Changes apply as soon as you save.', 'mg-membership' ) . '</span>';
		submit_button( __( 'Save changes', 'mg-membership' ), 'primary mgm-btn-submit', 'submit', false );
		echo '</div></form></div>';
		self::footer();
	}

	public static function field_row( $key, $f ) {
		$value = mgm_option( $key, isset( $f['default'] ) ? $f['default'] : '' );
		if ( 'heading' === $f['type'] ) {
			echo '<h3 class="mgm-subheading">' . esc_html( $f['label'] ) . '</h3>';
			return;
		}
		$name = 'mgm[' . esc_attr( $key ) . ']';
		if ( 'checkbox' === $f['type'] ) {
			printf(
				'<div class="mgm-field-row mgm-field-toggle"><input type="hidden" name="%1$s" value="0"><label class="mgm-switch" for="%2$s"><input type="checkbox" id="%2$s" name="%1$s" value="1" %3$s><span class="mgm-switch-ui" aria-hidden="true"></span><span class="mgm-switch-label">%4$s</span></label>%5$s</div>',
				$name, // phpcs:ignore
				esc_attr( $key ),
				checked( '1', (string) $value, false ),
				esc_html( $f['label'] ),
				empty( $f['desc'] ) ? '' : '<p class="description">' . wp_kses_post( $f['desc'] ) . '</p>'
			);
			return;
		}
		echo '<div class="mgm-field-row"><label class="mgm-field-label" for="' . esc_attr( $key ) . '">' . esc_html( $f['label'] ) . '</label><div class="mgm-field-control">';
		switch ( $f['type'] ) {
			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $key ), $name ); // phpcs:ignore
				foreach ( $f['options'] as $ov => $ol ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $ov ), selected( (string) $ov, (string) $value, false ), esc_html( $ol ) );
				}
				echo '</select>';
				break;
			case 'textarea':
				printf( '<textarea id="%s" name="%s" rows="5" class="large-text" placeholder="%s">%s</textarea>', esc_attr( $key ), $name, esc_attr( $f['placeholder'] ?? '' ), esc_textarea( $value ) ); // phpcs:ignore
				break;
			case 'editor':
				wp_editor( $value, $key, array( 'textarea_name' => 'mgm[' . $key . ']', 'textarea_rows' => 6, 'media_buttons' => false ) );
				break;
			default:
				$type = in_array( $f['type'], array( 'password', 'number', 'email', 'url' ), true ) ? $f['type'] : 'text';
				printf( '<input type="%s" id="%s" name="%s" value="%s" class="regular-text" placeholder="%s" %s autocomplete="off">', esc_attr( $type ), esc_attr( $key ), $name, esc_attr( $value ), esc_attr( $f['placeholder'] ?? '' ), isset( $f['step'] ) ? 'step="' . esc_attr( $f['step'] ) . '"' : '' ); // phpcs:ignore
		}
		if ( ! empty( $f['desc'] ) ) {
			echo '<p class="description">' . wp_kses_post( $f['desc'] ) . '</p>';
		}
		echo '</div></div>';
	}

	public static function save_settings() {
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mg-membership' ) );
		}
		check_admin_referer( 'mgm_save_settings' );
		$tab    = sanitize_key( $_POST['tab'] ?? 'general' );
		$tabs   = MGM_Settings::fields();
		$input  = isset( $_POST['mgm'] ) ? wp_unslash( $_POST['mgm'] ) : array(); // phpcs:ignore
		$values = array();
		if ( isset( $tabs[ $tab ] ) ) {
			foreach ( $tabs[ $tab ]['fields'] as $key => $f ) {
				if ( 'heading' === $f['type'] || ! array_key_exists( $key, $input ) ) {
					continue;
				}
				$v = $input[ $key ];
				switch ( $f['type'] ) {
					case 'editor':
						$v = wp_kses_post( $v );
						break;
					case 'textarea':
						$v = sanitize_textarea_field( $v );
						break;
					case 'checkbox':
						$v = '1' === (string) $v ? '1' : '0';
						break;
					default:
						$v = sanitize_text_field( $v );
				}
				$values[ $key ] = $v;
			}
		}
		MGM_Settings::update( $values );
		MGM_Access::flush_rules();
		wp_safe_redirect( admin_url( 'admin.php?page=mgm-settings&tab=' . $tab . '&mgm_msg=' . rawurlencode( __( 'Settings saved.', 'mg-membership' ) ) ) );
		exit;
	}

	/* ----------------------------- Add-ons ----------------------------- */

	public static function addons_page() {
		$registry = MGM_Addons::registry();
		$active   = count( array_intersect( array_keys( $registry ), MGM_Addons::active() ) );
		self::header(
			__( 'Add-ons', 'mg-membership' ),
			array( 'desc' => sprintf( __( '%1$d of %2$d add-ons are on. Turned-off add-ons load no code at all.', 'mg-membership' ), $active, count( $registry ) ) )
		);
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'mgm_save_addons' );
		echo '<input type="hidden" name="action" value="mgm_save_addons"><div class="mgm-addons">';
		foreach ( $registry as $slug => $a ) {
			$on    = MGM_Addons::is_active( $slug );
			$avail = file_exists( MGM_PATH . 'addons/' . $slug . '/' . $slug . '.php' ) || has_filter( 'mgm_addon_file' );
			$req   = '';
			if ( ! empty( $a['requires'] ) ) {
				$names = array();
				foreach ( $a['requires'] as $r ) {
					$names[] = $registry[ $r ]['name'] ?? $r;
				}
				$req = sprintf( __( 'Turns on: %s', 'mg-membership' ), implode( ', ', $names ) );
			}
			printf(
				'<label class="mgm-addon %1$s"><span class="mgm-addon-icon"><span class="dashicons %2$s" aria-hidden="true"></span></span><span class="mgm-addon-body"><strong>%3$s</strong><span class="mgm-addon-desc">%4$s</span>%5$s</span><span class="mgm-switch"><input type="checkbox" name="addons[]" value="%6$s" %7$s %8$s aria-label="%3$s"><span class="mgm-switch-ui" aria-hidden="true"></span></span></label>',
				( $on ? 'is-on' : '' ) . ( $avail ? '' : ' is-unavailable' ),
				esc_attr( $a['icon'] ),
				esc_html( $a['name'] ),
				esc_html( $a['description'] ),
				( $req ? '<span class="mgm-addon-note">' . esc_html( $req ) . '</span>' : '' ) . ( $avail ? '' : '<span class="mgm-addon-note">' . esc_html__( 'Not installed', 'mg-membership' ) . '</span>' ),
				esc_attr( $slug ),
				checked( $on, true, false ),
				$avail ? '' : 'disabled'
			);
		}
		echo '</div><div class="mgm-savebar"><span class="mgm-muted">' . esc_html__( 'Menus update after saving.', 'mg-membership' ) . '</span>';
		submit_button( __( 'Save add-ons', 'mg-membership' ), 'primary mgm-btn-submit', 'submit', false );
		echo '</div></form>';
		self::footer();
	}

	public static function save_addons() {
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mg-membership' ) );
		}
		check_admin_referer( 'mgm_save_addons' );
		$addons = isset( $_POST['addons'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['addons'] ) ) : array();
		MGM_Addons::set_active( $addons );
		update_option( 'mgm_flush_rewrite', 1 );
		wp_safe_redirect( admin_url( 'admin.php?page=mgm-addons&mgm_msg=' . rawurlencode( __( 'Add-ons updated.', 'mg-membership' ) ) ) );
		exit;
	}
}
