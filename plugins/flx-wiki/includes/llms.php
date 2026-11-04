<?php
/**
 * LLM dostu çıktılar: /wiki/<yol>.md (sayfa Markdown'u), /wiki/index.md ve /wiki/llms.txt (dizin),
 * /wiki/llms-full.txt (tüm wiki tek dosyada). Flux SEO'nun kök /llms.txt dosyasına "Wiki" bölümü eklenir.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'template_redirect',
	function () {
		$llms = get_query_var( 'flxwk_llms' );
		$raw  = get_query_var( 'flxwk_raw' );
		if ( $llms ) {
			flxwk_send_text( 'full' === $llms ? flxwk_full_markdown() : flxwk_index_markdown(), 'text/plain' );
		}
		if ( ! $raw ) {
			return;
		}
		if ( '__index' === $raw ) {
			flxwk_send_text( flxwk_index_markdown(), 'text/markdown' );
		}
		$post = get_page_by_path( (string) $raw, OBJECT, FLXWK_PT );
		if ( ! $post || ! flxwk_readable( $post ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}
		flxwk_send_text( flxwk_page_markdown( $post ), 'text/markdown' );
	},
	0
);

/** Ziyaretçi bu sayfayı okuyabilir mi (yayında, parolasız ya da düzenleme yetkisi). */
function flxwk_readable( $post ) {
	$post = get_post( $post );
	if ( ! $post || FLXWK_PT !== $post->post_type ) {
		return false;
	}
	if ( 'publish' === $post->post_status && '' === $post->post_password ) {
		return true;
	}
	return current_user_can( 'read_post', $post->ID );
}

/** Wiki bağlantılarını mutlak Markdown bağlantısına çevirir; olmayan sayfa düz metin kalır. */
function flxwk_md_export( $md ) {
	$md = flxwk_wikilinks( (string) $md );
	return preg_replace( '#<span class="flxwk-missing"[^>]*>(.*?)</span>#', '$1', $md );
}

/** Bir sayfanın LLM'e verilecek Markdown'u (başlık, kaynak, tarih + içerik). */
function flxwk_page_markdown( $post ) {
	$post = get_post( $post );
	$sec  = flxwk_section_of( $post );
	$head = '# ' . get_the_title( $post ) . "\n\n";
	$ex   = flxwk_excerpt( $post, 300 );
	if ( $ex ) {
		$head .= '> ' . $ex . "\n\n";
	}
	$meta   = array();
	$meta[] = __( 'Kaynak', 'flx-wiki' ) . ': ' . get_permalink( $post );
	if ( $sec ) {
		$meta[] = __( 'Bölüm', 'flx-wiki' ) . ': ' . $sec->name;
	}
	$meta[] = __( 'Son güncelleme', 'flx-wiki' ) . ': ' . get_post_modified_time( 'Y-m-d', true, $post );
	$body   = flxwk_md_export( flxwk_md( $post ) );
	/* İçerik kendi H1'iyle başlıyorsa tekrar etmesin. */
	$body = preg_replace( '/^\s*#\s+[^\n]+\n+/', '', $body, 1 );
	$text = $head . implode( "  \n", $meta ) . "\n\n" . trim( $body ) . "\n";
	return (string) apply_filters( 'flxwk_page_markdown', $text, $post );
}

/** llms.txt biçiminde dizin: başlık, özet, bölümlere göre sayfalar (.md adresleriyle). */
function flxwk_index_markdown() {
	$out   = array();
	$out[] = '# ' . flxwk_title();
	$out[] = '';
	$out[] = '> ' . flxwk_intro();
	$out[] = '';
	$out[] = sprintf(
		/* translators: 1: llms-full.txt adresi */
		__( 'Her sayfanın Markdown hâli adresin sonuna .md eklenerek alınır. Tüm wiki tek dosyada: %1$s', 'flx-wiki' ),
		home_url( '/' . flxwk_base() . '/llms-full.txt' )
	);
	if ( function_exists( 'flxai_mcp_enabled' ) && flxai_mcp_enabled() && function_exists( 'flxai_mcp_url' ) ) {
		/* translators: %s: MCP sunucu adresi */
		$out[] = sprintf( __( 'MCP sunucusu (anahtar gerekir): %s, araçlar flxwk_search, flxwk_get_page, flxwk_list_pages.', 'flx-wiki' ), flxai_mcp_url() );
	}
	$out[] = '';
	$line = function ( $p, $depth ) {
		$ex = flxwk_excerpt( $p, 140 );
		return str_repeat( '  ', $depth ) . '- [' . get_the_title( $p ) . '](' . flxwk_md_url( $p ) . ')' . ( $ex ? ': ' . $ex : '' );
	};
	$walk = function ( $nodes, $depth ) use ( &$walk, &$out, $line ) {
		foreach ( $nodes as $n ) {
			$out[] = $line( $n['post'], $depth );
			$walk( $n['children'], $depth + 1 );
		}
	};
	foreach ( flxwk_tree() as $g ) {
		$out[] = '## ' . ( $g['term'] ? $g['term']->name : __( 'Diğer sayfalar', 'flx-wiki' ) );
		$out[] = '';
		$walk( $g['items'], 0 );
		$out[] = '';
	}
	return (string) apply_filters( 'flxwk_index_markdown', implode( "\n", $out ) );
}

/** Tüm wiki tek Markdown dosyasında (llms-full.txt). */
function flxwk_full_markdown() {
	$parts = array( flxwk_index_markdown() );
	foreach ( flxwk_flat() as $p ) {
		$parts[] = flxwk_page_markdown( $p );
	}
	return implode( "\n\n---\n\n", $parts );
}

/* Flux SEO /llms.txt: "Wiki" bölümü, "İsteğe bağlı" bölümünden önce. */
add_filter(
	'flxseo_llms_txt',
	function ( $text ) {
		$rows   = array();
		$rows[] = '## ' . flxwk_title();
		$rows[] = '';
		$rows[] = '- [' . flxwk_title() . ' – llms.txt](' . home_url( '/' . flxwk_base() . '/llms.txt' ) . '): ' . flxwk_intro();
		$rows[] = '- [' . __( 'Tüm wiki (tek dosya)', 'flx-wiki' ) . '](' . home_url( '/' . flxwk_base() . '/llms-full.txt' ) . ')';
		$block = implode( "\n", $rows ) . "\n\n";
		$at    = strrpos( $text, "\n## " );
		return false === $at ? $text . "\n" . $block : substr( $text, 0, $at + 1 ) . $block . substr( $text, $at + 1 );
	}
);
