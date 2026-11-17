/* MG Membership – frontend */
( function ( $ ) {
	'use strict';

	// Coupon validation on checkout.
	$( document ).on( 'click', '[data-mgm-apply-coupon]', function ( e ) {
		e.preventDefault();
		var $wrap = $( this ).closest( '.mgm-checkout' );
		var $msg = $wrap.find( '[data-mgm-coupon-msg]' );
		var code = $.trim( $wrap.find( 'input[name="mgm_coupon"]' ).val() );
		if ( ! code ) {
			return;
		}
		$msg.removeClass( 'is-ok is-bad' ).text( MGM.i18n.processing );
		$.ajax( {
			url: MGM.rest + 'coupon',
			method: 'POST',
			data: { code: code, membership_id: $wrap.data( 'membership' ) },
			beforeSend: function ( xhr ) { xhr.setRequestHeader( 'X-WP-Nonce', MGM.nonce ); }
		} ).done( function ( res ) {
			if ( res && res.valid ) {
				$msg.addClass( 'is-ok' ).text( MGM.i18n.applied );
				$wrap.find( '[data-mgm-total]' ).text( res.total_html );
				$wrap.find( '[data-mgm-terms]' ).text( res.terms );
				$wrap.find( '[data-mgm-gateways]' ).toggle( ! res.free );
			} else {
				$msg.addClass( 'is-bad' ).text( ( res && res.message ) || MGM.i18n.invalidCoupon );
			}
		} ).fail( function () {
			$msg.addClass( 'is-bad' ).text( MGM.i18n.invalidCoupon );
		} );
	} );

	// Auto-apply ?coupon=CODE.
	$( function () {
		var $c = $( '.mgm-checkout input[name="mgm_coupon"]' );
		if ( $c.length && $c.val() ) {
			$c.closest( '.mgm-checkout' ).find( '[data-mgm-apply-coupon]' ).trigger( 'click' );
		}
	} );

	// Prevent double submit.
	$( document ).on( 'submit', '.mgm-checkout form', function () {
		var $b = $( this ).find( 'button[type=submit]' );
		setTimeout( function () { $b.prop( 'disabled', true ).text( MGM.i18n.processing ); }, 10 );
	} );

	// Event countdowns.
	function tick() {
		$( '[data-mgm-countdown]' ).each( function () {
			var diff = Math.max( 0, parseInt( $( this ).data( 'mgm-countdown' ), 10 ) * 1000 - Date.now() );
			var s = Math.floor( diff / 1000 );
			$( this ).find( '[data-d]' ).text( Math.floor( s / 86400 ) );
			$( this ).find( '[data-h]' ).text( Math.floor( ( s % 86400 ) / 3600 ) );
			$( this ).find( '[data-m]' ).text( Math.floor( ( s % 3600 ) / 60 ) );
			$( this ).find( '[data-s]' ).text( s % 60 );
		} );
	}
	if ( $( '[data-mgm-countdown]' ).length ) {
		tick();
		setInterval( tick, 1000 );
	}
} )( jQuery );
