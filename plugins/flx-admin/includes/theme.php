<?php
/**
 * Görünüm ayarları (Ayarlar › Yönetim teması › Görünüm): renkler (açık + koyu), yazı tipi, yazı boyutu,
 * köşe yuvarlaklığı, yoğunluk. Hepsi CSS değişkeni olarak <head>'e yazılır; admin.css bu değişkenleri kullanır.
 * Ayrıca hızlı geçiş: menü bağlantılarına önden yükleme (Speculation Rules) ve yumuşak sayfa geçişi.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_footer', 'flxa_fast_nav', 99 );

function flxa_theme_defaults() {
	return array(
		'radius'      => 'medium',  // none | small | medium | large
		'font'        => 'figtree', // figtree | system | custom
		'font_custom' => '',
		'font_size'   => 13,        // 13 | 14 | 15
		'density'     => 'comfortable', // comfortable | compact
		'light'       => array( 'bg' => '', 'side' => '', 'surface' => '', 'text' => '' ),
		'dark'        => array( 'bg' => '', 'side' => '', 'surface' => '', 'text' => '' ),
	);
}

/**
 * Renk alanlarının varsayılanları (ayar ekranında gösterilir; boş = varsayılan).
 *
 * @return array{light: array{bg: string, side: string, surface: string, text: string}, dark: array{bg: string, side: string, surface: string, text: string}}
 */
function flxa_theme_color_defaults() {
	return array(
		'light' => array( 'bg' => '#F3F2EF', 'side' => '#FFFFFF', 'surface' => '#FFFFFF', 'text' => '#16181D' ),
		'dark'  => array( 'bg' => '#101114', 'side' => '#14161A', 'surface' => '#191B20', 'text' => '#E8E8EA' ),
	);
}

/**
 * @return array<string, mixed>
 */
function flxa_theme() {
	$t = flxa_setting( 'theme' );
	$t = is_array( $t ) ? $t : array();
	$d = flxa_theme_defaults();
	$o = wp_parse_args( $t, $d );
	foreach ( array( 'light', 'dark' ) as $m ) {
		$o[ $m ] = wp_parse_args( is_array( $o[ $m ] ) ? $o[ $m ] : array(), $d[ $m ] );
	}
	return $o;
}

/**
 * @param array<string, mixed> $in Raw theme values.
 * @return array<string, mixed>
 */
function flxa_theme_sanitize( array $in ) {
	$d = flxa_theme_defaults();
	$o = array(
		'radius'      => in_array( $in['radius'] ?? '', array( 'none', 'small', 'medium', 'large' ), true ) ? $in['radius'] : $d['radius'],
		'font'        => in_array( $in['font'] ?? '', array( 'figtree', 'system', 'custom' ), true ) ? $in['font'] : $d['font'],
		'font_custom' => trim( preg_replace( '/[^A-Za-z0-9 ,"\'\-]/', '', (string) ( $in['font_custom'] ?? '' ) ) ),
		'font_size'   => in_array( (int) ( $in['font_size'] ?? 0 ), array( 13, 14, 15 ), true ) ? (int) $in['font_size'] : $d['font_size'],
		'density'     => in_array( $in['density'] ?? '', array( 'comfortable', 'compact' ), true ) ? $in['density'] : $d['density'],
	);
	foreach ( array( 'light', 'dark' ) as $m ) {
		$o[ $m ] = array();
		foreach ( array_keys( $d[ $m ] ) as $k ) {
			$c            = sanitize_hex_color( (string) ( $in[ $m ][ $k ] ?? '' ) );
			$def          = flxa_theme_color_defaults()[ $m ][ $k ];
			$o[ $m ][ $k ] = $c && strtolower( $c ) !== strtolower( $def ) ? $c : '';
		}
	}
	$o['font_custom'] = mb_substr( $o['font_custom'], 0, 120 );
	return $o;
}

/** <head>'deki değişkenler. */
function flxa_tokens_css() {
	$t     = flxa_theme();
	$radii = array(
		'none'   => array( 4, 4, 3 ),
		'small'  => array( 8, 6, 5 ),
		'medium' => array( 14, 10, 8 ),
		'large'  => array( 20, 14, 10 ),
	);
	$r     = $radii[ $t['radius'] ] ?? $radii['medium'];
	$font  = '';
	if ( 'system' === $t['font'] ) {
		$font = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
	} elseif ( 'custom' === $t['font'] && '' !== $t['font_custom'] ) {
		$font = $t['font_custom'] . ', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
	}
	$vars = array(
		'--flxa-radius'  => $r[0] . 'px',
		'--flxa-r'       => $r[1] . 'px',
		'--flxa-r-sm'    => $r[2] . 'px',
		'--flxa-fs'      => (int) $t['font_size'] . 'px',
		'--flxa-item-h'  => 'compact' === $t['density'] ? '32px' : '38px',
		'--flxa-item-py' => 'compact' === $t['density'] ? '5px' : '8px',
	);
	if ( '' !== $font ) {
		$vars['--flxa-font'] = $font;
	}
	$map = array(
		'bg'      => '--flxa-bg',
		'side'    => '--flxa-side',
		'surface' => '--flxa-surface',
		'text'    => '--flxa-text',
	);
	$css = 'html.flxa-html{';
	foreach ( $vars as $k => $v ) {
		$css .= $k . ':' . $v . ';';
	}
	foreach ( $map as $k => $var ) {
		if ( '' !== $t['light'][ $k ] ) {
			$css .= $var . ':' . $t['light'][ $k ] . ';';
		}
	}
	$css .= '}';
	$dark = '';
	foreach ( $map as $k => $var ) {
		if ( '' !== $t['dark'][ $k ] ) {
			$dark .= $var . ':' . $t['dark'][ $k ] . ';';
		}
	}
	if ( '' !== $dark ) {
		$css .= 'html.flxa-html.flxa-dark{' . $dark . '}';
	}
	return wp_strip_all_tags( $css );
}

/**
 * Hızlı geçiş: menüdeki bir bağlantıya basılınca (pointerdown) sayfa önden hazırlanır; Chrome/Edge'de
 * bırakınca anında açılır. "conservative" seviye: yalnız tıklanan bağlantı, Hostinger'a ek yük yok denecek kadar az.
 * İşlem bağlantıları (nonce, action, silme, çıkış) hariç.
 */
function flxa_fast_nav() {
	if ( ! flxa_shell_on() || ! flxa_setting( 'fast_nav' ) ) {
		return;
	}
	$admin = wp_parse_url( admin_url(), PHP_URL_PATH );
	$rules = array(
		'prerender' => array(
			array(
				'where'     => array(
					'and' => array(
						array( 'href_matches' => $admin . '*' ),
						array( 'not' => array( 'href_matches' => '*admin-ajax.php*' ) ),
						array( 'not' => array( 'href_matches' => '*admin-post.php*' ) ),
						array( 'not' => array( 'href_matches' => '*_wpnonce=*' ) ),
						array( 'not' => array( 'href_matches' => '*action=*' ) ),
						array( 'not' => array( 'href_matches' => '*logout*' ) ),
						array( 'not' => array( 'href_matches' => '*download*' ) ),
						array( 'not' => array( 'selector_matches' => '[target], [download], .submitdelete, .delete a, .trash a' ) ),
					),
				),
				'eagerness' => 'conservative',
			),
		),
	);
	echo '<script type="speculationrules">' . wp_json_encode( $rules ) . '</script>';
}
