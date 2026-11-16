<?php
/**
 * Scheduled tasks: the list, one task, saving, running now, pausing and deleting. The classic page and
 * GraphQL share this class.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

final class FLXAI_Tasks_Service {

	public const CACHE_TAG = 'flx-ai:tasks';

	/** The tasks page. */
	public static function overview(): FLXAI_Task_Overview {
		$tasks  = array();
		$unread = FLXAI_Cast::arr( flxai_tasks_unread() );
		$next   = 0;
		foreach ( FLXAI_Cast::arr( flxai_task_list( array( 'limit' => 200 ) ) ) as $row ) {
			$r = FLXAI_Cast::arr( $row );
			if ( 'active' === FLXAI_Cast::str( $r['status'] ?? '' ) ) {
				$n    = FLXAI_Cast::int( $r['next_run'] ?? 0 );
				$next = $n && ( ! $next || $n < $next ) ? $n : $next;
			}
			$tasks[] = self::task( $r, isset( $unread[ FLXAI_Cast::int( $r['id'] ?? 0 ) ] ) );
		}
		$skills = array();
		foreach ( FLXAI_Cast::arr( flxai_skills_all() ) as $sk ) {
			$sk       = FLXAI_Cast::arr( $sk );
			$skills[] = new FLXAI_Task_Skill( FLXAI_Cast::str( $sk['slug'] ?? '' ), FLXAI_Cast::str( $sk['name'] ?? '' ), 'active' === FLXAI_Cast::str( $sk['status'] ?? '' ) );
		}
		$me   = wp_get_current_user();
		$tick = FLXAI_Cast::int( get_option( 'flxai_tasks_tick', 0 ) );
		return new FLXAI_Task_Overview(
			$tasks,
			FLXAI_Cast::bool( flxai_setting( 'tasks_enabled', 1 ) ),
			$next ? $next : null,
			FLXAI_Cast::int( flxai_approvals_count() ),
			$tick ? $tick : null,
			function_exists( 'flxai_ws_ready' ) && flxai_ws_ready(),
			function_exists( 'flxi_send_message' ),
			$me->user_email,
			wp_timezone_string(),
			$skills,
			self::channels()
		);
	}

	public static function channels(): FLXAI_Task_Channels {
		$c = FLXAI_Cast::arr( flxai_notify_channels() );
		return new FLXAI_Task_Channels( ! empty( $c['email'] ), ! empty( $c['sms'] ), ! empty( $c['whatsapp'] ) );
	}

	/**
	 * One task as a value object.
	 *
	 * @param array<mixed> $t Row from flxai_task_get().
	 */
	public static function task( array $t, bool $unread = false ): FLXAI_Task {
		$meta  = FLXAI_Cast::arr( $t['meta'] ?? array() );
		$s     = FLXAI_Cast::arr( $t['schedule'] ?? array() );
		$hist  = FLXAI_Cast::arr( $meta['history'] ?? array() );
		$cost  = 0.0;
		$ms    = array();
		foreach ( $hist as $h ) {
			$h     = FLXAI_Cast::arr( $h );
			$cost += FLXAI_Cast::float( $h['cost'] ?? 0 );
			if ( ! empty( $h['ms'] ) ) {
				$ms[] = FLXAI_Cast::int( $h['ms'] );
			}
		}
		$owner = get_userdata( FLXAI_Cast::int( $t['user_id'] ?? 0 ) );
		$out   = '';
		if ( FLXAI_Cast::int( $t['last_run'] ?? 0 ) && 'error' !== FLXAI_Cast::str( $t['last_state'] ?? '' ) ) {
			$out = FLXAI_Cast::str( flxai_task_last_output( $t ) );
			if ( '' === $out ) {
				$out = FLXAI_Cast::str( $meta['summary'] ?? '' );
			}
		}
		$n    = FLXAI_Cast::arr( flxai_task_notify_cfg( $t ) );
		$to   = array();
		foreach ( FLXAI_Cast::arr( $n['to'] ?? array() ) as $r ) {
			$to[] = self::recipient( FLXAI_Cast::arr( $r ) );
		}
		$ws   = null;
		if ( function_exists( 'flxai_ws_clean_cfg' ) ) {
			$w  = FLXAI_Cast::arr( $meta['ws'] ?? array() );
			$ws = new FLXAI_Task_Workspace( FLXAI_Cast::str( $w['dir'] ?? '' ), FLXAI_Cast::str( $w['access'] ?? '', 'workspace' ) ?: 'workspace', FLXAI_Cast::strings( $w['folders'] ?? array() ) );
		}
		$last = FLXAI_Cast::int( $t['last_run'] ?? 0 );
		$next = FLXAI_Cast::int( $t['next_run'] ?? 0 );
		return new FLXAI_Task(
			FLXAI_Cast::int( $t['id'] ?? 0 ),
			FLXAI_Cast::str( $t['title'] ?? '' ),
			FLXAI_Cast::str( $t['prompt'] ?? '' ),
			new FLXAI_Task_Schedule(
				FLXAI_Cast::str( $s['freq'] ?? 'daily' ),
				FLXAI_Cast::str( $s['time'] ?? '09:00' ),
				array_values( array_map( static fn( $d ): int => FLXAI_Cast::int( $d ), FLXAI_Cast::arr( $s['days'] ?? array() ) ) ),
				FLXAI_Cast::int( $s['day'] ?? 1 ),
				FLXAI_Cast::int( $s['every'] ?? 6 ),
				FLXAI_Cast::str( $s['at'] ?? '' )
			),
			FLXAI_Cast::str( flxai_task_schedule_label( $s ) ),
			FLXAI_Cast::str( $t['status'] ?? 'active' ),
			$next ? $next : null,
			$last ? $last : null,
			FLXAI_Cast::str( $t['last_state'] ?? '' ),
			FLXAI_Cast::str( $t['last_error'] ?? '' ),
			mb_substr( $out, 0, 20000 ),
			FLXAI_Cast::int( $t['runs'] ?? 0 ),
			$cost,
			$ms ? (int) ( array_sum( $ms ) / count( $ms ) ) : 0,
			FLXAI_Cast::int( $t['chat_id'] ?? 0 ),
			$owner ? $owner->display_name : '',
			FLXAI_Cast::int( $t['user_id'] ?? 0 ) === get_current_user_id(),
			FLXAI_Cast::str( $meta['skill'] ?? '' ),
			$unread,
			new FLXAI_Task_Notify(
				FLXAI_Cast::str( $n['mode'] ?? 'none' ),
				! empty( $n['email'] ),
				! empty( $n['panel'] ),
				FLXAI_Cast::str( $n['whatsapp'] ?? '' ),
				! empty( $n['promo'] ),
				$to,
				FLXAI_Cast::str( flxai_task_notify_label( $t ) )
			),
			$ws
		);
	}

	/**
	 * @param array<mixed> $r Recipient row.
	 */
	public static function recipient( array $r ): FLXAI_Task_Recipient {
		return new FLXAI_Task_Recipient(
			FLXAI_Cast::str( $r['kind'] ?? '' ),
			FLXAI_Cast::int( $r['id'] ?? 0 ),
			FLXAI_Cast::str( $r['name'] ?? '' ),
			FLXAI_Cast::str( $r['email'] ?? '' ),
			FLXAI_Cast::str( $r['phone'] ?? '' ),
			FLXAI_Cast::str( $r['jid'] ?? '' ),
			FLXAI_Cast::str( $r['sub'] ?? '' ),
			FLXAI_Cast::strings( $r['channels'] ?? array() )
		);
	}

	/** Finds people, addresses and groups to notify. */
	public static function search( string $q ): FLXAI_Task_Search {
		$items = array();
		foreach ( FLXAI_Cast::arr( flxai_notify_find( $q ) ) as $r ) {
			$items[] = self::recipient( FLXAI_Cast::arr( $r ) );
		}
		return new FLXAI_Task_Search( $items, self::channels() );
	}

	/**
	 * Creates or changes a task. Fields you leave out keep their value (a new task starts active).
	 *
	 * @param array<mixed> $in Input.
	 * @return FLXAI_Task|WP_Error
	 */
	public static function save( array $in, int $id = 0 ) {
		$data = array();
		foreach ( array( 'title', 'prompt', 'skill' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$data[ $k ] = FLXAI_Cast::str( $in[ $k ] );
			}
		}
		if ( array_key_exists( 'active', $in ) ) {
			$data['status'] = FLXAI_Cast::bool( $in['active'] ) ? 'active' : 'paused';
		}
		if ( isset( $in['schedule'] ) ) {
			$s = FLXAI_Cast::arr( $in['schedule'] );
			$data['schedule'] = array_intersect_key( $s, array_flip( array( 'freq', 'time', 'days', 'day', 'every', 'at' ) ) );
			if ( isset( $data['schedule']['at'] ) ) {
				$data['schedule']['at'] = str_replace( 'T', ' ', FLXAI_Cast::str( $data['schedule']['at'] ) );
			}
		}
		if ( isset( $in['notify'] ) ) {
			$n  = FLXAI_Cast::arr( $in['notify'] );
			$to = array();
			foreach ( FLXAI_Cast::arr( $n['to'] ?? array() ) as $r ) {
				$to[] = FLXAI_Cast::arr( $r );
			}
			$data['notify'] = array_intersect_key( $n, array_flip( array( 'mode', 'email', 'panel', 'whatsapp', 'promo' ) ) );
			if ( array_key_exists( 'to', $n ) ) {
				$data['notify']['to'] = $to;
			}
		}
		if ( isset( $in['workspace'] ) ) {
			$w            = FLXAI_Cast::arr( $in['workspace'] );
			$data['ws']   = array();
			if ( isset( $w['access'] ) ) {
				$data['ws']['access'] = sanitize_key( FLXAI_Cast::str( $w['access'] ) );
			}
			if ( isset( $w['folders'] ) ) {
				$data['ws']['folders'] = implode( "\n", FLXAI_Cast::strings( $w['folders'] ) );
			}
		}
		$t = flxai_task_save( $data, $id );
		if ( $t instanceof WP_Error ) {
			return self::coded( $t );
		}
		self::changed();
		return self::task( FLXAI_Cast::arr( $t ) );
	}

	/** Starts a task in a few seconds. */
	public static function run( int $id ): FLXAI_Task|WP_Error {
		if ( ! flxai_task_get( $id ) ) {
			return self::missing();
		}
		flxai_task_queue_now( $id );
		self::changed();
		return self::task( FLXAI_Cast::arr( flxai_task_get( $id ) ) );
	}

	/** Pauses or resumes a task. */
	public static function set_active( int $id, bool $active ): FLXAI_Task|WP_Error {
		if ( ! flxai_task_get( $id ) ) {
			return self::missing();
		}
		return self::save( array( 'active' => $active ), $id );
	}

	public static function delete( int $id ): ?WP_Error {
		if ( ! flxai_task_get( $id ) ) {
			return self::missing();
		}
		flxai_task_delete( $id );
		self::changed();
		return null;
	}

	/** Turns the whole task system on or off. */
	public static function toggle( bool $on ): FLXAI_Task_Overview {
		flxai_update_settings( array( 'tasks_enabled' => $on ? 1 : 0 ) );
		self::changed();
		return self::overview();
	}

	/** The owner (or an administrator) opened the report. */
	public static function mark_read( int $id ): FLXAI_Task|WP_Error {
		$t = flxai_task_get( $id );
		if ( ! $t ) {
			return self::missing();
		}
		flxai_task_mark_read( $id );
		return self::task( FLXAI_Cast::arr( $t ) );
	}

	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}

	private static function missing(): WP_Error {
		return new WP_Error( 'flxai_not_found', __( 'Task not found.', 'flx-ai' ), array( 'graphql_code' => 'NOT_FOUND' ) );
	}

	/** Gives the older WP_Error a GraphQL code (400 → INVALID_INPUT, 404 → NOT_FOUND). */
	private static function coded( WP_Error $e ): WP_Error {
		$d      = FLXAI_Cast::arr( $e->get_error_data() );
		$status = FLXAI_Cast::int( $d['status'] ?? 400 );
		$field  = array(
			'flxai_task_prompt' => 'prompt',
			'flxai_task_at'     => 'schedule',
			'flxai_task_skill'  => 'skill',
		)[ $e->get_error_code() ] ?? '';
		$out = array( 'graphql_code' => 404 === $status ? 'NOT_FOUND' : 'INVALID_INPUT' );
		if ( '' !== $field ) {
			$out['field'] = $field;
		}
		return new WP_Error( (string) $e->get_error_code(), $e->get_error_message(), $out );
	}
}
