/* Flux Wiki – ön yüz: canlı arama (Ctrl/⌘+K), Wiki'ye sor penceresi, kopyalama, içindekiler takibi, mobil menü. */
( function () {
	'use strict';
	var W = window.flxWiki || {};
	var T = W.i18n || {};
	var root = document.getElementById( 'flxwk' );
	if ( ! root ) {
		return;
	}
	var $ = function ( s, el ) { return ( el || document ).querySelector( s ); };
	var $$ = function ( s, el ) { return Array.prototype.slice.call( ( el || document ).querySelectorAll( s ) ); };
	var esc = function ( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	};
	var ICON_COPY = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>';
	var ICON_OK = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';

	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( function ( ok, fail ) {
			var ta = document.createElement( 'textarea' );
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild( ta );
			ta.select();
			try {
				document.execCommand( 'copy' ) ? ok() : fail();
			} catch ( e ) {
				fail( e );
			}
			ta.remove();
		} );
	}

	/* Temanın yapışkan başlığı varsa yan menüler ve başlık bağlantıları altında kalmasın. */
	function stickyTop() {
		var top = 0;
		$$( 'header, .site-header' ).forEach( function ( h ) {
			var cs = window.getComputedStyle( h );
			if ( ( 'sticky' === cs.position || 'fixed' === cs.position ) && h.getBoundingClientRect().top <= 40 ) {
				top = Math.max( top, h.getBoundingClientRect().bottom );
			}
		} );
		var bar = document.getElementById( 'wpadminbar' );
		if ( bar && top ) {
			top -= bar.offsetHeight;
		}
		root.style.setProperty( '--flxwk-top', Math.max( 24, Math.round( top ) + 20 ) + 'px' );
	}
	stickyTop();
	window.addEventListener( 'resize', stickyTop );

	/* Kod blokları: kopyala düğmesi. */
	$$( '.flxwk-content pre' ).forEach( function ( pre ) {
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = 'flxwk-copy';
		b.setAttribute( 'aria-label', T.copy || 'Copy' );
		b.innerHTML = ICON_COPY;
		b.addEventListener( 'click', function () {
			var code = pre.querySelector( 'code' );
			copyText( ( code || pre ).innerText ).then( function () {
				b.innerHTML = ICON_OK;
				b.classList.add( 'is-done' );
				setTimeout( function () {
					b.innerHTML = ICON_COPY;
					b.classList.remove( 'is-done' );
				}, 1600 );
			} );
		} );
		pre.appendChild( b );
	} );

	/* Markdown'u kopyala. */
	$$( '[data-flxwk-copy-md]' ).forEach( function ( btn ) {
		var label = btn.querySelector( 'span' );
		var orig = label ? label.textContent : '';
		btn.addEventListener( 'click', function () {
			fetch( btn.getAttribute( 'data-flxwk-copy-md' ), { credentials: 'same-origin' } )
				.then( function ( r ) { return r.text(); } )
				.then( copyText )
				.then( function () {
					btn.classList.add( 'is-done' );
					if ( label ) {
						label.textContent = T.copied || 'Copied';
					}
				} )
				.catch( function () {
					if ( label ) {
						label.textContent = T.copyFail || 'Failed';
					}
				} )
				.then( function () {
					setTimeout( function () {
						var menu = btn.closest( '.flxwk-menu' );
						if ( menu ) {
							menu.removeAttribute( 'open' );
						}
						btn.classList.remove( 'is-done' );
						if ( label ) {
							label.textContent = orig;
						}
					}, 1800 );
				} );
		} );
	} );

	/* Açılır menüler: biri açılınca diğeri kapanır; bağlantıya, dışarı ya da Esc ile kapanır. */
	$$( '.flxwk-menu' ).forEach( function ( d ) {
		d.addEventListener( 'toggle', function () {
			if ( d.open ) {
				$$( '.flxwk-menu[open]' ).forEach( function ( o ) {
					if ( o !== d ) {
						o.removeAttribute( 'open' );
					}
				} );
			}
		} );
		Array.prototype.forEach.call( d.querySelectorAll( '.flxwk-menu__pop a, .flxwk-menu__pop [data-flxwk-ask]' ), function ( a ) {
			a.addEventListener( 'click', function () {
				d.removeAttribute( 'open' );
			} );
		} );
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			$$( '.flxwk-menu[open]' ).forEach( function ( d ) {
				d.removeAttribute( 'open' );
			} );
		}
	} );
	document.addEventListener( 'click', function ( e ) {
		$$( '.flxwk-menu[open]' ).forEach( function ( d ) {
			if ( ! d.contains( e.target ) ) {
				d.removeAttribute( 'open' );
			}
		} );
	} );

	/* Mobil sayfa menüsü. */
	var navBtn = $( '[data-flxwk-nav]' );
	var nav = $( '#flxwk-nav' );
	if ( navBtn && nav ) {
		navBtn.addEventListener( 'click', function () {
			var on = nav.classList.toggle( 'is-open' );
			navBtn.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
		} );
	}
	/* Geçerli sayfa menüde görünür olsun. */
	/* Arama kutusu sabit kalır; yalnız sayfa ağacı kayar. */
	var tree = $( '.flxwk-tree' );
	var cur = $( '.flxwk-tree a[aria-current]' );
	if ( cur && tree && tree.scrollHeight > tree.clientHeight ) {
		tree.scrollTop = Math.max( 0, cur.getBoundingClientRect().top - tree.getBoundingClientRect().top - tree.clientHeight / 3 );
	}

	/* İçindekiler: görünen bölüm vurgulanır. */
	var links = $$( '.flxwk-toc .flxwk-toc__list a' );
	if ( links.length && 'IntersectionObserver' in window ) {
		var map = {};
		links.forEach( function ( a ) {
			map[ decodeURIComponent( a.getAttribute( 'href' ).slice( 1 ) ) ] = a;
		} );
		var heads = $$( '.flxwk-content h2[id], .flxwk-content h3[id]' );
		var active = null;
		var io = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( en ) {
				if ( en.isIntersecting && map[ en.target.id ] ) {
					if ( active ) {
						active.classList.remove( 'is-active' );
					}
					active = map[ en.target.id ];
					active.classList.add( 'is-active' );
				}
			} );
		}, { rootMargin: '0px 0px -70% 0px' } );
		heads.forEach( function ( h ) { io.observe( h ); } );
	}

	/* ------------------------------------------------------------ */
	/* Canlı arama: yazdıkça (debounce) sonuçlar kutunun hemen altında  */
	/* açılır, sayfa aşağı uzar; pencere açılmaz. Arama sayfasında     */
	/* büyük kutu sayfadaki sonuç listesini yerinde günceller.         */
	/* ------------------------------------------------------------ */
	var fmt = function ( s, a, b ) {
		return String( s || '' ).replace( '%1$s', a ).replace( '%2$s', b ).replace( '%s', a ).replace( '%d', a );
	};
	var searchUrl = function ( q ) {
		return W.home + ( W.home.indexOf( '?' ) > -1 ? '&' : '?' ) + 'q=' + encodeURIComponent( q );
	};
	function hitHtml( r ) {
		return '<a class="flxwk-hit" role="option" href="' + esc( r.url ) + '"><span class="flxwk-hit__t">' + esc( r.title ) +
			( r.heading ? ' <small>› ' + esc( r.heading ) + '</small>' : '' ) +
			( r.section ? ' <span class="flxwk-chip">' + esc( r.section ) + '</span>' : '' ) +
			'</span><span class="flxwk-hit__s">' + r.snippet + '</span></a>';
	}
	function pageHtml( q, d ) {
		var list = ( d && d.results ) || [];
		var head = d && d.fixed ? fmt( T.fixed, q, d.fixed ) : fmt( T.countFor, ( d && d.total ) || 0, q );
		var body = list.length ? '<ol class="flxwk-results">' + list.map( function ( r ) {
			return '<li><a class="flxwk-results__t" href="' + esc( r.url ) + '">' + esc( r.title ) + ( r.heading ? ' <span>› ' + esc( r.heading ) + '</span>' : '' ) + '</a>' +
				( r.section ? '<span class="flxwk-chip">' + esc( r.section ) + '</span>' : '' ) + '<p>' + r.snippet + '</p></li>';
		} ).join( '' ) + '</ol>' : '<p class="flxwk-empty">' + esc( T.noResults || '' ) + '</p>';
		return '<p class="flxwk-muted">' + esc( head ) + '</p>' + body;
	}
	var pageRes = $( '[data-flxwk-page-results]' );
	$$( '[data-flxwk-live]' ).forEach( function ( box ) {
		var inp = $( '[data-flxwk-q]', box );
		var drop = $( '[data-flxwk-drop]', box );
		var frm = $( 'form', box );
		if ( ! inp || ! drop || ! frm ) {
			return;
		}
		var inPage = !! ( pageRes && box.classList.contains( 'is-big' ) );
		var t = null;
		var n = 0;
		var at = -1;
		var last = inPage ? inp.value.trim() : '';
		function shut() {
			drop.hidden = true;
			drop.innerHTML = '';
			inp.setAttribute( 'aria-expanded', 'false' );
			at = -1;
			last = '';
		}
		function pick( i ) {
			var h = $$( '.flxwk-hit', drop );
			if ( ! h.length ) {
				at = -1;
				return;
			}
			at = ( i + h.length ) % h.length;
			h.forEach( function ( el, k ) {
				el.classList.toggle( 'is-sel', k === at );
			} );
			h[ at ].scrollIntoView( { block: 'nearest' } );
		}
		function go() {
			var q = inp.value.trim();
			clearTimeout( t );
			if ( q === last ) {
				return;
			}
			if ( ! q ) {
				if ( ! inPage ) {
					shut();
				}
				return;
			}
			t = setTimeout( function () {
				var my = ++n;
				last = q;
				box.classList.add( 'is-busy' );
				fetch( W.rest + 'search?lang=' + encodeURIComponent( W.lang || '' ) + '&limit=' + ( inPage ? 50 : 8 ) + '&q=' + encodeURIComponent( q ) )
					.then( function ( r ) { return r.json(); } )
					.then( function ( d ) {
						if ( my !== n ) {
							return;
						}
						box.classList.remove( 'is-busy' );
						if ( inPage ) {
							pageRes.innerHTML = pageHtml( q, d );
							try {
								history.replaceState( null, '', searchUrl( q ) );
							} catch ( e ) {}
							return;
						}
						var list = ( d && d.results ) || [];
						var top = d && d.fixed ? '<p class="flxwk-drop__note">' + esc( fmt( T.fixed, q, d.fixed ) ) + '</p>' : '';
						if ( ! list.length ) {
							drop.innerHTML = '<p class="flxwk-drop__note">' + esc( T.noResults || '' ) + '</p>' +
								( W.canAsk ? '<button type="button" class="flxwk-link flxwk-drop__ask" data-flxwk-ask-q>' + esc( T.askAi || '' ) + ' →</button>' : '' );
						} else {
							drop.innerHTML = top + '<div role="listbox">' + list.map( hitHtml ).join( '' ) + '</div>' +
								( d.total > list.length ? '<a class="flxwk-drop__all" href="' + esc( searchUrl( q ) ) + '">' + esc( fmt( T.seeAll, d.total ) ) + ' →</a>' : '' );
						}
						drop.hidden = false;
						inp.setAttribute( 'aria-expanded', 'true' );
						at = -1;
					} )
					.catch( function () {
						box.classList.remove( 'is-busy' );
					} );
			}, 220 );
		}
		inp.addEventListener( 'input', go );
		inp.addEventListener( 'focus', go );
		inp.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowDown' === e.key && ! drop.hidden ) {
				e.preventDefault();
				pick( at + 1 );
			} else if ( 'ArrowUp' === e.key && ! drop.hidden ) {
				e.preventDefault();
				pick( at - 1 );
			} else if ( 'Escape' === e.key ) {
				if ( ! drop.hidden ) {
					shut();
				} else {
					inp.blur();
				}
			}
		} );
		frm.addEventListener( 'submit', function ( e ) {
			var h = $$( '.flxwk-hit', drop );
			if ( at >= 0 && h[ at ] ) {
				e.preventDefault();
				window.location.href = h[ at ].href;
			} else if ( inPage ) {
				e.preventDefault();
				last = '';
				go();
			}
		} );
		drop.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-flxwk-ask-q]' ) && window.flxwkOpenAsk ) {
				window.flxwkOpenAsk( inp.value.trim() );
			}
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( ! box.contains( e.target ) && ! drop.hidden ) {
				shut();
			}
		} );
	} );
	/* Ctrl/⌘+K ve "/" sayfadaki arama kutusuna odaklanır (yan menü görünürse o, yoksa büyük kutu). */
	document.addEventListener( 'keydown', function ( e ) {
		var tag = ( e.target && e.target.tagName ) || '';
		var typing = /INPUT|TEXTAREA|SELECT/.test( tag ) || ( e.target && e.target.isContentEditable );
		if ( ( ( e.metaKey || e.ctrlKey ) && 'k' === e.key.toLowerCase() ) || ( '/' === e.key && ! typing ) ) {
			var side = $( '#flxwk-nav [data-flxwk-q]' );
			var target = ( side && side.offsetParent ? side : null ) || $( '.flxwk-sbox.is-big [data-flxwk-q]' ) || side;
			if ( target ) {
				e.preventDefault();
				var nv = $( '#flxwk-nav' );
				if ( target === side && nv && ! side.offsetParent && navBtn ) {
					nav.classList.add( 'is-open' );
					navBtn.setAttribute( 'aria-expanded', 'true' );
				}
				target.focus();
				target.select();
			}
		}
	} );

	/* ------------------------------------------------------------ */
	/* Wiki'ye sor penceresi                                         */
	/* ------------------------------------------------------------ */
	var modal = $( '[data-flxwk-modal]' );
	if ( ! modal ) {
		return;
	}
	var input = $( '[data-flxwk-input]', modal );
	var out = $( '[data-flxwk-out]', modal );
	var form = $( '[data-flxwk-form]', modal );
	var mode = 'search';
	var sel = -1;
	var timer = null;
	var seq = 0;
	var lastFocus = null;

	function hint( text ) {
		out.innerHTML = '<p class="flxwk-hint">' + esc( text ) + '</p>';
	}

	function setMode( m ) {
		mode = m;
		$$( '[data-flxwk-tab]', modal ).forEach( function ( b ) {
			var on = b.getAttribute( 'data-flxwk-tab' ) === m;
			b.classList.toggle( 'is-on', on );
			b.setAttribute( 'aria-selected', on ? 'true' : 'false' );
		} );
		input.placeholder = 'ask' === m ? ( T.askHint || '' ) : ( T.typeToFind || '' );
		if ( 'ask' === m ) {
			hint( T.askHint || '' );
		} else {
			run();
		}
		input.focus();
	}

	function open( m, value ) {
		lastFocus = document.activeElement;
		modal.hidden = false;
		document.documentElement.classList.add( 'flxwk-lock' );
		if ( typeof value === 'string' ) {
			input.value = value;
		}
		setMode( m || 'search' );
		input.focus();
		var len = input.value.length;
		try {
			input.setSelectionRange( len, len );
		} catch ( e ) {}
	}

	function close() {
		modal.hidden = true;
		document.documentElement.classList.remove( 'flxwk-lock' );
		if ( lastFocus && lastFocus.blur && lastFocus.closest && lastFocus.closest( '[data-flxwk-open]' ) ) {
			lastFocus.blur();
		} else if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	function hits() {
		return $$( '.flxwk-hit', out );
	}

	function mark( i ) {
		var h = hits();
		if ( ! h.length ) {
			sel = -1;
			return;
		}
		sel = ( i + h.length ) % h.length;
		h.forEach( function ( el, k ) {
			el.classList.toggle( 'is-sel', k === sel );
			el.setAttribute( 'aria-selected', k === sel ? 'true' : 'false' );
		} );
		h[ sel ].scrollIntoView( { block: 'nearest' } );
	}

	function run() {
		if ( 'search' !== mode ) {
			return;
		}
		var q = input.value.trim();
		clearTimeout( timer );
		if ( ! q ) {
			hint( T.typeToFind || '' );
			return;
		}
		timer = setTimeout( function () {
			var my = ++seq;
			fetch( W.rest + 'search?lang=' + encodeURIComponent( W.lang || '' ) + '&limit=10&q=' + encodeURIComponent( q ) )
				.then( function ( r ) { return r.json(); } )
				.then( function ( d ) {
					if ( my !== seq ) {
						return;
					}
					var list = ( d && d.results ) || [];
					if ( ! list.length ) {
						var more = W.canAsk ? ' <button type="button" class="flxwk-link" data-flxwk-goask>' + esc( T.askAi || '' ) + ' →</button>' : '';
						out.innerHTML = '<p class="flxwk-hint">' + esc( T.noResults || '' ) + more + '</p>';
						sel = -1;
						return;
					}
					out.innerHTML = '<div role="listbox">' + list.map( function ( r ) {
						return '<a class="flxwk-hit" role="option" href="' + esc( r.url ) + '"><span class="flxwk-hit__t">' + esc( r.title ) +
							( r.heading ? ' <small>› ' + esc( r.heading ) + '</small>' : '' ) +
							( r.section ? ' <span class="flxwk-chip">' + esc( r.section ) + '</span>' : '' ) +
							'</span><span class="flxwk-hit__s">' + r.snippet + '</span></a>';
					} ).join( '' ) + '</div>';
					mark( 0 );
				} )
				.catch( function () {
					hint( T.noResults || '' );
				} );
		}, 140 );
	}

	function ask() {
		var q = input.value.trim();
		if ( q.length < 3 ) {
			return;
		}
		out.innerHTML = '<p class="flxwk-hint"><span class="flxwk-dots"><i></i><i></i><i></i></span>' + esc( T.thinking || '' ) + '</p>';
		var headers = { 'Content-Type': 'application/json' };
		if ( W.nonce ) {
			headers[ 'X-WP-Nonce' ] = W.nonce;
		}
		fetch( W.rest + 'ask?lang=' + encodeURIComponent( W.lang || '' ), { method: 'POST', credentials: 'same-origin', headers: headers, body: JSON.stringify( { q: q, page: W.page || 0 } ) } )
			.then( function ( r ) {
				return r.json().then( function ( d ) { return { ok: r.ok, d: d }; } );
			} )
			.then( function ( res ) {
				if ( ! res.ok ) {
					hint( ( res.d && res.d.message ) || T.askFail || '' );
					return;
				}
				var src = ( res.d.sources || [] ).map( function ( s ) {
					return '<a href="' + esc( s.url ) + '">' + esc( s.title ) + '</a>';
				} ).join( '' );
				out.innerHTML = '<div class="flxwk-answer"><div class="flxwk-content">' + res.d.html + '</div>' +
					( src ? '<div class="flxwk-answer__src"><strong>' + esc( T.sources || '' ) + ':</strong> ' + src + '</div>' : '' ) + '</div>';
			} )
			.catch( function () {
				hint( T.askFail || '' );
			} );
	}

	input.addEventListener( 'input', run );
	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		if ( 'ask' === mode ) {
			ask();
			return;
		}
		var h = hits();
		if ( sel >= 0 && h[ sel ] ) {
			window.location.href = h[ sel ].href;
		} else if ( input.value.trim() ) {
			window.location.href = W.home + ( W.home.indexOf( '?' ) > -1 ? '&' : '?' ) + 'q=' + encodeURIComponent( input.value.trim() );
		}
	} );
	input.addEventListener( 'keydown', function ( e ) {
		if ( 'search' !== mode ) {
			return;
		}
		if ( 'ArrowDown' === e.key ) {
			e.preventDefault();
			mark( sel + 1 );
		} else if ( 'ArrowUp' === e.key ) {
			e.preventDefault();
			mark( sel - 1 );
		}
	} );
	out.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '[data-flxwk-goask]' ) ) {
			setMode( 'ask' );
		}
	} );
	$$( '[data-flxwk-tab]', modal ).forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			setMode( b.getAttribute( 'data-flxwk-tab' ) );
		} );
	} );
	$$( '[data-flxwk-close]', modal ).forEach( function ( b ) {
		b.addEventListener( 'click', close );
	} );

	$$( '[data-flxwk-ask]' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			open( 'ask', '' );
		} );
	} );
	window.flxwkOpenAsk = function ( q ) {
		open( 'ask', q || '' );
	};

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && ! modal.hidden ) {
			close();
		}
	} );
}() );
