<?php
/**
 * Yapay zekâ araçları: Flux SEO & MCP (flxseo_mcp_tools) üzerinden kaydolur; Flux AI sohbeti bunları
 * kendiliğinden alır. Flux SEO & MCP yoksa doğrudan Flux AI'ye (flxai_tools). flxan_ref_save dışında hepsi salt okunur.
 * Tüm okuma araçları seg (rol süzgeci) ve rc (referans kodu süzgeci) alır.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

/** Aralık şeması (tüm araçlarda aynı). */
function flxan_tool_range_props() {
	return array(
		'range' => array(
			'type'        => 'string',
			'enum'        => array_keys( FLXAN_Stats::presets() ),
			'description' => 'Hazır aralık (varsayılan 30d). custom ise start/end verin.',
		),
		'start' => array( 'type' => 'string', 'description' => 'YYYY-MM-DD (range=custom).' ),
		'end'   => array( 'type' => 'string', 'description' => 'YYYY-MM-DD (range=custom).' ),
		'seg'   => array( 'type' => 'string', 'description' => 'Rol süzgeci: all (varsayılan) | guest (dışarıdan, giriş yapmamış) | members (giriş yapmış herkes) | rol adı (ör. administrator, editor, customer).' ),
		'rc'    => array( 'type' => 'string', 'description' => 'Referans kodu süzgeci (?r=kod): boş = hepsi, * = yalnız referansla gelenler, ya da kod.' ),
	);
}

function flxan_tool_range( $a ) {
	FLXAN_Stats::set_filter( (string) ( $a['seg'] ?? 'all' ), (string) ( $a['rc'] ?? '' ) );
	$p = (string) ( $a['range'] ?? '' );
	if ( '' === $p && ( ! empty( $a['start'] ) || ! empty( $a['end'] ) ) ) {
		$p = 'custom';
	}
	return FLXAN_Stats::range( '' !== $p ? $p : '30d', (string) ( $a['start'] ?? '' ), (string) ( $a['end'] ?? '' ) );
}

function flxan_tool_limit( $a, $def = 20, $max = 100 ) {
	return max( 1, min( $max, (int) ( $a['limit'] ?? $def ) ) );
}

