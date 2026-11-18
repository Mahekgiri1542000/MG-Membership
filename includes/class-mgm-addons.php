<?php
/**
 * Add-on (module) manager. Each add-on lives in /addons/{slug}/{slug}.php
 * and can be switched on/off from MG Membership → Add-ons.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Addons {

	private static $loaded = array();

	public static function registry() {
		return apply_filters(
			'mgm_addons_registry',
			array(
				'coupons'       => array(
					'name'        => __( 'Coupons', 'mg-membership' ),
					'description' => __( 'Percentage or fixed discount codes with usage limits, expiry dates and per-membership restrictions.', 'mg-membership' ),
					'icon'        => 'dashicons-tickets-alt',
				),
				'drip'          => array(
					'name'        => __( 'Drip Content', 'mg-membership' ),
					'description' => __( 'Release protected content X days after a member joins, and expire access after a period.', 'mg-membership' ),
					'icon'        => 'dashicons-clock',
				),
				'courses'       => array(
					'name'        => __( 'Courses (LMS)', 'mg-membership' ),
					'description' => __( 'Courses, sections, lessons, quizzes, progress tracking, course curriculum and membership-based enrollment.', 'mg-membership' ),
					'icon'        => 'dashicons-welcome-learn-more',
				),
				'certificates'  => array(
					'name'        => __( 'Certificates', 'mg-membership' ),
					'description' => __( 'Printable completion certificates for courses with verification codes. Requires Courses.', 'mg-membership' ),
					'icon'        => 'dashicons-awards',
					'requires'    => array( 'courses' ),
				),
				'events'        => array(
					'name'        => __( 'Seminars & Events', 'mg-membership' ),
					'description' => __( 'Seminars, webinars, workshops and live events with capacity, member-only registration, tickets, attendee check-in, calendar export and reminders.', 'mg-membership' ),
					'icon'        => 'dashicons-calendar-alt',
				),
				'reports'       => array(
					'name'        => __( 'Reports', 'mg-membership' ),
					'description' => __( 'Revenue, new members, churn, course completions and event registration reports with CSV export.', 'mg-membership' ),
					'icon'        => 'dashicons-chart-area',
				),
				'page-builders' => array(
					'name'        => __( 'Page Builder Integrations', 'mg-membership' ),
					'description' => __( 'Gutenberg blocks, Elementor widgets & section protection, Divi modules, WPBakery elements and Beaver Builder modules.', 'mg-membership' ),
					'icon'        => 'dashicons-layout',
				),
				'groups'        => array(
					'name'        => __( 'Corporate / Group Accounts', 'mg-membership' ),
					'description' => __( 'Let a parent account purchase seats and invite sub-accounts that inherit the membership.', 'mg-membership' ),
					'icon'        => 'dashicons-groups',
				),
				'approval'      => array(
					'name'        => __( 'Member Approval', 'mg-membership' ),
					'description' => __( 'Hold new signups for selected memberships until an admin approves or denies them.', 'mg-membership' ),
					'icon'        => 'dashicons-yes-alt',
				),
				'menu-visibility' => array(
					'name'        => __( 'Menu Visibility', 'mg-membership' ),
					'description' => __( 'Show or hide individual menu items for members, non-members, guests or specific memberships.', 'mg-membership' ),
					'icon'        => 'dashicons-menu',
				),
				'downloads'     => array(
					'name'        => __( 'Protected Downloads', 'mg-membership' ),
					'description' => __( 'Members-only files stored in a protected folder and served through access-checked download links, with counts.', 'mg-membership' ),
					'icon'        => 'dashicons-download',
				),
				'pay-per-post'  => array(
					'name'        => __( 'Pay-Per-Post', 'mg-membership' ),
					'description' => __( 'Sell access to a single post, page or lesson without a full membership.', 'mg-membership' ),
					'icon'        => 'dashicons-cart',
				),
				'directory'     => array(
					'name'        => __( 'Member Directory & Profiles', 'mg-membership' ),
					'description' => __( 'Searchable member directory, public profiles and membership badges. Members opt in from their account.', 'mg-membership' ),
					'icon'        => 'dashicons-id',
				),
				'automation'    => array(
					'name'        => __( 'Membership Automation', 'mg-membership' ),
					'description' => __( 'Automatically give memberships to every new user or to users with chosen roles.', 'mg-membership' ),
					'icon'        => 'dashicons-controls-repeat',
				),
				'webhooks'      => array(
					'name'        => __( 'Webhooks & Integrations', 'mg-membership' ),
					'description' => __( 'Send signed events (signups, payments, cancellations, course completions…) to Zapier, Make, n8n, Mailchimp automations or your own app.', 'mg-membership' ),
					'icon'        => 'dashicons-rest-api',
				),
				'activity-log'  => array(
					'name'        => __( 'Activity Log', 'mg-membership' ),
					'description' => __( 'A timeline of member activity: logins, payments, cancellations, lessons, downloads and more.', 'mg-membership' ),
					'icon'        => 'dashicons-backup',
				),
				'import'        => array(
					'name'        => __( 'Import Members', 'mg-membership' ),
					'description' => __( 'Import members from a CSV file, creating accounts and granting memberships in one go.', 'mg-membership' ),
					'icon'        => 'dashicons-upload',
				),
				'security'      => array(
					'name'        => __( 'Account Sharing & Privacy', 'mg-membership' ),
					'description' => __( 'Limit simultaneous logins per member and hide locked content from site search.', 'mg-membership' ),
					'icon'        => 'dashicons-shield',
				),
			)
		);
	}

	public static function active() {
		return (array) get_option( 'mgm_active_addons', array() );
	}

	public static function is_active( $slug ) {
		return in_array( $slug, self::active(), true );
	}

	public static function set_active( array $slugs ) {
		$registry = self::registry();
		$slugs    = array_values( array_intersect( $slugs, array_keys( $registry ) ) );
		// Pull in dependencies.
		foreach ( $slugs as $slug ) {
			if ( ! empty( $registry[ $slug ]['requires'] ) ) {
				$slugs = array_merge( $slugs, $registry[ $slug ]['requires'] );
			}
		}
		update_option( 'mgm_active_addons', array_values( array_unique( $slugs ) ) );
	}

	public static function load_active() {
		foreach ( self::active() as $slug ) {
			if ( isset( self::$loaded[ $slug ] ) ) {
				continue;
			}
			$file = MGM_PATH . 'addons/' . $slug . '/' . $slug . '.php';
			$file = apply_filters( 'mgm_addon_file', $file, $slug );
			if ( file_exists( $file ) ) {
				require_once $file;
				self::$loaded[ $slug ] = true;
			}
		}
	}
}
