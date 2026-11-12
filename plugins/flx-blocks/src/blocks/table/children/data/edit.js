import { __ } from '@wordpress/i18n';
import React, { useEffect, useMemo, useState, memo } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { BlockControls, useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import metadata from './block.json';
import { plus } from '@wordpress/icons';
import BackendStyles from './backend-styles';
import {
	CopyPasteAttributes,
	FlxbkInspectorControls,
	FlxbkPanelBody,
	InspectorControlTabs,
	SelectParentBlock,
	ResponsiveMeasureRangeControl,
} from '@flx-blocks/components';
import { uniqueIdHelper } from '@flx-blocks/helpers';
import { createBlock } from '@wordpress/blocks';
import { flow } from 'lodash';
import classnames from 'classnames';
import { ToolbarDropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { TableControlsDropdown } from './table-controls';

const DEFAULT_BLOCK = [['core/paragraph', {}]];
export function Edit(props) {
	const { attributes, setAttributes, className, clientId, context } = props;

	const { uniqueID, column, padding, tabletPadding, mobilePadding, paddingType } = attributes;

	const [activeTab, setActiveTab] = useState('general');

	const { replaceInnerBlocks } = useDispatch('core/block-editor');
	const { insertBlock, updateBlockAttributes } = useDispatch('core/block-editor');

	const { index, parentTableClientId, parentColumns, columnPosition, siblingRows, hasInnerBlocks, previewDevice } =
		useSelect(
			(select) => {
				const tableClientId =
					select('core/block-editor').getBlockParentsByBlockName(clientId, 'flx/table')[0] || null;
				const rowId =
					select('core/block-editor').getBlockParentsByBlockName(clientId, 'flx/table-row')[0] || null;
				const rowBlocks = rowId ? select('core/block-editor').getBlockOrder(rowId) : [];
				return {
					index: select('core/block-editor').getBlockIndex(clientId),
					parentTableClientId: tableClientId,
					parentColumns: tableClientId
						? select('core/block-editor').getBlockAttributes(tableClientId).columns
						: undefined,
					currentRowClientId: rowId || undefined,
					columnPosition: rowBlocks.indexOf(clientId),
					siblingRows: tableClientId ? select('core/block-editor').getBlocks(tableClientId) : [],
					hasInnerBlocks: select('core/block-editor').getBlocks(clientId).length > 0,
					previewDevice: select('flxblocks/data').getPreviewDeviceType(),
				};
			},
			[clientId]
		);

	useEffect(() => {
		if (columnPosition !== column) {
			setAttributes({ column: columnPosition });
		}
	}, [columnPosition]);

	useEffect(() => {
		if (!hasInnerBlocks) {
			const paragraph = createBlock('core/paragraph');
			replaceInnerBlocks(clientId, [paragraph], false);
		}
	}, [hasInnerBlocks]);

	const Tag =
		(index === 0 && context['flx/table/isFirstColumnHeader']) || context['flx/table/thisRowIsHeader']
			? 'th'
			: 'td';

	const classes = useMemo(
		() =>
			classnames({
				className: true,
				[`fxb-table-data${uniqueID}`]: uniqueID,
				'fxb-table-data': true,
			}),
		[uniqueID]
	);

	const blockProps = useBlockProps({
		className: classes,
	});

	uniqueIdHelper(props);

	useEffect(() => {
		// Moved uniqueIdHelper outside of useEffect
	}, []);

	const addRow = (position) => {
		let insertIndex;

		switch (position) {
			case 'before':
				insertIndex = index;
				break;
			case 'after':
				insertIndex = index + 1;
				break;
			case 'top':
				insertIndex = 0;
				break;
			case 'bottom':
				insertIndex = undefined;
				break;
			default:
				return;
		}

		const newRow = createBlock('flx/table-row', {});
		insertBlock(newRow, insertIndex, parentTableClientId, false);
	};

	const addColumn = (position) => {
		const currentColumns =
			typeof parentColumns === 'number' ? parentColumns : context?.['flx/table/columns'] || 0;
		const newColumnCount = currentColumns + 1;

		let insertIndex;
		switch (position) {
			case 'before':
				insertIndex = columnPosition;
				break;
			case 'after':
				insertIndex = columnPosition + 1;
				break;
			case 'start':
				insertIndex = 0;
				break;
			case 'end':
				insertIndex = parentColumns;
				break;
			default:
				return;
		}

		siblingRows.forEach((row) => {
			const newCells = [...row.innerBlocks];
			const newCell = createBlock('flx/table-data', {});
			newCells.splice(insertIndex, 0, newCell);

			replaceInnerBlocks(row.clientId, newCells, false);
			updateBlockAttributes(parentTableClientId, { columns: newColumnCount });
		});
	};

	return (
		<Tag {...blockProps}>
			<BackendStyles attributes={attributes} previewDevice={previewDevice} />
			<BlockControls>
				<TableControlsDropdown onAddRow={addRow} onAddColumn={addColumn} />
				<CopyPasteAttributes
					attributes={attributes}
					setAttributes={setAttributes}
					blockName={'flx/table-data'}
				/>
			</BlockControls>
			<FlxbkInspectorControls blockSlug={'flx/table-data'}>
				<SelectParentBlock
					label={__('View Row Settings', 'flx-blocks')}
					clientId={clientId}
					parentSlug={'flx/table-row'}
				/>
				<InspectorControlTabs
					panelName={'table-data'}
					allowedTabs={['general']}
					setActiveTab={(value) => setActiveTab(value)}
					activeTab={activeTab}
				/>

				{activeTab === 'general' && (
					<FlxbkPanelBody initialOpen={true}>
						<ResponsiveMeasureRangeControl
							label={__('Padding', 'flx-blocks')}
							value={padding}
							tabletValue={tabletPadding}
							mobileValue={mobilePadding}
							onChange={(value) => setAttributes({ padding: value })}
							onChangeTablet={(value) => setAttributes({ tabletPadding: value })}
							onChangeMobile={(value) => setAttributes({ mobilePadding: value })}
							min={paddingType === 'em' || paddingType === 'rem' ? -25 : -999}
							max={paddingType === 'em' || paddingType === 'rem' ? 25 : 999}
							step={paddingType === 'em' || paddingType === 'rem' ? 0.1 : 1}
							unit={paddingType}
							units={['px', 'em', 'rem', '%']}
							onUnit={(value) => setAttributes({ paddingType: value })}
						/>
					</FlxbkPanelBody>
				)}
			</FlxbkInspectorControls>
			<InnerBlocks template={DEFAULT_BLOCK} renderAppender={false} templateLock={false} />
		</Tag>
	);
}

export default Edit;
