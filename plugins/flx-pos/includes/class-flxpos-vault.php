<?php
/**
 * Kart kasası: kart bilgisi ödeme formundan 3D köprü sayfasına geçerken düz metin olarak hiçbir yerde durmaz.
 *
 * Kart, rastgele bir anahtarla şifrelenip kısa ömürlü bir transient'e yazılır (en çok 10 dakika). Anahtar
 * yalnız ödeyenin tarayıcısında, HttpOnly bir çerezde durur; sunucuda yalnız şifreli metin vardır. Köprü
 * sayfası kartı bir kez açar ve hemen siler. Çerez ya da transient yoksa kart yoktur: ödeyen formu yeniden doldurur.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Vault {

	const TTL    = 600;
	const COOKIE = 'flxpos_k';

	/** Kartı sakla; dönüş: kasa kimliği. */
	public static function put( array $card ) {
		$id  = bin2hex( random_bytes( 12 ) );
		$key = random_bytes( 32 );
		set_transient( 'flxpos_v_' . $id, flxpos_encrypt( wp_json_encode( $card ), $key ), self::TTL );
		$cookie = base64_encode( $key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE . '_' . $id,
				$cookie,
				array(
					'expires'  => time() + self::TTL,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
		$_COOKIE[ self::COOKIE . '_' . $id ] = $cookie;
		return $id;
	}

	/** Kartı bir kez aç ve sil. */
	public static function take( $id ) {
		$id = preg_replace( '/[^a-f0-9]/', '', (string) $id );
		if ( '' === $id ) {
			return null;
		}
		$name = self::COOKIE . '_' . $id;
		$enc  = get_transient( 'flxpos_v_' . $id );
		delete_transient( 'flxpos_v_' . $id );
		$k = isset( $_COOKIE[ $name ] ) ? base64_decode( sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ), true ) : false; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( ! headers_sent() ) {
			setcookie( $name, '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN ? COOKIE_DOMAIN : '', is_ssl(), true );
		}
		unset( $_COOKIE[ $name ] );
		if ( ! $enc || ! $k || 32 !== strlen( $k ) ) {
			return null;
		}
		$card = json_decode( flxpos_decrypt( $enc, $k ), true );
		return is_array( $card ) ? $card : null;
	}

	/**
	 * Bankası 3D dönüşünden sonra kartı yeniden isteyen protokoller (PayFlex) için: kart şifreli olarak
	 * işleme bağlı saklanır; anahtar dönüş adresine yazılır (bankaya gider, banka kartı zaten biliyor).
	 * Çerez kullanılamaz: bankanın dönüşü siteler arası POST'tur, tarayıcı çerezi göndermez.
	 *
	 * @return string Dönüş adresine eklenecek anahtar.
	 */
	public static function put_return( $txn_id, array $card ) {
		$key = random_bytes( 32 );
		set_transient( 'flxpos_r_' . (int) $txn_id, flxpos_encrypt( wp_json_encode( $card ), $key ), 20 * MINUTE_IN_SECONDS );
		return rtrim( strtr( base64_encode( $key ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public static function take_return( $txn_id, $key ) {
		$enc = get_transient( 'flxpos_r_' . (int) $txn_id );
		delete_transient( 'flxpos_r_' . (int) $txn_id );
		$k = base64_decode( strtr( (string) $key, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( ! $enc || ! $k || 32 !== strlen( $k ) ) {
			return null;
		}
		$card = json_decode( flxpos_decrypt( $enc, $k ), true );
		return is_array( $card ) ? $card : null;
	}

	/** Kartı açmadan sil (vazgeçilen ödeme). */
	public static function drop( $id ) {
		delete_transient( 'flxpos_v_' . preg_replace( '/[^a-f0-9]/', '', (string) $id ) );
	}
}
