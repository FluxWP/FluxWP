<?php
/**
 * Çok dil (i18n) desteği.
 *
 * - Tema metinleri "fluxwp" text domain'i ile çevrilir (languages/*.mo, *.l10n.php).
 * - Template, template part ve pattern'lerdeki sabit metinler render sırasında çevrilir:
 *   blok yapısı ve editörden düzenlenebilirlik aynı kalır, Türkçe dışındaki dilde
 *   eşleşen metin varsa çevirisi gösterilir. Değiştirilmiş metinler olduğu gibi kalır
 *   (çok dil eklentisi bunları kendi ekranından çevirir).
 * - TranslatePress, Polylang ve WPML ile uyumlu dil seçici bloğu: "Dil Seçici".
 *
 * @package fluxwp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function flx_load_textdomain() {
	load_theme_textdomain( 'fluxwp', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'flx_load_textdomain', 1 );

/**
 * Sitenin şu anki dili Türkçe değilse true.
 */
function flx_i18n_active() {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}
	return 0 !== strpos( determine_locale(), 'tr' );
}

/**
 * Tek metni çevirir; baştaki/sondaki boşluk korunur.
 */
function flx_t( $text ) {
	$trim = trim( $text );
	if ( '' === $trim ) {
		return $text;
	}
	static $domains = null;
	if ( null === $domains ) {
		// Ön ayar eklentileri kendi desen metinlerinin text domain'ini ekler.
		$domains = array_unique( array_merge( array( 'fluxwp' ), (array) apply_filters( 'flx_i18n_domains', array() ) ) );
	}
	foreach ( $domains as $domain ) {
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
		$tr = translate( $trim, $domain );
		if ( $tr !== $trim ) {
			return str_replace( $trim, $tr, $text );
		}
	}
	return $text;
}

/**
 * Dinamik blokların metin attribute'ları (menü etiketi, arama butonu, sayfalama vb.).
 */
function flx_i18n_block_data( $block ) {
	if ( empty( $block['attrs'] ) || ! flx_i18n_active() ) {
		return $block;
	}
	$keys = array( 'label', 'buttonText', 'placeholder', 'prefix', 'suffix', 'moreText' );
	if ( 'core/read-more' === $block['blockName'] ) {
		$keys[] = 'content';
	}
	foreach ( $keys as $k ) {
		if ( isset( $block['attrs'][ $k ] ) && is_string( $block['attrs'][ $k ] ) ) {
			$block['attrs'][ $k ] = flx_t( $block['attrs'][ $k ] );
		}
	}
	// Polylang: menüdeki "/hakkimizda/" gibi göreli linkler o dildeki sayfaya gider.
	if ( in_array( $block['blockName'], array( 'core/navigation-link', 'core/navigation-submenu' ), true ) && ! empty( $block['attrs']['url'] ) ) {
		$block['attrs']['url'] = flx_i18n_url( $block['attrs']['url'] );
	}
	return $block;
}
add_filter( 'render_block_data', 'flx_i18n_block_data' );

/**
 * Göreli sayfa linkini aktif dildeki karşılığına çevirir (Polylang / WPML).
 * TranslatePress linkleri zaten kendisi çevirir.
 */
