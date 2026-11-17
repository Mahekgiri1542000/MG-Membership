<?php
/**
 * Add-on: Drip & expiring content on access rules.
 *
 * Drip: content unlocks N days/weeks/months after the member's membership started.
 * Expiration: access to the content ends N days/weeks/months after the start.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Drip {

	public static function init() {
		add_filter( 'mgm_user_meets_rule', array( __CLASS__, 'filter' ), 10, 4 );
		add_action( 'mgm_rule_box_after', array( __CLASS__, 'fields' ) );
		add_action( 'mgm_save_rule', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'mgm_unauthorized_message', array( __CLASS__, 'message' ), 10, 2 );
	}

	private static function seconds( $amount, $unit ) {
		$map = array(
			'day'   => DAY_IN_SECONDS,
			'week'  => WEEK_IN_SECONDS,
			'month' => MONTH_IN_SECONDS,
			'year'  => YEAR_IN_SECONDS,
		);
		return (int) $amount * ( $map[ $unit ] ?? DAY_IN_SECONDS );
	}

	/**
	 * Earliest start date among the memberships that matched.
	 */
	private static function start( $user_id, array $matched ) {
		$start = 0;
		foreach ( $matched as $mid ) {
			$s = MGM_Member::membership_start( $user_id, $mid );
			if ( $s && ( ! $start || $s < $start ) ) {
				$start = $s;
			}
		}
		return $start;
	}

	/**
	 * Scheduled release: content unlocks for everyone with access on a calendar date (site timezone).
	 */
	public static function release_time( $rule ) {
		if ( empty( $rule['drip_date'] ) ) {
			return 0;
		}
		$dt = date_create( $rule['drip_date'] . ' 00:00', wp_timezone() );
		return $dt ? $dt->getTimestamp() : 0;
	}

	public static function filter( $ok, $rule, $user_id, $matched ) {
		if ( ! $ok ) {
			return $ok;
		}
		$release = self::release_time( $rule );
		if ( $release && time() < $release ) {
			return false;
		}
		if ( empty( $rule['drip_enabled'] ) && empty( $rule['exp_enabled'] ) ) {
			return $ok;
		}
		$start = self::start( $user_id, $matched );
		if ( ! $start ) {
			return $ok;
		}
		$elapsed = time() - $start;
		if ( ! empty( $rule['drip_enabled'] ) && $elapsed < self::seconds( $rule['drip_amount'], $rule['drip_unit'] ) ) {
			return false;
		}
		if ( ! empty( $rule['exp_enabled'] ) && $elapsed > self::seconds( $rule['exp_amount'], $rule['exp_unit'] ) ) {
			return false;
		}
		return $ok;
	}

	/**
	 * When will a dripped rule unlock for a user? (timestamp or 0)
	 */
	public static function unlock_time( $rule, $user_id ) {
		$release = self::release_time( $rule );
		if ( $release > time() ) {
			return $release;
		}
		if ( empty( $rule['drip_enabled'] ) ) {
			return 0;
		}
		$matched = array_intersect( $rule['memberships'], MGM_Member::active_membership_ids( $user_id ) );
		$start   = self::start( $user_id, $matched );
		return $start ? $start + self::seconds( $rule['drip_amount'], $rule['drip_unit'] ) : 0;
	}

	public static function fields( $post ) {
		$g     = function ( $k, $d = '' ) use ( $post ) {
			$v = get_post_meta( $post->ID, '_mgm_rule_' . $k, true );
			return '' === $v ? $d : $v;
		};
		$units = mgm_periods();
		?>
		<div class="mgm-subgroup">
			<p class="mgm-field"><label><input type="checkbox" name="mgm_rule[drip_enabled]" value="1" <?php checked( '1', $g( 'drip_enabled' ) ); ?>> <?php esc_html_e( 'Drip: unlock after', 'mg-membership' ); ?></label>
				<input type="number" min="0" name="mgm_rule[drip_amount]" value="<?php echo esc_attr( $g( 'drip_amount', '7' ) ); ?>" style="width:80px">
				<select name="mgm_rule[drip_unit]"><?php foreach ( $units as $k => $l ) { printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $g( 'drip_unit', 'day' ), $k, false ), esc_html( $l ) ); } ?></select>
				<?php esc_html_e( 'from membership start', 'mg-membership' ); ?></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Scheduled release: unlock on this date (optional)', 'mg-membership' ); ?></label>
				<input type="date" name="mgm_rule[drip_date]" value="<?php echo esc_attr( $g( 'drip_date' ) ); ?>">
				<span class="description"><?php esc_html_e( 'Members see "unlocks on <date>" until then.', 'mg-membership' ); ?></span></p>
			<p class="mgm-field"><label><input type="checkbox" name="mgm_rule[exp_enabled]" value="1" <?php checked( '1', $g( 'exp_enabled' ) ); ?>> <?php esc_html_e( 'Expire: lock again after', 'mg-membership' ); ?></label>
				<input type="number" min="0" name="mgm_rule[exp_amount]" value="<?php echo esc_attr( $g( 'exp_amount', '30' ) ); ?>" style="width:80px">
				<select name="mgm_rule[exp_unit]"><?php foreach ( $units as $k => $l ) { printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $g( 'exp_unit', 'day' ), $k, false ), esc_html( $l ) ); } ?></select>
				<?php esc_html_e( 'from membership start', 'mg-membership' ); ?></p>
		</div>
		<?php
	}

	public static function save( $post_id, $in ) {
		update_post_meta( $post_id, '_mgm_rule_drip_enabled', empty( $in['drip_enabled'] ) ? '' : '1' );
		update_post_meta( $post_id, '_mgm_rule_drip_amount', absint( $in['drip_amount'] ?? 0 ) );
		update_post_meta( $post_id, '_mgm_rule_drip_unit', sanitize_key( $in['drip_unit'] ?? 'day' ) );
		$date = sanitize_text_field( $in['drip_date'] ?? '' );
		update_post_meta( $post_id, '_mgm_rule_drip_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '' );
		update_post_meta( $post_id, '_mgm_rule_exp_enabled', empty( $in['exp_enabled'] ) ? '' : '1' );
		update_post_meta( $post_id, '_mgm_rule_exp_amount', absint( $in['exp_amount'] ?? 0 ) );
		update_post_meta( $post_id, '_mgm_rule_exp_unit', sanitize_key( $in['exp_unit'] ?? 'day' ) );
	}

	/**
	 * Tell members when dripped content will unlock.
	 */
	public static function message( $message, $post_id ) {
		$user_id = get_current_user_id();
		if ( ! $post_id || ! $user_id ) {
			return $message;
		}
		$soonest = 0;
		foreach ( MGM_Access::rules_for_post( $post_id ) as $rule ) {
			$t = self::unlock_time( $rule, $user_id );
			if ( $t > time() && ( ! $soonest || $t < $soonest ) ) {
				$soonest = $t;
			}
		}
		if ( $soonest ) {
			/* translators: %s date */
			return sprintf( __( 'This content will unlock for you on %s. Stay tuned!', 'mg-membership' ), date_i18n( get_option( 'date_format' ), $soonest ) );
		}
		return $message;
	}
}

MGM_Drip::init();
