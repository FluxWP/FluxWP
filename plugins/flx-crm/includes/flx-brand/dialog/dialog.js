/*
 * FluxWP ortak modal ve bildirim (flx-brand dialog). Tarayıcının confirm/alert/prompt'u yerine; telefonda alttan açılır.
 *
 *   flxDialog.confirm({ title, message, ok, cancel, danger, type: 'Ad Soyad' }) -> Promise<boolean>
 *   flxDialog.alert({ title, message })                                         -> Promise<void>
 *   flxDialog.prompt({ title, message, value, placeholder, label })             -> Promise<string|null>
 *   flxDialog.toast('Kaydedildi.', { type: 'ok'|'bad'|'warn'|'info', timeout: 4000 })
 *
 * Bildirimsel: <form data-flx-confirm="İleti"> ya da bir düğme/bağlantıda data-flx-confirm. İsteğe bağlı
 * data-flx-confirm-title, data-flx-confirm-ok, data-flx-confirm-danger, data-flx-confirm-type (bu metin aynen
 * yazılmadan onay düğmesi açılmaz). Onaylanınca form aynı düğmeyle yeniden gönderilir (submit olayı yine çalışır).
 * Metinler: window.FLX_DIALOG.i18n (PHP flx_dialog_strings(), filtre flx_dialog_strings).
 */
