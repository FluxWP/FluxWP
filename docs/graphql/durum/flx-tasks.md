# flx-tasks 0.9.0

- Faz: 0
- Durum: ✓
- PR: #300

Pilot: görev dilimi uçtan uca GraphQL (servis katmanı, 9 kayıtlı işlem, sözleşme testi 43/43, e2e). React ekranı klasikle eşit, wp-admin varsayılanı. **Kalan:** pilot dışındaki uçlar (sync, yapı, belgeler, ayarlar, pano, dosya) Faz 1 flx-tasks paket PR'ında. Eşleme `ESLEME/flx-tasks.md`

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Flux Tasks: pilot dışındaki `flx-tasks/v1` uçları (sync, yapı, belgeler, ayarlar, pano, dosya) | flx-tasks | Pilot yalnız görev dilimini taşıdı; ekran REST yedekli adaptörle çalışır | Geçici; kalkar: Faz 1 flx-tasks paket PR'ı (ESLEME/flx-tasks.md) |
