/**
 * BLOCK: Flux Tabs
 */
import classnames from 'classnames';
import { times } from 'lodash';
import { IconSpanTag } from '@flx-blocks/components';

import { InnerBlocks, RichText, useBlockProps } from '@wordpress/block-editor';

/**
 * Internal block libraries
 */
import { __, sprintf } from '@wordpress/i18n';

function FlxbkTabsSave({ attributes }) {
	const stripStringRender = (string) => {
		return string.toLowerCase().replace(/[^0-9a-z-]/g, '');
	};

	const {
		tabCount,
		blockAlignment,
		currentTab,
		mobileLayout,
		layout,
		tabletLayout,
		uniqueID,
		titles,
		iSize,
		maxWidth,
		tabAlignment,
		startTab,
		enableSubtitle,
		widthType,
		tabWidth,
		linkPaneCollapse,
		size,
		tabSize,
		mobileSize,
	} = attributes;
	const layoutClass = !layout ? 'tabs' : layout;
	const tabLayoutClass = !tabletLayout ? 'inherit' : tabletLayout;
	const mobileLayoutClass = !mobileLayout ? 'inherit' : mobileLayout;
	const accordionClass =
		(mobileLayout && 'accordion' === mobileLayout) || (tabletLayout && 'accordion' === tabletLayout)
			? 'fxt-create-accordion'
			: '';
	const classId = !uniqueID ? 'notset' : uniqueID;
	const theTabAlignment = tabAlignment ? tabAlignment : 'left';
	const classes = classnames(`align${blockAlignment ? blockAlignment : 'none'}`);
	const activeTab = startTab ? startTab : currentTab;
	const innerClasses = classnames(
		`fxt-tabs-wrap fxt-tabs-id${classId} fxt-tabs-has-${tabCount}-tabs fxt-active-tab-${activeTab} fxt-tabs-layout-${layoutClass} fxt-tabs-tablet-layout-${tabLayoutClass} fxt-tabs-mobile-layout-${mobileLayoutClass} fxt-tab-alignment-${theTabAlignment} ${accordionClass}`
	);

	const renderTitles = (index) => {
		const backupAnchor = `tab-${
			titles[index] && titles[index].text
				? stripStringRender(titles[index].text.toString())
				: stripStringRender(__('Tab', 'flx-blocks') + (1 + index))
		}`;
		return (
			<li
				id={titles[index] && titles[index].anchor ? titles[index].anchor : backupAnchor}
				className={`fxt-title-item fxt-title-item-${1 + index} fxt-tabs-svg-show-${
					titles[index] && titles[index].onlyIcon ? 'only' : 'always'
				} fxt-tabs-icon-side-${
					titles[index] && titles[index].iconSide ? titles[index].iconSide : 'right'
				} fxt-tab-title-${1 + index === activeTab ? 'active' : 'inactive'}${
					enableSubtitle ? ' fxb-tabs-have-subtitle' : ''
				}`}
			>
				<a
					href={`#${titles[index] && titles[index].anchor ? titles[index].anchor : backupAnchor}`}
					data-tab={1 + index}
					className={`fxt-tab-title fxt-tab-title-${1 + index} `}
				>
					{titles[index] && titles[index].icon && 'right' !== titles[index].iconSide && (
						<IconSpanTag
							className={`fxt-tab-svg-icon fxt-tab-svg-icon-${titles[index].icon} fxt-title-svg-side-${titles[index].iconSide}`}
							name={titles[index].icon}
							size={!iSize ? '14' : iSize}
						/>
					)}
					{(!enableSubtitle ||
						(undefined !== titles[index] && undefined === titles[index].subText) ||
						(undefined !== titles[index] &&
							undefined !== titles[index].subText &&
							'' === titles[index].subText)) && (
						<RichText.Content
							tagName="span"
							value={
								titles[index] && titles[index].text
									? titles[index].text
									: // Translators: %d is the tab number.
										sprintf(__('Tab %d', 'flx-blocks'), 1 + index)
							}
							className={'fxt-title-text'}
						/>
					)}
					{enableSubtitle &&
						titles[index] &&
						undefined !== titles[index].subText &&
						'' !== titles[index].subText && (
							<div className="fxb-tab-titles-wrap">
								<RichText.Content
									tagName="span"
									value={
										titles[index] && titles[index].text
											? titles[index].text
											: // Translators: %d is the tab number.
												sprintf(__('Tab %d', 'flx-blocks'), 1 + index)
									}
									className={'fxt-title-text'}
								/>
								<RichText.Content
									tagName="span"
									value={titles[index].subText}
									className={'fxt-title-sub-text'}
								/>
							</div>
						)}
					{titles[index] && titles[index].icon && 'right' === titles[index].iconSide && (
						<IconSpanTag
							className={`fxt-tab-svg-icon fxt-tab-svg-icon-${titles[index].icon} fxt-title-svg-side-${titles[index].iconSide}`}
							name={titles[index].icon}
							size={!iSize ? '14' : iSize}
						/>
					)}
				</a>
			</li>
		);
	};

	const blockProps = useBlockProps.save({
		className: classes,
	});

	return (
		<div {...blockProps}>
			<div className={innerClasses} data-no-allow-multiple-open={linkPaneCollapse ? true : null}>
				<ul
					className={`fxt-tabs-title-list${
						'tabs' === layout && widthType === 'percent'
							? ' fxb-tabs-list-columns fxb-tab-title-columns-' + tabWidth[0]
							: ''
					}`}
				>
					{times(tabCount, (n) => renderTitles(n))}
				</ul>
				<div className="fxt-tabs-content-wrap">
					<InnerBlocks.Content />
				</div>
			</div>
		</div>
	);
}

export default FlxbkTabsSave;
