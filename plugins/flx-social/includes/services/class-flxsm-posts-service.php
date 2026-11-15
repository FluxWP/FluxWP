<?php
/**
 * Posts: list, read, save, state changes, delete, bulk actions, duplicate, per-account retry and
 * remote edit/delete, AI writing help. REST, GraphQL and MCP use the same methods.
 *
 * Methods that change a post return its ID (or a WP_Error); read it back with get().
 *
 * @package flx-social
 */

defined( 'ABSPATH' ) || exit;

final class FLXSM_Posts_Service {

	public const CACHE_TAG = 'flx-social:posts';

	/**
	 * One page of posts.
	 *
	 * @param array<string,mixed> $filters status, account, provider, q, tag, mine, from, to, asc.
	 * @param int                 $offset  Skip this many.
	 * @param int                 $limit   Page size.
	 */
	public static function page( array $filters, int $offset, int $limit ): FLXSM_Posts_Page {
		$res   = FLXSM_Cast::arr(
			flxsm_query_posts(
				$filters + array(
					'offset' => $offset,
					'per'    => $limit,
					'full'   => true,
				)
			)
		);
		$items = array();
		foreach ( FLXSM_Cast::rows( $res['items'] ?? array() ) as $row ) {
			$items[] = self::to_dto( $row );
		}
		return new FLXSM_Posts_Page( $items, FLXSM_Cast::int( $res['total'] ?? 0 ) );
	}

	/**
	 * One post with its accounts.
	 *
	 * @param int $id Post ID.
	 * @return FLXSM_Post|WP_Error
	 */
	public static function get( int $id ) {
		$row = flxsm_post_row( $id );
		if ( ! is_array( $row ) ) {
			return self::not_found();
		}
		return self::to_dto( FLXSM_Cast::arr( flxsm_post_public( $row ) ) );
	}

	/**
	 * Creates ($id 0) or changes a post. Keys left out stay as they are.
	 *
	 * @param array<string,mixed> $in Post input (title, content, media, tags, scheduled_at, repeat_days, note, targets, action, source).
	 * @param int                 $id Post ID; 0 creates.
	 * @return int|WP_Error Post ID.
	 */
	public static function save( array $in, int $id = 0 ) {
		if ( $id ) {
			$row = flxsm_post_row( $id );
			if ( ! is_array( $row ) ) {
				return self::not_found();
			}
			if ( ! flxsm_can_edit_post( $row ) ) {
				return self::forbidden( __( 'You cannot change this post.', 'flx-social' ) );
			}
		}
		$saved = flxsm_save_post( $in, $id );
		return is_wp_error( $saved ) ? $saved : (int) $saved;
	}

	/**
	 * Moves a post through its states: draft, submit, schedule, now, approve, reject, cancel.
	 *
	 * @param int    $id   Post ID.
	 * @param string $act  Action.
	 * @param string $note Reason (reject).
	 * @return int|WP_Error Post ID.
	 */
	public static function act( int $id, string $act, string $note = '' ) {
		$p = flxsm_post_row( $id );
		if ( ! is_array( $p ) ) {
			return self::not_found();
		}
		$act = sanitize_key( $act );
		if ( ( 'approve' === $act && ! flxsm_can_publish() ) || ! flxsm_can_edit_post( $p ) ) {
			return self::forbidden( __( 'You do not have permission for this action.', 'flx-social' ) );
		}
		if ( 'reject' === $act ) {
			global $wpdb;
			$wpdb->update( // phpcs:ignore WordPress.DB
				flxsm_table( 'posts' ),
				array(
					'status' => 'draft',
					'note'   => sanitize_textarea_field( $note ),
				),
				array( 'id' => $id )
			);
			do_action( 'flxsm_post_rejected', $id );
			return $id;
		}
		$res = flxsm_apply_action( $id, $act );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		flxsm_refresh_post_status( $id );
		return $id;
	}

