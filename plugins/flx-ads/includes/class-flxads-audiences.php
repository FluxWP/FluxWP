<?php
/**
 * Flux Ads: Meta özel hedef kitleleri (Custom Audiences). Flux CRM'deki bir kişi grubu (tüm izinliler,
 * bağışçılar, RFM dilimi ya da kayıtlı segment) Meta reklam hesabında bir kitleye eşitlenir.
 *
 * - Yalnız reklam izni (consent_ads) açık, etkin kişiler gider. E-posta, telefon, ad, soyad ve ülke
 *   Meta'nın istediği gibi normalleştirilip SHA-256 ile özetlenir; düz veri siteden çıkmaz.
 * - Günlük fark eşitlenir: yeni girenler eklenir, gruptan çıkanlar ya da izni kapananlar Meta'dan silinir.
 *   İzin kapanınca ya da kişi silinince o kişi beklemeden tüm kitlelerden çıkarılır.
 * - Benzer kitle (lookalike) kaynak kitleden bir kez oluşturulur; üyesi yoktur, Meta doldurur.
 * - "Hariç tut" amaçlı kitle (ör. son 30 günün bağışçıları) reklam setinde hariç tutmak içindir.
 *
 * Anahtar ads_management izni ister (yalnız harcama okuyan ads_read yetmez).
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Audiences {

	const OPTION = 'flxads_audiences';
	const STALE  = DAY_IN_SECONDS;
	const BATCH  = 5000;
	const SCHEMA = array( 'EMAIL', 'PHONE', 'FN', 'LN', 'COUNTRY' );

	/** @var array İstek sonunda Meta'dan çıkarılacak kişiler. */
	private static $drop = array();

	public static function init() {
		add_action( 'flxads_retry', array( __CLASS__, 'maybe_sync' ) );
		add_action( 'flxcrm_consent_changed', array( __CLASS__, 'consent_changed' ), 10, 2 );
		add_action( 'flxcrm_contact_deleting', array( __CLASS__, 'person_gone' ) );
	}

	/** CRM kurulu ve kişi tablosu hazır mı? */
	public static function crm_ready() {
		return class_exists( 'FLXCRM_Contacts' ) && function_exists( 'flxcrm_table' );
	}

	public static function ready() {
		return self::crm_ready() && FLXADS_Spend::meta_ready();
	}

	/* ------------------------------------------------------------ Kayıtlar */

	/** @return array id => audience */
	public static function all() {
		$a = get_option( self::OPTION, array() );
		return is_array( $a ) ? $a : array();
	}

	public static function get( $id ) {
		return self::all()[ (string) $id ] ?? null;
	}

	private static function put( array $a ) {
		$all             = self::all();
		$all[ $a['id'] ] = $a;
		update_option( self::OPTION, $all, false );
		return $a;
	}

	/**
	 * Holdout payını ayarlar (0 = kapalı) ve kitleyi hemen eşitler: holdout'a düşenler Meta'dan çıkar, kapanınca geri girer.
	 *
	 * @return array|WP_Error
	 */
	public static function set_holdout( $id, $pct, $exp = '' ) {
		$a = self::get( $id );
		if ( ! $a ) {
			return new WP_Error( 'flxads_bad', __( 'Audience not found.', 'flx-ads' ) );
		}
		$a['holdout']     = max( 0, min( 50, (int) $pct ) );
		$a['holdout_exp'] = $a['holdout'] ? (string) $exp : '';
		self::put( $a );
		return self::sync( $id );
	}

	/** Kaynak seçenekleri: anahtar => ad. */
	public static function sources() {
		$out = array(
			'all'    => __( 'Everyone with ad consent', 'flx-ads' ),
			'donors' => __( 'Donors and customers (paid at least once)', 'flx-ads' ),
		);
		if ( function_exists( 'flxcrm_rfm_segments' ) ) {
			foreach ( flxcrm_rfm_segments() as $k => $s ) {
				/* translators: %s: RFM segment name, e.g. Champion */
				$out[ 'rfm:' . $k ] = sprintf( __( 'Value group: %s', 'flx-ads' ), $s[0] );
			}
		}
		if ( class_exists( 'FLXCRM_Segments' ) ) {
			foreach ( FLXCRM_Segments::all() as $s ) {
				/* translators: %s: CRM segment name */
				$out[ 'seg:' . (int) $s['id'] ] = sprintf( __( 'Segment: %s', 'flx-ads' ), $s['name'] );
			}
		}
		return $out;
	}

	public static function purposes() {
		return array(
			'target'  => __( 'Show ads to them', 'flx-ads' ),
			'exclude' => __( 'Exclude from ads', 'flx-ads' ),
		);
	}

	/**
	 * Yeni kitle: Meta'da oluşturur, yerelde kaydeder, ilk eşitlemeyi yapar. İstenirse benzer kitle de açılır.
	 *
	 * @param array $d name, source, purpose, lookalike (bool), country (TR), ratio (1–10 yüzde)
	 * @return array|WP_Error
	 */
	public static function create( array $d ) {
		if ( ! self::ready() ) {
			return new WP_Error( 'flxads_not_ready', __( 'Flux CRM and a Meta ad account with an ads_management key are needed.', 'flx-ads' ) );
		}
		$name   = mb_substr( trim( sanitize_text_field( (string) ( $d['name'] ?? '' ) ) ), 0, 100 );
		$source = (string) ( $d['source'] ?? '' );
		if ( '' === $name || ! isset( self::sources()[ $source ] ) ) {
			return new WP_Error( 'flxads_bad', __( 'Enter a name and pick a group.', 'flx-ads' ) );
		}
		$purpose = isset( self::purposes()[ $d['purpose'] ?? '' ] ) ? (string) $d['purpose'] : 'target';
		$r       = self::graph(
			'POST',
			'act_' . FLXADS_Spend::meta_account() . '/customaudiences',
			array(
				'name'                 => $name,
				'subtype'              => 'CUSTOM',
				'description'          => 'Flux Ads · ' . self::sources()[ $source ],
				'customer_file_source' => 'USER_PROVIDED_ONLY',
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$a = self::put(
			array(
				'id'         => 'a' . substr( md5( uniqid( '', true ) ), 0, 8 ),
				'name'       => $name,
				'source'     => $source,
				'purpose'    => $purpose,
				'meta_id'    => (string) $r['id'],
				'kind'       => 'custom',
				'count'      => 0,
				'synced'     => '',
				'error'      => '',
				'created_at' => flxads_now(),
			)
		);
		$sync = self::sync( $a['id'] );
		$a    = self::get( $a['id'] );
		if ( ! empty( $d['lookalike'] ) ) {
			$l = self::lookalike( $a['id'], (string) ( $d['country'] ?? 'TR' ), (float) ( $d['ratio'] ?? 1 ) );
			if ( is_wp_error( $l ) ) {
				$a['lookalike_error'] = $l->get_error_message();
			}
		}
		if ( is_wp_error( $sync ) ) {
			$a['sync_error'] = $sync->get_error_message();
		}
		return $a;
	}

	/**
	 * Benzer kitle (lookalike): kaynak kitleye benzeyen kişiler, ülke + oran (%1–10).
	 *
	 * @return array|WP_Error
	 */
	public static function lookalike( $origin_id, $country = 'TR', $ratio = 1.0 ) {
		$o = self::get( $origin_id );
		if ( ! $o || 'custom' !== $o['kind'] ) {
			return new WP_Error( 'flxads_bad', __( 'Pick one of your own audiences as the source.', 'flx-ads' ) );
		}
		$country = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $country ) );
		$country = 2 === strlen( $country ) ? $country : 'TR';
		$ratio   = max( 1, min( 10, (int) round( (float) $ratio ) ) );
		/* translators: 1: source audience name, 2: percent, 3: country code */
		$name = sprintf( __( '%1$s · lookalike %2$d%% %3$s', 'flx-ads' ), $o['name'], $ratio, $country );
		$r    = self::graph(
			'POST',
			'act_' . FLXADS_Spend::meta_account() . '/customaudiences',
			array(
				'name'               => $name,
				'subtype'            => 'LOOKALIKE',
				'origin_audience_id' => $o['meta_id'],
				'lookalike_spec'     => wp_json_encode( array( 'type' => 'similarity', 'country' => $country, 'ratio' => $ratio / 100 ) ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return self::put(
			array(
				'id'         => 'l' . substr( md5( uniqid( '', true ) ), 0, 8 ),
				'name'       => $name,
				'source'     => 'lookalike:' . $o['id'],
				'purpose'    => 'target',
				'meta_id'    => (string) $r['id'],
				'kind'       => 'lookalike',
				'country'    => $country,
				'ratio'      => $ratio,
				'count'      => 0,
				'synced'     => flxads_now(),
				'error'      => '',
				'created_at' => flxads_now(),
			)
		);
	}

	/** Kitleyi Meta'dan ve yerelden siler. Meta'da zaten yoksa yerel kayıt yine silinir. */
	public static function delete( $id ) {
		global $wpdb;
		$a = self::get( $id );
		if ( ! $a ) {
			return new WP_Error( 'flxads_bad', __( 'Audience not found.', 'flx-ads' ) );
		}
		$r = self::graph( 'DELETE', rawurlencode( $a['meta_id'] ) );
		if ( is_wp_error( $r ) && false === stripos( $r->get_error_message(), 'does not exist' ) ) {
			return $r;
		}
		$all = self::all();
		unset( $all[ $a['id'] ] );
		update_option( self::OPTION, $all, false );
		$wpdb->delete( flxads_table( 'aud_members' ), array( 'aud' => $a['id'] ) ); // phpcs:ignore WordPress.DB
		return true;
	}

	/* ------------------------------------------------------------ Üyeler */

	/** Kaynağa göre kişi id'leri (yalnız reklam izni açık, etkin, e-posta ya da telefonu olan). */
	public static function member_ids( $source ) {
		global $wpdb;
		if ( ! self::crm_ready() ) {
			return array();
		}
		$t    = flxcrm_table( 'contacts' );
		$base = "status = 'active' AND consent_ads = 1 AND (email <> '' OR phone <> '')";
		if ( 'all' === $source ) {
			return array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM $t WHERE $base" ) ); // phpcs:ignore WordPress.DB
		}
		if ( 'donors' === $source ) {
			return array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM $t WHERE $base AND paid_days > 0" ) ); // phpcs:ignore WordPress.DB
		}
		if ( 0 === strpos( $source, 'rfm:' ) ) {
			return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $t WHERE $base AND segment = %s", substr( $source, 4 ) ) ) ); // phpcs:ignore WordPress.DB
		}
		if ( 0 === strpos( $source, 'seg:' ) && class_exists( 'FLXCRM_Segments' ) ) {
			$seg = FLXCRM_Segments::get( (int) substr( $source, 4 ) );
			if ( ! $seg ) {
				return array();
			}
			$ids = FLXCRM_Contacts::ids( array( 'rules' => $seg['rules'] ), 100000 );
			if ( ! $ids ) {
				return array();
			}
			$ok = array();
			foreach ( array_chunk( $ids, 1000 ) as $chunk ) {
				$ok = array_merge( $ok, array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM $t WHERE $base AND id IN (" . implode( ',', array_map( 'intval', $chunk ) ) . ')' ) ) ); // phpcs:ignore WordPress.DB
			}
			return $ok;
		}
		return array();
	}

	/** Meta normalizasyonu: küçük harf, noktalama yok. */
	private static function name_part( $v ) {
		$v = mb_strtolower( trim( (string) $v ), 'UTF-8' );
		$v = preg_replace( '/[^\p{L}\p{N}]+/u', '', $v );
		return '' === (string) $v ? '' : hash( 'sha256', $v );
	}

	/** Kişi satırı → özetlenmiş satır (SCHEMA sırasıyla). */
	public static function hashed_row( array $c ) {
		$parts = preg_split( '/\s+/u', trim( (string) $c['name'] ) );
		$fn    = count( $parts ) > 1 ? implode( ' ', array_slice( $parts, 0, -1 ) ) : (string) ( $parts[0] ?? '' );
		$ln    = count( $parts ) > 1 ? (string) end( $parts ) : '';
		$ct    = strtolower( preg_replace( '/[^A-Za-z]/', '', (string) ( $c['country'] ?? '' ) ) );
		return array(
			flxads_hash( (string) $c['email'] ),
			flxads_hash_phone( (string) $c['phone'] ),
			self::name_part( $fn ),
			self::name_part( $ln ),
			2 === strlen( $ct ) ? hash( 'sha256', $ct ) : '',
		);
	}

	/**
	 * Fark eşitlemesi.
	 *
	 * @return array|WP_Error [ added, removed, count ]
	 */
	public static function sync( $id ) {
		global $wpdb;
		$a = self::get( $id );
		if ( ! $a || 'custom' !== $a['kind'] ) {
			return new WP_Error( 'flxads_bad', __( 'Audience not found.', 'flx-ads' ) );
		}
		if ( ! self::ready() ) {
			return new WP_Error( 'flxads_not_ready', __( 'Flux CRM and a Meta ad account with an ads_management key are needed.', 'flx-ads' ) );
		}
		$t      = flxcrm_table( 'contacts' );
		$m      = flxads_table( 'aud_members' );
		$ids    = self::member_ids( $a['source'] );
		if ( ! empty( $a['holdout'] ) ) {
			/* Holdout deneyi: kişilerin sabit bir payı reklam görmesin diye kitleye girmez (FLXADS_Experiments). */
			$ids = array_values( array_filter( $ids, static function ( $pid ) use ( $a ) { return ! FLXADS_Experiments::in_holdout( $a['id'], $pid, (int) $a['holdout'] ); } ) );
		}
		$want   = array();
		foreach ( array_chunk( $ids, 1000 ) as $chunk ) {
			$rows = (array) $wpdb->get_results( "SELECT id, name, email, phone, country FROM $t WHERE id IN (" . implode( ',', array_map( 'intval', $chunk ) ) . ')', ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( $rows as $c ) {
				$h = self::hashed_row( $c );
				if ( '' === $h[0] && '' === $h[1] ) {
					continue;
				}
				$want[ (int) $c['id'] ] = $h;
			}
		}
		$have = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT person_id, em, ph, sig FROM $m WHERE aud = %s", $a['id'] ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$have[ (int) $r['person_id'] ] = $r;
		}
		$add = array();
		$rem = array();
		foreach ( $want as $pid => $h ) {
			$sig = substr( md5( implode( '|', $h ) ), 0, 16 );
			if ( ! isset( $have[ $pid ] ) ) {
				$add[ $pid ] = array( $h, $sig );
			} elseif ( $have[ $pid ]['sig'] !== $sig ) {
				$rem[ $pid ] = $have[ $pid ]; // bilgisi değişmiş: eski özet çıkar, yenisi girer
				$add[ $pid ] = array( $h, $sig );
			}
		}
		foreach ( $have as $pid => $r ) {
			if ( ! isset( $want[ $pid ] ) ) {
				$rem[ $pid ] = $r;
			}
		}
		/* Önce çıkar (izni kapanan kişi Meta'da kalmasın), sonra ekle. */
		foreach ( array_chunk( $rem, self::BATCH, true ) as $chunk ) {
			$r = self::users( 'DELETE', $a['meta_id'], array_map( static function ( $x ) { return array( $x['em'], $x['ph'] ); }, array_values( $chunk ) ), array( 'EMAIL', 'PHONE' ) );
			if ( is_wp_error( $r ) ) {
				return self::failed( $a, $r );
			}
			$wpdb->query( $wpdb->prepare( "DELETE FROM $m WHERE aud = %s AND person_id IN (" . implode( ',', array_map( 'intval', array_keys( $chunk ) ) ) . ')', $a['id'] ) ); // phpcs:ignore WordPress.DB
		}
		foreach ( array_chunk( $add, self::BATCH, true ) as $chunk ) {
			$r = self::users( 'POST', $a['meta_id'], array_map( static function ( $x ) { return $x[0]; }, array_values( $chunk ) ), self::SCHEMA );
			if ( is_wp_error( $r ) ) {
				return self::failed( $a, $r );
			}
			foreach ( $chunk as $pid => $x ) {
				$wpdb->replace( $m, array( 'aud' => $a['id'], 'person_id' => (int) $pid, 'em' => $x[0][0], 'ph' => $x[0][1], 'sig' => $x[1], 'added_at' => flxads_now() ) ); // phpcs:ignore WordPress.DB
			}
		}
		$a['count']  = count( $want );
		$a['synced'] = flxads_now();
		$a['error']  = '';
		$a['last']   = array( 'added' => count( $add ), 'removed' => count( $rem ) );
		self::put( $a );
		return array( 'added' => count( $add ), 'removed' => count( $rem ), 'count' => count( $want ) );
	}

	private static function failed( array $a, WP_Error $e ) {
		$a['error']  = $e->get_error_message();
		$a['synced'] = flxads_now();
		self::put( $a );
		return $e;
	}

	/** Günde bir eşitle (saatlik iş ya da ekran). */
	public static function maybe_sync() {
		if ( ! self::ready() || get_transient( 'flxads_aud_lock' ) ) {
			return;
		}
		set_transient( 'flxads_aud_lock', 1, 10 * MINUTE_IN_SECONDS );
		$start = time();
		foreach ( self::all() as $a ) {
			if ( 'custom' !== $a['kind'] || ( ! empty( $a['synced'] ) && strtotime( $a['synced'] . ' UTC' ) > time() - self::STALE ) ) {
				continue;
			}
			if ( time() - $start > 40 ) {
				break; // PHP ~60 sn'de kesilir; kalanlar sonraki turda
			}
			self::sync( $a['id'] );
		}
		delete_transient( 'flxads_aud_lock' );
	}

	/* ------------------------------------------------------------ İzin kapanınca */

	public static function consent_changed( $id, $changed ) {
		if ( isset( $changed['ads'] ) && ! (int) $changed['ads'] ) {
			self::person_gone( $id );
		}
	}

	/** Kişiyi tüm kitlelerden istek sonunda çıkarır (bekleme yok, günlük eşitlemeyi beklemez). */
	public static function person_gone( $id ) {
		if ( ! self::$drop ) {
			add_action( 'shutdown', array( __CLASS__, 'drop_now' ), 30 );
		}
		self::$drop[ (int) $id ] = true;
	}

	public static function drop_now() {
		global $wpdb;
		$ids        = array_keys( self::$drop );
		self::$drop = array();
		if ( ! $ids || ! FLXADS_Spend::meta_ready() ) {
			return;
		}
		$m    = flxads_table( 'aud_members' );
		$rows = (array) $wpdb->get_results( "SELECT aud, person_id, em, ph FROM $m WHERE person_id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')', ARRAY_A ); // phpcs:ignore WordPress.DB
		$by   = array();
		foreach ( $rows as $r ) {
			$by[ $r['aud'] ][] = $r;
		}
		foreach ( $by as $aud => $list ) {
			$a = self::get( $aud );
			if ( ! $a ) {
				continue;
			}
			$r = self::users( 'DELETE', $a['meta_id'], array_map( static function ( $x ) { return array( $x['em'], $x['ph'] ); }, $list ), array( 'EMAIL', 'PHONE' ) );
			if ( ! is_wp_error( $r ) ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM $m WHERE aud = %s AND person_id IN (" . implode( ',', array_map( 'intval', wp_list_pluck( $list, 'person_id' ) ) ) . ')', $aud ) ); // phpcs:ignore WordPress.DB
			}
			/* Hata olursa satır kalır; günlük eşitleme izni kapalı kişiyi yeniden çıkarmayı dener. */
		}
	}

	/* ------------------------------------------------------------ Meta */

	private static function users( $method, $meta_id, array $data, array $schema ) {
		return self::graph(
			$method,
			rawurlencode( (string) $meta_id ) . '/users',
			array( 'payload' => wp_json_encode( array( 'schema' => $schema, 'data' => array_values( $data ) ) ) )
		);
	}

	/**
	 * Graph API çağrısı. Anahtar gövdede gider (adres satırında değil).
	 *
	 * @return array|WP_Error
	 */
	public static function graph( $method, $path, array $params = array() ) {
		$params['access_token'] = FLXADS_Spend::meta_token();
		$url                    = FLXADS_Send::META_API . ltrim( $path, '/' );
		$args                   = array( 'timeout' => 30, 'method' => $method );
		if ( 'GET' === $method ) {
			$url = add_query_arg( array_map( 'rawurlencode', $params ), $url );
		} else {
			$args['body'] = $params;
		}
		$r = wp_remote_request( $url, $args );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		$j    = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $j ) || ! empty( $j['error'] ) ) {
			$msg = isset( $j['error']['error_user_msg'] ) ? $j['error']['error_user_msg'] : ( $j['error']['message'] ?? '' );
			return new WP_Error( 'flxads_meta', 'HTTP ' . $code . ( '' !== $msg ? ': ' . $msg : '' ) );
		}
		return $j;
	}
}
