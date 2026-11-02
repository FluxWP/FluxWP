<?php
/**
 * Personel araçları (1.13.0, Flux Roles 1.1.0 sözleşmesi): yazan numara bir site kullanıcısınınsa asistan
 * ona ek araçlar açar. Araçlar rol adına değil yetkiye bakar; her çağrıda yetki yeniden kontrol edilir.
 *
 * - Numara yalnız WhatsApp kimliğinden (JID) alınır; gizli numarada (@lid) kodla doğrulanmış numara.
 * - flxb_create_ref: başkası adına bağış (referans = personel), bağışçıya talep mesajı + ödeme linki.
 * - flxb_donors_own / flxb_view_own: kendi bağışçıları ve bağışları.
 * - flx_reports_view: genel toplamlar ve personel bazında özet.
 * - flxb_ref_notify: referansı olduğu bağış ödenince/iptal olunca WhatsApp bildirimi.
 *
 * Yeni araç eklemek: flxc_staff_tool_list (tanım + yetki) ve flxc_staff_tool (cevap) filtreleri.
 *
 * @package FLX_Cagri_Merkezi
 */

defined( 'ABSPATH' ) || exit;

/** Flux Roles kurulu ve ayarda açık mı? */
function flxc_staff_on() {
	return function_exists( 'flxr_phone_identity' ) && function_exists( 'flxr_phone_can' ) && (int) flxc_setting( 'staff_on' );
}

/** Uyguladığımız yetkiler (Kullanıcılar › Roller ve erişim'de "güncelleme bekleniyor" rozeti kalkar). */
add_filter(
	'flx_role_caps_supported',
	function ( $caps ) {
		return array_merge( (array) $caps, array( 'flxc_staff_tools', 'flxb_create_ref', 'flxb_donors_own', 'flxb_ref_notify' ) );
	}
);

/**
 * Sohbetin personel numarası: telefon JID'i; gizli numarada (@lid) bu sohbette kodla doğrulanmış ve
 * bir kullanıcıya kayıtlı numara. Mesaj metnindeki numaraya hiç bakılmaz.
 */
function flxc_staff_phone( $chat, array $state ) {
	$jid = (string) ( $chat['jid'] ?? '' );
	if ( '' === $jid || flxc_is_group_jid( $jid ) ) {
		return '';
	}
	$p = flxc_jid_phone( $jid );
	if ( '' !== $p ) {
		return $p;
	}
	// 1.31.0: @lid sohbette WhatsApp'ın sonradan bildirdiği gerçek numara (gizli kimliğin rakamları değil).
	$cp = (string) ( $chat['phone'] ?? '' );
	if ( '' !== $cp && $cp !== flxc_lid_digits( $jid ) ) {
		return $cp;
	}
	foreach ( flxc_verify_phones( $state ) as $v ) {
		if ( flxr_user_by_phone( $v ) ) {
			return $v;
		}
	}
	return '';
}

/**
 * Personel kimliği ya da null. Test sohbetinde ($state['as_user']) seçilen kullanıcı gibi davranır.
 *
 * @return array|null user_id, name, role, role_label, caps[], is_admin, phone
 */
function flxc_staff_identity( $chat, array $state ) {
	if ( ! flxc_staff_on() ) {
		return null;
	}
	if ( ! empty( $state['as_user'] ) ) {
		$u = get_userdata( (int) $state['as_user'] );
		if ( ! $u ) {
			return null;
		}
		$phone = function_exists( 'flxr_user_phone' ) ? (string) flxr_user_phone( $u->ID ) : '';
		$id    = '' !== $phone ? flxr_phone_identity( $phone ) : null;
		if ( ! $id || (int) $id['user_id'] !== (int) $u->ID ) {
			$id = array( 'user_id' => (int) $u->ID, 'name' => (string) $u->display_name, 'role' => '', 'role_label' => '', 'caps' => array(), 'is_admin' => user_can( $u, 'manage_options' ) );
		}
		$id['phone'] = $phone;
		$id['test']  = true;
	} else {
		$phone = flxc_staff_phone( $chat, $state );
		$id    = '' !== $phone ? flxr_phone_identity( $phone ) : null;
		if ( ! $id ) {
			return null;
		}
		$id['phone'] = $phone;
	}
	return flxc_staff_can( $id, 'flxc_staff_tools' ) ? $id : null;
}

/** Yetki kontrolü: rol sonradan değişebilir, her seferinde kullanıcıdan okunur. */
function flxc_staff_can( $id, $cap ) {
	if ( ! $id ) {
		return false;
	}
	$u = get_userdata( (int) $id['user_id'] );
	return $u && ( user_can( $u, 'manage_options' ) || user_can( $u, $cap ) );
}

