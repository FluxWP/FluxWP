<?php
/**
 * Flux Ads: sunucu dönüşümleri. Meta Conversions API, TikTok Events API, GA4 Measurement Protocol.
 *
 * Kuyruk (wp_flxads_conversions): her sipariş × platform bir satır, olay kimliği tekil. Gönderim yanıt
 * gönderildikten sonra aynı istekte yapılır; olmazsa saatlik iş ve Flux Ads ekranı açılınca yeniden denenir
 * (en fazla 6 deneme, artan bekleme). Kişisel veri (e-posta, telefon) yalnız ayara ve kişinin reklam iznine
 * göre, SHA-256 ile gider; ham hâli hiçbir platforma gitmez.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Send {

	const MAX_ATTEMPTS = 6;
	const META_API     = 'https://graph.facebook.com/v21.0/';
	const TIKTOK_API   = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';
	const GA4_API      = 'https://www.google-analytics.com/mp/collect';
	const GA4_DEBUG    = 'https://www.google-analytics.com/debug/mp/collect';

	/** @var int[] Bu istekte gönderilecek satırlar. */
	private static $now = array();

	public static function init() {
		add_action( 'flxads_retry', array( __CLASS__, 'run_queue' ) );
		add_action( 'shutdown', array( __CLASS__, 'flush' ), 50 );
	}

	/** Sipariş için açık platformlara Purchase satırı açar. $consent: ziyaretçi sitede onay verdi mi. */
	public static function queue( $order, $consent ) {
		global $wpdb;
		$t   = flxads_table( 'conversions' );
		$now = flxads_now();
		foreach ( array_keys( flxads_platforms() ) as $p ) {
			if ( ! flxads_platform_on( $p, true ) ) {
				continue;
			}
			$status = $consent ? 'pending' : 'skipped';
			/* GA4: teşekkür sayfası tarayıcıdan gönderdiyse çift sayılmasın diye 30 dk beklenir, sonra bakılır. */
			$next = 'ga4' === $p ? gmdate( 'Y-m-d H:i:s', time() + 30 * MINUTE_IN_SECONDS ) : $now;
			$ok   = $wpdb->query( // phpcs:ignore WordPress.DB
				$wpdb->prepare(
					"INSERT IGNORE INTO $t (order_id, platform, event_id, event_name, value, currency, status, last_error, created_at, next_at) VALUES (%d, %s, %s, %s, %f, %s, %s, %s, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$order->get_id(),
					$p,
					FLXADS_Orders::event_id( $order ),
					'Purchase',
					round( (float) $order->get_total(), 2 ),
					$order->get_currency(),
					$status,
					$consent ? '' : 'no_consent',
					$now,
					$next
				)
			);
			if ( $ok && $consent && 'ga4' !== $p ) {
				self::$now[] = (int) $wpdb->insert_id;
			}
		}
	}

	/** İstek sonunda bekleyenleri gönder (yanıt tarayıcıya gittikten sonra). */
	public static function flush() {
		if ( ! self::$now ) {
			return;
		}
		$ids       = array_unique( self::$now );
		self::$now = array();
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}
		foreach ( $ids as $id ) {
			self::send_id( $id );
		}
	}

	/** Zamanı gelmiş bekleyen/başarısız satırlar (saatlik iş + ekran açılınca). */
	public static function run_queue( $limit = 25 ) {
		global $wpdb;
		update_option( 'flxads_heartbeat', time(), false );
		$t   = flxads_table( 'conversions' );
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $t WHERE status IN ('pending','failed') AND attempts < %d AND next_at <= %s ORDER BY id LIMIT %d", self::MAX_ATTEMPTS, flxads_now(), (int) $limit ) ); // phpcs:ignore WordPress.DB
		foreach ( $ids as $id ) {
			self::send_id( (int) $id );
		}
		return count( $ids );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxads_table( 'conversions' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	private static function mark( $id, array $d ) {
		global $wpdb;
		if ( isset( $d['last_error'] ) ) {
			$d['last_error'] = mb_substr( wp_strip_all_tags( (string) $d['last_error'] ), 0, 250 );
		}
		$wpdb->update( flxads_table( 'conversions' ), $d, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/** Bir satırı gönder. */
	public static function send_id( $id ) {
		$row = self::get( $id );
		if ( ! $row || ! in_array( $row['status'], array( 'pending', 'failed' ), true ) ) {
			return false;
		}
		$order = wc_get_order( (int) $row['order_id'] );
		if ( ! $order ) {
			self::mark( $id, array( 'status' => 'skipped', 'last_error' => 'order_missing' ) );
			return false;
		}
		$p = $row['platform'];
		if ( ! flxads_platform_on( $p, true ) ) {
			self::mark( $id, array( 'status' => 'skipped', 'last_error' => 'platform_off' ) );
			return false;
		}
		if ( 'ga4' === $p && $order->get_meta( '_flxads_browser' ) ) {
			self::mark( $id, array( 'status' => 'skipped', 'last_error' => 'browser_sent' ) );
			return false;
		}
		$res  = self::request( $p, $order, $row );
		$att  = (int) $row['attempts'] + 1;
		$data = array( 'attempts' => $att );
		if ( true === $res ) {
			$data += array( 'status' => 'sent', 'sent_at' => flxads_now(), 'last_error' => '' );
		} else {
			$data += array(
				'status'     => 'failed',
				'last_error' => (string) $res,
				'next_at'    => gmdate( 'Y-m-d H:i:s', time() + (int) min( DAY_IN_SECONDS, 300 * pow( 2, $att ) ) ),
			);
		}
		self::mark( $id, $data );
		return true === $res;
	}

	/** Bu siparişte kişisel veri (hash'li e-posta/telefon) gönderilebilir mi? */
	private static function pii_allowed( $order ) {
		$mode = (string) flxads_setting( 'pii' );
		if ( 'always' === $mode ) {
			return true;
		}
		if ( 'never' === $mode || ! function_exists( 'flx_person_find' ) || ! function_exists( 'flx_person_get' ) ) {
			return false;
		}
		$pid = (int) flx_person_find( array( 'email' => (string) $order->get_billing_email(), 'phone' => (string) $order->get_billing_phone() ) );
		$p   = $pid ? flx_person_get( $pid ) : null;
		return is_array( $p ) && ! empty( $p['consent_ads'] );
	}

	/** @return true|string true ya da hata metni. */
	private static function request( $p, $order, array $row ) {
		list( $t, , $a ) = FLXADS_Orders::attribution( $order );
		$pii             = self::pii_allowed( $order );
		$email           = $pii ? flxads_hash( $order->get_billing_email() ) : '';
		$phone           = $pii ? flxads_hash_phone( $order->get_billing_phone() ) : '';
		$ip              = (string) $order->get_customer_ip_address();
		$ua              = (string) $order->get_customer_user_agent();
		$time            = $order->get_date_paid() ? $order->get_date_paid()->getTimestamp() : time();
		$url             = ! empty( $t['lp'] ) ? (string) $t['lp'] : home_url( '/' );
		$value           = round( (float) $row['value'], 2 );
		$cur             = (string) $row['currency'];
		$s               = flxads_settings();

		if ( 'meta' === $p ) {
			$user = array_filter(
				array(
					'em'                => $email ? array( $email ) : null,
					'ph'                => $phone ? array( $phone ) : null,
					'client_ip_address' => $ip,
					'client_user_agent' => $ua,
					'fbp'               => $a['fbp'] ?? '',
					'fbc'               => $a['fbc'] ?? '',
				)
			);
			$ev   = array(
				'event_name'       => 'Purchase',
				'event_time'       => $time,
				'event_id'         => $row['event_id'],
				'action_source'    => 'website',
				'event_source_url' => $url,
				'user_data'        => $user,
				'custom_data'      => array(
					'currency'     => $cur,
					'value'        => $value,
					'content_type' => 'product',
					'order_id'     => (string) $order->get_order_number(),
					'content_ids'  => wp_list_pluck( FLXADS_Orders::items( $order ), 'id' ),
				),
			);
			$body = array(
				'data'         => wp_json_encode( array( $ev ) ),
				'access_token' => flxads_secret( 'meta_token' ),
			);
			if ( '' !== (string) $s['meta_test'] ) {
				$body['test_event_code'] = (string) $s['meta_test'];
			}
			$r = wp_remote_post( self::META_API . rawurlencode( (string) $s['meta_pixel'] ) . '/events', array( 'timeout' => 8, 'body' => $body ) );
			return self::check( $r, 'meta' );
		}

		if ( 'tiktok' === $p ) {
			$user = array_filter(
				array(
					'email'      => $email,
					'phone'      => $phone,
					'ttclid'     => $t['ttclid'] ?? '',
					'ttp'        => $a['ttp'] ?? '',
					'ip'         => $ip,
					'user_agent' => $ua,
				)
			);
			$body = array(
				'event_source'    => 'web',
				'event_source_id' => strtoupper( (string) $s['tiktok_pixel'] ),
				'data'            => array(
					array(
						'event'      => 'CompletePayment',
						'event_time' => $time,
						'event_id'   => $row['event_id'],
						'user'       => (object) $user,
						'page'       => array( 'url' => $url ),
						'properties' => array(
							'currency'     => $cur,
							'value'        => $value,
							'content_type' => 'product',
							'order_id'     => (string) $order->get_order_number(),
						),
					),
				),
			);
			if ( '' !== (string) $s['tiktok_test'] ) {
				$body['test_event_code'] = (string) $s['tiktok_test'];
			}
			$r = wp_remote_post(
				self::TIKTOK_API,
				array(
					'timeout' => 8,
					'headers' => array( 'Content-Type' => 'application/json', 'Access-Token' => flxads_secret( 'tiktok_token' ) ),
					'body'    => wp_json_encode( $body ),
				)
			);
			return self::check( $r, 'tiktok' );
		}

		if ( 'ga4' === $p ) {
			$cid  = ! empty( $a['ga'] ) ? (string) $a['ga'] : ( (string) ( crc32( (string) $order->get_order_key() ) ) . '.' . $time );
			$body = array(
				'client_id'        => $cid,
				'timestamp_micros' => $time * 1000000,
				'events'           => array(
					array(
						'name'   => 'purchase',
						'params' => array_filter(
							array(
								'transaction_id'       => (string) $order->get_order_number(),
								'value'                => $value,
								'currency'             => $cur,
								'campaign'             => $t['utm_campaign'] ?? '',
								'source'               => $t['utm_source'] ?? '',
								'medium'               => $t['utm_medium'] ?? '',
								'engagement_time_msec' => 1,
								'items'                => array_map(
									static function ( $i ) {
										return array( 'item_id' => $i['id'], 'item_name' => $i['name'], 'price' => $i['price'], 'quantity' => $i['qty'] );
									},
									FLXADS_Orders::items( $order )
								),
							)
						),
					),
				),
			);
			$r = wp_remote_post( add_query_arg( array( 'measurement_id' => flxads_ga4_id(), 'api_secret' => flxads_secret( 'ga4_secret' ) ), self::GA4_API ), array( 'timeout' => 8, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ) ) );
			return self::check( $r, 'ga4' );
		}
		return 'unknown_platform';
	}

	/** Yanıtı değerlendir; gizli değer hata metnine girmez. */
	private static function check( $r, $p ) {
		if ( is_wp_error( $r ) ) {
			return $r->get_error_message();
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		$json = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( 'meta' === $p ) {
			if ( $code >= 200 && $code < 300 && empty( $json['error'] ) ) {
				return true;
			}
			return 'HTTP ' . $code . ( isset( $json['error']['message'] ) ? ': ' . $json['error']['message'] : '' );
		}
		if ( 'tiktok' === $p ) {
			if ( 200 === $code && isset( $json['code'] ) && 0 === (int) $json['code'] ) {
				return true;
			}
			return 'HTTP ' . $code . ( isset( $json['message'] ) ? ': ' . $json['message'] : '' );
		}
		return ( $code >= 200 && $code < 300 ) ? true : 'HTTP ' . $code;
	}

	/**
	 * Bağlantı testi (yalnız yönetici "Test"e basınca). Gerçek dönüşüm yazmaz: Meta/TikTok test olay koduyla,
	 * GA4 doğrulama ucuyla. Sonuç `flxads_tests` seçeneğinde saklanır.
	 *
	 * @return array [ ok (bool), message ]
	 */
	public static function test( $p ) {
		$s   = flxads_settings();
		$out = array( false, __( 'Fill in the ID and the key first.', 'flx-ads' ) );
		if ( 'meta' === $p && flxads_platform_on( 'meta', true ) ) {
			if ( '' === (string) $s['meta_test'] ) {
				$r   = wp_remote_get( add_query_arg( array( 'fields' => 'id,name', 'access_token' => flxads_secret( 'meta_token' ) ), self::META_API . rawurlencode( (string) $s['meta_pixel'] ) ), array( 'timeout' => 8 ) );
				$res = self::check( $r, 'meta' );
				$j   = is_wp_error( $r ) ? array() : json_decode( (string) wp_remote_retrieve_body( $r ), true );
				/* translators: %s: pixel/dataset name */
				$out = true === $res ? array( true, sprintf( __( 'Connected to "%s". Enter a test event code to see a test event in Events Manager.', 'flx-ads' ), (string) ( $j['name'] ?? $s['meta_pixel'] ) ) ) : array( false, $res );
			} else {
				$ev  = array( 'event_name' => 'PageView', 'event_time' => time(), 'event_id' => 'flxads-test-' . wp_generate_password( 8, false ), 'action_source' => 'website', 'event_source_url' => home_url( '/' ), 'user_data' => array( 'client_ip_address' => '127.0.0.1', 'client_user_agent' => 'FluxAds/' . FLXADS_VERSION ) );
				$r   = wp_remote_post( self::META_API . rawurlencode( (string) $s['meta_pixel'] ) . '/events', array( 'timeout' => 8, 'body' => array( 'data' => wp_json_encode( array( $ev ) ), 'access_token' => flxads_secret( 'meta_token' ), 'test_event_code' => (string) $s['meta_test'] ) ) );
				$res = self::check( $r, 'meta' );
				$out = true === $res ? array( true, __( 'Test event sent. Check Events Manager › Test events.', 'flx-ads' ) ) : array( false, $res );
			}
		} elseif ( 'tiktok' === $p && flxads_platform_on( 'tiktok', true ) ) {
			if ( '' === (string) $s['tiktok_test'] ) {
				$out = array( false, __( 'Enter the test event code from TikTok Events Manager › Test events, then test again.', 'flx-ads' ) );
			} else {
				$body = array( 'event_source' => 'web', 'event_source_id' => strtoupper( (string) $s['tiktok_pixel'] ), 'test_event_code' => (string) $s['tiktok_test'], 'data' => array( array( 'event' => 'ViewContent', 'event_time' => time(), 'event_id' => 'flxads-test-' . wp_generate_password( 8, false ), 'user' => array( 'ip' => '127.0.0.1', 'user_agent' => 'FluxAds/' . FLXADS_VERSION ), 'page' => array( 'url' => home_url( '/' ) ) ) ) );
				$r    = wp_remote_post( self::TIKTOK_API, array( 'timeout' => 8, 'headers' => array( 'Content-Type' => 'application/json', 'Access-Token' => flxads_secret( 'tiktok_token' ) ), 'body' => wp_json_encode( $body ) ) );
				$res  = self::check( $r, 'tiktok' );
				$out  = true === $res ? array( true, __( 'Test event sent. Check TikTok Events Manager › Test events.', 'flx-ads' ) ) : array( false, $res );
			}
		} elseif ( 'ga4' === $p && flxads_platform_on( 'ga4', true ) ) {
			$body = array( 'client_id' => '1.' . time(), 'events' => array( array( 'name' => 'purchase', 'params' => array( 'transaction_id' => 'test', 'value' => 1, 'currency' => 'TRY' ) ) ) );
			$r    = wp_remote_post( add_query_arg( array( 'measurement_id' => flxads_ga4_id(), 'api_secret' => flxads_secret( 'ga4_secret' ) ), self::GA4_DEBUG ), array( 'timeout' => 8, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ) ) );
			if ( is_wp_error( $r ) ) {
				$out = array( false, $r->get_error_message() );
			} else {
				$j    = json_decode( (string) wp_remote_retrieve_body( $r ), true );
				$msgs = isset( $j['validationMessages'] ) && is_array( $j['validationMessages'] ) ? wp_list_pluck( $j['validationMessages'], 'description' ) : array();
				$code = (int) wp_remote_retrieve_response_code( $r );
				$out  = $code >= 200 && $code < 300 && ! $msgs ? array( true, __( 'Google accepted the test event (validation only; nothing was recorded).', 'flx-ads' ) ) : array( false, $msgs ? implode( ' ', $msgs ) : 'HTTP ' . $code );
			}
		}
		$tests       = (array) get_option( 'flxads_tests', array() );
		$tests[ $p ] = array( 'ok' => $out[0], 'message' => mb_substr( (string) $out[1], 0, 300 ), 'at' => flxads_now() );
		update_option( 'flxads_tests', $tests, false );
		return $out;
	}

	/** Yeniden dene: başarısız satırı sıfırla ve hemen gönder. */
	public static function retry( $id ) {
		$row = self::get( $id );
		if ( ! $row || 'sent' === $row['status'] ) {
			return false;
		}
		if ( 'skipped' === $row['status'] && 'no_consent' === $row['last_error'] ) {
			return false; // onaysız ziyaretçinin dönüşümü elle de gönderilmez
		}
		self::mark( $id, array( 'status' => 'pending', 'attempts' => 0, 'next_at' => flxads_now() ) );
		return self::send_id( $id );
	}

	/** Durum sayıları (sistem kontrolü, rapor). */
	public static function counts() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT status, COUNT(*) n FROM ' . flxads_table( 'conversions' ) . ' GROUP BY status', ARRAY_A ); // phpcs:ignore WordPress.DB
		$out  = array( 'pending' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0 );
		foreach ( (array) $rows as $r ) {
			$out[ $r['status'] ] = (int) $r['n'];
		}
		return $out;
	}
}
