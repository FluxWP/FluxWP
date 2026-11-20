<?php
/**
 * Paket imzası (Ed25519): resmi FluxWP paketleri kurulmadan önce doğrulanır.
 *
 * Paket deposunun GitHub Actions'ı her release'e <slug>.zip.sig ekler (bin/sign-package.php, secret
 * FLX_RELEASE_SIGNING_KEY). Gizli anahtar yalnız orada durur: güncelleme sunucusu, onu yöneten panel/anahtar ya da
 * depo listesi ele geçirilse bile imzalı olmayan zip bu siteye kurulmaz.
 *
 * İmzalanan ileti: "flx-sig/1\n<slug>\n<sürüm>\n<zip sha256 hex>". İmza başka pakete ya da başka sürüme taşınamaz.
 * İmza dosyası: {"v":1,"kid":"<16 hex>","sig":"<base64>"}.
 *
 * Zorunlu olduğu yer: varsayılan güncelleme sunucusundan (FLXU_DEFAULT_SERVER) ve github.com/FluxWP depolarından
 * gelen paketler. Kendi sunucunuzdan ya da başka GitHub deposundan gelen paketler değişmez.
 * Acil durumda wp-config.php: define( 'FLXU_SIGNATURES', 'off' );
 */

defined( 'ABSPATH' ) || exit;

final class FLXU_Signature {

	/**
	 * Güvenilen public key'ler: kid (sha256(pk) ilk 16 hex) => base64 public key. Anahtar değişince yenisi eklenir,
	 * eskisi bir sürüm sonra çıkarılır.
	 */
	const KEYS = array(
		'62a6e76c0e412d9d' => 'PxeM00rC+xjZAhAGFGwxk/mHa2lb9AL0rFCw07SiH7E=',
	);

	/**
	 * Güvenilen anahtarlar (KEYS). Liste boş olabilir: çağıran taraf sabit değeri değil tipi görsün diye metot.
	 *
	 * @return array<string,string>
	 */
	public static function keys(): array {
		return self::KEYS;
	}

	/** GitHub'da resmi paket sahibi. */
	const GITHUB_OWNER = 'fluxwp';

	/**
	 * Bu paket adresi için imza zorunlu mu; zorunluysa slug, sürüm ve imza adresi.
	 *
	 * Zorunluluk kaynağa göre: resmi sunucunun host'u ya da github.com/fluxwp/* adresi hangi şema ya da yolla gelirse
	 * gelsin imzalıdır. Kanonik biçime uymayan adres (http, başka yol, slug/etiket uyuşmazlığı) 'reject' döner;
	 * ele geçirilmiş sunucu adresi değiştirerek doğrulamayı atlatamaz.
	 *
	 * @return array{slug:string,version:string,sig:string}|array{reject:true}|null
	 */
	public static function target( string $url ): ?array {
		if ( 'off' === self::mode() || ! self::keys() ) {
			return null;
		}
		$p        = wp_parse_url( $url );
		$scheme   = strtolower( (string) ( $p['scheme'] ?? '' ) );
		$host     = rtrim( strtolower( (string) ( $p['host'] ?? '' ) ), '.' );
		$path     = (string) ( $p['path'] ?? '' );
		$official = strtolower( (string) wp_parse_url( FLXU_DEFAULT_SERVER, PHP_URL_HOST ) );
		$github   = 'github.com' === $host && preg_match( '#^/' . self::GITHUB_OWNER . '/#i', $path );
		if ( '' === $host || ( $host !== $official && ! $github ) ) {
			return null;
		}
		if ( 'https' === $scheme && empty( $p['port'] ) && empty( $p['user'] ) ) {
			if ( $host === $official && preg_match( '#^/v1/download/([a-z0-9][a-z0-9-]*)/([0-9][0-9A-Za-z.+-]*)\.zip$#D', $path, $m ) ) {
				return array(
					'slug'    => $m[1],
					'version' => rawurldecode( $m[2] ),
					'sig'     => self::sig_url( $host, $path, (string) ( $p['query'] ?? '' ) ),
				);
			}
			if ( $github && preg_match( '#^/[^/]+/[^/]+/releases/download/([a-z0-9][a-z0-9-]*)-v([0-9][0-9A-Za-z.+-]*)/([a-z0-9][a-z0-9-]*)\.zip$#D', $path, $m ) && $m[1] === $m[3] ) {
				return array(
					'slug'    => $m[1],
					'version' => $m[2],
					'sig'     => self::sig_url( $host, $path, (string) ( $p['query'] ?? '' ) ),
				);
			}
		}
		return array( 'reject' => true );
	}