/** Araç tanımları ve gereken yetki (biri yeterli). */
function flxc_staff_tool_list( $f ) {
	$person = array(
		'type'       => 'object',
		'properties' => array(
			'name'  => array( 'type' => 'string' ),
			'count' => array( 'type' => 'integer', 'description' => 'Bu isme kaç adet; varsayılan 1. "Tamamı X adına" ise adet sayısının tamamı. Aynı ismi tekrarlama, count ver.' ),
		),
		'required'   => array( 'name' ),
	);
	$list = array(
		array(
			'caps' => array( 'flxb_create_ref' ),
			'def'  => $f(
				'staff_create_donation',
				'PERSONEL: başkası (bağışçı) adına bağış kaydı açar, referans bu personel olur. Bağışçıya WhatsApp\'tan "talebiniz alındı" mesajı ve ödeme linki sistemden gider. Önce donation_options ile tür/seçenek bul, personele özeti göster; personel onaylayınca confirmed=true ile çağır. Farklı kişiler adına ayrı sertifika (ör. 3 fidan, 3 isim) TEK kayıt ve TEK ödemedir: quantity=adet, beneficiaries=her sertifika için bir isim (o isme düşen adet count ile; tamamı tek isme ise count=adet); bunun için ayrı kayıt açma.',
				array(
					'donor_name'    => array( 'type' => 'string', 'description' => 'Bağışçının adı soyadı' ),
					'donor_phone'   => array( 'type' => 'string', 'description' => 'Bağışçının WhatsApp numarası (ödeme linki buna gider)' ),
					'donor_email'   => array( 'type' => 'string' ),
					'type_id'       => array( 'type' => 'integer' ),
					'option'        => array( 'type' => 'string', 'description' => 'Seçenek anahtarı (donation_options)' ),
					'quantity'      => array( 'type' => 'integer' ),
					'amount'        => array( 'type' => 'number', 'description' => 'Yalnız serbest tutarlı türde' ),
					'beneficiaries' => array( 'type' => 'array', 'items' => $person, 'description' => 'Sertifikada yazacak isimler, her isim bir öğe ve o isme düşen adet count ile (her isme ayrı sertifika çıkar); boşsa bağışçının adı' ),
					'replaces'      => array( 'type' => 'integer', 'description' => 'Personel az önce açılan bir bağışı düzeltiyorsa ("pardon 100 fidan olacak") o ödenmemiş bağışın no\'su: yeni kayıt açılınca eskisi iptal edilir.' ),
					'separate'      => array( 'type' => 'boolean', 'description' => 'Yalnız aynı bağışçıya bilerek ikinci, ayrı ödemeli bir bağış açılıyorsa true (personel ayrı ayrı ödeme istedi ya da düzeltme değil yeni bir bağış).' ),
					'method'        => array( 'type' => 'string', 'enum' => array( 'choose', 'card', 'havale' ) ),
					'send_to_donor' => array( 'type' => 'boolean', 'description' => 'Varsayılan true. false: link yalnız personele döner, personel kendisi iletir.' ),
					'confirmed'     => array( 'type' => 'boolean' ),
				),
				array( 'donor_name', 'type_id', 'confirmed' )
			),
		),
		array(
			'caps' => array( 'flxb_create_ref' ),
			'def'  => $f(
				'staff_payment_link',
				'PERSONEL: referansı bu personel olan bir bağışın ödeme linkini yeniden üretir; istenirse bağışçıya yeniden gönderir.',
				array(
					'donation'      => array( 'type' => 'integer' ),
					'method'        => array( 'type' => 'string', 'enum' => array( 'choose', 'card', 'havale' ) ),
					'send_to_donor' => array( 'type' => 'boolean' ),
				),
				array( 'donation' )
			),
		),
		array(
			'caps' => array( 'flxb_create_ref' ),
			'def'  => $f(
				'staff_send_certificate',
				'PERSONEL: referansı bu personel olan ÖDENMİŞ bir bağışın sertifikalarını WhatsApp\'tan personelin kendisine (me), bağışçıya (donor) ya da ikisine (both) yeniden gönderir. Önce confirmed olmadan çağır: kime gideceği (numaralarla) döner, personele yazıp onay iste; onaylarsa confirmed=true. Bağış başına sınır vardır.',
				array(
					'donation'  => array( 'type' => 'integer' ),
					'to'        => array( 'type' => 'string', 'enum' => array( 'me', 'donor', 'both' ) ),
					'confirmed' => array( 'type' => 'boolean' ),
				),
				array( 'donation', 'to' )
			),
		),
		array(
			'caps' => array( 'flxb_donors_own', 'flxb_view_own' ),
			'def'  => $f(
				'staff_my_donations',
				'PERSONEL: yalnız bu personelin açtığı / referansı olduğu bağışlar ve bağışçılar (ad, tutar, durum, tarih) ve toplamlar. "Açtığım bağış", "bağışçılarım" sorularında bunu kullan; genel rapor için değil.',
				array(
					'period' => array( 'type' => 'string', 'enum' => array( 'today', 'week', 'month', 'year', 'all' ) ),
					'status' => array( 'type' => 'string', 'enum' => array( 'all', 'pending', 'completed', 'canceled' ) ),
					'search' => array( 'type' => 'string', 'description' => 'Bağışçı adı ya da numarası' ),
				)
			),
		),
		array(
			'caps' => array( 'flx_reports_view' ),
			'def'  => $f(
				'staff_report',
				'İDARECİ: kurumun genel bağış raporu (toplam, ödenen, bekleyen, iptal) ve personel bazında özet. "Genel rapor", "dünkü/bu ayki bağışlar" sorularında bunu kullan.',
				array( 'period' => array( 'type' => 'string', 'enum' => array( 'today', 'yesterday', 'week', 'month', 'year' ) ) )
			),
		),
	);
	return (array) apply_filters( 'flxc_staff_tool_list', $list, $f );
}

/** Bu sohbette açılacak araç tanımları. */
function flxc_staff_tools_for( $chat, array $state ) {
	$id = flxc_staff_identity( $chat, $state );
	if ( ! $id || ! function_exists( 'flxc_flxb_on' ) || ! flxc_flxb_on() ) {
		return array();
	}
	$out = array();
	foreach ( flxc_staff_tool_list( flxc_bot_tool_def_fn() ) as $t ) {
		foreach ( $t['caps'] as $cap ) {
			if ( flxc_staff_can( $id, $cap ) ) {
				$out[] = $t['def'];
				break;
			}
		}
	}
	return $out;
}

/** Talimata eklenen bölüm (yalnız personel numarasında). */
function flxc_staff_prompt( $chat, array $state ) {
	$id = flxc_staff_identity( $chat, $state );
	if ( ! $id ) {
		return flxc_staff_unknown_rule( $chat );
	}
	$names = wp_list_pluck( wp_list_pluck( flxc_staff_tools_for( $chat, $state ), 'function' ), 'name' );
	$lines = array(
		'Bu numara ekibimizden ' . $id['name'] . ( '' !== $id['role_label'] ? ' (' . $id['role_label'] . ')' : '' ) . '. Bu kişi bağışçı değil, personel: ona müşteri gibi bağış teklif etme, kısa ve iş odaklı yaz, adıyla hitap et (' . flxc_staff_first_name( $id['name'] ) . '; kişi kendisi başka bir hitap istemedikçe sertifika, bağış kaydı ya da hafızadaki başka bir isimle hitap etme, onlar başkası adına olabilir). Kendi adına bağış yapmak isterse normal bağış akışını kullan.',
	);
	if ( in_array( 'staff_create_donation', $names, true ) ) {
		$lines[] = '- "Ahmet Yılmaz adına 1 fidan" gibi isteklerde staff_create_donation kullan: bağışçının adı soyadı ve WhatsApp numarası gerekir (numara yoksa sor; personel "link bana gelsin" derse send_to_donor=false). Sertifikada başka isim yazacaksa beneficiaries; farklı isimlere ayrı sertifika istenirse yine TEK kayıt (quantity=adet, her isim beneficiaries\'te ayrı öğe, adedi count ile), tek ödeme linki. Ayrı ayrı ödeme açıkça istenmedikçe aynı bağışçıya birden çok kayıt açma. Personel az önce açılan bağışı düzeltirse ("pardon 100 olacak") yeni kaydı replaces=eski no ile aç, eskisi iptal olur. Önce donation_options ile türü bul, özeti (bağışçı, numara, tür, adet, tutar, sertifika ismi) personele göster, onaylayınca confirmed=true. Bağışçıya mesajı sistem gönderir; sen personele kısa sonuç yaz.';
		$lines[] = '- Bağışçıya linki yeniden göndermek için staff_payment_link.';
		$lines[] = '- Ödenen bağışın sertifikaları bağışçıya ve sana (personele) sistemden kendiliğinden gider. Personel sertifikayı kendisine ya da bağışçıya yeniden göndermeni isterse staff_send_certificate: önce confirmed olmadan çağır, dönen numaraları yazıp onay iste, onaylarsa confirmed=true. "Böyle bir işlemim yok" deme.';
	}
	if ( in_array( 'staff_my_donations', $names, true ) ) {
		$lines[] = '- "Bağışçılarım", "bugün kaç bağış geldi", "kim ödedi" gibi sorularda staff_my_donations; yalnız dönen kayıtları söyle.';
		$lines[] = '- Personel kendi açtığı ya da başkası adına açtığı bağışı sorarsa ("açtığım bağış ödendi mi") my_records değil staff_my_donations kullan; my_records yalnız kendi adına yaptığı bağışlar içindir.';
	}
	if ( in_array( 'staff_report', $names, true ) ) {
		$lines[] = '- Genel rapor, toplamlar, personel performansı sorulursa staff_report.';
	}
	if ( ! empty( $id['test'] ) ) {
		$lines[] = '- (Test sohbeti: kayıt açılmaz, bağışçıya mesaj gitmez.)';
	}
	return "## Personel\n" . implode( "\n", $lines );
}

