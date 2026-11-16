# Flux AI & MCP (flx-ai): klasik → GraphQL eşlemesi

Paket sürümü: 0.26.0 · Önek: `flxai_` · Sahip thread: Faz 1 / flx-ai · Durum: **kısmen bitti** (0.23.0–0.25.0 merge; `gql/flx-ai-4` 0.26.0 PR'ında; kalan: MCP, sistem başı SDK ayarları, sohbet geçmişi)

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
| Zamanlanmış görevler (liste, form, çalıştır, duraklat, sil, aç/kapa, alıcı arama) | `flxAiTasks`, `flxAiRecipients`, `flxAiTaskSave`, `flxAiTaskRun`, `flxAiTaskSetActive`, `flxAiTaskDelete`, `flxAiTaskMarkRead`, `flxAiTasksToggle` | `ai/Tasks.tsx` (0.25.0; klasik işlemler `FLXAI_Tasks_Service` ile) |
| Skill'ler (liste, aç/kapat, dışa aktar, sil/sıfırla, form) | `flxAiSkills`, `flxAiSkill`, `flxAiSkillSave`, `flxAiSkillToggle`, `flxAiSkillDelete`, `flxAiSkillsSettings` | `ai/Skills.tsx` (0.26.0) |
| Skill klasörü (dosya aç/düzenle/sil/yükle) | `flxAiSkillFile`, `flxAiSkillFileSave`, `flxAiSkillFileDelete`, `flxAiSkillFileUpload` (base64) | `ai/Skills.tsx` Dosyalar sekmesi; indirme `downloadUrl` (nonce'lu) |
| Skill testleri (tetiklenme, fonksiyonel, sonuçlar) | `flxAiSkillEvalsSave`, `flxAiSkillTestRun` (sohbet modelini çağırır), sonuçlar `flxAiSkill.results` | `ai/Skills.tsx` Testler sekmesi (eski REST `skills/<slug>/test` klasikte durur) |
| İçe aktar | `flxAiSkillImport` (markdown ya da base64 dosya) | `ai/SkillsExtra.tsx` |
| Marka kiti | `flxAiBrand`, `flxAiBrandSave` | `ai/SkillsExtra.tsx` |

Yetki: hepsi `manage_options`. wp-admin'de ekranlar varsayılan, "Klasik görünüm" bağlantısı kullanıcı başına (`flxai_ui` meta). FluxApp modülü `flxai-admin`.

## Kalanlar

Sohbet geçmişi, MCP anahtarları/bağlantılar, sistem başı SDK ayarları.
