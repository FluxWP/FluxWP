/**
 * The Flux Block Editor's own form blocks (flx/form and flx/advanced-form) are
 * replaced by Flux Forms. They leave the inserter (see blocks.php) but keep working
 * on existing pages; this adds "Move to Flux Forms" to them: the fields are mapped to
 * Flux Forms field blocks and the block becomes a Flux Form, which is created in
 * Flux Forms when the page is saved.
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { createBlock, parse } from '@wordpress/blocks';
import { BlockControls, InspectorControls, store as blockEditorStore } from '@wordpress/block-editor';
import { PanelBody, ToolbarGroup, ToolbarButton, Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { newId } from './convert';

const LEGACY = ['flx/form', 'flx/advanced-form'];

const widthOf = (w) => {
	const n = parseFloat(Array.isArray(w) ? w[0] : w);
	if (!n || n > 66) {
		return '100';
	}
	return n > 40 ? '50' : '33';
};

const optionsOf = (opts) =>
	(Array.isArray(opts) ? opts : [])
		.filter((o) => o && (o.label || o.value))
		.map((o) => ({ id: newId(), label: String(o.label || o.value) }));

/**
 * One legacy field -> [block name, attributes], or null when it has no field.
 *
 * @param {string} type Legacy type (text, email, tel, telephone, accept...).
 * @param {Object} f    Normalised field: label, placeholder, help, required, width, options, value, multi, min, max, maxSize.
 * @return {Array|null} Block.
 */
export function mapField(type, f) {
	const base = {
		label: f.label || '',
		placeholder: f.placeholder || '',
		help: f.help || '',
		required: !!f.required,
		width: widthOf(f.width),
	};
	const efb = {};
	if (f.value) {
		efb.value = String(f.value);
	}
	switch (type) {
		case 'text':
		case 'email':
		case 'textarea':
		case 'date':
			return [`flxf/${type}`, { ...base, efb }];
		case 'time':
			return ['flxf/text', { ...base, efb }];
		case 'tel':
		case 'telephone':
			return ['flxf/tel', { ...base, efb }];
		case 'number':
			if ('' !== (f.min ?? '')) {
				efb.milen = String(f.min);
			}
			if ('' !== (f.max ?? '')) {
				efb.mlen = String(f.max);
			}
			return ['flxf/number', { ...base, efb }];
		case 'hidden':
			return ['flxf/text', { ...base, required: false, efb: { ...efb, hidden: 1 } }];
		case 'select':
			return [f.multi ? 'flxf/multiselect' : 'flxf/select', { ...base, options: optionsOf(f.options), efb: f.multi ? { maxSelect: 99, minSelect: 0 } : {} }];
		case 'checkbox':
		case 'radio':
			return [`flxf/${type}`, { ...base, options: optionsOf(f.options), efb: f.inline ? { op_style: '3' } : {} }];
		case 'accept':
			// The consent text is the option; without a separate text the label is hidden so it is not shown twice.
			return [
				'flxf/terms',
				{ ...base, help: '', options: [{ id: newId(), label: f.help || f.label || __('I accept the terms and conditions.', 'flx-forms') }], efb: f.help ? {} : { hflabel: 1 } },
			];
		case 'file':
			return ['flxf/file', { ...base, efb: { file: 'allformat', value: 'allformat', ...(f.maxSize ? { max_fsize: String(f.maxSize) } : {}) } }];
		default:
			return null;
	}
}

/**
 * flx/form attributes -> { name, head, blocks }.
 *
 * @param {Object} a Attributes.
 * @return {Object} Result.
 */
export function fromSimpleForm(a) {
	const blocks = (a.fields || [])
		.map((f) =>
			mapField(f.type, {
				label: f.label,
				placeholder: f.placeholder,
				help: f.description,
				required: f.required,
				width: f.width,
				options: f.options,
				value: f.default,
				multi: f.multiSelect,
				inline: f.inline,
				min: f.min,
				max: f.max,
			})
		)
		.filter(Boolean);
	const msg = (a.messages && a.messages[0]) || {};
	const mail = (a.email && a.email[0]) || {};
	const sub = (a.submit && a.submit[0]) || {};
	return {
		blocks,
		head: headOf({
			button: a.submitLabel || sub.label,
			success: msg.success,
			emailTo: (a.actions || ['email']).includes('email') ? mail.emailTo : '',
			captcha: !!a.recaptcha,
		}),
	};
}

function headOf({ button, success, emailTo, captcha }) {
	const head = {};
	if (button) {
		head.button_single_text = button;
	}
	if (success) {
		head.thank_you_message = {
			icon: 'bi-hand-thumbs-up',
			thankYou: __('Thank you', 'flx-forms'),
			done: success,
			trackingCode: __('Confirmation code', 'flx-forms'),
			error: __('Error', 'flx-forms'),
			pleaseFillInRequiredFields: __('Please fill in the required fields.', 'flx-forms'),
		};
	}
	const to = String(emailTo || '')
		.split(/[,;\s]+/)
		.find((x) => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(x));
	if (to) {
		head.sendEmail = true;
		head.email = to;
	}
	if (captcha) {
		head.captcha = true;
	}
	return head;
}

