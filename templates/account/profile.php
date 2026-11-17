<?php
/**
 * Account: profile.
 *
 * @var WP_User $user
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'Profile', 'mg-membership' ); ?></h2>
<form method="post" class="mgm-form">
	<?php wp_nonce_field( 'mgm_profile', '_mgm_nonce' ); ?>
	<input type="hidden" name="mgm_profile" value="1">
	<div class="mgm-row">
		<p class="mgm-col"><label><?php esc_html_e( 'First name', 'mg-membership' ); ?></label><input type="text" name="first_name" value="<?php echo esc_attr( $user->first_name ); ?>"></p>
		<p class="mgm-col"><label><?php esc_html_e( 'Last name', 'mg-membership' ); ?></label><input type="text" name="last_name" value="<?php echo esc_attr( $user->last_name ); ?>"></p>
	</div>
	<p><label><?php esc_html_e( 'Display name', 'mg-membership' ); ?></label><input type="text" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>"></p>
	<p><label><?php esc_html_e( 'Email', 'mg-membership' ); ?></label><input type="email" name="user_email" value="<?php echo esc_attr( $user->user_email ); ?>"></p>
	<p><label><?php esc_html_e( 'New password (leave blank to keep current)', 'mg-membership' ); ?></label><input type="password" name="new_pass" autocomplete="new-password" minlength="8"></p>
	<p><label><?php esc_html_e( 'Current password (needed only to change your email or password)', 'mg-membership' ); ?></label><input type="password" name="current_pass" autocomplete="current-password"></p>
	<?php do_action( 'mgm_profile_fields', $user ); ?>
	<p><button type="submit" class="mgm-button"><?php esc_html_e( 'Save changes', 'mg-membership' ); ?></button></p>
</form>
