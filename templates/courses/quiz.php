<?php
/**
 * Quiz form / result.
 *
 * @var int         $lesson_id
 * @var array       $questions
 * @var object|null $last
 * @var bool        $show_form
 * @var bool        $can_retake
 * @var int         $pass_mark
 */

defined( 'ABSPATH' ) || exit;

if ( ! $questions ) {
	echo '<p class="mgm-muted">' . esc_html__( 'This quiz has no questions yet.', 'mg-membership' ) . '</p>';
	return;
}
// Never reveal the answer key while the member can still retake the quiz.
$show_answers = get_post_meta( $lesson_id, '_mgm_quiz_show_answers', true ) && $last && ( $last->passed || ! $can_retake );
?>
<div class="mgm-quiz">
	<?php if ( $last && ! $show_form ) : ?>
		<div class="mgm-quiz-result <?php echo $last->passed ? 'is-passed' : 'is-failed'; ?>">
			<div class="mgm-quiz-score"><?php echo esc_html( round( $last->score ) ); ?>%</div>
			<div>
				<strong><?php echo $last->passed ? esc_html__( 'Passed! 🎉', 'mg-membership' ) : esc_html__( 'Not passed yet', 'mg-membership' ); ?></strong>
				<p><?php echo esc_html( sprintf( __( 'Pass mark: %d%%', 'mg-membership' ), $pass_mark ) ); ?></p>
			</div>
			<?php if ( $can_retake ) : ?>
				<a class="mgm-button mgm-button-secondary" href="<?php echo esc_url( add_query_arg( 'retake', 1, get_permalink( $lesson_id ) ) ); ?>"><?php esc_html_e( 'Retake quiz', 'mg-membership' ); ?></a>
			<?php endif; ?>
		</div>
		<?php if ( $show_answers ) : ?>
			<?php $given = json_decode( (string) $last->answers, true ); ?>
			<ol class="mgm-quiz-review">
				<?php foreach ( $questions as $i => $q ) : ?>
					<li><strong><?php echo esc_html( $q['question'] ); ?></strong><ul>
					<?php foreach ( $q['options'] as $oi => $opt ) : ?>
						<?php $picked = in_array( $oi, (array) ( $given[ $i ] ?? array() ), true ); $right = in_array( $oi, $q['correct'], true ); ?>
						<li class="<?php echo $right ? 'is-correct' : ( $picked ? 'is-wrong' : '' ); ?>"><?php echo esc_html( $opt ); ?><?php echo $picked ? ' ←' : ''; ?></li>
					<?php endforeach; ?>
					</ul></li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	<?php elseif ( $show_form ) : ?>
		<form method="post" class="mgm-form">
			<?php wp_nonce_field( 'mgm_quiz_' . $lesson_id, '_mgm_nonce' ); ?>
			<input type="hidden" name="mgm_quiz_submit" value="1">
			<input type="hidden" name="lesson_id" value="<?php echo (int) $lesson_id; ?>">
			<?php foreach ( $questions as $i => $q ) : ?>
				<?php $multi = count( $q['correct'] ) > 1; ?>
				<fieldset class="mgm-question">
					<legend><?php echo (int) ( $i + 1 ) . '. ' . esc_html( $q['question'] ); ?> <?php if ( $multi ) : ?><small>(<?php esc_html_e( 'select all that apply', 'mg-membership' ); ?>)</small><?php endif; ?></legend>
					<?php foreach ( $q['options'] as $oi => $opt ) : ?>
						<label class="mgm-check"><input type="<?php echo $multi ? 'checkbox' : 'radio'; ?>" name="mgm_q[<?php echo (int) $i; ?>][]" value="<?php echo (int) $oi; ?>" <?php echo $multi ? '' : 'required'; ?>> <?php echo esc_html( $opt ); ?></label>
					<?php endforeach; ?>
				</fieldset>
			<?php endforeach; ?>
			<p><button type="submit" class="mgm-button"><?php esc_html_e( 'Submit answers', 'mg-membership' ); ?></button></p>
		</form>
	<?php else : ?>
		<p><?php esc_html_e( 'You have used all attempts for this quiz.', 'mg-membership' ); ?></p>
	<?php endif; ?>
</div>
