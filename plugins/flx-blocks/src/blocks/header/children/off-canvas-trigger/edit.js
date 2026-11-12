/**
 * BLOCK: Flux Off Canvas Trigger
 */

/**
 * Import Css
 */
import './editor.scss';
import './style.scss';

/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * Internal block libraries
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';

import { uniqueIdHelper, getPreviewSize } from '@flx-blocks/helpers';
import {
	SelectParentBlock,
	IconRender,
	FlxbkIconPicker,
	FlxbkPanelBody,
	PopColorControl,
	SmallResponsiveControl,
	ResponsiveMeasureRangeControl,
	ResponsiveRangeControls,
	InspectorControlTabs,
	HoverToggleControl,
	ResponsiveBorderControl,
	ResponsiveMeasurementControls,
} from '@flx-blocks/components';
import { TextControl, RangeControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import BackendStyles from './components/backend-styles';

export function Edit(props) {
	const { attributes, setAttributes, clientId } = props;

	const {
		uniqueID,
		icon,
		iconSize,
		iconSizeTablet,
		iconSizeMobile,
		lineWidth,
		iconColor,
		iconColorTablet,
		iconColorMobile,
		iconColorHover,
		iconColorHoverTablet,
		iconColorHoverMobile,
		iconBackgroundColor,
		iconBackgroundColorTablet,
		iconBackgroundColorMobile,
		iconBackgroundColorHover,
		iconBackgroundColorHoverTablet,
		iconBackgroundColorHoverMobile,
		label,
		padding,
		paddingTablet,
		paddingMobile,
		paddingUnit,
		margin,
		marginTablet,
		marginMobile,
		marginUnit,
		border,
		borderTablet,
		borderMobile,
		borderHover,
		borderHoverTablet,
		borderHoverMobile,
		borderRadius,
		borderRadiusTablet,
		borderRadiusMobile,
		borderRadiusUnit,
	} = attributes;

	const [activeTab, setActiveTab] = useState('general');
	const { previewDevice } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
			};
		},
		[clientId]
	);

	uniqueIdHelper(props);

	const styleColorControls = (size = '', suffix = '') => {
		const iconColorValue = attributes['iconColor' + suffix + size];
		const iconBackgroundColorValue = attributes['iconBackgroundColor' + suffix + size];
		return (
			<>
				<PopColorControl
					label={__('Color', 'flx-blocks')}
					value={iconColorValue}
					default={''}
					onChange={(value) => setAttributes({ ['iconColor' + suffix + size]: value })}
					key={'normal'}
				/>
				<PopColorControl
					label={__('Background', 'flx-blocks')}
					value={iconBackgroundColorValue}
					default={''}
					onChange={(value) => setAttributes({ ['iconBackgroundColor' + suffix + size]: value })}
					key={'normalb'}
				/>
			</>
		);
	};

	const previewIconSize = getPreviewSize(previewDevice, iconSize, iconSizeTablet, iconSizeMobile);

	const classes = classnames('wp-block-flx-off-canvas-trigger', {
		[`wp-block-flx-off-canvas-trigger${uniqueID}`]: uniqueID,
	});

	const blockProps = useBlockProps({
		className: classes,
	});

	return (
		<button {...blockProps}>
			<InspectorControls>
				<SelectParentBlock
					label={__('View Header Settings', 'flx-blocks')}
					clientId={clientId}
					parentSlug={'flx/header'}
				/>
				<InspectorControlTabs panelName={'off-canvas'} setActiveTab={setActiveTab} activeTab={activeTab} />
				{activeTab === 'general' && (
					<>
						<FlxbkPanelBody panelName={'fxb-off-canvas-icon'}>
							<FlxbkIconPicker
								value={icon}
								onChange={(value) => setAttributes({ icon: value })}
								allowClear={true}
							/>

							<ResponsiveRangeControls
								label={__('Icon Size', 'flx-blocks')}
								value={iconSize}
								tabletValue={iconSizeTablet ? iconSizeTablet : ''}
								mobileValue={iconSizeMobile ? iconSizeMobile : ''}
								onChange={(value) => setAttributes({ iconSize: value })}
								onChangeTablet={(value) => setAttributes({ iconSizeTablet: value })}
								onChangeMobile={(value) => setAttributes({ iconSizeMobile: value })}
								units={['px']}
								unit={'px'}
								min={5}
								max={250}
								step={1}
							/>
							{icon && 'fe' === icon.substring(0, 2) && (
								<RangeControl
									label={__('Icon Line Width', 'flx-blocks')}
									value={lineWidth}
									onChange={(value) => setAttributes({ lineWidth: value })}
									step={0.5}
									min={0.5}
									max={4}
								/>
							)}
							<TextControl
								label={__('Button Label Attribute for Accessibility', 'flx-blocks')}
								value={label}
								onChange={(value) => setAttributes({ label: value })}
							/>
						</FlxbkPanelBody>
					</>
				)}
				{activeTab === 'style' && (
					<>
						<FlxbkPanelBody>
							<HoverToggleControl
								normal={
									<>
										<SmallResponsiveControl
											label={__('Trigger Colors', 'flx-blocks')}
											desktopChildren={styleColorControls()}
											tabletChildren={styleColorControls('Tablet')}
											mobileChildren={styleColorControls('Mobile')}
											key={'normalc'}
										></SmallResponsiveControl>
										<ResponsiveBorderControl
											label={__('Border', 'flx-blocks')}
											value={border}
											tabletValue={borderTablet}
											mobileValue={borderMobile}
											onChange={(value) => setAttributes({ border: value })}
											onChangeTablet={(value) => setAttributes({ borderTablet: value })}
											onChangeMobile={(value) => setAttributes({ borderMobile: value })}
											key={'normalbr'}
										/>
									</>
								}
								hover={
									<>
										<SmallResponsiveControl
											label={__('Hover Colors', 'flx-blocks')}
											desktopChildren={styleColorControls('', 'Hover')}
											tabletChildren={styleColorControls('Tablet', 'Hover')}
											mobileChildren={styleColorControls('Mobile', 'Hover')}
											key={'hoverc'}
										></SmallResponsiveControl>
										<ResponsiveBorderControl
											label={__('Hover Border', 'flx-blocks')}
											value={borderHover}
											tabletValue={borderHoverTablet}
											mobileValue={borderHoverMobile}
											onChange={(value) => setAttributes({ borderHover: value })}
											onChangeTablet={(value) => setAttributes({ borderHoverTablet: value })}
											onChangeMobile={(value) => setAttributes({ borderHoverMobile: value })}
											key={'normalbrhv'}
										/>
									</>
								}
							/>

							<ResponsiveMeasurementControls
								label={__('Border Radius', 'flx-blocks')}
								value={borderRadius}
								tabletValue={borderRadiusTablet}
								mobileValue={borderRadiusMobile}
								onChange={(value) => setAttributes({ borderRadius: value })}
								onChangeTablet={(value) => setAttributes({ borderRadiusTablet: value })}
								onChangeMobile={(value) => setAttributes({ borderRadiusMobile: value })}
								min={0}
								max={borderRadiusUnit === 'em' || borderRadiusUnit === 'rem' ? 24 : 100}
								step={borderRadiusUnit === 'em' || borderRadiusUnit === 'rem' ? 0.1 : 1}
								unit={borderRadiusUnit}
								units={['px', 'em', 'rem', '%']}
								onUnit={(value) => setAttributes({ borderRadiusUnit: value })}
								isBorderRadius={true}
								allowEmpty={true}
							/>
						</FlxbkPanelBody>
					</>
				)}
				{activeTab === 'advanced' && (
					<>
						<FlxbkPanelBody>
							<ResponsiveMeasureRangeControl
								label={__('Padding', 'flx-blocks')}
								value={padding}
								tabletValue={paddingTablet}
								mobileValue={paddingMobile}
								onChange={(value) => setAttributes({ padding: value })}
								onChangeTablet={(value) => setAttributes({ paddingTablet: value })}
								onChangeMobile={(value) => setAttributes({ paddingMobile: value })}
								min={0}
								max={
									paddingUnit === 'em' || paddingUnit === 'rem'
										? 25
										: paddingUnit === 'px'
											? 400
											: 100
								}
								ghostDefault={['xxs', 'xxs', 'xxs', 'xxs']}
								step={paddingUnit === 'em' || paddingUnit === 'rem' ? 0.1 : 1}
								unit={paddingUnit}
								units={['px', 'em', 'rem', '%']}
								onUnit={(value) => setAttributes({ paddingUnit: value })}
							/>
							<ResponsiveMeasureRangeControl
								label={__('Margin', 'flx-blocks')}
								value={margin}
								tabletValue={marginTablet}
								mobileValue={marginMobile}
								onChange={(value) => setAttributes({ margin: value })}
								onChangeTablet={(value) => setAttributes({ marginTablet: value })}
								onChangeMobile={(value) => setAttributes({ marginMobile: value })}
								min={0}
								max={marginUnit === 'em' || marginUnit === 'rem' ? 25 : marginUnit === 'px' ? 400 : 100}
								step={marginUnit === 'em' || marginUnit === 'rem' ? 0.1 : 1}
								unit={marginUnit}
								units={['px', 'em', 'rem', '%']}
								onUnit={(value) => setAttributes({ marginUnit: value })}
							/>
						</FlxbkPanelBody>
					</>
				)}
			</InspectorControls>
			<BackendStyles {...props} previewDevice={previewDevice} />

			{icon && previewIconSize && (
				<IconRender
					className={`fxb-off-canvas-trigger-icon`}
					name={icon}
					size={previewIconSize}
					strokeWidth={'fe' === icon.substring(0, 2) ? lineWidth : undefined}
				/>
			)}
		</button>
	);
}

export default Edit;
