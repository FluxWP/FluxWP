<?php
/**
 * İstemcinin arayüz dili: REST isteğinde `X-Flux-Locale` başlığı (tr_TR, tr-TR, tr) yüklü bir dile denk geliyorsa
 * o istek bu dille işlenir. Uygulama telefonun dilinde açılır; sunucudan gelen menü/sekme adları da aynı dilde olur,
 * WordPress profil dili ya da site dili ne olursa olsun. Başlık yoksa ya da dil yüklü değilse WordPress'in kendi kuralı geçerli.
 */

defined( 'ABSPATH' ) || exit;

/** Başlıktaki dil (yüklü dillerden biri), yoksa ''. Sonuç istek boyunca saklanır. */
function flxapp_request_locale() {
	static $done = false, $out = '';
	if ( $done ) {
		return $out;
	}
	$done = true; // İç içe çağrıda (aşağıdaki işlevler dil sorarsa) tekrar hesaplanmaz.
	$raw  = isset( $_SERVER['HTTP_X_FLUX_LOCALE'] ) ? (string) wp_unslash( $_SERVER['HTTP_X_FLUX_LOCALE'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$raw  = substr( preg_replace( '/[^A-Za-z_\-]/', '', $raw ), 0, 20 );
	if ( '' === $raw || ! flxapp_is_rest_request() ) {
		return $out;
	}
	$parts = explode( '_', str_replace( '-', '_', $raw ) );
	$want  = strtolower( $parts[0] ) . ( isset( $parts[1] ) ? '_' . strtoupper( $parts[1] ) : '' );
	$base  = strtolower( $parts[0] );
	// Yüklü çekirdek dilleri + eklentinin kendi paketindeki diller (çekirdek çevirisi kurulmamış sitede de menü çevrilir).
	$have = array_merge( array( 'en_US' ), get_available_languages() );
	foreach ( (array) glob( FLXAPP_DIR . 'languages/flx-app-*.mo' ) as $mo ) {
		$have[] = preg_replace( '/^flx-app-/', '', basename( $mo, '.mo' ) );
	}
	$have = array_values( array_unique( $have ) );
	if ( in_array( $want, $have, true ) ) {
		$out = $want;
	} else {
		foreach ( $have as $l ) {
			if ( $l === $base || 0 === strpos( $l, $base . '_' ) ) {
				$out = $l;
				break;
			}
		}
	}
	return $out;
}

add_filter(
	'determine_locale',
	function ( $locale ) {
		$l = flxapp_request_locale();
		return '' !== $l ? $l : $locale;
	},
	99 // Flux Lang (20) ve çekirdek kararından sonra.
);
