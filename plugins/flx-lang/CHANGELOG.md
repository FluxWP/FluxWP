# Flux Lang

## 0.1.4
- Ara bilgi metinleri Flux Admin tonunu izliyor (koyu modda okunur).

## 0.1.3
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 0.1.2
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 0.1.1
- Dil önerisi kutusu tema paletinin ana rengini kullanıyor; sabit yeşil kaldırıldı.

## 0.1.0

- İlk sürüm: çok dilli site altyapısı (TR varsayılan, EN; panelden yeni dil).
- Dil adresleri: varsayılan dil öneksiz, diğerleri `/en/…`. Rewrite kuralı/flush gerekmez; yerel ayar istek başında belirlenir, tema ve eklenti arayüzü kendi çevirileriyle gelir.
- İçerik çevirileri: yazı, sayfa, proje, etkinlik başına ayrı dil sürümü (kendi başlık, gövde, slug, öne çıkan görsel, SEO). Dil atanmamış içerik Türkçe sayılır (migration yok).
- Çevirisi olmayan içerik diğer dilde Türkçesiyle gösterilir (ayar); kanonik adres Türkçe, hreflang yalnız gerçek çeviriler.
- Ana sayfa, yazılar sayfası ve WooCommerce sayfalarının dildeki karşılığı; sepet/ödeme/hesabım ortak.
- Menü, düğme ve metin bağlantıları dildeki karşılığa çevrilir (tema koduna dokunmadan).
- Dil seçici: varsayılan alt bilgide (her ekranda); ayarla başlık/mobil menü ya da ikisi. Ayrıca Flux Lang Seçici bloğu, `[flx_dil_secici]`.
- SEO: hreflang + x-default, og:locale:alternate, kanonik, sitemap'te tüm diller, dile göre site adı/slogan/Flux SEO ana sayfa başlığı.
- Türkiye dışından gelen ziyaretçi direkt İngilizce açılır (sayfa çizilmeden, sonraki girişlerde de; dil seçicide seçilen dil kalır; JS, önbellek güvenli, botlar hariç). İstenirse öneri bandı. Ülke: Cloudflare/GeoIP başlığı, yoksa saat dilimi.
- Dile özgü görsel: görsel başına dil alt metni ve "bu dilde şu görsel".
- Kategori/etiket adı ve açıklaması dil başına.
- AI: MCP/Flux AI araçları `flxl_*`; Flux SEO `list_content`, `get_content`, `create_content`, `update_content` araçlarına `lang`. Flux AI ile tek tık çeviri (parça parça, Hostinger süre sınırına takılmaz).
- REST: `lang`, `translations` alanları, `?lang=en` süzgeci. Siparişe ziyaretçinin dili (`_flxl_lang`).
- Sistem kontrolü satırları, TR kaynak + EN çeviri.
