/**
 * Generic Responsive Component, add your own controls
 *
 */

/**
 * Internal block libraries
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { map } from 'lodash';
import { capitalizeFirstLetter } from '@flx-blocks/helpers';
import { Dashicon, Button, ButtonGroup } from '@wordpress/components';
/**
 * Import Css
 */
import './editor.scss';
/**
 * Build the Measure controls
 * @returns {object} Measure settings.
 */
export default function ResponsiveControl({ desktopChildren, tabletChildren, mobileChildren }) {
	const [deviceType, setDeviceType] = useState('Desktop');
	const theDevice = useSelect((select) => {
		return select('flxblocks/data').getPreviewDeviceType();
	}, []);
	if (theDevice !== deviceType) {
		setDeviceType(theDevice);
	}
	const { setPreviewDeviceType } = useDispatch('flxblocks/data');
	const customSetPreviewDeviceType = (device) => {
		setPreviewDeviceType(capitalizeFirstLetter(device));
		setDeviceType(capitalizeFirstLetter(device));
	};
	const devices = [
		{
			name: 'Desktop',
			key: 'desktop',
			label: __('Desktop', 'flx-blocks'),
			title: <Dashicon icon="desktop" />,
			itemClass: 'fxb-desk-tab',
		},
		{
			name: 'Tablet',
			key: 'tablet',
			label: __('Tablet', 'flx-blocks'),
			title: <Dashicon icon="tablet" />,
			itemClass: 'fxb-tablet-tab',
		},
		{
			name: 'Mobile',
			key: 'mobile',
			label: __('Mobile', 'flx-blocks'),
			title: <Dashicon icon="smartphone" />,
			itemClass: 'fxb-mobile-tab',
		},
	];
	const output = {};
	output.Mobile = mobileChildren;
	output.Tablet = tabletChildren;
	output.Desktop = desktopChildren;
	return (
		<div className={'components-base-control fxt-inspect-tabs fxb-responsive-control'}>
			<div className="flxbk-title-bar">
				<ButtonGroup className="components-tab-panel__tabs" aria-label={__('Device', 'flx-blocks')}>
					{map(devices, ({ name, key, title, itemClass, label }) => (
						<Button
							key={key}
							className={`components-tab-panel__tabs-item ${itemClass}${
								name === deviceType ? ' active-tab' : ''
							}`}
							aria-pressed={deviceType === name}
							aria-label={label}
							onClick={() => customSetPreviewDeviceType(name)}
						>
							{title}
						</Button>
					))}
				</ButtonGroup>
			</div>
			<div className="fxb-responsive-control-inner">
				{output[deviceType] ? output[deviceType] : output.Desktop}
			</div>
		</div>
	);
}
