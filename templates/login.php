<?php
/**
 * Login & lost password form.
 *
 * Texts, labels and options: MG Membership → Forms & Fields → Login. Colours and fonts: Settings → Appearance.
 * Copy to yourtheme/mg-membership/login.php to customise the markup.
 *
 * @var string $redirect
 * @var string $mode login|lost
 */

defined( 'ABSPATH' ) || exit;

$lost = 'lost' === $mode;
$s    = function ( $key ) {
	return MGM_Forms::setting( 'login', $key );
};
?>
<div class="mgm-login mgm-auth">
	<div class="mgm-auth-card">
		<div class="mgm-auth-head">
			<span class="mgm-auth-icon"><?php echo MGM_Account::icon( $lost ? 'privacy' : 'profile' ); // phpcs:ignore ?></span>
			<?php $title = $s( $lost ? 'lost_title' : 'title' ); ?>
			<?php if ( '' !== $title ) : ?>
				<h2 class="mgm-auth-title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<?php $intro = $s( $lost ? 'lost_intro' : 'intro' ); ?>
			<?php if ( '' !== $intro ) : ?>
				<p class="mgm-auth-intro"><?php echo esc_html( $intro ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $lost ) : ?>
			<form method="post" class="mgm-form">
				<?php wp_nonce_field( 'mgm_lost', '_mgm_nonce' ); ?>
				<input type="hidden" name="mgm_lost_password" value="1">
				<?php MGM_Forms::render( 'login', array( 'group' => 'lost' ) ); ?>
				<p><button type="submit" class="mgm-button mgm-button-block"><?php echo esc_html( $s( 'lost_button' ) ); ?></button></p>
			</form>
			<p class="mgm-auth-foot"><a href="<?php echo esc_url( remove_query_arg( 'mgm_lost' ) ); ?>">&larr; <?php echo esc_html( $s( 'back_text' ) ); ?></a></p>
		<?php else : ?>
			<form method="post" class="mgm-form">
				<?php wp_nonce_field( 'mgm_login', '_mgm_nonce' ); ?>
				<input type="hidden" name="mgm_login" value="1">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>">
				<?php MGM_Forms::render( 'login' ); ?>
				<?php if ( MGM_Forms::on( 'login', 'remember' ) || MGM_Forms::on( 'login', 'lost_link' ) ) : ?>
					<p class="mgm-inline mgm-space-between mgm-auth-row">
						<?php if ( MGM_Forms::on( 'login', 'remember' ) ) : ?>
							<label class="mgm-check"><input type="checkbox" name="rememberme" value="1"> <?php esc_html_e( 'Remember me', 'mg-membership' ); ?></label>
						<?php else : ?>
							<span></span>
						<?php endif; ?>
						<?php if ( MGM_Forms::on( 'login', 'lost_link' ) ) : ?>
							<a class="mgm-small" href="<?php echo esc_url( add_query_arg( 'mgm_lost', 1 ) ); ?>"><?php echo esc_html( $s( 'lost_text' ) ); ?></a>
						<?php endif; ?>
					</p>
				<?php endif; ?>
				<?php do_action( 'mgm_login_form' ); ?>
				<p><button type="submit" class="mgm-button mgm-button-block"><?php echo esc_html( $s( 'button' ) ); ?></button></p>
			</form>
			<?php do_action( 'mgm_login_form_after', $redirect ); ?>
			<?php if ( MGM_Forms::on( 'login', 'register' ) ) : ?>
				<?php $url = $s( 'register_url' ) ? $s( 'register_url' ) : mgm_page_url( 'pricing' ); ?>
				<?php if ( $url ) : ?>
					<p class="mgm-auth-foot"><?php echo esc_html( $s( 'register_text' ) ); ?> <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $s( 'register_link' ) ); ?></a></p>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
