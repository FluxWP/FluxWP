<?php
/**
 * Markdown → HTML: Parsedown (MIT) + wiki bağlantıları ([[sayfa]]), uyarı kutuları (> [!NOTE]),
 * başlık kimlikleri ve içindekiler. Çıktı wp_kses_post ile temizlenir.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

require_once FLXWK_DIR . 'includes/lib/class-flxwk-parsedown.php';

/** İşleyici sürümü; değişince önbellekteki HTML yeniden üretilir. */
const FLXWK_RENDER_VER = '1';

/**
 * Markdown'u HTML'e çevirir.
 *
 * @param string $md Kaynak.
 * @return array { html: string, toc: array<int, array{level:int, id:string, text:string}> }
 */
function flxwk_render( $md ) {
	$md = str_replace( array( "\r\n", "\r" ), "\n", (string) $md );
	$md = flxwk_wikilinks( $md );

	$pd = new FLXWK_Parsedown();
	$pd->setSafeMode( false );
	$pd->setBreaksEnabled( false );
	$html = $pd->text( $md );

	$html = flxwk_callouts( $html );
	$html = wp_kses( $html, flxwk_allowed_html() );

	/* Başlıklara kimlik + bağlantı; içindekiler. */
	$toc  = array();
	$seen = array();
	$html = preg_replace_callback(
		'#<h([1-4])>(.*?)</h\1>#s',
		function ( $m ) use ( &$toc, &$seen ) {
			$level = (int) $m[1];
			$text  = trim( wp_strip_all_tags( $m[2] ) );
			$id    = flxwk_anchor( $text );
			$base  = $id;
			$n     = 2;
			while ( isset( $seen[ $id ] ) ) {
				$id = $base . '-' . $n++;
			}
			$seen[ $id ] = true;
			if ( $level >= 2 && $level <= 3 ) {
				$toc[] = array( 'level' => $level, 'id' => $id, 'text' => html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );
			}
			return '<h' . $level . ' id="' . esc_attr( $id ) . '">' . $m[2] . '<a class="flxwk-anchor" href="#' . esc_attr( $id ) . '" aria-hidden="true" tabindex="-1">#</a></h' . $level . '>';
		},
		$html
	);

	/* Kod blokları: dil etiketi; tablolar: kaydırılabilir kap. */
	$html = preg_replace( '#<pre><code class="language-([a-z0-9_+-]+)">#i', '<pre data-lang="$1"><code class="language-$1">', $html );
	$html = preg_replace( '#<table>#', '<div class="flxwk-table"><table>', $html );
	$html = str_replace( '</table>', '</table></div>', $html );
	/* Dış bağlantılar yeni sekmede. */
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$html = preg_replace_callback(
		'#<a href="(https?://[^"]+)"#',
		function ( $m ) use ( $host ) {
			$h = wp_parse_url( html_entity_decode( $m[1] ), PHP_URL_HOST );
			return ( $h && $h !== $host ) ? $m[0] . ' target="_blank" rel="noopener"' : $m[0];
		},
		$html
	);
	/* Görev listeleri: - [ ] / - [x] */
	$html = preg_replace( '#<li>\s*\[ \]\s*#', '<li class="flxwk-task"><input type="checkbox" disabled> ', $html );
	$html = preg_replace( '#<li>\s*\[[xX]\]\s*#', '<li class="flxwk-task"><input type="checkbox" checked disabled> ', $html );
	/* Kısa kodlar çalışmasın (belgelerde [flx_credit] gibi örnekler olur). */
	$html = str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), $html );

	return array(
		'html' => $html,
		'toc'  => $toc,
	);
}

/** Kod blokları dışındaki [[sayfa]] ve [[sayfa|etiket]] bağlantılarını Markdown bağlantısına çevirir. */
function flxwk_wikilinks( $md ) {
	$parts = preg_split( '/(^```.*?^```\s*$|`[^`\n]+`)/ms', $md, -1, PREG_SPLIT_DELIM_CAPTURE );
	foreach ( $parts as $i => $part ) {
		if ( $i % 2 ) {
			continue; // kod
		}
		$parts[ $i ] = preg_replace_callback(
			'/\[\[([^\]|#]+)(#[^\]|]+)?(?:\|([^\]]+))?\]\]/u',
			function ( $m ) {
				$target = trim( $m[1] );
				$hash   = isset( $m[2] ) ? $m[2] : '';
				$label  = isset( $m[3] ) && '' !== trim( $m[3] ) ? trim( $m[3] ) : $target;
				$post   = flxwk_find_page( $target );
				if ( ! $post ) {
					return '<span class="flxwk-missing" title="' . esc_attr__( 'Bu sayfa henüz yok', 'flx-wiki' ) . '">' . esc_html( $label ) . '</span>';
				}
				if ( $label === $target ) {
					$label = get_the_title( $post );
				}
				return '[' . str_replace( array( '[', ']' ), '', $label ) . '](' . get_permalink( $post ) . $hash . ')';
			},
			$part
		);
	}
	return implode( '', $parts );
}

