<?php
/**
 * Paket ikonlarının sitede önbelleği.
 *
 * Sunucu ikonları imzalı adresle verir (?expires=…&sig=…); imza her katalog kontrolünde yenilendiği için
 * adres değişir, tarayıcı önbelleği işe yaramaz: "Güncellemeleri kontrol et" deyince tüm ikonlar yeniden
 * iner, yanıp söner, sunucu yavaşsa hiç gelmez. Dosya adı içerikten türetilir (/v1/media/<slug>/<hash>.svg: depo + git blob sha),
 * yani içerik değişmeden adı değişmez. İkonlar bu ada göre uploads/flx-setup/icons/ altına indirilir ve
 * oradan sabit adresle gösterilir:
 * - eldeki ikon kalır; yenisi gelince (hash değişince) indirilir ve hemen o gösterilir;
 * - yenisi henüz inemediyse eskisi gösterilir, hiç yoksa sunucu adresi kullanılır.
 *
 * @package flx-setup
 */

defined( 'ABSPATH' ) || exit;

final class FLXU_Icon_Cache {

	/** Paket başına son iyi ikon dosyası: [slug][svg|pastel] => dosya adı. */
	const OPTION = 'flxu_icon_cache';

	const MAX_BYTES = 262144;

	/** @var array|null */
	private static $map = null;

	public static function init(): void {
		add_action( 'flxu_catalog_updated', array( __CLASS__, 'warm' ), 10, 1 );
	}

	/** uploads/flx-setup/icons */
	private static function dir(): array {
		$up = wp_upload_dir( null, false );
		if ( ! empty( $up['error'] ) ) {
			return array( '', '' );
		}
		return array( trailingslashit( $up['basedir'] ) . 'flx-setup/icons', set_url_scheme( trailingslashit( $up['baseurl'] ) . 'flx-setup/icons' ) );
	}

	private static function map(): array {
		if ( null === self::$map ) {
			$m         = get_site_option( self::OPTION, array() );
			self::$map = is_array( $m ) ? $m : array();
		}
		return self::$map;
	}

	/** Sunucu adresindeki içerik kimliği: <64 hex>.<uzantı>. */
	public static function file_id( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return preg_match( '#/v1/media/[a-z0-9][a-z0-9-]{0,63}/([a-f0-9]{64}\.(?:svg|png|webp|jpg))$#D', $path, $m ) ? $m[1] : '';
	}

	/**
	 * Gösterilecek adres: önbellekteki aynı içerik > aynı paketin eski ikonu > sunucu adresi.
	 */
	public static function url( string $slug, string $kind, string $remote ): string {
		list( $dir, $base ) = self::dir();
		if ( '' === $dir ) {
			return $remote;
		}
		$id = self::file_id( $remote );
		if ( '' !== $id && is_file( $dir . '/' . $id ) ) {
			return $base . '/' . $id;
		}
		$old = (string) ( self::map()[ $slug ][ $kind ] ?? '' );
		if ( '' !== $old && is_file( $dir . '/' . $old ) ) {
			return $base . '/' . $old;
		}
		return $remote;
	}

	/**
	 * Katalogdaki ikonları indir (eksik olanları). Süre sınırlı; kalanlar sonraki istekte iner.
	 *
	 * @param array $catalog Katalog.
	 */
	public static function warm( $catalog, float $budget = 8.0 ): void {
		if ( ! is_array( $catalog ) || ! $catalog ) {
			return;
		}
		list( $dir ) = self::dir();
		if ( '' === $dir || ! self::prepare( $dir ) ) {
			return;
		}
		$start = microtime( true );
		$map   = self::map();
		$keep  = array();
		$dirty = false;
		foreach ( $catalog as $slug => $p ) {
			foreach ( array( 'svg', 'pastel' ) as $kind ) {
				$remote = (string) ( $p['icons'][ $kind ] ?? '' );
				$id     = '' !== $remote ? self::file_id( $remote ) : '';
				if ( '' === $id ) {
					if ( isset( $map[ $slug ][ $kind ] ) ) {
						$keep[ $map[ $slug ][ $kind ] ] = true;
					}
					continue;
				}
				$keep[ $id ] = true;
				if ( ! is_file( $dir . '/' . $id ) ) {
					if ( microtime( true ) - $start > $budget || ! self::download( $remote, $dir . '/' . $id ) ) {
						// İnemedi: eski ikon silinmesin, gösterilmeye devam etsin.
						if ( isset( $map[ $slug ][ $kind ] ) ) {
							$keep[ $map[ $slug ][ $kind ] ] = true;
						}
						continue;
					}
				}
				if ( ( $map[ $slug ][ $kind ] ?? '' ) !== $id ) {
					$map[ $slug ][ $kind ] = $id;
					$dirty                 = true;
				}
			}
		}
		if ( $dirty ) {
			self::$map = $map;
			update_site_option( self::OPTION, $map );
		}
		// Artık kullanılmayan dosyalar.
		$files = array();
		foreach ( array( 'svg', 'png', 'webp', 'jpg' ) as $ext ) { // GLOB_BRACE yok: musl/Alpine PHP'de tanımsız.
			$files = array_merge( $files, glob( $dir . '/*.' . $ext ) ?: array() );
		}
		foreach ( $files as $f ) {
			if ( is_string( $f ) && ! isset( $keep[ basename( $f ) ] ) && ! in_array( basename( $f ), self::all_mapped( $map ), true ) ) {
				wp_delete_file( $f );
			}
		}
	}

