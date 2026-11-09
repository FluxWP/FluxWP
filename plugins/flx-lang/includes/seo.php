<?php
/**
 * SEO: hreflang, og:locale:alternate, sitemap, dile göre site metinleri (site adı, slogan, Flux SEO ana sayfa başlığı).
 * Kanonik adres icerik.php'de (get_canonical_url): yedek gösterimde Türkçe adres.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bu sayfanın dillerdeki karşılıkları: dil => adres.
 *
 * @param bool $real true: yalnız gerçek çeviriler (hreflang); false: dil seçici için yedek adresler de.
 */
function flxl_alternates( $real = true ) {
	$out  = array();
	$def  = flxl_default_language();
	$post = is_singular() ? get_queried_object() : null;

	if ( is_404() ) {
		if ( ! $real ) {
			foreach ( flxl_languages() as $code => $l ) {
				$out[ $code ] = flxl_url_for_language( trailingslashit( flxl_root() ), $code );
			}
		}
		return $out;
	}

	if ( $post instanceof WP_Post && flxl_is_translatable_type( $post->post_type ) && ! flxl_is_shared( $post->ID ) && ! is_front_page() ) {
		$tr = flxl_get_translations( $post->ID );
		foreach ( flxl_languages() as $code => $l ) {
			$id = (int) ( $tr[ $code ] ?? 0 );
			if ( $id && ( 'publish' === get_post_status( $id ) || (int) $id === (int) $post->ID ) ) {
				$out[ $code ] = flxl_strict_links(
					function () use ( $id ) {
						return get_permalink( $id );
					}
				);
			} elseif ( ! $real ) {
				$src = (int) ( $tr[ $def ] ?? 0 );
				if ( $src && flxl_fallback() && 'publish' === get_post_status( $src ) ) {
					// Yedek gösterim adresi: /en/turkce-slug/.
					$out[ $code ] = flxl_url_for_language(
						flxl_strict_links(
							function () use ( $src ) {
								return get_permalink( $src );
							}
						),
						$code
					);
				} else {
					$out[ $code ] = flxl_url_for_language( trailingslashit( flxl_root() ), $code );
				}
			}
		}
		return $out;
	}

	// Ana sayfa, arşivler, arama, ortak sayfalar: aynı adres, farklı önek.
	$url = flxl_current_url();
	if ( $real ) {
		$url = remove_query_arg( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'ref' ), $url );
	}
	foreach ( flxl_languages() as $code => $l ) {
		if ( $real && is_front_page() && 'page' === get_option( 'show_on_front' ) ) {
			// Ana sayfa: o dilde ana sayfa çevirisi yoksa da dil ana sayfası vardır (arayüz çevrili).
			$out[ $code ] = flxl_url_for_language( trailingslashit( flxl_root() ), $code );
			continue;
		}
		$out[ $code ] = flxl_url_for_language( $url, $code );
	}
	if ( $real && ( is_search() || is_paged() || ! flxl_should_hreflang_archive() ) ) {
		return array();
	}
	return $out;
}

/**
 * Arşivlerde hreflang yalnız gerçekten içerik varsa (boş İngilizce arşiv bildirilmesin).
 */
function flxl_should_hreflang_archive() {
	$on = is_front_page() || is_home() || is_post_type_archive() || is_category() || is_tax() || is_tag()
		|| ( is_singular() && flxl_is_shared( get_queried_object_id() ) ); // Paylaşılan sayfa (flxl_shared_post_ids) her dilde aynı içerik.
	return (bool) apply_filters( 'flxl_hreflang_archive', $on );
}

add_action(
	'wp_head',
	function () {
		if ( is_paged() || ( is_singular() && (int) get_query_var( 'page' ) > 1 ) || flxl_is_fallback_view() ) {
			return;
		}
		$alts = flxl_alternates( true );
		if ( count( $alts ) < 2 ) {
			return;
		}
		$def = flxl_default_language();
		$tags = array();
		foreach ( $alts as $code => $url ) {
			$tags[ flxl_hreflang( $code ) ] = $url;
		}
		if ( isset( $alts[ $def ] ) ) {
			$tags['x-default'] = $alts[ $def ];
		}
		$tags = (array) apply_filters( 'flxl_hreflang_links', $tags );
		foreach ( $tags as $hl => $url ) {
			printf( '<link rel="alternate" hreflang="%s" href="%s">' . "\n", esc_attr( $hl ), esc_url( $url ) );
		}
		foreach ( array_keys( $alts ) as $code ) {
			if ( $code !== flxl_current_language() ) {
				$loc = flxl_language_locale( $code );
				$loc = false === strpos( $loc, '_' ) ? $loc . '_' . strtoupper( $loc ) : $loc;
				printf( '<meta property="og:locale:alternate" content="%s">' . "\n", esc_attr( $loc ) );
			}
		}
	},
	2
);

