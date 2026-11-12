# Flux Wiki

## 0.2.11
- Tarayıcının onay/uyarı/giriş pencereleri (confirm, alert, prompt) yerine ortak FluxWP modalı (flx-brand flxDialog): yeni tarz, telefonda alttan açılır, koyu modla uyumlu. Silme gibi geri alınamaz işlemlerde kırmızı onay düğmesi.

## 0.2.10
- Wiki'ye sor saatlik sınırı ortak sayacı kullanır. Yönetici muafiyeti ve saatlik kota aynı kalır.

## 0.2.9
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 0.2.8
- Table header cells follow the theme's table header colour (FluxWP palette `table-head` or Theme Settings), falling back to the soft surface tone on other themes. Table rows sit on the surface colour (white; dark surface in dark mode).

## 0.2.7
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 0.2.6
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 0.2.5
- Support RTL navigation trees, article layout, table of contents, pager, search and AI dialogs with logical CSS; keep code syntax LTR and mirror directional icons only.

## 0.2.4

- Redesign the documentation home with a start guide, full-width search, responsive section cards and quieter recent/AI resource panels.
- Fix missing section translation metadata being treated as manual edits during preset upgrades.
- Search every configured content language for Ask Wiki, with spelling correction and bounded query translation for weak matches.
- Restrict AI context to public, password-free documents; isolate untrusted page data, disable tools, validate clickable citation URLs and handle empty results without generating an unsupported answer.
- Serialize requests per visitor and enforce a fixed hourly quota; validate question length before calling Flux AI.

## 0.2.3

- Flux Lang integration for wiki pages and preset language/translation pairs.
- Isolate page, search and rendered Markdown caches by language; preserve language in live search and AI requests.
- Mark bundled Turkish guides as Turkish on import.

## 0.2.2

- **Canlı arama:** wiki'deki arama kutuları (yan menü ve ana sayfa) artık pencere açmıyor. Yazdıkça (220 ms bekleyerek) sonuçlar kutunun hemen altında açılıyor, sayfa aşağı uzuyor. Ok tuşlarıyla seçilip Enter ile açılıyor, Esc kapatıyor. Fazla sonuç varsa "N sonucun hepsini gör" bağlantısı çıkıyor. Ctrl/⌘+K ve "/" kutuya odaklanıyor. "Wiki'ye sor" penceresi ayrı kalıyor.
- **Arama sayfası** da canlı: büyük kutuya yazdıkça liste yerinde güncelleniyor, adres `?q=` ile eşitleniyor.
- **Yazım hatası düzeltme:** sonuç yoksa her kelime dizindeki en yakın kelimeyle (1-2 harf fark) değiştirilip yeniden aranıyor: "kurlum" → "kurulum", "eklnti" → "eklenti". Sayfada "'kurlum' bulunamadı; 'kurulum' için sonuçlar gösteriliyor" yazıyor.
- Arama sayfasında sonuç yokken içerik sütunu daralmıyor (wiki kapsayıcısı tam genişlik).
- Arama sonuçlarında `&` gibi karakterler artık `&#038;` olarak görünmüyor.

## 0.2.1
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.


## 0.2.0
- Uygulama: sayfa listesi, arama ve Markdown düzenleme. `wp/v2/flx_wiki` alanına `markdown` eklendi.

## 0.1.1

- Sol admin menüsünde Flux Wiki artık diğer FluxWP paketleri gibi renkli paket logosuyla görünür (önceden gri WordPress kitap simgesi). Flux Admin varsa ortak logo setindeki `flx-wiki.svg`, yoksa paketteki `assets/icon.svg`.

## 0.1.0

- İlk sürüm: `/wiki` altında geliştirici wiki'si. Hiyerarşik wiki sayfaları (`flx_wiki`) ve bölümler (`flx_wiki_section`).
- Markdown düzenleyici: Yaz / Önizle / Yan yana, araç çubuğu, `[[wiki bağlantısı]]`, `> [!NOTE]` not kutuları, görev listeleri. Markdown kaynağı sayfa meta'sında, HTML kopyası içerikte (WP araması, RSS, SEO eklentileri için).
- Ön yüz: ana sayfa (bölüm kartları, son güncellenenler, LLM kutusu), sayfa ağacı, içindekiler (görünen bölüm vurgulanır), kırıntı, önceki/sonraki, alt sayfalar, kod kopyalama. Blok ve klasik temalarda temanın başlık ve altlığıyla; FluxWP koyu moduna uyar.
- Sayfa başlığının sağında iki menü: **Markdown** (kopyala, .md olarak aç) ve **AI'a sor** (Wiki'ye sor, ChatGPT, Claude, Google AI, Perplexity); mobilde başlığın altında yan yana.
- Ayarlar sekmeli: **Genel** (wiki açık/kapalı, ad, tanıtım, adres kökü), **Görünüm** (vurgu rengi, genişlik, sol menü, içindekiler), **AI**. Kapalıyken ziyaretçilere 404, site haritası ve llms.txt'den çıkar; editörler görür.
- Flux Setup ön ayarları `wiki_section` ve `wiki` içerik türleriyle wiki bölümü ve sayfası yazabilir (Markdown `$file` ile).
- Arama: Ctrl/⌘ + K penceresi ve `/wiki/?q=` sayfası; Türkçe karakter katlama, soru kelimesi ve ek temizliği, en iyi başlığa bağlantı, vurgulu alıntı. REST `flx-wiki/v1/search`.
- LLM: `/wiki/<yol>.md`, `Accept: text/markdown`, `/wiki/llms.txt`, `/wiki/llms-full.txt`, `/wiki/index.md`; "Markdown'u kopyala", "AI'a sor" menüsü. Flux SEO `/llms.txt` dosyasına Wiki bölümü.
- Flux AI: "Wiki'ye sor" (yalnız wiki sayfalarından, kaynaklı; kimler sorabilir ve saatlik sınır ayarı). MCP araçları `flxwk_search`, `flxwk_get_page`, `flxwk_list_pages`, `flxwk_save_page`; kaynaklar ve `flxwk_build_plugin` istemi.
- SEO: TechArticle + BreadcrumbList JSON-LD (Flux SEO grafına eklenir), açıklama, Open Graph (Flux SEO yoksa), arama sayfaları `noindex`.
- Hazır 14 rehber (5 bölüm): FluxWP nedir, adlandırma, wiki kullanımı, yeni eklenti, ayarlar ve şema, çeviri ve tarih, Flux Admin, sistem kontrolü, AI destekli eklenti, MCP aracı, AI iyi uygulamalar, tema, ön ayar eklentisi, sürüm ve yayın. Ayarlar'dan güncellenir; elle değiştirilen sayfalara dokunulmaz.
- Sistem kontrolü (kalıcı bağlantılar, Wiki'ye sor) ve `flx_settings_schema` kaydı.
