<?php
/**
 * Import from Excel for the admin screen (GraphQL part b8): the upload page with earlier imports, the preview
 * (columns, options, matches, rows) and the run (step by step).
 *
 * The wp-admin screen (packages/modules/src/donate/Import.tsx) calls this class. Every call needs
 * flxb_can_manage(). The file upload stays a form post (admin-post flxb_import_upload): the screen posts the
 * file with the nonce it gets here.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_Admin_Import_Service {

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	private static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * @param mixed $v Value.
	 * @return array<string,mixed>
	 */
	private static function map( $v ): array {
		$out = array();
		foreach ( is_array( $v ) ? $v : array() as $k => $x ) {
			$out[ (string) $k ] = $x;
		}
		return $out;
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function s( $v ): string {
		return is_scalar( $v ) ? (string) $v : '';
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function i( $v ): int {
		return is_numeric( $v ) ? (int) $v : 0;
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function n( $v ): string {
		return self::s( number_format_i18n( (float) self::i( $v ) ) );
	}

	public static function can(): bool {
		return (bool) flxb_can_manage();
	}

	private static function invalid( string $msg ): WP_Error {
		return new WP_Error( 'flxb_import', $msg, array( 'graphql_code' => 'INVALID_INPUT' ) );
	}

	/**
	 * @param array<int|string,string> $pairs value => label.
	 * @return list<array{value: string, label: string}>
	 */
	private static function choices( array $pairs ): array {
		$out = array();
		foreach ( $pairs as $k => $v ) {
			$out[] = array(
				'value' => (string) $k,
				'label' => $v,
			);
		}
		return $out;
	}

	/**
	 * @param list<array{0: string, 1: string, 2: string}> $k Label, value, accent.
	 * @return list<array{label: string, value: string, accent: string}>
	 */
	private static function kpis( array $k ): array {
		return array_map(
			static fn( array $x ): array => array(
				'label'  => $x[0],
				'value'  => $x[1],
				'accent' => $x[2],
			),
			$k
		);
	}

	/**
	 * The import page: id '' = the upload page with earlier imports; else one import (null when it is gone).
	 *
	 * @return array<string,mixed>|null
	 */
	public static function page( string $id ): ?array {
		$id   = sanitize_key( $id );
		$base = array(
			'id'          => $id,
			'state'       => 'upload',
			'file'        => '',
			'uploadUrl'   => admin_url( 'admin-post.php' ),
			'uploadNonce' => wp_create_nonce( 'flxb_import_upload' ),
			'earlier'     => array(),
			'preview'     => null,
			'run'         => null,
		);
		if ( '' === $id ) {
			$st = array(
				'preview' => __( 'Waiting', 'flx-donate' ),
				'running' => __( 'Running', 'flx-donate' ),
				'done'    => __( 'Finished', 'flx-donate' ),
			);
			foreach ( self::arr( FLXB_Import::all() ) as $d ) {
				$d                 = self::map( $d );
				$state             = self::s( $d['state'] ?? '' );
				$base['earlier'][] = array(
					'id'    => self::s( $d['id'] ?? '' ),
					'file'  => self::s( $d['file'] ?? '' ),
					'state' => $st[ $state ] ?? $state,
					/* translators: 1: rows, 2: created */
					'sub'   => self::s( flxb_date( self::s( $d['created_at'] ?? '' ) ) ) . ' · ' . sprintf( __( '%1$s rows, %2$s written', 'flx-donate' ), number_format_i18n( count( self::arr( $d['rows'] ?? array() ) ) ), self::n( self::map( $d['result'] ?? array() )['created'] ?? 0 ) ),
				);
			}
			return $base;
		}
		$imp = FLXB_Import::get( $id );
		if ( ! is_array( $imp ) ) {
			return null;
		}
		$imp           = self::map( $imp );
		$base['file']  = self::s( $imp['file'] ?? '' );
		$base['state'] = self::s( $imp['state'] ?? '' );
		if ( 'preview' === $base['state'] ) {
			$base['preview'] = self::preview( $imp );
		} else {
			$base['run'] = self::run( $imp );
		}
		return $base;
	}

	/**
	 * The run of an import: progress, counts, errors.
	 *
	 * @param array<string,mixed> $imp Import.
	 * @return array<string,mixed>
	 */
	private static function run( array $imp ): array {
		$total = count( self::arr( $imp['rows'] ?? array() ) );
		$pos   = self::i( $imp['pos'] ?? 0 );
		$r     = self::map( $imp['result'] ?? array() );
		$errs  = array();
		foreach ( self::arr( $r['errors'] ?? array() ) as $e ) {
			$e      = self::map( $e );
			$errs[] = array(
				'n'    => self::i( $e['n'] ?? 0 ),
				'name' => self::s( $e['name'] ?? '' ),
				'msg'  => self::s( $e['msg'] ?? '' ),
			);
		}
		$track = flxb_track_enabled();
		return array(
			'done'      => 'done' === self::s( $imp['state'] ?? '' ),
			'pos'       => $pos,
			'total'     => $total,
			'percent'   => $total ? (int) floor( 100 * $pos / $total ) : 100,
			/* translators: 1: rows done, 2: rows in all */
			'progress'  => sprintf( __( '%1$s / %2$s rows', 'flx-donate' ), number_format_i18n( $pos ), number_format_i18n( $total ) ),
			'kpis'      => self::kpis(
				array(
					array( __( 'Written', 'flx-donate' ), self::n( $r['created'] ?? 0 ), '#1F7A4D' ),
					array( __( 'Skipped (already imported)', 'flx-donate' ), self::n( $r['skipped'] ?? 0 ), '#6B7C8F' ),
					array( __( 'Errors', 'flx-donate' ), self::n( $r['failed'] ?? 0 ), '#B42318' ),
				)
			),
			'errors'    => $errs,
			'nextUrl'   => self::s( FLXB_Admin_Url::page( $track ? 'flxb-track' : 'flxb-donations' ) ),
			'nextLabel' => $track ? self::s( FLXB_Track::label( 'menu' ) ) : __( 'Donations', 'flx-donate' ),
		);
	}

	/**
	 * The preview: counts, summary, columns, options, matches and rows.
	 *
	 * @param array<string,mixed> $imp Import.
	 * @return array<string,mixed>
	 */
	private static function preview( array $imp ): array {
		$a      = self::map( FLXB_Import::analyze( $imp ) );
		$fields = self::arr( FLXB_Import::fields() );
		$s      = self::map( $a['stats'] ?? array() );
		$sum    = self::map( $a['summary'] ?? array() );
		$cur    = self::s( $sum['currency'] ?? '' );
		$map    = self::arr( $imp['map'] ?? array() );
		$rows   = self::arr( $imp['rows'] ?? array() );
		$opts   = self::map( $imp['opts'] ?? array() );

		$by_ref = array();
		$refs   = self::arr( $sum['by_ref'] ?? array() );
		uasort( $refs, static fn( $x, $y ): int => self::i( self::map( $y )['rows'] ?? 0 ) <=> self::i( self::map( $x )['rows'] ?? 0 ) );
		foreach ( $refs as $uid => $x ) {
			$x        = self::map( $x );
			$u        = $uid ? get_userdata( (int) $uid ) : null;
			$by_ref[] = array(
				'name'   => $u ? $u->display_name : __( '(none)', 'flx-donate' ),
				'rows'   => self::n( $x['rows'] ?? 0 ),
				'units'  => self::n( $x['units'] ?? 0 ),
				'amount' => self::s( flxb_money( $x['amount'] ?? 0, $cur ) ),
			);
		}

		$fch = array( '' => '— ' . __( 'skip', 'flx-donate' ) . ' —' );
		foreach ( $fields as $f => $def ) {
			$fch[ (string) $f ] = self::s( self::arr( $def )[0] ?? $f );
		}
		$columns = array();
		foreach ( self::arr( $imp['headers'] ?? array() ) as $c => $h ) {
			$h = self::s( $h );
			if ( '' === trim( $h ) && ! isset( $map[ $c ] ) ) {
				continue;
			}
			$mf = self::s( $map[ $c ] ?? '' );
			$ex = '';
			foreach ( array_slice( $rows, 0, 3 ) as $row ) {
				$v = self::s( self::arr( $row )[ $c ] ?? '' );
				if ( '' !== trim( $v ) ) {
					$ex = $v;
					if ( 'created' === $mf && '' !== self::s( FLXB_Import::datetime( $ex ) ) ) {
						$ex = self::s( flxb_date( get_gmt_from_date( self::s( FLXB_Import::datetime( $ex ) ) ) ) );
					} elseif ( 'target' === $mf && '' !== self::s( flxb_parse_date( $ex ) ) ) {
						$ts = strtotime( self::s( flxb_parse_date( $ex ) ) . ' 12:00' );
						$ex = self::s( flx_date( false === $ts ? 0 : $ts ) );
					}
					break;
				}
			}
			$columns[] = array(
				'col'     => (int) $c,
				'header'  => '' !== trim( $h ) ? $h : '#' . ( (int) $c + 1 ),
				'field'   => $mf,
				'example' => '' !== $ex ? wp_trim_words( $ex, 8 ) : '',
			);
		}

		$types = array();
		foreach ( self::arr( FLXB_Import::type_choices() ) as $k => $v ) {
			$types[ (string) $k ] = self::s( $v );
		}
		$users = array( '0' => '—' );
		foreach ( get_users( array( 'fields' => array( 'ID', 'display_name' ), 'number' => 500 ) ) as $u ) {
			if ( is_object( $u ) ) {
				$users[ self::s( $u->ID ?? '' ) ] = self::s( $u->display_name ?? '' );
			}
		}
		$partners = array(
			'0'   => '—',
			'new' => __( '+ Add as new', 'flx-donate' ),
		);
		foreach ( self::arr( FLXB_Partners::all() ) as $pid => $p ) {
			$partners[ (string) $pid ] = self::s( self::map( $p )['name'] ?? '' );
		}
		$statuses = array();
		foreach ( self::arr( FLXB_Track::statuses() ) as $k => $v ) {
			$statuses[ (string) $k ] = self::s( $v );
		}
		$paid_ch = array(
			'1' => __( 'Paid', 'flx-donate' ),
			'0' => __( 'Waiting for payment', 'flx-donate' ),
		);
		$sets    = array(
			'type'    => array( __( 'Donation types', 'flx-donate' ), array( '' => '— ' . __( 'choose', 'flx-donate' ) . ' —' ) + $types, array( 'type', 'option' ) ),
			'partner' => array( self::s( FLXB_Track::label( 'partner' ) ), $partners, array( 'partner' ) ),
			'source'  => array( __( 'Brought in by', 'flx-donate' ), $partners, array( 'source' ) ),
			'status'  => array( __( 'Status', 'flx-donate' ), $statuses, array( 'status' ) ),
			'ref'     => array( __( 'Referral', 'flx-donate' ), $users, array( 'ref' ) ),
			'paid'    => array( __( 'Payment', 'flx-donate' ), $paid_ch, array( 'paid' ) ),
		);
		$groups  = self::map( $a['groups'] ?? array() );
		$matches = array();
		foreach ( $sets as $g => $def ) {
			if ( ! array_intersect( array_map( array( __CLASS__, 's' ), self::arr( $def[2] ) ), array_map( array( __CLASS__, 's' ), $map ) ) ) {
				continue;
			}
			$list = array();
			foreach ( self::arr( $groups[ $g ] ?? array() ) as $k => $x ) {
				$x      = self::map( $x );
				$raw    = self::s( $x['raw'] ?? '' );
				$list[] = array(
					'key'   => (string) $k,
					'raw'   => '' !== $raw ? $raw : __( '(empty)', 'flx-donate' ),
					'count' => self::i( $x['n'] ?? 0 ),
					'to'    => self::s( $x['to'] ?? '' ),
				);
			}
			$matches[] = array(
				'group'   => $g,
				'title'   => $def[0],
				'choices' => self::choices( $def[1] ),
				'rows'    => $list,
			);
		}

		$all      = self::arr( $a['rows'] ?? array() );
		$problems = array_filter(
			$all,
			static function ( $r ): bool {
				$r = is_array( $r ) ? $r : array();
				return ! empty( $r['errors'] ) || ! empty( $r['warns'] ) || ! empty( $r['dup'] );
			}
		);
		$list     = array_slice( $problems + $all, 0, 200, true );
		ksort( $list );
		$keep = ! empty( $opts['keep_date'] );
		$out  = array();
		foreach ( $list as $rr ) {
			$r      = self::map( $rr );
			$tr     = self::i( $r['type_id'] ?? 0 ) ? FLXB_Types::get( self::i( $r['type_id'] ) ) : null;
			$t      = is_array( $tr ) ? self::map( $tr ) : null;
			$checks = array();
			if ( ! empty( $r['dup'] ) ) {
				$checks[] = array(
					'label' => __( 'Already imported', 'flx-donate' ),
					'tone'  => 'muted',
				);
			}
			foreach ( self::arr( $r['errors'] ?? array() ) as $e ) {
				$checks[] = array(
					'label' => self::s( $e ),
					'tone'  => 'failed',
				);
			}
			foreach ( self::arr( $r['warns'] ?? array() ) as $w ) {
				$checks[] = array(
					'label' => self::s( $w ),
					'tone'  => 'pending',
				);
			}
			if ( ! $checks ) {
				$checks[] = array(
					'label' => __( 'Ready', 'flx-donate' ),
					'tone'  => 'ok',
				);
			}
			$donor   = self::s( $r['donor'] ?? '' );
			$name    = self::s( $r['name'] ?? '' );
			$who     = '' !== $donor ? $donor : $name;
			$names   = array_map( array( __CLASS__, 's' ), self::arr( $r['names'] ?? array() ) );
			$created = self::s( $r['created'] ?? '' );
			$rcur    = self::s( $r['currency'] ?? '' );
			$amount  = $r['amount'] ?? null;
			$st      = self::s( $r['status'] ?? '' );
			$out[]   = array(
				'n'        => self::i( $r['n'] ?? 0 ),
				'who'      => $who,
				'sub'      => count( $names ) > 1 ? implode( ' · ', $names ) : ( '' !== $name && $name !== $who ? $name : '' ),
				'date'     => $keep && '' !== $created ? self::s( flxb_date( get_gmt_from_date( $created ) ) ) : __( 'Today', 'flx-donate' ),
				'dated'    => ! empty( $r['dated'] ),
				'type'     => $t ? self::s( $t['title'] ?? '' ) : '—',
				'quantity' => self::i( $r['qty'] ?? 0 ),
				'amount'   => null === $amount ? '—' : self::s( flxb_money( $amount, '' !== $rcur ? $rcur : ( $t ? self::s( $t['currency'] ?? '' ) : '' ) ) ),
				'status'   => $t && ! FLXB_Types::tracks_items( $t ) ? ( ! empty( $r['paid'] ) ? __( 'Paid', 'flx-donate' ) : __( 'Waiting for payment', 'flx-donate' ) ) : self::s( $statuses[ $st ] ?? $st ),
				'checks'   => $checks,
			);
		}
		$ok = self::i( $s['ok'] ?? 0 );
		return array(
			'stats'    => self::kpis(
				array(
					array( __( 'Rows', 'flx-donate' ), self::n( $s['total'] ?? 0 ), '' ),
					array( __( 'Ready', 'flx-donate' ), self::n( $ok ), '#1F7A4D' ),
					array( __( 'With a warning', 'flx-donate' ), self::n( $s['warn'] ?? 0 ), '#B7791F' ),
					array( __( 'Already imported', 'flx-donate' ), self::n( $s['dup'] ?? 0 ), '#6B7C8F' ),
					array( __( 'Errors', 'flx-donate' ), self::n( $s['error'] ?? 0 ), '#B42318' ),
				)
			),
			'summary'  => self::kpis(
				array(
					array( __( 'Records', 'flx-donate' ), self::n( $sum['rows'] ?? 0 ), '' ),
					array( __( 'Units', 'flx-donate' ), self::n( $sum['units'] ?? 0 ), '' ),
					array( __( 'Paid', 'flx-donate' ), self::s( flxb_money( $sum['paid'] ?? 0, $cur ) ) . ' · ' . self::n( $sum['paid_n'] ?? 0 ), '#1F7A4D' ),
					array( __( 'Waiting for payment', 'flx-donate' ), self::s( flxb_money( $sum['waiting'] ?? 0, $cur ) ) . ' · ' . self::n( $sum['waiting_n'] ?? 0 ), '#B7791F' ),
				)
			),
			'byRef'    => $by_ref,
			/* translators: 1: records, 2: units, 3: paid amount, 4: amount waiting for payment */
			'confirm'  => sprintf( __( '%1$s records (%2$s units) will be written. Paid: %3$s, waiting for payment: %4$s. Nothing is sent to anyone.', 'flx-donate' ), self::n( $sum['rows'] ?? 0 ), self::n( $sum['units'] ?? 0 ), self::s( flxb_money( $sum['paid'] ?? 0, $cur ) ), self::s( flxb_money( $sum['waiting'] ?? 0, $cur ) ) ),
			'ready'    => $ok,
			/* translators: %s: number of rows */
			'runLabel' => sprintf( _n( 'Import %s row', 'Import %s rows', $ok, 'flx-donate' ), number_format_i18n( $ok ) ),
			'fields'   => self::choices( $fch ),
			'columns'  => $columns,
			'types'    => self::choices( array( '' => '—' ) + $types ),
			'options'  => array(
				'type'      => self::s( $opts['type'] ?? '' ),
				'paid'      => ! empty( $opts['paid'] ),
				'keepDate'  => $keep,
				'nodate'    => self::s( $opts['nodate'] ?? 'today' ),
				'nodateDay' => self::s( $opts['nodate_day'] ?? '' ),
			),
			'matches'  => $matches,
			'rows'     => $out,
			/* translators: %s: number of rows */
			'more'     => count( $all ) > count( $list ) ? sprintf( __( 'Showing rows with a problem first; %s rows in all.', 'flx-donate' ), number_format_i18n( count( $all ) ) ) : '',
		);
	}

	/**
	 * Saves the columns, options and matches; with $run (and no column change) the import starts.
	 *
	 * @param array<int,string>                $map  Column => field.
	 * @param list<array<string,string>>       $res  Matches: group, key, value.
	 * @param array<string,mixed>              $o    Options: type, paid, keepDate, nodate, nodateDay.
	 * @return array{message: string, state: string}|WP_Error
	 */
	public static function save( string $id, array $map, array $res, array $o, bool $run ) {
		$raw = FLXB_Import::get( sanitize_key( $id ) );
		if ( ! is_array( $raw ) || 'preview' !== ( $raw['state'] ?? '' ) ) {
			return self::invalid( __( 'The import was not found or has finished.', 'flx-donate' ) );
		}
		$imp    = $raw;
		$fields = self::arr( FLXB_Import::fields() );
		$clean  = array();
		foreach ( $map as $c => $f ) {
			$f = sanitize_key( $f );
			if ( '' !== $f && isset( $fields[ $f ] ) && ! in_array( $f, $clean, true ) ) {
				$clean[ (int) $c ] = $f;
			}
		}
		ksort( $clean );
		$old = self::arr( $imp['map'] ?? array() );
		ksort( $old );
		$changed    = $clean !== $old;
		$imp['map'] = $clean;
		$matches    = array();
		if ( ! $changed ) { // new columns make new values: earlier matches would point at the wrong ones
			foreach ( $res as $x ) {
				$g = self::s( $x['group'] ?? '' );
				if ( ! in_array( $g, array( 'type', 'partner', 'source', 'status', 'ref', 'paid' ), true ) ) {
					continue;
				}
				$v                                           = self::s( $x['value'] ?? '' );
				$matches[ $g ][ self::s( $x['key'] ?? '' ) ] = 'type' === $g ? self::s( preg_replace( '/[^a-z0-9|_-]/', '', $v ) ) : sanitize_key( $v );
			}
		}
		$imp['res']  = $matches;
		$nodate      = self::s( $o['nodate'] ?? '' );
		$imp['opts'] = array(
			'type'       => self::s( preg_replace( '/[^a-z0-9|_-]/', '', self::s( $o['type'] ?? '' ) ) ),
			'paid'       => empty( $o['paid'] ) ? 0 : 1,
			'keep_date'  => empty( $o['keepDate'] ) ? 0 : 1,
			'nodate'     => in_array( $nodate, array( 'above', 'target', 'day', 'today' ), true ) ? $nodate : 'today',
			'nodate_day' => flxb_parse_date( sanitize_text_field( self::s( $o['nodateDay'] ?? '' ) ) ),
		);
		if ( $run && ! $changed ) {
			FLXB_Import::prepare( $imp );
		} else {
			FLXB_Import::save( $imp );
		}
		return array(
			'message' => __( 'Saved.', 'flx-donate' ),
			'state'   => self::s( $imp['state'] ?? '' ),
		);
	}

	/** @return array{message: string} */
	public static function delete( string $id ): array {
		FLXB_Import::delete( sanitize_key( $id ) );
		return array( 'message' => __( 'Deleted.', 'flx-donate' ) );
	}

	/**
	 * Writes the next rows of a running import.
	 *
	 * @return array{message: string, done: bool}|WP_Error
	 */
	public static function step( string $id ) {
		$r = FLXB_Import::step( sanitize_key( $id ) );
		if ( is_wp_error( $r ) ) {
			return self::invalid( $r->get_error_message() );
		}
		$r = self::map( $r );
		return array(
			'message' => __( 'Saved.', 'flx-donate' ),
			'done'    => ! empty( $r['done'] ),
		);
	}
}
