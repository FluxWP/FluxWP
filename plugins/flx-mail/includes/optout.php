<?php
/**
 * Tanıtım mesajlarından çıkış (IPTAL): kişi IPTAL yazınca o kanaldan tanıtım/reklam mesajı gitmez.
 *
 * - Yalnız `kind = marketing` gönderimlere uygulanır. Bilgilendirme (`info`) ve kişinin kendi yazdığı
 *   mesaja verilen cevaplar gider.
 * - Kanal bazlıdır: WhatsApp'tan IPTAL yazan kişiye SMS gidebilir (ve tersi).
 * - Tanıtım mesajlarının altına çıkış satırı eklenir ("Bu tarz mesajlar istemiyorsanız IPTAL yazın.").
 * - Gelen mesaj Flux Chat'ten (`flxc_message_received`) okunur; başka kaynaklar `flxi_handle_inbound()` çağırır.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Tanıtım (izin şartlı, IPTAL'e uyar) mı, bilgilendirme mi? */
function flxi_message_kind( $kind ) {
	return 'info' === $kind ? 'info' : 'marketing';
}

/** Kayıt anahtarı: WhatsApp/SMS'te yalnız rakamlar (90532…), e-postada küçük harf. */
function flxi_optout_key( $channel, $to ) {
	if ( 'email' === $channel ) {
		return strtolower( trim( (string) $to ) );
	}
	$n = function_exists( 'flxi_normalize_phone' ) ? flxi_normalize_phone( (string) $to ) : '';
	$n = $n ? $n : preg_replace( '/\D+/', '', (string) $to );
	return (string) $n;
}

/** Ayardaki virgüllü kelime listesi, karşılaştırma biçiminde. */
function flxi_optout_words( $key, $default ) {
	$raw = trim( (string) flxi_setting( $key ) );
	$raw = '' === $raw ? $default : $raw;
	$out = array();
	foreach ( preg_split( '/[,;\n]+/u', $raw ) as $w ) {
		$w = flxi_optout_norm( $w );
		if ( '' !== $w ) {
			$out[] = $w;
		}
	}
	return array_values( array_unique( $out ) );
}

/** "İptal.", "iptal 🙏", " IPTAL! " → "IPTAL". Türkçe İ/ı ve noktalama/emoji yok sayılır. */
function flxi_optout_norm( $text ) {
	$t = strtr( (string) $text, array( 'İ' => 'I', 'ı' => 'I', 'i' => 'I', 'Ş' => 'S', 'ş' => 'S', 'Ğ' => 'G', 'ğ' => 'G', 'Ü' => 'U', 'ü' => 'U', 'Ö' => 'O', 'ö' => 'O', 'Ç' => 'C', 'ç' => 'C' ) );
	$t = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $t, 'UTF-8' ) : strtoupper( $t );
	$t = preg_replace( '/[^\p{L}\p{N} ]+/u', ' ', $t );
	return trim( preg_replace( '/\s+/u', ' ', $t ) );
}

/** Varsayılan çıkış kelimeleri. */
function flxi_optout_default_words() {
	return 'IPTAL, İPTAL, STOP';
}

/** Varsayılan geri dönüş kelimeleri. */
function flxi_optin_default_words() {
	return 'BAŞLA, BASLA, START';
}

/**
 * Mesaj bir çıkış/geri dönüş komutu mu? Yalnız mesajın TAMAMI kelimeyse sayılır
 * ("bağışımı iptal etmek istiyorum" sayılmaz).
 *
 * @return string 'out' | 'in' | ''
 */
function flxi_optout_match( $text ) {
	if ( ! flxi_setting( 'optout_enabled' ) ) {
		return '';
	}
	$t = flxi_optout_norm( $text );
	if ( '' === $t || mb_strlen( $t ) > 20 ) {
		return '';
	}
	if ( in_array( $t, flxi_optout_words( 'optout_keywords', flxi_optout_default_words() ), true ) ) {
		return 'out';
	}
	if ( in_array( $t, flxi_optout_words( 'optin_keywords', flxi_optin_default_words() ), true ) ) {
		return 'in';
	}
	return '';
}

