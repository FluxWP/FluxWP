<?php
/**
 * Dil seçici: blok (Flux Lang Seçici), kısa kod [flx_dil_secici] ve temanın kendi "Dil Seçici" bloğu.
 * Tema bloğu Polylang/WPML bulamayınca boş döner; o durumda bu eklentinin seçicisi basılır (aynı .flx-lang işaretlemesi).
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dil seçici satırları: code, name, flag, url, current, translated.
 */
function flxl_switcher_items() {
	$alts  = flxl_alternates( false );
	$real  = flxl_alternates( true );
	$cur   = flxl_current_language();
	$items = array();
	foreach ( flxl_languages() as $code => $l ) {
		$items[] = array(
			'code'       => $code,
			'name'       => $l['name'],
			'flag'       => $l['flag'],
			'url'        => $alts[ $code ] ?? flxl_url_for_language( trailingslashit( flxl_root() ), $code ),
			'current'    => $code === $cur,
			'translated' => isset( $real[ $code ] ),
		);
	}
	return (array) apply_filters( 'flxl_switcher_items', $items );
}

/**
 * Dil seçici HTML'i. Tek dil varsa boş.
 *
 * @param array $args style: code|name|flag.
 */
function flxl_switcher( $args = array() ) {
	$items = flxl_switcher_items();
	if ( count( $items ) < 2 ) {
		return '';
	}
	$style = $args['style'] ?? flxl_settings()['switcher'];
	$compact = 'dropdown' === ( $args['layout'] ?? '' ) || ( count( $items ) > 4 && 'inline' !== ( $args['layout'] ?? '' ) );
	$out   = '<nav class="flx-lang flxl-switcher" aria-label="' . esc_attr__( 'Language selection', 'flx-lang' ) . '" data-no-translation>';
	if ( $compact ) {
		$current = $items[0];
		foreach ( $items as $item ) { if ( $item['current'] ) { $current = $item; break; } }
		$out .= '<details class="flxl-picker"><summary aria-label="' . esc_attr__( 'Language selection', 'flx-lang' ) . '"><span dir="auto">' . esc_html( strtoupper( $current['code'] ) ) . '</span><span aria-hidden="true">⌄</span></summary><ul class="flxl-picker__list">';
	}
	foreach ( $items as $i ) {
		if ( $compact ) { $out .= '<li>'; }
		switch ( $style ) {
			case 'name':
				$label = esc_html( $i['name'] );
				break;
			case 'flag':
				$label = '<span aria-hidden="true">' . esc_html( $i['flag'] ) . '</span><span class="screen-reader-text">' . esc_html( $i['name'] ) . '</span>';
				break;
			default:
				$label = esc_html( strtoupper( $i['code'] ) );
		}
		$out .= sprintf(
			'<a href="%1$s" hreflang="%2$s" lang="%2$s" dir="auto" title="%3$s" data-flxl="%4$s"%5$s>%6$s</a>',
			esc_url( $i['url'] ),
			esc_attr( flxl_hreflang( $i['code'] ) ),
			esc_attr( $i['name'] ),
			esc_attr( $i['code'] ),
			$i['current'] ? ' class="is-current" aria-current="true"' : '',
			$compact ? esc_html( $i['name'] ) . ' <small>' . esc_html( strtoupper( $i['code'] ) ) . '</small>' : $label
		);
		if ( $compact ) { $out .= '</li>'; }
	}
	if ( $compact ) { $out .= '</ul></details>'; }
	return $out . '</nav>';
}

add_shortcode(
	'flx_dil_secici',
	function ( $atts ) {
		return flxl_switcher( shortcode_atts( array( 'style' => flxl_settings()['switcher'] ), $atts ) );
	}
);

