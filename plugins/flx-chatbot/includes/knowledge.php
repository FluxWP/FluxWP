<?php
/**
 * Asistanın bilgi kaynakları: site araması, sayfa metni, ürün araması (kartlarla) ve WooCommerce siparişleri.
 * Yalnız herkese açık, şifresiz, yayımlanmış içerik okunur.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/** Aranacak yazı türleri (ürünler ayrı araçta). */
function flxcb_search_types() {
	$t = array();
	if ( flxcb_setting( 'ai_src_pages' ) ) {
		$t[] = 'page';
	}
	if ( flxcb_setting( 'ai_src_posts' ) ) {
		$t[] = 'post';
	}
	if ( flxcb_setting( 'ai_src_wiki' ) && defined( 'FLXWK_PT' ) ) {
		$t[] = FLXWK_PT;
	}
	return (array) apply_filters( 'flxcb_search_post_types', $t );
}

function flxcb_products_on() {
	return flxcb_setting( 'ai_src_products' ) && function_exists( 'wc_get_product' );
}

/** Yazı ziyaretçiye açık mı. */
function flxcb_public_post( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
		return false;
	}
	$pto = get_post_type_object( $post->post_type );
	return $pto && $pto->public && (bool) apply_filters( 'flxcb_post_readable', true, $post );
}

/** HTML → sade metin. */
function flxcb_plain( $html, $max = 0 ) {
	$html = preg_replace( '#<(script|style|svg|noscript)[^>]*>.*?</\1>#is', ' ', (string) $html );
	$html = preg_replace( '#</(p|div|li|h[1-6]|tr|br)\s*/?>#i', "\n", $html );
	$t    = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
	$t    = preg_replace( "/[ \t]+/", ' ', $t );
	$t    = trim( preg_replace( "/\n\s*\n+/", "\n", $t ) );
	if ( $max && mb_strlen( $t ) > $max ) {
		$t = mb_substr( $t, 0, $max ) . '…';
	}
	return $t;
}

/** Sayfa içeriğinin metni (kısa kodlar işlenmeden çıkarılır, blok yorumları silinir). */
function flxcb_post_text( $post, $max = 6000 ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$c = strip_shortcodes( (string) $post->post_content );
	$c = preg_replace( '/<!--.*?-->/s', '', $c );
	return flxcb_plain( $c, $max );
}

/**
 * Site araması.
 *
 * @return array[] { id, title, url, type, excerpt }
 */
