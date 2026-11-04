<?php
/**
 * Ön yüz: şablon (blok temada tema başlık/altlık parçalarıyla, klasik temada get_header/get_footer),
 * varlıklar, <head> (Markdown alternatifi, SEO, JSON-LD), Markdown içerik pazarlığı.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

/** Şu anki istek wiki ekranı mı (ana sayfa, arama, bölüm, sayfa). */
function flxwk_is_view() {
	return is_post_type_archive( FLXWK_PT ) || is_singular( FLXWK_PT ) || is_tax( FLXWK_TAX );
}

function flxwk_query() {
	return isset( $_GET['q'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['q'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
}

add_action(
	'init',
	function () {
		register_block_type(
			'flx-wiki/view',
			array(
				'api_version'     => 3,
				'title'           => 'Flux Wiki',
				'category'        => 'widgets',
				'supports'        => array( 'inserter' => false, 'html' => false ),
				'render_callback' => 'flxwk_view_html',
			)
		);
	}
);

add_filter(
	'template_include',
	function ( $template ) {
		if ( ! flxwk_is_view() ) {
			return $template;
		}
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			global $_wp_current_template_content, $_wp_current_template_id;
			$header = get_block_template( get_stylesheet() . '//header', 'wp_template_part' ) ? '<!-- wp:template-part {"slug":"header","tagName":"header","className":"flx-header-part"} /-->' : '';
			$footer = get_block_template( get_stylesheet() . '//footer', 'wp_template_part' ) ? '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' : '';
			$_wp_current_template_content = $header . "\n<!-- wp:flx-wiki/view /-->\n" . $footer;
			$_wp_current_template_id      = get_stylesheet() . '//flx-wiki';
			return ABSPATH . WPINC . '/template-canvas.php';
		}
		return FLXWK_DIR . 'templates/classic.php';
	},
	99
);

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! flxwk_is_view() ) {
			return;
		}
		wp_enqueue_style( 'flx-wiki', FLXWK_URL . 'assets/wiki.css', array(), FLXWK_VERSION );
		wp_enqueue_script( 'flx-wiki', FLXWK_URL . 'assets/wiki.js', array(), FLXWK_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
		$post = is_singular( FLXWK_PT ) ? get_queried_object() : null;
		wp_localize_script(
			'flx-wiki',
			'flxWiki',
			array(
				'rest'   => esc_url_raw( rest_url( 'flx-wiki/v1/' ) ),
				'nonce'  => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'home'   => flxwk_home_url(),
				'canAsk' => flxwk_can_ask() ? 1 : 0,
				'page'   => $post ? (int) $post->ID : 0,
				'md'     => $post ? flxwk_md_url( $post ) : flxwk_home_url( 'index.md' ),
				'i18n'   => array(
					'copied'      => __( 'Kopyalandı', 'flx-wiki' ),
					'copy'        => __( 'Kopyala', 'flx-wiki' ),
					'copyFail'    => __( 'Kopyalanamadı', 'flx-wiki' ),
					'searching'   => __( 'Aranıyor…', 'flx-wiki' ),
					'noResults'   => __( 'Sonuç yok. Başka bir kelime deneyin.', 'flx-wiki' ),
					'typeToFind'  => __( 'Aramak için yazın', 'flx-wiki' ),
					'results'     => __( '%d sonuç', 'flx-wiki' ),
					'askAi'       => __( 'AI\'a sor', 'flx-wiki' ),
					'askHint'     => __( 'Sorunuzu yazın; cevap yalnız wiki sayfalarından verilir.', 'flx-wiki' ),
					'thinking'    => __( 'Wiki okunuyor, cevap hazırlanıyor…', 'flx-wiki' ),
					'sources'     => __( 'Kaynaklar', 'flx-wiki' ),
					'askFail'     => __( 'Cevap alınamadı. Biraz sonra tekrar deneyin.', 'flx-wiki' ),
					'searchTab'   => __( 'Ara', 'flx-wiki' ),
					'loginToAsk'  => __( 'Soru sormak için giriş yapın.', 'flx-wiki' ),
				),
			)
		);
	}
);

/* Arama sonuçları dizine girmez. */
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_post_type_archive( FLXWK_PT ) && '' !== flxwk_query() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);

add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( is_post_type_archive( FLXWK_PT ) ) {
			$q             = flxwk_query();
			/* translators: %s: arama sorgusu */
			$parts['title'] = '' !== $q ? sprintf( __( '"%s" için wiki araması', 'flx-wiki' ), $q ) : flxwk_title();
		} elseif ( is_singular( FLXWK_PT ) ) {
			$parts['title'] = single_post_title( '', false ) . ' – ' . flxwk_title();
		}
		return $parts;
	},
	20
);
add_filter(
	'flxseo_archive_title',
	function ( $title, $type ) {
		return FLXWK_PT === $type ? flxwk_title() : $title;
	},
	10,
	2
);
add_filter(
	'flxseo_archive_desc',
	function ( $desc, $type ) {
		return FLXWK_PT === $type ? flxwk_intro() : $desc;
	},
	10,
	2
);

