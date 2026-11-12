/**
 * BLOCK: Flux Testimonials
 */

import { FlxbkColorOutput } from '@flx-blocks/helpers';
import { RichText, useBlockProps, InnerBlocks } from '@wordpress/block-editor';
/**
 * External dependencies
 */
import { IconSpanTag } from '@flx-blocks/components';
/**
 * External dependencies
 */
import { times } from 'lodash';
import classnames from 'classnames';

function Save({ attributes }) {
	// const {
	// 	uniqueID,
	// 	style,
	// 	hAlign,
	// 	layout,
	// 	itemsCount,
	// 	containerBackground,
	// 	containerBorder,
	// 	containerBorderWidth,
	// 	containerBorderRadius,
	// 	containerPadding,
	// 	mediaStyles,
	// 	displayTitle,
	// 	titleFont,
	// 	displayContent,
	// 	displayName,
	// 	displayMedia,
	// 	displayShadow,
	// 	shadow,
	// 	displayRating,
	// 	ratingStyles,
	// 	displayOccupation,
	// 	containerBackgroundOpacity,
	// 	containerBorderOpacity,
	// 	containerMaxWidth,
	// 	columnGap,
	// 	autoPlay,
	// 	autoSpeed,
	// 	transSpeed,
	// 	slidesScroll,
	// 	arrowStyle,
	// 	dotStyle,
	// 	columns,
	// 	displayIcon,
	// 	iconStyles,
	// 	containerVAlign,
	// 	containerPaddingType,
	// } = attributes;

	// const blockProps = useBlockProps.save( {
	// 	className: classnames( {
	// 		[ `wp-block-flx-testimonials` ]                                                                       : true,
	// 		[ `fxt-testimonial-halign-${hAlign}` ]                                                                     : true,
	// 		[ `fxt-testimonial-style-${style}` ]                                                                       : true,
	// 		[ `fxt-testimonials-media-${( displayMedia ? 'on' : 'off' )}` ]                                            : true,
	// 		[ `fxt-testimonials-icon-${( displayIcon ? 'on' : 'off' )}` ]                                              : true,
	// 		[ `fxt-testimonial-columns-${columns[ 0 ]}` ]                                                              : true,
	// 		[ `fxt-t-xxl-col-${columns[ 0 ]}` ]                                                                        : true,
	// 		[ `fxt-t-xl-col-${columns[ 1 ]}` ]                                                                         : true,
	// 		[ `fxt-t-lg-col-${columns[ 2 ]}` ]                                                                         : true,
	// 		[ `fxt-t-md-col-${columns[ 3 ]}` ]                                                                         : true,
	// 		[ `fxt-t-sm-col-${columns[ 4 ]}` ]                                                                         : true,
	// 		[ `fxt-t-xs-col-${columns[ 5 ]}` ]                                                                         : true,
	// 		[ `fxt-blocks-testimonials-wrap${uniqueID}` ]: true,
	// 	} )
	// } );

	// return (
	// 	<div {...blockProps}>
	// 		{layout && layout === 'carousel' && (
	// 			<div className={`fxt-blocks-carousel fxt-carousel-container-dotstyle-${dotStyle} fxt-carousel-container-arrowstyle-${arrowStyle}`}>
	// 				<div className={`fxt-blocks-carousel-init fxt-carousel-arrowstyle-${arrowStyle} fxt-carousel-dotstyle-${dotStyle}`} data-columns-xxl={columns[ 0 ]} data-columns-xl={columns[ 1 ]}
	// 					 data-columns-md={columns[ 2 ]} data-columns-sm={columns[ 3 ]} data-columns-xs={columns[ 4 ]} data-columns-ss={columns[ 5 ]} data-slider-anim-speed={transSpeed}
	// 					 data-slider-scroll={slidesScroll} data-slider-arrows={( 'none' === arrowStyle ? false : true )} data-slider-dots={( 'none' === dotStyle ? false : true )}
	// 					 data-slider-hover-pause="false" data-slider-auto={autoPlay} data-slider-speed={autoSpeed}>
	// 					<InnerBlocks.Content/>
	// 				</div>
	// 			</div>
	// 		)}
	// 		{layout && layout !== 'carousel' && (
	// 			<div className={'fxt-testimonial-grid-wrap'} style={{
	// 				'grid-row-gap'   : columnGap + 'px',
	// 				'grid-column-gap': columnGap + 'px',
	// 			}}>
	// 				<InnerBlocks.Content/>
	// 			</div>
	// 		)}
	// 	</div>
	// );
	return <InnerBlocks.Content />;
}

export default Save;
