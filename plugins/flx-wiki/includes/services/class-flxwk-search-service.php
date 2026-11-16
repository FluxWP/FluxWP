<?php
/**
 * Wiki search: the same ranking for the live search box (REST), MCP and GraphQL.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wiki search service.
 *
 * @phpstan-type FlxWkHit array{id: int, title: string, url: string, section: string, heading: string, snippet: string, score: float}
 * @phpstan-type FlxWkSearch array{total: int, results: list<FlxWkHit>, fixed?: string}
 */
final class FLXWK_Search_Service {

	/** Longest query that is searched (characters). */
	public const MAX_QUERY = 200;

	/**
	 * Searches and returns the legacy array shape (REST and MCP answer with it unchanged).
	 *
	 * @param string $query   Words.
	 * @param string $section Section short name; empty for all.
	 * @param int    $limit   Best hits to return (1–50).
	 * @return FlxWkSearch
	 */
	public static function run( string $query, string $section = '', int $limit = 10 ): array {
		$q = trim( $query );
		if ( '' === $q ) {
			return array(
				'total'   => 0,
				'results' => array(),
			);
		}
		/** @var FlxWkSearch $res */
		$res = flxwk_search(
			mb_substr( $q, 0, self::MAX_QUERY ),
			array(
				'limit'   => $limit,
				'section' => sanitize_title( $section ),
			)
		);
		return $res;
	}

	/**
	 * Typed search result.
	 */
	public static function search( string $query, string $section = '', int $limit = 10 ): FLXWK_Search_Result {
		$res  = self::run( $query, $section, $limit );
		$hits = array();
		foreach ( $res['results'] as $h ) {
			$hits[] = new FLXWK_Search_Hit( (int) $h['id'], (string) $h['title'], (string) $h['url'], (string) $h['section'], (string) $h['heading'], (string) $h['snippet'], (float) $h['score'] );
		}
		return new FLXWK_Search_Result( (int) $res['total'], $hits, isset( $res['fixed'] ) ? (string) $res['fixed'] : null );
	}
}
