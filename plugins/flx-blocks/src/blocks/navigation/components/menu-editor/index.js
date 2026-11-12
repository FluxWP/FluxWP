/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

import { useEffect, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { PostSelectorCheckbox, FlxbkRadioButtons } from '@flx-blocks/components';
import { createBlock } from '@wordpress/blocks';
import { TextControl, Button } from '@wordpress/components';
import { plus } from '@wordpress/icons';

import MenuEdit from './edit';
import './editor.scss';

export default function MenuEditor({
	clientId,
	closeModal,
	title,
	setTitle,
	updateBlocksCallback,
	orientation,
	orientationTablet,
	setMetaAttribute,
}) {
	const [sidebarTab, setSidebarTab] = useState('page');

	const { blocks } = useSelect(
		(select) => {
			const { getBlock, getBlockOrder } = select('core/block-editor');
			const topLevelIds = getBlockOrder(clientId);

			return {
				blocks: topLevelIds.map((_id) => getBlock(_id)),
			};
		},
		[clientId]
	);

	const { addStash } = useDispatch('flxblocks/data');
	useEffect(() => {
		addStash('open-navigation-builder', clientId);
	}, [clientId]);

	const onSelect = (posts) => {
		const navItems = [];

		posts.forEach((post) => {
			navItems.push(
				createBlock('flx/navigation-link', {
					label: post.title.rendered,
					url: post.link,
					id: post.id,
					type: post.type,
					kind: 'post-type',
					uniqueID: Math.random().toString(36).substr(2, 9),
				})
			);
		});

		if (navItems.length !== 0) {
			updateBlocksCallback(navItems);
			// wp.data.dispatch('core/block-editor').insertBlocks(navItems, 99999, clientId, false, null);
		}
	};

	const onSelectCategory = (posts) => {
		const navItems = [];

		posts.forEach((post) => {
			navItems.push(
				createBlock('flx/navigation-link', {
					label: post.name,
					url: post.link,
					id: post.id,
					type: post.type,
					kind: 'post-type',
					uniqueID: Math.random().toString(36).substr(2, 9),
				})
			);
		});

		if (navItems.length !== 0) {
			updateBlocksCallback(navItems);
		}
	};

	return (
		<div className="fxb-menu-visual-editor">
			<div className="left-column">
				<div className="menu-container">
					<FlxbkRadioButtons
						label={__('Desktop Orientation', 'flx-blocks')}
						value={orientation !== '' ? orientation : 'horizontal'}
						options={[
							{ value: 'vertical', label: __('Vertical', 'flx-blocks') },
							{ value: 'horizontal', label: __('Horizontal', 'flx-blocks') },
						]}
						hideLabel={false}
						onChange={(value) => {
							setMetaAttribute(value, 'orientation');
						}}
					/>

					<FlxbkRadioButtons
						label={__('Tablet Orientation', 'flx-blocks')}
						value={
							orientationTablet !== ''
								? orientationTablet
								: orientation !== ''
									? orientation
									: 'horizontal'
						}
						// technically there should be a third option here, 'inherit', as the default. but that doesn't fit into the design well..
						options={[
							{ value: 'vertical', label: __('Vertical', 'flx-blocks') },
							{ value: 'horizontal', label: __('Horizontal', 'flx-blocks') },
						]}
						hideLabel={false}
						onChange={(value) => {
							setMetaAttribute(value, 'orientationTablet');
						}}
					/>

					{flxbk_params.postTypesSearchable.map((postType) => (
						<PostSelectorCheckbox
							key={postType.value}
							forceOpen={sidebarTab === postType.value}
							useForceState={true}
							onPanelBodyToggle={() =>
								setSidebarTab(sidebarTab === postType.value ? null : postType.value)
							}
							postType={postType.value}
							title={postType.label}
							onSelect={onSelect}
						/>
					))}

					<PostSelectorCheckbox
						key={'categories'}
						forceOpen={sidebarTab === 'categories'}
						useForceState={true}
						onPanelBodyToggle={() => setSidebarTab(sidebarTab === 'categories' ? null : 'categories')}
						postType={'categories'}
						title={__('Categories', 'flx-blocks')}
						onSelect={onSelectCategory}
					/>

					{flxbk_params.hasWoocommerce && (
						<PostSelectorCheckbox
							key={'product_cat'}
							forceOpen={sidebarTab === 'product_cat'}
							useForceState={true}
							onPanelBodyToggle={() => setSidebarTab(sidebarTab === 'product_cat' ? null : 'product_cat')}
							postType={'product_cat'}
							title={__('Product Categories', 'flx-blocks')}
							onSelect={onSelectCategory}
						/>
					)}
				</div>
				<div className={'add-menu'}></div>
			</div>
			<div className="right-column">
				<div>
					<TextControl
						value={title === 'Auto Draft' ? '' : title}
						onChange={setTitle}
						label={__('Navigation Name', 'flx-blocks')}
						help={__('This is used for your reference only.', 'flx-blocks')}
					/>

					<MenuEdit blocks={blocks} />
				</div>

				<div className={'footer'}>
					<Button
						isSecondary
						onClick={() => {
							updateBlocksCallback([
								createBlock('flx/navigation-link', {
									label: __('New Link', 'flx-blocks'),
									url: '',
									kind: 'custom',
									uniqueID: Math.random().toString(36).substr(2, 9),
									forceOpenInEditor: true,
								}),
							]);
						}}
						icon={plus}
					>
						{__('Custom link', 'flx-blocks')}
					</Button>
					<Button isPrimary onClick={closeModal}>
						{__('Done Editing', 'flx-blocks')}
					</Button>
				</div>
			</div>
		</div>
	);
}
