<?php
/**
 * Admin dashboard: KPIs with trends, revenue chart, plan mix, activity, attention items, setup checklist.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Admin_Dashboard {

	const RANGES = array(
		'7d'  => 7,
		'30d' => 30,
		'90d' => 90,
		'12m' => 365,
	);

	public static function range_key() {
		$r = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : ''; // phpcs:ignore
		if ( ! isset( self::RANGES[ $r ] ) ) {
			$saved = get_user_meta( get_current_user_id(), '_mgm_dash_range', true );
			$r     = isset( self::RANGES[ $saved ] ) ? $saved : '30d';
		} else {
			update_user_meta( get_current_user_id(), '_mgm_dash_range', $r );
		}
		return $r;
	}

	/* ------------------------------ Data ------------------------------- */

	private static function bounds( $days, $offset = 0 ) {
		$end   = strtotime( 'tomorrow' ) - 1 - $offset * $days * DAY_IN_SECONDS;
		$start = $end - $days * DAY_IN_SECONDS + 1;
		return array( gmdate( 'Y-m-d H:i:s', $start ), gmdate( 'Y-m-d H:i:s', $end ), $start, $end );
	}

	private static function sum_revenue( $from, $to ) {
		global $wpdb;
		return (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(total),0) FROM ' . MGM_Transaction::table() . " WHERE status='complete' AND created_at BETWEEN %s AND %s", $from, $to ) ); // phpcs:ignore
	}

	private static function new_members( $from, $to ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM (SELECT user_id, MIN(created_at) first FROM ' . MGM_Transaction::table() . " WHERE status='complete' GROUP BY user_id) x WHERE first BETWEEN %s AND %s", $from, $to ) ); // phpcs:ignore
	}

	private static function cancellations( $from, $to ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . MGM_Subscription::table() . ' WHERE cancelled_at BETWEEN %s AND %s', $from, $to ) ); // phpcs:ignore
	}

	/**
	 * Revenue + signups bucketed per day (or month for 12m).
	 */
	private static function series( $range ) {
		global $wpdb;
		$days    = self::RANGES[ $range ];
		$monthly = '12m' === $range;
		list( $from, $to, $start ) = self::bounds( $days );
		$fmt = $monthly ? '%Y-%m' : '%Y-%m-%d';
		$t   = MGM_Transaction::table();

		$rev = $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(created_at, %s) p, SUM(total) v FROM {$t} WHERE status='complete' AND created_at BETWEEN %s AND %s GROUP BY p", $fmt, $from, $to ), OBJECT_K ); // phpcs:ignore
		$new = $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(first, %s) p, COUNT(*) v FROM (SELECT user_id, MIN(created_at) first FROM {$t} WHERE status='complete' GROUP BY user_id) x WHERE first BETWEEN %s AND %s GROUP BY p", $fmt, $from, $to ), OBJECT_K ); // phpcs:ignore

		$out    = array();
		$cursor = $monthly ? strtotime( gmdate( 'Y-m-01', $start ) ) : $start;
		$end    = strtotime( $to );
		while ( $cursor <= $end ) {
			$key   = $monthly ? gmdate( 'Y-m', $cursor ) : gmdate( 'Y-m-d', $cursor );
			$out[] = array(
				'k'     => $key,
				'label' => $monthly ? date_i18n( 'M Y', $cursor ) : date_i18n( 'M j', $cursor ),
				'short' => $monthly ? date_i18n( 'M', $cursor ) : date_i18n( 'M j', $cursor ),
				'rev'   => isset( $rev[ $key ] ) ? round( (float) $rev[ $key ]->v, 2 ) : 0,
				'new'   => isset( $new[ $key ] ) ? (int) $new[ $key ]->v : 0,
			);
			$cursor = $monthly ? strtotime( '+1 month', $cursor ) : $cursor + DAY_IN_SECONDS;
		}
		return $out;
	}

	private static function delta( $now, $prev ) {
		if ( $prev <= 0 ) {
			return $now > 0 ? array( 'dir' => 'up', 'pct' => null ) : array( 'dir' => 'flat', 'pct' => 0 );
		}
		$pct = round( ( $now - $prev ) / $prev * 100 );
		return array(
			'dir' => $pct > 0 ? 'up' : ( $pct < 0 ? 'down' : 'flat' ),
			'pct' => abs( $pct ),
		);
	}

	public static function data( $range ) {
		global $wpdb;
		$days = self::RANGES[ $range ];
		list( $from, $to )   = self::bounds( $days );
		list( $pfrom, $pto ) = self::bounds( $days, 1 );
		$t   = MGM_Transaction::table();
		$s   = MGM_Subscription::table();
		$now = mgm_now();

		$base  = MGM_Admin::stats();
		$rev   = self::sum_revenue( $from, $to );
		$prev  = self::sum_revenue( $pfrom, $pto );
		$new   = self::new_members( $from, $to );
		$pnew  = self::new_members( $pfrom, $pto );
		$canc  = self::cancellations( $from, $to );
		$pcanc = self::cancellations( $pfrom, $pto );

		$plans = $wpdb->get_results( $wpdb->prepare( "SELECT membership_id, COUNT(DISTINCT user_id) members FROM {$t} WHERE status='complete' AND (expires_at IS NULL OR expires_at='0000-00-00 00:00:00' OR expires_at > %s) GROUP BY membership_id ORDER BY members DESC", $now ) ); // phpcs:ignore
		$plan_rev = $wpdb->get_results( $wpdb->prepare( "SELECT membership_id, SUM(total) revenue FROM {$t} WHERE status='complete' AND created_at BETWEEN %s AND %s GROUP BY membership_id", $from, $to ), OBJECT_K ); // phpcs:ignore

		return array(
			'series'        => self::series( $range ),
			'revenue'       => $rev,
			'revenue_delta' => self::delta( $rev, $prev ),
			'new'           => $new,
			'new_delta'     => self::delta( $new, $pnew ),
			'cancel'        => $canc,
			'cancel_delta'  => self::delta( $canc, $pcanc ),
			'active'        => $base['active_members'],
			'mrr'           => $base['mrr'],
			'active_subs'   => $base['active_subs'],
			'pending'       => $base['pending'],
			'suspended'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$s} WHERE status='suspended'" ), // phpcs:ignore
			'plans'         => $plans,
			'plan_rev'      => $plan_rev,
		);
	}

	/**
	 * Merged recent activity feed.
	 */
	private static function activity( $limit = 8 ) {
		global $wpdb;
		$t     = MGM_Transaction::table();
		$s     = MGM_Subscription::table();
		$items = array();
		foreach ( $wpdb->get_results( "SELECT t.*, u.display_name, u.user_email FROM {$t} t LEFT JOIN {$wpdb->users} u ON u.ID = t.user_id ORDER BY t.id DESC LIMIT {$limit}" ) as $r ) { // phpcs:ignore
			$type = 'complete' === $r->status ? ( (float) $r->total > 0 ? 'payment' : 'join' ) : $r->status;
			$items[] = array(
				'time' => strtotime( $r->created_at . ' UTC' ),
				'type' => $type,
				'who'  => $r->display_name ? $r->display_name : __( 'Deleted user', 'mg-membership' ),
				'uid'  => (int) $r->user_id,
				'what' => get_the_title( $r->membership_id ),
				'amt'  => (float) $r->total,
			);
		}
		foreach ( $wpdb->get_results( "SELECT s.*, u.display_name FROM {$s} s LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id WHERE s.cancelled_at IS NOT NULL ORDER BY s.cancelled_at DESC LIMIT {$limit}" ) as $r ) { // phpcs:ignore
			$items[] = array(
				'time' => strtotime( $r->cancelled_at . ' UTC' ),
				'type' => 'cancelled',
				'who'  => $r->display_name ? $r->display_name : __( 'Deleted user', 'mg-membership' ),
				'uid'  => (int) $r->user_id,
				'what' => get_the_title( $r->membership_id ),
				'amt'  => 0,
			);
		}
		usort(
			$items,
			function ( $a, $b ) {
				return $b['time'] - $a['time'];
			}
		);
		return array_slice( $items, 0, $limit );
	}

	/**
	 * Setup checklist (auto-detected).
	 */
	private static function checklist() {
		$gateways = array_filter(
			mgm()->gateways,
			function ( $g ) {
				return $g->is_enabled();
			}
		);
		$pricing  = (int) mgm_option( 'page_pricing' );
		return array(
			array(
				'done'  => (bool) $gateways,
				'label' => __( 'Turn on a payment method', 'mg-membership' ),
				'hint'  => __( 'Stripe, PayPal or offline payments', 'mg-membership' ),
				'url'   => admin_url( 'admin.php?page=mgm-settings&tab=payments' ),
			),
			array(
				'done'  => (bool) mgm_membership_options(),
				'label' => __( 'Create your first membership', 'mg-membership' ),
				'hint'  => __( 'Price, billing period and trial', 'mg-membership' ),
				'url'   => admin_url( 'post-new.php?post_type=mgm_membership' ),
			),
			array(
				'done'  => (bool) MGM_Access::rules() || (bool) get_posts( array( 'post_type' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_mgm_post_memberships', 'meta_compare' => '!=', 'meta_value' => 'a:0:{}' ) ), // phpcs:ignore
				'label' => __( 'Protect some content', 'mg-membership' ),
				'hint'  => __( 'Add an access rule or lock a post', 'mg-membership' ),
				'url'   => admin_url( 'post-new.php?post_type=mgm_rule' ),
			),
			array(
				'done'  => $pricing && 'publish' === get_post_status( $pricing ),
				'label' => __( 'Publish your pricing page', 'mg-membership' ),
				'hint'  => __( 'Shortcode, block or builder widget', 'mg-membership' ),
				'url'   => $pricing ? (string) get_edit_post_link( $pricing ) : admin_url( 'admin.php?page=mgm-settings&tab=pages' ),
			),
			array(
				'done'  => (bool) MGM_Transaction::query( array( 'count' => true ) ),
				'label' => __( 'Get your first member', 'mg-membership' ),
				'hint'  => __( 'Share the pricing page or add one manually', 'mg-membership' ),
				'url'   => admin_url( 'admin.php?page=mgm-transactions&new=1' ),
			),
		);
	}

	/* ---------------------------- Rendering ---------------------------- */

	private static function delta_html( $d, $invert = false, $label = '' ) {
		if ( 'flat' === $d['dir'] ) {
			return '<span class="mgmd-delta is-flat"><span aria-hidden="true">→</span> ' . esc_html__( 'No change', 'mg-membership' ) . ' <span class="mgmd-delta-cmp">' . esc_html( $label ) . '</span></span>';
		}
		$good = ( 'up' === $d['dir'] ) !== $invert;
		if ( null === $d['pct'] ) {
			$label = __( 'from none in the previous period', 'mg-membership' );
		}
		$text = null === $d['pct'] ? __( 'Up', 'mg-membership' ) : $d['pct'] . '%';
		return sprintf(
			'<span class="mgmd-delta %1$s"><span aria-hidden="true">%2$s</span> <span class="screen-reader-text">%3$s</span>%4$s <span class="mgmd-delta-cmp">%5$s</span></span>',
			$good ? 'is-good' : 'is-bad',
			'up' === $d['dir'] ? '▲' : '▼',
			esc_html( 'up' === $d['dir'] ? __( 'Up', 'mg-membership' ) : __( 'Down', 'mg-membership' ) ),
			esc_html( $text ),
			esc_html( $label )
		);
	}

	private static function sparkline( $values ) {
		$n = count( $values );
		if ( $n < 2 || ! array_sum( $values ) ) {
			return '<svg class="mgmd-spark" viewBox="0 0 100 28" aria-hidden="true"><line x1="0" y1="26" x2="100" y2="26" /></svg>';
		}
		$max = max( $values );
		$pts = array();
		foreach ( array_values( $values ) as $i => $v ) {
			$pts[] = round( $i / ( $n - 1 ) * 100, 2 ) . ',' . round( 26 - ( $max ? $v / $max : 0 ) * 22, 2 );
		}
		return '<svg class="mgmd-spark" viewBox="0 0 100 28" preserveAspectRatio="none" aria-hidden="true"><polyline points="' . esc_attr( implode( ' ', $pts ) ) . '" /></svg>';
	}

	private static function time_ago( $ts ) {
		return $ts ? sprintf( __( '%s ago', 'mg-membership' ), human_time_diff( $ts, time() ) ) : '';
	}

	public static function render() {
		$range = self::range_key();
		$d     = self::data( $range );
		$user  = wp_get_current_user();
		$hour  = (int) current_time( 'G' );
		$greet = $hour < 12 ? __( 'Good morning', 'mg-membership' ) : ( $hour < 18 ? __( 'Good afternoon', 'mg-membership' ) : __( 'Good evening', 'mg-membership' ) );
		$cmp   = array(
			'7d'  => __( 'vs previous 7 days', 'mg-membership' ),
			'30d' => __( 'vs previous 30 days', 'mg-membership' ),
			'90d' => __( 'vs previous 90 days', 'mg-membership' ),
			'12m' => __( 'vs previous 12 months', 'mg-membership' ),
		)[ $range ];
		$range_labels = array(
			'7d'  => __( '7 days', 'mg-membership' ),
			'30d' => __( '30 days', 'mg-membership' ),
			'90d' => __( '90 days', 'mg-membership' ),
			'12m' => __( '12 months', 'mg-membership' ),
		);
		$checklist = self::checklist();
		$done      = count( array_filter( wp_list_pluck( $checklist, 'done' ) ) );
		$rev_vals  = wp_list_pluck( $d['series'], 'rev' );
		$new_vals  = wp_list_pluck( $d['series'], 'new' );

		if ( ! wp_script_is( 'mgm-dashboard', 'enqueued' ) ) {
			wp_enqueue_style( 'mgm-dashboard', MGM_URL . 'assets/css/dashboard.css', array(), MGM_VERSION );
			wp_enqueue_script( 'mgm-dashboard', MGM_URL . 'assets/js/dashboard.js', array(), MGM_VERSION, true );
		}
		wp_localize_script(
			'mgm-dashboard',
			'MGMDash',
			array(
				'series'   => $d['series'],
				'currency' => mgm_currency_symbol(),
				'position' => mgm_option( 'currency_position', 'before' ),
				'i18n'     => array(
					'revenue' => __( 'Revenue', 'mg-membership' ),
					'members' => __( 'New members', 'mg-membership' ),
					'copied'  => __( 'Copied', 'mg-membership' ),
				),
			)
		);
		?>
		<div class="wrap mgmd">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Membership Dashboard', 'mg-membership' ); ?></h1>
			<?php if ( isset( $_GET['mgm_msg'] ) ) : // phpcs:ignore ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['mgm_msg'] ) ) ); // phpcs:ignore ?></p></div>
			<?php endif; ?>

			<header class="mgmd-header">
				<div>
					<p class="mgmd-eyebrow"><?php echo esc_html( date_i18n( 'l, F j' ) ); ?></p>
					<h2 class="mgmd-title"><?php echo esc_html( $greet . ', ' . ( $user->first_name ? $user->first_name : $user->display_name ) ); ?></h2>
					<p class="mgmd-sub"><?php echo esc_html( sprintf( _n( '%s active member', '%s active members', $d['active'], 'mg-membership' ), number_format_i18n( $d['active'] ) ) ); ?> · <?php echo esc_html( sprintf( __( '%s monthly recurring revenue', 'mg-membership' ), mgm_price( $d['mrr'] ) ) ); ?></p>
				</div>
				<div class="mgmd-actions">
					<a class="mgmd-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=mgm-transactions&new=1' ) ); ?>"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><?php esc_html_e( 'Add member', 'mg-membership' ); ?></a>
					<a class="mgmd-btn mgmd-btn-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=mgm_membership' ) ); ?>"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'New membership', 'mg-membership' ); ?></a>
				</div>
			</header>

			<?php
			$alerts = array();
			if ( $d['pending'] ) {
				$alerts[] = array( 'warning', sprintf( _n( '%d payment is waiting for your approval', '%d payments are waiting for your approval', $d['pending'], 'mg-membership' ), $d['pending'] ), admin_url( 'admin.php?page=mgm-transactions&status=pending' ), __( 'Review payments', 'mg-membership' ) );
			}
			if ( $d['suspended'] ) {
				$alerts[] = array( 'critical', sprintf( _n( '%d subscription has a failed payment', '%d subscriptions have failed payments', $d['suspended'], 'mg-membership' ), $d['suspended'] ), admin_url( 'admin.php?page=mgm-subscriptions&status=suspended' ), __( 'View subscriptions', 'mg-membership' ) );
			}
			if ( ! mgm()->enabled_gateways() ) {
				$alerts[] = array( 'warning', __( 'No payment method is enabled, so paid memberships cannot be purchased.', 'mg-membership' ), admin_url( 'admin.php?page=mgm-settings&tab=payments' ), __( 'Set up payments', 'mg-membership' ) );
			}
			$alerts = apply_filters( 'mgm_dashboard_alerts', $alerts );
			?>
			<?php if ( $alerts ) : ?>
				<div class="mgmd-alerts" role="region" aria-label="<?php esc_attr_e( 'Needs attention', 'mg-membership' ); ?>">
					<?php foreach ( $alerts as $a ) : ?>
						<div class="mgmd-alert is-<?php echo esc_attr( $a[0] ); ?>">
							<span class="dashicons <?php echo 'critical' === $a[0] ? 'dashicons-warning' : 'dashicons-flag'; ?>" aria-hidden="true"></span>
							<span class="mgmd-alert-text"><strong><?php echo esc_html( 'critical' === $a[0] ? __( 'Action needed:', 'mg-membership' ) : __( 'Heads up:', 'mg-membership' ) ); ?></strong> <?php echo esc_html( $a[1] ); ?></span>
							<a href="<?php echo esc_url( $a[2] ); ?>"><?php echo esc_html( $a[3] ); ?> →</a>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $done < count( $checklist ) ) : ?>
				<section class="mgmd-card mgmd-setup" aria-labelledby="mgmd-setup-title">
					<div class="mgmd-setup-head">
						<div>
							<h3 id="mgmd-setup-title"><?php esc_html_e( 'Finish setting up', 'mg-membership' ); ?></h3>
							<p><?php echo esc_html( sprintf( __( '%1$d of %2$d steps done', 'mg-membership' ), $done, count( $checklist ) ) ); ?></p>
						</div>
						<div class="mgmd-meter" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo count( $checklist ); ?>" aria-valuenow="<?php echo (int) $done; ?>"><span style="width:<?php echo (int) round( $done / count( $checklist ) * 100 ); ?>%"></span></div>
					</div>
					<ol class="mgmd-steps">
						<?php foreach ( $checklist as $i => $step ) : ?>
							<li class="<?php echo $step['done'] ? 'is-done' : ''; ?>">
								<a href="<?php echo esc_url( $step['url'] ); ?>">
									<span class="mgmd-step-num" aria-hidden="true">
										<?php if ( $step['done'] ) : ?>
											<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 8.5l3 3 6-7" /></svg>
										<?php else : ?>
											<?php echo (int) ( $i + 1 ); ?>
										<?php endif; ?>
									</span>
									<span class="mgmd-step-body">
										<span class="mgmd-step-title"><?php echo esc_html( $step['label'] ); ?></span>
										<span class="mgmd-step-hint"><?php echo esc_html( $step['done'] ? __( 'Completed', 'mg-membership' ) : $step['hint'] ); ?></span>
										<span class="screen-reader-text"><?php echo esc_html( $step['done'] ? __( '(done)', 'mg-membership' ) : __( '(to do)', 'mg-membership' ) ); ?></span>
									</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ol>
				</section>
			<?php endif; ?>

			<div class="mgmd-toolbar">
				<h3 class="mgmd-section-title"><?php esc_html_e( 'Performance', 'mg-membership' ); ?></h3>
				<nav class="mgmd-segment" aria-label="<?php esc_attr_e( 'Date range', 'mg-membership' ); ?>">
					<?php foreach ( $range_labels as $k => $l ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'range', $k, admin_url( 'admin.php?page=mgm' ) ) ); ?>" class="<?php echo $k === $range ? 'is-active' : ''; ?>" <?php echo $k === $range ? 'aria-current="true"' : ''; ?>><?php echo esc_html( $l ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>

			<div class="mgmd-kpis">
				<div class="mgmd-card mgmd-kpi">
					<p class="mgmd-kpi-label"><?php esc_html_e( 'Revenue', 'mg-membership' ); ?></p>
					<p class="mgmd-kpi-value"><?php echo esc_html( mgm_price( $d['revenue'] ) ); ?></p>
					<?php echo self::delta_html( $d['revenue_delta'], false, $cmp ); // phpcs:ignore ?>
					<?php echo self::sparkline( $rev_vals ); // phpcs:ignore ?>
				</div>
				<div class="mgmd-card mgmd-kpi">
					<p class="mgmd-kpi-label"><?php esc_html_e( 'New members', 'mg-membership' ); ?></p>
					<p class="mgmd-kpi-value"><?php echo esc_html( number_format_i18n( $d['new'] ) ); ?></p>
					<?php echo self::delta_html( $d['new_delta'], false, $cmp ); // phpcs:ignore ?>
					<?php echo self::sparkline( $new_vals ); // phpcs:ignore ?>
				</div>
				<div class="mgmd-card mgmd-kpi">
					<p class="mgmd-kpi-label"><?php esc_html_e( 'Recurring revenue (MRR)', 'mg-membership' ); ?></p>
					<p class="mgmd-kpi-value"><?php echo esc_html( mgm_price( $d['mrr'] ) ); ?></p>
					<p class="mgmd-kpi-note"><?php echo esc_html( sprintf( _n( 'from %s active subscription', 'from %s active subscriptions', $d['active_subs'], 'mg-membership' ), number_format_i18n( $d['active_subs'] ) ) ); ?></p>
				</div>
				<div class="mgmd-card mgmd-kpi">
					<p class="mgmd-kpi-label"><?php esc_html_e( 'Cancellations', 'mg-membership' ); ?></p>
					<p class="mgmd-kpi-value"><?php echo esc_html( number_format_i18n( $d['cancel'] ) ); ?></p>
					<?php echo self::delta_html( $d['cancel_delta'], true, $cmp ); // phpcs:ignore ?>
				</div>
			</div>

			<div class="mgmd-grid">
				<section class="mgmd-card mgmd-chart-card" aria-labelledby="mgmd-chart-title">
					<div class="mgmd-card-head">
						<div>
							<h3 id="mgmd-chart-title"><?php esc_html_e( 'Revenue', 'mg-membership' ); ?></h3>
							<p class="mgmd-muted"><?php echo esc_html( sprintf( __( 'Completed payments, last %s', 'mg-membership' ), $range_labels[ $range ] ) ); ?></p>
						</div>
						<button type="button" class="mgmd-link" data-mgmd-table-toggle aria-expanded="false" aria-controls="mgmd-chart-table"><?php esc_html_e( 'View as table', 'mg-membership' ); ?></button>
					</div>
					<?php if ( array_sum( $rev_vals ) > 0 ) : ?>
						<div class="mgmd-chart" data-mgmd-chart role="img" aria-label="<?php echo esc_attr( sprintf( __( 'Revenue chart, total %s', 'mg-membership' ), mgm_price( $d['revenue'] ) ) ); ?>">
							<div class="mgmd-tooltip" hidden></div>
						</div>
					<?php else : ?>
						<div class="mgmd-empty">
							<span class="dashicons dashicons-chart-area" aria-hidden="true"></span>
							<p><strong><?php esc_html_e( 'No revenue in this period yet', 'mg-membership' ); ?></strong></p>
							<p class="mgmd-muted"><?php esc_html_e( 'Completed payments will appear here as a chart.', 'mg-membership' ); ?></p>
						</div>
					<?php endif; ?>
					<div id="mgmd-chart-table" class="mgmd-table-wrap" hidden>
						<table class="mgmd-table">
							<thead><tr><th scope="col"><?php esc_html_e( 'Period', 'mg-membership' ); ?></th><th scope="col"><?php esc_html_e( 'Revenue', 'mg-membership' ); ?></th><th scope="col"><?php esc_html_e( 'New members', 'mg-membership' ); ?></th></tr></thead>
							<tbody>
							<?php foreach ( array_reverse( $d['series'] ) as $row ) : ?>
								<tr><td><?php echo esc_html( $row['label'] ); ?></td><td><?php echo esc_html( mgm_price( $row['rev'] ) ); ?></td><td><?php echo (int) $row['new']; ?></td></tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>

				<section class="mgmd-card" aria-labelledby="mgmd-plans-title">
					<div class="mgmd-card-head">
						<div>
							<h3 id="mgmd-plans-title"><?php esc_html_e( 'Active members by plan', 'mg-membership' ); ?></h3>
							<p class="mgmd-muted"><?php esc_html_e( 'Revenue shown for the selected period', 'mg-membership' ); ?></p>
						</div>
					</div>
					<?php if ( $d['plans'] ) : ?>
						<?php $max = max( 1, (int) $d['plans'][0]->members ); ?>
						<ul class="mgmd-bars">
							<?php foreach ( array_slice( $d['plans'], 0, 6 ) as $p ) : ?>
								<?php $pr = isset( $d['plan_rev'][ $p->membership_id ] ) ? (float) $d['plan_rev'][ $p->membership_id ]->revenue : 0; ?>
								<li>
									<div class="mgmd-bar-label">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=mgm-members&membership=' . (int) $p->membership_id ) ); ?>"><?php echo esc_html( get_the_title( $p->membership_id ) ); ?></a>
										<span><strong><?php echo (int) $p->members; ?></strong> · <?php echo esc_html( mgm_price( $pr ) ); ?></span>
									</div>
									<div class="mgmd-bar" title="<?php echo esc_attr( sprintf( '%s: %d', get_the_title( $p->membership_id ), $p->members ) ); ?>"><span style="width:<?php echo esc_attr( max( 2, round( $p->members / $max * 100 ) ) ); ?>%"></span></div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<div class="mgmd-empty mgmd-empty-sm">
							<p class="mgmd-muted"><?php esc_html_e( 'No active members yet.', 'mg-membership' ); ?></p>
							<a class="mgmd-btn" href="<?php echo esc_url( admin_url( 'edit.php?post_type=mgm_membership' ) ); ?>"><?php esc_html_e( 'Manage memberships', 'mg-membership' ); ?></a>
						</div>
					<?php endif; ?>
				</section>
			</div>

			<div class="mgmd-grid">
				<section class="mgmd-card" aria-labelledby="mgmd-activity-title">
					<div class="mgmd-card-head">
						<h3 id="mgmd-activity-title"><?php esc_html_e( 'Recent activity', 'mg-membership' ); ?></h3>
						<a class="mgmd-link" href="<?php echo esc_url( admin_url( 'admin.php?page=mgm-transactions' ) ); ?>"><?php esc_html_e( 'All transactions', 'mg-membership' ); ?> →</a>
					</div>
					<?php $feed = self::activity(); ?>
					<?php if ( $feed ) : ?>
						<ul class="mgmd-feed">
							<?php
							$verbs = array(
								'payment'   => array( __( 'paid for', 'mg-membership' ), 'dashicons-money-alt', 'good' ),
								'join'      => array( __( 'joined', 'mg-membership' ), 'dashicons-admin-users', 'good' ),
								'pending'   => array( __( 'started checkout for', 'mg-membership' ), 'dashicons-clock', 'warning' ),
								'refunded'  => array( __( 'was refunded for', 'mg-membership' ), 'dashicons-undo', 'neutral' ),
								'failed'    => array( __( 'had a failed payment for', 'mg-membership' ), 'dashicons-warning', 'critical' ),
								'cancelled' => array( __( 'cancelled', 'mg-membership' ), 'dashicons-dismiss', 'neutral' ),
							);
							foreach ( $feed as $it ) :
								$v = $verbs[ $it['type'] ] ?? $verbs['pending'];
								?>
								<li>
									<span class="mgmd-feed-icon is-<?php echo esc_attr( $v[2] ); ?>"><span class="dashicons <?php echo esc_attr( $v[1] ); ?>" aria-hidden="true"></span></span>
									<span class="mgmd-feed-text">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=mgm-members&member=' . $it['uid'] ) ); ?>"><?php echo esc_html( $it['who'] ); ?></a>
										<?php echo esc_html( $v[0] ); ?> <strong><?php echo esc_html( $it['what'] ); ?></strong>
										<small><?php echo esc_html( self::time_ago( $it['time'] ) ); ?></small>
									</span>
									<?php if ( $it['amt'] > 0 ) : ?><span class="mgmd-feed-amt"><?php echo esc_html( mgm_price( $it['amt'] ) ); ?></span><?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<div class="mgmd-empty mgmd-empty-sm"><p class="mgmd-muted"><?php esc_html_e( 'Signups, payments and cancellations will show up here.', 'mg-membership' ); ?></p></div>
					<?php endif; ?>
				</section>

				<div class="mgmd-stack">
					<?php self::addons_card(); ?>
					<?php self::quick_links_card(); ?>
				</div>
			</div>

			<details class="mgmd-card mgmd-shortcodes">
				<summary><span class="dashicons dashicons-shortcode" aria-hidden="true"></span> <?php esc_html_e( 'Shortcode reference', 'mg-membership' ); ?> <small class="mgmd-muted"><?php esc_html_e( 'For any page builder, including ones without a native widget', 'mg-membership' ); ?></small></summary>
				<ul>
					<?php foreach ( MGM_Shortcodes::registry() as $tag => $el ) : ?>
						<li>
							<span><?php echo esc_html( $el['label'] ); ?></span>
							<button type="button" class="mgmd-code" data-mgmd-copy="<?php echo esc_attr( '[' . $tag . ( ! empty( $el['enclosing'] ) ? ']…[/' . $tag . ']' : ']' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Copy %s shortcode', 'mg-membership' ), $tag ) ); ?>"><code>[<?php echo esc_html( $tag ); ?>]</code><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
		</div>
		<?php
	}

	private static function addons_card() {
		$items = array();
		if ( class_exists( 'MGM_Courses' ) ) {
			$c       = wp_count_posts( 'mgm_course' );
			global $wpdb;
			$learners = (int) $wpdb->get_var( 'SELECT COUNT(DISTINCT user_id) FROM ' . MGM_Install::table( 'course_progress' ) ); // phpcs:ignore
			$items[]  = array( 'dashicons-welcome-learn-more', __( 'Courses', 'mg-membership' ), sprintf( __( '%1$d published · %2$d learners', 'mg-membership' ), (int) ( $c->publish ?? 0 ), $learners ), admin_url( 'edit.php?post_type=mgm_course' ) );
		}
		if ( class_exists( 'MGM_Events' ) ) {
			$next = get_posts(
				array(
					'post_type'   => 'mgm_event',
					'numberposts' => 1,
					'meta_key'    => '_mgm_event_start', // phpcs:ignore
					'orderby'     => 'meta_value',
					'order'       => 'ASC',
					'meta_query'  => array( array( 'key' => '_mgm_event_start', 'value' => wp_date( 'Y-m-d H:i' ), 'compare' => '>=' ) ), // phpcs:ignore
				)
			);
			$items[] = array(
				'dashicons-calendar-alt',
				__( 'Next seminar', 'mg-membership' ),
				$next ? sprintf( __( '%1$s · %2$d registered', 'mg-membership' ), get_the_title( $next[0] ), MGM_Events::count_registered( $next[0]->ID ) ) : __( 'Nothing scheduled', 'mg-membership' ),
				$next ? admin_url( 'edit.php?post_type=mgm_event&page=mgm-attendees&event=' . $next[0]->ID ) : admin_url( 'post-new.php?post_type=mgm_event' ),
			);
		}
		if ( class_exists( 'MGM_Coupons' ) ) {
			$cp      = wp_count_posts( 'mgm_coupon' );
			$items[] = array( 'dashicons-tickets-alt', __( 'Coupons', 'mg-membership' ), sprintf( _n( '%d active code', '%d active codes', (int) ( $cp->publish ?? 0 ), 'mg-membership' ), (int) ( $cp->publish ?? 0 ) ), admin_url( 'edit.php?post_type=mgm_coupon' ) );
		}
		if ( ! $items ) {
			return;
		}
		?>
		<section class="mgmd-card" aria-labelledby="mgmd-addons-title">
			<div class="mgmd-card-head">
				<h3 id="mgmd-addons-title"><?php esc_html_e( 'Learning & events', 'mg-membership' ); ?></h3>
				<a class="mgmd-link" href="<?php echo esc_url( admin_url( 'admin.php?page=mgm-addons' ) ); ?>"><?php esc_html_e( 'Add-ons', 'mg-membership' ); ?> →</a>
			</div>
			<ul class="mgmd-list">
				<?php foreach ( $items as $it ) : ?>
					<li><a href="<?php echo esc_url( $it[3] ); ?>"><span class="mgmd-list-icon"><span class="dashicons <?php echo esc_attr( $it[0] ); ?>" aria-hidden="true"></span></span><span><strong><?php echo esc_html( $it[1] ); ?></strong><small><?php echo esc_html( $it[2] ); ?></small></span><span class="dashicons dashicons-arrow-right-alt2 mgmd-chev" aria-hidden="true"></span></a></li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}

	private static function quick_links_card() {
		$links = array(
			array( 'dashicons-lock', __( 'Access rules', 'mg-membership' ), admin_url( 'edit.php?post_type=mgm_rule' ) ),
			array( 'dashicons-groups', __( 'Members', 'mg-membership' ), admin_url( 'admin.php?page=mgm-members' ) ),
			array( 'dashicons-update', __( 'Subscriptions', 'mg-membership' ), admin_url( 'admin.php?page=mgm-subscriptions' ) ),
			array( 'dashicons-admin-settings', __( 'Settings', 'mg-membership' ), admin_url( 'admin.php?page=mgm-settings' ) ),
		);
		if ( class_exists( 'MGM_Reports' ) ) {
			$links[] = array( 'dashicons-chart-bar', __( 'Reports', 'mg-membership' ), admin_url( 'admin.php?page=mgm-reports' ) );
		}
		$links[] = array( 'dashicons-external', __( 'View pricing page', 'mg-membership' ), mgm_page_url( 'pricing' ) );
		?>
		<section class="mgmd-card" aria-labelledby="mgmd-links-title">
			<h3 id="mgmd-links-title"><?php esc_html_e( 'Shortcuts', 'mg-membership' ); ?></h3>
			<div class="mgmd-tiles">
				<?php foreach ( $links as $l ) : ?>
					<a href="<?php echo esc_url( $l[2] ); ?>"><span class="dashicons <?php echo esc_attr( $l[0] ); ?>" aria-hidden="true"></span><?php echo esc_html( $l[1] ); ?></a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}
}
