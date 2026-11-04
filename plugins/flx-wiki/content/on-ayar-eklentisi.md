---
title: Ön ayar eklentisi
slug: on-ayar-eklentisi
parent: tema-yazma
order: 1
excerpt: Kuruma ya da siteye özel logo, renk, sayfa ve ayarları taşıyan flx-preset-* eklentileri ve preset.json.
---
Bir siteye ya da kuruma özel olan her şey (logo, renk paleti, sayfalar, menüler, eklenti ayarları) **ön ayar eklentisinde** durur. Tema ve eklentiler genel kalır.

## Yapı

```text
wp-extras/plugins/flx-preset-ornek/
├── flx-preset-ornek.php
├── preset/
│   ├── preset.json      # flx-preset/1 şeması
│   └── assets/          # logo, görseller
├── patterns/            # siteye özel pattern'ler
└── inc/
```

Flux Setup `flx-preset-*` eklentilerini kendiliğinden bulur ve **Tema Ayarları › Ön ayarlar** ekranında listeler.

## preset.json

Şema: `plugins/flx-setup/schema/flx-preset.schema.json`. Belgeler: `docs/flx-setup/FORMAT.md` ve `plugins/flx-setup/API.md`.

Başlıca bölümler: `profile` (site adı, logo, renk), `wp` (WordPress ayarları), `settings` (eklenti option'ları; alanlar `flx_settings_schema`'dan), içerik (sayfa, menü, ürün) ve `migrations`.

## Sürüm ve eşitleme

- `version` artınca ayarlar eşitlenir; **yalnız ön ayarın bildirdiği alanlar** yazılır, sitede elle değiştirilen değerlere dokunulmaz.
- Sonradan eklenen içerik `migrations` ile gelir (`id` bir kez yayınlandıktan sonra değişmez).
- Her uygulama geri alınabilir bir kayıttır.

> [!TIP]
> Eklentiniz ön ayarla ayarlanabilsin istiyorsanız alanlarını `flx_settings_schema` ile tanımlayın: [[eklenti-yazma/ayarlar]].
