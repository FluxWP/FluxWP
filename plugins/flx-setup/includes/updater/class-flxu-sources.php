<?php
/**
 * Güncelleme kaynakları: ana sunucu + panelden eklenen diğer kaynaklar.
 *
 * Tür:
 * - server: FluxWP güncelleme protokolü (/v1/check, /v1/download), isteğe bağlı site anahtarı.
 * - github: public GitHub deposu; release tag'i <slug>-v<sürüm>, asset <slug>.zip, notta flx-meta.
 *
 * Ana kaynak ("main") eski tek sunucu ayarından gelir (FLX_UPDATE_SERVER / FLX_UPDATE_KEY sabitleri dahil).
 * Aynı paket birden çok kaynakta varsa: paket hangi kaynaktan kurulduysa oradan güncellenir;
 * kurulu değilse listede üstteki kaynak kazanır.
 */

defined( 'ABSPATH' ) || exit;

final class FLXU_Sources {

	const OPTION = 'flxu_sources';
	const STATUS = 'flxu_source_status';
	const CACHE  = 'flxu_source_catalogs';
	const PINS   = 'flxu_pins';

	/** Ek kaynak gelmez: FluxWP Extras (public) ana sunucudan anahtarsız gelir. */
	const DEFAULT_EXTRAS = array();

	/** Panelden eklenen kaynaklar (ana sunucu hariç). */
	public static function extra(): array {
		$list = get_site_option( self::OPTION, null );
		if ( ! is_array( $list ) ) {
			$list = self::DEFAULT_EXTRAS;
		}
		$out = array();
		foreach ( $list as $s ) {
			if ( is_array( $s ) && ! empty( $s['id'] ) && ! empty( $s['url'] ) ) {
				$out[] = wp_parse_args(
					$s,
					array(
						'name'    => $s['id'],
						'type'    => 'server',
						'key'     => '',
						'enabled' => 1,
					)
				);
			}
		}
		return $out;
	}

	public static function main(): array {
		return array(
			'id'      => 'main',
			'name'    => 'FluxWP',
			'type'    => 'server',
			'url'     => FLXU_Updater::server(),
			'key'     => FLXU_Updater::key(),
			'enabled' => 1,
			'main'    => true,
		);
	}

	/** Öncelik sırasıyla tüm kaynaklar. */
	public static function all(): array {
		return array_merge( array( self::main() ), self::extra() );
	}

	public static function get( string $id ): ?array {
		foreach ( self::all() as $s ) {
			if ( $s['id'] === $id ) {
				return $s;
			}
		}
		return null;
	}

	public static function host( array $s ): string {
		return 'github' === $s['type'] ? 'github.com' : strtolower( (string) wp_parse_url( $s['url'], PHP_URL_HOST ) );
	}

