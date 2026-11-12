<?php
/**
 * FluxWP kaynaklarından (güncelleme sunucusu / GitHub) paket indirme.
 *
 * WordPress indirme hatasında yalnız "Download failed." (TR "Dosya indirilemedi.") yazar; asıl neden
 * (HTTP kodu, sunucunun yanıtı, disk dolu, zaman aşımı) kaybolur. Bu sınıf upgrader_pre_download ile
 * FluxWP kaynaklarına giden indirmeyi üstlenir:
 * - indirmeden önce geçici klasörün yazılabilir olduğuna ve boş yere bakar,
 * - geçici hatada (zaman aşımı, bağlantı, 408/425/429/5xx) birkaç kez bekleyip yeniden dener,
 * - başarısızlıkta nedeni ve ne yapılacağını yazan bir hata döndürür (HTTP kodu + sunucu mesajı).
 */

defined( 'ABSPATH' ) || exit;

final class FLXU_Download {

	/** Toplam deneme sayısı. */
	const ATTEMPTS = 3;

	/** Denemeler arası bekleme (saniye). */
	const WAITS = array( 2, 5 );

	const MAX_WAIT = 10;

	/** Bu kadar saniye geçtiyse yeniden denenmez (paylaşımlı barındırmada PHP ~60 sn'de kesilir). */
	const RETRY_BUDGET = 25;

	/** Geçici klasörde istenen en az boş yer (bayt): paket + açılmış kopyası. */
	const MIN_FREE = 50 * 1024 * 1024;

