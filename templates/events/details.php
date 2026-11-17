<?php
/**
 * Event details box.
 *
 * @var int $event_id
 */

defined( 'ABSPATH' ) || exit;

$type  = MGM_Events::meta( $event_id, 'location_type', 'in_person' );
$left  = MGM_Events::seats_left( $event_id );
$types = wp_get_post_terms( $event_id, 'mgm_event_type', array( 'fields' => 'names' ) );
?>
<div class="mgm-event-details">
	<?php if ( $types && ! is_wp_error( $types ) ) : ?><span class="mgm-tag-preview"><?php echo esc_html( implode( ', ', $types ) ); ?></span><?php endif; ?>
	<dl>
		<dt><?php esc_html_e( 'When', 'mg-membership' ); ?></dt>
		<dd><?php echo esc_html( MGM_Events::date_range( $event_id ) ); ?></dd>
		<dt><?php esc_html_e( 'Where', 'mg-membership' ); ?></dt>
		<dd><?php echo esc_html( MGM_Events::location_text( $event_id ) ); ?>
			<?php if ( 'online' !== $type && MGM_Events::meta( $event_id, 'address' ) ) : ?>
				<br><a href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( MGM_Events::meta( $event_id, 'address' ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View map', 'mg-membership' ); ?></a>
			<?php endif; ?></dd>
		<?php if ( MGM_Events::meta( $event_id, 'registration', '1' ) && $left >= 0 ) : ?>
			<dt><?php esc_html_e( 'Seats', 'mg-membership' ); ?></dt>
			<dd><?php echo $left > 0 ? esc_html( sprintf( _n( '%d seat left', '%d seats left', $left, 'mg-membership' ), $left ) ) : esc_html__( 'Fully booked', 'mg-membership' ); ?></dd>
		<?php endif; ?>
	</dl>
	<p class="mgm-small"><a href="<?php echo esc_url( MGM_Events::ics_url( $event_id ) ); ?>"><?php esc_html_e( 'Add to calendar (.ics)', 'mg-membership' ); ?></a> · <a href="<?php echo esc_url( MGM_Events::google_calendar_url( $event_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Google Calendar', 'mg-membership' ); ?></a></p>
</div>
