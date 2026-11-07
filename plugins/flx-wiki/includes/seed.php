<?php
/**
 * Hazır rehberler: content/*.md (ön bilgili Markdown) ve content/sections.json wiki'ye aktarılır.
 * Elle değiştirilmemiş sayfalar yeni sürümle güncellenir; değiştirilenlere dokunulmaz.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

/** Ön bilgiyi (--- anahtar: değer ---) ayırır. */
function flxwk_parse_front( $text ) {
	$meta = array();
	$body = str_replace( "\r\n", "\n", (string) $text );
	if ( preg_match( '/^---\n(.*?)\n---\n?(.*)$/s', $body, $m ) ) {
		foreach ( explode( "\n", $m[1] ) as $line ) {
			if ( preg_match( '/^([a-z_]+):\s*(.*)$/', trim( $line ), $kv ) ) {
				$meta[ $kv[1] ] = trim( $kv[2], " \"'" );
			}
		}
		$body = $m[2];
	}
	return array( $meta, ltrim( $body, "\n" ) );
}

/**
 * Pakete gömülü rehber dosyaları (sıralı: önce üst sayfalar).
 *
 * @return array [ [ meta, body, file ] ]
 */
function flxwk_seed_files() {
	$dir   = (string) apply_filters( 'flxwk_seed_dir', FLXWK_DIR . 'content' );
	$files = glob( trailingslashit( $dir ) . '*.md' );
	$out   = array();
	foreach ( $files ? $files : array() as $f ) {
		list( $meta, $body ) = flxwk_parse_front( file_get_contents( $f ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( empty( $meta['title'] ) ) {
			continue;
		}
		$meta['slug'] = $meta['slug'] ?? basename( $f, '.md' );
		$out[]        = array( $meta, $body, $f );
	}
	usort(
		$out,
		function ( $a, $b ) {
			$da = empty( $a[0]['parent'] ) ? 0 : 1;
			$db = empty( $b[0]['parent'] ) ? 0 : 1;
			return $da === $db ? ( (int) ( $a[0]['order'] ?? 0 ) ) - ( (int) ( $b[0]['order'] ?? 0 ) ) : $da - $db;
		}
	);
	return $out;
}

/**
 * Rehberleri aktarır.
 *
 * @param bool $update Elle değiştirilmemiş mevcut sayfalar da güncellensin mi.
 * @return array { created, updated, skipped }
 */
function flxwk_seed_import( $update = true ) {
	flxwk_register_types();
	$stat = array( 'created' => 0, 'updated' => 0, 'skipped' => 0 );

	$sj = trailingslashit( (string) apply_filters( 'flxwk_seed_dir', FLXWK_DIR . 'content' ) ) . 'sections.json';
	if ( is_readable( $sj ) ) {
		foreach ( (array) json_decode( (string) file_get_contents( $sj ), true ) as $i => $s ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( empty( $s['slug'] ) || empty( $s['name'] ) ) {
				continue;
			}
			$t = get_term_by( 'slug', $s['slug'], FLXWK_TAX );
			if ( ! $t ) {
				$n = wp_insert_term( $s['name'], FLXWK_TAX, array( 'slug' => $s['slug'], 'description' => $s['description'] ?? '' ) );
				if ( is_wp_error( $n ) ) {
					continue;
				}
				update_term_meta( $n['term_id'], 'flxwk_order', (int) ( $s['order'] ?? $i ) );
			}
		}
	}

	foreach ( flxwk_seed_files() as $f ) {
		list( $meta, $body ) = $f;
		$path     = empty( $meta['parent'] ) ? $meta['slug'] : $meta['parent'] . '/' . $meta['slug'];
		$existing = get_page_by_path( $path, OBJECT, FLXWK_PT );
		$hash     = md5( $body );
		$args     = array(
			'title'      => $meta['title'],
			'md'         => $body,
			'slug'       => $meta['slug'],
			'parent'     => $meta['parent'] ?? '',
			'excerpt'    => $meta['excerpt'] ?? '',
			'menu_order' => (int) ( $meta['order'] ?? 0 ),
		);
		if ( empty( $meta['parent'] ) && ! empty( $meta['section'] ) ) {
			$args['section'] = $meta['section'];
		}
		if ( $existing ) {
			if ( function_exists( 'flxl_set_post_language' ) ) {
				flxl_set_post_language( $existing->ID, $meta['language'] ?? 'tr' );
			}
			$seeded = get_post_meta( $existing->ID, '_flxwk_seed_hash', true );
			$same   = $seeded && md5( flxwk_md( $existing ) ) === $seeded;
			if ( ! $update || ! $same || $seeded === $hash ) {
				++$stat['skipped'];
				continue;
			}
			$args['id'] = $existing->ID;
			$id         = flxwk_save_page( $args );
			$key        = 'updated';
		} else {
			$args['status'] = 'publish';
			$id             = flxwk_save_page( $args );
			$key            = 'created';
		}
		if ( ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_flxwk_seed_hash', $hash );
			if ( function_exists( 'flxl_set_post_language' ) ) {
				flxl_set_post_language( $id, $meta['language'] ?? 'tr' );
			}
			++$stat[ $key ];
		}
	}
	flxwk_bump();
	return $stat;
}
