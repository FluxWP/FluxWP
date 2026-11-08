<?php
/**
 * 1.23.0 (Ahmet): başkası adına bağış.
 * - WhatsApp kişi kartındaki (vCard) numara okunur, asistan adla birlikte numarayı da görür ve özete yazar.
 * - Ödeme onaylanıp sertifika oluşunca sertifika, adına bağış yapılan kişinin numarasına da WhatsApp'tan gider
 *   (Ayarlar › Yapay zekâ › Başkası adına bağış).
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

/** vCard'dan numara: önce waid (WhatsApp numarası), yoksa ilk TEL satırı. */
function flxc_vcard_phone( $vcard ) {
	$vcard = (string) $vcard;
	if ( preg_match( '/waid=(\d+)/', $vcard, $m ) ) {
		return flxc_normalize_phone( '+' . $m[1] );
	}
	if ( preg_match( '/^(?:item\d+\.)?TEL[^:\r\n]*:([^\r\n]+)$/mi', $vcard, $m ) ) {
		return flxc_normalize_phone( trim( $m[1] ) );
	}
	return '';
}

/** Kişi kartı mesajı modele: "Ömer Ali Bellikli · Tel: +90 532 …" (birden çok kart virgülle). */
function flxc_contact_card_text( $body, array $meta ) {
	$cards = (array) ( $meta['vcards'] ?? array() );
	if ( ! $cards ) {
		return '' !== trim( (string) $body ) ? (string) $body : __( '(no name)', 'flx-chat' );
	}
	$out = array();
	foreach ( $cards as $c ) {
		$n     = trim( (string) ( $c['name'] ?? '' ) );
		$out[] = ( '' !== $n ? $n : __( '(no name)', 'flx-chat' ) ) . ( ! empty( $c['phone'] ) ? ' · Tel: ' . flxc_format_phone( $c['phone'] ) : '' );
	}
	return implode( ', ', $out );
}

function flxc_benef_cert_on() {
	return (int) flxc_setting( 'benef_cert_notify' ) && function_exists( 'flxs_build_certificate_message' );
}

function flxc_benef_cert_default_text() {
	return __( 'Hello {ad} 🌱 {bagisci} made a donation in your name: {bagis}. Your certificate is below.', 'flx-chat' );
}

add_action(
	'flxb_wc_certificates_issued',
	function ( $order, $made ) {
		if ( ! flxc_flxb_on() || ! is_object( $order ) || ( ! flxc_benef_cert_on() && ! flxc_ref_cert_on() ) ) {
			return;
		}
		$made = array_map( 'intval', wp_list_pluck( (array) $made, 'id' ) );
		$jobs = array(); // donation id => cert ids (2.4.1: her isme bir sertifika, _flxb_cert_ids).
		foreach ( $order->get_items() as $item ) {
			$did = (int) $item->get_meta( '_flxb_donation_id' );
			$ids = array_filter( array_map( 'intval', (array) $item->get_meta( '_flxb_cert_ids' ) ) );
			$ids = $ids ? $ids : array_filter( array( (int) $item->get_meta( '_flxb_cert_id' ) ) );
			$ids = array_values( array_intersect( $ids, $made ) );
			if ( $did && $ids ) {
				$jobs[ $did ] = $ids;
			}
		}
		if ( $jobs ) {
			$oid = (int) $order->get_id();
			// Ödeme isteğini yavaşlatmasın: gönderim istek sonunda.
			add_action(
				'shutdown',
				function () use ( $jobs, $oid ) {
					foreach ( $jobs as $did => $ids ) {
						if ( flxc_benef_cert_on() ) {
							flxc_benef_cert_send( $ids, $did, $oid );
						}
						if ( flxc_ref_cert_on() ) {
							flxc_ref_cert_send( $ids, $did, $oid );
						}
					}
				}
			);
		}
	},
	30,
	2
);

/** Sertifikadaki ad (Flux Certificates ad_soyad alanı). */
function flxc_cert_name( array $cert ) {
	$v = (array) ( $cert['data']['values'] ?? $cert['data'] ?? array() );
	return trim( (string) ( $v['ad_soyad'] ?? '' ) );
}

/**
 * Sertifikaları bağıştaki "adına" kişilerin numaralarına gönderir: her kişiye kendi adındaki sertifika
 * (tek sertifika varsa o); her sertifika + numara bir kez.
 *
 * @param int[]|int $cert_ids
 */
