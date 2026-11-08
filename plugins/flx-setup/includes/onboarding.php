<?php
/**
 * Kurulum sihirbazı: FluxWP Panel ilk etkinleşince açılır, istenirse atlanır.
 *
 * Adımlar: tanıtım → eklenti seçimi → görünüm (ön ayar, renk, site adı) → kurulum → hazır.
 * Kurulum güncelleyicinin flxu_plan / flxu_step uçlarını kullanır (her paket ayrı istek; paylaşımlı
 * sunucuların süre sınırına takılmaz). Tema, renk, ön ayar ve site adı flxk_onboarding_step ile uygulanır.
 *
 * Durum: option flxk_onboarding = pending | done | skipped. Sihirbaz FluxWP Panel › Kurulum sihirbazı'ndan her zaman açılır.
 */

defined( 'ABSPATH' ) || exit;

final class FLXK_Onboarding {

	const PAGE  = 'flx-setup-wizard';
	const NONCE = 'flxk_onboarding';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ), 20 );
		add_action( 'wp_ajax_flxk_onboarding_step', array( __CLASS__, 'ajax_step' ) );
		add_action( 'wp_ajax_flxk_onboarding_logo', array( __CLASS__, 'ajax_logo' ) );
		add_action( 'wp_ajax_flxk_onboarding_updates', array( __CLASS__, 'ajax_updates' ) );
	}

	/** Sihirbazın "Sayfalar" sekmesindeki sayfalar: anahtar => [başlık, içerik]. */
	private static function page_defs(): array {
		$p = static function ( $text ) {
			return '<!-- wp:paragraph --><p>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->';
		};
		return (array) apply_filters(
			'flxk_onboarding_pages',
			array(
				'blog'    => array( __( 'Blog', 'flx-setup' ), '' ),
				'about'   => array( __( 'About us', 'flx-setup' ), $p( __( 'Tell visitors who you are and what you do.', 'flx-setup' ) ) ),
				'contact' => array( _x( 'Contact', 'context_ac58193b5653', 'flx-setup' ), $p( __( 'Add your address, phone and e-mail here.', 'flx-setup' ) ) ),
				'faq'     => array( __( 'FAQ', 'flx-setup' ), $p( __( 'Add common questions and their answers here.', 'flx-setup' ) ) ),
			)
		);
	}

	/** Logo yükleme: Medya'ya ekler, kurulumda tema logosu yapılır. */
	public static function ajax_logo(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( self::cap() ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'flx-setup' ) ), 403 );
		}
		$type = (string) ( $_FILES['logo']['type'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( empty( $_FILES['logo'] ) || ! in_array( $type, array( 'image/png', 'image/jpeg', 'image/webp', 'image/gif' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Upload a PNG, JPG, WebP or GIF.', 'flx-setup' ) ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_upload( 'logo', 0 );
		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => $id->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'id'  => (int) $id,
				'url' => (string) wp_get_attachment_image_url( $id, 'medium' ),
			)
		);
	}

	public static function cap(): string {
		return (string) apply_filters( 'flxk_onboarding_cap', 'install_plugins' );
	}

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	public static function menu(): void {
		$hook = add_submenu_page( FLXU_Admin::SLUG, __( 'Setup wizard', 'flx-setup' ), __( 'Setup wizard', 'flx-setup' ), self::cap(), self::PAGE, '__return_null', 1 );
		if ( $hook ) {
			add_action( 'load-' . $hook, array( __CLASS__, 'render' ) );
		}
	}

	/** Etkinleştirmeden hemen sonra bir kez sihirbaza götürür. */
	public static function maybe_redirect(): void {
		if ( ! get_transient( 'flxk_onboarding_redirect' ) ) {
			return;
		}
		if ( wp_doing_ajax() || is_network_admin() || ! current_user_can( self::cap() ) || 'pending' !== get_option( 'flxk_onboarding' ) ) {
			return;
		}
		delete_transient( 'flxk_onboarding_redirect' );
		wp_safe_redirect( self::url() );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Veri                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * "Neyle başlayalım?" ekranının kartları. Her kart bir ya da birkaç paketi birlikte seçer.
	 * art: ekran görüntüsü yerine gösterilecek görsel (boşsa ilk paketin ekran görüntüsü).
	 * solo: diğerlerinden bağımsız seçilir. Solo olmayan kartlardan (site türü) aynı anda yalnız biri seçilir.
	 * FluxWP teması kart olarak gösterilmez, her kurulumda gelir (ön ayar, renk ve sayfalar ona bağlı).
	 */
	private static function core(): array {
		$items = array(
			array(
				'key'   => 'admin',
				'slugs' => array( 'flx-admin' ),
				'name'  => __( 'Admin theme', 'flx-setup' ),
				'desc'  => __( 'Flux Admin: a redesigned admin panel with a command palette, mobile menu and light/dark mode.', 'flx-setup' ),
				'art'   => '',
				'solo'  => true,
			),
			array(
				'key'   => 'store',
				'slugs' => array( 'flx-commerce' ),
				'name'  => __( 'Store', 'flx-setup' ),
				'desc'  => __( 'Flux Commerce: sales dashboard, customers, reports and bank transfer with receipts.', 'flx-setup' ),
				'art'   => '',
			),
			array(
				'key'   => 'charity',
				'slugs' => array( 'flx-donate', 'flx-certificates' ),
				'name'  => __( 'Nonprofit and donations', 'flx-setup' ),
				'desc'  => __( 'Flux Donate and Flux Certificates: donation types, a one-page checkout and a certificate in every donor\'s name.', 'flx-setup' ),
				'art'   => FLXK_URL . 'assets/onboarding/art/charity.svg',
			),
		);
		return array_values( (array) apply_filters( 'flxk_onboarding_core', $items ) );
	}

	/** Başlangıç kartlarındaki tüm paketler. */
	private static function core_slugs(): array {
		$out = array();
		foreach ( self::core() as $c ) {
			$out = array_merge( $out, (array) ( $c['slugs'] ?? array() ) );
		}
		return $out;
	}

	/**
	 * Eklenti adımının kategorileri; her biri ayrı ekran. Sunucu kategori bildirdiyse onlar (sırası ve TR/EN adlarıyla)
	 * ve paketlerin sunucudaki kategorisi kullanılır. Kategorisi olmayan paket "Diğer"e düşer. Sunucu kategori
	 * bildirmediyse tek bir "Eklentiler" ekranı gösterilir.
	 */
	private static function groups(): array {
		$remote = FLXU_Updater::categories();
		if ( $remote ) {
			usort(
				$remote,
				static function ( $a, $b ) {
					return array( $a['order'], $a['key'] ) <=> array( $b['order'], $b['key'] );
				}
			);
			$pick = static function ( array $t ): string {
				$lang = self::lang();
				return (string) ( $t[ $lang ] ?? ( $t['en'] ?? ( $t ? reset( $t ) : '' ) ) );
			};
			$g = array();
			foreach ( $remote as $c ) {
				if ( 'other' === $c['key'] ) {
					continue;
				}
				$g[] = array( 'key' => $c['key'], 'name' => $pick( $c['name'] ), 'lead' => $pick( $c['lead'] ), 'slugs' => array(), 'remote' => true );
			}
			$g   = array_values( (array) apply_filters( 'flxk_onboarding_groups', $g ) );
			$g[] = array( 'key' => 'other', 'name' => __( 'Other', 'flx-setup' ), 'lead' => __( 'Other FluxWP packages.', 'flx-setup' ), 'slugs' => array() );
			return $g;
		}
		return array( array( 'key' => 'other', 'name' => __( 'Plugins', 'flx-setup' ), 'lead' => __( 'FluxWP packages.', 'flx-setup' ), 'slugs' => array() ) );
	}

	private static function group_of( string $slug, array $groups, string $remote = '' ): string {
		if ( '' !== $remote ) {
			foreach ( $groups as $g ) {
				if ( $g['key'] === $remote ) {
					return $remote;
				}
			}
		}
		foreach ( $groups as $g ) {
			if ( in_array( $slug, (array) $g['slugs'], true ) ) {
				return (string) $g['key'];
			}
		}
		return 'other';
	}

	/**
	 * Sihirbazda gösterilmeyen katalog paketleri: sunucunun private dediği her paket, kendisi, ön ayarlar, geçiş araçları.
	 * Filtre: flxk_onboarding_hidden.
	 */
	private static function hidden( string $slug, array $p = array() ): bool {
		if ( isset( $p['public'] ) && ! $p['public'] ) {
			return true;
		}
		$list = (array) apply_filters( 'flxk_onboarding_hidden', array( 'flx-setup', 'flx-migrate' ) );
		return in_array( $slug, $list, true ) || 0 === strpos( $slug, 'flx-preset-' );
	}

	private static function plugins( array $catalog, array $rows ): array {
		$state = array();
		foreach ( $rows as $r ) {
			$state[ $r['slug'] ] = $r;
		}
		$lang   = self::lang();
		$out    = array();
		$core   = self::core_slugs();
		$groups = self::groups();
		foreach ( $catalog as $slug => $p ) {
			if ( 'plugin' !== $p['type'] || self::hidden( $slug, $p ) ) {
				continue;
			}
			$icons = FLXU_Updater::icons( $slug );
			$out[] = array(
				'slug'    => $slug,
				'name'    => $p['name'],
				'version' => $p['version'],
				// Açıklama, öneri, sıra ve önizleme güncelleme sunucusundan (sitenin dilinde, yoksa İngilizce).
				'desc'    => (string) ( $p['desc'][ $lang ] ?? ( $p['desc']['en'] ?? '' ) ),
				'rec'     => ! empty( $p['rec'] ),
				'icon'    => (string) ( $icons['svg'] ?? ( $icons['1x'] ?? '' ) ),
				'thumb'   => (string) ( $p['shots'][ $lang ] ?? ( $p['shots']['en'] ?? '' ) ),
				'active'  => ! empty( $state[ $slug ]['active'] ),
				'core'    => in_array( $slug, $core, true ),
				'group'   => self::group_of( $slug, $groups, (string) ( $p['category'] ?? '' ) ),
				'order'   => isset( $p['order'] ) ? (int) $p['order'] : 9999,
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return array( $a['order'], $a['name'] ) <=> array( $b['order'], $b['name'] );
			}
		);
		return $out;
	}

	/**
	 * Ön ayar seçenekleri: kurulu ön ayarlar, katalogdaki flx-preset-* eklentileri, FluxWP temasının demo ön ayarı, Boş.
	 */
	private static function presets( array $catalog ): array {
		$out = array();
		foreach ( flxk_presets() as $id => $p ) {
			if ( empty( $p['error'] ) ) {
				$out[ $id ] = array(
					'id'      => $id,
					'name'    => (string) $p['name'],
					'desc'    => (string) ( $p['description'] ?? '' ),
					'package' => '',
				);
			}
		}
		if ( ! isset( $out['flux-demo'] ) && ( isset( $catalog['fluxwp'] ) || wp_get_theme( 'fluxwp' )->exists() ) ) {
			$out['flux-demo'] = array(
				'id'      => 'flux-demo',
				'name'    => __( 'FluxWP demo', 'flx-setup' ),
				'desc'    => __( 'Sample home page, products and menus.', 'flx-setup' ),
				'package' => '',
			);
		}
		if ( isset( $out['flux-demo'] ) ) {
			$out['flux-demo']['theme'] = true; // FluxWP temasıyla gelir; tema seçilmezse gösterilmez.
		}
		foreach ( $catalog as $slug => $p ) {
			if ( 0 !== strpos( $slug, 'flx-preset-' ) || 'plugin' !== $p['type'] ) {
				continue;
			}
			$id = substr( $slug, strlen( 'flx-preset-' ) );
			if ( ! isset( $out[ $id ] ) ) {
				$out[ $id ] = array(
					'id'      => $id,
					'name'    => preg_replace( '/\s*Preset$/i', '', (string) $p['name'] ),
					'desc'    => '',
					'package' => $slug,
				);
			} else {
				$out[ $id ]['package'] = $slug;
			}
		}
		$out['blank'] = array(
			'id'      => 'blank',
			'name'    => __( 'Blank', 'flx-setup' ),
			'desc'    => __( 'Theme and colour only', 'flx-setup' ),
			'package' => '',
		);
		return array_values( (array) apply_filters( 'flxk_onboarding_presets', $out, $catalog ) );
	}

	/** FluxWP temasının stil varyasyonları (tema kuruluysa dosyadan, değilse bilinen liste). */
	private static function styles(): array {
		$fallback = array(
			array( 'antrasit', __( 'Anthracite', 'flx-setup' ), '#1F2937', '#111827', '#3B82F6', '#F2EEE7', '#F8F5F0', '#E6E0D6', '#111827' ),
			array( 'mavi', __( 'Blue', 'flx-setup' ), '#2563EB', '#1D4ED8', '#F59E0B', '#EFF6FF', '#F8FAFC', '#E2E8F0', '#0F172A' ),
			array( 'petrol', __( 'Teal', 'flx-setup' ), '#0F766E', '#115E59', '#F59E0B', '#ECFDF9', '#F7FAFA', '#E2E8F0', '#0B2F2C' ),
			array( 'indigo', __( 'Indigo', 'flx-setup' ), '#4338CA', '#3730A3', '#F59E0B', '#EEF2FF', '#F7F8FE', '#E0E7FF', '#1E1B4B' ),
			array( 'orman', __( 'Forest', 'flx-setup' ), '#0E7A56', '#0A5A40', '#6DBE45', '#EAF5EE', '#F5FAF6', '#DCE9E0', '#14324A' ),
		);
		$out   = array();
		$theme = wp_get_theme( 'fluxwp' );
		if ( $theme->exists() ) {
			foreach ( (array) glob( $theme->get_stylesheet_directory() . '/styles/*.json' ) as $f ) {
				$j = json_decode( (string) file_get_contents( $f ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$c = array();
				foreach ( (array) ( $j['settings']['color']['palette'] ?? array() ) as $row ) {
					if ( isset( $row['slug'], $row['color'] ) ) {
						$c[ $row['slug'] ] = $row['color'];
					}
				}
				if ( empty( $c['primary'] ) ) {
					continue;
				}
				$key         = basename( $f, '.json' );
				$out[ $key ] = array( $key, (string) ( $j['title'] ?? $key ), $c['primary'], $c['primary-dark'] ?? $c['primary'], $c['leaf'] ?? $c['primary'], $c['mint'] ?? '#F2EEE7', $c['mint-soft'] ?? '#F8F5F0', $c['line'] ?? '#E6E0D6', $c['ink'] ?? '#111827' );
			}
		}
		$list = $out ? $out : $fallback;
		$keys  = array( 'key', 'name', 'p', 'pd', 'l', 'm', 'ms', 'sl', 'ink' );
		$moods = array(
			'antrasit' => __( 'Calm and serious; fits anything.', 'flx-setup' ),
			'mavi'     => __( 'Trustworthy, corporate.', 'flx-setup' ),
			'petrol'   => __( 'Quiet and modern.', 'flx-setup' ),
			'indigo'   => __( 'Lively; tech and education.', 'flx-setup' ),
			'orman'    => __( 'Nature; for nonprofits and foundations.', 'flx-setup' ),
		);
		$res   = array();
		foreach ( $list as $row ) {
			$r         = array_combine( $keys, array_values( $row ) );
			$r['desc'] = $moods[ $r['key'] ] ?? '';
			$res[]     = $r;
		}
		usort(
			$res,
			static function ( $a, $b ) {
				$order = array( 'antrasit', 'mavi', 'petrol', 'indigo', 'orman' );
				$ia    = array_search( $a['key'], $order, true );
				$ib    = array_search( $b['key'], $order, true );
				return array( false === $ia ? 99 : $ia, $a['name'] ) <=> array( false === $ib ? 99 : $ib, $b['name'] );
			}
		);
		return $res;
	}

	private static function lang(): string {
		return 0 === strpos( determine_locale(), 'tr' ) ? 'tr' : 'en';
	}

	/** Bitince önerilen işler (yalnız seçilip kurulan paketler için). */
	private static function next_steps(): array {
		return array(
			array( 'flx-mail', __( 'Connect e-mail', 'flx-setup' ), __( 'Without SMTP, receipts and notifications are not delivered.', 'flx-setup' ), __( 'Flux Mail › Settings', 'flx-setup' ), admin_url( 'admin.php?page=flxi-ayarlar' ) ),
			array( 'flx-admin', __( 'Upload your logo', 'flx-setup' ), __( 'Used in the admin and in e-mails.', 'flx-setup' ), __( 'Settings › Admin theme', 'flx-setup' ), admin_url( 'options-general.php?page=flx-admin' ) ),
			array( 'flx-ai', __( 'Add an AI key', 'flx-setup' ), __( 'Needed for assistants and AI tools.', 'flx-setup' ), __( 'Flux AI & MCP › Settings', 'flx-setup' ), admin_url( 'admin.php?page=flx-ai-settings' ) ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Ekran                                                               */
	/* ------------------------------------------------------------------ */

	public static function render(): void {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-setup' ) );
		}
		$catalog = FLXU_Updater::instance()->catalog();
		$rows    = FLXU_Admin::rows( $catalog );
		$status  = FLXU_Updater::status();
		$data    = array(
			'ajax'      => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( self::NONCE ),
			'flxuNonce' => wp_create_nonce( FLXU_Ajax::NONCE ),
			'lang'      => self::lang(),
			'plugins'   => self::plugins( $catalog, $rows ),
			'core'      => array_map(
				static function ( $c ) {
					return array(
						'key'   => (string) $c['key'],
						'slugs' => array_values( (array) $c['slugs'] ),
						'name'  => (string) $c['name'],
						'desc'  => (string) $c['desc'],
						'art'   => (string) ( $c['art'] ?? '' ),
						'solo'  => ! empty( $c['solo'] ),
					);
				},
				self::core()
			),
			'groups'    => array_map(
				static function ( $g ) {
					return array( 'key' => (string) $g['key'], 'name' => (string) $g['name'], 'lead' => (string) $g['lead'] );
				},
				self::groups()
			),
			'presets'   => self::presets( $catalog ),
			'styles'    => self::styles(),
			'theme'     => isset( $catalog['fluxwp'] ) || wp_get_theme( 'fluxwp' )->exists() ? 'fluxwp' : '',
			'themeThumb' => (string) ( $catalog['fluxwp']['shots'][ self::lang() ] ?? ( $catalog['fluxwp']['shots']['en'] ?? '' ) ),
			'themeIcon' => (string) ( FLXU_Updater::icons( 'fluxwp' )['svg'] ?? '' ),
			'setupIcon' => FLXK_URL . 'assets/icon.svg',
			'legal'     => flxk_legal_urls(),
			'usage'     => flxk_telemetry_enabled(),
			'terms'     => ! empty( flxk_terms_accepted() ),
			'siteName'  => get_bloginfo( 'name' ),
			'tagline'   => get_bloginfo( 'description' ),
			'pageDefs'  => array_map(
				static function ( $k, $d ) {
					return array( 'key' => $k, 'title' => $d[0] );
				},
				array_keys( self::page_defs() ),
				array_values( self::page_defs() )
			),
			'canUpload' => current_user_can( 'upload_files' ),
			'host'      => wp_parse_url( home_url(), PHP_URL_HOST ),
			'server'    => wp_parse_url( FLXU_Updater::server(), PHP_URL_HOST ),
			'offline'   => ! $catalog,
			'connError' => $catalog ? '' : (string) ( $status['error'] ?? '' ),
			'home'      => home_url( '/' ),
			'dashboard' => admin_url(),
			'packages'  => FLXU_Admin::url(),
			'next'      => array_map(
				static function ( $n ) {
					return array(
						'slug'  => $n[0],
						'title' => $n[1],
						'text'  => $n[2],
						'where' => $n[3],
						'url'   => $n[4],
						'icon'  => (string) ( FLXU_Updater::icons( $n[0] )['svg'] ?? '' ),
					);
				},
				self::next_steps()
			),
			'i18n'      => self::strings(),
		);
		$ver = FLXK_VERSION;
		$url = FLXK_URL . 'assets/onboarding/';
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( 'FluxWP Panel · ' . get_bloginfo( 'name' ) ); ?></title>
<style>
@font-face{font-family:"Geist";font-style:normal;font-weight:400 700;font-display:swap;src:url(<?php echo esc_url( $url . 'fonts/geist-latin-ext.woff2' ); ?>) format("woff2");unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}
@font-face{font-family:"Geist";font-style:normal;font-weight:400 700;font-display:swap;src:url(<?php echo esc_url( $url . 'fonts/geist-latin.woff2' ); ?>) format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
@font-face{font-family:"Geist Mono";font-style:normal;font-weight:400 500;font-display:swap;src:url(<?php echo esc_url( $url . 'fonts/geist-mono-latin-ext.woff2' ); ?>) format("woff2");unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}
@font-face{font-family:"Geist Mono";font-style:normal;font-weight:400 500;font-display:swap;src:url(<?php echo esc_url( $url . 'fonts/geist-mono-latin.woff2' ); ?>) format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
</style>
<link rel="stylesheet" href="<?php echo esc_url( $url . 'onboarding.css?ver=' . $ver ); ?>">
</head>
<body>
<div id="flxk-ob" data-logo="<?php echo esc_url( $url . 'logo.svg' ); ?>"></div>
<noscript><p style="padding:32px"><?php esc_html_e( 'The setup wizard needs JavaScript.', 'flx-setup' ); ?> <a href="<?php echo esc_url( FLXU_Admin::url() ); ?>"><?php esc_html_e( 'Go to the Packages screen.', 'flx-setup' ); ?></a></p></noscript>
<script>window.FLXK_OB=<?php echo wp_json_encode( $data ); ?>;</script>
<script src="<?php echo esc_url( $url . 'onboarding.js?ver=' . $ver ); ?>"></script>
<?php if ( class_exists( 'FLXK_Telemetry' ) ) { FLXK_Telemetry::onboarding_script(); } ?>
</body>
</html>
		<?php
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* AJAX: tema, renk, ön ayar, site adı, bitir / atla                    */
	/* ------------------------------------------------------------------ */

	public static function ajax_step(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( self::cap() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'flx-setup' ) ), 403 );
		}
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$kind  = sanitize_key( wp_unslash( $_POST['kind'] ?? '' ) );
		$value = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );
		ob_start();
		$res = self::run( $kind, $value );
		ob_end_clean();
		wp_send_json_success( $res );
	}

	/**
	 * Kuruluma başlamadan önce: sunucuya sorar, yüklü paketlerden güncellemesi bekleyenleri döndürür.
	 * FluxWP Panel'in kendisi varsa en başta ve self: true ile gelir (güncellenince sihirbaz yeniden açılır).
	 */
	public static function ajax_updates(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( self::cap() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'flx-setup' ) ), 403 );
		}
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		FLXU_Updater::instance()->check_now();
		$status = FLXU_Updater::status();
		$items  = array();
		if ( ! empty( $status['ok'] ) ) {
			foreach ( FLXU_Admin::rows( FLXU_Updater::instance()->catalog() ) as $r ) {
				if ( 'update' !== $r['state'] || ! current_user_can( 'theme' === $r['type'] ? 'update_themes' : 'update_plugins' ) ) {
					continue;
				}
				$items[] = array(
					'slug' => $r['slug'],
					'name' => $r['name'],
					'from' => $r['installed'],
					'to'   => $r['latest'],
					'icon' => (string) ( FLXU_Updater::icons( $r['slug'] )['svg'] ?? '' ),
					'self' => 'flx-setup' === $r['slug'],
				);
			}
			usort(
				$items,
				static function ( $a, $b ) {
					return array( ! $a['self'], $a['name'] ) <=> array( ! $b['self'], $b['name'] );
				}
			);
		}
		wp_send_json_success(
			array(
				'ok'    => ! empty( $status['ok'] ),
				'error' => (string) ( $status['error'] ?? '' ),
				'items' => $items,
			)
		);
	}

	private static function run( string $kind, string $value ): array {
		switch ( $kind ) {
			case 'name':
				if ( '' !== $value && current_user_can( 'manage_options' ) ) {
					update_option( 'blogname', $value );
				}
				return self::ok( sprintf( /* translators: %s: site name */ __( 'Site name: %s', 'flx-setup' ), get_bloginfo( 'name' ) ) );

			case 'tagline':
				if ( current_user_can( 'manage_options' ) ) {
					update_option( 'blogdescription', $value );
				}
				return self::ok( sprintf( /* translators: %s: tagline */ __( 'Tagline: %s', 'flx-setup' ), $value ) );

			case 'logo':
				$id = absint( $value );
				if ( ! $id || 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_theme_options' ) ) {
					return self::fail( __( 'Could not set the logo.', 'flx-setup' ) );
				}
				set_theme_mod( 'custom_logo', $id );
				if ( ! get_option( 'site_icon' ) ) {
					update_option( 'site_icon', $id );
				}
				return self::ok( __( 'Logo set.', 'flx-setup' ) );

			case 'pages':
				if ( ! current_user_can( 'publish_pages' ) ) {
					return self::fail( __( 'You are not allowed to create pages.', 'flx-setup' ) );
				}
				$defs  = self::page_defs();
				$made  = array();
				$lines = array();
				foreach ( array_filter( array_map( 'sanitize_key', explode( ',', $value ) ) ) as $key ) {
					if ( ! isset( $defs[ $key ] ) ) {
						continue;
					}
					list( $title, $content ) = $defs[ $key ];
					$existing = get_page_by_path( sanitize_title( $title ) );
					$id       = $existing ? (int) $existing->ID : (int) wp_insert_post(
						array(
							'post_type'    => 'page',
							'post_status'  => 'publish',
							'post_title'   => $title,
							'post_content' => $content,
						)
					);
					if ( ! $id ) {
						$lines[] = array( 'ok' => false, 'text' => $title );
						continue;
					}
					if ( 'blog' === $key && ! get_option( 'page_for_posts' ) ) {
						update_option( 'page_for_posts', $id );
					}
					$made[]  = $title;
					$lines[] = array( 'ok' => true, 'text' => sprintf( /* translators: %s: page title */ __( 'Page: %s', 'flx-setup' ), $title ) );
				}
				return array(
					'ok'    => true,
					'lines' => $lines ? $lines : array( array( 'ok' => true, 'text' => __( 'No new pages.', 'flx-setup' ) ) ),
					'pages' => self::count_pages(),
				);

			case 'theme':
				$theme = wp_get_theme( 'fluxwp' );
				if ( ! $theme->exists() ) {
					return self::fail( __( 'The FluxWP theme is not installed.', 'flx-setup' ) );
				}
				if ( 'fluxwp' !== get_stylesheet() && 'fluxwp' !== get_template() ) {
					if ( ! current_user_can( 'switch_themes' ) ) {
						return self::fail( __( 'You are not allowed to switch themes.', 'flx-setup' ) );
					}
					switch_theme( 'fluxwp' );
				}
				return self::ok( __( 'FluxWP theme is active.', 'flx-setup' ) );

			case 'style':
				return self::apply_style( $value );

			case 'preset':
				if ( 'blank' === $value || '' === $value ) {
					return self::ok( __( 'No preset applied (Blank).', 'flx-setup' ) );
				}
				if ( ! current_user_can( flxk_cap() ) ) {
					return self::fail( __( 'You are not allowed to apply presets.', 'flx-setup' ) );
				}
				flxk_presets( true );
				if ( flxk_state( $value ) ) {
					return self::ok( __( 'The preset is already applied.', 'flx-setup' ) );
				}
				$p = flxk_get_preset( $value );
				if ( ! $p ) {
					return self::fail( __( 'Preset not found. If the package is installed, apply it under Appearance › Presets.', 'flx-setup' ) );
				}
				$res = flxk_apply_preset( $value, flxk_site_is_fresh() ? 'apply' : 'fill' );
				if ( is_wp_error( $res ) ) {
					return self::fail( $res->get_error_message() );
				}
				$q = array_values( array_diff( (array) get_option( 'flxk_install_queue', array() ), array( $value ) ) );
				update_option( 'flxk_install_queue', $q, false );
				/* translators: %s: preset name */
				return self::ok( sprintf( __( '%s preset applied.', 'flx-setup' ), $p['name'] ), array( 'pages' => self::count_pages() ) );

			case 'consent':
				// value: "1" kullanım verisi izni, "0" yok. Şartlar onayı bu adımla kaydedilir.
				flxk_accept_terms();
				flxk_set_telemetry( '1' === $value );
				return self::ok( '1' === $value ? __( 'Usage data consent saved.', 'flx-setup' ) : __( 'Usage data will not be sent.', 'flx-setup' ) );

			case 'done':
			case 'skip':
				update_option( 'flxk_onboarding', 'done' === $kind ? 'done' : 'skipped', false );
				delete_transient( 'flxk_onboarding_redirect' );
				return self::ok( 'done' === $kind ? __( 'Setup finished.', 'flx-setup' ) : __( 'Wizard skipped.', 'flx-setup' ) );
		}
		return self::fail( __( 'Unknown action.', 'flx-setup' ) );
	}

	/** Stil varyasyonunu kullanıcı global stiline yazar (Site Düzenleyici › Stiller'de seçmekle aynı). */
	private static function apply_style( string $key ): array {
		if ( '' === $key ) {
			return self::ok( __( 'Colour unchanged.', 'flx-setup' ) );
		}
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return self::fail( __( 'You are not allowed to edit the appearance.', 'flx-setup' ) );
		}
		if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) || ! wp_theme_has_theme_json() ) {
			return self::fail( __( 'The active theme does not support colour styles.', 'flx-setup' ) );
		}
		$file = get_stylesheet_directory() . '/styles/' . sanitize_file_name( $key ) . '.json';
		$var  = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $var ) ) {
			/* translators: %s: style key */
			return self::fail( sprintf( __( 'The theme has no %s colour style.', 'flx-setup' ), $key ) );
		}
		$user = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), true );
		if ( empty( $user['ID'] ) ) {
			return self::fail( __( 'Could not create the global styles record.', 'flx-setup' ) );
		}
		$json = array(
			'version'                     => (int) ( $var['version'] ?? 3 ),
			'isGlobalStylesUserThemeJSON' => true,
			'title'                       => (string) ( $var['title'] ?? $key ),
			'settings'                    => (array) ( $var['settings'] ?? array() ),
			'styles'                      => (array) ( $var['styles'] ?? array() ),
		);
		wp_update_post(
			array(
				'ID'           => (int) $user['ID'],
				'post_content' => wp_slash( (string) wp_json_encode( $json ) ),
			)
		);
		if ( method_exists( 'WP_Theme_JSON_Resolver', 'clean_cached_data' ) ) {
			WP_Theme_JSON_Resolver::clean_cached_data();
		}
		if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
			wp_clean_theme_json_cache();
		}
		/* translators: %s: style name */
		return self::ok( sprintf( __( 'Colour style: %s', 'flx-setup' ), $json['title'] ) );
	}

	private static function count_pages(): int {
		return (int) wp_count_posts( 'page' )->publish;
	}

	private static function ok( string $text, array $extra = array() ): array {
		return array(
			'ok'    => true,
			'lines' => array(
				array(
					'ok'   => true,
					'text' => $text,
				),
			),
		) + $extra;
	}

	private static function fail( string $text ): array {
		return array(
			'ok'    => false,
			'lines' => array(
				array(
					'ok'   => false,
					'text' => $text,
				),
			),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Metinler                                                            */
	/* ------------------------------------------------------------------ */

	private static function strings(): array {
		return array(
			'skip'       => __( 'Skip', 'flx-setup' ),
			/* translators: 1: step, 2: total */
			'step'       => __( 'Step %1$s of %2$s', 'flx-setup' ),
			'i_h'        => __( 'Welcome to FluxWP', 'flx-setup' ),
			'i_p'        => __( 'Let\'s set up your site in three steps. Pick the plugins, choose the look, and FluxWP Panel installs the rest.', 'flx-setup' ),
			/* translators: %d: number of plugins */
			'f1b'        => __( '%d plugins', 'flx-setup' ),
			'f1s'        => __( 'Donations, forms, WhatsApp, e-mail, SEO and backups work from one admin.', 'flx-setup' ),
			'f2b'        => __( 'Updates', 'flx-setup' ),
			'f2s'        => __( 'New versions appear on WordPress\'s Updates screen.', 'flx-setup' ),
			'f3b'        => __( 'Change later', 'flx-setup' ),
			'f3s'        => __( 'You can change the preset and colour later under Appearance › Theme settings.', 'flx-setup' ),
			'start'      => __( 'Get started', 'flx-setup' ),
			/* translators: 1: Terms of Use link, 2: Privacy Policy link, 3: KVKK notice link */
			'cTerms'     => __( 'I accept the %1$s and %2$s; I have read the %3$s.', 'flx-setup' ),
			'cTermsL'    => __( 'Terms of Use', 'flx-setup' ),
			'cPrivL'     => __( 'Privacy Policy', 'flx-setup' ),
			'cKvkkL'     => __( 'KVKK Privacy Notice', 'flx-setup' ),
			'cUsage'     => __( 'Help us improve FluxWP (send usage data)', 'flx-setup' ),
			'cUsageS'    => __( 'Optional. WordPress and PHP version, language and time zone, plus anonymous version and error data for FluxWP plugins. The site address and country are always recorded for updates (IPs are not stored). Updates work when this is off; change it under FluxWP Panel › Settings.', 'flx-setup' ),
			'cNeed'      => __( 'Accept the terms to continue.', 'flx-setup' ),
			'skipSelf'   => __( 'I\'ll set it up myself', 'flx-setup' ),
			'time'       => __( 'About 3 minutes', 'flx-setup' ),
			/* translators: 1: category number, 2: category count */
			'groupK'     => __( 'Plugins · %1$s of %2$s', 'flx-setup' ),
			/* translators: %s: package count */
			'wQueued'    => __( '%s packages queued', 'flx-setup' ),
			'activating' => __( 'activating', 'flx-setup' ),
			'selAll'     => __( 'Select all', 'flx-setup' ),
			'selNone'    => __( 'Clear selection', 'flx-setup' ),
			'coreK'      => __( 'Essentials', 'flx-setup' ),
			'hBase'      => __( 'What should we start with?', 'flx-setup' ),
			'leadBase'   => __( 'The admin theme and the kind of site: a store or a nonprofit. Pick one kind; you can install the other later.', 'flx-setup' ),
			'noneBtn'    => __( 'Continue without these', 'flx-setup' ),
			'recB'       => __( 'Recommended', 'flx-setup' ),
			'themeDesc'  => __( 'Block theme with shop, donation and company page templates.', 'flx-setup' ),
			'installedB' => __( 'Installed', 'flx-setup' ),
			'k2'         => __( 'Appearance', 'flx-setup' ),
			'h2'         => __( 'How should your site look?', 'flx-setup' ),
			'lead2'      => __( 'Your name and logo first, then preset, colour and pages. The preview on the right updates right away.', 'flx-setup' ),
			'preset'     => __( 'Preset', 'flx-setup' ),
			'presetHint' => __( 'Pages and menus are created from it.', 'flx-setup' ),
			'color'      => __( 'Colour', 'flx-setup' ),
			'colorHint'  => __( 'The theme and plugins share this colour.', 'flx-setup' ),
			'siteName'   => __( 'Site name', 'flx-setup' ),
			'nameHint'   => __( 'Also shown in e-mails and certificates.', 'flx-setup' ),
			'tabId'      => __( 'Identity', 'flx-setup' ),
			'tabPreset'  => __( 'Preset', 'flx-setup' ),
			'tabColor'   => __( 'Colour', 'flx-setup' ),
			'tabPages'   => __( 'Pages', 'flx-setup' ),
			'tagline'    => __( 'Tagline', 'flx-setup' ),
			'taglineHint' => __( 'Shown under the name on the home page and in search results.', 'flx-setup' ),
			'logo'       => __( 'Logo', 'flx-setup' ),
			'logoHint'   => __( 'A square or wide image (PNG, JPG, WebP). You can change it later.', 'flx-setup' ),
			'logoBtn'    => __( 'Upload logo', 'flx-setup' ),
			'logoChange' => __( 'Change', 'flx-setup' ),
			'logoRemove' => __( 'Delete', 'flx-setup' ),
			'uploading'  => __( 'Uploading…', 'flx-setup' ),
			'pagesHint'  => __( 'Selected pages are created empty for you to fill in later. Existing pages are not created again.', 'flx-setup' ),
			'logoStep'   => __( 'Logo', 'flx-setup' ),
			'pagesStep'  => __( 'Pages', 'flx-setup' ),
			'tagStep'    => __( 'Tagline', 'flx-setup' ),
			'shop'       => __( 'Store', 'flx-setup' ),
			'donate'     => __( 'Donate', 'flx-setup' ),
			'latest'     => __( 'Latest posts', 'flx-setup' ),
			'home'       => __( 'Home', 'flx-setup' ),
			'h4'         => __( 'Your site is ready', 'flx-setup' ),
			'lead4'      => __( 'Plugins installed, preset and colour applied.', 'flx-setup' ),
			'h4warn'     => __( 'Setup finished, some steps did not complete', 'flx-setup' ),
			'lead4warn'  => __( 'The failed steps are below. You can try again from FluxWP Panel › Packages.', 'flx-setup' ),
			'sumPk'      => __( 'plugins', 'flx-setup' ),
			'sumPg'      => __( 'pages', 'flx-setup' ),
			'sumSt'      => __( 'colour', 'flx-setup' ),
			'viewSite'   => __( 'Open site', 'flx-setup' ),
			'toDash'     => __( 'Go to dashboard', 'flx-setup' ),
			'back'       => __( 'Back', 'flx-setup' ),
			'next'       => __( 'Continue', 'flx-setup' ),
			'install'    => __( 'Install', 'flx-setup' ),
			'skipT'      => __( 'Skip setup?', 'flx-setup' ),
			'skipP'      => __( 'Nothing is installed. You can open the wizard later from FluxWP Panel › Setup wizard.', 'flx-setup' ),
			'stay'       => __( 'Cancel', 'flx-setup' ),
			'skipYes'    => __( 'Skip', 'flx-setup' ),
			/* translators: %s: number of plugins */
			'selected'   => __( '%s plugins + FluxWP theme', 'flx-setup' ),
			/* translators: %s: number of plugins */
			'selectedNT' => __( '%s plugins', 'flx-setup' ),
			'keep'       => __( 'Keep this tab open.', 'flx-setup' ),
			'gH'         => __( 'Updates first', 'flx-setup' ),
			'gP'         => __( 'Your installed apps are being updated before setup, so everything is installed on the latest version.', 'flx-setup' ),
			'gCheck'     => __( 'Checking for updates…', 'flx-setup' ),
			'gUpdating'  => __( 'updating', 'flx-setup' ),
			'gUpdated'   => __( 'updated', 'flx-setup' ),
			'gSelf'      => __( 'FluxWP Panel was updated. Reopening the wizard on the new version…', 'flx-setup' ),
			/* translators: %s: error message */
			'gErr'       => __( 'Updates could not be completed: %s Setup does not continue until the updates are done.', 'flx-setup' ),
			'gRetry'     => __( 'Try again', 'flx-setup' ),
			'gPk'        => __( 'Go to Packages', 'flx-setup' ),
			'gUnknown'   => __( 'Unknown error.', 'flx-setup' ),
			'waiting'    => __( 'queued', 'flx-setup' ),
			'installing' => __( 'installing', 'flx-setup' ),
			'installed'  => __( 'installed', 'flx-setup' ),
			'applying'   => __( 'applying', 'flx-setup' ),
			'applied'    => __( 'applied', 'flx-setup' ),
			'failed'     => __( 'error', 'flx-setup' ),
			'msgs'       => array(
				__( 'Downloading packages', 'flx-setup' ),
				__( 'Installing plugins', 'flx-setup' ),
				__( 'Getting your site ready', 'flx-setup' ),
				__( 'Creating pages and menus', 'flx-setup' ),
				__( 'Almost there, final touches', 'flx-setup' ),
			),
			'theme'      => __( 'FluxWP theme', 'flx-setup' ),
			'presetStep' => __( 'Preset', 'flx-setup' ),
			'styleStep'  => __( 'Colour', 'flx-setup' ),
			'nameStep'   => __( 'Site name', 'flx-setup' ),
			'activate'   => __( 'Activating the theme', 'flx-setup' ),
			'doneMsg'    => __( 'Ready', 'flx-setup' ),
			'doneSub'    => __( 'Opening the final screen.', 'flx-setup' ),
			'netErr'     => __( 'Could not reach the server. Check your connection and try again.', 'flx-setup' ),
			'offline'    => __( 'Could not reach the update server; the package list is empty. Check the server under FluxWP Panel › Settings. The theme and preset can still be applied.', 'flx-setup' ),
			'retry'      => __( 'Try again', 'flx-setup' ),
		);
	}
}

FLXK_Onboarding::init();
