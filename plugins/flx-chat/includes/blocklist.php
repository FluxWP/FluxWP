<?php
/**
 * Kara liste: "bana mesaj atmayın" diyen kişiler. Asistan bu kişiyle konuşmaz, panelden ve otomasyonlardan
 * mesaj gitmez; diğer eklentiler (Flux Mail e-posta/SMS, Flux Donate, Flux Certificates) ortak kontrolü kullanır:
 *
 *   if ( apply_filters( 'flx_contact_blocked', false, $phone, $email, 'email' ) ) { return; }
 *   // ya da: function_exists( 'flx_contact_blocked' ) && flx_contact_blocked( $phone, $email, 'sms' )
 *
 * Değişince do_action( 'flx_contact_block_changed', $phone, $email, $blocked ) çalışır.
 * Listeyi yalnız yöneticiler görür ve değiştirir; kişi listeden çıkarılınca her şey yeniden çalışır.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

function flxc_block_email( $e ) {
	$e = strtolower( trim( sanitize_email( (string) $e ) ) );
	return is_email( $e ) ? $e : '';
}

/** Tüm liste tek sorguyla (istek başına önbellek). */
function flxc_block_index( $reset = false ) {
	static $idx = null;
	if ( null === $idx || $reset ) {
		global $wpdb;
		$idx  = array( 'p' => array(), 'e' => array(), 'j' => array() );
		$rows = (array) $wpdb->get_results( 'SELECT id, phone, email, jid FROM ' . flxc_table( 'blocklist' ), ARRAY_A ); // phpcs:ignore
		foreach ( $rows as $r ) {
			foreach ( array( 'p' => 'phone', 'e' => 'email', 'j' => 'jid' ) as $k => $f ) {
				if ( '' !== (string) $r[ $f ] ) {
					$idx[ $k ][ (string) $r[ $f ] ] = (int) $r['id'];
				}
			}
		}
	}
	return $idx;
}

/** @return int kara liste kaydı id (yoksa 0) */
function flxc_block_find( $phone = '', $email = '', $jid = '' ) {
	$idx   = flxc_block_index();
	$phone = flxc_normalize_phone( (string) $phone );
	$email = flxc_block_email( $email );
	if ( '' !== $phone && isset( $idx['p'][ $phone ] ) ) {
		return $idx['p'][ $phone ];
	}
	if ( '' !== $email && isset( $idx['e'][ $email ] ) ) {
		return $idx['e'][ $email ];
	}
	if ( '' !== (string) $jid && isset( $idx['j'][ (string) $jid ] ) ) {
		return $idx['j'][ (string) $jid ];
	}
	return 0;
}

function flxc_chat_blocked( $chat ) {
	return $chat && flxc_block_find( $chat['phone'] ?? '', '', $chat['jid'] ?? '' ) > 0;
}

/**
 * Kişiyi listeye ekler (aynı telefon/e-posta varsa o kayıt döner).
 *
 * @return int|WP_Error
 */
