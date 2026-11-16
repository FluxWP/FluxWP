<?php
/**
 * Captcha for other packages: what a client needs to protect a form, and the verification a public
 * mutation (or any code) runs before it does its work. REST, forms and GraphQL share it.
 *
 *   // In a public GraphQL mutation resolver (the form registered its context with flx_captcha_contexts):
 *   flx_captcha_gql_verify( 'my_form', $input['captchaToken'] ?? null );   // throws INVALID_INPUT on failure
 *
 * Without Flux reCAPTCHA installed the filters pass everything through (see api.php), so callers need no
 * existence check. Documentation: docs/flx-recaptcha/GRAPHQL.md and API.md.
 *
 * @package FLX_Recaptcha
 */

defined( 'ABSPATH' ) || exit;

final class FLXRC_Captcha_Service {

	/**
	 * What a client needs to protect a form. Null when the context is not registered.
	 * Visitor-level: it does not depend on the IP allow list or on who is signed in, so it can be cached.
	 *
	 * @param string $context Context key.
	 */
	public static function config( string $context ): ?FLXRC_Public_Config {
		$context = sanitize_key( $context );
		$all     = flxrc_contexts();
		if ( ! isset( $all[ $context ] ) ) {
			return null;
		}
		$c       = $all[ $context ];
		$enabled = flxrc_active() && ! empty( $c['available'] ) && flxrc_context_on( $context );
		$s       = FLXRC_Settings_Service::settings();
		$v3      = 'v3' === $s->version;
		$host    = flxrc_api_host() . '/recaptcha/api.js';
		return new FLXRC_Public_Config(
			$context,
			$enabled,
			$s->version,
			$enabled ? flxrc_site_key() : null,
			$enabled ? self::str( $c['action'] ) : null,
			$enabled ? ( $v3 ? $host . '?render=' . rawurlencode( flxrc_site_key() ) : $host . '?render=explicit' ) : null,
			$s->v2_theme,
			$s->v2_size,
			$v3 && $s->hide_badge,
			FLXRC_Verify::FIELD,
			FLXRC_Verify::HEADER,
		);
	}

	/**
	 * Verifies the token of one form. True when the form is not protected for this request.
	 *
	 * @param string      $context Context key.
	 * @param string|null $token   Token; null reads the POST field or the X-Flx-Captcha header.
	 * @return true|WP_Error
	 */
	public static function verify( string $context, ?string $token = null ) {
		return FLXRC_Verify::check( sanitize_key( $context ), $token );
	}

	/**
	 * For a GraphQL mutation: throws INVALID_INPUT (payload errors, field `captchaToken`) when the check fails.
	 *
	 * @param string      $context Context key.
	 * @param string|null $token   Token from the mutation input; null reads the X-Flx-Captcha header.
	 * @param string      $field   Input field the error is shown on.
	 * @throws FLXGQL_Error When the check fails.
	 */
	public static function guard( string $context, ?string $token = null, string $field = 'captchaToken' ): void {
		$r = self::verify( $context, $token );
		if ( $r instanceof WP_Error ) {
			throw flx_gql_error( 'INVALID_INPUT', $r->get_error_message(), $field, array( 'reason' => $r->get_error_code() ) );
		}
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function str( $v ): string {
		return is_scalar( $v ) ? (string) $v : '';
	}
}