	/**
	 * Deletes a post (and on the networks too, when asked).
	 *
	 * @param int  $id     Post ID.
	 * @param bool $remote Also delete on the networks that support it.
	 * @return true|WP_Error
	 */
	public static function delete( int $id, bool $remote = false ) {
		$p = flxsm_post_row( $id );
		if ( ! is_array( $p ) ) {
			return self::not_found();
		}
		if ( ! flxsm_can_edit_post( $p ) ) {
			return self::forbidden( __( 'You cannot delete this post.', 'flx-social' ) );
		}
		$left = FLXSM_Cast::strings( flxsm_delete_post( $id, $remote ) );
		if ( $left ) {
			return new WP_Error( 'flxsm_remote', __( 'Could not delete on some networks; the post stays in the list.', 'flx-social' ) . "\n" . implode( "\n", $left ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * One action on many posts; posts the user may not change are skipped.
	 *
	 * @param list<int> $ids Post IDs.
	 * @param string    $act Action (or "delete").
	 */
	public static function bulk( array $ids, string $act ): FLXSM_Bulk_Result {
		$act  = sanitize_key( $act );
		$done = 0;
		$errs = array();
		foreach ( $ids as $id ) {
			$p = flxsm_post_row( $id );
			if ( ! is_array( $p ) || ! flxsm_can_edit_post( $p ) ) {
				continue;
			}
			if ( 'delete' === $act ) {
				$left = flxsm_delete_post( $id, false );
			} else {
				$left = flxsm_apply_action( $id, $act );
				flxsm_refresh_post_status( $id );
			}
			if ( is_wp_error( $left ) ) {
				$errs[] = '#' . $id . ': ' . $left->get_error_message();
			} else {
				++$done;
			}
		}
		return new FLXSM_Bulk_Result( $done, $errs );
	}

	/**
	 * Copies a post as a new draft.
	 *
	 * @param int $id Post ID.
	 * @return int|WP_Error New post ID.
	 */
	public static function duplicate( int $id ) {
		$p = flxsm_post_row( $id );
		if ( ! is_array( $p ) ) {
			return self::not_found();
		}
		$targets = array();
		foreach ( FLXSM_Cast::rows( flxsm_targets_of( $id ) ) as $t ) {
			$media     = $t['media'] ?? null;
			$targets[] = array(
				'account_id' => FLXSM_Cast::int( $t['account_id'] ?? 0 ),
				'content'    => $t['content'] ?? null,
				'media'      => null === $media ? null : flxsm_dec( $media ),
				'options'    => flxsm_dec( $t['options'] ?? '' ),
			);
		}
		$title = FLXSM_Cast::str( $p['title'] ?? '' );
		$new   = flxsm_save_post(
			array(
				/* translators: %s: title */
				'title'   => '' !== $title ? sprintf( __( '%s (copy)', 'flx-social' ), $title ) : '',
				'content' => FLXSM_Cast::str( $p['content'] ?? '' ),
				'media'   => flxsm_dec( $p['media'] ?? '' ),
				'tags'    => explode( ',', FLXSM_Cast::str( $p['tags'] ?? '' ) ),
				'targets' => $targets,
				'source'  => 'duplicate',
			)
		);
		return is_wp_error( $new ) ? $new : (int) $new;
	}

	/**
	 * Tries a failed account again.
	 *
	 * @param int $target_id Target ID.
	 * @return int|WP_Error Post ID.
	 */
	public static function retry_target( int $target_id ) {
		if ( ! flxsm_can_publish() ) {
			return self::forbidden( __( 'You do not have permission for this action.', 'flx-social' ) );
		}
		$res = flxsm_retry_target( $target_id );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return self::post_of_target( $target_id );
	}

	/**
	 * Changes the text of a published post on the network.
	 *
	 * @param int                 $target_id Target ID.
	 * @param string|null         $content   New text; null keeps it.
	 * @param array<string,mixed> $options   Network options to change.
	 * @return int|WP_Error Post ID.
	 */
	public static function remote_edit( int $target_id, ?string $content, array $options = array() ) {
		if ( ! flxsm_can_publish() ) {
			return self::forbidden( __( 'You do not have permission for this action.', 'flx-social' ) );
		}
		$res = flxsm_remote_edit( $target_id, $content, $options );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 400 ) );
		}
		return self::post_of_target( $target_id );
	}

	/**
	 * Deletes a published post on the network.
	 *
	 * @param int $target_id Target ID.
	 * @return int|WP_Error Post ID.
	 */
	public static function remote_delete( int $target_id ) {
		if ( ! flxsm_can_publish() ) {
			return self::forbidden( __( 'You do not have permission for this action.', 'flx-social' ) );
		}
		$res = flxsm_remote_delete( $target_id );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 400 ) );
		}
		return self::post_of_target( $target_id );
	}

	/**
	 * Writing help through Flux AI.
	 *
	 * @param string       $mode      write, improve, shorten, hashtags, adapt, translate.
	 * @param string       $text      Current text.
	 * @param string       $brief     Idea to write from.
	 * @param list<string> $providers Networks the text is for.
	 * @param string       $tone      Tone.
	 * @param int          $limit     Character limit.
	 * @param string       $to        Target language (translate).
	 * @return array<string,string>|string|WP_Error Per-network texts for adapt, otherwise the text.
	 */
	public static function ai( string $mode, string $text, string $brief, array $providers, string $tone, int $limit, string $to ) {
		$res = flxsm_ai_write(
			sanitize_key( $mode ),
			array(
				'text'      => $text,
				'brief'     => $brief,
				'providers' => array_map( 'sanitize_key', $providers ),
				'tone'      => sanitize_text_field( $tone ),
				'limit'     => $limit,
				'to'        => sanitize_text_field( $to ),
			)
		);
		if ( is_wp_error( $res ) ) {
			$d = FLXSM_Cast::arr( $res->get_error_data() );
			return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => FLXSM_Cast::int( $d['status'] ?? 400 ) ) );
		}
		return is_array( $res ) ? FLXSM_Cast::map( $res ) : FLXSM_Cast::str( $res );
	}

	/**
	 * Legacy public post → typed post.
	 *
	 * @param array<mixed> $p flxsm_post_public() row.
	 */
	public static function to_dto( array $p ): FLXSM_Post {
		$targets = array();
		foreach ( FLXSM_Cast::rows( $p['targets'] ?? array() ) as $t ) {
			$own       = $t['media'] ?? null;
			$targets[] = new FLXSM_Target(
				FLXSM_Cast::int( $t['id'] ?? 0 ),
				FLXSM_Cast::int( $t['account_id'] ?? 0 ),
				FLXSM_Cast::str( $t['provider'] ?? '' ),
				FLXSM_Cast::str( $t['account'] ?? '' ),
				FLXSM_Cast::str( $t['avatar'] ?? '' ),
				FLXSM_Cast::str( $t['status'] ?? '' ),
				FLXSM_Cast::str( $t['remote_url'] ?? '' ),
				FLXSM_Cast::str( $t['error'] ?? '' ),
				FLXSM_Cast::int( $t['published_at'] ?? 0 ),
				FLXSM_Metrics::from_array( FLXSM_Cast::arr( $t['metrics'] ?? array() ) ),
				FLXSM_Caps::from_array( FLXSM_Cast::arr( $t['caps'] ?? array() ) ),
				isset( $t['content'] ) ? FLXSM_Cast::str( $t['content'] ) : null,
				is_array( $own ) ? FLXSM_Media::list_from( $own ) : null,
				FLXSM_Options::known( FLXSM_Cast::arr( (array) ( $t['options'] ?? array() ) ) )
			);
		}
		return new FLXSM_Post(
			FLXSM_Cast::int( $p['id'] ?? 0 ),
			FLXSM_Cast::str( $p['status'] ?? 'draft' ),
			FLXSM_Cast::str( $p['title'] ?? '' ),
			FLXSM_Cast::str( $p['content'] ?? '' ),
			FLXSM_Media::list_from( $p['media'] ?? array() ),
			FLXSM_Cast::strings( $p['tags'] ?? array() ),
			FLXSM_Cast::int( $p['scheduled_at'] ?? 0 ),
			FLXSM_Cast::int( $p['published_at'] ?? 0 ),
			FLXSM_Cast::int( $p['repeat_days'] ?? 0 ),
			FLXSM_Cast::str( $p['source'] ?? '' ),
			FLXSM_Cast::str( $p['note'] ?? '' ),
			FLXSM_Cast::str( $p['user'] ?? '' ),
			FLXSM_Cast::int( $p['user_id'] ?? 0 ),
			FLXSM_Cast::int( $p['approved_by'] ?? 0 ),
			FLXSM_Cast::int( $p['created'] ?? 0 ),
			FLXSM_Cast::int( $p['updated'] ?? 0 ),
			$targets
		);
	}

	/**
	 * Post of a target.
	 *
	 * @param int $target_id Target ID.
	 * @return int|WP_Error
	 */
	private static function post_of_target( int $target_id ) {
		$t = flxsm_target_row( $target_id );
		if ( ! is_array( $t ) ) {
			return self::not_found();
		}
		return FLXSM_Cast::int( $t['post_id'] ?? 0 );
	}

	private static function not_found(): WP_Error {
		return new WP_Error( 'flxsm_not_found', __( 'Record not found.', 'flx-social' ), array( 'status' => 404 ) );
	}

	private static function forbidden( string $msg ): WP_Error {
		return new WP_Error( 'rest_forbidden', $msg, array( 'status' => 403 ) );
	}
}
