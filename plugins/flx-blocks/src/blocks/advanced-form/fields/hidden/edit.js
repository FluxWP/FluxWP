/**
 * Internal dependencies
 */

/**
 * WordPress dependencies
 */
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps, store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	FlxbkPanelBody,
	InspectorControlTabs,
	DynamicTextInputControl,
	SelectParentBlock,
} from '@flx-blocks/components';
import { useEffect, useState, useMemo } from '@wordpress/element';
import classNames from 'classnames';
import { DuplicateField, FieldBlockAppender, FieldName, getUniqueFieldId } from '../../components';

function FieldHidden(props) {
	const { attributes, setAttributes, isSelected, clientId, context, name } = props;
	const { uniqueID, label, defaultValue, defaultParameter, inputName, flxbkDynamic } = attributes;
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
	const classes = classNames({
		'fxb-adv-form-field': true,
	});
	const blockProps = useBlockProps({
		className: classes,
	});
	const defaultPreview = useMemo(() => {
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
						panelName={'advanced-form-hidden'}
						setActiveTab={setActiveTab}
						activeTab={activeTab}
						allowedTabs={['general', 'advanced']}
					/>
					{activeTab === 'general' && (
						<>
							<FlxbkPanelBody
								title={__('Field Controls', 'flx-blocks')}
								initialOpen={true}
								panelName={'fxb-adv-form-hidden-controls'}
							>
								<TextControl
									label={__('Field Label', 'flx-blocks')}
									value={label}
									onChange={(value) => setAttributes({ label: value })}
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
							</FlxbkPanelBody>
						</>
					)}
					{activeTab === 'advanced' && (
						<>
							<FlxbkPanelBody
								title={__('Extra Settings', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-adv-form-hidden-extra-settings'}
							>
								<FieldName
									value={inputName}
									uniqueID={uniqueID}
									onChange={(value) =>
										setAttributes({ inputName: value.replace(/[^a-z0-9-_]/gi, '') })
									}
								/>
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
					{label === '' ? (
						<em className="fxb-hidden-field-label">Hidden Field</em>
					) : (
						<em className="fxb-hidden-field-label">Hidden Field: {label}</em>
					)}
				</>
				<FieldBlockAppender inline={true} className="fxb-custom-inbetween-inserter" getRoot={clientId} />
			</div>
		</>
	);
}

export default FieldHidden;
