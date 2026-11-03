# Değişiklikler

## 0.7.4
- Varsayılan favicon: site simgesi ve kurum profili simgesi yoksa tema da yeni FluxWP favicon'unu (zeminsiz, beyaz konturlu işaret) gösterir; Flux SEO ya da Flux Admin kuruluysa onlarınki kullanılır, çift yazılmaz. Filtre `flx_theme_favicon`.

## 0.7.3
- Sistem Kontrolü: Flux SEO artık isteğe bağlı. Kurulu ya da etkin değilse hata yerine uyarı gösterilir.

## 0.7.2
- Sistem Kontrolü › Eklentiler artık site türüne göre: Flux Donate ve Flux Certificates yalnız bağış sitesinde (Flux Donate kurulu ya da Site türü "Bağış") gerekli; Flux Commerce kuruluysa mağaza sayılır ve Flux Commerce gerekli olur. İkisi de değilse (ör. fluxwp.com) bağış/mağaza paketleri istenmez, WooCommerce isteğe bağlıdır.
- Bağış sitesi değilse iyzico satırı gösterilmez; cron, saat dilimi ve SMTP uyarıları sertifika/bağış yerine genel dille yazılır.
- Yeni filtre `flx_sk_site_kinds` (array( 'donate', 'store' ) içinden).

## 0.7.1
- Footer alt satırında `flx/credit` bloğu (logo + "Altyapı: FluxWP", fluxwp.com). Blok Düzenleyici'de silinirse footer'ın en altına ince bir imza şeridi eklenir (`add_theme_support( 'flx-credit' )`).
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `inc/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 0.7.0
- Yeni varsayılan renkler: antrasit düğmeler, mavi vurgu, açık bej sayfa zemini (yeşil yerine). Yeni palet rengi `base` (Sayfa zemini).
- Stil varyasyonları: Antrasit, Mavi, Petrol, İndigo, Orman (eski yeşil) (Görünüm › Düzenleyici › Stiller). Renkler aynı yerden tek tek de değiştirilebilir.
- CSS'teki sabit yeşil tonlar kaldırıldı: gölgeler, bantlar, video zemini, menü deseni ve koyu mod renkleri paletten türetilir. Paleti değiştiren her şey (stil, ön ayar, Site Editor) siteyi baştan sona boyar.
- Ön ayarlar türetilmiş tonları `settings.custom.color` (deep, band-mid, band-end, media, tint) ve `settings.custom.dark` (bg, surface, ink, text, primary, mint, mint-soft, line, link) ile sabitleyebilir. Antrasit koyu modda mavi düğme kullanır (`custom.dark.primary`).
- Palet adları genelleşti (Ana renk, Vurgu, Başlık…), slug'lar aynı.
- Demo ön ayarı (flux-demo 0.2.0) FluxWP logosu, koyu zemin logosu ve site simgesiyle kurulur.

## 0.6.0
- Tema `fluxwp` (FluxWP): genel, sade WooCommerce teması. Derneğe özel desenler, görseller, logolar, proje/etkinlik şablonları ve Site Kurulumu Örnek Preset ön ayar eklentisine taşındı.
- Yeni genel header, footer, ana sayfa (hero, özellikler, ürünler, yazılar) ve demo ön ayarı (4 ürün, sayfalar).
- Tema Ayarları › Ön ayarlar sekmesi (Flux Setup ekranı burada). Site türü: Mağaza / Bağış.
- Temayı taşı: eski temanın Site Editor kayıtları yeni temaya kopyalanır.
- Logo yoksa site adı gösterilir; logo kurum profilinden ya da `flx_logo_files` filtresinden.
- `#wc-*` link yer tutucuları; şablon ve desenlerde `[products]` kısa kodu; ürün ızgarası.
- Yeni filtreler: `flx_img_dirs`, `flx_i18n_domains`, `flx_search_placeholder`, `flx_search_chips`, `flx_account_cta`, `flx_logo_files`, `flx_tasi_sources`.
