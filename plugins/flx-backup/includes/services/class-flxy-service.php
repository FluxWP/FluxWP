<?php
/**
 * Backup service: the one place the classic admin page, GraphQL and the REST list use for reading
 * backups, the dashboard, settings and the step-by-step jobs. The long work itself stays in
 * FLXY_Backup / FLXY_Restore / FLXY_Runner (resumable steps, PHP ~60 s limit): a start call only
 * creates the job, a step call advances it by one budget, and the status is read separately.
 *
 * Shapes use the GraphQL field names (camelCase) so a resolver can return them as they are.
 *
 * @package flx-backup
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type Package array{slug: string, name: string, fromVersion: string, toVersion: string, label: string}
 * @phpstan-type RestoreItem array{path: string, label: string}
 * @phpstan-type RestoreGroup array{key: string, label: string, items: list<RestoreItem>}
 * @phpstan-type RestoreOptions array{id: string, title: string, database: bool, groups: list<RestoreGroup>, foreignSite: string|null, prefixInBackup: string|null, prefixOnSite: string|null}
 * @phpstan-type Backup array{id: string, type: string, typeLabel: string, typeColor: string, status: string, statusLabel: string, title: string, label: string, created: int, finished: int, size: float, sizeLabel: string, files: int, includesDatabase: bool, contents: list<string>, source: string, sourceLabel: string, originalSite: string|null, originalCreated: int, wpVersion: string, locked: bool, keepKind: string, keepUntil: int, keepLabel: string, error: string, packages: list<Package>, downloadUrl: string, percent: int|null, progress: string, running: bool}
 * @phpstan-type Job array{status: string, kind: string|null, id: string, phase: string, percent: int, text: string, error: string, token: string, cancelled: bool, elapsed: int, elapsedLabel: string, eta: int|null, etaLabel: string, title: string, sizeLabel: string, result: string}
 * @phpstan-type Settings array{quotaGb: float, preScope: string, preKeepDays: int, schedule: string, scheduleHour: int, scheduleDay: int, scheduleKeep: int, includeCore: bool, exclude: string, safetyRestore: bool, deleteOnUninstall: bool}
 */
final class FLXY_Service {

	const CACHE_TAG = 'flx-backup:backups';

	/** Backup types a person can start. */
	const START_TYPES = array( 'full', 'db', 'files' );

	/** Typed access to the untyped legacy records: a scalar as string. */
	private static function s( $v ): string {
		return is_scalar( $v ) ? (string) $v : '';
	}

	/** A number as int. */
	private static function i( $v ): int {
		return is_numeric( $v ) || is_bool( $v ) ? (int) $v : 0;
	}

	/** A number as float. */
	private static function f( $v ): float {
		return is_numeric( $v ) ? (float) $v : 0.0;
	}

	/** Drop cached GraphQL reads after a change. */
	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Labels                                                              */
	/* ------------------------------------------------------------------ */

	public static function status_label( string $status ): string {
		switch ( $status ) {
			case 'running':
				return __( 'Running', 'flx-backup' );
			case 'ok':
			case 'done':
				return __( 'Done', 'flx-backup' );
			case 'failed':
				return __( 'Error', 'flx-backup' );
		}
		return $status;
	}

	/** "https://ornek.org/" → "ornek.org" */
	public static function short_url( string $url ): string {
		return untrailingslashit( (string) preg_replace( '#^https?://(www\.)?#i', '', $url ) );
	}

	/** size_format that stays whole under 1 KB ("393 B"). */
	public static function bytes( int $n ): string {
		return (string) ( $n < KB_IN_BYTES ? size_format( $n, 0 ) : size_format( $n, 1 ) );
	}

	/* ------------------------------------------------------------------ */
	/* Backups                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Retention text and kind of a record.
	 *
	 * @param array<string,mixed> $r Record.
	 * @return array{0: string, 1: string} kind (locked|until|schedule|quota), label
	 */
	public static function keep( array $r ): array {
		$ctx    = (array) $r['context'];
		$source = self::s( $ctx['source'] ?? '' );
		if ( ! empty( $r['locked'] ) ) {
			return array( 'locked', __( 'Locked', 'flx-backup' ) );
		}
		if ( ! empty( $r['keep_until'] ) ) {
			/* translators: %s: date */
			return array( 'until', sprintf( __( 'Until %s', 'flx-backup' ), flxy_date( self::i( $r['keep_until'] ), 'date' ) ) );
		}
		if ( 'schedule' === $source ) {
			/* translators: %s: number of backups kept */
			return array( 'schedule', sprintf( __( 'Last %s scheduled', 'flx-backup' ), number_format_i18n( self::i( FLXY_Settings::get( 'schedule_keep' ) ) ) ) );
		}
		return array( 'quota', __( 'Until the quota is full', 'flx-backup' ) );
	}

