const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');
const StyleOnlyEntryPlugin = require('./tools/webpack/style-only-entry-plugin.js');
const ReplaceTextDomainPlugin = require('./tools/webpack/replace-text-domain-plugin.js');
const EXTERNAL_NAME = 'flxbk';
const HANDLE_NAME = 'flxbk';
const PROJECT_NAMESPACE = '@flx-blocks/';

const path = require('path');

function camelCaseDash(string) {
	return string.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
}

function getFlxbkNamespaceRequest(request) {
	const bundledFlxbkEntryPackages = new Set(['@flx-blocks/icons', '@flx-blocks/components', '@flx-blocks/helpers']);

	if (!request.startsWith(PROJECT_NAMESPACE)) {
		return undefined;
	}

	if (bundledFlxbkEntryPackages.has(request)) {
		return request.substring(PROJECT_NAMESPACE.length);
	}

	return request.substring(PROJECT_NAMESPACE.length);
}

module.exports = {
	...defaultConfig,
	entry: {
		icons: './packages/icons/src/index.js',
		components: './packages/components/src/index.js',
		helpers: './packages/helpers/src/index.js',
		'blocks-navigation': './src/blocks/navigation/index.js',
		'blocks-navigation-link': './src/blocks/navigation-link/index.js',
		'blocks-header': './src/blocks/header/index.js',
		'blocks-googlemaps': './src/blocks/googlemaps/block.js',
		'blocks-lottie': './src/blocks/lottie/index.js',
		'blocks-image': './src/blocks/image/index.js',
		'blocks-spacer': './src/blocks/spacer/index.js',
		'blocks-advancedbtn': './src/blocks/advancedbtn/block.js',
		'blocks-singlebtn': './src/blocks/singlebtn/block.js',
		'blocks-countup': './src/blocks/countup/block.js',
		'blocks-rowlayout': './src/blocks/rowlayout/index.js',
		'blocks-identity': './src/blocks/identity/index.js',
		'blocks-column': './src/blocks/column/index.js',
		'blocks-advancedheading': './src/blocks/advancedheading/block.js',
		'blocks-icon': './src/blocks/icon/block.js',
		'blocks-single-icon': './src/blocks/single-icon/block.js',
		'blocks-tabs': './src/blocks/tabs/block.js',
		'blocks-infobox': './src/blocks/infobox/block.js',
		'blocks-accordion': './src/blocks/accordion/block.js',
		'blocks-iconlist': './src/blocks/iconlist/block.js',
		'blocks-advancedgallery': './src/blocks/advancedgallery/block.js',
		'blocks-form': './src/blocks/form/block.js',
		'blocks-tableofcontents': './src/blocks/tableofcontents/index.js',
		'blocks-posts': './src/blocks/posts/block.js',
		'blocks-search': './src/blocks/search/index.js',
		'blocks-show-more': './src/blocks/show-more/index.js',
		'blocks-countdown': './src/blocks/countdown/block.js',
		'blocks-testimonial': './src/blocks/testimonial/block.js',
		'blocks-testimonials': './src/blocks/testimonials/block.js',
		'blocks-advanced-form': './src/blocks/advanced-form/index.js',
		'blocks-progress-bar': './src/blocks/progress-bar/index.js',
		'blocks-vector': './src/blocks/vector/block.js',
		'blocks-table': './src/blocks/table/index.js',
		'blocks-videopopup': './src/blocks/videopopup/block.js',
		'plugin-flxbk-control': './src/plugin.js',
		'early-filters': './src/early-filters.js',
		'extension-flxbk-base': './src/extension/flxbk-base/index.js',
		'extension-stores': './src/extension/stores/index.js',
		'extension-block-css': './src/extension/block-css/index.js',
		'extension-admin-notices': './src/extension/admin-notices/index.js',
		'admin-flxbk-style-library': './src/style-library.js',
		'header-visual-builder': './src/header-visual-builder.js',
		'post-saved-event': './src/extension/post-saved-event/index.js',
	},
	output: {
		...defaultConfig.output,
		path: __dirname + '/build/',
		library: ['flxbk', '[name]'],
		libraryTarget: 'this',
	},
	resolve: {
		alias: {
			'@flxbk-bundled/admin-notices': path.resolve(__dirname, 'src/extension/admin-notices'),
			'@flx-blocks/components$': path.resolve(__dirname, 'packages/components/src/index.js'),
			'@flx-blocks/helpers$': path.resolve(__dirname, 'packages/helpers/src/index.js'),
			'@flx-blocks/icons$': path.resolve(__dirname, 'packages/icons/src/index.js'),
		},
	},
	plugins: [
		new StyleOnlyEntryPlugin(),
		...defaultConfig.plugins.filter((plugin) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'),
		new DependencyExtractionWebpackPlugin({
			requestToExternal(request) {
				if (request.endsWith('.css')) {
					return false;
				}

				const flxbkNamespaceRequest = getFlxbkNamespaceRequest(request);
				if (!flxbkNamespaceRequest) {
					return undefined;
				}

				return [EXTERNAL_NAME, camelCaseDash(flxbkNamespaceRequest)];
			},
			requestToHandle(request) {
				const flxbkNamespaceRequest = getFlxbkNamespaceRequest(request);
				if (!flxbkNamespaceRequest) {
					return undefined;
				}

				return `${HANDLE_NAME}-${flxbkNamespaceRequest}`;
			},
		}),
		new ReplaceTextDomainPlugin({
			placeholder: '__FLX__TEXT__DOMAIN__',
			value: 'flx-blocks',
		}),
	],
};
