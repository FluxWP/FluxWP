<?php
/**
 * The approval queue: actions the assistant prepared that wait for the signed-in user, and open change
 * requests. The classic page and GraphQL share this class.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

final class FLXAI_Approvals_Service {

	public const CACHE_TAG = 'flx-ai:approvals';

	/** The signed-in user's queue. */
	public static function queue(): FLXAI_Approval_Queue {
		$items = array();
		foreach ( FLXAI_Cast::arr( flxai_approvals_list() ) as $row ) {
			$r    = FLXAI_Cast::arr( $row );
			$task = FLXAI_Cast::arr( $r['task'] ?? array() );
			$args = $r['args'] ?? array();
			$json = wp_json_encode( $args ? $args : new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$items[] = new FLXAI_Approval(
				FLXAI_Cast::int( $r['chat_id'] ?? 0 ),
				FLXAI_Cast::str( $r['chat_title'] ?? '' ),
				FLXAI_Cast::str( $r['call_id'] ?? '' ),
				FLXAI_Cast::str( $r['tool'] ?? '' ),
				FLXAI_Cast::str( $r['title'] ?? '' ),
				false === $json ? '{}' : $json,
				mb_substr( FLXAI_Cast::str( $r['note'] ?? '' ), 0, 1500 ),
				FLXAI_Cast::int( $r['ts'] ?? 0 ),
				FLXAI_Cast::int( $task['id'] ?? 0 ),
				FLXAI_Cast::str( $task['title'] ?? '' )
			);
		}
		$changes = array();
		if ( function_exists( 'flxai_changes_list' ) ) {
			foreach ( FLXAI_Cast::arr( flxai_changes_list( array( 'user_id' => get_current_user_id(), 'kind' => 'pr', 'status' => 'open' ) ) ) as $row ) {
				$r     = FLXAI_Cast::arr( $row );
				$tid   = FLXAI_Cast::int( $r['task_id'] ?? 0 );
				$task  = $tid ? FLXAI_Cast::arr( flxai_task_get( $tid ) ) : array();
				$files = array();
				foreach ( FLXAI_Cast::arr( $r['files'] ?? array() ) as $f ) {
					$f       = FLXAI_Cast::arr( $f );
					$files[] = new FLXAI_Change_File( FLXAI_Cast::str( $f['path'] ?? '' ), FLXAI_Cast::str( $f['op'] ?? 'edit' ), FLXAI_Cast::int( $f['add'] ?? 0 ), FLXAI_Cast::int( $f['del'] ?? 0 ) );
				}
				$changes[] = new FLXAI_Change(
					FLXAI_Cast::int( $r['id'] ?? 0 ),
					FLXAI_Cast::str( $r['title'] ?? '' ),
					FLXAI_Cast::int( $r['chat_id'] ?? 0 ),
					$tid,
					FLXAI_Cast::str( $task['title'] ?? '' ),
					FLXAI_Cast::int( $r['created'] ?? 0 ),
					$files
				);
			}
		}
		return new FLXAI_Approval_Queue( $items, $changes, FLXAI_Cast::int( flxai_approvals_count() ) );
	}

	/**
	 * Approves or rejects one action; `$args` (JSON) runs it with the edited input.
	 *
	 * @param int         $chat_id Chat.
	 * @param string      $call_id Tool call.
	 * @param bool        $approve Approve (false: reject).
	 * @param string|null $args    Edited input as JSON, or null to run it as prepared.
	 * @return FLXAI_Approval_Result|WP_Error
	 */
	public static function decide( int $chat_id, string $call_id, bool $approve, ?string $args = null ) {
		$decoded = null;
		if ( null !== $args && '' !== trim( $args ) ) {
			$decoded = json_decode( $args, true );
			if ( ! is_array( $decoded ) ) {
				return new WP_Error( 'flxai_invalid_json', __( 'The input is not valid JSON; nothing was run.', 'flx-ai' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'args' ) );
			}
		}
		$r = flxai_approval_decide( $chat_id, $call_id, $approve, $decoded );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		$state = FLXAI_Cast::str( FLXAI_Cast::arr( $r )['state'] ?? 'done' );
		self::changed();
		return new FLXAI_Approval_Result( $state, self::queue() );
	}

	/**
	 * Merges or rejects an open change request.
	 *
	 * @return FLXAI_Approval_Queue|WP_Error
	 */
	public static function decide_change( int $id, bool $merge ) {
		if ( ! function_exists( 'flxai_change_decide' ) ) {
			return new WP_Error( 'flxai_not_found', __( 'Change not found.', 'flx-ai' ), array( 'graphql_code' => 'NOT_FOUND' ) );
		}
		$c = FLXAI_Cast::arr( flxai_change_get( $id ) );
		if ( ! $c || FLXAI_Cast::int( $c['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return new WP_Error( 'flxai_not_found', __( 'Change not found.', 'flx-ai' ), array( 'graphql_code' => 'NOT_FOUND' ) );
		}
		$r = flxai_change_decide( $c, $merge );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		$r    = FLXAI_Cast::arr( $r );
		$left = array_merge( FLXAI_Cast::strings( $r['conflicts'] ?? array() ), array_map( 'strval', array_keys( FLXAI_Cast::arr( $r['errors'] ?? array() ) ) ) );
		self::changed();
		if ( $merge && $left ) {
			/* translators: %s: file paths */
			return new WP_Error( 'flxai_change_partial', sprintf( __( 'Some files were not applied: %s', 'flx-ai' ), implode( ', ', $left ) ), array( 'graphql_code' => 'CONFLICT' ) );
		}
		return self::queue();
	}

	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}
}
