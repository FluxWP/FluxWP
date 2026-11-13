# Changelog

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
