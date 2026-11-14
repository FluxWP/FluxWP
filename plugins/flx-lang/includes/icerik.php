<?php
/**
 * İçeriğin dili ve çevirileri.
 *
 * - Dil: gizli taksonomi flxl_lang (terim = dil kodu). Terimi olmayan içerik varsayılan dildedir.
 * - Çeviri grubu: _flxl_group post meta (grubun ilk içeriğinin ID'si). Hızlı erişim için flxl_index seçeneği.
 * - Ön yüz sorguları istek diline göre süzülür; çevirisi olmayan içerik (ayar açıksa) varsayılan dilde gösterilir.
 * - Ortak sayfalar (sepet, ödeme, hesabım): dili yok, her dilde aynı sayfa, arayüzü istek dilinde.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

const FLXL_TAX = 'flxl_lang';

function flxl_register_taxonomy() {
	register_taxonomy(
		FLXL_TAX,
		flxl_post_types(),
		array(
			'public'             => false,
			'show_ui'            => false,
			'show_in_rest'       => false,
			'hierarchical'       => false,
			'rewrite'            => false,
			'query_var'          => false,
			'show_admin_column'  => false,
			'label'              => 'Flux Lang',
		)
	);
}
// Tema içerik türlerini (proje, etkinlik) init'te kaydeder; taksonomi onlardan sonra bağlanır.
add_action( 'init', 'flxl_register_taxonomy', 99 );

function flxl_ensure_terms() {
	foreach ( array_keys( flxl_languages( true ) ) as $code ) {
		if ( ! term_exists( $code, FLXL_TAX ) ) {
			wp_insert_term( $code, FLXL_TAX, array( 'slug' => $code ) );
		}
	}
}
add_action( 'update_option_flxl_settings', 'flxl_ensure_terms', 20 );

/* ---------------------------------------------------------------------------
 * Dil
 * ------------------------------------------------------------------------- */

/**
 * İçeriğin dili. Çevrilmeyen tür ya da ortak sayfa için ''.
 *
 * @param int|WP_Post $post
 */
function flxl_post_language( $post ) {
	$post = get_post( $post );
	if ( ! $post || ! flxl_is_translatable_type( $post->post_type ) || flxl_is_shared( $post->ID ) ) {
		return '';
	}
	$terms = get_the_terms( $post->ID, FLXL_TAX );
	if ( is_array( $terms ) ) {
		foreach ( $terms as $t ) {
			if ( flxl_is_language( $t->slug ) ) {
				return $t->slug;
			}
		}
	}
	return flxl_default_language();
}

/**
 * İçeriğin dilini ayarlar.
 */
function flxl_set_post_language( $post_id, $code ) {
	$post_id = (int) $post_id;
	if ( ! flxl_is_language( $code ) && ! isset( flxl_languages( true )[ $code ] ) ) {
		return false;
	}
	if ( ! term_exists( $code, FLXL_TAX ) ) {
		wp_insert_term( $code, FLXL_TAX, array( 'slug' => $code ) );
	}
	wp_set_object_terms( $post_id, array( $code ), FLXL_TAX, false );
	flxl_index_flush();
	do_action( 'flxl_post_language_set', $post_id, $code );
	return true;
}

/* ---------------------------------------------------------------------------
 * Çeviri grupları
 * ------------------------------------------------------------------------- */

/**
 * Dizin: g = grup => [dil => ID], p = ID => grup. Çöp/otomatik taslak hariç.
 */
function flxl_index() {
	static $cache = null;
	if ( null !== $cache && ! isset( $GLOBALS['flxl_index_dirty'] ) ) {
		return $cache;
	}
	unset( $GLOBALS['flxl_index_dirty'] );
	$idx = get_option( 'flxl_index' );
	if ( ! is_array( $idx ) || ! isset( $idx['g'], $idx['p'] ) ) {
		$idx = flxl_index_build();
		update_option( 'flxl_index', $idx, true );
	}
	$cache = $idx;
	return $cache;
}

