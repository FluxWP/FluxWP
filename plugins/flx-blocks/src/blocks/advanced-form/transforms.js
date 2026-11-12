/**
 * External dependencies
 */
import { kebabCase, cloneDeep } from 'lodash';

/**
 * Cadence dependencies
 */
import { FlxbkColorOutput } from '@flx-blocks/helpers';

/**
 * WordPress dependencies
 */
import { createBlock } from '@wordpress/blocks';

const LEGACY_FIELD_TYPE_MAP = {
	text: 'flx/advanced-form-text',
	email: 'flx/advanced-form-email',
	textarea: 'flx/advanced-form-textarea',
	select: 'flx/advanced-form-select',
	radio: 'flx/advanced-form-radio',
	checkbox: 'flx/advanced-form-checkbox',
	accept: 'flx/advanced-form-accept',
	hidden: 'flx/advanced-form-hidden',
	tel: 'flx/advanced-form-telephone',
};

const LEGACY_TYPE_ALIAS = {
	tel: 'telephone',
};

const transforms = {
	from: [
		{
			type: 'block',
			blocks: ['flx/form'],
			transform: (attributes) => {
				try {
					return migrateClassicForm(attributes);
				} catch (error) {
					// eslint-disable-next-line no-console
					console.error('Flux Block Editor: failed to migrate classic form', error);
					return createBlock('flx/form', attributes, []);
				}
			},
		},
	],
	to: [],
};

export default transforms;

function migrateClassicForm(attributes = {}) {
	const migrationPayload = prepareLegacyMigration(attributes);
	return createBlock('flx/advanced-form', {
		id: 0,
		uniqueID: generateUniqueId('advform'),
		legacyMigration: migrationPayload,
	});
}

function buildFieldBlueprints(fields) {
	const usedNames = new Set();
	const blueprints = [];

	fields.forEach((legacyField, index) => {
		const blockName = getAdvancedBlockName(legacyField.type);
		const uniqueID = generateUniqueId('fld');
		const inputName = ensureUniqueInputName(
			sanitizeInputName(legacyField.slug || legacyField.label || legacyField.type || `field-${index + 1}`),
			usedNames,
			index
		);
		const label = legacyField.label || '';
		const type = normalizeFieldType(legacyField.type);

		const attributes = buildFieldAttributes(blockName, legacyField, {
			uniqueID,
			inputName,
			label,
		});

		blueprints.push({
			blockName,
			attributes,
			uniqueID,
			inputName,
			label,
			type,
			legacyIndex: index,
		});
	});

	return blueprints;
}

function buildFieldAttributes(blockName, legacyField, context) {
	const shared = {
		uniqueID: context.uniqueID,
		formID: '',
		inputName: context.inputName,
		label: context.label,
		showLabel: legacyField.showLabel !== false,
		required: !!legacyField.required,
		helpText: legacyField.description || '',
		ariaDescription: legacyField.ariaLabel || '',
		requiredMessage: legacyField.requiredMessage || '',
		errorMessage: legacyField.errorMessage || '',
		maxWidth: normalizeResponsive(legacyField.width, 3, ['100', '', '']),
		maxWidthUnit: '%',
		minWidth: ['', '', ''],
		minWidthUnit: 'px',
		defaultValue: legacyField.default || '',
		placeholder: legacyField.placeholder || '',
		auto: legacyField.auto || '',
		autoCustom: legacyField.autoCustom || '',
		defaultParameter: legacyField.defaultParameter || '',
	};

	switch (blockName) {
		case 'flx/advanced-form-select':
			return {
				...shared,
				options: buildOptionList(legacyField, false),
				multiSelect: !!legacyField.multiSelect,
			};
		case 'flx/advanced-form-radio':
			return {
				...shared,
				options: buildOptionList(legacyField, true),
				inline: !!legacyField.inline,
			};
		case 'flx/advanced-form-checkbox':
			return {
				...shared,
				options: buildOptionList(legacyField, true),
				inline: !!legacyField.inline,
				defaultValue: Array.isArray(legacyField.default)
					? legacyField.default.join(',')
					: legacyField.default || '',
			};
		case 'flx/advanced-form-accept':
			return {
				...shared,
				description: legacyField.showLink
					? buildAcceptDescriptionWithLink(legacyField)
					: legacyField.label || '',
				helpText: legacyField.description || '',
				isChecked: !!legacyField.inline,
				defaultValue: legacyField.inline ? 'accept' : '',
				placeholder: legacyField.placeholder || '',
			};
		case 'flx/advanced-form-telephone':
			return shared;
		case 'flx/advanced-form-textarea':
			return {
				...shared,
				rows: Number.isFinite(legacyField.rows) ? legacyField.rows : 3,
			};
		case 'flx/advanced-form-hidden':
			return {
				uniqueID: context.uniqueID,
				formID: '',
				inputName: context.inputName,
				label: context.label,
				defaultValue: legacyField.default || '',
				defaultParameter: legacyField.defaultParameter || '',
			};
		default:
			return shared;
	}
}

