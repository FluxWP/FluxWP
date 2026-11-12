/**
 * BLOCK: Flux Single Testimonial
 *
 */

import metadata from './block.json';
import TestimonialItemWrap from './carousel-item-wrap';
import { useIsSiteStylesPanel } from '../../site-styles/use-is-site-styles-panel';

/**
 * Import External
 */
import { has } from 'lodash';

/**
 * Import Components
 */

import {
	PopColorControl,
	TypographyControls,
	ResponsiveMeasurementControls,
	ResponsiveRangeControls,
	FlxbkPanelBody,
	WebfontLoader,
	FlxbkIconPicker,
	IconRender,
	FlxbkMediaPlaceholder,
	MeasurementControls,
	InspectorControlTabs,
	FlxbkBlockDefaults,
	ResponsiveMeasureRangeControl,
	CopyPasteAttributes,
	SelectParentBlock,
} from '@flx-blocks/components';
import { getPreviewSize, FlxbkColorOutput, showSettings, uniqueIdHelper } from '@flx-blocks/helpers';

/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';

import { useEffect, Fragment, useState, useRef } from '@wordpress/element';

import { MediaUpload, RichText, InspectorControls, useBlockProps, BlockControls } from '@wordpress/block-editor';

import { useSelect, useDispatch } from '@wordpress/data';

import {
	Button,
	ButtonGroup,
	Dashicon,
	RangeControl,
	ToggleControl,
	SelectControl,
	Tooltip,
} from '@wordpress/components';

import { closeSmall, image } from '@wordpress/icons';
import classnames from 'classnames';

/**
 * Build the overlay edit
 */

