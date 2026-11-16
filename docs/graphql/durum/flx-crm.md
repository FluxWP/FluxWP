# flx-crm 0.10.0

- Faz: 1
- Durum: ✓
- PR: #313

`gql/flx-crm-1`: servis katmanı, 28 işlem, `FlxContact` = ortak `FlxPerson`, sözleşme testi 121/121. React ekranı (`crm`) klasikle eşit, wp-admin varsayılanı. Açık borç (zorunlu, `gql/flx-crm-2`): Olaylar sayfası ve kişi kartı eklenti kancaları hâlâ klasik, Ahmet'in ekran kuralına aykırı. CRM'e özel FlxContact alanları `flxcrm_view` cap'li ve nullable; `flxcrm_gql_can_see_contact` filtresi. `flxcrm_sync` → `flxCrmSyncStep`. Belge `docs/flx-crm/GRAPHQL.md`, eşleme `ESLEME/flx-crm.md`
