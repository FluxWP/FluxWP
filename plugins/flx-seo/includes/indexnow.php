<?php
/**
 * IndexNow: yayınlanan/güncellenen sayfalar Bing, Yandex, Seznam, Naver ve (Bing üzerinden) DuckDuckGo'ya anında bildirilir.
 * Anahtar dosyası /<anahtar>.txt adresinden sunulur; kurulum gerekmez.
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FLXSEO_INDEXNOW_ENDPOINT = 'https://api.indexnow.org/indexnow';

/** Site başına sabit anahtar (ilk istekte üretilir). */
function flxseo_indexnow_key() {
	$key = (string) get_option( 'flxseo_indexnow_key' );
	if ( ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
		$key = md5( wp_generate_password( 32, false ) . home_url() );
		update_option( 'flxseo_indexnow_key', $key, true );
	}
	return $key;
}

function flxseo_indexnow_enabled() {
	if ( ! flxseo_opt( 'indexnow' ) || ! get_option( 'blog_public' ) || wp_get_environment_type() === 'local' ) {
		return false;
	}
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	return '' !== $host && ! preg_match( '/^(localhost|127\.|10\.|192\.168\.)|\.(local|test)$/', $host );
}

/** /<anahtar>.txt → anahtar. */
add_action(
	'parse_request',
	function () {
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! preg_match( '/^[a-f0-9]{32}\.txt$/', $path ) || ! flxseo_opt( 'indexnow' ) ) {
			return;
		}
		$key = flxseo_indexnow_key();
		if ( $key . '.txt' !== $path ) {
			return;
		}
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo $key; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	},
	0
);

/**
 * Adresleri gönderir (en çok 10.000). Engellemesiz değilse sonucu döner.
 *
 * @param string[] $urls     Adresler.
 * @param bool     $blocking Sonuç beklensin mi.
 * @return true|WP_Error
 */
function flxseo_indexnow_send( $urls, $blocking = false ) {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$urls = array_values( array_unique( array_filter( (array) $urls, fn( $u ) => wp_parse_url( $u, PHP_URL_HOST ) === $host ) ) );
	if ( ! $urls ) {
		return new WP_Error( 'flxseo_indexnow_empty', __( 'No URLs to submit.', 'flx-seo' ) );
	}
	$key = flxseo_indexnow_key();
	$r   = wp_remote_post(
		FLXSEO_INDEXNOW_ENDPOINT,
		array(
			'blocking' => $blocking,
			'timeout'  => $blocking ? 20 : 3,
			'headers'  => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'     => wp_json_encode(
				array(
					'host'        => $host,
					'key'         => $key,
					'keyLocation' => home_url( '/' . $key . '.txt' ),
					'urlList'     => array_slice( $urls, 0, 10000 ),
				)
			),
		)
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	if ( $blocking ) {
		$code = (int) wp_remote_retrieve_response_code( $r );
		update_option( 'flxseo_indexnow_last', array( 'time' => time(), 'code' => $code, 'count' => count( $urls ) ), false );
		if ( $code >= 400 ) {
			/* translators: %d: HTTP durum kodu */
			return new WP_Error( 'flxseo_indexnow_http', sprintf( __( 'IndexNow returned %d.', 'flx-seo' ), $code ) );
		}
	}
	return true;
}

/** Bir yazının bildirilecek adresleri (Flux Lang diğer dillerdeki karşılıklarını ekleyebilir). */
function flxseo_indexnow_post_urls( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, flxseo_post_types(), true ) || ! is_post_type_viewable( $post->post_type ) ) {
		return array();
	}
	if ( flxseo_get( $post->ID, 'noindex' ) || post_password_required( $post ) ) {
		return array();
	}
	// Sepet, ödeme ve Hesabım sayfaları (ve alt sayfaları, ör. order-received) noindex ise bildirilmez.
	if ( flxseo_opt( 'noindex_shop' ) && 'page' === $post->post_type ) {
		$shop = array_filter( array_map( 'intval', array( get_option( 'woocommerce_cart_page_id' ), get_option( 'woocommerce_checkout_page_id' ), get_option( 'woocommerce_myaccount_page_id' ) ) ) );
		if ( $shop && array_intersect( array_merge( array( (int) $post->ID ), array_map( 'intval', get_post_ancestors( $post ) ) ), $shop ) ) {
			return array();
		}
	}
	return (array) apply_filters( 'flxseo_indexnow_urls', array( get_permalink( $post ) ), $post );
}

/** Kaydetmede kuyruğa alınır, istek sonunda tek seferde gönderilir. Aynı adres 10 dk içinde tekrar gönderilmez. */
function flxseo_indexnow_queue( $urls = null ) {
	static $queue = array();
	if ( null === $urls ) {
		return $queue;
	}
	foreach ( (array) $urls as $u ) {
		$t = 'flxseo_inow_' . md5( $u );
		if ( ! get_transient( $t ) ) {
			set_transient( $t, 1, 10 * MINUTE_IN_SECONDS );
			$queue[ $u ] = $u;
		}
	}
	return $queue;
}

