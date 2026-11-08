<?php
/**
 * Asistanın sitedeki içeriklerde (haber, proje, etkinlik…) arama yapması. Ne döneceğine model değil kod karar verir:
 * yalnız yayında, şifresiz, herkese açık ve ayarda seçili içerik türleri; hariç tutulan kategori/etiketler ve
 * "Asistan paylaşmasın" işaretli içerikler hiç dönmez. Model yalnız arama metnini verir.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

function flxc_site_on() {
	return (bool) flxc_setting( 'bot_site_search' );
}

/** Aranabilecek içerik türleri (ayar boşsa yazılar ve arşivi olan herkese açık türler; sayfa ve ürün hariç). */
function flxc_site_types() {
	$all = get_post_types( array( 'public' => true ), 'objects' );
	$sel = array_filter( (array) flxc_setting( 'bot_site_types' ) );
	$out = array();
	foreach ( $all as $name => $o ) {
		if ( in_array( $name, array( 'attachment', 'page', 'product' ), true ) && ! in_array( $name, $sel, true ) ) {
			continue;
		}
		if ( $sel ? in_array( $name, $sel, true ) : ( 'post' === $name || ( ! $o->_builtin && $o->has_archive ) ) ) {
			$out[ $name ] = $o->labels->singular_name;
		}
	}
	return (array) apply_filters( 'flxc_site_types', $out );
}

/** Hariç tutulan terimler (kategori/etiket adı ya da kısa adı, virgülle). */
function flxc_site_excluded_terms() {
	$ids = array();
	foreach ( preg_split( '/\s*[,\n]\s*/', (string) flxc_setting( 'bot_site_exclude' ) ) as $w ) {
		$w = trim( $w );
		if ( '' === $w ) {
			continue;
		}
		foreach ( get_taxonomies( array( 'public' => true ) ) as $tax ) {
			foreach ( array( 'slug', 'name' ) as $f ) {
				$t = get_term_by( $f, 'slug' === $f ? sanitize_title( $w ) : $w, $tax );
				if ( $t ) {
					$ids[ $tax ][] = (int) $t->term_id;
				}
			}
		}
	}
	return $ids;
}

/** Bu içerik asistanla paylaşılabilir mi? (tek yazı için son kontrol) */
function flxc_site_allowed( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
		return false;
	}
	if ( ! isset( flxc_site_types()[ $post->post_type ] ) || get_post_meta( $post->ID, '_flxc_bot_hide', true ) ) {
		return false;
	}
	foreach ( flxc_site_excluded_terms() as $tax => $ids ) {
		if ( has_term( $ids, $tax, $post ) ) {
			return false;
		}
	}
	return (bool) apply_filters( 'flxc_site_allowed', true, $post );
}

function flxc_site_item( $p ) {
	$text  = has_excerpt( $p ) ? $p->post_excerpt : wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $p->post_content ) ) );
	$terms = array();
	foreach ( get_object_taxonomies( $p->post_type, 'objects' ) as $tax ) {
		if ( ! $tax->public ) {
			continue;
		}
		foreach ( (array) get_the_terms( $p, $tax->name ) as $t ) {
			if ( $t instanceof WP_Term ) {
				$terms[] = $t->name;
			}
		}
	}
	return array(
		'title'   => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
		'type'    => flxc_site_types()[ $p->post_type ] ?? $p->post_type,
		'date'    => get_the_date( 'd.m.Y', $p ),
		'url'     => get_permalink( $p ),
		'terms'   => array_slice( array_unique( $terms ), 0, 6 ),
		'excerpt' => wp_trim_words( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ), 45, '…' ),
	);
}

/**
 * @param string $q    aranan metin ('' ise en yeniler)
 * @param string $type içerik türü (izinli değilse yok sayılır)
 */
