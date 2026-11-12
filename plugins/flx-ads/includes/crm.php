<?php
/**
 * Flux Ads × Flux CRM: CRM kuruluysa reklamla gelen ödeme kişinin zaman tünelinde "Reklam dönüşümü" olur,
 * kişi kartında "Reklam kaynağı" kartı çıkar ve kişide ilk kampanya boşsa doldurulur. CRM yoksa hiçbiri çalışmaz,
 * Flux Ads kendi raporuyla tek başına çalışır.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_CRM {

	public static function init() {
		add_filter( 'flxcrm_event_types', array( __CLASS__, 'event_types' ) );
		add_action( 'flxads_order_attributed', array( __CLASS__, 'attributed' ), 10, 3 );
		add_action( 'flxcrm_person_card_side', array( __CLASS__, 'card' ), 15, 2 );
		add_action( 'flx_person_merged', array( __CLASS__, 'merged' ), 10, 2 );
		add_filter(
			'flxcrm_sources',
			static function ( $names ) {
				$names['ads'] = 'Flux Ads';
				return $names;
			}
		);
	}

	public static function active() {
		return class_exists( 'FLXCRM_Events' ) && function_exists( 'flxcrm_icon' );
	}

	public static function event_types( $types ) {
		$types['ad_conversion'] = array( __( 'Ad conversion', 'flx-ads' ), 'trend' );
		return $types;
	}

	/** Kısa açıklama: "Meta · fidan-kasim · video-1". */
	public static function summary( array $row ) {
		$parts = array( flxads_channel_label( $row['channel'] ) );
		if ( '' !== (string) $row['campaign'] ) {
			$parts[] = $row['campaign'];
		}
		if ( '' !== (string) $row['content'] ) {
			$parts[] = $row['content'];
		}
		return implode( ' · ', $parts );
	}

	/** @var array İstek sonunda işlenecek atıflar (kişi kaydı aynı istekte başka paketçe açılabilir). */
	private static $later = array();

	public static function attributed( $order_id, array $row, $order ) {
		if ( '' === (string) $row['channel'] ) {
			return; // reklamsız ödeme zaman tünelinde zaten bağış/sipariş olarak var
		}
		if ( ! self::$later ) {
			add_action( 'shutdown', array( __CLASS__, 'run_later' ), 20 );
		}
		self::$later[ (int) $order_id ] = $row;
	}

	public static function run_later() {
		$todo        = self::$later;
		self::$later = array();
		foreach ( $todo as $id => $row ) {
			$order = wc_get_order( $id );
			if ( $order ) {
				self::apply( $id, $row, $order );
			}
		}
	}

	/** Kişiyi bul, zaman tüneline yaz, ilk kampanyayı doldur. */
	public static function apply( $order_id, array $row, $order ) {
		$pid = (int) $row['person_id'];
		if ( ! $pid && function_exists( 'flx_person_find' ) ) {
			$pid = (int) flx_person_find( array( 'email' => (string) $order->get_billing_email(), 'phone' => (string) $order->get_billing_phone() ) );
		}
		if ( ! $pid ) {
			return;
		}
		if ( self::active() ) {
			/* Tutar 0: bağışın kendisi zaten Donate/Commerce olayı olarak sayılıyor, ömür boyu değer iki kez artmasın. */
			FLXCRM_Events::add(
				$pid,
				'ad_conversion',
				array(
					'source' => 'ads',
					'title'  => self::summary( $row ) . ' — ' . flxads_money( $row['total'], $row['currency'] ),
					'paid'   => 1,
					'uniq'   => 'ads:order:' . (int) $order_id,
					'at'     => $row['paid_at'],
					'url'    => $order->get_edit_order_url(),
					'data'   => array_filter( array( 'channel' => $row['channel'], 'campaign' => $row['campaign'], 'content' => $row['content'], 'source' => $row['source'], 'medium' => $row['medium'], 'order_id' => (int) $order_id ) ),
				)
			);
		}
		/* Kişinin "ilk kampanya" alanı boşsa ilk dokunuşla doldurulur (CRM yalnız boş alanı yazar). */
		if ( function_exists( 'flx_person_get' ) && function_exists( 'flx_person_ensure' ) ) {
			$p = flx_person_get( $pid );
			if ( is_array( $p ) && array_key_exists( 'utm_source', $p ) && '' === (string) $p['utm_source'] && '' === (string) ( $p['utm_campaign'] ?? '' ) ) {
				list( , $first ) = FLXADS_Orders::attribution( $order );
				$src             = (string) ( $first['utm_source'] ?? $row['source'] );
				$cmp             = (string) ( $first['utm_campaign'] ?? $row['campaign'] );
				flx_person_ensure(
					array(
						'email'        => (string) $p['email'],
						'phone'        => (string) $p['phone'],
						'user_id'      => (int) ( $p['user_id'] ?? 0 ),
						'utm_source'   => mb_substr( '' !== $src ? $src : $row['channel'], 0, 100 ),
						'utm_campaign' => mb_substr( $cmp, 0, 100 ),
					)
				);
			}
		}
		if ( ! (int) $row['person_id'] ) {
			global $wpdb;
			$wpdb->update( flxads_table( 'orders' ), array( 'person_id' => $pid ), array( 'order_id' => (int) $order_id ) ); // phpcs:ignore WordPress.DB
		}
	}

	/** Kişi kartı yan sütunu: reklamla gelen ödemeleri. */
	public static function card( $c, $context = '' ) {
		if ( ! self::active() || ! flxads_can() || empty( $c['id'] ) ) {
			return;
		}
		$rows = array_values(
			array_filter(
				FLXADS_Report::for_person( (int) $c['id'], 20 ),
				static function ( $r ) {
					return '' !== (string) $r['channel'];
				}
			)
		);
		echo '<section class="flxcrm-card flxads-crm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'trend', 16 ) . esc_html__( 'Ad source', 'flx-ads' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! $rows ) {
			echo '<p class="flxcrm-muted">' . esc_html__( 'No payment from this contact came through an ad.', 'flx-ads' ) . '</p></section>';
			return;
		}
		echo '<ul class="flxcrm-list flxcrm-list--plain">';
		foreach ( array_slice( $rows, 0, 6 ) as $r ) {
			$o   = wc_get_order( (int) $r['order_id'] );
			$url = $o ? $o->get_edit_order_url() : '';
			echo '<li>' . ( $url ? '<a href="' . esc_url( $url ) . '">' : '<span>' ) . '<span class="flxcrm-list__main"><strong>' . esc_html( self::summary( $r ) ) . '</strong><small>' . esc_html( flxads_money( $r['total'], $r['currency'] ) . ' · ' . flxads_date( $r['paid_at'], 'date' ) ) . '</small></span>' . ( $url ? flxcrm_icon( 'arrow', 14 ) . '</a>' : '</span>' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</ul>';
		$first = end( $rows );
		if ( $first && '' !== (string) $first['ft_channel'] ) {
			/* translators: %s: channel and campaign */
			echo '<p class="flxcrm-muted">' . esc_html( sprintf( __( 'First came through: %s', 'flx-ads' ), trim( flxads_channel_label( $first['ft_channel'] ) . ( '' !== (string) $first['ft_campaign'] ? ' · ' . $first['ft_campaign'] : '' ) ) ) ) . '</p>';
		}
		if ( flxads_can() ) {
			echo '<p><a href="' . esc_url( flxads_admin_url( 'report' ) ) . '">' . esc_html__( 'Ad report', 'flx-ads' ) . '</a></p>';
		}
		echo '</section>';
	}

	/** Kişiler birleşince atıf satırları kalan kişiye geçer. */
	public static function merged( $primary, $dup ) {
		global $wpdb;
		$wpdb->update( flxads_table( 'orders' ), array( 'person_id' => (int) $primary ), array( 'person_id' => (int) $dup ) ); // phpcs:ignore WordPress.DB
	}
}
