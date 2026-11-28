# Changelog

## 0.5.5
- Yönetim ekranları için dil katalogları güncellendi.


## 0.5.4
- Ekran başlığı sol menüdeki adı gösterir.

## 0.5.3
- Uyarı rozetlerinin (sekme güncelleme sayacı, yavaş istek işareti) içindeki yazı beyaz; koyu modda rozet zemini daha koyu amber. Ortak modül paketi yenilendi.

## 0.5.2
- Panel yüzeyleri (düğme, etiket, kenarlık) krem yerine nötr gri; ortak modül paketi yenilendi.

## 0.5.1
- Ekran ortak FluxWP kabuğuna taşındı: başlık, simge, sekmeler, kenar boşlukları, en fazla genişlik ve güvenli alan (safe area) bütün Flux ekranlarında aynı. Sekmeler eski Flux Admin sekmeleri gibi alt çizgili; taşarsa sayfa değil sekme çubuğu kayar. Ortak modül paketi yenilendi.

## 0.5.0
- **Klasik pano kaldırıldı.** Blok Düzenleyici panosu yalnız React ekranıyla açılır; "Klasik görünüm" bağlantısı, görünüm değiştirme (`flxbk_ui`), eski pano betiği (admin-scripts.js) ve admin-ajax işleyicileri (`flxbk_activate_deactivate`, `flxbk_save_config`, `flxbk_post_default_width`) silindi.
- Ekran açılamazsa (Flux GraphQL hazır değil, paket dosyası eksik, bundle yüklenmedi) boş sayfa yerine "Bu ekran yüklenemedi" kutusu ve **Sayfayı yenile** düğmesi çıkar.

## 0.4.1
- wp-admin ekranı Flux GraphQL ekran kabuğundan açılır: ilk veri PHP'de sayfaya basılır (yükleniyor göstergesi yok), menüden diğer Flux ekranlarına sayfa yenilenmeden geçilir. Ekranın parçaları sayfa başında birlikte iner (modulepreload).
- Ortak modül paketi yenilendi.

## 0.4.0
- FluxWP mobil uygulamasında Blok Düzenleyici native çizilir (ortak manifest `app/manifest.json`, GraphQL): blokları aç/kapat, bağlı desen kütüphaneleri. Masaüstü panel React ekranını kullanmaya devam eder.

## 0.3.0
- GraphQL (Flux GraphQL gerekir, PHP 8.1+): her içerik tipinde `flxBlocks` alanı; headless ön yüz bir sayfayı sitedeki gibi göstermek için işlenmiş HTML'i, blok CSS'ini, stil/betik/modül dosyalarını (bağımlılık sırasıyla), yazı tiplerini, FAQ şemasını, hazır `headHtml`/`footerHtml`'i ve düz blok ağacını tek yanıtta alır. Site geneli stiller `flxBlocksSiteStyles`, taslak/desen önizlemesi `flxBlocksRender`. Belge docs/flx-blocks/GRAPHQL.md.
- GraphQL: tasarım kütüphanesi (desenler, önizleme, bağlı kütüphaneler, paylaşım) ve Block Editor panosu (bloklar, ayarlar). Klasik pano ve kütüphane REST uçları aynı servisleri kullanır.
- Yeni Block Editor ekranı (FluxWP uygulamasıyla aynı React ekranı): klasik panonun tüm özellikleri ve ek olarak tasarım kütüphanesi (desen tarayıcı + önizleme, bağlı kütüphaneler, paylaşım). wp-admin'de varsayılan; başlıktaki "Klasik görünüm" ile eski panoya dönülür.
- Türkçe: 134 yeni çeviri.

