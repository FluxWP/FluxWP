/* global wp */
/**
 * External dependencies
 */
import Select from 'react-select';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';
import FlxbkPostSelectTerms from '../post-select-terms-control';
import { useInstanceId } from '@wordpress/compose';
import { isArrayLike, has, isEmpty } from 'lodash';
import { tryParseJSON } from '@flx-blocks/helpers';

export default function TaxonomySelect({
	label,
	value,
	onChange,
	source,
	contextPost = null,
	className = null,
	termIsMulti = false,
	taxOnly = false,
	termIsOptional = false,
}) {
	const instanceId = useInstanceId(TaxonomySelect);
	const id = `inspector-taxonomy-select-control-${instanceId}`;
	const [isLoading, setIsLoading] = useState(true);
	const [taxonomies, setTaxonomies] = useState([]);
	let taxStart = '';
	if (taxOnly || (termIsOptional && typeof value == 'string')) {
		taxStart = value;
	} else {
		if (isArrayLike(value) && has(value, [0, 'value']) && !isEmpty(value)) {
			taxStart = value[0].value.split('|');
		} else if (value && !isEmpty(value)) {
			taxStart = value.split('|');
		} else {
			taxStart = [''];
		}
	}
	const [tax, setTax] = useState(typeof taxStart == 'object' ? taxStart[0] : taxStart);
	useEffect(() => {
		let theSource = source ? source : contextPost;
		if (wp.data.select('core/editor') && !theSource) {
			if (fxbpData.isFlxbkE && flxbkElementParams.previewPostID) {
				const postId = tryParseJSON(flxbkElementParams.previewPostID);
				theSource = postId && postId.id ? postId.id : '';
			} else {
				theSource = wp.data.select('core/editor').getCurrentPostId();
			}
		}
		const options = {
			source: theSource,
		};
		setIsLoading(true);
		apiFetch({
			path: addQueryArgs(window.flxbk_params.taxonomiesEndpoint, options),
		})
			.then((taxonomyItems) => {
				if (!taxonomyItems) {
					setTaxonomies([]);
				} else {
					setTaxonomies(taxonomyItems);
				}
				setIsLoading(false);
			})
			.catch(() => {
				setIsLoading(false);
				setTaxonomies([]);
			});
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
		<div className={`components-base-control fxb-taxonomy-select-control${className ? ' ' + className : ''}`}>
			{label && (
				<label htmlFor={id} className="components-taxonomy-select-control__label fxb-dynamic-components-label">
					{label}
				</label>
			)}
			<Select
				options={taxonomies}
				id={id}
				className="fxb-dynamic-select"
				classNamePrefix="kbp"
				value={'' !== tax ? taxonomies.filter(({ value }) => value === tax) : ''}
				isMulti={false}
				isSearchable={true}
				isClearable={true}
				menuPortalTarget={document.body}
				styles={customStyles}
				maxMenuHeight={200}
				placeholder={__('Select Taxonomy', 'flx-blocks')}
				onChange={(val) => {
					if (taxOnly || termIsOptional) {
						if (!val) {
							setTax('');
							onChange('');
						} else {
							setTax(val.value);
							onChange(val.value);
						}
					} else {
						if (!val) {
							setTax('');
						} else {
							setTax(val.value);
						}
					}
				}}
			/>
			{'' !== tax && !taxOnly && (
				<FlxbkPostSelectTerms
					source={tax}
					value={value}
					isMulti={termIsMulti}
					onChange={(val) => {
						if (val && !isEmpty(val)) {
							onChange(val);
						} else if (termIsOptional && tax) {
							onChange(tax);
						} else {
							onChange('');
						}
					}}
				/>
			)}
		</div>
	);
}
