import { __ } from '@wordpress/i18n';
import { plus } from '@wordpress/icons';
import { ToolbarDropdownMenu, MenuGroup, MenuItem, NavigableMenu } from '@wordpress/components';

const TABLE_ACTIONS = {
	ROW: {
		BEFORE: 'before',
		AFTER: 'after',
		TOP: 'top',
		BOTTOM: 'bottom',
	},
	COLUMN: {
		BEFORE: 'before',
		AFTER: 'after',
		START: 'start',
		END: 'end',
	},
};

const getControlConfigs = () => ({
	row: [
		{
			key: TABLE_ACTIONS.ROW.BEFORE,
			title: __('Add Row Before', 'flx-blocks'),
			position: TABLE_ACTIONS.ROW.BEFORE,
		},
		{
			key: TABLE_ACTIONS.ROW.AFTER,
			title: __('Add Row After', 'flx-blocks'),
			position: TABLE_ACTIONS.ROW.AFTER,
		},
		{
			key: TABLE_ACTIONS.ROW.TOP,
			title: __('Add Row at Top', 'flx-blocks'),
			position: TABLE_ACTIONS.ROW.TOP,
		},
		{
			key: TABLE_ACTIONS.ROW.BOTTOM,
			title: __('Add Row at Bottom', 'flx-blocks'),
			position: TABLE_ACTIONS.ROW.BOTTOM,
		},
	],
	column: [
		{
			key: TABLE_ACTIONS.COLUMN.BEFORE,
			title: __('Add Column Before', 'flx-blocks'),
			position: TABLE_ACTIONS.COLUMN.BEFORE,
		},
		{
			key: TABLE_ACTIONS.COLUMN.AFTER,
			title: __('Add Column After', 'flx-blocks'),
			position: TABLE_ACTIONS.COLUMN.AFTER,
		},
		{
			key: TABLE_ACTIONS.COLUMN.START,
			title: __('Add Column at Start', 'flx-blocks'),
			position: TABLE_ACTIONS.COLUMN.START,
		},
		{
			key: TABLE_ACTIONS.COLUMN.END,
			title: __('Add Column at End', 'flx-blocks'),
			position: TABLE_ACTIONS.COLUMN.END,
		},
	],
});

const TableControlsDropdown = ({ onAddRow, onAddColumn }) => {
	const controls = getControlConfigs();

	return (
		<ToolbarDropdownMenu icon={plus} label={__('Add Row or Column', 'flx-blocks')}>
			{({ onClose }) => (
				<NavigableMenu>
					<MenuGroup>
						{controls.row.map(({ key, title, position }) => (
							<MenuItem
								key={key}
								onClick={() => {
									onAddRow(position);
									onClose();
								}}
							>
								{title}
							</MenuItem>
						))}
					</MenuGroup>
					<MenuGroup>
						{controls.column.map(({ key, title, position }) => (
							<MenuItem
								key={key}
								onClick={() => {
									onAddColumn(position);
									onClose();
								}}
							>
								{title}
							</MenuItem>
						))}
					</MenuGroup>
				</NavigableMenu>
			)}
		</ToolbarDropdownMenu>
	);
};

export { TableControlsDropdown };
