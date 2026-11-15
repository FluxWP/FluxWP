<?php
/**
 * Product telemetry hub, read side: what the "Product telemetry" screen (GraphQL and the classic page)
 * shows. The collection endpoint (POST /flxan/v1/telemetry, sent by FluxWP Panel on other sites) stays in
 * FLXAN_Telemetry_Hub: it is a public ingest endpoint called by other installs, not a screen.
 *
 * Permission checks stay with the caller (manage_options). Needs the hub to be on (settings › Telemetry hub).
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type Count array{key: string, label: string, sites: int}
 * @phpstan-type TmOverview array{installs: int, reports: int, errorsOpen: int, fatalOpen: int, events: int, appUsage: list<array{day: string, platform: string, accounts: int}>, usage: list<array{slug: string, name: string, sites: int, topVersion: string}>, pairs: list<array{aName: string, bName: string, sites: int}>, combos: list<array{names: list<string>, sites: int}>, php: list<Count>, wp: list<Count>, locales: list<Count>, countries: list<Count>}
 * @phpstan-type TmOnboarding array{runs: int, done: int, skipped: int, left: int, medianSeconds: int, reach: list<array{step: string, label: string, count: int}>, stops: list<array{step: string, label: string, outcome: string, count: int}>, cards: list<array{slug: string, name: string, picked: int, dropped: int, recOff: int}>, presets: list<array{key: string, count: int}>, styles: list<array{key: string, count: int}>}
 * @phpstan-type TmCountry array{code: string, name: string, sites: int}
 * @phpstan-type Telemetry array{enabled: bool, endpoint: string, days: int, country: string, countries: list<TmCountry>, overview: TmOverview, onboarding: TmOnboarding}
 * @phpstan-type TmError array{hash: string, type: string, fatal: bool, message: string, more: string, file: string, line: int, plugin: string, pluginName: string, version: string, sites: int, count: int, lastTs: int}
 * @phpstan-type TmErrors array{items: list<TmError>, plugins: list<array{value: string, label: string}>}
 */
final class FLXAN_Telemetry_Service {

	/** Ranges the screen offers. */
	public const DAYS = array( 7, 30, 90 );

	/** The hub is switched on (settings › Telemetry hub). */
	public static function on(): bool {
		return class_exists( 'FLXAN_Telemetry_Hub' ) && FLXAN_Telemetry_Hub::on();
	}

	/** Valid range in days. */
	public static function days( int $days ): int {
		return in_array( $days, self::DAYS, true ) ? $days : 30;
	}

	/**
	 * @param mixed $m Legacy count map: key => count.
	 * @return list<Count>
	 */
	private static function counts( $m, bool $country = false ): array {
		$out = array();
		foreach ( FLXAN_Service::arr( $m ) as $k => $n ) {
			$k     = (string) $k;
			$out[] = array(
				'key'   => $k,
				'label' => $country ? self::cc_label( $k ) : $k,
				'sites' => FLXAN_Service::int( $n ),
			);
		}
		return $out;
	}

	/** Country name in the site language ("?" and empty are unknown). */
	private static function cc_label( string $cc ): string {
		return FLXAN_Service::str( FLXAN_Telemetry_Hub::cc_name( $cc ) );
	}

