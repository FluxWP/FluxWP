<?php
/**
 * Records, people and the public verification/lookup. REST, admin-post and GraphQL call these
 * methods; the classic JSON and redirect texts stay the ones the screens already return.
 *
 * @package flx-certificates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared helpers for the records services.
 */
final class FLXS_Records {

	public const TAG_LIST     = 'flx-certificates:list';
	public const TAG_PEOPLE   = 'flx-certificates:people';
	public const TAG_SETTINGS = 'flx-certificates:settings';

	/**
	 * A WP_Error the GraphQL layer maps to a stable code.
	 *
	 * @param string $gql   INVALID_INPUT | NOT_FOUND | CONFLICT | FORBIDDEN | RATE_LIMITED.
	 * @param string $field Input field.
	 */
	/**
	 * The classic REST error, typed for the services. flxs_rest_error() is untyped.
	 *
	 * @param mixed $e Error or message.
	 */
	public static function rest_response( $e, int $status = 400 ): WP_REST_Response {
		$response = flxs_rest_error( $e, $status );
		if ( $response instanceof WP_REST_Response ) {
			return $response;
		}
		return new WP_REST_Response( array( 'ok' => false, 'message' => is_string( $e ) ? $e : '' ), $status );
	}

	public static function error( string $code, string $message, string $gql = 'INVALID_INPUT', string $field = '', int $status = 400 ): WP_Error {
		$data = array(
			'graphql_code' => $gql,
			'status'       => $status,
		);
		if ( '' !== $field ) {
			$data['field'] = $field;
		}
		return new WP_Error( $code, $message, $data );
	}

	/**
	 * Gives an existing WP_Error a GraphQL code when it has none. The classic message is Turkish;
	 * GraphQL clients get the translatable English text instead.
	 */
	public static function classify( WP_Error $e ): WP_Error {
		$data = $e->get_error_data();
		if ( is_array( $data ) && ! empty( $data['graphql_code'] ) ) {
			return $e;
		}
		$code = FLXS_Val::str($e->get_error_code());
		$gql  = 'INVALID_INPUT';
		if ( in_array( $code, array( 'flxs_404', 'flxs_template' ), true ) ) {
			$gql = 'NOT_FOUND';
		} elseif ( 'flxs_has_certs' === $code ) {
			$gql = 'CONFLICT';
		} elseif ( in_array( $code, array( 'flxs_db', 'flxs_person_db' ), true ) ) {
			$gql = 'INTERNAL';
		}
		$field = 'flxs_phone' === $code ? 'phone' : ( 'flxs_email' === $code ? 'email' : ( 'flxs_name' === $code ? 'name' : '' ) );
		return self::error( $code, self::message( $e ), $gql, $field );
	}

