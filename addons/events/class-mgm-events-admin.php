<?php
/**
 * Events admin: settings metabox, attendees & check-in.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Events_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'metaboxes' ) );
		add_action( 'save_post_mgm_event', array( __CLASS__, 'save' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_mgm_event_admin', array( __CLASS__, 'actions' ) );
		add_filter( 'manage_mgm_event_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_mgm_event_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=mgm_event', __( 'Attendees', 'mg-membership' ), __( 'Attendees & Check-in', 'mg-membership' ), mgm_admin_cap(), 'mgm-attendees', array( __CLASS__, 'attendees_page' ) );
	}

	public static function metaboxes() {
		add_meta_box( 'mgm_event_details', __( 'Event Details', 'mg-membership' ), array( __CLASS__, 'box' ), 'mgm_event', 'normal', 'high' );
	}

	public static function box( $post ) {
		wp_nonce_field( 'mgm_event_save', '_mgm_event_nonce' );
		$g      = function ( $k, $d = '' ) use ( $post ) {
			return MGM_Events::meta( $post->ID, $k, $d );
		};
		$loc    = $g( 'location_type', 'in_person' );
		$access = $g( 'access', 'everyone' );
		$dt     = function ( $v ) {
			return $v ? str_replace( ' ', 'T', $v ) : '';
		};
		?>
		<div class="mgm-fields">
			<p class="mgm-field"><label><?php esc_html_e( 'Starts', 'mg-membership' ); ?></label>
				<input type="datetime-local" name="mgm_event[start]" value="<?php echo esc_attr( $dt( $g( 'start' ) ) ); ?>" required>
				<label><?php esc_html_e( 'Ends', 'mg-membership' ); ?></label>
				<input type="datetime-local" name="mgm_event[end]" value="<?php echo esc_attr( $dt( $g( 'end' ) ) ); ?>">
				<span class="description"><?php echo esc_html( sprintf( __( 'Site timezone: %s', 'mg-membership' ), wp_timezone_string() ) ); ?></span></p>

			<p class="mgm-field"><label><?php esc_html_e( 'Format', 'mg-membership' ); ?></label>
				<select name="mgm_event[location_type]">
					<option value="in_person" <?php selected( $loc, 'in_person' ); ?>><?php esc_html_e( 'In person', 'mg-membership' ); ?></option>
					<option value="online" <?php selected( $loc, 'online' ); ?>><?php esc_html_e( 'Online (webinar)', 'mg-membership' ); ?></option>
					<option value="hybrid" <?php selected( $loc, 'hybrid' ); ?>><?php esc_html_e( 'Hybrid', 'mg-membership' ); ?></option>
				</select></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Venue', 'mg-membership' ); ?></label><input type="text" class="regular-text" name="mgm_event[venue]" value="<?php echo esc_attr( $g( 'venue' ) ); ?>">
				<label><?php esc_html_e( 'Address', 'mg-membership' ); ?></label><input type="text" class="regular-text" name="mgm_event[address]" value="<?php echo esc_attr( $g( 'address' ) ); ?>"></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Online meeting URL (Zoom, Meet, Teams…) – shown only to registered attendees', 'mg-membership' ); ?></label>
				<input type="url" class="large-text" name="mgm_event[online_url]" value="<?php echo esc_attr( $g( 'online_url' ) ); ?>"></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Recording URL (shown after the event to attendees)', 'mg-membership' ); ?></label>
				<input type="url" class="large-text" name="mgm_event[recording_url]" value="<?php echo esc_attr( $g( 'recording_url' ) ); ?>"></p>

			<h4><?php esc_html_e( 'Registration', 'mg-membership' ); ?></h4>
			<p class="mgm-field"><label><input type="checkbox" name="mgm_event[registration]" value="1" <?php checked( '1', $g( 'registration', '1' ) ); ?>> <?php esc_html_e( 'Enable registration', 'mg-membership' ); ?></label>
				&nbsp; <label><?php esc_html_e( 'Capacity (0 = unlimited)', 'mg-membership' ); ?></label> <input type="number" min="0" name="mgm_event[capacity]" value="<?php echo esc_attr( $g( 'capacity', '0' ) ); ?>" style="width:90px">
				&nbsp; <label><input type="checkbox" name="mgm_event[waitlist]" value="1" <?php checked( '1', $g( 'waitlist' ) ); ?>> <?php esc_html_e( 'Waitlist when full', 'mg-membership' ); ?></label></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Registration closes', 'mg-membership' ); ?></label> <input type="datetime-local" name="mgm_event[reg_close]" value="<?php echo esc_attr( $dt( $g( 'reg_close' ) ) ); ?>"></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Who can register', 'mg-membership' ); ?></label>
				<select name="mgm_event[access]">
					<option value="everyone" <?php selected( $access, 'everyone' ); ?>><?php esc_html_e( 'Everyone (guests too)', 'mg-membership' ); ?></option>
					<option value="logged_in" <?php selected( $access, 'logged_in' ); ?>><?php esc_html_e( 'Logged-in users', 'mg-membership' ); ?></option>
					<option value="members" <?php selected( $access, 'members' ); ?>><?php esc_html_e( 'Members only (selected below)', 'mg-membership' ); ?></option>
					<option value="ticket" <?php selected( $access, 'ticket' ); ?>><?php esc_html_e( 'Paid ticket (buy the ticket membership)', 'mg-membership' ); ?></option>
				</select></p>
			<div class="mgm-field"><label><?php esc_html_e( 'Members of (for "Members only"; none = any membership)', 'mg-membership' ); ?></label>
				<?php echo MGM_Admin_Metaboxes::memberships_checklist( 'mgm_event[memberships]', mgm_int_list( $g( 'memberships', array() ) ) ); // phpcs:ignore ?></div>
			<p class="mgm-field"><label><?php esc_html_e( 'Ticket membership (buying it auto-registers the buyer – create a one-time membership as the ticket)', 'mg-membership' ); ?></label>
				<select name="mgm_event[ticket_membership]"><option value="0">—</option>
					<?php foreach ( mgm_membership_options() as $id => $t ) : ?>
						<option value="<?php echo (int) $id; ?>" <?php selected( (int) $g( 'ticket_membership' ), $id ); ?>><?php echo esc_html( $t ); ?></option>
					<?php endforeach; ?></select></p>

			<h4><?php esc_html_e( 'Program', 'mg-membership' ); ?></h4>
			<p class="mgm-field"><label><?php esc_html_e( 'Speakers (one per line: Name | Title)', 'mg-membership' ); ?></label>
				<textarea name="mgm_event[speakers]" rows="3" class="large-text"><?php echo esc_textarea( $g( 'speakers' ) ); ?></textarea></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Agenda (one per line: 09:00 | Session title)', 'mg-membership' ); ?></label>
				<textarea name="mgm_event[agenda]" rows="4" class="large-text"><?php echo esc_textarea( $g( 'agenda' ) ); ?></textarea></p>
			<p class="mgm-field"><label><input type="checkbox" name="mgm_custom_layout" value="1" <?php checked( '1', get_post_meta( $post->ID, '_mgm_custom_layout', true ) ); ?>> <?php esc_html_e( 'Custom page-builder layout (use event widgets instead of the default layout)', 'mg-membership' ); ?></label></p>
		</div>
		<?php
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['_mgm_event_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_event_nonce'] ), 'mgm_event_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in  = isset( $_POST['mgm_event'] ) ? wp_unslash( $_POST['mgm_event'] ) : array(); // phpcs:ignore
		$dtv = function ( $v ) {
			$v = sanitize_text_field( (string) $v );
			return $v ? str_replace( 'T', ' ', substr( $v, 0, 16 ) ) : '';
		};
		$old_start = MGM_Events::meta( $post_id, 'start' );
		foreach ( array( 'start', 'end', 'reg_close' ) as $k ) {
			update_post_meta( $post_id, '_mgm_event_' . $k, $dtv( $in[ $k ] ?? '' ) );
		}
		if ( $old_start !== $dtv( $in['start'] ?? '' ) ) {
			delete_post_meta( $post_id, '_mgm_event_reminded' );
		}
		foreach ( array( 'venue', 'address' ) as $k ) {
			update_post_meta( $post_id, '_mgm_event_' . $k, sanitize_text_field( $in[ $k ] ?? '' ) );
		}
		foreach ( array( 'online_url', 'recording_url' ) as $k ) {
			update_post_meta( $post_id, '_mgm_event_' . $k, esc_url_raw( $in[ $k ] ?? '' ) );
		}
		foreach ( array( 'speakers', 'agenda' ) as $k ) {
			update_post_meta( $post_id, '_mgm_event_' . $k, sanitize_textarea_field( $in[ $k ] ?? '' ) );
		}
		update_post_meta( $post_id, '_mgm_event_location_type', in_array( $in['location_type'] ?? '', array( 'in_person', 'online', 'hybrid' ), true ) ? $in['location_type'] : 'in_person' );
		update_post_meta( $post_id, '_mgm_event_access', in_array( $in['access'] ?? '', array( 'everyone', 'logged_in', 'members', 'ticket' ), true ) ? $in['access'] : 'everyone' );
		update_post_meta( $post_id, '_mgm_event_registration', empty( $in['registration'] ) ? '0' : '1' );
		update_post_meta( $post_id, '_mgm_event_waitlist', empty( $in['waitlist'] ) ? '' : '1' );
		update_post_meta( $post_id, '_mgm_event_capacity', absint( $in['capacity'] ?? 0 ) );
		update_post_meta( $post_id, '_mgm_event_memberships', mgm_int_list( $in['memberships'] ?? array() ) );
		update_post_meta( $post_id, '_mgm_event_ticket_membership', absint( $in['ticket_membership'] ?? 0 ) );
		update_post_meta( $post_id, '_mgm_custom_layout', empty( $_POST['mgm_custom_layout'] ) ? '' : '1' );
	}

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			if ( 'date' === $k ) {
				continue;
			}
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['mgm_when']      = __( 'When', 'mg-membership' );
				$new['mgm_attendees'] = __( 'Attendees', 'mg-membership' );
			}
		}
		return $new;
	}

	public static function column( $col, $id ) {
		if ( 'mgm_when' === $col ) {
			echo esc_html( MGM_Events::date_range( $id ) );
			if ( MGM_Events::is_past( $id ) ) {
				echo ' <span class="mgm-status mgm-status-expired">' . esc_html__( 'Past', 'mg-membership' ) . '</span>';
			}
		} elseif ( 'mgm_attendees' === $col ) {
			$cap = (int) MGM_Events::meta( $id, 'capacity', 0 );
			echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=mgm_event&page=mgm-attendees&event=' . $id ) ) . '">' . (int) MGM_Events::count_registered( $id ) . ( $cap ? ' / ' . (int) $cap : '' ) . '</a>';
		}
	}

	private static function url( $do, $args ) {
		return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'mgm_event_admin', 'do' => $do ), $args ), admin_url( 'admin-post.php' ) ), 'mgm_event_admin' );
	}

	public static function attendees_page() {
		MGM_Admin::header( __( 'Attendees & Check-in', 'mg-membership' ), array( 'desc' => __( 'Registrations, waitlists and check-in for each seminar or event.', 'mg-membership' ), 'actions' => array( array( 'label' => __( 'New event', 'mg-membership' ), 'url' => admin_url( 'post-new.php?post_type=mgm_event' ), 'primary' => true, 'icon' => 'dashicons-plus-alt2' ) ) ) );
		$events = get_posts( array( 'post_type' => 'mgm_event', 'numberposts' => -1, 'meta_key' => '_mgm_event_start', 'orderby' => 'meta_value', 'order' => 'DESC' ) ); // phpcs:ignore
		$eid    = absint( $_GET['event'] ?? ( $events ? $events[0]->ID : 0 ) ); // phpcs:ignore
		echo '<div class="mgm-toolbar"><form method="get" class="mgm-filter"><input type="hidden" name="post_type" value="mgm_event"><input type="hidden" name="page" value="mgm-attendees"><label class="mgm-filter-label" for="mgm-event-pick">' . esc_html__( 'Event', 'mg-membership' ) . '</label><select id="mgm-event-pick" name="event" onchange="this.form.submit()">';
		foreach ( $events as $e ) {
			echo '<option value="' . (int) $e->ID . '" ' . selected( $eid, $e->ID, false ) . '>' . esc_html( $e->post_title . ' — ' . MGM_Events::date_range( $e->ID ) ) . '</option>';
		}
		echo '</select></form></div>';
		if ( ! $eid ) {
			echo '<p>' . esc_html__( 'No events yet.', 'mg-membership' ) . '</p>';
			MGM_Admin::footer();
			return;
		}
		$rows = MGM_Events::registrations( $eid );
		$in   = count( array_filter( $rows, function ( $r ) { return $r->attended; } ) ); // phpcs:ignore
		printf(
			'<p><strong>%s</strong> · %s · %s &nbsp; <a class="button" href="%s">%s</a></p>',
			esc_html( sprintf( __( '%d registered', 'mg-membership' ), MGM_Events::count_registered( $eid ) ) ),
			esc_html( sprintf( __( '%d checked in', 'mg-membership' ), $in ) ),
			esc_html( sprintf( __( '%d left', 'mg-membership' ), max( 0, MGM_Events::seats_left( $eid ) ) ) ),
			esc_url( self::url( 'export', array( 'event' => $eid ) ) ),
			esc_html__( 'Export CSV', 'mg-membership' )
		);

		// Check-in by ticket code & manual add.
		echo '<div class="mgm-grid-2"><form class="mgm-panel" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'mgm_event_admin' );
		echo '<input type="hidden" name="action" value="mgm_event_admin"><input type="hidden" name="do" value="checkin_code"><input type="hidden" name="event" value="' . (int) $eid . '">';
		echo '<h3>' . esc_html__( 'Check in by ticket code', 'mg-membership' ) . '</h3><input type="text" name="code" placeholder="ABCD1234" autofocus> ';
		submit_button( __( 'Check in', 'mg-membership' ), 'primary', '', false );
		echo '</form><form class="mgm-panel" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'mgm_event_admin' );
		echo '<input type="hidden" name="action" value="mgm_event_admin"><input type="hidden" name="do" value="add"><input type="hidden" name="event" value="' . (int) $eid . '">';
		echo '<h3>' . esc_html__( 'Add attendee', 'mg-membership' ) . '</h3><input type="text" name="name" placeholder="' . esc_attr__( 'Name', 'mg-membership' ) . '"> <input type="email" name="email" required placeholder="' . esc_attr__( 'Email', 'mg-membership' ) . '"> ';
		submit_button( __( 'Add', 'mg-membership' ), 'secondary', '', false );
		echo '</form></div>';

		echo '<div class="mgm-table-card"><table class="widefat mgm-table"><thead><tr><th>#</th><th>' . esc_html__( 'Name', 'mg-membership' ) . '</th><th>' . esc_html__( 'Email', 'mg-membership' ) . '</th><th>' . esc_html__( 'Ticket', 'mg-membership' ) . '</th><th>' . esc_html__( 'Status', 'mg-membership' ) . '</th><th>' . esc_html__( 'Registered', 'mg-membership' ) . '</th><th>' . esc_html__( 'Attended', 'mg-membership' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $i => $r ) {
			printf(
				'<tr><td>%d</td><td>%s</td><td>%s</td><td><code>%s</code></td><td><span class="mgm-status mgm-status-%s">%s</span></td><td>%s</td><td>%s</td><td>%s %s</td></tr>',
				(int) $i + 1,
				esc_html( $r->name ),
				esc_html( $r->email ),
				esc_html( $r->ticket_code ),
				esc_attr( 'registered' === $r->status ? 'active' : ( 'waitlist' === $r->status ? 'pending' : 'expired' ) ),
				esc_html( ucfirst( $r->status ) ),
				esc_html( mgm_date( $r->created_at ) ),
				$r->attended ? '✅' : '—',
				'registered' === $r->status ? '<a class="button button-small" href="' . esc_url( self::url( 'toggle', array( 'reg' => $r->id ) ) ) . '">' . ( $r->attended ? esc_html__( 'Undo check-in', 'mg-membership' ) : esc_html__( 'Check in', 'mg-membership' ) ) . '</a>' : '', // phpcs:ignore
				'cancelled' !== $r->status ? '<a class="button button-small button-link-delete" href="' . esc_url( self::url( 'cancel', array( 'reg' => $r->id ) ) ) . '">' . esc_html__( 'Cancel', 'mg-membership' ) . '</a>' : '' // phpcs:ignore
			);
		}
		if ( ! $rows ) {
			echo '<tr class="mgm-empty-row"><td colspan="8"><span class="dashicons dashicons-tickets-alt" aria-hidden="true"></span><strong>' . esc_html__( 'No registrations yet', 'mg-membership' ) . '</strong><span>' . esc_html__( 'Share the event page, or add an attendee above.', 'mg-membership' ) . '</span></td></tr>';
		}
		echo '</tbody></table></div>';
		MGM_Admin::footer();
	}

	public static function actions() {
		global $wpdb;
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			wp_die();
		}
		check_admin_referer( 'mgm_event_admin' );
		$t    = MGM_Events::table();
		$do   = sanitize_key( $_REQUEST['do'] ?? '' );
		$back = remove_query_arg( 'mgm_msg', wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=mgm_event&page=mgm-attendees' ) );
		$msg  = __( 'Done.', 'mg-membership' );

		switch ( $do ) {
			case 'toggle':
				$id = absint( $_GET['reg'] ?? 0 );
				$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET attended = 1 - attended WHERE id = %d", $id ) ); // phpcs:ignore
				break;
			case 'cancel':
				MGM_Events::cancel_registration( absint( $_GET['reg'] ?? 0 ) );
				$msg = __( 'Registration cancelled.', 'mg-membership' );
				break;
			case 'checkin_code':
				$code = strtoupper( sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ) );
				$eid  = absint( $_POST['event'] ?? 0 );
				$n    = $wpdb->query( $wpdb->prepare( "UPDATE {$t} SET attended = 1 WHERE event_id = %d AND ticket_code = %s AND status = 'registered'", $eid, $code ) ); // phpcs:ignore
				$msg  = $n ? __( 'Checked in!', 'mg-membership' ) : __( 'Ticket code not found for this event.', 'mg-membership' );
				break;
			case 'add':
				$r   = MGM_Events::register_attendee(
					absint( $_POST['event'] ?? 0 ),
					array(
						'name'  => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
						'email' => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
					)
				);
				$msg = is_wp_error( $r ) ? $r->get_error_message() : __( 'Attendee added.', 'mg-membership' );
				break;
			case 'export':
				$eid = absint( $_GET['event'] ?? 0 );
				nocache_headers();
				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename=attendees-' . $eid . '.csv' );
				$out = fopen( 'php://output', 'w' );
				fputcsv( $out, MGM_Security_Core::csv_row( array( 'name', 'email', 'phone', 'ticket', 'status', 'attended', 'registered_at' ) ) );
				foreach ( MGM_Events::registrations( $eid ) as $r ) {
					fputcsv( $out, MGM_Security_Core::csv_row( array( $r->name, $r->email, $r->phone, $r->ticket_code, $r->status, $r->attended ? 'yes' : 'no', $r->created_at ) ) );
				}
				fclose( $out ); // phpcs:ignore
				exit;
		}
		wp_safe_redirect( add_query_arg( 'mgm_msg', rawurlencode( $msg ), $back ) );
		exit;
	}
}
