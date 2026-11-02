<?php
/**
 * Güncelleme öncesi otomatik yedek.
 *
 * upgrader_pre_install her paket için, paket kurulmadan hemen önce çalışır (elle, toplu, otomatik/cron, WP-CLI).
 * Aynı istekte tek yedek alınır: ilk pakette veritabanı + paket klasörü, sonraki her pakette yalnız o paketin
 * klasörü aynı zip'e eklenir. Yedek eşzamanlıdır (güncelleme o sırada bekler). Yedek alınamazsa güncelleme
 * engellenmez; hata günlüğe ve başarısız kayıt olarak listeye yazılır.
 *
 * Zip yükleyip "mevcut olanı değiştir" (install + overwrite, ör. wp plugin install x.zip --force) için
 * upgrader_source_selection kullanılır: klasör adı ancak paket açıldıktan sonra bellidir.
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_Pre_Update {

	/** @var string|null Bu istekte alınan yedeğin kimliği. */
	private static $backup_id = null;

	/** @var array<string,bool> Bu istekte yedeklenen paketler (type:slug). */
	private static $done = array();

	/** @var bool Bu istekte yedek denendi ve başarısız oldu (tekrar denenmez). */
	private static $failed = false;

	/** @var string|null Bu istekte başarısız olan yedeğin kaydı (listede sürüm bilgisi görünsün diye). */
	private static $failed_id = null;

	/** @var bool Çalışan "install" işlemi mevcut paketi değiştiriyor mu. */
	private static $overwrite = false;

	public static function init(): void {
		add_filter( 'upgrader_package_options', array( __CLASS__, 'package_options' ), 999 ); // Geç: WP-CLI --force clear_destination'ı kendi filtresiyle açar.
		add_filter( 'upgrader_pre_install', array( __CLASS__, 'pre_install' ), 5, 2 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'source_selection' ), 5, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'complete' ), 20, 2 );
	}

	public static function package_options( $options ) {
		if ( is_array( $options ) ) {
			$extra           = (array) ( $options['hook_extra'] ?? array() );
			self::$overwrite = 'install' === ( $extra['action'] ?? '' ) && ! empty( $options['clear_destination'] );
		}
		return $options;
	}

	/**
	 * @param bool|WP_Error $response
	 * @param array         $hook_extra
	 * @return bool|WP_Error Değiştirilmeden.
	 */
	public static function pre_install( $response, $hook_extra ) {
		if ( is_wp_error( $response ) || ! is_array( $hook_extra ) ) {
			return $response;
		}
		$pkg = null;
		if ( ! empty( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) ) {
			$pkg = self::plugin_package( $hook_extra['plugin'] );
		} elseif ( ! empty( $hook_extra['theme'] ) && is_string( $hook_extra['theme'] ) ) {
			$pkg = self::theme_package( $hook_extra['theme'] );
		}
		if ( $pkg ) {
			self::backup( $pkg, $hook_extra );
		}
		return $response;
	}

	public static function source_selection( $source, $remote_source = '', $upgrader = null, $hook_extra = array() ) {
		if ( is_wp_error( $source ) || ! is_array( $hook_extra ) || 'install' !== ( $hook_extra['action'] ?? '' ) ) {
			return $source;
		}
		// Değiştirme kurulumu mu: clear_destination (upgrader_package_options), yönetimdeki "mevcut olanı değiştir"
		// (skin->overwrite) veya WP-CLI --force (DestructivePluginUpgrader / DestructiveThemeUpgrader).
		$overwrite = self::$overwrite;
		if ( is_object( $upgrader ) ) {
			$overwrite = $overwrite || false !== stripos( get_class( $upgrader ), 'Destructive' ) || ( isset( $upgrader->skin ) && ! empty( $upgrader->skin->overwrite ) );
		}
		if ( ! $overwrite ) {
			return $source;
		}
		$slug = basename( untrailingslashit( (string) $source ) );
		$pkg  = null;
		if ( 'plugin' === ( $hook_extra['type'] ?? '' ) && is_dir( WP_PLUGIN_DIR . '/' . $slug ) ) {
			$file = self::plugin_file_for_slug( $slug );
			$pkg  = $file ? self::plugin_package( $file ) : null;
		} elseif ( 'theme' === ( $hook_extra['type'] ?? '' ) && is_dir( get_theme_root() . '/' . $slug ) ) {
			$pkg = self::theme_package( $slug );
		}
		if ( $pkg ) {
			$pkg['to_version'] = self::version_in_source( (string) $source, $pkg['type'] );
			self::backup( $pkg, $hook_extra );
		}
		return $source;
	}

	private static function plugin_file_for_slug( string $slug ): string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$list = get_plugins( '/' . $slug );
		if ( ! $list ) {
			return '';
		}
		return $slug . '/' . array_key_first( $list );
	}

	private static function plugin_package( string $plugin ): ?array {
		$plugin = wp_normalize_path( $plugin );
		$main   = WP_PLUGIN_DIR . '/' . $plugin;
		if ( ! file_exists( $main ) ) {
			return null; // Yeni kurulum: yedeklenecek bir şey yok.
		}
		$slug = dirname( $plugin );
		$path = '.' === $slug ? $main : WP_PLUGIN_DIR . '/' . $slug;
		$data = get_file_data(
			$main,
			array(
				'name'    => 'Plugin Name',
				'version' => 'Version',
			)
		);
		$to   = '';
		$upd  = get_site_transient( 'update_plugins' );
		if ( is_object( $upd ) && isset( $upd->response[ $plugin ]->new_version ) ) {
			$to = (string) $upd->response[ $plugin ]->new_version;
		}
		return array(
			'type'         => 'plugin',
			'slug'         => '.' === $slug ? basename( $plugin, '.php' ) : $slug,
			'file'         => $plugin,
			'name'         => (string) $data['name'],
			'path'         => FLXY_Backup::rel( $path ),
			'from_version' => (string) $data['version'],
			'to_version'   => $to,
		);
	}

	private static function theme_package( string $slug ): ?array {
		$theme = wp_get_theme( $slug );
		if ( ! $theme->exists() ) {
			return null;
		}
		$to  = '';
		$upd = get_site_transient( 'update_themes' );
		if ( is_object( $upd ) && isset( $upd->response[ $slug ]['new_version'] ) ) {
			$to = (string) $upd->response[ $slug ]['new_version'];
		}
		return array(
			'type'         => 'theme',
			'slug'         => $slug,
			'file'         => '',
			'name'         => (string) $theme->get( 'Name' ),
			'path'         => FLXY_Backup::rel( $theme->get_stylesheet_directory() ),
			'from_version' => (string) $theme->get( 'Version' ),
			'to_version'   => $to,
		);
	}

	private static function version_in_source( string $source, string $type ): string {
		$source = trailingslashit( $source );
		if ( 'theme' === $type ) {
			if ( file_exists( $source . 'style.css' ) ) {
				$d = get_file_data( $source . 'style.css', array( 'v' => 'Version' ) );
				return (string) $d['v'];
			}
			return '';
		}
		foreach ( (array) glob( $source . '*.php' ) as $f ) {
			$d = get_file_data( $f, array( 'n' => 'Plugin Name', 'v' => 'Version' ) );
			if ( '' !== $d['n'] ) {
				return (string) $d['v'];
			}
		}
		return '';
	}

	/** Kapsam ayarına göre bu paket yedeklenmeli mi. */
	public static function in_scope( array $pkg ): bool {
		$scope = (string) FLXY_Settings::get( 'pre_scope' );
		if ( 'off' === $scope ) {
			return false;
		}
		if ( 'flx' === $scope ) {
			return self::is_flx_package( $pkg['type'], $pkg['slug'] );
		}
		return true;
	}

	public static function is_flx_package( string $type, string $slug ): bool {
		if ( 0 === strpos( $slug, 'flx-' ) ) {
			return true;
		}
		/**
		 * FluxWP kapsamındaki tema klasörleri.
		 *
		 * @param string[] $themes
		 */
		$themes = (array) apply_filters( 'flxy_flx_themes', array( 'flx-menu-theme', 'fluxwp' ) );
		if ( 'theme' !== $type ) {
			return false;
		}
		if ( in_array( $slug, $themes, true ) ) {
			return true;
		}
		// Listede olmayan tema: "Update URI" FluxWP güncelleme sunucusunu gösteriyorsa FluxWP temasıdır.
		$theme = wp_get_theme( $slug );
		$uri   = $theme->exists() ? (string) $theme->get( 'UpdateURI' ) : '';
		if ( '' === $uri || ! class_exists( 'FLXU_Updater' ) ) {
			return false;
		}
		$host = strtolower( (string) wp_parse_url( $uri, PHP_URL_HOST ) );
		return '' !== $host && strtolower( (string) wp_parse_url( FLXU_Updater::server(), PHP_URL_HOST ) ) === $host;
	}

	private static function source(): string {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}
		if ( wp_doing_cron() ) {
			return 'auto';
		}
		return 'update';
	}

	private static function backup( array $pkg, array $hook_extra ): void {
		$key = $pkg['type'] . ':' . $pkg['slug'];
		if ( isset( self::$done[ $key ] ) || self::$failed || ! self::in_scope( $pkg ) ) {
			return;
		}
		/**
		 * Güncelleme öncesi yedek alınsın mı.
		 *
		 * @param bool  $take       Varsayılan true.
		 * @param array $hook_extra upgrader_pre_install'a gelen hook_extra (plugin/theme, type, action).
		 */
		if ( ! apply_filters( 'flxy_pre_update_backup', true, $hook_extra ) ) {
			return;
		}
		self::$done[ $key ] = true;
		$ctx_pkg            = array(
			'type'         => $pkg['type'],
			'slug'         => $pkg['slug'],
			'file'         => $pkg['file'],
			'name'         => $pkg['name'],
			'from_version' => $pkg['from_version'],
			'to_version'   => $pkg['to_version'],
		);

		if ( null === self::$backup_id ) {
			self::$backup_id = self::recent_backup_id();
		}

		if ( null === self::$backup_id ) {
			$res = FLXY_Backup::create(
				array(
					'type'    => 'pre_update',
					'db'      => true,
					'paths'   => array( $pkg['path'] ),
					'context' => array(
						'source'   => self::source(),
						'action'   => (string) ( $hook_extra['action'] ?? 'update' ),
						'user'     => get_current_user_id(),
						'packages' => array( $ctx_pkg ),
					),
					'sync'    => true,
				)
			);
			if ( is_wp_error( $res ) ) {
				self::$failed = true;
				$data         = $res->get_error_data();
				if ( is_array( $data ) && ! empty( $data['id'] ) ) {
					self::$failed_id = $data['id'];
				}
				FLXY_Storage::log(
					'pre_update',
					sprintf(
						/* translators: 1: package name, 2: error */
						__( '%1$s güncellemesinden önce yedek alınamadı, güncelleme yedeksiz devam etti: %2$s', 'flx-backup' ),
						$pkg['name'] ? $pkg['name'] : $pkg['slug'],
						$res->get_error_message()
					),
					'error'
				);
				return;
			}
			self::$backup_id = $res['id'];
			return;
		}

		$res = FLXY_Backup::append( self::$backup_id, array( $pkg['path'] ), array( $ctx_pkg ) );
		if ( is_wp_error( $res ) ) {
			FLXY_Storage::log(
				'pre_update',
				sprintf(
					/* translators: 1: package name, 2: error */
					__( '%1$s klasörü güncelleme öncesi yedeğe eklenemedi: %2$s', 'flx-backup' ),
					$pkg['name'] ? $pkg['name'] : $pkg['slug'],
					$res->get_error_message()
				),
				'error'
			);
		}
	}

	/**
	 * Eklentiler ekranındaki toplu güncelleme her paketi ayrı AJAX isteğinde kurar. Birkaç dakika içinde aynı
	 * kaynaktan alınmış güncelleme öncesi yedek varsa yeni yedek yerine ona eklenir (veritabanı bir kez alınır).
	 */
	private static function recent_backup_id(): ?string {
		/**
		 * Ardışık güncellemelerin aynı yedeğe ekleneceği süre (saniye). 0 = her istek ayrı yedek.
		 *
		 * @param int $seconds Varsayılan 180.
		 */
		$window = (int) apply_filters( 'flxy_pre_update_merge_window', 180 );
		if ( $window <= 0 ) {
			return null;
		}
		foreach ( FLXY_Storage::records() as $r ) {
			if ( $r['created'] < time() - $window ) {
				break;
			}
			if ( 'pre_update' === $r['type'] && 'ok' === $r['status'] && ! $r['locked'] && self::source() === ( $r['context']['source'] ?? '' )
				&& (int) ( $r['context']['user'] ?? 0 ) === get_current_user_id() && file_exists( $r['file'] ) ) {
				return $r['id'];
			}
		}
		return null;
	}

	/** Güncelleme bitince yeni sürümleri bağlama yazar. */
	public static function complete( $upgrader = null, $hook_extra = array() ): void {
		$id = null !== self::$backup_id ? self::$backup_id : self::$failed_id;
		if ( null === $id ) {
			return;
		}
		$rec = FLXY_Storage::get( $id );
		if ( ! $rec ) {
			return;
		}
		$ctx     = $rec['context'];
		$changed = false;
		foreach ( (array) ( $ctx['packages'] ?? array() ) as $i => $p ) {
			$now = '';
			if ( 'plugin' === $p['type'] && ! empty( $p['file'] ) && file_exists( WP_PLUGIN_DIR . '/' . $p['file'] ) ) {
				$d   = get_file_data( WP_PLUGIN_DIR . '/' . $p['file'], array( 'v' => 'Version' ) );
				$now = (string) $d['v'];
			} elseif ( 'theme' === $p['type'] ) {
				$theme_dir = get_theme_root( $p['slug'] ) . '/' . $p['slug'];
				if ( file_exists( $theme_dir . '/style.css' ) ) {
					$d   = get_file_data( $theme_dir . '/style.css', array( 'v' => 'Version' ) );
					$now = (string) $d['v'];
				}
			}
			if ( '' !== $now && $now !== ( $p['to_version'] ?? '' ) ) {
				$ctx['packages'][ $i ]['to_version'] = $now;
				$changed                             = true;
			}
		}
		if ( $changed ) {
			FLXY_Storage::patch( $id, array( 'context' => $ctx ) );
		}
	}

	/** Bu istekte alınan güncelleme öncesi yedeğin kimliği (ör. Flux Updater geri alma için). */
	public static function current_backup_id(): ?string {
		return self::$backup_id;
	}
}