/** Kişi bu kanalda tanıtım mesajlarından çıkmış mı? */
function flxi_optout_has( $channel, $to ) {
	global $wpdb;
	$key = flxi_optout_key( $channel, $to );
	if ( '' === $key || ( 'email' !== $channel && function_exists( 'flxi_is_wa_group' ) && flxi_is_wa_group( $to ) ) ) {
		return false;
	}
	$ck = $channel . '|' . $key;
	if ( ! isset( $GLOBALS['flxi_optout_cache'][ $ck ] ) ) {
		$GLOBALS['flxi_optout_cache'][ $ck ] = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxi_table( 'optouts' ) . ' WHERE channel = %s AND recipient = %s LIMIT 1', $channel, $key ) ); // phpcs:ignore
	}
	return (bool) apply_filters( 'flxi_optout_has', $GLOBALS['flxi_optout_cache'][ $ck ], $channel, $key );
}

/**
 * Kişiyi bu kanalın tanıtım mesajlarından çıkarır. Ortak kişi kaydında (Flux CRM) kanal izni de kapanır.
 *
 * @param string $source 'inbound' | 'admin' | eklenti adı
 * @return bool yeni kayıt açıldı mı
 */
function flxi_optout_add( $channel, $to, $keyword = '', $source = 'admin' ) {
	global $wpdb;
	$key = flxi_optout_key( $channel, $to );
	if ( '' === $key || ! in_array( $channel, array( 'whatsapp', 'sms', 'email' ), true ) ) {
		return false;
	}
	$t = flxi_table( 'optouts' );
	if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE channel = %s AND recipient = %s", $channel, $key ) ) ) { // phpcs:ignore
		return false;
	}
	$wpdb->insert(
		$t,
		array(
			'channel'    => $channel,
			'recipient'  => $key,
			'keyword'    => mb_substr( (string) $keyword, 0, 40 ),
			'source'     => mb_substr( sanitize_key( $source ), 0, 40 ),
			'created_at' => flxi_now(),
		)
	);
	unset( $GLOBALS['flxi_optout_cache'] );
	flxi_optout_sync_person( $channel, $to, false );
	flx_background_log( 'optout.added', array( 'plugin' => 'flx-mail', 'channel' => $channel, 'source' => $source ) );
	do_action( 'flxi_optout_added', $channel, $key, $source );
	return true;
}

/** Çıkışı geri alır (kişi BAŞLA yazdı ya da yönetici sildi). */
function flxi_optout_remove( $channel, $to, $source = 'admin' ) {
	global $wpdb;
	$key = flxi_optout_key( $channel, $to );
	$n   = $wpdb->delete( flxi_table( 'optouts' ), array( 'channel' => $channel, 'recipient' => $key ) );
	unset( $GLOBALS['flxi_optout_cache'] );
	if ( $n && 'inbound' === $source ) {
		flxi_optout_sync_person( $channel, $to, true );
	}
	if ( $n ) {
		do_action( 'flxi_optout_removed', $channel, $key, $source );
	}
	return (bool) $n;
}

/** Ortak kişi kaydındaki kanal iznini (consent_whatsapp/sms/email) günceller. */
function flxi_optout_sync_person( $channel, $to, $allow ) {
	if ( ! function_exists( 'flx_person_find' ) || ! function_exists( 'flx_person_update' ) || ! function_exists( 'flx_people_ready' ) || ! flx_people_ready() ) {
		return;
	}
	$id = (int) flx_person_find( 'email' === $channel ? array( 'email' => $to ) : array( 'phone' => $to ) );
	if ( $id ) {
		flx_person_update( $id, array( 'consent_' . $channel => $allow ? 1 : 0 ) );
	}
}

/** Çıkış listesi (yönetim ekranı). */
function flxi_optout_list( $channel = '', $limit = 200, $search = '' ) {
	global $wpdb;
	$sql = 'SELECT * FROM ' . flxi_table( 'optouts' ) . ' WHERE 1=1';
	$prm = array();
	if ( $channel ) {
		$sql  .= ' AND channel = %s';
		$prm[] = $channel;
	}
	if ( '' !== $search ) {
		$sql  .= ' AND recipient LIKE %s';
		$prm[] = '%' . $wpdb->esc_like( $search ) . '%';
	}
	$sql  .= ' ORDER BY id DESC LIMIT %d';
	$prm[] = max( 1, (int) $limit );
	return (array) $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ); // phpcs:ignore
}

