<?php
/**
 * Add-on: Import members from CSV.
 *
 * Columns (header row, any order): email (required), first_name, last_name, username,
 * membership (ID or exact title), expires (YYYY-MM-DD | lifetime | blank = membership terms),
 * plus any custom field key.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Import {

	public static function init() {
		if ( is_admin() ) {
			add_action( 'mgm_admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'admin_post_mgm_import', array( __CLASS__, 'handle' ) );
		}
	}

	public static function menu( $cap ) {
		add_submenu_page( 'mgm', __( 'Import Members', 'mg-membership' ), __( 'Import', 'mg-membership' ), $cap, 'mgm-import', array( __CLASS__, 'page' ) );
	}

	private static function membership_id( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}
		if ( ctype_digit( $value ) && 'mgm_membership' === get_post_type( (int) $value ) ) {
			return (int) $value;
		}
		foreach ( mgm_membership_options() as $id => $title ) {
			if ( 0 === strcasecmp( $title, $value ) ) {
				return (int) $id;
			}
		}
		return -1;
	}

	/**
	 * Import rows from a CSV file.
	 *
	 * @return array{created:int,updated:int,granted:int,skipped:int,errors:string[]}
	 */
	public static function import_file( $path, array $opts = array() ) {
		$opts   = wp_parse_args( $opts, array( 'update' => false, 'notify' => false ) );
		$result = array( 'created' => 0, 'updated' => 0, 'granted' => 0, 'skipped' => 0, 'errors' => array() );
		$fh     = fopen( $path, 'r' ); // phpcs:ignore
		if ( ! $fh ) {
			$result['errors'][] = __( 'Could not read the file.', 'mg-membership' );
			return $result;
		}
		$head = fgetcsv( $fh );
		if ( ! $head ) {
			$result['errors'][] = __( 'The file is empty.', 'mg-membership' );
			return $result;
		}
		$head = array_map(
			function ( $h ) {
				return sanitize_key( str_replace( ' ', '_', strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', $h ) ) ) ) );
			},
			$head
		);
		if ( ! in_array( 'email', $head, true ) ) {
			$result['errors'][] = __( 'The header row must contain an "email" column.', 'mg-membership' );
			return $result;
		}
		$fields = class_exists( 'MGM_Custom_Fields' ) ? wp_list_pluck( MGM_Custom_Fields::fields(), 'key' ) : array();
		$line   = 1;
		while ( false !== ( $cells = fgetcsv( $fh ) ) ) {
			++$line;
			if ( 1 === count( $cells ) && '' === trim( (string) $cells[0] ) ) {
				continue;
			}
			$row   = array_combine( $head, array_pad( array_slice( $cells, 0, count( $head ) ), count( $head ), '' ) );
			$email = sanitize_email( $row['email'] );
			if ( ! is_email( $email ) ) {
				$result['errors'][] = sprintf( __( 'Line %1$d: "%2$s" is not a valid email.', 'mg-membership' ), $line, $row['email'] );
				continue;
			}
			$mid = self::membership_id( $row['membership'] ?? '' );
			if ( -1 === $mid ) {
				$result['errors'][] = sprintf( __( 'Line %1$d: membership "%2$s" not found.', 'mg-membership' ), $line, $row['membership'] );
				continue;
			}
			$user = get_user_by( 'email', $email );
			$data = array_filter(
				array(
					'first_name' => sanitize_text_field( $row['first_name'] ?? '' ),
					'last_name'  => sanitize_text_field( $row['last_name'] ?? '' ),
				),
				'strlen'
			);
			if ( ! $user ) {
				$login = sanitize_user( $row['username'] ?? '', true );
				$login = $login && ! username_exists( $login ) ? $login : sanitize_user( current( explode( '@', $email ) ), true );
				while ( username_exists( $login ) ) {
					$login .= wp_rand( 1, 9 );
				}
				$uid = wp_insert_user( array_merge( $data, array( 'user_login' => $login, 'user_email' => $email, 'user_pass' => wp_generate_password( 20 ) ) ) );
				if ( is_wp_error( $uid ) ) {
					$result['errors'][] = sprintf( __( 'Line %1$d: %2$s', 'mg-membership' ), $line, $uid->get_error_message() );
					continue;
				}
				$user = get_userdata( $uid );
				++$result['created'];
				if ( $opts['notify'] ) {
					wp_new_user_notification( $uid, null, 'user' );
				}
			} elseif ( $opts['update'] && $data ) {
				wp_update_user( array_merge( $data, array( 'ID' => $user->ID ) ) );
				++$result['updated'];
			} else {
				++$result['skipped'];
			}
			foreach ( $fields as $key ) {
				if ( isset( $row[ $key ] ) && '' !== $row[ $key ] && ( $opts['update'] || ! get_user_meta( $user->ID, 'mgm_field_' . $key, true ) ) ) {
					update_user_meta( $user->ID, 'mgm_field_' . $key, sanitize_text_field( $row[ $key ] ) );
				}
			}
			if ( $mid > 0 && ! in_array( $mid, MGM_Member::active_membership_ids( $user->ID ), true ) ) {
				$exp = strtolower( trim( $row['expires'] ?? '' ) );
				if ( 'lifetime' === $exp || 'never' === $exp ) {
					$expires = null;
				} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $exp ) ) {
					$expires = gmdate( 'Y-m-d 23:59:59', strtotime( $exp ) );
				} else {
					$expires = 'auto';
				}
				MGM_Member::grant( $user->ID, $mid, $expires, 'import' );
				++$result['granted'];
			}
		}
		fclose( $fh ); // phpcs:ignore
		return $result;
	}

	public static function page() {
		MGM_Admin::header( __( 'Import Members', 'mg-membership' ), array( 'desc' => __( 'Upload a CSV to create accounts and grant memberships in bulk – handy when moving from another membership plugin.', 'mg-membership' ) ) );
		$res = get_transient( 'mgm_import_result_' . get_current_user_id() );
		if ( $res ) {
			delete_transient( 'mgm_import_result_' . get_current_user_id() );
			echo '<div class="mgm-card"><h2 class="mgm-card-title">' . esc_html__( 'Import finished', 'mg-membership' ) . '</h2><p>' . esc_html( sprintf( __( 'Created %1$d · Updated %2$d · Already existed %3$d · Memberships granted %4$d · Problems %5$d', 'mg-membership' ), $res['created'], $res['updated'], $res['skipped'], $res['granted'], count( $res['errors'] ) ) ) . '</p>';
			if ( $res['errors'] ) {
				echo '<ul style="color:#b42318">';
				foreach ( array_slice( $res['errors'], 0, 50 ) as $e ) {
					echo '<li>' . esc_html( $e ) . '</li>';
				}
				echo '</ul>';
			}
			echo '</div>';
		}
		echo '<form class="mgm-card" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'mgm_import' );
		echo '<input type="hidden" name="action" value="mgm_import"><div class="mgm-fields-grid">';
		echo '<div class="mgm-field-row"><label class="mgm-field-label" for="mgm-csv">' . esc_html__( 'CSV file', 'mg-membership' ) . '</label><div class="mgm-field-control"><input type="file" id="mgm-csv" name="csv" accept=".csv,text/csv" required><p class="description">' . esc_html__( 'Columns: email (required), first_name, last_name, username, membership (ID or exact name), expires (YYYY-MM-DD, "lifetime", or blank for the membership\'s own terms), plus any custom field key.', 'mg-membership' ) . '</p><p class="description"><code>email,first_name,last_name,membership,expires</code><br><code>jane@example.com,Jane,Doe,Gold,2027-12-31</code></p></div></div>';
		echo '<div class="mgm-field-row mgm-field-toggle"><label class="mgm-switch"><input type="checkbox" name="update" value="1"><span class="mgm-switch-ui" aria-hidden="true"></span><span class="mgm-switch-label">' . esc_html__( 'Update names and fields of existing users', 'mg-membership' ) . '</span></label></div>';
		echo '<div class="mgm-field-row mgm-field-toggle"><label class="mgm-switch"><input type="checkbox" name="notify" value="1"><span class="mgm-switch-ui" aria-hidden="true"></span><span class="mgm-switch-label">' . esc_html__( 'Email new users a link to set their password', 'mg-membership' ) . '</span></label></div>';
		echo '</div><p>';
		submit_button( __( 'Import', 'mg-membership' ), 'primary mgm-btn-submit', 'submit', false );
		echo '</p></form>';
		MGM_Admin::footer();
	}

	public static function handle() {
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mg-membership' ) );
		}
		check_admin_referer( 'mgm_import' );
		$res = array( 'created' => 0, 'updated' => 0, 'granted' => 0, 'skipped' => 0, 'errors' => array( __( 'No file uploaded.', 'mg-membership' ) ) );
		if ( ! empty( $_FILES['csv']['tmp_name'] ) && UPLOAD_ERR_OK === (int) $_FILES['csv']['error'] ) {
			$name = sanitize_file_name( wp_unslash( $_FILES['csv']['name'] ) );
			if ( 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
				$res['errors'] = array( __( 'Please upload a .csv file.', 'mg-membership' ) );
			} else {
				$res = self::import_file( $_FILES['csv']['tmp_name'], array( 'update' => ! empty( $_POST['update'] ), 'notify' => ! empty( $_POST['notify'] ) ) ); // phpcs:ignore
			}
		}
		set_transient( 'mgm_import_result_' . get_current_user_id(), $res, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=mgm-import' ) );
		exit;
	}
}

MGM_Import::init();
