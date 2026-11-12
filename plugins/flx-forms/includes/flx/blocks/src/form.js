/**
 * flxf/form: the form container. Saves its fields into the linked Flux Forms form
 * whenever the post is saved, and loads a panel-made form back into blocks.
 */
import { __, sprintf } from '@wordpress/i18n';
import { registerBlockType, createBlock } from '@wordpress/blocks';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	BlockControls,
	PanelColorSettings,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
	RangeControl,
	Button,
	Notice,
	Placeholder,
	Spinner,
	ToolbarGroup,
	ToolbarButton,
	ExternalLink,
} from '@wordpress/components';
import { useSelect, useDispatch, select, subscribe } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { toStructure, fromStructure, defaultHead, newId } from './convert';
import { FIELD_BLOCK_NAMES } from './fields';
import { TYPES } from './types';

const ALLOWED = [...FIELD_BLOCK_NAMES, 'flxf/step', 'flxf/raw'];
const PAYMENT_BLOCKS = Object.keys(TYPES)
	.filter((t) => TYPES[t].payment)
	.map((t) => `flxf/${TYPES[t].slug || t}`);
const CURRENCIES = ['try', 'usd', 'eur', 'gbp', 'chf', 'cad', 'aud', 'sar', 'aed', 'jpy'];
const cfg = window.flxfBlocks || {};

const TEMPLATE = [
	['flxf/text', { label: __('Full name', 'flx-forms'), required: true, width: '50' }],
	['flxf/email', { label: __('Email', 'flx-forms'), required: true, width: '50' }],
	['flxf/textarea', { label: __('Message', 'flx-forms'), required: true }],
];

function toBlocks(list) {
	return list.map(([name, attrs]) => createBlock(name, attrs));
}

function designStyle(d = {}) {
	const s = {};
	const map = {
		labelColor: '--flxf-b-label',
		inputText: '--flxf-b-input-text',
		inputBg: '--flxf-b-input-bg',
		inputBorder: '--flxf-b-input-border',
		buttonBg: '--flxf-b-button-bg',
		buttonText: '--flxf-b-button-text',
		cardBg: '--flxf-b-card-bg',
	};
	Object.entries(map).forEach(([k, v]) => d[k] && (s[v] = d[k]));
	if (d.radius !== undefined && d.radius !== '') {
		s['--flxf-b-radius'] = `${d.radius}px`;
	}
	if (d.gap !== undefined && d.gap !== '') {
		s['--flxf-b-gap'] = `${d.gap}px`;
	}
	return s;
}

// Posts saved while a new form was being created are saved once more, so the new
// form id lands in the post content.
let resaveQueued = false;

