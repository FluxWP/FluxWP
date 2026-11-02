<?php
/**
 * Arka plandaki tek işi (yedek veya geri yükleme) adım adım yürütür.
 *
 * - Yedek işinin durumu flxy_job seçeneğinde durur.
 * - Geri yükleme işinin durumu depolama klasöründeki .restore-job.json dosyasındadır: veritabanı geri yüklenirken
 *   wp_options tablosu silinip yeniden kurulur, seçenekte duran durum kaybolurdu.
 * - Adımları sürenler: yönetim ekranının AJAX yoklaması, WP-Cron (dakikada bir yedek olay) ve kendi kendine
 *   engellemeyen loopback isteği. Aynı anda iki adım çalışmasın diye depolama klasöründe flock kilidi kullanılır.
 * - Kimlik doğrulama: yönetici + nonce, ya da işe özel rastgele belirteç (veritabanı geri yüklenince oturum
 *   çerezleri geçersizleşebilir; yoklama belirteçle devam eder).
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_Runner {

	const OPTION     = 'flxy_job';
	const CRON_TICK  = 'flxy_job_tick';
	const RESTORE_FN = '.restore-job.json';
	const FLAG       = 'flxy_indicator';

	/** @var resource|null */
	private static $lock = null;

	public static function init(): void {
		add_action( 'wp_ajax_flxy_step', array( __CLASS__, 'ajax_step' ) );
		add_action( 'wp_ajax_nopriv_flxy_step', array( __CLASS__, 'ajax_step' ) );
		add_action( 'wp_ajax_flxy_status', array( __CLASS__, 'ajax_step' ) );
		add_action( 'wp_ajax_nopriv_flxy_status', array( __CLASS__, 'ajax_step' ) );
		add_action( 'wp_ajax_flxy_seen', array( __CLASS__, 'ajax_seen' ) );
		add_action( 'wp_ajax_flxy_tick', array( __CLASS__, 'ajax_tick' ) );
		add_action( 'wp_ajax_nopriv_flxy_tick', array( __CLASS__, 'ajax_tick' ) );
		add_action( self::CRON_TICK, array( __CLASS__, 'cron_tick' ) );
	}

	/** Bir adımın süre bütçesi (saniye). */
	public static function budget( bool $interactive = false ): float {
		$max    = (int) ini_get( 'max_execution_time' );
		$budget = $interactive ? 8.0 : 20.0;
		if ( $max > 0 ) {
			$budget = min( $budget, max( 3, $max - 8 ) );
		}
		/**
		 * Bir adımın süresi (saniye). Hostinger'de 20 sn güvenlidir; tarayıcıdan sürülen adım 8 sn.
		 *
		 * @param float $budget
		 * @param bool  $interactive Yönetim ekranından yoklanıyor mu.
		 */
		return (float) apply_filters( 'flxy_step_seconds', $budget, $interactive );
	}

	/* ------------------------------------------------------------------ */
	/* Durum saklama                                                       */
	/* ------------------------------------------------------------------ */

	private static function restore_file(): string {
		return FLXY_Storage::dir() . '/' . self::RESTORE_FN;
	}

	public static function load_restore(): ?array {
		$f = self::restore_file();
		if ( ! file_exists( $f ) ) {
			return null;
		}
		$data = json_decode( (string) file_get_contents( $f ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return is_array( $data ) ? $data : null;
	}

	public static function save_restore( ?array $state ): void {
		$f = self::restore_file();
		if ( null === $state ) {
			if ( file_exists( $f ) ) {
				wp_delete_file( $f );
			}
			return;
		}
		$tmp = $f . '.tmp';
		file_put_contents( $tmp, wp_json_encode( $state ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		rename( $tmp, $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	public static function load_backup(): ?array {
		wp_cache_delete( self::OPTION, 'options' );
		$s = get_option( self::OPTION, null );
		return is_array( $s ) ? $s : null;
	}

	public static function save_backup( ?array $state ): void {
		if ( null === $state ) {
			delete_option( self::OPTION );
			return;
		}
		update_option( self::OPTION, $state, false );
	}

	/** Etkin iş (geri yükleme önce). */
	public static function current(): ?array {
		$r = self::load_restore();
		if ( $r ) {
			return $r;
		}
		return self::load_backup();
	}

	private static function save( array $state ): void {
		if ( 'restore' === $state['kind'] ) {
			self::save_restore( $state );
		} else {
			self::save_backup( $state );
		}
	}

	private static function is_finished( array $state ): bool {
		return in_array( $state['phase'], array( 'done', 'failed' ), true );
	}

	/** Çalışan (bitmemiş) bir iş var mı. */
	public static function busy(): bool {
		$s = self::current();
		return $s && ! self::is_finished( $s );
	}

	public static function job_is_for( string $id ): bool {
		$s = self::load_backup();
		return $s && ! self::is_finished( $s ) && ( $s['id'] ?? '' ) === $id;
	}

	/* ------------------------------------------------------------------ */
	/* Başlatma                                                            */
	/* ------------------------------------------------------------------ */

	/** @return true|WP_Error */
	public static function start( array $state, bool $interactive = false ) {
		if ( self::busy() ) {
			return new WP_Error( 'flxy_busy', __( 'Şu anda başka bir yedekleme veya geri yükleme çalışıyor. Bitmesini bekleyin.', 'flx-backup' ) );
		}
		// Bitmiş eski işi temizle.
		self::save_backup( null );
		if ( 'restore' !== $state['kind'] ) {
			self::save_restore( null );
		}
		if ( file_exists( self::cancel_file() ) ) {
			wp_delete_file( self::cancel_file() );
		}
		$state['token']   = wp_generate_password( 32, false, false );
		$state['touched'] = time();
		self::save( $state );
		self::flag( $state );
		self::schedule_tick();
		if ( ! $interactive ) {
			self::spawn( $state['token'] );
		}
		return true;
	}

	private static function schedule_tick(): void {
		if ( ! wp_next_scheduled( self::CRON_TICK ) ) {
			wp_schedule_single_event( time() + 60, self::CRON_TICK );
		}
	}

	/** Engellemeyen loopback: bir sonraki adımı ayrı bir istekte başlatır. */
	public static function spawn( string $token ): void {
		/** Loopback kapatılabilir (yalnız cron ve yönetim ekranı sürer). */
		if ( ! apply_filters( 'flxy_use_loopback', true ) ) {
			return;
		}
		wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'body'      => array(
					'action' => 'flxy_tick',
					'token'  => $token,
				),
				'cookies'   => array(),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Adım                                                                */
	/* ------------------------------------------------------------------ */

	private static function lock(): bool {
		if ( ! FLXY_Storage::ensure() ) {
			return false;
		}
		$fh = fopen( FLXY_Storage::dir() . '/.lock', 'c' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return false;
		}
		if ( ! flock( $fh, LOCK_EX | LOCK_NB ) ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return false;
		}
		self::$lock = $fh;
		return true;
	}

	private static function unlock(): void {
		if ( self::$lock ) {
			flock( self::$lock, LOCK_UN );
			fclose( self::$lock ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			self::$lock = null;
		}
	}

	/**
	 * Etkin işi bir adım ilerletir. Kilit başkasındaysa dokunmadan döner.
	 *
	 * @return array|null Güncel durum.
	 */
	public static function run_step( bool $interactive = false ): ?array {
		$state = self::current();
		if ( ! $state || self::is_finished( $state ) ) {
			return $state;
		}
		if ( ! self::lock() ) {
			return $state;
		}
		// Kilit alındıktan sonra taze oku.
		$state = self::current();
		if ( ! $state || self::is_finished( $state ) ) {
			self::unlock();
			return $state;
		}
		if ( self::cancel_requested( $state ) ) {
			$state = self::apply_cancel( $state );
			self::unlock();
			return $state;
		}
		FLXY_Backup::raise_limits();
		ignore_user_abort( true );
		$deadline = microtime( true ) + self::budget( $interactive );
		if ( 'restore' === $state['kind'] ) {
			$state = FLXY_Restore::step( $state, $deadline );
		} else {
			$state = FLXY_Backup::step( $state, $deadline );
		}
		$state['touched'] = time();
		self::save( $state );
		if ( ! self::is_finished( $state ) && self::cancel_requested( $state ) ) {
			$state = self::apply_cancel( $state );
		}
		self::unlock();
		if ( ! self::is_finished( $state ) ) {
			self::schedule_tick();
		} else {
			self::flag( $state );
		}
		return $state;
	}

	/* ------------------------------------------------------------------ */
	/* Yönetim çubuğu göstergesi bayrağı                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * Göstergenin ucuz bayrağı (otomatik yüklenen küçük seçenek): sayfa yüklenirken iş var mı diye
	 * iş durumunu okumadan karar verilir. flxy_* olduğu için veritabanı geri yüklemesinde korunur.
	 */
	private static function flag( array $state ): void {
		$status = 'done' === $state['phase'] ? 'done' : ( 'failed' === $state['phase'] ? 'failed' : 'running' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( self::FLAG, 'options' );
		update_option(
			self::FLAG,
			array(
				'kind'   => $state['kind'],
				'id'     => $state['id'] ?? '',
				'status' => $status,
				'at'     => time(),
				'seen'   => ! empty( $state['cancelled'] ),
			),
			true
		);
	}

	/** @return array|null */
	public static function get_flag(): ?array {
		$f = get_option( self::FLAG, null );
		return is_array( $f ) && ! empty( $f['status'] ) ? $f : null;
	}

	/** Gösterge gösterilmeli mi: çalışan iş ya da son 30 dakikada bitmiş ve henüz kapatılmamış iş. */
	public static function flag_active(): bool {
		$f = self::get_flag();
		if ( ! $f ) {
			return false;
		}
		if ( 'running' === $f['status'] ) {
			// Uzun süredir "çalışıyor" görünüyorsa işin gerçekten sürdüğünü doğrula (yarım kalmış iş göstergeyi kilitlemesin).
			return time() - (int) $f['at'] < 10 * MINUTE_IN_SECONDS || self::busy();
		}
		return empty( $f['seen'] ) && time() - (int) $f['at'] < 30 * MINUTE_IN_SECONDS;
	}

	public static function mark_seen(): void {
		$f = self::get_flag();
		if ( $f && 'running' !== $f['status'] ) {
			$f['seen'] = true;
			update_option( self::FLAG, $f, true );
		}
	}

	/** Durum + ilerleme (ekran için). */
	public static function report( ?array $state ): array {
		if ( ! $state ) {
			return array( 'status' => 'idle' );
		}
		$p       = 'restore' === $state['kind'] ? FLXY_Restore::progress( $state ) : FLXY_Backup::progress( $state );
		$status  = 'done' === $state['phase'] ? 'done' : ( 'failed' === $state['phase'] ? 'failed' : 'running' );
		$started = (int) ( $state['started'] ?? 0 );
		$end     = 'running' === $status ? time() : (int) ( $state['touched'] ?? time() );
		$elapsed = $started ? max( 0, $end - $started ) : 0;
		$eta     = null;
		if ( 'running' === $status && $p['percent'] >= 5 && $elapsed >= 15 ) {
			$eta = (int) round( $elapsed * ( 100 - $p['percent'] ) / max( 1, $p['percent'] ) );
		}
		$id   = (string) ( $state['id'] ?? '' );
		$rec  = $id ? FLXY_Storage::get( $id ) : null;
		$flag = self::get_flag();
		$out  = array(
			'status'    => $status,
			'kind'      => $state['kind'],
			'id'        => $id,
			'phase'     => $state['phase'],
			'percent'   => $p['percent'],
			'pct'       => FLXY_Indicator::pct( (int) $p['percent'] ),
			'text'      => $p['text'],
			'error'     => $state['error'] ?? '',
			'token'     => $state['token'] ?? '',
			'cancelled' => ! empty( $state['cancelled'] ),
			'elapsed'   => $elapsed,
			'elapsed_h' => self::duration( $elapsed ),
			'eta'       => $eta,
			'eta_h'     => null === $eta ? '' : self::duration( $eta ),
			'title'     => $rec ? FLXY_Storage::title( $rec ) : (string) ( $state['title'] ?? '' ),
			'size'      => $rec ? (int) $rec['size'] : 0,
			'size_h'    => $rec && $rec['size'] ? size_format( $rec['size'], 1 ) : '',
			'url'       => $id ? admin_url( 'admin.php?page=flx-backup-list&view=' . rawurlencode( $id ) ) : '',
			'seen'      => $flag && ( $flag['id'] ?? '' ) === $id ? ! empty( $flag['seen'] ) : false,
		);
		return $out;
	}

	/** "3 dk 12 sn" biçiminde kısa süre. */
	public static function duration( int $sec ): string {
		if ( $sec < 60 ) {
			/* translators: %s: seconds */
			return sprintf( __( '%s sn', 'flx-backup' ), number_format_i18n( $sec ) );
		}
		if ( $sec < HOUR_IN_SECONDS ) {
			/* translators: 1: minutes, 2: seconds */
			return sprintf( __( '%1$s dk %2$s sn', 'flx-backup' ), number_format_i18n( intdiv( $sec, 60 ) ), number_format_i18n( $sec % 60 ) );
		}
		/* translators: 1: hours, 2: minutes */
		return sprintf( __( '%1$s sa %2$s dk', 'flx-backup' ), number_format_i18n( intdiv( $sec, 3600 ) ), number_format_i18n( intdiv( $sec % 3600, 60 ) ) );
	}

	private static function cancel_file(): string {
		return FLXY_Storage::dir() . '/.cancel';
	}

	/**
	 * İşi iptal eder (yarım dosyalar silinir). Adım o anda çalışıyorsa iptal isteği bırakılır ve adım
	 * bitince uygulanır.
	 *
	 * @return bool|string true, 'pending' ya da false (iş yok).
	 */
	public static function cancel() {
		$state = self::current();
		if ( ! $state || self::is_finished( $state ) ) {
			return false;
		}
		if ( ! self::lock() ) {
			file_put_contents( self::cancel_file(), (string) ( $state['token'] ?? '' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return 'pending';
		}
		$state = self::current();
		if ( ! $state || self::is_finished( $state ) ) {
			self::unlock();
			return false;
		}
		self::apply_cancel( $state );
		self::unlock();
		return true;
	}

	/** Kilit tutulurken çağrılır. */
	private static function apply_cancel( array $state ): array {
		if ( file_exists( self::cancel_file() ) ) {
			wp_delete_file( self::cancel_file() );
		}
		if ( 'restore' === $state['kind'] ) {
			$state = FLXY_Restore::fail( $state, __( 'İptal edildi.', 'flx-backup' ) );
		} else {
			$state = FLXY_Backup::fail( $state, __( 'İptal edildi.', 'flx-backup' ) );
		}
		$state['cancelled'] = true;
		self::save( $state );
		self::flag( $state );
		return $state;
	}

	/** Bekleyen iptal isteği bu işe mi ait. */
	private static function cancel_requested( array $state ): bool {
		$f = self::cancel_file();
		if ( ! file_exists( $f ) ) {
			return false;
		}
		$tok = (string) file_get_contents( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( '' !== $tok && $tok !== (string) ( $state['token'] ?? '' ) ) {
			wp_delete_file( $f ); // Eski bir işten kalmış.
			return false;
		}
		return true;
	}

	private static function token_ok( ?array $state ): bool {
		$token = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return $state && '' !== $token && ! empty( $state['token'] ) && hash_equals( (string) $state['token'], $token );
	}

	/** Yönetim ekranının yoklaması: bir adım çalıştırır ve durumu döndürür. */
	public static function ajax_step(): void {
		$state  = self::current();
		$authed = self::token_ok( $state );
		if ( ! $authed && current_user_can( 'manage_options' ) && check_ajax_referer( 'flxy', 'nonce', false ) ) {
			$authed = true;
		}
		if ( ! $authed ) {
			// Bitmiş geri yüklemenin sonucunu belirteç olmadan da göstermeyelim.
			wp_send_json_error( array( 'message' => __( 'Oturum doğrulanamadı. Sayfayı yenileyin.', 'flx-backup' ) ), 403 );
		}
		$state = self::run_step( true );
		// Metinler göstergenin bulunduğu sayfanın dilinde olsun (ön yüzde sitenin dili geçerli olabilir).
		$locale = isset( $_REQUEST['locale'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['locale'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $locale && preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/', $locale ) && determine_locale() !== $locale ) {
			switch_to_locale( $locale );
		}
		$out = self::report( $state );
		if ( $state && 'done' === $state['phase'] && 'restore' === $state['kind'] ) {
			$out['result'] = $state['result'] ?? '';
		}
		if ( $state && 'backup' === $state['kind'] && self::is_finished( $state ) ) {
			$out['record'] = FLXY_Storage::get( $state['id'] );
		}
		wp_send_json_success( $out );
	}

	/** Bitmiş işin göstergesini kapatır. */
	public static function ajax_seen(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'flxy', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		self::mark_seen();
		wp_send_json_success();
	}

	/** Loopback: belirteçle bir adım, sonra gerekirse zinciri sürdürür. */
	public static function ajax_tick(): void {
		$state = self::current();
		if ( ! self::token_ok( $state ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$state = self::run_step( false );
		if ( $state && ! self::is_finished( $state ) ) {
			self::spawn( $state['token'] );
		}
		wp_die( '', '', array( 'response' => 200 ) );
	}

	public static function cron_tick(): void {
		$state = self::run_step( false );
		if ( $state && ! self::is_finished( $state ) ) {
			self::spawn( $state['token'] );
		}
	}
}
