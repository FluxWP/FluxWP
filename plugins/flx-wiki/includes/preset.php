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
	$p = get_page_by_path( $parent, OBJECT, FLXWK_PT ) ?: flxwk_find_page( $parent );
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
				'name'        => (string) ( $item['translations'][ function_exists( 'flxl_default_language' ) ? flxl_default_language() : 'tr' ]['name'] ?? $item['name'] ?? $item['key'] ),
				'slug'        => sanitize_title( (string) ( $item['slug'] ?? $item['key'] ) ),
				'description' => (string) ( $item['translations'][ function_exists( 'flxl_default_language' ) ? flxl_default_language() : 'tr' ]['description'] ?? $item['description'] ?? '' ),
				'order'       => (int) ( $item['order'] ?? 0 ),
				'translations' => (array) ( $item['translations'] ?? array() ),
			);
		},
		'read'    => function ( $id ) {
			$term = get_term( (int) $id, FLXWK_TAX );
			return $term && ! is_wp_error( $term ) ? array(
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'translations' => (array) get_term_meta( $id, '_flxwk_translations', true ),
				'order'       => (int) get_term_meta( $term->term_id, 'flxwk_order', true ),
			) : array();
		},
		'save'    => function ( $item, $id ) {
			flxwk_register_types();
			$name = (string) ( $item['translations'][ function_exists( 'flxl_default_language' ) ? flxl_default_language() : 'tr' ]['name'] ?? $item['name'] ?? $item['key'] );
			$args = array(
				'slug'        => sanitize_title( (string) ( $item['slug'] ?? $item['key'] ) ),
				'description' => (string) ( $item['translations'][ function_exists( 'flxl_default_language' ) ? flxl_default_language() : 'tr' ]['description'] ?? $item['description'] ?? '' ),
			);
			$r    = $id ? wp_update_term( (int) $id, FLXWK_TAX, $args + array( 'name' => $name ) ) : wp_insert_term( $name, FLXWK_TAX, $args );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			update_term_meta( (int) $r['term_id'], 'flxwk_order', (int) ( $item['order'] ?? 0 ) );
			$translations = (array) ( $item['translations'] ?? array() );
			update_term_meta( $r['term_id'], '_flxwk_translations', $translations );
			foreach ( $translations as $code => $text ) {
				update_term_meta( $r['term_id'], '_flxl_name_' . sanitize_key( $code ), sanitize_text_field( $text['name'] ?? '' ) );
				update_term_meta( $r['term_id'], '_flxl_desc_' . sanitize_key( $code ), sanitize_textarea_field( $text['description'] ?? '' ) );
			}
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
				$id = flxwk_preset_key( (string) $item['key'] );
				if ( $id ) {
					return $id;
				}
			}
			$p = get_page_by_path( flxwk_preset_path( $item ), OBJECT, FLXWK_PT );
			return $p ? (int) $p->ID : 0;
		},
		'data'    => function ( $item ) {
			return array(
				'language' => (string) ( $item['language'] ?? '' ),
				'translation_of' => (string) ( $item['translation_of'] ?? '' ),
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
				'language' => (string) get_post_meta( $id, '_flxwk_language', true ),
				'translation_of' => (string) get_post_meta( $id, '_flxwk_translation_of', true ),
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
				$language = sanitize_key( $item['language'] ?? '' );
				$source = (string) ( $item['translation_of'] ?? '' );
				update_post_meta( $new, '_flxwk_language', $language );
				update_post_meta( $new, '_flxwk_translation_of', $source );
				if ( $language && function_exists( 'flxl_set_post_language' ) ) {
					flxl_set_post_language( $new, $language );
					$original = $source ? flxwk_preset_key( $source ) : 0;
					if ( $original ) {
						flxl_link_translations( array( flxl_post_language( $original ) => $original, $language => $new ) );
					}
				}
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

/** Preset keys are global; imports must find records across all languages. */
function flxwk_preset_key( $key ) {
	$ids = get_posts( array(
		'post_type' => FLXWK_PT,
		'post_status' => 'any',
		'meta_key' => '_flxk_key',
		'meta_value' => $key,
		'fields' => 'ids',
		'posts_per_page' => 1,
		'flxl_lang' => 'all',
	) );
	return $ids ? (int) $ids[0] : 0;
}
