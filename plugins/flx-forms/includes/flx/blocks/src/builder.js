/**
 * Flux Forms builder: the form's blocks in a block editor of their own, for the Forms screen (Forms › New form /
 * edit). The same blocks as in the page editor, so a form looks the same in both. The screen (packages/modules
 * forms/Builder.tsx) reads and saves the structure over GraphQL; this file only edits it.
 *
 *   const ed = window.flxfBuilder.mount(element, { id, name, structure, onChange });
 *   ed.value()        // { name, structure }
 *   ed.setId(id)      // after the first save of a new form
 *   ed.setName(name)  // the screen's name field
 *   ed.destroy()
 */
import { createRoot, useState, useEffect } from '@wordpress/element';
import {
	BlockEditorProvider,
	BlockList,
	BlockTools,
	BlockInspector,
	WritingFlow,
	ObserveTyping,
	BlockEditorKeyboardShortcuts,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { Popover, SlotFillProvider } from '@wordpress/components';
import { ShortcutProvider } from '@wordpress/keyboard-shortcuts';
import { createBlock } from '@wordpress/blocks';
import { useRegistry } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { fromStructure, toStructure } from './convert';
import { TEMPLATE, toBlocks } from './form';

function formBlock(id, name, structure) {
	if (Array.isArray(structure) && structure.length) {
		const p = fromStructure(structure);
		return createBlock('flxf/form', { formId: id || 0, formName: name || '', head: p.head, step: p.step }, toBlocks(p.blocks));
	}
	return createBlock('flxf/form', { formId: 0, formName: name || '' }, toBlocks(TEMPLATE));
}

function valueOf(blocks) {
	const root = (blocks || []).find((b) => 'flxf/form' === b.name);
	if (!root) {
		return null;
	}
	return { name: root.attributes.formName || '', structure: toStructure(root.attributes, root.innerBlocks) };
}

/**
 * Inside the editor's own data registry (BlockEditorProvider makes one): keeps it for setId/setName and opens with
 * the form selected, so its settings are in the side panel.
 */
function Bridge({ holder, rootId }) {
	const registry = useRegistry();
	holder.registry = registry;
	useEffect(() => {
		if (rootId) {
			registry.dispatch(blockEditorStore).selectBlock(rootId, null); // null: select without moving focus (no scroll)
		}
	}, []);
	return null;
}

function Editor({ initial, onChange, holder }) {
	const [blocks, setBlocks] = useState(initial);
	holder.blocks = blocks;
	const update = (next) => {
		setBlocks(next);
		holder.blocks = next;
		if (onChange) {
			onChange();
		}
	};
	const settings = {
		hasFixedToolbar: false,
		focusMode: false,
		__experimentalBlockPatterns: [],
		__experimentalBlockPatternCategories: [],
		templateLock: false,
		bodyPlaceholder: '',
	};
	return (
		<ShortcutProvider>
			<SlotFillProvider>
				<BlockEditorProvider value={blocks} onInput={update} onChange={update} settings={settings}>
					<BlockEditorKeyboardShortcuts.Register />
					<Bridge holder={holder} rootId={initial[0] && initial[0].clientId} />
					<div className="flxf-builder">
						<div className="flxf-builder__canvas editor-styles-wrapper">
							<BlockTools>
								<WritingFlow>
									<ObserveTyping>
										<BlockList />
									</ObserveTyping>
								</WritingFlow>
							</BlockTools>
						</div>
						<aside className="flxf-builder__side" aria-label={__('Settings', 'flx-forms')}>
							<BlockInspector />
						</aside>
					</div>
					<Popover.Slot />
				</BlockEditorProvider>
			</SlotFillProvider>
		</ShortcutProvider>
	);
}

window.flxfBuilder = {
	mount(el, opts = {}) {
		window.flxfBuilderActive = true;
		const initial = [formBlock(opts.id, opts.name, opts.structure)];
		const holder = { blocks: initial };
		const root = createRoot(el);
		const set = (attrs) => {
			const b = (holder.blocks || []).find((x) => 'flxf/form' === x.name);
			const r = holder.registry;
			if (b && r && r.select(blockEditorStore).getBlock(b.clientId)) {
				r.dispatch(blockEditorStore).updateBlockAttributes(b.clientId, attrs);
			}
		};
		root.render(<Editor initial={initial} onChange={opts.onChange} holder={holder} />);
		return {
			value: () => valueOf(holder.blocks),
			/** After the first save of a new form: the block knows its id. */
			setId(id) {
				set({ formId: id });
			},
			setName(name) {
				set({ formName: name });
			},
			destroy() {
				root.unmount();
				window.flxfBuilderActive = false;
			},
		};
	},
};
