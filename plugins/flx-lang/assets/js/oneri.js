/* Flux Lang: ziyaretçiye dil önerisi. Ayrıntı: includes/oneri.php */
( function () {
	var d = window.flxlData;
	if ( ! d ) {
		return;
	}
	var BOT = /bot|crawl|spider|slurp|bingpreview|mediapartners|google|yandex|baidu|duckduck|facebookexternalhit|embedly|whatsapp|telegram|skype|discord|slack|twitter|linkedin|pinterest|lighthouse|pagespeed|headless|phantom|prerender|gtmetrix|pingdom|uptime/i;

	function cookie( name ) {
		var m = document.cookie.match( new RegExp( '(?:^|; )' + name + '=([^;]*)' ) );
		return m ? decodeURIComponent( m[ 1 ] ) : '';
	}
	function setCookie( name, value, days ) {
		document.cookie = name + '=' + encodeURIComponent( value ) + '; path=' + d.path + '; max-age=' + days * 86400 + '; SameSite=Lax' + ( location.protocol === 'https:' ? '; Secure' : '' );
	}

	// Dil seçiciden seçen ziyaretçiye bir daha öneri yok.
	document.addEventListener( 'click', function ( e ) {
		var a = e.target.closest && e.target.closest( 'a[data-flxl], .flx-lang a[hreflang]' );
		if ( a ) {
			setCookie( 'flxl_pref', a.getAttribute( 'data-flxl' ) || ( a.getAttribute( 'hreflang' ) || '' ).slice( 0, 2 ), 365 );
		}
	} );

	var force = /[?&]flxl_oneri=1/.test( location.search );
	var pref = cookie( 'flxl_pref' );
	if ( ! force && ( d.mode === 'off' || BOT.test( navigator.userAgent || '' ) || navigator.webdriver ) ) {
		return;
	}
	if ( pref && ! force ) {
		// Ziyaretçi dilini seçmiş (ya da bir kez yönlendirildi): yönlendirme kipinde başka dildeki adrese gelirse kendi diline.
		if ( d.mode === 'redirect' && pref !== d.cur && d.alts[ pref ] ) {
			location.replace( d.alts[ pref ] );
		}
		return;
	}

	function browserLang() {
		var list = navigator.languages && navigator.languages.length ? navigator.languages : [ navigator.language || '' ];
		for ( var i = 0; i < list.length; i++ ) {
			var c = String( list[ i ] ).slice( 0, 2 ).toLowerCase();
			if ( d.alts[ c ] ) {
				return c;
			}
		}
		return '';
	}

	function decide( country ) {
		var home;
		if ( country ) {
			home = d.home.indexOf( country ) !== -1;
		} else {
			var tz = '';
			try {
				tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
			} catch ( e ) {}
			home = tz === 'Europe/Istanbul' || tz === 'Asia/Istanbul' || tz === 'Asia/Famagusta' || tz === 'Asia/Nicosia';
		}
		if ( home ) {
			return d.def;
		}
		var b = browserLang();
		if ( b && b !== d.def ) {
			return b;
		}
		return d.alts[ d.to ] ? d.to : '';
	}

	function show( target ) {
		var t = d.texts[ target ];
		if ( ! t || ! d.alts[ target ] ) {
			return;
		}
		var bar = document.createElement( 'div' );
		bar.className = 'flxl-oneri';
		bar.setAttribute( 'role', 'dialog' );
		bar.setAttribute( 'lang', target );
		bar.setAttribute( 'aria-label', t.msg );
		var p = document.createElement( 'p' );
		p.textContent = t.msg;
		var go = document.createElement( 'a' );
		go.className = 'flxl-oneri__go';
		go.href = d.alts[ target ];
		go.textContent = t.go;
		go.setAttribute( 'hreflang', target );
		go.addEventListener( 'click', function () {
			setCookie( 'flxl_pref', target, 365 );
		} );
		var stay = document.createElement( 'button' );
		stay.type = 'button';
		stay.className = 'flxl-oneri__stay';
		stay.textContent = t.stay;
		stay.addEventListener( 'click', function () {
			setCookie( 'flxl_pref', d.cur, 365 );
			bar.remove();
		} );
		var x = document.createElement( 'button' );
		x.type = 'button';
		x.className = 'flxl-oneri__x';
		x.setAttribute( 'aria-label', t.close );
		x.textContent = '×';
		x.addEventListener( 'click', function () {
			setCookie( 'flxl_pref', d.cur, 30 );
			bar.remove();
		} );
		var row = document.createElement( 'div' );
		row.className = 'flxl-oneri__row';
		row.appendChild( go );
		row.appendChild( stay );
		bar.appendChild( p );
		bar.appendChild( row );
		bar.appendChild( x );
		document.body.appendChild( bar );
		requestAnimationFrame( function () {
			bar.classList.add( 'is-open' );
		} );
	}

	function act( country ) {
		var target = decide( country );
		if ( ! target || target === d.cur ) {
			return;
		}
		if ( d.mode === 'redirect' && ! force ) {
			setCookie( 'flxl_pref', target, 365 );
			location.replace( d.alts[ target ] );
			return;
		}
		if ( document.body ) {
			show( target );
		} else {
			document.addEventListener( 'DOMContentLoaded', function () {
				show( target );
			} );
		}
	}

	var known = cookie( 'flxl_geo' );
	if ( known ) {
		act( known === '-' ? '' : known );
		return;
	}
	var done = false;
	if ( d.mode === 'redirect' && ! force ) {
		// Sayfa çizilmeden karar: ülke çerezi yoksa saat dilimi. Ülke bilgisi sonraki ziyaretler için arka planda alınır.
		act( '' );
		done = true;
	}
	var timer = done ? 0 : setTimeout( function () {
		if ( ! done ) {
			done = true;
			act( '' );
		}
	}, 2500 );
	fetch( d.geo + ( d.geo.indexOf( '?' ) === -1 ? '?' : '&' ) + '_t=' + Date.now(), { credentials: 'omit' } )
		.then( function ( r ) {
			return r.ok ? r.json() : {};
		} )
		.catch( function () {
			return {};
		} )
		.then( function ( j ) {
			var c = j && /^[A-Z]{2}$/.test( j.country || '' ) ? j.country : '';
			setCookie( 'flxl_geo', c || '-', 30 );
			if ( ! done ) {
				done = true;
				clearTimeout( timer );
				act( c );
			}
		} );
} )();
