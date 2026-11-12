import { createInterpolateElement, useEffect, useState } from '@wordpress/element';
import { useSelect, useDispatch, select, dispatch } from '@wordpress/data';
import FlxbkPanelBody from '../panel-body/index.js';
import { __, sprintf } from '@wordpress/i18n';
import { getBlockType } from '@wordpress/blocks';
import { addQueryArgs } from '@wordpress/url';
import { omit, head, get, isEqual } from 'lodash';
import { store as noticesStore } from '@wordpress/notices';
import { Button, Modal, __experimentalConfirmDialog as ConfirmDialog } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { SafeParseJSON, getTransferableAttributes } from '@flx-blocks/helpers';
/**
 * The data store flx-blocks registers in the Site Editor to open the Flux
 * panel. Looked up by name, so this package doesn't depend on flx-blocks code.
 */
const SITE_STYLES_STORE = 'flx/site-styles';

/**
 * @param {string} blockName Block name, e.g. `flx/singlebtn`.
 * @return {boolean} Whether site-level styles replace Block Defaults for the block: the
 * Flux theme runs in FSE mode and flx-blocks lists the block.
 */
function usesSiteStyles(blockName) {
	const params = typeof flxbk_params === 'undefined' ? {} : flxbk_params;

	return !!params.isFseMode && Array.isArray(params.siteStylesBlocks) && params.siteStylesBlocks.includes(blockName);
}

/**
 * Block Defaults for a block whose site-level styles are set in the Flux panel:
 * a button that opens the panel in the Site Editor, a note elsewhere.
 *
 * @param {object} props           Component props.
 * @param {string} props.blockSlug Block name, e.g. `flx/singlebtn`.
 * @return {Element} The panel.
 */
function SiteStylesPointer({ blockSlug }) {
	// flx-blocks registers this store only where it mounts the panel: the Site Editor in FSE mode.
	const canOpenPanel = !!select(SITE_STYLES_STORE);
	const blockTitle = getBlockType(blockSlug)?.title || blockSlug;
	// Relative to wp-admin, where both editors run. The block name is encoded once more inside `section`, as core does.
	const stylesUrl = addQueryArgs('site-editor.php', {
		p: '/styles',
		section: '/blocks/' + encodeURIComponent(blockSlug),
	});

	return (
		<FlxbkPanelBody
			title={__('Block Defaults', 'flx-blocks')}
			initialOpen={false}
			panelName={`fxb-${blockSlug}-defaults`}
		>
			{canOpenPanel
				? sprintf(
						/* translators: %1$s: block title, e.g. Single Button. */
						__(
							'Site styles replace Block Defaults for %1$s. They apply to every %1$s on the site, except where a block has its own setting.',
							'flx-blocks'
						),
						blockTitle
				  )
				: createInterpolateElement(
						sprintf(
							/* translators: %1$s: block title, e.g. Single Button. */
							__(
								'Site styles replace Block Defaults for %1$s. You can change them in the Site Editor, under <a>Styles > Blocks > %1$s</a>.',
								'flx-blocks'
							),
							blockTitle
						),
						{ a: <a href={stylesUrl} /> }
				  )}
			{canOpenPanel && (
				<>
					<br />
					<br />
					<Button variant="secondary" onClick={() => dispatch(SITE_STYLES_STORE).openSiteStyles(blockSlug)}>
						{__('Edit site styles', 'flx-blocks')}
					</Button>
				</>
			)}
		</FlxbkPanelBody>
	);
}

/**
 * Display Flux Block Default settings -- intended for use in Inspector Controls.
 *
 * @param {object} attributes Block attributes.
 * @param {object} defaultAttributes The blocks default attributs for comparison on what is new.
 * @param {string} blockSlug Block slug.
 * @param {object} attributeNamePair Key value pair of attribute name and attribute label.
 * @param {array} excludedAttrs Keys to exclude from saved block defaults. An array of strings.
 * @param {array} preventMultiple Keys that should not have more than one subitem. An array of strings.
 *
 * @public
 */
export default function FlxbkBlockDefaults(props) {
	const [user, setUser] = useState(flxbk_params.userrole ? flxbk_params.userrole : 'admin');
	if (user !== 'admin') {
		return null;
	}

	if (usesSiteStyles(props.blockSlug)) {
		return <SiteStylesPointer blockSlug={props.blockSlug} />;
	}

	return <BlockDefaultsPanel {...props} />;
}

/**
 * The Save / Modify / Reset Block Defaults panel. Kept apart from FlxbkBlockDefaults so
 * its hooks always run in the same order, whichever panel that component returns.
 *
 * @param {object} props Same as FlxbkBlockDefaults.
 */
