<?php
/**
 * Login & lost password form.
 *
 * @var string $redirect
 * @var string $mode login|lost
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="mgm-login">
	<?php if ( 'lost' === $mode ) : ?>
		<form method="post" class="mgm-form">
			<?php wp_nonce_field( 'mgm_lost', '_mgm_nonce' ); ?>
			<input type="hidden" name="mgm_lost_password" value="1">
			<p><label><?php esc_html_e( 'Username or email', 'mg-membership' ); ?></label><input type="text" name="user_login" required></p>
			<p><button type="submit" class="mgm-button mgm-button-block"><?php esc_html_e( 'Email me a reset link', 'mg-membership' ); ?></button></p>
			<p class="mgm-small"><a href="<?php echo esc_url( remove_query_arg( 'mgm_lost' ) ); ?>"><?php esc_html_e( 'Back to login', 'mg-membership' ); ?></a></p>
		</form>
	<?php else : ?>
		<form method="post" class="mgm-form">
			<?php wp_nonce_field( 'mgm_login', '_mgm_nonce' ); ?>
			<input type="hidden" name="mgm_login" value="1">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>">
			<p><label><?php esc_html_e( 'Username or email', 'mg-membership' ); ?></label><input type="text" name="log" required autocomplete="username"></p>
			<p><label><?php esc_html_e( 'Password', 'mg-membership' ); ?></label><input type="password" name="pwd" required autocomplete="current-password"></p>
			<p class="mgm-inline mgm-space-between">
				<label class="mgm-check"><input type="checkbox" name="rememberme" value="1"> <?php esc_html_e( 'Remember me', 'mg-membership' ); ?></label>
				<a class="mgm-small" href="<?php echo esc_url( add_query_arg( 'mgm_lost', 1 ) ); ?>"><?php esc_html_e( 'Forgot password?', 'mg-membership' ); ?></a>
			</p>
			<?php do_action( 'mgm_login_form' ); ?>
			<p><button type="submit" class="mgm-button mgm-button-block"><?php esc_html_e( 'Log in', 'mg-membership' ); ?></button></p>
		</form>
		<?php do_action( 'mgm_login_form_after', $redirect ); ?>
	<?php endif; ?>
</div>
