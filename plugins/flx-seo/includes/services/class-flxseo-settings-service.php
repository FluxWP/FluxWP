<?php
/**
 * Settings tabs: everything the tabs show besides the setting values (those are the `flxseo` option, written
 * through `flx_settings_schema`): search engines, sitemap, IndexNow state, the other-plugin notice. Also the
 * IndexNow bulk submission. The classic tabs, GraphQL and Flux AI call this; permission (manage_options) stays
 * with the caller.
 *
 * @package flx-seo
 */

defined( 'ABSPATH' ) || exit;

final class FLXSEO_Settings_Service {

	public const CACHE_TAG = 'flx-seo:settings';

	public static function setup(): FLXSEO_Setup {
		$engines = array();
		foreach ( flxseo_search_engines() as $key => $e ) {
			$e         = FLXSEO_Util::arr( $e );
			$engines[] = new FLXSEO_Search_Engine(
				(string) $key,
				FLXSEO_Util::str( $e['name'] ?? $key ),
				FLXSEO_Util::str( $e['url'] ?? '' ),
				FLXSEO_Util::str( $e['link'] ?? '' ),
				FLXSEO_Util::str( $e['placeholder'] ?? '' ),
				FLXSEO_Util::str( $e['help'] ?? '' ),
			);
		}
		$key = flxseo_indexnow_key();
		return new FLXSEO_Setup(
			flxseo_other_seo_plugin(),
			function_exists( 'flxai_mcp_tools' ),
			trim( wp_strip_all_tags( FLXSEO_Util::str( get_bloginfo( 'name' ) ) ) ),
			home_url( '/' ),
			home_url( '/wp-sitemap.xml' ),
			FLXSEO_URL . 'assets/icons/icon-512.png',
			FLXSEO_URL . 'assets/icons/og-varsayilan.jpg',
			FLXSEO_Util::str( flxseo_opt( 'home_title' ) ),
			FLXSEO_Util::str( flxseo_opt( 'home_desc' ) ),
			flxseo_sitekit_connected(),
			(bool) get_option( 'blog_public' ),
			$key,
			home_url( '/' . $key . '.txt' ),
			flxseo_indexnow_last_text( FLXSEO_Util::arr( get_option( 'flxseo_indexnow_last', array() ) ) ),
			flxseo_indexnow_enabled(),
			'' !== flxseo_psi_key(),
			FLXSEO_Util::str( get_option( 'admin_email' ) ),
			$engines,
			FLXSEO_Util::strings( flxseo_guide_sections() ),
		);
	}

	/**
	 * Sends every public address to the IndexNow search engines now.
	 *
	 * @return int|WP_Error Addresses submitted.
	 */
	public static function submit_indexnow() {
		if ( ! flxseo_indexnow_enabled() ) {
			return new WP_Error( 'flxseo_indexnow_off', __( 'IndexNow is off or the site is hidden from search engines (Settings › Reading).', 'flx-seo' ), array( 'graphql_code' => 'INVALID_INPUT' ) );
		}
		$urls = flxseo_indexnow_all_urls();
		$r    = flxseo_indexnow_send( $urls, true );
		if ( $r instanceof WP_Error ) {
			return new WP_Error( $r->get_error_code(), $r->get_error_message(), array( 'graphql_code' => 'INVALID_INPUT' ) );
		}
		update_option( 'flxseo_indexnow_init', time(), false );
		return count( $urls );
	}
}