function flxc_benef_cert_send( $cert_ids, $donation_id, $order_id = 0 ) {
	$d = FLXB_Donations::get( (int) $donation_id );
	if ( ! $d || ! function_exists( 'flxs_get_certificate' ) ) {
		return;
	}
	$certs = array();
	foreach ( (array) $cert_ids as $cid ) {
		$c = flxs_get_certificate( (int) $cid );
		if ( $c && 'revoked' !== ( $c['status'] ?? '' ) ) {
			$certs[] = $c;
		}
	}
	if ( ! $certs ) {
		return;
	}
	$contacts = array_values( array_filter( (array) ( FLXB_Donations::meta( $d )['beneficiary_contacts'] ?? array() ), 'is_array' ) );
	$donor    = ! empty( $d['donor_id'] ) && class_exists( 'FLXB_Donors' ) ? FLXB_Donors::get( (int) $d['donor_id'] ) : null;
	$dphone   = $donor ? flxc_normalize_phone( (string) $donor['phone'] ) : '';
	$conn     = flxc_default_connection();
	if ( ! $conn ) {
		return;
	}
	$tpl  = trim( (string) flxc_setting( 'benef_cert_text' ) );
	$tpl  = '' !== $tpl ? $tpl : flxc_benef_cert_default_text();
	$what = function_exists( 'flxc_staff_donation_title' ) ? flxc_staff_donation_title( $d ) : (string) $d['option_label'];
	foreach ( $contacts as $c ) {
		$phone = flxc_normalize_phone( (string) ( $c['phone'] ?? '' ) );
		if ( '' === $phone || $phone === $dphone ) {
			continue;
		}
		$name = sanitize_text_field( (string) ( $c['name'] ?? '' ) );
		$mine = array();
		foreach ( $certs as $cert ) {
			if ( '' !== $name && flxc_bot_lower( flxc_cert_name( $cert ) ) === flxc_bot_lower( $name ) ) {
				$mine[] = $cert;
			}
		}
		$mine = $mine ? $mine : ( 1 === count( $certs ) ? $certs : array() );
		$mine = array_values(
			array_filter(
				$mine,
				function ( $cert ) use ( $phone ) {
					return ! flxc_cert_sent_once( (int) $cert['id'], $phone, false );
				}
			)
		);
		if ( ! $mine ) {
			continue;
		}
		$chat = flxc_ensure_chat( (string) $conn['id'], $phone . '@s.whatsapp.net', array( 'name' => $name ) );
		if ( ! $chat || flxc_chat_blocked( $chat ) ) {
			continue;
		}
		$first = '' !== $name ? preg_split( '/\s+/u', $name )[0] : '';
		$intro = strtr(
			$tpl,
			array(
				'{ad}'      => $first,
				'{bagisci}' => $donor && '' !== (string) $donor['name'] ? $donor['name'] : __( 'One of our donors', 'flx-chat' ),
				'{bagis}'   => $what,
				'{brand}'   => flxc_brand_name(),
			)
		);
		$ok = flxc_cert_deliver( $chat, $phone, trim( preg_replace( '/[ \t]{2,}/u', ' ', $intro ) ), $mine );
		foreach ( $mine as $cert ) {
			if ( $order_id && function_exists( 'wc_get_order' ) ) {
				$o = wc_get_order( (int) $order_id );
				if ( $o ) {
					/* translators: 1: certificate number, 2: name, 3: phone */
					$o->add_order_note( sprintf( $ok ? __( 'Flux Chat: certificate %1$s was sent on WhatsApp to the person the donation was made for (%2$s, %3$s).', 'flx-chat' ) : __( 'Flux Chat: certificate %1$s could not be sent to the person the donation was made for (%2$s, %3$s).', 'flx-chat' ), (string) $cert['number'], $name, flxc_format_phone( $phone ) ) );
				}
			}
			do_action( 'flxc_benef_cert_sent', $cert, $d, $phone, $ok );
		}
	}
}

/** Bu sertifika bu numaraya gönderildi mi; $mark ise işaretler. */
function flxc_cert_sent_once( $cert_id, $phone, $mark = true ) {
	$sent = get_option( 'flxc_benef_sent', array() );
	$sent = is_array( $sent ) ? $sent : array();
	$key  = (int) $cert_id . '|' . $phone;
	if ( isset( $sent[ $key ] ) ) {
		return true;
	}
	if ( $mark ) {
		$sent[ $key ] = time();
		update_option( 'flxc_benef_sent', array_slice( $sent, -1000, null, true ), false );
	}
	return false;
}

