import { get } from 'lodash';
import { __ } from '@wordpress/i18n';
import { Fragment, useState } from '@wordpress/element';

import { Button, CheckboxControl } from '@wordpress/components';
import { SafeParseJSON } from '@flx-blocks/helpers';

function ExportDefaults() {
	const blockDefaults = SafeParseJSON(get(flxbk_params, ['configuration'], {}), true);
	const blockVisibility = SafeParseJSON(get(flxbk_params, ['settings'], {}), true);

	const [includeDefaults, setIncludeDefaults] = useState(Object.keys(blockDefaults).length === 0 ? false : true);
	const [includeVisbility, setIncludeVisibility] = useState(Object.keys(blockVisibility).length === 0 ? false : true);

	const exportData = {};

	if (includeVisbility) {
		exportData.block_visibility = blockVisibility;
	}

	if (includeDefaults) {
		exportData.block_defaults = blockDefaults;
	}

	const downloadName = 'flxbk_defaults' + '.json';
	const downloadData = 'text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(exportData));

	return (
		<Fragment>
			<p>
				{__(
					'Create an export of block default settings that can be imported into another site.',
					'flx-blocks'
				)}
			</p>

			<CheckboxControl
				label={__('Include Block Defaults', 'flx-blocks')}
				checked={includeDefaults}
				onChange={(value) => setIncludeDefaults(value)}
				disabled={Object.keys(blockDefaults).length === 0}
				help={
					Object.keys(blockDefaults).length === 0 ? __('No custom defaults to export.', 'flx-blocks') : ''
				}
			/>

			<CheckboxControl
				label={__('Include Block Visibility', 'flx-blocks')}
				checked={includeVisbility}
				onChange={(value) => setIncludeVisibility(value)}
				disabled={Object.keys(blockVisibility).length === 0}
				help={
					Object.keys(blockVisibility).length === 0
						? __('No custom visibility settings to export.', 'flx-blocks')
						: ''
				}
			/>

			<Button
				href={'data:' + downloadData}
				download={downloadName}
				isPrimary={true}
				disabled={!includeDefaults && !includeVisbility}
			>
				{__('Download Export', 'flx-blocks')}
			</Button>
		</Fragment>
	);
}

export default ExportDefaults;
