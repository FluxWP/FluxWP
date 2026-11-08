<?php
/**
 * Flux Donate: partners (tracking module).
 *
 * A partner either brings gifts in (source: an office, an association abroad) or carries the work
 * out (service: e.g. the place where it is done). A service partner has priced agreements per
 * donation type and option (cost, currency, valid from, on/off); the price valid on the day an item
 * is sent to it is copied onto the item. Payments to and from partners are kept, and the
 * settlement shows per period what was sent, what it costs, and what was paid.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Partners {

	/* ---------- Partners ---------- */

	public static function all( $active_only = false ) {
		global $wpdb;
		static $cache = array();
		$k = $active_only ? 1 : 0;
		if ( ! isset( $cache[ $k ] ) ) {
			$t           = flxb_table( 'partners' );
			$cache[ $k ] = array();
			foreach ( (array) $wpdb->get_results( "SELECT * FROM {$t}" . ( $active_only ? ' WHERE active = 1' : '' ) . ' ORDER BY sort_order ASC, name ASC', ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cache[ $k ][ (int) $r['id'] ] = $r;
			}
		}
		return $cache[ $k ];
	}

	public static function flush() {
		wp_cache_delete( 'flxb_partners', 'flxb' );
	}

	public static function get( $id ) {
		global $wpdb;
		if ( ! (int) $id ) {
			return null;
		}
		$t = flxb_table( 'partners' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $r ? $r : null;
	}

	public static function name( $id ) {
		$p = self::get( (int) $id );
		return $p ? (string) $p['name'] : '';
	}

	public static function by_key( $key ) {
		global $wpdb;
		$key = sanitize_key( (string) $key );
		if ( '' === $key ) {
			return null;
		}
		$t = flxb_table( 'partners' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE pkey = %s", $key ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $r ? $r : null;
	}

	/** Type ids a partner serves (empty = all). */
	public static function type_ids( array $p ) {
		return array_values( array_filter( array_map( 'intval', explode( ',', (string) $p['type_ids'] ) ) ) );
	}

	public static function serves( array $p, $type_id ) {
		$ids = self::type_ids( $p );
		return ! $ids || ! $type_id || in_array( (int) $type_id, $ids, true );
	}

	/**
	 * Active service partners for a type (id => name). A partner that has agreements for the type but
	 * none of them switched on is left out: no valid price, no new work.
	 */
	public static function service_choices( $type_id = 0 ) {
		$out = array();
		foreach ( self::all( true ) as $id => $p ) {
			if ( ! (int) $p['is_service'] || ! self::serves( $p, $type_id ) ) {
				continue;
			}
			$st = self::price_state( $id, (int) $type_id );
			if ( 'off' === $st ) {
				continue;
			}
			$out[ $id ] = (string) $p['name'];
		}
		return $out;
	}

	/** Active source partners (id => name). */
	public static function source_choices() {
		$out = array();
		foreach ( self::all( true ) as $id => $p ) {
			if ( (int) $p['is_source'] ) {
				$out[ $id ] = (string) $p['name'];
			}
		}
		return $out;
	}

	/** Find by id, key or name (accent- and case-insensitive). */
	public static function find( $v, $kind = '' ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			return null;
		}
		if ( ctype_digit( $v ) && ( $p = self::get( (int) $v ) ) ) {
			return $p;
		}
		$n = flxb_fold( $v );
		foreach ( self::all() as $p ) {
			if ( ( 'source' === $kind && ! (int) $p['is_source'] ) || ( 'service' === $kind && ! (int) $p['is_service'] ) ) {
				continue;
			}
			if ( flxb_fold( $p['name'] ) === $n || ( '' !== (string) $p['pkey'] && $p['pkey'] === sanitize_key( $v ) ) ) {
				return $p;
			}
		}
		return null;
	}

	/**
	 * Create or update a partner.
	 *
	 * @param array $d  name, pkey, group_name, is_source, is_service, type_ids (int[]), contact_name,
	 *                  phone, email, currency, active, sort_order, note.
	 * @return int|WP_Error
	 */
	public static function save( array $d, $id = 0 ) {
		global $wpdb;
		$name = mb_substr( sanitize_text_field( (string) ( $d['name'] ?? '' ) ), 0, 191 );
		if ( '' === $name ) {
			return new WP_Error( 'flxb_partner', __( '\\nEnter a name.\\n', 'flx-donate' ) );
		}
		$cur = strtoupper( sanitize_key( (string) ( $d['currency'] ?? flxb_default_currency() ) ) );
		$row = array(
			'name'         => $name,
			'pkey'         => sanitize_key( (string) ( $d['pkey'] ?? '' ) ),
			'group_name'   => mb_substr( sanitize_text_field( (string) ( $d['group_name'] ?? '' ) ), 0, 191 ),
			'is_source'    => empty( $d['is_source'] ) ? 0 : 1,
			'is_service'   => empty( $d['is_service'] ) ? 0 : 1,
			'type_ids'     => implode( ',', array_filter( array_map( 'absint', (array) ( $d['type_ids'] ?? array() ) ) ) ),
			'contact_name' => mb_substr( sanitize_text_field( (string) ( $d['contact_name'] ?? '' ) ), 0, 191 ),
			'phone'        => mb_substr( preg_replace( '/[^\d+]/', '', (string) ( $d['phone'] ?? '' ) ), 0, 32 ),
			'email'        => mb_substr( sanitize_email( (string) ( $d['email'] ?? '' ) ), 0, 191 ),
			'currency'     => preg_match( '/^[A-Z]{3}$/', $cur ) ? $cur : flxb_default_currency(),
			'active'       => array_key_exists( 'active', $d ) && empty( $d['active'] ) ? 0 : 1,
			'sort_order'   => (int) ( $d['sort_order'] ?? 0 ),
			'note'         => sanitize_textarea_field( (string) ( $d['note'] ?? '' ) ),
			'updated_at'   => flxb_now(),
		);
		$t = flxb_table( 'partners' );
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $id ) );
		} else {
			$row['created_at'] = flxb_now();
			$wpdb->insert( $t, $row );
			$id = (int) $wpdb->insert_id;
		}
		do_action( 'flxb_partner_saved', (int) $id, $row );
		return (int) $id;
	}

	/* ---------- Agreements (prices) ---------- */

	public static function prices( $partner_id = 0 ) {
		global $wpdb;
		$t = flxb_table( 'partner_prices' );
		$w = $partner_id ? $wpdb->prepare( 'WHERE partner_id = %d', (int) $partner_id ) : '';
		return (array) $wpdb->get_results( "SELECT * FROM {$t} {$w} ORDER BY partner_id, type_id, option_key, valid_from DESC, id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function save_price( array $d, $id = 0 ) {
		global $wpdb;
		$cur = strtoupper( sanitize_key( (string) ( $d['currency'] ?? '' ) ) );
		$vf  = flxb_parse_date( (string) ( $d['valid_from'] ?? '' ) );
		$row = array(
			'partner_id' => absint( $d['partner_id'] ?? 0 ),
			'type_id'    => absint( $d['type_id'] ?? 0 ),
			'option_key' => sanitize_key( (string) ( $d['option_key'] ?? '' ) ),
			'price'      => round( (float) str_replace( ',', '.', (string) ( $d['price'] ?? 0 ) ), 4 ),
			'currency'   => preg_match( '/^[A-Z]{3}$/', $cur ) ? $cur : flxb_default_currency(),
			'valid_from' => $vf ? $vf : wp_date( 'Y-m-d' ),
			'active'     => array_key_exists( 'active', $d ) && empty( $d['active'] ) ? 0 : 1,
			'note'       => mb_substr( sanitize_text_field( (string) ( $d['note'] ?? '' ) ), 0, 191 ),
		);
		if ( ! $row['partner_id'] ) {
			return new WP_Error( 'flxb_price', __( '\\nPartner not found.\\n', 'flx-donate' ) );
		}
		$t = flxb_table( 'partner_prices' );
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$row['created_at'] = flxb_now();
		$wpdb->insert( $t, $row );
		return (int) $wpdb->insert_id;
	}

	public static function delete_price( $id ) {
		global $wpdb;
		$wpdb->delete( flxb_table( 'partner_prices' ), array( 'id' => (int) $id ) );
	}

	/**
	 * Price valid on a day for a partner, type and option: the newest active agreement that started
	 * on or before the day; an agreement without an option covers every option of the type, one
	 * without a type every type. null when there is none.
	 *
	 * @return array|null { price, currency, id }
	 */
	public static function cost( $partner_id, $type_id, $option_key = '', $date = '' ) {
		$date = $date ? $date : wp_date( 'Y-m-d' );
		$best = null;
		$rank = -1;
		foreach ( self::prices( $partner_id ) as $a ) {
			if ( ! (int) $a['active'] || $a['valid_from'] > $date ) {
				continue;
			}
			if ( (int) $a['type_id'] && (int) $a['type_id'] !== (int) $type_id ) {
				continue;
			}
			if ( '' !== (string) $a['option_key'] && (string) $a['option_key'] !== (string) $option_key ) {
				continue;
			}
			$r = ( (int) $a['type_id'] ? 2 : 0 ) + ( '' !== (string) $a['option_key'] ? 1 : 0 );
			if ( $r > $rank || ( $r === $rank && $a['valid_from'] > $best['valid_from'] ) ) {
				$best = $a;
				$rank = $r;
			}
		}
		return $best ? array( 'price' => (float) $best['price'], 'currency' => (string) $best['currency'], 'id' => (int) $best['id'] ) : null;
	}

	/** 'none' (no agreement for the type), 'on' (at least one active), 'off' (only switched-off ones). */
	public static function price_state( $partner_id, $type_id = 0 ) {
		$any = false;
		foreach ( self::prices( $partner_id ) as $a ) {
			if ( $type_id && (int) $a['type_id'] && (int) $a['type_id'] !== (int) $type_id ) {
				continue;
			}
			$any = true;
			if ( (int) $a['active'] ) {
				return 'on';
			}
		}
		return $any ? 'off' : 'none';
	}

	/* ---------- Payments ---------- */

	public static function payments( $partner_id = 0, $from = '', $to = '' ) {
		global $wpdb;
		$t = flxb_table( 'partner_payments' );
		$w = array( '1=1' );
		if ( $partner_id ) {
			$w[] = $wpdb->prepare( 'partner_id = %d', (int) $partner_id );
		}
		if ( $from ) {
			$w[] = $wpdb->prepare( 'paid_at >= %s', $from );
		}
		if ( $to ) {
			$w[] = $wpdb->prepare( 'paid_at <= %s', $to );
		}
		return (array) $wpdb->get_results( "SELECT * FROM {$t} WHERE " . implode( ' AND ', $w ) . ' ORDER BY paid_at DESC, id DESC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** direction: out = we paid the partner, in = the partner paid us. */
	public static function add_payment( array $d ) {
		global $wpdb;
		$cur = strtoupper( sanitize_key( (string) ( $d['currency'] ?? '' ) ) );
		$amt = round( (float) str_replace( ',', '.', (string) ( $d['amount'] ?? 0 ) ), 4 );
		$pid = absint( $d['partner_id'] ?? 0 );
		if ( ! $pid || $amt <= 0 ) {
			return new WP_Error( 'flxb_pay', __( 'Enter an amount.', 'flx-donate' ) );
		}
		$day = flxb_parse_date( (string) ( $d['paid_at'] ?? '' ) );
		$wpdb->insert(
			flxb_table( 'partner_payments' ),
			array(
				'partner_id' => $pid,
				'direction'  => 'in' === ( $d['direction'] ?? '' ) ? 'in' : 'out',
				'amount'     => $amt,
				'currency'   => preg_match( '/^[A-Z]{3}$/', $cur ) ? $cur : flxb_default_currency(),
				'paid_at'    => $day ? $day : wp_date( 'Y-m-d' ),
				'note'       => mb_substr( sanitize_text_field( (string) ( $d['note'] ?? '' ) ), 0, 191 ),
				'user_id'    => get_current_user_id(),
				'created_at' => flxb_now(),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function delete_payment( $id ) {
		global $wpdb;
		$wpdb->delete( flxb_table( 'partner_payments' ), array( 'id' => (int) $id ) );
	}

	/* ---------- Settlement ---------- */

	/** Period → [from, to] (Y-m-d). 'YYYY-MM' or 'YYYY-MM-DD..YYYY-MM-DD'; anything else = this month. */
	public static function period( $p ) {
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})\.\.(\d{4}-\d{2}-\d{2})$/', (string) $p, $m ) ) {
			return $m[1] <= $m[2] ? array( $m[1], $m[2] ) : array( $m[2], $m[1] );
		}
		$p = preg_match( '/^\d{4}-\d{2}$/', (string) $p ) ? $p : wp_date( 'Y-m' );
		return array( $p . '-01', gmdate( 'Y-m-t', strtotime( $p . '-01' ) ) );
	}

	/**
	 * Settlement of a period.
	 * service: items sent to each service partner in the period (by sent date), at the agreed cost.
	 * source: items that came in through each source partner (by record date), at the gift price.
	 * Payments of the period and the balance up to the period's end are added.
	 *
	 * @return array partner_id => { name, n, sum [cur => amount], uncosted, paid [cur], received [cur], rows? }
	 */
	public static function settlement( $kind, $period, $partner_id = 0, $with_rows = false ) {
		global $wpdb;
		list( $from, $to ) = self::period( $period );
		$service = 'service' === $kind;
		$col     = $service ? 'i.partner_id' : 'i.source_id';
		$df      = $service ? 'i.sent_at' : 'i.created_at';
		$w       = array( "i.status <> 'canceled'", "{$col} > 0", $wpdb->prepare( "{$df} >= %s AND {$df} <= %s", $from . ' 00:00:00', $to . ' 23:59:59' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $partner_id ) {
			$w[] = $wpdb->prepare( "{$col} = %d", (int) $partner_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$sel  = $service ? 'i.cost AS price, i.cost_currency AS cur' : 'i.amount AS price, i.currency AS cur';
		$rows = (array) $wpdb->get_results( "SELECT i.id, i.code, i.type_id, i.option_label, i.beneficiary_name, i.status, i.place_name, i.partner_id, i.source_id, i.created_at, i.sent_at, i.done_at, i.target_date, {$col} AS pid, {$df} AS day, {$sel} FROM " . FLXB_Track::from() . ' WHERE ' . implode( ' AND ', $w ) . " ORDER BY {$df} ASC, i.id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out  = array();
		foreach ( $rows as $r ) {
			$pid = (int) $r['pid'];
			if ( ! isset( $out[ $pid ] ) ) {
				$out[ $pid ] = array( 'name' => self::name( $pid ), 'n' => 0, 'sum' => array(), 'uncosted' => 0, 'paid' => array(), 'received' => array(), 'rows' => array() );
			}
			++$out[ $pid ]['n'];
			if ( '' !== (string) $r['cur'] && ( ! $service || (float) $r['price'] > 0 ) ) {
				$out[ $pid ]['sum'][ $r['cur'] ] = ( $out[ $pid ]['sum'][ $r['cur'] ] ?? 0 ) + (float) $r['price'];
			} else {
				++$out[ $pid ]['uncosted'];
			}
			if ( $with_rows ) {
				$out[ $pid ]['rows'][] = $r;
			}
		}
		foreach ( self::payments( $partner_id, $from, $to ) as $p ) {
			$pid = (int) $p['partner_id'];
			if ( ! isset( $out[ $pid ] ) ) {
				$pp = self::get( $pid );
				if ( ! $pp || ( $service ? ! (int) $pp['is_service'] : ! (int) $pp['is_source'] ) ) {
					continue;
				}
				$out[ $pid ] = array( 'name' => (string) $pp['name'], 'n' => 0, 'sum' => array(), 'uncosted' => 0, 'paid' => array(), 'received' => array(), 'rows' => array() );
			}
			$k                                 = 'in' === $p['direction'] ? 'received' : 'paid';
			$out[ $pid ][ $k ][ $p['currency'] ] = ( $out[ $pid ][ $k ][ $p['currency'] ] ?? 0 ) + (float) $p['amount'];
		}
		uasort(
			$out,
			function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);
		return $out;
	}

	/** Item counts and sums of the last N months (oldest first), for the month cards. */
	public static function month_stats( $kind, $months = 12, $partner_id = 0 ) {
		global $wpdb;
		$service = 'service' === $kind;
		$col     = $service ? 'i.partner_id' : 'i.source_id';
		$df      = $service ? 'i.sent_at' : 'i.created_at';
		$pr      = $service ? 'i.cost' : 'i.amount';
		$cc      = $service ? 'i.cost_currency' : 'i.currency';
		$first   = wp_date( 'Y-m-01', strtotime( '-' . ( $months - 1 ) . ' months', strtotime( wp_date( 'Y-m-15' ) ) ) );
		$w       = "i.status <> 'canceled' AND {$col} > 0 AND {$df} >= %s";
		$a       = array( $first . ' 00:00:00' );
		if ( $partner_id ) {
			$w  .= " AND {$col} = %d";
			$a[] = (int) $partner_id;
		}
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT SUBSTR({$df}, 1, 7) m, {$cc} c, COUNT(*) n, SUM({$pr}) s FROM " . flxb_table( 'items' ) . " i WHERE {$w} GROUP BY SUBSTR({$df}, 1, 7), {$cc}", $a ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$out  = array();
		for ( $i = $months - 1; $i >= 0; $i-- ) {
			$out[ wp_date( 'Y-m', strtotime( "-$i months", strtotime( wp_date( 'Y-m-15' ) ) ) ) ] = array( 'n' => 0, 'sum' => array() );
		}
		foreach ( $rows as $r ) {
			if ( ! isset( $out[ $r['m'] ] ) ) {
				continue;
			}
			$out[ $r['m'] ]['n'] += (int) $r['n'];
			if ( '' !== (string) $r['c'] ) {
				$out[ $r['m'] ]['sum'][ $r['c'] ] = ( $out[ $r['m'] ]['sum'][ $r['c'] ] ?? 0 ) + (float) $r['s'];
			}
		}
		return $out;
	}

	/** Re-read agreement prices for items already sent (after an agreement was entered late). */
	public static function reprice( array $item_ids ) {
		$n = 0;
		foreach ( $item_ids as $id ) {
			$it = FLXB_Track::get( (int) $id );
			if ( ! $it || ! (int) $it['partner_id'] || ! $it['sent_at'] ) {
				continue;
			}
			$c = self::cost( (int) $it['partner_id'], (int) $it['type_id'], (string) $it['option_key'], substr( (string) $it['sent_at'], 0, 10 ) );
			if ( $c ) {
				FLXB_Track::upd( (int) $id, array( 'cost' => $c['price'], 'cost_currency' => $c['currency'] ) );
				++$n;
			}
		}
		return $n;
	}
}

/** Lower-case ASCII form of a Turkish name for matching (Çağlayan → caglayan). */
function flxb_fold( $s ) {
	$s = mb_strtolower( trim( (string) $s ), 'UTF-8' );
	$s = strtr( $s, array( 'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'i̇' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'â' => 'a', 'î' => 'i', 'û' => 'u' ) );
	return trim( preg_replace( '/[^a-z0-9]+/', ' ', remove_accents( $s ) ) );
}
