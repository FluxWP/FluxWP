/**
 * BLOCK: Flux Accordion
 */
import classnames from 'classnames';

import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

function FlxbkAccordionSave({ attributes }) {
	const {
		uniqueID,
		paneCount,
		align,
		maxWidth,
		titleAlignment,
		startCollapsed,
		linkPaneCollapse,
		showIcon,
		iconStyle,
		iconSide,
		openPane,
	} = attributes;
	const classes = classnames(`align${align ? align : 'none'}`);
	const innerClasses = classnames(
		`fxt-accordion-wrap fxt-accordion-id${uniqueID} fxt-accordion-has-${paneCount}-panes fxt-active-pane-${openPane} fxt-accordion-block fxt-pane-header-alignment-${
			titleAlignment ? titleAlignment : 'left'
		} fxt-accodion-icon-style-${iconStyle && showIcon ? iconStyle : 'none'} fxt-accodion-icon-side-${
			iconSide ? iconSide : 'right'
		}`
	);

	const blockProps = useBlockProps.save({
		className: classes,
	});

	return (
		<div {...blockProps}>
			<div
				className={innerClasses}
				style={{
					maxWidth: maxWidth ? maxWidth + 'px' : 'none',
				}}
			>
				<div
					className="fxt-accordion-inner-wrap"
					data-allow-multiple-open={!linkPaneCollapse ? 'true' : 'false'}
					data-start-open={!startCollapsed ? openPane : 'none'}
				>
					<InnerBlocks.Content />
				</div>
			</div>
		</div>
	);
}

export default FlxbkAccordionSave;
