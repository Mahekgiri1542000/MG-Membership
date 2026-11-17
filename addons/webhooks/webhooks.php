<?php
/**
 * Add-on: Outgoing webhooks (Zapier, Make, n8n, Mailchimp automations, custom apps).
 *
 * Each endpoint: URL, secret, chosen events. Payload is JSON; header X-MGM-Signature is
 * "sha256=" . HMAC-SHA256(body, secret). Deliveries are queued during the request and sent on shutdown.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Webhooks {

	private static $queue = array();

	public static function events() {
		return apply_filters(
			'mgm_webhook_events',
			array(
				'member.created'              => __( 'New member signed up', 'mg-membership' ),
				'membership.granted'          => __( 'Membership granted (paid, free or manual)', 'mg-membership' ),
				'membership.revoked'          => __( 'Membership revoked / ended', 'mg-membership' ),
				'payment.completed'           => __( 'Payment completed', 'mg-membership' ),
				'payment.refunded'            => __( 'Payment refunded', 'mg-membership' ),
				'subscription.status_changed' => __( 'Subscription status changed (active, cancelled, expired…)', 'mg-membership' ),
				'course.completed'            => __( 'Course completed', 'mg-membership' ),
				'lesson.completed'            => __( 'Lesson completed', 'mg-membership' ),
				'event.registered'            => __( 'Event / seminar registration', 'mg-membership' ),
				'approval.pending'            => __( 'Member waiting for approval', 'mg-membership' ),
				'download.completed'          => __( 'Protected file downloaded', 'mg-membership' ),
			)
		);
	}

	public static function init() {
		add_action( 'mgm_new_member_signup', function ( $uid, $txn_id ) { self::emit( 'member.created', self::user( $uid ) + array( 'transaction_id' => (int) $txn_id ) ); }, 50, 2 );
		add_action( 'mgm_membership_granted', function ( $uid, $mid ) { self::emit( 'membership.granted', self::user( $uid ) + self::membership( $mid ) ); }, 50, 2 );
		add_action( 'mgm_membership_revoked', function ( $uid, $mid ) { self::emit( 'membership.revoked', self::user( $uid ) + self::membership( $mid ) ); }, 50, 2 );
		add_action( 'mgm_transaction_completed', function ( $txn ) {
			if ( (float) $txn->total > 0 ) {
				self::emit( 'payment.completed', self::user( $txn->user_id ) + self::membership( $txn->membership_id ) + self::txn( $txn ) );
			}
		}, 50 );
		add_action( 'mgm_transaction_refunded', function ( $id ) {
			$txn = MGM_Transaction::get( $id );
			if ( $txn ) {
				self::emit( 'payment.refunded', self::user( $txn->user_id ) + self::membership( $txn->membership_id ) + self::txn( $txn ) );
			}
		}, 50 );
		add_action( 'mgm_subscription_status_changed', function ( $sid, $new, $old ) {
			$sub = MGM_Subscription::get( $sid );
			if ( $sub ) {
				self::emit( 'subscription.status_changed', self::user( $sub->user_id ) + self::membership( $sub->membership_id ) + array( 'subscription_id' => (int) $sid, 'status' => $new, 'previous_status' => $old, 'gateway' => $sub->gateway ) );
			}
		}, 50, 3 );
		add_action( 'mgm_course_completed', function ( $uid, $cid ) { self::emit( 'course.completed', self::user( $uid ) + array( 'course_id' => (int) $cid, 'course' => get_the_title( $cid ) ) ); }, 50, 2 );
		add_action( 'mgm_lesson_completed', function ( $uid, $lid, $cid ) { self::emit( 'lesson.completed', self::user( $uid ) + array( 'lesson_id' => (int) $lid, 'lesson' => get_the_title( $lid ), 'course_id' => (int) $cid ) ); }, 50, 3 );
		add_action( 'mgm_event_registered', function ( $reg, $eid ) {
			self::emit( 'event.registered', array( 'email' => $reg->email, 'name' => $reg->name, 'user_id' => (int) $reg->user_id, 'event_id' => (int) $eid, 'event' => get_the_title( $eid ), 'status' => $reg->status, 'ticket' => $reg->ticket_code ) );
		}, 50, 2 );
		add_action( 'mgm_approval_pending', function ( $uid, $mid ) { self::emit( 'approval.pending', self::user( $uid ) + self::membership( $mid ) ); }, 50, 2 );
		add_action( 'mgm_file_downloaded', function ( $uid, $did ) { self::emit( 'download.completed', self::user( $uid ) + array( 'download_id' => (int) $did, 'file' => get_the_title( $did ) ) ); }, 50, 2 );
		add_action( 'shutdown', array( __CLASS__, 'flush' ), 0 );

		if ( is_admin() ) {
			add_action( 'mgm_admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'admin_post_mgm_webhooks', array( __CLASS__, 'handle' ) );
		}
	}

	/* ------------------------------ Data ------------------------------- */

	public static function endpoints() {
		$e = get_option( 'mgm_webhooks', array() );
		return is_array( $e ) ? $e : array();
	}

	private static function user( $uid ) {
		$u = get_userdata( (int) $uid );
		return array(
			'user_id'    => (int) $uid,
			'email'      => $u ? $u->user_email : '',
			'first_name' => $u ? $u->first_name : '',
			'last_name'  => $u ? $u->last_name : '',
			'username'   => $u ? $u->user_login : '',
		);
	}

	private static function membership( $mid ) {
		return array( 'membership_id' => (int) $mid, 'membership' => get_the_title( $mid ) );
	}

	private static function txn( $t ) {
		return array( 'transaction_id' => (int) $t->id, 'invoice' => $t->trans_num, 'amount' => (float) $t->amount, 'tax' => (float) $t->tax_amount, 'total' => (float) $t->total, 'currency' => mgm_currency(), 'gateway' => $t->gateway );
	}

	/**
	 * Queue an event for every endpoint subscribed to it.
	 */
	public static function emit( $event, array $data ) {
		foreach ( self::endpoints() as $i => $ep ) {
			if ( ! empty( $ep['active'] ) && in_array( $event, (array) ( $ep['events'] ?? array() ), true ) ) {
				self::$queue[] = array( $i, $event, $data );
			}
		}
	}

	public static function payload( $event, array $data ) {
		return wp_json_encode(
			array(
				'event'     => $event,
				'id'        => wp_generate_uuid4(),
				'timestamp' => gmdate( 'c' ),
				'site'      => home_url( '/' ),
				'data'      => $data,
			)
		);
	}

	public static function send( array $ep, $event, array $data, $timeout = 5 ) {
		$body = self::payload( $event, $data );
		$res  = wp_remote_post(
			$ep['url'],
			array(
				'timeout'     => $timeout,
				'redirection' => 2,
				'headers'     => array(
					'Content-Type'    => 'application/json',
					'User-Agent'      => 'MG-Membership-Webhooks/' . MGM_VERSION,
					'X-MGM-Event'     => $event,
					'X-MGM-Signature' => 'sha256=' . hash_hmac( 'sha256', $body, (string) ( $ep['secret'] ?? '' ) ),
				),
				'body'        => $body,
			)
		);
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		self::log( $ep['url'], $event, $code, is_wp_error( $res ) ? $res->get_error_message() : '' );
		return $code;
	}

	public static function flush() {
		if ( ! self::$queue ) {
			return;
		}
		if ( function_exists( 'fastcgi_finish_request' ) && ! headers_sent() ) {
			// Free the visitor before talking to external services (PHP-FPM).
			// phpcs:ignore
			@fastcgi_finish_request();
		}
		$eps = self::endpoints();
		foreach ( self::$queue as $q ) {
			if ( isset( $eps[ $q[0] ] ) ) {
				self::send( $eps[ $q[0] ], $q[1], $q[2] );
			}
		}
		self::$queue = array();
	}

	private static function log( $url, $event, $code, $error ) {
		$log = get_option( 'mgm_webhook_log', array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, array( 'time' => time(), 'url' => $url, 'event' => $event, 'code' => $code, 'error' => $error ) );
		update_option( 'mgm_webhook_log', array_slice( $log, 0, 30 ), false );
	}

	/* ------------------------------ Admin ------------------------------ */

	public static function menu( $cap ) {
		add_submenu_page( 'mgm', __( 'Webhooks', 'mg-membership' ), __( 'Webhooks', 'mg-membership' ), $cap, 'mgm-webhooks', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		$eps   = self::endpoints();
		$eps[] = array( 'url' => '', 'secret' => wp_generate_password( 32, false ), 'events' => array_keys( self::events() ), 'active' => '1', 'new' => true );
		MGM_Admin::header( __( 'Webhooks & Integrations', 'mg-membership' ), array( 'desc' => __( 'Send member events to Zapier, Make, n8n or your own app. Mailchimp, ConvertKit, HubSpot and CRMs connect through those tools.', 'mg-membership' ) ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'mgm_webhooks' );
		echo '<input type="hidden" name="action" value="mgm_webhooks"><input type="hidden" name="do" value="save">';
		foreach ( $eps as $i => $ep ) {
			echo '<section class="mgm-card"><h2 class="mgm-card-title">' . esc_html( empty( $ep['new'] ) ? sprintf( __( 'Endpoint %d', 'mg-membership' ), $i + 1 ) : __( 'Add an endpoint', 'mg-membership' ) ) . '</h2><div class="mgm-fields-grid">';
			echo '<div class="mgm-field-row"><label class="mgm-field-label" for="mgm-wh-url-' . (int) $i . '">' . esc_html__( 'URL', 'mg-membership' ) . '</label><div class="mgm-field-control"><input type="url" id="mgm-wh-url-' . (int) $i . '" name="eps[' . (int) $i . '][url]" value="' . esc_attr( $ep['url'] ) . '" placeholder="https://hooks.zapier.com/hooks/catch/…"></div></div>';
			echo '<div class="mgm-field-row"><label class="mgm-field-label">' . esc_html__( 'Signing secret', 'mg-membership' ) . '</label><div class="mgm-field-control"><input type="text" name="eps[' . (int) $i . '][secret]" value="' . esc_attr( $ep['secret'] ) . '"><p class="description">' . esc_html__( 'Verify requests with header X-MGM-Signature = sha256=HMAC-SHA256(body, secret).', 'mg-membership' ) . '</p></div></div>';
			echo '<div class="mgm-field-row"><span class="mgm-field-label">' . esc_html__( 'Events', 'mg-membership' ) . '</span><div class="mgm-field-control">';
			foreach ( self::events() as $k => $l ) {
				echo '<label style="display:block;margin:3px 0"><input type="checkbox" name="eps[' . (int) $i . '][events][]" value="' . esc_attr( $k ) . '" ' . checked( in_array( $k, (array) $ep['events'], true ), true, false ) . '> ' . esc_html( $l ) . ' <code>' . esc_html( $k ) . '</code></label>';
			}
			echo '</div></div><div class="mgm-field-row mgm-field-toggle"><label class="mgm-switch"><input type="checkbox" name="eps[' . (int) $i . '][active]" value="1" ' . checked( ! empty( $ep['active'] ), true, false ) . '><span class="mgm-switch-ui" aria-hidden="true"></span><span class="mgm-switch-label">' . esc_html__( 'Active', 'mg-membership' ) . '</span></label>';
			if ( empty( $ep['new'] ) ) {
				echo ' &nbsp; <a class="mgm-btn mgm-btn-sm" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mgm_webhooks&do=test&ep=' . $i ), 'mgm_webhooks' ) ) . '">' . esc_html__( 'Send test event', 'mg-membership' ) . '</a> <label style="margin-left:12px"><input type="checkbox" name="eps[' . (int) $i . '][delete]" value="1"> ' . esc_html__( 'Delete', 'mg-membership' ) . '</label>';
			}
			echo '</div></div></section>';
		}
		echo '<div class="mgm-savebar">';
		submit_button( __( 'Save webhooks', 'mg-membership' ), 'primary mgm-btn-submit', 'submit', false );
		echo '</div></form>';

		$log = get_option( 'mgm_webhook_log', array() );
		echo '<section class="mgm-card mgm-card-flush"><h2 class="mgm-card-title">' . esc_html__( 'Recent deliveries', 'mg-membership' ) . '</h2><div class="mgm-table-card"><table class="widefat mgm-table"><thead><tr><th>' . esc_html__( 'When', 'mg-membership' ) . '</th><th>' . esc_html__( 'Event', 'mg-membership' ) . '</th><th>' . esc_html__( 'URL', 'mg-membership' ) . '</th><th>' . esc_html__( 'Result', 'mg-membership' ) . '</th></tr></thead><tbody>';
		foreach ( (array) $log as $l ) {
			$ok = $l['code'] >= 200 && $l['code'] < 300;
			echo '<tr><td>' . esc_html( sprintf( __( '%s ago', 'mg-membership' ), human_time_diff( $l['time'] ) ) ) . '</td><td><code>' . esc_html( $l['event'] ) . '</code></td><td>' . esc_html( $l['url'] ) . '</td><td><span class="mgm-status mgm-status-' . ( $ok ? 'active' : 'failed' ) . '">' . esc_html( $l['code'] ? 'HTTP ' . $l['code'] : __( 'Failed', 'mg-membership' ) ) . '</span> ' . esc_html( $l['error'] ) . '</td></tr>';
		}
		if ( ! $log ) {
			echo '<tr class="mgm-empty-row"><td colspan="4"><strong>' . esc_html__( 'No deliveries yet', 'mg-membership' ) . '</strong><span>' . esc_html__( 'Use "Send test event" after saving an endpoint.', 'mg-membership' ) . '</span></td></tr>';
		}
		echo '</tbody></table></div></section>';
		MGM_Admin::footer();
	}

	public static function handle() {
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mg-membership' ) );
		}
		check_admin_referer( 'mgm_webhooks' );
		$do = sanitize_key( $_REQUEST['do'] ?? '' );
		if ( 'test' === $do ) {
			$eps  = self::endpoints();
			$i    = absint( $_GET['ep'] ?? -1 );
			$code = isset( $eps[ $i ] ) ? self::send( $eps[ $i ], 'test', array( 'message' => 'Hello from MG Membership', 'user_id' => get_current_user_id() ), 10 ) : 0;
			$msg  = $code >= 200 && $code < 300 ? sprintf( __( 'Test delivered (HTTP %d).', 'mg-membership' ), $code ) : sprintf( __( 'Test failed (HTTP %d). See the delivery log.', 'mg-membership' ), $code );
		} else {
			$out = array();
			foreach ( (array) wp_unslash( $_POST['eps'] ?? array() ) as $ep ) { // phpcs:ignore
				$url = esc_url_raw( trim( $ep['url'] ?? '' ), array( 'http', 'https' ) );
				if ( ! $url || ! empty( $ep['delete'] ) ) {
					continue;
				}
				$out[] = array(
					'url'    => $url,
					'secret' => sanitize_text_field( $ep['secret'] ?? '' ),
					'events' => array_values( array_intersect( (array) ( $ep['events'] ?? array() ), array_keys( self::events() ) ) ),
					'active' => empty( $ep['active'] ) ? '' : '1',
				);
			}
			update_option( 'mgm_webhooks', $out, false );
			$msg = __( 'Webhooks saved.', 'mg-membership' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=mgm-webhooks&mgm_msg=' . rawurlencode( $msg ) ) );
		exit;
	}
}

MGM_Webhooks::init();
