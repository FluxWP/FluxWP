<?php
/**
 * Flux Admin GraphQL contract tests. The documents are the persisted operations the Admin theme screen
 * sends (plugins/flx-admin/graphql/persisted-queries.json). The classic handlers and GraphQL share the
 * FLXA_*_Service classes, so the classic behaviour (lenient form values) is checked through the service too.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-admin/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Admin GraphQL\n";

$GLOBALS['flxa_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXA_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxa_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 13, count( $GLOBALS['flxa_ops'] ), 'Flux Admin ships 13 persisted operations' );

function flxa_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxa_ops'][ $name ], $vars, $user );
}

/** [ code, field ] pairs of a payload's errors. */
function flxa_t_errs( array $r, string $root ): array {
	return array_map( static fn( $e ) => array( $e['code'], $e['field'] ), $r['data'][ $root ]['errors'] ?? array() );
}

$admin = 1;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxa_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );

// The menu catalog is a snapshot taken on admin pages; a WP-CLI run has none, so seed one.
$catalog_before = get_option( 'flxa_menu_catalog' );
update_option(
	'flxa_menu_catalog',
	array(
		'hash'  => 'test',
		'at'    => time(),
		'items' => array(
			array( 'slug' => 'index.php', 'label' => 'Dashboard', 'cap' => 'read', 'icon' => 'grid', 'url' => admin_url( 'index.php' ), 'sub' => array() ),
			array( 'slug' => 'edit.php', 'label' => 'Posts', 'cap' => 'edit_posts', 'icon' => 'pen', 'url' => admin_url( 'edit.php' ), 'sub' => array(
				array( 'slug' => 'edit.php', 'label' => 'All posts', 'cap' => 'edit_posts' ),
				array( 'slug' => 'post-new.php', 'label' => 'Add new', 'cap' => 'edit_posts' ),
			) ),
			array( 'slug' => 'upload.php', 'label' => 'Media', 'cap' => 'upload_files', 'icon' => 'image', 'url' => admin_url( 'upload.php' ), 'sub' => array() ),
			array( 'slug' => 'tools.php', 'label' => 'Tools', 'cap' => 'edit_posts', 'icon' => '', 'url' => admin_url( 'tools.php' ), 'sub' => array() ),
			array( 'slug' => 'profile.php', 'label' => 'Profile', 'cap' => 'read', 'icon' => 'users', 'url' => admin_url( 'profile.php' ), 'sub' => array() ),
		),
	),
	false
);
$settings_before = get_option( 'flxa_settings' );
$menus_before    = get_option( 'flxa_menus' );
$backup_before   = get_option( 'flxa_caps_backup' );
delete_option( 'flxa_caps_backup' );

/* ───────────── Permissions ───────────── */
foreach ( array( 'FlxAdminSettingsScreen', 'FlxAdminMenuScreen', 'FlxAdminCapsScreen' ) as $op ) {
	flxt_same( 'UNAUTHENTICATED', flxt_code( flxa_t_op( $op ) ), "$op: visitor → UNAUTHENTICATED" );
	flxt_same( 'FORBIDDEN', flxt_code( flxa_t_op( $op, array(), $sub ) ), "$op: subscriber → FORBIDDEN" );
}
flxt_same( 'FORBIDDEN', flxt_code( flxa_t_op( 'FlxAdminSettingsUpdate', array( 'input' => array( 'brand' => 'X' ) ), $sub ) ), 'settings update: subscriber → FORBIDDEN' );
flxt_same( 'UNAUTHENTICATED', flxt_code( flxa_t_op( 'FlxAdminModeGet' ) ), 'mode: visitor → UNAUTHENTICATED' );

