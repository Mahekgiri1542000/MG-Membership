<?php
/**
 * Account: payment history.
 *
 * @var WP_User $user
 */

defined( 'ABSPATH' ) || exit;

$rows = MGM_Transaction::query(
	array(
		'user_id'  => $user->ID,
		'per_page' => 50,
	)
);
?>
<h2><?php esc_html_e( 'Payments', 'mg-membership' ); ?></h2>
<?php if ( ! $rows ) : ?>
	<p><?php esc_html_e( 'No payments yet.', 'mg-membership' ); ?></p>
<?php else : ?>
	<table class="mgm-table">
		<thead><tr><th><?php esc_html_e( 'Date', 'mg-membership' ); ?></th><th><?php esc_html_e( 'Invoice #', 'mg-membership' ); ?></th><th><?php esc_html_e( 'Membership', 'mg-membership' ); ?></th><th><?php esc_html_e( 'Total', 'mg-membership' ); ?></th><th><?php esc_html_e( 'Status', 'mg-membership' ); ?></th><th></th></tr></thead>
		<tbody>
		<?php foreach ( $rows as $r ) : ?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Date', 'mg-membership' ); ?>"><?php echo esc_html( mgm_date( $r->created_at ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Invoice #', 'mg-membership' ); ?>"><code><?php echo esc_html( $r->trans_num ); ?></code></td>
				<td data-label="<?php esc_attr_e( 'Membership', 'mg-membership' ); ?>"><?php echo esc_html( get_the_title( $r->membership_id ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Total', 'mg-membership' ); ?>"><?php echo esc_html( mgm_price( $r->total ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Status', 'mg-membership' ); ?>"><span class="mgm-status mgm-status-<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( ucfirst( $r->status ) ); ?></span></td>
				<td><a class="mgm-link" href="<?php echo esc_url( add_query_arg( 'mgm_txn', rawurlencode( $r->trans_num ), mgm_page_url( 'thankyou' ) ) ); ?>"><?php esc_html_e( 'Receipt', 'mg-membership' ); ?></a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
