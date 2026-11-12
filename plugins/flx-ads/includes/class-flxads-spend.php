<?php
/**
 * Flux Ads: reklam harcaması. Meta reklam hesabının günlük, reklam bazında harcaması Marketing API'den
 * (insights) çekilir; diğer platformlar için harcama elle girilir. Rapor harcamayı kanal, kampanya
 * (utm_campaign = kampanya adı ya da kimliği) ve reklam (utm_content = reklam adı) ile eşleyip
 * ROAS ve ödeme başı maliyeti hesaplar.
 *
 * Cron'a güvenilmez (Hostinger): saatlik iş varsa onunla, yoksa Flux Ads ekranı açılınca, yanıt
 * gönderildikten sonra eşitlenir. İlk eşitleme son 30 gün, sonrakiler son 7 gün (Meta geçmiş günleri düzeltir).
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Spend {

	const STALE      = 6 * HOUR_IN_SECONDS;
	const FIRST_DAYS = 30;
	const DAYS       = 7;
	const MAX_PAGES  = 20;

	public static function init() {
		add_action( 'flxads_retry', array( __CLASS__, 'maybe_sync' ) );
	}

	/** Meta harcama eşitlemesi için hesap + anahtar var mı? */
	public static function meta_ready() {
		return '' !== self::meta_account() && '' !== self::meta_token();
	}

	/** Reklam hesabı kimliği, yalnız rakam ("act_" öneki atılır). */
	public static function meta_account() {
		return preg_replace( '/\D+/', '', (string) flxads_setting( 'meta_account' ) );
	}

	/** Marketing API anahtarı (ads_read); boşsa Conversions API anahtarı denenir. */
	public static function meta_token() {
		$t = flxads_secret( 'meta_ads_token' );
		return '' !== $t ? $t : flxads_secret( 'meta_token' );
	}

	/** Son eşitleme durumu: [ at (gmt), ok, message, rows, since, until ]. */
	public static function status( $p = 'meta' ) {
		$s = (array) get_option( 'flxads_spend_sync', array() );
		return (array) ( $s[ $p ] ?? array() );
	}

	private static function set_status( $p, array $v ) {
		$s       = (array) get_option( 'flxads_spend_sync', array() );
		$s[ $p ] = $v + array( 'at' => flxads_now() );
		update_option( 'flxads_spend_sync', $s, false );
	}

	/** Eskiyse eşitle (cron ya da ekran). */
	public static function maybe_sync() {
		if ( ! self::meta_ready() ) {
			return;
		}
		$st = self::status( 'meta' );
		if ( ! empty( $st['at'] ) && strtotime( $st['at'] . ' UTC' ) > time() - self::STALE ) {
			return;
		}
		/* Aynı anda iki istek çekmesin. */
		if ( get_transient( 'flxads_spend_lock' ) ) {
			return;
		}
		set_transient( 'flxads_spend_lock', 1, 5 * MINUTE_IN_SECONDS );
		self::sync_meta();
		delete_transient( 'flxads_spend_lock' );
	}

	/** Ekran açılınca: eskiyse yanıt gönderildikten sonra eşitle (sayfa beklemesin). */
	public static function maybe_sync_later() {
		if ( ! self::meta_ready() ) {
			return;
		}
		add_action(
			'shutdown',
			static function () {
				if ( function_exists( 'fastcgi_finish_request' ) ) {
					fastcgi_finish_request();
				} elseif ( function_exists( 'litespeed_finish_request' ) ) {
					litespeed_finish_request();
				}
				self::maybe_sync();
			},
			60
		);
	}

	/**
	 * Meta insights: reklam bazında günlük harcama. Aralığın tamamı başarıyla gelirse o aralıktaki eski
	 * API satırları silinip yenileri yazılır (silinen reklamlar da düşer).
	 *
	 * @return true|string Hata metni.
	 */
	public static function sync_meta( $days = 0 ) {
		global $wpdb;
		if ( ! self::meta_ready() ) {
			return __( 'Enter the ad account ID and a key first.', 'flx-ads' );
		}
		$first = empty( self::status( 'meta' )['ok'] );
		$days  = $days ? (int) $days : ( $first ? self::FIRST_DAYS : self::DAYS );
		$since = wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
		$until = wp_date( 'Y-m-d' );
		$url   = add_query_arg(
			array(
				'level'          => 'ad',
				'fields'         => 'campaign_id,campaign_name,adset_name,ad_id,ad_name,spend,impressions,inline_link_clicks,account_currency',
				'time_increment' => 1,
				'time_range'     => rawurlencode( wp_json_encode( array( 'since' => $since, 'until' => $until ) ) ),
				'limit'          => 500,
				'access_token'   => rawurlencode( self::meta_token() ),
			),
			FLXADS_Send::META_API . 'act_' . self::meta_account() . '/insights'
		);
		$rows  = array();
		$start = time();
		for ( $page = 0; $url && $page < self::MAX_PAGES; $page++ ) {
			$r = wp_remote_get( $url, array( 'timeout' => 20 ) );
			if ( is_wp_error( $r ) ) {
				return self::fail( $r->get_error_message(), $since, $until );
			}
			$code = (int) wp_remote_retrieve_response_code( $r );
			$j    = json_decode( (string) wp_remote_retrieve_body( $r ), true );
			if ( $code < 200 || $code >= 300 || ! is_array( $j ) || ! empty( $j['error'] ) ) {
				return self::fail( 'HTTP ' . $code . ( isset( $j['error']['message'] ) ? ': ' . $j['error']['message'] : '' ), $since, $until );
			}
			foreach ( (array) ( $j['data'] ?? array() ) as $d ) {
				if ( empty( $d['date_start'] ) || ! isset( $d['spend'] ) ) {
					continue;
				}
				$rows[] = array(
					'platform'    => 'meta',
					'day'         => substr( (string) $d['date_start'], 0, 10 ),
					'ad_key'      => mb_substr( (string) ( $d['ad_id'] ?? '' ), 0, 40 ),
					'campaign_id' => mb_substr( (string) ( $d['campaign_id'] ?? '' ), 0, 40 ),
					'campaign'    => mb_substr( (string) ( $d['campaign_name'] ?? '' ), 0, 150 ),
					'adset'       => mb_substr( (string) ( $d['adset_name'] ?? '' ), 0, 150 ),
					'ad'          => mb_substr( (string) ( $d['ad_name'] ?? '' ), 0, 150 ),
					'spend'       => round( (float) $d['spend'], 2 ),
					'currency'    => strtoupper( substr( (string) ( $d['account_currency'] ?? '' ), 0, 3 ) ),
					'impressions' => (int) ( $d['impressions'] ?? 0 ),
					'clicks'      => (int) ( $d['inline_link_clicks'] ?? 0 ),
					'manual'      => 0,
					'updated_at'  => flxads_now(),
				);
			}
			$url = isset( $j['paging']['next'] ) ? (string) $j['paging']['next'] : '';
			if ( time() - $start > 40 ) {
				return self::fail( __( 'Meta answered too slowly; try again later.', 'flx-ads' ), $since, $until );
			}
		}
		if ( $url ) {
			return self::fail( __( 'Too many rows; only part of the period was read.', 'flx-ads' ), $since, $until );
		}
		$t = flxads_table( 'spend' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE platform = 'meta' AND manual = 0 AND day BETWEEN %s AND %s", $since, $until ) ); // phpcs:ignore WordPress.DB
		foreach ( $rows as $row ) {
			$wpdb->replace( $t, $row ); // phpcs:ignore WordPress.DB
		}
		self::set_status( 'meta', array( 'ok' => true, 'message' => '', 'rows' => count( $rows ), 'since' => $since, 'until' => $until ) );
		return true;
	}

	private static function fail( $msg, $since, $until ) {
		$prev = self::status( 'meta' );
		self::set_status(
			'meta',
			array(
				'ok'      => false,
				'message' => mb_substr( (string) $msg, 0, 300 ),
				'rows'    => 0,
				'since'   => $since,
				'until'   => $until,
				'last_ok' => ! empty( $prev['ok'] ) ? $prev['at'] : ( $prev['last_ok'] ?? '' ),
			)
		);
		return (string) $msg;
	}

	/** Elle harcama (Google, TikTok, afiş, influencer...): bir gün, kanal, kampanya, isteğe bağlı reklam. */
	public static function add_manual( array $in ) {
		global $wpdb;
		$day = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in['day'] ?? '' ) ) ? $in['day'] : wp_date( 'Y-m-d' );
		$ch  = isset( flxads_channel_labels()[ (string) ( $in['channel'] ?? '' ) ] ) && '' !== (string) $in['channel'] ? (string) $in['channel'] : 'other';
		$amt = self::parse_amount( (string) ( $in['spend'] ?? '' ) );
		if ( $amt <= 0 ) {
			return false;
		}
		$cur = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $in['currency'] ?? '' ) ) );
		$cur = 3 === strlen( $cur ) ? $cur : FLXADS_Report::base_currency();
		return (bool) $wpdb->insert( // phpcs:ignore WordPress.DB
			flxads_table( 'spend' ),
			array(
				'platform'    => $ch,
				'day'         => $day,
				'ad_key'      => 'm-' . wp_generate_password( 10, false ),
				'campaign_id' => '',
				'campaign'    => mb_substr( sanitize_text_field( (string) ( $in['campaign'] ?? '' ) ), 0, 150 ),
				'adset'       => '',
				'ad'          => mb_substr( sanitize_text_field( (string) ( $in['ad'] ?? '' ) ), 0, 150 ),
				'spend'       => $amt,
				'currency'    => $cur,
				'impressions' => 0,
				'clicks'      => 0,
				'manual'      => 1,
				'updated_at'  => flxads_now(),
			)
		);
	}

	/** "1.200", "1.200,50", "1200.5", "1 200" → sayı. */
	public static function parse_amount( $s ) {
		$s = preg_replace( '/[^\d.,]/', '', $s );
		if ( false !== strpos( $s, ',' ) ) {
			$s = str_replace( array( '.', ',' ), array( '', '.' ), $s );
		} elseif ( preg_match( '/^\d{1,3}(\.\d{3})+$/', $s ) ) {
			$s = str_replace( '.', '', $s );
		}
		return round( (float) $s, 2 );
	}

	public static function delete_manual( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( flxads_table( 'spend' ), array( 'id' => (int) $id, 'manual' => 1 ) ); // phpcs:ignore WordPress.DB
	}

	public static function manual_rows( $limit = 50 ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxads_table( 'spend' ) . ' WHERE manual = 1 ORDER BY day DESC, id DESC LIMIT %d', (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/** Eşleştirme anahtarı: "Fidan Kasım 2026" = "fidan-kasim-2026". */
	public static function norm( $s ) {
		return sanitize_title( remove_accents( trim( (string) $s ) ) );
	}

	/**
	 * Aralıktaki harcama, ana para birimine çevrilmiş. Dönüş:
	 * [ total, mixed, by_channel{ch:amt}, by_campaign{norm:[amt, ch, name, ids[]]}, by_ad{norm:[amt, ch, name]}, impressions, clicks ]
	 */
	public static function totals( $from, $to, $channel = '' ) {
		global $wpdb;
		$w = $wpdb->prepare( 'day BETWEEN %s AND %s', $from, $to );
		if ( '' !== (string) $channel && '*' !== $channel ) {
			$w .= $wpdb->prepare( ' AND platform = %s', '-' === $channel ? '' : (string) $channel );
		}
		$rows = (array) $wpdb->get_results( 'SELECT platform, campaign_id, campaign, ad, currency, SUM(spend) AS amt, SUM(impressions) AS imp, SUM(clicks) AS clk, MIN(day) AS d FROM ' . flxads_table( 'spend' ) . " WHERE $w GROUP BY platform, campaign_id, campaign, ad, currency", ARRAY_A ); // phpcs:ignore WordPress.DB
		$out  = array( 'total' => 0.0, 'mixed' => false, 'by_channel' => array(), 'by_campaign' => array(), 'by_ad' => array(), 'impressions' => 0, 'clicks' => 0 );
		foreach ( $rows as $r ) {
			$amt = FLXADS_Report::to_base( (float) $r['amt'], (string) $r['currency'], (string) $r['d'] );
			if ( null === $amt ) {
				$out['mixed'] = true;
				continue;
			}
			$ch                        = (string) $r['platform'];
			$out['total']             += $amt;
			$out['impressions']       += (int) $r['imp'];
			$out['clicks']            += (int) $r['clk'];
			$out['by_channel'][ $ch ]  = ( $out['by_channel'][ $ch ] ?? 0 ) + $amt;
			$ck                        = self::norm( $r['campaign'] );
			if ( '' !== $ck ) {
				if ( ! isset( $out['by_campaign'][ $ck ] ) ) {
					$out['by_campaign'][ $ck ] = array( 0.0, $ch, (string) $r['campaign'], array() );
				}
				$out['by_campaign'][ $ck ][0] += $amt;
				if ( '' !== (string) $r['campaign_id'] ) {
					$out['by_campaign'][ $ck ][3][ (string) $r['campaign_id'] ] = true;
				}
			}
			$ak = self::norm( $r['ad'] );
			if ( '' !== $ak ) {
				if ( ! isset( $out['by_ad'][ $ak ] ) ) {
					$out['by_ad'][ $ak ] = array( 0.0, $ch, (string) $r['ad'] );
				}
				$out['by_ad'][ $ak ][0] += $amt;
			}
		}
		return $out;
	}

	/** Harcama kaydı var mı (rapordaki sütunları göstermek için)? */
	public static function any() {
		global $wpdb;
		return (bool) $wpdb->get_var( 'SELECT 1 FROM ' . flxads_table( 'spend' ) . ' LIMIT 1' ); // phpcs:ignore WordPress.DB
	}
}
