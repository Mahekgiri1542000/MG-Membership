<?php
/**
 * Enrolled courses list.
 *
 * @var int $user_id
 * @var int $limit (optional)
 */

defined( 'ABSPATH' ) || exit;

$courses = MGM_Courses::enrolled_courses( $user_id );
if ( ! empty( $limit ) ) {
	$courses = array_slice( $courses, 0, (int) $limit );
}
?>
<div class="mgm-my-courses">
	<?php foreach ( $courses as $cid ) : ?>
		<?php $p = MGM_Courses::progress( $user_id, $cid ); $next = MGM_Courses::next_lesson( $user_id, $cid ); ?>
		<div class="mgm-my-course">
			<div class="mgm-my-course-info">
				<strong><a href="<?php echo esc_url( get_permalink( $cid ) ); ?>"><?php echo esc_html( get_the_title( $cid ) ); ?></a></strong>
				<div class="mgm-progress"><div class="mgm-progress-bar" style="width:<?php echo (int) $p; ?>%"></div></div>
				<small><?php echo esc_html( sprintf( __( '%d%% complete', 'mg-membership' ), $p ) ); ?></small>
			</div>
			<div class="mgm-my-course-actions">
				<?php if ( $p < 100 && $next ) : ?>
					<a class="mgm-button" href="<?php echo esc_url( get_permalink( $next ) ); ?>"><?php esc_html_e( 'Continue', 'mg-membership' ); ?></a>
				<?php endif; ?>
				<?php do_action( 'mgm_my_course_actions', $cid, $user_id, $p ); ?>
			</div>
		</div>
	<?php endforeach; ?>
	<?php if ( ! $courses ) : ?>
		<?php echo MGM_Account::empty_state( __( 'No courses yet', 'mg-membership' ), __( 'You are not enrolled in any course yet.', 'mg-membership' ), __( 'Browse courses', 'mg-membership' ), mgm_page_url( 'courses' ), 'courses' ); // phpcs:ignore ?>
	<?php endif; ?>
</div>