function buildSubmitBlueprint(attributes) {
	const legacySubmit = Array.isArray(attributes.submit) && attributes.submit[0] ? attributes.submit[0] : {};
	const legacySubmitFont =
		Array.isArray(attributes.submitFont) && attributes.submitFont[0] ? attributes.submitFont[0] : {};
	const legacySubmitMargin =
		Array.isArray(attributes.submitMargin) && attributes.submitMargin[0] ? attributes.submitMargin[0] : {};

	const widthType = legacySubmit.widthType || 'auto';
	const widthUnit = widthType === 'fixed' ? 'px' : '%';
	const widthSource = widthType === 'fixed' ? legacySubmit.fixedWidth : legacySubmit.width;

	const baseShadow = Array.isArray(legacySubmit.boxShadow)
		? legacySubmit.boxShadow
		: [false, '#000000', 0.2, 1, 1, 2, 0, false];
	const hoverShadow = Array.isArray(legacySubmit.boxShadowHover)
		? legacySubmit.boxShadowHover
		: [false, '#000000', 0.4, 2, 2, 3, 0, false];

	return {
		blockName: 'flx/advanced-form-submit',
		attributes: {
			uniqueID: generateUniqueId('submit'),
			formID: '',
			text: legacySubmit.label && legacySubmit.label.length ? legacySubmit.label : 'Submit',
			label: attributes.submitLabel || '',
			style: legacySubmit.btnStyle || 'basic',
			sizePreset: legacySubmit.btnSize || legacySubmit.size || 'standard',
			widthType,
			widthUnit,
			width: normalizeResponsive(widthSource, 3, ['', '', '']),
			padding: normalizeBox(legacySubmit.deskPadding),
			tabletPadding: normalizeBox(legacySubmit.tabletPadding),
			mobilePadding: normalizeBox(legacySubmit.mobilePadding),
			paddingUnit: 'px',
			margin: normalizeBox(legacySubmitMargin.desk),
			tabletMargin: normalizeBox(legacySubmitMargin.tablet),
			mobileMargin: normalizeBox(legacySubmitMargin.mobile),
			marginUnit: legacySubmitMargin.unit || 'px',
			color: legacySubmit.color || '',
			backgroundType: legacySubmit.backgroundType || 'normal',
			background: normalizeColorWithOpacity(legacySubmit.background, legacySubmit.backgroundOpacity),
			gradient: convertLegacyGradient(
				legacySubmit.background,
				legacySubmit.backgroundOpacity,
				legacySubmit.gradient
			),
			colorHover: legacySubmit.colorHover || '',
			backgroundHoverType: legacySubmit.backgroundHoverType || 'normal',
			backgroundHover: normalizeColorWithOpacity(
				legacySubmit.backgroundHover,
				legacySubmit.backgroundHoverOpacity
			),
			gradientHover: convertLegacyGradient(
				legacySubmit.backgroundHover,
				legacySubmit.backgroundHoverOpacity,
				legacySubmit.gradientHover
			),
			borderStyle: [
				mapBorderStyleObject(
					legacySubmit.border,
					legacySubmit.borderOpacity,
					legacySubmit.borderWidth,
					legacySubmit.borderStyle || 'solid'
				),
			],
			tabletBorderStyle: [
				mapBorderStyleObject(
					legacySubmit.border,
					legacySubmit.borderOpacity,
					legacySubmit.borderWidth,
					legacySubmit.borderStyle || 'solid'
				),
			],
			mobileBorderStyle: [
				mapBorderStyleObject(
					legacySubmit.border,
					legacySubmit.borderOpacity,
					legacySubmit.borderWidth,
					legacySubmit.borderStyle || 'solid'
				),
			],
			borderHoverStyle: [
				mapBorderStyleObject(
					legacySubmit.borderHover,
					legacySubmit.borderHoverOpacity,
					legacySubmit.borderWidth,
					legacySubmit.borderStyle || 'solid'
				),
			],
			tabletBorderHoverStyle: [
				mapBorderStyleObject(
					legacySubmit.borderHover,
					legacySubmit.borderHoverOpacity,
					legacySubmit.borderWidth,
					legacySubmit.borderStyle || 'solid'
				),
			],
			mobileBorderHoverStyle: [
				mapBorderStyleObject(
					legacySubmit.borderHover,
					legacySubmit.borderHoverOpacity,
					legacySubmit.borderWidth,
					legacySubmit.borderStyle || 'solid'
				),
			],
			borderRadius: normalizeBox(legacySubmit.borderRadius, ''),
			tabletBorderRadius: normalizeBox(legacySubmit.tabletBorderRadius, ''),
			mobileBorderRadius: normalizeBox(legacySubmit.mobileBorderRadius, ''),
			borderRadiusUnit: 'px',
			borderHoverRadius: normalizeBox(legacySubmit.borderRadius, ''),
			tabletBorderHoverRadius: normalizeBox(legacySubmit.tabletBorderRadius, ''),
			mobileBorderHoverRadius: normalizeBox(legacySubmit.mobileBorderRadius, ''),
			borderHoverRadiusUnit: 'px',
			typography: [
				{
					size: normalizeResponsive(legacySubmitFont.size),
					sizeType: legacySubmitFont.sizeType || 'px',
					lineHeight: normalizeResponsive(legacySubmitFont.lineHeight),
					lineType: legacySubmitFont.lineType || 'px',
					letterSpacing: normalizeResponsiveTypographyValue(legacySubmitFont.letterSpacing),
					letterType: 'px',
					textTransform: legacySubmitFont.textTransform || '',
					family: legacySubmitFont.family || '',
					google: legacySubmitFont.google || false,
					style: legacySubmitFont.style || '',
					weight: legacySubmitFont.weight || '',
					variant: legacySubmitFont.variant || '',
					subset: legacySubmitFont.subset || '',
					loadGoogle: legacySubmitFont.loadGoogle !== false,
				},
			],
			displayShadow: !!baseShadow[0],
			shadow: [
				{
					color: baseShadow[1] || '#000000',
					opacity: isFinite(baseShadow[2]) ? baseShadow[2] : 0.2,
					hOffset: isFinite(baseShadow[3]) ? baseShadow[3] : 1,
					vOffset: isFinite(baseShadow[4]) ? baseShadow[4] : 1,
					blur: isFinite(baseShadow[5]) ? baseShadow[5] : 2,
					spread: isFinite(baseShadow[6]) ? baseShadow[6] : 0,
					inset: !!baseShadow[7],
				},
			],
			displayHoverShadow: !!hoverShadow[0],
			shadowHover: [
				{
					color: hoverShadow[1] || '#000000',
					opacity: isFinite(hoverShadow[2]) ? hoverShadow[2] : 0.4,
					hOffset: isFinite(hoverShadow[3]) ? hoverShadow[3] : 2,
					vOffset: isFinite(hoverShadow[4]) ? hoverShadow[4] : 2,
					blur: isFinite(hoverShadow[5]) ? hoverShadow[5] : 3,
					spread: isFinite(hoverShadow[6]) ? hoverShadow[6] : 0,
					inset: !!hoverShadow[7],
				},
			],
			icon: legacySubmit.icon || '',
			iconColor: legacySubmit.iconColor || '',
			iconColorHover: legacySubmit.iconColorHover || '',
			iconSide: legacySubmit.iconSide || 'right',
			iconHover: !!legacySubmit.iconHover,
			iconPadding: normalizeBox(legacySubmit.iconPadding),
			iconPaddingUnit: 'px',
			tabletIconPadding: normalizeBox(legacySubmit.tabletIconPadding),
			mobileIconPadding: normalizeBox(legacySubmit.mobileIconPadding),
			iconSize: normalizeResponsive(legacySubmit.iconSize),
			iconSizeUnit: legacySubmit.iconSizeUnit || 'px',
			onlyIcon: Array.isArray(legacySubmit.onlyIcon) ? legacySubmit.onlyIcon : [false, '', ''],
			inheritStyles: legacySubmit.inheritStyles || 'fill',
			displayShadowHover: !!hoverShadow[0],
			hAlign: attributes.hAlign || 'left',
			thAlign: legacySubmit.align && legacySubmit.align[1] ? legacySubmit.align[1] : '',
			mhAlign: legacySubmit.align && legacySubmit.align[2] ? legacySubmit.align[2] : '',
		},
	};
}

