<?php
/**
 * Diğer eklentiler için ortak API (Flux reCAPTCHA kurulu değilse de güvenle çağrılabilir filtre/eylem biçimleri var).
 * Belge: docs/flx-recaptcha/API.md
 *
 *   // Form içinde alan (kurulu değilse hiçbir şey basmaz):
 *   do_action( 'flx_captcha_field', 'my_form' );
 *
 *   // Gönderimde doğrulama (kurulu değilse true döner):
 *   $ok = apply_filters( 'flx_captcha_verify', true, 'my_form' );
 *   if ( is_wp_error( $ok ) ) { … $ok->get_error_message() … }
 *
 *   // GraphQL mutation'da (hata: INVALID_INPUT, alan captchaToken; Flux GraphQL gerekir, kurulu değilse hiçbir şey yapmaz):
 *   do_action( 'flx_captcha_gql_verify', 'my_form', $input['captchaToken'] ?? null );
 *
 * Formun panelde aç/kapa satırı olması için `flx_captcha_contexts` filtresiyle kaydedin (includes/contexts.php).
 *
 * @package FLX_Recaptcha
 */

defined( 'ABSPATH' ) || exit;

/** Bu istekte bu form korunuyor mu. */
function flx_captcha_enabled( $context ) {
	return flxrc_required( sanitize_key( $context ) );
}

/** Alan HTML'i ('' = koruma yok). */
function flx_captcha_field( $context ) {
	return FLXRC_Front::field( sanitize_key( $context ) );
}

/**
 * Doğrula.
 *
 * @param string      $context Bağlam.
 * @param string|null $token   Belirteç; null ise POST alanından ya da X-Flx-Captcha başlığından okunur.
 * @return true|WP_Error
 */
function flx_captcha_verify( $context, $token = null ) {
	return FLXRC_Verify::check( sanitize_key( $context ), $token );
}

/**
 * GraphQL mutation için doğrula; geçmezse INVALID_INPUT fırlatır (Flux GraphQL kit hatası).
 *
 * @param string      $context Bağlam.
 * @param string|null $token   Belirteç; null ise X-Flx-Captcha başlığından okunur.
 * @param string      $field   Hatanın gösterileceği girdi alanı.
 * @throws FLXGQL_Error Geçmezse.
 */
function flx_captcha_gql_verify( $context, $token = null, $field = 'captchaToken' ) {
	if ( function_exists( 'flx_gql_error' ) ) {
		FLXRC_Captcha_Service::guard( (string) $context, null === $token ? null : (string) $token, (string) $field );
	}
}

add_action(
	'flx_captcha_gql_verify',
	function ( $context, $token = null, $field = 'captchaToken' ) {
		flx_captcha_gql_verify( $context, $token, $field );
	},
	10,
	3
);

add_action(
	'flx_captcha_field',
	function ( $context ) {
		echo flx_captcha_field( (string) $context ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
);

add_filter(
	'flx_captcha_verify',
	function ( $ok, $context, $token = null ) {
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return flx_captcha_verify( (string) $context, $token );
	},
	10,
	3
);

/** Sayfada alanı PHP'den basmayan (yalnız JS ile gönderen) form için betiği yükle. */
add_action(
	'flx_captcha_enqueue',
	function ( $context ) {
		FLXRC_Front::need( sanitize_key( (string) $context ) );
	}
);
