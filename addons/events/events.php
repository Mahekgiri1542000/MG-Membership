<?php
/**
 * Add-on: Seminars, Webinars & Events.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Events {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 6 );
		add_filter( 'the_content', array( __CLASS__, 'content' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 4 );
		add_filter( 'mgm_shortcodes_registry', array( __CLASS__, 'shortcodes' ) );
		add_filter( 'mgm_account_tabs', array( __CLASS__, 'account_tab' ) );
		add_action( 'mgm_account_tab_events', array( __CLASS__, 'account_tab_content' ) );
		add_action( 'mgm_transaction_completed', array( __CLASS__, 'ticket_purchase' ), 30 );
		add_action(
			'mgm_approval_approved',
			function ( $uid, $mid ) {
				self::ticket_purchase( (object) array( 'user_id' => $uid, 'membership_id' => $mid ) );
			},
			10,
			2
		);
		add_action( 'mgm_cron', array( __CLASS__, 'reminders' ) );
		add_filter( 'mgm_email_templates', array( __CLASS__, 'email_templates' ) );
		if ( is_admin() ) {
			require_once __DIR__ . '/class-mgm-events-admin.php';
			MGM_Events_Admin::init();
		}
	}

	public static function register() {
		register_post_type(
			'mgm_event',
			array(
				'labels'        => array(
					'name'          => __( 'Seminars & Events', 'mg-membership' ),
					'singular_name' => __( 'Event', 'mg-membership' ),
					'add_new_item'  => __( 'Add New Event / Seminar', 'mg-membership' ),
					'edit_item'     => __( 'Edit Event', 'mg-membership' ),
					'all_items'     => __( 'All Events', 'mg-membership' ),
					'menu_name'     => __( 'Seminars', 'mg-membership' ),
				),
				'public'        => true,
				'has_archive'   => true,
				'show_in_rest'  => true,
				'menu_icon'     => 'dashicons-calendar-alt',
				'menu_position' => 28,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'elementor' ),
				'rewrite'       => array( 'slug' => apply_filters( 'mgm_event_slug', 'events' ) ),
			)
		);
		register_taxonomy(
			'mgm_event_type',
			'mgm_event',
			array(
				'label'        => __( 'Event Types', 'mg-membership' ),
				'hierarchical' => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'event-type' ),
			)
		);
	}

	/* ------------------------------ Data ------------------------------- */

	public static function meta( $id, $key, $default = '' ) {
		$v = get_post_meta( $id, '_mgm_event_' . $key, true );
		return '' === $v ? $default : $v;
	}

	/** Start/end as site-local "Y-m-d H:i" → timestamp (UTC). */
	public static function ts( $id, $which = 'start' ) {
		$v = self::meta( $id, $which );
		if ( ! $v ) {
			return 0;
		}
		$dt = date_create( $v, wp_timezone() );
		return $dt ? $dt->getTimestamp() : 0;
	}

	public static function is_past( $id ) {
		$end = self::ts( $id, 'end' ) ? self::ts( $id, 'end' ) : self::ts( $id, 'start' );
		return $end && $end < time();
	}

	public static function date_range( $id ) {
		$s = self::ts( $id, 'start' );
		$e = self::ts( $id, 'end' );
		if ( ! $s ) {
			return '';
		}
		$df  = get_option( 'date_format' );
		$tf  = get_option( 'time_format' );
		$out = wp_date( $df . ' ' . $tf, $s );
		if ( $e ) {
			$out .= ' – ' . ( wp_date( 'Ymd', $s ) === wp_date( 'Ymd', $e ) ? wp_date( $tf, $e ) : wp_date( $df . ' ' . $tf, $e ) );
		}
		return $out . ' (' . wp_timezone_string() . ')';
	}

	public static function table() {
		return MGM_Install::table( 'event_registrations' );
	}

	public static function count_registered( $event_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE event_id = %d AND status = 'registered'", $event_id ) ); // phpcs:ignore
	}

	public static function seats_left( $event_id ) {
		$cap = (int) self::meta( $event_id, 'capacity', 0 );
		return $cap > 0 ? max( 0, $cap - self::count_registered( $event_id ) ) : -1; // -1 = unlimited.
	}

	public static function registration( $event_id, $user_id = 0, $email = '' ) {
		global $wpdb;
		if ( $user_id ) {
			return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE event_id = %d AND user_id = %d AND status != 'cancelled' ORDER BY id DESC LIMIT 1", $event_id, $user_id ) ); // phpcs:ignore
		}
		if ( $email ) {
			return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE event_id = %d AND email = %s AND status != 'cancelled' ORDER BY id DESC LIMIT 1", $event_id, $email ) ); // phpcs:ignore
		}
		return null;
	}

	public static function registrations( $event_id, $status = '' ) {
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE event_id = %d', $event_id ); // phpcs:ignore
		if ( $status ) {
			$sql .= $wpdb->prepare( ' AND status = %s', $status );
		}
		return $wpdb->get_results( $sql . ' ORDER BY id ASC' ); // phpcs:ignore
	}

	/**
	 * Who may register? everyone | logged_in | members | ticket.
	 *
	 * @return true|WP_Error
	 */
	public static function can_register( $event_id, $user_id = 0 ) {
		if ( 'publish' !== get_post_status( $event_id ) ) {
			return new WP_Error( 'mgm_event', __( 'Event not available.', 'mg-membership' ) );
		}
		if ( self::is_past( $event_id ) ) {
			return new WP_Error( 'mgm_event', __( 'This event has already taken place.', 'mg-membership' ) );
		}
		$close = self::meta( $event_id, 'reg_close' );
		if ( $close && date_create( $close, wp_timezone() ) && date_create( $close, wp_timezone() )->getTimestamp() < time() ) {
			return new WP_Error( 'mgm_event', __( 'Registration is closed.', 'mg-membership' ) );
		}
		if ( ! self::meta( $event_id, 'registration', '1' ) ) {
			return new WP_Error( 'mgm_event', __( 'Registration is not required for this event.', 'mg-membership' ) );
		}
		$access = self::meta( $event_id, 'access', 'everyone' );
		if ( 'logged_in' === $access && ! $user_id ) {
			return new WP_Error( 'mgm_event_login', __( 'Please log in to register.', 'mg-membership' ) );
		}
		if ( 'members' === $access ) {
			$ms = mgm_int_list( self::meta( $event_id, 'memberships', array() ) );
			if ( ! $user_id || ! mgm_user_has_membership( $ms ? $ms : 'any', $user_id ) ) {
				return new WP_Error( 'mgm_event_members', __( 'This event is for members only.', 'mg-membership' ) );
			}
		}
		if ( 'ticket' === $access ) {
			return new WP_Error( 'mgm_event_ticket', __( 'Please purchase a ticket to register.', 'mg-membership' ) );
		}
		return apply_filters( 'mgm_event_can_register', true, $event_id, $user_id );
	}

	/**
	 * Create a registration (or waitlist entry).
	 *
	 * @return object|WP_Error
	 */
	public static function register_attendee( $event_id, array $data ) {
		global $wpdb;
		$data = wp_parse_args( $data, array( 'user_id' => 0, 'name' => '', 'email' => '', 'phone' => '' ) );
		if ( $data['user_id'] && ! $data['email'] ) {
			$u             = get_userdata( $data['user_id'] );
			$data['email'] = $u ? $u->user_email : '';
			$data['name']  = $data['name'] ? $data['name'] : ( $u ? $u->display_name : '' );
		}
		if ( ! is_email( $data['email'] ) ) {
			return new WP_Error( 'mgm_event', __( 'A valid email is required.', 'mg-membership' ) );
		}
		$existing = self::registration( $event_id, $data['user_id'], $data['user_id'] ? '' : $data['email'] );
		if ( $existing ) {
			return $existing;
		}
		$left   = self::seats_left( $event_id );
		$status = 0 === $left ? ( self::meta( $event_id, 'waitlist' ) ? 'waitlist' : '' ) : 'registered';
		if ( ! $status ) {
			return new WP_Error( 'mgm_event_full', __( 'Sorry, this event is fully booked.', 'mg-membership' ) );
		}
		$wpdb->insert( // phpcs:ignore
			self::table(),
			array(
				'event_id'    => $event_id,
				'user_id'     => (int) $data['user_id'],
				'name'        => sanitize_text_field( $data['name'] ),
				'email'       => sanitize_email( $data['email'] ),
				'phone'       => sanitize_text_field( $data['phone'] ),
				'status'      => $status,
				'ticket_code' => strtoupper( wp_generate_password( 8, false ) ),
				'created_at'  => mgm_now(),
			)
		);
		$reg = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $wpdb->insert_id ) ); // phpcs:ignore
		do_action( 'mgm_event_registered', $reg, $event_id );
		self::send_confirmation( $reg );
		return $reg;
	}

	public static function cancel_registration( $reg_id ) {
		global $wpdb;
		$reg = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $reg_id ) ); // phpcs:ignore
		if ( ! $reg ) {
			return;
		}
		$wpdb->update( self::table(), array( 'status' => 'cancelled' ), array( 'id' => $reg_id ) ); // phpcs:ignore
		do_action( 'mgm_event_registration_cancelled', $reg );
		// Promote from waitlist.
		if ( 'registered' === $reg->status ) {
			$next = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE event_id = %d AND status = 'waitlist' ORDER BY id ASC LIMIT 1", $reg->event_id ) ); // phpcs:ignore
			if ( $next ) {
				$wpdb->update( self::table(), array( 'status' => 'registered' ), array( 'id' => $next->id ) ); // phpcs:ignore
				$next->status = 'registered';
				self::send_confirmation( $next );
			}
		}
	}

	/**
	 * Purchasing an event's ticket membership auto-registers the buyer.
	 */
	public static function ticket_purchase( $txn ) {
		if ( class_exists( 'MGM_Approval' ) && in_array( (int) $txn->membership_id, MGM_Approval::pending( (int) $txn->user_id ), true ) ) {
			return; // Registered once approved (mgm_approval_approved).
		}
		$events = get_posts(
			array(
				'post_type'   => 'mgm_event',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_mgm_event_ticket_membership', // phpcs:ignore
				'meta_value'  => (int) $txn->membership_id, // phpcs:ignore
			)
		);
		foreach ( $events as $eid ) {
			if ( ! self::is_past( $eid ) ) {
				self::register_attendee( $eid, array( 'user_id' => (int) $txn->user_id ) );
			}
		}
	}

	public static function email_templates( $templates ) {
		$templates['event_registered'] = array(
			'label'   => __( 'Event registration confirmation', 'mg-membership' ),
			'subject' => __( 'You are registered: {event}', 'mg-membership' ),
			'body'    => __( "Hi {name},\n\nYou're registered for {event}.\n\nWhen: {event_date}\nWhere: {event_location}\nTicket: {ticket}\n\nAdd to calendar: {ics_url}\nDetails: {event_url}\n\n{site_name}", 'mg-membership' ),
		);
		$templates['event_waitlist']   = array(
			'label'   => __( 'Event waitlist confirmation', 'mg-membership' ),
			'subject' => __( 'You are on the waitlist: {event}', 'mg-membership' ),
			'body'    => __( "Hi {name},\n\n{event} is currently full. You're on the waitlist and we'll email you if a seat opens.\n\n{site_name}", 'mg-membership' ),
		);
		$templates['event_reminder']   = array(
			'label'   => __( 'Event reminder (24h before)', 'mg-membership' ),
			'subject' => __( 'Reminder: {event} starts soon', 'mg-membership' ),
			'body'    => __( "Hi {name},\n\nA reminder that {event} starts {event_date}.\n\nWhere: {event_location}\n{join_link}\n\nSee you there!\n{site_name}", 'mg-membership' ),
		);
		return $templates;
	}

	public static function location_text( $event_id, $reveal_link = false ) {
		$type  = self::meta( $event_id, 'location_type', 'in_person' );
		$venue = self::meta( $event_id, 'venue' );
		$addr  = self::meta( $event_id, 'address' );
		$parts = array();
		if ( 'online' !== $type ) {
			$parts[] = trim( $venue . ( $addr ? ', ' . $addr : '' ), ', ' );
		}
		if ( 'in_person' !== $type ) {
			$parts[] = $reveal_link && self::meta( $event_id, 'online_url' ) ? self::meta( $event_id, 'online_url' ) : __( 'Online', 'mg-membership' );
		}
		return implode( ' · ', array_filter( $parts ) );
	}

	private static function email_vars( $reg ) {
		$eid = (int) $reg->event_id;
		$url = self::meta( $eid, 'online_url' );
		return array(
			'name'           => $reg->name,
			'event'          => get_the_title( $eid ),
			'event_url'      => get_permalink( $eid ),
			'event_date'     => self::date_range( $eid ),
			'event_location' => self::location_text( $eid, 'registered' === $reg->status ),
			'ticket'         => $reg->ticket_code,
			'ics_url'        => self::ics_url( $eid ),
			'join_link'      => $url && 'registered' === $reg->status ? sprintf( __( 'Join link: %s', 'mg-membership' ), $url ) : '',
		);
	}

	public static function send_confirmation( $reg ) {
		MGM_Emails::send( 'waitlist' === $reg->status ? 'event_waitlist' : 'event_registered', $reg->email, self::email_vars( $reg ) );
	}

	public static function reminders() {
		$events = get_posts(
			array(
				'post_type'   => 'mgm_event',
				'numberposts' => 50,
				'meta_key'    => '_mgm_event_start', // phpcs:ignore
				'orderby'     => 'meta_value',
				'order'       => 'ASC',
				'meta_query'  => array( array( 'key' => '_mgm_event_start', 'value' => wp_date( 'Y-m-d H:i' ), 'compare' => '>=' ) ), // phpcs:ignore
			)
		);
		foreach ( $events as $e ) {
			$start = self::ts( $e->ID );
			if ( $start - time() > DAY_IN_SECONDS || $start < time() || get_post_meta( $e->ID, '_mgm_event_reminded', true ) ) {
				continue;
			}
			update_post_meta( $e->ID, '_mgm_event_reminded', 1 );
			foreach ( self::registrations( $e->ID, 'registered' ) as $reg ) {
				MGM_Emails::send( 'event_reminder', $reg->email, self::email_vars( $reg ) );
			}
		}
	}

	public static function ics_url( $event_id ) {
		return add_query_arg( 'mgm_ics', (int) $event_id, home_url( '/' ) );
	}

	public static function google_calendar_url( $event_id ) {
		$fmt = 'Ymd\THis\Z';
		$s   = self::ts( $event_id );
		$e   = self::ts( $event_id, 'end' ) ? self::ts( $event_id, 'end' ) : $s + HOUR_IN_SECONDS;
		return add_query_arg(
			array(
				'action'   => 'TEMPLATE',
				'text'     => rawurlencode( get_the_title( $event_id ) ),
				'dates'    => gmdate( $fmt, $s ) . '/' . gmdate( $fmt, $e ),
				'details'  => rawurlencode( get_permalink( $event_id ) ),
				'location' => rawurlencode( self::location_text( $event_id ) ),
			),
			'https://calendar.google.com/calendar/render'
		);
	}

	/* ----------------------------- Requests ---------------------------- */

	public static function handle() {
		// ICS download.
		if ( isset( $_GET['mgm_ics'] ) ) { // phpcs:ignore
			$id = absint( $_GET['mgm_ics'] ); // phpcs:ignore
			if ( 'mgm_event' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
				$fmt = 'Ymd\THis\Z';
				$s   = self::ts( $id );
				$e   = self::ts( $id, 'end' ) ? self::ts( $id, 'end' ) : $s + HOUR_IN_SECONDS;
				$esc = function ( $t ) {
					return addcslashes( wp_strip_all_tags( (string) $t ), ",;\\" );
				};
				nocache_headers();
				header( 'Content-Type: text/calendar; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename=event-' . $id . '.ics' );
				echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//MG Membership//EN\r\nBEGIN:VEVENT\r\n";
				echo 'UID:mgm-event-' . (int) $id . '@' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . "\r\n";
				echo 'DTSTAMP:' . esc_html( gmdate( $fmt ) ) . "\r\n";
				echo 'DTSTART:' . esc_html( gmdate( $fmt, $s ) ) . "\r\nDTEND:" . esc_html( gmdate( $fmt, $e ) ) . "\r\n";
				echo 'SUMMARY:' . $esc( get_the_title( $id ) ) . "\r\n"; // phpcs:ignore
				echo 'URL:' . esc_url_raw( get_permalink( $id ) ) . "\r\n";
				echo 'LOCATION:' . $esc( self::location_text( $id ) ) . "\r\n"; // phpcs:ignore
				echo "END:VEVENT\r\nEND:VCALENDAR\r\n";
				exit;
			}
		}

		if ( empty( $_POST['mgm_event_action'] ) || ! isset( $_POST['_mgm_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_nonce'] ), 'mgm_event' ) ) {
			return;
		}
		$event_id = absint( $_POST['event_id'] ?? 0 );
		$user_id  = get_current_user_id();
		$action   = sanitize_key( $_POST['mgm_event_action'] );

		if ( 'register' === $action ) {
			if ( ! empty( $_POST['mgm_website'] ) ) {
				return; // Honeypot.
			}
			if ( ! $user_id && ! MGM_Security_Core::hit( 'event_guest' ) ) {
				mgm_add_notice( __( 'Too many registrations from your network. Please try again later.', 'mg-membership' ) );
				return;
			}
			$can = self::can_register( $event_id, $user_id );
			if ( is_wp_error( $can ) ) {
				mgm_add_notice( $can->get_error_message() );
				return;
			}
			$reg = self::register_attendee(
				$event_id,
				array(
					'user_id' => $user_id,
					'name'    => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
					'email'   => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
					'phone'   => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
				)
			);
			if ( is_wp_error( $reg ) ) {
				mgm_add_notice( $reg->get_error_message() );
				return;
			}
			mgm_add_notice( 'waitlist' === $reg->status ? __( 'The event is full – you have been added to the waitlist.', 'mg-membership' ) : __( 'You are registered! A confirmation email is on its way.', 'mg-membership' ), 'success' );
		}

		if ( 'cancel' === $action && $user_id ) {
			$reg = self::registration( $event_id, $user_id );
			if ( $reg ) {
				self::cancel_registration( $reg->id );
				mgm_add_notice( __( 'Your registration has been cancelled.', 'mg-membership' ), 'success' );
			}
		}
	}

	/* ---------------------------- Rendering ---------------------------- */

	public static function content( $content ) {
		if ( ! is_singular( 'mgm_event' ) || ! in_the_loop() || ! is_main_query() || get_post_meta( get_the_ID(), '_mgm_custom_layout', true ) ) {
			return $content;
		}
		return mgm_get_template( 'events/single-event.php', array( 'event_id' => get_the_ID(), 'content' => $content ) );
	}

	public static function shortcodes( $registry ) {
		$events = array( '' => __( 'Current event', 'mg-membership' ) );
		foreach ( get_posts( array( 'post_type' => 'mgm_event', 'numberposts' => 200 ) ) as $p ) {
			$events[ $p->ID ] = $p->post_title;
		}
		$types = array( '' => __( 'All types', 'mg-membership' ) );
		$terms = get_terms( array( 'taxonomy' => 'mgm_event_type', 'hide_empty' => false ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$types[ $t->slug ] = $t->name;
			}
		}
		$registry['mgm_events']          = array(
			'label'    => __( 'Events / Seminars List', 'mg-membership' ),
			'icon'     => 'eicon-calendar',
			'callback' => array( __CLASS__, 'sc_events' ),
			'atts'     => array(
				'show'    => array( 'type' => 'select', 'label' => __( 'Show', 'mg-membership' ), 'default' => 'upcoming', 'options' => array( 'upcoming' => __( 'Upcoming', 'mg-membership' ), 'past' => __( 'Past', 'mg-membership' ), 'all' => __( 'All', 'mg-membership' ) ) ),
				'type'    => array( 'type' => 'select', 'label' => __( 'Event type', 'mg-membership' ), 'default' => '', 'options' => $types ),
				'layout'  => array( 'type' => 'select', 'label' => __( 'Layout', 'mg-membership' ), 'default' => 'list', 'options' => array( 'list' => __( 'List', 'mg-membership' ), 'grid' => __( 'Grid', 'mg-membership' ) ) ),
				'limit'   => array( 'type' => 'number', 'label' => __( 'Number of events', 'mg-membership' ), 'default' => '10' ),
			),
		);
		$registry['mgm_event_register']  = array(
			'label'    => __( 'Event Registration Form', 'mg-membership' ),
			'icon'     => 'eicon-form-horizontal',
			'callback' => array( __CLASS__, 'sc_register' ),
			'atts'     => array( 'id' => array( 'type' => 'select', 'label' => __( 'Event', 'mg-membership' ), 'default' => '', 'options' => $events ) ),
		);
		$registry['mgm_event_countdown'] = array(
			'label'    => __( 'Event Countdown', 'mg-membership' ),
			'icon'     => 'eicon-countdown',
			'callback' => array( __CLASS__, 'sc_countdown' ),
			'atts'     => array( 'id' => array( 'type' => 'select', 'label' => __( 'Event', 'mg-membership' ), 'default' => '', 'options' => $events ) ),
		);
		$registry['mgm_event_details']   = array(
			'label'    => __( 'Event Details (date, venue, speakers)', 'mg-membership' ),
			'icon'     => 'eicon-info-box',
			'callback' => array( __CLASS__, 'sc_details' ),
			'atts'     => array( 'id' => array( 'type' => 'select', 'label' => __( 'Event', 'mg-membership' ), 'default' => '', 'options' => $events ) ),
		);
		$registry['mgm_my_events']       = array(
			'label'    => __( 'My Events', 'mg-membership' ),
			'icon'     => 'eicon-calendar',
			'callback' => array( __CLASS__, 'sc_my_events' ),
			'atts'     => array(),
		);
		return $registry;
	}

	private static function event_from( $atts ) {
		$id = absint( $atts['id'] ?? 0 );
		return $id ? $id : ( is_singular( 'mgm_event' ) ? get_the_ID() : 0 );
	}

	public static function sc_events( $atts ) {
		$atts = shortcode_atts( MGM_Shortcodes::defaults( 'mgm_events' ), (array) $atts );
		$now  = wp_date( 'Y-m-d H:i' );
		$args = array(
			'post_type'      => 'mgm_event',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $atts['limit'] ),
			'meta_key'       => '_mgm_event_start', // phpcs:ignore
			'orderby'        => 'meta_value',
			'order'          => 'past' === $atts['show'] ? 'DESC' : 'ASC',
		);
		if ( 'upcoming' === $atts['show'] ) {
			$args['meta_query'] = array( array( 'key' => '_mgm_event_start', 'value' => $now, 'compare' => '>=' ) ); // phpcs:ignore
		} elseif ( 'past' === $atts['show'] ) {
			$args['meta_query'] = array( array( 'key' => '_mgm_event_start', 'value' => $now, 'compare' => '<' ) ); // phpcs:ignore
		}
		if ( $atts['type'] ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'mgm_event_type', 'field' => 'slug', 'terms' => sanitize_title( $atts['type'] ) ) ); // phpcs:ignore
		}
		return mgm_get_template( 'events/list.php', array( 'events' => get_posts( $args ), 'layout' => $atts['layout'] ) );
	}

	public static function sc_register( $atts ) {
		$id = self::event_from( $atts );
		return $id ? mgm_get_template( 'events/register.php', array( 'event_id' => $id ) ) : '';
	}

	public static function sc_countdown( $atts ) {
		$id = self::event_from( $atts );
		$ts = $id ? self::ts( $id ) : 0;
		if ( ! $ts || $ts < time() ) {
			return '';
		}
		return '<div class="mgm-countdown" data-mgm-countdown="' . (int) $ts . '"><span data-d>0</span><small>' . esc_html__( 'days', 'mg-membership' ) . '</small><span data-h>0</span><small>' . esc_html__( 'hrs', 'mg-membership' ) . '</small><span data-m>0</span><small>' . esc_html__( 'min', 'mg-membership' ) . '</small><span data-s>0</span><small>' . esc_html__( 'sec', 'mg-membership' ) . '</small></div>';
	}

	public static function sc_details( $atts ) {
		$id = self::event_from( $atts );
		return $id ? mgm_get_template( 'events/details.php', array( 'event_id' => $id ) ) : '';
	}

	public static function sc_my_events() {
		if ( ! is_user_logged_in() ) {
			return MGM_Account::render_login();
		}
		return mgm_get_template( 'events/my-events.php', array( 'user_id' => get_current_user_id() ) );
	}

	public static function account_tab( $tabs ) {
		$new = array();
		foreach ( $tabs as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'home' === $k || 'courses' === $k ) {
				if ( ! isset( $new['events'] ) && ( 'courses' === $k || ! isset( $tabs['courses'] ) ) ) {
					$new['events'] = __( 'My Events', 'mg-membership' );
				}
			}
		}
		if ( ! isset( $new['events'] ) ) {
			$new['events'] = __( 'My Events', 'mg-membership' );
		}
		return $new;
	}

	public static function account_tab_content( $user ) {
		echo mgm_get_template( 'events/my-events.php', array( 'user_id' => $user->ID ) ); // phpcs:ignore
	}
}

MGM_Events::init();