function buildCaptchaBlueprint(attributes) {
	const version = attributes.recaptchaVersion === 'v2' ? 'googlev2' : 'googlev3';
	return {
		blockName: 'flx/advanced-form-captcha',
		attributes: {
			uniqueID: generateUniqueId('captcha'),
			formID: '',
			type: version,
			useKbSettings: true,
			useKcSettings: false,
			hideRecaptcha: false,
			showRecaptchaNotice: false,
			maxWidth: ['', '', ''],
			maxWidthUnit: '%',
			minWidth: ['', '', ''],
			minWidthUnit: 'px',
		},
	};
}

function buildMeta(attributes, fieldBlueprints) {
	const meta = {};
	const actions = Array.isArray(attributes.actions) && attributes.actions.length ? attributes.actions : ['email'];
	const styleSource = attributes.style?.[0] || {};
	const labelFontSource = attributes.labelFont?.[0] || {};
	const messagesSource = attributes.messages?.[0] || {};
	const messageFontSource = attributes.messageFont?.[0] || {};

	meta._flxbk_form_actions = [...new Set(actions)];
	meta._flxbk_form_fields = fieldBlueprints.map((field) => ({
		uniqueID: field.uniqueID,
		name: field.inputName,
		label: field.label,
		type: field.type,
	}));
	meta._flxbk_form_email = buildEmailMeta(attributes.email);
	meta._flxbk_form_redirect = attributes.redirect || '';
	meta._flxbk_form_recaptcha = !!attributes.recaptcha;
	meta._flxbk_form_recaptchaVersion = attributes.recaptchaVersion === 'v2' ? 'v2' : 'v3';
	meta._flxbk_form_browserValidation = true;
	meta._flxbk_form_enableAnalytics = false;
	meta._flxbk_form_submitHide = false;
	meta._flxbk_form_anchor = attributes.anchor || '';
	meta._flxbk_form_className = attributes.className || '';
	meta._flxbk_form_padding = ['', '', '', ''];
	meta._flxbk_form_tabletPadding = ['', '', '', ''];
	meta._flxbk_form_mobilePadding = ['', '', '', ''];
	meta._flxbk_form_paddingUnit = 'px';
	meta._flxbk_form_margin = normalizeBox(attributes.containerMargin);
	meta._flxbk_form_tabletMargin = normalizeBox(attributes.tabletContainerMargin);
	meta._flxbk_form_mobileMargin = normalizeBox(attributes.mobileContainerMargin);
	meta._flxbk_form_marginUnit = attributes.containerMarginType || 'px';
	meta._flxbk_form_maxWidth = ['', '', ''];
	meta._flxbk_form_maxWidthUnit = 'px';

	const styleMeta = buildStyleMeta(styleSource);
	Object.assign(meta, styleMeta);

	meta._flxbk_form_inputFont = buildInputFontMeta(styleSource);
	meta._flxbk_form_labelFont = buildTypographyMeta(labelFontSource, { includeSpacing: true });
	meta._flxbk_form_radioLabelFont = buildTypographyMeta(labelFontSource, { includeSpacing: false });
	const messageMeta = buildMessageMeta(messagesSource, messageFontSource);
	Object.assign(meta, messageMeta);

	const mailerLiteSource = attributes.mailerlite?.[0] || {};
	meta._flxbk_form_mailerlite = buildMailerLiteMeta(mailerLiteSource, fieldBlueprints);

	const fluentCrmSource = attributes.fluentcrm?.[0] || {};
	meta._flxbk_form_fluentcrm = buildFluentCrmMeta(fluentCrmSource, fieldBlueprints);

	const sendinblueSource = attributes.sendinblue?.[0] || {};
	meta._flxbk_form_sendinblue = {
		lists: normalizeOptionArray(sendinblueSource.lists),
		map: convertMapArrayToObject(sendinblueSource.map, fieldBlueprints),
	};

	const mailchimpSource = attributes.mailchimp?.[0] || {};
	meta._flxbk_form_mailchimp = {
		list: normalizeOption(mailchimpSource.list),
		map: convertMapArrayToObject(mailchimpSource.map, fieldBlueprints),
		groups: normalizeOptionArray(mailchimpSource.groups),
		tags: normalizeOptionArray(mailchimpSource.tags),
		doubleOptin: !!mailchimpSource.doubleOptin,
	};

	const convertkitSource = attributes.convertkit?.[0] || {};
	meta._flxbk_form_convertkit = {
		form: normalizeOption(convertkitSource.form, 'number'),
		sequence: normalizeOption(convertkitSource.sequence, 'number'),
		tags: normalizeOptionArray(convertkitSource.tags, 'number'),
		map: convertMapArrayToObject(convertkitSource.map, fieldBlueprints),
	};

	const activeCampaignSource = attributes.activecampaign?.[0] || {};
	meta._flxbk_form_activecampaign = {
		list: normalizeOption(activeCampaignSource.list),
		automation: normalizeOption(activeCampaignSource.automation),
		tags: normalizeOptionArray(activeCampaignSource.tags),
		map: convertMapArrayToObject(activeCampaignSource.map, fieldBlueprints),
		listMulti: normalizeOptionArray(activeCampaignSource.listMulti),
		doubleOptin: !!activeCampaignSource.doubleOptin,
	};

	const getResponseSource = attributes.getresponse?.[0] || {};
	meta._flxbk_form_getresponse = {
		automation: normalizeOption(getResponseSource.automation),
		tags: normalizeOptionArray(getResponseSource.tags),
		map: convertMapArrayToObject(getResponseSource.map, fieldBlueprints),
		listMulti: normalizeOptionArray(getResponseSource.listMulti),
		doubleOptin: !!getResponseSource.doubleOptin,
	};
	meta._flxbk_form_autoEmail = {
		emailTo: '',
		subject: '',
		message: '',
		fromEmail: '',
		fromName: '',
		replyTo: '',
		cc: '',
		bcc: '',
		html: true,
	};
	meta._flxbk_form_entry = { userIP: true, userDevice: true };
	meta._flxbk_form_webhook = { url: '', map: {} };
	meta._flxbk_form_messages = buildMessagesObject(messagesSource);
	meta._flxbk_form_description = '';
	meta._flxbk_form_background = buildBackgroundMeta(styleSource);
	meta._flxbk_form_fieldBorderStyle = buildFieldBorderMeta(styleSource);
	meta._flxbk_form_tabletFieldBorderStyle = buildFieldBorderMeta(styleSource);
	meta._flxbk_form_mobileFieldBorderStyle = buildFieldBorderMeta(styleSource);
	meta._flxbk_form_fieldBorderRadius = buildFieldBorderRadiusMeta(styleSource);
	meta._flxbk_form_tabletFieldBorderRadius = buildFieldBorderRadiusMeta(styleSource);
	meta._flxbk_form_mobileFieldBorderRadius = buildFieldBorderRadiusMeta(styleSource);
	meta._flxbk_form_fieldBorderRadiusUnit = 'px';

	return pruneUndefined(meta);
}

