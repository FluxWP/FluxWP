/* Flux Backup yönetim ekranı: yedek başlatma (arka planda), geri yükleme, yedek yükleme, silme/kilitleme, ayrıntı ve grafik. */
( function () {
	'use strict';

	var Y = window.FLXY || {};
	var T = Y.i18n || {};
	var $ = function ( sel, ctx ) { return ( ctx || document ).querySelector( sel ); };
	var $$ = function ( sel, ctx ) { return Array.prototype.slice.call( ( ctx || document ).querySelectorAll( sel ) ); };

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', Y.nonce );
		Object.keys( data || {} ).forEach( function ( k ) {
			var v = data[ k ];
			if ( Array.isArray( v ) ) {
				v.forEach( function ( x ) { body.append( k + '[]', x ); } );
			} else if ( v !== undefined && v !== null ) {
				body.append( k, v );
			}
		} );
		return fetch( Y.ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) {
			return r.json().catch( function () { return { success: false, data: { message: T.error } }; } );
		} );
	}

	/* ---------------- Pencereler ---------------- */

	function openModal( el ) {
		el.hidden = false;
		var f = $( 'input, button', el );
		if ( f ) { f.focus(); }
	}
	function closeModal( el ) { el.hidden = true; }

	$$( '.flxy-modal' ).forEach( function ( m ) {
		m.addEventListener( 'click', function ( e ) {
			if ( e.target === m ) { closeModal( m ); }
		} );
		$$( '.flxy-modal-close', m ).forEach( function ( b ) {
			b.addEventListener( 'click', function () { closeModal( m ); } );
		} );
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			$$( '#flxy-backup-modal, #flxy-restore-modal' ).forEach( closeModal );
		}
	} );

	/* ---------------- Bildirimler ve gösterge ---------------- */

	function fmt( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( str ).replace( /%(\d+\$)?s/g, function ( m, n ) {
			return String( n ? args[ parseInt( n, 10 ) - 1 ] : args[ i++ ] );
		} );
	}

	/** Kapatılabilir WP bildirimi; links: [{text, href|onClick}] */
	function toast( type, msg, links ) {
		var box = $( '#flxy-toasts' );
		if ( ! box ) { window.flxDialog.alert( msg ); return null; }
		var n = document.createElement( 'div' );
		n.className = 'notice notice-' + type + ' is-dismissible flxy-toast';
		var p = document.createElement( 'p' );
		p.textContent = msg;
		( links || [] ).forEach( function ( l ) {
			p.appendChild( document.createTextNode( ' ' ) );
			var a = document.createElement( l.href ? 'a' : 'button' );
			a.textContent = l.text;
			if ( l.href ) { a.href = l.href; } else { a.type = 'button'; a.className = 'button-link'; a.addEventListener( 'click', l.onClick ); }
			p.appendChild( a );
		} );
		n.appendChild( p );
		var x = document.createElement( 'button' );
		x.type = 'button';
		x.className = 'notice-dismiss';
		x.innerHTML = '<span class="screen-reader-text"></span>';
		x.firstChild.textContent = T.dismiss || '';
		x.addEventListener( 'click', function () { n.parentNode.removeChild( n ); } );
		n.appendChild( x );
		box.appendChild( n );
		return n;
	}

	function track( r ) {
		if ( window.FLXYBar ) { window.FLXYBar.track( r ); }
	}

	function modalOpen() {
		return $$( '.flxy-modal' ).some( function ( m ) { return ! m.hidden; } );
	}

	// İş bitince: yedek sayfalarında listeyi tazele (pencere açık değilse), yoksa bildirim göster.
	var seenRunning = false;
	document.addEventListener( 'flxy:job', function ( e ) {
		var r = e.detail;
		if ( ! r ) { return; }
		if ( 'running' === r.status ) { seenRunning = true; return; }
		if ( ! seenRunning ) { return; }
		seenRunning = false;
		if ( 'backup' !== r.kind || 'done' !== r.status ) { return; }
		if ( ! modalOpen() && ! uploading ) {
			window.location.reload();
			return;
		}
		toast( 'success', T.backupReady + ( r.size_h ? ' ' + r.size_h : '' ), [ { text: T.details, href: r.url }, { text: T.showList, onClick: function () { window.location.reload(); } } ] );
	} );

	/* ---------------- Şimdi yedek al ---------------- */

	var backupModal = $( '#flxy-backup-modal' );
	$$( '.flxy-open-backup' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () { if ( backupModal ) { openModal( backupModal ); } } );
	} );
	var bform = $( '#flxy-backup-form' );
	if ( bform ) {
		var syncCore = function () {
			var t = ( $( 'input[name=type]:checked', bform ) || {} ).value;
			$( '.flxy-core-opt', bform ).hidden = 'full' !== t;
		};
		$$( 'input[name=type]', bform ).forEach( function ( i ) { i.addEventListener( 'change', syncCore ); } );
		syncCore();
		bform.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var fd = new FormData( bform );
			var btn = $( 'button[type=submit]', bform );
			btn.disabled = true;
			post( 'flxy_start', {
				type: fd.get( 'type' ),
				label: fd.get( 'label' ) || '',
				include_core: fd.get( 'include_core' ) ? 1 : 0,
				lock: fd.get( 'lock' ) ? 1 : 0
			} ).then( function ( res ) {
				btn.disabled = false;
				if ( ! res.success ) {
					window.flxDialog.alert( res.data && res.data.message ? res.data.message : T.error );
					return;
				}
				closeModal( backupModal );
				bform.reset();
				syncCore();
				toast( 'info', T.bgBackup );
				track( res.data );
			} );
		} );
	}

	/* ---------------- Geri yükleme ---------------- */

	var restoreModal = $( '#flxy-restore-modal' );
	var rform = $( '#flxy-restore-form' );

	function el( tag, attrs, text ) {
		var n = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) { n.setAttribute( k, attrs[ k ] ); } );
		if ( text ) { n.textContent = text; }
		return n;
	}

	$$( '.flxy-restore' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			var d = JSON.parse( b.getAttribute( 'data-restore' ) );
			$( '.flxy-restore-name', restoreModal ).textContent = d.title;
			var warn = $( '.flxy-restore-foreign', restoreModal );
			var msgs = [];
			if ( d.foreign ) { msgs.push( fmt( T.foreign, d.foreign ) ); }
			if ( d.prefix ) { msgs.push( fmt( T.prefixMismatch, d.prefix[ 0 ], d.prefix[ 1 ] ) ); }
			warn.hidden = ! msgs.length;
			$( 'p', warn ).textContent = msgs.join( ' ' );
			rform.elements.id.value = d.id;
			var box = $( '.flxy-restore-parts', rform );
			box.textContent = '';
			if ( d.db ) {
				var g = el( 'fieldset', { 'class': 'flxy-part-group' } );
				var l = el( 'label', { 'class': 'flxy-part-db' } );
				var cb = el( 'input', { type: 'checkbox', name: 'db', value: '1' } );
				l.appendChild( cb );
				l.appendChild( document.createTextNode( ' ' + T.db ) );
				g.appendChild( l );
				box.appendChild( g );
			}
			( d.parts || [] ).forEach( function ( grp ) {
				var g = el( 'fieldset', { 'class': 'flxy-part-group' } );
				g.appendChild( el( 'legend', {}, grp.label ) );
				var list = el( 'div', { 'class': 'flxy-part-list' } );
				grp.items.forEach( function ( it ) {
					var l = el( 'label' );
					var cb = el( 'input', { type: 'checkbox', name: 'paths', value: it.path } );
					l.appendChild( cb );
					l.appendChild( document.createTextNode( ' ' + it.label ) );
					list.appendChild( l );
				} );
				g.appendChild( list );
				box.appendChild( g );
			} );
			var syncSafety = function () {
				var dbOn = rform.elements.db && rform.elements.db.checked;
				$( '.flxy-safety', rform ).hidden = ! dbOn;
			};
			if ( rform.elements.db ) { rform.elements.db.addEventListener( 'change', syncSafety ); }
			syncSafety();
			openModal( restoreModal );
		} );
	} );

	if ( rform ) {
		rform.addEventListener( 'submit', async function ( e ) {
			e.preventDefault();
			var db = rform.elements.db && rform.elements.db.checked;
			var paths = $$( 'input[name=paths]:checked', rform ).map( function ( i ) { return i.value; } );
			if ( ! db && ! paths.length ) { window.flxDialog.alert( T.pickPart ); return; }
			if ( ! (await window.flxDialog.confirm({ message:  T.confirmRestore  })) ) { return; }
			var btn = $( 'button[type=submit]', rform );
			btn.disabled = true;
			post( 'flxy_restore_start', {
				id: rform.elements.id.value,
				db: db ? 1 : 0,
				paths: paths,
				safety: rform.elements.safety.checked ? 1 : 0
			} ).then( function ( res ) {
				btn.disabled = false;
				if ( ! res.success ) {
					window.flxDialog.alert( res.data && res.data.message ? res.data.message : T.error );
					return;
				}
				closeModal( restoreModal );
				toast( 'warning', T.bgRestore );
				track( res.data );
				if ( window.FLXYBar ) { window.FLXYBar.open(); }
			} );
		} );
	}

	/* ---------------- Liste: sil, toplu sil, kilit ---------------- */

	var listForm = $( '#flxy-list-form' );
	if ( listForm ) {
		var bulk = $( '.flxy-bulk-delete', listForm );
		var syncBulk = function () {
			bulk.disabled = ! $$( 'input[name="ids[]"]:checked', listForm ).length;
		};
		listForm.addEventListener( 'change', function ( e ) {
			if ( e.target.classList.contains( 'flxy-check-all' ) ) {
				$$( 'input[name="ids[]"]', listForm ).forEach( function ( c ) { c.checked = e.target.checked; } );
			}
			syncBulk();
		} );
		var remove = function ( ids ) {
			return post( 'flxy_delete', { ids: ids } ).then( function ( res ) {
				if ( ! res.success ) { window.flxDialog.alert( res.data && res.data.message ? res.data.message : T.error ); return; }
				res.data.deleted.forEach( function ( id ) {
					var tr = $( 'tr[data-id="' + id + '"]', listForm );
					if ( tr ) { tr.parentNode.removeChild( tr ); }
				} );
				var n = $( '.displaying-num', listForm );
				if ( n && res.data.total ) { n.textContent = n.textContent.replace( /\S+\s*\S*$/, res.data.total ); }
				syncBulk();
			} );
		};
		bulk.addEventListener( 'click', async function () {
			var ids = $$( 'input[name="ids[]"]:checked', listForm ).map( function ( c ) { return c.value; } );
			if ( ids.length && (await window.flxDialog.confirm({ message:  T.confirmBulk  })) ) { remove( ids ); }
		} );
		listForm.addEventListener( 'click', async function ( e ) {
			// Satırın boş bir yerine tıklamak ayrıntıyı açar.
			if ( ! e.target.closest( 'a, button, input, label, .check-column' ) ) {
				var row = e.target.closest( 'tr[data-id]' );
				var link = row && $( '.flxy-row-link', row );
				if ( link && ! ( window.getSelection && String( window.getSelection() ) ) ) { window.location.href = link.href; return; }
			}
			var del = e.target.closest( '.flxy-delete' );
			if ( del ) {
				var id = del.closest( 'tr' ).getAttribute( 'data-id' );
				if ( (await window.flxDialog.confirm({ message:  T.confirmDelete , danger: true })) ) { remove( [ id ] ); }
				return;
			}
			var lock = e.target.closest( '.flxy-lock' );
			if ( lock ) {
				var tr = lock.closest( 'tr' );
				var to = '1' !== lock.getAttribute( 'data-locked' );
				lock.disabled = true;
				post( 'flxy_lock', { id: tr.getAttribute( 'data-id' ), lock: to ? 1 : 0 } ).then( function ( res ) {
					lock.disabled = false;
					if ( ! res.success ) { window.flxDialog.alert( res.data && res.data.message ? res.data.message : T.error ); return; }
					lock.setAttribute( 'data-locked', to ? '1' : '0' );
					lock.setAttribute( 'aria-pressed', to ? 'true' : 'false' );
					lock.classList.toggle( 'is-locked', to );
					lock.title = res.data.title;
					var ic = $( '.dashicons', lock );
					ic.className = 'dashicons ' + ( to ? 'dashicons-lock' : 'dashicons-unlock' );
					$( '.flxy-keep-text', tr ).textContent = res.data.text;
				} );
			}
		} );
	}

	/* ---------------- Yedek yükleme (parça parça) ---------------- */

	var uploading = false;
	var fileInput = $( '#flxy-upload-file' );
	var upPanel = $( '#flxy-upload-panel' );
	if ( fileInput && upPanel ) {
		var upCancelled = false;
		var upUid = '';
		var setUp = function ( pct, status ) {
			var bar = $( '.flxy-progress', upPanel );
			bar.setAttribute( 'aria-valuenow', String( Math.round( pct ) ) );
			$( 'span', bar ).style.width = pct + '%';
			$( '.flxy-upload-status', upPanel ).textContent = status;
		};
		var sizeH = function ( b ) {
			var u = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
			var i = 0;
			while ( b >= 1024 && i < u.length - 1 ) { b /= 1024; i++; }
			return ( i ? b.toFixed( 1 ).replace( '.', Y.dec || '.' ) : b ) + ' ' + u[ i ];
		};
		var endUpload = function () {
			uploading = false;
			fileInput.value = '';
			fileInput.disabled = false;
			upPanel.hidden = true;
			upPanel.classList.remove( 'is-verifying' );
		};
		var failUpload = function ( msg ) {
			if ( upUid ) { post( 'flxy_upload_abort', { uid: upUid } ); }
			endUpload();
			toast( 'error', fmt( T.uploadFailed, msg || T.error ) );
		};
		var sendChunk = function ( file, chunk, offset, tries ) {
			if ( upCancelled ) { return; }
			if ( offset >= file.size ) {
				upPanel.classList.add( 'is-verifying' );
				setUp( 100, T.verifying );
				post( 'flxy_upload_finish', { uid: upUid } ).then( function ( res ) {
					var uid = upUid;
					upUid = '';
					if ( ! res.success ) { upUid = uid; failUpload( res.data && res.data.message ); return; }
					endUpload();
					toast( 'success', res.data.message, [ { text: T.details, href: res.data.url } ] );
					setTimeout( function () { if ( ! modalOpen() ) { window.location.href = res.data.url; } }, 1500 );
				} ).catch( function () { failUpload( T.error ); } );
				return;
			}
			var blob = file.slice( offset, Math.min( file.size, offset + chunk ) );
			var body = new FormData();
			body.append( 'action', 'flxy_upload_chunk' );
			body.append( 'nonce', Y.nonce );
			body.append( 'uid', upUid );
			body.append( 'offset', String( offset ) );
			body.append( 'chunk', blob, 'chunk.bin' );
			fetch( Y.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( ! res.success ) {
						if ( res.data && 'number' === typeof res.data.received && tries < 3 ) {
							sendChunk( file, chunk, res.data.received, tries + 1 );
							return;
						}
						failUpload( res.data && res.data.message );
						return;
					}
					var got = res.data.received;
					setUp( 100 * got / file.size, fmt( T.uploadProgress, sizeH( got ), sizeH( file.size ) ) );
					sendChunk( file, chunk, got, 0 );
				} )
				.catch( function () {
					if ( tries < 5 && ! upCancelled ) {
						setUp( 100 * offset / file.size, T.retry );
						setTimeout( function () { sendChunk( file, chunk, offset, tries + 1 ); }, 1500 * ( tries + 1 ) );
						return;
					}
					failUpload( T.error );
				} );
		};
		fileInput.addEventListener( 'change', function () {
			var file = fileInput.files && fileInput.files[ 0 ];
			if ( ! file || uploading ) { return; }
			if ( ! /\.zip$/i.test( file.name ) ) { toast( 'error', T.uploadNotZip ); fileInput.value = ''; return; }
			uploading = true;
			upCancelled = false;
			fileInput.disabled = true;
			upPanel.hidden = false;
			$( '.flxy-upload-name', upPanel ).textContent = fmt( T.uploading, file.name );
			setUp( 0, fmt( T.uploadProgress, sizeH( 0 ), sizeH( file.size ) ) );
			post( 'flxy_upload_start', { name: file.name, size: file.size } ).then( function ( res ) {
				if ( ! res.success ) { failUpload( res.data && res.data.message ); return; }
				upUid = res.data.uid;
				sendChunk( file, res.data.chunk || Y.chunk || 2097152, 0, 0 );
			} );
		} );
		$( '.flxy-upload-cancel', upPanel ).addEventListener( 'click', function () {
			upCancelled = true;
			if ( upUid ) { post( 'flxy_upload_abort', { uid: upUid } ); upUid = ''; }
			endUpload();
			toast( 'info', T.uploadCancel );
		} );
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( uploading ) { e.preventDefault(); e.returnValue = ''; }
		} );
	}

	/* ---------------- Ayrıntı: boyut dağılımı ---------------- */

	var bd = $( '.flxy-breakdown' );
	if ( bd ) {
		var setMetric = function ( m ) {
			bd.setAttribute( 'data-metric', m );
			$$( '.flxy-metric button', bd ).forEach( function ( b ) { b.setAttribute( 'aria-pressed', String( b.getAttribute( 'data-metric' ) === m ) ); } );
			$$( '[data-raw]', bd ).forEach( function ( n ) { n.hidden = 'raw' !== m; } );
			$$( '[data-zip]', bd ).forEach( function ( n ) { n.hidden = 'zip' !== m; } );
			try { window.localStorage.setItem( 'flxy-metric', m ); } catch ( e ) {}
		};
		$$( '.flxy-metric button', bd ).forEach( function ( b ) {
			b.addEventListener( 'click', function () { setMetric( b.getAttribute( 'data-metric' ) ); } );
		} );
		var saved = null;
		try { saved = window.localStorage.getItem( 'flxy-metric' ); } catch ( e ) {}
		if ( 'zip' === saved ) { setMetric( 'zip' ); }
		$$( '.flxy-bd-toggle', bd ).forEach( function ( t ) {
			t.addEventListener( 'click', function () {
				var open = 'true' !== t.getAttribute( 'aria-expanded' );
				t.setAttribute( 'aria-expanded', String( open ) );
				document.getElementById( t.getAttribute( 'aria-controls' ) ).hidden = ! open;
				t.closest( 'tbody' ).classList.toggle( 'is-open', open );
			} );
		} );
		// Kategori satırının tamamı tıklanabilir.
		$$( '.flxy-bd-head', bd ).forEach( function ( tr ) {
			tr.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '.flxy-bd-toggle' ) ) { return; }
				$( '.flxy-bd-toggle', tr ).click();
			} );
		} );
	}

	/* ---------------- Grafik: yığılmış alan, SVG ---------------- */

	var chartBox = $( '#flxy-history' );
	var dataEl = $( '#flxy-history-data' );
	if ( chartBox && dataEl ) {
		var D = JSON.parse( dataEl.textContent );
		var range = 30;
		try { range = parseInt( window.localStorage.getItem( 'flxy-range' ), 10 ) || 30; } catch ( e ) {}
		if ( [ 30, 90, 180 ].indexOf( range ) < 0 ) { range = 30; }
		var NS = 'http://www.w3.org/2000/svg';
		var svgEl = function ( tag, attrs ) {
			var n = document.createElementNS( NS, tag );
			Object.keys( attrs || {} ).forEach( function ( k ) { n.setAttribute( k, attrs[ k ] ); } );
			return n;
		};

		var draw = function () {
			chartBox.textContent = '';
			$$( '.flxy-chart-card .flxy-range button' ).forEach( function ( b ) {
				b.setAttribute( 'aria-pressed', String( parseInt( b.getAttribute( 'data-range' ), 10 ) === range ) );
			} );
			var days = D.days.slice( -range );
			var R = D.ranges[ range ];
			var hasData = days.some( function ( d ) { return d.h; } );
			var W = Math.max( 300, chartBox.clientWidth || 600 );
			var H = 260;
			var m = { l: 64, r: 16, t: 14, b: 30 };
			var iw = W - m.l - m.r;
			var ih = H - m.t - m.b;
			var svg = svgEl( 'svg', { width: W, height: H, viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': $( '.flxy-chart-card h2' ).textContent } );
			var x = function ( i ) { return m.l + ( days.length < 2 ? iw / 2 : iw * i / ( days.length - 1 ) ); };
			var y = function ( v ) { return m.t + ih - ih * v / ( R.max || 1 ); };

			// Izgara ve eksen etiketleri.
			R.ticks.forEach( function ( t ) {
				svg.appendChild( svgEl( 'line', { x1: m.l, x2: W - m.r, y1: y( t.v ), y2: y( t.v ), 'class': t.v ? 'flxy-grid-line' : 'flxy-axis-line' } ) );
				var tx = svgEl( 'text', { x: m.l - 8, y: y( t.v ) + 4, 'text-anchor': 'end', 'class': 'flxy-tick' } );
				tx.textContent = t.l;
				svg.appendChild( tx );
			} );
			var every = Math.ceil( days.length / Math.max( 2, Math.floor( iw / 70 ) ) );
			days.forEach( function ( d, i ) {
				if ( ( days.length - 1 - i ) % every ) { return; }
				var tx = svgEl( 'text', { x: x( i ), y: H - 8, 'text-anchor': 'middle', 'class': 'flxy-tick' } );
				tx.textContent = d.x;
				svg.appendChild( tx );
			} );

			// Yığılmış alanlar (alttan üste sabit sırayla), aralarında 2px yüzey boşluğu (beyaz çizgi).
			var base = days.map( function () { return 0; } );
			D.series.forEach( function ( s, si ) {
				var top = days.map( function ( d, i ) { return base[ i ] + d.v[ si ]; } );
				if ( ! top.some( function ( v, i ) { return v > base[ i ]; } ) ) { return; }
				var pts = top.map( function ( v, i ) { return x( i ) + ',' + y( v ); } );
				var back = base.map( function ( v, i ) { return x( i ) + ',' + y( v ); } ).reverse();
				svg.appendChild( svgEl( 'polygon', { points: pts.concat( back ).join( ' ' ), fill: s.color, 'fill-opacity': '0.85' } ) );
				svg.appendChild( svgEl( 'polyline', { points: pts.join( ' ' ), fill: 'none', stroke: '#fff', 'stroke-width': '2' } ) );
				base = top;
			} );
			// Toplam çizgisi.
			svg.appendChild( svgEl( 'polyline', { points: base.map( function ( v, i ) { return x( i ) + ',' + y( v ); } ).join( ' ' ), fill: 'none', stroke: '#1d2327', 'stroke-width': '2', 'stroke-linejoin': 'round' } ) );

			// Kota çizgisi (lejantta yalnız çizildiğinde).
			var ql0 = $( '.flxy-quota-legend' );
			if ( ql0 ) { ql0.hidden = ! R.showQuota; }
			if ( R.showQuota ) {
				svg.appendChild( svgEl( 'line', { x1: m.l, x2: W - m.r, y1: y( D.quota ), y2: y( D.quota ), 'class': 'flxy-quota-line' } ) );
				var ql = svgEl( 'text', { x: W - m.r - 4, y: y( D.quota ) - 6, 'text-anchor': 'end', 'class': 'flxy-quota-label' } );
				ql.textContent = D.quotaL;
				svg.appendChild( ql );
			}

			if ( ! hasData ) {
				var em = svgEl( 'text', { x: m.l + iw / 2, y: m.t + ih / 2, 'text-anchor': 'middle', 'class': 'flxy-tick' } );
				em.textContent = D.empty;
				svg.appendChild( em );
			}

			// Artı imleç ve ipucu.
			var cross = svgEl( 'line', { y1: m.t, y2: m.t + ih, 'class': 'flxy-cross', visibility: 'hidden' } );
			var dot = svgEl( 'circle', { r: 5, 'class': 'flxy-cross-dot', visibility: 'hidden' } );
			svg.appendChild( cross );
			svg.appendChild( dot );
			var hit = svgEl( 'rect', { x: m.l, y: m.t, width: iw, height: ih, fill: 'transparent' } );
			svg.appendChild( hit );
			chartBox.appendChild( svg );

			var tip = document.createElement( 'div' );
			tip.className = 'flxy-tip';
			tip.hidden = true;
			chartBox.appendChild( tip );

			var show = function ( clientX ) {
				var rect = svg.getBoundingClientRect();
				var px = clientX - rect.left;
				var i = days.length < 2 ? 0 : Math.round( ( px - m.l ) / iw * ( days.length - 1 ) );
				i = Math.max( 0, Math.min( days.length - 1, i ) );
				var d = days[ i ];
				var total = d.v.reduce( function ( a, b ) { return a + b; }, 0 );
				cross.setAttribute( 'x1', x( i ) );
				cross.setAttribute( 'x2', x( i ) );
				cross.setAttribute( 'visibility', 'visible' );
				dot.setAttribute( 'cx', x( i ) );
				dot.setAttribute( 'cy', y( total ) );
				dot.setAttribute( 'visibility', 'visible' );
				tip.textContent = '';
				var h = document.createElement( 'strong' );
				h.textContent = d.d;
				tip.appendChild( h );
				if ( ! d.h ) {
					var nd = document.createElement( 'span' );
					nd.textContent = D.noData;
					tip.appendChild( nd );
					tip.hidden = false;
					tip.style.left = Math.min( x( i ) + 14, W - tip.offsetWidth ) + 'px';
					tip.style.top = ( m.t + 4 ) + 'px';
					return;
				}
				D.series.slice().reverse().forEach( function ( s ) {
					var si = D.series.indexOf( s );
					if ( ! d.v[ si ] ) { return; }
					var row = document.createElement( 'div' );
					var sw = document.createElement( 'i' );
					sw.style.background = s.color;
					row.appendChild( sw );
					row.appendChild( document.createTextNode( s.name + ' ' ) );
					var b = document.createElement( 'b' );
					b.textContent = d.vl[ si ];
					row.appendChild( b );
					tip.appendChild( row );
				} );
				var tr = document.createElement( 'div' );
				tr.className = 'flxy-tip-total';
				tr.appendChild( document.createTextNode( D.total + ' ' ) );
				var tb = document.createElement( 'b' );
				tb.textContent = d.t;
				tr.appendChild( tb );
				tip.appendChild( tr );
				tip.hidden = false;
				var left = x( i ) + 14;
				if ( left + tip.offsetWidth > W ) { left = x( i ) - tip.offsetWidth - 14; }
				tip.style.left = left + 'px';
				tip.style.top = ( m.t + 4 ) + 'px';
			};
			var hide = function () {
				tip.hidden = true;
				cross.setAttribute( 'visibility', 'hidden' );
				dot.setAttribute( 'visibility', 'hidden' );
			};
			hit.addEventListener( 'mousemove', function ( e ) { show( e.clientX ); } );
			hit.addEventListener( 'mouseleave', hide );
			hit.addEventListener( 'touchstart', function ( e ) { show( e.touches[ 0 ].clientX ); }, { passive: true } );
		};

		$$( '.flxy-chart-card .flxy-range button' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				range = parseInt( b.getAttribute( 'data-range' ), 10 );
				try { window.localStorage.setItem( 'flxy-range', String( range ) ); } catch ( e ) {}
				draw();
			} );
		} );
		var rt;
		window.addEventListener( 'resize', function () { clearTimeout( rt ); rt = setTimeout( draw, 150 ); } );
		draw();
	}
}() );
