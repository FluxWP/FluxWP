# Değişiklikler

## 1.2.4
- Kontrol listesindeki uyarı/hata renkleri koyu modda okunur.

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
