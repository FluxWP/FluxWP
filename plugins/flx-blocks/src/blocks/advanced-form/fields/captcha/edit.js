/**
 * WordPress dependencies
 */
import {
	TextControl,
	SelectControl,
	ToggleControl,
	Button,
	TextareaControl,
	Spinner,
	ExternalLink,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps, store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	FlxbkPanelBody,
	InspectorControlTabs,
	ResponsiveRangeControls,
	SelectParentBlock,
} from '@flx-blocks/components';
import { useEffect, useState } from '@wordpress/element';
import { getPreviewSize } from '@flx-blocks/helpers';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import classNames from 'classnames';
import { FieldBlockAppender, getUniqueFieldId } from '../../components';
import { get } from 'lodash';
import ReCAPTCHA from 'react-google-recaptcha';
import Turnstile from 'react-turnstile';
import HCaptcha from '@hcaptcha/react-hcaptcha';

/**
 * Import Css
 */
import './editor.scss';

function FieldCaptcha(props) {
	const { attributes, setAttributes, isSelected, clientId, context, name } = props;
	//usekcsettings is to use settings from the seperate flxbk captcha plugin
	//usekbsettings is to use the global flxbk blocks settings for captions
	const {
		uniqueID,
		hideRecaptcha,
		type,
		useKcSettings,
		useKbSettings,
		showRecaptchaNotice,
		recaptchaNotice,
		theme,
		size,
		maxWidth,
		minWidth,
		maxWidthUnit,
		minWidthUnit,
		requiredMessage,
		recaptchaSiteKey,
		recaptchaSecretKey,
		recaptchaLanguage,
		hCaptchaSiteKey,
		hCaptchaSecretKey,
		turnstileSiteKey,
		turnstileSecretKey,
	} = attributes;
	const [activeTab, setActiveTab] = useState('general');
	const { previewDevice } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
			};
		},
		[clientId]
	);

	useEffect(() => {
		// Doesn't worry about if a filed is duplicated. Duplicated fields get a custom ID through the watch at the form level.
		const uniqueId = getUniqueFieldId(uniqueID, clientId);
		if (uniqueId !== uniqueID) {
			attributes.uniqueID = uniqueId;
			setAttributes({ uniqueID: uniqueId });
		}
	}, []);

	useEffect(() => {
		const neededFields = [
			'flxbk_recaptcha_site_key',
			'flxbk_recaptcha_secret_key',
			'flxbk_recaptcha_language',
			'flxbk_hcaptcha_site_key',
			'flxbk_hcaptcha_secret_key',
			'flxbk_turnstile_site_key',
			'flxbk_turnstile_secret_key',
			'fxt_recaptcha',
		];

		/**
		 * Get settings
		 */
		apiFetch({
			path: addQueryArgs('/wp/v2/settings', { _fields: neededFields }),
			method: 'GET',
		}).then((response) => {
			setRecaptchaSiteKeyState(response.flxbk_recaptcha_site_key);
			setRecaptchaSecretKeyState(response.flxbk_recaptcha_secret_key);
			setRecaptchaLanguageState(response.flxbk_recaptcha_language);

			setHCaptchaSiteKeyState(response.flxbk_hcaptcha_site_key);
			setHCaptchaSecretKeyState(response.flxbk_hcaptcha_secret_key);

			setTurnstileSiteKeyState(response.flxbk_turnstile_site_key);
			setTurnstileSecretKeyState(response.flxbk_turnstile_secret_key);

			try {
				setFlxbkRecaptha(JSON.parse(get(response, 'fxt_recaptcha', {})));
			} catch (e) {
				setFlxbkRecaptha({});
			}

			setSettingsLoaded(true);
		});
	}, [type]);

	const previewMaxWidth = getPreviewSize(
		previewDevice,
		maxWidth && maxWidth[0] ? maxWidth[0] : '',
		maxWidth && maxWidth[1] ? maxWidth[1] : '',
		maxWidth && maxWidth[2] ? maxWidth[2] : ''
	);
	const previewMinWidth = getPreviewSize(
		previewDevice,
		minWidth && minWidth[0] ? minWidth[0] : '',
		minWidth && minWidth[1] ? minWidth[1] : '',
		minWidth && minWidth[2] ? minWidth[2] : ''
	);

	const hasFlxbkCaptcha = flxbk_params.hasFlxbkCaptcha !== '';

	const classes = classNames({
		'fxb-adv-form-field': true,
	});
	const blockProps = useBlockProps({
		className: classes,
		style: {
			maxWidth: '' !== previewMaxWidth ? previewMaxWidth + (maxWidthUnit ? maxWidthUnit : '%') : undefined,
			minWidth: '' !== previewMinWidth ? previewMinWidth + (minWidthUnit ? minWidthUnit : 'px') : undefined,
		},
	});

	const [settingsLoaded, setSettingsLoaded] = useState(false);
	const [recaptchaSiteKeyState, setRecaptchaSiteKeyState] = useState('-');
	const [recaptchaSecretKeyState, setRecaptchaSecretKeyState] = useState('');
	const [recaptchaLanguageState, setRecaptchaLanguageState] = useState('');
	const [hCaptchaSiteKeyState, setHCaptchaSiteKeyState] = useState('-');
	const [hCaptchaSecretKeyState, setHCaptchaSecretKeyState] = useState('');
	const [turnstileSiteKeyState, setTurnstileSiteKeyState] = useState('-');
	const [turnstileSecretKeyState, setTurnstileSecretKeyState] = useState('');

	const [flxbkRecaptha, setFlxbkRecaptha] = useState(false);
	const [isSaving, setIsSaving] = useState(false);

	const allowedTabs = ['general', 'style', 'advanced'];
	const linkToFlxbkCaptchaSettings = (
		<a
			href={flxbk_params.adminUrl + 'options-general.php?page=flxbk-recaptcha-settings'}
			target={'_blank'}
			rel="noreferrer"
		>
			{__('Modify Flux Captcha settings', 'flx-blocks')}
		</a>
	);

	const kcKeyToType = (key) => {
		const types = ['googlev2', 'googlev3', 'turnstile'];

		return types[key];
	};

	const previewType = useKcSettings ? kcKeyToType(get(flxbkRecaptha, 'enable_v3', 0)) : type;
	const previewRecaptchaSiteKey = useKcSettings
		? get(flxbkRecaptha, 'v3_re_site_key', '-')
		: useKbSettings
			? recaptchaSiteKeyState
			: recaptchaSiteKey;
	const previewRecaptchaSecretKey = useKbSettings ? recaptchaSecretKeyState : recaptchaSecretKey;
	const previewRecaptchaLanguage = useKbSettings ? recaptchaLanguageState : recaptchaLanguage;
	const previewTurnstileSiteKey = useKcSettings
		? get(flxbkRecaptha, 'v3_re_site_key', '-')
		: useKbSettings
			? turnstileSiteKeyState
			: turnstileSiteKey;
	const previewTurnstileSecretKey = useKbSettings ? turnstileSecretKeyState : turnstileSecretKey;
	const previewhCaptchaSiteKey = useKcSettings
		? get(flxbkRecaptha, 'v3_re_site_key', '-')
		: useKbSettings
			? hCaptchaSiteKeyState
			: hCaptchaSiteKey;
	const previewhCaptchaSecretKey = useKbSettings ? hCaptchaSecretKeyState : hCaptchaSecretKey;
	const previewTheme = useKcSettings ? get(flxbkRecaptha, 'fxt_re_theme', 'light') : theme;
	const previewSize = useKcSettings ? get(flxbkRecaptha, 'fxt_re_size', 'normal') : size;
	const previewHide = useKcSettings ? get(flxbkRecaptha, 'hide_v3_badge', false) : hideRecaptcha;
	const previewShowNotice = useKcSettings ? get(flxbkRecaptha, 'show_v3_notice', true) : showRecaptchaNotice;
	const previewNotice = useKcSettings ? get(flxbkRecaptha, 'v3_notice', '') : recaptchaNotice;
	const googlev3HiddenNotice =
		previewNotice !== ''
			? previewNotice
			: __(
					'This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply',
					'flx-blocks'
				);

	const googleV2RerenderKey = theme + size + previewRecaptchaLanguage + previewRecaptchaSiteKey + recaptchaSecretKey;

	const saveCaptchaSetting = () => {
		setIsSaving(true);

		if (type === 'turnstile') {
			const settingModel = new wp.api.models.Settings({
				flxbk_turnstile_site_key: turnstileSiteKeyState,
				flxbk_turnstile_secret_key: turnstileSecretKeyState,
				flxbk_recaptcha_language: recaptchaLanguageState,
			});

			settingModel.save().then((response) => {
				setIsSaving(false);
			});
		} else if (type === 'googlev2' || type === 'googlev3') {
			const settingModel = new wp.api.models.Settings({
				flxbk_recaptcha_site_key: recaptchaSiteKeyState,
				flxbk_recaptcha_secret_key: recaptchaSecretKeyState,
				flxbk_recaptcha_language: recaptchaLanguageState,
			});

			settingModel.save().then((response) => {
				setIsSaving(false);
			});
		} else if (type === 'hcaptcha') {
			const settingModel = new wp.api.models.Settings({
				flxbk_hcaptcha_site_key: hCaptchaSiteKeyState,
				flxbk_hcaptcha_secret_key: hCaptchaSecretKeyState,
				flxbk_recaptcha_language: recaptchaLanguageState,
			});

			settingModel.save().then((response) => {
				setIsSaving(false);
			});
		}
	};

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
			<div {...blockProps}>
				<InspectorControls>
					<SelectParentBlock
						label={__('View Form Settings', 'flx-blocks')}
						clientId={clientId}
						parentSlug={'flx/advanced-form'}
					/>
					<InspectorControlTabs
						panelName={'advanced-form-date-general'}
						setActiveTab={(value) => setActiveTab(value)}
						activeTab={activeTab}
						allowedTabs={allowedTabs}
					/>
					{activeTab === 'general' && (
						<>
							<FlxbkPanelBody initialOpen={true} panelName={'fxb-adv-form-captcha-controls'}>
								{hasFlxbkCaptcha && (
									<ToggleControl
										label={__('Use Flux Captcha Settings', 'flx-blocks')}
										checked={useKcSettings}
										onChange={(value) => setAttributes({ useKcSettings: value })}
										help={__('Use settings from Flux Captcha Plugin', 'flx-blocks')}
									/>
								)}

								{hasFlxbkCaptcha && useKcSettings ? (
									linkToFlxbkCaptchaSettings
								) : (
									<>
										<SelectControl
											label={__('Captcha Type', 'flx-blocks')}
											value={type}
											options={[
												{ value: 'googlev2', label: __('Google V2', 'flx-blocks') },
												{ value: 'googlev3', label: __('Google V3', 'flx-blocks') },
												{ value: 'turnstile', label: __('Turnstile', 'flx-blocks') },
												{ value: 'hcaptcha', label: __('hCaptcha', 'flx-blocks') },
											]}
											onChange={(value) => {
												setAttributes({ type: value });
											}}
										/>

										{type === 'turnstile' && (
											<>
												<TextControl
													label={__('Turnstile Site Key', 'flx-blocks')}
													value={previewTurnstileSiteKey}
													onChange={(value) =>
														useKbSettings
															? setTurnstileSiteKeyState(value)
															: setAttributes({ turnstileSiteKey: value })
													}
												/>

												<TextControl
													label={__('Turnstile Secret Key', 'flx-blocks')}
													value={previewTurnstileSecretKey}
													onChange={(value) =>
														useKbSettings
															? setTurnstileSecretKeyState(value)
															: setAttributes({ turnstileSecretKey: value })
													}
												/>

												{useKbSettings && (
													<div className="components-base-control">
														<Button
															isPrimary
															onClick={() => saveCaptchaSetting()}
															disabled={
																'' === previewTurnstileSiteKey ||
																'' === previewTurnstileSecretKey
															}
														>
															{isSaving
																? __('Saving', 'flx-blocks')
																: __('Save', 'flx-blocks')}
														</Button>
													</div>
												)}
											</>
										)}

										{(type === 'googlev2' || type === 'googlev3') && (
											<>
												<TextControl
													label={__('Recaptcha Site Key', 'flx-blocks')}
													value={previewRecaptchaSiteKey}
													onChange={(value) =>
														useKbSettings
															? setRecaptchaSiteKeyState(value)
															: setAttributes({ recaptchaSiteKey: value })
													}
												/>

												<TextControl
													label={__('Recaptcha Secret Key', 'flx-blocks')}
													value={previewRecaptchaSecretKey}
													onChange={(value) =>
														useKbSettings
															? setRecaptchaSecretKeyState(value)
															: setAttributes({ recaptchaSecretKey: value })
													}
												/>

												<TextControl
													label={__('Force Specific Language', 'flx-blocks')}
													value={previewRecaptchaLanguage}
													onChange={(value) =>
														useKbSettings
															? setRecaptchaLanguageState(value)
															: setAttributes({ recaptchaLanguage: value })
													}
												/>
												<p className="fxb-small-help">
													<ExternalLink
														href={'https://developers.google.com/recaptcha/docs/language/'}
													>
														{__('View language codes here', 'flx-blocks')}
													</ExternalLink>
												</p>

												{useKbSettings && (
													<div className="components-base-control">
														<Button
															isPrimary
															onClick={() => saveCaptchaSetting()}
															disabled={
																'' === previewRecaptchaSiteKey ||
																'' === previewRecaptchaSecretKey
															}
														>
															{isSaving
																? __('Saving', 'flx-blocks')
																: __('Save', 'flx-blocks')}
														</Button>
													</div>
												)}
											</>
										)}

										{type === 'hcaptcha' && (
											<>
												<TextControl
													label={__('hCaptcha Site Key', 'flx-blocks')}
													value={previewhCaptchaSiteKey}
													onChange={(value) =>
														useKbSettings
															? setHCaptchaSiteKeyState(value)
															: setAttributes({ hCaptchaSiteKey: value })
													}
												/>

												<TextControl
													label={__('hCaptcha Secret Key', 'flx-blocks')}
													value={previewhCaptchaSecretKey}
													onChange={(value) =>
														useKbSettings
															? setHCaptchaSecretKeyState(value)
															: setAttributes({ hCaptchaSecretKey: value })
													}
												/>

												<TextControl
													label={__('Force Specific Language', 'flx-blocks')}
													value={previewRecaptchaLanguage}
													onChange={(value) =>
														useKbSettings
															? setRecaptchaLanguageState(value)
															: setAttributes({ recaptchaLanguage: value })
													}
												/>

												{useKbSettings && (
													<div className="components-base-control">
														<Button
															isPrimary
															onClick={() => saveCaptchaSetting()}
															disabled={
																'' === previewhCaptchaSiteKey ||
																'' === previewhCaptchaSecretKey
															}
														>
															{isSaving
																? __('Saving', 'flx-blocks')
																: __('Save', 'flx-blocks')}
														</Button>
													</div>
												)}
											</>
										)}
										<ToggleControl
											label={__('Use Global Flux Settings', 'flx-blocks')}
											checked={useKbSettings}
											onChange={(value) => setAttributes({ useKbSettings: value })}
											help={__(
												'Use settings shared for all Flux Form Captchas',
												'flx-blocks'
											)}
										/>
									</>
								)}
							</FlxbkPanelBody>
						</>
					)}
					{activeTab === 'style' && (
						<FlxbkPanelBody>
							{type === 'googlev3' && useKcSettings === false && (
								<>
									<ToggleControl
										label={__('Hide reCAPTCHA badge', 'flx-blocks')}
										checked={hideRecaptcha}
										onChange={(value) => setAttributes({ hideRecaptcha: value })}
										help={__(
											'Hiding requires that information about recaptcha be added to your form',
											'flx-blocks'
										)}
									/>

									{hideRecaptcha && (
										<>
											<ToggleControl
												label={__('Add reCAPTCHA notice info to form', 'flx-blocks')}
												checked={showRecaptchaNotice}
												onChange={(value) => setAttributes({ showRecaptchaNotice: value })}
												help={__(
													'This will add the required reCAPTCHA version 3 informtion to your form.',
													'flx-blocks'
												)}
											/>

											{showRecaptchaNotice && (
												<TextareaControl
													label={__('Optional - Custom reCaptcha notice', 'flx-blocks')}
													value={recaptchaNotice}
													onChange={(value) => setAttributes({ recaptchaNotice: value })}
													help={__(
														'If left empty then "This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply" is used.',
														'flx-blocks'
													)}
												/>
											)}
										</>
									)}
								</>
							)}
							{useKcSettings === true && linkToFlxbkCaptchaSettings}
							{useKcSettings === false && type !== 'googlev3' && (
								<SelectControl
									label={__('Color Theme', 'flx-blocks')}
									value={theme}
									options={[
										{ label: __('Light', 'flx-blocks'), value: 'light' },
										{ label: __('Dark', 'flx-blocks'), value: 'dark' },
									]}
									onChange={(value) => setAttributes({ theme: value })}
								/>
							)}

							{type !== 'googlev3' && (
								<SelectControl
									label={__('Size', 'flx-blocks')}
									options={[
										{ label: __('Normal', 'flx-blocks'), value: 'normal' },
										{ label: __('Compact', 'flx-blocks'), value: 'compact' },
									]}
									value={size}
									onChange={(value) => setAttributes({ size: value })}
								/>
							)}
						</FlxbkPanelBody>
					)}
					{activeTab === 'advanced' && (
						<>
							<FlxbkPanelBody
								title={__('Field Width', 'flx-blocks')}
								initialOpen={true}
								panelName={'fxb-adv-form-date-width'}
							>
								<ResponsiveRangeControls
									label={__('Max Width', 'flx-blocks')}
									value={undefined !== maxWidth && undefined !== maxWidth[0] ? maxWidth[0] : ''}
									onChange={(value) => {
										setAttributes({
											maxWidth: [
												value,
												undefined !== maxWidth && undefined !== maxWidth[1] ? maxWidth[1] : '',
												undefined !== maxWidth && undefined !== maxWidth[2] ? maxWidth[2] : '',
											],
										});
									}}
									tabletValue={undefined !== maxWidth && undefined !== maxWidth[1] ? maxWidth[1] : ''}
									onChangeTablet={(value) => {
										setAttributes({
											maxWidth: [
												undefined !== maxWidth && undefined !== maxWidth[0] ? maxWidth[0] : '',
												value,
												undefined !== maxWidth && undefined !== maxWidth[2] ? maxWidth[2] : '',
											],
										});
									}}
									mobileValue={undefined !== maxWidth && undefined !== maxWidth[2] ? maxWidth[2] : ''}
									onChangeMobile={(value) => {
										setAttributes({
											maxWidth: [
												undefined !== maxWidth && undefined !== maxWidth[0] ? maxWidth[0] : '',
												undefined !== maxWidth && undefined !== maxWidth[1] ? maxWidth[1] : '',
												value,
											],
										});
									}}
									min={0}
									max={maxWidthUnit === 'px' ? 2000 : 100}
									step={1}
									unit={maxWidthUnit ? maxWidthUnit : '%'}
									onUnit={(value) => {
										setAttributes({ maxWidthUnit: value });
									}}
									units={['px', '%', 'vw']}
								/>
								<ResponsiveRangeControls
									label={__('Min Width', 'flx-blocks')}
									value={undefined !== minWidth && undefined !== minWidth[0] ? minWidth[0] : ''}
									onChange={(value) => {
										setAttributes({
											minWidth: [
												value,
												undefined !== minWidth && undefined !== minWidth[1] ? minWidth[1] : '',
												undefined !== minWidth && undefined !== minWidth[2] ? minWidth[2] : '',
											],
										});
									}}
									tabletValue={undefined !== minWidth && undefined !== minWidth[1] ? minWidth[1] : ''}
									onChangeTablet={(value) => {
										setAttributes({
											minWidth: [
												undefined !== minWidth && undefined !== minWidth[0] ? minWidth[0] : '',
												value,
												undefined !== minWidth && undefined !== minWidth[2] ? minWidth[2] : '',
											],
										});
									}}
									mobileValue={undefined !== minWidth && undefined !== minWidth[2] ? minWidth[2] : ''}
									onChangeMobile={(value) => {
										setAttributes({
											minWidth: [
												undefined !== minWidth && undefined !== minWidth[0] ? minWidth[0] : '',
												undefined !== minWidth && undefined !== minWidth[1] ? minWidth[1] : '',
												value,
											],
										});
									}}
									min={0}
									max={minWidthUnit === 'px' ? 2000 : 100}
									step={1}
									unit={minWidthUnit ? minWidthUnit : 'px'}
									onUnit={(value) => {
										setAttributes({ minWidthUnit: value });
									}}
									units={['px', '%', 'vw']}
								/>
							</FlxbkPanelBody>
						</>
					)}
				</InspectorControls>
				<div style={{ minHeight: type !== 'googlev3' ? '74px' : undefined }}>
					{!useKbSettings || settingsLoaded ? (
						<>
							{previewType === 'hcaptcha' && (
								<>
									{previewhCaptchaSiteKey && (
										<HCaptcha
											reCaptchaCompat={false}
											theme={previewTheme}
											size={previewSize}
											sitekey={previewhCaptchaSiteKey}
											onVerify={(token, ekey) => {
												return null;
											}}
										/>
									)}
									{!previewhCaptchaSiteKey && (
										<div
											className={
												'preview-captcha  preview-captcha-' +
												previewSize +
												' preview-captcha-' +
												previewTheme
											}
										>
											{__('Please Add API Key', 'flx-blocks')}
										</div>
									)}
								</>
							)}

							{previewType === 'googlev2' && (
								<>
									{previewRecaptchaSiteKey && googleV2RerenderKey && (
										<ReCAPTCHA
											key={googleV2RerenderKey}
											sitekey={previewRecaptchaSiteKey}
											theme={previewTheme}
											hl={previewRecaptchaLanguage}
											size={previewSize}
											onChange={() => {
												return null;
											}}
										/>
									)}
									{(!previewRecaptchaSiteKey || !googleV2RerenderKey) && (
										<div
											className={
												'preview-captcha  preview-captcha-' +
												previewSize +
												' preview-captcha-' +
												previewTheme
											}
										>
											{__('Please Add API Keys', 'flx-blocks')}
										</div>
									)}
								</>
							)}

							{previewType === 'googlev3' && (
								<>
									{previewHide === false && <em>{__('Google V3 reCAPTCHA', 'flx-blocks')}</em>}
									{previewHide && !previewShowNotice && (
										<em>{__('Placeholder for hidden Google V3 reCaptcha', 'flx-blocks')}</em>
									)}
									{previewHide && previewShowNotice && (
										<span
											style={{
												fontSize: '11px',
												color: '#555',
												lineHeight: '1.2',
												display: 'block',
												marginBottom: '16px',
												// maxWidth: '400px',
												padding: '10px',
												background: '#f2f2f2',
											}}
											className={'fxt-recaptcha-branding-string'}
										>
											{googlev3HiddenNotice}
										</span>
									)}
								</>
							)}

							{previewType === 'turnstile' && (
								<>
									{previewTurnstileSiteKey && (
										<Turnstile
											theme={previewTheme}
											size={previewSize}
											sitekey={previewTurnstileSiteKey}
											onVerify={(token) => {
												return null;
											}}
										/>
									)}
									{!previewTurnstileSiteKey && (
										<div
											className={
												'preview-captcha  preview-captcha-' +
												previewSize +
												' preview-captcha-' +
												previewTheme
											}
										>
											{__('Please Add API Key', 'flx-blocks')}
										</div>
									)}
								</>
							)}
						</>
					) : (
						<div
							className={
								'preview-captcha  preview-captcha-' + previewSize + ' preview-captcha-' + previewTheme
							}
						>
							{__('Loading Captcha Settings', 'flx-blocks')}
							<Spinner />
						</div>
					)}
				</div>
				<FieldBlockAppender inline={true} className="fxb-custom-inbetween-inserter" getRoot={clientId} />
			</div>
		</>
	);
}

export default FieldCaptcha;