function Edit({ attributes, setAttributes, clientId }) {
	const { formId, formName, head = {}, design = {}, uid } = attributes;
	const [state, setState] = useState(formId ? 'loading' : 'ready');
	const [forms, setForms] = useState(null);
	const [error, setError] = useState('');
	const [conflict, setConflict] = useState(false);
	// While the panel version differs, saving the page must not overwrite the form.
	const conflictRef = useRef(false);
	conflictRef.current = conflict;
	const hashRef = useRef('');
	const { replaceInnerBlocks, __unstableMarkNextChangeAsNotPersistent: notPersistent } = useDispatch(blockEditorStore);
	const innerCount = useSelect((s) => s(blockEditorStore).getBlockCount(clientId), [clientId]);
	const hasPayment = useSelect(
		(s) => s(blockEditorStore).getBlocks(clientId).some((b) => PAYMENT_BLOCKS.includes(b.name)),
		[clientId]
	);
	const emailFields = useSelect(
		(s) =>
			s(blockEditorStore)
				.getBlocks(clientId)
				.filter((b) => 'flxf/email' === b.name)
				.map((b) => ({ value: b.attributes.fieldId, label: b.attributes.label || b.attributes.fieldId })),
		[clientId]
	);
	const setHead = (patch) => setAttributes({ head: { ...head, ...patch } });
	const setDesign = (patch) => setAttributes({ design: { ...design, ...patch } });
	const h = { ...defaultHead(), ...head };

	useEffect(() => {
		if (!uid) {
			setAttributes({ uid: newId(6) });
		}
	}, []);

	// Load the stored form: fill empty blocks, or detect a change made in the panel.
	const load = (id, replace) => {
		setState('loading');
		return apiFetch({ path: `/flxf/v1/block-forms/${id}` })
			.then((res) => {
				hashRef.current = res.hash;
				const parsed = fromStructure(res.structure);
				if (replace || !innerCount) {
					setAttributes({ formId: res.id, formName: res.name, head: parsed.head, step: parsed.step });
					replaceInnerBlocks(clientId, toBlocks(parsed.blocks), false);
					setConflict(false);
				} else {
					// Form settings (button, thank-you, e-mail...) always follow the stored form,
					// so saving the page never writes back an older copy of them.
					if (JSON.stringify(attributes.head || {}) !== JSON.stringify(parsed.head) || attributes.formName !== res.name) {
						if (notPersistent) {
							notPersistent();
						}
						setAttributes({ formName: res.name, head: parsed.head, step: parsed.step });
					}
					const mine = fingerprint(toStructure({ ...attributes, head: parsed.head }, select(blockEditorStore).getBlocks(clientId)));
					const theirs = fingerprint(toStructure({ ...attributes, head: parsed.head }, toBlocks(parsed.blocks)));
					setConflict(mine !== theirs);
				}
				setState('ready');
			})
			.catch((e) => {
				setError(e?.message || __('The form could not be loaded.', 'flx-forms'));
				setState('ready');
			});
	};

	useEffect(() => {
		if (formId) {
			load(formId, false);
		}
	}, []);

	// Save the form together with the post.
	const attrsRef = useRef(attributes);
	attrsRef.current = attributes;
	const busy = useRef(false);
	useEffect(() => {
		let wasSaving = false;
		return subscribe(() => {
			const editor = select('core/editor');
			if (!editor) {
				return;
			}
			const saving = editor.isSavingPost() && !editor.isAutosavingPost();
			if (saving && !wasSaving && !busy.current) {
				const a = attrsRef.current;
				const blocks = select(blockEditorStore).getBlocks(clientId);
				if (!blocks.length || !cfg.canManage || conflictRef.current) {
					wasSaving = saving;
					return;
				}
				busy.current = true;
				const isNew = !a.formId;
				apiFetch({
					path: '/flxf/v1/block-forms',
					method: 'POST',
					data: {
						id: a.formId || 0,
						name: a.formName || select('core/editor').getEditedPostAttribute('title') || __('Form', 'flx-forms'),
						structure: toStructure(a, blocks),
						hash: hashRef.current,
					},
				})
					.then((res) => {
						hashRef.current = res.hash;
						setError('');
						setConflict(false);
						if (isNew) {
							setAttributes({ formId: res.id, formName: res.name });
							if (!resaveQueued) {
								resaveQueued = true;
								const off = subscribe(() => {
									const ed = select('core/editor');
									if (!ed.isSavingPost()) {
										off();
										resaveQueued = false;
										window.wp.data.dispatch('core/editor').savePost();
									}
								});
							}
						}
					})
					.catch((e) => {
						if ('flxf_form_conflict' === e?.code) {
							setConflict(true);
							return;
						}
						setError(e?.message || __('The form could not be saved.', 'flx-forms'));
					})
					.finally(() => {
						busy.current = false;
					});
			}
			wasSaving = saving;
		});
	}, [clientId]);

	const forceSave = () => {
		const blocks = select(blockEditorStore).getBlocks(clientId);
		apiFetch({
			path: '/flxf/v1/block-forms',
			method: 'POST',
			data: { id: formId, name: formName, structure: toStructure(attributes, blocks), force: true },
		})
			.then((res) => {
				hashRef.current = res.hash;
				setConflict(false);
				setError('');
			})
			.catch((e) => setError(e?.message || __('The form could not be saved.', 'flx-forms')));
	};

	const blockProps = useBlockProps({ className: 'flxf-bform flxf-bform-editor', style: designStyle(design) });
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'flxf-b-fields' },
		{ allowedBlocks: ALLOWED, templateLock: false }
	);

	if (!formId && !innerCount && 'choose' !== state) {
		return (
			<div {...blockProps}>
				<Placeholder
					icon="feedback"
					label={__('Flux Form', 'flx-forms')}
					instructions={__('Build a new form here, or edit a form made in the Flux Forms panel. Both edit the same form.', 'flx-forms')}
				>
					<Button variant="primary" onClick={() => replaceInnerBlocks(clientId, toBlocks(TEMPLATE), false)}>
						{__('New form', 'flx-forms')}
					</Button>
					<Button
						variant="secondary"
						onClick={() => {
							setState('choose');
							apiFetch({ path: '/flxf/v1/block-forms' }).then(setForms).catch(() => setForms([]));
						}}
					>
						{__('Choose an existing form', 'flx-forms')}
					</Button>
				</Placeholder>
			</div>
		);
	}
	if ('choose' === state) {
		return (
			<div {...blockProps}>
				<Placeholder icon="feedback" label={__('Choose a form', 'flx-forms')}>
					{null === forms ? (
						<Spinner />
					) : (
						<SelectControl
							__nextHasNoMarginBottom
							value=""
							options={[
								{ value: '', label: __('— Select —', 'flx-forms') },
								...forms.map((f) => ({ value: String(f.id), label: `${f.name} (#${f.id})` })),
							]}
							onChange={(v) => v && load(Number(v), true)}
						/>
					)}
					<Button variant="tertiary" onClick={() => setState('ready')}>
						{__('Cancel', 'flx-forms')}
					</Button>
				</Placeholder>
			</div>
		);
	}

	return (
		<div {...blockProps}>
			{formId > 0 && (
				<BlockControls>
					<ToolbarGroup>
						<ToolbarButton
							icon="update"
							label={__('Reload from Flux Forms', 'flx-forms')}
							onClick={() => load(formId, true)}
						/>
					</ToolbarGroup>
				</BlockControls>
			)}
			<InspectorControls>
				<PanelBody title={__('Form', 'flx-forms')}>
					<TextControl
						__nextHasNoMarginBottom
						label={__('Form name', 'flx-forms')}
						help={__('Shown in the Flux Forms panel.', 'flx-forms')}
						value={formName}
						onChange={(v) => setAttributes({ formName: v })}
					/>
					<TextControl
						__nextHasNoMarginBottom
						label={__('Button text', 'flx-forms')}
						value={h.button_single_text}
						onChange={(v) => setHead({ button_single_text: v })}
					/>
					{formId > 0 ? (
						<p className="flxf-b-links">
							{/* translators: %d: Flux Forms form id. */}
							{sprintf(__('Flux Forms form #%d', 'flx-forms'), formId)} ·{' '}
							<ExternalLink href={`${cfg.panelUrl}&state=edit-form&id=${formId}`}>{__('Open in panel', 'flx-forms')}</ExternalLink>{' '}
							· <ExternalLink href={`${cfg.panelUrl}&state=show-messages&id=${formId}&form_type=form`}>{__('Responses', 'flx-forms')}</ExternalLink>
						</p>
					) : (
						<p className="flxf-b-help">{__('The form is created in Flux Forms when you save the page.', 'flx-forms')}</p>
					)}
				</PanelBody>
				{hasPayment && (
					<PanelBody title={__('Payment', 'flx-forms')}>
						<SelectControl
							__nextHasNoMarginBottom
							label={__('Currency', 'flx-forms')}
							value={String(h.currency || 'try').toLowerCase()}
							options={[...new Set([String(h.currency || 'try').toLowerCase(), ...CURRENCIES])].map((c) => ({ value: c, label: c.toUpperCase() }))}
							onChange={(v) => setHead({ currency: v })}
						/>
						<SelectControl
							__nextHasNoMarginBottom
							label={__('Payment type', 'flx-forms')}
							value={h.paymentmethod || 'charge'}
							options={[
								{ value: 'charge', label: __('One-time', 'flx-forms') },
								{ value: 'day', label: __('Daily subscription', 'flx-forms') },
								{ value: 'week', label: __('Weekly subscription', 'flx-forms') },
								{ value: 'month', label: __('Monthly subscription', 'flx-forms') },
								{ value: 'year', label: __('Yearly subscription', 'flx-forms') },
							]}
							onChange={(v) => setHead({ paymentmethod: v })}
						/>
						<p className="flxf-b-help">{__('Card payments use the Stripe keys in Flux Forms settings.', 'flx-forms')}</p>
					</PanelBody>
				)}
				<PanelBody title={__('Steps', 'flx-forms')} initialOpen={false}>
					<p className="flxf-b-help">{__('Add a Step block to split the form into steps.', 'flx-forms')}</p>
					<TextControl
						__nextHasNoMarginBottom
						label={__('Next button', 'flx-forms')}
						value={h.button_Next_text}
						onChange={(v) => setHead({ button_Next_text: v })}
					/>
					<TextControl
						__nextHasNoMarginBottom
						label={__('Previous button', 'flx-forms')}
						value={h.button_Previous_text}
						onChange={(v) => setHead({ button_Previous_text: v })}
					/>
				</PanelBody>
				<PanelBody title={__('After sending', 'flx-forms')} initialOpen={false}>
					<TextControl
						__nextHasNoMarginBottom
						label={__('Thank-you title', 'flx-forms')}
						value={h.thank_you_message?.thankYou || ''}
						onChange={(v) => setHead({ thank_you_message: { ...h.thank_you_message, thankYou: v } })}
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={__('Thank-you message', 'flx-forms')}
						value={h.thank_you_message?.done || ''}
						onChange={(v) => setHead({ thank_you_message: { ...h.thank_you_message, done: v } })}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={__('Show a confirmation code', 'flx-forms')}
						checked={!!h.trackingCode}
						onChange={(v) => setHead({ trackingCode: v })}
					/>
				</PanelBody>
				<PanelBody title={__('Notifications', 'flx-forms')} initialOpen={false}>
					<ToggleControl
						__nextHasNoMarginBottom
						label={__('Email me new responses', 'flx-forms')}
						checked={!!h.sendEmail}
						onChange={(v) => setHead({ sendEmail: v })}
					/>
					{h.sendEmail && (
						<TextControl
							__nextHasNoMarginBottom
							type="email"
							label={__('Notification address', 'flx-forms')}
							help={__('Empty: the address in Flux Forms settings.', 'flx-forms')}
							value={h.email || ''}
							onChange={(v) => setHead({ email: v })}
						/>
					)}
					<SelectControl
						__nextHasNoMarginBottom
						label={__('Send a copy to the person', 'flx-forms')}
						value={h.email_to || ''}
						options={[{ value: '', label: __('No', 'flx-forms') }, ...emailFields]}
						onChange={(v) => setHead({ email_to: v || false })}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={__('Spam protection (captcha)', 'flx-forms')}
						checked={!!h.captcha}
						onChange={(v) => setHead({ captcha: v })}
					/>
				</PanelBody>
				<PanelColorSettings
					title={__('Colors', 'flx-forms')}
					initialOpen={false}
					colorSettings={[
						{ label: __('Labels', 'flx-forms'), value: design.labelColor, onChange: (v) => setDesign({ labelColor: v }) },
						{ label: __('Field text', 'flx-forms'), value: design.inputText, onChange: (v) => setDesign({ inputText: v }) },
						{ label: __('Field background', 'flx-forms'), value: design.inputBg, onChange: (v) => setDesign({ inputBg: v }) },
						{ label: __('Field border', 'flx-forms'), value: design.inputBorder, onChange: (v) => setDesign({ inputBorder: v }) },
						{ label: __('Button', 'flx-forms'), value: design.buttonBg, onChange: (v) => setDesign({ buttonBg: v }) },
						{ label: __('Button text', 'flx-forms'), value: design.buttonText, onChange: (v) => setDesign({ buttonText: v }) },
						{ label: __('Form background', 'flx-forms'), value: design.cardBg, onChange: (v) => setDesign({ cardBg: v }) },
					]}
				/>
				<PanelBody title={__('Layout', 'flx-forms')} initialOpen={false}>
					<RangeControl
						__nextHasNoMarginBottom
						label={__('Corner radius', 'flx-forms')}
						min={0}
						max={30}
						allowReset
						value={design.radius}
						onChange={(v) => setDesign({ radius: v })}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						label={__('Space between fields', 'flx-forms')}
						min={0}
						max={48}
						allowReset
						value={design.gap}
						onChange={(v) => setDesign({ gap: v })}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={__('Show form title and steps', 'flx-forms')}
						help={__('The title and step bar Flux Forms prints above the fields.', 'flx-forms')}
						checked={!!design.showHeader}
						onChange={(v) => setDesign({ showHeader: v })}
					/>
				</PanelBody>
			</InspectorControls>
			{'loading' === state && <Spinner />}
			{error && (
				<Notice status="error" isDismissible onRemove={() => setError('')}>
					{error}
				</Notice>
			)}
			{conflict && (
				<Notice
					status="warning"
					isDismissible={false}
					actions={[
						{ label: __('Load the panel version', 'flx-forms'), onClick: () => load(formId, true) },
						{ label: __('Keep mine', 'flx-forms'), onClick: forceSave, variant: 'secondary' },
					]}
				>
					{__('This form was changed in the Flux Forms panel after you opened it, so your changes were not written to the form. Load the panel version, or keep yours and overwrite it.', 'flx-forms')}
				</Notice>
			)}
			<div {...innerBlocksProps} />
			<div className="flxf-b-submit">
				<span className="flxf-b-button">{h.button_single_text}</span>
			</div>
		</div>
	);
}

