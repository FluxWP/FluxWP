<?php
/**
 * Flux Mail → zaman tüneli: kişiye giden e-posta, SMS ve WhatsApp bildirimleri (gönderim kaydından) ve posta
 * kutusuna gelen e-postalar. Yalnız var olan kişilere yazılır; giden iletiden kişi oluşturulmaz.
 * Flux Chat'in kendi WhatsApp mesajları Chat kaynağından gelir, burada tekrar yazılmaz.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Adapter_Mail {

	const KEY = 'mail';

	public static function enabled() {
		return (bool) flxcrm_setting( 'src_mail' ) && function_exists( 'flxi_table' );
	}

	public static function label() {
		return 'Flux Mail';
	}

	public static function init() {
		if ( ! self::enabled() ) {
			return;
		}
		add_action( 'flxi_mail_logged', array( __CLASS__, 'on_logged' ), 20, 2 );
		add_action( 'flxi_message_sent', array( __CLASS__, 'on_message' ), 20, 5 );
		add_action( 'flxi_mailbox_received', array( __CLASS__, 'on_received' ), 20, 2 );
	}

	/** Alıcı metninden e-postalar ("Ad <a@b>, c@d"). */
	private static function emails( $list ) {
		preg_match_all( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', (string) $list, $m );
		return array_values( array_unique( array_filter( array_map( 'flxcrm_email', $m[0] ) ) ) );
	}

	private static function snippet( $text, $len = 600 ) {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
		return mb_substr( $text, 0, $len );
	}

	/** Kaynağı Flux Chat olan ileti (Chat kaynağı zaten yazar). */
	private static function from_chat( $source ) {
		return 0 === strpos( (string) $source, 'flx-chat' ) || 'chat' === (string) $source;
	}

	/** Gönderim kaydı satırını kişilere yazar. */
	public static function log_row( array $r ) {
		if ( 'sent' !== $r['status'] || self::from_chat( $r['source'] ) ) {
			return 0;
		}
		$ch   = (string) $r['channel'];
		$cids = array();
		if ( 'email' === $ch ) {
			foreach ( self::emails( $r['recipient'] ) as $e ) {
				$cids[] = FLXCRM_Contacts::find( array( 'email' => $e ) );
			}
		} else {
			$cids[] = FLXCRM_Contacts::find( array( 'phone' => $r['recipient'] ) );
		}
		$n = 0;
		foreach ( array_unique( array_filter( array_map( 'intval', $cids ) ) ) as $cid ) {
			$type = 'email' === $ch ? 'email_sent' : ( 'sms' === $ch ? 'sms_sent' : 'message_out' );
			FLXCRM_Events::add(
				$cid,
				$type,
				array(
					'source' => self::KEY,
					'uniq'   => 'mail:log:' . (int) $r['id'] . ':' . $cid,
					'title'  => 'email' === $ch ? (string) $r['subject'] : ( 'sms' === $ch ? __( 'SMS sent', 'flx-crm' ) : __( 'WhatsApp notification', 'flx-crm' ) ),
					'url'    => 'admin.php?page=flxi-kayitlar&channel=' . rawurlencode( $ch ) . '&s=' . rawurlencode( 'email' === $ch ? (string) ( self::emails( $r['recipient'] )[0] ?? '' ) : (string) $r['recipient'] ),
					'at'     => (string) $r['created_at'],
					'data'   => array( 'body' => self::snippet( $r['body'] ), 'template' => (string) $r['template'], 'by' => (string) $r['source'] ),
				)
			);
			++$n;
		}
		return $n;
	}

	public static function on_logged( $id, $status ) {
		if ( 'sent' !== $status ) {
			return;
		}
		try {
			global $wpdb;
			$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxi_table( 'log' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
			if ( $r ) {
				self::log_row( $r );
			}
		} catch ( \Throwable $e ) {
			error_log( 'flx-crm mail: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	/** SMS / WhatsApp: gönderim kaydı varsa ondan, yoksa (kayıt kapalı) iletinin kendisinden. */
	public static function on_message( $channel, $to, $text, $r, $args ) {
		if ( empty( $r['ok'] ) || 'email' === $channel || self::from_chat( $args['source'] ?? '' ) ) {
			return;
		}
		try {
			global $wpdb;
			$row = null;
			if ( ! isset( $args['log'] ) || $args['log'] ) {
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxi_table( 'log' ) . ' WHERE channel = %s AND recipient = %s ORDER BY id DESC LIMIT 1', $channel, (string) $to ), ARRAY_A ); // phpcs:ignore WordPress.DB
			}
			if ( ! $row ) {
				$cid = FLXCRM_Contacts::find( array( 'phone' => $to ) );
				if ( $cid ) {
					FLXCRM_Events::add(
						$cid,
						'sms' === $channel ? 'sms_sent' : 'message_out',
						array(
							'source' => self::KEY,
							'uniq'   => 'mail:msg:' . md5( $channel . $to . $text . flxcrm_now() ),
							'title'  => 'sms' === $channel ? __( 'SMS sent', 'flx-crm' ) : __( 'WhatsApp notification', 'flx-crm' ),
							'data'   => array( 'body' => self::snippet( $text ), 'by' => (string) ( $args['source'] ?? '' ) ),
						)
					);
				}
				return;
			}
			self::log_row( $row );
		} catch ( \Throwable $e ) {
			error_log( 'flx-crm mail: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	/** Posta kutusuna gelen e-posta (yalnız var olan kişiden). */
	public static function on_received( $id, $row ) {
		try {
			$cid = FLXCRM_Contacts::find( array( 'email' => $row['from_email'] ?? '' ) );
			if ( ! $cid ) {
				return;
			}
			FLXCRM_Events::add(
				$cid,
				'email_in',
				array(
					'source' => self::KEY,
					'uniq'   => 'mail:in:' . (int) $id,
					'title'  => (string) ( $row['subject'] ?? '' ),
					'url'    => 'admin.php?page=flxi-posta&id=' . (int) $id,
					'at'     => ! empty( $row['mail_date'] ) ? (string) $row['mail_date'] : flxcrm_now(),
					'data'   => array( 'body' => self::snippet( $row['snippet'] ?? '' ) ),
				)
			);
		} catch ( \Throwable $e ) {
			error_log( 'flx-crm mail: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	/** Geçmiş: gönderim kaydı (sent) ve posta kutusu gelenleri, 500'lük parçalar. */
	public static function backfill( $page, $per ) {
		global $wpdb;
		$per   = $per * 5;
		$log   = flxi_table( 'log' );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $log WHERE status = 'sent'" ); // phpcs:ignore WordPress.DB
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $log WHERE status = 'sent' ORDER BY id LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( $rows as $r ) {
			self::log_row( $r );
		}
		$done = count( $rows ) < $per;
		if ( $done ) {
			$mail = flxi_table( 'mail' );
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $mail ) ) ) {
				$in = (array) $wpdb->get_results( "SELECT id, from_email, subject, snippet, mail_date FROM $mail WHERE folder = 'inbox' AND is_system = 0 ORDER BY id DESC LIMIT 5000", ARRAY_A ); // phpcs:ignore WordPress.DB
				foreach ( $in as $r ) {
					self::on_received( (int) $r['id'], $r );
				}
			}
		}
		return array( 'done' => $done, 'scanned' => min( $total, $page * $per ), 'total' => $total );
	}
}
