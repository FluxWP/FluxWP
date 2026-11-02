/* Editör yan paneli: SEO başlığı, açıklaması, odak kelime, noindex ve Google önizlemesi. */
(function (wp, cfg) {
	var h = wp.element.createElement;
	var Panel = (wp.editor && wp.editor.PluginDocumentSettingPanel) || wp.editPost.PluginDocumentSettingPanel;
	var C = wp.components;
	var useSelect = wp.data.useSelect, useDispatch = wp.data.useDispatch;

	function strip(html) { var d = document.createElement('div'); d.innerHTML = html || ''; return (d.textContent || '').replace(/\s+/g, ' ').trim(); }
	function counter(n, min, max) {
		var c = n === 0 ? '#757575' : (n < min || n > max ? '#d63638' : '#00a32a');
		return h('span', { style: { color: c, fontSize: '11px', fontWeight: 600 } }, n + ' / ' + max);
	}

	function SeoPanel() {
		var d = useSelect(function (s) {
			var e = s('core/editor');
			return {
				meta: e.getEditedPostAttribute('meta') || {},
				title: e.getEditedPostAttribute('title') || '',
				excerpt: e.getEditedPostAttribute('excerpt') || '',
				content: e.getEditedPostContent(),
				link: e.getPermalink() || cfg.home
			};
		}, []);
		var edit = useDispatch('core/editor').editPost;
		var set = function (k, v) { var m = {}; m[k] = v; edit({ meta: m }); };
		var m = d.meta;
		var autoTitle = d.title + ' ' + cfg.sep + ' ' + cfg.siteName;
		var autoDesc = strip(d.excerpt) || strip(d.content).slice(0, 158);
		var t = m._flxseo_title || autoTitle, ds = m._flxseo_desc || autoDesc;
		var focus = (m._flxseo_focus || '').toLowerCase().trim();
		var checks = [];
		if (focus) {
			checks.push([t.toLowerCase().indexOf(focus) > -1, 'Odak kelime başlıkta']);
			checks.push([ds.toLowerCase().indexOf(focus) > -1, 'Odak kelime açıklamada']);
			checks.push([strip(d.content).toLowerCase().indexOf(focus) > -1, 'Odak kelime içerikte']);
		}
		checks.push([/<h2/i.test(d.content), 'En az bir ara başlık (H2)']);
		checks.push([strip(d.content).split(' ').length >= 250, 'İçerik en az 250 kelime']);
		checks.push([!/<img(?![^>]*alt="[^"]+")[^>]*>/i.test(d.content), 'Tüm görsellerde alt metin']);

		return h(Panel, { name: 'flxseo-panel', title: 'SEO', className: 'flxseo-panel', icon: 'search' },
			h('div', { style: { border: '1px solid #ddd', borderRadius: '8px', padding: '10px 12px', marginBottom: '14px', background: '#fff' } },
				h('div', { style: { fontSize: '12px', color: '#4d5156', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } }, d.link.replace(/^https?:\/\//, '')),
				h('div', { style: { fontSize: '16px', color: '#1a0dab', lineHeight: 1.3, margin: '4px 0' } }, t.length > 62 ? t.slice(0, 60) + '…' : t),
				h('div', { style: { fontSize: '12.5px', color: '#4d5156', lineHeight: 1.45 } }, ds.length > 160 ? ds.slice(0, 157) + '…' : ds)
			),
			h(C.TextControl, { label: h('span', null, 'SEO başlığı ', counter((m._flxseo_title || '').length, 30, 60)), value: m._flxseo_title || '', placeholder: autoTitle, onChange: function (v) { set('_flxseo_title', v); }, __nextHasNoMarginBottom: true }),
			h('div', { style: { height: '12px' } }),
			h(C.TextareaControl, { label: h('span', null, 'Meta açıklama ', counter((m._flxseo_desc || '').length, 120, 160)), value: m._flxseo_desc || '', placeholder: autoDesc, rows: 4, onChange: function (v) { set('_flxseo_desc', v); }, __nextHasNoMarginBottom: true }),
			h('div', { style: { height: '12px' } }),
			h(C.TextControl, { label: 'Odak anahtar kelime', value: m._flxseo_focus || '', onChange: function (v) { set('_flxseo_focus', v); }, __nextHasNoMarginBottom: true }),
			h('ul', { style: { margin: '10px 0 14px', fontSize: '12px' } }, checks.map(function (c, i) {
				return h('li', { key: i, style: { color: c[0] ? '#00a32a' : '#b26200', margin: '3px 0' } }, (c[0] ? '✓ ' : '• ') + c[1]);
			})),
			h(C.ToggleControl, { label: 'Arama motorlarında gösterme (noindex)', checked: !!m._flxseo_noindex, onChange: function (v) { set('_flxseo_noindex', v); }, __nextHasNoMarginBottom: true })
		);
	}

	wp.plugins.registerPlugin('flxseo', { render: SeoPanel });
})(window.wp, window.flxseoEditor || {});
