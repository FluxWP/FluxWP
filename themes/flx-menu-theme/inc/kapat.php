<?php
/**
 * Tema Ayarları'ndaki anahtarların davranışı: yazılar, yorumlar, beslemeler, arama, ek sayfaları, XML-RPC, emoji,
 * oEmbed ve anonim kullanıcı listesi. Flux QR Menu'nün sayfalarına (parse_request'te sunulur), REST uçlarına ve
 * menü yöneticisi paneline dokunmaz.
 *
 * @package flx-menu-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * İsteği 404 yapar (tema 404 şablonu gösterilir, önbelleğe alınmaz; benzer adrese tahminle yönlendirilmez).
 * set_404() is_feed'i korur; o zaman besleme 404 koduyla yine de basılırdı, bu yüzden sıfırlanır.
 */
function yf_404() {
	global $wp_query;
	$wp_query->set_404();
	$wp_query->is_feed  = false;
	$wp_query->is_embed = false;
	remove_action( 'template_redirect', 'redirect_canonical' );
	status_header( 404 );
	nocache_headers();
}

/* ---------- Ön yüz: 404 / yönlendirme ---------- */

add_action(
	'template_redirect',
	function () {
		if ( yf_ayar( 'feeds_off' ) && is_feed() ) {
			return yf_404();
		}
		if ( yf_ayar( 'comments_off' ) && is_comment_feed() ) {
			return yf_404();
		}
		if ( yf_ayar( 'search_off' ) && is_search() ) {
			return yf_404();
		}
		if ( yf_ayar( 'embeds_off' ) && is_embed() ) {
			return yf_404();
		}
		if ( yf_ayar( 'attachments_off' ) && is_attachment() ) {
			$url = wp_get_attachment_url( get_queried_object_id() );
			if ( $url ) {
				wp_safe_redirect( $url, 301 );
				exit;
			}
			return yf_404();
		}
		if ( yf_ayar( 'posts_off' ) && ( is_singular( 'post' ) || is_category() || is_tag() || is_date() || is_author() || ( is_home() && ( ! is_front_page() || is_paged() ) ) ) ) {
			return yf_404();
		}
	},
	1
);

/* ---------- Yazılar ---------- */

add_action(
	'admin_menu',
	function () {
		if ( yf_ayar( 'posts_off' ) ) {
			remove_menu_page( 'edit.php' );
		}
		if ( yf_ayar( 'comments_off' ) ) {
			remove_menu_page( 'edit-comments.php' );
		}
	},
	999
);

add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( yf_ayar( 'posts_off' ) ) {
			$bar->remove_node( 'new-post' );
			$new = $bar->get_node( 'new-content' );
			if ( $new && current_user_can( 'edit_pages' ) ) {
				$new->href = admin_url( 'post-new.php?post_type=page' );
				$bar->add_node( (array) $new );
			}
		}
		if ( yf_ayar( 'comments_off' ) ) {
			$bar->remove_node( 'comments' );
		}
	},
	999
);

add_action(
	'wp_dashboard_setup',
	function () {
		if ( yf_ayar( 'posts_off' ) ) {
			remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
		}
		if ( yf_ayar( 'posts_off' ) && yf_ayar( 'comments_off' ) ) {
			remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
		}
	}
);

/* Site haritası: yazılar, kategoriler/etiketler ve kullanıcılar çıkar; sayfalar ve QR menüler kalır. */
add_filter(
	'wp_sitemaps_post_types',
	function ( $types ) {
		if ( yf_ayar( 'posts_off' ) ) {
			unset( $types['post'] );
		}
		unset( $types['attachment'] );
		return $types;
	}
);
add_filter(
	'wp_sitemaps_add_provider',
	function ( $provider, $name ) {
		if ( yf_ayar( 'posts_off' ) && in_array( $name, array( 'users', 'taxonomies' ), true ) ) {
			return false;
		}
		return $provider;
	},
	10,
	2
);
add_action(
	'init',
	function () {
		// Görünmez modda site haritası hiç yok (inc/gorunmez.php).
		if ( ! yf_gizli() && function_exists( 'flxq_routes' ) && function_exists( 'wp_register_sitemap_provider' ) && class_exists( 'WP_Sitemaps_Provider' ) ) {
			require_once __DIR__ . '/sitemap-menus.php';
			wp_register_sitemap_provider( 'qrmenus', new YF_Sitemap_Menus() );
		}
	}
);

