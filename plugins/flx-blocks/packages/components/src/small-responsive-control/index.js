/**
 * Responsive Range Component
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
import './editor.scss';

/**
 * Build the Measure controls
 * @returns {object} Measure settings.
 */
export default function SmallResponsiveControl({
	label,
	desktopChildren,
	tabletChildren,
	mobileChildren,
	hasPadding = false,
}) {
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
			title: <Dashicon icon="desktop" />,
			itemClass: 'fxb-desk-tab',
		},
		{
			name: 'Tablet',
			title: <Dashicon icon="tablet" />,
			itemClass: 'fxb-tablet-tab',
		},
		{
			name: 'Mobile',
			key: 'mobile',
			title: <Dashicon icon="smartphone" />,
			itemClass: 'fxb-mobile-tab',
		},
	];
	const output = {};
	output.Mobile = mobileChildren;
	output.Tablet = tabletChildren;
	output.Desktop = desktopChildren;
	return (
		<div
			className={`components-base-control fxb-small-responsive-control${
				hasPadding ? ' responsive-title-area-spacing' : ''
			}`}
		>
			<div className="flxbk-title-bar">
				{label && <span className="flxbk-control-title">{label}</span>}
				<ButtonGroup className="fxb-small-responsive-options" aria-label={__('Device', 'flx-blocks')}>
					{map(devices, ({ name, key, title, itemClass }) => (
						<Button
							key={key}
							className={`fxb-responsive-btn ${itemClass}${name === deviceType ? ' is-active' : ''}`}
							isSmall
							aria-pressed={deviceType === name}
							onClick={() => customSetPreviewDeviceType(name)}
						>
							{title}
						</Button>
					))}
				</ButtonGroup>
			</div>
			<div className="fxb-small-measure-control-inner">
				{output[deviceType] ? output[deviceType] : output.Desktop}
			</div>
		</div>
	);
}
