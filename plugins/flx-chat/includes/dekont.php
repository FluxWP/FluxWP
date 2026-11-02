<?php
/**
 * 1.15.0: dekont kayıt defteri. Gelen her dekont (WhatsApp, site) gönderen + dekont no ile kaydedilir;
 * aynı dekont ikinci kez gelirse asistan ve ekip baştan uyarılır.
 *
 * Ortak sözleşme (başka eklentiler Çağrı Merkezi kurulu olmasa da güvenle çağırır):
 *   $r = apply_filters( 'flx_dekont_register', null, array( 'payer' => ..., 'dekont_no' => ..., 'source' => 'site', ... ) );
 *   $r = apply_filters( 'flx_dekont_seen', null, array( 'payer' => ..., 'dekont_no' => ... ) );
 * Çağrı Merkezi yoksa null döner.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Karşılaştırma anahtarı: küçük harf, Türkçe karakterler katlanır (ı→i, ş→s, ğ→g, ü→u, ö→o, ç→c),
 * harf ve rakam dışı her şey (boşluk, tire, nokta, eğik çizgi) atılır.
 */
function flxc_dekont_key( $s ) {
	$s = strtr( (string) $s, array( 'İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g', 'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o', 'Ç' => 'c', 'ç' => 'c' ) );
	$s = mb_strtolower( remove_accents( $s ), 'UTF-8' );
	return substr( (string) preg_replace( '/[^a-z0-9]+/', '', $s ), 0, 120 );
}

/**
 * Daha önce kaydedilmiş aynı dekontlar. Eşleşme: aynı dosya (hash) ya da aynı dekont no + aynı gönderen
 * (iki taraftan birinde gönderen okunamadıysa yalnız dekont no).
 *
 * @param array $d payer, dekont_no, hash; exclude_id ya da message_id kendisini dışarıda bırakır.
 * @return array[] en yeni önce, en fazla 5
 */
function flxc_dekont_find( array $d ) {
	global $wpdb;
	$t     = flxc_table( 'dekonts' );
	$no    = flxc_dekont_key( $d['dekont_no'] ?? '' );
	$payer = flxc_dekont_key( $d['payer'] ?? '' );
	$hash  = preg_replace( '/[^a-f0-9]/', '', (string) ( $d['hash'] ?? '' ) );
	$or    = array();
	$args  = array();
	if ( strlen( $no ) >= 4 ) {
		$or[]   = '( no_key = %s AND ( payer_key = %s OR payer_key = \'\' OR %s = \'\' ) )';
		$args[] = $no;
		$args[] = $payer;
		$args[] = $payer;
	}
	if ( 40 === strlen( $hash ) ) {
		$or[]   = 'file_hash = %s';
		$args[] = $hash;
	}
	if ( ! $or ) {
		return array();
	}
	$sql    = "SELECT * FROM $t WHERE ( " . implode( ' OR ', $or ) . ' ) AND id <> %d AND ( message_id = 0 OR message_id <> %d ) ORDER BY id DESC LIMIT 5';
	$args[] = (int) ( $d['exclude_id'] ?? 0 );
	$args[] = (int) ( $d['message_id'] ?? 0 );
	$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore
	return array_map( 'flxc_dekont_public', (array) $rows );
}

/** Dışarıya verilen satır (anahtarlar ve hash hariç). */
function flxc_dekont_public( array $r ) {
	return array(
		'id'          => (int) $r['id'],
		'payer'       => (string) $r['payer'],
		'dekont_no'   => (string) $r['dekont_no'],
		'amount'      => null === $r['amount'] ? null : (float) $r['amount'],
		'currency'    => (string) $r['currency'],
		'date'        => (string) $r['rdate'],
		'recipient'   => (string) $r['recipient'],
		'source'      => (string) $r['source'],
		'chat_id'     => (int) $r['chat_id'],
		'message_id'  => (int) $r['message_id'],
		'donation_id' => (int) $r['donation_id'],
		'verdict'     => (string) $r['verdict'],
		'created_at'  => (int) $r['created_at'],
	);
}

