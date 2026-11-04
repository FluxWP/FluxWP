/* Flux reCAPTCHA · yönetim. GPL-2.0-or-later. */
( function () {
	'use strict';
	var A = window.flxrcAdmin || {};
	var T = A.i18n || {};
	var form = document.querySelector( '[data-flxrc-form]' );
	if ( ! form ) {
		return;
	}
	var $ = function ( s, r ) {
		return ( r || document ).querySelector( s );
	};

	/* Sürüme göre alanlar */
	function version() {
		var r = form.querySelector( 'input[name="version"]:checked' );
		return r ? r.value : 'v3';
	}
	function sync() {
		var v = version();
		form.querySelectorAll( '[data-flxrc-only]' ).forEach( function ( el ) {
			el.hidden = el.getAttribute( 'data-flxrc-only' ) !== v;
		} );
	}
	form.addEventListener( 'change', function ( e ) {
		if ( e.target.name === 'version' ) {
			sync();
		}
	} );
	sync();

	var range = $( '[data-flxrc-range]' );
	var out = $( '[data-flxrc-out]' );
	if ( range && out ) {
		range.addEventListener( 'input', function () {
			out.textContent = Number( range.value ).toLocaleString( document.documentElement.lang || undefined, { minimumFractionDigits: 1 } );
		} );
	}

	/* Test */
	var run = $( '[data-flxrc-run]' );
	var box = $( '[data-flxrc-box]' );
	var msg = $( '[data-flxrc-msg]' );
	var site = $( '[data-flxrc-site]' );
	var secret = $( '[data-flxrc-secret]' );
	var loaded = null; // { key, v }

	function say( text, cls ) {
		msg.hidden = ! text;
		msg.textContent = text || '';
		msg.className = 'flxrc-test__msg' + ( cls ? ' ' + cls : '' );
	}

	function load( key, v ) {
		return new Promise( function ( resolve, reject ) {
			if ( loaded ) {
				return loaded.key === key && loaded.v === v ? window.grecaptcha.ready( function () {
					resolve( window.grecaptcha );
				} ) : reject( 'reload' );
			}
			loaded = { key: key, v: v };
			window.flxrcAdminOnload = function () {
				window.grecaptcha.ready( function () {
					resolve( window.grecaptcha );
				} );
			};
			var s = document.createElement( 'script' );
			s.src = A.api + '?render=' + ( v === 'v3' ? encodeURIComponent( key ) : 'explicit' ) + '&onload=flxrcAdminOnload&hl=' + encodeURIComponent( A.hl || '' );
			s.async = true;
			s.onerror = function () {
				loaded = null;
				reject( 'load' );
			};
			document.head.appendChild( s );
			setTimeout( function () {
				if ( ! window.grecaptcha ) {
					reject( 'load' );
				}
			}, 15000 );
		} );
	}

	function send( token ) {
		say( T.testing, 'is-busy' );
		var fd = new FormData();
		fd.append( 'action', 'flxrc_test' );
		fd.append( 'nonce', A.nonce );
		fd.append( 'version', version() );
		fd.append( 'site_key', site ? site.value.trim() : '' );
		fd.append( 'secret', secret ? secret.value.trim() : '' );
		fd.append( 'token', token );
		fetch( A.ajax, { method: 'POST', body: fd, credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( j ) {
				var d = ( j && j.data ) || {};
				if ( j && j.success ) {
					say( ( d.msg || '' ) + ' ' + T.saved, 'is-ok' );
					setTimeout( function () {
						location.href = location.href.replace( /[?&]flxrc_(msg|autotest)=[^&]*/g, '' );
					}, 1600 );
				} else {
					say( d.msg || T.fail, 'is-bad' );
					run.disabled = false;
				}
			} )
			.catch( function () {
				say( T.fail, 'is-bad' );
				run.disabled = false;
			} );
	}

	function test() {
		var key = site ? site.value.trim() : '';
		var v = version();
		if ( ! key && ! ( site && site.disabled ) ) {
			say( T.noKey, 'is-bad' );
			site && site.focus();
			return;
		}
		if ( site && site.disabled ) {
			key = site.getAttribute( 'data-key' ) || key;
		}
		run.disabled = true;
		say( T.loading, 'is-busy' );
		load( key, v ).then(
			function ( g ) {
				if ( v === 'v3' ) {
					g.execute( key, { action: 'flx_test' } ).then( send, function () {
						say( T.loadFail, 'is-bad' );
						run.disabled = false;
					} );
					return;
				}
				box.hidden = false;
				box.innerHTML = '<div></div>';
				say( T.tick, '' );
				g.render( box.firstChild, {
					sitekey: key,
					theme: document.documentElement.classList.contains( 'flxa-dark' ) ? 'dark' : 'light',
					callback: send,
					'error-callback': function () {
						say( T.loadFail, 'is-bad' );
						run.disabled = false;
					},
				} );
			},
			function ( why ) {
				if ( why === 'reload' ) {
					/* Google betiği başka anahtarla yüklendi: ayarları kaydet, sayfa açılınca test kendiliğinden başlar. */
					say( T.reload, 'is-busy' );
					var h = document.createElement( 'input' );
					h.type = 'hidden';
					h.name = 'then_test';
					h.value = '1';
					form.appendChild( h );
					form.submit();
					return;
				}
				say( T.loadFail, 'is-bad' );
				run.disabled = false;
			}
		);
	}

	if ( run ) {
		run.addEventListener( 'click', test );
		if ( /[?&]flxrc_autotest=1/.test( location.search ) ) {
			test();
		}
	}
} )();
