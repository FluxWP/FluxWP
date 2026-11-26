<?php
/**
 * Language of GraphQL answers: the signed-in user's own language for FluxWP screens.
 *
 * WordPress answers REST in the user's language only when the request says `_locale=user` and its Accept or
 * Content-Type header is JSON (determine_locale()), and only for translations loaded after the user is known.
 * /graphql is not wp-admin, so any text domain loaded before that (or a proxy that drops the header) left
 * server-built texts (system check rows, group names, dates) in the site language while the screen around them
 * was in the user's. Every FluxWP client sends `_locale=user` (@fluxwp/core buildUrl, the screen navigator),
 * so here that flag is enough: once the request is authenticated, the whole request switches to the user's
 * language. A request that names its language itself (Flux Lang `?lang=`, a headless front end) keeps it.
 * No `locale` filter is added (a filter that reads options re-enters itself), only one switch per request.
 *
 * @package Flux_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/** The request asks for the signed-in user's language (`_locale=user`). */
function flx_gql_wants_user_locale(): bool {
	return isset( $_GET['_locale'] ) && 'user' === $_GET['_locale']; // phpcs:ignore WordPress.Security.NonceVerification
}

/**
 * Locale the GraphQL answer is written in: the user's for `_locale=user` with a signed-in user, else the
 * request's own (site or Flux Lang language).
 */
function flx_gql_request_locale(): string {
	if ( flx_gql_wants_user_locale() && is_user_logged_in() && ! ( function_exists( 'flxl_is_explicit' ) && flxl_is_explicit() ) ) {
		return get_user_locale();
	}
	return determine_locale();
}

/** Switches the running GraphQL request to flx_gql_request_locale() (after WPGraphQL checked the nonce). */
function flx_gql_apply_request_locale(): void {
	static $done = false;
	if ( $done || ! flx_gql_wants_user_locale() ) {
		return;
	}
	$done   = true;
	$locale = flx_gql_request_locale();
	if ( '' !== $locale ) {
		switch_to_locale( $locale ); // A no-op when the request already runs in it.
	}
}
add_action( 'graphql_process_http_request', 'flx_gql_apply_request_locale', 1 );

/*
 * Every FluxWP plugin ships its translations in its own languages/ folder and maps the WordPress language
 * folder path to it (languages/locale-loader.php, `load_textdomain_mofile`). The first load therefore succeeds
 * without WordPress learning the plugin's folder, and any later language switch (this request, an e-mail in
 * the donor's language, switch_to_user_locale()) looked only in wp-content/languages/plugins, found nothing and
 * left the plugin in English. The plugin's own folder is the answer for its text domain in every locale.
 */
add_filter(
	'lang_dir_for_domain',
	static function ( $path, $domain, $locale ) {
		if ( $path || ! is_string( $domain ) || ! preg_match( '/^flx-[a-z0-9-]+$/', $domain ) ) {
			return $path;
		}
		$dir = WP_PLUGIN_DIR . '/' . $domain . '/languages/';
		return is_dir( $dir ) ? $dir : $path;
	},
	10,
	3
);
