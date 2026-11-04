---
title: Bu wiki nasıl kullanılır?
slug: wiki-kullanimi
section: baslangic
order: 3
excerpt: Flux Wiki'de arama, Markdown yazımı, wiki bağlantıları, not kutuları, LLM için .md ve llms.txt adresleri ve MCP araçları.
---
Bu wiki **Flux Wiki** eklentisiyle çalışır. Sayfalar Markdown yazılır; her sayfa hem insanlar hem de AI ajanları için okunaklıdır.

## Arama

- Her sayfada <kbd>Ctrl</kbd> + <kbd>K</kbd> (Mac'te <kbd>⌘</kbd> + <kbd>K</kbd>) ya da <kbd>/</kbd> arama penceresini açar.
- Türkçe karakterler eşit sayılır: `ozel`, `özel` ile aynı sonucu verir.
- Sonuç en iyi eşleşen ara başlığa götürür.
- Flux AI açıksa **Wiki'ye sor** sekmesi, sorunuzu wiki sayfalarına dayanarak yanıtlar ve kaynakları listeler.

## Markdown yazımı

GitHub biçimi geçerlidir: başlıklar, listeler, tablolar, kod blokları.

```markdown
## Ara başlık

Metin, **kalın**, `satır içi kod`.

| Sütun | Açıklama |
|---|---|
| a | b |
```

Sayfa başlığını içerikte tekrar yazmayın; en üst seviye `##` olsun. İçindekiler `##` ve `###` başlıklarından üretilir.

### Wiki bağlantıları

- `[[adlandirma]]` sayfanın kısa adıyla bağlantı kurar; başlık otomatik yazılır.
- `[[eklenti-yazma/ceviri|çeviri rehberi]]` alt sayfaya, kendi etiketinizle.
- `[[adlandirma#ozet-tablo]]` sayfadaki bir başlığa.
- Olmayan sayfa kırmızı kesikli çizgiyle görünür; yazılmayı bekleyen konuları böyle işaretleyebilirsiniz.

### Not kutuları

```markdown
> [!TIP]
> Kısa ipucu.
```

Türler: `NOTE`, `TIP`, `IMPORTANT`, `WARNING`, `CAUTION`.

> [!TIP]
> Kod bloklarının sağ üstünde kopyalama düğmesi vardır.

## LLM ve AI ajanları için

| Adres | Ne |
|---|---|
| `/wiki/<sayfa>.md` | Sayfanın düz Markdown'u (başlık, kaynak adresi, güncelleme tarihi ile) |
| `/wiki/llms.txt` | Tüm sayfaların dizini ([llms.txt](https://llmstxt.org/) biçimi) |
| `/wiki/llms-full.txt` | Tüm wiki tek dosyada |
| `/wiki/index.md` | Dizin, Markdown |

Sayfadaki **Markdown'u kopyala** düğmesi sayfayı panoya alır; **AI ile aç** menüsü ChatGPT, Claude ya da Perplexity'de sayfayı okutan bir sohbet başlatır. `Accept: text/markdown` başlığıyla istek atan ajanlara sayfa adresi de doğrudan Markdown döner.

## MCP

Flux AI'nin MCP sunucusu açıksa (Flux AI › MCP) şu araçlar kullanılabilir:

| Araç | Ne yapar |
|---|---|
| `flxwk_search` | Wiki'de arar |
| `flxwk_get_page` | Sayfayı Markdown olarak getirir |
| `flxwk_list_pages` | Bölümlere göre sayfa ağacı |
| `flxwk_save_page` | Sayfa yazar ya da günceller (onay ister; varsayılan taslak) |

Kaynaklar: `flxwiki://llms.txt`, `flxwiki://llms-full.txt`. İstem: `flxwk_build_plugin` (yeni eklenti işini ilgili rehberlerle başlatır).

Claude Code, Cursor gibi bir istemciye ekleyince ajan önce wiki'yi okuyup FluxWP kurallarına göre kod yazar.
