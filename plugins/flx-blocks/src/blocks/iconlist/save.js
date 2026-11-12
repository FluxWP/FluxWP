/**
 * BLOCK: Flux Spacer
 */

import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
/**
 * External dependencies
 */
import { FlxbkColorOutput } from '@flx-blocks/helpers';
import { times } from 'lodash';

function Save({ attributes }) {
	const { items, listCount, columns, blockAlignment, iconAlign, uniqueID, tabletColumns, mobileColumns } = attributes;

	const blockProps = useBlockProps.save({
		className: `wp-block-flx-iconlist fxt-svg-icon-list-items fxt-svg-icon-list-items${uniqueID} fxt-svg-icon-list-columns-${columns} align${
			blockAlignment ? blockAlignment : 'none'
		}${undefined !== iconAlign && 'middle' !== iconAlign ? ' fxt-list-icon-align' + iconAlign : ''}${
			undefined !== tabletColumns && '' !== tabletColumns
				? ' fxt-tablet-svg-icon-list-columns-' + tabletColumns
				: ''
		}${
			undefined !== mobileColumns && '' !== mobileColumns
				? ' fxt-mobile-svg-icon-list-columns-' + mobileColumns
				: ''
		}`,
	});
	return (
		<div {...blockProps}>
			<ul className="fxt-svg-icon-list">
				<InnerBlocks.Content />
			</ul>
		</div>
	);
}

export default Save;
