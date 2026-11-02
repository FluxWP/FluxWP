---
name: flx-wp-tema
description: WordPress block theme yazarken ya da FluxWP/fluxwp themes altındaki bir temayı değiştirirken kullan; theme.json, pattern, i18n, dark mode, Site Kurulumu migration'ları, WooCommerce ve önbellek tuzakları.
---

# Flux WordPress block theme yazma

Referans temalar: `themes/fluxwp` (genel WooCommerce teması; kuruma özel desenler FluxWP/wp-extras ön ayarlarında).
Yeni tema yazarken sıfırdan başlama: bu ikisinin `inc/` altyapısını (dil, ayarlar, kurulum, önbellek, logo, hız, erişim,
sistem) kopyalayıp prefix/text domain'i değiştir. Paket kuralları ve yayın için `flx-wp-eklenti` ve `flx-paket-yayin` skill'lerine bak.

## 1. Mimari kararlar

- **Standalone block theme** (child theme değil), her bölüm Site Editor'den düzenlenebilir olmalı. Kullanıcı içeriği
  panelden düzenler; PHP'ye gömülü sabit içerik yok.
- Düzen: `style.css` (başlık + `Version:`), `theme.json` (v3), `functions.php` (sabit `<PREFIX>_VERSION`, `require inc/*.php`),
  `templates/*.html`, `parts/header.html|footer.html`, `patterns/*.php`, `assets/{css,js,images,fonts,vendor}`, `languages/`, `woocommerce/` (gerekirse override).
- Ana sayfa `templates/front-page.html` yalnız pattern referansları; her bölüm `patterns/<ad>.php` (başlıkta `Title/Slug/Categories/Description`).
  Görsel yolu PHP helper ile (`flx_img( 'hero-1.webp' )`), fontlar ve vendored kütüphaneler yerel (CDN yok, Swiper `assets/vendor`).
