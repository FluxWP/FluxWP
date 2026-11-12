import { map, get, uniqueId, findIndex } from 'lodash';
import { AdvancedColorControlPalette } from '@flx-blocks/components';
import { Component, Fragment, useEffect, useState } from '@wordpress/element';
import { ToggleControl, Dashicon, Button, Tooltip } from '@wordpress/components';
import { compose } from '@wordpress/compose';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { useSetting, useSettings, store as blockEditorStore } from '@wordpress/block-editor';

const fxbColorUniqueIDs = [];
/**
 * Internal block libraries
 */
import { __, sprintf } from '@wordpress/i18n';

export default function FlxbkColorDefault() {
	const [isSaving, setIsSaving] = useState(false);
	const [flxbkColors, setFlxbkColors] = useState(
		flxbk_params.colors
			? JSON.parse(flxbk_params.colors)
			: {
					palette: [],
					override: false,
				}
	);
	// The theme origin, which this panel writes back: the resolved palette holds only the user colors once one exists.
	// A theme without a palette has no theme origin, so it starts from a copy of the resolved palette:
	// the panel edits its list in place, and the resolved one belongs to the default or user origin.
	const [themePalette, resolvedPalette] = useSettings('color.palette.theme', 'color.palette');
	const colorPalette = themePalette ?? [...(resolvedPalette ?? [])];
	const disableCustomColors = !useSetting('color.custom');
	const [colors, setColors] = useState('');
	const [themeColors, setThemeColors] = useState([]);
	const [showMessage, setShowMessage] = useState(false);
	const [classSat, setClassSat] = useState('first');
	const { createErrorNotice } = useDispatch(noticesStore);
	const { updateSettings } = useDispatch(blockEditorStore);
	const existingFeatures = useSelect((select) => select(blockEditorStore).getSettings().__experimentalFeatures, []);
	useEffect(() => {
		if (!colors) {
			if (undefined !== flxbkColors.override && true === flxbkColors.override) {
				setColors(flxbkColors.palette || []);
			} else {
				setColors(colorPalette);
			}
		}

		if (undefined !== flxbkColors.palette && undefined !== flxbkColors.palette[0]) {
			map(flxbkColors.palette, ({ slug }) => {
				const theID = slug.substring(11);
				fxbColorUniqueIDs.push(theID);
			});
			if (undefined !== flxbkColors.override && true === flxbkColors.override) {
				setShowMessage(true);
			}
		}
	}, []);

	// This effect will run whenever the colors state changes and will update the block editor settings with the new color palette.
	// It included support for legacy color palette and new color palette, but the legacy support will be removed in future versions.
	useEffect(() => {
		if (colors) {
			try {
				existingFeatures.color.palette.theme = colors;
				updateSettings({
					// Legacy support for color palette, will be removed in future versions.
					colors,

					// Update the experimental features with the new color palette.
					__experimentalFeatures: {
						...existingFeatures,
						color: {
							...existingFeatures?.color,
							palette: {
								...existingFeatures?.color?.palette,
								theme: colors,
							},
						},
					},
				});
			} catch (error) {
				console.error('Failed to update colors', error);
			}
		}
	}, [colors]);

	const saveConfig = async (configToSave = null) => {
		if (false === isSaving) {
			setIsSaving(true);
			const config = configToSave || flxbkColors;
			try {
				const response = await apiFetch({
					path: '/wp/v2/settings',
					method: 'POST',
					data: { flxbk_colors: JSON.stringify(config) },
				});

				if (response) {
					setIsSaving(false);
					if (!configToSave) {
						setFlxbkColors(config);
					}
					flxbk_params.colors = JSON.stringify(config);
					createErrorNotice(__('Colors saved!', 'flx-blocks'), {
						type: 'snackbar',
					});

					return true;
				}
			} catch (error) {
				createErrorNotice(__('Failed to save colors', 'flx-blocks'), {
					type: 'snackbar',
				});

				return false;
			}
			// const settingModel = new wp.api.models.Settings({ flxbk_colors: JSON.stringify(config) });
			// settingModel.save().then((response) => {
			// 	createErrorNotice(__('Block defaults saved!', 'flx-blocks'), {
			// 		type: 'snackbar',
			// 	});

			// 	setIsSaving(false);
			// 	setFlxbkColors(config);

			// 	flxbk_params.colors = JSON.stringify(config);

			// 	props.updateSettings({ colors });
			// });
		}
	};
	const saveFlxbkColors = (value, index) => {
		const newItems = flxbkColors.palette.map((item, thisIndex) => {
			if (parseInt(index) === parseInt(thisIndex)) {
				item = { ...item, ...value };
			}

			return item;
		});
		const newPal = flxbkColors;
		newPal.palette = newItems;

		setFlxbkColors(newPal);
	};

	const saveColors = (value, index) => {
		const newItems = colors.map((item, thisIndex) => {
			if (parseInt(index) === parseInt(thisIndex)) {
				item = { ...item, ...value };
			}

			return item;
		});

		setColors(newItems);
	};

	const colorRemove = undefined !== flxbkColors.override && true === flxbkColors.override ? 1 : 0;
	return (
		<div className="fxt-block-default-palette">
			{colors && (
				<div className={`components-color-palette palette-comp-${classSat}`}>
					{classSat === 'first' &&
						Object.keys(colors).map((index) => {
							let editable = false;
							let theIndex;
							const color = colors[index].color;
							const name = colors[index].name;
							const slug = colors[index].slug;
							if (undefined !== slug && slug.substr(0, 10) === 'fxb-palette') {
								theIndex = findIndex(flxbkColors.palette, (c) => c.slug === slug);
								editable = true;
							}
							const style = { color };
							return (
								<div key={index} className="components-color-palette__item-wrapper">
									{editable && undefined !== theIndex && flxbkColors.palette[theIndex].color && (
										<AdvancedColorControlPalette
											nameValue={
												flxbkColors.palette[theIndex].name
													? flxbkColors.palette[theIndex].name
													: __('Color', 'flx-blocks') + ' ' + theIndex + 1
											}
											colorValue={
												flxbkColors.palette[theIndex].color
													? flxbkColors.palette[theIndex].color
													: '#ffffff'
											}
											onSave={(value, title) => {
												saveFlxbkColors(
													{
														color: value,
														name: title,
														slug,
													},
													theIndex
												);

												saveColors({ color: value, name: title, slug }, index);

												saveConfig();
											}}
										/>
									)}
									{!editable && (
										<Tooltip
											text={
												name ||
												// translators: %s: color hex code e.g: "#f00".
												sprintf(__('Color code: %s', 'flx-blocks'), color)
											}
										>
											<div className="components-color-palette__item" style={style}>
												<Dashicon icon="lock" />
											</div>
										</Tooltip>
									)}
								</div>
							);
						})}
					{classSat === 'second' &&
						Object.keys(colors).map((index) => {
							let editable = false;
							let theIndex;
							const color = colors[index].color;
							const name = colors[index].name;
							const slug = colors[index].slug;
							if (undefined !== slug && slug.substr(0, 10) === 'fxb-palette') {
								theIndex = findIndex(flxbkColors.palette, (c) => c.slug === slug);
								editable = true;
							}
							const style = { color };
							return (
								<div key={index} className="components-color-palette__item-wrapper">
									{editable && undefined !== theIndex && flxbkColors.palette[theIndex].color && (
										<AdvancedColorControlPalette
											nameValue={
												flxbkColors.palette[theIndex].name
													? flxbkColors.palette[theIndex].name
													: __('Color') + ' ' + theIndex + 1
											}
											colorValue={
												flxbkColors.palette[theIndex].color
													? flxbkColors.palette[theIndex].color
													: '#ffffff'
											}
											onSave={(value, title) => {
												saveFlxbkColors(
													{
														color: value,
														name: title,
														slug,
													},
													theIndex
												);
												saveColors({ color: value, name: title, slug }, index);
												saveConfig();
											}}
										/>
									)}
									{!editable && (
										<Tooltip
											text={
												name ||
												// translators: %s: color hex code e.g: "#f00".
												sprintf(__('Color code: %s', 'flx-blocks'), color)
											}
										>
											<div className="components-color-palette__item" style={style}>
												<Dashicon icon="lock" />
											</div>
										</Tooltip>
									)}
								</div>
							);
						})}
					{undefined !== flxbkColors.palette &&
						undefined !== flxbkColors.palette[colorRemove] &&
						!disableCustomColors && (
							<div className="fxt-colors-remove-last">
								<Tooltip text={__('Remove Last Color', 'flx-blocks')}>
									<Button
										type="button"
										isDestructive
										onClick={() => {
											const removeKey = flxbkColors.palette.length - 1;
											const removeItem =
												undefined !== flxbkColors.palette[removeKey]
													? flxbkColors.palette[removeKey]
													: flxbkColors.palette[removeKey];
											flxbkColors.palette.pop();
											const theColorIndex = findIndex(colors, (c) => c.slug === removeItem.slug);
											colors.splice(theColorIndex, 1);
											setFlxbkColors(flxbkColors);
											setColors(colors);
											saveConfig();
										}}
										aria-label={__('Remove Last Color', 'flx-blocks')}
									>
										<Dashicon icon="editor-removeformatting" />
									</Button>
								</Tooltip>
							</div>
						)}
				</div>
			)}
			{!disableCustomColors && (
				<div className="fxt-colors-add-new">
					<Button
						type="button"
						className={isSaving ? 'fxb-add-btn-is-saving' : 'fxb-add-btn-is-active'}
						isPrimary
						disabled={isSaving}
						onClick={() => {
							if (isSaving) {
								return;
							}
							if (undefined === flxbkColors.palette) {
								flxbkColors.palette = [];
							}
							let id = uniqueId();
							if (fxbColorUniqueIDs.includes(id)) {
								id = flxbkColors.palette.length.toString();
								if (fxbColorUniqueIDs.includes(id)) {
									id = uniqueId(id);
									if (fxbColorUniqueIDs.includes(id)) {
										id = uniqueId(id);
										if (fxbColorUniqueIDs.includes(id)) {
											id = uniqueId(id);
											if (fxbColorUniqueIDs.includes(id)) {
												id = uniqueId(id);
												if (fxbColorUniqueIDs.includes(id)) {
													id = uniqueId(id);
												}
											}
										}
									}
								}
							}
							fxbColorUniqueIDs.push(id);
							flxbkColors.palette.push({
								color: '#888888',
								name: __('Color') + ' ' + id,
								slug: 'fxb-palette-' + id,
							});
							colors.push({
								color: '#888888',
								name: __('Color') + ' ' + id,
								slug: 'fxb-palette-' + id,
							});

							setFlxbkColors(flxbkColors);
							setColors(colors);
							saveConfig();
						}}
						aria-label={__('Add Color', 'flx-blocks')}
					>
						{__('Add Color', 'flx-blocks')}
					</Button>
				</div>
			)}
			{undefined !== flxbkColors.palette && undefined !== flxbkColors.palette[0] && !disableCustomColors && (
				<>
					<ToggleControl
						label={__('Use only Custom Colors?', 'flx-blocks')}
						checked={undefined !== flxbkColors.override ? flxbkColors.override : false}
						onChange={(value) => {
							let newColors;
							const newFlxbkColors = { ...flxbkColors };

							if (true === value) {
								newColors = [...(newFlxbkColors.palette || [])];
								newFlxbkColors.override = true;
								setShowMessage(true);
							} else {
								newFlxbkColors.override = false;
								const themeColorsArray = Array.isArray(colorPalette) ? colorPalette : [];
								const customColors = newFlxbkColors.palette || [];
								const uniqueCustomColors = customColors.filter(
									(customColor) =>
										!themeColorsArray.some((themeColor) => themeColor.slug === customColor.slug)
								);
								newColors = [...themeColorsArray, ...uniqueCustomColors];
								setShowMessage(true);
							}
							setFlxbkColors(newFlxbkColors);
							setColors(newColors);
							saveConfig(newFlxbkColors);
						}}
					/>
					{undefined !== flxbkColors.override && true === showMessage && (
						<p className="fxb-colors-show-notice">
							{__('Refresh page to reload theme defined colors', 'flx-blocks')}
						</p>
					)}
				</>
			)}
		</div>
	);
}
