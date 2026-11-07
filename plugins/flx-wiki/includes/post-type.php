<?php
/**
 * Wiki sayfası (flx_wiki, hiyerarşik) ve bölüm (flx_wiki_section) türleri, adres kuralları, kaydetme.
 *
 * İçerik Markdown olarak _flxwk_md meta alanında durur (kses Markdown'u bozmasın diye);
 * post_content işlenmiş HTML'in kopyasıdır (WP araması, RSS, SEO eklentileri için).
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'flxl_post_types', function ( $types ) {
	$types[] = FLXWK_PT;
	return array_values( array_unique( $types ) );
} );

add_action( 'init', 'flxwk_register_types', 5 );
add_action( 'init', 'flxwk_rewrite_rules', 6 );

function flxwk_register_types() {
	$base = flxwk_base();

	/* Bölüm önce kaydedilir: kuralı sayfa yolu kuralından (wiki/(.+?)) önce gelsin. */
	register_taxonomy(
		FLXWK_TAX,
		FLXWK_PT,
		array(
			'labels'            => array(
				'name'          => __( 'Bölümler', 'flx-wiki' ),
				'singular_name' => __( 'Bölüm', 'flx-wiki' ),
				'add_new_item'  => __( 'Yeni bölüm', 'flx-wiki' ),
				'edit_item'     => __( 'Bölümü düzenle', 'flx-wiki' ),
				'search_items'  => __( 'Bölüm ara', 'flx-wiki' ),
				'all_items'     => __( 'Tüm bölümler', 'flx-wiki' ),
				'menu_name'     => __( 'Bölümler', 'flx-wiki' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'       => $base . '/section',
				'with_front' => false,
			),
		)
	);

	register_post_type(
		FLXWK_PT,
		array(
			'labels'          => array(
				'name'               => __( 'Wiki', 'flx-wiki' ),
				'singular_name'      => __( 'Wiki sayfası', 'flx-wiki' ),
				'menu_name'          => __( 'Wiki', 'flx-wiki' ),
				'all_items'          => __( 'Tüm sayfalar', 'flx-wiki' ),
				'add_new'            => __( 'Yeni sayfa', 'flx-wiki' ),
				'add_new_item'       => __( 'Yeni wiki sayfası', 'flx-wiki' ),
				'edit_item'          => __( 'Wiki sayfasını düzenle', 'flx-wiki' ),
				'new_item'           => __( 'Yeni wiki sayfası', 'flx-wiki' ),
				'view_item'          => __( 'Sayfayı görüntüle', 'flx-wiki' ),
				'search_items'       => __( 'Wiki\'de ara', 'flx-wiki' ),
				'not_found'          => __( 'Sayfa bulunamadı', 'flx-wiki' ),
				'not_found_in_trash' => __( 'Çöpte sayfa yok', 'flx-wiki' ),
				'parent_item_colon'  => __( 'Üst sayfa:', 'flx-wiki' ),
			),
			'description'     => flxwk_intro(),
			'public'          => true,
			'hierarchical'    => true,
			'has_archive'     => $base,
			'rewrite'         => array(
				'slug'       => $base,
				'with_front' => false,
				'feeds'      => false,
			),
			'menu_icon'       => 'none', // Renkli paket logosu: includes/admin.php.
			'menu_position'   => 21,
			'show_in_rest'    => true,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'supports'        => array( 'title', 'excerpt', 'page-attributes', 'revisions', 'author', 'custom-fields' ),
		)
	);

	register_post_meta(
		FLXWK_PT,
		'_flxwk_md',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'revisions_enabled' => true,
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}

/** /wiki/llms.txt, /wiki/llms-full.txt, /wiki/<yol>.md */
/* Adres kökü değişince kurallar bir kez yenilenir. */
add_action(
	'init',
	function () {
		if ( get_option( 'flxwk_flush' ) ) {
			delete_option( 'flxwk_flush' );
			flush_rewrite_rules( false );
		}
	},
	99
);

function flxwk_rewrite_rules() {
	$b = preg_quote( flxwk_base(), '#' );
	add_rewrite_rule( '^' . $b . '/llms\.txt$', 'index.php?flxwk_llms=index', 'top' );
	add_rewrite_rule( '^' . $b . '/llms-full\.txt$', 'index.php?flxwk_llms=full', 'top' );
	add_rewrite_rule( '^' . $b . '/index\.md$', 'index.php?flxwk_raw=__index', 'top' );
	add_rewrite_rule( '^' . $b . '/(.+?)\.md$', 'index.php?flxwk_raw=$matches[1]', 'top' );
}

add_filter(
	'query_vars',
	function ( $v ) {
		$v[] = 'flxwk_llms';
		$v[] = 'flxwk_raw';
		return $v;
	}
);

/* Markdown düzenleyici kullanılır; blok düzenleyici kapalı. */
add_filter(
	'use_block_editor_for_post_type',
	function ( $use, $type ) {
		return FLXWK_PT === $type ? false : $use;
	},
	10,
	2
);

/**
 * Sayfa oluşturur ya da günceller (MCP, içe aktarma ve düzenleyici ortak yolu).
 *
 * @param array $a id, title, md, slug, parent (id ya da yol), section (ad ya da kısa ad), status, excerpt, menu_order.
 * @return int|WP_Error
 */
function flxwk_save_page( array $a ) {
	$id   = isset( $a['id'] ) ? (int) $a['id'] : 0;
	$post = $id ? get_post( $id ) : null;
	if ( $id && ( ! $post || FLXWK_PT !== $post->post_type ) ) {
		return new WP_Error( 'flxwk_not_found', __( 'Wiki sayfası bulunamadı.', 'flx-wiki' ) );
	}
	$md = array_key_exists( 'md', $a ) ? (string) $a['md'] : ( $post ? flxwk_md( $post ) : '' );
	$r  = flxwk_render( $md );

	$data = array(
		'post_type'    => FLXWK_PT,
		'post_content' => $r['html'],
	);
	if ( $id ) {
		$data['ID'] = $id;
	}
	if ( isset( $a['title'] ) ) {
		$data['post_title'] = sanitize_text_field( $a['title'] );
	} elseif ( ! $id ) {
		return new WP_Error( 'flxwk_title', __( 'Başlık gerekli.', 'flx-wiki' ) );
	}
	if ( isset( $a['slug'] ) && '' !== $a['slug'] ) {
		$data['post_name'] = sanitize_title( $a['slug'] );
	}
	if ( isset( $a['excerpt'] ) ) {
		$data['post_excerpt'] = sanitize_textarea_field( $a['excerpt'] );
	}
	if ( isset( $a['menu_order'] ) ) {
		$data['menu_order'] = (int) $a['menu_order'];
	}
	if ( isset( $a['status'] ) ) {
		$data['post_status'] = in_array( $a['status'], array( 'publish', 'draft', 'pending', 'private' ), true ) ? $a['status'] : 'draft';
	} elseif ( ! $id ) {
		$data['post_status'] = 'draft';
	}
	if ( isset( $a['parent'] ) ) {
		$parent              = '' === $a['parent'] || 0 === $a['parent'] ? null : ( get_page_by_path( (string) $a['parent'], OBJECT, FLXWK_PT ) ?: flxwk_find_page( (string) $a['parent'] ) );
		$data['post_parent'] = $parent ? $parent->ID : 0;
	}

	$GLOBALS['flxwk_saving'] = true;
	$res                     = $id ? wp_update_post( wp_slash( $data ), true ) : wp_insert_post( wp_slash( $data ), true );
	unset( $GLOBALS['flxwk_saving'] );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	update_post_meta( $res, '_flxwk_md', wp_slash( $md ) );
	if ( isset( $a['section'] ) ) {
		flxwk_set_section( $res, (string) $a['section'] );
	}
	flxwk_bump();
	return (int) $res;
}

/** Bölümü ada ya da kısa ada göre atar; yoksa oluşturur. Boş: bölümden çıkarır. */
function flxwk_set_section( $post_id, $section ) {
	$section = trim( $section );
	if ( '' === $section ) {
		wp_set_object_terms( $post_id, array(), FLXWK_TAX );
		return;
	}
	$t = get_term_by( 'slug', sanitize_title( $section ), FLXWK_TAX );
	if ( ! $t ) {
		$t = get_term_by( 'name', $section, FLXWK_TAX );
	}
	if ( ! $t ) {
		$n = wp_insert_term( $section, FLXWK_TAX );
		if ( is_wp_error( $n ) ) {
			return;
		}
		$t = get_term( $n['term_id'], FLXWK_TAX );
	}
	wp_set_object_terms( $post_id, array( (int) $t->term_id ), FLXWK_TAX );
}

/** Önbellekleri (wiki bağlantıları, arama dizini, llms) geçersiz kılar. */
function flxwk_bump() {
	update_option( 'flxwk_gen', (int) get_option( 'flxwk_gen', 0 ) + 1, false );
	delete_transient( 'flxwk_index' );
	delete_transient( 'flxwk_vocab' );
	delete_transient( 'flxseo_llms' );
}

add_action(
	'save_post_' . FLXWK_PT,
	function () {
		if ( empty( $GLOBALS['flxwk_saving'] ) ) {
			flxwk_bump();
		}
	}
);
add_action(
	'deleted_post',
	function ( $id, $post = null ) {
		if ( $post && FLXWK_PT === $post->post_type ) {
			flxwk_bump();
		}
	},
	10,
	2
);
add_action( 'edited_' . FLXWK_TAX, 'flxwk_bump' );
add_action( 'created_' . FLXWK_TAX, 'flxwk_bump' );
add_action( 'delete_' . FLXWK_TAX, 'flxwk_bump' );

/*
 * Tema şablonu dışındaki çıktılar (RSS, başka eklentiler) için the_content Markdown'dan üretilir;
 * wpautop kod bloklarını bozmasın diye bu yazı için kapatılır.
 */
add_filter(
	'the_content',
	function ( $content ) {
		$post = get_post();
		if ( $post && FLXWK_PT === $post->post_type && ! is_admin() ) {
			if ( false !== has_filter( 'the_content', 'wpautop' ) ) {
				remove_filter( 'the_content', 'wpautop' );
				$GLOBALS['flxwk_autop_off'] = true;
			}
			$r = flxwk_page_html( $post );
			return $r['html'];
		}
		return $content;
	},
	1
);
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! empty( $GLOBALS['flxwk_autop_off'] ) ) {
			add_filter( 'the_content', 'wpautop' );
			unset( $GLOBALS['flxwk_autop_off'] );
		}
		return $content;
	},
	99
);
