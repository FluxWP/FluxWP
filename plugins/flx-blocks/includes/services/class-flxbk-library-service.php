<?php
/**
 * Design library service: this site's pattern catalogue, linked libraries (other Flux sites) and
 * sharing. The editor's REST routes (Flx_Blocks_Design_Library) and GraphQL both call this.
 *
 * @package flx-blocks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Design library.
 */
final class FLXBK_Library_Service {

	/** Cache tag of the library (sources, sharing, catalogue). */
	const TAG = 'flx-blocks:library';

	/**
	 * This site's catalogue.
	 */
	public static function local(): FLXBK_Library {
		return self::library( Flx_Blocks_Design_Library::catalogue(), null );
	}

	/**
	 * A linked library's catalogue (cached for 30 minutes unless $refresh).
	 *
	 * @param string $id      Source id.
	 * @param bool   $refresh Skip the cache.
	 * @return FLXBK_Library|WP_Error
	 */
	public static function remote( string $id, bool $refresh = false ) {
		foreach ( self::source_rows() as $row ) {
			if ( $row['id'] === $id ) {
				$data = Flx_Blocks_Design_Library::fetch_remote( $row, $refresh );
				return $data instanceof WP_Error ? $data : self::library( $data, $id );
			}
		}
		return new WP_Error( 'flxbk_library_source', __( 'Library not found.', 'flx-blocks' ), array( 'status' => 404 ) );
	}

	/**
	 * The catalogue of a source (null = this site).
	 *
	 * @param string|null $source  Source id.
	 * @param bool        $refresh Skip the linked library cache.
	 * @return FLXBK_Library|WP_Error
	 */
	public static function get( ?string $source, bool $refresh = false ) {
		return null === $source || '' === $source ? self::local() : self::remote( $source, $refresh );
	}

	/**
	 * Patterns of a catalogue, filtered by category and search text.
	 *
	 * @param FLXBK_Library $library  Catalogue.
	 * @param string        $category Category slug or empty.
	 * @param string        $search   Words to look for in title, description, keywords.
	 * @return list<FLXBK_Pattern>
	 */
	public static function filter( FLXBK_Library $library, string $category, string $search ): array {
		$words = array_values( array_filter( preg_split( '/\s+/u', mb_strtolower( trim( $search ) ) ) ?: array() ) );
		$out   = array();
		foreach ( $library->patterns as $p ) {
			if ( '' !== $category && ! in_array( $category, $p->categories, true ) ) {
				continue;
			}
			if ( $words ) {
				$hay = mb_strtolower( $p->title . ' ' . $p->description . ' ' . implode( ' ', $p->keywords ) . ' ' . $p->id );
				foreach ( $words as $w ) {
					if ( ! str_contains( $hay, $w ) ) {
						continue 2;
					}
				}
			}
			$out[] = $p;
		}
		return $out;
	}

	/**
	 * One pattern.
	 *
	 * @param string|null $source Source id (null = this site).
	 * @param string      $id     Pattern id.
	 * @return FLXBK_Pattern|WP_Error
	 */
	public static function pattern( ?string $source, string $id ) {
		$library = self::get( $source );
		if ( $library instanceof WP_Error ) {
			return $library;
		}
		foreach ( $library->patterns as $p ) {
			if ( $p->id === $id ) {
				return $p;
			}
		}
		return new WP_Error( 'flxbk_pattern', __( 'Pattern not found.', 'flx-blocks' ), array( 'status' => 404 ) );
	}

	/**
	 * Linked libraries, without keys.
	 *
	 * @return list<FLXBK_Library_Source>
	 */
	public static function sources(): array {
		$out = array();
		foreach ( self::source_rows() as $row ) {
			$out[] = new FLXBK_Library_Source( $row['id'], $row['name'], $row['url'], '' !== $row['key'] );
		}
		return $out;
	}

