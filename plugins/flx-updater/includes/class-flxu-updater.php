<?php
/**
 * Güncelleme sunucusuyla konuşan katman.
 *
 * - Sunucudan katalog alır (slug → son sürüm).
 * - WordPress'in update_plugins / update_themes verisine ekler.
 * - Bu paketleri wordpress.org'a giden istekten çıkarır (isim sızmasın, çakışma olmasın).
 * - Sunucuya giden isteklere site anahtarını başlık olarak ekler.
 */

defined( 'ABSPATH' ) || exit;

final class FLXU_Updater {

	const CATALOG_TRANSIENT = 'flxu_catalog';
	const STATUS_OPTION     = 'flxu_status';
	const SETTINGS_OPTION   = 'flxu_settings';
	const SLUGS_OPTION      = 'flxu_known_slugs';
	const CATALOG_TTL       = HOUR_IN_SECONDS;

	/** @var self|null */
	private static $instance = null;

	/** @var array|null İstek içi önbellek. */
	private $catalog = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_plugins' ), 20 );
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'inject_themes' ), 20 );
		// Okurken de korur: bazı eklentiler (ör. flx-forms'un wp.org koruması) kendi kaydını response'tan siler.
		add_filter( 'site_transient_update_plugins', array( $this, 'keep_plugins' ), PHP_INT_MAX );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 20, 3 );
		add_filter( 'http_request_args', array( $this, 'http_request_args' ), 20, 2 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 20, 4 );
		add_filter( 'auto_update_plugin', array( $this, 'auto_update' ), 20, 2 );
		add_filter( 'auto_update_theme', array( $this, 'auto_update' ), 20, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'after_upgrade' ), 10, 2 );
		add_filter( 'flx_system_checks', array( $this, 'system_checks' ) );
		add_filter( 'flx_system_checks_versions', array( $this, 'system_check_versions' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Ayarlar                                                             */
	/* ------------------------------------------------------------------ */

	public static function settings(): array {
		$s = get_site_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args(
			is_array( $s ) ? $s : array(),
			array(
				'server' => '',
				'key'    => '',
				'auto'   => 0,
				'health' => 1,
			)
		);
	}

	public static function server(): string {
		if ( defined( 'FLX_UPDATE_SERVER' ) && FLX_UPDATE_SERVER ) {
			return untrailingslashit( (string) FLX_UPDATE_SERVER );
		}
		$s      = self::settings();
		$server = (string) $s['server'];
		if ( '' === $server ) {
			$server = FLXU_DEFAULT_SERVER;
		}
		return untrailingslashit( $server );
	}

	public static function key(): string {
		if ( defined( 'FLX_UPDATE_KEY' ) && FLX_UPDATE_KEY ) {
			return (string) FLX_UPDATE_KEY;
		}
		return (string) self::settings()['key'];
	}

	public static function key_from_config(): bool {
		return defined( 'FLX_UPDATE_KEY' ) && FLX_UPDATE_KEY;
	}

	/* ------------------------------------------------------------------ */
	/* Katalog                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Sunucudaki paketler: slug => {type, name, version, package, ...}.
	 * Hata olursa son başarılı katalog döner (o da yoksa boş dizi).
	 */
	public function catalog( bool $force = false ): array {
		if ( ! $force && null !== $this->catalog ) {
			return $this->catalog;
		}
		$cached = get_site_transient( self::CATALOG_TRANSIENT );
		if ( ! $force && is_array( $cached ) ) {
			$this->catalog = $cached;
			return $cached;
		}

		$fresh = $this->fetch_catalog();
		if ( is_array( $fresh ) ) {
			set_site_transient( self::CATALOG_TRANSIENT, $fresh, self::CATALOG_TTL );
			update_site_option( self::SLUGS_OPTION, array_keys( $fresh ) );
			$this->catalog = $fresh;
			return $fresh;
		}

		// Sunucuya ulaşılamadı: kısa süre tekrar deneme, eldekiyle devam et.
		$fallback = get_site_option( 'flxu_catalog_last', array() );
		$fallback = is_array( $fallback ) ? $fallback : array();
		set_site_transient( self::CATALOG_TRANSIENT, $fallback, 10 * MINUTE_IN_SECONDS );
		$this->catalog = $fallback;
		return $fallback;
	}

	/**
	 * Açık kaynakların kataloglarını toplayıp birleştirir. Hata veren kaynağın son iyi kataloğu kullanılır.
	 *
	 * @return array|null Hiçbir kaynak yanıt vermediyse ve eldeki veri yoksa null.
	 */
	private function fetch_catalog(): ?array {
		$cache = get_site_option( FLXU_Sources::CACHE, array() );
		$cache = is_array( $cache ) ? $cache : array();
		$parts = array();
		$any   = false;
		foreach ( FLXU_Sources::all() as $src ) {
			if ( empty( $src['enabled'] ) ) {
				continue;
			}
			$cat = 'github' === $src['type'] ? FLXU_Sources::fetch_github( $src ) : $this->fetch_server( $src );
			if ( is_array( $cat ) ) {
				$any                 = true;
				$cache[ $src['id'] ] = $cat;
				$parts[ $src['id'] ] = $cat;
			} elseif ( isset( $cache[ $src['id'] ] ) && is_array( $cache[ $src['id'] ] ) ) {
				$parts[ $src['id'] ] = $cache[ $src['id'] ];
			}
		}
		if ( ! $any ) {
			return null;
		}
		update_site_option( FLXU_Sources::CACHE, $cache );
		$out = FLXU_Sources::merge( $parts );
		update_site_option( 'flxu_catalog_last', $out );
		return $out;
	}

	/**
	 * Bu sitenin sunucuya özel kurulum kimliği (rastgele, yalnız o sunucuya gider).
	 * Sunucu panelden "Anahtar ver" denince anahtarı yalnız bu kimlikle gelen isteğe teslim eder.
	 */
	public static function install_id( string $host ): string {
		$ids = get_site_option( 'flxu_install_ids', array() );
		$ids = is_array( $ids ) ? $ids : array();
		if ( empty( $ids[ $host ] ) || ! preg_match( '/^[a-f0-9]{40}$/', (string) $ids[ $host ] ) ) {
			$ids[ $host ] = bin2hex( random_bytes( 20 ) );
			update_site_option( 'flxu_install_ids', $ids );
		}
		return (string) $ids[ $host ];
	}

	/** Sunucunun teslim ettiği anahtarı kaynağa kaydeder (anahtarı boş olan kaynağa). */
	private static function save_assigned_key( array $src, string $key ): bool {
		if ( ! preg_match( '/^[A-Za-z0-9_-]{20,128}$/', $key ) ) {
			return false;
		}
		if ( ! empty( $src['main'] ) ) {
			if ( self::key_from_config() || '' !== self::key() ) {
				return false;
			}
			$set        = self::settings();
			$set['key'] = $key;
			update_site_option( self::SETTINGS_OPTION, $set );
			return true;
		}
		$list = get_site_option( FLXU_Sources::OPTION, array() );
		foreach ( (array) $list as $i => $s ) {
			if ( is_array( $s ) && ( $s['id'] ?? '' ) === $src['id'] && '' === (string) ( $s['key'] ?? '' ) ) {
				$list[ $i ]['key'] = $key;
				update_site_option( FLXU_Sources::OPTION, $list );
				return true;
			}
		}
		return false;
	}

	/**
	 * Flux Updater protokolünü konuşan bir sunucunun kataloğu.
	 *
	 * @return array|null Başarısızsa null; durum kaynak durumuna (ana sunucuda flxu_status'a da) yazılır.
	 */
	public function fetch_server( array $src, bool $retry = true ): ?array {
		$is_main = ! empty( $src['main'] );
		$status  = function ( bool $ok, string $error, string $site = '', int $count = 0 ) use ( $src, $is_main ) {
			if ( $is_main ) {
				$this->set_status( $ok, $error, $site );
			}
			FLXU_Sources::set_status( $src['id'], $ok, $error, $count );
		};
		$key  = (string) $src['key'];
		$host = FLXU_Sources::host( $src );
		$headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);
		if ( '' !== $key ) {
			$headers['X-FluxWP-Site-Key'] = $key;
		}
		$res = wp_remote_post(
			untrailingslashit( (string) $src['url'] ) . '/v1/check',
			array(
				'timeout' => 15,
				'headers' => $headers,
				'body'    => wp_json_encode(
					array(
						'site'     => home_url( '/' ),
						'wp'       => get_bloginfo( 'version' ),
						'php'      => PHP_VERSION,
						'updater'  => FLXU_VERSION,
						'tz'       => (string) get_option( 'timezone_string' ),
						'locale'   => get_locale(),
						'install'  => self::install_id( $host ),
						'packages' => $this->installed_versions(),
					)
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			/* translators: %s: error message */
			$status( false, sprintf( __( 'Sunucuya ulaşılamadı: %s', 'flx-updater' ), $res->get_error_message() ) );
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );

		// Sunucu yöneticisi bu siteye anahtar verdiyse (anahtarsız erişim kapalıyken 401 ile de gelir): kaydet ve anahtarla yeniden sor.
		if ( $retry && '' === $key && is_array( $body ) && ! empty( $body['assigned_key'] ) && self::save_assigned_key( $src, (string) $body['assigned_key'] ) ) {
			$src['key'] = (string) $body['assigned_key'];
			return $this->fetch_server( $src, false );
		}

		if ( 200 !== $code || ! is_array( $body ) || empty( $body['ok'] ) ) {
			if ( 401 === $code && '' === $key ) {
				$msg = __( 'Bu sunucu anahtarsız güncelleme vermiyor; site anahtarı girin.', 'flx-updater' );
			} elseif ( 401 === $code || 403 === $code ) {
				$msg = __( 'Site anahtarı geçersiz. Güncelleme sunucusunun panelinde (/admin) "Var olan bir anahtarı test et" ile deneyin.', 'flx-updater' );
			} else {
				/* translators: %d: HTTP status code */
				$msg = sprintf( __( 'Sunucu beklenmeyen yanıt verdi (HTTP %d).', 'flx-updater' ), $code );
			}
			$status( false, $msg );
			return null;
		}

		$out = array();
		foreach ( (array) ( $body['packages'] ?? array() ) as $slug => $p ) {
			if ( ! is_array( $p ) || ! preg_match( '/^[a-z0-9-]{1,64}$/', (string) $slug ) || empty( $p['version'] ) || empty( $p['package'] ) ) {
				continue;
			}
			// İndirme adresi yalnız bu sunucunun kendi host'unda olabilir.
			if ( strtolower( (string) wp_parse_url( $p['package'], PHP_URL_HOST ) ) !== $host ) {
				continue;
			}
			$out[ $slug ] = array(
				'slug'             => (string) $slug,
				'type'             => 'theme' === ( $p['type'] ?? '' ) ? 'theme' : 'plugin',
				'name'             => sanitize_text_field( (string) ( $p['name'] ?? $slug ) ),
				'version'          => sanitize_text_field( (string) $p['version'] ),
				'package'          => esc_url_raw( (string) $p['package'] ),
				'requires'         => sanitize_text_field( (string) ( $p['requires'] ?? '' ) ),
				'tested'           => sanitize_text_field( (string) ( $p['tested'] ?? '' ) ),
				'requires_php'     => sanitize_text_field( (string) ( $p['requires_php'] ?? '' ) ),
				'requires_plugins' => self::slug_list( $p['requires_plugins'] ?? array() ),
				'changelog'        => wp_kses_post( (string) ( $p['changelog'] ?? '' ) ),
				'released'         => sanitize_text_field( (string) ( $p['released'] ?? '' ) ),
				'versions'         => $this->clean_versions( (array) ( $p['versions'] ?? array() ), $host ),
			);
		}

		if ( ! empty( $body['key_disabled'] ) ) {
			$status( true, __( 'Site anahtarı sunucuda pasif; yalnız public paketler geliyor.', 'flx-updater' ), '', count( $out ) );
			return $out;
		}
		$status( true, '', ! empty( $body['public'] ) ? '' : (string) ( $body['site'] ?? '' ), count( $out ) );
		return $out;
	}

	/** @param mixed $list Dizi ya da virgüllü metin ("Requires Plugins" biçimi). */
	public static function slug_list( $list ): array {
		if ( is_string( $list ) ) {
			$list = explode( ',', $list );
		}
		$out = array();
		foreach ( (array) $list as $s ) {
			$s = strtolower( trim( (string) $s ) );
			if ( preg_match( '/^[a-z0-9][a-z0-9-]{0,63}$/', $s ) ) {
				$out[] = $s;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Paketin gerekli eklentileri: sunucunun bildirdiği (yeni release'ler) + kuruluysa
	 * eklentinin kendi "Requires Plugins" başlığı (sunucu eskiyse ya da eski release'te yoksa).
	 */
	public static function requires_plugins( string $slug, array $p = array() ): array {
		$out = (array) ( $p['requires_plugins'] ?? array() );
		if ( 'theme' === ( $p['type'] ?? 'plugin' ) ) {
			// Tema: style.css'teki "Requires Plugins" (WP_Theme bu başlığı okumaz).
			$css = get_theme_root( $slug ) . '/' . $slug . '/style.css';
			if ( is_readable( $css ) ) {
				$data = get_file_data( $css, array( 'RequiresPlugins' => 'Requires Plugins' ), 'theme' );
				$out  = array_merge( $out, self::slug_list( (string) $data['RequiresPlugins'] ) );
			}
		} else {
			$files = self::plugin_files();
			if ( isset( $files[ $slug ] ) ) {
				$data = get_plugins()[ $files[ $slug ] ] ?? array();
				$out  = array_merge( $out, self::slug_list( (string) ( $data['RequiresPlugins'] ?? '' ) ) );
			}
		}
		return array_values( array_diff( array_unique( $out ), array( $slug ) ) );
	}

	/** Sunucunun verdiği eski sürümler: yalnız o sunucunun host'undaki indirme adresleri. */
	private function clean_versions( array $list, string $host ): array {
		$out = array();
		foreach ( $list as $v ) {
			if ( ! is_array( $v ) || empty( $v['version'] ) || empty( $v['package'] ) ) {
				continue;
			}
			if ( strtolower( (string) wp_parse_url( $v['package'], PHP_URL_HOST ) ) !== $host ) {
				continue;
			}
			$out[] = array(
				'version'  => sanitize_text_field( (string) $v['version'] ),
				'released' => sanitize_text_field( (string) ( $v['released'] ?? '' ) ),
				'package'  => esc_url_raw( (string) $v['package'] ),
			);
		}
		return array_slice( $out, 0, 20 );
	}

	private function set_status( bool $ok, string $error, string $site = '' ): void {
		update_site_option(
			self::STATUS_OPTION,
			array(
				'ok'      => $ok,
				'error'   => $error,
				'site'    => $site,
				'checked' => time(),
			)
		);
	}

	public static function status(): array {
		$s = get_site_option( self::STATUS_OPTION, array() );
		return is_array( $s ) ? $s : array();
	}

	/* ------------------------------------------------------------------ */
	/* Kurulu paketler                                                     */
	/* ------------------------------------------------------------------ */

	/** slug => plugin dosyası (ör. flx-forms => flx-forms/flx-forms.php). */
	public static function plugin_files(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$out = array();
		foreach ( get_plugins() as $file => $data ) {
			$dir = dirname( $file );
			if ( '.' !== $dir && ! isset( $out[ $dir ] ) ) {
				$out[ $dir ] = $file;
			}
		}
		return $out;
	}

	/** Bilinen FluxWP paketlerinin kurulu sürümleri (sunucunun durum sayfası için). */
	private function installed_versions(): array {
		$known   = (array) get_site_option( self::SLUGS_OPTION, array() );
		$known[] = 'flx-updater';
		$out     = array();
		$files   = self::plugin_files();
		$plugins = get_plugins();
		foreach ( $files as $slug => $file ) {
			if ( in_array( $slug, $known, true ) ) {
				$out[ $slug ] = (string) ( $plugins[ $file ]['Version'] ?? '' );
			}
		}
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			if ( in_array( $stylesheet, $known, true ) ) {
				$out[ $stylesheet ] = (string) $theme->get( 'Version' );
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* WordPress güncelleme verisi                                         */
	/* ------------------------------------------------------------------ */

	private function force_requested(): bool {
		global $pagenow;
		return is_admin() && 'update-core.php' === $pagenow && isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Paket logosu stili: `gradient` (varsayılan) ya da `pastel`. Flux Admin › Görünüm › Paket logoları,
	 * Flux Admin yoksa `flx_logo_style` filtresi.
	 */
	public static function logo_style(): string {
		$v = function_exists( 'flxa_logo_style' ) ? flxa_logo_style() : (string) apply_filters( 'flx_logo_style', 'gradient' );
		return 'pastel' === $v ? 'pastel' : 'gradient';
	}

	/**
	 * Paketin logosu (assets/icons/<slug>.svg, pastel stilde assets/icons/pastel/<slug>.svg). Güncellemeler ekranında görünür.
	 * Başka bir paket kendi logosunu `flxu_package_icons` filtresiyle verebilir.
	 */
	public static function icons( string $slug ): array {
		$icons  = array();
		$slug   = sanitize_key( $slug );
		$url    = '';
		$pastel = 'pastel' === self::logo_style();
		if ( '' !== $slug && $pastel && is_readable( FLXU_DIR . 'assets/icons/pastel/' . $slug . '.svg' ) ) {
			$url = plugins_url( 'assets/icons/pastel/' . $slug . '.svg', FLXU_FILE ) . '?ver=' . FLXU_VERSION;
		} elseif ( '' !== $slug && is_readable( FLXU_DIR . 'assets/icons/' . $slug . '.svg' ) ) {
			$url = plugins_url( 'assets/icons/' . $slug . '.svg', FLXU_FILE ) . '?ver=' . FLXU_VERSION;
		} elseif ( '' !== $slug ) {
			// Burada logosu olmayan yeni paket: kurulu eklentinin kendi menü logosu (assets/icon.svg / icon-pastel.svg).
			foreach ( $pastel ? array( 'icon-pastel.svg', 'icon.svg' ) : array( 'icon.svg' ) as $f ) {
				if ( is_readable( WP_PLUGIN_DIR . '/' . $slug . '/assets/' . $f ) ) {
					$url = plugins_url( $slug . '/assets/' . $f ) . '?ver=' . filemtime( WP_PLUGIN_DIR . '/' . $slug . '/assets/' . $f );
					break;
				}
			}
		}
		if ( $url ) {
			$icons = array(
				'svg' => $url,
				'1x'  => $url,
				'2x'  => $url,
			);
		}
		return (array) apply_filters( 'flxu_package_icons', $icons, $slug );
	}

	public function inject_plugins( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		$catalog = $this->catalog( $this->force_requested() );
		if ( ! $catalog ) {
			return $transient;
		}
		$files   = self::plugin_files();
		$plugins = get_plugins();

		foreach ( $catalog as $slug => $p ) {
			if ( 'plugin' !== $p['type'] || ! isset( $files[ $slug ] ) ) {
				continue;
			}
			$file      = $files[ $slug ];
			$installed = (string) ( $transient->checked[ $file ] ?? ( $plugins[ $file ]['Version'] ?? '' ) );
			$item      = (object) array(
				'id'            => ( $p['source_url'] ?? self::server() ) . '/' . $slug,
				'slug'          => $slug,
				'plugin'        => $file,
				'new_version'   => $p['version'],
				'url'           => $p['source_url'] ?? self::server(),
				'package'       => $p['package'],
				'requires'      => $p['requires'],
				'tested'        => $p['tested'],
				'requires_php'  => $p['requires_php'],
				'icons'         => self::icons( $slug ),
				'banners'       => array(),
				'compatibility' => new stdClass(),
			);
			// wordpress.org'dan aynı isimle gelmiş bir kayıt varsa sil.
			unset( $transient->response[ $file ], $transient->no_update[ $file ] );
			if ( $installed && version_compare( $p['version'], $installed, '>' ) ) {
				$transient->response[ $file ] = $item;
			} else {
				$transient->no_update[ $file ] = $item;
			}
		}
		return $transient;
	}

	/**
	 * Okunan update_plugins verisinde bizim paketimiz response'tan silinmişse geri koy.
	 * Ağ isteği yapmaz: yalnız önbellekteki katalog kullanılır.
	 */
	public function keep_plugins( $value ) {
		if ( ! is_object( $value ) ) {
			return $value;
		}
		$catalog = $this->catalog;
		if ( null === $catalog ) {
			$catalog = get_site_transient( self::CATALOG_TRANSIENT );
			if ( ! is_array( $catalog ) ) {
				$catalog = get_site_option( 'flxu_catalog_last', array() );
			}
		}
		if ( ! is_array( $catalog ) || ! $catalog ) {
			return $value;
		}
		if ( ! isset( $value->response ) || ! is_array( $value->response ) ) {
			$value->response = array();
		}
		$files   = self::plugin_files();
		$plugins = null;
		foreach ( $catalog as $slug => $p ) {
			if ( 'plugin' !== ( $p['type'] ?? '' ) || ! isset( $files[ $slug ] ) || empty( $p['package'] ) ) {
				continue;
			}
			$file = $files[ $slug ];
			if ( isset( $value->response[ $file ] ) ) {
				continue;
			}
			if ( null === $plugins ) {
				$plugins = get_plugins();
			}
			$installed = (string) ( $plugins[ $file ]['Version'] ?? '' );
			if ( $installed && version_compare( (string) $p['version'], $installed, '>' ) ) {
				$value->response[ $file ] = (object) array(
					'id'            => ( $p['source_url'] ?? self::server() ) . '/' . $slug,
					'slug'          => $slug,
					'plugin'        => $file,
					'new_version'   => $p['version'],
					'url'           => $p['source_url'] ?? self::server(),
					'package'       => $p['package'],
					'requires'      => $p['requires'] ?? '',
					'tested'        => $p['tested'] ?? '',
					'requires_php'  => $p['requires_php'] ?? '',
					'icons'         => self::icons( $slug ),
					'banners'       => array(),
					'compatibility' => new stdClass(),
				);
				unset( $value->no_update[ $file ] );
			}
		}
		return $value;
	}

	public function inject_themes( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		$catalog = $this->catalog( $this->force_requested() );
		if ( ! $catalog ) {
			return $transient;
		}
		foreach ( $catalog as $slug => $p ) {
			if ( 'theme' !== $p['type'] ) {
				continue;
			}
			$theme = wp_get_theme( $slug );
			if ( ! $theme->exists() ) {
				continue;
			}
			$installed = (string) ( $transient->checked[ $slug ] ?? $theme->get( 'Version' ) );
			$item      = array(
				'theme'        => $slug,
				'new_version'  => $p['version'],
				'url'          => $p['source_url'] ?? self::server(),
				'package'      => $p['package'],
				'requires'     => $p['requires'],
				'requires_php' => $p['requires_php'],
			);
			unset( $transient->response[ $slug ], $transient->no_update[ $slug ] );
			if ( $installed && version_compare( $p['version'], $installed, '>' ) ) {
				$transient->response[ $slug ] = $item;
			} else {
				$transient->no_update[ $slug ] = $item;
			}
		}
		return $transient;
	}

	/** Eklentiler ekranındaki "Ayrıntıları görüntüle" penceresi. */
	public function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}
		$catalog = $this->catalog();
		$p       = $catalog[ $args->slug ] ?? null;
		if ( ! $p || 'plugin' !== $p['type'] ) {
			return $result;
		}
		$changelog = $p['changelog'] ? $p['changelog'] : '<p>' . esc_html__( 'Bu sürüm için not yok.', 'flx-updater' ) . '</p>';
		return (object) array(
			'name'          => $p['name'],
			'slug'          => $p['slug'],
			'version'       => $p['version'],
			'author'        => 'Örnek Vakfı',
			'requires'      => $p['requires'],
			'tested'        => $p['tested'],
			'requires_php'  => $p['requires_php'],
			'last_updated'  => $p['released'],
			'download_link' => $p['package'],
			'sections'      => array(
				'changelog' => $changelog,
			),
			'icons'         => self::icons( $p['slug'] ),
			'banners'       => array(),
		);
	}

	/* ------------------------------------------------------------------ */
	/* HTTP                                                                */
	/* ------------------------------------------------------------------ */

	public function http_request_args( $args, $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		// Sunucuya giden indirme isteklerine o sunucunun site anahtarı (anahtar yalnız kendi host'una gider).
		if ( $host && 'api.wordpress.org' !== $host ) {
			foreach ( FLXU_Sources::all() as $src ) {
				if ( 'server' !== $src['type'] || FLXU_Sources::host( $src ) !== $host ) {
					continue;
				}
				if ( '' !== (string) $src['key'] ) {
					$args['headers']                      = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
					$args['headers']['X-FluxWP-Site-Key'] = (string) $src['key'];
				}
				return $args;
			}
		}

		// wordpress.org güncelleme isteğinden FluxWP paketlerini çıkar.
		if ( 'api.wordpress.org' === $host && isset( $args['body'] ) && is_array( $args['body'] ) ) {
			$known = (array) get_site_option( self::SLUGS_OPTION, array() );
			$known[] = 'flx-updater';
			if ( false !== strpos( $url, '/plugins/update-check/' ) && ! empty( $args['body']['plugins'] ) ) {
				$data = json_decode( $args['body']['plugins'], true );
				if ( is_array( $data ) && isset( $data['plugins'] ) ) {
					foreach ( array_keys( $data['plugins'] ) as $file ) {
						if ( in_array( dirname( $file ), $known, true ) ) {
							unset( $data['plugins'][ $file ] );
						}
					}
					$data['active']           = array_values( array_filter( (array) ( $data['active'] ?? array() ), static function ( $f ) use ( $known ) {
						return ! in_array( dirname( $f ), $known, true );
					} ) );
					$args['body']['plugins'] = wp_json_encode( $data );
				}
			} elseif ( false !== strpos( $url, '/themes/update-check/' ) && ! empty( $args['body']['themes'] ) ) {
				$data = json_decode( $args['body']['themes'], true );
				if ( is_array( $data ) && isset( $data['themes'] ) ) {
					foreach ( $known as $slug ) {
						unset( $data['themes'][ $slug ] );
					}
					$args['body']['themes'] = wp_json_encode( $data );
				}
			}
		}
		return $args;
	}

	/**
	 * Zip'teki klasör adı slug'dan farklıysa düzelt (ör. GitHub'ın "repo-abc123" klasörü).
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;
		if ( is_wp_error( $source ) || ! $wp_filesystem ) {
			return $source;
		}
		$slug = '';
		if ( ! empty( $hook_extra['plugin'] ) ) {
			$slug = dirname( $hook_extra['plugin'] );
		} elseif ( ! empty( $hook_extra['theme'] ) ) {
			$slug = $hook_extra['theme'];
		}
		$catalog = $this->catalog();
		if ( '' === $slug || '.' === $slug || ! isset( $catalog[ $slug ] ) ) {
			return $source;
		}
		$current = basename( untrailingslashit( $source ) );
		if ( $current === $slug ) {
			return $source;
		}
		$target = trailingslashit( $remote_source ) . $slug;
		if ( $wp_filesystem->move( $source, $target, true ) ) {
			return trailingslashit( $target );
		}
		return new WP_Error( 'flxu_rename', __( 'Paket klasörü yeniden adlandırılamadı.', 'flx-updater' ) );
	}

	public function auto_update( $update, $item ) {
		if ( empty( self::settings()['auto'] ) ) {
			return $update;
		}
		$slug    = isset( $item->theme ) ? $item->theme : ( $item->slug ?? '' );
		$catalog = $this->catalog();
		return ( $slug && isset( $catalog[ $slug ] ) ) ? true : $update;
	}

	public function after_upgrade( $upgrader, $extra ): void {
		// Güncelleme sonrası kurulu sürümleri sunucuya bir sonraki kontrolde bildirmek için katalog önbelleğini boşalt.
		if ( isset( $extra['type'] ) && in_array( $extra['type'], array( 'plugin', 'theme' ), true ) ) {
			delete_site_transient( self::CATALOG_TRANSIENT );
		}
	}

	/** "Şimdi kontrol et": katalog + WP güncelleme verisi yenilenir. */
	public function check_now(): void {
		delete_site_transient( self::CATALOG_TRANSIENT );
		$this->catalog = null;
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		if ( ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . 'wp-includes/update.php';
		}
		wp_update_plugins();
		wp_update_themes();
		// wp.org'a ulaşılamazsa WP filtreyi çağırmayabilir; katalog yine de tazelensin.
		$this->catalog( true );
	}

	/* ------------------------------------------------------------------ */
	/* Sistem kontrolü                                                     */
	/* ------------------------------------------------------------------ */

	public function system_checks( $checks ) {
		$checks   = is_array( $checks ) ? $checks : array();
		$page     = admin_url( 'admin.php?page=flx-updater-ayarlar' );
		$status   = self::status();
		$source   = 'Flux Updater';

		if ( empty( $status['checked'] ) ) {
			$st  = 'info';
			$msg = __( 'Henüz kontrol yapılmadı.', 'flx-updater' );
		} elseif ( empty( $status['ok'] ) ) {
			$st  = 'error';
			$msg = (string) $status['error'];
		} else {
			$st = 'ok';
			/* translators: %s: human time diff */
			$msg = sprintf( __( 'Sunucu bağlantısı çalışıyor. Son kontrol: %s önce.', 'flx-updater' ), human_time_diff( (int) $status['checked'] ) );
			if ( '' === self::key() ) {
				$msg .= ' ' . __( 'Anahtar yok: yalnız public paketler gelir.', 'flx-updater' );
			}
		}
		$checks[] = array(
			'id'           => 'flxu.server',
			'source'       => $source,
			'group'        => 'Config',
			'label'        => __( 'Güncelleme sunucusu', 'flx-updater' ),
			'status'       => $st,
			'message'      => $msg,
			'action_url'   => $page,
			'action_label' => __( 'Şimdi kontrol et', 'flx-updater' ),
		);

		foreach ( FLXU_Sources::failing() as $i => $f ) {
			$checks[] = array(
				'id'           => 'flxu.source.' . $i,
				'source'       => $source,
				'group'        => 'Config',
				/* translators: %s: source name */
				'label'        => sprintf( __( 'Güncelleme kaynağı: %s', 'flx-updater' ), $f['name'] ),
				'status'       => 'warning',
				'message'      => $f['error'],
				'action_url'   => $page . '#flxu-sources',
				'action_label' => __( 'Kaynaklar', 'flx-updater' ),
			);
		}

		$pending = FLXU_Admin::rows( $this->catalog() );
		$names   = array();
		foreach ( $pending as $row ) {
			if ( 'update' === $row['state'] ) {
				$names[] = $row['name'] . ' ' . $row['latest'];
			}
		}
		if ( $names ) {
			$checks[] = array(
				'id'           => 'flxu.updates',
				'source'       => $source,
				'group'        => 'Plugins',
				'label'        => __( 'FluxWP paket güncellemeleri', 'flx-updater' ),
				'status'       => 'warning',
				/* translators: %s: comma separated package list */
				'message'      => sprintf( __( 'Güncelleme bekleyenler: %s', 'flx-updater' ), implode( ', ', $names ) ),
				'action_url'   => admin_url( 'admin.php?page=flx-updater' ),
				'action_label' => __( 'Güncellemeler', 'flx-updater' ),
			);
		}
		return $checks;
	}

	public function system_check_versions( $versions ) {
		$versions                    = is_array( $versions ) ? $versions : array();
		$versions['flx-updater'] = FLXU_VERSION;
		return $versions;
	}
}
