/**
 * BLOCK: Flux Pane
 *
 * Registering a basic block with Gutenberg.
 */

import { RichText, InnerBlocks, useBlockProps } from '@wordpress/block-editor';

/**
 * Import Icons
 */
import { accordionBlockIcon } from '@flx-blocks/icons';
import { IconSpanTag } from '@flx-blocks/components';

/**
 * Import edit
 */
import edit from './edit';
import metadata from './block.json';

/**
 * Import deprecated.
 */
import deprecated from './deprecated';

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
registerBlockType('flx/pane', {
	...metadata,
	title: __('Pane', 'flx-blocks'),
	icon: accordionBlockIcon,
	getEditWrapperProps(attributes) {
		return { 'data-pane': attributes.id };
	},
	edit,
	save({ attributes }) {
		const { id, uniqueID, title, icon, iconSide, hideLabel, titleTag, ariaLabel } = attributes;
		const HtmlTagOut = !titleTag ? 'div' : titleTag;

		const blockProps = useBlockProps.save({
			className: `fxt-accordion-pane fxt-accordion-pane-${id} fxt-pane${uniqueID}`,
		});

		return (
			<div {...blockProps}>
				<HtmlTagOut className={'fxt-accordion-header-wrap'}>
					<button
						className={`fxt-blocks-accordion-header fxt-acccordion-button-label-${
							hideLabel ? 'hide' : 'show'
						}`}
						aria-label={ariaLabel ? ariaLabel : undefined}
						type="button"
					>
						<span className="fxt-blocks-accordion-title-wrap">
							{icon && 'left' === iconSide && (
								<IconSpanTag extraClass={`fxt-btn-side-${iconSide}`} name={icon} />
							)}
							<RichText.Content className={'fxt-blocks-accordion-title'} tagName={'span'} value={title} />
							{icon && 'right' === iconSide && (
								<IconSpanTag extraClass={`fxt-btn-side-${iconSide}`} name={icon} />
							)}
						</span>
						<span className="fxt-blocks-accordion-icon-trigger"></span>
					</button>
				</HtmlTagOut>
				<div className={'fxt-accordion-panel'}>
					<div className={'fxt-accordion-panel-inner'}>
						<InnerBlocks.Content />
					</div>
				</div>
			</div>
		);
	},
	deprecated,
	example: {},
});
