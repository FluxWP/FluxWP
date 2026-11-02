<?php
/**
 * Sayfa/şablon gövdesi: blok markup'ı ya da JSON blok ağacı.
 *
 * Gövde alanı şu biçimlerden biri olabilir:
 *  - "markup" metni (WordPress blok markup'ı, <!-- wp:... --> yorumlarıyla),
 *  - { "$file": "pages/x.html" } (yükleyicide metne çevrilir),
 *  - blok ağacı: [ node, ... ]. Node:
 *      "metin"                                      → olduğu gibi markup parçası
 *      { "name": "core/paragraph", "attrs": {...}, "html": "<p>Merhaba</p>" }
 *      { "name": "core/group", "attrs": {...}, "html": "<div class=\"wp-block-group\">{{inner}}</div>", "inner": [ ... ] }
 *      { "name": "core/template-part", "attrs": { "slug": "header" } }   (dinamik blok, html yok)
 * Metinlerdeki yer tutucular ({profile.name}, {url:@page:iletisim} …) uygulamada çözülür.
 */

defined( 'ABSPATH' ) || exit;

/** Gövdeyi markup'a çevirir. */
function flxk_body_to_markup( $body ) {
	if ( is_string( $body ) ) {
		return $body;
	}
	if ( ! is_array( $body ) ) {
		return '';
	}
	if ( isset( $body['name'] ) ) {
		$body = array( $body );
	}
	$out = array();
	foreach ( $body as $node ) {
		$out[] = is_string( $node ) ? $node : serialize_block( flxk_node_to_block( $node ) );
	}
	return implode( "\n\n", $out );
}

function flxk_node_to_block( $node ) {
	$name  = isset( $node['name'] ) ? (string) $node['name'] : 'core/html';
	$attrs = isset( $node['attrs'] ) && is_array( $node['attrs'] ) ? $node['attrs'] : array();
	$html  = isset( $node['html'] ) ? (string) $node['html'] : '';
	$inner = array();
	foreach ( (array) ( $node['inner'] ?? array() ) as $child ) {
		if ( is_string( $child ) ) {
			$child = array(
				'name' => 'core/html',
				'html' => $child,
			);
		}
		$inner[] = flxk_node_to_block( $child );
	}
	$content = array();
	if ( $inner ) {
		$parts = explode( '{{inner}}', $html, 2 );
		$open  = $parts[0];
		$close = $parts[1] ?? '';
		$content[] = $open;
		foreach ( $inner as $i => $unused ) {
			if ( $i ) {
				$content[] = "\n\n";
			}
			$content[] = null;
		}
		$content[] = $close;
	} elseif ( '' !== $html ) {
		$content[] = str_replace( '{{inner}}', '', $html );
	}
	return array(
		'blockName'    => $name,
		'attrs'        => $attrs,
		'innerBlocks'  => $inner,
		'innerHTML'    => implode( '', array_filter( $content, 'is_string' ) ),
		'innerContent' => $content,
	);
}

/**
 * Markup'ı kabaca doğrular: kapanmamış blok yorumu, kayıtlı olmayan blok adı.
 *
 * @return string[] uyarılar
 */
function flxk_check_markup( $markup ) {
	$warn = array();
	if ( substr_count( $markup, '<!-- wp:' ) < substr_count( $markup, '<!-- /wp:' ) ) {
		$warn[] = __( 'Kapanış yorumu açılıştan fazla.', 'flx-setup' );
	}
	$reg = class_exists( 'WP_Block_Type_Registry' ) ? WP_Block_Type_Registry::get_instance() : null;
	$walk = function ( $blocks ) use ( &$walk, &$warn, $reg ) {
		foreach ( $blocks as $b ) {
			if ( $b['blockName'] && $reg && did_action( 'init' ) && ! $reg->is_registered( $b['blockName'] ) ) {
				/* translators: %s: blok adı */
				$warn[] = sprintf( __( 'Kayıtlı olmayan blok: %s', 'flx-setup' ), $b['blockName'] );
			}
			if ( ! empty( $b['innerBlocks'] ) ) {
				$walk( $b['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( $markup ) );
	return array_values( array_unique( $warn ) );
}