function buildEmailMeta(emailAttr) {
	const source = Array.isArray(emailAttr) && emailAttr[0] ? emailAttr[0] : {};
	return {
		emailTo: source.emailTo || '',
		subject: source.subject || '',
		fromEmail: source.fromEmail || '',
		fromName: source.fromName || '',
		replyTo: source.replyTo || '',
		cc: source.cc || '',
		bcc: source.bcc || '',
		html: source.html !== false,
	};
}

function buildMailerLiteMeta(source, fieldBlueprints) {
	const map = convertMapArrayToObject(source.map, fieldBlueprints);
	return {
		group: normalizeOption(source.group),
		map,
	};
}

function buildFluentCrmMeta(source, fieldBlueprints) {
	const map = convertMapArrayToObject(source.map, fieldBlueprints);
	return {
		lists: normalizeOptionArray(source.lists),
		tags: normalizeOptionArray(source.tags),
		map,
		doubleOptin: !!source.doubleOptin,
	};
}

function buildMessagesObject(source) {
	return {
		success: source.success || '',
		error: source.error || '',
		required: source.required || '',
		invalid: source.invalid || '',
		recaptchaerror: source.recaptchaerror || '',
		preError: source.preError || '',
	};
}

function buildStyleMeta(style) {
	const meta = {};
	meta._flxbk_form_style = {
		showRequired: style.showRequired !== false,
		size: style.size || 'standard',
		padding: normalizeBox(style.deskPadding),
		tabletPadding: normalizeBox(style.tabletPadding),
		mobilePadding: normalizeBox(style.mobilePadding),
		paddingUnit: 'px',
		requiredColor: style.requiredColor ? FlxbkColorOutput(style.requiredColor) : '',
		background: normalizeColorWithOpacity(style.background, style.backgroundOpacity),
		backgroundActive: normalizeColorWithOpacity(style.backgroundActive, style.backgroundActiveOpacity),
		borderActive: normalizeColorWithOpacity(style.borderActive, style.borderActiveOpacity),
		placeholderColor: style.placeholderColor ? FlxbkColorOutput(style.placeholderColor) : '',
		gradient: convertLegacyGradient(style.background, style.backgroundOpacity, style.gradient),
		gradientActive: convertLegacyGradient(
			style.backgroundActive,
			style.backgroundActiveOpacity,
			style.gradientActive
		),
		backgroundType: style.backgroundType || 'normal',
		backgroundActiveType: style.backgroundActiveType || 'normal',
		boxShadow: Array.isArray(style.boxShadow) ? style.boxShadow : [false, '#000000', 0.2, 1, 1, 2, 0, false],
		boxShadowActive: Array.isArray(style.boxShadowActive)
			? style.boxShadowActive
			: [false, '#000000', 0.4, 2, 2, 3, 0, false],
		gap: [getValidNumber(style.rowGap), getValidNumber(style.tabletRowGap), getValidNumber(style.mobileRowGap)],
		gapUnit: style.rowGapType || 'px',
		labelStyle: style.labelStyle || 'normal',
		basicStyles: style.basicStyles !== false,
		isDark: !!style.isDark,
	};
	return meta;
}

