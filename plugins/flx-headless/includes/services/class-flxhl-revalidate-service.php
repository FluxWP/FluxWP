<?php
/**
 * Revalidate webhook: when content, menus or settings change, every front end with a webhook address gets one
 * signed POST at the end of the request with the cache tags (and page paths) to refresh.
 *
 * Tags come from every FluxWP package: whatever is purged with flx_gql_purge() (Flux GraphQL's
 * `flx_gql_purged` action) is forwarded, plus the content tags of this package (post, term, archive, menus).
 *
 * Signature (Stripe style, replay-safe): header `X-Flx-Signature: t=<unix time>,v1=<hex>` where
 * hex = HMAC-SHA256( secret, "<t>.<raw body>" ). The front end rejects the call when the HMAC differs or t is
 * older than five minutes.
 *
 * Hostinger has no working cron, so delivery happens in the same request: after the response is flushed when
 * the server can (fastcgi_finish_request / litespeed_finish_request), else as a short non-blocking call.
 *
 * @package FLX_Headless
 */

defined( 'ABSPATH' ) || exit;

final class FLXHL_Revalidate_Service {

	public const LOG_OPTION = 'flxhl_log';
	public const LOG_MAX    = 30;
	public const MAX_TAGS   = 200;

	/** @var array<string,true> */
	private static array $tags = array();
	/** @var array<string,true> */
	private static array $paths = array();
	private static bool $hooked = false;
	private static string $event = 'purge';

	/**
	 * Queues tags (and WordPress page addresses) for this request's delivery.
	 *
	 * @param list<string> $tags  Cache tags.
	 * @param list<string> $paths Page addresses (WordPress URLs or front-end paths).
	 */
	public static function queue( array $tags, array $paths = array() ): void {
		foreach ( $tags as $t ) {
			$t = (string) preg_replace( '/[^A-Za-z0-9:_.-]/', '', $t );
			if ( '' !== $t && count( self::$tags ) < self::MAX_TAGS ) {
				self::$tags[ $t ] = true;
			}
		}
		foreach ( $paths as $p ) {
			$p = FLXHL_Frontend_Service::front_path( $p );
			if ( null !== $p && count( self::$paths ) < self::MAX_TAGS ) {
				self::$paths[ $p ] = true;
			}
		}
		if ( ! self::$hooked && ( self::$tags || self::$paths ) ) {
			self::$hooked = true;
			add_action( 'shutdown', array( self::class, 'flush' ), 100 );
		}
	}

	/** @return array{tags: list<string>, paths: list<string>} Queued, not yet sent (tests). */
	public static function pending(): array {
		return array( 'tags' => array_keys( self::$tags ), 'paths' => array_keys( self::$paths ) );
	}

