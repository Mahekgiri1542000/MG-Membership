<?php
/**
 * Demo data: MG Membership → Demo Data.
 *
 * Installs example plans, 10 members with payments and subscriptions, custom fields, a course and events,
 * so every screen has something to show. Everything created is tagged (`_mgm_demo`) and can be removed
 * again with one click; nothing else on the site is touched.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Demo {

	const OPTION = 'mgm_demo_data';
	const FLAG   = '_mgm_demo';

	public static function init() {
		add_action( 'mgm_admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'admin_post_mgm_demo', array( __CLASS__, 'handle' ) );
	}

	public static function menu( $cap ) {
		add_submenu_page( 'mgm', __( 'Demo Data', 'mg-membership' ), __( 'Demo Data', 'mg-membership' ), $cap, 'mgm-demo', array( __CLASS__, 'page' ) );
	}

	public static function installed() {
		$d = get_option( self::OPTION );
		return is_array( $d ) && ! empty( $d['users'] ) ? $d : null;
	}

	/* ------------------------------------------------------------------ */
	/* Screen                                                              */
	/* ------------------------------------------------------------------ */

	public static function page() {
		$d = self::installed();
		MGM_Admin::header( __( 'Demo Data', 'mg-membership' ), array( 'desc' => __( 'Fill the site with realistic example members, plans, payments, a course and events, so you can try every screen. Remove it again with one click.', 'mg-membership' ) ) );
		echo '<section class="mgm-card" style="max-width:820px">';
		if ( $d ) {
			echo '<h2 class="mgm-card-title">' . esc_html__( 'Demo data is installed', 'mg-membership' ) . '</h2>';
			echo '<p>' . esc_html( sprintf( __( 'Installed on %s.', 'mg-membership' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $d['time'] ) ) ) . '</p>';
			echo '<ul style="list-style:disc;padding-left:20px">';
			printf( '<li>%s</li>', esc_html( sprintf( _n( '%d member', '%d members', count( $d['users'] ), 'mg-membership' ), count( $d['users'] ) ) ) );
			printf( '<li>%s</li>', esc_html( sprintf( _n( '%d page/post (plans, course, lessons, events)', '%d pages/posts (plans, course, lessons, events)', count( $d['posts'] ), 'mg-membership' ), count( $d['posts'] ) ) ) );
			printf( '<li>%s</li>', esc_html( sprintf( __( 'Custom fields added: %s', 'mg-membership' ), $d['fields'] ? implode( ', ', $d['fields'] ) : '—' ) ) );
			echo '</ul><p>';
			foreach ( array( 'mgm-members' => __( 'View members', 'mg-membership' ), 'mgm' => __( 'View dashboard', 'mg-membership' ) ) as $slug => $label ) {
				echo '<a class="mgm-btn" style="margin-right:8px" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
			}
			echo '<a class="mgm-btn" href="' . esc_url( admin_url( 'users.php' ) ) . '">' . esc_html__( 'Users', 'mg-membership' ) . '</a></p>';
			self::form( 'remove', __( 'Remove demo data', 'mg-membership' ), __( 'Delete every demo member, plan, payment, course, event and the demo custom fields? Your own data is not touched.', 'mg-membership' ) );
		} else {
			echo '<h2 class="mgm-card-title">' . esc_html__( 'What will be added', 'mg-membership' ) . '</h2><ul style="list-style:disc;padding-left:20px">';
			foreach ( array(
				__( '3 plans: Starter (free), Pro (monthly, 7-day trial) and Business (yearly)', 'mg-membership' ),
				__( '10 members: active, on trial, cancelled, payment failed, expired, refunded and waiting for an offline payment', 'mg-membership' ),
				__( 'About 6 months of payment history, so Reports and the dashboard have charts', 'mg-membership' ),
				__( 'Custom fields: Company, Job title, Phone, Country, Newsletter, with answers', 'mg-membership' ),
				__( 'A course with 5 lessons and member progress (if the Courses add-on is on)', 'mg-membership' ),
				__( 'An upcoming webinar and a past meetup with registrations (if Seminars & Events is on)', 'mg-membership' ),
				__( 'Admin notes on some members', 'mg-membership' ),
			) as $li ) {
				echo '<li>' . esc_html( $li ) . '</li>';
			}
			echo '</ul><p class="mgm-muted">' . esc_html__( 'Demo members use @example.com addresses and no emails are sent. Everything is tagged, so "Remove demo data" deletes only what was added here.', 'mg-membership' ) . '</p>';
			self::form( 'install', __( 'Install demo data', 'mg-membership' ) );
		}
		echo '</section>';
		MGM_Admin::footer();
	}

	private static function form( $do, $label, $confirm = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:16px">';
		wp_nonce_field( 'mgm_demo' );
		echo '<input type="hidden" name="action" value="mgm_demo"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
		printf(
			'<button type="submit" class="mgm-btn %s"%s>%s</button></form>',
			'install' === $do ? 'mgm-btn-primary' : 'mgm-btn-danger',
			$confirm ? ' onclick="return confirm(&quot;' . esc_js( $confirm ) . '&quot;);"' : '',
			esc_html( $label )
		);
	}

	public static function handle() {
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mg-membership' ) );
		}
		check_admin_referer( 'mgm_demo' );
		$do = sanitize_key( $_POST['do'] ?? '' );
		if ( 'install' === $do && ! self::installed() ) {
			self::install();
			$msg = __( 'Demo data installed.', 'mg-membership' );
		} elseif ( 'remove' === $do ) {
			$n   = self::remove();
			$msg = sprintf( __( 'Demo data removed (%d members).', 'mg-membership' ), $n );
		} else {
			$msg = __( 'Nothing to do.', 'mg-membership' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=mgm-demo&mgm_msg=' . rawurlencode( $msg ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Install                                                             */
	/* ------------------------------------------------------------------ */

	private static $track = array( 'users' => array(), 'posts' => array(), 'fields' => array() );

	private static function post( $type, $title, $content = '', array $meta = array(), $parent = 0 ) {
		$id = wp_insert_post(
			array(
				'post_type'    => $type,
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_parent'  => $parent,
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, self::FLAG, 1 );
			foreach ( $meta as $k => $v ) {
				update_post_meta( $id, $k, $v );
			}
			self::$track['posts'][] = $id;
		}
		return (int) $id;
	}

	private static function ts( $days_ago, $hour = 10 ) {
		return gmdate( 'Y-m-d H:i:s', strtotime( '-' . (int) $days_ago . ' days ' . $hour . ':00:00 UTC', time() ) );
	}

	/** One payment; returns the transaction id. */
	private static function pay( $uid, $plan, $days_ago, $amount, $gateway, $expires_days, $sub = 0, $status = 'complete' ) {
		global $wpdb;
		$id = MGM_Transaction::create(
			array(
				'user_id'         => $uid,
				'membership_id'   => $plan,
				'subscription_id' => $sub,
				'amount'          => $amount,
				'total'           => $amount,
				'gateway'         => $gateway,
				'trans_num'       => 'demo-' . strtolower( wp_generate_password( 10, false ) ),
				'status'          => $status,
				'expires_at'      => null === $expires_days ? null : gmdate( 'Y-m-d H:i:s', strtotime( self::ts( $days_ago ) . ' UTC' ) + $expires_days * DAY_IN_SECONDS ),
			)
		);
		// Back-date it so reports show a real history.
		$wpdb->update( MGM_Transaction::table(), array( 'created_at' => self::ts( $days_ago ) ), array( 'id' => $id ) ); // phpcs:ignore
		return $id;
	}

	private static function sub( $uid, $plan, $status, $price, $unit, $started_days_ago, $next_days ) {
		global $wpdb;
		$id = MGM_Subscription::create(
			array(
				'user_id'         => $uid,
				'membership_id'   => $plan,
				'gateway'         => 'stripe',
				'gateway_sub_id'  => 'sub_demo_' . strtolower( wp_generate_password( 10, false ) ),
				'status'          => $status,
				'price'           => $price,
				'period'          => 1,
				'period_unit'     => $unit,
				'trial_days'      => 'trialing' === $status ? 7 : 0,
				'next_payment_at' => null === $next_days ? null : gmdate( 'Y-m-d H:i:s', time() + $next_days * DAY_IN_SECONDS ),
			)
		);
		$wpdb->update( MGM_Subscription::table(), array( 'created_at' => self::ts( $started_days_ago ) ), array( 'id' => $id ) ); // phpcs:ignore
		return $id;
	}

	public static function install() {
		global $wpdb;
		if ( self::installed() ) {
			return get_option( self::OPTION ); // Never install twice.
		}
		self::$track = array( 'users' => array(), 'posts' => array(), 'fields' => array() );
		add_filter( 'pre_wp_mail', '__return_true', 999 ); // Never email demo addresses.
		try {
			// Plans.
			$starter  = self::post( 'mgm_membership', __( 'Starter', 'mg-membership' ), __( 'Free access to the community and newsletter.', 'mg-membership' ), array( '_mgm_billing_type' => 'free', '_mgm_price' => '0', '_mgm_features' => "Community access\nMonthly newsletter\nFree preview lessons", '_mgm_signup_button_text' => 'Join free' ) );
			$pro      = self::post( 'mgm_membership', __( 'Pro', 'mg-membership' ), __( 'Everything you need to learn and grow.', 'mg-membership' ), array( '_mgm_billing_type' => 'recurring', '_mgm_price' => '499', '_mgm_period' => '1', '_mgm_period_unit' => 'month', '_mgm_trial_enabled' => '1', '_mgm_trial_days' => '7', '_mgm_trial_amount' => '0', '_mgm_highlight' => '1', '_mgm_badge' => 'Most popular', '_mgm_features' => "All courses\nLive webinars\nDownloadable resources\nCertificates", '_mgm_signup_button_text' => 'Start free trial', '_mgm_upgrade_group' => 'demo' ) );
			$business = self::post( 'mgm_membership', __( 'Business', 'mg-membership' ), __( 'For teams that learn together.', 'mg-membership' ), array( '_mgm_billing_type' => 'recurring', '_mgm_price' => '4999', '_mgm_period' => '1', '_mgm_period_unit' => 'year', '_mgm_features' => "Everything in Pro\n5 team seats\nPriority support\nQuarterly strategy call", '_mgm_signup_button_text' => 'Get Business', '_mgm_upgrade_group' => 'demo' ) );

			// Custom fields (only the ones the site does not have yet).
			$fields = MGM_Custom_Fields::fields();
			$have   = wp_list_pluck( $fields, 'key' );
			foreach ( array(
				array( 'key' => 'company', 'label' => 'Company', 'type' => 'text', 'show_on' => 'both', 'placeholder' => 'Acme Ltd' ),
				array( 'key' => 'job_title', 'label' => 'Job title', 'type' => 'text', 'show_on' => 'both' ),
				array( 'key' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'show_on' => 'profile' ),
				array( 'key' => 'country', 'label' => 'Country', 'type' => 'select', 'options' => 'India, United States, United Kingdom, Germany, Spain, Australia, Canada, United Arab Emirates', 'show_on' => 'both' ),
				array( 'key' => 'newsletter', 'label' => 'Send me the newsletter', 'type' => 'checkbox', 'show_on' => 'both', 'admin_column' => '' ),
			) as $f ) {
				if ( in_array( $f['key'], $have, true ) ) {
					continue;
				}
				$fields[]                 = array_merge( array( 'options' => '', 'required' => '', 'public' => '', 'placeholder' => '', 'help' => '', 'memberships' => array(), 'admin_column' => '1', 'demo' => '1' ), $f );
				self::$track['fields'][]  = $f['key'];
			}
			update_option( 'mgm_custom_fields', array_values( $fields ) );

			// Course (Courses add-on).
			$course  = 0;
			$lessons = array();
			if ( class_exists( 'MGM_Courses' ) ) {
				$course = self::post( 'mgm_course', __( 'Web Design Fundamentals', 'mg-membership' ), __( 'Learn layout, colour and typography and build your first responsive page.', 'mg-membership' ), array( '_mgm_course_access' => 'members', '_mgm_course_memberships' => array( $pro, $business ), '_mgm_course_level' => 'beginner', '_mgm_course_duration' => '2h 30m', '_mgm_course_sequential' => '' ) );
				foreach ( array( 'Welcome and course overview', 'Layout and grids', 'Colour that works', 'Typography basics', 'Build a responsive page' ) as $i => $t ) {
					$lessons[] = self::post( 'mgm_lesson', $t, '<p>' . esc_html( $t ) . ' – demo lesson content.</p>', array( '_mgm_course_id' => $course, '_mgm_lesson_type' => 'text', '_mgm_lesson_duration' => '25', '_mgm_preview' => 0 === $i ? '1' : '' ) );
				}
				update_post_meta(
					$course,
					'_mgm_curriculum',
					array(
						array( 'title' => __( 'Getting started', 'mg-membership' ), 'lessons' => array_slice( $lessons, 0, 2 ) ),
						array( 'title' => __( 'Design skills', 'mg-membership' ), 'lessons' => array_slice( $lessons, 2 ) ),
					)
				);
			}

			// Events (Seminars & Events add-on).
			$webinar = 0;
			$meetup  = 0;
			if ( class_exists( 'MGM_Events' ) ) {
				$webinar = self::post( 'mgm_event', __( 'Live Q&A: Building Your Portfolio', 'mg-membership' ), __( 'Bring your questions – we review portfolios live.', 'mg-membership' ), array( '_mgm_event_start' => wp_date( 'Y-m-d 18:00', strtotime( '+6 days' ) ), '_mgm_event_end' => wp_date( 'Y-m-d 19:00', strtotime( '+6 days' ) ), '_mgm_event_capacity' => 50, '_mgm_event_access' => 'everyone', '_mgm_event_registration' => '1', '_mgm_event_location_type' => 'online', '_mgm_event_online_url' => 'https://meet.example.com/demo-webinar', '_mgm_event_speakers' => "Maya Rao | Lead Designer\nTom Becker | Hiring Manager", '_mgm_event_agenda' => "18:00 | Welcome\n18:10 | Portfolio reviews\n18:45 | Open questions" ) );
				$meetup  = self::post( 'mgm_event', __( 'Members Kick-off Meetup', 'mg-membership' ), __( 'Our first in-person meetup.', 'mg-membership' ), array( '_mgm_event_start' => wp_date( 'Y-m-d 17:00', strtotime( '-20 days' ) ), '_mgm_event_capacity' => 30, '_mgm_event_access' => 'everyone', '_mgm_event_registration' => '1', '_mgm_event_location_type' => 'in_person', '_mgm_event_venue' => 'Demo Hall', '_mgm_event_address' => '1 Example Street' ) );
			}

			// Members: name, country, company, job, days since joining, scenario.
			$people = array(
				array( 'Aarav', 'Sharma', 'India', 'Pixel Forge', 'UI Designer', 170, 'pro_active' ),
				array( 'Emma', 'Johnson', 'United States', 'Brightline Media', 'Marketing Lead', 150, 'business_active' ),
				array( 'Liam', 'Smith', 'United Kingdom', 'Northwind', 'Developer', 4, 'pro_trial' ),
				array( 'Priya', 'Patel', 'India', 'Freelance', 'Illustrator', 60, 'starter' ),
				array( 'Noah', 'Williams', 'Canada', 'Maple Apps', 'Product Manager', 120, 'pro_cancelled' ),
				array( 'Sofia', 'García', 'Spain', 'Estudio Sol', 'Art Director', 95, 'pro_failed' ),
				array( 'Mohammed', 'Khan', 'United Arab Emirates', 'Desert Labs', 'Founder', 2, 'business_pending' ),
				array( 'Olivia', 'Brown', 'Australia', 'Koala Creative', 'Copywriter', 140, 'pro_expired' ),
				array( 'Lukas', 'Müller', 'Germany', 'Berlin Works', 'Front-end Engineer', 80, 'pro_refunded' ),
				array( 'Ananya', 'Iyer', 'India', 'Lotus Digital', 'UX Researcher', 45, 'business_active2' ),
			);
			foreach ( $people as $i => $p ) {
				list( $first, $last, $country, $company, $job, $joined, $scenario ) = $p;
				$login = 'demo_' . sanitize_user( strtolower( remove_accents( $first . '_' . $last ) ), true );
				if ( username_exists( $login ) ) {
					$login .= '_' . wp_rand( 100, 999 );
				}
				$uid = wp_insert_user(
					array(
						'user_login'      => $login,
						'user_email'      => $login . '@example.com',
						'user_pass'       => wp_generate_password( 24 ),
						'first_name'      => $first,
						'last_name'       => $last,
						'display_name'    => $first . ' ' . $last,
						'role'            => 'subscriber',
						'user_registered' => self::ts( $joined, 9 ),
						'description'     => sprintf( '%s at %s.', $job, $company ),
					)
				);
				if ( is_wp_error( $uid ) ) {
					continue;
				}
				update_user_meta( $uid, self::FLAG, 1 );
				self::$track['users'][] = $uid;
				foreach ( array( 'company' => $company, 'job_title' => $job, 'phone' => '+1 555 01' . sprintf( '%02d', $i + 10 ), 'country' => $country, 'newsletter' => 0 === $i % 3 ? '' : '1' ) as $k => $v ) {
					update_user_meta( $uid, 'mgm_field_' . $k, $v );
				}

				switch ( $scenario ) {
					case 'pro_active': // 6 monthly payments, renews soon.
						$s = self::sub( $uid, $pro, 'active', 499, 'month', $joined, 27 );
						foreach ( array( 153, 123, 93, 63, 33, 3 ) as $d ) {
							self::pay( $uid, $pro, $d, 499, 'stripe', 30, $s );
						}
						break;
					case 'business_active':
						$s = self::sub( $uid, $business, 'active', 4999, 'year', $joined, 215 );
						self::pay( $uid, $business, 150, 4999, 'stripe', 365, $s );
						break;
					case 'business_active2':
						$s = self::sub( $uid, $business, 'active', 4999, 'year', $joined, 320 );
						self::pay( $uid, $business, 45, 4999, 'paypal', 365, $s );
						break;
					case 'pro_trial':
						$s = self::sub( $uid, $pro, 'trialing', 499, 'month', $joined, 3 );
						self::pay( $uid, $pro, $joined, 0, 'stripe', 7, $s );
						break;
					case 'starter':
						self::pay( $uid, $starter, $joined, 0, 'manual', null );
						break;
					case 'pro_cancelled': // Cancelled, keeps access until the paid month ends.
						$s = self::sub( $uid, $pro, 'cancelled', 499, 'month', $joined, null );
						foreach ( array( 120, 90, 60, 30, 12 ) as $d ) {
							self::pay( $uid, $pro, $d, 499, 'stripe', 30, $s );
						}
						break;
					case 'pro_failed': // Last renewal failed.
						$s = self::sub( $uid, $pro, 'suspended', 499, 'month', $joined, 0 );
						foreach ( array( 95, 65, 35 ) as $d ) {
							self::pay( $uid, $pro, $d, 499, 'stripe', 30, $s );
						}
						self::pay( $uid, $pro, 5, 499, 'stripe', 30, $s, 'failed' );
						break;
					case 'business_pending': // Chose bank transfer, not paid yet.
						self::pay( $uid, $business, $joined, 4999, 'offline', 365, 0, 'pending' );
						break;
					case 'pro_expired':
						$s = self::sub( $uid, $pro, 'expired', 499, 'month', $joined, null );
						foreach ( array( 140, 110, 80 ) as $d ) {
							self::pay( $uid, $pro, $d, 499, 'paypal', 30, $s );
						}
						break;
					case 'pro_refunded':
						$s = self::sub( $uid, $pro, 'active', 499, 'month', $joined, 24 );
						foreach ( array( 80, 50 ) as $d ) {
							self::pay( $uid, $pro, $d, 499, 'stripe', 30, $s );
						}
						self::pay( $uid, $pro, 40, 499, 'stripe', 0, $s, 'refunded' );
						self::pay( $uid, $pro, 6, 499, 'stripe', 30, $s );
						break;
				}
				MGM_Member::flush_cache( $uid );

				// Course progress.
				if ( $course && $lessons ) {
					$done = array( 0 => 5, 1 => 3, 4 => 2, 8 => 4, 9 => 2 )[ $i ] ?? 0;
					if ( $done ) {
						MGM_Courses::enroll( $uid, $course );
						foreach ( array_slice( $lessons, 0, $done ) as $n => $lid ) {
							MGM_Courses::complete_lesson( $uid, $lid );
							$wpdb->update( MGM_Install::table( 'course_progress' ), array( 'completed_at' => self::ts( max( 1, 30 - $n * 5 ) ) ), array( 'user_id' => $uid, 'lesson_id' => $lid ) ); // phpcs:ignore
						}
					}
				}
				// Event registrations.
				if ( $webinar && in_array( $i, array( 0, 1, 2, 5, 9 ), true ) ) {
					MGM_Events::register_attendee( $webinar, array( 'user_id' => $uid ) );
				}
				if ( $meetup && in_array( $i, array( 0, 1, 4, 7, 8 ), true ) ) {
					$reg = MGM_Events::register_attendee( $meetup, array( 'user_id' => $uid ) );
					if ( $reg && ! is_wp_error( $reg ) && 7 !== $i ) {
						$wpdb->update( MGM_Events::table(), array( 'attended' => 1 ), array( 'id' => $reg->id ) ); // phpcs:ignore
					}
				}
				// Admin notes.
				$notes = array(
					5 => 'Card expired – sent a reminder to update payment details.',
					6 => 'Asked to pay by bank transfer. Mark the payment complete when it arrives.',
					8 => 'Refunded one month after a double charge.',
				);
				if ( isset( $notes[ $i ] ) ) {
					update_user_meta( $uid, '_mgm_admin_notes', array( array( 'text' => $notes[ $i ], 'by' => get_current_user_id(), 'time' => time() - DAY_IN_SECONDS ) ) );
				}
			}
		} finally {
			remove_filter( 'pre_wp_mail', '__return_true', 999 );
			update_option( self::OPTION, array_merge( self::$track, array( 'time' => time() ) ), false );
		}
		return self::$track;
	}

	/* ------------------------------------------------------------------ */
	/* Remove                                                              */
	/* ------------------------------------------------------------------ */

	public static function remove() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$d = (array) get_option( self::OPTION, array() );

		// Everything tagged as demo, plus anything recorded at install time.
		$users = array_unique( array_merge( array_map( 'intval', (array) ( $d['users'] ?? array() ) ), array_map( 'intval', get_users( array( 'meta_key' => self::FLAG, 'fields' => 'ID' ) ) ) ) );
		$posts = array_unique( array_merge( array_map( 'intval', (array) ( $d['posts'] ?? array() ) ), array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", self::FLAG ) ) ) ) ); // phpcs:ignore

		$removed = 0;
		foreach ( $users as $uid ) {
			if ( ! $uid || ! get_user_meta( $uid, self::FLAG, true ) ) {
				continue; // Only ever delete users this tool created.
			}
			$u = get_userdata( $uid );
			foreach ( array( 'transactions', 'subscriptions', 'course_progress', 'quiz_attempts', 'activity' ) as $t ) {
				$table = MGM_Install::table( $t );
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) { // phpcs:ignore
					$wpdb->delete( $table, array( 'user_id' => $uid ) ); // phpcs:ignore
				}
			}
			$reg = MGM_Install::table( 'event_registrations' );
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $reg ) ) === $reg ) { // phpcs:ignore
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$reg} WHERE user_id = %d OR email = %s", $uid, $u ? $u->user_email : '' ) ); // phpcs:ignore
			}
			wp_delete_user( $uid );
			++$removed;
		}
		foreach ( $posts as $pid ) {
			if ( $pid && get_post_meta( $pid, self::FLAG, true ) ) {
				wp_delete_post( $pid, true );
			}
		}
		// Custom fields that the demo added (fields you already had are kept).
		$added = (array) ( $d['fields'] ?? array() );
		foreach ( MGM_Custom_Fields::fields() as $f ) {
			if ( ! empty( $f['demo'] ) ) {
				$added[] = $f['key']; // Tagged when the demo created it.
			}
		}
		if ( $added ) {
			$keep = array_values(
				array_filter(
					MGM_Custom_Fields::fields(),
					function ( $f ) use ( $added ) {
						return ! in_array( $f['key'], $added, true );
					}
				)
			);
			if ( $keep ) {
				update_option( 'mgm_custom_fields', $keep );
			} else {
				delete_option( 'mgm_custom_fields' );
			}
		}
		delete_option( self::OPTION );
		return $removed;
	}
}
