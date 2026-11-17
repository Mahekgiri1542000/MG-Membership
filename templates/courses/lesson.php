<?php
/**
 * Single lesson layout.
 *
 * @var int      $lesson_id
 * @var int      $course_id
 * @var string   $content
 * @var int|bool $blocker  Previous lesson ID that must be completed first, or false.
 */

defined( 'ABSPATH' ) || exit;

$user_id = get_current_user_id();
$type    = get_post_meta( $lesson_id, '_mgm_lesson_type', true );
$done    = $user_id && MGM_Courses::is_completed( $user_id, $lesson_id );
list( $prev, $next ) = MGM_Courses::adjacent( $lesson_id );
$resources = array_filter( array_map( 'trim', explode( "\n", (string) get_post_meta( $lesson_id, '_mgm_resources', true ) ) ) );
?>
<div class="mgm-lesson-layout">
	<div class="mgm-lesson-main">
		<?php if ( $course_id ) : ?>
			<p class="mgm-breadcrumb"><a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>">&larr; <?php echo esc_html( get_the_title( $course_id ) ); ?></a></p>
		<?php endif; ?>

		<?php if ( $blocker ) : ?>
			<div class="mgm-locked">
				<div class="mgm-locked-message"><?php echo esc_html( sprintf( __( 'Please complete "%s" first.', 'mg-membership' ), get_the_title( $blocker ) ) ); ?></div>
				<a class="mgm-button" href="<?php echo esc_url( get_permalink( $blocker ) ); ?>"><?php esc_html_e( 'Go to lesson', 'mg-membership' ); ?></a>
			</div>
		<?php else : ?>
			<?php echo MGM_Courses::video_embed( get_post_meta( $lesson_id, '_mgm_video_url', true ) ); // phpcs:ignore ?>

			<div class="mgm-lesson-content"><?php echo $content; // phpcs:ignore ?></div>

			<?php if ( $resources ) : ?>
				<div class="mgm-resources"><h4><?php esc_html_e( 'Resources', 'mg-membership' ); ?></h4><ul>
				<?php foreach ( $resources as $r ) : ?>
					<?php $parts = array_map( 'trim', explode( '|', $r, 2 ) ); $url = end( $parts ); $label = count( $parts ) > 1 ? $parts[0] : basename( wp_parse_url( $url, PHP_URL_PATH ) ); ?>
					<li><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $label ); ?></a></li>
				<?php endforeach; ?>
				</ul></div>
			<?php endif; ?>

			<?php if ( 'quiz' === $type ) : ?>
				<?php echo MGM_Quiz::render( $lesson_id ); // phpcs:ignore ?>
			<?php endif; ?>

			<div class="mgm-lesson-footer">
				<div>
					<?php if ( $prev ) : ?><a class="mgm-button mgm-button-secondary" href="<?php echo esc_url( get_permalink( $prev ) ); ?>">&larr; <?php esc_html_e( 'Previous', 'mg-membership' ); ?></a><?php endif; ?>
				</div>
				<div>
					<?php if ( $user_id && 'quiz' !== $type && ! $done ) : ?>
						<form method="post" class="mgm-inline-form">
							<?php wp_nonce_field( 'mgm_course_action', '_mgm_nonce' ); ?>
							<input type="hidden" name="lesson_id" value="<?php echo (int) $lesson_id; ?>">
							<button class="mgm-button" name="mgm_course_action" value="complete"><?php echo esc_html( $next ? __( 'Complete & continue', 'mg-membership' ) : __( 'Complete lesson', 'mg-membership' ) ); ?> &rarr;</button>
						</form>
					<?php elseif ( $next ) : ?>
						<?php if ( $done ) : ?><span class="mgm-status mgm-status-active"><?php esc_html_e( 'Completed', 'mg-membership' ); ?></span><?php endif; ?>
						<a class="mgm-button" href="<?php echo esc_url( get_permalink( $next ) ); ?>"><?php esc_html_e( 'Next', 'mg-membership' ); ?> &rarr;</a>
					<?php elseif ( $done ) : ?>
						<span class="mgm-status mgm-status-active"><?php esc_html_e( 'Completed', 'mg-membership' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php if ( $course_id ) : ?>
		<aside class="mgm-lesson-sidebar">
			<?php if ( $user_id ) : ?>
				<?php $p = MGM_Courses::progress( $user_id, $course_id ); ?>
				<div class="mgm-progress"><div class="mgm-progress-bar" style="width:<?php echo (int) $p; ?>%"></div></div>
				<div class="mgm-progress-label"><?php echo esc_html( sprintf( __( '%d%% complete', 'mg-membership' ), $p ) ); ?></div>
			<?php endif; ?>
			<?php echo MGM_Courses::curriculum_html( $course_id, $lesson_id ); // phpcs:ignore ?>
		</aside>
	<?php endif; ?>
</div>
