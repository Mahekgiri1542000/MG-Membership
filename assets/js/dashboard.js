/* MG Membership – dashboard revenue chart (area + line, crosshair tooltip), table toggle, copy shortcodes. */
( function () {
	'use strict';
	var D = window.MGMDash;
	if ( ! D ) {
		return;
	}
	var NS = 'http://www.w3.org/2000/svg';

	function money( v, short ) {
		var n = Number( v ) || 0;
		var s;
		if ( short && n >= 1000 ) {
			s = ( n >= 1e6 ? ( n / 1e6 ).toFixed( 1 ) + 'M' : ( n / 1e3 ).toFixed( n >= 1e4 ? 0 : 1 ) + 'k' ).replace( '.0', '' );
		} else {
			s = n.toLocaleString( undefined, { minimumFractionDigits: short ? 0 : 2, maximumFractionDigits: short ? 0 : 2 } );
		}
		return D.position === 'after' ? s + D.currency : D.currency + s;
	}

	function niceMax( v ) {
		if ( v <= 0 ) { return 1; }
		var p = Math.pow( 10, Math.floor( Math.log10( v ) ) );
		var m = v / p;
		return ( m <= 1 ? 1 : m <= 2 ? 2 : m <= 2.5 ? 2.5 : m <= 5 ? 5 : 10 ) * p;
	}

	function node( tag, attrs, parent ) {
		var el = document.createElementNS( NS, tag );
		Object.keys( attrs ).forEach( function ( k ) { el.setAttribute( k, attrs[ k ] ); } );
		if ( parent ) { parent.appendChild( el ); }
		return el;
	}

	function draw( host ) {
		var tip = host.querySelector( '.mgmd-tooltip' );
		var old = host.querySelector( 'svg' );
		if ( old ) { old.remove(); }
		var data = D.series;
		var W = host.clientWidth;
		var H = host.clientHeight;
		if ( ! W || ! data.length ) { return; }
		var m = { t: 12, r: 12, b: 28, l: 52 };
		var w = W - m.l - m.r;
		var h = H - m.t - m.b;
		var max = niceMax( Math.max.apply( null, data.map( function ( d ) { return d.rev; } ) ) );
		var n = data.length;
		var x = function ( i ) { return m.l + ( n === 1 ? w / 2 : i / ( n - 1 ) * w ); };
		var y = function ( v ) { return m.t + h - v / max * h; };

		var svg = node( 'svg', { viewBox: '0 0 ' + W + ' ' + H, 'aria-hidden': 'true' } );
		host.insertBefore( svg, tip );

		// Recessive grid (4 steps) + y labels.
		for ( var g = 0; g <= 4; g++ ) {
			var gv = max / 4 * g;
			var gy = y( gv );
			node( 'line', { x1: m.l, x2: W - m.r, y1: gy, y2: gy, 'class': g === 0 ? 'base' : 'grid' }, svg );
			var t = node( 'text', { x: m.l - 8, y: gy + 4, 'text-anchor': 'end', 'class': 'axis' }, svg );
			t.textContent = money( gv, true );
		}

		// X labels: about 6, never colliding.
		var step = Math.max( 1, Math.ceil( n / Math.max( 2, Math.floor( w / 80 ) ) ) );
		data.forEach( function ( d, i ) {
			if ( i % step === 0 || i === n - 1 ) {
				if ( i !== n - 1 && n - 1 - i < step / 2 ) { return; }
				var tx = node( 'text', { x: x( i ), y: H - 8, 'text-anchor': i === 0 ? 'start' : i === n - 1 ? 'end' : 'middle', 'class': 'axis' }, svg );
				tx.textContent = d.short;
			}
		} );

		// Area wash + 2px line.
		var pts = data.map( function ( d, i ) { return x( i ).toFixed( 1 ) + ',' + y( d.rev ).toFixed( 1 ); } );
		node( 'path', { d: 'M' + x( 0 ) + ',' + y( 0 ) + ' L' + pts.join( ' L' ) + ' L' + x( n - 1 ) + ',' + y( 0 ) + ' Z', 'class': 'area' }, svg );
		node( 'polyline', { points: pts.join( ' ' ), 'class': 'line' }, svg );

		// Crosshair layer.
		var cross = node( 'line', { y1: m.t, y2: m.t + h, 'class': 'cross', visibility: 'hidden' }, svg );
		var dot = node( 'circle', { r: 5, 'class': 'dot', visibility: 'hidden' }, svg );
		var hit = node( 'rect', { x: m.l - 8, y: 0, width: w + 16, height: H, fill: 'transparent', tabindex: 0, 'aria-label': D.i18n.revenue }, svg );
		var active = n - 1;

		function show( i ) {
			active = Math.max( 0, Math.min( n - 1, i ) );
			var d = data[ active ];
			var px = x( active );
			var py = y( d.rev );
			cross.setAttribute( 'x1', px );
			cross.setAttribute( 'x2', px );
			cross.setAttribute( 'visibility', 'visible' );
			dot.setAttribute( 'cx', px );
			dot.setAttribute( 'cy', py );
			dot.setAttribute( 'visibility', 'visible' );
			tip.textContent = '';
			var label = document.createElement( 'span' );
			label.className = 'muted';
			label.textContent = d.label;
			var val = document.createElement( 'strong' );
			var key = document.createElement( 'span' );
			key.className = 'key';
			val.appendChild( key );
			val.appendChild( document.createTextNode( money( d.rev ) ) );
			var mem = document.createElement( 'span' );
			mem.className = 'muted';
			mem.textContent = D.i18n.members + ': ' + d.new;
			tip.appendChild( label );
			tip.appendChild( val );
			tip.appendChild( mem );
			tip.hidden = false;
			var tw = tip.offsetWidth;
			var left = px + 12 + tw > W ? px - tw - 12 : px + 12;
			tip.style.left = Math.max( 0, left ) + 'px';
			tip.style.top = Math.max( 0, Math.min( py - 30, H - tip.offsetHeight - m.b ) ) + 'px';
		}
		function hide() {
			cross.setAttribute( 'visibility', 'hidden' );
			dot.setAttribute( 'visibility', 'hidden' );
			tip.hidden = true;
		}
		function fromEvent( e ) {
			var r = svg.getBoundingClientRect();
			var px = ( e.clientX - r.left ) * ( W / r.width );
			return Math.round( ( px - m.l ) / ( w || 1 ) * ( n - 1 ) );
		}
		hit.addEventListener( 'pointermove', function ( e ) { show( fromEvent( e ) ); } );
		hit.addEventListener( 'pointerdown', function ( e ) { show( fromEvent( e ) ); } );
		hit.addEventListener( 'pointerleave', hide );
		hit.addEventListener( 'focus', function () { show( active ); } );
		hit.addEventListener( 'blur', hide );
		hit.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowLeft' ) { show( active - 1 ); e.preventDefault(); }
			if ( e.key === 'ArrowRight' ) { show( active + 1 ); e.preventDefault(); }
			if ( e.key === 'Home' ) { show( 0 ); e.preventDefault(); }
			if ( e.key === 'End' ) { show( n - 1 ); e.preventDefault(); }
		} );
	}

	var host = document.querySelector( '[data-mgmd-chart]' );
	if ( host ) {
		draw( host );
		if ( window.ResizeObserver ) {
			var raf;
			new ResizeObserver( function () {
				cancelAnimationFrame( raf );
				raf = requestAnimationFrame( function () { draw( host ); } );
			} ).observe( host );
		}
	}

	// Table view toggle.
	var toggle = document.querySelector( '[data-mgmd-table-toggle]' );
	if ( toggle ) {
		var hideLabel = toggle.textContent;
		toggle.addEventListener( 'click', function () {
			var table = document.getElementById( toggle.getAttribute( 'aria-controls' ) );
			var open = table.hidden;
			table.hidden = ! open;
			toggle.setAttribute( 'aria-expanded', String( open ) );
			toggle.textContent = open ? '× ' + hideLabel : hideLabel;
		} );
	}

	// Copy shortcode.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-mgmd-copy]' );
		if ( ! btn ) { return; }
		var text = btn.getAttribute( 'data-mgmd-copy' );
		var done = function () {
			btn.classList.add( 'is-copied' );
			var code = btn.querySelector( 'code' );
			var prev = code.textContent;
			code.textContent = D.i18n.copied + ' ✓';
			setTimeout( function () { btn.classList.remove( 'is-copied' ); code.textContent = prev; }, 1400 );
		};
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( text ).then( done, function () {} );
		}
	} );
} )();