/* ───────────── Settings ───────────── */
delete_option( 'flxa_settings' );
$r = flxa_t_op( 'FlxAdminSettingsScreen', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'settings: no errors for the administrator' );
$s = $r['data']['flxAdminSettings'] ?? array();
$c = $r['data']['flxAdminSettingsConfig'] ?? array();
flxt_same( array( true, '', 'AUTO', 60, 'GRADIENT', null ), array( $s['enabled'] ?? null, $s['brand'] ?? null, $s['mode'] ?? null, $s['heartbeat'] ?? null, $s['logoStyle'] ?? null, array_key_exists( 'logo', $s ) ? $s['logo'] : 'x' ), 'settings: defaults' );
flxt_same( array( 'MEDIUM', 'FIGTREE', 13, 'COMFORTABLE' ), array( $s['theme']['radius'] ?? null, $s['theme']['font'] ?? null, $s['theme']['fontSize'] ?? null, $s['theme']['density'] ?? null ), 'settings: theme defaults' );
flxt_same( '#F3F2EF', $s['theme']['light']['bg'] ?? null, 'settings: an empty color shows its default' );
flxt_same( array( 'donations_today', 'donations_month', 'pending', 'whatsapp', 'tasks', 'system' ), array_map( 'strtolower', $s['dashStats'] ?? array() ), 'settings: all summary tiles on by default' );
flxt_same( 6, count( $c['dashStatChoices'] ?? array() ), 'config: six summary tile choices' );
flxt_same( '#F3F2EF', $c['colorDefaults']['light']['bg'] ?? null, 'config: color defaults' );
flxt_ok( '' !== ( $s['logoUrl'] ?? '' ), 'settings: effective logo url' );
flxt_ok( preg_match( '/^#[0-9a-f]{6}$/i', (string) ( $s['accent'] ?? '' ) ) === 1, 'settings: effective accent color' );

// Update: partial, typed.
$r = flxa_t_op( 'FlxAdminSettingsUpdate', array( 'input' => array(
	'brand'      => '  Yeşil Türkiye  ',
	'mode'       => 'DARK',
	'heartbeat'  => 120,
	'bottomBar'  => false,
	'dashStats'  => array( 'TASKS', 'SYSTEM' ),
	'quickLinks' => "Events|edit.php?post_type=event\nBad line\nSite|https://example.com/x\nEmpty|",
	'pwaShort'   => str_repeat( 'a', 30 ),
	'theme'      => array( 'radius' => 'LARGE', 'fontSize' => 14, 'dark' => array( 'bg' => '#000000' ), 'light' => array( 'bg' => '#F3F2EF' ) ),
) ), $admin );
$p = $r['data']['flxAdminSettingsUpdate'] ?? array();
flxt_same( array(), $p['errors'] ?? null, 'update: no errors' );
$s = $p['settings'] ?? array();
flxt_same( array( 'Yeşil Türkiye', 'DARK', 120, false ), array( $s['brand'] ?? null, $s['mode'] ?? null, $s['heartbeat'] ?? null, $s['bottomBar'] ?? null ), 'update: values saved (brand trimmed)' );
flxt_same( array( 'TASKS', 'SYSTEM' ), $s['dashStats'] ?? null, 'update: summary tiles' );
flxt_same( "Events|edit.php?post_type=event\nSite|https://example.com/x", $s['quickLinks'] ?? null, 'update: quick links keep valid "Label|Address" lines only' );
flxt_same( 20, strlen( $s['pwaShort'] ?? '' ), 'update: short app name is cut at 20 characters' );
flxt_same( array( 'LARGE', 14, '#000000', '#F3F2EF' ), array( $s['theme']['radius'] ?? null, $s['theme']['fontSize'] ?? null, $s['theme']['dark']['bg'] ?? null, $s['theme']['light']['bg'] ?? null ), 'update: theme patch' );
$stored = get_option( 'flxa_settings' );
flxt_same( array( '', '#000000' ), array( $stored['theme']['light']['bg'] ?? 'x', $stored['theme']['dark']['bg'] ?? 'x' ), 'update: a default color is stored as empty ("not customised")' );
flxt_same( true, ! empty( flxa_settings( true )['pwa'] ) && ! empty( flxa_settings( true )['login'] ), 'update: fields left out stay as they are' );
$r = flxa_t_op( 'FlxAdminSettingsScreen', array(), $admin );
flxt_same( 'Yeşil Türkiye', $r['data']['flxAdminSettings']['brand'] ?? null, 'screen: reads what update wrote' );

