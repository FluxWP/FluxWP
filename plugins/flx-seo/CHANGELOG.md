# Değişiklikler

## 1.4.1
- Rapor geçmişinde iki grafik: öneri sayısı (önceliğe göre yığılmış, eksen ve açıklamalı) ve PageSpeed puanları (Performans, Erişilebilirlik, En iyi uygulamalar, SEO çizgileri; Mobil / Masaüstü seçmeli).
- Geçmiş tablosunda mobil ve masaüstü puanları + LCP birlikte.

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
