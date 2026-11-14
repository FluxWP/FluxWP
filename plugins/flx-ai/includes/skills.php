<?php
/**
 * Skill'ler: Agent Skills standardında talimat klasörleri (agentskills.io; Claude.ai / Claude Code ile aynı biçim).
 *
 * Klasör: <ad>/SKILL.md (YAML ön bölüm + Markdown gövde) ve isteğe bağlı references/, assets/, scripts/ (+ evals/).
 * Ön bölüm: name (zorunlu, kebab-case, ≤64, klasör adıyla aynı), description (zorunlu, ≤1024, < > yok),
 * isteğe bağlı license, compatibility, allowed-tools (boşlukla ayrılmış), metadata (title, version, …).
 *
 * Kaynaklar: eklentilerin getirdiği hazır skill'ler (`flxai_skills` filtresi; dizi ya da 'dir' => klasör yolu) +
 * sitede kayıtlı olanlar (<çalışma alanı>/.skills/<ad>/; Flux Files'tan da görülür). Aynı adlı kayıt hazır skill'in
 * yerine geçer; kayıt silinince hazır hali geri gelir. Açık/kapalı durumu dosyada değil, `flxai_skills_off` seçeneğinde.
 *
 * Aşamalı açıklama (3 seviye): 1) etkin skill'lerin ad + açıklaması sistem metninde, 2) gövde flxai_skill_get ile ya da
 * "/ad" ile mesaja eklenir, 3) klasördeki dosyalar gerektiğinde flxai_skill_file ile okunur.
 * Bir mesajda birden çok skill: "/a /b metin". scripts/ saklanır, şimdilik çalıştırılmaz.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/yaml.php';

/** Skill klasöründe bilinen alt klasörler. */
const FLXAI_SKILL_SUBDIRS = array( 'references', 'assets', 'scripts', 'evals' );

/** Skill adı: küçük harf, rakam, tek tire (en fazla 64). */
function flxai_skill_slug( $s ) {
	$s = sanitize_title( remove_accents( (string) $s ) );
	$s = trim( preg_replace( '/-+/', '-', preg_replace( '/[^a-z0-9-]+/', '-', $s ) ), '-' );
	return trim( substr( $s, 0, 64 ), '-' );
}

