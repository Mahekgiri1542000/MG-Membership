<?php
/**
 * Add-on: Coupons.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Coupons {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 6 );
		add_filter( 'mgm_apply_coupon', array( __CLASS__, 'apply' ), 10, 4 );
		add_action( 'mgm_coupon_used', array( __CLASS__, 'increment' ) );
		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
			add_action( 'save_post_mgm_coupon', array( __CLASS__, 'save' ) );
			add_filter( 'manage_mgm_coupon_posts_columns', array( __CLASS__, 'columns' ) );
			add_action( 'manage_mgm_coupon_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
			add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		}
	}

	public static function register() {
		register_post_type(
			'mgm_coupon',
			array(
				'labels'       => array(
					'name'          => __( 'Coupons', 'mg-membership' ),
					'singular_name' => __( 'Coupon', 'mg-membership' ),
					'add_new_item'  => __( 'Add New Coupon', 'mg-membership' ),
					'edit_item'     => __( 'Edit Coupon', 'mg-membership' ),
					'all_items'     => __( 'Coupons', 'mg-membership' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'mgm',
				'supports'     => array( 'title' ),
			)
		);
	}

	public static function title_placeholder( $text, $post ) {
		return 'mgm_coupon' === $post->post_type ? __( 'Coupon code, e.g. SUMMER20', 'mg-membership' ) : $text;
	}

	public static function find( $code ) {
		$code = strtoupper( trim( $code ) );
		$q    = get_posts(
			array(
				'post_type'   => 'mgm_coupon',
				'post_status' => 'publish',
				'title'       => $code,
				'numberposts' => 1,
			)
		);
		if ( ! $q ) {
			// Case-insensitive fallback.
			global $wpdb;
			$id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='mgm_coupon' AND post_status='publish' AND UPPER(post_title) = %s LIMIT 1", $code ) ); // phpcs:ignore
			return $id ? get_post( $id ) : null;
		}
		return $q[0];
	}

	/**
	 * @param WP_Error|array $result Default error.
	 */
	public static function apply( $result, $code, MGM_Membership $m, array $base ) {
		$coupon = self::find( $code );
		if ( ! $coupon ) {
			return $result;
		}
		$id    = $coupon->ID;
		$meta  = function ( $k ) use ( $id ) {
			return get_post_meta( $id, '_mgm_coupon_' . $k, true );
		};
		$now   = current_time( 'timestamp' );
		$start = $meta( 'starts' );
		$end   = $meta( 'expires' );
		if ( $start && strtotime( $start ) > $now ) {
			return new WP_Error( 'mgm_coupon', __( 'This coupon is not active yet.', 'mg-membership' ) );
		}
		if ( $end && strtotime( $end . ' 23:59:59' ) < $now ) {
			return new WP_Error( 'mgm_coupon', __( 'This coupon has expired.', 'mg-membership' ) );
		}
		$limit = (int) $meta( 'usage_limit' );
		if ( $limit > 0 ) {
			global $wpdb;
			// Completed uses + checkouts still pending (last 24h) count against the limit, so parallel
			// unpaid checkouts can't push a limited coupon past its cap.
			$pending = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . MGM_Transaction::table() . " WHERE coupon_id = %d AND status = 'pending' AND created_at > %s", $id, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore
			$used    = (int) $meta( 'used' ) + $pending;
		}
		if ( $limit > 0 && $used >= $limit ) {
			return new WP_Error( 'mgm_coupon', __( 'This coupon has reached its usage limit.', 'mg-membership' ) );
		}
		$allowed = mgm_int_list( $meta( 'memberships' ) );
		if ( $allowed && ! in_array( $m->id, $allowed, true ) ) {
			return new WP_Error( 'mgm_coupon', __( 'This coupon is not valid for this membership.', 'mg-membership' ) );
		}
		$amount = (float) $meta( 'amount' );
		$price  = $base['price'];
		if ( 'percent' === $meta( 'type' ) ) {
			$price = $price * ( 1 - min( 100, $amount ) / 100 );
		} else {
			$price = $price - $amount;
		}
		$base['price']     = max( 0, round( $price, 2 ) );
		$base['coupon_id'] = $id;

		$trial = (int) $meta( 'trial_days' );
		if ( $trial > 0 && $m->is_recurring() && ! MGM_Checkout::trial_used( get_current_user_id(), $m->id ) ) {
			$base['trial_days']   = $trial;
			$base['trial_amount'] = (float) $meta( 'trial_amount' );
		}
		return apply_filters( 'mgm_coupon_pricing', $base, $coupon, $m );
	}

	public static function increment( $coupon_id ) {
		update_post_meta( $coupon_id, '_mgm_coupon_used', (int) get_post_meta( $coupon_id, '_mgm_coupon_used', true ) + 1 );
	}

	public static function metabox() {
		add_meta_box( 'mgm_coupon', __( 'Coupon Settings', 'mg-membership' ), array( __CLASS__, 'box' ), 'mgm_coupon', 'normal', 'high' );
	}

	public static function box( $post ) {
		wp_nonce_field( 'mgm_coupon_save', '_mgm_coupon_nonce' );
		$g = function ( $k, $d = '' ) use ( $post ) {
			$v = get_post_meta( $post->ID, '_mgm_coupon_' . $k, true );
			return '' === $v ? $d : $v;
		};
		?>
		<div class="mgm-fields">
			<p class="mgm-field"><label><?php esc_html_e( 'Discount', 'mg-membership' ); ?></label>
				<input type="number" step="0.01" min="0" name="mgm_coupon[amount]" value="<?php echo esc_attr( $g( 'amount', '10' ) ); ?>" style="width:110px">
				<select name="mgm_coupon[type]">
					<option value="percent" <?php selected( $g( 'type', 'percent' ), 'percent' ); ?>>%</option>
					<option value="fixed" <?php selected( $g( 'type' ), 'fixed' ); ?>><?php echo esc_html( mgm_currency() ); ?></option>
				</select></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Valid from / until', 'mg-membership' ); ?></label>
				<input type="date" name="mgm_coupon[starts]" value="<?php echo esc_attr( $g( 'starts' ) ); ?>"> – <input type="date" name="mgm_coupon[expires]" value="<?php echo esc_attr( $g( 'expires' ) ); ?>"></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Usage limit (0 = unlimited)', 'mg-membership' ); ?></label>
				<input type="number" min="0" name="mgm_coupon[usage_limit]" value="<?php echo esc_attr( $g( 'usage_limit', '0' ) ); ?>" style="width:110px">
				<span class="description"><?php echo esc_html( sprintf( __( 'Used %d times', 'mg-membership' ), (int) $g( 'used', 0 ) ) ); ?></span></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Override trial for recurring plans (days, 0 = no change)', 'mg-membership' ); ?></label>
				<input type="number" min="0" name="mgm_coupon[trial_days]" value="<?php echo esc_attr( $g( 'trial_days', '0' ) ); ?>" style="width:110px">
				<?php esc_html_e( 'Trial price', 'mg-membership' ); ?> <input type="number" min="0" step="0.01" name="mgm_coupon[trial_amount]" value="<?php echo esc_attr( $g( 'trial_amount', '0' ) ); ?>" style="width:110px"></p>
			<div class="mgm-field"><label><?php esc_html_e( 'Valid for memberships (none selected = all)', 'mg-membership' ); ?></label>
				<?php echo MGM_Admin_Metaboxes::memberships_checklist( 'mgm_coupon[memberships]', mgm_int_list( $g( 'memberships', array() ) ) ); // phpcs:ignore ?></div>
			<p class="description"><?php esc_html_e( 'Tip: share a link that pre-fills the code: ?coupon=CODE on any registration page.', 'mg-membership' ); ?></p>
		</div>
		<?php
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['_mgm_coupon_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_coupon_nonce'] ), 'mgm_coupon_save' ) || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( mgm_admin_cap() ) ) {
			return;
		}
		$in = isset( $_POST['mgm_coupon'] ) ? wp_unslash( $_POST['mgm_coupon'] ) : array(); // phpcs:ignore
		update_post_meta( $post_id, '_mgm_coupon_type', 'fixed' === ( $in['type'] ?? '' ) ? 'fixed' : 'percent' );
		foreach ( array( 'amount', 'trial_amount' ) as $k ) {
			update_post_meta( $post_id, '_mgm_coupon_' . $k, (float) ( $in[ $k ] ?? 0 ) );
		}
		foreach ( array( 'usage_limit', 'trial_days' ) as $k ) {
			update_post_meta( $post_id, '_mgm_coupon_' . $k, absint( $in[ $k ] ?? 0 ) );
		}
		foreach ( array( 'starts', 'expires' ) as $k ) {
			update_post_meta( $post_id, '_mgm_coupon_' . $k, sanitize_text_field( $in[ $k ] ?? '' ) );
		}
		update_post_meta( $post_id, '_mgm_coupon_memberships', mgm_int_list( $in['memberships'] ?? array() ) );

		// Normalize code to uppercase.
		$post = get_post( $post_id );
		$code = strtoupper( preg_replace( '/\s+/', '', $post->post_title ) );
		if ( $code !== $post->post_title ) {
			remove_action( 'save_post_mgm_coupon', array( __CLASS__, 'save' ) );
			wp_update_post( array( 'ID' => $post_id, 'post_title' => $code ) );
			add_action( 'save_post_mgm_coupon', array( __CLASS__, 'save' ) );
		}
	}

	public static function columns( $cols ) {
		return array(
			'cb'           => $cols['cb'],
			'title'        => __( 'Code', 'mg-membership' ),
			'mgm_discount' => __( 'Discount', 'mg-membership' ),
			'mgm_usage'    => __( 'Usage', 'mg-membership' ),
			'mgm_expires'  => __( 'Expires', 'mg-membership' ),
		);
	}

	public static function column( $col, $id ) {
		$type = get_post_meta( $id, '_mgm_coupon_type', true );
		$amt  = (float) get_post_meta( $id, '_mgm_coupon_amount', true );
		switch ( $col ) {
			case 'mgm_discount':
				echo esc_html( 'percent' === $type ? $amt . '%' : mgm_price( $amt ) );
				break;
			case 'mgm_usage':
				$limit = (int) get_post_meta( $id, '_mgm_coupon_usage_limit', true );
				echo esc_html( (int) get_post_meta( $id, '_mgm_coupon_used', true ) . ' / ' . ( $limit ? $limit : '∞' ) );
				break;
			case 'mgm_expires':
				$e = get_post_meta( $id, '_mgm_coupon_expires', true );
				echo esc_html( $e ? $e : '—' );
				break;
		}
	}
}

MGM_Coupons::init();
