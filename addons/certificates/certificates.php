<?php
/**
 * Add-on: Course completion certificates (printable / save as PDF) with verification.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Certificates {

	public static function init() {
		if ( ! class_exists( 'MGM_Courses' ) ) {
			return;
		}
		add_action( 'template_redirect', array( __CLASS__, 'render' ), 1 );
		add_action( 'mgm_course_settings_box', array( __CLASS__, 'settings' ) );
		add_action( 'mgm_save_course', array( __CLASS__, 'save' ) );
		add_action( 'mgm_course_cta_after', array( __CLASS__, 'button' ), 10, 3 );
		add_action( 'mgm_my_course_actions', array( __CLASS__, 'button' ), 10, 3 );
		add_action( 'mgm_course_completed', array( __CLASS__, 'issue' ), 10, 2 );
		add_filter( 'mgm_shortcodes_registry', array( __CLASS__, 'shortcodes' ) );
	}

	public static function enabled( $course_id ) {
		return '0' !== (string) get_post_meta( $course_id, '_mgm_cert_enabled', true );
	}

	public static function code( $user_id, $course_id ) {
		$code = get_user_meta( $user_id, '_mgm_cert_' . $course_id, true );
		if ( ! $code && get_user_meta( $user_id, '_mgm_course_completed_' . $course_id, true ) ) {
			$code = self::issue( $user_id, $course_id );
		}
		return $code;
	}

	public static function issue( $user_id, $course_id ) {
		$code = get_user_meta( $user_id, '_mgm_cert_' . $course_id, true );
		if ( ! $code ) {
			$code = strtoupper( substr( wp_hash( $user_id . '|' . $course_id . '|' . time() ), 0, 12 ) );
			update_user_meta( $user_id, '_mgm_cert_' . $course_id, $code );
			update_user_meta( $user_id, '_mgm_cert_date_' . $course_id, time() );
			do_action( 'mgm_certificate_issued', $user_id, $course_id, $code );
		}
		return $code;
	}

	/**
	 * When the certificate was earned. Stored separately so resetting course progress never changes it.
	 */
	public static function issued_at( $user_id, $course_id ) {
		$ts = (int) get_user_meta( $user_id, '_mgm_cert_date_' . $course_id, true );
		if ( ! $ts ) {
			$ts = (int) get_user_meta( $user_id, '_mgm_course_completed_' . $course_id, true );
			if ( $ts ) {
				update_user_meta( $user_id, '_mgm_cert_date_' . $course_id, $ts ); // Backfill older certificates.
			}
		}
		return $ts ? $ts : time();
	}

	public static function url( $code ) {
		return add_query_arg( 'mgm_certificate', rawurlencode( $code ), home_url( '/' ) );
	}

	public static function find( $code ) {
		global $wpdb;
		$code = strtoupper( (string) $code );
		if ( ! preg_match( '/^[A-F0-9]{12}$/', $code ) ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT user_id, meta_key FROM {$wpdb->usermeta} WHERE meta_key REGEXP '^_mgm_cert_[0-9]+$' AND meta_value = %s LIMIT 1", $code ) ); // phpcs:ignore
		if ( ! $row ) {
			return null;
		}
		return array(
			'user_id'   => (int) $row->user_id,
			'course_id' => (int) str_replace( '_mgm_cert_', '', $row->meta_key ),
		);
	}

	public static function button( $course_id, $user_id, $progress ) {
		if ( 100 !== (int) $progress || ! self::enabled( $course_id ) ) {
			return;
		}
		$code = self::code( $user_id, $course_id );
		if ( $code ) {
			echo '<a class="mgm-button mgm-button-secondary" target="_blank" href="' . esc_url( self::url( $code ) ) . '">🎓 ' . esc_html__( 'Certificate', 'mg-membership' ) . '</a>';
		}
	}

	public static function render() {
		if ( empty( $_GET['mgm_certificate'] ) ) { // phpcs:ignore
			return;
		}
		$code = strtoupper( sanitize_text_field( wp_unslash( $_GET['mgm_certificate'] ) ) ); // phpcs:ignore
		$data = MGM_Security_Core::hit( 'cert' ) ? self::find( $code ) : null;
		if ( ! $data || ! self::enabled( $data['course_id'] ) ) {
			wp_die( esc_html__( 'Certificate not found.', 'mg-membership' ), '', array( 'response' => 404 ) );
		}
		$user = get_userdata( $data['user_id'] );
		$name = trim( $user->first_name . ' ' . $user->last_name );
		echo mgm_get_template( // phpcs:ignore
			'certificate.php',
			array(
				'name'      => $name ? $name : $user->display_name,
				'course'    => get_the_title( $data['course_id'] ),
				'course_id' => $data['course_id'],
				'date'      => date_i18n( get_option( 'date_format' ), self::issued_at( $user->ID, $data['course_id'] ) ),
				'code'      => $code,
				'title'     => get_post_meta( $data['course_id'], '_mgm_cert_title', true ) ? get_post_meta( $data['course_id'], '_mgm_cert_title', true ) : __( 'Certificate of Completion', 'mg-membership' ),
				'signer'    => get_post_meta( $data['course_id'], '_mgm_cert_signer', true ),
				'accent'    => get_post_meta( $data['course_id'], '_mgm_cert_color', true ) ? get_post_meta( $data['course_id'], '_mgm_cert_color', true ) : '#4f46e5',
			)
		);
		exit;
	}

	public static function settings( $post ) {
		?>
		<hr><p><strong><?php esc_html_e( 'Certificate', 'mg-membership' ); ?></strong></p>
		<p><label><input type="checkbox" name="mgm_cert[enabled]" value="1" <?php checked( self::enabled( $post->ID ) ); ?>> <?php esc_html_e( 'Award a certificate on completion', 'mg-membership' ); ?></label></p>
		<p><input class="widefat" type="text" name="mgm_cert[title]" placeholder="<?php esc_attr_e( 'Certificate of Completion', 'mg-membership' ); ?>" value="<?php echo esc_attr( get_post_meta( $post->ID, '_mgm_cert_title', true ) ); ?>"></p>
		<p><input class="widefat" type="text" name="mgm_cert[signer]" placeholder="<?php esc_attr_e( 'Signer name & title', 'mg-membership' ); ?>" value="<?php echo esc_attr( get_post_meta( $post->ID, '_mgm_cert_signer', true ) ); ?>"></p>
		<p><label><?php esc_html_e( 'Accent color', 'mg-membership' ); ?> <input type="color" name="mgm_cert[color]" value="<?php echo esc_attr( get_post_meta( $post->ID, '_mgm_cert_color', true ) ? get_post_meta( $post->ID, '_mgm_cert_color', true ) : '#4f46e5' ); ?>"></label></p>
		<?php
	}

	public static function save( $post_id ) {
		$in = isset( $_POST['mgm_cert'] ) ? wp_unslash( $_POST['mgm_cert'] ) : array(); // phpcs:ignore -- nonce checked by course save.
		update_post_meta( $post_id, '_mgm_cert_enabled', empty( $in['enabled'] ) ? '0' : '1' );
		update_post_meta( $post_id, '_mgm_cert_title', sanitize_text_field( $in['title'] ?? '' ) );
		update_post_meta( $post_id, '_mgm_cert_signer', sanitize_text_field( $in['signer'] ?? '' ) );
		update_post_meta( $post_id, '_mgm_cert_color', sanitize_hex_color( $in['color'] ?? '' ) );
	}

	public static function shortcodes( $registry ) {
		$registry['mgm_certificate_verify'] = array(
			'label'    => __( 'Certificate Verification', 'mg-membership' ),
			'icon'     => 'eicon-check-circle-o',
			'callback' => array( __CLASS__, 'sc_verify' ),
			'atts'     => array(),
		);
		return $registry;
	}

	public static function sc_verify() {
		$code = isset( $_GET['cert_code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['cert_code'] ) ) ) : ''; // phpcs:ignore
		$out  = '<form method="get" class="mgm-form mgm-inline"><input type="text" name="cert_code" value="' . esc_attr( $code ) . '" placeholder="' . esc_attr__( 'Certificate code', 'mg-membership' ) . '"> <button class="mgm-button">' . esc_html__( 'Verify', 'mg-membership' ) . '</button></form>';
		if ( $code ) {
			$data = self::find( $code );
			if ( $data ) {
				$u    = get_userdata( $data['user_id'] );
				$out .= '<div class="mgm-notice mgm-notice-success">' . esc_html( sprintf( __( 'Valid certificate: %1$s completed "%2$s".', 'mg-membership' ), $u ? $u->display_name : '', get_the_title( $data['course_id'] ) ) ) . ' <a href="' . esc_url( self::url( $code ) ) . '" target="_blank">' . esc_html__( 'View', 'mg-membership' ) . '</a></div>';
			} else {
				$out .= '<div class="mgm-notice mgm-notice-error">' . esc_html__( 'No certificate found with that code.', 'mg-membership' ) . '</div>';
			}
		}
		return $out;
	}
}

// Run after all add-ons are loaded (needs the Courses add-on).
add_action( 'mgm_loaded', array( 'MGM_Certificates', 'init' ) );