// Invalid input is refused as a whole.
$r = flxa_t_op( 'FlxAdminSettingsUpdate', array( 'input' => array( 'brand' => 'Changed', 'heartbeat' => 45, 'accent' => 'blue', 'theme' => array( 'dark' => array( 'text' => 'nope' ) ) ) ), $admin );
$f = array_column( flxa_t_errs( $r, 'flxAdminSettingsUpdate' ), 1 );
sort( $f );
flxt_same( array( 'accent', 'heartbeat', 'theme.dark.text' ), $f, 'update: invalid heartbeat, accent and color → INVALID_INPUT with fields' );
flxt_same( 'Yeşil Türkiye', get_option( 'flxa_settings' )['brand'] ?? null, 'update: nothing is written when a field is invalid' );
$r = flxa_t_op( 'FlxAdminSettingsUpdate', array( 'input' => array( 'logoId' => 999999 ) ), $admin );
flxt_same( array( array( 'INVALID_INPUT', 'logoId' ) ), flxa_t_errs( $r, 'flxAdminSettingsUpdate' ), 'update: unknown logo image → INVALID_INPUT' );

// Accent equal to the theme color is not stored.
$theme_primary = flxa_theme_primary();
$r             = flxa_t_op( 'FlxAdminSettingsUpdate', array( 'input' => array( 'accent' => '#123456' ) ), $admin );
flxt_same( '#123456', $r['data']['flxAdminSettingsUpdate']['settings']['accent'] ?? null, 'accent: a custom color is saved' );
$r = flxa_t_op( 'FlxAdminSettingsUpdate', array( 'input' => array( 'accent' => $theme_primary ) ), $admin );
flxt_same( '', get_option( 'flxa_settings' )['accent'] ?? 'x', 'accent: the theme color is stored as empty (follows the theme)' );

// Classic form: lenient values (service, strict = false).
$r = FLXA_Settings_Service::update( array( 'heartbeat' => 45, 'accent' => 'blue', 'mode' => 'weird' ), false );
flxt_same( array( 60, 'auto' ), is_array( $r ) ? array( $r['heartbeat'], $r['mode'] ) : null, 'classic: invalid values fall back to the default (heartbeat 60, mode auto)' );

// Logo: an uploaded image joins the media library.
$png  = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
$tmp  = wp_tempnam( 'logo.png' );
file_put_contents( $tmp, $png );
$id   = FLXA_Settings_Service::logo_from_upload( array( 'path' => $tmp, 'name' => 'logo.png' ) );
flxt_ok( is_int( $id ) && wp_attachment_is_image( $id ), 'logo: upload becomes a media library image', $id );
$r = flxa_t_op( 'FlxAdminSettingsUpdate', array( 'input' => array( 'logoId' => (int) $id ) ), $admin );
$s = $r['data']['flxAdminSettingsUpdate']['settings'] ?? array();
flxt_same( array( (string) $id, 'logo.png' ), array( $s['logo']['id'] ?? null, $s['logo']['name'] ?? null ), 'logo: FlxFile in the settings' );
flxt_ok( str_contains( (string) ( $s['logoUrl'] ?? '' ), 'logo' ), 'logo: effective url is the chosen logo' );
$r = flxa_t_op( 'FlxAdminSettingsUpdate', array( 'input' => array( 'logoId' => 0 ) ), $admin );
flxt_same( null, array_key_exists( 'logo', $r['data']['flxAdminSettingsUpdate']['settings'] ?? array() ) ? $r['data']['flxAdminSettingsUpdate']['settings']['logo'] : 'x', 'logo: 0 removes it' );
$r = FLXA_Settings_Service::logo_from_upload( array( 'path' => '/nope/nothing.png', 'name' => 'x.png' ) );
flxt_ok( $r instanceof WP_Error, 'logo: a missing upload is an error' );
wp_delete_attachment( (int) $id, true );

