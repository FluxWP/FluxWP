<?php
/**
 * Dumps the screen strings (flxfm_js_strings) of one locale for the React screen dictionaries.
 *   LOC=tr_TR OUT=/tmp/tr.json wp eval-file plugins/flx-files/tools/dump-strings.php
 * Used by tools/gen-react-i18n.sh.
 */
if ( ! function_exists( 'flxfm_js_strings' ) ) {
	require_once FLXFM_DIR . 'includes/admin.php';
}
$loc = (string) getenv( 'LOC' );
unload_textdomain( 'flx-files' );
if ( '' !== $loc && 'en_US' !== $loc ) {
	load_textdomain( 'flx-files', FLXFM_DIR . 'languages/flx-files-' . $loc . '.mo' );
}
file_put_contents( (string) getenv( 'OUT' ), wp_json_encode( flxfm_js_strings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
