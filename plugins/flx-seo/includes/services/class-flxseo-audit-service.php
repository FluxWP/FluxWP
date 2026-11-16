<?php
/**
 * Site audit: the one place that reads the audit report, its history and schedule, runs audits, applies one-click
 * fixes, checks content and returns the build guide. The classic tabs (AJAX / admin-post), REST (Flux App),
 * GraphQL and Flux AI call this. Permission checks stay with the caller (manage_options), as before.
 *
 * Errors are WP_Error with `graphql_code` INVALID_INPUT / NOT_FOUND / INTERNAL and, for input problems, the `field`.
 *
 * @package flx-seo
 */

defined( 'ABSPATH' ) || exit;

final class FLXSEO_Audit_Service {

	public const CACHE_TAG = 'flx-seo:audit';

	/**
	 * The current report.
	 */
	public static function audit(): FLXSEO_Audit {
		$r      = FLXSEO_Util::arr( flxseo_audit_report() );
		$prios  = flxseo_audit_priorities();
		$cats   = flxseo_audit_categories();
		$owners = flxseo_audit_owners();
		$fixes  = flxseo_system_fixes();

		$findings = array();
		foreach ( FLXSEO_Util::arr( $r['findings'] ?? null ) as $f ) {
			$f = FLXSEO_Util::arr( $f );
			$id = FLXSEO_Util::str( $f['id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$prio     = FLXSEO_Util::str( $f['priority'] ?? 'low' );
			$cat      = FLXSEO_Util::str( $f['category'] ?? 'seo' );
			$owner    = FLXSEO_Util::str( $f['owner'] ?? '' );
			$fix_id   = FLXSEO_Util::str( $f['fix_id'] ?? '' );
			$fix_name = '' !== $fix_id && isset( $fixes[ $fix_id ] ) ? FLXSEO_Util::str( FLXSEO_Util::arr( $fixes[ $fix_id ] )['label'] ?? '' ) : '';
			$findings[] = new FLXSEO_Finding(
				$id,
				FLXSEO_Util::str( $f['title'] ?? $id ),
				$prio,
				FLXSEO_Util::str( $prios[ $prio ][1] ?? $prio ),
				$cat,
				FLXSEO_Util::str( $cats[ $cat ] ?? $cat ),
				FLXSEO_Util::strings( $f['also'] ?? null ),
				FLXSEO_Util::str( $f['detail'] ?? '' ),
				FLXSEO_Util::str( $f['fix'] ?? '' ),
				'' !== $owner ? $owner : null,
				'' !== $owner ? FLXSEO_Util::str( $owners[ $owner ] ?? $owner ) : null,
				FLXSEO_Util::strings( $f['items'] ?? null ),
				FLXSEO_Util::strings( $f['sources'] ?? null ),
				'' !== $fix_id ? $fix_id : null,
				'' !== $fix_id ? ( '' !== $fix_name ? $fix_name : __( 'Fix', 'flx-seo' ) ) : null,
			);
		}

		$local_raw = FLXSEO_Util::arr( $r['local'] ?? null );
		$local     = ! empty( $local_raw['time'] )
			? new FLXSEO_Local_Run(
				FLXSEO_Util::int( $local_raw['time'] ),
				FLXSEO_Util::str( $local_raw['url'] ?? '' ),
				FLXSEO_Util::int( $local_raw['ms'] ?? 0 ),
				FLXSEO_Util::int( $local_raw['code'] ?? 0 ),
			)
			: null;

		$speed = array();
		foreach ( array( 'mobile', 'desktop' ) as $strategy ) {
			$p = FLXSEO_Util::arr( FLXSEO_Util::arr( $r['psi'] ?? null )[ $strategy ] ?? null );
			if ( ! $p ) {
				continue;
			}
			$scores = array();
			foreach ( FLXSEO_Util::arr( $p['scores'] ?? null ) as $cid => $sc ) {
				$sc       = FLXSEO_Util::arr( $sc );
				$scores[] = new FLXSEO_Score(
					(string) $cid,
					FLXSEO_Util::str( $cats[ $cid ] ?? ( $sc['title'] ?? $cid ) ),
					isset( $sc['score'] ) && is_numeric( $sc['score'] ) ? (int) $sc['score'] : null,
					FLXSEO_Util::int( $sc['passed'] ?? 0 ),
					FLXSEO_Util::int( $sc['total'] ?? 0 ),
				);
			}
			$metrics = array();
			foreach ( FLXSEO_Util::arr( $p['metrics'] ?? null ) as $mid => $m ) {
				$m         = FLXSEO_Util::arr( $m );
				$metrics[] = new FLXSEO_Metric(
					(string) $mid,
					FLXSEO_Util::str( $m['title'] ?? $mid ),
					FLXSEO_Util::str( $m['value'] ?? '' ),
					isset( $m['score'] ) && is_numeric( $m['score'] ) ? (float) $m['score'] : null,
				);
			}
			$speed[] = new FLXSEO_Page_Speed(
				$strategy,
				FLXSEO_Util::int( $p['time'] ?? 0 ),
				FLXSEO_Util::str( $p['url'] ?? '' ),
				FLXSEO_Util::str( $p['lighthouse'] ?? '' ),
				$scores,
				$metrics,
			);
		}

		$agentic = array();
		foreach ( flxseo_audit_agentic( $r ) as $key => $c ) {
			$agentic[] = new FLXSEO_Agentic_Check( (string) $key, FLXSEO_Util::str( $c[0] ), (bool) $c[1] );
		}

		return new FLXSEO_Audit(
			self::counts( FLXSEO_Util::arr( $r['counts'] ?? null ) ),
			$local,
			$speed,
			$agentic,
			$findings,
			null !== $local && '' !== $local->url ? $local->url : home_url( '/' ),
			flxseo_mcp_server_on(),
			home_url( '/llms.txt' ),
		);
	}

	/**
	 * @param array<mixed> $c Counts by priority.
	 */
	private static function counts( array $c ): FLXSEO_Counts {
		return new FLXSEO_Counts(
			FLXSEO_Util::int( $c['critical'] ?? 0 ),
			FLXSEO_Util::int( $c['high'] ?? 0 ),
			FLXSEO_Util::int( $c['medium'] ?? 0 ),
			FLXSEO_Util::int( $c['low'] ?? 0 ),
		);
	}

	/**
	 * Saved reports and the schedule.
	 *
	 * @param int $limit Reports to return (1–90).
	 */
	public static function history( int $limit = 30 ): FLXSEO_History {
		$limit    = max( 1, min( FLXSEO_HISTORY_MAX, $limit ) );
		$triggers = flxseo_audit_triggers();
		$cats     = flxseo_audit_categories();
		$entries  = array();
		foreach ( flxseo_audit_history( $limit ) as $e ) {
			$e      = FLXSEO_Util::arr( $e );
			$titles = array();
			foreach ( FLXSEO_Util::arr( $e['items'] ?? null ) as $it ) {
				$it = FLXSEO_Util::arr( $it );
				$titles[ FLXSEO_Util::str( $it[0] ?? '' ) ] = FLXSEO_Util::str( $it[2] ?? '' );
			}
			$scores = array();
			foreach ( FLXSEO_Util::arr( $e['scores'] ?? null ) as $strategy => $by_cat ) {
				foreach ( FLXSEO_Util::arr( $by_cat ) as $cid => $v ) {
					$scores[] = new FLXSEO_History_Score( (string) $strategy, (string) $cid, FLXSEO_Util::str( $cats[ $cid ] ?? $cid ), is_numeric( $v ) ? (int) $v : null );
				}
			}
			$metrics = array();
			foreach ( FLXSEO_Util::arr( $e['metrics'] ?? null ) as $strategy => $m ) {
				$m         = FLXSEO_Util::arr( $m );
				$metrics[] = new FLXSEO_History_Metrics(
					(string) $strategy,
					FLXSEO_Util::str( $m['fcp'] ?? '' ),
					FLXSEO_Util::str( $m['lcp'] ?? '' ),
					FLXSEO_Util::str( $m['tbt'] ?? '' ),
					FLXSEO_Util::str( $m['cls'] ?? '' ),
					FLXSEO_Util::str( $m['si'] ?? '' ),
				);
			}
			$added = array();
			foreach ( FLXSEO_Util::strings( $e['new'] ?? null ) as $id ) {
				$added[] = new FLXSEO_Finding_Ref( $id, FLXSEO_Util::str( $titles[ $id ] ?? $id ) );
			}
			$resolved = array();
			foreach ( FLXSEO_Util::strings( $e['fixed'] ?? null ) as $id ) {
				$resolved[] = new FLXSEO_Finding_Ref( $id, FLXSEO_Util::str( flxseo_audit_title( $id, $id ) ) );
			}
			$trigger   = FLXSEO_Util::str( $e['trigger'] ?? 'manual' );
			$entries[] = new FLXSEO_History_Entry(
				FLXSEO_Util::int( $e['t'] ?? 0 ),
				$trigger,
				FLXSEO_Util::str( $triggers[ $trigger ] ?? $trigger ),
				FLXSEO_Util::str( $e['url'] ?? '' ),
				self::counts( FLXSEO_Util::arr( $e['counts'] ?? null ) ),
				$scores,
				$metrics,
				FLXSEO_Util::str( $e['agentic'] ?? '' ),
				$added,
				$resolved,
			);
		}
		$running = is_array( get_option( 'flxseo_audit_job' ) );
		$next    = $running ? time() : flxseo_audit_next_run();
		return new FLXSEO_History(
			$entries,
			count( flxseo_audit_history() ),
			$next > 0 ? (int) $next : null,
			$running,
			FLXSEO_Util::strings( get_option( 'flxseo_audit_last_errors', array() ) ),
		);
	}

	/**
	 * On-site audit of an address of this site (anything else audits the home page).
	 *
	 * @param string $url Address; empty = home page.
	 * @return FLXSEO_Audit|WP_Error
	 */
	public static function run_local( string $url = '' ) {
		$r = flxseo_audit_run_local( '' !== $url ? flxseo_audit_url( $url ) : '' );
		if ( $r instanceof WP_Error ) {
			return self::failed( $r );
		}
		do_action( 'flxseo_audit_done', 'manual' );
		flxseo_gql_purge( self::CACHE_TAG );
		return self::audit();
	}

	/**
	 * One PageSpeed Insights measurement (15–40 s).
	 *
	 * @param string $strategy mobile|desktop.
	 * @param string $url      Address; empty = home page.
	 * @return FLXSEO_Audit|WP_Error
	 */
	public static function run_pagespeed( string $strategy, string $url = '' ) {
		$r = flxseo_psi_run( 'desktop' === $strategy ? 'desktop' : 'mobile', '' !== $url ? flxseo_audit_url( $url ) : '' );
		if ( $r instanceof WP_Error ) {
			return self::failed( $r );
		}
		flxseo_gql_purge( self::CACHE_TAG );
		return self::audit();
	}

	/**
	 * Applies a one-click fix, refreshes the on-site audit and logs the change.
	 *
	 * @param string $fix_id Fix id from a finding.
	 * @param string $via    Where it came from (audit|graphql).
	 * @return FLXSEO_Audit|WP_Error
	 */
	public static function apply_fix( string $fix_id, string $via = 'audit' ) {
		$fix_id = sanitize_key( $fix_id );
		if ( '' === $fix_id ) {
			return new WP_Error( 'flxseo_fix', __( 'Unknown fix.', 'flx-seo' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'id' ) );
		}
		try {
			$keys = flxseo_fix_apply( $fix_id );
		} catch ( Exception $e ) {
			return new WP_Error( 'flxseo_fix', $e->getMessage(), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'id' ) );
		}
		flxseo_system_fix_log_add(
			array(
				't'   => time(),
				'u'   => get_current_user_id(),
				'fix' => $fix_id,
				'ok'  => true,
				'via' => $via,
				'new' => implode( ',', array_map( 'strval', $keys ) ),
			)
		);
		flxseo_audit_run_local();
		do_action( 'flxseo_audit_done', 'fix' );
		flxseo_gql_purge( self::CACHE_TAG );
		return self::audit();
	}

	/**
	 * Saves or removes the PageSpeed API key. It is never read back.
	 *
	 * @param string|null $key   New key; null or empty keeps the saved one.
	 * @param bool        $clear Delete the saved key.
	 */
	public static function set_psi_key( ?string $key, bool $clear ): bool {
		if ( $clear ) {
			delete_option( 'flxseo_psi_key' );
		} elseif ( null !== $key && '' !== trim( $key ) ) {
			update_option( 'flxseo_psi_key', sanitize_text_field( $key ), false );
		}
		return '' !== flxseo_psi_key();
	}

	/**
	 * Recommendations per published content item (SEO check tab).
	 *
	 * @param string $post_type Post type; empty = every public type.
	 * @param int    $limit     Items to check (1–500).
	 */
	public static function content_check( string $post_type = '', int $limit = 300 ): FLXSEO_Content_Check {
		$r    = FLXSEO_Util::arr( flxseo_audit_site( max( 1, min( 500, $limit ) ), sanitize_key( $post_type ) ) );
		$rows = array();
		foreach ( FLXSEO_Util::arr( $r['items'] ?? null ) as $row ) {
			$row    = FLXSEO_Util::arr( $row );
			$id     = FLXSEO_Util::int( $row['id'] ?? 0 );
			$issues = array();
			foreach ( FLXSEO_Util::arr( $row['issues'] ?? null ) as $i ) {
				$i        = FLXSEO_Util::arr( $i );
				$issues[] = new FLXSEO_Content_Issue(
					'error' === ( $i['level'] ?? '' ) ? 'error' : 'warning',
					FLXSEO_Util::str( $i['code'] ?? '' ),
					FLXSEO_Util::str( $i['msg'] ?? '' ),
				);
			}
			$edit   = $id > 0 && current_user_can( 'edit_post', $id ) ? get_edit_post_link( $id, 'raw' ) : null;
			$rows[] = new FLXSEO_Content_Row(
				$id,
				FLXSEO_Util::str( $row['title'] ?? '' ),
				FLXSEO_Util::str( $row['type'] ?? '' ),
				FLXSEO_Util::str( $row['url'] ?? '' ),
				is_string( $edit ) && '' !== $edit ? $edit : null,
				$issues,
			);
		}
		return new FLXSEO_Content_Check( FLXSEO_Util::int( $r['checked'] ?? 0 ), array_slice( $rows, 0, 100 ), count( $rows ) );
	}

	/**
	 * The new site build guide.
	 *
	 * @param string $lang tr|en; empty = the user's language.
	 */
	public static function guide( string $lang = '' ): FLXSEO_Guide {
		$lang = flxseo_guide_lang( $lang );
		return new FLXSEO_Guide( $lang, flxseo_guide( 'all', $lang ), FLXSEO_Util::strings( flxseo_guide_sections( $lang ) ) );
	}

	/**
	 * Keeps the classic message, adds the code the clients read.
	 */
	private static function failed( WP_Error $e ): WP_Error {
		return new WP_Error( $e->get_error_code(), $e->get_error_message(), array( 'graphql_code' => 'INVALID_INPUT', 'status' => 400 ) );
	}
}

/**
 * Cache purge that works without Flux GraphQL (the services run in the classic page too).
 *
 * @param string $tag Cache tag.
 */
function flxseo_gql_purge( string $tag ): void {
	if ( function_exists( 'flx_gql_purge' ) ) {
		flx_gql_purge( array( $tag ) );
	}
}
