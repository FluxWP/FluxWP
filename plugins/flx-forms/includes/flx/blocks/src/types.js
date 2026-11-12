/**
 * Every Flux Forms element type that has a block.
 *
 * key      Flux Forms row `type`.
 * slug     Block name after `flxf/` (block names are lowercase).
 * kind     How the editor previews it (see Preview in fields.js).
 * group    Inserter category.
 * label    Default label of a new field (false: the element has no label).
 * defaults Row keys a new field starts with (same as the Flux Forms panel).
 * settings Row keys the block edits, besides label / placeholder / help / required / width.
 * options  The field has option rows ('option', or 'r_matrix' for table rows).
 */
import { __ } from '@wordpress/i18n';

const LEN = [
	{ key: 'milen', type: 'number', label: __('Minimum length', 'flx-forms') },
	{ key: 'mlen', type: 'number', label: __('Maximum length', 'flx-forms') },
];
const DEFAULT_VALUE = { key: 'value', type: 'text', label: __('Default value', 'flx-forms') };
const COLUMNS = {
	key: 'op_style',
	type: 'select',
	label: __('Columns', 'flx-forms'),
	options: [
		['1', __('One', 'flx-forms')],
		['2', __('Two', 'flx-forms')],
		['3', __('Three', 'flx-forms')],
	],
};
const MULTI = [
	{ key: 'minSelect', type: 'number', label: __('Minimum choices', 'flx-forms') },
	{ key: 'maxSelect', type: 'number', label: __('Maximum choices', 'flx-forms') },
];
const FILE_TYPES = {
	key: 'file',
	type: 'select',
	label: __('Allowed files', 'flx-forms'),
	options: [
		['allformat', __('All supported files', 'flx-forms')],
		['image', __('Images', 'flx-forms')],
		['document', __('Documents', 'flx-forms')],
		['media', __('Audio and video', 'flx-forms')],
		['zip', __('Archives (zip, rar)', 'flx-forms')],
		['customize', __('Custom list', 'flx-forms')],
	],
};
const FILE_CUSTOM = {
	key: 'file_ctype',
	type: 'text',
	label: __('Custom extensions', 'flx-forms'),
	help: __('Comma separated, for example: pdf, docx, png', 'flx-forms'),
	when: (e) => 'customize' === e.file,
};
const FILE_SIZE = { key: 'max_fsize', type: 'number', label: __('Maximum file size (MB)', 'flx-forms') };
const RECORDER = [
	{ key: 'max_duration', type: 'number', label: __('Maximum duration (seconds)', 'flx-forms') },
	FILE_SIZE,
	{ key: 'rec_countdown', type: 'number', label: __('Countdown before recording (seconds)', 'flx-forms') },
	{ key: 'rec_download', type: 'toggle', label: __('Let the person download the recording', 'flx-forms') },
];

export const GROUPS = {
	basic: 'flux-forms',
	choice: 'flux-forms-choice',
	advanced: 'flux-forms-advanced',
	payment: 'flux-forms-payment',
	content: 'flux-forms-content',
};

