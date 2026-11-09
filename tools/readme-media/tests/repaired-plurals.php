<?php
/** php repaired-plurals.php [repository-root] [fixture.json] */
$root = $argv[1] ?? dirname( __DIR__, 3 );
$labels = json_decode( file_get_contents( $argv[2] ?? __DIR__ . '/repaired-plurals.json' ), true, 32, JSON_THROW_ON_ERROR );
$count = 0;
foreach ( $labels as $label ) {
	$slug = $label['package']; $key = implode( chr(0), $label['forms'] );
	foreach ( glob( $root . '/plugins/' . $slug . '/languages/' . $slug . '-*.l10n.php' ) as $file ) {
		$pack = require $file;
		if ( ! isset( $pack['messages'][ $key ] ) ) { throw new RuntimeException( 'Missing repaired label: ' . basename( $file ) ); }
		foreach ( explode( chr(0), $pack['messages'][ $key ] ) as $form ) {
			if ( preg_match( '/^\[\[|\\\\n|\\\\x00/', $form ) ) { throw new RuntimeException( 'Malformed translation transport data in count label' ); }
			vsprintf( $form, array_fill( 0, 32, '3' ) ); $count++;
		}
	}
}
echo "$count localized plural forms formatted successfully\n";
