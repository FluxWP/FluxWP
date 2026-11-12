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
			blocks: [
				'flx/advanced-form-textarea',
				'flx/advanced-form-telephone',
				'flx/advanced-form-number',
			],
			transform: (attributes) => {
				return createBlock('flx/advanced-form-text', attributes);
			},
		},
	],
	to: [
		{
			type: 'block',
			blocks: ['flx/advanced-form-textarea'],
			transform: (attributes) => {
				return createBlock('flx/advanced-form-textarea', attributes);
			},
		},
		{
			type: 'block',
			blocks: ['flx/advanced-form-telephone'],
			transform: (attributes) => {
				return createBlock('flx/advanced-form-telephone', attributes);
			},
		},
		{
			type: 'block',
			blocks: ['flx/advanced-form-number'],
			transform: (attributes) => {
				return createBlock('flx/advanced-form-number', attributes);
			},
		},
	],
};

export default transforms;