// What tells whether the panel changed the form. Stored values come back sanitized
// (true -> "1", numbers -> strings), so values are compared as normalised strings,
// and only the keys the blocks manage are compared; rows kept as they are
// (flxf/raw) compare everything but their position.
const norm = (v) => (true === v ? '1' : false === v || null === v || undefined === v || 0 === v || '0' === v ? '' : String(v));
function fingerprint(rows) {
	const out = rows.slice(1).map((r) => {
		if (!r) {
			return null;
		}
		if ('option' === r.type || 'r_matrix' === r.type) {
			return [r.type, norm(r.parent), norm(r.value), norm(r.price), norm(r.src), norm(r.sub_value)];
		}
		if ('step' === r.type) {
			return ['step', norm(r.step), norm(r.name), norm(r.message)];
		}
		const def = TYPES[r.type];
		if (def) {
			const keys = [...(def.settings || []).map((x) => x.key), 'hidden', 'disabled', 'hflabel', 'customRequiredMsg', 'classes'];
			return [r.type, r.id_, norm(r.step), norm(r.name), norm(r.placeholder), norm(r.message), norm(r.required), norm(r.size || '100'), ...keys.map((k) => norm(r[k]))];
		}
		const { amount, step, dataId, ...rest } = r;
		return rest;
	});
	const rules = (rows[0].logic_rules || []).map((r) => [
		r.id,
		(r.actions || []).map((a) => [a.type, norm(a.target)]),
		norm(r.conditions && r.conditions.operator),
		((r.conditions && r.conditions.items) || []).map((c) => [norm(c.field_id), norm(c.compare), norm(c.value)]),
	]);
	return JSON.stringify([out, rules]);
}

