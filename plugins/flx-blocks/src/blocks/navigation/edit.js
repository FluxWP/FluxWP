/**
 * BLOCK: Flux Advanced Navigation
 */

/**
 * Import Css
 */
import './editor.scss';
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';
import { isEmpty } from 'lodash';
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { Placeholder, Spinner, Button } from '@wordpress/components';
import { store as coreStore, EntityProvider, useEntityProp, useEntityBlockEditor } from '@wordpress/core-data';

import { useEntityAutoDraft, useEntityAutoDraftAndPublish } from './hooks';
import { SelectOrCreatePlaceholder } from './components';
import { navigationBlockIcon } from '@flx-blocks/icons';
import { FlxbkPanelBody, SelectPostFromPostType } from '@flx-blocks/components';
import { uniqueIdHelper, getPreviewSize } from '@flx-blocks/helpers';

/**
 * Internal dependencies
 */
import EditInner from './edit-inner';
import { buildTemplateFromSelection } from './helpers';
import { useEffect, useState } from '@wordpress/element';

export function Edit(props) {
	const { attributes, setAttributes, isSelected, clientId } = props;

	const { id, uniqueID, templateKey, makePost } = attributes;
	const [hasStartedLoading, setHasStartedLoading] = useState(false);
	const [isLoadingTemplateContent, setIsLoadingTemplateContent] = useState(false);
	const [tmpTemplateKey, setTmpTemplateKey] = useState(templateKey);

	const { post, postExists, isLoading, currentPostType, postId } = useSelect(
		(select) => {
			return {
				post: id && select(coreStore).getEditedEntityRecord('postType', 'flxbk_navigation', id),
				postExists: id && select(coreStore).getEntityRecord('postType', 'flxbk_navigation', id),
				isLoading: select(coreStore).isResolving('getEntityRecord', ['postType', 'flxbk_navigation', id]),
				currentPostType: select('core/editor')?.getCurrentPostType()
					? select('core/editor')?.getCurrentPostType()
					: '',
				postId: select('core/editor')?.getCurrentPostId() ? select('core/editor')?.getCurrentPostId() : '',
			};
		},
		[id]
	);
	// Since we're not in the EntityProvider yet, we need to provide a post id.
	// 'id' and 'meta' will be undefined untill the actual post is chosen / loaded
	const [meta, setMeta] = useNavigationProp('meta', currentPostType === 'flxbk_navigation' ? postId : id);
	const [existingTitle, setTitle] = useNavigationProp(
		'title',
		currentPostType === 'flxbk_navigation' ? postId : id
	);
	const [isAdding, addNew] = useEntityAutoDraftAndPublish('flxbk_navigation', 'flxbk_navigation');
	const [blocks, onInput, onChange] = useEntityBlockEditor('postType', 'flxbk_navigation', { id });

	const metaAttributes = {
		orientation: meta?._flxbk_navigation_orientation,
		orientationTablet: meta?._flxbk_navigation_orientationTablet,
		orientationMobile: meta?._flxbk_navigation_orientationMobile,
		stretchFill: meta?._flxbk_navigation_stretchFill,
		stretchFillTablet: meta?._flxbk_navigation_stretchFillTablet,
		stretchFillMobile: meta?._flxbk_navigation_stretchFillMobile,
		horizontalLayout: meta?._flxbk_navigation_horizontalLayout,
		horizontalLayoutTablet: meta?._flxbk_navigation_horizontalLayoutTablet,
		horizontalLayoutMobile: meta?._flxbk_navigation_horizontalLayoutMobile,
		horizontalGrid: meta?._flxbk_navigation_horizontalGrid,
		horizontalGridTablet: meta?._flxbk_navigation_horizontalGridTablet,
		horizontalGridMobile: meta?._flxbk_navigation_horizontalGridMobile,
	};

	const {
		orientation,
		orientationTablet,
		orientationMobile,
		stretchFill,
		stretchFillTablet,
		stretchFillMobile,
		horizontalLayout,
		horizontalLayoutTablet,
		horizontalLayoutMobile,
	} = metaAttributes;
	const inTemplatePreviewMode = !id && templateKey;

	//some workarounds in here because we can't set meta attributes on no-post templated navs
	//this allows them to be vertical or horizontal
	const previewOrientationDesktop = inTemplatePreviewMode
		? templateKey.includes('vertical')
			? 'vertical'
			: 'horizontal'
		: orientation;
	const previewOrientationTablet = inTemplatePreviewMode
		? templateKey.includes('vertical')
			? 'vertical'
			: 'horizontal'
		: orientationTablet;
	const previewOrientationMobile = inTemplatePreviewMode
		? templateKey.includes('vertical')
			? 'vertical'
			: 'horizontal'
		: orientationMobile;
	const { previewDevice, isPreviewMode, getStash } = useSelect(
		(select) => {
			return {
				isPreviewMode: select('core/block-editor').getSettings().__unstableIsPreviewMode,
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
				getStash: (value) => select('flxblocks/data').getStash(value),
			};
		},
		[clientId]
	);

	uniqueIdHelper(props);

	useEffect(() => {
		if (currentPostType === 'flxbk_navigation') {
			// Lame workaround for gutenberg to prevent showing the block Validity error.
			window.wp.data.dispatch('core/block-editor').setTemplateValidity(true);
		}
	}, []);
	const previewHorizontalLayout = getPreviewSize(
		previewDevice,
		horizontalLayout,
		horizontalLayoutTablet,
		horizontalLayoutMobile
	);
	const previewStretchFill = getPreviewSize(previewDevice, stretchFill, stretchFillTablet, stretchFillMobile);
	const previewOrientation = getPreviewSize(
		previewDevice,
		previewOrientationDesktop ? previewOrientationDesktop : 'horizontal',
		previewOrientationTablet,
		previewOrientationMobile
	);
	const previewOrientationTabletInherited = getPreviewSize(
		'tablet',
		previewOrientationDesktop ? previewOrientationDesktop : 'horizontal',
		previewOrientationTablet,
		previewOrientationMobile
	);
	const previewOrientationMobileInherited = getPreviewSize(
		'mobile',
		previewOrientationDesktop ? previewOrientationDesktop : 'horizontal',
		previewOrientationTablet,
		previewOrientationMobile
	);
	const blockClasses = classnames({
		[`wp-block-flx-navigation${uniqueID}`]: uniqueID,
		[`fxb-navigation-horizontal-layout-${previewHorizontalLayout}`]: previewHorizontalLayout,
		[`fxb-navigation-orientation-${previewOrientation}`]: previewOrientation,
		'fxb-navigation-layout-stretch-fill': 'fill' === previewStretchFill,
		[`navigation-desktop-orientation-${previewOrientationDesktop ? previewOrientationDesktop : 'horizontal'}`]:
			!previewDevice || previewDevice == 'Desktop',
		[`navigation-tablet-orientation-${previewOrientationTabletInherited ? previewOrientationTabletInherited : 'horizontal'}`]:
			previewDevice == 'Tablet',
		[`navigation-mobile-orientation-${previewOrientationMobileInherited ? previewOrientationMobileInherited : 'horizontal'}`]:
			previewDevice == 'Mobile',
	});
	const blockProps = useBlockProps({
		className: blockClasses,
	});

	//Directly editing from via flxbk_navigation post type
	if (currentPostType === 'flxbk_navigation' && !id && !templateKey) {
		return (
			<div {...blockProps}>
				<EditInner {...props} direct={true} id={postId} />
			</div>
		);
	}

	const makeTemplatedNavigationPost = async () => {
		try {
			setIsLoadingTemplateContent(true);
			const response = await addNew();
			const newPostId = response?.id;

			if (newPostId) {
				//set the newly created post id.
				//This should trigger another useeffect on the next render to build out the inner blocks
				setAttributes({ id: newPostId });
			}
		} catch (error) {
			console.error(error);
		}
	};

	// if this is a templated navigation (usually coming from mega menu onboarding)
	// then it should get premade with some templated content based on templateKey
	// This process is for heavier templated navs that require a post for meta attributes / etc
	useEffect(() => {
		if (!id && templateKey && makePost) {
			makeTemplatedNavigationPost();
		}
	}, []);

	//this effect should trigger after we've made a new post for this templated nav
	//it will fill out the meta and inner blocks based on the templatekey
	useEffect(() => {
		if (id && templateKey && makePost) {
			setAttributes({ makePost: false });
			const { templateInnerBlocks, templatePostMeta } = buildTemplateFromSelection(templateKey, getStash);

			if (templateInnerBlocks) {
				onChange(templateInnerBlocks, clientId);
			} else {
				// Skip, or template not found
				onChange([createBlock('flx/navigation', {}, [])], clientId);
			}

			setTitle(templateKey);

			templatePostMeta._flxbk_navigation_description = 'A placeholder navigation';

			setMeta({ ...meta, ...templatePostMeta });
			wp.data
				.dispatch('core')
				.saveEditedEntityRecord('postType', 'flxbk_navigation', id)
				.then(() => {
					setIsLoadingTemplateContent(false);
				});
		}
	}, [id]);

	useEffect(() => {
		// Revert to the template if no nav was created/selected.
		if (
			currentPostType !== 'flxbk_navigation' &&
			!isSelected &&
			id === 0 &&
			templateKey === '' &&
			tmpTemplateKey !== ''
		) {
			setAttributes({ templateKey: tmpTemplateKey });
		}
	}, [isSelected]);

	if (!hasStartedLoading && isLoading) {
		setHasStartedLoading(true);
	}

	//todo, handle this short circuit better
	if (isLoadingTemplateContent) {
		return <Spinner />;
	}

	return (
		<div {...blockProps}>
			{/* No navigation selected or selected navigation was deleted from the site, display chooser */}
			{((id === 0 && !templateKey) ||
				(undefined === postExists && hasStartedLoading && !isLoading && post !== null)) && (
				<Chooser
					id={id}
					postExists={postExists}
					post={post}
					commit={(nextId) => setAttributes({ id: nextId })}
				/>
			)}

			{/* Navigation selected but not loaded yet, show spinner */}
			{id > 0 && isEmpty(post) && undefined === postExists && isLoading && (
				<>
					<Spinner />
					<InspectorControls>
						<FlxbkPanelBody
							panelName={'fxb-navigation-selected-switch'}
							title={__('Selected Navigation', 'flx-blocks')}
						>
							<SelectPostFromPostType
								postType="flxbk_navigation"
								label={__('Selected Navigation', 'flx-blocks')}
								hideLabelFromVision={true}
								onChange={(nextId) => {
									setAttributes({ id: parseInt(nextId) });
								}}
								value={id}
							/>

							<Button
								isLink={true}
								onClick={() => {
									setAttributes({ id: 0 });
								}}
								style={{ marginBottom: '10px' }}
							>
								{__('Create a New Navigation', 'flx-blocks')}
							</Button>
						</FlxbkPanelBody>
					</InspectorControls>
				</>
			)}
			{/* Navigation selected is in the trash */}
			{id > 0 && !isEmpty(post) && post.status === 'trash' && (
				<>
					<Placeholder
						className="fxb-select-or-create-placeholder"
						label={__('Flux Navigation', 'flx-blocks')}
						icon={navigationBlockIcon}
					>
						{__('The selected navigation is in the trash.', 'flx-blocks')}
					</Placeholder>
					<InspectorControls>
						<FlxbkPanelBody
							panelName={'fxb-advanced-form-selected-switch'}
							title={__('Selected Navigation', 'flx-blocks')}
						>
							<SelectPostFromPostType
								postType="flxbk_navigation"
								label={__('Selected Navigation', 'flx-blocks')}
								hideLabelFromVision={true}
								onChange={(nextId) => {
									setAttributes({ id: parseInt(nextId) });
								}}
								value={id}
							/>

							<Button
								isLink={true}
								onClick={() => {
									setAttributes({ id: 0 });
								}}
								style={{ marginBottom: '10px' }}
							>
								{__('Create a New Navigation', 'flx-blocks')}
							</Button>
						</FlxbkPanelBody>
					</InspectorControls>
				</>
			)}

			{/* Navigation selected and loaded (or this is a template), display it */}
			{((!id && templateKey) || (id > 0 && !isEmpty(post) && post.status !== 'trash')) && (
				<EntityProvider kind="postType" type="flxbk_navigation" id={id}>
					<EditInner {...props} direct={false} id={id} />
				</EntityProvider>
			)}
		</div>
	);
}

export default Edit;

function Chooser({ id, post, commit, postExists }) {
	const [isAdding, addNew] = useEntityAutoDraftAndPublish('flxbk_navigation', 'flxbk_navigation');
	const onAdd = async () => {
		try {
			const response = await addNew();
			commit(response.id);
		} catch (error) {
			console.error(error);
		}
	};

	const onAddOtherType = async (selected) => {
		try {
			const response = await addNew();
			commit(response.id);

			window.fxb_navigation_import_core = { coreMenuId: selected.id, id: response.id, label: selected.label };
		} catch (error) {
			console.error(error);
		}
	};

	return (
		<SelectOrCreatePlaceholder
			postType="flxbk_navigation"
			label={__('Advanced Navigation', 'flx-blocks')}
			instructions={__('Select an existing navigation or create a new one.', 'flx-blocks')}
			placeholder={__('Select navigation', 'flx-blocks')}
			onSelect={commit}
			isSelecting={id && isEmpty(post) && undefined !== postExists}
			onAddOtherType={onAddOtherType}
			onAdd={onAdd}
			isAdding={isAdding}
		/>
	);
}

function useNavigationProp(prop, postId) {
	return useEntityProp('postType', 'flxbk_navigation', prop, postId);
}