/**
 * A flxbk_form post (REST, context=edit) -> { name, head, blocks }.
 *
 * @param {Object} post Post.
 * @return {Object} Result.
 */
export function fromAdvancedForm(post) {
	const fields = [];
	let button = '';
	let captcha = false;
	const walk = (list) =>
		(list || []).forEach((b) => {
			const m = /^flx\/advanced-form-(.+)$/.exec(b.name || '');
			if (m) {
				const a = b.attributes || {};
				if ('submit' === m[1]) {
					button = a.text || a.label || button;
				} else if ('captcha' === m[1]) {
					captcha = true;
				} else {
					fields.push(
						mapField(m[1], {
							label: a.label,
							placeholder: a.placeholder,
							help: a.helpText || a.description,
							required: a.required,
							width: a.maxWidth,
							options: a.options,
							value: a.defaultValue,
							multi: a.multiSelect,
							inline: a.inline,
							min: a.minValue,
							max: a.maxValue,
							maxSize: a.maxSizeMb,
						})
					);
				}
			}
			walk(b.innerBlocks);
		});
	walk(parse((post.content && (post.content.raw ?? post.content.rendered)) || ''));
	const meta = post.meta || {};
	const actions = Array.isArray(meta._flxbk_form_actions) ? meta._flxbk_form_actions : ['email'];
	return {
		name: (post.title && (post.title.raw ?? post.title.rendered)) || '',
		blocks: fields.filter(Boolean),
		head: headOf({
			button,
			success: meta._flxbk_form_messages && meta._flxbk_form_messages.success,
			emailTo: actions.includes('email') && meta._flxbk_form_email ? meta._flxbk_form_email.emailTo : '',
			captcha,
		}),
	};
}

function MoveControls({ name, attributes, clientId }) {
	const { replaceBlocks } = useDispatch(blockEditorStore);
	const { createSuccessNotice, createErrorNotice } = useDispatch(noticesStore);
	const [busy, setBusy] = useState(false);
	const move = async () => {
		const dialog = window.flxDialog;
		const ok = dialog
			? await dialog.confirm({
					title: __('Move to Flux Forms', 'flx-forms'),
					message: __('The fields of this form become Flux Forms fields. The new form is created in Flux Forms when you save the page; responses, emails and automations then run there.', 'flx-forms'),
					ok: __('Move', 'flx-forms'),
					cancel: __('Cancel', 'flx-forms'),
			  })
			: true;
		if (!ok) {
			return;
		}
		setBusy(true);
		try {
			let res;
			if ('flx/advanced-form' === name) {
				const post = await apiFetch({ path: `/wp/v2/flxbk_form/${attributes.id}?context=edit` });
				res = fromAdvancedForm(post);
			} else {
				res = fromSimpleForm(attributes);
			}
			if (!res.blocks.length) {
				throw new Error(__('This form has no fields to move.', 'flx-forms'));
			}
			replaceBlocks(
				clientId,
				createBlock(
					'flxf/form',
					{ formName: res.name || '', head: res.head },
					res.blocks.map(([n, a]) => createBlock(n, a))
				)
			);
			createSuccessNotice(__('The form was moved to Flux Forms. Save the page to create it.', 'flx-forms'), { type: 'snackbar' });
		} catch (e) {
			createErrorNotice(e?.message || __('The form could not be moved.', 'flx-forms'), { type: 'snackbar' });
			setBusy(false);
		}
	};
	const editor = window.wp.data.select('core/editor');
	if (('flx/advanced-form' === name && !attributes.id) || (editor && 'flxbk_form' === editor.getCurrentPostType())) {
		return null;
	}
	return (
		<>
			<BlockControls group="other">
				<ToolbarGroup>
					<ToolbarButton icon="feedback" label={__('Move to Flux Forms', 'flx-forms')} onClick={move} disabled={busy}>
						{__('Move to Flux Forms', 'flx-forms')}
					</ToolbarButton>
				</ToolbarGroup>
			</BlockControls>
			<InspectorControls>
				<PanelBody title={__('Flux Forms', 'flx-forms')}>
					<p className="flxf-b-help">
						{__('Forms are now made with Flux Forms. This form keeps working as it is; move it to manage its responses, emails and automations in Flux Forms.', 'flx-forms')}
					</p>
					<Button variant="primary" onClick={move} disabled={busy}>
						{__('Move to Flux Forms', 'flx-forms')}
					</Button>
				</PanelBody>
			</InspectorControls>
		</>
	);
}

export function registerLegacy() {
	addFilter(
		'editor.BlockEdit',
		'flxf/legacy-forms',
		createHigherOrderComponent(
			(BlockEdit) => (props) =>
				LEGACY.includes(props.name) && (window.flxfBlocks || {}).canManage ? (
					<>
						<BlockEdit {...props} />
						<MoveControls {...props} />
					</>
				) : (
					<BlockEdit {...props} />
				),
			'withFlxfMove'
		)
	);
}