	/**
	 * What a backup holds, as short names.
	 *
	 * @param array<string,mixed> $r Record.
	 * @return list<string>
	 */
	public static function contents( array $r ): array {
		$contents = array();
		if ( $r['db'] ) {
			$contents[] = __( 'Database', 'flx-backup' );
		}
		$content_rel = FLXY_Backup::content_rel();
		$paths       = (array) $r['paths'];
		foreach ( $paths as $p ) {
			$p = self::s( $p );
			if ( $p === $content_rel ) {
				$contents[] = 'wp-content';
			} elseif ( preg_match( '#/(plugins|themes)/([^/]+)$#', $p, $m ) ) {
				$contents[] = ( 'plugins' === $m[1] ? __( 'Plugin', 'flx-backup' ) : __( 'Theme', 'flx-backup' ) ) . ': ' . preg_replace( '/\.php$/', '', $m[2] );
			} elseif ( ! in_array( $p, array( 'wp-admin', 'wp-includes' ), true ) && false === strpos( $p, '/' ) && is_file( FLXY_Backup::abs( $p ) ) ) {
				continue; // Root files count under "core".
			} else {
				$contents[] = $p;
			}
		}
		if ( in_array( 'wp-includes', $paths, true ) ) {
			$contents   = array_values( array_diff( $contents, array( 'wp-admin', 'wp-includes' ) ) );
			$contents[] = __( 'WordPress core', 'flx-backup' );
		}
		return $contents;
	}

