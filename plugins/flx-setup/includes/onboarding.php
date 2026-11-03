<?php
/**
 * Kurulum sihirbazı: Flux Setup ilk etkinleşince açılır, istenirse atlanır.
 *
 * Adımlar: tanıtım → eklenti seçimi → görünüm (ön ayar, renk, site adı) → kurulum → hazır.
 * Kurulum güncelleyicinin flxu_plan / flxu_step uçlarını kullanır (her paket ayrı istek; paylaşımlı
 * sunucuların süre sınırına takılmaz). Tema, renk, ön ayar ve site adı flxk_onboarding_step ile uygulanır.
 *
 * Durum: option flxk_onboarding = pending | done | skipped. Sihirbaz Setup › Kurulum sihirbazı'ndan her zaman açılır.
 */

defined( 'ABSPATH' ) || exit;

final class FLXK_Onboarding {

	const PAGE  = 'flx-setup-wizard';
	const NONCE = 'flxk_onboarding';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ), 20 );
		add_action( 'wp_ajax_flxk_onboarding_step', array( __CLASS__, 'ajax_step' ) );
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
	 * Bilinen paketlerin tanıtım metni. Katalogda olup burada olmayan paket de listelenir (yalnız adıyla).
	 * rec: hiç seçim yapmadan önerilen.
	 */
	private static function intro(): array {
		$d = array(
			'flx-commerce'     => array( 1, __( 'WooCommerce mağazası: satış paneli, müşteriler, raporlar ve dekontlu Havale/EFT.', 'flx-setup' ) ),
			'flx-admin'        => array( 1, __( 'Komut paleti, mobil menü ve düzenlenebilir panel.', 'flx-setup' ) ),
			'flx-donate'       => array( 1, __( 'WooCommerce üzerinde bağış türleri ve tek sayfalık ödeme.', 'flx-setup' ) ),
			'flx-certificates' => array( 1, __( 'Her bağışçıya adına PDF sertifika gönderir.', 'flx-setup' ) ),
			'flx-chat'         => array( 1, __( 'WhatsApp gelen kutusu, hazır cevaplar ve AI asistan.', 'flx-setup' ) ),
			'flx-forms'        => array( 1, __( 'Sürükle-bırak form, ödeme alanı ve otomasyon.', 'flx-setup' ) ),
			'flx-mail'         => array( 1, __( 'SMTP bağlantısı, posta kutusu ve gönderim kaydı.', 'flx-setup' ) ),
			'flx-seo'          => array( 1, __( 'Site denetimi, schema ve sayfa başına meta.', 'flx-setup' ) ),
			'flx-speed'        => array( 1, __( 'Önbellek, WebP dönüşümü ve sistem kontrolü.', 'flx-setup' ) ),
			'flx-backup'       => array( 1, __( 'Günlük yedek ve tek tıkla geri yükleme.', 'flx-setup' ) ),
			'flx-analytics'    => array( 0, __( 'Çerez kullanmadan ziyaretçi ve dönüşüm analizi.', 'flx-setup' ) ),
			'flx-tasks'        => array( 0, __( 'Görev panosu, takvim ve toplantı notları.', 'flx-setup' ) ),
			'flx-ai'           => array( 0, __( 'Ekip için ortak AI modelleri ve günlük bütçe.', 'flx-setup' ) ),
			'flx-lang'         => array( 0, __( 'Sayfa ve menüleri İngilizce ve diğer dillere çevirir.', 'flx-setup' ) ),
			'flx-social'       => array( 0, __( 'Sosyal medya paylaşım takvimi ve onay akışı.', 'flx-setup' ) ),
			'flx-studio'       => array( 0, __( 'AI ile görsel ve kısa video üretimi.', 'flx-setup' ) ),
			'flx-roles'        => array( 0, __( 'Personel rolleri ve sayfa bazlı yetkiler.', 'flx-setup' ) ),
			'flx-app'          => array( 0, __( 'Masaüstü ve mobil FluxApp bağlantısı.', 'flx-setup' ) ),
		);
		return (array) apply_filters( 'flxk_onboarding_intro', $d );
	}

	/** Tema ile birlikte en üstte "Temel" olarak gösterilen, varsayılan seçili paketler. */
	private static function core(): array {
		return (array) apply_filters( 'flxk_onboarding_core', array( 'flx-commerce' ) );
	}

	/** Sihirbazda gösterilmeyen katalog paketleri (kendisi, ön ayarlar, geçiş araçları). */
	private static function hidden( string $slug ): bool {
		return in_array( $slug, array( 'flx-setup', 'flx-updater', 'flx-migrate' ), true ) || 0 === strpos( $slug, 'flx-preset-' );
	}

	private static function plugins( array $catalog, array $rows ): array {
		$intro = self::intro();
		$state = array();
		foreach ( $rows as $r ) {
			$state[ $r['slug'] ] = $r;
		}
		$lang = self::lang();
		$out  = array();
		foreach ( $catalog as $slug => $p ) {
			if ( 'plugin' !== $p['type'] || self::hidden( $slug ) ) {
				continue;
			}
			$thumb = FLXK_DIR . 'assets/onboarding/thumbs/' . $slug . '-' . $lang . '.jpg';
			$icons = FLXU_Updater::icons( $slug );
			$out[] = array(
				'slug'    => $slug,
				'name'    => $p['name'],
				'version' => $p['version'],
				'desc'    => $intro[ $slug ][1] ?? '',
				'rec'     => ! empty( $intro[ $slug ][0] ),
				'icon'    => (string) ( $icons['svg'] ?? ( $icons['1x'] ?? '' ) ),
				'thumb'   => is_readable( $thumb ) ? FLXK_URL . 'assets/onboarding/thumbs/' . $slug . '-' . $lang . '.jpg' : '',
				'active'  => ! empty( $state[ $slug ]['active'] ),
				'core'    => in_array( $slug, self::core(), true ),
				'order'   => isset( $intro[ $slug ] ) ? array_search( $slug, array_keys( $intro ), true ) : 99,
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
			array( 'antrasit', __( 'Antrasit', 'flx-setup' ), '#1F2937', '#111827', '#3B82F6', '#F2EEE7', '#F8F5F0', '#E6E0D6' ),
			array( 'mavi', __( 'Mavi', 'flx-setup' ), '#2563EB', '#1D4ED8', '#F59E0B', '#EFF6FF', '#F8FAFC', '#E2E8F0' ),
			array( 'petrol', __( 'Petrol', 'flx-setup' ), '#0F766E', '#115E59', '#F59E0B', '#ECFDF9', '#F7FAFA', '#E2E8F0' ),
			array( 'indigo', __( 'İndigo', 'flx-setup' ), '#4338CA', '#3730A3', '#F59E0B', '#EEF2FF', '#F7F8FE', '#E0E7FF' ),
			array( 'orman', __( 'Orman', 'flx-setup' ), '#0E7A56', '#0A5A40', '#6DBE45', '#EAF5EE', '#F5FAF6', '#DCE9E0' ),
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
				$out[ $key ] = array( $key, (string) ( $j['title'] ?? $key ), $c['primary'], $c['primary-dark'] ?? $c['primary'], $c['leaf'] ?? $c['primary'], $c['mint'] ?? '#F2EEE7', $c['mint-soft'] ?? '#F8F5F0', $c['line'] ?? '#E6E0D6' );
			}
		}
		$list = $out ? $out : $fallback;
		$keys = array( 'key', 'name', 'p', 'pd', 'l', 'm', 'ms', 'sl' );
		$res  = array();
		foreach ( $list as $row ) {
			$res[] = array_combine( $keys, array_values( $row ) );
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
			array( 'flx-ai', __( 'AI anahtarı ekleyin', 'flx-setup' ), __( 'Asistanlar ve AI araçları için gerekli.', 'flx-setup' ), __( 'Flux AI › Ayarlar', 'flx-setup' ), admin_url( 'admin.php?page=flx-ai-settings' ) ),
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
			'presets'   => self::presets( $catalog ),
			'styles'    => self::styles(),
			'theme'     => isset( $catalog['fluxwp'] ) || wp_get_theme( 'fluxwp' )->exists() ? 'fluxwp' : '',
			'themeThumb' => is_readable( FLXK_DIR . 'assets/onboarding/thumbs/fluxwp.jpg' ) ? FLXK_URL . 'assets/onboarding/thumbs/fluxwp.jpg' : '',
			'themeIcon' => (string) ( FLXU_Updater::icons( 'fluxwp' )['svg'] ?? '' ),
			'setupIcon' => FLXK_URL . 'assets/icons/flx-setup.svg',
			'siteName'  => get_bloginfo( 'name' ),
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
<title><?php echo esc_html( 'Flux Setup · ' . get_bloginfo( 'name' ) ); ?></title>
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

	private static function run( string $kind, string $value ): array {
		switch ( $kind ) {
			case 'name':
				if ( '' !== $value && current_user_can( 'manage_options' ) ) {
					update_option( 'blogname', $value );
				}
				return self::ok( sprintf( /* translators: %s: site name */ __( 'Site adı: %s', 'flx-setup' ), get_bloginfo( 'name' ) ) );

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
			'i_p'        => __( 'Sitenizi üç adımda kuralım. Eklentileri seçin, görünümü belirleyin; kurulumu Flux Setup yapar.', 'flx-setup' ),
			/* translators: %d: number of plugins */
			'f1b'        => __( '%d eklenti', 'flx-setup' ),
			'f1s'        => __( 'Bağış, form, WhatsApp, e-posta, SEO ve yedek aynı panelde çalışır.', 'flx-setup' ),
			'f2b'        => __( 'Güncellemeler', 'flx-setup' ),
			'f2s'        => __( 'Yeni sürümler WordPress\'in Güncellemeler ekranına gelir.', 'flx-setup' ),
			'f3b'        => __( 'Geri dönüş', 'flx-setup' ),
			'f3s'        => __( 'Ön ayarı ve rengi sonra Görünüm › Tema Ayarları\'ndan değiştirirsiniz.', 'flx-setup' ),
			'start'      => __( 'Başlayalım', 'flx-setup' ),
			'skipSelf'   => __( 'Kendim kurarım', 'flx-setup' ),
			'time'       => __( 'Yaklaşık 3 dakika', 'flx-setup' ),
			'k1'         => __( 'Eklentiler', 'flx-setup' ),
			'h1'         => __( 'Hangi eklentiler kurulsun?', 'flx-setup' ),
			'lead1'      => __( 'Önerilenler işaretli. Seçmedikleriniz sonra Setup › Paketler\'den tek tıkla kurulur.', 'flx-setup' ),
			'selAll'     => __( 'Tümünü seç', 'flx-setup' ),
			'selNone'    => __( 'Seçimi temizle', 'flx-setup' ),
			'coreK'      => __( 'Temel', 'flx-setup' ),
			'coreHint'   => __( 'Sitenin üzerine kurulduğu iki parça.', 'flx-setup' ),
			'othersK'    => __( 'Diğer eklentiler', 'flx-setup' ),
			'themeDesc'  => __( 'Blok tabanlı tema: mağaza, bağış ve kurumsal sayfa şablonları.', 'flx-setup' ),
			'always'     => __( 'Her zaman', 'flx-setup' ),
			'installedB' => __( 'Kurulu', 'flx-setup' ),
			'k2'         => __( 'Görünüm', 'flx-setup' ),
			'h2'         => __( 'Siteniz nasıl görünsün?', 'flx-setup' ),
			'lead2'      => __( 'Ön ayar sayfaları, menüleri ve örnek içeriği kurar.', 'flx-setup' ),
			'preset'     => __( 'Ön ayar', 'flx-setup' ),
			'presetHint' => __( 'Sayfalar ve menüler buna göre oluşur.', 'flx-setup' ),
			'color'      => __( 'Renk', 'flx-setup' ),
			'colorHint'  => __( 'Tema ve eklentiler aynı rengi kullanır.', 'flx-setup' ),
			'siteName'   => __( 'Site adı', 'flx-setup' ),
			'nameHint'   => __( 'E-postalarda ve sertifikalarda da görünür.', 'flx-setup' ),
			'h4'         => __( 'Siteniz hazır', 'flx-setup' ),
			'lead4'      => __( 'Eklentiler kuruldu, ön ayar ve renk uygulandı.', 'flx-setup' ),
			'h4warn'     => __( 'Kurulum bitti, bazı adımlar tamamlanmadı', 'flx-setup' ),
			'lead4warn'  => __( 'Hatalı adımlar aşağıda. Setup › Paketler\'den yeniden deneyebilirsiniz.', 'flx-setup' ),
			'sumPk'      => __( 'eklenti', 'flx-setup' ),
			'sumPg'      => __( 'sayfa', 'flx-setup' ),
			'sumSt'      => __( 'renk', 'flx-setup' ),
			'viewSite'   => __( 'Siteyi aç', 'flx-setup' ),
			'toDash'     => __( 'Panele git', 'flx-setup' ),
			'back'       => __( 'Geri', 'flx-setup' ),
			'next'       => __( 'Devam', 'flx-setup' ),
			'install'    => __( 'Kur', 'flx-setup' ),
			'skipT'      => __( 'Kurulum atlansın mı?', 'flx-setup' ),
			'skipP'      => __( 'Hiçbir şey kurulmaz. Sihirbazı sonra Setup › Kurulum sihirbazı\'ndan açabilirsiniz.', 'flx-setup' ),
			'stay'       => __( 'Vazgeç', 'flx-setup' ),
			'skipYes'    => __( 'Atla', 'flx-setup' ),
			/* translators: %s: number of plugins */
			'selected'   => __( '%s eklenti + FluxWP teması', 'flx-setup' ),
			/* translators: %s: number of plugins */
			'selectedNT' => __( '%s eklenti', 'flx-setup' ),
			'keep'       => __( 'Sekmeyi kapatmayın.', 'flx-setup' ),
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
			'offline'    => __( 'Güncelleme sunucusuna bağlanılamadı, paket listesi boş. Setup › Ayarlar\'dan sunucuyu kontrol edin; tema ve ön ayar yine de uygulanabilir.', 'flx-setup' ),
			'retry'      => __( 'Yeniden dene', 'flx-setup' ),
		);
	}
}

FLXK_Onboarding::init();
