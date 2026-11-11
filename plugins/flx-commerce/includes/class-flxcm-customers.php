<?php
/**
 * Müşteriler: e-posta başına özet (sipariş sayısı, toplam harcama, ilk/son sipariş, not). Kayıtlı ve
 * misafir müşteriler birlikte. Sipariş durumu değişince e-posta tek bir seçenekte kirli
 * işaretlenir; satır yanıt gittikten sonra ya da günlük işte yeniden hesaplanır. Tek müşteri
 * okunurken kirliyse hemen hesaplanır. "Yeniden oluştur" tüm siparişleri 100'lük parçalarla tarar.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Customers {

	const BATCH = 100;

	/** Kirli e-postalar. Anahtar e-posta, değer 1. Olay başına satır yok. */
	const DIRTY = 'flxcm_customers_dirty';

	public static function init() {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status' ), 20, 4 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'on_refund' ), 20 );
		add_action( 'flxcm_daily', array( __CLASS__, 'flush_dirty' ) );
		add_action( 'wp_ajax_flxcm_rebuild', array( __CLASS__, 'ajax_rebuild' ) );
		add_action( 'wp_ajax_flxcm_note', array( __CLASS__, 'ajax_note' ) );
		add_action( 'admin_post_flxcm_customers_csv', array( __CLASS__, 'csv' ) );
	}

	public static function on_status( $order_id, $from, $to, $order = null ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( $order ) {
			self::mark_dirty( $order->get_billing_email() );
		}
	}

	public static function on_refund( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::mark_dirty( $order->get_billing_email() );
		}
	}

	/** E-postayı kirli işaretler. Aynı e-posta ikinci kez satır açmaz. */
	public static function mark_dirty( $email ) {
		self::edit_dirty( $email, true );
		// Yalnız bu istekte kirli işaret yazıldıysa istek sonunda hesaplanır; her sayfa isteğinde seçenek okunmaz.
		if ( ! has_action( 'shutdown', array( __CLASS__, 'flush_dirty' ) ) ) {
			add_action( 'shutdown', array( __CLASS__, 'flush_dirty' ), PHP_INT_MAX );
		}
	}

	private static function edit_dirty( $email, $add ) {
		$email = strtolower( sanitize_email( (string) $email ) );
		if ( '' === $email ) {
			return;
		}
		for ( $try = 0; $try < 3; $try++ ) {
			$list = get_option( self::DIRTY, array() );
			if ( ! is_array( $list ) ) {
				$list = array();
			}
			$has = isset( $list[ $email ] );
			if ( $add && $has ) {
				return;
			}
			if ( ! $add && ! $has ) {
				return;
			}
			if ( $add ) {
				$list[ $email ] = 1;
			} else {
				unset( $list[ $email ] );
			}
			if ( $list ) {
				if ( false === get_option( self::DIRTY, false ) ) {
					add_option( self::DIRTY, $list, '', false );
				} else {
					update_option( self::DIRTY, $list, false );
				}
			} else {
				delete_option( self::DIRTY );
			}
			$now = get_option( self::DIRTY, array() );
			$seen = is_array( $now ) && isset( $now[ $email ] );
			if ( $add === $seen ) {
				return;
			}
		}
	}

	private static function is_dirty( $email ) {
		$email = strtolower( sanitize_email( (string) $email ) );
		$list  = get_option( self::DIRTY, array() );
		return '' !== $email && is_array( $list ) && isset( $list[ $email ] );
	}

	/**
	 * Kirli müşterileri hesaplar. HTTP isteğinde önce bağlantıyı kapatır.
	 * 20 saniyede bitmeyenler seçenekte kalır; günlük iş onları alır.
	 */
	public static function flush_dirty() {
		$list = get_option( self::DIRTY, array() );
		if ( ! is_array( $list ) || ! $list ) {
			return;
		}
		if ( doing_action( 'shutdown' ) ) {
			if ( function_exists( 'litespeed_finish_request' ) ) {
				litespeed_finish_request();
			} elseif ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			}
		}
		$deadline = microtime( true ) + 20;
		foreach ( array_keys( $list ) as $email ) {
			if ( microtime( true ) >= $deadline ) {
				break;
			}
			self::recompute( $email );
		}
	}

	/** Bir müşterinin satırını siparişlerinden baştan hesaplar. */
	public static function recompute( $email ) {
		global $wpdb;
		$email = strtolower( sanitize_email( (string) $email ) );
		if ( '' === $email ) {
			return;
		}
		$ids = flxcm_order_ids(
			array(
				'type'          => 'shop_order',
				'billing_email' => $email,
				'status'        => array_map( static function ( $s ) { return 'wc-' . $s; }, flxcm_paid_statuses() ),
				'orderby'       => 'date',
				'order'         => 'ASC',
			)
		);
		$row = null;
		foreach ( (array) $ids as $oid ) {
			$o = wc_get_order( $oid );
			if ( $o && flxcm_count_order( $o ) ) {
				$row = self::add( $row, $o );
			}
		}
		$t = FLXCM_Install::table();
		if ( ! $row ) {
			// Hiç sayılan siparişi kalmadıysa notu korunarak sıfırlanır.
			$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET orders = 0, total = 0, updated_at = %s WHERE email = %s", current_time( 'mysql', true ), $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			self::edit_dirty( $email, false );
			return;
		}
		self::write( $row );
		self::edit_dirty( $email, false );
	}

	/** Satır özetine bir sipariş ekler. */
	private static function add( $row, WC_Order $o ) {
		$created = $o->get_date_created() ? gmdate( 'Y-m-d H:i:s', $o->get_date_created()->getTimestamp() ) : current_time( 'mysql', true );
		$net     = (float) $o->get_total() - (float) $o->get_total_refunded();
		if ( ! $row ) {
			$row = array(
				'email'       => strtolower( (string) $o->get_billing_email() ),
				'user_id'     => (int) $o->get_customer_id(),
				'orders'      => 0,
				'total'       => 0.0,
				'first_order' => $o->get_id(),
				'first_at'    => $created,
			);
		}
		++$row['orders'];
		$row['total']     += $net;
		$row['user_id']    = $o->get_customer_id() ? (int) $o->get_customer_id() : $row['user_id'];
		$row['name']       = trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() );
		$row['phone']      = (string) $o->get_billing_phone();
		$row['city']       = (string) $o->get_billing_city();
		$row['last_order'] = $o->get_id();
		$row['last_at']    = $created;
		if ( $created < $row['first_at'] ) {
			$row['first_at']    = $created;
			$row['first_order'] = $o->get_id();
		}
		return $row;
	}

	private static function write( array $row, $accumulate = false ) {
		global $wpdb;
		$t   = FLXCM_Install::table();
		$now = current_time( 'mysql', true );
		$old = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE email = %s", $row['email'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $old && $accumulate ) {
			$row['orders'] += (int) $old['orders'];
			$row['total']  += (float) $old['total'];
			if ( $old['first_at'] && $old['first_at'] < $row['first_at'] ) {
				$row['first_at']    = $old['first_at'];
				$row['first_order'] = (int) $old['first_order'];
			}
			if ( $old['last_at'] && $old['last_at'] > $row['last_at'] ) {
				foreach ( array( 'last_at', 'last_order', 'name', 'phone', 'city' ) as $k ) {
					$row[ $k ] = $old[ $k ];
				}
			}
		}
		$data = array(
			'email'       => $row['email'],
			'user_id'     => (int) $row['user_id'],
			'name'        => mb_substr( (string) $row['name'], 0, 190 ),
			'phone'       => mb_substr( (string) $row['phone'], 0, 40 ),
			'city'        => mb_substr( (string) $row['city'], 0, 100 ),
			'orders'      => (int) $row['orders'],
			'total'       => round( (float) $row['total'], 2 ),
			'first_order' => (int) $row['first_order'],
			'last_order'  => (int) $row['last_order'],
			'first_at'    => $row['first_at'],
			'last_at'     => $row['last_at'],
			'updated_at'  => $now,
		);
		if ( $old ) {
			$wpdb->update( $t, $data, array( 'id' => (int) $old['id'] ) );
		} else {
			$wpdb->insert( $t, $data );
		}
		self::person( $data );
	}

	/**
	 * The customer is a person of the shared FluxWP people table (flx-brand): found by the `commerce / email:`
	 * link (the key Flux CRM's WooCommerce source uses), user, e-mail or phone, or created. This table only keeps
	 * the order summary; who the person is lives once, in the shared table.
	 */
	private static function person( array $d ) {
		if ( ! function_exists( 'flx_person_ensure' ) || '' === (string) $d['email'] ) {
			return 0;
		}
		return (int) flx_person_ensure(
			array(
				'name'    => $d['name'],
				'email'   => $d['email'],
				'phone'   => $d['phone'],
				'user_id' => (int) $d['user_id'],
				'city'    => $d['city'],
				'source'  => 'commerce',
				'link'    => array( 'commerce', 'email:' . strtolower( (string) $d['email'] ) ),
				'at'      => $d['first_at'],
			)
		);
	}

	/**
	 * Tüm siparişlerden yeniden oluşturma, bir parça. Sayfa 1 tabloyu boşaltır (notlar korunur).
	 *
	 * @return array { done, page, scanned, total }
	 */
	public static function rebuild_step( $page ) {
		global $wpdb;
		$page = max( 1, (int) $page );
		$t    = FLXCM_Install::table();
		if ( 1 === $page ) {
			$wpdb->query( "UPDATE {$t} SET orders = 0, total = 0, first_at = NULL, last_at = NULL, first_order = 0, last_order = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$res = wc_get_orders(
			array(
				'type'     => 'shop_order',
				'status'   => array_map( static function ( $s ) { return 'wc-' . $s; }, flxcm_paid_statuses() ),
				'limit'    => self::BATCH,
				'paged'    => $page,
				'orderby'  => 'ID',
				'order'    => 'ASC',
				'paginate' => true,
			)
		);
		$rows = array();
		foreach ( (array) $res->orders as $o ) {
			$email = strtolower( (string) $o->get_billing_email() );
			if ( '' === $email || ! flxcm_count_order( $o ) ) {
				continue;
			}
			$rows[ $email ] = self::add( $rows[ $email ] ?? null, $o );
		}
		foreach ( $rows as $row ) {
			self::write( $row, true );
		}
		$done = $page >= (int) $res->max_num_pages;
		if ( $done ) {
			$wpdb->query( "DELETE FROM {$t} WHERE orders = 0 AND (note IS NULL OR note = '')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			update_option( 'flxcm_customers_built', time(), false );
			FLXCM_Stats::bump();
		}
		return array(
			'done'    => $done,
			'page'    => $page,
			'scanned' => min( (int) $res->total, $page * self::BATCH ),
			'total'   => (int) $res->total,
		);
	}

	public static function ajax_rebuild() {
		check_ajax_referer( 'flxcm_admin' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'flx-commerce' ) ), 403 );
		}
		wp_send_json_success( self::rebuild_step( absint( $_POST['page'] ?? 1 ) ) );
	}

	public static function ajax_note() {
		global $wpdb;
		check_ajax_referer( 'flxcm_admin' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'flx-commerce' ) ), 403 );
		}
		$id   = absint( $_POST['id'] ?? 0 );
		$note = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
		$wpdb->update( FLXCM_Install::table(), array( 'note' => mb_substr( $note, 0, 2000 ) ), array( 'id' => $id ) );
		wp_send_json_success( array( 'note' => $note ) );
	}

	/**
	 * Liste sorgusu.
	 *
	 * @param array $a { s, orderby (last_at|total|orders|name), order, paged, per_page, segment (all|repeat|vip|lapsed|new) }
	 */
	public static function query( array $a ) {
		global $wpdb;
		$t     = FLXCM_Install::table();
		$where = array( 'orders > 0' );
		$args  = array();
		$s     = trim( (string) ( $a['s'] ?? '' ) );
		if ( '' !== $s ) {
			$like    = '%' . $wpdb->esc_like( $s ) . '%';
			$where[] = '(email LIKE %s OR name LIKE %s OR phone LIKE %s OR city LIKE %s)';
			array_push( $args, $like, $like, $like, $like );
		}
		switch ( (string) ( $a['segment'] ?? 'all' ) ) {
			case 'repeat':
				$where[] = 'orders >= 2';
				break;
			case 'vip':
				$where[] = 'total >= %f';
				$args[]  = self::vip_threshold();
				break;
			case 'lapsed':
				$where[] = 'last_at < %s';
				$args[]  = gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS );
				break;
			case 'new':
				$where[] = 'first_at >= %s';
				$args[]  = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
				break;
		}
		$cols    = array( 'last_at', 'total', 'orders', 'name', 'first_at' );
		$orderby = in_array( $a['orderby'] ?? '', $cols, true ) ? $a['orderby'] : 'last_at';
		$order   = 'asc' === strtolower( (string) ( $a['order'] ?? '' ) ) ? 'ASC' : 'DESC';
		$per     = max( 1, min( 200, (int) ( $a['per_page'] ?? 25 ) ) );
		$paged   = max( 1, (int) ( $a['paged'] ?? 1 ) );
		$w       = implode( ' AND ', $where );
		$sql_c   = "SELECT COUNT(*) FROM {$t} WHERE {$w}";
		$sql     = "SELECT * FROM {$t} WHERE {$w} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d";
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $sql_c, $args ) ) : $wpdb->get_var( $sql_c ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( $per, ( $paged - 1 ) * $per ) ) ), ARRAY_A );
		// phpcs:enable
		return array(
			'rows'  => (array) $rows,
			'total' => $total,
			'pages' => (int) ceil( $total / $per ),
			'paged' => $paged,
		);
	}

	/** Tek müşteri satırı. */
	public static function get( $id ) {
		global $wpdb;
		$t   = FLXCM_Install::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $row && self::is_dirty( $row['email'] ) ) {
			self::recompute( $row['email'] );
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return $row ? $row : null;
	}

	/** E-postaya göre müşteri satırı. */
	public static function by_email( $email ) {
		global $wpdb;
		$email = strtolower( trim( (string) $email ) );
		if ( '' === $email ) {
			return null;
		}
		if ( self::is_dirty( $email ) ) {
			self::recompute( $email );
		}
		$t   = FLXCM_Install::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE email = %s", $email ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/**
	 * Müşterinin siparişleri, yeniden eskiye (tüm durumlar; mağaza istatistiğine girmeyen bağış siparişleri hariç).
	 *
	 * @return WC_Order[]
	 */
	public static function orders( array $row, $limit = 60 ) {
		if ( ! flxcm_wc_ready() ) {
			return array();
		}
		$who = array_values( array_filter( array( (string) $row['email'], (int) $row['user_id'] ) ) );
		if ( ! $who ) {
			return array();
		}
		$list = wc_get_orders(
			array(
				'customer' => $who,
				'type'     => 'shop_order',
				'limit'    => (int) $limit,
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);
		return array_values(
			array_filter(
				(array) $list,
				static function ( $o ) {
					return $o instanceof WC_Order && flxcm_count_order( $o );
				}
			)
		);
	}

	/** VIP eşiği: harcaması en yüksek %10'un alt sınırı (en az 1). Filtre `flxcm_vip_threshold`. */
	public static function vip_threshold() {
		global $wpdb;
		$t   = FLXCM_Install::table();
		$n   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE orders > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$off = max( 0, (int) floor( $n * 0.1 ) - 1 );
		$v   = $n ? (float) $wpdb->get_var( $wpdb->prepare( "SELECT total FROM {$t} WHERE orders > 0 ORDER BY total DESC LIMIT 1 OFFSET %d", $off ) ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (float) apply_filters( 'flxcm_vip_threshold', max( 1, $v ) );
	}

	public static function counts() {
		global $wpdb;
		$t = FLXCM_Install::table();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$r = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS n, SUM(orders >= 2) AS rep, SUM(total) AS spent, SUM(last_at < %s) AS lapsed, SUM(first_at >= %s) AS fresh FROM {$t} WHERE orders > 0",
				gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ),
				gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS )
			),
			ARRAY_A
		);
		$vip = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE orders > 0 AND total >= %f", self::vip_threshold() ) );
		// phpcs:enable
		$n = (int) ( $r['n'] ?? 0 );
		return array(
			'all'    => $n,
			'repeat' => (int) ( $r['rep'] ?? 0 ),
			'vip'    => $n ? $vip : 0,
			'lapsed' => (int) ( $r['lapsed'] ?? 0 ),
			'new'    => (int) ( $r['fresh'] ?? 0 ),
			'spent'  => (float) ( $r['spent'] ?? 0 ),
			'ltv'    => $n ? (float) $r['spent'] / $n : 0.0,
		);
	}

	public static function csv() {
		check_admin_referer( 'flxcm_customers_csv' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-commerce' ), 403 );
		}
		$q = self::query(
			array(
				's'        => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
				'segment'  => sanitize_key( $_GET['segment'] ?? 'all' ),
				'orderby'  => 'total',
				'per_page' => 200,
				'paged'    => 1,
			)
		);
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="customers-' . wp_date( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, array( __( 'Name', 'flx-commerce' ), __( 'E-mail', 'flx-commerce' ), __( 'Phone', 'flx-commerce' ), __( 'City', 'flx-commerce' ), __( 'Orders', 'flx-commerce' ), __( 'Total spent', 'flx-commerce' ), __( 'First order', 'flx-commerce' ), __( 'Last order', 'flx-commerce' ), __( 'Note', 'flx-commerce' ) ) );
		$page = 1;
		do {
			foreach ( $q['rows'] as $r ) {
				fputcsv(
					$out,
					array(
						self::csv_cell( $r['name'] ),
						self::csv_cell( $r['email'] ),
						self::csv_cell( $r['phone'] ),
						self::csv_cell( $r['city'] ),
						(int) $r['orders'],
						wc_format_decimal( $r['total'], 2 ),
						$r['first_at'] ? get_date_from_gmt( $r['first_at'], 'Y-m-d' ) : '',
						$r['last_at'] ? get_date_from_gmt( $r['last_at'], 'Y-m-d' ) : '',
						self::csv_cell( (string) $r['note'] ),
					)
				);
			}
			++$page;
			if ( $page > $q['pages'] ) {
				break;
			}
			$q = self::query(
				array(
					's'        => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
					'segment'  => sanitize_key( $_GET['segment'] ?? 'all' ),
					'orderby'  => 'total',
					'per_page' => 200,
					'paged'    => $page,
				)
			);
		} while ( true );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/** Excel formül enjeksiyonuna karşı (=, +, -, @ ile başlayan hücreler). */
	public static function csv_cell( $v ) {
		$v = (string) $v;
		return '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $v : $v;
	}
}
