/**
 * BLOCK: Flux Tab
 *
 * Registering a basic block with Gutenberg.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControlTabs, SelectParentBlock, FlxbkPanelBody } from '@flx-blocks/components';

import {
	InnerBlocks,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
	InspectorControls,
} from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { uniqueIdHelper } from '@flx-blocks/helpers';

/**
 * Build the spacer edit
 */
function FlxbkTab(props) {
	const { attributes, setAttributes, clientId } = props;
	const { id, uniqueID } = attributes;
	const [activeTab, setActiveTab] = useState('advanced');
	uniqueIdHelper(props);
	const hasChildBlocks = useSelect((select) => select(blockEditorStore).getBlocks(clientId).length > 0, [clientId]);
	const blockProps = useBlockProps({
		className: `fxt-tab-inner-content fxt-inner-tab-${id} fxt-inner-tab${uniqueID}`,
	});
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'fxt-tab-inner-content-inner',
		},
		{
			templateLock: false,
			renderAppender: hasChildBlocks ? undefined : InnerBlocks.ButtonBlockAppender,
		}
	);

	const dispatch = useDispatch('flxblocks/data').switchEditorTabOpened('tab' + clientId, 'advanced');

	return (
		<div {...blockProps} data-tab={id}>
			<InspectorControls>
				<SelectParentBlock clientId={clientId} />

				<InspectorControlTabs
					panelName={'tab'}
					setActiveTab={(value) => setActiveTab(value)}
					activeTab={activeTab}
					initialOpen={'advanced'}
					allowedTabs={['general', 'advanced']}
				/>
				{activeTab === 'general' && (
					<FlxbkPanelBody>
						<div className="components-base-control">{__('No general settings available.', 'flx-blocks')}</div>
					</FlxbkPanelBody>
				)}
			</InspectorControls>
			<div {...innerBlocksProps} />
		</div>
	);
}

export default FlxbkTab;
