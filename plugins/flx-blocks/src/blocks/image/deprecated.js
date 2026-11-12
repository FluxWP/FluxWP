/**
 * External dependencies
 */
import classnames from 'classnames';
/**
 * WordPress dependencies
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { getBlockDefaultClassName } from '@wordpress/blocks';
/**
 * Internal dependencies
 */
import metadata from './block.json';
const { attributes } = metadata;
export default [
	{
		attributes,
		save: ({ attributes }) => {
			const {
				url,
				alt,
				caption,
				align,
				link,
				width,
				height,
				id,
				linkTarget,
				linkNoFollow,
				linkSponsored,
				showCaption,
				sizeSlug,
				title,
				uniqueID,
				imageFilter,
				useRatio,
				ratio,
			} = attributes;

			const classes = classnames({
				[`align${align}`]: align,
				[`size-${sizeSlug}`]: sizeSlug,
				'is-resized': width || height,
				[`fxb-filter-${imageFilter}`]: imageFilter && imageFilter !== 'none',
				[`fxb-image-is-ratio-size`]: useRatio,
			});

			const allClasses = classnames({
				[`fxb-image${uniqueID}`]: uniqueID,
				[getBlockDefaultClassName('flx/image')]: getBlockDefaultClassName('flx/image'),
				[`align${align}`]: align,
				[`size-${sizeSlug}`]: sizeSlug,
				'is-resized': width || height,
				[`fxb-filter-${imageFilter}`]: imageFilter && imageFilter !== 'none',
				[`fxb-image-is-ratio-size`]: useRatio,
			});

			const containerClasses = classnames({
				[`fxb-image${uniqueID}`]: uniqueID,
				[getBlockDefaultClassName('flx/image')]: getBlockDefaultClassName('flx/image'),
			});

			let relAttr;
			if (linkTarget) {
				relAttr = 'noopener noreferrer';
			}
			if (undefined !== linkNoFollow && true === linkNoFollow) {
				relAttr = relAttr ? relAttr.concat(' nofollow') : 'nofollow';
			}
			if (undefined !== linkSponsored && true === linkSponsored) {
				relAttr = relAttr ? relAttr.concat(' sponsored') : 'sponsored';
			}
			let image = (
				<img
					src={url}
					alt={alt}
					className={id ? `fxb-img wp-image-${id}` : 'fxb-img'}
					width={width}
					height={height}
					title={title}
				/>
			);
			if (useRatio) {
				image = <div className={`fxb-is-ratio-image fxb-image-ratio-${ratio ? ratio : 'land43'}`}>{image}</div>;
			}

			const figure = (
				<>
					{link && true ? (
						<a
							href={link}
							className={'fxb-advanced-image-link'}
							target={linkTarget ? '_blank' : undefined}
							// eslint-disable-next-line react/jsx-no-target-blank
							rel={relAttr ? relAttr : undefined}
						>
							{image}
						</a>
					) : (
						image
					)}
					{!RichText.isEmpty(caption) && showCaption !== false && (
						<RichText.Content tagName="figcaption" value={caption} />
					)}
				</>
			);

			if ('left' === align || 'right' === align || 'center' === align) {
				return (
					<div {...useBlockProps.save({ className: containerClasses })}>
						<figure className={classes}>{figure}</figure>
					</div>
				);
			}

			return <figure {...useBlockProps.save({ className: allClasses })}>{figure}</figure>;
		},
	},
	{
		attributes,
		save: ({ attributes }) => {
			const {
				url,
				alt,
				caption,
				align,
				link,
				width,
				height,
				id,
				linkTarget,
				linkNoFollow,
				linkSponsored,
				showCaption,
				sizeSlug,
				title,
				uniqueID,
				imageFilter,
				useRatio,
				ratio,
			} = attributes;

			const classes = classnames({
				[`align${align}`]: align,
				[`size-${sizeSlug}`]: sizeSlug,
				'is-resized': width || height,
				[`fxb-filter-${imageFilter}`]: imageFilter && imageFilter !== 'none',
				[`fxb-image-is-ratio-size`]: useRatio,
			});

			const allClasses = classnames({
				[`fxb-image${uniqueID}`]: uniqueID,
				[getBlockDefaultClassName('flx/image')]: getBlockDefaultClassName('flx/image'),
				[`align${align}`]: align,
				[`size-${sizeSlug}`]: sizeSlug,
				'is-resized': width || height,
				[`fxb-filter-${imageFilter}`]: imageFilter && imageFilter !== 'none',
				[`fxb-image-is-ratio-size`]: useRatio,
			});

			const containerClasses = classnames({
				[`fxb-image${uniqueID}`]: uniqueID,
				[getBlockDefaultClassName('flx/image')]: getBlockDefaultClassName('flx/image'),
			});

			let relAttr;
			if (linkTarget) {
				relAttr = 'noopener noreferrer';
			}
			if (undefined !== linkNoFollow && true === linkNoFollow) {
				relAttr = relAttr ? relAttr.concat(' nofollow') : 'nofollow';
			}
			if (undefined !== linkSponsored && true === linkSponsored) {
				relAttr = relAttr ? relAttr.concat(' sponsored') : 'sponsored';
			}

			let image = (
				<img
					src={url}
					alt={alt}
					className={id ? `fxb-img flxbk-image-${id}` : 'fxb-img'}
					width={width}
					height={height}
					title={title}
				/>
			);
			if (useRatio) {
				image = <div className={`fxb-is-ratio-image fxb-image-ratio-${ratio ? ratio : 'land43'}`}>{image}</div>;
			}

			const figure = (
				<>
					{link && true ? (
						<a
							href={link}
							className={'fxb-advanced-image-link'}
							target={linkTarget ? '_blank' : undefined}
							rel={relAttr ? relAttr : undefined} // eslint-disable-line
						>
							{image}
						</a>
					) : (
						image
					)}
					{!RichText.isEmpty(caption) && showCaption !== false && (
						<RichText.Content tagName="figcaption" value={caption} />
					)}
				</>
			);

			if ('left' === align || 'right' === align || 'center' === align) {
				return (
					<div {...useBlockProps.save({ className: containerClasses })}>
						<figure className={classes}>{figure}</figure>
					</div>
				);
			}

			return <figure {...useBlockProps.save({ className: allClasses })}>{figure}</figure>;
		},
	},
];
