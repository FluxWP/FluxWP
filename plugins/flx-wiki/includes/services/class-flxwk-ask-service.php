<?php
/**
 * "Ask the wiki" (Flux AI): permission, one question at a time, hourly limit, answer.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ask the wiki service.
 */
final class FLXWK_Ask_Service {

	/** Shortest question (characters). */
	public const MIN_LENGTH = 3;

	/** Longest question (characters). */
	public const MAX_LENGTH = 1000;

	/**
	 * Whether the viewer may ask (setting, Flux AI state, login).
	 */
	public static function allowed(): bool {
		return (bool) flxwk_can_ask();
	}

	/**
	 * Permission error for a viewer who may not ask.
	 */
	public static function denied(): WP_Error {
		return new WP_Error( 'flxwk_ask_off', __( 'Log in to ask a question.', 'flx-wiki' ), array( 'status' => 403 ) );
	}

	/**
	 * Answers a question from the wiki content.
	 *
	 * @param string $question Question.
	 * @param int    $page_id  Page the visitor reads (its content comes first); 0 for none.
	 * @return FLXWK_Answer|WP_Error
	 */
	public static function ask( string $question, int $page_id = 0 ) {
		if ( ! self::allowed() ) {
			return self::denied();
		}
		$q = trim( sanitize_textarea_field( $question ) );
		if ( mb_strlen( $q ) < self::MIN_LENGTH ) {
			return new WP_Error( 'flxwk_short', __( 'The question is too short.', 'flx-wiki' ), array( 'status' => 400, 'field' => 'question' ) );
		}
		$lock = flxwk_ask_lock();
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			$limit = flxwk_rate_hit();
			if ( is_wp_error( $limit ) ) {
				return $limit;
			}
			$res = flxwk_ask( $q, max( 0, $page_id ) );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$sources = array();
			foreach ( (array) ( $res['sources'] ?? array() ) as $s ) {
				if ( is_array( $s ) ) {
					$sources[] = array(
						'title' => flxwk_s( $s['title'] ?? '' ),
						'url'   => flxwk_s( $s['url'] ?? '' ),
					);
				}
			}
			return new FLXWK_Answer( flxwk_s( $res['answer'] ?? '' ), flxwk_s( $res['html'] ?? '' ), $sources );
		} finally {
			delete_option( flxwk_s( $lock ) );
		}
	}
}