	/** Paketler ekranı açılırken eksik ikon varsa kısa süreyle indir. */
	public static function warm_missing(): void {
		$cat = get_site_option( 'flxu_catalog_last', array() );
		if ( ! is_array( $cat ) ) {
			return;
		}
		if ( get_site_transient( 'flxu_icon_warm_wait' ) ) {
			return;
		}
		list( $dir ) = self::dir();
		foreach ( $cat as $p ) {
			foreach ( array( 'svg', 'pastel' ) as $kind ) {
				$id = self::file_id( (string) ( $p['icons'][ $kind ] ?? '' ) );
				if ( '' !== $id && '' !== $dir && ! is_file( $dir . '/' . $id ) ) {
					// Sunucu yanıt vermiyorsa her sayfa açılışında beklemesin.
					set_site_transient( 'flxu_icon_warm_wait', 1, 10 * MINUTE_IN_SECONDS );
					self::warm( $cat, 4.0 );
					return;
				}
			}
		}
	}

	private static function all_mapped( array $map ): array {
		$out = array();
		foreach ( $map as $kinds ) {
			foreach ( (array) $kinds as $f ) {
				$out[] = (string) $f;
			}
		}
		return $out;
	}

	private static function prepare( string $dir ): bool {
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		if ( ! is_file( $dir . '/index.php' ) ) {
			@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
		}
		if ( ! is_file( $dir . '/.htaccess' ) ) {
			// Doğrudan açılan SVG'de betik çalışmasın (img etiketinde zaten çalışmaz).
			@file_put_contents( $dir . '/.htaccess', "<IfModule mod_headers.c>\nHeader set Content-Security-Policy \"default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox\"\nHeader set X-Content-Type-Options nosniff\n</IfModule>\n" ); // phpcs:ignore
		}
		return is_writable( $dir );
	}

	/** İndir, doğrula (güvenli SVG / gerçek resim), yaz. */
	private static function download( string $url, string $dest ): bool {
		$res = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 5,
				'redirection'         => 1,
				'limit_response_size' => self::MAX_BYTES + 1,
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return false;
		}
		$body = (string) wp_remote_retrieve_body( $res );
		if ( '' === $body || strlen( $body ) > self::MAX_BYTES ) {
			return false;
		}
		$id = basename( $dest );
		if ( ! self::safe( $body, pathinfo( $id, PATHINFO_EXTENSION ) ) ) {
			return false;
		}
		$tmp = $dest . '.' . wp_generate_password( 6, false ) . '.tmp';
		if ( false === @file_put_contents( $tmp, $body ) ) { // phpcs:ignore
			return false;
		}
		return @rename( $tmp, $dest ) || ( wp_delete_file( $tmp ) && false ); // phpcs:ignore
	}

	public static function safe( string $body, string $ext ): bool {
		if ( 'svg' === $ext ) {
			if ( false === stripos( $body, '<svg' ) ) {
				return false;
			}
			return ! preg_match( '#<\s*(script|foreignObject|iframe|embed|object)\b|\bon[a-z]+\s*=|javascript:|<!ENTITY#i', $body );
		}
		$magic = array(
			'png'  => "\x89PNG",
			'jpg'  => "\xFF\xD8\xFF",
			'webp' => 'RIFF',
		);
		return isset( $magic[ $ext ] ) && 0 === strpos( $body, $magic[ $ext ] );
	}
}
