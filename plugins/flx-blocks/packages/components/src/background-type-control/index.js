/**
 * Basic Background Control.
 */

/**
 * Import External
 */
import { get, map } from 'lodash';
import classnames from 'classnames';
/**
 * WordPress dependencies
 */
import { useInstanceId } from '@wordpress/compose';
/**
 * Import Css
 */
import './editor.scss';
/**
 * Import Flux Icons
 */
import { slider, brush, video, gradient } from '@flx-blocks/icons';
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, ButtonGroup, Icon } from '@wordpress/components';

/**
 * Tabs for Background Control.
 */
export default function BackgroundTypeControl({ label, type, onChange, allowedTypes = null, types = null }) {
	const defaultTabs = [
		{
			key: 'normal',
			title: __('Classic', 'flx-blocks'),
			icon: brush,
		},
		{
			key: 'gradient',
			title: __('Gradient', 'flx-blocks'),
			icon: gradient,
		},
		{
			key: 'slider',
			title: __('Slider', 'flx-blocks'),
			icon: slider,
		},
		{
			key: 'video',
			title: __('Video', 'flx-blocks'),
			icon: video,
		},
	];
	const typeKeys = ['normal', 'gradient', 'slider', 'video'];
	const allowedTypeKeys = allowedTypes ? allowedTypes : typeKeys;
	const typesMap = types ? types : defaultTabs;
	const instanceId = useInstanceId(BackgroundTypeControl);
	const id = `inspector-background-type-control-${instanceId}`;
	return (
		<div className="components-base-control flxbk-background-type-control">
			<div className="flxbk-background-type-container">
				{label && (
					<label htmlFor={id} className="flxbk-beside-label components-background-type-control__label">
						{label}
					</label>
				)}
				<ButtonGroup id={id} className={'flxbk-background-type-radio-container'}>
					{typesMap.map(({ key, title, icon }, i) => {
						if (allowedTypeKeys.includes(key)) {
							return (
								<Button
									key={key}
									label={title}
									onClick={() => onChange(key)}
									isTertiary={key !== type}
									isPrimary={key === type}
									className={`flxbk-radio-item${key === type ? ' radio-is-active' : ''}`}
									aria-pressed={key === type}
									icon={icon}
								/>
							);
						}
					})}
				</ButtonGroup>
			</div>
		</div>
	);
}
