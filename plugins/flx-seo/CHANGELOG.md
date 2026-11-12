# Değişiklikler

## 1.9.0
- MCP aracı `flxseo_order_summary` artık geçmiş bir günü de verir: `date` (Y-m-d, ör. dün) ile o günün ödenmiş sipariş, ciro, bekleyen ve iptal özeti; `recent` o günün son siparişleri olur.
- `compare=true` ile bir önceki günün özeti (`previous`) ve fark (`change`: diff + yüzde) da döner. Günlük brifing artık "dün"ü önceki günle karşılaştırabilir.
- Parametresiz çağrı eskisi gibi bugünü verir (TV sipariş paneli etkilenmez). Geçersiz ya da gelecek tarih açık bir hata döner.

## 1.8.9
- Sayfa yenilenince sekmede önce FluxWP simgesi, sonra sitenin simgesi görünüyordu. Sitenin simgesi (kurum profili ya da ön ayar) varsa artık ilk HTML'de yalnız o yazılır, FluxWP simgesi hiç basılmaz. /favicon.ico ve /site.webmanifest de sitenin simgesini verir.
- Yeni filtre `flx_custom_favicon` (adres); favicon yazılınca `flx_favicon_printed` çalışır, tema ve ön ayarlar ikinci simge basmaz.

## 1.8.8
- SEO API yanıtları ortak önbelleksiz başlıkları kullanır. CDN işareti durur.

## 1.8.7
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 1.8.6
- The scheduled-audit step (`admin-ajax.php?action=flxseo_audit_tick`) is no longer public. It runs for a logged-in user with `manage_options` and the `flxseo_audit_tick` nonce, or for this site's loopback carrying the per-audit token stored on the job. The token is created when the audit starts, checked with `hash_equals`, and deleted with the job. A lock still stops two ticks from running the same step. The shutdown nudge only opens a due job and sends that loopback, so the audit still advances after the screen is closed; the hourly WP-Cron event still runs a step itself, so the audit also advances where loopback requests are blocked.
- PageSpeed inside a web request waits at most 40 seconds (`set_time_limit` follows that budget). A timeout is stored as a failed step and the audit continues with the next step. CLI keeps the 90 second wait.

## 1.8.5
- Redirect conventional child names such as `/category-sitemap.xml` and `/page-sitemap2.xml` to existing core sitemap URLs. Preserve unknown, excluded, empty and out-of-range sitemap 404s and honor the global sitemap switch.

## 1.8.4
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 1.8.3
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 1.8.2
- **PHP güvenlik uyarısı** (Sistem Kontrolü › Güvenlik ve Site denetimi): çalışan PHP, PHP2Shell (CVE-2026-109618, CVE-2026-109619) açığına karşı yamasızsa (8.2.35, 8.3.36, 8.4.27, 8.5.12 altı) ya da desteklenmeyen bir daldaysa (8.1 ve öncesi) hata/kritik bulgu.
- `session.upload_progress.enabled` açıksa uyarı (PHP yamasızsa hata).
- Sürüm tablosu `flxseo_php_security_table` filtresiyle güncellenebilir (açık adı, sürüm notları adresi, dal başına yamalı sürüm).

## 1.8.1
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.


## 1.8.0
- Uygulama ayarları (ayar köprüsü) ve site denetimi (`flx-seo/v1/audit`).

## 1.7.1
- **Siteye özel sabit değer kalmadı.** Kurum schema'sına her sitede yazılan fidan/ağaçlandırma konuları (`knowsAbout`), `areaServed: Türkiye` ve "Fidan bağışı yap" `DonateAction` kaldırıldı. Yerine SEO › Kurum ve sosyal'de **Uzmanlık konuları**, **Hizmet bölgesi** ve **Bağış çağrısı** (ad + adres) ayarları geldi; boşsa schema'ya eklenmez. Ön ayarlar Flux Setup ile doldurur.
- Bağış/fidan sayfalarının ve etkinlik/proje arşivlerinin sabit SEO metinleri çıkarıldı; ön ayarlar `flxseo_keyword_pages` ve `flxseo_keyword_archives` filtreleriyle verir (değişince boş alanlara bir kez yazılır, elle girilene dokunulmaz).
- Makale sayılan yazı türleri `flxseo_article_types` filtresiyle (varsayılan yalnız `post`; ön ayar `etkinlik`, `proje` ekler). Bağış adresi için `flxseo_donate_url` filtresi.
- HTML yorumundaki eski ad "Flux SEO" oldu.

