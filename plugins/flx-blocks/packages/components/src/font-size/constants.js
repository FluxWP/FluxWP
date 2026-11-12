/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';
export const OPTIONS_MAP = [
	{
		value: 'sm',
		output: 'var(--global-fxb-font-size-sm, 0.9rem)',
		size: 14,
		label: __('SM', 'flx-blocks'),
		name: __('Small', 'flx-blocks'),
	},
	{
		value: 'md',
		output: 'var(--global-fxb-font-size-md, 1.2rem)',
		size: 32,
		label: __('MD', 'flx-blocks'),
		name: __('Medium', 'flx-blocks'),
	},
	{
		value: 'lg',
		output: 'var(--global-fxb-font-size-lg, 3rem)',
		size: 48,
		label: __('LG', 'flx-blocks'),
		name: __('Large', 'flx-blocks'),
	},
	{
		value: 'xl',
		output: 'var(--global-fxb-font-size-xl, 4rem)',
		size: 64,
		label: __('XL', 'flx-blocks'),
		name: __('X Large', 'flx-blocks'),
	},
	{
		value: 'xxl',
		output: 'var(--global-fxb-font-size-xxl, 5rem)',
		size: 80,
		label: __('XXL', 'flx-blocks'),
		name: __('2X Large', 'flx-blocks'),
	},
];
