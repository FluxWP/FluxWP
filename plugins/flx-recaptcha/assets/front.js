/* Flux reCAPTCHA · ön yüz. GPL-2.0-or-later.
 *
 * - Google betiği gerektiğinde yüklenir: v3'te korunan forma ilk dokunuşta, v2'de kutucuk ekrana yaklaşınca.
 * - Klasik formlar (.flxrc-field): gönderimde taze v3 belirteci alınır ve form yeniden gönderilir; v2'de kutucuk boşsa durur.
 *   Arkada da taze belirteç tutulur (90 sn), böylece formu JS ile gönderen eklentiler (jQuery trigger) de belirteç taşır.
 * - Seçiciyle korunan formlar (Flux Donate): alan forma kendiliğinden eklenir.
 * - REST ile gönderenler (blok ödeme, Flux Forms): fetch isteğine X-Flx-Captcha başlığı eklenir.
 * - Diğer eklentiler: window.flxCaptcha.token( 'bağlam' ) → Promise<string>.
 */
( function () {
	'use strict';
	var C = window.flxrcCfg;
	if ( ! C || ! C.key ) {
		return;
	}
	var V3 = C.v !== 'v2';
	var apiP = null;
	var fresh = {}; // bağlam → { t: belirteç, at: zaman }
	var widgets = []; // { el, id, ctx }
	var FRESH_MS = 90000;

	function api() {
		if ( apiP ) {
			return apiP;
		}
		apiP = new Promise( function ( resolve, reject ) {
			if ( window.grecaptcha && window.grecaptcha.render && ! V3 ) {
				return resolve( window.grecaptcha );
			}
			window.flxrcOnload = function () {
				window.grecaptcha.ready( function () {
					resolve( window.grecaptcha );
				} );
			};
			var s = document.createElement( 'script' );
			var q = V3 ? 'render=' + encodeURIComponent( C.key ) : 'render=explicit';
			s.src = C.api + '?' + q + '&onload=flxrcOnload&hl=' + encodeURIComponent( C.hl || '' );
			s.async = true;
			s.defer = true;
			s.onerror = function () {
				apiP = null;
				reject( new Error( 'recaptcha' ) );
			};
			document.head.appendChild( s );
		} );
		return apiP;
	}

	function act( ctx ) {
		return ( C.ctx[ ctx ] && C.ctx[ ctx ].action ) || ctx;
	}

	/* ------------------------------------------------------------ */
	/* Belirteç                                                      */
	/* ------------------------------------------------------------ */

	function v3token( ctx ) {
		return api().then( function ( g ) {
			return g.execute( C.key, { action: act( ctx ) } );
		} );
	}

	/** v3: arka planda taze belirteç tut. */
	function prefetch( ctx ) {
		if ( ! V3 ) {
			return;
		}
		var f = fresh[ ctx ];
		if ( f && ( f.p || Date.now() - f.at < FRESH_MS ) ) {
			return;
		}
		fresh[ ctx ] = { p: true };
		v3token( ctx ).then(
			function ( t ) {
				fresh[ ctx ] = { t: t, at: Date.now() };
				fill( ctx, t );
				setTimeout( function () {
					if ( fresh[ ctx ] && fresh[ ctx ].t === t ) {
						delete fresh[ ctx ];
						if ( document.visibilityState !== 'hidden' && touched[ ctx ] ) {
							prefetch( ctx );
						}
					}
				}, FRESH_MS );
			},
			function () {
				delete fresh[ ctx ];
			}
		);
	}

	/** Kullanılmamış taze belirteci al (ve harca). */
	function take( ctx ) {
		var f = fresh[ ctx ];
		if ( f && f.t && Date.now() - f.at < FRESH_MS ) {
			delete fresh[ ctx ];
			return f.t;
		}
		return '';
	}

	function fill( ctx, t ) {
		var list = document.querySelectorAll( '.flxrc-field[data-flxrc="' + ctx + '"] input[name="' + C.field + '"]' );
		for ( var i = 0; i < list.length; i++ ) {
			list[ i ].value = t;
		}
	}

	function widgetFor( el ) {
		for ( var i = 0; i < widgets.length; i++ ) {
			if ( widgets[ i ].el === el ) {
				return widgets[ i ];
			}
		}
		return null;
	}

	/** v2: bağlamın (ya da kapsayıcının) dolu kutucuk yanıtı. */
	function v2response( ctx, root ) {
		if ( ! window.grecaptcha ) {
			return '';
		}
		for ( var i = 0; i < widgets.length; i++ ) {
			var w = widgets[ i ];
			if ( w.ctx !== ctx || ! document.contains( w.el ) || ( root && ! root.contains( w.el ) ) ) {
				continue;
			}
			var r = window.grecaptcha.getResponse( w.id );
			if ( r ) {
				return r;
			}
		}
		return '';
	}

	function v2reset( ctx ) {
		if ( ! window.grecaptcha ) {
			return;
		}
		widgets.forEach( function ( w ) {
			if ( w.ctx === ctx && document.contains( w.el ) ) {
				try {
					window.grecaptcha.reset( w.id );
				} catch ( e ) {}
			}
		} );
	}

	/** Diğer eklentiler için: taze belirteç (v3) ya da kutucuk yanıtı (v2; boşsa ''). */
	function token( ctx, root ) {
		if ( ! V3 ) {
			return Promise.resolve( v2response( ctx, root ) );
		}
		var t = take( ctx );
		var p = t ? Promise.resolve( t ) : v3token( ctx );
		return p.then( function ( x ) {
			touched[ ctx ] = 1;
			setTimeout( function () {
				prefetch( ctx );
			}, 400 );
			return x;
		} );
	}

	/* ------------------------------------------------------------ */
	/* Alanlar ve v2 kutucuğu                                         */
	/* ------------------------------------------------------------ */

	var touched = {};
	var io = 'IntersectionObserver' in window ? new IntersectionObserver( function ( ents ) {
		ents.forEach( function ( e ) {
			if ( e.isIntersecting ) {
				io.unobserve( e.target );
				renderV2( e.target );
			}
		} );
	}, { rootMargin: '300px' } ) : null;

	function theme() {
		if ( C.theme === 'dark' || C.theme === 'light' ) {
			return C.theme;
		}
		/* Otomatik: sitenin kendi koyu modu (FluxWP teması, Flux Admin); işletim sistemine bakılmaz (açık tema + koyu sistem uyuşmazlığı). */
		var d = document.documentElement;
		var cls = ' ' + d.className + ' ' + ( document.body ? document.body.className : '' ) + ' ';
		var dark = /\s(flx-dark|flxa-dark|is-dark|dark-mode|dark)\s/.test( cls ) || d.getAttribute( 'data-theme' ) === 'dark';
		return dark ? 'dark' : 'light';
	}

	function renderV2( box ) {
		if ( box.getAttribute( 'data-flxrc-done' ) ) {
			return;
		}
		box.setAttribute( 'data-flxrc-done', '1' );
		var field = box.closest( '.flxrc-field' );
		var ctx = field ? field.getAttribute( 'data-flxrc' ) : '';
		api().then( function ( g ) {
			if ( ! document.contains( box ) ) {
				return;
			}
			var id = g.render( box, {
				sitekey: C.key,
				theme: theme(),
				size: C.size === 'compact' ? 'compact' : 'normal',
				callback: function () {
					hideError( field );
				},
			} );
			widgets.push( { el: box, id: id, ctx: ctx } );
		} );
	}

	function setup( field ) {
		if ( field.getAttribute( 'data-flxrc-init' ) ) {
			return;
		}
		field.setAttribute( 'data-flxrc-init', '1' );
		var ctx = field.getAttribute( 'data-flxrc' );
		if ( ! V3 ) {
			var box = field.querySelector( '.flxrc-v2' );
			if ( box ) {
				if ( io ) {
					io.observe( box );
				} else {
					renderV2( box );
				}
			}
			return;
		}
		/* v3: yeni çizilen alanı (WooCommerce ödeme parçası) eldeki taze belirteçle doldur. */
		var f = fresh[ ctx ];
		var inp = field.querySelector( 'input[name="' + C.field + '"]' );
		if ( f && f.t && inp ) {
			inp.value = f.t;
		}
	}

	/** Seçiciyle korunan form/kapsayıcıya alan ekle. */
	function inject( root ) {
		Object.keys( C.ctx ).forEach( function ( ctx ) {
			var c = C.ctx[ ctx ];
			if ( ! c.sel ) {
				return;
			}
			var hosts = ( root.matches && root.matches( c.sel ) ) ? [ root ] : root.querySelectorAll ? root.querySelectorAll( c.sel ) : [];
			for ( var i = 0; i < hosts.length; i++ ) {
				var h = hosts[ i ];
				if ( h.querySelector( '.flxrc-field[data-flxrc="' + ctx + '"]' ) ) {
					continue;
				}
				var isForm = h.tagName === 'FORM';
				var before = c.before ? h.querySelector( c.before ) : null;
				/* REST ile gönderen kapsayıcıda v3 için görünür bir şey gerekmez; v2 kutucuğu gönder düğmesinin önüne. */
				if ( ! isForm && ( V3 ? ! C.notice : ! before ) ) {
					continue;
				}
				var d = document.createElement( 'div' );
				d.className = 'flxrc-field flxrc-field--auto';
				d.setAttribute( 'data-flxrc', ctx );
				d.innerHTML = ( isForm ? '<input type="hidden" name="' + C.field + '" value="">' : '' ) + ( V3 ? '' : '<div class="flxrc-v2"></div>' ) + ( C.notice || '' );
				if ( before ) {
					var anchor = before;
					/* Düğme bir sarmalayıcının içindeyse sarmalayıcının önüne. */
					while ( anchor.parentNode && anchor.parentNode !== h && anchor.parentNode.children.length === 1 ) {
						anchor = anchor.parentNode;
					}
					anchor.parentNode.insertBefore( d, anchor );
				} else {
					h.appendChild( d );
				}
			}
		} );
	}

	function scan( root ) {
		inject( root || document );
		var list = ( root || document ).querySelectorAll ? ( root || document ).querySelectorAll( '.flxrc-field' ) : [];
		for ( var i = 0; i < list.length; i++ ) {
			setup( list[ i ] );
		}
	}

	/* ------------------------------------------------------------ */
	/* Hata kutusu                                                    */
	/* ------------------------------------------------------------ */

	function showError( field, msg ) {
		if ( ! field ) {
			window.alert( msg );
			return;
		}
		var e = field.querySelector( '.flxrc-error' );
		if ( ! e ) {
			e = document.createElement( 'p' );
			e.className = 'flxrc-error';
			e.setAttribute( 'role', 'alert' );
			field.appendChild( e );
		}
		e.textContent = msg;
		field.scrollIntoView && field.scrollIntoView( { block: 'center', behavior: 'smooth' } );
	}

	function hideError( field ) {
		var e = field && field.querySelector( '.flxrc-error' );
		if ( e ) {
			e.parentNode.removeChild( e );
		}
	}

	/* ------------------------------------------------------------ */
	/* Klasik form gönderimi                                          */
	/* ------------------------------------------------------------ */

	function fieldOf( form ) {
		return form && form.querySelector ? form.querySelector( '.flxrc-field[data-flxrc]' ) : null;
	}

	document.addEventListener( 'focusin', touch, true );
	document.addEventListener( 'pointerdown', touch, true );
	document.addEventListener( 'keydown', touch, true );

	function touch( e ) {
		var t = e.target;
		if ( ! t || ! t.closest ) {
			return;
		}
		var host = t.closest( 'form' );
		var field = fieldOf( host );
		var ctx = field ? field.getAttribute( 'data-flxrc' ) : '';
		if ( ! ctx ) {
			Object.keys( C.ctx ).some( function ( k ) {
				if ( C.ctx[ k ].sel && t.closest( C.ctx[ k ].sel ) ) {
					ctx = k;
					return true;
				}
				return false;
			} );
		}
		if ( ! ctx ) {
			return;
		}
		if ( ! V3 ) {
			api();
			return;
		}
		touched[ ctx ] = 1;
		prefetch( ctx );
	}

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		var field = fieldOf( form );
		if ( ! field ) {
			return;
		}
		var ctx = field.getAttribute( 'data-flxrc' );
		if ( ! V3 ) {
			var r = v2response( ctx, form );
			if ( ! r ) {
				e.preventDefault();
				e.stopImmediatePropagation();
				showError( field, C.i18n.empty );
			} else {
				hideError( field );
				var hid = field.querySelector( 'input[name="' + C.field + '"]' );
				if ( hid ) {
					hid.value = r;
				}
			}
			return;
		}
		var inp = field.querySelector( 'input[name="' + C.field + '"]' );
		if ( form.getAttribute( 'data-flxrc-go' ) ) {
			form.removeAttribute( 'data-flxrc-go' );
			after( ctx );
			return;
		}
		var t = take( ctx );
		if ( t ) {
			inp.value = t;
			after( ctx );
			return;
		}
		e.preventDefault();
		e.stopImmediatePropagation();
		var sub = e.submitter || null;
		v3token( ctx ).then(
			function ( tok ) {
				inp.value = tok;
				form.setAttribute( 'data-flxrc-go', '1' );
				resubmit( form, sub );
			},
			function () {
				showError( field, C.i18n.empty );
			}
		);
	}, true );

	function resubmit( form, sub ) {
		if ( form.requestSubmit ) {
			try {
				if ( sub && sub.form === form ) {
					form.requestSubmit( sub );
				} else {
					form.requestSubmit();
				}
				return;
			} catch ( x ) {}
		}
		/* Eski tarayıcı: düğmenin adı/değeri kaybolmasın. */
		if ( sub && sub.name ) {
			var h = document.createElement( 'input' );
			h.type = 'hidden';
			h.name = sub.name;
			h.value = sub.value;
			form.appendChild( h );
		}
		HTMLFormElement.prototype.submit.call( form );
	}

	/** Gönderim sonrası: belirteç harcandı, yenisini hazırla (AJAX formlar sayfada kalır). */
	function after( ctx ) {
		setTimeout( function () {
			fill( ctx, '' );
			prefetch( ctx );
		}, 1500 );
	}

	/* WooCommerce klasik ödeme hata verince: v2 kutucuğunu sıfırla, v3 belirtecini yenile. */
	if ( window.jQuery ) {
		window.jQuery( document.body ).on( 'checkout_error', function () {
			if ( V3 ) {
				delete fresh.checkout;
				prefetch( 'checkout' );
			} else {
				v2reset( 'checkout' );
			}
		} );
	}

	/* ------------------------------------------------------------ */
	/* REST (fetch) gönderimleri                                      */
	/* ------------------------------------------------------------ */

	var routed = Object.keys( C.ctx ).filter( function ( k ) {
		return C.ctx[ k ].routes && C.ctx[ k ].routes.length;
	} );

	function routeOf( url ) {
		var a = document.createElement( 'a' );
		a.href = url;
		if ( a.host !== location.host ) {
			return '';
		}
		var m = /[?&]rest_route=([^&]+)/.exec( a.search );
		if ( m ) {
			return decodeURIComponent( m[ 1 ] );
		}
		var root = C.rest || '/wp-json/';
		var p = a.pathname;
		return p.indexOf( root ) === 0 ? '/' + p.slice( root.length ).replace( /^\/+/, '' ) : '';
	}

	function ctxOf( url ) {
		var r = routeOf( url );
		if ( ! r ) {
			return '';
		}
		for ( var i = 0; i < routed.length; i++ ) {
			var rs = C.ctx[ routed[ i ] ].routes;
			for ( var j = 0; j < rs.length; j++ ) {
				try {
					if ( new RegExp( rs[ j ][ 0 ], rs[ j ][ 1 ] ).test( r ) ) {
						return routed[ i ];
					}
				} catch ( x ) {}
			}
		}
		return '';
	}

	if ( routed.length && window.fetch ) {
		var orig = window.fetch;
		window.fetch = function ( input, init ) {
			var url = typeof input === 'string' ? input : ( input && input.url ) || String( input );
			var method = ( ( init && init.method ) || ( input && input.method ) || 'GET' ).toUpperCase();
			var ctx = method === 'POST' ? ctxOf( url ) : '';
			if ( ! ctx ) {
				return orig.apply( this, arguments );
			}
			var self = this;
			return token( ctx ).then(
				function ( t ) {
					return send( t );
				},
				function () {
					return send( '' );
				}
			);
			function send( t ) {
				if ( ! V3 ) {
					setTimeout( function () {
						v2reset( ctx );
					}, 50 );
				}
				if ( ! t ) {
					return orig.call( self, input, init );
				}
				var opts = Object.assign( {}, init || {} );
				var h = new Headers( ( init && init.headers ) || ( input && input.headers ) || {} );
				h.set( C.header, t );
				opts.headers = h;
				if ( typeof input !== 'string' && input instanceof Request ) {
					return orig.call( self, new Request( input, opts ) );
				}
				return orig.call( self, input, opts );
			}
		};
	}

	/* ------------------------------------------------------------ */
	/* Başlat                                                         */
	/* ------------------------------------------------------------ */

	window.flxCaptcha = {
		token: token,
		reset: v2reset,
		header: C.header,
		field: C.field,
		version: C.v,
	};

	if ( ! C.badge ) {
		document.documentElement.classList.add( 'flxrc-nobadge' );
	}

	function start() {
		scan( document );
		if ( 'MutationObserver' in window ) {
			var queued = false;
			new MutationObserver( function () {
				if ( queued ) {
					return;
				}
				queued = true;
				( window.requestAnimationFrame || setTimeout )( function () {
					queued = false;
					scan( document );
				} );
			} ).observe( document.body, { childList: true, subtree: true } );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
