<?php
/**
 * Event speakers & agenda.
 *
 * @var int $event_id
 */

defined( 'ABSPATH' ) || exit;

$lines    = function ( $key ) use ( $event_id ) {
	return array_filter( array_map( 'trim', explode( "\n", (string) MGM_Events::meta( $event_id, $key ) ) ) );
};
$speakers = $lines( 'speakers' );
$agenda   = $lines( 'agenda' );
?>
<?php if ( $speakers ) : ?>
	<h3><?php esc_html_e( 'Speakers', 'mg-membership' ); ?></h3>
	<div class="mgm-speakers">
		<?php foreach ( $speakers as $s ) : ?>
			<?php $p = array_map( 'trim', explode( '|', $s, 2 ) ); ?>
			<div class="mgm-speaker"><span class="mgm-avatar"><?php echo esc_html( mb_substr( $p[0], 0, 1 ) ); ?></span><div><strong><?php echo esc_html( $p[0] ); ?></strong><?php if ( ! empty( $p[1] ) ) : ?><br><small><?php echo esc_html( $p[1] ); ?></small><?php endif; ?></div></div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<?php if ( $agenda ) : ?>
	<h3><?php esc_html_e( 'Agenda', 'mg-membership' ); ?></h3>
	<ul class="mgm-agenda">
		<?php foreach ( $agenda as $a ) : ?>
			<?php $p = array_map( 'trim', explode( '|', $a, 2 ) ); ?>
			<li><?php if ( count( $p ) > 1 ) : ?><time><?php echo esc_html( $p[0] ); ?></time><span><?php echo esc_html( $p[1] ); ?></span><?php else : ?><span><?php echo esc_html( $p[0] ); ?></span><?php endif; ?></li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
