import { __ } from '@wordpress/i18n';

export const fieldBlocks = [
	'flx/advanced-form-text',
	'flx/advanced-form-textarea',
	'flx/advanced-form-select',
	'flx/advanced-form-radio',
	'flx/advanced-form-file',
	'flx/advanced-form-time',
	'flx/advanced-form-date',
	'flx/advanced-form-telephone',
	'flx/advanced-form-checkbox',
	'flx/advanced-form-email',
	'flx/advanced-form-accept',
	'flx/advanced-form-number',
	'flx/advanced-form-hidden',
];

export const fieldAutoFillOptions = [
	{ value: '', label: __('Default', 'flx-blocks') },
	{ value: 'name', label: __('Name', 'flx-blocks') },
	{ value: 'given-name', label: __('First Name', 'flx-blocks') },
	{ value: 'family-name', label: __('Last Name', 'flx-blocks') },
	{ value: 'email', label: __('Email', 'flx-blocks') },
	{ value: 'organization', label: __('Organization', 'flx-blocks') },
	{ value: 'street-address', label: __('Street Address', 'flx-blocks') },
	{ value: 'address-line1', label: __('Address Line 1', 'flx-blocks') },
	{ value: 'address-line2', label: __('Address Line 2', 'flx-blocks') },
	{ value: 'country-name', label: __('Country Name', 'flx-blocks') },
	{ value: 'postal-code', label: __('Postal Code', 'flx-blocks') },
	{ value: 'tel', label: __('Telephone', 'flx-blocks') },
	{ value: 'custom', label: __('Custom', 'flx-blocks') },
	{ value: 'off', label: __('Off', 'flx-blocks') },
];
