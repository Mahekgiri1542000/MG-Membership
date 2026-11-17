<?php
/**
 * Quizzes (a lesson with type "quiz").
 *
 * Question format (one block per question):
 *   ? What is the capital of France?
 *   - Berlin
 *   * Paris        (← correct answers start with *)
 *   - Madrid
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Quiz {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'submit' ), 4 );
	}

	public static function questions( $lesson_id ) {
		$raw       = (string) get_post_meta( $lesson_id, '_mgm_quiz_questions', true );
		$questions = array();
		$current   = null;
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$marker = $line[0];
			$text   = trim( substr( $line, 1 ) );
			if ( '?' === $marker ) {
				if ( $current ) {
					$questions[] = $current;
				}
				$current = array(
					'question' => $text,
					'options'  => array(),
					'correct'  => array(),
				);
			} elseif ( $current && ( '-' === $marker || '*' === $marker ) ) {
				if ( '*' === $marker ) {
					$current['correct'][] = count( $current['options'] );
				}
				$current['options'][] = $text;
			}
		}
		if ( $current ) {
			$questions[] = $current;
		}
		return array_values(
			array_filter(
				$questions,
				function ( $q ) {
					return $q['options'] && $q['correct'];
				}
			)
		);
	}

	public static function pass_mark( $lesson_id ) {
		$v = get_post_meta( $lesson_id, '_mgm_quiz_pass', true );
		return '' === $v ? 70 : (int) $v;
	}

	public static function attempts( $user_id, $lesson_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . MGM_Install::table( 'quiz_attempts' ) . ' WHERE user_id = %d AND quiz_id = %d ORDER BY id DESC', $user_id, $lesson_id ) ); // phpcs:ignore
	}

	public static function can_retake( $user_id, $lesson_id ) {
		$max = (int) get_post_meta( $lesson_id, '_mgm_quiz_max_attempts', true );
		if ( $max <= 0 ) {
			return true;
		}
		return count( self::attempts( $user_id, $lesson_id ) ) < $max;
	}

	public static function render( $lesson_id ) {
		$user_id   = get_current_user_id();
		$questions = self::questions( $lesson_id );
		$attempts  = $user_id ? self::attempts( $user_id, $lesson_id ) : array();
		$last      = $attempts ? $attempts[0] : null;
		$retake    = isset( $_GET['retake'] ); // phpcs:ignore
		return mgm_get_template(
			'courses/quiz.php',
			array(
				'lesson_id'  => $lesson_id,
				'questions'  => $questions,
				'last'       => $last,
				'show_form'  => ( ! $last || $retake ) && self::can_retake( $user_id, $lesson_id ),
				'can_retake' => self::can_retake( $user_id, $lesson_id ),
				'pass_mark'  => self::pass_mark( $lesson_id ),
			)
		);
	}

	public static function submit() {
		if ( empty( $_POST['mgm_quiz_submit'] ) || ! is_user_logged_in() ) {
			return;
		}
		$lesson_id = absint( $_POST['lesson_id'] ?? 0 );
		if ( ! isset( $_POST['_mgm_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_nonce'] ), 'mgm_quiz_' . $lesson_id ) ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( ! MGM_Access::can_access_post( $lesson_id ) || ! self::can_retake( $user_id, $lesson_id ) ) {
			return;
		}
		$questions = self::questions( $lesson_id );
		$answers   = isset( $_POST['mgm_q'] ) ? (array) wp_unslash( $_POST['mgm_q'] ) : array(); // phpcs:ignore
		$correct   = 0;
		$given     = array();
		foreach ( $questions as $i => $q ) {
			$a          = array_map( 'intval', (array) ( $answers[ $i ] ?? array() ) );
			sort( $a );
			$expected   = $q['correct'];
			sort( $expected );
			$given[ $i ] = $a;
			if ( $a === $expected ) {
				++$correct;
			}
		}
		$score  = $questions ? round( $correct / count( $questions ) * 100, 2 ) : 0;
		$passed = $score >= self::pass_mark( $lesson_id );

		global $wpdb;
		$wpdb->insert( // phpcs:ignore
			MGM_Install::table( 'quiz_attempts' ),
			array(
				'user_id'    => $user_id,
				'quiz_id'    => $lesson_id,
				'course_id'  => MGM_Courses::lesson_course( $lesson_id ),
				'score'      => $score,
				'passed'     => $passed ? 1 : 0,
				'answers'    => wp_json_encode( $given ),
				'created_at' => mgm_now(),
			)
		);
		do_action( 'mgm_quiz_attempted', $user_id, $lesson_id, $score, $passed );
		if ( $passed ) {
			MGM_Courses::complete_lesson( $user_id, $lesson_id );
		}
		wp_safe_redirect( get_permalink( $lesson_id ) );
		exit;
	}
}
