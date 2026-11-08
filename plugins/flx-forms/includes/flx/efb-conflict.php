<?php
/**
 * Guard against a second copy of the Easy Form Builder code base.
 *
 * Flux Forms is a fork of Easy Form Builder and keeps its class, function and
 * table names (Emsfb, emsfb_*, wp_emsfb_*). The retired YT Form plugin and the
 * upstream Easy Form Builder plugin declare the very same symbols, so loading
 * both in one request is a "Cannot redeclare" fatal: activating Flux Forms on a
 * site that still runs YT Form answered HTTP 500.
 *
 * This file only uses WordPress core and is loaded before anything else in the
 * plugin. When another copy is active, Flux Forms does not load in that request,
 * switches the other copy off at the end of the request (both share the same
 * tables and options, so no data moves) and shows a notice. From the next
 * request on Flux Forms runs alone and heals its tables through the DB version
 * check in Emsfb::maybe_upgrade_schema_efb().
 *
 * @package flx-forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Active plugins (basenames) that carry another copy of the EFB code.
 *
 * @param string $own_file Main file of this plugin.
 * @return string[]
 */
function flxf_efb_conflicting_plugins( $own_file ) {
	$own_dir = basename( dirname( $own_file ) );

	$active = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}

	$found = array();
	foreach ( array_unique( $active ) as $plugin ) {
		$plugin = (string) $plugin;
		$dir    = dirname( $plugin );
		if ( '.' === $dir || $dir === $own_dir ) {
			continue;
		}
		if ( file_exists( WP_PLUGIN_DIR . '/' . $dir . '/includes/class-Emsfb.php' ) ) {
			$found[] = $plugin;
		}
	}

	// Loaded by some other path (mu-plugin, renamed folder) but not in the list.
	if ( ! $found && ( class_exists( 'Emsfb', false ) || function_exists( 'emsfb_probe_ir_cdn_status_efb' ) ) ) {
		$file = class_exists( 'Emsfb', false )
			? ( new ReflectionClass( 'Emsfb' ) )->getFileName()
			: ( new ReflectionFunction( 'emsfb_probe_ir_cdn_status_efb' ) )->getFileName();
		$rel  = ltrim( str_replace( wp_normalize_path( WP_PLUGIN_DIR ), '', wp_normalize_path( (string) $file ) ), '/' );
		$dir  = strtok( $rel, '/' );
		foreach ( $active as $plugin ) {
			if ( 0 === strpos( (string) $plugin, $dir . '/' ) ) {
				$found[] = (string) $plugin;
			}
		}
		if ( ! $found ) {
			$found[] = ''; // Unknown source: still refuse to load.
		}
	}

	return $found;
}

/**
 * Handle a detected conflict: switch the other copies off once the current
 * request is done and leave a notice for the next admin screen.
 *
 * Deactivation waits for shutdown on purpose. During activate_plugin() core has
 * already read active_plugins and writes it back after including this file, so
 * an immediate deactivate_plugins() would be overwritten and both copies would
 * be active on the next request.
 *
 * @param string   $own_file  Main file of this plugin.
 * @param string[] $conflicts Basenames from flxf_efb_conflicting_plugins().
 * @return void
 */
function flxf_efb_handle_conflict( $own_file, array $conflicts ) {
	$conflicts = array_values( array_filter( $conflicts ) );

	add_action(
		'shutdown',
		static function () use ( $own_file, $conflicts ) {
			if ( ! $conflicts ) {
				return;
			}
			$own = plugin_basename( $own_file );
			if ( ! in_array( $own, (array) get_option( 'active_plugins', array() ), true ) ) {
				return; // Flux Forms itself did not end up active; leave the site as it was.
			}
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			// Silent: the old copy's deactivation hooks would clear cron events
			// and options that Flux Forms shares with it.
			deactivate_plugins( $conflicts, true );

			$names = array();
			foreach ( $conflicts as $plugin ) {
				$data    = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );
				$names[] = ! empty( $data['Name'] ) ? $data['Name'] : $plugin;
			}
			set_transient( 'flxf_efb_conflict_notice', $names, DAY_IN_SECONDS );
		}
	);
}

/**
 * Notice after the other copy was switched off (shown once).
 *
 * @param string $own_file Main file of this plugin.
 * @return void
 */
function flxf_efb_conflict_notice( $own_file ) {
	$names = get_transient( 'flxf_efb_conflict_notice' );
	if ( ! $names || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	delete_transient( 'flxf_efb_conflict_notice' );

	load_plugin_textdomain( 'flx-forms', false, dirname( plugin_basename( $own_file ) ) . '/languages' );

	printf(
		'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %s: plugin names, comma separated */
				__( '\\nFlux Forms replaces%s, which has been deactivated. Your forms, entries and settings stay in place. You can delete the old plugin.\\n', 'flx-forms' ),
				implode( ', ', (array) $names )
			)
		)
	);
}
