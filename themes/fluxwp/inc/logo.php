<?php
/**
 * Logo: Site Logosu bloğu boşsa kurum profilindeki logoyu, o da yoksa site adını gösterir.
 * Footer'daki "flx-logo--beyaz" sınıflı logo koyu zemin için açık renk sürümdür (profil: logo_light).
 * Kendi logonuzu Düzenleyici › Header › Site Logosu'ndan yükleyebilirsiniz.
 *
 * @package fluxwp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logo dosyaları: kurum profili (Flux Setup › Kurum profili: logo, logo_light, icon) ya da filtre.
 * Hiçbiri yoksa site adı yazı olarak gösterilir.
 *
 * @return array{light:string,dark:string,icon:string,alt:string}
 */
function flx_logo_files() {
	static $files = null;
	if ( null !== $files ) {
		return $files;
	}
	$url = function ( $v ) {
		if ( is_numeric( $v ) && (int) $v > 0 ) {
			return (string) wp_get_attachment_url( (int) $v );
		}
		return is_string( $v ) ? $v : '';
	};
	$p     = function_exists( 'flx_profile' ) ? 'flx_profile' : null;
	$files = array(
		'light' => $p ? $url( $p( 'logo', '' ) ) : '',
		'dark'  => $p ? $url( $p( 'logo_light', '' ) ) : '',
		'icon'  => $p ? $url( $p( 'icon', '' ) ) : '',
		'alt'   => $p ? (string) $p( 'name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' ),
	);
	$files = (array) apply_filters( 'flx_logo_files', $files ) + array( 'light' => '', 'dark' => '', 'icon' => '', 'alt' => get_bloginfo( 'name' ) );
	if ( '' === $files['dark'] ) {
		$files['dark'] = $files['light'];
	}
	return $files;
}

function flx_logo_html( $dark = false, $class = '' ) {
	$f   = flx_logo_files();
	$src = $dark ? $f['dark'] : $f['light'];
	if ( '' === $src ) {
		// Logo yok: site adı.
		return sprintf(
			'<div class="wp-block-site-logo flx-logo-text %1$s"><a href="%2$s" class="custom-logo-link" rel="home">%3$s</a></div>',
			esc_attr( $class ),
			esc_url( home_url( '/' ) ),
			esc_html( $f['alt'] )
		);
	}
	// Header logosu ilk anda görünsün: lazy-load eklentilerinin dışında, yüksek öncelikli.
	$img = sprintf(
		'<img class="custom-logo skip-lazy no-lazyload" data-no-lazy="1" src="%1$s" width="336" height="100" alt="%2$s" loading="eager" fetchpriority="high" decoding="sync">',
		esc_url( $src ),
		esc_attr( $f['alt'] )
	);
	// Karanlık modda açık renk logo doğrudan seçilir (CSS ile sonradan değiştirilmez, gecikme olmaz).
	if ( ! $dark && flx_logo_dark_enabled() && $f['dark'] !== $f['light'] ) {
		$img = sprintf( '<picture><source media="(prefers-color-scheme: dark)" srcset="%s">%s</picture>', esc_url( $f['dark'] ), $img );
	}
	return sprintf(
		'<div class="wp-block-site-logo %1$s"><a href="%2$s" class="custom-logo-link" rel="home" aria-label="%4$s">%3$s</a></div>',
		esc_attr( $class ),
		esc_url( home_url( '/' ) ),
		$img,
		esc_attr__( 'Ana sayfa', 'fluxwp' )
	);
}

function flx_logo_dark_enabled() {
	return function_exists( 'flx_ayar' ) && 'off' !== flx_ayar( 'dark_mode' );
}

/**
 * Logo ve ana yazı tipleri sayfa açılır açılmaz indirilsin (header'da "sonradan gelme" olmasın).
 */
