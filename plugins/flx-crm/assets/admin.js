/* Flux CRM yönetim ekranları: eşitleme, skorlama, toplu işlem, onay, segment kural düzenleyici. */
window.flxcrmBoot = window.flxcrmBoot || [];
/* Her blok bir kök (document ya da fetch ile yenilenen bölüm) alır; aynı öğe iki kez bağlanmaz. */
function flxcrmOnce( root, sel ) {
	var list = Array.prototype.slice.call( root.querySelectorAll( sel ) );
	if ( root.matches && root.matches( sel ) ) {
		list.unshift( root );
	}
	return list.filter( function ( n ) {
		if ( n.flxcrmBound ) {
			return false;
		}
		n.flxcrmBound = true;
		return true;
	} );
}

window.flxcrmBoot.push( function ( R ) {
	'use strict';

	var cfg = window.flxcrmAdmin || {};
	var t = cfg.i18n || {};

	function fmt( s ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( s || '' ).replace( /%(\d+)\$s|%s/g, function ( m, n ) {
			var v = n ? args[ n - 1 ] : args[ i++ ];
			return v === undefined ? '' : v;
		} );
	}

	function num( n ) {
		try {
			return Number( n ).toLocaleString( document.documentElement.lang || undefined );
		} catch ( e ) {
			return String( n );
		}
	}

	function ajax( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( '_ajax_nonce', cfg.nonce );
		Object.keys( data || {} ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		return fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( j ) {
				if ( ! j || ! j.success ) {
					throw new Error( 'fail' );
				}
				return j.data;
			} );
	}

	function el( tag, attrs, kids ) {
		var n = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) {
			if ( 'text' === k ) {
				n.textContent = attrs[ k ];
			} else if ( 'html' === k ) {
				n.innerHTML = attrs[ k ];
			} else {
				n.setAttribute( k, attrs[ k ] );
			}
		} );
		( kids || [] ).forEach( function ( c ) {
			if ( c ) {
				n.appendChild( c );
			}
		} );
		return n;
	}

	/* ------------------------------------------------------------ */
	/* Eşitleme ve skorlama                                          */
	/* ------------------------------------------------------------ */

	function progressFor( btn ) {
		var box = btn.closest( 'section, .flxcrm-card, .flxcrm-callout' ) || document;
		return box.querySelector( '[data-flxcrm-progress]' ) || document.querySelector( '[data-flxcrm-progress]' );
	}

	function lockAll( on ) {
		document.querySelectorAll( '[data-flxcrm-sync],[data-flxcrm-score]' ).forEach( function ( b ) {
			b.disabled = on;
		} );
	}

	function say( p, text, bad ) {
		if ( ! p ) {
			return;
		}
		p.hidden = false;
		p.textContent = text;
		p.classList.toggle( 'is-bad', !! bad );
	}

	flxcrmOnce( R, '[data-flxcrm-sync]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var p = progressFor( btn );
			lockAll( true );
			say( p, t.counting || '…' );
			( function run( step, page ) {
				ajax( 'flxcrm_sync', { step: step, page: page } )
					.then( function ( r ) {
						say( p, fmt( t.working, r.label, num( r.scanned ), num( r.total ) ) );
						if ( r.finished ) {
							say( p, t.done );
							window.setTimeout( function () {
								flxcrmReload();
							}, 700 );
							return;
						}
						run( r.next, r.page );
					} )
					.catch( function () {
						say( p, t.fail, true );
						lockAll( false );
					} );
			} )( '', 1 );
		} );
	} );

	flxcrmOnce( R, '[data-flxcrm-score]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var p = progressFor( btn );
			lockAll( true );
			say( p, t.scoring );
			var left = 40;
			( function run() {
				ajax( 'flxcrm_score', {} )
					.then( function ( r ) {
						if ( r && r.done === false && left > 0 ) {
							left -= 1;
							run();
							return;
						}
						say( p, fmt( t.scored, num( r.scored || 0 ) ) );
						window.setTimeout( function () {
							flxcrmReload();
						}, 700 );
					} )
					.catch( function () {
						say( p, t.fail, true );
						lockAll( false );
					} );
			} )();
		} );
	} );

	/* ------------------------------------------------------------ */
	/* Onay                                                          */
	/* ------------------------------------------------------------ */

	/* Onay pencereleri: flx-brand flxDialog (data-flx-confirm), tarayıcının confirm'ü değil. */

	/* ------------------------------------------------------------ */
	/* Toplu işlem                                                   */
	/* ------------------------------------------------------------ */

	flxcrmOnce( R, '[data-flxcrm-bulk]' ).forEach( function ( form ) {
		var bar = form.querySelector( '[data-flxcrm-bulkbar]' );
		var all = form.querySelector( '[data-flxcrm-checkall]' );
		var count = form.querySelector( '[data-flxcrm-selcount]' );
		var action = form.querySelector( '[data-flxcrm-bulkaction]' );
		if ( ! bar ) {
			return;
		}
		function boxes() {
			return Array.prototype.slice.call( form.querySelectorAll( 'input[name="ids[]"]' ) );
		}
		function refresh() {
			var b = boxes();
			var n = b.filter( function ( x ) {
				return x.checked;
			} ).length;
			bar.hidden = 0 === n;
			if ( count ) {
				count.textContent = fmt( t.selected, num( n ) );
			}
			if ( all ) {
				all.checked = n > 0 && n === b.length;
				all.indeterminate = n > 0 && n < b.length;
			}
		}
		function swap() {
			var a = action ? action.value : '';
			form.querySelectorAll( '[data-flxcrm-bulkvalue]' ).forEach( function ( v ) {
				var on = v.getAttribute( 'data-flxcrm-bulkvalue' ) === a;
				v.hidden = ! on;
				v.disabled = ! on;
			} );
		}
		form.addEventListener( 'change', function ( e ) {
			if ( e.target === all ) {
				boxes().forEach( function ( x ) {
					x.checked = all.checked;
				} );
			}
			if ( e.target === action ) {
				swap();
			}
			refresh();
		} );
		form.addEventListener( 'submit', function ( e ) {
			if ( ! action || 'delete' !== action.value || form.flxcrmOk || ! window.flxDialog ) {
				form.flxcrmOk = false;
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();
			var sub = e.submitter;
			window.flxDialog.confirm( { title: t.bulkDelete, message: t.confirm, ok: t.del, danger: true } ).then( function ( yes ) {
				if ( yes ) {
					form.flxcrmOk = true;
					form.requestSubmit( sub && sub.form === form ? sub : undefined );
				}
			} );
		} );
		swap();
		refresh();
	} );

	/* ------------------------------------------------------------ */
	/* Segment kural düzenleyici                                     */
	/* ------------------------------------------------------------ */

	flxcrmOnce( R, '[data-flxcrm-segedit]' ).forEach( function ( form ) {
		var schemaEl = form.querySelector( '[data-flxcrm-schema]' );
		var hidden = form.querySelector( '[data-flxcrm-rules]' );
		var list = form.querySelector( '[data-flxcrm-rulelist]' );
		var match = form.querySelector( '[data-flxcrm-match]' );
		var add = form.querySelector( '[data-flxcrm-addrule]' );
		var countEl = form.querySelector( '[data-flxcrm-count]' );
		var sumEl = form.querySelector( '[data-flxcrm-summary]' );
		var sampleEl = form.querySelector( '[data-flxcrm-sample]' );
		var schema;
		var state;
		try {
			schema = JSON.parse( schemaEl.textContent );
			state = JSON.parse( hidden.value || '{}' );
		} catch ( e ) {
			return;
		}
		state.match = 'any' === state.match ? 'any' : 'all';
		state.rules = Array.isArray( state.rules ) ? state.rules : [];
		var fieldKeys = Object.keys( schema.fields );
		var timer = null;
		var seq = 0;

		function defaults( field ) {
			var f = schema.fields[ field ];
			var opts = schema.options[ f.type ] || [];
			var v = '';
			if ( 'number' === f.type ) {
				v = 0;
			} else if ( 'days' === f.type ) {
				v = 90;
			} else if ( opts.length ) {
				v = opts[ 0 ][ 0 ];
			}
			return { field: field, op: f.ops[ 0 ], value: v };
		}

		function valueInput( rule, i ) {
			var f = schema.fields[ rule.field ];
			var opts = schema.options[ f.type ];
			if ( 'bool' === f.type ) {
				return null;
			}
			if ( opts ) {
				var s = el( 'select', { 'aria-label': f.label } );
				opts.forEach( function ( o ) {
					var op = el( 'option', { value: o[ 0 ], text: o[ 1 ] } );
					if ( String( o[ 0 ] ) === String( rule.value ) ) {
						op.selected = true;
					}
					s.appendChild( op );
				} );
				s.addEventListener( 'change', function () {
					state.rules[ i ].value = s.value;
					changed();
				} );
				return s;
			}
			var numeric = 'number' === f.type || 'days' === f.type;
			var inp = el( 'input', {
				type: numeric ? 'number' : 'text',
				'aria-label': f.label,
			} );
			if ( numeric ) {
				inp.min = '0';
				inp.step = 'any';
			}
			inp.value = rule.value;
			inp.addEventListener( 'input', function () {
				state.rules[ i ].value = numeric ? Number( inp.value || 0 ) : inp.value;
				changed();
			} );
			if ( 'days' === f.type ) {
				var wrap = el( 'span', { class: 'flxcrm-rules__days' }, [ inp, el( 'span', { text: ' ' + ( t.days || '' ) } ) ] );
				return wrap;
			}
			return inp;
		}

		function render() {
			list.innerHTML = '';
			state.rules.forEach( function ( rule, i ) {
				if ( ! schema.fields[ rule.field ] ) {
					return;
				}
				var f = schema.fields[ rule.field ];
				var fs = el( 'select', { 'aria-label': f.label } );
				fieldKeys.forEach( function ( k ) {
					var o = el( 'option', { value: k, text: schema.fields[ k ].label } );
					if ( k === rule.field ) {
						o.selected = true;
					}
					fs.appendChild( o );
				} );
				fs.addEventListener( 'change', function () {
					state.rules[ i ] = defaults( fs.value );
					render();
					changed();
				} );
				var os = el( 'select', { 'aria-label': f.label } );
				f.ops.forEach( function ( k ) {
					var o = el( 'option', { value: k, text: schema.ops[ k ] || k } );
					if ( k === rule.op ) {
						o.selected = true;
					}
					os.appendChild( o );
				} );
				os.addEventListener( 'change', function () {
					state.rules[ i ].op = os.value;
					changed();
				} );
				var rm = el( 'button', {
					type: 'button',
					class: 'button-link',
					'aria-label': t.remove || '×',
					title: t.remove || '',
					html: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>',
				} );
				rm.addEventListener( 'click', function () {
					state.rules.splice( i, 1 );
					render();
					changed();
				} );
				var li = el( 'li', {}, [
					i > 0 ? el( 'span', { class: 'flxcrm-rules__and', text: 'any' === state.match ? t.or : t.and } ) : null,
					fs,
					os,
					valueInput( rule, i ),
					rm,
				] );
				list.appendChild( li );
			} );
		}

		function sync() {
			hidden.value = JSON.stringify( state );
		}

		function preview() {
			var my = ++seq;
			if ( countEl ) {
				countEl.textContent = '…';
			}
			fetch( cfg.rest + '/segments/preview', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce },
				body: JSON.stringify( { rules: state } ),
			} )
				.then( function ( r ) {
					if ( ! r.ok ) {
						throw new Error( 'fail' );
					}
					return r.json();
				} )
				.then( function ( d ) {
					if ( my !== seq ) {
						return;
					}
					countEl.textContent = num( d.count );
					sumEl.textContent = state.rules.length ? d.summary : t.empty;
					sampleEl.innerHTML = '';
					if ( ! d.sample.length ) {
						sampleEl.appendChild( el( 'li', { class: 'flxcrm-muted', text: t.none } ) );
					}
					d.sample.forEach( function ( c ) {
						var u = new URL( window.location.href );
						u.search = '';
						u.searchParams.set( 'page', 'flx-crm-contacts' );
						u.searchParams.set( 'id', c.id );
						sampleEl.appendChild( el( 'li', {}, [ el( 'a', { href: u.toString(), text: c.name } ) ] ) );
					} );
				} )
				.catch( function () {
					if ( my === seq ) {
						countEl.textContent = '–';
						sumEl.textContent = t.fail;
					}
				} );
		}

		function changed() {
			sync();
			window.clearTimeout( timer );
			timer = window.setTimeout( preview, 300 );
		}

		match.addEventListener( 'change', function () {
			state.match = match.value;
			render();
			changed();
		} );
		add.addEventListener( 'click', function () {
			state.rules.push( defaults( fieldKeys[ 0 ] ) );
			render();
			changed();
			var last = list.querySelector( 'li:last-child select' );
			if ( last ) {
				last.focus();
			}
		} );
		form.addEventListener( 'submit', sync );

		render();
		sync();
		preview();
	} );
} );

