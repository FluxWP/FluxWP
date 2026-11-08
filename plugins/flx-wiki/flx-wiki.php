<?php
/**
 * Plugin Name:       Flux Wiki
 * Description:       Sitede /wiki altında geliştirici wiki'si: Markdown sayfalar, bölümler, sayfa ağacı ve içindekiler, ayrıntılı anlık arama, SEO uyumlu çıktı. LLM dostu: her sayfa .md olarak açılır ve kopyalanır, /wiki/llms.txt ve llms-full.txt, ChatGPT / Claude'da aç. Flux AI ile "Wiki'ye sor" ve MCP araçları (ara, oku, listele, yaz). Eklenti ve tema yazma, eklentileri AI destekli yapma rehberleriyle gelir.
 * Version:           0.2.6
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flx-wiki
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-wiki
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXWK_VERSION' ) ) {
	return;
}

define( 'FLXWK_VERSION', '0.2.6' );
define( 'FLXWK_FILE', __FILE__ );
define( 'FLXWK_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXWK_URL', plugin_dir_url( __FILE__ ) );
define( 'FLXWK_PT', 'flx_wiki' );
define( 'FLXWK_TAX', 'flx_wiki_section' );

require_once FLXWK_DIR . 'includes/helpers.php';
require_once FLXWK_DIR . 'includes/markdown.php';
require_once FLXWK_DIR . 'includes/post-type.php';
require_once FLXWK_DIR . 'includes/search.php';
require_once FLXWK_DIR . 'includes/front.php';
require_once FLXWK_DIR . 'includes/llms.php';
require_once FLXWK_DIR . 'includes/rest.php';
require_once FLXWK_DIR . 'includes/ai.php';
require_once FLXWK_DIR . 'includes/mcp.php';
require_once FLXWK_DIR . 'includes/seed.php';
require_once FLXWK_DIR . 'includes/preset.php'; // Flux Setup ön ayarları wiki sayfası yazabilir.
require_once FLXWK_DIR . 'includes/system-checks.php';
require_once FLXWK_DIR . 'includes/app.php';
if ( is_admin() ) {
	require_once FLXWK_DIR . 'includes/admin.php';
}

/*
 * Çeviri: kaynak metinler Türkçe; site/kullanıcı dili İngilizce olduğunda languages/ içindeki çeviri kullanılır.
 * en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-wiki', false, dirname( plugin_basename( FLXWK_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-wiki' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXWK_DIR . 'languages/flx-wiki-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

register_activation_hook(
	FLXWK_FILE,
	function () {
		flxwk_register_types();
		flxwk_rewrite_rules();
		flush_rewrite_rules();
		update_option( 'flxwk_flush', FLXWK_VERSION, false );
		if ( ! get_option( 'flxwk_seeded' ) ) {
			flxwk_seed_import( false );
			update_option( 'flxwk_seeded', FLXWK_VERSION, false );
		}
	}
);
register_deactivation_hook(
	FLXWK_FILE,
	function () {
		flush_rewrite_rules();
	}
);

/* Sürüm değişince kalıcı bağlantılar yeniden yazılır (yeni kural eklenmiş olabilir). */
add_action(
	'init',
	function () {
		if ( get_option( 'flxwk_flush' ) !== FLXWK_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'flxwk_flush', FLXWK_VERSION, false );
		}
	},
	99
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXWK_FILE ),
	function ( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( flxwk_admin_url() ) . '">' . esc_html__( 'Settings', 'flx-wiki' ) . '</a>',
			'<a href="' . esc_url( flxwk_home_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open wiki', 'flx-wiki' ) . '</a>'
		);
		return $links;
	}
);

/** Güncellemeler ekranında paket logosu. */
add_filter(
	'flxu_package_icons',
	function ( $icons, $slug ) {
		if ( 'flx-wiki' === $slug && ! $icons ) {
			$url   = FLXWK_URL . 'assets/icon.svg?ver=' . FLXWK_VERSION;
			$icons = array( 'svg' => $url, '1x' => $url, '2x' => $url );
		}
		return $icons;
	},
	10,
	2
);

/* Flux Admin: sayfa başlığı (logo + açıklama), menü logosu ve "İçerik" grubunda yer. */
add_filter(
	'flxa_page_heads',
	function ( $map ) {
		$map['flx-wiki'] = 'flx-wiki';
		return $map;
	}
);
add_filter(
	'flxa_package_heads',
	function ( $heads ) {
		$heads['flx-wiki'] = array( 'Flux Wiki', __( 'Developer wiki: Markdown pages, search, LLM and MCP support', 'flx-wiki' ) );
		return $heads;
	}
);
add_filter(
	'flxa_menu_logos',
	function ( $m ) {
		$m[ 'edit.php?post_type=' . FLXWK_PT ] = 'flx-wiki.svg';
		return $m;
	}
);
add_filter(
	'flxa_default_groups',
	function ( $groups ) {
		$slug = 'edit.php?post_type=' . FLXWK_PT;
		if ( isset( $groups['content'][1] ) && is_array( $groups['content'][1] ) && ! in_array( $slug, $groups['content'][1], true ) ) {
			$groups['content'][1][] = $slug;
		}
		return $groups;
	}
);
