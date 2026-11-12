/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';
export const OPTIONS_MAP = [
	{
		value: '0',
		label: __('None', 'flx-blocks'),
		size: 0,
		name: __('None', 'flx-blocks'),
	},
	{
		value: 'xxs',
		output: 'var(--global-fxb-spacing-xxs, 0.5rem)',
		size: 8,
		label: __('XXS', 'flx-blocks'),
		name: __('2X Small', 'flx-blocks'),
	},
	{
		value: 'xs',
		output: 'var(--global-fxb-spacing-xs, 1rem)',
		size: 16,
		label: __('XS', 'flx-blocks'),
		name: __('X Small', 'flx-blocks'),
	},
	{
		value: 'sm',
		output: 'var(--global-fxb-spacing-sm, 1.5rem)',
		size: 24,
		label: __('SM', 'flx-blocks'),
		name: __('Small', 'flx-blocks'),
	},
	{
		value: 'md',
		output: 'var(--global-fxb-spacing-md, 2rem)',
		size: 32,
		label: __('MD', 'flx-blocks'),
		name: __('Medium', 'flx-blocks'),
	},
	{
		value: 'lg',
		output: 'var(--global-fxb-spacing-lg, 3rem)',
		size: 48,
		label: __('LG', 'flx-blocks'),
		name: __('Large', 'flx-blocks'),
	},
	{
		value: 'xl',
		output: 'var(--global-fxb-spacing-xl, 4rem)',
		size: 64,
		label: __('XL', 'flx-blocks'),
		name: __('X Large', 'flx-blocks'),
	},
	{
		value: 'xxl',
		output: 'var(--global-fxb-spacing-xxl, 5rem)',
		size: 80,
		label: __('XXL', 'flx-blocks'),
		name: __('2X Large', 'flx-blocks'),
	},
	{
		value: '3xl',
		output: 'var(--global-fxb-spacing-3xl, 6.5rem)',
		size: 104,
		label: __('3XL', 'flx-blocks'),
		name: __('3X Large', 'flx-blocks'),
	},
	{
		value: '4xl',
		output: 'var(--global-fxb-spacing-4xl, 8rem)',
		size: 128,
		label: __('4XL', 'flx-blocks'),
		name: __('4X Large', 'flx-blocks'),
	},
	{
		value: '5xl',
		output: 'var(--global-fxb-spacing-5xl, 10rem)',
		size: 160,
		label: __('5XL', 'flx-blocks'),
		name: __('5X Large', 'flx-blocks'),
	},
];