	/**
	 * Sunucunun bildirdiği paket adresi o slug ve sürüme mi ait. İmza gerekmeyen adreste true; imzalı adreste slug ve
	 * sürüm adresle birebir aynı olmalı, kanonik olmayan resmi adres false.
	 */
	public static function matches( string $url, string $slug, string $version ): bool {
		$t = self::target( $url );
		if ( ! $t ) {
			return true;
		}
		return empty( $t['reject'] ) && $t['slug'] === $slug && $t['version'] === $version;
	}

	/** İmza adresi: zip yolu + ".sig"; sorgu (varsa) aynen kalır, site anahtarı başlıkla gider. */
	private static function sig_url( string $host, string $path, string $query ): string {
		return 'https://' . $host . $path . '.sig' . ( '' !== $query ? '?' . $query : '' );
	}

	/** 'enforce' (varsayılan) ya da 'off' (FLXU_SIGNATURES sabiti). */
	public static function mode(): string {
		return defined( 'FLXU_SIGNATURES' ) && 'off' === FLXU_SIGNATURES ? 'off' : 'enforce';
	}

	/**
	 * İnen zip'i doğrular. İmza gerekmiyorsa true.
	 *
	 * @return true|WP_Error
	 */
	public static function check( string $url, string $file ) {
		$t = self::target( $url );
		if ( ! $t ) {
			return true;
		}
		if ( ! empty( $t['reject'] ) ) {
			return new WP_Error( 'flxu_sig_url', __( 'This FluxWP package address is not in the expected signed form, so it was not installed. Contact FluxWP support.', 'flx-setup' ) );
		}
		$res = wp_remote_get(
			$t['sig'],
			array(
				'timeout'             => 15,
				'redirection'         => 'github.com' === strtolower( (string) wp_parse_url( $t['sig'], PHP_URL_HOST ) ) ? 3 : 0,
				'limit_response_size' => 4096,
			)
		);
		if ( is_wp_error( $res ) ) {
			/* translators: %s: error */
			return new WP_Error( 'flxu_sig_fetch', sprintf( __( 'The package signature could not be downloaded (%s). The package was not installed. Try again in a minute.', 'flx-setup' ), $res->get_error_message() ), array( 'transient' => true ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			return new WP_Error(
				'flxu_sig_missing',
				/* translators: 1: package slug, 2: version, 3: HTTP status code */
				sprintf( __( '%1$s %2$s has no valid FluxWP signature (HTTP %3$d), so it was not installed. Check for updates later or contact FluxWP support.', 'flx-setup' ), $t['slug'], $t['version'], $code )
			);
		}
		return self::verify( (string) wp_remote_retrieve_body( $res ), $t['slug'], $t['version'], $file );
	}

	/**
	 * İmza dosyası + zip → doğrulama.
	 *
	 * @param array<string,string>|null $keys Test için anahtar listesi (null: KEYS).
	 * @return true|WP_Error
	 */
	public static function verify( string $sig_json, string $slug, string $version, string $file, ?array $keys = null ) {
		$keys = null === $keys ? self::keys() : $keys;
		$j    = json_decode( trim( $sig_json ), true );
		$sig  = is_array( $j ) ? base64_decode( (string) ( $j['sig'] ?? '' ), true ) : false;
		$kid  = is_array( $j ) ? (string) ( $j['kid'] ?? '' ) : '';
		$pk   = isset( $keys[ $kid ] ) ? base64_decode( (string) $keys[ $kid ], true ) : false;
		$hash = is_readable( $file ) ? hash_file( 'sha256', $file ) : false;
		$ok   = false;
		if ( function_exists( 'sodium_crypto_sign_verify_detached' ) && is_array( $j ) && 1 === ( $j['v'] ?? null ) && false !== $sig && SODIUM_CRYPTO_SIGN_BYTES === strlen( $sig )
			&& false !== $pk && SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES === strlen( $pk ) && $hash ) {
			try {
				$ok = sodium_crypto_sign_verify_detached( $sig, "flx-sig/1\n" . $slug . "\n" . $version . "\n" . $hash, $pk );
			} catch ( \Throwable $e ) {
				$ok = false;
			}
		}
		if ( $ok ) {
			return true;
		}
		return new WP_Error(
			'flxu_sig_invalid',
			/* translators: 1: package slug, 2: version */
			sprintf( __( 'The FluxWP signature of %1$s %2$s does not match the downloaded file, so it was not installed. The package may have been changed on the way or on the server. Contact FluxWP support.', 'flx-setup' ), $slug, $version )
		);
	}
}
