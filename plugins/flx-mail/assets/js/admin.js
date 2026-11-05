/* Flux Mail – yönetim ekranları */
( function () {
	'use strict';
	/* Yalnız http(s) veya site içi adres; sonuç sabit bir önekle başlar (javascript:, data: vb. düşer). */
	var flxiSafeUrl = function (u) {
		u = String(u == null ? '' : u).trim();
		var l = u.toLowerCase();
		if (!(l.indexOf('https://') === 0 || l.indexOf('http://') === 0 || (u.charAt(0) === '/' && u.charAt(1) !== '/' && u.charAt(1) !== '\\'))) return '';
		// Normalised with encodeURI: quotes, spaces and angle brackets are escaped as well.
		try { return encodeURI(decodeURI(u)); } catch (e) { return ''; }
	};

	var C = window.FLXI || { rest: '', nonce: '', i18n: {} };
	var T = C.i18n || {};
	var $ = function ( s, r ) { return ( r || document ).querySelector( s ); };
	var $$ = function ( s, r ) { return Array.prototype.slice.call( ( r || document ).querySelectorAll( s ) ); };
	var fmt = function ( s, v ) { return String( s || '' ).replace( '%s', v ); };

	function api( path, data, method ) {
		return fetch( C.rest + path, {
			method: method || 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
			body: method === 'GET' ? undefined : JSON.stringify( data || {} )
		} ).then( function ( r ) {
			return r.json().then( function ( j ) {
				if ( ! r.ok ) {
					throw new Error( ( j && j.message ) || r.statusText );
				}
				return j;
			} );
		} );
	}

	function status( el, text, kind ) {
		if ( ! el ) {
			return;
		}
		el.textContent = text || '';
		el.className = 'flxi-status' + ( kind ? ' is-' + kind : '' );
	}

	function debounce( fn, ms ) {
		var t;
		return function () {
			clearTimeout( t );
			t = setTimeout( fn, ms );
		};
	}

	/* ---------------- Önizleme (iframe) ---------------- */

	function Preview( root, collect ) {
		var frame = $( '[data-preview-frame]', root );
		var subj = $( '[data-preview-subject]', root );
		if ( ! frame ) {
			return null;
		}
		var wrap = frame.parentNode;
		var dark = false;
		var html = '';
		var subject = '';
		var seq = 0;

		function paint() {
			var out = html;
			if ( dark ) {
				// E-postanın koyu mod kurallarını her zaman uygula
				out = out.replace( /@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)/gi, '@media all' );
			}
			var doc = frame.contentDocument;
			if ( ! doc ) {
				frame.srcdoc = out;
				return;
			}
			var y = doc.scrollingElement ? doc.scrollingElement.scrollTop : 0;
			doc.open();
			doc.write( out );
			doc.close();
			fit();
			if ( y && doc.scrollingElement ) {
				doc.scrollingElement.scrollTop = y;
			}
		}

		function fit() {
			var doc = frame.contentDocument;
			if ( ! doc || ! doc.documentElement ) {
				return;
			}
			var h = Math.max( 420, doc.documentElement.scrollHeight );
			frame.style.height = h + 'px';
			// Görseller yüklendikçe yeniden ölç
			$$( 'img', doc ).forEach( function ( im ) {
				if ( ! im.complete ) {
					im.addEventListener( 'load', function () {
						frame.style.height = Math.max( 420, doc.documentElement.scrollHeight ) + 'px';
					} );
				}
			} );
		}

		function load() {
			var my = ++seq;
			root.classList.add( 'is-loading' );
			api( 'preview', collect() ).then( function ( r ) {
				if ( my !== seq ) {
					return;
				}
				html = r.html || '';
				subject = r.subject || '';
				if ( subj ) {
					subj.textContent = r.subject || '—';
				}
				paint();
			} ).catch( function ( e ) {
				if ( subj ) {
					subj.textContent = T.error + ': ' + e.message;
				}
			} ).then( function () {
				root.classList.remove( 'is-loading' );
			} );
		}

		$$( '[data-device]', root ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var d = b.getAttribute( 'data-device' );
				if ( d === 'dark' ) {
					dark = ! dark;
					b.classList.toggle( 'is-active', dark );
					b.setAttribute( 'aria-pressed', dark ? 'true' : 'false' );
					wrap.classList.toggle( 'is-dark', dark );
					paint();
					return;
				}
				$$( '[data-device="desktop"],[data-device="mobile"]', root ).forEach( function ( o ) {
					var on = o === b;
					o.classList.toggle( 'is-active', on );
					o.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				} );
				wrap.classList.toggle( 'is-mobile', d === 'mobile' );
				setTimeout( fit, 60 );
			} );
		} );

		var seg = $( '.flxi-seg', root );
		if ( seg && $( '[data-device]', seg ) ) {
			var pdfBtn = document.createElement( 'button' );
			pdfBtn.type = 'button';
			pdfBtn.className = 'button flxi-pdf-btn';
			pdfBtn.innerHTML = '<span class="dashicons dashicons-pdf" aria-hidden="true"></span>';
			pdfBtn.appendChild( document.createTextNode( T.pdf || 'PDF' ) );
			seg.parentNode.insertBefore( pdfBtn, seg.nextSibling );
			pdfBtn.addEventListener( 'click', function () {
				if ( ! html ) {
					return;
				}
				var out = dark ? html.replace( /@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)/gi, '@media all' ) : html;
				if ( ! C.pdf ) {
					printHtml( out, subject );
					return;
				}
				pdfBtn.disabled = true;
				pdfBtn.lastChild.textContent = T.pdfWait;
				var data = collect();
				data.dark = dark;
				api( 'preview-pdf', data ).then( function ( r ) {
					var bin = atob( r.data );
					var bytes = new Uint8Array( bin.length );
					for ( var i = 0; i < bin.length; i++ ) {
						bytes[ i ] = bin.charCodeAt( i );
					}
					var url = URL.createObjectURL( new Blob( [ bytes ], { type: 'application/pdf' } ) );
					var a = document.createElement( 'a' );
					a.href = url;
					a.download = r.name || 'e-posta.pdf';
					document.body.appendChild( a );
					a.click();
					a.remove();
					setTimeout( function () { URL.revokeObjectURL( url ); }, 4000 );
				} ).catch( function () {
					// Sunucudaki çevirici çalışmazsa tarayıcının "PDF olarak kaydet"i.
					printHtml( out, subject );
				} ).then( function () {
					pdfBtn.disabled = false;
					pdfBtn.lastChild.textContent = T.pdf;
				} );
			} );
		}

		return { load: load, later: debounce( load, 450 ) };
	}

	/** HTML'i görünmez bir çerçevede yazdırır; tarayıcıda "PDF olarak kaydet" seçilir. */
	function printHtml( html, title ) {
		var f = document.createElement( 'iframe' );
		f.setAttribute( 'aria-hidden', 'true' );
		f.style.cssText = 'position:fixed;right:0;bottom:0;width:820px;height:10px;border:0;opacity:0;pointer-events:none';
		document.body.appendChild( f );
		var d = f.contentDocument;
		var t = String( title || '' ).replace( /[<>&]/g, '' );
		var head = '<title>' + t + '</title><style>@page{margin:0}html,body{-webkit-print-color-adjust:exact;print-color-adjust:exact}</style>';
		d.open();
		d.write( /<head[^>]*>/i.test( html ) ? html.replace( /<head([^>]*)>/i, '<head$1>' + head ) : head + html );
		d.close();
		var go = function () {
			f.contentWindow.focus();
			f.contentWindow.print();
			setTimeout( function () { f.remove(); }, 60000 );
		};
		var imgs = $$( 'img', d ).filter( function ( im ) { return ! im.complete; } );
		var left = imgs.length;
		if ( ! left ) {
			setTimeout( go, 150 );
			return;
		}
		var done = function () {
			if ( --left === 0 ) {
				go();
			}
		};
		imgs.forEach( function ( im ) {
			im.addEventListener( 'load', done );
			im.addEventListener( 'error', done );
		} );
		setTimeout( function () { if ( left > 0 ) { left = 1; done(); } }, 4000 );
	}

	/* İçerik editörü (TinyMCE ya da metin sekmesi) */
	function editorContent() {
		if ( window.tinymce ) {
			var ed = window.tinymce.get( 'flxi_body' );
			if ( ed && ! ed.isHidden() ) {
				return ed.getContent();
			}
		}
		var ta = document.getElementById( 'flxi_body' );
		return ta ? ta.value : '';
	}

	function onEditorChange( cb ) {
		var ta = document.getElementById( 'flxi_body' );
		if ( ta ) {
			ta.addEventListener( 'input', cb );
		}
		var hook = function ( ed ) {
			if ( ed && ed.id === 'flxi_body' ) {
				ed.on( 'input change keyup NodeChange', cb );
			}
		};
		if ( window.tinymce ) {
			var ed = window.tinymce.get( 'flxi_body' );
			if ( ed ) {
				hook( ed );
			} else {
				window.tinymce.on( 'AddEditor', function ( e ) {
					hook( e.editor );
				} );
			}
		}
		if ( window.jQuery ) {
			window.jQuery( document ).on( 'tinymce-editor-init', function ( ev, ed ) {
				hook( ed );
			} );
		}
	}

	function formFields( form ) {
		var out = {};
		$$( '[data-preview]', form ).forEach( function ( el ) {
			if ( ! el.name ) {
				return;
			}
			out[ el.name ] = el.type === 'checkbox' ? ( el.checked ? 1 : 0 ) : el.value;
		} );
		out.body = editorContent();
		return out;
	}

	/* Değişken çipleri: son odaklanan alana ekle */
	function setupChips( form, after ) {
		var last = null;
		$$( '[data-insert-target]', form ).forEach( function ( el ) {
			el.addEventListener( 'focus', function () {
				last = el;
			} );
		} );
		var mce = false;
		if ( window.jQuery ) {
			window.jQuery( document ).on( 'tinymce-editor-init', function ( ev, ed ) {
				if ( ed.id === 'flxi_body' ) {
					ed.on( 'focus', function () {
						last = null;
						mce = true;
					} );
				}
			} );
		}
		var ta = document.getElementById( 'flxi_body' );
		if ( ta ) {
			ta.addEventListener( 'focus', function () {
				last = ta;
				mce = false;
			} );
		}
		$$( '[data-var]', form ).forEach( function ( chip ) {
			chip.addEventListener( 'mousedown', function ( e ) {
				e.preventDefault(); // odağı kaybetme
			} );
			chip.addEventListener( 'click', function () {
				var v = chip.getAttribute( 'data-var' );
				var ed = window.tinymce && window.tinymce.get( 'flxi_body' );
				if ( ! last && ed && ! ed.isHidden() ) {
					ed.focus();
					ed.insertContent( v );
				} else {
					var t = last || ta;
					if ( ! t ) {
						return;
					}
					var s = t.selectionStart || t.value.length;
					var e2 = t.selectionEnd || s;
					t.value = t.value.slice( 0, s ) + v + t.value.slice( e2 );
					t.focus();
					t.setSelectionRange( s + v.length, s + v.length );
					t.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				}
				chip.classList.add( 'is-flash' );
				setTimeout( function () {
					chip.classList.remove( 'is-flash' );
				}, 500 );
				if ( after ) {
					after();
				}
			} );
		} );
		return mce;
	}

	/* ---------------- Şablon düzenleyici ---------------- */

	var editor = $( '#flxi-editor' );
	if ( editor ) {
		var key = editor.getAttribute( 'data-key' );
		var pv = Preview( editor, function () {
			return { key: key, fields: formFields( editor ) };
		} );
		if ( pv ) {
			$$( '[data-preview]', editor ).forEach( function ( el ) {
				el.addEventListener( 'input', pv.later );
				el.addEventListener( 'change', pv.later );
			} );
			onEditorChange( pv.later );
			setupChips( editor, pv.later );
			pv.load();
		}
		var testBtn = $( '[data-test-template]', editor );
		if ( testBtn ) {
			testBtn.addEventListener( 'click', function () {
				var st = $( '.flxi-status', editor );
				status( st, T.sending );
				testBtn.disabled = true;
				// Önce kaydetmeden, kaydedilmemiş içerikle denemek için önizleme filtresi kullanılmaz;
				// test, kayıtlı içerikle gider.
				api( 'test-email', { key: key } ).then( function ( r ) {
					status( st, r.ok ? fmt( T.sentTo, r.to ) : T.failed + ( r.error ? ': ' + r.error : '' ), r.ok ? 'ok' : 'err' );
				} ).catch( function ( e ) {
					status( st, T.failed + ': ' + e.message, 'err' );
				} ).then( function () {
					testBtn.disabled = false;
				} );
			} );
		}
	}

	/* ---------------- Şablon listesi ---------------- */

	$$( '[data-autosubmit]' ).forEach( function ( el ) {
		el.addEventListener( 'change', function () {
			var row = el.closest( '.flxi-row' );
			if ( row ) {
				row.classList.toggle( 'is-off', ! el.checked );
				row.classList.add( 'is-saving' );
			}
			var f = el.form;
			f.submit();
		} );
	} );

	var search = $( '#flxi-tpl-search' );
	if ( search ) {
		search.addEventListener( 'input', function () {
			var q = search.value.trim().toLocaleLowerCase( 'tr' );
			$$( '[data-group]' ).forEach( function ( g ) {
				var any = false;
				$$( '[data-search]', g ).forEach( function ( r ) {
					var hit = ! q || r.getAttribute( 'data-search' ).indexOf( q ) !== -1;
					r.hidden = ! hit;
					any = any || hit;
				} );
				g.hidden = ! any;
			} );
		} );
	}

	/* ---------------- Gönder ---------------- */

	var send = $( '#flxi-send' );
	if ( send ) {
		var channel = function () {
			var c = $( 'input[name="channel"]:checked', send );
			return c ? c.value : 'email';
		};
		var spv = Preview( $( '.flxi-preview' ) || document.body, function () {
			var f = formFields( send );
			return { key: '', fields: f };
		} );
		var applyChannel = function () {
			var ch = channel();
			$$( '[data-for-channel]' ).forEach( function ( el ) {
				var list = el.getAttribute( 'data-for-channel' ).split( ' ' );
				el.hidden = list.indexOf( ch ) === -1;
			} );
			$$( '[data-channel-seg] label' ).forEach( function ( l ) {
				var i = $( 'input', l );
				l.classList.toggle( 'is-active', !! ( i && i.checked ) );
			} );
			var tf = $( '[data-test-free]', send );
			if ( tf ) {
				tf.hidden = ch !== 'email';
			}
			countRecipients();
		};
		var countEl = $( '[data-recipient-count]', send );
		var countRecipients = debounce( function () {
			if ( ! countEl ) {
				return;
			}
			var sources = $$( 'input[name="sources[]"]:checked', send ).map( function ( i ) {
				return i.value;
			} );
			var manual = ( $( 'textarea[name="manual"]', send ) || {} ).value || '';
			if ( ! sources.length && ! manual.trim() ) {
				countEl.textContent = '';
				return;
			}
			countEl.textContent = T.checking;
			api( 'recipients', { sources: sources, manual: manual, channel: channel(), sender: ( $( '[name="sender"]:checked', send ) || $( '[name="sender"]', send ) || {} ).value || 'info' } ).then( function ( r ) {
				countEl.textContent = r.count ? fmt( T.recipients, r.count ) + ( r.sample && r.sample.length ? ' · ' + r.sample.map( function ( x ) {
					return x.name ? x.name : x.to;
				} ).join( ', ' ) + ( r.count > r.sample.length ? '…' : '' ) : '' ) : T.noRecipients;
				if ( r.quota_note ) {
					countEl.textContent += ' · ' + r.quota_note;
				}
				countEl.classList.toggle( 'is-empty', ! r.count );
			} ).catch( function () {
				countEl.textContent = '';
			} );
		}, 500 );

		$$( 'input[name="channel"]', send ).forEach( function ( r ) {
			r.addEventListener( 'change', applyChannel );
		} );
		$$( '[data-recipients]', send ).forEach( function ( el ) {
			el.addEventListener( 'input', countRecipients );
			el.addEventListener( 'change', countRecipients );
		} );
		if ( spv ) {
			$$( '[data-preview]', send ).forEach( function ( el ) {
				el.addEventListener( 'input', spv.later );
				el.addEventListener( 'change', spv.later );
			} );
			onEditorChange( spv.later );
			setupChips( send, spv.later );
			spv.load();
		} else {
			setupChips( send );
		}
		applyChannel();

		send.addEventListener( 'submit', function ( e ) {
			var b = e.submitter || $( 'button[type="submit"]', send );
			if ( window.tinymce ) {
				window.tinymce.triggerSave();
			}
			if ( b && b.getAttribute( 'data-confirm' ) && ! window.confirm( b.getAttribute( 'data-confirm' ) ) ) {
				e.preventDefault();
			}
		} );

		var free = $( '[data-test-free]', send );
		if ( free ) {
			free.addEventListener( 'click', function () {
				var st = free.parentNode.querySelector( '.flxi-status' );
				var f = formFields( send );
				status( st, T.sending );
				free.disabled = true;
				// Gönderim ekranında test: kayıt ekranındaki serbest önizleme ile aynı içerik, alıcı = ben
				api( 'test-email', { key: '', free: f, sender: f.sender } ).then( function ( r ) {
					status( st, r.ok ? fmt( T.sentTo, r.to ) : T.failed + ( r.error ? ': ' + r.error : '' ), r.ok ? 'ok' : 'err' );
				} ).catch( function ( er ) {
					status( st, T.failed + ': ' + er.message, 'err' );
				} ).then( function () {
					free.disabled = false;
				} );
			} );
		}
	}

	/* Toplu gönderim ilerlemesi: açık sekme kuyruğu da iter (cron'a ek) */
	var prog = $( '.flxi-progress[data-campaign]' );
	if ( prog && prog.getAttribute( 'data-open' ) === '1' ) {
		var cid = parseInt( prog.getAttribute( 'data-campaign' ), 10 );
		var txt = $( '[data-progress-text]', prog );
		var bar = $( '.flxi-bar span', prog );
		var tpl = txt ? txt.textContent : '';
		var tick = function () {
			api( 'queue/run', { campaign: cid } ).then( function ( r ) {
				var c = r.campaign;
				if ( ! c ) {
					return;
				}
				var done = ( +c.sent ) + ( +c.failed ) + ( +c.skipped );
				var pct = c.total ? Math.round( done * 100 / c.total ) : 100;
				if ( bar ) {
					bar.style.width = pct + '%';
				}
				if ( txt ) {
					// "%1$s / %2$s … %3$s" kalıbındaki ilk üç sayıyı güncelle
					var n = [ c.sent, c.total, c.failed ];
					var i = 0;
					txt.textContent = tpl.replace( /[\d.,]+/g, function ( m ) {
						return i < 3 ? String( n[ i++ ] ) : m;
					} );
				}
				var note = $( '[data-quota-paused]', prog );
				if ( r.paused_until && note ) {
					note.textContent = r.paused_text || '';
					note.hidden = false;
				}
				if ( r.paused_until && ( c.status === 'running' || c.status === 'queued' ) ) {
					// Sınır doldu: sayfa açık kalsa da sunucuyu yormadan seyrek kontrol et.
					setTimeout( tick, 300000 );
				} else if ( c.status === 'running' || c.status === 'queued' ) {
					setTimeout( tick, 1500 );
				} else {
					prog.classList.add( 'is-done' );
					setTimeout( function () {
						window.location.reload();
					}, 900 );
				}
			} ).catch( function () {
				setTimeout( tick, 5000 );
			} );
		};
		tick();
	}

	/* ---------------- Kayıtlar ---------------- */

	var dlg = $( '#flxi-log-dialog' );
	if ( dlg ) {
		var openLog = function ( id ) {
			var meta = $( '[data-log-meta]', dlg );
			var frame = $( '[data-log-frame]', dlg );
			meta.innerHTML = '';
			$( '#flxi-log-title', dlg ).textContent = '…';
			if ( dlg.showModal ) {
				dlg.showModal();
			} else {
				dlg.setAttribute( 'open', '' );
			}
			api( 'log/' + id, null, 'GET' ).then( function ( r ) {
				$( '#flxi-log-title', dlg ).textContent = r.subject || r.recipient;
				var rows = [
					[ 'To', r.recipient ],
					[ 'From', r.sender ],
					[ 'Status', r.status + ( r.error ? ' · ' + r.error : '' ) ],
					[ 'Mailer', r.mailer ],
					[ 'Template', r.template ],
					[ 'Source', r.source ],
					[ 'Time', r.created_at + ' UTC' ]
				];
				rows.forEach( function ( p ) {
					if ( ! p[ 1 ] ) {
						return;
					}
					var dt = document.createElement( 'dt' );
					var dd = document.createElement( 'dd' );
					dt.textContent = p[ 0 ];
					dd.textContent = p[ 1 ];
					meta.appendChild( dt );
					meta.appendChild( dd );
				} );
				frame.srcdoc = r.body || '<p style="font-family:sans-serif;color:#888;margin:16px">—</p>';
			} ).catch( function ( e ) {
				$( '#flxi-log-title', dlg ).textContent = T.error + ': ' + e.message;
			} );
		};
		$$( '.flxi-logrow' ).forEach( function ( row ) {
			row.addEventListener( 'click', function () {
				openLog( row.getAttribute( 'data-log' ) );
			} );
			row.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					openLog( row.getAttribute( 'data-log' ) );
				}
			} );
		} );
		$( '[data-close]', dlg ).addEventListener( 'click', function () {
			dlg.close ? dlg.close() : dlg.removeAttribute( 'open' );
		} );
		dlg.addEventListener( 'click', function ( e ) {
			if ( e.target === dlg && dlg.close ) {
				dlg.close();
			}
		} );
	}

	/* ---------------- Bağlantılar ---------------- */

	/* Test penceresi: alıcı, senaryolar (metin/resim/ses/belge/şablon), önizleme, sonuçlar */
	var td = document.getElementById( 'flxi-test-dialog' );
	if ( td ) {
		var TT = T.test || {}, SM = T.samples || {};
		var tbody = $( '[data-test-body]', td );
		var th = function ( v ) {
			var d = document.createElement( 'div' );
			d.textContent = v == null ? '' : String( v );
			return d.innerHTML;
		};
		var ICON = {
			text: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H8l-4 4z"/></svg>',
			image: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="M4 18l5-5 4 4 3-3 4 4"/></svg>',
			audio: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>',
			document: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/></svg>',
			template: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>'
		};
		var ts = { id: 0, type: '', scen: [], sel: {}, tpls: null, tpl: null };
		var lsGet = function ( k ) {
			try {
				return localStorage.getItem( k ) || '';
			} catch ( e ) {
				return '';
			}
		};
		var lsSet = function ( k, v ) {
			try {
				localStorage.setItem( k, v );
			} catch ( e ) {}
		};
		var clock = function () {
			var d = new Date();
			return ( '0' + d.getHours() ).slice( -2 ) + ':' + ( '0' + d.getMinutes() ).slice( -2 );
		};
		var tplText = function () {
			if ( ! ts.tpl ) {
				return '';
			}
			var body = ts.tpl.body || ts.tpl.name;
			$$( '[data-tpl-param]', tbody ).forEach( function ( inp, i ) {
				body = body.split( '{{' + ( i + 1 ) + '}}' ).join( inp.value || '{{' + ( i + 1 ) + '}}' );
			} );
			return body;
		};
		var bubble = function ( k ) {
			var txt = ( $( '[data-test-text]', tbody ) || {} ).value || '';
			var cap = ( $( '[data-test-cap]', tbody ) || {} ).value || '';
			var meta = '<span class="flxi-bubble__meta">' + clock() + ' <span data-tick></span></span>';
			var inner = '';
			if ( k === 'text' ) {
				inner = '<div class="flxi-bubble__text">' + th( txt ) + '</div>';
			} else if ( k === 'image' ) {
				inner = '<img src="' + th( SM.image ) + '" alt="" class="flxi-bubble__img">' + ( cap ? '<div class="flxi-bubble__text">' + th( cap ) + '</div>' : '' );
			} else if ( k === 'audio' ) {
				inner = '<audio controls preload="none" src="' + th( SM.audio ) + '"></audio>';
			} else if ( k === 'document' ) {
				inner = '<div class="flxi-bubble__doc">' + ICON.document + '<div><strong>' + th( TT.pdfName ) + '</strong><small>' + th( TT.pdfMeta ) + '</small></div></div>' + ( cap ? '<div class="flxi-bubble__text">' + th( cap ) + '</div>' : '' );
			} else if ( k === 'template' ) {
				inner = '<div class="flxi-bubble__text">' + ( ts.tpl ? th( tplText() ) : '<em>' + th( TT.tplPick ) + '</em>' ) + '</div>';
			}
			return '<div class="flxi-bubble flxi-bubble--' + th( k ) + '" data-bubble="' + th( k ) + '">' + inner + meta + '<div class="flxi-bubble__err" data-err hidden></div></div>';
		};
		var tpreview = function () {
			var box = $( '[data-test-preview]', tbody );
			var list = ts.scen.filter( function ( k ) {
				return ts.sel[ k ];
			} );
			box.innerHTML = list.length ? list.map( bubble ).join( '' ) : '<p class="flxi-muted">' + th( TT.needOne ) + '</p>';
			$( '[data-test-cap-row]', tbody ).hidden = ! ( ts.sel.image || ts.sel.document );
			var tr = $( '[data-test-text-row]', tbody );
			if ( tr ) {
				tr.hidden = ! ts.sel.text;
			}
			var tp = $( '[data-test-tpl-row]', tbody );
			if ( tp ) {
				tp.hidden = ! ts.sel.template;
			}
			var go = $( '[data-test-send]', tbody );
			go.textContent = list.length > 1 ? TT.sendN.replace( '%d', list.length ) : TT.send;
			go.disabled = ! list.length;
		};
		var loadTpls = function () {
			var row = $( '[data-test-tpl-row]', tbody );
			if ( ! row || ts.tpls ) {
				return;
			}
			var sel = $( 'select', row ), st = $( '.flxi-status', row );
			status( st, TT.tplLoad );
			api( 'wacloud/' + ts.id + '/templates', null, 'GET' ).then( function ( list ) {
				ts.tpls = list.filter( function ( t ) {
					return t.status === 'approved';
				} );
				status( st, ts.tpls.length ? '' : TT.tplNone );
				sel.innerHTML = '<option value="">' + th( TT.tplPick ) + '</option>' + ts.tpls.map( function ( t, i ) {
					return '<option value="' + i + '">' + th( t.name + ' · ' + t.lang ) + '</option>';
				} ).join( '' );
			} ).catch( function ( e ) {
				ts.tpls = [];
				status( st, e.message, 'err' );
			} );
		};
		var trender = function ( b ) {
			var state = ( $( '[data-wa-state="' + ts.id + '"]' ) || { getAttribute: function () {
				return '';
			} } ).getAttribute( 'data-state' );
			var wa = ts.type !== 'netgsm' && ts.scen.length > 1;
			var html = '<p class="flxi-muted flxi-test__sub">' + th( b.getAttribute( 'data-label' ) ) + '</p>';
			html += '<div class="flxi-test__check"><button type="button" class="button" data-test-check>' + th( TT.check ) + '</button> <span class="flxi-status" aria-live="polite"></span><small class="flxi-muted">' + th( TT.checkHelp ) + '</small></div>';
			if ( wa && ts.type !== 'wacloud' && state && state !== 'open' ) {
				html += '<p class="flxi-callout flxi-callout--warn">' + th( TT.notOpen ) + '</p>';
			}
			if ( ts.type === 'wacloud' ) {
				html += '<p class="flxi-callout">' + th( TT.window ) + '</p>';
			}
			if ( ! wa ) {
				html += '<p class="flxi-callout">' + th( TT.smsNote ) + '</p>';
			}
			html += '<div class="flxi-test__grid"><div class="flxi-test__form">';
			html += '<label class="flxi-field"><span>' + th( TT.to ) + '</span><input type="tel" inputmode="tel" data-test-to placeholder="905301234567" value="' + th( lsGet( 'flxi-test-to' ) ) + '"><small class="flxi-muted">' + th( TT.toHelp ) + '</small></label>';
			if ( wa ) {
				html += '<fieldset class="flxi-test__scen"><legend>' + th( TT.scenarios ) + '</legend>' + ts.scen.map( function ( k ) {
					return '<label class="flxi-chip"><input type="checkbox" value="' + th( k ) + '"' + ( ts.sel[ k ] ? ' checked' : '' ) + '>' + ( ICON[ k ] || '' ) + '<span>' + th( TT[ k ] ) + '</span></label>';
				} ).join( '' ) + '</fieldset>';
			}
			html += '<label class="flxi-field" data-test-text-row><span>' + th( TT.message ) + '</span><textarea rows="3" data-test-text>' + th( wa ? TT.sampleText : TT.sampleSms ) + '</textarea></label>';
			html += '<label class="flxi-field" data-test-cap-row hidden><span>' + th( TT.caption ) + '</span><input type="text" data-test-cap value="' + th( TT.sampleCap ) + '"></label>';
			if ( ts.scen.indexOf( 'template' ) > -1 ) {
				html += '<div class="flxi-field" data-test-tpl-row hidden><span>' + th( TT.template ) + '</span><select data-test-tpl><option value="">' + th( TT.tplLoad ) + '</option></select><div data-tpl-params></div><span class="flxi-status"></span></div>';
			}
			html += '</div><div class="flxi-test__phone"><div class="flxi-test__phonehead">' + th( TT.preview ) + ( wa ? '' : ' · ' + th( TT.sms ) ) + '</div><div class="flxi-test__chat' + ( wa ? '' : ' is-sms' ) + '" data-test-preview></div></div></div>';
			html += '<div class="flxi-test__foot"><span class="flxi-status" data-test-sum aria-live="polite"></span><button type="button" class="button button-primary" data-test-send>' + th( TT.send ) + '</button></div>';
			tbody.innerHTML = html;
			tpreview();

			$( '[data-test-check]', tbody ).addEventListener( 'click', function () {
				var st = this.nextElementSibling, btn = this;
				status( st, T.testing );
				btn.disabled = true;
				api( 'connection-test', { id: ts.id, to: '' } ).then( function ( r ) {
					status( st, r.ok ? T.ok : T.error + ': ' + ( r.error || '' ), r.ok ? 'ok' : 'err' );
				} ).catch( function ( e ) {
					status( st, T.error + ': ' + e.message, 'err' );
				} ).then( function () {
					btn.disabled = false;
				} );
			} );
			$$( '.flxi-test__scen input', tbody ).forEach( function ( c ) {
				c.addEventListener( 'change', function () {
					ts.sel[ c.value ] = c.checked;
					if ( c.value === 'template' && c.checked ) {
						loadTpls();
					}
					tpreview();
				} );
			} );
			[ '[data-test-text]', '[data-test-cap]' ].forEach( function ( q ) {
				var el = $( q, tbody );
				el.addEventListener( 'input', debounce( tpreview, 150 ) );
			} );
			var tsel = $( '[data-test-tpl]', tbody );
			if ( tsel ) {
				tsel.addEventListener( 'change', function () {
					ts.tpl = tsel.value === '' ? null : ts.tpls[ +tsel.value ];
					var pb = $( '[data-tpl-params]', tbody ), n = ts.tpl ? ts.tpl.params : 0, out = '';
					for ( var i = 1; i <= n; i++ ) {
						out += '<input type="text" data-tpl-param placeholder="' + th( TT.tplParam.replace( '%d', i ) ) + '">';
					}
					pb.innerHTML = out;
					$$( '[data-tpl-param]', pb ).forEach( function ( inp ) {
						inp.addEventListener( 'input', debounce( tpreview, 150 ) );
					} );
					tpreview();
				} );
			}
			$( '[data-test-send]', tbody ).addEventListener( 'click', function () {
				var btn = this, sum = $( '[data-test-sum]', tbody );
				var to = $( '[data-test-to]', tbody ).value.trim();
				var list = ts.scen.filter( function ( k ) {
					return ts.sel[ k ];
				} );
				if ( ! to ) {
					status( sum, TT.needTo, 'err' );
					$( '[data-test-to]', tbody ).focus();
					return;
				}
				lsSet( 'flxi-test-to', to );
				tpreview();
				btn.disabled = true;
				var okN = 0, i = 0;
				var next = function () {
					if ( i >= list.length ) {
						status( sum, TT.summary.replace( '%1$d', okN ).replace( '%2$d', list.length ) + ( okN ? ' ' + TT.checkPhone : '' ), okN === list.length ? 'ok' : 'err' );
						btn.disabled = false;
						return;
					}
					var k = list[ i++ ], bub = $( '[data-bubble="' + k + '"]', tbody );
					var tick = $( '[data-tick]', bub ), err = $( '[data-err]', bub );
					bub.classList.add( 'is-sending' );
					if ( bub.scrollIntoView ) {
						bub.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
					}
					tick.textContent = '🕓';
					status( sum, TT.sending + ' ' + i + '/' + list.length );
					var data = { id: ts.id, to: to, scenario: k, text: $( '[data-test-text]', tbody ).value, caption: $( '[data-test-cap]', tbody ).value };
					if ( k === 'template' && ts.tpl ) {
						data.template = { name: ts.tpl.name, lang: ts.tpl.lang, params: $$( '[data-tpl-param]', tbody ).map( function ( x ) {
							return x.value;
						} ) };
					}
					api( 'connection-test', data ).then( function ( r ) {
						return r;
					}, function ( e ) {
						return { ok: false, error: e.message };
					} ).then( function ( r ) {
						bub.classList.remove( 'is-sending' );
						bub.classList.add( r.ok ? 'is-ok' : 'is-err' );
						tick.textContent = r.ok ? '✓✓' : '✕';
						tick.title = r.ok ? TT.delivered : T.error;
						if ( ! r.ok ) {
							err.hidden = false;
							err.textContent = r.error || T.error;
						} else {
							okN++;
						}
						next();
					} );
				};
				next();
			} );
		};
		$$( '[data-conn-test]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				ts.id = b.getAttribute( 'data-conn-test' );
				ts.type = b.getAttribute( 'data-type' );
				ts.scen = ( b.getAttribute( 'data-scenarios' ) || 'text' ).split( ',' );
				ts.sel = { text: true };
				ts.tpls = null;
				ts.tpl = null;
				$( '#flxi-test-title', td ).textContent = TT.title + ' · ' + b.getAttribute( 'data-name' );
				trender( b );
				if ( td.showModal ) {
					td.showModal();
				} else {
					td.setAttribute( 'open', '' );
				}
			} );
		} );
		$( '[data-close]', td ).addEventListener( 'click', function () {
			td.close ? td.close() : td.removeAttribute( 'open' );
		} );
	}

	/* ---------------- WhatsApp hesapları (QR, durum, sunucu) ---------------- */

	var W = T.wa || {};
	function waLabel( s ) {
		var t = { open: W.open, connecting: W.connecting, close: W.close, missing: W.missing, error: W.unreach }[ s.state ] || W.unknown;
		return s.state === 'open' && s.me ? t + ' · ' + s.me : t;
	}
	function waBadge( id, s ) {
		$$( '[data-wa-state="' + id + '"]' ).forEach( function ( b ) {
			b.textContent = waLabel( s );
			b.setAttribute( 'data-state', s.state || '' );
			b.title = s.error || ( s.name || '' );
		} );
		$$( '.flxi-wa-connect-main[data-wa-connect="' + id + '"]' ).forEach( function ( b ) {
			b.hidden = s.state === 'open';
		} );
	}
	var waBadges = $$( '[data-wa-state]' );
	if ( waBadges.length ) {
		// Liste açılınca durumları bir kez tazele (sırayla; Hostinger hız sınırına takılmasın).
		waBadges.reduce( function ( p, b ) {
			return p.then( function () {
				var id = b.getAttribute( 'data-wa-state' );
				return api( 'wa/' + id + '/state?fresh=1', null, 'GET' ).then( function ( s ) {
					waBadge( id, s );
				} ).catch( function () {} );
			} );
		}, Promise.resolve() );
	}

	var wd = document.getElementById( 'flxi-wa-dialog' );
	if ( wd ) {
		var wid = 0, wtimer = 0, wtick = 0, wnum = '';
		var wImg = $( '[data-wa-qr]', wd ), wCode = $( '[data-wa-code]', wd ), wMsg = $( '[data-wa-msg]', wd );
		var wSteps = $( '[data-wa-steps]', wd ), wPair = $( '[data-wa-pair]', wd );
		var stop = function () {
			clearTimeout( wtimer );
			wid = 0;
		};
		var show = function ( r ) {
			if ( r.state === 'open' ) {
				wImg.hidden = true;
				wCode.hidden = true;
				wSteps.hidden = true;
				wPair.hidden = true;
				wMsg.className = 'flxi-wa__state is-ok';
				wMsg.textContent = W.done + ( r.me ? ' ' + r.me : '' ) + ( r.name ? ' (' + r.name + ')' : '' );
				waBadge( wid, r );
				clearTimeout( wtimer );
				return;
			}
			wSteps.hidden = !! r.pairing;
			wPair.hidden = false;
			wMsg.className = 'flxi-wa__state';
			wMsg.textContent = r.pairing ? W.pairHint : W.scan;
			if ( r.qr && ! r.pairing ) {
				wImg.src = r.qr;
				wImg.hidden = false;
			}
			if ( r.pairing ) {
				wCode.textContent = r.pairing.replace( /^(.{4})(.{4})$/, '$1-$2' );
				wCode.hidden = false;
				wImg.hidden = true;
			}
		};
		var fail = function ( e ) {
			wMsg.className = 'flxi-wa__state is-err';
			wMsg.textContent = e.message;
		};
		var connect = function () {
			if ( ! wid ) {
				return Promise.resolve();
			}
			var id = wid;
			return api( 'wa/' + id + '/connect', { number: wnum } ).then( function ( r ) {
				if ( id === wid ) {
					show( r );
				}
				return r;
			} );
		};
		// QR ~20 sn'de değişir: 5 sn'de bir durum, her 4. turda QR yenilenir (köprü belgesindeki öneri).
		var poll = function () {
			clearTimeout( wtimer );
			wtimer = setTimeout( function () {
				if ( ! wid || ! wd.open ) {
					return;
				}
				var id = wid;
				wtick++;
				var p = wtick % 4 === 0 && ! wnum ? connect() : api( 'wa/' + id + '/state?fresh=1', null, 'GET' ).then( function ( s ) {
					if ( id === wid && s.state === 'open' ) {
						show( s );
					}
					return s;
				} );
				p.then( function ( s ) {
					if ( s && s.state !== 'open' ) {
						poll();
					}
				} ).catch( function ( e ) {
					fail( e );
					poll();
				} );
			}, 5000 );
		};
		var open = function ( id, name ) {
			wid = +id;
			wtick = 0;
			wnum = '';
			$( '#flxi-wa-title', wd ).textContent = name || 'WhatsApp';
			wImg.hidden = true;
			wImg.removeAttribute( 'src' );
			wCode.hidden = true;
			wPair.open = false;
			wMsg.className = 'flxi-wa__state';
			wMsg.textContent = W.wait;
			if ( wd.showModal ) {
				wd.showModal();
			} else {
				wd.setAttribute( 'open', '' );
			}
			connect().then( function ( r ) {
				if ( r && r.state !== 'open' ) {
					poll();
				}
			} ).catch( fail );
		};
		$$( '[data-wa-connect]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				open( b.getAttribute( 'data-wa-connect' ), b.getAttribute( 'data-wa-name' ) );
			} );
		} );
		$( '[data-wa-paircode]', wd ).addEventListener( 'click', function () {
			wnum = ( $( '[data-wa-number]', wd ).value || '' ).replace( /\D/g, '' );
			if ( ! wnum ) {
				return;
			}
			wMsg.textContent = W.wait;
			connect().then( function () {
				poll();
			} ).catch( fail );
		} );
		$( '[data-close]', wd ).addEventListener( 'click', function () {
			wd.close();
		} );
		wd.addEventListener( 'close', stop );
		var qs = new URLSearchParams( location.search ), auto = qs.get( 'wa_connect' );
		var ab = auto && $( '[data-wa-connect="' + auto + '"]' );
		if ( auto && window.history.replaceState ) {
			qs.delete( 'wa_connect' );
			window.history.replaceState( null, '', location.pathname + '?' + qs.toString() );
		}
		if ( ab ) {
			open( auto, ab.getAttribute( 'data-wa-name' ) );
		}
	}

	var waAct = function ( attr, path, confirmText ) {
		$$( '[' + attr + ']' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var id = b.getAttribute( attr );
				if ( confirmText && ! window.confirm( confirmText ) ) {
					return;
				}
				var more = b.closest( 'details' );
				if ( more ) {
					more.open = false;
				}
				var st = $( '.flxi-status', b.closest( '.flxi-row__actions' ) );
				status( st, W.wait );
				api( 'wa/' + id + '/' + path, {} ).then( function ( s ) {
					waBadge( id, s );
					status( st, W.okDone, 'ok' );
				} ).catch( function ( e ) {
					status( st, T.error + ': ' + e.message, 'err' );
				} );
			} );
		} );
	};
	waAct( 'data-wa-restart', 'restart' );
	waAct( 'data-wa-logout', 'logout', W.confirmLogout );

	/* Flux WPAPI sunucu ayarları */
	var sd = document.getElementById( 'flxi-wa-server' );
	if ( sd ) {
		var sbody = $( '[data-ws-body]', sd ), sid = 0;
		var esc = function ( v ) {
			var d = document.createElement( 'div' );
			d.textContent = v == null ? '' : String( v );
			return d.innerHTML;
		};
		var render = function ( j ) {
			var db = j.db || {}, lock = j.locked || {}, ka = j.keepAliveState || {};
			var kaText = j.lastKeepAlive ? new Date( j.lastKeepAlive ).toLocaleString() + ( ka.ok === false ? ' · ' + ( ka.error || T.error ) : '' ) : W.never;
			var dbOff = lock.db ? ' disabled' : '';
			sbody.innerHTML =
				'<dl class="flxi-dl flxi-wa__info">' +
				'<dt>' + esc( W.version ) + '</dt><dd>' + esc( ( j.version || '?' ) + ' · Baileys ' + ( j.baileys || '?' ) + ' · Node ' + ( j.node || '?' ) ) + '</dd>' +
				'<dt>' + esc( W.store ) + '</dt><dd>' + esc( j.store === 'mysql' ? 'MySQL' : W.file ) + ( j.storeError ? ' · <span class="flxi-err">' + esc( j.storeError ) + '</span>' : '' ) + '</dd>' +
				'<dt>' + esc( W.lastKa ) + '</dt><dd>' + esc( kaText ) + '</dd>' +
				'<dt>' + esc( W.voice ) + '</dt><dd>' + esc( j.ffmpeg ? W.yes : W.no ) + '</dd>' +
				'</dl><form class="flxi-wa__form" data-ws-form>' +
				'<label class="flxi-field"><span>' + esc( W.publicUrl ) + '</span><input type="url" name="publicUrl" value="' + esc( j.publicUrl ) + '" placeholder="https://wa.ornek.com"><small>' + esc( W.publicUrlHelp ) + '</small></label>' +
				'<label class="flxi-check"><input type="checkbox" name="keepAlive"' + ( j.keepAlive ? ' checked' : '' ) + '> ' + esc( W.keepAlive ) + '</label>' +
				'<fieldset class="flxi-wa__db"' + dbOff + '><legend>' + esc( W.db ) + '</legend>' + ( lock.db ? '<p class="flxi-muted">' + esc( W.dbLocked ) + '</p>' : '' ) +
				'<label class="flxi-field"><span>' + esc( W.driver ) + '</span><select name="driver"><option value="file"' + ( db.driver !== 'mysql' ? ' selected' : '' ) + '>' + esc( W.file ) + '</option><option value="mysql"' + ( db.driver === 'mysql' ? ' selected' : '' ) + '>MySQL</option></select></label>' +
				'<div class="flxi-grid2" data-ws-mysql>' +
				'<label class="flxi-field"><span>' + esc( W.host ) + '</span><input type="text" name="host" value="' + esc( db.host || 'localhost' ) + '"></label>' +
				'<label class="flxi-field"><span>Port</span><input name="port" type="number" value="' + esc( db.port || 3306 ) + '"></label>' +
				'<label class="flxi-field"><span>' + esc( W.dbName ) + '</span><input type="text" name="database" value="' + esc( db.database ) + '"></label>' +
				'<label class="flxi-field"><span>' + esc( W.dbUser ) + '</span><input type="text" name="user" value="' + esc( db.user ) + '" autocomplete="off"></label>' +
				'<label class="flxi-field"><span>' + esc( W.dbPass ) + '</span><input name="password" type="password" value="" autocomplete="new-password" placeholder="' + esc( db.passwordSet ? W.passKeep : '' ) + '"></label>' +
				'<label class="flxi-field"><span>' + esc( W.prefix ) + '</span><input type="text" name="prefix" value="' + esc( db.prefix || 'flxw_' ) + '"></label>' +
				'</div><p class="flxi-muted">' + esc( W.dbWarn ) + '</p></fieldset>' +
				'<div class="flxi-actions"><button type="submit" class="button button-primary">' + esc( W.save ) + '</button> <button type="button" class="button" data-ws-test' + dbOff + '>' + esc( W.dbTest ) + '</button> <span class="flxi-status" aria-live="polite"></span></div></form>';
			var f = $( '[data-ws-form]', sbody ), drv = f.elements.driver, my = $( '[data-ws-mysql]', f );
			var sync = function () {
				my.hidden = drv.value !== 'mysql';
			};
			drv.addEventListener( 'change', sync );
			sync();
			var data = function ( withDb ) {
				var d = { publicUrl: f.elements.publicUrl.value, keepAlive: f.elements.keepAlive.checked };
				if ( withDb && ! lock.db && ( drv.value !== ( db.driver || 'file' ) || drv.value === 'mysql' ) ) {
					d.db = { driver: drv.value };
					[ 'host', 'port', 'database', 'user', 'password', 'prefix' ].forEach( function ( k ) {
						d.db[ k ] = f.elements[ k ].value;
					} );
				}
				return d;
			};
			var st = $( '.flxi-status', f );
			f.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var d = data( true );
				if ( d.db && d.db.driver !== ( db.driver || 'file' ) && ! window.confirm( W.dbSwitch ) ) {
					return;
				}
				status( st, W.wait );
				api( 'wa/' + sid + '/server', d ).then( function ( j2 ) {
					render( j2 );
					status( $( '.flxi-status', sbody ), W.okDone, 'ok' );
				} ).catch( function ( e2 ) {
					status( st, T.error + ': ' + e2.message, 'err' );
				} );
			} );
			$( '[data-ws-test]', f ).addEventListener( 'click', function () {
				var d = data( true );
				d.db = d.db || { driver: drv.value };
				d.test = true;
				status( st, T.testing );
				api( 'wa/' + sid + '/server', d ).then( function ( r ) {
					status( st, r.ok ? T.ok : T.error + ': ' + ( r.error || '' ), r.ok ? 'ok' : 'err' );
				} ).catch( function ( e2 ) {
					status( st, T.error + ': ' + e2.message, 'err' );
				} );
			} );
		};
		$$( '[data-wa-server]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var more = b.closest( 'details' );
				if ( more ) {
					more.open = false;
				}
				sid = b.getAttribute( 'data-wa-server' );
				sbody.innerHTML = '<p class="flxi-muted">' + W.wait + '</p>';
				if ( sd.showModal ) {
					sd.showModal();
				} else {
					sd.setAttribute( 'open', '' );
				}
				api( 'wa/' + sid + '/server', null, 'GET' ).then( render ).catch( function ( e ) {
					sbody.innerHTML = '<p class="flxi-err">' + esc( e.message ) + '</p>';
				} );
			} );
		} );
		$( '[data-close]', sd ).addEventListener( 'click', function () {
			sd.close();
		} );
	}

	/* WhatsApp Cloud API kurulum penceresi */
	var cd = document.getElementById( 'flxi-wac-setup' );
	if ( cd ) {
		var cbody = $( '[data-wac-body]', cd ), cid = 0;
		var h = function ( v ) {
			var d = document.createElement( 'div' );
			d.textContent = v == null ? '' : String( v );
			return d.innerHTML;
		};
		var when = function ( t ) {
			return t ? new Date( t * 1000 ).toLocaleString() : W.never;
		};
		var copyRow = function ( label, val ) {
			return '<label class="flxi-field"><span>' + h( label ) + '</span><div class="flxi-wa__pairrow"><input type="text" readonly value="' + h( val ) + '"><button type="button" class="button" data-copy>' + h( W.copy ) + '</button></div></label>';
		};
		var crender = function ( j ) {
			var i = j.info || {}, hk = j.hook || {};
			var q = { green: W.qGreen, yellow: W.qYellow, red: W.qRed }[ String( i.quality_rating || '' ).toLowerCase() ] || '';
			var html = '';
			if ( j.error ) {
				html += '<p class="flxi-wa__state is-err">' + h( j.error ) + '</p>';
			} else {
				html += '<p class="flxi-wa__state is-ok">' + h( W.done + ' ' + ( i.display_phone_number || '' ) + ( i.verified_name ? ' (' + i.verified_name + ')' : '' ) ) + '</p>';
				if ( q ) {
					html += '<p class="flxi-muted">' + h( W.quality + ': ' + q ) + '</p>';
				}
			}
			html += '<h3 class="flxi-wac__h">' + h( W.hookTitle ) + '</h3><p class="flxi-muted">' + h( W.hookHelp ) + '</p>';
			html += copyRow( W.callback, j.webhook ) + copyRow( W.verifyTok, j.verify );
			html += '<dl class="flxi-dl flxi-wa__info"><dt>' + h( W.lastVerify ) + '</dt><dd>' + h( when( hk.verify ) ) + '</dd><dt>' + h( W.lastEvent ) + '</dt><dd>' + h( when( hk.event ) ) + '</dd>' +
				( hk.bad_signature ? '<dt>' + h( W.badSig ) + '</dt><dd class="flxi-err">' + h( when( hk.bad_signature ) ) + '</dd>' : '' ) +
				'<dt>' + h( W.openChats ) + '</dt><dd>' + h( j.open_chats ) + '</dd></dl>';
			if ( ! j.signed ) {
				html += '<p class="flxi-callout flxi-callout--warn">' + h( W.noSecret ) + '</p>';
			}
			html += '<div class="flxi-actions"><button type="button" class="button" data-wac-tpl' + ( j.has_waba ? '' : ' disabled title="' + h( W.needWaba ) + '"' ) + '>' + h( W.templates ) + '</button> <span class="flxi-status" aria-live="polite"></span></div><div data-wac-tpls></div>';
			cbody.innerHTML = html;
			$$( '[data-copy]', cbody ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					var inp = b.previousElementSibling;
					inp.select();
					( navigator.clipboard ? navigator.clipboard.writeText( inp.value ) : Promise.reject() ).catch( function () {
						document.execCommand( 'copy' );
					} );
					b.textContent = T.copied;
				} );
			} );
			var tb = $( '[data-wac-tpl]', cbody );
			tb.addEventListener( 'click', function () {
				var st = $( '.flxi-status', cbody ), box = $( '[data-wac-tpls]', cbody );
				status( st, W.wait );
				api( 'wacloud/' + cid + '/templates', null, 'GET' ).then( function ( list ) {
					status( st, '' );
					if ( ! list.length ) {
						box.innerHTML = '<p class="flxi-muted">' + h( W.noTpl ) + '</p>';
						return;
					}
					box.innerHTML = '<ul class="flxi-wac__tpls">' + list.map( function ( t ) {
						return '<li><strong>' + h( t.name ) + '</strong> <span class="flxi-muted">' + h( t.lang + ' · ' + t.category ) + '</span> <span class="flxi-wa-state" data-state="' + ( t.status === 'approved' ? 'open' : ( t.status === 'rejected' ? 'error' : 'connecting' ) ) + '">' + h( t.status ) + '</span><div class="flxi-muted">' + h( t.body ) + '</div></li>';
					} ).join( '' ) + '</ul>';
				} ).catch( function ( e ) {
					status( st, T.error + ': ' + e.message, 'err' );
				} );
			} );
		};
		var copen = function ( id, name ) {
			cid = id;
			$( '#flxi-wac-title', cd ).textContent = name || 'WhatsApp Cloud API';
			cbody.innerHTML = '<p class="flxi-muted">' + h( W.wait ) + '</p>';
			if ( cd.showModal ) {
				cd.showModal();
			} else {
				cd.setAttribute( 'open', '' );
			}
			api( 'wacloud/' + id + '/setup', null, 'GET' ).then( function ( j ) {
				crender( j );
				waBadge( id, j.error ? { state: 'error', error: j.error } : { state: 'open', me: ( j.info || {} ).display_phone_number || '' } );
			} ).catch( function ( e ) {
				cbody.innerHTML = '<p class="flxi-err">' + h( e.message ) + '</p>';
			} );
		};
		$$( '[data-wac-setup]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				copen( b.getAttribute( 'data-wac-setup' ), b.getAttribute( 'data-wa-name' ) );
			} );
		} );
		$( '[data-close]', cd ).addEventListener( 'click', function () {
			cd.close();
		} );
		var cq = new URLSearchParams( location.search ), cauto = cq.get( 'wac_setup' );
		var cb = cauto && $( '[data-wac-setup="' + cauto + '"]' );
		if ( cauto && window.history.replaceState ) {
			cq.delete( 'wac_setup' );
			window.history.replaceState( null, '', location.pathname + '?' + cq.toString() );
		}
		if ( cb ) {
			copen( cauto, cb.getAttribute( 'data-wa-name' ) );
		}
	}

	/* Yeni numara formu: "Aynı sunucu" seçiliyse adres + API key gizlenir. */
	var wcopy = $( '[data-wa-copy]' );
	if ( wcopy ) {
		var wsync = function () {
			$$( '[data-wa-server]' ).forEach( function ( l ) {
				var on = ! wcopy.value;
				l.hidden = ! on;
				$$( 'input', l ).forEach( function ( i ) {
					if ( on && ! i.dataset.req ) {
						return;
					}
					if ( ! i.dataset.req ) {
						i.dataset.req = i.required ? '1' : '0';
					}
					i.required = on && i.dataset.req === '1';
				} );
			} );
		};
		wcopy.addEventListener( 'change', wsync );
		wsync();
	}

	/* ---------------- Ayarlar ---------------- */

	var mailer = $$( 'input[data-mailer]' );
	if ( mailer.length ) {
		var applyMailer = function () {
			var v = ( mailer.filter( function ( i ) {
				return i.checked;
			} )[ 0 ] || {} ).value;
			$$( '[data-for-mailer]' ).forEach( function ( el ) {
				el.hidden = el.getAttribute( 'data-for-mailer' ) !== v;
			} );
			mailer.forEach( function ( i ) {
				i.parentNode.classList.toggle( 'is-active', i.checked );
			} );
		};
		mailer.forEach( function ( i ) {
			i.addEventListener( 'change', applyMailer );
		} );
		applyMailer();
	}

	var preset = $( '[data-preset-hostinger]' );
	if ( preset ) {
		preset.addEventListener( 'click', function () {
			var set = function ( n, v ) {
				var el = $( '[name="flxi[' + n + ']"]' );
				if ( el ) {
					el.value = v;
					el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			};
			set( 'smtp_host', 'smtp.hostinger.com' );
			set( 'smtp_port', '465' );
			set( 'smtp_secure', 'ssl' );
			var user = $( '[name="flxi[smtp_user]"]' );
			var from = $( '[name="flxi[from_email]"]' );
			if ( user && ! user.value ) {
				user.value = from && from.value ? from.value : user.placeholder;
			}
			var auth = $( '[name="flxi[smtp_auth]"]' );
			if ( auth ) {
				auth.checked = true;
			}
			var pass = $( '[name="flxi[smtp_pass]"]' );
			if ( pass && ! pass.disabled ) {
				pass.focus();
			}
		} );
	}

	var testSettings = $( '[data-test-settings]' );
	if ( testSettings ) {
		testSettings.addEventListener( 'click', function () {
			var st = testSettings.parentNode.querySelector( '.flxi-status' );
			status( st, T.sending );
			testSettings.disabled = true;
			api( 'test-email', { to: $( '#flxi-test-to' ).value, sender: $( '#flxi-test-sender' ).value } ).then( function ( r ) {
				status( st, r.ok ? fmt( T.sentTo, r.to ) : T.failed + ( r.error ? ': ' + r.error : '' ), r.ok ? 'ok' : 'err' );
			} ).catch( function ( e ) {
				status( st, T.failed + ': ' + e.message, 'err' );
			} ).then( function () {
				testSettings.disabled = false;
			} );
		} );
	}

	var mbTest = $( '[data-mailbox-test]' );
	if ( mbTest ) {
		mbTest.addEventListener( 'click', function () {
			var st = mbTest.parentNode.querySelector( '.flxi-status' );
			status( st, T.checking );
			mbTest.disabled = true;
			api( 'mailbox/test', {} ).then( function ( r ) {
				status( st, r.message, r.ok ? 'ok' : 'err' );
			} ).catch( function ( e ) {
				status( st, e.message, 'err' );
			} ).then( function () {
				mbTest.disabled = false;
			} );
		} );
	}

	var dnsBtn = $( '[data-dns-check]' );
	if ( dnsBtn ) {
		dnsBtn.addEventListener( 'click', function () {
			var st = dnsBtn.parentNode.querySelector( '.flxi-status' );
			var out = $( '[data-dns-out]' );
			status( st, T.checking );
			dnsBtn.disabled = true;
			api( 'dns', {} ).then( function ( r ) {
				status( st, r.domain + ( r.error ? ' · ' + r.error : '' ), r.error ? 'err' : 'ok' );
				out.innerHTML = '';
				[ [ 'SPF', r.spf ], [ 'DMARC', r.dmarc ], [ 'MX', ( r.mx || [] ).join( ', ' ) ] ].forEach( function ( p ) {
					var dt = document.createElement( 'dt' );
					var dd = document.createElement( 'dd' );
					dt.textContent = p[ 0 ];
					if ( p[ 1 ] ) {
						var code = document.createElement( 'code' );
						code.textContent = p[ 1 ];
						dd.appendChild( code );
					} else {
						dd.textContent = '—';
						dd.className = p[ 0 ] === 'SPF' ? 'flxi-err' : 'flxi-muted';
					}
					out.appendChild( dt );
					out.appendChild( dd );
				} );
			} ).catch( function ( e ) {
				status( st, T.error + ': ' + e.message, 'err' );
			} ).then( function () {
				dnsBtn.disabled = false;
			} );
		} );
	}

	/* Logo seçici (WP ortam kütüphanesi) */
	var brandPv = null;
	$$( '[data-media]' ).forEach( function ( box ) {
		var img = $( '[data-media-img]', box );
		var input = $( '[data-media-input]', box );
		var pick = $( '[data-media-pick]', box );
		var clear = $( '[data-media-clear]', box );
		var def = img ? img.getAttribute( 'src' ) : '';
		if ( pick ) {
			pick.addEventListener( 'click', function () {
				if ( ! window.wp || ! window.wp.media ) {
					return;
				}
				var fr = window.wp.media( { title: T.chooseLogo, button: { text: T.use }, library: { type: 'image' }, multiple: false } );
				fr.on( 'select', function () {
					var a = fr.state().get( 'selection' ).first().toJSON();
					input.value = a.url;
					if ( img ) {
						img.src = a.url;
					}
					input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				} );
				fr.open();
			} );
		}
		if ( clear ) {
			clear.addEventListener( 'click', function () {
				input.value = '';
				if ( img ) {
					var back = clear.getAttribute( 'data-media-clear' ) || def || '';
					img.src = flxiSafeUrl( back );
				}
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
		}
	} );

	var brandFrame = $( '[data-brand-preview]' );
	if ( brandFrame ) {
		var brandRoot = brandFrame.closest( '.flxi-preview' ) || document;
		brandPv = Preview( brandRoot, function () {
			var b = {};
			$$( '[data-brand]' ).forEach( function ( el ) {
				var m = /^flxi\[([a-z_]+)\](?:\[([a-z_]+)\])?$/.exec( el.name || '' );
				if ( ! m || ( 'radio' === el.type && ! el.checked ) ) {
					return;
				}
				if ( m[ 2 ] ) {
					b[ m[ 1 ] ] = b[ m[ 1 ] ] || {};
					b[ m[ 1 ] ][ m[ 2 ] ] = el.value;
				} else {
					b[ m[ 1 ] ] = el.value;
				}
			} );
			return { key: 'brand', fields: { brand: b } };
		} );
		if ( brandPv ) {
			$$( '[data-brand]' ).forEach( function ( el ) {
				el.addEventListener( 'input', brandPv.later );
				el.addEventListener( 'change', brandPv.later );
			} );
			brandPv.load();
		}
	}
}() );