function flxcb_search_site( $q, $limit = 5 ) {
	$q     = trim( (string) $q );
	$types = flxcb_search_types();
	if ( '' === $q || ! $types ) {
		return array();
	}
	$query = new WP_Query(
		array(
			's'                   => $q,
			'post_type'           => $types,
			'post_status'         => 'publish',
			'posts_per_page'      => $limit * 2,
			'has_password'        => false,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'suppress_filters'    => false,
		)
	);
	$out = array();
	foreach ( $query->posts as $p ) {
		if ( ! flxcb_public_post( $p ) ) {
			continue;
		}
		$ex    = has_excerpt( $p ) ? $p->post_excerpt : flxcb_post_text( $p, 900 );
		$out[] = array(
			'id'      => (int) $p->ID,
			'title'   => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
			'url'     => get_permalink( $p ),
			'type'    => $p->post_type,
			'excerpt' => flxcb_plain( $ex, 900 ),
		);
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return (array) apply_filters( 'flxcb_search_results', $out, $q );
}

/** Adres ya da id ile sayfa metni. */
function flxcb_read_page( $ref ) {
	$id = is_numeric( $ref ) ? (int) $ref : url_to_postid( (string) $ref );
	if ( ! $id && is_string( $ref ) && untrailingslashit( $ref ) === untrailingslashit( home_url() ) ) {
		$id = (int) get_option( 'page_on_front' );
	}
	if ( ! $id || ! flxcb_public_post( $id ) ) {
		return null;
	}
	$p   = get_post( $id );
	$out = array(
		'title' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
		'url'   => get_permalink( $p ),
		'text'  => flxcb_post_text( $p, 7000 ),
	);
	if ( 'product' === $p->post_type && function_exists( 'wc_get_product' ) ) {
		$prod = flxcb_product_info( wc_get_product( $id ) );
		if ( $prod ) {
			$out['product'] = $prod;
		}
	}
	return $out;
}

/** Ürün özeti (model ve kart için). */
function flxcb_product_info( $p ) {
	if ( ! $p || ! is_object( $p ) || 'publish' !== $p->get_status() || 'hidden' === $p->get_catalog_visibility() ) {
		return null;
	}
	$img = wp_get_attachment_image_url( (int) $p->get_image_id(), 'woocommerce_thumbnail' );
	$cur = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
	return array(
		'id'       => (int) $p->get_id(),
		'name'     => html_entity_decode( $p->get_name(), ENT_QUOTES, 'UTF-8' ),
		'url'      => get_permalink( $p->get_id() ),
		'price'    => '' !== (string) $p->get_price() ? (float) wc_get_price_to_display( $p ) : null,
		'regular'  => $p->is_on_sale() && '' !== (string) $p->get_regular_price() ? (float) wc_get_price_to_display( $p, array( 'price' => $p->get_regular_price() ) ) : null,
		'currency' => $cur,
		'priceText' => flxcb_plain( $p->get_price_html() ),
		'stock'    => $p->is_in_stock() ? 'in' : 'out',
		'image'    => $img ? $img : '',
		'short'    => flxcb_plain( $p->get_short_description(), 300 ),
		'cart'     => $p->is_purchasable() && $p->is_in_stock() && $p->is_type( 'simple' ) ? add_query_arg( 'add-to-cart', $p->get_id(), wc_get_cart_url() ) : '',
	);
}

/** Ürün araması. */
function flxcb_search_products( $q, $limit = 4, $max_price = 0 ) {
	if ( ! flxcb_products_on() ) {
		return array();
	}
	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => $limit * 3,
		'no_found_rows'  => true,
	);
	if ( '' !== trim( (string) $q ) ) {
		$args['s'] = (string) $q;
	}
	$query = new WP_Query( $args );
	$ids   = wp_list_pluck( $query->posts, 'ID' );
	if ( ! $ids && '' !== trim( (string) $q ) ) {
		// Kategori ya da etiket adıyla dene.
		$terms = get_terms( array( 'taxonomy' => array( 'product_cat', 'product_tag' ), 'search' => (string) $q, 'number' => 5, 'fields' => 'ids', 'hide_empty' => true ) );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$ids = get_posts(
				array(
					'post_type'      => 'product',
					'posts_per_page' => $limit * 3,
					'fields'         => 'ids',
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						'relation' => 'OR',
						array( 'taxonomy' => 'product_cat', 'terms' => $terms ),
						array( 'taxonomy' => 'product_tag', 'terms' => $terms ),
					),
				)
			);
		}
	}
	$out = array();
	foreach ( $ids as $id ) {
		$info = flxcb_product_info( wc_get_product( $id ) );
		if ( ! $info || ( $max_price > 0 && null !== $info['price'] && $info['price'] > $max_price ) ) {
			continue;
		}
		$out[] = $info;
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/** Ürün → widget kartı. */
function flxcb_product_card( array $p ) {
	return array(
		'kind'  => 'product',
		'title' => $p['name'],
		'url'   => $p['url'],
		'image' => $p['image'],
		'price' => $p['priceText'],
		'stock' => $p['stock'],
		'cart'  => $p['cart'],
	);
}

/** Giriş yapmış müşterinin siparişleri. */
function flxcb_user_orders( $user_id, $number = '' ) {
	if ( ! $user_id || ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}
	$orders = wc_get_orders(
		array(
			'customer_id' => (int) $user_id,
			'limit'       => 5,
			'orderby'     => 'date',
			'order'       => 'DESC',
		)
	);
	$out = array();
	foreach ( $orders as $o ) {
		if ( '' !== (string) $number && (string) $o->get_order_number() !== ltrim( (string) $number, '#' ) ) {
			continue;
		}
		$items = array();
		foreach ( $o->get_items() as $it ) {
			$items[] = $it->get_name() . ' × ' . $it->get_quantity();
		}
		$out[] = array(
			'number'   => (string) $o->get_order_number(),
			'date'     => function_exists( 'flx_date' ) ? flx_date( $o->get_date_created() ) : (string) $o->get_date_created(),
			'status'   => wc_get_order_status_name( $o->get_status() ),
			'total'    => flxcb_plain( $o->get_formatted_order_total() ),
			'items'    => $items,
			'payment'  => (string) $o->get_payment_method_title(),
			'tracking' => (string) apply_filters( 'flxcb_order_tracking', '', $o ),
			'url'      => $o->get_view_order_url(),
		);
	}
	return $out;
}

/** Sepet özeti (asistan bağlamı). */
function flxcb_cart_summary() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return null;
	}
	$items = array();
	foreach ( WC()->cart->get_cart() as $it ) {
		if ( ! empty( $it['data'] ) && is_object( $it['data'] ) ) {
			$items[] = $it['data']->get_name() . ' × ' . (int) $it['quantity'];
		}
	}
	if ( ! $items ) {
		return null;
	}
	return array(
		'items' => array_slice( $items, 0, 10 ),
		'total' => flxcb_plain( WC()->cart->get_cart_total() ),
	);
}
