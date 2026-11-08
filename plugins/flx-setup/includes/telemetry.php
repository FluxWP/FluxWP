<?php
/**
 * Anonim kullanım verisi (telemetri). Yalnız "Ürünü geliştirmemize yardımcı olun" kutusu işaretliyse çalışır:
 * get_option( 'flxk_telemetry' ) === '1' (seçeneği sihirbaz ve FluxWP Panel › Ayarlar yazar, varsayılan kapalı).
 *
 * Gönderilen (günde en fazla bir kez, telemetri adresine):
 *   - kuruluma özel rastgele kimlik (güncelleme anahtarı ve kurulum kimliğiyle ilişkisiz; kutu kapatılınca silinir),
 *   - WordPress sürümü, PHP ana sürümü (ör. 8.2), site dili, ülke kodu (site saat diliminden ya da
 *     WooCommerce mağaza ülkesinden; IP'ye bakılmaz), çoklu site mi,
 *   - etkin FluxWP eklentileri ve FluxWP teması (slug => sürüm),
 *   - FluxWP dosyalarından kaynaklanan PHP hata/uyarıları: tür, temizlenmiş mesaj, paket içindeki göreli dosya yolu,
 *     satır, tekrar sayısı.
 * Mesajlardan sunucu yolları, alan adları, URL, e-posta, IP ve uzun sayılar silinir.
 *
 *   - kurulum sihirbazı kullanımı (son çalıştırma): hangi adıma kadar gelindi, hangi kartlar seçildi/bırakıldı,
 *     ön ayar ve renk stili, bitirildi/atlandı/yarıda bırakıldı, yaklaşık süre.
 * Gönderilmeyen: site adresi, IP, kullanıcı, e-posta, içerik, FluxWP dışı eklentiler, güncelleme anahtarı.
 *
 * Hedef: Flux Analytics "Telemetri merkezi" açık bir site (varsayılan fluxwp.com). Adres FluxWP Panel › Ayarlar'dan
 * değişir (option flxk_telemetry_url), böylece başkası kendi Flux Analytics sitesini merkez yapabilir.
 *
 * Filtreler: flxk_telemetry_enabled (bool), flxk_telemetry_url (string), flxk_telemetry_payload (array).
 */

defined( 'ABSPATH' ) || exit;

final class FLXK_Telemetry {

	const OPTION = 'flxk_telemetry';
	const ID     = 'flxk_telemetry_id';
	const QUEUE  = 'flxk_telemetry_queue';
	const LAST   = 'flxk_telemetry_last';
	const HOOK   = 'flxk_telemetry_send';
	const URL    = 'flxk_telemetry_url';
	const FUNNEL = 'flxk_telemetry_funnel';
	const NONCE  = 'flxk_tm';

	const DEFAULT_URL = 'https://fluxwp.com/wp-json/flxan/v1/telemetry';

	/** Kuyrukta tutulan en fazla farklı hata. */
	const MAX_ERRORS = 50;

	/** Aynı hatanın sayacı en sık bu aralıkla veritabanına yazılır (sn). */
	const WRITE_EVERY = 600;

	const FATAL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

	/** @var array|null */
	private static $queue = null;

	/** @var bool */
	private static $dirty = false;

	/** @var bool */
	private static $fresh = false;

	/** @var callable|null */
	private static $prev = null;

	/** @var bool */
	private static $busy = false;

	/** @var int Bu istekte yakalanan farklı hata (aşırı yükü önler). */
	private static $seen = 0;

	/** @var array|null */
	private static $roots = null;

