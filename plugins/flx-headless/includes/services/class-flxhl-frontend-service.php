<?php
/**
 * Front ends: the headless sites this WordPress serves (one or many, e.g. site.com and shop.site.com, or
 * one per brand). Each has its own address, languages, revalidate webhook and secret. Settings live in this
 * site's option, so on a multisite network every site manages its own front ends.
 *
 * The secret is write-only: stored sealed (flx_secret_seal), never returned; reads give only whether one is
 * saved and its last characters.
 *
 * @package FLX_Headless
 */

defined( 'ABSPATH' ) || exit;

final class FLXHL_Frontend_Service {

	public const OPTION    = 'flxhl_settings';
	public const CACHE_TAG = 'flx-headless:site';
	public const MAX       = 20;
	/** What WordPress does with its own theme pages once a front end serves the site. */
	public const WP_FRONT = array( 'keep', 'noindex', 'redirect' );
	/** Value of the secret field that removes the saved secret. */
	public const CLEAR = '__clear__';

	/**
	 * Stored settings.
	 *
	 * @return array{frontends: list<array<string,mixed>>, wp_front: string}
	 */
	public static function raw(): array {
		$o   = flxhl_arr( get_option( self::OPTION, array() ) );
		$fes = array();
		foreach ( flxhl_arr( $o['frontends'] ?? array() ) as $f ) {
			if ( is_array( $f ) && '' !== flxhl_str( $f['id'] ?? '' ) ) {
				$row = array();
				foreach ( $f as $k => $v ) {
					$row[ (string) $k ] = $v;
				}
				$fes[] = $row;
			}
		}
		$mode = flxhl_str( $o['wp_front'] ?? 'keep' );
		return array(
			'frontends' => $fes,
			'wp_front'  => in_array( $mode, self::WP_FRONT, true ) ? $mode : 'keep',
		);
	}

	/**
	 * @param array{frontends: list<array<string,mixed>>, wp_front: string} $raw
	 */
	private static function write( array $raw ): void {
		update_option( self::OPTION, $raw, false );
		self::changed();
	}