/** <head>: Markdown alternatifi; Flux SEO yoksa açıklama, canonical ve Open Graph; JSON-LD. */
add_action(
	'wp_head',
	function () {
		if ( ! flxwk_is_view() ) {
			return;
		}
		$post = is_singular( FLXWK_PT ) ? get_queried_object() : null;
		$md   = $post ? flxwk_md_url( $post ) : flxwk_home_url( 'index.md' );
		echo '<link rel="alternate" type="text/markdown" href="' . esc_url( $md ) . '" title="Markdown">' . "\n";
		echo '<link rel="help" type="text/plain" href="' . esc_url( home_url( '/' . flxwk_base() . '/llms.txt' ) ) . '" title="llms.txt">' . "\n";

		if ( ! function_exists( 'flxseo_clean' ) ) {
			$desc  = $post ? flxwk_excerpt( $post ) : ( is_tax( FLXWK_TAX ) ? wp_strip_all_tags( term_description() ) : flxwk_intro() );
			$url   = $post ? get_permalink( $post ) : ( is_tax( FLXWK_TAX ) ? get_term_link( get_queried_object() ) : flxwk_home_url() );
			$title = $post ? get_the_title( $post ) : flxwk_title();
			if ( $desc ) {
				echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
			}
			if ( ! $post && ! is_wp_error( $url ) ) {
				echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
			}
			echo '<meta property="og:type" content="' . ( $post ? 'article' : 'website' ) . '">' . "\n";
			echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
			echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
			if ( ! is_wp_error( $url ) ) {
				echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
			}
			if ( $desc ) {
				echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
			}
			echo '<meta name="twitter:card" content="summary">' . "\n";
			$graph = flxwk_schema( true );
			if ( $graph ) {
				echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
			}
		}
	},
	5
);

/* Flux SEO varsa JSON-LD onun @graph'ına eklenir (tek graf; kırıntıyı Flux SEO yazar). */
add_filter(
	'flxseo_schema_graph',
	function ( $graph ) {
		return flxwk_is_view() ? array_merge( (array) $graph, flxwk_schema( false ) ) : $graph;
	}
);

/**
 * TechArticle (sayfa) + BreadcrumbList (Flux SEO yoksa).
 *
 * @param bool $crumbs Kırıntı da eklensin mi.
 */
function flxwk_schema( $crumbs ) {
	$out  = array();
	$post = is_singular( FLXWK_PT ) ? get_queried_object() : null;
	if ( $post ) {
		$sec   = flxwk_section_of( $post );
		$out[] = array(
			'@type'            => 'TechArticle',
			'@id'              => get_permalink( $post ) . '#article',
			'headline'         => get_the_title( $post ),
			'description'      => flxwk_excerpt( $post ),
			'url'              => get_permalink( $post ),
			'datePublished'    => get_post_time( 'c', true, $post ),
			'dateModified'     => get_post_modified_time( 'c', true, $post ),
			'inLanguage'       => get_bloginfo( 'language' ),
			'articleSection'   => $sec ? $sec->name : flxwk_title(),
			'author'           => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
			'isPartOf'         => array( '@type' => 'CollectionPage', 'name' => flxwk_title(), 'url' => flxwk_home_url() ),
			'encoding'         => array( '@type' => 'MediaObject', 'encodingFormat' => 'text/markdown', 'contentUrl' => flxwk_md_url( $post ) ),
			'mainEntityOfPage' => get_permalink( $post ),
		);
	}
	if ( $crumbs ) {
		$items = array();
		foreach ( flxwk_breadcrumbs() as $i => $c ) {
			$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1] );
		}
		if ( count( $items ) > 1 ) {
			$out[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
		}
	}
	return $out;
}

/** Kırıntı: [ [ ad, adres ] ]. */
function flxwk_breadcrumbs() {
	$c = array( array( flxwk_title(), flxwk_home_url() ) );
	if ( is_singular( FLXWK_PT ) ) {
		$post = get_queried_object();
		$sec  = flxwk_section_of( $post );
		if ( $sec ) {
			$c[] = array( $sec->name, get_term_link( $sec ) );
		}
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $a ) {
			$c[] = array( get_the_title( $a ), get_permalink( $a ) );
		}
		$c[] = array( get_the_title( $post ), get_permalink( $post ) );
	} elseif ( is_tax( FLXWK_TAX ) ) {
		$t   = get_queried_object();
		$c[] = array( $t->name, get_term_link( $t ) );
	}
	return $c;
}

/* İstemci Markdown isterse (Accept: text/markdown) sayfa Markdown olarak döner. */
add_action(
	'template_redirect',
	function () {
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? strtolower( (string) $_SERVER['HTTP_ACCEPT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( false === strpos( $accept, 'text/markdown' ) || false !== strpos( $accept, 'text/html' ) ) {
			return;
		}
		if ( is_singular( FLXWK_PT ) ) {
			flxwk_send_text( flxwk_page_markdown( get_queried_object() ), 'text/markdown' );
		} elseif ( is_post_type_archive( FLXWK_PT ) && '' === flxwk_query() ) {
			flxwk_send_text( flxwk_index_markdown(), 'text/markdown' );
		}
	},
	1
);

/** Düz metin yanıtı gönderip çıkar. */
function flxwk_send_text( $text, $type = 'text/plain' ) {
	status_header( 200 );
	header( 'Content-Type: ' . $type . '; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, follow' );
	header( 'Vary: Accept' );
	header( 'Cache-Control: public, max-age=300' );
	echo $text; // phpcs:ignore WordPress.Security.EscapeOutput -- düz metin.
	exit;
}

require_once FLXWK_DIR . 'includes/view.php';