	/**
	 * Overview and wizard numbers for the range.
	 *
	 * @return Telemetry
	 */
	public static function summary( int $days, string $country ): array {
		$days = self::days( $days );
		$cc   = FLXAN_Service::str( FLXAN_Telemetry_Hub::cc( $country ) );
		$S    = FLXAN_Service::arr( FLXAN_Telemetry_Hub::summary( $days, $cc ) );
		$ob   = FLXAN_Service::arr( $S['ob'] ?? array() );
		$out  = FLXAN_Service::arr( $ob['out'] ?? array() );

		$usage = array();
		$vers  = FLXAN_Service::arr( $S['versions'] ?? array() );
		foreach ( FLXAN_Service::arr( $S['usage'] ?? array() ) as $slug => $n ) {
			$slug    = (string) $slug;
			$top     = array_key_first( FLXAN_Service::arr( $vers[ $slug ] ?? array() ) );
			$usage[] = array(
				'slug'       => $slug,
				'name'       => FLXAN_Service::str( FLXAN_Telemetry_Hub::name( $slug ) ),
				'sites'      => FLXAN_Service::int( $n ),
				'topVersion' => null === $top ? '' : (string) $top,
			);
		}
		$usage = array_slice( $usage, 0, 25 );

		$pairs = array();
		foreach ( FLXAN_Service::arr( $S['pairs'] ?? array() ) as $k => $n ) {
			$parts   = explode( '|', (string) $k, 2 );
			$pairs[] = array(
				'aName' => FLXAN_Service::str( FLXAN_Telemetry_Hub::name( $parts[0] ) ),
				'bName' => FLXAN_Service::str( FLXAN_Telemetry_Hub::name( $parts[1] ?? '' ) ),
				'sites' => FLXAN_Service::int( $n ),
			);
		}

		$combos = array();
		foreach ( FLXAN_Service::arr( $S['combos'] ?? array() ) as $k => $n ) {
			$slugs    = '' === (string) $k ? array() : explode( '|', (string) $k );
			$combos[] = array(
				'names' => array_values( array_map( static fn( string $s ): string => FLXAN_Service::str( FLXAN_Telemetry_Hub::name( $s ) ), $slugs ) ),
				'sites' => FLXAN_Service::int( $n ),
			);
		}

		$app = array();
		$au  = FLXAN_Service::arr( $S['app_usage'] ?? array() );
		krsort( $au );
		foreach ( $au as $day => $platforms ) {
			foreach ( FLXAN_Service::arr( $platforms ) as $platform => $count ) {
				$app[] = array( 'day' => (string) $day, 'platform' => (string) $platform, 'accounts' => FLXAN_Service::int( $count ) );
			}
		}

		$reach = array();
		foreach ( FLXAN_Service::arr( $ob['reach'] ?? array() ) as $step => $n ) {
			$reach[] = array( 'step' => (string) $step, 'label' => FLXAN_Service::str( FLXAN_Telemetry_Hub::step_name( (string) $step ) ), 'count' => FLXAN_Service::int( $n ) );
		}
		$stops = array();
		foreach ( FLXAN_Service::arr( $ob['stop'] ?? array() ) as $k => $n ) {
			$parts   = explode( '|', (string) $k, 2 );
			$stops[] = array(
				'step'    => $parts[0],
				'label'   => FLXAN_Service::str( FLXAN_Telemetry_Hub::step_name( $parts[0] ) ),
				'outcome' => 'skip' === ( $parts[1] ?? '' ) ? 'skip' : 'left',
				'count'   => FLXAN_Service::int( $n ),
			);
		}
		$picked  = FLXAN_Service::arr( $ob['picked'] ?? array() );
		$dropped = FLXAN_Service::arr( $ob['dropped'] ?? array() );
		$rec_off = FLXAN_Service::arr( $ob['rec_off'] ?? array() );
		$cards   = array();
		foreach ( array_unique( array_merge( array_keys( $picked ), array_keys( $dropped ), array_keys( $rec_off ) ) ) as $slug ) {
			$slug    = (string) $slug;
			$cards[] = array(
				'slug'    => $slug,
				'name'    => FLXAN_Service::str( FLXAN_Telemetry_Hub::name( $slug ) ),
				'picked'  => FLXAN_Service::int( $picked[ $slug ] ?? 0 ),
				'dropped' => FLXAN_Service::int( $dropped[ $slug ] ?? 0 ),
				'recOff'  => FLXAN_Service::int( $rec_off[ $slug ] ?? 0 ),
			);
		}
		usort( $cards, static fn( array $a, array $b ): int => $b['picked'] <=> $a['picked'] );
		$presets = array();
		foreach ( FLXAN_Service::arr( $ob['preset'] ?? array() ) as $k => $n ) {
			$presets[] = array( 'key' => (string) $k, 'count' => FLXAN_Service::int( $n ) );
		}
		$styles = array();
		foreach ( FLXAN_Service::arr( $ob['style'] ?? array() ) as $k => $n ) {
			$styles[] = array( 'key' => (string) $k, 'count' => FLXAN_Service::int( $n ) );
		}

		return array(
			'enabled'    => true,
			'endpoint'   => FLXAN_Service::str( FLXAN_Telemetry_Hub::endpoint() ),
			'days'       => $days,
			'country'    => strtolower( $cc ),
			'countries'  => self::countries( $days ),
			'overview'   => array(
				'installs'   => FLXAN_Service::int( $S['installs'] ?? 0 ),
				'reports'    => FLXAN_Service::int( $S['reports'] ?? 0 ),
				'errorsOpen' => FLXAN_Service::int( $S['errors_open'] ?? 0 ),
				'fatalOpen'  => FLXAN_Service::int( $S['fatal_open'] ?? 0 ),
				'events'     => FLXAN_Service::int( $S['events'] ?? 0 ),
				'appUsage'   => $app,
				'usage'      => $usage,
				'pairs'      => $pairs,
				'combos'     => $combos,
				'php'        => self::counts( $S['php'] ?? array() ),
				'wp'         => self::counts( $S['wp'] ?? array() ),
				'locales'    => self::counts( $S['locale'] ?? array() ),
				'countries'  => self::counts( array_slice( FLXAN_Service::arr( $S['country'] ?? array() ), 0, 15, true ), true ),
			),
			'onboarding' => array(
				'runs'          => FLXAN_Service::int( $ob['runs'] ?? 0 ),
				'done'          => FLXAN_Service::int( $out['done'] ?? 0 ),
				'skipped'       => FLXAN_Service::int( $out['skip'] ?? 0 ),
				'left'          => FLXAN_Service::int( $out['left'] ?? 0 ),
				'medianSeconds' => FLXAN_Service::int( $ob['median'] ?? 0 ),
				'reach'         => $reach,
				'stops'         => $stops,
				'cards'         => $cards,
				'presets'       => $presets,
				'styles'        => $styles,
			),
		);
	}

