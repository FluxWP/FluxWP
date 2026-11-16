# Flux Tema (fluxwp, themes/fluxwp): REST → GraphQL eşlemesi

Tema sürümü: 0.7.27 · Önek: `flx_` (tema fonksiyonları) · Cap: `manage_options` (ayar, sistem kontrolü) · Sahip thread: envanter (Faz 1) · Durum: taslak

Not: Bu dosya yalnız envanterdir. "belirsiz" işaretli satırlar uygulayıcıdan önce karar ister.

## Özet

- REST: 0 · AJAX: 0 · Admin sayfası: 2 (`add_theme_page`: `flx-ayarlar` Tema Ayarları, `flx-sistem` Sistem Kontrolü)
- MCP/AI aracı: yok (`flx_mcp_tools` ve `flx_ai_tool` grep'te yok).
- Ayar şeması: var, `inc/ayarlar.php:547` `flx_settings_schema` (Ön ayar / AI için). Otomatik `flxThemeSettings` / `flxThemeSettingsUpdate` üretilir.
- İstisna: yok.
- Plan'daki "menü: 0" satırı düzeltilmeli: iki tema sayfası var.
- Dosya sayısı: 39 PHP (vendor hariç).

## REST / AJAX

Yok. Tema REST ya da AJAX kaydetmiyor.

## Admin sayfaları

| Sayfa (`page=`) | Ne gösterir | Kullandığı sorgular | İstemci ekranı |
|---|---|---|---|
| `flx-ayarlar` (Tema Ayarları, `inc/ayarlar.php:165`) | Renk, font, logo, başlık, sayfa/menü ayarları, ön ayar | `flxThemeSettings`, `flxThemeSettingsUpdate`, `flxThemeGlobalStyles` (aşağıda) | `theme/settings` |
| `flx-sistem` (Sistem Kontrolü, `inc/sistem.php:481`) | Sistem denetimi, uyarılar, düzeltme | `flxSystemChecks`, `flxSystemFixRun` (belirsiz: ortak sistem kontrolü kuralı, bkz. flx-seo `flxSeoSystemChecks`) | `theme/system` |

## Ayarlar (`flx_settings_schema`)

- `flxThemeSettings` (query) ve `flxThemeSettingsUpdate` (mutation) otomatik üretilir, cap `manage_options`.
- Şema açıklamaları makine içindir (AI, ön ayar yazarı), gerekirse kısaltılır.
- **Belirsiz:** ayar anahtarlarının tam listesi çıkarılmadı; `secret`/`token`/`key` içeren anahtar olup olmadığı kontrol edilmeli.

## Global styles

- Plan'da "global styles sorgusu" geçiyor. Önerilen: `flxThemeGlobalStyles` (query, cap `public`): renk tokenları, font, koyu mod için token seti. Headless ön yüz ve FluxApp tema renklerini buradan alır (tema renkleri eklentileri de izler).
- **Belirsiz:** `theme.json` tabanlı global styles çekirdek WP'den mi, yoksa tema ayarı mı okunacak? Kaynak tek olmalı.

## Tipler

| Tip | Alanlar (kısa) | Nullable alanlar ve nedeni | Loader |
|---|---|---|---|
| `FlxThemeSettings` (otomatik) | ayar grupları (ayar şemasına göre) | ayar boşsa varsayılan döner, nullable değil | — |
| `FlxThemeGlobalStyles` | palette (renk listesi), fonts, darkMode (bool) | `fonts` boş olabilir | — |
| `FlxSystemCheck` (belirsiz, ortak tip) | id, status, label, group, source, message | `message` nullable | — |

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-theme:settings` | `flxThemeSettings`, `flxThemeGlobalStyles` | `flxThemeSettingsUpdate`, preset migration |
| `flx-theme:system` | `flxSystemChecks` | `flxSystemFixRun` |

## Açık sorular

- Global styles kaynağı tek mi olsun: tema ayarı mı, `theme.json` mu?
- Sistem kontrolü (`flx-sistem`) tek ortak GraphQL tipi mi olacak (flx-seo ve flx-admin ile birlikte)? Ortak kural `flx-wp-eklenti` skill'indeki sistem kontrolüne göre belirlenmeli.
- Site Kurulumu / ön ayar uygulaması bu envanterde yok; ayrı kalacak mı?
