<?php
/**
 * Account dashboard.
 *
 * Copy to yourtheme/mg-membership/account/home.php to customise.
 *
 * @var WP_User $user
 */

defined( 'ABSPATH' ) || exit;

$active = MGM_Member::active_membership_ids( $user->ID );

// Next renewal among live subscriptions.
$next_payment = 0;
$sub_by_plan  = array();
foreach ( MGM_Subscription::for_user( $user->ID ) as $sub ) {
	if ( in_array( $sub->status, array( 'active', 'trialing' ), true ) ) {
		$sub_by_plan[ (int) $sub->membership_id ] = $sub;
		$ts = $sub->next_payment_at ? strtotime( $sub->next_payment_at ) : 0;
		if ( $ts && ( ! $next_payment || $ts < $next_payment ) ) {
			$next_payment = $ts;
		}
	}
}

$stats = array(
	'memberships' => array(
		'icon'  => 'membership',
		'label' => __( 'Active memberships', 'mg-membership' ),
		'value' => count( $active ),
	),
	'next'        => array(
		'icon'  => 'calendar',
		'label' => __( 'Next payment', 'mg-membership' ),
		'value' => $next_payment ? mgm_date( gmdate( 'Y-m-d H:i:s', $next_payment ) ) : '—',
	),
);
if ( class_exists( 'MGM_Courses' ) ) {
	$courses = MGM_Courses::enrolled_courses( $user->ID );
	$done    = 0;
	foreach ( $courses as $cid ) {
		if ( MGM_Courses::progress( $user->ID, $cid ) >= 100 ) {
			++$done;
		}
	}
	$stats['courses'] = array(
		'icon'  => 'courses',
		'label' => __( 'Courses completed', 'mg-membership' ),
		'value' => $courses ? $done . ' / ' . count( $courses ) : '0',
	);
}
$stats['since'] = array(
	'icon'  => 'clock',
	'label' => __( 'Member since', 'mg-membership' ),
	'value' => mgm_date( $user->user_registered ),
);
$stats = apply_filters( 'mgm_account_stats', $stats, $user );
?>

<?php if ( MGM_Appearance::on( 'acc_show_stats' ) && $stats ) : ?>
	<div class="mgm-stats">
		<?php foreach ( $stats as $key => $s ) : ?>
			<div class="mgm-stat mgm-stat-<?php echo esc_attr( $key ); ?>">
				<span class="mgm-stat-icon"><?php echo MGM_Account::icon( $s['icon'] ?? 'default' ); // phpcs:ignore ?></span>
				<span class="mgm-stat-body">
					<span class="mgm-stat-label"><?php echo esc_html( $s['label'] ); ?></span>
					<strong class="mgm-stat-value"><?php echo esc_html( (string) $s['value'] ); ?></strong>
				</span>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<section class="mgm-account-section">
	<h3 class="mgm-section-title"><?php esc_html_e( 'Your memberships', 'mg-membership' ); ?></h3>
	<?php if ( $active ) : ?>
		<div class="mgm-account-cards">
			<?php foreach ( $active as $mid ) : ?>
				<?php
				$exp = MGM_Member::membership_expires( $user->ID, $mid );
				$sub = $sub_by_plan[ (int) $mid ] ?? null;
				if ( $sub && 'trialing' === $sub->status ) {
					$line = sprintf( __( 'Free trial — first payment %s', 'mg-membership' ), mgm_date( $sub->next_payment_at ) );
				} elseif ( $sub && $sub->next_payment_at ) {
					$line = sprintf( __( 'Renews on %s', 'mg-membership' ), mgm_date( $sub->next_payment_at ) );
				} else {
					$line = $exp ? sprintf( __( 'Access until %s', 'mg-membership' ), mgm_date( $exp ) ) : __( 'Lifetime access', 'mg-membership' );
				}
				?>
				<div class="mgm-account-card">
					<div class="mgm-account-card-top">
						<span class="mgm-account-card-icon"><?php echo MGM_Account::icon( 'membership' ); // phpcs:ignore ?></span>
						<span class="mgm-status mgm-status-<?php echo esc_attr( $sub && 'trialing' === $sub->status ? 'trialing' : 'active' ); ?>"><?php echo esc_html( $sub && 'trialing' === $sub->status ? __( 'Trial', 'mg-membership' ) : __( 'Active', 'mg-membership' ) ); ?></span>
					</div>
					<h3><?php echo esc_html( get_the_title( $mid ) ); ?></h3>
					<p class="mgm-muted"><?php echo esc_html( $line ); ?></p>
					<div class="mgm-account-card-actions">
						<?php if ( $sub ) : ?>
							<a class="mgm-link" href="<?php echo esc_url( MGM_Account::tab_url( 'subscriptions' ) ); ?>"><?php esc_html_e( 'Manage', 'mg-membership' ); ?></a>
						<?php endif; ?>
						<a class="mgm-link" href="<?php echo esc_url( get_permalink( $mid ) ); ?>"><?php esc_html_e( 'View plan', 'mg-membership' ); ?></a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<?php
		echo MGM_Account::empty_state( // phpcs:ignore
			MGM_Appearance::text( 'acc_empty_title', $user ),
			MGM_Appearance::text( 'acc_empty_text', $user ),
			MGM_Appearance::text( 'acc_empty_button', $user ),
			mgm_page_url( 'pricing' ),
			'membership'
		);
		?>
	<?php endif; ?>
</section>

<?php do_action( 'mgm_account_dashboard', $user ); ?>
