<?php
/**
 * Uninstall: front ends (with their sealed secrets) and the webhook log are removed.
 *
 * @package FLX_Headless
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'flxhl_settings' );
delete_option( 'flxhl_log' );