	/** Drops cached site / route answers after a settings change. */
	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}

	/** @param array<string,mixed> $f */
	private static function dto( array $f ): FLXHL_Frontend {
		$stored = flxhl_str( $f['secret'] ?? '' );
		$plain  = '' !== $stored ? self::open( $stored ) : '';
		return new FLXHL_Frontend(
			flxhl_str( $f['id'] ?? '' ),
			flxhl_str( $f['name'] ?? '' ),
			untrailingslashit( flxhl_str( $f['url'] ?? '' ) ),
			FLXHL_Read::strings( $f, 'languages' ),
			! array_key_exists( 'enabled', $f ) || ! empty( $f['enabled'] ),
			! empty( $f['default'] ),
			flxhl_str( $f['revalidate_url'] ?? '' ),
			'' !== $plain,
			'' !== $plain ? '••••' . substr( $plain, -4 ) : '',
			flxhl_str( $f['preview_path'] ?? '' ),
		);
	}

	/** @return list<FLXHL_Frontend> */
	public static function frontends(): array {
		return array_map( array( self::class, 'dto' ), self::raw()['frontends'] );
	}

	public static function get( string $id ): ?FLXHL_Frontend {
		foreach ( self::frontends() as $f ) {
			if ( $f->id === $id ) {
				return $f;
			}
		}
		return null;
	}

	/** Default front end: the one marked default, else the first enabled one. */
	public static function default(): ?FLXHL_Frontend {
		$first = null;
		foreach ( self::frontends() as $f ) {
			if ( ! $f->enabled ) {
				continue;
			}
			if ( $f->is_default ) {
				return $f;
			}
			$first ??= $f;
		}
		return $first;
	}

	/**
	 * The front end a request is for: the given id, else the browser's Origin, else the default one.
	 * A disabled front end is never chosen.
	 */
	public static function current( ?string $id = null ): ?FLXHL_Frontend {
		if ( null !== $id && '' !== $id ) {
			$f = self::get( sanitize_key( $id ) );
			return $f && $f->enabled ? $f : null;
		}
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? strtolower( untrailingslashit( flxhl_str( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' !== $origin ) {
			foreach ( self::frontends() as $f ) {
				if ( $f->enabled && $f->origin() === $origin ) {
					return $f;
				}
			}
		}
		return self::default();
	}

	/** Plain secret of a front end; '' when none. Server side only (signing). */
	public static function secret( string $id ): string {
		foreach ( self::raw()['frontends'] as $f ) {
			if ( flxhl_str( $f['id'] ?? '' ) === $id ) {
				$s = flxhl_str( $f['secret'] ?? '' );
				return '' === $s ? '' : self::open( $s );
			}
		}
		return '';
	}

	/**
	 * Whether this request carries the front end's preview signature for a draft:
	 * X-Flx-Preview: t=<unix time>,v1=hex(HMAC-SHA256(secret, "<t>.preview.<post id>")), at most 5 minutes old.
	 * Lets the front end's server show a draft with only its connection key (no application password).
	 */
	public static function preview_signed( ?FLXHL_Frontend $fe, int $post_id ): bool {
		$header = isset( $_SERVER['HTTP_X_FLX_PREVIEW'] ) ? sanitize_text_field( wp_unslash( flxhl_str( $_SERVER['HTTP_X_FLX_PREVIEW'] ) ) ) : '';
		if ( ! $fe || $post_id <= 0 || '' === $header || ! preg_match( '/^t=(\d+),v1=([a-f0-9]{64})$/', $header, $m ) ) {
			return false;
		}
		$secret = self::secret( $fe->id );
		$time   = (int) $m[1];
		if ( '' === $secret || abs( time() - $time ) > 5 * MINUTE_IN_SECONDS ) {
			return false;
		}
		return hash_equals( hash_hmac( 'sha256', $time . '.preview.' . $post_id, $secret ), $m[2] );
	}

	private static function seal( #[\SensitiveParameter] string $plain ): string {
		return function_exists( 'flx_secret_seal' ) ? flx_secret_seal( $plain, 'flx-headless' ) : '';
	}

	private static function open( string $stored ): string {
		return function_exists( 'flx_secret_open' ) ? flx_secret_open( $stored, 'flx-headless' ) : '';
	}

	/* ------------------------------------------------------------------ */
	/* Writes                                                              */
	/* ------------------------------------------------------------------ */

	/** Whether an address may be a front end or webhook: https, or http on a local host while building. */
	public static function valid_url( string $url ): bool {
		$p = wp_parse_url( $url );
		if ( ! is_array( $p ) || empty( $p['host'] ) || empty( $p['scheme'] ) || isset( $p['user'] ) || isset( $p['pass'] ) ) {
			return false;
		}
		if ( 'https' === $p['scheme'] ) {
			return true;
		}
		return 'http' === $p['scheme'] && self::is_local( (string) $p['host'] );
	}

	public static function is_local( string $host ): bool {
		return in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) || (bool) preg_match( '/\.(test|local|localhost)$/', $host );
	}

	/**
	 * Adds or changes a front end. Fields left out stay as they are. Secret: empty or left out keeps the saved
	 * one, CLEAR removes it.
	 *
	 * @param array{id?: string, name?: string, url?: string, languages?: list<string>, enabled?: bool, default?: bool, revalidate_url?: string, secret?: string, preview_path?: string} $in
	 * @return FLXHL_Frontend|WP_Error
	 */
	public static function save( #[\SensitiveParameter] array $in ) {
		$raw  = self::raw();
		$id   = sanitize_key( $in['id'] ?? '' );
		$at   = null;
		$row  = array();
		foreach ( $raw['frontends'] as $i => $f ) {
			if ( '' !== $id && flxhl_str( $f['id'] ?? '' ) === $id ) {
				$at  = $i;
				$row = $f;
			}
		}
		if ( '' !== $id && null === $at ) {
			return self::invalid( 'id', __( 'This front end does not exist any more.', 'flx-headless' ), 'NOT_FOUND' );
		}
		if ( null === $at && count( $raw['frontends'] ) >= self::MAX ) {
			/* translators: %d: maximum number of front ends */
			return self::invalid( 'name', sprintf( __( 'At most %d front ends can be added.', 'flx-headless' ), self::MAX ) );
		}

		$name = isset( $in['name'] ) ? sanitize_text_field( $in['name'] ) : flxhl_str( $row['name'] ?? '' );
		if ( '' === $name ) {
			return self::invalid( 'name', __( 'Give the front end a name.', 'flx-headless' ) );
		}
		$url = isset( $in['url'] ) ? untrailingslashit( esc_url_raw( trim( $in['url'] ) ) ) : flxhl_str( $row['url'] ?? '' );
		if ( '' === $url || ! self::valid_url( $url ) ) {
			return self::invalid( 'url', __( 'Enter the front end address with https:// (http:// only for localhost).', 'flx-headless' ) );
		}
		$url = flxhl_origin( $url ) . untrailingslashit( flxhl_str( wp_parse_url( $url, PHP_URL_PATH ) ) );
		if ( untrailingslashit( $url ) === untrailingslashit( flxhl_origin( flxhl_home() ) . flxhl_home_path() ) ) {
			return self::invalid( 'url', __( 'The front end cannot have the same address as this WordPress. Move WordPress to another address (e.g. cms.example.com) first.', 'flx-headless' ) );
		}
		foreach ( $raw['frontends'] as $i => $f ) {
			if ( $i !== $at && flxhl_origin( flxhl_str( $f['url'] ?? '' ) ) === flxhl_origin( $url ) && untrailingslashit( flxhl_str( $f['url'] ?? '' ) ) === $url ) {
				return self::invalid( 'url', __( 'Another front end already has this address.', 'flx-headless' ), 'CONFLICT' );
			}
		}

		$hook = isset( $in['revalidate_url'] ) ? trim( $in['revalidate_url'] ) : flxhl_str( $row['revalidate_url'] ?? '' );
		if ( '' !== $hook ) {
			$hook = esc_url_raw( $hook );
			if ( ! self::valid_url( $hook ) ) {
				return self::invalid( 'revalidateUrl', __( 'Enter the webhook address with https:// (http:// only for localhost).', 'flx-headless' ) );
			}
		}

		$langs = isset( $in['languages'] ) ? array_values( array_unique( array_filter( array_map( 'sanitize_key', $in['languages'] ) ) ) ) : FLXHL_Read::strings( $row, 'languages' );
		if ( function_exists( 'flxl_is_language' ) ) {
			foreach ( $langs as $l ) {
				if ( ! flxl_is_language( $l ) ) {
					/* translators: %s: language code */
					return self::invalid( 'languages', sprintf( __( 'The language "%s" is not on this site.', 'flx-headless' ), $l ) );
				}
			}
		}

		$preview = isset( $in['preview_path'] ) ? trim( sanitize_text_field( $in['preview_path'] ) ) : flxhl_str( $row['preview_path'] ?? '' );
		if ( '' !== $preview && '/' !== $preview[0] ) {
			return self::invalid( 'previewPath', __( 'The preview path starts with "/" (e.g. /api/preview).', 'flx-headless' ) );
		}

		$secret = flxhl_str( $row['secret'] ?? '' );
		$given  = isset( $in['secret'] ) ? trim( $in['secret'] ) : '';
		if ( self::CLEAR === $given ) {
			$secret = '';
		} elseif ( '' !== $given ) {
			if ( strlen( $given ) < 24 ) {
				return self::invalid( 'secret', __( 'The secret must be at least 24 characters. Use the Generate button.', 'flx-headless' ) );
			}
			$secret = self::seal( $given );
			if ( '' === $secret ) {
				return self::invalid( 'secret', __( 'The secret could not be stored securely on this server.', 'flx-headless' ), 'INTERNAL' );
			}
		}
		if ( '' !== $hook && '' === $secret ) {
			return self::invalid( 'secret', __( 'A webhook needs a secret so the front end can check that the call comes from this site.', 'flx-headless' ) );
		}

		if ( '' === $id ) {
			$id   = self::new_id( $name, $raw['frontends'] );
			$row  = array( 'id' => $id );
		}
		$row = array_merge(
			$row,
			array(
				'name'           => $name,
				'url'            => $url,
				'languages'      => $langs,
				'enabled'        => isset( $in['enabled'] ) ? (bool) $in['enabled'] : ( ! array_key_exists( 'enabled', $row ) || ! empty( $row['enabled'] ) ),
				'default'        => isset( $in['default'] ) ? (bool) $in['default'] : ( ! empty( $row['default'] ) || ! $raw['frontends'] ),
				'revalidate_url' => $hook,
				'secret'         => $secret,
				'preview_path'   => $preview,
			)
		);
		if ( null === $at ) {
			$raw['frontends'][] = $row;
			$at                 = count( $raw['frontends'] ) - 1;
		} else {
			$raw['frontends'][ $at ] = $row;
		}
		if ( ! empty( $row['default'] ) ) {
			foreach ( $raw['frontends'] as $i => $f ) {
				if ( $i !== $at ) {
					$raw['frontends'][ $i ]['default'] = false;
				}
			}
		}
		self::write( $raw );
		return self::dto( $row );
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 */
	private static function new_id( string $name, array $rows ): string {
		$base = sanitize_key( remove_accents( $name ) );
		$base = '' === $base ? 'frontend' : substr( $base, 0, 40 );
		$ids  = array_map( static fn( array $f ): string => flxhl_str( $f['id'] ?? '' ), $rows );
		$id   = $base;
		for ( $n = 2; in_array( $id, $ids, true ); $n++ ) {
			$id = $base . '-' . $n;
		}
		return $id;
	}

	/** @return true|WP_Error */
	public static function delete( string $id ) {
		$raw  = self::raw();
		$keep = array();
		$gone = false;
		foreach ( $raw['frontends'] as $f ) {
			if ( flxhl_str( $f['id'] ?? '' ) === $id ) {
				$gone = true;
				continue;
			}
			$keep[] = $f;
		}
		if ( ! $gone ) {
			return self::invalid( 'id', __( 'This front end does not exist any more.', 'flx-headless' ), 'NOT_FOUND' );
		}
		$raw['frontends'] = $keep;
		self::write( $raw );
		return true;
	}

	/** @return true|WP_Error */
	public static function set_wp_front( string $mode ) {
		if ( ! in_array( $mode, self::WP_FRONT, true ) ) {
			return self::invalid( 'wpFront', __( 'Choose what WordPress does with its own pages.', 'flx-headless' ) );
		}
		if ( 'keep' !== $mode && null === self::default() ) {
			return self::invalid( 'wpFront', __( 'Add a front end first; WordPress pages point visitors and search engines to it.', 'flx-headless' ) );
		}
		$raw             = self::raw();
		$raw['wp_front'] = $mode;
		self::write( $raw );
		return true;
	}

	private static function invalid( string $field, string $message, string $code = 'INVALID_INPUT' ): WP_Error {
		return new WP_Error( 'flxhl_' . strtolower( $code ), $message, array( 'graphql_code' => $code, 'field' => $field ) );
	}

	/* ------------------------------------------------------------------ */
	/* Reads                                                               */
	/* ------------------------------------------------------------------ */

	public static function settings(): FLXHL_Settings {
		$gql     = function_exists( 'flx_gql_settings' ) ? flxhl_arr( flx_gql_settings() ) : array();
		$cors    = function_exists( 'flx_gql_cors_origins' ) ? array_values( array_map( 'flxhl_str', flx_gql_cors_origins() ) ) : array();
		$persist = function_exists( 'flx_gql_persisted_only' ) && flx_gql_persisted_only();
		unset( $gql );
		return new FLXHL_Settings(
			self::frontends(),
			self::raw()['wp_front'],
			$cors,
			flxhl_home(),
			function_exists( 'graphql_get_endpoint_url' ) ? flxhl_str( graphql_get_endpoint_url() ) : home_url( '/graphql' ),
			admin_url( 'admin.php?page=flx-graphql' ),
			$persist,
			function_exists( 'flxl_languages' ),
			FLXHL_Revalidate_Service::log(),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Addresses                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Path of a WordPress address relative to its home (with the query and fragment), or null when the address
	 * is on another site or belongs to WordPress itself (admin, files, REST, GraphQL).
	 */
	public static function front_path( string $url ): ?string {
		if ( '' === $url ) {
			return null;
		}
		$rel = '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] );
		if ( ! $rel ) {
			$home = preg_replace( '#^https?:#', '', flxhl_home() );
			$bare = preg_replace( '#^https?:#', '', $url );
			if ( ! is_string( $home ) || ! is_string( $bare ) || 0 !== strpos( $bare, $home ) ) {
				return null;
			}
			$url = substr( $bare, strlen( $home ) );
			if ( '' !== $url && ! in_array( $url[0], array( '/', '?', '#' ), true ) ) {
				return null; // Another host or folder that only starts the same (example.com2).
			}
		} else {
			$base = flxhl_home_path();
			if ( '' !== $base && 0 === strpos( $url, $base ) ) {
				$url = substr( $url, strlen( $base ) );
			}
		}
		$url = '' === $url || '/' !== $url[0] ? '/' . $url : $url;
		return flxhl_path_is_backend( $url ) ? null : $url;
	}

	/** A WordPress address as the front end serves it; other addresses come back unchanged. */
	public static function to_front( string $url, ?FLXHL_Frontend $fe ): string {
		if ( null === $fe ) {
			return $url;
		}
		$path = self::front_path( $url );
		return null === $path ? $url : $fe->url . $path;
	}
}
