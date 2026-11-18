<?php
/**
 * Wiki settings page: what it shows besides the settings (addresses, AI and MCP state, guide counts),
 * saving a settings tab and importing the bundled guides.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings page service.
 */
final class FLXWK_Admin_Service {

	/** MCP tools the wiki adds. */
	public const MCP_TOOLS = array( 'flxwk_search', 'flxwk_get_page', 'flxwk_list_pages', 'flxwk_save_page' );

	/** Settings tabs. */
	public const TABS = array( 'general', 'look', 'ai' );

	/**
	 * Addresses, AI state and counts for the settings screen.
	 */
	public function overview(): FLXWK_Overview {
		$count = wp_count_posts( FLXWK_PT );
		$base  = flxwk_base();
		return new FLXWK_Overview(
			flxwk_s( flxwk_home_url() ),
			home_url( '/' ),
			$base,
			home_url( '/' . $base . '/llms.txt' ),
			home_url( '/' . $base . '/llms-full.txt' ),
			'/wp-json/flx-wiki/v1/search?q=',
			function_exists( 'flxai_mcp_enabled' ) && (bool) flxai_mcp_enabled(),
			self::MCP_TOOLS,
			function_exists( 'flxai_chat' ),
			(bool) flxwk_ai_ready(),
			count( (array) flxwk_seed_files() ),
			flxwk_i( $count->publish ?? 0 ),
			__( 'Developer Wiki', 'flx-wiki' ),
			flxwk_s( flxwk_intro_default() ),
			admin_url( 'post-new.php?post_type=' . FLXWK_PT )
		);
	}

	/**
	 * Saves one tab of the settings (the same sanitising for every caller).
	 *
	 * @param string               $tab Tab: general, look or ai.
	 * @param array<string,mixed>  $p   Submitted values (already unslashed).
	 * @return string The tab that was saved.
	 */
	public function save_tab( string $tab, array $p ): string {
		$cur = flxwk_settings();
		$new = $cur;
		if ( 'general' === $tab ) {
			$base = sanitize_title( self::str( $p['base'] ?? '' ) );
			$new  = array_merge(
				$new,
				array(
					'enabled'   => empty( $p['enabled'] ) ? 0 : 1,
					'title'     => sanitize_text_field( self::str( $p['title'] ?? '' ) ),
					'intro'     => sanitize_textarea_field( self::str( $p['intro'] ?? '' ) ),
					'base'      => '' === $base ? 'wiki' : $base,
					'ai_links'  => empty( $p['ai_links'] ) ? 0 : 1,
					'edit_link' => empty( $p['edit_link'] ) ? 0 : 1,
				)
			);
		} elseif ( 'look' === $tab ) {
			$new = array_merge(
				$new,
				array(
					'accent' => empty( $p['accent_theme'] ) ? flxwk_s( sanitize_hex_color( self::str( $p['accent'] ?? '' ) ) ) : '',
					'width'  => 'wide' === ( $p['width'] ?? '' ) ? 'wide' : 'normal',
					'nav'    => empty( $p['nav'] ) ? 0 : 1,
					'toc'    => empty( $p['toc'] ) ? 0 : 1,
				)
			);
		} else {
			$tab = 'ai';
			$ask = $p['ask'] ?? '';
			$new = array_merge(
				$new,
				array(
					'ask'       => in_array( $ask, array( 'off', 'users', 'all' ), true ) ? $ask : 'users',
					'ask_limit' => max( 1, min( 500, flxwk_i( self::str( $p['ask_limit'] ?? 20 ) ) ) ),
				)
			);
		}
		update_option( 'flxwk_settings', $new ); // The update_option_flxwk_settings hook clears caches and refreshes the addresses.
		return $tab;
	}

	/**
	 * Imports the bundled guides (missing pages are added, pages nobody edited are updated).
	 */
	public function import_guides(): FLXWK_Import_Result {
		$s = flxwk_seed_import( true );
		return new FLXWK_Import_Result( flxwk_i( $s['created'] ?? 0 ), flxwk_i( $s['updated'] ?? 0 ), flxwk_i( $s['skipped'] ?? 0 ) );
	}

	/**
	 * Scalar → string.
	 *
	 * @param mixed $v Value.
	 */
	private static function str( $v ): string {
		return is_scalar( $v ) ? flxwk_s( $v ) : '';
	}
}
