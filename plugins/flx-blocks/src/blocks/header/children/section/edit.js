/**
 * BLOCK: Flux Header section
 */

/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * Internal block libraries
 */
import { createBlock } from '@wordpress/blocks';
import { useSelect, useDispatch } from '@wordpress/data';
import { useBlockProps, InnerBlocks, useInnerBlocksProps, InspectorControls } from '@wordpress/block-editor';

import { uniqueIdHelper } from '@flx-blocks/helpers';
import { SelectParentBlock } from '@flx-blocks/components';

/**
 * Internal dependencies
 */
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export function Edit(props) {
	const { attributes, setAttributes, clientId } = props;

	const { uniqueID, location } = attributes;

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
		'wp-block-flx-header-section': true,
		[`wp-block-flx-header-section-${location}`]: location,
		[`wp-block-flx-header-section${uniqueID}`]: uniqueID,
	});

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: innerBlockClasses,
		},
		{
			templateLock: 'all',
		}
	);

	return (
		<>
			<InspectorControls>
				<SelectParentBlock
					label={__('View Header Row Settings', 'flx-blocks')}
					clientId={clientId}
					parentSlug={'flx/header-row'}
				/>
			</InspectorControls>
			<div {...innerBlocksProps} />
		</>
	);
}

export default Edit;
