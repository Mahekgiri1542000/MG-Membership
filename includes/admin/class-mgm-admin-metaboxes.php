<?php
/**
 * Metaboxes: membership pricing/terms, rules, per-post protection.
 *
 * @package MG_Membership
 */

defined( 'ABSPATH' ) || exit;

class MGM_Admin_Metaboxes {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add' ) );
		add_action( 'save_post_mgm_membership', array( __CLASS__, 'save_membership' ), 10, 2 );
		add_action( 'save_post_mgm_rule', array( __CLASS__, 'save_rule' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save_post_protection' ), 10, 2 );
		add_filter( 'manage_mgm_membership_posts_columns', array( __CLASS__, 'membership_columns' ) );
		add_action( 'manage_mgm_membership_posts_custom_column', array( __CLASS__, 'membership_column' ), 10, 2 );
		add_filter( 'manage_mgm_rule_posts_columns', array( __CLASS__, 'rule_columns' ) );
		add_action( 'manage_mgm_rule_posts_custom_column', array( __CLASS__, 'rule_column' ), 10, 2 );
	}

	public static function add() {
		add_meta_box( 'mgm_membership_terms', __( 'Membership Terms', 'mg-membership' ), array( __CLASS__, 'membership_box' ), 'mgm_membership', 'normal', 'high' );
		add_meta_box( 'mgm_membership_options', __( 'Membership Options', 'mg-membership' ), array( __CLASS__, 'membership_options_box' ), 'mgm_membership', 'normal', 'default' );
		add_meta_box( 'mgm_membership_side', __( 'Shortcodes', 'mg-membership' ), array( __CLASS__, 'membership_side_box' ), 'mgm_membership', 'side' );
		add_meta_box( 'mgm_rule', __( 'Rule Settings', 'mg-membership' ), array( __CLASS__, 'rule_box' ), 'mgm_rule', 'normal', 'high' );

		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'], $types['mgm_membership'] );
		foreach ( apply_filters( 'mgm_protectable_post_types', $types ) as $pt ) {
			add_meta_box( 'mgm_protect', __( 'Membership Access', 'mg-membership' ), array( __CLASS__, 'protect_box' ), $pt, 'side', 'default' );
		}
	}

	private static function val( $post_id, $key ) {
		$m = new MGM_Membership( $post_id );
		return $m->get( $key );
	}

	private static function select( $name, $options, $value, $attrs = '' ) {
		$out = '<select name="' . esc_attr( $name ) . '" ' . $attrs . '>';
		foreach ( $options as $k => $l ) {
			$out .= '<option value="' . esc_attr( $k ) . '" ' . selected( (string) $k, (string) $value, false ) . '>' . esc_html( $l ) . '</option>';
		}
		return $out . '</select>';
	}

	public static function memberships_checklist( $name, array $selected ) {
		$opts = mgm_membership_options();
		if ( ! $opts ) {
			return '<p><em>' . esc_html__( 'No memberships yet.', 'mg-membership' ) . '</em> <a href="' . esc_url( admin_url( 'post-new.php?post_type=mgm_membership' ) ) . '">' . esc_html__( 'Create one', 'mg-membership' ) . '</a></p>';
		}
		$out = '<div class="mgm-checklist">';
		foreach ( $opts as $id => $title ) {
			$out .= sprintf( '<label><input type="checkbox" name="%s[]" value="%d" %s> %s</label>', esc_attr( $name ), (int) $id, checked( in_array( (int) $id, $selected, true ), true, false ), esc_html( $title ) );
		}
		return $out . '</div>';
	}

	/* --------------------------- Membership ---------------------------- */

	public static function membership_box( $post ) {
		wp_nonce_field( 'mgm_membership_save', '_mgm_membership_nonce' );
		$v = function ( $k ) use ( $post ) {
			return self::val( $post->ID, $k );
		};
		?>
		<div class="mgm-fields">
			<p class="mgm-field">
				<label><?php esc_html_e( 'Billing type', 'mg-membership' ); ?></label>
				<?php echo self::select( 'mgm[billing_type]', array( 'one-time' => __( 'One-time payment', 'mg-membership' ), 'recurring' => __( 'Recurring subscription', 'mg-membership' ), 'free' => __( 'Free', 'mg-membership' ) ), $v( 'billing_type' ), 'class="mgm-billing-type"' ); // phpcs:ignore ?>
			</p>
			<p class="mgm-field mgm-show-paid">
				<label><?php echo esc_html( sprintf( __( 'Price (%s)', 'mg-membership' ), mgm_currency() ) ); ?></label>
				<input type="number" step="0.01" min="0" name="mgm[price]" value="<?php echo esc_attr( $v( 'price' ) ); ?>">
			</p>
			<p class="mgm-field mgm-show-recurring">
				<label><?php esc_html_e( 'Billing every', 'mg-membership' ); ?></label>
				<input type="number" min="1" name="mgm[period]" value="<?php echo esc_attr( $v( 'period' ) ); ?>" style="width:70px">
				<?php echo self::select( 'mgm[period_unit]', mgm_periods(), $v( 'period_unit' ) ); // phpcs:ignore ?>
			</p>
			<p class="mgm-field mgm-show-recurring">
				<label><?php esc_html_e( 'Limit payment cycles (0 = unlimited)', 'mg-membership' ); ?></label>
				<input type="number" min="0" name="mgm[limit_cycles]" value="<?php echo esc_attr( $v( 'limit_cycles' ) ); ?>" style="width:90px">
			</p>
			<div class="mgm-show-recurring mgm-subgroup">
				<p class="mgm-field">
					<label><input type="checkbox" name="mgm[trial_enabled]" value="1" <?php checked( '1', (string) $v( 'trial_enabled' ) ); ?>> <?php esc_html_e( 'Trial period', 'mg-membership' ); ?></label>
				</p>
				<p class="mgm-field">
					<label><?php esc_html_e( 'Trial days', 'mg-membership' ); ?></label>
					<input type="number" min="1" name="mgm[trial_days]" value="<?php echo esc_attr( $v( 'trial_days' ) ); ?>" style="width:90px">
					<label><?php esc_html_e( 'Trial price', 'mg-membership' ); ?></label>
					<input type="number" min="0" step="0.01" name="mgm[trial_amount]" value="<?php echo esc_attr( $v( 'trial_amount' ) ); ?>" style="width:110px">
				</p>
			</div>
			<div class="mgm-show-onetime mgm-subgroup">
				<p class="mgm-field">
					<label><?php esc_html_e( 'Access duration', 'mg-membership' ); ?></label>
					<?php echo self::select( 'mgm[access_type]', array( 'lifetime' => __( 'Lifetime', 'mg-membership' ), 'fixed-period' => __( 'Expire after a period', 'mg-membership' ), 'fixed-date' => __( 'Expire on a fixed date', 'mg-membership' ) ), $v( 'access_type' ) ); // phpcs:ignore ?>
				</p>
				<p class="mgm-field">
					<label><?php esc_html_e( 'Period', 'mg-membership' ); ?></label>
					<input type="number" min="1" name="mgm[access_period]" value="<?php echo esc_attr( $v( 'access_period' ) ); ?>" style="width:70px">
					<?php echo self::select( 'mgm[access_period_unit]', mgm_periods(), $v( 'access_period_unit' ) ); // phpcs:ignore ?>
					&nbsp;<label><?php esc_html_e( 'or date', 'mg-membership' ); ?></label>
					<input type="date" name="mgm[access_expire_date]" value="<?php echo esc_attr( $v( 'access_expire_date' ) ); ?>">
				</p>
			</div>
		</div>
		<?php
	}

	public static function membership_options_box( $post ) {
		$v     = function ( $k ) use ( $post ) {
			return self::val( $post->ID, $k );
		};
		$roles = array( '' => __( '— No extra role —', 'mg-membership' ) );
		foreach ( wp_roles()->get_names() as $k => $n ) {
			if ( MGM_Post_Types::safe_role( $k ) ) {
				$roles[ $k ] = translate_user_role( $n );
			}
		}
		?>
		<div class="mgm-fields">
			<p class="mgm-field"><label><?php esc_html_e( 'Pricing table features (one per line)', 'mg-membership' ); ?></label>
				<textarea name="mgm[features]" rows="5" class="large-text"><?php echo esc_textarea( $v( 'features' ) ); ?></textarea></p>
			<p class="mgm-field"><label><input type="checkbox" name="mgm[highlight]" value="1" <?php checked( '1', (string) $v( 'highlight' ) ); ?>> <?php esc_html_e( 'Highlight in pricing table', 'mg-membership' ); ?></label>
				&nbsp; <label><?php esc_html_e( 'Badge', 'mg-membership' ); ?></label> <input type="text" name="mgm[badge]" value="<?php echo esc_attr( $v( 'badge' ) ); ?>" placeholder="<?php esc_attr_e( 'Most popular', 'mg-membership' ); ?>"></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Sign-up button text', 'mg-membership' ); ?></label>
				<input type="text" name="mgm[signup_button_text]" value="<?php echo esc_attr( $v( 'signup_button_text' ) ); ?>"></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Who can purchase', 'mg-membership' ); ?></label>
				<?php echo self::select( 'mgm[who_can_purchase]', array( 'everyone' => __( 'Everyone', 'mg-membership' ), 'guests' => __( 'New customers only', 'mg-membership' ), 'members' => __( 'Existing members only', 'mg-membership' ) ), $v( 'who_can_purchase' ) ); // phpcs:ignore ?></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Assign user role while active', 'mg-membership' ); ?></label>
				<?php echo self::select( 'mgm[user_role]', $roles, $v( 'user_role' ) ); // phpcs:ignore ?></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Upgrade group (memberships with the same group replace each other on upgrade/downgrade)', 'mg-membership' ); ?></label>
				<input type="text" name="mgm[upgrade_group]" value="<?php echo esc_attr( $v( 'upgrade_group' ) ); ?>" placeholder="e.g. main-plans"></p>
			<p class="mgm-field"><label><input type="checkbox" name="mgm[allow_multiple]" value="1" <?php checked( '1', (string) $v( 'allow_multiple' ) ); ?>> <?php esc_html_e( 'Allow buying again while active (renew early)', 'mg-membership' ); ?></label></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Custom thank-you URL', 'mg-membership' ); ?></label>
				<input type="url" class="large-text" name="mgm[thankyou_url]" value="<?php echo esc_attr( $v( 'thankyou_url' ) ); ?>"></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Thank-you message', 'mg-membership' ); ?></label>
				<textarea name="mgm[thankyou_message]" rows="3" class="large-text"><?php echo esc_textarea( $v( 'thankyou_message' ) ); ?></textarea></p>
			<p class="mgm-field"><label><input type="checkbox" name="mgm[custom_page]" value="1" <?php checked( '1', (string) $v( 'custom_page' ) ); ?>> <?php esc_html_e( 'I build this registration page myself with a page builder (do not auto-append the checkout form)', 'mg-membership' ); ?></label></p>
			<?php do_action( 'mgm_membership_options_box', $post ); ?>
		</div>
		<?php
	}

	public static function membership_side_box( $post ) {
		echo '<p>' . esc_html__( 'Checkout form:', 'mg-membership' ) . '<br><code>[mgm_checkout id="' . (int) $post->ID . '"]</code></p>';
		echo '<p>' . esc_html__( 'Buy button:', 'mg-membership' ) . '<br><code>[mgm_buy_button id="' . (int) $post->ID . '"]</code></p>';
		echo '<p>' . esc_html__( 'Members-only content:', 'mg-membership' ) . '<br><code>[mgm_protect memberships="' . (int) $post->ID . '"]…[/mgm_protect]</code></p>';
		if ( 'publish' === $post->post_status ) {
			echo '<p><a class="button" target="_blank" href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html__( 'View registration page', 'mg-membership' ) . '</a></p>';
		}
	}

	public static function save_membership( $post_id, $post ) {
		if ( ! isset( $_POST['_mgm_membership_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_membership_nonce'] ), 'mgm_membership_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( mgm_admin_cap() ) ) {
			return;
		}
		$in = isset( $_POST['mgm'] ) ? wp_unslash( $_POST['mgm'] ) : array(); // phpcs:ignore
		foreach ( array_keys( MGM_Membership::meta_defaults() ) as $key ) {
			$val = isset( $in[ $key ] ) ? $in[ $key ] : '';
			if ( in_array( $key, array( 'trial_enabled', 'highlight', 'allow_multiple', 'custom_page' ), true ) ) {
				$val = $val ? '1' : '0';
			} elseif ( in_array( $key, array( 'features', 'thankyou_message' ), true ) ) {
				$val = sanitize_textarea_field( $val );
			} elseif ( 'thankyou_url' === $key ) {
				$val = esc_url_raw( $val );
			} elseif ( 'user_role' === $key ) {
				$val = MGM_Post_Types::safe_role( sanitize_key( $val ) ) ? sanitize_key( $val ) : '';
			} else {
				$val = sanitize_text_field( $val );
			}
			update_post_meta( $post_id, '_mgm_' . $key, $val );
		}
		do_action( 'mgm_save_membership', $post_id, $in );
	}

	public static function membership_columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $l ) {
			$new[ $k ] = $l;
			if ( 'title' === $k ) {
				$new['mgm_terms']   = __( 'Terms', 'mg-membership' );
				$new['mgm_members'] = __( 'Active members', 'mg-membership' );
				$new['mgm_code']    = __( 'Shortcode', 'mg-membership' );
			}
		}
		return $new;
	}

	public static function membership_column( $col, $post_id ) {
		global $wpdb;
		if ( 'mgm_terms' === $col ) {
			echo esc_html( ( new MGM_Membership( $post_id ) )->price_terms() );
		} elseif ( 'mgm_members' === $col ) {
			echo (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT user_id) FROM ' . MGM_Transaction::table() . " WHERE membership_id = %d AND status='complete' AND (expires_at IS NULL OR expires_at > %s)", $post_id, mgm_now() ) ); // phpcs:ignore
		} elseif ( 'mgm_code' === $col ) {
			echo '<code>[mgm_checkout id="' . (int) $post_id . '"]</code>';
		}
	}

	/* ------------------------------ Rules ------------------------------ */

	public static function rule_box( $post ) {
		wp_nonce_field( 'mgm_rule_save', '_mgm_rule_nonce' );
		$type        = get_post_meta( $post->ID, '_mgm_rule_type', true );
		$target      = get_post_meta( $post->ID, '_mgm_rule_target', true );
		$exclude     = get_post_meta( $post->ID, '_mgm_rule_exclude', true );
		$memberships = mgm_int_list( get_post_meta( $post->ID, '_mgm_rule_memberships', true ) );
		$message     = get_post_meta( $post->ID, '_mgm_rule_message', true );
		$post_types  = implode( ', ', array_keys( get_post_types( array( 'public' => true ) ) ) );
		?>
		<div class="mgm-fields">
			<p class="mgm-field"><label><?php esc_html_e( 'Protected content', 'mg-membership' ); ?></label>
				<?php echo self::select( 'mgm_rule[type]', MGM_Access::rule_types(), $type, 'class="mgm-rule-type"' ); // phpcs:ignore ?></p>
			<p class="mgm-field mgm-rule-target"><label><?php esc_html_e( 'Target (comma separated)', 'mg-membership' ); ?></label>
				<input type="text" class="large-text" name="mgm_rule[target]" value="<?php echo esc_attr( $target ); ?>">
				<span class="description"><?php echo esc_html( sprintf( __( 'IDs, slugs, URL parts or regex depending on the type. Available post types: %s', 'mg-membership' ), $post_types ) ); ?></span></p>
			<p class="mgm-field"><label><?php esc_html_e( 'Exclude post IDs', 'mg-membership' ); ?></label>
				<input type="text" class="regular-text" name="mgm_rule[exclude]" value="<?php echo esc_attr( is_array( $exclude ) ? implode( ',', $exclude ) : $exclude ); ?>"></p>
			<div class="mgm-field"><label><?php esc_html_e( 'Members of any of these memberships get access', 'mg-membership' ); ?></label>
				<?php echo self::memberships_checklist( 'mgm_rule[memberships]', $memberships ); // phpcs:ignore ?></div>
			<?php do_action( 'mgm_rule_box_after', $post ); ?>
			<p class="mgm-field"><label><?php esc_html_e( 'Custom unauthorized message (optional)', 'mg-membership' ); ?></label>
				<textarea name="mgm_rule[message]" rows="3" class="large-text"><?php echo esc_textarea( $message ); ?></textarea></p>
		</div>
		<?php
	}

	public static function save_rule( $post_id ) {
		if ( ! isset( $_POST['_mgm_rule_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_rule_nonce'] ), 'mgm_rule_save' ) || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( mgm_admin_cap() ) ) {
			return;
		}
		$in = isset( $_POST['mgm_rule'] ) ? wp_unslash( $_POST['mgm_rule'] ) : array(); // phpcs:ignore
		update_post_meta( $post_id, '_mgm_rule_type', sanitize_key( $in['type'] ?? 'all_content' ) );
		update_post_meta( $post_id, '_mgm_rule_target', sanitize_text_field( $in['target'] ?? '' ) );
		update_post_meta( $post_id, '_mgm_rule_exclude', mgm_int_list( $in['exclude'] ?? '' ) );
		update_post_meta( $post_id, '_mgm_rule_memberships', mgm_int_list( $in['memberships'] ?? array() ) );
		update_post_meta( $post_id, '_mgm_rule_message', wp_kses_post( $in['message'] ?? '' ) );
		do_action( 'mgm_save_rule', $post_id, $in );
		MGM_Access::flush_rules();
	}

	public static function rule_columns( $cols ) {
		return array(
			'cb'               => $cols['cb'],
			'title'            => $cols['title'],
			'mgm_type'         => __( 'Protects', 'mg-membership' ),
			'mgm_memberships'  => __( 'Access for', 'mg-membership' ),
			'date'             => $cols['date'],
		);
	}

	public static function rule_column( $col, $post_id ) {
		if ( 'mgm_type' === $col ) {
			$types  = MGM_Access::rule_types();
			$type   = get_post_meta( $post_id, '_mgm_rule_type', true );
			$target = get_post_meta( $post_id, '_mgm_rule_target', true );
			echo esc_html( ( $types[ $type ] ?? $type ) . ( $target ? ': ' . $target : '' ) );
		} elseif ( 'mgm_memberships' === $col ) {
			$ids = mgm_int_list( get_post_meta( $post_id, '_mgm_rule_memberships', true ) );
			echo esc_html( implode( ', ', array_map( 'get_the_title', $ids ) ) );
		}
	}

	/* ---------------------- Per-post protection ----------------------- */

	public static function protect_box( $post ) {
		wp_nonce_field( 'mgm_protect_save', '_mgm_protect_nonce' );
		$selected = mgm_int_list( get_post_meta( $post->ID, '_mgm_post_memberships', true ) );
		echo '<p>' . esc_html__( 'Restrict this content to members of:', 'mg-membership' ) . '</p>';
		echo self::memberships_checklist( 'mgm_post_memberships', $selected ); // phpcs:ignore
		echo '<p><label>' . esc_html__( 'Custom message', 'mg-membership' ) . '</label><textarea name="mgm_post_message" class="widefat" rows="2">' . esc_textarea( get_post_meta( $post->ID, '_mgm_post_message', true ) ) . '</textarea></p>';

		$rules = array_filter(
			MGM_Access::rules(),
			function ( $r ) use ( $post ) {
				return MGM_Access::rule_matches_post( $r, $post );
			}
		);
		if ( $rules ) {
			echo '<p class="description">' . esc_html__( 'Also protected by rules:', 'mg-membership' ) . ' ';
			echo wp_kses_post(
				implode(
					', ',
					array_map(
						function ( $r ) {
							return '<a href="' . esc_url( get_edit_post_link( $r['id'] ) ) . '">' . esc_html( get_the_title( $r['id'] ) ) . '</a>';
						},
						$rules
					)
				)
			);
			echo '</p>';
		}
	}

	public static function save_post_protection( $post_id, $post ) {
		if ( ! isset( $_POST['_mgm_protect_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mgm_protect_nonce'] ), 'mgm_protect_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$ids = isset( $_POST['mgm_post_memberships'] ) ? mgm_int_list( wp_unslash( $_POST['mgm_post_memberships'] ) ) : array(); // phpcs:ignore
		update_post_meta( $post_id, '_mgm_post_memberships', $ids );
		update_post_meta( $post_id, '_mgm_post_message', wp_kses_post( wp_unslash( $_POST['mgm_post_message'] ?? '' ) ) );
	}
}