	/** Signed download link (the file itself is streamed by admin-post, not GraphQL). */
	public static function download_url( string $id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'flxy_download',
					'id'     => $id,
				),
				admin_url( 'admin-post.php' )
			),
			'flxy_download_' . $id
		);
	}

	/**
	 * A stored record in the GraphQL shape.
	 *
	 * @param array<string,mixed> $r Record (FLXY_Storage::normalize).
	 * @return Backup
	 */
	public static function present( array $r ): array {
		$ctx      = (array) $r['context'];
		$source   = self::s( $ctx['source'] ?? '' );
		$sources  = FLXY_Storage::source_labels();
		$types    = FLXY_Storage::type_labels();
		$colors   = FLXY_Storage::type_colors();
		$type     = self::s( $r['type'] );
		$status   = self::s( $r['status'] );
		$packages = array();
		foreach ( (array) ( $ctx['packages'] ?? array() ) as $p ) {
			$p          = (array) $p;
			$slug       = self::s( $p['slug'] ?? '' );
			$name       = self::s( $p['name'] ?? '' );
			$from       = self::s( $p['from_version'] ?? '' );
			$to         = self::s( $p['to_version'] ?? '' );
			$packages[] = array(
				'slug'        => $slug,
				'name'        => '' !== $name ? $name : $slug,
				'fromVersion' => $from,
				'toVersion'   => $to,
				'label'       => trim( ( '' !== $name ? $name : $slug ) . ' ' . $from . ( '' !== $to ? ' → ' . $to : '' ) ),
			);
		}
		$running = 'running' === $status;
		$percent = null;
		$text    = '';
		if ( $running ) {
			$state = FLXY_Runner::load_backup();
			if ( is_array( $state ) && self::s( $state['id'] ?? '' ) === self::s( $r['id'] ) ) {
				$p       = FLXY_Backup::progress( $state );
				$percent = self::i( $p['percent'] );
				$text    = self::s( $p['text'] );
			}
		}
		list( $keep_kind, $keep_label ) = self::keep( $r );
		$orig = untrailingslashit( self::s( $ctx['original_site_url'] ?? '' ) );
		return array(
			'id'               => self::s( $r['id'] ),
			'type'             => $type,
			'typeLabel'        => $types[ $type ] ?? $type,
			'typeColor'        => $colors[ $type ] ?? '#646970',
			'status'           => $status,
			'statusLabel'      => self::status_label( $status ),
			'title'            => FLXY_Storage::title( $r ),
			'label'            => self::s( $r['label'] ),
			'created'          => self::i( $r['created'] ),
			'finished'         => self::i( $r['finished'] ),
			'size'             => self::f( $r['size'] ),
			'sizeLabel'        => 'ok' === $status ? (string) size_format( self::i( $r['size'] ), 1 ) : '',
			'files'            => self::i( $r['files'] ),
			'includesDatabase' => (bool) $r['db'],
			'contents'         => self::contents( $r ),
			'source'           => $source,
			'sourceLabel'      => '' !== $source ? ( $sources[ $source ] ?? $source ) : '',
			'originalSite'     => 'upload' === $source && '' !== $orig ? self::short_url( $orig ) : null,
			'originalCreated'  => self::i( $ctx['original_created'] ?? 0 ),
			'wpVersion'        => self::s( $r['wp_version'] ),
			'locked'           => (bool) $r['locked'],
			'keepKind'         => $keep_kind,
			'keepUntil'        => self::i( $r['keep_until'] ?? 0 ),
			'keepLabel'        => $keep_label,
			'error'            => self::s( $r['error'] ),
			'packages'         => $packages,
			'downloadUrl'      => 'ok' === $status ? self::download_url( self::s( $r['id'] ) ) : '',
			'percent'          => $percent,
			'progress'         => $text,
			'running'          => $running,
		);
	}

	/**
	 * One backup, or null.
	 *
	 * @return Backup|null
	 */
	public static function backup( string $id ): ?array {
		FLXY_Storage::sync();
		$r = FLXY_Storage::get( $id );
		return $r ? self::present( $r ) : null;
	}

	/**
	 * A page of backups, newest first, with per type counts for the filter links.
	 *
	 * @return array{items: list<Backup>, total: int, counts: list<array{type: string, label: string, count: int}>, all: int, usedBytes: float, usedLabel: string}
	 */
	public static function backups( string $type, string $status, int $offset, int $limit ): array {
		FLXY_Storage::sync();
		$all    = FLXY_Storage::records();
		$counts = array();
		foreach ( $all as $r ) {
			$counts[ self::s( $r['type'] ) ] = ( $counts[ self::s( $r['type'] ) ] ?? 0 ) + 1;
		}
		$rows = array_values(
			array_filter(
				$all,
				static function ( $r ) use ( $type, $status ) {
					return ( '' === $type || $r['type'] === $type ) && ( '' === $status || $r['status'] === $status );
				}
			)
		);
		$items = array();
		foreach ( array_slice( $rows, $offset, $limit ) as $r ) {
			$items[] = self::present( $r );
		}
		$by = array();
		foreach ( FLXY_Storage::type_labels() as $t => $label ) {
			if ( ! empty( $counts[ $t ] ) ) {
				$by[] = array(
					'type'  => self::s( $t ),
					'label' => self::s( $label ),
					'count' => self::i( $counts[ $t ] ),
				);
			}
		}
		$used = FLXY_Storage::used_bytes();
		return array(
			'items'     => $items,
			'total'     => count( $rows ),
			'counts'    => $by,
			'all'       => count( $all ),
			'usedBytes' => self::f( $used ),
			'usedLabel' => (string) size_format( $used, 1 ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Restore options                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * What the restore dialog offers for a backup.
	 *
	 * @param array<string,mixed> $r Record.
	 * @return RestoreOptions
	 */
	public static function restore_options( array $r ): array {
		global $wpdb;
		$ctx     = (array) $r['context'];
		$foreign = null;
		$orig    = untrailingslashit( self::s( $ctx['original_site_url'] ?? '' ) );
		if ( '' !== $orig && self::short_url( $orig ) !== self::short_url( home_url() ) ) {
			$foreign = $orig;
		}
		$prefix   = self::s( $ctx['original_prefix'] ?? '' );
		$mismatch = '' !== $prefix && $prefix !== self::s( $wpdb->base_prefix );
		return array(
			'id'             => self::s( $r['id'] ),
			'title'          => FLXY_Storage::title( $r ) . ' · ' . flxy_date( self::i( $r['created'] ) ),
			'database'       => $r['db'] && ! $mismatch,
			'groups'         => self::restore_groups( $r ),
			'foreignSite'    => $foreign,
			'prefixInBackup' => $mismatch ? $prefix : null,
			'prefixOnSite'   => $mismatch ? self::s( $wpdb->base_prefix ) : null,
		);
	}

	/**
	 * Restore dialog choices, grouped.
	 *
	 * @param array<string,mixed> $r Record.
	 * @return list<RestoreGroup>
	 */
	private static function restore_groups( array $r ): array {
		$c      = FLXY_Backup::content_rel();
		$groups = array(
			'plugins' => array(
				'key'   => 'plugins',
				'label' => __( 'Plugins', 'flx-backup' ),
				'items' => array(),
			),
			'themes'  => array(
				'key'   => 'themes',
				'label' => __( 'Themes', 'flx-backup' ),
				'items' => array(),
			),
			'other'   => array(
				'key'   => 'other',
				'label' => __( 'Other folders', 'flx-backup' ),
				'items' => array(),
			),
		);
		$items = $r['items'] ? (array) $r['items'] : (array) $r['paths'];
		$names = array();
		foreach ( (array) ( ( (array) $r['context'] )['packages'] ?? array() ) as $p ) {
			$p                                   = (array) $p;
			$names[ self::s( $p['slug'] ?? '' ) ] = self::s( $p['name'] ?? '' );
		}
		foreach ( $items as $p ) {
			$p = self::s( $p );
			if ( preg_match( '#^' . preg_quote( $c, '#' ) . '/(plugins|themes)/([^/]+)$#', $p, $m ) ) {
				$slug                       = (string) preg_replace( '/\.php$/', '', $m[2] );
				$groups[ $m[1] ]['items'][] = array(
					'path'  => $p,
					'label' => ! empty( $names[ $slug ] ) ? $names[ $slug ] . ' (' . $slug . ')' : $slug,
				);
			} elseif ( in_array( $p, array( $c . '/plugins', $c . '/themes' ), true ) ) {
				continue; // Single packages are listed; "all" is wp-content.
			} elseif ( $p === $c ) {
				array_unshift(
					$groups['other']['items'],
					array(
						'path'  => $p,
						'label' => __( 'wp-content (all)', 'flx-backup' ),
					)
				);
			} elseif ( false === strpos( $p, '/' ) && ! in_array( $p, array( 'wp-admin', 'wp-includes' ), true ) ) {
				continue;
			} else {
				$groups['other']['items'][] = array(
					'path'  => $p,
					'label' => $p,
				);
			}
		}
		return array_values(
			array_filter(
				$groups,
				static function ( $g ) {
					return (bool) $g['items'];
				}
			)
		);
	}

	/**
	 * Restore choices of a backup, or null when it is missing or not finished.
	 *
	 * @return RestoreOptions|null
	 */
	public static function restore_for( string $id ): ?array {
		$r = FLXY_Storage::get( $id );
		return $r && 'ok' === $r['status'] ? self::restore_options( $r ) : null;
	}

	/* ------------------------------------------------------------------ */
	/* Size breakdown                                                      */
	/* ------------------------------------------------------------------ */

	/** Item name: plugin/theme name, uploads year, root files … */
	public static function item_label( string $cat, string $n ): string {
		if ( '/' === $n ) {
			return 'core' === $cat ? __( 'Root files (wp-config.php, index.php …)', 'flx-backup' ) : __( 'Files directly in this folder', 'flx-backup' );
		}
		if ( 'plugins' === $cat || 'themes' === $cat ) {
			static $names = null;
			if ( null === $names ) {
				$names = array();
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				foreach ( get_plugins() as $file => $data ) {
					$slug                        = false === strpos( $file, '/' ) ? (string) preg_replace( '/\.php$/', '', $file ) : dirname( $file );
					$names[ 'plugins/' . $slug ] = self::s( $data['Name'] );
				}
				foreach ( wp_get_themes() as $slug => $theme ) {
					$names[ 'themes/' . $slug ] = self::s( $theme->get( 'Name' ) );
				}
			}
			$slug = (string) preg_replace( '/\.php$/', '', $n );
			$key  = $cat . '/' . $slug;
			return isset( $names[ $key ] ) && $names[ $key ] !== $slug ? $names[ $key ] . ' (' . $slug . ')' : $n;
		}
		return $n;
	}

	/**
	 * Size breakdown of a finished backup (computed once, kept on the record).
	 *
	 * @return array{raw: float, zip: float, categories: list<array{key: string, label: string, color: string, isDatabase: bool, raw: float, zip: float, count: int, items: list<array{name: string, label: string, raw: float, zip: float, count: int}>, moreCount: int, moreRaw: float, moreZip: float, moreItems: int}>}|null
	 */
	public static function breakdown( string $id ): ?array {
		$r = FLXY_Storage::get( $id );
		if ( ! $r || 'ok' !== $r['status'] ) {
			return null;
		}
		$b = FLXY_Breakdown::for_record( $r );
		if ( ! $b ) {
			return null;
		}
		$labels = FLXY_Breakdown::labels();
		$colors = FLXY_Breakdown::colors();
		$cats   = array();
		foreach ( FLXY_Breakdown::CATS as $k ) {
			if ( empty( $b['cats'][ $k ] ) ) {
				continue;
			}
			$c     = $b['cats'][ $k ];
			$items = array();
			foreach ( (array) $c['items'] as $it ) {
				$items[] = array(
					'name'  => self::s( $it['n'] ),
					'label' => self::item_label( $k, self::s( $it['n'] ) ),
					'raw'   => self::f( $it['raw'] ),
					'zip'   => self::f( $it['zip'] ),
					'count' => self::i( $it['c'] ),
				);
			}
			$more   = (array) ( $c['more'] ?? array() );
			$cats[] = array(
				'key'        => $k,
				'label'      => $labels[ $k ],
				'color'      => $colors[ $k ],
				'isDatabase' => 'db' === $k,
				'raw'        => self::f( $c['raw'] ),
				'zip'        => self::f( $c['zip'] ),
				'count'      => self::i( $c['files'] ),
				'items'      => $items,
				'moreCount'  => self::i( $more['c'] ?? 0 ),
				'moreRaw'    => self::f( $more['raw'] ?? 0 ),
				'moreZip'    => self::f( $more['zip'] ?? 0 ),
				'moreItems'  => self::i( $more['k'] ?? 0 ),
			);
		}
		return array(
			'raw'        => self::f( $b['raw'] ),
			'zip'        => self::f( $b['zip'] ),
			'categories' => $cats,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Dashboard                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Storage history for the chart: one point a day, 180 days, gaps filled with the previous value.
	 *
	 * @return array{days: list<array{key: string, label: string, fullLabel: string, values: list<float>, valueLabels: list<string>, totalLabel: string, measured: bool}>, series: list<array{key: string, name: string, color: string}>, ranges: list<array{days: int, max: float, showQuota: bool, ticks: list<array{value: float, label: string}>}>, quota: float, quotaLabel: string}
	 */
	public static function history(): array {
		$hist   = FLXY_Storage::history();
		$types  = array_keys( FLXY_Storage::type_labels() );
		$labels = FLXY_Storage::type_labels();
		$colors = FLXY_Storage::type_colors();
		$tz     = wp_timezone();
		$today  = new DateTimeImmutable( 'today', $tz );
		$first  = $hist ? new DateTimeImmutable( (string) array_key_first( $hist ), $tz ) : $today;
		$start  = $today->modify( '-179 days' );
		$days   = array();
		$prev   = array_fill_keys( $types, 0 );
		$have   = false;
		for ( $d = $start; $d <= $today; $d = $d->modify( '+1 day' ) ) {
			$key = $d->format( 'Y-m-d' );
			if ( isset( $hist[ $key ] ) ) {
				$prev = array();
				foreach ( $types as $t ) {
					$prev[ $t ] = self::i( $hist[ $key ]['by'][ $t ] ?? 0 );
				}
				$have = true;
			}
			$ts     = $d->getTimestamp();
			$vals   = array();
			$vlabel = array();
			foreach ( $types as $t ) {
				$v        = $have ? $prev[ $t ] : 0;
				$vals[]   = self::f( $v );
				$vlabel[] = (string) size_format( $v, 1 );
			}
			$days[] = array(
				'key'         => $key,
				'label'       => (string) flx_date( $ts, 'short' ),
				'fullLabel'   => (string) flx_date( $ts ),
				'values'      => $vals,
				'valueLabels' => $vlabel,
				'totalLabel'  => (string) size_format( (int) array_sum( $vals ), 1 ),
				'measured'    => $have && $d >= $first,
			);
		}
		$quota  = FLXY_Settings::quota_bytes();
		$ranges = array();
		foreach ( array( 30, 90, 180 ) as $n ) {
			$max = 0;
			foreach ( array_slice( $days, -$n ) as $day ) {
				$max = max( $max, (int) array_sum( $day['values'] ) );
			}
			$ranges[] = array( 'days' => $n ) + self::ticks( $max, $quota );
		}
		$series = array();
		foreach ( $types as $t ) {
			$series[] = array(
				'key'   => self::s( $t ),
				'name'  => self::s( $labels[ $t ] ),
				'color' => self::s( $colors[ $t ] ),
			);
		}
		return array(
			'days'       => $days,
			'series'     => $series,
			'ranges'     => $ranges,
			'quota'      => self::f( $quota ),
			'quotaLabel' => __( 'Quota', 'flx-backup' ) . ' ' . size_format( $quota, 1 ),
		);
	}

	/**
	 * Y axis: round steps; the quota is part of the scale when usage is near it.
	 *
	 * @return array{max: float, showQuota: bool, ticks: list<array{value: float, label: string}>}
	 */
	public static function ticks( int $max, int $quota ): array {
		$show_quota = $max >= $quota * 0.5;
		$top        = max( $show_quota ? $quota : $max, MB_IN_BYTES );
		$raw        = $top / 4;
		$mag        = pow( 10, floor( log10( $raw ) ) );
		$step       = $mag;
		foreach ( array( 1, 2, 2.5, 5, 10 ) as $m ) {
			if ( $m * $mag >= $raw ) {
				$step = $m * $mag;
				break;
			}
		}
		$unit = $top >= GB_IN_BYTES ? GB_IN_BYTES : MB_IN_BYTES;
		$step = max( $unit / 4, round( $step / ( $unit / 4 ) ) * ( $unit / 4 ) );
		$ymax = $step * ceil( $top * 1.08 / $step );
		$out  = array();
		for ( $v = 0; $v <= $ymax + 1; $v += $step ) {
			$out[] = array(
				'value' => self::f( $v ),
				'label' => 0 === self::i( $v ) ? '0' : (string) size_format( self::i( $v ), $step < $unit ? 1 : 0 ),
			);
		}
		return array(
			'max'       => self::f( $ymax ),
			'showQuota' => $show_quota,
			'ticks'     => $out,
		);
	}

	/**
	 * Dashboard numbers: storage, counts, last and next backup, by type, recent backups and events.
	 *
	 * @return array{usedBytes: float, usedLabel: string, quotaBytes: float, quotaLabel: string, percent: float, level: string, freeLabel: string, backupCount: int, lockedCount: int, last: Backup|null, nextRun: int, scheduleKind: string, preScope: string, byType: list<array{type: string, label: string, color: string, bytes: float, bytesLabel: string, count: int, share: float}>, recent: list<Backup>, events: list<array{at: int, level: string, message: string}>}
	 */
	public static function overview(): array {
		FLXY_Storage::sync();
		FLXY_Storage::sample();
		$records = FLXY_Storage::records();
		$used    = FLXY_Storage::used_bytes();
		$quota   = max( 1, FLXY_Settings::quota_bytes() );
		$pct     = min( 100, 100 * $used / $quota );
		$ok      = array_values(
			array_filter(
				$records,
				static function ( $r ) {
					return 'ok' === $r['status'];
				}
			)
		);
		$locked  = count(
			array_filter(
				$ok,
				static function ( $r ) {
					return $r['locked'];
				}
			)
		);
		$by      = array_filter( FLXY_Storage::bytes_by_type() );
		$total   = array_sum( $by );
		$labels  = FLXY_Storage::type_labels();
		$colors  = FLXY_Storage::type_colors();
		$count   = array();
		foreach ( $ok as $r ) {
			$count[ $r['type'] ] = ( $count[ $r['type'] ] ?? 0 ) + 1;
		}
		$types = array();
		foreach ( $labels as $t => $label ) {
			$bytes = self::i( $by[ $t ] ?? 0 );
			if ( $bytes <= 0 ) {
				continue;
			}
			$types[] = array(
				'type'       => self::s( $t ),
				'label'      => self::s( $label ),
				'color'      => self::s( $colors[ $t ] ?? '#646970' ),
				'bytes'      => self::f( $bytes ),
				'bytesLabel' => (string) size_format( $bytes, 1 ),
				'count'      => self::i( $count[ $t ] ?? 0 ),
				'share'      => $total > 0 ? round( 100 * $bytes / $total, 1 ) : 0.0,
			);
		}
		$recent = array();
		foreach ( array_slice( $records, 0, 6 ) as $r ) {
			$recent[] = self::present( $r );
		}
		$events = array();
		foreach ( FLXY_Storage::events( 7 ) as $e ) {
			$events[] = array(
				'at'      => self::i( $e['at'] ),
				'level'   => self::s( $e['level'] ),
				'message' => self::s( $e['msg'] ),
			);
		}
		return array(
			'usedBytes'    => self::f( $used ),
			'usedLabel'    => (string) size_format( $used, 1 ),
			'quotaBytes'   => self::f( $quota ),
			'quotaLabel'   => (string) size_format( $quota, 1 ),
			'percent'      => round( $pct, 1 ),
			'level'        => $pct > 95 ? 'error' : ( $pct > 80 ? 'warning' : 'ok' ),
			'freeLabel'    => (string) size_format( max( 0, $quota - $used ), 1 ),
			'backupCount'  => count( $ok ),
			'lockedCount'  => $locked,
			'last'         => $ok ? self::present( $ok[0] ) : null,
			'nextRun'      => self::i( FLXY_Cron::next_scheduled() ),
			'scheduleKind' => self::s( FLXY_Settings::get( 'schedule' ) ),
			'preScope'     => self::s( FLXY_Settings::get( 'pre_scope' ) ),
			'byType'       => $types,
			'recent'       => $recent,
			'events'       => $events,
		);
	}

	/**
	 * Server state the screen warns about, plus values the upload and start dialogs need.
	 *
	 * @return array{zipAvailable: bool, writable: bool, folder: string, chunkSize: int, nonce: string, ajaxUrl: string, lastRestore: string|null, includeCoreDefault: bool, safetyDefault: bool}
	 */
	public static function health(): array {
		$last = get_option( 'flxy_last_restore' );
		return array(
			'zipAvailable'       => class_exists( 'ZipArchive' ),
			'writable'           => FLXY_Storage::is_writable(),
			'folder'             => FLXY_Backup::rel( FLXY_Storage::dir() ),
			'chunkSize'          => FLXY_Upload::chunk_size(),
			'nonce'              => wp_create_nonce( 'flxy' ),
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			'lastRestore'        => is_array( $last ) && self::i( $last['at'] ?? 0 ) > time() - 10 * MINUTE_IN_SECONDS ? self::s( $last['result'] ?? '' ) : null,
			'includeCoreDefault' => (bool) FLXY_Settings::get( 'include_core' ),
			'safetyDefault'      => (bool) FLXY_Settings::get( 'safety_restore' ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	/** @return Settings */
	public static function settings(): array {
		$s = FLXY_Settings::all();
		return array(
			'quotaGb'           => self::f( $s['quota_gb'] ),
			'preScope'          => self::s( $s['pre_scope'] ),
			'preKeepDays'       => self::i( $s['pre_keep_days'] ),
			'schedule'          => self::s( $s['schedule'] ),
			'scheduleHour'      => self::i( $s['schedule_hour'] ),
			'scheduleDay'       => self::i( $s['schedule_day'] ),
			'scheduleKeep'      => self::i( $s['schedule_keep'] ),
			'includeCore'       => (bool) $s['include_core'],
			'exclude'           => self::s( $s['exclude'] ),
			'safetyRestore'     => (bool) $s['safety_restore'],
			'deleteOnUninstall' => (bool) $s['delete_on_uninstall'],
		);
	}

	/**
	 * Saves settings from the form fields of the classic page (snake_case, as posted) and runs what
	 * a change needs: retention of pre-update backups, the cron schedule and a new storage sample.
	 *
	 * @param array<string,mixed> $in Fields; checkboxes are present when on.
	 * @return Settings
	 */
	public static function save_settings( array $in ): array {
		$old_keep = self::i( FLXY_Settings::get( 'pre_keep_days' ) );
		$saved    = FLXY_Settings::save( $in );
		if ( self::i( $saved['pre_keep_days'] ) !== $old_keep ) {
			FLXY_Settings::apply_pre_keep_days();
			FLXY_Cron::cleanup();
		}
		FLXY_Cron::reschedule();
		FLXY_Storage::sample();
		self::changed();
		return self::settings();
	}

	/* ------------------------------------------------------------------ */
	/* Jobs                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * The running (or just finished) job as the screen shows it.
	 *
	 * @param array<string,mixed>|null $state Job state; null = current.
	 * @return Job
	 */
	public static function job( ?array $state = null ): array {
		$state = $state ?? FLXY_Runner::current();
		$r     = FLXY_Runner::report( $state );
		if ( ! $state ) {
			return array(
				'status'       => 'idle',
				'kind'         => null,
				'id'           => '',
				'phase'        => '',
				'percent'      => 0,
				'text'         => '',
				'error'        => '',
				'token'        => '',
				'cancelled'    => false,
				'elapsed'      => 0,
				'elapsedLabel' => '',
				'eta'          => null,
				'etaLabel'     => '',
				'title'        => '',
				'sizeLabel'    => '',
				'result'       => '',
			);
		}
		return array(
			'status'       => self::s( $r['status'] ),
			'kind'         => self::s( $r['kind'] ),
			'id'           => self::s( $r['id'] ),
			'phase'        => self::s( $r['phase'] ),
			'percent'      => self::i( $r['percent'] ),
			'text'         => self::s( $r['text'] ),
			'error'        => self::s( $r['error'] ),
			'token'        => self::s( $r['token'] ),
			'cancelled'    => (bool) $r['cancelled'],
			'elapsed'      => self::i( $r['elapsed'] ),
			'elapsedLabel' => self::s( $r['elapsed_h'] ),
			'eta'          => null === $r['eta'] ? null : self::i( $r['eta'] ),
			'etaLabel'     => self::s( $r['eta_h'] ),
			'title'        => self::s( $r['title'] ),
			'sizeLabel'    => self::s( $r['size_h'] ),
			'result'       => 'done' === $state['phase'] && 'restore' === $state['kind'] ? self::s( $state['result'] ?? '' ) : '',
		);
	}

	/**
	 * Starts a backup in the background (the loopback, cron and the open screen advance it).
	 *
	 * @return Job|WP_Error
	 */
	public static function start_backup( string $type, string $label, ?bool $include_core, bool $lock ) {
		$type = in_array( $type, self::START_TYPES, true ) ? $type : 'full';
		$args = array(
			'type'        => $type,
			'label'       => sanitize_text_field( $label ),
			'context'     => array(
				'source' => 'manual',
				'user'   => get_current_user_id(),
			),
			'interactive' => false,
		);
		if ( 'full' === $type ) {
			$args['include_core'] = null === $include_core ? (bool) FLXY_Settings::get( 'include_core' ) : $include_core;
		}
		if ( $lock ) {
			$args['keep_days'] = null;
		}
		$rec = FLXY_Backup::create( $args );
		if ( is_wp_error( $rec ) ) {
			return self::busy_status( $rec );
		}
		self::changed();
		return self::job();
	}

	/**
	 * Starts a restore in the background.
	 *
	 * @param list<string> $paths Paths of the backup to restore (ABSPATH relative).
	 * @return Job|WP_Error
	 */
	public static function start_restore( string $id, bool $db, array $paths, ?bool $safety ) {
		if ( ! FLXY_Storage::get( $id ) ) {
			return new WP_Error( 'flxy_not_found', __( 'Backup not found.', 'flx-backup' ), array( 'status' => 404 ) );
		}
		$state = FLXY_Restore::start(
			$id,
			array(
				'db'     => $db,
				'paths'  => array_map( 'sanitize_text_field', $paths ),
				'safety' => null === $safety ? (bool) FLXY_Settings::get( 'safety_restore' ) : $safety,
			),
			false
		);
		if ( is_wp_error( $state ) ) {
			return self::busy_status( $state );
		}
		self::changed();
		return self::job( $state );
	}

	/**
	 * One step of the active job. Cheap when nothing runs.
	 *
	 * @return Job
	 */
	public static function step(): array {
		$state = FLXY_Runner::run_step( true );
		if ( $state && in_array( $state['phase'], array( 'done', 'failed' ), true ) ) {
			self::changed();
		}
		return self::job( $state );
	}

	/**
	 * Cancels the active job. A running step finishes first ('pending').
	 *
	 * @return array{pending: bool}|WP_Error
	 */
	public static function cancel() {
		$res = FLXY_Runner::cancel();
		if ( false === $res ) {
			return new WP_Error( 'flxy_no_job', __( 'No operation is running.', 'flx-backup' ), array( 'status' => 404 ) );
		}
		self::changed();
		return array( 'pending' => 'pending' === $res );
	}

	/** A busy runner is a conflict, other start errors are bad input. */
	private static function busy_status( WP_Error $e ): WP_Error {
		$status = 'flxy_busy' === $e->get_error_code() ? 409 : 400;
		return new WP_Error( $e->get_error_code(), $e->get_error_message(), array( 'status' => $status ) );
	}

	/* ------------------------------------------------------------------ */
	/* Backup changes                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Deletes backups; one that is still running is cancelled first.
	 *
	 * @param list<string> $ids Backup ids.
	 * @return list<string>|WP_Error Deleted ids.
	 */
	public static function delete( array $ids ) {
		$deleted = array();
		foreach ( $ids as $id ) {
			$id = sanitize_text_field( $id );
			if ( '' === $id || ! FLXY_Storage::get( $id ) ) {
				continue;
			}
			if ( FLXY_Runner::job_is_for( $id ) ) {
				FLXY_Runner::cancel();
			}
			if ( FLXY_Storage::delete( $id ) ) {
				$deleted[] = $id;
			}
		}
		if ( ! $deleted ) {
			return new WP_Error( 'flxy_not_found', __( 'Could not delete.', 'flx-backup' ), array( 'status' => 404 ) );
		}
		self::changed();
		return $deleted;
	}

	/**
	 * Locks or unlocks a finished backup.
	 *
	 * @return Backup|WP_Error
	 */
	public static function set_lock( string $id, bool $lock ) {
		if ( ! FLXY_Storage::get( $id ) ) {
			return new WP_Error( 'flxy_not_found', __( 'Backup not found.', 'flx-backup' ), array( 'status' => 404 ) );
		}
		FLXY_Storage::patch( $id, array( 'locked' => $lock ) );
		self::changed();
		$r = FLXY_Storage::get( $id );
		return self::present( (array) $r );
	}
}
