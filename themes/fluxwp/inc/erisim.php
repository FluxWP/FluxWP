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
 */
add_filter(
	'render_block_core/search',
	function ( $html ) {
		return preg_replace( '/<button(?![^>]*aria-label)([^>]*class="[^"]*wp-block-search__button)/', '<button aria-label="' . esc_attr__( 'Ara', 'fluxwp' ) . '"$1', $html, 1 );
	}
);
