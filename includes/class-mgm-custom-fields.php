<?php
/**
 * Custom registration & profile fields (core). Field definitions are edited in MG Membership → Forms.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

/**
 * Value of a member's custom field.
 */
function mgm_get_member_field( $user_id, $key ) {
	return get_user_meta( (int) $user_id, 'mgm_field_' . sanitize_key( $key ), true );
}

class MGM_Custom_Fields {

	const TYPES = array( 'text', 'textarea', 'select', 'radio', 'checkbox', 'date', 'tel', 'url', 'number' );

	public static function init() {
		add_filter( 'mgm_checkout_validate', array( __CLASS__, 'validate_checkout' ), 10, 3 );
		add_action( 'mgm_checkout_user_ready', array( __CLASS__, 'save_checkout' ), 10, 2 );
		add_action( 'mgm_profile_updated', array( __CLASS__, 'save_profile' ), 10, 2 );
		add_filter( 'mgm_export_member_columns', array( __CLASS__, 'export_columns' ) );
		add_filter( 'mgm_export_member_value', array( __CLASS__, 'export_value' ), 10, 3 );
		if ( is_admin() ) {
			add_action( 'mgm_admin_member_detail', array( __CLASS__, 'member_detail' ) );
			add_action( 'show_user_profile', array( __CLASS__, 'wp_profile' ), 20 );
			add_action( 'edit_user_profile', array( __CLASS__, 'wp_profile' ), 20 );
			add_action( 'personal_options_update', array( __CLASS__, 'wp_profile_save' ) );
			add_action( 'edit_user_profile_update', array( __CLASS__, 'wp_profile_save' ) );
			// Users → Add New.
			add_action( 'user_new_form', array( __CLASS__, 'wp_new_user_form' ) );
			add_action( 'user_register', array( __CLASS__, 'wp_profile_save' ) );
			// Format errors are shown in WordPress' own notice and stop the save (profile, edit user, add user).
			add_action( 'user_profile_update_errors', array( __CLASS__, 'wp_profile_errors' ), 10, 3 );
			// Users → All Users columns.
			add_filter( 'manage_users_columns', array( __CLASS__, 'users_columns' ) );
			add_filter( 'manage_users_custom_column', array( __CLASS__, 'users_column_value' ), 10, 3 );
			add_filter( 'manage_users_sortable_columns', array( __CLASS__, 'users_sortable' ) );
			add_action( 'pre_get_users', array( __CLASS__, 'users_orderby' ) );
		}
	}

	/** @return array[] */
	public static function fields() {
		$fields = get_option( 'mgm_custom_fields', array() );
		return is_array( $fields ) ? array_values( $fields ) : array();
	}

