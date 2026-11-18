<?php
/**
 * Form builder for the Registration (checkout), Account profile and Login forms.
 *
 * Built-in fields (name, email, password…) can be renamed, reordered, resized, made required or hidden.
 * Custom fields (MGM_Custom_Fields) are added, edited and deleted from the same screen: MG Membership → Forms.
 *
 * Developers: filter `mgm_form_fields` changes the final field list, `mgm_form_builtins` the built-in
 * definitions and `mgm_form_settings_defaults` the default texts.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Forms {

	const OPTION = 'mgm_forms';

	public static function init() {
		add_filter( 'mgm_checkout_validate', array( __CLASS__, 'validate_checkout' ), 5, 2 );
		if ( is_admin() ) {
			add_action( 'mgm_admin_menu', array( __CLASS__, 'menu' ), 1 );
			add_action( 'admin_post_mgm_save_forms', array( __CLASS__, 'save' ) );
			add_action( 'admin_init', array( __CLASS__, 'old_page_redirect' ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Definitions                                                         */
	/* ------------------------------------------------------------------ */

	public static function forms() {
		return array(
			'checkout' => array( __( 'Registration', 'mg-membership' ), __( 'The sign-up form on every membership page.', 'mg-membership' ), 'dashicons-id-alt' ),
			'profile'  => array( __( 'Account profile', 'mg-membership' ), __( 'Account → Profile, where members edit their details.', 'mg-membership' ), 'dashicons-admin-users' ),
			'login'    => array( __( 'Login', 'mg-membership' ), __( 'The login and forgotten-password forms.', 'mg-membership' ), 'dashicons-lock' ),
		);
	}

	/**
	 * Built-in fields. `lock` lists the switches that cannot change (e.g. the email is always shown and required).
	 */
	public static function builtins( $form ) {
		$b = array(
			'checkout' => array(
				'first_name'        => array( 'label' => __( 'First name', 'mg-membership' ), 'width' => 'half', 'auto' => 'given-name' ),
				'last_name'         => array( 'label' => __( 'Last name', 'mg-membership' ), 'width' => 'half', 'auto' => 'family-name' ),
				'user_email'        => array( 'label' => __( 'Email', 'mg-membership' ), 'type' => 'email', 'required' => '1', 'lock' => array( 'hidden', 'required' ), 'account' => true, 'auto' => 'email' ),
				'user_login'        => array( 'label' => __( 'Username', 'mg-membership' ), 'placeholder' => __( 'Optional – generated from email', 'mg-membership' ), 'account' => true, 'auto' => 'username' ),
				'user_pass'         => array( 'label' => __( 'Password', 'mg-membership' ), 'type' => 'password', 'width' => 'half', 'required' => '1', 'lock' => array( 'hidden', 'required' ), 'account' => true, 'auto' => 'new-password', 'min' => 8 ),
				'user_pass_confirm' => array( 'label' => __( 'Confirm password', 'mg-membership' ), 'type' => 'password', 'width' => 'half', 'required' => '1', 'lock' => array( 'required' ), 'account' => true, 'auto' => 'new-password', 'min' => 8 ),
			),
			'profile'  => array(
				'first_name'   => array( 'label' => __( 'First name', 'mg-membership' ), 'width' => 'half', 'auto' => 'given-name' ),
				'last_name'    => array( 'label' => __( 'Last name', 'mg-membership' ), 'width' => 'half', 'auto' => 'family-name' ),
				'display_name' => array( 'label' => __( 'Display name', 'mg-membership' ), 'width' => 'half' ),
				'user_email'   => array( 'label' => __( 'Email', 'mg-membership' ), 'type' => 'email', 'width' => 'half', 'required' => '1', 'lock' => array( 'hidden', 'required' ), 'auto' => 'email' ),
				'new_pass'     => array( 'label' => __( 'New password', 'mg-membership' ), 'type' => 'password', 'width' => 'half', 'help' => __( 'At least 8 characters. Leave blank to keep your current password.', 'mg-membership' ), 'lock' => array( 'required' ), 'group' => 'security', 'auto' => 'new-password', 'min' => 8 ),
				'current_pass' => array( 'label' => __( 'Current password', 'mg-membership' ), 'type' => 'password', 'width' => 'half', 'help' => __( 'Needed only to change your email or password.', 'mg-membership' ), 'lock' => array( 'hidden', 'required' ), 'group' => 'security', 'auto' => 'current-password' ),
			),
			'login'    => array(
				'log'        => array( 'label' => __( 'Username or email', 'mg-membership' ), 'required' => '1', 'lock' => array( 'hidden', 'required' ), 'auto' => 'username' ),
				'pwd'        => array( 'label' => __( 'Password', 'mg-membership' ), 'type' => 'password', 'required' => '1', 'lock' => array( 'hidden', 'required' ), 'auto' => 'current-password' ),
				'lost_login' => array( 'label' => __( 'Username or email (forgotten password form)', 'mg-membership' ), 'required' => '1', 'lock' => array( 'hidden', 'required' ), 'group' => 'lost', 'auto' => 'username', 'name' => 'user_login' ),
			),
		);
		$list = apply_filters( 'mgm_form_builtins', $b[ $form ] ?? array(), $form );
		foreach ( $list as $k => $f ) {
			$list[ $k ] = array_merge(
				array(
					'key'         => $k,
					'label'       => $k,
					'type'        => 'text',
					'placeholder' => '',
					'help'        => '',
					'width'       => 'full',
					'required'    => '',
					'hidden'      => '',
					'class'       => '',
					'lock'        => array(),
					'group'       => 'main',
					'builtin'     => true,
				),
				$f
			);
		}
		return $list;
	}

	public static function settings_defaults( $form ) {
		$d = array(
			'checkout' => array(
				'login_link' => '1',
			),
			'profile'  => array(
				'personal_title' => __( 'Personal details', 'mg-membership' ),
				'security_title' => __( 'Login & security', 'mg-membership' ),
				'button'         => __( 'Save changes', 'mg-membership' ),
			),
			'login'    => array(
				'title'         => __( 'Welcome back', 'mg-membership' ),
				'intro'         => __( 'Log in to your account to continue.', 'mg-membership' ),
				'button'        => __( 'Log in', 'mg-membership' ),
				'remember'      => '1',
				'lost_link'     => '1',
				'lost_text'     => __( 'Forgot password?', 'mg-membership' ),
				'register'      => '1',
				'register_text' => __( 'Don\'t have an account?', 'mg-membership' ),
				'register_link' => __( 'Join now', 'mg-membership' ),
				'register_url'  => '',
				'lost_title'    => __( 'Reset your password', 'mg-membership' ),
				'lost_intro'    => __( 'Enter your username or email and we will send you a link to choose a new password.', 'mg-membership' ),
				'lost_button'   => __( 'Email me a reset link', 'mg-membership' ),
				'back_text'     => __( 'Back to login', 'mg-membership' ),
			),
		);
		return apply_filters( 'mgm_form_settings_defaults', $d[ $form ] ?? array(), $form );
	}

	public static function config() {
		$c = get_option( self::OPTION, array() );
		return is_array( $c ) ? $c : array();
	}

	public static function setting( $form, $key ) {
		$c = self::config();
		$d = self::settings_defaults( $form );
		$v = $c[ $form ]['settings'][ $key ] ?? '';
		return '' === (string) $v ? ( $d[ $key ] ?? '' ) : $v;
	}

	public static function on( $form, $key ) {
		return '1' === (string) self::setting( $form, $key );
	}

	/** Custom fields are "cf:<key>" in the form order. */
	private static function custom_for( $form ) {
		if ( 'login' === $form || ! class_exists( 'MGM_Custom_Fields' ) ) {
			return array();
		}
		$out = array();
		foreach ( MGM_Custom_Fields::fields() as $f ) {
			$where              = $f['show_on'] ?? 'both';
			$f['hidden']        = ( 'both' === $where || $where === $form ) ? '' : '1';
			$f['builtin']       = false;
			$f['group']         = 'main';
			$f['lock']          = array();
			$out[ 'cf:' . $f['key'] ] = $f;
		}
		return $out;
	}

	/**
	 * Every field of a form in the saved order, with saved overrides applied.
	 *
	 * @param string $form          checkout|profile|login
	 * @param bool   $include_hidden Also return hidden fields (admin screen).
	 * @param int    $membership_id  Checkout: drop custom fields limited to other memberships.
	 */
	public static function fields( $form, $include_hidden = false, $membership_id = 0 ) {
		$cfg  = self::config()[ $form ] ?? array();
		$over = (array) ( $cfg['fields'] ?? array() );
		$all  = self::builtins( $form ) + self::custom_for( $form );
		$ids  = array_values( array_unique( array_merge( array_intersect( (array) ( $cfg['order'] ?? array() ), array_keys( $all ) ), array_keys( $all ) ) ) );

		$out = array();
		foreach ( $ids as $id ) {
			$f       = $all[ $id ];
			$f['id'] = $id;
			$o       = (array) ( $over[ $id ] ?? array() );
			foreach ( array( 'label', 'placeholder', 'help' ) as $k ) {
				if ( $f['builtin'] && isset( $o[ $k ] ) && '' !== (string) $o[ $k ] ) {
					$f[ $k ] = $o[ $k ];
				}
			}
			foreach ( array( 'required', 'hidden' ) as $k ) {
				if ( $f['builtin'] && isset( $o[ $k ] ) && ! in_array( $k, $f['lock'], true ) ) {
					$f[ $k ] = $o[ $k ] ? '1' : '';
				}
			}
			$f['width'] = in_array( $o['width'] ?? '', array( 'full', 'half' ), true ) ? $o['width'] : ( $f['builtin'] ? $f['width'] : 'full' );
			$f['class'] = (string) ( $o['class'] ?? '' );
			if ( ! $include_hidden && $f['hidden'] ) {
				continue;
			}
			if ( ! $f['builtin'] && $membership_id && 'checkout' === $form ) {
				$only = mgm_int_list( $f['memberships'] ?? array() );
				if ( $only && ! in_array( (int) $membership_id, $only, true ) ) {
					continue;
				}
			}
			$out[ $id ] = $f;
		}
		return apply_filters( 'mgm_form_fields', $out, $form, $include_hidden );
	}

	public static function visible( $form, $id ) {
		$f = self::fields( $form, true );
		return isset( $f[ $id ] ) && ! $f[ $id ]['hidden'];
	}

	/* ------------------------------------------------------------------ */
	/* Front end                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Print a form's fields.
	 *
	 * @param array $ctx user (WP_User|null), membership (MGM_Membership|null), group (main|security|lost).
	 */
	public static function render( $form, array $ctx = array() ) {
		$user      = $ctx['user'] ?? null;
		$logged_in = $user && $user->ID;
		$group     = $ctx['group'] ?? 'main';
		$mid       = isset( $ctx['membership'] ) && $ctx['membership'] ? (int) $ctx['membership']->id : 0;

		echo '<div class="mgm-form-grid mgm-form-grid-' . esc_attr( $form ) . '">';
		foreach ( self::fields( $form, false, $mid ) as $f ) {
			if ( $f['group'] !== $group ) {
				continue;
			}
			if ( 'checkout' === $form && ! empty( $f['account'] ) && $logged_in ) {
				continue; // Logged-in buyers already have an account.
			}
			$classes = 'mgm-field mgm-w-' . $f['width'] . ' mgm-field-' . sanitize_html_class( $f['key'] );
			foreach ( preg_split( '/\s+/', trim( $f['class'] ) ) as $c ) {
				$classes .= $c ? ' ' . sanitize_html_class( $c ) : '';
			}
			echo '<div class="' . esc_attr( $classes ) . '">';
			if ( $f['builtin'] ) {
				echo self::builtin_input( $form, $f, $user ); // phpcs:ignore -- escaped inside.
			} else {
				$posted = MGM_Custom_Fields::posted( $f['key'] );
				$value  = null !== $posted ? $posted : ( $logged_in ? mgm_get_member_field( $user->ID, $f['key'] ) : '' );
				echo MGM_Custom_Fields::input( $f, $value ); // phpcs:ignore -- escaped inside.
			}
			echo '</div>';
		}
		echo '</div>';
	}

	private static function builtin_input( $form, array $f, $user ) {
		$name  = $f['name'] ?? $f['key'];
		$id    = 'mgm-' . $form . '-' . $f['key'];
		$value = '';
		if ( 'password' !== $f['type'] ) {
			if ( isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] ) ) { // phpcs:ignore
				$value = sanitize_text_field( wp_unslash( $_POST[ $name ] ) ); // phpcs:ignore
			} elseif ( $user && $user->ID && isset( $user->{$name} ) ) {
				$value = $user->{$name};
			}
		}
		$req  = ! empty( $f['required'] );
		$html = '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . ( $req ? ' <span class="mgm-field-required" aria-hidden="true">*</span>' : '' ) . '</label>';
		$html .= sprintf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s%7$s%8$s>',
			esc_attr( $f['type'] ),
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( (string) $value ),
			$f['placeholder'] ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : '',
			$req ? ' required aria-required="true"' : '',
			! empty( $f['auto'] ) ? ' autocomplete="' . esc_attr( $f['auto'] ) . '"' : '',
			! empty( $f['min'] ) ? ' minlength="' . (int) $f['min'] . '"' : ''
		);
		if ( $f['help'] ) {
			$html .= '<small class="mgm-hint">' . esc_html( $f['help'] ) . '</small>';
		}
		return $html . '</p>';
	}

	/** Required built-in fields (custom fields validate themselves). */
	public static function validate( $form, $logged_in = false ) {
		$errors = new WP_Error();
		foreach ( self::fields( $form ) as $f ) {
			if ( ! $f['builtin'] || ! $f['required'] || in_array( 'required', $f['lock'], true ) ) {
				continue;
			}
			if ( 'checkout' === $form && ! empty( $f['account'] ) && $logged_in ) {
				continue;
			}
			$name = $f['name'] ?? $f['key'];
			if ( '' === trim( (string) wp_unslash( $_POST[ $name ] ?? '' ) ) ) { // phpcs:ignore
				/* translators: %s: field label */
				$errors->add( 'mgm_' . $f['key'], sprintf( __( '%s is required.', 'mg-membership' ), $f['label'] ) );
			}
		}
		return $errors;
	}

	public static function validate_checkout( $errors, $membership ) {
		foreach ( self::validate( 'checkout', is_user_logged_in() )->get_error_messages() as $m ) {
			$errors->add( 'mgm_form', $m );
		}
		return $errors;
	}

	/* ------------------------------------------------------------------ */
	/* Admin screen                                                        */
	/* ------------------------------------------------------------------ */

	public static function menu( $cap ) {
		add_submenu_page( 'mgm', __( 'Forms & Fields', 'mg-membership' ), __( 'Forms & Fields', 'mg-membership' ), $cap, 'mgm-forms', array( __CLASS__, 'page' ) );
	}

	/** The old "Custom Fields" screen now lives here. */
	public static function old_page_redirect() {
		if ( isset( $_GET['page'] ) && 'mgm-fields' === $_GET['page'] ) { // phpcs:ignore
			wp_safe_redirect( admin_url( 'admin.php?page=mgm-forms' ) );
			exit;
		}
	}

	public static function types() {
		return array(
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
	}

	private static function preview_url( $form ) {
		if ( 'login' === $form ) {
			return mgm_page_url( 'login' );
		}
		if ( 'profile' === $form ) {
			return MGM_Account::tab_url( 'profile' );
		}
		return mgm_page_url( 'pricing' );
	}

	public static function page() {
		$forms   = self::forms();
		$current = isset( $_GET['form'] ) ? sanitize_key( $_GET['form'] ) : 'checkout'; // phpcs:ignore
		$current = isset( $forms[ $current ] ) ? $current : 'checkout';

		MGM_Admin::header(
			__( 'Forms & Fields', 'mg-membership' ),
			array(
				'desc'    => __( 'Choose which fields appear on the registration, profile and login forms, in what order, and what they say. Add your own fields too.', 'mg-membership' ),
				'actions' => array(
					array(
						'label' => __( 'View form', 'mg-membership' ),
						'url'   => self::preview_url( $current ),
						'icon'  => 'dashicons-external',
					),
				),
			)
		);

		echo '<div class="mgm-settings"><nav class="mgm-settings-nav" aria-label="' . esc_attr__( 'Forms', 'mg-membership' ) . '">';
		foreach ( $forms as $key => $f ) {
			printf(
				'<a href="%1$s" class="%2$s"%3$s><span class="dashicons %4$s" aria-hidden="true"></span><span><strong>%5$s</strong><small>%6$s</small></span></a>',
				esc_url( admin_url( 'admin.php?page=mgm-forms&form=' . $key ) ),
				$key === $current ? 'is-active' : '',
				$key === $current ? ' aria-current="page"' : '',
				esc_attr( $f[2] ),
				esc_html( $f[0] ),
				esc_html( $f[1] )
			);
		}
		echo '</nav><form class="mgm-settings-main" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'mgm_save_forms' );
		echo '<input type="hidden" name="action" value="mgm_save_forms"><input type="hidden" name="form" value="' . esc_attr( $current ) . '">';

		echo '<section class="mgm-card mgm-settings-card"><div class="mgm-fb-top"><div><h2 class="mgm-card-title">' . esc_html__( 'Fields', 'mg-membership' ) . '</h2>';
		echo '<p class="mgm-muted">' . esc_html__( 'Drag ☰ to reorder. Click a field to change its label, placeholder, help text, width and more.', 'mg-membership' ) . '</p></div>';
		if ( 'login' !== $current ) {
			echo '<button type="button" class="button button-primary mgm-fb-add"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' . esc_html__( 'Add field', 'mg-membership' ) . '</button>';
		}
		echo '</div><div class="mgm-fb-list" data-form="' . esc_attr( $current ) . '">';
		$i = 0;
		foreach ( self::fields( $current, true ) as $f ) {
			self::item( $current, $f, 'f' . ( $i++ ) );
		}
		echo '</div>';
		if ( 'login' !== $current ) {
			echo '<template id="mgm-fb-template">';
			self::item(
				$current,
				array(
					'id'          => 'new',
					'key'         => '',
					'label'       => '',
					'type'        => 'text',
					'placeholder' => '',
					'help'        => '',
					'width'       => 'full',
					'required'    => '',
					'hidden'      => '',
					'class'       => '',
					'lock'        => array(),
					'builtin'     => false,
					'options'     => '',
					'public'      => '',
					'memberships' => array(),
				),
				'__N__',
				true
			);
			echo '</template>';
			echo '<p class="mgm-muted mgm-fb-note">' . esc_html__( 'Custom fields are shared by the Registration and Account profile forms: their answers are saved on the member, shown in Members and included in the CSV export. Use "Show on this form" to choose where each one appears. They also appear in Users → Profile / Edit User / Add New, and as columns in Users → All Users.', 'mg-membership' ) . '</p>';
		}
		echo '</section>';

		self::settings_card( $current );

		echo '<div class="mgm-savebar"><span class="mgm-muted">' . esc_html__( 'Changes apply as soon as you save.', 'mg-membership' ) . '</span>';
		printf(
			'<button type="submit" name="mgm_reset" value="1" class="button mgm-btn-reset" onclick="return confirm(&quot;%s&quot;);">%s</button> ',
			esc_js( __( 'Reset the order, labels and texts of this form to the defaults? Your custom fields are kept.', 'mg-membership' ) ),
			esc_html__( 'Reset form', 'mg-membership' )
		);
		submit_button( __( 'Save form', 'mg-membership' ), 'primary mgm-btn-submit', 'submit', false );
		echo '</div></form></div>';
		MGM_Admin::footer();
	}

	/** One sortable field row. */
	private static function item( $form, array $f, $idx, $is_new = false ) {
		$n       = 'items[' . $idx . ']';
		$builtin = ! empty( $f['builtin'] );
		$lock    = (array) $f['lock'];
		$types   = self::types();
		$badge   = $builtin ? __( 'Built-in', 'mg-membership' ) : ( $types[ $f['type'] ] ?? $f['type'] );

		echo '<div class="mgm-fb-item' . ( $f['hidden'] ? ' is-off' : '' ) . ( $is_new ? ' is-open is-new' : '' ) . '">';
		echo '<input type="hidden" name="' . esc_attr( $n ) . '[id]" value="' . esc_attr( $f['id'] ) . '">';
		echo '<div class="mgm-fb-head">';
		echo '<span class="mgm-fb-handle dashicons dashicons-menu" title="' . esc_attr__( 'Drag to reorder', 'mg-membership' ) . '" aria-hidden="true"></span>';
		echo '<button type="button" class="mgm-fb-toggle" aria-expanded="' . ( $is_new ? 'true' : 'false' ) . '"><strong class="mgm-fb-label">' . esc_html( '' !== $f['label'] ? $f['label'] : __( 'New field', 'mg-membership' ) ) . '</strong>';
		echo '<span class="mgm-fb-badge' . ( $builtin ? ' is-builtin' : '' ) . '">' . esc_html( $badge ) . '</span>';
		if ( $f['required'] ) {
			echo '<span class="mgm-fb-badge is-req">' . esc_html__( 'Required', 'mg-membership' ) . '</span>';
		}
		echo '<span class="mgm-fb-badge is-width">' . esc_html( 'half' === $f['width'] ? __( 'Half width', 'mg-membership' ) : __( 'Full width', 'mg-membership' ) ) . '</span>';
		if ( 'security' === ( $f['group'] ?? '' ) ) {
			echo '<span class="mgm-fb-badge">' . esc_html( self::setting( 'profile', 'security_title' ) ) . '</span>';
		}
		echo '<span class="dashicons dashicons-arrow-down-alt2 mgm-fb-caret" aria-hidden="true"></span></button>';

		// Visibility switch.
		$vis_locked = in_array( 'hidden', $lock, true );
		echo '<label class="mgm-switch mgm-fb-visible" title="' . esc_attr__( 'Show on this form', 'mg-membership' ) . '">';
		if ( $vis_locked ) {
			echo '<input type="hidden" name="' . esc_attr( $n ) . '[visible]" value="1"><input type="checkbox" checked disabled>';
		} else {
			echo '<input type="hidden" name="' . esc_attr( $n ) . '[visible]" value="0"><input type="checkbox" name="' . esc_attr( $n ) . '[visible]" value="1" ' . checked( ! $f['hidden'], true, false ) . '>';
		}
		echo '<span class="mgm-switch-ui" aria-hidden="true"></span><span class="mgm-switch-label">' . esc_html__( 'Show on this form', 'mg-membership' ) . '</span></label>';
		echo '</div>';

		// Details.
		echo '<div class="mgm-fb-body"><div class="mgm-fb-grid">';
		self::text( $n . '[label]', __( 'Label', 'mg-membership' ), $f['label'], '', 'mgm-fb-label-input' . ( $builtin ? '' : ' is-required-input' ) );
		if ( $builtin || in_array( $f['type'], array( 'text', 'textarea', 'tel', 'url', 'number', 'date' ), true ) || $is_new ) {
			self::text( $n . '[placeholder]', __( 'Placeholder', 'mg-membership' ), $f['placeholder'], __( 'Grey hint inside the box', 'mg-membership' ) );
		}
		self::text( $n . '[help]', __( 'Help text', 'mg-membership' ), $f['help'], __( 'Small text under the field', 'mg-membership' ) );
		echo '<p><label>' . esc_html__( 'Width', 'mg-membership' ) . '</label><select name="' . esc_attr( $n ) . '[width]"><option value="full" ' . selected( $f['width'], 'full', false ) . '>' . esc_html__( 'Full width', 'mg-membership' ) . '</option><option value="half" ' . selected( $f['width'], 'half', false ) . '>' . esc_html__( 'Half width (two per row)', 'mg-membership' ) . '</option></select></p>';
		self::text( $n . '[class]', __( 'CSS class (for developers)', 'mg-membership' ), $f['class'], 'my-field' );

		if ( ! $builtin ) {
			echo '<p><label>' . esc_html__( 'Field type', 'mg-membership' ) . '</label><select name="' . esc_attr( $n ) . '[type]" class="mgm-fb-type">';
			foreach ( $types as $tk => $tl ) {
				echo '<option value="' . esc_attr( $tk ) . '" ' . selected( $f['type'], $tk, false ) . '>' . esc_html( $tl ) . '</option>';
			}
			echo '</select></p>';
			self::text( $n . '[options]', __( 'Choices (comma separated)', 'mg-membership' ), $f['options'] ?? '', __( 'Small, Medium, Large', 'mg-membership' ), 'mgm-fb-choices' );
		}
		echo '</div><div class="mgm-fb-switches">';
		if ( in_array( 'required', $lock, true ) ) {
			if ( $f['required'] ) {
				echo '<span class="mgm-muted"><span class="dashicons dashicons-lock" aria-hidden="true"></span> ' . esc_html__( 'Always required', 'mg-membership' ) . '</span>';
			}
		} else {
			self::switch_box( $n . '[required]', __( 'Required', 'mg-membership' ), $f['required'] );
		}
		if ( ! $builtin ) {
			self::switch_box( $n . '[public]', __( 'Show on the public member profile (Directory add-on)', 'mg-membership' ), $f['public'] ?? '' );
			self::switch_box( $n . '[admin_column]', __( 'Show as a column in Users → All Users', 'mg-membership' ), $is_new || MGM_Custom_Fields::in_users_list( $f ) );
		}
		echo '</div>';

		if ( ! $builtin && 'checkout' === $form ) {
			$plans = get_posts( array( 'post_type' => 'mgm_membership', 'post_status' => 'publish', 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
			if ( $plans ) {
				$sel = mgm_int_list( $f['memberships'] ?? array() );
				echo '<fieldset class="mgm-fb-plans"><legend>' . esc_html__( 'Only ask on these membership sign-ups (none ticked = all)', 'mg-membership' ) . '</legend>';
				foreach ( $plans as $p ) {
					echo '<label><input type="checkbox" name="' . esc_attr( $n ) . '[memberships][]" value="' . (int) $p->ID . '" ' . checked( in_array( (int) $p->ID, $sel, true ), true, false ) . '> ' . esc_html( $p->post_title ) . '</label>';
				}
				echo '</fieldset>';
			}
		}

		echo '<div class="mgm-fb-foot">';
		if ( $builtin ) {
			echo '<span class="mgm-muted">' . esc_html__( 'Field name:', 'mg-membership' ) . ' <code>' . esc_html( $f['name'] ?? $f['key'] ) . '</code></span>';
		} elseif ( $f['key'] ) {
			echo '<span class="mgm-muted">' . esc_html__( 'Field key:', 'mg-membership' ) . ' <code>' . esc_html( $f['key'] ) . '</code> · ' . esc_html__( 'PHP:', 'mg-membership' ) . ' <code>mgm_get_member_field( $user_id, \'' . esc_html( $f['key'] ) . '\' )</code></span>';
			echo '<label class="mgm-fb-delete"><input type="checkbox" name="' . esc_attr( $n ) . '[delete]" value="1"> ' . esc_html__( 'Delete this field everywhere', 'mg-membership' ) . '</label>';
		} else {
			echo '<span class="mgm-muted">' . esc_html__( 'The field key is created from the label when you save.', 'mg-membership' ) . '</span><button type="button" class="button-link mgm-fb-remove">' . esc_html__( 'Remove', 'mg-membership' ) . '</button>';
		}
		echo '</div></div></div>';
	}

	private static function text( $name, $label, $value, $placeholder = '', $class = '' ) {
		echo '<p><label>' . esc_html( $label ) . '</label><input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" class="' . esc_attr( $class ) . '"></p>';
	}

	private static function switch_box( $name, $label, $on ) {
		echo '<label class="mgm-switch"><input type="hidden" name="' . esc_attr( $name ) . '" value="0"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( (bool) $on, true, false ) . '><span class="mgm-switch-ui" aria-hidden="true"></span><span class="mgm-switch-label">' . esc_html( $label ) . '</span></label>';
	}

	private static function settings_card( $form ) {
		$labels = array(
			'login_link'     => array( 'checkbox', __( 'Show "Already have an account? Log in" under the account fields', 'mg-membership' ) ),
			'personal_title' => array( 'text', __( 'Title of the first box', 'mg-membership' ) ),
			'security_title' => array( 'text', __( 'Title of the password box', 'mg-membership' ) ),
			'button'         => array( 'text', __( 'Button text', 'mg-membership' ) ),
			'title'          => array( 'text', __( 'Heading', 'mg-membership' ) ),
			'intro'          => array( 'text', __( 'Text under the heading', 'mg-membership' ) ),
			'remember'       => array( 'checkbox', __( 'Show "Remember me"', 'mg-membership' ) ),
			'lost_link'      => array( 'checkbox', __( 'Show the "Forgot password?" link', 'mg-membership' ) ),
			'lost_text'      => array( 'text', __( '"Forgot password?" link text', 'mg-membership' ) ),
			'register'       => array( 'checkbox', __( 'Show a sign-up link under the form', 'mg-membership' ) ),
			'register_text'  => array( 'text', __( 'Sign-up line text', 'mg-membership' ) ),
			'register_link'  => array( 'text', __( 'Sign-up link text', 'mg-membership' ) ),
			'register_url'   => array( 'text', __( 'Sign-up link address (blank = your plans page)', 'mg-membership' ) ),
			'lost_title'     => array( 'text', __( 'Forgotten password: heading', 'mg-membership' ) ),
			'lost_intro'     => array( 'text', __( 'Forgotten password: text', 'mg-membership' ) ),
			'lost_button'    => array( 'text', __( 'Forgotten password: button', 'mg-membership' ) ),
			'back_text'      => array( 'text', __( '"Back to login" link text', 'mg-membership' ) ),
		);
		$defaults = self::settings_defaults( $form );
		if ( ! $defaults ) {
			return;
		}
		echo '<section class="mgm-card mgm-settings-card"><h2 class="mgm-card-title">' . esc_html__( 'Texts & options', 'mg-membership' ) . '</h2><div class="mgm-fields-grid">';
		foreach ( $defaults as $key => $default ) {
			$l    = $labels[ $key ] ?? array( 'text', $key );
			$name = 'settings[' . $key . ']';
			$val  = self::setting( $form, $key );
			if ( 'checkbox' === $l[0] ) {
				echo '<div class="mgm-field-row mgm-field-toggle"><input type="hidden" name="' . esc_attr( $name ) . '" value="0"><label class="mgm-switch"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( '1', (string) $val, false ) . '><span class="mgm-switch-ui" aria-hidden="true"></span><span class="mgm-switch-label">' . esc_html( $l[1] ) . '</span></label></div>';
			} else {
				echo '<div class="mgm-field-row"><label class="mgm-field-label" for="mgm-fs-' . esc_attr( $key ) . '">' . esc_html( $l[1] ) . '</label><div class="mgm-field-control"><input type="text" id="mgm-fs-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $default ) . '" class="regular-text"></div></div>';
			}
		}
		if ( 'login' === $form ) {
			echo '<p class="description">' . wp_kses_post( sprintf( __( 'Magic-link login is switched on or off in <a href="%s">Settings → General</a>. Colours and fonts follow <a href="%s">Settings → Appearance</a>.', 'mg-membership' ), esc_url( admin_url( 'admin.php?page=mgm-settings&tab=general' ) ), esc_url( admin_url( 'admin.php?page=mgm-settings&tab=appearance' ) ) ) ) . '</p>';
		}
		echo '</div></section>';
	}

	public static function save() {
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mg-membership' ) );
		}
		check_admin_referer( 'mgm_save_forms' );
		$form = sanitize_key( $_POST['form'] ?? 'checkout' );
		if ( ! isset( self::forms()[ $form ] ) ) {
			wp_die( esc_html__( 'Unknown form.', 'mg-membership' ) );
		}
		$cfg  = self::config();
		$back = admin_url( 'admin.php?page=mgm-forms&form=' . $form );

		if ( ! empty( $_POST['mgm_reset'] ) ) {
			unset( $cfg[ $form ] );
			update_option( self::OPTION, $cfg );
			wp_safe_redirect( $back . '&mgm_msg=' . rawurlencode( __( 'Form reset to defaults.', 'mg-membership' ) ) );
			exit;
		}

		$items    = isset( $_POST['items'] ) ? (array) wp_unslash( $_POST['items'] ) : array(); // phpcs:ignore
		$builtins = self::builtins( $form );
		$defs     = array();
		foreach ( MGM_Custom_Fields::fields() as $d ) {
			$defs[ $d['key'] ] = $d;
		}
		// Reserved: WordPress profile keys, form field names and the core CSV import/export columns.
		$taken = array_merge( array( 'first_name', 'last_name', 'user_email', 'user_login', 'user_pass', 'description', 'nickname', 'email', 'username', 'membership', 'expires', 'user_id', 'name', 'active_memberships', 'lifetime_value', 'registered', 'display_name', 'new_pass', 'current_pass', 'log', 'pwd' ), array_keys( $defs ) );
		$types = array_keys( self::types() );
		$order = array();
		$over  = array();

		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$id    = (string) ( $it['id'] ?? '' );
			$label = sanitize_text_field( $it['label'] ?? '' );
			$vis   = ! empty( $it['visible'] );
			$width = in_array( $it['width'] ?? '', array( 'full', 'half' ), true ) ? $it['width'] : 'full';
			$class = implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) ( $it['class'] ?? '' ) ) ) ) );

			if ( isset( $builtins[ $id ] ) ) {
				$b             = $builtins[ $id ];
				$o             = array(
					'label'       => $label === $b['label'] ? '' : $label,
					'placeholder' => sanitize_text_field( $it['placeholder'] ?? '' ),
					'help'        => sanitize_text_field( $it['help'] ?? '' ),
					'width'       => $width,
					'class'       => $class,
				);
				$o['placeholder'] = $o['placeholder'] === $b['placeholder'] ? '' : $o['placeholder'];
				$o['help']        = $o['help'] === $b['help'] ? '' : $o['help'];
				if ( ! in_array( 'required', $b['lock'], true ) ) {
					$o['required'] = empty( $it['required'] ) ? '' : '1';
				}
				if ( ! in_array( 'hidden', $b['lock'], true ) ) {
					$o['hidden'] = $vis ? '' : '1';
				}
				$over[ $id ] = $o;
				$order[]     = $id;
				continue;
			}

			// Custom field: existing ("cf:key") or new.
			$key = 0 === strpos( $id, 'cf:' ) ? sanitize_key( substr( $id, 3 ) ) : '';
			if ( $key && ! isset( $defs[ $key ] ) ) {
				continue;
			}
			if ( $key && ! empty( $it['delete'] ) ) {
				unset( $defs[ $key ] );
				continue;
			}
			if ( '' === $label ) {
				if ( ! $key ) {
					continue; // Empty new row.
				}
				$label = $defs[ $key ]['label'];
			}
			if ( ! $key ) {
				$base = sanitize_key( str_replace( '-', '_', sanitize_title( $label ) ) );
				$base = $base ? $base : 'field';
				$key  = $base;
				$n    = 2;
				while ( in_array( $key, $taken, true ) ) {
					$key = $base . '_' . $n++;
				}
				$taken[]      = $key;
				$defs[ $key ] = array( 'key' => $key, 'show_on' => $vis ? 'both' : ( 'checkout' === $form ? 'profile' : 'checkout' ) );
			}
			$old   = $defs[ $key ];
			$where = $old['show_on'] ?? 'both';
			$flags = array(
				'checkout' => in_array( $where, array( 'both', 'checkout' ), true ),
				'profile'  => in_array( $where, array( 'both', 'profile' ), true ),
			);
			if ( 0 === strpos( $id, 'cf:' ) ) {
				$flags[ $form ] = $vis;
			}
			$show = $flags['checkout'] && $flags['profile'] ? 'both' : ( $flags['checkout'] ? 'checkout' : ( $flags['profile'] ? 'profile' : 'none' ) );

			$defs[ $key ] = array(
				'key'         => $key,
				'label'       => $label,
				'type'        => in_array( $it['type'] ?? '', $types, true ) ? $it['type'] : 'text',
				'options'     => sanitize_text_field( $it['options'] ?? '' ),
				'required'    => empty( $it['required'] ) ? '' : '1',
				'public'      => empty( $it['public'] ) ? '' : '1',
				'admin_column' => isset( $it['admin_column'] ) ? ( empty( $it['admin_column'] ) ? '' : '1' ) : ( MGM_Custom_Fields::in_users_list( $old ) ? '1' : '' ),
				'show_on'     => $show,
				'placeholder' => sanitize_text_field( $it['placeholder'] ?? '' ),
				'help'        => sanitize_text_field( $it['help'] ?? '' ),
				'memberships' => 'checkout' === $form ? mgm_int_list( $it['memberships'] ?? array() ) : mgm_int_list( $old['memberships'] ?? array() ),
			);
			if ( ! empty( $old['demo'] ) ) {
				$defs[ $key ]['demo'] = '1';
			}
			$over[ 'cf:' . $key ] = array( 'width' => $width, 'class' => $class );
			$order[]              = 'cf:' . $key;
		}

		$settings = array();
		$in       = isset( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore
		foreach ( self::settings_defaults( $form ) as $k => $default ) {
			if ( ! isset( $in[ $k ] ) ) {
				continue;
			}
			$v = 'register_url' === $k ? esc_url_raw( trim( (string) $in[ $k ] ) ) : sanitize_text_field( (string) $in[ $k ] );
			// Store only real changes so translated defaults keep working.
			$settings[ $k ] = $v === $default ? '' : $v;
		}

		$cfg[ $form ] = array(
			'order'    => $order,
			'fields'   => $over,
			'settings' => $settings,
		);
		update_option( self::OPTION, $cfg );
		update_option( 'mgm_custom_fields', array_values( $defs ) );
		wp_safe_redirect( $back . '&mgm_msg=' . rawurlencode( __( 'Form saved.', 'mg-membership' ) ) );
		exit;
	}
}
