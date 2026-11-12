/**
 * BLOCK: Flux Testimonial
 */

/**
 * Import Icons
 */
import { IconRender, IconSpanTag } from '@flx-blocks/components';

import attributes from './attributes';

/**
 * Internal block libraries
 */
import { RichText, InnerBlocks, useBlockProps } from '@wordpress/block-editor';

/**
 * Register: a Gutenberg Block.
 *
 * @link https://wordpress.org/gutenberg/handbook/block-api/
 * @param  {string}   name     Block name.
 * @param  {Object}   settings Block settings.
 * @return {?WPBlock}          The block, if it has been successfully
 *                             registered; otherwise `undefined`.
 */
export default [
	{
		attributes,
		apiVersion: 3,
		supports: {
			inserter: false,
			reusable: false,
			html: false,
			anchor: true,
			lock: false,
			fxbMetadata: true,
			fxbContentLabel: 'title',
		},
		save: ({ attributes }) => {
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
						>
							<span className="fxt-blocks-accordion-title-wrap">
								{icon && 'left' === iconSide && (
									<IconSpanTag extraClass={`fxt-btn-side-${iconSide}`} name={icon} />
								)}
								<RichText.Content
									className={'fxt-blocks-accordion-title'}
									tagName={'span'}
									value={title}
								/>
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
	},
	{
		attributes,
		apiVersion: 2,
		supports: {
			inserter: false,
			reusable: false,
			html: false,
			anchor: true,
			lock: false,
			fxbMetadata: true,
			fxbContentLabel: 'title',
		},
		save: ({ attributes }) => {
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
						>
							<span className="fxt-blocks-accordion-title-wrap">
								{icon && 'left' === iconSide && (
									<IconSpanTag extraClass={`fxt-btn-side-${iconSide}`} name={icon} />
								)}
								<RichText.Content
									className={'fxt-blocks-accordion-title'}
									tagName={'span'}
									value={title}
								/>
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
	},
	{
		attributes,
		apiVersion: 2,
		save: ({ attributes }) => {
			const { id, uniqueID, title, icon, iconSide, hideLabel, titleTag } = attributes;
			const HtmlTagOut = !titleTag ? 'div' : titleTag;
			return (
				<div className={`fxt-accordion-pane fxt-accordion-pane-${id} fxt-pane${uniqueID}`}>
					<HtmlTagOut className={'fxt-accordion-header-wrap'}>
						<button
							className={`fxt-blocks-accordion-header fxt-acccordion-button-label-${
								hideLabel ? 'hide' : 'show'
							}`}
						>
							<div className="fxt-blocks-accordion-title-wrap">
								{icon && 'left' === iconSide && (
									<IconRender
										className={`fxt-btn-svg-icon fxt-btn-svg-icon-${icon} fxt-btn-side-${iconSide}`}
										name={icon}
									/>
								)}
								<RichText.Content
									className={'fxt-blocks-accordion-title'}
									tagName={'span'}
									value={title}
								/>
								{icon && 'right' === iconSide && (
									<IconRender
										className={`fxt-btn-svg-icon fxt-btn-svg-icon-${icon} fxt-btn-side-${iconSide}`}
										name={icon}
									/>
								)}
							</div>
							<div className="fxt-blocks-accordion-icon-trigger"></div>
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
	},
	{
		attributes,
		apiVersion: 2,
		save: ({ attributes }) => {
			const { id, uniqueID, title, icon, iconSide, hideLabel, titleTag } = attributes;
			const HtmlTagOut = !titleTag ? 'div' : titleTag;
			return (
				<div className={`fxt-accordion-pane fxt-accordion-pane-${id} fxt-pane${uniqueID}`}>
					<HtmlTagOut className={'fxt-accordion-header-wrap'}>
						<button
							className={`fxt-blocks-accordion-header fxt-acccordion-button-label-${
								hideLabel ? 'hide' : 'show'
							}`}
						>
							<div className="fxt-blocks-accordion-title-wrap">
								{icon && 'left' === iconSide && (
									<IconRender
										className={`fxt-btn-svg-icon fxt-btn-svg-icon-${icon} fxt-btn-side-${iconSide}`}
										name={icon}
									/>
								)}
								<RichText.Content
									className={'fxt-blocks-accordion-title'}
									tagName={'span'}
									value={title}
								/>
								{icon && 'right' === iconSide && (
									<IconRender
										className={`fxt-btn-svg-icon fxt-btn-svg-icon-${icon} fxt-btn-side-${iconSide}`}
										name={icon}
									/>
								)}
							</div>
							<div className="fxt-blocks-accordion-icon-trigger"></div>
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
	},
];
