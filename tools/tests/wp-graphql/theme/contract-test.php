<?php
/**
 * FluxWP theme GraphQL contract tests. The documents are the persisted operations the Theme Settings
 * and System Check screens send (themes/fluxwp/graphql/persisted-queries.json). The classic pages and
 * GraphQL share FLX_Theme_Service and the same sanitizers, so classic behaviour is checked through them.
 *
 *   wp eval-file tools/tests/wp-graphql/theme/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "FluxWP theme GraphQL\n";

$GLOBALS['flxth_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( get_theme_file_path( 'graphql/persisted-queries.json' ) ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxth_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 7, count( $GLOBALS['flxth_ops'] ), 'the theme ships 7 persisted operations' );
flxt_same( 'fluxwp', get_stylesheet(), 'the FluxWP theme is active' );

function flxth_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxth_ops'][ $name ], $vars, $user );
}

/** Saves and returns the payload. */
function flxth_save( array $input, int $user = 1 ): array {
	$r = flxth_op( 'FlxThemeSettingsSave', array( 'input' => $input ), $user );
	return $r['data']['flxThemeSettingsSave'] ?? array( 'raw' => $r );
}

$admin = 1;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxth_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$boss  = (int) wp_insert_user( array( 'user_login' => 'flxth_boss_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
( new WP_User( $boss ) )->add_cap( 'manage_options' ); // May change settings, but not switch themes or add raw code.

delete_option( 'flx_ayarlar' );
delete_option( 'flx_pay_logos' );

// Permissions.
foreach ( array( 'FlxThemeSettings', 'FlxThemeSystem' ) as $op ) {
	flxt_same( 'UNAUTHENTICATED', flxt_code( flxth_op( $op ) ), "$op: visitor → UNAUTHENTICATED" );
	flxt_same( 'FORBIDDEN', flxt_code( flxth_op( $op, array(), $sub ) ), "$op: subscriber → FORBIDDEN" );
}
flxt_same( 'UNAUTHENTICATED', flxt_code( flxth_op( 'FlxThemeSettingsSave', array( 'input' => array( 'darkMode' => 'OFF' ) ) ) ), 'save: visitor → UNAUTHENTICATED' );
flxt_same( 'FORBIDDEN', flxt_code( flxth_op( 'FlxThemeSettingsSave', array( 'input' => array( 'darkMode' => 'OFF' ) ), $sub ) ), 'save: subscriber → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxth_op( 'FlxThemePayLogosSave', array( 'input' => array( 'automatic' => true ) ), $sub ) ), 'logos save: subscriber → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxth_op( 'FlxThemeMigrationSources', array(), $boss ) ), 'migration list: manage_options without switch_themes → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxth_op( 'FlxThemeMigrate', array( 'input' => array( 'source' => 'x' ) ), $boss ) ), 'migrate: manage_options without switch_themes → FORBIDDEN' );

// Settings screen data.
$r = flxth_op( 'FlxThemeSettings', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'settings: no errors for the administrator' );
$s = $r['data']['flxThemeSettings'] ?? array();
flxt_same( array( 'LIVE', 'SHOP', 'DOTS', 'AUTO', true, 'INFINITE', true ), array( $s['visibility'] ?? null, $s['siteType'] ?? null, $s['pattern'] ?? null, $s['darkMode'] ?? null, $s['animations'] ?? null, $s['pagination'] ?? null, $s['cookieEnabled'] ?? null ), 'settings: defaults' );
flxt_same( 'CARD', $s['cookiePosition'] ?? null, 'settings: cookie banner is a card by default' );
flxt_ok( is_string( $s['consentScripts'] ?? null ), 'settings: administrator sees consentScripts' );
flxt_same( true, $s['canEditScripts'] ?? null, 'settings: administrator may edit scripts' );
flxt_ok( is_array( $r['data']['pages']['nodes'] ?? null ), 'settings: pages list for the cookie policy select' );
flxt_same( flx_coming_soon_store_texts( false )['title'], $s['storeNoticeTitleDefault'] ?? null, 'settings: store notice default title' );
$r = flxth_op( 'FlxThemeSettings', array(), $boss );
flxt_ok( array_key_exists( 'consentScripts', $r['data']['flxThemeSettings'] ?? array() ) && null === $r['data']['flxThemeSettings']['consentScripts'], 'settings: consentScripts is null without unfiltered_html' );
flxt_same( false, $r['data']['flxThemeSettings']['canEditScripts'] ?? null, 'settings: canEditScripts is false without unfiltered_html' );

// Save: partial, validated, sanitised.
$p = flxth_save( array( 'darkMode' => 'OFF', 'animations' => false, 'pagination' => 'NUMBERED', 'bankHolder' => '  Yeşil Türkiye  ', 'bankIban' => 'tr33 0006 1005 1978 6457 8413 26' ) );
flxt_same( array(), $p['errors'] ?? null, 'save: valid input has no errors' );
flxt_same( array( 'OFF', false, 'NUMBERED', 'Yeşil Türkiye', 'TR330006100519786457841326' ), array( $p['settings']['darkMode'] ?? null, $p['settings']['animations'] ?? null, $p['settings']['pagination'] ?? null, $p['settings']['bankHolder'] ?? null, $p['settings']['bankIban'] ?? null ), 'save: values returned (IBAN cleaned)' );
flxt_same( array( 'off', 'off', 'numara', 'TR330006100519786457841326' ), array( flx_ayar( 'dark_mode' ), flx_ayar( 'animasyon' ), flx_ayar( 'sayfalama' ), flx_ayar( 'bank_iban' ) ), 'save: classic option values (what the theme reads)' );
flxt_same( 1, (int) flx_ayar( 'cookie_enabled' ), 'save: a field that was not sent stays as it was' );
flxt_same( 'dots', flx_ayar( 'decoration_pattern' ), 'save: pattern stays' );

$p = flxth_save( array( 'bankIban' => 'TR12', 'ga4Id' => 'nope', 'tableHead' => 'red', 'contactEmail' => 'not-an-email', 'darkMode' => 'AUTO' ) );
$fields = array_column( $p['errors'] ?? array(), 'field' );
sort( $fields );
flxt_same( array( 'bank_iban', 'contact_email', 'ga4_id', 'table_head' ), $fields, 'save: every invalid field is reported with its field' );
flxt_same( 'INVALID_INPUT', $p['errors'][0]['code'] ?? null, 'save: error code INVALID_INPUT' );
flxt_same( 'off', flx_ayar( 'dark_mode' ), 'save: nothing is written when a field is invalid' );

$p = flxth_save( array( 'ga4Id' => 'g-abcd1234', 'gaMode' => 'AFTER', 'tableHead' => '#F1F2F4', 'cookieTitle' => 'Çerezler', 'cookiePage' => 0, 'activeCampaign' => 0 ) );
flxt_same( array( 'G-ABCD1234', 'AFTER', '#F1F2F4', 'Çerezler' ), array( $p['settings']['ga4Id'] ?? null, $p['settings']['gaMode'] ?? null, $p['settings']['tableHead'] ?? null, $p['settings']['cookieTitle'] ?? null ), 'save: GA4 id upper-cased, mode, colour, banner title' );

$rev = (int) flx_ayar( 'cookie_rev' );
$p   = flxth_save( array( 'cookieReset' => true ) );
flxt_same( $rev + 1, $p['settings']['cookieRevision'] ?? null, 'save: cookieReset raises the consent version' );
$p = flxth_save( array( 'cookieEnabled' => false ) );
flxt_same( $rev + 1, $p['settings']['cookieRevision'] ?? null, 'save: the version stays without a reset' );
flxt_same( false, flx_cookie_active(), 'save: cookie banner is off for the classic code too' );

// Raw scripts need unfiltered_html.
$p = flxth_save( array( 'consentScripts' => '<script>window.x=1</script>' ) );
flxt_same( '<script>window.x=1</script>', $p['settings']['consentScripts'] ?? null, 'save: administrator saves consent scripts' );
$p = flxth_save( array( 'consentScripts' => '<script>evil()</script>' ), $boss );
flxt_same( array( 'consent_scripts' ), array_column( $p['errors'] ?? array(), 'field' ), 'save: consent scripts refused without unfiltered_html' );
flxt_same( '<script>window.x=1</script>', flx_ayar( 'consent_scripts' ), 'save: refused scripts are not written' );

// Visibility: store needs WooCommerce; the others work and clear purge-able state.
$wc = class_exists( 'WooCommerce' );
$p  = flxth_save( array( 'visibility' => 'STORE' ) );
if ( $wc ) {
	flxt_same( 'STORE', $p['settings']['visibility'] ?? null, 'visibility: store mode with WooCommerce' );
} else {
	flxt_same( array( 'visibility' ), array_column( $p['errors'] ?? array(), 'field' ), 'visibility: store mode refused without WooCommerce' );
}
$p = flxth_save( array( 'visibility' => 'COMING_SOON', 'comingSoonTitle' => 'Yakında' ) );
flxt_same( array( 'COMING_SOON', 'Yakında' ), array( $p['settings']['visibility'] ?? null, $p['settings']['comingSoonTitle'] ?? null ), 'visibility: entire-site coming soon' );
flxt_same( 'coming-soon', flx_visibility_mode(), 'visibility: the gate reads the same mode' );
$p = flxth_save( array( 'visibility' => 'LIVE' ) );
flxt_same( 'LIVE', $p['settings']['visibility'] ?? null, 'visibility: live again' );
flxt_same( 'live', flx_visibility_mode(), 'visibility: the gate is open again' );

// Presets and Flux AI write the same option through the generic settings type.
$g = graphql( array( 'query' => '{ flxSettings { themeSettings { bankHolder darkMode } } }' ) );
wp_set_current_user( $admin );
$g = graphql( array( 'query' => '{ flxSettings { themeSettings { bankHolder darkMode } } }' ) );
flxt_same( 'Yeşil Türkiye', $g['data']['flxSettings']['themeSettings']['bankHolder'] ?? null, 'generic flxSettings.themeSettings reads the same option' );
$g = graphql( array( 'query' => 'mutation { flxSettingsUpdate(input: { themeSettings: { bankIban: "bad" } }) { settings { themeSettings { bankIban } } errors { field } } }' ) );
flxt_same( 'TR330006100519786457841326', flx_ayar( 'bank_iban' ), 'generic update: the theme sanitiser still guards the IBAN (also outside wp-admin)' );

// Global styles: public.
$r = flxth_op( 'FlxThemeGlobalStyles' );
flxt_same( null, $r['errors'] ?? null, 'global styles: visitors can read them' );
$st = $r['data']['flxThemeGlobalStyles'] ?? array();
flxt_ok( count( $st['palette'] ?? array() ) > 0, 'global styles: the palette is not empty', $st );
$slugs = array_column( $st['palette'] ?? array(), 'slug' );
flxt_ok( in_array( 'table-head', $slugs, true ) || count( $slugs ) > 3, 'global styles: theme.json colours are listed' );
flxt_same( array( false, '#F1F2F4', 'DOTS' ), array( $st['darkMode'] ?? null, $st['tableHead'] ?? null, $st['pattern'] ?? null ), 'global styles: dark mode (off now), table head and pattern follow Theme Settings' );
flxt_ok( count( $st['fonts'] ?? array() ) > 0 && ! empty( $st['fonts'][0]['fontFamily'] ), 'global styles: font families come from theme.json', $st['fonts'] ?? null );

// Payment logos.
$r = flxth_op( 'FlxThemeSettings', array(), $admin );
$l = $r['data']['flxThemePayLogos'] ?? array();
flxt_same( true, $l['automatic'] ?? null, 'logos: automatic at first' );
flxt_same( array( 'iyzico' ), array_column( $l['current'] ?? array(), 'key' ), 'logos: the iyzico band is the automatic default' );
$p = flxth_op( 'FlxThemePayLogosSave', array( 'input' => array( 'keys' => array() ) ), $admin )['data']['flxThemePayLogosSave'] ?? array();
flxt_same( array( false, array() ), array( $p['logos']['automatic'] ?? null, $p['logos']['current'] ?? null ), 'logos: an empty hand-picked list shows no logos' );
$p = flxth_op( 'FlxThemePayLogosSave', array( 'input' => array( 'keys' => array( 'iyzico', 'nope', 'a:999999', 'iyzico' ) ) ), $admin )['data']['flxThemePayLogosSave'] ?? array();
flxt_same( array( false, array( 'iyzico' ) ), array( $p['logos']['automatic'] ?? null, array_column( $p['logos']['current'] ?? array(), 'key' ) ), 'logos: unknown keys and missing images are dropped, duplicates merged' );
flxt_same( array( 'iyzico' ), array_column( flx_pay_logos(), 'key' ), 'logos: the footer helper shows the same list' );
flxt_same( array( 'iyzico' ), array_column( $p['logos']['automaticList'] ?? array(), 'key' ), 'logos: automaticList shows what the automatic list would be' );
$att = (int) wp_insert_attachment( array( 'post_title' => 'Logo', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), 'flxth-logo.png' );
$p   = flxth_op( 'FlxThemePayLogosSave', array( 'input' => array( 'keys' => array( 'a:' . $att, 'iyzico' ) ) ), $admin )['data']['flxThemePayLogosSave'] ?? array();
flxt_ok( 'iyzico' === ( $p['logos']['current'][ count( $p['logos']['current'] ?? array() ) - 1 ]['key'] ?? '' ), 'logos: order is kept (iyzico last)' );
$p = flxth_op( 'FlxThemePayLogosSave', array( 'input' => array( 'automatic' => true ) ), $admin )['data']['flxThemePayLogosSave'] ?? array();
flxt_same( array( true, array( 'iyzico' ) ), array( $p['logos']['automatic'] ?? null, array_column( $p['logos']['current'] ?? array(), 'key' ) ), 'logos: back to automatic' );
wp_delete_attachment( $att, true );

// System Check.
$r   = flxth_op( 'FlxThemeSystem', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'system: no errors for the administrator' );
$sys = $r['data']['flxThemeSystem'] ?? array();
$checks = $sys['checks'] ?? array();
flxt_ok( count( $checks ) > 5, 'system: checks of the theme and the plugins are listed', count( $checks ) );
flxt_ok( ! array_diff( array_unique( array_column( $checks, 'status' ) ), array( 'OK', 'WARNING', 'ERROR', 'INFO' ) ), 'system: statuses are the enum' );
flxt_ok( in_array( 'theme.site.tz', array_column( $checks, 'id' ), true ), 'system: theme rows are present', array_column( $checks, 'id' ) );
flxt_same( count( flx_sk_collect() ), count( $checks ), 'system: same rows as the classic page' );
flxt_ok( isset( $sys['checkedAt']['timestamp'] ) && abs( (int) $sys['checkedAt']['timestamp'] - time() ) < 120, 'system: checkedAt is now' );
$order = array( 'ERROR' => 0, 'WARNING' => 1, 'INFO' => 2, 'OK' => 3 );
$by    = array();
foreach ( $checks as $c ) {
	$by[ $c['group'] ][] = $order[ $c['status'] ];
}
$sorted = true;
foreach ( $by as $list ) {
	$copy = $list;
	sort( $copy );
	$sorted = $sorted && $copy === $list;
}
flxt_ok( $sorted, 'system: problems come first inside every group' );
flxt_same( function_exists( 'flxseo_system_fix_log' ), $sys['fixLogAvailable'] ?? null, 'system: the fix log is available exactly when Flux SEO is active' );

// Migration from a previous theme.
$r = flxth_op( 'FlxThemeMigrationSources', array(), $admin );
flxt_same( array(), $r['data']['flxThemeMigrationSources'] ?? null, 'migration: nothing to migrate at first' );
add_filter( 'flx_tasi_sources', static fn( $s ) => array_merge( (array) $s, array( 'oldtheme' ) ) );
update_option( 'theme_mods_oldtheme', array( 'custom_logo' => 123, 'flxth_marker' => 'copied' ) );
$r = flxth_op( 'FlxThemeMigrationSources', array(), $admin );
flxt_same( array( 'oldtheme' ), array_column( $r['data']['flxThemeMigrationSources'] ?? array(), 'stylesheet' ), 'migration: a previous theme with data is listed' );
$m = flxth_op( 'FlxThemeMigrate', array( 'input' => array( 'source' => 'nope' ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $m ), 'migrate: unknown theme → NOT_FOUND' );
$m = flxth_op( 'FlxThemeMigrate', array( 'input' => array( 'source' => 'oldtheme' ) ), $admin );
flxt_same( null, $m['errors'] ?? null, 'migrate: runs for the administrator' );
flxt_ok( count( $m['data']['flxThemeMigrate']['log'] ?? array() ) >= 1, 'migrate: returns what was done' );
flxt_same( 'copied', ( (array) get_option( 'theme_mods_' . get_stylesheet() ) )['flxth_marker'] ?? null, 'migrate: the theme settings were copied' );
$r = flxth_op( 'FlxThemeMigrationSources', array(), $admin );
flxt_ok( ! empty( $r['data']['flxThemeMigrationSources'][0]['lastRun']['label'] ), 'migration: the last run is shown' );
$m = flxth_op( 'FlxThemeMigrate', array( 'input' => array( 'source' => 'oldtheme' ) ), $admin );
flxt_ok( empty( $m['errors'] ), 'migrate: safe to repeat' );
delete_option( 'theme_mods_oldtheme' );

// The schema is lazy: no GraphQL schema is loaded on a plain request, and the service stays usable.
flxt_ok( class_exists( 'FLX_Theme_Service' ) && function_exists( 'flx_ayar' ), 'service: loaded without a GraphQL request' );

flxt_done();
