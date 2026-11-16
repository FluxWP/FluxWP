<?php
/**
 * Flux GraphQL wiring: the schema (only inside a GraphQL request), the persisted operations the
 * Block Editor screen and headless front ends send, and cache purges when content or styles change.
 * Without Flux GraphQL (0.2.0+) nothing is registered and the plugin works as before.
 *
 * @package flx-blocks
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_all_caps' ) ) {
			return;
		}
		require_once __DIR__ . '/schema.php';
	}
);

add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m               = is_array( $m ) ? $m : array();
		$m['flx-blocks'] = FLXBK_PATH . 'graphql/persisted-queries.json';
		return $m;
	}
);

/**
 * Clears cached GraphQL reads.
 *
 * @param list<string> $tags Tags.
 */
function flxbk_gql_purge( array $tags ): void {
	if ( function_exists( 'flx_gql_purge' ) ) {
		flx_gql_purge( $tags );
	}
}

// A post's rendered blocks change with the post; synced patterns, headers, menus and forms are used
// inside other posts, so a change to one of them clears every render.
add_action(
	'save_post',
	static function ( $post_id, $post ) {
		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post ) ) {
			return;
		}
		$shared = array( 'wp_block', 'flxbk_header', 'flxbk_navigation', 'flxbk_form', 'wp_template_part', 'wp_global_styles' );
		if ( in_array( $post->post_type, $shared, true ) ) {
			flxbk_gql_purge( array( FLXBK_Render_Service::TAG_STYLES, FLXBK_Library_Service::TAG ) );
			return;
		}
		flxbk_gql_purge( array( FLXBK_Render_Service::TAG_POST . $post->ID ) );
	},
	10,
	2
);
add_action(
	'deleted_post',
	static function ( $post_id ) {
		if ( is_numeric( $post_id ) ) {
			flxbk_gql_purge( array( FLXBK_Render_Service::TAG_POST . (int) $post_id ) );
		}
	}
);

// Design tokens (projected into flxbk_colors), palette, block defaults, fonts and turned-off blocks change every render.
foreach ( array( 'flxbk_colors', 'flxbk_settings', 'flxbk_config_blocks', 'fxt_blocks_config_blocks', 'flxbk_font_settings', 'fxt_blocks_unregistered_blocks', 'flxbk_settings_blocks' ) as $flxbk_opt ) {
	add_action(
		'update_option_' . $flxbk_opt,
		static function () {
			flxbk_gql_purge( array( FLXBK_Render_Service::TAG_STYLES ) );
		}
	);
}
unset( $flxbk_opt );
add_action(
	'switch_theme',
	static function () {
		flxbk_gql_purge( array( FLXBK_Render_Service::TAG_STYLES, FLXBK_Library_Service::TAG ) );
	}
);

/*
 * WPGraphQL turns every setting with show_in_rest into a public settings field. The editor's settings
 * (API keys and captcha secrets among them) are for the editor's REST calls only; the GraphQL side is
 * the typed flxBlocks* fields.
 */
add_filter(
	'register_setting_args',
	static function ( $args, $defaults, $option_group, $option_name ) {
		if ( is_array( $args ) && is_string( $option_name ) && ( str_starts_with( $option_name, 'flxbk_' ) || str_starts_with( $option_name, 'fxt_blocks_' ) ) ) {
			$args['show_in_graphql'] = false;
		}
		return $args;
	},
	10,
	4
);
