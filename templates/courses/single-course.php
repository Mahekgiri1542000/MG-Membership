<?php
/**
 * Single course page (appended around the course content).
 *
 * @var int    $course_id
 * @var string $content
 */

defined( 'ABSPATH' ) || exit;

$user_id  = get_current_user_id();
$lessons  = MGM_Courses::lesson_ids( $course_id );
$level    = MGM_Courses::course_meta( $course_id, 'level' );
$duration = MGM_Courses::course_meta( $course_id, 'duration' );
?>
<div class="mgm-course">
	<div class="mgm-course-meta">
		<span><?php echo esc_html( sprintf( _n( '%d lesson', '%d lessons', count( $lessons ), 'mg-membership' ), count( $lessons ) ) ); ?></span>
		<?php if ( $duration ) : ?><span><?php echo esc_html( $duration ); ?></span><?php endif; ?>
		<?php if ( $level ) : ?><span><?php echo esc_html( ucfirst( $level ) ); ?></span><?php endif; ?>
		<?php $author = get_post_field( 'post_author', $course_id ); ?>
		<span><?php echo esc_html( sprintf( __( 'Instructor: %s', 'mg-membership' ), get_the_author_meta( 'display_name', $author ) ) ); ?></span>
	</div>

	<?php echo mgm_get_template( 'courses/button.php', array( 'course_id' => $course_id ) ); // phpcs:ignore ?>

	<div class="mgm-course-description"><?php echo $content; // phpcs:ignore ?></div>

	<h3 class="mgm-course-heading"><?php esc_html_e( 'Course curriculum', 'mg-membership' ); ?></h3>
	<?php echo MGM_Courses::curriculum_html( $course_id ); // phpcs:ignore ?>

	<?php do_action( 'mgm_after_course', $course_id, $user_id ); ?>
</div>