## 0.2.3
- Performans: İlerleme çubuğu bloğu olan sayfalarda tüm blok CSS'i (`flxbk_css`) iki kez basılıyordu (blok ikinci bir CSS nesnesi açıp yazdırma kancasını yineliyordu). Artık tek kez basılıyor (Qurban Global ana sayfası 399 KB → 277 KB, satır içi blok CSS'i 244 KB → 122 KB).
- Görsel bloğu: yalnız adresle eklenen görsellere (desenler, ön ayarlar) genişlik/yükseklik, ortam kütüphanesindeyse `srcset`/`sizes` eklenir; şablondan doğrudan basılan görsellerde ilk görseller hemen, sonrakiler `loading="lazy"` ve `decoding="async"` ile yüklenir. Kapatmak için `flxbk_image_add_dimensions` / `flxbk_image_loading_optimization` filtreleri.
- Akordiyon: başlık düğmesi sayfanın yazı tipini kullanıyor (önceden tarayıcı varsayılanı Arial çıkıyordu).

## 0.2.2
- Design Library: header/footer patterns whose navigation block has no menu (WooCommerce and others) no longer list every page of the site. In the preview and after insertion they get the site's own menu: the top-level items of the header navigation (submenus become plain links, at most 6; filters `flxbk_library_menu_items`, `flxbk_library_menu_links`).

## 0.2.1
- Design Library toolbar button no longer blinks: the button keeps one holder and one React root for the page and is put back as-is when the editor header re-renders, instead of being built again a frame later.

## 0.2.0

- Yeni: üst çubukta **Tasarım kütüphanesi** düğmesi. Tam ekran pencerede bu sitenin desenleri (tema, ön ayarlar, Desenlerim) önizlemeli, kategorili ve aramalı listelenir; tıklanan desen imlecin olduğu yere eklenir.
- Yeni: başka Flux sitelerini adres + anahtarla kütüphane olarak ekleme ve bu siteyi kütüphane olarak paylaşma (REST `flx-blocks/v1/library`, belge docs/flx-blocks/LIBRARY.md).
- Türkçe: header ve mega menü şablonlarındaki satır/bölüm adları, düğme ve bağlantı metinleri, form şablonu alanları (Ad, E-posta, Mesaj, Gönder), entegrasyon alan listeleri, sekme adları ve kalan sabit metinler çeviriye alındı (71 yeni çeviri).
- Blok arama anahtar kelimelerindeki eski "KB" kısaltması "flux" oldu.

## 0.1.2

- Düzeltme: blokların sayfa başı CSS ve verisi yeniden basılıyor; akordiyonda "SSS şeması" açıkken FAQPage yapılandırılmış verisi çıkıyor.

## 0.1.1

- FluxWP ortak imza modülü (flx-brand) eklendi; diğer FluxWP paketleriyle aynı kişi, etkinlik, telefon ve form alanı araçları.

## 0.1.0

- İlk sürüm: Flux Block Editor. Blok editörüne satır/sütun düzeni, bölüm, gelişmiş başlık, düğme, ikon, ikon listesi, bilgi kutusu, görsel, galeri, akordiyon, sekme, tablo, içindekiler, referans, geri sayım, sayaç, ilerleme çubuğu, Google Haritalar, Lottie, vektör, video açılır pencere, yazılar, arama, "daha fazla göster", boşluk/ayırıcı, form + gelişmiş form ve header/menü oluşturucu blokları (`flx/*`).
- Tasarım tokenları ve Stil kütüphanesi (renk paleti, yazı tipi, boşluk, köşe, gölge, blok presetleri), blok varsayılanları ve ayar görünürlüğü.
- Dışarıya hiçbir istek yok: lisans/telemetri, bulut kütüphane, görsel indirici ve uzak AI kaldırıldı. Lottie oynatıcı ve video ikonları yerelden, Google Haritalar anahtarsız gömme, Google Fonts varsayılan olarak siteden sunulur.
- Türkçe çeviri (TR varsayılan, EN kaynak), renkli menü logosu, FluxWP sistem kontrolü satırları (Google Fonts, form spam koruması, bloklar).
