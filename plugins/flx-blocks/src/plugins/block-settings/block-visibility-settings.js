import { Component, Fragment, useState } from '@wordpress/element';
import { Dashicon, Tooltip, SelectControl, Button, Modal } from '@wordpress/components';
import { map } from 'lodash';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal block libraries
 */
import { __, sprintf } from '@wordpress/i18n';

function FlxbkVisibilitySettings({ blockSlug, blockName, options }) {
	const [isOpen, setIsOpen] = useState(false);
	const [hasChanges, setHasChanges] = useState(false);
	const [isSaving, setIsSaving] = useState(false);
	const [settings, setSettings] = useState(
		flxbk_params.settings ? JSON.parse(flxbk_params.settings) : {}
	);
	const { createSuccessNotice } = useDispatch(noticesStore);

	const saveConfig = (blockID, settingArray) => {
		setIsSaving(true);

		const config = flxbk_params.settings ? JSON.parse(flxbk_params.settings) : {};
		if (!config[blockID]) {
			config[blockID] = {};
		}
		config[blockID] = settingArray;
		const settingModel = new wp.api.models.Settings({ flxbk_settings_blocks: JSON.stringify(config) });

		settingModel.save().then((response) => {
			createSuccessNotice(blockName + ' ' + __('block visibility saved!', 'flx-blocks'), {
				type: 'snackbar',
			});

			setIsSaving(false);
			setSettings(config);
			setIsOpen(false);
			setHasChanges(false);

			flxbk_params.settings = JSON.stringify(config);
		});
	};

	const saveConfigState = (key, value) => {
		const config = settings;

		if (!config['flx/' + blockSlug]) {
			config['flx/' + blockSlug] = {};
		}
		config['flx/' + blockSlug][key] = value;

		setHasChanges(true);
		setSettings(config);
	};

	const resetSettings = () => {
		const config = flxbk_params.settings ? JSON.parse(flxbk_params.settings) : {};
		setSettings(config);

		setHasChanges(false);
		setIsOpen(false);
	};

	const blockSettings = settings && settings['flx/' + blockSlug] ? settings['flx/' + blockSlug] : {};

	return (
		<Fragment>
			<Tooltip text={__('Block Settings Visibility', 'flx-blocks')}>
				<Button className="fxt-block-settings" onClick={() => setIsOpen(true)}>
					<Dashicon icon="visibility" />
				</Button>
			</Tooltip>

			{isOpen && (
				<Modal
					className="fxt-block-settings-modal"
					title={sprintf(
						/* translators: %s is the name of the Flux Block */
						__('%s Settings', 'flx-blocks'),
						blockName
					)}
					onRequestClose={() => {
						resetSettings();
					}}
				>
					<h2>{__('Control All Block Settings', 'flx-blocks')}</h2>

					<SelectControl
						label={__('Enabled All Settings For', 'flx-blocks')}
						value={blockSettings.allSettings ? blockSettings.allSettings : 'all'}
						options={[
							{ value: 'all', label: __('All Users', 'flx-blocks') },
							{ value: 'contributor', label: __('Minimum User Role Contributor', 'flx-blocks') },
							{ value: 'author', label: __('Minimum User Role Author', 'flx-blocks') },
							{ value: 'editor', label: __('Minimum User Role Editor', 'flx-blocks') },
							{ value: 'admin', label: __('Minimum User Role Admin', 'flx-blocks') },
							{ value: 'none', label: __('No Users', 'flx-blocks') },
						]}
						onChange={(value) => saveConfigState('allSettings', value)}
					/>

					<h2>{__('Control Individual Settings Groups', 'flx-blocks')}</h2>

					{map(options, ({ key, label }) => (
						<SelectControl
							label={label}
							value={blockSettings[key] ? blockSettings[key] : 'all'}
							options={[
								{ value: 'all', label: __('All Users', 'flx-blocks') },
								{ value: 'contributor', label: __('Minimum User Role Contributor', 'flx-blocks') },
								{ value: 'author', label: __('Minimum User Role Author', 'flx-blocks') },
								{ value: 'editor', label: __('Minimum User Role Editor', 'flx-blocks') },
								{ value: 'admin', label: __('Minimum User Role Admin', 'flx-blocks') },
								{ value: 'none', label: __('No Users', 'flx-blocks') },
							]}
							onChange={(value) => saveConfigState(key, value)}
						/>
					))}

					<Button
						className="fxt-settings-save"
						isPrimary
						isBusy={isSaving}
						disabled={!hasChanges}
						onClick={() => {
							saveConfig('flx/' + blockSlug, blockSettings);
						}}
					>
						{__('Save', 'flx-blocks')}
					</Button>

					<Button
						className="fxt-settings-save"
						isDestructive
						style={{ float: 'right' }}
						onClick={() => {
							resetSettings();
						}}
					>
						{__('Close', 'flx-blocks')}
					</Button>
				</Modal>
			)}
		</Fragment>
	);
}

export default FlxbkVisibilitySettings;