	/**
	 * Countries that reported in the range (the filter list).
	 *
	 * @return list<TmCountry>
	 */
	public static function countries( int $days ): array {
		$out = array();
		foreach ( FLXAN_Service::rows( FLXAN_Telemetry_Hub::countries( self::days( $days ) ) ) as $r ) {
			$cc    = FLXAN_Service::str( $r['cc'] ?? '' );
			$out[] = array( 'code' => strtolower( $cc ), 'name' => self::cc_label( $cc ), 'sites' => FLXAN_Service::int( $r['n'] ?? 0 ) );
		}
		return $out;
	}

	/**
	 * Error records, fatal first.
	 *
	 * @return TmErrors
	 */
	public static function errors( int $days, string $country, string $plugin, string $type ): array {
		$cc    = FLXAN_Service::str( FLXAN_Telemetry_Hub::cc( $country ) );
		$type  = 'fatal' === $type || in_array( $type, FLXAN_Telemetry_Hub::TYPES, true ) ? $type : '';
		$items = array();
		foreach ( FLXAN_Service::rows( FLXAN_Telemetry_Hub::errors( self::days( $days ), sanitize_key( $plugin ), $type, $cc ) ) as $e ) {
			$msg     = FLXAN_Service::str( $e['m'] ?? '' );
			$first   = strtok( $msg, "\n" );
			$first   = false === $first ? '' : $first;
			$t       = FLXAN_Service::str( $e['t'] ?? '' );
			$slug    = FLXAN_Service::str( $e['p'] ?? '' );
			$items[] = array(
				'hash'       => FLXAN_Service::str( $e['h'] ?? '' ),
				'type'       => $t,
				'fatal'      => in_array( $t, FLXAN_Telemetry_Hub::FATAL, true ),
				'message'    => $first,
				'more'       => trim( substr( $msg, strlen( $first ) ) ),
				'file'       => FLXAN_Service::str( $e['f'] ?? '' ),
				'line'       => FLXAN_Service::int( $e['l'] ?? 0 ),
				'plugin'     => $slug,
				'pluginName' => FLXAN_Service::str( FLXAN_Telemetry_Hub::name( $slug ) ),
				'version'    => FLXAN_Service::str( $e['v'] ?? '' ),
				'sites'      => FLXAN_Service::int( $e['sites'] ?? 0 ),
				'count'      => FLXAN_Service::int( $e['n'] ?? 0 ),
				'lastTs'     => FLXAN_Service::int( $e['last_ts'] ?? 0 ),
			);
		}
		$plugins = array();
		foreach ( FLXAN_Service::arr( FLXAN_Telemetry_Hub::error_plugins() ) as $p ) {
			$p         = FLXAN_Service::str( $p );
			$plugins[] = array( 'value' => $p, 'label' => FLXAN_Service::str( FLXAN_Telemetry_Hub::name( $p ) ) );
		}
		return array( 'items' => $items, 'plugins' => $plugins );
	}

	/**
	 * Removes one error record (or all of them with "all"). It reappears when it happens again.
	 *
	 * @return bool False when the key is not valid.
	 */
	public static function forget( string $h ): bool {
		global $wpdb;
		$h = sanitize_key( $h );
		// phpcs:disable WordPress.DB
		if ( 'all' === $h ) {
			$wpdb->query( 'TRUNCATE TABLE ' . FLXAN_Telemetry_Hub::t( 'errors' ) );
			$wpdb->query( 'TRUNCATE TABLE ' . FLXAN_Telemetry_Hub::t( 'esites' ) );
		} elseif ( preg_match( '/^[a-f0-9]{16}$/', $h ) ) {
			$wpdb->delete( FLXAN_Telemetry_Hub::t( 'errors' ), array( 'h' => $h ) );
			$wpdb->delete( FLXAN_Telemetry_Hub::t( 'esites' ), array( 'h' => $h ) );
		} else {
			return false;
		}
		// phpcs:enable
		delete_transient( 'flxan_tm_sum' );
		return true;
	}
}
