# Flux SEO (flx-seo): REST → GraphQL eşlemesi

Paket sürümü: 1.11.0 (taşındı) · Önek: `flxseo_` (fonksiyonel, sınıf yok) · Cap: `manage_options` (ayar/ AJAX), `flxseo_app_can` (REST) · Sahip thread: envanter (Faz 1) · Durum: uygulandı (PR `gql/flx-seo-1`). Kararlar: IndexNow bayrağı düz açık/kapalı (anahtar dosyası zaten herkese açık); `indexnowSubmit` sayı döner; ayar alanları yönetici cap'inde

Not: Bu dosya yalnız envanterdir. "belirsiz" işaretli satırlar uygulayıcıdan önce karar ister.

## Özet

- REST: 1 yol `flx-seo/v1/audit` (GET + POST) · AJAX: 4 (2 tick endpoint'i priv+nopriv, `flxseo_audit_local`, `flxseo_audit_psi`) · Admin sayfası: 2 üst menü kaydı (`flxseo` + sekmeler)
- MCP/AI: `flx_mcp_tools` ile ~20 araç (ana: `mcp.php`, sistem: `mcp-sistem.php`, sipariş: `mcp-siparis.php`, rehber/denetim: `rehber.php`, `denetim-gecmis.php`), ayrıca 4 prompt, 2 resource.
- İstisna önerisi: `flxseo_audit_tick` (nopriv endpoint, arka plan tick'i). Hostinger'da WP cron tetiklenmediği için loopback isteğiyle çalışıyor; GraphQL'e taşınmaz, `ISTISNALAR.md`'ye satır eklenmeli (gerekçe: dış/loopback tetikleme protokolü).
- Servis (önerilen): `includes/services/class-flxseo-audit-service.php`, `class-flxseo-content-service.php`, `class-flxseo-settings-service.php`. Mantık şu an `denetim-gecmis.php`, `mcp.php` ve `ayarlar.php` içinde.
- Node tipi: yok. `Seo` tipi içerik tiplerine `flxSeo` alanı olarak eklenir.

## REST uçları

| Yöntem | Yol | cap | → GraphQL | Girdi | Çıktı | Not |
|---|---|---|---|---|---|---|
| GET | `/flx-seo/v1/audit` | `flxseo_app_can` | `flxSeoAudit` (query) | `history: Int` (kaç rapor) | `FlxSeoAuditReport` (son rapor + geçmiş özeti) | Mevcut: `flxseo_app_audit` |
| POST | `/flx-seo/v1/audit` | `flxseo_app_can` | `flxSeoAuditRun` (mutation) | `{ mode: LOCAL \| PSI, strategy: MOBILE \| DESKTOP }` | `{ report: FlxSeoAuditReport, errors }` | Mevcut: `flxseo_app_audit_run`. Uzun iş: sonuç kaydedilir, durum için query |

## AJAX

| Action | cap / nonce | → GraphQL | Not |
|---|---|---|---|
| `flxseo_audit_tick` (priv + nopriv) | token (`flxseo_audit_tick_authorized`) | **İSTİSNA** (ISTISNALAR.md satırı gerekli) | Loopback tetik; dış kullanıcıya açık değil, kod token ile doğruluyor. GraphQL'e taşınmaz |
| `flxseo_audit_local` | `manage_options` + nonce | `flxSeoAuditRun(input: { mode: LOCAL })` | Yerel denetim; REST ile aynı servis |
| `flxseo_audit_psi` | `manage_options` + nonce | `flxSeoAuditRun(input: { mode: PSI, strategy })` | PageSpeed API (dış çağrı, belirsiz: anahtar nerede saklanıyor, sonuç cache süresi) |

Not: PSI ve local aynı mutation'ın modu olarak önerilir; ayrı mutation gerekirse uygulayıcı karar verir.

## Admin sayfaları

| Sayfa (`page=`) | Ne gösterir | Kullandığı sorgular | İstemci ekranı |
|---|---|---|---|
| `flxseo` (Genel) | Genel SEO ayarları (site adı, ana sayfa başlık/açıklama, kuruluş bilgisi, doğrulama kodları, noindex, llms, markdown, webmcp, indexnow) | `flxSeoSettings` / `flxSeoSettingsUpdate` (otomatik, aşağıda) | `seo/settings` |
| `flxseo&tab=<k>` (sekmeler: `flxseo_settings_page` ile dinamik) | Diğer sekmeler (ayarlar grupları) | aynı ayar tipi, grup alanı | `seo/settings/<tab>` |
| Denetim geçmişi kartı (`flxseo_audit_history_card`) | Denetim grafiği, skor geçmişi, planlama | `flxSeoAudit`, `flxSeoAuditRun`, `flxSeoSettings` (`audit_freq`, `audit_day`) | `seo/audit` |

## Ayarlar (`flx_settings_schema`, `includes/ajan.php:333`)

- Otomatik tip: `flxSeoSettings` (query) ve `flxSeoSettingsUpdate` (mutation), kit üretir. Alanlar `flxseo_defaults()` anahtarlarından (`ayarlar.php:12`) türer: enabled, site_name, alt_name, sep, home_title, home_desc, org_name, org_type (enum: NGO, Organization), founding, logo, og_image, street, locality, postal, country, phone, email, same_as, twitter, knows_about, area_served, donate_label, donate_url, verify_google/bing/yandex/naver/seznam/baidu, noindex_shop/search/author/tags, auto_alt, indexnow, llms, llms_intro, webmcp, markdown, content_signal, cs_search/cs_ai_input/cs_ai_train (enum: yes/no/''), audit_freq (enum: daily/weekly/off), audit_day (enum: 1–7).
- **Belirsiz:** `indexnow` alanı bir anahtar değeri olabilir. Kural "secret/token/api_key/private" adlarını dışlıyor; `indexnow` bu kurala girmez. Değer gizli ise şemadan çıkarılmalı (karar: Ahmet/uygulayıcı).
- **Belirsiz:** `phone` ve `email` alanlarının herkese açık sorguda dönüp dönmeyeceği (KVKK). Önerilen: `flxSeoSettings` yönetici cap'i (`manage_options`), public site bilgisi ayrı alan.

## MCP / AI araçları (`flx_mcp_tools`)

| Grup | Araçlar (kayıt anahtarı) | Servis | GraphQL karşılığı |
|---|---|---|---|
| Site | `flxseo_site_info`, `flxseo_update_site_seo` | `flxseo_mcp_*` | `flxSeoSiteInfo` (query), `flxSeoSiteSettingsUpdate` (belirsiz: `flxSeoSettingsUpdate` ile birleşebilir) |
| İçerik | `flxseo_list_content`, `flxseo_get_content`, `flxseo_create_content`, `flxseo_update_content`, `flxseo_delete_content` | `flxseo_mcp_apply`, `flxseo_mcp_status` | `flxSeoContent`, `flxSeoContents`, `flxSeoContentCreate`, `flxSeoContentUpdate`, `flxSeoContentDelete` (cap: post tipi `edit_post`/`delete_post`) |
| SEO alanı | `flxseo_set_seo`, `flxseo_seo_audit` | `flxseo_mcp_save_seo`, denetim | `flxSeoSetContentSeo`, `flxSeoContentAudit` |
| Şablon / pattern | `flxseo_list_patterns`, `flxseo_get_pattern` | `flxseo_mcp_theme_patterns` | `flxSeoPatterns`, `flxSeoPattern(name:)` (belirsiz: pattern tipi tema tarafına ait, flx-blocks ile çakışabilir) |
| Medya | `flxseo_upload_media`, `flxseo_list_media`, `flxseo_set_image_alt` | `flxseo_mcp_sideload` | `flxSeoMediaUpload` (dosya = kit upload akışı, `uploadId`), `flxSeoMedia`, `flxSeoMediaAltSet` |
| Terim | `flxseo_list_terms`, `flxseo_update_term` | `flxseo_mcp_terms` | `flxSeoTerms`, `flxSeoTermUpdate` |
| Sistem kontrolü (`mcp-sistem.php`) | sistem denetimi ve düzeltme araçları (`flxseo_system_fixes`, `flxseo_system_fix_log`, `flxseo_system_fix_run`) | `flxseo_system_*` | `flxSeoSystemChecks`, `flxSeoSystemFixLog`, `flxSeoSystemFixRun` (belirsiz: ortak sistem kontrolü kuralı, flx-setup/flx-admin ile paylaşılıyor olabilir) |
| Sipariş özeti (`mcp-siparis.php`, WooCommerce) | gün özeti + değişim | `flxseo_order_day_summary` | belirsiz: sipariş SEO ile ilgisiz; `flx-commerce`'e taşınması önerilir |
| Rehber / denetim (`rehber.php`, `denetim-gecmis.php`) | rehber konuları, denetim raporu, bulgu listesi | `flxseo_guide`, `flxseo_audit_*` | `flxSeoGuide(topic:, lang:)` (query), `flxSeoAudit` |

Prompt'lar (`seo_duzelt`, `haber_yaz`, `site_audit_fix`, `sayfa_kur`) ve resource'lar (`site-build-guide`, `llms-txt`) GraphQL'e taşınmaz; MCP protokolü içinde kalır.

## Tipler

| Tip | Alanlar (kısa) | Nullable alanlar ve nedeni | Loader |
|---|---|---|---|
| `FlxSeo` (içerik tiplerine `flxSeo` alanı) | title, description, focusKeyword, noindex, ogImage, canonical (belirsiz) | `title`, `description` nullable: boşsa site adı/özet kullanılır (ayar kuralı) | — |
| `FlxSeoAuditReport` | id, createdAt, trigger, scores (belirsiz yapı), findings (connection) | `scores` belirsiz; `findings` yalnız `withFindings` ile | — |
| `FlxSeoAuditFinding` | id, priority (enum), title, detail, url | `url` nullable | — |
| `FlxSeoSystemCheck` | id, status (enum), label, group, source, message | `message` nullable | — |

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-seo:settings` | `flxSeoSettings`, `flxSeoSiteInfo` | `flxSeoSettingsUpdate` |
| `flx-seo:audit` | `flxSeoAudit` | `flxSeoAuditRun`, `flxseo_audit_tick` (istisna) |
| `flx-seo:content:{id}` | `flxSeo` alanı | içerik kaydetme, `flxSeoSetContentSeo` |
| `flx-seo:system` | `flxSeoSystemChecks` | `flxSeoSystemFixRun` |

## Açık sorular

- `flxseo_audit_tick` istisna satırı ISTISNALAR.md'ye eklensin mi (gerekçe: loopback tetik, Hostinger cron yok)?
- `indexnow` ayar alanı şemadan çıkarılsın mı (anahtar değeri ise)?
- `phone`/`email` gibi site iletişim bilgileri public sorguya girsin mi (KVKK)?
- MCP içerik CRUD araçlarının GraphQL karşılığı `Post/Page` mutation'ları ile çakışıyor mu? flx-blocks Faz 4'te içerik tiplerini zaten açacak; sahiplik kararı gerekli.
- Sipariş özeti aracı (`mcp-siparis.php`) SEO paketinde kalmalı mı, `flx-commerce`'e mi taşınmalı?
- Plan'daki "1.9.1" sürümü ve "menü 2" bilgisi güncellenmeli (sürüm 1.10.0).
