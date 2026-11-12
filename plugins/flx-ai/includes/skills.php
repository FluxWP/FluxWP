<?php
/**
 * Skill'ler (0.12.0): adlı, sürümlü talimat paketleri ("Günlük brifing", "Bağışçı incelemesi", "Reklam taslağı").
 *
 * Kaynaklar: eklentilerin getirdiği hazır skill'ler (`flxai_skills` filtresi, kodda) + sitede kayıtlı olanlar
 * (0.13.0'dan beri dosya: <çalışma alanı>/.skills/<ad>/SKILL.md; Flux Files'tan da görülür/düzenlenir). Aynı adlı kayıt hazır skill'in yerine geçer (kopyalayıp değiştirme); kayıt silinince
 * hazır hali geri gelir. Sohbet modeli etkin skill'lerin ad + açıklama listesini sistem metninde görür,
 * ihtiyaç olunca flxai_skill_get ile talimatı okur. Kullanıcı mesajı "/ad" ile başlarsa ya da görev bir
 * skill'e bağlıysa talimat doğrudan mesaja eklenir. Skill düzenlenince ona bağlı tüm görevler yeni hali kullanır.
 *
 * Dışa/içe aktarma SKILL.md biçimi: YAML ön bölüm (name = ad, title, description, tools) + Markdown gövde;
 * "## Örnek çıktı" / "## Example output" başlığından sonrası örnek çıktıdır.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Skill adı: küçük harf, rakam, tire (en fazla 60). */
function flxai_skill_slug( $s ) {
	$s = sanitize_title( remove_accents( (string) $s ) );
	$s = trim( preg_replace( '/[^a-z0-9-]+/', '-', $s ), '-' );
	return substr( $s, 0, 60 );
}