/**
 * 1.31.0: personel olarak tanınmayan numara "ben personelim / referansım" derse. Personel araçları olmadan
 * başkası adına kayıt açılamaz ve ödeme mesajları yalnız bu sohbete gider; model bunu vaat etmesin.
 */
function flxc_staff_unknown_rule( $chat ) {
	if ( ! function_exists( 'flxc_flxb_on' ) || ! flxc_flxb_on() || flxc_is_group_jid( (string) ( $chat['jid'] ?? '' ) ) ) {
		return '';
	}
	$verify = function_exists( 'flxc_verify_on' ) && flxc_verify_on();
	return "## Personel olduğunu söyleyen kişi
"
		. '- Bu numara personel olarak tanınmıyor. Kişi personel, referans ya da ekipten olduğunu söyleyip başkası (bağışçı) adına bağış açmak isterse normal bağış akışına geçme. '
		. ( $verify
			? 'Ona bu numaranın personel numarası olarak görünmediğini söyle ve Flux Roles profilindeki telefon numarasını iste; verirse verify_send type=phone ile kod gönder, kodu verify_check ile kontrol et. Doğrulanınca personel araçları açılır, sonra staff_create_donation ile devam et. '
			: 'Ona bu numaranın personel numarası olarak görünmediğini, kayıtlı personel numarasından yazması gerektiğini söyle. ' )
		. 'Personel araçları yokken ödeme linki ve bilgiler yalnız bu sohbete gider: "bağışçının numarasına gönderilecek" deme, save_details ile bağışçının adını bu kişinin adı gibi kaydetme.';
}

/** Test sohbetinde "kim gibi yaz" listesi: personel araçları yetkili kullanıcılar. */
function flxc_staff_test_users() {
	if ( ! flxc_staff_on() ) {
		return array();
	}
	$out = array();
	foreach ( get_users( array( 'capability__in' => array( 'flxc_staff_tools' ), 'number' => 100, 'orderby' => 'display_name' ) ) as $u ) {
		$out[] = array( 'id' => (int) $u->ID, 'name' => $u->display_name . ( function_exists( 'flxr_user_role' ) && flxr_user_role( $u ) && isset( flxr_roles()[ flxr_user_role( $u ) ] ) ? ' · ' . flxr_roles()[ flxr_user_role( $u ) ]['label'] : '' ) );
	}
	return $out;
}

/* ---------- Araç cevapları ---------- */

/** Dönem başlangıç/bitiş günleri (site saat dilimi, Y-m-d). */
function flxc_staff_period( $period ) {
	$today = wp_date( 'Y-m-d' );
	switch ( $period ) {
		case 'today':
			return array( $today, $today );
		case 'yesterday':
			$y = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS );
			return array( $y, $y );
		case 'week':
			return array( wp_date( 'Y-m-d', time() - 6 * DAY_IN_SECONDS ), $today );
		case 'month':
			return array( wp_date( 'Y-m-01' ), $today );
		case 'year':
			return array( wp_date( 'Y-01-01' ), $today );
	}
	return array( '', '' );
}

function flxc_staff_status_label( $s ) {
	$l = array(
		'pending'            => 'ödeme bekleniyor',
		'completed'          => 'ödendi',
		'canceled'           => 'iptal',
		'failed'             => 'başarısız',
		'refunded'           => 'iade edildi',
		'partially_refunded' => 'kısmen iade',
	);
	return $l[ $s ] ?? $s;
}

/** Bağış başlığı: "Fidan bağışı · 3 adet". */
function flxc_staff_donation_title( array $d ) {
	$t = (string) ( $d['option_label'] ?? '' );
	if ( ! empty( $d['type_id'] ) && class_exists( 'FLXB_Types' ) ) {
		$type = FLXB_Types::get( (int) $d['type_id'] );
		if ( $type && '' !== (string) $type['title'] ) {
			$t = $type['title'] . ( '' !== $t && false === mb_stripos( $type['title'], $t ) ? ' · ' . $t : '' );
		}
	}
	$q = (int) ( $d['quantity'] ?? 1 );
	return ( '' !== $t ? $t : __( 'Bağış', 'flx-chat' ) ) . ( $q > 1 ? ' · ' . $q . ' adet' : '' );
}

/**
 * @param string $name  araç adı
 * @param array  $state sohbet durumu (değişirse $save ile yazılır)
 */
function flxc_staff_tool( $name, array $a, $chat, array &$state, array &$ctx, $sandbox, $save ) {
	$id = flxc_staff_identity( $chat, $state );
	if ( ! $id ) {
		return array( 'ok' => false, 'error' => 'Bu araç yalnız kayıtlı personel numarasından kullanılır.' );
	}
	$need = array();
	foreach ( flxc_staff_tool_list( flxc_bot_tool_def_fn() ) as $t ) {
		if ( $t['def']['function']['name'] === $name ) {
			$need = $t['caps'];
		}
	}
	$ok = false;
	foreach ( $need as $cap ) {
		$ok = $ok || flxc_staff_can( $id, $cap );
	}
	if ( ! $ok ) {
		return array( 'ok' => false, 'error' => 'Bu personelin bu işlem için yetkisi yok; nazikçe söyle.' );
	}
	switch ( $name ) {
		case 'staff_create_donation':
			return flxc_staff_create_donation( $id, $a, $chat, $state, $ctx, $sandbox, $save );
		case 'staff_payment_link':
			return flxc_staff_payment_link( $id, $a, $chat, $sandbox );
		case 'staff_send_certificate':
			return flxc_staff_send_certificate( $id, $a, $state, $ctx, $sandbox, $save );
		case 'staff_my_donations':
			return flxc_staff_my_donations( $id, $a, $state, $sandbox );
		case 'staff_report':
			return flxc_staff_report( (string) ( $a['period'] ?? 'today' ) );
	}
	$r = apply_filters( 'flxc_staff_tool', null, $name, $a, $id, $chat, $sandbox );
	return null === $r ? array( 'ok' => false, 'error' => 'Bilinmeyen araç.' ) : $r;
}

/** Bağışçıya giden ilk mesaj. */
function flxc_staff_donor_text( $donor, $staff, $what ) {
	$t = trim( (string) flxc_setting( 'staff_donor_text' ) );
	$t = '' !== $t ? $t : __( 'Merhaba {ad} 🌱 {personel} aracılığıyla {bagis} bağış talebiniz alındı. Ödemeyi aşağıdaki bilgilerle yapabilirsiniz.', 'flx-chat' );
	$first = trim( strtok( (string) $donor, ' ' ) );
	return strtr( $t, array( '{ad}' => '' !== $first ? $first : $donor, '{adsoyad}' => $donor, '{personel}' => $staff, '{bagis}' => $what ) );
}

