<?php
/**
 * Add-on: Member activity log.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Activity {

	const DB_VERSION = '1';

	public static function table() {
		return MGM_Install::table( 'activity' );
	}

	public static function types() {
		return array(
			'login'        => __( 'Logged in', 'mg-membership' ),
			'signup'       => __( 'Signed up', 'mg-membership' ),
			'payment'      => __( 'Payment', 'mg-membership' ),
			'refund'       => __( 'Refund', 'mg-membership' ),
			'granted'      => __( 'Access granted', 'mg-membership' ),
			'revoked'      => __( 'Access removed', 'mg-membership' ),
			'subscription' => __( 'Subscription', 'mg-membership' ),
			'lesson'       => __( 'Lesson completed', 'mg-membership' ),
			'course'       => __( 'Course completed', 'mg-membership' ),
			'quiz'         => __( 'Quiz attempt', 'mg-membership' ),
			'event'        => __( 'Event registration', 'mg-membership' ),
			'download'     => __( 'Download', 'mg-membership' ),
			'profile'      => __( 'Profile updated', 'mg-membership' ),
			'approval'     => __( 'Approval', 'mg-membership' ),
		);
	}

	public static function init() {
		self::maybe_install();
		$t = __CLASS__;
		add_action( 'wp_login', function ( $login, $user ) use ( $t ) { $t::log( $user->ID, 'login', __( 'Logged in', 'mg-membership' ) ); }, 10, 2 );
		add_action( 'mgm_new_member_signup', function ( $uid ) use ( $t ) { $t::log( $uid, 'signup', __( 'Created an account', 'mg-membership' ) ); } );
		add_action( 'mgm_transaction_completed', function ( $txn ) use ( $t ) {
			if ( (float) $txn->total > 0 ) {
				$t::log( $txn->user_id, 'payment', sprintf( __( 'Paid %1$s for %2$s', 'mg-membership' ), mgm_price( $txn->total ), get_the_title( $txn->membership_id ) ), $txn->id );
			}
		} );
		add_action( 'mgm_membership_granted', function ( $uid, $mid ) use ( $t ) { $t::log( $uid, 'granted', sprintf( __( 'Got access to %s', 'mg-membership' ), get_the_title( $mid ) ), $mid ); }, 10, 2 );
		add_action( 'mgm_membership_revoked', function ( $uid, $mid ) use ( $t ) { $t::log( $uid, 'revoked', sprintf( __( 'Lost access to %s', 'mg-membership' ), get_the_title( $mid ) ), $mid ); }, 10, 2 );
		add_action( 'mgm_transaction_refunded', function ( $id ) use ( $t ) {
			$txn = MGM_Transaction::get( $id );
			if ( $txn ) {
				$t::log( $txn->user_id, 'refund', sprintf( __( 'Refunded %s', 'mg-membership' ), mgm_price( $txn->total ) ), $id );
			}
		} );
		add_action( 'mgm_subscription_status_changed', function ( $sid, $new ) use ( $t ) {
			$sub = MGM_Subscription::get( $sid );
			if ( $sub ) {
				$t::log( $sub->user_id, 'subscription', sprintf( __( '%1$s subscription is now %2$s', 'mg-membership' ), get_the_title( $sub->membership_id ), MGM_Subscription::status_label( $new ) ), $sid );
			}
		}, 10, 2 );
		add_action( 'mgm_lesson_completed', function ( $uid, $lid ) use ( $t ) { $t::log( $uid, 'lesson', sprintf( __( 'Completed lesson "%s"', 'mg-membership' ), get_the_title( $lid ) ), $lid ); }, 10, 2 );
		add_action( 'mgm_course_completed', function ( $uid, $cid ) use ( $t ) { $t::log( $uid, 'course', sprintf( __( 'Completed course "%s"', 'mg-membership' ), get_the_title( $cid ) ), $cid ); }, 10, 2 );
		add_action( 'mgm_quiz_attempted', function ( $uid, $qid, $score, $passed ) use ( $t ) { $t::log( $uid, 'quiz', sprintf( __( 'Scored %1$s%% on "%2$s" (%3$s)', 'mg-membership' ), round( $score ), get_the_title( $qid ), $passed ? __( 'passed', 'mg-membership' ) : __( 'not passed', 'mg-membership' ) ), $qid ); }, 10, 4 );
		add_action( 'mgm_event_registered', function ( $reg, $eid ) use ( $t ) {
			if ( $reg->user_id ) {
				$t::log( $reg->user_id, 'event', sprintf( __( 'Registered for "%1$s" (%2$s)', 'mg-membership' ), get_the_title( $eid ), $reg->status ), $eid );
			}
		}, 10, 2 );
		add_action( 'mgm_file_downloaded', function ( $uid, $did ) use ( $t ) { $t::log( $uid, 'download', sprintf( __( 'Downloaded "%s"', 'mg-membership' ), get_the_title( $did ) ), $did ); }, 10, 2 );
		add_action( 'mgm_profile_updated', function ( $uid ) use ( $t ) { $t::log( $uid, 'profile', __( 'Updated their profile', 'mg-membership' ) ); } );
		add_action( 'mgm_approval_pending', function ( $uid, $mid ) use ( $t ) { $t::log( $uid, 'approval', sprintf( __( 'Waiting for approval: %s', 'mg-membership' ), get_the_title( $mid ) ), $mid ); }, 10, 2 );
		add_action( 'mgm_approval_approved', function ( $uid, $mid ) use ( $t ) { $t::log( $uid, 'approval', sprintf( __( 'Approved for %s', 'mg-membership' ), get_the_title( $mid ) ), $mid ); }, 10, 2 );
		add_action( 'mgm_approval_denied', function ( $uid, $mid ) use ( $t ) { $t::log( $uid, 'approval', sprintf( __( 'Not approved for %s', 'mg-membership' ), get_the_title( $mid ) ), $mid ); }, 10, 2 );
		add_action( 'mgm_cron', array( __CLASS__, 'cleanup' ) );
		if ( is_admin() ) {
			add_action( 'mgm_admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'mgm_admin_member_detail', array( __CLASS__, 'member_detail' ), 50 );
		}
	}

	public static function maybe_install() {
		if ( self::DB_VERSION === get_option( 'mgm_activity_db' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			'CREATE TABLE ' . self::table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(20) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			message varchar(255) NOT NULL DEFAULT '',
			ip varchar(45) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY type (type),
			KEY created_at (created_at)
		) " . $wpdb->get_charset_collate() . ';'
		);
		update_option( 'mgm_activity_db', self::DB_VERSION );
	}

	public static function log( $user_id, $type, $message, $object_id = 0 ) {
		global $wpdb;
		if ( ! $user_id ) {
			return;
		}
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$wpdb->insert( // phpcs:ignore
			self::table(),
			array(
				'user_id'    => (int) $user_id,
				'type'       => $type,
				'object_id'  => (int) $object_id,
				'message'    => mb_substr( wp_strip_all_tags( $message ), 0, 255 ),
				'ip'         => apply_filters( 'mgm_activity_store_ip', true ) ? $ip : '',
				'created_at' => mgm_now(),
			)
		);
	}

	public static function query( array $args ) {
		global $wpdb;
		$args  = wp_parse_args( $args, array( 'user_id' => 0, 'type' => '', 'search' => '', 'per_page' => 50, 'page' => 1, 'count' => false ) );
		$where = array( '1=1' );
		if ( $args['user_id'] ) {
			$where[] = $wpdb->prepare( 'a.user_id = %d', $args['user_id'] );
		}
		if ( $args['type'] ) {
			$where[] = $wpdb->prepare( 'a.type = %s', $args['type'] );
		}
		if ( $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = $wpdb->prepare( '(u.user_email LIKE %s OR u.display_name LIKE %s OR a.message LIKE %s)', $like, $like, $like );
		}
		$from = self::table() . " a LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id WHERE " . implode( ' AND ', $where );
		if ( $args['count'] ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$from}" ); // phpcs:ignore
		}
		return $wpdb->get_results( "SELECT a.*, u.display_name, u.user_email FROM {$from} ORDER BY a.id DESC " . $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['per_page'], ( max( 1, $args['page'] ) - 1 ) * $args['per_page'] ) ); // phpcs:ignore
	}

	public static function cleanup() {
		global $wpdb;
		$days = (int) apply_filters( 'mgm_activity_retention_days', 365 );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE created_at < %s', gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore
	}

	public static function menu( $cap ) {
		add_submenu_page( 'mgm', __( 'Activity Log', 'mg-membership' ), __( 'Activity Log', 'mg-membership' ), $cap, 'mgm-activity', array( __CLASS__, 'page' ) );
	}

	private static function rows( $rows, $show_user = true ) {
		echo '<div class="mgm-table-card"><table class="widefat mgm-table"><thead><tr><th>' . esc_html__( 'When', 'mg-membership' ) . '</th>' . ( $show_user ? '<th>' . esc_html__( 'Member', 'mg-membership' ) . '</th>' : '' ) . '<th>' . esc_html__( 'Activity', 'mg-membership' ) . '</th><th>' . esc_html__( 'Type', 'mg-membership' ) . '</th></tr></thead><tbody>';
		$types = self::types();
		foreach ( $rows as $r ) {
			echo '<tr><td title="' . esc_attr( get_date_from_gmt( $r->created_at, 'Y-m-d H:i' ) ) . '">' . esc_html( sprintf( __( '%s ago', 'mg-membership' ), human_time_diff( strtotime( $r->created_at . ' UTC' ) ) ) ) . '</td>';
			if ( $show_user ) {
				echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=mgm-members&member=' . (int) $r->user_id ) ) . '">' . esc_html( $r->display_name ? $r->display_name : '#' . $r->user_id ) . '</a></td>';
			}
			echo '<td>' . esc_html( $r->message ) . '</td><td><span class="mgm-chip">' . esc_html( $types[ $r->type ] ?? $r->type ) . '</span></td></tr>';
		}
		if ( ! $rows ) {
			echo '<tr class="mgm-empty-row"><td colspan="4"><strong>' . esc_html__( 'No activity yet', 'mg-membership' ) . '</strong><span>' . esc_html__( 'Logins, payments, lessons and more will appear here.', 'mg-membership' ) . '</span></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function page() {
		$type   = sanitize_key( $_GET['type'] ?? '' ); // phpcs:ignore
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore
		$page   = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore
		MGM_Admin::header( __( 'Activity Log', 'mg-membership' ), array( 'desc' => __( 'What members have been doing, newest first. Entries older than one year are removed automatically.', 'mg-membership' ) ) );
		echo '<div class="mgm-toolbar"><form method="get" class="mgm-filter"><input type="hidden" name="page" value="mgm-activity"><select name="type" aria-label="' . esc_attr__( 'Type', 'mg-membership' ) . '"><option value="">' . esc_html__( 'All activity', 'mg-membership' ) . '</option>';
		foreach ( self::types() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $type, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select><span class="mgm-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search member or activity…', 'mg-membership' ) . '"></span>';
		submit_button( __( 'Apply', 'mg-membership' ), 'secondary', '', false );
		echo '</form></div>';
		$args = array( 'type' => $type, 'search' => $search, 'page' => $page );
		self::rows( self::query( $args ) );
		$total = self::query( array_merge( $args, array( 'count' => true ) ) );
		if ( $total > 50 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $page, 'total' => (int) ceil( $total / 50 ) ) ) . '</div></div>'; // phpcs:ignore
		}
		MGM_Admin::footer();
	}

	public static function member_detail( $user ) {
		echo '<section class="mgm-card mgm-card-flush"><h2 class="mgm-card-title">' . esc_html__( 'Recent activity', 'mg-membership' ) . '</h2>';
		self::rows( self::query( array( 'user_id' => $user->ID, 'per_page' => 15 ) ), false );
		echo '</section>';
	}
}

MGM_Activity::init();
