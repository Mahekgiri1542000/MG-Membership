<?php
/**
 * Pricing table.
 *
 * @var MGM_Membership[] $memberships
 * @var int              $columns
 * @var string           $style
 * @var string           $button_text
 */

defined( 'ABSPATH' ) || exit;

$active = MGM_Member::active_membership_ids( get_current_user_id() );
?>
<div class="mgm-pricing mgm-pricing-<?php echo esc_attr( $style ); ?> mgm-cols-<?php echo (int) $columns; ?>">
	<?php foreach ( $memberships as $m ) : ?>
		<?php $is_current = in_array( $m->id, $active, true ); ?>
		<div class="mgm-plan <?php echo $m->get( 'highlight' ) ? 'is-highlight' : ''; ?> <?php echo $is_current ? 'is-current' : ''; ?>">
			<?php if ( $m->get( 'badge' ) ) : ?><span class="mgm-plan-badge"><?php echo esc_html( $m->get( 'badge' ) ); ?></span><?php endif; ?>
			<?php if ( has_post_thumbnail( $m->id ) ) : ?><div class="mgm-plan-image"><?php echo get_the_post_thumbnail( $m->id, 'medium' ); ?></div><?php endif; ?>
			<h3 class="mgm-plan-title"><?php echo esc_html( $m->title() ); ?></h3>
			<div class="mgm-plan-price">
				<span class="mgm-plan-amount"><?php echo esc_html( $m->price() > 0 ? mgm_price( $m->price() ) : __( 'Free', 'mg-membership' ) ); ?></span>
				<?php if ( $m->is_recurring() ) : ?>
					<span class="mgm-plan-period">/ <?php echo esc_html( ( (int) $m->get( 'period' ) > 1 ? (int) $m->get( 'period' ) . ' ' : '' ) . $m->get( 'period_unit' ) ); ?></span>
				<?php endif; ?>
			</div>
			<div class="mgm-plan-terms"><?php echo esc_html( $m->price_terms() ); ?></div>
			<?php if ( $m->post->post_excerpt ) : ?><div class="mgm-plan-desc"><?php echo wp_kses_post( wpautop( $m->post->post_excerpt ) ); ?></div><?php endif; ?>
			<?php if ( $m->features() ) : ?>
				<ul class="mgm-plan-features">
					<?php foreach ( $m->features() as $f ) : ?><li><?php echo esc_html( $f ); ?></li><?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<div class="mgm-plan-cta">
				<?php if ( $is_current ) : ?>
					<span class="mgm-button mgm-button-disabled"><?php esc_html_e( 'Your current plan', 'mg-membership' ); ?></span>
				<?php else : ?>
					<a class="mgm-button mgm-button-block" href="<?php echo esc_url( $m->url() ); ?>"><?php echo esc_html( $button_text ? $button_text : $m->get( 'signup_button_text' ) ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
	<?php if ( ! $memberships ) : ?>
		<p><?php esc_html_e( 'No memberships available yet.', 'mg-membership' ); ?></p>
	<?php endif; ?>
</div>
