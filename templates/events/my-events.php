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
	<p><?php esc_html_e( 'You have not registered for any events yet.', 'mg-membership' ); ?> <a href="<?php echo esc_url( mgm_page_url( 'events' ) ); ?>"><?php esc_html_e( 'Browse events', 'mg-membership' ); ?></a></p>
<?php else : ?>
	<table class="mgm-table">
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
	</table>
<?php endif; ?>
