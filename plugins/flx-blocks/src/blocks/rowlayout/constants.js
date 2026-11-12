/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';

export const BLEND_OPTIONS = [
	{ value: 'normal', label: __('Normal', 'flx-blocks') },
	{ value: 'multiply', label: __('Multiply', 'flx-blocks') },
	{ value: 'screen', label: __('Screen', 'flx-blocks') },
	{ value: 'overlay', label: __('Overlay', 'flx-blocks') },
	{ value: 'darken', label: __('Darken', 'flx-blocks') },
	{ value: 'lighten', label: __('Lighten', 'flx-blocks') },
	{ value: 'color-dodge', label: __('Color Dodge', 'flx-blocks') },
	{ value: 'color-burn', label: __('Color Burn', 'flx-blocks') },
	{ value: 'difference', label: __('Difference', 'flx-blocks') },
	{ value: 'exclusion', label: __('Exclusion', 'flx-blocks') },
	{ value: 'hue', label: __('Hue', 'flx-blocks') },
	{ value: 'saturation', label: __('Saturation', 'flx-blocks') },
	{ value: 'color', label: __('Color', 'flx-blocks') },
	{ value: 'luminosity', label: __('Luminosity', 'flx-blocks') },
];
export const SPACING_SIZES_MAP = [
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

//Mapping of column amounts and their colLayout values to what each column width should be.
export const COLUMN_WIDTH_MAP = {
	1: {
		equal: [100],
	},
	2: {
		'left-golden': [66.67, 33.33],
		'right-golden': [33.33, 66.67],
		equal: [50, 50],
	},
	3: {
		'first-row': [100, 50, 50],
		'left-half': [50, 25, 25],
		'right-half': [25, 25, 50],
		'center-half': [25, 50, 25],
		'center-wide': [20, 60, 20],
		'center-exwide': [15, 70, 15],
		equal: [33.33, 33.33, 33.33],
	},
	4: {
		'left-forty': [40, 20, 20, 20],
		'right-forty': [20, 20, 20, 40],
		equal: [25, 25, 25, 25],
	},
	5: {
		equal: [20, 20, 20, 20, 20],
	},
	6: {
		equal: [16.66, 16.66, 16.66, 16.66, 16.66, 16.66],
	},
};

export const PADDING_RESIZE_MAP = [0, 8, 16, 24, 32, 48, 64, 80, 104, 128, 160];