	/**
	 * Adds a linked library after checking that it answers.
	 *
	 * @param string $url  Site address.
	 * @param string $name Name (empty: the other site's name).
	 * @param string $key  Library key of the other site (empty when it shares without one).
	 * @return FLXBK_Library_Source|WP_Error
	 */
	public static function add_source( string $url, string $name, #[\SensitiveParameter] string $key ) {
		$url = esc_url_raw( trim( $url ), array( 'https', 'http' ) );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'flxbk_library_url', __( 'Enter a valid site address.', 'flx-blocks' ), array( 'status' => 400, 'graphql_code' => 'INVALID_INPUT', 'field' => 'url' ) );
		}
		$url  = trailingslashit( (string) preg_replace( '#/wp-json/.*$#', '', $url ) );
		$name = sanitize_text_field( $name );
		$row  = array(
			'id'   => substr( md5( $url . wp_rand() ), 0, 12 ),
			'name' => '' !== $name ? $name : (string) wp_parse_url( $url, PHP_URL_HOST ),
			'url'  => $url,
			'key'  => sanitize_text_field( $key ),
		);
		$test = Flx_Blocks_Design_Library::fetch_remote( $row, true );
		if ( $test instanceof WP_Error ) {
			$test->add_data( array_merge( (array) $test->get_error_data(), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'url' ) ) );
			return $test;
		}
		if ( '' === $name && ! empty( $test['name'] ) && is_string( $test['name'] ) ) {
			$row['name'] = sanitize_text_field( $test['name'] );
		}
		$rows   = self::source_rows();
		$rows[] = $row;
		update_option( Flx_Blocks_Design_Library::OPT_SOURCES, $rows, false );
		self::purge();
		return new FLXBK_Library_Source( $row['id'], $row['name'], $row['url'], '' !== $row['key'] );
	}

	/**
	 * Removes a linked library.
	 *
	 * @param string $id Source id.
	 * @return true|WP_Error
	 */
	public static function remove_source( string $id ) {
		$rows = self::source_rows();
		$keep = array_values( array_filter( $rows, static fn( array $r ): bool => $r['id'] !== $id ) );
		if ( count( $keep ) === count( $rows ) ) {
			return new WP_Error( 'flxbk_library_source', __( 'Library not found.', 'flx-blocks' ), array( 'status' => 404 ) );
		}
		update_option( Flx_Blocks_Design_Library::OPT_SOURCES, $keep, false );
		delete_transient( 'flxbk_lib_' . md5( $id ) );
		self::purge();
		return true;
	}

	/**
	 * This site's sharing.
	 */
	public static function share(): FLXBK_Library_Share {
		$s = Flx_Blocks_Design_Library::share_settings();
		return new FLXBK_Library_Share( (bool) $s['enabled'], (string) $s['key'], home_url( '/' ) );
	}

	/**
	 * Changes sharing. Turning it on without a key, or $regenerate, makes a new key.
	 *
	 * @param bool|null $enabled    New state (null = unchanged).
	 * @param bool      $regenerate New key.
	 */
	public static function update_share( ?bool $enabled, bool $regenerate ): FLXBK_Library_Share {
		$s = Flx_Blocks_Design_Library::share_settings();
		if ( null !== $enabled ) {
			$s['enabled'] = $enabled;
		}
		if ( $regenerate || ( $s['enabled'] && '' === $s['key'] ) ) {
			$s['key'] = wp_generate_password( 32, false );
		}
		update_option( Flx_Blocks_Design_Library::OPT_SHARE, $s, false );
		self::purge();
		return self::share();
	}

	/**
	 * Stored source rows with keys (server use only).
	 *
	 * @return list<array{id:string,name:string,url:string,key:string}>
	 */
	public static function source_rows(): array {
		$list = get_option( Flx_Blocks_Design_Library::OPT_SOURCES, array() );
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) || empty( $row['url'] ) || ! is_scalar( $row['id'] ) || ! is_scalar( $row['url'] ) ) {
				continue;
			}
			$out[] = array(
				'id'   => (string) $row['id'],
				'name' => isset( $row['name'] ) && is_scalar( $row['name'] ) ? (string) $row['name'] : (string) $row['url'],
				'url'  => (string) $row['url'],
				'key'  => isset( $row['key'] ) && is_scalar( $row['key'] ) ? (string) $row['key'] : '',
			);
		}
		return $out;
	}

	/**
	 * Catalogue array (docs/flx-blocks/LIBRARY.md) → typed library.
	 *
	 * @param array<mixed> $data   Catalogue.
	 * @param string|null  $source Source id.
	 */
	private static function library( array $data, ?string $source ): FLXBK_Library {
		$patterns = array();
		$counts   = array();
		foreach ( isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array() as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) || ! is_scalar( $item['id'] ) || ! isset( $item['content'] ) || ! is_string( $item['content'] ) ) {
				continue;
			}
			$cats = self::strings( $item['categories'] ?? array() );
			foreach ( $cats as $c ) {
				$counts[ $c ] = ( $counts[ $c ] ?? 0 ) + 1;
			}
			$patterns[] = new FLXBK_Pattern(
				(string) $item['id'],
				isset( $item['title'] ) && is_scalar( $item['title'] ) ? (string) $item['title'] : (string) $item['id'],
				isset( $item['description'] ) && is_scalar( $item['description'] ) ? (string) $item['description'] : '',
				$cats,
				self::strings( $item['keywords'] ?? array() ),
				isset( $item['viewportWidth'] ) && is_numeric( $item['viewportWidth'] ) ? max( 320, min( 2400, (int) $item['viewportWidth'] ) ) : 1200,
				$item['content'],
				$source
			);
		}
		$categories = array();
		foreach ( isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array() as $cat ) {
			if ( ! is_array( $cat ) || empty( $cat['slug'] ) || ! is_scalar( $cat['slug'] ) ) {
				continue;
			}
			$slug         = (string) $cat['slug'];
			$categories[] = new FLXBK_Pattern_Category( $slug, isset( $cat['label'] ) && is_scalar( $cat['label'] ) ? (string) $cat['label'] : $slug, $counts[ $slug ] ?? 0 );
		}
		return new FLXBK_Library(
			isset( $data['name'] ) && is_scalar( $data['name'] ) ? (string) $data['name'] : '',
			isset( $data['url'] ) && is_scalar( $data['url'] ) ? (string) $data['url'] : '',
			$source,
			$categories,
			$patterns
		);
	}

	/**
	 * Strings of a list.
	 *
	 * @param mixed $list Value.
	 * @return list<string>
	 */
	private static function strings( $list ): array {
		$out = array();
		foreach ( is_array( $list ) ? $list : array() as $v ) {
			if ( is_scalar( $v ) ) {
				$out[] = (string) $v;
			}
		}
		return $out;
	}

	/**
	 * Clears cached GraphQL reads of the library.
	 */
	private static function purge(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::TAG ) );
		}
	}
}