	public static function init(): void {
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'pre_download' ), 20, 4 );
	}

	/**
	 * @param false|string|WP_Error $reply
	 * @param string                $package
	 * @return false|string|WP_Error
	 */
	public static function pre_download( $reply, $package, $upgrader = null, $hook_extra = array() ) {
		if ( false !== $reply || ! is_string( $package ) || ! self::is_ours( $package ) ) {
			return $reply;
		}
		if ( $upgrader instanceof WP_Upgrader && $upgrader->skin ) {
			$upgrader->skin->feedback( 'downloading_package', $package );
		}
		return self::fetch( $package );
	}

	/** Paket adresi FluxWP kaynaklarından birinde mi (sunucu ya da GitHub kaynağı). */
	public static function is_ours( string $url ): bool {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $host || ! class_exists( 'FLXU_Sources' ) ) {
			return false;
		}
		foreach ( FLXU_Sources::all() as $s ) {
			if ( is_array( $s ) && FLXU_Sources::host( $s ) === $host ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * İndirir; geçici hatada yeniden dener.
	 *
	 * @return string|WP_Error Geçici dosya yolu.
	 */
	public static function fetch( string $url ) {
		$space = self::check_space();
		if ( is_wp_error( $space ) ) {
			return $space;
		}
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$last  = null;
		$start = microtime( true );
		for ( $i = 0; $i < self::ATTEMPTS; $i++ ) {
			if ( $i > 0 ) {
				$wait = self::WAITS[ min( $i - 1, count( self::WAITS ) - 1 ) ];
				if ( microtime( true ) - $start + $wait > self::RETRY_BUDGET ) {
					break;
				}
				sleep( (int) min( self::MAX_WAIT, $wait ) );
			}
			$file = download_url( $url, 300 );
			if ( ! is_wp_error( $file ) ) {
				$bad = self::check_zip( $file );
				if ( ! is_wp_error( $bad ) ) {
					return $file;
				}
				@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$last = $bad;
				continue;
			}
			$last = self::describe( $file );
			if ( ! self::transient( $last ) ) {
				break;
			}
		}
		self::log( $url, $last );
		return $last;
	}

	/** Geçici klasör yazılabilir ve yeterince boş mu. */
	private static function check_space() {
		$dir = get_temp_dir();
		if ( ! wp_is_writable( $dir ) ) {
			/* translators: %s: folder */
			return new WP_Error( 'flxu_tmp', sprintf( __( 'Download failed: the temporary folder (%s) is not writable. Ask your host to check folder permissions.', 'flx-setup' ), $dir ) );
		}
		$free = function_exists( 'disk_free_space' ) ? @disk_free_space( $dir ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false !== $free && $free < self::MIN_FREE ) {
			return new WP_Error(
				'flxu_disk',
				/* translators: %s: free space, e.g. 12 MB */
				sprintf( __( 'Download failed: the disk is full (%s free). Delete old backups (Flux Backup) or other large files, then try again.', 'flx-setup' ), size_format( (int) $free ) )
			);
		}
		return true;
	}

	/** İnen dosya gerçekten zip mi (proxy/WAF sayfası, yarım dosya). */
	private static function check_zip( string $file ) {
		$size = (int) @filesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$head = (string) @file_get_contents( $file, false, null, 0, 2 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		if ( $size > 0 && 'PK' === $head ) {
			return true;
		}
		if ( 0 === $size ) {
			$space = self::check_space();
			if ( is_wp_error( $space ) ) {
				return $space;
			}
		}
		return new WP_Error(
			'flxu_not_zip',
			/* translators: %s: size */
			sprintf( __( 'Download failed: the file that arrived is not a zip (%s). A firewall or proxy may be changing the response, or the disk may be full.', 'flx-setup' ), size_format( $size ) ),
			array( 'transient' => true )
		);
	}

	/** download_url hatasını açıklayıcı hataya çevirir. */
	private static function describe( WP_Error $e ): WP_Error {
		$data = $e->get_error_data();
		$data = is_array( $data ) ? $data : array();
		$code = (int) ( $data['code'] ?? 0 );
		$body = (string) ( $data['body'] ?? '' );

		// Sunucunun JSON hata mesajı: {"ok":false,"error":"…","code":"…"}.
		$server = '';
		$json   = json_decode( $body, true );
		if ( is_array( $json ) && isset( $json['error'] ) ) {
			$server = (string) $json['error'];
		} elseif ( '' !== trim( $body ) ) {
			$server = wp_html_excerpt( trim( wp_strip_all_tags( $body ) ), 120, '…' );
		}

		if ( 0 === $code ) {
			$raw = $e->get_error_message();
			if ( preg_match( '/no space|disk quota|failed to write|failure writing|could not write|write error|could not create temporary/i', $raw ) ) {
				/* translators: %s: error */
				return new WP_Error( 'flxu_disk', sprintf( __( 'Download failed: the file could not be saved, the disk may be full (%s). Delete old backups (Flux Backup) or other large files, then try again.', 'flx-setup' ), $raw ) );
			}
			if ( preg_match( '/timed out|timeout/i', $raw ) ) {
				/* translators: %s: error */
				$msg = sprintf( __( 'Download failed: the connection timed out (%s).', 'flx-setup' ), $raw );
			} else {
				/* translators: %s: error */
				$msg = sprintf( __( 'Download failed: the server could not be reached (%s).', 'flx-setup' ), $raw );
			}
			return new WP_Error( 'flxu_http', $msg, array( 'transient' => true ) );
		}

		if ( 401 === $code || 403 === $code ) {
			$hint = __( 'The site key was refused or this package is not allowed for it. Check the key in FluxWP Panel › Settings.', 'flx-setup' );
		} elseif ( 404 === $code ) {
			$hint = __( 'This version is no longer on the server. Click "Check for updates" and try again.', 'flx-setup' );
		} elseif ( 429 === $code ) {
			$hint = __( 'Too many requests in a short time. Wait a minute and try again.', 'flx-setup' );
		} elseif ( $code >= 500 ) {
			$hint = __( 'The update server had a temporary error. Try again in a minute.', 'flx-setup' );
		} else {
			$hint = '';
		}
		$detail = '' !== $server ? $server : $e->get_error_message();
		/* translators: 1: HTTP status code, 2: server message, 3: hint */
		$msg = trim( sprintf( __( 'Download failed (HTTP %1$d: %2$s). %3$s', 'flx-setup' ), $code, $detail, $hint ) );

		$transient = in_array( $code, array( 408, 425, 429 ), true ) || $code >= 500;
		return new WP_Error(
			'flxu_http_' . $code,
			$msg,
			array(
				'code'        => $code,
				'transient'   => $transient,
			)
		);
	}

	private static function transient( WP_Error $e ): bool {
		$d = $e->get_error_data();
		return is_array( $d ) && ! empty( $d['transient'] );
	}

	/** Hata günlüğüne (debug.log) adres anahtarsız yazılır. */
	private static function log( string $url, $err ): void {
		if ( ! is_wp_error( $err ) ) {
			return;
		}
		$clean = strtok( $url, '?' );
		error_log( '[flx-setup] download ' . $clean . ': ' . $err->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}
}