/** Flux AI'nin kendi hazır skill'leri. */
function flxai_skills_builtin() {
	return array(
		'gunluk-brifing' => array(
			'name'        => __( 'Daily briefing', 'flx-ai' ),
			'description' => __( 'Short morning report: yesterday\'s donations and orders, unanswered messages, site issues and what needs you today.', 'flx-ai' ),
			'tools'       => array( 'flxai_current_time', 'flxb_donation_summary', 'flxseo_order_summary', 'flxcrm_overview', 'flxcrm_recent_events', 'flxads_report' ),
			'body'        => "# Günlük brifing\n\n"
				. "Kullanıcıya güne başlarken okuyacağı kısa bir rapor hazırla.\n\n"
				. "1. Tarihi flxai_current_time ile al; \"dün\" ve \"önceki gün\" bu tarihe göre (Y-m-d).\n"
				. "2. Bağışlar: flxb_donation_summary varsa period=yesterday, compare=true ile çağır (ödenen adet/toplam, türlere göre, bekleyen, iptal ve önceki güne göre değişim tek çağrıda gelir).\n"
				. "3. Siparişler: flxseo_order_summary varsa date=<dün>, compare=true ile çağır; bugünkü bekleyen ödemeler için bir kez de parametresiz çağır.\n"
				. "4. Mesajlar ve kişiler: Flux Chat / Flux CRM araçlarından hangileri varsa (ör. flxcrm_overview, flxcrm_recent_events) cevapsız sohbetleri ve dün eklenen kişileri al. Site sorunları: sistem kontrolü ve hız araçları. Reklam: flxads_report.\n"
				. "5. Araç listesinde olmayan konuyu hiç yazma; \"doğrulanamadı\", \"alınamadı\" gibi boş maddeler ekleme. Uydurma.\n"
				. "6. Rapor: başlıklı, madde madde, en fazla 15 satır. Değişim varsa sayının yanına yaz (ör. \"12 bağış, 4.350 TL (önceki gün 9)\"). En sonda \"Bugün bakmanız gereken\" bölümü (onay bekleyen dekont, bekleyen ödeme, cevapsız mesaj, hata).\n"
				. "7. Önceki günden belirgin fark (ör. bağış yarıya düştü) varsa bir cümleyle belirt; karşılaştırma verisi yoksa bu cümleyi atla.",
			'example'     => "## Günlük brifing\n- **Bağışlar:** dün 12 bağış, toplam 4.350 TL.\n- **Mesajlar:** 3 cevapsız WhatsApp sohbeti.\n- **Site:** sorun yok.\n\n**Bugün bakmanız gereken:** 2 dekont onay bekliyor.",
		),
		'gorsel-rapor'   => array(
			'name'        => __( 'Visual report', 'flx-ai' ),
			'description' => __( 'Turns data, plans or explanations into an interactive one-page visual report (Tailwind, charts, diagrams) you open in the chat.', 'flx-ai' ),
			'tools'       => array( 'flxai_visual_report' ),
			'body'        => "# Görsel rapor\n\n"
				. "Konuyu ya da veriyi tek sayfalık, etkileşimli bir HTML rapora çevir ve flxai_visual_report ile kaydet.\n\n"
				. "## Akış\n"
				. "1. Gereken veriyi önce araçlarla topla (sayfa dışarıya istek atamaz, her şey sayfaya gömülür). Uydurma veri yok; eksik olanı sayfada \"veri yok\" diye belirt.\n"
				. "2. Sayfayı yaz ve flxai_visual_report'u bir kez çağır. Cevapta HTML'i tekrarlama; 2-3 cümle özet + rapordaki en önemli bulgu.\n\n"
				. "## Tasarım\n"
				. "- Önce cevap: ilk ekranda büyük başlık, tek cümlelik sonuç ve 3-4 KPI kartı (sayı büyük, açıklama küçük, değişim yeşil/kırmızı rozet).\n"
				. "- Türüne göre üslup: metrik/inceleme = sade kartlar ve tablolar; süreç/mimari = akış şeması (Mermaid ya da inline SVG); kavram = sade metin + çizim; özet/sunum = büyük başlıklar, bölüm bölüm.\n"
				. "- Tailwind sınıfları, yalnız 1 vurgu rengi + gri tonları; her öğede dark: karşılığı (koyu modda okunur olmalı). Kartlar rounded-2xl, ince kenarlık, bol boşluk; mobilde tek sütun (grid-cols-1 sm:grid-cols-2 lg:grid-cols-4).\n"
				. "- Grafik: <canvas> + new Chart(...) (Chart.js kendiliğinden yüklenir; responsive, maintainAspectRatio:false, sabit yükseklikli kap). Zaman serisi çizgi, karşılaştırma bar, pay en çok 5 dilimli doughnut.\n"
				. "- Tablolar: başlık satırı sabit renk, sayılar sağa yaslı, durum için renkli küçük rozet; 4+ satırlı veri her zaman tablo ya da grafik.\n"
				. "- Etkileşim: <details> ile açılır bölümler, sekme ya da filtre için küçük inline <script>; tıklanınca ayrıntı. Dış bağlantı yok, form yok.\n"
				. "- Metin kısa ve sade; Türkçe karakterler doğru; tarih ve para biçimi yerel (1.250 TL, 12 Kasım 2026).\n"
				. "- Sonda \"Ne yapmalı\" bölümü: en fazla 3 somut adım.",
			'example'     => "Rapor kaydedildi: **Kasım bağış raporu**. Bağışlar geçen aya göre %18 arttı, en çok fidan bağışı geldi; 2 bekleyen havale var.",
		),
		'site-sagligi'   => array(
			'name'        => __( 'Site health check', 'flx-ai' ),
			'description' => __( 'Checks system checks, speed and SEO audit results; reports only what changed or needs action.', 'flx-ai' ),
			'tools'       => array(),
			'body'        => "# Site sağlığı incelemesi\n\n"
				. "1. Sistem kontrolü, hız (PageSpeed) ve SEO denetimi araçlarından hangileri varsa son durumu oku.\n"
				. "2. Hata ve uyarıları önem sırasına koy; her biri için bir cümle neden + bir cümle ne yapılmalı.\n"
				. "3. Önceki rapora göre kötüleşen değer varsa vurgula. Her şey yolundaysa tek satırla söyle.\n"
				. "4. Ayar değiştiren düzeltmeleri kendin uygulama; öner, kullanıcı onaylasın.",
			'example'     => "**Site sağlığı:** 1 uyarı.\n- Mobil hız 71 → 58 düştü: ana sayfa görseli 1,8 MB. Görseli sıkıştırın.",
		),
	);
}

