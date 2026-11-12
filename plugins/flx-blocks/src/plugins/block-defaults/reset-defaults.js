import { __ } from '@wordpress/i18n';
import { SafeParseJSON } from '@flx-blocks/helpers';
import { get } from 'lodash';

import { Fragment, useState } from '@wordpress/element';

import { Button, CheckboxControl, __experimentalConfirmDialog as ConfirmDialog } from '@wordpress/components';

import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

function ResetDefaults() {
	const [includeVisbility, setIncludeVisibility] = useState(true);
	const [includeDefaults, setIncludeDefaults] = useState(true);
	const [isConfirmOpen, setIsConfirmOpen] = useState(false);
	const { createSuccessNotice } = useDispatch(noticesStore);

	const blockDefaults = SafeParseJSON(get(flxbk_params, ['configuration'], {}), true);
	const blockVisibility = SafeParseJSON(get(flxbk_params, ['settings'], {}), true);

	const reset = () => {
		if (includeDefaults) {
			apiFetch({
				path: '/wp/v2/settings',
				method: 'POST',
				data: { flxbk_config_blocks: JSON.stringify({}) },
			}).then(() => {
				createSuccessNotice(__('Block defaults reset', 'flx-blocks'), {
					type: 'snackbar',
				});
				flxbk_params.configuration = JSON.stringify({});
			});
		}

		if (includeVisbility) {
			apiFetch({
				path: '/wp/v2/settings',
				method: 'POST',
				data: { flxbk_settings_blocks: JSON.stringify({}) },
			}).then(() => {
				createSuccessNotice(__('Block visibility reset', 'flx-blocks'), {
					type: 'snackbar',
				});
				flxbk_params.settings = JSON.stringify({});
			});
		}

		setIsConfirmOpen(false);
	};

	return (
		<Fragment>
			<p>
				{__(
					'Reset all custom block defaults or visability settings. This will not modify any existing blocks.',
					'flx-blocks'
				)}
			</p>

			<CheckboxControl
				label={__('Reset Block Visibility Settings', 'flx-blocks')}
				checked={includeVisbility}
				onChange={(value) => setIncludeVisibility(value)}
				disabled={Object.keys(blockVisibility).length === 0}
			/>

			<CheckboxControl
				label={__('Reset Block Defaults', 'flx-blocks')}
				checked={includeDefaults}
				onChange={(value) => setIncludeDefaults(value)}
				disabled={Object.keys(blockDefaults).length === 0}
			/>

			<Button
				isDestructive={true}
				disabled={Object.keys(blockDefaults).length === 0 && Object.keys(blockVisibility).length === 0}
				onClick={() => setIsConfirmOpen(true)}
			>
				{__('Reset', 'flx-blocks')}
			</Button>

			<ConfirmDialog isOpen={isConfirmOpen} onConfirm={() => reset()} onCancel={() => setIsConfirmOpen(false)}>
				{__("Are you sure you'd like to reset these settings?", 'flx-blocks')}
			</ConfirmDialog>
		</Fragment>
	);
}

export default ResetDefaults;
