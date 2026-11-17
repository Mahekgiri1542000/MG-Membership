<?php
/**
 * Course start / continue / buy button with progress.
 *
 * @var int $course_id
 */

defined( 'ABSPATH' ) || exit;

$user_id = get_current_user_id();
$access  = MGM_Courses::can_access_course( $course_id );
$prog    = $user_id ? MGM_Courses::progress( $user_id, $course_id ) : 0;
$started = $user_id && get_user_meta( $user_id, '_mgm_enrolled_' . $course_id, true );
?>
<div class="mgm-course-cta">
	<?php if ( $access && $user_id ) : ?>
		<?php if ( $started ) : ?>
			<div class="mgm-progress"><div class="mgm-progress-bar" style="width:<?php echo (int) $prog; ?>%"></div></div>
			<div class="mgm-progress-label"><?php echo esc_html( sprintf( __( '%d%% complete', 'mg-membership' ), $prog ) ); ?></div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'mgm_course_action', '_mgm_nonce' ); ?>
			<input type="hidden" name="course_id" value="<?php echo (int) $course_id; ?>">
			<button class="mgm-button" name="mgm_course_action" value="start"><?php echo esc_html( 100 === $prog ? __( 'Review course', 'mg-membership' ) : ( $started ? __( 'Continue course', 'mg-membership' ) : __( 'Start course', 'mg-membership' ) ) ); ?></button>
			<?php if ( $started && MGM_Courses::course_meta( $course_id, 'allow_reset' ) ) : ?>
				<button class="mgm-button mgm-button-secondary" name="mgm_course_action" value="reset" onclick="return confirm('<?php echo esc_js( __( 'Reset your progress?', 'mg-membership' ) ); ?>')"><?php esc_html_e( 'Reset progress', 'mg-membership' ); ?></button>
			<?php endif; ?>
		</form>
		<?php do_action( 'mgm_course_cta_after', $course_id, $user_id, $prog ); ?>
	<?php elseif ( $access ) : ?>
		<a class="mgm-button" href="<?php echo esc_url( add_query_arg( 'mgm_redirect', rawurlencode( get_permalink( $course_id ) ), mgm_page_url( 'login' ) ) ); ?>"><?php esc_html_e( 'Log in to start', 'mg-membership' ); ?></a>
	<?php else : ?>
		<?php $ms = MGM_Courses::course_memberships( $course_id ); ?>
		<?php if ( 1 === count( $ms ) ) : ?>
			<?php $m = new MGM_Membership( $ms[0] ); ?>
			<a class="mgm-button" href="<?php echo esc_url( $m->url() ); ?>"><?php echo esc_html( sprintf( __( 'Enroll – %s', 'mg-membership' ), $m->price_terms() ) ); ?></a>
		<?php else : ?>
			<a class="mgm-button" href="<?php echo esc_url( mgm_page_url( 'pricing' ) ); ?>"><?php esc_html_e( 'Join to enroll', 'mg-membership' ); ?></a>
		<?php endif; ?>
		<?php if ( ! $user_id ) : ?>
			<a class="mgm-button mgm-button-secondary" href="<?php echo esc_url( add_query_arg( 'mgm_redirect', rawurlencode( get_permalink( $course_id ) ), mgm_page_url( 'login' ) ) ); ?>"><?php esc_html_e( 'Log in', 'mg-membership' ); ?></a>
		<?php endif; ?>
	<?php endif; ?>
</div>
