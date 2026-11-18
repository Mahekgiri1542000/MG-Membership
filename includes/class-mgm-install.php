<?php
/**
 * Installation: tables, pages, defaults.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Install {

	public static function activate() {
		self::create_tables();
		self::default_settings();
		self::create_pages();

		// Rewrite rules are flushed on the next request, after all (add-on) post types are registered.
		update_option( 'mgm_flush_rewrite', 1 );

		if ( ! wp_next_scheduled( 'mgm_daily_cron' ) ) {
			wp_schedule_event( time() + 60, 'hourly', 'mgm_daily_cron' );
		}
		update_option( 'mgm_db_version', MGM_DB_VERSION );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'mgm_daily_cron' );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'mgm_db_version' ) !== MGM_DB_VERSION ) {
			self::create_tables();
			update_option( 'mgm_db_version', MGM_DB_VERSION );
		}
	}

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'mgm_' . $name;
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		$subs  = self::table( 'subscriptions' );
		$trans = self::table( 'transactions' );
		$prog  = self::table( 'course_progress' );
		$quiz  = self::table( 'quiz_attempts' );
		$reg   = self::table( 'event_registrations' );

		dbDelta(
			"CREATE TABLE {$subs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			membership_id bigint(20) unsigned NOT NULL,
			gateway varchar(40) NOT NULL DEFAULT '',
			gateway_sub_id varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			price decimal(16,2) NOT NULL DEFAULT 0,
			period int(11) NOT NULL DEFAULT 1,
			period_unit varchar(10) NOT NULL DEFAULT 'month',
			trial_days int(11) NOT NULL DEFAULT 0,
			trial_amount decimal(16,2) NOT NULL DEFAULT 0,
			limit_cycles int(11) NOT NULL DEFAULT 0,
			coupon_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			next_payment_at datetime NULL DEFAULT NULL,
			cancelled_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY membership_id (membership_id),
			KEY gateway_sub_id (gateway_sub_id),
			KEY status (status)
		) {$c};"
		);

		dbDelta(
			"CREATE TABLE {$trans} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			membership_id bigint(20) unsigned NOT NULL,
			subscription_id bigint(20) unsigned NOT NULL DEFAULT 0,
			amount decimal(16,2) NOT NULL DEFAULT 0,
			tax_amount decimal(16,2) NOT NULL DEFAULT 0,
			total decimal(16,2) NOT NULL DEFAULT 0,
			coupon_id bigint(20) unsigned NOT NULL DEFAULT 0,
			gateway varchar(40) NOT NULL DEFAULT '',
			trans_num varchar(191) NOT NULL DEFAULT '',
			txn_type varchar(20) NOT NULL DEFAULT 'payment',
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			expires_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY membership_id (membership_id),
			KEY subscription_id (subscription_id),
			KEY trans_num (trans_num),
			KEY status (status)
		) {$c};"
		);

		dbDelta(
			"CREATE TABLE {$prog} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			lesson_id bigint(20) unsigned NOT NULL DEFAULT 0,
			completed_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY user_lesson (user_id,lesson_id),
			KEY course_id (course_id)
		) {$c};"
		);

		dbDelta(
			"CREATE TABLE {$quiz} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			quiz_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			score decimal(5,2) NOT NULL DEFAULT 0,
			passed tinyint(1) NOT NULL DEFAULT 0,
			answers longtext NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY user_quiz (user_id,quiz_id)
		) {$c};"
		);

		dbDelta(
			"CREATE TABLE {$reg} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'registered',
			ticket_code varchar(40) NOT NULL DEFAULT '',
			attended tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY event_id (event_id),
			KEY user_id (user_id),
			KEY email (email)
		) {$c};"
		);
	}

	private static function default_settings() {
		$defaults = array(
			'currency'              => 'USD',
			'currency_position'     => 'before',
			'unauthorized_message'  => __( 'This content is for members only. Please log in or purchase a membership to continue.', 'mg-membership' ),
			'excerpt_on_protected'  => '1',
			'redirect_unauthorized' => '0',
			'login_redirect'        => 'account',
			'hide_admin_bar'        => '1',
			'email_from_name'       => get_bloginfo( 'name' ),
			'email_from_address'    => get_option( 'admin_email' ),
			'tax_rate'              => '0',
			'gateway_offline_enabled' => '1',
		);
		$current  = get_option( 'mgm_settings', array() );
		update_option( 'mgm_settings', wp_parse_args( $current, $defaults ) );

		$addons = get_option( 'mgm_active_addons', null );
		if ( null === $addons ) {
			update_option( 'mgm_active_addons', array( 'coupons', 'courses', 'events', 'certificates', 'reports', 'page-builders', 'drip', 'menu-visibility', 'downloads', 'pay-per-post', 'activity-log', 'security' ) );
		}
	}

	private static function create_pages() {
		$pages = array(
			'account'  => array( __( 'Account', 'mg-membership' ), '[mgm_account]' ),
			'login'    => array( __( 'Login', 'mg-membership' ), '[mgm_login]' ),
			'thankyou' => array( __( 'Thank You', 'mg-membership' ), '[mgm_thankyou]' ),
			'pricing'  => array( __( 'Plans', 'mg-membership' ), '[mgm_pricing]' ),
			'courses'  => array( __( 'Courses', 'mg-membership' ), '[mgm_courses]' ),
			'events'   => array( __( 'Seminars & Events', 'mg-membership' ), '[mgm_events]' ),
		);
		$opts  = get_option( 'mgm_settings', array() );
		foreach ( $pages as $key => $page ) {
			$opt = 'page_' . $key;
			if ( ! empty( $opts[ $opt ] ) && get_post( $opts[ $opt ] ) ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_title'   => $page[0],
					'post_content' => '<!-- wp:shortcode -->' . $page[1] . '<!-- /wp:shortcode -->',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$opts[ $opt ] = $id;
			}
		}
		update_option( 'mgm_settings', $opts );
	}
}
