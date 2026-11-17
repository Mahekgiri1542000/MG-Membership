<?php
/**
 * Account dashboard.
 *
 * @var WP_User $user
 */

defined( 'ABSPATH' ) || exit;

$active = MGM_Member::active_membership_ids( $user->ID );
?>
<h2><?php echo esc_html( sprintf( __( 'Hello, %s', 'mg-membership' ), $user->first_name ? $user->first_name : $user->display_name ) ); ?></h2>

<div class="mgm-account-cards">
	<?php foreach ( $active as $mid ) : ?>
		<?php $exp = MGM_Member::membership_expires( $user->ID, $mid ); ?>
		<div class="mgm-account-card">
			<span class="mgm-status mgm-status-active"><?php esc_html_e( 'Active', 'mg-membership' ); ?></span>
			<h3><?php echo esc_html( get_the_title( $mid ) ); ?></h3>
			<p><?php echo esc_html( $exp ? sprintf( __( 'Access until %s', 'mg-membership' ), mgm_date( $exp ) ) : __( 'Lifetime access', 'mg-membership' ) ); ?></p>
		</div>
	<?php endforeach; ?>
	<?php if ( ! $active ) : ?>
		<div class="mgm-account-card mgm-account-card-empty">
			<h3><?php esc_html_e( 'No active membership', 'mg-membership' ); ?></h3>
			<p><a class="mgm-button" href="<?php echo esc_url( mgm_page_url( 'pricing' ) ); ?>"><?php esc_html_e( 'Browse plans', 'mg-membership' ); ?></a></p>
		</div>
	<?php endif; ?>
</div>

<?php do_action( 'mgm_account_dashboard', $user ); ?>
