/**
 * BLOCK: Flux Advanced Form
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
import { formBlockIcon, formTemplateContactIcon } from '@flx-blocks/icons';
import { FlxbkPanelBody, SelectPostFromPostType } from '@flx-blocks/components';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { Placeholder, Spinner, TextControl, Button, Modal } from '@wordpress/components';
import { store as coreStore, EntityProvider } from '@wordpress/core-data';
import { serialize } from '@wordpress/blocks';

import { useEntityAutoDraft } from './hooks';
import { SelectOrCreatePlaceholder } from './components';
import { uniqueIdHelper } from '@flx-blocks/helpers';
import { applyFormIdToBlocks } from './transforms';

/**
 * Internal dependencies
 */
import EditInner from './edit-inner';
import { useEffect, useRef, useState } from '@wordpress/element';

export function Edit(props) {
	const { attributes, setAttributes, clientId } = props;

	const { id, uniqueID, legacyMigration } = attributes;

	const blockClasses = classnames({
		'wp-block-flx-advanced-form': true,
		[`wp-block-flx-advanced-form${uniqueID}`]: uniqueID,
	});
	const blockProps = useBlockProps({
		className: blockClasses,
	});
	const { post, postExists, isLoading, currentPostType, postId } = useSelect(
		(select) => {
			return {
				post: id && select(coreStore).getEditedEntityRecord('postType', 'flxbk_form', id),
				postExists: id && select(coreStore).getEntityRecord('postType', 'flxbk_form', id),
				isLoading: select(coreStore).isResolving('getEntityRecord', ['postType', 'flxbk_form', id]),
				currentPostType: select('core/editor')?.getCurrentPostType()
					? select('core/editor')?.getCurrentPostType()
					: '',
				postId: select('core/editor')?.getCurrentPostId() ? select('core/editor')?.getCurrentPostId() : '',
			};
		},
		[id]
	);

	const { saveEntityRecord } = useDispatch(coreStore);

	const { isPreviewMode } = useSelect(
		(select) => {
			return {
				isPreviewMode: select('core/block-editor').getSettings().__unstableIsPreviewMode,
			};
		},
		[clientId]
	);

	if (isPreviewMode) {
		return <>{formTemplateContactIcon}</>;
	}

	uniqueIdHelper(props);

	useEffect(() => {
		if (currentPostType === 'flxbk_form') {
			// Lame workaround for gutenberg to prevent showing the block Validity error.
			window.wp.data.dispatch('core/block-editor').setTemplateValidity(true);
		}
	}, []);

	const legacyMigrationRef = useRef(false);
	const [pendingTitle, setPendingTitle] = useState('');
	const [chosenTitle, setChosenTitle] = useState('');
	const [isTitlePromptVisible, setIsTitlePromptVisible] = useState(false);
	const [isMigrationReady, setIsMigrationReady] = useState(false);

	useEffect(() => {
		if (legacyMigration) {
			setPendingTitle(legacyMigration.title || __('Migrated Form – Name', 'flx-blocks'));
			setChosenTitle('');
			setIsTitlePromptVisible(true);
			setIsMigrationReady(false);
		} else {
			setIsTitlePromptVisible(false);
			setIsMigrationReady(false);
		}
	}, [legacyMigration]);

	useEffect(() => {
		if (!legacyMigration || !isMigrationReady || legacyMigrationRef.current) {
			return;
		}
		legacyMigrationRef.current = true;
		(async () => {
			try {
				const { meta, rootBlock } = legacyMigration;
				const resolvedTitle =
					chosenTitle ||
					pendingTitle.trim() ||
					legacyMigration.title ||
					__('Migrated Form', 'flx-blocks');
				const createdRecord = await saveEntityRecord('postType', 'flxbk_form', {
					status: 'publish',
					title: resolvedTitle,
					content: serialize([rootBlock]),
					meta,
				});

				if (createdRecord && createdRecord.id) {
					const contentWithId = serialize([applyFormIdToBlocks(rootBlock, String(createdRecord.id))]);
					await saveEntityRecord('postType', 'flxbk_form', {
						id: createdRecord.id,
						content: contentWithId,
					});
					setAttributes({ id: createdRecord.id, legacyMigration: undefined });
				} else {
					setAttributes({ legacyMigration: undefined });
				}
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Flux Block Editor: legacy form migration failed', error);
				setAttributes({ legacyMigration: undefined });
			} finally {
				legacyMigrationRef.current = false;
				setIsMigrationReady(false);
			}
		})();
	}, [chosenTitle, isMigrationReady, legacyMigration, pendingTitle, saveEntityRecord, setAttributes]);

	const handleConfirmMigration = (useSuggested = false) => {
		if (!legacyMigration) {
			return;
		}
		const suggestedTitle = legacyMigration.title || __('Migrated Form', 'flx-blocks');
		const resolvedTitle = useSuggested ? suggestedTitle : pendingTitle.trim() || suggestedTitle;
		setChosenTitle(resolvedTitle);
		setIsTitlePromptVisible(false);
		setIsMigrationReady(true);
	};

	{
		/* Directly editing from via flxbk_form post type */
	}
	if (currentPostType === 'flxbk_form') {
		return (
			<div {...blockProps}>
				<EditInner {...props} direct={true} id={postId} />
			</div>
		);
	}

	if (legacyMigration) {
		return (
			<div {...blockProps}>
				<Placeholder
					className="fxb-select-or-create-placeholder"
					label={__('Flux Form', 'flx-blocks')}
					icon={formBlockIcon}
				>
					{isMigrationReady ? (
						<>
							<Spinner />
							<p>{__('Migrating legacy form…', 'flx-blocks')}</p>
						</>
					) : (
						<>
							<p>{__('Name your migrated form to finish the upgrade.', 'flx-blocks')}</p>
							<Button variant="primary" onClick={() => setIsTitlePromptVisible(true)}>
								{__('Name Form', 'flx-blocks')}
							</Button>
						</>
					)}
				</Placeholder>
				{isTitlePromptVisible && (
					<Modal
						title={__('Name Your Form', 'flx-blocks')}
						onRequestClose={() => handleConfirmMigration(false)}
						shouldCloseOnClickOutside={false}
						shouldCloseOnEsc={false}
						className="fxb-form-migration-modal"
					>
						<TextControl
							label={__('Form name', 'flx-blocks')}
							value={pendingTitle}
							onChange={(value) => setPendingTitle(value)}
							help={__('This name appears in the Flux Forms list.', 'flx-blocks')}
							autoComplete="off"
						/>
						<div className="fxb-form-migration-modal__actions">
							<Button variant="primary" onClick={() => handleConfirmMigration(false)}>
								{__('Create Form', 'flx-blocks')}
							</Button>
						</div>
					</Modal>
				)}
			</div>
		);
	}

	return (
		<div {...blockProps}>
			{/* No form selected or selected form was deleted from the site, display chooser */}
			{(id === 0 || (undefined === postExists && !isLoading)) && (
				<Chooser
					id={id}
					postExists={postExists}
					post={post}
					commit={(nextId) => setAttributes({ id: nextId })}
				/>
			)}

			{/* Form selected but not loaded yet, show spinner */}
			{id > 0 && isEmpty(post) && undefined === postExists && isLoading && (
				<>
					<Placeholder
						className="fxb-select-or-create-placeholder"
						label={__('Flux Form', 'flx-blocks')}
						icon={formBlockIcon}
					>
						<Spinner />
					</Placeholder>
					<InspectorControls>
						<FlxbkPanelBody
							panelName={'fxb-advanced-form-selected-switch'}
							title={__('Selected Form', 'flx-blocks')}
						>
							<SelectPostFromPostType
								postType="flxbk_form"
								label={__('Selected Form', 'flx-blocks')}
								hideLabelFromVision={true}
								onChange={(nextId) => {
									setAttributes({ id: parseInt(nextId) });
								}}
								value={id}
							/>
						</FlxbkPanelBody>
					</InspectorControls>
				</>
			)}
			{/* Form selected is in the trash */}
			{id > 0 && !isEmpty(post) && post.status === 'trash' && (
				<>
					<Placeholder
						className="fxb-select-or-create-placeholder"
						label={__('Flux Form', 'flx-blocks')}
						icon={formBlockIcon}
					>
						{__('The selected from is in the trash.', 'flx-blocks')}
					</Placeholder>
					<InspectorControls>
						<FlxbkPanelBody
							panelName={'fxb-advanced-form-selected-switch'}
							title={__('Selected Form', 'flx-blocks')}
						>
							<SelectPostFromPostType
								postType="flxbk_form"
								label={__('Selected Form', 'flx-blocks')}
								hideLabelFromVision={true}
								onChange={(nextId) => {
									setAttributes({ id: parseInt(nextId) });
								}}
								value={id}
							/>
						</FlxbkPanelBody>
					</InspectorControls>
				</>
			)}

			{/* Form selected and loaded, display it */}
			{id > 0 && !isEmpty(post) && post.status !== 'trash' && (
				<EntityProvider kind="postType" type="flxbk_form" id={id}>
					<EditInner {...props} direct={false} id={id} />
				</EntityProvider>
			)}
		</div>
	);
}

export default Edit;

function Chooser({ id, post, commit, postExists }) {
	const [isAdding, addNew] = useEntityAutoDraft('flxbk_form', 'flxbk_form');
	const onAdd = async () => {
		try {
			const response = await addNew();
			commit(response.id);
		} catch (error) {
			console.error(error);
		}
	};

	return (
		<SelectOrCreatePlaceholder
			postType="flxbk_form"
			label={__('Advanced Form', 'flx-blocks')}
			instructions={__('Select an existing form or create a new one.', 'flx-blocks')}
			placeholder={__('Select Form', 'flx-blocks')}
			onSelect={commit}
			isSelecting={id && isEmpty(post) && undefined !== postExists}
			onAdd={onAdd}
			isAdding={isAdding}
		/>
	);
}
