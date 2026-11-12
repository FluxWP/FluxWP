<?php
/**
 * Flux Block Editor uninstall: settings options are removed; design token tables and
 * user content (forms, headers, navigation, vectors, Lottie files) stay.
 *
 * @package FLX_Blocks
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'flxbk_redirect_on_activation', 'flxbk_settings_blocks', 'flxbk_config_blocks', 'fxt_blocks_config_blocks', 'flxbk_admin_bar_settings', 'flxbk_font_settings', 'fxt_blocks_editor_width' ) as $flxbk_option ) {
	delete_option( $flxbk_option );
}
