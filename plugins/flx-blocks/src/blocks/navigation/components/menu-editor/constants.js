export const EDITABLE_BLOCK_ATTRIBUTES = [
	{
		name: 'core/paragraph',
		attributes: [
			{
				name: 'content',
				type: 'string',
			},
		],
	},
	{
		name: 'flx/navigation-link',
		attributes: [
			{
				name: 'label',
				type: 'string',
			},
			{
				name: 'url',
				type: 'string',
			},
		],
	},
	{
		name: 'flx/advancedheading',
		attributes: [
			{
				name: 'content',
				type: 'string',
			},
		],
	},
];

export const PREVENT_BLOCK_DELETE = ['flx/column', 'flx/rowlayout'];
