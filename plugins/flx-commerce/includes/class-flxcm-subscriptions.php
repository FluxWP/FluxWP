<?php
/**
 * Abonelikler: aylık / yıllık yinelenen ödeme (WooCommerce üzerinde, ücretli eklenti yok).
 *
 * Akış:
 *  - Sepette abonelik ürünü → siparişte satır meta `_flxcm_period` → sipariş oluşunca abonelik "pending".
 *  - Sipariş ödenince (her ödeme yöntemi, Havale/EFT onayı dahil) → "active", olay flxcm_subscription_started.
 *  - Dönem bitmeden `renew_days` gün önce yenileme siparişi + hatırlatma e-postası (Havale/EFT ya da ödeme bağlantısı).
 *  - Yenileme ödenince dönem uzar → flxcm_subscription_renewed.
 *  - Dönem sonunda ödenmemişse "past_due" (ek süre), ek süre bitince "expired" → flxcm_subscription_ended.
 *  - Müşteri iptal ederse "cancelled" (dönem sonuna kadar geçerli), dönem bitince "expired".
 *
 * Kartla otomatik çekim (Faz 2) bu sınıfa sağlayıcı olarak eklenecek; olaylar aynı kalır.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Subscriptions {

	const TICK      = 'flxcm_subs_tick';
	const HEARTBEAT = 'flxcm_subs_tick_at';
	const PERIODS   = array( 'month', 'year' );
	const STATUSES  = array( 'pending', 'active', 'past_due', 'cancelled', 'expired' );

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'flxcm_subscriptions';
	}

	public static function schema( $charset ) {
		$t = self::table();
		return "CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			status varchar(20) NOT NULL DEFAULT 'pending',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(190) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL DEFAULT '',
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_name varchar(255) NOT NULL DEFAULT '',
			period varchar(10) NOT NULL DEFAULT 'month',
			amount decimal(18,2) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT '',
			payment_method varchar(100) NOT NULL DEFAULT '',
			parent_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			last_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			renewal_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			renewals int(10) unsigned NOT NULL DEFAULT 0,
			anchor_day tinyint(2) unsigned NOT NULL DEFAULT 0,
			started_at datetime NULL DEFAULT NULL,
			period_end datetime NULL DEFAULT NULL,
			cancelled_at datetime NULL DEFAULT NULL,
			ended_at datetime NULL DEFAULT NULL,
			end_reason varchar(20) NOT NULL DEFAULT '',
			meta longtext NULL,
			created_at datetime NULL DEFAULT NULL,
			updated_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status_end (status,period_end),
			KEY user_id (user_id),
			KEY email (email),
			KEY parent_order_id (parent_order_id)
			) {$charset};";
	}

	public static function init() {
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'from_order' ), 20 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'from_order' ), 20 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'order_status' ), 20, 4 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'order_paid_id' ), 20 );
		add_action( self::TICK, array( __CLASS__, 'tick' ) );
		add_action( 'flxcm_daily', array( __CLASS__, 'tick' ), 20 );
		add_action( 'shutdown', array( __CLASS__, 'pump' ) );
		add_filter( 'flxi_email_types', array( __CLASS__, 'email_types' ) );
		if ( ! wp_next_scheduled( self::TICK ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::TICK );
		}
	}

	/* ---------- Okuma ---------- */

	public static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	private static function ts( $gmt ) {
		return $gmt ? (int) strtotime( $gmt . ' UTC' ) : 0;
	}

	public static function row( $id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Dışarıya verilen biçim (olaylar, API, Hesabım). Zamanlar Unix (UTC).
	 *
	 * @param array|int $row Satır ya da id.
	 * @return array|null
	 */
	public static function export( $row ) {
		$row = is_array( $row ) ? $row : self::row( (int) $row );
		if ( ! $row ) {
			return null;
		}
		$meta = json_decode( (string) $row['meta'], true );
		$meta = is_array( $meta ) ? $meta : array();
		$card = isset( $meta[ FLXCM_Sub_Cards::META ] ) && is_array( $meta[ FLXCM_Sub_Cards::META ] ) ? $meta[ FLXCM_Sub_Cards::META ] : null;
		unset( $meta[ FLXCM_Sub_Cards::META ] ); // Kart verisi dışarı verilmez; yalnız etiketi.
		return array(
			'id'               => (int) $row['id'],
			'status'           => (string) $row['status'],
			'has_access'       => self::has_access( $row ),
			'auto_renew'       => in_array( $row['status'], array( 'active', 'past_due' ), true ),
			'user_id'          => (int) $row['user_id'],
			'email'            => (string) $row['email'],
			'name'             => (string) $row['name'],
			'product_id'       => (int) $row['product_id'],
			'product_name'     => (string) $row['product_name'],
			'period'           => (string) $row['period'],
			'amount'           => (float) $row['amount'],
			'currency'         => (string) $row['currency'],
			'payment_method'   => (string) $row['payment_method'],
			'parent_order_id'  => (int) $row['parent_order_id'],
			'last_order_id'    => (int) $row['last_order_id'],
			'renewal_order_id' => (int) $row['renewal_order_id'],
			'renewals'         => (int) $row['renewals'],
			'started_at'       => self::ts( $row['started_at'] ),
			'period_end'       => self::ts( $row['period_end'] ),
			'cancelled_at'     => self::ts( $row['cancelled_at'] ),
			'ended_at'         => self::ts( $row['ended_at'] ),
			'end_reason'       => (string) $row['end_reason'],
			'card'             => $card ? (string) ( $card['label'] ?? '' ) : '',
			'auto_charge'      => $card && in_array( $row['status'], array( 'active', 'past_due' ), true ),
			'meta'             => $meta,
			'created_at'       => self::ts( $row['created_at'] ),
		);
	}

	/** Hizmet açık mı: etkin, ek sürede ya da iptal edilmiş ama dönemi bitmemiş. */
	public static function has_access( array $row ) {
		if ( in_array( $row['status'], array( 'active', 'past_due' ), true ) ) {
			return true;
		}
		return 'cancelled' === $row['status'] && self::ts( $row['period_end'] ) > time();
	}

	/**
	 * Liste.
	 *
	 * @param array $args status (string|array), user_id, email, product_id, s, paged, per_page, orderby.
	 * @return array{rows: array, total: int, pages: int, paged: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$t     = self::table();
		$where = array( '1=1' );
		$vals  = array();
		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$st      = array_values( array_intersect( (array) $args['status'], self::STATUSES ) );
			$st      = $st ? $st : array( '-' );
			$where[] = 'status IN (' . implode( ',', array_fill( 0, count( $st ), '%s' ) ) . ')';
			$vals    = array_merge( $vals, $st );
		}
		if ( isset( $args['user_id'] ) || isset( $args['email'] ) ) {
			$or = array();
			if ( ! empty( $args['user_id'] ) ) {
				$or[]   = 'user_id = %d';
				$vals[] = (int) $args['user_id'];
			}
			if ( ! empty( $args['email'] ) ) {
				$or[]   = 'email = %s';
				$vals[] = strtolower( (string) $args['email'] );
			}
			$where[] = $or ? '(' . implode( ' OR ', $or ) . ')' : '0=1';
		}
		if ( ! empty( $args['product_id'] ) ) {
			$where[] = 'product_id = %d';
			$vals[]  = (int) $args['product_id'];
		}
		if ( ! empty( $args['s'] ) ) {
			$like    = '%' . $wpdb->esc_like( (string) $args['s'] ) . '%';
			$where[] = '(email LIKE %s OR name LIKE %s OR product_name LIKE %s OR id = %d)';
			array_push( $vals, $like, $like, $like, (int) $args['s'] );
		}
		$per   = max( 1, min( 200, (int) ( $args['per_page'] ?? 25 ) ) );
		$paged = max( 1, (int) ( $args['paged'] ?? 1 ) );
		$order = 'period_end' === ( $args['orderby'] ?? '' ) ? 'period_end IS NULL, period_end ASC' : 'id DESC';
		$sql_w = implode( ' AND ', $where );
		// phpcs:disable WordPress.DB
		$total = (int) $wpdb->get_var( $vals ? $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$sql_w}", $vals ) : "SELECT COUNT(*) FROM {$t} WHERE {$sql_w}" );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$sql_w} ORDER BY {$order} LIMIT %d OFFSET %d", array_merge( $vals, array( $per, ( $paged - 1 ) * $per ) ) ), ARRAY_A );
		// phpcs:enable WordPress.DB
		return array(
			'rows'  => $rows ? $rows : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per ),
			'paged' => $paged,
		);
	}

	/** Durum sayıları + aylık yinelenen gelir (yıllıklar 12'ye bölünür; site para biriminde). */
	public static function counts() {
		global $wpdb;
		$t   = self::table();
		$out = array_fill_keys( self::STATUSES, 0 );
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$t} GROUP BY status", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$out[ $r['status'] ] = (int) $r['n'];
		}
		$out['all'] = array_sum( $out );
		$cur        = get_woocommerce_currency();
		$out['mrr'] = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(CASE WHEN period = 'year' THEN amount / 12 ELSE amount END) FROM {$t} WHERE status IN ('active','past_due') AND currency = %s", $cur ) ); // phpcs:ignore WordPress.DB
		$soon       = gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS );
		$out['due'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE status = 'active' AND period_end <= %s", $soon ) ); // phpcs:ignore WordPress.DB
		return $out;
	}

	/* ---------- Yazma ---------- */

	private static function update( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = self::now();
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/** Durumu değiştirir ve flxcm_subscription_status_changed olayını yayar. */
	private static function set_status( array $row, $status, array $extra = array() ) {
		$old = (string) $row['status'];
		self::update( (int) $row['id'], array_merge( $extra, array( 'status' => $status ) ) );
		$sub = self::export( (int) $row['id'] );
		if ( $old !== $status ) {
			/**
			 * Abonelik durumu değişti.
			 *
			 * @param array  $sub Abonelik (export biçimi).
			 * @param string $old Eski durum.
			 * @param string $new Yeni durum.
			 */
			do_action( 'flxcm_subscription_status_changed', $sub, $old, $status );
		}
		return $sub;
	}

	/** Abonelik meta'sına anahtar yazar (Flux Cloud gibi eklentiler kendi bağlarını tutar). */
	public static function update_meta( $id, $key, $value ) {
		$row = self::row( $id );
		if ( ! $row ) {
			return false;
		}
		$meta = json_decode( (string) $row['meta'], true );
		$meta = is_array( $meta ) ? $meta : array();
		if ( null === $value ) {
			unset( $meta[ $key ] );
		} else {
			$meta[ $key ] = $value;
		}
		self::update( (int) $id, array( 'meta' => wp_json_encode( $meta ) ) );
		return true;
	}

	/**
	 * Dönem ekler; ay sonu kayması olmaz (31 Ocak + 1 ay = 28/29 Şubat, sonra yeniden 31 Mart).
	 *
	 * @param int    $from   Unix.
	 * @param string $period month|year.
	 * @param int    $anchor Ayın günü (1-31), 0 = $from'un günü.
	 */
	public static function add_period( $from, $period, $anchor = 0 ) {
		$y      = (int) gmdate( 'Y', $from );
		$m      = (int) gmdate( 'n', $from );
		$anchor = $anchor ? (int) $anchor : (int) gmdate( 'j', $from );
		if ( 'year' === $period ) {
			++$y;
		} else {
			++$m;
			if ( $m > 12 ) {
				$m = 1;
				++$y;
			}
		}
		$days = (int) gmdate( 't', gmmktime( 0, 0, 0, $m, 1, $y ) );
		return gmmktime( (int) gmdate( 'G', $from ), (int) gmdate( 'i', $from ), (int) gmdate( 's', $from ), $m, min( $anchor, $days ), $y );
	}

	/** Siparişteki abonelik satırlarından bekleyen abonelikleri açar (bir kez). */
	public static function from_order( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order || $order->get_meta( '_flxcm_renewal_of' ) ) {
			return array();
		}
		global $wpdb;
		$made = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$period = (string) $item->get_meta( '_flxcm_period' );
			if ( ! in_array( $period, self::PERIODS, true ) || $item->get_meta( '_flxcm_sub_id' ) ) {
				continue;
			}
			$qty  = max( 1, (int) $item->get_quantity() );
			$meta = $item->get_meta( '_flxcm_sub_meta' );
			$meta = is_array( $meta ) ? $meta : array();
			$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			$wpdb->insert( // phpcs:ignore WordPress.DB
				self::table(),
				array(
					'status'          => 'pending',
					'user_id'         => (int) $order->get_customer_id(),
					'email'           => strtolower( (string) $order->get_billing_email() ),
					'name'            => $name,
					'product_id'      => (int) $item->get_product_id(),
					'product_name'    => $item->get_name(),
					'period'          => $period,
					'amount'          => round( ( (float) $item->get_total() + (float) $item->get_total_tax() ) / $qty, 2 ),
					'currency'        => $order->get_currency(),
					'payment_method'  => $order->get_payment_method(),
					'parent_order_id' => $order->get_id(),
					'last_order_id'   => $order->get_id(),
					'meta'            => wp_json_encode( $meta ),
					'created_at'      => self::now(),
					'updated_at'      => self::now(),
				)
			);
			$id = (int) $wpdb->insert_id;
			if ( ! $id ) {
				continue;
			}
			wc_update_order_item_meta( $item_id, '_flxcm_sub_id', $id );
			$made[] = $id;
			/* translators: %d: subscription id */
			$order->add_order_note( sprintf( __( 'Subscription #%d created; it starts when the payment arrives.', 'flx-commerce' ), $id ) );
		}
		if ( $made ) {
			$order->update_meta_data( '_flxcm_subs', $made );
			$order->save_meta_data();
			if ( $order->is_paid() ) {
				self::order_paid( $order );
			}
		}
		return $made;
	}

	/** Siparişe bağlı abonelik id'leri. */
	public static function ids_of_order( WC_Order $order ) {
		$renew = (int) $order->get_meta( '_flxcm_renewal_of' );
		if ( $renew ) {
			return array( $renew );
		}
		return array_map( 'intval', (array) $order->get_meta( '_flxcm_subs' ) );
	}

	public static function order_paid_id( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::order_paid( $order );
		}
	}

	public static function order_status( $order_id, $from, $to, $order = null ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( in_array( $to, wc_get_is_paid_statuses(), true ) ) {
			// Elle açılmış ya da başka yoldan gelen siparişte satırlar henüz aboneliğe dönmemiş olabilir.
			if ( ! $order->get_meta( '_flxcm_subs' ) && ! $order->get_meta( '_flxcm_renewal_of' ) ) {
				self::from_order( $order );
				return;
			}
			self::order_paid( $order );
			return;
		}
		if ( in_array( $to, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
			self::order_failed( $order, $to );
		}
	}

	/** Ödenen sipariş: ilk sipariş aboneliği başlatır, yenileme dönemi uzatır. Tekrar çağrılması zararsız. */
	public static function order_paid( WC_Order $order ) {
		$renew = (int) $order->get_meta( '_flxcm_renewal_of' );
		foreach ( self::ids_of_order( $order ) as $id ) {
			$row = self::row( $id );
			if ( ! $row ) {
				continue;
			}
			if ( $renew ) {
				self::renewed( $row, $order );
			} elseif ( 'pending' === $row['status'] ) {
				self::started( $row, $order );
			}
		}
	}

	private static function started( array $row, WC_Order $order ) {
		$now = time();
		$end = self::add_period( $now, $row['period'] );
		$sub = self::set_status(
			$row,
			'active',
			array(
				'started_at'     => gmdate( 'Y-m-d H:i:s', $now ),
				'period_end'     => gmdate( 'Y-m-d H:i:s', $end ),
				'anchor_day'     => (int) gmdate( 'j', $now ),
				'last_order_id'  => $order->get_id(),
				'payment_method' => $order->get_payment_method(),
				'user_id'        => (int) $row['user_id'] ? (int) $row['user_id'] : (int) $order->get_customer_id(),
			)
		);
		/* translators: 1: subscription id, 2: date */
		$order->add_order_note( sprintf( __( 'Subscription #%1$d started; next renewal %2$s.', 'flx-commerce' ), (int) $row['id'], flxcm_date( $end ) ) );
		/**
		 * Abonelik başladı (ilk ödeme alındı).
		 *
		 * @param array    $sub   Abonelik (export biçimi).
		 * @param WC_Order $order İlk sipariş.
		 */
		do_action( 'flxcm_subscription_started', $sub, $order );
	}

	private static function renewed( array $row, WC_Order $order ) {
		if ( (int) $order->get_meta( '_flxcm_renewal_done' ) || in_array( $row['status'], array( 'pending' ), true ) ) {
			return;
		}
		// Dönem eskisinin sonundan uzar; çok geç ödendiyse (bitmiş) bugünden başlar.
		$base = max( self::ts( $row['period_end'] ), 'expired' === $row['status'] ? time() : 0 );
		$base = $base ? $base : time();
		$end  = self::add_period( $base, $row['period'], (int) $row['anchor_day'] );
		$sub  = self::set_status(
			$row,
			'active',
			array(
				'period_end'       => gmdate( 'Y-m-d H:i:s', $end ),
				'last_order_id'    => $order->get_id(),
				'renewal_order_id' => 0,
				'renewals'         => (int) $row['renewals'] + 1,
				'payment_method'   => $order->get_payment_method(),
				'cancelled_at'     => null,
				'ended_at'         => null,
				'end_reason'       => '',
			)
		);
		$order->update_meta_data( '_flxcm_renewal_done', time() );
		$order->save_meta_data();
		/* translators: 1: subscription id, 2: date */
		$order->add_order_note( sprintf( __( 'Subscription #%1$d renewed until %2$s.', 'flx-commerce' ), (int) $row['id'], flxcm_date( $end ) ) );
		/**
		 * Abonelik yenilendi (yenileme siparişi ödendi).
		 *
		 * @param array    $sub   Abonelik (export biçimi; period_end yeni bitiş).
		 * @param WC_Order $order Yenileme siparişi.
		 */
		do_action( 'flxcm_subscription_renewed', $sub, $order );
	}

	private static function order_failed( WC_Order $order, $to ) {
		$renew = (int) $order->get_meta( '_flxcm_renewal_of' );
		foreach ( self::ids_of_order( $order ) as $id ) {
			$row = self::row( $id );
			if ( ! $row ) {
				continue;
			}
			if ( $renew ) {
				if ( (int) $row['renewal_order_id'] === $order->get_id() ) {
					self::update( $id, array( 'renewal_order_id' => 0 ) );
				}
				continue;
			}
			if ( 'pending' === $row['status'] ) {
				self::set_status( $row, 'expired', array( 'ended_at' => self::now(), 'end_reason' => 'unpaid' ) );
			} elseif ( 'refunded' === $to && 'expired' !== $row['status'] ) {
				self::end( $row, 'refunded' );
			}
		}
	}

	/**
	 * Aboneliği bitirir (erişim kapanır) ve flxcm_subscription_ended olayını yayar.
	 *
	 * @param string $reason cancelled | unpaid | refunded | admin.
	 */
	public static function end( $row, $reason = 'admin' ) {
		$row = is_array( $row ) ? $row : self::row( (int) $row );
		if ( ! $row || 'expired' === $row['status'] ) {
			return null;
		}
		$was_pending = 'pending' === $row['status'];
		self::cancel_renewal_order( $row );
		$sub = self::set_status(
			$row,
			'expired',
			array(
				'ended_at'         => self::now(),
				'end_reason'       => sanitize_key( $reason ),
				'renewal_order_id' => 0,
			)
		);
		if ( $was_pending ) {
			return $sub;
		}
		self::mail( $sub, 'ended' );
		/**
		 * Abonelik bitti; erişim kapatılmalı.
		 *
		 * @param array  $sub    Abonelik (export biçimi).
		 * @param string $reason cancelled (dönem sonu iptal) | unpaid (ek süre bitti) | refunded | admin.
		 */
		do_action( 'flxcm_subscription_ended', $sub, $sub['end_reason'] );
		return $sub;
	}

	/**
	 * Yenilemeyi kapatır. Varsayılan: dönem sonuna kadar geçerli kalır; $now = true hemen bitirir.
	 *
	 * @param int  $id  Abonelik.
	 * @param bool $now Hemen bitir.
	 * @param string $by customer | admin.
	 */
	public static function cancel( $id, $now = false, $by = 'customer' ) {
		$row = self::row( $id );
		if ( ! $row || in_array( $row['status'], array( 'expired' ), true ) ) {
			return null;
		}
		if ( $now || 'pending' === $row['status'] || self::ts( $row['period_end'] ) <= time() ) {
			return self::end( $row, 'customer' === $by ? 'cancelled' : 'admin' );
		}
		if ( 'cancelled' === $row['status'] ) {
			return self::export( $row );
		}
		self::cancel_renewal_order( $row );
		$sub = self::set_status( $row, 'cancelled', array( 'cancelled_at' => self::now(), 'renewal_order_id' => 0 ) );
		/**
		 * Yenileme kapatıldı; abonelik period_end'e kadar geçerli, sonra flxcm_subscription_ended (cancelled) gelir.
		 *
		 * @param array  $sub Abonelik.
		 * @param string $by  customer | admin.
		 */
		do_action( 'flxcm_subscription_cancelled', $sub, $by );
		return $sub;
	}

	/** İptal edilmiş (dönemi bitmemiş) aboneliğin yenilemesini yeniden açar. */
	public static function resume( $id ) {
		$row = self::row( $id );
		if ( ! $row || 'cancelled' !== $row['status'] || self::ts( $row['period_end'] ) <= time() ) {
			return null;
		}
		$sub = self::set_status( $row, 'active', array( 'cancelled_at' => null ) );
		// İptalde kapatılan yenileme siparişi bir sonraki çalışmada yeniden açılsın.
		self::update_meta( (int) $row['id'], '_renewal_for', null );
		do_action( 'flxcm_subscription_resumed', $sub );
		return $sub;
	}

	private static function cancel_renewal_order( array $row ) {
		$oid = (int) $row['renewal_order_id'];
		if ( ! $oid ) {
			return;
		}
		$o = wc_get_order( $oid );
		if ( $o && ! $o->is_paid() && $o->has_status( array( 'pending', 'on-hold', 'failed' ) ) ) {
			$o->update_status( 'cancelled', __( 'Subscription ended; the renewal order is no longer needed.', 'flx-commerce' ) );
		}
	}

	/* ---------- Yenileme ---------- */

	/** Yenileme siparişinde kullanılacak ödeme yöntemi: Havale/EFT öncelikli, yoksa müşteri ödeme sayfasında seçer. */
	private static function renewal_method( array $row ) {
		$gws  = WC()->payment_gateways() ? WC()->payment_gateways()->get_available_payment_gateways() : array();
		$want = array( (string) $row['payment_method'], FLXCM_Bank::ID, 'flxb_havale', 'bacs' );
		$man  = (array) apply_filters( 'flxcm_subscription_manual_methods', array( FLXCM_Bank::ID, 'flxb_havale', 'bacs' ) );
		foreach ( $want as $id ) {
			if ( '' !== $id && in_array( $id, $man, true ) && isset( $gws[ $id ] ) ) {
				return $gws[ $id ];
			}
		}
		return null;
	}

	/**
	 * Yenileme siparişi açar (dönem başına bir tane). Havale/EFT'de sipariş "Beklemede" + referans kodu,
	 * diğerlerinde "Ödeme bekleniyor" + ödeme bağlantısı.
	 *
	 * @return WC_Order|WP_Error
	 */
	public static function create_renewal( $id, $force = false ) {
		$row = self::row( $id );
		if ( ! $row || ! in_array( $row['status'], array( 'active', 'past_due' ), true ) ) {
			return new WP_Error( 'flxcm_sub_state', __( 'Only an active subscription can be renewed.', 'flx-commerce' ) );
		}
		$open = (int) $row['renewal_order_id'] ? wc_get_order( (int) $row['renewal_order_id'] ) : null;
		if ( $open && ! $open->is_paid() && ! $open->has_status( array( 'cancelled', 'failed', 'refunded' ) ) ) {
			return $open;
		}
		$meta = json_decode( (string) $row['meta'], true );
		$meta = is_array( $meta ) ? $meta : array();
		if ( ! $force && (string) ( $meta['_renewal_for'] ?? '' ) === (string) $row['period_end'] ) {
			return new WP_Error( 'flxcm_sub_done', __( 'A renewal order for this period was already created.', 'flx-commerce' ) );
		}
		$last    = wc_get_order( (int) $row['last_order_id'] );
		$product = wc_get_product( (int) $row['product_id'] );
		$amount  = (float) apply_filters( 'flxcm_subscription_renewal_amount', (float) $row['amount'], self::export( $row ) );
		$order   = wc_create_order(
			array(
				'customer_id' => (int) $row['user_id'],
				'created_via' => 'flxcm_subscription',
			)
		);
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		if ( $last ) {
			$order->set_address( $last->get_address( 'billing' ), 'billing' );
			$order->set_address( $last->get_address( 'shipping' ), 'shipping' );
		} else {
			$order->set_billing_email( $row['email'] );
		}
		$order->set_currency( $row['currency'] ? $row['currency'] : get_woocommerce_currency() );
		$order->set_prices_include_tax( true );
		$item = new WC_Order_Item_Product();
		if ( $product ) {
			$item->set_product( $product );
		}
		$item->set_name( $row['product_name'] );
		$item->set_quantity( 1 );
		$item->set_subtotal( $amount );
		$item->set_total( $amount );
		$item->add_meta_data( '_flxcm_period', $row['period'], true );
		$item->add_meta_data( 'flxcm_period', $row['period'], true );
		$item->add_meta_data( '_flxcm_sub_id', (int) $row['id'], true );
		$order->add_item( $item );
		$order->update_meta_data( '_flxcm_renewal_of', (int) $row['id'] );
		$order->update_meta_data( '_flxcm_renewal_for', (string) $row['period_end'] );
		$order->calculate_totals( false );
		$card = FLXCM_Sub_Cards::card( $row );
		$gw   = $card ? null : self::renewal_method( $row );
		if ( $card ) {
			// Kartla otomatik: dönem bitince kayıtlı karttan çekilir (FLXCM_Sub_Cards).
			$all = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
			$cgw = $all[ $card['gateway'] ] ?? null;
			$order->set_payment_method( (string) $card['gateway'] );
			$order->set_payment_method_title( $cgw ? $cgw->get_title() : (string) $card['label'] );
			$order->update_meta_data( '_flxcm_card_auto', 1 );
		} elseif ( $gw ) {
			$order->set_payment_method( $gw );
		}
		$order->save();
		/* translators: 1: subscription id, 2: date */
		$note = sprintf( __( 'Renewal order for subscription #%1$d (period ends %2$s).', 'flx-commerce' ), (int) $row['id'], flxcm_date( self::ts( $row['period_end'] ) ) );
		if ( $gw && FLXCM_Bank::ID === $gw->id ) {
			FLXCM_Bank::reference( $order );
			$order->update_status( 'on-hold', $note . ' ' . __( 'Bank transfer: awaiting payment.', 'flx-commerce' ) );
			do_action( 'flxcm_bank_placed', $order );
		} else {
			$order->update_status( 'pending', $note );
		}
		$meta['_renewal_for'] = (string) $row['period_end'];
		self::update( (int) $row['id'], array( 'renewal_order_id' => $order->get_id(), 'meta' => wp_json_encode( $meta ) ) );
		$sub = self::export( (int) $row['id'] );
		self::mail( $sub, $card ? 'card_notice' : 'renewal', $order );
		/**
		 * Yenileme siparişi açıldı (ödeme bekleniyor).
		 *
		 * @param array    $sub   Abonelik.
		 * @param WC_Order $order Yenileme siparişi.
		 */
		do_action( 'flxcm_subscription_renewal_created', $sub, $order );
		return $order;
	}

	/** Müşterinin yenileme siparişini ödeyeceği adres: Havale/EFT'de dekont sayfası, diğerlerinde ödeme sayfası. */
	public static function pay_url( WC_Order $order ) {
		if ( FLXCM_Bank::ID === $order->get_payment_method() ) {
			return $order->get_checkout_order_received_url();
		}
		return $order->needs_payment() ? $order->get_checkout_payment_url() : $order->get_view_order_url();
	}

	/** Saatlik iş: yenileme siparişleri, hatırlatmalar, ek süre ve bitişler. Kilitli, parça parça. */
	public static function tick() {
		if ( get_transient( 'flxcm_subs_lock' ) ) {
			return;
		}
		set_transient( 'flxcm_subs_lock', 1, 5 * MINUTE_IN_SECONDS );
		update_option( self::HEARTBEAT, time(), false );
		global $wpdb;
		$t     = self::table();
		$days  = max( 0, (int) flxcm_setting( 'sub_renew_days' ) );
		$grace = max( 0, (int) flxcm_setting( 'sub_grace_days' ) );
		$now   = time();
		$soon  = gmdate( 'Y-m-d H:i:s', $now + $days * DAY_IN_SECONDS );
		$nowg  = gmdate( 'Y-m-d H:i:s', $now );
		// phpcs:disable WordPress.DB
		// 1) Yaklaşan yenilemeler.
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE status IN ('active','past_due') AND renewal_order_id = 0 AND period_end <= %s ORDER BY period_end ASC LIMIT 50", $soon ) ) as $id ) {
			self::create_renewal( (int) $id );
		}
		// 2) Son gün hatırlatması (bir kez).
		$tomorrow = gmdate( 'Y-m-d H:i:s', $now + DAY_IN_SECONDS );
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE status = 'active' AND renewal_order_id > 0 AND period_end <= %s AND period_end > %s LIMIT 50", $tomorrow, $nowg ) ) as $id ) {
			self::last_reminder( (int) $id );
		}
		// 2b) Kartla otomatik yenilemeler: dönem bitince ve tekrar deneme zamanlarında kart çekilir.
		FLXCM_Sub_Cards::run();
		// 3) Dönemi bitenler: iptal edilmişse biter, ödenmemişse ek süreye girer.
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE status IN ('active','cancelled') AND period_end <= %s LIMIT 50", $nowg ), ARRAY_A ) as $row ) {
			if ( 'cancelled' === $row['status'] ) {
				self::end( $row, 'cancelled' );
			} elseif ( $grace > 0 ) {
				$sub = self::set_status( $row, 'past_due' );
				/**
				 * Dönem bitti, yenileme ödenmedi; ek süre (sub_grace_days) başladı. Erişim açık kalır.
				 *
				 * @param array $sub Abonelik.
				 */
				do_action( 'flxcm_subscription_past_due', $sub );
			} else {
				self::end( $row, 'unpaid' );
			}
		}
		// 4) Ek süresi dolanlar.
		$limit = gmdate( 'Y-m-d H:i:s', $now - $grace * DAY_IN_SECONDS );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE status = 'past_due' AND period_end <= %s LIMIT 50", $limit ), ARRAY_A ) as $row ) {
			self::end( $row, 'unpaid' );
		}
		// phpcs:enable WordPress.DB
		delete_transient( 'flxcm_subs_lock' );
	}

	private static function last_reminder( $id ) {
		$row = self::row( $id );
		if ( ! $row ) {
			return;
		}
		$meta = json_decode( (string) $row['meta'], true );
		$meta = is_array( $meta ) ? $meta : array();
		if ( (string) ( $meta['_reminded_for'] ?? '' ) === (string) $row['period_end'] ) {
			return;
		}
		$order = wc_get_order( (int) $row['renewal_order_id'] );
		if ( ! $order || $order->is_paid() || FLXCM_Sub_Cards::auto_pending( $order ) ) {
			return;
		}
		self::update_meta( $id, '_reminded_for', (string) $row['period_end'] );
		self::mail( self::export( $id ), 'renewal', $order );
	}

	/** Gerçek cron çalışmıyorsa (Hostinger) iş, sayfa isteklerinin sonunda saatte bir kez döner. */
	public static function pump() {
		if ( wp_doing_cron() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		if ( time() - (int) get_option( self::HEARTBEAT ) < HOUR_IN_SECONDS || ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return;
		}
		global $wpdb;
		if ( ! $wpdb->get_var( 'SELECT id FROM ' . self::table() . " WHERE status IN ('active','past_due','cancelled') LIMIT 1" ) ) { // phpcs:ignore WordPress.DB
			update_option( self::HEARTBEAT, time(), false );
			return;
		}
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}
		self::tick();
	}

	/* ---------- E-posta ---------- */

	public static function email_types( $types ) {
		$vars = array(
			'ad'     => __( 'First name', 'flx-commerce' ),
			'urun'   => __( 'Product', 'flx-commerce' ),
			'tutar'  => __( 'Amount', 'flx-commerce' ),
			'donem'  => __( 'Billing period', 'flx-commerce' ),
			'bitis'  => __( 'Period end', 'flx-commerce' ),
			'link'   => __( 'Payment link', 'flx-commerce' ),
			'odeme'  => __( 'Bank transfer details', 'flx-commerce' ),
		);
		$sample = array(
			'ad'    => 'Ayşe',
			'urun'  => 'Pro',
			'tutar' => '1.299 ₺',
			'donem' => __( 'Monthly', 'flx-commerce' ),
			'bitis' => flxcm_date( time() + 7 * DAY_IN_SECONDS ),
			'link'  => home_url( '/' ),
			'odeme' => 'IBAN TR00 0000 0000 0000 0000 0000 00 · SP1234',
		);
		$types['flx-commerce/sub-renewal'] = array(
			'label'       => __( 'Subscription renewal reminder', 'flx-commerce' ),
			'group'       => 'Flux Commerce',
			'description' => __( 'Goes to the customer when the renewal order is created and again on the last day if it is unpaid.', 'flx-commerce' ),
			'recipient'   => 'user',
			'vars'        => $vars,
			'sample'      => $sample,
			'defaults'    => array(
				/* translators: {urun}, {bitis} are template variables */
				'subject'     => __( 'Your {urun} subscription renews on {bitis}', 'flx-commerce' ),
				'heading'     => __( 'Time to renew, {ad}', 'flx-commerce' ),
				'body'        => '<p>' . __( 'Your {urun} subscription ({donem}) renews on {bitis}. Amount: {tutar}.', 'flx-commerce' ) . '</p><p>{odeme}</p>',
				'button_text' => __( 'Pay now', 'flx-commerce' ),
				'button_url'  => '{link}',
				'sender'      => 'info',
				'enabled'     => 1,
			),
			'can_disable' => true,
		);
		$types['flx-commerce/sub-card-notice'] = array(
			'label'       => __( 'Subscription renews by card', 'flx-commerce' ),
			'group'       => 'Flux Commerce',
			'description' => __( 'Goes to the customer before a renewal that will be charged to the stored card.', 'flx-commerce' ),
			'recipient'   => 'user',
			'vars'        => $vars + array( 'kart' => __( 'Card', 'flx-commerce' ) ),
			'sample'      => $sample + array( 'kart' => 'Visa •••• 4242' ),
			'defaults'    => array(
				'subject'     => __( 'Your {urun} subscription renews on {bitis}', 'flx-commerce' ),
				'heading'     => __( 'Renewal coming up, {ad}', 'flx-commerce' ),
				'body'        => '<p>' . __( 'Your {urun} subscription ({donem}) renews on {bitis}. {tutar} will be charged to your card {kart}; you do not need to do anything.', 'flx-commerce' ) . '</p><p>' . __( 'To change the card or turn off renewal, go to My account › Subscriptions.', 'flx-commerce' ) . '</p>',
				'button_text' => __( 'My subscriptions', 'flx-commerce' ),
				'button_url'  => '{link}',
				'sender'      => 'info',
				'enabled'     => 1,
			),
			'can_disable' => true,
		);
		$types['flx-commerce/sub-card-failed'] = array(
			'label'       => __( 'Subscription card charge failed', 'flx-commerce' ),
			'group'       => 'Flux Commerce',
			'description' => __( 'Goes to the customer when the stored card could not be charged after the last attempt.', 'flx-commerce' ),
			'recipient'   => 'user',
			'vars'        => $vars + array( 'kart' => __( 'Card', 'flx-commerce' ) ),
			'sample'      => $sample + array( 'kart' => 'Visa •••• 4242' ),
			'defaults'    => array(
				'subject'     => __( 'We could not renew your {urun} subscription', 'flx-commerce' ),
				'heading'     => __( 'Payment needed, {ad}', 'flx-commerce' ),
				'body'        => '<p>' . __( 'We could not charge {tutar} to your card {kart} for your {urun} subscription. Please pay with the button below so the subscription continues.', 'flx-commerce' ) . '</p>',
				'button_text' => __( 'Pay now', 'flx-commerce' ),
				'button_url'  => '{link}',
				'sender'      => 'info',
				'enabled'     => 1,
			),
			'can_disable' => true,
		);
		$types['flx-commerce/sub-ended'] = array(
			'label'       => __( 'Subscription ended', 'flx-commerce' ),
			'group'       => 'Flux Commerce',
			'description' => __( 'Goes to the customer when a subscription ends.', 'flx-commerce' ),
			'recipient'   => 'user',
			'vars'        => $vars,
			'sample'      => $sample,
			'defaults'    => array(
				'subject'     => __( 'Your {urun} subscription has ended', 'flx-commerce' ),
				'heading'     => __( 'Your subscription has ended', 'flx-commerce' ),
				'body'        => '<p>' . __( 'Hello {ad}, your {urun} subscription has ended. You can start it again at any time.', 'flx-commerce' ) . '</p>',
				'button_text' => __( 'Start again', 'flx-commerce' ),
				'button_url'  => '{link}',
				'sender'      => 'info',
				'enabled'     => 1,
			),
			'can_disable' => true,
		);
		return $types;
	}

	public static function period_label( $period, $adverb = true ) {
		if ( $adverb ) {
			return 'year' === $period ? __( 'Yearly', 'flx-commerce' ) : __( 'Monthly', 'flx-commerce' );
		}
		return 'year' === $period ? __( 'year', 'flx-commerce' ) : __( 'month', 'flx-commerce' );
	}

	/**
	 * Müşteriye e-posta (Flux Mail şablonu, yoksa wp_mail düz metin).
	 *
	 * @param string $kind renewal | ended | card_notice | card_failed.
	 */
	public static function mail( $sub, $kind, $order = null ) {
		if ( ! $sub || ! is_email( $sub['email'] ) || ! apply_filters( 'flxcm_subscription_send_mail', true, $kind, $sub, $order ) ) {
			return false;
		}
		$first = $order instanceof WC_Order ? $order->get_billing_first_name() : '';
		$first = '' !== $first ? $first : strtok( (string) $sub['name'], ' ' );
		$bank  = '';
		if ( $order instanceof WC_Order && FLXCM_Bank::ID === $order->get_payment_method() ) {
			$bank = sprintf(
				/* translators: 1: holder, 2: IBAN, 3: reference */
				__( 'Recipient: %1$s · IBAN: %2$s · Reference: %3$s', 'flx-commerce' ),
				FLXCM_Bank::option( 'holder' ),
				FLXCM_Bank::format_iban( FLXCM_Bank::option( 'iban' ) ),
				FLXCM_Bank::reference( $order )
			);
		}
		$link = 'ended' === $kind ? ( $sub['product_id'] ? get_permalink( $sub['product_id'] ) : home_url( '/' ) ) : ( $order instanceof WC_Order && 'card_notice' !== $kind ? self::pay_url( $order ) : wc_get_account_endpoint_url( FLXCM_Sub_Account::ENDPOINT ) );
		$vars = array(
			'ad'    => (string) $first,
			'urun'  => (string) $sub['product_name'],
			'tutar' => flxcm_money( $order instanceof WC_Order ? $order->get_total() : $sub['amount'], $sub['currency'] ),
			'donem' => self::period_label( $sub['period'] ),
			'bitis' => flxcm_date( $sub['period_end'] ),
			'link'  => (string) $link,
			'odeme' => $bank,
			'kart'  => (string) $sub['card'],
		);
		$tpl  = array(
			'ended'       => 'sub-ended',
			'card_notice' => 'sub-card-notice',
			'card_failed' => 'sub-card-failed',
		);
		if ( function_exists( 'flxi_send_email' ) ) {
			return (bool) flxi_send_email(
				array(
					'to'       => $sub['email'],
					'template' => 'flx-commerce/' . ( $tpl[ $kind ] ?? 'sub-renewal' ),
					'vars'     => $vars,
				)
			);
		}
		if ( 'ended' === $kind ) {
			/* translators: %s: product */
			$subject = sprintf( __( 'Your %s subscription has ended', 'flx-commerce' ), $vars['urun'] );
			/* translators: 1: first name, 2: product, 3: link */
			$body = sprintf( __( 'Hello %1$s, your %2$s subscription has ended. You can start it again at any time: %3$s', 'flx-commerce' ), $vars['ad'], $vars['urun'], $vars['link'] );
		} elseif ( 'card_notice' === $kind ) {
			/* translators: 1: product, 2: date */
			$subject = sprintf( __( 'Your %1$s subscription renews on %2$s', 'flx-commerce' ), $vars['urun'], $vars['bitis'] );
			/* translators: 1: first name, 2: product, 3: date, 4: amount, 5: card, 6: link */
			$body = sprintf( __( 'Hello %1$s, your %2$s subscription renews on %3$s. %4$s will be charged to your card %5$s; you do not need to do anything. Manage it here: %6$s', 'flx-commerce' ), $vars['ad'], $vars['urun'], $vars['bitis'], $vars['tutar'], $vars['kart'], $vars['link'] );
		} elseif ( 'card_failed' === $kind ) {
			/* translators: %s: product */
			$subject = sprintf( __( 'We could not renew your %s subscription', 'flx-commerce' ), $vars['urun'] );
			/* translators: 1: first name, 2: amount, 3: card, 4: product, 5: link */
			$body = sprintf( __( 'Hello %1$s, we could not charge %2$s to your card %3$s for your %4$s subscription. Please pay here so it continues: %5$s', 'flx-commerce' ), $vars['ad'], $vars['tutar'], $vars['kart'], $vars['urun'], $vars['link'] );
		} else {
			/* translators: 1: product, 2: date */
			$subject = sprintf( __( 'Your %1$s subscription renews on %2$s', 'flx-commerce' ), $vars['urun'], $vars['bitis'] );
			/* translators: 1: first name, 2: product, 3: period, 4: date, 5: amount, 6: link */
			$body = sprintf( __( 'Hello %1$s, your %2$s subscription (%3$s) renews on %4$s. Amount: %5$s. Pay here: %6$s', 'flx-commerce' ), $vars['ad'], $vars['urun'], $vars['donem'], $vars['bitis'], $vars['tutar'], $vars['link'] );
			if ( '' !== $bank ) {
				$body .= "\n\n" . $bank;
			}
		}
		return wp_mail( $sub['email'], $subject, $body );
	}
}

