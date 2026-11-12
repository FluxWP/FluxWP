/**
 * BLOCK: Flux Advanced Form
 */

/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';
import { useState, useCallback, useEffect, useMemo } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { size, get, isEqual } from 'lodash';
import { addQueryArgs } from '@wordpress/url';
import { applyFilters } from '@wordpress/hooks';
import { useEntityBlockEditor, useEntityProp } from '@wordpress/core-data';
import { formBlockIcon } from '@flx-blocks/icons';
import {
	FlxbkPanelBody,
	InspectorControlTabs,
	URLInputControl,
	ResponsiveMeasureRangeControl,
	SpacingVisualizer,
	BackgroundTypeControl,
	PopColorControl,
	ResponsiveRangeControls,
	GradientControl,
	SelectPostFromPostType,
} from '@flx-blocks/components';
import {
	getPreviewSize,
	FlxbkColorOutput,
	getSpacingOptionOutput,
	mouseOverVisualizer,
	arrayStringToInt,
	allowOneBlockOfType,
} from '@flx-blocks/helpers';

import {
	InspectorControls,
	BlockControls,
	useInnerBlocksProps,
	InspectorAdvancedControls,
	store as editorStore,
} from '@wordpress/block-editor';
import {
	TextControl,
	SelectControl,
	ToggleControl,
	ToolbarGroup,
	ExternalLink,
	Button,
	Placeholder,
	TextareaControl,
} from '@wordpress/components';

import {
	FieldStyles,
	SubmitActionOptions,
	LabelOptions,
	RadioLabelOptions,
	HelpTextOptions,
	MailerLiteOptions,
	FluentCrmOptions,
	SendinBlueOptions,
	MailchimpOptions,
	ConvertKitOptions,
	ActiveCampaignOptions,
	GetResponseOptions,
	FormTitle,
	WebhookOptions,
	AutoEmailOptions,
	DbEntryOptions,
	BackendStyles,
	MessageOptions,
	MessageStyling,
	getFormFields,
	dedupeFormFieldUniqueIds,
	FieldBlockAppender,
} from './components';

/**
 * Internal dependencies
 */
import classnames from 'classnames';
import { useEntityPublish } from './hooks';
const ALLOWED_BLOCKS = [
	'core/paragraph',
	'flx/advancedheading',
	'flx/spacer',
	'flx/rowlayout',
	'flx/column',
	'flx/advanced-form-text',
	'flx/advanced-form-textarea',
	'flx/advanced-form-select',
	'flx/advanced-form-submit',
	'flx/advanced-form-radio',
	'flx/advanced-form-file',
	'flx/advanced-form-time',
	'flx/advanced-form-date',
	'flx/advanced-form-telephone',
	'flx/advanced-form-checkbox',
	'flx/advanced-form-email',
	'flx/advanced-form-accept',
	'flx/advanced-form-number',
	'flx/advanced-form-hidden',
	'flx/advanced-form-captcha',
];

/**
 * Regular expression matching invalid anchor characters for replacement.
 *
 * @type {RegExp}
 */