add_action(
	'init',
	function () {
		wp_register_script( 'flxl-blok', FLXL_URL . 'assets/js/blok.js', array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor' ), FLXL_VERSION, true );
		wp_set_script_translations( 'flxl-blok', 'flx-lang', FLXL_DIR . 'languages' );
		register_block_type(
			'flx-lang/secici',
			array(
				'api_version'     => 3,
				'title'           => __( 'Flux Language Switcher', 'flx-lang' ),
				'category'        => 'theme',
				'editor_script'   => 'flxl-blok',
				'attributes'      => array( 'style' => array( 'type' => 'string', 'default' => '' ) ),
				'supports'        => array( 'html' => false ),
				'render_callback' => function ( $atts ) {
					return flxl_switcher( array_filter( array( 'style' => $atts['style'] ?? '' ) ) );
				},
			)
		);
	}
);

/**
 * Temanın Dil Seçici bloğu boşsa bizim seçici.
 */
add_filter(
	'render_block',
	function ( $html, $block ) {
		if ( 'fluxwp/dil-secici' === ( $block['blockName'] ?? '' ) && '' === trim( (string) $html ) ) {
			return flxl_switcher();
		}
		return $html;
	},
	10,
	2
);

/**
 * Temanın başlığındaki/alt bilgisindeki seçici yeri (ayar: switcher_place).
 */
function flxl_switcher_place() {
	return (string) apply_filters( 'flxl_switcher_place', flxl_settings()['switcher_place'] ?? 'footer' );
}

/**
 * Yalnız alt bilgi seçiliyse başlıktaki Dil Seçici bloğu ve mobil menüdeki seçici gizlenir.
 */
add_filter(
	'render_block',
	function ( $html, $block ) {
		if ( 'footer' !== flxl_switcher_place() || is_admin() ) {
			return $html;
		}
		$name = $block['blockName'] ?? '';
		if ( 'fluxwp/dil-secici' === $name ) {
			return '';
		}
		if ( 'core/social-links' === $name && false !== strpos( $html, 'flx-mnav-lang' ) ) {
			return (string) preg_replace( '#<div class="flx-mnav-lang">.*?</nav></div>#s', '', $html );
		}
		return $html;
	},
	20,
	2
);

/**
 * Alt bilgi şablon parçasının sonuna seçici (dış grubun içine, geniş hizada).
 */
add_filter(
	'render_block_core/template-part',
	function ( $html, $block ) {
		if ( 'header' === flxl_switcher_place() || is_admin() ) {
			return $html;
		}
		$attrs = $block['attrs'] ?? array();
		if ( 'footer' !== ( $attrs['slug'] ?? '' ) && 'footer' !== ( $attrs['area'] ?? '' ) ) {
			return $html;
		}
		$sw = flxl_switcher();
		if ( '' === $sw || false !== strpos( $html, 'flxl-footer-lang' ) ) {
			return $html;
		}
		$end = strrpos( $html, '</footer>' );
		if ( false === $end ) {
			return $html . '<div class="flxl-footer-lang">' . $sw . '</div>';
		}
		// Dış grubun kapanışı ve ondan önceki son satır (tema: telif + çerez tercihleri satırı).
		$outer = strrpos( substr( $html, 0, $end ), '</div>' );
		$row   = false === $outer ? false : strrpos( substr( $html, 0, $outer ), '</div>' );
		if ( false !== $row && false === strpos( substr( $html, $row, $outer - $row ), '<' . 'div', 6 ) ) {
			// Son satırın sonuna: masaüstünde sağ alt köşe.
			$add = '<div class="flxl-footer-lang flxl-footer-lang--row">' . $sw . '</div>';
			return substr( $html, 0, $row ) . $add . substr( $html, $row );
		}
		$at = false === $outer ? $end : $outer;
		return substr( $html, 0, $at ) . '<div class="flxl-footer-lang alignwide">' . $sw . '</div>' . substr( $html, $at );
	},
	10,
	2
);

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( count( flxl_languages() ) > 1 ) {
			wp_enqueue_style( 'flxl-secici', FLXL_URL . 'assets/css/secici.css', array(), FLXL_VERSION );
			if ( count( flxl_languages() ) > 1 ) { wp_enqueue_script( 'flxl-picker', FLXL_URL . 'assets/js/secici.js', array(), FLXL_VERSION, true ); }
		}
	}
);
