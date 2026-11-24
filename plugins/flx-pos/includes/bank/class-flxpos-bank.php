<?php
/**
 * Havale / EFT hareketleri: ekstre yükleme, tekrar kayıt engeli, arka planda sipariş/bağış eşleştirme.
 *
 *  - Kaynaklar: elle yüklenen ekstre (upload) ve canlı kaynaklar (filtre `flxpos_bank_sources`; her biri
 *    FLXPOS_Bank_Source). Canlı kaynaklar saatlik çekilir.
 *  - Her hareket parmak iziyle (fp) bir kez yazılır: aynı ya da çakışan dönemli ekstre yeniden yüklenirse
 *    önceki hareketler atlanır.
 *  - Eşleştirme arka planda çalışır (Action Scheduler, yoksa WP-Cron): giren tutar, bekleyen siparişlerle
 *    tutar + referans kodu + gönderen adına göre puanlanır. Varsayılan: öneri; ayar açıksa kesin eşleşmeler
 *    (kod + tutar, tek aday) otomatik onaylanır.
 *  - Filtreler: flxpos_bank_methods (eşleşecek ödeme yöntemleri), flxpos_bank_codes (siparişin referans
 *    kodları), flxpos_bank_candidates (aday listesi), flxpos_bank_confirm (onayı devralma).
 *    Eylemler: flxpos_bank_imported, flxpos_bank_matched.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

/** Canlı hareket kaynağı (banka API'si, açık bankacılık…). */
abstract class FLXPOS_Bank_Source {

	/** @var string */
	public $id = '';

	/** @var string */
	public $name = '';

	/** Ayar ekranı alanları: anahtar => array( label, type ). */
	public function fields() {
		return array();
	}

	/**
	 * Hareketleri getir.
	 *
	 * @param string $since Y-m-d.
	 * @param array  $creds Kayıtlı ayarlar.
	 * @return array|WP_Error FLXPOS_Bank_Layouts::parse() items biçiminde liste.
	 */
	abstract public function fetch( $since, array $creds );
}

final class FLXPOS_Bank {

	const DB      = '1';
	const HOOK    = 'flxpos_bank_match';
	const BATCH   = 100;
	const NEW     = 'new';
	const SUGGEST = 'suggested';
	const MATCHED = 'matched';
	const IGNORED = 'ignored';
	const NOMATCH = 'nomatch'; // denendi, aday yok; günlük iş yeniden dener
	const OUT     = 'out';