function flxi_optout_count( $channel = '' ) {
	global $wpdb;
	$t = flxi_table( 'optouts' );
	return $channel ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE channel = %s", $channel ) ) : (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ); // phpcs:ignore
}

/** Tanıtım mesajlarının altına eklenen çıkış satırı (boş: eklenmez). */
function flxi_optout_footer( $channel ) {
	if ( ! flxi_setting( 'optout_enabled' ) || ! in_array( $channel, array( 'whatsapp', 'sms' ), true ) || ! flxi_setting( 'optout_footer_' . ( 'sms' === $channel ? 'sms' : 'wa' ) ) ) {
		return '';
	}
	$text = trim( (string) flxi_setting( 'optout_text' ) );
	if ( '' === $text ) {
		$text = __( 'If you don\'t want messages like this, reply STOP.', 'flx-mail' );
	}
	return (string) apply_filters( 'flxi_optout_footer', $text, $channel );
}

/** Metnin altına çıkış satırını ekler (zaten varsa eklemez). */
function flxi_optout_append( $text, $channel ) {
	$foot = flxi_optout_footer( $channel );
	$text = (string) $text;
	if ( '' === $foot || false !== mb_stripos( $text, $foot ) ) {
		return $text;
	}
	return rtrim( $text ) . ( 'whatsapp' === $channel ? "\n\n_" . $foot . '_' : "\n" . $foot );
}

/** Kişiye IPTAL / BAŞLA onayı (boş: gönderilmez). */
function flxi_optout_reply_text( $dir ) {
	$k = 'out' === $dir ? 'optout_reply' : 'optin_reply';
	$t = trim( (string) flxi_setting( $k ) );
	if ( '' !== $t ) {
		return '-' === $t ? '' : $t;
	}
	return 'out' === $dir
		? __( 'Done, you won\'t get promotional messages from us anymore. You can still write to us any time. To get them again, reply START.', 'flx-mail' )
		: __( 'Welcome back, you will get our messages again.', 'flx-mail' );
}

/**
 * Gelen mesajı işler: IPTAL ise çıkış, BAŞLA ise geri dönüş. Kişinin kendisi yazdığı için onay mesajı
 * (bilgilendirme) gider.
 *
 * @param array $args connection_id (Flux Mail bağlantısı), source
 * @return string 'out' | 'in' | '' (komut değil)
 */
function flxi_handle_inbound( $channel, $from, $text, array $args = array() ) {
	if ( ! in_array( $channel, array( 'whatsapp', 'sms' ), true ) || ( function_exists( 'flxi_is_wa_group' ) && flxi_is_wa_group( $from ) ) ) {
		return '';
	}
	$dir = flxi_optout_match( $text );
	if ( '' === $dir ) {
		return '';
	}
	$src     = sanitize_key( $args['source'] ?? 'inbound' );
	$changed = 'out' === $dir ? flxi_optout_add( $channel, $from, flxi_optout_norm( $text ), 'inbound' ) : flxi_optout_remove( $channel, $from, 'inbound' );
	$reply   = flxi_optout_reply_text( $dir );
	if ( $changed && '' !== $reply && 'whatsapp' === $channel ) {
		$r = flxi_send_message(
			$channel,
			$from,
			$reply,
			array(
				'connection_id' => (int) ( $args['connection_id'] ?? 0 ),
				'source'        => 'flx-mail-optout',
				'kind'          => 'info',
			)
		);
		if ( ! empty( $r['external_id'] ) ) {
			flxi_remember_sent_id( (string) $r['external_id'] );
		}
	}
	do_action( 'flxi_inbound_command', $dir, $channel, flxi_optout_key( $channel, $from ), $src );
	return $dir;
}

