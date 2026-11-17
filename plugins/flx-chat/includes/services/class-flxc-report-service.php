<?php
/**
 * Scheduled WhatsApp report rules, previews and sending, shared between the classic Reports page
 * (admin-post) and GraphQL. Sending itself stays in reports.php (flxc_report_send, flxc_reports_run_due).
 *
 * @package FLX_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxcReportRule array<string,mixed>
 */
final class FLXC_Report_Service {

	public const CACHE = 'flx-chat:reports';

	/**
	 * Purges the cached report screen after a change.
	 */
	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE ) );
		}
	}

	/**
	 * Stored rules as a typed list.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function rules(): array {
		$out = array();
		foreach ( FLXC_Val::arr( flxc_reports() ) as $r ) {
			if ( is_array( $r ) ) {
				/** @var array<string,mixed> $r */
				$out[] = $r;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $v Value.
	 * @return list<int>
	 */
	private static function ints( $v ): array {
		return array_values( array_map( static fn( $x ): int => FLXC_Val::int( $x ), FLXC_Val::arr( $v ) ) );
	}

	/**
	 * Applies the posted rule fields to every rule present in `$forms` (keyed by rule id). Same
	 * sanitizing the classic form always had; unknown ids and non-array rows are skipped.
	 *
	 * @param list<array<string,mixed>> $list  Stored rules.
	 * @param array<mixed>              $forms Posted fields by rule id.
	 * @return list<array<string,mixed>>
	 */
	public static function apply_forms( array $list, array $forms ): array {
		$byid = array();
		foreach ( $list as $i => $r ) {
			$byid[ FLXC_Val::str( $r['id'] ?? '' ) ] = $i;
		}
		foreach ( $forms as $rid => $f ) {
			if ( ! isset( $byid[ $rid ] ) || ! is_array( $f ) ) {
				continue;
			}
			$r               = $list[ $byid[ $rid ] ];
			$r['title']      = sanitize_text_field( FLXC_Val::raw_str( $f['title'] ?? '' ) );
			$r['active']     = empty( $f['active'] ) ? 0 : 1;
			$r['audience']   = in_array( $f['audience'] ?? '', array( 'receive', 'view', 'users' ), true ) ? $f['audience'] : 'receive';
			$r['users']      = array_values( array_filter( array_map( static fn( $x ): int => FLXC_Val::raw_int( $x ), FLXC_Val::raw_arr( $f['users'] ?? array() ) ) ) );
			$r['content']    = 'general' === ( $f['content'] ?? '' ) ? 'general' : 'own';
			$r['period']     = in_array( $f['period'] ?? '', array( 'today', 'yesterday', 'week', 'month' ), true ) ? $f['period'] : 'today';
			$r['freq']       = in_array( $f['freq'] ?? '', array( 'daily', 'weekly', 'monthly' ), true ) ? $f['freq'] : 'daily';
			$r['weekday']    = min( 7, max( 1, FLXC_Val::raw_int( $f['weekday'] ?? 1 ) ) );
			$r['monthday']   = min( 28, max( 1, FLXC_Val::raw_int( $f['monthday'] ?? 1 ) ) );
			$r['time']       = preg_match( '/^\d{2}:\d{2}$/', FLXC_Val::raw_str( $f['time'] ?? '' ) ) ? $f['time'] : '18:00';
			$r['motivation'] = sanitize_textarea_field( FLXC_Val::raw_str( $f['motivation'] ?? '' ) );
			$list[ $byid[ $rid ] ] = $r;
		}
		return $list;
	}

	/**
	 * A new, inactive rule appended to the list.
	 *
	 * @param list<array<string,mixed>> $list Stored rules.
	 * @return list<array<string,mixed>>
	 */
	public static function with_new( array $list ): array {
		/** @var array<string,mixed> $new */
		$new    = wp_parse_args( array( 'id' => 'r' . wp_generate_password( 6, false, false ), 'title' => __( 'New report', 'flx-chat' ), 'active' => 0 ), FLXC_Val::arr( flxc_report_defaults() ) );
		$list[] = $new;
		return $list;
	}

	/**
	 * @param list<array<string,mixed>> $list Stored rules.
	 * @return list<array<string,mixed>>
	 */
	public static function without( array $list, string $id ): array {
		foreach ( $list as $i => $r ) {
			if ( FLXC_Val::str( $r['id'] ?? '' ) === $id ) {
				array_splice( $list, $i, 1 );
				break;
			}
		}
		return $list;
	}

	/**
	 * One rule by id, or null.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function rule( string $id ): ?array {
		foreach ( self::rules() as $r ) {
			if ( FLXC_Val::str( $r['id'] ?? '' ) === $id ) {
				return $r;
			}
		}
		return null;
	}

	/**
	 * Sends a rule's report to the current user only. `0`/`1` messages sent, or an error when the
	 * profile has no WhatsApp number.
	 *
	 * @param array<string,mixed> $rule Rule.
	 * @return int|WP_Error
	 */
	public static function send_test( array $rule ) {
		$me = function_exists( 'flxr_user_phone' ) ? FLXC_Val::str( flxr_user_phone( get_current_user_id() ) ) : '';
		if ( '' === $me ) {
			return new WP_Error( 'flxc_report_no_number', __( 'Your profile has no WhatsApp number (Users › Profile).', 'flx-chat' ), array( 'status' => 400 ) );
		}
		return flxc_report_send( $me, wp_get_current_user()->display_name, flxc_report_text( $rule, get_current_user_id() ) ) ? 1 : 0;
	}

	/**
	 * Sends a rule's report to its recipients now (at most 25 people per call, like the schedule).
	 *
	 * @param array<string,mixed> $rule Rule.
	 */
	public static function send_now( array $rule ): int {
		$n = 0;
		foreach ( array_slice( self::recipients( $rule ), 0, 25 ) as $p ) {
			$n += flxc_report_send( $p['phone'], $p['name'], flxc_report_text( $rule, $p['id'] ) ) ? 1 : 0;
		}
		return $n;
	}

	/**
	 * @param array<string,mixed> $rule Rule.
	 * @return list<array{id: int, name: string, phone: string}>
	 */
	private static function recipients( array $rule ): array {
		$out = array();
		foreach ( FLXC_Val::arr( flxc_report_recipients( $rule ) ) as $p ) {
			$p = FLXC_Val::arr( $p );
			if ( array() !== $p ) {
				$out[] = array(
					'id'    => FLXC_Val::int( $p['id'] ?? 0 ),
					'name'  => FLXC_Val::str( $p['name'] ?? '' ),
					'phone' => FLXC_Val::str( $p['phone'] ?? '' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Report text as the current user would get it.
	 *
	 * @param array<string,mixed> $rule Rule.
	 */
	public static function preview( array $rule ): string {
		return FLXC_Val::bool( flxc_reports_on() ) ? FLXC_Val::str( flxc_report_text( $rule, get_current_user_id() ) ) : '';
	}

	/**
	 * Screen model (the preview is a separate, per-rule call): the rules, the people a rule can be sent to and the permission/dependency state.
	 *
	 * @return array{on: bool, days: list<array{value: int, label: string}>, people: list<array{id: int, name: string, hasNumber: bool}>, rules: list<array<string,mixed>>}
	 */
	public static function screen(): array {
		$days = array();
		for ( $n = 1; $n <= 7; $n++ ) {
			$days[] = array(
				'value' => $n,
				'label' => FLXC_Val::str( flx_date( (int) gmmktime( 12, 0, 0, 1, 4 + $n, 2026 ), 'l' ) ), // 5 January 2026 is a Monday.
			);
		}
		$people = array();
		if ( function_exists( 'flxr_user_phone' ) ) {
			foreach ( get_users( array( 'capability__in' => array( 'flx_reports_receive', 'flx_reports_view' ), 'number' => 300, 'orderby' => 'display_name' ) ) as $u ) {
				if ( $u instanceof WP_User ) {
					$people[] = array(
						'id'        => $u->ID,
						'name'      => $u->display_name,
						'hasNumber' => '' !== FLXC_Val::str( flxr_user_phone( $u->ID ) ),
					);
				}
			}
		}
		$rules = array();
		foreach ( self::rules() as $r ) {
			$slot    = FLXC_Val::str( $r['slot'] ?? '' );
			$rules[] = array(
				'id'             => FLXC_Val::str( $r['id'] ?? '' ),
				'title'          => FLXC_Val::str( $r['title'] ?? '' ),
				'active'         => FLXC_Val::bool( $r['active'] ?? 0 ),
				'audience'       => FLXC_Val::str( $r['audience'] ?? 'receive' ),
				'users'          => self::ints( $r['users'] ?? array() ),
				'content'        => FLXC_Val::str( $r['content'] ?? 'own' ),
				'period'         => FLXC_Val::str( $r['period'] ?? 'today' ),
				'freq'           => FLXC_Val::str( $r['freq'] ?? 'daily' ),
				'weekday'        => FLXC_Val::int( $r['weekday'] ?? 1 ),
				'monthday'       => FLXC_Val::int( $r['monthday'] ?? 1 ),
				'time'           => FLXC_Val::str( $r['time'] ?? '18:00' ),
				'motivation'     => FLXC_Val::str( $r['motivation'] ?? '' ),
				'recipientCount' => count( self::recipients( $r ) ),
				'lastSent'       => '' !== $slot ? (int) strtotime( $slot . ' 12:00' ) : 0,
			);
		}
		return array(
			'on'     => FLXC_Val::bool( flxc_reports_on() ),
			'days'   => $days,
			'people' => $people,
			'rules'  => $rules,
		);
	}

	/**
	 * Delivery state: whether something is due, whether a run holds the lock, the next scheduled tick
	 * and the last background request problem. Nothing here sends.
	 *
	 * @return array{on: bool, due: bool, running: bool, nextAt: int, loopbackError: string, loopbackErrorAt: int}
	 */
	public static function status(): array {
		$err  = get_option( 'flxc_loopback_err' );
		$next = wp_next_scheduled( 'flxc_reports_tick' );
		return array(
			'on'              => FLXC_Val::bool( flxc_reports_on() ),
			'due'             => FLXC_Val::bool( flxc_reports_has_due() ),
			'running'         => (bool) get_transient( 'flxc_reports_lock' ),
			'nextAt'          => false === $next ? 0 : (int) $next,
			'loopbackError'   => is_array( $err ) ? FLXC_Val::str( $err['msg'] ?? '' ) : '',
			'loopbackErrorAt' => is_array( $err ) ? FLXC_Val::int( $err['at'] ?? 0 ) : 0,
		);
	}

	/**
	 * Starts a run on the token-protected loopback (the same one chat jobs use; Hostinger does not run
	 * WP-Cron and spawn_cron is not a fix). Returns whether the background request was accepted.
	 */
	public static function run(): bool {
		if ( ! FLXC_Val::bool( flxc_reports_on() ) ) {
			return false;
		}
		return FLXC_Val::bool( flxc_reports_kick() );
	}
}
