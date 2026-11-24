<?php
/**
 * Partners (tracking module) for the admin screen (GraphQL part b6): the list, the settlement of a period and
 * the partner editor with its agreements and payments.
 *
 * The wp-admin screen (packages/modules/src/donate/Partners.tsx) calls this class. Rights are the classic ones:
 * reading needs flx_bagis_view; every write needs flxb_can_manage(). The settlement CSV stays a download
 * (admin-post flxb_partner_settle_csv).
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_Admin_Partners_Service {

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
	private static function f( $v ): float {
		return is_numeric( $v ) ? (float) $v : 0.0;
	}

	public static function can_view(): bool {
		return (bool) flxb_can_view();
	}

	public static function can_manage(): bool {
		return (bool) flxb_can_manage();
	}

	/** What a partner that carries the work out is called on this site. */
	private static function label(): string {
		return self::s( FLXB_Track::label( 'partner' ) );
	}

	/**
	 * Amounts per currency as "100 ₺ + 20 $"; "—" without any.
	 *
	 * @param mixed $by_cur Currency => amount.
	 */
	private static function money_list( $by_cur ): string {
		$out = array();
		foreach ( self::arr( $by_cur ) as $c => $v ) {
			$out[] = self::s( flxb_money( self::f( $v ), (string) $c ) );
		}
		return $out ? implode( ' + ', $out ) : '—';
	}

	/**
	 * @return list<string>
	 */
	private static function currencies(): array {
		$c = array( self::s( flxb_default_currency() ), 'TRY', 'USD', 'EUR', 'GBP' );
		return array_values( array_unique( array_map( static fn( $x ): string => self::s( $x ), self::arr( apply_filters( 'flxb_partner_currencies', $c ) ) ) ) );
	}

	private static function day( string $ymd ): string {
		$ts = strtotime( $ymd );
		return false !== $ts ? self::s( flx_date( $ts, 'j M Y' ) ) : $ymd;
	}

	/**
	 * Type id => title.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function types(): array {
		$out = array();
		foreach ( self::arr( FLXB_Types::all() ) as $t ) {
			$t                         = self::map( $t );
			$out[ self::i( $t['id'] ?? 0 ) ] = $t;
		}
		return $out;
	}

	/**
	 * The partners list.
	 *
	 * @return array<string,mixed>
	 */
	public static function list(): array {
		$types = self::types();
		$label = self::label();
		$state = array(
			'none' => '—',
			'on'   => __( 'Active', 'flx-donate' ),
			'off'  => __( 'All off', 'flx-donate' ),
		);
		$items = array();
		foreach ( self::arr( FLXB_Partners::all() ) as $id => $p ) {
			$p     = self::map( $p );
			$roles = array();
			if ( self::i( $p['is_service'] ?? 0 ) ) {
				$roles[] = $label;
			}
			if ( self::i( $p['is_source'] ?? 0 ) ) {
				$roles[] = __( 'Brings gifts in', 'flx-donate' );
			}
			$tn = array();
			foreach ( self::arr( FLXB_Partners::type_ids( $p ) ) as $tid ) {
				$tid  = self::i( $tid );
				$tn[] = isset( $types[ $tid ] ) ? self::s( $types[ $tid ]['title'] ?? '' ) : '#' . $tid;
			}
			$phone   = self::s( $p['phone'] ?? '' );
			$items[] = array(
				'databaseId' => (int) $id,
				'name'       => self::s( $p['name'] ?? '' ),
				'group'      => self::s( $p['group_name'] ?? '' ),
				'roles'      => implode( ', ', $roles ),
				'types'      => $tn ? implode( ', ', $tn ) : __( 'All', 'flx-donate' ),
				'agreement'  => $state[ self::s( FLXB_Partners::price_state( (int) $id ) ) ] ?? '—',
				'contact'    => trim( self::s( $p['contact_name'] ?? '' ) . ' ' . ( '' !== $phone ? self::s( flxb_format_phone( $phone ) ) : '' ) ),
				'active'     => (bool) self::i( $p['active'] ?? 0 ),
			);
		}
		return array(
			'canManage'    => self::can_manage(),
			'partnerLabel' => $label,
			'items'        => $items,
		);
	}

	/**
	 * A period from its parts: from+to → range, a month → the month, else this month.
	 */
	public static function period( string $period ): string {
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}\.\.\d{4}-\d{2}-\d{2}$/', $period ) || preg_match( '/^\d{4}-\d{2}$/', $period ) ) {
			return $period;
		}
		return self::s( wp_date( 'Y-m' ) );
	}

	/**
	 * The settlement of a period: per partner what was sent to it (service: at the agreed cost) or came in
	 * through it (source: at the gift price), what was paid and what is open; the items of one partner.
	 *
	 * @return array<string,mixed>
	 */
	public static function settlement( string $kind, string $period, int $pid ): array {
		$kind    = 'source' === $kind ? 'source' : 'service';
		$service = 'service' === $kind;
		$period  = self::period( $period );
		$range   = self::arr( FLXB_Partners::period( $period ) );
		$from    = self::s( $range[0] ?? '' );
		$to      = self::s( $range[1] ?? '' );
		$months  = array();
		foreach ( array_reverse( self::arr( FLXB_Partners::month_stats( $kind, 12, $pid ) ), true ) as $m => $st ) {
			$st       = self::map( $st );
			$ts       = strtotime( $m . '-15' );
			$months[] = array(
				'key'   => (string) $m,
				'label' => false !== $ts ? self::s( flx_date( $ts, 'M Y' ) ) : (string) $m,
				'count' => self::s( number_format_i18n( self::i( $st['n'] ?? 0 ) ) ),
				'sum'   => self::money_list( $st['sum'] ?? null ),
			);
		}
		$choices = array();
		foreach ( self::arr( FLXB_Partners::all() ) as $id => $p ) {
			$p = self::map( $p );
			if ( self::i( $service ? ( $p['is_service'] ?? 0 ) : ( $p['is_source'] ?? 0 ) ) ) {
				$choices[] = array(
					'value' => (string) $id,
					'label' => self::s( $p['name'] ?? '' ),
				);
			}
		}
		$res    = self::arr( FLXB_Partners::settlement( $kind, $period, $pid, (bool) $pid ) );
		$rows   = array();
		$detail = null;
		foreach ( $res as $id => $r ) {
			$r     = self::map( $r );
			$moved = self::arr( $service ? ( $r['paid'] ?? null ) : ( $r['received'] ?? null ) );
			$open  = self::arr( $r['sum'] ?? null );
			foreach ( $moved as $c => $v ) {
				$open[ $c ] = self::f( $open[ $c ] ?? 0 ) - self::f( $v );
			}
			$open     = array_filter( $open, static fn( $v ): bool => abs( self::f( $v ) ) > 0.004 );
			$uncosted = self::i( $r['uncosted'] ?? 0 );
			$rows[]   = array(
				'partnerId' => (int) $id,
				'name'      => self::s( $r['name'] ?? '' ),
				/* translators: %d: items without a price */
				'uncosted'  => $uncosted ? sprintf( _n( '%d without a price', '%d without a price', $uncosted, 'flx-donate' ), $uncosted ) : '',
				'count'     => self::s( number_format_i18n( self::i( $r['n'] ?? 0 ) ) ),
				'sum'       => self::money_list( $r['sum'] ?? null ),
				'moved'     => self::money_list( $moved ),
				'open'      => self::money_list( $open ),
			);
			if ( $pid && (int) $id === $pid && self::arr( $r['rows'] ?? null ) ) {
				$items = array();
				foreach ( self::arr( $r['rows'] ) as $x ) {
					$x       = self::map( $x );
					$tid     = self::i( $x['type_id'] ?? 0 );
					$st      = self::s( $x['status'] ?? '' );
					$all     = self::arr( FLXB_Track::statuses( $tid ) );
					$tone    = array(
						'pending'   => 'pending',
						'approved'  => 'recurring',
						'sent'      => 'recurring',
						'done'      => 'ok',
						'delivered' => 'ok',
						'canceled'  => 'failed',
					);
					$cur     = self::s( $x['cur'] ?? '' );
					$price   = self::f( $x['price'] ?? 0 );
					$items[] = array(
						'date'        => self::s( flxb_date( self::s( $x['day'] ?? '' ) ) ),
						'code'        => self::s( $x['code'] ?? '' ),
						'trackUrl'    => self::s( FLXB_Page_Track::url( self::i( $x['id'] ?? 0 ) ) ),
						'beneficiary' => self::s( $x['beneficiary_name'] ?? '' ),
						'option'      => self::s( $x['option_label'] ?? '' ),
						'status'      => self::s( $all[ $st ] ?? $st ),
						'tone'        => $tone[ $st ] ?? 'muted',
						'price'       => '' !== $cur && $price > 0 ? self::s( flxb_money( $price, $cur ) ) : '—',
					);
				}
				$detail = array(
					'name'       => self::s( $r['name'] ?? '' ),
					'canReprice' => $service && $uncosted > 0 && self::can_manage(),
					'items'      => $items,
				);
			}
		}
		return array(
			'kind'         => strtoupper( $kind ),
			'period'       => $period,
			'from'         => $from,
			'to'           => $to,
			'partnerLabel' => self::label(),
			'months'       => $months,
			'partners'     => $choices,
			'rows'         => $rows,
			'detail'       => $detail,
			'csvUrl'       => self::s( wp_nonce_url( admin_url( 'admin-post.php?action=flxb_partner_settle_csv&kind=' . $kind . '&period=' . rawurlencode( $period ) . '&partner=' . $pid ), 'flxb_partner_settle_csv' ) ),
		);
	}

	/**
	 * The partner editor; id 0 = a new one. Null when the partner does not exist.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function form( int $id ): ?array {
		$p = $id ? FLXB_Partners::get( $id ) : null;
		if ( $id && ! is_array( $p ) ) {
			return null;
		}
		$p     = is_array( $p ) ? self::map( $p ) : array(
			'name'      => '',
			'currency'  => flxb_default_currency(),
			'active'    => 1,
			'is_service' => 1,
		);
		$types = self::types();
		$tlist = array();
		$topts = array(
			array(
				'value' => '0|',
				'label' => __( 'All types', 'flx-donate' ),
			),
		);
		foreach ( $types as $tid => $t ) {
			$title   = self::s( $t['title'] ?? '' );
			$tlist[] = array(
				'value' => (string) $tid,
				'label' => $title,
			);
			$topts[] = array(
				'value' => $tid . '|',
				'label' => $title . ' — ' . __( 'all options', 'flx-donate' ),
			);
			foreach ( self::arr( $t['options'] ?? null ) as $o ) {
				$o       = self::map( $o );
				$topts[] = array(
					'value' => $tid . '|' . self::s( $o['key'] ?? '' ),
					'label' => $title . ' — ' . self::s( $o['label'] ?? '' ),
				);
			}
		}
		$pays = array();
		$agrs = array();
		if ( $id ) {
			foreach ( self::arr( FLXB_Partners::payments( $id ) ) as $py ) {
				$py     = self::map( $py );
				$pays[] = array(
					'databaseId' => self::i( $py['id'] ?? 0 ),
					'date'       => self::day( self::s( $py['paid_at'] ?? '' ) ),
					'direction'  => 'in' === ( $py['direction'] ?? '' ) ? __( 'Received', 'flx-donate' ) : __( 'Paid', 'flx-donate' ),
					'amount'     => self::s( flxb_money( self::f( $py['amount'] ?? 0 ), self::s( $py['currency'] ?? '' ) ) ),
					'note'       => self::s( $py['note'] ?? '' ),
				);
			}
			foreach ( self::arr( FLXB_Partners::prices( $id ) ) as $a ) {
				$a      = self::map( $a );
				$t      = $types[ self::i( $a['type_id'] ?? 0 ) ] ?? null;
				$key    = self::s( $a['option_key'] ?? '' );
				$opt    = $t && '' !== $key ? FLXB_Types::option( $t, $key ) : null;
				$agrs[] = array(
					'databaseId' => self::i( $a['id'] ?? 0 ),
					'type'       => $t ? self::s( $t['title'] ?? '' ) : __( 'All', 'flx-donate' ),
					'option'     => is_array( $opt ) ? self::s( $opt['label'] ?? '' ) : ( '' !== $key ? $key : __( 'All', 'flx-donate' ) ),
					'price'      => self::s( flxb_money( self::f( $a['price'] ?? 0 ), self::s( $a['currency'] ?? '' ) ) ),
					'validFrom'  => self::day( self::s( $a['valid_from'] ?? '' ) ),
					'active'     => (bool) self::i( $a['active'] ?? 0 ),
					'note'       => self::s( $a['note'] ?? '' ),
				);
			}
		}
		return array(
			'databaseId'   => $id,
			'name'         => self::s( $p['name'] ?? '' ),
			'pkey'         => self::s( $p['pkey'] ?? '' ),
			'group'        => self::s( $p['group_name'] ?? '' ),
			'isService'    => (bool) self::i( $p['is_service'] ?? 0 ),
			'isSource'     => (bool) self::i( $p['is_source'] ?? 0 ),
			'typeIds'      => array_map( static fn( $x ): int => self::i( $x ), self::arr( FLXB_Partners::type_ids( $p ) ) ),
			'contactName'  => self::s( $p['contact_name'] ?? '' ),
			'phone'        => self::s( $p['phone'] ?? '' ),
			'email'        => self::s( $p['email'] ?? '' ),
			'currency'     => self::s( $p['currency'] ?? '' ),
			'active'       => (bool) self::i( $p['active'] ?? 0 ),
			'note'         => self::s( $p['note'] ?? '' ),
			'canManage'    => self::can_manage(),
			'partnerLabel' => self::label(),
			'currencies'   => self::currencies(),
			'types'        => $tlist,
			'typeOptions'  => $topts,
			'today'        => self::s( wp_date( 'Y-m-d' ) ),
			'payments'     => $pays,
			'agreements'   => $agrs,
		);
	}

	private static function invalid( string $msg ): WP_Error {
		return new WP_Error( 'flxb_partner', $msg, array( 'graphql_code' => 'INVALID_INPUT' ) );
	}

	/**
	 * Creates or saves a partner.
	 *
	 * @param array<string,mixed> $d Classic fields (FLXB_Partners::save()).
	 * @return array{message: string, id: int}|WP_Error
	 */
	public static function save( int $id, array $d ) {
		if ( $id && ! FLXB_Partners::get( $id ) ) {
			return self::invalid( __( 'Partner not found.', 'flx-donate' ) );
		}
		$r = FLXB_Partners::save( $d, $id );
		if ( is_wp_error( $r ) ) {
			return self::invalid( $r->get_error_message() );
		}
		return array(
			'message' => __( 'Saved.', 'flx-donate' ),
			'id'      => self::i( $r ),
		);
	}

	/**
	 * @param array<string,mixed> $d partner_id, type_opt ("type|option"), price, currency, valid_from, note.
	 * @return array{message: string}|WP_Error
	 */
	public static function price_add( array $d ) {
		$pid = self::i( $d['partner_id'] ?? 0 );
		if ( ! FLXB_Partners::get( $pid ) ) {
			return self::invalid( __( 'Partner not found.', 'flx-donate' ) );
		}
		if ( self::f( str_replace( ',', '.', self::s( $d['price'] ?? '' ) ) ) <= 0 ) {
			return self::invalid( __( 'Enter an amount.', 'flx-donate' ) );
		}
		list( $tid, $opt ) = array_pad( explode( '|', self::s( $d['type_opt'] ?? '' ), 2 ), 2, '' );
		$r                 = FLXB_Partners::save_price(
			array(
				'partner_id' => $pid,
				'type_id'    => $tid,
				'option_key' => $opt,
				'price'      => self::s( $d['price'] ?? '' ),
				'currency'   => self::s( $d['currency'] ?? '' ),
				'valid_from' => self::s( $d['valid_from'] ?? '' ),
				'note'       => self::s( $d['note'] ?? '' ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return self::invalid( $r->get_error_message() );
		}
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}

	/**
	 * Switches an agreement on or off.
	 *
	 * @return array{message: string}|WP_Error
	 */
	public static function price_toggle( int $id ) {
		/** @var wpdb $wpdb */
		global $wpdb;
		$t = self::s( flxb_table( 'partner_prices' ) );
		$a = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $t, $id ), ARRAY_A );
		if ( ! is_array( $a ) ) {
			return self::invalid( __( 'The action could not be completed.', 'flx-donate' ) );
		}
		$wpdb->update( $t, array( 'active' => self::i( $a['active'] ?? 0 ) ? 0 : 1 ), array( 'id' => $id ) );
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}

	/**
	 * @return array{message: string}
	 */
	public static function price_delete( int $id ): array {
		FLXB_Partners::delete_price( $id );
		return array( 'message' => __( 'Deleted.', 'flx-donate' ) );
	}

	/**
	 * @param array<string,mixed> $d partner_id, direction (in / out), amount, currency, paid_at, note.
	 * @return array{message: string}|WP_Error
	 */
	public static function payment_add( array $d ) {
		if ( ! FLXB_Partners::get( self::i( $d['partner_id'] ?? 0 ) ) ) {
			return self::invalid( __( 'Partner not found.', 'flx-donate' ) );
		}
		$r = FLXB_Partners::add_payment( $d );
		if ( is_wp_error( $r ) ) {
			return self::invalid( $r->get_error_message() );
		}
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}

	/**
	 * @return array{message: string}
	 */
	public static function payment_delete( int $id ): array {
		FLXB_Partners::delete_payment( $id );
		return array( 'message' => __( 'Deleted.', 'flx-donate' ) );
	}

	/**
	 * Re-reads the agreement prices of a partner's items sent in a period.
	 *
	 * @return array{message: string}
	 */
	public static function reprice( int $pid, string $period ): array {
		$res = self::arr( FLXB_Partners::settlement( 'service', self::period( $period ), $pid, true ) );
		$ids = array();
		foreach ( self::arr( self::map( $res[ $pid ] ?? null )['rows'] ?? null ) as $r ) {
			$ids[] = self::i( self::map( $r )['id'] ?? 0 );
		}
		FLXB_Partners::reprice( $ids );
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}
}
