<?php
/**
 * Tiny assertion helpers for `wp eval-file` tests (no PHPUnit on the CI image).
 */

$GLOBALS['flxt_fail'] = 0;
$GLOBALS['flxt_pass'] = 0;

function flxt_ok( bool $cond, string $name, $detail = null ): void {
	if ( $cond ) {
		$GLOBALS['flxt_pass']++;
		echo "  ok   $name\n";
		return;
	}
	$GLOBALS['flxt_fail']++;
	echo "  FAIL $name" . ( null === $detail ? '' : ': ' . ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) ) . "\n";
}

function flxt_same( $want, $got, string $name ): void {
	flxt_ok( $want === $got, $name, array( 'want' => $want, 'got' => $got ) );
}

/** Runs a document as a user (0 = visitor); returns the result without debug extensions. */
function flxt_gql( string $query, array $vars = array(), int $user = 0 ): array {
	wp_set_current_user( $user );
	$r = graphql( array( 'query' => $query, 'variables' => $vars ) );
	unset( $r['extensions'] );
	return $r;
}

function flxt_code( array $r ): ?string {
	return $r['errors'][0]['extensions']['code'] ?? null;
}

function flxt_done(): void {
	printf( "%d passed, %d failed\n", $GLOBALS['flxt_pass'], $GLOBALS['flxt_fail'] );
	if ( $GLOBALS['flxt_fail'] > 0 ) {
		exit( 1 );
	}
}
