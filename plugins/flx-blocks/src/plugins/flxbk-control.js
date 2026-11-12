import { registerPlugin } from '@wordpress/plugins';
import { applyFilters } from '@wordpress/hooks';
import './editor.scss';
/**
 * Import Icons
 */
import { flxbkNewIcon } from '@flx-blocks/icons';
/*
 * Components
 */
import FlxbkConfig from './flxbk-control-plugin';

registerPlugin('flxbk-control', {
	icon: applyFilters('flxbk.block_sidebar_control_icon', flxbkNewIcon),
	render: FlxbkConfig,
});