export function registerForm() {
	registerBlockType('flxf/form', {
		apiVersion: 3,
		title: __('Flux Form', 'flx-forms'),
		description: __('A Flux Forms form built from blocks. Responses, emails and automations run in Flux Forms.', 'flx-forms'),
		icon: 'feedback',
		category: 'flux-forms',
		keywords: [__('form', 'flx-forms'), __('contact', 'flx-forms'), 'flux'],
		attributes: {
			formId: { type: 'number', default: 0 },
			formName: { type: 'string', default: '' },
			hash: { type: 'string', default: '' },
			head: { type: 'object', default: {} },
			step: { type: 'object', default: {} },
			uid: { type: 'string', default: '' },
			design: { type: 'object', default: {} },
			align: { type: 'string', default: '' },
		},
		supports: { html: false, align: ['wide', 'full'] },
		providesContext: { 'flxf/formId': 'formId' },
		transforms: {
			from: [
				{
					// The older form picker block of Flux Forms: same form, now editable here.
					type: 'block',
					blocks: ['easy-form-builder/form'],
					isMatch: (a) => /^\d+$/.test(String(a.formId || '')),
					transform: (a) => createBlock('flxf/form', { formId: parseInt(a.formId, 10), formName: a.formName || '' }),
				},
			],
		},
		edit: Edit,
		save: () => {
			const { InnerBlocks } = window.wp.blockEditor;
			return <InnerBlocks.Content />;
		},
	});
}
