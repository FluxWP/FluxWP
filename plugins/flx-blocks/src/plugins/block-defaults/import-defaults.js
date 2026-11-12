import { has } from 'lodash';
import { __ } from '@wordpress/i18n';
import { Fragment, useState } from '@wordpress/element';

import { Button, FormFileUpload } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

function ImportDefaults() {
	const { createSuccessNotice, createErrorNotice } = useDispatch(noticesStore);
	const [includeVisbility, setIncludeVisibility] = useState(true);
	const [importFile, setImportFile] = useState();

	const parseAndUpload = (selectedFile) => {
		const fileread = new FileReader();
		let fileData;

		fileread.onload = function (e) {
			try {
				fileData = JSON.parse(e.target.result);
			} catch (e) {
				createErrorNotice(__('Invalid file', 'flx-blocks'));
				setImportFile();
				return;
			}

			if (typeof fileData !== 'object') {
				createErrorNotice(__('Invalid JSON', 'flx-blocks'));
				setImportFile();
				return;
			}

			if (!has(fileData, 'block_defaults') && !has(fileData, 'block_visibility')) {
				createErrorNotice(__('Invalid File. No block defaults or visibility settings.', 'flx-blocks'));
			}

			if (has(fileData, 'block_defaults')) {
				const blockDefaults = JSON.stringify(fileData.block_defaults);

				apiFetch({
					path: '/wp/v2/settings',
					method: 'POST',
					data: { flxbk_config_blocks: blockDefaults },
				}).then(() => {
					createSuccessNotice(__('Block defaults imported', 'flx-blocks'), {
						type: 'snackbar',
					});
					flxbk_params.configuration = blockDefaults;
				});
			}

			if (has(fileData, 'block_visibility')) {
				const blockVis = JSON.stringify(fileData.block_visibility);

				apiFetch({
					path: '/wp/v2/settings',
					method: 'POST',
					data: { flxbk_settings_blocks: blockVis },
				}).then(() => {
					createSuccessNotice(__('Block visbility imported', 'flx-blocks'), {
						type: 'snackbar',
					});
					flxbk_params.settings = blockVis;
				});
			}

			setImportFile();
		};

		fileread.readAsText(selectedFile);
	};

	return (
		<Fragment>
			<p>{__('Import settings from an export file.', 'flx-blocks')}</p>

			<FormFileUpload
				accept="application/json"
				className={'is-secondary'}
				onChange={(event) => {
					setImportFile(event.target.files[0]);
				}}
			>
				{__('Select File', 'flx-blocks')}
			</FormFileUpload>

			<br />

			<Button
				isPrimary={true}
				disabled={!importFile}
				onClick={() => {
					parseAndUpload(importFile);
				}}
			>
				{__('Import', 'flx-blocks')}
			</Button>
		</Fragment>
	);
}

export default ImportDefaults;
