<?php
/**
 * Ayar şeması: eklentiler ve tema, ön ayarla yazılabilecek ayarlarını tek yerde tanımlar.
 * AI (flxk_preset_schema aracı) ve ön ayar yazarı hangi alanların olduğunu, türünü ve varsayılanını buradan öğrenir;
 * motor bilinmeyen alanlar için uyarı verir.
 *
 *   add_filter( 'flx_settings_schema', function ( $s ) {
 *       $s['flx_ayarlar'] = array(
 *           'label'  => 'FluxWP tema ayarları',
 *           'fields' => array(
 *               'cookie_enabled' => array( 'type' => 'bool', 'default' => 1, 'label' => 'Çerez banner'ı' ),
 *               'cookie_position' => array( 'type' => 'enum', 'enum' => array( 'card', 'bar' ) ),
 *           ),
 *       );
 *       return $s;
 *   } );
 *
 * Türler: bool, int, string, text, html, enum, color, url, page (sayfa ID'si; ön ayarda "@page:<key>"), object, list.
 * `object` / `list` alanının alt yolları serbesttir. Şemada olmayan option'lar da yazılabilir (uyarısız).
 */

defined( 'ABSPATH' ) || exit;

/** @return array<string, array{label?:string, fields:array}> */
function flxk_settings_schema() {
	$s = apply_filters( 'flx_settings_schema', array() );
	return is_array( $s ) ? $s : array();
}

/** Şemaya göre yol bilinmiyorsa uyarı metni, biliniyorsa ''. */
function flxk_settings_path_warning( $opt, $path ) {
	$schema = flxk_settings_schema();
	if ( empty( $schema[ $opt ]['fields'] ) ) {
		return '';
	}
	$fields = (array) $schema[ $opt ]['fields'];
	if ( isset( $fields[ $path ] ) ) {
		return '';
	}
	foreach ( $fields as $key => $def ) {
		if ( in_array( $def['type'] ?? '', array( 'object', 'list' ), true ) && 0 === strpos( $path, $key . '.' ) ) {
			return '';
		}
	}
	/* translators: 1: option, 2: alan yolu */
	return sprintf( __( '%1$s: "%2$s" is not in the settings schema; it is written but the plugin may not use it.', 'flx-setup' ), $opt, $path );
}
