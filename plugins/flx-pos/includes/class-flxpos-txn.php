<?php
/**
 * İşlem kayıtları: her ödeme denemesi, iade ve iptal bir satır. Kart numarası saklanmaz (ilk 6 + son 4).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Txn {

	/** Durumlar. */
	const PENDING  = 'pending';   // başlatıldı, banka dönüşü bekleniyor
	const PAID     = 'paid';
	const FAILED   = 'failed';
	const REFUNDED = 'refunded';  // tamamı iade
	const PARTIAL  = 'partial';   // kısmi iade
	const VOIDED   = 'voided';    // iptal (gün sonu öncesi)
	const REVIEW   = 'review';    // bankada başarılı ama tutar/sipariş tutmuyor: elle bakılmalı

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'flxpos_txn';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = self::table();
		dbDelta(
			"CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ref varchar(64) NOT NULL DEFAULT '',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			kind varchar(16) NOT NULL DEFAULT 'sale',
			account varchar(40) NOT NULL DEFAULT '',
			provider varchar(40) NOT NULL DEFAULT '',
			mode varchar(16) NOT NULL DEFAULT '',
			test tinyint(1) NOT NULL DEFAULT 0,
			amount decimal(14,2) NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT 'TRY',
			installment smallint(5) unsigned NOT NULL DEFAULT 1,
			status varchar(16) NOT NULL DEFAULT 'pending',
			bank_ref varchar(100) NOT NULL DEFAULT '',
			auth_code varchar(40) NOT NULL DEFAULT '',
			card varchar(24) NOT NULL DEFAULT '',
			card_brand varchar(20) NOT NULL DEFAULT '',
			message varchar(255) NOT NULL DEFAULT '',
			refunded decimal(14,2) NOT NULL DEFAULT 0,
			data longtext NULL,
			ip varchar(45) NOT NULL DEFAULT '',
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY ref (ref),
			KEY order_id (order_id),
			KEY status (status),
			KEY created (created)
			) {$c};"
		);
		update_option( 'flxpos_db', FLXPOS_DB, false );
	}

	public static function maybe_upgrade() {
		if ( (int) get_option( 'flxpos_db' ) < FLXPOS_DB ) {
			self::install();
		}
	}

	/** Yeni bir kayıt; dönüş: satır. */
	public static function create( array $row ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$row = array_merge(
			array(
				'ref'         => '',
				'order_id'    => 0,
				'parent_id'   => 0,
				'kind'        => 'sale',
				'account'     => '',
				'provider'    => '',
				'mode'        => '',
				'test'        => 0,
				'amount'      => 0,
				'currency'    => 'TRY',
				'installment' => 1,
				'status'      => self::PENDING,
				'card'        => '',
				'card_brand'  => '',
				'ip'          => flxpos_client_ip(),
			),
			$row
		);
		if ( '' === $row['ref'] ) {
			$row['ref'] = self::new_ref( (int) $row['order_id'] );
		}
		$row['data']    = isset( $row['data'] ) ? wp_json_encode( flxpos_redact( (array) $row['data'] ) ) : '{}';
		$row['created'] = $now;
		$row['updated'] = $now;
		$wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return self::get( (int) $wpdb->insert_id );
	}

	/**
	 * Bankaya giden sipariş numarası: en çok 20 karakter, harf+rakam, her denemede yeni ("FX" + sipariş + zaman).
	 * Bankaların çoğu aynı sipariş numarasını ikinci kez kabul etmez.
	 */
	public static function new_ref( $order_id ) {
		$t = strtoupper( base_convert( (string) ( time() - 1700000000 ), 10, 36 ) . bin2hex( random_bytes( 2 ) ) );
		return substr( 'FX' . $order_id . 'T' . $t, 0, 20 );
	}

	public static function get( $id ) {
		global $wpdb;
		$t = self::table();
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $r ? self::hydrate( $r ) : null;
	}

	public static function by_ref( $ref ) {
		global $wpdb;
		$t = self::table();
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE ref = %s", (string) $ref ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $r ? self::hydrate( $r ) : null;
	}

	/** Siparişin başarılı satış kaydı (iade için). */
	public static function paid_for_order( $order_id ) {
		global $wpdb;
		$t = self::table();
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE order_id = %d AND kind = 'sale' AND status IN ('paid','partial') ORDER BY id DESC LIMIT 1", (int) $order_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $r ? self::hydrate( $r ) : null;
	}

	public static function for_order( $order_id ) {
		global $wpdb;
		$t    = self::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE order_id = %d ORDER BY id DESC LIMIT 50", (int) $order_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	private static function hydrate( array $r ) {
		$r['id']          = (int) $r['id'];
		$r['order_id']    = (int) $r['order_id'];
		$r['parent_id']   = (int) $r['parent_id'];
		$r['test']        = (int) $r['test'];
		$r['amount']      = (float) $r['amount'];
		$r['refunded']    = (float) $r['refunded'];
		$r['installment'] = (int) $r['installment'];
		$d                = json_decode( (string) $r['data'], true );
		$r['data']        = is_array( $d ) ? $d : array();
		return $r;
	}

	/** Alanları güncelle; data birleştirilir (maskelenerek). */
	public static function update( $id, array $set, array $data = array() ) {
		global $wpdb;
		if ( $data ) {
			$cur         = self::get( $id );
			$set['data'] = wp_json_encode( array_merge( $cur ? $cur['data'] : array(), flxpos_redact( $data ) ) );
		}
		if ( isset( $set['message'] ) ) {
			$set['message'] = mb_substr( wp_strip_all_tags( (string) $set['message'] ), 0, 250 );
		}
		$set['updated'] = current_time( 'mysql', true );
		$wpdb->update( self::table(), $set, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Durumu yalnız beklemedeyse değiştir (aynı dönüş iki kez gelirse ikincisi bir şey yapmaz).
	 *
	 * @return bool Bu çağrı mı değiştirdi.
	 */
	public static function settle( $id, $status, array $set = array(), array $data = array() ) {
		global $wpdb;
		$t   = self::table();
		$now = current_time( 'mysql', true );
		$n   = $wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = %s, updated = %s WHERE id = %d AND status = %s", $status, $now, (int) $id, self::PENDING ) ); // phpcs:ignore WordPress.DB
		if ( $n ) {
			self::update( $id, $set, $data );
		}
		return (bool) $n;
	}

	/**
	 * Liste (yönetim ekranı).
	 *
	 * @return array{rows:array,total:int}
	 */
	public static function query( array $q ) {
		global $wpdb;
		$t     = self::table();
		$where = array( '1=1' );
		$args  = array();
		if ( ! empty( $q['status'] ) ) {
			$where[] = 'status = %s';
			$args[]  = (string) $q['status'];
		}
		if ( ! empty( $q['account'] ) ) {
			$where[] = 'account = %s';
			$args[]  = (string) $q['account'];
		}
		if ( ! empty( $q['s'] ) ) {
			$like    = '%' . $wpdb->esc_like( (string) $q['s'] ) . '%';
			$where[] = '(ref LIKE %s OR bank_ref LIKE %s OR order_id = %d OR card LIKE %s OR message LIKE %s)';
			array_push( $args, $like, $like, (int) $q['s'], $like, $like );
		}
		if ( isset( $q['test'] ) && '' !== $q['test'] ) {
			$where[] = 'test = %d';
			$args[]  = (int) $q['test'];
		}
		$per   = max( 1, min( 200, (int) ( $q['per'] ?? 30 ) ) );
		$page  = max( 1, (int) ( $q['page'] ?? 1 ) );
		$w     = implode( ' AND ', $where );
		$sql   = "SELECT * FROM {$t} WHERE {$w} ORDER BY id DESC LIMIT %d OFFSET %d";
		$rows  = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$csql  = "SELECT COUNT(*) FROM {$t} WHERE {$w}";
		$total = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $csql, $args ) ) : $wpdb->get_var( $csql ) ); // phpcs:ignore WordPress.DB
		return array(
			'rows'  => array_map( array( __CLASS__, 'hydrate' ), (array) $rows ),
			'total' => $total,
		);
	}

	/** Son 30 gün özeti (panel). */
	public static function summary( $days = 30 ) {
		global $wpdb;
		$t    = self::table();
		$from = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * (int) $days );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, currency, COUNT(*) n, SUM(amount) total FROM {$t} WHERE kind = 'sale' AND test = 0 AND created >= %s GROUP BY status, currency", $from ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return (array) $rows;
	}

	public static function status_labels() {
		return array(
			self::PENDING  => __( 'Bekliyor', 'flx-pos' ),
			self::PAID     => __( 'Ödendi', 'flx-pos' ),
			self::FAILED   => __( 'Başarısız', 'flx-pos' ),
			self::REFUNDED => __( 'İade edildi', 'flx-pos' ),
			self::PARTIAL  => __( 'Kısmi iade', 'flx-pos' ),
			self::VOIDED   => __( 'İptal edildi', 'flx-pos' ),
			self::REVIEW   => __( 'Kontrol gerekli', 'flx-pos' ),
		);
	}

	/** Bekleyen ve süresi geçen denemeler başarısız sayılır (günlük). */
	public static function expire_stale() {
		global $wpdb;
		$t    = self::table();
		$from = gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = %s, message = %s WHERE status = %s AND created < %s", self::FAILED, 'timeout', self::PENDING, $from ) ); // phpcs:ignore WordPress.DB
	}
}
