<?php
/**
 * Account: profile.
 *
 * Fields, order, labels and widths are set in MG Membership → Forms & Fields → Account profile.
 * Copy to yourtheme/mg-membership/account/profile.php to customise the markup.
 *
 * @var WP_User $user
 */

defined( 'ABSPATH' ) || exit;
?>
<form method="post" class="mgm-form mgm-profile-form">
	<?php wp_nonce_field( 'mgm_profile', '_mgm_nonce' ); ?>
	<input type="hidden" name="mgm_profile" value="1">

	<div class="mgm-box">
		<h3 class="mgm-box-title"><?php echo esc_html( MGM_Forms::setting( 'profile', 'personal_title' ) ); ?></h3>
		<?php MGM_Forms::render( 'profile', array( 'user' => $user ) ); ?>
		<?php do_action( 'mgm_profile_fields', $user ); ?>
	</div>

	<div class="mgm-box">
		<h3 class="mgm-box-title"><?php echo esc_html( MGM_Forms::setting( 'profile', 'security_title' ) ); ?></h3>
		<?php
		MGM_Forms::render(
			'profile',
			array(
				'user'  => $user,
				'group' => 'security',
			)
		);
		?>
	</div>

	<p class="mgm-form-actions"><button type="submit" class="mgm-button"><?php echo esc_html( MGM_Forms::setting( 'profile', 'button' ) ); ?></button></p>
</form>
