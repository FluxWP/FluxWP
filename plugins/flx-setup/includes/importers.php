<?php
/**
 * İçerik türleri (content[].type). Paketler kendi türlerini `flx_preset_importers` filtresiyle ekler.
 *
 * Tür tanımı:
 *  'label'   => görünen ad
 *  'find'    => function( array $item ): int     mevcut kaydın ID'si (yoksa 0). Önce _flxk_key, sonra slug gibi doğal anahtar.
 *  'data'    => function( array $item ): array   uygulanacak hali (yer tutucuları çözülmüş $item gelir), karşılaştırma için
 *  'read'    => function( int $id ): array       mevcut kaydın aynı biçimdeki hali
 *  'save'    => function( array $item, int $id ): int|WP_Error   oluştur ($id = 0) ya da güncelle
 *  'disable' => function( int $id ): void        ön ayar kaldırılınca (silmez, pasife alır)
 *  'url'     => function( int $id ): string      {url:@tür:key} için (isteğe bağlı)
 *  'same'    => function( array $new, array $cur ): bool   isteğe bağlı özel karşılaştırma
 */

defined( 'ABSPATH' ) || exit;

function flxk_importers() {
	static $list = null;
	if ( null === $list || ! did_action( 'init' ) ) {
		$list = (array) apply_filters( 'flx_preset_importers', flxk_core_importers() );
	}
	return $list;
}

/** _flxk_key meta'sı ile kayıt bul. */
function flxk_find_by_key( $post_type, $key ) {
	$q = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'meta_key'       => '_flxk_key', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => (string) $key, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		)
	);
	return $q ? (int) $q[0] : 0;
}

