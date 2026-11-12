<?php
/**
 * Panelden reklam yönetimi (Meta Marketing API): kampanya → reklam seti → reklam ağacı, dönem metrikleri,
 * yaş/cinsiyet/yerleşim kırılımları, durum (aç/duraklat) ve günlük bütçe değişikliği.
 *
 * Kurallar: silme/arşivleme yok, yalnız ACTIVE ↔ PAUSED ve bütçe. Her değişiklik `flxads_mgr_log`
 * seçeneğine (son 100) yazılır. Okumalar 10 dk önbellekte; değişiklikten sonra önbellek temizlenir.
 * Anahtarın ads_management izni olmalı (okuma için ads_read yeter).
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Manager {

	const CACHE   = 10 * MINUTE_IN_SECONDS;
	const LOG_MAX = 100;
	const METRICS = 'spend,impressions,reach,inline_link_clicks,ctr,cpm,frequency';

	/** Ondalıksız para birimleri (Meta bütçeyi en küçük birimde ister). */
	const ZERO_DECIMAL = array( 'JPY', 'KRW', 'CLP', 'COP', 'CRC', 'HUF', 'ISK', 'IDR', 'PYG', 'TWD', 'VND' );

	public static function ranges() {
		return array(
			'7'  => __( 'Last 7 days', 'flx-ads' ),
			'30' => __( 'Last 30 days', 'flx-ads' ),
			'90' => __( 'Last 90 days', 'flx-ads' ),
		);
	}

	public static function statuses() {
		return array(
			'ACTIVE'          => __( 'Active', 'flx-ads' ),
			'PAUSED'          => __( 'Paused', 'flx-ads' ),
			'CAMPAIGN_PAUSED' => __( 'Campaign paused', 'flx-ads' ),
			'ADSET_PAUSED'    => __( 'Ad set paused', 'flx-ads' ),
			'IN_PROCESS'      => __( 'Processing', 'flx-ads' ),
			'WITH_ISSUES'     => __( 'Has issues', 'flx-ads' ),
			'PENDING_REVIEW'  => __( 'In review', 'flx-ads' ),
			'DISAPPROVED'     => __( 'Rejected', 'flx-ads' ),
			'PREAPPROVED'     => __( 'Approved', 'flx-ads' ),
			'PENDING_BILLING_INFO' => __( 'Billing needed', 'flx-ads' ),
			'ARCHIVED'        => __( 'Archived', 'flx-ads' ),
			'DELETED'         => __( 'Deleted', 'flx-ads' ),
		);
	}

	/** [ since, until ] site saatiyle. */
	public static function span( $range ) {
		$d = isset( self::ranges()[ (string) $range ] ) ? (int) $range : 7;
		return array( wp_date( 'Y-m-d', time() - ( $d - 1 ) * DAY_IN_SECONDS ), wp_date( 'Y-m-d' ) );
	}

	private static function insights_field( $range ) {
		list( $since, $until ) = self::span( $range );
		return 'insights.time_range(' . wp_json_encode( array( 'since' => $since, 'until' => $until ) ) . '){' . self::METRICS . '}';
	}

	/** Önbellekli GET. @return array|WP_Error */
	private static function get( $path, array $params ) {
		$key = 'flxads_mgr_' . md5( $path . '|' . wp_json_encode( $params ) . '|' . FLXADS_Spend::meta_account() );
		$hit = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$r = FLXADS_Audiences::graph( 'GET', $path, $params );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		set_transient( $key, $r, self::CACHE );
		$keys          = (array) get_option( 'flxads_mgr_keys', array() );
		$keys[ $key ] = 1;
		update_option( 'flxads_mgr_keys', array_slice( $keys, -200, null, true ), false );
		return $r;
	}

	public static function flush() {
		foreach ( array_keys( (array) get_option( 'flxads_mgr_keys', array() ) ) as $k ) {
			delete_transient( $k );
		}
		update_option( 'flxads_mgr_keys', array(), false );
	}

	private static function ready() {
		if ( ! FLXADS_Spend::meta_ready() ) {
			return new WP_Error( 'flxads_meta', __( 'Enter the Meta ad account ID and a Marketing API key in Settings › Meta.', 'flx-ads' ) );
		}
		return true;
	}

	/** Hesap para birimi (bütçe birimleri için). */
	public static function currency() {
		$r = self::get( 'act_' . FLXADS_Spend::meta_account(), array( 'fields' => 'currency,name' ) );
		return is_wp_error( $r ) ? FLXADS_Report::base_currency() : strtoupper( (string) ( $r['currency'] ?? FLXADS_Report::base_currency() ) );
	}

	private static function factor( $cur ) {
		return in_array( $cur, self::ZERO_DECIMAL, true ) ? 1 : 100;
	}

	/** Meta satırını sade biçime çevirir. */
	private static function shape( array $o, $cur ) {
		$m   = (array) ( $o['insights']['data'][0] ?? array() );
		$f   = self::factor( $cur );
		$out = array(
			'id'          => (string) $o['id'],
			'name'        => (string) ( $o['name'] ?? '' ),
			'status'      => (string) ( $o['status'] ?? '' ),
			'effective'   => (string) ( $o['effective_status'] ?? ( $o['status'] ?? '' ) ),
			'daily'       => isset( $o['daily_budget'] ) && '' !== (string) $o['daily_budget'] && (int) $o['daily_budget'] > 0 ? (int) $o['daily_budget'] / $f : null,
			'lifetime'    => isset( $o['lifetime_budget'] ) && (int) $o['lifetime_budget'] > 0 ? (int) $o['lifetime_budget'] / $f : null,
			'spend'       => round( (float) ( $m['spend'] ?? 0 ), 2 ),
			'impressions' => (int) ( $m['impressions'] ?? 0 ),
			'reach'       => (int) ( $m['reach'] ?? 0 ),
			'clicks'      => (int) ( $m['inline_link_clicks'] ?? 0 ),
			'ctr'         => isset( $m['ctr'] ) ? round( (float) $m['ctr'], 2 ) : null,
			'cpm'         => isset( $m['cpm'] ) ? round( (float) $m['cpm'], 2 ) : null,
			'frequency'   => isset( $m['frequency'] ) ? round( (float) $m['frequency'], 2 ) : null,
		);
		foreach ( array( 'objective', 'optimization_goal', 'bid_strategy', 'campaign_id', 'adset_id' ) as $k ) {
			if ( isset( $o[ $k ] ) ) {
				$out[ $k ] = (string) $o[ $k ];
			}
		}
		if ( isset( $o['targeting'] ) ) {
			$out['targeting'] = self::targeting( (array) $o['targeting'] );
		}
		if ( isset( $o['creative'] ) ) {
			$c              = (array) $o['creative'];
			$out['creative'] = array(
				'title' => (string) ( $c['title'] ?? '' ),
				'body'  => (string) ( $c['body'] ?? '' ),
				'thumb' => esc_url_raw( (string) ( $c['thumbnail_url'] ?? '' ) ),
			);
		}
		return $out;
	}

	/** Hedefleme özeti: yaş, cinsiyet, ülkeler/şehirler, kitle sayısı. */
	private static function targeting( array $t ) {
		$g   = array_map( 'intval', (array) ( $t['genders'] ?? array() ) );
		$geo = (array) ( $t['geo_locations'] ?? array() );
		return array(
			'age_min'   => (int) ( $t['age_min'] ?? 18 ),
			'age_max'   => (int) ( $t['age_max'] ?? 65 ),
			'genders'   => 1 === count( $g ) ? ( 1 === $g[0] ? 'male' : 'female' ) : 'all',
			'countries' => array_values( array_map( 'strval', (array) ( $geo['countries'] ?? array() ) ) ),
			'cities'    => array_values( array_filter( array_map( static function ( $c ) { return (string) ( $c['name'] ?? '' ); }, (array) ( $geo['cities'] ?? array() ) ) ) ),
			'audiences' => count( (array) ( $t['custom_audiences'] ?? array() ) ),
			'excluded'  => count( (array) ( $t['excluded_custom_audiences'] ?? array() ) ),
		);
	}

	/**
	 * Kampanyalar + dönem metrikleri + sitedeki ödemeler (kampanya adı ya da kimliği eşleşir).
	 *
	 * @return array|WP_Error [ currency, base, since, until, rows[], totals ]
	 */
	public static function campaigns( $range = '7' ) {
		$ok = self::ready();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$cur = self::currency();
		$r   = self::get(
			'act_' . FLXADS_Spend::meta_account() . '/campaigns',
			array(
				'fields'           => 'id,name,status,effective_status,objective,daily_budget,lifetime_budget,bid_strategy,' . self::insights_field( $range ),
				'limit'            => 100,
				'effective_status' => wp_json_encode( array( 'ACTIVE', 'PAUSED', 'IN_PROCESS', 'WITH_ISSUES', 'PENDING_REVIEW', 'DISAPPROVED', 'PREAPPROVED', 'PENDING_BILLING_INFO', 'CAMPAIGN_PAUSED', 'ADSET_PAUSED' ) ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		list( $since, $until ) = self::span( $range );
		$income = self::income( $since, $until );
		$rows   = array();
		$tot    = array( 'spend' => 0.0, 'impressions' => 0, 'clicks' => 0, 'income' => 0.0, 'payments' => 0, 'active' => 0 );
		foreach ( (array) ( $r['data'] ?? array() ) as $o ) {
			$c             = self::shape( (array) $o, $cur );
			$inc           = $income[ $c['id'] ] ?? ( $income[ FLXADS_Spend::norm( $c['name'] ) ] ?? array( 0.0, 0, 0 ) );
			$c['income']   = round( $inc[0], 2 );
			$c['payments'] = (int) $inc[1];
			$c['new']      = (int) $inc[2];
			$c['roas']     = $c['spend'] > 0 ? round( $c['income'] / $c['spend'], 2 ) : null;
			$c['cpa']      = $c['payments'] ? round( $c['spend'] / $c['payments'], 2 ) : null;
			$rows[]        = $c;
			$tot['spend']       += $c['spend'];
			$tot['impressions'] += $c['impressions'];
			$tot['clicks']      += $c['clicks'];
			$tot['income']      += $c['income'];
			$tot['payments']    += $c['payments'];
			$tot['active']      += 'ACTIVE' === $c['effective'] ? 1 : 0;
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return ( 'ACTIVE' === $b['effective'] ) <=> ( 'ACTIVE' === $a['effective'] ) ?: $b['spend'] <=> $a['spend'];
			}
		);
		$tot['roas'] = $tot['spend'] > 0 ? round( $tot['income'] / $tot['spend'], 2 ) : null;
		return array( 'currency' => $cur, 'base' => FLXADS_Report::base_currency(), 'since' => $since, 'until' => $until, 'rows' => $rows, 'totals' => $tot );
	}

	/** Sitedeki ödenmiş siparişler, kampanya anahtarına (normalize ad ya da Meta kimliği) göre: [ tutar, adet, yeni ]. */
	private static function income( $since, $until ) {
		$g   = FLXADS_Report::grouped( 'campaign', array( 'from' => $since, 'to' => $until, 'channel' => 'meta' ), 500 );
		$out = array();
		foreach ( (array) $g['rows'] as $row ) {
			if ( ! isset( $row['count'] ) || ! $row['count'] ) {
				continue;
			}
			$k         = ctype_digit( (string) $row['key'] ) ? (string) $row['key'] : FLXADS_Spend::norm( $row['key'] );
			$out[ $k ] = array( (float) $row['amount'], (int) $row['count'], (int) $row['new'] );
		}
		return $out;
	}

	/**
	 * Tek kampanya: bilgi, reklam setleri (her birinin reklamlarıyla), kırılımlar.
	 *
	 * @return array|WP_Error
	 */
	public static function campaign( $id, $range = '7', $breakdown = true ) {
		$ok = self::ready();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$id = preg_replace( '/\D+/', '', (string) $id );
		if ( '' === $id ) {
			return new WP_Error( 'flxads_mgr', __( 'Campaign not found.', 'flx-ads' ) );
		}
		$cur = self::currency();
		$ins = self::insights_field( $range );
		$c   = self::get( $id, array( 'fields' => 'id,name,status,effective_status,objective,daily_budget,lifetime_budget,bid_strategy,' . $ins ) );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$sets = self::get( $id . '/adsets', array( 'fields' => 'id,name,status,effective_status,daily_budget,lifetime_budget,optimization_goal,bid_strategy,targeting,' . $ins, 'limit' => 100 ) );
		$ads  = self::get( $id . '/ads', array( 'fields' => 'id,name,status,effective_status,adset_id,creative{title,body,thumbnail_url},' . $ins, 'limit' => 200 ) );
		if ( is_wp_error( $sets ) ) {
			return $sets;
		}
		$by = array();
		foreach ( is_wp_error( $ads ) ? array() : (array) ( $ads['data'] ?? array() ) as $a ) {
			$a                         = self::shape( (array) $a, $cur );
			$by[ $a['adset_id'] ?? '' ][] = $a;
		}
		$out = self::shape( (array) $c, $cur );
		foreach ( (array) ( $sets['data'] ?? array() ) as $s ) {
			$s          = self::shape( (array) $s, $cur );
			$s['ads']   = $by[ $s['id'] ] ?? array();
			$out['adsets'][] = $s;
		}
		$out['adsets']    = $out['adsets'] ?? array();
		$out['currency']  = $cur;
		$out['breakdown'] = $breakdown ? array(
			'age_gender' => self::breakdown( $id, 'age,gender', $range ),
			'placement'  => self::breakdown( $id, 'publisher_platform,platform_position', $range ),
		) : array();
		return $out;
	}

	/** Kırılım satırları: [ key[], spend, impressions, clicks ]. Hata olursa boş. */
	public static function breakdown( $id, $by, $range = '7' ) {
		list( $since, $until ) = self::span( $range );
		$r = self::get(
			preg_replace( '/\D+/', '', (string) $id ) . '/insights',
			array(
				'fields'     => 'spend,impressions,inline_link_clicks',
				'breakdowns' => $by,
				'time_range' => wp_json_encode( array( 'since' => $since, 'until' => $until ) ),
				'limit'      => 200,
			)
		);
		if ( is_wp_error( $r ) ) {
			return array();
		}
		$keys = explode( ',', $by );
		$out  = array();
		foreach ( (array) ( $r['data'] ?? array() ) as $d ) {
			$out[] = array(
				'key'         => array_map( static function ( $k ) use ( $d ) { return (string) ( $d[ $k ] ?? '' ); }, $keys ),
				'spend'       => round( (float) ( $d['spend'] ?? 0 ), 2 ),
				'impressions' => (int) ( $d['impressions'] ?? 0 ),
				'clicks'      => (int) ( $d['inline_link_clicks'] ?? 0 ),
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $b['spend'] <=> $a['spend'];
			}
		);
		return $out;
	}

	/** Tek nesne (kampanya / reklam seti / reklam) – değişiklik önizlemesi için, önbelleksiz. */
	public static function object( $id ) {
		$ok = self::ready();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$id = preg_replace( '/\D+/', '', (string) $id );
		$r  = FLXADS_Audiences::graph( 'GET', $id, array( 'fields' => 'id,name,status,effective_status,daily_budget,lifetime_budget' ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return self::shape( (array) $r, self::currency() );
	}

	/**
	 * Aç / duraklat.
	 *
	 * @return array|WP_Error
	 */
	public static function set_status( $id, $status, $channel = 'panel' ) {
		$status = strtoupper( (string) $status );
		if ( ! in_array( $status, array( 'ACTIVE', 'PAUSED' ), true ) ) {
			return new WP_Error( 'flxads_mgr', __( 'Status can only be Active or Paused.', 'flx-ads' ) );
		}
		$o = self::object( $id );
		if ( is_wp_error( $o ) ) {
			return $o;
		}
		if ( $o['status'] === $status ) {
			return array( 'id' => $o['id'], 'status' => $status, 'changed' => false );
		}
		$r = FLXADS_Audiences::graph( 'POST', $o['id'], array( 'status' => $status ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		self::log( $o, 'status', $o['status'], $status, $channel );
		self::flush();
		return array( 'id' => $o['id'], 'name' => $o['name'], 'status' => $status, 'changed' => true );
	}

	/**
	 * Günlük bütçe (hesap para biriminde, tam birim). Yalnız günlük bütçesi olan kampanya/reklam setinde.
	 *
	 * @return array|WP_Error
	 */
	public static function set_budget( $id, $amount, $channel = 'panel' ) {
		$amount = round( (float) $amount, 2 );
		if ( $amount <= 0 ) {
			return new WP_Error( 'flxads_mgr', __( 'Enter a daily budget greater than zero.', 'flx-ads' ) );
		}
		$o = self::object( $id );
		if ( is_wp_error( $o ) ) {
			return $o;
		}
		if ( null === $o['daily'] ) {
			return new WP_Error( 'flxads_mgr', __( 'This item has no daily budget here (lifetime budget, or the budget is set on the campaign / ad sets). Change it in Meta Ads Manager.', 'flx-ads' ) );
		}
		$cur = self::currency();
		$r   = FLXADS_Audiences::graph( 'POST', $o['id'], array( 'daily_budget' => (string) (int) round( $amount * self::factor( $cur ) ) ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		self::log( $o, 'daily_budget', $o['daily'], $amount, $channel );
		self::flush();
		return array( 'id' => $o['id'], 'name' => $o['name'], 'daily_budget' => $amount, 'previous' => $o['daily'], 'currency' => $cur );
	}

	/** Bütçe değişikliğinin büyüklüğü (önizleme uyarısı için): yüzde, ya da null. */
	public static function change_pct( $from, $to ) {
		return $from > 0 ? round( 100 * ( $to - $from ) / $from ) : null;
	}

	private static function log( array $o, $field, $from, $to, $channel ) {
		$log   = (array) get_option( 'flxads_mgr_log', array() );
		$log[] = array(
			'at'      => flxads_now(),
			'user'    => get_current_user_id(),
			'channel' => (string) $channel,
			'id'      => $o['id'],
			'name'    => $o['name'],
			'field'   => $field,
			'from'    => $from,
			'to'      => $to,
		);
		update_option( 'flxads_mgr_log', array_slice( $log, -self::LOG_MAX ), false );
	}

	public static function history( $limit = 20 ) {
		return array_slice( array_reverse( (array) get_option( 'flxads_mgr_log', array() ) ), 0, (int) $limit );
	}
}
