<?php
/**
 * Add-on: Member directory, public profiles and membership badges.
 * Privacy first: members only appear after opting in from their account profile.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Directory {

	public static function init() {
		add_filter( 'mgm_shortcodes_registry', array( __CLASS__, 'shortcodes' ) );
		add_action( 'mgm_profile_fields', array( __CLASS__, 'profile_fields' ), 5 );
		add_action( 'mgm_profile_updated', array( __CLASS__, 'save_profile' ) );
		add_action( 'mgm_membership_options_box', array( __CLASS__, 'badge_field' ) );
		add_action( 'mgm_save_membership', array( __CLASS__, 'save_badge' ) );
	}

	public static function opted_in( $user_id ) {
		return '1' === (string) get_user_meta( $user_id, '_mgm_directory_optin', true );
	}

	public static function badge_field( $post ) {
		$c = get_post_meta( $post->ID, '_mgm_badge_color', true );
		echo '<p class="mgm-field"><label>' . esc_html__( 'Member badge color (directory & profiles)', 'mg-membership' ) . '</label><input type="color" name="mgm_badge_color" value="' . esc_attr( $c ? $c : '#4f46e5' ) . '"></p>';
	}

	public static function save_badge( $post_id ) {
		update_post_meta( $post_id, '_mgm_badge_color', sanitize_hex_color( wp_unslash( $_POST['mgm_badge_color'] ?? '' ) ) ); // phpcs:ignore -- nonce checked in membership save.
	}

	public static function badges( $user_id ) {
		$out = '';
		foreach ( MGM_Member::active_membership_ids( $user_id ) as $mid ) {
			if ( ! mgm_is_public_membership( $mid ) ) {
				continue;
			}
			$c    = get_post_meta( $mid, '_mgm_badge_color', true );
			$out .= '<span class="mgm-badge" style="--mgm-badge:' . esc_attr( $c ? $c : '#4f46e5' ) . '">' . esc_html( get_the_title( $mid ) ) . '</span>';
		}
		return $out;
	}

	public static function profile_fields( $user ) {
		echo '<h3>' . esc_html__( 'Member directory', 'mg-membership' ) . '</h3>';
		echo '<p><label class="mgm-check"><input type="hidden" name="mgm_directory_optin" value="0"><input type="checkbox" name="mgm_directory_optin" value="1" ' . checked( self::opted_in( $user->ID ), true, false ) . '> ' . esc_html__( 'Show my profile in the member directory', 'mg-membership' ) . '</label></p>';
		echo '<p><label for="mgm-bio">' . esc_html__( 'Short bio', 'mg-membership' ) . '</label><textarea id="mgm-bio" name="mgm_bio" rows="3">' . esc_textarea( get_user_meta( $user->ID, 'description', true ) ) . '</textarea></p>';
	}

	public static function save_profile( $user_id ) {
		if ( isset( $_POST['mgm_directory_optin'] ) ) { // phpcs:ignore -- nonce checked by profile handler.
			update_user_meta( $user_id, '_mgm_directory_optin', '1' === $_POST['mgm_directory_optin'] ? '1' : '0' ); // phpcs:ignore
		}
		if ( isset( $_POST['mgm_bio'] ) ) { // phpcs:ignore
			update_user_meta( $user_id, 'description', sanitize_textarea_field( wp_unslash( $_POST['mgm_bio'] ) ) ); // phpcs:ignore
		}
	}

	public static function shortcodes( $registry ) {
		$registry['mgm_directory']      = array(
			'label'    => __( 'Member Directory', 'mg-membership' ),
			'icon'     => 'eicon-person',
			'callback' => array( __CLASS__, 'sc_directory' ),
			'atts'     => array(
				'memberships' => array( 'type' => 'memberships', 'label' => __( 'Only members of (empty = any)', 'mg-membership' ), 'default' => '' ),
				'per_page'    => array( 'type' => 'number', 'label' => __( 'Members per page', 'mg-membership' ), 'default' => '12' ),
				'columns'     => array( 'type' => 'select', 'label' => __( 'Columns', 'mg-membership' ), 'default' => '3', 'options' => array( '2' => '2', '3' => '3', '4' => '4' ) ),
				'visible_to'  => array( 'type' => 'select', 'label' => __( 'Who can see the directory', 'mg-membership' ), 'default' => 'members', 'options' => array( 'members' => __( 'Members only', 'mg-membership' ), 'everyone' => __( 'Everyone', 'mg-membership' ) ) ),
			),
		);
		$registry['mgm_member_badges'] = array(
			'label'    => __( 'Member Badges', 'mg-membership' ),
			'icon'     => 'eicon-star',
			'callback' => function () {
				return is_user_logged_in() ? '<span class="mgm-badges">' . self::badges( get_current_user_id() ) . '</span>' : '';
			},
			'atts'     => array(),
		);
		return $registry;
	}

	public static function sc_directory( $atts ) {
		$atts = shortcode_atts( MGM_Shortcodes::defaults( 'mgm_directory' ), (array) $atts );
		if ( 'everyone' !== $atts['visible_to'] && ! MGM_Access::check_condition( 'member' ) ) {
			return MGM_Access::unauthorized_html( 0, __( 'The member directory is visible to members only.', 'mg-membership' ) );
		}
		$slug = isset( $_GET['member'] ) ? sanitize_title( wp_unslash( $_GET['member'] ) ) : ''; // phpcs:ignore
		if ( $slug ) {
			return self::profile( $slug, mgm_int_list( $atts['memberships'] ) );
		}
		$filter  = mgm_int_list( $atts['memberships'] );
		$search  = isset( $_GET['msearch'] ) ? sanitize_text_field( wp_unslash( $_GET['msearch'] ) ) : ''; // phpcs:ignore
		$page    = max( 1, absint( $_GET['mpage'] ?? 1 ) ); // phpcs:ignore
		$per     = max( 1, min( 60, (int) $atts['per_page'] ) );
		$args    = array(
			'meta_key'   => '_mgm_directory_optin', // phpcs:ignore
			'meta_value' => '1', // phpcs:ignore
			'orderby'    => 'display_name',
			'number'     => 500,
			'fields'     => 'all',
		);
		if ( $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'display_name', 'user_nicename' );
		}
		$users = array_values(
			array_filter(
				get_users( $args ),
				function ( $u ) use ( $filter ) {
					$active = array_filter( MGM_Member::active_membership_ids( $u->ID ), 'mgm_is_public_membership' );
					return $filter ? (bool) array_intersect( $filter, $active ) : (bool) $active;
				}
			)
		);
		$total = count( $users );
		$users = array_slice( $users, ( $page - 1 ) * $per, $per );

		$out  = '<div class="mgm-directory">';
		$out .= '<form method="get" class="mgm-directory-search" role="search"><input type="search" name="msearch" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search members…', 'mg-membership' ) . '" aria-label="' . esc_attr__( 'Search members', 'mg-membership' ) . '"><button class="mgm-button" type="submit">' . esc_html__( 'Search', 'mg-membership' ) . '</button></form>';
		$out .= '<div class="mgm-grid mgm-cols-' . (int) $atts['columns'] . '">';
		foreach ( $users as $u ) {
			$link = add_query_arg( 'member', $u->user_nicename, remove_query_arg( array( 'msearch', 'mpage' ) ) );
			$out .= '<div class="mgm-member-card">' . get_avatar( $u->ID, 72 ) . '<h3><a href="' . esc_url( $link ) . '">' . esc_html( $u->display_name ) . '</a></h3><div>' . self::badges( $u->ID ) . '</div>';
			$bio  = get_user_meta( $u->ID, 'description', true );
			if ( $bio ) {
				$out .= '<p class="mgm-muted">' . esc_html( wp_trim_words( $bio, 18 ) ) . '</p>';
			}
			$out .= '</div>';
		}
		$out .= '</div>';
		if ( ! $users ) {
			$out .= '<p class="mgm-muted">' . esc_html( $search ? __( 'No members match your search.', 'mg-membership' ) : __( 'No members have joined the directory yet.', 'mg-membership' ) ) . '</p>';
		}
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			$out .= '<nav class="mgm-pagination">' . paginate_links( array( 'base' => add_query_arg( 'mpage', '%#%' ), 'format' => '', 'current' => $page, 'total' => $pages ) ) . '</nav>';
		}
		return $out . '</div>';
	}

	public static function profile( $slug, array $filter = array() ) {
		$u      = get_user_by( 'slug', $slug );
		$active = $u ? array_filter( MGM_Member::active_membership_ids( $u->ID ), 'mgm_is_public_membership' ) : array();
		if ( ! $u || ! self::opted_in( $u->ID ) || ! $active || ( $filter && ! array_intersect( $filter, $active ) ) ) {
			return '<p class="mgm-muted">' . esc_html__( 'This profile is not available.', 'mg-membership' ) . '</p>';
		}
		$out  = '<div class="mgm-member-profile"><p><a href="' . esc_url( remove_query_arg( 'member' ) ) . '">&larr; ' . esc_html__( 'All members', 'mg-membership' ) . '</a></p>';
		$out .= '<div class="mgm-member-profile-head">' . get_avatar( $u->ID, 96 ) . '<div><h2>' . esc_html( $u->display_name ) . '</h2>' . self::badges( $u->ID ) . '<p class="mgm-muted">' . esc_html( sprintf( __( 'Member since %s', 'mg-membership' ), date_i18n( 'F Y', strtotime( $u->user_registered ) ) ) ) . '</p></div></div>';
		$bio  = get_user_meta( $u->ID, 'description', true );
		if ( $bio ) {
			$out .= wpautop( esc_html( $bio ) );
		}
		if ( class_exists( 'MGM_Custom_Fields' ) ) {
			$rows = '';
			foreach ( MGM_Custom_Fields::fields() as $f ) {
				$v = mgm_get_member_field( $u->ID, $f['key'] );
				if ( empty( $f['public'] ) || '' === (string) $v ) {
					continue;
				}
				$v     = 'checkbox' === $f['type'] ? __( 'Yes', 'mg-membership' ) : $v;
				$rows .= '<dt>' . esc_html( $f['label'] ) . '</dt><dd>' . ( 'url' === $f['type'] ? '<a href="' . esc_url( $v ) . '" rel="nofollow noopener" target="_blank">' . esc_html( $v ) . '</a>' : esc_html( $v ) ) . '</dd>';
			}
			if ( $rows ) {
				$out .= '<dl class="mgm-profile-fields">' . $rows . '</dl>';
			}
		}
		if ( class_exists( 'MGM_Courses' ) ) {
			$done = 0;
			foreach ( MGM_Courses::enrolled_courses( $u->ID ) as $cid ) {
				$done += get_user_meta( $u->ID, '_mgm_course_completed_' . $cid, true ) ? 1 : 0;
			}
			if ( $done ) {
				$out .= '<p>🎓 ' . esc_html( sprintf( _n( 'Completed %d course', 'Completed %d courses', $done, 'mg-membership' ), $done ) ) . '</p>';
			}
		}
		return $out . '</div>';
	}
}

MGM_Directory::init();