/* ───────────── Menu editor ───────────── */
delete_option( 'flxa_menus' );
$r = flxa_t_op( 'FlxAdminMenuScreen', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'menu: no errors' );
$m = $r['data']['flxAdminMenuEditor'] ?? array();
flxt_same( array( 'index.php', 'edit.php', 'upload.php', 'tools.php', 'profile.php' ), array_column( $m['catalog'] ?? array(), 'slug' ), 'menu: catalog from the snapshot' );
flxt_ok( str_contains( (string) ( $m['catalog'][0]['iconSvg'] ?? '' ), '<rect' ), 'menu: icon markup (shapes)' );
flxt_ok( str_contains( (string) ( $m['catalog'][3]['iconSvg'] ?? '' ), '<circle' ), 'menu: item without an icon gets the dot' );
flxt_same( array( '_default', 'administrator' ), array_slice( array_column( $m['roles'] ?? array(), 'key' ), 0, 2 ), 'menu: "all other roles" first, then WordPress roles' );
$def = $m['layouts'][0] ?? array();
flxt_same( array( '_default', false ), array( $def['role'] ?? null, $def['custom'] ?? null ), 'menu: nothing saved → default layout, not custom' );
$items = array_values( array_filter( $m['defaults']['entries'] ?? array(), static fn( $e ) => 'ITEM' === $e['type'] ) );
flxt_same( array( 'index.php', 'edit.php', 'upload.php', 'tools.php', 'profile.php' ), array_column( $items, 'slug' ), 'menu: every menu item is in the default layout exactly once' );
flxt_ok( 'GROUP' === ( $m['defaults']['entries'][0]['type'] ?? '' ), 'menu: default layout starts with a group' );
$editor_access = null;
foreach ( $m['layouts'] as $l ) {
	if ( 'subscriber' === $l['role'] ) {
		$editor_access = $l['access'];
	}
}
flxt_same( array( 'index.php', 'profile.php' ), $editor_access, 'menu: access lists only what the role may open (subscriber: read)' );
flxt_same( array( 'index.php', 'profile.php' ), $m['unblockable'] ?? null, 'menu: unblockable links' );

// Save a layout.
$entries = array(
	array( 'type' => 'GROUP', 'label' => 'Work', 'collapsed' => true ),
	array( 'type' => 'ITEM', 'slug' => 'edit.php', 'label' => 'Articles', 'subHidden' => array( 'post-new.php', 'ghost.php' ), 'blocked' => true ),
	array( 'type' => 'ITEM', 'slug' => 'edit.php' ),
	array( 'type' => 'ITEM', 'slug' => 'not-in-menu.php' ),
	array( 'type' => 'ITEM', 'slug' => 'profile.php', 'blocked' => true, 'hidden' => true ),
);
$r = flxa_t_op( 'FlxAdminMenuLayoutsSave', array( 'input' => array( 'layouts' => array(
	array( 'role' => 'editor', 'entries' => $entries, 'bottom' => array( 'edit.php', 'edit.php', 'zzz', 'index.php', 'upload.php', 'tools.php', 'profile.php' ) ),
	array( 'role' => 'administrator', 'entries' => array( array( 'type' => 'ITEM', 'slug' => 'edit.php', 'blocked' => true ) ) ),
) ) ), $admin );
$p = $r['data']['flxAdminMenuLayoutsSave'] ?? array();
flxt_same( array(), $p['errors'] ?? null, 'menu save: no errors' );
$saved = get_option( 'flxa_menus' )['roles'] ?? array();
flxt_same( array( 'editor', 'administrator' ), array_keys( $saved ), 'menu save: stored per role' );
flxt_same( array( array( 't' => 'g', 'id' => 'g1', 'label' => 'Work', 'c' => 1 ), array( 't' => 'i', 'slug' => 'edit.php', 'label' => 'Articles', 'blocked' => 1, 'sub_hidden' => array( 'post-new.php' ) ), array( 't' => 'i', 'slug' => 'profile.php', 'hidden' => 1 ) ), $saved['editor']['entries'] ?? null, 'menu save: stored shape (duplicates and unknown links dropped, unblockable stays open, unknown submenu dropped)' );
flxt_same( array( 'edit.php', 'index.php', 'upload.php', 'tools.php' ), $saved['editor']['bottom'] ?? null, 'menu save: bottom bar is deduplicated, known links only, 4 at most' );
flxt_same( array( array( 't' => 'i', 'slug' => 'edit.php' ) ), $saved['administrator']['entries'] ?? null, 'menu save: access to the Administrator role cannot be blocked' );
$editor = null;
foreach ( $p['layouts'] ?? array() as $l ) {
	if ( 'editor' === $l['role'] ) {
		$editor = $l;
	}
}
flxt_same( true, $editor['custom'] ?? null, 'menu save: the role is custom now' );
flxt_same( array( 'GROUP', 'ITEM', 'ITEM', 'GROUP', 'ITEM', 'ITEM', 'ITEM' ), array_column( $editor['entries'] ?? array(), 'type' ), 'menu save: items missing from the layout land under a new "Other" group' );
flxt_same( 'Other', $editor['entries'][3]['label'] ?? null, 'menu save: the group is called Other' );
flxt_same( array( true, true ), array( $editor['entries'][0]['collapsed'] ?? null, $editor['entries'][3]['collapsed'] ?? null ), 'menu save: collapsed groups' );

