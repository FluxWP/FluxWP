/**
 * Webhook Controls
 *
 */

/**
 * Imports
 */
import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';
import { TextControl } from '@wordpress/components';
import getFormFields from '../../get-form-fields';
import FlxbkPanelBody from '../../../panel-body';

/**
 * Build the Measure controls
 * @returns {object} Measure settings.
 */
function WebhookOptions({ formInnerBlocks, parentClientId, settings, save }) {
	const fields = useMemo(() => getFormFields(formInnerBlocks), [parentClientId]);
	const SLUG_REGEX = /[\s#]/g;

	const saveMap = (value, uniqueID) => {
		const updatedMap = { ...settings.map };
		updatedMap[uniqueID] = value;
		save({ map: updatedMap });
	};

	return (
		<FlxbkPanelBody
			title={__('Webhook Settings', 'flx-blocks')}
			initialOpen={false}
			panelName={'fxb-webhook-settings'}
		>
			<TextControl
				label={__('Webhook URL', 'flx-blocks')}
				help={__('Enter the URL that will receive the form submitted data.', 'flx-blocks')}
				value={undefined !== settings.url ? settings.url : ''}
				onChange={(value) => save({ url: value })}
			/>

			<>
				<h2 className="fxt-heading-size-title">{__('Map Fields', 'flx-blocks')}</h2>
				{fields &&
					fields.map((item, index) => {
						return (
							<div key={index} className="fxb-field-map-item">
								<div className="fxb-field-map-item-form">
									<p className="fxb-field-map-item-label">{__('Form Field', 'flx-blocks')}</p>
									<p className="fxb-field-map-item-name">{item.label}</p>
								</div>
								<TextControl
									label={__('Webhook Field Name', 'flx-blocks')}
									value={
										undefined !== settings.map &&
										undefined !== settings.map[item.uniqueID] &&
										settings.map[item.uniqueID]
											? settings.map[item.uniqueID]
											: ''
									}
									onChange={(nextValue) => {
										nextValue = nextValue.replace(SLUG_REGEX, '-');
										saveMap(nextValue, item.uniqueID);
									}}
								/>
							</div>
						);
					})}
			</>
		</FlxbkPanelBody>
	);
}

export default WebhookOptions;
