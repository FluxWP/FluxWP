import { __ } from '@wordpress/i18n';
import { createBlock } from '@wordpress/blocks';

export const HEADER_INNERBLOCK_DEFAULTS = [
	createBlock('flx/header-container-desktop', {}, [
		createBlock('flx/header-row', { metadata: { name: __('Top Row', 'flx-blocks') }, location: 'top' }, [
			createBlock(
				'flx/header-section',
				{ metadata: { name: __('Left Section', 'flx-blocks') }, location: 'left' },
				[
					createBlock(
						'flx/header-column',
						{ metadata: { name: __('Left', 'flx-blocks') }, location: 'left' },
						[]
					),
					createBlock(
						'flx/header-column',
						{ metadata: { name: __('Center Left', 'flx-blocks') }, location: 'center-left' },
						[]
					),
				]
			),
			createBlock('flx/header-column', {
				metadata: { name: __('Center', 'flx-blocks') },
				location: 'center',
			}),
			createBlock(
				'flx/header-section',
				{ metadata: { name: __('Right Section', 'flx-blocks') }, location: 'right' },
				[
					createBlock(
						'flx/header-column',
						{ metadata: { name: __('Center Right', 'flx-blocks') }, location: 'center-right' },
						[]
					),
					createBlock(
						'flx/header-column',
						{ metadata: { name: __('Right', 'flx-blocks') }, location: 'right' },
						[]
					),
				]
			),
		]),
		createBlock(
			'flx/header-row',
			{ metadata: { name: __('Middle Row', 'flx-blocks') }, location: 'center' },
			[
				createBlock(
					'flx/header-section',
					{ metadata: { name: __('Left Section', 'flx-blocks') }, location: 'left' },
					[
						createBlock(
							'flx/header-column',
							{ metadata: { name: __('Left', 'flx-blocks') }, location: 'left' },
							[]
						),
						createBlock(
							'flx/header-column',
							{ metadata: { name: __('Center Left', 'flx-blocks') }, location: 'center-left' },
							[]
						),
					]
				),
				createBlock('flx/header-column', {
					metadata: { name: __('Center', 'flx-blocks') },
					location: 'center',
				}),
				createBlock(
					'flx/header-section',
					{ metadata: { name: __('Right Section', 'flx-blocks') }, location: 'right' },
					[
						createBlock(
							'flx/header-column',
							{ metadata: { name: __('Center Right', 'flx-blocks') }, location: 'center-right' },
							[]
						),
						createBlock(
							'flx/header-column',
							{ metadata: { name: __('Right', 'flx-blocks') }, location: 'right' },
							[]
						),
					]
				),
			]
		),
		createBlock(
			'flx/header-row',
			{ metadata: { name: __('Bottom Row', 'flx-blocks') }, location: 'bottom' },
			[
				createBlock(
					'flx/header-section',
					{ metadata: { name: __('Left Section', 'flx-blocks') }, location: 'left' },
					[
						createBlock(
							'flx/header-column',
							{ metadata: { name: __('Left', 'flx-blocks') }, location: 'left' },
							[]
						),
						createBlock(
							'flx/header-column',
							{ metadata: { name: __('Center Left', 'flx-blocks') }, location: 'center-left' },
							[]
						),
					]
				),
				createBlock('flx/header-column', {
					metadata: { name: __('Center', 'flx-blocks') },
					location: 'center',
				}),
				createBlock(
					'flx/header-section',
					{ metadata: { name: __('Right Section', 'flx-blocks') }, location: 'right' },
					[
						createBlock(
							'flx/header-column',
							{ metadata: { name: __('Center Right', 'flx-blocks') }, location: 'center-right' },
							[]
						),
						createBlock(
							'flx/header-column',
							{ metadata: { name: __('Right', 'flx-blocks') }, location: 'right' },
							[]
						),
					]
				),
			]
		),
	]),
	createBlock('flx/header-container-tablet', {}, [
		createBlock('flx/header-row', { metadata: { name: __('Top Row', 'flx-blocks') }, location: 'top' }, [
			createBlock(
				'flx/header-column',
				{ metadata: { name: __('Left', 'flx-blocks') }, location: 'tablet-left' },
				[]
			),
			createBlock(
				'flx/header-column',
				{ metadata: { name: __('Center', 'flx-blocks') }, location: 'tablet-center' },
				[]
			),
			createBlock(
				'flx/header-column',
				{ metadata: { name: __('Right', 'flx-blocks') }, location: 'tablet-right' },
				[]
			),
		]),
		createBlock(
			'flx/header-row',
			{ metadata: { name: __('Middle Row', 'flx-blocks') }, location: 'center' },
			[
				createBlock(
					'flx/header-column',
					{ metadata: { name: __('Left', 'flx-blocks') }, location: 'tablet-left' },
					[]
				),
				createBlock(
					'flx/header-column',
					{ metadata: { name: __('Center', 'flx-blocks') }, location: 'tablet-center' },
					[]
				),
				createBlock(
					'flx/header-column',
					{ metadata: { name: __('Right', 'flx-blocks') }, location: 'tablet-right' },
					[]
				),
			]
		),
		createBlock(
			'flx/header-row',
			{ metadata: { name: __('Bottom Row', 'flx-blocks') }, location: 'bottom' },
			[
				createBlock(
					'flx/header-column',
					{ metadata: { name: __('Left', 'flx-blocks') }, location: 'tablet-left' },
					[]
				),
				createBlock(
					'flx/header-column',
					{ metadata: { name: __('Center', 'flx-blocks') }, location: 'tablet-center' },
					[]
				),
				createBlock(
					'flx/header-column',
					{ metadata: { name: __('Right', 'flx-blocks') }, location: 'tablet-right' },
					[]
				),
			]
		),
	]),
	createBlock('flx/off-canvas', {}, []),
];

export const HEADER_ALLOWED_BLOCKS = [
	'flx/header-container-desktop',
	'flx/header-container-tablet',
	'flx/header-row',
	'flx/header-column',
	'flx/off-canvas',
];