// Roles you leave out stay; reset sends back to default.
$r = flxa_t_op( 'FlxAdminMenuLayoutsSave', array( 'input' => array( 'reset' => array( 'editor' ) ) ), $admin );
flxt_same( array( 'administrator' ), array_keys( get_option( 'flxa_menus' )['roles'] ?? array() ), 'menu reset: only the reset role goes back to default' );
$r = flxa_t_op( 'FlxAdminMenuLayoutsSave', array( 'input' => array( 'layouts' => array( array( 'role' => 'nope', 'entries' => array( array( 'type' => 'ITEM', 'slug' => 'edit.php' ) ) ) ) ) ), $admin );
flxt_same( array( array( 'INVALID_INPUT', 'layouts.0.role' ) ), flxa_t_errs( $r, 'flxAdminMenuLayoutsSave' ), 'menu save: unknown role → INVALID_INPUT' );
$r = flxa_t_op( 'FlxAdminMenuLayoutsSave', array( 'input' => array( 'layouts' => array( array( 'role' => 'editor', 'entries' => array() ) ) ) ), $admin );
flxt_same( array( array( 'INVALID_INPUT', 'layouts.0.entries' ) ), flxa_t_errs( $r, 'flxAdminMenuLayoutsSave' ), 'menu save: empty layout → INVALID_INPUT' );
flxt_same( 'FORBIDDEN', flxt_code( flxa_t_op( 'FlxAdminMenuLayoutsSave', array( 'input' => array( 'reset' => array( 'editor' ) ) ), $sub ) ), 'menu save: subscriber → FORBIDDEN' );

// The classic editor posts every custom role at once (same sanitizer).
FLXA_Menu_Service::save_raw( array( 'author' => array( 'entries' => array( array( 't' => 'i', 'slug' => 'upload.php', 'hidden' => 1 ) ), 'bottom' => array( 'upload.php' ) ) ) );
flxt_same( array( 'author' ), array_keys( get_option( 'flxa_menus' )['roles'] ?? array() ), 'classic: replaces the whole option' );
flxt_same( FLXA_Menu_Service::sanitize( array( 'author' => array( 'entries' => array( array( 't' => 'i', 'slug' => 'upload.php', 'hidden' => 1 ) ) ) ) ), array( 'author' => array( 'entries' => array( array( 't' => 'i', 'slug' => 'upload.php', 'hidden' => 1 ) ), 'bottom' => array() ) ), 'classic: the sanitizer keeps the stored shape' );

