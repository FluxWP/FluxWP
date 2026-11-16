# Flux Ads (flx-ads): klasik → GraphQL eşlemesi

Paket sürümü: 0.8.0 · Önek: `flxads_` · Sahip thread: Faz 1 / flx-ads · Durum: bitti (PR `gql/flx-ads-1`)

## Özet

- REST uçları: 0 · AJAX: 0 · admin-post işleyici: 9 · Admin sayfası: 1 (12 sekme) · MCP/AI aracı: var (aynı kalır, GraphQL'e taşınmaz; araçlar eski sınıfları çağırır)
- İstisna: yok. Reklam platformu bağlantıları (Meta, TikTok, GA4) sunucudan sunucuya; OAuth ya da webhook akışı yok.
- Servisler: `includes/services/` (`FLXADS_Reports_Service`, `FLXADS_Meta_Service`, `FLXADS_Creative_Service`, `FLXADS_Settings_Service`, ortak `FLXADS_V`). Klasik sınıflar (`FLXADS_Report`, `_Spend`, `_Manager`, `_Optimizer`, `_Experiments`, `_Planner`, `_Studio`, `_Audiences`, `_Cohort`, `_Send`) iki ekranın ortak iş mantığıdır; servisler tipli çıktı üretir.
- Node tipleri: yok (kayıtlar paket içi anahtarla anılır).
- Gizli anahtarlar (Meta/TikTok token, GA4 secret) yalnız yazılır: okuma `{set, mask, fromConfig}` döner. Boş gönderilen mevcut olanı korur, `__clear__` siler.
- Testler reklam hesabına yazmaz: bağlantı testi test olayı ya da doğrulama uç noktasını kullanır; sözleşme testi tüm dış HTTP'yi engeller.

## Yetki

| Rol | Yetki | Kapsam |
|---|---|---|
| Görüntüleyici | `flxads_view` (ya da `flxads_manage`, `manage_options`) | Tüm sorgular (`flxAdsSettings` hariç) |
| Yönetici | `flxads_manage` (ya da `manage_options`) | Tüm mutation'lar, `flxAdsSettings` |

Her alan cap ister (fail closed); ziyaretçi ve cap'siz kullanıcı reddedilir.

## admin-post işleyicileri

| Action | → GraphQL |
|---|---|
| `flxads_save` | `flxAdsSettingsSave`, `flxAdsConnectionTest` (önce kaydeder, sonra dener) |
| `flxads_spend` | `flxAdsSpendAdd`, `flxAdsSpendDelete`, `flxAdsSpendSync` |
| `flxads_retry` | `flxAdsSendRetry` |
| `flxads_aud` | `flxAdsAudienceCreate`, `flxAdsAudienceLookalike`, `flxAdsAudienceSync`, `flxAdsAudienceDelete` |
| `flxads_mgr` | `flxAdsCampaignStatus`, `flxAdsCampaignBudget` |
| `flxads_opt` | `flxAdsSuggestionApply`, `flxAdsSuggestionDismiss`, `flxAdsSuggestionsRefresh` |
| `flxads_studio` | `flxAdsDraftSave`, `flxAdsDraftGenerate`, `flxAdsDraftDuplicate`, `flxAdsDraftDelete`, `flxAdsDraftSend` |
| `flxads_plan` | `flxAdsSeasonAdd`, `flxAdsSeasonDelete` |
| `flxads_exp` | `flxAdsExperimentCreate`, `flxAdsExperimentFinish`, `flxAdsExperimentDelete`, `flxAdsExperimentPauseLoser` |

Klasik işleyiciler değişmedi; GraphQL aynı klasik sınıfları çağırır, davranış iki ekranda aynıdır.

## Sayfa yüklenirken yapılan iş

Klasik sayfa açılınca gönderim kuyruğunu işler ve harcama/öneri yenilemesini zamanlar. React ekranı bunu `flxAdsHeartbeat` mutation'ıyla açılışta ve 5 dakikada bir yapar (reklam hesabına yazmaz).

## Admin sayfaları

| Sayfa (`page=`) | Ne gösterir | Kullandığı işlemler | İstemci ekranı |
|---|---|---|---|
| `flx-ads` (sekmeler: report, campaigns, suggestions, studio, planner, experiments, value, spend, audiences, log, links, settings) | Rapor, kampanyalar, öneriler, Stüdyo, sezon planı, testler, bağışçı değeri, harcama, kitleler, gönderim kaydı, reklam bağlantısı, ayarlar | `FlxAds*` (44 kayıtlı işlem, `plugins/flx-ads/graphql/operations/`) | `packages/modules/src/ads/AdsScreen.tsx` (`flxads-ads`), wp-admin varsayılanı; "Klasik görünüm" bağlantısı kullanıcı başına (`flxads_ui` meta). FluxApp modülü `flxads-ads`. |

## Tipler

Ana tipler: `FlxAdsContext`, `FlxAdsReport` (+ `FlxAdsTotals`, `FlxAdsRow`, `FlxAdsRecent`), `FlxAdsSpendPage`, `FlxAdsValuePage`, `FlxAdsSendLogPage`, `FlxAdsLinkBuilder`, `FlxAdsCampaignList`, `FlxAdsCampaignDetail`, `FlxAdsChange`, `FlxAdsSuggestionsPage`, `FlxAdsExperimentsPage`, `FlxAdsSeason`, `FlxAdsStudioPage`, `FlxAdsDraft`, `FlxAdsAudiencesPage`, `FlxAdsSettings`. Para alanları ortak `FlxMoney`, zamanlar `FlxDateTime`.

| Nullable alan | Neden |
|---|---|
| `FlxAdsTotals.spend`, `roas`, `cpa`, `cpn` | Harcama bilinmiyorsa |
| `FlxAdsCampaignList.error`, `FlxAdsCampaignDetail.error` | Meta sorunları isteği düşürmez, `error` metni döner |
| `FlxAdsSeason.id` | Hazır tarihlerin kimliği yok |
| `FlxAdsAudience.syncedAt`, `added`, `removed` | Hiç eşitlenmediyse |
| `flxAdsSuggestionsRefresh.newCount`, `flxAdsExperimentCreate.id` | Girdi reddedilince boş |

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-ads:settings` | `flxAdsContext`, `flxAdsSettings` | `flxAdsSettingsSave`, `flxAdsConnectionTest` |
| `FLXADS_Reports_Service::CACHE_TAG` | rapor, harcama, değer, kayıt sorguları | ayarlar, harcama ve gönderim mutation'ları |
| `FLXADS_Meta_Service::CACHE_TAG` | kampanya, öneri, test sorguları | Meta'ya dokunan mutation'lar |

## Açık sorular

- Klasik ekran ne zaman kaldırılacak (pilotla aynı karar: bir süre erişilebilir).
- MCP/AI araçları (`includes/mcp.php`, `ai-write.php`) şimdilik doğrudan klasik sınıfları çağırıyor; servislere geçirmek ayrı iş.