add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( ! flxseo_indexnow_enabled() || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( 'publish' === $new ) {
			// Kalıcı bağlantı ve meta kaydı bitsin diye adres istek sonunda hesaplanır.
			add_action(
				'shutdown',
				function () use ( $post ) {
					flxseo_indexnow_queue( flxseo_indexnow_post_urls( $post->ID ) );
				},
				5
			);
		} elseif ( 'publish' === $old ) {
			// Yayından kalkan sayfa da bildirilir (arama motoru 404'ü görüp çıkarır).
			flxseo_indexnow_queue( array( get_permalink( $post ) ) );
		}
	},
	10,
	3
);

add_action(
	'shutdown',
	function () {
		$q = flxseo_indexnow_queue();
		if ( $q ) {
			flxseo_indexnow_send( array_values( $q ) );
		}
	},
	20
);

/** Tüm site: yayındaki tüm içerik adresleri (Ayarlar'daki "Tüm siteyi bildir" düğmesi). */
function flxseo_indexnow_all_urls() {
	$urls = array( home_url( '/' ) );
	$ids  = get_posts(
		array(
			'post_type'      => flxseo_post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 9000,
			'fields'         => 'ids',
			'has_password'   => false,
			'no_found_rows'  => true,
		)
	);
	foreach ( $ids as $id ) {
		$urls = array_merge( $urls, flxseo_indexnow_post_urls( $id ) );
	}
	// Kategori arşivleri (etiketler noindex ise atlanır).
	$taxes = array_filter( array( 'category', 'product_cat', flxseo_opt( 'noindex_tags' ) ? '' : 'post_tag' ), 'taxonomy_exists' );
	if ( $taxes ) {
		$terms = get_terms(
			array(
				'taxonomy'   => array_values( $taxes ),
				'hide_empty' => true,
				'number'     => 500,
			)
		);
		foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
			$link = get_term_link( $t );
			if ( ! is_wp_error( $link ) ) {
				$urls[] = $link;
			}
		}
	}
	return array_values( array_unique( (array) apply_filters( 'flxseo_indexnow_all_urls', $urls ) ) );
}

add_action(
	'admin_post_flxseo_indexnow_all',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'flx-seo' ) );
		}
		check_admin_referer( 'flxseo_indexnow_all' );
		if ( ! flxseo_indexnow_enabled() ) {
			$msg = array( 'error', __( 'IndexNow is off or the site is hidden from search engines (Settings › Reading).', 'flx-seo' ) );
		} else {
			$urls = flxseo_indexnow_all_urls();
			$r    = flxseo_indexnow_send( $urls, true );
			if ( ! is_wp_error( $r ) ) {
				update_option( 'flxseo_indexnow_init', time(), false );
			}
			/* translators: %d: adres sayısı */
			$msg = is_wp_error( $r ) ? array( 'error', $r->get_error_message() ) : array( 'success', sprintf( __( '%d URLs submitted to Bing, Yandex and the other IndexNow search engines.', 'flx-seo' ), count( $urls ) ) );
		}
		set_transient( 'flxseo_indexnow_msg_' . get_current_user_id(), $msg, 60 );
		wp_safe_redirect( flxseo_admin_url() . '#indexnow' );
		exit;
	}
);

/**
 * İlk bildirim: IndexNow açıkken site hiç toplu bildirilmediyse (yeni kurulum, IndexNow yeni açıldı ya da site
 * arama motorlarına yeni açıldı) tüm site bir kez kendiliğinden gönderilir. Cron'a güvenilmez (Hostinger'da
 * tetiklenmeyebiliyor); yönetim panelindeki bir isteğin sonunda, yanıt gönderildikten sonra çalışır.
 */
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || get_option( 'flxseo_indexnow_init' ) || ! current_user_can( 'manage_options' ) || ! flxseo_indexnow_enabled() ) {
			return;
		}
		if ( get_transient( 'flxseo_indexnow_init_lock' ) ) {
			return;
		}
		set_transient( 'flxseo_indexnow_init_lock', 1, HOUR_IN_SECONDS );
		add_action(
			'shutdown',
			function () {
				if ( function_exists( 'fastcgi_finish_request' ) ) {
					fastcgi_finish_request();
				}
				$r = flxseo_indexnow_send( flxseo_indexnow_all_urls(), true );
				if ( ! is_wp_error( $r ) ) {
					update_option( 'flxseo_indexnow_init', time(), false );
				}
			},
			30
		);
	}
);

/** IndexNow yeniden açılınca ya da site arama motorlarına açılınca ilk bildirim tekrarlanır. */
add_action(
	'update_option_flxseo',
	function ( $old, $new ) {
		if ( empty( $old['indexnow'] ) && ! empty( $new['indexnow'] ) ) {
			delete_option( 'flxseo_indexnow_init' );
			delete_transient( 'flxseo_indexnow_init_lock' );
		}
	},
	10,
	2
);
add_action(
	'update_option_blog_public',
	function ( $old, $new ) {
		if ( ! $old && $new ) {
			delete_option( 'flxseo_indexnow_init' );
			delete_transient( 'flxseo_indexnow_init_lock' );
		}
	},
	10,
	2
);
