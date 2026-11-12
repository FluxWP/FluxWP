/**
 * Range Control
 *
 */

/**
 * Internal block libraries
 */
import { __, sprintf } from '@wordpress/i18n';
import { range, isEqual } from 'lodash';
import { Button, ToolbarGroup } from '@wordpress/components';
import { undo } from '@wordpress/icons';
/**
 * Import Css
 */
import './editor.scss';
import HeadingLevelIcon from './../heading-level-icon';

/**
 * Build the Measure controls
 * @returns {object} Measure settings.
 */
export default function tagSelect({
	label,
	onChange,
	value = '',
	className = '',
	ariaLabel = __('Change HTML Tag', 'flx-blocks'),
	reset = false,
	headingOnly = false,
	tagLowLevel = 1,
	tagHighLevel = 7,
	otherTags,
}) {
	const level = value !== 'span' && value !== 'div' && value !== 'p' ? value : 2;
	const htmlTag = value === 'span' || value === 'div' || value === 'p' ? value : 'heading';
	const createhtmlTagControl = (targetLevel) => {
		return [
			{
				icon: (
					<HeadingLevelIcon
						level={targetLevel}
						isPressed={targetLevel === level && htmlTag && htmlTag === 'heading' ? true : false}
					/>
				),
				title: sprintf(
					/* translators: %d: heading level e.g: "1", "2", "3" */
					__('Heading %d', 'flx-blocks'),
					targetLevel
				),
				isActive: targetLevel === level && htmlTag && htmlTag === 'heading' ? true : false,
				onClick: () => onChange(targetLevel),
			},
		];
	};
	const headingOnlyOptions = range(tagLowLevel, tagHighLevel).map(createhtmlTagControl);
	const headingOptions = headingOnlyOptions.map((val) => val);

	if ((otherTags && otherTags.p) || !otherTags) {
		headingOptions.push([
			{
				icon: <HeadingLevelIcon level={'p'} isPressed={htmlTag && htmlTag === 'p' ? true : false} />,
				title: __('Paragraph', 'flx-blocks'),
				isActive: htmlTag && htmlTag === 'p' ? true : false,
				onClick: () => onChange('p'),
			},
		]);
	}
	if ((otherTags && otherTags.span) || !otherTags) {
		headingOptions.push([
			{
				icon: <HeadingLevelIcon level={'span'} isPressed={htmlTag && htmlTag === 'span' ? true : false} />,
				title: __('Span', 'flx-blocks'),
				isActive: htmlTag && htmlTag === 'span' ? true : false,
				onClick: () => onChange('span'),
			},
		]);
	}
	if ((otherTags && otherTags.div) || !otherTags) {
		headingOptions.push([
			{
				icon: <HeadingLevelIcon level={'div'} isPressed={htmlTag && htmlTag === 'div' ? true : false} />,
				title: __('div', 'flx-blocks'),
				isActive: htmlTag && htmlTag === 'div' ? true : false,
				onClick: () => onChange('div'),
			},
		]);
	}

	return [
		onChange && (
			<div className={`fxb-tag-level-control components-base-control${className ? ' ' + className : ''}`}>
				{label && (
					<div className={'flxbk-component__header flxbk-tag-select__header'}>
						{label && (
							<div className="flxbk-component__header__title flxbk-tag-select__title">
								<label className="components-base-control__label">{label}</label>
								{reset && (
									<div className="title-reset-wrap">
										<Button
											className="is-reset is-single"
											label="reset"
											isSmall
											// eslint-disable-next-line no-undef
											disabled={isEqual(defaultValue, value) ? true : false}
											icon={undo}
											// eslint-disable-next-line no-undef
											onClick={() => onReset()}
										/>
									</div>
								)}
							</div>
						)}
					</div>
				)}
				<div className={'flxbk-controls-content fxb-tag-select-control-inner'}>
					<ToolbarGroup
						isCollapsed={false}
						label={ariaLabel}
						controls={headingOnly ? headingOnlyOptions : headingOptions}
					/>
				</div>
			</div>
		),
	];
}