/* ───────────── The user's own menu ───────────── */
delete_user_meta( $admin, 'flxa_pins' );
delete_user_meta( $admin, 'flxa_away' );
$r = flxa_t_op( 'FlxAdminUserMenuGet', array(), $admin );
flxt_same( array( 'index.php', 'flx-chat', 'flx-tasks', 'flxb-dashboard' ), $r['data']['flxAdminUserMenu']['pins'] ?? null, 'user menu: default pins before the first change' );
$r = flxa_t_op( 'FlxAdminUserMenuSet', array( 'input' => array( 'op' => 'UNPIN', 'slug' => 'flx-chat' ) ), $admin );
flxt_same( array( 'index.php', 'flx-tasks', 'flxb-dashboard' ), $r['data']['flxAdminUserMenuSet']['menu']['pins'] ?? null, 'user menu: unpin' );
$r = flxa_t_op( 'FlxAdminUserMenuSet', array( 'input' => array( 'op' => 'AWAY', 'slug' => 'flx-tasks' ) ), $admin );
flxt_same( array( array( 'index.php', 'flxb-dashboard' ), array( 'flx-tasks' ) ), array( $r['data']['flxAdminUserMenuSet']['menu']['pins'] ?? null, $r['data']['flxAdminUserMenuSet']['menu']['away'] ?? null ), 'user menu: move to Other takes it off the pins' );
$r = flxa_t_op( 'FlxAdminUserMenuSet', array( 'input' => array( 'op' => 'PIN', 'slug' => 'tools.php' ) ), $admin );
$r = flxa_t_op( 'FlxAdminUserMenuSet', array( 'input' => array( 'op' => 'ORDER', 'order' => array( 'tools.php', 'flxb-dashboard', 'index.php', 'ghost' ) ) ), $admin );
flxt_same( array( 'tools.php', 'flxb-dashboard', 'index.php' ), $r['data']['flxAdminUserMenuSet']['menu']['pins'] ?? null, 'user menu: reorder (only existing pins)' );
$r = flxa_t_op( 'FlxAdminUserMenuSet', array( 'input' => array( 'op' => 'SUB_AWAY', 'slug' => 'edit.php?post_type=page' ) ), $admin );
flxt_same( array( 'edit.php?post_type=page' ), $r['data']['flxAdminUserMenuSet']['menu']['subAway'] ?? null, 'user menu: submenu item moved' );
$r = flxa_t_op( 'FlxAdminUserMenuSet', array( 'input' => array( 'op' => 'SUB_BACK', 'slug' => 'edit.php?post_type=page' ) ), $admin );
flxt_same( array( array(), array( 'edit.php?post_type=page' ) ), array( $r['data']['flxAdminUserMenuSet']['menu']['subAway'] ?? null, $r['data']['flxAdminUserMenuSet']['menu']['subKeep'] ?? null ), 'user menu: submenu item taken back (kept in place)' );
$r = flxa_t_op( 'FlxAdminUserMenuSet', array( 'input' => array( 'op' => 'PIN' ) ), $admin );
flxt_same( array( array( 'INVALID_INPUT', 'slug' ) ), flxa_t_errs( $r, 'flxAdminUserMenuSet' ), 'user menu: pin without a link → INVALID_INPUT slug' );
$r = flxa_t_op( 'FlxAdminUserMenuSet', array( 'input' => array( 'op' => 'PIN', 'slug' => 'edit.php' ) ), $sub );
flxt_same( null, flxt_code( $r ), 'user menu: any signed-in person changes their own' );
flxt_same( 'UNAUTHENTICATED', flxt_code( flxa_t_op( 'FlxAdminUserMenuSet', array( 'input' => array( 'op' => 'PIN', 'slug' => 'x' ) ) ) ), 'user menu: visitor → UNAUTHENTICATED' );
flxt_same( array( 'index.php', 'flx-chat', 'flx-tasks', 'flxb-dashboard', 'edit.php' ), get_user_meta( $sub, 'flxa_pins', true ), 'user menu: stored in the user\'s meta (after the default pins)' );

/* ───────────── Light / dark mode ───────────── */
delete_user_meta( $admin, 'flxa_mode' );
update_option( 'flxa_settings', array( 'mode' => 'light' ) );
flxa_settings( true );
$r = flxa_t_op( 'FlxAdminModeGet', array(), $admin );
flxt_same( 'LIGHT', $r['data']['flxAdminMode'] ?? null, 'mode: the site default until the person chooses' );
$r = flxa_t_op( 'FlxAdminModeSet', array( 'input' => array( 'mode' => 'DARK' ) ), $admin );
flxt_same( 'DARK', $r['data']['flxAdminModeSet']['mode'] ?? null, 'mode: set' );
flxt_same( 'dark', get_user_meta( $admin, 'flxa_mode', true ), 'mode: stored in the user\'s meta (same as the moon/sun button)' );
flxt_same( 'DARK', flxa_t_op( 'FlxAdminModeGet', array(), $admin )['data']['flxAdminMode'] ?? null, 'mode: read back' );
flxt_same( 'auto', get_user_meta( $sub, 'flxa_mode', true ) ?: 'auto', 'mode: other people are not affected' );
$r = FLXA_User_Service::set_mode( 'sepia' );
flxt_same( 'INVALID_INPUT', $r instanceof WP_Error ? ( $r->get_error_data()['graphql_code'] ?? null ) : null, 'classic: unknown mode is refused (service)' );
delete_user_meta( $admin, 'flxa_mode' );

