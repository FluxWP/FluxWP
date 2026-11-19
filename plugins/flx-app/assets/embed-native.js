/*
 * Flux App mobile (1.0.2+): the site's own dialogs open as the phone's native dialogs.
 *
 * Loaded only inside the app's webview (flxapp_embed_native()). The page asks, the app answers:
 *   page → app  {type:"dialog", id, kind:"confirm"|"alert"|"prompt"|"choice"|"menu", title, message, ok, cancel, danger,
 *                value, placeholder, buttons:[{label, danger, cancel}]}
 *   app → page  {type:"flxapp:dialog", id, ok, value, index}
 *
 * - flxDialog.confirm/alert/prompt (flx-brand) → native alert; a typed confirmation ("type the name") → native prompt.
 * - flxDialog.toast → the app's toast.
 * - A plain confirmation window of a React screen (role="dialog" with only text and up to three buttons) is
 *   hidden and asked natively; the chosen button is clicked on the page. Windows with fields, lists or images
 *   stay on the page.
 * - A menu of a React screen (role="menu" with menu items, e.g. a file's ⋯ menu) opens as the app's action sheet.
 */
( function ( w, d ) {
	'use strict';
	var B = w.ReactNativeWebView;
	if ( ! B || w.FLXAppNative ) {
		return;
	}
	var seq = 0;
	var wait = new Map();

	function send( m ) {
		m.v = 1;
		try {
			B.postMessage( JSON.stringify( m ) );
		} catch ( e ) {}
	}
	function ask( m ) {
		return new Promise( function ( done ) {
			var id = 'd' + ++seq;
			wait.set( id, done );
			m.type = 'dialog';
			m.id = id;
			send( m );
		} );
	}
	function onMessage( e ) {
		var m = e && e.data;
		if ( 'string' === typeof m ) {
			try {
				m = JSON.parse( m );
			} catch ( x ) {
				return;
			}
		}
		if ( ! m || 'flxapp:dialog' !== m.type || 'string' !== typeof m.id ) {
			return;
		}
		var done = wait.get( m.id );
		if ( 'function' !== typeof done ) {
			return;
		}
		wait.delete( m.id );
		done( m );
	}
	w.addEventListener( 'message', onMessage );
	d.addEventListener( 'message', onMessage ); // Android

	function str( v, max ) {
		return String( null == v ? '' : v ).replace( /\s+/g, ' ' ).trim().slice( 0, max || 500 );
	}
	function norm( v ) {
		return str( v, 500 ).toLocaleLowerCase();
	}

	w.FLXAppNative = {
		v: 1,
		confirm: function ( o ) {
			o = 'string' === typeof o ? { message: o } : o || {};
			if ( o.type ) {
				return ask( { kind: 'prompt', title: str( o.title, 120 ), message: str( o.message ), ok: str( o.ok, 40 ), cancel: str( o.cancel, 40 ), danger: !! o.danger, placeholder: str( o.type, 120 ) } ).then( function ( r ) {
					return !! r.ok && norm( r.value ) === norm( o.type );
				} );
			}
			return ask( { kind: 'confirm', title: str( o.title, 120 ), message: str( o.message ), ok: str( o.ok, 40 ), cancel: str( o.cancel, 40 ), danger: !! o.danger } ).then( function ( r ) {
				return !! r.ok;
			} );
		},
		alert: function ( o ) {
			o = 'string' === typeof o ? { message: o } : o || {};
			return ask( { kind: 'alert', title: str( o.title, 120 ), message: str( o.message ), ok: str( o.ok, 40 ) } ).then( function () {} );
		},
		prompt: function ( o ) {
			o = 'string' === typeof o ? { message: o } : o || {};
			return ask( { kind: 'prompt', title: str( o.title || o.label, 120 ), message: str( o.message ), ok: str( o.ok, 40 ), cancel: str( o.cancel, 40 ), value: str( o.value, 500 ), placeholder: str( o.placeholder, 120 ) } ).then( function ( r ) {
				return r.ok ? String( null == r.value ? '' : r.value ) : null;
			} );
		},
		toast: function ( message, o ) {
			var t = ( o && o.type ) || 'ok';
			send( { type: 'toast', text: str( message, 200 ), tone: 'bad' === t ? 'error' : 'ok' } );
		}
	};

	/* flx-brand flxDialog: keep its API, answer natively. dialog.js may load before or after this file. */
	function patch() {
		var f = w.flxDialog;
		if ( ! f || f.flxappNative ) {
			return;
		}
		f.confirm = w.FLXAppNative.confirm;
		f.alert = w.FLXAppNative.alert;
		f.prompt = w.FLXAppNative.prompt;
		f.toast = w.FLXAppNative.toast;
		f.flxappNative = true;
	}
	patch();
	d.addEventListener( 'DOMContentLoaded', patch );
	w.addEventListener( 'load', patch );

	/* Plain confirmation windows of React screens. */
	var CANCEL = /^(cancel|close|no|vazgeç|iptal|kapat|hayır|не|отмена|закрыть|إلغاء|abbrechen|annuler|cancelar|annulla)$/i;
	var DANGER = /(delete|remove|revoke|reset|empty|sil|kaldır|boşalt|iptal et|sıfırla|удалить|حذف|löschen|supprimer|eliminar|elimina)/i;
	function plain( el ) {
		if ( el.closest( '.flx-dlg' ) || el.hasAttribute( 'data-flxapp-web' ) || el.querySelector( 'input:not([type=hidden]):not([type=checkbox]),select,textarea,img,svg image,table,ul li+li+li,[contenteditable]' ) ) {
			return null;
		}
		var buttons = [].slice.call( el.querySelectorAll( 'button,a.button,a[role=button]' ) ).filter( function ( b ) {
			return b.offsetParent !== null && str( b.textContent, 60 ) && ! /^[×✕x]$/i.test( str( b.textContent ) );
		} );
		if ( buttons.length < 1 || buttons.length > 3 ) {
			return null;
		}
		var head = el.querySelector( 'h1,h2,h3,.modal-title,[class*=title]' );
		var title = head ? str( head.textContent, 120 ) : str( el.getAttribute( 'aria-label' ), 120 );
		var clone = el.cloneNode( true );
		[].slice.call( clone.querySelectorAll( 'button,a.button,a[role=button],h1,h2,h3,.modal-title,[class*=title]' ) ).forEach( function ( n ) {
			n.remove();
		} );
		var message = str( clone.textContent, 600 );
		if ( ! title && ! message ) {
			return null;
		}
		return { title: title, message: message, buttons: buttons };
	}
	function boxOf( el ) {
		return el.closest( '.overlay,.flx-modal,.flx-overlay,[class*=overlay],[class*=backdrop]' ) || el;
	}
	/* Returns false when the window stays on the page. */
	function handle( el, prev ) {
		if ( el.flxappAsked ) {
			return true;
		}
		var p = plain( el );
		if ( ! p ) {
			return false;
		}
		el.flxappAsked = true;
		var box = boxOf( el );
		var list = p.buttons.map( function ( b ) {
			var label = str( b.textContent, 40 );
			return { label: label, cancel: CANCEL.test( label ), danger: /danger|delete|destructive/i.test( b.className ) || DANGER.test( label ) };
		} );
		ask( { kind: 'choice', title: p.title, message: p.message, buttons: list } ).then( function ( r ) {
			var i = 'number' === typeof r.index ? r.index : -1;
			var b = p.buttons[ i ] || p.buttons[ list.findIndex( function ( x ) {
				return x.cancel;
			} ) ];
			if ( b && d.contains( b ) ) {
				b.click();
			} else if ( d.contains( el ) ) {
				el.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );
				w.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );
			}
			// A window that stays open (e.g. it shows a result next) is shown again on the page.
			setTimeout( function () {
				if ( d.contains( box ) ) {
					box.style.visibility = prev;
				}
			}, 400 );
		} );
		return true;
	}
	/* role="menu": the app's action sheet; the chosen item is clicked, a sub menu is asked again. */
	function items( menu ) {
		return [].slice.call( menu.querySelectorAll( '[role=menuitem],[role=menuitemcheckbox],[role=menuitemradio]' ) ).filter( function ( b ) {
			return b.offsetParent !== null && ! b.disabled && 'true' !== b.getAttribute( 'aria-disabled' ) && str( b.textContent, 60 );
		} );
	}
	function closeMenu( menu ) {
		var t = menu.querySelector( 'button,[role=menuitem]' ) || menu;
		t.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );
		if ( d.contains( menu ) ) {
			d.body.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) );
		}
	}
	function handleMenu( menu, prev, seen ) {
		var list = items( menu ).filter( function ( b ) {
			return ! seen || seen.indexOf( b ) < 0;
		} );
		if ( ! list.length || list.length > 20 ) {
			return false;
		}
		var head = menu.getAttribute( 'aria-label' ) || '';
		ask( {
			kind: 'menu',
			title: str( head, 120 ),
			message: '',
			buttons: list.map( function ( b ) {
				return { label: str( b.textContent, 40 ), danger: /danger|delete|destructive/i.test( b.className ) || DANGER.test( str( b.textContent ) ) };
			} )
		} ).then( function ( r ) {
			var b = list[ 'number' === typeof r.index ? r.index : -1 ];
			if ( ! b || ! d.contains( b ) ) {
				closeMenu( menu );
				return;
			}
			b.click();
			setTimeout( function () {
				// Still open: the item opened a sub menu; ask for its items.
				if ( d.contains( menu ) && ! handleMenu( menu, prev, list ) ) {
					menu.style.visibility = prev;
				}
			}, 120 );
		} );
		return true;
	}
	function scan( root ) {
		if ( ! root.querySelectorAll ) {
			return;
		}
		var menus = root.matches && root.matches( '[role=menu]' ) ? [ root ] : [];
		menus.concat( [].slice.call( root.querySelectorAll( '[role=menu]' ) ) ).forEach( function ( menu ) {
			if ( menu.flxappAsked || menu.parentElement.closest( '[role=menu]' ) ) {
				return;
			}
			menu.flxappAsked = true;
			var prev = menu.style.visibility;
			menu.style.visibility = 'hidden';
			setTimeout( function () {
				if ( ! d.contains( menu ) || ! handleMenu( menu, prev ) ) {
					menu.style.visibility = prev;
				}
			}, 30 );
		} );
		var found = root.matches && root.matches( '[role=dialog],[role=alertdialog],dialog[open]' ) ? [ root ] : [];
		found = found.concat( [].slice.call( root.querySelectorAll( '[role=dialog],[role=alertdialog],dialog[open]' ) ) );
		found.forEach( function ( el ) {
			if ( el.closest( '.flx-dlg' ) || el.flxappAsked ) {
				return;
			}
			// Hidden at once (no flash), read after React's first render, shown again if it stays on the page.
			var box = boxOf( el );
			var prev = box.style.visibility;
			box.style.visibility = 'hidden';
			setTimeout( function () {
				if ( ! d.contains( el ) || ! handle( el, prev ) ) {
					box.style.visibility = prev;
				}
			}, 30 );
		} );
	}
	if ( w.MutationObserver ) {
		new MutationObserver( function ( list ) {
			list.forEach( function ( m ) {
				[].forEach.call( m.addedNodes, scan );
			} );
		} ).observe( d.documentElement, { childList: true, subtree: true } );
	}
} )( window, document );
