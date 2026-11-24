# flx-crm 0.12.0

- Faz: 1
- Durum: 👀
- PR: #333

`gql/flx-crm-2`: etkinlikler ekranı (`flxGatherings` ve 11 işlem daha, toplam 40 kayıtlı işlem) ve kişi kartı bölüm kaydı (`FlxContact.sections`, `flxcrm_register_person_section`). React Olaylar sekmesi klasikle aynı; wp-admin varsayılanı. Donate, Commerce ve Chat bölümlerini kendi paketlerinde kaydeder. Eşleme `ESLEME/flx-crm.md`. Review düzeltmeleri: React etkinlik formu paket denetimlerini (ör. `flxs_adopt`) gönderir, etkinlik eylem adresleri süzülür, önbellek temizliği tek kez çalışır, klasik hata metni aynı kaldı.


0.12.0: tüm CRM sayfaları `flx_admin_screen` kabuğunda, sayfa başına preload (`flxcrm_preload_for`). Klasik PHP sayfaları ve görünüm geçişi silindi; `flxcrm_person_card` ve kart admin-post işleyicileri kaldı.