function flxk_core_importers() {
	$t = array();

	/* Sayfa ve yazı */
	$post_type = function ( $item, $default ) {
		return isset( $item['post_type'] ) ? sanitize_key( $item['post_type'] ) : $default;
	};
	$post_like = function ( $default ) use ( $post_type ) {
		return array(
			'label'   => 'page' === $default ? __( 'Page', 'flx-setup' ) : __( 'Post', 'flx-setup' ),
			'find'    => function ( $item ) use ( $post_type, $default ) {
				$pt = $post_type( $item, $default );
				$id = flxk_find_by_key( $pt, $item['key'] );
				if ( ! $id ) {
					$slug = $item['slug'] ?? $item['key'];
					$p    = get_page_by_path( (string) $slug, OBJECT, $pt );
					$id   = $p ? (int) $p->ID : 0;
				}
				return $id;
			},
			'data'    => function ( $item ) use ( $post_type, $default ) {
				return array(
					'title'    => (string) ( $item['title'] ?? '' ),
					'slug'     => (string) ( $item['slug'] ?? $item['key'] ),
					'content'  => flxk_body_to_markup( $item['body'] ?? '' ),
					'excerpt'  => (string) ( $item['excerpt'] ?? '' ),
					'status'   => (string) ( $item['status'] ?? 'publish' ),
					'template' => (string) ( $item['template'] ?? '' ),
					'parent'   => (int) ( $item['parent'] ?? 0 ),
					'image'    => (int) ( $item['image'] ?? 0 ),
					'terms'    => (array) ( $item['terms'] ?? array() ),
					'meta'     => (array) ( $item['meta'] ?? array() ),
				);
			},
			'read'    => function ( $id ) {
				$p = get_post( $id );
				if ( ! $p ) {
					return array();
				}
				return array(
					'title'    => $p->post_title,
					'slug'     => $p->post_name,
					'content'  => $p->post_content,
					'excerpt'  => $p->post_excerpt,
					'status'   => $p->post_status,
					'template' => (string) get_page_template_slug( $p ),
					'parent'   => (int) $p->post_parent,
					'image'    => (int) get_post_thumbnail_id( $p ),
				);
			},
			'same'    => function ( $new, $cur ) {
				unset( $new['terms'], $new['meta'] );
				return flxk_hash( $new ) === flxk_hash( $cur );
			},
			'save'    => function ( $item, $id ) use ( $post_type, $default ) {
				$d   = flxk_importers()[ $default ]['data']( $item );
				$arr = array(
					'ID'           => $id,
					'post_type'    => $post_type( $item, $default ),
					'post_title'   => $d['title'],
					'post_name'    => $d['slug'],
					'post_content' => $d['content'],
					'post_excerpt' => $d['excerpt'],
					'post_status'  => $d['status'],
					'post_parent'  => $d['parent'],
				);
				$new = $id ? wp_update_post( wp_slash( $arr ), true ) : wp_insert_post( wp_slash( $arr ), true );
				if ( is_wp_error( $new ) ) {
					return $new;
				}
				update_post_meta( $new, '_flxk_key', (string) $item['key'] );
				if ( '' !== $d['template'] ) {
					update_post_meta( $new, '_wp_page_template', $d['template'] );
				}
				if ( $d['image'] ) {
					set_post_thumbnail( $new, $d['image'] );
				}
				foreach ( $d['terms'] as $tax => $terms ) {
					if ( taxonomy_exists( $tax ) ) {
						wp_set_object_terms( $new, (array) $terms, $tax );
					}
				}
				foreach ( $d['meta'] as $mk => $mv ) {
					if ( is_string( $mk ) && '_flxk_key' !== $mk ) {
						update_post_meta( $new, $mk, $mv );
					}
				}
				return (int) $new;
			},
			'disable' => function ( $id ) {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'draft',
					)
				);
			},
			'url'     => 'get_permalink',
		);
	};
	$t['page'] = $post_like( 'page' );
	$t['post'] = $post_like( 'post' );

	/* Terim (kategori, etiket, özel taksonomi) */
	$t['term'] = array(
		'label'   => __( 'Term', 'flx-setup' ),
		'find'    => function ( $item ) {
			$tax  = (string) ( $item['taxonomy'] ?? 'category' );
			$term = get_term_by( 'slug', (string) ( $item['slug'] ?? $item['key'] ), $tax );
			return $term ? (int) $term->term_id : 0;
		},
		'data'    => function ( $item ) {
			return array(
				'name'        => (string) ( $item['name'] ?? $item['key'] ),
				'slug'        => (string) ( $item['slug'] ?? $item['key'] ),
				'description' => (string) ( $item['description'] ?? '' ),
			);
		},
		'read'    => function ( $id ) {
			$term = get_term( $id );
			return $term && ! is_wp_error( $term ) ? array(
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
			) : array();
		},
		'save'    => function ( $item, $id ) {
			$tax = (string) ( $item['taxonomy'] ?? 'category' );
			if ( ! taxonomy_exists( $tax ) ) {
				/* translators: %s: taksonomi */
				return new WP_Error( 'tax', sprintf( __( 'Taxonomy not found: %s', 'flx-setup' ), $tax ) );
			}
			$args = array(
				'slug'        => (string) ( $item['slug'] ?? $item['key'] ),
				'description' => (string) ( $item['description'] ?? '' ),
				'parent'      => (int) ( $item['parent'] ?? 0 ),
			);
			$r    = $id ? wp_update_term( $id, $tax, $args + array( 'name' => (string) ( $item['name'] ?? $item['key'] ) ) ) : wp_insert_term( (string) ( $item['name'] ?? $item['key'] ), $tax, $args );
			return is_wp_error( $r ) ? $r : (int) $r['term_id'];
		},
		'disable' => '__return_null',
		'url'     => function ( $id ) {
			$l = get_term_link( (int) $id );
			return is_wp_error( $l ) ? '' : $l;
		},
	);

	/* Gezinme menüsü (blok teması: wp_navigation) */
	$t['navigation'] = array(
		'label'   => __( 'Menu', 'flx-setup' ),
		'find'    => function ( $item ) {
			return flxk_find_by_key( 'wp_navigation', $item['key'] );
		},
		'data'    => function ( $item ) {
			return array(
				'title'   => (string) ( $item['title'] ?? $item['key'] ),
				'content' => isset( $item['items'] ) ? flxk_nav_items_markup( (array) $item['items'] ) : flxk_body_to_markup( $item['body'] ?? '' ),
			);
		},
		'read'    => function ( $id ) {
			$p = get_post( $id );
			return $p ? array(
				'title'   => $p->post_title,
				'content' => $p->post_content,
			) : array();
		},
		'save'    => function ( $item, $id ) {
			$d   = flxk_importers()['navigation']['data']( $item );
			$arr = array(
				'ID'           => $id,
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => $d['title'],
				'post_content' => $d['content'],
			);
			$new = $id ? wp_update_post( wp_slash( $arr ), true ) : wp_insert_post( wp_slash( $arr ), true );
			if ( ! is_wp_error( $new ) ) {
				update_post_meta( $new, '_flxk_key', (string) $item['key'] );
			}
			return $new;
		},
		'disable' => function ( $id ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'draft',
				)
			);
		},
	);

	/* Blok şablonu ve şablon parçası (header, footer…): aktif tema için kullanıcı katmanı */
	foreach ( array(
		'template' => array( 'wp_template', __( 'Template', 'flx-setup' ) ),
		'part'     => array( 'wp_template_part', __( 'Template part', 'flx-setup' ) ),
	) as $type => $def ) {
		list( $pt, $label ) = $def;
		$t[ $type ]         = array(
			'label'   => $label,
			'find'    => function ( $item ) use ( $pt ) {
				$tpl = get_block_template( get_stylesheet() . '//' . $item['key'], $pt );
				return $tpl && 'custom' === $tpl->source && $tpl->wp_id ? (int) $tpl->wp_id : 0;
			},
			'data'    => function ( $item ) use ( $type ) {
				$d = array(
					'title'   => (string) ( $item['title'] ?? $item['key'] ),
					'content' => flxk_body_to_markup( $item['body'] ?? '' ),
				);
				if ( 'part' === $type ) {
					$d['area'] = (string) ( $item['area'] ?? ( in_array( $item['key'], array( 'header', 'footer' ), true ) ? $item['key'] : 'uncategorized' ) );
				}
				return $d;
			},
			'read'    => function ( $id ) use ( $type ) {
				$p = get_post( $id );
				if ( ! $p ) {
					return array();
				}
				$d = array(
					'title'   => $p->post_title,
					'content' => $p->post_content,
				);
				if ( 'part' === $type ) {
					$terms     = wp_get_post_terms( $id, 'wp_template_part_area', array( 'fields' => 'slugs' ) );
					$d['area'] = is_array( $terms ) && $terms ? $terms[0] : 'uncategorized';
				}
				return $d;
			},
			'save'    => function ( $item, $id ) use ( $pt, $type ) {
				$d   = flxk_importers()[ $type ]['data']( $item );
				$arr = array(
					'ID'           => $id,
					'post_type'    => $pt,
					'post_status'  => 'publish',
					'post_name'    => (string) $item['key'],
					'post_title'   => $d['title'],
					'post_content' => $d['content'],
				);
				$new = $id ? wp_update_post( wp_slash( $arr ), true ) : wp_insert_post( wp_slash( $arr ), true );
				if ( is_wp_error( $new ) ) {
					return $new;
				}
				wp_set_post_terms( $new, array( get_stylesheet() ), 'wp_theme' );
				if ( 'part' === $type ) {
					wp_set_post_terms( $new, array( $d['area'] ), 'wp_template_part_area' );
				}
				update_post_meta( $new, '_flxk_key', (string) $item['key'] );
				return (int) $new;
			},
			// Kullanıcı katmanı silinir, temanın kendi dosyası geri gelir.
			'disable' => function ( $id ) {
				wp_delete_post( $id, true );
			},
		);
	}

	/* Global stil (theme.json biçimi, kullanıcı katmanı). Mevcut stillerle derin birleşir. */
	$t['styles'] = array(
		'label'   => __( 'Global styles', 'flx-setup' ),
		'find'    => function () {
			return class_exists( 'WP_Theme_JSON_Resolver' ) ? (int) WP_Theme_JSON_Resolver::get_user_global_styles_post_id() : 0;
		},
		'data'    => function ( $item ) {
			return (array) ( $item['data'] ?? array() );
		},
		'read'    => function ( $id ) {
			$p = get_post( $id );
			$j = $p ? json_decode( $p->post_content, true ) : array();
			if ( ! is_array( $j ) ) {
				return array();
			}
			unset( $j['isGlobalStylesUserThemeJSON'], $j['version'] );
			return $j;
		},
		'same'    => function ( $new, $cur ) {
			return flxk_hash( flxk_merge( $cur, $new ) ) === flxk_hash( $cur );
		},
		'save'    => function ( $item, $id ) {
			$id  = $id ? $id : flxk_importers()['styles']['find']( $item );
			$cur = $id ? flxk_importers()['styles']['read']( $id ) : array();
			$new = empty( $item['__replace'] ) ? flxk_merge( $cur, (array) ( $item['data'] ?? array() ) ) : (array) ( $item['data'] ?? array() ); // Geri almada birebir eski hal.
			$new = array(
				'version'                     => 3,
				'isGlobalStylesUserThemeJSON' => true,
			) + $new;
			$r   = wp_update_post(
				array(
					'ID'           => $id,
					'post_content' => wp_slash( wp_json_encode( $new ) ),
				),
				true
			);
			if ( class_exists( 'WP_Theme_JSON_Resolver' ) ) {
				WP_Theme_JSON_Resolver::clean_cached_data();
			}
			return is_wp_error( $r ) ? $r : (int) $id;
		},
		'disable' => '__return_null',
	);

	/* WooCommerce ürünü (demo veriler için) */
	if ( class_exists( 'WooCommerce' ) ) {
		$t['product'] = array(
			'label'   => __( 'Product', 'flx-setup' ),
			'find'    => function ( $item ) {
				$id = flxk_find_by_key( 'product', $item['key'] );
				if ( ! $id && ! empty( $item['sku'] ) && function_exists( 'wc_get_product_id_by_sku' ) ) {
					$id = (int) wc_get_product_id_by_sku( $item['sku'] );
				}
				return $id;
			},
			'data'    => function ( $item ) {
				return array(
					'name'        => (string) ( $item['name'] ?? $item['key'] ),
					'price'       => (string) ( $item['price'] ?? '' ),
					'description' => flxk_body_to_markup( $item['body'] ?? ( $item['description'] ?? '' ) ),
					'short'       => (string) ( $item['short'] ?? '' ),
					'sku'         => (string) ( $item['sku'] ?? '' ),
					'image'       => (int) ( $item['image'] ?? 0 ),
				);
			},
			'read'    => function ( $id ) {
				$p = wc_get_product( $id );
				return $p ? array(
					'name'        => $p->get_name(),
					'price'       => (string) $p->get_regular_price(),
					'description' => $p->get_description(),
					'short'       => $p->get_short_description(),
					'sku'         => $p->get_sku(),
					'image'       => (int) $p->get_image_id(),
				) : array();
			},
			'save'    => function ( $item, $id ) {
				$d = flxk_importers()['product']['data']( $item );
				$p = $id ? wc_get_product( $id ) : new WC_Product_Simple();
				if ( ! $p ) {
					return new WP_Error( 'product', __( 'Product not found.', 'flx-setup' ) );
				}
				$p->set_name( $d['name'] );
				$p->set_regular_price( $d['price'] );
				$p->set_description( $d['description'] );
				$p->set_short_description( $d['short'] );
				if ( $d['sku'] ) {
					try {
						$p->set_sku( $d['sku'] );
					} catch ( Exception $e ) {
						unset( $e );
					}
				}
				if ( $d['image'] ) {
					$p->set_image_id( $d['image'] );
				}
				$p->set_status( 'publish' );
				$new = $p->save();
				update_post_meta( $new, '_flxk_key', (string) $item['key'] );
				if ( ! empty( $item['categories'] ) ) {
					wp_set_object_terms( $new, (array) $item['categories'], 'product_cat' );
				}
				return (int) $new;
			},
			'disable' => function ( $id ) {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'draft',
					)
				);
			},
			'url'     => 'get_permalink',
		);
	}

	return $t;
}

/** Basit menü listesi → core/navigation-link blokları. Öğe: { label, url } ya da { label, page: "@page:key" }, isteğe bağlı children. */
function flxk_nav_items_markup( array $items ) {
	$out = array();
	foreach ( $items as $it ) {
		$label = (string) ( $it['label'] ?? '' );
		$attrs = array( 'label' => $label );
		if ( ! empty( $it['page'] ) ) {
			$attrs['type'] = 'page';
			$attrs['id']   = (int) $it['page'];
			$attrs['url']  = get_permalink( (int) $it['page'] ) ? get_permalink( (int) $it['page'] ) : '#';
			$attrs['kind'] = 'post-type';
		} else {
			$attrs['url']  = (string) ( $it['url'] ?? '#' );
			$attrs['kind'] = 'custom';
		}
		if ( ! empty( $it['children'] ) ) {
			$out[] = '<!-- wp:navigation-submenu ' . wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' -->' . "\n" . flxk_nav_items_markup( (array) $it['children'] ) . "\n" . '<!-- /wp:navigation-submenu -->';
		} else {
			$out[] = '<!-- wp:navigation-link ' . wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' /-->';
		}
	}
	return implode( "\n", $out );
}
