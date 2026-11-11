<?php
/**
 * Asistanın güncel bilgi tool'ları (1.10.0): bağış türleri / projeler ve fiyatlar, ödeme (havale) bilgisi,
 * şu anki saat ve mesai. Talimata gömülmez; asistan gerektiğinde çağırır, değer her seferinde
 * ayardan ve Flux Donate'tan o an okunur.
 *
 * Kaynaklar:
 * - Bağış türleri: Flux Donate › Bağış türleri (açık + fiyatlı; Çağrı Merkezi ayarında seçilenlerle sınırlı).
 * - Alıcı adı / IBAN: WooCommerce › Ödemeler › Havale (bağış); Çağrı Merkezi › Ayarlar › Ödeme doluysa o ezer.
 * - Bağış sayfası: ayardaki adres, yoksa /bagis/.
 *
 * Başka eklenti bilgi ekleyebilir: flxc_bot_info_tools (tanım listesi) + flxc_bot_info_tool (cevap).
 *
 * @package FLX_Cagri_Merkezi
 */

defined( 'ABSPATH' ) || exit;

/** Bilgi tool'larının adları. */
function flxc_bot_info_names() {
	return array( 'donation_options', 'payment_info', 'current_time' );
}

/**
 * Tool tanımları.
 *
 * @param callable $f flxc_bot_tools içindeki tanım yardımcısı.
 */
function flxc_bot_info_tools( $f ) {
	$list = array(
		$f(
			'donation_options',
			'Güncel bağış türlerini (projeleri), seçenekleri/paketleri, birim fiyatları, adet ve tutar kurallarını ve her türün bağış linkini getirir. Fiyat, paket, adet, proje ya da "ne bağışlayabilirim" sorulduğunda ve set_order\'dan önce çağır; fiyatı ve türleri ezberden yazma. Birden fazla tür/proje dönerse kişiye hangisine katılmak istediğini sor.',
			array( 'type_id' => array( 'type' => 'integer', 'description' => 'Yalnız bu tür/proje; boşsa hepsi' ) )
		),
		$f(
			'payment_info',
			'Güncel havale/EFT bilgisini (alıcı adı, IBAN, açıklamaya ne yazılacağı), açık ödeme yollarını ve bağış sayfası linkini getirir. IBAN ya da ödeme yolu sorulduğunda çağır; IBAN\'ı ezberden yazma.'
		),
		$f(
			'current_time',
			'Şu anki tarih, gün, saat ve ekibin mesai saatlerini (şu an mesai içi mi) getirir. Tarih/saat gerektiren her cevaptan (yarın, hafta sonu, ekip ne zaman döner, hatırlatma zamanı) önce çağır.'
		),
	);
	return (array) apply_filters( 'flxc_bot_info_tools', $list, $f );
}

/** Tool cevabı. Bilinmeyen ad için null (başka eklenti filtreyle cevaplayabilir). */
function flxc_bot_info_tool( $name, array $a ) {
	switch ( $name ) {
		case 'donation_options':
			return flxc_bot_info_options( (int) ( $a['type_id'] ?? 0 ) );
		case 'payment_info':
			return flxc_bot_info_payment();
		case 'current_time':
			return flxc_bot_info_time();
	}
	return apply_filters( 'flxc_bot_info_tool', null, $name, $a );
}

function flxc_bot_info_options( $type_id = 0 ) {
	$out = array();
	foreach ( flxc_ai_types() as $t ) {
		if ( $type_id && $type_id !== (int) $t['id'] ) {
			continue;
		}
		$opts = array();
		foreach ( $t['options'] as $o ) {
			$opts[] = array(
				'option' => $o['key'],
				'label'  => $o['label'],
				'price'  => flxc_money( $o['price'], $t['currency'] ),
				'per'    => $o['unit'],
				'link'   => flxc_donate_url( null, $o['key'], 1 ),
			);
		}
		$row = array(
			'type_id' => $t['id'],
			'title'   => $t['title'],
			'about'   => $t['desc'],
			'options' => $opts,
		);
		if ( $t['qty'] ) {
			$row['quantity'] = $t['maxQty'] > 0 ? '1-' . $t['maxQty'] : 'serbest';
		}
		if ( $t['free'] ) {
			$row['free_amount'] = true;
			if ( $t['min'] > 0 ) {
				$row['min_amount'] = flxc_money( $t['min'], $t['currency'] );
			}
		}
		if ( $t['proxy'] ) {
			$row['on_behalf'] = 'Başkası adına (vekâleten) yapılır; kimin adına olduğunu sor.';
		}
		$out[] = $row;
	}
	if ( ! $out ) {
		return array( 'ok' => false, 'error' => $type_id ? 'Bu tür/proje yok ya da kapalı. type_id vermeden tekrar çağır.' : 'Şu an sunulacak bağış seçeneği yok. handoff çağır.' );
	}
	$res = array( 'ok' => true, 'types' => $out );
	if ( count( $out ) > 1 && ! $type_id ) {
		$res['note'] = 'Birden fazla tür/proje var: kişi belirtmediyse hangisine katılmak istediğini sor.';
	}
	return $res;
}