function flxc_block_add( $phone, $email = '', $reason = '', $source = 'agent', $chat = null ) {
	global $wpdb;
	$phone = flxc_normalize_phone( (string) $phone );
	$email = flxc_block_email( $email );
	$jid   = $chat && '' === $phone ? (string) $chat['jid'] : '';
	if ( '' === $phone && '' === $email && '' === $jid ) {
		return new WP_Error( 'flxc_block', __( 'Phone or email is required.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	$have = flxc_block_find( $phone, $email, $jid );
	if ( $have ) {
		// Yeni bilinen e-posta/telefon aynı kayda eklenir.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'blocklist' ) . ' WHERE id = %d', $have ), ARRAY_A ); // phpcs:ignore
		$upd = array();
		if ( $row && '' === $row['phone'] && '' !== $phone ) {
			$upd['phone'] = $phone;
		}
		if ( $row && '' === $row['email'] && '' !== $email ) {
			$upd['email'] = $email;
		}
		if ( $upd ) {
			$wpdb->update( flxc_table( 'blocklist' ), $upd, array( 'id' => $have ) );
			flxc_block_index( true );
		}
		return $have;
	}
	$wpdb->insert(
		flxc_table( 'blocklist' ),
		array(
			'phone'      => $phone,
			'email'      => $email,
			'jid'        => $jid,
			'reason'     => mb_substr( sanitize_text_field( (string) $reason ), 0, 250 ),
			'source'     => 'bot' === $source ? 'bot' : 'agent',
			'chat_id'    => $chat ? (int) $chat['id'] : 0,
			'user_id'    => 'bot' === $source ? 0 : get_current_user_id(),
			'created_at' => time(),
		)
	);
	$id = (int) $wpdb->insert_id;
	flxc_block_index( true );
	flxc_block_touch_chats( $phone, $jid, true, array( 'source' => 'bot' === $source ? 'ai' : 'staff', 'user_id' => 'bot' === $source ? 0 : get_current_user_id(), 'note' => (string) $reason ) );
	do_action( 'flx_contact_block_changed', $phone, $email, true );
	return $id;
}

function flxc_block_remove( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxc_table( 'blocklist' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	if ( ! $row ) {
		return false;
	}
	$wpdb->delete( flxc_table( 'blocklist' ), array( 'id' => (int) $id ) );
	flxc_block_index( true );
	flxc_block_touch_chats( $row['phone'], $row['jid'], false );
	do_action( 'flx_contact_block_changed', $row['phone'], $row['email'], false );
	return true;
}

/**
 * Panel listesi yenilensin diye ilgili sohbetlerin rev'i artar.
 * 1.41.0: $blocked verilirse kişi değişiklik geçmişine yazılır (null: yalnız yenile).
 */
function flxc_block_touch_chats( $phone, $jid, $blocked = null, array $by = array() ) {
	global $wpdb;
	$ct  = flxc_table( 'chats' );
	$ids = '' !== (string) $phone ? $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $ct WHERE phone = %s", $phone ) ) : $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $ct WHERE jid = %s", $jid ) ); // phpcs:ignore
	foreach ( (array) $ids as $cid ) {
		flxc_update_chat( (int) $cid, array() );
		if ( null !== $blocked && function_exists( 'flxc_clog_add' ) ) {
			flxc_clog_add( (int) $cid, 'blocked', $blocked ? '0' : '1', $blocked ? '1' : '0', $by );
		}
	}
}

/** Kişinin bilinen e-postaları: asistan durumu ve hafızadaki "e-posta". */
function flxc_block_chat_emails( $chat ) {
	$out   = array();
	$state = flxc_bot_state( $chat );
	if ( '' !== (string) $state['email'] ) {
		$out[] = $state['email'];
	}
	if ( function_exists( 'flxc_mem_items' ) ) {
		foreach ( flxc_mem_items( $chat ) as $r ) {
			if ( 'fact' === $r['kind'] && flxc_block_email( $r['v'] ) && false !== strpos( $r['k'], 'posta' ) && false === strpos( $r['k'], ' ' ) ) {
				$out[] = $r['v'];
			}
		}
	}
	return array_values( array_unique( array_filter( array_map( 'flxc_block_email', $out ) ) ) );
}

/** Sohbetteki kişiyi listeye alır (asistan aracı ve ⋮ menüsü). */
function flxc_block_chat( $chat, $reason, $source ) {
	$emails = flxc_block_chat_emails( $chat );
	$id     = flxc_block_add( $chat['phone'], $emails[0] ?? '', $reason, $source, $chat );
	if ( ! is_wp_error( $id ) ) {
		foreach ( array_slice( $emails, 1 ) as $e ) {
			flxc_block_add( '', $e, $reason, $source, null );
		}
		/* translators: %s: reason */
		flxc_system_message( $chat, sprintf( __( 'Contact added to the blocklist: %s', 'flx-chat' ), '' !== (string) $reason ? $reason : '-' ) );
	}
	return $id;
}

function flxc_block_for_js( $r ) {
	$chat = $r['chat_id'] ? flxc_get_chat( (int) $r['chat_id'] ) : null;
	return array(
		'id'     => (int) $r['id'],
		'phone'  => '' !== $r['phone'] ? flxc_format_phone( $r['phone'] ) : '',
		'email'  => (string) $r['email'],
		'hidden' => '' !== (string) $r['jid'],
		'reason' => (string) $r['reason'],
		'source' => (string) $r['source'],
		'by'     => $r['user_id'] ? flxc_user_name( (int) $r['user_id'] ) : '',
		'chat'   => (int) $r['chat_id'],
		'title'  => $chat ? flxc_chat_title( $chat ) : '',
		'at'     => (int) $r['created_at'] * 1000,
	);
}

/* ---------- Ortak sözleşme ---------- */

add_filter(
	'flx_contact_blocked',
	function ( $blocked, $phone = '', $email = '' ) {
		return $blocked || flxc_block_find( $phone, $email ) > 0;
	},
	10,
	3
);

if ( ! function_exists( 'flx_contact_blocked' ) ) {
	/** Diğer FluxWP eklentileri için: bu kişiye ileti gönderilmemeli mi? */
	function flx_contact_blocked( $phone = '', $email = '', $channel = '' ) {
		return (bool) apply_filters( 'flx_contact_blocked', false, (string) $phone, (string) $email, (string) $channel );
	}
}

/* ---------- REST (yönetici) ---------- */

add_action(
	'rest_api_init',
	function () {
		$ns  = 'flx-chat/v1';
		$mng = function () {
			return flxc_can_manage();
		};
		register_rest_route(
			$ns,
			'/blocklist',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $mng,
					'callback'            => function ( WP_REST_Request $req ) {
						global $wpdb;
						$t = flxc_table( 'blocklist' );
						$q = trim( sanitize_text_field( (string) $req->get_param( 'q' ) ) );
						if ( '' !== $q ) {
							$like = '%' . $wpdb->esc_like( $q ) . '%';
							$dig  = preg_replace( '/\D+/', '', $q );
							$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE email LIKE %s OR reason LIKE %s OR ( %s <> '' AND phone LIKE %s ) ORDER BY id DESC LIMIT 200", $like, $like, $dig, '%' . $dig . '%' ), ARRAY_A ); // phpcs:ignore
						} else {
							$rows = $wpdb->get_results( "SELECT * FROM $t ORDER BY id DESC LIMIT 200", ARRAY_A ); // phpcs:ignore
						}
						return array(
							'items' => array_map( 'flxc_block_for_js', (array) $rows ),
							'total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ), // phpcs:ignore
						);
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $mng,
					'callback'            => function ( WP_REST_Request $req ) {
						$reason = (string) $req->get_param( 'reason' );
						$cid    = (int) $req->get_param( 'chat' );
						if ( $cid ) {
							$chat = flxc_get_chat( $cid );
							if ( ! $chat ) {
								return new WP_Error( 'flxc_404', __( 'Chat not found.', 'flx-chat' ), array( 'status' => 404 ) );
							}
							$id = flxc_block_chat( $chat, $reason, 'agent' );
							return is_wp_error( $id ) ? $id : array( 'id' => $id, 'chat' => flxc_chat_for_js( flxc_get_chat( $cid ) ) );
						}
						$id = flxc_block_add( (string) $req->get_param( 'phone' ), (string) $req->get_param( 'email' ), $reason, 'agent' );
						return is_wp_error( $id ) ? $id : array( 'id' => $id );
					},
				),
			)
		);
		register_rest_route(
			$ns,
			'/blocklist/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'permission_callback' => $mng,
				'callback'            => function ( WP_REST_Request $req ) {
					return flxc_block_remove( (int) $req['id'] ) ? array( 'ok' => true ) : new WP_Error( 'flxc_404', __( 'No records found.', 'flx-chat' ), array( 'status' => 404 ) );
				},
			)
		);
		register_rest_route(
			$ns,
			'/chats/(?P<id>\d+)/unblock',
			array(
				'methods'             => 'POST',
				'permission_callback' => $mng,
				'callback'            => function ( WP_REST_Request $req ) {
					$chat = flxc_get_chat( (int) $req['id'] );
					if ( ! $chat ) {
						return new WP_Error( 'flxc_404', __( 'Chat not found.', 'flx-chat' ), array( 'status' => 404 ) );
					}
					$id = flxc_block_find( $chat['phone'], '', $chat['jid'] );
					if ( $id ) {
						flxc_block_remove( $id );
						flxc_system_message( $chat, __( 'Contact removed from the blocklist.', 'flx-chat' ) );
					}
					return array( 'chat' => flxc_chat_for_js( flxc_get_chat( $chat['id'] ) ) );
				},
			)
		);
	}
);
