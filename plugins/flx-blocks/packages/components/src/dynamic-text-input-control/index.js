/**
 * WordPress dependencies
 */
import DynamicTextPopover from '../dynamic-text-popover';

import { __ } from '@wordpress/i18n';
import { Button, TextControl } from '@wordpress/components';
import { undo } from '@wordpress/icons';
import { map, isEqual } from 'lodash';
/**
 * WordPress dependencies
 */
import { useInstanceId } from '@wordpress/compose';
import { applyFilters } from '@wordpress/hooks';

/**
 * Import Css
 */
import './editor.scss';
export default function DynamicTextInputControl({
	value,
	onChange,
	label,
	className,
	reset,
	defaultValue = '',
	dynamicAttribute,
	isSelected,
	attributes,
	setAttributes,
	name,
	clientId,
	context,
}) {
	const instanceId = useInstanceId(DynamicTextInputControl);
	const onReset = () => {
		if (typeof reset === 'function') {
			reset();
		} else {
			onChange(defaultValue);
		}
	};
	const { flxbkDynamic } = attributes;
	const hasDynamic =
		undefined !== flxbkDynamic &&
		undefined !== flxbkDynamic[dynamicAttribute] &&
		undefined !== flxbkDynamic[dynamicAttribute].enable &&
		'' !== flxbkDynamic[dynamicAttribute].enable
			? flxbkDynamic[dynamicAttribute].enable
			: false;
	return (
		<div
			className={`components-base-control flxbk-form-input-control flxbk-form-input-control${instanceId}${
				className ? ' ' + className : ''
			}`}
		>
			{label && (
				<div className={'flxbk-form-input-control__header'}>
					{label && (
						<div className="flxbk-form-input-control__title">
							<label className="components-base-control__label">{label}</label>
							{reset && (
								<div className="title-reset-wrap">
									<Button
										className="is-reset is-single"
										label="reset"
										isSmall
										disabled={isEqual(defaultValue, value) ? true : false}
										icon={undo}
										onClick={() => onReset()}
									/>
								</div>
							)}
						</div>
					)}
				</div>
			)}
			<div className={'flxbk-controls-content'}>
				{hasDynamic && (
					<div className="flxbk-form-input-control__dynamic__label">
						{applyFilters('flxbk.formInputDisplay', value, attributes, dynamicAttribute)}
					</div>
				)}
				{!hasDynamic && <TextControl label={''} value={value} onChange={(value) => onChange(value)} />}
				<DynamicTextPopover
					dynamicAttribute={dynamicAttribute}
					isSelected={isSelected}
					attributes={attributes}
					setAttributes={setAttributes}
					name={name}
					clientId={clientId}
					context={context}
				/>
			</div>
		</div>
	);
}