/** Araç tanımları (flxseo_mcp_tools biçiminde). */
function flxan_tools() {
	$cap   = 'flxan_view';
	$range = flxan_tool_range_props();
	$tools = array();

	$tools['flxan_summary'] = array(
		'title'       => __( 'Site trafiği özeti', 'flx-analytics' ),
		'description' => 'Site Analitik (Flux Analiz): seçilen aralıkta ziyaretçi ve görüntülenme toplamı, önceki eşit dönemle yüzde değişim, en çok görüntülenen 10 sayfa, ilk 10 yönlendiren site, kanallar (doğrudan/arama/sosyal/yapay zekâ/e-posta/reklam/kampanya/diğer siteler), cihazlar, olay toplamları (dış bağlantı, indirme, telefon, WhatsApp, arama, bağış) oturum ölçüleri (ziyaret, ortalama süre, hemen çıkma, ziyaret başına sayfa), rollere göre dağılım, referans kodları, bağış hunisi (kanala göre) ve son 30 dakika. "Site trafiği nasıl / nereden geliyor" sorularına buradan başla. Not: ziyaretçi = günlük tekil ziyaretçilerin toplamı; çerez yok.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array( 'type' => 'object', 'properties' => $range ),
		'run'         => function ( $a ) {
			FLXAN_Aggregator::maybe_run( 60 );
			$r   = flxan_tool_range( $a );
			$rep = FLXAN_Stats::report( $r );
			unset( $rep['series'] );
			$rep['dims'] = array(
				'channel' => $rep['dims']['channel'],
				'device'  => $rep['dims']['device'],
				'country' => array( 'total' => $rep['dims']['country']['total'], 'items' => array_slice( $rep['dims']['country']['items'], 0, 10 ) ),
			);
			$rep['note'] = 'Ayrıntı için flxan_timeseries, flxan_pages, flxan_referrers, flxan_breakdown, flxan_events, flxan_page.';
			return $rep;
		},
	);

	$tools['flxan_timeseries'] = array(
		'title'       => __( 'Günlük ziyaretçi grafiği verisi', 'flx-analytics' ),
		'description' => 'Ziyaretçi ve görüntülenme zaman serisi (eksik günler 0). group: day | week | month | auto (≤92 gün günlük, ≤400 haftalık, üstü aylık).',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array_merge( $range, array( 'group' => array( 'type' => 'string', 'enum' => array( 'auto', 'day', 'week', 'month' ) ) ) ),
		),
		'run'         => function ( $a ) {
			$r = flxan_tool_range( $a );
			return array( 'range' => $r ) + FLXAN_Stats::series( $r['start'], $r['end'], (string) ( $a['group'] ?? 'auto' ) );
		},
	);

	$tools['flxan_pages'] = array(
		'title'       => __( 'En çok görüntülenen sayfalar', 'flx-analytics' ),
		'description' => 'Sayfa/yazı başına görüntülenme ve ziyaretçi (çoktan aza). q: adreste geçen metin (ör. "bagis"). only404: yalnız bulunamayan (404) adresler. Sayfalama: limit (≤100), offset.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array_merge(
				$range,
				array(
					'q'       => array( 'type' => 'string' ),
					'only404' => array( 'type' => 'boolean' ),
					'limit'   => array( 'type' => 'integer' ),
					'offset'  => array( 'type' => 'integer' ),
				)
			),
		),
		'run'         => function ( $a ) {
			$r = flxan_tool_range( $a );
			return array( 'range' => $r ) + FLXAN_Stats::pages( $r['start'], $r['end'], flxan_tool_limit( $a ), (int) ( $a['offset'] ?? 0 ), (string) ( $a['q'] ?? '' ), ! empty( $a['only404'] ) );
		},
	);

	$tools['flxan_referrers'] = array(
		'title'       => __( 'Yönlendiren siteler', 'flx-analytics' ),
		'description' => 'Ziyaretçiyi gönderen siteler (alan adı; google.com, instagram.com, chatgpt.com gibi bilinen servisler tek ada toplanır) ve kanalı. q: alan adında geçen metin.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array_merge(
				$range,
				array(
					'q'      => array( 'type' => 'string' ),
					'limit'  => array( 'type' => 'integer' ),
					'offset' => array( 'type' => 'integer' ),
				)
			),
		),
		'run'         => function ( $a ) {
			$r = flxan_tool_range( $a );
			return array( 'range' => $r ) + FLXAN_Stats::referrers( $r['start'], $r['end'], flxan_tool_limit( $a ), (int) ( $a['offset'] ?? 0 ), (string) ( $a['q'] ?? '' ) );
		},
	);

	$tools['flxan_breakdown'] = array(
		'title'       => __( 'Kanal, cihaz, tarayıcı dağılımı', 'flx-analytics' ),
		'description' => 'Bir boyuta göre dağılım. kind: channel (giriş kanalı; visitors = o kanaldan gelen tekil ziyaretçi), device, browser, os, lang (tarayıcı dili), country (yalnız sunucu ülke başlığı gönderiyorsa), utm_source, utm_medium, utm_campaign.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'required'   => array( 'kind' ),
			'properties' => array_merge(
				$range,
				array(
					'kind'  => array( 'type' => 'string', 'enum' => array_keys( FLXAN_Stats::kinds() ) ),
					'limit' => array( 'type' => 'integer' ),
				)
			),
		),
		'run'         => function ( $a ) {
			$r    = flxan_tool_range( $a );
			$kind = (string) ( $a['kind'] ?? '' );
			if ( ! array_key_exists( $kind, FLXAN_Stats::kinds() ) ) {
				throw new Exception( 'kind: ' . implode( ', ', array_keys( FLXAN_Stats::kinds() ) ) );
			}
			return array( 'range' => $r, 'kind' => $kind ) + FLXAN_Stats::dim( $kind, $r['start'], $r['end'], flxan_tool_limit( $a, 20 ) );
		},
	);

	$tools['flxan_events'] = array(
		'title'       => __( 'Tıklamalar ve dönüşümler', 'flx-analytics' ),
		'description' => 'Olaylar: outbound (dış bağlantı; param = alan adı), download (param = dosya adı), tel / mail (param = numara/adres), whatsapp (param = tıklandığı sayfa), search (param = aranan kelime), donation (ödenen bağış/sipariş; param = bağış türü, amount = toplam tutar). name boşsa tüm olaylar; summary olay türü toplamlarıdır. hits = tıklama, uniques = günlük tekil kişi.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array_merge(
				$range,
				array(
					'name'   => array( 'type' => 'string' ),
					'limit'  => array( 'type' => 'integer' ),
					'offset' => array( 'type' => 'integer' ),
				)
			),
		),
		'run'         => function ( $a ) {
			$r = flxan_tool_range( $a );
			return array(
				'range'   => $r,
				'summary' => FLXAN_Stats::event_names( $r['start'], $r['end'] ),
			) + FLXAN_Stats::events( $r['start'], $r['end'], (string) ( $a['name'] ?? '' ), flxan_tool_limit( $a ), (int) ( $a['offset'] ?? 0 ) );
		},
	);

	$tools['flxan_realtime'] = array(
		'title'       => __( 'Şu an sitede kaç kişi var?', 'flx-analytics' ),
		'description' => 'Son X dakika (1-60, varsayılan 30): görüntülenme, tekil ziyaretçi, dakikalık dağılım, en çok bakılan sayfalar ve kaynaklar. Önce kuyruğu işler.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array( 'type' => 'object', 'properties' => array( 'minutes' => array( 'type' => 'integer' ), 'seg' => $range['seg'], 'rc' => $range['rc'] ) ),
		'run'         => function ( $a ) {
			FLXAN_Aggregator::maybe_run( 20 );
			FLXAN_Stats::set_filter( (string) ( $a['seg'] ?? 'all' ), (string) ( $a['rc'] ?? '' ) );
			return FLXAN_Stats::realtime( (int) ( $a['minutes'] ?? 30 ) );
		},
	);

	$tools['flxan_page'] = array(
		'title'       => __( 'Tek sayfanın istatistiği', 'flx-analytics' ),
		'description' => 'Bir sayfa ya da yazının toplamı ve günlük serisi. target: yazı kimliği (sayı), yol ("/bagis/") ya da tam adres.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'required'   => array( 'target' ),
			'properties' => array_merge( array( 'target' => array( 'type' => 'string' ) ), $range ),
		),
		'run'         => function ( $a ) {
			$r = flxan_tool_range( $a );
			return array( 'range' => $r ) + FLXAN_Stats::page_stats( (string) ( $a['target'] ?? '' ), $r['start'], $r['end'] );
		},
	);

	$tools['flxan_compare'] = array(
		'title'       => __( 'İki dönemi karşılaştır', 'flx-analytics' ),
		'description' => 'İki tarih aralığının ziyaretçi/görüntülenme toplamları ve yüzde değişimi, iki dönemin en çok görüntülenen 10 sayfası ve kanalları. a_start, a_end, b_start, b_end: YYYY-MM-DD (a = yeni dönem, b = karşılaştırılan).',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'required'   => array( 'a_start', 'a_end', 'b_start', 'b_end' ),
			'properties' => array(
				'a_start' => array( 'type' => 'string' ),
				'a_end'   => array( 'type' => 'string' ),
				'b_start' => array( 'type' => 'string' ),
				'b_end'   => array( 'type' => 'string' ),
			),
		),
		'run'         => function ( $a ) {
			$ra  = FLXAN_Stats::range( 'custom', (string) ( $a['a_start'] ?? '' ), (string) ( $a['a_end'] ?? '' ) );
			$rb  = FLXAN_Stats::range( 'custom', (string) ( $a['b_start'] ?? '' ), (string) ( $a['b_end'] ?? '' ) );
			$one = function ( $r ) {
				return array(
					'start'    => $r['start'],
					'end'      => $r['end'],
					'totals'   => FLXAN_Stats::totals( $r['start'], $r['end'] ),
					'pages'    => FLXAN_Stats::pages( $r['start'], $r['end'], 10 )['items'],
					'channels' => FLXAN_Stats::dim( 'channel', $r['start'], $r['end'], 10 )['items'],
				);
			};
			$x   = $one( $ra );
			$y   = $one( $rb );
			return array(
				'a'      => $x,
				'b'      => $y,
				'change' => array(
					'visitors'  => FLXAN_Stats::change( $x['totals']['visitors'], $y['totals']['visitors'] ),
					'pageviews' => FLXAN_Stats::change( $x['totals']['pageviews'], $y['totals']['pageviews'] ),
				),
			);
		},
	);

	$tools['flxan_funnel'] = array(
		'title'       => __( 'Bağış / ödeme hunisi', 'flx-analytics' ),
		'description' => 'Huni: her adıma (siteye giriş → bağış sayfası → ödeme sayfası → forma başladı → "Ödeme yap" → tamamlandı; ayarlardan değişir) ulaşan ziyaret sayısı, başlangıca oranı (rate) ve önceki adımdan kayıp (drop) yüzdesi. by: channel | ref (yönlendiren site) | rc (referans kodu) | seg (rol) | country → "nereden gelenler hangi adıma kadar gitti" kırılımı (en kalabalık 8 grup, counts dizisi adım sırasında). id: huni no (boş = ilk huni; funnels listesi döner).',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array_merge(
				$range,
				array(
					'id' => array( 'type' => 'integer' ),
					'by' => array( 'type' => 'string', 'enum' => array( '', 'channel', 'ref', 'rc', 'seg', 'country' ) ),
				)
			),
		),
		'run'         => function ( $a ) {
			FLXAN_Aggregator::maybe_run( 60 );
			$r = flxan_tool_range( $a );
			return array( 'range' => $r ) + FLXAN_Stats::funnel( (int) ( $a['id'] ?? 0 ), $r['start'], $r['end'], (string) ( $a['by'] ?? '' ) );
		},
	);

	$tools['flxan_journeys'] = array(
		'title'       => __( 'Ziyaretçi yolculukları', 'flx-analytics' ),
		'description' => 'Yolculuklar: ziyaretçilerin siteye girdikten sonra hangi sayfaları hangi sırayla gezdiği; araya giren olaylar (sepete ekleme, ödeme formu, "Ödeme yap", ödeme yöntemi, sipariş, WhatsApp/telefon tıklaması) "@olay" anahtarlı adım olarak görünür (ev: true). summary: çok sayfalı ziyaret sayısı/oranı, ortalama yol uzunluğu, en sık giriş ve çıkış sayfası, hedefe ulaşma oranı (önceki döneme göre değişimle). flow: adım adım her sayfada kaç ziyaret, kaçı orada bitti (ends) ve adımlar arası geçişler (links). paths.items: en sık izlenen yollar (sessions, share %, prev, change %, rate = hedefe ulaşma %). steps: 2–6 adım; from / to: başlangıç / bitiş sayfası yolu; goal: "huni:adım" (ör. "1:3"), boş = hedefsiz, verilmezse ödeme sayfası; group: true ise /haber/... gibi alt sayfalar tek grupta.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array_merge(
				$range,
				array(
					'steps' => array( 'type' => 'integer', 'minimum' => 2, 'maximum' => 6 ),
					'from'  => array( 'type' => 'string' ),
					'to'    => array( 'type' => 'string' ),
					'goal'  => array( 'type' => 'string' ),
					'group' => array( 'type' => 'boolean' ),
					'top'   => array( 'type' => 'integer' ),
				)
			),
		),
		'run'         => function ( $a ) {
			FLXAN_Aggregator::maybe_run( 60 );
			$r   = flxan_tool_range( $a );
			$out = FLXAN_Journeys::report(
				$r['start'],
				$r['end'],
				$r['prev_start'],
				$r['prev_end'],
				array(
					'steps' => (int) ( $a['steps'] ?? 4 ),
					'start' => (string) ( $a['from'] ?? '' ),
					'end'   => (string) ( $a['to'] ?? '' ),
					'goal'  => array_key_exists( 'goal', $a ) ? (string) $a['goal'] : null,
					'group' => ! empty( $a['group'] ),
				)
			);
			$out['paths']['items'] = array_slice( $out['paths']['items'], 0, max( 5, min( 50, (int) ( $a['top'] ?? 15 ) ) ) );
			return array( 'range' => $r ) + $out;
		},
	);

	$tools['flxan_refs'] = array(
		'title'       => __( 'Referans bağlantıları raporu', 'flx-analytics' ),
		'description' => 'Referans kodları (?r=kod bağlantıları; ör. "ahmet-ig" = "Ahmet\'in Instagram\'ı"): kod, ad, bağlantı, ziyaretçi, görüntülenme, ziyaret (sessions), ziyaret başına sayfa, ortalama süre (sn), bağış sayısı/tutarı, huni içinde ulaşılan en derin adım ve tamamlayan ziyaret. Referansla gelen kişi 30 gün tanınır (çerez), sonraki ziyaretleri ve bağışı da o koda sayılır. Adı verilmiş ama trafik almamış kodlar da listelenir.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array( 'type' => 'object', 'properties' => array_merge( $range, array( 'limit' => array( 'type' => 'integer' ) ) ) ),
		'run'         => function ( $a ) {
			FLXAN_Aggregator::maybe_run( 60 );
			$r = flxan_tool_range( $a );
			return array( 'range' => $r, 'items' => FLXAN_Stats::refs( $r['start'], $r['end'], flxan_tool_limit( $a, 50, 200 ) ) );
		},
	);

	$tools['flxan_segments'] = array(
		'title'       => __( 'Rollere göre trafik', 'flx-analytics' ),
		'description' => 'Ziyaretçi ve görüntülenmenin role göre dağılımı: "" (Dışarıdan gelenler = giriş yapmamış) ve her WordPress rolü (yönetici, editör, müşteri…). Dışarıdan gelen trafiği ayrıca görmek için diğer araçlarda seg=guest verin.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array( 'type' => 'object', 'properties' => $range ),
		'run'         => function ( $a ) {
			$r = flxan_tool_range( $a );
			return array( 'range' => $r, 'items' => FLXAN_Stats::segments( $r['start'], $r['end'] ) );
		},
	);

	$tools['flxan_ref_save'] = array(
		'title'       => __( 'Referans bağlantısı oluştur / adlandır', 'flx-analytics' ),
		'description' => 'Referans kodu ekler ya da adını değiştirir (delete=true siler; istatistikler silinmez). Kod: küçük harf, rakam, - ve _ (en fazla 32; Türkçe karakter sadeleşir). Döner: paylaşılacak bağlantı (https://site/?r=kod). Ör. kod "ayse-wp", ad "Ayşe\'nin WhatsApp grubu".',
		'readonly'    => false,
		'cap'         => 'flxan_view',
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'code'   => array( 'type' => 'string' ),
				'name'   => array( 'type' => 'string' ),
				'delete' => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'code' ),
		),
		'run'         => function ( $a ) {
			if ( ! flxan_can_manage_refs() ) {
				return new WP_Error( 'flxan_forbidden', __( 'Referans bağlantılarını düzenleme yetkiniz yok.', 'flx-analytics' ) );
			}
			$code = flxan_ref_save( (string) ( $a['code'] ?? '' ), ! empty( $a['delete'] ) ? null : (string) ( $a['name'] ?? '' ) );
			if ( '' === $code ) {
				return new WP_Error( 'flxan_bad_code', __( 'Geçersiz kod. Harf, rakam, - ve _ kullanın.', 'flx-analytics' ) );
			}
			return array(
				'code'    => $code,
				'name'    => flxan_ref_names()[ $code ] ?? '',
				'url'     => add_query_arg( 'r', $code, home_url( '/' ) ),
				'deleted' => ! empty( $a['delete'] ),
			);
		},
	);

	return $tools;
}

add_filter(
	'flxseo_mcp_tools',
	function ( $tools ) {
		return array_merge( (array) $tools, flxan_tools() );
	}
);

/** Flux SEO & MCP yoksa Flux AI sohbetine doğrudan. */
add_filter(
	'flxai_tools',
	function ( $tools ) {
		if ( function_exists( 'flxseo_mcp_tools' ) ) {
			return $tools;
		}
		foreach ( flxan_tools() as $name => $t ) {
			$t['source']    = 'flx-analytics';
			$tools[ $name ] = $t;
		}
		return $tools;
	}
);
