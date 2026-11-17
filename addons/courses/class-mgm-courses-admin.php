<?php
/**
 * Courses admin: course builder, lesson settings, students screen.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Courses_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'metaboxes' ) );
		add_action( 'save_post_mgm_course', array( __CLASS__, 'save_course' ) );
		add_action( 'save_post_mgm_lesson', array( __CLASS__, 'save_lesson' ) );
		add_action( 'wp_ajax_mgm_quick_lesson', array( __CLASS__, 'ajax_quick_lesson' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_filter( 'manage_mgm_lesson_posts_columns', array( __CLASS__, 'lesson_columns' ) );
		add_action( 'manage_mgm_lesson_posts_custom_column', array( __CLASS__, 'lesson_column' ), 10, 2 );
		add_filter( 'manage_mgm_course_posts_columns', array( __CLASS__, 'course_columns' ) );
		add_action( 'manage_mgm_course_posts_custom_column', array( __CLASS__, 'course_column' ), 10, 2 );
		add_action( 'admin_post_mgm_reset_student', array( __CLASS__, 'reset_student' ) );
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=mgm_course', __( 'Students', 'mg-membership' ), __( 'Students & Progress', 'mg-membership' ), mgm_admin_cap(), 'mgm-students', array( __CLASS__, 'students_page' ) );
	}

	public static function metaboxes() {
		add_meta_box( 'mgm_course_builder', __( 'Curriculum Builder', 'mg-membership' ), array( __CLASS__, 'builder_box' ), 'mgm_course', 'normal', 'high' );
		add_meta_box( 'mgm_course_settings', __( 'Course Access & Settings', 'mg-membership' ), array( __CLASS__, 'settings_box' ), 'mgm_course', 'side', 'default' );
		add_meta_box( 'mgm_lesson_settings', __( 'Lesson Settings', 'mg-membership' ), array( __CLASS__, 'lesson_box' ), 'mgm_lesson', 'normal', 'high' );
	}

	/* ----------------------------- Course ------------------------------ */

	public static function builder_box( $post ) {
		wp_nonce_field( 'mgm_course_save', '_mgm_course_nonce' );
		$curriculum = MGM_Courses::curriculum( $post->ID );
		if ( ! $curriculum ) {
			$curriculum = array( array( 'title' => __( 'Section 1', 'mg-membership' ), 'lessons' => array() ) );
		}
		$lessons = get_posts(
			array(
				'post_type'   => 'mgm_lesson',
				'numberposts' => -1,
				'post_status' => array( 'publish', 'draft' ),
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$lesson_data = array();
		foreach ( $lessons as $l ) {
			$course = MGM_Courses::lesson_course( $l->ID );
			if ( $course && $course !== $post->ID ) {
				continue; // Belongs to another course.
			}
			$lesson_data[ $l->ID ] = array(
				'title' => $l->post_title,
				'type'  => get_post_meta( $l->ID, '_mgm_lesson_type', true ) ? get_post_meta( $l->ID, '_mgm_lesson_type', true ) : 'text',
				'edit'  => get_edit_post_link( $l->ID, 'raw' ),
			);
		}
		?>
		<div id="mgm-curriculum-builder"
			data-curriculum="<?php echo esc_attr( wp_json_encode( $curriculum ) ); ?>"
			data-lessons="<?php echo esc_attr( wp_json_encode( (object) $lesson_data ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'mgm_quick_lesson' ) ); ?>"
			data-course="<?php echo (int) $post->ID; ?>">
			<div class="mgm-sections"></div>
			<p><button type="button" class="button mgm-add-section"><?php esc_html_e( '+ Add section', 'mg-membership' ); ?></button></p>
			<input type="hidden" name="mgm_curriculum_json" value="">
			<p class="description"><?php esc_html_e( 'Drag lessons to reorder. Create lessons inline or under Courses → Lessons & Quizzes.', 'mg-membership' ); ?></p>
		</div>
		<?php
	}

	public static function settings_box( $post ) {
		$g      = function ( $k, $d = '' ) use ( $post ) {
			return MGM_Courses::course_meta( $post->ID, $k, $d );
		};
		$access = $g( 'access', 'members' );
		?>
		<p><strong><?php esc_html_e( 'Who can take this course?', 'mg-membership' ); ?></strong></p>
		<p>
			<label><input type="radio" name="mgm_course[access]" value="members" <?php checked( $access, 'members' ); ?>> <?php esc_html_e( 'Members of selected memberships', 'mg-membership' ); ?></label><br>
			<label><input type="radio" name="mgm_course[access]" value="open" <?php checked( $access, 'open' ); ?>> <?php esc_html_e( 'Any logged-in user', 'mg-membership' ); ?></label><br>
			<label><input type="radio" name="mgm_course[access]" value="free" <?php checked( $access, 'free' ); ?>> <?php esc_html_e( 'Everyone (public)', 'mg-membership' ); ?></label>
		</p>
		<?php echo MGM_Admin_Metaboxes::memberships_checklist( 'mgm_course[memberships]', MGM_Courses::course_memberships( $post->ID ) ); // phpcs:ignore ?>
		<p class="description"><?php esc_html_e( 'None selected = any active membership.', 'mg-membership' ); ?></p>
		<hr>
		<p><label><input type="checkbox" name="mgm_course[sequential]" value="1" <?php checked( '1', $g( 'sequential' ) ); ?>> <?php esc_html_e( 'Lessons must be completed in order', 'mg-membership' ); ?></label></p>
		<p><label><input type="checkbox" name="mgm_course[allow_reset]" value="1" <?php checked( '1', $g( 'allow_reset' ) ); ?>> <?php esc_html_e( 'Students can reset progress', 'mg-membership' ); ?></label></p>
		<p><label><input type="checkbox" name="mgm_custom_layout" value="1" <?php checked( '1', get_post_meta( $post->ID, '_mgm_custom_layout', true ) ); ?>> <?php esc_html_e( 'Custom page-builder layout (use course widgets)', 'mg-membership' ); ?></label></p>
		<p><label><?php esc_html_e( 'Level', 'mg-membership' ); ?></label><br>
			<select name="mgm_course[level]">
				<?php foreach ( array( '' => '—', 'beginner' => __( 'Beginner', 'mg-membership' ), 'intermediate' => __( 'Intermediate', 'mg-membership' ), 'advanced' => __( 'Advanced', 'mg-membership' ), 'all' => __( 'All levels', 'mg-membership' ) ) as $k => $l ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $g( 'level' ), $k ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select></p>
		<p><label><?php esc_html_e( 'Total duration (e.g. 4h 30m)', 'mg-membership' ); ?></label><br><input type="text" class="widefat" name="mgm_course[duration]" value="<?php echo esc_attr( $g( 'duration' ) ); ?>"></p>
		<p><label><?php esc_html_e( 'Locked lesson message', 'mg-membership' ); ?></label><textarea class="widefat" rows="2" name="mgm_course[locked_message]"><?php echo esc_textarea( $g( 'locked_message' ) ); ?></textarea></p>
		<?php
		do_action( 'mgm_course_settings_box', $post );
	}

	public static function save_course( $post_id ) {
		if ( ! isset( $_POST['_mgm_course_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_course_nonce'] ), 'mgm_course_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = isset( $_POST['mgm_course'] ) ? wp_unslash( $_POST['mgm_course'] ) : array(); // phpcs:ignore
		update_post_meta( $post_id, '_mgm_course_access', in_array( $in['access'] ?? '', array( 'members', 'open', 'free' ), true ) ? $in['access'] : 'members' );
		update_post_meta( $post_id, '_mgm_course_memberships', mgm_int_list( $in['memberships'] ?? array() ) );
		update_post_meta( $post_id, '_mgm_course_sequential', empty( $in['sequential'] ) ? '' : '1' );
		update_post_meta( $post_id, '_mgm_course_allow_reset', empty( $in['allow_reset'] ) ? '' : '1' );
		update_post_meta( $post_id, '_mgm_course_level', sanitize_key( $in['level'] ?? '' ) );
		update_post_meta( $post_id, '_mgm_course_duration', sanitize_text_field( $in['duration'] ?? '' ) );
		update_post_meta( $post_id, '_mgm_course_locked_message', wp_kses_post( $in['locked_message'] ?? '' ) );
		update_post_meta( $post_id, '_mgm_custom_layout', empty( $_POST['mgm_custom_layout'] ) ? '' : '1' );
		do_action( 'mgm_save_course', $post_id, $in );

		$json = isset( $_POST['mgm_curriculum_json'] ) ? json_decode( wp_unslash( $_POST['mgm_curriculum_json'] ), true ) : null; // phpcs:ignore
		if ( is_array( $json ) ) {
			$clean = array();
			$all   = array();
			foreach ( $json as $section ) {
				$ids     = array_values(
					array_filter(
						mgm_int_list( $section['lessons'] ?? array() ),
						function ( $lid ) use ( $post_id ) {
							if ( 'mgm_lesson' !== get_post_type( $lid ) || ! current_user_can( 'edit_post', $lid ) ) {
								return false;
							}
							$owner = MGM_Courses::lesson_course( $lid );
							return ! $owner || $owner === $post_id || current_user_can( 'edit_post', $owner );
						}
					)
				);
				$all     = array_merge( $all, $ids );
				$clean[] = array(
					'title'   => sanitize_text_field( $section['title'] ?? '' ),
					'lessons' => $ids,
				);
			}
			// Detach lessons removed from this course.
			foreach ( get_posts( array( 'post_type' => 'mgm_lesson', 'numberposts' => -1, 'fields' => 'ids', 'post_status' => 'any', 'meta_key' => '_mgm_course_id', 'meta_value' => $post_id ) ) as $lid ) { // phpcs:ignore
				if ( ! in_array( $lid, $all, true ) ) {
					delete_post_meta( $lid, '_mgm_course_id' );
				}
			}
			foreach ( $all as $lid ) {
				update_post_meta( $lid, '_mgm_course_id', $post_id );
			}
			update_post_meta( $post_id, '_mgm_curriculum', $clean );
		}
	}

	public static function ajax_quick_lesson() {
		check_ajax_referer( 'mgm_quick_lesson' );
		$course = absint( $_POST['course'] ?? 0 );
		if ( ! current_user_can( 'edit_post', $course ) ) {
			wp_send_json_error();
		}
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$type  = sanitize_key( $_POST['type'] ?? 'text' );
		if ( ! $title ) {
			wp_send_json_error();
		}
		$id = wp_insert_post(
			array(
				'post_type'   => 'mgm_lesson',
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);
		if ( is_wp_error( $id ) ) {
			wp_send_json_error();
		}
		update_post_meta( $id, '_mgm_course_id', $course );
		update_post_meta( $id, '_mgm_lesson_type', in_array( $type, array( 'text', 'video', 'quiz' ), true ) ? $type : 'text' );
		wp_send_json_success(
			array(
				'id'    => $id,
				'title' => $title,
				'type'  => $type,
				'edit'  => get_edit_post_link( $id, 'raw' ),
			)
		);
	}

	/* ----------------------------- Lesson ------------------------------ */

	public static function lesson_box( $post ) {
		wp_nonce_field( 'mgm_lesson_save', '_mgm_lesson_nonce' );
		$g       = function ( $k, $d = '' ) use ( $post ) {
			$v = get_post_meta( $post->ID, '_mgm_' . $k, true );
			return '' === $v ? $d : $v;
		};
		$courses = get_posts( array( 'post_type' => 'mgm_course', 'numberposts' => -1, 'post_status' => array( 'publish', 'draft' ) ) );
		$type    = $g( 'lesson_type', 'text' );
		?>
		<div class="mgm-fields">
			<p class="mgm-field"><label><?php esc_html_e( 'Course', 'mg-membership' ); ?></label>
				<select name="mgm_lesson[course_id]"><option value="0">—</option>
				<?php foreach ( $courses as $c ) : ?>
					<option value="<?php echo (int) $c->ID; ?>" <?php selected( (int) $g( 'course_id' ), $c->ID ); ?>><?php echo esc_html( $c->post_title ); ?></option>
				<?php endforeach; ?></select>
				<span class="description"><?php esc_html_e( 'Use the Curriculum Builder on the course to position the lesson.', 'mg-membership' ); ?></span></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Type', 'mg-membership' ); ?></label>
				<select name="mgm_lesson[lesson_type]" class="mgm-lesson-type">
					<option value="text" <?php selected( $type, 'text' ); ?>><?php esc_html_e( 'Text / article', 'mg-membership' ); ?></option>
					<option value="video" <?php selected( $type, 'video' ); ?>><?php esc_html_e( 'Video', 'mg-membership' ); ?></option>
					<option value="quiz" <?php selected( $type, 'quiz' ); ?>><?php esc_html_e( 'Quiz', 'mg-membership' ); ?></option>
				</select></p>
			<p class="mgm-field mgm-lesson-video"><label><?php esc_html_e( 'Video URL (YouTube, Vimeo, Wistia, MP4…)', 'mg-membership' ); ?></label>
				<input type="url" class="large-text" name="mgm_lesson[video_url]" value="<?php echo esc_attr( $g( 'video_url' ) ); ?>"></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Duration', 'mg-membership' ); ?></label>
				<input type="text" name="mgm_lesson[duration]" value="<?php echo esc_attr( $g( 'duration' ) ); ?>" placeholder="12 min"></p>
			<p class="mgm-field"><label><input type="checkbox" name="mgm_lesson[preview]" value="1" <?php checked( '1', $g( 'preview' ) ); ?>> <?php esc_html_e( 'Free preview (anyone can view)', 'mg-membership' ); ?></label></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Downloadable resources (one URL per line, optional "Title | URL")', 'mg-membership' ); ?></label>
				<textarea name="mgm_lesson[resources]" rows="3" class="large-text"><?php echo esc_textarea( $g( 'resources' ) ); ?></textarea></p>
			<div class="mgm-lesson-quiz mgm-subgroup">
				<p class="mgm-field"><label><?php esc_html_e( 'Questions', 'mg-membership' ); ?></label>
					<textarea name="mgm_lesson[quiz_questions]" rows="12" class="large-text code" placeholder="? What is 2 + 2?&#10;- 3&#10;* 4&#10;- 5&#10;&#10;? Select all primary colors&#10;* Red&#10;* Blue&#10;- Green"><?php echo esc_textarea( $g( 'quiz_questions' ) ); ?></textarea>
					<span class="description"><?php esc_html_e( 'Start a question with "?", wrong answers with "-", correct answers with "*". Several "*" = multiple-choice question.', 'mg-membership' ); ?></span></p>
				<p class="mgm-field"><label><?php esc_html_e( 'Pass mark (%)', 'mg-membership' ); ?></label>
					<input type="number" min="0" max="100" name="mgm_lesson[quiz_pass]" value="<?php echo esc_attr( $g( 'quiz_pass', '70' ) ); ?>" style="width:90px">
					<label><?php esc_html_e( 'Max attempts (0 = unlimited)', 'mg-membership' ); ?></label>
					<input type="number" min="0" name="mgm_lesson[quiz_max_attempts]" value="<?php echo esc_attr( $g( 'quiz_max_attempts', '0' ) ); ?>" style="width:90px"></p>
				<p class="mgm-field"><label><input type="checkbox" name="mgm_lesson[quiz_show_answers]" value="1" <?php checked( '1', $g( 'quiz_show_answers' ) ); ?>> <?php esc_html_e( 'Show correct answers after submission', 'mg-membership' ); ?></label></p>
			</div>
		</div>
		<?php
	}

	public static function save_lesson( $post_id ) {
		if ( ! isset( $_POST['_mgm_lesson_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_lesson_nonce'] ), 'mgm_lesson_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in     = isset( $_POST['mgm_lesson'] ) ? wp_unslash( $_POST['mgm_lesson'] ) : array(); // phpcs:ignore
		$course = absint( $in['course_id'] ?? 0 );
		if ( $course && ( 'mgm_course' !== get_post_type( $course ) || ! current_user_can( 'edit_post', $course ) ) ) {
			$course = MGM_Courses::lesson_course( $post_id ); // Not allowed to move it there – keep current course.
		}
		$old    = MGM_Courses::lesson_course( $post_id );
		update_post_meta( $post_id, '_mgm_course_id', $course );
		update_post_meta( $post_id, '_mgm_lesson_type', in_array( $in['lesson_type'] ?? '', array( 'text', 'video', 'quiz' ), true ) ? $in['lesson_type'] : 'text' );
		update_post_meta( $post_id, '_mgm_video_url', esc_url_raw( $in['video_url'] ?? '' ) );
		update_post_meta( $post_id, '_mgm_duration', sanitize_text_field( $in['duration'] ?? '' ) );
		update_post_meta( $post_id, '_mgm_preview', empty( $in['preview'] ) ? '' : '1' );
		update_post_meta( $post_id, '_mgm_resources', sanitize_textarea_field( $in['resources'] ?? '' ) );
		update_post_meta( $post_id, '_mgm_quiz_questions', sanitize_textarea_field( $in['quiz_questions'] ?? '' ) );
		update_post_meta( $post_id, '_mgm_quiz_pass', min( 100, absint( $in['quiz_pass'] ?? 70 ) ) );
		update_post_meta( $post_id, '_mgm_quiz_max_attempts', absint( $in['quiz_max_attempts'] ?? 0 ) );
		update_post_meta( $post_id, '_mgm_quiz_show_answers', empty( $in['quiz_show_answers'] ) ? '' : '1' );

		// Append to the course's last section if newly attached.
		if ( $course && $course !== $old && ! in_array( $post_id, MGM_Courses::lesson_ids( $course ), true ) ) {
			$cur = get_post_meta( $course, '_mgm_curriculum', true );
			$cur = is_array( $cur ) && $cur ? $cur : array( array( 'title' => __( 'Section 1', 'mg-membership' ), 'lessons' => array() ) );
			$cur[ count( $cur ) - 1 ]['lessons'][] = $post_id;
			update_post_meta( $course, '_mgm_curriculum', $cur );
		}
	}

	public static function lesson_columns( $cols ) {
		$cols['mgm_course'] = __( 'Course', 'mg-membership' );
		$cols['mgm_type']   = __( 'Type', 'mg-membership' );
		return $cols;
	}

	public static function lesson_column( $col, $id ) {
		if ( 'mgm_course' === $col ) {
			$c = MGM_Courses::lesson_course( $id );
			echo $c ? '<a href="' . esc_url( get_edit_post_link( $c ) ) . '">' . esc_html( get_the_title( $c ) ) . '</a>' : '—';
		} elseif ( 'mgm_type' === $col ) {
			$t = get_post_meta( $id, '_mgm_lesson_type', true );
			echo esc_html( ucfirst( $t ? $t : 'text' ) ) . ( get_post_meta( $id, '_mgm_preview', true ) ? ' · ' . esc_html__( 'Preview', 'mg-membership' ) : '' );
		}
	}

	public static function course_columns( $cols ) {
		$cols['mgm_lessons']  = __( 'Lessons', 'mg-membership' );
		$cols['mgm_students'] = __( 'Students', 'mg-membership' );
		$cols['mgm_access']   = __( 'Access', 'mg-membership' );
		return $cols;
	}

	public static function course_column( $col, $id ) {
		global $wpdb;
		if ( 'mgm_lessons' === $col ) {
			echo (int) count( MGM_Courses::lesson_ids( $id ) );
		} elseif ( 'mgm_students' === $col ) {
			echo (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s", '_mgm_enrolled_' . $id ) ); // phpcs:ignore
		} elseif ( 'mgm_access' === $col ) {
			$a = MGM_Courses::access_type( $id );
			if ( 'members' === $a ) {
				$ms = MGM_Courses::course_memberships( $id );
				echo esc_html( $ms ? implode( ', ', array_map( 'get_the_title', $ms ) ) : __( 'Any membership', 'mg-membership' ) );
			} else {
				echo esc_html( 'free' === $a ? __( 'Public', 'mg-membership' ) : __( 'Logged-in users', 'mg-membership' ) );
			}
		}
	}

	/* ----------------------------- Students ---------------------------- */

	public static function students_page() {
		global $wpdb;
		MGM_Admin::header( __( 'Students & Progress', 'mg-membership' ), array( 'desc' => __( 'Who is enrolled in each course and how far they have got.', 'mg-membership' ), 'actions' => array( array( 'label' => __( 'New course', 'mg-membership' ), 'url' => admin_url( 'post-new.php?post_type=mgm_course' ), 'primary' => true, 'icon' => 'dashicons-plus-alt2' ) ) ) );
		$courses = get_posts( array( 'post_type' => 'mgm_course', 'numberposts' => -1 ) );
		$cid     = absint( $_GET['course'] ?? ( $courses ? $courses[0]->ID : 0 ) ); // phpcs:ignore
		echo '<div class="mgm-toolbar"><form method="get" class="mgm-filter"><input type="hidden" name="post_type" value="mgm_course"><input type="hidden" name="page" value="mgm-students"><label class="mgm-filter-label" for="mgm-course-pick">' . esc_html__( 'Course', 'mg-membership' ) . '</label><select id="mgm-course-pick" name="course" onchange="this.form.submit()">';
		foreach ( $courses as $c ) {
			echo '<option value="' . (int) $c->ID . '" ' . selected( $cid, $c->ID, false ) . '>' . esc_html( $c->post_title ) . '</option>';
		}
		echo '</select></form></div>';
		if ( ! $cid ) {
			echo '<p>' . esc_html__( 'No courses yet.', 'mg-membership' ) . '</p>';
			MGM_Admin::footer();
			return;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s ORDER BY meta_value DESC LIMIT 500", '_mgm_enrolled_' . $cid ) ); // phpcs:ignore
		echo '<div class="mgm-table-card"><table class="widefat mgm-table"><thead><tr><th>' . esc_html__( 'Student', 'mg-membership' ) . '</th><th>' . esc_html__( 'Enrolled', 'mg-membership' ) . '</th><th>' . esc_html__( 'Progress', 'mg-membership' ) . '</th><th>' . esc_html__( 'Completed', 'mg-membership' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$u = get_userdata( $r->user_id );
			if ( ! $u ) {
				continue;
			}
			$p    = MGM_Courses::progress( $u->ID, $cid );
			$done = get_user_meta( $u->ID, '_mgm_course_completed_' . $cid, true );
			printf(
				'<tr><td><a href="%s"><strong>%s</strong></a><br><small class="mgm-muted">%s</small></td><td>%s</td><td><div class="mgm-progress"><div class="mgm-progress-bar" style="width:%d%%"></div></div> %d%%</td><td>%s</td><td class="mgm-row-actions"><a class="mgm-btn mgm-btn-sm" href="%s" onclick="return confirm(\'%s\')">%s</a></td></tr>',
				esc_url( admin_url( 'admin.php?page=mgm-members&member=' . $u->ID ) ),
				esc_html( $u->display_name ),
				esc_html( $u->user_email ),
				esc_html( date_i18n( get_option( 'date_format' ), (int) $r->meta_value ) ),
				(int) $p,
				(int) $p,
				$done ? esc_html( date_i18n( get_option( 'date_format' ), (int) $done ) ) : '—',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mgm_reset_student&user=' . $u->ID . '&course=' . $cid ), 'mgm_reset_student' ) ),
				esc_js( __( 'Reset this student\'s progress?', 'mg-membership' ) ),
				esc_html__( 'Reset', 'mg-membership' )
			);
		}
		if ( ! $rows ) {
			echo '<tr class="mgm-empty-row"><td colspan="5"><span class="dashicons dashicons-welcome-learn-more" aria-hidden="true"></span><strong>' . esc_html__( 'No students enrolled yet', 'mg-membership' ) . '</strong><span>' . esc_html__( 'Members appear here once they start the course.', 'mg-membership' ) . '</span></td></tr>';
		}
		echo '</tbody></table></div>';
		MGM_Admin::footer();
	}

	public static function reset_student() {
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			wp_die();
		}
		check_admin_referer( 'mgm_reset_student' );
		MGM_Courses::reset_progress( absint( $_GET['user'] ?? 0 ), absint( $_GET['course'] ?? 0 ) );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
