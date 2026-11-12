import { Popover, TextControl, PanelBody, SelectControl, Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { debounce, isEqual, has, get } from 'lodash';
import { applyFilters } from '@wordpress/hooks';
import { useState, useMemo, useEffect, useCallback } from '@wordpress/element';
import { default as GenIcon } from '../icons/gen-icon';
import { plus, chevronDown, closeSmall } from '@wordpress/icons';
import './editor.scss';
import SvgAddModal from './svg-add-modal';
import SvgDeleteModal from './svg-delete-modal';
import { compareVersions } from '@flx-blocks/helpers';
import { default as IconRender } from '../icons/icon-render';
export default function FlxbkIconPicker({
	value,
	onChange,
	label,
	placeholder = __('Select Icon', 'flx-blocks'),
	showSearch = true,
	renderFunc = null,
	className,
	theme = 'default',
	allowClear = false,
	icons = null,
}) {
	const [popoverAnchor, setPopoverAnchor] = useState();
	const [isVisible, setIsVisible] = useState(false);
	const [search, setSearch] = useState('');
	const [filter, setFilter] = useState('all');
	const [isOpen, setIsOpen] = useState(false);
	const [isDeleteOpen, setIsDeleteOpen] = useState(false);
	const [deleteId, setDeleteId] = useState(null);
	const [customSvgs, setCustomSvgs] = useState(false);
	const [customSvgTitles, setCustomSvgTitles] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	// Make sure user has pro and the appropriate version that has the rest endpoint to accept SVGs
	const hasPro = flxbk_params.pro && flxbk_params.pro === 'true' ? true : false;
	const proVersion = window?.fxbpData ? get(window.fxbpData, ['pVersion'], '1.0.0') : '1.0.0';
	const isSupportedProVersion = compareVersions(proVersion, '2.4.0') >= 0;
	const toggleVisible = () => {
		setIsVisible(!isVisible);
	};
	const debounceToggle = debounce(toggleVisible, 100);

	const getCustomSvgs = async (force = false) => {
		if (force || (hasPro && isSupportedProVersion && customSvgs === false && !isLoading)) {
			try {
				setIsLoading(true);
				const response = await fetchCustomSvgs(force);

				if (response.length > 0) {
					const svgIds = response.map((svg) => svg.id.toString());
					const svgTitles = {};
					response.forEach((item) => {
						svgTitles[item.id.toString()] = item.title.rendered.toLowerCase();
					});

					if (!isEqual(svgIds, customSvgs) && svgIds.length > 0) {
						setCustomSvgs(svgIds);
						setCustomSvgTitles(svgTitles);
					}
				} else {
					setCustomSvgs([]);
				}
			} catch (error) {
				setCustomSvgs([]);
				console.error('Failed to fetch custom SVGs (picker):', error);
			}
			setIsLoading(false);
		}
	};
	useEffect(() => {
		getCustomSvgs(), [];
	});

	const deleteCallback = () => {
		getCustomSvgs(true);
		setDeleteId(null);
	};

	const addCallback = ( postId ) => {
		onChange('fxb-custom-' + postId.toString() );
		getCustomSvgs(true);
	};

	const translatedCustomSvgString = __('My Icons', 'flx-blocks');

	const iconNames = useMemo(() => {
		if (icons) {
			const iconNames = icons.map((slug) => {
				return { value: slug, label: slug };
			});

			if (customSvgs.length > 0) {
				return { [translatedCustomSvgString]: customSvgs, ...iconNames };
			} else if (hasPro && isSupportedProVersion) {
				return { [translatedCustomSvgString]: ['placeholder'], ...iconNames };
			} else {
				return iconNames;
			}
		}
		const svgs = applyFilters('flxbk.icon_options_names', flxbk_params.icon_names);

		if (customSvgs.length > 0) {
			return { [translatedCustomSvgString]: customSvgs, ...svgs };
		} else if (hasPro && isSupportedProVersion) {
			return { [translatedCustomSvgString]: ['placeholder'], ...svgs };
		}

		return svgs;
	}, [flxbk_params.icon_names, icons, customSvgs]);

	const iconOptions = useMemo(() => {
		return applyFilters('flxbk.icon_options', {
			...flxbk_params_ico.icons,
			...flxbk_params_fa.icons,
		});
	}, [flxbk_params_ico.icons, flxbk_params_fa.icons, customSvgs]);
	const iconFilterOptions = useMemo(() => {
		let options = Object.keys(iconNames).map((label, index) => {
			return { value: index, label: label };
		});

		return [{ value: 'all', label: __('Show All', 'flx-blocks') }, ...options];
	}, [flxbk_params.icon_names, iconNames]);

	const iconRenderFunc = useCallback(
		(iconSlug) => {
			// Using GenIcon directly is less overhead, but IconRender allows for custom SVGs to be fetched and rendered
			if (iconSlug.startsWith('fxb-custom')) {
				return <IconRender className={`fxt-svg-icon-single-${iconSlug}`} name={iconSlug} />;
			}

			return (
				<GenIcon className={`fxt-svg-icon-single-${iconSlug}`} name={iconSlug} icon={iconOptions[iconSlug]} />
			);
		},
		[iconOptions]
	);

	const iconRenderFunction = renderFunc ? renderFunc : iconRenderFunc;

	const results = useMemo(() => {
		let results = {};
		if (!icons) {
			const searchLower = search.toLowerCase();
			Object.keys(iconNames).map((label, groupIndex) => {
				if (filter === 'all' || groupIndex === parseInt(filter)) {
					{
						iconNames[label].map((icon, iconIndex) => {
							const iconLower = icon.toLowerCase();

							if (
								search === '' ||
								iconLower.includes(searchLower) ||
								(groupIndex === 0 &&
									has(customSvgTitles, iconLower.toString()) &&
									customSvgTitles[iconLower.toString()].includes(searchLower))
							) {
								results = {
									...results,
									[groupIndex]: {
										label: label,
										icons: { ...results[groupIndex]?.icons, [icon]: iconOptions[icon] },
									},
								};

								return icon;
							}
						});
					}
				}
			});
		}
		return results;
	}, [search, filter, iconNames]);

	return (
		<div className={'flxbk-icon-picker'}>
			<SvgAddModal isOpen={isOpen} setIsOpen={setIsOpen} callback={addCallback} proVersion={proVersion} />
			<SvgDeleteModal isOpen={isDeleteOpen} setIsOpen={setIsDeleteOpen} id={deleteId} callback={deleteCallback} />
			<div
				className={`flxbk-icon-picker-selection flxbk-icon-picker-theme-${theme ? theme : 'default'}${
					className ? ' ' + className : ''
				}`}
			>
				{label && (
					<div className="flxbk-icon-picker__title">
						<label className="components-base-control__label">{label}</label>
					</div>
				)}
				<div className="flxbk-icon-picker-toggle-wrap">
					<button
						onClick={() => debounceToggle()}
						ref={setPopoverAnchor}
						className={'flxbk-icon-picker-link flxbk-icon-picker-selection-toggle'}
					>
						<span
							className={`flxbk-icon-picker-selection-value${
								!value ? ' flxbk-icon-picker-placeholder' : ''
							}`}
						>
							{value ? iconRenderFunction(value) : placeholder}
						</span>
						<span className="flxbk-icon-picker-selection-arrow">
							<Icon icon={chevronDown}></Icon>
						</span>
					</button>
					{value && allowClear && (
						<button
							className="flxbk-icon-picker-clear"
							onClick={() => {
								onChange('');
								setIsVisible(false);
							}}
						>
							<Icon icon={closeSmall}></Icon>
						</button>
					)}
				</div>
			</div>

			{isVisible && (
				<Popover
					headerTitle={__('Select Icon', 'flx-blocks')}
					noArrow={false}
					// expandOnMobile={true}
					onClose={debounceToggle}
					placement="bottom-end"
					anchor={popoverAnchor}
					className={`flxbk-icon-picker-pop-selection flxbk-icon-picker-pop-theme-${
						theme ? theme : 'default'
					}`}
				>
					<div className="flxbk-icon-picker-container">
						{showSearch && (
							<div className={'flxbk-icon-picker-search'}>
								<TextControl
									label={__('Search Icons', 'flx-blocks')}
									hideLabelFromVision={true}
									value={search}
									placeholder={__('Search Icons', 'flx-blocks')}
									onChange={(value) => setSearch(value)}
								/>
								<SelectControl
									label={__('Filter Icons', 'flx-blocks')}
									hideLabelFromVision={true}
									value={filter}
									options={iconFilterOptions}
									onChange={setFilter}
								/>
							</div>
						)}
						<div className={`flxbk-icon-picker-content${showSearch ? ' has-search' : ''}`}>
							{icons && (
								<div className="flxbk-icon-grid-wrap">
									{icons.map((iconKey) => {
										return (
											<button
												className={'flxbk-icon-picker-link'}
												onClick={() => {
													onChange(iconKey);
													debounceToggle();
												}}
											>
												{iconRenderFunction(iconKey)}
											</button>
										);
									})}
								</div>
							)}
							{!icons && (
								<>
									{Object.keys(results).length === 0 && (
										<div style={{ padding: '15px' }}>
											<p>{__('No icons found', 'flx-blocks')}</p>
										</div>
									)}
									{Object.keys(results).map((groupKey) => {
										return (
											<PanelBody title={results[groupKey].label} key={groupKey}>
												<div className="flxbk-icon-grid-wrap">
													{results[groupKey].label === translatedCustomSvgString &&
														search === '' &&
														isSupportedProVersion &&
														hasPro && (
															<button
																className={'flxbk-icon-picker-link add-custom-svg'}
																onClick={() => {
																	setIsOpen(true);
																	debounceToggle();
																}}
															>
																<Icon icon={plus} />
															</button>
														)}
													{Object.keys(results[groupKey].icons).map((iconKey) => {
														if (results[groupKey].label === translatedCustomSvgString) {
															if (iconKey === 'placeholder') {
																return;
															}

															return (
																<div className={'fxb-custom-svg'}>
																	{hasPro && isSupportedProVersion && (
																		<div
																			className={'custom-svg-delete'}
																			onClick={() => {
																				setDeleteId(iconKey);
																				setIsDeleteOpen(true);
																			}}
																		>
																			<Icon icon={closeSmall} size={20} />
																		</div>
																	)}
																	<button
																		className={'flxbk-icon-picker-link'}
																		key={results[groupKey].label + iconKey}
																		onClick={() => {
																			onChange('fxb-custom-' + iconKey);
																			debounceToggle();
																		}}
																	>
																		{iconRenderFunction('fxb-custom-' + iconKey)}
																	</button>
																</div>
															);
														} else {
															return (
																<button
																	className={'flxbk-icon-picker-link'}
																	key={results[groupKey].label + iconKey}
																	onClick={() => {
																		onChange(iconKey);
																		debounceToggle();
																	}}
																>
																	{iconRenderFunction(iconKey)}
																</button>
															);
														}
													})}
												</div>
											</PanelBody>
										);
									})}
								</>
							)}
						</div>
					</div>
				</Popover>
			)}
		</div>
	);
}

const fetchCustomSvgs = async (cacheBust = false) => {
	const params = {
		per_page: 100,
	};

	if (cacheBust) {
		params.cache_bust = new Date().getTime();
	}

	const urlParams = new URLSearchParams(params);

	const response = await fetch(`/wp-json/wp/v2/flxbk_custom_svg?${urlParams.toString()}`, {
		method: 'GET',
	});

	if (!response.ok) {
		throw new Error('Network response was not ok');
	}

	return response.json();
};