function flxc_bot_info_payment() {
	$acc  = flxc_bot_bank_account();
	$card = function_exists( 'flxc_pay_card_gateway' ) && '' !== flxc_pay_card_gateway();
	$res  = array(
		'ok'      => true,
		'methods' => $card ? array( 'card', 'havale' ) : array( 'havale' ),
	);
	if ( '' !== $acc['holder'] || '' !== $acc['iban'] ) {
		$tpl          = trim( (string) flxc_setting( 'bank_desc' ) );
		$res['havale'] = array(
			'holder'      => $acc['holder'],
			'iban'        => $acc['iban'],
			'bank'        => $acc['bank'],
			'description' => '' !== $tpl ? $tpl : '{type} {name}',
		);
	} else {
		$bank = flxc_bank_text();
		if ( '' !== $bank ) {
			$res['havale'] = array( 'text' => $bank );
		}
	}
	$url = flxc_donate_url();
	if ( '' !== $url ) {
		$res['donate_page'] = $url;
	}
	$res['note'] = 'Bağış kaydı açılınca (create_donation) kişiye özel ödeme linki ve havale açıklama kodu sistemce ayrıca gönderilir; açıklamadaki {type} {name} yer tutucularını kişiye yazarken doldur.';
	return $res;
}

function flxc_bot_info_time() {
	$now  = time();
	$days = array_map( 'intval', (array) flxc_setting( 'away_days' ) );
	$on   = array();
	foreach ( $days as $n ) {
		// Haftanın günü site dilinde (1 = Pazartesi); 5 Ocak 2026 Pazartesi.
		$on[] = flx_date( gmmktime( 12, 0, 0, 1, 4 + max( 1, min( 7, $n ) ), 2026 ), 'l' );
	}
	return array(
		'ok'         => true,
		'now'        => wp_date( 'Y-m-d H:i', $now ),
		'weekday'    => flx_date( $now, 'l' ),
		'timezone'   => wp_timezone_string(),
		'open_now'   => flxc_is_open_hours( $now ),
		'team_hours' => implode( ', ', $on ) . ' ' . flxc_setting( 'away_start' ) . '-' . flxc_setting( 'away_end' ),
	);
}

/**
 * Talimatın en başındaki sistem tanımı: asistan hangi bilgiyi hangi araçtan alacağını baştan bilsin,
 * gerektiğinde düşünmeden çağırsın. Yalnız o an açık araçlar listelenir.
 */
function flxc_bot_info_rule( $tools = null ) {
	$have = array();
	foreach ( null === $tools ? flxc_bot_tools() : $tools as $t ) {
		$have[ $t['function']['name'] ] = true;
	}
	$map = array(
		'donation_options'   => 'Bağış türleri / projeler, paketler, fiyatlar, adet kuralları',
		'payment_info'       => 'Havale/EFT alıcı adı ve IBAN, ödeme yolları, bağış sayfası linki',
		'current_time'       => 'Bugünün tarihi, gün, saat, ekip mesaide mi',
		'send_price_list'    => 'Fiyat listesini hazır mesaj olarak göndermek',
		'set_order'          => 'Kişinin seçtiği bağışı kaydetmek (önce donation_options)',
		'create_donation'    => 'Kişi onaylayınca bağış kaydı ve ödeme linki',
		'payment_link'       => 'Ödeme linkini yeniden göndermek / yöntemi değiştirmek',
		'cancel_donation'    => 'Ödenmemiş bağışı iptal etmek (onay sorusunu sistem gönderir)',
		'my_records'         => 'Kişinin kendi bağış, fidan ve sertifika kayıtları',
		'certificate_resend' => 'Sertifikayı yeniden göndermek',
		'receipt_check'      => 'Dekont daha önce yüklenmiş mi (dekont no ile)',
		'verify_send'        => 'Kayıt sorgusu için kod göndermek (verify_check ile doğrula)',
		'search_site'        => 'Sitedeki yayında içerik (haber, proje, sayfa) ve etkinliklerin tarihi, saati, yeri',
		'read_doc'           => 'Başvuru belgeleri',
		'reminder_set'       => 'Hatırlatıcıyı hemen kurmak / güncellemek, onay sormadan (reminder_list / reminder_cancel)',
		'close_chat'         => 'Vedalaşan kişide sohbeti tamamlandı işaretlemek (tekrar veda yazmamak)',
		'react'              => 'Kişinin mesajına emoji tepkisi bırakmak',
		'remember'           => 'Kişi hakkında kalıcı bilgi saklamak (forget ile silmek)',
		'save_details'       => 'Hitap, ad soyad, e-posta kaydetmek',
		'handoff'            => 'Ekipten bir temsilciye devretmek (son çare)',
		'opt_out'            => 'Kişi hiç mesaj istemiyorsa kara liste',
	);
	$lines = array();
	foreach ( $map as $name => $what ) {
		if ( isset( $have[ $name ] ) ) {
			$lines[] = '- ' . $what . ' → ' . $name;
		}
	}
	return "## Sistem\n"
		. "Güncel veriler (fiyat, tür/proje, IBAN, tarih) bu metinde YOK; gerektiği an ilgili aracı çağırıp oradan alırsın. Fiyat, IBAN, tür/proje ya da tarih uydurma, eski mesajlardan tekrar kullanma; her gerektiğinde aracı yeniden çağır. Araç çağırmadan önce kişiye \"bakıyorum\" gibi ara mesaj yazma.\n"
		. "Hangi bilgi hangi araçta:\n" . implode( "\n", $lines ) . "\n"
		. '- Birden fazla tür/proje varsa ve kişi belirtmediyse hangisine katılmak istediğini sor.';
}
