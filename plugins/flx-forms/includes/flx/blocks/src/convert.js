/**
 * Blocks <-> Flux Forms structure.
 *
 * The Flux Forms structure is a flat array: [0] form settings ("head"), then step rows,
 * field rows and option rows (options point at their field through `parent`). Every
 * row keeps keys the block editor does not manage (styling chosen in the panel, pro
 * flags...): a field block stores the row it came from in `efb` and only overwrites
 * the keys it owns, so a form survives the round trip panel -> blocks -> panel.
 *
 * Steps: a flxf/step block starts a step; fields before the first one are in step 1.
 * Conditional display: each field block's `logic` becomes one rule in
 * head.logic_rules (show or hide that field); other rules are kept as they are.
 */
import { __ } from '@wordpress/i18n';
import { TYPES, slugOf, typeOfBlock, FIELD_TYPES, OPTION_TYPES, PAYMENT_TYPES } from './types';

export { FIELD_TYPES, OPTION_TYPES };

// Short ids for fields, options and the block's CSS scope (not secrets).
export function newId(len = 9) {
	const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
	const bytes = new Uint8Array(len);
	window.crypto.getRandomValues(bytes);
	return Array.from(bytes, (b) => chars[b % chars.length]).join('');
}

export function defaultHead() {
	return {
		type: 'form',
		steps: 1,
		formName: '',
		email: '',
		trackingCode: true,
		EfbVersion: 2,
		button_single_text: __('Send', 'flx-forms'),
		button_color: 'btn-primary',
		icon: 'bi-send',
		button_Next_text: __('Next', 'flx-forms'),
		button_Previous_text: __('Previous', 'flx-forms'),
		button_Next_icon: 'bi-chevron-right',
		button_Previous_icon: 'bi-chevron-left',
		button_state: 'single',
		label_text_color: 'text-light',
		el_text_color: 'text-light',
		message_text_color: 'text-muted',
		icon_color: 'text-light',
		el_height: 'h-d-efb',
		email_to: false,
		show_icon: false,
		show_pro_bar: false,
		captcha: false,
		private: false,
		sendEmail: false,
		font: true,
		stateForm: 0,
		thank_you: 'msg',
		thank_you_message: {
			icon: 'bi-hand-thumbs-up',
			thankYou: __('Thank you', 'flx-forms'),
			done: __('Your message has been sent.', 'flx-forms'),
			trackingCode: __('Confirmation code', 'flx-forms'),
			error: __('Error', 'flx-forms'),
			pleaseFillInRequiredFields: __('Please fill in the required fields.', 'flx-forms'),
		},
		email_temp: '',
		dShowBg: true,
	};
}

export function defaultStep(name, n = 1) {
	return {
		id_: String(n),
		type: 'step',
		dataId: String(n),
		classes: '',
		id: String(n),
		name: name || '',
		icon: 'bi-ui-checks-grid',
		step: n,
		amount: 1,
		EfbVersion: 2,
		message: '',
		label_text_size: 'fs-5',
		el_text_size: 'fs-5',
		label_text_color: 'text-darkb',
		el_text_color: 'text-labelEfb',
		message_text_color: 'text-muted',
		icon_color: 'text-danger',
		visible: 1,
	};
}

/** Keys every new row starts with, plus the type's own defaults. */
export function defaultFieldRow(type) {
	return {
		value: '',
		id: '',
		classes: '',
		label_text_size: 'fs-6',
		label_position: 'up',
		el_text_size: 'fs-6',
		label_text_color: 'text-labelEfb',
		el_border_color: 'border-d',
		el_text_color: 'stripe' === type ? 'text-white' : 'text-labelEfb',
		message_text_color: 'text-muted',
		el_height: 'h-d-efb',
		label_align: 'txt-left',
		message_align: 'justify-content-start',
		el_align: 'justify-content-start',
		pro: false,
		icon_input: '',
		...((TYPES[type] && TYPES[type].defaults) || {}),
		type,
	};
}

