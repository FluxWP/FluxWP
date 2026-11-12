/**
 * BLOCK: Flux Header Container for a size (Desktop or Tablet)
 */

/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * Internal block libraries
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useBlockProps, InnerBlocks, useInnerBlocksProps, InspectorControls } from '@wordpress/block-editor';

import { uniqueIdHelper } from '@flx-blocks/helpers';
import { SelectParentBlock } from '@flx-blocks/components';

/**
 * Internal dependencies
 */
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function ChildContainer(props, size = 'Desktop') {
	const { attributes, setAttributes, clientId } = props;

	const { uniqueID } = attributes;

	const { previewDevice } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
			};
		},
		[clientId]
	);

	uniqueIdHelper(props);

	const innerBlockClasses = classnames({
		['wp-block-flx-header-' + size.toLowerCase()]: true,
		'fxb-header-container': true,
		['wp-block-flx-header-' + size.toLowerCase() + uniqueID]: uniqueID,
	});

	const active = previewDevice === size || (previewDevice == 'Mobile' && size == 'Tablet');

	const { children, ...innerBlocksProps } = useInnerBlocksProps(
		{
			className: innerBlockClasses,
			style: {
				display: active ? 'block' : 'none',
			},
		},
		{
			renderAppender: false,
			templateLock: 'all',
		}
	);

	return (
		<>
			<InspectorControls>
				<SelectParentBlock
					label={__('View Header Settings', 'flx-blocks')}
					clientId={clientId}
					parentSlug={'flx/header'}
				/>
			</InspectorControls>
			<div {...innerBlocksProps}>{active && children}</div>
		</>
	);
}
