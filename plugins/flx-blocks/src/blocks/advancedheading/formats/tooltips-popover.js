import { __ } from '@wordpress/i18n';
import { useAnchor } from '@wordpress/rich-text';
import { Popover, SelectControl, TextareaControl } from '@wordpress/components';
import { useCachedTruthy, __experimentalLinkControl as LinkControl } from '@wordpress/block-editor';
import { FlxbkPanelBody } from '@flx-blocks/components';
import { useMemo } from '@wordpress/element';
import { flxbkToolTips as settings } from './tooltips';

// Ensure DEFAULT_LINK_SETTINGS is iterable (WP 6.3 compatibility).
const defaultLinkSettings = Array.isArray(LinkControl?.DEFAULT_LINK_SETTINGS)
	? LinkControl.DEFAULT_LINK_SETTINGS
	: [
			{
				id: '"opensInNewTab"',
				title: __('Open in new tab', 'flx-blocks'),
			},
		];

const LINK_SETTINGS = [
	...defaultLinkSettings,
	{
		id: 'nofollow',
		title: __('Mark as nofollow', 'flx-blocks'),
	},
];

export default function TooltipsPopover({
	name,
	value,
	updateFormat,
	isActive,
	onClose,
	contentRef,
	activeAttributes,
}) {
	const tooltipSettings = {
		content: activeAttributes.content || '',
		placement: activeAttributes.placement || '',
	};
	const linkValue = useMemo(
		() => ({
			url: activeAttributes.url,
			opensInNewTab: activeAttributes.target === '_blank',
			nofollow: activeAttributes.rel?.includes('nofollow'),
		}),
		[activeAttributes.rel, activeAttributes.target, activeAttributes.url]
	);
	const popoverAnchor = useCachedTruthy(
		useAnchor({
			editableContentElement: contentRef.current,
			settings: {
				...settings,
				isActive,
			},
		})
	);
	function onChangeLink(nextValue) {
		if (!nextValue?.url) {
			updateFormat({ url: '', target: '', rel: '' });
			return;
		}
		// Merge the next value with the current link value.
		nextValue = {
			...linkValue,
			...nextValue,
		};

		const linkAttributes = {
			url: nextValue?.url,
		};

		if (nextValue?.opensInNewTab) {
			linkAttributes.target = '_blank';
			linkAttributes.rel = linkAttributes.rel
				? linkAttributes.rel + ' noreferrer noopener'
				: 'noreferrer noopener';
		}

		if (nextValue?.nofollow) {
			linkAttributes.rel = linkAttributes.rel ? linkAttributes.rel + ' nofollow' : 'nofollow';
		}
		updateFormat(linkAttributes);
	}
	return (
		<Popover className="fxb-typing-popover fxb-tooltip-popover" onClose={onClose} anchor={popoverAnchor}>
			<FlxbkPanelBody
				title={__('Tooltip Content', 'flx-blocks')}
				initialOpen={true}
				panelName={'fxb-tooltip-basic-settings'}
			>
				<TextareaControl
					label={__('Content', 'flx-blocks')}
					value={tooltipSettings.content}
					onChange={(newValue) => updateFormat({ content: newValue })}
				/>
				<SelectControl
					label={__('Placement', 'flx-blocks')}
					value={tooltipSettings.placement || 'top'}
					options={[
						{ label: __('Top', 'flx-blocks'), value: 'top' },
						{ label: __('Top Start', 'flx-blocks'), value: 'top-start' },
						{ label: __('Top End', 'flx-blocks'), value: 'top-end' },
						{ label: __('Right', 'flx-blocks'), value: 'right' },
						{ label: __('Right Start', 'flx-blocks'), value: 'right-start' },
						{ label: __('Right End', 'flx-blocks'), value: 'right-end' },
						{ label: __('Bottom', 'flx-blocks'), value: 'bottom' },
						{ label: __('Bottom Start', 'flx-blocks'), value: 'bottom-start' },
						{ label: __('Bottom End', 'flx-blocks'), value: 'bottom-end' },
						{ label: __('Left', 'flx-blocks'), value: 'left' },
						{ label: __('Left Start', 'flx-blocks'), value: 'left-start' },
						{ label: __('Left End', 'flx-blocks'), value: 'left-end' },
						{ label: __('Auto', 'flx-blocks'), value: 'auto' },
						{ label: __('Auto Start', 'flx-blocks'), value: 'auto-start' },
						{ label: __('Auto End', 'flx-blocks'), value: 'auto-end' },
					]}
					onChange={(val) => {
						updateFormat({ placement: val });
					}}
				/>
			</FlxbkPanelBody>
			<FlxbkPanelBody
				title={__('Link Settings', 'flx-blocks')}
				initialOpen={false}
				panelName={'fxb-tooltip-link-settings'}
			>
				<LinkControl
					label={__('Link', 'flx-blocks')}
					onChange={onChangeLink}
					value={linkValue}
					settings={LINK_SETTINGS}
				/>
			</FlxbkPanelBody>
		</Popover>
	);
}