/** Flux AI'nin kendi hazır skill'leri. */
function flxai_skills_builtin() {
	return array(
		'gunluk-brifing'   => array(
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
		'gorsel-rapor'     => array(
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
		'site-sagligi'     => array(
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
		'skill-olusturucu' => array(
			'name'        => __( 'Skill creator', 'flx-ai' ),
			'description' => __( 'Creates, reviews and tests skills in the Agent Skills format. Use when the user wants a new skill, wants to improve one or asks why a skill does not trigger.', 'flx-ai' ),
			'dir'         => FLXAI_DIR . 'skills/skill-olusturucu',
		),
		'kapak-tasarimi'   => array(
			'name'        => __( 'Cover design', 'flx-ai' ),
			'description' => __( 'Brand-style visuals with text as PNG/JPEG in several sizes from one brief: event covers, campaign and donation announcements, posts, stories, WhatsApp status, link previews, thank-you and quote cards, step infographics, carousel pages. Use for "cover", "poster", "event visual", "story", "post image", "banner".', 'flx-ai' ),
			'dir'         => FLXAI_DIR . 'skills/kapak-tasarimi',
		),
	) + flxai_skills_social();
}

/**
 * Sosyal medya skill'leri (0.21.0): charlie947/social-media-skills v1.1.0 (MIT, Charlie Hills), Flux AI araçlarına uyarlandı.
 * Lisans: skills/THIRD-PARTY.md.
 */
function flxai_skills_social() {
	$src  = __( 'Social media', 'flx-ai' );
	$list = array(
		'voice-builder'          => array( __( 'Voice builder', 'flx-ai' ), __( 'Learns who you are and how you write from a short interview and 3-5 writing samples; saves about-me.md and voice.md to the brand kit. The other social media skills read them. Use for "learn my voice", "set up my content system".', 'flx-ai' ) ),
		'newsletter-voice'       => array( __( 'Newsletter voice', 'flx-ai' ), __( 'Adds newsletter-specific writing rules on top of the voice profile (newsletter-voice.md), from past issues or one of 6 archetypes. Use for "learn my newsletter style", "set up my newsletter".', 'flx-ai' ) ),
		'post-writer'            => array( __( 'Post writer', 'flx-ai' ), __( 'Writes LinkedIn and social media posts in your voice from a topic, notes or a transcript. Use for "write a post", "draft a post about…".', 'flx-ai' ) ),
		'post-formatter'         => array( __( 'Post formatter', 'flx-ai' ), __( 'Turns a topic into a ready-to-publish post with a named framework (PAS, AIDA, BAB, STAR, SLAY), mobile-formatted. Use for "format this as a post", "write it as PAS".', 'flx-ai' ) ),
		'hook-generator'         => array( __( 'Hook generator', 'flx-ai' ), __( 'Six short two-line opening hooks for a post topic, using your real numbers and experience. Use for "write me hooks", "hook ideas".', 'flx-ai' ) ),
		'post-scorer'            => array( __( 'Post scorer', 'flx-ai' ), __( 'Scores a post draft against your own past performance (export or pasted posts) or with a labelled editorial review. Use for "score my post", "rate this post".', 'flx-ai' ) ),
		'content-matrix'         => array( __( 'Content matrix', 'flx-ai' ), __( 'Pairs your 3-5 content pillars with 8 formats for 24-40 post ideas in one table. Use for "post ideas", "content plan for the month", "what should I post".', 'flx-ai' ) ),
		'niche-research'         => array( __( 'Niche research', 'flx-ai' ), __( 'Finds up to 20 dated stories from the last 7 days in a niche with web search, with links and post angles. Use for "what\'s trending", "this week\'s news in…", "research my niche".', 'flx-ai' ) ),
		'graphic-designer'       => array( __( 'Graphic designer', 'flx-ai' ), __( 'Designs the image for a post: a structured HTML graphic rendered to PNG or an AI infographic (whiteboard or branded). Use for "design a graphic for my post", "make an image for this post".', 'flx-ai' ) ),
		'carousel'               => array( __( 'Carousel', 'flx-ai' ), __( 'Builds a branded multi-slide carousel (1080x1350): brief, approval, then one image prompt or render per slide. Use for "carousel", "turn this into slides".', 'flx-ai' ) ),
		'whiteboard-infographic' => array( __( 'Whiteboard infographic', 'flx-ai' ), __( 'Turns a post, newsletter or note into a hand-drawn whiteboard infographic (brief + image prompt or generated image). Use for "whiteboard infographic", "hand-drawn graphic".', 'flx-ai' ) ),
		'quote-post'             => array( __( 'Quote post', 'flx-ai' ), __( 'Writes short original quotes and a matching quote graphic for a caption. Use for "quote post", "quote graphic", "motivational post".', 'flx-ai' ) ),
		'pinned-comment'         => array( __( 'Pinned comment', 'flx-ai' ), __( 'Writes a deadpan pinned/first comment for a post together with a matching image prompt. Use for "pinned comment", "first comment".', 'flx-ai' ) ),
		'profile-optimizer'      => array( __( 'Profile optimizer', 'flx-ai' ), __( 'Rewrites a LinkedIn profile: headline options, about, experience, featured section and banner/photo image prompts. Use for "optimize my profile", "rewrite my headline".', 'flx-ai' ) ),
		'youtube-thumbnail'      => array( __( 'YouTube thumbnail', 'flx-ai' ), __( 'Turns a video title into a high-click branded YouTube thumbnail prompt or image. Use for "thumbnail", "YouTube cover".', 'flx-ai' ) ),
		'reels-scripting'        => array( __( 'Reels scripting', 'flx-ai' ), __( 'Writes a Reel/short video script in your voice from a reference video\'s transcript and your content. Use for "script a reel", "turn this into a reel".', 'flx-ai' ) ),
		'analytics-dashboard'    => array( __( 'Analytics dashboard', 'flx-ai' ), __( 'Turns a LinkedIn Analytics export (CSV) into an interactive visual report and 5 data-backed recommendations. Use for "analyse my LinkedIn", "build my dashboard".', 'flx-ai' ) ),
	);
	$out = array();
	foreach ( $list as $slug => $s ) {
		$out[ $slug ] = array(
			'name'        => $s[0],
			'description' => $s[1],
			'dir'         => FLXAI_DIR . 'skills/' . $slug,
			'source'      => $src,
		);
	}
	return $out;
}

/**
 * Ad / açıklama kuralları (standart). $folder verilirse ad klasör adıyla aynı olmalı.
 *
 * @return true|WP_Error
 */
function flxai_skill_validate( array $s, $folder = '' ) {
	$name = (string) ( $s['slug'] ?? '' );
	$e    = fn( $code, $msg ) => new WP_Error( 'flxai_skill_' . $code, $msg, array( 'status' => 400 ) );
	if ( '' === $name ) {
		return $e( 'name', __( 'The skill needs a name (lowercase letters, numbers and dashes).', 'flx-ai' ) );
	}
	if ( strlen( $name ) > 64 || ! preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $name ) ) {
		/* translators: %s: skill name */
		return $e( 'name', sprintf( __( '"%s" is not a valid skill name: use up to 64 lowercase letters, numbers and single dashes, not starting or ending with a dash.', 'flx-ai' ), $name ) );
	}
	if ( preg_match( '/claude|anthropic/', $name ) ) {
		return $e( 'name', __( 'Skill names cannot contain "claude" or "anthropic" (reserved).', 'flx-ai' ) );
	}
	if ( '' !== $folder && $folder !== $name ) {
		/* translators: 1: folder name, 2: skill name */
		return $e( 'folder', sprintf( __( 'The folder "%1$s" must have the same name as the skill ("%2$s").', 'flx-ai' ), $folder, $name ) );
	}
	$d = trim( (string) ( $s['description'] ?? '' ) );
	if ( '' === $d ) {
		return $e( 'description', __( 'Write a description: what the skill does and when to use it. The assistant picks skills by this.', 'flx-ai' ) );
	}
	if ( mb_strlen( $d ) > 1024 ) {
		return $e( 'description', __( 'The description can be at most 1024 characters.', 'flx-ai' ) );
	}
	foreach ( array( 'description' => $d, 'title' => (string) ( $s['name'] ?? '' ) ) as $v ) {
		if ( preg_match( '/[<>]/', $v ) ) {
			return $e( 'chars', __( 'The name and description cannot contain < or > characters.', 'flx-ai' ) );
		}
	}
	if ( mb_strlen( (string) ( $s['compatibility'] ?? '' ) ) > 500 ) {
		return $e( 'compat', __( 'Compatibility can be at most 500 characters.', 'flx-ai' ) );
	}
	return true;
}

/** allowed-tools: boşlukla (ya da virgülle) ayrılmış metin ya da liste → ad listesi. */
function flxai_skill_tool_list( $tools ) {
	if ( is_string( $tools ) ) {
		$tools = preg_split( '/[\s,]+/', trim( $tools, '[] ' ), -1, PREG_SPLIT_NO_EMPTY );
	}
	$out = array();
	foreach ( (array) $tools as $t ) {
		$t = trim( (string) $t, " \t'\"" );
		if ( '' !== $t && preg_match( '/^[A-Za-z0-9_.*:\-()\/]+$/', $t ) && strlen( $t ) <= 120 ) {
			$out[] = $t;
		}
	}
	return array_values( array_unique( $out ) );
}

/** Bir skill kaydını tek biçime getirir. */
function flxai_skill_normalize( $slug, array $s ) {
	$meta = isset( $s['meta'] ) && is_array( $s['meta'] ) ? $s['meta'] : array();
	return array(
		'slug'          => flxai_skill_slug( $slug ),
		'name'          => mb_substr( trim( wp_strip_all_tags( (string) ( $s['name'] ?? $slug ) ) ), 0, 190 ),
		'description'   => mb_substr( trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) ( $s['description'] ?? '' ) ) ) ), 0, 1024 ),
		'body'          => (string) ( $s['body'] ?? '' ),
		'tools'         => flxai_skill_tool_list( $s['tools'] ?? array() ),
		'example'       => (string) ( $s['example'] ?? '' ),
		'license'       => mb_substr( trim( wp_strip_all_tags( (string) ( $s['license'] ?? '' ) ) ), 0, 200 ),
		'compatibility' => mb_substr( trim( wp_strip_all_tags( (string) ( $s['compatibility'] ?? '' ) ) ), 0, 500 ),
		'meta'          => $meta,
		'status'        => 'off' === ( $s['status'] ?? '' ) ? 'off' : 'active',
		'source'        => (string) ( $s['source'] ?? '' ),
		'version'       => max( 1, (int) ( $s['version'] ?? 1 ) ),
		'updated'       => (int) ( $s['updated'] ?? 0 ),
		'id'            => (int) ( $s['id'] ?? 0 ),
		'dir'           => (string) ( $s['dir'] ?? '' ),
	);
}

/** Kapalı skill'ler (site ayarı). */
function flxai_skills_off() {
	$v = get_option( 'flxai_skills_off', array() );
	return is_array( $v ) ? array_values( array_filter( array_map( 'strval', $v ) ) ) : array();
}

function flxai_skill_set_status( $slug, $status ) {
	$off = array_diff( flxai_skills_off(), array( $slug ) );
	if ( 'off' === $status ) {
		$off[] = $slug;
	}
	update_option( 'flxai_skills_off', array_values( $off ), false );
}

