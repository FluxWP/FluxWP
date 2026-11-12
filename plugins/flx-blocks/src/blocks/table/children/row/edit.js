import { __ } from '@wordpress/i18n';
import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { useBlockProps, BlockControls, BlockContextProvider, useInnerBlocksProps } from '@wordpress/block-editor';
import metadata from './block.json';
import { flow } from 'lodash';
import { ToolbarDropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { createBlock } from '@wordpress/blocks';

import { plus } from '@wordpress/icons';

import {
	FlxbkPanelBody,
	InspectorControlTabs,
	FlxbkInspectorControls,
	CopyPasteAttributes,
	PopColorControl,
	SelectParentBlock,
	ResponsiveRangeControls,
} from '@flx-blocks/components';
import { uniqueIdHelper } from '@flx-blocks/helpers';

import classnames from 'classnames';
import BackendStyles from './backend-styles';

const createRowControls = (addRow) => [
	{
		title: __('Add Row Before', 'flx-blocks'),
		onClick: () => addRow('before'),
	},
	{
		title: __('Add Row After', 'flx-blocks'),
		onClick: () => addRow('after'),
	},
	{
		title: __('Add Row at Top', 'flx-blocks'),
		onClick: () => addRow('top'),
	},
	{
		title: __('Add Row at Bottom', 'flx-blocks'),
		onClick: () => addRow('bottom'),
	},
];

const innerBlockProps = {};
const innerBlockPropOptions = {
	allowedBlocks: ['flx/table-data'],
	renderAppender: false,
	templateInsertUpdatesSelection: true,
	templateLock: 'insert',
	orientation: 'horizontal',
};

export function Edit(props) {
	const { attributes, setAttributes, className, clientId, context } = props;

	const {
		uniqueID,
		backgroundColor,
		backgroundHoverColor,
		minHeight,
		tabletMinHeight,
		mobileMinHeight,
		minHeightType,
		row,
	} = attributes;

	const columns = context['flx/table/columns'];
	const isFirstRowHeader = context['flx/table/isFirstRowHeader'];

	const { insertBlock } = useDispatch('core/block-editor');
	const { previewDevice, index, parentClientId } = useSelect(
		(select) => {
			return {
				parentClientId: select('core/block-editor').getBlockParentsByBlockName(clientId, 'flx/table')[0],
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
				index: select('core/block-editor').getBlockIndex(clientId),
			};
		},
		[clientId]
	);

	useEffect(() => {
		if (index !== row) {
			setAttributes({ row: index });
		}
	}, [index]);

	const addRow = useCallback(
		(position) => {
			const insertIndex = {
				before: index,
				after: index + 1,
				top: 0,
				bottom: undefined,
			}[position];

			if (insertIndex !== undefined) {
				const newRow = createBlock('flx/table-row', {});
				insertBlock(newRow, insertIndex, parentClientId, false);
			}
		},
		[index, parentClientId, insertBlock]
	);

	const rowControls = useMemo(() => createRowControls(addRow), [addRow]);

	const [activeTab, setActiveTab] = useState('style');
	const { getBlocks } = useSelect((select) => select('core/block-editor'));
	const { replaceInnerBlocks } = useDispatch('core/block-editor');
	const thisRowIsHeader = useMemo(
		() => ({
			'flx/table/thisRowIsHeader': index === 0 && isFirstRowHeader,
		}),
		[index, isFirstRowHeader]
	);

	const nonTransAttrs = [];

	uniqueIdHelper(props);

	useEffect(() => {
		// Moved uniqueIdHelper outside of useEffect
	}, []);

	useEffect(() => {
		const innerBlocks = getBlocks(clientId);

		if (innerBlocks.length < columns) {
			const newBlocks = [
				...innerBlocks,
				...Array(Math.max(1, columns - innerBlocks.length))
					.fill(null)
					.map(() => createBlock('flx/table-data', {})),
			];
			replaceInnerBlocks(clientId, newBlocks, false);
		} else if (innerBlocks.length > columns) {
			// Remove excess blocks
			const updatedBlocks = innerBlocks.slice(0, columns);
			replaceInnerBlocks(clientId, updatedBlocks, false);
		}
	}, [columns]);

	const classNames = useMemo(
		() =>
			classnames({
				'fxb-table-row': true,
				[`fxb-table-row${uniqueID}`]: uniqueID,
			}),
		[uniqueID]
	);

	const blockProps = useBlockProps({
		className: classNames,
	});

	const { children } = useInnerBlocksProps(innerBlockProps, innerBlockPropOptions);

	return (
		<tr {...blockProps}>
			<BlockControls>
				<ToolbarDropdownMenu icon={plus} label={__('Add Row', 'flx-blocks')}>
					{({ onClose }) => (
						<>
							<MenuGroup>
								{rowControls.map((control) => (
									<MenuItem key={control.title} onClick={flow(onClose, control.onClick)}>
										{control.title}
									</MenuItem>
								))}
							</MenuGroup>
						</>
					)}
				</ToolbarDropdownMenu>
				<CopyPasteAttributes
					attributes={attributes}
					excludedAttrs={nonTransAttrs}
					defaultAttributes={metadata.attributes}
					blockSlug={metadata.name}
					onPaste={(attributesToPaste) => setAttributes(attributesToPaste)}
				/>
			</BlockControls>
			<FlxbkInspectorControls blockSlug={'flx/table-row'}>
				<SelectParentBlock
					label={__('View Table Settings', 'flx-blocks')}
					clientId={clientId}
					parentSlug={'flx/table'}
				/>
				<InspectorControlTabs
					panelName={'table-row'}
					allowedTabs={['general']}
					setActiveTab={setActiveTab}
					activeTab={activeTab}
				/>

				{activeTab === 'general' && (
					<>
						<FlxbkPanelBody
							title={__('Row Style', 'flx-blocks')}
							initialOpen={true}
							panelName={'table-row-style'}
						>
							<PopColorControl
								label={__('Row Background', 'flx-blocks')}
								value={backgroundColor ? backgroundColor : ''}
								default={''}
								onChange={(value) => setAttributes({ backgroundColor: value })}
							/>

							<PopColorControl
								label={__('Row Hover Background', 'flx-blocks')}
								value={backgroundHoverColor ? backgroundHoverColor : ''}
								default={''}
								onChange={(value) => setAttributes({ backgroundHoverColor: value })}
							/>
						</FlxbkPanelBody>

						<FlxbkPanelBody
							title={__('Row Height', 'flx-blocks')}
							initialOpen={true}
							panelName={'table-row-height'}
						>
							<ResponsiveRangeControls
								label={__('Height', 'flx-blocks')}
								value={minHeight}
								onChange={(value) => setAttributes({ minHeight: value })}
								tabletValue={tabletMinHeight}
								onChangeTablet={(value) => setAttributes({ tabletMinHeight: value })}
								mobileValue={mobileMinHeight}
								onChangeMobile={(value) => setAttributes({ mobileMinHeight: value })}
								min={0}
								max={minHeightType === 'px' ? 600 : 100}
								step={1}
								unit={minHeightType}
								onUnit={(value) => {
									setAttributes({ minHeightType: value });
								}}
								units={['px', 'em', 'vh']}
								reset={() =>
									setAttributes({
										minHeight: null,
										tabletMinHeight: null,
										mobileMinHeight: null,
									})
								}
								showUnit={true}
							/>
						</FlxbkPanelBody>
					</>
				)}
			</FlxbkInspectorControls>
			<BlockContextProvider value={thisRowIsHeader}>{children}</BlockContextProvider>
			<BackendStyles attributes={attributes} previewDevice={previewDevice} />
		</tr>
	);
}

export default Edit;
