<?php
/**
 * Kara liste: "bana mesaj atmayın" diyen kişilere e-posta, WhatsApp ve SMS gitmez.
 *
 * Liste Flux Chat'nde (1.7.0+) tutulur; ortak sözleşme `flx_contact_blocked` filtresi.
 * Kişinin kendi istediği güvenlik e-postaları (parola sıfırlama, adres değişikliği) gider.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Bu kişiye bu kanaldan ileti gönderilmemeli mi? */
function flxi_contact_blocked( $phone, $email, $channel ) {
	$phone = trim( (string) $phone );
	$email = strtolower( trim( (string) $email ) );
	if ( '' === $phone && '' === $email ) {
		return false;
	}
	return (bool) apply_filters( 'flx_contact_blocked', false, $phone, $email, (string) $channel );
}

/** Kara listeye rağmen gidecek şablonlar: kişinin kendi başlattığı hesap güvenliği e-postaları. */
function flxi_blocklist_exempt( $key ) {
	$keys = apply_filters( 'flxi_blocklist_exempt', array( 'core/password_reset', 'core/password_changed', 'core/email_changed' ) );
	return in_array( (string) $key, (array) $keys, true );
}

/** E-posta alıcı listesinden ("Ad <adres>" biçimleri dahil) kara listedekileri ayırır. */
function flxi_split_blocked_recipients( $to ) {
	$list = is_array( $to ) ? $to : explode( ',', (string) $to );
	$keep = array();
	$drop = array();
	foreach ( $list as $r ) {
		$r = trim( (string) $r );
		if ( '' === $r ) {
			continue;
		}
		$addr = preg_match( '/<([^>]+)>/', $r, $m ) ? $m[1] : $r;
		if ( flxi_contact_blocked( '', $addr, 'email' ) ) {
			$drop[] = $addr;
		} else {
			$keep[] = $r;
		}
	}
	return array( $keep, $drop );
}

/* wp_mail bağlamı: kara listedeki alıcılar çıkarılır, kimse kalmazsa e-posta "engellendi" olarak kaydedilir. */
add_filter(
	'flxi_mail_atts',
	function ( $atts ) {
		if ( empty( $atts['flxi'] ) || $atts['flxi']['block'] || flxi_blocklist_exempt( $atts['flxi']['key'] ) || ! has_filter( 'flx_contact_blocked' ) ) {
			return $atts;
		}
		list( $keep, $drop ) = flxi_split_blocked_recipients( $atts['to'] ?? '' );
		if ( ! $drop ) {
			return $atts;
		}
		if ( $keep ) {
			$atts['to'] = $keep;
		} else {
			$atts['flxi']['block']    = true;
			$atts['flxi']['block_by'] = 'blocklist';
		}
		return $atts;
	}
);
