/**
 * Field blocks: one block per Flux Forms element type (see types.js). They never
 * render on their own: the form block turns them into the Flux Forms structure and the
 * Flux Forms renderer prints the real inputs. Every setting the panel has for a type is
 * edited here too; settings the blocks do not know stay on the row untouched.
 */
import { __, sprintf } from '@wordpress/i18n';
import { registerBlockType, createBlock } from '@wordpress/blocks';
import { useBlockProps, InspectorControls, RichText, MediaUpload, MediaUploadCheck, store as blockEditorStore } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
	Button,
	Notice,
	ExternalLink,
	Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { newId } from './convert';
import { TYPES, GROUPS, COMMON_TOGGLES, slugOf, typeOfBlock } from './types';

const cfg = () => window.flxfBlocks || {};

const COMMON_ATTRS = {
	fieldId: { type: 'string', default: '' },
	label: { type: 'string', default: '' },
	placeholder: { type: 'string', default: '' },
	help: { type: 'string', default: '' },
	required: { type: 'boolean', default: false },
	width: { type: 'string', default: '100' },
	options: { type: 'array', default: [] },
	efb: { type: 'object', default: {} },
	logic: { type: 'object' },
};

const isOn = (v) => !!v && '0' !== v && 0 !== v && 'false' !== v;
const PLACEHOLDER_KINDS = ['input', 'textarea', 'select'];
const GEO = { conturyList: 'country', stateProvince: 'state', cityList: 'city' };

// ---------------------------------------------------------------------------
// Preview: a light picture of the field; the real input comes from Flux Forms.