/**
 * Dekontu deftere yazar ve öncekileri döndürür. Aynı mesaj ikinci kez yazılmaz (güncellenir).
 *
 * @param array $d payer, dekont_no, amount, currency, date, recipient, iban, source (whatsapp|site|…),
 *                 chat_id, message_id, donation_id, verdict, file (yol) ya da hash.
 * @return array { id, duplicates: array[] }
 */
function flxc_dekont_register( array $d ) {
	global $wpdb;
	$d = apply_filters( 'flxc_dekont_register_data', $d );
	if ( empty( $d['hash'] ) && ! empty( $d['file'] ) && is_readable( $d['file'] ) ) {
		$d['hash'] = sha1_file( $d['file'] );
	}
	$s   = function ( $k, $len ) use ( $d ) {
		return mb_substr( sanitize_text_field( (string) ( $d[ $k ] ?? '' ) ), 0, $len );
	};
	$row = array(
		'payer'       => $s( 'payer', 190 ),
		'payer_key'   => flxc_dekont_key( $d['payer'] ?? '' ),
		'dekont_no'   => $s( 'dekont_no', 80 ),
		'no_key'      => substr( flxc_dekont_key( $d['dekont_no'] ?? '' ), 0, 80 ),
		'amount'      => isset( $d['amount'] ) && is_numeric( $d['amount'] ) ? (float) $d['amount'] : null,
		'currency'    => $s( 'currency', 8 ),
		'rdate'       => $s( 'date', 20 ),
		'recipient'   => $s( 'recipient', 190 ),
		'iban'        => $s( 'iban', 40 ),
		'source'      => '' !== $s( 'source', 20 ) ? sanitize_key( $d['source'] ) : 'whatsapp',
		'chat_id'     => (int) ( $d['chat_id'] ?? 0 ),
		'message_id'  => (int) ( $d['message_id'] ?? 0 ),
		'donation_id' => (int) ( $d['donation_id'] ?? 0 ),
		'verdict'     => sanitize_key( (string) ( $d['verdict'] ?? '' ) ),
		'file_hash'   => preg_replace( '/[^a-f0-9]/', '', (string) ( $d['hash'] ?? '' ) ),
	);
	$dups = flxc_dekont_find( $d + array( 'hash' => $row['file_hash'] ) );
	$id   = $row['message_id'] ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxc_table( 'dekonts' ) . ' WHERE message_id = %d AND source = %s', $row['message_id'], $row['source'] ) ) : 0; // phpcs:ignore
	if ( $id ) {
		$wpdb->update( flxc_table( 'dekonts' ), $row, array( 'id' => $id ) );
	} else {
		$wpdb->insert( flxc_table( 'dekonts' ), $row + array( 'created_at' => time() ) );
		$id = (int) $wpdb->insert_id;
	}
	$out = array( 'id' => $id, 'duplicates' => $dups );
	do_action( 'flxc_dekont_registered', $id, $row, $dups );
	return $out;
}

/** Defterden bir dekontun ilişkilerini günceller (ör. sonradan bağışa bağlanınca). */
function flxc_dekont_update( $id, array $f ) {
	global $wpdb;
	$ok = array_intersect_key( $f, array_flip( array( 'donation_id', 'verdict', 'chat_id' ) ) );
	return $ok ? (bool) $wpdb->update( flxc_table( 'dekonts' ), $ok, array( 'id' => (int) $id ) ) : false;
}

add_filter(
	'flx_dekont_register',
	function ( $r, $d ) {
		return flxc_dekont_register( (array) $d );
	},
	10,
	2
);
add_filter(
	'flx_dekont_seen',
	function ( $r, $d ) {
		return flxc_dekont_find( (array) $d );
	},
	10,
	2
);

/** Kişiye/ekibe kısa açıklama: "12.10.2026 tarihinde WhatsApp'tan yüklenmiş". */
function flxc_dekont_dup_text( array $dup ) {
	$src = array(
		'whatsapp' => __( 'WhatsApp\'tan', 'flx-chat' ),
		'site'     => __( 'siteden', 'flx-chat' ),
	);
	/* translators: 1: date, 2: source (WhatsApp'tan / siteden) */
	return sprintf( __( '%1$s tarihinde %2$s yüklenmiş', 'flx-chat' ), wp_date( 'd.m.Y H:i', (int) $dup['created_at'] ), $src[ $dup['source'] ] ?? $dup['source'] );
}