/** Giriş mesajı + her sertifikanın Flux Certificates WhatsApp mesajı. */
function flxc_cert_deliver( $chat, $phone, $intro, array $certs ) {
	$ok = true;
	if ( '' !== $intro ) {
		flxc_send( $chat, array( 'type' => 'text', 'body' => $intro, 'source' => 'certificate', 'user_id' => 0, 'preview' => false ) );
	}
	foreach ( $certs as $i => $cert ) {
		flxc_cert_sent_once( (int) $cert['id'], $phone );
		$m    = flxs_build_certificate_message( $cert, 'whatsapp', array( 'to' => $phone ) );
		$body = is_wp_error( $m ) ? flxs_certificate_url( $cert ) : (string) $m['body'];
		$r    = flxc_send( $chat, array( 'type' => 'text', 'body' => $body, 'source' => 'certificate', 'user_id' => 0, 'delay' => $i ? 900 : 0 ) );
		$ok   = $ok && ! is_wp_error( $r );
	}
	return $ok;
}

/* ---------- 1.29.0 (Ahmet): referans personele de sertifika ---------- */

function flxc_ref_cert_on() {
	return (int) flxc_setting( 'ref_cert_notify' ) && function_exists( 'flxs_build_certificate_message' ) && function_exists( 'flxr_user_phone' );
}

function flxc_ref_cert_default_text() {
	return __( '✅ Certificates for {bagisci}\'s donation are ready: {bagis}. They were also sent to the donor.', 'flx-chat' );
}

/** Bağışın referans personelinin numarası ('' yoksa). */
function flxc_ref_phone( array $d ) {
	$uid = (int) ( $d['ref_user_id'] ?? 0 );
	return $uid && function_exists( 'flxr_user_phone' ) ? flxc_normalize_phone( (string) flxr_user_phone( $uid ) ) : '';
}

/** Ödeme onaylanınca bağışın tüm sertifikaları referans personelin WhatsApp'ına da gider (bir kez). */
function flxc_ref_cert_send( $cert_ids, $donation_id, $order_id = 0 ) {
	$d     = FLXB_Donations::get( (int) $donation_id );
	$phone = $d ? flxc_ref_phone( $d ) : '';
	$conn  = flxc_default_connection();
	if ( '' === $phone || ! $conn ) {
		return;
	}
	$donor  = ! empty( $d['donor_id'] ) && class_exists( 'FLXB_Donors' ) ? FLXB_Donors::get( (int) $d['donor_id'] ) : null;
	if ( $donor && flxc_normalize_phone( (string) $donor['phone'] ) === $phone ) {
		return; // Personel kendi adına bağış yaptı; sertifika zaten ona gidiyor.
	}
	$certs = array();
	foreach ( (array) $cert_ids as $cid ) {
		$c = flxs_get_certificate( (int) $cid );
		if ( $c && 'revoked' !== ( $c['status'] ?? '' ) && ! flxc_cert_sent_once( (int) $c['id'], $phone, false ) ) {
			$certs[] = $c;
		}
	}
	if ( ! $certs ) {
		return;
	}
	$u    = get_userdata( (int) $d['ref_user_id'] );
	$chat = flxc_ensure_chat( (string) $conn['id'], $phone . '@s.whatsapp.net', array( 'name' => $u ? $u->display_name : '' ) );
	if ( ! $chat || flxc_chat_blocked( $chat ) ) {
		return;
	}
	$tpl   = trim( (string) flxc_setting( 'ref_cert_text' ) );
	$tpl   = '' !== $tpl ? $tpl : flxc_ref_cert_default_text();
	$intro = strtr(
		$tpl,
		array(
			'{ad}'      => $u ? flxc_staff_first_name( $u->display_name ) : '',
			'{bagisci}' => $donor && '' !== (string) $donor['name'] ? $donor['name'] : __( 'Your donor', 'flx-chat' ),
			'{bagis}'   => function_exists( 'flxc_staff_donation_title' ) ? flxc_staff_donation_title( $d ) : (string) $d['option_label'],
			'{no}'      => (string) (int) $donation_id,
			'{brand}'   => flxc_brand_name(),
		)
	);
	$ok = flxc_cert_deliver( $chat, $phone, trim( $intro ), $certs );
	if ( $order_id && function_exists( 'wc_get_order' ) ) {
		$o = wc_get_order( (int) $order_id );
		if ( $o ) {
			/* translators: 1: certificate numbers, 2: phone */
			$o->add_order_note( sprintf( $ok ? __( 'Flux Call Center: certificates %1$s were sent to the referring staff member on WhatsApp (%2$s).', 'flx-chat' ) : __( 'Flux Call Center: certificates %1$s could not be sent to the referring staff member (%2$s).', 'flx-chat' ), implode( ', ', wp_list_pluck( $certs, 'number' ) ), flxc_format_phone( $phone ) ) );
		}
	}
}
