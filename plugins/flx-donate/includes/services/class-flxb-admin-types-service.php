<?php
/**
 * Donation types list for the admin screen (GraphQL part b4).
 *
 * The wp-admin screen (packages/modules/src/donate/Types.tsx) calls this class. Rights are the classic ones:
 * the list needs flx_bagis_view; status, order and sample types need flxb_can_manage(). The type editor
 * (?edit=) is still the classic form.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_Admin_Types_Service {

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	private static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
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
	private static function f( $v ): float {
		return is_numeric( $v ) ? (float) $v : 0.0;
	}

	public static function can_list(): bool {
		return (bool) flxb_can_view();
	}

	public static function can_manage(): bool {
		return (bool) flxb_can_manage();
	}

	private static function day( string $ymd ): string {
		return '' !== $ymd ? self::s( flx_date( (int) strtotime( $ymd . ' 12:00' ) ) ) : '';
	}

	/**
	 * Life-cycle pill of a type: [ tone, label ].
	 *
	 * @param array<string,mixed> $t Type.
	 * @return array{0: string, 1: string}
	 */
	private static function state( array $t ): array {
		switch ( self::s( FLXB_Types::state( $t ) ) ) {
			case 'open':
				return array( 'ok', __( 'Open', 'flx-donate' ) );
			case 'upcoming':
				/* translators: %s: start date */
				return array( 'pending', sprintf( __( 'Upcoming (%s)', 'flx-donate' ), self::day( self::s( $t['start_date'] ?? '' ) ) ) );
			case 'ended':
				return array( 'muted', __( 'Ended', 'flx-donate' ) );
			case 'archived':
				return array( 'muted', __( 'Archived', 'flx-donate' ) );
			default:
				return array( 'pending', __( 'Closed', 'flx-donate' ) );
		}
	}

	/**
	 * The list: open and closed types, or the archive.
	 *
	 * @return array<string,mixed>
	 */
	public static function list( bool $archived ): array {
		$cats   = self::arr( flxb_categories() );
		$manage = self::can_manage();
		$items  = array();
		foreach ( self::arr( FLXB_Types::all( $archived ? 'archived' : '' ) ) as $t ) {
			if ( ! is_array( $t ) ) {
				continue;
			}
			/** @var array<string,mixed> $t */
			$id       = self::i( $t['id'] ?? 0 );
			$currency = self::s( $t['currency'] ?? '' );
			$p        = self::arr( FLXB_Types::progress( $t ) );
			$st       = self::state( $t );
			$options  = self::arr( $t['options'] ?? null );
			$prices   = array();
			foreach ( array_slice( $options, 0, 4 ) as $o ) {
				$o        = self::arr( $o );
				$price    = self::f( $o['price'] ?? 0 );
				$prices[] = self::s( $o['label'] ?? '' ) . ' ' . ( empty( $o['active'] ) ? __( '(inactive)', 'flx-donate' ) : ( $price > 0 ? self::s( flxb_money( $price, $currency ) ) : __( '(price not set)', 'flx-donate' ) ) );
			}
			if ( count( $options ) > 4 ) {
				$prices[] = '…';
			}
			if ( ! empty( $t['allow_free'] ) ) {
				$min      = self::f( $t['min_amount'] ?? 0 );
				/* translators: %s: minimum */
				$prices[] = $min > 0 ? sprintf( __( 'free amount (min. %s)', 'flx-donate' ), self::s( flxb_money( $min, $currency ) ) ) : __( 'free amount', 'flx-donate' );
			}
			$flags = array();
			if ( ! empty( $t['allow_quantity'] ) ) {
				$flags[] = __( 'quantity', 'flx-donate' );
			}
			if ( ! empty( $t['allow_recurring'] ) ) {
				$flags[] = __( 'recurring', 'flx-donate' );
			}
			$goal   = self::f( $p['goal'] ?? 0 );
			$raised = self::f( $p['raised'] ?? 0 );
			$donors = self::i( $p['donors'] ?? 0 );
			$pct    = self::f( $p['percent'] ?? 0 );
			$start  = self::s( $t['start_date'] ?? '' );
			$end    = self::s( $t['end_date'] ?? '' );
			$forms  = array();
			foreach ( self::arr( $t['form_ids'] ?? null ) as $fid ) {
				$forms[] = self::s( flxb_form_title( self::i( $fid ) ) );
			}
			$status  = self::s( $t['status'] ?? '' );
			$items[] = array(
				'databaseId'   => $id,
				'title'        => self::s( $t['title'] ?? '' ),
				'stateLabel'   => $st[1],
				'tone'         => $st[0],
				'category'     => self::s( $cats[ self::s( $t['category'] ?? '' ) ] ?? __( 'No category', 'flx-donate' ) ),
				'shortcode'    => '[flx_bagis_kampanya id=' . $id . ']',
				'prices'       => implode( ' · ', $prices ),
				'flags'        => implode( ', ', $flags ),
				'hasGoal'      => $goal > 0,
				'percent'      => $pct,
				/* translators: 1: raised, 2: goal, 3: percent */
				'progress'     => $goal > 0 ? sprintf( __( '%1$s of %2$s (%3$s%%)', 'flx-donate' ), self::s( flxb_money( $raised, $currency ) ), self::s( flxb_money( $goal, $currency ) ), number_format_i18n( $pct, 0 ) ) : self::s( flxb_money( $raised, $currency ) ),
				/* translators: %s: number of donors */
				'donors'       => sprintf( _n( '%s donor', '%s donors', $donors, 'flx-donate' ), number_format_i18n( $donors ) ),
				'dates'        => '' !== $start || '' !== $end ? ( '' !== $start ? self::day( $start ) : '…' ) . ' – ' . ( '' !== $end ? self::day( $end ) : '…' ) : __( 'Open-ended', 'flx-donate' ),
				'forms'        => $forms ? implode( ', ', $forms ) : '',
				'status'       => in_array( $status, array( 'active', 'closed', 'archived' ), true ) ? $status : 'closed',
				'listUrl'      => self::s( FLXB_Admin_Url::page( 'flxb-donations', array( 'dtype' => $id ) ) ),
				'editUrl'      => $manage ? self::s( FLXB_Admin_Url::page( 'flxb-types', array( 'edit' => $id ) ) ) : '',
			);
		}
		$samples = array();
		if ( $manage && ! $archived ) {
			foreach ( self::arr( FLXB_Types::samples() ) as $k => $s ) {
				$samples[] = array(
					'value' => self::s( $k ),
					'label' => self::s( self::arr( $s )['label'] ?? $k ),
				);
			}
		}
		return array(
			'items'     => $items,
			'canManage' => $manage,
			'newUrl'    => $manage ? self::s( FLXB_Admin_Url::page( 'flxb-types', array( 'edit' => 'new' ) ) ) : '',
			'samples'   => $samples,
		);
	}

	private static function err( string $code, string $msg ): WP_Error {
		return new WP_Error( 'flxb_types', $msg, array( 'graphql_code' => $code ) );
	}

	/**
	 * Open, close or archive a type. Opening is refused when one of its forms already collects for
	 * another open type (FLXB_Types::$notices says which).
	 *
	 * @return array{message: string}|WP_Error
	 */
	public static function set_status( int $id, string $status ) {
		if ( ! FLXB_Types::get( $id ) ) {
			return self::err( 'INVALID_INPUT', __( 'Donation type not found.', 'flx-donate' ) );
		}
		if ( ! FLXB_Types::set_status( $id, $status ) ) {
			$why = implode( ' ', array_map( array( __CLASS__, 's' ), self::arr( FLXB_Types::$notices ) ) );
			return self::err( 'CONFLICT', '' !== $why ? $why : __( 'The action could not be completed.', 'flx-donate' ) );
		}
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}

	/**
	 * Move a type one place up (-1) or down (+1).
	 *
	 * @return array{message: string}|WP_Error
	 */
	public static function move( int $id, int $dir ) {
		if ( ! FLXB_Types::get( $id ) ) {
			return self::err( 'INVALID_INPUT', __( 'Donation type not found.', 'flx-donate' ) );
		}
		FLXB_Types::move( $id, $dir < 0 ? -1 : 1 );
		return array( 'message' => '' );
	}

	/**
	 * Load a sample set of types and categories.
	 *
	 * @return array{message: string}|WP_Error
	 */
	public static function samples( string $key ) {
		if ( ! array_key_exists( $key, self::arr( FLXB_Types::samples() ) ) ) {
			return self::err( 'INVALID_INPUT', __( 'The action could not be completed.', 'flx-donate' ) );
		}
		FLXB_Types::load_sample( $key );
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}
}
