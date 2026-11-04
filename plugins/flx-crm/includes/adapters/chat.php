<?php
/**
 * Flux Chat (WhatsApp) → kişi + mesaj olayları. Sohbetin kendisi Flux Chat'te kalır.
 * Gelen/giden mesaj başına satır yazılmaz: kişi başına günde bir "Gelen mesaj" ve bir "Giden mesaj" olayı.
 * Gruplar atlanır.
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
		self::daily( $chat, 'message_in', __( 'WhatsApp\'tan yazdı', 'flx-crm' ) );
	}

	public static function on_out( $msg_id, $chat ) {
		self::daily( $chat, 'message_out', __( 'WhatsApp\'tan yanıt verildi', 'flx-crm' ) );
	}

	private static function daily( $chat, $type, $title ) {
		if ( ! flxcrm_setting( 'chat_events' ) ) {
			return;
		}
		try {
			$chat = (array) $chat;
			$cid  = self::contact_for( $chat );
			if ( ! $cid ) {
				return;
			}
			$day = wp_date( 'Y-m-d' );
			FLXCRM_Events::add(
				$cid,
				$type,
				array(
					'source' => self::KEY,
					'uniq'   => 'chat:' . $type . ':' . (int) $chat['id'] . ':' . $day,
					'title'  => $title,
					'url'    => 'admin.php?page=flx-chat&chat=' . (int) $chat['id'],
					'data'   => array( 'day' => $day ),
				)
			);
		} catch ( \Throwable $e ) {
			error_log( 'flx-crm chat: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	/** Geçmiş sohbetler (yalnız kişi kartı ve "sohbet açıldı"; geçmiş mesajlar günlük özetle alınmaz). */
	public static function backfill( $page, $per ) {
		global $wpdb;
		$t     = flxc_table( 'chats' );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE jid NOT LIKE '%@g.us'" ); // phpcs:ignore WordPress.DB
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE jid NOT LIKE %s ORDER BY id LIMIT %d OFFSET %d", '%@g.us', $per, ( $page - 1 ) * $per ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $r ) {
			self::on_chat( $r );
			$last = (int) $r['last_in_ts'];
			if ( $last > 0 ) {
				$cid = self::contact_for( $r, false );
				if ( $cid ) {
					FLXCRM_Events::add(
						$cid,
						'message_in',
						array(
							'source' => self::KEY,
							'uniq'   => 'chat:message_in:' . (int) $r['id'] . ':' . wp_date( 'Y-m-d', $last > 9999999999 ? (int) ( $last / 1000 ) : $last ),
							'title'  => __( 'WhatsApp\'tan yazdı', 'flx-crm' ),
							'url'    => 'admin.php?page=flx-chat&chat=' . (int) $r['id'],
							'at'     => gmdate( 'Y-m-d H:i:s', $last > 9999999999 ? (int) ( $last / 1000 ) : $last ),
						)
					);
				}
			}
		}
		return array( 'done' => count( (array) $rows ) < $per, 'scanned' => min( $total, $page * $per ), 'total' => $total );
	}
}