/** Asistan aracı: kişi dekont no yazınca ya da emin olmak istediğinde. */
function flxc_dekont_tool_def( $f ) {
	return $f(
		'receipt_check',
		'Bir dekontun daha önce sisteme (WhatsApp ya da site) yüklenip yüklenmediğine bakar. Kişi dekont/işlem numarasını yazdığında ya da aynı ödemeyi tekrar bildirdiğinde kullan. Dosya olarak gelen dekontlara sistem zaten bakar.',
		array(
			'dekont_no' => array( 'type' => 'string', 'description' => 'Dekont, işlem ya da referans numarası' ),
			'payer'     => array( 'type' => 'string', 'description' => 'Parayı gönderen kişinin adı (biliniyorsa)' ),
		),
		array( 'dekont_no' )
	);
}

function flxc_dekont_tool( array $a, $chat = null, array $state = array() ) {
	if ( strlen( flxc_dekont_key( $a['dekont_no'] ?? '' ) ) < 4 ) {
		return array( 'ok' => false, 'error' => 'Dekont numarası çok kısa ya da yok.' );
	}
	$dups = flxc_dekont_find( array( 'dekont_no' => (string) $a['dekont_no'], 'payer' => (string) ( $a['payer'] ?? '' ) ) );
	// 1.29.0: bu sohbetten (ya da bu sohbetin bağışı için) yüklenen dekont "daha önce kullanılmış" sayılmaz;
	// kişi az önce gönderdiği dekontu sorunca kendi yüklemesi kopya gibi görünüyordu.
	$cid  = (int) ( $chat['id'] ?? 0 );
	$own  = (int) ( $state['donation'] ?? 0 );
	$mine = array();
	$dups = array_values(
		array_filter(
			$dups,
			function ( $r ) use ( $cid, $own, &$mine ) {
				$here = $cid && (int) $r['chat_id'] === $cid && ( ! $r['donation_id'] || (int) $r['donation_id'] === $own || (int) $r['created_at'] > time() - DAY_IN_SECONDS );
				if ( $here || ( $own && (int) $r['donation_id'] === $own ) ) {
					$mine[] = $r;
					return false;
				}
				return true;
			}
		)
	);
	// Aynı dekontun bu bağış için siteden/onay kartından da yazılmış kopyası da bu yüklemedir.
	$dids = array_filter( array_map( 'intval', wp_list_pluck( $mine, 'donation_id' ) ) );
	if ( $dids ) {
		$dups = array_values(
			array_filter(
				$dups,
				function ( $r ) use ( $dids ) {
					return ! in_array( (int) $r['donation_id'], $dids, true );
				}
			)
		);
	}
	if ( ! $dups && $mine ) {
		return array( 'ok' => true, 'seen' => false, 'uploaded_here' => true, 'note' => 'Bu dekont bu sohbetten ' . wp_date( 'd.m.Y H:i', (int) $mine[0]['created_at'] ) . ' tarihinde yüklenmiş ve sisteme kayıtlı; kopya DEĞİL. Ödeme ekip onaylayınca kesinleşir; kayıt durumunu my_records / staff_my_donations ile söyle, "ödemeniz onaylandı" deme.' );
	}
	if ( ! $dups ) {
		return array( 'ok' => true, 'seen' => false, 'note' => 'Bu dekont daha önce yüklenmemiş. Bu ödemenin onaylandığı anlamına gelmez: kişiye teşekkür et, ekibin ödemeyi kontrol edip döneceğini söyle; dekontun fotoğrafını ya da PDF\'ini buradan göndermesini iste. "Ödemeniz onaylandı" deme.' );
	}
	return array(
		'ok'   => true,
		'seen' => true,
		'note' => 'Bu dekont daha önce yüklenmiş: ' . flxc_dekont_dup_text( $dups[0] ) . '. Kişiyi nazikçe uyar: aynı dekont ikinci bir bağış için kullanılamaz; ekip ayrıca kontrol edecek.',
	);
}
