<?php
/**
 * Güncelleme güvenliği: geçmiş kaydı, güncelleme sonrası site kontrolü, sorun olursa eski sürüme dönüş.
 *
 * - Her FluxWP paket güncellemesinde önceki/sonraki sürüm, kullanıcı ve (Flux Backup varsa) yedek kimliği kaydedilir.
 * - Güncelleme bitince site kendine istek atar (ön yüz + admin-ajax). HTTP 500 dönerse paket bir önceki
 *   sürüme döndürülür: önce sunucudaki eski sürüm zip'i, o yoksa Flux Backup'teki klasör yedeği.
 * - Sürüm değiştirme (geri alma) de buradan yapılır.
 */

defined( 'ABSPATH' ) || exit;

final class FLXU_Guard {

	const LOG_OPTION = 'flxu_log';
	const LOG_MAX    = 1000;

	/** @var self|null */
	private static $instance = null;

	/** @var array slug => {type, name, from, file} bu istekte güncellenen FluxWP paketleri */
	private $before = array();

	/** @var string Bu istekte Flux Backup'in aldığı yedek. */
	private $backup_id = '';

	/** @var bool Geri dönüş sırasında kendi kancalarımızı çalıştırma. */
	private $busy = false;

	/** @var int Bu istekte yazılan geçmiş satırı sayısı. */
	public $logged = 0;

