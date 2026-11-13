/**
 * Flux AI › Skill'ler: açıklama sayacı, test çalıştırma ve sonuç tabloları.
 */
(function () {
	'use strict';
	var A = window.FLXAI_ADMIN || {};
	var L = A.i18n || {};
	function t(s) { return L[s] || s; }
	function tf(s) { var a = arguments, i = 1; return t(s).replace(/%(\d\$)?[sd]/g, function () { return a[i++]; }); }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	var loc = A.locale || 'tr-TR';
	function num(v) { return (+v || 0).toLocaleString(loc); }
	function usd(v) { v = +v || 0; return '$' + v.toLocaleString(loc, { minimumFractionDigits: 4, maximumFractionDigits: 4 }); }
	function when(ts) { try { return new Date(ts * 1000).toLocaleString(loc, { dateStyle: 'medium', timeStyle: 'short' }); } catch (e) { return ''; } }

	document.querySelectorAll('textarea[data-count]').forEach(function (ta) {
		var out = ta.parentNode.querySelector('.flxai-count');
		function upd() { if (out) { out.textContent = tf('%s / 1024', num(ta.value.length)); out.classList.toggle('is-bad', ta.value.length > 1024 || /[<>]/.test(ta.value)); } }
		ta.addEventListener('input', upd); upd();
	});

	var box = document.querySelector('.flxai-skill-tests');
	if (!box) return;
	var slug = box.getAttribute('data-skill');
	var out = box.querySelector('.flxai-skill-results');
	var res = {};
	try { res = JSON.parse(out.getAttribute('data-results') || '{}') || {}; } catch (e) { res = {}; }

	function yes(b) { return b ? '<span class="flxai-pill is-ok">✓</span>' : '<span class="flxai-pill is-err">✕</span>'; }
	function trigger(r) {
		if (!r) return '';
		var h = '<h3>' + esc(t('Trigger test')) + ' · <span class="flxai-rate' + (r.rate >= 90 ? ' is-ok' : ' is-warn') + '">' + esc(tf('%s% correct', r.rate)) + '</span></h3>';
		h += '<p class="flxai-note">' + esc(tf('%1$s of %2$s prompts · %3$s · %4$s', r.hits, r.total, usd(r.cost), when(r.ts))) + (r.partial ? ' · ' + esc(t('Stopped early (time limit); run again for the rest.')) : '') + '</p>';
		h += '<table class="widefat striped"><thead><tr><th>' + esc(t('Prompt')) + '</th><th>' + esc(t('Expected')) + '</th><th>' + esc(t('Picked skills')) + '</th><th></th></tr></thead><tbody>';
		(r.rows || []).forEach(function (x) {
			h += '<tr><td>' + esc(x.query) + '</td><td>' + esc(x.should_trigger ? t('Use') : t('Do not use')) + '</td><td><code>' + esc((x.picked || []).join(', ') || '—') + '</code></td><td>' + yes(x.triggered === x.should_trigger) + '</td></tr>';
		});
		return h + '</tbody></table>';
	}
	function side(label, s) {
		return '<div class="flxai-skill-side"><h4>' + esc(label) + ' · ' + esc(tf('%1$s/%2$s checks', s.passed, s.total)) + '</h4><p class="flxai-note">' +
			esc(tf('%1$s in · %2$s out tokens · %3$s · %4$s s', num(s.in), num(s.out), usd(s.cost), (s.ms / 1000).toFixed(1))) + '</p><pre>' + esc(s.output) + '</pre></div>';
	}
	function functional(r) {
		var h = '<h3>' + esc(tf('Test #%s', r.id)) + '</h3><p class="flxai-note">' + esc(r.prompt) + ' · ' + esc(when(r.ts)) + '</p>';
		if ((r.checks || []).length) {
			h += '<table class="widefat striped"><thead><tr><th>' + esc(t('Check')) + '</th><th>' + esc(t('With skill')) + '</th><th>' + esc(t('Without')) + '</th></tr></thead><tbody>';
			r.checks.forEach(function (c) { h += '<tr><td>' + esc(c.text) + '</td><td>' + yes(c['with']) + '</td><td>' + yes(c.without) + '</td></tr>'; });
			h += '</tbody></table>';
		}
		if (r.note) h += '<p><em>' + esc(r.note) + '</em></p>';
		return h + '<div class="flxai-skill-sides">' + side(t('With skill'), r['with']) + side(t('Without'), r.without) + '</div>';
	}
	function render() {
		var h = trigger(res.trigger);
		var f = res.functional || {};
		Object.keys(f).forEach(function (k) { h += functional(f[k]); });
		out.innerHTML = h || '<p class="flxai-note">' + esc(t('No results yet.')) + '</p>';
	}
	render();

	box.querySelectorAll('[data-run]').forEach(function (b) {
		b.addEventListener('click', function () {
			var kind = b.getAttribute('data-run'), id = b.getAttribute('data-id') || '';
			var all = box.querySelectorAll('[data-run]');
			all.forEach(function (x) { x.disabled = true; });
			var old = b.textContent; b.textContent = t('Running…');
			fetch(A.rest + 'skills/' + encodeURIComponent(slug) + '/test' + (A.rest.indexOf('?') < 0 ? '?' : '&') + '_locale=user', {
				method: 'POST', credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': A.nonce, 'Accept': 'application/json' },
				body: JSON.stringify({ kind: kind, id: id })
			}).then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d && d.message || r.statusText); return d; }); })
				.then(function (d) {
					if (kind === 'trigger') res.trigger = d; else { res.functional = res.functional || {}; res.functional[d.id] = d; }
					render();
				})
				.catch(function (e) { out.insertAdjacentHTML('afterbegin', '<div class="notice notice-error inline"><p>' + esc(e.message) + '</p></div>'); })
				.then(function () { all.forEach(function (x) { x.disabled = false; }); b.textContent = old; });
		});
	});
})();