	/** Sends what was queued to every front end with a webhook. Runs on shutdown. */
	public static function flush(): void {
		$tags  = array_keys( self::$tags );
		$paths = array_keys( self::$paths );
		self::$tags  = array();
		self::$paths = array();
		self::$hooked = false;
		if ( ! $tags && ! $paths ) {
			return;
		}
		$targets = array_values( array_filter( FLXHL_Frontend_Service::frontends(), static fn( FLXHL_Frontend $f ): bool => $f->enabled && '' !== $f->revalidate_url && $f->secret_set ) );
		if ( ! $targets ) {
			return;
		}
		$wait = false;
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! headers_sent() ) {
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				$wait = fastcgi_finish_request();
			} elseif ( function_exists( 'litespeed_finish_request' ) ) {
				$wait = (bool) litespeed_finish_request();
			}
		}
		foreach ( $targets as $fe ) {
			self::log_add( self::send( $fe, self::$event, $tags, $paths, $wait ) );
		}
	}

	/** Sends a test call to one front end and waits for the answer. */
	public static function test( string $id ): FLXHL_Delivery|WP_Error {
		$fe = FLXHL_Frontend_Service::get( $id );
		if ( null === $fe ) {
			return new WP_Error( 'flxhl_not_found', __( 'This front end does not exist any more.', 'flx-headless' ), array( 'graphql_code' => 'NOT_FOUND', 'field' => 'id' ) );
		}
		if ( '' === $fe->revalidate_url || ! $fe->secret_set ) {
			return new WP_Error( 'flxhl_no_hook', __( 'Enter the webhook address and the secret first.', 'flx-headless' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'revalidateUrl' ) );
		}
		$d = self::send( $fe, 'test', array( FLXHL_Frontend_Service::CACHE_TAG ), array( '/' ), true );
		self::log_add( $d );
		return $d;
	}

	/** Signature header value for a body (also documented for front ends). */
	public static function signature( #[\SensitiveParameter] string $secret, string $body, int $time ): string {
		return 't=' . $time . ',v1=' . hash_hmac( 'sha256', $time . '.' . $body, $secret );
	}

	/**
	 * @param list<string> $tags
	 * @param list<string> $paths
	 */
	private static function send( FLXHL_Frontend $fe, string $event, array $tags, array $paths, bool $wait ): FLXHL_Delivery {
		$secret = FLXHL_Frontend_Service::secret( $fe->id );
		$time   = time();
		$body   = (string) wp_json_encode(
			array(
				'event'    => $event,
				'site'     => untrailingslashit( home_url() ),
				'frontend' => $fe->id,
				'tags'     => $tags,
				'paths'    => self::localize_paths( $paths, $fe ),
				'time'     => $time,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		$args   = array(
			'body'        => $body,
			'headers'     => array(
				'Content-Type'    => 'application/json',
				'User-Agent'      => 'FluxWP-Headless/' . FLXHL_VERSION,
				'X-Flx-Event'     => $event,
				'X-Flx-Signature' => self::signature( $secret, $body, $time ),
			),
			'timeout'     => $wait ? 5 : 1,
			'blocking'    => $wait,
			'redirection' => 0,
		);
		$host   = flxhl_str( wp_parse_url( $fe->revalidate_url, PHP_URL_HOST ) );
		$start  = microtime( true );
		$res    = FLXHL_Frontend_Service::is_local( $host ) ? wp_remote_post( $fe->revalidate_url, $args ) : wp_safe_remote_post( $fe->revalidate_url, $args );
		$ms     = (int) round( ( microtime( true ) - $start ) * 1000 );
		if ( is_wp_error( $res ) ) {
			$ok   = false;
			$code = 0;
			$msg  = $res->get_error_message();
		} else {
			$code = $wait ? (int) wp_remote_retrieve_response_code( $res ) : 0;
			$ok   = ! $wait || ( $code >= 200 && $code < 300 );
			$msg  = self::result_text( $code, $ok );
		}
		return new FLXHL_Delivery( $time, $fe->id, $fe->name, $event, $tags, $paths, $code, $ok, $msg, $ms );
	}

	/**
	 * Paths for one front end: only its languages.
	 *
	 * @param list<string> $paths
	 * @return list<string>
	 */
	private static function localize_paths( array $paths, FLXHL_Frontend $fe ): array {
		if ( ! $fe->languages || ! function_exists( 'flxl_url_language' ) ) {
			return $paths;
		}
		return array_values( array_filter( $paths, static fn( string $p ): bool => in_array( flxhl_str( flxl_url_language( $p ) ), $fe->languages, true ) ) );
	}

	private static function log_add( FLXHL_Delivery $d ): void {
		$log = flxhl_arr( get_option( self::LOG_OPTION, array() ) );
		array_unshift( $log, $d->to_array() );
		update_option( self::LOG_OPTION, array_slice( $log, 0, self::LOG_MAX ), false );
	}

	/** @return list<FLXHL_Delivery> Newest first. */
	public static function log(): array {
		$rows = FLXHL_Read::rows( array( 'l' => get_option( self::LOG_OPTION, array() ) ), 'l', array( FLXHL_Delivery::class, 'from' ) );
		// Known results are written again in the reader's language; transport errors stay as they came.
		return array_map(
			static fn( FLXHL_Delivery $d ): FLXHL_Delivery => ( $d->status > 0 || $d->ok )
				? new FLXHL_Delivery( $d->time, $d->frontend_id, $d->frontend_name, $d->event, $d->tags, $d->paths, $d->status, $d->ok, self::result_text( $d->status, $d->ok ), $d->ms )
				: $d,
			$rows
		);
	}

	/** Result of a call that reached the front end (status 0 = sent without waiting). */
	private static function result_text( int $code, bool $ok ): string {
		if ( 0 === $code ) {
			return __( 'Sent without waiting for the answer.', 'flx-headless' );
		}
		/* translators: %d: HTTP status code */
		return $ok ? __( 'The front end accepted the call.', 'flx-headless' ) : sprintf( __( 'The front end answered with HTTP %d.', 'flx-headless' ), $code );
	}

	public static function log_clear(): void {
		delete_option( self::LOG_OPTION );
	}
}
