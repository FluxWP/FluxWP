/**
 * Blocks <-> Flux Forms structure.
 *
 * The Flux Forms structure is a flat array: [0] form settings ("head"), then step rows,
 * field rows and option rows (options point at their field through `parent`). Every
 * row keeps keys the block editor does not manage (styling chosen in the panel, pro
 * flags...): a field block stores the row it came from in `efb` and only overwrites
 * the keys it owns, so a form survives the round trip panel -> blocks -> panel.
 */
import { __ } from '@wordpress/i18n';

export const FIELD_TYPES = ['text', 'email', 'tel', 'textarea', 'number', 'select', 'radio', 'checkbox', 'date'];
export const OPTION_TYPES = ['select', 'radio', 'checkbox'];

export function newId() {
	return Math.random().toString(36).slice(2, 11);
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

export function defaultStep(name) {
	return {
		id_: '1',
		type: 'step',
		dataId: '1',
		classes: '',
		id: '1',
		name: name || '',
		icon: 'bi-ui-checks-grid',
		step: '1',
		amount: 1,
		EfbVersion: 2,
		message: '',
		label_text_size: 'fs-5',
		el_text_size: 'fs-5',
		label_text_color: 'text-darkb',
		el_text_color: 'text-labelEfb',
		message_text_color: 'text-muted',
		icon_color: 'text-danger',
		visible: 0,
	};
}

function defaultFieldRow(type) {
	return {
		value: '',
		id: '',
		classes: '',
		label_text_size: 'fs-6',
		label_position: 'up',
		el_text_size: 'fs-6',
		label_text_color: 'text-labelEfb',
		el_border_color: 'border-d',
		el_text_color: 'text-labelEfb',
		message_text_color: 'text-muted',
		el_height: 'h-d-efb',
		label_align: 'txt-left',
		message_align: 'justify-content-start',
		el_align: 'justify-content-start',
		pro: false,
		icon_input: '',
		type,
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
	const head = { ...defaultHead(), ...(attrs.head || {}), formName: attrs.formName || '', steps: 1 };
	const rows = [head, { ...defaultStep(attrs.formName), ...(attrs.step || {}), step: '1', amount: 1 }];
	let amount = 2;
	blocks.forEach((block) => {
		const a = block.attributes || {};
		if ('flxf/raw' === block.name) {
			if (a.efb && a.efb.type) {
				rows.push({ ...a.efb, step: '1', amount: amount++ });
				(a.children || []).forEach((child) => rows.push({ ...child, step: '1', amount: amount++ }));
			}
			return;
		}
		const type = block.name.replace('flxf/', '');
		if (!FIELD_TYPES.includes(type)) {
			return;
		}
		const id = a.fieldId || newId();
		rows.push({
			...defaultFieldRow(type),
			...(a.efb || {}),
			id_: id,
			dataId: `${id}-id`,
			type,
			name: a.label || '',
			placeholder: a.placeholder || '',
			message: a.help || '',
			required: !!a.required,
			size: String(a.width || '100'),
			step: '1',
			amount: amount++,
		});
		if (OPTION_TYPES.includes(type)) {
			(a.options || []).forEach((opt) => {
				const oid = opt.id || newId();
				rows.push({
					id_: oid,
					dataId: `${oid}-id`,
					parent: id,
					type: 'option',
					value: opt.label || '',
					id_op: oid,
					step: '1',
					amount: amount++,
				});
			});
		}
	});
	return rows;
}

/**
 * Structure array -> { head, step, blocks: [[name, attributes]] }.
 *
 * @param {Array} structure Structure.
 * @return {Object} Result; `multiStep` true when the form has more than one step.
 */
export function fromStructure(structure) {
	const rows = Array.isArray(structure) ? structure : [];
	const head = rows[0] || {};
	const steps = rows.filter((r) => r && 'step' === r.type);
	if (steps.length > 1 || Number(head.steps) > 1) {
		return { multiStep: true, head, step: steps[0] || {}, blocks: [] };
	}
	const options = {};
	rows.forEach((r) => {
		if (r && 'option' === r.type && r.parent) {
			(options[r.parent] = options[r.parent] || []).push(r);
		}
	});
	const fields = rows
		.slice(1)
		.filter((r) => r && 'step' !== r.type && 'option' !== r.type)
		.sort((x, y) => Number(x.amount || 0) - Number(y.amount || 0));
	const blocks = fields.map((r) => {
		const type = r.type;
		const kids = options[r.id_] || [];
		if (!FIELD_TYPES.includes(type)) {
			return ['flxf/raw', { efb: r, children: kids }];
		}
		const { id_, dataId, name, placeholder, message, required, size, step, amount, ...rest } = r;
		const attrs = {
			fieldId: id_ || newId(),
			label: name || '',
			placeholder: placeholder || '',
			help: message || '',
			required: !!required && '0' !== required && 0 !== required,
			width: ['100', '50', '33'].includes(String(size)) ? String(size) : '100',
			efb: rest,
		};
		if (OPTION_TYPES.includes(type)) {
			attrs.options = kids
				.sort((x, y) => Number(x.amount || 0) - Number(y.amount || 0))
				.map((o) => ({ id: o.id_ || o.id_op || newId(), label: o.value || '' }));
		}
		return [`flxf/${type}`, attrs];
	});
	return { multiStep: false, head, step: steps[0] || {}, blocks };
}
