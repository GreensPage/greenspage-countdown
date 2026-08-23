/**
 * Greenspage Countdown - frontend runtime.
 *
 * Everything is computed in the browser, so a cached HTML page is still correct.
 * The server only ships configuration.
 */
( function () {
	'use strict';

	var STORE_PREFIX = 'gpcd:';
	var ORDER = [ 'days', 'hours', 'minutes', 'seconds' ];
	var MS = { days: 86400000, hours: 3600000, minutes: 60000, seconds: 1000 };

	function pad( n ) {
		n = Math.max( 0, Math.floor( n ) );
		return n < 10 ? '0' + n : String( n );
	}

	function readStore( key ) {
		try {
			var raw = window.localStorage.getItem( key );
			return raw ? parseInt( raw, 10 ) : null;
		} catch ( e ) {
			return null;
		}
	}

	function writeStore( key, value ) {
		try {
			window.localStorage.setItem( key, String( value ) );
		} catch ( e ) {
			/* Private mode, quota, blocked storage. The timer still runs for this pageview. */
		}
	}

	function split( diff, units ) {
		var active = ORDER.filter( function ( u ) { return units.indexOf( u ) !== -1; } );
		if ( ! active.length ) { active = ORDER.slice(); }
		var out = {};
		var rest = diff;
		active.forEach( function ( unit ) {
			out[ unit ] = Math.floor( rest / MS[ unit ] );
			rest -= out[ unit ] * MS[ unit ];
		} );
		return out;
	}

	function humanise( parts, units ) {
		var bits = [];
		ORDER.forEach( function ( unit ) {
			if ( units.indexOf( unit ) === -1 || typeof parts[ unit ] === 'undefined' ) { return; }
			var value = parts[ unit ];
			if ( 0 === value ) { return; }
			if ( 'seconds' === unit && bits.length ) { return; }
			bits.push( value + ' ' + ( 1 === value ? unit.replace( /s$/, '' ) : unit ) );
		} );
		if ( ! bits.length ) { return 'Less than a minute remaining'; }
		return bits.join( ', ' ) + ' remaining';
	}

	function Countdown( root ) {
		var config;
		try { config = JSON.parse( root.getAttribute( 'data-gpcd' ) || '{}' ); } catch ( e ) { return; }
		var units = ( config.units && config.units.length ) ? config.units : ORDER.slice();
		var nums = {};
		ORDER.forEach( function ( unit ) {
			var node = root.querySelector( '[data-gpcd-unit="' + unit + '"] .gp-cd__num' );
			if ( node ) { nums[ unit ] = node; }
		} );
		var bar = root.querySelector( '[data-gpcd-bar]' );
		var sr = root.querySelector( '[data-gpcd-sr]' );
		var expired = root.querySelector( '[data-gpcd-expired]' );
		var unitsWrap = root.querySelector( '.gp-cd__units' );
		var target = null;
		var start = null;

		if ( 'evergreen' === config.mode ) {
			var key = STORE_PREFIX + ( config.uid || 'default' );
			var first = readStore( key );
			var now = Date.now();
			var duration = Math.max( 0.0166, config.hours || 0 ) * 3600000;
			if ( ! first ) { first = now; writeStore( key, first ); }
			else if ( config.resetDays > 0 && now - first > config.resetDays * 86400000 ) { first = now; writeStore( key, first ); }
			start = first;
			target = first + duration;
		} else {
			if ( ! config.target ) { return; }
			target = new Date( config.target ).getTime();
			if ( isNaN( target ) ) { return; }
			start = config.from ? new Date( config.from ).getTime() : null;
			if ( start !== null && isNaN( start ) ) { start = null; }
		}

		var timer = null;
		var lastSpoken = '';
		var lastSpokenAt = 0;
		var finished = false;

		function finish() {
			if ( finished ) { return; }
			finished = true;
			if ( timer ) { window.clearInterval( timer ); timer = null; }
			ORDER.forEach( function ( unit ) { if ( nums[ unit ] ) { nums[ unit ].textContent = '00'; } } );
			if ( bar ) { bar.style.width = '100%'; }
			root.classList.add( 'is-expired' );
			if ( 'hide' === config.onExpire ) { root.hidden = true; root.setAttribute( 'aria-hidden', 'true' ); return; }
			if ( 'message' === config.onExpire ) {
				if ( unitsWrap ) { unitsWrap.hidden = true; }
				if ( bar && bar.parentNode ) { bar.parentNode.hidden = true; }
				if ( expired ) { expired.hidden = false; if ( sr ) { sr.textContent = expired.textContent.trim(); } }
				return;
			}
			if ( 'redirect' === config.onExpire && config.redirect ) { window.location.replace( config.redirect ); return; }
			if ( sr ) { sr.textContent = 'Countdown finished'; }
		}

		function tick() {
			var diff = target - Date.now();
			if ( diff <= 0 ) { finish(); return; }
			var parts = split( diff, units );
			ORDER.forEach( function ( unit ) {
				if ( nums[ unit ] && typeof parts[ unit ] !== 'undefined' ) {
					var next = pad( parts[ unit ] );
					if ( nums[ unit ].textContent !== next ) { nums[ unit ].textContent = next; }
				}
			} );
			if ( bar && start !== null && target > start ) {
				var pct = ( ( Date.now() - start ) / ( target - start ) ) * 100;
				bar.style.width = Math.min( 100, Math.max( 0, pct ) ).toFixed( 2 ) + '%';
			}
			if ( sr ) {
				var spoken = humanise( parts, units );
				var now = Date.now();
				if ( spoken !== lastSpoken && ( '' === lastSpoken || now - lastSpokenAt >= 10000 ) ) {
					sr.textContent = spoken;
					lastSpoken = spoken;
					lastSpokenAt = now;
				}
			}
		}

		tick();
		if ( ! finished ) { timer = window.setInterval( tick, 1000 ); }
		document.addEventListener( 'visibilitychange', function () { if ( ! document.hidden && ! finished ) { tick(); } } );
	}

	function init() {
		var nodes = document.querySelectorAll( '[data-gpcd]:not([data-gpcd-ready])' );
		Array.prototype.forEach.call( nodes, function ( node ) {
			node.setAttribute( 'data-gpcd-ready', '1' );
			Countdown( node );
		} );
	}

	if ( 'loading' === document.readyState ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
	window.GreenspageCountdown = { refresh: init };
}() );
