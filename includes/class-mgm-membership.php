<?php
/**
 * Membership (product) model – wraps the mgm_membership post type.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Membership {

	public $id;
	public $post;

	public static function meta_defaults() {
		return array(
			'price'              => '0',
			'billing_type'       => 'one-time', // one-time | recurring | free.
			'period'             => '1',
			'period_unit'        => 'month',
			'access_type'        => 'lifetime', // lifetime | fixed-period | fixed-date (one-time only).
			'access_period'      => '1',
			'access_period_unit' => 'year',
			'access_expire_date' => '',
			'trial_enabled'      => '0',
			'trial_days'         => '7',
			'trial_amount'       => '0',
			'limit_cycles'       => '0',
			'signup_button_text' => __( 'Sign Up', 'mg-membership' ),
			'thankyou_message'   => '',
			'thankyou_url'       => '',
			'features'           => '',
			'highlight'          => '0',
			'badge'              => '',
			'user_role'          => '',
			'allow_multiple'     => '0',
			'upgrade_group'      => '',
			'group_priority'     => '0',
			'custom_page'        => '0',
			'who_can_purchase'   => 'everyone', // everyone | guests | members.
		);
	}

	public function __construct( $id ) {
		$this->id   = (int) $id;
		$this->post = get_post( $this->id );
	}

	public function exists() {
		return $this->post && 'mgm_membership' === $this->post->post_type;
	}

	public function get( $key ) {
		$defaults = self::meta_defaults();
		$value    = get_post_meta( $this->id, '_mgm_' . $key, true );
		if ( '' === $value && isset( $defaults[ $key ] ) ) {
			return $defaults[ $key ];
		}
		return $value;
	}

	public function title() {
		return $this->post ? get_the_title( $this->post ) : '';
	}

	public function price() {
		return 'free' === $this->get( 'billing_type' ) ? 0.0 : (float) $this->get( 'price' );
	}

	public function is_recurring() {
		return 'recurring' === $this->get( 'billing_type' ) && $this->price() > 0;
	}

	public function is_free() {
		return $this->price() <= 0 && ! ( $this->is_recurring() );
	}

	public function has_trial() {
		return $this->is_recurring() && '1' === (string) $this->get( 'trial_enabled' ) && (int) $this->get( 'trial_days' ) > 0;
	}

	public function url() {
		return get_permalink( $this->id );
	}

	public function features() {
		return array_filter( array_map( 'trim', explode( "\n", (string) $this->get( 'features' ) ) ) );
	}

	/**
	 * Human readable price terms, e.g. "$19.00 / month".
	 */
	public function price_terms( $price = null ) {
		$price = null === $price ? $this->price() : (float) $price;
		if ( 'free' === $this->get( 'billing_type' ) || $price <= 0 && ! $this->is_recurring() ) {
			return __( 'Free', 'mg-membership' );
		}
		if ( $this->is_recurring() ) {
			$period = (int) $this->get( 'period' );
			$unit   = $this->get( 'period_unit' );
			$label  = 1 === $period
				/* translators: 1: price 2: unit */
				? sprintf( __( '%1$s / %2$s', 'mg-membership' ), mgm_price( $price ), $unit )
				/* translators: 1: price 2: count 3: unit */
				: sprintf( __( '%1$s every %2$d %3$ss', 'mg-membership' ), mgm_price( $price ), $period, $unit );
			if ( $this->has_trial() ) {
				$trial_amount = (float) $this->get( 'trial_amount' );
				$label       .= ' ' . sprintf(
					/* translators: 1: days 2: price */
					__( 'after a %1$d-day trial (%2$s)', 'mg-membership' ),
					(int) $this->get( 'trial_days' ),
					$trial_amount > 0 ? mgm_price( $trial_amount ) : __( 'free', 'mg-membership' )
				);
			}
			$cycles = (int) $this->get( 'limit_cycles' );
			if ( $cycles > 0 ) {
				/* translators: %d payments */
				$label .= ' ' . sprintf( _n( 'for %d payment', 'for %d payments', $cycles, 'mg-membership' ), $cycles );
			}
			return $label;
		}
		$access = $this->get( 'access_type' );
		if ( 'fixed-period' === $access ) {
			/* translators: 1: price 2: count 3: unit */
			return sprintf( __( '%1$s for %2$d %3$s(s) access', 'mg-membership' ), mgm_price( $price ), (int) $this->get( 'access_period' ), $this->get( 'access_period_unit' ) );
		}
		return sprintf( __( '%s one-time', 'mg-membership' ), mgm_price( $price ) );
	}

	/**
	 * Expiration (GMT mysql) for a one-time purchase starting now. Null = lifetime.
	 */
	public function one_time_expiration( $from = null ) {
		$from = $from ? $from : time();
		switch ( $this->get( 'access_type' ) ) {
			case 'fixed-period':
				return gmdate( 'Y-m-d H:i:s', mgm_add_period( $from, $this->get( 'access_period' ), $this->get( 'access_period_unit' ) ) );
			case 'fixed-date':
				$date = $this->get( 'access_expire_date' );
				return $date ? gmdate( 'Y-m-d 23:59:59', strtotime( $date ) ) : null;
		}
		return null;
	}

	/**
	 * Can this user purchase the membership?
	 *
	 * @return true|WP_Error
	 */
	public function can_purchase( $user_id = 0 ) {
		$who = $this->get( 'who_can_purchase' );
		if ( 'guests' === $who && $user_id ) {
			return new WP_Error( 'mgm_guest_only', __( 'This membership is only available to new customers.', 'mg-membership' ) );
		}
		if ( 'members' === $who && ! mgm_user_has_membership( 'any', $user_id ) ) {
			return new WP_Error( 'mgm_members_only', __( 'This membership is only available to existing members.', 'mg-membership' ) );
		}
		if ( $user_id && ! $this->get( 'allow_multiple' ) && in_array( $this->id, MGM_Member::active_membership_ids( $user_id ), true ) && $this->is_recurring() ) {
			return new WP_Error( 'mgm_already_member', __( 'You already have an active subscription to this membership.', 'mg-membership' ) );
		}
		return apply_filters( 'mgm_can_purchase', true, $this, $user_id );
	}

	/**
	 * Other memberships in the same upgrade group (for upgrade/downgrade).
	 */
	public function group_siblings() {
		$group = $this->get( 'upgrade_group' );
		if ( ! $group ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'   => 'mgm_membership',
				'numberposts' => -1,
				'fields'      => 'ids',
				'exclude'     => array( $this->id ),
				'meta_key'    => '_mgm_upgrade_group', // phpcs:ignore
				'meta_value'  => $group, // phpcs:ignore
			)
		);
	}
}