function prepareLegacyMigration(attributes) {
	const fieldBlueprints = buildFieldBlueprints(attributes.fields || []);
	const submitBlueprint = buildSubmitBlueprint(attributes);
	const captchaBlueprint = attributes.recaptcha ? buildCaptchaBlueprint(attributes) : null;

	const innerBlocks = [
		...fieldBlueprints.map((item) => createBlock(item.blockName, item.attributes)),
		...(captchaBlueprint ? [createBlock(captchaBlueprint.blockName, captchaBlueprint.attributes)] : []),
		createBlock(submitBlueprint.blockName, submitBlueprint.attributes),
	];

	const rootBlock = createBlock(
		'flx/advanced-form',
		{
			id: 0,
			uniqueID: generateUniqueId('form'),
		},
		innerBlocks
	);

	return {
		meta: buildMeta(attributes, fieldBlueprints),
		title: buildTitle(attributes, fieldBlueprints),
		rootBlock,
	};
}

function buildInputFontMeta(style) {
	return {
		color: style.color ? FlxbkColorOutput(style.color) : '',
		colorActive: style.colorActive ? FlxbkColorOutput(style.colorActive) : '',
		size: normalizeResponsive(style.fontSize),
		sizeType: style.fontSizeType || 'px',
		lineHeight: normalizeResponsive(style.lineHeight),
		lineType: style.lineType || '',
		letterSpacing: normalizeResponsiveTypographyValue(style.letterSpacing),
		letterType: 'px',
		textTransform: style.textTransform || '',
		family: style.family || '',
		google: style.google || false,
		style: style.fontStyle || '',
		weight: style.fontWeight || '',
		variant: style.fontVariant || '',
		subset: style.fontSubset || '',
		loadGoogle: style.loadGoogle !== false,
		padding: normalizeBox(style.fontPadding),
		margin: normalizeBox(style.fontMargin),
	};
}

