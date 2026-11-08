# Flux Wiki

WordPress içinde `/wiki` adresinde geliştirici wiki'si: bölümlere ayrılmış Markdown sayfalar, sayfa ağacı ve içindekiler,
ayrıntılı anlık arama (Ctrl/⌘ + K) ve SEO uyumlu çıktı. Hem insanlar hem LLM'ler için.

- **Markdown.** Sayfalar Markdown (GitHub biçimi) yazılır; Yaz / Önizle / Yan yana düzenleyici, `[[wiki bağlantıları]]`,
  `> [!NOTE]` not kutuları, görev listeleri, kopyalanabilir kod blokları. Parsedown (MIT) ile işlenir.
- **Arama.** Başlık, ara başlık ve metne göre puanlanır; Türkçe karakterler eşit sayılır (ı = i, ş = s), basit ek
  temizliği yapılır, sonuç en iyi eşleşen başlığa götürür. JS'siz de çalışır: `/wiki/?q=`. REST: `GET /wp-json/flx-wiki/v1/search?q=`.
- **LLM dostu.** Her sayfa `/wiki/<yol>.md` adresinde Markdown (ya da `Accept: text/markdown`); ayrıca `/wiki/llms.txt`,
  `/wiki/llms-full.txt`, `/wiki/index.md`. Her sayfada "Markdown'u kopyala" ve "AI'a sor" (ChatGPT / Claude / Google AI / Perplexity).
  Flux SEO'nun `/llms.txt` dosyasına Wiki bölümü eklenir.
- **AI destekli.** Flux AI varsa "Wiki'ye sor" soruları yalnız wiki sayfalarından yanıtlar ve kaynakları listeler
  (varsayılan: giriş yapmış kullanıcılar, saatlik sınır). Flux AI MCP sunucusunda araçlar: `flxwk_search`,
  `flxwk_get_page`, `flxwk_list_pages`, `flxwk_save_page`; kaynaklar `flxwiki://llms.txt`, `flxwiki://llms-full.txt`; istem `flxwk_build_plugin`.
- **SEO.** TechArticle + BreadcrumbList JSON-LD (Flux SEO varsa onun grafına eklenir), açıklama, canonical, çekirdek
  site haritası; arama sonuç sayfaları `noindex`.
- Etkin temanın başlık, altlık ve renklerini kullanır (blok ve klasik tema), FluxWP koyu moduna uyar.
- Hazır rehberlerle gelir: eklenti ve tema yazma, ayar şeması, çeviri, Flux Admin, sistem kontrolü, AI destekli eklenti,
  MCP aracı, sürüm ve yayın.

Geliştirici notları: [docs/flx-wiki/API.md](../../docs/flx-wiki/API.md).

English: [README.md](README.md)

## Wiki asistanının kontrolleri

Sorular, yazım düzeltmesiyle birlikte etkin içerik dillerinin tamamında aranır. Zayıf eşleşmeler için Flux AI üzerinden bir kısa sorgu çevirisi yapılabilir. Yalnız yayımlanmış, parolasız Wiki sayfaları bağlam olarak gönderilir; araç çalıştırılmaz. Cevaplar verilen kaynaklara bağlantı verir; çıktı temizlenir ve tıklanabilir bağlantılar kaynaklarla sınırlandırılır. Bu kontroller riski azaltır; model cevabının doğruluğunu garanti etmez.

Varsayılan sınır kullanıcı veya tuzlanmış IP kimliği başına saatlik 20 sorudur; aynı ziyaretçinin bir isteği sürerken yenisi kabul edilmez. Yöneticiler saatlik sınırdan muaftır. Flux AI harcama sınırları geçerlidir. Wiki ayarlarından erişim kapatılabilir, giriş yapmış kullanıcılara sınırlandırılabilir (varsayılan) veya herkese açılabilir.
