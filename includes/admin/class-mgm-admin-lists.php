<?php
/**
 * Admin: Members, Subscriptions, Transactions screens + actions + CSV export.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Admin_Lists {

	public static function init() {
		add_action( 'admin_post_mgm_admin_action', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_mgm_export', array( __CLASS__, 'export' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'user_profile' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'user_profile' ) );
	}

	private static function action_url( $action, array $args = array() ) {
		return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'mgm_admin_action', 'do' => $action ), $args ), admin_url( 'admin-post.php' ) ), 'mgm_admin_action' );
	}

	private static function pagination( $total, $per_page, $page ) {
		$pages = (int) ceil( $total / $per_page );
		if ( $pages < 2 ) {
			return;
		}
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo paginate_links( // phpcs:ignore
			array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $page,
				'total'   => $pages,
			)
		);
		echo '</div></div>';
	}

	/**
	 * Status tabs with counts.
	 *
	 * @param string $page    Admin page slug.
	 * @param string $current Current status.
	 * @param array  $tabs    status => [label, count].
	 */
	private static function status_tabs( $page, $current, array $tabs ) {
		echo '<nav class="mgm-tabs" aria-label="' . esc_attr__( 'Filter by status', 'mg-membership' ) . '">';
		foreach ( $tabs as $key => $t ) {
			$url = admin_url( 'admin.php?page=' . $page . ( '' === $key ? '' : '&status=' . $key ) );
			printf(
				'<a href="%1$s" class="%2$s" %3$s>%4$s <span class="mgm-count">%5$s</span></a>',
				esc_url( $url ),
				(string) $key === (string) $current ? 'is-active' : '',
				(string) $key === (string) $current ? 'aria-current="page"' : '',
				esc_html( $t[0] ),
				esc_html( number_format_i18n( (int) $t[1] ) )
			);
		}
		echo '</nav>';
	}

	/**
	 * Toolbar: status tabs (left) + filters & search (right).
	 */
	private static function search_form( $page, $extra = '', $tabs_html = '' ) {
		$s = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
		echo '<div class="mgm-toolbar">' . $tabs_html; // phpcs:ignore
		echo '<form method="get" class="mgm-filter"><input type="hidden" name="page" value="' . esc_attr( $page ) . '">';
		if ( isset( $_GET['status'] ) ) { // phpcs:ignore
			echo '<input type="hidden" name="status" value="' . esc_attr( sanitize_key( $_GET['status'] ) ) . '">'; // phpcs:ignore
		}
		echo $extra; // phpcs:ignore
		echo '<span class="mgm-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" name="s" value="' . esc_attr( $s ) . '" placeholder="' . esc_attr__( 'Search name, email or ID…', 'mg-membership' ) . '" aria-label="' . esc_attr__( 'Search', 'mg-membership' ) . '"></span>';
		submit_button( __( 'Apply', 'mg-membership' ), 'secondary', '', false );
		echo '</form></div>';
	}

	private static function capture( $cb ) {
		ob_start();
		call_user_func( $cb );
		return ob_get_clean();
	}

	private static function empty_row( $cols, $text ) {
		echo '<tr class="mgm-empty-row"><td colspan="' . (int) $cols . '"><span class="dashicons dashicons-search" aria-hidden="true"></span><strong>' . esc_html( $text ) . '</strong><span>' . esc_html__( 'Try another status or clear the search.', 'mg-membership' ) . '</span></td></tr>';
	}

	private static function export_action( $type ) {
		return array(
			'label' => __( 'Export CSV', 'mg-membership' ),
			'url'   => wp_nonce_url( admin_url( 'admin-post.php?action=mgm_export&type=' . $type ), 'mgm_export' ),
			'icon'  => 'dashicons-download',
		);
	}

	/* ----------------------------- Members ----------------------------- */

	public static function members_page() {
		if ( ! empty( $_GET['member'] ) ) { // phpcs:ignore
			self::member_detail( absint( $_GET['member'] ) ); // phpcs:ignore
			return;
		}
		$page   = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore
		$status = sanitize_key( $_GET['status'] ?? '' ); // phpcs:ignore
		$mid    = absint( $_GET['membership'] ?? 0 ); // phpcs:ignore
		$args   = array(
			'search'        => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ), // phpcs:ignore
			'status'        => $status,
			'membership_id' => $mid,
			'page'          => $page,
		);
		$all = MGM_Member::query( array( 'count' => true ) );
		$act = MGM_Member::query( array( 'count' => true, 'status' => 'active' ) );

		MGM_Admin::header(
			__( 'Members', 'mg-membership' ),
			array(
				'desc'    => __( 'Everyone who has joined, paid or been given access.', 'mg-membership' ),
				'actions' => array(
					self::export_action( 'members' ),
					array( 'label' => __( 'Add member', 'mg-membership' ), 'url' => admin_url( 'admin.php?page=mgm-transactions&new=1' ), 'primary' => true, 'icon' => 'dashicons-plus-alt2' ),
				),
			)
		);

		$extra = '<select name="membership" aria-label="' . esc_attr__( 'Membership', 'mg-membership' ) . '"><option value="0">' . esc_html__( 'All memberships', 'mg-membership' ) . '</option>';
		foreach ( mgm_membership_options() as $id => $t ) {
			$extra .= '<option value="' . (int) $id . '" ' . selected( $mid, $id, false ) . '>' . esc_html( $t ) . '</option>';
		}
		$extra .= '</select>';
		$tabs = self::capture(
			function () use ( $status, $all, $act ) {
				self::status_tabs(
					'mgm-members',
					$status,
					array(
						''         => array( __( 'All', 'mg-membership' ), $all ),
						'active'   => array( __( 'Active', 'mg-membership' ), $act ),
						'inactive' => array( __( 'Inactive', 'mg-membership' ), max( 0, $all - $act ) ),
					)
				);
			}
		);
		self::search_form( 'mgm-members', $extra, $tabs );

		$total = MGM_Member::query( array_merge( $args, array( 'count' => true ) ) );
		$rows  = MGM_Member::query( $args );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'mgm_admin_action' );
		echo '<input type="hidden" name="action" value="mgm_admin_action"><input type="hidden" name="do" value="bulk">';
		echo '<div class="mgm-bulkbar"><select name="bulk_action" aria-label="' . esc_attr__( 'Bulk action', 'mg-membership' ) . '"><option value="">' . esc_html__( 'Bulk actions', 'mg-membership' ) . '</option><option value="grant">' . esc_html__( 'Grant membership', 'mg-membership' ) . '</option><option value="revoke">' . esc_html__( 'Revoke membership', 'mg-membership' ) . '</option></select> <select name="bulk_membership" aria-label="' . esc_attr__( 'Membership', 'mg-membership' ) . '">';
		foreach ( mgm_membership_options() as $id => $t ) {
			echo '<option value="' . (int) $id . '">' . esc_html( $t ) . '</option>';
		}
		echo '</select> <button class="mgm-btn mgm-btn-sm" type="submit">' . esc_html__( 'Apply to selected', 'mg-membership' ) . '</button></div>';
		echo '<div class="mgm-table-card"><table class="widefat mgm-table"><thead><tr><td class="check-column"><input type="checkbox" onclick="document.querySelectorAll(\'.mgm-bulk-cb\').forEach(function(c){c.checked=this.checked}.bind(this))" aria-label="' . esc_attr__( 'Select all', 'mg-membership' ) . '"></td><th>' . esc_html__( 'Member', 'mg-membership' ) . '</th><th>' . esc_html__( 'Memberships', 'mg-membership' ) . '</th><th class="num">' . esc_html__( 'Payments', 'mg-membership' ) . '</th><th class="num">' . esc_html__( 'Lifetime value', 'mg-membership' ) . '</th><th>' . esc_html__( 'Joined', 'mg-membership' ) . '</th><th><span class="screen-reader-text">' . esc_html__( 'Actions', 'mg-membership' ) . '</span></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$active = MGM_Member::active_membership_ids( $r->ID );
			$chips  = '';
			foreach ( $active as $a ) {
				$chips .= '<span class="mgm-chip">' . esc_html( get_the_title( $a ) ) . '</span>';
			}
			$url = admin_url( 'admin.php?page=mgm-members&member=' . $r->ID );
			printf(
				'<tr><td class="check-column"><input type="checkbox" class="mgm-bulk-cb" name="users[]" value="' . (int) $r->ID . '" aria-label="' . str_replace( '%', '%%', esc_attr( sprintf( __( 'Select %s', 'mg-membership' ), $r->display_name ) ) ) . '"></td><td><a class="mgm-person" href="%1$s">%2$s<span><strong>%3$s</strong><small>%4$s</small></span></a></td><td>%5$s</td><td class="num">%6$d</td><td class="num">%7$s</td><td>%8$s</td><td class="mgm-row-actions"><a class="mgm-btn mgm-btn-sm" href="%1$s">%9$s</a></td></tr>',
				esc_url( $url ),
				get_avatar( $r->ID, 36, '', '', array( 'class' => 'mgm-avatar' ) ),
				esc_html( $r->display_name ),
				esc_html( $r->user_email ),
				$chips ? $chips : '<span class="mgm-status mgm-status-expired">' . esc_html__( 'Inactive', 'mg-membership' ) . '</span>', // phpcs:ignore
				(int) $r->txn_count,
				esc_html( mgm_price( $r->ltv ) ),
				esc_html( mysql2date( get_option( 'date_format' ), $r->user_registered ) ),
				esc_html__( 'Manage', 'mg-membership' )
			);
		}
		if ( ! $rows ) {
			self::empty_row( 7, __( 'No members found', 'mg-membership' ) );
		}
		echo '</tbody></table></div></form>';
		self::pagination( $total, 20, $page );
		MGM_Admin::footer();
	}

	public static function member_detail( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_die( esc_html__( 'User not found.', 'mg-membership' ) );
		}
		global $wpdb;
		$ltv = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(total),0) FROM ' . MGM_Transaction::table() . " WHERE user_id = %d AND status='complete'", $user_id ) ); // phpcs:ignore
		MGM_Admin::header(
			$user->display_name,
			array(
				'back'    => array( __( 'All members', 'mg-membership' ), admin_url( 'admin.php?page=mgm-members' ) ),
				'actions' => array(
					array( 'label' => __( 'Edit WordPress user', 'mg-membership' ), 'url' => get_edit_user_link( $user_id ), 'icon' => 'dashicons-admin-users' ),
					array( 'label' => __( 'Add transaction', 'mg-membership' ), 'url' => admin_url( 'admin.php?page=mgm-transactions&new=1' ), 'primary' => true, 'icon' => 'dashicons-plus-alt2' ),
				),
			)
		);
		$active = MGM_Member::active_membership_ids( $user_id );
		printf(
			'<div class="mgm-card mgm-profile">%1$s<div class="mgm-profile-main"><strong>%2$s</strong><span>%3$s</span></div><dl class="mgm-profile-stats"><div><dt>%4$s</dt><dd>%5$s</dd></div><div><dt>%6$s</dt><dd>%7$d</dd></div><div><dt>%8$s</dt><dd>%9$s</dd></div></dl></div>',
			get_avatar( $user_id, 56, '', '', array( 'class' => 'mgm-avatar' ) ),
			esc_html( $user->user_email ),
			esc_html( sprintf( __( 'Username: %s', 'mg-membership' ), $user->user_login ) ),
			esc_html__( 'Lifetime value', 'mg-membership' ),
			esc_html( mgm_price( $ltv ) ),
			esc_html__( 'Active memberships', 'mg-membership' ),
			count( $active ),
			esc_html__( 'Member since', 'mg-membership' ),
			esc_html( mysql2date( get_option( 'date_format' ), $user->user_registered ) )
		);

		echo '<section class="mgm-card"><h2 class="mgm-card-title">' . esc_html__( 'Access', 'mg-membership' ) . '</h2><ul class="mgm-access-list">';
		foreach ( mgm_membership_options() as $mid => $title ) {
			$is  = in_array( (int) $mid, $active, true );
			$exp = $is ? MGM_Member::membership_expires( $user_id, $mid ) : '';
			printf(
				'<li><span class="mgm-access-name"><strong>%1$s</strong><small>%2$s</small></span>%3$s</li>',
				esc_html( $title ),
				$is ? esc_html( $exp ? sprintf( __( 'Active until %s', 'mg-membership' ), mgm_date( $exp ) ) : __( 'Active · lifetime access', 'mg-membership' ) ) : esc_html__( 'No access', 'mg-membership' ),
				$is
					? '<a class="mgm-btn mgm-btn-sm mgm-btn-danger" href="' . esc_url( self::action_url( 'revoke', array( 'user' => $user_id, 'membership' => $mid ) ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Revoke access?', 'mg-membership' ) ) . '\')">' . esc_html__( 'Revoke', 'mg-membership' ) . '</a>'
					: '<a class="mgm-btn mgm-btn-sm" href="' . esc_url( self::action_url( 'grant', array( 'user' => $user_id, 'membership' => $mid ) ) ) . '">' . esc_html__( 'Grant access', 'mg-membership' ) . '</a>'
			);
		}
		if ( ! mgm_membership_options() ) {
			echo '<li class="mgm-muted">' . esc_html__( 'No memberships exist yet.', 'mg-membership' ) . '</li>';
		}
		echo '</ul></section>';

		echo '<section class="mgm-card mgm-card-flush"><h2 class="mgm-card-title">' . esc_html__( 'Subscriptions', 'mg-membership' ) . '</h2>';
		self::subs_table( MGM_Subscription::for_user( $user_id ) );
		echo '</section>';

		echo '<section class="mgm-card mgm-card-flush"><h2 class="mgm-card-title">' . esc_html__( 'Payment history', 'mg-membership' ) . '</h2>';
		self::txn_table( MGM_Transaction::query( array( 'user_id' => $user_id, 'per_page' => 100 ) ) );
		echo '</section>';

		do_action( 'mgm_admin_member_detail', $user );
		self::notes_card( $user );
		MGM_Admin::footer();
	}

	/**
	 * Private admin notes on a member (only visible to admins).
	 */
	private static function notes_card( $user ) {
		$notes = (array) get_user_meta( $user->ID, '_mgm_admin_notes', true );
		echo '<section class="mgm-card"><h2 class="mgm-card-title">' . esc_html__( 'Admin notes', 'mg-membership' ) . ' <small class="mgm-muted" style="font-weight:400">' . esc_html__( 'Only visible to administrators', 'mg-membership' ) . '</small></h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:12px">';
		wp_nonce_field( 'mgm_admin_action' );
		echo '<input type="hidden" name="action" value="mgm_admin_action"><input type="hidden" name="do" value="add_note"><input type="hidden" name="user" value="' . (int) $user->ID . '"><textarea name="note" rows="2" class="large-text" required placeholder="' . esc_attr__( 'e.g. Called about invoice, agreed to extend access to June', 'mg-membership' ) . '"></textarea><p style="margin:6px 0 0"><button class="mgm-btn mgm-btn-sm" type="submit">' . esc_html__( 'Add note', 'mg-membership' ) . '</button></p></form>';
		foreach ( array_reverse( array_filter( $notes, 'is_array' ) ) as $n ) {
			$author = get_userdata( (int) ( $n['by'] ?? 0 ) );
			echo '<div style="border-top:1px solid var(--m-grid);padding:10px 0"><div>' . wp_kses_post( wpautop( esc_html( $n['text'] ?? '' ) ) ) . '</div><small class="mgm-muted">' . esc_html( sprintf( __( '%1$s · %2$s ago', 'mg-membership' ), $author ? $author->display_name : '—', human_time_diff( (int) ( $n['time'] ?? time() ) ) ) ) . '</small></div>';
		}
		echo '</section>';
	}

	public static function user_profile( $user ) {
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			return;
		}
		$active = array_map( 'get_the_title', MGM_Member::active_membership_ids( $user->ID ) );
		echo '<h2>' . esc_html__( 'MG Membership', 'mg-membership' ) . '</h2><table class="form-table"><tr><th>' . esc_html__( 'Active memberships', 'mg-membership' ) . '</th><td>' . esc_html( $active ? implode( ', ', $active ) : '—' ) . ' &nbsp; <a class="button" href="' . esc_url( admin_url( 'admin.php?page=mgm-members&member=' . $user->ID ) ) . '">' . esc_html__( 'Manage', 'mg-membership' ) . '</a></td></tr></table>';
	}

	/* --------------------------- Subscriptions ------------------------- */

	public static function subscriptions_page() {
		global $wpdb;
		$page   = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore
		$status = sanitize_key( $_GET['status'] ?? '' ); // phpcs:ignore
		$args   = array( 'status' => $status, 'search' => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ), 'page' => $page ); // phpcs:ignore
		$counts = $wpdb->get_results( 'SELECT status, COUNT(*) c FROM ' . MGM_Subscription::table() . ' GROUP BY status', OBJECT_K ); // phpcs:ignore

		MGM_Admin::header(
			__( 'Subscriptions', 'mg-membership' ),
			array(
				'desc'    => __( 'Recurring plans, their billing status and next payment date.', 'mg-membership' ),
				'actions' => array( self::export_action( 'subscriptions' ) ),
			)
		);
		$tabs = array( '' => array( __( 'All', 'mg-membership' ), array_sum( wp_list_pluck( $counts, 'c' ) ) ) );
		foreach ( array( 'active', 'trialing', 'suspended', 'pending', 'cancelled', 'expired' ) as $st ) {
			if ( isset( $counts[ $st ] ) || $st === $status ) {
				$tabs[ $st ] = array( MGM_Subscription::status_label( $st ), isset( $counts[ $st ] ) ? $counts[ $st ]->c : 0 );
			}
		}
		$tabs_html = self::capture(
			function () use ( $status, $tabs ) {
				self::status_tabs( 'mgm-subscriptions', $status, $tabs );
			}
		);
		self::search_form( 'mgm-subscriptions', '', $tabs_html );
		self::subs_table( MGM_Subscription::query( $args ), true );
		self::pagination( MGM_Subscription::query( array_merge( $args, array( 'count' => true ) ) ), 20, $page );
		MGM_Admin::footer();
	}

	private static function subs_table( $rows, $show_user = false ) {
		echo '<div class="mgm-table-card"><table class="widefat mgm-table"><thead><tr><th>ID</th>' . ( $show_user ? '<th>' . esc_html__( 'Member', 'mg-membership' ) . '</th>' : '' ) . '<th>' . esc_html__( 'Membership', 'mg-membership' ) . '</th><th>' . esc_html__( 'Terms', 'mg-membership' ) . '</th><th>' . esc_html__( 'Gateway', 'mg-membership' ) . '</th><th>' . esc_html__( 'Status', 'mg-membership' ) . '</th><th>' . esc_html__( 'Created', 'mg-membership' ) . '</th><th>' . esc_html__( 'Next payment', 'mg-membership' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$actions = '';
			if ( in_array( $r->status, array( 'active', 'trialing', 'pending', 'suspended' ), true ) ) {
				$actions = '<a class="mgm-btn mgm-btn-sm mgm-btn-danger" href="' . esc_url( self::action_url( 'cancel_sub', array( 'sub' => $r->id ) ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Cancel this subscription?', 'mg-membership' ) ) . '\')">' . esc_html__( 'Cancel', 'mg-membership' ) . '</a>';
			}
			printf(
				'<tr><td>%d</td>%s<td>%s</td><td>%s</td><td>%s<br><small>%s</small></td><td><span class="mgm-status mgm-status-%s">%s</span></td><td>%s</td><td>%s</td><td>%s</td></tr>',
				(int) $r->id,
				$show_user ? '<td><a href="' . esc_url( admin_url( 'admin.php?page=mgm-members&member=' . $r->user_id ) ) . '">' . esc_html( $r->user_email ?? '' ) . '</a></td>' : '', // phpcs:ignore
				esc_html( get_the_title( $r->membership_id ) ),
				esc_html( mgm_price( $r->price ) . ' / ' . $r->period . ' ' . $r->period_unit ),
				esc_html( $r->gateway ),
				esc_html( $r->gateway_sub_id ),
				esc_attr( $r->status ),
				esc_html( MGM_Subscription::status_label( $r->status ) ),
				esc_html( mgm_date( $r->created_at ) ),
				esc_html( in_array( $r->status, array( 'active', 'trialing' ), true ) ? mgm_date( $r->next_payment_at ) : '—' ),
				$actions // phpcs:ignore
			);
		}
		if ( ! $rows ) {
			self::empty_row( 9, __( 'No subscriptions found', 'mg-membership' ) );
		}
		echo '</tbody></table></div>';
	}

	/* --------------------------- Transactions -------------------------- */

	public static function transactions_page() {
		global $wpdb;
		$page   = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore
		$status = sanitize_key( $_GET['status'] ?? '' ); // phpcs:ignore
		$args   = array( 'status' => $status, 'search' => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ), 'page' => $page ); // phpcs:ignore
		$counts = $wpdb->get_results( 'SELECT status, COUNT(*) c FROM ' . MGM_Transaction::table() . ' GROUP BY status', OBJECT_K ); // phpcs:ignore

		MGM_Admin::header(
			__( 'Transactions', 'mg-membership' ),
			array(
				'desc'    => __( 'Every payment, free signup and manual grant. Approve offline payments here.', 'mg-membership' ),
				'actions' => array(
					self::export_action( 'transactions' ),
					array( 'label' => __( 'Add transaction', 'mg-membership' ), 'url' => admin_url( 'admin.php?page=mgm-transactions&new=1' ), 'primary' => true, 'icon' => 'dashicons-plus-alt2' ),
				),
			)
		);
		if ( ! empty( $_GET['new'] ) ) { // phpcs:ignore
			self::new_txn_form();
		}
		$labels = array(
			'complete' => __( 'Paid', 'mg-membership' ),
			'pending'  => __( 'Pending', 'mg-membership' ),
			'failed'   => __( 'Failed', 'mg-membership' ),
			'refunded' => __( 'Refunded', 'mg-membership' ),
		);
		$tabs = array( '' => array( __( 'All', 'mg-membership' ), array_sum( wp_list_pluck( $counts, 'c' ) ) ) );
		foreach ( $labels as $st => $l ) {
			if ( isset( $counts[ $st ] ) || $st === $status ) {
				$tabs[ $st ] = array( $l, isset( $counts[ $st ] ) ? $counts[ $st ]->c : 0 );
			}
		}
		$tabs_html = self::capture(
			function () use ( $status, $tabs ) {
				self::status_tabs( 'mgm-transactions', $status, $tabs );
			}
		);
		self::search_form( 'mgm-transactions', '', $tabs_html );
		self::txn_table( MGM_Transaction::query( $args ), true );
		self::pagination( MGM_Transaction::query( array_merge( $args, array( 'count' => true ) ) ), 20, $page );
		MGM_Admin::footer();
	}

	private static function new_txn_form() {
		?>
		<div class="mgm-card mgm-new-txn">
			<h2 class="mgm-card-title"><?php esc_html_e( 'Add manual transaction / member', 'mg-membership' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'mgm_admin_action' ); ?>
				<input type="hidden" name="action" value="mgm_admin_action"><input type="hidden" name="do" value="create_txn">
				<table class="form-table">
					<tr><th><?php esc_html_e( 'User (email or username)', 'mg-membership' ); ?></th><td><input type="text" name="user" required class="regular-text"> <label><input type="checkbox" name="create_user" value="1"> <?php esc_html_e( 'Create user if email does not exist', 'mg-membership' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Membership', 'mg-membership' ); ?></th><td><select name="membership" required><?php foreach ( mgm_membership_options() as $id => $t ) { echo '<option value="' . (int) $id . '">' . esc_html( $t ) . '</option>'; } ?></select></td></tr>
					<tr><th><?php esc_html_e( 'Amount', 'mg-membership' ); ?></th><td><input type="number" step="0.01" name="amount" value="0"></td></tr>
					<tr><th><?php esc_html_e( 'Status', 'mg-membership' ); ?></th><td><select name="status"><?php foreach ( MGM_Transaction::STATUSES as $s ) { echo '<option value="' . esc_attr( $s ) . '" ' . selected( $s, 'complete', false ) . '>' . esc_html( self::txn_label( $s ) ) . '</option>'; } ?></select></td></tr>
					<tr><th><?php esc_html_e( 'Expires', 'mg-membership' ); ?></th><td><input type="date" name="expires"> <label><input type="checkbox" name="lifetime" value="1"> <?php esc_html_e( 'Lifetime (never expires)', 'mg-membership' ); ?></label><p class="description"><?php esc_html_e( 'Leave blank to use the membership’s own terms.', 'mg-membership' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Reference / invoice #', 'mg-membership' ); ?></th><td><input type="text" name="trans_num" class="regular-text"></td></tr>
				</table>
				<?php submit_button( __( 'Create', 'mg-membership' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function txn_label( $status ) {
		$labels = array(
			'complete' => __( 'Paid', 'mg-membership' ),
			'pending'  => __( 'Pending', 'mg-membership' ),
			'failed'   => __( 'Failed', 'mg-membership' ),
			'refunded' => __( 'Refunded', 'mg-membership' ),
		);
		return $labels[ $status ] ?? ucfirst( $status );
	}

	private static function txn_table( $rows, $show_user = false ) {
		echo '<div class="mgm-table-card"><table class="widefat mgm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Invoice #', 'mg-membership' ) . '</th>' . ( $show_user ? '<th>' . esc_html__( 'Member', 'mg-membership' ) . '</th>' : '' ) . '<th>' . esc_html__( 'Membership', 'mg-membership' ) . '</th><th>' . esc_html__( 'Total', 'mg-membership' ) . '</th><th>' . esc_html__( 'Gateway', 'mg-membership' ) . '</th><th>' . esc_html__( 'Status', 'mg-membership' ) . '</th><th>' . esc_html__( 'Date', 'mg-membership' ) . '</th><th>' . esc_html__( 'Expires', 'mg-membership' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$actions = array();
			if ( 'pending' === $r->status ) {
				$actions[] = '<a class="mgm-btn mgm-btn-sm mgm-btn-primary" href="' . esc_url( self::action_url( 'complete_txn', array( 'txn' => $r->id ) ) ) . '">' . esc_html__( 'Mark paid', 'mg-membership' ) . '</a>';
			}
			if ( 'complete' === $r->status && (float) $r->total > 0 ) {
				$actions[] = '<a class="mgm-btn mgm-btn-sm" href="' . esc_url( self::action_url( 'refund_txn', array( 'txn' => $r->id ) ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Mark as refunded and remove access? (Issue the actual refund in your payment gateway.)', 'mg-membership' ) ) . '\')">' . esc_html__( 'Mark refunded', 'mg-membership' ) . '</a>';
			}
			$actions[] = '<a class="mgm-btn mgm-btn-sm mgm-btn-ghost-danger" href="' . esc_url( self::action_url( 'delete_txn', array( 'txn' => $r->id ) ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this transaction?', 'mg-membership' ) ) . '\')">' . esc_html__( 'Delete', 'mg-membership' ) . '</a>';
			printf(
				'<tr><td>%d</td><td><code>%s</code></td>%s<td>%s</td><td>%s%s</td><td>%s</td><td><span class="mgm-status mgm-status-%s">%s</span></td><td>%s</td><td>%s</td><td class="mgm-actions">%s</td></tr>',
				(int) $r->id,
				esc_html( $r->trans_num ),
				$show_user ? '<td><a href="' . esc_url( admin_url( 'admin.php?page=mgm-members&member=' . $r->user_id ) ) . '">' . esc_html( $r->user_email ?? '' ) . '</a></td>' : '', // phpcs:ignore
				esc_html( get_the_title( $r->membership_id ) ),
				esc_html( mgm_price( $r->total ) ),
				(float) $r->tax_amount > 0 ? '<br><small>' . esc_html( sprintf( __( 'incl. %s tax', 'mg-membership' ), mgm_price( $r->tax_amount ) ) ) . '</small>' : '',
				esc_html( $r->gateway ),
				esc_attr( $r->status ),
				esc_html( self::txn_label( $r->status ) ),
				esc_html( mgm_date( $r->created_at ) ),
				esc_html( $r->expires_at ? mgm_date( $r->expires_at ) : __( 'Never', 'mg-membership' ) ),
				implode( ' ', $actions ) // phpcs:ignore
			);
		}
		if ( ! $rows ) {
			self::empty_row( 10, __( 'No transactions found', 'mg-membership' ) );
		}
		echo '</tbody></table></div>';
	}

	/* ------------------------------ Actions ---------------------------- */

	public static function handle() {
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mg-membership' ) );
		}
		check_admin_referer( 'mgm_admin_action' );
		$do   = sanitize_key( $_REQUEST['do'] ?? '' );
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=mgm' );
		$back = remove_query_arg( array( 'mgm_msg', 'new' ), $back );
		$msg  = __( 'Done.', 'mg-membership' );

		switch ( $do ) {
			case 'grant':
				MGM_Member::grant( absint( $_GET['user'] ), absint( $_GET['membership'] ) );
				$msg = __( 'Membership granted.', 'mg-membership' );
				break;
			case 'revoke':
				MGM_Member::revoke( absint( $_GET['user'] ), absint( $_GET['membership'] ) );
				$msg = __( 'Membership revoked.', 'mg-membership' );
				break;
			case 'cancel_sub':
				$r   = MGM_Subscription::cancel( absint( $_GET['sub'] ) );
				$msg = is_wp_error( $r ) ? $r->get_error_message() : __( 'Subscription cancelled.', 'mg-membership' );
				break;
			case 'complete_txn':
				MGM_Transaction::complete( absint( $_GET['txn'] ) );
				$msg = __( 'Transaction marked as paid.', 'mg-membership' );
				break;
			case 'refund_txn':
				MGM_Transaction::update( absint( $_GET['txn'] ), array( 'status' => 'refunded' ) );
				$msg = __( 'Transaction marked as refunded.', 'mg-membership' );
				break;
			case 'delete_txn':
				MGM_Transaction::delete( absint( $_GET['txn'] ) );
				$msg = __( 'Transaction deleted.', 'mg-membership' );
				break;
			case 'create_txn':
				$ident = sanitize_text_field( wp_unslash( $_POST['user'] ?? '' ) );
				$user  = is_email( $ident ) ? get_user_by( 'email', $ident ) : get_user_by( 'login', $ident );
				if ( ! $user && is_email( $ident ) && ! empty( $_POST['create_user'] ) ) {
					$uid = wp_insert_user(
						array(
							'user_login' => sanitize_user( current( explode( '@', $ident ) ) . wp_rand( 10, 999 ), true ),
							'user_email' => $ident,
							'user_pass'  => wp_generate_password( 16 ),
						)
					);
					if ( ! is_wp_error( $uid ) ) {
						wp_new_user_notification( $uid, null, 'user' );
						$user = get_userdata( $uid );
					}
				}
				if ( ! $user ) {
					$msg = __( 'User not found.', 'mg-membership' );
					break;
				}
				$m       = new MGM_Membership( absint( $_POST['membership'] ?? 0 ) );
				$expires = null;
				if ( empty( $_POST['lifetime'] ) ) {
					$date    = sanitize_text_field( wp_unslash( $_POST['expires'] ?? '' ) );
					$expires = $date ? gmdate( 'Y-m-d 23:59:59', strtotime( $date ) ) : ( $m->is_recurring() ? gmdate( 'Y-m-d H:i:s', mgm_add_period( time(), $m->get( 'period' ), $m->get( 'period_unit' ) ) ) : $m->one_time_expiration() );
				}
				$status = sanitize_key( $_POST['status'] ?? 'complete' );
				MGM_Transaction::create(
					array(
						'user_id'       => $user->ID,
						'membership_id' => $m->id,
						'amount'        => (float) ( $_POST['amount'] ?? 0 ),
						'gateway'       => 'manual',
						'txn_type'      => 'manual',
						'trans_num'     => sanitize_text_field( wp_unslash( $_POST['trans_num'] ?? '' ) ),
						'status'        => in_array( $status, MGM_Transaction::STATUSES, true ) ? $status : 'complete',
						'expires_at'    => $expires,
					)
				);
				$msg  = __( 'Transaction created.', 'mg-membership' );
				$back = admin_url( 'admin.php?page=mgm-members&member=' . $user->ID );
				break;
			case 'add_note':
				$uid  = absint( $_POST['user'] ?? 0 );
				$text = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
				if ( $uid && get_userdata( $uid ) && '' !== $text ) {
					$notes   = array_filter( (array) get_user_meta( $uid, '_mgm_admin_notes', true ), 'is_array' );
					$notes[] = array( 'text' => mb_substr( $text, 0, 2000 ), 'by' => get_current_user_id(), 'time' => time() );
					update_user_meta( $uid, '_mgm_admin_notes', array_slice( $notes, -100 ) );
					$msg = __( 'Note added.', 'mg-membership' );
				}
				break;
			case 'bulk':
				$users = array_map( 'absint', (array) ( $_POST['users'] ?? array() ) );
				$mid   = absint( $_POST['bulk_membership'] ?? 0 );
				$what  = sanitize_key( $_POST['bulk_action'] ?? '' );
				$n     = 0;
				if ( $mid && in_array( $what, array( 'grant', 'revoke' ), true ) ) {
					foreach ( $users as $uid ) {
						if ( ! get_userdata( $uid ) ) {
							continue;
						}
						if ( 'grant' === $what && ! in_array( $mid, MGM_Member::active_membership_ids( $uid ), true ) ) {
							MGM_Member::grant( $uid, $mid );
							++$n;
						} elseif ( 'revoke' === $what && in_array( $mid, MGM_Member::active_membership_ids( $uid ), true ) ) {
							MGM_Member::revoke( $uid, $mid );
							++$n;
						}
					}
				}
				/* translators: %d number of members */
				$msg = sprintf( _n( '%d member updated.', '%d members updated.', $n, 'mg-membership' ), $n );
				break;
			default:
				do_action( 'mgm_admin_action_' . $do );
		}
		wp_safe_redirect( add_query_arg( 'mgm_msg', rawurlencode( $msg ), $back ) );
		exit;
	}

	public static function export() {
		if ( ! current_user_can( mgm_admin_cap() ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mg-membership' ) );
		}
		check_admin_referer( 'mgm_export' );
		$type = sanitize_key( $_GET['type'] ?? 'members' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=mgm-' . $type . '-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		switch ( $type ) {
			case 'transactions':
				fputcsv( $out, MGM_Security_Core::csv_row( array( 'id', 'trans_num', 'user_id', 'email', 'membership', 'amount', 'tax', 'total', 'gateway', 'status', 'created_at', 'expires_at' ) ) );
				foreach ( MGM_Transaction::query( array( 'per_page' => 0 ) ) as $r ) {
					fputcsv( $out, MGM_Security_Core::csv_row( array( $r->id, $r->trans_num, $r->user_id, $r->user_email, get_the_title( $r->membership_id ), $r->amount, $r->tax_amount, $r->total, $r->gateway, $r->status, $r->created_at, $r->expires_at ) ) );
				}
				break;
			case 'subscriptions':
				fputcsv( $out, MGM_Security_Core::csv_row( array( 'id', 'user_id', 'email', 'membership', 'price', 'period', 'unit', 'gateway', 'gateway_sub_id', 'status', 'created_at' ) ) );
				foreach ( MGM_Subscription::query( array( 'per_page' => 100000 ) ) as $r ) {
					fputcsv( $out, MGM_Security_Core::csv_row( array( $r->id, $r->user_id, $r->user_email, get_the_title( $r->membership_id ), $r->price, $r->period, $r->period_unit, $r->gateway, $r->gateway_sub_id, $r->status, $r->created_at ) ) );
				}
				break;
			default:
				do_action( 'mgm_export_' . $type, $out );
				if ( 'members' === $type ) {
					$extra = apply_filters( 'mgm_export_member_columns', array() ); // key => label.
					fputcsv( $out, array_merge( array( 'user_id', 'username', 'email', 'name', 'active_memberships', 'lifetime_value', 'registered' ), array_keys( $extra ) ) );
					foreach ( MGM_Member::query( array( 'per_page' => 100000 ) ) as $r ) {
						$row = array( $r->ID, $r->user_login, $r->user_email, $r->display_name, implode( '|', array_map( 'get_the_title', MGM_Member::active_membership_ids( $r->ID ) ) ), $r->ltv, $r->user_registered );
						foreach ( array_keys( $extra ) as $key ) {
							$row[] = apply_filters( 'mgm_export_member_value', '', $key, (int) $r->ID );
						}
						fputcsv( $out, MGM_Security_Core::csv_row( $row ) );
					}
				}
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
}
