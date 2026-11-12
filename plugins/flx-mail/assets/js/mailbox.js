/* Flux Mail – Posta kutusu */
( function () {
	'use strict';
	/* Yalnız http(s) veya site içi adres; sonuç sabit bir önekle başlar. */
	var flxmSafeUrl = function (u) {
		u = String(u == null ? '' : u).trim();
		var l = u.toLowerCase();
		if (!(l.indexOf('https://') === 0 || l.indexOf('http://') === 0 || (u.charAt(0) === '/' && u.charAt(1) !== '/' && u.charAt(1) !== '\\'))) return '';
		// Normalised with encodeURI: quotes, spaces and angle brackets are escaped as well.
		try { return encodeURI(decodeURI(u)); } catch (e) { return ''; }
	};

	var C = window.FLXIMB;
	var root = document.querySelector( '[data-flxm]' );
	if ( ! C || ! root ) {
		return;
	}
	var T = C.i18n || {};
	var fmt = function ( s, v ) { return String( s || '' ).replace( '%s', v ); };

	/* ---------------- yardımcılar ---------------- */

	function el( tag, attrs, kids ) {
		var n = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) {
			var v = attrs[ k ];
			if ( null === v || undefined === v || false === v ) {
				return;
			}
			if ( 'class' === k ) {
				n.className = v;
			} else if ( 'text' === k ) {
				n.textContent = v;
			} else if ( 0 === k.indexOf( 'on' ) ) {
				n.addEventListener( k.slice( 2 ), v );
			} else if ( true === v ) {
				n.setAttribute( k, '' );
			} else {
				n.setAttribute( k, v );
			}
		} );
		( Array.isArray( kids ) ? kids : ( undefined === kids ? [] : [ kids ] ) ).forEach( function ( c ) {
			if ( null === c || undefined === c || false === c ) {
				return;
			}
			n.appendChild( 'string' === typeof c || 'number' === typeof c ? document.createTextNode( String( c ) ) : c );
		} );
		return n;
	}

	// Çizgi ikonlar (Lucide, MIT). Dashicons yönetim temasında kaydığı için kendi SVG'lerimiz.
	var ICONS = {
		inbox: '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
		check: '<circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/>',
		drafts: '<path d="M12.5 22H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v9.5"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M13.38 15.63a1 1 0 1 0-3-3l-5 5a2 2 0 0 0-.51.85l-.84 2.87a.5.5 0 0 0 .62.62l2.87-.84a2 2 0 0 0 .85-.5z"/>',
		send: '<path d="M14.54 21.69a.5.5 0 0 0 .94-.03l6.5-19a.5.5 0 0 0-.64-.64l-19 6.5a.5.5 0 0 0-.02.94l7.93 3.18a2 2 0 0 1 1.11 1.11z"/><path d="m21.85 2.15-10.94 10.94"/>',
		layout: '<rect width="18" height="7" x="3" y="3" rx="1.5"/><rect width="9" height="7" x="3" y="14" rx="1.5"/><rect width="5" height="7" x="16" y="14" rx="1.5"/>',
		pen: '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.38 2.63a1 1 0 0 1 3 3l-9.02 9.01a2 2 0 0 1-.85.51l-2.87.84a.5.5 0 0 1-.62-.62l.84-2.87a2 2 0 0 1 .5-.85z"/>',
		refresh: '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
		clip: '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
		reply: '<polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>',
		replyall: '<polyline points="7 17 2 12 7 7"/><polyline points="12 17 7 12 12 7"/><path d="M22 18v-2a4 4 0 0 0-4-4H7"/>',
		forward: '<polyline points="15 17 20 12 15 7"/><path d="M4 18v-2a4 4 0 0 1 4-4h12"/>',
		sparkles: '<path d="M9.94 15.5A2 2 0 0 0 8.5 14.06l-6.14-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.14a.5.5 0 0 1 .96 0l1.58 6.14a2 2 0 0 0 1.44 1.44l6.14 1.58a.5.5 0 0 1 0 .96l-6.14 1.58a2 2 0 0 0-1.44 1.44l-1.58 6.14a.5.5 0 0 1-.96 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/>',
		mail: '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
		unread: '<path d="M22 12v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h11"/><path d="m2 7 8.97 5.7a1.94 1.94 0 0 0 2.06 0L16 10.8"/><circle cx="20" cy="5" r="3"/>',
		star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
		back: '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
		trash: '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
		eye: '<path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/>',
		bold: '<path d="M6 12h9a4 4 0 0 1 0 8H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7a4 4 0 0 1 0 8"/>',
		italic: '<line x1="19" x2="10" y1="4" y2="4"/><line x1="14" x2="5" y1="20" y2="20"/><line x1="15" x2="9" y1="4" y2="20"/>',
		list: '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>',
		link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
		eraser: '<path d="m7 21-4.3-4.3c-1-1-1-2.5 0-3.4l9.6-9.6c1-1 2.5-1 3.4 0l5.6 5.6c1 1 1 2.5 0 3.4L13 21"/><path d="M22 21H7"/><path d="m5 11 9 9"/>',
		image: '<rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>',
		plus: '<path d="M5 12h14"/><path d="M12 5v14"/>',
		ok: '<path d="M20 6 9 17l-5-5"/>',
		x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		desktop: '<rect width="20" height="14" x="2" y="3" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>',
		phone: '<rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/>',
		moon: '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>'
	};
	function icon( name ) {
		var s = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
		s.setAttribute( 'viewBox', '0 0 24 24' );
		s.setAttribute( 'class', 'flxm-i' );
		s.setAttribute( 'aria-hidden', 'true' );
		s.setAttribute( 'focusable', 'false' );
		s.innerHTML = ICONS[ name ] || '';
		return s;
	}

	/** Düğme: cls içinde is-primary | is-sm | is-ai | is-ghost. Yazı her zaman son düğüm (yükleniyor metni için). */
	function btn( label, onclick, cls, ico ) {
		return el( 'button', { type: 'button', class: 'flxm-btn' + ( cls ? ' ' + cls : '' ), onclick: onclick }, [ ico ? icon( ico ) : null, document.createTextNode( label ) ] );
	}

	/** Yalnız ikonlu düğme. */
	function ibtn( ico, label, onclick, cls ) {
		return el( 'button', { type: 'button', class: 'flxm-ibtn' + ( cls ? ' ' + cls : '' ), title: label, 'aria-label': label, onclick: onclick }, [ icon( ico ) ] );
	}

	/** Kişi baş harfi (renk adresten). */
	function avatar( name, email ) {
		var key = String( email || name || '?' );
		var h = 0;
		for ( var i = 0; i < key.length; i++ ) {
			h = ( h * 31 + key.charCodeAt( i ) ) % 360;
		}
		var ch = String( name || email || '?' ).replace( /^[^\p{L}\p{N}]+/u, '' ).charAt( 0 ).toLocaleUpperCase( 'tr' ) || '?';
		return el( 'span', { class: 'flxm-av', style: 'background:hsl(' + h + ',45%,88%);color:hsl(' + h + ',45%,28%)', 'aria-hidden': 'true', text: ch } );
	}

	function api( path, data, method ) {
		var m = method || ( data ? 'POST' : 'GET' );
		var url = C.rest + path;
		if ( 'GET' === m && data ) {
			url += ( url.indexOf( '?' ) > -1 ? '&' : '?' ) + new URLSearchParams( data ).toString();
		}
		return fetch( url, {
			method: m,
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
			body: 'GET' === m ? undefined : JSON.stringify( data || {} )
		} ).then( function ( r ) {
			return r.json().catch( function () { return {}; } ).then( function ( j ) {
				if ( ! r.ok ) {
					throw new Error( ( j && j.message ) || r.statusText );
				}
				return j;
			} );
		} );
	}

	var toastBox = el( 'div', { class: 'flxm-toasts', 'aria-live': 'polite' } );
	document.body.appendChild( toastBox );
	function toast( text, kind ) {
		var t = el( 'div', { class: 'flxm-toast' + ( kind ? ' is-' + kind : '' ), text: text } );
		toastBox.appendChild( t );
		setTimeout( function () { t.remove(); }, kind === 'error' ? 7000 : 3500 );
	}

	function debounce( fn, ms ) {
		var t;
		return function () {
			var a = arguments;
			clearTimeout( t );
			t = setTimeout( function () { fn.apply( null, a ); }, ms );
		};
	}

	/** Sandbox'lı iframe'e HTML yazar ve yüksekliği içeriğe uydurur. */
	function frameWrite( frame, html, opts ) {
		opts = opts || {};
		// Kendi yazı tipi olmayan e-postalar tarayıcının serif varsayılanı yerine sade görünsün.
		var head = '<base target="_blank"><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;font-size:14px;line-height:1.5;color:#1d2327}</style>';
		if ( opts.blockRemote ) {
			head += '<meta http-equiv="Content-Security-Policy" content="img-src data: ' + location.origin + '; style-src \'unsafe-inline\' ' + location.origin + '; font-src data:">';
		}
		if ( opts.dark ) {
			html = String( html ).replace( /@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)/gi, '@media all' );
		}
		var doc = String( html );
		doc = /<head[^>]*>/i.test( doc ) ? doc.replace( /<head([^>]*)>/i, '<head$1>' + head ) : head + doc;
		frame.srcdoc = doc;
		frame.onload = function () {
			try {
				var d = frame.contentDocument;
				if ( opts.fit !== false && d && d.documentElement ) {
					frame.style.height = Math.max( 160, d.documentElement.scrollHeight + 4 ) + 'px';
				}
			} catch ( e ) {}
		};
	}

	/* ---------------- durum ---------------- */

	var S = {
		folder: 'inbox',
		search: '',
		system: false,
		unread: false,
		offset: 0,
		total: 0,
		status: C.status || {},
		layouts: C.layouts || [],
		current: null, // { type: 'mail'|'draft'|'layout', id }
		syncing: false
	};

	/* ---------------- iskelet ---------------- */

	var counts = {};
	function folderLink( key, ico ) {
		var c = el( 'b', { class: 'flxm-count' } );
		counts[ key ] = c;
		return el( 'a', { href: '#', class: 'flxm-folder', title: T[ key ], 'data-folder': key, onclick: function ( e ) { e.preventDefault(); setFolder( key ); } }, [ icon( ico ), el( 'span', { text: T[ key ] } ), c ] );
	}

	var syncText = el( 'small', { class: 'flxm-sync__text' } );
	var newBtn = btn( T.newMail, function () { newDraft(); }, 'is-primary flxm-new', 'pen' );
	newBtn.title = T.newMail;
	var syncBtn = btn( T.sync, function () { sync( true ); }, 'is-sm is-ghost', 'refresh' );
	var side = el( 'aside', { class: 'flxm-side' }, [
		newBtn,
		el( 'nav', { class: 'flxm-folders' }, [
			folderLink( 'inbox', 'inbox' ),
			folderLink( 'review', 'check' ),
			folderLink( 'drafts', 'drafts' ),
			folderLink( 'sent', 'send' ),
			folderLink( 'layouts', 'layout' )
		] ),
		el( 'div', { class: 'flxm-sync' }, [ syncBtn, syncText ] )
	] );

	var searchIn = el( 'input', { type: 'search', class: 'flxm-search', placeholder: T.search, 'aria-label': T.search } );
	function chip( label, key ) {
		return el( 'button', { type: 'button', class: 'flxm-chip', 'aria-pressed': 'false', onclick: function ( e ) {
			S[ key ] = ! S[ key ];
			e.currentTarget.setAttribute( 'aria-pressed', S[ key ] ? 'true' : 'false' );
			loadList();
		} }, label );
	}
	var filters = el( 'div', { class: 'flxm-filters', role: 'group' }, [ chip( T.unreadOnly, 'unread' ), chip( T.system, 'system' ) ] );
	var items = el( 'div', { class: 'flxm-items', role: 'list' } );
	var moreBtn = btn( T.more, function () { loadList( true ); }, 'flxm-more' );
	var notice = el( 'div', { class: 'flxm-notice' } );
	var list = el( 'section', { class: 'flxm-list' }, [ el( 'div', { class: 'flxm-list__bar' }, [ searchIn, filters ] ), notice, items, moreBtn ] );
	var pane = el( 'section', { class: 'flxm-pane', 'aria-live': 'polite' } );

	// Kutu darsa (yönetim menüsü açık, küçük ekran) sol menü yalnız ikon olur.
	if ( window.ResizeObserver ) {
		new ResizeObserver( function () { root.classList.toggle( 'is-narrow', root.clientWidth < 1080 ); } ).observe( root );
	}
	root.innerHTML = '';
	root.appendChild( side );
	root.appendChild( list );
	root.appendChild( pane );

	searchIn.addEventListener( 'input', debounce( function () { S.search = searchIn.value.trim(); loadList(); }, 350 ) );

	function setStatus( st ) {
		if ( ! st ) {
			return;
		}
		S.status = st;
		[ [ 'inbox', st.unread ], [ 'review', st.review ], [ 'drafts', st.drafts ] ].forEach( function ( x ) {
			counts[ x[ 0 ] ].textContent = x[ 1 ] ? String( x[ 1 ] ) : '';
		} );
		var t = st.error ? st.error : ( st.more ? T.syncMore : ( st.last_ok ? fmt( T.lastSync, st.last_ok ) : '' ) );
		syncText.textContent = S.syncing ? T.syncing : t;
		syncText.className = 'flxm-sync__text' + ( st.error ? ' is-error' : '' );
		syncBtn.disabled = ! st.enabled || S.syncing;
		notice.innerHTML = '';
		if ( ! st.enabled && ( 'inbox' === S.folder || 'sent' === S.folder ) ) {
			notice.appendChild( el( 'div', { class: 'flxm-callout' }, [ T.off, ' ', el( 'a', { href: C.settings, text: T.openSettings } ) ] ) );
		}
	}

	function setFolder( f ) {
		S.folder = f;
		S.offset = 0;
		Array.prototype.forEach.call( side.querySelectorAll( '.flxm-folder' ), function ( a ) {
			a.classList.toggle( 'is-active', a.getAttribute( 'data-folder' ) === f );
		} );
		var mails = 'inbox' === f || 'sent' === f;
		searchIn.hidden = ! mails;
		filters.hidden = ! mails;
		root.classList.remove( 'is-reading', 'is-composing' );
		// Şablon düzenleyicisi yalnız Şablonlar klasöründe açık kalır.
		if ( S.current && 'layout' === S.current.type && 'layouts' !== f ) {
			S.current = null;
		}
		loadList();
		if ( 'layouts' === f ) {
			showLayoutsIntro();
		} else if ( ! S.current ) {
			showEmpty();
		}
	}

	function showEmpty() {
		pane.innerHTML = '';
		pane.appendChild( el( 'div', { class: 'flxm-empty' }, [ icon( 'mail' ), el( 'p', { text: T.pick } ) ] ) );
	}

	function backBtn() {
		return el( 'button', { type: 'button', class: 'flxm-link flxm-back', onclick: closePane }, [ icon( 'back' ), T.back ] );
	}

	async function closePane() {
		if ( S.dirty && ! (await window.flxDialog.confirm({ message:  T.leave  })) ) {
			return;
		}
		S.dirty = false;
		root.classList.remove( 'is-reading', 'is-composing' );
		S.current = null;
		markActive();
		if ( 'layouts' === S.folder ) {
			showLayoutsIntro();
		} else {
			showEmpty();
		}
	}

	function markActive() {
		Array.prototype.forEach.call( items.children, function ( n ) {
			n.classList.toggle( 'is-active', !! S.current && n.getAttribute( 'data-key' ) === S.current.type + ':' + S.current.id );
		} );
	}

	/* ---------------- liste ---------------- */

	var listSeq = 0;
	function loadList( append ) {
		var f = S.folder;
		var seq = ++listSeq;
		if ( ! append ) {
			S.offset = 0;
		}
		moreBtn.hidden = true;
		var req;
		if ( 'inbox' === f || 'sent' === f ) {
			req = api( 'list', { folder: f, search: S.search, system: S.system ? 1 : 0, unread: S.unread ? 1 : 0, offset: S.offset }, 'GET' );
		} else if ( 'review' === f || 'drafts' === f ) {
			req = api( 'drafts', 'review' === f ? { status: 'review' } : null, 'GET' ).then( function ( r ) {
				if ( 'drafts' === f ) {
					r.rows = r.rows.filter( function ( d ) { return 'review' !== d.status; } );
				}
				return r;
			} );
		} else {
			req = api( 'layouts', null, 'GET' ).then( function ( rows ) { S.layouts = rows; return { rows: rows }; } );
		}
		return req.then( function ( r ) {
			if ( seq !== listSeq ) {
				return;
			}
			if ( r.status ) {
				setStatus( r.status );
			}
			if ( ! append ) {
				items.innerHTML = '';
			}
			( r.rows || [] ).forEach( function ( row ) {
				items.appendChild( 'layouts' === f ? layoutItem( row ) : ( 'inbox' === f || 'sent' === f ? mailItem( row ) : draftItem( row ) ) );
			} );
			if ( ! items.children.length ) {
				items.appendChild( el( 'p', { class: 'flxm-items__empty', text: 'review' === f || 'drafts' === f ? T.emptyDrafts : T.empty } ) );
			}
			if ( r.total !== undefined ) {
				S.total = r.total;
				S.offset += ( r.rows || [] ).length;
				moreBtn.hidden = S.offset >= S.total;
			}
			markActive();
		} ).catch( function ( e ) { toast( e.message, 'error' ); } );
	}

	function mailItem( m ) {
		var who = 'sent' === m.folder ? ( T.to + ': ' + ( m.to || '' ) ) : m.from;
		var badges = el( 'span', { class: 'flxm-badges' }, [
			m.att ? el( 'span', { class: 'flxm-mini', title: T.attachments }, [ icon( 'clip' ) ] ) : null,
			m.replied ? el( 'span', { class: 'flxm-mini', title: T.replied }, [ icon( 'reply' ) ] ) : null,
			m.system ? el( 'span', { class: 'flxm-badge is-muted', text: T.sys } ) : null,
			m.starred ? el( 'span', { class: 'flxm-mini is-star' }, [ icon( 'star' ) ] ) : null
		] );
		return el( 'div', {
			role: 'listitem',
			tabindex: '0',
			class: 'flxm-item' + ( m.read ? '' : ' is-unread' ),
			'data-key': 'mail:' + m.id,
			onclick: function () { openMail( m.id ); },
			onkeydown: function ( e ) { if ( 'Enter' === e.key ) { openMail( m.id ); } }
		}, [
			avatar( 'sent' === m.folder ? m.to : m.from, m.from_email ),
			el( 'div', { class: 'flxm-item__main' }, [
				el( 'div', { class: 'flxm-item__top' }, [ el( 'span', { class: 'flxm-item__who', text: who } ), el( 'time', { datetime: m.iso, text: m.date } ) ] ),
				el( 'div', { class: 'flxm-item__subj' }, [ el( 'span', { text: m.subject } ), badges ] ),
				el( 'div', { class: 'flxm-item__snip', text: m.snippet || '' } )
			] )
		] );
	}

	function draftItem( d ) {
		return el( 'div', {
			role: 'listitem',
			tabindex: '0',
			class: 'flxm-item',
			'data-key': 'draft:' + d.id,
			onclick: function () { openDraft( d.id ); },
			onkeydown: function ( e ) { if ( 'Enter' === e.key ) { openDraft( d.id ); } }
		}, [
			el( 'span', { class: 'flxm-av is-icon' + ( 'ai' === d.origin ? ' is-ai' : '' ), 'aria-hidden': 'true' }, [ icon( 'ai' === d.origin ? 'sparkles' : 'drafts' ) ] ),
			el( 'div', { class: 'flxm-item__main' }, [
				el( 'div', { class: 'flxm-item__top' }, [ el( 'span', { class: 'flxm-item__who', text: d.to ? T.to + ': ' + d.to : '—' } ), el( 'time', { text: d.date } ) ] ),
				el( 'div', { class: 'flxm-item__subj' }, [ el( 'span', { text: d.subject || T.noSubject } ), el( 'span', { class: 'flxm-badges' }, [
					el( 'span', { class: 'flxm-badge' + ( 'failed' === d.status ? ' is-error' : ( 'review' === d.status ? ' is-ai' : '' ) ), text: d.status_text } )
				] ) ] ),
				d.error ? el( 'div', { class: 'flxm-item__snip is-error', text: d.error } ) : null
			] )
		] );
	}

	function layoutItem( l ) {
		return el( 'div', {
			role: 'listitem',
			tabindex: '0',
			class: 'flxm-item',
			'data-key': 'layout:' + l.id,
			onclick: function () { openLayout( l.id ); },
			onkeydown: function ( e ) { if ( 'Enter' === e.key ) { openLayout( l.id ); } }
		}, [
			l.partner_logo ? el( 'img', { class: 'flxm-av is-logo', src: l.partner_logo, alt: '' } ) : el( 'span', { class: 'flxm-av is-icon', 'aria-hidden': 'true', style: l.accent ? 'color:' + l.accent : null }, [ icon( 'layout' ) ] ),
			el( 'div', { class: 'flxm-item__main' }, [
				el( 'div', { class: 'flxm-item__subj' }, [ el( 'span', { text: l.name } ), l.builtin ? el( 'span', { class: 'flxm-badge is-muted', text: T.builtinShort } ) : null ] ),
				el( 'div', { class: 'flxm-item__snip', text: l.partner_name ? ( l.partner_name + ( l.desc ? ' · ' + l.desc : '' ) ) : ( l.desc || '' ) } )
			] )
		] );
	}

	/* ---------------- okuma ---------------- */

	function openMail( id ) {
		S.current = { type: 'mail', id: id };
		markActive();
		root.classList.add( 'is-reading' );
		pane.innerHTML = '';
		pane.appendChild( el( 'p', { class: 'flxm-loading', text: '…' } ) );
		api( 'item/' + id, null, 'GET' ).then( function ( m ) {
			renderMail( m );
			var li = items.querySelector( '[data-key="mail:' + id + '"]' );
			if ( li ) {
				li.classList.remove( 'is-unread' );
			}
			loadCounts();
		} ).catch( function ( e ) { pane.innerHTML = ''; toast( e.message, 'error' ); } );
	}

	function renderMail( m ) {
		pane.innerHTML = '';
		var aiBox = null;
		var actions = el( 'div', { class: 'flxm-actions' }, [
			btn( T.reply, function () { startReply( m.id, 'reply' ); }, 'is-primary', 'reply' ),
			btn( T.replyAll, function () { startReply( m.id, 'replyall' ); }, '', 'replyall' ),
			btn( T.forward, function () { startReply( m.id, 'forward' ); }, '', 'forward' ),
			'inbox' === m.folder ? btn( T.aiReply, function () { aiBox.hidden = ! aiBox.hidden; if ( ! aiBox.hidden ) { aiBox.querySelector( 'textarea' ).focus(); } }, 'is-ai', 'sparkles' ) : null,
		] );

		if ( 'inbox' === m.folder ) {
			var ins = el( 'textarea', { rows: '2', placeholder: T.aiHint } );
			var go = btn( T.aiWrite, function () {
				if ( ! S.status.ai ) {
					toast( T.aiOff, 'error' );
					return;
				}
				go.disabled = true;
				go.lastChild.textContent = T.aiWorking;
				api( 'ai', { mail_id: m.id, instruction: ins.value } ).then( function ( d ) {
					loadCounts();
					openDraftData( d );
				} ).catch( function ( e ) { toast( e.message, 'error' ); } ).then( function () { go.disabled = false; go.lastChild.textContent = T.aiWrite; } );
			}, 'is-ai is-solid', 'sparkles' );
			aiBox = el( 'div', { class: 'flxm-aibox', hidden: true }, [ ins, go, S.status.ai ? null : el( 'small', { class: 'flxm-muted', text: T.aiOff } ) ] );
		}

		var tools = el( 'div', { class: 'flxm-read__tools' }, [
			'inbox' === m.folder ? ibtn( 'unread', T.markUnread, function () {
				api( 'item/' + m.id + '/mark', { field: 'read', value: 0 } ).then( function ( r ) { setStatus( r.status ); loadList(); toast( T.saved ); } );
			} ) : null,
			el( 'button', { type: 'button', class: 'flxm-ibtn flxm-star' + ( m.starred ? ' is-on' : '' ), title: T.star, 'aria-label': T.star, 'aria-pressed': m.starred ? 'true' : 'false', onclick: function ( e ) {
				var b = e.currentTarget;
				var on = ! b.classList.contains( 'is-on' );
				api( 'item/' + m.id + '/mark', { field: 'starred', value: on ? 1 : 0 } ).then( function () { b.classList.toggle( 'is-on', on ); b.setAttribute( 'aria-pressed', on ? 'true' : 'false' ); } );
			} }, [ icon( 'star' ) ] )
		] );

		var meta = el( 'div', { class: 'flxm-sender' }, [
			avatar( m.from_name || m.from_email, m.from_email ),
			el( 'div', { class: 'flxm-sender__main' }, [
				el( 'div', { class: 'flxm-sender__name' }, [ el( 'strong', { text: m.from_name || m.from_email } ), m.from_name ? el( 'span', { text: m.from_email } ) : null ] ),
				el( 'div', { class: 'flxm-sender__to' }, [ T.to + ': ' + ( m.to || '' ) + ( m.cc ? ' · ' + T.cc + ': ' + m.cc : '' ) ] )
			] ),
			el( 'time', { class: 'flxm-sender__date', text: m.full_date } )
		] );

		var thread = null;
		if ( m.thread && m.thread.length ) {
			thread = el( 'details', { class: 'flxm-thread' }, [
				el( 'summary', { text: T.thread + ' (' + m.thread.length + ')' } ),
				el( 'ol', {}, m.thread.map( function ( t ) {
					return el( 'li', { class: t.id === m.id ? 'is-current' : '' }, [
						el( 'a', { href: '#', onclick: function ( e ) { e.preventDefault(); openMail( t.id ); } }, [
							el( 'strong', { text: 'sent' === t.folder ? T.me : t.from } ), ' · ', el( 'span', { text: t.date } ), ' — ', el( 'span', { text: t.snippet || t.subject } )
						] )
					] );
				} ) )
			] );
		}

		var atts = null;
		if ( m.attachments && m.attachments.length ) {
			atts = el( 'div', { class: 'flxm-atts', 'aria-label': T.attachments }, m.attachments.map( function ( a ) {
				return el( 'a', { class: 'flxm-att', href: a.url, target: '_blank', rel: 'noopener' }, [ icon( 'clip' ), el( 'span', { text: a.name } ), el( 'small', { text: a.size } ) ] );
			} ) );
		}

		var frame = el( 'iframe', { class: 'flxm-body', title: m.subject, sandbox: 'allow-same-origin allow-popups allow-popups-to-escape-sandbox' } );
		var remote = /<img[^>]+src=["']?https?:/i.test( m.html ) && m.source === 'imap';
		var imgBar = null;
		if ( remote ) {
			imgBar = el( 'div', { class: 'flxm-imgbar' }, [ T.imagesBlocked, ' ', el( 'button', { type: 'button', class: 'flxm-link', text: T.images, onclick: function () { frameWrite( frame, m.html ); imgBar.remove(); } } ) ] );
		}

		pane.appendChild( el( 'article', { class: 'flxm-read' }, [
			backBtn(),
			el( 'div', { class: 'flxm-read__head' }, [ el( 'h2', { class: 'flxm-read__subj' }, [ m.subject, m.system ? el( 'span', { class: 'flxm-badge is-muted', text: T.sys } ) : null ] ), tools ] ),
			meta,
			m.log_url ? el( 'a', { class: 'flxm-read__log', href: m.log_url, text: T.logView } ) : null,
			actions,
			aiBox,
			thread,
			atts,
			imgBar,
			frame
		] ) );
		frameWrite( frame, m.html, { blockRemote: remote } );
	}

	function startReply( id, mode ) {
		api( 'reply', { mail_id: id, mode: mode } ).then( openDraftData ).catch( function ( e ) { toast( e.message, 'error' ); } );
	}

	function loadCounts() {
		api( 'drafts', { status: 'review' }, 'GET' ).then( function ( r ) { setStatus( r.status ); } );
	}

	/* ---------------- taslak / yazma ---------------- */

	function newDraft( layoutId ) {
		var l = layoutId ? S.layouts.filter( function ( x ) { return x.id === layoutId; } )[ 0 ] : null;
		api( 'draft', { layout: layoutId || 'kurumsal', subject: l && l.subject ? l.subject : '', body: l && l.body ? l.body : '' } ).then( function ( d ) {
			if ( 'drafts' === S.folder ) {
				loadList();
			}
			loadCounts();
			openDraftData( d );
		} ).catch( function ( e ) { toast( e.message, 'error' ); } );
	}

	function openDraft( id ) {
		api( 'draft/' + id, null, 'GET' ).then( openDraftData ).catch( function ( e ) { toast( e.message, 'error' ); } );
	}

	function editor( html ) {
		var ed = el( 'div', { class: 'flxm-editor', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', 'aria-label': T.lBody } );
		ed.innerHTML = html || '<p><br></p>';
		var cmd = function ( c, v ) { return function () { ed.focus(); document.execCommand( c, false, v ); ed.dispatchEvent( new Event( 'input' ) ); }; };
		var bar = el( 'div', { class: 'flxm-toolbar', role: 'toolbar' }, [
			ibtn( 'bold', T.bold, cmd( 'bold' ) ),
			ibtn( 'italic', T.italic, cmd( 'italic' ) ),
			ibtn( 'list', T.list, cmd( 'insertUnorderedList' ) ),
			ibtn( 'link', T.link, async function () {
				var u = (await window.flxDialog.prompt({ title: T.linkUrl, value: 'https://' }));
				if ( u && /^(https?:|mailto:|tel:)/i.test( u ) ) {
					cmd( 'createLink', u )();
				}
			} ),
			ibtn( 'eraser', T.clear, cmd( 'removeFormat' ) )
		] );
		// Yapıştırılan biçimli metin sadeleşsin.
		ed.addEventListener( 'paste', function ( e ) {
			var txt = ( e.clipboardData || window.clipboardData ).getData( 'text/plain' );
			if ( txt ) {
				e.preventDefault();
				document.execCommand( 'insertText', false, txt );
			}
		} );
		return { bar: bar, ed: ed };
	}

	function layoutSelect( value ) {
		var s = el( 'select', { class: 'flxm-layout' } );
		S.layouts.forEach( function ( l ) {
			var o = el( 'option', { value: l.id, text: l.name + ( l.partner_name ? ' · ' + l.partner_name : '' ) } );
			if ( l.id === value ) {
				o.selected = true;
			}
			s.appendChild( o );
		} );
		return s;
	}

	function openDraftData( d ) {
		S.current = { type: 'draft', id: d.id };
		markActive();
		root.classList.add( 'is-reading' );
		pane.innerHTML = '';

		var to = el( 'input', { type: 'text', value: d.to || '', autocomplete: 'off', 'aria-label': T.to } );
		var cc = el( 'input', { type: 'text', value: d.cc || '', autocomplete: 'off', 'aria-label': T.cc } );
		var subj = el( 'input', { type: 'text', value: d.subject || '', 'aria-label': T.subject } );
		var lay = layoutSelect( d.layout );
		var E = editor( d.body );
		var stat = el( 'span', { class: 'flxm-savestate' } );

		function fields() {
			return { to: to.value, cc: cc.value, subject: subj.value, body: E.ed.innerHTML, layout: lay.value };
		}
		var save = debounce( function () {
			api( 'draft', Object.assign( { id: d.id }, fields() ) ).then( function () { S.dirty = false; stat.textContent = T.saved; } ).catch( function ( e ) { stat.textContent = e.message; } );
		}, 1200 );
		function changed() {
			S.dirty = true;
			stat.textContent = '…';
			save();
		}
		[ to, cc, subj ].forEach( function ( i ) { i.addEventListener( 'input', changed ); } );
		E.ed.addEventListener( 'input', changed );
		lay.addEventListener( 'change', function () {
			var l = S.layouts.filter( function ( x ) { return x.id === lay.value; } )[ 0 ];
			var empty = ! E.ed.textContent.trim();
			if ( l && l.body && empty ) {
				E.ed.innerHTML = l.body;
			}
			if ( l && l.subject && ! subj.value.trim() ) {
				subj.value = l.subject;
			}
			changed();
		} );

		var ins = el( 'textarea', { rows: '2', placeholder: d.reply_to_id ? T.aiHint : T.aiNewHint } );
		var aiBtn = btn( E.ed.textContent.trim() ? T.aiFix : T.aiWrite, function () {
			if ( ! S.status.ai ) {
				toast( T.aiOff, 'error' );
				return;
			}
			aiBtn.disabled = true;
			var old = aiBtn.lastChild.textContent;
			aiBtn.lastChild.textContent = T.aiWorking;
			api( 'ai', Object.assign( { draft_id: d.id, instruction: ins.value }, fields() ) ).then( function ( nd ) {
				E.ed.innerHTML = nd.body;
				if ( nd.subject && ! subj.value.trim() ) {
					subj.value = nd.subject;
				}
				ins.value = '';
				S.dirty = false;
				stat.textContent = T.saved;
			} ).catch( function ( e ) { toast( e.message, 'error' ); } ).then( function () { aiBtn.disabled = false; aiBtn.lastChild.textContent = T.aiFix; } );
		}, 'is-ai is-solid', 'sparkles' );

		var sendBtn = btn( T.send, async function () {
			if ( ! (await window.flxDialog.confirm({ message:  fmt( T.confirmSend, to.value )  })) ) {
				return;
			}
			sendBtn.disabled = true;
			sendBtn.lastChild.textContent = T.sending;
			api( 'draft/' + d.id + '/send', fields() ).then( function ( r ) {
				if ( r.status ) {
					setStatus( r.status );
				}
				if ( ! r.ok ) {
					throw new Error( r.error || T.error );
				}
				toast( T.sentOk, 'ok' );
				S.current = null;
				if ( 'inbox' === S.folder || 'sent' === S.folder ) {
					loadList();
				} else {
					setFolder( 'sent' );
				}
				if ( r.mail_id ) {
					openMail( r.mail_id );
				}
			} ).catch( function ( e ) {
				toast( e.message, 'error' );
				errBox.textContent = e.message;
				errBox.hidden = false;
			} ).then( function () { sendBtn.disabled = false; sendBtn.lastChild.textContent = T.send; } );
		}, 'is-primary', 'send' );

		var errBox = el( 'div', { class: 'flxm-callout is-error', hidden: ! d.error, text: d.error || '' } );
		var ccRow = el( 'label', { class: 'flxm-field', hidden: ! d.cc }, [ el( 'span', { text: T.cc } ), cc ] );

		// Canlı önizleme: şablonlu gerçek görünüm (Gönder ekranındaki gibi).
		var pv = livePreview( function () { return Object.assign( { reply_to_id: d.reply_to_id, mode: d.mode }, fields() ); } );
		[ to, subj ].forEach( function ( i ) { i.addEventListener( 'input', pv.later ); } );
		E.ed.addEventListener( 'input', pv.later );
		lay.addEventListener( 'change', pv.later );

		var aiRow = el( 'div', { class: 'flxm-aibox is-inline', hidden: true }, [ ins, aiBtn ] );
		var title = 'forward' === d.mode ? T.forward : ( d.reply_to ? T.reply : T.newMail );

		var form = el( 'div', { class: 'flxm-compose__form' }, [
			'ai' === d.origin && 'review' === d.status ? el( 'div', { class: 'flxm-callout is-ai' }, [ icon( 'sparkles' ), el( 'div', {}, [ T.aiDraft, d.note ? el( 'div', { class: 'flxm-muted', text: '“' + d.note + '”' } ) : null ] ) ] ) : null,
			errBox,
			d.reply_to ? el( 'p', { class: 'flxm-replyto' }, [ icon( 'reply' ), T.replyingTo + ': ', el( 'a', { href: '#', text: d.reply_to.subject || T.noSubject, onclick: function ( e ) { e.preventDefault(); openMail( d.reply_to.id ); } } ), ' · ', d.reply_to.from ] ) : null,
			el( 'div', { class: 'flxm-fields' }, [
				el( 'label', { class: 'flxm-field' }, [ el( 'span', { text: T.to } ), to, ccRow.hidden ? el( 'button', { type: 'button', class: 'flxm-link flxm-cc-toggle', text: 'Cc', onclick: function ( e ) { ccRow.hidden = false; e.currentTarget.remove(); cc.focus(); } } ) : null ] ),
				ccRow,
				el( 'label', { class: 'flxm-field' }, [ el( 'span', { text: T.subject } ), subj ] ),
				el( 'label', { class: 'flxm-field' }, [ el( 'span', { text: T.layout } ), lay ] )
			] ),
			el( 'div', { class: 'flxm-edwrap' }, [ E.bar, E.ed ] ),
			d.reply_to ? el( 'p', { class: 'flxm-muted flxm-small', text: T.quoteNote } ) : null,
			aiRow
		] );
		E.bar.appendChild( el( 'span', { class: 'flxm-actions__sp' } ) );
		E.bar.appendChild( btn( T.aiShort, function () { aiRow.hidden = ! aiRow.hidden; if ( ! aiRow.hidden ) { ins.focus(); } }, 'is-sm is-ai', 'sparkles' ) );

		var pvToggle = btn( T.preview, function () {
			var on = ! root.classList.contains( 'is-pv' );
			root.classList.toggle( 'is-pv', on );
			pvToggle.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			if ( on ) {
				pv.load();
			}
		}, 'flxm-pv-toggle', 'eye' );

		pane.appendChild( el( 'article', { class: 'flxm-compose' }, [
			el( 'header', { class: 'flxm-compose__head' }, [
				el( 'h2', { text: title } ),
				stat,
				el( 'span', { class: 'flxm-actions__sp' } ),
				ibtn( 'trash', T.delete, async function () {
					if ( ! (await window.flxDialog.confirm({ message:  T.confirmDel  })) ) {
						return;
					}
					api( 'draft/' + d.id + '/delete', {} ).then( function ( r ) {
						setStatus( r.status );
						S.dirty = false;
						closePane();
						if ( 'drafts' === S.folder || 'review' === S.folder ) {
							loadList();
						}
					} );
				}, 'is-danger' ),
				ibtn( 'x', T.close, closePane )
			] ),
			el( 'div', { class: 'flxm-compose__body' }, [ form, pv.box ] ),
			el( 'footer', { class: 'flxm-compose__foot' }, [ sendBtn, pvToggle ] )
		] ) );
		root.classList.add( 'is-composing' );
		pv.load();
		if ( ! to.value ) {
			to.focus();
		} else {
			E.ed.focus();
		}
	}

	/* ---------------- canlı önizleme ---------------- */

	function livePreview( collect ) {
		var frame = el( 'iframe', { class: 'flxm-pv__frame', title: T.preview, sandbox: 'allow-same-origin' } );
		var mode = 'desktop';
		var html = '';
		var seq = 0;
		var segBtns = [];
		var seg = el( 'div', { class: 'flxm-seg', role: 'group', 'aria-label': T.preview }, [ [ 'desktop', T.desktop ], [ 'phone', T.phone ], [ 'moon', T.dark ] ].map( function ( x ) {
			var b = el( 'button', { type: 'button', title: x[ 1 ], 'aria-label': x[ 1 ], 'aria-pressed': 'desktop' === x[ 0 ] ? 'true' : 'false', onclick: function () {
				mode = 'desktop' === x[ 0 ] ? 'desktop' : ( 'phone' === x[ 0 ] ? 'mobile' : 'dark' );
				segBtns.forEach( function ( o ) { o.setAttribute( 'aria-pressed', o === b ? 'true' : 'false' ); } );
				paint();
			} }, [ icon( x[ 0 ] ) ] );
			segBtns.push( b );
			return b;
		} ) );
		var stage = el( 'div', { class: 'flxm-pv__stage' }, [ frame ] );
		var box = el( 'aside', { class: 'flxm-pv', 'aria-label': T.preview }, [
			el( 'div', { class: 'flxm-pv__bar' }, [ el( 'strong', { text: T.previewTitle } ), seg ] ),
			stage
		] );
		function paint() {
			stage.classList.toggle( 'is-mobile', 'mobile' === mode );
			stage.classList.toggle( 'is-dark', 'dark' === mode );
			frameWrite( frame, html, { dark: 'dark' === mode, fit: false } );
		}
		function load() {
			// Görünmüyorsa istek atma (Hostinger istek sınırı).
			if ( ! box.offsetParent ) {
				return;
			}
			var my = ++seq;
			box.classList.add( 'is-loading' );
			api( 'preview', collect() ).then( function ( r ) {
				if ( my === seq ) {
					html = r.html || '';
					paint();
				}
			} ).catch( function () {} ).then( function () { box.classList.remove( 'is-loading' ); } );
		}
		return { box: box, load: load, later: debounce( load, 900 ) };
	}

	/* ---------------- şablonlar ---------------- */

	function showLayoutsIntro() {
		S.current = null;
		pane.innerHTML = '';
		pane.appendChild( el( 'div', { class: 'flxm-layout-intro' }, [
			el( 'p', { text: T.layoutsIntro } ),
			btn( T.newLayout, function () { openLayout( '' ); }, 'is-primary', 'plus' )
		] ) );
	}

	function openLayout( id ) {
		var l = S.layouts.filter( function ( x ) { return x.id === id; } )[ 0 ] || { id: '', name: '', desc: '', base: 'kurumsal', partner_name: '', partner_logo: '', partner_url: '', accent: '#F59E0B', heading: '', subject: '', body: '', builtin: false };
		S.current = { type: 'layout', id: id };
		markActive();
		root.classList.add( 'is-reading' );
		pane.innerHTML = '';
		var frame = el( 'iframe', { class: 'flxm-lpreview', title: T.preview, sandbox: 'allow-same-origin' } );
		var refresh = debounce( function () {
			api( 'preview', { layout_data: data(), subject: subj.value, body: E.ed.innerHTML } ).then( function ( r ) { frameWrite( frame, r.html ); } );
		}, 500 );
		if ( l.builtin ) {
			pane.appendChild( el( 'div', { class: 'flxm-lform' }, [
				backBtn(),
				el( 'h2', { text: l.name } ),
				el( 'p', { class: 'flxm-muted', text: l.desc + ' · ' + T.builtin } ),
				btn( T.useLayout, function () { newDraft( l.id ); }, 'is-primary', 'pen' ),
				frame
			] ) );
			api( 'preview', { layout: l.id, subject: 'Örnek', body: '<p>Merhaba,</p><p>Bu bir örnek e-postadır.</p>' } ).then( function ( r ) { frameWrite( frame, r.html ); } );
			return;
		}
		var inp = function ( v, type ) { var i = el( 'input', { type: type || 'text', value: v || '' } ); i.addEventListener( 'input', refresh ); return i; };
		var name = inp( l.name );
		var desc = inp( l.desc );
		var base = el( 'select', {}, [ el( 'option', { value: 'kurumsal', text: T.lBaseCard } ), el( 'option', { value: 'sade', text: T.lBaseLetter } ) ] );
		base.value = l.base || 'kurumsal';
		base.addEventListener( 'change', refresh );
		var pname = inp( l.partner_name );
		var plogo = inp( l.partner_logo, 'url' );
		var purl = inp( l.partner_url, 'url' );
		var accent = inp( l.accent || '#F59E0B', 'color' );
		var heading = inp( l.heading );
		var subj = inp( l.subject );
		var E = editor( l.body );
		E.ed.addEventListener( 'input', refresh );
		var logoImg = el( 'img', { class: 'flxm-logo-prev', alt: '', src: l.partner_logo || '', hidden: ! l.partner_logo } );
		plogo.addEventListener( 'input', function () {
			var u = flxmSafeUrl( plogo.value );
			logoImg.src = u;
			logoImg.hidden = ! u;
		} );
		function data() {
			return { name: name.value, desc: desc.value, base: base.value, partner_name: pname.value, partner_logo: plogo.value, partner_url: purl.value, accent: accent.value, heading: heading.value, subject: subj.value, body: E.ed.innerHTML };
		}
		var pick = btn( T.lLogoPick, function () {
			if ( ! window.wp || ! wp.media ) {
				return;
			}
			var fr = wp.media( { title: T.lLogoPick, library: { type: 'image' }, multiple: false } );
			fr.on( 'select', function () {
				var a = fr.state().get( 'selection' ).first().toJSON();
				plogo.value = a.url;
				logoImg.src = a.url;
				logoImg.hidden = false;
				refresh();
			} );
			fr.open();
		}, 'is-sm', 'image' );
		var f = function ( label, input, extra ) { return el( 'label', { class: 'flxm-field' }, [ el( 'span', { text: label } ), input, extra || null ] ); };
		pane.appendChild( el( 'div', { class: 'flxm-lform' }, [
			backBtn(),
			el( 'div', { class: 'flxm-lgrid' }, [
				el( 'div', {}, [
					f( T.lName, name ),
					f( T.lDesc, desc ),
					f( T.lBase, base ),
					f( T.lPartner, pname ),
					el( 'div', { class: 'flxm-field' }, [ el( 'span', { text: T.lLogo } ), el( 'div', { class: 'flxm-logo-row' }, [ logoImg, plogo, pick ] ) ] ),
					f( T.lUrl, purl ),
					f( T.lAccent, accent ),
					f( T.lHeading, heading ),
					f( T.lSubject, subj ),
					el( 'div', { class: 'flxm-field' }, [ el( 'span', { text: T.lBody } ), el( 'div', { class: 'flxm-edwrap' }, [ E.bar, E.ed ] ) ] ),
					el( 'div', { class: 'flxm-compose__foot' }, [
						btn( T.save, function () {
							api( 'layout', Object.assign( { id: l.id }, data() ) ).then( function ( r ) {
								S.layouts = r.layouts;
								toast( T.saved, 'ok' );
								loadList();
								openLayout( r.id );
							} ).catch( function ( e ) { toast( e.message, 'error' ); } );
						}, 'is-primary', 'ok' ),
						l.id ? btn( T.useLayout, function () { newDraft( l.id ); }, '', 'pen' ) : null,
						el( 'span', { class: 'flxm-actions__sp' } ),
						l.id ? el( 'button', { type: 'button', class: 'flxm-link flxm-del', onclick: async function () {
							if ( ! (await window.flxDialog.confirm({ message:  T.confirmDel  })) ) {
								return;
							}
							api( 'layout/' + l.id + '/delete', {} ).then( function ( r ) { S.layouts = r.layouts; loadList(); showLayoutsIntro(); } );
						} }, [ icon( 'trash' ), T.delete ] ) : null
					] )
				] ),
				el( 'div', { class: 'flxm-lprev' }, [ frame ] )
			] )
		] ) );
		refresh();
	}

	/* ---------------- eşitleme ---------------- */

	var rounds = 0;
	function sync( manual ) {
		if ( ! S.status.enabled || S.syncing ) {
			return;
		}
		S.syncing = true;
		setStatus( S.status );
		api( 'sync', {} ).then( function ( r ) {
			S.syncing = false;
			setStatus( r.status );
			if ( r.error && manual ) {
				toast( r.error, 'error' );
			}
			if ( r['new'] && ( 'inbox' === S.folder || 'sent' === S.folder ) ) {
				loadList();
			}
			// İlk kurulumda eski e-postalar parça parça gelir.
			if ( r.more && ! r.error && rounds++ < 30 ) {
				setTimeout( sync, 2500 );
			}
		} ).catch( function ( e ) {
			S.syncing = false;
			setStatus( S.status );
			if ( manual ) {
				toast( e.message, 'error' );
			}
		} );
	}
	setInterval( function () {
		if ( ! document.hidden ) {
			rounds = 0;
			sync();
		}
	}, 120000 );

	/* ---------------- başlangıç ---------------- */

	window.addEventListener( 'beforeunload', function ( e ) {
		if ( S.dirty ) {
			e.preventDefault();
		}
	} );
	setStatus( S.status );
	var o = C.open || {};
	if ( o.draft ) {
		// Dışarıdan açılan taslak (ör. Flux Tasks belgesinden): kendi klasöründe açılır.
		api( 'draft/' + o.draft, null, 'GET' ).then( function ( d ) {
			setFolder( 'review' === d.status ? 'review' : 'drafts' );
			openDraftData( d );
		} ).catch( function ( e ) { setFolder( 'inbox' ); toast( e.message, 'error' ); } );
	} else {
		setFolder( 'layouts' === o.view ? 'layouts' : 'inbox' );
		if ( o.mail ) {
			openMail( o.mail );
		}
	}
	sync();
}() );
