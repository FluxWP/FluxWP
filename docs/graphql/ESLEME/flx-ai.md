# Flux AI & MCP (flx-ai): klasik → GraphQL eşlemesi

Paket sürümü: 0.23.0 · Önek: `flxai_` · Sahip thread: Faz 1 / flx-ai · Durum: **kısmen bitti** (PR `gql/flx-ai-1`; kalan iş `gql/flx-ai-2`)

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
| Araçlar (aç/kapa, toplu, çakışma seçimi) | `flxAiTools`, `flxAiToolsUpdate`, `flxAiToolPick` | `ai/Tools.tsx` |
| Ayarlar (servis + GraphQL hazır) | `flxAiSettings`, `flxAiSecrets`, `flxAiSettingsOptions`, `flxAiModels`, `flxAiSettingsUpdate`, `flxAiModelTest`, `flxAiRenderTest` | React ekranı **sonraki PR** |

Yetki: hepsi `manage_options`. wp-admin'de ekranlar varsayılan, "Klasik görünüm" bağlantısı kullanıcı başına (`flxai_ui` meta). FluxApp modülü `flxai-admin`.

## Kalanlar (gql/flx-ai-2)

Ayarlar React ekranı (klasik `flxai_save_settings` servise devredilecek), Onay bekleyenler (`flxai_approvals_*`, model adımı çağırır), Skill'ler (CRUD, dosyalar, testler, marka kiti), Zamanlanmış görevler, Sohbet geçmişi, MCP anahtarları/bağlantılar, sistem başı SDK ayarları.
