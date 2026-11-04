<?php
/**
 * Sol menüde "Güncellemeler": FluxWP paketleri, seçerek/toplu güncelleme, sürüm değiştirme, geçmiş, ayarlar.
 */

defined( 'ABSPATH' ) || exit;

final class FLXU_Admin {

	const SLUG          = 'flx-setup';
	const SETTINGS_SLUG = 'flx-setup-settings';

	/** @var self|null */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_head', array( $this, 'menu_icon_css' ) );
		add_action( 'admin_init', array( $this, 'redirect_old_url' ) );
		// Kayıtsız sayfa (eski slug) admin_init'ten önce erişim reddiyle kesilir.
		add_action( 'admin_page_access_denied', array( $this, 'redirect_old_url' ) );
		add_action( 'admin_post_flxu_save', array( $this, 'save' ) );
		add_action( 'admin_post_flxu_check', array( $this, 'check' ) );
		add_action( 'admin_post_flxu_source', array( $this, 'source_action' ) );
		add_action( 'admin_post_flxu_backups', array( $this, 'backups_action' ) );
		add_action( 'admin_post_flxu_manage', array( $this, 'manage_action' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FLXU_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/** Bekleyen FluxWP güncellemesi sayısı (önbellekteki katalogdan, ağ isteği yapmaz). */
	public static function pending_count(): int {
		$n = 0;
		foreach ( self::rows( FLXU_Updater::instance()->catalog() ) as $row ) {
			if ( 'update' === $row['state'] ) {
				++$n;
			}
		}
		return $n;
	}

	public function menu(): void {
		$n     = current_user_can( 'update_plugins' ) ? self::pending_count() : 0;
		$badge = $n ? ' <span class="update-plugins count-' . $n . '"><span class="plugin-count">' . number_format_i18n( $n ) . '</span></span>' : '';
		// Çağrı Merkezi (3) ve Görevler (4) üstünde, Başlangıç (2) altında.
		add_menu_page(
			'FluxWP Panel',
			'FluxWP Panel' . $badge,
			'update_plugins',
			self::SLUG,
			array( $this, 'render' ),
			'none',
			2.9
		);
		add_submenu_page( self::SLUG, __( 'Flux Paketleri', 'flx-setup' ), __( 'Paketler', 'flx-setup' ), 'update_plugins', self::SLUG, array( $this, 'render' ) );
		if ( function_exists( 'flxk_cap' ) && current_user_can( flxk_cap() ) ) {
			// Ön ayarlar ekranı Görünüm altında (ya da tema ayarlarında) kalır; buradan kısayol.
			add_submenu_page( self::SLUG, __( 'Ön ayarlar', 'flx-setup' ), __( 'Ön ayarlar', 'flx-setup' ), flxk_cap(), 'flx-setup-presets', '__return_null' );
		}
		add_submenu_page( self::SLUG, __( 'Güncelleme ayarları', 'flx-setup' ), __( 'Ayarlar', 'flx-setup' ), 'manage_options', self::SETTINGS_SLUG, array( $this, 'render_settings' ) );
	}

	/** WordPress menü SVG'lerini tek renge boyar; renkli logo CSS ile. */
	public function menu_icon_css(): void {
		$url = plugins_url( 'assets/icons/flx-setup.svg', FLXU_FILE ) . '?ver=' . FLXU_VERSION;
		echo '<style>#toplevel_page_' . esc_attr( self::SLUG ) . ' .wp-menu-image{background:url(' . esc_url( $url ) . ') center/20px 20px no-repeat}</style>';
	}

	/** Eski adresler: Ayarlar › Flux Updater (1.0.x), admin.php?page=flx-updater(-ayarlar), FluxWP Panel › Ön ayarlar kısayolu. */
	public function redirect_old_url(): void {
		global $pagenow;
		$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'options-general.php' === $pagenow && 'flx-updater' === $page ) {
			wp_safe_redirect( self::url() );
			exit;
		}
		if ( 'admin.php' === $pagenow && in_array( $page, array( 'flx-updater', 'flx-updater-ayarlar' ), true ) ) {
			$args = array_map( 'sanitize_text_field', wp_unslash( array_diff_key( $_GET, array( 'page' => 1 ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( self::url( $args, 'flx-updater' === $page ? self::SLUG : self::SETTINGS_SLUG ) );
			exit;
		}
		if ( 'admin.php' === $pagenow && 'flx-setup-presets' === $page && function_exists( 'flxk_admin_url' ) ) {
			wp_safe_redirect( flxk_admin_url() );
			exit;
		}
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Paketler', 'flx-setup' ) . '</a>' );
		return $links;
	}

	/** Eklentiler listesinde bağlantı durumu. */
	public function row_meta( $meta, $file ) {
		if ( plugin_basename( FLXU_FILE ) !== $file ) {
			return $meta;
		}
		$status = FLXU_Updater::status();
		$state  = self::connection_state( $status );
		$labels = array(
			'connected' => array( '#1e8e3e', __( 'Bağlı', 'flx-setup' ) . ( ! empty( $status['site'] ) ? ': ' . $status['site'] : '' ) ),
			'error'     => array( 'paused' === ( $status['code'] ?? '' ) ? '#dba617' : '#d63638', FLXU_Updater::block_title( $status ) ?: __( 'Bağlantı hatası', 'flx-setup' ) ),
			'unknown'   => array( '#dba617', __( 'Henüz kontrol edilmedi', 'flx-setup' ) ),
		);
		list( $color, $text ) = $labels[ $state ];
		$meta[] = '<a href="' . esc_url( self::url() ) . '"><span style="color:' . esc_attr( $color ) . '">&#9679;</span> ' . esc_html( $text ) . '</a>';
		return $meta;
	}

	public static function url( array $args = array(), string $page = self::SLUG ): string {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/* ------------------------------------------------------------------ */
	/* İşlemler                                                            */
	/* ------------------------------------------------------------------ */

	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-setup' ), 403 );
		}
		check_admin_referer( 'flxu_save' );

		$old    = FLXU_Updater::settings();
		$server = esc_url_raw( trim( wp_unslash( $_POST['server'] ?? '' ) ) );
		if ( $server && 'https' !== wp_parse_url( $server, PHP_URL_SCHEME ) ) {
			$server = '';
		}
		$key = trim( sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) ) );

		$new = array(
			'server' => untrailingslashit( $server ),
			// Boş bırakılırsa mevcut anahtar korunur.
			'key'    => '' !== $key ? $key : $old['key'],
			'auto'   => empty( $_POST['auto'] ) ? 0 : 1,
			'health' => empty( $_POST['health'] ) ? 0 : 1,
		);
		if ( ! empty( $_POST['clear_key'] ) ) {
			$new['key'] = '';
		}
		update_site_option( FLXU_Updater::SETTINGS_OPTION, $new );
		if ( function_exists( 'flxk_set_telemetry' ) ) {
			flxk_set_telemetry( ! empty( $_POST['usage'] ) );
		}

		FLXU_Updater::instance()->check_now();
		$connected = 'connected' === self::connection_state( FLXU_Updater::status() );
		wp_safe_redirect( $connected ? self::url( array( 'flxu_msg' => 'saved' ) ) : self::url( array( 'flxu_msg' => 'saved' ), self::SETTINGS_SLUG ) );
		exit;
	}

	public function check(): void {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-setup' ), 403 );
		}
		check_admin_referer( 'flxu_check' );
		FLXU_Updater::instance()->check_now();
		wp_safe_redirect( self::url( array( 'flxu_msg' => 'checked' ) ) );
		exit;
	}

	/** Diğer kaynaklar: ekle / sil / aç-kapat / yukarı. */
	public function source_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-setup' ), 403 );
		}
		check_admin_referer( 'flxu_source' );
		$op   = sanitize_key( wp_unslash( $_POST['op'] ?? '' ) );
		$id   = sanitize_key( wp_unslash( $_POST['id'] ?? '' ) );
		$args = array( 'flxu_msg' => 'src_' . $op );
		if ( 'add' === $op ) {
			$res = FLXU_Sources::add(
				(string) wp_unslash( $_POST['name'] ?? '' ),
				(string) wp_unslash( $_POST['url'] ?? '' ),
				trim( (string) wp_unslash( $_POST['key'] ?? '' ) )
			);
			if ( is_wp_error( $res ) ) {
				set_transient( 'flxu_src_err_' . get_current_user_id(), $res->get_error_message(), 5 * MINUTE_IN_SECONDS );
				$args = array( 'flxu_msg' => 'src_error' );
			}
		} elseif ( 'remove' === $op ) {
			FLXU_Sources::remove( $id );
		} elseif ( 'toggle' === $op ) {
			FLXU_Sources::toggle( $id );
		} elseif ( 'up' === $op ) {
			FLXU_Sources::move_up( $id );
		}
		FLXU_Updater::instance()->check_now();
		wp_safe_redirect( self::url( $args, self::SETTINGS_SLUG ) . '#flxu-sources' );
		exit;
	}

	/**
	 * JavaScript kapalıyken: seçimi gerekli eklentilerle tamamlayıp adımları bu istekte sırayla çalıştırır.
	 * (Panel normalde aynı adımları FLXU_Ajax ile sayfadan çıkmadan çalıştırır.)
	 */
	private function run_fallback( array $slugs, bool $activate ): void {
		$catalog = FLXU_Updater::instance()->catalog();
		$steps   = FLXU_Installer::steps( FLXU_Installer::plan( $slugs, $catalog, self::rows( $catalog ) ), $catalog, $activate );
		if ( ! $steps ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Kurulacak ya da güncellenecek paket seçilmedi.', 'flx-setup' ) . '</p></div>';
		}
		foreach ( $steps as $st ) {
			$res = FLXU_Ajax::run_step( $st['kind'], $st['slug'] );
			foreach ( $res['lines'] as $l ) {
				echo '<div class="notice notice-' . ( $l['ok'] ? 'success' : 'error' ) . ' inline"><p>' . esc_html( $l['text'] ) . '</p></div>';
			}
		}
		FLXU_Updater::instance()->check_now();
		echo '<p><a class="button button-primary" href="' . esc_url( self::url() ) . '">' . esc_html__( 'Güncellemelere dön', 'flx-setup' ) . '</a></p>';
	}

	private function version_change( string $slug, string $version ): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$catalog = FLXU_Updater::instance()->catalog();
		$p       = $catalog[ $slug ] ?? null;
		if ( ! $p ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Paket bulunamadı.', 'flx-setup' ) . '</p></div>';
			return;
		}
		/* translators: 1: package name, 2: version */
		$title = sprintf( __( '%1$s %2$s sürümü kuruluyor', 'flx-setup' ), $p['name'], $version );
		echo '<h2>' . esc_html( $title ) . '</h2>';
		$skin = 'theme' === $p['type'] ? new Theme_Installer_Skin( array( 'title' => '' ) ) : new Plugin_Installer_Skin( array( 'title' => '' ) );
		$res  = FLXU_Guard::instance()->change_version( $slug, $version, $skin );
		if ( is_wp_error( $res ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $res->get_error_message() ) . '</p></div>';
		}
		$this->after_action_summary();
	}

	/** İşlem sonrası: geçmişin son satırları + dönüş düğmesi. */
	private function after_action_summary(): void {
		FLXU_Updater::instance()->check_now();
		$recent = array_reverse( array_slice( FLXU_Guard::history(), 0, FLXU_Guard::instance()->logged ) );
		echo '<h2>' . esc_html__( 'Sonuç', 'flx-setup' ) . '</h2>';
		$shown = 0;
		foreach ( $recent as $e ) {
			++$shown;
			$ok = 'ok' === $e['result'];
			echo '<div class="notice notice-' . ( $ok ? 'success' : 'error' ) . ' inline"><p><strong>' . esc_html( $e['name'] ) . '</strong> ' . esc_html( self::entry_tail( $e ) ) . '</p></div>';
		}
		if ( ! $shown ) {
			echo '<p>' . esc_html__( 'Kayıt yok.', 'flx-setup' ) . '</p>';
		}
		echo '<p><a class="button button-primary" href="' . esc_url( self::url() ) . '">' . esc_html__( 'Güncellemelere dön', 'flx-setup' ) . '</a></p>';
	}

	/** Geçmiş kaydının tek satırlık metni: "Ad güncellendi (1.0 → 1.1) · Başarılı ✓"; mesaj yalnız sorunda yazılır. */
	public static function entry_text( array $e ): string {
		return $e['name'] . ' ' . self::entry_tail( $e );
	}

	public static function entry_tail( array $e ): string {
		$tail = self::action_label( $e );
		if ( 'ok' === $e['result'] && 'auto_rollback' !== $e['action'] ) {
			return $tail . ' · ' . __( 'Başarılı ✓', 'flx-setup' );
		}
		return $tail . ( $e['message'] ? ' · ' . $e['message'] : '' );
	}

	public static function action_label( array $e ): string {
		$v = $e['from'] . ' → ' . ( '' !== $e['to'] ? $e['to'] : '?' );
		switch ( $e['action'] ) {
			case 'version_change':
				/* translators: %s: from → to */
				return sprintf( __( 'sürüm değiştirildi (%s)', 'flx-setup' ), $v );
			case 'install':
				return 'ok' === $e['result']
					/* translators: %s: version */
					? sprintf( __( 'kuruldu %s', 'flx-setup' ), $e['to'] )
					: __( 'kurulamadı', 'flx-setup' );
			case 'auto_rollback':
				/* translators: %s: from → to */
				return sprintf( __( 'otomatik geri alındı (%s)', 'flx-setup' ), $v );
			default:
				if ( 'ok' !== $e['result'] ) {
					/* translators: %s: from → to */
					return sprintf( __( 'güncelleme sorunlu (%s)', 'flx-setup' ), $v );
				}
				/* translators: %s: from → to */
				return sprintf( __( 'güncellendi (%s)', 'flx-setup' ), $v );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Tablo verisi                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Katalogdaki her paket için: name, slug, type, installed, latest, state (update|current|missing).
	 */
	public static function rows( array $catalog ): array {
		$files   = FLXU_Updater::plugin_files();
		$plugins = get_plugins();
		$rows    = array();
		foreach ( $catalog as $slug => $p ) {
			$installed = '';
			$active    = false;
			if ( 'plugin' === $p['type'] && isset( $files[ $slug ] ) ) {
				$installed = (string) ( $plugins[ $files[ $slug ] ]['Version'] ?? '' );
				$active    = '' !== $installed && is_plugin_active( $files[ $slug ] );
			} elseif ( 'theme' === $p['type'] ) {
				$theme = wp_get_theme( $slug );
				if ( $theme->exists() ) {
					$installed = (string) $theme->get( 'Version' );
					$active    = in_array( $slug, array( get_stylesheet(), get_template() ), true );
				}
			}
			if ( '' === $installed ) {
				$state = 'missing';
			} elseif ( version_compare( $p['version'], $installed, '>' ) ) {
				$state = 'update';
			} else {
				$state = 'current';
			}
			$rows[] = array(
				'slug'      => $slug,
				'name'      => $p['name'],
				'type'      => $p['type'],
				'installed' => $installed,
				'active'    => $active,
				'latest'    => $p['version'],
				'released'  => $p['released'],
				'state'     => $state,
				'package'   => $p['package'],
			);
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				$order = array( 'update' => 0, 'current' => 1, 'missing' => 2 );
				return array( $order[ $a['state'] ], $a['type'], $a['name'] ) <=> array( $order[ $b['state'] ], $b['type'], $b['name'] );
			}
		);
		return $rows;
	}

	/* ------------------------------------------------------------------ */
	/* Ekran                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Bağlantı durumu: connected | error | unknown. Anahtarsız bağlantı da "connected" (public paketler).
	 */
	public static function connection_state( array $status ): string {
		if ( empty( $status['checked'] ) ) {
			return 'unknown';
		}
		return empty( $status['ok'] ) ? 'error' : 'connected';
	}

	/** "Son kontrol: 2 Kasım 2026 17:12" */
	public static function checked_label( array $status ): string {
		if ( empty( $status['checked'] ) ) {
			return __( 'Henüz kontrol edilmedi', 'flx-setup' );
		}
		$t = (int) $status['checked'];
		/* translators: %s: date and time */
		return sprintf( __( 'Son kontrol: %s', 'flx-setup' ), flx_date( $t, 'datetime' ) );
	}

	private static function status_card( array $status, array $catalog ): void {
		$state = self::connection_state( $status );
		$host  = (string) wp_parse_url( FLXU_Updater::server(), PHP_URL_HOST );
		switch ( $state ) {
			case 'connected':
				$title = __( 'Bağlı', 'flx-setup' );
				if ( '' !== (string) ( $status['error'] ?? '' ) ) {
					$line = (string) $status['error'];
				} elseif ( '' === FLXU_Updater::key() ) {
					/* translators: %s: server host */
					$line = sprintf( __( 'Anahtarsız bağlı (%s): public paketler geliyor. Private paketler için site anahtarı girin.', 'flx-setup' ), $host );
				} else {
					/* translators: 1: site name on server, 2: server host */
					$line = sprintf( __( 'Sunucuda "%1$s" olarak tanınıyor (%2$s).', 'flx-setup' ), $status['site'], $host );
				}
				break;
			case 'error':
				$title = FLXU_Updater::block_title( $status ) ?: __( 'Bağlantı hatası', 'flx-setup' );
				$line  = FLXU_Updater::status_message( $status, '' !== FLXU_Updater::key(), $host );
				if ( 'paused' === ( $status['code'] ?? '' ) ) {
					$state = 'paused';
				}
				break;
			default:
				$title = __( 'Henüz kontrol edilmedi', 'flx-setup' );
				$line  = __( '"Bağlantıyı test et" ile sunucuya bağlanın.', 'flx-setup' );
		}
		$updates = 0;
		foreach ( self::rows( $catalog ) as $row ) {
			if ( 'update' === $row['state'] ) {
				++$updates;
			}
		}
		?>
		<div class="flxu-card flxu-card--<?php echo esc_attr( $state ); ?>">
			<span class="flxu-dot" aria-hidden="true"></span>
			<div class="flxu-card__body">
				<strong class="flxu-card__title"><?php echo esc_html( $title ); ?></strong>
				<span><?php echo esc_html( $line ); ?></span>
				<?php if ( ! empty( $status['checked'] ) ) : ?>
					<small>
						<?php
						/* translators: %s: human time diff */
						echo esc_html( sprintf( __( 'Son kontrol: %s', 'flx-setup' ), flx_time_ago( (int) $status['checked'] ) ) );
						if ( 'connected' === $state ) {
							echo ' · ';
							/* translators: 1: package count, 2: update count */
							echo esc_html( sprintf( __( '%1$d paket, %2$d güncelleme', 'flx-setup' ), count( $catalog ), $updates ) );
						}
						?>
					</small>
				<?php endif; ?>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="flxu_check">
				<?php wp_nonce_field( 'flxu_check' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Bağlantıyı test et', 'flx-setup' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Sunucu adresi + site anahtarı + otomatik güncelleme formu.
	 */
	private static function connection_form( array $settings ): void {
		?>
			<h2><?php esc_html_e( 'Bağlantı', 'flx-setup' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="flxu_save">
				<?php wp_nonce_field( 'flxu_save' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="flxu-server"><?php esc_html_e( 'Sunucu adresi', 'flx-setup' ); ?></label></th>
						<td>
							<?php if ( defined( 'FLX_UPDATE_SERVER' ) && FLX_UPDATE_SERVER ) : ?>
								<code><?php echo esc_html( FLXU_Updater::server() ); ?></code>
								<p class="description"><?php esc_html_e( 'wp-config.php içindeki FLX_UPDATE_SERVER sabitinden geliyor.', 'flx-setup' ); ?></p>
							<?php else : ?>
								<input type="url" id="flxu-server" name="server" class="regular-text" value="<?php echo esc_attr( $settings['server'] ); ?>" placeholder="<?php echo esc_attr( FLXU_DEFAULT_SERVER ); ?>">
								<p class="description"><?php esc_html_e( 'Boş bırakılırsa varsayılan sunucu kullanılır. Yalnız https.', 'flx-setup' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="flxu-key"><?php esc_html_e( 'Site anahtarı', 'flx-setup' ); ?></label></th>
						<td>
							<?php if ( FLXU_Updater::key_from_config() ) : ?>
								<code>••••••••</code>
								<p class="description"><?php esc_html_e( 'wp-config.php içindeki FLX_UPDATE_KEY sabitinden geliyor.', 'flx-setup' ); ?></p>
							<?php else : ?>
								<input type="password" id="flxu-key" name="key" class="regular-text" autocomplete="off" value="" placeholder="<?php echo $settings['key'] ? esc_attr__( 'Kayıtlı (değiştirmek için yeni anahtarı yazın)', 'flx-setup' ) : 'flxu_…'; ?>">
								<?php if ( $settings['key'] ) : ?>
									<label style="margin-left:8px"><input type="checkbox" name="clear_key" value="1"> <?php esc_html_e( 'Anahtarı sil', 'flx-setup' ); ?></label>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'İsteğe bağlı. Public paketler anahtarsız gelir; private paketler için güncelleme sunucusunun panelinde (/admin) "Site ekle" ile alınır. Her siteye ayrı anahtar verin.', 'flx-setup' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Güncelleme sonrası kontrol', 'flx-setup' ); ?></th>
						<td><label><input type="checkbox" name="health" value="1" <?php checked( ! empty( $settings['health'] ) ); ?>> <?php esc_html_e( 'Güncellemeden sonra site açılıyor mu kontrol et; hata verirse paketi önceki sürüme döndür', 'flx-setup' ); ?></label>
							<p class="description"><?php esc_html_e( 'Yalnız HTTP 500 (kritik hata) geri alınır; ağ veya güvenlik duvarı hataları geri alma sebebi sayılmaz.', 'flx-setup' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Otomatik güncelleme', 'flx-setup' ); ?></th>
						<td><label><input type="checkbox" name="auto" value="1" <?php checked( ! empty( $settings['auto'] ) ); ?>> <?php esc_html_e( 'FluxWP paketlerinin yeni sürümleri kendiliğinden kurulsun', 'flx-setup' ); ?></label>
							<p class="description"><?php esc_html_e( 'Kapalıyken WordPress\'in eklenti başına "Otomatik güncellemeyi etkinleştir" ayarı geçerlidir.', 'flx-setup' ); ?></p>
						</td>
					</tr>
					<?php if ( function_exists( 'flxk_telemetry_enabled' ) ) : ?>
						<?php $legal = flxk_legal_urls(); ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Kullanım verisi', 'flx-setup' ); ?></th>
							<td><label><input type="checkbox" name="usage" value="1" <?php checked( flxk_telemetry_enabled() ); ?>> <?php esc_html_e( 'Ürünü geliştirmemize yardımcı olun (kullanım verisi gönder)', 'flx-setup' ); ?></label>
								<p class="description"><?php esc_html_e( 'Açıkken güncelleme denetimine site adresi, WordPress ve PHP sürümü, saat dilimi ve dil eklenir; FluxWP eklentilerinin sürümleri ve hataları anonim olarak gönderilir. Kapalıyken yalnız güncelleme için gereken bilgi gider, güncellemeler aynı çalışır. İzni istediğiniz zaman buradan geri alabilirsiniz.', 'flx-setup' ); ?></p>
								<p class="description">
									<a href="<?php echo esc_url( $legal['kvkk'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'KVKK Aydınlatma Metni', 'flx-setup' ); ?></a> ·
									<a href="<?php echo esc_url( $legal['privacy'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Gizlilik Politikası', 'flx-setup' ); ?></a> ·
									<a href="<?php echo esc_url( $legal['terms'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Kullanım Şartları', 'flx-setup' ); ?></a>
								</p>
							</td>
						</tr>
					<?php endif; ?>
				</table>
				<?php submit_button( __( 'Kaydet ve kontrol et', 'flx-setup' ) ); ?>
			</form>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Ekranlar                                                            */
	/* ------------------------------------------------------------------ */

	public function render(): void {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['flxu_install'] ) ) {
			$slug = sanitize_key( wp_unslash( $_GET['flxu_install'] ) );
			check_admin_referer( 'flxu_install_' . $slug );
			echo '<div class="wrap flxu-wrap"><h1>' . esc_html__( 'Paketler', 'flx-setup' ) . '</h1>';
			$this->run_fallback( array( $slug ), true );
			echo '</div>';
			return;
		}
		$do = isset( $_POST['flxu_do'] ) ? sanitize_key( wp_unslash( $_POST['flxu_do'] ) ) : '';
		if ( $do ) {
			check_admin_referer( 'flxu_bulk' );
			echo '<div class="wrap flxu-wrap"><h1>' . esc_html__( 'Paketler', 'flx-setup' ) . '</h1>';
			if ( 'version' === $do ) {
				$slug    = sanitize_key( wp_unslash( $_POST['flxu_slug'] ?? '' ) );
				$version = sanitize_text_field( wp_unslash( $_POST['flxu_version'] ?? '' ) );
				$p       = FLXU_Updater::instance()->catalog()[ $slug ] ?? null;
				if ( $p && current_user_can( 'theme' === $p['type'] ? 'update_themes' : 'update_plugins' ) ) {
					$this->version_change( $slug, $version );
				}
			} else {
				$slugs = 'all' === $do
					? wp_list_pluck( array_filter( self::rows( FLXU_Updater::instance()->catalog() ), static function ( $r ) {
						return 'update' === $r['state'];
					} ), 'slug' )
					: array_map( 'sanitize_key', (array) wp_unslash( $_POST['flxu_slugs'] ?? array() ) );
				if ( 'one' === $do ) {
					$slugs = array( sanitize_key( wp_unslash( $_POST['flxu_slug'] ?? '' ) ) );
				}
				$this->run_fallback( array_values( $slugs ), ! empty( $_POST['flxu_activate'] ) || 'one' === $do );
			}
			echo '</div>';
			return;
		}
		$msg = isset( $_GET['flxu_msg'] ) ? sanitize_key( wp_unslash( $_GET['flxu_msg'] ) ) : '';
		// phpcs:enable

		$status  = FLXU_Updater::status();
		$catalog = FLXU_Updater::instance()->catalog();
		$rows    = self::rows( $catalog );
		$pending = count( array_filter( $rows, static function ( $r ) {
			return 'update' === $r['state'];
		} ) );
		$state   = self::connection_state( $status );
		?>
		<div class="wrap flxu-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Paketler', 'flx-setup' ); ?></h1>
			<a class="page-title-action" id="flxu-check" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxu_check' ), 'flxu_check' ) ); ?>"><span class="flxu-check__spin" aria-hidden="true"></span><?php esc_html_e( 'Güncellemeleri kontrol et', 'flx-setup' ); ?></a>
			<span class="flxu-checked" id="flxu-checked"><?php echo esc_html( self::checked_label( $status ) ); ?></span>
			<hr class="wp-header-end">

			<div id="flxu-log" class="flxu-log" hidden>
				<div class="flxu-log__head">
					<span class="flxu-log__spin"></span>
					<strong class="flxu-log__title"></strong>
					<span class="flxu-log__count"></span>
					<span class="flxu-toolbar__spacer"></span>
					<button type="button" class="button-link flxu-log__toggle"><?php esc_html_e( 'Kaydı gizle', 'flx-setup' ); ?></button>
				</div>
				<div class="flxu-log__bar"><span></span></div>
				<ol class="flxu-log__lines"></ol>
			</div>

			<?php if ( 'saved' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ayarlar kaydedildi ve kontrol yapıldı.', 'flx-setup' ); ?></p></div>
			<?php elseif ( 'managed' === $msg && ( $mr = get_transient( 'flxu_manage_' . get_current_user_id() ) ) ) : ?>
				<?php
				delete_transient( 'flxu_manage_' . get_current_user_id() );
				$labels = array(
					/* translators: %d: number of packages */
					'activate'   => _n( '%d paket etkinleştirildi.', '%d paket etkinleştirildi.', (int) $mr['done'], 'flx-setup' ),
					/* translators: %d: number of packages */
					'deactivate' => _n( '%d paket devre dışı bırakıldı.', '%d paket devre dışı bırakıldı.', (int) $mr['done'], 'flx-setup' ),
					/* translators: %d: number of packages */
					'delete'     => _n( '%d paket kaldırıldı.', '%d paket kaldırıldı.', (int) $mr['done'], 'flx-setup' ),
				);
				?>
				<div class="notice notice-<?php echo $mr['fail'] ? 'warning' : 'success'; ?> is-dismissible"><p>
					<?php echo esc_html( sprintf( $labels[ $mr['op'] ] ?? '%d', (int) $mr['done'] ) ); ?>
					<?php if ( $mr['skip'] ) : ?>
						<?php /* translators: %s: package names */ echo esc_html( sprintf( __( 'Atlananlar (zaten bu durumda, etkin tema ya da FluxWP Panel): %s.', 'flx-setup' ), implode( ', ', $mr['skip'] ) ) ); ?>
					<?php endif; ?>
					<?php if ( $mr['fail'] ) : ?>
						<?php /* translators: %s: package names */ echo esc_html( sprintf( __( 'Yapılamayanlar: %s.', 'flx-setup' ), implode( ', ', $mr['fail'] ) ) ); ?>
					<?php endif; ?>
				</p></div>
			<?php elseif ( 'checked' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Güncellemeler kontrol edildi.', 'flx-setup' ); ?></p></div>
			<?php elseif ( 'bk_deleted' === $msg ) : ?>
				<?php $n = isset( $_GET['flxu_n'] ) ? absint( $_GET['flxu_n'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( _n( '%d yedek silindi.', '%d yedek silindi.', $n, 'flx-setup' ), $n ) ); ?></p></div>
			<?php elseif ( 'keep_saved' === $msg ) : ?>
				<?php $n = isset( $_GET['flxu_n'] ) ? absint( $_GET['flxu_n'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php
					esc_html_e( 'Saklama süresi kaydedildi; mevcut güncelleme yedeklerine de uygulandı.', 'flx-setup' );
					if ( $n ) {
						echo ' ' . esc_html( sprintf( _n( 'Süresi dolan %d yedek silindi.', 'Süresi dolan %d yedek silindi.', $n, 'flx-setup' ), $n ) );
					}
				?></p></div>
			<?php endif; ?>

			<div id="flxu-status-wrap">
				<?php if ( 'connected' !== $state ) : ?>
					<?php self::status_card( $status, $catalog ); ?>
				<?php endif; ?>
				<?php self::failing_sources(); ?>
			</div>

			<?php if ( 'connected' !== $state && current_user_can( 'manage_options' ) && ! $rows ) : ?>
				<?php self::connection_form( FLXU_Updater::settings() ); ?>
			<?php endif; ?>

			<form method="post" id="flxu-packages" data-deps="<?php echo esc_attr( wp_json_encode( FLXU_Installer::client_data( $catalog, $rows ) ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( FLXU_Ajax::NONCE ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
				<?php wp_nonce_field( 'flxu_bulk' ); ?>
				<input type="hidden" name="flxu_slug" value="">
				<input type="hidden" name="flxu_version" value="">
				<div class="flxu-toolbar">
					<h2><?php esc_html_e( 'FluxWP paketleri', 'flx-setup' ); ?></h2>
					<span class="flxu-toolbar__spacer"></span>
					<label class="flxu-act"><input type="checkbox" name="flxu_activate" value="1" id="flxu-activate" checked> <?php esc_html_e( 'Kurulanları etkinleştir', 'flx-setup' ); ?></label>
					<button type="submit" class="button" name="flxu_do" value="selected" id="flxu-do-selected" disabled><?php esc_html_e( 'Seçilenleri kur / güncelle', 'flx-setup' ); ?></button>
					<span class="flxu-bulk">
						<label class="screen-reader-text" for="flxu-bulk-op"><?php esc_html_e( 'Toplu işlem', 'flx-setup' ); ?></label>
						<select name="flxu_op" id="flxu-bulk-op">
							<option value=""><?php esc_html_e( 'Toplu işlemler', 'flx-setup' ); ?></option>
							<option value="activate"><?php esc_html_e( 'Etkinleştir', 'flx-setup' ); ?></option>
							<option value="deactivate"><?php esc_html_e( 'Devre dışı bırak', 'flx-setup' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Kaldır', 'flx-setup' ); ?></option>
						</select>
						<button type="submit" class="button" name="flxu_do" value="manage" id="flxu-do-manage" formaction="<?php echo esc_url( admin_url( 'admin-post.php?action=flxu_manage' ) ); ?>" disabled><?php esc_html_e( 'Uygula', 'flx-setup' ); ?></button>
					</span>
					<button type="submit" class="button button-primary" name="flxu_do" value="all" <?php disabled( 0 === $pending ); ?>>
						<?php
						/* translators: %s: number of updates */
						echo esc_html( sprintf( __( 'Tümünü güncelle (%s)', 'flx-setup' ), number_format_i18n( $pending ) ) );
						?>
					</button>
				</div>
				<div id="flxu-deps" class="flxu-deps" hidden></div>
				<table class="widefat striped flxu-table">
					<thead><tr>
						<td class="check-column"><input type="checkbox" id="flxu-check-all" aria-label="<?php esc_attr_e( 'Tümünü seç', 'flx-setup' ); ?>"></td>
						<th><?php esc_html_e( 'Paket', 'flx-setup' ); ?></th>
						<th><?php esc_html_e( 'Tür', 'flx-setup' ); ?></th>
						<th><?php esc_html_e( 'Kurulu', 'flx-setup' ); ?></th>
						<th><?php esc_html_e( 'Son sürüm', 'flx-setup' ); ?></th>
						<th><?php esc_html_e( 'Durum', 'flx-setup' ); ?></th>
					</tr></thead>
					<tbody>
					<?php if ( ! $rows ) : ?>
						<tr><td></td><td colspan="5"><?php esc_html_e( 'Sunucudan paket listesi alınamadı. Ayarlar\'dan sunucu adresini ve (varsa) anahtarı kontrol edip "Bağlantıyı test et"e basın.', 'flx-setup' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$versions = array();
						foreach ( (array) ( $catalog[ $row['slug'] ]['versions'] ?? array() ) as $v ) {
							if ( $v['version'] !== $row['installed'] ) {
								$versions[] = $v;
							}
						}
						$can     = current_user_can( 'theme' === $row['type'] ? 'update_themes' : 'update_plugins' );
						$can_ins = current_user_can( 'theme' === $row['type'] ? 'install_themes' : 'install_plugins' );
						$req     = FLXU_Updater::requires_plugins( $row['slug'], $catalog[ $row['slug'] ] ?? array() );
						?>
						<tr class="flxu-row flxu-row--<?php echo esc_attr( $row['state'] ); ?>" data-slug="<?php echo esc_attr( $row['slug'] ); ?>">
							<th class="check-column" scope="row">
								<?php if ( ( 'update' === $row['state'] && $can ) || ( 'missing' === $row['state'] && $can_ins ) || ( 'current' === $row['state'] && self::can_manage( $row['type'] ) ) ) : ?>
									<input type="checkbox" name="flxu_slugs[]" value="<?php echo esc_attr( $row['slug'] ); ?>" class="flxu-check" data-state="<?php echo esc_attr( $row['state'] ); ?>" aria-label="<?php echo esc_attr( $row['name'] ); ?>">
								<?php endif; ?>
							</th>
							<td class="flxu-pkg">
								<?php
								$icon = FLXU_Updater::icons( $row['slug'] );
								if ( empty( $icon['svg'] ) && 'theme' === $row['type'] && wp_get_theme( $row['slug'] )->exists() ) {
									$shot = wp_get_theme( $row['slug'] )->get_screenshot();
									if ( $shot ) {
										$icon['svg'] = $shot;
									}
								}
								?>
								<?php if ( ! empty( $icon['svg'] ) ) : ?>
									<img src="<?php echo esc_url( $icon['svg'] ); ?>" width="40" height="40" alt="">
								<?php else : ?>
									<span class="flxu-pkg__ph dashicons <?php echo 'theme' === $row['type'] ? 'dashicons-admin-appearance' : 'dashicons-admin-plugins'; ?>"></span>
								<?php endif; ?>
								<span><strong><?php echo esc_html( $row['name'] ); ?></strong><br><code><?php echo esc_html( $row['slug'] ); ?></code>
									<?php
									$src_n = count( (array) ( $catalog[ $row['slug'] ]['sources'] ?? array() ) );
									if ( 'main' !== ( $catalog[ $row['slug'] ]['source'] ?? 'main' ) || $src_n > 1 ) :
										?>
										<small class="flxu-src">
										<?php
										/* translators: %s: source name */
										echo esc_html( sprintf( __( 'Kaynak: %s', 'flx-setup' ), $catalog[ $row['slug'] ]['source_name'] ?? '' ) );
										if ( $src_n > 1 ) {
											/* translators: %d: number of sources */
											echo ' · ' . esc_html( sprintf( __( '%d kaynakta var', 'flx-setup' ), $src_n ) );
										}
										?>
										</small>
									<?php endif; ?>
									<?php if ( $req ) : ?>
										<br><small class="flxu-req"><?php esc_html_e( 'Gerekli:', 'flx-setup' ); ?>
										<?php
										$installed_pl = FLXU_Installer::installed_plugins();
										foreach ( $req as $i => $d ) {
											$on = ! empty( $installed_pl[ $d ]['active'] );
											echo ( $i ? ', ' : ' ' ) . '<span class="flxu-req__item ' . ( $on ? 'is-ok' : 'is-missing' ) . '" data-dep="' . esc_attr( $d ) . '" title="' . esc_attr( $on ? __( 'Etkin', 'flx-setup' ) : ( isset( $installed_pl[ $d ] ) ? __( 'Kurulu, etkin değil', 'flx-setup' ) : __( 'Kurulu değil', 'flx-setup' ) ) ) . '">' . esc_html( FLXU_Installer::plugin_name( $d, $catalog, $installed_pl ) ) . ( $on ? ' ✓' : '' ) . '</span>';
										}
										?>
										</small>
									<?php endif; ?>
								</span>
							</td>
							<td class="flxu-c-type"><?php echo 'theme' === $row['type'] ? esc_html__( 'Tema', 'flx-setup' ) : esc_html__( 'Eklenti', 'flx-setup' ); ?></td>
							<td class="flxu-c-inst"><?php echo $row['installed'] ? esc_html( $row['installed'] ) : '—'; ?>
								<?php if ( $row['installed'] && ! $row['active'] && 'plugin' === $row['type'] ) : ?>
									<br><small class="flxu-off"><?php esc_html_e( 'Devre dışı', 'flx-setup' ); ?></small>
								<?php endif; ?>
							</td>
							<td class="flxu-c-latest"><?php echo esc_html( $row['latest'] ); ?>
								<?php if ( $row['released'] ) : ?>
									<br><small><?php echo esc_html( flx_date( strtotime( $row['released'] ) ) ); ?></small>
								<?php endif; ?>
							</td>
							<td class="flxu-actions">
								<div class="flxu-run" hidden><span class="flxu-run__spin"></span><span class="flxu-run__text"></span><span class="flxu-run__bar"><span></span></span></div>
								<div class="flxu-done" hidden></div>
								<div class="flxu-idle">
								<?php if ( 'update' === $row['state'] ) : ?>
									<span class="flxu-badge flxu-badge--update"><?php esc_html_e( 'Güncelleme var', 'flx-setup' ); ?></span>
									<?php if ( $can ) : ?>
										<button type="submit" class="button button-small" name="flxu_do" value="one" data-slug="<?php echo esc_attr( $row['slug'] ); ?>"><?php esc_html_e( 'Güncelle', 'flx-setup' ); ?></button>
									<?php endif; ?>
								<?php elseif ( 'current' === $row['state'] ) : ?>
									<span class="flxu-badge flxu-badge--ok"><?php esc_html_e( 'Güncel', 'flx-setup' ); ?></span>
								<?php else : ?>
									<span class="flxu-badge"><?php esc_html_e( 'Kurulu değil', 'flx-setup' ); ?></span>
									<?php if ( $can_ins ) : ?>
										<button type="submit" class="button button-small" name="flxu_do" value="one" data-slug="<?php echo esc_attr( $row['slug'] ); ?>"><?php esc_html_e( 'Kur', 'flx-setup' ); ?></button>
									<?php endif; ?>
								<?php endif; ?>
								<?php if ( $row['installed'] && $versions && $can ) : ?>
									<details class="flxu-ver">
										<summary><?php esc_html_e( 'Sürüm değiştir', 'flx-setup' ); ?></summary>
										<div class="flxu-ver__box">
											<select aria-label="<?php esc_attr_e( 'Sürüm', 'flx-setup' ); ?>">
												<?php foreach ( $versions as $v ) : ?>
													<option value="<?php echo esc_attr( $v['version'] ); ?>">
														<?php
														echo esc_html( $v['version'] );
														if ( $v['released'] ) {
															echo ' · ' . esc_html( flx_date( strtotime( $v['released'] ) ) );
														}
														if ( version_compare( $v['version'], $row['installed'], '<' ) ) {
															echo ' · ' . esc_html__( 'eski', 'flx-setup' );
														}
														?>
													</option>
												<?php endforeach; ?>
											</select>
											<button type="submit" class="button button-small" name="flxu_do" value="version" data-slug="<?php echo esc_attr( $row['slug'] ); ?>" data-name="<?php echo esc_attr( $row['name'] ); ?>"><?php esc_html_e( 'Bu sürümü kur', 'flx-setup' ); ?></button>
											<p class="description"><?php echo FLXU_Guard::backup_ready() ? esc_html__( 'Önce yedek alınır.', 'flx-setup' ) : esc_html__( 'Flux Backup kurulu değil: yedek alınmaz.', 'flx-setup' ); ?></p>
										</div>
									</details>
								<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</form>

			<p><a class="button-link" href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>"><?php esc_html_e( 'WordPress güncellemeleri', 'flx-setup' ); ?></a></p>

			<?php self::backup_strip( $catalog ); ?>

			<div id="flxu-history-wrap"><?php self::history_table(); ?></div>
		</div>
		<?php
		self::page_assets();
	}

	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$msg = isset( $_GET['flxu_msg'] ) ? sanitize_key( wp_unslash( $_GET['flxu_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap flxu-wrap">
			<h1><?php esc_html_e( 'Güncelleme ayarları', 'flx-setup' ); ?></h1>
			<p class="description"><?php esc_html_e( 'FluxWP tema ve eklentileri wordpress.org yerine kendi güncelleme sunucunuzdan güncellenir.', 'flx-setup' ); ?></p>
			<?php if ( 'saved' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ayarlar kaydedildi ve kontrol yapıldı.', 'flx-setup' ); ?></p></div>
			<?php endif; ?>
			<?php self::status_card( FLXU_Updater::status(), FLXU_Updater::instance()->catalog() ); ?>
			<?php self::connection_form( FLXU_Updater::settings() ); ?>
			<?php self::sources_section( $msg ); ?>
			<?php do_action( 'flxu_settings_after' ); ?>
		</div>
		<?php
		self::page_assets();
	}

	/** Ayarlar › Diğer kaynaklar: public GitHub depoları ve başka güncelleme sunucuları. */
	private static function sources_section( string $msg ): void {
		$extra  = FLXU_Sources::extra();
		$form   = static function ( string $op, string $id, string $label, string $class = 'button-link' ): void {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxu-inline">
				<input type="hidden" name="action" value="flxu_source">
				<input type="hidden" name="op" value="<?php echo esc_attr( $op ); ?>">
				<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
				<?php wp_nonce_field( 'flxu_source' ); ?>
				<button type="submit" class="<?php echo esc_attr( $class ); ?>"<?php echo 'remove' === $op ? ' data-confirm="' . esc_attr__( 'Bu kaynak silinsin mi? Ondan kurulan paketler sitede kalır, yalnız güncelleme gelmez.', 'flx-setup' ) . '"' : ''; ?>><?php echo esc_html( $label ); ?></button>
			</form>
			<?php
		};
		$state_cell = static function ( array $s ): string {
			if ( empty( $s['enabled'] ) ) {
				return '<span class="flxu-src-st is-off">' . esc_html__( 'Kapalı', 'flx-setup' ) . '</span>';
			}
			$st = FLXU_Sources::status( $s['id'] );
			if ( empty( $st['checked'] ) ) {
				return '<span class="flxu-src-st">' . esc_html__( 'Henüz kontrol edilmedi', 'flx-setup' ) . '</span>';
			}
			if ( empty( $st['ok'] ) ) {
				return '<span class="flxu-src-st is-err">' . esc_html( FLXU_Updater::status_message( $st, '' !== (string) ( $s['key'] ?? '' ), FLXU_Sources::host( $s ) ) ) . '</span>';
			}
			/* translators: %d: package count */
			return '<span class="flxu-src-st is-ok">' . esc_html( sprintf( _n( 'Bağlı · %d paket', 'Bağlı · %d paket', (int) $st['count'], 'flx-setup' ), (int) $st['count'] ) ) . '</span>';
		};
		$main = FLXU_Sources::main();
		?>
		<h2 id="flxu-sources"><?php esc_html_e( 'Diğer kaynaklar', 'flx-setup' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Paketler ana sunucuyla birlikte bu kaynaklardan da toplanır. Aynı paket birden çok kaynakta varsa kurulu olduğu kaynaktan güncellenir; kurulu değilse listede üstteki kaynak kazanır.', 'flx-setup' ); ?></p>
		<?php if ( 'src_error' === $msg ) : ?>
			<?php $err = (string) get_transient( 'flxu_src_err_' . get_current_user_id() ); ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( sprintf( /* translators: %s: error */ __( 'Kaynak eklenmedi: %s', 'flx-setup' ), $err ) ); ?></p></div>
		<?php elseif ( 'src_add' === $msg ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Kaynak eklendi ve paketleri alındı.', 'flx-setup' ); ?></p></div>
		<?php elseif ( 'src_remove' === $msg ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Kaynak silindi.', 'flx-setup' ); ?></p></div>
		<?php endif; ?>
		<table class="widefat striped flxu-sources">
			<thead><tr>
				<th><?php esc_html_e( 'Ad', 'flx-setup' ); ?></th>
				<th><?php esc_html_e( 'Adres', 'flx-setup' ); ?></th>
				<th><?php esc_html_e( 'Tür', 'flx-setup' ); ?></th>
				<th><?php esc_html_e( 'Durum', 'flx-setup' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
				<tr class="is-main">
					<td><strong><?php echo esc_html( $main['name'] ); ?></strong><br><small><?php esc_html_e( 'Ana sunucu', 'flx-setup' ); ?></small></td>
					<td class="flxu-src-url"><code><?php echo esc_html( $main['url'] ); ?></code></td>
					<td class="flxu-src-type"><?php esc_html_e( 'Sunucu', 'flx-setup' ); ?></td>
					<td><?php echo $state_cell( $main ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td class="flxu-src-act"><small><?php esc_html_e( 'Yukarıdaki Bağlantı ayarı', 'flx-setup' ); ?></small></td>
				</tr>
				<?php foreach ( $extra as $i => $s ) : ?>
					<tr class="<?php echo empty( $s['enabled'] ) ? 'is-off' : ''; ?>">
						<td><strong><?php echo esc_html( $s['name'] ); ?></strong>
							<?php if ( 'server' === $s['type'] && '' !== $s['key'] ) : ?><br><small><?php esc_html_e( 'Anahtar kayıtlı', 'flx-setup' ); ?></small><?php endif; ?>
						</td>
						<td class="flxu-src-url"><a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $s['url'] ); ?></code></a></td>
						<td class="flxu-src-type"><?php echo 'github' === $s['type'] ? esc_html__( 'GitHub (public)', 'flx-setup' ) : esc_html__( 'Sunucu', 'flx-setup' ); ?></td>
						<td><?php echo $state_cell( $s ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td class="flxu-src-act">
							<?php
							if ( $i > 0 ) {
								$form( 'up', $s['id'], __( 'Yukarı', 'flx-setup' ) );
							}
							$form( 'toggle', $s['id'], empty( $s['enabled'] ) ? __( 'Kaynağı aç', 'flx-setup' ) : __( 'Kapat', 'flx-setup' ) );
							$form( 'remove', $s['id'], __( 'Sil', 'flx-setup' ), 'button-link flxu-src-del flxu-confirm' );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Kaynak ekle', 'flx-setup' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxu-src-add">
			<input type="hidden" name="action" value="flxu_source">
			<input type="hidden" name="op" value="add">
			<?php wp_nonce_field( 'flxu_source' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="flxu-src-url"><?php esc_html_e( 'Adres', 'flx-setup' ); ?></label></th>
					<td><input type="url" id="flxu-src-url" name="url" class="regular-text" required placeholder="https://github.com/owner/repo">
						<p class="description"><?php esc_html_e( 'Public GitHub deposu (release\'lerinde <slug>.zip olan) ya da FluxWP güncelleme sunucusu adresi. Yalnız https.', 'flx-setup' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="flxu-src-name"><?php esc_html_e( 'Ad', 'flx-setup' ); ?></label></th>
					<td><input type="text" id="flxu-src-name" name="name" class="regular-text" placeholder="<?php esc_attr_e( 'Boşsa adresten alınır', 'flx-setup' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="flxu-src-key"><?php esc_html_e( 'Site anahtarı', 'flx-setup' ); ?></label></th>
					<td><input type="password" id="flxu-src-key" name="key" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'İsteğe bağlı', 'flx-setup' ); ?>">
						<p class="description"><?php esc_html_e( 'Yalnız anahtar isteyen sunucularda. Anahtar sadece o sunucunun adresine gönderilir.', 'flx-setup' ); ?></p></td>
				</tr>
			</table>
			<p class="flxu-src-warn"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Bu kaynaktan gelen kod sitende çalışır; yalnız güvendiğin kaynağı ekle.', 'flx-setup' ); ?></p>
			<?php submit_button( __( 'Test et ve ekle', 'flx-setup' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/** Hata veren ek kaynaklar: paketleri son bilinen veriden gösterilir. */
	public static function failing_sources(): void {
		foreach ( FLXU_Sources::failing() as $f ) {
			echo '<div class="flxu-strip flxu-strip--err"><span class="dashicons dashicons-warning"></span><span>';
			/* translators: 1: source name, 2: error */
			echo esc_html( sprintf( __( '%1$s kaynağına ulaşılamadı: %2$s Bu kaynağın paketleri son bilinen veriden gösteriliyor.', 'flx-setup' ), $f['name'], rtrim( $f['error'], '.' ) . '.' ) );
			if ( current_user_can( 'manage_options' ) ) {
				echo ' <a href="' . esc_url( self::url( array(), self::SETTINGS_SLUG ) . '#flxu-sources' ) . '">' . esc_html__( 'Kaynaklar', 'flx-setup' ) . '</a>';
			}
			echo '</span></div>';
		}
	}

	/** Flux Backup durumu. */
	private static function backup_strip( array $catalog ): void {
		if ( FLXU_Guard::backup_ready() ) {
			$url = admin_url( 'admin.php?page=flx-backup' );
			echo '<div class="flxu-strip flxu-strip--ok"><span class="dashicons dashicons-shield"></span><span>' . esc_html__( 'Her güncellemeden önce yedek alınır (Flux Backup).', 'flx-setup' ) . '</span> <a href="' . esc_url( $url ) . '">' . esc_html__( 'Yedekler', 'flx-setup' ) . '</a></div>';
			return;
		}
		$files = FLXU_Updater::plugin_files();
		echo '<div class="flxu-strip flxu-strip--warn"><span class="dashicons dashicons-warning"></span><span>' . esc_html__( 'Güncelleme öncesi yedek alınmıyor: Flux Backup eklentisi kurulu ya da etkin değil.', 'flx-setup' ) . '</span>';
		if ( isset( $files['flx-backup'] ) && current_user_can( 'activate_plugins' ) ) {
			echo ' <a href="' . esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $files['flx-backup'] ) ), 'activate-plugin_' . $files['flx-backup'] ) ) . '">' . esc_html__( 'Etkinleştir', 'flx-setup' ) . '</a>';
		} elseif ( isset( $catalog['flx-backup'] ) && current_user_can( 'install_plugins' ) ) {
			echo ' <a href="' . esc_url( wp_nonce_url( self::url( array( 'flxu_install' => 'flx-backup' ) ), 'flxu_install_flx-backup' ) ) . '">' . esc_html__( 'Flux Backup\'i kur', 'flx-setup' ) . '</a>';
		}
		echo '</div>';
	}

	const HISTORY_PER_PAGE = 20;

	/** Flux Backup'in güncelleme yedeği yönetimi için gereken API'si var mı. */
	private static function backup_api(): bool {
		return function_exists( 'flxy_backup_get' ) && function_exists( 'flxy_backup_delete' );
	}

	/** Güncelleme yedeklerini sil / saklama süresini değiştir (Geçmiş bölümünden). */
	/** Kurulu paket için toplu işlem (etkinleştir/devre dışı/kaldır) yetkisi. */
	private static function can_manage( string $type ): bool {
		return 'theme' === $type ? current_user_can( 'delete_themes' ) : ( current_user_can( 'activate_plugins' ) || current_user_can( 'delete_plugins' ) );
	}

	/**
	 * Toplu işlem: seçilen kurulu paketleri etkinleştir, devre dışı bırak ya da kaldır.
	 * FluxWP Panel kendini kapatmaz/silmez; etkin tema (ve üst teması) silinmez; temalar
	 * etkinleştirilip kapatılmaz (Görünüm › Temalar'dan değiştirilir).
	 */
	public function manage_action(): void {
		check_admin_referer( 'flxu_bulk' );
		$op = sanitize_key( wp_unslash( $_POST['flxu_op'] ?? '' ) );
		if ( ! in_array( $op, array( 'activate', 'deactivate', 'delete' ), true ) ) {
			wp_safe_redirect( self::url() );
			exit;
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$catalog = FLXU_Updater::instance()->catalog();
		$files   = FLXU_Updater::plugin_files();
		$slugs   = array_unique( array_map( 'sanitize_key', (array) wp_unslash( $_POST['flxu_slugs'] ?? array() ) ) );
		$done    = 0;
		$skip    = array();
		$fail    = array();
		$del_pl  = array();
		foreach ( $slugs as $slug ) {
			$p    = $catalog[ $slug ] ?? null;
			$name = $p['name'] ?? $slug;
			if ( ! $p ) {
				continue;
			}
			if ( 'theme' === $p['type'] ) {
				$theme = wp_get_theme( $slug );
				if ( 'delete' !== $op || ! $theme->exists() || ! current_user_can( 'delete_themes' ) || in_array( $slug, array( get_stylesheet(), get_template() ), true ) ) {
					$skip[] = $name;
					continue;
				}
				$r = delete_theme( $slug );
				if ( true === $r ) {
					++$done;
				} else {
					$fail[] = $name;
				}
				continue;
			}
			$file = $files[ $slug ] ?? '';
			if ( '' === $file || ( plugin_basename( FLXU_FILE ) === $file && 'activate' !== $op ) ) {
				$skip[] = $name;
				continue;
			}
			if ( 'activate' === $op ) {
				if ( ! current_user_can( 'activate_plugins' ) || is_plugin_active( $file ) ) {
					$skip[] = $name;
					continue;
				}
				$r = activate_plugin( $file );
				if ( is_wp_error( $r ) ) {
					$fail[] = $name;
				} else {
					++$done;
				}
			} elseif ( 'deactivate' === $op ) {
				if ( ! current_user_can( 'activate_plugins' ) || ! is_plugin_active( $file ) ) {
					$skip[] = $name;
					continue;
				}
				deactivate_plugins( $file );
				++$done;
			} else {
				if ( ! current_user_can( 'delete_plugins' ) ) {
					$skip[] = $name;
					continue;
				}
				$del_pl[ $file ] = $name;
			}
		}
		if ( $del_pl ) {
			// Önce kapat, sonra sil (WordPress etkin eklentiyi silmez).
			deactivate_plugins( array_keys( $del_pl ), true );
			$r = delete_plugins( array_keys( $del_pl ) );
			if ( true === $r ) {
				$done += count( $del_pl );
			} else {
				$fail = array_merge( $fail, array_values( $del_pl ) );
			}
		}
		set_transient(
			'flxu_manage_' . get_current_user_id(),
			array(
				'op'   => $op,
				'done' => $done,
				'skip' => $skip,
				'fail' => $fail,
			),
			300
		);
		wp_safe_redirect( add_query_arg( 'flxu_msg', 'managed', self::url() ) );
		exit;
	}

	public function backups_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-setup' ), 403 );
		}
		check_admin_referer( 'flxu_backups' );
		$back = self::url( array( 'hp' => max( 1, absint( $_POST['hp'] ?? 1 ) ) ) );
		$do   = sanitize_key( wp_unslash( $_POST['flxu_bk'] ?? '' ) );

		if ( 'keep' === $do && function_exists( 'flxy_set_pre_update_keep_days' ) ) {
			$res = flxy_set_pre_update_keep_days( max( 1, min( 365, absint( $_POST['keep_days'] ?? 7 ) ) ) );
			wp_safe_redirect( add_query_arg( array( 'flxu_msg' => 'keep_saved', 'flxu_n' => (int) $res['deleted'] ), $back ) . '#flxu-history' );
			exit;
		}

		$n = 0;
		if ( self::backup_api() ) {
			$ids = array();
			if ( 'one' === $do ) {
				$ids = array( sanitize_text_field( wp_unslash( $_POST['flxu_id'] ?? '' ) ) );
			} elseif ( 'selected' === $do ) {
				$ids = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['flxu_ids'] ?? array() ) );
			} elseif ( 'all' === $do ) {
				foreach ( self::update_backups() as $r ) {
					$ids[] = $r['id'];
				}
			}
			foreach ( array_unique( array_filter( $ids ) ) as $id ) {
				$r = flxy_backup_get( $id );
				// Yalnız güncelleme öncesi yedekler; kilitli olanlar silinmez.
				if ( $r && 'pre_update' === $r['type'] && empty( $r['locked'] ) && flxy_backup_delete( $id ) ) {
					++$n;
				}
			}
		}
		wp_safe_redirect( add_query_arg( array( 'flxu_msg' => 'bk_deleted', 'flxu_n' => $n ), $back ) . '#flxu-history' );
		exit;
	}

	/** Silinebilir (kilitsiz, bitmiş) güncelleme öncesi yedekler. */
	private static function update_backups(): array {
		if ( ! function_exists( 'flxy_backup_list' ) ) {
			return array();
		}
		return array_values( array_filter( flxy_backup_list( array( 'type' => 'pre_update' ) ), static function ( $r ) {
			return empty( $r['locked'] ) && 'running' !== $r['status'];
		} ) );
	}

	/** Saklama süresi + toplam boyut + "tümünü sil". */
	private static function retention_bar(): void {
		if ( ! self::backup_api() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$list  = self::update_backups();
		$total = 0;
		foreach ( $list as $r ) {
			$total += (int) ( $r['size'] ?? 0 );
		}
		$days    = function_exists( 'flxy_pre_update_keep_days' ) ? flxy_pre_update_keep_days() : 0;
		$choices = array( 1, 3, 7, 14, 30, 60, 90 );
		if ( $days && ! in_array( $days, $choices, true ) ) {
			$choices[] = $days;
			sort( $choices );
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxu-keep">
			<input type="hidden" name="action" value="flxu_backups">
			<input type="hidden" name="hp" value="<?php echo esc_attr( (string) self::history_page() ); ?>">
			<?php wp_nonce_field( 'flxu_backups' ); ?>
			<span class="dashicons dashicons-backup"></span>
			<span class="flxu-keep__text">
				<?php if ( $days ) : ?>
					<label for="flxu-keep-days"><?php esc_html_e( 'Güncelleme yedekleri', 'flx-setup' ); ?></label>
					<select id="flxu-keep-days" name="keep_days">
						<?php foreach ( $choices as $c ) : ?>
							<option value="<?php echo esc_attr( (string) $c ); ?>" <?php selected( $days, $c ); ?>><?php echo esc_html( sprintf( _n( '%d gün', '%d gün', $c, 'flx-setup' ), $c ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php esc_html_e( 'sonra otomatik silinir.', 'flx-setup' ); ?>
					<button type="submit" name="flxu_bk" value="keep" class="button button-small"><?php esc_html_e( 'Kaydet', 'flx-setup' ); ?></button>
				<?php else : ?>
					<?php
					/* translators: %s: Flux Backup ayarları bağlantısı */
					printf( esc_html__( 'Güncelleme yedeklerinin saklama süresi %s sayfasından değiştirilir (Flux Backup 1.2.0 ile buradan da değiştirilebilir).', 'flx-setup' ), '<a href="' . esc_url( admin_url( 'admin.php?page=flx-backup-settings' ) ) . '">' . esc_html__( 'Flux Backup ayarları', 'flx-setup' ) . '</a>' );
					?>
				<?php endif; ?>
				<small><?php echo esc_html( sprintf( _n( 'Şu an %1$d güncelleme yedeği, %2$s.', 'Şu an %1$d güncelleme yedeği, %2$s.', count( $list ), 'flx-setup' ), count( $list ), size_format( $total, 1 ) ) ); ?></small>
			</span>
			<?php if ( $list ) : ?>
				<button type="submit" name="flxu_bk" value="all" class="button button-link-delete flxu-confirm" data-confirm="<?php echo esc_attr__( 'Kilitli olmayan tüm güncelleme yedekleri silinsin mi? Bu geri alınamaz.', 'flx-setup' ); ?>"><?php esc_html_e( 'Tümünü sil', 'flx-setup' ); ?></button>
			<?php endif; ?>
		</form>
		<?php
	}

	private static function history_page(): int {
		return isset( $_GET['hp'] ) ? max( 1, absint( $_GET['hp'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
	}

	private static function history_table(): void {
		$all   = FLXU_Guard::history();
		$total = count( $all );
		$pages = max( 1, (int) ceil( $total / self::HISTORY_PER_PAGE ) );
		$page  = min( self::history_page(), $pages );
		$log   = array_slice( $all, ( $page - 1 ) * self::HISTORY_PER_PAGE, self::HISTORY_PER_PAGE );

		echo '<h2 id="flxu-history">' . esc_html__( 'Geçmiş', 'flx-setup' ) . '</h2>';
		self::retention_bar();
		if ( ! $log ) {
			echo '<p class="description">' . esc_html__( 'Henüz FluxWP paket güncellemesi yapılmadı.', 'flx-setup' ) . '</p>';
			return;
		}
		$api         = self::backup_api();
		$can_delete  = $api && current_user_can( 'manage_options' );
		$backup_page = FLXU_Guard::backup_ready() ? admin_url( 'admin.php?page=flx-backup-list' ) : '';
		$seen        = array();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="flxu-history-form">
			<input type="hidden" name="action" value="flxu_backups">
			<input type="hidden" name="hp" value="<?php echo esc_attr( (string) $page ); ?>">
			<input type="hidden" name="flxu_id" value="">
			<?php wp_nonce_field( 'flxu_backups' ); ?>
			<?php self::history_nav( $page, $pages, $total, $can_delete ); ?>
			<table class="widefat striped flxu-history">
				<thead><tr>
					<?php if ( $can_delete ) : ?><td class="check-column"><input type="checkbox" id="flxu-hcheck-all" aria-label="<?php esc_attr_e( 'Tümünü seç', 'flx-setup' ); ?>"></td><?php endif; ?>
					<th><?php esc_html_e( 'Tarih', 'flx-setup' ); ?></th>
					<th><?php esc_html_e( 'Paket', 'flx-setup' ); ?></th>
					<th><?php esc_html_e( 'İşlem', 'flx-setup' ); ?></th>
					<th><?php esc_html_e( 'Sonuç', 'flx-setup' ); ?></th>
					<th><?php esc_html_e( 'Kim', 'flx-setup' ); ?></th>
					<th><?php esc_html_e( 'Yedek', 'flx-setup' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $log as $e ) : ?>
					<?php
					$bid = (string) ( $e['backup'] ?? '' );
					$rec = ( $bid && $api ) ? flxy_backup_get( $bid ) : null;
					// Toplu güncellemede birden çok satır aynı yedeği paylaşır: onay kutusu yalnız ilkinde.
					$first = $rec && ! isset( $seen[ $bid ] );
					if ( $rec ) {
						$seen[ $bid ] = true;
					}
					$deletable = $can_delete && $rec && 'pre_update' === $rec['type'] && empty( $rec['locked'] ) && 'running' !== $rec['status'];
					?>
					<tr>
						<?php if ( $can_delete ) : ?>
							<th scope="row" class="check-column"><?php if ( $deletable && $first ) : ?><input type="checkbox" class="flxu-hcheck" name="flxu_ids[]" value="<?php echo esc_attr( $bid ); ?>"><?php endif; ?></th>
						<?php endif; ?>
						<td><?php echo esc_html( flx_date( (int) $e['time'], 'datetime' ) ); ?></td>
						<td><strong><?php echo esc_html( $e['name'] ); ?></strong></td>
						<td><?php echo esc_html( self::action_label( $e ) ); ?></td>
						<td>
							<span class="flxu-badge <?php echo 'ok' === $e['result'] ? 'flxu-badge--ok' : 'flxu-badge--err'; ?>"><?php echo 'ok' === $e['result'] ? esc_html__( 'Başarılı', 'flx-setup' ) : esc_html__( 'Hata', 'flx-setup' ); ?></span>
							<?php if ( $e['message'] && ( 'ok' !== $e['result'] || 'auto_rollback' === $e['action'] ) ) : ?><br><small><?php echo esc_html( $e['message'] ); ?></small><?php endif; ?>
						</td>
						<td><?php echo $e['auto'] ? esc_html__( 'Otomatik', 'flx-setup' ) : esc_html( $e['user'] ); ?></td>
						<td class="flxu-bk">
							<?php if ( ! $bid ) : ?>
								—
							<?php elseif ( $api && ! $rec ) : ?>
								<span class="flxu-muted"><?php esc_html_e( 'Silindi', 'flx-setup' ); ?></span>
							<?php elseif ( $rec ) : ?>
								<span class="flxu-bk__size"><?php echo esc_html( size_format( (int) ( $rec['size'] ?? 0 ), 1 ) ); ?></span>
								<?php if ( ! empty( $rec['locked'] ) ) : ?>
									<span class="flxu-badge" title="<?php esc_attr_e( 'Kilitli yedekler otomatik silinmez.', 'flx-setup' ); ?>"><?php esc_html_e( 'Kilitli', 'flx-setup' ); ?></span>
								<?php elseif ( ! empty( $rec['keep_until'] ) ) : ?>
									<small class="flxu-muted"><?php echo esc_html( sprintf( __( '%s silinir', 'flx-setup' ), flx_date( (int) $rec['keep_until'] ) ) ); ?></small>
								<?php endif; ?>
								<br>
								<?php if ( $backup_page ) : ?><a href="<?php echo esc_url( add_query_arg( 'view', $bid, $backup_page ) ); ?>"><?php esc_html_e( 'Aç', 'flx-setup' ); ?></a><?php endif; ?>
								<?php if ( $deletable ) : ?>
									<?php if ( $backup_page ) : ?> · <?php endif; ?>
									<button type="submit" name="flxu_bk" value="one" class="button-link button-link-delete flxu-del" data-id="<?php echo esc_attr( $bid ); ?>"><?php esc_html_e( 'Sil', 'flx-setup' ); ?></button>
								<?php endif; ?>
							<?php elseif ( $backup_page ) : ?>
								<a href="<?php echo esc_url( add_query_arg( 'id', $bid, $backup_page ) ); ?>"><?php esc_html_e( 'Yedeği aç', 'flx-setup' ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php self::history_nav( $page, $pages, $total, false ); ?>
		</form>
		<?php
	}

	/** Sayfalama (WP list table görünümünde) + toplu silme. */
	private static function history_nav( int $page, int $pages, int $total, bool $bulk ): void {
		$link = static function ( int $p ): string {
			return self::url( array( 'hp' => $p ) ) . '#flxu-history';
		};
		?>
		<div class="tablenav">
			<?php if ( $bulk ) : ?>
				<div class="alignleft actions">
					<button type="submit" name="flxu_bk" value="selected" class="button flxu-confirm" id="flxu-hdel" disabled data-confirm="<?php echo esc_attr__( 'Seçilen güncelleme yedekleri silinsin mi? Bu geri alınamaz.', 'flx-setup' ); ?>"><?php esc_html_e( 'Seçili yedekleri sil', 'flx-setup' ); ?></button>
				</div>
			<?php endif; ?>
			<div class="tablenav-pages<?php echo $pages < 2 ? ' one-page' : ''; ?>">
				<span class="displaying-num"><?php echo esc_html( sprintf( _n( '%s kayıt', '%s kayıt', $total, 'flx-setup' ), number_format_i18n( $total ) ) ); ?></span>
				<?php if ( $pages > 1 ) : ?>
					<span class="pagination-links">
						<?php if ( $page > 1 ) : ?>
							<a class="first-page button" href="<?php echo esc_url( $link( 1 ) ); ?>"><span aria-hidden="true">«</span><span class="screen-reader-text"><?php esc_html_e( 'İlk sayfa', 'flx-setup' ); ?></span></a>
							<a class="prev-page button" href="<?php echo esc_url( $link( $page - 1 ) ); ?>"><span aria-hidden="true">‹</span><span class="screen-reader-text"><?php esc_html_e( 'Önceki sayfa', 'flx-setup' ); ?></span></a>
						<?php else : ?>
							<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
							<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
						<?php endif; ?>
						<span class="paging-input"><?php echo esc_html( sprintf( __( '%1$d / %2$d', 'flx-setup' ), $page, $pages ) ); ?></span>
						<?php if ( $page < $pages ) : ?>
							<a class="next-page button" href="<?php echo esc_url( $link( $page + 1 ) ); ?>"><span aria-hidden="true">›</span><span class="screen-reader-text"><?php esc_html_e( 'Sonraki sayfa', 'flx-setup' ); ?></span></a>
							<a class="last-page button" href="<?php echo esc_url( $link( $pages ) ); ?>"><span aria-hidden="true">»</span><span class="screen-reader-text"><?php esc_html_e( 'Son sayfa', 'flx-setup' ); ?></span></a>
						<?php else : ?>
							<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
							<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
						<?php endif; ?>
					</span>
				<?php endif; ?>
			</div>
			<br class="clear">
		</div>
		<?php
	}

	private static function page_assets(): void {
		?>
		<style>
			.flxu-wrap .flxu-card{max-width:none}
			.flxu-toolbar{display:flex;align-items:center;gap:8px;margin:20px 0 10px;flex-wrap:wrap}
			.flxu-toolbar h2{margin:0}.flxu-toolbar__spacer{flex:1}
			#flxu-check{display:inline-flex;align-items:center;gap:6px}
			.flxu-check__spin{display:none;width:12px;height:12px;border-radius:50%;border:2px solid var(--flxa-border-strong, #c3c4c7);border-top-color:#2271b1;animation:flxu-spin .8s linear infinite}
			#flxu-check.is-busy{pointer-events:none;opacity:.8}#flxu-check.is-busy .flxu-check__spin{display:inline-block}
			.flxu-checked{color:var(--flxa-muted, #646970);margin-left:8px;font-size:13px;vertical-align:middle}.flxu-checked.is-ok{color:color-mix(in srgb, #1e8e3e 80%, var(--flxa-text, #000))}.flxu-checked.is-err{color:var(--flxa-error, #d63638)}
			.flxu-table td,.flxu-table th{vertical-align:middle}
			.flxu-table .check-column{width:2.2em;padding-left:10px}
			.flxu-pkg{display:flex;align-items:center;gap:12px}
			.flxu-pkg img,.flxu-pkg__ph{width:40px;height:40px;border-radius:9px;flex:none;object-fit:cover}
			.flxu-pkg__ph{display:inline-flex;align-items:center;justify-content:center;background:var(--flxa-surface-2, #f0f0f1);color:var(--flxa-muted, #646970);font-size:22px}
			.flxu-row--update{box-shadow:inset 3px 0 0 #dba617}
			.flxu-actions{white-space:nowrap}
			.flxu-ver{display:inline-block;margin-left:8px;position:relative}
			.flxu-ver summary{cursor:pointer;color:var(--flxa-info, #2271b1);font-size:12px;list-style:none;display:inline}
			.flxu-ver summary::-webkit-details-marker{display:none}
			.flxu-ver summary::after{content:" ▾"}
			.flxu-ver__box{position:absolute;right:0;top:22px;z-index:20;background:var(--flxa-surface, #fff);border:1px solid var(--flxa-border, #dcdcde);border-radius:6px;box-shadow:0 6px 18px rgba(0,0,0,.12);padding:10px;display:flex;gap:6px;flex-wrap:wrap;min-width:330px;white-space:normal}
			.flxu-ver__box select{flex:1;min-width:150px}.flxu-ver__box .description{margin:2px 0 0;width:100%;font-size:12px}
			.flxu-strip{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:4px;margin:12px 0}
			.flxu-strip--ok{background:color-mix(in srgb, #1e8e3e 12%, var(--flxa-surface, #fff));color:color-mix(in srgb, #1e8e3e 70%, var(--flxa-text, #000))}.flxu-strip--warn{background:var(--flxa-warn-soft, #fcf9e8);color:var(--flxa-warn, #7a5a00)}.flxu-strip--err{background:var(--flxa-error-soft, #fcf0f1);color:var(--flxa-error, #8a2424)}
			.flxu-src{display:inline-block;margin-top:2px;color:var(--flxa-muted, #646970)}
			.flxu-sources{max-width:960px;margin-top:8px}.flxu-sources td{vertical-align:middle}.flxu-sources tr.is-off td{opacity:.6}
			.flxu-src-act{white-space:nowrap;text-align:right}.flxu-inline{display:inline;margin-left:10px}.flxu-src-del{color:var(--flxa-error, #b32d2e)}
			.flxu-src-st.is-ok{color:color-mix(in srgb, #1e8e3e 70%, var(--flxa-text, #000))}.flxu-src-st.is-ok:before{content:"";display:inline-block;width:8px;height:8px;border-radius:50%;background:#1e8e3e;margin-right:6px}
			.flxu-src-st.is-err{color:var(--flxa-error, #b32d2e)}.flxu-src-st.is-off{color:var(--flxa-muted, #646970)}
			.flxu-src-warn{display:flex;align-items:center;gap:6px;color:var(--flxa-warn, #7a5a00);background:var(--flxa-warn-soft, #fcf9e8);padding:8px 12px;border-radius:4px;max-width:760px}
			.flxu-history td,.flxu-history th{vertical-align:top}
			.flxu-history .check-column{width:2.2em;padding-left:10px}
			.flxu-bk__size{font-weight:600}.flxu-muted{color:var(--flxa-muted, #646970)}
			.flxu-bk .button-link{font-size:13px}
			.flxu-keep{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:var(--flxa-surface, #fff);border:1px solid var(--flxa-border, #dcdcde);border-radius:4px;padding:10px 14px;margin:8px 0 4px}
			.flxu-keep__text{flex:1;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
			.flxu-keep__text small{color:var(--flxa-muted, #646970);width:100%}
			.flxu-keep .dashicons{color:var(--flxa-info, #2271b1)}
			.flxu-card{display:flex;align-items:center;gap:14px;background:var(--flxa-surface, #fff);border:1px solid var(--flxa-border, #dcdcde);border-left-width:4px;border-radius:4px;padding:14px 16px;margin:16px 0}
			.flxu-card__body{flex:1;display:flex;flex-direction:column;gap:2px}
			.flxu-card__title{font-size:15px}
			.flxu-card small{color:var(--flxa-muted, #646970)}
			.flxu-dot{width:12px;height:12px;border-radius:50%;background:#a7aaad;flex:none}
			.flxu-card--connected{border-left-color:color-mix(in srgb, #1e8e3e 80%, var(--flxa-text, #000))}.flxu-card--connected .flxu-dot{background:#1e8e3e;box-shadow:0 0 0 4px color-mix(in srgb, #1e8e3e 12%, var(--flxa-surface, #fff))}
			.flxu-card--error{border-left-color:var(--flxa-error, #d63638)}.flxu-card--error .flxu-dot{background:#d63638;box-shadow:0 0 0 4px var(--flxa-error-soft, #fcf0f1)}
			.flxu-card--unknown,.flxu-card--paused{border-left-color:#dba617}.flxu-card--unknown .flxu-dot,.flxu-card--paused .flxu-dot{background:#dba617}
			.flxu-badge{display:inline-block;padding:2px 8px;border-radius:999px;background:var(--flxa-surface-2, #f0f0f1);color:var(--flxa-muted, #50575e);font-size:12px;margin-right:6px}
			.flxu-badge--ok{background:color-mix(in srgb, #1e8e3e 12%, var(--flxa-surface, #fff));color:color-mix(in srgb, #1e8e3e 70%, var(--flxa-text, #000))}
			.flxu-badge--update{background:#fcf0e3;color:#9a4d00}
			.flxu-badge--err{background:#fcf0f1;color:#a00}
			.flxu-run[hidden],.flxu-done[hidden],.flxu-idle[hidden],.flxu-deps[hidden],.flxu-log[hidden]{display:none!important}
			.flxu-act{display:inline-flex;align-items:center;gap:4px;color:var(--flxa-muted, #50575e)}
			.flxu-bulk{display:inline-flex;align-items:center;gap:4px;margin-left:8px;padding-left:12px;border-left:1px solid #dcdcde}.flxu-off{color:var(--flxa-error, #b32d2e)}
			.flxu-req{color:var(--flxa-muted, #646970)}.flxu-req__item.is-ok{color:color-mix(in srgb, #1e8e3e 70%, var(--flxa-text, #000))}.flxu-req__item.is-missing{color:var(--flxa-error, #b32d2e);font-weight:600}
			.flxu-deps{background:var(--flxa-surface, #fff);border:1px solid var(--flxa-border, #dcdcde);border-left:4px solid #dba617;border-radius:4px;padding:6px 14px;margin:0 0 10px}
			.flxu-deps p{margin:6px 0;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
			.flxu-deps__miss .dashicons{color:#dba617}.flxu-deps__extra .dashicons{color:var(--flxa-info, #2271b1)}
			.flxu-deps__note{color:#8a2424}
			.flxu-chip{display:inline-flex;align-items:center;gap:4px;background:#f0f6fc;border:1px solid #c5d9ed;border-radius:999px;padding:1px 4px 1px 10px}
			.flxu-chip small{color:var(--flxa-muted, #646970)}.flxu-chip__x{border:0;background:none;cursor:pointer;font-size:16px;line-height:1;color:var(--flxa-muted, #646970);padding:0 4px}
			.flxu-row--sel{background:color-mix(in srgb, var(--flxa-accent, #2271b1) 9%, var(--flxa-surface, #fff))!important}
			.flxu-row--busy{background:var(--flxa-warn-soft, #fffbea)!important}
			.flxu-row--queued{opacity:.75}
			.flxu-run{display:flex;align-items:center;gap:8px;min-width:200px}
			.flxu-run__spin,.flxu-log__spin{width:16px;height:16px;border-radius:50%;border:2px solid var(--flxa-border-strong, #c3c4c7);border-top-color:#2271b1;animation:flxu-spin .8s linear infinite;flex:none}
			.flxu-run__text{font-weight:600;color:#1d2327}
			.flxu-run__bar{flex:1;height:4px;border-radius:2px;background:#e0e0e0;overflow:hidden;position:relative;min-width:60px}
			.flxu-run__bar span{position:absolute;inset:0;width:40%;background:#2271b1;border-radius:2px;animation:flxu-slide 1.2s ease-in-out infinite}
			.flxu-res{display:inline-flex;align-items:center;gap:4px;font-weight:600}
			.flxu-res.is-ok{color:color-mix(in srgb, #1e8e3e 70%, var(--flxa-text, #000))}.flxu-res.is-err{color:var(--flxa-error, #b32d2e)}
			.flxu-done{margin-bottom:6px;animation:flxu-in .3s ease-out}
			.flxu-log{background:var(--flxa-surface, #fff);border:1px solid var(--flxa-border, #dcdcde);border-left:4px solid #2271b1;border-radius:4px;margin:14px 0;box-shadow:0 1px 2px rgba(0,0,0,.04)}
			.flxu-log.is-ok{border-left-color:color-mix(in srgb, #1e8e3e 80%, var(--flxa-text, #000))}.flxu-log.is-err{border-left-color:var(--flxa-error, #d63638)}
			.flxu-log__head{display:flex;align-items:center;gap:10px;padding:10px 14px}
			.flxu-log__spin{display:none}.flxu-log.is-busy .flxu-log__spin{display:block}
			.flxu-log__count{color:var(--flxa-muted, #646970)}
			.flxu-log__bar{height:3px;background:var(--flxa-surface-2, #f0f0f1)}
			.flxu-log__bar span{display:block;height:100%;width:0;background:#2271b1;transition:width .4s ease}
			.flxu-log.is-ok .flxu-log__bar span{background:#1e8e3e}.flxu-log.is-err .flxu-log__bar span{background:#d63638}
			.flxu-log__lines{margin:0;padding:8px 14px 12px;list-style:none;max-height:260px;overflow:auto;font-size:13px}
			.flxu-log__line{display:grid;grid-template-columns:auto 18px 1fr;gap:2px 8px;align-items:start;padding:3px 0;animation:flxu-in .25s ease-out}
			.flxu-log__line time{color:var(--flxa-muted, #646970);font-variant-numeric:tabular-nums}
			.flxu-log__ico{width:14px;height:14px;margin-top:2px;border-radius:50%}
			.flxu-log__line.is-run .flxu-log__ico{border:2px solid var(--flxa-border-strong, #c3c4c7);border-top-color:#2271b1;animation:flxu-spin .8s linear infinite;width:10px;height:10px}
			.flxu-log__line.is-ok .flxu-log__ico{background:#1e8e3e;box-shadow:inset 0 0 0 3px #e6f4ea}
			.flxu-log__line.is-err .flxu-log__ico{background:#d63638;box-shadow:inset 0 0 0 3px #fcf0f1}
			.flxu-log__sub{grid-column:3;font-size:12px}.flxu-log__sub.is-ok{color:var(--flxa-muted, #50575e)}.flxu-log__sub.is-err{color:var(--flxa-error, #b32d2e)}
			#flxu-packages.is-busy .flxu-check,#flxu-packages.is-busy button[name="flxu_do"],#flxu-packages.is-busy #flxu-check-all{pointer-events:none;opacity:.5}
			@keyframes flxu-spin{to{transform:rotate(360deg)}}
			@keyframes flxu-slide{0%{left:-40%}100%{left:100%}}
			@keyframes flxu-in{from{opacity:0;transform:translateY(-3px)}to{opacity:1;transform:none}}
.flxu-stay{position:fixed;z-index:100000;top:calc(var(--flxa-safe-top, var(--wp-admin--admin-bar--height, 32px)) + 10px);left:50%;transform:translateX(-50%);width:min(560px, calc(100vw - 32px));box-sizing:border-box;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:4px 12px;padding:12px 16px 14px;background:var(--flxa-text, #1d2327);color:var(--flxa-surface, #fff);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.25);overflow:hidden;animation:flxu-in .25s ease-out}
			.flxu-stay[hidden]{display:none!important}
			.flxu-stay__spin{width:18px;height:18px;border-radius:50%;border:2px solid color-mix(in srgb, currentColor 30%, transparent);border-top-color:currentColor;animation:flxu-spin .8s linear infinite}
			.flxu-stay__body{display:flex;flex-direction:column;min-width:0}
			.flxu-stay__title{font-size:14px;line-height:1.35}
			.flxu-stay__step{font-size:12px;opacity:.75;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.flxu-stay__step:empty{display:none}
			.flxu-stay__count{font-size:12px;opacity:.75;font-variant-numeric:tabular-nums}
			.flxu-stay__bar{position:absolute;left:0;right:0;bottom:0;height:3px;background:color-mix(in srgb, currentColor 15%, transparent)}
			.flxu-stay__bar span{display:block;height:100%;width:0;background:#4f94d4;transition:width .4s ease}
			.flxu-stay.is-ok .flxu-stay__spin,.flxu-stay.is-err .flxu-stay__spin{animation:none;border:0;background:#1e8e3e}.flxu-stay.is-err .flxu-stay__spin{background:#d63638}
			.flxu-stay.is-ok .flxu-stay__bar span{background:#1e8e3e}.flxu-stay.is-err .flxu-stay__bar span{background:#d63638}
						@media (prefers-reduced-motion:reduce){.flxu-run__spin,.flxu-log__spin,.flxu-stay__spin,.flxu-run__bar span,.flxu-log__line.is-run .flxu-log__ico{animation:none}}
			@media (max-width:782px){
				.flxu-card{flex-wrap:wrap}
				/* Araç çubuğu: başlık, iki ana düğme yan yana, toplu işlem tam satır */
				.flxu-toolbar{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:16px}
				.flxu-toolbar h2{grid-column:1/-1}.flxu-toolbar__spacer{display:none}
				#flxu-packages .flxu-toolbar .button{display:inline-flex;align-items:center;justify-content:center;min-height:40px;margin:0;padding:4px 10px;white-space:normal;line-height:1.25;text-align:center}
				.flxu-toolbar>.button{order:1}
				.flxu-bulk{order:2;grid-column:1/-1;margin:0;padding:0;border:0;display:flex;gap:8px}
				.flxu-bulk select{flex:1;min-width:0;max-width:none}
				.flxu-act{order:3;grid-column:1/-1;font-size:13px}
				/* Paket tablosu: telefonda her satır bir kart (logo | ad + sürüm | durum) */
				.flxu-table,.flxu-table tbody{display:block}
				.flxu-table thead{display:none}
				.flxu-table tr.flxu-row{display:grid;grid-template-columns:22px 36px auto auto 1fr auto;grid-template-rows:auto auto;align-items:center;column-gap:10px;row-gap:2px;padding:12px;border-top:1px solid var(--flxa-border,#dcdcde);position:relative}
				.flxu-table tbody tr:first-child{border-top:0}
				.flxu-table tr.flxu-row>*{display:block;padding:0;border:0!important;width:auto;min-width:0}
				.flxu-table .check-column{grid-column:1;grid-row:1/3;margin:0}
				.flxu-table .check-column input{margin:0}
				.flxu-table tr.flxu-row>td.flxu-pkg{display:contents}
				.flxu-pkg>img,.flxu-pkg>.flxu-pkg__ph{grid-column:2;grid-row:1/3;width:36px;height:36px;align-self:center}
				.flxu-pkg>span{grid-column:3/6;grid-row:1;min-width:0;align-self:end;line-height:1.35}
				.flxu-pkg code,.flxu-pkg>span>br:first-of-type,.flxu-c-type,.flxu-c-latest small,.flxu-c-latest br{display:none!important}
				.flxu-table td.flxu-c-inst,.flxu-table td.flxu-c-latest{grid-row:2;align-self:start;font-size:12px;font-variant-numeric:tabular-nums;white-space:nowrap}
				.flxu-table td.flxu-c-inst{grid-column:3;color:var(--flxa-muted,#646970)}
				.flxu-table td.flxu-c-inst br{display:none}.flxu-c-inst .flxu-off{margin-left:4px}
				.flxu-table tr.flxu-row>td.flxu-c-latest{grid-column:4;display:none;font-weight:600}
				.flxu-table tr.flxu-row--update>td.flxu-c-latest{display:block}
				.flxu-row--update td.flxu-c-inst::after{content:" →"}
				.flxu-table td.flxu-actions{grid-column:6;grid-row:1/3;white-space:normal;text-align:right}
				.flxu-idle{display:flex;flex-direction:column;align-items:flex-end;gap:4px}
				#flxu-packages .flxu-idle>.button{min-height:30px;line-height:28px}
				.flxu-badge{margin:0}
				.flxu-ver{position:static;margin:0}
				.flxu-ver__box{left:12px;right:12px;top:auto;min-width:0;text-align:left}
				.flxu-run{min-width:0;max-width:150px}
				.flxu-row--update{box-shadow:inset 3px 0 0 #dba617}
				/* Diğer kaynaklar: kart */
				.flxu-sources,.flxu-sources tbody{display:block}
				.flxu-sources thead{display:none}
				.flxu-sources tr{display:grid;grid-template-columns:1fr auto;gap:4px 10px;padding:12px;border-top:1px solid var(--flxa-border,#dcdcde)}
				.flxu-sources tbody tr:first-child{border-top:0}
				.flxu-sources tr>td{display:block;padding:0;border:0!important}
				.flxu-sources .flxu-src-url{grid-column:1/-1;grid-row:2;min-width:0;overflow-wrap:anywhere}
				.flxu-sources .flxu-src-url code{font-size:12px;background:none;padding:0;color:var(--flxa-muted,#646970)}
				.flxu-sources .flxu-src-type{display:none}
				.flxu-sources .flxu-src-act{grid-column:1/-1;text-align:left;white-space:normal}
			}
		</style>
		<script>
		(function(){
			document.addEventListener('click',function(e){
				var c=e.target.closest('.flxu-confirm'); if(c&&!window.confirm(c.getAttribute('data-confirm'))){e.preventDefault();}
				var d=e.target.closest('.flxu-del'); if(d){
					if(!window.confirm(<?php echo wp_json_encode( __( 'Bu yedek silinsin mi? Aynı toplu güncellemedeki diğer paketlerin yedeği de bu dosyadadır. Geri alınamaz.', 'flx-setup' ) ); ?>)){e.preventDefault();return;}
					d.form.elements.flxu_id.value=d.getAttribute('data-id');
				}
			});
			// Geçmiş, işlem sonrası yeniden yüklendiği için olaylar belgeye bağlı.
			document.addEventListener('change',function(e){
				var hf=document.getElementById('flxu-history-form'); if(!hf||!hf.contains(e.target)){return;}
				if(e.target.id==='flxu-hcheck-all'){hf.querySelectorAll('.flxu-hcheck').forEach(function(b){b.checked=e.target.checked;});}
				var hdel=document.getElementById('flxu-hdel'); if(hdel){hdel.disabled=!hf.querySelectorAll('.flxu-hcheck:checked').length;}
			});
		})();
		</script>
		<script>window.flxuL10n=<?php echo wp_json_encode( self::js_strings() ); ?>;</script>
		<script src="<?php echo esc_url( plugins_url( 'assets/packages.js', FLXU_FILE ) . '?ver=' . FLXU_VERSION ); ?>"></script>
		<?php
	}

	/** Panel betiğinin metinleri (çevrilebilir). */
	private static function js_strings(): array {
		return array(
			'needs'        => __( '%1$s için %2$s gerekli (%3$s).', 'flx-setup' ),
			'checking'     => __( 'Kontrol ediliyor…', 'flx-setup' ),
			'notInstalled' => __( 'kurulu değil', 'flx-setup' ),
			'inactive'     => __( 'kurulu, etkin değil', 'flx-setup' ),
			'addDep'       => __( '%s eklentisini de ekle', 'flx-setup' ),
			'together'     => __( 'Birlikte kurulacak / etkinleştirilecek:', 'flx-setup' ),
			'wporg'        => __( 'wordpress.org', 'flx-setup' ),
			'remove'       => __( 'Çıkar', 'flx-setup' ),
			'removed'      => __( '%1$s seçimden çıkarıldı: %2$s olmadan çalışmaz.', 'flx-setup' ),
			'confirmDeps'  => __( '%1$s için şunlar da kurulup etkinleştirilecek: %2$s. Devam edilsin mi?', 'flx-setup' ),
			'confirmVer'   => __( '%1$s için %2$s sürümü kurulsun mu?', 'flx-setup' ),
			'confirmDel'   => __( 'Şu paketler siteden kaldırılsın mı? Dosyaları silinir. %s', 'flx-setup' ),
			'preparing'    => __( 'Hazırlanıyor…', 'flx-setup' ),
			'working'      => __( 'İşlem sürüyor, sayfadan ayrılmayın…', 'flx-setup' ),
			'stay'         => __( 'Bitene kadar bu sayfadan ayrılmayın', 'flx-setup' ),
			'nothing'      => __( 'Yapılacak bir şey yok.', 'flx-setup' ),
			'done'         => __( 'Bitti: %1$d başarılı, %2$d hatalı.', 'flx-setup' ),
			'stepOf'       => __( '%1$d / %2$d', 'flx-setup' ),
			'http'         => __( 'Sunucu yanıt vermedi (%s). Geçmiş ve sistem kontrolüne bakın.', 'flx-setup' ),
			'leave'        => __( 'Kurulum/güncelleme sürüyor. Sayfadan ayrılırsanız yarım kalabilir.', 'flx-setup' ),
			'hideLog'      => __( 'Kaydı gizle', 'flx-setup' ),
			'showLog'      => __( 'Kaydı göster', 'flx-setup' ),
			'kind'         => array(
				'install'  => __( '%s kuruluyor…', 'flx-setup' ),
				'wporg'    => __( '%s wordpress.org\'dan kuruluyor…', 'flx-setup' ),
				'activate' => __( '%s etkinleştiriliyor…', 'flx-setup' ),
				'update'   => __( '%s güncelleniyor (önce yedek alınır)…', 'flx-setup' ),
				'version'  => __( '%s sürümü değiştiriliyor (önce yedek alınır)…', 'flx-setup' ),
			),
			'short'        => array(
				'install'  => __( 'Kuruluyor…', 'flx-setup' ),
				'wporg'    => __( 'Kuruluyor…', 'flx-setup' ),
				'activate' => __( 'Etkinleştiriliyor…', 'flx-setup' ),
				'update'   => __( 'Güncelleniyor…', 'flx-setup' ),
				'version'  => __( 'Sürüm değiştiriliyor…', 'flx-setup' ),
				'queued'   => __( 'Sırada', 'flx-setup' ),
			),
			'ok'           => __( 'Tamam', 'flx-setup' ),
			'fail'         => __( 'Hata', 'flx-setup' ),
		);
	}
}