/** Bir skill kaydını tek biçime getirir. */
function flxai_skill_normalize( $slug, array $s ) {
	$tools = $s['tools'] ?? array();
	if ( is_string( $tools ) ) {
		$tools = preg_split( '/[\s,]+/', $tools, -1, PREG_SPLIT_NO_EMPTY );
	}
	$tools = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $tools ) ) ) );
	return array(
		'slug'        => flxai_skill_slug( $slug ),
		'name'        => mb_substr( trim( wp_strip_all_tags( (string) ( $s['name'] ?? $slug ) ) ), 0, 190 ),
		'description' => mb_substr( trim( wp_strip_all_tags( (string) ( $s['description'] ?? '' ) ) ), 0, 500 ),
		'body'        => (string) ( $s['body'] ?? '' ),
		'tools'       => $tools,
		'example'     => (string) ( $s['example'] ?? '' ),
		'status'      => 'off' === ( $s['status'] ?? '' ) ? 'off' : 'active',
		'source'      => (string) ( $s['source'] ?? '' ),
		'version'     => max( 1, (int) ( $s['version'] ?? 1 ) ),
		'updated'     => (int) ( $s['updated'] ?? 0 ),
		'id'          => (int) ( $s['id'] ?? 0 ),
	);
}

/** Eklentilerin getirdiği hazır skill'ler: slug => skill. */
function flxai_skills_bundled() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$builtin = array();
	foreach ( flxai_skills_builtin() as $slug => $s ) {
		$builtin[ $slug ] = $s + array( 'source' => 'Flux AI & MCP' );
	}
	/**
	 * Hazır skill'ler. Eklentiler kendi skill'lerini ekler:
	 * $skills['bagisci-incelemesi'] = array( 'name', 'description', 'body' (Markdown, modele talimat), 'tools' (araç adları), 'example', 'source' (eklenti adı) ).
	 *
	 * @param array $skills slug => skill.
	 */
	$list = apply_filters( 'flxai_skills', $builtin );
	$out  = array();
	foreach ( is_array( $list ) ? $list : array() as $slug => $s ) {
		if ( ! is_array( $s ) || '' === trim( (string) ( $s['body'] ?? '' ) ) ) {
			continue;
		}
		$n = flxai_skill_normalize( (string) $slug, $s );
		if ( '' !== $n['slug'] ) {
			$out[ $n['slug'] ] = $n;
		}
	}
	$cache = $out;
	return $out;
}

/** Skill dosyası (çalışma alanına göre): .skills/<ad>/SKILL.md */
function flxai_skill_file( $slug ) {
	return flxai_ws_skills_dir() . '/' . flxai_skill_slug( $slug ) . '/SKILL.md';
}