/* ---------- Yorumlar ---------- */

add_action(
	'init',
	function () {
		if ( ! yf_ayar( 'comments_off' ) ) {
			return;
		}
		foreach ( get_post_types() as $pt ) {
			if ( post_type_supports( $pt, 'comments' ) ) {
				remove_post_type_support( $pt, 'comments' );
			}
			if ( post_type_supports( $pt, 'trackbacks' ) ) {
				remove_post_type_support( $pt, 'trackbacks' );
			}
		}
	},
	100
);
add_filter( 'comments_open', 'yf_comments_closed', 20 );
add_filter( 'pings_open', 'yf_comments_closed', 20 );
function yf_comments_closed( $open ) {
	return yf_ayar( 'comments_off' ) ? false : $open;
}
add_filter(
	'comments_array',
	function ( $c ) {
		return yf_ayar( 'comments_off' ) ? array() : $c;
	},
	20
);
add_action(
	'pre_comment_on_post',
	function () {
		if ( yf_ayar( 'comments_off' ) ) {
			wp_die( esc_html__( 'Bu sitede yorumlar kapalı.', 'flx-menu-theme' ), '', array( 'response' => 403 ) );
		}
	},
	1
);
add_action(
	'admin_init',
	function () {
		global $pagenow;
		if ( yf_ayar( 'comments_off' ) && 'edit-comments.php' === $pagenow ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}
);
add_filter(
	'wp_headers',
	function ( $h ) {
		if ( yf_ayar( 'comments_off' ) || yf_ayar( 'xmlrpc_off' ) ) {
			unset( $h['X-Pingback'] );
		}
		return $h;
	}
);

/* ---------- Beslemeler, XML-RPC, emoji, oEmbed (başlık etiketleri) ---------- */

add_action(
	'after_setup_theme',
	function () {
		if ( yf_ayar( 'feeds_off' ) ) {
			remove_action( 'wp_head', 'feed_links', 2 );
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}
		if ( yf_ayar( 'xmlrpc_off' ) ) {
			remove_action( 'wp_head', 'rsd_link' );
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', '__return_empty_array' );
		}
		if ( yf_ayar( 'emoji_off' ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
			remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			add_filter( 'emoji_svg_url', '__return_false' );
			add_filter(
				'tiny_mce_plugins',
				function ( $p ) {
					return array_diff( (array) $p, array( 'wpemoji' ) );
				}
			);
		}
		if ( yf_ayar( 'embeds_off' ) ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );
			add_filter( 'embed_oembed_discover', '__return_false' );
		}
	},
	20
);

/* XML-RPC: istek hiç işlenmez. */
add_action(
	'init',
	function () {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && yf_ayar( 'xmlrpc_off' ) ) {
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo esc_html__( 'XML-RPC bu sitede kapalı.', 'flx-menu-theme' );
			exit;
		}
	},
	0
);

/* Ek sayfaları: ek bağlantısı doğrudan dosyaya gider (WP 6.4+). */
add_filter(
	'pre_option_wp_attachment_pages_enabled',
	function ( $v ) {
		return yf_ayar( 'attachments_off' ) ? '0' : $v;
	}
);

/* ---------- REST: anonim kullanıcı listesi ---------- */

add_filter(
	'rest_endpoints',
	function ( $endpoints ) {
		if ( yf_ayar( 'users_rest_off' ) && ! is_user_logged_in() ) {
			foreach ( array_keys( $endpoints ) as $route ) {
				if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
					unset( $endpoints[ $route ] );
				}
			}
		}
		return $endpoints;
	}
);