/** Bağışçının sohbeti (yoksa açılır), personelin bağlantısından. */
function flxc_staff_donor_chat( $chat, $phone, $name ) {
	$conn = (string) ( $chat['conn'] ?? '' );
	if ( '' === $conn ) {
		$c    = flxc_default_connection();
		$conn = $c ? (string) $c['id'] : '';
	}
	if ( '' === $conn || '' === $phone ) {
		return null;
	}
	return flxc_ensure_chat( $conn, $phone . '@s.whatsapp.net', array( 'name' => $name ) );
}

/** "HALİT ALTUN" → "Halit" */
function flxc_staff_first_name( $name ) {
	$first = (string) strtok( trim( (string) $name ), ' ' );
	if ( '' === $first ) {
		return (string) $name;
	}
	$low = flxc_bot_lower( $first );
	$one = mb_substr( $low, 0, 1 );
	return strtr( $one, array( 'i' => 'İ', 'ı' => 'I' ) ) === $one ? mb_strtoupper( $one ) . mb_substr( $low, 1 ) : strtr( $one, array( 'i' => 'İ', 'ı' => 'I' ) ) . mb_substr( $low, 1 );
}

/** Bağışın durumu (test sohbetinde örnek kayıtlardan): pending | completed | canceled | ''. */
function flxc_staff_donation_status( array $id, $did, array $state, $sandbox ) {
	if ( $sandbox ) {
		foreach ( (array) ( $state['tdons'] ?? array() ) as $td ) {
			if ( (int) $td['id'] === (int) $did ) {
				return (string) $td['status'];
			}
		}
		return '';
	}
	$d = flxc_staff_own_donation( $id, $did );
	return $d ? (string) $d['status'] : '';
}

/**
 * 1.27.1 (Ahmet): aynı bağışçıya ikinci kayıt. Ayrı sertifika ayrı kayıt demek değil (tek kayıt, tek ödeme);
 * düzeltmede eski kayıt replaces ile iptal edilir. Bilerek ikinci bağışsa separate=true.
 *
 * @return string hata ya da ''
 */
function flxc_staff_dup_error( array $id, $key, array $a, array $state, array $ctx, $sandbox ) {
	if ( ! empty( $a['separate'] ) || ! empty( $a['replaces'] ) ) {
		return '';
	}
	foreach ( (array) ( $state['staff_recent'] ?? array() ) as $r ) {
		if ( $r['key'] !== $key || (int) $r['ts'] < time() - 3 * HOUR_IN_SECONDS ) {
			continue;
		}
		if ( in_array( (int) $r['id'], (array) ( $ctx['staff_made'] ?? array() ), true ) ) {
			return sprintf( 'Bu turda bu bağışçıya zaten #%d (%s) açıldı. Farklı isimlere ayrı sertifika TEK kayıtta olur: o kaydı replaces=%d ile quantity=toplam adet ve beneficiaries=her sertifika için bir isim (adedi count) vererek yeniden aç (eskisi iptal olur). Personel açıkça ayrı ayrı ödeme istediyse separate=true.', (int) $r['id'], $r['what'], (int) $r['id'] );
		}
		if ( 'pending' === flxc_staff_donation_status( $id, $r['id'], $state, $sandbox ) ) {
			return sprintf( 'Bu bağışçının ödenmemiş #%d (%s) kaydı var. Personel onu düzeltiyorsa (adet, isim, tutar) replaces=%d ile çağır, eskisi iptal olur; bu ayrı, yeni bir bağışsa separate=true ile çağır. Emin değilsen personele sor.', (int) $r['id'], $r['what'], (int) $r['id'] );
		}
	}
	return '';
}

/** replaces: yeni kayıt açıldıktan sonra eski ödenmemiş kaydı iptal eder. */
function flxc_staff_cancel_replaced( array $id, $old, $new, $chat, array &$state, $sandbox ) {
	if ( $sandbox ) {
		foreach ( (array) ( $state['tdons'] ?? array() ) as $i => $td ) {
			if ( (int) $td['id'] === (int) $old && 'pending' === $td['status'] ) {
				$state['tdons'][ $i ]['status'] = 'canceled';
				return true;
			}
		}
		return false;
	}
	$d = flxc_staff_own_donation( $id, $old );
	if ( ! $d || 'pending' !== $d['status'] ) {
		return false;
	}
	/* translators: 1: staff, 2: new donation id */
	$note  = sprintf( __( 'Personel %1$s düzeltti; yerine #%2$d açıldı.', 'flx-chat' ), $id['name'], (int) $new );
	$order = function_exists( 'flxc_pay_order' ) ? flxc_pay_order( (int) $old ) : null;
	if ( $order ) {
		if ( ! $order->has_status( array( 'pending', 'on-hold' ) ) ) {
			return false;
		}
		$order->update_status( 'cancelled', $note );
	} elseif ( ! empty( $d['uniq'] ) ) {
		FLXB_Donations::set_status_by_uniq( $d['uniq'], 'canceled' );
		FLXB_Donations::add_audit( (int) $old, 'status', array( 'note' => $note ) );
	} else {
		return false;
	}
	if ( ! empty( $chat['id'] ) ) {
		/* translators: 1: old donation id, 2: new donation id */
		flxc_system_message( $chat, sprintf( __( 'Bağış #%1$d iptal edildi, yerine #%2$d açıldı.', 'flx-chat' ), (int) $old, (int) $new ) );
	}
	return true;
}