/* Zaman tüneli: 20'şer olay; aşağı kaydırınca devamı yüklenir (mobilde tünel kendi içinde kayar). */
window.flxcrmBoot.push( function ( R ) {
	'use strict';
	var cfg = window.flxcrmAdmin || {};
	flxcrmOnce( R, '[data-flxcrm-tl]' ).forEach( function ( list ) {
		var busy = false;
		/* Mobilde tünel kendi içinde kayar: gözcünün kökü liste; masaüstünde sayfa. */
		var own = /(auto|scroll)/.test( window.getComputedStyle( list ).overflowY );
		var io = 'IntersectionObserver' in window ? new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( en ) {
				if ( en.isIntersecting ) {
					load( en.target );
				}
			} );
		}, { root: own ? list : null, rootMargin: own ? '0px 0px 200px 0px' : '300px 0px' } ) : null;
		function watch() {
			var more = list.querySelector( '[data-flxcrm-more]' );
			if ( ! more ) {
				return;
			}
			if ( io ) {
				io.observe( more );
			} else {
				load( more );
			}
		}
		function load( more ) {
			if ( busy || ! more.isConnected ) {
				return;
			}
			busy = true;
			if ( io ) {
				io.unobserve( more );
			}
			var body = new FormData();
			body.append( 'action', 'flxcrm_timeline' );
			body.append( '_ajax_nonce', cfg.nonce );
			body.append( 'id', list.getAttribute( 'data-id' ) );
			body.append( 'tl', list.getAttribute( 'data-tl' ) || '' );
			body.append( 'base', list.getAttribute( 'data-base' ) || '' );
			body.append( 'offset', more.getAttribute( 'data-offset' ) );
			body.append( 'day', more.getAttribute( 'data-day' ) || '' );
			fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( j ) {
					if ( ! j || ! j.success ) {
						throw new Error( 'fail' );
					}
					more.insertAdjacentHTML( 'beforebegin', j.data.html );
					more.remove();
					busy = false;
					watch();
				} )
				.catch( function () {
					busy = false;
					more.textContent = ( cfg.i18n && cfg.i18n.fail ) || '';
				} );
		}
		watch();
	} );

	/* Kişi kartı › Bilgiler: salt okunur görünüm, "Düzenle" ile form; hatalı telefonla kaydetmez. */
	flxcrmOnce( R, '[data-flxcrm-details]' ).forEach( function ( card ) {
		var btn = card.querySelector( '[data-flxcrm-edit]' );
		var view = card.querySelector( '.flxcrm-details__view' );
		var form = card.querySelector( '.flxcrm-details__form' );
		if ( ! btn || ! view || ! form ) {
			return;
		}
		function mode( edit ) {
			card.classList.toggle( 'is-editing', edit );
			view.hidden = edit;
			form.hidden = ! edit;
			btn.hidden = edit;
			btn.setAttribute( 'aria-expanded', edit ? 'true' : 'false' );
			if ( edit ) {
				var first = form.querySelector( 'input:not([type=hidden]),select,textarea' );
				if ( first ) {
					first.focus();
				}
			} else {
				btn.focus();
			}
		}
		btn.addEventListener( 'click', function () {
			mode( true );
		} );
		var cancel = form.querySelector( '[data-flxcrm-cancel]' );
		if ( cancel ) {
			cancel.addEventListener( 'click', function () {
				/* Değişiklik varsa (telefon bileşeni dahil) sayfa yeniden yüklenir; yoksa yalnız görünüme dönülür. */
				if ( dirty ) {
					flxcrmReload();
					return;
				}
				mode( false );
			} );
		}
		var dirty = false;
		form.addEventListener( 'input', function () {
			dirty = true;
		} );
		form.addEventListener( 'change', function () {
			dirty = true;
		} );
		form.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && ! e.defaultPrevented && cancel && ! form.querySelector( '.flx-ph.is-open' ) ) {
				cancel.click();
			}
		} );
		form.addEventListener( 'submit', function ( e ) {
			var bad = form.querySelector( '.flx-ph.is-bad' );
			if ( bad ) {
				e.preventDefault();
				var num = bad.querySelector( '.flx-ph__num' );
				if ( num ) {
					num.focus();
				}
			}
		} );
	} );
} );