const ANCHOR_REGEX = /[\s#]/g;
export function EditInner(props) {
	const { attributes, setAttributes, clientId, direct, id, isSelected } = props;
	const { uniqueID } = attributes;

	const { previewDevice } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
			};
		},
		[clientId]
	);

	const [activeTab, setActiveTab] = useState('general');

	const paddingMouseOver = mouseOverVisualizer();
	const marginMouseOver = mouseOverVisualizer();

	const [fields] = useFormMeta('_flxbk_form_fields');
	const [email] = useFormMeta('_flxbk_form_email');
	const [actions] = useFormMeta('_flxbk_form_actions');
	const [mailerlite] = useFormMeta('_flxbk_form_mailerlite');
	const [fluentcrm] = useFormMeta('_flxbk_form_fluentcrm');
	const [sendinblue] = useFormMeta('_flxbk_form_sendinblue');
	const [mailchimp] = useFormMeta('_flxbk_form_mailchimp');
	const [convertkit] = useFormMeta('_flxbk_form_convertkit');
	const [activecampaign] = useFormMeta('_flxbk_form_activecampaign');
	const [getresponse] = useFormMeta('_flxbk_form_getresponse');

	const [redirect] = useFormMeta('_flxbk_form_redirect');
	const [description] = useFormMeta('_flxbk_form_description');

	const [webhook] = useFormMeta('_flxbk_form_webhook');
	const [autoEmail] = useFormMeta('_flxbk_form_autoEmail');
	const [entry] = useFormMeta('_flxbk_form_entry');
	const [messages] = useFormMeta('_flxbk_form_messages');

	const [labelFont] = useFormMeta('_flxbk_form_labelFont');
	const [inputFont] = useFormMeta('_flxbk_form_inputFont');

	const [padding] = useFormMeta('_flxbk_form_padding');
	const [tabletPadding] = useFormMeta('_flxbk_form_tabletPadding');
	const [mobilePadding] = useFormMeta('_flxbk_form_mobilePadding');
	const [paddingUnit] = useFormMeta('_flxbk_form_paddingUnit');

	const [margin] = useFormMeta('_flxbk_form_margin');
	const [tabletMargin] = useFormMeta('_flxbk_form_tabletMargin');
	const [mobileMargin] = useFormMeta('_flxbk_form_mobileMargin');
	const [marginUnit] = useFormMeta('_flxbk_form_marginUnit');

	const [style] = useFormMeta('_flxbk_form_style');
	const [background] = useFormMeta('_flxbk_form_background');
	const [helpFont] = useFormMeta('_flxbk_form_helpFont');
	const [radioLabelFont] = useFormMeta('_flxbk_form_radioLabelFont');
	const [maxWidth] = useFormMeta('_flxbk_form_maxWidth');
	const [maxWidthUnit] = useFormMeta('_flxbk_form_maxWidthUnit');
	const [submitHide] = useFormMeta('_flxbk_form_submitHide');
	const [browserValidation] = useFormMeta('_flxbk_form_browserValidation');
	const [enableAnalytics] = useFormMeta('_flxbk_form_enableAnalytics');

	const [className] = useFormMeta('_flxbk_form_className');
	const [anchor] = useFormMeta('_flxbk_form_anchor');

	const [meta, setMeta] = useFormProp('meta');

	const setMetaAttribute = (value, key) => {
		setMeta({ ...meta, ['_flxbk_form_' + key]: value });
	};
	const saveBackgroundStyle = (value) => {
		setMetaAttribute({ ...background, ...value }, 'background');
	};
	const saveStyle = (value) => {
		setMetaAttribute({ ...style, ...value }, 'style');
	};
	const saveInputFont = (value) => {
		setMetaAttribute({ ...inputFont, ...value }, 'inputFont');
	};
	const saveLabelFont = (value) => {
		setMetaAttribute({ ...labelFont, ...value }, 'labelFont');
	};

	const previewMarginTop = getPreviewSize(
		previewDevice,
		undefined !== margin ? margin[0] : '',
		undefined !== tabletMargin ? tabletMargin[0] : '',
		undefined !== mobileMargin ? mobileMargin[0] : ''
	);
	const previewMarginRight = getPreviewSize(
		previewDevice,
		undefined !== margin ? margin[1] : '',
		undefined !== tabletMargin ? tabletMargin[1] : '',
		undefined !== mobileMargin ? mobileMargin[1] : ''
	);
	const previewMarginBottom = getPreviewSize(
		previewDevice,
		undefined !== margin ? margin[2] : '',
		undefined !== tabletMargin ? tabletMargin[2] : '',
		undefined !== mobileMargin ? mobileMargin[2] : ''
	);
	const previewMarginLeft = getPreviewSize(
		previewDevice,
		undefined !== margin ? margin[3] : '',
		undefined !== tabletMargin ? tabletMargin[3] : '',
		undefined !== mobileMargin ? mobileMargin[3] : ''
	);

	const previewPaddingTop = getPreviewSize(
		previewDevice,
		undefined !== padding ? padding[0] : '',
		undefined !== tabletPadding ? tabletPadding[0] : '',
		undefined !== mobilePadding ? mobilePadding[0] : ''
	);
	const previewPaddingRight = getPreviewSize(
		previewDevice,
		undefined !== padding ? padding[1] : '',
		undefined !== tabletPadding ? tabletPadding[1] : '',
		undefined !== mobilePadding ? mobilePadding[1] : ''
	);
	const previewPaddingBottom = getPreviewSize(
		previewDevice,
		undefined !== padding ? padding[2] : '',
		undefined !== tabletPadding ? tabletPadding[2] : '',
		undefined !== mobilePadding ? mobilePadding[2] : ''
	);
	const previewPaddingLeft = getPreviewSize(
		previewDevice,
		undefined !== padding ? padding[3] : '',
		undefined !== tabletPadding ? tabletPadding[3] : '',
		undefined !== mobilePadding ? mobilePadding[3] : ''
	);

	const previewMaxWidth = getPreviewSize(
		previewDevice,
		undefined !== maxWidth?.[0] ? maxWidth[0] : '',
		undefined !== maxWidth?.[1] ? maxWidth[1] : '',
		undefined !== maxWidth?.[2] ? maxWidth[2] : ''
	);
	const backgroundType =
		undefined !== background?.backgroundType && '' !== background?.backgroundType
			? background.backgroundType
			: 'normal';
	let hasBackground =
		'normal' === backgroundType && undefined !== background?.background && '' !== background?.background
			? true
			: false;
	if (!hasBackground) {
		hasBackground =
			'gradient' === backgroundType && undefined !== background?.gradient && '' !== background?.gradient
				? true
				: false;
	}
	const previewBackground = 'gradient' === backgroundType ? background?.gradient : background?.background;

	const formClasses = classnames({
		'fxb-advanced-form': true,
		[`fxb-advanced-form-${id}`]: true,
		[`fxb-form${uniqueID}`]: uniqueID,
		[`fxb-form-label-style-${style?.labelStyle}`]: style?.labelStyle,
		[`fxb-form-basic-style`]: undefined !== style?.basicStyles && false === style.basicStyles ? false : true,
		[`fxb-form-hide-required-asterisk`]:
			undefined !== style?.showRequired && false === style.showRequired ? true : false,
		[`fxb-form-has-background`]: hasBackground,
		[`fxb-form-is-dark`]: undefined !== style?.isDark && true === style.isDark,
		[`fxb-form-input-size-${style?.size}`]: style?.size,
	});

	const [title, setTitle] = useFormProp('title');

	let [blocks, onInput, onChange] = useEntityBlockEditor('postType', 'flxbk_form', id);
	const { updateBlockAttributes } = useDispatch(editorStore);

	const emptyForm = useMemo(() => {
		return [createBlock('flx/advanced-form', {})];
	}, [clientId]);

	if (blocks.length === 0) {
		blocks = emptyForm;
	}

	const formInnerBlocks = useMemo(() => {
		return get(blocks, [0, 'innerBlocks'], []);
	}, [blocks]);

	const filterDuplicateBlocks = allowOneBlockOfType();
	useEffect(() => {
		filterDuplicateBlocks(formInnerBlocks, 'flx/advanced-form-submit', __('submit button', 'flx-blocks'));
		filterDuplicateBlocks(formInnerBlocks, 'flx/advanced-form-captcha', __('captcha', 'flx-blocks'));
	}, [formInnerBlocks, filterDuplicateBlocks]);

	const newBlock = useMemo(() => {
		return get(blocks, [0], {});
	}, [blocks]);

	useEffect(() => {
		if (Array.isArray(formInnerBlocks) && formInnerBlocks.length) {
			dedupeFormFieldUniqueIds(formInnerBlocks, updateBlockAttributes, id);

			const currentFields = getFormFields(formInnerBlocks);

			if (!isEqual(fields, currentFields)) {
				setMetaAttribute(currentFields, 'fields');
			}
		}
	}, [formInnerBlocks]);

	const showAnalytics = useMemo(() => {
		return applyFilters('flxbk.analyticsOptionAdvancedForm', false);
	}, []);

	const [isAdding, addNew] = useEntityPublish('flxbk_form', id);
	const onAdd = async (title, template, style, initialDescription) => {
		try {
			const response = await addNew();
			let buttonAttributes = {
				text: __('Submit', 'flx-blocks'),
				widthType: 'full',
			};
			if ('subscribe' === template && 'infield' === style) {
				buttonAttributes = {
					typography: [
						{
							size: ['', '', ''],
							sizeType: 'px',
							lineHeight: ['42', '', ''],
							lineType: 'px',
							letterSpacing: ['', '', ''],
							letterType: 'px',
							textTransform: '',
							family: '',
							google: '',
							style: '',
							weight: '',
							variant: '',
							subset: '',
							loadGoogle: true,
						},
					],
					text: __('Submit', 'flx-blocks'),
					widthType: 'full',
				};
			}
			if (response.id) {
				switch (template) {
					case 'contact':
						onChange(
							[
								{
									...newBlock,
									innerBlocks: [
										createBlock(
											'flx/rowlayout',
											{ colLayout: 'equal', padding: ['0', '0', '0', '0'] },
											[
												createBlock('flx/column', {}, [
													createBlock('flx/advanced-form-text', { label: __('Name', 'flx-blocks') }),
												]),
												createBlock('flx/column', {}, [
													createBlock('flx/advanced-form-email', {
														label: __('Email', 'flx-blocks'),
														required: true,
													}),
												]),
											]
										),
										createBlock('flx/advanced-form-textarea', {
											label: __('Message', 'flx-blocks'),
											required: true,
										}),
										createBlock('flx/advanced-form-submit', { text: __('Submit', 'flx-blocks') }),
									],
								},
							],
							clientId
						);
						break;
					case 'contactAdvanced':
						onChange(
							[
								{
									...newBlock,
									innerBlocks: [
										createBlock(
											'flx/rowlayout',
											{ colLayout: 'equal', padding: ['0', '0', '0', '0'] },
											[
												createBlock('flx/column', {}, [
													createBlock('flx/advanced-form-text', { label: __('Name', 'flx-blocks') }),
												]),
												createBlock('flx/column', {}, [
													createBlock('flx/advanced-form-email', {
														label: __('Email', 'flx-blocks'),
														required: true,
													}),
												]),
											]
										),
										createBlock(
											'flx/rowlayout',
											{ colLayout: 'equal', padding: ['0', '0', '0', '0'] },
											[
												createBlock('flx/column', {}, [
													createBlock('flx/advanced-form-select', { label: __('Option 1', 'flx-blocks') }),
												]),
												createBlock('flx/column', {}, [
													createBlock('flx/advanced-form-select', { label: __('Option 2', 'flx-blocks') }),
												]),
											]
										),
										createBlock('flx/advanced-form-textarea', {
											label: __('Message', 'flx-blocks'),
											required: true,
										}),
										createBlock('flx/advanced-form-submit', { text: __('Submit', 'flx-blocks') }),
									],
								},
							],
							clientId
						);
						break;
					case 'subscribeAdvanced':
						onChange(
							[
								{
									...newBlock,
									innerBlocks: [
										createBlock(
											'flx/rowlayout',
											{ colLayout: 'equal', padding: ['0', '0', '0', '0'] },
											[
												createBlock('flx/column', {}, [
													createBlock('flx/advanced-form-text', { label: __('Name', 'flx-blocks') }),
												]),
												createBlock('flx/column', {}, [
													createBlock('flx/advanced-form-email', {
														label: __('Email', 'flx-blocks'),
														required: true,
													}),
												]),
											]
										),
										createBlock('flx/advanced-form-submit', { text: __('Submit', 'flx-blocks') }),
									],
								},
							],
							clientId
						);
						break;
					case 'subscribe':
						onChange(
							[
								{
									...newBlock,
									innerBlocks: [
										createBlock(
											'flx/rowlayout',
											{ colLayout: 'left-golden', padding: ['0', '0', '0', '0'] },
											[
												createBlock('flx/column', {}, [
													createBlock('flx/advanced-form-email', {
														label: __('Email', 'flx-blocks'),
														required: true,
													}),
												]),
												createBlock('flx/column', { verticalAlignment: 'bottom' }, [
													createBlock('flx/advanced-form-submit', buttonAttributes),
												]),
											]
										),
									],
								},
							],
							clientId
						);
						break;
					default:
						onChange(
							[
								{
									...newBlock,
									innerBlocks: [
										createBlock('core/paragraph', {}),
										createBlock('flx/advanced-form-submit', {
											text: __('Submit', 'flx-blocks'),
											uniqueID: '1_default',
										}),
									],
								},
							],
							clientId
						);
						break;
				}
				setTitle(title);
				const updatedMeta = meta;
				if (
					flxbk_params.pro === 'true' &&
					updatedMeta?._flxbk_form_actions &&
					!updatedMeta._flxbk_form_actions.includes('entry')
				) {
					updatedMeta._flxbk_form_actions.push('entry');
				}
				updatedMeta._flxbk_form_description = initialDescription;
				if (style === 'dark') {
					updatedMeta._flxbk_form_style = {
						...updatedMeta._flxbk_form_style,
						background: 'palette3',
						isDark: true,
						placeholderColor: 'palette6',
						borderActive: 'palette7',
					};
					updatedMeta._flxbk_form_inputFont = { ...updatedMeta._flxbk_form_inputFont, color: 'palette9' };
					updatedMeta._flxbk_form_labelFont = { ...updatedMeta._flxbk_form_labelFont, color: 'palette9' };
					updatedMeta._flxbk_form_background = { ...updatedMeta._flxbk_form_background, background: 'palette3' };
					const borderStyle = {
						top: ['palette6', '', 1],
						right: ['palette6', '', 1],
						bottom: ['palette6', '', 1],
						left: ['palette6', '', 1],
						unit: 'px',
					};
					updatedMeta._flxbk_form_fieldBorderStyle = borderStyle;
				} else if (style === 'infield') {
					updatedMeta._flxbk_form_style = { ...updatedMeta._flxbk_form_style, labelStyle: 'infield' };
				} else if (style === 'underline') {
					const borderStyle = {
						top: ['#B9B9C5', '', 0],
						right: ['#B9B9C5', '', 0],
						bottom: ['#B9B9C5', '', 2],
						left: ['#B9B9C5', '', 0],
						unit: 'px',
					};
					updatedMeta._flxbk_form_labelFont = { ...updatedMeta._flxbk_form_labelFont, color: '#909097' };
					updatedMeta._flxbk_form_style = {
						...updatedMeta._flxbk_form_style,
						labelStyle: 'float',
						borderActive: 'palette1',
						boxShadow: [true, '#000000', 0, 0, 0, 0, 0, false],
						boxShadowActive: [true, '#000000', 0, 0, 0, 0, 0, false],
					};
					updatedMeta._flxbk_form_fieldBorderStyle = borderStyle;
					updatedMeta._flxbk_form_fieldBorderRadius = [0, 0, 0, 0];
				}
				setMeta({ ...meta, ...updatedMeta });
				await wp.data.dispatch('core').saveEditedEntityRecord('postType', 'flxbk_form', id);
			}
		} catch (error) {
			console.error(error);
		}
	};
	const useFieldBlockAppender = () => {
		if (isSelected) {
			return null;
		}
		return <FieldBlockAppender inline={true} rootClientId={clientId} />;
	};
	const useFieldBlockAppenderBase = () => {
		return <FieldBlockAppender inline={false} rootClientId={clientId} />;
	};
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: formClasses,
			style: {
				marginTop: '' !== previewMarginTop ? getSpacingOptionOutput(previewMarginTop, marginUnit) : undefined,
				marginRight:
					'' !== previewMarginRight ? getSpacingOptionOutput(previewMarginRight, marginUnit) : undefined,
				marginBottom:
					'' !== previewMarginBottom ? getSpacingOptionOutput(previewMarginBottom, marginUnit) : undefined,
				marginLeft:
					'' !== previewMarginLeft ? getSpacingOptionOutput(previewMarginLeft, marginUnit) : undefined,

				paddingTop:
					'' !== previewPaddingTop ? getSpacingOptionOutput(previewPaddingTop, paddingUnit) : undefined,
				paddingRight:
					'' !== previewPaddingRight ? getSpacingOptionOutput(previewPaddingRight, paddingUnit) : undefined,
				paddingBottom:
					'' !== previewPaddingBottom ? getSpacingOptionOutput(previewPaddingBottom, paddingUnit) : undefined,
				paddingLeft:
					'' !== previewPaddingLeft ? getSpacingOptionOutput(previewPaddingLeft, paddingUnit) : undefined,

				maxWidth: '' !== previewMaxWidth ? previewMaxWidth + maxWidthUnit : undefined,
				background: '' !== previewBackground ? FlxbkColorOutput(previewBackground) : undefined,
			},
		},
		{
			allowedBlocks: ALLOWED_BLOCKS,
			value: !direct ? formInnerBlocks : undefined,
			onInput: !direct ? (a, b) => onInput([{ ...newBlock, innerBlocks: a }], b) : undefined,
			onChange: !direct ? (a, b) => onChange([{ ...newBlock, innerBlocks: a }], b) : undefined,
			templateLock: false,
			renderAppender: formInnerBlocks.length === 0 ? useFieldBlockAppenderBase : useFieldBlockAppender,
		}
	);

	if (formInnerBlocks.length === 0) {
		return (
			<>
				<FormTitle onAdd={onAdd} isAdding={isAdding} existingTitle={title} />
				<div className="fxb-form-hide-while-setting-up">
					<div {...innerBlocksProps} />
				</div>
			</>
		);
	}
	if (typeof pagenow !== 'undefined' && ('widgets' === pagenow || 'customize' === pagenow)) {
		const editPostLink = addQueryArgs('post.php', {
			post: id,
			action: 'edit',
		});
		return (
			<>
				<Placeholder
					className="fxb-select-or-create-placeholder"
					label={__('Flux Form', 'flx-blocks')}
					icon={formBlockIcon}
				>
					<p style={{ width: '100%', marginBottom: '10px' }}>
						{__('Advanced forms can not be edited within the widgets screen.', 'flx-blocks')}
					</p>
					<Button href={editPostLink} variant="primary" className="fxb-form-edit-link">
						{__('Edit Form', 'flx-blocks')}
					</Button>
				</Placeholder>
				<InspectorControls>
					<FlxbkPanelBody
						panelName={'fxb-advanced-form-selected-switch'}
						title={__('Selected Form', 'flx-blocks')}
					>
						<SelectPostFromPostType
							postType="flxbk_form"
							label={__('Selected Form', 'flx-blocks')}
							hideLabelFromVision={true}
							onChange={(nextId) => {
								setAttributes({ id: parseInt(nextId) });
							}}
							value={id}
						/>
					</FlxbkPanelBody>
				</InspectorControls>
			</>
		);
	}
	return (
		<>
			<style>
				{isSelected && (
					<>
						{`.block-editor-block-popover__inbetween-container .block-editor-block-list__insertion-point.is-with-inserter { display: none }`}
						;
					</>
				)}
			</style>
			<BlockControls>
				<ToolbarGroup group="add-block" className="fxb-add-block">
					<FieldBlockAppender rootClientId={clientId} />
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<InspectorControlTabs
					panelName={'advanced-form'}
					setActiveTab={(value) => setActiveTab(value)}
					activeTab={activeTab}
				/>

				{activeTab === 'general' && (
					<>
						<FlxbkPanelBody
							panelName={'fxb-advanced-form-selected-switch'}
							title={__('Form', 'flx-blocks')}
						>
							{!direct && (
								<SelectPostFromPostType
									postType="flxbk_form"
									label={__('Selected Form', 'flx-blocks')}
									hideLabelFromVision={true}
									onChange={(nextId) => setAttributes({ id: parseInt(nextId) })}
									value={id}
								/>
							)}
							<TextareaControl
								label={__('Form Description', 'flx-blocks')}
								placeholder={__('Optionally add an description about your form', 'flx-blocks')}
								help={__('This is used for your reference only.', 'flx-blocks')}
								value={undefined !== description ? description : ''}
								onChange={(value) => setMetaAttribute(value, 'description')}
							/>
						</FlxbkPanelBody>
						<FlxbkPanelBody
							panelName={'fxb-advanced-form-submit-actions'}
							title={__('Submit Actions', 'flx-blocks')}
						>
							<SubmitActionOptions setAttributes={setMetaAttribute} selectedActions={actions} />
							<ToggleControl
								label={__('Hide form after submit?', 'flx-blocks')}
								checked={undefined !== submitHide ? submitHide : false}
								onChange={(value) => {
									setMetaAttribute(value, 'submitHide');
								}}
							/>
						</FlxbkPanelBody>

						{size(actions) > 0 && <div className="fxt-sidebar-settings-spacer"></div>}

						{actions.includes('email') && (
							<FlxbkPanelBody
								title={__('Email Settings', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-form-email-settings'}
							>
								<TextControl
									label={__('Email To Address', 'flx-blocks')}
									placeholder={__('name@example.com', 'flx-blocks')}
									value={undefined !== email.emailTo ? email.emailTo : ''}
									onChange={(value) => setMetaAttribute({ ...email, emailTo: value }, 'email')}
									help={__(
										'Separate with a comma for more than one email address.',
										'flx-blocks'
									)}
								/>
								<TextControl
									label={__('Email Subject', 'flx-blocks')}
									value={undefined !== email.subject ? email.subject : ''}
									onChange={(value) => setMetaAttribute({ ...email, subject: value }, 'email')}
								/>
								<TextControl
									label={__('From Email', 'flx-blocks')}
									value={undefined !== email.fromEmail ? email.fromEmail : ''}
									onChange={(value) => setMetaAttribute({ ...email, fromEmail: value }, 'email')}
								/>
								<TextControl
									label={__('From Name', 'flx-blocks')}
									value={undefined !== email.fromName ? email.fromName : ''}
									onChange={(value) => setMetaAttribute({ ...email, fromName: value }, 'email')}
								/>
								<SelectControl
									label={__('Reply To', 'flx-blocks')}
									value={email.replyTo}
									options={[
										{ value: 'email_field', label: __('Email Field', 'flx-blocks') },
										{ value: 'from_email', label: __('From Email', 'flx-blocks') },
									]}
									onChange={(value) => {
										setMetaAttribute({ ...email, replyTo: value }, 'email');
									}}
								/>
								<TextControl
									label={__('Cc', 'flx-blocks')}
									value={undefined !== email.cc ? email.cc : ''}
									onChange={(value) => setMetaAttribute({ ...email, cc: value }, 'email')}
								/>
								<TextControl
									label={__('Bcc', 'flx-blocks')}
									value={undefined !== email.bcc ? email.bcc : ''}
									onChange={(value) => setMetaAttribute({ ...email, bcc: value }, 'email')}
								/>
								<ToggleControl
									label={__('Send as HTML email?', 'flx-blocks')}
									help={__('If off, plain text is used.', 'flx-blocks')}
									checked={undefined !== email.html ? email.html : true}
									onChange={(value) => setMetaAttribute({ ...email, html: value }, 'email')}
								/>
							</FlxbkPanelBody>
						)}

						{actions.includes('redirect') && (
							<FlxbkPanelBody
								title={__('Redirect Settings', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-form-redirect-settings'}
							>
								<URLInputControl
									label={__('Redirect to', 'flx-blocks')}
									url={redirect}
									onChangeUrl={(value) => setMetaAttribute(value, 'redirect')}
									additionalControls={false}
								/>
							</FlxbkPanelBody>
						)}

						{actions.includes('mailerlite') && (
							<MailerLiteOptions
								parentClientId={clientId}
								formInnerBlocks={formInnerBlocks}
								settings={mailerlite}
								save={(value) => {
									setMetaAttribute({ ...mailerlite, ...value }, 'mailerlite');
								}}
							/>
						)}

						{actions.includes('fluentCRM') && (
							<FluentCrmOptions
								parentClientId={clientId}
								formInnerBlocks={formInnerBlocks}
								settings={fluentcrm}
								save={(value) => setMetaAttribute({ ...fluentcrm, ...value }, 'fluentcrm')}
							/>
						)}

						{actions.includes('sendinblue') && (
							<SendinBlueOptions
								parentClientId={clientId}
								formInnerBlocks={formInnerBlocks}
								settings={sendinblue}
								save={(value) => setMetaAttribute({ ...sendinblue, ...value }, 'sendinblue')}
							/>
						)}

						{actions.includes('mailchimp') && (
							<MailchimpOptions
								parentClientId={clientId}
								formInnerBlocks={formInnerBlocks}
								settings={mailchimp}
								save={(value) => setMetaAttribute({ ...mailchimp, ...value }, 'mailchimp')}
							/>
						)}

						{actions.includes('convertkit') && (
							<ConvertKitOptions
								parentClientId={clientId}
								formInnerBlocks={formInnerBlocks}
								settings={convertkit}
								save={(value) => setMetaAttribute({ ...convertkit, ...value }, 'convertkit')}
							/>
						)}

						{actions.includes('activecampaign') && (
							<ActiveCampaignOptions
								parentClientId={clientId}
								formInnerBlocks={formInnerBlocks}
								settings={activecampaign}
								save={(value) => setMetaAttribute({ ...activecampaign, ...value }, 'activecampaign')}
							/>
						)}

						{actions.includes('getresponse') && (
							<GetResponseOptions
								parentClientId={clientId}
								formInnerBlocks={formInnerBlocks}
								settings={getresponse}
								save={(value) => setMetaAttribute({ ...getresponse, ...value }, 'getresponse')}
							/>
						)}

						{actions.includes('webhook') && (
							<WebhookOptions
								parentClientId={clientId}
								formInnerBlocks={formInnerBlocks}
								settings={webhook}
								save={(value) => setMetaAttribute({ ...webhook, ...value }, 'webhook')}
							/>
						)}

						{actions.includes('autoEmail') && (
							<AutoEmailOptions
								settings={autoEmail}
								save={(value) => setMetaAttribute({ ...autoEmail, ...value }, 'autoEmail')}
							/>
						)}

						{actions.includes('entry') && (
							<DbEntryOptions
								settings={entry}
								save={(value) => setMetaAttribute({ ...entry, ...value }, 'entry')}
							/>
						)}
						<div className="fxt-sidebar-settings-spacer"></div>
						{/*<FlxbkPanelBody*/}
						{/*	panelName={'fxb-advanced-form-spam'}*/}
						{/*	title={__( 'Spam Prevention', 'flx-blocks' )}*/}
						{/*	initialOpen={false}*/}
						{/*>*/}
						{/*	*/}
						{/*</FlxbkPanelBody>*/}
						<FlxbkPanelBody
							title={__('Message Settings', 'flx-blocks')}
							initialOpen={false}
							panelName={'fxb-form-message'}
						>
							<MessageOptions setAttributes={setMetaAttribute} messages={messages} />
						</FlxbkPanelBody>
					</>
				)}

				{activeTab === 'style' && (
					<>
						<FlxbkPanelBody
							title={__('Input Fields', 'flx-blocks')}
							initialOpen={true}
							panelName={'fxb-form-field-styles'}
						>
							<FieldStyles
								setMetaAttribute={setMetaAttribute}
								inputFont={inputFont}
								style={style}
								useFormMeta={useFormMeta}
							/>
						</FlxbkPanelBody>

						{/* Label Styles*/}
						<FlxbkPanelBody
							title={__('Labels', 'flx-blocks')}
							initialOpen={false}
							panelName={'fxb-form-label-styles'}
						>
							<LabelOptions
								styleAttribute={style}
								setAttributes={setMetaAttribute}
								labelFont={labelFont}
							/>
						</FlxbkPanelBody>
						{/* Label Styles*/}
						<FlxbkPanelBody
							title={__('Radio/Checkbox Labels', 'flx-blocks')}
							initialOpen={false}
							panelName={'fxb-form-radio-label-styles'}
						>
							<RadioLabelOptions setAttributes={setMetaAttribute} radioLabelFont={radioLabelFont} />
						</FlxbkPanelBody>
						{/* Help Text Styles*/}
						<FlxbkPanelBody
							title={__('Help Text', 'flx-blocks')}
							initialOpen={false}
							panelName={'fxb-form-help-styles'}
						>
							<HelpTextOptions setAttributes={setMetaAttribute} helpFont={helpFont} />
						</FlxbkPanelBody>

						<FlxbkPanelBody
							title={__('Message Styling', 'flx-blocks')}
							initialOpen={false}
							panelName={'fxb-form-message'}
						>
							<MessageStyling setMetaAttribute={setMetaAttribute} useFormMeta={useFormMeta} />
						</FlxbkPanelBody>

						<FlxbkPanelBody
							title={__('Background', 'flx-blocks')}
							initialOpen={false}
							panelName={'fxb-form-background'}
						>
							<BackgroundTypeControl
								label={__('Background Type', 'flx-blocks')}
								type={background?.backgroundType ? background.backgroundType : 'normal'}
								onChange={(value) => saveBackgroundStyle({ backgroundType: value })}
								allowedTypes={['normal', 'gradient']}
							/>
							{'gradient' !== background?.backgroundType && (
								<PopColorControl
									label={__('Background', 'flx-blocks')}
									value={background?.background ? background.background : ''}
									default={''}
									onChange={(value) => {
										saveBackgroundStyle({ background: value });
									}}
								/>
							)}
							{'gradient' === background?.backgroundType && (
								<GradientControl
									value={background?.gradient}
									onChange={(value) => {
										saveBackgroundStyle({ gradient: value });
									}}
									gradients={[]}
								/>
							)}
						</FlxbkPanelBody>
					</>
				)}

				{activeTab === 'advanced' && (
					<>
						<FlxbkPanelBody panelName={'fxb-row-padding'}>
							<ResponsiveMeasureRangeControl
								label={__('Padding', 'flx-blocks')}
								value={arrayStringToInt(padding)}
								tabletValue={arrayStringToInt(tabletPadding)}
								mobileValue={arrayStringToInt(mobilePadding)}
								onChange={(value) => {
									setMetaAttribute(value.map(String), 'padding');
								}}
								onChangeTablet={(value) => {
									setMetaAttribute(value.map(String), 'tabletPadding');
								}}
								onChangeMobile={(value) => {
									setMetaAttribute(value.map(String), 'mobilePadding');
								}}
								min={0}
								max={paddingUnit === 'em' || paddingUnit === 'rem' ? 24 : 999}
								step={paddingUnit === 'em' || paddingUnit === 'rem' ? 0.1 : 1}
								unit={paddingUnit}
								units={['px', 'em', 'rem', '%']}
								onUnit={(value) => setMetaAttribute(value, 'paddingUnit')}
								onMouseOver={paddingMouseOver.onMouseOver}
								onMouseOut={paddingMouseOver.onMouseOut}
							/>
							<ResponsiveMeasureRangeControl
								label={__('Margin', 'flx-blocks')}
								value={arrayStringToInt(margin)}
								tabletValue={arrayStringToInt(tabletMargin)}
								mobileValue={arrayStringToInt(mobileMargin)}
								onChange={(value) => {
									setMetaAttribute(value.map(String), 'margin');
								}}
								onChangeTablet={(value) => {
									setMetaAttribute(value.map(String), 'tabletMargin');
								}}
								onChangeMobile={(value) => {
									setMetaAttribute(value.map(String), 'mobileMargin');
								}}
								min={marginUnit === 'em' || marginUnit === 'rem' ? -12 : -999}
								max={marginUnit === 'em' || marginUnit === 'rem' ? 24 : 999}
								step={marginUnit === 'em' || marginUnit === 'rem' ? 0.1 : 1}
								unit={marginUnit}
								units={['px', 'em', 'rem', '%', 'vh']}
								onUnit={(value) => setMetaAttribute(value, 'marginUnit')}
								onMouseOver={marginMouseOver.onMouseOver}
								onMouseOut={marginMouseOver.onMouseOut}
								allowAuto={true}
							/>
						</FlxbkPanelBody>
						<FlxbkPanelBody initialOpen={true} panelName={'fxb-adv-form-max-width'}>
							<ResponsiveRangeControls
								label={__('Max Width', 'flx-blocks')}
								value={maxWidth[0] !== '' ? parseInt(maxWidth[0]) : ''}
								onChange={(value) => {
									value = value ? value : '';
									setMetaAttribute([value.toString(), maxWidth[1], maxWidth[2]], 'maxWidth');
								}}
								tabletValue={maxWidth[1] !== '' ? parseInt(maxWidth[1]) : ''}
								onChangeTablet={(value) => {
									value = value ? value : '';
									setMetaAttribute([maxWidth[0], value.toString(), maxWidth[2]], 'maxWidth');
								}}
								mobileValue={maxWidth[2] !== '' ? parseInt(maxWidth[2]) : ''}
								onChangeMobile={(value) => {
									value = value ? value : '';
									setMetaAttribute([maxWidth[0], maxWidth[1], value.toString()], 'maxWidth');
								}}
								min={0}
								max={maxWidthUnit === 'px' ? 2000 : 100}
								step={1}
								reset={() => setMetaAttribute(['', '', ''], 'maxWidth')}
								unit={maxWidthUnit ? maxWidthUnit : '%'}
								onUnit={(value) => {
									setMetaAttribute(value, 'maxWidthUnit');
								}}
								units={['px', '%', 'vw']}
							/>
						</FlxbkPanelBody>
						<FlxbkPanelBody
							title={__('Validation', 'flx-blocks')}
							initialOpen={false}
							panelName={'fxb-adv-form-browser-validation'}
						>
							<ToggleControl
								label={__('Use Browser Validation', 'flx-blocks')}
								checked={browserValidation}
								onChange={(value) => {
									setMetaAttribute(value, 'browserValidation');
								}}
								help={__(
									'This will use the browsers default validation for required fields. If disabled, custom error message will be displayed.',
									'flx-blocks'
								)}
							/>
						</FlxbkPanelBody>
						{showAnalytics && (
							<FlxbkPanelBody
								title={__('Analytics', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-adv-form-enable-analytics'}
							>
								<ToggleControl
									label={__('Enable Form Analytics', 'flx-blocks')}
									checked={enableAnalytics}
									onChange={(value) => {
										setMetaAttribute(value, 'enableAnalytics');
									}}
									help={__(
										'This will capture how many times the form is loaded, started, and submitted so you can have conversion analytics.',
										'flx-blocks'
									)}
								/>
							</FlxbkPanelBody>
						)}
					</>
				)}
			</InspectorControls>
			<InspectorAdvancedControls>
				<ToggleControl
					label={__('Form Styles', 'flx-blocks')}
					help={__(
						'Only disable if you intend to control form styles through custom css or theme',
						'flx-blocks'
					)}
					checked={undefined !== style?.basicStyles ? style.basicStyles : true}
					onChange={(value) => {
						setMetaAttribute({ ...style, ...{ basicStyles: value } }, 'style');
					}}
				/>

				<TextControl
					__nextHasNoMarginBottom
					className="html-anchor-control"
					label={__('HTML anchor', 'flx-blocks')}
					help={
						<>
							{__(
								'Enter a word or two — without spaces — to make a unique web address just for this block, called an “anchor.” Then, you’ll be able to link directly to this section of your page.',
								'flx-blocks'
							)}

							<ExternalLink
								href={__('https://wordpress.org/documentation/article/page-jumps/', 'flx-blocks')}
							>
								{__('Learn more about anchors', 'flx-blocks')}
							</ExternalLink>
						</>
					}
					value={anchor}
					placeholder={__('Add an anchor', 'flx-blocks')}
					onChange={(nextValue) => {
						nextValue = nextValue.replace(ANCHOR_REGEX, '-');
						setMetaAttribute(nextValue, 'anchor');
					}}
					autoCapitalize="none"
					autoComplete="off"
				/>

				<TextControl
					__nextHasNoMarginBottom
					autoComplete="off"
					label={__('Additional CSS class(es)', 'flx-blocks')}
					value={className}
					onChange={(nextValue) => {
						setMetaAttribute(nextValue !== '' ? nextValue : undefined, 'className');
					}}
					help={__('Separate multiple classes with spaces.', 'flx-blocks')}
				/>
			</InspectorAdvancedControls>
			<BackendStyles
				uniqueID={uniqueID}
				useFormMeta={useFormMeta}
				previewDevice={previewDevice}
				inputFont={inputFont}
				fieldStyle={style}
				labelStyle={labelFont}
				helpStyle={helpFont}
				radioLabelFont={radioLabelFont}
			/>

			<div {...innerBlocksProps} />
			<SpacingVisualizer
				style={{
					marginLeft:
						undefined !== previewMarginLeft
							? getSpacingOptionOutput(previewMarginLeft, marginUnit)
							: undefined,
					marginRight:
						undefined !== previewMarginRight
							? getSpacingOptionOutput(previewMarginRight, marginUnit)
							: undefined,
					marginTop:
						undefined !== previewMarginTop
							? getSpacingOptionOutput(previewMarginTop, marginUnit)
							: undefined,
					marginBottom:
						undefined !== previewMarginBottom
							? getSpacingOptionOutput(previewMarginBottom, marginUnit)
							: undefined,
				}}
				type="inside"
				forceShow={paddingMouseOver.isMouseOver}
				spacing={[
					getSpacingOptionOutput(previewPaddingTop, paddingUnit),
					getSpacingOptionOutput(previewPaddingRight, paddingUnit),
					getSpacingOptionOutput(previewPaddingBottom, paddingUnit),
					getSpacingOptionOutput(previewPaddingLeft, paddingUnit),
				]}
			/>
			<SpacingVisualizer
				type="inside"
				forceShow={marginMouseOver.isMouseOver}
				spacing={[
					getSpacingOptionOutput(previewMarginTop, marginUnit),
					getSpacingOptionOutput(previewMarginRight, marginUnit),
					getSpacingOptionOutput(previewMarginBottom, marginUnit),
					getSpacingOptionOutput(previewMarginLeft, marginUnit),
				]}
			/>
		</>
	);
}
export default EditInner;

function useFormProp(prop) {
	return useEntityProp('postType', 'flxbk_form', prop);
}

function useFormMeta(key) {
	const [meta, setMeta] = useFormProp('meta');

	return [
		meta[key],
		useCallback(
			(newValue) => {
				setMeta({ ...meta, [key]: newValue });
			},
			[key, setMeta]
		),
	];
}
