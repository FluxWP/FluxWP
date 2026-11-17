<?php
/**
 * App manifests on GraphQL: every module the mobile app gets resolves its operations (queryId + kind),
 * and every GraphQL source that needs no screen parameter runs without errors as an administrator
 * and returns the `data` path the screen reads.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-app-manifests/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "App manifests (GraphQL)\n";

if ( ! function_exists( 'flxapp_user_manifest' ) || ! function_exists( 'flx_gql_persisted' ) ) {
	echo "Flux App or Flux GraphQL is not active, skipped\n";
	return;
}

/** Path inside a decoded JSON value ("a.b.0"). */
function flxam_t_path( $v, string $path ) {
	foreach ( ( '' === $path ? array() : explode( '.', $path ) ) as $k ) {
		if ( ! is_array( $v ) || ! array_key_exists( $k, $v ) ) {
			return null;
		}
		$v = $v[ $k ];
	}
	return $v;
}

/** Template variables render empty without a screen context, so they are left out (as the app does). */
function flxam_t_vars( array $vars ): array {
	$out = array();
	foreach ( $vars as $k => $v ) {
		if ( is_string( $v ) && false !== strpos( $v, '{{' ) ) {
			continue;
		}
		$out[ $k ] = is_array( $v ) ? flxam_t_vars( $v ) : $v;
	}
	return $out;
}

$admin   = 1;
$queries = flx_gql_persisted()['queries'];
wp_set_current_user( $admin );
$mods = json_decode( (string) wp_json_encode( flxapp_user_manifest( get_user_by( 'id', $admin ), 'mobile' ) ), true );
$mods = is_array( $mods ) ? $mods : array();

$expected = array(
	'wiki'      => 'flx-wiki/flx-wiki.php',
	'recaptcha' => 'flx-recaptcha/flx-recaptcha.php',
	'seo'       => 'flx-seo/flx-seo.php',
	'backup'    => 'flx-backup/flx-backup.php',
	'lang'      => 'flx-lang/flx-lang.php',
);
foreach ( $expected as $id => $plugin ) {
	if ( is_plugin_active( $plugin ) ) {
		flxt_ok( isset( $mods[ $id ] ), "module $id is sent to the mobile app" );
	}
}

$desktop = json_decode( (string) wp_json_encode( flxapp_user_manifest( get_user_by( 'id', $admin ), 'desktop' ) ), true );
foreach ( array( 'recaptcha', 'seo', 'backup', 'lang' ) as $id ) {
	if ( isset( $mods[ $id ] ) ) {
		flxt_ok( ! isset( $desktop[ $id ] ), "module $id stays on its React screen on desktop" );
	}
}

foreach ( $mods as $mid => $m ) {
	$refs = array();
	foreach ( (array) ( $m['sources'] ?? array() ) as $sid => $src ) {
		if ( isset( $src['graphql'] ) ) {
			$refs[ "sources.$sid" ] = array( $src['graphql'], true );
		}
	}
	foreach ( (array) ( $m['actions'] ?? array() ) as $aid => $a ) {
		if ( isset( $a['request']['graphql'] ) ) {
			$refs[ "actions.$aid" ] = array( $a['request']['graphql'], false );
		}
	}
	foreach ( (array) ( $m['screens'] ?? array() ) as $sid => $s ) {
		foreach ( array( 'graphql', 'graphql_create', 'graphql_update' ) as $k ) {
			if ( isset( $s['submit'][ $k ] ) ) {
				$refs[ "screens.$sid.submit.$k" ] = array( $s['submit'][ $k ], false );
			}
		}
	}
	foreach ( $refs as $where => list( $ref, $run ) ) {
		$id = (string) ( $ref['id'] ?? '' );
		flxt_ok( isset( $queries[ $id ] ), "$mid.$where: {$ref['op']} has a persisted queryId" );
		flxt_ok( in_array( $ref['kind'] ?? '', array( 'query', 'mutation' ), true ), "$mid.$where: kind is set" );
		if ( ! $run || ! isset( $queries[ $id ] ) || 'query' !== ( $ref['kind'] ?? '' ) || false !== strpos( (string) wp_json_encode( $ref['variables'] ?? array() ), 'params.' ) ) {
			continue;
		}
		$r = flxt_gql( $queries[ $id ], flxam_t_vars( (array) ( $ref['variables'] ?? array() ) ), $admin );
		flxt_ok( empty( $r['errors'] ), "$mid.$where: {$ref['op']} runs", $r['errors'] ?? null );
		if ( ! empty( $ref['data'] ) && empty( $r['errors'] ) ) {
			flxt_ok( null !== flxam_t_path( $r['data'] ?? null, (string) $ref['data'] ), "$mid.$where: data path {$ref['data']} exists" );
		}
	}
}

flxt_done();
