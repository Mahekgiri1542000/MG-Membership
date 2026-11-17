<?php
/**
 * Unauthorized (locked content) message.
 *
 * @var string $message
 * @var int    $post_id
 * @var int[]  $memberships
 * @var bool   $show_login
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="mgm-locked">
	<div class="mgm-locked-icon" aria-hidden="true">
		<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
	</div>
	<div class="mgm-locked-message"><?php echo wp_kses_post( wpautop( $message ) ); ?></div>
	<div class="mgm-locked-actions">
		<?php if ( count( $memberships ) === 1 ) : ?>
			<?php $m = new MGM_Membership( $memberships[0] ); ?>
			<?php if ( $m->exists() ) : ?>
				<a class="mgm-button" href="<?php echo esc_url( $m->url() ); ?>"><?php echo esc_html( sprintf( __( 'Join %s', 'mg-membership' ), $m->title() ) ); ?></a>
			<?php endif; ?>
		<?php else : ?>
			<a class="mgm-button" href="<?php echo esc_url( mgm_page_url( 'pricing' ) ); ?>"><?php esc_html_e( 'View membership plans', 'mg-membership' ); ?></a>
		<?php endif; ?>
		<?php if ( ! is_user_logged_in() && ! $show_login ) : ?>
			<a class="mgm-button mgm-button-secondary" href="<?php echo esc_url( add_query_arg( 'mgm_redirect', rawurlencode( get_permalink( $post_id ) ? get_permalink( $post_id ) : home_url( add_query_arg( array() ) ) ), mgm_page_url( 'login' ) ) ); ?>"><?php esc_html_e( 'Log in', 'mg-membership' ); ?></a>
		<?php endif; ?>
	</div>
	<?php if ( $show_login ) : ?>
		<?php echo MGM_Account::render_login( $post_id ? get_permalink( $post_id ) : '' ); // phpcs:ignore ?>
	<?php endif; ?>
</div>
