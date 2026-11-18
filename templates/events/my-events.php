<?php
/**
 * Events the user registered for.
 *
 * @var int $user_id
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . MGM_Events::table() . " WHERE user_id = %d AND status != 'cancelled' ORDER BY id DESC", $user_id ) ); // phpcs:ignore
?>
<?php if ( ! $rows ) : ?>
	<?php echo MGM_Account::empty_state( __( 'No events yet', 'mg-membership' ), __( 'You have not registered for any seminars or events.', 'mg-membership' ), __( 'Browse events', 'mg-membership' ), mgm_page_url( 'events' ), 'events' ); // phpcs:ignore ?>
<?php else : ?>
	<div class="mgm-box mgm-box-flush"><table class="mgm-table">
		<thead><tr><th><?php esc_html_e( 'Event', 'mg-membership' ); ?></th><th><?php esc_html_e( 'When', 'mg-membership' ); ?></th><th><?php esc_html_e( 'Ticket', 'mg-membership' ); ?></th><th><?php esc_html_e( 'Status', 'mg-membership' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $rows as $r ) : ?>
			<?php if ( 'publish' !== get_post_status( $r->event_id ) ) { continue; } ?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Event', 'mg-membership' ); ?>"><a href="<?php echo esc_url( get_permalink( $r->event_id ) ); ?>"><?php echo esc_html( get_the_title( $r->event_id ) ); ?></a></td>
				<td data-label="<?php esc_attr_e( 'When', 'mg-membership' ); ?>"><?php echo esc_html( MGM_Events::date_range( $r->event_id ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Ticket', 'mg-membership' ); ?>"><code><?php echo esc_html( $r->ticket_code ); ?></code></td>
				<td data-label="<?php esc_attr_e( 'Status', 'mg-membership' ); ?>"><?php echo esc_html( MGM_Events::is_past( $r->event_id ) ? ( $r->attended ? __( 'Attended', 'mg-membership' ) : __( 'Past', 'mg-membership' ) ) : ucfirst( $r->status ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table></div>
<?php endif; ?>
