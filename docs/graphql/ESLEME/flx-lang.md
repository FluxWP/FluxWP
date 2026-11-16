# Flux Lang (flx-lang): REST → GraphQL eşlemesi

Paket sürümü: 0.3.0 · Önek: `flxl_` · Cap: içerik düzenleme `edit_post` (AI çeviri), ayar `manage_options` · Sahip thread: envanter (Faz 1) · Durum: uygulandı (`gql/flx-lang-1`; ayrıntı `docs/flx-lang/GRAPHQL.md`)

Not: Bu dosya yalnız envanterdir. "belirsiz" işaretli satırlar uygulayıcıdan önce karar ister.

## Özet

- REST: 1 yol (`flx-lang/v1/geo`, GET, herkese açık) · AJAX: 1 (`flxl_ai`, iki adımlı iş: başlat + adım) · Admin sayfası: 1 (Ayarlar > Languages, `add_options_page`)
- MCP/AI aracı: yok (`flx_mcp_tools` kullanılmıyor). AI çeviri `flxai_chat` üzerinden; `flxl_ai_available()` `flx-ai` varlığını ve `flxai_ready('flx-lang')` kontrolünü yapar.
- İstisna: yok.
- Asıl GraphQL işi: içerik tiplerine `flxLanguage` ve `flxTranslations` alanları (plan: her içerik tipinde). Dil seçici footer'da, sayfa çevirisi `flx-lang` içinde.
- Servis (önerilen): `includes/services/class-flxl-language-service.php` (dil listesi, çeviri eşleri, çeviri işi) ve `class-flxl-geo-service.php`.
- Node tipi: yok (kendi nesnesi yok). Çeviri eşleri içerik nesnesinin alanı olarak döner.

## REST uçları

| Yöntem | Yol | cap | → GraphQL | Girdi | Çıktı | Not |
|---|---|---|---|---|---|---|
| GET | `/flx-lang/v1/geo` | public | `flxVisitorCountry` (query) | yok | `{ country: String }` (ISO kodu, boş olabilir) | Yalnız ziyaretçinin ülke kodu, kayıt yok. **Belirsiz:** GraphQL GET persisted cache ziyaretçi bazlı (Vary: CF-IPCountry); bu alan önbelleğe alınmamalı. Yazan yan etki: `flxl_geo_seen` seçeneği (günde bir). Önerilen: sorgu için `cache_ttl` 0, LiteSpeed nocache. |

## AJAX

| Action | cap / nonce | → GraphQL | Not |
|---|---|---|---|
| `flxl_ai` (job yok → başlat) | `edit_post` (`src` kaydı) + `flxl_ai` nonce | `flxLanguageTranslateStart(input: { sourceId: ID!, lang: String! })` → `{ job: FlxLanguageJob!, errors }` | Mevcut kod `flxl_ai_job_start` çağırıyor; servis katmanına taşınacak |
| `flxl_ai` (job var → adım) | aynı | `flxLanguageTranslateStep(input: { jobId: ID!, step: Int! })` → `{ job: FlxLanguageJob!, errors }` | Adım adım çalışır (zaman aşımı koruması, PHP ~60 sn). Durum için `flxLanguageJob(id:)` query |

## Admin sayfaları

| Sayfa | Ne gösterir | Kullandığı sorgular | İstemci ekranı |
|---|---|---|---|
| `flx-lang` (Ayarlar > Languages) | Dil listesi, varsayılan dil, geo, öneri bandı metinleri, AI çeviri ayarı | `flxLanguageSettings`, `flxLanguageSettingsUpdate` (aşağıda), `flxLanguages` (aktif diller) | `packages/modules/src/lang/settings` (belirsiz: kapsam tek sayfa mı) |

Plan'daki "menü: 0" satırı düzeltilmeli: `ayarlar.php:13` `add_options_page` ile 1 menü kaydı var.

## Ayarlar

- `flx_settings_schema` bu pakette yok. `flxLanguageSettings` ve `flxLanguageSettingsUpdate` elle tanımlanır (cap `manage_options`). Belirsiz: ayar anahtarlarının tam listesi çıkarılmadı (dil listesi, öneri bandı metinleri, geo açık/kapalı).

## İçerik alanları (öneri)

| Alan | Hangi tipler | Tip | Cap | Not |
|---|---|---|---|---|
| `Post.flxLanguage`, `Page.flxLanguage` (ve diğer içerik tipleri) | WP içerik tipleri | `String` (ISO kodu) | okuma: `public` (yayınlanmış), taslak için `edit_post` | Kural: WP tipine eklenen alan `flx` önekli ve cap zorunlu |
| `Post.flxTranslations`, `Page.flxTranslations` | aynı | `[FlxTranslation!]!` (`{ lang, id, url, status }`) | okuma: içerik okuma cap'i | Çeviri eşi listesi; DataLoader ile N+1 önlenir |

## Tipler

| Tip | Alanlar | Nullable alanlar ve nedeni | Loader |
|---|---|---|---|
| `FlxLanguage` | code, name, default (bool), active (bool), flag (belirsiz) | `flag` nullable (görsel tanımsız olabilir) | — |
| `FlxTranslation` | lang, id (global ID), url, status | `url` nullable (çeviri henüz yayınlanmadı) | — |
| `FlxLanguageJob` | id, status (enum: RUNNING, DONE, FAILED), step, total, sourceId, lang | `total` belirsiz (adım sayısı baştan bilinmiyorsa) | — |
| `FlxVisitorCountry` (yalnız `flxVisitorCountry` sorgusu için) | country | `country` nullable | — |

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-lang:languages` | `flxLanguages`, `flxLanguageSettings` | `flxLanguageSettingsUpdate` |
| `flx-lang:post:{id}` | `flxTranslations`, alan okuma | çeviri işi bitince (`flxLanguageTranslateStep`), içerik kaydedilince |
| `flx-lang:job:{id}` | `flxLanguageJob` | adım yazımı |

## Açık sorular

- `flxVisitorCountry` persisted GET cache'lenmemeli; kit'te `cache_ttl` 0 desteği var mı?
- AI çeviri `flx-ai` (ZORUNLU kuralı) üzerinden mi? Şu an `flxai_chat` çağrılıyor; `flx-ai` yoksa `flxl_ai_available()` false dönüyor, bu zorunlulukla uyumlu mu doğrulanmalı.
- Çeviri işi için `FlxLanguageJob` durumu tek başına yeterli mi, yoksa SSE/polling mi? Polling (≥ 10 sn) önerilir.
- Ayar anahtar listesi ve öneri bandı metinlerinin i18n yönetimi (TR + EN) netleşmeli.
