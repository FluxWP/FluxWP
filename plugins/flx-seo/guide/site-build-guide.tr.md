# FluxWP site yapım rehberi: SEO, performans, erişilebilirlik, güvenlik ve ajan uyumu

Bu rehber yeni bir WordPress sitesi, tema, ön ayar (flx-preset-*), blok deseni ya da sayfa yaparken uyulacak kuralları toplar. Hedef PageSpeed Insights'ta dört kategoride **100** ve Ajan uyumunda **tam puan**. Yapay zekâ ajanları bu rehberi Flux SEO MCP sunucusundan `flxseo_site_build_guide` aracıyla (ya da `flxseo://guide/site-build` kaynağıyla) alır; Flux AI sohbeti aynı aracı kullanır.

<!-- topic:workflow -->
## 1. Çalışma akışı (ajanlar için)

1. İşe başlamadan bu rehberi oku: `flxseo_site_build_guide` (konu: `all` ya da ilgili başlık).
2. `flxseo_site_info` ile siteyi, `flxseo_list_patterns` ile temanın hazır bölümlerini gör. Yeni bölüm yazmadan önce var olanı kullan.
3. Sayfayı/temayı bu rehberdeki kurallarla yap. İçerik taslak (draft) oluşturulur; yayın kullanıcının onayıyla.
4. Bitince `flxseo_site_audit` aracını `refresh: true` ile çalıştır. Bulgular **kritik → yüksek → orta → düşük** sırasıyla gelir; aynı sırayla düzelt.
5. Kullanıcı isterse `flxseo_site_audit` ile `pagespeed: "mobile"` (sonra `"desktop"`) çalıştırıp gerçek Lighthouse puanını al. Bir ölçüm 15–40 sn sürer.
   Her çalıştırma saklanır; `flxseo_site_audit_history` geçmiş raporları, eğilimi ve çözülenleri gösterir (düzeltmenin kalıcı olduğunu doğrulamak için).
6. `fix_id` alanı dolu bulgular tek tıkla düzelir: `flxseo_system_fix` aracına bu kimliği ver (önce kullanıcıya ne açılacağını söyle).
7. Düzeltmeden sonra önbelleği boşalt (LiteSpeed › Purge All) ve denetimi yeniden çalıştır.

### Öncelikler

| Öncelik | Anlamı | Örnek |
|---|---|---|
| Kritik | Site Google'da görünmez ya da açılmaz | noindex, robots.txt `Disallow: /`, HTTP 500, viewport yok, LCP > 4 sn |
| Yüksek | Puanı doğrudan düşürür, kullanıcıyı engeller | user-scalable=no, bozuk ARIA, alt metinsiz görsel, meta açıklama yok |
| Orta | Ölçülebilir kayıp, kısa sürede düzelir | engelleyen CSS, kopya sayfa, CSP/XFO yok, llms.txt yok |
| Düşük | İnce ayar | JS minify, HSTS preload, Trusted Types |

<!-- topic:performance -->
## 2. Performans

**Hedefler (mobil, Slow 4G):** LCP < 2,5 sn · CLS < 0,1 · TBT < 200 ms · FCP < 1,8 sn · Speed Index < 3,4 sn · HTML < 100 KB · toplam sayfa < 500 KB · DOM < 1.500 öğe.

### CSS
- İlk ekran için gereken kritik CSS'i (`<head>` içinde) satır içine al; sayfanın geri kalanının CSS'ini ertele: `<link rel="stylesheet" href="…" media="print" onload="this.media='all'">` + `<noscript>` yedeği.
- Tek büyük CSS dosyası yerine sayfa türüne/bloğa göre böl (`wp_enqueue_block_style`, `theme.json` stilleri). Kullanılmayan CSS 20 KB'ı geçmesin.
- `@import` kullanma; zincir oluşturur.

