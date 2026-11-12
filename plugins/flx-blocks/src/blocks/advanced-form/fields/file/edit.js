/**
 * Internal dependencies
 */
import FormFieldLabel from '../../label';

/**
 * WordPress dependencies
 */
import { TextControl, TextareaControl, ToggleControl, CheckboxControl, RangeControl } from '@wordpress/components';
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
import { without } from 'lodash';
import { getPreviewSize } from '@flx-blocks/helpers';
import classNames from 'classnames';
import { DuplicateField, FieldBlockAppender, FieldName, getUniqueFieldId } from '../../components';

function FieldFile(props) {
	const { attributes, setAttributes, isSelected, clientId, context, name } = props;
	const {
		uniqueID,
		required,
		label,
		showLabel,
		maxSizeMb,
		allowedTypes,
		helpText,
		ariaDescription,
		maxWidth,
		maxWidthUnit,
		minWidth,
		minWidthUnit,
		inputName,
		requiredMessage,
		multiple,
		multipleLimit,
	} = attributes;

	const wpMaxUploadSizeBytes = flxbk_params.wp_max_upload_size;
	const wpMaxUploadSizeMb = formatBytesToMb(wpMaxUploadSizeBytes);
	const wpMaxUploadSizePretty = formatBytes(wpMaxUploadSizeBytes);

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
		if (maxSizeMb > wpMaxUploadSizeMb) {
			setAttributes({ maxSizeMb: wpMaxUploadSizeMb });
		}
	}, [wpMaxUploadSizeMb]);
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
	const classes = classNames({
		'fxb-adv-form-field': true,
		'fxb-adv-form-infield-type-input': true,
	});
	const blockProps = useBlockProps({
		className: classes,
		style: {
			maxWidth: '' !== previewMaxWidth ? previewMaxWidth + (maxWidthUnit ? maxWidthUnit : '%') : undefined,
			minWidth: '' !== previewMinWidth ? previewMinWidth + (minWidthUnit ? minWidthUnit : 'px') : undefined,
		},
	});

	const toggleAllowedTypes = (type) => {
		let newTypes = [];

		if (allowedTypes.includes(type)) {
			newTypes = without(allowedTypes, type);
		} else {
			newTypes = [...allowedTypes, type];
		}

		setAttributes({ allowedTypes: newTypes });
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
				<DuplicateField clientId={clientId} name={name} attributes={attributes} />
				<InspectorControls>
					<SelectParentBlock
						label={__('View Form Settings', 'flx-blocks')}
						clientId={clientId}
						parentSlug={'flx/advanced-form'}
					/>
					<InspectorControlTabs
						panelName={'advanced-form-file'}
						setActiveTab={setActiveTab}
						activeTab={activeTab}
						allowedTabs={['general', 'advanced']}
					/>

					{activeTab === 'general' && (
						<>
							<FlxbkPanelBody
								title={__('Field Controls', 'flx-blocks')}
								initialOpen={true}
								panelName={'fxb-adv-form-text-controls'}
							>
								<ToggleControl
									label={__('Required', 'flx-blocks')}
									checked={required}
									onChange={(value) => setAttributes({ required: value })}
								/>
								<TextControl
									label={__('Field Label', 'flx-blocks')}
									value={label}
									onChange={(value) => setAttributes({ label: value })}
								/>
								<ToggleControl
									label={__('Show Label', 'flx-blocks')}
									checked={showLabel}
									onChange={(value) => setAttributes({ showLabel: value })}
								/>
								<TextareaControl
									label={__('Description', 'flx-blocks')}
									help={__(
										'This will be displayed under the input and can be used to provide direction on how the field should be filled out.',
										'flx-blocks'
									)}
									value={helpText}
									onChange={(value) => setAttributes({ helpText: value })}
								/>
								<ToggleControl
									label={__('Allow Multiple', 'flx-blocks')}
									checked={multiple}
									help={__('This will allow users to upload multiple files', 'flx-blocks')}
									onChange={(value) => setAttributes({ multiple: value })}
								/>
								{multiple && (
									<RangeControl
										label={__('Maxim amount of files per form submission', 'flx-blocks')}
										value={multipleLimit ? multipleLimit : 5}
										onChange={(value) => setAttributes({ multipleLimit: value })}
										min={0}
										max={30}
									/>
								)}
							</FlxbkPanelBody>
							<FlxbkPanelBody title={__('File Options', 'flx-blocks')}>
								<RangeControl
									label={__('File Size Limit', 'flx-blocks')}
									value={maxSizeMb ? maxSizeMb : 10}
									onChange={(value) => setAttributes({ maxSizeMb: value })}
									min={1}
									max={wpMaxUploadSizeMb}
									step={1}
									help={
										__('WordPress max upload size:', 'flx-blocks') + ' ' + wpMaxUploadSizePretty
									}
								/>

								<h2>{__('Allowed File Types', 'flx-blocks')}</h2>
								<CheckboxControl
									label={__('Images', 'flx-blocks')}
									help="jpeg, jpg, gif, png, and webp"
									checked={allowedTypes.includes('image')}
									onChange={(value) => toggleAllowedTypes('image')}
								/>

								<CheckboxControl
									label={__('PDF', 'flx-blocks')}
									checked={allowedTypes.includes('pdf')}
									onChange={(value) => toggleAllowedTypes('pdf')}
								/>

								<CheckboxControl
									label={__('Audio', 'flx-blocks')}
									help="mp3, wav, ogg, wma, m4a, mid, mka"
									checked={allowedTypes.includes('audio')}
									onChange={(value) => toggleAllowedTypes('audio')}
								/>

								<CheckboxControl
									label={__('Video', 'flx-blocks')}
									help="mp4, mpg, mpeg, mpe, m4v, avi, mov"
									checked={allowedTypes.includes('video')}
									onChange={(value) => toggleAllowedTypes('video')}
								/>

								<CheckboxControl
									label={__('Documents', 'flx-blocks')}
									help="csv, doc, ppt, docx, ody, odp, ods, txt, rtf, xls, xlsx, odt, ott"
									checked={allowedTypes.includes('document')}
									onChange={(value) => toggleAllowedTypes('document')}
								/>
								<CheckboxControl
									label={__('Design', 'flx-blocks')}
									help="ai, ait, eps, psd, psb, xcf, svg, svgz"
									checked={allowedTypes.includes('design')}
									onChange={(value) => toggleAllowedTypes('design')}
								/>
								<CheckboxControl
									label={__('Zip Archive', 'flx-blocks')}
									checked={allowedTypes.includes('archive')}
									onChange={(value) => toggleAllowedTypes('archive')}
								/>
							</FlxbkPanelBody>
						</>
					)}
					{activeTab === 'advanced' && (
						<>
							<FlxbkPanelBody
								title={__('Field Width', 'flx-blocks')}
								initialOpen={true}
								panelName={'fxb-adv-form-text-width'}
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
							<FlxbkPanelBody
								title={__('Extra Settings', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-adv-form-text-extra-settings'}
							>
								<FieldName
									value={inputName}
									uniqueID={uniqueID}
									onChange={(value) =>
										setAttributes({ inputName: value.replace(/[^a-z0-9-_]/gi, '') })
									}
								/>
								<TextControl
									label={__('Input aria description', 'flx-blocks')}
									value={ariaDescription}
									onChange={(value) => setAttributes({ ariaDescription: value })}
									help={__(
										'This content will be hidden by default and exposed to screen readers as the aria-describedby attribute for this form field. Note that the normal description field will no longer be used for aria-describedby.',
										'flx-blocks'
									)}
								/>
								{required && (
									<TextControl
										label={__('Field error message when required', 'flx-blocks')}
										value={requiredMessage}
										onChange={(value) => setAttributes({ requiredMessage: value })}
										placeholder={
											(undefined !== label ? label : '') +
											' ' +
											__('is required', 'flx-blocks')
										}
									/>
								)}
							</FlxbkPanelBody>
						</>
					)}
				</InspectorControls>
				<div className={'fxb-form-field-container'}>
					<div className={'fxb-form-field'}>
						<FormFieldLabel
							required={required}
							label={label}
							showLabel={showLabel}
							setAttributes={setAttributes}
							isSelected={isSelected}
							name={name}
						/>
						<input type={'file'} multiple={multiple ? true : false} />

						{helpText && <span className="fxb-adv-form-help">{helpText}</span>}
					</div>
				</div>
				<FieldBlockAppender inline={true} className="fxb-custom-inbetween-inserter" getRoot={clientId} />
			</div>
		</>
	);
}

export default FieldFile;

function formatBytes(bytes) {
	if (bytes === 0) {
		return '0 Bytes';
	}

	const k = 1024;
	const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];

	const i = Math.floor(Math.log(bytes) / Math.log(k));

	return parseFloat((bytes / Math.pow(k, i)).toFixed(0)) + ' ' + sizes[i];
}

function formatBytesToMb(bytes) {
	if (bytes === 0) {
		return 0;
	}

	const k = 1024;

	return parseFloat((bytes / Math.pow(k, 2)).toFixed(0));
}