function buildTypographyMeta(source, { includeSpacing = true } = {}) {
	const typography = {
		color: source.color ? FlxbkColorOutput(source.color) : '',
		size: normalizeResponsive(source.size),
		sizeType: source.sizeType || 'px',
		lineHeight: normalizeResponsive(source.lineHeight),
		lineType: source.lineType || '',
		letterSpacing: normalizeResponsiveTypographyValue(source.letterSpacing),
		letterType: 'px',
		textTransform: source.textTransform || '',
		family: source.family || '',
		google: source.google || false,
		style: source.style || '',
		weight: source.weight || '',
		variant: source.variant || '',
		subset: source.subset || '',
		loadGoogle: source.loadGoogle !== false,
	};
	if (includeSpacing) {
		typography.padding = normalizeBox(source.padding);
		typography.margin = normalizeBox(source.margin);
	}
	return typography;
}

function buildMessageMeta(messageCopy, messageFont) {
	const meta = {};
	meta._flxbk_form_messageColor = messageFont.colorSuccess ? FlxbkColorOutput(messageFont.colorSuccess) : '';
	meta._flxbk_form_messageBackground = normalizeColorWithOpacity(
		messageFont.backgroundSuccess,
		messageFont.backgroundSuccessOpacity
	);
	meta._flxbk_form_messageColorError = messageFont.colorError ? FlxbkColorOutput(messageFont.colorError) : '';
	meta._flxbk_form_messageBackgroundError = normalizeColorWithOpacity(
		messageFont.backgroundError,
		messageFont.backgroundErrorOpacity
	);
	meta._flxbk_form_messagePadding = normalizeBox(messageFont.padding);
	meta._flxbk_form_tableMessagePadding = normalizeBox(messageFont.tabletPadding);
	meta._flxbk_form_mobileMessagePadding = normalizeBox(messageFont.mobilePadding);
	meta._flxbk_form_messagePaddingUnit = 'px';
	meta._flxbk_form_messageMargin = normalizeBox(messageFont.margin);
	meta._flxbk_form_tabletMessageMargin = normalizeBox(messageFont.tabletMargin);
	meta._flxbk_form_mobileMessageMargin = normalizeBox(messageFont.mobileMargin);
	meta._flxbk_form_messageMarginUnit = 'px';
	meta._flxbk_form_messageBorderRadius = normalizeBox(messageFont.borderRadius);
	meta._flxbk_form_tabletMessageBorderRadius = normalizeBox(messageFont.tabletBorderRadius);
	meta._flxbk_form_mobileMessageBorderRadius = normalizeBox(messageFont.mobileBorderRadius);
	meta._flxbk_form_messageBorderRadiusUnit = 'px';
	meta._flxbk_form_messageBorderSuccess = mapBorderStyleObject(
		messageFont.borderSuccess,
		1,
		messageFont.borderWidth,
		'solid'
	);
	meta._flxbk_form_tabletMessageBorderSuccess = mapBorderStyleObject(
		messageFont.borderSuccess,
		1,
		messageFont.borderWidth,
		'solid'
	);
	meta._flxbk_form_mobileMessageBorderSuccess = mapBorderStyleObject(
		messageFont.borderSuccess,
		1,
		messageFont.borderWidth,
		'solid'
	);
	meta._flxbk_form_messageBorderError = mapBorderStyleObject(
		messageFont.borderError,
		1,
		messageFont.borderWidth,
		'solid'
	);
	meta._flxbk_form_tabletMessageBorderError = mapBorderStyleObject(
		messageFont.borderError,
		1,
		messageFont.borderWidth,
		'solid'
	);
	meta._flxbk_form_mobileMessageBorderError = mapBorderStyleObject(
		messageFont.borderError,
		1,
		messageFont.borderWidth,
		'solid'
	);
	meta._flxbk_form_messageFont = [
		{
			size: normalizeResponsive(messageFont.size),
			sizetype: messageFont.sizeType || 'px',
			lineHeight: normalizeResponsive(messageFont.lineHeight),
			lineType: messageFont.lineType || '',
			letterSpacing: normalizeResponsiveTypographyValue(messageFont.letterSpacing),
			letterType: 'px',
			textTransform: messageFont.textTransform || '',
			family: messageFont.family || '',
			google: messageFont.google || false,
			style: messageFont.style || '',
			weight: messageFont.weight || '',
			variant: messageFont.variant || '',
			subset: messageFont.subset || '',
			loadgoogle: messageFont.loadGoogle !== false,
		},
	];
	return meta;
}

