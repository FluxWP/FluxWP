<?php
/**
 * Flux Donate: item tracking (tracking module, off by default).
 *
 * Types that track their units one by one (type meta `track_items`, or `requires_proxy`) give every
 * unit its own item row with a 7-digit code. An item moves through
 * waiting → approved → sent to a partner → completed → delivered to the owner (or canceled).
 * It can be taken back from a partner and sent to another; files (videos, photos, documents) are
 * kept on the item and can be sent to the owner over WhatsApp (Flux Chat). Every step is
 * written to the item's history (flxb_item_log).
 *
 * Nothing here names a kind of gift: the words on screen come from labels() (type meta `labels`,
 * option `flxb_track_labels`, filter `flxb_track_label`), so a preset can call a partner
 * "Slaughter place" and "completed" "Slaughtered" for one type and "Planting area" / "Planted"
 * for another on the same site.
 *
 * Other plugins: `flxb_track_places` (extra partners, ref => name), `flxb_track_item_changed`
 * (item id, event, data), `flxb_track_can` (who may run the flow), `flxb_track_assign_block`.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Track {

	const CAP = 'flxb_track';

	/** Order of the forward steps; canceled is outside it. */
	const STEPS = array(
		'pending'   => 0,
		'approved'  => 1,
		'sent'      => 2,
		'done'      => 3,
		'delivered' => 4,
	);

	public static function init() {
		add_filter( 'flx_role_caps_supported', array( __CLASS__, 'supported' ) );
	}

	public static function supported( $caps ) {
		return array_values( array_unique( array_merge( (array) $caps, array( self::CAP, 'flxb_kurban' ) ) ) );
	}

	/** May run the flow (approve, send to a partner, upload and send files). */
	public static function can() {
		$ok = is_user_logged_in() && ( flxb_can_manage() || current_user_can( self::CAP ) || current_user_can( 'flxb_kurban' ) );
		$ok = (bool) apply_filters( 'flxb_kurban_can', $ok ); // before 2.11
		return (bool) apply_filters( 'flxb_track_can', $ok );
	}

	/** May see the items. */
	public static function can_view() {
		return flxb_can_view() || self::can();
	}

	/* ---------- Labels ---------- */

	/** Built-in words (generic). key => text. */
	public static function default_labels() {
		return array(
			'menu'         => __( 'Tracking', 'flx-donate' ),
			'title'        => __( 'Tracked items', 'flx-donate' ),
			'item'         => __( 'Record', 'flx-donate' ),
			'partner'      => __( 'Partner', 'flx-donate' ),
			'files'        => __( 'Files', 'flx-donate' ),
			'st_pending'   => __( 'Waiting', 'flx-donate' ),
			'st_approved'  => __( 'Approved', 'flx-donate' ),
			'st_sent'      => __( 'Sent to partner', 'flx-donate' ),
			'st_done'      => __( 'Completed', 'flx-donate' ),
			'st_delivered' => __( 'Delivered to owner', 'flx-donate' ),
			'st_canceled'  => __( 'Canceled', 'flx-donate' ),
			'do_assign'    => __( 'Send to a partner', 'flx-donate' ),
			'do_revoke'    => __( 'Take back from the partner', 'flx-donate' ),
			'do_done'      => __( 'Mark completed', 'flx-donate' ),
			'caption'      => __( 'Your gift on behalf of%1$sis completed. Thank you. Code:%2$s', 'flx-donate' ),
		);
	}

	/** Keys a type or preset may override, with a short description for the editor. */
	public static function label_fields() {
		return array(
			'menu'         => __( 'Menu name', 'flx-donate' ),
			'title'        => __( 'Page title', 'flx-donate' ),
			'item'         => __( 'One item', 'flx-donate' ),
			'partner'      => __( 'Partner', 'flx-donate' ),
			'files'        => __( 'Files', 'flx-donate' ),
			'st_sent'      => __( 'Status: sent to partner', 'flx-donate' ),
			'st_done'      => __( 'Status: completed', 'flx-donate' ),
			'do_assign'    => __( 'Button: send to partner', 'flx-donate' ),
			'do_revoke'    => __( 'Button: take back', 'flx-donate' ),
			'do_done'      => __( 'Button: mark completed', 'flx-donate' ),
			'caption'      => __( 'WhatsApp text with the file (%1$sname,%2$scode)', 'flx-donate' ),
		);
	}

	/** Labels of a type (0 = the site's own words, or the only tracked type's). */
	public static function labels( $type_id = 0 ) {
		static $cache = array();
		$type_id = (int) $type_id;
		if ( ! $type_id ) {
			$tracked = self::tracked_types();
			if ( 1 === count( $tracked ) ) {
				$type_id = (int) $tracked[0]['id'];
			}
		}
		if ( isset( $cache[ $type_id ] ) ) {
			return $cache[ $type_id ];
		}
		$l    = self::default_labels();
		$site = get_option( 'flxb_track_labels', array() );
		foreach ( is_array( $site ) ? $site : array() as $k => $v ) {
			if ( isset( $l[ $k ] ) && '' !== trim( (string) $v ) ) {
				$l[ $k ] = (string) $v;
			}
		}
		if ( $type_id && ( $t = FLXB_Types::get( $type_id ) ) ) {
			foreach ( (array) FLXB_Types::meta( $t, 'labels', array() ) as $k => $v ) {
				if ( isset( $l[ $k ] ) && '' !== trim( (string) $v ) ) {
					$l[ $k ] = (string) $v;
				}
			}
		}
		$cache[ $type_id ] = (array) apply_filters( 'flxb_track_labels', $l, $type_id );
		return $cache[ $type_id ];
	}

	public static function label( $key, $type_id = 0 ) {
		$l = self::labels( $type_id );
		return (string) apply_filters( 'flxb_track_label', $l[ $key ] ?? $key, $key, (int) $type_id );
	}

	/** Clean a labels array from a form or a preset. */
	public static function clean_labels( $raw ) {
		$out = array();
		foreach ( array_keys( self::default_labels() ) as $k ) {
			if ( isset( $raw[ $k ] ) && '' !== trim( (string) $raw[ $k ] ) ) {
				$out[ $k ] = mb_substr( sanitize_text_field( (string) $raw[ $k ] ), 0, 'caption' === $k ? 300 : 80 );
			}
		}
		return $out;
	}

	/** Types whose units are tracked one by one. */
	public static function tracked_types() {
		static $out = null;
		if ( null === $out ) {
			$out = array();
			foreach ( FLXB_Types::all() as $t ) {
				if ( FLXB_Types::tracks_items( $t ) ) {
					$out[] = $t;
				}
			}
		}
		return $out;
	}

	public static function statuses( $type_id = 0 ) {
		$l = self::labels( $type_id );
		return array(
			'pending'   => $l['st_pending'],
			'approved'  => $l['st_approved'],
			'sent'      => $l['st_sent'],
			'done'      => $l['st_done'],
			'delivered' => $l['st_delivered'],
			'canceled'  => $l['st_canceled'],
		);
	}

	public static function status_pill( $status, $type_id = 0 ) {
		$cls = array(
			'pending'   => 'pending',
			'approved'  => 'recurring',
			'sent'      => 'recurring',
			'done'      => 'ok',
			'delivered' => 'ok',
			'canceled'  => 'failed',
		);
		$all = self::statuses( $type_id );
		return '<span class="flxb-pill flxb-pill--' . esc_attr( $cls[ $status ] ?? 'muted' ) . '">' . esc_html( $all[ $status ] ?? $status ) . '</span>';
	}

	public static function events( $type_id = 0 ) {
		$l = self::labels( $type_id );
		return array(
			'created'   => __( 'Recorded', 'flx-donate' ),
			'approved'  => $l['st_approved'],
			'assigned'  => $l['st_sent'],
			'revoked'   => $l['do_revoke'],
			'done'      => $l['st_done'],
			'delivered' => $l['st_delivered'],
			'canceled'  => $l['st_canceled'],
			'reset'     => __( 'Back to waiting', 'flx-donate' ),
			'file'      => __( 'File added', 'flx-donate' ),
			'file_del'  => __( 'File removed', 'flx-donate' ),
			'file_sent' => __( 'File sent to owner', 'flx-donate' ),
			'edited'    => __( 'Details changed', 'flx-donate' ),
			'note'      => __( 'Note', 'flx-donate' ),
			'imported'  => __( 'Imported', 'flx-donate' ),
			/* before 2.11 */
			'video'      => __( 'File added', 'flx-donate' ),
			'video_del'  => __( 'File removed', 'flx-donate' ),
			'video_sent' => __( 'File sent to owner', 'flx-donate' ),
		);
	}

	/* ---------- Partners ---------- */

	/**
	 * Partners an item of this type can be sent to: ref => name. Own partners ('pt:ID', Donations ›
	 * Partners) that serve the type, plus whatever another plugin adds (`flxb_track_places`).
	 */
	public static function places( $type_id = 0 ) {
		$own = array();
		foreach ( FLXB_Partners::service_choices( (int) $type_id ) as $id => $name ) {
			$own[ 'pt:' . $id ] = $name;
		}
		$own = (array) apply_filters( 'flxb_kurban_places', $own, (int) $type_id ); // before 2.11
		return (array) apply_filters( 'flxb_track_places', $own, (int) $type_id );
	}

	public static function place_name( $ref ) {
		$ref = (string) $ref;
		if ( 0 === strpos( $ref, 'pt:' ) ) {
			$p = FLXB_Partners::get( (int) substr( $ref, 3 ) );
			if ( $p ) {
				return (string) $p['name'];
			}
		}
		$p = self::places();
		return $p[ $ref ] ?? '';
	}

	/* ---------- Records ---------- */

	public static function get( $id ) {
		global $wpdb;
		$t = flxb_table( 'items' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $r ? $r : null;
	}

	public static function by_code( $code ) {
		global $wpdb;
		$code = preg_replace( '/\D/', '', (string) $code );
		if ( 7 !== strlen( $code ) ) {
			return null;
		}
		$t = flxb_table( 'items' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE code = %s", $code ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $r ? $r : null;
	}

	public static function meta( array $it ) {
		$m = json_decode( (string) $it['meta'], true );
		return is_array( $m ) ? $m : array();
	}

	private static function save_meta( $id, array $m ) {
		global $wpdb;
		$wpdb->update( flxb_table( 'items' ), array( 'meta' => wp_json_encode( $m ), 'updated_at' => flxb_now() ), array( 'id' => (int) $id ) );
	}

	/** Unused 7-digit code. */
	public static function new_code() {
		global $wpdb;
		$t = flxb_table( 'items' );
		do {
			$code = (string) wp_rand( 1000000, 9999999 );
		} while ( $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$t} WHERE code = %s", $code ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $code;
	}

	/** Called by FLXB_Items::create() for every tracked item row. */
	public static function on_created( $item_id ) {
		global $wpdb;
		$wpdb->update( flxb_table( 'items' ), array( 'code' => self::new_code() ), array( 'id' => (int) $item_id ) );
		self::log( $item_id, 'created' );
	}

	/** Codes for tracked items recorded before codes existed (one per item row). */
	public static function backfill_codes() {
		global $wpdb;
		$t     = flxb_table( 'items' );
		$types = array();
		foreach ( FLXB_Types::all() as $ty ) {
			if ( FLXB_Types::meta( $ty, 'requires_proxy', false ) || FLXB_Types::meta( $ty, 'track_items', false ) ) {
				$types[] = (int) $ty['id'];
			}
		}
		if ( ! $types ) {
			return 0;
		}
		$ids = $wpdb->get_col( "SELECT id FROM {$t} WHERE code = '' AND quantity = 1 AND option_key <> '' AND type_id IN (" . implode( ',', $types ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $ids as $id ) {
			$wpdb->update( $t, array( 'code' => self::new_code() ), array( 'id' => (int) $id ) );
		}
		return count( (array) $ids );
	}

	/** SQL WHERE (items alias i, donations alias d, donors alias dn) for the list filters. */
	public static function where( array $f ) {
		global $wpdb;
		$w = array( "i.code <> ''" );
		if ( ! empty( $f['status'] ) && isset( self::STEPS[ $f['status'] ] ) || 'canceled' === ( $f['status'] ?? '' ) ) {
			$w[] = $wpdb->prepare( 'i.status = %s', $f['status'] );
		}
		if ( ! empty( $f['place'] ) ) {
			$w[] = $wpdb->prepare( 'i.place_ref = %s', (string) $f['place'] );
		}
		if ( ! empty( $f['partner_id'] ) ) {
			$w[] = $wpdb->prepare( 'i.partner_id = %d', (int) $f['partner_id'] );
		}
		if ( ! empty( $f['source_id'] ) ) {
			$w[] = $wpdb->prepare( 'i.source_id = %d', (int) $f['source_id'] );
		}
		if ( ! empty( $f['type_id'] ) ) {
			$w[] = $wpdb->prepare( 'i.type_id = %d', (int) $f['type_id'] );
		}
		if ( ! empty( $f['donation_id'] ) ) {
			$w[] = $wpdb->prepare( 'i.donation_id = %d', (int) $f['donation_id'] );
		}
		if ( ! empty( $f['ids'] ) ) {
			$w[] = 'i.id IN (' . implode( ',', array_map( 'intval', (array) $f['ids'] ) ) . ')';
		}
		if ( ! empty( $f['ref_user_id'] ) ) {
			$w[] = $wpdb->prepare( 'd.ref_user_id = %d', (int) $f['ref_user_id'] );
		}
		/* due: items whose target date has come (from target − early days) and are not completed yet. */
		if ( ! empty( $f['due'] ) ) {
			$w[] = $wpdb->prepare( "i.target_date IS NOT NULL AND i.target_date <= %s AND i.status IN ('pending','approved','sent')", wp_date( 'Y-m-d', time() + DAY_IN_SECONDS * flxb_track_early_days() ) );
		}
		if ( ! empty( $f['target'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $f['target'] ) ) {
			$w[] = $wpdb->prepare( 'i.target_date = %s', $f['target'] );
		}
		if ( ! empty( $f['date_field'] ) && in_array( $f['date_field'], array( 'created_at', 'sent_at', 'done_at', 'approved_at' ), true ) ) {
			$df = 'i.' . $f['date_field'];
			if ( ! empty( $f['from'] ) ) {
				$w[] = $wpdb->prepare( "{$df} >= %s", $f['from'] . ' 00:00:00' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			if ( ! empty( $f['to'] ) ) {
				$w[] = $wpdb->prepare( "{$df} <= %s", $f['to'] . ' 23:59:59' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		if ( isset( $f['paid'] ) && '' !== (string) $f['paid'] ) {
			$in  = "'" . implode( "','", FLXB_Donations::PAID ) . "'";
			$w[] = $f['paid'] ? "d.status IN ($in)" : "d.status NOT IN ($in)";
		}
		if ( ! empty( $f['q'] ) ) {
			$q  = trim( (string) $f['q'] );
			$lk = '%' . $wpdb->esc_like( $q ) . '%';
			$or = array( $wpdb->prepare( 'i.code = %s', $q ), $wpdb->prepare( 'i.beneficiary_name LIKE %s', $lk ), $wpdb->prepare( 'dn.name LIKE %s', $lk ) );
			$dg = preg_replace( '/\D/', '', $q );
			if ( strlen( $dg ) >= 4 ) {
				$or[] = $wpdb->prepare( 'i.beneficiary_phone LIKE %s', '%' . $dg . '%' );
				$or[] = $wpdb->prepare( 'dn.phone LIKE %s', '%' . $dg . '%' );
			}
			if ( ctype_digit( $q ) ) {
				$or[] = $wpdb->prepare( 'i.donation_id = %d', (int) $q );
			}
			$w[] = '(' . implode( ' OR ', $or ) . ')';
		}
		return implode( ' AND ', $w );
	}

	public static function from() {
		return flxb_table( 'items' ) . ' i LEFT JOIN ' . flxb_table( 'donations' ) . ' d ON d.id = i.donation_id LEFT JOIN ' . flxb_table( 'donors' ) . ' dn ON dn.id = d.donor_id';
	}

	/** Items with their gift's payment status and donor. */
	public static function query( array $f = array() ) {
		global $wpdb;
		$per  = max( 1, min( 5000, (int) ( $f['per_page'] ?? 50 ) ) );
		$page = max( 1, (int) ( $f['page'] ?? 1 ) );
		$w    = self::where( $f );
		$from = self::from();
		$ord  = 'target' === ( $f['order'] ?? '' ) ? 'i.target_date IS NULL, i.target_date ASC, i.id ASC' : 'i.id DESC';
		$tot  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$from} WHERE {$w}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT i.*, d.status AS pay_status, d.amount AS don_amount, d.currency AS don_currency, d.wc_order_id, d.provider, d.ref_user_id, dn.name AS donor_name, dn.phone AS donor_phone FROM {$from} WHERE {$w} ORDER BY {$ord} LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array( 'items' => (array) $rows, 'total' => $tot );
	}

	public static function counts( array $f = array() ) {
		global $wpdb;
		unset( $f['status'] );
		$w    = self::where( $f );
		$from = self::from();
		$out  = array_fill_keys( array_keys( self::statuses() ), 0 );
		foreach ( (array) $wpdb->get_results( "SELECT i.status, COUNT(*) n FROM {$from} WHERE {$w} GROUP BY i.status", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$out[ $r['status'] ] = (int) $r['n'];
		}
		return $out;
	}

	/** Is the item's gift paid? */
	public static function paid( array $it ) {
		$d = FLXB_Donations::get( (int) $it['donation_id'] );
		return $d && in_array( $d['status'], FLXB_Donations::PAID, true );
	}

	/* ---------- History ---------- */

	public static function log( $item_id, $event, $note = '', array $data = array() ) {
		global $wpdb;
		$it = self::get( $item_id );
		$wpdb->insert(
			flxb_table( 'item_log' ),
			array(
				'item_id'     => (int) $item_id,
				'donation_id' => $it ? (int) $it['donation_id'] : 0,
				'event'       => sanitize_key( $event ),
				'note'        => mb_substr( sanitize_textarea_field( (string) $note ), 0, 1000 ),
				'data'        => $data ? wp_json_encode( $data ) : null,
				'user_id'     => get_current_user_id(),
				'created_at'  => flxb_now(),
			)
		);
		do_action( 'flxb_kurban_item_changed', (int) $item_id, $event, $data ); // before 2.11
		do_action( 'flxb_track_item_changed', (int) $item_id, $event, $data );
	}

	public static function history( $item_id ) {
		global $wpdb;
		$t = flxb_table( 'item_log' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE item_id = %d ORDER BY id DESC", (int) $item_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/* ---------- Flow ---------- */

	public static function upd( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = flxb_now();
		$wpdb->update( flxb_table( 'items' ), $data, array( 'id' => (int) $id ) );
	}

	/**
	 * Run one step on several items.
	 *
	 * @param int[]  $ids    Item ids.
	 * @param string $action approve | assign | revoke | done | delivered | cancel | reset.
	 * @param array  $o      place (ref, for assign), note, force_paid (import of old records).
	 * @return array { done: int[], skipped: array id => reason }
	 */
	public static function apply( array $ids, $action, array $o = array() ) {
		$done = array();
		$skip = array();
		$note = (string) ( $o['note'] ?? '' );
		$now  = (string) ( $o['at'] ?? flxb_now() );
		$ref  = '';
		$name = '';
		if ( 'assign' === $action ) {
			$ref  = (string) ( $o['place'] ?? '' );
			$name = self::place_name( $ref );
			if ( '' === $name ) {
				/* translators: %s: what a partner is called, e.g. "Partner" */
				return array( 'done' => array(), 'skipped' => array(), 'error' => sprintf( __( 'Choose:%s.', 'flx-donate' ), self::label( 'partner' ) ) );
			}
		}
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$it = self::get( $id );
			if ( ! $it || '' === (string) $it['code'] ) {
				continue;
			}
			$st = (string) $it['status'];
			$at = self::STEPS[ $st ] ?? -1;
			$no = '';
			switch ( $action ) {
				case 'approve':
					if ( 'pending' !== $st ) {
						$no = __( 'not waiting', 'flx-donate' );
						break;
					}
					self::upd( $id, array( 'status' => 'approved', 'approved_at' => $now ) );
					self::log( $id, 'approved', $note );
					break;
				case 'assign':
					if ( ! in_array( $st, array( 'approved', 'sent' ), true ) ) {
						$no = 'pending' === $st ? __( 'approve it first', 'flx-donate' ) : __( 'already completed or canceled', 'flx-donate' );
						break;
					}
					if ( empty( $o['force_paid'] ) && ! self::paid( $it ) ) {
						$no = __( 'payment not received', 'flx-donate' );
						break;
					}
					$block = (string) apply_filters( 'flxb_kurban_assign_block', '', $it, $ref ); // before 2.11
					$block = '' !== $block ? $block : (string) apply_filters( 'flxb_track_assign_block', '', $it, $ref );
					if ( '' !== $block ) {
						$no = $block;
						break;
					}
					if ( 'sent' === $st && $it['place_ref'] === $ref ) {
						$no = __( 'already with this partner', 'flx-donate' );
						break;
					}
					if ( 'sent' === $st ) {
						self::log( $id, 'revoked', $note, array( 'place' => $it['place_ref'], 'place_name' => $it['place_name'], 'reassign' => $ref ) );
					}
					$pid  = 0 === strpos( $ref, 'pt:' ) ? (int) substr( $ref, 3 ) : 0;
					$cost = $pid ? FLXB_Partners::cost( $pid, (int) $it['type_id'], (string) $it['option_key'], substr( $now, 0, 10 ) ) : null;
					self::upd(
						$id,
						array(
							'status'        => 'sent',
							'place_ref'     => $ref,
							'place_name'    => $name,
							'partner_id'    => $pid,
							'cost'          => $cost ? $cost['price'] : 0,
							'cost_currency' => $cost ? $cost['currency'] : '',
							'sent_at'       => $now,
						)
					);
					self::log( $id, 'assigned', $note, array( 'place' => $ref, 'place_name' => $name, 'from' => 'sent' === $st ? $it['place_ref'] : '' ) );
					break;
				case 'revoke':
					if ( 'sent' !== $st ) {
						$no = __( 'not with a partner, or already completed', 'flx-donate' );
						break;
					}
					self::upd( $id, array( 'status' => 'approved', 'place_ref' => '', 'place_name' => '', 'partner_id' => 0, 'cost' => 0, 'cost_currency' => '', 'sent_at' => null ) );
					self::log( $id, 'revoked', $note, array( 'place' => $it['place_ref'], 'place_name' => $it['place_name'] ) );
					break;
				case 'done':
					if ( 'sent' !== $st ) {
						$no = __( 'send it to a partner first', 'flx-donate' );
						break;
					}
					self::upd( $id, array( 'status' => 'done', 'done_at' => $now ) );
					self::log( $id, 'done', $note );
					break;
				case 'delivered':
					if ( $at < 2 ) {
						$no = __( 'not completed yet', 'flx-donate' );
						break;
					}
					self::upd( $id, array( 'status' => 'delivered', 'notified_at' => $now, 'done_at' => $it['done_at'] ? $it['done_at'] : $now ) );
					self::log( $id, 'delivered', $note );
					break;
				case 'cancel':
					if ( ! in_array( $st, array( 'pending', 'approved' ), true ) ) {
						$no = 'sent' === $st ? __( 'take it back from the partner first', 'flx-donate' ) : __( 'cannot be canceled', 'flx-donate' );
						break;
					}
					self::upd( $id, array( 'status' => 'canceled' ) );
					self::log( $id, 'canceled', $note );
					break;
				case 'reset':
					if ( ! in_array( $st, array( 'approved', 'canceled' ), true ) ) {
						$no = __( 'only approved or canceled items', 'flx-donate' );
						break;
					}
					self::upd( $id, array( 'status' => 'pending', 'approved_at' => null ) );
					self::log( $id, 'reset', $note );
					break;
				default:
					$no = __( 'unknown step', 'flx-donate' );
			}
			if ( '' === $no ) {
				$done[] = $id;
			} else {
				$skip[ $id ] = $no;
			}
		}
		return array( 'done' => $done, 'skipped' => $skip );
	}

	/** Correct the owner's name and phone on an item. */
	public static function edit( $id, $name, $phone, $log = true ) {
		$it = self::get( $id );
		if ( ! $it ) {
			return false;
		}
		$name  = mb_substr( sanitize_text_field( (string) $name ), 0, 191 );
		$phone = preg_replace( '/[^\d+]/', '', (string) $phone );
		$ch    = array();
		if ( $name !== (string) $it['beneficiary_name'] ) {
			$ch['beneficiary_name'] = $name;
		}
		if ( $phone !== (string) $it['beneficiary_phone'] ) {
			$ch['beneficiary_phone'] = mb_substr( $phone, 0, 32 );
		}
		if ( ! $ch ) {
			return false;
		}
		self::upd( $id, $ch );
		if ( ! $log ) {
			return true;
		}
		$from = array_intersect_key( $it, $ch );
		self::log( $id, 'edited', '', array( 'from' => $from, 'to' => $ch ) );
		FLXB_Donations::add_audit( (int) $it['donation_id'], 'beneficiaries', array( array( 'item' => (int) $id, 'from' => $from, 'to' => $ch ) ) );
		return true;
	}

	/** Set the target date (the day the owner wants it done; '' clears it). */
	public static function set_target( $id, $date, $log = true ) {
		$it = self::get( $id );
		if ( ! $it ) {
			return false;
		}
		$date = flxb_parse_date( $date );
		if ( (string) $date === (string) $it['target_date'] ) {
			return false;
		}
		self::upd( $id, array( 'target_date' => $date ? $date : null ) );
		if ( $log ) {
			self::log( $id, 'edited', '', array( 'from' => array( 'target_date' => (string) $it['target_date'] ), 'to' => array( 'target_date' => (string) $date ) ) );
		}
		return true;
	}

	/**
	 * Where an item stands against its target date: '' (none), 'early' (before the window),
	 * 'window' (from target − early days up to the target day), 'late' (after the target day, not
	 * completed), 'ok' (completed).
	 */
	public static function target_state( array $it, $today = '' ) {
		$t = (string) ( $it['target_date'] ?? '' );
		if ( '' === $t ) {
			return '';
		}
		if ( in_array( $it['status'], array( 'done', 'delivered', 'canceled' ), true ) ) {
			return 'ok';
		}
		$today = $today ? $today : wp_date( 'Y-m-d' );
		$open  = gmdate( 'Y-m-d', strtotime( $t . ' -' . flxb_track_early_days() . ' days' ) );
		if ( $today < $open ) {
			return 'early';
		}
		return $today <= $t ? 'window' : 'late';
	}

	/* ---------- Files ---------- */

	/** Files kept on the item (videos, photos, documents). */
	public static function files( array $it ) {
		$m   = self::meta( $it );
		$out = array();
		foreach ( array_merge( (array) ( $m['videos'] ?? array() ), (array) ( $m['files'] ?? array() ) ) as $v ) {
			if ( ! empty( $v['att'] ) && get_post( (int) $v['att'] ) ) {
				$v['mime'] = (string) get_post_mime_type( (int) $v['att'] );
				$out[]     = $v;
			}
		}
		return $out;
	}

	/** Does a file complete an item that is with a partner (type meta `file_completes`, default on)? */
	public static function file_completes( array $it ) {
		$t = (int) $it['type_id'] ? FLXB_Types::get( (int) $it['type_id'] ) : null;
		return ! $t || false !== FLXB_Types::meta( $t, 'file_completes', true );
	}

	/** Keep a media library attachment on the item. */
	public static function add_file( $id, $attachment_id, $note = '' ) {
		$it = self::get( $id );
		if ( ! $it || ! get_post( (int) $attachment_id ) ) {
			return new WP_Error( 'flxb_file', __( 'The file could not be saved.', 'flx-donate' ) );
		}
		$m          = self::meta( $it );
		$m['files'] = array_values( (array) ( $m['files'] ?? array() ) );
		$m['files'][] = array( 'att' => (int) $attachment_id, 'at' => flxb_now(), 'by' => get_current_user_id(), 'sent' => array() );
		self::save_meta( $id, $m );
		update_post_meta( (int) $attachment_id, '_flxb_item', (int) $id );
		self::log( $id, 'file', $note, array( 'att' => (int) $attachment_id ) );
		if ( 'sent' === $it['status'] && self::file_completes( $it ) ) {
			self::apply( array( $id ), 'done' );
		}
		return true;
	}

	/** Add a file from disk (e.g. one that came over WhatsApp with the item's code). */
	public static function add_file_path( $id, $path, $filename = '', $note = '' ) {
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'flxb_file', __( 'The file could not be saved.', 'flx-donate' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( $filename ? $filename : basename( $path ) );
		copy( $path, $tmp );
		$att = media_handle_sideload( array( 'name' => $filename ? $filename : basename( $path ), 'tmp_name' => $tmp ), 0 );
		if ( is_wp_error( $att ) ) {
			return $att;
		}
		return self::add_file( $id, $att, $note );
	}

	public static function remove_file( $id, $attachment_id ) {
		$it = self::get( $id );
		if ( ! $it ) {
			return false;
		}
		$m    = self::meta( $it );
		$keep = function ( $v ) use ( $attachment_id ) {
			return (int) ( $v['att'] ?? 0 ) !== (int) $attachment_id;
		};
		foreach ( array( 'files', 'videos' ) as $k ) {
			if ( isset( $m[ $k ] ) ) {
				$m[ $k ] = array_values( array_filter( (array) $m[ $k ], $keep ) );
			}
		}
		self::save_meta( $id, $m );
		self::log( $id, 'file_del', '', array( 'att' => (int) $attachment_id ) );
		return true;
	}

	/** Phone the file goes to: the item's own number, else the donor's. */
	public static function owner_phone( array $it ) {
		if ( '' !== (string) $it['beneficiary_phone'] ) {
			return (string) $it['beneficiary_phone'];
		}
		$d  = FLXB_Donations::get( (int) $it['donation_id'] );
		$dn = $d && (int) $d['donor_id'] ? FLXB_Donors::get( (int) $d['donor_id'] ) : null;
		return $dn ? (string) $dn['phone'] : '';
	}

	public static function can_send() {
		return function_exists( 'flxc_send' ) && function_exists( 'flxc_ensure_chat' ) && function_exists( 'flxc_default_connection' );
	}

	public static function file_caption( array $it ) {
		$tpl = self::label( 'caption', (int) $it['type_id'] );
		$t   = sprintf( $tpl, (string) $it['beneficiary_name'], (string) $it['code'] );
		$t   = (string) apply_filters( 'flxb_kurban_video_caption', $t, $it ); // before 2.11
		return (string) apply_filters( 'flxb_track_file_caption', $t, $it );
	}

	/** Send a kept file to the owner over WhatsApp (Flux Chat). */
	public static function send_file( $id, $attachment_id, $phone = '' ) {
		$it = self::get( $id );
		if ( ! $it ) {
			return new WP_Error( 'flxb_item', __( 'Item not found.', 'flx-donate' ) );
		}
		if ( ! self::can_send() ) {
			return new WP_Error( 'flxb_wa', __( 'Flux Chat is needed to send over WhatsApp.', 'flx-donate' ) );
		}
		$phone = function_exists( 'flxc_normalize_phone' ) ? flxc_normalize_phone( '' !== (string) $phone ? $phone : self::owner_phone( $it ) ) : preg_replace( '/\D/', '', (string) $phone );
		if ( ! $phone ) {
			return new WP_Error( 'flxb_phone', __( 'There is no phone number for the owner.', 'flx-donate' ) );
		}
		$path = get_attached_file( (int) $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			return new WP_Error( 'flxb_file', __( 'The file was not found.', 'flx-donate' ) );
		}
		$conn = flxc_default_connection();
		if ( ! $conn ) {
			return new WP_Error( 'flxb_wa', __( 'There is no WhatsApp connection.', 'flx-donate' ) );
		}
		$chat = flxc_ensure_chat( $conn['id'], $phone . '@s.whatsapp.net', array( 'name' => (string) $it['beneficiary_name'] ) );
		if ( ! $chat ) {
			return new WP_Error( 'flxb_wa', __( 'The WhatsApp chat could not be opened.', 'flx-donate' ) );
		}
		$mime = (string) get_post_mime_type( (int) $attachment_id );
		$kind = 0 === strpos( $mime, 'video/' ) ? 'video' : ( 0 === strpos( $mime, 'image/' ) ? 'image' : ( 0 === strpos( $mime, 'audio/' ) ? 'audio' : 'document' ) );
		$r    = flxc_send(
			$chat,
			array(
				'type'    => $kind,
				'body'    => self::file_caption( $it ),
				'file'    => $path,
				'mime'    => $mime,
				'name'    => basename( $path ),
				'source'  => 'flxb-track',
				'user_id' => get_current_user_id(),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$m = self::meta( $it );
		foreach ( array( 'files', 'videos' ) as $k ) {
			foreach ( (array) ( $m[ $k ] ?? array() ) as $i => $v ) {
				if ( (int) ( $v['att'] ?? 0 ) === (int) $attachment_id ) {
					$m[ $k ][ $i ]['sent'][] = array( 'at' => flxb_now(), 'to' => $phone, 'by' => get_current_user_id() );
				}
			}
		}
		self::save_meta( $id, $m );
		self::log( $id, 'file_sent', '', array( 'att' => (int) $attachment_id, 'to' => $phone ) );
		if ( in_array( $it['status'], array( 'sent', 'done' ), true ) ) {
			self::apply( array( $id ), 'delivered' );
		}
		return true;
	}

	/* Names used before 2.11. */
	public static function videos( array $it ) {
		return self::files( $it );
	}

	public static function add_video( $id, $attachment_id, $note = '' ) {
		return self::add_file( $id, $attachment_id, $note );
	}

	public static function add_video_file( $id, $path, $filename = '', $note = '' ) {
		return self::add_file_path( $id, $path, $filename, $note );
	}

	public static function send_video( $id, $attachment_id, $phone = '' ) {
		return self::send_file( $id, $attachment_id, $phone );
	}
}

class_alias( 'FLXB_Track', 'FLXB_Kurban' ); // before 2.11

/* ---------- Public API (other plugins) ---------- */

/** Days before the target date an item may already be done (option flxb_track_early_days, default 1). */
function flxb_track_early_days() {
	return max( 0, min( 30, (int) apply_filters( 'flxb_track_early_days', (int) get_option( 'flxb_track_early_days', 1 ) ) ) );
}

/** Tracked item by its 7-digit code (null when none). */
function flxb_track_by_code( $code ) {
	return FLXB_Track::by_code( $code );
}

/** Run a step: approve | assign (o.place) | revoke | done | delivered | cancel | reset. */
function flxb_track_apply( array $item_ids, $action, array $o = array() ) {
	return FLXB_Track::apply( $item_ids, $action, $o );
}

/** Keep a file (on disk) on an item. */
function flxb_track_add_file( $item_id, $path, $filename = '', $note = '' ) {
	return FLXB_Track::add_file_path( $item_id, $path, $filename, $note );
}

/** Send a kept file to the owner over WhatsApp. */
function flxb_track_send_file( $item_id, $attachment_id, $phone = '' ) {
	return FLXB_Track::send_file( $item_id, $attachment_id, $phone );
}

/** Write a line to an item's history. */
function flxb_track_log( $item_id, $event, $note = '', array $data = array() ) {
	FLXB_Track::log( $item_id, $event, $note, $data );
}

/* Names used before 2.11 (Qurban 0.3). */
function flxb_kurban_by_code( $code ) {
	return FLXB_Track::by_code( $code );
}

function flxb_kurban_apply( array $item_ids, $action, array $o = array() ) {
	return FLXB_Track::apply( $item_ids, $action, $o );
}

function flxb_kurban_add_video_file( $item_id, $path, $filename = '', $note = '' ) {
	return FLXB_Track::add_file_path( $item_id, $path, $filename, $note );
}

function flxb_kurban_send_video( $item_id, $attachment_id, $phone = '' ) {
	return FLXB_Track::send_file( $item_id, $attachment_id, $phone );
}

function flxb_kurban_log( $item_id, $event, $note = '', array $data = array() ) {
	FLXB_Track::log( $item_id, $event, $note, $data );
}
