/**
 * Field blocks. They never render on their own: the form block turns them into the
 * Flux Forms structure and the Flux Forms renderer prints the real inputs.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType, createBlock } from '@wordpress/blocks';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
	Button,
	Notice,
	ExternalLink,
} from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { newId, OPTION_TYPES } from './convert';

const COMMON_ATTRS = {
	fieldId: { type: 'string', default: '' },
	label: { type: 'string', default: '' },
	placeholder: { type: 'string', default: '' },
	help: { type: 'string', default: '' },
	required: { type: 'boolean', default: false },
	width: { type: 'string', default: '100' },
	options: { type: 'array', default: [] },
	efb: { type: 'object', default: {} },
};

export const FIELDS = {
	text: { title: __('Text', 'flx-forms'), icon: 'editor-textcolor', label: __('Full name', 'flx-forms') },
	email: { title: __('Email', 'flx-forms'), icon: 'email', label: __('Email', 'flx-forms') },
	tel: { title: __('Phone', 'flx-forms'), icon: 'phone', label: __('Phone', 'flx-forms') },
	textarea: { title: __('Long text', 'flx-forms'), icon: 'editor-paragraph', label: __('Message', 'flx-forms') },
	number: { title: __('Number', 'flx-forms'), icon: 'editor-ol', label: __('Number', 'flx-forms') },
	date: { title: __('Date', 'flx-forms'), icon: 'calendar-alt', label: __('Date', 'flx-forms') },
	select: { title: __('Dropdown', 'flx-forms'), icon: 'menu-alt', label: __('Choose one', 'flx-forms') },
	radio: { title: __('Single choice', 'flx-forms'), icon: 'marker', label: __('Choose one', 'flx-forms') },
	checkbox: { title: __('Multiple choice', 'flx-forms'), icon: 'yes-alt', label: __('Choose any', 'flx-forms') },
};

function Preview({ type, attributes }) {
	const { placeholder, options = [] } = attributes;
	if ('textarea' === type) {
		return <textarea className="flxf-b-input" placeholder={placeholder} rows={4} readOnly tabIndex={-1} />;
	}
	if ('select' === type) {
		return (
			<select className="flxf-b-input" tabIndex={-1} disabled>
				<option>{placeholder || __('Select', 'flx-forms')}</option>
				{options.map((o) => (
					<option key={o.id}>{o.label}</option>
				))}
			</select>
		);
	}
	if ('radio' === type || 'checkbox' === type) {
		return (
			<div className="flxf-b-choices">
				{options.map((o) => (
					<label key={o.id} className="flxf-b-choice">
						<input type={type} tabIndex={-1} disabled /> <span>{o.label}</span>
					</label>
				))}
			</div>
		);
	}
	const htmlType = { email: 'email', tel: 'tel', number: 'number', date: 'date' }[type] || 'text';
	return <input className="flxf-b-input" type={htmlType} placeholder={placeholder} readOnly tabIndex={-1} />;
}

function OptionsEditor({ options, onChange }) {
	const set = (i, label) => onChange(options.map((o, k) => (k === i ? { ...o, label } : o)));
	const move = (i, d) => {
		const next = [...options];
		const [it] = next.splice(i, 1);
		next.splice(i + d, 0, it);
		onChange(next);
	};
	return (
		<div className="flxf-b-options">
			{options.map((o, i) => (
				<div className="flxf-b-option" key={o.id}>
					<TextControl
						__nextHasNoMarginBottom
						value={o.label}
						onChange={(v) => set(i, v)}
						aria-label={__('Option', 'flx-forms')}
					/>
					<Button icon="arrow-up-alt2" size="small" label={__('Move up', 'flx-forms')} disabled={!i} onClick={() => move(i, -1)} />
					<Button
						icon="arrow-down-alt2"
						size="small"
						label={__('Move down', 'flx-forms')}
						disabled={i === options.length - 1}
						onClick={() => move(i, 1)}
					/>
					<Button
						icon="trash"
						size="small"
						isDestructive
						label={__('Remove option', 'flx-forms')}
						onClick={() => onChange(options.filter((_, k) => k !== i))}
					/>
				</div>
			))}
			<Button
				variant="secondary"
				icon="plus"
				onClick={() =>
					onChange([...options, { id: newId(), label: `${__('Option', 'flx-forms')} ${options.length + 1}` }])
				}
			>
				{__('Add option', 'flx-forms')}
			</Button>
		</div>
	);
}

function makeEdit(type) {
	return function Edit({ attributes, setAttributes, isSelected }) {
		const { label, placeholder, help, required, width, options = [], fieldId } = attributes;
		// New blocks get their id and starter content here, not from attribute defaults:
		// a default is not saved in the post, so it would follow the editor's language.
		useEffect(() => {
			if (!fieldId) {
				const init = { fieldId: newId() };
				if (!label) {
					init.label = FIELDS[type].label;
				}
				if (OPTION_TYPES.includes(type) && !options.length) {
					init.options = [1, 2].map((n) => ({ id: newId(), label: `${__('Option', 'flx-forms')} ${n}` }));
				}
				setAttributes(init);
			}
		}, [fieldId]);
		const blockProps = useBlockProps({ className: `flxf-b-field flxf-b-w${width || '100'}` });
		return (
			<div {...blockProps}>
				<InspectorControls>
					<PanelBody title={__('Field', 'flx-forms')}>
						<TextControl __nextHasNoMarginBottom label={__('Label', 'flx-forms')} value={label} onChange={(v) => setAttributes({ label: v })} />
						{!OPTION_TYPES.includes(type) || 'select' === type ? (
							<TextControl
								__nextHasNoMarginBottom
								label={__('Placeholder', 'flx-forms')}
								value={placeholder}
								onChange={(v) => setAttributes({ placeholder: v })}
							/>
						) : null}
						<TextareaControl
							__nextHasNoMarginBottom
							label={__('Help text', 'flx-forms')}
							help={__('Shown under the field.', 'flx-forms')}
							value={help}
							onChange={(v) => setAttributes({ help: v })}
						/>
						<ToggleControl __nextHasNoMarginBottom label={__('Required', 'flx-forms')} checked={!!required} onChange={(v) => setAttributes({ required: v })} />
						<SelectControl
							__nextHasNoMarginBottom
							label={__('Width', 'flx-forms')}
							value={width}
							options={[
								{ value: '100', label: __('Full', 'flx-forms') },
								{ value: '50', label: __('Half', 'flx-forms') },
								{ value: '33', label: __('One third', 'flx-forms') },
							]}
							onChange={(v) => setAttributes({ width: v })}
						/>
					</PanelBody>
					{OPTION_TYPES.includes(type) && (
						<PanelBody title={__('Options', 'flx-forms')}>
							<OptionsEditor options={options} onChange={(v) => setAttributes({ options: v })} />
						</PanelBody>
					)}
				</InspectorControls>
				<RichText
					tagName="label"
					className="flxf-b-label"
					value={label}
					allowedFormats={[]}
					withoutInteractiveFormatting
					placeholder={__('Label', 'flx-forms')}
					onChange={(v) => setAttributes({ label: v })}
				/>
				{required && <span className="flxf-b-req">*</span>}
				<Preview type={type} attributes={attributes} />
				{help && <p className="flxf-b-help">{help}</p>}
				{isSelected && OPTION_TYPES.includes(type) && !options.length && (
					<p className="flxf-b-help">{__('Add options in the block settings.', 'flx-forms')}</p>
				)}
			</div>
		);
	};
}

export function registerFields() {
	Object.entries(FIELDS).forEach(([type, def]) => {
		registerBlockType(`flxf/${type}`, {
			apiVersion: 3,
			title: def.title,
			icon: def.icon,
			category: 'flux-forms',
			parent: ['flxf/form'],
			attributes: COMMON_ATTRS,
			supports: { html: false, reusable: false },
			transforms: {
				to: Object.keys(FIELDS)
					.filter((t) => t !== type)
					.map((t) => ({
						type: 'block',
						blocks: [`flxf/${t}`],
						transform: (a) => createBlock(`flxf/${t}`, { ...a, efb: {} }),
					})),
			},
			edit: makeEdit(type),
			save: () => null,
		});
	});

	registerBlockType('flxf/raw', {
		apiVersion: 3,
		title: __('Flux Forms field', 'flx-forms'),
		description: __('A field made in the Flux Forms panel. Edit it there; it is kept as it is.', 'flx-forms'),
		icon: 'feedback',
		category: 'flux-forms',
		parent: ['flxf/form'],
		inserter: false,
		attributes: { efb: { type: 'object', default: {} }, children: { type: 'array', default: [] } },
		supports: { html: false, reusable: false, inserter: false },
		usesContext: ['flxf/formId'],
		edit: ({ attributes, context }) => {
			const r = attributes.efb || {};
			const formId = context && context['flxf/formId'];
			const cfg = window.flxfBlocks || {};
			const blockProps = useBlockProps({ className: 'flxf-b-field flxf-b-raw' });
			return (
				<div {...blockProps}>
					<span className="flxf-b-label">{r.name || r.type}</span>
					<Notice status="info" isDismissible={false}>
						{__('This field type is edited in the Flux Forms panel. It is kept unchanged.', 'flx-forms')}{' '}
						<code>{r.type}</code>
						{formId && cfg.canManage ? (
							<>
								{' '}
								<ExternalLink href={`${cfg.panelUrl}&state=edit-form&id=${formId}`}>
									{__('Edit in panel', 'flx-forms')}
								</ExternalLink>
							</>
						) : null}
					</Notice>
				</div>
			);
		},
		save: () => null,
	});
}