	public static function init() {
		self::maybe_install();
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'flxpos_bank_pull', array( __CLASS__, 'pull' ) );
		add_action( 'flxpos_daily', array( __CLASS__, 'daily' ) );
		if ( self::sources() && ! wp_next_scheduled( 'flxpos_bank_pull' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'flxpos_bank_pull' );
		}
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'flxpos_bank';
	}

	public static function maybe_install() {
		if ( self::DB !== get_option( 'flxpos_bank_db' ) ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		dbDelta(
			"CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			fp char(40) NOT NULL DEFAULT '',
			source varchar(40) NOT NULL DEFAULT 'upload',
			bank varchar(40) NOT NULL DEFAULT '',
			account varchar(64) NOT NULL DEFAULT '',
			tdate date NOT NULL DEFAULT '1970-01-01',
			ttime varchar(5) NOT NULL DEFAULT '',
			amount decimal(14,2) NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT 'TRY',
			description text NULL,
			ref varchar(100) NOT NULL DEFAULT '',
			balance decimal(14,2) NULL,
			file varchar(190) NOT NULL DEFAULT '',
			status varchar(16) NOT NULL DEFAULT 'new',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			score smallint(5) unsigned NOT NULL DEFAULT 0,
			cands varchar(255) NOT NULL DEFAULT '',
			matched_by bigint(20) unsigned NOT NULL DEFAULT 0,
			matched_at datetime NULL,
			created datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY fp (fp),
			KEY status (status),
			KEY tdate (tdate),
			KEY order_id (order_id)
			) " . $wpdb->get_charset_collate() . ';'
		);
		update_option( 'flxpos_bank_db', self::DB, true );
	}

	public static function status_labels() {
		return array(
			self::NEW     => __( 'Sırada', 'flx-pos' ),
			self::NOMATCH => __( 'Eşleşmedi', 'flx-pos' ),
			self::SUGGEST => __( 'Öneri var', 'flx-pos' ),
			self::MATCHED => __( 'Eşleşti', 'flx-pos' ),
			self::IGNORED => __( 'Yoksayıldı', 'flx-pos' ),
			self::OUT     => __( 'Çıkan', 'flx-pos' ),
		);
	}

	/** Eşleşecek ödeme yöntemleri. */
	public static function methods() {
		return array_values( array_unique( (array) apply_filters( 'flxpos_bank_methods', array( 'bacs' ) ) ) );
	}

	/* ---------------- İçe aktarma ---------------- */

	/**
	 * Dosyayı oku ve yaz.
	 *
	 * @return array|WP_Error array( bank, added, skipped, total, out )
	 */
	public static function import_file( $path, $name, $bank = '', $account = '' ) {
		$rows = FLXPOS_Sheet::read( $path, $name );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$p = FLXPOS_Bank_Layouts::parse( $rows, $bank );
		if ( is_wp_error( $p ) ) {
			return $p;
		}
		if ( ! $p['items'] ) {
			return new WP_Error( 'flxpos_bank_empty', __( 'Dosyada hareket bulunamadı.', 'flx-pos' ) );
		}
		return self::import( $p['items'], 'upload', $p['bank'], $account, $name );
	}

	/** Hareketleri yaz (tekrarları atla) ve eşleştirmeyi arka planda başlat. */
	public static function import( array $items, $source, $bank, $account = '', $file = '' ) {
		global $wpdb;
		$t     = self::table();
		$seen  = array();
		$added = 0;
		$skip  = 0;
		$out   = 0;
		$now   = current_time( 'mysql', true );
		foreach ( $items as $it ) {
			$key           = implode( '|', array( $bank, $it['date'], $it['time'], number_format( (float) $it['amount'], 2, '.', '' ), null === $it['balance'] ? '' : number_format( (float) $it['balance'], 2, '.', '' ), $it['ref'], FLXPOS_Bank_Layouts::plain( $it['desc'] ) ) );
			$seen[ $key ]  = ( $seen[ $key ] ?? 0 ) + 1;
			$fp            = sha1( $key . '#' . $seen[ $key ] );
			$st            = $it['amount'] > 0 ? self::NEW : self::OUT;
			$vals          = array( $fp, sanitize_key( $source ), sanitize_key( $bank ), substr( sanitize_text_field( $account ), 0, 64 ), $it['date'], $it['time'], (float) $it['amount'], $it['currency'], sanitize_text_field( $it['desc'] ), sanitize_text_field( $it['ref'] ), substr( sanitize_file_name( $file ), 0, 190 ), $st, $now );
			$bal_sql       = '%f';
			if ( null === $it['balance'] ) {
				$bal_sql = 'NULL';
			} else {
				$vals[] = (float) $it['balance'];
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablo adı ve sabit yer tutucu
			$ok = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t} (fp, source, bank, account, tdate, ttime, amount, currency, description, ref, file, status, created, balance) VALUES (%s, %s, %s, %s, %s, %s, %f, %s, %s, %s, %s, %s, %s, {$bal_sql})", $vals ) );
			if ( $ok ) {
				++$added;
				if ( self::OUT === $st ) {
					++$out;
				}
			} else {
				++$skip;
			}
		}
		$res = array(
			'bank'    => $bank,
			'added'   => $added,
			'skipped' => $skip,
			'total'   => count( $items ),
			'out'     => $out,
		);
		do_action( 'flxpos_bank_imported', $res, $source );
		if ( $added > $out ) {
			self::schedule();
		}
		return $res;
	}

	/** Eşleştirmeyi arka planda başlat. */
	public static function schedule() {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			if ( ! function_exists( 'as_has_scheduled_action' ) || ! as_has_scheduled_action( self::HOOK, array(), 'flx-pos' ) ) {
				as_enqueue_async_action( self::HOOK, array(), 'flx-pos' );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time(), self::HOOK );
		}
	}

	/* ---------------- Eşleştirme ---------------- */

	/** Arka plan işi: yeni hareketleri puanla; kalan varsa yeniden kuyruğa al. */
	public static function run() {
		global $wpdb;
		$t    = self::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE status = %s ORDER BY id ASC LIMIT %d", self::NEW, self::BATCH ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $r ) {
			self::match_row( $r );
		}
		if ( count( (array) $rows ) >= self::BATCH ) {
			self::schedule();
		}
	}

	/** Eşleşmemiş/önerili son 60 günü yeniden dene (yeni siparişler için). */
	public static function daily() {
		global $wpdb;
		$t = self::table();
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = %s WHERE status IN (%s, %s) AND tdate >= %s", self::NEW, self::SUGGEST, self::NOMATCH, gmdate( 'Y-m-d', time() - 60 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE status = %s", self::NEW ) ); // phpcs:ignore WordPress.DB
		if ( $n ) {
			self::schedule();
		}
	}

	/** Bir hareketi puanla, öneriyi yaz; kesin ve ayar açıksa onayla. */
	public static function match_row( array $r ) {
		$c   = self::candidates( $r );
		$top = $c[0] ?? null;
		$sec = $c[1] ?? null;
		$upd = array(
			'status' => $top && $top['score'] >= 40 ? self::SUGGEST : self::NOMATCH,
			'score'  => $top ? (int) $top['score'] : 0,
			'cands'  => wp_json_encode( array_map( static fn( $x ) => array( $x['id'], $x['score'] ), array_slice( $c, 0, 5 ) ) ),
		);
		self::update( (int) $r['id'], $upd );
		$sure = $top && $top['exact'] && $top['code'] && $top['score'] >= 90 && ( ! $sec || $sec['score'] <= $top['score'] - 30 );
		if ( $sure && flxpos_setting( 'bank_auto' ) ) {
			self::confirm( (int) $r['id'], (int) $top['id'], 0 );
		}
	}

	/**
	 * Hareket için aday siparişler, puana göre.
	 *
	 * @return array list of array( id, score, exact, code, why[] )
	 */
	public static function candidates( array $r ) {
		if ( ! function_exists( 'wc_get_orders' ) || (float) $r['amount'] <= 0 ) {
			return array();
		}
		$ts     = strtotime( $r['tdate'] . ' 23:59:59 UTC' );
		$orders = wc_get_orders(
			array(
				'limit'          => 300,
				'status'         => array( 'on-hold', 'pending' ),
				'payment_method' => self::methods(),
				'date_created'   => gmdate( 'Y-m-d', $ts - 45 * DAY_IN_SECONDS ) . '...' . gmdate( 'Y-m-d', $ts + 2 * DAY_IN_SECONDS ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$desc  = (string) $r['description'];
		$plain = FLXPOS_Bank_Layouts::plain( $desc );
		$words = FLXPOS_Bank_Layouts::words( $desc );
		$out   = array();
		foreach ( $orders as $o ) {
			if ( strtoupper( $o->get_currency() ) !== strtoupper( $r['currency'] ) ) {
				continue;
			}
			$why   = array();
			$score = 0;
			$exact = abs( (float) $o->get_total() - (float) $r['amount'] ) < 0.01;
			if ( $exact ) {
				$score += 40;
				$why[]  = 'amount';
			}
			$code  = false;
			$codes = (array) apply_filters( 'flxpos_bank_codes', array(), $o );
			foreach ( $codes as $k ) {
				$k = FLXPOS_Bank_Layouts::plain( $k );
				if ( strlen( $k ) >= 4 && false !== strpos( $plain, $k ) ) {
					$code = true;
					break;
				}
			}
			$num = (string) $o->get_order_number();
			$q = preg_quote( $num, '/' );
			// Sipariş no: 3+ hane tek başına ya da kısa numara "sipariş/no/#/bağış" sözcüğünden sonra.
			if ( ! $code && ( ( strlen( $num ) >= 3 && preg_match( '/(?<![0-9])' . $q . '(?![0-9])/', $desc ) ) || preg_match( '/(?:sipari[şs]|order|no|ref|#|ba[ğg][ıi][şs])\W{0,3}0*' . $q . '(?![0-9])/iu', $desc ) ) ) {
				$code = true;
			}
			if ( $code ) {
				$score += 50;
				$why[]  = 'code';
			}
			$first = FLXPOS_Bank_Layouts::words( $o->get_billing_first_name() );
			$last  = FLXPOS_Bank_Layouts::words( $o->get_billing_last_name() );
			$name  = array_merge( $first, $last );
			if ( $name && ! array_diff( $name, $words ) ) {
				$score += 30;
				$why[]  = 'name';
			} elseif ( $last && ! array_diff( $last, $words ) ) {
				$score += 15;
				$why[]  = 'surname';
			}
			if ( ! $exact && ! $code ) {
				continue;
			}
			$created = $o->get_date_created();
			if ( $created && $created->getTimestamp() <= $ts ) {
				$score += 5;
			}
			$out[] = array(
				'id'    => $o->get_id(),
				'score' => min( 100, $score ),
				'exact' => $exact,
				'code'  => $code,
				'why'   => $why,
			);
		}
		usort( $out, static fn( $a, $b ) => $b['score'] <=> $a['score'] );
		return (array) apply_filters( 'flxpos_bank_candidates', $out, $r );
	}

	/**
	 * Hareketi siparişle eşleştir ve siparişi ödendi yap.
	 *
	 * @param int $user 0 = otomatik.
	 * @return true|WP_Error
	 */
	public static function confirm( $id, $order_id, $user = 0 ) {
		$r = self::get( $id );
		$o = $r && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $r || ! $o ) {
			return new WP_Error( 'flxpos_bank', __( 'Hareket ya da sipariş bulunamadı.', 'flx-pos' ) );
		}
		if ( self::MATCHED === $r['status'] ) {
			return new WP_Error( 'flxpos_bank', __( 'Bu hareket zaten eşleşmiş.', 'flx-pos' ) );
		}
		if ( $o->is_paid() ) {
			return new WP_Error( 'flxpos_bank', __( 'Sipariş zaten ödenmiş.', 'flx-pos' ) );
		}
		self::update(
			$id,
			array(
				'status'     => self::MATCHED,
				'order_id'   => $o->get_id(),
				'matched_by' => (int) $user,
				'matched_at' => current_time( 'mysql', true ),
			)
		);
		$who = $user ? get_userdata( $user ) : null;
		/* translators: 1: tarih, 2: tutar, 3: açıklama, 4: kim */
		$note = sprintf( __( 'Havale/EFT banka hareketiyle eşleşti: %1$s, %2$s, "%3$s" (%4$s).', 'flx-pos' ), flx_date( strtotime( $r['tdate'] . ' 12:00 UTC' ), 'date' ), flxpos_money( (float) $r['amount'], $r['currency'] ), mb_substr( (string) $r['description'], 0, 120 ), $who ? $who->display_name : __( 'otomatik', 'flx-pos' ) );
		$o->update_meta_data( '_flxpos_bank', (int) $id );
		$o->save_meta_data();
		if ( ! apply_filters( 'flxpos_bank_confirm', false, $o, $r, $note ) ) {
			$o->add_order_note( $note );
			$o->payment_complete( '' !== $r['ref'] ? $r['ref'] : 'BANK-' . $id );
		}
		do_action( 'flxpos_bank_matched', $r, $o );
		return true;
	}

	public static function ignore( $id, $on = true ) {
		$r = self::get( $id );
		if ( ! $r || self::MATCHED === $r['status'] ) {
			return false;
		}
		self::update( $id, array( 'status' => $on ? self::IGNORED : self::NEW ) );
		if ( ! $on ) {
			self::schedule();
		}
		return true;
	}

	/** Eşleşmeyi kaldır (sipariş durumuna dokunmaz). */
	public static function unmatch( $id ) {
		$r = self::get( $id );
		if ( ! $r || self::MATCHED !== $r['status'] ) {
			return false;
		}
		$o = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $r['order_id'] ) : null;
		if ( $o ) {
			$o->delete_meta_data( '_flxpos_bank' );
			$o->save_meta_data();
			$o->add_order_note( __( 'Banka hareketi eşleşmesi kaldırıldı; sipariş durumu değiştirilmedi.', 'flx-pos' ) );
		}
		self::update(
			$id,
			array(
				'status'     => self::NEW,
				'order_id'   => 0,
				'matched_by' => 0,
				'matched_at' => null,
			)
		);
		return true;
	}

	/* ---------------- Kayıt ---------------- */

	public static function get( $id ) {
		global $wpdb;
		$t = self::table();
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $r ? $r : null;
	}

	private static function update( $id, array $f ) {
		global $wpdb;
		$wpdb->update( self::table(), $f, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/** Liste: status (boş = çıkanlar hariç hepsi), s, page, per. */
	public static function query( array $q ) {
		global $wpdb;
		$t     = self::table();
		$where = array( '1=1' );
		$args  = array();
		$st    = (string) ( $q['status'] ?? '' );
		if ( 'open' === $st ) {
			$where[] = 'status IN (%s, %s, %s)';
			array_push( $args, self::NEW, self::SUGGEST, self::NOMATCH );
		} elseif ( '' !== $st && 'all' !== $st ) {
			$where[] = 'status = %s';
			$args[]  = $st;
		} elseif ( '' === $st ) {
			$where[] = 'status <> %s';
			$args[]  = self::OUT;
		}
		$s = trim( (string) ( $q['s'] ?? '' ) );
		if ( '' !== $s ) {
			$like    = '%' . $wpdb->esc_like( $s ) . '%';
			$where[] = '(description LIKE %s OR ref LIKE %s OR CAST(amount AS CHAR) LIKE %s OR order_id = %d)';
			array_push( $args, $like, $like, $like, (int) $s );
		}
		$per   = max( 1, (int) ( $q['per'] ?? 30 ) );
		$page  = max( 1, (int) ( $q['page'] ?? 1 ) );
		$w     = implode( ' AND ', $where );
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$w}", $args ) ); // phpcs:ignore WordPress.DB
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$w} ORDER BY tdate DESC, id DESC LIMIT %d OFFSET %d", array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return array(
			'rows'  => (array) $rows,
			'total' => $total,
		);
	}

	/** Durum sayıları. */
	public static function counts() {
		global $wpdb;
		$t   = self::table();
		$out = array_fill_keys( array_keys( self::status_labels() ), 0 );
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$t} GROUP BY status", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$out[ $r['status'] ] = (int) $r['n'];
		}
		$out['last'] = (string) $wpdb->get_var( "SELECT MAX(tdate) FROM {$t}" ); // phpcs:ignore WordPress.DB
		return $out;
	}

	/* ---------------- Canlı kaynaklar ---------------- */

	/** @return FLXPOS_Bank_Source[] */
	public static function sources() {
		$out = array();
		foreach ( (array) apply_filters( 'flxpos_bank_sources', array() ) as $s ) {
			if ( $s instanceof FLXPOS_Bank_Source && '' !== $s->id ) {
				$out[ $s->id ] = $s;
			}
		}
		return $out;
	}

	/** Saatlik: etkin canlı kaynaklardan son 7 günü çek. */
	public static function pull() {
		$cfg = (array) get_option( 'flxpos_bank_sources', array() );
		foreach ( self::sources() as $id => $src ) {
			$c = (array) ( $cfg[ $id ] ?? array() );
			if ( empty( $c['enabled'] ) ) {
				continue;
			}
			$items = $src->fetch( gmdate( 'Y-m-d', time() - 7 * DAY_IN_SECONDS ), $c );
			if ( is_wp_error( $items ) ) {
				$cfg[ $id ]['error'] = $items->get_error_message();
				continue;
			}
			self::import( (array) $items, $id, $id, (string) ( $c['account'] ?? '' ) );
			$cfg[ $id ]['error']  = '';
			$cfg[ $id ]['pulled'] = time();
		}
		update_option( 'flxpos_bank_sources', $cfg, false );
	}
}
