/**
 * BLOCK: Flux Video Popup
 *
 * Registering a basic block with Gutenberg.
 */

/**
 * Import Icons
 */
import { videoPopupIcon } from '@flx-blocks/icons';

/**
 * Imports
 */
import edit from './edit';
import save from './save';
import metadata from './block.json';
import backwardCompatibility from './deprecated';
const { name } = metadata;

/**
 * Import Css
 */
import './editor.scss';
/**
 * Import Css
 */
import './style.scss';

export { metadata, name };

/**
 * Internal block libraries
 */
import { __, _x } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Register: a Gutenberg Block.
 *
 * @link https://wordpress.org/gutenberg/handbook/block-api/
 * @param  {string}   name     Block name.
 * @param  {Object}   settings Block settings.
 * @return {?WPBlock}          The block, if it has been successfully
 *                             registered; otherwise `undefined`.
 */
registerBlockType('flx/videopopup', {
	...metadata,
	description: _x('Display a thumbnail that opens a video in a popup.', 'block description', 'flx-blocks'),
	title: __('Video Popup', 'flx-blocks'),
	icon: {
		src: videoPopupIcon,
	},
	category: 'flx-blocks',
	keywords: [__('Video Popup', 'flx-blocks'), __('Modal', 'flx-blocks'), 'flux'],
	example: {
		attributes: {
			background: [
				{
					color: '',
					colorOpacity: 1,
					img: 'https://s.w.org/images/core/5.3/MtBlanc1.jpg',
					imgID: 9288,
					imgAlt: '',
					imgWidth: 2560,
					imageHeight: 1982,
					bgSize: 'cover',
					bgPosition: 'center center',
					bgAttachment: 'scroll',
					bgRepeat: 'no-repeat',
				},
			],
		},
	},
	edit,
	save,
	deprecated: backwardCompatibility,
});