	/** @var string Bu isteğin işlem türü: update | version_change */
	private $action = 'update';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'upgrader_pre_install', array( $this, 'pre_install' ), 5, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'complete' ), 20, 2 );
		add_action( 'flxy_backup_created', array( $this, 'backup_created' ) );
		add_action( 'flxy_backup_appended', array( $this, 'backup_created' ) );
		add_action( 'wp_ajax_nopriv_flxu_ping', array( $this, 'ping' ) );
		add_action( 'wp_ajax_flxu_ping', array( $this, 'ping' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Yedek (Flux Backup eklentisi)                                          */
	/* ------------------------------------------------------------------ */

	public static function backup_ready(): bool {
		return function_exists( 'flxy_is_ready' ) && function_exists( 'flxy_backup_create' ) && flxy_is_ready();
	}

	public function backup_created( $backup ): void {
		if ( is_array( $backup ) && ! empty( $backup['id'] ) && ! $this->backup_id ) {
			$this->backup_id = (string) $backup['id'];
		}
	}

	/** Paket klasörünün ABSPATH'e göre yolu (Flux Backup bu biçimi kullanır). */
	public static function rel_path( string $type, string $slug ): string {
		$dir = 'theme' === $type ? get_theme_root( $slug ) . '/' . $slug : WP_PLUGIN_DIR . '/' . $slug;
		$abs = wp_normalize_path( ABSPATH );
		$dir = wp_normalize_path( $dir );
		return 0 === strpos( $dir, $abs ) ? ltrim( substr( $dir, strlen( $abs ) ), '/' ) : $dir;
	}

	/* ------------------------------------------------------------------ */
	/* Güncelleme kancaları                                                */
	/* ------------------------------------------------------------------ */

	public static function installed_version( string $type, string $slug ): string {
		if ( 'theme' === $type ) {
			$theme = wp_get_theme( $slug );
			return $theme->exists() ? (string) $theme->get( 'Version' ) : '';
		}
		$files = FLXU_Updater::plugin_files();
		if ( ! isset( $files[ $slug ] ) ) {
			return '';
		}
		$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $files[ $slug ], false, false );
		return (string) ( $data['Version'] ?? '' );
	}

	/** Güncellenecek paketin mevcut sürümünü not al. */
	public function pre_install( $response, $extra ) {
		if ( $this->busy || is_wp_error( $response ) || ! is_array( $extra ) ) {
			return $response;
		}
		if ( ! empty( $extra['plugin'] ) ) {
			$type = 'plugin';
			$slug = dirname( (string) $extra['plugin'] );
		} elseif ( ! empty( $extra['theme'] ) ) {
			$type = 'theme';
			$slug = (string) $extra['theme'];
		} else {
			return $response;
		}
		$catalog = FLXU_Updater::instance()->catalog();
		if ( '.' === $slug || ! isset( $catalog[ $slug ] ) || isset( $this->before[ $slug ] ) ) {
			return $response;
		}
		$this->expect( $type, $slug, self::installed_version( $type, $slug ) );
		return $response;
	}

	private function expect( string $type, string $slug, string $from ): void {
		$catalog              = FLXU_Updater::instance()->catalog();
		$this->before[ $slug ] = array(
			'type' => $type,
			'name' => $catalog[ $slug ]['name'] ?? $slug,
			'from' => $from,
		);
	}

	public function complete( $upgrader, $extra ): void {
		if ( $this->busy || ! $this->before ) {
			return;
		}
		$changed = array();
		foreach ( $this->before as $slug => $b ) {
			if ( 'plugin' === $b['type'] ) {
				wp_clean_plugins_cache( false );
			}
			$to = self::installed_version( $b['type'], $slug );
			if ( '' === $to || $to === $b['from'] ) {
				$this->log( $slug, $b, $to, $this->action, 'failed', __( 'Paket değişmedi; kurulum başarısız olmuş olabilir.', 'flx-updater' ) );
				continue;
			}
			$changed[ $slug ] = $b + array( 'to' => $to );
		}
		$this->before = array();
		if ( ! $changed ) {
			return;
		}

		$health = ! empty( FLXU_Updater::settings()['health'] );
		$probe  = $health ? self::probe() : array( 'state' => 'skipped', 'message' => '' );

		foreach ( $changed as $slug => $c ) {
			if ( 'fail' !== $probe['state'] ) {
				// Başarılıda mesaj yok: kontrolün yapılamaması (görünmez mod 404, güvenlik duvarı 403) hata değil.
				$this->log( $slug, $c, $c['to'], $this->action, 'ok', '' );
			}
		}
		if ( 'fail' !== $probe['state'] ) {
			return;
		}

		// Site bozuldu: güncellenen paketleri eski sürüme döndür.
		foreach ( $changed as $slug => $c ) {
			$this->log( $slug, $c, $c['to'], $this->action, 'failed', $probe['message'] );
			$res = $this->revert( $slug, $c );
			$this->log(
				$slug,
				array( 'from' => $c['to'] ) + $c,
				self::installed_version( $c['type'], $slug ),
				'auto_rollback',
				is_wp_error( $res ) ? 'failed' : 'ok',
				is_wp_error( $res ) ? $res->get_error_message() : __( 'Önceki sürüme otomatik dönüldü.', 'flx-updater' )
			);
		}
	}

	/** Paketi $c['from'] sürümüne döndür: sunucudaki zip, yoksa Flux Backup klasör yedeği. */
	private function revert( string $slug, array $c ) {
		$catalog = FLXU_Updater::instance()->catalog();
		foreach ( (array) ( $catalog[ $slug ]['versions'] ?? array() ) as $v ) {
			if ( $v['version'] === $c['from'] ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				// Bu kod dıştaki güncelleyicinin upgrader_process_complete'i içinde çalışır; onun
				// delete_old_plugin vb. kancaları hâlâ takılıdır ve kurulumu bozar. Geçici olarak ayır.
				global $wp_filter;
				$saved = array();
				foreach ( array( 'upgrader_clear_destination', 'upgrader_pre_install', 'upgrader_post_install' ) as $hook ) {
					$saved[ $hook ] = $wp_filter[ $hook ] ?? null;
					unset( $wp_filter[ $hook ] );
				}
				$this->busy = true;
				$res        = self::install_package( $c['type'], $v['package'], new Automatic_Upgrader_Skin() );
				$this->busy = false;
				foreach ( $saved as $hook => $obj ) {
					if ( null !== $obj ) {
						$wp_filter[ $hook ] = $obj; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
					}
				}
				wp_clean_plugins_cache( false );
				$now = self::installed_version( $c['type'], $slug );
				if ( true === $res && $now === $c['from'] ) {
					return true;
				}
				error_log( '[flx-updater] revert ' . $slug . ': ' . ( is_wp_error( $res ) ? $res->get_error_message() : 'installed=' . $now ) ); // phpcs:ignore
				break;
			}
		}
		if ( $this->backup_id && function_exists( 'flxy_backup_restore' ) ) {
			$res = flxy_backup_restore( $this->backup_id, array( 'db' => false, 'paths' => array( self::rel_path( $c['type'], $slug ) ) ) );
			return is_wp_error( $res ) ? $res : true;
		}
		return new WP_Error( 'flxu_no_rollback', __( 'Eski sürüm bulunamadı; elle geri yükleyin.', 'flx-updater' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Sürüm değiştirme (geri alma / belirli sürüm)                       */
	/* ------------------------------------------------------------------ */

	/**
	 * @return true|WP_Error
	 */
	public function change_version( string $slug, string $version, $skin = null ) {
		$catalog = FLXU_Updater::instance()->catalog();
		$p       = $catalog[ $slug ] ?? null;
		if ( ! $p ) {
			return new WP_Error( 'flxu_unknown', __( 'Paket bulunamadı.', 'flx-updater' ) );
		}
		$package = '';
		foreach ( (array) $p['versions'] as $v ) {
			if ( $v['version'] === $version ) {
				$package = $v['package'];
			}
		}
		if ( '' === $package && $version === $p['version'] ) {
			$package = $p['package'];
		}
		if ( '' === $package ) {
			return new WP_Error( 'flxu_version', __( 'Bu sürüm sunucuda yok.', 'flx-updater' ) );
		}
		$from = self::installed_version( $p['type'], $slug );
		if ( '' === $from ) {
			return new WP_Error( 'flxu_missing', __( 'Paket bu sitede kurulu değil.', 'flx-updater' ) );
		}

		// Kurulumda WordPress hangi paketin değiştiğini bildirmez; yedeği burada biz alıyoruz.
		if ( self::backup_ready() ) {
			$backup = flxy_backup_create(
				array(
					'type'    => 'pre_update',
					/* translators: 1: package name, 2: from version, 3: to version */
					'label'   => sprintf( __( '%1$s %2$s → %3$s öncesi', 'flx-updater' ), $p['name'], $from, $version ),
					'db'      => true,
					'paths'   => array( self::rel_path( $p['type'], $slug ) ),
					'context' => array(
						'source'   => 'flx-updater',
						'packages' => array(
							array(
								'type'         => $p['type'],
								'slug'         => $slug,
								'from_version' => $from,
								'to_version'   => $version,
							),
						),
					),
					'sync'    => true,
				)
			);
			if ( is_wp_error( $backup ) ) {
				/* translators: %s: error */
				return new WP_Error( 'flxu_backup', sprintf( __( 'Yedek alınamadı, işlem yapılmadı: %s', 'flx-updater' ), $backup->get_error_message() ) );
			}
			$this->backup_id = (string) ( $backup['id'] ?? '' );
		}

		$this->action = 'version_change';
		$this->expect( $p['type'], $slug, $from );
		add_filter( 'flxy_pre_update_backup', '__return_false' );
		$res = self::install_package( $p['type'], $package, $skin );
		remove_filter( 'flxy_pre_update_backup', '__return_false' );
		// Kurulumda upgrader_process_complete 'install' olarak tetiklenir; yine de emin ol.
		if ( $this->before ) {
			$this->complete( null, array() );
		}
		$this->action = 'update';
		return is_wp_error( $res ) ? $res : true;
	}

	/**
	 * Zip'i mevcut paketin üzerine kurar.
	 *
	 * @return true|WP_Error
	 */
	public static function install_package( string $type, string $package, $skin = null ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$skin     = $skin ? $skin : new Automatic_Upgrader_Skin();
		$upgrader = 'theme' === $type ? new Theme_Upgrader( $skin ) : new Plugin_Upgrader( $skin );
		$res      = $upgrader->install( $package, array( 'overwrite_package' => true ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( ! $res ) {
			$errors = method_exists( $skin, 'get_errors' ) ? $skin->get_errors() : null;
			if ( $errors instanceof WP_Error && $errors->has_errors() ) {
				return $errors;
			}
			$msgs = method_exists( $skin, 'get_upgrade_messages' ) ? implode( ' | ', array_map( 'wp_strip_all_tags', (array) $skin->get_upgrade_messages() ) ) : '';
			return new WP_Error( 'flxu_install', trim( __( 'Kurulum başarısız.', 'flx-updater' ) . ' ' . $msgs ) );
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Site kontrolü                                                       */
	/* ------------------------------------------------------------------ */

	public function ping(): void {
		wp_send_json( array( 'flxu' => 'ok' ) );
	}

	/**
	 * Ön yüz ve admin-ajax'a istek atar. Yalnız HTTP 500 "bozuk" sayılır; ağ hatası, 403/429/503
	 * (Hostinger güvenlik duvarı, bakım) "bilinmiyor" olur ve geri dönüş yapılmaz.
	 *
	 * @return array{state:string,message:string} state: ok | fail | unknown
	 */
	public static function probe(): array {
		$urls = array(
			add_query_arg( 'flxu_health', time(), home_url( '/' ) ),
			add_query_arg( array( 'action' => 'flxu_ping', 'flxu_health' => time() ), admin_url( 'admin-ajax.php' ) ),
		);
		$unknown = '';
		foreach ( $urls as $url ) {
			$code = 0;
			for ( $try = 0; $try < 2; $try++ ) {
				$res = wp_remote_get(
					$url,
					array(
						'timeout'     => 20,
						'redirection' => 3,
						'headers'     => array( 'Cache-Control' => 'no-cache' ),
						'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
					)
				);
				$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
				if ( 500 !== $code ) {
					break;
				}
				sleep( 2 );
			}
			if ( 500 === $code ) {
				/* translators: %s: URL path */
				return array( 'state' => 'fail', 'message' => sprintf( __( 'Güncellemeden sonra site hata verdi (HTTP 500: %s).', 'flx-updater' ), (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
			}
			if ( 200 !== $code && '' === $unknown ) {
				$unknown = 0 === $code
					? __( 'Site kontrolü yapılamadı (bağlantı kurulamadı).', 'flx-updater' )
					/* translators: %d: HTTP status */
					: sprintf( __( 'Site kontrolü HTTP %d döndü; hata sayılmadı.', 'flx-updater' ), $code );
			}
		}
		return '' === $unknown ? array( 'state' => 'ok', 'message' => '' ) : array( 'state' => 'unknown', 'message' => $unknown );
	}

	/* ------------------------------------------------------------------ */
	/* Geçmiş                                                              */
	/* ------------------------------------------------------------------ */

	private function log( string $slug, array $b, string $to, string $action, string $result, string $message ): void {
		$log = self::history();
		array_unshift(
			$log,
			array(
				'time'    => time(),
				'user'    => wp_doing_cron() ? '' : (string) wp_get_current_user()->display_name,
				'auto'    => wp_doing_cron(),
				'slug'    => $slug,
				'name'    => (string) ( $b['name'] ?? $slug ),
				'type'    => (string) ( $b['type'] ?? 'plugin' ),
				'from'    => (string) ( $b['from'] ?? '' ),
				'to'      => $to,
				'action'  => $action,
				'result'  => $result,
				'message' => $message,
				'backup'  => $this->backup_id,
			)
		);
		update_site_option( self::LOG_OPTION, array_slice( $log, 0, self::LOG_MAX ) );
		++$this->logged;
	}

	/** Kurulum gibi güncelleme dışı işlemleri geçmişe yazar (FLXU_Installer). */
	public function record( string $slug, string $name, string $type, string $to, string $action, bool $ok, string $message = '' ): void {
		$keep            = $this->backup_id;
		$this->backup_id = '';
		$this->log( $slug, array( 'name' => $name, 'type' => $type, 'from' => '' ), $to, $action, $ok ? 'ok' : 'error', $message );
		$this->backup_id = $keep;
	}

	public static function history(): array {
		$log = get_site_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}
}
