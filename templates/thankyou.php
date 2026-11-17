<?php
/**
 * Thank-you / receipt page.
 *
 * @var object|null $txn
 */

defined( 'ABSPATH' ) || exit;

if ( ! $txn ) : ?>
	<div class="mgm-thankyou"><h2><?php esc_html_e( 'Thank you!', 'mg-membership' ); ?></h2>
	<p><a class="mgm-button" href="<?php echo esc_url( mgm_page_url( 'account' ) ); ?>"><?php esc_html_e( 'Go to my account', 'mg-membership' ); ?></a></p></div>
	<?php
	return;
endif;

$m       = new MGM_Membership( $txn->membership_id );
$offline = mgm()->gateway( 'offline' );
?>
<div class="mgm-thankyou">
	<?php if ( 'complete' === $txn->status && apply_filters( 'mgm_txn_awaiting_approval', false, $txn ) ) : ?>
		<h2><?php esc_html_e( 'Thanks – your application is in!', 'mg-membership' ); ?></h2>
		<p><?php echo esc_html( sprintf( __( 'Your %s membership is awaiting approval. We will email you as soon as it is approved.', 'mg-membership' ), $m->title() ) ); ?></p>
	<?php elseif ( 'complete' === $txn->status ) : ?>
		<h2><?php esc_html_e( 'Welcome aboard! 🎉', 'mg-membership' ); ?></h2>
		<p><?php echo esc_html( sprintf( __( 'Your %s membership is now active.', 'mg-membership' ), $m->title() ) ); ?></p>
	<?php else : ?>
		<h2><?php esc_html_e( 'Thank you for your order', 'mg-membership' ); ?></h2>
		<p><?php esc_html_e( 'Your payment is being processed. Access will be granted as soon as payment is confirmed.', 'mg-membership' ); ?></p>
		<?php if ( 'offline' === $txn->gateway && $offline && $offline->opt( 'instructions' ) ) : ?>
			<div class="mgm-instructions"><?php echo wp_kses_post( wpautop( $offline->opt( 'instructions' ) ) ); ?></div>
		<?php endif; ?>
	<?php endif; ?>

	<?php do_action( 'mgm_thankyou_notices', $txn ); ?>

	<?php if ( $m->get( 'thankyou_message' ) ) : ?>
		<div class="mgm-thankyou-message"><?php echo wp_kses_post( wpautop( $m->get( 'thankyou_message' ) ) ); ?></div>
	<?php endif; ?>

	<table class="mgm-receipt">
		<tr><th><?php esc_html_e( 'Invoice #', 'mg-membership' ); ?></th><td><?php echo esc_html( $txn->trans_num ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Membership', 'mg-membership' ); ?></th><td><?php echo esc_html( $m->title() ); ?></td></tr>
		<?php if ( (float) $txn->tax_amount > 0 ) : ?>
			<tr><th><?php esc_html_e( 'Subtotal', 'mg-membership' ); ?></th><td><?php echo esc_html( mgm_price( $txn->amount ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Tax', 'mg-membership' ); ?></th><td><?php echo esc_html( mgm_price( $txn->tax_amount ) ); ?></td></tr>
		<?php endif; ?>
		<tr><th><?php esc_html_e( 'Total', 'mg-membership' ); ?></th><td><strong><?php echo esc_html( mgm_price( $txn->total ) ); ?></strong></td></tr>
		<tr><th><?php esc_html_e( 'Date', 'mg-membership' ); ?></th><td><?php echo esc_html( mgm_date( $txn->created_at ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Status', 'mg-membership' ); ?></th><td><span class="mgm-status mgm-status-<?php echo esc_attr( $txn->status ); ?>"><?php echo esc_html( ucfirst( $txn->status ) ); ?></span></td></tr>
	</table>
	<p class="mgm-no-print">
		<a class="mgm-button" href="<?php echo esc_url( mgm_page_url( 'account' ) ); ?>"><?php esc_html_e( 'Go to my account', 'mg-membership' ); ?></a>
		<button type="button" class="mgm-button mgm-button-secondary" onclick="window.print()"><?php esc_html_e( 'Print receipt', 'mg-membership' ); ?></button>
	</p>
</div>
