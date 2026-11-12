/**
 * BLOCK: Flux Spacer
 */

import { useBlockProps } from '@wordpress/block-editor';

/**
 * External dependencies
 */
import classnames from 'classnames';
import { FlxbkColorOutput } from '@flx-blocks/helpers';
import SvgPattern from './svg-pattern';

function Save({ attributes }) {
	const {
		className,
		blockAlignment,
		dividerEnable,
		dividerStyle,
		hAlign,
		dividerColor,
		dividerOpacity,
		uniqueID,
		rotate,
		strokeWidth,
		strokeGap,
		tabletHAlign,
		mobileHAlign,
		vsdesk,
		vstablet,
		vsmobile,
	} = attributes;

	const innerSpacerClasses = classnames({
		'fxt-block-spacer': true,
		[`fxt-block-spacer-halign-${hAlign}`]: hAlign,
		[`fxt-block-spacer-thalign-${tabletHAlign}`]: tabletHAlign,
		[`fxt-block-spacer-malign-${mobileHAlign}`]: mobileHAlign,
	});

	const blockProps = useBlockProps.save({
		className: classnames(
			{
				[`align${blockAlignment ? blockAlignment : 'none'}`]: true,
				[`fxt-block-spacer-${uniqueID}`]: uniqueID,
				'fxvs-lg-false': vsdesk !== 'undefined' && vsdesk,
				'fxvs-md-false': vstablet !== 'undefined' && vstablet,
				'fxvs-sm-false': vsmobile !== 'undefined' && vsmobile,
			},
			className
		),
	});

	return (
		<div {...blockProps}>
			<div className={innerSpacerClasses}>
				{dividerEnable && (
					<>
						{dividerStyle === 'stripe' && (
							<span className="fxt-divider-stripe">
								<SvgPattern
									uniqueID={uniqueID}
									color={FlxbkColorOutput(dividerColor)}
									opacity={dividerOpacity}
									rotate={rotate}
									strokeWidth={strokeWidth}
									strokeGap={strokeGap}
								/>
							</span>
						)}
						{dividerStyle !== 'stripe' && <hr className="fxt-divider" />}
					</>
				)}
			</div>
		</div>
	);
}

export default Save;