/** Sitede kayıtlı skill'ler (dosyalar): slug => skill. */
function flxai_skills_saved( $flush = false ) {
	static $cache = null;
	if ( $flush ) {
		$cache = null;
		return array();
	}
	if ( null !== $cache ) {
		return $cache;
	}
	$out = array();
	foreach ( (array) glob( flxai_ws_abs( flxai_ws_skills_dir() ) . '/*/SKILL.md' ) as $file ) {
		$slug = flxai_skill_slug( basename( dirname( $file ) ) );
		if ( '' === $slug ) {
			continue;
		}
		$md = flxai_ws_read( flxai_skill_file( $slug ) );
		$d  = null === $md ? null : flxai_skill_from_md( $md );
		if ( ! $d || is_wp_error( $d ) || '' === trim( $d['body'] ) ) {
			continue;
		}
		$d['name']    = $d['_title'] ? $d['name'] : '';
		$d['updated'] = (int) filemtime( $file );
		$n            = flxai_skill_normalize( $slug, $d );
		if ( ! $d['_title'] ) {
			$n['name'] = ''; // hazır skill'in (çevrilmiş) adı kullanılır
		}
		$out[ $slug ] = $n;
	}
	$cache = $out;
	return $out;
}

/**
 * Tüm skill'ler (kayıtlı olan hazır olanın yerine geçer).
 * Ek alanlar: bundled (hazır kaynaktan geliyor), custom (yalnız sitede), changed (hazır skill sitede değiştirilmiş).
 *
 * @param bool $active_only Yalnız etkinler.
 */
function flxai_skills_all( $active_only = false ) {
	$bundled = flxai_skills_bundled();
	$saved   = flxai_skills_saved();
	$out     = array();
	foreach ( $bundled as $slug => $s ) {
		$s['bundled'] = true;
		$s['custom']  = false;
		$s['changed'] = false;
		if ( isset( $saved[ $slug ] ) ) {
			$keys = array( 'name', 'description', 'body', 'tools', 'example' );
			$orig = array_intersect_key( $s, array_flip( $keys ) );
			// Boş ad/açıklama: hazır skill'in (çevrilmiş) metni kullanılır; dil değişince eski dilde kalmaz.
			$over = array_filter( array_intersect_key( $saved[ $slug ], array_flip( array( 'name', 'description' ) ) ), 'strlen' );
			$s    = array_merge( $s, $over, array_intersect_key( $saved[ $slug ], array_flip( array( 'body', 'tools', 'example', 'status', 'version', 'updated', 'id' ) ) ) );
			$s['changed'] = array_intersect_key( $s, array_flip( $keys ) ) != $orig; // phpcs:ignore Universal.Operators.StrictComparisons
		}
		$out[ $slug ] = $s;
	}
	foreach ( $saved as $slug => $s ) {
		if ( ! isset( $out[ $slug ] ) ) {
			$s['name']    = '' !== $s['name'] ? $s['name'] : $slug;
			$s['bundled'] = false;
			$s['custom']  = true;
			$s['changed'] = false;
			$s['source']  = '';
			$out[ $slug ] = $s;
		}
	}
	if ( $active_only ) {
		$out = array_filter( $out, fn( $s ) => 'active' === $s['status'] );
	}
	uasort( $out, fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );
	return $out;
}

function flxai_skill_get( $slug ) {
	$all = flxai_skills_all();
	return $all[ flxai_skill_slug( $slug ) ] ?? null;
}

/**
 * Skill kaydeder. Hazır skill için sitede değiştirilmiş kopya oluşur.
 *
 * @param array  $data slug, name, description, body, tools, example, status.
 * @param string $old  Düzenlenen skill'in adı (ad değişebilir).
 * @return array|WP_Error
 */
