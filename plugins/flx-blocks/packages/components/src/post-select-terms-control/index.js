/* global wp */
/**
 * External dependencies
 */
import Select from 'react-select';
import { __ } from '@wordpress/i18n';
import { useState, useRef, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';

export default function FlxbkPostSelectTerms({ value, onChange, source, isMulti = false, termOnly = false }) {
	const [isLoading, setIsLoading] = useState(true);
	const [terms, setTerms] = useState([]);
	const [page, setPage] = useState(1);
	const [hasMore, setHasMore] = useState(false);
	const theValue = value;
	const termOnlyValue =
		'array' == typeof value || 'object' == typeof value
			? value.map((option) => {
					const optValWithSource = source + '|' + option.value;
					return { value: optValWithSource, label: option.label };
			  })
			: value;

	useEffect(() => {
		if (
			source &&
			typeof window.flxbk_params.taxonomies[source] != 'undefined' &&
			window.flxbk_params.taxonomies[source]
		) {
			setTerms(Array.from(window.flxbk_params.taxonomies[source]));
			setIsLoading(false);
		} else {
			const options = {
				source: source,
				page: page,
				per_page: 50,
			};
			setIsLoading(true);
			apiFetch({
				path: addQueryArgs(window.flxbk_params.termEndpoint, options),
			})
				.then((taxonomyItems) => {
					if (!taxonomyItems) {
						setTerms([]);
						window.flxbk_params.taxonomies[source] = [];
					} else {
						setTerms(taxonomyItems);
						window.flxbk_params.taxonomies[source] = taxonomyItems;
					}
					setIsLoading(false);
				})
				.catch(() => {
					setIsLoading(false);
					setTerms([]);
					window.flxbk_params.taxonomies[source] = [];
				});
		}
	}, [source]);
	if (isLoading) {
		return <Spinner />;
	}
	const customStyles = {
		menuPortal: (provided) => ({
			...provided,
			zIndex: 99999999,
		}),
	};
	return (
		<div className={'fxb-inner-term-select-wrap'}>
			<Select
				options={terms}
				className="fxb-dynamic-select"
				classNamePrefix="kbp"
				value={
					isMulti
						? termOnly
							? termOnlyValue
							: value
						: '' !== value
						? terms.filter(({ value }) => value === theValue)
						: ''
				}
				isMulti={isMulti}
				isSearchable={true}
				isClearable={true}
				menuPortalTarget={document.body}
				styles={customStyles}
				maxMenuHeight={200}
				placeholder={__('Select Term', 'flx-blocks')}
				onChange={(val) => {
					if (!val) {
						onChange('');
					} else if (isMulti) {
						var toReturn = val;
						if (termOnly) {
							toReturn = val.map((option) => {
								const optValTermOnly = option.value.split('|')?.[1];
								return { value: optValTermOnly, label: option.label };
							});
						}
						onChange(toReturn);
					} else {
						if (termOnly) {
							onChange(val.value.split('|')?.[1]);
						}
						onChange(val.value);
					}
				}}
			/>
		</div>
	);
}
