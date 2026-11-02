/* Flux Donate · iyzico card method in the checkout block. GPL v2 or later. */
(function () {
	'use strict';
	var reg = window.wc && window.wc.wcBlocksRegistry;
	var settings = window.wc && window.wc.wcSettings;
	var E = window.wp && window.wp.element;
	var el = E && E.createElement;
	if (!reg || !settings || !el) return;
	var data = settings.getSetting('flxb_iyzico_data', {});
	var decode = (window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities) || function (s) { return s; };
	var title = decode(data.title || 'Card (iyzico)');

	function Description() {
		return data.description ? el('p', { className: 'flxb-blocks-iyzico' }, decode(data.description)) : null;
	}

	/* 2.1.0: our own card fields (assets/wc/card.js); the card goes to the server only with the order. */
	function Fields(props) {
		var ref = E.useRef(null);
		var api = E.useRef(null);
		E.useEffect(function () {
			if (ref.current && window.FLXBCardForm) api.current = window.FLXBCardForm.mount(ref.current, { blocks: true });
		}, []);
		var total = props.billing && props.billing.cartTotal ? props.billing.cartTotal.value : 0;
		E.useEffect(function () { if (api.current) api.current.reload(); }, [total]);
		var R = props.emitResponse && props.emitResponse.responseTypes;
		var onSetup = props.eventRegistration && props.eventRegistration.onPaymentSetup;
		E.useEffect(function () {
			if (!onSetup) return undefined;
			return onSetup(function () {
				var a = api.current;
				if (!a) return { type: R.ERROR, message: title };
				var bad = a.validate();
				if (bad) return { type: R.ERROR, message: bad, messageContext: props.emitResponse.noticeContexts && props.emitResponse.noticeContexts.PAYMENTS };
				return { type: R.SUCCESS, meta: { paymentMethodData: a.data() } };
			});
		}, [onSetup]);
		return el('div', null, el(Description), el('div', { ref: ref, className: 'flxb-card' }));
	}

	reg.registerPaymentMethod({
		name: 'flxb_iyzico',
		label: el('span', { className: 'flxb-blocks-iyzico__label' }, title),
		ariaLabel: title,
		content: data.fields ? el(Fields) : el(Description),
		edit: el(Description),
		/* Availability per cart comes from the server (cart.paymentMethods). */
		canMakePayment: function () { return true; },
		supports: { features: data.supports || ['products'] }
	});
})();