function flxc_staff_create_donation( array $id, array $a, $chat, array &$state, array &$ctx, $sandbox, $save ) {
	if ( empty( $a['confirmed'] ) ) {
		return array( 'ok' => false, 'error' => 'Önce özeti personele gösterip onay al, sonra confirmed=true.' );
	}
	$replaces = (int) ( $a['replaces'] ?? 0 );
	if ( $replaces && 'pending' !== flxc_staff_donation_status( $id, $replaces, $state, $sandbox ) ) {
		return array( 'ok' => false, 'error' => sprintf( '#%d düzeltilemez: senin açtığın ödenmemiş bir kayıt değil (ödenmiş, iptal ya da başkasının). replaces olmadan çağır ya da personele sor.', $replaces ) );
	}
	$donor = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( (string) ( $a['donor_name'] ?? '' ) ) ) );
	if ( mb_strlen( $donor ) < 3 ) {
		return array( 'ok' => false, 'error' => 'Bağışçının adı soyadı gerekli; personele sor.' );
	}
	$send  = ! isset( $a['send_to_donor'] ) || ! empty( $a['send_to_donor'] );
	$phone = ! empty( $a['donor_phone'] ) ? flxc_normalize_phone( (string) $a['donor_phone'] ) : '';
	if ( $send && strlen( $phone ) < 10 ) {
		return array( 'ok' => false, 'error' => 'Ödeme linkini göndermek için bağışçının WhatsApp numarası gerekli; personele sor (ya da link personele gelsin: send_to_donor=false).' );
	}
	$o = flxc_bot_build_order(
		array(
			'type_id'       => (int) ( $a['type_id'] ?? 0 ),
			'option'        => (string) ( $a['option'] ?? '' ),
			'quantity'      => (int) ( $a['quantity'] ?? 1 ),
			'amount'        => $a['amount'] ?? 0,
			'beneficiaries' => ! empty( $a['beneficiaries'] ) ? (array) $a['beneficiaries'] : array( array( 'name' => $donor, 'count' => max( 1, (int) ( $a['quantity'] ?? 1 ) ) ) ),
		)
	);
	if ( is_wp_error( $o ) ) {
		return array( 'ok' => false, 'error' => $o->get_error_message() );
	}
	$what   = $o['title'] . ' · ' . number_format_i18n( $o['qty'] ) . ( '' !== $o['unit'] ? ' ' . $o['unit'] : '' );
	$key    = '' !== $phone ? $phone : mb_strtolower( $donor );
	$dup    = flxc_staff_dup_error( $id, $key, $a, $state, $ctx, $sandbox );
	if ( '' !== $dup ) {
		return array( 'ok' => false, 'error' => $dup );
	}
	$remember = function ( $did ) use ( $id, $key, $what, $replaces, $chat, &$state, &$ctx, $sandbox ) {
		$ctx['staff_made'][]   = (int) $did;
		$state['staff_recent'] = array_slice( array_merge( (array) ( $state['staff_recent'] ?? array() ), array( array( 'id' => (int) $did, 'key' => $key, 'what' => $what, 'ts' => time() ) ) ), -10 );
		return $replaces ? flxc_staff_cancel_replaced( $id, $replaces, $did, $chat, $state, $sandbox ) : false;
	};
	$method = flxc_pay_default_method( (string) ( $a['method'] ?? '' ) );
	$email  = ! empty( $a['donor_email'] ) && is_email( $a['donor_email'] ) ? sanitize_email( (string) $a['donor_email'] ) : '';
	if ( $sandbox ) {
		$tmp          = $state;
		$tmp['order'] = $o;
		$tid          = flxc_bot_sandbox_donation( $tmp, $method );
		$state['tdons'] = $tmp['tdons'];
		$gone = $remember( $tid );
		$save( $state );
		$msgs = array_merge( array( flxc_staff_donor_text( $donor, $id['name'], $what ) ), flxc_bot_payment_messages( $o, $donor, flxc_bot_sandbox_pay( $tid, $method ) ) );
		// 1.20.0: test olduğu modele söylenmez; gerçek hattaki gibi cevap verir (personel test panelinde görür).
		return array( 'ok' => true, 'donation' => $tid, 'replaced' => $gone ? $replaces : 0, 'sent_to_donor' => true, 'donor_messages' => $msgs, 'note' => 'Bağış açıldı; bağışçıya talebin alındığı mesajı ve ödeme linki gönderildi.' . ( $gone ? ' Eski #' . $replaces . ' iptal edildi.' : '' ) . ' Personele kısa sonuç yaz.' );
	}
	if ( ! flxc_flxb_on() ) {
		return array( 'ok' => false, 'error' => 'Bağış sistemi kapalı.' );
	}
	list( $names, $contacts ) = flxc_bot_benef_lines( $o );
	$res = flxc_create_donation(
		array(
			'type_id'              => $o['type'],
			'option_key'           => $o['option'],
			'quantity'             => $o['qty'],
			'amount'               => $o['amount'],
			'status'               => 'pending',
			'method'               => 'bank_transfer',
			'name'                 => $donor,
			'phone'                => $phone,
			'email'                => $email,
			'beneficiaries'        => implode( "\n", $names ),
			'beneficiary_contacts' => $contacts,
			/* translators: %s: staff name */
			'note'                 => sprintf( __( 'Personel %s WhatsApp asistanıyla oluşturdu.', 'flx-chat' ), $id['name'] ),
			'user_id'              => (int) $id['user_id'],
			'ref_user_id'          => (int) $id['user_id'],
			'source_detail'        => 'flx-chat-staff',
		),
		null
	);
	if ( is_wp_error( $res ) ) {
		return array( 'ok' => false, 'error' => $res->get_error_message() );
	}
	$did  = (int) $res['donation']['id'];
	$gone = $remember( $did );
	$save( $state );
	flxc_bot_stat_add( array( 'staff_donations' => 1 ) );
	$pay  = flxc_bot_pay( $did, $method );
	$msgs = array_merge( array( flxc_staff_donor_text( $donor, $id['name'], $what ) ), flxc_bot_payment_messages( $o, $donor, $pay ) );
	/* translators: 1: staff, 2: what, 3: donation id */
	$sys  = sprintf( __( 'Personel %1$s aracılığıyla bağış kaydı açıldı: %2$s (#%3$d)', 'flx-chat' ), $id['name'], $what, $did );
	$sent = false;
	$dc   = '' !== $phone ? flxc_staff_donor_chat( $chat, $phone, $donor ) : null;
	if ( $dc ) {
		// Bağışçı cevap verirse asistan bağışı bilir (ödeme linki, iptal, dekont).
		$ds             = flxc_bot_state( $dc );
		$ds['order']    = $o;
		$ds['donation'] = $did;
		$ds['done_sig'] = md5( wp_json_encode( $o ) );
		$ds['full']     = '' !== $ds['full'] ? $ds['full'] : $donor;
		$ds['pay_method'] = $pay ? $pay['method'] : 'havale';
		flxc_bot_save_state( (int) $dc['id'], $ds );
		flxc_mem_save_benef( $dc, (array) $o['benef'], $donor );
		flxc_system_message( $dc, $sys );
		if ( $send ) {
			if ( flxc_chat_blocked( $dc ) ) {
				$send = false;
			} else {
				foreach ( $msgs as $i => $t ) {
					flxc_send( $dc, array( 'type' => 'text', 'body' => $t, 'source' => 'bot', 'user_id' => 0, 'preview' => false, 'delay' => $i ? 900 : 0 ) );
				}
				$sent = true;
			}
		}
	}
	if ( ! empty( $chat['id'] ) ) {
		flxc_system_message( $chat, $sys );
	}
	$out = array(
		'ok'           => true,
		'donation'     => $did,
		'what'         => $what,
		'amount'       => flxc_money( $o['amount'], $o['currency'] ),
		'sent_to_donor' => $sent,
		'replaced'     => $gone ? $replaces : 0,
		'pay_url'      => $pay ? $pay['url'] : '',
		'note'         => ( $gone ? 'Eski #' . $replaces . ' iptal edildi, bunu da söyle. ' : '' ) . ( $sent ? 'Bağışçıya talep mesajı ve ödeme bilgileri gönderildi. Personele kısa sonuç yaz; ödeme ya da iptal olursa sistem ona haber verir.' : 'Bağışçıya mesaj gönderilmedi (istenmedi ya da numara kara listede). Ödeme linkini (pay_url) personele ver, o iletsin.' ),
	);
	if ( ! $sent ) {
		$out['donor_messages'] = $msgs;
	}
	return $out;
}