/* ---------- Genel API (başka eklentiler: Flux Cloud vb.) ---------- */

/** @return array|null Abonelik (export biçimi). */
function flxcm_get_subscription( $id ) {
	return FLXCM_Subscriptions::export( (int) $id );
}

/**
 * @param array $args status, user_id, email, product_id, s, paged, per_page.
 * @return array[] Abonelikler (export biçimi).
 */
function flxcm_get_subscriptions( array $args = array() ) {
	$q = FLXCM_Subscriptions::query( $args );
	return array_values( array_filter( array_map( array( 'FLXCM_Subscriptions', 'export' ), $q['rows'] ) ) );
}

/** Kullanıcının erişimi açık abonelikleri (isteğe bağlı ürünle sınırlı). */
function flxcm_user_subscriptions( $user_id = 0, $product_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}
	$u    = get_userdata( $user_id );
	$args = array( 'user_id' => $user_id, 'email' => $u ? $u->user_email : '', 'per_page' => 200 );
	if ( $product_id ) {
		$args['product_id'] = (int) $product_id;
	}
	return array_values( array_filter( flxcm_get_subscriptions( $args ), static function ( $s ) { return $s['has_access']; } ) );
}

function flxcm_cancel_subscription( $id, $immediately = false ) {
	return FLXCM_Subscriptions::cancel( (int) $id, (bool) $immediately, 'admin' );
}

function flxcm_update_subscription_meta( $id, $key, $value ) {
	return FLXCM_Subscriptions::update_meta( (int) $id, sanitize_key( $key ), $value );
}
