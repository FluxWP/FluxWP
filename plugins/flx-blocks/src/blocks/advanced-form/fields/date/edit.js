/**
 * Internal dependencies
 */
import FormFieldLabel from '../../label';

/**
 * WordPress dependencies
 */
import { TextControl, TextareaControl, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import { InspectorControls, useBlockProps, store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	FlxbkPanelBody,
	InspectorControlTabs,
	ResponsiveRangeControls,
	DynamicTextInputControl,
	SelectParentBlock,
} from '@flx-blocks/components';
import { useEffect, useState, useMemo } from '@wordpress/element';
import { getPreviewSize } from '@flx-blocks/helpers';
import classNames from 'classnames';
import { DuplicateField, FieldBlockAppender, FieldName, getUniqueFieldId } from '../../components';
import { fieldAutoFillOptions } from '../../constants';

function FieldDate(props) {
	const { attributes, setAttributes, isSelected, clientId, context, name } = props;
	const {
		uniqueID,
		required,
		label,
		showLabel,
		defaultValue,
		helpText,
		ariaDescription,
		maxWidth,
		maxWidthUnit,
		minWidth,
		minWidthUnit,
		defaultParameter,
		auto,
		autoCustom,
		inputName,
		requiredMessage,
		flxbkDynamic,
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
	const defaultPreview = useMemo(() => {
		if (defaultValue === 'today') {
			return dateI18n('Y-m-d', new Date());
		}
		if (
			undefined !== flxbkDynamic &&
			undefined !== flxbkDynamic.defaultValue &&
			undefined !== flxbkDynamic.defaultValue?.enable &&
			'' !== flxbkDynamic.defaultValue.enable &&
			true === flxbkDynamic.defaultValue.enable
		) {
			return flxbkDynamic?.defaultValue?.field ? '{' + flxbkDynamic.defaultValue.field + '}' : '';
		}
		return attributes?.defaultValue ? attributes.defaultValue : '';
	}, [flxbkDynamic, defaultValue]);
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
						panelName={'advanced-form-date'}
						setActiveTab={setActiveTab}
						activeTab={activeTab}
						allowedTabs={['general', 'advanced']}
					/>
					{activeTab === 'general' && (
						<>
							<FlxbkPanelBody
								title={__('Field Controls', 'flx-blocks')}
								initialOpen={true}
								panelName={'fxb-adv-form-date-controls'}
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
								<DynamicTextInputControl
									label={__('Default Value', 'flx-blocks')}
									value={defaultValue}
									preview={defaultPreview}
									onChange={(value) => setAttributes({ defaultValue: value })}
									dynamicAttribute={'defaultValue'}
									allowClear={true}
									isSelected={isSelected}
									attributes={attributes}
									setAttributes={setAttributes}
									name={name}
									clientId={clientId}
									context={context}
								/>
								<em>{__('Set to "today" to fill with the current date', 'flx-blocks')}</em>
							</FlxbkPanelBody>
						</>
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
							<FlxbkPanelBody
								title={__('Extra Settings', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-adv-form-date-extra-settings'}
							>
								<FieldName
									value={inputName}
									uniqueID={uniqueID}
									onChange={(value) =>
										setAttributes({ inputName: value.replace(/[^a-z0-9-_]/gi, '') })
									}
								/>
								<SelectControl
									label={__('Field Auto Fill', 'flx-blocks')}
									value={auto}
									options={fieldAutoFillOptions}
									onChange={(value) => setAttributes({ auto: value })}
								/>
								{auto === 'custom' && (
									<TextControl
										label={__('Auto Fill Value', 'flx-blocks')}
										value={autoCustom}
										onChange={(value) => setAttributes({ autoCustom: value })}
									/>
								)}
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
								<TextControl
									label={__('Populate with Parameter', 'flx-blocks')}
									help={__(
										'Enter a parameter that can be used in the page url to dynamically populate the field.',
										'flx-blocks'
									)}
									value={defaultParameter}
									onChange={(value) => setAttributes({ defaultParameter: value })}
								/>
							</FlxbkPanelBody>
						</>
					)}
				</InspectorControls>
				<>
					<FormFieldLabel
						required={required}
						label={label}
						showLabel={showLabel}
						setAttributes={setAttributes}
						isSelected={isSelected}
						name={name}
					/>
					<input type={'date'} className={'fxb-field'} value={defaultPreview} onChange={(value) => false} />
					{helpText && <span className="fxb-adv-form-help">{helpText}</span>}
				</>
				<FieldBlockAppender inline={true} className="fxb-custom-inbetween-inserter" getRoot={clientId} />
			</div>
		</>
	);
}

export default FieldDate;