function flxai_skill_save( array $data, $old = '' ) {
	$old  = flxai_skill_slug( $old );
	$prev = '' !== $old ? flxai_skill_get( $old ) : null;
	if ( '' !== $old && ! $prev ) {
		return new WP_Error( 'flxai_not_found', __( 'Skill not found.', 'flx-ai' ), array( 'status' => 404 ) );
	}
	$base = $prev ? $prev : array();
	$data = array_merge( $base, array_filter( $data, fn( $v ) => null !== $v ) );
	$slug = flxai_skill_slug( (string) ( $data['slug'] ?? '' ) );
	if ( '' === $slug ) {
		$slug = flxai_skill_slug( (string) ( $data['name'] ?? '' ) );
	}
	if ( $prev && ! empty( $prev['bundled'] ) ) {
		$slug = $prev['slug']; // Hazır skill'in adı değişmez.
	}
	$s = flxai_skill_normalize( $slug, $data );
	if ( '' === $s['slug'] || '' === $s['name'] ) {
		return new WP_Error( 'flxai_skill_name', __( 'Give the skill a name.', 'flx-ai' ), array( 'status' => 400 ) );
	}
	if ( '' === trim( $s['body'] ) ) {
		return new WP_Error( 'flxai_skill_body', __( 'Write the skill instructions.', 'flx-ai' ), array( 'status' => 400 ) );
	}
	$s['body']    = mb_substr( $s['body'], 0, 30000 );
	$s['example'] = mb_substr( $s['example'], 0, 8000 );
	$taken        = flxai_skill_get( $s['slug'] );
	if ( $taken && $s['slug'] !== ( $prev['slug'] ?? '' ) ) {
		/* translators: %s: skill name (slug) */
		return new WP_Error( 'flxai_skill_taken', sprintf( __( 'A skill named "%s" already exists.', 'flx-ai' ), $s['slug'] ), array( 'status' => 400 ) );
	}
	$bund    = flxai_skills_bundled()[ $s['slug'] ] ?? null;
	$changed = $prev && ( $prev['body'] !== $s['body'] || $prev['tools'] !== $s['tools'] || $prev['example'] !== $s['example'] );
	$s['version'] = $prev ? (int) $prev['version'] + ( $changed ? 1 : 0 ) : 1;
	// Hazır skill'in adı/açıklaması değişmediyse dosyaya yazılmaz; dil değişince çevrilmiş hali gelir.
	$w = flxai_ws_write(
		flxai_skill_file( $s['slug'] ),
		flxai_skill_to_md( $s, $bund && $bund['name'] === $s['name'] ? '' : null, $bund && $bund['description'] === $s['description'] ? '' : null )
	);
	if ( is_wp_error( $w ) ) {
		return $w;
	}
	if ( $prev && $prev['slug'] !== $s['slug'] && ! empty( $prev['custom'] ) ) {
		flxai_ws_remove( dirname( flxai_skill_file( $prev['slug'] ) ) );
	}
	flxai_skills_saved( true );
	if ( $prev && $prev['slug'] !== $s['slug'] ) {
		flxai_skill_rename_in_tasks( $prev['slug'], $s['slug'] );
	}
	return flxai_skill_get( $s['slug'] );
}

/** Sitedeki kaydı siler: kendi skill'i kalkar, hazır skill varsayılan haline döner. */
function flxai_skill_delete( $slug ) {
	$ok = flxai_ws_remove( dirname( flxai_skill_file( $slug ) ) );
	flxai_skills_saved( true );
	return $ok;
}

/** Skill'e bağlı görevler. */
function flxai_skill_tasks( $slug ) {
	$out = array();
	foreach ( flxai_task_list( array( 'limit' => 500 ) ) as $t ) {
		if ( ( $t['meta']['skill'] ?? '' ) === $slug ) {
			$out[] = $t;
		}
	}
	return $out;
}

function flxai_skill_rename_in_tasks( $from, $to ) {
	foreach ( flxai_skill_tasks( $from ) as $t ) {
		$t['meta']['skill'] = $to;
		flxai_task_patch( $t['id'], array( 'meta' => $t['meta'] ) );
	}
}

/** Modele giden talimat bloğu. */
function flxai_skill_prompt( array $s ) {
	$out = '[Skill: ' . $s['name'] . ' (' . $s['slug'] . ")]\n" . trim( $s['body'] );
	if ( $s['tools'] ) {
		$out .= "\n\nÖnerilen araçlar: " . implode( ', ', $s['tools'] );
	}
	if ( '' !== trim( $s['example'] ) ) {
		$out .= "\n\nÖrnek çıktı:\n" . trim( $s['example'] );
	}
	return $out . "\n[/Skill]";
}

