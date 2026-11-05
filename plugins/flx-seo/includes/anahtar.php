<?php
/**
 * Anahtar kelime hedeflemesi ve kurum schema ekleri. Flux SEO içinde siteye özel değer yoktur:
 * - Sayfa SEO varsayılanları `flxseo_keyword_pages`, arşiv başlıkları `flxseo_keyword_archives` filtresiyle (ön ayarlar verir).
 * - Kurum: knowsAbout, areaServed, DonateAction SEO › Kurum ve sosyal ayarlarından (boşsa eklenmez).
 * - Sayfadaki "Ayrıntılar" bloklarından FAQPage.
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sayfa slug'ına göre varsayılan SEO başlığı / açıklaması / odak kelimesi.
 * Flux SEO kendi içinde değer taşımaz; ön ayarlar (flx-preset-*) filtreyle verir.
 *
 * @return array<string, array{title?: string, desc?: string, focus?: string}>
 */
function flxseo_keyword_pages() {
	return (array) apply_filters( 'flxseo_keyword_pages', array() );
}

/**
 * Yazı türü arşivlerine başlık + açıklama (ör. etkinlik, proje). Ön ayarlar filtreyle verir.
 *
 * @return array<string, array{title?: string, desc?: string}>
 */
function flxseo_keyword_archives() {
	return (array) apply_filters( 'flxseo_keyword_archives', array() );
}

/* Ön ayarın verdiği sayfa değerleri, değiştiğinde bir kez boş SEO alanlarına yazılır (elle girilmiş değerlere dokunmaz). */
add_action(
	'init',
	function () {
		$pages = flxseo_keyword_pages();
		$sig   = $pages ? md5( wp_json_encode( $pages ) ) : '';
		if ( '' === $sig || get_option( 'flxseo_kw_sig' ) === $sig ) {
			return;
		}
		foreach ( $pages as $slug => $v ) {
			$p = get_page_by_path( $slug );
			if ( ! $p ) {
				continue;
			}
			foreach ( array( 'title', 'desc', 'focus' ) as $k ) {
				if ( ! empty( $v[ $k ] ) && '' === (string) get_post_meta( $p->ID, '_flxseo_' . $k, true ) ) {
					update_post_meta( $p->ID, '_flxseo_' . $k, $v[ $k ] );
				}
			}
		}
		update_option( 'flxseo_kw_sig', $sig, false );
	},
	30
);

add_filter(
	'pre_get_document_title',
	function ( $title ) {
		if ( flxseo_output_enabled() && is_post_type_archive() && ! is_paged() ) {
			$a = flxseo_keyword_archives()[ get_query_var( 'post_type' ) ] ?? null;
			if ( ! empty( $a['title'] ) ) {
				return apply_filters( 'flxseo_archive_title', $a['title'], get_query_var( 'post_type' ) );
			}
		}
		return $title;
	},
	25
);

add_filter(
	'flxseo_archive_desc',
	function ( $desc, $type ) {
		$a = flxseo_keyword_archives()[ $type ] ?? null;
		return ! empty( $a['desc'] ) ? $a['desc'] : $desc;
	},
	10,
	2
);

/** Bağış sayfasının adresi: ayar, yoksa bilinen slug'lar (filtre flxseo_donate_url). */
function flxseo_donate_url() {
	$url = (string) flxseo_opt( 'donate_url' );
	if ( '' !== $url ) {
		return $url;
	}
	foreach ( array( 'bagis', 'bagis-yap', 'fidan-bagisi' ) as $slug ) {
		$p = get_page_by_path( $slug );
		if ( $p && 'publish' === $p->post_status ) {
			return (string) apply_filters( 'flxseo_donate_url', get_permalink( $p ) );
		}
	}
	return (string) apply_filters( 'flxseo_donate_url', '' );
}

/** Sayfadaki core/details bloklarından soru-cevap listesi. */
function flxseo_faq_items( $post ) {
	if ( ! $post || ! has_block( 'core/details', $post ) ) {
		return array();
	}
	$out  = array();
	$walk = function ( $blocks ) use ( &$walk, &$out ) {
		foreach ( $blocks as $b ) {
			if ( 'core/details' === $b['blockName'] ) {
				$html = render_block( $b );
				if ( preg_match( '#<summary[^>]*>(.*?)</summary>(.*)</details>#is', $html, $m ) ) {
					$q = trim( wp_strip_all_tags( $m[1] ) );
					$a = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $m[2] ) ) );
					if ( $q && $a ) {
						$out[] = array( $q, $a );
					}
				}
			} elseif ( ! empty( $b['innerBlocks'] ) ) {
				$walk( $b['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( $post->post_content ) );
	return $out;
}

add_filter(
	'flxseo_schema_graph',
	function ( $graph ) {
		$home = home_url( '/' );
		foreach ( $graph as &$node ) {
			if ( ( $node['@id'] ?? '' ) !== $home . '#organization' ) {
				continue;
			}
			$area  = trim( (string) flxseo_opt( 'area_served' ) );
			$knows = array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) flxseo_opt( 'knows_about' ) ) ) ) );
			$label = trim( (string) flxseo_opt( 'donate_label' ) );
			if ( '' !== $area ) {
				$node['areaServed'] = array( '@type' => 'Country', 'name' => $area );
			}
			if ( $knows ) {
				$node['knowsAbout'] = $knows;
			}
			$donate = '' !== $label ? flxseo_donate_url() : '';
			if ( $donate ) {
				$node['potentialAction'] = array(
					'@type'     => 'DonateAction',
					'name'      => $label,
					'target'    => $donate,
					'recipient' => array( '@id' => $home . '#organization' ),
				);
			}
		}
		unset( $node );

		if ( is_singular() ) {
			$faq = flxseo_faq_items( get_queried_object() );
			if ( count( $faq ) >= 2 ) {
				$canon   = flxseo_canonical();
				$graph[] = array(
					'@type'      => 'FAQPage',
					'@id'        => $canon . '#faq',
					'isPartOf'   => array( '@id' => $canon . '#webpage' ),
					'mainEntity' => array_map(
						fn( $f ) => array(
							'@type'          => 'Question',
							'name'           => $f[0],
							'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f[1] ),
						),
						$faq
					),
				);
			}
		}
		return $graph;
	}
);