/** Bağışın sertifikaları (Flux Donate: bağış meta "certificates" kodları, yoksa siparişteki _flxb_cert_ids). */
function flxc_donation_certs( array $d ) {
	if ( ! function_exists( 'flxs_get_certificate_by_code' ) ) {
		return array();
	}
	$out   = array();
	$codes = (array) ( FLXB_Donations::meta( $d )['certificates'] ?? array() );
	$codes = $codes ? $codes : array_filter( array( (string) ( $d['certificate'] ?? '' ) ) );
	foreach ( $codes as $code ) {
		$c = flxs_get_certificate_by_code( (string) $code );
		if ( $c ) {
			$out[ (int) $c['id'] ] = $c;
		}
	}
	$order = function_exists( 'flxc_pay_order' ) ? flxc_pay_order( (int) $d['id'] ) : null;
	if ( $order ) {
		foreach ( $order->get_items() as $item ) {
			if ( (int) $item->get_meta( '_flxb_donation_id' ) !== (int) $d['id'] ) {
				continue;
			}
			foreach ( array_filter( array_map( 'intval', (array) $item->get_meta( '_flxb_cert_ids' ) ) ) as $cid ) {
				if ( ! isset( $out[ $cid ] ) ) {
					$c = flxs_get_certificate( $cid );
					if ( $c ) {
						$out[ $cid ] = $c;
					}
				}
			}
		}
	}
	return array_values(
		array_filter(
			$out,
			function ( $c ) {
				return 'revoked' !== ( $c['status'] ?? '' );
			}
		)
	);
}

/**
 * 1.29.0 (Ahmet): personel ödenmiş bağışın sertifikasını kendine ve/veya bağışçıya yeniden gönderir; önce onay,
 * bağış başına en fazla staff_cert_max kez.
 */
function flxc_staff_send_certificate( array $id, array $a, array &$state, array &$ctx, $sandbox, $save ) {
	$did = (int) ( $a['donation'] ?? 0 );
	$to  = in_array( $a['to'] ?? '', array( 'me', 'donor', 'both' ), true ) ? $a['to'] : 'both';
	$max = (int) flxc_setting( 'staff_cert_max' );
	if ( $max < 1 ) {
		return array( 'ok' => false, 'error' => 'Sertifikayı yeniden gönderme kapalı; personele ekibin yardımcı olacağını söyle.' );
	}
	$targets = array(); // [ label, phone, name ]
	$certs   = array();
	if ( $sandbox ) {
		if ( 'pending' === flxc_staff_donation_status( $id, $did, $state, true ) ) {
			return array( 'ok' => false, 'error' => 'Bu bağış henüz ödenmedi; sertifika ödeme onaylanınca oluşur ve bağışçıya da personele de kendiliğinden gider.' );
		}
		$certs = array( array( 'id' => 0, 'number' => 'FluxWP-TEST-0001' ) );
		if ( 'donor' !== $to ) {
			$targets[] = array( 'me', '' !== (string) $id['phone'] ? $id['phone'] : '905000000000', $id['name'] );
		}
		if ( 'me' !== $to ) {
			$targets[] = array( 'donor', '905000000001', 'Test Bağışçı' );
		}
	} else {
		$d = flxc_staff_own_donation( $id, $did );
		if ( ! $d ) {
			return array( 'ok' => false, 'error' => 'Bu numarada referansı bu personel olan bir bağış yok. staff_my_donations ile bak.' );
		}
		if ( 'completed' !== $d['status'] ) {
			return array( 'ok' => false, 'error' => 'pending' === $d['status'] ? 'Bu bağış henüz ödenmedi; sertifika ödeme onaylanınca oluşur ve bağışçıya da personele de kendiliğinden gider.' : 'Bu bağış ödenmiş görünmüyor (' . $d['status'] . '); sertifika gönderilemez.' );
		}
		$certs = flxc_donation_certs( $d );
		if ( ! $certs ) {
			return array( 'ok' => false, 'error' => 'Bağış ödendi ama sertifika henüz oluşmamış. Ekibin kontrol edeceğini söyle; gerekirse handoff.' );
		}
		if ( 'donor' !== $to ) {
			$targets[] = array( 'me', (string) $id['phone'], $id['name'] );
		}
		if ( 'me' !== $to ) {
			$donor = ! empty( $d['donor_id'] ) && class_exists( 'FLXB_Donors' ) ? FLXB_Donors::get( (int) $d['donor_id'] ) : null;
			$dp    = $donor ? flxc_normalize_phone( (string) $donor['phone'] ) : '';
			if ( '' === $dp ) {
				return array( 'ok' => false, 'error' => 'Bağışçının kayıtlı WhatsApp numarası yok; yalnız personele (to=me) gönderilebilir.' );
			}
			$targets[] = array( 'donor', $dp, $donor ? (string) $donor['name'] : '' );
		}
	}
	$targets = array_values(
		array_filter(
			$targets,
			function ( $t ) {
				return strlen( (string) $t[1] ) >= 10;
			}
		)
	);
	if ( ! $targets ) {
		return array( 'ok' => false, 'error' => 'Gönderilecek numara bulunamadı (personelin profilinde WhatsApp numarası yok).' );
	}
	$counts = get_option( 'flxc_staff_cert_sends', array() );
	$counts = is_array( $counts ) ? $counts : array();
	$used   = (int) ( $counts[ $did ] ?? 0 );
	if ( $used >= $max ) {
		return array( 'ok' => false, 'error' => 'Bu bağışın sertifikası en fazla ' . $max . ' kez yeniden gönderilebilir; sınır doldu. Personele linki daha önce gönderildiğini söyle.' );
	}
	$who  = array();
	foreach ( $targets as $t ) {
		$who[] = ( 'me' === $t[0] ? 'personel' : 'bağışçı' ) . ' ' . ( '' !== $t[2] ? $t[2] . ' ' : '' ) . flxc_format_phone( $t[1] );
	}
	$sig = $did . '|' . $to;
	if ( empty( $a['confirmed'] ) || (string) ( $state['staff_cert_ask'] ?? '' ) !== $sig || ! empty( $ctx['staff_cert_asked'] ) ) {
		$state['staff_cert_ask'] = $sig;
		$save( $state );
		$ctx['staff_cert_asked'] = true;
		return array(
			'ok'           => true,
			'needs_confirm' => true,
			'certificates' => wp_list_pluck( $certs, 'number' ),
			'to'           => $who,
			'left'         => $max - $used,
			'note'         => 'Henüz gönderilmedi. Personele hangi sertifikaların kime (numarasıyla) gideceğini yazıp onay iste; onaylarsa bir sonraki mesajda confirmed=true ile çağır.',
		);
	}
	unset( $state['staff_cert_ask'] );
	$save( $state );
	$counts[ $did ] = $used + 1;
	update_option( 'flxc_staff_cert_sends', array_slice( $counts, -2000, null, true ), false );
	if ( $sandbox ) {
		return array( 'ok' => true, 'sent' => $who, 'left' => $max - $used - 1, 'note' => 'Gönderildi. Personele kısaca bildir.' );
	}
	$conn = flxc_default_connection();
	$sent = array();
	foreach ( $targets as $i => $t ) {
		$c = $conn ? flxc_ensure_chat( (string) $conn['id'], $t[1] . '@s.whatsapp.net', array( 'name' => $t[2] ) ) : null;
		if ( ! $c || flxc_chat_blocked( $c ) ) {
			continue;
		}
		$intro = 'donor' === $t[0] ? __( 'Sertifikanız 🌱', 'flx-chat' ) : '';
		if ( flxc_cert_deliver( $c, $t[1], $intro, $certs ) ) {
			$sent[] = $who[ $i ];
		}
	}
	/* translators: 1: staff, 2: certificate numbers */
	FLXB_Donations::add_audit( $did, 'certificate', array( 'note' => sprintf( __( 'Personel %1$s sertifikayı WhatsApp\'tan yeniden gönderdi: %2$s', 'flx-chat' ), $id['name'], implode( ', ', wp_list_pluck( $certs, 'number' ) ) ) ) );
	return $sent
		? array( 'ok' => true, 'sent' => $sent, 'left' => $max - $used - 1, 'note' => 'Gönderildi. Personele kısaca kime gittiğini yaz.' )
		: array( 'ok' => false, 'error' => 'Gönderilemedi (numara engelli ya da bağlantı yok). Ekibin kontrol edeceğini söyle.' );
}

