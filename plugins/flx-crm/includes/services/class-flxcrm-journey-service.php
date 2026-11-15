<?php
/**
 * Journeys (automatic contact flows): list, save, turn on / pause, delete, templates and the editor's vocabulary.
 * A new journey starts as a draft; nothing is sent until it is turned on.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Journey_Service {

	public const TAG = 'flx-crm:journeys';

	/**
	 * Row → typed journey.
	 *
	 * @param array<mixed> $j Decoded journey (FLXCRM_Journeys::get).
	 */
	public static function build( array $j ): FLXCRM_Journey {
		$id    = FLXCRM_Val::int( $j['id'] ?? 0 );
		$n     = FLXCRM_Journeys::run_counts( $id );
		$st    = FLXCRM_Val::arr( $j['stats'] ?? array() );
		$tr    = FLXCRM_Val::arr( $j['trigger_def'] ?? array() );
		$o     = FLXCRM_Val::arr( $j['opts'] ?? array() );
		$steps = array();
		foreach ( FLXCRM_Val::arr( $j['steps'] ?? array() ) as $s ) {
			$s    = FLXCRM_Val::arr( $s );
			$step = array( 'type' => FLXCRM_Val::str( $s['type'] ?? '' ) );
			foreach ( array( 'days', 'hours' ) as $k ) {
				if ( isset( $s[ $k ] ) ) {
					$step[ $k ] = FLXCRM_Val::int( $s[ $k ] );
				}
			}
			foreach ( array( 'subject', 'text', 'cond', 'value' ) as $k ) {
				if ( isset( $s[ $k ] ) ) {
					$step[ $k ] = FLXCRM_Val::str( $s[ $k ] );
				}
			}
			if ( isset( $s['tag'] ) ) {
				$step['tag'] = FLXCRM_Val::str( $s['tag'] );
			}
			$steps[] = $step;
		}
		return new FLXCRM_Journey(
			$id,
			FLXCRM_Val::str( $j['name'] ?? '' ),
			FLXCRM_Val::str( $j['status'] ?? 'draft' ),
			FLXCRM_Val::str( FLXCRM_Journeys::describe( $j ) ),
			FLXCRM_Val::str( $tr['type'] ?? '' ),
			FLXCRM_Val::str( $tr['value'] ?? '' ),
			$steps,
			array(
				'goal'         => FLXCRM_Val::str( $o['goal'] ?? '' ),
				'reenter_days' => FLXCRM_Val::int( $o['reenter_days'] ?? 0 ),
				'quiet_from'   => FLXCRM_Val::int( $o['quiet_from'] ?? 21 ),
				'quiet_to'     => FLXCRM_Val::int( $o['quiet_to'] ?? 9 ),
				'cap_week'     => FLXCRM_Val::int( $o['cap_week'] ?? 3 ),
			),
			array(
				'entered'  => FLXCRM_Val::int( $st['entered'] ?? 0 ),
				'finished' => FLXCRM_Val::int( $n['done'] ?? 0 ),
				'goal'     => FLXCRM_Val::int( $n['goal'] ?? 0 ),
				'sent'     => FLXCRM_Val::int( $st['sent'] ?? 0 ),
				'skipped'  => FLXCRM_Val::int( $st['skipped'] ?? 0 ),
				'active'   => FLXCRM_Val::int( $n['active'] ?? 0 ),
			)
		);
	}

	/**
	 * @return list<FLXCRM_Journey>
	 */
	public static function all(): array {
		FLXCRM_Journeys::tick();
		$out = array();
		foreach ( FLXCRM_Journeys::all() as $j ) {
			$out[] = self::build( FLXCRM_Val::arr( $j ) );
		}
		return $out;
	}

	public static function get( int $id ): ?FLXCRM_Journey {
		$j = FLXCRM_Journeys::get( $id );
		return is_array( $j ) ? self::build( $j ) : null;
	}

	/**
	 * Saves a journey (new = draft; saving an active one pauses it).
	 *
	 * @param array{name: string, trigger_type: string, trigger_value: string, steps: list<array<string,mixed>>, opts: array<string,mixed>} $in Fields.
	 * @return FLXCRM_Journey|WP_Error
	 */
	public static function save( array $in, int $id = 0 ) {
		$res = FLXCRM_Journeys::save(
			array(
				'name'    => $in['name'],
				'trigger' => array( 'type' => $in['trigger_type'], 'value' => $in['trigger_value'] ),
				'steps'   => $in['steps'],
				'opts'    => $in['opts'],
			),
			$id
		);
		if ( $res instanceof WP_Error ) {
			return FLXCRM_Contact_Service::error( (string) $res->get_error_code(), $res->get_error_message(), 'flxcrm_missing' === $res->get_error_code() || str_contains( $res->get_error_message(), 'not found' ) ? 'NOT_FOUND' : 'INVALID_INPUT' );
		}
		self::changed();
		$j = self::get( $res );
		return $j ? $j : FLXCRM_Contact_Service::error( 'flxcrm_missing', __( 'Journey not found.', 'flx-crm' ), 'NOT_FOUND' );
	}

	/**
	 * @param string $status active | paused | draft.
	 * @return FLXCRM_Journey|WP_Error
	 */
	public static function set_status( int $id, string $status ) {
		if ( ! FLXCRM_Journeys::set_status( $id, $status ) ) {
			return FLXCRM_Contact_Service::error( 'flxcrm_journey', __( 'Journey not found.', 'flx-crm' ), 'NOT_FOUND' );
		}
		self::changed();
		$j = self::get( $id );
		return $j ? $j : FLXCRM_Contact_Service::error( 'flxcrm_missing', __( 'Journey not found.', 'flx-crm' ), 'NOT_FOUND' );
	}

	public static function delete( int $id ): bool {
		if ( null === FLXCRM_Journeys::get( $id ) ) {
			return false;
		}
		FLXCRM_Journeys::delete( $id );
		self::changed();
		return true;
	}

	/**
	 * Saves a template as a draft.
	 *
	 * @return FLXCRM_Journey|WP_Error
	 */
	public static function from_template( string $key ) {
		$tpl = FLXCRM_Journeys::templates()[ sanitize_key( $key ) ] ?? null;
		if ( ! is_array( $tpl ) ) {
			return FLXCRM_Contact_Service::error( 'flxcrm_journey', __( 'Unknown template.', 'flx-crm' ), 'INVALID_INPUT', 'template' );
		}
		$res = FLXCRM_Journeys::save( $tpl );
		if ( $res instanceof WP_Error ) {
			return FLXCRM_Contact_Service::error( (string) $res->get_error_code(), $res->get_error_message(), 'INVALID_INPUT' );
		}
		self::changed();
		$j = self::get( $res );
		return $j ? $j : FLXCRM_Contact_Service::error( 'flxcrm_missing', __( 'Journey not found.', 'flx-crm' ), 'NOT_FOUND' );
	}

	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( self::TAG );
		}
	}

	/**
	 * What the journey editor offers.
	 *
	 * @return array{triggers: list<array{key: string, label: string}>, step_types: list<array{key: string, label: string}>, conditions: list<array{key: string, label: string}>, statuses: list<array{key: string, label: string}>, goals: list<array{key: string, label: string}>, templates: list<array{key: string, label: string}>, defaults: array{goal: string, reenter_days: int, quiet_from: int, quiet_to: int, cap_week: int}, trigger_values: list<array{trigger: string, values: list<array{value: string, label: string}>}>, mail_active: bool}
	 */
	public static function config(): array {
		$pairs = static function ( array $m ): array {
			$out = array();
			foreach ( $m as $k => $l ) {
				$out[] = array( 'key' => (string) $k, 'label' => FLXCRM_Val::str( $l ) );
			}
			return $out;
		};
		$goals = array();
		foreach ( array( 'donation', 'order', 'form', 'signup', 'message_in' ) as $g ) {
			$goals[] = array( 'key' => $g, 'label' => FLXCRM_Val::str( flxcrm_event_label( $g ) ) );
		}
		$tpls = array();
		foreach ( FLXCRM_Journeys::templates() as $k => $t ) {
			$tpls[] = array( 'key' => (string) $k, 'label' => FLXCRM_Val::str( FLXCRM_Val::arr( $t )['name'] ?? $k ) );
		}
		$tags = array();
		foreach ( FLXCRM_Tags::all() as $t ) {
			$t      = FLXCRM_Val::arr( $t );
			$tags[] = array( 'value' => FLXCRM_Val::str( $t['id'] ?? '' ), 'label' => FLXCRM_Val::str( $t['name'] ?? '' ) );
		}
		$roles = array();
		foreach ( flxcrm_roles() as $k => $r ) {
			$roles[] = array( 'value' => (string) $k, 'label' => FLXCRM_Val::str( FLXCRM_Val::arr( $r )['label'] ?? $k ) );
		}
		$segs = array();
		foreach ( flxcrm_rfm_segments_labels() as $k => $l ) {
			$segs[] = array( 'value' => (string) $k, 'label' => FLXCRM_Val::str( $l ) );
		}
		$events = array();
		foreach ( array_diff_key( flxcrm_event_types(), array_flip( array( 'journey', 'journey_skip' ) ) ) as $k => $e ) {
			$events[] = array( 'value' => (string) $k, 'label' => FLXCRM_Val::str( FLXCRM_Val::arr( $e )[0] ?? $k ) );
		}
		$d = FLXCRM_Journeys::defaults();
		return array(
			'triggers'       => $pairs( FLXCRM_Journeys::triggers() ),
			'step_types'     => $pairs( FLXCRM_Journeys::step_types() ),
			'conditions'     => $pairs( FLXCRM_Journeys::conditions() ),
			'statuses'       => $pairs( FLXCRM_Journeys::statuses() ),
			'goals'          => $goals,
			'templates'      => $tpls,
			'defaults'       => array(
				'goal'         => FLXCRM_Val::str( $d['goal'] ?? '' ),
				'reenter_days' => FLXCRM_Val::int( $d['reenter_days'] ?? 0 ),
				'quiet_from'   => FLXCRM_Val::int( $d['quiet_from'] ?? 21 ),
				'quiet_to'     => FLXCRM_Val::int( $d['quiet_to'] ?? 9 ),
				'cap_week'     => FLXCRM_Val::int( $d['cap_week'] ?? 3 ),
			),
			'trigger_values' => array(
				array( 'trigger' => 'segment', 'values' => $segs ),
				array( 'trigger' => 'tag', 'values' => $tags ),
				array( 'trigger' => 'role', 'values' => $roles ),
				array( 'trigger' => 'event', 'values' => $events ),
			),
			'mail_active'    => function_exists( 'flxi_table' ),
		);
	}
}