/* Yolculuk düzenleyicisi: türe göre alan göster/gizle, adım ekle/sil. */
window.flxcrmBoot.push( function ( R ) {
	'use strict';
	function sync( sel ) {
		var group = sel.getAttribute( 'data-flxcrm-jswitch' );
		var form = sel.closest( 'form' ) || document;
		form.querySelectorAll( '[data-flxcrm-jshow]' ).forEach( function ( el ) {
			var spec = el.getAttribute( 'data-flxcrm-jshow' );
			var at = spec.lastIndexOf( ':' );
			if ( spec.slice( 0, at ) !== group ) {
				return;
			}
			var on = spec.slice( at + 1 ).split( ',' ).indexOf( sel.value ) > -1;
			el.hidden = ! on;
			el.querySelectorAll( 'input,select,textarea' ).forEach( function ( f ) {
				f.disabled = ! on;
			} );
		} );
	}
	function bindSwitches( root ) {
		flxcrmOnce( root, '[data-flxcrm-jswitch]' ).forEach( function ( sel ) {
			sel.addEventListener( 'change', function () {
				sync( sel );
			} );
			sync( sel );
		} );
	}
	flxcrmOnce( R, '[data-flxcrm-journey]' ).forEach( function ( form ) {
		var list = form.querySelector( '[data-flxcrm-jsteps]' );
		var tpl = form.querySelector( 'template[data-flxcrm-jtpl]' );
		var n = list ? list.children.length : 0;
		bindSwitches( form );
		form.addEventListener( 'click', function ( e ) {
			var add = e.target.closest( '[data-flxcrm-jadd]' );
			var del = e.target.closest( '[data-flxcrm-jdel]' );
			if ( add && tpl && list ) {
				var i = 'n' + ( n++ );
				var box = document.createElement( 'div' );
				box.innerHTML = tpl.innerHTML.replace( /__i__/g, i );
				var li = box.firstElementChild;
				list.appendChild( li );
				bindSwitches( li );
				var first = li.querySelector( 'select' );
				if ( first ) {
					first.focus();
				}
			} else if ( del ) {
				var step = del.closest( '.flxcrm-jstep' );
				if ( step && list.children.length > 1 ) {
					step.remove();
				}
			}
		} );
	} );
} );