function flx_header_preload() {
	$f = flx_logo_files();
	if ( '' !== $f['light'] && flx_logo_dark_enabled() && $f['dark'] !== $f['light'] ) {
		printf( '<link rel="preload" as="image" href="%s" media="(prefers-color-scheme: light)" fetchpriority="high">' . "\n", esc_url( $f['light'] ) );
		printf( '<link rel="preload" as="image" href="%s" media="(prefers-color-scheme: dark)" fetchpriority="high">' . "\n", esc_url( $f['dark'] ) );
	} elseif ( '' !== $f['light'] ) {
		printf( '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n", esc_url( $f['light'] ) );
	}
	foreach ( array( 'nunito-latin.woff2', 'nunitosans-latin.woff2' ) as $font ) {
		printf( '<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin>' . "\n", esc_url( get_theme_file_uri( 'assets/fonts/' . $font ) ) );
	}
}
add_action( 'wp_head', 'flx_header_preload', 1 );

/**
 * LiteSpeed Cache / Hostinger lazy-load: logo hariç tutulur.
 */
add_filter(
	'litespeed_media_lazy_img_excludes',
	function ( $list ) {
		$list   = (array) $list;
		$list[] = 'custom-logo';
		return $list;
	}
);

/**
 * Medya kütüphanesinden yüklenmiş özel logo da aynı şekilde öncelikli yüklenir.
 */
add_filter(
	'get_custom_logo_image_attributes',
	function ( $attr ) {
		$attr['loading']       = 'eager';
		$attr['fetchpriority'] = 'high';
		$attr['data-no-lazy']  = '1';
		$attr['class']         = trim( ( $attr['class'] ?? '' ) . ' skip-lazy no-lazyload' );
		return $attr;
	}
);

function flx_site_logo_fallback( $content, $block ) {
	if ( 'core/site-logo' !== $block['blockName'] ) {
		return $content;
	}
	$class = $block['attrs']['className'] ?? '';
	if ( false !== strpos( $class, 'flx-logo--beyaz' ) ) {
		return flx_logo_html( true, $class );
	}
	if ( '' === trim( $content ) ) {
		return flx_logo_html( false, $class );
	}
	return $content;
}
add_filter( 'render_block', 'flx_site_logo_fallback', 10, 2 );

/**
 * Favicon sırası: WordPress site simgesi > kurum profilindeki simge > FluxWP simgesi.
 * Flux SEO ya da Flux Admin kuruluysa FluxWP simgesini onlar yazar; tema yalnız ikisi de yoksa yazar.
 * Filtre `flx_theme_favicon` (svg, png, apple adresleri) ile değiştirilebilir; boş dizi kapatır.
 */
function flx_favicon() {
	if ( has_site_icon() ) {
		return;
	}
	$icon = flx_logo_files()['icon'];
	if ( '' !== $icon ) {
		echo '<link rel="icon" href="' . esc_url( $icon ) . '">' . "\n";
		return;
	}
	if ( defined( 'FLXSEO_VERSION' ) || defined( 'FLXA_VERSION' ) ) {
		return;
	}
	$v = '?v=' . wp_get_theme( get_template() )->get( 'Version' );
	$f = (array) apply_filters(
		'flx_theme_favicon',
		array(
			'svg'   => get_theme_file_uri( 'assets/images/favicon/favicon.svg' ) . $v,
			'png'   => get_theme_file_uri( 'assets/images/favicon/favicon-32.png' ) . $v,
			'apple' => get_theme_file_uri( 'assets/images/favicon/apple-touch-icon.png' ) . $v,
		)
	);
	if ( ! empty( $f['svg'] ) ) {
		echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( $f['svg'] ) . '">' . "\n";
	}
	if ( ! empty( $f['png'] ) ) {
		echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url( $f['png'] ) . '">' . "\n";
	}
	if ( ! empty( $f['apple'] ) ) {
		echo '<link rel="apple-touch-icon" href="' . esc_url( $f['apple'] ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'flx_favicon', 2 );