	/**
	 * The text of a classic error in English, translatable. The classic code writes Turkish
	 * sentences (REST keeps them byte for byte); anything not listed here is returned as it is.
	 */
	public static function message( WP_Error $e ): string {
		$raw = FLXS_Val::str( $e->get_error_message() );
		$map = array(
			'Sertifika bulunamadı.'                    => __( 'Certificate not found.', 'flx-certificates' ),
			'Şablon bulunamadı.'                       => __( 'Template not found.', 'flx-certificates' ),
			'Telefon numarası geçersiz. Örnek: 0532 123 45 67' => __( 'That phone number is not valid.', 'flx-certificates' ),
			'E-posta adresi geçersiz.'                 => __( 'That e-mail address is not valid.', 'flx-certificates' ),
			'Ad soyad gerekli.'                        => __( 'The full name is required.', 'flx-certificates' ),
			'Bu kişinin sertifikaları var; önce sertifikaları silin.' => __( 'This person has certificates. Delete the certificates first.', 'flx-certificates' ),
			'Bu etkinlik kapalı ya da yok. Kapalı etkinliğe sertifika eklenemez.' => __( 'That event is closed or does not exist. A closed event cannot take certificates.', 'flx-certificates' ),
			'Geçersiz PDF.'                            => __( 'That is not a valid PDF.', 'flx-certificates' ),
			'Geçersiz görsel (JPEG bekleniyor).'       => __( 'That is not a valid image (JPEG expected).', 'flx-certificates' ),
			'Dosya çok büyük.'                         => __( 'The file is too large.', 'flx-certificates' ),
			'Dosya yazılamadı (uploads klasörü izinleri?).' => __( 'The file could not be written. Check the uploads folder permissions.', 'flx-certificates' ),
			'Kişinin e-postası yok.'                   => __( 'The person has no e-mail address.', 'flx-certificates' ),
			'Kişinin telefonu yok.'                    => __( 'The person has no phone number.', 'flx-certificates' ),
			'Sertifika iptal edilmiş.'                 => __( 'A revoked certificate cannot be sent.', 'flx-certificates' ),
			'Kanal seçin.'                             => __( 'Choose a channel.', 'flx-certificates' ),
			'Kanal geçersiz.'                          => __( 'That channel is not valid.', 'flx-certificates' ),
			'E-posta adresi girin.'                    => __( 'Enter an e-mail address.', 'flx-certificates' ),
			'Telefon numarası girin.'                  => __( 'Enter a phone number.', 'flx-certificates' ),
			'Geçerli bir telefon ya da e-posta girin.' => __( 'Enter a valid phone number or e-mail address.', 'flx-certificates' ),
			'Gönderilemedi.'                           => __( 'The message could not be sent.', 'flx-certificates' ),
			'Mesaj metni boş.'                         => __( 'The message text is empty.', 'flx-certificates' ),
		);
		if ( isset( $map[ $raw ] ) ) {
			return $map[ $raw ];
		}
		if ( 0 === strpos( $raw, 'Sertifika kaydedilemedi:' ) ) {
			return __( 'The certificate could not be saved.', 'flx-certificates' );
		}
		if ( 0 === strpos( $raw, 'Kuyruğa eklenemedi:' ) ) {
			return __( 'The message could not be queued.', 'flx-certificates' );
		}
		if ( 1 === preg_match( '/^(.+) için aktif sağlayıcı yok\./u', $raw, $m ) ) {
			/* translators: %s: channel name */
			return sprintf( __( 'There is no active connection for %s. Set one up in Flux Mail.', 'flx-certificates' ), $m[1] );
		}
		if ( 1 === preg_match( '/^(.+): tarih biçimi geçersiz\.$/u', $raw, $m ) ) {
			/* translators: %s: field label */
			return sprintf( __( '%s: the date format is not valid.', 'flx-certificates' ), $m[1] );
		}
		if ( 1 === preg_match( '/^(.+) gerekli\.$/u', $raw, $m ) ) {
			/* translators: %s: field label */
			return sprintf( __( '%s is required.', 'flx-certificates' ), $m[1] );
		}
		return $raw;
	}

	/**
	 * A note the person upsert wrote in Turkish, in English. Unknown notes pass through.
	 */
	public static function note( string $raw ): string {
		if ( 1 === preg_match( '/^Bu telefon başka bir kişide kayıtlı \(#(\d+)\), kişiye eklenmedi\.$/u', $raw, $m ) ) {
			/* translators: %d: other person number */
			return sprintf( __( 'That phone number belongs to another person (#%d) and was not added.', 'flx-certificates' ), (int) $m[1] );
		}
		if ( 1 === preg_match( '/^Bu e-posta başka bir kişide kayıtlı \(#(\d+)\), kişiye eklenmedi\.$/u', $raw, $m ) ) {
			/* translators: %d: other person number */
			return sprintf( __( 'That e-mail belongs to another person (#%d) and was not added.', 'flx-certificates' ), (int) $m[1] );
		}
		return $raw;
	}

	/**
	 * Drops the GraphQL cache of the records that changed.
	 *
	 * @param list<string> $tags Tags.
	 */
	public static function purge( array $tags ): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( $tags );
		}
	}

	/**
	 * @param int $id Certificate id.
	 * @return list<string>
	 */
	public static function certificate_tags( int $id, string $code = '' ): array {
		$tags = array( self::TAG_LIST, 'flx-certificates:certificate:' . $id );
		if ( '' !== $code ) {
			$tags[] = 'flx-certificates:verify:' . $code;
		}
		return $tags;
	}
}

/**
 * Scalar coercion for database rows. Services run without the GraphQL kit, so this does not call it.
 */
final class FLXS_Val {

	/**
	 * @param mixed $v Value.
	 */
	public static function str( $v, string $default = '' ): string {
		return is_scalar( $v ) ? (string) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function int( $v, int $default = 0 ): int {
		return is_numeric( $v ) ? (int) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	public static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function mix( $v ): mixed {
		return $v;
	}

	/**
	 * @param mixed $v Value.
	 * @return array<string,mixed>
	 */
	public static function row( $v ): array {
		$out = array();
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				$out[ (string) $k ] = $x;
			}
		}
		return $out;
	}
}
