import { __ } from '@wordpress/i18n';
import FlxbkSetting from '../settings';
import { PanelBody } from '@wordpress/components';
import { SafeParseJSON } from '@flx-blocks/helpers';
import apiFetch from '@wordpress/api-fetch';

export default function DefaultEditorBlock() {
	function successCallback(key, value) {
		if (value) {
			const config = flxbk_params.configuration
				? SafeParseJSON(flxbk_params.configuration, true)
				: {};
			config['flx/advancedheading'] = { htmlTag: 'p' };
			apiFetch({
				path: '/wp/v2/settings',
				method: 'POST',
				data: { flxbk_config_blocks: JSON.stringify(config) },
			}).then(() => {
				flxbk_params.configuration = JSON.stringify(config);
			});
		}
	}

	return (
		<PanelBody title={__('Default Editor Block', 'flx-blocks')} initialOpen={false}>
			<FlxbkSetting
				slug={'adv_text_is_default_editor_block'}
				label={__('Set Advanced Text as default block', 'flx-blocks')}
				type={'toggle'}
				theDefault={false}
				successCallback={successCallback}
			/>
			{__(
				'This will set the Advanced Text block as the default block in the editor and update its default styles to use the paragraph tag.',
				'flx-blocks'
			)}
			&nbsp;
			{__(
				'If Flux Block Editor is disabled Advanced Text blocks will continue to display, but may lose some of their styling.',
				'flx-blocks'
			)}
		</PanelBody>
	);
}
