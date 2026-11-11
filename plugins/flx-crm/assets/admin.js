/* Flux CRM yönetim ekranları: eşitleme, skorlama, toplu işlem, onay, segment kural düzenleyici. */
( function () {
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

	document.querySelectorAll( '[data-flxcrm-sync]' ).forEach( function ( btn ) {
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
								window.location.reload();
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

	document.querySelectorAll( '[data-flxcrm-score]' ).forEach( function ( btn ) {
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
							window.location.reload();
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

	document.addEventListener(
		'submit',
		function ( e ) {
			var f = e.target;
			if ( f.matches && f.matches( '[data-flxcrm-confirm]' ) ) {
				var msg = f.getAttribute( 'data-flxcrm-confirm' ) || t.confirm;
				if ( ! window.confirm( msg ) ) {
					e.preventDefault();
				}
			}
		},
		true
	);

	/* ------------------------------------------------------------ */
	/* Toplu işlem                                                   */
	/* ------------------------------------------------------------ */

	document.querySelectorAll( '[data-flxcrm-bulk]' ).forEach( function ( form ) {
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
			if ( action && 'delete' === action.value && ! window.confirm( t.confirm ) ) {
				e.preventDefault();
			}
		} );
		swap();
		refresh();
	} );

	/* ------------------------------------------------------------ */
	/* Segment kural düzenleyici                                     */
	/* ------------------------------------------------------------ */

	document.querySelectorAll( '[data-flxcrm-segedit]' ).forEach( function ( form ) {
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
} )();

/* Zaman tüneli: 20'şer olay; aşağı kaydırınca devamı yüklenir (mobilde tünel kendi içinde kayar). */
( function () {
	'use strict';
	var cfg = window.flxcrmAdmin || {};
	document.querySelectorAll( '[data-flxcrm-tl]' ).forEach( function ( list ) {
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
	document.querySelectorAll( '[data-flxcrm-details]' ).forEach( function ( card ) {
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
					window.location.reload();
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
} )();
