/**
 * BLOCK: Flux Icon
 */
/**
 * Import Controls
 */
import classnames from 'classnames';
/**
 * Import externals
 */
import {
	FlxbkPanelBody,
	InspectorControlTabs,
	ResponsiveAlignControls,
	FlxbkInspectorControls,
	ResponsiveGapSizeControl,
} from '@flx-blocks/components';
import { getPreviewSize, uniqueIdHelper, getInQueryBlock, getGapSizeOptionOutput } from '@flx-blocks/helpers';
import { useSelect, useDispatch, withDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
/**
 * Import Css
 */
import './editor.scss';
import metadata from './block.json';
import { isEqual } from 'lodash';
import { migrateToInnerblocks } from './utils';
/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';

import {
	BlockControls,
	BlockAlignmentToolbar,
	BlockVerticalAlignmentControl,
	JustifyContentControl,
	useInnerBlocksProps,
	useBlockProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useEffect, useState } from '@wordpress/element';
import { plusCircle } from '@wordpress/icons';
import { ToolbarButton, ToolbarGroup, ToggleControl } from '@wordpress/components';
import { useIsSiteStylesPanel } from '../../site-styles/use-is-site-styles-panel';

function FlxbkIcons(props) {
	const { attributes, className, setAttributes, iconsBlock, insertIcon, insertIcons, clientId, context } = props;
	const {
		inQueryBlock,
		icons,
		blockAlignment,
		textAlignment,
		tabletTextAlignment,
		mobileTextAlignment,
		wrapIcons,
		uniqueID,
		verticalAlignment,
		gap,
		gapUnit,
	} = attributes;

	const [activeTab, setActiveTab] = useState('general');

	const { removeBlock } = useDispatch('core/block-editor');
	const { previewDevice } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
			};
		},
		[clientId]
	);

	useEffect(() => {
		setAttributes({ inQueryBlock: getInQueryBlock(context, inQueryBlock) });
	}, []);

	uniqueIdHelper(props);
	const isSiteStylesPanel = useIsSiteStylesPanel();

	useEffect(() => {
		if (uniqueID && !iconsBlock.innerBlocks.length) {
			if (
				icons?.length &&
				undefined !== metadata?.attributes?.icons?.default &&
				!isEqual(metadata.attributes.icons.default, icons)
			) {
				const migrateUpdate = migrateToInnerblocks(attributes);
				setAttributes(migrateUpdate[0]);
				insertIcons(migrateUpdate[1]);
			} else {
				// Delete if no inner blocks.
				removeBlock(clientId, true);
			}
		}
	}, [iconsBlock.innerBlocks.length]);

	const blockProps = useBlockProps({
		className,
		'data-align':
			'left' === blockAlignment || 'right' === blockAlignment || 'center' === blockAlignment
				? blockAlignment
				: undefined,
	});
	const previewGap = getPreviewSize(
		previewDevice,
		undefined !== gap?.[0] ? gap[0] : '',
		undefined !== gap?.[1] ? gap[1] : '',
		undefined !== gap?.[2] ? gap[2] : ''
	);
	const previewVerticalAlignment = verticalAlignment && 'middle' === verticalAlignment ? 'center' : verticalAlignment;
	const previewTextAlign = getPreviewSize(
		previewDevice,
		textAlignment ? textAlignment : undefined,
		undefined !== tabletTextAlignment && tabletTextAlignment ? tabletTextAlignment : undefined,
		undefined !== mobileTextAlignment && mobileTextAlignment ? mobileTextAlignment : undefined
	);
	const innerClasses = classnames({
		'fxt-svg-icons': true,
		[`fxt-svg-icons-${uniqueID}`]: uniqueID,
		[`fxb-icon-halign-${previewTextAlign}`]: previewTextAlign,
		[`fxb-icon-valign-${previewVerticalAlignment}`]: previewVerticalAlignment,
		[`fxb-icon-wrap`]: wrapIcons,
	});
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: innerClasses,
			style: {
				gap: '' !== previewGap ? getGapSizeOptionOutput(previewGap, gapUnit ? gapUnit : 'px') : undefined,
			},
		},
		{
			allowedBlocks: ['flx/single-icon'],
			orientation: 'horizontal',
			templateLock: false,
			template: [['flx/single-icon']],
			renderAppender: false,
			templateInsertUpdatesSelection: true,
		}
	);
	return (
		<div {...blockProps}>
			<BlockControls>
				<BlockAlignmentToolbar
					value={blockAlignment}
					controls={['left', 'right']}
					onChange={(value) => setAttributes({ blockAlignment: value })}
				/>
				<ToolbarGroup>
					<JustifyContentControl
						value={previewTextAlign}
						onChange={(value) => {
							if (previewDevice === 'Mobile') {
								setAttributes({ mobileTextAlignment: value ? value : '' });
							} else if (previewDevice === 'Tablet') {
								setAttributes({ tabletTextAlignment: value ? value : '' });
							} else {
								setAttributes({ textAlignment: value ? value : 'center' });
							}
						}}
					/>
					<BlockVerticalAlignmentControl
						value={previewVerticalAlignment || 'center'}
						onChange={(value) => setAttributes({ verticalAlignment: value })}
					/>
				</ToolbarGroup>
				<ToolbarGroup>
					<ToolbarButton
						className="fxb-icons-add-icon"
						icon={plusCircle}
						onClick={() => {
							const prevAttributes = iconsBlock.innerBlocks[iconsBlock.innerBlocks.length - 1].attributes;
							const latestAttributes = JSON.parse(JSON.stringify(prevAttributes));
							latestAttributes.uniqueID = '';
							const newBlock = createBlock('flx/single-icon', latestAttributes);
							insertIcon(newBlock);
						}}
						label={__('Add Another Icon', 'flx-blocks')}
						showTooltip={true}
					/>
				</ToolbarGroup>
			</BlockControls>
			<FlxbkInspectorControls blockSlug={'flx/icon'}>
				<InspectorControlTabs
					panelName={'icon'}
					allowedTabs={['general', 'advanced']}
					setActiveTab={(value) => setActiveTab(value)}
					activeTab={activeTab}
				/>

				{activeTab === 'general' && (
					<FlxbkPanelBody panelName={'fxb-icon-alignment-settings'}>
						<ResponsiveAlignControls
							label={__('Icon Alignment', 'flx-blocks')}
							value={textAlignment ? textAlignment : 'center'}
							mobileValue={mobileTextAlignment ? mobileTextAlignment : ''}
							tabletValue={tabletTextAlignment ? tabletTextAlignment : ''}
							onChange={(nextAlign) => setAttributes({ textAlignment: nextAlign ? nextAlign : 'center' })}
							onChangeTablet={(nextAlign) =>
								setAttributes({ tabletTextAlignment: nextAlign ? nextAlign : '' })
							}
							onChangeMobile={(nextAlign) =>
								setAttributes({ mobileTextAlignment: nextAlign ? nextAlign : '' })
							}
							type={'justify'}
						/>
						<ToggleControl
							label={__('Wrap Icons?', 'flx-blocks')}
							checked={wrapIcons}
							onChange={(value) => {
								setAttributes({ wrapIcons: value });
							}}
						/>
						{/* The panel's block has one inner block, and its gap is a site value all the same. */}
						{(isSiteStylesPanel || iconsBlock?.innerBlocks?.length > 1) && (
							<ResponsiveGapSizeControl
								label={__('Icons Gap', 'flx-blocks')}
								value={undefined !== gap?.[0] ? gap[0] : ''}
								onChange={(value) =>
									setAttributes({
										gap: [
											value,
											undefined !== gap[1] ? gap[1] : '',
											undefined !== gap[2] ? gap[2] : '',
										],
									})
								}
								tabletValue={undefined !== gap?.[1] ? gap[1] : ''}
								onChangeTablet={(value) =>
									setAttributes({
										gap: [
											undefined !== gap[0] ? gap[0] : '',
											value,
											undefined !== gap[2] ? gap[2] : '',
										],
									})
								}
								mobileValue={undefined !== gap?.[2] ? gap[2] : ''}
								onChangeMobile={(value) =>
									setAttributes({
										gap: [
											undefined !== gap[0] ? gap[0] : '',
											undefined !== gap[1] ? gap[1] : '',
											value,
										],
									})
								}
								min={0}
								max={gapUnit === 'px' ? 200 : 12}
								step={gapUnit === 'px' ? 1 : 0.1}
								unit={gapUnit ? gapUnit : 'px'}
								onUnit={(value) => {
									setAttributes({ gapUnit: value });
								}}
								units={['px', 'em', 'rem']}
							/>
						)}
					</FlxbkPanelBody>
				)}
			</FlxbkInspectorControls>
			<div {...innerBlocksProps} />
		</div>
	);
}
const FlxbkIconsWrapper = withDispatch((dispatch, ownProps, registry) => ({
	insertIcon(newBlock) {
		const { clientId } = ownProps;
		const { insertBlock } = dispatch(blockEditorStore);
		const { getBlock } = registry.select(blockEditorStore);
		const block = getBlock(clientId);
		console.log(clientId);
		console.log(parseInt(block.innerBlocks.length));
		insertBlock(
			newBlock,
			undefined !== block?.innerBlocks?.length ? parseInt(block.innerBlocks.length) : 0,
			clientId
		);
	},
	insertIcons(newBlocks) {
		const { clientId } = ownProps;
		const { replaceInnerBlocks } = dispatch(blockEditorStore);

		replaceInnerBlocks(clientId, newBlocks);
	},
}))(FlxbkIcons);
const FlxbkIconsEdit = (props) => {
	const { clientId } = props;
	const { iconsBlock } = useSelect(
		(select) => {
			const { getBlock } = select('core/block-editor');
			const block = getBlock(clientId);
			return {
				iconsBlock: block,
			};
		},
		[clientId]
	);
	return <FlxbkIconsWrapper iconsBlock={iconsBlock} {...props} />;
};
export default FlxbkIconsEdit;