/* Flux Chat: gelen WhatsApp mesajı. */
add_action(
	'flxc_message_received',
	function ( $id, $chat ) {
		if ( ! function_exists( 'flxc_get_message' ) || ! is_array( $chat ) ) {
			return;
		}
		$m = flxc_get_message( (int) $id );
		if ( ! $m || 'in' !== ( $m['direction'] ?? '' ) || 'text' !== ( $m['type'] ?? 'text' ) ) {
			return;
		}
		$jid = (string) ( $chat['jid'] ?? '' );
		if ( '' !== $jid && false === strpos( $jid, '@s.whatsapp.net' ) && false === strpos( $jid, '@c.us' ) && false === strpos( $jid, '@lid' ) ) {
			return; // grup, kanal, durum
		}
		$phone = (string) ( $chat['phone'] ?? '' );
		if ( '' === $phone ) {
			return;
		}
		$flxi = 0;
		if ( function_exists( 'flxc_get_connection' ) ) {
			$c    = flxc_get_connection( $chat['conn'] ?? '' );
			$flxi = ( $c && 'flxi' === ( $c['source'] ?? '' ) ) ? (int) ( $c['provider_id'] ?? 0 ) : 0;
		}
		flxi_handle_inbound( 'whatsapp', $phone, (string) $m['body'], array( 'connection_id' => $flxi, 'source' => 'flx-chat' ) );
	},
	5,
	2
);

/** Flux Mail'in gönderdiği WhatsApp mesaj kimlikleri (son 3 gün, en çok 1000). */
function flxi_remember_sent_id( $wa_id ) {
	if ( '' === $wa_id ) {
		return;
	}
	$ids = get_option( 'flxi_sent_ids', array() );
	$ids = is_array( $ids ) ? $ids : array();
	$cut = time() - 3 * DAY_IN_SECONDS;
	$ids = array_filter(
		$ids,
		function ( $t ) use ( $cut ) {
			return $t > $cut;
		}
	);
	$ids[ $wa_id ] = time();
	if ( count( $ids ) > 1000 ) {
		$ids = array_slice( $ids, -1000, null, true );
	}
	update_option( 'flxi_sent_ids', $ids, false );
}

/*
 * Flux Chat, telefondan yazılmış sandığı giden mesajda asistanı susturur. Flux Mail'in toplu/IPTAL
 * mesajları telefondan değil: kaynak 'flx-mail' olarak işaretlenir, asistan susmaz.
 */
add_filter(
	'flxc_external_source',
	function ( $source, $wa_id ) {
		if ( 'phone' !== $source || '' === (string) $wa_id ) {
			return $source;
		}
		$ids = get_option( 'flxi_sent_ids', array() );
		return ( is_array( $ids ) && isset( $ids[ (string) $wa_id ] ) ) ? 'flx-mail' : $source;
	},
	10,
	2
);

/* ------------------------------------------------------------------ */
/* Yönetim: listeye elle ekle / çıkar                                  */
/* ------------------------------------------------------------------ */

add_action(
	'admin_post_flxi_optout_add',
	function () {
		flxi_check( 'flxi_optout' );
		$in = wp_unslash( $_POST ); // phpcs:ignore
		$ch = sanitize_key( $in['channel'] ?? 'whatsapp' );
		$ch = in_array( $ch, array( 'whatsapp', 'sms' ), true ) ? $ch : 'whatsapp';
		$to = sanitize_text_field( $in['recipient'] ?? '' );
		if ( '' === flxi_optout_key( $ch, $to ) ) {
			flxi_redirect( flxi_admin_url( 'flxi-ayarlar' ) . '#iptal', '', __( 'Enter a valid phone number.', 'flx-mail' ) );
		}
		flxi_optout_add( $ch, $to, '', 'admin' );
		flxi_redirect( flxi_admin_url( 'flxi-ayarlar' ) . '#iptal', __( 'Added to the opt-out list.', 'flx-mail' ) );
	}
);

add_action(
	'admin_post_flxi_optout_remove',
	function () {
		flxi_check( 'flxi_optout_remove' );
		$in = wp_unslash( $_POST ); // phpcs:ignore
		flxi_optout_remove( sanitize_key( $in['channel'] ?? '' ), sanitize_text_field( $in['recipient'] ?? '' ), 'admin' );
		flxi_redirect( flxi_admin_url( 'flxi-ayarlar' ) . '#iptal', __( 'Removed from the opt-out list. Promotional messages will be sent to this person again.', 'flx-mail' ) );
	}
);
