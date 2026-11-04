/* Flux Wiki – Markdown düzenleyici: araç çubuğu, Yaz / Önizle / Yan yana, Tab girintisi. */
( function () {
	'use strict';
	var A = window.flxWikiAdmin || {};
	var T = A.i18n || {};
	var ed = document.querySelector( '[data-flxwk-ed]' );
	if ( ! ed ) {
		return;
	}
	var ta = ed.querySelector( 'textarea' );
	var pv = ed.querySelector( '[data-preview]' );
	var timer = null;
	var last = null;

	function preview() {
		var md = ta.value;
		if ( md === last ) {
			return;
		}
		last = md;
		if ( ! md.trim() ) {
			pv.innerHTML = '<p>' + ( T.empty || '' ) + '</p>';
			return;
		}
		if ( ! pv.innerHTML ) {
			pv.innerHTML = '<p>' + ( T.loading || '' ) + '</p>';
		}
		fetch( A.rest + 'preview', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': A.nonce }, body: JSON.stringify( { md: md } ) } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( d ) { pv.innerHTML = d && typeof d.html === 'string' ? d.html : '<p>' + ( T.fail || '' ) + '</p>'; } )
			.catch( function () { pv.innerHTML = '<p>' + ( T.fail || '' ) + '</p>'; } );
	}

	function tab( name ) {
		ed.querySelectorAll( '[data-tab]' ).forEach( function ( b ) {
			b.classList.toggle( 'is-on', b.getAttribute( 'data-tab' ) === name );
		} );
		ed.classList.toggle( 'is-split', 'split' === name );
		ta.hidden = 'preview' === name;
		pv.hidden = 'write' === name;
		if ( 'write' !== name ) {
			last = null;
			preview();
		}
		if ( 'preview' !== name ) {
			ta.focus();
		}
	}
	ed.querySelectorAll( '[data-tab]' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () { tab( b.getAttribute( 'data-tab' ) ); } );
	} );
	ta.addEventListener( 'input', function () {
		if ( ! pv.hidden ) {
			clearTimeout( timer );
			timer = setTimeout( preview, 400 );
		}
	} );

	function wrap( before, after, ph, block ) {
		var s = ta.selectionStart;
		var e = ta.selectionEnd;
		var v = ta.value;
		var sel = v.slice( s, e ) || ph || '';
		var pre = '';
		if ( block && s > 0 && v[ s - 1 ] !== '\n' ) {
			pre = '\n';
		}
		var ins = pre + before + sel + after;
		ta.setRangeText( ins, s, e, 'end' );
		ta.selectionStart = s + pre.length + before.length;
		ta.selectionEnd = ta.selectionStart + sel.length;
		ta.focus();
		ta.dispatchEvent( new Event( 'input' ) );
	}
	var actions = {
		h2: function () { wrap( '## ', '', T.heading, true ); },
		h3: function () { wrap( '### ', '', T.heading, true ); },
		b: function () { wrap( '**', '**', T.text ); },
		i: function () { wrap( '_', '_', T.text ); },
		code: function () { wrap( '`', '`', T.code ); },
		pre: function () { wrap( '```php\n', '\n```\n', '', true ); },
		link: function () {
			var url = window.prompt( T.link || 'URL', 'https://' );
			if ( url ) {
				wrap( '[', '](' + url + ')', T.linkText );
			}
		},
		wiki: function () { wrap( '[[', ']]', T.page ); },
		ul: function () { wrap( '- ', '', T.item, true ); },
		note: function () { wrap( '> [!NOTE]\n> ', '\n', T.note, true ); },
		table: function () { wrap( '| A | B |\n|---|---|\n| ', ' |  |\n', '1', true ); }
	};
	ed.querySelectorAll( '[data-md]' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			var f = actions[ b.getAttribute( 'data-md' ) ];
			if ( f ) {
				f();
			}
		} );
	} );
	ta.addEventListener( 'keydown', function ( e ) {
		if ( 'Tab' === e.key && ! e.shiftKey && ! e.ctrlKey && ! e.metaKey ) {
			e.preventDefault();
			ta.setRangeText( '\t', ta.selectionStart, ta.selectionEnd, 'end' );
		}
		if ( ( e.ctrlKey || e.metaKey ) && 'b' === e.key.toLowerCase() ) {
			e.preventDefault();
			actions.b();
		}
	} );
}() );
