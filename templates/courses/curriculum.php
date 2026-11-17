<?php
/**
 * Course curriculum (sections + lessons).
 *
 * @var int $course_id
 * @var int $current Current lesson ID.
 */

defined( 'ABSPATH' ) || exit;

$user_id = get_current_user_id();
$done    = $user_id ? MGM_Courses::completed_lessons( $user_id, $course_id ) : array();
$access  = MGM_Courses::can_access_course( $course_id );
$num     = 0;
?>
<div class="mgm-curriculum">
	<?php foreach ( MGM_Courses::curriculum( $course_id ) as $section ) : ?>
		<?php if ( ! $section['lessons'] ) { continue; } ?>
		<details class="mgm-section" <?php echo ( ! $current || in_array( $current, $section['lessons'], true ) ) ? 'open' : ''; ?>>
			<summary><span><?php echo esc_html( $section['title'] ); ?></span><small><?php echo esc_html( sprintf( _n( '%d lesson', '%d lessons', count( $section['lessons'] ), 'mg-membership' ), count( $section['lessons'] ) ) ); ?></small></summary>
			<ol class="mgm-lessons">
				<?php foreach ( $section['lessons'] as $lid ) : ?>
					<?php
					++$num;
					$type     = get_post_meta( $lid, '_mgm_lesson_type', true );
					$preview  = get_post_meta( $lid, '_mgm_preview', true );
					$is_done  = in_array( $lid, $done, true );
					$viewable = $access || $preview;
					$classes  = array( 'mgm-lesson-item', 'mgm-type-' . ( $type ? $type : 'text' ) );
					if ( $is_done ) {
						$classes[] = 'is-done';
					}
					if ( (int) $current === $lid ) {
						$classes[] = 'is-current';
					}
					if ( ! $viewable ) {
						$classes[] = 'is-locked';
					}
					?>
					<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
						<span class="mgm-lesson-num"><?php echo $is_done ? '✓' : (int) $num; ?></span>
						<?php if ( $viewable ) : ?>
							<a href="<?php echo esc_url( get_permalink( $lid ) ); ?>"><?php echo esc_html( get_the_title( $lid ) ); ?></a>
						<?php else : ?>
							<span><?php echo esc_html( get_the_title( $lid ) ); ?></span>
						<?php endif; ?>
						<span class="mgm-lesson-tags">
							<?php if ( 'quiz' === $type ) : ?><em><?php esc_html_e( 'Quiz', 'mg-membership' ); ?></em><?php endif; ?>
							<?php if ( $preview && ! $access ) : ?><em class="mgm-tag-preview"><?php esc_html_e( 'Preview', 'mg-membership' ); ?></em><?php endif; ?>
							<?php $dur = get_post_meta( $lid, '_mgm_duration', true ); ?>
							<?php if ( $dur ) : ?><small><?php echo esc_html( $dur ); ?></small><?php endif; ?>
							<?php if ( ! $viewable ) : ?><span class="mgm-lock" aria-label="<?php esc_attr_e( 'Locked', 'mg-membership' ); ?>">🔒</span><?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
		</details>
	<?php endforeach; ?>
	<?php if ( ! $num ) : ?>
		<p class="mgm-muted"><?php esc_html_e( 'Lessons coming soon.', 'mg-membership' ); ?></p>
	<?php endif; ?>
</div>
