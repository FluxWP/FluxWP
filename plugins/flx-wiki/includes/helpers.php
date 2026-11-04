<?php
/**
 * Ortak yardımcılar: ayarlar, adresler, sayfa verisi, Türkçe katlama.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

/** Varsayılan ayarlar. */
function flxwk_defaults() {
	return array(
		'title'     => '',          // boş: "Geliştirici Wiki" (dile göre)
		'intro'     => '',          // boş: varsayılan tanıtım
		'ask'       => 'users',     // off | users | all : "Wiki'ye sor" (Flux AI)
		'ask_limit' => 20,          // saatte kişi başı soru
		'ai_links'  => 1,           // ChatGPT / Claude / Perplexity'de aç bağlantıları
		'edit_link' => 1,           // yetkililere "Düzenle"
	);
}

function flxwk_settings() {
	$s = get_option( 'flxwk_settings', array() );
	return array_merge( flxwk_defaults(), is_array( $s ) ? $s : array() );
}

function flxwk_opt( $key ) {
	$s = flxwk_settings();
	return $s[ $key ] ?? null;
}

/** Wiki'nin adres kökü (varsayılan "wiki"). */
function flxwk_base() {
	$b = trim( (string) apply_filters( 'flxwk_base', 'wiki' ), '/' );
	return '' === $b ? 'wiki' : $b;
}

function flxwk_home_url( $path = '' ) {
	return home_url( user_trailingslashit( '/' . flxwk_base() . ( $path ? '/' . ltrim( $path, '/' ) : '' ) ) );
}

function flxwk_admin_url() {
	return admin_url( 'edit.php?post_type=' . FLXWK_PT . '&page=flx-wiki' );
}

function flxwk_title() {
	$t = trim( (string) flxwk_opt( 'title' ) );
	return '' !== $t ? $t : __( 'Geliştirici Wiki', 'flx-wiki' );
}

function flxwk_intro() {
	$t = trim( (string) flxwk_opt( 'intro' ) );
	return '' !== $t ? $t : __( 'FluxWP için eklenti ve tema yazma, AI destekli özellikler ve ortak altyapı rehberleri.', 'flx-wiki' );
}

/** Sayfanın Markdown kaynağı. */
function flxwk_md( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$md = get_post_meta( $post->ID, '_flxwk_md', true );
	return is_string( $md ) ? $md : '';
}

/** Sayfanın .md adresi (/wiki/ust/alt.md). */
function flxwk_md_url( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return flxwk_home_url( 'index.md' );
	}
	$path = get_page_uri( $post );
	if ( '' === (string) $post->post_name || '' === (string) $path ) {
		return ''; // taslak: henüz kalıcı adresi yok
	}
	return home_url( '/' . flxwk_base() . '/' . $path . '.md' );
}

/** Türkçe karakterleri katlayıp küçük harfe çevirir (arama için). */
function flxwk_fold( $text ) {
	$text = (string) $text;
	$text = strtr( $text, array( 'İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ğ' => 'g', 'ğ' => 'g', 'Ş' => 's', 'ş' => 's', 'Ç' => 'c', 'ç' => 'c', 'Ö' => 'o', 'ö' => 'o', 'Ü' => 'u', 'ü' => 'u', 'Â' => 'a', 'â' => 'a', 'Î' => 'i', 'î' => 'i', 'Û' => 'u', 'û' => 'u' ) );
	$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	return remove_accents( $text );
}

/** Başlıktan bağlantı kimliği (#kurulum-adimlari). */
function flxwk_anchor( $text ) {
	$s = flxwk_fold( wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) ) );
	$s = preg_replace( '/[^a-z0-9]+/', '-', $s );
	$s = trim( (string) $s, '-' );
	return '' === $s ? 'bolum' : substr( $s, 0, 80 );
}

/** Okuma süresi (dakika). */
function flxwk_read_minutes( $md ) {
	$words = str_word_count( wp_strip_all_tags( (string) $md ) );
	$words = max( $words, (int) ( mb_strlen( (string) $md ) / 7 ) );
	return max( 1, (int) round( $words / 200 ) );
}

/** Sayfanın bölümü (ilk terim) ya da null. */
function flxwk_section_of( $post ) {
	$post  = get_post( $post );
	$top   = $post ? flxwk_root( $post ) : null;
	$terms = $top ? get_the_terms( $top, FLXWK_TAX ) : null;
	return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;
}

/** Ağacın kök sayfası. */
function flxwk_root( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return null;
	}
	$anc = get_post_ancestors( $post );
	return $anc ? get_post( end( $anc ) ) : $post;
}

/**
 * Yayımlanmış tüm wiki sayfaları (menu_order, başlık sırasıyla), istek içinde önbellekli.
 *
 * @return WP_Post[]
 */
function flxwk_all_pages() {
	static $cache = null;
	if ( null === $cache ) {
		$cache = get_posts(
			array(
				'post_type'        => FLXWK_PT,
				'post_status'      => 'publish',
				'posts_per_page'   => 1000,
				'orderby'          => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
				'has_password'     => false,
				'suppress_filters' => false,
			)
		);
	}
	return $cache;
}

