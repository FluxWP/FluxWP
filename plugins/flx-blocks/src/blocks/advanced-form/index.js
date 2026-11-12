import { registerBlockType } from '@wordpress/blocks';
import { __, _x } from '@wordpress/i18n';

import { formBlockIcon } from '@flx-blocks/icons';

import './fields/submit/style.scss';
import './style.scss';
import './fields/accept/index';
import './fields/captcha/index';
import './fields/checkbox/index';
import './fields/date/index';
import './fields/email/index';
import './fields/file/index';
import './fields/hidden/index';
import './fields/number/index';
import './fields/radio/index';
import './fields/select/index';
import './fields/submit/index';
import './fields/telephone/index';
import './fields/text/index';
import './fields/textarea/index';
import './fields/time/index';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';

import { InnerBlocks } from '@wordpress/block-editor';
import transforms from './transforms';

registerBlockType('flx/advanced-form', {
	...metadata,
	title: _x('Form (Adv)', 'block title', 'flx-blocks'),
	description: _x(
		'Create an advanced contact or marketing form for your website.',
		'block description',
		'flx-blocks'
	),
	keywords: [__('contact', 'flx-blocks'), __('form', 'flx-blocks'), 'kb'],
	icon: {
		src: formBlockIcon,
	},
	transforms,
	edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
});
