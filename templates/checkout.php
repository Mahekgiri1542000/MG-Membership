<?php
/**
 * Checkout / registration form.
 * Override: yourtheme/mg-membership/checkout.php
 *
 * @var MGM_Membership $membership
 * @var true|WP_Error  $can
 * @var MGM_Gateway[]  $gateways
 * @var string         $notices
 * @var WP_User        $user
 */

defined( 'ABSPATH' ) || exit;

$price     = $membership->price();
$tax       = MGM_Checkout::tax_for( $price );
$logged_in = $user && $user->ID;
?>
<div class="mgm-checkout" data-membership="<?php echo (int) $membership->id; ?>">
	<?php echo $notices; // phpcs:ignore ?>

	<?php if ( is_wp_error( $can ) ) : ?>
		<div class="mgm-notice mgm-notice-info"><?php echo esc_html( $can->get_error_message() ); ?></div>
		<?php if ( $logged_in ) : ?>
			<p><a class="mgm-button" href="<?php echo esc_url( mgm_page_url( 'account' ) ); ?>"><?php esc_html_e( 'Go to my account', 'mg-membership' ); ?></a></p>
		<?php endif; ?>
		<?php return; ?>
	<?php endif; ?>

	<div class="mgm-checkout-summary">
		<div class="mgm-checkout-title"><?php echo esc_html( $membership->title() ); ?></div>
		<div class="mgm-checkout-terms" data-mgm-terms><?php echo esc_html( $membership->price_terms() ); ?></div>
		<?php if ( $tax['tax'] > 0 ) : ?>
			<div class="mgm-checkout-tax"><?php printf( esc_html__( 'Tax: %s', 'mg-membership' ), '<span data-mgm-tax>' . esc_html( mgm_price( $tax['tax'] ) ) . '</span>' ); ?></div>
		<?php endif; ?>
		<div class="mgm-checkout-total"><?php esc_html_e( 'Total due today:', 'mg-membership' ); ?>
			<strong data-mgm-total><?php echo esc_html( mgm_price( $membership->has_trial() ? MGM_Checkout::tax_for( (float) $membership->get( 'trial_amount' ) )['total'] : $tax['total'] ) ); ?></strong>
		</div>
	</div>

	<form method="post" class="mgm-form" novalidate>
		<?php wp_nonce_field( 'mgm_checkout', '_mgm_nonce' ); ?>
		<input type="hidden" name="mgm_checkout" value="1">
		<input type="hidden" name="mgm_membership_id" value="<?php echo (int) $membership->id; ?>">
		<div class="mgm-hp" aria-hidden="true"><input type="text" name="mgm_website" tabindex="-1" autocomplete="off"></div>

		<?php
		// Fields, order, labels and widths: MG Membership → Forms & Fields → Registration.
		MGM_Forms::render(
			'checkout',
			array(
				'user'       => $logged_in ? $user : null,
				'membership' => $membership,
			)
		);
		?>

		<?php if ( ! $logged_in ) : ?>
			<?php if ( MGM_Forms::on( 'checkout', 'login_link' ) ) : ?>
				<p class="mgm-small"><?php printf( wp_kses_post( __( 'Already have an account? <a href="%s">Log in</a>', 'mg-membership' ) ), esc_url( add_query_arg( 'mgm_redirect', rawurlencode( $membership->url() ), mgm_page_url( 'login' ) ) ) ); ?></p>
			<?php endif; ?>
		<?php else : ?>
			<p class="mgm-small"><?php printf( esc_html__( 'Signed in as %s', 'mg-membership' ), '<strong>' . esc_html( $user->user_email ) . '</strong>' ); ?></p>
		<?php endif; ?>

		<?php do_action( 'mgm_checkout_fields', $membership ); ?>

		<?php if ( $price > 0 && MGM_Addons::is_active( 'coupons' ) ) : ?>
			<div class="mgm-coupon">
				<label><?php esc_html_e( 'Coupon code', 'mg-membership' ); ?></label>
				<div class="mgm-inline">
					<input type="text" name="mgm_coupon" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_POST['mgm_coupon'] ?? ( $_GET['coupon'] ?? '' ) ) ) ); // phpcs:ignore ?>">
					<button type="button" class="mgm-button mgm-button-secondary" data-mgm-apply-coupon><?php esc_html_e( 'Apply', 'mg-membership' ); ?></button>
				</div>
				<div class="mgm-coupon-msg" data-mgm-coupon-msg></div>
			</div>
		<?php endif; ?>

		<?php if ( $price > 0 ) : ?>
			<div class="mgm-gateways" data-mgm-gateways>
				<label><?php esc_html_e( 'Payment method', 'mg-membership' ); ?></label>
				<?php if ( ! $gateways ) : ?>
					<div class="mgm-notice mgm-notice-error"><?php esc_html_e( 'No payment methods are enabled yet.', 'mg-membership' ); ?></div>
				<?php endif; ?>
				<?php $first = true; foreach ( $gateways as $gid => $gw ) : ?>
					<?php if ( $membership->is_recurring() && ! $gw->supports_recurring ) { continue; } ?>
					<label class="mgm-gateway">
						<input type="radio" name="mgm_gateway" value="<?php echo esc_attr( $gid ); ?>" <?php checked( $first ); ?>>
						<span><strong><?php echo esc_html( $gw->label() ); ?></strong>
						<?php if ( $gw->description() ) : ?><small><?php echo esc_html( $gw->description() ); ?></small><?php endif; ?></span>
					</label>
				<?php $first = false; endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( mgm_option( 'require_terms' ) ) : ?>
			<p><label class="mgm-check"><input type="checkbox" name="mgm_terms" value="1" required>
				<?php printf( wp_kses_post( __( 'I agree to the <a href="%s" target="_blank">terms & conditions</a>', 'mg-membership' ) ), esc_url( mgm_option( 'terms_url', '#' ) ) ); ?></label></p>
		<?php endif; ?>

		<p><button type="submit" class="mgm-button mgm-button-block"><?php echo esc_html( $membership->get( 'signup_button_text' ) ); ?></button></p>
	</form>
</div>