### Yazı tipleri
- En fazla 2 aile, ilk ekranda kullanılan ağırlıklar. Değişken font tek dosya.
- `@font-face { font-display: swap; }` her zaman. İlk ekran fontunu önden yükle: `<link rel="preload" href="…/font.woff2" as="font" type="font/woff2" crossorigin>`.
- latin + latin-ext alt kümeleri ayrı dosya, `unicode-range` ile.
- Font değişiminde kayma olmasın: `size-adjust`, `ascent-override` ile yedek font ayarla.
- Fontları yerelde barındır (Google Fonts isteği zinciri uzatır).

### LCP öğesi
- LCP çoğunlukla hero başlığı ya da hero görseli. Görselse: `loading="eager"`, `fetchpriority="high"`, doğru `srcset`/`sizes`, WebP/AVIF. **LiteSpeed lazy load ilk görseli hariç tutmalı** (`data-no-lazy="1"` ya da "Lazy Load Image Excludes").
- Metinse: giriş animasyonunu (kelime kelime belirme, opacity 0'dan başlama) ilk karede çalıştırma; LCP, öğe görünene kadar bekler. Animasyon gerekiyorsa son durumdan başlat ya da yalnız `transform` ile kaydır.

### Görseller
- Her `<img>` için `width` ve `height` (ya da CSS `aspect-ratio`). WordPress `wp_get_attachment_image()` bunu verir; elle `<img>` yazma.
- İlk ekran dışındaki görseller `loading="lazy"`, `decoding="async"`.
- Görünenden büyük görsel gönderme; `srcset`/`sizes` kullan. Süs görsellerini CSS arka planı ya da SVG yap.

### JavaScript
- Tüm betikler `defer` ya da `type="module"`; `<head>` içinde senkron betik yok. `wp_enqueue_script( …, array( 'strategy' => 'defer', 'in_footer' => true ) )`.
- Yayında `.min.js`. Açılışta tek uzun görev olmasın (> 50 ms); işi `requestIdleCallback`/`IntersectionObserver` ile böl.
- Zorunlu yeniden yerleşimden kaçın: önce oku (`getBoundingClientRect`), sonra yaz; yazmaları `requestAnimationFrame` içinde topla.
- jQuery'ye ihtiyaç yoksa yükleme. Emoji betiğini kaldır.

### Animasyon
- Yalnız `transform` ve `opacity` animasyonu (GPU'da çalışır). `stroke-dashoffset`, `top/left`, `width/height`, `filter`, `box-shadow` animasyonu yapma; SVG çizgi animasyonu gerekiyorsa `transform: scaleX()` + `clip-path` dene.
- `@media (prefers-reduced-motion: reduce)` içinde tüm hareketi kapat.
- Sonsuz kayan şeritlerde (marquee) DOM kopyalarını en az tut; CSS `animation` + tek kopya + `aria-hidden` ikinci kopya.

### DOM
- 1.500 öğe altı; iç içe derinlik 32 altı; bir öğenin en fazla 60 çocuğu.
- Satır içi SVG ikonları tek `<svg><symbol>` sprite'ta topla (`<use href="#i-x">`); her ikonu tekrar tekrar gömme.
- Mobilde görünmeyen menü/mega menüleri ihtiyaç olunca oluştur.

### Sunucu ve önbellek
- Sayfa önbelleği açık (LiteSpeed Cache). TTFB < 600 ms. Flux Speed raporunda yavaş eklentileri izle.
- gzip/brotli açık. Statik dosyalar 1 yıl önbellek (Flux SEO `.htaccess` kurallarını yazar), sürüm parametresiyle (`?ver=`) yenile.
- Üçüncü taraf betikleri (sohbet, analitik, harita) etkileşimde ya da `load` sonrasında yükle.

<!-- topic:accessibility -->
## 3. Erişilebilirlik

### Viewport ve dil
- `<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">`. **Asla `user-scalable=no` ya da `maximum-scale=1` kullanma**; puanı doğrudan düşürür. iOS'ta input odakta yakınlaşmasını önlemek için input yazı boyutunu en az 16px yap.
- `<html <?php language_attributes(); ?>>`; çok dilli sayfada farklı dildeki bloklara `lang`.

### Yapı
- Tek `<header>`, `<nav aria-label>`, `<main id="main">`, `<footer>`. "İçeriğe atla" bağlantısı ilk odaklanabilir öğe.
- Başlıklar sırayla: sayfada tek H1, sonra H2 → H3. Görünüm için seviye atlama; CSS ile boyutlandır.
- Liste görünümlü içerik `<ul>/<ol>` + `<li>`. **Listeye liste dışı rol verme** (`<ol role="tablist">` hatalı).

### ARIA desenleri (doğru örnekler)
Sekmeler:
```html
<div role="tablist" aria-label="Kurulum adımları">
  <button role="tab" id="step-1" aria-selected="true" aria-controls="scene-1">1. İndir</button>
  <button role="tab" id="step-2" aria-selected="false" aria-controls="scene-2" tabindex="-1">2. Yükle</button>
</div>
<div role="tabpanel" id="scene-1" aria-labelledby="step-1">…</div>
```
Numaralı adım görünümü isteniyorsa ya sekme rolü kullanma (düz `<ol>` + `<li>` + `<button aria-pressed>`), ya da `<ol role="tablist">` içinde `<li role="presentation">` kullan.
- `role="tab"` yalnız `role="tablist"` içinde; `role="option"` yalnız `role="listbox"` içinde; `role="menuitem"` yalnız `role="menu"` içinde.
- Bozuk ARIA hiç ARIA'dan kötüdür. Yerel öğe varsa (button, a, details, dialog) onu kullan.
- `aria-controls`, `aria-labelledby`, `label for` var olan bir `id`'yi göstermeli; `id`'ler tekil.

### Adlar ve formlar
- Her bağlantı ve düğmenin adı olsun. Simge düğmesi: `<button aria-label="Menüyü aç"><svg aria-hidden="true">…</svg></button>`.
- Aynı metinli bağlantılar aynı yere gitsin ("Daha fazla" × 10 farklı hedef yerine "Projeler hakkında daha fazla").
- Her form alanına `<label for>`; placeholder etiket değildir. Hata mesajı `aria-describedby` ile bağlı, `aria-live="polite"`.
- Görsellere anlamlı `alt`; süs görseli `alt=""`. SVG ikon `aria-hidden="true"`.

### Görünüm
- Karşıtlık: normal metin ≥ 4.5:1, büyük metin ve simgeler ≥ 3:1 (koyu modda da).
- Dokunma hedefi ≥ 24×24 px (tercihen 44×44), aralarında boşluk.
- Odak görünür: `:focus-visible { outline: 2px solid; outline-offset: 2px }`; `outline: none` yasak.
- Bilgi yalnız renkle verilmesin; bağlantılar metin içinde altı çizili.
- Klavyeyle her şey yapılabilsin; odak tuzağı yok; modal açılınca odak içeri, kapanınca geri.

<!-- topic:seo -->
## 4. SEO

- `<title>`: ≤ 60 karakter, "Sayfa konusu – Site adı". Ana sayfa başlığı Flux SEO › Genel'de.
- Meta açıklama: 120–160 karakter, sayfaya özel, eyleme çağıran.
- Her sayfada tek `rel=canonical` (Flux SEO verir; tema ya da başka eklenti ikinci kez eklemesin).
- Çok dilli: Flux Lang hreflang ve `x-default` üretir; dil sürümleri birbirine bağlı olsun.
- Schema (JSON-LD): Organization/NGO + WebSite (Flux SEO), BreadcrumbList, Article/NewsArticle, Product, Event, FAQPage. Geçerli JSON, görünen içerikle aynı bilgi.
- `robots.txt` WordPress'in ürettiği; `Sitemap:` satırı var. Sepet, ödeme, hesabım, site içi arama ve etiket sayfaları noindex (Flux SEO ayarı).
- Kopya içerik yok: aynı başlıklı iki yayın ("…-2" adresli kopya sayfa) bırakma; silip yönlendir.
- Adresler kısa, Türkçe karakterleri sadeleştirilmiş, anlamlı (`/fidan-bagisi/`).
- İç bağlantı: her yazıda 2–5 ilgili sayfaya açıklayıcı metinli bağlantı. "Tıklayın", "devamı" yok.
- Görseller: dosya adı anlamlı, `alt` dolu, paylaşım görseli 1200×630 (Flux SEO JPEG kopyasını üretir).
- Yeni ya da güncellenen sayfalar IndexNow ile Bing/Yandex'e bildirilir; Google için Search Console'a sitemap ekle.
- İçerik: H2 ara başlıklarla 300+ kelime, ilk paragrafta odak kelime, SSS bölümü (FAQPage) arama niyetine yanıt verir.

<!-- topic:best-practices -->
## 5. En iyi uygulamalar ve güvenlik

- Tümüyle HTTPS; karışık içerik (http:// görsel/betik) yok.
- `<!DOCTYPE html>` ve ilk satırlarda `<meta charset="UTF-8">`.
- Konsolda hata yok; kullanımdan kalkmış API yok; üçüncü taraf çerez yok; sayfa açılışında konum/bildirim izni istenmez; şifre alanına yapıştırma serbest.
- Güvenlik başlıkları (Flux SEO › Site denetimi › Ayarlar'dan tek tık):
  - `Strict-Transport-Security: max-age=31536000; includeSubDomains` (tüm alt alan adları HTTPS ise `; preload`).
  - `X-Frame-Options: SAMEORIGIN` + CSP `frame-ancestors 'self'` (site başka sitede iframe ile gösterilmiyorsa).
  - `Cross-Origin-Opener-Policy: same-origin-allow-popups` (ödeme/giriş açılır pencereleri çalışır).
  - `Content-Security-Policy: object-src 'none'; base-uri 'self'; upgrade-insecure-requests`. Tam `script-src` politikası (nonce/strict-dynamic) özel temalı, az eklentili sitelerde önce `Content-Security-Policy-Report-Only` ile denenir.
  - `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` (Flux SEO varsayılan gönderir).
- `meta generator` ile WordPress sürümünü gösterme.
- Görsellerin oranı doğru, çözünürlüğü ekran yoğunluğuna uygun.

<!-- topic:agentic -->
## 6. Ajan uyumu (Agentic browsing)

Yapay zekâ ajanları (tarayıcı ajanları, arama asistanları) siteyi erişilebilirlik ağacı, yapılandırılmış veri ve `llms.txt` üzerinden anlar.

- **llms.txt**: Flux SEO `/llms.txt`'yi kendiliğinden üretir. Biçim: ilk satır `# Site adı`, ardından `> kısa özet`, isteğe bağlı açıklama paragrafı, `## Bölüm` başlıkları altında `- [Başlık](adres): açıklama` listeleri, sonda `## İsteğe bağlı`. Kökte elle konmuş eski bir `llms.txt` varsa sil ya da güncel tut.
- **Erişilebilirlik ağacı düzgün olsun**: yukarıdaki ARIA kuralları ajan puanını da belirler (bozuk `tablist` ajan uyumunu 3/3'ten 2/3'e düşürür).
- **Kararlı yerleşim**: CLS < 0,1. Ajan tıklarken öğe kaymamalı.
- **WebMCP**: tarayıcıdaki ajanlar formları araç olarak çağırabilir. Bildirim tabanlı biçim:
  ```html
  <form action="/" toolname="search_site" tooldescription="Sitede arama yapar">
    <input name="s" toolparamdescription="Aranacak kelimeler">
  </form>
  ```
  Flux SEO arama formuna kendiliğinden ekler. İletişim, bağış, bülten formlarına da kısa, eylem bildiren `toolname` (snake_case) ve açıklama ver. Ödeme ve kişisel veri formlarında ajan kullanıcı onayı olmadan göndermemeli; açıklamada belirt.
- **Anlamlı HTML**: düğme `<button>`, bağlantı `<a href>`; `div onclick` yok. Görünür metin ile erişilebilir ad aynı.
- **Yapılandırılmış veri**: Organization, ürün, etkinlik, SSS schema'sı ajanların doğru yanıt vermesini sağlar.
- **robots.txt**: GPTBot, ClaudeBot, Google-Extended, PerplexityBot gibi tarayıcıları bilerek engellemiyorsan açık bırak.
- **MCP**: site yönetimi için Flux SEO MCP sunucusu (anahtarla) kullanılır; anahtar herkese açık dosyalara yazılmaz.

<!-- topic:wordpress -->
## 7. WordPress ve FluxWP kuralları

- Tema: blok tema, `theme.json` ile palet/tipografi; derneğe/markaya özel her şey ön ayar eklentisinde (flx-preset-*), temada değil.
- Stil ve betikler `wp_enqueue_*` ile; blok başına stil `wp_enqueue_block_style`; `<head>`'e elle `<link>` basma.
- Desenler (`patterns/`): metinler `__()` / `esc_html__()` ile, TR varsayılan + EN çeviri; sabit Türkçe yok. Görseller `width/height` ile.
- Tarih ve "… önce" metinleri `flx_date()` / `flx_time_ago()` ile.
- Ayarlar wp-config yerine panelden; ön ayar `flx_settings_schema` alanlarıyla yazar (Flux SEO alanları: `flxseo.*`).
- AI kullanan her kod ortak Flux AI eklentisi üzerinden.
- Yeni sayfa için önce mevcut desenleri kullan; yeni CSS yazarsan sadece o bloğun stil dosyasına.
- Erişilebilir bileşen kütüphanesi: sekme, akordeon (`<details>`), modal (`<dialog>`), kaydırıcı (düğmeli, otomatik oynatmasız ya da duraklatılabilir).

<!-- topic:checklist -->
## 8. Yayın öncesi kontrol listesi

- [ ] Flux SEO › Site denetimi: kritik ve yüksek bulgu yok.
- [ ] PageSpeed mobil ve masaüstü: Performans ≥ 90 (hedef 100), Erişilebilirlik 100, En iyi uygulamalar 100, SEO 100, Ajan uyumu tam.
- [ ] Viewport'ta `user-scalable=no` / `maximum-scale` yok.
- [ ] ARIA rolleri doğru (tablist › tab, listbox › option); `<li>` yalnız listede.
- [ ] Aynı başlıkta iki yayın yok; "-2" kopya sayfalar silindi/yönlendirildi.
- [ ] İlk ekran görseli lazy değil, `fetchpriority="high"`; tüm görsellerde width/height ve alt.
- [ ] Kritik CSS satır içi, geri kalanı ertelenmiş; JS `defer`; fontlar preload + swap.
- [ ] Animasyonlar yalnız transform/opacity; reduced-motion'da kapalı.
- [ ] DOM < 1.500 öğe; ikonlar sprite'ta.
- [ ] Başlık ≤ 60, açıklama 120–160 karakter; canonical tek; schema geçerli; hreflang (çok dilliyse).
- [ ] `/llms.txt` açılıyor ve güncel; arama formunda WebMCP `toolname` var.
- [ ] HTTPS, HSTS, X-Frame-Options/frame-ancestors, COOP, temel CSP açık (iframe ile gösterim gerekmiyorsa).
- [ ] Koyu modda karşıtlık ve odak görünür.
- [ ] LiteSpeed › Purge All, ardından denetimi tekrar çalıştır.
