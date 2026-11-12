/**
 * External dependencies
 */
import { every } from 'lodash';

/**
 * WordPress dependencies
 */
import { createBlobURL } from '@wordpress/blob';
import { createBlock, getBlockAttributes } from '@wordpress/blocks';
import { dispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { __ } from '@wordpress/i18n';

const transforms = {
	from: [
		{
			type: 'block',
			blocks: ['flx/advanced-form-checkbox', 'flx/advanced-form-select'],
			transform: (attributes) => {
				return createBlock('flx/advanced-form-radio', attributes);
			},
		},
	],
	to: [
		{
			type: 'block',
			blocks: ['flx/advanced-form-radio'],
			transform: (attributes) => {
				return createBlock('flx/advanced-form-checkbox', attributes);
			},
		},
		{
			type: 'block',
			blocks: ['flx/advanced-form-radio'],
			transform: (attributes) => {
				return createBlock('flx/advanced-form-select', attributes);
			},
		},
	],
};

export default transforms;