function BlockDefaultsPanel({
	attributes,
	defaultAttributes = {},
	blockSlug,
	excludedAttrs = [],
	preventMultiple = [],
}) {
	const {createErrorNotice, createSuccessNotice} = useDispatch(noticesStore);

	const [isOpenResetConfirm, setIsOpenResetConfirm] = useState(false);
	const [isOpenSaveConfirm, setIsOpenSaveConfirm] = useState(false);
	const [isOpenModify, setIsOpenModify] = useState(false);

	const currentDefaults = SafeParseJSON(get(flxbk_params, ['configuration'], {}), true);
	const currentBlockDefaults = get(currentDefaults, blockSlug, {});

	const [tmpDefaults, setTmpDefaults] = useState(currentBlockDefaults);
	const hasConfig = Object.keys(currentBlockDefaults).length !== 0;

	const calculate = () => {
		//grab all block attributes, minus the exclusions
		return getTransferableAttributes(attributes, defaultAttributes, excludedAttrs, preventMultiple);
	};

	const reset = () => {
		let config = flxbk_params.configuration
			? SafeParseJSON(flxbk_params.configuration, true)
			: {};
		config = omit(config, blockSlug);

		apiFetch({
			path: '/wp/v2/settings',
			method: 'POST',
			data: { flxbk_config_blocks: JSON.stringify(config)},
		} ).then( () => {
			createSuccessNotice(__('Block default saved', 'flx-blocks'), {
                type: 'snackbar',
            })
            setIsOpenResetConfirm(false);
            flxbk_params.configuration = JSON.stringify(config);
            setTmpDefaults({});
		});

    }

    const saveAll = () => {

        const newConfig = calculate();

        const config = (flxbk_params.configuration ? SafeParseJSON(flxbk_params.configuration, true) : {});
        config[blockSlug] = newConfig;
		apiFetch( {
			path: '/wp/v2/settings',
			method: 'POST',
			data: { flxbk_config_blocks: JSON.stringify(config)},
		} ).then( () => {
			createSuccessNotice(__('Block default saved', 'flx-blocks'), {
				type: 'snackbar',
			});
			setIsOpenResetConfirm(false);
			flxbk_params.configuration = JSON.stringify(config);
			setTmpDefaults({});
		});
	};

	const saveModified = () => {
		const config = flxbk_params.configuration
			? SafeParseJSON(flxbk_params.configuration, true)
			: {};
		config[blockSlug] = tmpDefaults;
		apiFetch({
			path: '/wp/v2/settings',
			method: 'POST',
			data: { flxbk_config_blocks: JSON.stringify(config)},
		} ).then( () => {
			createSuccessNotice(__('Block default saved', 'flx-blocks'), {
				type: 'snackbar',
			});
			flxbk_params.configuration = JSON.stringify(config);
		});
	};

	return (
		<>
			<FlxbkPanelBody
				title={__('Block Defaults', 'flx-blocks')}
				initialOpen={false}
				panelName={`fxb-${blockSlug}-defaults`}
			>
				{__(
					'This will set the current block attributes as the default styles for this block type.',
					'flx-blocks'
				)}
				&nbsp;
				{__('This will not modify any blocks that have already been created.', 'flx-blocks')}&nbsp;
				{__('Block content is not included.', 'flx-blocks')}&nbsp;
				<br />
				<br />
				<Button isPrimary={true} onClick={() => setIsOpenSaveConfirm(true)}>
					{__(' Save as default', 'flx-blocks')}
				</Button>
				{hasConfig && (
					<>
						<br />
						<br />

						<a href={'#'} onClick={() => setIsOpenModify(true)}>
							{__('Modify attributes', 'flx-blocks')}
						</a>

						<a
							href={'#'}
							style={{ color: 'red', float: 'right' }}
							onClick={() => setIsOpenResetConfirm(true)}
						>
							{__('Reset defaults', 'flx-blocks')}
						</a>
					</>
				)}
			</FlxbkPanelBody>

			<ConfirmDialog
				isOpen={isOpenResetConfirm}
				onConfirm={() => reset()}
				onCancel={() => setIsOpenResetConfirm(false)}
			>
				{__("Are you sure you'd like to reset this blocks default attributes?", 'flx-blocks')}
			</ConfirmDialog>

			<ConfirmDialog
				isOpen={isOpenSaveConfirm}
				onConfirm={() => saveAll()}
				onCancel={() => setIsOpenSaveConfirm(false)}
			>
				{__("Are you sure you'd like to save this as the blocks default attributes?", 'flx-blocks')}
			</ConfirmDialog>

			{isOpenModify ? (
				<Modal
					className="fxt-block-defaults-modal"
					title={__('Modify Block Defaults', 'flx-blocks')}
					onRequestClose={() => {
						setTmpDefaults(currentBlockDefaults);
						setIsOpenModify(false);
					}}
				>
					{Object.keys(tmpDefaults).map((key, i) => {
						return (
							<>
								<div key={i} style={{ marginBottom: '10px' }}>
									<Button onClick={() => setTmpDefaults(omit(tmpDefaults, key))}>
										<span className="dashicons dashicons-trash"></span>
									</Button>
									<span style={{ verticalAlign: 'super' }}>{key}</span>
								</div>
							</>
						);
					})}

					<div className="fxb-modal-footer">
						<Button
							className="fxt-defaults-save"
							isDestructive
							disabled={false}
							onClick={() => {
								setTmpDefaults(currentBlockDefaults);
								setIsOpenModify(false);
							}}
						>
							{__('Cancel', 'flx-blocks')}
						</Button>

						<Button
							className="fxt-defaults-save"
							isPrimary
							onClick={() => {
								saveModified();
								setIsOpenModify(false);
							}}
						>
							{__('Save', 'flx-blocks')}
						</Button>
					</div>
				</Modal>
			) : null}
		</>
	);
}