/* ---------- SKILL.md ---------- */

/**
 * SKILL.md metni.
 *
 * @param string|null $title       Başlık (null: skill adı, '': yazılmaz).
 * @param string|null $description Açıklama (null: skill açıklaması, '': yazılmaz).
 */
function flxai_skill_to_md( array $s, $title = null, $description = null ) {
	$q   = fn( $v ) => preg_match( '/[:#\-\[\]{}"\'&*!|>%@`]|^\s|\s$/u', $v ) ? '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $v ) . '"' : $v;
	$title       = null === $title ? $s['name'] : (string) $title;
	$description = null === $description ? $s['description'] : (string) $description;
	$out         = "---\nname: " . $s['slug'] . "\n";
	if ( '' !== $title ) {
		$out .= 'title: ' . $q( $title ) . "\n";
	}
	if ( '' !== $description ) {
		$out .= 'description: ' . $q( preg_replace( '/\s+/u', ' ', $description ) ) . "\n";
	}
	if ( $s['tools'] ) {
		$out .= 'tools: [' . implode( ', ', $s['tools'] ) . "]\n";
	}
	if ( 'off' === ( $s['status'] ?? '' ) ) {
		$out .= "status: off\n";
	}
	$out .= 'version: ' . (int) $s['version'] . "\n---\n\n" . trim( $s['body'] ) . "\n";
	if ( '' !== trim( $s['example'] ) ) {
		$out .= "\n## Example output\n\n" . trim( $s['example'] ) . "\n";
	}
	return $out;
}

/** @return array|WP_Error skill alanları (kaydetmez). */
function flxai_skill_from_md( $text ) {
	$text = str_replace( "\r\n", "\n", (string) $text );
	if ( ! preg_match( '/^\s*---\n(.*?)\n---\n?(.*)$/s', $text, $m ) ) {
		return new WP_Error( 'flxai_skill_md', __( 'This does not look like a SKILL.md file (it must start with a --- header).', 'flx-ai' ), array( 'status' => 400 ) );
	}
	$head = array();
	foreach ( explode( "\n", $m[1] ) as $line ) {
		if ( preg_match( '/^([a-z_]+):\s*(.*)$/i', $line, $kv ) ) {
			$v = trim( $kv[2] );
			if ( preg_match( '/^"(.*)"$/s', $v, $qq ) ) {
				$v = str_replace( array( '\\"', '\\\\' ), array( '"', '\\' ), $qq[1] );
			} elseif ( preg_match( "/^'(.*)'$/s", $v, $qq ) ) {
				$v = str_replace( "''", "'", $qq[1] );
			}
			$head[ strtolower( $kv[1] ) ] = $v;
		}
	}
	$body    = trim( $m[2] );
	$example = '';
	$parts   = preg_split( '/^##\s+(?:Example output|Örnek çıktı)\s*$/mu', $body, 2 );
	if ( 2 === count( $parts ) ) {
		$body    = trim( $parts[0] );
		$example = trim( $parts[1] );
	}
	$tools = trim( (string) ( $head['tools'] ?? '' ), '[] ' );
	$slug  = flxai_skill_slug( $head['name'] ?? '' );
	return array(
		'slug'        => $slug,
		'name'        => '' !== (string) ( $head['title'] ?? '' ) ? $head['title'] : ( $head['name'] ?? '' ),
		'description' => (string) ( $head['description'] ?? '' ),
		'tools'       => '' === $tools ? array() : array_map( 'trim', explode( ',', $tools ) ),
		'body'        => $body,
		'example'     => $example,
		'status'      => 'off' === strtolower( (string) ( $head['status'] ?? '' ) ) ? 'off' : 'active',
		'version'     => max( 1, (int) ( $head['version'] ?? 1 ) ),
		'_title'      => '' !== (string) ( $head['title'] ?? '' ),
	);
}

