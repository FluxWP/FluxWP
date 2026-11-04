# Flux Wiki

## 0.1.0

- İlk sürüm: `/wiki` altında geliştirici wiki'si. Hiyerarşik wiki sayfaları (`flx_wiki`) ve bölümler (`flx_wiki_section`).
- Markdown düzenleyici: Yaz / Önizle / Yan yana, araç çubuğu, `[[wiki bağlantısı]]`, `> [!NOTE]` not kutuları, görev listeleri. Markdown kaynağı sayfa meta'sında, HTML kopyası içerikte (WP araması, RSS, SEO eklentileri için).
- Ön yüz: ana sayfa (bölüm kartları, son güncellenenler, LLM kutusu), sayfa ağacı, içindekiler (görünen bölüm vurgulanır), kırıntı, önceki/sonraki, alt sayfalar, kod kopyalama. Blok ve klasik temalarda temanın başlık ve altlığıyla; FluxWP koyu moduna uyar.
- Arama: Ctrl/⌘ + K penceresi ve `/wiki/?q=` sayfası; Türkçe karakter katlama, soru kelimesi ve ek temizliği, en iyi başlığa bağlantı, vurgulu alıntı. REST `flx-wiki/v1/search`.
- LLM: `/wiki/<yol>.md`, `Accept: text/markdown`, `/wiki/llms.txt`, `/wiki/llms-full.txt`, `/wiki/index.md`; "Markdown'u kopyala", "ChatGPT / Claude / Perplexity ile aç". Flux SEO `/llms.txt` dosyasına Wiki bölümü.
- Flux AI: "Wiki'ye sor" (yalnız wiki sayfalarından, kaynaklı; kimler sorabilir ve saatlik sınır ayarı). MCP araçları `flxwk_search`, `flxwk_get_page`, `flxwk_list_pages`, `flxwk_save_page`; kaynaklar ve `flxwk_build_plugin` istemi.
- SEO: TechArticle + BreadcrumbList JSON-LD (Flux SEO grafına eklenir), açıklama, Open Graph (Flux SEO yoksa), arama sayfaları `noindex`.
- Hazır 14 rehber (5 bölüm): FluxWP nedir, adlandırma, wiki kullanımı, yeni eklenti, ayarlar ve şema, çeviri ve tarih, Flux Admin, sistem kontrolü, AI destekli eklenti, MCP aracı, AI iyi uygulamalar, tema, ön ayar eklentisi, sürüm ve yayın. Ayarlar'dan güncellenir; elle değiştirilen sayfalara dokunulmaz.
- Sistem kontrolü (kalıcı bağlantılar, Wiki'ye sor) ve `flx_settings_schema` kaydı.
