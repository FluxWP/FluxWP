/**
 * BLOCK: Flux Advanced Heading
 */

/**
 * External dependencies
 */
import classnames from 'classnames';
import { getBlockDefaultClassName } from '@wordpress/blocks';
import { RichText, getColorClassName } from '@wordpress/block-editor';

function Save({ attributes }) {
	const {
		anchor,
		level,
		content,
		colorClass,
		uniqueID,
		className,
		flxbkAnimation,
		flxbkAOSOptions,
		htmlTag,
		link,
		linkNoFollow,
		linkSponsored,
		linkTarget,
		backgroundColorClass,
		linkStyle,
		icon,
		altTitle,
	} = attributes;
	const tagName = htmlTag && htmlTag !== 'heading' ? htmlTag : 'h' + level;
	const textColorClass = getColorClassName('color', colorClass);
	const textBackgroundColorClass = getColorClassName('background-color', backgroundColorClass);
	const revealAnimation =
		flxbkAnimation &&
		('reveal-left' === flxbkAnimation ||
			'reveal-right' === flxbkAnimation ||
			'reveal-up' === flxbkAnimation ||
			'reveal-down' === flxbkAnimation)
			? true
			: false;
	const wrapper = revealAnimation ? true : false;
	const classes = classnames({
		[`fxt-adv-heading${uniqueID}`]: uniqueID,
		[className]: !wrapper && !link && className,
		[getBlockDefaultClassName('flx/advancedheading')]: getBlockDefaultClassName('flx/advancedheading'),
		[textColorClass]: textColorClass,
		'has-text-color': textColorClass,
		[textBackgroundColorClass]: textBackgroundColorClass,
		'has-background': textBackgroundColorClass,
		[`hls-${linkStyle}`]: !link && linkStyle,
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
	const htmlItem = (
		<RichText.Content
			tagName={tagName}
			id={anchor ? anchor : undefined}
			className={classes}
			data-fxb-block={`fxb-adv-heading${uniqueID}`}
			data-aos={flxbkAnimation ? flxbkAnimation : undefined}
			data-aos-offset={
				flxbkAOSOptions && flxbkAOSOptions[0] && flxbkAOSOptions[0].offset
					? flxbkAOSOptions[0].offset
					: undefined
			}
			data-aos-duration={
				flxbkAOSOptions && flxbkAOSOptions[0] && flxbkAOSOptions[0].duration
					? flxbkAOSOptions[0].duration
					: undefined
			}
			data-aos-delay={
				flxbkAOSOptions && flxbkAOSOptions[0] && flxbkAOSOptions[0].delay
					? flxbkAOSOptions[0].delay
					: undefined
			}
			data-aos-easing={
				flxbkAOSOptions && flxbkAOSOptions[0] && flxbkAOSOptions[0].easing
					? flxbkAOSOptions[0].easing
					: undefined
			}
			data-aos-once={
				flxbkAOSOptions &&
				flxbkAOSOptions[0] &&
				undefined !== flxbkAOSOptions[0].once &&
				'' !== flxbkAOSOptions[0].once
					? flxbkAOSOptions[0].once
					: undefined
			}
			data-alt-title={altTitle ? altTitle : undefined}
			value={content}
		/>
	);
	const linkHTMLItem = (
		<a
			href={link}
			className={`fxb-advanced-heading-link fxt-adv-heading-link${uniqueID}${
				!wrapper && className ? ' ' + className : ''
			}${linkStyle ? ' hls-' + linkStyle : ''}`}
			target={linkTarget ? '_blank' : undefined}
			rel={relAttr ? relAttr : undefined}
		>
			{htmlItem}
		</a>
	);
	const readyContent = link ? linkHTMLItem : htmlItem;
	if (icon && '' !== icon) {
		return htmlItem;
	}
	return (
		<>
			{wrapper && (
				<div
					className={`fxb-adv-heading-wrap${uniqueID} flxbk-advanced-heading-wrapper${
						revealAnimation ? ' flxbk-heading-clip-animation' : ''
					}${className ? ' ' + className : ''}`}
				>
					{readyContent}
				</div>
			)}
			{!wrapper && readyContent}
		</>
	);
}

export default Save;
