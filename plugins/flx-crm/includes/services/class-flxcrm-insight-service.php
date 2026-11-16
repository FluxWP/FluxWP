<?php
/**
 * The overview numbers, the shared vocabulary (roles, tags, event types…), scoring and the step-by-step
 * sync of past records.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Insight_Service {

	/**
	 * Overview counters and distributions.
	 *
	 * @return array{total: int, new30: int, new_prev: int, active: int, risk: int, ltv: float, paying: int, repeat: int, currency: string, active_days: int, risk_threshold: int, scored_at: int|null, synced_at: int|null, needs_sync: bool, sync_sources: list<string>, roles: list<array{key: string, label: string, color: string, count: int}>, segments: list<array{key: string, label: string, hint: string, tone: string, count: int, value: float}>, sources: list<array{key: string, label: string, count: int}>}
	 */
	public static function overview(): array {
		$s     = FLXCRM_Val::arr( FLXCRM_Contacts::stats() );
		$roles = array();
		foreach ( flxcrm_roles() as $k => $r ) {
			$r = FLXCRM_Val::arr( $r );
			if ( ! empty( $r['hidden'] ) ) {
				continue;
			}
			$n = FLXCRM_Val::int( FLXCRM_Val::arr( $s['roles'] ?? array() )[ $k ] ?? 0 );
			if ( $n < 1 && empty( $r['builtin'] ) ) {
				continue;
			}
			$roles[] = array( 'key' => (string) $k, 'label' => FLXCRM_Val::str( $r['label'] ?? $k ), 'color' => FLXCRM_Val::str( $r['color'] ?? '#64748b' ), 'count' => $n );
		}
		$segments = array();
		$by_seg   = FLXCRM_Val::arr( $s['segments'] ?? array() );
		foreach ( flxcrm_rfm_segments() as $k => $seg ) {
			$seg        = FLXCRM_Val::arr( $seg );
			$row        = FLXCRM_Val::arr( $by_seg[ $k ] ?? array() );
			$segments[] = array(
				'key'   => (string) $k,
				'label' => FLXCRM_Val::str( $seg[0] ?? $k ),
				'hint'  => FLXCRM_Val::str( $seg[1] ?? '' ),
				'tone'  => FLXCRM_Val::str( $seg[2] ?? '' ),
				'count' => FLXCRM_Val::int( $row[0] ?? 0 ),
				'value' => FLXCRM_Val::float( $row[1] ?? 0 ),
			);
		}
		$sources = array();
		foreach ( FLXCRM_Val::arr( $s['sources'] ?? array() ) as $k => $n ) {
			$sources[] = array( 'key' => (string) $k, 'label' => FLXCRM_Val::str( flxcrm_source_label( (string) $k ) ), 'count' => FLXCRM_Val::int( $n ) );
		}
		$names = array();
		foreach ( FLXCRM_Val::arr( FLXCRM_Sync::sources() ) as $src ) {
			$names[] = FLXCRM_Val::str( FLXCRM_Val::arr( $src )[0] ?? '' );
		}
		$synced = FLXCRM_Val::int( get_option( 'flxcrm_synced_at' ) );
		$scored = FLXCRM_Val::int( get_option( 'flxcrm_scored_at' ) );
		return array(
			'total'          => FLXCRM_Val::int( $s['total'] ?? 0 ),
			'new30'          => FLXCRM_Val::int( $s['new30'] ?? 0 ),
			'new_prev'       => FLXCRM_Val::int( $s['new_prev'] ?? 0 ),
			'active'         => FLXCRM_Val::int( $s['active'] ?? 0 ),
			'risk'           => FLXCRM_Val::int( $s['risk'] ?? 0 ),
			'ltv'            => FLXCRM_Val::float( $s['ltv'] ?? 0 ),
			'paying'         => FLXCRM_Val::int( $s['paying'] ?? 0 ),
			'repeat'         => FLXCRM_Val::int( $s['repeat'] ?? 0 ),
			'currency'       => flxcrm_currency(),
			'active_days'    => FLXCRM_Val::int( flxcrm_setting( 'active_days' ) ),
			'risk_threshold' => FLXCRM_Val::int( flxcrm_setting( 'risk_threshold' ) ),
			'scored_at'      => $scored > 0 ? $scored : null,
			'synced_at'      => $synced > 0 ? $synced : null,
			'needs_sync'     => 0 === $synced,
			'sync_sources'   => $names,
			'roles'          => $roles,
			'segments'       => $segments,
			'sources'        => $sources,
		);
	}

	/**
	 * Vocabulary the screens share.
	 *
	 * @return array{roles: list<array{key: string, label: string, color: string, builtin: bool, hidden: bool}>, rfm: list<array{key: string, label: string, hint: string, tone: string}>, event_types: list<array{key: string, label: string, icon: string, manual: bool}>, sources: list<array{key: string, label: string}>, consents: list<array{key: string, label: string}>, timeline_filters: list<array{key: string, label: string}>, currency: string, can_write: bool}
	 */
	public static function meta(): array {
		$roles = array();
		foreach ( flxcrm_roles() as $k => $r ) {
			$r       = FLXCRM_Val::arr( $r );
			$roles[] = array(
				'key'     => (string) $k,
				'label'   => FLXCRM_Val::str( $r['label'] ?? $k ),
				'color'   => FLXCRM_Val::str( $r['color'] ?? '#64748b' ),
				'builtin' => ! empty( $r['builtin'] ),
				'hidden'  => ! empty( $r['hidden'] ),
			);
		}
		$rfm = array();
		foreach ( flxcrm_rfm_segments() as $k => $s ) {
			$s     = FLXCRM_Val::arr( $s );
			$rfm[] = array( 'key' => (string) $k, 'label' => FLXCRM_Val::str( $s[0] ?? $k ), 'hint' => FLXCRM_Val::str( $s[1] ?? '' ), 'tone' => FLXCRM_Val::str( $s[2] ?? '' ) );
		}
		$manual = flxcrm_manual_event_types();
		$types  = array();
		foreach ( flxcrm_event_types() as $k => $t ) {
			$t       = FLXCRM_Val::arr( $t );
			$types[] = array( 'key' => (string) $k, 'label' => FLXCRM_Val::str( $t[0] ?? $k ), 'icon' => FLXCRM_Val::str( $t[1] ?? 'note' ), 'manual' => in_array( $k, $manual, true ) );
		}
		$sources = array();
		foreach ( flxcrm_sources() as $k => $l ) {
			$sources[] = array( 'key' => (string) $k, 'label' => FLXCRM_Val::str( $l ) );
		}
		$consents = array();
		foreach ( FLXCRM_Val::arr( FLXCRM_Contacts::consent_labels() ) as $k => $l ) {
			$consents[] = array( 'key' => (string) $k, 'label' => $l );
		}
		$filters = array();
		foreach ( FLXCRM_Contact_Service::timeline_filters() as $k => $f ) {
			$filters[] = array( 'key' => (string) $k, 'label' => $f[0] );
		}
		return array(
			'roles'            => $roles,
			'rfm'              => $rfm,
			'event_types'      => $types,
			'sources'          => $sources,
			'consents'         => $consents,
			'timeline_filters' => $filters,
			'currency'         => flxcrm_currency(),
			'can_write'        => flxcrm_can( true ),
		);
	}

	/**
	 * Recalculates the RFM scores (one slice; call again until `done`).
	 *
	 * @return array{scored: int, changed: int, done: bool, total: int}
	 */
	public static function score(): array {
		$r = FLXCRM_Scoring::run();
		FLXCRM_Contact_Service::changed();
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( FLXCRM_Segment_Service::TAG, FLXCRM_Contact_Service::TAG_META ) );
		}
		return array(
			'scored'  => FLXCRM_Val::int( $r['scored'] ),
			'changed' => FLXCRM_Val::int( $r['changed'] ),
			'done'    => FLXCRM_Val::bool( $r['done'] ),
			'total'   => FLXCRM_Val::int( $r['total'] ),
		);
	}

	/**
	 * One step of the past-data sync. Start with an empty step; follow `next` and `page` until `finished`.
	 *
	 * @param string $step Source key, recalc or score ('' = the first).
	 * @param int    $page Page of the step (1 = first).
	 * @return array{step: string, label: string, scanned: int, total: int, next: string|null, page: int, finished: bool}
	 */
	public static function sync_step( string $step, int $page ): array {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$r = FLXCRM_Sync::step( sanitize_key( $step ), max( 1, $page ) );
		FLXCRM_Contact_Service::changed();
		return array(
			'step'     => FLXCRM_Val::str( $r['step'] ?? '' ),
			'label'    => FLXCRM_Val::str( $r['label'] ?? '' ),
			'scanned'  => FLXCRM_Val::int( $r['scanned'] ?? 0 ),
			'total'    => FLXCRM_Val::int( $r['total'] ),
			'next'     => isset( $r['next'] ) && is_string( $r['next'] ) ? $r['next'] : null,
			'page'     => FLXCRM_Val::int( $r['page'] ?? 1, 1 ),
			'finished' => FLXCRM_Val::bool( $r['finished'] ?? false ),
		);
	}
}