/** Referansı bu personel olan bağış (Flux Donate). */
function flxc_staff_own_donation( array $id, $did ) {
	$d = class_exists( 'FLXB_Donations' ) ? FLXB_Donations::get( (int) $did ) : null;
	if ( ! $d || ( (int) ( $d['ref_user_id'] ?? 0 ) !== (int) $id['user_id'] && ! flxc_staff_can( $id, 'flx_reports_view' ) ) ) {
		return null;
	}
	return $d;
}

function flxc_staff_payment_link( array $id, array $a, $chat, $sandbox ) {
	if ( $sandbox ) {
		return array( 'ok' => true, 'test' => true, 'note' => 'Test: link üretilmedi.' );
	}
	$d = flxc_staff_own_donation( $id, (int) ( $a['donation'] ?? 0 ) );
	if ( ! $d ) {
		return array( 'ok' => false, 'error' => 'Bu numarada referansı bu personel olan bir bağış yok.' );
	}
	if ( 'pending' !== $d['status'] ) {
		return array( 'ok' => false, 'error' => 'Bu bağış ödeme beklemiyor (' . flxc_staff_status_label( $d['status'] ) . ').' );
	}
	$order = flxc_pay_order( (int) $d['id'] );
	$pay   = $order ? flxc_pay_link( $order, flxc_pay_default_method( (string) ( $a['method'] ?? '' ) ) ) : null;
	if ( ! $pay || is_wp_error( $pay ) ) {
		return array( 'ok' => false, 'error' => 'Ödeme sayfası üretilemedi (WooCommerce kapalı olabilir).' );
	}
	$sent = false;
	if ( ! empty( $a['send_to_donor'] ) ) {
		$donor = class_exists( 'FLXB_Donors' ) && ! empty( $d['donor_id'] ) ? FLXB_Donors::get( (int) $d['donor_id'] ) : null;
		$phone = $donor ? flxc_normalize_phone( (string) $donor['phone'] ) : '';
		$dc    = '' !== $phone ? flxc_staff_donor_chat( $chat, $phone, (string) $donor['name'] ) : null;
		if ( $dc && ! ( flxc_chat_blocked( $dc ) ) ) {
			foreach ( flxc_bot_payment_messages( array(), (string) $donor['name'], $pay, true ) as $i => $t ) {
				flxc_send( $dc, array( 'type' => 'text', 'body' => $t, 'source' => 'bot', 'user_id' => 0, 'preview' => false, 'delay' => $i ? 900 : 0 ) );
			}
			$sent = true;
		}
	}
	return array( 'ok' => true, 'donation' => (int) $d['id'], 'pay_url' => $pay['url'], 'sent_to_donor' => $sent );
}

function flxc_staff_my_donations( array $id, array $a, array $state, $sandbox ) {
	if ( $sandbox && ! empty( $state['tdons'] ) ) {
		return array( 'ok' => true, 'donations' => flxc_bot_sandbox_records( $state )['donations'] );
	}
	if ( ! class_exists( 'FLXB_Donations' ) ) {
		return array( 'ok' => false, 'error' => 'Bağış sistemi kapalı.' );
	}
	list( $from, $to ) = flxc_staff_period( (string) ( $a['period'] ?? 'month' ) );
	$q = array(
		'ref_user_id' => (int) $id['user_id'],
		'from'        => $from,
		'to'          => $to,
		'per_page'    => 20,
		'group'       => 1,
	);
	$st = (string) ( $a['status'] ?? 'all' );
	if ( 'all' !== $st ) {
		$q['status'] = $st;
	}
	if ( ! empty( $a['search'] ) ) {
		$q['search'] = sanitize_text_field( (string) $a['search'] );
	}
	$r    = FLXB_Donations::query( $q );
	$list = array();
	foreach ( (array) $r['items'] as $d ) {
		$list[] = array(
			'no'     => (int) $d['id'],
			'donor'  => (string) ( $d['donor_name'] ?? '' ),
			'phone'  => flxc_format_phone( flxc_normalize_phone( (string) ( $d['donor_phone'] ?? '' ) ) ),
			'what'   => flxc_staff_donation_title( $d ),
			'amount' => flxc_money( (float) ( $d['group_amount'] ?? $d['amount'] ), (string) $d['currency'] ),
			'status' => flxc_staff_status_label( (string) $d['status'] ),
			'date'   => function_exists( 'flxb_date' ) ? flxb_date( $d['created_at'], 'j M Y H:i' ) : (string) $d['created_at'],
		);
	}
	return array(
		'ok'        => true,
		'period'    => '' !== $from ? $from . ' – ' . $to : 'tümü',
		'count'     => (int) $r['total'],
		'paid_sum'  => flxc_money( (float) $r['sum'], function_exists( 'flxb_default_currency' ) ? flxb_default_currency() : 'TRY' ),
		'donations' => $list,
		'note'      => (int) $r['total'] > count( $list ) ? 'En yeni 20 kayıt gösterildi.' : '',
	);
}

/** Genel rapor verisi: toplamlar + personel bazında (zamanlanmış raporlar da kullanır). */
function flxc_staff_report_data( $from, $to, $ref_user = 0 ) {
	global $wpdb;
	if ( ! function_exists( 'flxb_table' ) ) {
		return null;
	}
	$t     = flxb_table( 'donations' );
	$where = array( '1=1' );
	$prm   = array();
	if ( '' !== $from && function_exists( 'flxb_local_day_to_utc' ) ) {
		$where[] = 'created_at >= %s';
		$prm[]   = flxb_local_day_to_utc( $from );
		$where[] = 'created_at <= %s';
		$prm[]   = flxb_local_day_to_utc( $to, true );
	}
	if ( $ref_user ) {
		$where[] = 'ref_user_id = %d';
		$prm[]   = (int) $ref_user;
	}
	$w    = implode( ' AND ', $where );
	$sql  = "SELECT status, ref_user_id, COUNT(*) n, COALESCE(SUM(amount),0) s, COALESCE(SUM(quantity),0) q FROM {$t} WHERE {$w} GROUP BY status, ref_user_id";
	$rows = $prm ? $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$tot  = array( 'paid' => 0.0, 'paid_n' => 0, 'paid_q' => 0, 'pending' => 0.0, 'pending_n' => 0, 'canceled_n' => 0 );
	$by   = array();
	foreach ( (array) $rows as $r ) {
		$u = (int) $r['ref_user_id'];
		$by[ $u ] = $by[ $u ] ?? array( 'paid' => 0.0, 'paid_n' => 0, 'pending_n' => 0, 'canceled_n' => 0 );
		if ( in_array( $r['status'], array( 'completed', 'partially_refunded' ), true ) ) {
			$tot['paid']   += (float) $r['s'];
			$tot['paid_n'] += (int) $r['n'];
			$tot['paid_q'] += (int) $r['q'];
			$by[ $u ]['paid']   += (float) $r['s'];
			$by[ $u ]['paid_n'] += (int) $r['n'];
		} elseif ( 'pending' === $r['status'] ) {
			$tot['pending']   += (float) $r['s'];
			$tot['pending_n'] += (int) $r['n'];
			$by[ $u ]['pending_n'] += (int) $r['n'];
		} elseif ( 'canceled' === $r['status'] ) {
			$tot['canceled_n'] += (int) $r['n'];
			$by[ $u ]['canceled_n'] += (int) $r['n'];
		}
	}
	uasort(
		$by,
		function ( $x, $y ) {
			return $y['paid'] <=> $x['paid'];
		}
	);
	return array( 'total' => $tot, 'by' => $by );
}