function flxl_index_build() {
	global $wpdb;
	$def  = flxl_default_language();
	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT m.post_id, m.meta_value AS grp, t.slug AS lang
			 FROM {$wpdb->postmeta} m
			 JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_status NOT IN ('trash','auto-draft','inherit')
			 LEFT JOIN {$wpdb->term_relationships} r ON r.object_id = m.post_id
			   AND r.term_taxonomy_id IN (SELECT tt.term_taxonomy_id FROM {$wpdb->term_taxonomy} tt WHERE tt.taxonomy = %s)
			 LEFT JOIN {$wpdb->term_taxonomy} x ON x.term_taxonomy_id = r.term_taxonomy_id
			 LEFT JOIN {$wpdb->terms} t ON t.term_id = x.term_id
			 WHERE m.meta_key = '_flxl_group'
			 ORDER BY m.post_id ASC",
			FLXL_TAX
		)
	);
	$idx = array( 'g' => array(), 'p' => array() );
	foreach ( (array) $rows as $r ) {
		$g    = (int) $r->grp;
		$lang = $r->lang ? $r->lang : $def;
		if ( $g <= 0 || isset( $idx['g'][ $g ][ $lang ] ) ) {
			continue; // Aynı grupta aynı dilde ikinci içerik sayılmaz.
		}
		$idx['g'][ $g ][ $lang ]          = (int) $r->post_id;
		$idx['p'][ (int) $r->post_id ] = $g;
	}
	foreach ( $idx['g'] as $g => $m ) {
		if ( count( $m ) < 2 ) {
			// Tek üyeli grup çeviri sayılmaz.
			foreach ( $m as $id ) {
				unset( $idx['p'][ $id ] );
			}
			unset( $idx['g'][ $g ] );
		}
	}
	return $idx;
}

function flxl_index_flush() {
	delete_option( 'flxl_index' );
	$GLOBALS['flxl_index_dirty'] = true;
}

foreach ( array( 'deleted_post', 'trashed_post', 'untrashed_post' ) as $flxl_hook ) {
	add_action(
		$flxl_hook,
		function ( $id ) {
			if ( get_post_meta( $id, '_flxl_group', true ) || doing_action( 'deleted_post' ) ) {
				flxl_index_flush();
			}
		}
	);
}
add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( $new !== $old && ( 'auto-draft' === $old || 'trash' === $new ) && get_post_meta( $post->ID, '_flxl_group', true ) ) {
			flxl_index_flush();
		}
	},
	10,
	3
);

/**
 * İçeriğin çevirileri: dil => ID (kendisi dahil).
 */
function flxl_get_translations( $post_id ) {
	$post_id = (int) $post_id;
	$idx     = flxl_index();
	if ( isset( $idx['p'][ $post_id ] ) ) {
		$out = $idx['g'][ $idx['p'][ $post_id ] ];
	} else {
		$lang = flxl_post_language( $post_id );
		$out  = $lang ? array( $lang => $post_id ) : array();
	}
	$order = array();
	foreach ( flxl_language_codes() as $c ) {
		if ( isset( $out[ $c ] ) ) {
			$order[ $c ] = $out[ $c ];
		}
	}
	return $order + $out;
}

/**
 * Verilen dildeki çeviri ID'si ya da 0.
 *
 * @param bool $published Yalnız yayında olan.
 */
function flxl_get_translation( $post_id, $code, $published = false ) {
	$tr = flxl_get_translations( $post_id );
	$id = (int) ( $tr[ $code ] ?? 0 );
	if ( $id && $published && 'publish' !== get_post_status( $id ) ) {
		return 0;
	}
	return $id;
}

/**
 * İçerikleri birbirinin çevirisi yapar. $map = dil => ID.
 */
function flxl_link_translations( array $map ) {
	$ids = array_filter( array_map( 'intval', $map ) );
	if ( count( $ids ) < 1 ) {
		return false;
	}
	$types = array_unique( array_map( 'get_post_type', $ids ) );
	if ( count( $types ) > 1 || ! flxl_is_translatable_type( (string) reset( $types ) ) ) {
		return false; // Çeviriler aynı ve çevrilebilir türde olmalı.
	}
	// Var olan grubu koru (birinde grup varsa onu kullan).
	$group = 0;
	foreach ( $ids as $id ) {
		$g = (int) get_post_meta( $id, '_flxl_group', true );
		if ( $g ) {
			$group = $group ? min( $group, $g ) : $g;
		}
	}
	if ( ! $group ) {
		$group = min( $ids );
	}
	// Grubun diğer üyeleri de aynı grupta kalsın.
	foreach ( flxl_raw_group_members( $group ) as $mid ) {
		if ( ! in_array( $mid, $ids, true ) ) {
			$ids[ 'x' . $mid ] = $mid;
		}
	}
	foreach ( $map as $code => $id ) {
		if ( $id && flxl_is_language( $code ) && flxl_post_language( $id ) !== $code ) {
			flxl_set_post_language( $id, $code );
		}
	}
	foreach ( $ids as $id ) {
		update_post_meta( $id, '_flxl_group', $group );
	}
	flxl_index_flush();
	return $group;
}