/**
 * Sitemap: tüm dillerdeki içerikler (her biri kendi dil adresiyle).
 */
add_filter(
	'wp_sitemaps_posts_query_args',
	function ( $args ) {
		$args['flxl_lang'] = 'all';
		return $args;
	},
	20
);
add_filter(
	'wp_sitemaps_posts_entry',
	function ( $entry, $post ) {
		if ( flxl_is_translatable_type( $post->post_type ) ) {
			$entry['loc'] = flxl_strict_links(
				function () use ( $post ) {
					return get_permalink( $post );
				}
			);
		}
		// Shared links normally follow the visitor language. Sitemap roots must not.
		if ( flxl_is_shared( $post->ID ) ) {
			$entry['loc'] = flxl_url_for_language( $entry['loc'], flxl_default_language() );
		}
		return $entry;
	},
	20,
	2
);
/* ---------------------------------------------------------------------------
 * Dile göre site metinleri
 * ------------------------------------------------------------------------- */

/**
 * Panelden dile göre girilebilen metinler: anahtar => [etiket, seçenek, alt anahtar].
 * Diğer paketler flxl_text_fields filtresiyle ekler (ör. tema alt bilgi metni).
 */
function flxl_text_fields() {
	$labels = array(
		'blogname'         => __( 'Site title', 'flx-lang' ),
		'blogdescription'  => __( 'Tagline', 'flx-lang' ),
		'flxseo_site_name'  => __( 'Flux SEO: site name', 'flx-lang' ),
		'flxseo_alt_name'   => __( 'Flux SEO: alternate name', 'flx-lang' ),
		'flxseo_home_title' => __( 'Flux SEO: home page title', 'flx-lang' ),
		'flxseo_home_desc'  => __( 'Flux SEO: home page description', 'flx-lang' ),
	);
	$out = array();
	foreach ( flxl_text_targets() as $k => $t ) {
		$out[ $k ] = $t + array( 'label' => $labels[ $k ] ?? ( $t['label'] ?? $k ) );
	}
	return $out;
}

/**
 * Metin anahtarı => [option, key?, textarea?, label?]. Diğer paketler flxl_text_fields filtresiyle ekler
 * (çeviri yüklenmeden de çağrılır; etiketi kendi __() ile verin).
 */
function flxl_text_targets() {
	$f = array(
		'blogname'        => array( 'option' => 'blogname' ),
		'blogdescription' => array( 'option' => 'blogdescription' ),
	);
	if ( defined( 'FLXSEO_VERSION' ) || function_exists( 'flxseo_opt' ) ) {
		$f['flxseo_site_name']  = array( 'option' => 'flxseo', 'key' => 'site_name' );
		$f['flxseo_alt_name']   = array( 'option' => 'flxseo', 'key' => 'alt_name' );
		$f['flxseo_home_title'] = array( 'option' => 'flxseo', 'key' => 'home_title' );
		$f['flxseo_home_desc']  = array( 'option' => 'flxseo', 'key' => 'home_desc', 'textarea' => true );
	}
	return (array) apply_filters( 'flxl_text_fields', $f );
}

/**
 * Dile göre metin (yoksa '').
 */
function flxl_text( $key, $code = '' ) {
	$code = $code ? $code : flxl_current_language();
	return (string) ( flxl_settings()['texts'][ $code ][ $key ] ?? '' );
}

/**
 * Dile göre metinleri option filtreleriyle uygular. Eklentilerin alanları plugins_loaded'da, temanınkiler
 * (ör. çerez banner'ı metinleri, tema_*) functions.php yüklendikten sonra eklenir; ikinci çağrı yalnız yeni
 * alanları kaydeder.
 */
function flxl_register_text_filters() {
	static $done = array();
	$cur = flxl_current_language();
	if ( $cur === flxl_default_language() ) {
		return;
	}
	$by_option = array();
	foreach ( flxl_text_targets() as $k => $f ) {
		if ( isset( $done[ $k ] ) ) {
			continue;
		}
		$done[ $k ] = true;
		$v          = flxl_text( $k, $cur );
		if ( '' !== $v && ! empty( $f['option'] ) ) {
			$by_option[ $f['option'] ][ $f['key'] ?? '' ] = $v;
		}
	}
	foreach ( $by_option as $opt => $vals ) {
		add_filter(
			'option_' . $opt,
			function ( $value ) use ( $vals ) {
				if ( flxl_is_backend() || $GLOBALS['flxl_state']['raw'] > 0 ) {
					return $value;
				}
				foreach ( $vals as $sub => $v ) {
					if ( '' === $sub ) {
						$value = $v;
					} elseif ( is_array( $value ) ) {
						$value[ $sub ] = $v;
					}
				}
				return $value;
			},
			20
		);
	}
}
add_action( 'plugins_loaded', 'flxl_register_text_filters', 0 );
add_action( 'after_setup_theme', 'flxl_register_text_filters', 99 );
