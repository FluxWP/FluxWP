<?php
/**
 * Zaman tüneli olayları.
 *
 * Ödeme olayları (bağış, sipariş) `paid = 1` ile toplamlara girer; tutar raporlama para birimine çevrilir
 * (`base_amount`). Kur bulunamazsa `base_amount` boş kalır: sıklığa sayılır, değere sayılmaz.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Events {

	/** @var bool Toplu eşitlemede her olayda yeniden hesap yapılmaz. */
	public static $defer_recalc = false;

	/**
	 * Olay ekler ya da (aynı `uniq`) günceller.
	 *
	 * @param array $a source, title, amount, currency, paid, url, data, user_id, at (GMT), uniq.
	 * @return int olay kimliği
	 */
	public static function add( $contact_id, $type, array $a = array() ) {
		global $wpdb;
		$contact_id = (int) $contact_id;
		if ( ! $contact_id || '' === (string) $type ) {
			return 0;
		}
		$t      = flxcrm_table( 'events' );
		$uniq   = isset( $a['uniq'] ) && '' !== (string) $a['uniq'] ? mb_substr( (string) $a['uniq'], 0, 120 ) : null;
		$amount = round( (float) ( $a['amount'] ?? 0 ), 2 );
		$cur    = strtoupper( (string) ( $a['currency'] ?? ( $amount ? flxcrm_currency() : '' ) ) );
		$at     = ! empty( $a['at'] ) ? (string) $a['at'] : flxcrm_now();
		$row    = array(
			'contact_id'  => $contact_id,
			'type'        => sanitize_key( $type ),
			'source'      => sanitize_key( (string) ( $a['source'] ?? '' ) ),
			'title'       => mb_substr( wp_strip_all_tags( (string) ( $a['title'] ?? '' ) ), 0, 255 ),
			'amount'      => $amount,
			'currency'    => $cur,
			'base_amount' => $amount ? flxcrm_to_base( $amount, $cur, $at ) : 0,
			'paid'        => empty( $a['paid'] ) ? 0 : 1,
			'url'         => isset( $a['url'] ) ? esc_url_raw( (string) $a['url'] ) : '',
			'data'        => ! empty( $a['data'] ) ? wp_json_encode( $a['data'], JSON_UNESCAPED_UNICODE ) : null,
			'user_id'     => (int) ( $a['user_id'] ?? 0 ),
			'at'          => $at,
		);
		$id      = 0;
		$changed = true;
		$is_new  = true;
		if ( null !== $uniq ) {
			$old = $wpdb->get_row( $wpdb->prepare( "SELECT id, contact_id, paid, base_amount, title, data FROM $t WHERE uniq = %s", $uniq ), ARRAY_A ); // phpcs:ignore WordPress.DB
			if ( $old ) {
				$id      = (int) $old['id'];
				$is_new  = false;
				$changed = (int) $old['paid'] !== $row['paid'] || (float) $old['base_amount'] !== (float) $row['base_amount'] || (int) $old['contact_id'] !== $contact_id || $old['title'] !== $row['title'] || (string) $old['data'] !== (string) $row['data'];
				if ( $changed ) {
					$wpdb->update( $t, $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
					if ( (int) $old['contact_id'] !== $contact_id && ! self::$defer_recalc ) {
						FLXCRM_Contacts::recalc( (int) $old['contact_id'] );
					}
				}
			}
		}
		if ( ! $id ) {
			$row['uniq'] = $uniq;
			if ( ! $wpdb->insert( $t, $row ) ) { // phpcs:ignore WordPress.DB
				return 0;
			}
			$id = (int) $wpdb->insert_id;
		}
		if ( $changed && ! self::$defer_recalc ) {
			FLXCRM_Contacts::recalc( $contact_id );
			FLXCRM_Contacts::flush_stats();
		}
		if ( $is_new ) {
			/**
			 * Yeni olay. Otomasyonlar (0.2) buradan tetiklenir.
			 *
			 * @param int    $id         Olay.
			 * @param int    $contact_id Kişi.
			 * @param string $type       Tür.
			 * @param array  $row        Satır.
			 */
			do_action( 'flxcrm_event', $id, $contact_id, $row['type'], $row );
		}
		return $id;
	}

	public static function delete_by_uniq( $uniq ) {
		global $wpdb;
		$t   = flxcrm_table( 'events' );
		$cid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT contact_id FROM $t WHERE uniq = %s", $uniq ) ); // phpcs:ignore WordPress.DB
		if ( $cid ) {
			$wpdb->delete( $t, array( 'uniq' => $uniq ) ); // phpcs:ignore WordPress.DB
			if ( ! self::$defer_recalc ) {
				FLXCRM_Contacts::recalc( $cid );
			}
		}
	}

	/** Elle eklenen not/arama/görüşme silinebilir; kaynaktan gelen olay silinmez. */
	public static function delete( $id ) {
		global $wpdb;
		$t   = flxcrm_table( 'events' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, contact_id, type, uniq FROM $t WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( ! $row || null !== $row['uniq'] || ! in_array( $row['type'], flxcrm_manual_event_types(), true ) ) {
			return false;
		}
		$wpdb->delete( $t, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
		FLXCRM_Contacts::recalc( (int) $row['contact_id'] );
		return true;
	}

	/**
	 * Kişinin zaman tüneli.
	 *
	 * @param array $f type, limit, before (id).
	 */
	public static function timeline( $contact_id, array $f = array() ) {
		global $wpdb;
		$t      = flxcrm_table( 'events' );
		$sql    = "SELECT * FROM $t WHERE contact_id = %d";
		$params = array( (int) $contact_id );
		if ( ! empty( $f['type'] ) ) {
			$types = array_filter( array_map( 'sanitize_key', (array) $f['type'] ) );
			if ( $types ) {
				$sql   .= ' AND type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ')';
				$params = array_merge( $params, $types );
			}
		}
		$sql     .= ' ORDER BY at DESC, id DESC LIMIT %d OFFSET %d';
		$params[] = max( 1, min( 500, (int) ( $f['limit'] ?? 100 ) ) );
		$params[] = max( 0, (int) ( $f['offset'] ?? 0 ) );
		$rows     = (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( $rows as &$r ) {
			$r['data'] = $r['data'] ? json_decode( $r['data'], true ) : array();
		}
		return $rows;
	}

	/** Tüm kişilerde son olaylar (genel bakış). */
	public static function recent( $limit = 12, array $types = array() ) {
		global $wpdb;
		$t    = flxcrm_table( 'events' );
		$c    = flxcrm_table( 'contacts' );
		$skip = "'segment_entered','role_added','consent','created'";
		$w    = $types ? ' AND e.type IN (' . implode( ',', array_map( static function ( $x ) {
			return "'" . esc_sql( sanitize_key( $x ) ) . "'";
		}, $types ) ) . ')' : " AND e.type NOT IN ($skip)";
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT e.*, c.name, c.email, c.phone FROM $t e INNER JOIN $c c ON c.id = e.contact_id WHERE 1=1 $w ORDER BY e.at DESC, e.id DESC LIMIT %d", (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/** Kur bulunamadığı için değere sayılmayan ödeme olayı sayısı (sistem kontrolü). */
	public static function unconverted() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxcrm_table( 'events' ) . ' WHERE paid = 1 AND base_amount IS NULL' ); // phpcs:ignore WordPress.DB
	}
}
