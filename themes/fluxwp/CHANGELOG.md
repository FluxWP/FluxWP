# Değişiklikler

## 0.7.0
- Yeni varsayılan renkler: FluxWP indigo paleti (yeşil yerine). Yeşil görünüm "Orman" stil varyasyonu olarak duruyor.
- Stil varyasyonları: İndigo, Gece, Mor, Orman (Görünüm › Düzenleyici › Stiller). Renkler aynı yerden tek tek de değiştirilebilir.
- CSS'teki sabit yeşil tonlar kaldırıldı: gölgeler, bantlar, video zemini, menü deseni ve koyu mod renkleri paletten türetilir. Paleti değiştiren her şey (stil, ön ayar, Site Editor) siteyi baştan sona boyar.
- Ön ayarlar türetilmiş tonları `settings.custom.color` (deep, band-mid, band-end, media, tint) ve `settings.custom.dark` (bg, surface, ink, text, primary, mint, mint-soft, line, link) ile sabitleyebilir.
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
