# Flux AI & MCP (flx-ai): klasik → GraphQL eşlemesi

Paket sürümü: 0.24.0 · Önek: `flxai_` · Sahip thread: Faz 1 / flx-ai · Durum: **kısmen bitti** (0.23.0 merge; `gql/flx-ai-2` 0.24.0 PR'ında; kalan: Skill'ler, Görevler, MCP)

## Özet

- Servisler: `includes/services/` (`FLXAI_Usage_Service`, `FLXAI_Settings_Service`, `FLXAI_Tools_Service`, DTO'lar, `FLXAI_Cast`). Klasik sayfalar, REST ve GraphQL aynı servisi okur.
- API anahtarları **yalnız yazılır** (`secrets: [{name, value, clear}]`); okuma: `flxAiSecrets` → `configured`, `hint` (son 4 karakter, ≥12 karakterli anahtarda), `fromConfig`. `flxai_settings` genel `flx_settings_schema` şemasından çıkarılır (kit regex'i `key_*`'i yakalamıyor; kit'e not).
- İstisna (ISTISNALAR): MCP sunucusu + OAuth, sohbet akışı (SSE), dosya yükleme.

## Bu PR'da geçenler

| Klasik | → GraphQL | İstemci ekranı |
|---|---|---|
| Genel bakış (harcama, rapor, grafikler) | `flxAiBudget`, `flxAiReport`, `flxAiLogSources`, `flxAiSecrets` | `ai/Overview.tsx` (`flxai-admin`, `startPage=overview`) |
| Kayıtlar + içerik penceresi | `flxAiLogs` (connection), `flxAiLogBody` | `ai/Logs.tsx` |
| Faturalama (aylar, CSV, OpenRouter kredisi) | `flxAiBilling` (`csvUrl` nonce'lu), `flxAiCreditsRefresh` | `ai/Billing.tsx` |
| Bağlı sistemler (aç/kapa) | `flxAiSystems`, `flxAiSystemsUpdate` | `ai/Systems.tsx` (sistem başı AI ayarı klasikte kalır) |
| Onay bekleyenler (bekleyen işlemler, düzenleyip onayla, değişiklik istekleri) | `flxAiApprovals`, `flxAiApprovalDecide`, `flxAiChangeDecide` | `ai/Approvals.tsx` (0.24.0) |
| Araçlar (aç/kapa, toplu, çakışma seçimi) | `flxAiTools`, `flxAiToolsUpdate`, `flxAiToolPick` | `ai/Tools.tsx` |
| Ayarlar (servis + GraphQL hazır) | `flxAiSettings`, `flxAiSecrets`, `flxAiSettingsOptions`, `flxAiModels`, `flxAiSettingsUpdate`, `flxAiModelTest`, `flxAiRenderTest` | `ai/Settings.tsx` (0.24.0; klasik `flxai_save_settings` aynı servisle kaydeder) |

Yetki: hepsi `manage_options`. wp-admin'de ekranlar varsayılan, "Klasik görünüm" bağlantısı kullanıcı başına (`flxai_ui` meta). FluxApp modülü `flxai-admin`.

## Kalanlar

Skill'ler (CRUD, dosyalar, testler, marka kiti), Zamanlanmış görevler, Sohbet geçmişi, MCP anahtarları/bağlantılar, sistem başı SDK ayarları.