function buildBackgroundMeta(style) {
	return {
		background: normalizeColorWithOpacity(style.background, style.backgroundOpacity),
		gradient: convertLegacyGradient(style.background, style.backgroundOpacity, style.gradient),
		backgroundType: style.backgroundType || 'normal',
	};
}

function buildFieldBorderMeta(style) {
	return mapBorderStyleObject(style.border, style.borderOpacity, style.borderWidth, style.borderStyle || 'solid');
}

function buildFieldBorderRadiusMeta(style) {
	const radius =
		style.borderRadius !== undefined && style.borderRadius !== null && style.borderRadius !== ''
			? String(style.borderRadius)
			: '';
	return [radius, radius, radius, radius];
}

function applyFormIdToBlocks(rootBlock, formId) {
	const clone = cloneDeep(rootBlock);
	const stringId = String(formId);

	const assignIds = (block, isRoot = false) => {
		if (block?.attributes) {
			if (isRoot) {
				// standard built advanced form blocks do no get their id attached here, so leaving this off
				// block.attributes.id = numericId;
			} else {
				block.attributes.formID = stringId;
			}
		}

		if (Array.isArray(block.innerBlocks) && block.innerBlocks.length) {
			block.innerBlocks = block.innerBlocks.map((inner) => assignIds(inner, false));
		}

		return block;
	};

	return assignIds(clone, true);
}

function buildTitle(attributes, fieldBlueprints) {
	const primaryLabel = fieldBlueprints.find((field) => field.label)?.label;
	const uniqueHint = attributes.uniqueID ? attributes.uniqueID : generateUniqueId('classic');
	const rawTitle = primaryLabel ? `Migrated Form – ${primaryLabel}` : `Migrated Form – ${uniqueHint}`;
	return rawTitle.length > 60 ? `${rawTitle.slice(0, 57)}…` : rawTitle;
}

function buildOptionList(legacyField, includeSelection) {
	if (!Array.isArray(legacyField.options)) {
		return [{ value: '', label: '', selected: false }];
	}
	const selectedValues = Array.isArray(legacyField.default) ? legacyField.default : [legacyField.default];
	return legacyField.options.map((option, index) => {
		const value = option && option.value !== undefined ? option.value : '';
		const label = option && option.label !== undefined ? option.label : '';
		const selected = includeSelection
			? selectedValues.filter((item) => item !== undefined && item !== null).includes(value)
			: false;
		return {
			value: value === undefined || value === null ? `option-${index}` : value,
			label: label || '',
			selected,
		};
	});
}

function sanitizeInputName(candidate) {
	if (!candidate) {
		return '';
	}
	return (
		kebabCase(candidate)
			.replace(/[^a-z0-9-_]/g, '')
			.replace(/^-+/, '') || ''
	);
}

function ensureUniqueInputName(baseName, usedNames, index) {
	let name = baseName || `field-${index + 1}`;
	let suffix = 1;
	while (usedNames.has(name)) {
		name = `${baseName || `field-${index + 1}`}-${suffix}`;
		suffix++;
	}
	usedNames.add(name);
	return name;
}

function generateUniqueId(prefix) {
	return `${prefix}-${Math.random().toString(36).slice(2, 10)}`;
}

function normalizeResponsive(values, length = 3, fallback = ['', '', '']) {
	const source = Array.isArray(values) ? values : [];
	const result = [];
	for (let i = 0; i < length; i++) {
		const value = source[i];
		result.push(value !== undefined && value !== null && value !== '' ? String(value) : fallback[i] || '');
	}
	return result;
}

function normalizeBox(values, fallback = '') {
	const source = Array.isArray(values) ? values : [];
	const result = [];
	for (let i = 0; i < 4; i++) {
		const value = source[i];
		result.push(value !== undefined && value !== null && value !== '' ? String(value) : fallback || '');
	}
	return result;
}

function normalizeResponsiveTypographyValue(value) {
	if (Array.isArray(value)) {
		return value.map((item) => (item !== undefined && item !== null && item !== '' ? String(item) : ''));
	}
	if (value === undefined || value === null || value === '') {
		return ['', '', ''];
	}
	return [String(value), '', ''];
}

function normalizeColorWithOpacity(color, opacity) {
	if (!color) {
		return '';
	}
	return FlxbkColorOutput(color, opacity !== undefined ? opacity : null);
}

