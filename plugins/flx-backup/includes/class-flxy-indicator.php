<?php
/**
 * Yönetim çubuğu göstergesi: çalışan yedek/geri yükleme işinin ilerlemesi, bitince sonucu.
 *
 * Her sayfada yalnızca otomatik yüklenen küçük bir bayrağa (flxy_indicator) bakılır; iş yoksa hiçbir dosya
 * yüklenmez. İş varsa küçük bir betik durumu birkaç saniyede bir yoklar; yoklama aynı zamanda işin bir sonraki
 * adımını çalıştırır (Hostinger loopback isteklerini engellese bile iş, açık herhangi bir yönetim sayfasıyla
 * ilerler). Aynı anda iki adım çalışmasını depolama klasöründeki kilit önler.
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_Indicator {

	const CAP = 'manage_options';

	/** @var bool|null */
	private static $show = null;

	public static function init(): void {
		add_action( 'admin_bar_menu', array( __CLASS__, 'node' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	private static function on_own_page(): bool {
		if ( ! is_admin() ) {
			return false;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return 0 === strpos( $page, 'flx-backup' );
	}

	/** Gösterge bu sayfada olmalı mı (ucuz: yalnız otomatik yüklenen bayrak). */
	public static function should_show(): bool {
		if ( null === self::$show ) {
			self::$show = is_user_logged_in()
				&& current_user_can( self::CAP )
				&& is_admin_bar_showing()
				&& ( FLXY_Runner::flag_active() || self::on_own_page() );
		}
		return self::$show;
	}

	/** Sayfa açılırken gösterilecek ilk durum (iş varsa). */
	private static function initial(): ?array {
		if ( ! FLXY_Runner::flag_active() ) {
			return null;
		}
		$state = FLXY_Runner::current();
		if ( ! $state ) {
			return null;
		}
		$r = FLXY_Runner::report( $state );
		if ( 'running' !== $r['status'] && ! empty( $r['seen'] ) ) {
			return null;
		}
		return $r;
	}

	/** @param WP_Admin_Bar $bar */
	public static function node( $bar ): void {
		if ( ! self::should_show() ) {
			return;
		}
		$switched = self::user_locale();
		$r        = self::initial();
		$class    = 'flxy-ab';
		$text  = '';
		$pct   = 0;
		if ( $r ) {
			$class .= ' is-' . $r['status'];
			$pct    = (int) $r['percent'];
			$text   = self::label( $r );
		} else {
			$class .= ' is-idle';
		}
		$bar->add_node(
			array(
				'id'     => 'flxy-job',
				'parent' => 'top-secondary',
				'title'  => '<span class="flxy-ab-ring" aria-hidden="true"><svg viewBox="0 0 20 20" width="20" height="20"><circle class="flxy-ab-track" cx="10" cy="10" r="8"/><circle class="flxy-ab-val" cx="10" cy="10" r="8" pathLength="100" style="stroke-dasharray:' . $pct . ' 100"/></svg><svg class="flxy-ab-glyph" viewBox="0 0 20 20" width="10" height="10"><path d="M10 3v9m-4-4 4 4 4-4M4 16h12"/></svg></span><span class="flxy-ab-text">' . esc_html( $text ) . '</span><span class="flxy-ab-bar" aria-hidden="true"><span style="width:' . $pct . '%"></span></span>',
				'href'   => admin_url( 'admin.php?page=flx-backup-list' ),
				'meta'   => array(
					'class' => $class,
					'title' => __( 'Flux Backup: ayrıntılar için tıklayın', 'flx-backup' ),
				),
			)
		);
		if ( $switched ) {
			restore_previous_locale();
		}
	}

	/**
	 * Ön yüzde sitenin dili, yönetimde kullanıcının dili geçerlidir; göstergenin metinleri (ve yoklamanın döndürdüğü
	 * admin-ajax metinleri) her yerde kullanıcının dilinde olsun.
	 */
	private static function user_locale(): bool {
		if ( is_admin() || ! function_exists( 'switch_to_user_locale' ) ) {
			return false;
		}
		return (bool) switch_to_user_locale( get_current_user_id() );
	}

	/** Gösterge metni ("Yedek alınıyor %87" …). */
	public static function label( array $r ): string {
		$restore = 'restore' === $r['kind'];
		if ( 'running' === $r['status'] ) {
			/* translators: %s: percent */
			return sprintf( $restore ? __( 'Geri yükleniyor %s', 'flx-backup' ) : __( 'Yedek alınıyor %s', 'flx-backup' ), self::pct( (int) $r['percent'] ) );
		}
		if ( 'done' === $r['status'] ) {
			if ( $restore ) {
				return __( 'Geri yükleme tamamlandı', 'flx-backup' );
			}
			/* translators: %s: size */
			return '' !== $r['size_h'] ? sprintf( __( 'Yedek hazır · %s', 'flx-backup' ), $r['size_h'] ) : __( 'Yedek hazır', 'flx-backup' );
		}
		return $restore ? __( 'Geri yükleme başarısız', 'flx-backup' ) : __( 'Yedek alınamadı', 'flx-backup' );
	}

	/** Türkçede yüzde işareti sayının önünde: %87; İngilizcede 87%. */
	public static function pct( int $n ): string {
		/* translators: %s: number. Turkish puts the percent sign first (%87); English: 87% */
		return sprintf( _x( '%%%s', 'percent', 'flx-backup' ), number_format_i18n( $n ) );
	}

	public static function assets(): void {
		if ( ! self::should_show() ) {
			return;
		}
		wp_enqueue_style( 'flxy-bar', FLXY_URL . 'assets/bar.css', array(), FLXY_VERSION );
		wp_enqueue_script( 'flxy-bar', FLXY_URL . 'assets/bar.js', array(), FLXY_VERSION, true );
		$switched = self::user_locale();
		$r        = self::initial();
		wp_localize_script(
			'flxy-bar',
			'FLXYBarConfig',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'flxy' ),
				'job'     => $r,
				'listUrl' => admin_url( 'admin.php?page=flx-backup-list' ),
				'locale'  => determine_locale(),
				'own'     => self::on_own_page(),
				'i18n'    => array(
					'backup'        => __( 'Yedek alınıyor %s', 'flx-backup' ),
					'restore'       => __( 'Geri yükleniyor %s', 'flx-backup' ),
					'pct'           => _x( '%%%s', 'percent', 'flx-backup' ),
					'ready'         => __( 'Yedek hazır · %s', 'flx-backup' ),
					'readyNoSize'   => __( 'Yedek hazır', 'flx-backup' ),
					'restored'      => __( 'Geri yükleme tamamlandı', 'flx-backup' ),
					'backupFailed'  => __( 'Yedek alınamadı', 'flx-backup' ),
					'restoreFailed' => __( 'Geri yükleme başarısız', 'flx-backup' ),
					'cancelled'     => __( 'İptal edildi', 'flx-backup' ),
					'cancelling'    => __( 'İptal ediliyor…', 'flx-backup' ),
					'starting'      => __( 'Başlatılıyor…', 'flx-backup' ),
					'elapsed'       => __( 'Geçen süre: %s', 'flx-backup' ),
					'eta'           => __( 'Kalan: yaklaşık %s', 'flx-backup' ),
					'cancel'        => __( 'İptal et', 'flx-backup' ),
					'details'       => __( 'Ayrıntılar', 'flx-backup' ),
					'close'         => __( 'Kapat', 'flx-backup' ),
					'reload'        => __( 'Sayfayı yenile', 'flx-backup' ),
					'confirmCancel' => __( 'Çalışan işlem iptal edilsin mi?', 'flx-backup' ),
					'retry'         => __( 'Bağlantı kesildi, yeniden deneniyor…', 'flx-backup' ),
					'backupTitle'   => __( 'Yedek alma', 'flx-backup' ),
					'restoreTitle'  => __( 'Geri yükleme', 'flx-backup' ),
					'bgNote'        => __( 'Sayfadan ayrılabilirsiniz; işlem arka planda sürer.', 'flx-backup' ),
					'restoreNote'   => __( 'Geri yükleme sürerken siteyi değiştirmeyin.', 'flx-backup' ),
				),
			)
		);
		if ( $switched ) {
			restore_previous_locale();
		}
	}
}
