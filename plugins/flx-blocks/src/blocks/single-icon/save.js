/**
 * BLOCK: Flux Spacer
 */

import { useBlockProps } from '@wordpress/block-editor';

/**
 * External dependencies
 */
import { IconSpanTag } from '@flx-blocks/components';
/**
 * External dependencies
 */
import classnames from 'classnames';

function Save({ attributes, className }) {
	const { icon, link, target, width, title, style, linkTitle, uniqueID, tooltip, tooltipPlacement, tooltipDash } =
		attributes;

	const classes = classnames({
		[`fxt-svg-style-${style}`]: style,
		'fxt-svg-icon-wrap': true,
		[`fxt-svg-item-${uniqueID}`]: uniqueID,
	});

	const blockProps = useBlockProps.save({
		className: classes,
	});
	const tooltipID = tooltip && uniqueID ? `fxt-svg-tooltip-${uniqueID}` : undefined;
	return (
		<div {...blockProps}>
			{icon && link && (
				<a
					href={link}
					className={`fxt-svg-icon-link${tooltipDash && tooltipID ? ' fxb-icon-tooltip-border' : ''}`}
					data-tooltip-id={tooltipID ? tooltipID : undefined}
					data-tooltip-placement={tooltipID && tooltipPlacement ? tooltipPlacement : undefined}
					target={'_blank' === target ? target : undefined}
					rel={'_blank' === target ? 'noopener noreferrer' : undefined}
					aria-label={undefined !== linkTitle && '' !== linkTitle ? linkTitle : undefined}
				>
					<IconSpanTag
						name={icon}
						strokeWidth={'fe' === icon.substring(0, 2) ? width : undefined}
						title={title ? title : ''}
					/>
				</a>
			)}
			{icon && !link && (
				<IconSpanTag
					extraClass={tooltipDash && tooltipID ? 'fxb-icon-tooltip-border' : undefined}
					name={icon}
					strokeWidth={'fe' === icon.substring(0, 2) ? width : undefined}
					title={title ? title : ''}
					tooltipID={tooltipID ? tooltipID : undefined}
					tooltipPlacement={tooltipPlacement ? tooltipPlacement : undefined}
				/>
			)}
			{tooltipID && (
				<span
					className={'fxb-tooltip-hidden-content'}
					style={{ display: 'none' }}
					id={tooltipID}
					dangerouslySetInnerHTML={{
						__html: tooltip, // Because this is saved into the post as html WordPress core will sanitize it if the user does not have the unfiltered_html capability.
					}}
				/>
			)}
		</div>
	);
}

export default Save;
