<?php
/**
 * Anahtar kelime hedeflemesi: "fidan bağışı" ve yakın aramalar.
 * - Bağış ve fidan sayfalarına SEO başlığı / açıklaması / odak kelimesi (yalnızca boşsa, bir kez).
 * - Etkinlik ve proje arşivleri için başlık + açıklama.
 * - Schema: kuruma DonateAction, areaServed, knowsAbout; sayfadaki "Ayrıntılar" bloklarından FAQPage.
 * Tüm değerler sonradan sayfanın SEO panelinden değiştirilebilir.
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function flxseo_keyword_pages() {
	return array(
		'bagis'                => array(
			'title' => '150 TL’ye Fidan Bağışı – Sevdiklerin Adına Fidan Bağışla | Örnek Vakfı',
			'desc'  => 'Bir fidan 150 TL: Türkiye’nin farklı şehirlerindeki fidan dikim etkinliklerimiz için fidan bağışı yapın. Kart ya da Havale/EFT ile kolayca bağışlayın, adınıza fidan sertifikası düzenlensin.',
			'focus' => 'fidan bağışı',
		),
		'fidan-nasil-dikilir'  => array(
			'title' => 'Fidan Nasıl Dikilir? Adım Adım Fidan Dikimi | Örnek Vakfı',
			'desc'  => 'Fidan nasıl dikilir? Çukur açmaktan can suyuna kadar 4 adımda doğru fidan dikimi, kısa video ve fidan dikim etkinliklerimiz. Siz de fidan bağışıyla katılın.',
			'focus' => 'fidan nasıl dikilir',
		),
		'fidan-dikim-videomuz' => array(
			'title' => 'Fidan Dikim Videomuz – Ailen İçin Fidan Dik | Örnek Vakfı',
			'desc'  => 'Fidan dikim etkinliğimizi izleyin. Aileniz ve sevdikleriniz adına fidan bağışı yapın; fidanınız bir sonraki dikimde toprakla buluşsun, adınıza sertifika düzenlensin.',
			'focus' => 'fidan dikimi',
		),
	);
}

function flxseo_keyword_archives() {
	return array(
		'etkinlik' => array(
			'title' => 'Fidan Dikim Etkinlikleri – Örnek Vakfı',
			'desc'  => 'Türkiye’nin farklı şehirlerinde devam eden ve geçmiş fidan dikim etkinliklerimiz. Tarih, konum ve toplanan fidan sayısını görün; fidan bağışıyla etkinliğe katkıda bulunun.',
		),
		'proje'    => array(
			'title' => 'Ağaçlandırma ve Çevre Projeleri – Örnek Vakfı',
			'desc'  => 'Örnek Vakfı’nin ağaçlandırma, orman koruma ve çevre eğitimi projeleri. Fidan bağışınızla projelerimize destek olun.',
		),
	);
}

/* Bir kez: boş SEO alanlarını doldur (elle girilmiş değerlere dokunmaz). */
add_action(
	'init',
	function () {
		if ( version_compare( (string) get_option( 'flxseo_kw', '0' ), '1.0.7', '>=' ) ) {
			return;
		}
		// 1.0.6'nın bağış varsayılanları (fiyatsız) değiştirilmediyse yenisiyle değişir.
		$old = array(
			'title' => 'Fidan Bağışı Yap – İstanbul İçin Fidan Bağışla | Örnek Vakfı',
			'desc'  => 'İstanbul’da fidan dikim etkinliklerimiz için fidan bağışı yapın. Havale/EFT ile kolayca bağışlayın, fidanınız adınıza düzenlenen sertifikayla kayıt altına alınsın.',
		);
		$bp  = get_page_by_path( 'bagis' );
		if ( $bp ) {
			foreach ( $old as $k => $v ) {
				if ( get_post_meta( $bp->ID, '_flxseo_' . $k, true ) === $v ) {
					delete_post_meta( $bp->ID, '_flxseo_' . $k );
				}
			}
		}
		foreach ( flxseo_keyword_pages() as $slug => $v ) {
			$p = get_page_by_path( $slug );
			if ( ! $p ) {
				continue;
			}
			foreach ( array( 'title', 'desc', 'focus' ) as $k ) {
				if ( '' === (string) get_post_meta( $p->ID, '_flxseo_' . $k, true ) ) {
					update_post_meta( $p->ID, '_flxseo_' . $k, $v[ $k ] );
				}
			}
		}
		update_option( 'flxseo_kw', '1.0.7', false );
	},
	30
);

