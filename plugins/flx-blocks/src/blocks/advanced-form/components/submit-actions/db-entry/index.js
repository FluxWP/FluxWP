/**
 * Db Entry Controls
 *
 */

/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';
import { Fragment, useEffect, useMemo, useState } from '@wordpress/element';
import { TextControl, Button, Spinner, ToggleControl, SelectControl, ExternalLink } from '@wordpress/components';
import { FlxbkPanelBody } from '@flx-blocks/components';

/**
 * Build the Measure controls
 * @returns {object} Measure settings.
 */
function DbEntryOptions({ settings, save }) {
	return (
		<FlxbkPanelBody
			title={__('Database Entry Settings', 'flx-blocks')}
			initialOpen={false}
			panelName={'fxb-db-entry-settings'}
		>
			<ToggleControl
				label={__('Save User IP Address', 'flx-blocks')}
				help={__('Saves the entrants IP address with the form data', 'flx-blocks')}
				checked={undefined !== settings?.userIP ? settings.userIP : true}
				onChange={(value) => save({ userIP: value })}
			/>
			<ToggleControl
				label={__('Save User Device', 'flx-blocks')}
				help={__('Saves the entrants device with form data', 'flx-blocks')}
				checked={undefined !== settings?.userDevice ? settings.userDevice : true}
				onChange={(value) => save({ userDevice: value })}
			/>
		</FlxbkPanelBody>
	);
}

export default DbEntryOptions;