function flxc_staff_report( $period ) {
	list( $from, $to ) = flxc_staff_period( $period );
	$data = flxc_staff_report_data( $from, $to );
	if ( ! $data ) {
		return array( 'ok' => false, 'error' => 'Bağış sistemi kapalı.' );
	}
	$cur   = function_exists( 'flxb_default_currency' ) ? flxb_default_currency() : 'TRY';
	$staff = array();
	foreach ( array_slice( $data['by'], 0, 15, true ) as $uid => $x ) {
		$u       = $uid ? get_userdata( $uid ) : null;
		$staff[] = array(
			'staff'    => $u ? $u->display_name : 'Referanssız (site / doğrudan)',
			'paid'     => flxc_money( $x['paid'], $cur ),
			'paid_n'   => $x['paid_n'],
			'pending'  => $x['pending_n'],
			'canceled' => $x['canceled_n'],
		);
	}
	$t = $data['total'];
	return array(
		'ok'        => true,
		'period'    => $from . ' – ' . $to,
		'paid'      => flxc_money( $t['paid'], $cur ),
		'paid_n'    => $t['paid_n'],
		'units'     => $t['paid_q'],
		'pending'   => flxc_money( $t['pending'], $cur ) . ' (' . $t['pending_n'] . ' kayıt)',
		'canceled'  => $t['canceled_n'],
		'by_staff'  => $staff,
	);
}

/* ---------- Referansa bildirim (flxb_ref_notify) ---------- */

add_action(
	'flxb_donation_updated',
	function ( $id, $upd ) {
		if ( isset( $upd['status'] ) ) {
			flxc_staff_ref_notify_queue( (int) $id, (string) $upd['status'] );
		}
	},
	20,
	2
);
add_action(
	'flxb_donation_recorded',
	function ( $id, $d ) {
		if ( 'completed' === ( $d['status'] ?? '' ) ) {
			flxc_staff_ref_notify_queue( (int) $id, 'completed' );
		}
	},
	20,
	2
);

/** Aynı istekte birden çok satır (aynı sipariş) tek bildirime iner; gönderim istek sonunda. */
function flxc_staff_ref_notify_queue( $id, $status ) {
	if ( ! in_array( $status, array( 'completed', 'canceled' ), true ) || ! (int) flxc_setting( 'ref_notify' ) || ! function_exists( 'flxr_user_phone' ) ) {
		return;
	}
	static $q = null;
	if ( null === $q ) {
		$q = array();
		add_action(
			'shutdown',
			function () use ( &$q ) {
				foreach ( $q as $x ) {
					flxc_staff_ref_notify( $x[0], $x[1] );
				}
			}
		);
	}
	$q[ $id . '|' . $status ] = array( $id, $status );
}

function flxc_staff_ref_notify( $id, $status ) {
	$d = class_exists( 'FLXB_Donations' ) ? FLXB_Donations::get( (int) $id ) : null;
	if ( ! $d || ! (int) ( $d['ref_user_id'] ?? 0 ) || $d['status'] !== $status ) {
		return;
	}
	$uid = (int) $d['ref_user_id'];
	$u   = get_userdata( $uid );
	if ( ! $u || ! ( user_can( $u, 'flxb_ref_notify' ) || user_can( $u, 'manage_options' ) ) ) {
		return;
	}
	$phone = (string) flxr_user_phone( $uid );
	if ( '' === $phone ) {
		return;
	}
	// Bir sipariş/bağış ve durum için tek bildirim.
	$key  = ( (int) $d['wc_order_id'] ? 'o' . (int) $d['wc_order_id'] : 'd' . (int) $d['id'] ) . '|' . $status;
	$sent = get_option( 'flxc_ref_notified', array() );
	$sent = is_array( $sent ) ? $sent : array();
	if ( isset( $sent[ $key ] ) ) {
		return;
	}
	$sent[ $key ] = time();
	update_option( 'flxc_ref_notified', array_slice( $sent, -1000, null, true ), false );

	$amount = (float) $d['amount'];
	if ( (int) $d['wc_order_id'] && function_exists( 'wc_get_order' ) ) {
		$o      = wc_get_order( (int) $d['wc_order_id'] );
		$amount = $o ? (float) $o->get_total() : $amount;
	}
	$donor = class_exists( 'FLXB_Donors' ) && ! empty( $d['donor_id'] ) ? FLXB_Donors::get( (int) $d['donor_id'] ) : null;
	$name  = $donor && '' !== (string) $donor['name'] ? $donor['name'] : __( 'Bağışçı', 'flx-chat' );
	$what  = flxc_staff_donation_title( $d ) . ' (' . flxc_money( $amount, (string) $d['currency'] ) . ')';
	$text  = 'completed' === $status
		/* translators: 1: donor, 2: what, 3: id */
		? sprintf( __( '✅ Bağışçınız ödedi: *%1$s* · %2$s #%3$d', 'flx-chat' ), $name, $what, (int) $d['id'] )
		/* translators: 1: donor, 2: what, 3: id */
		: sprintf( __( '❌ Bağış iptal edildi: *%1$s* · %2$s #%3$d', 'flx-chat' ), $name, $what, (int) $d['id'] );
	$c    = flxc_default_connection();
	$chat = $c ? flxc_ensure_chat( (string) $c['id'], $phone . '@s.whatsapp.net', array( 'name' => $u->display_name ) ) : null;
	if ( ! $chat || ( flxc_chat_blocked( $chat ) ) ) {
		return;
	}
	flxc_send( $chat, array( 'type' => 'text', 'body' => $text, 'source' => 'bot', 'user_id' => 0, 'preview' => false ) );
	do_action( 'flxc_ref_notified', $d, $status, $uid );
}

/* Personel numarasıyla konuşmalar (personel araçları, raporlar, bildirimler) ekip grubuna yansıtılmaz. */
add_filter(
	'flxc_mirror_chat',
	function ( $ok, $chat ) {
		if ( $ok && flxc_staff_on() && '' !== (string) ( $chat['phone'] ?? '' ) && flxr_user_by_phone( (string) $chat['phone'] ) ) {
			return false;
		}
		return $ok;
	},
	10,
	2
);