/* 1.0.12: İstanbul vurgulu eski varsayılan başlık/açıklamalar değiştirilmediyse yenileriyle değişir. */
add_action(
	'init',
	function () {
		if ( version_compare( (string) get_option( 'flxseo_kw', '0' ), '1.0.12', '>=' ) ) {
			return;
		}
		$old = array(
			'bagis'                => array(
				'title' => '150 TL’ye Fidan Bağışı – İstanbul İçin Fidan Bağışla | Örnek Vakfı',
				'desc'  => 'Bir fidan 150 TL: İstanbul’daki fidan dikim etkinliklerimiz için fidan bağışı yapın. Havale/EFT ile kolayca bağışlayın, adınıza fidan sertifikası düzenlensin.',
			),
			'fidan-nasil-dikilir'  => array(
				'desc' => 'Fidan nasıl dikilir? Çukur açmaktan can suyuna kadar 4 adımda doğru fidan dikimi, kısa video ve İstanbul fidan dikim etkinliklerimiz. Siz de fidan bağışıyla katılın.',
			),
			'fidan-dikim-videomuz' => array(
				'desc' => 'İstanbul’daki fidan dikim etkinliğimizi izleyin. Aileniz ve sevdikleriniz adına fidan bağışı yapın; fidanınız bir sonraki dikimde toprakla buluşsun, adınıza sertifika düzenlensin.',
			),
		);
		$new = flxseo_keyword_pages();
		foreach ( $old as $slug => $fields ) {
			$p = get_page_by_path( $slug );
			if ( ! $p ) {
				continue;
			}
			foreach ( $fields as $k => $v ) {
				if ( get_post_meta( $p->ID, '_flxseo_' . $k, true ) === $v ) {
					update_post_meta( $p->ID, '_flxseo_' . $k, $new[ $slug ][ $k ] );
				}
			}
		}
		$o = (array) get_option( 'flxseo', array() );
		if ( ( $o['home_desc'] ?? '' ) === 'Örnek Vakfı: fidan dikim etkinlikleri, çevre eğitimleri ve fidan bağışıyla daha yeşil bir İstanbul için çalışıyoruz.' ) {
			$o['home_desc'] = flxseo_defaults()['home_desc'];
			update_option( 'flxseo', $o );
		}
		update_option( 'flxseo_kw', '1.0.12', false );
	},
	31
);

add_filter(
	'pre_get_document_title',
	function ( $title ) {
		if ( flxseo_output_enabled() && is_post_type_archive() && ! is_paged() ) {
			$a = flxseo_keyword_archives()[ get_query_var( 'post_type' ) ] ?? null;
			if ( $a ) {
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
		return $a ? $a['desc'] : $desc;
	},
	10,
	2
);

/** Bağış sayfasının adresi (yoksa boş). */
function flxseo_donate_url() {
	foreach ( array( 'bagis', 'bagis-yap', 'fidan-bagisi' ) as $slug ) {
		$p = get_page_by_path( $slug );
		if ( $p && 'publish' === $p->post_status ) {
			return get_permalink( $p );
		}
	}
	return '';
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
		$home   = home_url( '/' );
		$donate = flxseo_donate_url();
		foreach ( $graph as &$node ) {
			if ( ( $node['@id'] ?? '' ) !== $home . '#organization' ) {
				continue;
			}
			$node['areaServed'] = array( '@type' => 'Country', 'name' => 'Türkiye' );
			$node['knowsAbout'] = array( 'Fidan bağışı', 'Fidan dikimi', 'Ağaçlandırma', 'Orman koruma', 'Çevre eğitimi' );
			if ( $donate ) {
				$node['potentialAction'] = array(
					'@type'     => 'DonateAction',
					'name'      => 'Fidan bağışı yap',
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