## 1.7.0
- **Arama motorları** bölümü (SEO › Genel): Google Search Console, Bing Webmaster Tools ve Yandex Webmaster için panel bağlantıları, adım adım ipucu ve doğrulama alanları; kopyalanabilir sitemap adresi. Bing'de "Google Search Console'dan içe aktar" yolu öne çıkarıldı (kod gerekmez). Tam `<meta>` etiketi yapıştırılabilir.
- **IndexNow ilk bildirim:** IndexNow açıkken site hiç toplu bildirilmediyse tüm site bir kez kendiliğinden Bing, Yandex, Seznam ve Naver'e gönderilir (cron'a bağlı değil; yönetim panelinde yanıt gönderildikten sonra). IndexNow yeniden açılınca ya da site arama motorlarına açılınca tekrarlanır.
- IndexNow toplu bildirimine kategori arşivleri eklendi; sepet, ödeme, Hesabım ve alt sayfaları (ör. order-received) "noindex" ayarı açıkken gönderilmez. Yeni filtre `flxseo_indexnow_all_urls`.
- Site denetimine yeni kontroller: robots.txt Bingbot/YandexBot/DuckDuckBot'u engelliyor (yüksek), robots.txt'de Sitemap satırı yok, IndexNow kapalı (tek tık düzeltme `flxseo_indexnow`), IndexNow anahtar dosyası açılmıyor ya da son bildirim hatalı, Bing/Yandex doğrulama kodu yok.
- Sistem Kontrolü'nde Bing ve Yandex doğrulama + IndexNow durumu. MCP `seo.setting` Yandex doğrulama kodunu da yazar.
- Arama motoru ve IndexNow metinleri çevrilebilir (TR + EN).

## 1.6.2
- Site denetimi yanlış uyarı vermiyor: `<template>` içindeki öğeler (ör. WooCommerce mini sepetinin şablonları) artık alt metinsiz görsel, adsız düğme, etiketsiz alan ve liste dışı `<li>` sayılmıyor; tarayıcı bunları çizmez.
- "İlk ekrandaki görsel lazy yükleniyor" kontrolü `skip-lazy`, `no-lazyload` sınıflarını ve `data-no-lazy`'yi hariç tutma işareti olarak tanır (önceden "no-lazyload" içindeki "lazy" yüzünden logoyu lazy sanıyordu).
- FluxWP imzası (flx-brand 1.1.1) koyu zeminde soluk değil.

## 1.6.1
- Metinler: MCP ekranı adresi "Flux AI & MCP › MCP".

## 1.6.0
- MCP araç adları önek aldı: `site_info` → `flxseo_site_info`, `list_content` → `flxseo_list_content` … `order_summary` → `flxseo_order_summary` (24 araç). Eski adlar alias olarak çalışmaya devam eder (Flux AI 0.8.0+): bağlı asistanlar ve araçla sınırlı anahtarlar bozulmaz. Talimatlar, istemler ve site yapım rehberi yeni adları kullanır.

## 1.5.1
- PageSpeed Insights isteği site adresini `Referer` olarak gönderir: "Web siteleri" (HTTP referrer) kısıtlı API anahtarları artık "Requests from referer <empty> are blocked" hatası vermez. Anahtarın izinli siteler listesinde sitenin adresi (ör. `example.com/*`) olmalı.

## 1.5.0
- **MCP sunucusu Flux AI'ya taşındı.** Sunucu, anahtarlar ve kayıtlar artık **Flux AI › MCP** ekranında (Flux AI 0.7.0+). Flux SEO yalnız kendi araçlarını (`flx_mcp_tools`), istemlerini (`flx_mcp_prompts`), kaynaklarını (`flx_mcp_resources`) ve talimatlarını (`flx_mcp_instructions`) kaydeder; adres ve araç adları aynı.
- Menü adı **SEO & MCP → SEO**; "Yapay zekâ (MCP)" sekmesi kaldırıldı, eski adres Flux AI › MCP'ye yönlenir. Eski anahtarlar Flux AI'ya kendiliğinden geçer.
- Flux AI kurulu değilse SEO ekranında ve Sistem Kontrolü'nde uyarı çıkar.

## 1.4.2
- Rapor geçmişinde iki grafik: öneri sayısı (önceliğe göre yığılmış, eksen ve açıklamalı) ve PageSpeed puanları (Performans, Erişilebilirlik, En iyi uygulamalar, SEO çizgileri; Mobil / Masaüstü seçmeli).
- Geçmiş tablosunda mobil ve masaüstü puanları + LCP birlikte.
- Yeni **SEO & MCP › Ayarlar** sekmesi: denetim ayarları (PageSpeed anahtarı, otomatik denetim, ajan uyumu, güvenlik başlıkları) ve yeni site yapım rehberi Site denetimi sekmesinden buraya taşındı.