	public static function options_of( $f ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) ( $f['options'] ?? '' ) ) ), 'strlen' ) );
	}

	/**
	 * Fields shown on a given screen (checkout|profile) for an optional membership.
	 */
	public static function for_screen( $screen, $membership_id = 0 ) {
		return array_filter(
			self::fields(),
			function ( $f ) use ( $screen, $membership_id ) {
				$where = $f['show_on'] ?? 'both';
				if ( 'both' !== $where && $where !== $screen ) {
					return false;
				}
				$only = mgm_int_list( $f['memberships'] ?? array() );
				return 'profile' === $screen || ! $only || in_array( (int) $membership_id, $only, true );
			}
		);
	}

	/* ---------------------------- Rendering ---------------------------- */

	public static function input( array $f, $value ) {
		$key  = sanitize_key( $f['key'] );
		$name = 'mgm_field[' . $key . ']';
		$id   = 'mgm-field-' . $key;
		$req  = ! empty( $f['required'] );
		$lab  = esc_html( $f['label'] ) . ( $req ? ' <span class="mgm-field-required" aria-hidden="true">*</span>' : '' );
		$attr = ( $req ? ' required aria-required="true"' : '' ) . ( ! empty( $f['placeholder'] ) ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : '' );
		$out  = '<p class="mgm-custom-field mgm-custom-field-' . esc_attr( $f['type'] ) . '">';
		switch ( $f['type'] ) {
			case 'textarea':
				$out .= '<label for="' . esc_attr( $id ) . '">' . $lab . '</label><textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3"' . $attr . '>' . esc_textarea( (string) $value ) . '</textarea>';
				break;
			case 'select':
				$out .= '<label for="' . esc_attr( $id ) . '">' . $lab . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $attr . '><option value="">' . esc_html__( '— Select —', 'mg-membership' ) . '</option>';
				foreach ( self::options_of( $f ) as $o ) {
					$out .= '<option value="' . esc_attr( $o ) . '" ' . selected( (string) $value, $o, false ) . '>' . esc_html( $o ) . '</option>';
				}
				$out .= '</select>';
				break;
			case 'radio':
				$out .= '<span class="mgm-field-label">' . $lab . '</span>';
				foreach ( self::options_of( $f ) as $i => $o ) {
					$out .= '<label class="mgm-check"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $o ) . '" ' . checked( (string) $value, $o, false ) . ( $req && 0 === $i ? ' required' : '' ) . '> ' . esc_html( $o ) . '</label>';
				}
				break;
			case 'checkbox':
				$out .= '<label class="mgm-check"><input type="hidden" name="' . esc_attr( $name ) . '" value=""><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( '1', (string) $value, false ) . $attr . '> ' . $lab . '</label>';
				break;
			default:
				$type = in_array( $f['type'], array( 'date', 'tel', 'url', 'number' ), true ) ? $f['type'] : 'text';
				$out .= '<label for="' . esc_attr( $id ) . '">' . $lab . '</label><input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . $attr . '>';
		}
		if ( ! empty( $f['help'] ) ) {
			$out .= '<small class="mgm-muted">' . esc_html( $f['help'] ) . '</small>';
		}
		return $out . '</p>';
	}

	public static function posted( $key ) {
		$all = isset( $_POST['mgm_field'] ) ? (array) wp_unslash( $_POST['mgm_field'] ) : array(); // phpcs:ignore
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/* --------------------------- Validation ---------------------------- */

	/**
	 * Validate & sanitize. Returns [clean values, WP_Error].
	 */
	public static function check( array $fields ) {
		$errors = new WP_Error();
		$clean  = array();
		foreach ( $fields as $f ) {
			$raw = self::posted( $f['key'] );
			if ( null === $raw ) {
				continue;
			}
			$raw = is_array( $raw ) ? '' : trim( (string) $raw );
			if ( '' === $raw ) {
				if ( ! empty( $f['required'] ) ) {
					/* translators: %s field label */
					$errors->add( 'mgm_field_' . $f['key'], sprintf( __( '%s is required.', 'mg-membership' ), $f['label'] ) );
				}
				$clean[ $f['key'] ] = '';
				continue;
			}
			switch ( $f['type'] ) {
				case 'url':
					// Format check only (no DNS lookups – wp_http_validate_url() is meant for outgoing requests).
					$v = esc_url_raw( $raw, array( 'http', 'https' ) );
					if ( ! $v || ! filter_var( $v, FILTER_VALIDATE_URL ) || ! preg_match( '#^https?://[^/\s]+\.[^/\s]+#i', $v ) ) {
						$errors->add( 'mgm_field_' . $f['key'], sprintf( __( '%s must be a valid web address.', 'mg-membership' ), $f['label'] ) );
					}
					break;
				case 'number':
					$v = is_numeric( $raw ) ? $raw + 0 : '';
					if ( '' === $v ) {
						$errors->add( 'mgm_field_' . $f['key'], sprintf( __( '%s must be a number.', 'mg-membership' ), $f['label'] ) );
					}
					break;
				case 'date':
					$v = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : '';
					if ( '' === $v ) {
						$errors->add( 'mgm_field_' . $f['key'], sprintf( __( '%s must be a date.', 'mg-membership' ), $f['label'] ) );
					}
					break;
				case 'tel':
					$v = preg_replace( '/[^0-9+\-\s().]/', '', $raw );
					if ( strlen( preg_replace( '/\D/', '', $v ) ) < 5 ) {
						$errors->add( 'mgm_field_' . $f['key'], sprintf( __( '%s must be a valid phone number.', 'mg-membership' ), $f['label'] ) );
					}
					break;
				case 'select':
				case 'radio':
					$v = in_array( $raw, self::options_of( $f ), true ) ? $raw : '';
					if ( '' === $v ) {
						$errors->add( 'mgm_field_' . $f['key'], sprintf( __( 'Please choose a valid option for %s.', 'mg-membership' ), $f['label'] ) );
					}
					break;
				case 'checkbox':
					$v = '1' === $raw ? '1' : '';
					break;
				case 'textarea':
					$v = sanitize_textarea_field( $raw );
					break;
				default:
					$v = sanitize_text_field( $raw );
			}
			$clean[ $f['key'] ] = $v;
		}
		return array( $clean, $errors );
	}

	public static function validate_checkout( $errors, $membership ) {
		$fields = self::for_screen( 'checkout', $membership->id );
		// Missing required fields that weren't posted at all.
		foreach ( $fields as $f ) {
			if ( ! empty( $f['required'] ) && null === self::posted( $f['key'] ) ) {
				$errors->add( 'mgm_field_' . $f['key'], sprintf( __( '%s is required.', 'mg-membership' ), $f['label'] ) );
			}
		}
		list( , $e ) = self::check( $fields );
		foreach ( $e->get_error_messages() as $m ) {
			$errors->add( 'mgm_fields', $m );
		}
		return $errors;
	}

	public static function store( $user_id, array $clean ) {
		foreach ( $clean as $k => $v ) {
			update_user_meta( $user_id, 'mgm_field_' . sanitize_key( $k ), $v );
		}
	}

	public static function save_checkout( $user_id, $membership ) {
		list( $clean ) = self::check( self::for_screen( 'checkout', $membership->id ) );
		self::store( $user_id, $clean );
	}

	public static function save_profile( $user_id ) {
		list( $clean, $errors ) = self::check( self::for_screen( 'profile' ) );
		if ( $errors->has_errors() ) {
			foreach ( $errors->get_error_messages() as $m ) {
				mgm_add_notice( $m );
			}
			// Store only the valid ones.
			foreach ( $errors->get_error_codes() as $code ) {
				unset( $clean[ str_replace( 'mgm_field_', '', $code ) ] );
			}
		}
		self::store( $user_id, $clean );
	}

	/* ------------------------------ Admin ------------------------------ */

	/**
	 * Export columns use the plain field key, so a Members export can be re-imported as-is.
	 */
	public static function export_columns( $cols ) {
		foreach ( self::fields() as $f ) {
			$cols[ $f['key'] ] = $f['label'];
		}
		return $cols;
	}

	public static function export_value( $v, $key, $user_id ) {
		foreach ( self::fields() as $f ) {
			if ( $f['key'] === $key ) {
				return (string) mgm_get_member_field( $user_id, $key );
			}
		}
		return $v;
	}

	public static function member_detail( $user ) {
		$fields = self::fields();
		if ( ! $fields ) {
			return;
		}
		echo '<section class="mgm-card"><h2 class="mgm-card-title">' . esc_html__( 'Profile fields', 'mg-membership' ) . '</h2><dl class="mgm-profile-stats" style="flex-wrap:wrap;gap:18px 32px">';
		foreach ( $fields as $f ) {
			$v = mgm_get_member_field( $user->ID, $f['key'] );
			echo '<div><dt>' . esc_html( $f['label'] ) . '</dt><dd style="font-size:14px;font-weight:600">' . esc_html( 'checkbox' === $f['type'] ? ( $v ? __( 'Yes', 'mg-membership' ) : __( 'No', 'mg-membership' ) ) : ( '' === (string) $v ? '—' : $v ) ) . '</dd></div>';
		}
		echo '</dl></section>';
	}

	/* ------------------------- WordPress Users ------------------------- */

	/** Field shown as a column in Users → All Users (on unless switched off in Forms & Fields). */
	public static function in_users_list( array $f ) {
		return ! isset( $f['admin_column'] ) || '' !== (string) $f['admin_column'];
	}

	/** A field control styled like the rest of the WordPress profile screen. */
	public static function admin_control( array $f, $value ) {
		$key  = sanitize_key( $f['key'] );
		$name = 'mgm_field[' . $key . ']';
		$id   = 'mgm-field-' . $key;
		$ph   = ! empty( $f['placeholder'] ) ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : '';
		switch ( $f['type'] ) {
			case 'textarea':
				$out = '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4" class="large-text"' . $ph . '>' . esc_textarea( (string) $value ) . '</textarea>';
				break;
			case 'select':
				$out = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( '— Select —', 'mg-membership' ) . '</option>';
				foreach ( self::options_of( $f ) as $o ) {
					$out .= '<option value="' . esc_attr( $o ) . '" ' . selected( (string) $value, $o, false ) . '>' . esc_html( $o ) . '</option>';
				}
				$out .= '</select>';
				break;
			case 'radio':
				$out = '<fieldset><legend class="screen-reader-text">' . esc_html( $f['label'] ) . '</legend><input type="hidden" name="' . esc_attr( $name ) . '" value="">';
				foreach ( self::options_of( $f ) as $o ) {
					$out .= '<label><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $o ) . '" ' . checked( (string) $value, $o, false ) . '> ' . esc_html( $o ) . '</label><br>';
				}
				$out .= '</fieldset>';
				break;
			case 'checkbox':
				$out = '<label for="' . esc_attr( $id ) . '"><input type="hidden" name="' . esc_attr( $name ) . '" value=""><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( '1', (string) $value, false ) . '> ' . esc_html__( 'Yes', 'mg-membership' ) . '</label>';
				break;
			default:
				$type = in_array( $f['type'], array( 'date', 'tel', 'url', 'number' ), true ) ? $f['type'] : 'text';
				$out  = '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" class="regular-text"' . $ph . '>';
		}
		if ( ! empty( $f['help'] ) ) {
			$out .= '<p class="description">' . esc_html( $f['help'] ) . '</p>';
		}
		return $out;
	}

	private static function admin_rows( $user_id ) {
		$where = array(
			'both'     => __( 'Registration + profile', 'mg-membership' ),
			'checkout' => __( 'Registration only', 'mg-membership' ),
			'profile'  => __( 'Profile only', 'mg-membership' ),
			'none'     => __( 'Admin only', 'mg-membership' ),
		);
		wp_nonce_field( 'mgm_wp_profile_fields', '_mgm_fields_nonce' );
		echo '<table class="form-table mgm-user-fields" role="presentation">';
		foreach ( self::fields() as $f ) {
			$value = $user_id ? mgm_get_member_field( $user_id, $f['key'] ) : '';
			$label = 'radio' === $f['type'] ? '<span>' . esc_html( $f['label'] ) . '</span>' : '<label for="mgm-field-' . esc_attr( sanitize_key( $f['key'] ) ) . '">' . esc_html( $f['label'] ) . '</label>';
			echo '<tr class="mgm-user-field-' . esc_attr( sanitize_key( $f['key'] ) ) . '"><th>' . $label . ( ! empty( $f['required'] ) ? ' <span class="description">' . esc_html__( '(required for members)', 'mg-membership' ) . '</span>' : '' ) . '</th><td>' . self::admin_control( $f, $value ); // phpcs:ignore -- escaped above / inside.
			echo '<p class="description mgm-user-field-where">' . esc_html( sprintf( __( 'Shown on: %s', 'mg-membership' ), $where[ $f['show_on'] ?? 'both' ] ?? $where['both'] ) ) . '</p></td></tr>';
		}
		echo '</table>';
	}

	private static function section_title() {
		echo '<h2 id="mgm-member-details">' . esc_html__( 'Member details', 'mg-membership' );
		if ( current_user_can( mgm_admin_cap() ) ) {
			echo ' <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=mgm-forms' ) ) . '">' . esc_html__( 'Manage fields', 'mg-membership' ) . '</a>';
		}
		echo '</h2><p class="description">' . esc_html__( 'Extra fields from MG Membership → Forms & Fields. Members fill these in when they sign up or in Account → Profile.', 'mg-membership' ) . '</p>';
	}

	/** Profile + Users → Edit. */
	public static function wp_profile( $user ) {
		if ( ! self::fields() || ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		self::section_title();
		self::admin_rows( $user->ID );
	}

	/** Users → Add New. */
	public static function wp_new_user_form( $type ) {
		if ( 'add-new-user' !== $type || ! self::fields() || ! current_user_can( 'create_users' ) ) {
			return;
		}
		self::section_title();
		self::admin_rows( 0 );
	}

	private static function admin_posted_ok() {
		return isset( $_POST['_mgm_fields_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_mgm_fields_nonce'] ), 'mgm_wp_profile_fields' ); // phpcs:ignore
	}

	/** Admins are not forced to fill "required" fields, but values must be valid. */
	private static function relaxed() {
		return array_map(
			function ( $f ) {
				$f['required'] = false;
				return $f;
			},
			self::fields()
		);
	}

	public static function wp_profile_errors( $errors, $update, $user ) {
		if ( ! self::admin_posted_ok() ) {
			return;
		}
		list( , $e ) = self::check( self::relaxed() );
		foreach ( $e->get_error_messages() as $m ) {
			$errors->add( 'mgm_field', $m );
		}
	}

	public static function wp_profile_save( $user_id ) {
		if ( ! self::admin_posted_ok() || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		list( $clean, $e ) = self::check( self::relaxed() );
		if ( $e->has_errors() ) {
			return; // Shown by wp_profile_errors(); nothing is saved.
		}
		self::store( $user_id, $clean );
	}

	public static function users_columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'email' === $k ) {
				$new['mgm_memberships'] = __( 'Memberships', 'mg-membership' );
				foreach ( self::fields() as $f ) {
					if ( self::in_users_list( $f ) ) {
						$new[ 'mgm_f_' . $f['key'] ] = $f['label'];
					}
				}
			}
		}
		return $new;
	}

	public static function users_column_value( $out, $col, $user_id ) {
		if ( 'mgm_memberships' === $col ) {
			$ids = MGM_Member::active_membership_ids( $user_id );
			return $ids ? esc_html( implode( ', ', array_map( 'get_the_title', $ids ) ) ) : '<span aria-hidden="true">—</span>';
		}
		if ( 0 !== strpos( $col, 'mgm_f_' ) ) {
			return $out;
		}
		$key = substr( $col, 6 );
		foreach ( self::fields() as $f ) {
			if ( $f['key'] !== $key ) {
				continue;
			}
			$v = (string) mgm_get_member_field( $user_id, $key );
			if ( '' === $v ) {
				return '<span aria-hidden="true">—</span>';
			}
			if ( 'checkbox' === $f['type'] ) {
				return esc_html__( 'Yes', 'mg-membership' );
			}
			if ( 'url' === $f['type'] ) {
				return '<a href="' . esc_url( $v ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( preg_replace( '#^https?://#', '', $v ) ) . '</a>';
			}
			if ( 'date' === $f['type'] && strtotime( $v ) ) {
				return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $v ) ) );
			}
			return esc_html( wp_trim_words( $v, 12 ) );
		}
		return $out;
	}

	public static function users_sortable( $cols ) {
		foreach ( self::fields() as $f ) {
			if ( self::in_users_list( $f ) ) {
				$cols[ 'mgm_f_' . $f['key'] ] = 'mgm_f_' . $f['key'];
			}
		}
		return $cols;
	}

	public static function users_orderby( $query ) {
		$by = $query->get( 'orderby' );
		if ( ! is_admin() || ! is_string( $by ) || 0 !== strpos( $by, 'mgm_f_' ) ) {
			return;
		}
		$key = sanitize_key( substr( $by, 6 ) );
		if ( in_array( $key, wp_list_pluck( self::fields(), 'key' ), true ) ) {
			$type = 'number' === ( array_column( self::fields(), 'type', 'key' )[ $key ] ?? '' ) ? 'NUMERIC' : 'CHAR';
			// LEFT JOIN so members without an answer stay in the list.
			$query->set(
				'meta_query',
				array(
					'relation' => 'OR',
					'mgm_sort' => array( 'key' => 'mgm_field_' . $key, 'compare' => 'EXISTS', 'type' => $type ),
					array( 'key' => 'mgm_field_' . $key, 'compare' => 'NOT EXISTS' ),
				)
			);
			$query->set( 'orderby', 'mgm_sort' );
		}
	}

}

