<?php
/**
 * Account: subscriptions.
 *
 * @var WP_User $user
 */

defined( 'ABSPATH' ) || exit;

$subs   = MGM_Subscription::for_user( $user->ID );
$active = MGM_Member::active_membership_ids( $user->ID );
?>
<?php if ( ! $subs ) : ?>
	<?php echo MGM_Account::empty_state( __( 'No subscriptions yet', 'mg-membership' ), __( 'When you join a plan it will appear here, with its renewal date and options.', 'mg-membership' ), __( 'Browse plans', 'mg-membership' ), mgm_page_url( 'pricing' ), 'subscriptions' ); // phpcs:ignore ?>
<?php else : ?>
	<div class="mgm-box mgm-box-flush"><table class="mgm-table">
		<thead><tr><th><?php esc_html_e( 'Membership', 'mg-membership' ); ?></th><th><?php esc_html_e( 'Terms', 'mg-membership' ); ?></th><th><?php esc_html_e( 'Status', 'mg-membership' ); ?></th><th><?php esc_html_e( 'Next payment', 'mg-membership' ); ?></th><th></th></tr></thead>
		<tbody>
		<?php foreach ( $subs as $sub ) : ?>
			<?php
			$m       = new MGM_Membership( $sub->membership_id );
			$gateway = mgm()->gateway( $sub->gateway );
			$live    = in_array( $sub->status, array( 'active', 'trialing', 'suspended' ), true );
			?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Membership', 'mg-membership' ); ?>"><strong><?php echo esc_html( $m->title() ); ?></strong></td>
				<td data-label="<?php esc_attr_e( 'Terms', 'mg-membership' ); ?>"><?php echo esc_html( mgm_price( $sub->price ) . ' / ' . ( $sub->period > 1 ? $sub->period . ' ' : '' ) . $sub->period_unit ); ?></td>
				<td data-label="<?php esc_attr_e( 'Status', 'mg-membership' ); ?>"><span class="mgm-status mgm-status-<?php echo esc_attr( $sub->status ); ?>"><?php echo esc_html( MGM_Subscription::status_label( $sub->status ) ); ?></span></td>
				<td data-label="<?php esc_attr_e( 'Next payment', 'mg-membership' ); ?>"><?php echo esc_html( in_array( $sub->status, array( 'active', 'trialing' ), true ) ? mgm_date( $sub->next_payment_at ) : '—' ); ?></td>
				<td class="mgm-actions">
					<?php if ( $live ) : ?>
						<?php if ( $gateway && $gateway->update_payment_url( $sub ) ) : ?>
							<a class="mgm-link" href="<?php echo esc_url( $gateway->update_payment_url( $sub ) ); ?>"><?php esc_html_e( 'Update payment', 'mg-membership' ); ?></a>
						<?php endif; ?>
						<?php foreach ( $m->group_siblings() as $sib ) : ?>
							<a class="mgm-link" href="<?php echo esc_url( get_permalink( $sib ) ); ?>"><?php echo esc_html( sprintf( __( 'Switch to %s', 'mg-membership' ), get_the_title( $sib ) ) ); ?></a>
						<?php endforeach; ?>
						<a class="mgm-link mgm-link-danger" href="<?php echo esc_url( MGM_Account::cancel_url( $sub ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Cancel this subscription?', 'mg-membership' ) ); ?>');"><?php esc_html_e( 'Cancel', 'mg-membership' ); ?></a>
					<?php elseif ( ! in_array( (int) $sub->membership_id, $active, true ) ) : ?>
						<a class="mgm-link" href="<?php echo esc_url( $m->url() ); ?>"><?php esc_html_e( 'Resubscribe', 'mg-membership' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table></div>
<?php endif; ?>