## 1.4.1
- Site denetimi başka paketlerin tek tık düzeltmelerini de çalıştırır (`flx_system_fixes` › `current` / `run`). Flux Speed 0.5.0 kuruluysa engelleyen CSS, kullanılmayan CSS, sıkıştırılmamış CSS/JS, viewport yakınlaştırma kilidi, yazı tipleri ve emoji bulgularında "Flux Speed (tek tık)" düğmesi çıkar.

## 1.4.0
- **Rapor geçmişi**: her denetim (elle, otomatik, yapay zekâ, düzeltme sonrası) özetiyle saklanır (son 90). Panelde önceliğe göre yığılmış eğilim grafiği ve tablo: tarih, nasıl çalıştığı, öncelik sayıları, mobil PageSpeed puanları + LCP, önceki rapora göre yeni ve çözülen öneriler.
- **Otomatik denetim**: her gün ya da haftada bir (gün ve saat seçilir, sitenin saat dilimi), isteğe bağlı PageSpeed mobil + masaüstü dahil. Arka planda adım adım çalışır (PHP süre sınırına takılmaz); WP-Cron çalışmasa da ilk istekte başlar.
- Yeni kritik ya da yüksek öncelikli öneri çıkınca e-posta (adres seçilebilir, boşsa site yöneticisi).
- Yeni MCP aracı `site_audit_history` (geçmiş raporlar + takvim); Flux AI sohbetinde de var. `site_audit` her çalıştırmayı geçmişe yazar.

## 1.3.0
- Yeni **SEO & MCP › Site denetimi**: PageSpeed Insights'ın beş başlığı (Performans, Erişilebilirlik, En iyi uygulamalar, SEO, Ajan uyumu) önceliğe göre (kritik, yüksek, orta, düşük). Her öneride nasıl düzeltilir, sorunlu öğeler ve düzeltme yeri. Menüde kritik + yüksek sayısı, Sistem Kontrolü'nde "Site denetimi" satırı.
- Site içi denetim anahtarsız ve günde bir kendiliğinden: viewport yakınlaştırma kilidi, ARIA rolleri (tablist › tab, gerekli üst öğe), liste dışı `<li>`, adsız bağlantı/düğme/alan, yinelenen id, aynı başlıklı yayınlar ve aynı metinli farklı bağlantılar, engelleyen CSS/JS, DOM boyutu, boyutsuz ve lazy ilk görsel, font-display, güvenlik başlıkları, robots.txt, llms.txt, WebMCP.
- İsteğe bağlı PageSpeed Insights API ölçümü (mobil + masaüstü): Lighthouse puanları, Core Web Vitals ve tüm başarısız denetimler. API anahtarı panelden.
- Ajan uyumu: `/llms.txt` site adı, açıklama, menü sayfaları ve son içeriklerden kendiliğinden üretilir (varsayılan açık); arama formuna WebMCP `toolname` (varsayılan açık).
- İsteğe bağlı güvenlik başlıkları (varsayılan kapalı, tek tıkla açılır): X-Frame-Options + frame-ancestors, COOP same-origin-allow-popups, temel CSP, HSTS includeSubDomains/preload. Açıkken WordPress sürüm etiketi gizlenir.
- **Site yapım rehberi** (TR + EN Markdown): yeni sayfa, tema ve ön ayarlar için SEO, performans, erişilebilirlik, güvenlik ve ajan uyumu kuralları + yayın öncesi kontrol listesi. Panelden indirilir; MCP aracı `site_build_guide`, kaynak `flxseo://guide/site-build`.
- Yeni MCP araçları `site_audit` ve `site_build_guide`, istem `site_audit_fix`, MCP kaynakları (resources/list, resources/read). Flux AI sohbetine araç olarak kendiliğinden gelir; sohbet talimatı sayfa işinde önce rehberi okumasını söyler.
- Tek tık düzeltmeler `flx_system_fixes` ile (`flxseo_llms`, `flxseo_webmcp`, `flxseo_headers`, `flxseo_hsts`); Flux Setup ayar şemasına `flxseo` alanları.
- Çeviri: yeni metinler i18n (TR kaynak + `languages/flx-seo-en_US`).

## 1.2.12
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 1.2.11
- MCP ayarları: "(yalnız okuma)" etiketi açık zeminde okunur.

## 1.2.10
- Ana sayfada og:title artık site adı değil, sayfa başlığıyla aynı (sayfanın SEO başlığı ya da Ana sayfa başlığı ayarı, çevirisiyle). Yeni filtre `flxseo_og` (Open Graph etiketleri dizisi).

