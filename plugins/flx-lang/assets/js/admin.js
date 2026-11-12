/* Flux Lang: "AI ile çevir" (adım adım admin-ajax; her adım kısa sürer). */
( function () {
	var A = window.flxlAdmin;
	if ( ! A || ! A.ai ) {
		return;
	}
	var busy = false;

	function toast() {
		var el = document.getElementById( 'flxl-toast' );
		if ( ! el ) {
			el = document.createElement( 'div' );
			el.id = 'flxl-toast';
			el.className = 'flxl-toast';
			el.setAttribute( 'role', 'status' );
			el.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( el );
		}
		return el;
	}
	function say( text, kind ) {
		var el = toast();
		el.textContent = text;
		el.className = 'flxl-toast is-open' + ( kind ? ' is-' + kind : '' );
	}
	function post( data ) {
		var body = new FormData();
		body.append( 'action', 'flxl_ai' );
		body.append( 'nonce', A.nonce );
		Object.keys( data ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		return fetch( A.ajax, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function ( r ) {
			return r.json().catch( function () {
				return { success: false, data: { message: 'HTTP ' + r.status } };
			} );
		} );
	}
	function fmt( s, a, b ) {
		return s.replace( '%1$d', a ).replace( '%2$d', b );
	}
	function run( src, lang ) {
		if ( busy ) {
			return;
		}
		busy = true;
		say( A.t.start );
		post( { src: src, lang: lang } ).then( function ( r ) {
			if ( ! r.success ) {
				throw new Error( r.data && r.data.message ? r.data.message : '?' );
			}
			var job = r.data.job;
			var steps = r.data.steps;
			var i = 0;
			function next() {
				say( fmt( A.t.step, i + 1, steps ) );
				return post( { job: job, step: i } ).then( function ( s ) {
					if ( ! s.success ) {
						throw new Error( s.data && s.data.message ? s.data.message : '?' );
					}
					if ( s.data.finished ) {
						say( s.data.errors && s.data.errors.length ? A.t.partial : A.t.done, s.data.errors && s.data.errors.length ? 'warn' : 'ok' );
						setTimeout( function () {
							location.href = s.data.edit_url;
						}, s.data.errors && s.data.errors.length ? 2500 : 600 );
						return;
					}
					i++;
					return next();
				} );
			}
			return next();
		} ).catch( function ( e ) {
			busy = false;
			say( A.t.error + ' ' + e.message, 'error' );
		} );
	}

	document.addEventListener( 'click', async function ( e ) {
		var b = e.target.closest( '.flxl-ai' );
		if ( b ) {
			e.preventDefault();
			if ( b.getAttribute( 'data-flxl-confirm' ) && ! (await window.flxDialog.confirm({ message:  A.t.confirm  })) ) {
				return;
			}
			run( b.getAttribute( 'data-flxl-src' ), b.getAttribute( 'data-flxl-lang' ) );
			return;
		}
		var m = e.target.closest( 'a.flxl-badge.is-missing' );
		if ( m ) {
			e.preventDefault();
			if ( await window.flxDialog.confirm( { message: A.t.ask } ) ) {
				run( m.getAttribute( 'data-flxl-src' ), m.getAttribute( 'data-flxl-lang' ) );
			} else {
				window.location.href = m.href;
			}
		}
	} );
} )();