	/** "owner/repo" ya da boş. */
	public static function github_repo( string $url ): string {
		if ( 'github.com' !== strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) {
			return '';
		}
		$parts = array_values( array_filter( explode( '/', (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) );
		if ( count( $parts ) < 2 || ! preg_match( '/^[A-Za-z0-9_.-]+$/', $parts[0] ) || ! preg_match( '/^[A-Za-z0-9_.-]+$/', $parts[1] ) ) {
			return '';
		}
		return $parts[0] . '/' . preg_replace( '/\.git$/', '', $parts[1] );
	}

	/* ------------------------------------------------------------------ */
	/* Durum                                                               */
	/* ------------------------------------------------------------------ */

	public static function set_status( string $id, bool $ok, string $error, int $count = 0, string $code = '', int $retry = 0 ): void {
		$all        = get_site_option( self::STATUS, array() );
		$all        = is_array( $all ) ? $all : array();
		$all[ $id ] = array(
			'ok'      => $ok,
			'error'   => $error,
			'count'   => $count,
			'code'    => $code,
			'retry'   => $retry,
			'checked' => time(),
		);
		update_site_option( self::STATUS, $all );
	}

	public static function status( string $id ): array {
		$all = get_site_option( self::STATUS, array() );
		return is_array( $all ) && isset( $all[ $id ] ) && is_array( $all[ $id ] ) ? $all[ $id ] : array();
	}

	/** Hata veren, açık ek kaynaklar: [ {name, error} ]. */
	public static function failing(): array {
		$out = array();
		foreach ( self::extra() as $s ) {
			$st = self::status( $s['id'] );
			if ( ! empty( $s['enabled'] ) && ! empty( $st['checked'] ) && empty( $st['ok'] ) ) {
				$out[] = array(
					'name'  => $s['name'],
					'error' => FLXU_Updater::status_message( $st, '' !== (string) ( $s['key'] ?? '' ), self::host( $s ) ),
				);
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Katalog                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Kaynak kataloglarını birleştirir.
	 *
	 * @param array $parts id => katalog (öncelik sırasında).
	 */
	public static function merge( array $parts ): array {
		$pins    = get_site_option( self::PINS, array() );
		$pins    = is_array( $pins ) ? $pins : array();
		$names   = array();
		$urls    = array();
		foreach ( self::all() as $s ) {
			$names[ $s['id'] ] = $s['name'];
			$urls[ $s['id'] ]  = 'github' === $s['type'] ? $s['url'] : untrailingslashit( $s['url'] );
		}
		$has = array();
		foreach ( $parts as $id => $catalog ) {
			foreach ( $catalog as $slug => $p ) {
				$has[ $slug ][] = $id;
			}
		}
		$installed = self::installed_slugs();
		$out       = array();
		$changed   = false;
		foreach ( $has as $slug => $ids ) {
			$pick = $ids[0];
			if ( isset( $pins[ $slug ] ) && in_array( $pins[ $slug ], $ids, true ) ) {
				$pick = $pins[ $slug ];
			} elseif ( isset( $installed[ $slug ] ) && ( $pins[ $slug ] ?? '' ) !== $pick ) {
				// Kurulu paket: bulunduğu ilk kaynağa sabitle (sonradan eklenen kaynak onu ele geçiremez).
				$pins[ $slug ] = $pick;
				$changed       = true;
			}
			$out[ $slug ] = $parts[ $pick ][ $slug ] + array(
				'source'      => $pick,
				'source_name' => $names[ $pick ] ?? $pick,
				'source_url'  => $urls[ $pick ] ?? '',
				'sources'     => $ids,
			);
		}
		if ( $changed ) {
			update_site_option( self::PINS, $pins );
		}
		ksort( $out );
		return $out;
	}

	/** Paket kurulunca/güncellenince geldiği kaynağa sabitlenir. */
	public static function pin( string $slug, string $id ): void {
		$pins = get_site_option( self::PINS, array() );
		$pins = is_array( $pins ) ? $pins : array();
		if ( ( $pins[ $slug ] ?? '' ) !== $id ) {
			$pins[ $slug ] = $id;
			update_site_option( self::PINS, $pins );
		}
	}

	private static function installed_slugs(): array {
		$out = array_fill_keys( array_keys( FLXU_Updater::plugin_files() ), true );
		foreach ( array_keys( wp_get_themes() ) as $t ) {
			$out[ $t ] = true;
		}
		return $out;
	}

	/**
	 * Public GitHub deposunun release'lerinden katalog.
	 *
	 * @return array|null Başarısızsa null (durum yazılır).
	 */
	public static function fetch_github( array $s ): ?array {
		$repo = self::github_repo( $s['url'] );
		if ( '' === $repo ) {
			self::set_status( $s['id'], false, __( 'GitHub adresi owner/repo biçiminde değil.', 'flx-setup' ) );
			return null;
		}
		$res = wp_remote_get(
			'https://api.github.com/repos/' . $repo . '/releases?per_page=100',
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'flx-setup/' . FLXU_VERSION,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			/* translators: %s: error message */
			self::set_status( $s['id'], false, sprintf( __( 'Kaynağa ulaşılamadı: %s', 'flx-setup' ), $res->get_error_message() ) );
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code || ! is_array( $body ) ) {
			$msg = 404 === $code
				? __( 'Depo bulunamadı ya da public değil.', 'flx-setup' )
				/* translators: %d: HTTP status code */
				: sprintf( __( 'GitHub beklenmeyen yanıt verdi (HTTP %d).', 'flx-setup' ), $code );
			self::set_status( $s['id'], false, $msg );
			return null;
		}

		$by = array();
		foreach ( $body as $rel ) {
			$r = is_array( $rel ) ? self::parse_release( $rel ) : null;
			if ( $r ) {
				$by[ $r['slug'] ][] = $r;
			}
		}
		$out = array();
		foreach ( $by as $slug => $list ) {
			usort(
				$list,
				static function ( $a, $b ) {
					return version_compare( $b['version'], $a['version'] );
				}
			);
			$latest = $list[0];
			$out[ $slug ] = array(
				'slug'             => $slug,
				'type'             => $latest['type'],
				'name'             => $latest['name'],
				'version'          => $latest['version'],
				'package'          => $latest['package'],
				'requires'         => $latest['requires'],
				'tested'           => $latest['tested'],
				'requires_php'     => $latest['requires_php'],
				'requires_plugins' => $latest['requires_plugins'],
				'changelog'        => $latest['changelog'],
				'released'         => $latest['released'],
				'versions'         => array_slice(
					array_map(
						static function ( $r ) {
							return array(
								'version'  => $r['version'],
								'released' => $r['released'],
								'package'  => $r['package'],
							);
						},
						$list
					),
					0,
					20
				),
			);
		}
		self::set_status( $s['id'], true, '', count( $out ) );
		return $out;
	}

	/** Kararlı, zip'i olan release; yoksa null. */
	private static function parse_release( array $rel ): ?array {
		if ( ! empty( $rel['draft'] ) || ! empty( $rel['prerelease'] ) ) {
			return null;
		}
		if ( ! preg_match( '/^([a-z0-9][a-z0-9-]{0,63})-v([0-9][0-9A-Za-z.+-]*)$/', (string) ( $rel['tag_name'] ?? '' ), $m ) ) {
			return null;
		}
		$slug  = $m[1];
		$body  = (string) ( $rel['body'] ?? '' );
		$meta  = array();
		if ( preg_match( '/<!--\s*flx-meta\s+(\{.*?\})\s*-->/s', $body, $mm ) ) {
			$meta = json_decode( $mm[1], true );
			$meta = is_array( $meta ) ? $meta : array();
			$body = trim( str_replace( $mm[0], '', $body ) );
		}
		$package = '';
		foreach ( (array) ( $rel['assets'] ?? array() ) as $a ) {
			$url = (string) ( $a['browser_download_url'] ?? '' );
			if ( ( $a['name'] ?? '' ) === $slug . '.zip' && 'github.com' === strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) {
				$package = $url;
				break;
			}
		}
		if ( '' === $package ) {
			return null;
		}
		return array(
			'slug'             => $slug,
			'version'          => sanitize_text_field( $m[2] ),
			'type'             => 'theme' === ( $meta['type'] ?? '' ) ? 'theme' : 'plugin',
			'name'             => sanitize_text_field( (string) ( $meta['name'] ?? $slug ) ),
			'requires'         => sanitize_text_field( (string) ( $meta['requires'] ?? '' ) ),
			'tested'           => sanitize_text_field( (string) ( $meta['tested'] ?? '' ) ),
			'requires_php'     => sanitize_text_field( (string) ( $meta['requires_php'] ?? '' ) ),
			'requires_plugins' => FLXU_Updater::slug_list( $meta['requires_plugins'] ?? array() ),
			'changelog'        => wp_kses_post( wpautop( esc_html( $body ) ) ),
			'released'         => sanitize_text_field( (string) ( $rel['published_at'] ?? '' ) ),
			'package'          => esc_url_raw( $package ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Ekleme / silme                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Yeni kaynağı test edip ekler.
	 *
	 * @return true|WP_Error
	 */
	public static function add( string $name, string $url, string $key ) {
		$url = untrailingslashit( esc_url_raw( trim( $url ) ) );
		if ( '' === $url || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return new WP_Error( 'flxu_url', __( 'Adres https:// ile başlamalı.', 'flx-setup' ) );
		}
		$type = '' !== self::github_repo( $url ) ? 'github' : 'server';
		foreach ( self::all() as $s ) {
			if ( strtolower( $s['url'] ) === strtolower( $url ) ) {
				return new WP_Error( 'flxu_dup', __( 'Bu kaynak zaten listede.', 'flx-setup' ) );
			}
		}
		$id = sanitize_key( substr( $type . '-' . md5( $url ), 0, 20 ) );
		$s  = array(
			'id'      => $id,
			'name'    => '' !== trim( $name ) ? sanitize_text_field( $name ) : ( 'github' === $type ? self::github_repo( $url ) : (string) wp_parse_url( $url, PHP_URL_HOST ) ),
			'type'    => $type,
			'url'     => $url,
			'key'     => 'server' === $type ? sanitize_text_field( $key ) : '',
			'enabled' => 1,
		);
		$catalog = 'github' === $type ? self::fetch_github( $s ) : FLXU_Updater::instance()->fetch_server( $s );
		if ( null === $catalog ) {
			$st = self::status( $id );
			return new WP_Error( 'flxu_test', (string) ( $st['error'] ?? __( 'Kaynak test edilemedi.', 'flx-setup' ) ) );
		}
		$list   = self::extra();
		$list[] = $s;
		update_site_option( self::OPTION, $list );
		return true;
	}

	public static function remove( string $id ): void {
		update_site_option(
			self::OPTION,
			array_values(
				array_filter(
					self::extra(),
					static function ( $s ) use ( $id ) {
						return $s['id'] !== $id;
					}
				)
			)
		);
		$cache = get_site_option( self::CACHE, array() );
		if ( is_array( $cache ) ) {
			unset( $cache[ $id ] );
			update_site_option( self::CACHE, $cache );
		}
	}

	public static function toggle( string $id ): void {
		$list = self::extra();
		foreach ( $list as &$s ) {
			if ( $s['id'] === $id ) {
				$s['enabled'] = empty( $s['enabled'] ) ? 1 : 0;
			}
		}
		unset( $s );
		update_site_option( self::OPTION, $list );
	}

	/** Bir sırayı yukarı taşır (öncelik). */
	public static function move_up( string $id ): void {
		$list = self::extra();
		foreach ( $list as $i => $s ) {
			if ( $s['id'] === $id && $i > 0 ) {
				$prev           = $list[ $i - 1 ];
				$list[ $i - 1 ] = $s;
				$list[ $i ]     = $prev;
				break;
			}
		}
		update_site_option( self::OPTION, $list );
	}
}
