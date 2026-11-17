<?php
/**
 * Add-on: Protected downloads.
 *
 * Files are uploaded into wp-content/uploads/mgm-protected/<random>/ (deny rules for Apache/IIS,
 * unguessable path for nginx) and are only served through ?mgm_download=<id>, which checks access.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Downloads {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 6 );
		add_action( 'template_redirect', array( __CLASS__, 'serve' ), 0 );
		add_filter( 'mgm_shortcodes_registry', array( __CLASS__, 'shortcodes' ) );
		add_filter( 'mgm_account_tabs', array( __CLASS__, 'account_tab' ) );
		add_action( 'mgm_account_tab_downloads', array( __CLASS__, 'account_tab_content' ) );
		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
			add_action( 'post_edit_form_tag', array( __CLASS__, 'form_tag' ) );
			add_action( 'save_post_mgm_download', array( __CLASS__, 'save' ) );
			add_filter( 'manage_mgm_download_posts_columns', array( __CLASS__, 'columns' ) );
			add_action( 'manage_mgm_download_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
			add_action( 'before_delete_post', array( __CLASS__, 'delete_file' ) );
		}
		add_filter( 'mgm_cpt_intros', array( __CLASS__, 'intro' ) );
	}

	public static function register() {
		register_post_type(
			'mgm_download',
			array(
				'labels'       => array(
					'name'          => __( 'Downloads', 'mg-membership' ),
					'singular_name' => __( 'Download', 'mg-membership' ),
					'add_new_item'  => __( 'Add New Download', 'mg-membership' ),
					'edit_item'     => __( 'Edit Download', 'mg-membership' ),
					'all_items'     => __( 'Downloads', 'mg-membership' ),
					'not_found'     => __( 'No protected files yet. Click “Add New” to upload one.', 'mg-membership' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'mgm',
				'supports'     => array( 'title', 'excerpt' ),
			)
		);
	}

	public static function intro( $intros ) {
		$intros['mgm_download'] = array( 'dashicons-download', __( 'Members-only files. They are stored in a protected folder and can only be downloaded through access-checked links.', 'mg-membership' ) );
		return $intros;
	}

	/* ----------------------------- Storage ----------------------------- */

	public static function dir() {
		$token = get_option( 'mgm_protected_dir_token' );
		if ( ! $token ) {
			$token = wp_generate_password( 24, false );
			update_option( 'mgm_protected_dir_token', $token, false );
		}
		$up   = wp_upload_dir();
		$base = trailingslashit( $up['basedir'] ) . 'mgm-protected';
		$dir  = $base . '/' . $token;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		foreach ( array( $base, $dir ) as $d ) {
			if ( ! file_exists( $d . '/.htaccess' ) ) {
				file_put_contents( $d . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
			}
			if ( ! file_exists( $d . '/index.php' ) ) {
				file_put_contents( $d . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
			}
			if ( ! file_exists( $d . '/web.config' ) ) {
				file_put_contents( $d . '/web.config', '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>' ); // phpcs:ignore
			}
		}
		return $dir;
	}

	public static function file_path( $id ) {
		$name = get_post_meta( $id, '_mgm_file', true );
		return $name ? self::dir() . '/' . basename( $name ) : '';
	}

	public static function memberships( $id ) {
		return mgm_int_list( get_post_meta( $id, '_mgm_download_memberships', true ) );
	}

	public static function can_download( $id, $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( 'mgm_download' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return false;
		}
		if ( $user_id && user_can( $user_id, mgm_admin_cap() ) ) {
			return true;
		}
		$access = get_post_meta( $id, '_mgm_download_access', true );
		if ( 'logged_in' === $access ) {
			return (bool) $user_id;
		}
		$ms = self::memberships( $id );
		return (bool) $user_id && mgm_user_has_membership( $ms ? $ms : 'any', $user_id );
	}

	public static function url( $id ) {
		return add_query_arg( 'mgm_download', (int) $id, home_url( '/' ) );
	}

	public static function serve() {
		if ( empty( $_GET['mgm_download'] ) ) { // phpcs:ignore
			return;
		}
		$id = absint( $_GET['mgm_download'] ); // phpcs:ignore
		if ( ! self::can_download( $id ) ) {
			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( add_query_arg( 'mgm_redirect', rawurlencode( self::url( $id ) ), mgm_page_url( 'login' ) ) );
				exit;
			}
			wp_die( esc_html__( 'This file is available to members only.', 'mg-membership' ), esc_html__( 'Members only', 'mg-membership' ), array( 'response' => 403, 'link_url' => esc_url( mgm_page_url( 'pricing' ) ), 'link_text' => esc_html__( 'View membership plans', 'mg-membership' ) ) );
		}
		$path = self::file_path( $id );
		if ( ! $path || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'File not found.', 'mg-membership' ), '', array( 'response' => 404 ) );
		}
		update_post_meta( $id, '_mgm_download_count', (int) get_post_meta( $id, '_mgm_download_count', true ) + 1 );
		do_action( 'mgm_file_downloaded', get_current_user_id(), $id );
		$name = get_post_meta( $id, '_mgm_file_name', true );
		$type = wp_check_filetype( $path );
		nocache_headers();
		header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $name ? $name : basename( $path ) ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		readfile( $path ); // phpcs:ignore
		exit;
	}

	/* ------------------------------ Admin ------------------------------ */

	public static function form_tag( $post ) {
		if ( $post && 'mgm_download' === $post->post_type ) {
			echo ' enctype="multipart/form-data"';
		}
	}

	public static function metabox() {
		add_meta_box( 'mgm_download_file', __( 'File & Access', 'mg-membership' ), array( __CLASS__, 'box' ), 'mgm_download', 'normal', 'high' );
	}

	public static function box( $post ) {
		wp_nonce_field( 'mgm_download_save', '_mgm_download_nonce' );
		$name   = get_post_meta( $post->ID, '_mgm_file_name', true );
		$path   = self::file_path( $post->ID );
		$access = get_post_meta( $post->ID, '_mgm_download_access', true );
		echo '<div class="mgm-fields">';
		if ( $name && $path && file_exists( $path ) ) {
			echo '<p class="mgm-field"><strong>' . esc_html( $name ) . '</strong> · ' . esc_html( size_format( filesize( $path ) ) ) . ' · ' . esc_html( sprintf( _n( '%d download', '%d downloads', (int) get_post_meta( $post->ID, '_mgm_download_count', true ), 'mg-membership' ), (int) get_post_meta( $post->ID, '_mgm_download_count', true ) ) ) . '</p>';
		}
		echo '<p class="mgm-field"><label>' . esc_html( $name ? __( 'Replace file', 'mg-membership' ) : __( 'Upload file', 'mg-membership' ) ) . '</label><input type="file" name="mgm_download_file"></p>';
		echo '<p class="mgm-field"><label>' . esc_html__( 'Who can download', 'mg-membership' ) . '</label><select name="mgm_download_access"><option value="members" ' . selected( $access, 'members', false ) . '>' . esc_html__( 'Members of selected memberships (none = any membership)', 'mg-membership' ) . '</option><option value="logged_in" ' . selected( $access, 'logged_in', false ) . '>' . esc_html__( 'Any logged-in user', 'mg-membership' ) . '</option></select></p>';
		echo '<div class="mgm-field">' . MGM_Admin_Metaboxes::memberships_checklist( 'mgm_download_memberships', self::memberships( $post->ID ) ) . '</div>'; // phpcs:ignore
		if ( 'publish' === $post->post_status ) {
			echo '<p class="mgm-field"><label>' . esc_html__( 'Shortcode', 'mg-membership' ) . '</label><code>[mgm_download id="' . (int) $post->ID . '"]</code> &nbsp; <label>' . esc_html__( 'Link', 'mg-membership' ) . '</label><code>' . esc_html( self::url( $post->ID ) ) . '</code></p>';
		}
		echo '</div>';
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['_mgm_download_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_download_nonce'] ), 'mgm_download_save' ) || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( mgm_admin_cap() ) ) {
			return;
		}
		update_post_meta( $post_id, '_mgm_download_access', 'logged_in' === ( $_POST['mgm_download_access'] ?? '' ) ? 'logged_in' : 'members' );
		update_post_meta( $post_id, '_mgm_download_memberships', mgm_int_list( wp_unslash( $_POST['mgm_download_memberships'] ?? array() ) ) ); // phpcs:ignore
		if ( ! empty( $_FILES['mgm_download_file']['name'] ) && UPLOAD_ERR_OK === (int) $_FILES['mgm_download_file']['error'] ) {
			self::store_upload( $post_id, $_FILES['mgm_download_file'] ); // phpcs:ignore
		}
	}

	/**
	 * Move an uploaded file into protected storage.
	 *
	 * @param array $file A $_FILES entry, or ['name','tmp_name'] for local files (import/tests).
	 * @return true|WP_Error
	 */
	public static function store_upload( $post_id, array $file, $is_upload = true ) {
		$name  = sanitize_file_name( wp_basename( $file['name'] ) );
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $name );
		if ( ! $check['ext'] || ! $check['type'] ) {
			return new WP_Error( 'mgm_download_type', __( 'This file type is not allowed.', 'mg-membership' ) );
		}
		$stored = wp_generate_password( 12, false ) . '-' . $name;
		$target = self::dir() . '/' . $stored;
		$ok     = $is_upload ? move_uploaded_file( $file['tmp_name'], $target ) : copy( $file['tmp_name'], $target ); // phpcs:ignore
		if ( ! $ok ) {
			return new WP_Error( 'mgm_download_move', __( 'Could not store the file.', 'mg-membership' ) );
		}
		self::delete_file( $post_id );
		update_post_meta( $post_id, '_mgm_file', $stored );
		update_post_meta( $post_id, '_mgm_file_name', $name );
		return true;
	}

	public static function delete_file( $post_id ) {
		if ( 'mgm_download' !== get_post_type( $post_id ) ) {
			return;
		}
		$path = self::file_path( $post_id );
		if ( $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	public static function columns( $cols ) {
		return array(
			'cb'            => $cols['cb'],
			'title'         => $cols['title'],
			'mgm_file'      => __( 'File', 'mg-membership' ),
			'mgm_access'    => __( 'Access', 'mg-membership' ),
			'mgm_downloads' => __( 'Downloads', 'mg-membership' ),
			'mgm_code'      => __( 'Shortcode', 'mg-membership' ),
		);
	}

	public static function column( $col, $id ) {
		switch ( $col ) {
			case 'mgm_file':
				echo esc_html( get_post_meta( $id, '_mgm_file_name', true ) ? get_post_meta( $id, '_mgm_file_name', true ) : '—' );
				break;
			case 'mgm_access':
				$ms = self::memberships( $id );
				echo esc_html( 'logged_in' === get_post_meta( $id, '_mgm_download_access', true ) ? __( 'Logged-in users', 'mg-membership' ) : ( $ms ? implode( ', ', array_map( 'get_the_title', $ms ) ) : __( 'Any membership', 'mg-membership' ) ) );
				break;
			case 'mgm_downloads':
				echo (int) get_post_meta( $id, '_mgm_download_count', true );
				break;
			case 'mgm_code':
				echo '<code>[mgm_download id="' . (int) $id . '"]</code>';
				break;
		}
	}

	/* ---------------------------- Front-end ---------------------------- */

	public static function shortcodes( $registry ) {
		$opts = array( '' => __( '— Select —', 'mg-membership' ) );
		foreach ( get_posts( array( 'post_type' => 'mgm_download', 'numberposts' => 200 ) ) as $p ) {
			$opts[ $p->ID ] = $p->post_title;
		}
		$registry['mgm_download']  = array(
			'label'    => __( 'Protected Download', 'mg-membership' ),
			'icon'     => 'eicon-download-button',
			'callback' => array( __CLASS__, 'sc_download' ),
			'atts'     => array(
				'id'   => array( 'type' => 'select', 'label' => __( 'File', 'mg-membership' ), 'default' => '', 'options' => $opts ),
				'text' => array( 'type' => 'text', 'label' => __( 'Button text', 'mg-membership' ), 'default' => __( 'Download', 'mg-membership' ) ),
			),
		);
		$registry['mgm_downloads'] = array(
			'label'    => __( 'Member Downloads List', 'mg-membership' ),
			'icon'     => 'eicon-folder-o',
			'callback' => array( __CLASS__, 'sc_list' ),
			'atts'     => array(),
		);
		return $registry;
	}

	public static function item_html( $id, $text = '' ) {
		$text = $text ? $text : __( 'Download', 'mg-membership' );
		$name = get_post_meta( $id, '_mgm_file_name', true );
		$path = self::file_path( $id );
		$size = $path && file_exists( $path ) ? size_format( filesize( $path ) ) : '';
		$can  = self::can_download( $id );
		$out  = '<div class="mgm-download' . ( $can ? '' : ' is-locked' ) . '"><span class="mgm-download-name">' . esc_html( get_the_title( $id ) ) . '<small>' . esc_html( trim( $name . ( $size ? ' · ' . $size : '' ) ) ) . '</small></span>';
		if ( $can ) {
			$out .= '<a class="mgm-button" href="' . esc_url( self::url( $id ) ) . '">' . esc_html( $text ) . '</a>';
		} elseif ( ! is_user_logged_in() ) {
			$out .= '<a class="mgm-button mgm-button-secondary" href="' . esc_url( add_query_arg( 'mgm_redirect', rawurlencode( get_permalink() ? get_permalink() : home_url( '/' ) ), mgm_page_url( 'login' ) ) ) . '">🔒 ' . esc_html__( 'Log in to download', 'mg-membership' ) . '</a>';
		} else {
			$out .= '<a class="mgm-button mgm-button-secondary" href="' . esc_url( mgm_page_url( 'pricing' ) ) . '">🔒 ' . esc_html__( 'Members only – join to download', 'mg-membership' ) . '</a>';
		}
		return $out . '</div>';
	}

	public static function sc_download( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'text' => '' ), (array) $atts );
		$id   = absint( $atts['id'] );
		return 'mgm_download' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ? self::item_html( $id, $atts['text'] ) : '';
	}

	public static function sc_list() {
		$items = get_posts( array( 'post_type' => 'mgm_download', 'numberposts' => 200, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) );
		$out   = '';
		foreach ( $items as $p ) {
			if ( self::can_download( $p->ID ) ) {
				$out .= self::item_html( $p->ID );
			}
		}
		return $out ? '<div class="mgm-downloads">' . $out . '</div>' : '<p class="mgm-muted">' . esc_html__( 'No downloads available for your membership yet.', 'mg-membership' ) . '</p>';
	}

	public static function account_tab( $tabs ) {
		$new = array();
		foreach ( $tabs as $k => $v ) {
			if ( 'profile' === $k ) {
				$new['downloads'] = __( 'Downloads', 'mg-membership' );
			}
			$new[ $k ] = $v;
		}
		return $new;
	}

	public static function account_tab_content() {
		echo '<h2>' . esc_html__( 'Downloads', 'mg-membership' ) . '</h2>' . self::sc_list(); // phpcs:ignore
	}
}

MGM_Downloads::init();
