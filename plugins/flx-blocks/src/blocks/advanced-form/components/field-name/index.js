/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Placeholder, Button, TextControl, ButtonGroup, Notice } from '@wordpress/components';
import { FlxbkRadioButtons } from '@flx-blocks/components';
import { size, get, isEqual } from 'lodash';
import { useState, useCallback, useEffect } from '@wordpress/element';
import verifyUniqueFieldName from './../verify-unique-field-name';
import { useEntityBlockEditor, useEntityProp } from '@wordpress/core-data';
import { withSelect, withDispatch, useSelect, useDispatch } from '@wordpress/data';
export default function FieldName({ value, onChange, uniqueID }) {
	const [hasUniqueFieldName, setHasUniqueFieldName] = useState(true);
	const [conflictFields, setConflictFields] = useState([]);
	const [fields] = useFormMeta('_flxbk_form_fields');
	useEffect(() => {
		if (value) {
			const tempConflictFields = verifyUniqueFieldName(fields, uniqueID, value);
			setConflictFields(tempConflictFields);
			if (tempConflictFields.length > 0) {
				setHasUniqueFieldName(false);
			} else {
				setHasUniqueFieldName(true);
			}
		} else {
			setHasUniqueFieldName(true);
			setConflictFields([]);
		}
	}, [value]);
	return (
		<div className={`fxb-form-field-name-wrap ${!hasUniqueFieldName ? 'fxb-field-has-warning' : ''}`}>
			<TextControl
				label={__('Field Name', 'flx-blocks')}
				help={__(
					'This is the name attribute that is applied to the html input tag. Names must be unique',
					'flx-blocks'
				)}
				value={value}
				onChange={(name) => onChange(name.replace(/[^a-z0-9-_]/gi, ''))}
			/>
			{!hasUniqueFieldName && (
				<Notice status="error" isDismissible={false}>
					{__('Field name must be unique, Conflicts:', 'flx-blocks')}
					<ul>
						{conflictFields.map((item, index) => (
							<li key={index}>
								{__('Field Label:', 'flx-blocks')} {item.label}
							</li>
						))}
					</ul>
				</Notice>
			)}
		</div>
	);
}

function useFormProp(prop) {
	return useEntityProp('postType', 'flxbk_form', prop);
}
function useFormMeta(key) {
	const [meta, setMeta] = useFormProp('meta');

	return [
		meta[key],
		useCallback(
			(newValue) => {
				setMeta({ ...meta, [key]: newValue });
			},
			[key, setMeta]
		),
	];
}