/* ───────────── Dashboard module filters ───────────── */
delete_user_meta( $admin, 'flxa_wprefs' );
$r = flxa_t_op( 'FlxAdminWidgetPrefsGet', array(), $admin );
$w = $r['data']['flxAdminWidgetPrefs'] ?? array();
flxt_same( array( array( 'MINE', 0, 'STATUS' ), array( 14, 'TYPE' ) ), array( array( $w['tasks']['scope'] ?? null, $w['tasks']['list'] ?? null, $w['tasks']['chart'] ?? null ), array( $w['donations']['days'] ?? null, $w['donations']['split'] ?? null ) ), 'widgets: default filters' );
flxt_same( FLXA_User_Service::widgets_available()['donations'], $w['donationsAvailable'] ?? null, 'widgets: Donations module offered only when Flux Donate is usable' );
$r = flxa_t_op( 'FlxAdminWidgetPrefsSet', array( 'input' => array( 'widget' => 'DONATIONS', 'donationsDays' => 30 ) ), $admin );
if ( ! empty( $w['donationsAvailable'] ) ) {
	$p = $r['data']['flxAdminWidgetPrefsSet'] ?? array();
	flxt_same( array( null, 30 ), array( flxt_code( $r ), $p['prefs']['donations']['days'] ?? null ), 'widgets: Donations filters saved' );
	$m = get_user_meta( $admin, 'flxa_wprefs', true );
	flxt_same( 30, (int) ( is_array( $m ) ? ( $m['donations']['days'] ?? 0 ) : 0 ), 'widgets: Donations filters stored in the user\'s meta (same as the classic filter form)' );
} else {
	flxt_same( 'FORBIDDEN', flxt_code( $r ), 'widgets: Donations filters without Flux Donate → FORBIDDEN' );
}
if ( ! empty( $w['tasksAvailable'] ) ) {
	$r = flxa_t_op( 'FlxAdminWidgetPrefsSet', array( 'input' => array( 'widget' => 'TASKS', 'tasksScope' => 'ALL', 'tasksChart' => 'PRIORITY' ) ), $admin );
	$p = $r['data']['flxAdminWidgetPrefsSet'] ?? array();
	flxt_same( array( 'ALL', 'PRIORITY' ), array( $p['prefs']['tasks']['scope'] ?? null, $p['prefs']['tasks']['chart'] ?? null ), 'widgets: Tasks filters saved' );
	flxt_ok( is_string( $p['html'] ?? null ) && str_contains( $p['html'], 'flxa-wf' ), 'widgets: the module HTML comes back' );
} else {
	echo "  skip widgets: Tasks module (Flux Tasks is not usable by this user)\n";
}
flxt_same( 'FORBIDDEN', flxt_code( flxa_t_op( 'FlxAdminWidgetPrefsSet', array( 'input' => array( 'widget' => 'TASKS' ) ), $sub ) ), 'widgets: a person without the Tasks permission → FORBIDDEN' );

/* ───────────── Permissions table ───────────── */
$r = flxa_t_op( 'FlxAdminCapsScreen', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'caps: no errors' );
$k    = $r['data']['flxAdminCaps'] ?? array();
$keys = array();
foreach ( $k['groups'] ?? array() as $g ) {
	foreach ( $g['capabilities'] as $cap ) {
		$keys[] = $cap['key'];
	}
}
flxt_ok( in_array( 'edit_posts', $keys, true ) && in_array( 'manage_options', $keys, true ), 'caps: known permissions are rows' );
flxt_same( FLXA_Caps_Service::shown(), $keys, 'caps: rows match the classic table (flxa_caps_rows)' );
$roles = array_column( $k['roles'] ?? array(), null, 'key' );
flxt_same( array( true, true ), array( $roles['administrator']['isAdministrator'] ?? null, in_array( 'edit_posts', $roles['editor']['granted'] ?? array(), true ) ), 'caps: Administrator flagged, editor has edit_posts' );
flxt_same( null, array_key_exists( 'backupAt', $k ) ? $k['backupAt'] : 'x', 'caps: no backup before the first change' );