function convertLegacyGradient(baseColor, baseOpacity, gradientArray) {
	if (!Array.isArray(gradientArray) || !gradientArray.length) {
		return '';
	}
	const firstColor =
		normalizeColorWithOpacity(baseColor, baseOpacity !== undefined ? baseOpacity : 1) || 'rgba(0,0,0,0)';
	const secondColor = normalizeColorWithOpacity(gradientArray[0], gradientArray[1]) || firstColor;
	const start = isFinite(gradientArray[2]) ? gradientArray[2] : 0;
	const end = isFinite(gradientArray[3]) ? gradientArray[3] : 100;
	const type = gradientArray[4] || 'linear';
	if (type === 'radial') {
		const position = gradientArray[6] || 'center center';
		return `radial-gradient(at ${position}, ${firstColor} ${start}%, ${secondColor} ${end}%)`;
	}
	const angle = gradientArray[5] !== undefined && gradientArray[5] !== null ? gradientArray[5] : 180;
	return `linear-gradient(${angle}deg, ${firstColor} ${start}%, ${secondColor} ${end}%)`;
}

function getAdvancedBlockName(type) {
	if (!type) {
		return LEGACY_FIELD_TYPE_MAP.text;
	}
	const key = String(type).toLowerCase();
	return LEGACY_FIELD_TYPE_MAP[key] || LEGACY_FIELD_TYPE_MAP.text;
}

function normalizeFieldType(type) {
	if (!type) {
		return 'text';
	}
	const key = String(type).toLowerCase();
	return LEGACY_TYPE_ALIAS[key] || key;
}

function buildAcceptDescriptionWithLink(field) {
	const label = field.label || '';
	const linkText = field.placeholder || label || '';
	const url = field.default || '#';
	return `${label} <a href="${url}" target="_blank" rel="noopener noreferrer">${linkText}</a>`;
}

function getValidNumber(value) {
	if (value === 0 || value === '0') {
		return '0';
	}
	if (value === undefined || value === null || value === '') {
		return '';
	}
	const numeric = Number(value);
	return Number.isFinite(numeric) ? String(numeric) : String(value);
}

function mapBorderStyleObject(color, opacity, widthArray, format = 'solid') {
	const normalizedColor = color ? normalizeColorWithOpacity(color, opacity) : '';
	const widths = Array.isArray(widthArray) ? widthArray : ['', '', '', ''];
	return {
		top: [normalizedColor, format, toBorderWidth(widths[0])],
		right: [normalizedColor, format, toBorderWidth(widths[1])],
		bottom: [normalizedColor, format, toBorderWidth(widths[2])],
		left: [normalizedColor, format, toBorderWidth(widths[3])],
		unit: 'px',
	};
}

function toBorderWidth(value) {
	if (value === undefined || value === null || value === '') {
		return '';
	}
	const numeric = Number(value);
	return Number.isFinite(numeric) ? String(numeric) : String(value);
}

function normalizeOption(option, valueType = 'string') {
	if (option === undefined || option === null || option === '') {
		return {};
	}
	if (typeof option !== 'object') {
		const coercedValue = coerceValue(option, valueType);
		if (coercedValue === undefined) {
			return {};
		}
		return {
			value: coercedValue,
			label: valueType === 'number' ? String(coercedValue) : String(option),
		};
	}
	if (!option) {
		return { value: '', label: '' };
	}
	const coercedValue = coerceValue(option.value, valueType);
	if (coercedValue === undefined) {
		return {};
	}
	return {
		value: coercedValue,
		label: option.label !== undefined && option.label !== null ? String(option.label) : '',
	};
}

function normalizeOptionArray(options, valueType = 'string') {
	if (!Array.isArray(options)) {
		return [];
	}
	return options
		.map((option) => normalizeOption(option, valueType))
		.filter((item) => item.value !== undefined && item.value !== '');
}

function coerceValue(value, valueType) {
	if (value === undefined || value === null || value === '') {
		return undefined;
	}
	if (valueType === 'number') {
		const numeric = Number(value);
		return Number.isFinite(numeric) ? numeric : undefined;
	}
	return String(value);
}

function convertMapArrayToObject(mapArray, fieldBlueprints) {
	const result = {};
	if (!Array.isArray(mapArray)) {
		return result;
	}
	mapArray.forEach((value, index) => {
		if (value === undefined || value === null || value === '') {
			return;
		}
		const field = fieldBlueprints[index];
		if (field) {
			const resolved =
				typeof value === 'object' && value !== null
					? value.value !== undefined && value.value !== null
						? value.value
						: ''
					: value;
			result[field.uniqueID] = resolved !== undefined && resolved !== null ? String(resolved) : '';
		}
	});
	return result;
}

function pruneUndefined(obj) {
	Object.keys(obj).forEach((key) => {
		const value = obj[key];
		if (value === undefined) {
			delete obj[key];
		} else if (value && typeof value === 'object' && !Array.isArray(value)) {
			pruneUndefined(value);
		}
	});
	return obj;
}

export { applyFormIdToBlocks };
