<?php
/**
 * Plugin Name: FluxWP Docker
 * Description: Activates Flux Setup right after the WordPress install screen, opens its setup wizard on the first wp-admin visit, and (wpapi stack) adds the WhatsApp API connection. Written by the FluxWP Docker worker.
 * Version: 1.1.0
 * Author: FluxWP
 * License: MIT
 *
 * Managed by the worker container (FLUXWP_SETUP=true, or WPAPI_KEY set). Deleted otherwise.
 */

defined( 'ABSPATH' ) || exit;

const FLX_DOCKER_SETUP_PLUGIN = 'flx-setup/flx-setup.php';
const FLX_DOCKER_WIZARD_FLAG  = 'flx_docker_open_wizard';

/**
 * Activates Flux Setup, ignoring anything that goes wrong (the worker retries with wp-cli).
 */
function flx_docker_activate_setup(): void {
	if ( ! is_file( WP_PLUGIN_DIR . '/' . FLX_DOCKER_SETUP_PLUGIN ) ) {
		return;
	}
	if ( ! function_exists( 'activate_plugin' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	try {
		if ( ! is_plugin_active( FLX_DOCKER_SETUP_PLUGIN ) ) {
			activate_plugin( FLX_DOCKER_SETUP_PLUGIN ); // Not silent: Flux Setup's activation hook marks the wizard as pending.
		}
	} catch ( Throwable $e ) {
		error_log( 'FluxWP Docker: Flux Setup activation failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return;
	}
	update_option( FLX_DOCKER_WIZARD_FLAG, 1, false );
}

// The browser install screen (wp-admin/install.php) fires this after the admin user is created.
add_action( 'wp_install', 'flx_docker_activate_setup', 99 );

/**
 * First wp-admin visit after the install: hand over to Flux Setup's own one-time redirect.
 * The plugin's transient lives only 60 seconds, which the "Log in" step can outlast.
 */
add_action(
	'admin_init',
	static function () {
		if ( ! get_option( FLX_DOCKER_WIZARD_FLAG ) ) {
			return;
		}
		if ( wp_doing_ajax() || ! current_user_can( 'install_plugins' ) ) {
			return;
		}
		delete_option( FLX_DOCKER_WIZARD_FLAG );
		if ( 'pending' === get_option( 'flxk_onboarding' ) ) {
			set_transient( 'flxk_onboarding_redirect', 1, 60 );
		}
	},
	1
);

/**
 * wpapi stack: WPAPI_URL + WPAPI_KEY are shared with the wpapi container (flx-wpapi). Once Flux Mail
 * is active, make sure it has a WhatsApp connection (type Flux WPAPI) with that address and key.
 * Runs again only when URL, key or instance change; a connection the user edits later is not touched
 * unless the shared key or address changes.
 */
function flx_docker_sync_wpapi(): void {
	$url = untrailingslashit( (string) getenv( 'WPAPI_URL' ) );
	$key = (string) getenv( 'WPAPI_KEY' );
	if ( '' === $url || '' === $key || ! function_exists( 'flxi_save_connection' ) ) {
		return;
	}
	$instance = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( getenv( 'WPAPI_INSTANCE' ) ?: 'main' ) );
	$instance = '' === $instance ? 'main' : $instance;
	$hash     = hash( 'sha256', $url . "\n" . $key . "\n" . $instance );
	if ( get_option( 'flx_docker_wpapi_hash' ) === $hash ) {
		return;
	}
	global $wpdb;
	$table = function_exists( 'flxi_table' ) ? flxi_table( 'connections' ) : '';
	if ( '' === $table || $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return; // Flux Mail has not created its tables yet; try again on a later request.
	}
	$id = 0;
	foreach ( flxi_list_connections( 'whatsapp' ) as $c ) {
		if ( 'flxwpapi' === $c['type'] && untrailingslashit( (string) ( $c['config']['base_url'] ?? '' ) ) === $url ) {
			$id = (int) $c['id'];
			break;
		}
	}
	$saved = flxi_save_connection(
		$id,
		'flxwpapi',
		'Flux WhatsApp API',
		array(
			'base_url'     => $url,
			'api_key'      => $key,
			'instance'     => $instance,
			'link_preview' => 1,
			'delay'        => 4,
			'typing'       => 1200,
		),
		1
	);
	if ( ! is_wp_error( $saved ) ) {
		update_option( 'flx_docker_wpapi_hash', $hash, true );
	}
}

add_action( 'init', 'flx_docker_sync_wpapi', 99 );
