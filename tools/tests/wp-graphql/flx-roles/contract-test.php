<?php
/**
 * Flux Roles GraphQL contract tests. The documents are the persisted operations the Roles and access
 * screen sends (plugins/flx-roles/graphql/persisted-queries.json). The classic page handlers and
 * GraphQL share FLXR_Roles_Service, so the classic behaviour is checked through the service too.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-roles/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Roles GraphQL\n";

$GLOBALS['flxr_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXR_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxr_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 8, count( $GLOBALS['flxr_ops'] ), 'Flux Roles ships 8 persisted operations' );

function flxr_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxr_ops'][ $name ], $vars, $user );
}

/** Role row by key from a list. */
function flxr_t_role( array $roles, string $key ): array {
	foreach ( $roles as $r ) {
		if ( ( $r['key'] ?? '' ) === $key ) {
			return $r;
		}
	}
	return array();
}

$admin = 1;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxr_sub_' . wp_rand(), 'user_email' => 'flxr_sub_' . wp_rand() . '@example.com', 'user_pass' => wp_generate_password(), 'role' => 'subscriber', 'display_name' => 'Ayşe Test' ) );
$sub2  = (int) wp_insert_user( array( 'user_login' => 'flxr_sub2_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber', 'display_name' => 'Zeynep Test' ) );
$boss  = (int) wp_insert_user( array( 'user_login' => 'flxr_boss_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
( new WP_User( $boss ) )->add_cap( 'manage_options' ); // Manages options but cannot promote users.

// Permissions.
$r = flxr_t_op( 'FlxRolesScreen' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'screen: visitor → UNAUTHENTICATED' );
$r = flxr_t_op( 'FlxRolesScreen', array(), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'screen: subscriber → FORBIDDEN' );

// Screen data.
$r = flxr_t_op( 'FlxRolesScreen', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'screen: no errors for the administrator' );
$d     = $r['data'] ?? array();
$keys  = array_column( $d['flxRoles'] ?? array(), 'key' );
flxt_same( array( 'flx_yonetici', 'flx_idareci', 'flx_personel' ), array_slice( $keys, 0, 3 ), 'screen: built-in roles, broadest first' );
$staff = flxr_t_role( $d['flxRoles'] ?? array(), 'flx_personel' );
flxt_same( array( 'Staff', false, 'auto' ), array( $staff['label'] ?? null, $staff['custom'] ?? null, $staff['home'] ?? null ), 'screen: staff label, built-in, home auto' );
flxt_ok( in_array( 'read', $staff['capabilities'] ?? array(), true ) && in_array( 'flx_referans', $staff['capabilities'] ?? array(), true ), 'screen: staff defaults (read, flx_referans)' );
$want = array();
foreach ( flxr_catalog() as $g ) {
	foreach ( array_keys( $g['caps'] ) as $cap ) {
		if ( flxr_role_has( 'flx_personel', $cap ) ) {
			$want[] = $cap;
		}
	}
}
flxt_same( $want, $staff['capabilities'] ?? null, 'screen: role capabilities match flxr_role_has (classic table)' );
$groups = $d['flxRoleCapabilityGroups'] ?? array();
flxt_same( array_keys( flxr_catalog() ), array_column( $groups, 'key' ), 'screen: groups in catalog order' );
flxt_same( array( 'key' => 'read', 'locked' => true ), array_intersect_key( $groups[0]['capabilities'][0] ?? array(), array( 'key' => 1, 'locked' => 1 ) ), 'screen: "can enter the panel" is locked' );
flxt_ok( array_key_exists( 'plugin', $groups[0] ?? array() ) && null === $groups[0]['plugin'], 'screen: General has no plugin' );
$tasks = array_values( array_filter( $groups, static fn( $g ) => 'gorev' === $g['key'] ) )[0] ?? array();
flxt_same( array( 'Flux Tasks', true ), array( $tasks['plugin'] ?? null, $tasks['active'] ?? null ), 'screen: Tasks group is active (Flux Tasks installed)' );
$manage = array_values( array_filter( $tasks['capabilities'] ?? array(), static fn( $c ) => 'flxg_manage' === $c['key'] ) )[0] ?? array();
flxt_same( true, $manage['broad'] ?? null, 'screen: broad access flag' );
$cfg = $d['flxRolesConfig'] ?? array();
flxt_same( 30, $cfg['referralDays'] ?? null, 'screen: referral days default 30' );
flxt_same( array_keys( flxr_home_choices() ), array_column( $cfg['homeChoices'] ?? array(), 'key' ), 'screen: home choices' );
flxt_ok( str_ends_with( (string) ( $cfg['newUserUrl'] ?? '' ), 'user-new.php' ), 'screen: new user link' );

// Save: table + settings in one go, atomic.
$staff_caps = array( 'flx_referans', 'flxg_use', 'flxb_view_own' );
$r          = flxr_t_op( 'FlxRolesSave', array( 'input' => array( 'roles' => array( array( 'key' => 'flx_personel', 'capabilities' => $staff_caps, 'home' => 'profile' ) ), 'referralDays' => 45 ) ), $admin );
$p          = $r['data']['flxRolesSave'] ?? array();
flxt_same( array(), $p['errors'] ?? null, 'save: no errors' );
$staff = flxr_t_role( $p['roles'] ?? array(), 'flx_personel' );
flxt_same( array( 'read', 'flx_referans', 'flxg_use', 'flxb_view_own' ), $staff['capabilities'] ?? null, 'save: only the listed permissions are on (read stays, locked)' );
flxt_same( array( 'profile', 45 ), array( $staff['home'] ?? null, $p['config']['referralDays'] ?? null ), 'save: home and referral days' );
flxt_same( false, get_role( 'flx_personel' )->has_cap( 'flxs_use' ), 'save: turned-off permission is removed from the WordPress role' );
flxt_same( flxr_t_role( $d['flxRoles'] ?? array(), 'flx_idareci' )['capabilities'] ?? 'x', flxr_t_role( $p['roles'] ?? array(), 'flx_idareci' )['capabilities'] ?? 'y', 'save: roles not in the input stay as they are' );

$r = flxr_t_op( 'FlxRolesSave', array( 'input' => array( 'roles' => array( array( 'key' => 'nope' ) ) ) ), $admin );
flxt_same( array( array( 'code' => 'INVALID_INPUT', 'field' => 'roles.0.key' ) ), array_map( static fn( $e ) => array( 'code' => $e['code'], 'field' => $e['field'] ), $r['data']['flxRolesSave']['errors'] ?? array() ), 'save: unknown role → INVALID_INPUT roles.0.key' );
$r = flxr_t_op( 'FlxRolesSave', array( 'input' => array( 'roles' => array( array( 'key' => 'flx_personel', 'capabilities' => array( 'flxs_use', 'not_a_cap' ) ) ), 'referralDays' => 60 ) ), $admin );
flxt_same( 'roles.0.capabilities', $r['data']['flxRolesSave']['errors'][0]['field'] ?? null, 'save: unknown permission → INVALID_INPUT' );
flxt_same( array( false, 45 ), array( get_role( 'flx_personel' )->has_cap( 'flxs_use' ), FLXR_Roles_Service::referral_days() ), 'save: nothing is written when a part is invalid' );
$r = flxr_t_op( 'FlxRolesSave', array( 'input' => array( 'referralDays' => 0 ) ), $admin );
flxt_same( 'referralDays', $r['data']['flxRolesSave']['errors'][0]['field'] ?? null, 'save: referral days 0 → INVALID_INPUT' );
$r = flxr_t_op( 'FlxRolesSave', array( 'input' => array( 'roles' => array( array( 'key' => 'flx_personel', 'home' => 'mars' ) ) ) ), $admin );
flxt_same( 'roles.0.home', $r['data']['flxRolesSave']['errors'][0]['field'] ?? null, 'save: unknown home screen → INVALID_INPUT' );
$r = flxr_t_op( 'FlxRolesSave', array( 'input' => array( 'referralDays' => 10 ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'save: subscriber → FORBIDDEN' );

// Reset.
$r = flxr_t_op( 'FlxRoleReset', array( 'input' => array( 'key' => 'flx_personel' ) ), $admin );
$staff = $r['data']['flxRoleReset']['role'] ?? array();
$want  = array_values( array_filter( flxr_default_caps( 'flx_personel' ), static fn( $c ) => flxr_role_has( 'flx_personel', $c ) ) );
flxt_same( true, ! array_diff( $want, $staff['capabilities'] ?? array() ) && in_array( 'flxs_use', $staff['capabilities'] ?? array(), true ), 'reset: defaults are back' );
$r = flxr_t_op( 'FlxRoleReset', array( 'input' => array( 'key' => 'nope' ) ), $admin );
flxt_same( 'key', $r['data']['flxRoleReset']['errors'][0]['field'] ?? null, 'reset: unknown role → INVALID_INPUT' );

// Custom roles.
$r = flxr_t_op( 'FlxRoleCreate', array( 'input' => array( 'name' => 'Gönüllü' ) ), $admin );
$c = $r['data']['flxRoleCreate']['role'] ?? array();
flxt_same( array( 'flx_gonullu', 'Gönüllü', true, array( 'read' ), 0 ), array( $c['key'] ?? null, $c['label'] ?? null, $c['custom'] ?? null, $c['capabilities'] ?? null, $c['memberCount'] ?? null ), 'create: key, name, custom, panel access only' );
$r = flxr_t_op( 'FlxRoleCreate', array( 'input' => array( 'name' => '   ' ) ), $admin );
flxt_same( array( 'INVALID_INPUT', 'name' ), array( $r['data']['flxRoleCreate']['errors'][0]['code'] ?? null, $r['data']['flxRoleCreate']['errors'][0]['field'] ?? null ), 'create: empty name → INVALID_INPUT name' );
$r = flxr_t_op( 'FlxRoleDelete', array( 'input' => array( 'key' => 'flx_personel' ) ), $admin );
flxt_same( 'key', $r['data']['flxRoleDelete']['errors'][0]['field'] ?? null, 'delete: a built-in role cannot be deleted' );
flxt_ok( (bool) get_role( 'flx_personel' ), 'delete: built-in role still exists' );

// People.
$r = flxr_t_op( 'FlxRoleMemberAdd', array( 'input' => array( 'role' => 'flx_personel', 'login' => 'nobody_' . wp_rand() ) ), $admin );
flxt_same( array( 'INVALID_INPUT', 'login' ), array( $r['data']['flxRoleMemberAdd']['errors'][0]['code'] ?? null, $r['data']['flxRoleMemberAdd']['errors'][0]['field'] ?? null ), 'member add: unknown user → INVALID_INPUT login' );
$r = flxr_t_op( 'FlxRoleMemberAdd', array( 'input' => array( 'role' => 'flx_personel', 'login' => 'admin' ) ), $admin );
flxt_same( 'login', $r['data']['flxRoleMemberAdd']['errors'][0]['field'] ?? null, 'member add: an administrator cannot get the role' );
$r = flxr_t_op( 'FlxRoleMemberAdd', array( 'input' => array( 'role' => 'flx_personel', 'login' => get_userdata( $sub )->user_login ) ), $boss );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'member add: manage_options without promote_users → FORBIDDEN (all caps)' );
$r = flxr_t_op( 'FlxRoleMemberAdd', array( 'input' => array( 'role' => 'flx_personel', 'login' => get_userdata( $sub )->user_email ) ), $admin );
$m = $r['data']['flxRoleMemberAdd'] ?? array();
flxt_same( array( $sub, 'Ayşe Test' ), array( $m['member']['databaseId'] ?? null, $m['member']['name'] ?? null ), 'member add: by email' );
flxt_same( array( 'flx_personel' ), array_values( get_userdata( $sub )->roles ), 'member add: subscriber role replaced' );
flxt_ok( str_contains( (string) ( $m['member']['referralLink'] ?? '' ), '?ref=' ), 'member add: staff gets a referral link' );
flxt_same( 1, flxr_t_role( $m['roles'] ?? array(), 'flx_personel' )['memberCount'] ?? null, 'member add: role count in the payload' );
flxr_set_user_phone( $sub, '05301234567' );
flxr_t_op( 'FlxRoleMemberAdd', array( 'input' => array( 'role' => 'flx_personel', 'login' => get_userdata( $sub2 )->user_login ) ), $admin );
$r = flxr_t_op( 'FlxRoleMembers', array( 'key' => 'flx_personel', 'first' => 1 ), $admin );
$page = $r['data']['flxRole']['members'] ?? array();
flxt_same( array( array( 'Ayşe Test' ), true, 2 ), array( array_column( $page['nodes'] ?? array(), 'name' ), $page['pageInfo']['hasNextPage'] ?? null, $page['totalCount'] ?? null ), 'members: first page by name, more follow, total' );
flxt_same( '+905301234567', $page['nodes'][0]['phone']['e164'] ?? null, 'members: WhatsApp number as FlxPhone' );
$r = flxr_t_op( 'FlxRoleMembers', array( 'key' => 'flx_personel', 'first' => 1, 'after' => $page['pageInfo']['endCursor'] ?? '' ), $admin );
flxt_same( array( 'Zeynep Test' ), array_column( $r['data']['flxRole']['members']['nodes'] ?? array(), 'name' ), 'members: next page' );
$r = flxt_gql( '{ flxRole(key:"nope") { key } }', array(), $admin );
flxt_ok( array_key_exists( 'flxRole', $r['data'] ?? array() ) && null === $r['data']['flxRole'] && empty( $r['errors'] ), 'flxRole: unknown key → null' );

// Moving to a custom role removes the other FluxWP role.
flxr_t_op( 'FlxRoleMemberAdd', array( 'input' => array( 'role' => 'flx_gonullu', 'login' => get_userdata( $sub2 )->user_login ) ), $admin );
flxt_same( array( 'flx_gonullu' ), array_values( get_userdata( $sub2 )->roles ), 'member add: one FluxWP role at a time' );
$r = flxr_t_op( 'FlxRoleDelete', array( 'input' => array( 'key' => 'flx_gonullu' ) ), $admin );
flxt_same( 'flx_gonullu', $r['data']['flxRoleDelete']['deletedKey'] ?? null, 'delete: custom role deleted' );
flxt_same( array( get_option( 'default_role', 'subscriber' ) ), array_values( get_userdata( $sub2 )->roles ), 'delete: its people move to the default role' );

// Viewer (kit 0.2 + Flux Roles fields).
$r = flxt_gql( '{ flxViewer { roleKey roleLabel referralLink capabilities } }', array(), $sub );
$v = $r['data']['flxViewer'] ?? array();
flxt_same( array( 'flx_personel', 'Staff' ), array( $v['roleKey'] ?? null, $v['roleLabel'] ?? null ), 'viewer: own FluxWP role' );
flxt_ok( str_contains( (string) ( $v['referralLink'] ?? '' ), '?ref=' . flxr_ref_code( $sub ) ), 'viewer: own referral link' );
flxt_ok( in_array( 'flxg_use', $v['capabilities'] ?? array(), true ) && ! in_array( 'flxg_manage', $v['capabilities'] ?? array(), true ), 'viewer: catalog capabilities from Flux Roles (flxg_use yes, flxg_manage no)' );
$r = flxt_gql( '{ flxViewer { roleKey referralLink } }' );
flxt_same( array( 'roleKey' => null, 'referralLink' => null ), $r['data']['flxViewer'] ?? null, 'viewer: visitor has no role' );

$r = flxr_t_op( 'FlxRoleMemberRemove', array( 'input' => array( 'role' => 'flx_personel', 'userId' => $sub ) ), $admin );
flxt_same( array( $sub, 0 ), array( $r['data']['flxRoleMemberRemove']['removedUserId'] ?? null, flxr_t_role( $r['data']['flxRoleMemberRemove']['roles'] ?? array(), 'flx_personel' )['memberCount'] ?? null ), 'member remove: removed, count 0' );
flxt_same( array( get_option( 'default_role', 'subscriber' ) ), array_values( get_userdata( $sub )->roles ), 'member remove: default role' );
$r = flxr_t_op( 'FlxRoleMemberRemove', array( 'input' => array( 'role' => 'flx_personel', 'userId' => 999999 ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'member remove: unknown user → NOT_FOUND' );

// Persisted: the screen's documents are allowed in strict mode.
wp_set_current_user( $admin );
$r = graphql( array( 'queryId' => hash( 'sha256', $GLOBALS['flxr_ops']['FlxRolesScreen'] ) ) );
flxt_ok( ! empty( $r['data']['flxRoles'] ), 'persisted queryId runs (strict mode)' );

// Clean up.
FLXR_Roles_Service::save( array(), 30 );
FLXR_Roles_Service::reset_role( 'flx_personel' );
$s         = flxr_settings();
$s['home'] = array();
update_option( 'flxr_settings', $s, false );
delete_user_meta( $sub, 'flx_phone' );
foreach ( array( $sub, $sub2, $boss ) as $u ) {
	wp_delete_user( $u );
}
flxt_done();
