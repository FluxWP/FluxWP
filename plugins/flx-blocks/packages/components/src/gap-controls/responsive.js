/**
 * Responsive Range Component
 *
 */

/**
 * Internal block libraries
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { map, isEqual } from 'lodash';
import GapSizeControl from './index';
import { capitalizeFirstLetter, GAP_SIZES_MAP } from '@flx-blocks/helpers';
import { undo, settings, link, linkOff } from '@wordpress/icons';
import { Dashicon, Button, DropdownMenu, ButtonGroup } from '@wordpress/components';
/**
 * Build the Measure controls
 * @returns {object} Measure settings.
 */
export default function ResponsiveGapSizeControl({
	label,
	onChange,
	onChangeTablet,
	onChangeMobile,
	mobileValue,
	tabletValue,
	value,
	units = ['px', 'em', 'rem'],
	onUnit,
	options = GAP_SIZES_MAP,
	step = 1,
	max = 200,
	min = 0,
	unit = 'px',
	defaultValue = ['', '', ''],
	reset = true,
	radio = true,
	compressedDevice = false,
}) {
	const ref = useRef();
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
	const currentValue = [value, tabletValue, mobileValue];
	const onReset = () => {
		if (typeof reset === 'function') {
			reset();
		} else {
			if (deviceType === 'Mobile') {
				onChangeMobile(defaultValue[2]);
			} else if (deviceType === 'Tablet') {
				onChangeTablet(defaultValue[1]);
			} else {
				onChange(defaultValue[0]);
			}
		}
	};
	const output = {};
	output.Mobile = (
		<GapSizeControl
			value={mobileValue ? mobileValue : undefined}
			onChange={(size) => onChangeMobile(size)}
			options={options}
			onUnit={onUnit}
			defaultValue={defaultValue[2]}
			units={units}
			step={step}
			max={max}
			min={min}
			unit={unit}
			radio={radio}
		/>
	);
	output.Tablet = (
		<GapSizeControl
			value={tabletValue ? tabletValue : undefined}
			onChange={(size) => onChangeTablet(size)}
			options={options}
			onUnit={onUnit}
			defaultValue={defaultValue[1]}
			units={units}
			step={step}
			max={max}
			min={min}
			unit={unit}
			radio={radio}
		/>
	);
	output.Desktop = (
		<GapSizeControl
			value={value ? value : undefined}
			onChange={(size) => onChange(size)}
			options={options}
			onUnit={onUnit}
			defaultValue={defaultValue[0]}
			units={units}
			step={step}
			max={max}
			min={min}
			unit={unit}
			radio={radio}
		/>
	);
	const icons = {
		Desktop: <Dashicon icon="desktop" />,
		Tablet: <Dashicon icon="tablet" />,
		Mobile: <Dashicon icon="smartphone" />,
	};
	const createLevelControlToolbar = (mappedDevice) => {
		return [
			{
				title: mappedDevice.name,
				icon: mappedDevice.title,
				isActive: deviceType === mappedDevice.name,
				onClick: () => {
					customSetPreviewDeviceType(mappedDevice.name);
				},
			},
		];
	};
	return [
		onChange && onChangeTablet && onChangeMobile && (
			<div
				ref={ref}
				className={'components-base-control fxb-responsive-gap-size-control flxbk-gap-size-control'}
			>
				<div className={'flxbk-gap-size-control__header flxbk-component__header'}>
					{label && (
						<div className="flxbk-component__header__title flxbk-gap-size__title">
							<label className="components-base-control__label">{label}</label>
							{reset && (
								<div className="title-reset-wrap">
									<Button
										className="is-reset is-single"
										label="reset"
										isSmall
										disabled={isEqual(defaultValue, currentValue) ? true : false}
										icon={undo}
										onClick={() => onReset()}
									/>
								</div>
							)}
						</div>
					)}
					{!compressedDevice && (
						<ButtonGroup
							className="fxb-responsive-options fxb-measure-responsive-options"
							aria-label={__('Device', 'flx-blocks')}
						>
							{map(devices, ({ name, key, title, itemClass }) => (
								<Button
									key={key}
									className={`fxb-responsive-btn ${itemClass}${
										name === deviceType ? ' is-active' : ''
									}`}
									isSmall
									aria-pressed={deviceType === name}
									onClick={() => customSetPreviewDeviceType(name)}
								>
									{title}
								</Button>
							))}
						</ButtonGroup>
					)}
					{compressedDevice && (
						<DropdownMenu
							className="fxb-responsive-options-dropdown"
							icon={icons[deviceType] ? icons[deviceType] : icons.Desktop}
							label={__('Target Device', 'flx-blocks')}
							controls={devices.map((singleDevice) => createLevelControlToolbar(singleDevice))}
						/>
					)}
				</div>
				<div className="fxb-responsive-border-control-inner">
					{output[deviceType] ? output[deviceType] : output.Desktop}
				</div>
			</div>
		),
	];
}