/**
 * Bölümler ve sayfa ağacı: [ [ term|null, [ [post, children[]] ] ] ].
 */
function flxwk_tree() {
	$pages    = flxwk_all_pages();
	$children = array();
	foreach ( $pages as $p ) {
		$children[ (int) $p->post_parent ][] = $p;
	}
	$build = function ( $parent ) use ( &$build, $children ) {
		$out = array();
		foreach ( $children[ $parent ] ?? array() as $p ) {
			$out[] = array( 'post' => $p, 'children' => $build( $p->ID ) );
		}
		return $out;
	};
	$roots    = $build( 0 );
	$sections = get_terms(
		array(
			'taxonomy'   => FLXWK_TAX,
			'hide_empty' => false,
			'orderby'    => 'term_order',
		)
	);
	$sections = is_wp_error( $sections ) ? array() : $sections;
	usort(
		$sections,
		function ( $a, $b ) {
			$oa = (int) get_term_meta( $a->term_id, 'flxwk_order', true );
			$ob = (int) get_term_meta( $b->term_id, 'flxwk_order', true );
			return $oa === $ob ? strcmp( $a->name, $b->name ) : $oa - $ob;
		}
	);
	$groups = array();
	$used   = array();
	foreach ( $sections as $t ) {
		$items = array();
		foreach ( $roots as $node ) {
			if ( has_term( $t->term_id, FLXWK_TAX, $node['post'] ) && empty( $used[ $node['post']->ID ] ) ) {
				$items[]                      = $node;
				$used[ $node['post']->ID ] = true;
			}
		}
		if ( $items ) {
			$groups[] = array( 'term' => $t, 'items' => $items );
		}
	}
	$rest = array_values( array_filter( $roots, fn( $n ) => empty( $used[ $n['post']->ID ] ) ) );
	if ( $rest ) {
		$groups[] = array( 'term' => null, 'items' => $rest );
	}
	return $groups;
}

/** Ağacı düz sırada döndürür (önceki / sonraki için). */
function flxwk_flat() {
	$out  = array();
	$walk = function ( $nodes ) use ( &$walk, &$out ) {
		foreach ( $nodes as $n ) {
			$out[] = $n['post'];
			$walk( $n['children'] );
		}
	};
	foreach ( flxwk_tree() as $g ) {
		$walk( $g['items'] );
	}
	return $out;
}

/** Özet: elle yazılan özet ya da Markdown'un ilk paragrafı. */
function flxwk_excerpt( $post, $len = 160 ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$text = $post->post_excerpt ? $post->post_excerpt : flxwk_first_paragraph( flxwk_md( $post ) );
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
	if ( mb_strlen( $text ) > $len ) {
		$cut  = mb_substr( $text, 0, $len - 1 );
		$sp   = mb_strrpos( $cut, ' ' );
		$text = rtrim( $sp > $len * 0.6 ? mb_substr( $cut, 0, $sp ) : $cut, ' ,.;:-' ) . '…';
	}
	return $text;
}

/** Markdown'un başlık, kod ve liste olmayan ilk paragrafı (düz metin). */
function flxwk_first_paragraph( $md ) {
	$md = preg_replace( '/^```.*?^```/ms', '', (string) $md );
	foreach ( preg_split( '/\n\s*\n/', (string) $md ) as $block ) {
		$b = trim( $block );
		if ( '' === $b || preg_match( '/^(#|>|\||[-*+] |\d+\. |<|!\[)/', $b ) ) {
			continue;
		}
		$b = preg_replace( '/!\[[^\]]*\]\([^)]*\)/', '', $b );
		$b = preg_replace( '/\[([^\]]+)\]\([^)]*\)/', '$1', $b );
		$b = preg_replace_callback( '/\[\[([^\]|#]+)(?:#[^\]|]*)?(?:\|([^\]]+))?\]\]/', fn( $m ) => isset( $m[2] ) && '' !== $m[2] ? $m[2] : ( ( $p = flxwk_find_page( $m[1] ) ) ? get_the_title( $p ) : $m[1] ), $b );
		return trim( str_replace( array( '**', '__', '`', '*' ), '', $b ) );
	}
	return '';
}

/** "Wiki'ye sor" bu ziyaretçiye açık mı. */
function flxwk_can_ask() {
	$mode = (string) flxwk_opt( 'ask' );
	if ( 'off' === $mode || ! flxwk_ai_ready() ) {
		return false;
	}
	$ok = 'all' === $mode || is_user_logged_in();
	return (bool) apply_filters( 'flxwk_can_ask', $ok );
}

/** Flux AI kurulu, anahtarlı ve bu sistem açık mı. */
function flxwk_ai_ready() {
	return function_exists( 'flxai_chat' ) && function_exists( 'flxai_ready' ) && flxai_ready( 'flx-wiki' )
		&& ( ! function_exists( 'flxai_source_enabled' ) || flxai_source_enabled( 'flx-wiki' ) );
}

/** Tarih: FluxWP ortak yardımcısı (dil karışmasın). */
function flxwk_ago( $time ) {
	return flx_time_ago( $time );
}
