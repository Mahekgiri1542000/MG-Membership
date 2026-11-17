<?php
/**
 * Courses grid.
 *
 * @var WP_Post[] $courses
 * @var int       $columns
 */

defined( 'ABSPATH' ) || exit;

$user_id = get_current_user_id();
?>
<div class="mgm-grid mgm-cols-<?php echo (int) $columns; ?>">
	<?php foreach ( $courses as $c ) : ?>
		<?php $count = count( MGM_Courses::lesson_ids( $c->ID ) ); ?>
		<article class="mgm-course-card">
			<a href="<?php echo esc_url( get_permalink( $c ) ); ?>" class="mgm-course-thumb">
				<?php if ( has_post_thumbnail( $c ) ) : ?>
					<?php echo get_the_post_thumbnail( $c, 'medium_large' ); ?>
				<?php else : ?>
					<span class="mgm-thumb-placeholder" aria-hidden="true">📘</span>
				<?php endif; ?>
			</a>
			<div class="mgm-course-card-body">
				<h3><a href="<?php echo esc_url( get_permalink( $c ) ); ?>"><?php echo esc_html( get_the_title( $c ) ); ?></a></h3>
				<p class="mgm-muted"><?php echo esc_html( wp_trim_words( $c->post_excerpt ? $c->post_excerpt : wp_strip_all_tags( $c->post_content ), 18 ) ); ?></p>
				<div class="mgm-course-card-meta">
					<span><?php echo esc_html( sprintf( _n( '%d lesson', '%d lessons', $count, 'mg-membership' ), $count ) ); ?></span>
					<?php if ( $user_id && get_user_meta( $user_id, '_mgm_enrolled_' . $c->ID, true ) ) : ?>
						<span><?php echo esc_html( sprintf( __( '%d%% complete', 'mg-membership' ), MGM_Courses::progress( $user_id, $c->ID ) ) ); ?></span>
					<?php elseif ( 'free' === MGM_Courses::access_type( $c->ID ) ) : ?>
						<span class="mgm-tag-preview"><?php esc_html_e( 'Free', 'mg-membership' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</article>
	<?php endforeach; ?>
	<?php if ( ! $courses ) : ?>
		<p><?php esc_html_e( 'No courses found.', 'mg-membership' ); ?></p>
	<?php endif; ?>
</div>