- Dinamik içerik Query Loop + kategori/CPT ile (ör. `haberler`, `calismalar`, CPT `proje`, `etkinlik`). Kategoriler tema aktivasyonunda oluşturulur.
- Ayarlar ekranı: Görünüm › **Tema Ayarları** (`inc/ayarlar.php`; çerez banner'ı, GA4, dark mode, banka hesapları vb.). Anahtar/kimlik wp-config'e değil buraya.
- Simgeler block style (`is-style-icon-leaf` gibi), özel davranış sınıf ile (`.flx-slider` içindeki doğrudan çocuklar = slayt, `.flx-copy` = kopyala butonu).
- Fiyat, IBAN, adres, telefon, istatistik **uydurulmaz**; ayardan gelir, boşsa "yakında açıklanacak" gibi nötr metin.
  Doğrulanmamış iddia (kurum adı, ortaklık) metne konmaz.

## 2. Block markup

- Pattern/template markup'ı geçerli blok serileştirmesi olmalı; yoksa editörde "Bu blok beklenmeyen içerik içeriyor" çıkar.
  Değiştirdikten sonra `@wordpress/blocks` ile validate et (npm; wordpress.org ağdan kapalı olabilir, Playground için npm `wordpress-builds`/`@wp-playground` paketleri).
- Blok yorumundaki attribute'lar ile HTML sınıf/stil birebir aynı olmalı (`"className":"x"` ↔ `class="... x"`, `style.spacing` ↔ inline style).
- `wp:html` bloğu kullanıcı düzenlemesini zorlaştırır; mecbur değilse kullanma.

## 3. Veritabanındaki kopyalar (en sık tuzak)

Kullanıcı Site Editor'de bir template/part'ı kaydettiyse artık DB'deki `wp_template`/`wp_template_part` kullanılır;
temadaki dosyayı güncellemek canlıya **yansımaz**. Kurulumda oluşturulan sayfalar da DB'de.

- Çözüm: Görünüm › **Site Kurulumu** › "Hazır güncellemeler" (`inc/kurulum.php` → `flx_kurulum_migrations()`):
  her değişiklik tek tıkla uygulanan, idempotent bir migration (`id => {title, desc, run}`), uygulananlar `flx_migrations` seçeneğinde tarihle.
  Sayfa/part/header'ı tema sürümüne döndüren, eski içeriği revizyonda bırakan migration'lar yaz. Kullanıcı değiştirdiyse (md5 karşılaştır) sessizce ezme.
- Kurulumda medya kütüphanesine kopyalanan sayfa görselleri, tema görseli değişince "Sayfa görsellerini güncelle"
  migration'ı ister (dosya md5 karşılaştır → yeni attachment → içerikte URL replace).
- Header DB'de özelleştirildiyse yeni logo görünmez: KURULUM'da "Desenler › Header › ⋮ › Özelleştirmeleri temizle" yaz.
- Kendi kendini onaran şeyler: CPT arşivi 404 verirse rewrite flush'ı bir kez yap (`/projeler/` örneği).

## 4. i18n (TR kaynak, EN çeviri)

- PHP metinleri `__( '…', '<textdomain>' )`, `load_theme_textdomain` `after_setup_theme` öncelik 1.
- Template/part/pattern'deki sabit metinler **render anında** çevrilir (`inc/dil.php`): `render_block_data` ile
  `label/buttonText/placeholder/...` attribute'ları, `render_block` ile metin düğümleri `translate( $metin, domain )`.
  Böylece blok yapısı ve düzenlenebilirlik bozulmaz; kullanıcının değiştirdiği metin eşleşmez, olduğu gibi kalır.
  Yalnız site dili Türkçe değilken ve admin dışında çalışır.
- `.pot` + `en_US.po/.mo` (+ `l10n.php`), pattern metinleri de pot'a girmeli (script ile topla).
- Dil seçici bloğu TranslatePress/Polylang/WPML'den hangisi varsa onu kullanır, tek dilde gizlenir.
- KVKK/Çerez gibi hukuki metinler çeviriye bilinçli olarak konmaz (README'ye yaz).
- WooCommerce'in İngilizce kalan sayfa başlıkları (My account/Cart/Checkout) için migration: Hesabım/Sepet/Ödeme + 301.

## 5. Dark mode

- Ayar: Tema Ayarları › Dark mode `auto | off`. `auto` ise `body` sınıfı `flx-dark` eklenir; renkler
  `@media (prefers-color-scheme: dark)` içinde `body.flx-dark { --wp--preset--color--*: …; --flx-*: … }` ile ezilir.
  `html:has(body.flx-dark){background:…;color-scheme:dark}` ile overscroll beyaz kalmaz.
- `<meta name="color-scheme" content="light dark">` (off ise `only light`) + `<meta name="darkreader-lock">`
  (Dark Reader vb. siteyi ikinci kez koyulaştırmasın).
- Logo: dark'ta beyaz sürüm için CSS `content:url()` swap gecikiyor; `<picture><source media="(prefers-color-scheme: dark)">` kullan.
- Eklentiler (Flux Donate/Form) `--flx-*` değişkenlerini okur, yoksa `--wp--preset--color--*`'a düşer; `flxb_front_dark_mode`/`flxf_front_dark_mode`
  filtreleriyle tema ayarına göre `'class' | 'auto' | 'off'` döndür.

## 6. CSS ve JS

- `assets/css/theme.css` kaynak, `theme.min.css` varsa o yüklenir: **CSS değişince min'i yeniden üret** (yoksa değişiklik görünmez).
  Editörde de aynı stil (`add_editor_style`). Sürüm sabiti cache-busting için `wp_enqueue_style` versiyonu.
- JS `defer` + footer, tek `main.js` (slider init, kopyala butonları, kaydırma animasyonları, sekmeler). Swiper yalnız slider olan sayfada.
- FOUC: Swiper başlamadan `.flx-slider:not(.swiper) > :not(:first-child){display:none}` (editörde değil).
- Kaydırma animasyonu IntersectionObserver ile; ilk ekran gizlenmez, bitince sınıf kaldırılır (hover transform bozulmasın), ayardan kapatılabilir.
- Eşit kart boyu için `:has()` ve grid; 601–1080 px arası header menüsü hamburger.
- View Transitions (`@view-transition{navigation:auto}`) ve header'a `view-transition-name`.
- **Speculation Rules**: `prerender + moderate` paylaşımlı hostingde PHP işçi limitini doldurup siteyi kilitledi;
  yalnız `prefetch + conservative`, hesap/sepet/ödeme/sorgu parametreli adresler hariç (`inc/hiz.php`).

## 7. Performans ve önbellek

- Tema güncellenince ve header/footer/template/menü kaydedilince tüm sayfa önbelleğini temizle (`inc/onbellek.php`:
  `litespeed_purge_all`, WP Rocket, W3TC, Super Cache, SiteGround, Fastest Cache, Autoptimize, Breeze, Cloudflare eklentisi).
- Logo ve LCP görseli: `loading="eager"`, `fetchpriority="high"`, `data-no-lazy`/`skip-lazy` + LiteSpeed lazy exclude filtresi, font + logo `preload`.
- Görseller WebP, `alt` zorunlu (otomatik alt'a sayfa başlığı yazma), dekoratif görsel `alt=""`.
- YouTube: önce kapak + oynat düğmesi, tıklayınca iframe (lite embed), altında "YouTube'da izle" yedek link.

## 8. WooCommerce

- `add_theme_support( 'woocommerce' )` + galeri desteği; WC block template'leri temanın header/footer'ını kullanır.
- WC'nin kendi CSS'i tema düzenini bozar (ör. `.woocommerce-account .woocommerce` clearfix `::before/::after`, blok tema menü `li` padding 1em).
  Override'ları yeterli özgüllükle yaz, testte **gerçek WC CSS'ini** yükle (mock değil).
- Hesabım: kendi pano şablonu `woocommerce/myaccount/dashboard.php`, ikonlu menü (mobilde kaydırmalı sekme), "Siparişler" → "Bağışlar".
  Hesap ve mini sepet header bloğu WC kapalıysa görünmez.
- Bağış sayfası içerikleri Flux Donate blok/kısa kodlarıyla (`flx-wc-bagis/secenekler`, `[flx_bagis_secenekleri tur=…]`, `flxb_donate_url()`);
  tema fiyat/ödeme mantığı içermez, eklenti yoksa "yakında" kartı.
- Kayıt formuna ek alan (telefon) → `billing_phone` meta, normalize (`flxs_normalize_phone` varsa).

## 9. Sistem kontrolü ve SEO

- Görünüm › **Sistem Kontrolü** (`inc/sistem.php`): gerekli eklentiler (kurulu/etkin/min sürüm), gerçek cron
  (DISABLE_WP_CRON + son çalışma + geciken görevler), HTTPS, saat dilimi (canlıda +00:00 çıktı, İstanbul olmalı), SMTP,
  sayfa önbelleği, gerekli sayfalar, para birimi, uygulanmamış hazır güncellemeler. Eklentiler `flx_system_checks` ile aynı ekrana satır ekler;
  sözleşme `flx-wp-eklenti` skill'inde. Tema düzeltmeleri `flx_system_fixes` (`site.timezone`, `theme.migration`, `theme.setting` …).
- SEO meta/JSON-LD temada değil Flux SEO & MCP eklentisinde. Tema yalnız semantik başlık hiyerarşisi (tek H1), `aria-label`'lar
  (logo, arama, slider düğmeleri) ve `inc/erisim.php` düzeltmeleri.
- Çerez banner'ı + GA4 Consent Mode v2: onaya kadar çerez yok; "ek kodlar" yalnız Kabul sonrası; footer'da "Çerez tercihleri".

## 10. Test

- Playground/yerel WP + SQLite'ta: ana sayfa, tüm sayfa şablonları, tek yazı/proje/etkinlik, arşiv, arama, 404, Hesabım, sepet, ödeme;
  masaüstü + mobil (390 px) + dark ekran görüntüsü (Playwright). Editörde pattern'ler "geçersiz blok" uyarısı vermemeli.
- `bin/test-update.sh themes/<slug> dist/<slug>.zip [önceki.zip]` (fatal/500 yok). Ayrıntı `flx-paket-yayin`.
- Teslim: zip + README/KURULUM.md + `onizleme/` görselleri `/mnt/project-files/<tema-klasörü>/` altında; README'de
  "canlıya uygulamak için şu hazır güncellemeyi çalıştırın" satırı.

## Kontrol listesi

- [ ] Tüm bölümler pattern, editörden düzenlenebilir, markup validate
- [ ] DB'deki kopyaları güncelleyen migration eklendi (gerekiyorsa)
- [ ] Metinler TR kaynak + EN çeviri, render-time çeviri bozulmadı
- [ ] Dark mode (auto/off), color-scheme + darkreader-lock, logo `<picture>`
- [ ] theme.min.css yeniden üretildi, sürüm sabiti = style.css Version
- [ ] Önbellek temizleme, LCP/logo eager, speculation conservative
- [ ] WC sayfaları gerçek WC CSS'iyle kontrol edildi
- [ ] Sistem kontrolü satırları ve yeni hazır güncellemeler README'de
