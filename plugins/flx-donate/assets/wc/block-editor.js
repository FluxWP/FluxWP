/*!
 * Flux Donate · "Donation options" block (flx-wc-bagis/secenekler, rendered on the server). GPL v2 or later.
 */
(function (wp) {
	'use strict';
	if (!wp || !wp.blocks || wp.blocks.getBlockType('flx-wc-bagis/secenekler')) return;
	var el = wp.element.createElement;
	var L = window.FLXB_WC_BLOCK || {};
	var be = wp.blockEditor, C = wp.components, SSR = wp.serverSideRender;
	wp.blocks.registerBlockType('flx-wc-bagis/secenekler', {
		apiVersion: 3,
		title: L.title || 'Donation options',
		description: L.desc || '',
		category: 'widgets',
		icon: 'heart',
		supports: { align: ['wide', 'full'], html: false },
		attributes: { tab: { type: 'string', default: '' }, general: { type: 'boolean', default: true }, align: { type: 'string', default: '' }, types: { type: 'string', default: '' } },
		edit: function (props) {
			var a = props.attributes;
			var bp = be.useBlockProps ? be.useBlockProps() : {};
			var chosen = (a.types || '').split(',').filter(Boolean);
			return el('div', bp,
				el(be.InspectorControls, null,
					el(C.PanelBody, { title: L.title },
						(L.typeList && L.typeList.length) ? el(C.SelectControl, { multiple: true, label: L.types, help: L.typesHelp, value: chosen, options: L.typeList, onChange: function (v) { props.setAttributes({ types: (v || []).join(',') }); } }) : null,
						(L.typeList && L.typeList.length) ? el(C.SelectControl, { label: L.tab, value: a.tab, options: [{ label: L.first, value: '' }].concat(L.typeList), onChange: function (v) { props.setAttributes({ tab: v }); } }) : null,
						el('p', { className: 'components-base-control__help' }, L.settings)
					)
				),
				el('div', { style: { pointerEvents: 'none' } }, el(SSR, { block: 'flx-wc-bagis/secenekler', attributes: a }))
			);
		},
		save: function () { return null; }
	});
})(window.wp);
