<?php
/**
 * Add-on: Reports.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Reports {

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'mgm_admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_mgm_report_csv', array( __CLASS__, 'csv' ) );
	}

	public static function menu( $cap ) {
		add_submenu_page( 'mgm', __( 'Reports', 'mg-membership' ), __( 'Reports', 'mg-membership' ), $cap, 'mgm-reports', array( __CLASS__, 'page' ) );
	}

	private static function range() {
		$preset = sanitize_key( $_GET['range'] ?? '30d' ); // phpcs:ignore
		$to     = time();
		switch ( $preset ) {
			case '7d':
				$from = strtotime( '-6 days', $to );
				break;
			case '90d':
				$from = strtotime( '-89 days', $to );
				break;
			case '12m':
				$from = strtotime( '-11 months', strtotime( gmdate( 'Y-m-01' ) ) );
				break;
			case 'ytd':
				$from = strtotime( gmdate( 'Y-01-01' ) );
				break;
			case 'custom':
				$from = strtotime( sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) ) ); // phpcs:ignore
				$to   = strtotime( sanitize_text_field( wp_unslash( $_GET['to'] ?? '' ) ) . ' 23:59:59' ); // phpcs:ignore
				$from = $from ? $from : strtotime( '-29 days' );
				$to   = $to ? $to : time();
				break;
			default:
				$preset = '30d';
				$from   = strtotime( '-29 days', $to );
		}
		return array(
			'preset' => $preset,
			'from'   => gmdate( 'Y-m-d 00:00:00', $from ),
			'to'     => gmdate( 'Y-m-d 23:59:59', $to ),
			'group'  => in_array( $preset, array( '12m', 'ytd' ), true ) || ( $to - $from ) > 120 * DAY_IN_SECONDS ? 'month' : 'day',
		);
	}

	public static function data( $r ) {
		global $wpdb;
		$t   = MGM_Transaction::table();
		$s   = MGM_Subscription::table();
		$fmt = 'month' === $r['group'] ? '%Y-%m' : '%Y-%m-%d';

		$series = $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(created_at, %s) AS period, SUM(total) AS revenue, COUNT(*) AS payments FROM {$t} WHERE status='complete' AND created_at BETWEEN %s AND %s GROUP BY period ORDER BY period", $fmt, $r['from'], $r['to'] ), OBJECT_K ); // phpcs:ignore

		// Fill gaps.
		$filled = array();
		$cursor = strtotime( $r['from'] );
		$end    = strtotime( $r['to'] );
		while ( $cursor <= $end ) {
			$key            = 'month' === $r['group'] ? gmdate( 'Y-m', $cursor ) : gmdate( 'Y-m-d', $cursor );
			$filled[ $key ] = isset( $series[ $key ] ) ? (float) $series[ $key ]->revenue : 0.0;
			$cursor         = 'month' === $r['group'] ? strtotime( '+1 month', $cursor ) : $cursor + DAY_IN_SECONDS;
		}

		$new_members = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM (SELECT user_id, MIN(created_at) first FROM {$t} WHERE status='complete' GROUP BY user_id) x WHERE first BETWEEN %s AND %s", $r['from'], $r['to'] ) ); // phpcs:ignore
		$cancelled   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$s} WHERE cancelled_at BETWEEN %s AND %s", $r['from'], $r['to'] ) ); // phpcs:ignore
		$active_subs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$s} WHERE status IN ('active','trialing')" ); // phpcs:ignore
		$refunds     = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(total),0) FROM {$t} WHERE status='refunded' AND created_at BETWEEN %s AND %s", $r['from'], $r['to'] ) ); // phpcs:ignore
		$by_plan     = $wpdb->get_results( $wpdb->prepare( "SELECT membership_id, COUNT(*) AS payments, SUM(total) AS revenue FROM {$t} WHERE status='complete' AND created_at BETWEEN %s AND %s GROUP BY membership_id ORDER BY revenue DESC", $r['from'], $r['to'] ) ); // phpcs:ignore
		$by_gateway  = $wpdb->get_results( $wpdb->prepare( "SELECT gateway, COUNT(*) AS payments, SUM(total) AS revenue FROM {$t} WHERE status='complete' AND created_at BETWEEN %s AND %s GROUP BY gateway ORDER BY revenue DESC", $r['from'], $r['to'] ) ); // phpcs:ignore

		$courses = array();
		if ( class_exists( 'MGM_Courses' ) ) {
			$courses = $wpdb->get_results( $wpdb->prepare( 'SELECT course_id, COUNT(DISTINCT user_id) AS learners, COUNT(*) AS lessons FROM ' . MGM_Install::table( 'course_progress' ) . ' WHERE completed_at BETWEEN %s AND %s GROUP BY course_id ORDER BY learners DESC LIMIT 10', $r['from'], $r['to'] ) ); // phpcs:ignore
		}
		$events = array();
		if ( class_exists( 'MGM_Events' ) ) {
			$events = $wpdb->get_results( $wpdb->prepare( 'SELECT event_id, SUM(status=\'registered\') AS registered, SUM(attended) AS attended FROM ' . MGM_Install::table( 'event_registrations' ) . ' WHERE created_at BETWEEN %s AND %s GROUP BY event_id ORDER BY registered DESC LIMIT 10', $r['from'], $r['to'] ) ); // phpcs:ignore
		}

		$revenue = array_sum( $filled );
		return compact( 'filled', 'revenue', 'new_members', 'cancelled', 'active_subs', 'refunds', 'by_plan', 'by_gateway', 'courses', 'events' );
	}

	public static function page() {
		$r = self::range();
		$d = self::data( $r );
		MGM_Admin::header( __( 'Reports', 'mg-membership' ), array( 'desc' => __( 'Revenue, signups, churn, course activity and event registrations.', 'mg-membership' ) ) );

		echo '<div class="mgm-toolbar"><form method="get" class="mgm-filter"><input type="hidden" name="page" value="mgm-reports"><select name="range" onchange="this.form.submit()">';
		foreach ( array( '7d' => __( 'Last 7 days', 'mg-membership' ), '30d' => __( 'Last 30 days', 'mg-membership' ), '90d' => __( 'Last 90 days', 'mg-membership' ), '12m' => __( 'Last 12 months', 'mg-membership' ), 'ytd' => __( 'Year to date', 'mg-membership' ), 'custom' => __( 'Custom', 'mg-membership' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $r['preset'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> <input type="date" name="from" value="' . esc_attr( substr( $r['from'], 0, 10 ) ) . '"> – <input type="date" name="to" value="' . esc_attr( substr( $r['to'], 0, 10 ) ) . '"> ';
		submit_button( __( 'Apply', 'mg-membership' ), 'secondary', '', false );
		echo ' <a class="button" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'mgm_report_csv', 'range' => $r['preset'], 'from' => substr( $r['from'], 0, 10 ), 'to' => substr( $r['to'], 0, 10 ) ), admin_url( 'admin-post.php' ) ), 'mgm_report_csv' ) ) . '">' . esc_html__( 'Export CSV', 'mg-membership' ) . '</a></form></div>';

		$cards = array(
			array( __( 'Revenue', 'mg-membership' ), mgm_price( $d['revenue'] ) ),
			array( __( 'New members', 'mg-membership' ), number_format_i18n( $d['new_members'] ) ),
			array( __( 'Cancellations', 'mg-membership' ), number_format_i18n( $d['cancelled'] ) ),
			array( __( 'Churn rate', 'mg-membership' ), ( $d['active_subs'] + $d['cancelled'] ) ? round( $d['cancelled'] / ( $d['active_subs'] + $d['cancelled'] ) * 100, 1 ) . '%' : '0%' ),
			array( __( 'Refunded', 'mg-membership' ), mgm_price( $d['refunds'] ) ),
		);
		echo '<div class="mgm-cards">';
		foreach ( $cards as $c ) {
			printf( '<div class="mgm-card"><div><div class="mgm-card-value">%s</div><div class="mgm-card-label">%s</div></div></div>', esc_html( $c[1] ), esc_html( $c[0] ) );
		}
		echo '</div>';

		// Bar chart (pure CSS, no external libraries).
		$max = max( 1, max( $d['filled'] ? $d['filled'] : array( 0 ) ) );
		echo '<div class="mgm-panel"><h2>' . esc_html__( 'Revenue over time', 'mg-membership' ) . '</h2><div class="mgm-chart">';
		$n     = count( $d['filled'] );
		$every = max( 1, (int) ceil( $n / 10 ) );
		$i     = 0;
		foreach ( $d['filled'] as $period => $value ) {
			$h     = $value > 0 ? max( 2, round( $value / $max * 100 ) ) : 0;
			$label = 'month' === $r['group'] ? date_i18n( 'M y', strtotime( $period . '-01' ) ) : date_i18n( 'M j', strtotime( $period ) );
			$show  = 0 === $i % $every || $i === $n - 1;
			printf( '<div class="mgm-bar" title="%1$s: %2$s"><span style="height:%3$d%%"></span>%4$s</div>', esc_attr( $label ), esc_attr( mgm_price( $value ) ), (int) $h, $show ? '<em>' . esc_html( $label ) . '</em>' : '' );
			++$i;
		}
		echo '</div></div>';

		echo '<div class="mgm-grid-2">';
		self::table(
			__( 'Revenue by membership', 'mg-membership' ),
			array( __( 'Membership', 'mg-membership' ), __( 'Payments', 'mg-membership' ), __( 'Revenue', 'mg-membership' ) ),
			array_map(
				function ( $row ) {
					return array( get_the_title( $row->membership_id ), (int) $row->payments, mgm_price( $row->revenue ) );
				},
				$d['by_plan']
			)
		);
		self::table(
			__( 'Revenue by gateway', 'mg-membership' ),
			array( __( 'Gateway', 'mg-membership' ), __( 'Payments', 'mg-membership' ), __( 'Revenue', 'mg-membership' ) ),
			array_map(
				function ( $row ) {
					return array( $row->gateway, (int) $row->payments, mgm_price( $row->revenue ) );
				},
				$d['by_gateway']
			)
		);
		if ( class_exists( 'MGM_Courses' ) ) {
			self::table(
				__( 'Course activity', 'mg-membership' ),
				array( __( 'Course', 'mg-membership' ), __( 'Active learners', 'mg-membership' ), __( 'Lessons completed', 'mg-membership' ) ),
				array_map(
					function ( $row ) {
						return array( get_the_title( $row->course_id ), (int) $row->learners, (int) $row->lessons );
					},
					$d['courses']
				)
			);
		}
		if ( class_exists( 'MGM_Events' ) ) {
			self::table(
				__( 'Event registrations', 'mg-membership' ),
				array( __( 'Event', 'mg-membership' ), __( 'Registered', 'mg-membership' ), __( 'Attended', 'mg-membership' ) ),
				array_map(
					function ( $row ) {
						return array( get_the_title( $row->event_id ), (int) $row->registered, (int) $row->attended );
					},
					$d['events']
				)
			);
		}
		echo '</div>';
		MGM_Admin::footer();
	}

	private static function table( $title, $head, $rows ) {
		echo '<div class="mgm-panel"><h2>' . esc_html( $title ) . '</h2><table class="widefat mgm-table"><thead><tr>';
		foreach ( $head as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $row as $cell ) {
				echo '<td>' . esc_html( $cell ) . '</td>';
			}
			echo '</tr>';
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="' . count( $head ) . '">' . esc_html__( 'No data for this period.', 'mg-membership' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function csv() {
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			wp_die();
		}
		check_admin_referer( 'mgm_report_csv' );
		$r = self::range();
		$d = self::data( $r );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=mgm-report-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, MGM_Security_Core::csv_row( array( 'period', 'revenue' ) ) );
		foreach ( $d['filled'] as $p => $v ) {
			fputcsv( $out, MGM_Security_Core::csv_row( array( $p, $v ) ) );
		}
		fputcsv( $out, MGM_Security_Core::csv_row( array() ) );
		fputcsv( $out, MGM_Security_Core::csv_row( array( 'membership', 'payments', 'revenue' ) ) );
		foreach ( $d['by_plan'] as $row ) {
			fputcsv( $out, MGM_Security_Core::csv_row( array( get_the_title( $row->membership_id ), $row->payments, $row->revenue ) ) );
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
}

MGM_Reports::init();