( function () {
	'use strict';
	if ( window.flxDialog && window.flxDialog.v ) {
		return;
	}
	var C = window.FLX_DIALOG || {};
	var T = C.i18n || {};
	var ICON = {
		info: '<path d="M12 8h.01M11 12h1v5h1"/><circle cx="12" cy="12" r="9"/>',
		warn: '<path d="M12 4 2.5 20h19L12 4z"/><path d="M12 10v4M12 17h.01"/>',
		trash: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
		ask: '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6M12 17h.01"/>'
	};
	var queue = Promise.resolve();

	function norm( v ) {
		return String( v == null ? '' : v ).replace( /\s+/g, ' ' ).trim().toLocaleLowerCase();
	}
	function h( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( text != null ) {
			n.textContent = text;
		}
		return n;
	}

	/* Tek pencere: kind = confirm | alert | prompt. Aynı anda biri açık; diğerleri sırada bekler. */
	function open( kind, o ) {
		o = 'string' === typeof o ? { message: o } : ( o || {} );
		var run = function () {
			return new Promise( function ( done ) {
				var last = document.activeElement;
				var d = h( 'dialog', 'flx-dlg' + ( o.danger ? ' flx-dlg--danger' : '' ) );
				d.setAttribute( 'aria-modal', 'true' );
				var body = h( 'div', 'flx-dlg__body' );
				var ic = h( 'span', 'flx-dlg__icon' );
				ic.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true">' + ICON[ o.icon || ( o.danger ? ( o.type ? 'trash' : 'warn' ) : ( 'alert' === kind ? 'info' : 'ask' ) ) ] + '</svg>';
				var main = h( 'div', 'flx-dlg__main' );
				var tid = 'flx-dlg-t' + Date.now();
				var title = h( 'h2', 'flx-dlg__title', o.title || ( 'alert' === kind ? T.notice : T.confirm ) );
				title.id = tid;
				d.setAttribute( 'aria-labelledby', tid );
				if ( o.message ) {
					var msg = h( 'p', 'flx-dlg__msg', o.message );
					msg.id = tid + 'm';
					d.setAttribute( 'aria-describedby', msg.id );
					main.appendChild( msg );
				}
				var input = null;
				if ( 'prompt' === kind || o.type ) {
					var f = h( 'div', 'flx-dlg__field' );
					var lab = h( 'label' );
					input = h( 'input' );
					input.id = tid + 'i';
					lab.htmlFor = input.id;
					if ( o.type ) {
						var parts = String( T.typeToConfirm || 'To confirm, type %s' ).split( '%s' );
						lab.appendChild( document.createTextNode( parts[ 0 ] || '' ) );
						lab.appendChild( h( 'b', '', o.type ) );
						lab.appendChild( document.createTextNode( parts[ 1 ] || '' ) );
						input.autocomplete = 'off';
						input.spellcheck = false;
					} else {
						lab.textContent = o.label || '';
						input.value = o.value || '';
						input.placeholder = o.placeholder || '';
					}
					if ( lab.textContent ) {
						f.appendChild( lab );
					}
					f.appendChild( input );
					main.appendChild( f );
				}
				/* Başlık şeridi (ikon + başlık + kapat) ayrı; içerik altta tam genişlik. */
				var head = h( 'div', 'flx-dlg__head' );
				head.appendChild( ic );
				head.appendChild( title );
				var x = h( 'button', 'flx-dlg__x', '×' );
				x.type = 'button';
				x.setAttribute( 'aria-label', T.close || 'Close' );
				head.appendChild( x );
				d.appendChild( head );
				if ( main.childNodes.length ) {
					body.appendChild( main );
				}
				var foot = h( 'div', 'flx-dlg__foot' );
				var no = null;
				if ( 'alert' !== kind ) {
					no = h( 'button', 'flx-dlg__btn', o.cancel || T.cancel || 'Cancel' );
					no.type = 'button';
					foot.appendChild( no );
				}
				var ok = h( 'button', 'flx-dlg__btn flx-dlg__btn--ok', o.ok || ( 'alert' === kind ? T.close || 'OK' : T.ok || 'OK' ) );
				ok.type = 'button';
				foot.appendChild( ok );
				if ( body.childNodes.length ) {
					d.appendChild( body );
				}
				d.appendChild( foot );
				document.body.appendChild( d );

				var settled = false;
				function finish( val ) {
					if ( settled ) {
						return;
					}
					settled = true;
					if ( d.open ) {
						d.close();
					}
					d.remove();
					if ( last && last.focus && last.isConnected ) {
						try {
							last.focus( { preventScroll: true } );
						} catch ( e ) {}
					}
					done( val );
				}
				function check() {
					if ( o.type ) {
						ok.disabled = norm( input.value ) !== norm( o.type );
					}
				}
				if ( input ) {
					input.addEventListener( 'input', check );
					input.addEventListener( 'keydown', function ( e ) {
						if ( 'Enter' === e.key && ! ok.disabled ) {
							e.preventDefault();
							ok.click();
						}
					} );
				}
				check();
				ok.addEventListener( 'click', function () {
					finish( 'prompt' === kind ? input.value : 'confirm' === kind ? true : undefined );
				} );
				x.addEventListener( 'click', function () {
					finish( 'prompt' === kind ? null : 'confirm' === kind ? false : undefined );
				} );
				if ( no ) {
					no.addEventListener( 'click', function () {
						finish( 'prompt' === kind ? null : false );
					} );
				}
				/* Esc ve arka plana tıklama = vazgeç */
				d.addEventListener( 'cancel', function ( e ) {
					e.preventDefault();
					finish( 'prompt' === kind ? null : 'confirm' === kind ? false : undefined );
				} );
				d.addEventListener( 'click', function ( e ) {
					if ( e.target === d ) {
						var r = d.getBoundingClientRect();
						if ( e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom ) {
							finish( 'prompt' === kind ? null : 'confirm' === kind ? false : undefined );
						}
					}
				} );
				if ( d.showModal ) {
					d.showModal();
				} else {
					d.setAttribute( 'open', '' );
				}
				( input || ( o.danger && no ) || ok ).focus();
			} );
		};
		var p = queue.then( run, run );
		queue = p.then( function () {}, function () {} );
		return p;
	}

	var box = null;
	function toast( message, o ) {
		o = o || {};
		if ( ! box || ! box.isConnected ) {
			box = h( 'div', 'flx-toasts' );
			box.setAttribute( 'role', 'status' );
			box.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( box );
		}
		var t = h( 'div', 'flx-toast flx-toast--' + ( o.type || 'info' ) );
		if ( 'bad' === o.type ) {
			t.setAttribute( 'role', 'alert' );
		}
		t.appendChild( h( 'span', 'flx-toast__msg', message ) );
		var x = h( 'button', 'flx-toast__x', '×' );
		x.type = 'button';
		x.setAttribute( 'aria-label', T.close || 'Close' );
		t.appendChild( x );
		box.appendChild( t );
		var gone = function () {
			t.classList.add( 'is-out' );
			setTimeout( function () {
				t.remove();
			}, 220 );
		};
		x.addEventListener( 'click', gone );
		var ms = undefined === o.timeout ? ( 'bad' === o.type ? 8000 : 4000 ) : o.timeout;
		if ( ms ) {
			setTimeout( gone, ms );
		}
		return t;
	}

	function opts( el ) {
		return {
			message: el.getAttribute( 'data-flx-confirm' ) || T.sure,
			title: el.getAttribute( 'data-flx-confirm-title' ) || '',
			ok: el.getAttribute( 'data-flx-confirm-ok' ) || '',
			danger: el.hasAttribute( 'data-flx-confirm-danger' ),
			type: el.getAttribute( 'data-flx-confirm-type' ) || ''
		};
	}

	/* Bildirimsel onay: formlar. Önce yakalanır, onaylanınca aynı düğmeyle yeniden gönderilir. */
	document.addEventListener(
		'submit',
		function ( e ) {
			var f = e.target;
			if ( ! f.matches || ! f.matches( 'form[data-flx-confirm]' ) ) {
				return;
			}
			if ( f.flxConfirmed ) {
				f.flxConfirmed = false;
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();
			var sub = e.submitter || null;
			open( 'confirm', opts( f ) ).then( function ( yes ) {
				if ( ! yes ) {
					return;
				}
				f.flxConfirmed = true;
				if ( f.requestSubmit ) {
					f.requestSubmit( sub && sub.form === f ? sub : undefined );
				} else {
					f.submit();
				}
			} );
		},
		true
	);
	/* Bildirimsel onay: form dışı düğme ve bağlantılar. */
	document.addEventListener(
		'click',
		function ( e ) {
			var el = e.target.closest ? e.target.closest( 'a[data-flx-confirm], button[data-flx-confirm], input[type=submit][data-flx-confirm]' ) : null;
			if ( ! el || el.flxConfirmed ) {
				if ( el ) {
					el.flxConfirmed = false;
				}
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();
			open( 'confirm', opts( el ) ).then( function ( yes ) {
				if ( yes ) {
					el.flxConfirmed = true;
					el.click();
				}
			} );
		},
		true
	);

	window.flxDialog = {
		v: 1,
		confirm: function ( o ) {
			return open( 'confirm', o );
		},
		alert: function ( o ) {
			return open( 'alert', o );
		},
		prompt: function ( o ) {
			return open( 'prompt', o );
		},
		toast: toast
	};
} )();
