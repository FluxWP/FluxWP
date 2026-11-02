/* Flux Backup yönetim çubuğu göstergesi: çalışan işi yoklar (ve yoklama işi ilerletir), açılır panelde ayrıntı gösterir. */
( function () {
	'use strict';

	var C = window.FLXYBarConfig || {};
	var T = C.i18n || {};
	var li = document.getElementById( 'wp-admin-bar-flxy-job' );
	if ( ! li ) { return; }
	var link = li.querySelector( '.ab-item' );
	var ring = li.querySelector( '.flxy-ab-val' );
	var text = li.querySelector( '.flxy-ab-text' );
	var fill = li.querySelector( '.flxy-ab-bar span' );
	var pop = null;
	var job = C.job || null;
	var token = job && job.token ? job.token : '';
	var timer = null;
	var busy = false;
	var fails = 0;
	var cancelling = false;
	var lastStatus = job ? job.status : 'idle';
	var listeners = [];

	function fmt( s ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( s ).replace( /%%|%s/g, function ( m ) { return '%%' === m ? '%' : String( args[ i++ ] ); } );
	}
	function pct( n ) { return fmt( T.pct || '%s%%', n ); }

	function label( r ) {
		var restore = 'restore' === r.kind;
		if ( 'running' === r.status ) {
			if ( cancelling ) { return T.cancelling; }
			return fmt( restore ? T.restore : T.backup, pct( r.percent || 0 ) );
		}
		if ( 'done' === r.status ) {
			if ( restore ) { return T.restored; }
			return r.size_h ? fmt( T.ready, r.size_h ) : T.readyNoSize;
		}
		if ( r.cancelled ) { return T.cancelled; }
		return restore ? T.restoreFailed : T.backupFailed;
	}

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', C.nonce );
		Object.keys( data || {} ).forEach( function ( k ) { body.append( k, data[ k ] ); } );
		return fetch( C.ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) {
			return r.json().catch( function () { return { success: false, data: {} }; } );
		} );
	}

	function setClass( status ) {
		li.className = li.className.replace( /\bis-(idle|running|done|failed)\b/g, '' ).trim() + ' is-' + status;
	}

	function render() {
		if ( ! job || 'idle' === job.status ) {
			setClass( 'idle' );
			closePop();
			return;
		}
		setClass( job.status );
		var p = 'done' === job.status ? 100 : ( job.percent || 0 );
		ring.style.strokeDasharray = p + ' 100';
		fill.style.width = p + '%';
		text.textContent = label( job );
		li.classList.toggle( 'is-cancelling', cancelling && 'running' === job.status );
		if ( pop && ! pop.hidden ) { fillPop(); }
	}

	/* ---------------- Açılır panel ---------------- */

	function el( tag, cls, txt ) {
		var n = document.createElement( tag );
		if ( cls ) { n.className = cls; }
		if ( txt !== undefined && txt !== null ) { n.textContent = txt; }
		return n;
	}

	function buildPop() {
		pop = el( 'div', 'flxy-pop' );
		pop.setAttribute( 'role', 'dialog' );
		pop.setAttribute( 'aria-live', 'polite' );
		pop.hidden = true;
		pop.addEventListener( 'click', function ( e ) { e.stopPropagation(); } );
		li.appendChild( pop );
	}

	function fillPop() {
		var r = job;
		pop.textContent = '';
		var head = el( 'div', 'flxy-pop-head' );
		head.appendChild( el( 'strong', '', 'restore' === r.kind ? T.restoreTitle : T.backupTitle ) );
		if ( r.title ) { head.appendChild( el( 'span', 'flxy-pop-title', r.title ) ); }
		pop.appendChild( head );

		var msg = 'failed' === r.status ? ( r.error || r.text ) : ( 'done' === r.status && 'restore' === r.kind && r.result ? r.result : r.text );
		if ( cancelling && 'running' === r.status ) { msg = T.cancelling; }
		pop.appendChild( el( 'p', 'flxy-pop-text' + ( 'failed' === r.status ? ' is-error' : '' ), msg || T.starting ) );

		var p = 'done' === r.status ? 100 : ( r.percent || 0 );
		var bar = el( 'div', 'flxy-pop-bar is-' + r.status );
		bar.setAttribute( 'role', 'progressbar' );
		bar.setAttribute( 'aria-valuemin', '0' );
		bar.setAttribute( 'aria-valuemax', '100' );
		bar.setAttribute( 'aria-valuenow', String( p ) );
		var s = el( 'span' );
		s.style.width = p + '%';
		bar.appendChild( s );
		var row = el( 'div', 'flxy-pop-row' );
		row.appendChild( bar );
		row.appendChild( el( 'b', 'flxy-pop-pct', pct( p ) ) );
		pop.appendChild( row );

		var meta = el( 'div', 'flxy-pop-meta' );
		if ( r.elapsed_h ) { meta.appendChild( el( 'span', '', fmt( T.elapsed, r.elapsed_h ) ) ); }
		if ( 'running' === r.status && r.eta_h ) { meta.appendChild( el( 'span', '', fmt( T.eta, r.eta_h ) ) ); }
		if ( meta.childNodes.length ) { pop.appendChild( meta ); }
		if ( 'running' === r.status ) {
			pop.appendChild( el( 'p', 'flxy-pop-note', 'restore' === r.kind ? T.restoreNote : T.bgNote ) );
		}

		var act = el( 'div', 'flxy-pop-actions' );
		if ( 'running' === r.status ) {
			var c = el( 'button', 'flxy-pop-btn is-danger', T.cancel );
			c.type = 'button';
			c.disabled = cancelling;
			c.addEventListener( 'click', cancel );
			act.appendChild( c );
		} else {
			var x = el( 'button', 'flxy-pop-btn', T.close );
			x.type = 'button';
			x.addEventListener( 'click', dismiss );
			act.appendChild( x );
		}
		if ( 'done' === r.status && 'restore' === r.kind ) {
			var rl = el( 'button', 'flxy-pop-btn is-primary', T.reload );
			rl.type = 'button';
			rl.addEventListener( 'click', function () {
				rl.disabled = true;
				var go = function () { window.location.reload(); };
				markSeen().then( go, go );
			} );
			act.appendChild( rl );
		} else if ( r.url && ( 'backup' === r.kind || 'restore' === r.kind ) && ! ( 'failed' === r.status && 'backup' === r.kind ) ) {
			var a = el( 'a', 'flxy-pop-btn is-primary', T.details );
			a.href = r.url;
			a.addEventListener( 'click', function () { if ( 'running' !== r.status ) { markSeen(); } } );
			act.appendChild( a );
		}
		pop.appendChild( act );
	}

	function openPop() {
		if ( ! pop ) { buildPop(); }
		fillPop();
		pop.hidden = false;
		li.classList.add( 'is-open' );
		if ( job && 'running' !== job.status ) { markSeen(); }
	}
	function closePop() {
		if ( pop ) { pop.hidden = true; }
		li.classList.remove( 'is-open' );
		if ( job && ( 'done' === job.status || 'failed' === job.status ) && job.seen ) {
			job = null;
			setClass( 'idle' );
		}
	}

	link.addEventListener( 'click', function ( e ) {
		if ( ! job || 'idle' === job.status ) { return; }
		e.preventDefault();
		e.stopPropagation();
		if ( pop && ! pop.hidden ) { closePop(); } else { openPop(); }
	} );
	document.addEventListener( 'click', function () { if ( pop && ! pop.hidden ) { closePop(); } } );
	document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key && pop && ! pop.hidden ) { closePop(); link.focus(); } } );

	function markSeen() {
		if ( job ) { job.seen = true; }
		return post( 'flxy_seen', {} );
	}
	function dismiss() {
		markSeen();
		closePop();
	}

	function cancel() {
		if ( ! window.confirm( T.confirmCancel ) ) { return; }
		cancelling = true;
		render();
		post( 'flxy_cancel', {} ).then( function ( res ) {
			if ( ! res.success ) {
				cancelling = false;
				render();
				if ( res.data && res.data.message ) { window.alert( res.data.message ); }
				return;
			}
			schedule( 300 );
		} );
	}

	/* ---------------- Yoklama ---------------- */

	function emit() {
		listeners.forEach( function ( fn ) { try { fn( job ); } catch ( e ) {} } );
		try { document.dispatchEvent( new CustomEvent( 'flxy:job', { detail: job } ) ); } catch ( e ) {}
	}

	function schedule( ms ) {
		clearTimeout( timer );
		timer = setTimeout( poll, ms );
	}

	function poll() {
		if ( busy ) { return; }
		busy = true;
		post( 'flxy_status', { token: token, locale: C.locale || '' } ).then( function ( res ) {
			busy = false;
			if ( ! res || ! res.success ) { throw new Error( 'auth' ); }
			fails = 0;
			var r = res.data;
			if ( r.token ) { token = r.token; }
			if ( 'idle' === r.status ) { job = null; render(); emit(); return; }
			if ( 'running' !== r.status ) { cancelling = false; }
			job = r;
			lastStatus = r.status;
			render();
			emit();
			if ( 'running' === r.status ) {
				schedule( 3000 );
			} else if ( pop && ! pop.hidden && ! r.cancelled ) {
				markSeen(); // Panel açıkken bitti: kullanıcı sonucu gördü.
			} else if ( r.cancelled ) {
				markSeen();
				setTimeout( function () { if ( job === r ) { job = null; render(); } }, 4000 );
			}
		} ).catch( function () {
			busy = false;
			fails++;
			if ( job && 'running' === job.status ) {
				text.textContent = T.retry;
			}
			if ( fails < 40 ) { schedule( Math.min( 15000, 2000 * fails ) ); }
		} );
	}

	window.FLXYBar = {
		/** Yeni başlatılan işi izlemeye al (yönetim ekranı çağırır). */
		track: function ( r ) {
			if ( ! r ) { return; }
			job = r;
			token = r.token || token;
			lastStatus = r.status;
			cancelling = false;
			fails = 0;
			render();
			emit();
			schedule( 1200 );
		},
		on: function ( fn ) { listeners.push( fn ); },
		open: openPop,
		current: function () { return job; }
	};

	render();
	if ( job && 'running' === job.status ) { schedule( 1500 ); }
}() );