/** Kısa ad, yol (ust/alt) ya da başlıkla sayfa bulur. */
function flxwk_find_page( $ref ) {
	$ref = trim( (string) $ref, " /\t" );
	if ( '' === $ref ) {
		return null;
	}
	if ( ctype_digit( $ref ) ) {
		$p = get_post( (int) $ref );
		return ( $p && FLXWK_PT === $p->post_type ) ? $p : null;
	}
	$p = get_page_by_path( $ref, OBJECT, FLXWK_PT );
	if ( $p ) {
		return $p;
	}
	$slug = sanitize_title( $ref );
	$q    = get_posts(
		array(
			'post_type'      => FLXWK_PT,
			'name'           => $slug,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => 1,
		)
	);
	if ( $q ) {
		return $q[0];
	}
	foreach ( flxwk_all_pages() as $p ) {
		if ( flxwk_fold( $p->post_title ) === flxwk_fold( $ref ) ) {
			return $p;
		}
	}
	return null;
}

/** GitHub tarzı uyarılar: > [!NOTE] / [!TIP] / [!IMPORTANT] / [!WARNING] / [!CAUTION]. */
function flxwk_callouts( $html ) {
	$labels = array(
		'note'      => __( 'Not', 'flx-wiki' ),
		'tip'       => __( 'İpucu', 'flx-wiki' ),
		'important' => __( 'Önemli', 'flx-wiki' ),
		'warning'   => __( 'Uyarı', 'flx-wiki' ),
		'caution'   => __( 'Dikkat', 'flx-wiki' ),
	);
	return preg_replace_callback(
		'#<blockquote>\s*<p>\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*(.*?)</blockquote>#s',
		function ( $m ) use ( $labels ) {
			$type = strtolower( $m[1] );
			return '<div class="flxwk-callout is-' . $type . '"><p class="flxwk-callout__t">' . esc_html( $labels[ $type ] ) . '</p><p>' . ltrim( $m[2] ) . '</div>';
		},
		$html
	);
}

/** İzin verilen HTML: wp_kses_post + kod/tablo/details öğeleri. */
function flxwk_allowed_html() {
	$a = wp_kses_allowed_html( 'post' );
	foreach ( array( 'pre', 'code', 'kbd', 'details', 'summary', 'mark', 'sup', 'sub' ) as $t ) {
		$a[ $t ] = array_merge( $a[ $t ] ?? array(), array( 'class' => true, 'id' => true, 'open' => true ) );
	}
	$a['div']  = array_merge( $a['div'] ?? array(), array( 'class' => true ) );
	$a['p']    = array_merge( $a['p'] ?? array(), array( 'class' => true ) );
	$a['span'] = array_merge( $a['span'] ?? array(), array( 'class' => true, 'title' => true ) );
	$a['th']   = array_merge( $a['th'] ?? array(), array( 'style' => true ) );
	$a['td']   = array_merge( $a['td'] ?? array(), array( 'style' => true ) );
	return $a;
}

/**
 * Sayfanın HTML'i ve içindekiler (önbellekli: _flxwk_html, _flxwk_toc).
 *
 * @return array { html, toc }
 */
function flxwk_page_html( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array( 'html' => '', 'toc' => array() );
	}
	$md    = flxwk_md( $post );
	$key   = FLXWK_RENDER_VER . ':' . (int) get_option( 'flxwk_gen', 0 ) . ':' . md5( $md );
	$cache = get_post_meta( $post->ID, '_flxwk_html', true );
	if ( is_array( $cache ) && ( $cache['key'] ?? '' ) === $key ) {
		return $cache;
	}
	$r        = flxwk_render( $md );
	$r['key'] = $key;
	if ( ! wp_is_post_revision( $post ) ) {
		update_post_meta( $post->ID, '_flxwk_html', wp_slash( $r ) );
	}
	return $r;
}
