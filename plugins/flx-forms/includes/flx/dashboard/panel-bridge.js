/* Flux Forms · deep links from the Dashboard into EFB's panel.
 * admin.php?page=Emsfb&flxf_form=ID           opens that form's response list
 * admin.php?page=Emsfb&flxf_form=ID&flxf_msg=N also opens that response
 * The panel rewrites the URL on load, so the parameters are read right away. */
(function () {
	'use strict';
	var qs = new URLSearchParams(window.location.search);
	var form = parseInt(qs.get('flxf_form') || '', 10);
	var msg = parseInt(qs.get('flxf_msg') || '', 10);
	if (!(form > 0)) return;

	function waitFor(test, done, timeout) {
		var t0 = Date.now();
		(function tick() {
			var ok = false;
			try { ok = test(); } catch (e) { ok = false; }
			if (ok) { done(); return; }
			if (Date.now() - t0 < (timeout || 15000)) setTimeout(tick, 120);
		})();
	}

	waitFor(function () {
		return typeof window.emsFormBuilder_messages === 'function' &&
			typeof window.ajax_object_efm === 'object' && window.ajax_object_efm &&
			Array.isArray(window.ajax_object_efm.ajax_value) &&
			document.getElementById('content-efb') &&
			document.readyState === 'complete';
	}, function () {
		var known = window.ajax_object_efm.ajax_value.some(function (r) { return Number(r.form_id) === form; });
		if (!known) return;
		/* Let the panel finish its own first render, then switch to the form's responses. */
		setTimeout(function () {
			window.emsFormBuilder_messages(form);
			if (!(msg > 0)) return;
			waitFor(function () { return !!document.getElementById('btn-m-' + msg); }, function () {
				var btn = document.getElementById('btn-m-' + msg);
				var row = btn.closest('tr');
				if (row && row.scrollIntoView) row.scrollIntoView({ block: 'center' });
				btn.click();
			}, 15000);
		}, 300);
	}, 20000);
})();
