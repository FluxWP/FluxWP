<?php
/**
 * Flux screens shell (plugins/flx-graphql/includes/screens.php): registration, preload keys (same format
 * as packages/graphql/src/store.ts), the preloaded results and the navigator routes printed on the page.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-graphql/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';
require_once FLXGQL_DIR . 'includes/screens.php'; // Loaded in wp-admin only.

echo "Flux screens shell\n";

// Later tests share this database: put every option the admin screen code changed back at the end.
wp_cache_delete( 'alloptions', 'options' );
$flxt_options = wp_load_alloptions( true );

// Same cases as packages/graphql/test/store.test.ts.
flxt_same( '{}', flx_admin_screen_stable_vars( null ), 'no variables = {}' );
flxt_same( '{}', flx_admin_screen_stable_vars( array() ), 'empty variables = {}' );
flxt_same( '{"a":{"c":null,"d":"x/y"},"b":1}', flx_admin_screen_stable_vars( array( 'b' => 1, 'a' => array( 'd' => 'x/y', 'c' => null ) ) ), 'keys sorted, slashes as is' );
flxt_same( '{"filter":{"range":"DAYS_30"},"kpi":true,"minutes":30}', flx_admin_screen_stable_vars( array( 'minutes' => 30, 'kpi' => true, 'filter' => array( 'range' => 'DAYS_30' ) ) ), 'nested input' );

// A screen with a stand-in bundle.
$dir = trailingslashit( get_temp_dir() ) . 'flxt-screen-' . wp_rand() . '/';
wp_mkdir_p( $dir );
file_put_contents( $dir . 'flux-modules.js', 'export {};' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
file_put_contents( $dir . 'chunks.json', '{"mode":"chunks","entry":"flux-modules.js","files":[]}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

$admin = (int) get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 1 ) )[0];
wp_set_current_user( $admin );
$GLOBALS['menu']    = array( array( 'Test screen', 'manage_options', 'flxt-screen', 'Test screen' ) );
$GLOBALS['submenu'] = array( 'flxt-screen' => array( array( 'Other', 'manage_options', 'flxt-screen-other', 'Other screen' ) ) );

$on = true;
flx_admin_screen(
	'flxt-screen',
	array(
		'module'      => 'flxt-test',
		'assets_dir'  => $dir,
		'assets_url'  => 'https://example.org/flux/',
		'version'     => '1.0.0',
		'enabled'     => static function () use ( &$on ) {
			return $on;
		},
		'options'     => static function () {
			return array( 'classicUrl' => 'https://example.org/classic' );
		},
		'start_param' => 'tab',
		'start'       => 'home',
		'preload'     => array( array( 'FlxViewer' ), array( 'FlxNoSuchOperation' ) ),
	)
);
flx_admin_screen( 'flxt-screen-other', array( 'module' => 'flxt-other', 'assets_dir' => $dir, 'assets_url' => 'https://example.org/flux/', 'layout' => 'full' ) );
flx_admin_screen( 'flxt-broken', array( 'module' => '' ) );
flxt_same( array( 'flxt-screen', 'flxt-screen-other' ), array_keys( flx_admin_screens() ), 'registration needs module and assets' );

$items = flx_admin_screen_preload_items( flx_admin_screens()['flxt-screen'], array() );
$ids   = flx_admin_screen_operation_ids();
flxt_same( 1, count( $items ), 'unknown operations are left out of the preload' );
flxt_same( $ids['FlxViewer'] . ':{}', $items[0]['key'] ?? '', 'preload key = persisted id + variables' );

$b1 = flx_admin_screen_bundle( flx_admin_screens()['flxt-screen'] );
$b2 = flx_admin_screen_bundle( flx_admin_screens()['flxt-screen-other'] );
flxt_same( $b1['id'], $b2['id'], 'identical bundles share an id (one runtime on the page)' );
flxt_ok( false !== strpos( $b1['entry'], 'flux-modules.js?ver=1.0.0-' . $b1['id'] ), 'entry URL carries version and bundle id', $b1['entry'] );

// Not a screen request.
set_current_screen( 'toplevel_page_flxt-screen' );
unset( $_GET['page'] );
flxt_same( '', flx_admin_screen_current(), 'no page argument: not a screen' );
ob_start();
$printed = flx_admin_screen_render();
$out     = (string) ob_get_clean();
flxt_same( false, $printed, 'render returns false outside a screen' );
flxt_same( '', $out, 'and outputs nothing' );

// The screen itself.
$_GET['page'] = 'flxt-screen';
$_GET['tab']  = 'audit';
flxt_same( 'flxt-screen', flx_admin_screen_current(), 'page argument selects the screen' );
ob_start();
$printed = flx_admin_screen_render();
$html    = (string) ob_get_clean();
flxt_same( true, $printed, 'render prints the screen' );

flxt_ok( (bool) preg_match( '#<script type="application/json" id="flx-gql-preload">(.*?)</script>#s', $html, $m ), 'preloaded results are printed' );
$pre = json_decode( $m[1] ?? '{}', true );
flxt_ok( ! empty( $pre['entries'][ $items[0]['key'] ]['flxViewer'] ), 'the viewer query ran as the signed-in user', $pre );
flxt_ok( is_int( $pre['at'] ?? null ), 'preload carries its time' );

flxt_ok( (bool) preg_match( '#s\.opts=(\{.*?\});function go#s', $html, $o ), 'mount options are printed' );
$opts = json_decode( $o[1] ?? '{}', true );
flxt_same( 'flxt-test', $opts['module'] ?? '', 'module' );
flxt_same( 'audit', $opts['startPage'] ?? '', 'start page from the start argument' );
flxt_same( 'https://example.org/classic', $opts['classicUrl'] ?? '', 'package options are merged' );
flxt_same( wp_create_nonce( 'wp_rest' ), $opts['nonce'] ?? '', 'REST nonce' );

flxt_ok( (bool) preg_match( '#window\.FlxScreensConfig=(\{.*?\});</script>#s', $html, $c ), 'navigator routes are printed' );
$cfg = json_decode( $c[1] ?? '{}', true );
flxt_same( array( 'flxt-screen' ), array_keys( (array) ( $cfg['routes'] ?? array() ) ), 'full-layout screens are not routes (they load as pages)' );
flxt_same( 'admin.php', $cfg['routes']['flxt-screen']['file'] ?? '', 'route file' );
flxt_same( 'tab', $cfg['routes']['flxt-screen']['startParam'] ?? '', 'route start argument' );
flxt_same( $items[0]['key'], $cfg['routes']['flxt-screen']['preload'][0]['key'] ?? '', 'route preload keys match' );
flxt_ok( false === strpos( $html, '</script><' . 'script>alert' ), 'JSON is script-safe' );

// A user who turned the new view off: not a screen, no routes for it.
$on = false;
flxt_same( '', flx_admin_screen_current(), 'disabled screen is not current' );

// Navigation can be turned off.
$on = true;
add_filter( 'flx_admin_screens_navigation', '__return_false' );
ob_start();
flx_admin_screen_render();
$html = (string) ob_get_clean();
preg_match( '#window\.FlxScreensConfig=(\{.*?\});</script>#s', $html, $c );
flxt_same( false, json_decode( $c[1] ?? '{}', true )['on'] ?? null, 'flx_admin_screens_navigation false turns navigation off' );
remove_filter( 'flx_admin_screens_navigation', '__return_false' );

// A plugin screens bundle (@fluxwp/plugin-kit): runs on the shared runtime in assets/runtime/.
$pdir = trailingslashit( get_temp_dir() ) . 'flxt-plugin-' . wp_rand() . '/';
wp_mkdir_p( $pdir );
file_put_contents( $pdir . 'flux-screens.js', 'export {};' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
file_put_contents( $pdir . 'flux-screens.css', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
file_put_contents( $pdir . 'chunks.json', '{"mode":"screens","entry":"flux-screens.js","files":[]}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
$GLOBALS['submenu']['flxt-screen'][] = array( 'Plugin', 'manage_options', 'flxt-plugin', 'Plugin screen' );
flx_admin_screen( 'flxt-plugin', array( 'module' => 'flxt-plugin', 'assets_dir' => $pdir, 'assets_url' => 'https://example.org/plugin/flux/', 'version' => '2.0.0' ) );
$ps = flx_admin_screens()['flxt-plugin'];
flxt_same( true, flx_admin_screen_is_plugin_bundle( $ps ), 'flux-screens.js marks a plugin screens bundle' );
flxt_same( true, null !== flx_admin_screen_runtime(), 'the shared runtime ships with Flux GraphQL' );
flxt_same( true, flx_admin_screen_usable( 'flxt-plugin' ), 'plugin screen is usable with the runtime' );
$pb = flx_admin_screen_bundle( $ps );
flxt_same( 'flx-runtime', $pb['id'], 'plugin bundles share the runtime id' );
flxt_same( true, str_contains( $pb['entry'], 'assets/runtime/flux-runtime.js' ), 'plugin route loads the runtime' );
flxt_same( true, str_starts_with( $pb['screens'] ?? '', 'https://example.org/plugin/flux/flux-screens.js?ver=2.0.0-' ), 'plugin screens entry is versioned' );
flxt_same( true, str_starts_with( $pb['screensCss'] ?? '', 'https://example.org/plugin/flux/flux-screens.css' ), 'plugin stylesheet' );
flxt_same( false, isset( flx_admin_screen_bundle( flx_admin_screens()['flxt-screen'] )['screens'] ), 'a FluxWP bundle has no screens entry' );
$_GET['page'] = 'flxt-plugin';
ob_start();
flx_admin_screen_render();
$html = (string) ob_get_clean();
preg_match( '#window\.FlxScreensConfig=(\{.*?\});</script>#s', $html, $c );
$route = json_decode( $c[1] ?? '{}', true )['routes']['flxt-plugin'] ?? array();
flxt_same( 'flx-runtime', $route['bundle']['id'] ?? null, 'plugin route bundle id' );
flxt_same( true, '' !== ( $route['bundle']['screensId'] ?? '' ), 'plugin route names its screens bundle' );
flxt_same( true, str_contains( $html, 'function go(){return;' ), 'FluxModules does not take a plugin screen' );
array_map( 'unlink', glob( $pdir . '*' ) ?: array() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
rmdir( $pdir ); // phpcs:ignore WordPress.WP.AlternativeFunctions

unset( $_GET['page'], $_GET['tab'] );
// Leave wp-admin: is_admin() stays true for the rest of the process otherwise, and plugins run admin-only
// work on shutdown (WooCommerce's new-store setup turns on coming soon).
$GLOBALS['current_screen'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
array_map( 'unlink', glob( $dir . '*' ) ?: array() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions

wp_cache_delete( 'alloptions', 'options' );
foreach ( wp_load_alloptions( true ) as $name => $value ) {
	if ( ! array_key_exists( $name, $flxt_options ) ) {
		delete_option( $name );
	} elseif ( $flxt_options[ $name ] !== $value ) {
		update_option( $name, maybe_unserialize( $flxt_options[ $name ] ) );
	}
}

flxt_done();
