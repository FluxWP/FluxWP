---
title: FluxWP nedir?
slug: fluxwp-nedir
section: baslangic
order: 1
excerpt: FluxWP; WordPress için tek elden yönetilen eklenti, tema ve uygulama ailesidir. Bu sayfa repoları, paketleri ve ortak kuralları özetler.
---
FluxWP; WordPress (ve WooCommerce) siteleri için tek elden geliştirilen eklenti, tema ve uygulama ailesidir. Her paket kendi işini yapar, ama ayarlar, AI, marka imzası, güncelleme ve yönetim menüsü gibi işler **ortak altyapıdan** gelir. Böylece yeni bir eklenti yazarken tekerleği yeniden icat etmezsiniz.

## Repolar

| Repo | İçerik |
|---|---|
| `FluxWP/fluxwp` | Ana monorepo: `plugins/<slug>`, `themes/<slug>`, FluxApp (React panel + Tauri) `apps/`, ortak araçlar `tools/` |
| `FluxWP/wp-extras` | Özel paketler: ön ayar eklentileri (`flx-preset-*`), TV Board, TV Remote, Staff, Qurban |
| `FluxWP/updater` | Güncelleme sunucusu (`updater.fluxwp.com`) |
| `FluxWP/whatsapp-api` | WhatsApp köprüsü |
| `FluxWP/mobile` | iOS ve Android uygulaması (Expo) |

## Ortak altyapı

- **Flux Setup** (`flx-setup`): kurulum sihirbazı, güncellemeler, ön ayarlar ve ayar şeması (`flx_settings_schema`).
- **Flux Admin** (`flx-admin`): tek yönetim menüsü, sayfa başlıkları, paket logoları, koyu tema.
- **Flux AI & MCP** (`flx-ai`): anahtarlar, modeller, harcama sınırı, ortak sohbet ve MCP sunucusu. AI kullanan her paket buradan geçer: [[ai-destekli-eklenti]].
- **flx-brand**: her pakete kopyalanan küçük modül; "Altyapı: FluxWP" imzası ve tarih yardımcıları `flx_date()`, `flx_time_ago()`.
- **Sistem kontrolü** (`flx_system_checks`): her paket kendi sağlık satırlarını aynı ekrana ekler.

## Temel kurallar

> [!IMPORTANT]
> Bu kurallar her pakette geçerlidir; kod incelemesinde ilk bakılan yerlerdir.

1. **Ayarlar panelden girilir.** Sunucu adresi, API anahtarı, token gibi değerler `wp-config.php` sabitine bağlanmaz; ayar ekranında durur (kurtarma sabitleri hariç, ör. `FLXRC_DISABLE`).
2. **Tüm metinler çevrilebilir.** Kaynak dil Türkçe, İngilizce çeviri pakette gelir. Koda gömülü Türkçe metin olmaz: [[eklenti-yazma/ceviri]].
3. **Tarih tek yerden.** `human_time_diff()` ve `wp_date()` doğrudan kullanılmaz; `flx_date()` ve `flx_time_ago()` kullanılır.
4. **AI tek yerden.** Paketler sağlayıcıya doğrudan istek atmaz; `flxai_chat()` kullanır.
5. **Önekler.** Her paketin kendi kod öneki vardır (`flxrc_`, `flxwk_` …): [[adlandirma]].
6. **Paylaşımlı sunucuda çalışmalı.** Hedef ortam Hostinger gibi paylaşımlı barındırma: PHP isteği ~60 saniyede kesilir, Redis ve Supervisor yoktur, WP-Cron güvenilir değildir. Uzun işler parça parça ve tarayıcı yoklamasıyla ilerler.
7. **JS paket yöneticisi Bun.** `npm` ya da `pnpm` değil.

## Nereden başlamalı?

- Yeni eklenti: [[eklenti-yazma]]
- Eklentiye AI özelliği: [[ai-destekli-eklenti]]
- MCP aracı: [[ai-destekli-eklenti/mcp-araci-yazma]]
- Tema: [[tema-yazma]]
- Yayınlama: [[surum-ve-yayin]]