/* ---------- Sohbet ---------- */

/* Sistem metni: etkin skill'lerin listesi (az değişir, önbelleğe alınan kısımda kalır). */
add_filter(
	'flxai_chat_system',
	function ( $text ) {
		$skills = flxai_skills_all( true );
		if ( ! $skills ) {
			return $text;
		}
		$lines = array();
		foreach ( array_slice( $skills, 0, (int) apply_filters( 'flxai_skills_prompt_max', 40 ) ) as $s ) {
			$lines[] = '- ' . $s['slug'] . ': ' . $s['name'] . ( '' !== $s['description'] ? ' — ' . $s['description'] : '' );
		}
		return $text . "\n\n## Skill'ler\nSitede hazır talimat paketleri var. İstek bunlardan birine uyuyorsa önce flxai_skill_get ile talimatını oku ve ona uy. "
			. "Mesajda [Skill: …] bloğu varsa talimat zaten verilmiştir.\n" . implode( "\n", $lines );
	},
	20
);

/* Görev skill'i ya da "/ad" ile çağrılan skill: talimat kullanıcı mesajına eklenir (kayıtlı mesaj değişmez). */
add_filter(
	'flxai_agent_user_text',
	function ( $text, $m ) {
		$slug = '';
		if ( ! empty( $m['_task']['skill'] ) ) {
			$slug = (string) $m['_task']['skill'];
		} elseif ( preg_match( '/^\/([a-z0-9-]{2,60})(?=\s|$)/', ltrim( (string) ( $m['content'] ?? '' ) ), $mm ) ) {
			$slug = $mm[1];
		}
		if ( '' === $slug ) {
			return $text;
		}
		$s = flxai_skill_get( $slug );
		if ( ! $s || 'active' !== $s['status'] ) {
			return $text;
		}
		return flxai_skill_prompt( $s ) . "\n\n" . $text;
	},
	5,
	2
);

