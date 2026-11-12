/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Placeholder, Button, TextControl, ButtonGroup, TextareaControl } from '@wordpress/components';
import { FlxbkRadioButtons } from '@flx-blocks/components';
import { useState } from '@wordpress/element';
import {
	formBlockIcon,
	formTemplateContactIcon,
	formTemplateContactAdvancedIcon,
	formTemplateSubscribeIcon,
	formTemplateSubscribeAdvancedIcon,
	formTemplateContactInFieldIcon,
	formTemplateContactDarkIcon,
	formTemplateContactUnderlineIcon,
	formTemplateContactAdvancedDarkIcon,
	formTemplateContactAdvancedUnderlineIcon,
	formTemplateContactAdvancedInFieldIcon,
	formTemplateSubscribeDarkIcon,
	formTemplateSubscribeUnderlineIcon,
	formTemplateSubscribeInFieldIcon,
	formTemplateSubscribeAdvancedDarkIcon,
	formTemplateSubscribeAdvancedUnderlineIcon,
	formTemplateSubscribeAdvancedInFieldIcon,
} from '@flx-blocks/icons';
import { applyFilters } from '@wordpress/hooks';
import { map } from 'lodash';

export default function FormTitle({ setTitle, isAdding, existingTitle, onAdd }) {
	const [tmpTitle, setTmpTitle] = useState(existingTitle);
	const [initialDescription, setTmpDescription] = useState('');
	const [wizardStep, setWizardStep] = useState('start');
	const [template, setTemplate] = useState('');
	const [style, setStyle] = useState('basic');
	const [isSaving, setIsSaving] = useState(false);

	const formSteps = [
		{ key: 'start', name: __('Layout', 'flx-blocks') },
		{ key: 'style', name: __('Style', 'flx-blocks') },
		{ key: 'title', name: __('Title', 'flx-blocks') },
	];
	const startLayoutOptions = [
		{ key: 'skip', name: __('Skip (blank)', 'flx-blocks'), icon: '', isDisabled: false },
		{ key: 'contact', name: __('Contact', 'flx-blocks'), icon: formTemplateContactIcon, isDisabled: false },
		{
			key: 'contactAdvanced',
			name: __('Contact With Options', 'flx-blocks'),
			icon: formTemplateContactAdvancedIcon,
			isDisabled: false,
		},
		{
			key: 'subscribe',
			name: __('Subscribe', 'flx-blocks'),
			icon: formTemplateSubscribeIcon,
			isDisabled: false,
		},
		{
			key: 'subscribeAdvanced',
			name: __('Subscribe With Name', 'flx-blocks'),
			icon: formTemplateSubscribeAdvancedIcon,
			isDisabled: false,
		},
	];
	let styleIcons = {
		skip: formTemplateContactIcon,
		dark: formTemplateContactDarkIcon,
		infield: formTemplateContactInFieldIcon,
		underline: formTemplateContactUnderlineIcon,
	};
	if ('subscribe' === template) {
		styleIcons = {
			skip: formTemplateSubscribeIcon,
			dark: formTemplateSubscribeDarkIcon,
			infield: formTemplateSubscribeInFieldIcon,
			underline: formTemplateSubscribeUnderlineIcon,
		};
	} else if ('contactAdvanced' === template) {
		styleIcons = {
			skip: formTemplateContactAdvancedIcon,
			dark: formTemplateContactAdvancedDarkIcon,
			infield: formTemplateContactAdvancedInFieldIcon,
			underline: formTemplateContactAdvancedUnderlineIcon,
		};
	} else if ('subscribeAdvanced' === template) {
		styleIcons = {
			skip: formTemplateSubscribeAdvancedIcon,
			dark: formTemplateSubscribeAdvancedDarkIcon,
			infield: formTemplateSubscribeAdvancedInFieldIcon,
			underline: formTemplateSubscribeAdvancedUnderlineIcon,
		};
	}
	const startStyleOptions = [
		{ key: 'skip', name: __('Basic'), icon: styleIcons.skip, isDisabled: false },
		{ key: 'dark', name: __('Dark', 'flx-blocks'), icon: styleIcons.dark, isDisabled: false },
		{ key: 'infield', name: __('Infield', 'flx-blocks'), icon: styleIcons.infield, isDisabled: false },
		{ key: 'underline', name: __('Underline', 'flx-blocks'), icon: styleIcons.underline, isDisabled: false },
	];
	const formTemplates = applyFilters('flxbk.formTemplates', startLayoutOptions);
	const formStyles = applyFilters('flxbk.formStyles', startStyleOptions);
	return (
		<Placeholder
			className="fxb-select-or-create-placeholder fxb-adv-form-select"
			icon={formBlockIcon}
			label={__('Flux Form', 'flx-blocks')}
		>
			<div className="fxb-form-wizard-pagination">
				{map(formSteps, ({ name, key }, index) => (
					<Button
						key={key}
						className="fxb-form-pagination-btn"
						isSmall
						onClick={() => {
							if (key === 'style' && (template === 'skip' || template === '')) {
								setWizardStep('start');
							} else {
								setWizardStep(key);
							}
						}}
						icon={<span className="fxb-form-pagination-icon">{index + 1}</span>}
						aria-label={sprintf(
							/* translators: 1: current page number 2: total number of pages */
							__('Page %1$d of %2$d', 'flx-blocks'),
							index + 1,
							formSteps.length
						)}
						text={name}
						isPressed={wizardStep == key}
					/>
				))}
			</div>
			<div className="fxb-select-or-create-placeholder__actions">
				{wizardStep === 'start' && (
					<div className="fxt-select-starter-style-forms">
						<div className="fxt-select-starter-style-forms-title">
							{__('Select Initial Layout', 'flx-blocks')}
						</div>
						<ButtonGroup
							className="fxt-init-forms-btn-group"
							aria-label={__('Initial Layout', 'flx-blocks')}
						>
							{map(formTemplates, ({ name, key, icon, isDisabled }) => (
								<Button
									key={key}
									className="fxt-inital-form-style-btn"
									isSmall
									onClick={() => {
										setTemplate(key);
										if (tmpTitle === '') {
											'skip' === key
												? setTmpTitle(__('Blank Form', 'flx-blocks'))
												: setTmpTitle(name + __('Form', 'flx-blocks'));
										}
										if ('skip' === key) {
											setWizardStep('title');
										} else {
											setWizardStep('style');
										}
									}}
									isPressed={template == key}
									label={name}
									isDisabled={isDisabled}
								>
									{name}
									{icon}
									{key !== 'skip' && (
										<span className="template-select">{__('Select', 'flx-blocks')}</span>
									)}
								</Button>
							))}
						</ButtonGroup>
					</div>
				)}
				{wizardStep === 'style' && (
					<>
						<div className="fxt-select-starter-style-forms fxt-select-starter-styles-only">
							<div className="fxt-select-starter-style-forms-title">
								{__('Select Initial Style', 'flx-blocks')}
							</div>
							<ButtonGroup
								className="fxt-init-forms-btn-group style-only"
								aria-label={__('Initial Style', 'flx-blocks')}
							>
								{map(formStyles, ({ name, key, icon, isDisabled }) => (
									<Button
										key={key}
										className="fxt-inital-form-style-btn"
										isSmall
										onClick={() => {
											setStyle(key);
											setWizardStep('title');
										}}
										isPressed={style == key}
										isDisabled={isDisabled}
										label={name}
									>
										{name}
										{icon}
										<span className="template-select">{__('Select', 'flx-blocks')}</span>
									</Button>
								))}
							</ButtonGroup>
						</div>
					</>
				)}
				{wizardStep === 'title' && (
					<>
						<TextControl
							className={`fxb-form-block-title${!tmpTitle ? ' flxbk-input-required-warning' : ''}`}
							label={__('Give your form a title (required)', 'flx-blocks')}
							placeholder={__('Contact Us', 'flx-blocks')}
							help={__('This is used for your reference only.', 'flx-blocks')}
							value={tmpTitle}
							onChange={setTmpTitle}
							autoFocus
						/>
						<TextareaControl
							label={__('Form Description', 'flx-blocks')}
							placeholder={__('Optionally add an description about your form', 'flx-blocks')}
							help={__('This is used for your reference only.', 'flx-blocks')}
							value={initialDescription}
							onChange={setTmpDescription}
						/>
						<Button
							variant="primary"
							onClick={() => {
								setIsSaving(true);
								onAdd(tmpTitle, template, style, initialDescription);
							}}
							isBusy={isAdding}
							disabled={tmpTitle === '' || isSaving}
						>
							{__('Create', 'flx-blocks')}
						</Button>
					</>
				)}
			</div>
		</Placeholder>
	);
}
