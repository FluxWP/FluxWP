/**
 * Flux Block Editor
 *
 */
import { get } from 'lodash';
import domReady from '@wordpress/dom-ready';

// Utils
import './plugins/flxbk-control.js';
import './plugins/editor-width.js';
import './plugins/block-rename/block-rename.js';
import './plugins/site-styles';
import './plugins/design-library';

if (typeof fxt_blocks_default_size !== 'undefined') {
	wp.data.dispatch('core/editor').updateEditorSettings({ maxWidth: fxt_blocks_default_size });
}

const config = get(flxbk_params, 'globalSettings') ? JSON.parse(flxbk_params.globalSettings) : {};
if (undefined !== config.adv_text_is_default_editor_block && config.adv_text_is_default_editor_block) {
	domReady(() => {
		wp.blocks.setDefaultBlockName('flx/advancedheading');
	});
}