function flx_i18n_url( $url ) {
	if ( ! is_string( $url ) || '/' !== substr( $url, 0, 1 ) || '//' === substr( $url, 0, 2 ) ) {
		return $url;
	}
	$path = trim( wp_parse_url( $url, PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		if ( function_exists( 'pll_home_url' ) ) {
			return pll_home_url();
		}
		return $url;
	}
	if ( ! function_exists( 'pll_get_post' ) && ! has_filter( 'wpml_object_id' ) ) {
		return $url;
	}
	$page = get_page_by_path( $path );
	if ( ! $page ) {
		return $url;
	}
	$id = function_exists( 'pll_get_post' ) ? pll_get_post( $page->ID ) : apply_filters( 'wpml_object_id', $page->ID, 'page', true );
	return $id ? get_permalink( $id ) : $url;
}

/**
 * Statik bloklarda metin düğümleri ve görsel alt metinleri.
 */
function flx_i18n_render_block( $html, $block ) {
	static $leaf = array( 'core/paragraph', 'core/heading', 'core/button', 'core/list-item', 'core/image', 'core/table', 'core/details', 'core/pullquote', 'core/verse', 'core/quote', 'core/post-title' );
	if ( '' === $html || ! in_array( $block['blockName'], $leaf, true ) || ! flx_i18n_active() ) {
		return $html;
	}
	$html = preg_replace_callback(
		'/>([^<>]*\S[^<>]*)</u',
		function ( $m ) {
			return '>' . flx_t( $m[1] ) . '<';
		},
		$html
	);
	if ( 'core/image' === $block['blockName'] ) {
		$html = preg_replace_callback(
			'/\balt="([^"]+)"/u',
			function ( $m ) {
				return 'alt="' . esc_attr( flx_t( $m[1] ) ) . '"';
			},
			$html
		);
	}
	if ( 'core/button' === $block['blockName'] && ( function_exists( 'pll_get_post' ) || has_filter( 'wpml_object_id' ) ) ) {
		$html = preg_replace_callback(
			'/\bhref="(\/[^"]*)"/',
			function ( $m ) {
				return 'href="' . esc_url( flx_i18n_url( $m[1] ) ) . '"';
			},
			$html
		);
	}
	return $html;
}
add_filter( 'render_block', 'flx_i18n_render_block', 5, 2 );

/**
 * main.js metinleri.
 */
function flx_i18n_script_strings() {
	$s = array(
		'copy'        => __( 'Copy', 'fluxwp' ),
		'prev'        => __( 'Previous', 'fluxwp' ),
		'next'        => __( 'Next', 'fluxwp' ),
		'more'        => __( 'Show more', 'fluxwp' ),
		'loading'     => __( 'Loading…', 'fluxwp' ),
		'copied'      => __( 'Copied ✓', 'fluxwp' ),
		'searchSite'  => __( 'Search the site', 'fluxwp' ),
		'close'       => __( 'Close', 'fluxwp' ),
		'searchTitle' => __( 'What are you looking for?', 'fluxwp' ),
		'search'      => __( 'Search', 'fluxwp' ),
		'searchPh'    => (string) apply_filters( 'flx_search_placeholder', __( 'Search products, posts, pages…', 'fluxwp' ) ),
		'popular'     => __( 'Popular:', 'fluxwp' ),
		'chips'       => array_values( (array) apply_filters( 'flx_search_chips', array() ) ),
	);
	wp_add_inline_script( 'fluxwp', 'window.flxI18n=' . wp_json_encode( $s ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'flx_i18n_script_strings', 20 );

/**
 * CSS içinde gösterilen metinler (content: var(--flx-t-...)).
 */
function flx_i18n_style_strings() {
	$t = array(
		'read' => __( 'Read more', 'fluxwp' ),
	);
	$css = ':root{';
	foreach ( $t as $k => $v ) {
		$css .= '--flx-t-' . $k . ':"' . addcslashes( wp_strip_all_tags( $v ), '"\\' ) . '";';
	}
	wp_add_inline_style( 'fluxwp', $css . '}' );
}
add_action( 'wp_enqueue_scripts', 'flx_i18n_style_strings', 20 );

/**
 * <html lang> eklentiler tarafından ayarlanır; burada yalnızca dil listesi toplanır.
 *
 * @return array[] code, name, url, current
 */
function flx_languages() {
	$list = array();
	if ( function_exists( 'trp_custom_language_switcher' ) ) {
		global $TRP_LANGUAGE; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		foreach ( (array) trp_custom_language_switcher() as $code => $l ) {
			$list[] = array(
				'code'    => $l['short_language_name'] ?? substr( $code, 0, 2 ),
				'name'    => $l['language_name'] ?? $code,
				'url'     => $l['current_page_url'] ?? '',
				'current' => $code === $TRP_LANGUAGE, // phpcs:ignore WordPress.NamingConventions.ValidVariableName
			);
		}
	} elseif ( function_exists( 'pll_the_languages' ) ) {
		foreach ( (array) pll_the_languages( array( 'raw' => 1, 'hide_if_empty' => 0 ) ) as $l ) {
			$list[] = array(
				'code'    => $l['slug'],
				'name'    => $l['name'],
				'url'     => $l['url'],
				'current' => ! empty( $l['current_lang'] ),
			);
		}
	} elseif ( has_filter( 'wpml_active_languages' ) ) {
		foreach ( (array) apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ) as $l ) {
			$list[] = array(
				'code'    => $l['code'],
				'name'    => $l['native_name'],
				'url'     => $l['url'],
				'current' => ! empty( $l['active'] ),
			);
		}
	}
	/** Flux Lang gibi eklentiler listeyi buradan da besleyebilir. */
	return (array) apply_filters( 'flx_languages', $list );
}

/**
 * Mobil menü panelinde dil seçici (Flux Lang varsa onun seçicisi). Sosyal ikonların hemen üstüne gelir.
 */
add_filter(
	'render_block_core/social-links',
	function ( $html, $block ) {
		if ( false === strpos( ' ' . ( $block['attrs']['className'] ?? '' ) . ' ', ' flx-mnav-social ' ) ) {
			return $html;
		}
		$sw = function_exists( 'flxl_switcher' ) ? (string) flxl_switcher() : flx_language_switcher();
		return '' === trim( $sw ) ? $html : '<div class="flx-mnav-lang">' . $sw . '</div>' . $html;
	},
	10,
	2
);

/**
 * Tema Ayarları'ndaki ziyaretçiye görünen metinler Flux Lang'de dile göre girilebilir.
 */
add_filter(
	'flxl_text_fields',
	function ( $f ) {
		$fields = array(
			'cookie_title'  => array( __( 'Cookie banner title', 'fluxwp' ), false ),
			'cookie_text'   => array( __( 'Cookie banner text', 'fluxwp' ), true ),
			'cookie_accept' => array( __( 'Cookie banner: accept button', 'fluxwp' ), false ),
			'cookie_reject' => array( __( 'Cookie banner: necessary only button', 'fluxwp' ), false ),
			'cookie_more'   => array( __( 'Cookie banner: policy link', 'fluxwp' ), false ),
		);
		foreach ( $fields as $key => $d ) {
			$f[ 'tema_' . $key ] = array(
				'option'   => 'flx_ayarlar',
				'key'      => $key,
				'textarea' => $d[1],
				'label'    => $d[0],
			);
		}
		return $f;
	}
);

/**
 * Dil seçici HTML'i. Tek dil varsa boş döner.
 */
function flx_language_switcher() {
	$langs = flx_languages();
	if ( count( $langs ) < 2 ) {
		return '';
	}
	$out = '<nav class="flx-lang" aria-label="' . esc_attr__( 'Language selection', 'fluxwp' ) . '" data-no-translation>';
	foreach ( $langs as $l ) {
		$out .= sprintf(
			'<a href="%1$s" hreflang="%2$s" lang="%2$s" title="%3$s"%4$s>%5$s</a>',
			esc_url( $l['url'] ),
			esc_attr( $l['code'] ),
			esc_attr( $l['name'] ),
			$l['current'] ? ' class="is-current" aria-current="true"' : '',
			esc_html( strtoupper( substr( $l['code'], 0, 2 ) ) )
		);
	}
	return $out . '</nav>';
}

/**
 * Tema blokları: Dil Seçici ve Hesap & Sepet. Editörde sürükle-bırak ile taşınabilir.
 */
function flx_register_theme_blocks() {
	wp_register_script( 'fluxwp-bloklar', get_theme_file_uri( 'assets/js/bloklar.js' ), array( 'wp-blocks', 'wp-element', 'wp-i18n' ), FLX_VERSION, true );
	wp_set_script_translations( 'fluxwp-bloklar', 'fluxwp', get_template_directory() . '/languages' );

	register_block_type(
		'fluxwp/dil-secici',
		array(
			'api_version'     => 3,
			'title'           => __( 'Language Switcher', 'fluxwp' ),
			'category'        => 'theme',
			'editor_script'   => 'fluxwp-bloklar',
			'supports'        => array( 'html' => false ),
			'render_callback' => 'flx_language_switcher',
		)
	);
	register_block_type(
		'fluxwp/hesap-sepet',
		array(
			'api_version'     => 3,
			'title'           => __( 'Account and Cart', 'fluxwp' ),
			'category'        => 'theme',
			'editor_script'   => 'fluxwp-bloklar',
			'supports'        => array( 'html' => false ),
			'render_callback' => 'flx_account_cart_block',
		)
	);
}
add_action( 'init', 'flx_register_theme_blocks' );