function flxl_raw_group_members( $group ) {
	global $wpdb;
	return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_flxl_group' AND meta_value = %s", (string) (int) $group ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * İçeriği çeviri grubundan çıkarır.
 */
function flxl_unlink_translation( $post_id ) {
	delete_post_meta( (int) $post_id, '_flxl_group' );
	flxl_index_flush();
}

/* ---------------------------------------------------------------------------
 * Ortak sayfalar
 * ------------------------------------------------------------------------- */

/**
 * Dili olmayan, her dilde aynı adreste (önekli) açılan sayfalar: WooCommerce sepet/ödeme/hesabım.
 */
function flxl_shared_ids() {
	static $ids = null;
	if ( null === $ids ) {
		$ids = array();
		foreach ( array( 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id' ) as $o ) {
			$v = (int) flxl_raw( function () use ( $o ) {
				return get_option( $o );
			} );
			if ( $v > 0 ) {
				$ids[] = $v;
			}
		}
		$ids = array_map( 'intval', (array) apply_filters( 'flxl_shared_post_ids', $ids ) );
	}
	return $ids;
}

function flxl_is_shared( $post_id ) {
	return in_array( (int) $post_id, flxl_shared_ids(), true ) && ! isset( flxl_index()['p'][ (int) $post_id ] );
}

/* ---------------------------------------------------------------------------
 * Sorgu süzme
 * ------------------------------------------------------------------------- */

/**
 * Varsayılan dilde olup verilen dilde çevirisi olan içerikler (yedek gösterimde tekrar etmesin).
 */
function flxl_default_ids_translated_to( $code ) {
	$def = flxl_default_language();
	$out = array();
	foreach ( flxl_index()['g'] as $m ) {
		if ( isset( $m[ $code ], $m[ $def ] ) && 'publish' === get_post_status( $m[ $code ] ) ) {
			$out[] = (int) $m[ $def ];
		}
	}
	return $out;
}

/**
 * Bir dil için tax_query parçası (+ hariç tutulacak ID'ler).
 *
 * @return array{0: array, 1: int[]}
 */
function flxl_lang_query_parts( $code, $fallback = null ) {
	$def      = flxl_default_language();
	$fallback = null === $fallback ? flxl_fallback() : $fallback;
	$others   = array_values( array_diff( array_keys( flxl_languages( true ) ), array( $def ) ) );
	if ( $code === $def ) {
		if ( ! $others ) {
			return array( array(), array() );
		}
		return array(
			array( 'taxonomy' => FLXL_TAX, 'field' => 'slug', 'terms' => $others, 'operator' => 'NOT IN' ),
			array(),
		);
	}
	$mine = array( 'taxonomy' => FLXL_TAX, 'field' => 'slug', 'terms' => array( $code ), 'operator' => 'IN' );
	if ( ! $fallback ) {
		return array( $mine, array() );
	}
	return array(
		array(
			'relation' => 'OR',
			$mine,
			array( 'taxonomy' => FLXL_TAX, 'field' => 'slug', 'terms' => $others, 'operator' => 'NOT IN' ),
		),
		flxl_default_ids_translated_to( $code ),
	);
}

/**
 * Sorgudaki türlerden en az biri çevrilebilir mi.
 */
function flxl_query_has_translatable( WP_Query $q ) {
	$pt = $q->get( 'post_type' );
	if ( empty( $pt ) ) {
		if ( $q->is_search() ) {
			return true;
		}
		if ( $q->is_tax() || $q->is_category() || $q->is_tag() ) {
			return true;
		}
		$pt = array( 'post' );
	}
	if ( 'any' === $pt ) {
		return true;
	}
	return (bool) array_intersect( (array) $pt, flxl_post_types() );
}

function flxl_pre_get_posts( WP_Query $q ) {
	$lang = $q->get( 'flxl_lang' );
	if ( 'all' === $lang || $q->get( 'flxl_skip' ) ) {
		return;
	}
	// Sorguda dil açıkça istendiyse (REST ?lang=, MCP, yönetim süzgeci) yedek gösterim yok.
	$strict = (bool) $lang && ! $q->get( 'flxl_fallback' );
	if ( ! $lang ) {
		if ( flxl_is_backend() ) {
			return; // Yönetimde liste filtresi admin.php'de.
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! flxl_is_explicit() ) {
			return;
		}
		// Tekil içerik çözümü süzülmez; dil uyuşmazlığı template_redirect'te yönlendirilir.
		// Yazılar sayfası (page_for_posts) tekil değil, yazı listesidir: süzülür.
		$posts_page = $q->is_main_query() && $q->is_posts_page;
		foreach ( $posts_page ? array() : array( 'p', 'page_id', 'name', 'pagename', 'attachment', 'attachment_id', 'post__in', 'preview', 'subpost', 'subpost_id' ) as $k ) {
			if ( $q->get( $k ) ) {
				return;
			}
		}
		if ( $q->is_main_query() && ( $q->is_singular() || $q->is_404() ) ) {
			return;
		}
		$lang = flxl_current_language();
	}
	if ( ! flxl_is_language( $lang ) || ! flxl_query_has_translatable( $q ) ) {
		return;
	}
	list( $clause, $exclude ) = flxl_lang_query_parts( $lang, $strict ? false : null );
	if ( $clause ) {
		$tq = $q->get( 'tax_query' );
		$q->set( 'tax_query', $tq ? array( 'relation' => 'AND', $tq, $clause ) : array( $clause ) );
	}
	if ( $exclude ) {
		$q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), $exclude ) );
	}
}
add_action( 'pre_get_posts', 'flxl_pre_get_posts', 20 );

