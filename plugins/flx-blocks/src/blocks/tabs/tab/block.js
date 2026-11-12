/**
 * BLOCK: Flux Tab
 *
 * Registering a basic block with Gutenberg.
 */

import metadata from './block.json';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

/**
 * Import Icons
 */
import { tabsBlockIcon } from '@flx-blocks/icons';

/**
 * Import edit
 */
import edit from './edit';

/**
 * Internal block libraries
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Register: a Gutenberg Block.
 *
 * @link https://wordpress.org/gutenberg/handbook/block-api/
 * @param  {string}   name     Block name.
 * @param  {Object}   settings Block settings.
 * @return {?WPBlock}          The block, if it has been successfully
 *                             registered; otherwise `undefined`.
 */
registerBlockType('flx/tab', {
	...metadata,
	title: __('Tab', 'flx-blocks'),
	keywords: [__('tabs', 'flx-blocks'), __('tab', 'flx-blocks'), 'flux'],
	icon: {
		src: tabsBlockIcon,
	},
	getEditWrapperProps(attributes) {
		return { 'data-tab': attributes.id };
	},
	edit,
	save({ attributes }) {
		const { id, uniqueID } = attributes;

		const blockProps = useBlockProps.save({
			className: `fxt-tab-inner-content fxt-inner-tab-${id} fxt-inner-tab${uniqueID}`,
		});

		return (
			<div {...blockProps}>
				<div className={'fxt-tab-inner-content-inner'}>
					<InnerBlocks.Content />
				</div>
			</div>
		);
	},
	deprecated: [
		{
			attributes: {
				id: {
					type: 'number',
					default: 1,
				},
			},
			save: ({ attributes }) => {
				const { id } = attributes;
				return (
					<div className={`fxt-tab-inner-content fxt-inner-tab-${id}`}>
						<div className={'fxt-tab-inner-content-inner'}>
							<InnerBlocks.Content />
						</div>
					</div>
				);
			},
		},
	],
});