$editor_before = get_role( 'editor' )->capabilities;
$r             = flxa_t_op( 'FlxAdminCapsSave', array( 'input' => array( 'roles' => array(
	array( 'role' => 'editor', 'capabilities' => array( 'read', 'edit_posts', 'upload_files' ) ),
	array( 'role' => 'administrator', 'capabilities' => array() ),
) ) ), $admin );
$p = $r['data']['flxAdminCapsSave'] ?? array();
flxt_same( array(), $p['errors'] ?? null, 'caps save: no errors' );
$ed = get_role( 'editor' );
flxt_same( array( true, true, false, false ), array( $ed->has_cap( 'edit_posts' ), $ed->has_cap( 'upload_files' ), $ed->has_cap( 'publish_posts' ), $ed->has_cap( 'moderate_comments' ) ), 'caps save: shown permissions follow the list (the others are turned off)' );
flxt_same( true, get_role( 'administrator' )->has_cap( 'manage_options' ) && get_role( 'administrator' )->has_cap( 'edit_posts' ), 'caps save: the Administrator role is skipped' );
flxt_same( array( true, true ), array( ! empty( $p['caps']['backupAt']['iso'] ), is_array( get_option( 'flxa_caps_backup' ) ) ), 'caps save: the first change takes a backup' );
$bk = get_option( 'flxa_caps_backup' );
flxt_same( true, ! empty( $bk['editor']['publish_posts'] ), 'caps save: the backup holds the old state' );
$r = flxa_t_op( 'FlxAdminCapsSave', array( 'input' => array( 'roles' => array( array( 'role' => 'author', 'capabilities' => array( 'read' ) ) ) ) ), $admin );
flxt_same( true, is_array( get_option( 'flxa_caps_backup' ) ) && ! empty( get_option( 'flxa_caps_backup' )['editor']['publish_posts'] ), 'caps save: the backup is taken once' );
$r = flxa_t_op( 'FlxAdminCapsSave', array( 'input' => array( 'roles' => array( array( 'role' => 'nope', 'capabilities' => array() ) ) ) ), $admin );
flxt_same( array( array( 'INVALID_INPUT', 'roles.0.role' ) ), flxa_t_errs( $r, 'flxAdminCapsSave' ), 'caps save: unknown role → INVALID_INPUT' );
$r = flxa_t_op( 'FlxAdminCapsSave', array( 'input' => array( 'roles' => array( array( 'role' => 'editor', 'capabilities' => array( 'edit_posts', 'fly_to_moon' ) ) ) ) ), $admin );
flxt_same( array( array( 'INVALID_INPUT', 'roles.0.capabilities' ) ), flxa_t_errs( $r, 'flxAdminCapsSave' ), 'caps save: a permission outside the table → INVALID_INPUT' );
flxt_same( true, get_role( 'editor' )->has_cap( 'upload_files' ), 'caps save: nothing is written when a part is invalid' );
flxt_same( 'FORBIDDEN', flxt_code( flxa_t_op( 'FlxAdminCapsSave', array( 'input' => array( 'roles' => array() ) ), $sub ) ), 'caps save: subscriber → FORBIDDEN' );

$r = flxa_t_op( 'FlxAdminCapsRestore', array( 'input' => array( 'confirm' => false ) ), $admin );
flxt_same( array( array( 'INVALID_INPUT', 'confirm' ) ), flxa_t_errs( $r, 'flxAdminCapsRestore' ), 'caps restore: needs confirm' );
$r = flxa_t_op( 'FlxAdminCapsRestore', array( 'input' => array( 'confirm' => true ) ), $admin );
flxt_same( array(), $r['data']['flxAdminCapsRestore']['errors'] ?? null, 'caps restore: no errors' );
$after = get_role( 'editor' )->capabilities;
ksort( $after );
ksort( $editor_before );
flxt_same( $editor_before, $after, 'caps restore: the editor role is exactly as before' );
flxt_same( false, get_option( 'flxa_caps_backup' ), 'caps restore: the backup is removed' );
$r = flxa_t_op( 'FlxAdminCapsRestore', array( 'input' => array( 'confirm' => true ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'caps restore: without a backup → NOT_FOUND' );

// Persisted: the screen's documents are allowed in strict mode.
wp_set_current_user( $admin );
$r = graphql( array( 'queryId' => hash( 'sha256', $GLOBALS['flxa_ops']['FlxAdminSettingsScreen'] ) ) );
flxt_ok( ! empty( $r['data']['flxAdminSettings'] ), 'persisted queryId runs (strict mode)' );

// Clean up.
foreach ( array( 'flxa_settings' => $settings_before, 'flxa_menus' => $menus_before, 'flxa_menu_catalog' => $catalog_before, 'flxa_caps_backup' => $backup_before ) as $opt => $val ) {
	false === $val ? delete_option( $opt ) : update_option( $opt, $val );
}
delete_user_meta( $admin, 'flxa_pins' );
delete_user_meta( $admin, 'flxa_away' );
delete_user_meta( $admin, 'flxa_sub_away' );
delete_user_meta( $admin, 'flxa_sub_keep' );
delete_user_meta( $admin, 'flxa_wprefs' );
wp_delete_user( $sub );
flxt_done();