// Image URLs are stored with '@efb@' for '/' (as the panel does).
export const encodeSrc = (url) =>
	String(url || '')
		.replace(/(https?):\/\//g, '$1:@efb@')
		.replace(/\//g, '@efb@');
export const decodeSrc = (src) => String(src || '').replace(/@efb@/g, '/');

const isOn = (v) => !!v && '0' !== v && 0 !== v && 'false' !== v;

/** A field block's logic attribute -> rule. */
function ruleOf(fieldId, label, logic) {
	if (!logic || !logic.action || !Array.isArray(logic.items)) {
		return null;
	}
	const items = logic.items
		.filter((c) => c && c.field_id)
		.map((c) => ({ type: 'condition', source: 'field', field_id: c.field_id, compare: c.compare || 'is', value: c.value ?? '' }));
	if (!items.length) {
		return null;
	}
	return {
		id: logic.id || `blk-${fieldId}`,
		name: logic.name || label || fieldId,
		scope: 'field',
		enabled: true,
		priority: 10,
		stop_processing: false,
		conditions: { type: 'group', operator: 'OR' === logic.operator ? 'OR' : 'AND', items },
		actions: [{ type: 'hide' === logic.action ? 'hide_field' : 'show_field', target: fieldId }],
	};
}

/** A rule the blocks can show: one show/hide action, flat field conditions. */
function simpleRule(rule) {
	if (!rule || false === rule.enabled || (rule.scope && 'field' !== rule.scope)) {
		return null;
	}
	const acts = rule.actions || [];
	if (1 !== acts.length || !['show_field', 'hide_field'].includes(acts[0].type) || !acts[0].target) {
		return null;
	}
	const g = rule.conditions || {};
	const items = g.items || [];
	if (g.negate || !items.length || items.some((c) => !c || 'condition' !== (c.type || 'condition') || (c.source && 'field' !== c.source) || c.items)) {
		return null;
	}
	return {
		target: String(acts[0].target),
		logic: {
			id: rule.id || '',
			name: rule.name || '',
			action: 'hide_field' === acts[0].type ? 'hide' : 'show',
			operator: 'OR' === g.operator ? 'OR' : 'AND',
			items: items.map((c) => ({ field_id: String(c.field_id || ''), compare: c.compare || 'is', value: c.value ?? '' })),
		},
	};
}

/**
 * Inner blocks of a flxf/form block -> structure array.
 *
 * @param {Object} attrs  Form block attributes.
 * @param {Array}  blocks Inner blocks.
 * @return {Array} Structure.
 */
export function toStructure(attrs, blocks) {
	const head = { ...defaultHead(), ...(attrs.head || {}), formName: attrs.formName || '' };
	const steps = [];
	const fields = [];
	const rules = [];
	let amount = 1;
	let step = 1;
	let seenField = false;
	const firstStep = { ...defaultStep(attrs.formName), ...(attrs.step || {}) };
	steps.push(firstStep);
	let gateway = '';

	const pushStep = (a) => {
		const stepRow = { ...defaultStep('', 1), ...(a.efb || {}), name: a.label || '', message: a.help || '' };
		if (a.icon) {
			stepRow.icon = a.icon;
		}
		if (!seenField && 1 === steps.length && !steps.blockDefined) {
			// A step block before any field names step 1.
			steps[0] = { ...steps[0], ...stepRow };
			steps.blockDefined = true;
			return;
		}
		step += 1;
		steps.push(stepRow);
	};

	blocks.forEach((block) => {
		const a = block.attributes || {};
		if ('flxf/step' === block.name) {
			pushStep(a);
			return;
		}
		if ('flxf/raw' === block.name) {
			if (a.efb && a.efb.type) {
				seenField = true;
				fields.push({ ...a.efb, step, _order: amount++ });
				(a.children || []).forEach((child) => fields.push({ ...child, step, _order: amount++ }));
			}
			return;
		}
		const type = typeOfBlock(block.name);
		if (!type) {
			return;
		}
		seenField = true;
		const def = TYPES[type];
		const id = a.fieldId || newId();
		const row = {
			...defaultFieldRow(type),
			...(a.efb || {}),
			id_: id,
			dataId: `${id}-id`,
			type,
			name: false === def.label ? (a.efb && a.efb.name) || def.title : a.label || '',
			placeholder: a.placeholder || '',
			message: a.help || '',
			required: def.noRequired || false === def.label ? 0 : a.required ? 1 : 0,
			size: String(a.width || '100'),
			step,
			_order: amount++,
		};
		if (def.payment) {
			gateway = def.payment;
		}
		fields.push(row);
		if (def.options) {
			(a.options || []).forEach((opt) => {
				const oid = opt.id || newId();
				const o = {
					...(opt.efb || {}),
					id_: oid,
					dataId: `${oid}-id`,
					parent: id,
					type: def.options,
					value: opt.label || '',
					id_op: (opt.efb && opt.efb.id_op) || oid,
					step,
					_order: amount++,
				};
				(def.optionFields || []).forEach((k) => {
					if ('price' === k) {
						o.price = Number(opt.price) || 0;
					} else if ('src' === k) {
						o.src = encodeSrc(opt.src);
					} else {
						o[k] = opt[k] || '';
					}
				});
				fields.push(o);
			});
		}
		const rule = ruleOf(id, a.label, a.logic);
		if (rule) {
			rules.push(rule);
		}
	});

	// Positions: steps first in the panel's order, then rows as they appear.
	const rows = [head];
	let pos = 1;
	steps.forEach((s, i) => {
		rows.push({ ...s, id_: String(i + 1), dataId: String(i + 1), id: String(i + 1), step: i + 1, amount: pos++ });
		fields
			.filter((f) => f.step === i + 1)
			.forEach((f) => {
				const { _order, ...rest } = f;
				rows.push({ ...rest, step: String(i + 1), amount: pos++ });
			});
	});

	head.steps = steps.length;
	head.button_state = steps.length > 1 ? 'multi' : 'single';
	// Rules the blocks do not show (fromStructure leaves them in the head) stay.
	const made = new Set(rules.map((r) => r.id));
	const kept = (Array.isArray(head.logic_rules) ? head.logic_rules : []).filter((r) => r && !made.has(r.id));
	if (kept.length || rules.length) {
		head.logic_rules = [...rules, ...kept];
	} else {
		delete head.logic_rules;
	}
	if (gateway) {
		head.type = 'payment';
		head.getway = gateway;
		head.currency = head.currency || 'try';
		head.paymentmethod = head.paymentmethod || 'charge';
	} else if ('payment' === head.type) {
		head.type = 'form';
		delete head.getway;
	}
	return rows;
}

/**
 * Structure array -> { head, step, blocks: [[name, attributes]] }.
 *
 * @param {Array} structure Structure.
 * @return {Object} Result.
 */
export function fromStructure(structure) {
	const rows = Array.isArray(structure) ? structure : [];
	const head = { ...(rows[0] || {}) };
	const steps = rows
		.filter((r) => r && 'step' === r.type)
		.sort((x, y) => Number(x.step || x.id_ || 0) - Number(y.step || y.id_ || 0));
	const children = {};
	rows.forEach((r) => {
		if (r && r.parent && ('option' === r.type || 'r_matrix' === r.type)) {
			(children[r.parent] = children[r.parent] || []).push(r);
		}
	});
	const fields = rows
		.slice(1)
		.filter((r) => r && 'step' !== r.type && !(r.parent && ('option' === r.type || 'r_matrix' === r.type)))
		.sort((x, y) => Number(x.step || 1) - Number(y.step || 1) || Number(x.amount || 0) - Number(y.amount || 0));

	// Simple show/hide rules move onto their field block.
	const logicOf = {};
	const fieldIds = new Set(fields.map((f) => String(f.id_)));
	(Array.isArray(head.logic_rules) ? head.logic_rules : []).forEach((r) => {
		const s = simpleRule(r);
		if (s && fieldIds.has(s.target) && !logicOf[s.target]) {
			logicOf[s.target] = s.logic;
		}
	});

	const blocks = [];
	const multi = steps.length > 1;
	let current = 0;
	const stepBlock = (s) => [
		'flxf/step',
		{
			label: s.name || '',
			help: s.message || '',
			icon: s.icon || '',
			efb: (({ id_, dataId, id, name, message, step, amount, type, ...rest }) => rest)(s),
		},
	];
	if (multi) {
		blocks.push(stepBlock(steps[0]));
		current = Number(steps[0].step || 1);
	}
	fields.forEach((r) => {
		const n = Number(r.step || 1);
		if (multi && n !== current) {
			steps
				.filter((s) => Number(s.step || s.id_) > current && Number(s.step || s.id_) <= n)
				.forEach((s) => blocks.push(stepBlock(s)));
			current = n;
		}
		const type = r.type;
		const def = TYPES[type];
		const kids = (children[r.id_] || []).sort((x, y) => Number(x.amount || 0) - Number(y.amount || 0));
		if (!def) {
			blocks.push(['flxf/raw', { efb: r, children: kids }]);
			return;
		}
		const { id_, dataId, name, placeholder, message, required, size, step, amount, ...rest } = r;
		const attrs = {
			fieldId: id_ || newId(),
			label: false === def.label ? '' : name || '',
			placeholder: placeholder || '',
			help: message || '',
			required: isOn(required),
			width: ['100', '50', '33'].includes(String(size)) ? String(size) : '100',
			efb: false === def.label ? { ...rest, name } : rest,
		};
		if (def.options) {
			attrs.options = kids.map((o) => {
				const { id_: oid, dataId: od, parent, type: ot, value, step: os, amount: oa, price, src, sub_value, ...orest } = o;
				const opt = { id: oid || o.id_op || newId(), label: value || '' };
				(def.optionFields || []).forEach((k) => {
					if ('price' === k) {
						opt.price = Number(price) || 0;
					} else if ('src' === k) {
						opt.src = decodeSrc(src);
					} else {
						opt[k] = o[k] || '';
					}
				});
				if (Object.keys(orest).some((k) => 'id_op' !== k || orest.id_op !== oid)) {
					opt.efb = orest;
				}
				return opt;
			});
		}
		if (logicOf[String(id_)]) {
			attrs.logic = logicOf[String(id_)];
		}
		blocks.push([`flxf/${slugOf(type)}`, attrs]);
	});
	if (multi) {
		steps.filter((s) => Number(s.step || s.id_) > current).forEach((s) => blocks.push(stepBlock(s)));
	}

	// Rules now on field blocks leave the head (toStructure adds them back).
	if (Array.isArray(head.logic_rules)) {
		head.logic_rules = head.logic_rules.filter((r) => {
			const s = simpleRule(r);
			return !(s && logicOf[s.target] && logicOf[s.target].id === (r.id || ''));
		});
	}
	return { head, step: steps[0] || {}, blocks };
}

export { PAYMENT_TYPES };
