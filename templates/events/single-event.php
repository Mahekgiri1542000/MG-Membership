<?php
/**
 * Single event layout.
 *
 * @var int    $event_id
 * @var string $content
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="mgm-event">
	<?php echo MGM_Events::sc_countdown( array( 'id' => $event_id ) ); // phpcs:ignore ?>
	<div class="mgm-event-layout">
		<div class="mgm-event-main">
			<div class="mgm-event-description"><?php echo $content; // phpcs:ignore ?></div>
			<?php echo mgm_get_template( 'events/program.php', array( 'event_id' => $event_id ) ); // phpcs:ignore ?>
		</div>
		<aside class="mgm-event-sidebar">
			<?php echo mgm_get_template( 'events/details.php', array( 'event_id' => $event_id ) ); // phpcs:ignore ?>
			<?php echo mgm_get_template( 'events/register.php', array( 'event_id' => $event_id ) ); // phpcs:ignore ?>
		</aside>
	</div>
</div>