## 1.2.9
- Düzeltme: Flux Setup ön ayarı (ör. fluxwp.com) Flux SEO ayarlarından yalnız birkaçını yazınca kayıt filtresi yazılmayan tüm açık/kapalı ayarları 0 yapıyordu. SEO çıktısı, noindex kuralları, otomatik alt metin ve IndexNow birlikte kapanıyordu ("SEO çıktısı kapalı"). Artık gönderilmeyen alan eski değerini korur; ayar formu kapalı kutular için gizli 0 gönderir. Bu yüzden kapanmış sitelerde (hepsi 0) güncellemeden sonra ayarlar bir kez varsayılanlarına döner.
- Varsayılanlar siteden bağımsız: site adı, kurum adı, ana sayfa başlığı ve açıklaması boş gelir ve boşken WordPress'in site adı ve sloganı kullanılır. Ayar sayfası hiç açılmasa da her sayfada başlık, açıklama, Open Graph ve schema dolu çıkar. Eski "Örnek Vakfı" örnek değerleri ve onları yazan eski geçişler kaldırıldı.
- Sistem Kontrolü: SEO çıktısı kapalıysa hata değil uyarı (SEO isteğe bağlı). Başka bir SEO eklentisi etkinse bilgi satırı. Kurum bilgilerinde yalnız telefon/adres eksikse bilgi; e-posta eksikse uyarı.

## 1.2.8
- Favicon yenilendi: FluxWP işareti tam kare zemin yerine kendi şekliyle, beyaz kontur ve ince gölge çizgisiyle (açık ve koyu sekmede okunur). SVG, favicon.ico (16/32/48) ve 32/48/96 PNG. Kökteki önceki FluxWP favicon.ico kopyası da yenilenir. Ana ekran simgeleri (180/192/512) değişmedi.

## 1.2.7
- Site simgesi (apple-touch, 96/192/512): içteki FluxWP işareti büyütüldü; iOS simgesi köşesiz tam dolgu.

## 1.2.6
- Varsayılan favicon.ico eski yaprak simgesiydi; FluxWP simgesiyle değişti, SVG favicon eklendi. Kök dizindeki eski kopya güncellenir (sitenin kendi favicon.ico'suna dokunulmaz). Site simgesi seçildiyse o kullanılır.

## 1.2.5
- Kontrol listesindeki uyarı/hata renkleri koyu modda okunur.

## 1.2.4
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 1.2.3
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 1.2.2
- Web manifest `theme_color` tema paletinin ana rengi; `short_name` site adı (sabit metin kaldırıldı).

## 1.2.1

- Paylaşım görseli (og:image) artık 1200×630 JPEG kopyası (uploads/flxseo-og/, en fazla ~290 KB). WhatsApp önizlemesi WebP ve büyük görsellerde bulanık çıkıyordu. Kopya ilk ziyarette bir kez üretilir, görsel değişince yenilenir.
- og:image:type ve og:image:secure_url eklendi.

## 1.2.0

- IndexNow: yayınlanan, güncellenen ve yayından kalkan sayfalar Bing, Yandex, DuckDuckGo (Bing üzerinden), Seznam ve Naver'e anında bildirilir. Anahtar dosyası /<anahtar>.txt kendiliğinden sunulur. Ayarlar › SEO'da aç/kapa ve "Tüm siteyi şimdi bildir" düğmesi. Noindex ve parolalı sayfalar gönderilmez; aynı adres 10 dakikada bir.
- Filtre `flxseo_indexnow_urls( $urls, $post )`: Flux Lang gibi eklentiler bir yazının diğer dildeki adreslerini ekleyebilir.
- robots.txt'de Sitemap satırı yoksa eklenir.

## 1.1.1

- Google'daki site adı varsayılanı "Örnek Vakfı" (eski "Örnek Vakfı" değiştirilmediyse kendiliğinden güncellenir). Ayarlar › SEO › Google'da görünen site adı'ndan değiştirilebilir.
- WebSite schema'da alan adı (example.org) artık alternatif ad olarak verilmiyor; yerine kurum adı. Google'ın alan adını site adı diye göstermesine yol açıyordu.

## 1.1.0

- MCP anahtarları araç bazında sınırlanabilir: anahtar oluştururken "Araçları sınırla" altından seçilen araçlar dışında hiçbir araç görünmez ve çalışmaz. Seçim yapılmayan (eski) anahtarlar eskisi gibi çalışır. Anahtar tablosunda yeni "Araçlar" sütunu.
- Yeni MCP aracı `order_summary` (yalnız okuma, WooCommerce): bugünkü ödenmiş sipariş sayısı, ciro (iadeler düşülmüş), ortalama sepet, bekleyenler ve son siparişler. Müşteri için yalnız ad + soyadın baş harfi; adres, e-posta, telefon dönmez. TV sipariş paneli için.
- Sık yoklanan okuma araçları (`'log' => false`) Son işlemler listesini doldurmaz; hatalı çağrıları yine yazılır.
