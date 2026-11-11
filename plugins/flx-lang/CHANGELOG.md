# Flux Lang

## 0.2.8
- Ülke ucu ortak önbelleksiz başlıkları kullanır. CDN işareti ve CF-IPCountry ayrımı durur.

## 0.2.7
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 0.2.6
- List language sitemap subtypes under the existing WordPress posts provider, such as `/wp-sitemap-posts-page-ar-1.xml`, without an extra `flxlang` or `translations` name. Retain language grouping and delegate native content subtypes unchanged.
- Permanently redirect valid previously submitted `flxlang` sitemap URLs to their new equivalents; respect pagination, disabled languages, SEO exclusions and the global sitemap switch.

## 0.2.5
- Add paginated core sitemap children for indexable shared pages and missing static home roots in every active nondefault language; physical post, page and wiki translations remain in their native sitemaps.
- Respect core post type/query exclusions and Flux SEO noindex; omit private, draft and password-protected shared pages, and keep default shared URLs independent of the request language.

## 0.2.4
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 0.2.3
- Match regional browser languages, use country hints only when no supported browser language exists, preserve explicit language URLs and keep the configured fallback available in the panel.
- Center the dropdown chevron with a fixed SVG box so alignment stays consistent across fonts, languages and text directions.

## 0.2.2
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 0.2.1
- Resolve front-end RTL/LTR from the selected language even without a WordPress language pack; synchronize HTML direction, core is_rtl and body classes while preserving the admin language. Isolate language-switch labels.

## 0.2.0
- Uygulama menüsü dil ayarlarını web görünümünde açar.

## 0.1.9
- Metinler: MCP sunucusu artık Flux AI & MCP eklentisinde; "Flux SEO & MCP" ve "Flux AI ›" adresleri "Flux AI & MCP" oldu.

## 0.1.8
- Flux SEO 1.6.0'ın önekli araçlarına (`flxseo_list_content` …) dil alanı eklenir; eski öneksiz adlar da desteklenir.

## 0.1.7
- Paylaşılan sayfalar (`flxl_shared_post_ids`, ör. sepet/ödeme) her dilde aynı adresle hreflang alır; `flxl_hreflang_archive` filtresi gerekmez.

## 0.1.6
- Temanın dile göre metinleri (çerez banner'ı başlığı, metni, düğmeler) ikinci dilde uygulanıyor. Önceden metin filtreleri tema yüklenmeden kuruluyordu, `tema_*` alanları hiç devreye girmiyordu.

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
