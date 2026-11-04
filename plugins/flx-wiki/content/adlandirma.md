---
title: Adlandırma kuralları
slug: adlandirma
section: baslangic
order: 2
excerpt: Paket kısa adı, görünen ad, kod öneki, hook, REST ad alanı, option ve MCP araç adları için FluxWP kuralları.
---
FluxWP'de birçok paket aynı sitede birlikte çalışır. Çakışma olmasın diye her adın bir kuralı vardır.

## Özet tablo

| Ne | Kural | Örnek |
|---|---|---|
| Klasör / slug | `flx-<ad>` | `flx-wiki` |
| Görünen ad | `Flux <İngilizce ad>` (her dilde aynı) | Flux Wiki |
| Tema adı | `FluxWP` | FluxWP |
| Ön ayar eklentisi | `flx-preset-<ad>` | `flx-preset-fluxwp` |
| Kod öneki | `flx<kısaltma>_` | `flxwk_search()` |
| Sınıf / sabit öneki | `FLX<KISALTMA>_` | `FLXWK_VERSION` |
| Ortak hook ve fonksiyon | `flx_` | `flx_mcp_tools`, `flx_system_checks` |
| REST ad alanı | slug + sürüm | `flx-wiki/v1` |
| Option | önek + ad | `flxwk_settings` |
| MCP aracı | önek + fiil | `flxwk_get_page` |
| npm paketi | `@fluxwp/<ad>` | `@fluxwp/panel` |

## Önek seçerken

- Önek benzersiz olmalı. Önce repoda arayın: `grep -rn "flxwk_" plugins themes`.
- Önek **eklentinin kendi hook'larında** da kullanılır (`flxwk_base`, `flxwk_ai_links`). Başka paketlerin de kullanacağı ortak hook gerekiyorsa `flx_` ile başlar ve API belgesine yazılır.
- Önek uzunluğuna bağlı kod yazmayın (ör. `substr( $key, 5 )`). Ad değişikliklerinde en çok bu tür kod kırılır.

> [!WARNING]
> MCP araç adları tüm eklentiler arasında tek bir listede durur. Öneksiz araç adı (ör. `search`) başka bir eklentinin aracıyla çakışır; her zaman önekli yazın.

## Görünen ad ve menü

Eklenti başlığındaki `Plugin Name` görünen addır (`Flux Wiki`). Yönetim menüsündeki kısa ad çevrilebilir olabilir (`Wiki`), ama paket adı çevrilmez.
