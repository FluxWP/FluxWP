<?php
/**
 * Erişilebilirlik ve SEO denetimlerinde "boş link / boş buton" çıkmaması için etiketler.
 *
 * @package fluxwp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Öne çıkan görsel linki: yazı başlığıyla etiketlenir (yalnızca görsel içeren link).
 */
add_filter(
	'render_block_core/post-featured-image',
	function ( $html, $block, $instance ) {
		if ( false === strpos( $html, '<a ' ) || false !== strpos( $html, 'aria-label=' ) ) {
			return $html;
		}
		$id    = $instance->context['postId'] ?? get_the_ID();
		$title = $id ? wp_strip_all_tags( get_the_title( $id ) ) : '';
		return $title ? preg_replace( '/<a /', '<a aria-label="' . esc_attr( $title ) . '" ', $html, 1 ) : $html;
	},
	10,
	3
);

/**
 * Arama butonu (yalnızca simge): görünmez metin etiketi.
 * Çekirdek düğmenin adını data-wp-bind--aria-label="state.ariaLabel" ile verir; bu durum sunucuda hesaplanamadığı için
 * Interactivity API sunucu tarafında aria-label'ı siler ve sayfa HTML'inde düğme adsız kalır (ajanlar, denetimler).
 * Tema arama düğmesinde kendi penceresini açar: bağlama kaldırılır, sabit ad verilir.
 */
add_filter(
	'render_block_core/search',
	function ( $html ) {
		if ( ! preg_match( '/<button\b[^>]*wp-block-search__button[^>]*>/', $html, $m ) ) {
			return $html;
		}
		$tag = preg_replace( '/\sdata-wp-bind--aria-label="[^"]*"/', '', $m[0] );
		if ( ! preg_match( '/\saria-label\s*=/', $tag ) ) {
			$tag = preg_replace( '/^<button\b/', '<button aria-label="' . esc_attr__( 'Search', 'fluxwp' ) . '"', $tag );
		}
		return str_replace( $m[0], $tag, $html );
	}
);
