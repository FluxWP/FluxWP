/**
 * BLOCK: Flux Tab
 *
 * Registering a basic block with Gutenberg.
 */
import classnames from 'classnames';

import {
	FlxbkPanelBody,
	FlxbkIconPicker,
	IconRender,
	SelectParentBlock,
	InspectorControlTabs,
} from '@flx-blocks/components';
import { uniqueIdHelper } from '@flx-blocks/helpers';

import { __ } from '@wordpress/i18n';
import { ToggleControl, SelectControl, TextControl } from '@wordpress/components';
import {
	RichText,
	InnerBlocks,
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';

/**
 * Build the Pane edit.
 */
function PaneEdit(props) {
	const { attributes, setAttributes, clientId } = props;
	const { id, uniqueID, title, icon, iconSide, hideLabel, titleTag, ariaLabel } = attributes;
	const HtmlTagOut = !titleTag ? 'div' : titleTag;
	const [activePane, setActivePane] = useState(false);
	const [activeTab, setActiveTab] = useState('general');
	const { addUniquePane } = useDispatch('flxblocks/data');
	const { isUniquePane, isUniquePaneBlock } = useSelect(
		(select) => {
			return {
				isUniquePane: (value, rootID) => select('flxblocks/data').isUniquePane(value, rootID),
				isUniquePaneBlock: (value, clientId, rootID) =>
					select('flxblocks/data').isUniquePaneBlock(value, clientId, rootID),
			};
		},
		[clientId]
	);
	const { accordionBlock, rootID } = useSelect(
		(select) => {
			const { getBlockRootClientId, getBlocksByClientId } = select(blockEditorStore);
			const rootID = getBlockRootClientId(clientId);
			const accordionBlock = getBlocksByClientId(rootID);
			return {
				accordionBlock: undefined !== accordionBlock ? accordionBlock : '',
				rootID: undefined !== rootID ? rootID : '',
			};
		},
		[clientId]
	);
	const { hasInnerBlocks } = useSelect(
		(select) => {
			const { getBlock } = select(blockEditorStore);
			const block = getBlock(clientId);
			return {
				hasInnerBlocks: !!(block && block.innerBlocks.length),
			};
		},
		[clientId]
	);
	const { updateBlockAttributes } = useDispatch(blockEditorStore);
	const updatePaneCount = (value) => {
		updateBlockAttributes(rootID, { paneCount: value });
	};

	uniqueIdHelper(props);

	useEffect(() => {
		// Moved uniqueIdHelper outside of useEffect
		if (!id) {
			const newPaneCount = accordionBlock[0].attributes.paneCount + 1;
			setAttributes({
				id: newPaneCount,
			});
			if (!isUniquePane(newPaneCount, rootID)) {
				addUniquePane(newPaneCount, clientId, rootID);
			}
			updatePaneCount(newPaneCount);
		} else if (!isUniquePane(id, rootID)) {
			// This checks if we are just switching views, client ID the same means we don't need to update.
			if (!isUniquePaneBlock(id, clientId, rootID)) {
				const newPaneCount = accordionBlock[0].attributes.paneCount + 1;
				setAttributes({
					id: newPaneCount,
				});
				addUniquePane(newPaneCount, clientId, rootID);
				updatePaneCount(newPaneCount);
			}
		} else {
			addUniquePane(id, clientId, rootID);
		}
		const isStartCollapsed =
			undefined !== accordionBlock?.[0]?.attributes?.startCollapsed &&
			accordionBlock[0].attributes.startCollapsed;
		const isOpenPane =
			!isStartCollapsed &&
			undefined !== accordionBlock?.[0]?.attributes?.openPane &&
			accordionBlock[0].attributes.openPane + 1 === id;
		const isNewPane = !uniqueID;
		setActivePane((activePane ?? isNewPane) ? true : isOpenPane);
	}, []);
	const blockClasses = classnames({
		'fxt-accordion-pane': true,
		[`fxt-accordion-pane-${id}`]: id,
		[`fxt-pane${uniqueID}`]: uniqueID,
		[`fxt-accordion-panel-active`]: activePane,
	});
	const blockProps = useBlockProps({
		className: blockClasses,
	});
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'fxt-accordion-panel-inner',
		},
		{
			templateLock: false,
			renderAppender: hasInnerBlocks ? undefined : InnerBlocks.ButtonBlockAppender,
		}
	);
	return (
		<div {...blockProps}>
			<InspectorControls>
				<SelectParentBlock clientId={clientId} />

				<InspectorControlTabs
					panelName={'pane'}
					setActiveTab={(value) => setActiveTab(value)}
					activeTab={activeTab}
					allowedTabs={['general', 'advanced']}
				/>
				{activeTab === 'general' && (
					<>
						<FlxbkPanelBody
							title={__('Title Icon Settings', 'flx-blocks')}
							initialOpen={true}
							panelName={'fxb-pane-title-icon'}
						>
							<FlxbkIconPicker
								value={icon}
								onChange={(value) => setAttributes({ icon: value })}
								allowClear={true}
							/>
							<SelectControl
								label={__('Icon Side', 'flx-blocks')}
								value={iconSide}
								options={[
									{ value: 'right', label: __('Right', 'flx-blocks') },
									{ value: 'left', label: __('Left', 'flx-blocks') },
								]}
								onChange={(value) => setAttributes({ iconSide: value })}
							/>
							<ToggleControl
								label={__('Show only Icon', 'flx-blocks')}
								checked={hideLabel}
								onChange={(value) => setAttributes({ hideLabel: value })}
							/>
							<TextControl
								label={__('Button Label Attribute for Accessibility', 'flx-blocks')}
								value={ariaLabel}
								onChange={(value) => setAttributes({ ariaLabel: value })}
							/>
						</FlxbkPanelBody>
					</>
				)}
			</InspectorControls>
			<HtmlTagOut className={`fxt-accordion-header-wrap`}>
				<div
					className={`fxt-blocks-accordion-header fxt-acccordion-button-label-${hideLabel ? 'hide' : 'show'}`}
					onClick={() => {
						setActivePane(!activePane);
					}}
				>
					<div className="fxt-blocks-accordion-title-wrap">
						{icon && 'left' === iconSide && (
							<IconRender
								className={`fxt-btn-svg-icon fxt-btn-svg-icon-${icon} fxt-btn-side-${iconSide}`}
								name={icon}
							/>
						)}
						<RichText
							className="fxt-blocks-accordion-title"
							tagName={'div'}
							placeholder={__('Add Title', 'flx-blocks')}
							onChange={(value) => setAttributes({ title: value })}
							value={title}
							keepPlaceholderOnFocus
							onClick={(e) => {
								e.stopPropagation();
							}}
						/>
						{icon && 'right' === iconSide && (
							<IconRender
								className={`fxt-btn-svg-icon fxt-btn-svg-icon-${icon} fxt-btn-side-${iconSide}`}
								name={icon}
							/>
						)}
					</div>
					<div className="fxt-blocks-accordion-icon-trigger"></div>
				</div>
			</HtmlTagOut>
			<div className={'fxt-accordion-panel'}>
				<div {...innerBlocksProps}></div>
			</div>
		</div>
	);
}
export default PaneEdit;