	public static function init(): void {
		add_action( 'added_option', array( __CLASS__, 'option_changed' ) );
		add_action( 'updated_option', array( __CLASS__, 'option_changed' ) );
		add_action( 'deleted_option', array( __CLASS__, 'option_changed' ) );
		add_action( self::HOOK, array( __CLASS__, 'send' ) );
		add_filter( 'http_request_args', array( __CLASS__, 'strip_request' ), 999, 2 );
		add_action( 'wp_ajax_flxk_tm_funnel', array( __CLASS__, 'ajax_funnel' ) );
		add_action( 'flxu_settings_after', array( __CLASS__, 'settings_card' ) );
		add_action( 'admin_post_flxk_telemetry', array( __CLASS__, 'admin_post' ) );

		if ( ! self::enabled() ) {
			// Kutu kapalıyken bekleyen gönderim kalmasın (kapatma başka yoldan olduysa).
			if ( wp_next_scheduled( self::HOOK ) ) {
				self::purge();
			}
			return;
		}

		self::$prev = set_error_handler( array( __CLASS__, 'on_error' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		register_shutdown_function( array( __CLASS__, 'on_shutdown' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	public static function enabled(): bool {
		return (bool) apply_filters( 'flxk_telemetry_enabled', '1' === (string) get_option( self::OPTION, '' ) );
	}

	/** Kayıtlı adres (boşsa varsayılan). Yalnız https. */
	public static function url(): string {
		$u = (string) get_option( self::URL, '' );
		if ( '' === $u || 'https' !== wp_parse_url( $u, PHP_URL_SCHEME ) ) {
			$u = self::DEFAULT_URL;
		}
		return (string) apply_filters( 'flxk_telemetry_url', $u );
	}

	/* ------------------------------------------------------------------ */
	/* Açma / kapama                                                       */
	/* ------------------------------------------------------------------ */

	public static function option_changed( $name ): void {
		if ( self::OPTION !== $name ) {
			return;
		}
		if ( self::enabled() ) {
			self::schedule();
		} else {
			self::purge();
		}
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS + wp_rand( 0, 6 * HOUR_IN_SECONDS ), 'daily', self::HOOK );
		}
	}

	/** Kutu kapatıldı: zamanlama, kuyruk ve rastgele kimlik silinir (yeniden açılırsa yeni kimlik üretilir). */
	public static function purge(): void {
		wp_clear_scheduled_hook( self::HOOK );
		delete_option( self::QUEUE );
		delete_option( self::ID );
		delete_option( self::LAST );
		delete_option( self::FUNNEL );
		self::$queue = array();
		self::$dirty = false;
	}

	/* ------------------------------------------------------------------ */
	/* Hata yakalama                                                       */
	/* ------------------------------------------------------------------ */

	public static function on_error( $no, $str, $file = '', $line = 0 ) {
		if ( ! self::$busy && ( error_reporting() & $no ) ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			self::$busy = true;
			try {
				$src = self::source( (string) $file );
				if ( $src ) {
					self::capture( (int) $no, (string) $str, $src, (int) $line );
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			}
			self::$busy = false;
		}
		if ( self::$prev ) {
			return call_user_func( self::$prev, $no, $str, $file, $line );
		}
		return false; // PHP'nin kendi işleyicisi devam etsin.
	}

	public static function on_shutdown(): void {
		if ( self::$busy ) {
			return;
		}
		self::$busy = true;
		try {
			$e = error_get_last();
			if ( $e && ( (int) $e['type'] & self::FATAL ) ) {
				$src  = self::source( (string) $e['file'] );
				$line = (int) $e['line'];
				if ( ! $src ) {
					// WordPress çekirdeğinde patlayan ama yığında FluxWP dosyası olan ölümcül hata.
					$src = self::source_from_trace( (string) $e['message'], $line );
				}
				if ( $src ) {
					self::capture( (int) $e['type'], (string) $e['message'], $src, $line );
				}
			}
			self::persist();
		} catch ( \Throwable $ex ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		}
		self::$busy = false;
	}

	/** Dosya bir FluxWP paketine aitse array( slug, tür, göreli yol ). */
	public static function source( string $file ): ?array {
		if ( '' === $file ) {
			return null;
		}
		$file = wp_normalize_path( $file );
		foreach ( self::roots() as $type => $root ) {
			if ( 0 !== strpos( $file, $root ) ) {
				continue;
			}
			$rel  = substr( $file, strlen( $root ) );
			$slug = strtok( $rel, '/' );
			if ( $slug && self::is_flux( $slug, $type ) ) {
				return array( $slug, $type, $rel );
			}
		}
		return null;
	}

	private static function source_from_trace( string $msg, int &$line ): ?array {
		if ( ! preg_match_all( '#(?:^|\s)(/\S+?|[A-Za-z]:\\\\\S+?)\((\d+)\)#', $msg, $m, PREG_SET_ORDER ) ) {
			return null;
		}
		foreach ( $m as $hit ) {
			$src = self::source( $hit[1] );
			if ( $src ) {
				$line = (int) $hit[2];
				return $src;
			}
		}
		return null;
	}

	private static function roots(): array {
		if ( null === self::$roots ) {
			self::$roots = array(
				'plugin' => trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ),
				'theme'  => trailingslashit( wp_normalize_path( get_theme_root() ) ),
			);
		}
		return self::$roots;
	}

	private static function is_flux( string $slug, string $type ): bool {
		if ( 'theme' === $type ) {
			return 'fluxwp' === $slug || 0 === strpos( $slug, 'fluxwp-' ) || 0 === strpos( $slug, 'flx-' );
		}
		if ( 0 === strpos( $slug, 'flx-' ) ) {
			return true;
		}
		static $known = null;
		if ( null === $known ) {
			$known = array_map( 'strval', (array) get_site_option( 'flxu_known_slugs', array() ) );
		}
		return in_array( $slug, $known, true );
	}

	private static function capture( int $no, string $msg, array $src, int $line ): void {
		list( $slug, $type, $rel ) = $src;
		$msg = self::scrub( $msg );
		$key = md5( $slug . '|' . $no . '|' . $rel . '|' . $line . '|' . preg_replace( '/\d+/', '0', $msg ) );
		$q   = self::queue();
		if ( isset( $q[ $key ] ) ) {
			$q[ $key ]['n'] = min( 1000000, (int) $q[ $key ]['n'] + 1 );
		} else {
			if ( count( array_diff_key( $q, array( '_w' => 0 ) ) ) >= self::MAX_ERRORS || self::$seen >= 20 ) {
				return;
			}
			++self::$seen;
			$q[ $key ]   = array(
				'p' => $slug,
				'k' => $type,
				'v' => self::version( $slug, $type ),
				't' => self::type_name( $no ),
				'm' => $msg,
				'f' => substr( $rel, 0, 200 ),
				'l' => $line,
				'n' => 1,
			);
			self::$fresh = true;
		}
		self::$queue = $q;
		self::$dirty = true;
	}

	private static function queue(): array {
		if ( null === self::$queue ) {
			$q           = get_option( self::QUEUE, array() );
			self::$queue = is_array( $q ) ? $q : array();
		}
		return self::$queue;
	}

	private static function persist(): void {
		if ( ! self::$dirty ) {
			return;
		}
		$q = self::queue();
		if ( ! self::$fresh && time() - (int) ( $q['_w'] ?? 0 ) < self::WRITE_EVERY ) {
			return;
		}
		$q['_w'] = time();
		update_option( self::QUEUE, $q, false );
		self::$dirty = false;
		self::$fresh = false;
	}

	private static function version( string $slug, string $type ): string {
		if ( 'theme' === $type ) {
			$t = wp_get_theme( $slug );
			return $t->exists() ? (string) $t->get( 'Version' ) : '';
		}
		$file = WP_PLUGIN_DIR . '/' . $slug . '/' . $slug . '.php';
		if ( ! is_readable( $file ) ) {
			return '';
		}
		$d = get_file_data( $file, array( 'v' => 'Version' ) );
		return substr( (string) $d['v'], 0, 32 );
	}

	public static function type_name( int $no ): string {
		$map = array(
			E_ERROR             => 'E_ERROR',
			E_WARNING           => 'E_WARNING',
			E_PARSE             => 'E_PARSE',
			E_NOTICE            => 'E_NOTICE',
			E_CORE_ERROR        => 'E_CORE_ERROR',
			E_CORE_WARNING      => 'E_CORE_WARNING',
			E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
			E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
			E_USER_ERROR        => 'E_USER_ERROR',
			E_USER_WARNING      => 'E_USER_WARNING',
			E_USER_NOTICE       => 'E_USER_NOTICE',
			E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
			E_DEPRECATED        => 'E_DEPRECATED',
			E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
		);
		return $map[ $no ] ?? 'E_UNKNOWN';
	}

	/**
	 * Mesajı kişisel ve siteye özgü bilgiden temizler: kurulum yolları göreli olur, diğer mutlak yollar, URL'ler,
	 * alan adları, e-postalar, IP'ler ve 6+ haneli sayılar yer tutucuya döner. Yığın izinin ilk 8 satırı kalır.
	 */
	public static function scrub( string $msg ): string {
		$msg = str_replace( '\\', '/', $msg );
		$map = array();
		foreach ( self::roots() as $type => $root ) {
			$map[ $root ] = '{' . $type . 's}/';
		}
		$map[ trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ) ] = '{content}/';
		$map[ trailingslashit( wp_normalize_path( ABSPATH ) ) ]        = '{wp}/';
		uksort(
			$map,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		$msg = str_replace( array_keys( $map ), array_values( $map ), $msg );

		$hosts = array();
		foreach ( array( home_url(), site_url() ) as $u ) {
			$host = strtolower( (string) wp_parse_url( $u, PHP_URL_HOST ) );
			if ( '' !== $host ) {
				$hosts[] = $host;
				$hosts[] = preg_replace( '/^www\./', '', $host );
				$hosts[] = 'www.' . preg_replace( '/^www\./', '', $host );
			}
		}
		$hosts = array_unique( $hosts );
		usort(
			$hosts,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		foreach ( $hosts as $host ) {
			$msg = str_ireplace( $host, '{domain}', $msg );
		}
		$rules = array(
			'#\b[a-z][a-z0-9+.-]*://\S+#i'                                    => '{url}',
			'#[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}#i'                         => '{email}',
			'#\b(?:\d{1,3}\.){3}\d{1,3}\b#'                                    => '{ip}',
			'#\b(?:[0-9a-f]{1,4}:){3,7}[0-9a-f]{1,4}\b#i'                      => '{ip}',
			'#(?<![\w{}])(?:/|[A-Za-z]:/)(?:[^\s/:\'"()]+/)+#'                 => '{path}/',
			'#\b(?:[a-z0-9-]+\.)+(?!php\b|js\b|css\b|json\b|html?\b|txt\b|xml\b|svg\b|png\b|jpe?g\b|gif\b|webp\b|zip\b|log\b|md\b|inc\b|tmp\b|lock\b|mo\b|po\b)[a-z]{2,24}\b#i' => '{domain}',
			'#\d{6,}#'                                                         => '{n}',
		);
		$msg   = preg_replace( array_keys( $rules ), array_values( $rules ), $msg );
		$lines = preg_split( '/\R/', trim( (string) $msg ) );
		$msg   = implode( "\n", array_slice( $lines, 0, 9 ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $msg, 0, 1500 ) : substr( $msg, 0, 1500 );
	}

	/* ------------------------------------------------------------------ */
	/* Gönderim                                                            */
	/* ------------------------------------------------------------------ */

	/** Rastgele kurulum kimliği (yalnız telemetri için). */
	private static function id(): string {
		$id = (string) get_option( self::ID, '' );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
			$id = bin2hex( random_bytes( 16 ) );
			update_option( self::ID, $id, false );
		}
		return $id;
	}

	/** Etkin FluxWP eklentileri ve teması: slug => sürüm. */
	public static function packages(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$all  = get_plugins();
		$out  = array();
		foreach ( array_unique( $active ) as $file ) {
			$slug = dirname( (string) $file );
			if ( '.' !== $slug && isset( $all[ $file ] ) && self::is_flux( $slug, 'plugin' ) ) {
				$out[ $slug ] = substr( (string) $all[ $file ]['Version'], 0, 32 );
			}
		}
		ksort( $out );
		return $out;
	}

	public static function themes(): array {
		$out = array();
		foreach ( array_unique( array( get_stylesheet(), get_template() ) ) as $slug ) {
			if ( self::is_flux( (string) $slug, 'theme' ) ) {
				$out[ $slug ] = substr( (string) wp_get_theme( $slug )->get( 'Version' ), 0, 32 );
			}
		}
		return $out;
	}

	/**
	 * Ülke kodu (ör. TR), yalnız site ayarlarından: WooCommerce mağaza adresi girilmişse mağaza ülkesi,
	 * yoksa Ayarlar › Genel saat dilimi (Europe/Istanbul → TR). IP'ye bakılmaz; bulunamazsa boş.
	 */
	public static function country(): string {
		$wc    = strtoupper( (string) get_option( 'woocommerce_default_country', '' ) );
		$wc_cc = preg_match( '/^([A-Z]{2})(:|$)/', $wc, $m ) ? $m[1] : '';
		if ( '' !== $wc_cc && '' !== trim( (string) get_option( 'woocommerce_store_address', '' ) ) ) {
			return $wc_cc;
		}
		$tz = (string) get_option( 'timezone_string', '' );
		if ( '' !== $tz ) {
			try {
				$loc = ( new DateTimeZone( $tz ) )->getLocation();
				$cc  = is_array( $loc ) ? strtoupper( (string) ( $loc['country_code'] ?? '' ) ) : '';
				if ( preg_match( '/^[A-Z]{2}$/', $cc ) && '??' !== $cc ) {
					return $cc;
				}
			} catch ( Exception $e ) {
				unset( $e );
			}
			if ( in_array( $tz, array( 'Asia/Istanbul', 'Turkey' ), true ) ) {
				return 'TR';
			}
		}
		return ''; // WooCommerce'in kurulumdaki varsayılanı (US:CA) mağaza adresi girilmeden ülke sayılmaz.
	}

	/** Gönderilecek veri (önizleme için de kullanılır; kimlik üretmez). */
	public static function payload( bool $with_id = true ): array {
		$errors = array();
		foreach ( self::queue() as $k => $e ) {
			if ( '_w' !== $k && is_array( $e ) ) {
				$errors[] = $e;
			}
		}
		return (array) apply_filters(
			'flxk_telemetry_payload',
			array(
				'v'         => 1,
				'id'        => $with_id ? self::id() : '',
				'wp'        => substr( (string) get_bloginfo( 'version' ), 0, 20 ),
				'php'       => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
				'locale'    => substr( (string) get_locale(), 0, 12 ),
				'cc'        => self::country(),
				'multisite' => is_multisite(),
				'panel'     => FLXK_VERSION,
				'plugins'   => self::packages(),
				'themes'    => self::themes(),
				'errors'    => $errors,
				'onboarding' => self::funnel(),
			)
		);
	}

	public static function send(): bool {
		if ( ! self::enabled() ) {
			self::purge();
			return false;
		}
		$res = wp_remote_post(
			self::url(),
			array(
				'timeout'        => 8,
				'redirection'    => 0,
				'user-agent'     => 'FluxWP-Telemetry/1',
				'headers'        => array( 'Content-Type' => 'application/json' ),
				'body'           => wp_json_encode( self::payload() ),
				'flxk_telemetry' => true,
			)
		);
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			return false;
		}
		delete_option( self::QUEUE );
		delete_option( self::FUNNEL );
		self::$queue = array();
		self::$dirty = false;
		update_option( self::LAST, time(), false );
		return true;
	}

	/**
	 * Telemetri isteğinden siteyi tanıtan her şeyi çıkarır: güncelleme anahtarı (FLXU_Updater ekler),
	 * WordPress'in site adresini içeren User-Agent'ı ve çerezler.
	 */
	public static function strip_request( $args, $url ) {
		if ( empty( $args['flxk_telemetry'] ) ) {
			return $args;
		}
		$args['user-agent'] = 'FluxWP-Telemetry/1';
		$args['cookies']    = array();
		$args['headers']    = array( 'Content-Type' => 'application/json' );
		return $args;
	}
	/* ------------------------------------------------------------------ */
	/* Kurulum sihirbazı                                                   */
	/* ------------------------------------------------------------------ */

	/** Sihirbaz sayfasına izleyiciyi ekler (onboarding.php render()). */
	public static function onboarding_script(): void {
		$data = array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( self::NONCE ),
		);
		echo '<script>window.FLXK_TM=' . wp_json_encode( $data ) . ';</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<script src="' . esc_url( FLXK_URL . 'assets/onboarding/telemetry.js?ver=' . FLXK_VERSION ) . '"></script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources
	}

	/** Sihirbazın son çalıştırması (gönderilmeyi bekleyen). */
	public static function funnel(): ?array {
		$f = get_option( self::FUNNEL, null );
		return is_array( $f ) ? $f : null;
	}

	/** Tarayıcıdan gelen sihirbaz özeti: yalnız kutu işaretliyse saklanır. */
	public static function ajax_funnel(): void {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		if ( ! self::enabled() ) {
			wp_send_json_success( array( 'stored' => false ) );
		}
		$in = json_decode( (string) wp_unslash( $_POST['data'] ?? '' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$f  = self::clean_funnel( is_array( $in ) ? $in : array() );
		$f['runs'] = (int) ( self::funnel()['runs'] ?? 0 ) + 1;
		update_option( self::FUNNEL, $f, false );
		wp_send_json_success( array( 'stored' => true ) );
	}

	public static function clean_funnel( array $in ): array {
		$keys = static function ( $list, int $max = 60 ): array {
			$out = array();
			foreach ( array_slice( is_array( $list ) ? $list : array(), 0, $max ) as $k ) {
				if ( is_string( $k ) && preg_match( '/^[a-z0-9][a-z0-9:_-]{0,63}$/', $k ) ) {
					$out[] = $k;
				}
			}
			return array_values( array_unique( $out ) );
		};
		$one = static function ( $v ): string {
			return is_string( $v ) && preg_match( '/^[a-z0-9][a-z0-9:_.-]{0,63}$/i', $v ) ? $v : '';
		};
		$out = in_array( $in['out'] ?? '', array( 'done', 'skip', 'left' ), true ) ? $in['out'] : 'left';
		return array(
			'out'     => $out,
			'max'     => max( 0, min( 50, (int) ( $in['max'] ?? 0 ) ) ),
			'total'   => max( 0, min( 50, (int) ( $in['total'] ?? 0 ) ) ),
			'at'      => $one( $in['at'] ?? '' ),
			'path'    => array_slice( $keys( $in['path'] ?? array(), 30 ), 0, 30 ),
			'picked'  => $keys( $in['picked'] ?? array() ),
			'dropped' => $keys( $in['dropped'] ?? array() ),
			'rec_off' => $keys( $in['rec_off'] ?? array() ),
			'preset'  => $one( $in['preset'] ?? '' ),
			'style'   => $one( $in['style'] ?? '' ),
			'secs'    => max( 0, min( 86400, (int) ( $in['secs'] ?? 0 ) ) ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Ayarlar kartı (FluxWP Panel › Ayarlar)                              */
	/* ------------------------------------------------------------------ */

	public static function settings_card(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$on   = self::enabled();
		$last = (int) get_option( self::LAST, 0 );
		$msg  = isset( $_GET['flxk_tm'] ) ? sanitize_key( wp_unslash( $_GET['flxk_tm'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="card flxk-tm" id="flxk-telemetry" style="max-width:none;margin-top:20px">
			<h2><?php esc_html_e( 'Anonymous usage data', 'flx-setup' ); ?></h2>
			<?php if ( 'sent' === $msg ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Sent.', 'flx-setup' ); ?></p></div>
			<?php elseif ( 'fail' === $msg ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'Could not send. Check the URL and that the Flux Analytics telemetry hub is on at the target site.', 'flx-setup' ); ?></p></div>
			<?php elseif ( 'saved' === $msg ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Saved.', 'flx-setup' ); ?></p></div>
			<?php endif; ?>
			<p>
				<?php if ( $on ) : ?>
					<span class="flxu-badge flxu-badge--ok"><?php esc_html_e( 'On', 'flx-setup' ); ?></span>
					<?php
					echo esc_html(
						$last
							/* translators: %s: süre, ör. "3 saat" */
							? sprintf( __( 'Last sent %s. Sent at most once a day.', 'flx-setup' ), flx_time_ago( $last ) )
							: __( 'Not sent yet. Sent at most once a day.', 'flx-setup' )
					);
					?>
				<?php else : ?>
					<span class="flxu-badge"><?php esc_html_e( 'Off', 'flx-setup' ); ?></span>
					<?php esc_html_e( 'Nothing is sent. Tick "Help us improve the product" to turn it on.', 'flx-setup' ); ?>
				<?php endif; ?>
			</p>
			<p class="description"><?php esc_html_e( 'Sent: a random install ID, WordPress and PHP version, site language, country (from the time zone or store country), active FluxWP plugins and theme, PHP errors from FluxWP (with paths, domain, emails and IPs removed), setup wizard progress and choices. Site address, IP, users and content are never sent.', 'flx-setup' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="flxk_telemetry">
				<?php wp_nonce_field( 'flxk_telemetry' ); ?>
				<table class="form-table" role="presentation"><tr>
					<th scope="row"><label for="flxk-tm-url"><?php esc_html_e( 'Telemetry URL', 'flx-setup' ); ?></label></th>
					<td>
						<input type="url" class="regular-text code" id="flxk-tm-url" name="url" value="<?php echo esc_attr( (string) get_option( self::URL, '' ) ); ?>" placeholder="<?php echo esc_attr( self::DEFAULT_URL ); ?>">
						<p class="description"><?php esc_html_e( 'Leave empty to send to FluxWP. To run your own hub, turn on Flux Analytics › Settings › Telemetry hub on one site and paste that address here. https only.', 'flx-setup' ); ?></p>
					</td>
				</tr></table>
				<p>
					<button class="button button-primary" name="op" value="save"><?php esc_html_e( 'Save', 'flx-setup' ); ?></button>
					<?php if ( $on ) : ?>
						<button class="button" name="op" value="send"><?php esc_html_e( 'Send now', 'flx-setup' ); ?></button>
					<?php endif; ?>
				</p>
			</form>
			<details>
				<summary><?php esc_html_e( 'Show the data that will be sent', 'flx-setup' ); ?></summary>
				<pre style="max-height:360px;overflow:auto;background:#f6f7f7;padding:10px;white-space:pre-wrap"><?php echo esc_html( wp_json_encode( self::payload( false ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
			</details>
		</div>
		<?php
	}

	public static function admin_post(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-setup' ), 403 );
		}
		check_admin_referer( 'flxk_telemetry' );
		$url = esc_url_raw( trim( (string) wp_unslash( $_POST['url'] ?? '' ) ) );
		if ( '' !== $url && 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			$url = '';
		}
		if ( $url === self::DEFAULT_URL ) {
			$url = '';
		}
		update_option( self::URL, $url, false );
		$msg = 'saved';
		if ( 'send' === ( $_POST['op'] ?? '' ) ) {
			$msg = self::send() ? 'sent' : 'fail';
		}
		$back = wp_get_referer() ? wp_get_referer() : admin_url();
		wp_safe_redirect( add_query_arg( 'flxk_tm', $msg, remove_query_arg( 'flxk_tm', $back ) ) . '#flxk-telemetry' );
		exit;
	}
}

FLXK_Telemetry::init();