/** Klasördeki SKILL.md'yi okur (dosya sistemi yolu). @return array|null */
function flxai_skill_read_dir( $abs ) {
	$file = rtrim( (string) $abs, '/' ) . '/SKILL.md';
	if ( ! is_readable( $file ) ) {
		return null;
	}
	$d = flxai_skill_from_md( (string) file_get_contents( $file ) ); // phpcs:ignore
	if ( is_wp_error( $d ) ) {
		return null;
	}
	$ex = rtrim( (string) $abs, '/' ) . '/references/examples.md';
	if ( '' === trim( $d['example'] ) && is_readable( $ex ) ) {
		$d['example'] = trim( (string) file_get_contents( $ex ) ); // phpcs:ignore
	}
	$d['updated'] = (int) filemtime( $file );
	$d['dir']     = rtrim( (string) $abs, '/' );
	return $d;
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
	 * $skills['bagisci-incelemesi'] = array( 'name', 'description', 'body' (Markdown), 'tools', 'example', 'source' )
	 * ya da standart klasör: array( 'dir' => __DIR__ . '/skills/bagisci-incelemesi', 'name' => __( … ), 'description' => __( … ), 'source' ).
	 * Klasördeki SKILL.md gövdeyi ve araçları verir; dizideki ad/açıklama (çevrilmiş) önce gelir.
	 *
	 * @param array $skills slug => skill.
	 */
	$list = apply_filters( 'flxai_skills', $builtin );
	$out  = array();
	foreach ( is_array( $list ) ? $list : array() as $slug => $s ) {
		if ( is_string( $s ) ) {
			$s = array( 'dir' => $s );
		}
		if ( ! is_array( $s ) ) {
			continue;
		}
		if ( ! empty( $s['dir'] ) ) {
			$f = flxai_skill_read_dir( (string) $s['dir'] );
			if ( ! $f ) {
				continue;
			}
			$s = array_merge( $f, array_filter( $s, fn( $v ) => '' !== $v && array() !== $v ) );
			if ( is_int( $slug ) ) {
				$slug = $f['slug'];
			}
		}
		if ( '' === trim( (string) ( $s['body'] ?? '' ) ) ) {
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

/** Sitedeki skill klasörü (çalışma alanına göre): .skills/<ad> */
function flxai_skill_rel_dir( $slug ) {
	return flxai_ws_skills_dir() . '/' . flxai_skill_slug( $slug );
}

/** Skill dosyası (çalışma alanına göre): .skills/<ad>/SKILL.md */
function flxai_skill_file( $slug ) {
	return flxai_skill_rel_dir( $slug ) . '/SKILL.md';
}

/** Sitede kayıtlı skill'ler (klasörler): slug => skill. */
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
		$d = flxai_skill_read_dir( dirname( $file ) );
		if ( ! $d || '' === trim( $d['body'] ) ) {
			continue;
		}
		$n = flxai_skill_normalize( $slug, $d );
		if ( ! $d['_title'] ) {
			$n['name'] = ''; // hazır skill'in (çevrilmiş) adı kullanılır
		}
		$n['_legacy_off'] = 'off' === $d['status'];
		$out[ $slug ]     = $n;
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
	$off     = flxai_skills_off();
	$out     = array();
	foreach ( $bundled as $slug => $s ) {
		$s['bundled'] = true;
		$s['custom']  = false;
		$s['changed'] = false;
		if ( isset( $saved[ $slug ] ) ) {
			$keys = array( 'name', 'description', 'body', 'tools', 'example' );
			$orig = array_intersect_key( $s, array_flip( $keys ) );
			// Boş ad/açıklama: hazır skill'in (çevrilmiş) metni kullanılır; dil değişince eski dilde kalmaz.
			$over = array_filter( array_intersect_key( $saved[ $slug ], array_flip( array( 'name', 'description', 'license', 'compatibility' ) ) ), 'strlen' );
			$s    = array_merge( $s, $over, array_intersect_key( $saved[ $slug ], array_flip( array( 'body', 'tools', 'example', 'meta', 'version', 'updated', 'id', 'dir', '_legacy_off' ) ) ) );
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
	foreach ( $out as $slug => $s ) {
		$out[ $slug ]['status'] = in_array( $slug, $off, true ) || ! empty( $s['_legacy_off'] ) ? 'off' : 'active';
		unset( $out[ $slug ]['_legacy_off'] );
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

/** Skill sitede kayıtlı mı (klasörü çalışma alanında)? */
function flxai_skill_is_saved( $slug ) {
	return isset( flxai_skills_saved()[ flxai_skill_slug( $slug ) ] );
}

/**
 * Skill kaydeder. Hazır skill için sitede değiştirilmiş kopya oluşur (klasördeki ek dosyalar da kopyalanır).
 *
 * @param array  $data slug, name, description, body, tools, example, status, license, compatibility, meta.
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
	if ( '' === $s['name'] ) {
		$s['name'] = $s['slug'];
	}
	if ( '' === $s['slug'] ) {
		return new WP_Error( 'flxai_skill_name', __( 'Give the skill a name.', 'flx-ai' ), array( 'status' => 400 ) );
	}
	if ( '' === trim( $s['body'] ) ) {
		return new WP_Error( 'flxai_skill_body', __( 'Write the skill instructions.', 'flx-ai' ), array( 'status' => 400 ) );
	}
	$ok = flxai_skill_validate( $s );
	if ( is_wp_error( $ok ) ) {
		return $ok;
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
	$rel          = flxai_skill_rel_dir( $s['slug'] );
	// Hazır skill klasörlüyse ek dosyaları (references, assets, scripts) ilk kayıtta kopyala.
	$src = $prev && ! flxai_skill_is_saved( $prev['slug'] ) ? (string) $prev['dir'] : '';
	if ( $prev && $prev['slug'] !== $s['slug'] && flxai_skill_is_saved( $prev['slug'] ) ) {
		$src = flxai_ws_abs( flxai_skill_rel_dir( $prev['slug'] ) );
	}
	if ( '' !== $src && is_dir( $src ) ) {
		foreach ( flxai_skill_files_in( $src ) as $f ) {
			if ( ! is_file( flxai_ws_abs( $rel . '/' . $f['path'] ) ) ) {
				flxai_ws_write( $rel . '/' . $f['path'], (string) file_get_contents( $src . '/' . $f['path'] ) ); // phpcs:ignore
			}
		}
	}
	// Hazır skill'in adı/açıklaması değişmediyse dosyaya yazılmaz; dil değişince çevrilmiş hali gelir.
	$w = flxai_ws_write(
		$rel . '/SKILL.md',
		flxai_skill_to_md( $s, $bund && $bund['name'] === $s['name'] ? '' : null, $bund && $bund['description'] === $s['description'] ? '' : null, false )
	);
	if ( is_wp_error( $w ) ) {
		return $w;
	}
	$ex = $rel . '/references/examples.md';
	if ( '' !== trim( $s['example'] ) ) {
		flxai_ws_write( $ex, trim( $s['example'] ) . "\n" );
	} elseif ( is_file( flxai_ws_abs( $ex ) ) ) {
		flxai_ws_remove( $ex );
	}
	if ( $prev && $prev['slug'] !== $s['slug'] && ! empty( $prev['custom'] ) ) {
		flxai_ws_remove( flxai_skill_rel_dir( $prev['slug'] ) );
		flxai_skill_set_status( $prev['slug'], 'active' );
	}
	flxai_skill_set_status( $s['slug'], $s['status'] );
	flxai_skills_saved( true );
	if ( $prev && $prev['slug'] !== $s['slug'] ) {
		flxai_skill_rename_in_tasks( $prev['slug'], $s['slug'] );
	}
	return flxai_skill_get( $s['slug'] );
}

/** Açık / kapalı (dosyaya dokunmaz; hazır skill kopyalanmaz). */
function flxai_skill_toggle( $slug ) {
	$s = flxai_skill_get( $slug );
	if ( ! $s ) {
		return false;
	}
	flxai_skill_set_status( $s['slug'], 'active' === $s['status'] ? 'off' : 'active' );
	if ( 'off' === $s['status'] && flxai_skill_is_saved( $s['slug'] ) ) {
		// Eski biçimde "status: off" satırı varsa temizle.
		$md = flxai_ws_read( flxai_skill_file( $s['slug'] ) );
		if ( null !== $md && preg_match( '/^status:\s*off\s*$/mi', $md ) ) {
			flxai_ws_write( flxai_skill_file( $s['slug'] ), preg_replace( '/^status:\s*off\s*\n/mi', '', $md, 1 ) );
			flxai_skills_saved( true );
		}
	}
	return true;
}

/** Sitedeki kaydı siler: kendi skill'i kalkar, hazır skill varsayılan haline döner. */
function flxai_skill_delete( $slug ) {
	$ok = flxai_ws_remove( flxai_skill_rel_dir( $slug ) );
	if ( ! isset( flxai_skills_bundled()[ flxai_skill_slug( $slug ) ] ) ) {
		flxai_skill_set_status( flxai_skill_slug( $slug ), 'active' );
	}
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

/* ---------- Klasördeki dosyalar (3. seviye) ---------- */

/** Dosya uzantısı yasak mı (sunucuda çalışabilecek dosyalar saklanmaz). */
function flxai_skill_ext_blocked( $path ) {
	return (bool) preg_match( '/\.(php\d?|phtml|phar|pht|phps|cgi|htaccess|htpasswd|user\.ini)$/i', $path ) || preg_match( '/(^|\/)\.ht/i', $path );
}

/**
 * Göreli dosya yolunu doğrular (skill klasörü içinde, SKILL.md dışında).
 *
 * @return string|WP_Error
 */
function flxai_skill_clean_path( $path ) {
	$path = trim( str_replace( '\\', '/', (string) $path ), '/' );
	$path = preg_replace( '#/+#', '/', $path );
	if ( '' === $path || preg_match( '#(^|/)\.\.?(/|$)#', $path ) || strlen( $path ) > 200 || ! preg_match( '#^[A-Za-z0-9._\-/ ]+$#', $path ) ) {
		return new WP_Error( 'flxai_skill_path', __( 'Invalid file path. Use something like references/guide.md.', 'flx-ai' ), array( 'status' => 400 ) );
	}
	if ( 'SKILL.md' === $path ) {
		return new WP_Error( 'flxai_skill_path', __( 'SKILL.md is edited with the skill form or flxai_skill_save.', 'flx-ai' ), array( 'status' => 400 ) );
	}
	if ( flxai_skill_ext_blocked( $path ) ) {
		return new WP_Error( 'flxai_skill_path', __( 'This file type is not allowed in a skill folder.', 'flx-ai' ), array( 'status' => 400 ) );
	}
	return $path;
}

/** Bir klasördeki dosyalar (SKILL.md ve gizliler hariç): path, size. */
function flxai_skill_files_in( $abs ) {
	$abs = rtrim( (string) $abs, '/' );
	$out = array();
	if ( '' === $abs || ! is_dir( $abs ) ) {
		return $out;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $abs, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	$it->setMaxDepth( 4 );
	foreach ( $it as $f ) {
		if ( count( $out ) >= 300 ) {
			break;
		}
		$rel = ltrim( str_replace( '\\', '/', substr( $f->getPathname(), strlen( $abs ) ) ), '/' );
		if ( $f->isLink() || ! $f->isFile() || 'SKILL.md' === $rel || preg_match( '#(^|/)\.#', $rel ) || 'index.php' === basename( $rel ) ) {
			continue;
		}
		$out[] = array(
			'path' => $rel,
			'size' => (int) $f->getSize(),
		);
	}
	usort( $out, fn( $a, $b ) => strcmp( $a['path'], $b['path'] ) );
	return $out;
}

/** Skill'in klasör yolu (dosya sistemi): kayıtlıysa çalışma alanı, değilse hazır klasör. */
function flxai_skill_abs_dir( array $s ) {
	if ( flxai_skill_is_saved( $s['slug'] ) ) {
		return flxai_ws_abs( flxai_skill_rel_dir( $s['slug'] ) );
	}
	return (string) ( $s['dir'] ?? '' );
}

/** Skill klasöründeki dosyalar. */
function flxai_skill_files( array $s ) {
	return flxai_skill_files_in( flxai_skill_abs_dir( $s ) );
}

/** scripts/ içinde dosya var mı? */
function flxai_skill_has_scripts( array $s ) {
	foreach ( flxai_skill_files( $s ) as $f ) {
		if ( 0 === strpos( $f['path'], 'scripts/' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Klasördeki bir dosyayı okur. Metin değilse yalnız bilgi döner.
 *
 * @return array|WP_Error path, size, text?, content?, offset, next_offset?
 */
function flxai_skill_file_read( array $s, $path, $offset = 0, $limit = 40000 ) {
	$path = flxai_skill_clean_path( $path );
	if ( is_wp_error( $path ) ) {
		return $path;
	}
	$dir  = flxai_skill_abs_dir( $s );
	$file = $dir . '/' . $path;
	$real = realpath( $file );
	if ( '' === $dir || ! $real || 0 !== strpos( $real, realpath( $dir ) . '/' ) || ! is_file( $real ) ) {
		/* translators: %s: file path */
		return new WP_Error( 'flxai_skill_file', sprintf( __( 'The skill has no file "%s".', 'flx-ai' ), $path ), array( 'status' => 404 ) );
	}
	$size = (int) filesize( $real );
	$head = (string) file_get_contents( $real, false, null, 0, 8000 ); // phpcs:ignore
	$text = false === strpos( $head, "\0" ) && ( '' === $head || preg_match( '//u', $head ) || preg_match( '//u', substr( $head, 0, -3 ) ) );
	$out  = array(
		'path' => $path,
		'size' => $size,
		'text' => $text,
	);
	if ( ! $text ) {
		$out['note'] = __( 'Binary file (image, font, archive…); it can be used as a template or attachment but not read as text.', 'flx-ai' );
		return $out;
	}
	$all            = (string) file_get_contents( $real ); // phpcs:ignore
	$offset         = max( 0, (int) $offset );
	$chunk          = mb_substr( $all, $offset, max( 1000, (int) $limit ) );
	$out['offset']  = $offset;
	$out['content'] = $chunk;
	if ( $offset + mb_strlen( $chunk ) < mb_strlen( $all ) ) {
		$out['next_offset'] = $offset + mb_strlen( $chunk );
	}
	return $out;
}

/** Hazır skill'i sitede kopyalar (dosya düzenlemeden önce). */
function flxai_skill_fork( $slug ) {
	$s = flxai_skill_get( $slug );
	if ( ! $s ) {
		return new WP_Error( 'flxai_not_found', __( 'Skill not found.', 'flx-ai' ), array( 'status' => 404 ) );
	}
	if ( flxai_skill_is_saved( $s['slug'] ) ) {
		return $s;
	}
	return flxai_skill_save( array(), $s['slug'] );
}

/**
 * Klasöre dosya yazar ya da siler ($content null). Hazır skill önce sitede kopyalanır.
 *
 * @return true|WP_Error
 */
function flxai_skill_file_write( $slug, $path, $content ) {
	$path = flxai_skill_clean_path( $path );
	if ( is_wp_error( $path ) ) {
		return $path;
	}
	$s = flxai_skill_fork( $slug );
	if ( is_wp_error( $s ) ) {
		return $s;
	}
	$rel = flxai_skill_rel_dir( $s['slug'] ) . '/' . $path;
	if ( null === $content ) {
		if ( ! file_exists( flxai_ws_abs( $rel ) ) ) {
			/* translators: %s: file path */
			return new WP_Error( 'flxai_skill_file', sprintf( __( 'The skill has no file "%s".', 'flx-ai' ), $path ), array( 'status' => 404 ) );
		}
		flxai_ws_remove( $rel );
	} else {
		if ( strlen( (string) $content ) > 5 * MB_IN_BYTES ) {
			return new WP_Error( 'flxai_skill_file', __( 'A skill file can be at most 5 MB.', 'flx-ai' ), array( 'status' => 400 ) );
		}
		if ( count( flxai_skill_files( $s ) ) >= 200 && ! is_file( flxai_ws_abs( $rel ) ) ) {
			return new WP_Error( 'flxai_skill_file', __( 'A skill folder can hold at most 200 files.', 'flx-ai' ), array( 'status' => 400 ) );
		}
		$w = flxai_ws_write( $rel, (string) $content );
		if ( is_wp_error( $w ) ) {
			return $w;
		}
	}
	if ( 'references/examples.md' === $path ) {
		flxai_skills_saved( true );
	}
	return true;
}

/* ---------- Zip (standart klasör) ---------- */

/** Skill klasörünü zip'ler: <ad>/SKILL.md + dosyalar. @return string|WP_Error geçici dosya yolu */
function flxai_skill_zip( array $s ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'flxai_zip', __( 'The zip PHP extension is not available on this server.', 'flx-ai' ) );
	}
	$tmp = wp_tempnam( $s['slug'] . '.zip' );
	$zip = new ZipArchive();
	if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
		return new WP_Error( 'flxai_zip', __( 'The zip file could not be created.', 'flx-ai' ) );
	}
	$zip->addFromString( $s['slug'] . '/SKILL.md', flxai_skill_to_md( $s, null, null, true ) );
	$dir = flxai_skill_abs_dir( $s );
	$has = false;
	foreach ( flxai_skill_files( $s ) as $f ) {
		$zip->addFile( $dir . '/' . $f['path'], $s['slug'] . '/' . $f['path'] );
		$has = $has || 'references/examples.md' === $f['path'];
	}
	if ( ! $has && '' !== trim( $s['example'] ) ) {
		$zip->addFromString( $s['slug'] . '/references/examples.md', trim( $s['example'] ) . "\n" );
	}
	$zip->close();
	return $tmp;
}

/**
 * Zip ya da tek SKILL.md içe aktarır. Zip'te birden çok skill olabilir (her SKILL.md'nin klasörü bir skill).
 *
 * @param string $file Dosya yolu.
 * @param string $name Özgün dosya adı.
 * @return array|WP_Error imported (slug listesi), notes
 */
function flxai_skill_import_file( $file, $name = '' ) {
	$head = (string) file_get_contents( $file, false, null, 0, 4 ); // phpcs:ignore
	if ( "PK\x03\x04" !== $head ) {
		return flxai_skill_import_md( (string) file_get_contents( $file, false, null, 0, 300000 ) ); // phpcs:ignore
	}
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'flxai_zip', __( 'The zip PHP extension is not available on this server.', 'flx-ai' ) );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $file ) ) {
		return new WP_Error( 'flxai_zip', __( 'The zip file could not be opened.', 'flx-ai' ) );
	}
	$entries = array();
	$total   = 0;
	for ( $k = 0; $k < $zip->numFiles; $k++ ) {
		$st = $zip->statIndex( $k );
		$n  = str_replace( '\\', '/', (string) $st['name'] );
		if ( '/' === substr( $n, -1 ) || 0 === strpos( $n, '__MACOSX/' ) || preg_match( '#(^|/)\.DS_Store$#', $n ) ) {
			continue;
		}
		$entries[ $n ] = (int) $st['size'];
		$total        += (int) $st['size'];
	}
	if ( count( $entries ) > 500 || $total > 30 * MB_IN_BYTES ) {
		$zip->close();
		return new WP_Error( 'flxai_zip', __( 'The zip is too large (at most 500 files and 30 MB).', 'flx-ai' ) );
	}
	$roots = array();
	foreach ( array_keys( $entries ) as $n ) {
		if ( 'SKILL.md' === basename( $n ) ) {
			$roots[] = '.' === dirname( $n ) ? '' : dirname( $n );
		} elseif ( 'skill.md' === strtolower( basename( $n ) ) ) {
			$zip->close();
			return new WP_Error( 'flxai_skill_md', __( 'The instructions file must be named exactly SKILL.md (capital letters).', 'flx-ai' ) );
		}
	}
	if ( ! $roots ) {
		$zip->close();
		return new WP_Error( 'flxai_skill_md', __( 'No SKILL.md found in the zip.', 'flx-ai' ) );
	}
	$done  = array();
	$notes = array();
	foreach ( $roots as $root ) {
		$pre  = '' === $root ? '' : $root . '/';
		$md   = (string) $zip->getFromName( $pre . 'SKILL.md' );
		$data = flxai_skill_from_md( $md );
		if ( is_wp_error( $data ) ) {
			$zip->close();
			return $data;
		}
		$ok = flxai_skill_validate( $data, '' === $root ? '' : basename( $root ) );
		if ( is_wp_error( $ok ) ) {
			$zip->close();
			return $ok;
		}
		$prev = flxai_skill_get( $data['slug'] );
		$s    = flxai_skill_save( $data, $prev ? $prev['slug'] : '' );
		if ( is_wp_error( $s ) ) {
			$zip->close();
			return $s;
		}
		$rel = flxai_skill_rel_dir( $s['slug'] );
		foreach ( $entries as $n => $size ) {
			if ( '' !== $pre && 0 !== strpos( $n, $pre ) ) {
				continue;
			}
			$sub = substr( $n, strlen( $pre ) );
			if ( 'SKILL.md' === $sub || ( '' === $pre && false !== strpos( $sub, '/SKILL.md' ) ) ) {
				continue;
			}
			// Başka bir iç skill'in dosyası mı?
			foreach ( $roots as $r2 ) {
				if ( $r2 !== $root && '' !== $r2 && 0 === strpos( $n, $r2 . '/' ) && strlen( $r2 ) > strlen( $root ) ) {
					continue 2;
				}
			}
			if ( 'README.md' === $sub ) {
				$notes[] = __( 'README.md is not part of a skill folder and was skipped; put documentation in SKILL.md or references/.', 'flx-ai' );
				continue;
			}
			$path = flxai_skill_clean_path( $sub );
			if ( is_wp_error( $path ) || $size > 5 * MB_IN_BYTES ) {
				/* translators: %s: file name */
				$notes[] = sprintf( __( 'Skipped: %s', 'flx-ai' ), $sub );
				continue;
			}
			flxai_ws_write( $rel . '/' . $path, (string) $zip->getFromName( $n ) );
		}
		// SKILL.md özgün haliyle yazılır (biçim ve ek metadata korunur).
		flxai_ws_write( $rel . '/SKILL.md', $md );
		flxai_skills_saved( true );
		$s = flxai_skill_get( $s['slug'] );
		if ( $s && flxai_skill_has_scripts( $s ) ) {
			/* translators: %s: skill name */
			$notes[] = sprintf( __( '"%s" has scripts. They are stored but not run on this server.', 'flx-ai' ), $s['slug'] );
		}
		$done[] = $data['slug'];
	}
	$zip->close();
	return array(
		'imported' => $done,
		'notes'    => array_values( array_unique( $notes ) ),
	);
}

/** Tek SKILL.md metni içe aktarır. */
function flxai_skill_import_md( $text ) {
	$data = flxai_skill_from_md( $text );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$ok = flxai_skill_validate( $data );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$prev = flxai_skill_get( $data['slug'] );
	$s    = flxai_skill_save( $data, $prev ? $prev['slug'] : '' );
	if ( is_wp_error( $s ) ) {
		return $s;
	}
	return array(
		'imported' => array( $s['slug'] ),
		'notes'    => array(),
	);
}

/* ---------- Model istemi ---------- */

/** Modele giden talimat bloğu (2. seviye) + klasördeki dosyaların listesi (3. seviye için). */
function flxai_skill_prompt( array $s ) {
	$out = '[Skill: ' . $s['name'] . ' (' . $s['slug'] . ")]\n" . trim( $s['body'] );
	if ( $s['tools'] ) {
		$out .= "\n\nÖnerilen araçlar: " . implode( ', ', $s['tools'] );
	}
	$files = array_filter( flxai_skill_files( $s ), fn( $f ) => 'references/examples.md' !== $f['path'] && 0 !== strpos( $f['path'], 'evals/' ) );
	if ( $files ) {
		$out .= "\n\nSkill klasöründeki dosyalar (gerektiğinde flxai_skill_file ile oku; scripts/ şimdilik çalıştırılamaz):\n"
			. implode( "\n", array_map( fn( $f ) => '- ' . $f['path'] . ' (' . size_format( $f['size'] ) . ')', array_slice( $files, 0, 40 ) ) );
	}
	if ( '' !== trim( $s['example'] ) ) {
		$out .= "\n\nÖrnek çıktı:\n" . trim( $s['example'] );
	}
	return $out . "\n[/Skill]";
}

/* ---------- SKILL.md ---------- */

/**
 * SKILL.md metni (standart ön bölüm).
 *
 * @param string|null $title       Görünen ad, metadata.title (null: skill adı, '': yazılmaz).
 * @param string|null $description Açıklama (null: skill açıklaması, '': yazılmaz; yalnız sitedeki kopyada).
 * @param bool        $export      Dışa aktarma: örnek çıktı references/examples.md'de olduğundan gövdeye eklenmez.
 */
function flxai_skill_to_md( array $s, $title = null, $description = null, $export = false ) {
	$s           = $s + array( 'license' => '', 'compatibility' => '', 'meta' => array(), 'tools' => array(), 'version' => 1 );
	$title       = null === $title ? $s['name'] : (string) $title;
	$description = null === $description ? $s['description'] : (string) $description;
	$meta        = is_array( $s['meta'] ) ? $s['meta'] : array();
	unset( $meta['title'], $meta['version'] );
	if ( '' !== $title && $title !== $s['slug'] ) {
		$meta = array( 'title' => $title ) + $meta;
	}
	$meta['version'] = (string) (int) $s['version'];
	$head            = array(
		'name'          => $s['slug'],
		'description'   => preg_replace( '/\s+/u', ' ', (string) $description ),
		'license'       => (string) $s['license'],
		'compatibility' => (string) $s['compatibility'],
		'allowed-tools' => implode( ' ', (array) $s['tools'] ),
		'metadata'      => $meta,
	);
	unset( $export );
	return "---\n" . flxai_yaml_dump( $head ) . "---\n\n" . trim( (string) $s['body'] ) . "\n";
}

/** @return array|WP_Error skill alanları (kaydetmez). Eski biçim (title, tools, status, version üstte) de okunur. */
function flxai_skill_from_md( $text ) {
	$text = str_replace( "\r\n", "\n", (string) $text );
	$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
	if ( ! preg_match( '/^\s*---\n(.*?)\n---[ \t]*\n?(.*)$/s', $text, $m ) ) {
		return new WP_Error( 'flxai_skill_md', __( 'This does not look like a SKILL.md file (it must start with a --- header).', 'flx-ai' ), array( 'status' => 400 ) );
	}
	$head = flxai_yaml_parse( $m[1] );
	if ( is_wp_error( $head ) ) {
		return new WP_Error( 'flxai_skill_md', $head->get_error_message(), array( 'status' => 400 ) );
	}
	$head    = array_change_key_case( $head, CASE_LOWER );
	$meta    = isset( $head['metadata'] ) && is_array( $head['metadata'] ) ? $head['metadata'] : array();
	$body    = trim( $m[2] );
	$example = '';
	$parts   = preg_split( '/^##\s+(?:Example output|Örnek çıktı)\s*$/mu', $body, 2 );
	if ( 2 === count( $parts ) ) {
		$body    = trim( $parts[0] );
		$example = trim( $parts[1] );
	}
	$title = (string) ( is_string( $meta['title'] ?? null ) ? $meta['title'] : ( is_string( $head['title'] ?? null ) ? $head['title'] : '' ) );
	$tools = $head['allowed-tools'] ?? ( $head['tools'] ?? array() );
	$str   = fn( $k ) => is_string( $head[ $k ] ?? null ) ? trim( $head[ $k ] ) : '';
	return array(
		'slug'          => flxai_skill_slug( $str( 'name' ) ),
		'_raw_name'     => $str( 'name' ),
		'name'          => '' !== $title ? $title : $str( 'name' ),
		'description'   => trim( preg_replace( '/\s+/u', ' ', $str( 'description' ) ) ),
		'license'       => $str( 'license' ),
		'compatibility' => $str( 'compatibility' ),
		'tools'         => flxai_skill_tool_list( $tools ),
		'meta'          => $meta,
		'body'          => $body,
		'example'       => $example,
		'status'        => 'off' === strtolower( $str( 'status' ) ) ? 'off' : 'active',
		'version'       => max( 1, (int) ( $meta['version'] ?? ( $head['version'] ?? 1 ) ) ),
		'_title'        => '' !== $title,
	);
}

/* ---------- Sohbet ---------- */

/* Sistem metni: etkin skill'lerin listesi (1. seviye; az değişir, önbelleğe alınan kısımda kalır). */
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
		return $text . "\n\n## Skill'ler\nSitede hazır talimat paketleri (Agent Skills) var. İstek bunlardan birine ya da birkaçına uyuyorsa önce flxai_skill_get ile talimatlarını oku ve uy; "
			. "talimat bir dosyaya (references/…, assets/…) işaret ediyorsa onu flxai_skill_file ile yalnız gerektiğinde oku. "
			. "Mesajda [Skill: …] bloğu varsa talimat zaten verilmiştir.\n" . implode( "\n", $lines );
	},
	20
);

/** Mesajın kullandığı skill'ler: görev skill'i + başta "/a /b" ile yazılanlar (etkin olanlar). */
function flxai_skill_slugs_in( array $m ) {
	$slugs = array();
	if ( ! empty( $m['_task']['skill'] ) ) {
		$slugs[] = (string) $m['_task']['skill'];
	}
	$text = ltrim( (string) ( is_string( $m['content'] ?? null ) ? $m['content'] : '' ) );
	while ( preg_match( '/^\/([a-z0-9-]{2,64})(?=\s|$)\s*/', $text, $mm ) ) {
		$slugs[] = $mm[1];
		$text    = substr( $text, strlen( $mm[0] ) );
	}
	$out = array();
	foreach ( array_unique( $slugs ) as $slug ) {
		$s = flxai_skill_get( $slug );
		if ( $s && 'active' === $s['status'] ) {
			$out[ $s['slug'] ] = $s;
		}
		if ( count( $out ) >= 5 ) {
			break;
		}
	}
	return $out;
}

/* Görev skill'i ya da "/a /b" ile çağrılan skill'ler: talimat kullanıcı mesajına eklenir (kayıtlı mesaj değişmez). */
add_filter(
	'flxai_agent_user_text',
	function ( $text, $m ) {
		$skills = flxai_skill_slugs_in( $m );
		if ( ! $skills ) {
			return $text;
		}
		return implode( "\n\n", array_map( 'flxai_skill_prompt', $skills ) ) . "\n\n" . $text;
	},
	5,
	2
);

/** "Skill kullanılırken araçları allowed-tools ile sınırla" ayarı. */
function flxai_skills_restrict_tools() {
	return (bool) get_option( 'flxai_skills_restrict', 0 );
}

/** Araç adı allowed-tools listesine uyuyor mu (tam ad ya da sonda * joker). */
function flxai_skill_tool_allowed( $name, array $allowed ) {
	foreach ( $allowed as $a ) {
		if ( $a === $name || ( '*' === substr( $a, -1 ) && 0 === strpos( $name, substr( $a, 0, -1 ) ) ) ) {
			return true;
		}
	}
	return false;
}

/* Ayar açıksa: son kullanıcı mesajı skill kullanıyorsa ve skill'ler allowed-tools veriyorsa araçlar bununla daraltılır. */
add_filter(
	'flxai_chat_tools',
	function ( $tools, $chat ) {
		if ( ! flxai_skills_restrict_tools() || empty( $chat['messages'] ) ) {
			return $tools;
		}
		$last = null;
		for ( $i = count( $chat['messages'] ) - 1; $i >= 0; $i-- ) {
			if ( 'user' === ( $chat['messages'][ $i ]['role'] ?? '' ) ) {
				$last = $chat['messages'][ $i ];
				break;
			}
		}
		$skills = $last ? flxai_skill_slugs_in( $last ) : array();
		$allow  = array();
		foreach ( $skills as $s ) {
			$allow = array_merge( $allow, $s['tools'] );
		}
		if ( ! $allow ) {
			return $tools;
		}
		$allow = array_merge( $allow, array( 'flxai_skill_get', 'flxai_skill_file', 'flxai_skill_list', 'flxai_current_time' ) );
		return array_filter( (array) $tools, fn( $t, $name ) => flxai_skill_tool_allowed( (string) $name, $allow ), ARRAY_FILTER_USE_BOTH );
	},
	20,
	2
);

/* Araçlar (sohbet + MCP). Skill'ler tüm site için ortak olduğundan değiştirmek yönetici işi. */
add_filter(
	'flx_mcp_tools',
	function ( $tools ) {
		$tools = is_array( $tools ) ? $tools : array();
		$pub   = function ( array $s, $full ) {
			$files = flxai_skill_files( $s );
			$o     = array(
				'name'        => $s['slug'],
				'title'       => $s['name'],
				'description' => $s['description'],
				'status'      => $s['status'],
				'source'      => $s['bundled'] ? $s['source'] : 'site',
				'changed'     => $s['changed'],
				'version'     => $s['version'],
				'tools'       => $s['tools'],
				'files'       => count( $files ),
			);
			if ( $full ) {
				$o['license']        = $s['license'];
				$o['compatibility']  = $s['compatibility'];
				$o['instructions']   = $s['body'];
				$o['example_output'] = $s['example'];
				$o['files']          = $files;
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
		$need  = function ( $a ) {
			$s = flxai_skill_get( (string) ( $a['name'] ?? '' ) );
			if ( ! $s ) {
				throw new Exception( __( 'Skill not found.', 'flx-ai' ) );
			}
			return $s;
		};
		$tools['flxai_skill_list']   = array(
			'title'       => __( 'List skills', 'flx-ai' ),
			'description' => 'Sitedeki skill\'ler (Agent Skills talimat klasörleri): ad, başlık, açıklama, durum, kaynak eklenti, dosya sayısı.',
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
			'description' => 'Bir skill\'in talimatının tamamı (SKILL.md gövdesi), önerilen araçlar, örnek çıktı, klasördeki dosyaların listesi ve ona bağlı görevler. İş bir skill\'e uyuyorsa önce bunu oku.',
			'readonly'    => true,
			'cap'         => 'read',
			'log'         => false,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'name' => array( 'type' => 'string', 'description' => 'Skill adı (ör. gunluk-brifing).' ) ),
				'required'   => array( 'name' ),
			),
			'run'         => function ( $a ) use ( $pub, $need ) {
				return $pub( $need( $a ), true );
			},
		);
		$tools['flxai_skill_file']   = array(
			'title'       => __( 'Read a skill file', 'flx-ai' ),
			'description' => 'Skill klasöründeki bir dosyayı okur (references/…, assets/…, scripts/…). Talimat bir dosyaya işaret ettiğinde, yalnız gerektiğinde oku. Uzun dosyalarda next_offset ile devam et.',
			'readonly'    => true,
			'cap'         => 'read',
			'log'         => false,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'name'   => array( 'type' => 'string', 'description' => 'Skill adı.' ),
					'path'   => array( 'type' => 'string', 'description' => 'Klasöre göre yol, ör. references/kurallar.md.' ),
					'offset' => array( 'type' => 'integer', 'description' => 'Karakter konumu (uzun dosyada devam için).' ),
				),
				'required'   => array( 'name', 'path' ),
			),
			'run'         => function ( $a ) use ( $need ) {
				$r = flxai_skill_file_read( $need( $a ), (string) ( $a['path'] ?? '' ), (int) ( $a['offset'] ?? 0 ) );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return $r;
			},
		);
		$tools['flxai_skill_save']   = array(
			'title'       => __( 'Create or update a skill', 'flx-ai' ),
			'description' => 'Skill oluşturur ya da değiştirir (Agent Skills standardı: name kebab-case ≤64, "claude"/"anthropic" içeremez; description ≤1024, ne yaptığını VE ne zaman kullanılacağını söyler, < > yok). Var olan skill için yalnız verilen alanlar değişir; talimatı değiştirirken önce flxai_skill_get ile oku ve tam yeni metni gönder. Uzun ayrıntıyı references/ dosyalarına koy (flxai_skill_file_write). Hazır (eklentiden gelen) skill değiştirilince sitede kopyası tutulur. Skill\'e bağlı görevler yeni hali kullanır.',
			'readonly'    => false,
			'cap'         => 'manage_options',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'name'           => array( 'type' => 'string', 'description' => 'Skill adı, küçük harf ve tire (ör. bagisci-incelemesi). Varsa günceller.' ),
					'new_name'       => array( 'type' => 'string', 'description' => 'Yeniden adlandırmak için yeni ad (yalnız sitede oluşturulan skill).' ),
					'title'          => array( 'type' => 'string', 'description' => 'Görünen ad (ör. "Bağışçı incelemesi"); metadata.title olarak saklanır.' ),
					'description'    => array( 'type' => 'string', 'description' => 'Ne yaptığı + ne zaman kullanılacağı (kullanıcının söyleyeceği tetikleyici ifadelerle). En fazla 1024 karakter.' ),
					'instructions'   => array( 'type' => 'string', 'description' => 'SKILL.md gövdesi, Markdown. Adım adım, kendi başına anlaşılır; 5.000 kelimenin altında.' ),
					'tools'          => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'allowed-tools: skill\'in kullanacağı araç adları.',
					),
					'license'        => array( 'type' => 'string' ),
					'compatibility'  => array( 'type' => 'string', 'description' => 'Ortam gereksinimi (ör. "Flux CRM gerekir").' ),
					'example_output' => array( 'type' => 'string' ),
					'status'         => array( 'type' => 'string', 'enum' => array( 'active', 'off' ) ),
				),
				'required'   => array( 'name' ),
			),
			'run'         => function ( $a ) use ( $pub ) {
				$name = flxai_skill_slug( (string) ( $a['name'] ?? '' ) );
				$prev = flxai_skill_get( $name );
				$s    = flxai_skill_save(
					array(
						'slug'          => $prev && ! empty( $a['new_name'] ) ? (string) $a['new_name'] : $name,
						'name'          => $a['title'] ?? null,
						'description'   => $a['description'] ?? null,
						'body'          => $a['instructions'] ?? null,
						'tools'         => $a['tools'] ?? null,
						'license'       => $a['license'] ?? null,
						'compatibility' => $a['compatibility'] ?? null,
						'example'       => $a['example_output'] ?? null,
						'status'        => $a['status'] ?? null,
					),
					$prev ? $name : ''
				);
				if ( is_wp_error( $s ) ) {
					throw new Exception( $s->get_error_message() );
				}
				return array(
					'saved'  => true,
					'skill'  => $pub( $s, false ),
					'manage' => admin_url( 'admin.php?page=flx-ai-skills&edit=' . $s['slug'] ),
				);
			},
		);
		$tools['flxai_skill_file_write'] = array(
			'title'       => __( 'Write a skill file', 'flx-ai' ),
			'description' => 'Skill klasörüne dosya yazar ya da siler (delete=true): references/ (ayrıntılı belge, kurallar, örnekler), assets/ (şablonlar), scripts/ (saklanır, şimdilik çalışmaz), evals/evals.json (testler). SKILL.md için flxai_skill_save kullan.',
			'readonly'    => false,
			'cap'         => 'manage_options',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'name'    => array( 'type' => 'string' ),
					'path'    => array( 'type' => 'string', 'description' => 'Ör. references/kurallar.md' ),
					'content' => array( 'type' => 'string' ),
					'delete'  => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'name', 'path' ),
			),
			'run'         => function ( $a ) use ( $need ) {
				$s = $need( $a );
				$r = flxai_skill_file_write( $s['slug'], (string) ( $a['path'] ?? '' ), ! empty( $a['delete'] ) ? null : (string) ( $a['content'] ?? '' ) );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return array(
					'ok'    => true,
					'files' => flxai_skill_files( flxai_skill_get( $s['slug'] ) ),
				);
			},
		);
		$tools['flxai_skill_test']   = array(
			'title'       => __( 'Test a skill', 'flx-ai' ),
			'description' => 'Skill testlerini çalıştırır (evals/evals.json). kind=trigger: tetiklenme testi (yalnız ad + açıklama ile doğru skill seçiliyor mu; trigger_evals[].query, should_trigger). kind=functional: bir test (evals[].id) skill\'li ve skill\'siz çalıştırılır, beklentiler (expectations) kontrol edilir, token ve maliyet kıyaslanır. Her çalıştırma AI harcaması yapar.',
			'readonly'    => false,
			'cap'         => 'manage_options',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'name' => array( 'type' => 'string' ),
					'kind' => array( 'type' => 'string', 'enum' => array( 'trigger', 'functional' ) ),
					'id'   => array( 'type' => 'string', 'description' => 'functional için test kimliği.' ),
				),
				'required'   => array( 'name', 'kind' ),
			),
			'run'         => function ( $a ) use ( $need ) {
				$s = $need( $a );
				$r = 'functional' === ( $a['kind'] ?? '' ) ? flxai_skill_eval_functional( $s['slug'], (string) ( $a['id'] ?? '' ) ) : flxai_skill_eval_trigger( $s['slug'] );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return $r;
			},
		);
		$tools['flxai_skill_delete'] = array(
			'title'       => __( 'Delete a skill', 'flx-ai' ),
			'description' => 'Sitede oluşturulan skill\'i (klasörüyle) siler; hazır skill\'i varsayılan haline döndürür. Bağlı görevler skill\'siz çalışır.',
			'readonly'    => false,
			'cap'         => 'manage_options',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'name' => array( 'type' => 'string' ) ),
				'required'   => array( 'name' ),
			),
			'run'         => function ( $a ) use ( $need ) {
				$s = $need( $a );
				return array(
					'deleted' => flxai_skill_delete( $s['slug'] ),
					'reset'   => (bool) $s['bundled'],
				);
			},
		);
		return $tools;
	}
);

require_once __DIR__ . '/skill-evals.php';