/*
 * Sayfa yenilemeden gezinme: CRM ekranlarında (ve başka eklentideki ortak kişi kartında) bağlantılar, filtreler
 * ve formlar fetch ile gider; yalnız CRM içeriği değişir. Yeni ekranın stil/betikleri eksikse eklenir.
 * Yeni bir sayfa CRM içeriği vermezse (başka eklentiye geçiş, hata) normal gezinmeye düşer.
 * Kapatmak için bağlantıya/forma data-flxcrm-hard.
 */
( function () {
	'use strict';
	if ( ! window.fetch || ! window.DOMParser || ! window.history || ! history.pushState ) {
		return;
	}
	var busy = null;

	function root( n ) {
		return n && n.closest ? n.closest( '.flxcrm' ) : null;
	}
	function pick( doc, box ) {
		return doc.querySelector( box.classList.contains( 'wrap' ) ? '.wrap.flxcrm' : '.flxcrm--contact' );
	}
	function page( u ) {
		return u.searchParams.get( 'page' ) || '';
	}
	/* Gidilen adres bu kökün ekranı mı: CRM ekranları kendi aralarında, kişi kartı yalnız kendi sayfasında. */
	function ours( u, box ) {
		var here = new URL( window.location.href );
		if ( u.origin !== here.origin || u.pathname !== here.pathname ) {
			return false;
		}
		if ( box.classList.contains( 'wrap' ) ) {
			return 0 === page( u ).indexOf( 'flx-crm' );
		}
		return page( u ) === page( here );
	}
	function clean( url ) {
		var u = new URL( url, window.location.href );
		u.searchParams.delete( 'flxcrm_msg' );
		u.searchParams.delete( 'flxcrm_err' );
		u.searchParams.delete( 'flxcrm_edit' );
		return u.toString();
	}
	/* Yeni ekranın ihtiyaç duyduğu ama sayfada olmayan stil ve betikler (ör. telefon alanı), sırayla. */
	function assets( doc ) {
		var chain = Promise.resolve();
		doc.querySelectorAll( 'link[rel=stylesheet][id], style[id]' ).forEach( function ( n ) {
			if ( ! document.getElementById( n.id ) ) {
				document.head.appendChild( document.importNode( n, true ) );
			}
		} );
		doc.querySelectorAll( 'script[id]' ).forEach( function ( n ) {
			if ( document.getElementById( n.id ) || ( n.type && 'text/javascript' !== n.type && 'module' !== n.type ) ) {
				return;
			}
			chain = chain.then( function () {
				return new Promise( function ( done ) {
					var s = document.createElement( 'script' );
					s.id = n.id;
					if ( n.src ) {
						s.src = n.src;
						s.onload = s.onerror = function () {
							done();
						};
						document.body.appendChild( s );
					} else {
						s.textContent = n.textContent;
						document.body.appendChild( s );
						done();
					}
				} );
			} );
		} );
		return chain;
	}
	/* Sol menüde geçerli öğe. */
	function menu( doc ) {
		var cur = doc.querySelector( '#adminmenu .wp-submenu li.current a' );
		if ( ! cur ) {
			return;
		}
		var want = cur.getAttribute( 'href' );
		document.querySelectorAll( '#adminmenu .wp-submenu li' ).forEach( function ( li ) {
			var a = li.querySelector( 'a' );
			var on = !! a && a.getAttribute( 'href' ) === want;
			li.classList.toggle( 'current', on );
			if ( a ) {
				a.classList.toggle( 'current', on );
				if ( on ) {
					a.setAttribute( 'aria-current', 'page' );
				} else {
					a.removeAttribute( 'aria-current' );
				}
			}
		} );
	}

	/**
	 * @param {Element} box  Değişecek CRM kökü.
	 * @param {string}  url  Adres.
	 * @param {Object}  opt  body (POST), push (geçmişe ekle), keep (kaydırma yerinde kalsın).
	 */
	function go( box, url, opt ) {
		opt = opt || {};
		if ( busy ) {
			busy.abort();
		}
		var ctl = 'AbortController' in window ? new AbortController() : null;
		busy = ctl;
		var slow = setTimeout( function () {
			box.classList.add( 'is-loading' );
			box.setAttribute( 'aria-busy', 'true' );
		}, 120 );
		var y = window.scrollY;
		var init = { credentials: 'same-origin', headers: { 'X-Flxcrm-Soft': '1' }, signal: ctl ? ctl.signal : undefined };
		if ( opt.body ) {
			init.method = 'POST';
			init.body = opt.body;
		}
		return fetch( url, init )
			.then( function ( r ) {
				return r.text().then( function ( html ) {
					return { url: r.url || url, ok: r.ok, html: html };
				} );
			} )
			.then( function ( res ) {
				var doc = new DOMParser().parseFromString( res.html, 'text/html' );
				var next = pick( doc, box );
				var to = new URL( res.url, window.location.href );
				if ( ! res.ok || ! next || ! ours( to, box ) ) {
					window.location.assign( res.url );
					return;
				}
				return assets( doc ).then( function () {
					var fresh = document.importNode( next, true );
					box.replaceWith( fresh );
					if ( doc.title ) {
						document.title = doc.title;
					}
					menu( doc );
					var shown = clean( res.url ) + ( opt.hash || '' );
					if ( opt.push && shown !== window.location.href ) {
						history.pushState( { flxcrm: 1 }, '', shown );
					} else {
						history.replaceState( { flxcrm: 1 }, '', shown );
					}
					boot( fresh );
					var at = opt.hash ? document.getElementById( opt.hash.slice( 1 ) ) : null;
					if ( at ) {
						at.scrollIntoView( { block: 'start' } );
					} else if ( opt.keep ) {
						window.scrollTo( 0, y );
					} else {
						window.scrollTo( 0, 0 );
					}
					/* Bildirim (Kaydedildi / hata) varsa ekran okuyucu duysun; görünmüyorsa oraya kaydır. */
					var note = fresh.querySelector( '.flxcrm-notice' );
					if ( note && opt.body ) {
						var top = note.getBoundingClientRect().top;
						if ( top < 0 || top > window.innerHeight ) {
							note.scrollIntoView( { block: 'center' } );
						}
					}
				} );
			} )
			.catch( function ( e ) {
				if ( e && 'AbortError' === e.name ) {
					return;
				}
				window.location.assign( url );
			} )
			.then( function () {
				clearTimeout( slow );
				if ( busy === ctl ) {
					busy = null;
				}
				box.classList.remove( 'is-loading' );
				box.removeAttribute( 'aria-busy' );
			} );
	}

	function boot( r ) {
		( window.flxcrmBoot || [] ).forEach( function ( fn ) {
			fn( r );
		} );
		if ( window.flxPhone && window.flxPhone.init ) {
			window.flxPhone.init( r );
		}
		if ( window.flxField && window.flxField.init ) {
			window.flxField.init( r );
		}
		if ( r !== document && window.flxaListInit ) {
			window.flxaListInit();
		}
	}

	/* Aynı ekranı sessizce yeniler (eşitleme / skorlama bitince). */
	window.flxcrmReload = function () {
		var box = document.querySelector( '.wrap.flxcrm, .flxcrm--contact' );
		if ( ! box ) {
			window.location.reload();
			return;
		}
		go( box, window.location.href, { keep: true } );
	};

	document.addEventListener( 'click', function ( e ) {
		if ( e.defaultPrevented || 0 !== e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
			return;
		}
		var a = e.target.closest ? e.target.closest( 'a[href]' ) : null;
		var box = root( a );
		/* Sol menüdeki CRM bağlantıları da bir CRM ekranındayken yenilemeden açılır. */
		if ( a && ! box && a.closest( '#adminmenu' ) ) {
			box = document.querySelector( '.wrap.flxcrm' );
		}
		if ( ! a || ! box || a.target || a.hasAttribute( 'download' ) || a.closest( '[data-flxcrm-hard]' ) ) {
			return;
		}
		var u = new URL( a.href, window.location.href );
		if ( ! ours( u, box ) ) {
			return;
		}
		var here = new URL( window.location.href );
		/* Aynı ekran içinde yalnız #çapa: tarayıcıya bırak. */
		if ( u.hash && u.search === here.search ) {
			return;
		}
		e.preventDefault();
		var hash = u.hash;
		u.hash = '';
		/* Zaman tüneli sekmeleri gibi aynı ekrandaki geçişlerde sayfa yerinde kalır. */
		go( box, u.toString(), { push: true, hash: hash, keep: page( u ) === page( here ) && u.searchParams.get( 'id' ) === here.searchParams.get( 'id' ) } );
	} );

	document.addEventListener( 'submit', function ( e ) {
		var f = e.target;
		var box = root( f );
		if ( e.defaultPrevented || ! box || f.target || f.closest( '[data-flxcrm-hard]' ) || 'multipart/form-data' === f.enctype ) {
			return;
		}
		var action = new URL( f.getAttribute( 'action' ) || window.location.href, window.location.href );
		var post = 'post' === ( f.getAttribute( 'method' ) || 'get' ).toLowerCase();
		var data = new FormData( f );
		if ( e.submitter && e.submitter.name ) {
			data.append( e.submitter.name, e.submitter.value );
		}
		if ( post ) {
			if ( action.origin !== window.location.origin || ! /\/admin-post\.php$/.test( action.pathname ) ) {
				return;
			}
			e.preventDefault();
			if ( e.submitter ) {
				e.submitter.disabled = true;
			}
			go( box, action.toString(), { body: data, keep: true } );
			return;
		}
		var u = new URL( action.toString() );
		u.search = '';
		data.forEach( function ( v, k ) {
			if ( 'string' === typeof v && '' !== v ) {
				u.searchParams.append( k, v );
			}
		} );
		if ( ! ours( u, box ) ) {
			return;
		}
		e.preventDefault();
		go( box, u.toString(), { push: true, keep: true } );
	} );

	window.addEventListener( 'popstate', function ( e ) {
		if ( ! e.state || ! e.state.flxcrm ) {
			return;
		}
		var box = document.querySelector( '.wrap.flxcrm, .flxcrm--contact' );
		if ( box ) {
			go( box, window.location.href, { keep: false } );
		}
	} );

	if ( document.querySelector( '.wrap.flxcrm, .flxcrm--contact' ) ) {
		history.replaceState( { flxcrm: 1 }, '', window.location.href );
	}
	boot( document );
} )();

/* ⋯ menüsü: dışarı tıklayınca ya da Esc ile kapanır. */
document.addEventListener( 'click', function ( e ) {
	document.querySelectorAll( '.flxcrm-more[open]' ).forEach( function ( d ) {
		if ( ! d.contains( e.target ) || ( e.target.closest && e.target.closest( '.flxcrm-more__item' ) ) ) {
			d.removeAttribute( 'open' );
		}
	} );
} );
document.addEventListener( 'keydown', function ( e ) {
	if ( 'Escape' === e.key ) {
		document.querySelectorAll( '.flxcrm-more[open]' ).forEach( function ( d ) {
			d.removeAttribute( 'open' );
			d.querySelector( 'summary' ).focus();
		} );
	}
} );
