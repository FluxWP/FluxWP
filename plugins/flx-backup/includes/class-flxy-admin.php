<?php
/**
 * Yönetim: Yedekler menüsü (Panel, Yedekler, Ayarlar), AJAX işlemleri ve indirme.
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_Admin {

	const SLUG = 'flx-backup';
	const CAP  = 'manage_options';

	/** @var self|null */
	private static $instance = null;

	/** @var string[] */
	private $hooks = array();

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_head', array( $this, 'menu_icon_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_flxy_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_flxy_download', array( $this, 'download' ) );
		add_action( 'wp_ajax_flxy_start', array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_flxy_restore_start', array( $this, 'ajax_restore_start' ) );
		add_action( 'wp_ajax_flxy_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_flxy_lock', array( $this, 'ajax_lock' ) );
		add_action( 'wp_ajax_flxy_cancel', array( $this, 'ajax_cancel' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FLXY_FILE ), array( $this, 'action_links' ) );
	}

	public static function url( string $page = '', array $args = array() ): string {
		$slug = '' === $page ? self::SLUG : self::SLUG . '-' . $page;
		return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
	}

	public function menu(): void {
		$this->hooks[] = add_menu_page(
			__( 'Yedekler', 'flx-backup' ),
			__( 'Yedekler', 'flx-backup' ),
			self::CAP,
			self::SLUG,
			array( $this, 'render_dashboard' ),
			'none',
			76
		);
		$this->hooks[] = add_submenu_page( self::SLUG, __( 'Yedekler › Panel', 'flx-backup' ), __( 'Panel', 'flx-backup' ), self::CAP, self::SLUG, array( $this, 'render_dashboard' ) );
		$this->hooks[] = add_submenu_page( self::SLUG, __( 'Yedekler', 'flx-backup' ), __( 'Yedekler', 'flx-backup' ), self::CAP, self::SLUG . '-list', array( $this, 'render_list' ) );
		$this->hooks[] = add_submenu_page( self::SLUG, __( 'Yedekler › Ayarlar', 'flx-backup' ), __( 'Ayarlar', 'flx-backup' ), self::CAP, self::SLUG . '-settings', array( $this, 'render_settings' ) );
	}

	/** WordPress menü SVG'lerini tek renge boyar; renkli logo CSS arka planı olarak çizilir. */
	public function menu_icon_css(): void {
		echo '<style>#toplevel_page_' . esc_attr( self::SLUG ) . ' .wp-menu-image{background:url(' . esc_url( FLXY_URL . 'assets/icon.svg' ) . ') center/20px 20px no-repeat}#toplevel_page_' . esc_attr( self::SLUG ) . ' .wp-menu-image:before{content:none}</style>';
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url( 'settings' ) ) . '">' . esc_html__( 'Ayarlar', 'flx-backup' ) . '</a>' );
		return $links;
	}

	public function assets( $hook ): void {
		if ( ! in_array( $hook, $this->hooks, true ) ) {
			return;
		}
		wp_enqueue_style( 'flxy-admin', FLXY_URL . 'assets/admin.css', array(), FLXY_VERSION );
		wp_enqueue_script( 'flxy-admin', FLXY_URL . 'assets/admin.js', array(), FLXY_VERSION, true );
		wp_localize_script(
			'flxy-admin',
			'FLXY',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'flxy' ),
				'chunk' => FLXY_Upload::chunk_size(),
				'site'  => untrailingslashit( home_url() ),
				'dec'   => isset( $GLOBALS['wp_locale'] ) ? $GLOBALS['wp_locale']->number_format['decimal_point'] : '.',
				'i18n'  => array(
					'confirmDelete'  => __( 'Bu yedek kalıcı olarak silinsin mi?', 'flx-backup' ),
					'confirmBulk'    => __( 'Seçilen yedekler kalıcı olarak silinsin mi?', 'flx-backup' ),
					'confirmRestore' => __( 'Seçilen parçalar yedekteki haline döndürülecek ve mevcut halleri üzerine yazılacak. Devam edilsin mi?', 'flx-backup' ),
					'confirmCancel'  => __( 'Çalışan işlem iptal edilsin mi?', 'flx-backup' ),
					'starting'       => __( 'Başlatılıyor…', 'flx-backup' ),
					'backupTitle'    => __( 'Yedek alınıyor', 'flx-backup' ),
					'restoreTitle'   => __( 'Geri yükleniyor', 'flx-backup' ),
					'done'           => __( 'Tamamlandı', 'flx-backup' ),
					'failed'         => __( 'Başarısız', 'flx-backup' ),
					'reload'         => __( 'Sayfayı yenile', 'flx-backup' ),
					'close'          => __( 'Kapat', 'flx-backup' ),
					'cancel'         => __( 'İptal et', 'flx-backup' ),
					'error'          => __( 'Bir hata oluştu.', 'flx-backup' ),
					'retry'          => __( 'Bağlantı kesildi, yeniden deneniyor…', 'flx-backup' ),
					'db'             => __( 'Veritabanı (tüm tablolar)', 'flx-backup' ),
					'pickPart'       => __( 'En az bir parça seçin.', 'flx-backup' ),
					'keepOpen'       => __( 'Bu sayfayı açık tutabilirsiniz; kapatırsanız işlem arka planda sürer.', 'flx-backup' ),
					'restoreOpen'    => __( 'Geri yükleme bitene kadar bu sayfayı kapatmayın.', 'flx-backup' ),
					'bgBackup'       => __( 'Yedek arka planda alınıyor. İlerlemeyi üst çubuktaki göstergeden izleyebilir, bu sayfadan ayrılabilirsiniz.', 'flx-backup' ),
					'bgRestore'      => __( 'Geri yükleme arka planda başladı. İlerleme üst çubuktaki göstergede; bitene kadar siteyi değiştirmeyin.', 'flx-backup' ),
					'backupReady'    => __( 'Yedek hazır.', 'flx-backup' ),
					'showList'       => __( 'Listeyi yenile', 'flx-backup' ),
					'details'        => __( 'Ayrıntılar', 'flx-backup' ),
					'dismiss'        => __( 'Bu bildirimi kapat.', 'flx-backup' ),
					'uploading'      => __( '%1$s yükleniyor…', 'flx-backup' ),
					'uploadProgress' => __( '%1$s / %2$s', 'flx-backup' ),
					'verifying'      => __( 'Doğrulanıyor…', 'flx-backup' ),
					'uploadNotZip'   => __( 'Yalnızca Flux Backup ile alınmış .zip yedek dosyaları yüklenebilir.', 'flx-backup' ),
					'uploadCancel'   => __( 'Yükleme iptal edildi.', 'flx-backup' ),
					'uploadFailed'   => __( 'Yükleme başarısız: %s', 'flx-backup' ),
					'foreign'        => __( 'Bu yedek başka bir siteden alınmış: %s. Veritabanı geri yüklenirse içerik o siteden gelir; bu sitenin adresi (siteurl/home) korunur, içerikteki eski adres bağlantıları kendiliğinden değişmez.', 'flx-backup' ),
					'prefixMismatch' => __( 'Yedekteki tablo öneki (%1$s) bu sitenin önekinden (%2$s) farklı; veritabanı bu siteye geri yüklenemez, yalnız dosyalar seçilebilir.', 'flx-backup' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Ortak parçalar                                                      */
	/* ------------------------------------------------------------------ */

	private function header( string $current ): void {
		$tabs = array(
			''         => __( 'Panel', 'flx-backup' ),
			'list'     => __( 'Yedekler', 'flx-backup' ),
			'settings' => __( 'Ayarlar', 'flx-backup' ),
		);
		echo '<div class="flxy-head">';
		echo '<img class="flxy-logo" src="' . esc_url( FLXY_URL . 'assets/icon.svg' ) . '" alt="" width="40" height="40">';
		echo '<div class="flxy-head-text"><h1>' . esc_html__( 'Flux Backup', 'flx-backup' ) . '</h1>';
		echo '<p>' . esc_html__( 'Güncelleme öncesi otomatik yedek, zamanlanmış yedek ve geri yükleme', 'flx-backup' ) . '</p></div>';
		if ( 'settings' !== $current ) {
			echo '<div class="flxy-head-actions">';
			if ( 'list' === $current ) {
				echo '<label class="button button-hero flxy-upload-btn"><span class="dashicons dashicons-upload" aria-hidden="true"></span> ' . esc_html__( 'Yedek yükle', 'flx-backup' ) . '<input type="file" id="flxy-upload-file" accept=".zip,application/zip" class="screen-reader-text"></label>';
			}
			echo '<button type="button" class="button button-primary button-hero flxy-open-backup">' . esc_html__( 'Şimdi yedek al', 'flx-backup' ) . '</button>';
			echo '</div>';
		}
		echo '</div>';
		echo '<nav class="nav-tab-wrapper flxy-tabs">';
		foreach ( $tabs as $key => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( self::url( $key ) ),
				$key === $current ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';
		echo '<hr class="wp-header-end">';
		$this->notices();
		echo '<div id="flxy-toasts" aria-live="polite"></div>';
		if ( 'list' === $current ) {
			$this->upload_panel();
		}
	}

	private function notices(): void {
		$msg = isset( $_GET['flxy_msg'] ) ? sanitize_key( $_GET['flxy_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'saved' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Ayarlar kaydedildi.', 'flx-backup' ) . '</p></div>';
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Sunucuda ZipArchive (PHP zip eklentisi) yok.', 'flx-backup' ) . '</p></div>';
		}
		if ( ! FLXY_Storage::is_writable() ) {
			/* translators: %s: directory */
			echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'Yedek klasörü yazılabilir değil: %s', 'flx-backup' ), FLXY_Storage::dir() ) ) . '</p></div>';
		}
		$last = get_option( 'flxy_last_restore' );
		if ( is_array( $last ) && $last['at'] > time() - 10 * MINUTE_IN_SECONDS ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $last['result'] ) . '</p></div>';
		}
	}

	private function type_badge( string $type ): string {
		$labels = FLXY_Storage::type_labels();
		$colors = FLXY_Storage::type_colors();
		return sprintf(
			'<span class="flxy-badge" style="--c:%s">%s</span>',
			esc_attr( $colors[ $type ] ?? '#646970' ),
			esc_html( $labels[ $type ] ?? $type )
		);
	}

	/** "Şimdi yedek al", geri yükleme ve ilerleme pencereleri. */
	private function modals(): void {
		$core = (bool) FLXY_Settings::get( 'include_core' );
		?>
		<div class="flxy-modal" id="flxy-backup-modal" hidden>
			<div class="flxy-modal-box" role="dialog" aria-modal="true" aria-labelledby="flxy-backup-title">
				<h2 id="flxy-backup-title"><?php esc_html_e( 'Şimdi yedek al', 'flx-backup' ); ?></h2>
				<form id="flxy-backup-form">
					<fieldset class="flxy-choices">
						<legend class="screen-reader-text"><?php esc_html_e( 'Yedek türü', 'flx-backup' ); ?></legend>
						<label class="flxy-choice"><input type="radio" name="type" value="full" checked>
							<span><strong><?php esc_html_e( 'Tam yedek', 'flx-backup' ); ?></strong><small><?php esc_html_e( 'Veritabanı + wp-content (eklentiler, temalar, yüklemeler)', 'flx-backup' ); ?></small></span></label>
						<label class="flxy-choice"><input type="radio" name="type" value="db">
							<span><strong><?php esc_html_e( 'Yalnız veritabanı', 'flx-backup' ); ?></strong><small><?php esc_html_e( 'Yazılar, sayfalar, ayarlar, kullanıcılar', 'flx-backup' ); ?></small></span></label>
						<label class="flxy-choice"><input type="radio" name="type" value="files">
							<span><strong><?php esc_html_e( 'Yalnız dosyalar', 'flx-backup' ); ?></strong><small><?php esc_html_e( 'wp-content klasörü', 'flx-backup' ); ?></small></span></label>
					</fieldset>
					<p class="flxy-core-opt"><label><input type="checkbox" name="include_core" value="1" <?php checked( $core ); ?>> <?php esc_html_e( 'WordPress çekirdek dosyalarını da ekle (wp-admin, wp-includes)', 'flx-backup' ); ?></label></p>
					<p><label for="flxy-label"><?php esc_html_e( 'Açıklama (isteğe bağlı)', 'flx-backup' ); ?></label><br>
						<input type="text" id="flxy-label" name="label" class="regular-text" maxlength="120" placeholder="<?php esc_attr_e( 'ör. Tema değişikliğinden önce', 'flx-backup' ); ?>"></p>
					<p><label><input type="checkbox" name="lock" value="1"> <?php esc_html_e( 'Kilitle: kota dolsa da otomatik silinmesin', 'flx-backup' ); ?></label></p>
					<div class="flxy-modal-actions">
						<button type="button" class="button flxy-modal-close"><?php esc_html_e( 'Vazgeç', 'flx-backup' ); ?></button>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Yedeği başlat', 'flx-backup' ); ?></button>
					</div>
				</form>
			</div>
		</div>

		<div class="flxy-modal" id="flxy-restore-modal" hidden>
			<div class="flxy-modal-box" role="dialog" aria-modal="true" aria-labelledby="flxy-restore-title">
				<h2 id="flxy-restore-title"><?php esc_html_e( 'Geri yükle', 'flx-backup' ); ?></h2>
				<p class="flxy-restore-name"></p>
				<div class="flxy-restore-foreign notice notice-warning inline" hidden><p></p></div>
				<form id="flxy-restore-form">
					<input type="hidden" name="id" value="">
					<div class="flxy-restore-parts"></div>
					<p class="flxy-safety"><label><input type="checkbox" name="safety" value="1" <?php checked( (bool) FLXY_Settings::get( 'safety_restore' ) ); ?>> <?php esc_html_e( 'Önce mevcut veritabanının güvenlik yedeğini al (önerilir)', 'flx-backup' ); ?></label></p>
					<p class="description"><?php esc_html_e( 'Site adresi (siteurl/home), Flux Backup ayarları ve yedek listesi korunur. Eklenti ve tema klasörleri yedekteki haliyle değiştirilir; diğer klasörlerde dosyalar üzerine yazılır.', 'flx-backup' ); ?></p>
					<div class="flxy-modal-actions">
						<button type="button" class="button flxy-modal-close"><?php esc_html_e( 'Vazgeç', 'flx-backup' ); ?></button>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Geri yüklemeyi başlat', 'flx-backup' ); ?></button>
					</div>
				</form>
			</div>
		</div>

		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Panel                                                               */
	/* ------------------------------------------------------------------ */

	public function render_dashboard(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-backup' ), 403 );
		}
		FLXY_Storage::sync();
		FLXY_Storage::sample();
		$records = FLXY_Storage::records();
		$used    = FLXY_Storage::used_bytes();
		$quota   = max( 1, FLXY_Settings::quota_bytes() );
		$pct     = min( 100, 100 * $used / $quota );
		$level   = $pct > 95 ? 'error' : ( $pct > 80 ? 'warning' : 'ok' );
		$ok      = array_values(
			array_filter(
				$records,
				static function ( $r ) {
					return 'ok' === $r['status'];
				}
			)
		);
		$locked  = count(
			array_filter(
				$ok,
				static function ( $r ) {
					return $r['locked'];
				}
			)
		);
		$last    = $ok ? $ok[0] : null;
		$next    = FLXY_Cron::next_scheduled();
		$pre     = (string) FLXY_Settings::get( 'pre_scope' );
		?>
		<div class="wrap flxy-wrap">
			<?php $this->header( '' ); ?>

			<div class="flxy-cards">
				<section class="flxy-card flxy-quota flxy-level-<?php echo esc_attr( $level ); ?>">
					<h2><?php esc_html_e( 'Depolama', 'flx-backup' ); ?></h2>
					<p class="flxy-big"><?php echo esc_html( size_format( $used, 1 ) ); ?> <span>/ <?php echo esc_html( size_format( $quota, 1 ) ); ?></span></p>
					<div class="flxy-bar" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: percent */ __( 'Kotanın %%%s kadarı dolu', 'flx-backup' ), number_format_i18n( $pct, 0 ) ) ); ?>">
						<?php
						$colors = FLXY_Storage::type_colors();
						foreach ( FLXY_Storage::bytes_by_type() as $type => $bytes ) {
							if ( $bytes <= 0 ) {
								continue;
							}
							printf( '<span style="width:%s%%;background:%s" title="%s"></span>', esc_attr( (string) round( 100 * $bytes / $quota, 3 ) ), esc_attr( $colors[ $type ] ?? '#646970' ), esc_attr( FLXY_Storage::type_labels()[ $type ] . ': ' . size_format( $bytes, 1 ) ) );
						}
						?>
					</div>
					<p class="flxy-sub">
						<?php
						/* translators: 1: percent used, 2: free space */
						echo esc_html( sprintf( __( '%%%1$s dolu · %2$s boş', 'flx-backup' ), number_format_i18n( $pct, 0 ), size_format( max( 0, $quota - $used ), 1 ) ) );
						?>
					</p>
				</section>
				<section class="flxy-card">
					<h2><?php esc_html_e( 'Yedekler', 'flx-backup' ); ?></h2>
					<p class="flxy-big"><?php echo esc_html( number_format_i18n( count( $ok ) ) ); ?></p>
					<p class="flxy-sub">
						<?php
						/* translators: %s: number of locked backups */
						echo esc_html( sprintf( _n( '%s kilitli', '%s kilitli', $locked, 'flx-backup' ), number_format_i18n( $locked ) ) );
						?>
					</p>
				</section>
				<section class="flxy-card">
					<h2><?php esc_html_e( 'Son başarılı yedek', 'flx-backup' ); ?></h2>
					<?php if ( $last ) : ?>
						<p class="flxy-big flxy-big-sm">
							<?php
							/* translators: %s: human time diff */
							echo esc_html( flx_time_ago( $last['created'] ) );
							?>
						</p>
						<p class="flxy-sub"><?php echo esc_html( FLXY_Storage::title( $last ) . ' · ' . flxy_date( $last['created'] ) ); ?></p>
					<?php else : ?>
						<p class="flxy-big flxy-big-sm">—</p>
						<p class="flxy-sub"><?php esc_html_e( 'Henüz yedek yok.', 'flx-backup' ); ?></p>
					<?php endif; ?>
				</section>
				<section class="flxy-card">
					<h2><?php esc_html_e( 'Sonraki zamanlanmış', 'flx-backup' ); ?></h2>
					<?php if ( $next ) : ?>
						<p class="flxy-big flxy-big-sm"><?php echo esc_html( flxy_date( $next, 'date' ) ); ?></p>
						<p class="flxy-sub">
							<?php
							echo esc_html( flxy_date( $next, 'time' ) . ' · ' . ( 'weekly' === FLXY_Settings::get( 'schedule' ) ? __( 'Haftalık tam yedek', 'flx-backup' ) : __( 'Günlük tam yedek', 'flx-backup' ) ) );
							?>
						</p>
					<?php else : ?>
						<p class="flxy-big flxy-big-sm"><?php esc_html_e( 'Kapalı', 'flx-backup' ); ?></p>
						<p class="flxy-sub"><a href="<?php echo esc_url( self::url( 'settings' ) ); ?>"><?php esc_html_e( 'Zamanlamayı aç', 'flx-backup' ); ?></a></p>
					<?php endif; ?>
					<p class="flxy-sub flxy-pre <?php echo 'off' === $pre ? 'is-off' : 'is-on'; ?>">
						<?php
						echo 'off' === $pre ? esc_html__( 'Güncelleme öncesi yedek: kapalı', 'flx-backup' ) : ( 'flx' === $pre ? esc_html__( 'Güncelleme öncesi yedek: FluxWP paketleri', 'flx-backup' ) : esc_html__( 'Güncelleme öncesi yedek: açık', 'flx-backup' ) );
						?>
					</p>
				</section>
			</div>

			<div class="flxy-grid">
				<section class="flxy-card flxy-chart-card">
					<div class="flxy-card-head">
						<h2><?php esc_html_e( 'Depolama geçmişi', 'flx-backup' ); ?></h2>
						<div class="flxy-range" role="group" aria-label="<?php esc_attr_e( 'Zaman aralığı', 'flx-backup' ); ?>">
							<button type="button" class="button" data-range="30" aria-pressed="true"><?php echo esc_html( sprintf( /* translators: %s: number of days */ __( '%s gün', 'flx-backup' ), number_format_i18n( 30 ) ) ); ?></button>
							<button type="button" class="button" data-range="90" aria-pressed="false"><?php echo esc_html( sprintf( /* translators: %s: number of days */ __( '%s gün', 'flx-backup' ), number_format_i18n( 90 ) ) ); ?></button>
							<button type="button" class="button" data-range="180" aria-pressed="false"><?php echo esc_html( sprintf( /* translators: %s: number of days */ __( '%s gün', 'flx-backup' ), number_format_i18n( 180 ) ) ); ?></button>
						</div>
					</div>
					<div class="flxy-legend">
						<?php foreach ( FLXY_Storage::type_labels() as $type => $label ) : ?>
							<span><i style="background:<?php echo esc_attr( $colors[ $type ] ); ?>"></i><?php echo esc_html( $label ); ?></span>
						<?php endforeach; ?>
						<span class="flxy-quota-legend"><i class="flxy-quota-key"></i><?php esc_html_e( 'Kota', 'flx-backup' ); ?></span>
					</div>
					<div class="flxy-chart" id="flxy-history"></div>
					<script type="application/json" id="flxy-history-data"><?php echo wp_json_encode( $this->history_data() ); ?></script>
				</section>
				<section class="flxy-card flxy-donut-card">
					<h2><?php esc_html_e( 'Türlere göre', 'flx-backup' ); ?></h2>
					<?php $this->donut(); ?>
				</section>
			</div>

			<div class="flxy-grid">
				<section class="flxy-card">
					<div class="flxy-card-head">
						<h2><?php esc_html_e( 'Son yedekler', 'flx-backup' ); ?></h2>
						<a href="<?php echo esc_url( self::url( 'list' ) ); ?>"><?php esc_html_e( 'Tümünü gör', 'flx-backup' ); ?></a>
					</div>
					<?php if ( ! $records ) : ?>
						<p class="flxy-empty"><?php esc_html_e( 'Henüz yedek yok. "Şimdi yedek al" ile ilk yedeği alın; eklenti ve tema güncellemelerinden önce yedek kendiliğinden alınır.', 'flx-backup' ); ?></p>
					<?php else : ?>
						<ul class="flxy-recent">
							<?php foreach ( array_slice( $records, 0, 6 ) as $r ) : ?>
								<li>
									<?php echo $this->type_badge( $r['type'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<span class="flxy-recent-title"><a href="<?php echo esc_url( self::detail_url( $r['id'] ) ); ?>"><?php echo esc_html( FLXY_Storage::title( $r ) ); ?></a>
										<small><?php echo esc_html( flxy_date( $r['created'] ) ); ?></small></span>
									<span class="flxy-recent-size">
										<?php
										if ( 'ok' === $r['status'] ) {
											echo esc_html( size_format( $r['size'], 1 ) );
										} elseif ( 'running' === $r['status'] ) {
											esc_html_e( 'Sürüyor', 'flx-backup' );
										} else {
											echo '<span class="flxy-failed" title="' . esc_attr( $r['error'] ) . '">' . esc_html__( 'Başarısız', 'flx-backup' ) . '</span>';
										}
										?>
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
				<section class="flxy-card">
					<h2><?php esc_html_e( 'Son olaylar', 'flx-backup' ); ?></h2>
					<?php $events = FLXY_Storage::events( 7 ); ?>
					<?php if ( ! $events ) : ?>
						<p class="flxy-empty"><?php esc_html_e( 'Henüz olay yok.', 'flx-backup' ); ?></p>
					<?php else : ?>
						<ul class="flxy-events">
							<?php foreach ( $events as $e ) : ?>
								<li class="flxy-ev-<?php echo esc_attr( $e['level'] ); ?>">
									<time><?php echo esc_html( flxy_date( (int) $e['at'] ) ); ?></time>
									<span><?php echo esc_html( $e['msg'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			</div>
			<?php $this->modals(); ?>
		</div>
		<?php
	}

	/** Grafik verisi: gün başına tür boyutları; eksik günler önceki değerle doldurulur. Etiketler PHP'de i18n ile. */
	private function history_data(): array {
		$hist   = FLXY_Storage::history();
		$types  = array_keys( FLXY_Storage::type_labels() );
		$labels = FLXY_Storage::type_labels();
		$colors = FLXY_Storage::type_colors();
		$tz     = wp_timezone();
		$today  = new DateTimeImmutable( 'today', $tz );
		$first  = $hist ? new DateTimeImmutable( (string) array_key_first( $hist ), $tz ) : $today;
		$start  = $today->modify( '-179 days' );
		$days   = array();
		$prev   = array_fill_keys( $types, 0 );
		$have   = false;
		for ( $d = $start; $d <= $today; $d = $d->modify( '+1 day' ) ) {
			$key = $d->format( 'Y-m-d' );
			if ( isset( $hist[ $key ] ) ) {
				$prev = array();
				foreach ( $types as $t ) {
					$prev[ $t ] = (int) ( $hist[ $key ]['by'][ $t ] ?? 0 );
				}
				$have = true;
			}
			$ts     = $d->getTimestamp();
			$vals   = array();
			$vlabel = array();
			foreach ( $types as $t ) {
				$v        = $have ? $prev[ $t ] : 0;
				$vals[]   = $v;
				$vlabel[] = size_format( $v, 1 );
			}
			$days[] = array(
				'k'  => $key,
				'x'  => flx_date( $ts, 'short' ),
				'd'  => flx_date( $ts ),
				'v'  => $vals,
				'vl' => $vlabel,
				't'  => size_format( array_sum( $vals ), 1 ),
				'h'  => $have && $d >= $first,
			);
		}
		$quota  = FLXY_Settings::quota_bytes();
		$ranges = array();
		foreach ( array( 30, 90, 180 ) as $n ) {
			$slice = array_slice( $days, -$n );
			$max   = 0;
			foreach ( $slice as $day ) {
				$max = max( $max, array_sum( $day['v'] ) );
			}
			$ranges[ $n ] = $this->ticks( $max, $quota );
		}
		$series = array();
		foreach ( $types as $t ) {
			$series[] = array(
				'key'   => $t,
				'name'  => $labels[ $t ],
				'color' => $colors[ $t ],
			);
		}
		return array(
			'days'   => $days,
			'series' => $series,
			'ranges' => $ranges,
			'quota'  => $quota,
			'quotaL' => __( 'Kota', 'flx-backup' ) . ' ' . size_format( $quota, 1 ),
			'total'  => __( 'Toplam', 'flx-backup' ),
			'empty'  => __( 'Henüz yeterli veri yok: her gün bir ölçüm kaydedilir.', 'flx-backup' ),
			'noData' => __( 'Bu gün için ölçüm yok', 'flx-backup' ),
		);
	}

	/** Y ekseni: "güzel" adımlar; kota yakınsa ölçeğe dahil edilir. */
	private function ticks( int $max, int $quota ): array {
		$show_quota = $max >= $quota * 0.5;
		$top        = max( $show_quota ? $quota : $max, MB_IN_BYTES );
		$raw        = $top / 4;
		$mag        = pow( 10, floor( log10( $raw ) ) );
		$step       = $mag;
		foreach ( array( 1, 2, 2.5, 5, 10 ) as $m ) {
			if ( $m * $mag >= $raw ) {
				$step = $m * $mag;
				break;
			}
		}
		// İkili birimlerle okunaklı olsun diye adımı MB/GB katlarına yuvarla.
		$unit = $top >= GB_IN_BYTES ? GB_IN_BYTES : MB_IN_BYTES;
		$step = max( $unit / 4, round( $step / ( $unit / 4 ) ) * ( $unit / 4 ) );
		$ymax = $step * ceil( $top * 1.08 / $step );
		$out  = array();
		for ( $v = 0; $v <= $ymax + 1; $v += $step ) {
			$out[] = array(
				'v' => $v,
				'l' => 0 === (int) $v ? '0' : size_format( $v, $step < $unit ? 1 : 0 ),
			);
		}
		return array(
			'max'       => $ymax,
			'ticks'     => $out,
			'showQuota' => $show_quota,
		);
	}

	private function donut(): void {
		$by     = array_filter( FLXY_Storage::bytes_by_type() );
		$total  = array_sum( $by );
		$labels = FLXY_Storage::type_labels();
		$colors = FLXY_Storage::type_colors();
		if ( $total <= 0 ) {
			echo '<p class="flxy-empty">' . esc_html__( 'Henüz yedek yok.', 'flx-backup' ) . '</p>';
			return;
		}
		$r    = 60;
		$c    = 2 * M_PI * $r;
		$off  = 0.0;
		$gap  = count( $by ) > 1 ? 2.0 : 0.0;
		echo '<div class="flxy-donut"><svg viewBox="0 0 160 160" width="160" height="160" role="img" aria-label="' . esc_attr__( 'Türlere göre depolama', 'flx-backup' ) . '">';
		echo '<circle cx="80" cy="80" r="' . esc_attr( (string) $r ) . '" fill="none" stroke="#f0f0f1" stroke-width="22"/>';
		foreach ( $by as $type => $bytes ) {
			$len = $c * $bytes / $total;
			printf(
				'<circle cx="80" cy="80" r="%1$s" fill="none" stroke="%2$s" stroke-width="22" stroke-dasharray="%3$s %4$s" stroke-dashoffset="%5$s" transform="rotate(-90 80 80)"><title>%6$s</title></circle>',
				esc_attr( (string) $r ),
				esc_attr( $colors[ $type ] ?? '#646970' ),
				esc_attr( (string) max( 0.5, $len - $gap ) ),
				esc_attr( (string) ( $c - max( 0.5, $len - $gap ) ) ),
				esc_attr( (string) -$off ),
				esc_html( ( $labels[ $type ] ?? $type ) . ': ' . size_format( $bytes, 1 ) )
			);
			$off += $len;
		}
		echo '<text x="80" y="76" text-anchor="middle" class="flxy-donut-total">' . esc_html( size_format( $total, 1 ) ) . '</text>';
		echo '<text x="80" y="96" text-anchor="middle" class="flxy-donut-sub">' . esc_html__( 'toplam', 'flx-backup' ) . '</text>';
		echo '</svg>';
		echo '<table class="flxy-donut-table"><tbody>';
		$count = array();
		foreach ( FLXY_Storage::records() as $rec ) {
			if ( 'ok' === $rec['status'] ) {
				$count[ $rec['type'] ] = ( $count[ $rec['type'] ] ?? 0 ) + 1;
			}
		}
		foreach ( $by as $type => $bytes ) {
			printf(
				'<tr><th scope="row"><i style="background:%1$s"></i>%2$s <small>%3$s</small></th><td>%4$s</td><td class="flxy-muted">%5$s</td></tr>',
				esc_attr( $colors[ $type ] ?? '#646970' ),
				esc_html( $labels[ $type ] ?? $type ),
				esc_html( sprintf( /* translators: %s: number of backups */ _n( '%s yedek', '%s yedek', $count[ $type ] ?? 0, 'flx-backup' ), number_format_i18n( $count[ $type ] ?? 0 ) ) ),
				esc_html( size_format( $bytes, 1 ) ),
				esc_html( '%' . number_format_i18n( 100 * $bytes / $total, 0 ) )
			);
		}
		echo '</tbody></table></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Liste                                                               */
	/* ------------------------------------------------------------------ */

	public static function detail_url( string $id ): string {
		return self::url( 'list', array( 'view' => $id ) );
	}

	public function render_list(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-backup' ), 403 );
		}
		FLXY_Storage::sync();
		$view = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $view ) {
			$this->render_detail( $view );
			return;
		}
		$filter  = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$all     = FLXY_Storage::records();
		$labels  = FLXY_Storage::type_labels();
		$counts  = array();
		foreach ( $all as $r ) {
			$counts[ $r['type'] ] = ( $counts[ $r['type'] ] ?? 0 ) + 1;
		}
		$records = $filter ? array_values(
			array_filter(
				$all,
				static function ( $r ) use ( $filter ) {
					return $r['type'] === $filter;
				}
			)
		) : $all;
		?>
		<div class="wrap flxy-wrap">
			<?php $this->header( 'list' ); ?>
			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( self::url( 'list' ) ); ?>" class="<?php echo '' === $filter ? 'current' : ''; ?>"><?php esc_html_e( 'Tümü', 'flx-backup' ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( count( $all ) ) ); ?>)</span></a></li>
				<?php foreach ( $labels as $type => $label ) : ?>
					<?php
					if ( empty( $counts[ $type ] ) ) {
						continue;
					}
					?>
					<li>| <a href="<?php echo esc_url( self::url( 'list', array( 'type' => $type ) ) ); ?>" class="<?php echo $filter === $type ? 'current' : ''; ?>"><?php echo esc_html( $label ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( $counts[ $type ] ) ); ?>)</span></a></li>
				<?php endforeach; ?>
			</ul>
			<form id="flxy-list-form">
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<button type="button" class="button flxy-bulk-delete" disabled><?php esc_html_e( 'Seçilenleri sil', 'flx-backup' ); ?></button>
					</div>
					<div class="tablenav-pages one-page"><span class="displaying-num">
						<?php
						/* translators: %s: total size */
						echo esc_html( sprintf( __( 'Toplam %s', 'flx-backup' ), size_format( FLXY_Storage::used_bytes(), 1 ) ) );
						?>
					</span></div>
				</div>
				<table class="widefat striped flxy-table">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column"><input type="checkbox" class="flxy-check-all" aria-label="<?php esc_attr_e( 'Tümünü seç', 'flx-backup' ); ?>"></td>
							<th scope="col" class="col-date"><?php esc_html_e( 'Tarih', 'flx-backup' ); ?></th>
							<th scope="col" class="col-type"><?php esc_html_e( 'Tür', 'flx-backup' ); ?></th>
							<th scope="col" class="col-title"><?php esc_html_e( 'Açıklama', 'flx-backup' ); ?></th>
							<th scope="col" class="col-contents"><?php esc_html_e( 'İçerik', 'flx-backup' ); ?></th>
							<th scope="col" class="col-size"><?php esc_html_e( 'Boyut', 'flx-backup' ); ?></th>
							<th scope="col" class="col-keep"><?php esc_html_e( 'Saklama', 'flx-backup' ); ?></th>
							<th scope="col" class="col-actions"><span class="screen-reader-text"><?php esc_html_e( 'İşlemler', 'flx-backup' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( ! $records ) : ?>
						<tr class="no-items"><td colspan="8"><?php esc_html_e( 'Yedek yok.', 'flx-backup' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $records as $r ) : ?>
						<?php $this->row( $r ); ?>
					<?php endforeach; ?>
					</tbody>
				</table>
			</form>
			<p class="description flxy-storage-note">
				<?php
				/* translators: %s: directory */
				echo esc_html( sprintf( __( 'Yedekler %s klasöründe. Hostinger bu klasörü kendi yedeklerine almaz: önemli yedekleri bilgisayarınıza indirin.', 'flx-backup' ), FLXY_Backup::rel( FLXY_Storage::dir() ) ) );
				?>
			</p>
			<?php $this->modals(); ?>
		</div>
		<?php
	}

	private function row( array $r ): void {
		$sources  = FLXY_Storage::source_labels();
		$ctx      = $r['context'];
		$source   = (string) ( $ctx['source'] ?? '' );
		$contents = array();
		if ( $r['db'] ) {
			$contents[] = __( 'Veritabanı', 'flx-backup' );
		}
		$content_rel = FLXY_Backup::content_rel();
		foreach ( $r['paths'] as $p ) {
			if ( $p === $content_rel ) {
				$contents[] = 'wp-content';
			} elseif ( preg_match( '#/(plugins|themes)/([^/]+)$#', $p, $m ) ) {
				$contents[] = ( 'plugins' === $m[1] ? __( 'Eklenti', 'flx-backup' ) : __( 'Tema', 'flx-backup' ) ) . ': ' . preg_replace( '/\.php$/', '', $m[2] );
			} elseif ( ! in_array( $p, array( 'wp-admin', 'wp-includes' ), true ) && false === strpos( $p, '/' ) && is_file( FLXY_Backup::abs( $p ) ) ) {
				continue; // Kök dosyalar "çekirdek" altında.
			} else {
				$contents[] = $p;
			}
		}
		if ( in_array( 'wp-includes', $r['paths'], true ) ) {
			$contents   = array_diff( $contents, array( 'wp-admin', 'wp-includes' ) );
			$contents[] = __( 'WordPress çekirdeği', 'flx-backup' );
		}
		$restore = $this->restore_data( $r );
		$dl = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'flxy_download',
					'id'     => $r['id'],
				),
				admin_url( 'admin-post.php' )
			),
			'flxy_download_' . $r['id']
		);
		?>
		<tr data-id="<?php echo esc_attr( $r['id'] ); ?>" class="flxy-status-<?php echo esc_attr( $r['status'] ); ?>">
			<th scope="row" class="check-column"><?php if ( 'running' !== $r['status'] ) : ?><input type="checkbox" name="ids[]" value="<?php echo esc_attr( $r['id'] ); ?>" aria-label="<?php esc_attr_e( 'Seç', 'flx-backup' ); ?>"><?php endif; ?></th>
			<td class="col-date" data-colname="<?php esc_attr_e( 'Tarih', 'flx-backup' ); ?>">
				<strong><?php echo esc_html( flxy_date( $r['created'], 'date' ) ); ?></strong><br>
				<span class="flxy-muted"><?php echo esc_html( flxy_date( $r['created'], 'time' ) ); ?></span>
			</td>
			<td class="col-type" data-colname="<?php esc_attr_e( 'Tür', 'flx-backup' ); ?>"><?php echo $this->type_badge( $r['type'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
			<td class="col-title" data-colname="<?php esc_attr_e( 'Açıklama', 'flx-backup' ); ?>">
				<strong><a class="flxy-row-link" href="<?php echo esc_url( self::detail_url( $r['id'] ) ); ?>"><?php echo esc_html( FLXY_Storage::title( $r ) ); ?></a></strong>
				<?php if ( $source ) : ?>
					<br><span class="flxy-muted"><?php echo esc_html( $sources[ $source ] ?? $source ); ?>
					<?php if ( 'upload' === $source && ! empty( $ctx['original_site_url'] ) ) : ?>
						· <?php echo esc_html( self::short_url( (string) $ctx['original_site_url'] ) ); ?>
					<?php endif; ?>
					</span>
				<?php endif; ?>
				<?php if ( 'pre_update' === $r['type'] && count( (array) ( $ctx['packages'] ?? array() ) ) > 1 ) : ?>
					<ul class="flxy-pkgs">
						<?php foreach ( $ctx['packages'] as $p ) : ?>
							<li><?php echo esc_html( ( $p['name'] ? $p['name'] : $p['slug'] ) . ' ' . $p['from_version'] . ( $p['to_version'] ? ' → ' . $p['to_version'] : '' ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( 'failed' === $r['status'] ) : ?>
					<div class="flxy-error"><?php echo esc_html( $r['error'] ); ?></div>
				<?php endif; ?>
			</td>
			<td class="col-contents" data-colname="<?php esc_attr_e( 'İçerik', 'flx-backup' ); ?>"><?php echo esc_html( implode( ', ', $contents ) ); ?>
				<?php if ( $r['files'] ) : ?>
					<br><span class="flxy-muted">
					<?php
					/* translators: %s: number of files */
					echo esc_html( sprintf( _n( '%s dosya', '%s dosya', $r['files'], 'flx-backup' ), number_format_i18n( $r['files'] ) ) );
					?>
					</span>
				<?php endif; ?>
			</td>
			<td class="col-size" data-colname="<?php esc_attr_e( 'Boyut', 'flx-backup' ); ?>">
				<?php
				if ( 'ok' === $r['status'] ) {
					echo esc_html( size_format( $r['size'], 1 ) );
				} elseif ( 'running' === $r['status'] ) {
					echo '<span class="flxy-running">' . esc_html__( 'Sürüyor…', 'flx-backup' ) . '</span>';
				} else {
					echo '<span class="flxy-failed">' . esc_html__( 'Başarısız', 'flx-backup' ) . '</span>';
				}
				?>
			</td>
			<td class="col-keep" data-colname="<?php esc_attr_e( 'Saklama', 'flx-backup' ); ?>">
				<button type="button" class="flxy-lock<?php echo $r['locked'] ? ' is-locked' : ''; ?>" data-locked="<?php echo $r['locked'] ? '1' : '0'; ?>" aria-pressed="<?php echo $r['locked'] ? 'true' : 'false'; ?>" title="<?php echo esc_attr( $r['locked'] ? __( 'Kilitli: otomatik silinmez. Kilidi açmak için tıklayın.', 'flx-backup' ) : __( 'Kilitle: kota ve saklama süresi bu yedeği silmez.', 'flx-backup' ) ); ?>" <?php disabled( 'ok' !== $r['status'] ); ?>>
					<span class="dashicons <?php echo $r['locked'] ? 'dashicons-lock' : 'dashicons-unlock'; ?>" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php $r['locked'] ? esc_html_e( 'Kilidi aç', 'flx-backup' ) : esc_html_e( 'Kilitle', 'flx-backup' ); ?></span>
				</button>
				<span class="flxy-keep-text">
				<?php
				if ( $r['locked'] ) {
					esc_html_e( 'Kilitli', 'flx-backup' );
				} elseif ( $r['keep_until'] ) {
					/* translators: %s: date */
					echo esc_html( sprintf( __( '%s tarihine kadar', 'flx-backup' ), flxy_date( $r['keep_until'], 'date' ) ) );
				} elseif ( 'schedule' === $source ) {
					/* translators: %s: number of backups kept */
					echo esc_html( sprintf( __( 'Son %s zamanlanmış', 'flx-backup' ), number_format_i18n( (int) FLXY_Settings::get( 'schedule_keep' ) ) ) );
				} else {
					esc_html_e( 'Kota dolana kadar', 'flx-backup' );
				}
				?>
				</span>
			</td>
			<td class="col-actions" data-colname="<?php esc_attr_e( 'İşlemler', 'flx-backup' ); ?>">
				<?php if ( 'ok' === $r['status'] ) : ?>
					<a class="button button-small" href="<?php echo esc_url( $dl ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span> <?php esc_html_e( 'İndir', 'flx-backup' ); ?></a>
					<button type="button" class="button button-small flxy-restore" data-restore="<?php echo esc_attr( wp_json_encode( $restore ) ); ?>"><span class="dashicons dashicons-backup" aria-hidden="true"></span> <?php esc_html_e( 'Geri yükle', 'flx-backup' ); ?></button>
				<?php endif; ?>
				<?php if ( 'running' !== $r['status'] || ! FLXY_Runner::job_is_for( $r['id'] ) ) : ?>
					<button type="button" class="button button-small button-link-delete flxy-delete"><?php esc_html_e( 'Sil', 'flx-backup' ); ?></button>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/** Yedek yükleme ilerleme paneli (dosya seçilince görünür). */
	private function upload_panel(): void {
		?>
		<div class="flxy-upload-panel" id="flxy-upload-panel" hidden>
			<div class="flxy-upload-row">
				<span class="dashicons dashicons-upload" aria-hidden="true"></span>
				<div class="flxy-upload-main">
					<strong class="flxy-upload-name"></strong>
					<div class="flxy-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span style="width:0"></span></div>
					<span class="flxy-upload-status flxy-muted"></span>
				</div>
				<button type="button" class="button flxy-upload-cancel"><?php esc_html_e( 'İptal et', 'flx-backup' ); ?></button>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Yedek ayrıntısı                                                     */
	/* ------------------------------------------------------------------ */

	private function render_detail( string $id ): void {
		$r = FLXY_Storage::get( $id );
		?>
		<div class="wrap flxy-wrap">
			<?php $this->header( 'list' ); ?>
			<p class="flxy-back"><a href="<?php echo esc_url( self::url( 'list' ) ); ?>">&larr; <?php esc_html_e( 'Tüm yedekler', 'flx-backup' ); ?></a></p>
			<?php if ( ! $r ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'Yedek bulunamadı.', 'flx-backup' ); ?></p></div>
				</div>
				<?php
				return;
			endif;
			$ctx     = $r['context'];
			$sources = FLXY_Storage::source_labels();
			$source  = (string) ( $ctx['source'] ?? '' );
			$b       = 'ok' === $r['status'] ? FLXY_Breakdown::for_record( $r ) : null;
			$dl      = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'flxy_download',
						'id'     => $r['id'],
					),
					admin_url( 'admin-post.php' )
				),
				'flxy_download_' . $r['id']
			);
			$meta = array();
			$meta[ __( 'Tarih', 'flx-backup' ) ] = flxy_date( $r['created'] );
			if ( 'ok' === $r['status'] ) {
				$meta[ __( 'Yedek dosyası', 'flx-backup' ) ] = size_format( $r['size'], 1 );
				if ( $b && $b['raw'] ) {
					$meta[ __( 'Açılmış boyut', 'flx-backup' ) ] = size_format( $b['raw'], 1 );
				}
			}
			if ( $r['files'] ) {
				/* translators: %s: number of files */
				$meta[ __( 'Dosyalar', 'flx-backup' ) ] = sprintf( _n( '%s dosya', '%s dosya', $r['files'], 'flx-backup' ), number_format_i18n( $r['files'] ) );
			}
			if ( $r['db'] ) {
				$tables = $b && isset( $b['cats']['db'] ) ? count( $b['cats']['db']['items'] ) + (int) ( $b['cats']['db']['more']['k'] ?? 0 ) : 0;
				$rows   = $b && isset( $b['cats']['db'] ) ? (int) $b['cats']['db']['files'] : 0;
				$meta[ __( 'Veritabanı', 'flx-backup' ) ] = $tables
					/* translators: 1: number of tables, 2: number of rows */
					? sprintf( __( '%1$s tablo, %2$s satır', 'flx-backup' ), number_format_i18n( $tables ), number_format_i18n( $rows ) )
					: __( 'Var', 'flx-backup' );
			}
			if ( $source ) {
				$meta[ __( 'Kaynak', 'flx-backup' ) ] = $sources[ $source ] ?? $source;
			}
			if ( 'upload' === $source ) {
				if ( ! empty( $ctx['original_site_url'] ) ) {
					$meta[ __( 'Alındığı site', 'flx-backup' ) ] = self::short_url( (string) $ctx['original_site_url'] );
				}
				if ( ! empty( $ctx['original_created'] ) ) {
					$meta[ __( 'Alındığı tarih', 'flx-backup' ) ] = flxy_date( (int) $ctx['original_created'] );
				}
			}
			if ( $r['wp_version'] ) {
				$meta[ __( 'WordPress', 'flx-backup' ) ] = $r['wp_version'];
			}
			if ( $r['locked'] ) {
				$meta[ __( 'Saklama', 'flx-backup' ) ] = __( 'Kilitli', 'flx-backup' );
			} elseif ( $r['keep_until'] ) {
				/* translators: %s: date */
				$meta[ __( 'Saklama', 'flx-backup' ) ] = sprintf( __( '%s tarihine kadar', 'flx-backup' ), flxy_date( $r['keep_until'], 'date' ) );
			} else {
				$meta[ __( 'Saklama', 'flx-backup' ) ] = __( 'Kota dolana kadar', 'flx-backup' );
			}
			?>
			<section class="flxy-card flxy-detail-head" data-id="<?php echo esc_attr( $r['id'] ); ?>">
				<div class="flxy-detail-title">
					<?php echo $this->type_badge( $r['type'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2><?php echo esc_html( FLXY_Storage::title( $r ) ); ?></h2>
				</div>
				<?php if ( 'pre_update' === $r['type'] && ! empty( $ctx['packages'] ) ) : ?>
					<ul class="flxy-pkgs">
						<?php foreach ( (array) $ctx['packages'] as $p ) : ?>
							<li><?php echo esc_html( ( ! empty( $p['name'] ) ? $p['name'] : ( $p['slug'] ?? '' ) ) . ' ' . ( $p['from_version'] ?? '' ) . ( ! empty( $p['to_version'] ) ? ' → ' . $p['to_version'] : '' ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<dl class="flxy-meta">
					<?php foreach ( $meta as $k => $v ) : ?>
						<div><dt><?php echo esc_html( $k ); ?></dt><dd><?php echo esc_html( $v ); ?></dd></div>
					<?php endforeach; ?>
				</dl>
				<?php if ( 'ok' === $r['status'] ) : ?>
					<div class="flxy-detail-actions">
						<a class="button" href="<?php echo esc_url( $dl ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span> <?php esc_html_e( 'İndir', 'flx-backup' ); ?></a>
						<button type="button" class="button flxy-restore" data-restore="<?php echo esc_attr( wp_json_encode( $this->restore_data( $r ) ) ); ?>"><span class="dashicons dashicons-backup" aria-hidden="true"></span> <?php esc_html_e( 'Geri yükle', 'flx-backup' ); ?></button>
					</div>
				<?php elseif ( 'running' === $r['status'] ) : ?>
					<p class="flxy-running"><?php esc_html_e( 'Yedek alınıyor; ayrıntılar yedek bitince burada görünür.', 'flx-backup' ); ?></p>
				<?php else : ?>
					<div class="flxy-error"><?php echo esc_html( $r['error'] ); ?></div>
				<?php endif; ?>
			</section>

			<?php if ( $b ) : ?>
				<?php $this->breakdown( $b ); ?>
			<?php elseif ( 'ok' === $r['status'] ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Yedek dosyası okunamadı; boyut dağılımı gösterilemiyor.', 'flx-backup' ); ?></p></div>
			<?php endif; ?>
			<?php $this->modals(); ?>
		</div>
		<?php
	}

	/** Boyut dağılımı: yığılmış çubuk + açılır tablo. */
	private function breakdown( array $b ): void {
		$labels = FLXY_Breakdown::labels();
		$colors = FLXY_Breakdown::colors();
		$cats   = array();
		foreach ( FLXY_Breakdown::CATS as $k ) {
			if ( ! empty( $b['cats'][ $k ] ) ) {
				$cats[ $k ] = $b['cats'][ $k ];
			}
		}
		$raw_total = max( 1, (int) $b['raw'] );
		$zip_total = max( 1, (int) $b['zip'] );
		?>
		<section class="flxy-card flxy-breakdown" data-metric="raw">
			<div class="flxy-card-head">
				<h2><?php esc_html_e( 'Boyut dağılımı', 'flx-backup' ); ?></h2>
				<div class="flxy-range flxy-metric" role="group" aria-label="<?php esc_attr_e( 'Ölçü', 'flx-backup' ); ?>">
					<button type="button" class="button" data-metric="raw" aria-pressed="true"><?php esc_html_e( 'Özgün boyut', 'flx-backup' ); ?></button>
					<button type="button" class="button" data-metric="zip" aria-pressed="false"><?php esc_html_e( 'Arşivdeki boyut', 'flx-backup' ); ?></button>
				</div>
			</div>
			<p class="flxy-big flxy-bd-total">
				<b data-raw><?php echo esc_html( self::bytes( $b['raw'] ) ); ?></b><b data-zip hidden><?php echo esc_html( self::bytes( $b['zip'] ) ); ?></b>
				<small data-raw><?php esc_html_e( 'açılmış haliyle', 'flx-backup' ); ?></small><small data-zip hidden><?php esc_html_e( 'zip içinde', 'flx-backup' ); ?></small>
			</p>
			<div class="flxy-stack" role="img" aria-label="<?php esc_attr_e( 'Kategorilere göre boyut dağılımı', 'flx-backup' ); ?>">
				<?php foreach ( $cats as $k => $c ) : ?>
					<span style="--raw:<?php echo esc_attr( (string) round( 100 * $c['raw'] / $raw_total, 3 ) ); ?>%;--zip:<?php echo esc_attr( (string) round( 100 * $c['zip'] / $zip_total, 3 ) ); ?>%;background:<?php echo esc_attr( $colors[ $k ] ); ?>" title="<?php echo esc_attr( $labels[ $k ] . ': ' . self::bytes( $c['raw'] ) ); ?>"></span>
				<?php endforeach; ?>
			</div>
			<ul class="flxy-legend flxy-stack-legend">
				<?php foreach ( $cats as $k => $c ) : ?>
					<li><i style="background:<?php echo esc_attr( $colors[ $k ] ); ?>"></i><?php echo esc_html( $labels[ $k ] ); ?>
						<b data-raw><?php echo esc_html( self::share( $c['raw'], $raw_total ) ); ?></b><b data-zip hidden><?php echo esc_html( self::share( $c['zip'], $zip_total ) ); ?></b></li>
				<?php endforeach; ?>
			</ul>

			<table class="widefat flxy-bd-table">
				<thead>
					<tr>
						<th scope="col" class="col-name"><?php esc_html_e( 'Bölüm', 'flx-backup' ); ?></th>
						<th scope="col" class="col-count"><?php esc_html_e( 'Dosya / satır', 'flx-backup' ); ?></th>
						<th scope="col" class="col-raw"><?php esc_html_e( 'Özgün boyut', 'flx-backup' ); ?></th>
						<th scope="col" class="col-zip"><?php esc_html_e( 'Arşivde', 'flx-backup' ); ?></th>
						<th scope="col" class="col-share"><?php esc_html_e( 'Pay', 'flx-backup' ); ?></th>
					</tr>
				</thead>
				<?php foreach ( $cats as $k => $c ) : ?>
					<?php
					$is_db = 'db' === $k;
					$max   = max( 1, (int) ( $c['items'][0]['raw'] ?? 1 ) );
					?>
					<tbody class="flxy-bd-cat" data-cat="<?php echo esc_attr( $k ); ?>">
						<tr class="flxy-bd-head">
							<th scope="row" class="col-name">
								<button type="button" class="flxy-bd-toggle" aria-expanded="false" aria-controls="flxy-bd-<?php echo esc_attr( $k ); ?>">
									<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
									<i style="background:<?php echo esc_attr( $colors[ $k ] ); ?>"></i><?php echo esc_html( $labels[ $k ] ); ?>
								</button>
							</th>
							<td class="col-count" data-colname="<?php esc_attr_e( 'Dosya / satır', 'flx-backup' ); ?>"><?php echo esc_html( self::count_text( (int) $c['files'], $is_db ) ); ?></td>
							<td class="col-raw" data-colname="<?php esc_attr_e( 'Özgün boyut', 'flx-backup' ); ?>"><?php echo esc_html( self::bytes( $c['raw'] ) ); ?></td>
							<td class="col-zip" data-colname="<?php esc_attr_e( 'Arşivde', 'flx-backup' ); ?>"><?php echo esc_html( self::bytes( $c['zip'] ) ); ?></td>
							<td class="col-share" data-colname="<?php esc_attr_e( 'Pay', 'flx-backup' ); ?>"><span data-raw><?php echo esc_html( self::share( $c['raw'], $raw_total ) ); ?></span><span data-zip hidden><?php echo esc_html( self::share( $c['zip'], $zip_total ) ); ?></span></td>
						</tr>
					</tbody>
					<tbody class="flxy-bd-items" id="flxy-bd-<?php echo esc_attr( $k ); ?>" hidden>
						<?php foreach ( $c['items'] as $it ) : ?>
							<tr>
								<th scope="row" class="col-name">
									<span class="flxy-bd-item"><?php echo esc_html( self::item_label( $k, (string) $it['n'] ) ); ?></span>
									<span class="flxy-bd-mini" aria-hidden="true"><span style="width:<?php echo esc_attr( (string) round( 100 * $it['raw'] / $max, 2 ) ); ?>%;background:<?php echo esc_attr( $colors[ $k ] ); ?>"></span></span>
								</th>
								<td class="col-count" data-colname="<?php esc_attr_e( 'Dosya / satır', 'flx-backup' ); ?>"><?php echo esc_html( self::count_text( (int) $it['c'], $is_db ) ); ?></td>
								<td class="col-raw" data-colname="<?php esc_attr_e( 'Özgün boyut', 'flx-backup' ); ?>"><?php echo esc_html( self::bytes( $it['raw'] ) ); ?></td>
								<td class="col-zip" data-colname="<?php esc_attr_e( 'Arşivde', 'flx-backup' ); ?>"><?php echo esc_html( self::bytes( $it['zip'] ) ); ?></td>
								<td class="col-share" data-colname="<?php esc_attr_e( 'Pay', 'flx-backup' ); ?>"><span data-raw><?php echo esc_html( self::share( $it['raw'], max( 1, $c['raw'] ) ) ); ?></span><span data-zip hidden><?php echo esc_html( self::share( $it['zip'], max( 1, $c['zip'] ) ) ); ?></span></td>
							</tr>
						<?php endforeach; ?>
						<?php if ( ! empty( $c['more'] ) ) : ?>
							<tr class="flxy-bd-more">
								<th scope="row" class="col-name">
									<?php
									/* translators: %s: number of other items */
									echo esc_html( sprintf( _n( 'Diğer %s öğe', 'Diğer %s öğe', (int) $c['more']['k'], 'flx-backup' ), number_format_i18n( (int) $c['more']['k'] ) ) );
									?>
								</th>
								<td class="col-count" data-colname="<?php esc_attr_e( 'Dosya / satır', 'flx-backup' ); ?>"><?php echo esc_html( self::count_text( (int) $c['more']['c'], $is_db ) ); ?></td>
								<td class="col-raw" data-colname="<?php esc_attr_e( 'Özgün boyut', 'flx-backup' ); ?>"><?php echo esc_html( self::bytes( $c['more']['raw'] ) ); ?></td>
								<td class="col-zip" data-colname="<?php esc_attr_e( 'Arşivde', 'flx-backup' ); ?>"><?php echo esc_html( self::bytes( $c['more']['zip'] ) ); ?></td>
								<td class="col-share" data-colname="<?php esc_attr_e( 'Pay', 'flx-backup' ); ?>"><span data-raw><?php echo esc_html( self::share( $c['more']['raw'], max( 1, $c['raw'] ) ) ); ?></span><span data-zip hidden><?php echo esc_html( self::share( $c['more']['zip'], max( 1, $c['zip'] ) ) ); ?></span></td>
							</tr>
						<?php endif; ?>
					</tbody>
				<?php endforeach; ?>
			</table>
			<p class="description"><?php esc_html_e( 'Özgün boyut: dosyaların sitedeki boyutu (veritabanı için SQL dökümünün boyutu). Arşivde: zip içindeki sıkıştırılmış boyutu. Görseller ve videolar zaten sıkıştırılmış olduğundan arşivde küçülmez.', 'flx-backup' ); ?></p>
		</section>
		<?php
	}

	private static function share( int $part, int $total ): string {
		$p = 100 * $part / max( 1, $total );
		$d = $p >= 0.1 && $p < 10 ? 1 : 0;
		/* translators: %s: number. Turkish puts the percent sign first (%87); English: 87% */
		return sprintf( _x( '%%%s', 'percent', 'flx-backup' ), number_format_i18n( $p, $d ) );
	}

	/** size_format, ama 1 KB altı ondalıksız ("393 B"). */
	private static function bytes( $n ): string {
		$n = (int) $n;
		return $n < KB_IN_BYTES ? size_format( $n, 0 ) : size_format( $n, 1 );
	}

	private static function count_text( int $n, bool $db ): string {
		return $db
			/* translators: %s: number of rows */
			? sprintf( _n( '%s satır', '%s satır', $n, 'flx-backup' ), number_format_i18n( $n ) )
			/* translators: %s: number of files */
			: sprintf( _n( '%s dosya', '%s dosya', $n, 'flx-backup' ), number_format_i18n( $n ) );
	}

	/** Öğe adı: eklenti/tema adı, uploads yılı, kök dosyalar … */
	private static function item_label( string $cat, string $n ): string {
		if ( '/' === $n ) {
			return 'core' === $cat ? __( 'Kök dosyalar (wp-config.php, index.php …)', 'flx-backup' ) : __( 'Klasörün kendi dosyaları', 'flx-backup' );
		}
		if ( 'plugins' === $cat || 'themes' === $cat ) {
			static $names = null;
			if ( null === $names ) {
				$names = array();
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				foreach ( get_plugins() as $file => $data ) {
					$slug                       = false === strpos( $file, '/' ) ? preg_replace( '/\.php$/', '', $file ) : dirname( $file );
					$names[ 'plugins/' . $slug ] = $data['Name'];
				}
				foreach ( wp_get_themes() as $slug => $theme ) {
					$names[ 'themes/' . $slug ] = $theme->get( 'Name' );
				}
			}
			$slug = preg_replace( '/\.php$/', '', $n );
			$key  = $cat . '/' . $slug;
			return isset( $names[ $key ] ) && $names[ $key ] !== $slug ? $names[ $key ] . ' (' . $slug . ')' : $n;
		}
		return $n;
	}

	/** Geri yükleme düğmesinin verisi (pencere bundan kurulur). */
	private function restore_data( array $r ): array {
		global $wpdb;
		$ctx     = $r['context'];
		$foreign = '';
		$orig    = untrailingslashit( (string) ( $ctx['original_site_url'] ?? '' ) );
		if ( '' !== $orig && self::short_url( $orig ) !== self::short_url( home_url() ) ) {
			$foreign = $orig;
		}
		$prefix = (string) ( $ctx['original_prefix'] ?? '' );
		return array(
			'id'      => $r['id'],
			'title'   => FLXY_Storage::title( $r ) . ' · ' . flxy_date( $r['created'] ),
			'db'      => $r['db'] && ( '' === $prefix || $prefix === $wpdb->base_prefix ),
			'parts'   => $this->restore_parts( $r ),
			'foreign' => $foreign,
			'prefix'  => '' !== $prefix && $prefix !== $wpdb->base_prefix ? array( $prefix, $wpdb->base_prefix ) : null,
		);
	}

	/** "https://ornek.org/" → "ornek.org" */
	public static function short_url( string $url ): string {
		return untrailingslashit( preg_replace( '#^https?://(www\.)?#i', '', $url ) );
	}

	/** Geri yükleme penceresindeki seçenekler: gruplar halinde. */
	private function restore_parts( array $r ): array {
		$c      = FLXY_Backup::content_rel();
		$groups = array(
			'plugins' => array(
				'label' => __( 'Eklentiler', 'flx-backup' ),
				'items' => array(),
			),
			'themes'  => array(
				'label' => __( 'Temalar', 'flx-backup' ),
				'items' => array(),
			),
			'other'   => array(
				'label' => __( 'Diğer klasörler', 'flx-backup' ),
				'items' => array(),
			),
		);
		$items = $r['items'] ? $r['items'] : $r['paths'];
		$names = array();
		foreach ( (array) ( $r['context']['packages'] ?? array() ) as $p ) {
			$names[ $p['slug'] ] = $p['name'];
		}
		foreach ( $items as $p ) {
			if ( preg_match( '#^' . preg_quote( $c, '#' ) . '/(plugins|themes)/([^/]+)$#', $p, $m ) ) {
				$slug = preg_replace( '/\.php$/', '', $m[2] );
				$groups[ $m[1] ]['items'][] = array(
					'path'  => $p,
					'label' => ! empty( $names[ $slug ] ) ? $names[ $slug ] . ' (' . $slug . ')' : $slug,
				);
			} elseif ( in_array( $p, array( $c . '/plugins', $c . '/themes' ), true ) ) {
				continue; // Tekil paketler ayrı listeleniyor; "tümü" için wp-content seçilir.
			} elseif ( $p === $c ) {
				array_unshift(
					$groups['other']['items'],
					array(
						'path'  => $p,
						'label' => __( 'wp-content (tamamı)', 'flx-backup' ),
					)
				);
			} elseif ( false === strpos( $p, '/' ) && ! in_array( $p, array( 'wp-admin', 'wp-includes' ), true ) ) {
				continue;
			} else {
				$groups['other']['items'][] = array(
					'path'  => $p,
					'label' => $p,
				);
			}
		}
		return array_values(
			array_filter(
				$groups,
				static function ( $g ) {
					return (bool) $g['items'];
				}
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Ayarlar                                                             */
	/* ------------------------------------------------------------------ */

	public function render_settings(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-backup' ), 403 );
		}
		$s    = FLXY_Settings::all();
		$days = array(
			1 => __( 'Pazartesi', 'flx-backup' ),
			2 => __( 'Salı', 'flx-backup' ),
			3 => __( 'Çarşamba', 'flx-backup' ),
			4 => __( 'Perşembe', 'flx-backup' ),
			5 => __( 'Cuma', 'flx-backup' ),
			6 => __( 'Cumartesi', 'flx-backup' ),
			0 => __( 'Pazar', 'flx-backup' ),
		);
		?>
		<div class="wrap flxy-wrap">
			<?php $this->header( 'settings' ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxy-settings">
				<input type="hidden" name="action" value="flxy_save_settings">
				<?php wp_nonce_field( 'flxy_save_settings' ); ?>

				<section class="flxy-card">
					<h2><?php esc_html_e( 'Depolama', 'flx-backup' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="flxy-quota"><?php esc_html_e( 'Kota', 'flx-backup' ); ?></label></th>
							<td>
								<input type="number" id="flxy-quota" name="quota_gb" min="0.1" step="0.1" value="<?php echo esc_attr( (string) $s['quota_gb'] ); ?>" class="small-text"> GB
								<p class="description"><?php esc_html_e( 'Yedeklerin toplam boyutu bunu aşmaz: yeni yedekten önce en eski kilitsiz yedekler silinir. Kilitli yedekler asla otomatik silinmez; yer açılamazsa yedek alınmaz.', 'flx-backup' ); ?></p>
								<p class="description">
									<?php
									/* translators: 1: used, 2: directory */
									echo esc_html( sprintf( __( 'Şu an %1$s kullanılıyor. Klasör: %2$s', 'flx-backup' ), size_format( FLXY_Storage::used_bytes(), 1 ), FLXY_Backup::rel( FLXY_Storage::dir() ) ) );
									?>
								</p>
							</td>
						</tr>
					</table>
				</section>

				<section class="flxy-card">
					<h2><?php esc_html_e( 'Güncelleme öncesi yedek', 'flx-backup' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Kapsam', 'flx-backup' ); ?></th>
							<td>
								<fieldset>
									<label><input type="radio" name="pre_scope" value="all" <?php checked( 'all', $s['pre_scope'] ); ?>> <?php esc_html_e( 'Tüm eklenti ve tema güncellemeleri', 'flx-backup' ); ?></label><br>
									<label><input type="radio" name="pre_scope" value="flx" <?php checked( 'flx', $s['pre_scope'] ); ?>> <?php esc_html_e( 'Yalnız FluxWP paketleri (flx-… eklentileri ve FluxWP temaları)', 'flx-backup' ); ?></label><br>
									<label><input type="radio" name="pre_scope" value="off" <?php checked( 'off', $s['pre_scope'] ); ?>> <?php esc_html_e( 'Kapalı', 'flx-backup' ); ?></label>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Elle, toplu ve otomatik güncellemelerde paket kurulmadan hemen önce veritabanı ve güncellenen paketin klasörü yedeklenir. Toplu güncellemede tek yedek alınır.', 'flx-backup' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="flxy-pre-keep"><?php esc_html_e( 'Saklama süresi', 'flx-backup' ); ?></label></th>
							<td><input type="number" id="flxy-pre-keep" name="pre_keep_days" min="1" max="365" value="<?php echo esc_attr( (string) $s['pre_keep_days'] ); ?>" class="small-text"> <?php esc_html_e( 'gün', 'flx-backup' ); ?>
								<p class="description"><?php esc_html_e( 'Güncelleme sorunsuz geçtiyse bu süre sonunda silinir. Saklamak istediğinizi listede kilitleyin.', 'flx-backup' ); ?></p></td>
						</tr>
					</table>
				</section>

				<section class="flxy-card">
					<h2><?php esc_html_e( 'Zamanlanmış tam yedek', 'flx-backup' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="flxy-schedule"><?php esc_html_e( 'Sıklık', 'flx-backup' ); ?></label></th>
							<td>
								<select id="flxy-schedule" name="schedule">
									<option value="off" <?php selected( 'off', $s['schedule'] ); ?>><?php esc_html_e( 'Kapalı', 'flx-backup' ); ?></option>
									<option value="daily" <?php selected( 'daily', $s['schedule'] ); ?>><?php esc_html_e( 'Her gün', 'flx-backup' ); ?></option>
									<option value="weekly" <?php selected( 'weekly', $s['schedule'] ); ?>><?php esc_html_e( 'Her hafta', 'flx-backup' ); ?></option>
								</select>
								<span class="flxy-weekly">
									<select name="schedule_day" aria-label="<?php esc_attr_e( 'Gün', 'flx-backup' ); ?>">
										<?php foreach ( $days as $n => $label ) : ?>
											<option value="<?php echo esc_attr( (string) $n ); ?>" <?php selected( $n, (int) $s['schedule_day'] ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</span>
								<select name="schedule_hour" aria-label="<?php esc_attr_e( 'Saat', 'flx-backup' ); ?>">
									<?php for ( $h = 0; $h < 24; $h++ ) : ?>
										<option value="<?php echo esc_attr( (string) $h ); ?>" <?php selected( $h, (int) $s['schedule_hour'] ); ?>><?php echo esc_html( sprintf( '%02d:00', $h ) ); ?></option>
									<?php endfor; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Site saat dilimine göre. Gece saatlerini seçin; WP-Cron ziyaretle tetiklendiği için birkaç dakika gecikebilir.', 'flx-backup' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="flxy-keep"><?php esc_html_e( 'Saklanacak sayı', 'flx-backup' ); ?></label></th>
							<td><input type="number" id="flxy-keep" name="schedule_keep" min="1" max="100" value="<?php echo esc_attr( (string) $s['schedule_keep'] ); ?>" class="small-text">
								<p class="description"><?php esc_html_e( 'Zamanlanmış yedeklerden son bu kadarı tutulur (kilitliler sayılmaz).', 'flx-backup' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'WordPress çekirdeği', 'flx-backup' ); ?></th>
							<td><label><input type="checkbox" name="include_core" value="1" <?php checked( $s['include_core'] ); ?>> <?php esc_html_e( 'Tam yedeklere wp-admin, wp-includes ve kök dosyaları (wp-config.php dahil) ekle', 'flx-backup' ); ?></label>
								<p class="description"><?php esc_html_e( 'Çekirdek her zaman wordpress.org\'dan yeniden indirilebilir; kapalı bırakmak yer kazandırır.', 'flx-backup' ); ?></p></td>
						</tr>
					</table>
				</section>

				<section class="flxy-card">
					<h2><?php esc_html_e( 'Hariç tutulanlar', 'flx-backup' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="flxy-exclude"><?php esc_html_e( 'Ek yollar', 'flx-backup' ); ?></label></th>
							<td>
								<textarea id="flxy-exclude" name="exclude" rows="5" class="large-text code" placeholder="wp-content/uploads/videolar&#10;wp-content/uploads/*.log"><?php echo esc_textarea( (string) $s['exclude'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Her satıra bir yol, WordPress kök klasörüne göre. * joker karakteri kullanılabilir.', 'flx-backup' ); ?></p>
								<p class="description"><?php esc_html_e( 'Her zaman hariç: yedek klasörü, wp-snapshots, diğer yedek eklentilerinin klasörleri (ai1wm-backups, updraft, wpvividbackups, backups-dup-*), cache, litespeed, upgrade, upgrade-temp-backup.', 'flx-backup' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<section class="flxy-card">
					<h2><?php esc_html_e( 'Geri yükleme ve kaldırma', 'flx-backup' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Güvenlik yedeği', 'flx-backup' ); ?></th>
							<td><label><input type="checkbox" name="safety_restore" value="1" <?php checked( $s['safety_restore'] ); ?>> <?php esc_html_e( 'Veritabanını geri yüklemeden önce mevcut veritabanını yedekle (7 gün saklanır)', 'flx-backup' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Eklenti silinince', 'flx-backup' ); ?></th>
							<td><label><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( $s['delete_on_uninstall'] ); ?>> <?php esc_html_e( 'Yedek dosyalarını da sil', 'flx-backup' ); ?></label>
								<p class="description"><?php esc_html_e( 'Kapalıysa (varsayılan) eklenti silindiğinde yalnız ayarlar silinir, zip dosyaları klasörde kalır.', 'flx-backup' ); ?></p></td>
						</tr>
					</table>
				</section>

				<?php submit_button( __( 'Ayarları kaydet', 'flx-backup' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function save_settings(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-backup' ), 403 );
		}
		check_admin_referer( 'flxy_save_settings' );
		$old_keep = (int) FLXY_Settings::get( 'pre_keep_days' );
		$saved    = FLXY_Settings::save( wp_unslash( $_POST ) );
		if ( (int) $saved['pre_keep_days'] !== $old_keep ) {
			FLXY_Settings::apply_pre_keep_days();
			FLXY_Cron::cleanup();
		}
		FLXY_Cron::reschedule();
		FLXY_Storage::sample();
		wp_safe_redirect( self::url( 'settings', array( 'flxy_msg' => 'saved' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                */
	/* ------------------------------------------------------------------ */

	private function ajax_guard(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Yetkiniz yok.', 'flx-backup' ) ), 403 );
		}
		check_ajax_referer( 'flxy', 'nonce' );
	}

	public function ajax_start(): void {
		$this->ajax_guard();
		$type = sanitize_key( wp_unslash( $_POST['type'] ?? 'full' ) );
		if ( ! in_array( $type, array( 'full', 'db', 'files' ), true ) ) {
			$type = 'full';
		}
		$args = array(
			'type'        => $type,
			'label'       => sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ),
			'context'     => array(
				'source' => 'manual',
				'user'   => get_current_user_id(),
			),
			'interactive' => false, // Arka planda: loopback + cron + açık sayfalardaki gösterge sürer.
		);
		if ( 'full' === $type ) {
			$args['include_core'] = ! empty( $_POST['include_core'] );
		}
		if ( ! empty( $_POST['lock'] ) ) {
			$args['keep_days'] = null;
		}
		$rec = FLXY_Backup::create( $args );
		if ( is_wp_error( $rec ) ) {
			wp_send_json_error( array( 'message' => $rec->get_error_message() ) );
		}
		wp_send_json_success( FLXY_Runner::report( FLXY_Runner::current() ) );
	}

	public function ajax_restore_start(): void {
		$this->ajax_guard();
		$id    = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		$paths = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['paths'] ?? array() ) );
		$state = FLXY_Restore::start(
			$id,
			array(
				'db'     => ! empty( $_POST['db'] ),
				'paths'  => $paths,
				'safety' => ! empty( $_POST['safety'] ),
			),
			false
		);
		if ( is_wp_error( $state ) ) {
			wp_send_json_error( array( 'message' => $state->get_error_message() ) );
		}
		wp_send_json_success( FLXY_Runner::report( $state ) );
	}

	public function ajax_delete(): void {
		$this->ajax_guard();
		$ids     = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['ids'] ?? array() ) );
		$deleted = array();
		foreach ( $ids as $id ) {
			if ( FLXY_Storage::delete( $id ) ) {
				$deleted[] = $id;
			}
		}
		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Silinemedi.', 'flx-backup' ) ) );
		}
		wp_send_json_success(
			array(
				'deleted' => $deleted,
				'total'   => size_format( FLXY_Storage::used_bytes(), 1 ),
			)
		);
	}

	public function ajax_lock(): void {
		$this->ajax_guard();
		$id   = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		$lock = ! empty( $_POST['lock'] );
		$rec  = FLXY_Storage::get( $id );
		if ( ! $rec ) {
			wp_send_json_error( array( 'message' => __( 'Yedek bulunamadı.', 'flx-backup' ) ) );
		}
		FLXY_Storage::patch( $id, array( 'locked' => $lock ) );
		$rec = FLXY_Storage::get( $id );
		if ( $lock ) {
			$text = __( 'Kilitli', 'flx-backup' );
		} elseif ( $rec['keep_until'] ) {
			/* translators: %s: date */
			$text = sprintf( __( '%s tarihine kadar', 'flx-backup' ), flxy_date( $rec['keep_until'], 'date' ) );
		} else {
			$text = __( 'Kota dolana kadar', 'flx-backup' );
		}
		wp_send_json_success(
			array(
				'locked' => $lock,
				'text'   => $text,
				'title'  => $lock ? __( 'Kilitli: otomatik silinmez. Kilidi açmak için tıklayın.', 'flx-backup' ) : __( 'Kilitle: kota ve saklama süresi bu yedeği silmez.', 'flx-backup' ),
			)
		);
	}

	public function ajax_cancel(): void {
		$this->ajax_guard();
		$res = FLXY_Runner::cancel();
		if ( false === $res ) {
			wp_send_json_error( array( 'message' => __( 'Çalışan bir işlem yok.', 'flx-backup' ) ) );
		}
		// 'pending': adım sürüyor, adım bitince iptal edilecek.
		wp_send_json_success( array( 'pending' => 'pending' === $res ) );
	}

	/* ------------------------------------------------------------------ */
	/* İndirme                                                             */
	/* ------------------------------------------------------------------ */

	public function download(): void {
		$id = sanitize_text_field( wp_unslash( $_GET['id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-backup' ), 403 );
		}
		check_admin_referer( 'flxy_download_' . $id );
		$r = FLXY_Storage::get( $id );
		if ( ! $r || 'ok' !== $r['status'] || ! FLXY_Storage::is_inside( $r['file'] ) || ! is_readable( $r['file'] ) ) {
			wp_die( esc_html__( 'Yedek bulunamadı.', 'flx-backup' ), 404 );
		}
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$name = sanitize_file_name( 'flx-backup-' . $host . '-' . str_replace( '_', '-', $r['type'] ) . '-' . gmdate( 'Ymd-His', $r['created'] ) . '.zip' );
		$size = (int) filesize( $r['file'] );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . $size );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Accel-Buffering: no' );
		$fh = fopen( $r['file'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		while ( $fh && ! feof( $fh ) && ! connection_aborted() ) {
			echo fread( $fh, 1048576 ); // phpcs:ignore WordPress.Security.EscapeOutput,WordPress.WP.AlternativeFunctions
			flush();
		}
		if ( $fh ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		exit;
	}
}