/* Araçlar (sohbet + MCP). Skill'ler tüm site için ortak olduğundan değiştirmek yönetici işi. */
add_filter(
	'flx_mcp_tools',
	function ( $tools ) {
		$tools = is_array( $tools ) ? $tools : array();
		$pub   = function ( array $s, $full ) {
			$o = array(
				'name'        => $s['slug'],
				'title'       => $s['name'],
				'description' => $s['description'],
				'status'      => $s['status'],
				'source'      => $s['bundled'] ? $s['source'] : 'site',
				'changed'     => $s['changed'],
				'version'     => $s['version'],
				'tools'       => $s['tools'],
			);
			if ( $full ) {
				$o['instructions']   = $s['body'];
				$o['example_output'] = $s['example'];
				$o['tasks']          = array_map(
					fn( $t ) => array(
						'id'    => $t['id'],
						'title' => $t['title'],
					),
					flxai_skill_tasks( $s['slug'] )
				);
			}
			return $o;
		};
		$tools['flxai_skill_list']   = array(
			'title'       => __( 'List skills', 'flx-ai' ),
			'description' => 'Sitedeki skill\'ler (hazır talimat paketleri): ad, başlık, açıklama, durum, kaynak eklenti.',
			'readonly'    => true,
			'cap'         => 'read',
			'log'         => false,
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'run'         => function () use ( $pub ) {
				return array( 'skills' => array_values( array_map( fn( $s ) => $pub( $s, false ), flxai_skills_all() ) ) );
			},
		);
		$tools['flxai_skill_get']    = array(
			'title'       => __( 'Read a skill', 'flx-ai' ),
			'description' => 'Bir skill\'in talimatının tamamı, önerilen araçlar, örnek çıktı ve ona bağlı görevler. İş bir skill\'e uyuyorsa önce bunu oku.',
			'readonly'    => true,
			'cap'         => 'read',
			'log'         => false,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'name' => array( 'type' => 'string', 'description' => 'Skill adı (ör. gunluk-brifing).' ) ),
				'required'   => array( 'name' ),
			),
			'run'         => function ( $a ) use ( $pub ) {
				$s = flxai_skill_get( (string) ( $a['name'] ?? '' ) );
				if ( ! $s ) {
					throw new Exception( __( 'Skill not found.', 'flx-ai' ) );
				}
				return $pub( $s, true );
			},
		);
		$tools['flxai_skill_save']   = array(
			'title'       => __( 'Create or update a skill', 'flx-ai' ),
			'description' => 'Skill oluşturur ya da değiştirir. Var olan skill için yalnız verilen alanlar değişir; talimatı değiştirirken önce flxai_skill_get ile oku ve tam yeni metni gönder. Hazır (eklentiden gelen) skill değiştirilince sitede kopyası tutulur. Skill\'e bağlı görevler yeni hali kullanır.',
			'readonly'    => false,
			'cap'         => 'manage_options',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'name'         => array( 'type' => 'string', 'description' => 'Skill adı, küçük harf ve tire (ör. bagisci-incelemesi). Varsa günceller.' ),
					'new_name'     => array( 'type' => 'string', 'description' => 'Yeniden adlandırmak için yeni ad (yalnız sitede oluşturulan skill).' ),
					'title'        => array( 'type' => 'string', 'description' => 'Görünen ad (ör. "Bağışçı incelemesi").' ),
					'description'  => array( 'type' => 'string', 'description' => 'Ne zaman kullanılacağı, tek cümle.' ),
					'instructions' => array( 'type' => 'string', 'description' => 'Modele talimat, Markdown. Adım adım, kendi başına anlaşılır.' ),
					'tools'        => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
					'example_output' => array( 'type' => 'string' ),
					'status'       => array( 'type' => 'string', 'enum' => array( 'active', 'off' ) ),
				),
				'required'   => array( 'name' ),
			),
			'run'         => function ( $a ) use ( $pub ) {
				$name = flxai_skill_slug( (string) ( $a['name'] ?? '' ) );
				$prev = flxai_skill_get( $name );
				$s    = flxai_skill_save(
					array(
						'slug'        => $prev && ! empty( $a['new_name'] ) ? (string) $a['new_name'] : $name,
						'name'        => $a['title'] ?? null,
						'description' => $a['description'] ?? null,
						'body'        => $a['instructions'] ?? null,
						'tools'       => $a['tools'] ?? null,
						'example'     => $a['example_output'] ?? null,
						'status'      => $a['status'] ?? null,
					),
					$prev ? $name : ''
				);
				if ( is_wp_error( $s ) ) {
					throw new Exception( $s->get_error_message() );
				}
				return array(
					'saved'  => true,
					'skill'  => $pub( $s, false ),
					'manage' => admin_url( 'admin.php?page=flx-ai-skills' ),
				);
			},
		);
		$tools['flxai_skill_delete'] = array(
			'title'       => __( 'Delete a skill', 'flx-ai' ),
			'description' => 'Sitede oluşturulan skill\'i siler; hazır skill\'i varsayılan haline döndürür. Bağlı görevler skill\'siz çalışır.',
			'readonly'    => false,
			'cap'         => 'manage_options',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'name' => array( 'type' => 'string' ) ),
				'required'   => array( 'name' ),
			),
			'run'         => function ( $a ) {
				$s = flxai_skill_get( (string) ( $a['name'] ?? '' ) );
				if ( ! $s ) {
					throw new Exception( __( 'Skill not found.', 'flx-ai' ) );
				}
				return array(
					'deleted' => flxai_skill_delete( $s['slug'] ),
					'reset'   => (bool) $s['bundled'],
				);
			},
		);
		return $tools;
	}
);
