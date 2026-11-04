<?php
/**
 * Flux Chat (WhatsApp) → kişi + mesaj olayları. Sohbetin kendisi Flux Chat'te kalır.
 * Her gelen/giden mesaj zaman tünelinde ayrı satır (metnin başı, medya türü). Gruplar atlanır.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Adapter_Chat {

	const KEY = 'chat';

	public static function enabled() {
		return (bool) flxcrm_setting( 'src_chat' ) && function_exists( 'flxc_table' );
	}

	public static function label() {
		return 'Flux Chat';
	}

	public static function init() {
		if ( ! self::enabled() ) {
			return;
		}
		add_action( 'flxc_chat_created', array( __CLASS__, 'on_chat' ), 20, 1 );
		add_action( 'flxc_message_received', array( __CLASS__, 'on_in' ), 20, 2 );
		add_action( 'flxc_message_sent', array( __CLASS__, 'on_out' ), 20, 2 );
	}

	private static function is_group( $chat ) {
		return '@g.us' === substr( (string) ( $chat['jid'] ?? '' ), -5 );
	}

	/** Sohbet satırından kişi. */
	public static function contact_for( $chat, $create = true ) {
		$chat = (array) $chat;
		if ( self::is_group( $chat ) || empty( $chat['id'] ) ) {
			return 0;
		}
		$phone = flxcrm_phone( $chat['phone'] ?? '' );
		$link  = array( self::KEY, 'chat:' . (int) $chat['id'] );
		if ( ! $create ) {
			$id = FLXCRM_Contacts::find( array( 'link' => $link, 'phone' => $phone ) );
			return $id;
		}
		$name = '' !== trim( (string) ( $chat['name'] ?? '' ) ) ? $chat['name'] : ( $chat['push_name'] ?? '' );
		$role = (string) flxcrm_setting( 'role_chat' );
		return FLXCRM_Contacts::upsert(
			array(
				'name'   => $name,
				'phone'  => $phone,
				'roles'  => '' !== $role ? array( $role ) : array(),
				'source' => self::KEY,
				'link'   => $link,
				'at'     => $chat['created_at'] ?? '',
			)
		);
	}

	public static function on_chat( $row ) {
		try {
			$cid = self::contact_for( $row );
			if ( $cid ) {
				FLXCRM_Events::add(
					$cid,
					'chat_started',
					array(
						'source' => self::KEY,
						'uniq'   => 'chat:started:' . (int) $row['id'],
						'title'  => __( 'WhatsApp sohbeti açıldı', 'flx-crm' ),
						'url'    => 'admin.php?page=flx-chat&chat=' . (int) $row['id'],
						'at'     => $row['created_at'] ?? flxcrm_now(),
					)
				);
			}
		} catch ( \Throwable $e ) {
			error_log( 'flx-crm chat: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	public static function on_in( $msg_id, $chat ) {
		self::message( $msg_id, $chat );
	}

	public static function on_out( $msg_id, $chat ) {
		self::message( $msg_id, $chat );
	}

	/** Mesaj satırının zamanı (ts ms ya da s; yoksa created_at). */
	private static function msg_at( array $m ) {
		$ts = (int) ( $m['ts'] ?? 0 );
		if ( $ts > 0 ) {
			return gmdate( 'Y-m-d H:i:s', $ts > 9999999999 ? (int) ( $ts / 1000 ) : $ts );
		}
		return ! empty( $m['created_at'] ) ? get_gmt_from_date( $m['created_at'] ) : flxcrm_now();
	}

	/** Tek mesajı olay olarak yazar (uniq chat:msg:ID). */
	private static function message( $msg_id, $chat, $cid = 0 ) {
		if ( ! flxcrm_setting( 'chat_events' ) || ! function_exists( 'flxc_get_message' ) ) {
			return;
		}
		try {
			$chat = (array) $chat;
			$m    = is_array( $msg_id ) ? $msg_id : flxc_get_message( (int) $msg_id );
			if ( ! $m || self::is_group( $chat ) ) {
				return;
			}
			$cid = $cid ? $cid : self::contact_for( $chat, 'in' === $m['direction'] );
			if ( ! $cid ) {
				return;
			}
			$out  = 'in' !== $m['direction'];
			$text = trim( wp_strip_all_tags( (string) $m['body'] ) );
			if ( '' === $text ) {
				$text = '' !== (string) $m['media_name'] ? (string) $m['media_name'] : '[' . sanitize_key( (string) $m['type'] ) . ']';
			}
			FLXCRM_Events::add(
				$cid,
				$out ? 'message_out' : 'message_in',
				array(
					'source'  => self::KEY,
					'uniq'    => 'chat:msg:' . (int) $m['id'],
					'title'   => $out ? __( 'WhatsApp yanıtı', 'flx-crm' ) : __( 'WhatsApp mesajı', 'flx-crm' ),
					'url'     => 'admin.php?page=flx-chat&chat=' . (int) $chat['id'],
					'user_id' => $out ? (int) $m['user_id'] : 0,
					'at'      => self::msg_at( $m ),
					'data'    => array( 'body' => mb_substr( $text, 0, 600 ), 'kind' => (string) $m['type'], 'by' => (string) $m['source'] ),
				)
			);
		} catch ( \Throwable $e ) {
			error_log( 'flx-crm chat: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	/** Geçmiş mesajlar (sohbetlerden sonra; 500'lük parçalar). */
	public static function backfill_messages( $page, $per ) {
		global $wpdb;
		$per   = $per * 5;
		$m     = flxc_table( 'messages' );
		$c     = flxc_table( 'chats' );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $m" ); // phpcs:ignore WordPress.DB
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT m.*, c.jid AS c_jid, c.phone AS c_phone, c.name AS c_name FROM $m m INNER JOIN $c c ON c.id = m.chat_id WHERE c.jid NOT LIKE %s ORDER BY m.id LIMIT %d OFFSET %d", '%@g.us', $per, ( $page - 1 ) * $per ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$cache = array();
		foreach ( $rows as $r ) {
			$chat_id = (int) $r['chat_id'];
			if ( ! isset( $cache[ $chat_id ] ) ) {
				$chat              = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $c WHERE id = %d", $chat_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
				$cache[ $chat_id ] = $chat ? array( $chat, self::contact_for( $chat, false ) ) : array( null, 0 );
			}
			if ( $cache[ $chat_id ][1] ) {
				self::message( $r, $cache[ $chat_id ][0], $cache[ $chat_id ][1] );
			}
		}
		return array( 'done' => count( $rows ) < $per, 'scanned' => min( $total, $page * $per ), 'total' => $total );
	}

	/** Geçmiş sohbetler: kişi kartı ve "sohbet açıldı". Mesajlar backfill_messages ile. */
	public static function backfill( $page, $per ) {
		global $wpdb;
		$t     = flxc_table( 'chats' );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE jid NOT LIKE '%@g.us'" ); // phpcs:ignore WordPress.DB
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE jid NOT LIKE %s ORDER BY id LIMIT %d OFFSET %d", '%@g.us', $per, ( $page - 1 ) * $per ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $r ) {
			self::on_chat( $r );
		}
		return array( 'done' => count( (array) $rows ) < $per, 'scanned' => min( $total, $page * $per ), 'total' => $total );
	}
}
