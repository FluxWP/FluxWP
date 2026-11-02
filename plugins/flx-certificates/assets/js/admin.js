/* Flux Certificates · panel ortak yardımcıları + küçük sayfa davranışları */
(function () {
	'use strict';
	var C = window.FLXS || {};

	function api(method, path, body) {
		var opts = { method: method, credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce } };
		if (body instanceof FormData) opts.body = body;
		else if (body !== undefined) {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(body);
		}
		return fetch(C.rest + path, opts).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok || j.ok === false) {
					var e = new Error(j.message || ('Hata ' + r.status));
					e.data = j;
					throw e;
				}
				return j;
			});
		});
	}

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function debounce(fn, ms) {
		var t;
		return function () {
			var a = arguments, self = this;
			clearTimeout(t);
			t = setTimeout(function () { fn.apply(self, a); }, ms);
		};
	}

	var toastBox;
	function toast(msg, type) {
		if (!toastBox) {
			toastBox = document.createElement('div');
			toastBox.className = 'flxs-toasts';
			document.body.appendChild(toastBox);
		}
		var t = document.createElement('div');
		t.className = 'flxs-toast flxs-toast--' + (type || 'ok');
		t.textContent = msg;
		toastBox.appendChild(t);
		setTimeout(function () { t.classList.add('is-out'); }, 3200);
		setTimeout(function () { t.remove(); }, 3700);
	}

	function copy(text) {
		var done = function () { toast('Kopyalandı'); };
		if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text).then(done);
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild(ta);
		ta.select();
		try { document.execCommand('copy'); done(); } catch (e) { toast('Kopyalanamadı', 'err'); }
		ta.remove();
		return Promise.resolve();
	}

	function download(blob, name) {
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = name;
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 2000);
	}

	/* İşlemler açılır menüsü: ekrana sığacak şekilde konumla (alta sığmazsa yukarı aç), dışarı tıklayınca kapat */
	function placeMenu(d) {
		var list = d.querySelector('.flxs-menu__list');
		if (!list) return;
		list.classList.add('is-fixed');
		list.style.left = list.style.top = '0px';
		var s = d.querySelector('summary').getBoundingClientRect();
		var w = list.offsetWidth, h = list.offsetHeight;
		var vw = document.documentElement.clientWidth, vh = window.innerHeight;
		var left = Math.max(8, Math.min(s.right - w, vw - w - 8));
		var top = s.bottom + 4;
		if (top + h > vh - 8 && s.top - h - 4 > 8) top = s.top - h - 4;
		else if (top + h > vh - 8) top = Math.max(8, vh - h - 8);
		list.style.left = left + 'px';
		list.style.top = top + 'px';
	}
	document.addEventListener('toggle', function (e) {
		var d = e.target;
		if (!d.classList || !d.classList.contains('flxs-menu') || !d.open) return;
		document.querySelectorAll('details.flxs-menu[open]').forEach(function (o) { if (o !== d) o.open = false; });
		placeMenu(d);
	}, true);
	document.addEventListener('click', function (e) {
		if (e.target.closest('details.flxs-menu')) {
			if (e.target.closest('.flxs-menu__list a, .flxs-menu__list button')) {
				var d = e.target.closest('details.flxs-menu');
				setTimeout(function () { d.open = false; }, 0);
			}
			return;
		}
		document.querySelectorAll('details.flxs-menu[open]').forEach(function (o) { o.open = false; });
	});
	window.addEventListener('scroll', function () { document.querySelectorAll('details.flxs-menu[open]').forEach(placeMenu); }, true);
	window.addEventListener('resize', function () { document.querySelectorAll('details.flxs-menu[open]').forEach(placeMenu); });

	/* ---------- Başka birine gönder: tek alan, rehberden dokun-ekle, çoklu alıcı ---------- */
	var fwd = null;
	/* includes/helpers.php flxs_normalize_phone ile aynı: "905321234567" ya da geçersizse "" */
	function normPhone(v) {
		var raw = String(v || '').trim();
		var d = raw.replace(/\D/g, '');
		if (!d) return '';
		var intl = raw[0] === '+' || d.indexOf('00') === 0;
		if (intl) d = d.replace(/^0+/, '');
		else if (d[0] === '0') d = '0' + d.replace(/^0+/, '');
		if (d.indexOf('900') === 0 && d.length === 13) d = '90' + d.slice(3);
		if (!intl) {
			if (d.length === 11 && d[0] === '0') d = '90' + d.slice(1);
			else if (d.length === 10 && d[0] !== '0') d = '90' + d;
		}
		d = d.replace(/^0+/, '');
		if (d.length < 10 || d.length > 15) return '';
		if (d.indexOf('90') === 0 && (d.length !== 12 || '23458'.indexOf(d[2]) === -1)) return '';
		return d;
	}
	function fmtPhone(d) {
		if (d.length === 12 && d.indexOf('90') === 0) return '+90 ' + d.slice(2, 5) + ' ' + d.slice(5, 8) + ' ' + d.slice(8, 10) + ' ' + d.slice(10);
		return '+' + d;
	}
	function validEmail(v) { return /^[^@\s]+@[^@\s]+\.[^@\s.]{2,}$/.test(String(v || '').trim()); }
	function waNumber(v) { return normPhone(v) || String(v || '').replace(/\D/g, ''); }
	/* "Hasan 0533 999 88 77, ayse@x.com" → [{name, to, email}] */
	function parseTargets(text) {
		return String(text || '').split(/[,;\n]+/).map(function (part) {
			part = part.trim();
			if (!part) return null;
			var em = part.match(/[^\s<>]+@[^\s<>]+\.[^\s<>]+/);
			if (em) return { to: em[0], email: true, name: part.replace(em[0], '').replace(/[<>()]/g, '').trim() };
			var ph = part.match(/\+?\d[\d\s().-]{8,}\d/);
			if (ph && normPhone(ph[0])) return { to: ph[0].trim(), email: false, name: part.replace(ph[0], '').replace(/[()]/g, '').trim() };
			return { bad: part };
		}).filter(Boolean);
	}
	function forward(certId) {
		if (!fwd) {
			fwd = document.createElement('div');
			fwd.className = 'flxs-job flxs-fwd';
			fwd.hidden = true;
			fwd.innerHTML = '<form class="flxs-job__box" novalidate>' +
				'<div class="flxs-fwd__head"><div><h2>Başka birine gönder</h2><p class="flxs-fwd__sub"></p></div><button type="button" class="flxs-fwd__x" data-fwd-close aria-label="Kapat">×</button></div>' +
				'<label class="flxs-fwd__to"><span>Kime?</span><textarea name="to" rows="1" placeholder="0532 123 45 67  ·  Ad yazabilirsiniz: Hasan 0532…  ·  virgülle birden fazla" autocomplete="off"></textarea></label>' +
				'<div class="flxs-fwd__book"><div class="flxs-fwd__list"></div></div>' +
				'<div class="flxs-fwd__opts"><span class="flxs-seg flxs-seg--sm" data-fwd-phone><button type="button" data-fwd-ch="whatsapp">WhatsApp</button><button type="button" data-fwd-ch="sms">SMS</button></span>' +
				'<label class="flxs-fwd__save"><input type="checkbox" name="save" checked> Yeni numaraları rehbere kaydet</label></div>' +
				'<details class="flxs-fwd__msg"><summary>Mesajı düzenle</summary><input type="text" name="subject" placeholder="E-posta konusu"><textarea name="body" rows="6"></textarea></details>' +
				'<p class="flxs-fwd__warn" hidden></p>' +
				'<div class="flxs-job__foot"><button type="submit" class="button button-primary" data-fwd-go>Gönder</button>' +
				'<a class="button" data-fwd-wa target="_blank" rel="noopener">Kendi WhatsApp\'ımdan aç</a></div></form>';
			document.body.appendChild(fwd);
			fwd._f = fwd.querySelector('form');
			fwd.addEventListener('click', function (e) {
				if (e.target === fwd || e.target.closest('[data-fwd-close]')) { fwd.hidden = true; return; }
				var c = e.target.closest('[data-fwd-ch]');
				if (c) { fwd._phoneCh = c.getAttribute('data-fwd-ch'); try { localStorage.setItem('flxs_fwd_ch', fwd._phoneCh); } catch (er) {} sync(); return; }
				var del = e.target.closest('[data-rcp-del]');
				if (del) {
					if (!window.confirm('Rehberden silinsin mi?')) return;
					api('DELETE', 'recipients/' + del.getAttribute('data-rcp-del')).then(loadBook).catch(function (er) { toast(er.message, 'err'); });
					return;
				}
				var pick = e.target.closest('[data-rcp]');
				if (pick) {
					var x = (fwd._book || []).filter(function (r) { return r.id === pick.getAttribute('data-rcp'); })[0];
					if (!x) return;
					var val = (x.name ? x.name + ' ' : '') + (x.phone || x.email);
					var ta = fwd._f.to;
					var cur = ta.value.replace(/[,;\s]+$/, '');
					if (cur.indexOf(x.phone || x.email) === -1) ta.value = cur ? cur + ', ' + val : val;
					ta.focus();
					sync();
				}
			});
			fwd._f.to.addEventListener('input', function () { sync(); renderBook(); });
			fwd._f.to.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); fwd._f.requestSubmit ? fwd._f.requestSubmit() : fwd._f.dispatchEvent(new Event('submit')); }
			});
			fwd._f.body.addEventListener('input', sync);
			fwd._f.addEventListener('submit', function (e) {
				e.preventDefault();
				var ts = parseTargets(fwd._f.to.value);
				var bad = ts.filter(function (t) { return t.bad; });
				if (!ts.length) { toast('Numara ya da e-posta yazın.', 'err'); fwd._f.to.focus(); return; }
				if (bad.length) { toast('Anlaşılamadı: ' + bad[0].bad, 'err'); return; }
				var btn = fwd._f.querySelector('[data-fwd-go]');
				btn.disabled = true;
				var ok = 0, errs = [];
				var chain = Promise.resolve();
				ts.forEach(function (t) {
					chain = chain.then(function () {
						var ch = t.email ? 'email' : fwd._phoneCh;
						var msg = fwd._msgs[ch] || {};
						var body = ch === 'email' ? (fwd._edited ? fwd._f.body.value : msg.body) : (fwd._edited ? fwd._f.body.value : msg.body);
						return api('POST', 'certificates/' + fwd._id + '/message', { channel: ch, to: t.to, name: t.name, body: body || '', subject: fwd._f.subject.value || msg.subject || '', save: fwd._f.save.checked }).then(function () { ok++; }).catch(function (er) { errs.push((t.name || t.to) + ': ' + er.message); });
					});
				});
				chain.then(function () {
					btn.disabled = false;
					if (ok) { toast(ok + ' alıcı için kuyruğa alındı'); api('POST', 'queue/run').catch(function () {}); }
					if (errs.length) { toast(errs[0], 'err'); return; }
					fwd.hidden = true;
				});
			});
		}
		fwd._id = certId;
		fwd._f.reset();
		fwd._f.save.checked = true;
		fwd._edited = false;
		fwd._msgs = {};
		fwd._phoneCh = 'whatsapp';
		try { fwd._phoneCh = localStorage.getItem('flxs_fwd_ch') || 'whatsapp'; } catch (e) {}
		fwd.querySelector('.flxs-fwd__msg').open = false;
		fwd.querySelector('.flxs-fwd__sub').textContent = '';
		fwd.hidden = false;
		['whatsapp', 'sms', 'email'].forEach(function (ch) {
			api('GET', 'certificates/' + certId + '/message?channel=' + ch).then(function (r) {
				if (fwd._id !== certId) return;
				fwd._msgs[ch] = r;
				fwd.querySelector('.flxs-fwd__sub').textContent = r.title + (r.owner ? ' · ' + r.owner.name : '');
				sync();
			}).catch(function () {});
		});
		loadBook();
		sync();
		setTimeout(function () { fwd._f.to.focus(); }, 60);
	}
	/* Alanlara göre kanal, mesaj metni, buton ve uyarıları güncelle */
	function sync() {
		var f = fwd._f;
		var ts = parseTargets(f.to.value).filter(function (t) { return !t.bad; });
		var phones = ts.filter(function (t) { return !t.email; });
		var emails = ts.filter(function (t) { return t.email; });
		fwd.querySelector('[data-fwd-phone]').hidden = !!ts.length && !phones.length;
		fwd.querySelectorAll('[data-fwd-ch]').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-fwd-ch') === fwd._phoneCh); });
		var mainCh = phones.length || !ts.length ? fwd._phoneCh : 'email';
		var msg = fwd._msgs[mainCh] || {};
		if (!fwd._edited && document.activeElement !== f.body) f.body.value = msg.body || '';
		if (f.body.value !== (msg.body || '')) fwd._edited = true;
		f.subject.hidden = !emails.length;
		if (!f.subject.value && msg.subject) f.subject.value = (fwd._msgs.email || {}).subject || '';
		var go = fwd.querySelector('[data-fwd-go]');
		var label = ts.length > 1 ? ts.length + ' kişiye gönder' : 'Gönder';
		var chans = [];
		if (phones.length) chans.push(fwd._phoneCh === 'sms' ? 'SMS' : 'WhatsApp');
		if (emails.length) chans.push('E-posta');
		go.textContent = label + (chans.length ? ' (' + chans.join(' + ') + ')' : '');
		var a = fwd.querySelector('[data-fwd-wa]');
		a.hidden = !(phones.length === 1 && !emails.length && fwd._phoneCh === 'whatsapp');
		if (!a.hidden) a.href = 'https://wa.me/' + waNumber(phones[0].to) + '?text=' + encodeURIComponent(f.body.value);
		var miss = [];
		if (phones.length && fwd._msgs[fwd._phoneCh] && !fwd._msgs[fwd._phoneCh].has_provider) miss.push(fwd._phoneCh === 'sms' ? 'SMS' : 'WhatsApp');
		if (emails.length && fwd._msgs.email && !fwd._msgs.email.has_provider) miss.push('E-posta');
		var w = fwd.querySelector('.flxs-fwd__warn');
		w.hidden = !miss.length;
		w.textContent = miss.join(', ') + ' için sağlayıcı tanımlı değil, "Gönder" bu kanalda çalışmaz.';
	}
	function loadBook() {
		return api('GET', 'recipients').then(function (r) { fwd._book = r || []; renderBook(); }).catch(function () { fwd._book = []; renderBook(); });
	}
	function renderBook() {
		var box = fwd.querySelector('.flxs-fwd__list');
		var all = fwd._book || [];
		fwd.querySelector('.flxs-fwd__book').hidden = !all.length;
		// yazılan son parçaya göre süz (numara ya da ad)
		var last = fwd._f.to.value.split(/[,;\n]/).pop().trim().toLocaleLowerCase('tr');
		var dig = last.replace(/\D/g, '');
		var list = all.filter(function (x) {
			if (!last || /\d{9,}/.test(dig) || last.indexOf('@') > -1) return true;
			var hay = (x.name + ' ' + x.phone + ' ' + x.email).toLocaleLowerCase('tr');
			return hay.indexOf(last) > -1 || (dig.length > 2 && hay.replace(/\D/g, '').indexOf(dig) > -1);
		});
		box.innerHTML = list.slice(0, 24).map(function (x) {
			return '<span class="flxs-rcp"><button type="button" data-rcp="' + esc(x.id) + '" title="' + esc(x.phone || x.email) + '">' + esc(x.name) + '</button>' +
				'<button type="button" class="flxs-rcp__del" data-rcp-del="' + esc(x.id) + '" aria-label="Sil">×</button></span>';
		}).join('') || '<small class="flxs-muted">Rehberde eşleşen yok</small>';
	}
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-forward]');
		if (!b) return;
		e.preventDefault();
		forward(b.getAttribute('data-forward'));
	});

	/* İşlemler › Referansı güncelle: mini pencere */
	var refBox = null;
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-ref-edit]');
		if (!b) return;
		e.preventDefault();
		if (!refBox) {
			refBox = document.createElement('div');
			refBox.className = 'flxs-job flxs-refbox';
			refBox.hidden = true;
			refBox.innerHTML = '<form class="flxs-job__box"><div class="flxs-fwd__head"><div><h2>Referansı güncelle</h2><p class="flxs-fwd__sub"></p></div>' +
				'<button type="button" class="flxs-fwd__x" data-ref-close aria-label="Kapat">×</button></div>' +
				'<select name="ref"></select>' +
				'<div class="flxs-job__foot"><button type="submit" class="button button-primary">Kaydet</button><button type="button" class="button" data-ref-close>Vazgeç</button></div></form>';
			document.body.appendChild(refBox);
			refBox.addEventListener('click', function (ev) { if (ev.target === refBox || ev.target.closest('[data-ref-close]')) refBox.hidden = true; });
			refBox.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') refBox.hidden = true; });
			refBox.querySelector('form').addEventListener('submit', function (ev) {
				ev.preventDefault();
				var sel = refBox.querySelector('select');
				var id = refBox._id;
				var btn = refBox.querySelector('[type=submit]');
				btn.disabled = true;
				api('POST', 'certificates/' + id + '/ref', { ref_user: +sel.value }).then(function (r) {
					refBox.hidden = true;
					toast(r.ref_user ? 'Referans: ' + r.ref_user : 'Referans kaldırıldı');
					var link = document.querySelector('[data-ref-edit="' + id + '"]');
					if (link) link.setAttribute('data-ref', r.ref_user_id);
					var cell = document.querySelector('[data-ref-cell="' + id + '"]');
					if (cell) {
						var first = cell.firstElementChild;
						var html = r.ref_user ? '<strong>' + esc(r.ref_user) + '</strong>' : '<span class="flxs-muted">–</span>';
						if (first) first.outerHTML = html; else cell.insertAdjacentHTML('afterbegin', html);
					}
				}).catch(function (er) { toast(er.message, 'err'); }).then(function () { btn.disabled = false; });
			});
		}
		refBox._id = b.getAttribute('data-ref-edit');
		var sel = refBox.querySelector('select');
		sel.innerHTML = '<option value="0">— Referans yok —</option>' + (C.refs || []).map(function (u) { return '<option value="' + u.id + '">' + esc(u.name) + '</option>'; }).join('');
		sel.value = b.getAttribute('data-ref') || '0';
		refBox.querySelector('.flxs-fwd__sub').textContent = b.getAttribute('data-label') || '';
		refBox.hidden = false;
		setTimeout(function () { sel.focus(); }, 30);
	});

	// Toplu işlem: "Referans ata" seçilince personel listesi görünsün
	document.addEventListener('change', function (e) {
		if (e.target.name !== 'bulk') return;
		var r = e.target.form && e.target.form.querySelector('.flxs-bulk-ref');
		if (r) r.hidden = e.target.value !== 'ref';
		var ev = e.target.form && e.target.form.querySelector('.flxs-bulk-event');
		if (ev) ev.hidden = e.target.value !== 'event';
	});

	window.FLXSA = { forward: forward, normPhone: normPhone, fmtPhone: fmtPhone, validEmail: validEmail, api: api, esc: esc, debounce: debounce, toast: toast, copy: copy, download: download, C: C };

	// Toplu işlem çubuğu: seçim varken görünür (telefonda yer kaplamasın)
	function selState(f) {
		if (!f || !f.querySelector('.flxs-bulkbar')) return;
		f.classList.toggle('flxs-has-sel', !!f.querySelector('input[name="ids[]"]:checked'));
	}
	document.querySelectorAll('.flxs-bulkbar').forEach(function (b) { selState(b.closest('form')); });

	/* ---------- ortak davranışlar ---------- */
	document.addEventListener('click', function (e) {
		var c = e.target.closest('[data-flxs-copy]');
		if (c) { e.preventDefault(); copy(c.getAttribute('data-flxs-copy')); return; }

		var cf = e.target.closest('[data-confirm]');
		if (cf && !window.confirm(cf.getAttribute('data-confirm'))) { e.preventDefault(); return; }

		var all = e.target.closest('.flxs-check-all');
		if (all) {
			var tbl = all.closest('table');
			tbl.querySelectorAll('tbody input[type=checkbox][name="ids[]"]').forEach(function (cb) { cb.checked = all.checked; });
		}
		if (e.target.matches && e.target.matches('input[type=checkbox]')) { selState(e.target.form); }

		var tg = e.target.closest('[data-toggle]');
		if (tg) {
			e.preventDefault();
			var el = document.getElementById(tg.getAttribute('data-toggle'));
			if (el) el.hidden = !el.hidden;
		}

		// Kuyruk: tekrar dene / iptal
		var op = e.target.closest('[data-msg-op]');
		if (op) {
			e.preventDefault();
			op.disabled = true;
			api('POST', 'messages/' + op.getAttribute('data-id') + '/' + op.getAttribute('data-msg-op')).then(function (r) {
				toast(r.ok ? 'Tamam' : 'Yapılamadı', r.ok ? 'ok' : 'err');
				setTimeout(function () { location.reload(); }, 600);
			}).catch(function (er) { toast(er.message, 'err'); op.disabled = false; });
		}

		// Kuyruğu şimdi işle
		var run = e.target.closest('[data-queue-run]');
		if (run) {
			e.preventDefault();
			run.disabled = true;
			run.textContent = 'İşleniyor…';
			api('POST', 'queue/run').then(function (r) {
				toast(r.locked ? 'Kuyruk zaten işleniyor' : (r.processed + ' mesaj işlendi'));
				setTimeout(function () { location.reload(); }, 500);
			}).catch(function (er) { toast(er.message, 'err'); run.disabled = false; });
		}

		// Sağlayıcı testi
		var tb = e.target.closest('[data-provider-test]');
		if (tb) {
			e.preventDefault();
			var box = tb.closest('.flxs-test');
			var to = box.querySelector('input').value.trim();
			var out = box.querySelector('.flxs-test__out');
			tb.disabled = true;
			out.className = 'flxs-test__out';
			out.textContent = 'Deneniyor…';
			api('POST', 'providers/' + tb.getAttribute('data-provider-test') + '/test', { to: to }).then(function (r) {
				out.className = 'flxs-test__out is-ok';
				out.textContent = '✓ ' + r.message + (r.response ? ' · ' + JSON.stringify(r.response).slice(0, 300) : '');
			}).catch(function (er) {
				out.className = 'flxs-test__out is-err';
				out.textContent = '✕ ' + er.message;
			}).finally(function () { tb.disabled = false; });
		}

		// Sertifika iptal / geri al
		var st = e.target.closest('[data-cert-status]');
		if (st) {
			e.preventDefault();
			api('POST', 'certificates/' + st.getAttribute('data-id') + '/status', { status: st.getAttribute('data-cert-status') }).then(function () {
				location.reload();
			}).catch(function (er) { toast(er.message, 'err'); });
		}

		// Tek sertifika hızlı gönder
		var qs = e.target.closest('[data-quick-send]');
		if (qs) {
			e.preventDefault();
			var ch = qs.getAttribute('data-quick-send');
			if (!window.confirm(C.channels[ch] + ' ile gönderilsin mi?')) return;
			api('POST', 'certificates/' + qs.getAttribute('data-id') + '/send', { channels: [ch] }).then(function (r) {
				var res = r.results[ch] || {};
				toast(res.ok ? 'Kuyruğa alındı' : res.message, res.ok ? 'ok' : 'err');
			}).catch(function (er) { toast(er.message, 'err'); });
		}
	});

	// Sağlayıcı formu: tip değişince alanlar
	var typeSel = document.getElementById('flxs-provider-type');
	if (typeSel) {
		var sync = function () {
			document.querySelectorAll('[data-ptype]').forEach(function (el) {
				var on = el.getAttribute('data-ptype') === typeSel.value;
				el.hidden = !on;
				el.querySelectorAll('input,select,textarea').forEach(function (i) { i.disabled = !on; });
			});
		};
		typeSel.addEventListener('change', sync);
		sync();
	}

	// Kuyruk sayfası: bekleyen varsa canlı yenile + tarayıcıdan işlet (WP-Cron gecikirse)
	var live = document.querySelector('[data-queue-live]');
	if (live) {
		var pending = parseInt(live.getAttribute('data-queue-live'), 10) || 0;
		if (pending > 0) {
			var tick = function () {
				if (document.hidden) return setTimeout(tick, 4000);
				api('POST', 'queue/run').then(function (r) {
					var c = r.counts || {};
					if ((c.queued || 0) + (c.processing || 0) !== pending || r.processed) location.reload();
					else setTimeout(tick, 6000);
				}).catch(function () { setTimeout(tick, 10000); });
			};
			setTimeout(tick, 2500);
		}
	}

	// Toplu gönderim formu
	var bulk = document.getElementById('flxs-bulk');
	if (bulk) {
		var syncBulk = function () {
			var mode = bulk.querySelector('[name=mode]:checked').value;
			var ch = bulk.querySelector('[name=channel]:checked').value;
			bulk.querySelectorAll('[data-mode]').forEach(function (el) {
				el.hidden = el.getAttribute('data-mode').split(' ').indexOf(mode) === -1;
			});
			bulk.querySelectorAll('[data-channel]').forEach(function (el) {
				el.hidden = el.getAttribute('data-channel').split(' ').indexOf(ch) === -1;
			});
			var ps = bulk.querySelector('[name=provider_id]');
			var cur = ps.value;
			ps.innerHTML = '<option value="0">Varsayılan</option>' + (C.providers[ch] || []).filter(function (p) { return p.id; }).map(function (p) {
				return '<option value="' + p.id + '">' + esc(p.name) + '</option>';
			}).join('');
			ps.value = cur;
			if (!ps.value) ps.value = '0';
			var ta = bulk.querySelector('[name=body]');
			var tpl = bulk.getAttribute('data-default-' + ch);
			if (ta.getAttribute('data-touched') !== '1' && tpl !== null) ta.value = mode === 'certificates' ? '' : tpl;
			var su = bulk.querySelector('[name=subject]');
			if (su.getAttribute('data-touched') !== '1') su.value = mode === 'certificates' ? '' : (bulk.getAttribute('data-default-subject') || '');
			bulk.querySelector('[data-cert-hint]').hidden = mode !== 'certificates';
			var cnt = bulk.querySelector('.flxs-charcount');
			if (cnt) cnt.hidden = ch !== 'sms';
			updCount();
		};
		var updCount = function () {
			var ta = bulk.querySelector('[name=body]');
			var cnt = bulk.querySelector('.flxs-charcount');
			if (!cnt) return;
			var n = ta.value.length;
			cnt.textContent = n + ' karakter · ~' + Math.max(1, Math.ceil(n / 150)) + ' SMS (yer tutucular dolunca değişir)';
		};
		bulk.addEventListener('change', function (e) {
			if (e.target.name === 'mode' || e.target.name === 'channel') syncBulk();
		});
		bulk.addEventListener('input', function (e) {
			if (e.target.name === 'body' || e.target.name === 'subject') e.target.setAttribute('data-touched', '1');
			updCount();
		});
		syncBulk();

		var spec = function () {
			var fd = new FormData(bulk);
			var s = {};
			fd.forEach(function (v, k) {
				if (k.slice(-2) === '[]') { k = k.slice(0, -2); (s[k] = s[k] || []).push(v); } else s[k] = v;
			});
			return s;
		};
		var out = document.getElementById('flxs-bulk-out');
		bulk.querySelector('[data-bulk-preview]').addEventListener('click', function (e) {
			e.preventDefault();
			out.innerHTML = '<p>Hesaplanıyor…</p>';
			api('POST', 'bulk', { spec: spec(), dry: true }).then(function (r) {
				var html = '<div class="flxs-bulk-sum"><strong>' + r.count + ' alıcı</strong> · ' + esc(r.provider) +
					(r.duration ? ' · tahmini süre ~' + Math.ceil(r.duration / 60) + ' dk (mesajlar arası bekleme)' : '') + '</div>';
				if (r.samples.length) {
					html += '<div class="flxs-samples">' + r.samples.map(function (m) {
						return '<div class="flxs-sample"><div class="flxs-sample__to">' + esc(m.recipient_name) + ' · ' + esc(m.recipient) + '</div>' +
							(m.subject ? '<div class="flxs-sample__subj">' + esc(m.subject) + '</div>' : '') +
							'<pre>' + esc(m.body.replace('{buton}', '[Sertifikayı görüntüle butonu]')) + '</pre></div>';
					}).join('') + '</div>';
				}
				if (r.skipped.length) {
					html += '<details class="flxs-skipped"><summary>' + r.skipped.length + ' alıcı atlanacak</summary><ul>' + r.skipped.map(function (s) { return '<li>' + esc(s) + '</li>'; }).join('') + '</ul></details>';
				}
				if (r.count) html += '<p><button class="button button-primary" data-bulk-go>' + r.count + ' mesajı kuyruğa al</button></p>';
				out.innerHTML = html;
			}).catch(function (er) { out.innerHTML = '<div class="notice notice-error inline"><p>' + esc(er.message) + '</p></div>'; });
		});
		out.addEventListener('click', function (e) {
			var go = e.target.closest('[data-bulk-go]');
			if (!go) return;
			e.preventDefault();
			go.disabled = true;
			api('POST', 'bulk', { spec: spec(), dry: false }).then(function (r) {
				toast(r.queued + ' mesaj kuyruğa alındı');
				location.href = C.admin + '?page=flxs-kuyruk&batch=' + r.batch_id;
			}).catch(function (er) { toast(er.message, 'err'); go.disabled = false; });
		});
	}
})();
