<?php
/**
 * Appearance: colours, fonts, layout and texts for the member-facing pages.
 *
 * Everything is stored in the normal plugin settings and printed as CSS variables, so a developer can also
 * override any value in their theme (e.g. `:root { --mgm-accent: #e11d48; }`) or with the `mgm_appearance_vars` filter.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Appearance {

	/** True while reading the original tab labels (so renames are not applied). */
	private static $raw_tabs = false;

	public static function init() {
		add_filter( 'mgm_settings_fields', array( __CLASS__, 'fields' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'wp_print_styles', array( __CLASS__, 'attach_inline' ), 1 );
		add_filter( 'mgm_account_tabs', array( __CLASS__, 'filter_tabs' ), 999 );
		add_action( 'mgm_settings_before_fields', array( __CLASS__, 'preview' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Defaults & options                                                  */
	/* ------------------------------------------------------------------ */

	public static function defaults() {
		return apply_filters(
			'mgm_appearance_defaults',
			array(
				'ui_accent'         => '#4f46e5',
				'ui_accent_text'    => '#ffffff',
				'ui_text'           => '#1f2937',
				'ui_muted'          => '#6b7280',
				'ui_border'         => '#e5e7eb',
				'ui_surface'        => '#ffffff',
				'ui_soft'           => '#f6f7fb',
				'ui_success'        => '#059669',
				'ui_danger'         => '#dc2626',
				'ui_font'           => 'inherit',
				'ui_font_custom'    => '',
				'ui_font_size'      => '16',
				'ui_heading_weight' => '700',
				'ui_width'          => '1100',
				'ui_radius'         => '12',
				'ui_button_shape'   => 'rounded',
				'ui_shadow'         => 'soft',
				'acc_wide'          => '1',
				'acc_layout'        => 'sidebar',
				'acc_sidebar_width' => '260',
				'acc_show_header'   => '1',
				'acc_show_icons'    => '1',
				'acc_show_stats'    => '1',
				'acc_greeting'      => __( 'Welcome back, {name}', 'mg-membership' ),
				'acc_intro'         => __( 'Here is an overview of your membership, courses and account.', 'mg-membership' ),
				'acc_empty_title'   => __( 'No active membership yet', 'mg-membership' ),
				'acc_empty_text'    => __( 'Choose a plan to unlock members-only content, courses and events.', 'mg-membership' ),
				'acc_empty_button'  => __( 'Browse plans', 'mg-membership' ),
				'acc_logout_label'  => __( 'Log out', 'mg-membership' ),
				'ui_custom_css'     => '',
			)
		);
	}

	/** Read an appearance option (falls back to the default). */
	public static function get( $key ) {
		$d = self::defaults();
		return mgm_option( $key, $d[ $key ] ?? '' );
	}

	public static function on( $key ) {
		return '1' === (string) self::get( $key );
	}

	/** Font choices: key => [label, CSS stack, Google Fonts family or ''] */
	public static function fonts() {
		$sans = ', system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
		return apply_filters(
			'mgm_appearance_fonts',
			array(
				'inherit'           => array( __( 'Same as my theme', 'mg-membership' ), 'inherit', '' ),
				'system'            => array( __( 'System font (fastest)', 'mg-membership' ), 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif', '' ),
				'Inter'             => array( 'Inter', '"Inter"' . $sans, 'Inter' ),
				'Poppins'           => array( 'Poppins', '"Poppins"' . $sans, 'Poppins' ),
				'Roboto'            => array( 'Roboto', '"Roboto"' . $sans, 'Roboto' ),
				'Open Sans'         => array( 'Open Sans', '"Open Sans"' . $sans, 'Open Sans' ),
				'Lato'              => array( 'Lato', '"Lato"' . $sans, 'Lato' ),
				'Montserrat'        => array( 'Montserrat', '"Montserrat"' . $sans, 'Montserrat' ),
				'Manrope'           => array( 'Manrope', '"Manrope"' . $sans, 'Manrope' ),
				'DM Sans'           => array( 'DM Sans', '"DM Sans"' . $sans, 'DM Sans' ),
				'Nunito'            => array( 'Nunito', '"Nunito"' . $sans, 'Nunito' ),
				'Plus Jakarta Sans' => array( 'Plus Jakarta Sans', '"Plus Jakarta Sans"' . $sans, 'Plus Jakarta Sans' ),
				'Outfit'            => array( 'Outfit', '"Outfit"' . $sans, 'Outfit' ),
				'Merriweather'      => array( 'Merriweather (serif)', '"Merriweather", Georgia, serif', 'Merriweather' ),
				'custom'            => array( __( 'Custom (type the font name below)', 'mg-membership' ), '', '' ),
			)
		);
	}

	public static function font_stack() {
		$fonts = self::fonts();
		$key   = self::get( 'ui_font' );
		if ( 'custom' === $key ) {
			$custom = preg_replace( '/[^A-Za-z0-9 ,"\'\-]/', '', (string) self::get( 'ui_font_custom' ) );
			return $custom ? $custom : 'inherit';
		}
		return isset( $fonts[ $key ] ) ? $fonts[ $key ][1] : 'inherit';
	}

	public static function google_font_url() {
		$fonts = self::fonts();
		$key   = self::get( 'ui_font' );
		if ( empty( $fonts[ $key ][2] ) ) {
			return '';
		}
		$weight = (int) self::get( 'ui_heading_weight' );
		$w      = array_unique( array( 400, 500, 600, max( 500, min( 900, $weight ) ) ) );
		sort( $w );
		return 'https://fonts.googleapis.com/css2?family=' . rawurlencode( $fonts[ $key ][2] ) . ':wght@' . implode( ';', $w ) . '&display=swap';
	}

	private static function color( $key ) {
		$c = sanitize_hex_color( (string) self::get( $key ) );
		if ( ! $c ) {
			$d = self::defaults();
			$c = $d[ $key ];
		}
		return $c;
	}

	private static function num( $key, $min, $max ) {
		return max( $min, min( $max, (int) self::get( $key ) ) );
	}

	/** CSS variables for the front end. */
	public static function vars() {
		$radius  = self::num( 'ui_radius', 0, 32 );
		$width   = self::num( 'ui_width', 0, 2400 );
		$shadows = array(
			'none'   => 'none',
			'soft'   => '0 1px 2px rgba(16,24,40,.05), 0 1px 3px rgba(16,24,40,.06)',
			'strong' => '0 4px 6px -2px rgba(16,24,40,.06), 0 12px 24px -6px rgba(16,24,40,.14)',
		);
		$shapes  = array(
			'rounded' => round( $radius * .7 ) . 'px',
			'pill'    => '999px',
			'square'  => '0px',
		);
		$vars    = array(
			'--mgm-accent'          => self::color( 'ui_accent' ),
			'--mgm-accent-contrast' => self::color( 'ui_accent_text' ),
			'--mgm-text'            => self::color( 'ui_text' ),
			'--mgm-muted'           => self::color( 'ui_muted' ),
			'--mgm-border'          => self::color( 'ui_border' ),
			'--mgm-surface'         => self::color( 'ui_surface' ),
			'--mgm-soft'            => self::color( 'ui_soft' ),
			'--mgm-success'         => self::color( 'ui_success' ),
			'--mgm-danger'          => self::color( 'ui_danger' ),
			'--mgm-radius'          => $radius . 'px',
			'--mgm-btn-radius'      => $shapes[ self::get( 'ui_button_shape' ) ] ?? $shapes['rounded'],
			'--mgm-shadow'          => $shadows[ self::get( 'ui_shadow' ) ] ?? $shadows['soft'],
			'--mgm-font'            => self::font_stack(),
			'--mgm-font-size'       => self::num( 'ui_font_size', 12, 22 ) . 'px',
			'--mgm-heading-weight'  => (string) self::num( 'ui_heading_weight', 400, 900 ),
			'--mgm-width'           => $width ? $width . 'px' : 'none',
			'--mgm-sidebar'         => self::num( 'acc_sidebar_width', 180, 400 ) . 'px',
		);
		return apply_filters( 'mgm_appearance_vars', $vars );
	}

	public static function inline_css() {
		$css = ':root{';
		foreach ( self::vars() as $k => $v ) {
			$css .= $k . ':' . str_replace( array( '{', '}', ';', '<' ), '', (string) $v ) . ';';
		}
		$css   .= '}';
		$custom = self::clean_css( (string) self::get( 'ui_custom_css' ) );
		if ( '' !== trim( $custom ) ) {
			$css .= "\n/* Custom CSS (MG Membership → Settings → Appearance) */\n" . $custom;
		}
		return $css;
	}

	/** Custom CSS can never close the <style> tag. */
	public static function clean_css( $css ) {
		return str_replace( '<', '', wp_strip_all_tags( $css ) );
	}

	/* ------------------------------------------------------------------ */
	/* Front end                                                           */
	/* ------------------------------------------------------------------ */

	public static function enqueue() {
		$url = self::google_font_url();
		if ( $url ) {
			wp_enqueue_style( 'mgm-font', $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceVersion
		}
	}

	/** Runs right before styles print, so it also covers page builders that enqueue our CSS late. */
	public static function attach_inline() {
		static $done = false;
		if ( $done || ! wp_style_is( 'mgm-frontend', 'enqueued' ) ) {
			return;
		}
		$done = true;
		wp_add_inline_style( 'mgm-frontend', self::inline_css() );
	}

	/** Rename / hide account menu items from the settings. */
	public static function filter_tabs( $tabs ) {
		if ( self::$raw_tabs ) {
			return $tabs;
		}
		foreach ( $tabs as $key => $label ) {
			$custom = trim( (string) mgm_option( 'acc_tab_label_' . $key, '' ) );
			if ( '' !== $custom ) {
				$tabs[ $key ] = $custom;
			}
			if ( 'home' !== $key && '1' === (string) mgm_option( 'acc_tab_hide_' . $key, '0' ) ) {
				unset( $tabs[ $key ] );
			}
		}
		return $tabs;
	}

	/** Original menu items, before any rename (used for the settings screen). */
	public static function raw_tabs() {
		self::$raw_tabs = true;
		$tabs           = MGM_Account::tabs();
		self::$raw_tabs = false;
		if ( class_exists( 'MGM_Groups' ) && ! isset( $tabs['team'] ) ) {
			$tabs['team'] = __( 'Team', 'mg-membership' );
		}
		return $tabs;
	}

	/** Replace {name}, {first_name}, {site_name} in a text option. */
	public static function text( $key, WP_User $user = null ) {
		$text = (string) self::get( $key );
		$user = $user ? $user : wp_get_current_user();
		return strtr(
			$text,
			array(
				'{name}'       => $user->first_name ? $user->first_name : $user->display_name,
				'{first_name}' => $user->first_name,
				'{full_name}'  => trim( $user->first_name . ' ' . $user->last_name ) ? trim( $user->first_name . ' ' . $user->last_name ) : $user->display_name,
				'{site_name}'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Settings tab                                                        */
	/* ------------------------------------------------------------------ */

	public static function fields( $fields ) {
		$d     = self::defaults();
		$font  = array();
		foreach ( self::fonts() as $k => $f ) {
			$font[ $k ] = $f[0];
		}
		$c = function ( $label, $key, $desc = '' ) use ( $d ) {
			return array( 'type' => 'color', 'label' => $label, 'default' => $d[ $key ], 'desc' => $desc );
		};

		$f = array(
			'ui_heading_colors' => array( 'type' => 'heading', 'label' => __( 'Colours', 'mg-membership' ) ),
			'ui_accent'         => $c( __( 'Brand colour', 'mg-membership' ), 'ui_accent', __( 'Buttons, links, the active menu item and highlights.', 'mg-membership' ) ),
			'ui_accent_text'    => $c( __( 'Button text colour', 'mg-membership' ), 'ui_accent_text' ),
			'ui_text'           => $c( __( 'Text colour', 'mg-membership' ), 'ui_text' ),
			'ui_muted'          => $c( __( 'Secondary text colour', 'mg-membership' ), 'ui_muted', __( 'Dates, hints and small labels.', 'mg-membership' ) ),
			'ui_border'         => $c( __( 'Border colour', 'mg-membership' ), 'ui_border' ),
			'ui_surface'        => $c( __( 'Card background', 'mg-membership' ), 'ui_surface' ),
			'ui_soft'           => $c( __( 'Light background', 'mg-membership' ), 'ui_soft', __( 'Menu hover, panels and empty states.', 'mg-membership' ) ),
			'ui_success'        => $c( __( 'Success colour', 'mg-membership' ), 'ui_success' ),
			'ui_danger'         => $c( __( 'Danger colour', 'mg-membership' ), 'ui_danger', __( 'Cancel links and error messages.', 'mg-membership' ) ),

			'ui_heading_type'   => array( 'type' => 'heading', 'label' => __( 'Typography', 'mg-membership' ) ),
			'ui_font'           => array( 'type' => 'select', 'label' => __( 'Font', 'mg-membership' ), 'options' => $font, 'default' => $d['ui_font'], 'desc' => __( 'Fonts other than "Same as my theme", "System" and "Custom" are loaded from Google Fonts.', 'mg-membership' ) ),
			'ui_font_custom'    => array( 'type' => 'text', 'label' => __( 'Custom font name', 'mg-membership' ), 'placeholder' => '"Satoshi", sans-serif', 'desc' => __( 'Only used with "Custom". The font must already be loaded by your theme or another plugin.', 'mg-membership' ) ),
			'ui_font_size'      => array( 'type' => 'number', 'label' => __( 'Base text size (px)', 'mg-membership' ), 'default' => $d['ui_font_size'], 'step' => '1', 'desc' => __( '12–22. Headings and labels scale with it.', 'mg-membership' ) ),
			'ui_heading_weight' => array(
				'type'    => 'select',
				'label'   => __( 'Heading weight', 'mg-membership' ),
				'default' => $d['ui_heading_weight'],
				'options' => array(
					'500' => __( 'Medium (500)', 'mg-membership' ),
					'600' => __( 'Semi-bold (600)', 'mg-membership' ),
					'700' => __( 'Bold (700)', 'mg-membership' ),
					'800' => __( 'Extra bold (800)', 'mg-membership' ),
				),
			),

			'ui_heading_layout' => array( 'type' => 'heading', 'label' => __( 'Layout & shape', 'mg-membership' ) ),
			'ui_width'          => array( 'type' => 'number', 'label' => __( 'Account area width (px)', 'mg-membership' ), 'default' => $d['ui_width'], 'step' => '10', 'desc' => __( 'Maximum width of the account area. 0 = use the theme\'s width.', 'mg-membership' ) ),
			'acc_wide'          => array( 'type' => 'checkbox', 'label' => __( 'Allow the account area to be wider than the theme\'s content column', 'mg-membership' ), 'default' => $d['acc_wide'] ),
			'ui_radius'         => array( 'type' => 'number', 'label' => __( 'Corner roundness (px)', 'mg-membership' ), 'default' => $d['ui_radius'], 'step' => '1', 'desc' => __( '0 = square corners, 32 = very round.', 'mg-membership' ) ),
			'ui_button_shape'   => array(
				'type'    => 'select',
				'label'   => __( 'Button shape', 'mg-membership' ),
				'default' => $d['ui_button_shape'],
				'options' => array(
					'rounded' => __( 'Rounded', 'mg-membership' ),
					'pill'    => __( 'Pill', 'mg-membership' ),
					'square'  => __( 'Square', 'mg-membership' ),
				),
			),
			'ui_shadow'         => array(
				'type'    => 'select',
				'label'   => __( 'Card shadow', 'mg-membership' ),
				'default' => $d['ui_shadow'],
				'options' => array(
					'none'   => __( 'None (flat)', 'mg-membership' ),
					'soft'   => __( 'Soft', 'mg-membership' ),
					'strong' => __( 'Strong', 'mg-membership' ),
				),
			),

			'acc_heading'       => array( 'type' => 'heading', 'label' => __( 'Account page', 'mg-membership' ) ),
			'acc_layout'        => array(
				'type'    => 'select',
				'label'   => __( 'Menu position', 'mg-membership' ),
				'default' => $d['acc_layout'],
				'options' => array(
					'sidebar' => __( 'Sidebar on the left', 'mg-membership' ),
					'tabs'    => __( 'Tabs along the top', 'mg-membership' ),
				),
			),
			'acc_sidebar_width' => array( 'type' => 'number', 'label' => __( 'Sidebar width (px)', 'mg-membership' ), 'default' => $d['acc_sidebar_width'], 'step' => '10', 'desc' => __( '180–400. Only for the sidebar layout.', 'mg-membership' ) ),
			'acc_show_header'   => array( 'type' => 'checkbox', 'label' => __( 'Show the member\'s photo, name and email above the menu', 'mg-membership' ), 'default' => $d['acc_show_header'] ),
			'acc_show_icons'    => array( 'type' => 'checkbox', 'label' => __( 'Show icons in the menu', 'mg-membership' ), 'default' => $d['acc_show_icons'] ),
			'acc_show_stats'    => array( 'type' => 'checkbox', 'label' => __( 'Show summary cards on the dashboard (memberships, next payment, courses…)', 'mg-membership' ), 'default' => $d['acc_show_stats'] ),

			'acc_heading_text'  => array( 'type' => 'heading', 'label' => __( 'Account page texts', 'mg-membership' ) ),
			'acc_greeting'      => array( 'type' => 'text', 'label' => __( 'Dashboard heading', 'mg-membership' ), 'default' => $d['acc_greeting'], 'desc' => __( 'You can use {name}, {first_name}, {full_name} and {site_name}.', 'mg-membership' ) ),
			'acc_intro'         => array( 'type' => 'textarea', 'label' => __( 'Dashboard intro text', 'mg-membership' ), 'default' => $d['acc_intro'], 'desc' => __( 'Leave empty to hide it.', 'mg-membership' ) ),
			'acc_empty_title'   => array( 'type' => 'text', 'label' => __( '"No membership" title', 'mg-membership' ), 'default' => $d['acc_empty_title'] ),
			'acc_empty_text'    => array( 'type' => 'text', 'label' => __( '"No membership" text', 'mg-membership' ), 'default' => $d['acc_empty_text'] ),
			'acc_empty_button'  => array( 'type' => 'text', 'label' => __( '"No membership" button', 'mg-membership' ), 'default' => $d['acc_empty_button'] ),
			'acc_logout_label'  => array( 'type' => 'text', 'label' => __( 'Log out link text', 'mg-membership' ), 'default' => $d['acc_logout_label'] ),

			'acc_heading_menu'  => array( 'type' => 'heading', 'label' => __( 'Account menu items', 'mg-membership' ) ),
		);

		// One "rename" box and one "hide" switch per menu item (only on the Appearance screen, to keep other screens light).
		$on_screen = is_admin() && isset( $_GET['page'], $_GET['tab'] ) && 'mgm-settings' === $_GET['page'] && 'appearance' === $_GET['tab']; // phpcs:ignore
		$saving    = isset( $_POST['action'], $_POST['tab'] ) && 'mgm_save_settings' === $_POST['action'] && 'appearance' === $_POST['tab']; // phpcs:ignore
		if ( $on_screen || $saving ) {
			foreach ( self::raw_tabs() as $key => $label ) {
				$f[ 'acc_tab_label_' . $key ] = array(
					'type'        => 'text',
					/* translators: %s: menu item name */
					'label'       => sprintf( __( '"%s" label', 'mg-membership' ), $label ),
					'placeholder' => $label,
				);
				if ( 'home' !== $key ) {
					/* translators: %s: menu item name */
					$f[ 'acc_tab_hide_' . $key ] = array( 'type' => 'checkbox', 'label' => sprintf( __( 'Hide "%s"', 'mg-membership' ), $label ) );
				}
			}
		}

		$f['ui_heading_css'] = array( 'type' => 'heading', 'label' => __( 'Custom CSS (for developers)', 'mg-membership' ) );
		$f['ui_custom_css']  = array(
			'type'        => 'code',
			'label'       => __( 'Extra CSS', 'mg-membership' ),
			'placeholder' => ".mgm-account-nav a { letter-spacing: .02em; }\n:root { --mgm-radius: 6px; }",
			'desc'        => __( 'Loaded after the plugin styles on every page. All colours and sizes above are CSS variables (--mgm-accent, --mgm-radius, --mgm-font…), so you can also change them here.', 'mg-membership' ),
		);

		$fields['appearance'] = array(
			'label'  => __( 'Appearance', 'mg-membership' ),
			'reset'  => true,
			'fields' => $f,
		);
		return $fields;
	}

	/** Live preview under the Appearance fields. */
	public static function preview( $tab ) {
		if ( 'appearance' !== $tab ) {
			return;
		}
		$stacks = array();
		foreach ( self::fonts() as $k => $f ) {
			$stacks[ $k ] = $f[1];
		}
		$style = '';
		foreach ( self::vars() as $k => $v ) {
			$style .= $k . ':' . $v . ';';
		}
		$font_url = self::google_font_url();
		if ( $font_url ) {
			echo '<link rel="stylesheet" href="' . esc_url( $font_url ) . '">'; // phpcs:ignore WordPress.WP.EnqueuedResources
		}
		?>
		<section class="mgm-card mgm-settings-card mgm-ui-preview-card">
			<h2 class="mgm-card-title"><?php esc_html_e( 'Preview', 'mg-membership' ); ?></h2>
			<p class="mgm-muted"><?php esc_html_e( 'Updates as you change colours, font, size and shape. Save to apply it to your site.', 'mg-membership' ); ?></p>
			<div id="mgm-ui-preview" class="mgm-ui-preview" style="<?php echo esc_attr( $style ); ?>" data-fonts="<?php echo esc_attr( wp_json_encode( $stacks ) ); ?>">
				<div class="mgm-ui-preview-side">
					<div class="mgm-ui-preview-user"><span class="mgm-ui-preview-avatar">JS</span><span><strong>Jane Smith</strong><small>jane@example.com</small></span></div>
					<span class="is-active"><?php esc_html_e( 'Dashboard', 'mg-membership' ); ?></span>
					<span><?php esc_html_e( 'My Courses', 'mg-membership' ); ?></span>
					<span><?php esc_html_e( 'Subscriptions', 'mg-membership' ); ?></span>
				</div>
				<div class="mgm-ui-preview-main">
					<h3><?php esc_html_e( 'Welcome back, Jane', 'mg-membership' ); ?></h3>
					<p class="mgm-ui-preview-muted"><?php esc_html_e( 'Here is an overview of your membership.', 'mg-membership' ); ?></p>
					<div class="mgm-ui-preview-box">
						<span class="mgm-ui-preview-pill"><?php esc_html_e( 'Active', 'mg-membership' ); ?></span>
						<strong><?php esc_html_e( 'Gold Membership', 'mg-membership' ); ?></strong>
						<span class="mgm-ui-preview-muted"><?php esc_html_e( 'Renews on 1 January', 'mg-membership' ); ?></span>
						<span class="mgm-ui-preview-actions"><span class="mgm-ui-preview-btn"><?php esc_html_e( 'Browse plans', 'mg-membership' ); ?></span><span class="mgm-ui-preview-danger"><?php esc_html_e( 'Cancel', 'mg-membership' ); ?></span></span>
					</div>
				</div>
			</div>
		</section>
		<?php
	}
}
