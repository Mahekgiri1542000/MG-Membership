<?php
/**
 * Event registration box.
 *
 * @var int $event_id
 */

defined( 'ABSPATH' ) || exit;

$user_id = get_current_user_id();
$reg     = $user_id ? MGM_Events::registration( $event_id, $user_id ) : null;
$can     = MGM_Events::can_register( $event_id, $user_id );
$past    = MGM_Events::is_past( $event_id );
?>
<div class="mgm-event-register">
	<?php echo mgm_print_notices(); // phpcs:ignore ?>

	<?php if ( $reg ) : ?>
		<div class="mgm-ticket">
			<strong><?php echo 'waitlist' === $reg->status ? esc_html__( 'You are on the waitlist', 'mg-membership' ) : esc_html__( 'You are registered ✓', 'mg-membership' ); ?></strong>
			<div class="mgm-ticket-code"><?php esc_html_e( 'Ticket', 'mg-membership' ); ?>: <code><?php echo esc_html( $reg->ticket_code ); ?></code></div>
			<?php if ( 'registered' === $reg->status ) : ?>
				<?php $url = MGM_Events::meta( $event_id, 'online_url' ); $rec = MGM_Events::meta( $event_id, 'recording_url' ); ?>
				<?php if ( $past && $rec ) : ?>
					<a class="mgm-button mgm-button-block" href="<?php echo esc_url( $rec ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Watch recording', 'mg-membership' ); ?></a>
				<?php elseif ( ! $past && $url ) : ?>
					<a class="mgm-button mgm-button-block" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Join online', 'mg-membership' ); ?></a>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( ! $past ) : ?>
				<form method="post">
					<?php wp_nonce_field( 'mgm_event', '_mgm_nonce' ); ?>
					<input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>">
					<button class="mgm-link mgm-link-danger" name="mgm_event_action" value="cancel" onclick="return confirm('<?php echo esc_js( __( 'Cancel your registration?', 'mg-membership' ) ); ?>')"><?php esc_html_e( 'Cancel registration', 'mg-membership' ); ?></button>
				</form>
			<?php endif; ?>
		</div>

	<?php elseif ( is_wp_error( $can ) ) : ?>
		<?php $code = $can->get_error_code(); ?>
		<p class="mgm-muted"><?php echo esc_html( $can->get_error_message() ); ?></p>
		<?php if ( 'mgm_event_ticket' === $code ) : ?>
			<?php $m = new MGM_Membership( (int) MGM_Events::meta( $event_id, 'ticket_membership' ) ); ?>
			<?php if ( $m->exists() ) : ?>
				<a class="mgm-button mgm-button-block" href="<?php echo esc_url( $m->url() ); ?>"><?php echo esc_html( sprintf( __( 'Buy ticket – %s', 'mg-membership' ), $m->price_terms() ) ); ?></a>
			<?php endif; ?>
		<?php elseif ( 'mgm_event_members' === $code ) : ?>
			<a class="mgm-button mgm-button-block" href="<?php echo esc_url( mgm_page_url( 'pricing' ) ); ?>"><?php esc_html_e( 'Become a member', 'mg-membership' ); ?></a>
		<?php endif; ?>
		<?php if ( ! $user_id && in_array( $code, array( 'mgm_event_login', 'mgm_event_members', 'mgm_event_ticket' ), true ) ) : ?>
			<a class="mgm-button mgm-button-secondary mgm-button-block" href="<?php echo esc_url( add_query_arg( 'mgm_redirect', rawurlencode( get_permalink( $event_id ) ), mgm_page_url( 'login' ) ) ); ?>"><?php esc_html_e( 'Log in', 'mg-membership' ); ?></a>
		<?php endif; ?>

	<?php else : ?>
		<form method="post" class="mgm-form">
			<?php wp_nonce_field( 'mgm_event', '_mgm_nonce' ); ?>
			<input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>">
			<div class="mgm-hp" aria-hidden="true"><input type="text" name="mgm_website" tabindex="-1" autocomplete="off"></div>
			<?php if ( ! $user_id ) : ?>
				<p><label><?php esc_html_e( 'Name', 'mg-membership' ); ?></label><input type="text" name="name" required></p>
				<p><label><?php esc_html_e( 'Email', 'mg-membership' ); ?></label><input type="email" name="email" required></p>
				<p><label><?php esc_html_e( 'Phone (optional)', 'mg-membership' ); ?></label><input type="tel" name="phone"></p>
			<?php endif; ?>
			<button class="mgm-button mgm-button-block" name="mgm_event_action" value="register">
				<?php echo 0 === MGM_Events::seats_left( $event_id ) ? esc_html__( 'Join waitlist', 'mg-membership' ) : esc_html__( 'Register now', 'mg-membership' ); ?>
			</button>
		</form>
	<?php endif; ?>
</div>