function FlxbkTestimonials(props) {
	const { attributes, setAttributes, className, clientId, isSelected, context } = props;

	const {
		uniqueID,
		url,
		id,
		media,
		icon,
		isize,
		istroke,
		ititle,
		color,
		title,
		content,
		name,
		occupation,
		rating,
		sizes,
		tabletIsize,
		mobileIsize,
		inQueryBlock,
	} = attributes;

	const displayContent = context['flx/testimonials-displayContent'];
	const displayTitle = context['flx/testimonials-displayTitle'];
	const displayRating = context['flx/testimonials-displayRating'];
	const displayName = context['flx/testimonials-displayName'];
	const displayIcon = context['flx/testimonials-displayIcon'];
	const iconStyles = context['flx/testimonials-iconStyles'];
	const style = context['flx/testimonials-style'];

	const titleFont = context['flx/testimonials-titleFont'];

	const displayOccupation = context['flx/testimonials-displayOccupation'];
	const displayMedia = context['flx/testimonials-displayMedia'];
	const containerMaxWidth = context['flx/testimonials-containerMaxWidth'];
	const mediaStyles = context['flx/testimonials-mediaStyles'];
	const ratingStyles = context['flx/testimonials-ratingStyles'];

	const [activeTab, setActiveTab] = useState('general');

	const { previewDevice } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
			};
		},
		[clientId]
	);

	useEffect(() => {
		if (context && context.queryId && context.postId) {
			if (context.queryId !== inQueryBlock) {
				setAttributes({
					inQueryBlock: context.queryId,
				});
			}
		} else if (inQueryBlock) {
			setAttributes({
				inQueryBlock: false,
			});
		}
	}, []);

	uniqueIdHelper(props);
	const isSiteStylesPanel = useIsSiteStylesPanel();

	const previewIconSize = getPreviewSize(
		previewDevice,
		undefined !== isize ? isize : '',
		undefined !== tabletIsize ? tabletIsize : '',
		undefined !== mobileIsize ? mobileIsize : ''
	);

	const nonTransAttrs = ['url', 'title', 'content', 'name', 'occupation'];

	const blockProps = useBlockProps({});

	const ALLOWED_MEDIA_TYPES = ['image'];

	const renderTestimonialSettings = () => {
		return (
			<>
				{!isSiteStylesPanel && (
					<SelectControl
						label={__('Media Type', 'flx-blocks')}
						value={media}
						options={[
							{ value: 'image', label: __('Image', 'flx-blocks') },
							{ value: 'icon', label: __('Icon', 'flx-blocks') },
						]}
						onChange={(value) => setAttributes({ media: value })}
					/>
				)}
				{(isSiteStylesPanel || 'icon' === media) && (
					<Fragment>
						{!isSiteStylesPanel && (
							<FlxbkIconPicker
								value={icon}
								onChange={(value) => {
									setAttributes({ icon: value });
								}}
							/>
						)}
						<ResponsiveRangeControls
							label={__('Icon Size', 'flx-blocks')}
							value={undefined !== isize ? isize : ''}
							onChange={(value) => setAttributes({ isize: value })}
							tabletValue={undefined !== tabletIsize ? tabletIsize : ''}
							onChangeTablet={(value) => setAttributes({ tabletIsize: value })}
							mobileValue={undefined !== mobileIsize ? mobileIsize : ''}
							onChangeMobile={(value) => setAttributes({ mobileIsize: value })}
							min={0}
							max={300}
							step={1}
							unit={'px'}
							showUnit={true}
							units={['px']}
						/>
						{icon && 'fe' === icon.substring(0, 2) && !isSiteStylesPanel && (
							<RangeControl
								label={__('Line Width', 'flx-blocks')}
								value={istroke}
								onChange={(value) => {
									setAttributes({ istroke: value });
								}}
								step={0.5}
								min={0.5}
								max={4}
							/>
						)}
						<PopColorControl
							label={__('Icon Color', 'flx-blocks')}
							value={color || isSiteStylesPanel ? color : '#555555'}
							default={isSiteStylesPanel ? '' : '#555555'}
							onChange={(value) => setAttributes({ color: value })}
						/>
					</Fragment>
				)}
				{!isSiteStylesPanel && (
					<RangeControl
						label={__('Rating', 'flx-blocks')}
						value={rating}
						onChange={(value) => {
							setAttributes({ rating: value });
						}}
						step={1}
						min={1}
						max={5}
					/>
				)}
			</>
		);
	};

	const renderTestimonialIcon = () => {
		return (
			<div className="fxt-svg-testimonial-global-icon-wrap">
				<IconRender
					className={`fxt-svg-testimonial-global-icon fxt-svg-testimonial-global-icon-${iconStyles[0].icon}`}
					name={iconStyles[0].icon}
					size={iconStyles[0].size}
					title={iconStyles[0].title ? iconStyles[0].title : ''}
					strokeWidth={'fe' === iconStyles[0].icon.substring(0, 2) ? iconStyles[0].stroke : undefined}
					style={{
						color: iconStyles[0].color ? FlxbkColorOutput(iconStyles[0].color) : undefined,
						background: iconStyles[0].background
							? FlxbkColorOutput(
									iconStyles[0].background,
									undefined !== iconStyles[0].backgroundOpacity ? iconStyles[0].backgroundOpacity : 1
								)
							: undefined,
					}}
				/>
			</div>
		);
	};
	const renderTestimonialMedia = () => {
		let urlOutput = url;
		if (has(sizes, 'thumbnail')) {
			if (('card' === style && containerMaxWidth > 500) || mediaStyles[0].width > 600) {
				urlOutput = url;
			} else if ('card' === style && containerMaxWidth <= 500 && containerMaxWidth > 100) {
				if (sizes.large && sizes.large.width > 1000) {
					urlOutput = sizes.large.url;
				}
			} else if ('card' === style && containerMaxWidth <= 100) {
				if (sizes.medium && sizes.medium.width > 200) {
					urlOutput = sizes.medium.url;
				} else if (sizes.large && sizes.large.width > 200) {
					urlOutput = sizes.large.url;
				}
			} else if (mediaStyles[0].width <= 600 && mediaStyles[0].width > 100) {
				if (sizes.large && sizes.large.width > 1000) {
					urlOutput = sizes.large.url;
				}
			} else if (mediaStyles[0].width <= 100 && mediaStyles[0].width > 75) {
				if (sizes.medium && sizes.medium.width > 200) {
					urlOutput = sizes.medium.url;
				} else if (sizes.large && sizes.large.width > 200) {
					urlOutput = sizes.large.url;
				}
			} else if (mediaStyles[0].width <= 75) {
				if (sizes.thumbnail && sizes.thumbnail.width > 140) {
					urlOutput = sizes.thumbnail.url;
				} else if (sizes.medium && sizes.medium.width > 140) {
					urlOutput = sizes.medium.url;
				} else if (sizes.large && sizes.large.width > 200) {
					urlOutput = sizes.large.url;
				}
			}
		}

		return (
			<div className="fxt-testimonial-media-wrap">
				<div className="fxt-testimonial-media-inner-wrap">
					<div className={'flxbk-testimonial-image-intrisic'}>
						{'icon' === media && icon && (
							<IconRender
								className={`fxt-svg-testimonial-icon fxt-svg-testimonial-icon-${icon}`}
								name={icon}
								size={previewIconSize}
								title={ititle ? ititle : ''}
								strokeWidth={'fe' === icon.substring(0, 2) ? istroke : undefined}
								style={{
									display: 'flex',
									color: color ? FlxbkColorOutput(color) : undefined,
								}}
							/>
						)}
						{'icon' !== media && url && (
							<>
								<MediaUpload
									onSelect={(media) => {
										setAttributes({
											id: media.id,
											url: media.url,
											alt: media.alt,
											subtype: media.subtype,
											sizes: media.sizes,
										});
									}}
									type="image"
									value={id ? id : ''}
									allowedTypes={ALLOWED_MEDIA_TYPES}
									render={({ open }) => (
										<Tooltip text={__('Edit Image', 'flx-blocks')}>
											<Button
												style={{
													backgroundImage: 'url("' + urlOutput + '")',
													backgroundSize:
														'card' === style ? mediaStyles[0].backgroundSize : undefined,
													borderRadius: mediaStyles[0].borderRadius + 'px',
												}}
												className={'fxt-testimonial-image'}
												onClick={open}
											/>
										</Tooltip>
									)}
								/>
								<Button
									label={__('Remove Image', 'flx-blocks')}
									className={'fxt-remove-img fxt-testimonial-remove-image'}
									onClick={() => {
										setAttributes({
											id: null,
											url: null,
											alt: null,
											subtype: null,
											sizes: null,
										});
									}}
									icon={closeSmall}
									showTooltip={true}
								/>
							</>
						)}
						{'icon' !== media && !url && (
							<Fragment>
								{'card' === style && (
									<FlxbkMediaPlaceholder
										onSelect={(media) => {
											setAttributes({
												id: media.id,
												url: media.url,
												alt: media.alt,
												sizes: media.sizes,
												subtype: media.subtype,
											});
										}}
										value={''}
										allowedTypes={ALLOWED_MEDIA_TYPES}
										onSelectURL={(media) => {
											if (media !== url) {
												setAttributes({
													id: null,
													url: media,
													alt: null,
													sizes: null,
													subtype: null,
												});
											}
										}}
										accept="image/*"
										className={'flxbk-image-upload'}
									/>
								)}
								{'card' !== style && (
									<MediaUpload
										onSelect={(media) => {
											setAttributes({
												id: media.id,
												url: media.url,
												alt: media.alt,
												sizes: media.sizes,
												subtype: media.subtype,
											});
										}}
										type="image"
										value={''}
										allowedTypes={ALLOWED_MEDIA_TYPES}
										render={({ open }) => (
											<Button
												className="fxt-testimonial-image-placeholder"
												aria-label={__('Add Image', 'flx-blocks')}
												icon={image}
												style={{
													borderRadius: mediaStyles[0].borderRadius + 'px',
												}}
												onClick={open}
											/>
										)}
									/>
								)}
							</Fragment>
						)}
					</div>
				</div>
			</div>
		);
	};

	const renderTestimonialPreview = (isCarousel = false) => {
		return (
			<div className={`fxt-testimonial-item-wrap fxt-testimonial-item-${uniqueID}`}>
				<div className="fxt-testimonial-text-wrap">
					{displayIcon && iconStyles[0].icon && 'card' !== style && renderTestimonialIcon()}
					{displayMedia && ('card' === style || 'inlineimage' === style) && renderTestimonialMedia()}
					{displayIcon && iconStyles[0].icon && 'card' === style && renderTestimonialIcon()}
					{displayTitle && (
						<div className="fxt-testimonial-title-wrap">
							<RichText
								tagName={'h' + titleFont[0].level}
								value={title}
								onChange={(value) => {
									setAttributes({ title: value });
								}}
								placeholder={__('Best product I have ever used!', 'flx-blocks')}
								className={'fxt-testimonial-title'}
							/>
						</div>
					)}
					{displayRating && (
						<div className={`fxt-testimonial-rating-wrap fxt-testimonial-rating-${rating}`}>
							<IconRender
								className={'fxt-svg-testimonial-rating-icon fxt-svg-testimonial-rating-icon-1'}
								name={'fas_star'}
								size={undefined !== ratingStyles?.[0]?.size ? ratingStyles[0].size : '1em'}
								style={{
									color:
										undefined !== ratingStyles?.[0]?.color
											? FlxbkColorOutput(ratingStyles[0].color)
											: undefined,
								}}
							/>
							{rating > 1 && (
								<IconRender
									className={'fxt-svg-testimonial-rating-icon fxt-svg-testimonial-rating-icon-2'}
									name={'fas_star'}
									size={undefined !== ratingStyles?.[0]?.size ? ratingStyles[0].size : '1em'}
									style={{
										color:
											undefined !== ratingStyles?.[0]?.color
												? FlxbkColorOutput(ratingStyles[0].color)
												: undefined,
									}}
								/>
							)}
							{rating > 2 && (
								<IconRender
									className={'fxt-svg-testimonial-rating-icon fxt-svg-testimonial-rating-icon-3'}
									name={'fas_star'}
									size={undefined !== ratingStyles?.[0]?.size ? ratingStyles[0].size : '1em'}
									style={{
										color:
											undefined !== ratingStyles?.[0]?.color
												? FlxbkColorOutput(ratingStyles[0].color)
												: undefined,
									}}
								/>
							)}
							{rating > 3 && (
								<IconRender
									className={'fxt-svg-testimonial-rating-icon fxt-svg-testimonial-rating-icon-4'}
									name={'fas_star'}
									size={undefined !== ratingStyles?.[0]?.size ? ratingStyles[0].size : '1em'}
									style={{
										color:
											undefined !== ratingStyles?.[0]?.color
												? FlxbkColorOutput(ratingStyles[0].color)
												: undefined,
									}}
								/>
							)}
							{rating > 4 && (
								<IconRender
									className={'fxt-svg-testimonial-rating-icon fxt-svg-testimonial-rating-icon-5'}
									name={'fas_star'}
									size={undefined !== ratingStyles?.[0]?.size ? ratingStyles[0].size : '1em'}
									style={{
										color:
											undefined !== ratingStyles?.[0]?.color
												? FlxbkColorOutput(ratingStyles[0].color)
												: undefined,
									}}
								/>
							)}
						</div>
					)}
					{displayContent && (
						<div className="fxt-testimonial-content-wrap">
							<RichText
								tagName={'div'}
								placeholder={__(
									'I have been looking for a product like this for years. I have tried everything and nothing did what I wanted until using this product. I am so glad I found it!',
									'flx-blocks'
								)}
								value={content}
								onChange={(value) => {
									setAttributes({ content: value });
								}}
								className={'fxt-testimonial-content'}
							/>
						</div>
					)}
				</div>
				{((displayMedia && 'card' !== style && 'inlineimage' !== style) ||
					displayOccupation ||
					displayName) && (
					<div className="fxt-testimonial-meta-wrap">
						{displayMedia && 'card' !== style && 'inlineimage' !== style && renderTestimonialMedia()}
						<div className="fxt-testimonial-meta-name-wrap">
							{displayName && (
								<div className="fxt-testimonial-name-wrap">
									<RichText
										tagName={'div'}
										placeholder={__('Sophia Reily', 'flx-blocks')}
										value={name}
										onChange={(value) => {
											setAttributes({ name: value });
										}}
										className={'fxt-testimonial-name'}
									/>
								</div>
							)}
							{displayOccupation && (
								<div className="fxt-testimonial-occupation-wrap">
									<RichText
										tagName={'div'}
										placeholder={__('CEO of Company', 'flx-blocks')}
										value={occupation}
										onChange={(value) => {
											setAttributes({ occupation: value });
										}}
										className={'fxt-testimonial-occupation'}
									/>
								</div>
							)}
						</div>
					</div>
				)}
			</div>
		);
	};
	return (
		<TestimonialItemWrap {...{ attributes, setAttributes, isSelected, clientId, context, previewDevice }}>
			<div {...blockProps}>
				{showSettings('allSettings') && (
					<>
						<BlockControls>
							<CopyPasteAttributes
								attributes={attributes}
								excludedAttrs={nonTransAttrs}
								defaultAttributes={metadata.attributes}
								blockSlug={metadata.name}
								onPaste={(attributesToPaste) => setAttributes(attributesToPaste)}
							/>
						</BlockControls>
						<InspectorControls>
							<SelectParentBlock clientId={clientId} />
							<InspectorControlTabs
								panelName={'testimonial'}
								allowedTabs={isSiteStylesPanel ? ['general'] : ['general', 'advanced']}
								setActiveTab={(value) => setActiveTab(value)}
								activeTab={activeTab}
							/>

							{activeTab === 'general' && (
								<>
									{showSettings('individualSettings', 'flx/testimonials') && (
										<FlxbkPanelBody
											title={__('Individual Settings', 'flx-blocks')}
											initialOpen={true}
											panelName={'fxb-testimonials-individual-settings'}
										>
											{renderTestimonialSettings()}
										</FlxbkPanelBody>
									)}
								</>
							)}

							{activeTab === 'advanced' && !isSiteStylesPanel && (
								<>
									<FlxbkBlockDefaults
										attributes={attributes}
										defaultAttributes={metadata.attributes}
										blockSlug={metadata.name}
										excludedAttrs={nonTransAttrs}
									/>
								</>
							)}
						</InspectorControls>
					</>
				)}

				<>{renderTestimonialPreview(true)}</>
			</div>
		</TestimonialItemWrap>
	);
}

export default FlxbkTestimonials;
