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
				'about'   => array( __( 'Hakkımızda', 'flx-setup' ), $p( __( 'Kim olduğunuzu ve ne yaptığınızı burada anlatın.', 'flx-setup' ) ) ),
				'contact' => array( _x( 'İletişim', 'page title', 'flx-setup' ), $p( __( 'Adres, telefon ve e-posta bilgilerinizi buraya yazın.', 'flx-setup' ) ) ),
				'faq'     => array( __( 'Sık sorulan sorular', 'flx-setup' ), $p( __( 'Sık gelen soruları ve cevaplarını buraya ekleyin.', 'flx-setup' ) ) ),
			)
		);
	}

	/** Logo yükleme: Medya'ya ekler, kurulumda tema logosu yapılır. */
	public static function ajax_logo(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( self::cap() ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Yetkiniz yok.', 'flx-setup' ) ), 403 );
		}
		$type = (string) ( $_FILES['logo']['type'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( empty( $_FILES['logo'] ) || ! in_array( $type, array( 'image/png', 'image/jpeg', 'image/webp', 'image/gif' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'PNG, JPG, WebP ya da GIF yükleyin.', 'flx-setup' ) ) );
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
		$hook = add_submenu_page( FLXU_Admin::SLUG, __( 'Kurulum sihirbazı', 'flx-setup' ), __( 'Kurulum sihirbazı', 'flx-setup' ), self::cap(), self::PAGE, '__return_null', 1 );
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
				'name'  => __( 'Yönetim teması', 'flx-setup' ),
				'desc'  => __( 'Flux Admin: yenilenmiş yönetim paneli, komut paleti, mobil menü ve açık/koyu tema.', 'flx-setup' ),
				'art'   => '',
				'solo'  => true,
			),
			array(
				'key'   => 'store',
				'slugs' => array( 'flx-commerce' ),
				'name'  => __( 'Mağaza', 'flx-setup' ),
				'desc'  => __( 'Flux Commerce: satış paneli, müşteriler, raporlar ve dekontlu Havale/EFT.', 'flx-setup' ),
				'art'   => '',
			),
			array(
				'key'   => 'charity',
				'slugs' => array( 'flx-donate', 'flx-certificates' ),
				'name'  => __( 'Dernek ve bağış', 'flx-setup' ),
				'desc'  => __( 'Flux Donate ve Flux Certificates: bağış türleri, tek sayfalık ödeme ve her bağışçıya adına sertifika.', 'flx-setup' ),
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
			$g[] = array( 'key' => 'other', 'name' => __( 'Diğer', 'flx-setup' ), 'lead' => __( 'Diğer FluxWP paketleri.', 'flx-setup' ), 'slugs' => array() );
			return $g;
		}
		return array( array( 'key' => 'other', 'name' => __( 'Eklentiler', 'flx-setup' ), 'lead' => __( 'FluxWP paketleri.', 'flx-setup' ), 'slugs' => array() ) );
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
				'desc'    => __( 'Örnek ana sayfa, ürünler ve menüler.', 'flx-setup' ),
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
			'name'    => __( 'Boş', 'flx-setup' ),
			'desc'    => __( 'Yalnız tema ve renk', 'flx-setup' ),
			'package' => '',
		);
		return array_values( (array) apply_filters( 'flxk_onboarding_presets', $out, $catalog ) );
	}

	/** FluxWP temasının stil varyasyonları (tema kuruluysa dosyadan, değilse bilinen liste). */
	private static function styles(): array {
		$fallback = array(
			array( 'antrasit', __( 'Antrasit', 'flx-setup' ), '#1F2937', '#111827', '#3B82F6', '#F2EEE7', '#F8F5F0', '#E6E0D6', '#111827' ),
			array( 'mavi', __( 'Mavi', 'flx-setup' ), '#2563EB', '#1D4ED8', '#F59E0B', '#EFF6FF', '#F8FAFC', '#E2E8F0', '#0F172A' ),
			array( 'petrol', __( 'Petrol', 'flx-setup' ), '#0F766E', '#115E59', '#F59E0B', '#ECFDF9', '#F7FAFA', '#E2E8F0', '#0B2F2C' ),
			array( 'indigo', __( 'İndigo', 'flx-setup' ), '#4338CA', '#3730A3', '#F59E0B', '#EEF2FF', '#F7F8FE', '#E0E7FF', '#1E1B4B' ),
			array( 'orman', __( 'Orman', 'flx-setup' ), '#0E7A56', '#0A5A40', '#6DBE45', '#EAF5EE', '#F5FAF6', '#DCE9E0', '#14324A' ),
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
			'antrasit' => __( 'Sade ve ciddi, her işe uyar.', 'flx-setup' ),
			'mavi'     => __( 'Güven veren, kurumsal.', 'flx-setup' ),
			'petrol'   => __( 'Sakin ve modern.', 'flx-setup' ),
			'indigo'   => __( 'Canlı, teknoloji ve eğitim.', 'flx-setup' ),
			'orman'    => __( 'Doğa; dernek ve vakıflar için.', 'flx-setup' ),
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
			array( 'flx-mail', __( 'E-postayı bağlayın', 'flx-setup' ), __( 'SMTP olmadan bağış makbuzları ve bildirimler gitmez.', 'flx-setup' ), __( 'Flux Mail › Ayarlar', 'flx-setup' ), admin_url( 'admin.php?page=flxi-ayarlar' ) ),
			array( 'flx-admin', __( 'Logonuzu yükleyin', 'flx-setup' ), __( 'Panelde ve e-postalarda kullanılır.', 'flx-setup' ), __( 'Ayarlar › Yönetim teması', 'flx-setup' ), admin_url( 'options-general.php?page=flx-admin' ) ),
			array( 'flx-ai', __( 'AI anahtarı ekleyin', 'flx-setup' ), __( 'Asistanlar ve AI araçları için gerekli.', 'flx-setup' ), __( 'Flux AI & MCP › Ayarlar', 'flx-setup' ), admin_url( 'admin.php?page=flx-ai-settings' ) ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Ekran                                                               */
	/* ------------------------------------------------------------------ */

	public static function render(): void {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-setup' ) );
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
<noscript><p style="padding:32px"><?php esc_html_e( 'Kurulum sihirbazı JavaScript ister.', 'flx-setup' ); ?> <a href="<?php echo esc_url( FLXU_Admin::url() ); ?>"><?php esc_html_e( 'Paketler ekranına geçin.', 'flx-setup' ); ?></a></p></noscript>
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
			wp_send_json_error( array( 'message' => __( 'Yetkiniz yok.', 'flx-setup' ) ), 403 );
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
			wp_send_json_error( array( 'message' => __( 'Yetkiniz yok.', 'flx-setup' ) ), 403 );
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
				return self::ok( sprintf( /* translators: %s: site name */ __( 'Site adı: %s', 'flx-setup' ), get_bloginfo( 'name' ) ) );

			case 'tagline':
				if ( current_user_can( 'manage_options' ) ) {
					update_option( 'blogdescription', $value );
				}
				return self::ok( sprintf( /* translators: %s: tagline */ __( 'Slogan: %s', 'flx-setup' ), $value ) );

			case 'logo':
				$id = absint( $value );
				if ( ! $id || 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_theme_options' ) ) {
					return self::fail( __( 'Logo ayarlanamadı.', 'flx-setup' ) );
				}
				set_theme_mod( 'custom_logo', $id );
				if ( ! get_option( 'site_icon' ) ) {
					update_option( 'site_icon', $id );
				}
				return self::ok( __( 'Logo ayarlandı.', 'flx-setup' ) );

			case 'pages':
				if ( ! current_user_can( 'publish_pages' ) ) {
					return self::fail( __( 'Sayfa oluşturma yetkiniz yok.', 'flx-setup' ) );
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
					$lines[] = array( 'ok' => true, 'text' => sprintf( /* translators: %s: page title */ __( 'Sayfa: %s', 'flx-setup' ), $title ) );
				}
				return array(
					'ok'    => true,
					'lines' => $lines ? $lines : array( array( 'ok' => true, 'text' => __( 'Yeni sayfa yok.', 'flx-setup' ) ) ),
					'pages' => self::count_pages(),
				);

			case 'theme':
				$theme = wp_get_theme( 'fluxwp' );
				if ( ! $theme->exists() ) {
					return self::fail( __( 'FluxWP teması kurulu değil.', 'flx-setup' ) );
				}
				if ( 'fluxwp' !== get_stylesheet() && 'fluxwp' !== get_template() ) {
					if ( ! current_user_can( 'switch_themes' ) ) {
						return self::fail( __( 'Tema değiştirme yetkiniz yok.', 'flx-setup' ) );
					}
					switch_theme( 'fluxwp' );
				}
				return self::ok( __( 'FluxWP teması etkin.', 'flx-setup' ) );

			case 'style':
				return self::apply_style( $value );

			case 'preset':
				if ( 'blank' === $value || '' === $value ) {
					return self::ok( __( 'Ön ayar uygulanmadı (Boş).', 'flx-setup' ) );
				}
				if ( ! current_user_can( flxk_cap() ) ) {
					return self::fail( __( 'Ön ayar uygulama yetkiniz yok.', 'flx-setup' ) );
				}
				flxk_presets( true );
				if ( flxk_state( $value ) ) {
					return self::ok( __( 'Ön ayar zaten uygulanmış.', 'flx-setup' ) );
				}
				$p = flxk_get_preset( $value );
				if ( ! $p ) {
					return self::fail( __( 'Ön ayar bulunamadı. Paketi kurulduysa Görünüm › Ön ayarlar\'dan uygulayın.', 'flx-setup' ) );
				}
				$res = flxk_apply_preset( $value, flxk_site_is_fresh() ? 'apply' : 'fill' );
				if ( is_wp_error( $res ) ) {
					return self::fail( $res->get_error_message() );
				}
				$q = array_values( array_diff( (array) get_option( 'flxk_install_queue', array() ), array( $value ) ) );
				update_option( 'flxk_install_queue', $q, false );
				/* translators: %s: preset name */
				return self::ok( sprintf( __( '%s ön ayarı uygulandı.', 'flx-setup' ), $p['name'] ), array( 'pages' => self::count_pages() ) );

			case 'consent':
				// value: "1" kullanım verisi izni, "0" yok. Şartlar onayı bu adımla kaydedilir.
				flxk_accept_terms();
				flxk_set_telemetry( '1' === $value );
				return self::ok( '1' === $value ? __( 'Kullanım verisi izni kaydedildi.', 'flx-setup' ) : __( 'Kullanım verisi gönderilmeyecek.', 'flx-setup' ) );

			case 'done':
			case 'skip':
				update_option( 'flxk_onboarding', 'done' === $kind ? 'done' : 'skipped', false );
				delete_transient( 'flxk_onboarding_redirect' );
				return self::ok( 'done' === $kind ? __( 'Kurulum tamamlandı.', 'flx-setup' ) : __( 'Sihirbaz atlandı.', 'flx-setup' ) );
		}
		return self::fail( __( 'Bilinmeyen işlem.', 'flx-setup' ) );
	}

	/** Stil varyasyonunu kullanıcı global stiline yazar (Site Düzenleyici › Stiller'de seçmekle aynı). */
	private static function apply_style( string $key ): array {
		if ( '' === $key ) {
			return self::ok( __( 'Renk değişmedi.', 'flx-setup' ) );
		}
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return self::fail( __( 'Görünüm düzenleme yetkiniz yok.', 'flx-setup' ) );
		}
		if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) || ! wp_theme_has_theme_json() ) {
			return self::fail( __( 'Etkin tema renk stillerini desteklemiyor.', 'flx-setup' ) );
		}
		$file = get_stylesheet_directory() . '/styles/' . sanitize_file_name( $key ) . '.json';
		$var  = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $var ) ) {
			/* translators: %s: style key */
			return self::fail( sprintf( __( '%s renk stili temada yok.', 'flx-setup' ), $key ) );
		}
		$user = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), true );
		if ( empty( $user['ID'] ) ) {
			return self::fail( __( 'Global stil kaydı oluşturulamadı.', 'flx-setup' ) );
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
		return self::ok( sprintf( __( 'Renk stili: %s', 'flx-setup' ), $json['title'] ) );
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
			'skip'       => __( 'Atla', 'flx-setup' ),
			/* translators: 1: step, 2: total */
			'step'       => __( 'Adım %1$s / %2$s', 'flx-setup' ),
			'i_h'        => __( 'FluxWP\'ye hoş geldiniz', 'flx-setup' ),
			'i_p'        => __( 'Sitenizi üç adımda kuralım. Eklentileri seçin, görünümü belirleyin; kurulumu FluxWP Panel yapar.', 'flx-setup' ),
			/* translators: %d: number of plugins */
			'f1b'        => __( '%d eklenti', 'flx-setup' ),
			'f1s'        => __( 'Bağış, form, WhatsApp, e-posta, SEO ve yedek aynı panelde çalışır.', 'flx-setup' ),
			'f2b'        => __( 'Güncellemeler', 'flx-setup' ),
			'f2s'        => __( 'Yeni sürümler WordPress\'in Güncellemeler ekranına gelir.', 'flx-setup' ),
			'f3b'        => __( 'Geri dönüş', 'flx-setup' ),
			'f3s'        => __( 'Ön ayarı ve rengi sonra Görünüm › Tema Ayarları\'ndan değiştirirsiniz.', 'flx-setup' ),
			'start'      => __( 'Başlayalım', 'flx-setup' ),
			/* translators: 1: Terms of Use link, 2: Privacy Policy link, 3: KVKK notice link */
			'cTerms'     => __( '%1$s ve %2$s\'nı kabul ediyorum; %3$s\'ni okudum.', 'flx-setup' ),
			'cTermsL'    => __( 'Kullanım Şartları', 'flx-setup' ),
			'cPrivL'     => __( 'Gizlilik Politikası', 'flx-setup' ),
			'cKvkkL'     => __( 'KVKK Aydınlatma Metni', 'flx-setup' ),
			'cUsage'     => __( 'Ürünü geliştirmemize yardımcı olun (kullanım verisi gönder)', 'flx-setup' ),
			'cUsageS'    => __( 'İsteğe bağlı. WordPress ve PHP sürümü, dil ve saat dilimi ile FluxWP eklentilerinin anonim sürüm ve hata bilgisi. Site adresi ve ülke güncelleme için her zaman kaydedilir (IP saklanmaz). Kapalıyken de güncellemeler çalışır; FluxWP Panel › Ayarlar\'dan değiştirebilirsiniz.', 'flx-setup' ),
			'cNeed'      => __( 'Devam etmek için şartları onaylayın.', 'flx-setup' ),
			'skipSelf'   => __( 'Kendim kurarım', 'flx-setup' ),
			'time'       => __( 'Yaklaşık 3 dakika', 'flx-setup' ),
			/* translators: 1: category number, 2: category count */
			'groupK'     => __( 'Eklentiler · %1$s / %2$s', 'flx-setup' ),
			/* translators: %s: package count */
			'wQueued'    => __( '%s paket sıraya alındı', 'flx-setup' ),
			'activating' => __( 'etkinleştiriliyor', 'flx-setup' ),
			'selAll'     => __( 'Tümünü seç', 'flx-setup' ),
			'selNone'    => __( 'Seçimi temizle', 'flx-setup' ),
			'coreK'      => __( 'Temel', 'flx-setup' ),
			'hBase'      => __( 'Neyle başlayalım?', 'flx-setup' ),
			'leadBase'   => __( 'Yönetim teması ve sitenin türü: mağaza ya da dernek. Türlerden biri seçilir; diğerini sonra da kurabilirsiniz.', 'flx-setup' ),
			'noneBtn'    => __( 'Seçmeden devam et', 'flx-setup' ),
			'recB'       => __( 'Önerilen', 'flx-setup' ),
			'themeDesc'  => __( 'Blok tabanlı tema: mağaza, bağış ve kurumsal sayfa şablonları.', 'flx-setup' ),
			'installedB' => __( 'Kurulu', 'flx-setup' ),
			'k2'         => __( 'Görünüm', 'flx-setup' ),
			'h2'         => __( 'Siteniz nasıl görünsün?', 'flx-setup' ),
			'lead2'      => __( 'Önce adınız ve logonuz, sonra ön ayar, renk ve sayfalar. Sağdaki önizleme hemen değişir.', 'flx-setup' ),
			'preset'     => __( 'Ön ayar', 'flx-setup' ),
			'presetHint' => __( 'Sayfalar ve menüler buna göre oluşur.', 'flx-setup' ),
			'color'      => __( 'Renk', 'flx-setup' ),
			'colorHint'  => __( 'Tema ve eklentiler aynı rengi kullanır.', 'flx-setup' ),
			'siteName'   => __( 'Site adı', 'flx-setup' ),
			'nameHint'   => __( 'E-postalarda ve sertifikalarda da görünür.', 'flx-setup' ),
			'tabId'      => __( 'Kimlik', 'flx-setup' ),
			'tabPreset'  => __( 'Ön ayar', 'flx-setup' ),
			'tabColor'   => __( 'Renk', 'flx-setup' ),
			'tabPages'   => __( 'Sayfalar', 'flx-setup' ),
			'tagline'    => __( 'Slogan', 'flx-setup' ),
			'taglineHint' => __( 'Ana sayfada ve arama sonuçlarında adın altında görünür.', 'flx-setup' ),
			'logo'       => __( 'Logo', 'flx-setup' ),
			'logoHint'   => __( 'Kare ya da yatay bir görsel (PNG, JPG, WebP). Sonra değiştirebilirsiniz.', 'flx-setup' ),
			'logoBtn'    => __( 'Logo yükle', 'flx-setup' ),
			'logoChange' => __( 'Değiştir', 'flx-setup' ),
			'logoRemove' => __( 'Kaldır', 'flx-setup' ),
			'uploading'  => __( 'Yükleniyor…', 'flx-setup' ),
			'pagesHint'  => __( 'Seçtikleriniz boş sayfa olarak açılır, içini sonra doldurursunuz. Var olan sayfalar tekrar açılmaz.', 'flx-setup' ),
			'logoStep'   => __( 'Logo', 'flx-setup' ),
			'pagesStep'  => __( 'Sayfalar', 'flx-setup' ),
			'tagStep'    => __( 'Slogan', 'flx-setup' ),
			'shop'       => __( 'Mağaza', 'flx-setup' ),
			'donate'     => __( 'Bağış yap', 'flx-setup' ),
			'latest'     => __( 'Son yazılar', 'flx-setup' ),
			'home'       => __( 'Ana sayfa', 'flx-setup' ),
			'h4'         => __( 'Siteniz hazır', 'flx-setup' ),
			'lead4'      => __( 'Eklentiler kuruldu, ön ayar ve renk uygulandı.', 'flx-setup' ),
			'h4warn'     => __( 'Kurulum bitti, bazı adımlar tamamlanmadı', 'flx-setup' ),
			'lead4warn'  => __( 'Hatalı adımlar aşağıda. FluxWP Panel › Paketler\'den yeniden deneyebilirsiniz.', 'flx-setup' ),
			'sumPk'      => __( 'eklenti', 'flx-setup' ),
			'sumPg'      => __( 'sayfa', 'flx-setup' ),
			'sumSt'      => __( 'renk', 'flx-setup' ),
			'viewSite'   => __( 'Siteyi aç', 'flx-setup' ),
			'toDash'     => __( 'Panele git', 'flx-setup' ),
			'back'       => __( 'Geri', 'flx-setup' ),
			'next'       => __( 'Devam', 'flx-setup' ),
			'install'    => __( 'Kur', 'flx-setup' ),
			'skipT'      => __( 'Kurulum atlansın mı?', 'flx-setup' ),
			'skipP'      => __( 'Hiçbir şey kurulmaz. Sihirbazı sonra FluxWP Panel › Kurulum sihirbazı\'ndan açabilirsiniz.', 'flx-setup' ),
			'stay'       => __( 'Vazgeç', 'flx-setup' ),
			'skipYes'    => __( 'Atla', 'flx-setup' ),
			/* translators: %s: number of plugins */
			'selected'   => __( '%s eklenti + FluxWP teması', 'flx-setup' ),
			/* translators: %s: number of plugins */
			'selectedNT' => __( '%s eklenti', 'flx-setup' ),
			'keep'       => __( 'Sekmeyi kapatmayın.', 'flx-setup' ),
			'gH'         => __( 'Önce güncellemeler', 'flx-setup' ),
			'gP'         => __( 'Kuruluma geçmeden önce yüklü uygulamalar güncelleniyor. Böylece her şey en yeni sürümle kurulur.', 'flx-setup' ),
			'gCheck'     => __( 'Güncellemeler kontrol ediliyor…', 'flx-setup' ),
			'gUpdating'  => __( 'güncelleniyor', 'flx-setup' ),
			'gUpdated'   => __( 'güncellendi', 'flx-setup' ),
			'gSelf'      => __( 'FluxWP Panel güncellendi. Sihirbaz yeni sürümle yeniden açılıyor…', 'flx-setup' ),
			/* translators: %s: error message */
			'gErr'       => __( 'Güncellemeler tamamlanamadı: %s Güncellemeler bitmeden kuruluma geçilmez.', 'flx-setup' ),
			'gRetry'     => __( 'Tekrar dene', 'flx-setup' ),
			'gPk'        => __( 'Paketler ekranına git', 'flx-setup' ),
			'gUnknown'   => __( 'Bilinmeyen hata.', 'flx-setup' ),
			'waiting'    => __( 'sırada', 'flx-setup' ),
			'installing' => __( 'kuruluyor', 'flx-setup' ),
			'installed'  => __( 'kuruldu', 'flx-setup' ),
			'applying'   => __( 'uygulanıyor', 'flx-setup' ),
			'applied'    => __( 'uygulandı', 'flx-setup' ),
			'failed'     => __( 'hata', 'flx-setup' ),
			'msgs'       => array(
				__( 'Paketler indiriliyor', 'flx-setup' ),
				__( 'Eklentiler kuruluyor', 'flx-setup' ),
				__( 'Siteniz sizin için hazırlanıyor', 'flx-setup' ),
				__( 'Sayfalar ve menüler oluşturuluyor', 'flx-setup' ),
				__( 'Az kaldı, son rötuşlar', 'flx-setup' ),
			),
			'theme'      => __( 'FluxWP teması', 'flx-setup' ),
			'presetStep' => __( 'Ön ayar', 'flx-setup' ),
			'styleStep'  => __( 'Renk', 'flx-setup' ),
			'nameStep'   => __( 'Site adı', 'flx-setup' ),
			'activate'   => __( 'Tema etkinleştiriliyor', 'flx-setup' ),
			'doneMsg'    => __( 'Hazır', 'flx-setup' ),
			'doneSub'    => __( 'Son ekran açılıyor.', 'flx-setup' ),
			'netErr'     => __( 'Sunucuya ulaşılamadı. Bağlantınızı kontrol edip yeniden deneyin.', 'flx-setup' ),
			'offline'    => __( 'Güncelleme sunucusuna bağlanılamadı, paket listesi boş. FluxWP Panel › Ayarlar\'dan sunucuyu kontrol edin; tema ve ön ayar yine de uygulanabilir.', 'flx-setup' ),
			'retry'      => __( 'Yeniden dene', 'flx-setup' ),
		);
	}
}

FLXK_Onboarding::init();
