import { useState } from '@wordpress/element';
import {
	useFloating,
	autoUpdate,
	offset,
	flip,
	shift,
	useHover,
	useFocus,
	useDismiss,
	useRole,
	useInteractions,
	FloatingPortal,
} from '@floating-ui/react';

import './editor.scss';

export default function Tooltip({ children, text, className, TagName = 'span', placement = 'top' }) {
	if (!text) {
		return <TagName className={`tooltip-ref-wrap${className ? ' ' + className : ''}`}>{children}</TagName>;
	}
	const [isOpen, setIsOpen] = useState(false);
	let tooltipPlacement = placement;
	switch (placement) {
		case 'auto':
			tooltipPlacement = 'top';
			break;
		case 'auto-start':
			tooltipPlacement = 'top-start';
			break;
		case 'auto-end':
			tooltipPlacement = 'top-end';
			break;
	}
	const { refs, floatingStyles, context } = useFloating({
		open: isOpen,
		onOpenChange: setIsOpen,
		placement: tooltipPlacement,
		// Make sure the tooltip stays on the screen
		whileElementsMounted: autoUpdate,
		middleware: [
			offset(5),
			flip({
				fallbackAxisSideDirection: 'start',
			}),
			shift(),
		],
	});

	// Event listeners to change the open state
	const hover = useHover(context, { move: false });
	const focus = useFocus(context);
	const dismiss = useDismiss(context);
	// Role props for screen readers
	const role = useRole(context, { role: 'tooltip' });
	// Rebuilds the markup from tokens: text is escaped and only allowed tags (without attributes) are kept.
	const strip_tags = (input, allowed) => {
		const allowedTags = (allowed || '').toLowerCase().match(/[a-z][a-z0-9]*/g) || [];
		const esc = (value) =>
			String(value)
				.replace(/&(?!(?:#x[0-9a-f]{1,6}|#[0-9]{1,7}|[a-z]{2,8});)/gi, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		const source = String(input || '').replace(/<!--[\s\S]*?-->/g, '');
		const tagRe = /<(\/?)([a-z][a-z0-9]*)\b[^<>]*>/gi;
		let out = '';
		let last = 0;
		let m;
		while ((m = tagRe.exec(source)) !== null) {
			out += esc(source.slice(last, m.index));
			last = tagRe.lastIndex;
			const tag = allowedTags.find((t) => t === m[2].toLowerCase());
			if (tag) {
				out += '<' + (m[1] ? '/' : '') + tag + '>';
			}
		}
		return out + esc(source.slice(last));
	};

	// Merge all the interactions into prop getters
	const { getReferenceProps, getFloatingProps } = useInteractions([hover, focus, dismiss, role]);
	return (
		<>
			<TagName
				className={`tooltip-ref-wrap tooltip-ref-has-tip${className ? ' ' + className : ''}`}
				ref={refs.setReference}
				{...getReferenceProps()}
			>
				{children}
			</TagName>
			<FloatingPortal>
				{isOpen && (
					<div
						className="fxb-floating-tooltip"
						ref={refs.setFloating}
						style={floatingStyles}
						{...getFloatingProps()}
						data-placement={tooltipPlacement}
					>
						<span
							className="fxb-tooltip-content"
							dangerouslySetInnerHTML={{
								__html: strip_tags(text, '<br><b><i><u><p><ol><ul><li><strong><small>'),
							}}
						/>
						<span className="fxb-tooltip-arrow" />
					</div>
				)}
			</FloatingPortal>
		</>
	);
}
