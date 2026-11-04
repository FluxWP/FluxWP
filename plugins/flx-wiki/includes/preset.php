<?php
/**
 * Flux Setup ön ayar desteği: ön ayar eklentileri wiki bölümü ve sayfası yazabilir.
 *
 * content[] / migrations[].content[] içinde:
 *   { "type": "wiki_section", "key": "kurulum", "slug": "kurulum", "name": "Kurulum", "description": "…", "order": 1 }
 *   { "type": "wiki", "key": "updater", "slug": "guncelleme-sunucusu", "title": "…", "parent": "kurulum-rehberi",
 *     "section": "kurulum", "order": 3, "excerpt": "…", "md": { "$file": "wiki/guncelleme-sunucusu.md" } }
 *
 * `parent` üst sayfanın kısa adı ya da yolu; üst sayfa listede önce gelmeli.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_preset_importers', 'flxwk_preset_importers' );

/** Ön ayar sayfasının wiki yolu (üst/alt). */
function flxwk_preset_path( array $item ) {
	$slug   = sanitize_title( (string) ( $item['slug'] ?? $item['key'] ?? '' ) );
	$parent = trim( (string) ( $item['parent'] ?? '' ), '/' );
	if ( '' === $parent ) {
		return $slug;
	}
	$p = flxwk_find_page( $parent );
	return ( $p ? get_page_uri( $p ) : $parent ) . '/' . $slug;
}

function flxwk_preset_importers( $t ) {
	$t = (array) $t;

	$t['wiki_section'] = array(
		'label'   => __( 'Wiki bölümü', 'flx-wiki' ),
		'find'    => function ( $item ) {
			$term = get_term_by( 'slug', sanitize_title( (string) ( $item['slug'] ?? $item['key'] ) ), FLXWK_TAX );
			return $term ? (int) $term->term_id : 0;
		},
		'data'    => function ( $item ) {
			return array(
				'name'        => (string) ( $item['name'] ?? $item['key'] ),
				'slug'        => sanitize_title( (string) ( $item['slug'] ?? $item['key'] ) ),
				'description' => (string) ( $item['description'] ?? '' ),
				'order'       => (int) ( $item['order'] ?? 0 ),
			);
		},
		'read'    => function ( $id ) {
			$term = get_term( (int) $id, FLXWK_TAX );
			return $term && ! is_wp_error( $term ) ? array(
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'order'       => (int) get_term_meta( $term->term_id, 'flxwk_order', true ),
			) : array();
		},
		'save'    => function ( $item, $id ) {
			flxwk_register_types();
			$name = (string) ( $item['name'] ?? $item['key'] );
			$args = array(
				'slug'        => sanitize_title( (string) ( $item['slug'] ?? $item['key'] ) ),
				'description' => (string) ( $item['description'] ?? '' ),
			);
			$r    = $id ? wp_update_term( (int) $id, FLXWK_TAX, $args + array( 'name' => $name ) ) : wp_insert_term( $name, FLXWK_TAX, $args );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			update_term_meta( (int) $r['term_id'], 'flxwk_order', (int) ( $item['order'] ?? 0 ) );
			flxwk_bump();
			return (int) $r['term_id'];
		},
		'disable' => '__return_null',
		'url'     => function ( $id ) {
			$l = get_term_link( (int) $id, FLXWK_TAX );
			return is_wp_error( $l ) ? '' : $l;
		},
	);

	$t['wiki'] = array(
		'label'   => __( 'Wiki sayfası', 'flx-wiki' ),
		'find'    => function ( $item ) {
			if ( function_exists( 'flxk_find_by_key' ) ) {
				$id = flxk_find_by_key( FLXWK_PT, (string) $item['key'] );
				if ( $id ) {
					return $id;
				}
			}
			$p = get_page_by_path( flxwk_preset_path( $item ), OBJECT, FLXWK_PT );
			return $p ? (int) $p->ID : 0;
		},
		'data'    => function ( $item ) {
			return array(
				'title'   => (string) ( $item['title'] ?? $item['key'] ),
				'slug'    => sanitize_title( (string) ( $item['slug'] ?? $item['key'] ) ),
				'md'      => str_replace( "\r\n", "\n", (string) ( $item['md'] ?? '' ) ),
				'excerpt' => (string) ( $item['excerpt'] ?? '' ),
				'parent'  => trim( (string) ( $item['parent'] ?? '' ), '/' ),
				'section' => (string) ( $item['section'] ?? '' ),
				'order'   => (int) ( $item['order'] ?? 0 ),
				'status'  => (string) ( $item['status'] ?? 'publish' ),
			);
		},
		'read'    => function ( $id ) {
			$p = get_post( (int) $id );
			if ( ! $p || FLXWK_PT !== $p->post_type ) {
				return array();
			}
			$par   = $p->post_parent ? get_post( $p->post_parent ) : null;
			$terms = get_the_terms( $p, FLXWK_TAX );
			return array(
				'title'   => $p->post_title,
				'slug'    => $p->post_name,
				'md'      => flxwk_md( $p ),
				'excerpt' => $p->post_excerpt,
				'parent'  => $par ? $par->post_name : '',
				'section' => $terms && ! is_wp_error( $terms ) ? $terms[0]->slug : '',
				'order'   => (int) $p->menu_order,
				'status'  => $p->post_status,
			);
		},
		'same'    => function ( $new, $cur ) {
			$new['parent'] = basename( (string) $new['parent'] );
			if ( '' !== $new['parent'] ) {
				unset( $new['section'], $cur['section'] ); // Alt sayfa bölümü üstten alır.
			}
			return md5( wp_json_encode( $new ) ) === md5( wp_json_encode( $cur ) );
		},
		'save'    => function ( $item, $id ) {
			flxwk_register_types();
			$d    = array(
				'title'      => (string) ( $item['title'] ?? $item['key'] ),
				'slug'       => sanitize_title( (string) ( $item['slug'] ?? $item['key'] ) ),
				'md'         => str_replace( "\r\n", "\n", (string) ( $item['md'] ?? '' ) ),
				'excerpt'    => (string) ( $item['excerpt'] ?? '' ),
				'parent'     => trim( (string) ( $item['parent'] ?? '' ), '/' ),
				'menu_order' => (int) ( $item['order'] ?? 0 ),
				'status'     => (string) ( $item['status'] ?? 'publish' ),
			);
			if ( '' === $d['parent'] && ! empty( $item['section'] ) ) {
				$d['section'] = (string) $item['section'];
			}
			if ( $id ) {
				$d['id'] = (int) $id;
			}
			$new = flxwk_save_page( $d );
			if ( ! is_wp_error( $new ) ) {
				update_post_meta( $new, '_flxk_key', (string) $item['key'] );
			}
			return $new;
		},
		'disable' => function ( $id ) {
			wp_update_post(
				array(
					'ID'          => (int) $id,
					'post_status' => 'draft',
				)
			);
			flxwk_bump();
		},
		'url'     => 'get_permalink',
	);

	return $t;
}
