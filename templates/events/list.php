<?php
/**
 * Events list / grid.
 *
 * @var WP_Post[] $events
 * @var string    $layout list|grid
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="mgm-events mgm-events-<?php echo esc_attr( 'grid' === $layout ? 'grid mgm-grid mgm-cols-3' : 'list' ); ?>">
	<?php foreach ( $events as $e ) : ?>
		<?php $s = MGM_Events::ts( $e->ID ); ?>
		<article class="mgm-event-card">
			<div class="mgm-event-date" aria-hidden="true">
				<span class="mgm-event-month"><?php echo esc_html( $s ? wp_date( 'M', $s ) : '' ); ?></span>
				<span class="mgm-event-day"><?php echo esc_html( $s ? wp_date( 'j', $s ) : '' ); ?></span>
			</div>
			<div class="mgm-event-card-body">
				<h3><a href="<?php echo esc_url( get_permalink( $e ) ); ?>"><?php echo esc_html( get_the_title( $e ) ); ?></a></h3>
				<p class="mgm-muted"><?php echo esc_html( MGM_Events::date_range( $e->ID ) ); ?><br><?php echo esc_html( MGM_Events::location_text( $e->ID ) ); ?></p>
				<?php if ( $e->post_excerpt ) : ?><p><?php echo esc_html( wp_trim_words( $e->post_excerpt, 25 ) ); ?></p><?php endif; ?>
				<a class="mgm-button mgm-button-secondary" href="<?php echo esc_url( get_permalink( $e ) ); ?>"><?php echo esc_html( MGM_Events::is_past( $e->ID ) ? __( 'View details', 'mg-membership' ) : __( 'Details & registration', 'mg-membership' ) ); ?></a>
			</div>
		</article>
	<?php endforeach; ?>
	<?php if ( ! $events ) : ?>
		<p><?php esc_html_e( 'No events to show.', 'mg-membership' ); ?></p>
	<?php endif; ?>
</div>
