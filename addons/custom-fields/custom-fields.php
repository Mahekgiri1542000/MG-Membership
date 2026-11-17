<?php
/**
 * Add-on: Custom registration & profile fields.
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
		add_action( 'mgm_checkout_fields', array( __CLASS__, 'checkout_fields' ) );
		add_filter( 'mgm_checkout_validate', array( __CLASS__, 'validate_checkout' ), 10, 3 );
		add_action( 'mgm_checkout_user_ready', array( __CLASS__, 'save_checkout' ), 10, 2 );
		add_action( 'mgm_profile_fields', array( __CLASS__, 'profile_fields' ) );
		add_action( 'mgm_profile_updated', array( __CLASS__, 'save_profile' ), 10, 2 );
		add_filter( 'mgm_export_member_columns', array( __CLASS__, 'export_columns' ) );
		add_filter( 'mgm_export_member_value', array( __CLASS__, 'export_value' ), 10, 3 );
		if ( is_admin() ) {
			add_action( 'mgm_admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'admin_post_mgm_save_fields', array( __CLASS__, 'save_fields' ) );
			add_action( 'mgm_admin_member_detail', array( __CLASS__, 'member_detail' ) );
			add_action( 'show_user_profile', array( __CLASS__, 'wp_profile' ), 20 );
			add_action( 'edit_user_profile', array( __CLASS__, 'wp_profile' ), 20 );
			add_action( 'personal_options_update', array( __CLASS__, 'wp_profile_save' ) );
			add_action( 'edit_user_profile_update', array( __CLASS__, 'wp_profile_save' ) );
		}
	}

	/** @return array[] */
	public static function fields() {
		$fields = get_option( 'mgm_custom_fields', array() );
		return is_array( $fields ) ? array_values( $fields ) : array();
	}

	private static function options_of( $f ) {
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

	private static function posted( $key ) {
		$all = isset( $_POST['mgm_field'] ) ? (array) wp_unslash( $_POST['mgm_field'] ) : array(); // phpcs:ignore
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function checkout_fields( $membership ) {
		$user = wp_get_current_user();
		foreach ( self::for_screen( 'checkout', $membership->id ) as $f ) {
			$p = self::posted( $f['key'] );
			echo self::input( $f, null !== $p ? $p : ( $user->ID ? mgm_get_member_field( $user->ID, $f['key'] ) : '' ) ); // phpcs:ignore -- escaped in input().
		}
	}

	public static function profile_fields( $user ) {
		$fields = self::for_screen( 'profile' );
		if ( ! $fields ) {
			return;
		}
		echo '<h3>' . esc_html__( 'More about you', 'mg-membership' ) . '</h3>';
		foreach ( $fields as $f ) {
			echo self::input( $f, mgm_get_member_field( $user->ID, $f['key'] ) ); // phpcs:ignore
		}
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

	private static function store( $user_id, array $clean ) {
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

	public static function wp_profile( $user ) {
		$fields = self::fields();
		if ( ! $fields || ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		echo '<h2>' . esc_html__( 'Membership profile fields', 'mg-membership' ) . '</h2><table class="form-table" role="presentation">';
		wp_nonce_field( 'mgm_wp_profile_fields', '_mgm_fields_nonce' );
		foreach ( $fields as $f ) {
			echo '<tr><td colspan="2">' . self::input( array_merge( $f, array( 'required' => false ) ), mgm_get_member_field( $user->ID, $f['key'] ) ) . '</td></tr>'; // phpcs:ignore
		}
		echo '</table>';
	}

	public static function wp_profile_save( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['_mgm_fields_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_fields_nonce'] ), 'mgm_wp_profile_fields' ) ) {
			return;
		}
		$relaxed = array_map(
			function ( $f ) {
				$f['required'] = false;
				return $f;
			},
			self::fields()
		);
		list( $clean ) = self::check( $relaxed );
		self::store( $user_id, $clean );
	}

	public static function menu( $cap ) {
		add_submenu_page( 'mgm', __( 'Custom Fields', 'mg-membership' ), __( 'Custom Fields', 'mg-membership' ), $cap, 'mgm-fields', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		$fields   = self::fields();
		$fields[] = array( 'key' => '', 'label' => '', 'type' => 'text', 'options' => '', 'required' => '', 'show_on' => 'both', 'public' => '', 'placeholder' => '', 'help' => '', 'memberships' => array() );
		$types    = array(
			'text'     => __( 'Text', 'mg-membership' ),
			'textarea' => __( 'Paragraph', 'mg-membership' ),
			'select'   => __( 'Dropdown', 'mg-membership' ),
			'radio'    => __( 'Radio buttons', 'mg-membership' ),
			'checkbox' => __( 'Checkbox', 'mg-membership' ),
			'date'     => __( 'Date', 'mg-membership' ),
			'tel'      => __( 'Phone', 'mg-membership' ),
			'url'      => __( 'Website', 'mg-membership' ),
			'number'   => __( 'Number', 'mg-membership' ),
		);
		MGM_Admin::header( __( 'Custom Fields', 'mg-membership' ), array( 'desc' => __( 'Extra questions on registration and in the member profile. Values are saved on the member and included in the Members CSV export.', 'mg-membership' ) ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'mgm_save_fields' );
		echo '<input type="hidden" name="action" value="mgm_save_fields"><div class="mgm-table-card"><table class="widefat mgm-table mgm-repeater"><thead><tr><th>' . esc_html__( 'Label', 'mg-membership' ) . '</th><th>' . esc_html__( 'Type', 'mg-membership' ) . '</th><th>' . esc_html__( 'Choices (comma separated)', 'mg-membership' ) . '</th><th>' . esc_html__( 'Show on', 'mg-membership' ) . '</th><th>' . esc_html__( 'Required', 'mg-membership' ) . '</th><th>' . esc_html__( 'Public on profile', 'mg-membership' ) . '</th><th>' . esc_html__( 'Delete', 'mg-membership' ) . '</th></tr></thead><tbody>';
		foreach ( $fields as $i => $f ) {
			$n = 'fields[' . $i . ']';
			echo '<tr><td><input type="hidden" name="' . esc_attr( $n ) . '[key]" value="' . esc_attr( $f['key'] ) . '"><input type="text" name="' . esc_attr( $n ) . '[label]" value="' . esc_attr( $f['label'] ) . '" placeholder="' . esc_attr( '' === $f['key'] ? __( '+ New field label', 'mg-membership' ) : '' ) . '"><input type="text" name="' . esc_attr( $n ) . '[help]" value="' . esc_attr( $f['help'] ?? '' ) . '" placeholder="' . esc_attr__( 'Help text (optional)', 'mg-membership' ) . '" style="margin-top:4px">' . ( $f['key'] ? '<br><small class="mgm-muted">' . esc_html__( 'Key:', 'mg-membership' ) . ' <code>' . esc_html( $f['key'] ) . '</code></small>' : '' ) . '</td>';
			echo '<td><select name="' . esc_attr( $n ) . '[type]">';
			foreach ( $types as $tk => $tl ) {
				echo '<option value="' . esc_attr( $tk ) . '" ' . selected( $f['type'], $tk, false ) . '>' . esc_html( $tl ) . '</option>';
			}
			echo '</select></td><td><input type="text" name="' . esc_attr( $n ) . '[options]" value="' . esc_attr( $f['options'] ?? '' ) . '"></td>';
			echo '<td><select name="' . esc_attr( $n ) . '[show_on]">';
			foreach ( array( 'both' => __( 'Registration + profile', 'mg-membership' ), 'checkout' => __( 'Registration only', 'mg-membership' ), 'profile' => __( 'Profile only', 'mg-membership' ) ) as $sk => $sl ) {
				echo '<option value="' . esc_attr( $sk ) . '" ' . selected( $f['show_on'] ?? 'both', $sk, false ) . '>' . esc_html( $sl ) . '</option>';
			}
			echo '</select></td><td><input type="checkbox" name="' . esc_attr( $n ) . '[required]" value="1" ' . checked( ! empty( $f['required'] ), true, false ) . '></td><td><input type="checkbox" name="' . esc_attr( $n ) . '[public]" value="1" ' . checked( ! empty( $f['public'] ), true, false ) . '></td><td>' . ( $f['key'] ? '<input type="checkbox" name="' . esc_attr( $n ) . '[delete]" value="1">' : '' ) . '</td></tr>';
		}
		echo '</tbody></table></div><p class="mgm-muted">' . esc_html__( 'Fill in the last row to add a field. Save to add another.', 'mg-membership' ) . '</p><div class="mgm-savebar">';
		submit_button( __( 'Save fields', 'mg-membership' ), 'primary mgm-btn-submit', 'submit', false );
		echo '</div></form>';
		MGM_Admin::footer();
	}

	public static function save_fields() {
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mg-membership' ) );
		}
		check_admin_referer( 'mgm_save_fields' );
		$in    = isset( $_POST['fields'] ) ? (array) wp_unslash( $_POST['fields'] ) : array(); // phpcs:ignore
		$out   = array();
		// Reserved: WordPress profile keys and the core CSV import/export columns.
		$taken = array( 'first_name', 'last_name', 'user_email', 'user_login', 'user_pass', 'description', 'nickname', 'email', 'username', 'membership', 'expires', 'user_id', 'name', 'active_memberships', 'lifetime_value', 'registered' );
		foreach ( $in as $f ) {
			$label = sanitize_text_field( $f['label'] ?? '' );
			if ( '' === $label || ! empty( $f['delete'] ) ) {
				continue;
			}
			$key = sanitize_key( $f['key'] ?? '' );
			if ( '' === $key ) {
				$key  = sanitize_key( str_replace( '-', '_', sanitize_title( $label ) ) );
				$base = $key ? $key : 'field';
				$key  = $base;
				$i    = 2;
				while ( in_array( $key, $taken, true ) || isset( $out[ $key ] ) ) {
					$key = $base . '_' . $i++;
				}
			}
			$type        = in_array( $f['type'] ?? '', self::TYPES, true ) ? $f['type'] : 'text';
			$out[ $key ] = array(
				'key'         => $key,
				'label'       => $label,
				'type'        => $type,
				'options'     => sanitize_text_field( $f['options'] ?? '' ),
				'required'    => empty( $f['required'] ) ? '' : '1',
				'public'      => empty( $f['public'] ) ? '' : '1',
				'show_on'     => in_array( $f['show_on'] ?? '', array( 'both', 'checkout', 'profile' ), true ) ? $f['show_on'] : 'both',
				'placeholder' => sanitize_text_field( $f['placeholder'] ?? '' ),
				'help'        => sanitize_text_field( $f['help'] ?? '' ),
				'memberships' => mgm_int_list( $f['memberships'] ?? array() ),
			);
		}
		update_option( 'mgm_custom_fields', array_values( $out ) );
		wp_safe_redirect( admin_url( 'admin.php?page=mgm-fields&mgm_msg=' . rawurlencode( __( 'Fields saved.', 'mg-membership' ) ) ) );
		exit;
	}
}

MGM_Custom_Fields::init();