/**
 * Önceki/sonraki yazı bağlantıları da aynı dilde kalsın.
 */
function flxl_adjacent_where( $where, $in_same_term = false, $excluded = array(), $taxonomy = '', $post = null ) {
	global $wpdb;
	if ( flxl_is_backend() || ! $post || ! flxl_is_translatable_type( $post->post_type ) ) {
		return $where;
	}
	$lang = flxl_post_language( $post );
	$def  = flxl_default_language();
	$sub  = "SELECT r.object_id FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = '" . FLXL_TAX . "'";
	if ( $lang === $def ) {
		return $where . $wpdb->prepare( " AND p.ID NOT IN ( $sub AND t.slug <> %s )", $def ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	return $where . $wpdb->prepare( " AND p.ID IN ( $sub AND t.slug = %s )", $lang ); // phpcs:ignore WordPress.DB.PreparedSQL
}
add_filter( 'get_previous_post_where', 'flxl_adjacent_where', 10, 5 );
add_filter( 'get_next_post_where', 'flxl_adjacent_where', 10, 5 );

/* ---------------------------------------------------------------------------
 * Ana sayfa, yazılar sayfası ve WooCommerce sayfaları: dildeki karşılığı
 * ------------------------------------------------------------------------- */

function flxl_page_option_filter( $value ) {
	$cur = flxl_current_language();
	if ( ! $value || $cur === flxl_default_language() || flxl_is_backend() || $GLOBALS['flxl_state']['raw'] > 0 ) {
		return $value;
	}
	$tr = flxl_get_translation( (int) $value, $cur, true );
	return $tr ? $tr : $value;
}
foreach ( array( 'page_on_front', 'page_for_posts', 'woocommerce_shop_page_id', 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id', 'woocommerce_terms_page_id', 'wp_page_for_privacy_policy' ) as $flxl_opt ) {
	add_filter( 'option_' . $flxl_opt, 'flxl_page_option_filter' );
}

/**
 * Sayfa sitenin (herhangi bir dildeki) ana sayfası mı; öyleyse dili.
 */
function flxl_front_page_language( $post_id ) {
	$front = (int) flxl_raw( function () {
		return 'page' === get_option( 'show_on_front' ) ? get_option( 'page_on_front' ) : 0;
	} );
	if ( ! $front ) {
		return '';
	}
	foreach ( flxl_get_translations( $front ) as $code => $id ) {
		if ( (int) $id === (int) $post_id ) {
			return $code;
		}
	}
	return (int) $post_id === $front ? flxl_default_language() : '';
}

/* ---------------------------------------------------------------------------
 * Kalıcı bağlantılar
 * ------------------------------------------------------------------------- */

/**
 * Kanonik hesapta yedek gösterim kapalı (içerik kendi dilinin adresini alır).
 */
function flxl_strict_links( callable $fn ) {
	$GLOBALS['flxl_state']['strict'] = ( $GLOBALS['flxl_state']['strict'] ?? 0 ) + 1;
	try {
		return $fn();
	} finally {
		$GLOBALS['flxl_state']['strict']--;
	}
}

/**
 * İçeriğin bu istekte kullanılacak dili (adres öneki için).
 */
function flxl_link_language( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$cur = flxl_current_language();
	if ( ! flxl_is_translatable_type( $post->post_type ) || flxl_is_shared( $post->ID ) ) {
		return flxl_is_backend() ? '' : $cur; // Dilsiz: istek dilinde kalır.
	}
	$lang = flxl_post_language( $post );
	$def  = flxl_default_language();
	// Yedek gösterim: İngilizce sayfada çevirisi olmayan Türkçe içerik /en/ altında açılır (arayüz İngilizce kalır).
	if ( $lang === $def && $cur !== $def && flxl_fallback() && empty( $GLOBALS['flxl_state']['strict'] ) && ! flxl_is_backend()
		&& ! flxl_get_translation( $post->ID, $cur, true ) ) {
		return $cur;
	}
	return $lang;
}

function flxl_filter_permalink( $url, $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return $url;
	}
	if ( 'page' === $post->post_type ) {
		$front = flxl_front_page_language( $post->ID );
		if ( '' !== $front ) {
			return flxl_url_for_language( trailingslashit( flxl_root() ), $front );
		}
	}
	$lang = flxl_link_language( $post );
	return '' === $lang ? $url : flxl_url_for_language( $url, $lang );
}
add_filter( 'post_link', 'flxl_filter_permalink', 20, 2 );
add_filter( 'page_link', 'flxl_filter_permalink', 20, 2 );
add_filter( 'post_type_link', 'flxl_filter_permalink', 20, 2 );

/**
 * Kanonik adres her zaman içeriğin kendi dilinde (yedek gösterimde Türkçe adres).
 */
add_filter(
	'get_canonical_url',
	function ( $url, $post ) {
		if ( ! flxl_is_translatable_type( $post->post_type ) || flxl_is_shared( $post->ID ) ) {
			return $url;
		}
		$canon = flxl_strict_links(
			function () use ( $post ) {
				return get_permalink( $post );
			}
		);
		$page = (int) get_query_var( 'page' );
		if ( $page > 1 ) {
			$canon = trailingslashit( $canon ) . user_trailingslashit( (string) $page, 'single_paged' );
		}
		return $canon;
	},
	20,
	2
);

/* ---------------------------------------------------------------------------
 * Yanlış dildeki adres → doğru adres
 * ------------------------------------------------------------------------- */

add_action(
	'template_redirect',
	function () {
		if ( is_preview() || is_feed() || is_robots() || is_trackback() || ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || ! flxl_is_translatable_type( $post->post_type ) || flxl_is_shared( $post->ID ) ) {
			return;
		}
		$cur  = flxl_current_language();
		$lang = flxl_post_language( $post );
		if ( $lang === $cur ) {
			return;
		}
		// Önek yoksa (varsayılan dil adresi) içerik kendi adresine; önek varsa o dildeki çevirisine.
		$tr = flxl_is_explicit() ? flxl_get_translation( $post->ID, $cur, true ) : 0;
		if ( $tr && (int) $tr !== (int) $post->ID ) {
			wp_safe_redirect( get_permalink( $tr ), 301 );
			exit;
		}
		if ( $lang === flxl_default_language() && flxl_fallback() && flxl_is_explicit() ) {
			return; // Yedek gösterim (kanonik Türkçe adres).
		}
		$target = flxl_strict_links(
			function () use ( $post ) {
				return get_permalink( $post );
			}
		);
		if ( $target && untrailingslashit( $target ) !== untrailingslashit( strtok( flxl_current_url(), '?' ) ) ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
	},
	5
);

/**
 * Yedek gösterimde mi (istek dilinde çevirisi olmayan varsayılan dil içeriği).
 */
function flxl_is_fallback_view() {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post || ! flxl_is_translatable_type( $post->post_type ) || flxl_is_shared( $post->ID ) ) {
		return false;
	}
	return flxl_post_language( $post ) !== flxl_current_language();
}

/**
 * Yedek gösterimde içerik dilini işaretle (ekran okuyucu ve çeviri araçları için).
 */
add_filter(
	'the_content',
	function ( $html ) {
		if ( is_main_query() && in_the_loop() && flxl_is_fallback_view() && '' !== trim( $html ) ) {
			$lang = flxl_hreflang( flxl_post_language( get_queried_object() ) );
			return '<div lang="' . esc_attr( $lang ) . '" class="flxl-fallback">' . $html . '</div>';
		}
		return $html;
	},
	99
);