export const TYPES = {
	// Basic -------------------------------------------------------------
	text: {
		title: __('Text', 'flx-forms'),
		icon: 'editor-textcolor',
		group: 'basic',
		kind: 'input',
		label: __('Full name', 'flx-forms'),
		settings: [DEFAULT_VALUE, ...LEN],
	},
	email: {
		title: __('Email', 'flx-forms'),
		icon: 'email',
		group: 'basic',
		kind: 'input',
		label: __('Email', 'flx-forms'),
		settings: [DEFAULT_VALUE],
	},
	tel: { title: __('Phone', 'flx-forms'), icon: 'phone', group: 'basic', kind: 'input', label: __('Phone', 'flx-forms'), settings: [DEFAULT_VALUE, ...LEN] },
	mobile: {
		title: __('Mobile (with country code)', 'flx-forms'),
		icon: 'smartphone',
		group: 'basic',
		kind: 'input',
		label: __('Mobile', 'flx-forms'),
		settings: [],
	},
	textarea: {
		title: __('Long text', 'flx-forms'),
		icon: 'editor-paragraph',
		group: 'basic',
		kind: 'textarea',
		label: __('Message', 'flx-forms'),
		settings: [DEFAULT_VALUE, ...LEN],
	},
	number: {
		title: __('Number', 'flx-forms'),
		icon: 'editor-ol',
		group: 'basic',
		kind: 'input',
		label: __('Number', 'flx-forms'),
		settings: [
			DEFAULT_VALUE,
			{ key: 'milen', type: 'number', label: __('Minimum', 'flx-forms') },
			{ key: 'mlen', type: 'number', label: __('Maximum', 'flx-forms') },
		],
	},
	password: { title: __('Password', 'flx-forms'), icon: 'lock', group: 'basic', kind: 'input', label: __('Password', 'flx-forms'), settings: LEN },
	url: { title: __('Web address', 'flx-forms'), icon: 'admin-links', group: 'basic', kind: 'input', label: __('Website', 'flx-forms'), settings: [DEFAULT_VALUE] },
	date: {
		title: __('Date', 'flx-forms'),
		icon: 'calendar-alt',
		group: 'basic',
		kind: 'input',
		label: __('Date', 'flx-forms'),
		settings: [
			{ key: 'milen', type: 'text', label: __('Earliest date', 'flx-forms'), help: __('YYYY-MM-DD, or 1 for today.', 'flx-forms') },
			{ key: 'mlen', type: 'text', label: __('Latest date', 'flx-forms'), help: __('YYYY-MM-DD, or 1 for today.', 'flx-forms') },
		],
	},
	range: {
		title: __('Slider', 'flx-forms'),
		icon: 'leftright',
		group: 'basic',
		kind: 'range',
		label: __('How much?', 'flx-forms'),
		settings: [
			{ key: 'milen', type: 'number', label: __('Minimum', 'flx-forms') },
			{ key: 'mlen', type: 'number', label: __('Maximum', 'flx-forms') },
			DEFAULT_VALUE,
		],
	},
	color: { title: __('Color', 'flx-forms'), icon: 'art', group: 'basic', kind: 'color', label: __('Color', 'flx-forms'), settings: [DEFAULT_VALUE] },

	// Choices -----------------------------------------------------------
	select: { title: __('Dropdown', 'flx-forms'), icon: 'menu-alt', group: 'choice', kind: 'select', label: __('Choose one', 'flx-forms'), options: 'option', settings: [] },
	radio: { title: __('Single choice', 'flx-forms'), icon: 'marker', group: 'choice', kind: 'choices', label: __('Choose one', 'flx-forms'), options: 'option', settings: [COLUMNS] },
	checkbox: { title: __('Multiple choice', 'flx-forms'), icon: 'yes-alt', group: 'choice', kind: 'choices', label: __('Choose any', 'flx-forms'), options: 'option', settings: [COLUMNS] },
	multiselect: {
		title: __('Multi-select list', 'flx-forms'),
		icon: 'list-view',
		group: 'choice',
		kind: 'select',
		label: __('Choose any', 'flx-forms'),
		options: 'option',
		defaults: { maxSelect: 2, minSelect: 0 },
		settings: MULTI,
	},
	imgRadio: {
		slug: 'image-choice',
		title: __('Image choice', 'flx-forms'),
		icon: 'format-gallery',
		group: 'choice',
		kind: 'images',
		label: __('Choose one', 'flx-forms'),
		options: 'option',
		optionFields: ['src', 'sub_value'],
		settings: [COLUMNS],
	},
	chlCheckBox: {
		slug: 'checklist',
		title: __('Checklist with quantity', 'flx-forms'),
		icon: 'editor-ul',
		group: 'choice',
		kind: 'choices',
		label: __('Choose any', 'flx-forms'),
		options: 'option',
		defaults: { pholder_chl_value: __('Quantity', 'flx-forms') },
		settings: [{ key: 'pholder_chl_value', type: 'text', label: __('Quantity placeholder', 'flx-forms') }, COLUMNS],
	},
	chlRadio: {
		slug: 'choice-quantity',
		title: __('Single choice with quantity', 'flx-forms'),
		icon: 'editor-ul',
		group: 'choice',
		kind: 'choices',
		label: __('Choose one', 'flx-forms'),
		options: 'option',
		defaults: { pholder_chl_value: __('Quantity', 'flx-forms') },
		settings: [{ key: 'pholder_chl_value', type: 'text', label: __('Quantity placeholder', 'flx-forms') }, COLUMNS],
	},
	yesNo: {
		slug: 'yes-no',
		title: __('Yes / No', 'flx-forms'),
		icon: 'thumbs-up',
		group: 'choice',
		kind: 'yesno',
		label: __('Do you agree?', 'flx-forms'),
		defaults: { button_1_text: __('Yes', 'flx-forms'), button_2_text: __('No', 'flx-forms') },
		settings: [
			{ key: 'button_1_text', type: 'text', label: __('First button', 'flx-forms') },
			{ key: 'button_2_text', type: 'text', label: __('Second button', 'flx-forms') },
		],
	},
	switch: {
		title: __('Switch', 'flx-forms'),
		icon: 'controls-pause',
		group: 'choice',
		kind: 'switch',
		label: __('Turn on', 'flx-forms'),
		defaults: { on: __('On', 'flx-forms'), off: __('Off', 'flx-forms') },
		settings: [
			{ key: 'on', type: 'text', label: __('On text', 'flx-forms') },
			{ key: 'off', type: 'text', label: __('Off text', 'flx-forms') },
		],
	},
	trmCheckbox: {
		slug: 'terms',
		title: __('Terms consent', 'flx-forms'),
		icon: 'privacy',
		group: 'choice',
		kind: 'choices',
		label: __('Terms', 'flx-forms'),
		options: 'option',
		optionDefaults: [__('I accept the terms and conditions.', 'flx-forms')],
		settings: [],
	},
	conturyList: {
		slug: 'country',
		title: __('Country', 'flx-forms'),
		icon: 'flag',
		group: 'choice',
		kind: 'select',
		label: __('Country', 'flx-forms'),
		options: 'option',
		settings: [],
	},
	stateProvince: {
		slug: 'state',
		title: __('State / province', 'flx-forms'),
		icon: 'location-alt',
		group: 'choice',
		kind: 'select',
		label: __('State / province', 'flx-forms'),
		options: 'option',
		settings: [{ key: 'country', type: 'text', label: __('Country code', 'flx-forms'), help: __('Two letters, for example TR.', 'flx-forms') }],
	},
	cityList: {
		slug: 'city',
		title: __('City', 'flx-forms'),
		icon: 'location',
		group: 'choice',
		kind: 'select',
		label: __('City', 'flx-forms'),
		options: 'option',
		settings: [
			{ key: 'country', type: 'text', label: __('Country code', 'flx-forms'), help: __('Two letters, for example TR.', 'flx-forms') },
			{ key: 'statePov', type: 'text', label: __('State / province code', 'flx-forms') },
		],
	},

	// Advanced ----------------------------------------------------------
	file: {
		title: __('File upload', 'flx-forms'),
		icon: 'upload',
		group: 'advanced',
		kind: 'file',
		label: __('Attach a file', 'flx-forms'),
		defaults: { file: 'allformat', value: 'allformat' },
		settings: [FILE_TYPES, FILE_CUSTOM, FILE_SIZE],
	},
	dadfile: {
		slug: 'file-drop',
		title: __('File drop zone', 'flx-forms'),
		icon: 'upload',
		group: 'advanced',
		kind: 'file',
		label: __('Drop your files here', 'flx-forms'),
		defaults: { file: 'allformat', value: 'allformat', icon: 'bi-cloud-arrow-up-fill' },
		settings: [FILE_TYPES, FILE_CUSTOM, FILE_SIZE],
	},
	rating: { title: __('Star rating', 'flx-forms'), icon: 'star-filled', group: 'advanced', kind: 'rating', label: __('Your rating', 'flx-forms'), settings: [] },
	pointr5: { slug: 'score-5', title: __('Score 1-5', 'flx-forms'), icon: 'heart', group: 'advanced', kind: 'points5', label: __('Your score', 'flx-forms'), settings: [] },
	pointr10: { slug: 'nps', title: __('Score 0-10 (NPS)', 'flx-forms'), icon: 'chart-bar', group: 'advanced', kind: 'points10', label: __('How likely are you to recommend us?', 'flx-forms'), settings: [] },
	table_matrix: {
		slug: 'matrix',
		title: __('Rating table', 'flx-forms'),
		icon: 'grid-view',
		group: 'advanced',
		kind: 'matrix',
		label: __('Rate each item', 'flx-forms'),
		options: 'r_matrix',
		settings: [],
	},
	esign: {
		slug: 'signature',
		title: __('Signature', 'flx-forms'),
		icon: 'edit',
		group: 'advanced',
		kind: 'esign',
		label: __('Signature', 'flx-forms'),
		defaults: { icon: 'bi-save', icon_color: 'text-white', button_single_text: __('Clear', 'flx-forms') },
		settings: [{ key: 'button_single_text', type: 'text', label: __('Clear button text', 'flx-forms') }],
	},
	maps: {
		slug: 'location',
		title: __('Location picker', 'flx-forms'),
		icon: 'location-alt',
		group: 'advanced',
		kind: 'map',
		label: __('Pick a location', 'flx-forms'),
		defaults: { lat: 41.0082, lng: 28.9784, mark: 1, zoom: 12 },
		settings: [
			{ key: 'lat', type: 'number', label: __('Latitude', 'flx-forms') },
			{ key: 'lng', type: 'number', label: __('Longitude', 'flx-forms') },
			{ key: 'zoom', type: 'number', label: __('Zoom', 'flx-forms') },
			{ key: 'mark', type: 'number', label: __('How many points can be marked', 'flx-forms') },
		],
	},
	audio_recorder: {
		slug: 'audio',
		title: __('Voice recording', 'flx-forms'),
		icon: 'microphone',
		group: 'advanced',
		kind: 'recorder',
		label: __('Record a voice message', 'flx-forms'),
		defaults: { file: 'media', value: 'media', record_quality: 'standard', max_duration: 90, max_fsize: '20', rec_countdown: '3', rec_download: 1, rec_noise: 1 },
		settings: [
			{ key: 'record_quality', type: 'select', label: __('Quality', 'flx-forms'), options: [['standard', __('Standard', 'flx-forms')], ['high', __('High', 'flx-forms')]] },
			...RECORDER,
			{ key: 'rec_noise', type: 'toggle', label: __('Noise reduction', 'flx-forms') },
		],
	},
	video_recorder: {
		slug: 'video',
		title: __('Video recording', 'flx-forms'),
		icon: 'video-alt3',
		group: 'advanced',
		kind: 'recorder',
		label: __('Record a video', 'flx-forms'),
		defaults: { file: 'media', value: 'media', record_quality: '720p', max_duration: 90, max_fsize: '20', rec_countdown: '3', rec_download: 1, rec_facing: 'user', rec_mirror: 1, rec_watermark: 1 },
		settings: [
			{ key: 'record_quality', type: 'select', label: __('Quality', 'flx-forms'), options: [['480p', '480p'], ['720p', '720p'], ['1080p', '1080p']] },
			...RECORDER,
			{ key: 'rec_facing', type: 'select', label: __('Camera', 'flx-forms'), options: [['user', __('Front camera', 'flx-forms')], ['environment', __('Back camera', 'flx-forms')]] },
			{ key: 'rec_mirror', type: 'toggle', label: __('Mirror the preview', 'flx-forms') },
		],
	},
	screen_recorder: {
		slug: 'screen',
		title: __('Screen recording', 'flx-forms'),
		icon: 'desktop',
		group: 'advanced',
		kind: 'recorder',
		label: __('Record your screen', 'flx-forms'),
		defaults: { file: 'media', value: 'media', record_quality: '720p', max_duration: 90, max_fsize: '20', rec_countdown: '3', rec_download: 1, rec_watermark: 1 },
		settings: [
			{ key: 'record_quality', type: 'select', label: __('Quality', 'flx-forms'), options: [['480p', '480p'], ['720p', '720p'], ['1080p', '1080p']] },
			...RECORDER,
		],
	},

	// Payment -----------------------------------------------------------
	payCheckbox: {
		slug: 'pay-checkbox',
		title: __('Products (multiple)', 'flx-forms'),
		icon: 'cart',
		group: 'payment',
		kind: 'choices',
		label: __('Choose products', 'flx-forms'),
		options: 'option',
		optionFields: ['price'],
		settings: [COLUMNS],
	},
	payRadio: {
		slug: 'pay-radio',
		title: __('Product (single)', 'flx-forms'),
		icon: 'cart',
		group: 'payment',
		kind: 'choices',
		label: __('Choose a product', 'flx-forms'),
		options: 'option',
		optionFields: ['price'],
		settings: [COLUMNS],
	},
	paySelect: {
		slug: 'pay-select',
		title: __('Product dropdown', 'flx-forms'),
		icon: 'cart',
		group: 'payment',
		kind: 'select',
		label: __('Choose a product', 'flx-forms'),
		options: 'option',
		optionFields: ['price'],
		settings: [],
	},
	payMultiselect: {
		slug: 'pay-multiselect',
		title: __('Product list (multiple)', 'flx-forms'),
		icon: 'cart',
		group: 'payment',
		kind: 'select',
		label: __('Choose products', 'flx-forms'),
		options: 'option',
		optionFields: ['price'],
		defaults: { maxSelect: 2, minSelect: 0 },
		settings: MULTI,
	},
	prcfld: {
		slug: 'amount',
		title: __('Amount field', 'flx-forms'),
		icon: 'money-alt',
		group: 'payment',
		kind: 'input',
		label: __('Amount', 'flx-forms'),
		settings: [DEFAULT_VALUE],
	},
	ttlprc: { slug: 'total', title: __('Total', 'flx-forms'), icon: 'money-alt', group: 'payment', kind: 'total', label: __('Total', 'flx-forms'), noRequired: true, settings: [] },
	stripe: {
		title: __('Card payment (Stripe)', 'flx-forms'),
		icon: 'money',
		group: 'payment',
		kind: 'payment',
		label: __('Payment', 'flx-forms'),
		payment: 'stripe',
		settings: [],
	},

	// Content -----------------------------------------------------------
	heading: {
		title: __('Heading', 'flx-forms'),
		icon: 'heading',
		group: 'content',
		kind: 'heading',
		label: false,
		defaults: { value: __('Heading', 'flx-forms'), el_text_size: 'fs-3', el_text_color: 'text-dark', el_align: 'justify-content-start' },
		settings: [
			{ key: 'value', type: 'text', label: __('Text', 'flx-forms') },
			{
				key: 'el_text_size',
				type: 'select',
				label: __('Size', 'flx-forms'),
				options: [
					['fs-6', __('Small', 'flx-forms')],
					['fs-5', __('Medium', 'flx-forms')],
					['fs-4', __('Large', 'flx-forms')],
					['fs-3', __('Larger', 'flx-forms')],
					['display-6', __('Huge', 'flx-forms')],
				],
			},
		],
	},
	link: {
		title: __('Link', 'flx-forms'),
		icon: 'admin-links',
		group: 'content',
		kind: 'link',
		label: false,
		defaults: { value: __('Link', 'flx-forms'), href: '', el_text_size: 'fs-6', el_text_color: 'text-primary', el_align: 'justify-content-start' },
		settings: [
			{ key: 'value', type: 'text', label: __('Text', 'flx-forms') },
			{ key: 'href', type: 'url', label: __('Address', 'flx-forms') },
		],
	},
	html: {
		title: __('HTML', 'flx-forms'),
		icon: 'html',
		group: 'content',
		kind: 'html',
		label: false,
		bare: true,
		settings: [{ key: 'value', type: 'code', label: __('HTML code', 'flx-forms') }],
	},
};

/** Block slug of a Flux Forms type. */
export function slugOf(type) {
	return (TYPES[type] && TYPES[type].slug) || type;
}

/** Flux Forms type of a block name (flxf/<slug>), or ''. */
export function typeOfBlock(name) {
	const slug = String(name || '').replace(/^flxf\//, '');
	return Object.keys(TYPES).find((t) => slugOf(t) === slug) || '';
}

/** Switches every field has (stored on the row as 1 / 0). */
export const COMMON_TOGGLES = [
	{ key: 'hidden', type: 'toggle', label: __('Hidden field', 'flx-forms'), help: __('Not shown to the person; its default value is still sent.', 'flx-forms') },
	{ key: 'disabled', type: 'toggle', label: __('Read only', 'flx-forms') },
	{ key: 'hflabel', type: 'toggle', label: __('Hide the label', 'flx-forms') },
];

export const FIELD_TYPES = Object.keys(TYPES);
export const OPTION_TYPES = FIELD_TYPES.filter((t) => TYPES[t].options);
export const PAYMENT_TYPES = FIELD_TYPES.filter((t) => 'payment' === TYPES[t].group);