function flxc_site_search( $q, $type = '' ) {
	$types = flxc_site_types();
	if ( ! $types ) {
		return array();
	}
	$pt   = isset( $types[ $type ] ) ? array( $type ) : array_keys( $types );
	$args = array(
		'post_type'           => $pt,
		'post_status'         => 'publish',
		'has_password'        => false,
		'posts_per_page'      => 8,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'suppress_filters'    => false,
		'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'key'     => '_flxc_bot_hide',
				'compare' => 'NOT EXISTS',
			),
		),
	);
	$tq = array();
	foreach ( flxc_site_excluded_terms() as $tax => $ids ) {
		$tq[] = array(
			'taxonomy' => $tax,
			'terms'    => array_unique( $ids ),
			'operator' => 'NOT IN',
		);
	}
	if ( $tq ) {
		$args['tax_query'] = $tq; // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	$q    = trim( mb_substr( sanitize_text_field( (string) $q ), 0, 100 ) );
	$runs = array( $q );
	if ( '' !== $q ) {
		// Tüm kelimelerle bulunamazsa en uzun kelimeyle bir kez daha.
		$words = preg_split( '/\s+/u', $q );
		usort(
			$words,
			function ( $a, $b ) {
				return mb_strlen( $b ) <=> mb_strlen( $a );
			}
		);
		if ( count( $words ) > 1 && mb_strlen( $words[0] ) >= 4 ) {
			$runs[] = $words[0];
		}
	}
	$out = array();
	foreach ( $runs as $s ) {
		$a = $args;
		if ( '' !== $s ) {
			$a['s'] = $s;
		}
		foreach ( get_posts( $a ) as $p ) {
			if ( count( $out ) < 5 && flxc_site_allowed( $p ) ) {
				$out[ $p->ID ] = flxc_site_item( $p );
			}
		}
		if ( $out ) {
			break;
		}
	}
	return array_values( $out );
}

/* ---------- Asistan aracı ---------- */

function flxc_site_tool_def( $f ) {
	$types = flxc_site_types();
	return $f(
		'search_site',
		'Sitemizde yayında olan içeriklerde (' . implode( ', ', $types ) . ') arar: kişi bir proje, etkinlik, haber ya da gönderi sorarsa ("… geçen bir gönderiniz var mı", "hangi projeler var"). query boşsa en yenileri verir. Yalnız dönen bilgileri ve linkleri kullan.',
		array(
			'query' => array( 'type' => 'string', 'description' => 'Aranacak kelimeler' ),
			'type'  => array( 'type' => 'string', 'enum' => array_keys( $types ) ),
		)
	);
}

function flxc_site_tool( array $a ) {
	$items = flxc_site_search( (string) ( $a['query'] ?? '' ), (string) ( $a['type'] ?? '' ) );
	return $items ? array( 'ok' => true, 'items' => $items ) : array( 'ok' => true, 'items' => array(), 'note' => 'Sitede bununla ilgili yayında bir içerik bulunamadı; uydurma, kişiye bulamadığını söyle.' );
}

/* ---------- İçerik düzenleme ekranı: "Asistan paylaşmasın" ---------- */

add_action(
	'add_meta_boxes',
	function ( $post_type ) {
		if ( ! flxc_site_on() || ! isset( flxc_site_types()[ $post_type ] ) || ! flxc_can_manage() ) {
			return;
		}
		add_meta_box(
			'flxc-bot-hide',
			__( 'WhatsApp assistant', 'flx-chat' ),
			function ( $post ) {
				wp_nonce_field( 'flxc_bot_hide', 'flxc_bot_hide_nonce' );
				echo '<label><input type="checkbox" name="flxc_bot_hide" value="1" ' . checked( (bool) get_post_meta( $post->ID, '_flxc_bot_hide', true ), true, false ) . '> ' . esc_html__( 'Do not let the assistant share this content', 'flx-chat' ) . '</label>';
			},
			$post_type,
			'side',
			'low'
		);
	}
);

add_action(
	'save_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['flxc_bot_hide_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['flxc_bot_hide_nonce'] ) ), 'flxc_bot_hide' ) || ! flxc_can_manage() || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! empty( $_POST['flxc_bot_hide'] ) ) {
			update_post_meta( $post_id, '_flxc_bot_hide', 1 );
		} else {
			delete_post_meta( $post_id, '_flxc_bot_hide' );
		}
	}
);