function Preview({ type, attributes }) {
	const def = TYPES[type];
	const { placeholder, options = [], efb = {} } = attributes;
	const ph = placeholder || '';
	switch (def.kind) {
		case 'textarea':
			return <textarea className="flxf-b-input" placeholder={ph} rows={4} readOnly tabIndex={-1} />;
		case 'select':
			return (
				<select className="flxf-b-input" tabIndex={-1} disabled>
					<option>{ph || __('Select', 'flx-forms')}</option>
					{options.slice(0, 50).map((o) => (
						<option key={o.id}>{o.label}</option>
					))}
				</select>
			);
		case 'choices': {
			const multi = /checkbox|Checkbox|multiselect/i.test(type) && !['radio', 'payRadio', 'chlRadio'].includes(type);
			return (
				<div className={`flxf-b-choices flxf-b-cols${efb.op_style || '1'}`}>
					{options.map((o) => (
						<label key={o.id} className="flxf-b-choice">
							<input type={multi ? 'checkbox' : 'radio'} tabIndex={-1} disabled /> <span>{o.label}</span>
							{undefined !== o.price && <em className="flxf-b-price">{o.price}</em>}
							{('chlCheckBox' === type || 'chlRadio' === type) && (
								<input className="flxf-b-input flxf-b-qty" placeholder={efb.pholder_chl_value || ''} readOnly tabIndex={-1} />
							)}
						</label>
					))}
				</div>
			);
		}
		case 'images':
			return (
				<div className={`flxf-b-images flxf-b-cols${efb.op_style || '1'}`}>
					{options.map((o) => (
						<figure key={o.id} className="flxf-b-image">
							{o.src ? <img src={o.src} alt="" /> : <span className="flxf-b-image-empty" />}
							<figcaption>
								<strong>{o.label}</strong>
								{o.sub_value && <small>{o.sub_value}</small>}
							</figcaption>
						</figure>
					))}
				</div>
			);
		case 'yesno':
			return (
				<div className="flxf-b-yesno">
					<span>{efb.button_1_text || __('Yes', 'flx-forms')}</span>
					<span>{efb.button_2_text || __('No', 'flx-forms')}</span>
				</div>
			);
		case 'switch':
			return (
				<div className="flxf-b-switch">
					<span>{efb.off}</span>
					<i />
					<span>{efb.on}</span>
				</div>
			);
		case 'range':
			return <input className="flxf-b-range" type="range" min={efb.milen || 0} max={efb.mlen || 100} readOnly tabIndex={-1} />;
		case 'color':
			return <input className="flxf-b-color" type="color" value={efb.value || '#000000'} readOnly tabIndex={-1} />;
		case 'file':
			return (
				<div className="flxf-b-drop">
					<span className="dashicons dashicons-upload" /> {__('Choose a file or drop it here', 'flx-forms')}
				</div>
			);
		case 'recorder':
			return (
				<div className="flxf-b-drop">
					<span className={`dashicons dashicons-${def.icon}`} /> {__('Start recording', 'flx-forms')}
				</div>
			);
		case 'rating':
			return <div className="flxf-b-stars">★★★★★</div>;
		case 'points5':
		case 'points10': {
			const n = 'points5' === def.kind ? [1, 2, 3, 4, 5] : [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
			return (
				<div className="flxf-b-points">
					{n.map((i) => (
						<span key={i}>{i}</span>
					))}
				</div>
			);
		}
		case 'matrix':
			return (
				<table className="flxf-b-matrix">
					<tbody>
						{options.map((o) => (
							<tr key={o.id}>
								<th>{o.label}</th>
								<td>☆☆☆☆☆</td>
							</tr>
						))}
					</tbody>
				</table>
			);
		case 'esign':
			return (
				<div className="flxf-b-sign">
					<span>{__('Sign here', 'flx-forms')}</span>
					<small>{efb.button_single_text}</small>
				</div>
			);
		case 'map':
			return (
				<div className="flxf-b-map">
					<span className="dashicons dashicons-location-alt" /> {efb.lat}, {efb.lng}
				</div>
			);
		case 'total':
			return <div className="flxf-b-total">0.00</div>;
		case 'payment':
			return (
				<div className="flxf-b-card">
					<span>•••• •••• •••• ••••</span>
					<span>MM / YY · CVC</span>
				</div>
			);
		case 'heading': {
			const Tag = 'display-6' === efb.el_text_size || 'fs-3' === efb.el_text_size ? 'h3' : 'h4';
			return <Tag className="flxf-b-heading">{efb.value}</Tag>;
		}
		case 'link':
			return <span className="flxf-b-link">{efb.value || efb.href}</span>;
		case 'html':
			return <pre className="flxf-b-html">{(efb.value || '').slice(0, 400) || __('HTML code', 'flx-forms')}</pre>;
		default: {
			const htmlType =
				{ email: 'email', tel: 'tel', mobile: 'tel', number: 'number', date: 'date', password: 'password', url: 'url', prcfld: 'number' }[type] ||
				'text';
			return <input className="flxf-b-input" type={htmlType} placeholder={ph} value={efb.value || ''} readOnly tabIndex={-1} />;
		}
	}
}

// ---------------------------------------------------------------------------
// Settings

function SettingControl({ s, efb, onChange }) {
	if (s.when && !s.when(efb)) {
		return null;
	}
	const value = efb[s.key];
	const set = (v) => onChange({ [s.key]: v });
	const common = { __nextHasNoMarginBottom: true, label: s.label, help: s.help };
	switch (s.type) {
		case 'toggle':
			return <ToggleControl {...common} checked={isOn(value)} onChange={(v) => set(v ? 1 : 0)} />;
		case 'select':
			return (
				<SelectControl
					{...common}
					value={undefined === value ? '' : String(value)}
					options={[...(s.options.some(([v]) => String(v) === String(value ?? '')) ? [] : [{ value: '', label: '—' }]), ...s.options.map(([v, l]) => ({ value: String(v), label: l }))]}
					onChange={set}
				/>
			);
		case 'code':
			return <TextareaControl {...common} className="flxf-b-code" rows={8} value={value || ''} onChange={set} />;
		case 'number':
			return <TextControl {...common} type="number" value={undefined === value || null === value ? '' : String(value)} onChange={set} />;
		case 'url':
			return <TextControl {...common} type="url" value={value || ''} onChange={set} />;
		default:
			return <TextControl {...common} value={undefined === value || null === value ? '' : String(value)} onChange={set} />;
	}
}

function OptionsEditor({ type, options, onChange }) {
	const def = TYPES[type];
	const fields = def.optionFields || [];
	const isMatrix = 'r_matrix' === def.options;
	const set = (i, patch) => onChange(options.map((o, k) => (k === i ? { ...o, ...patch } : o)));
	const move = (i, d) => {
		const next = [...options];
		const [it] = next.splice(i, 1);
		next.splice(i + d, 0, it);
		onChange(next);
	};
	const word = isMatrix ? __('Row', 'flx-forms') : __('Option', 'flx-forms');
	return (
		<div className="flxf-b-options">
			{options.map((o, i) => (
				<div className={`flxf-b-option${fields.length ? ' flxf-b-option-wide' : ''}`} key={o.id}>
					<div className="flxf-b-option-row">
						<TextControl __nextHasNoMarginBottom value={o.label} onChange={(v) => set(i, { label: v })} aria-label={word} />
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
							label={__('Remove', 'flx-forms')}
							onClick={() => onChange(options.filter((_, k) => k !== i))}
						/>
					</div>
					{fields.includes('price') && (
						<TextControl
							__nextHasNoMarginBottom
							type="number"
							label={__('Price', 'flx-forms')}
							value={String(o.price ?? 0)}
							onChange={(v) => set(i, { price: v })}
						/>
					)}
					{fields.includes('sub_value') && (
						<TextControl __nextHasNoMarginBottom label={__('Description', 'flx-forms')} value={o.sub_value || ''} onChange={(v) => set(i, { sub_value: v })} />
					)}
					{fields.includes('src') && (
						<div className="flxf-b-option-img">
							{o.src && <img src={o.src} alt="" />}
							<MediaUploadCheck>
								<MediaUpload
									allowedTypes={['image']}
									onSelect={(m) => set(i, { src: m.url })}
									render={({ open }) => (
										<Button variant="secondary" size="small" onClick={open}>
											{o.src ? __('Change image', 'flx-forms') : __('Choose image', 'flx-forms')}
										</Button>
									)}
								/>
							</MediaUploadCheck>
						</div>
					)}
				</div>
			))}
			<Button variant="secondary" icon="plus" onClick={() => onChange([...options, newOption(type, options.length + 1)])}>
				{isMatrix ? __('Add row', 'flx-forms') : __('Add option', 'flx-forms')}
			</Button>
		</div>
	);
}

function newOption(type, n) {
	const def = TYPES[type];
	const word = 'r_matrix' === def.options ? __('Row', 'flx-forms') : __('Option', 'flx-forms');
	const o = { id: newId(), label: `${word} ${n}` };
	(def.optionFields || []).forEach((k) => {
		if ('price' === k) {
			o.price = 0;
		} else if ('src' === k) {
			o.src = cfg().sampleImage || '';
		} else if ('sub_value' === k) {
			o.sub_value = __('Short description', 'flx-forms');
		}
	});
	return o;
}

function starterOptions(type) {
	const def = TYPES[type];
	if (def.optionDefaults) {
		return def.optionDefaults.map((label) => ({ id: newId(), label }));
	}
	return [1, 2].map((n) => newOption(type, n));
}

// Country, state and city lists come from the files Flux Forms ships.
const geoCache = {};
function fetchGeo(path, isScript) {
	const base = cfg().geoUrl || '';
	if (!geoCache[path]) {
		geoCache[path] = window
			.fetch(base + path, { credentials: 'same-origin' })
			.then((r) => {
				if (!r.ok) {
					throw new Error(String(r.status));
				}
				return isScript ? r.text() : r.json();
			})
			.then((d) => {
				if (!isScript) {
					return d;
				}
				const start = d.indexOf('[');
				return JSON.parse(d.slice(start, d.lastIndexOf(']') + 1));
			});
		geoCache[path].catch(() => delete geoCache[path]);
	}
	return geoCache[path];
}

const slugify = (s) => String(s || '').replace(/\s+/g, '_').toLowerCase();

export function geoOptions(type, efb) {
	const country = String(efb.country || '').toLowerCase();
	if ('conturyList' === type) {
		return fetchGeo('countries.js', true).then((list) =>
			[...list]
				.sort((a, b) => a.n.localeCompare(b.n))
				.map((c) => ({ id: newId(), label: `${c.n} (${c.l})`, efb: { id_op: c.s2.toLowerCase() } }))
		);
	}
	if (!/^[a-z]{2}$/.test(country)) {
		return Promise.reject(new Error('country'));
	}
	const path = 'stateProvince' === type ? `states/${country}.json` : `cites/${country}/${String(efb.statePov || '').toLowerCase()}.json`;
	return fetchGeo(path, false).then((list) =>
		list.map((r) => {
			const s2 = slugify('stateProvince' === type ? r.s : r.n);
			return {
				id: `${s2}-efb${newId(2)}`,
				label: r.n === r.l || !r.l ? r.n : `${r.l} (${r.n})`,
				efb: { s2, id_op: slugify(r.n), n: r.n, l: r.l },
			};
		})
	);
}

function GeoPanel({ type, efb, options, setAttributes }) {
	const [busy, setBusy] = useState(false);
	const [err, setErr] = useState('');
	const fill = () => {
		setBusy(true);
		setErr('');
		geoOptions(type, efb)
			.then((opts) => setAttributes({ options: opts }))
			.catch(() => setErr(__('The list could not be loaded. Check the country and state codes.', 'flx-forms')))
			.finally(() => setBusy(false));
	};
	return (
		<div className="flxf-b-geo">
			<p className="flxf-b-help">
				{/* translators: %d: number of entries in the list. */}
				{sprintf(__('%d entries in the list.', 'flx-forms'), options.length)}
			</p>
			<Button variant="secondary" onClick={fill} disabled={busy}>
				{busy ? <Spinner /> : null}
				{__('Fill the list again', 'flx-forms')}
			</Button>
			{err && <p className="flxf-b-error">{err}</p>}
		</div>
	);
}

// ---------------------------------------------------------------------------
// Conditional display

const COMPARES = {
	text: ['is', 'is_not', 'contains', 'not_contains', 'starts_with', 'ends_with', 'is_empty', 'is_not_empty'],
	num: ['is', 'is_not', 'gt', 'gte', 'lt', 'lte', 'is_empty', 'is_not_empty'],
	date: ['is', 'is_not', 'date_before', 'date_after', 'is_empty', 'is_not_empty'],
	choice: ['is', 'is_not', 'is_empty', 'is_not_empty'],
	multi: ['is', 'is_not', 'contains', 'not_contains', 'is_empty', 'is_not_empty'],
};
const NO_VALUE = ['is_empty', 'is_not_empty'];
const MULTI_TYPES = ['checkbox', 'payCheckbox', 'chlCheckBox', 'multiselect', 'payMultiselect'];
const NUM_TYPES = ['number', 'range', 'rating', 'pointr5', 'pointr10', 'prcfld'];
const NO_INPUT_KINDS = ['heading', 'link', 'html', 'payment', 'total'];

function compareLabel(c) {
	return {
		is: __('is', 'flx-forms'),
		is_not: __('is not', 'flx-forms'),
		contains: __('contains', 'flx-forms'),
		not_contains: __('does not contain', 'flx-forms'),
		starts_with: __('starts with', 'flx-forms'),
		ends_with: __('ends with', 'flx-forms'),
		is_empty: __('is empty', 'flx-forms'),
		is_not_empty: __('is filled in', 'flx-forms'),
		gt: __('is greater than', 'flx-forms'),
		gte: __('is at least', 'flx-forms'),
		lt: __('is less than', 'flx-forms'),
		lte: __('is at most', 'flx-forms'),
		date_before: __('is before', 'flx-forms'),
		date_after: __('is after', 'flx-forms'),
	}[c] || c;
}

function sourceKind(type, hasOptions) {
	if ('yesNo' === type || hasOptions) {
		return MULTI_TYPES.includes(type) ? 'multi' : 'choice';
	}
	if (NUM_TYPES.includes(type)) {
		return 'num';
	}
	return 'date' === type ? 'date' : 'text';
}

function LogicPanel({ clientId, fieldId, logic, setAttributes }) {
	const sources = useSelect(
		(s) => {
			const be = s(blockEditorStore);
			const root = be.getBlockRootClientId(clientId);
			return be
				.getBlocks(root)
				.map((b) => ({ b, type: typeOfBlock(b.name) }))
				.filter(({ b, type }) => type && b.attributes.fieldId && b.attributes.fieldId !== fieldId && !NO_INPUT_KINDS.includes(TYPES[type].kind))
				.map(({ b, type }) => ({
					id: b.attributes.fieldId,
					type,
					label: b.attributes.label || TYPES[type].title,
					options:
						'yesNo' === type
							? [
									{ id: 'yes', label: b.attributes.efb?.button_1_text || __('Yes', 'flx-forms') },
									{ id: 'no', label: b.attributes.efb?.button_2_text || __('No', 'flx-forms') },
							  ]
							: (b.attributes.options || []).map((o) => ({ id: o.id, label: o.label })),
				}));
		},
		[clientId, fieldId]
	);
	const mode = logic && logic.action ? logic.action : '';
	const items = (logic && logic.items) || [];
	const update = (patch) => setAttributes({ logic: { action: 'show', operator: 'AND', items: [], ...(logic || {}), ...patch } });
	const setItem = (i, patch) => update({ items: items.map((c, k) => (k === i ? { ...c, ...patch } : c)) });
	const addItem = () => update({ items: [...items, { field_id: sources[0] ? sources[0].id : '', compare: 'is', value: '' }] });
	return (
		<PanelBody title={__('Conditional display', 'flx-forms')} initialOpen={!!mode}>
			<SelectControl
				__nextHasNoMarginBottom
				label={__('Show this field', 'flx-forms')}
				value={mode}
				options={[
					{ value: '', label: __('Always', 'flx-forms') },
					{ value: 'show', label: __('Only when…', 'flx-forms') },
					{ value: 'hide', label: __('Hide when…', 'flx-forms') },
				]}
				onChange={(v) => {
					if (!v) {
						setAttributes({ logic: undefined });
						return;
					}
					update({ action: v, items: items.length ? items : sources[0] ? [{ field_id: sources[0].id, compare: 'is', value: '' }] : [] });
				}}
			/>
			{mode && !sources.length && <p className="flxf-b-help">{__('Add another field to the form first.', 'flx-forms')}</p>}
			{mode && items.length > 1 && (
				<SelectControl
					__nextHasNoMarginBottom
					label={__('Conditions to meet', 'flx-forms')}
					value={logic.operator || 'AND'}
					options={[
						{ value: 'AND', label: __('All of them', 'flx-forms') },
						{ value: 'OR', label: __('Any of them', 'flx-forms') },
					]}
					onChange={(v) => update({ operator: v })}
				/>
			)}
			{mode &&
				items.map((c, i) => {
					const src = sources.find((x) => x.id === c.field_id);
					const kind = src ? sourceKind(src.type, src.options.length > 0) : 'text';
					const compares = COMPARES[kind];
					return (
						<div className="flxf-b-cond" key={i}>
							<SelectControl
								__nextHasNoMarginBottom
								label={__('Field', 'flx-forms')}
								value={c.field_id}
								options={[
									...(src ? [] : [{ value: c.field_id || '', label: __('— Select —', 'flx-forms') }]),
									...sources.map((x) => ({ value: x.id, label: x.label })),
								]}
								onChange={(v) => setItem(i, { field_id: v, value: '' })}
							/>
							<SelectControl
								__nextHasNoMarginBottom
								label={__('Condition', 'flx-forms')}
								value={compares.includes(c.compare) ? c.compare : compares[0]}
								options={compares.map((x) => ({ value: x, label: compareLabel(x) }))}
								onChange={(v) => setItem(i, { compare: v })}
							/>
							{!NO_VALUE.includes(c.compare) &&
								(src && src.options.length ? (
									<SelectControl
										__nextHasNoMarginBottom
										label={__('Value', 'flx-forms')}
										value={String(c.value || '')}
										options={[{ value: '', label: __('— Select —', 'flx-forms') }, ...src.options.map((o) => ({ value: o.id, label: o.label }))]}
										onChange={(v) => setItem(i, { value: v })}
									/>
								) : (
									<TextControl
										__nextHasNoMarginBottom
										label={__('Value', 'flx-forms')}
										type={'num' === kind ? 'number' : 'date' === kind ? 'date' : 'text'}
										value={String(c.value ?? '')}
										onChange={(v) => setItem(i, { value: v })}
									/>
								))}
							<Button isDestructive variant="link" onClick={() => update({ items: items.filter((_, k) => k !== i) })}>
								{__('Remove condition', 'flx-forms')}
							</Button>
						</div>
					);
				})}
			{mode && sources.length > 0 && (
				<Button variant="secondary" icon="plus" onClick={addItem}>
					{__('Add condition', 'flx-forms')}
				</Button>
			)}
		</PanelBody>
	);
}

// ---------------------------------------------------------------------------

function makeEdit(type) {
	const def = TYPES[type];
	return function Edit({ attributes, setAttributes, isSelected, clientId }) {
		const { label, placeholder, help, required, width, options = [], fieldId, efb = {}, logic } = attributes;
		const setEfb = (patch) => setAttributes({ efb: { ...efb, ...patch } });
		// New blocks get their id and starter content here, not from attribute defaults:
		// a default is not saved in the post, so it would follow the editor's language.
		useEffect(() => {
			if (fieldId) {
				return;
			}
			const init = { fieldId: newId() };
			if (!label && false !== def.label) {
				init.label = def.label;
			}
			if (def.defaults && !Object.keys(efb).length) {
				init.efb = { ...def.defaults };
			}
			if (def.options && !options.length) {
				if (GEO[type]) {
					const e = init.efb || efb;
					const geoEfb = 'conturyList' === type ? e : { country: 'TR', statePov: '34', ...e };
					init.efb = geoEfb;
					geoOptions(type, geoEfb)
						.then((opts) => setAttributes({ options: opts }))
						.catch(() => {});
				} else {
					init.options = starterOptions(type);
				}
			}
			setAttributes(init);
		}, [fieldId]);
		const blockProps = useBlockProps({
			className: `flxf-b-field flxf-b-w${width || '100'} flxf-b-k-${def.kind}${isOn(efb.hidden) ? ' flxf-b-hidden' : ''}`,
		});
		const hasLabel = false !== def.label;
		const canRequire = hasLabel && !def.noRequired && 'payment' !== def.kind;
		return (
			<div {...blockProps}>
				<InspectorControls>
					<PanelBody title={def.title}>
						{hasLabel && (
							<TextControl __nextHasNoMarginBottom label={__('Label', 'flx-forms')} value={label} onChange={(v) => setAttributes({ label: v })} />
						)}
						{PLACEHOLDER_KINDS.includes(def.kind) && (
							<TextControl
								__nextHasNoMarginBottom
								label={__('Placeholder', 'flx-forms')}
								value={placeholder}
								onChange={(v) => setAttributes({ placeholder: v })}
							/>
						)}
						{(def.settings || []).map((s) => (
							<SettingControl key={s.key} s={s} efb={efb} onChange={setEfb} />
						))}
						{hasLabel && (
							<TextareaControl
								__nextHasNoMarginBottom
								label={__('Help text', 'flx-forms')}
								help={__('Shown under the field.', 'flx-forms')}
								value={help}
								onChange={(v) => setAttributes({ help: v })}
							/>
						)}
						{canRequire && (
							<ToggleControl __nextHasNoMarginBottom label={__('Required', 'flx-forms')} checked={!!required} onChange={(v) => setAttributes({ required: v })} />
						)}
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
					{def.options && (
						<PanelBody title={'r_matrix' === def.options ? __('Rows', 'flx-forms') : __('Options', 'flx-forms')} initialOpen={!GEO[type]}>
							{GEO[type] && <GeoPanel type={type} efb={efb} options={options} setAttributes={setAttributes} />}
							<OptionsEditor type={type} options={options} onChange={(v) => setAttributes({ options: v })} />
						</PanelBody>
					)}
					{hasLabel && 'payment' !== def.kind && (
						<PanelBody title={__('More settings', 'flx-forms')} initialOpen={false}>
							{COMMON_TOGGLES.map((s) => (
								<SettingControl key={s.key} s={s} efb={efb} onChange={setEfb} />
							))}
							<TextControl
								__nextHasNoMarginBottom
								label={__('Message when left empty', 'flx-forms')}
								value={efb.customRequiredMsg || ''}
								onChange={(v) => setEfb({ customRequiredMsg: v })}
							/>
							<TextControl
								__nextHasNoMarginBottom
								label={__('CSS classes', 'flx-forms')}
								value={efb.classes || ''}
								onChange={(v) => setEfb({ classes: v })}
							/>
						</PanelBody>
					)}
					<LogicPanel clientId={clientId} fieldId={fieldId} logic={logic} setAttributes={setAttributes} />
				</InspectorControls>
				{hasLabel && (
					<>
						<RichText
							tagName="label"
							className="flxf-b-label"
							value={label}
							allowedFormats={[]}
							withoutInteractiveFormatting
							placeholder={__('Label', 'flx-forms')}
							onChange={(v) => setAttributes({ label: v })}
						/>
						{required && canRequire && <span className="flxf-b-req">*</span>}
					</>
				)}
				{(isOn(efb.hidden) || (logic && logic.action)) && (
					<span className="flxf-b-badges">
						{isOn(efb.hidden) && <span className="flxf-b-badge">{__('Hidden', 'flx-forms')}</span>}
						{logic && logic.action && <span className="flxf-b-badge">{__('Conditional', 'flx-forms')}</span>}
					</span>
				)}
				<Preview type={type} attributes={attributes} />
				{help && <p className="flxf-b-help">{help}</p>}
				{isSelected && def.options && !options.length && <p className="flxf-b-help">{__('Add options in the block settings.', 'flx-forms')}</p>}
			</div>
		);
	};
}

function StepEdit({ attributes, setAttributes }) {
	const { label, help } = attributes;
	const blockProps = useBlockProps({ className: 'flxf-b-step' });
	return (
		<div {...blockProps}>
			<InspectorControls>
				<PanelBody title={__('Step', 'flx-forms')}>
					<TextControl __nextHasNoMarginBottom label={__('Step title', 'flx-forms')} value={label} onChange={(v) => setAttributes({ label: v })} />
					<TextareaControl __nextHasNoMarginBottom label={__('Description', 'flx-forms')} value={help} onChange={(v) => setAttributes({ help: v })} />
				</PanelBody>
			</InspectorControls>
			<span className="flxf-b-step-mark">{__('Step', 'flx-forms')}</span>
			<RichText
				tagName="strong"
				className="flxf-b-step-title"
				value={label}
				allowedFormats={[]}
				withoutInteractiveFormatting
				placeholder={__('Step title', 'flx-forms')}
				onChange={(v) => setAttributes({ label: v })}
			/>
			{help && <p className="flxf-b-help">{help}</p>}
		</div>
	);
}

export const FIELD_BLOCK_NAMES = Object.keys(TYPES).map((t) => `flxf/${slugOf(t)}`);

export function registerFields() {
	Object.entries(TYPES).forEach(([type, def]) => {
		const same = Object.keys(TYPES).filter((t) => t !== type && TYPES[t].group === def.group && !!TYPES[t].options === !!def.options);
		registerBlockType(`flxf/${slugOf(type)}`, {
			apiVersion: 3,
			title: def.title,
			icon: def.icon,
			category: GROUPS[def.group],
			parent: ['flxf/form'],
			attributes: COMMON_ATTRS,
			supports: { html: false, reusable: false },
			transforms: {
				to: same.map((t) => ({
					type: 'block',
					blocks: [`flxf/${slugOf(t)}`],
					transform: (a) => createBlock(`flxf/${slugOf(t)}`, { ...a, efb: { ...(TYPES[t].defaults || {}) } }),
				})),
			},
			edit: makeEdit(type),
			save: () => null,
		});
	});

	registerBlockType('flxf/step', {
		apiVersion: 3,
		title: __('Step', 'flx-forms'),
		description: __('Starts a new step. Fields below it, up to the next step, are shown together.', 'flx-forms'),
		icon: 'editor-ol',
		category: 'flux-forms',
		parent: ['flxf/form'],
		attributes: {
			label: { type: 'string', default: '' },
			help: { type: 'string', default: '' },
			icon: { type: 'string', default: '' },
			efb: { type: 'object', default: {} },
		},
		supports: { html: false, reusable: false },
		edit: StepEdit,
		save: () => null,
	});

	registerBlockType('flxf/raw', {
		apiVersion: 3,
		title: __('Flux Forms field', 'flx-forms'),
		description: __('A field type the block editor does not know yet. It is kept as it is.', 'flx-forms'),
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
			const blockProps = useBlockProps({ className: 'flxf-b-field flxf-b-raw' });
			return (
				<div {...blockProps}>
					<span className="flxf-b-label">{r.name || r.type}</span>
					<Notice status="info" isDismissible={false}>
						{__('This field type is not available as a block. It is kept unchanged.', 'flx-forms')} <code>{r.type}</code>
						{formId && cfg().canManage ? (
							<>
								{' '}
								<ExternalLink href={`${cfg().panelUrl}&state=edit-form&id=${formId}`}>{__('Edit in panel', 'flx-forms')}</ExternalLink>
							</>
						) : null}
					</Notice>
				</div>
			);
		},
		save: () => null,
	});
}
