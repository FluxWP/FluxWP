# flx-mail 1.22.0

- Faz: 3
- Durum: ✱
- PR:

Envanter (`ESLEME/flx-mail.md`). Servis, şema ve ekran yok. 42 REST ucu (39 GraphQL, 3 istisna), `wp_ajax_` yok, 9 admin sayfası. Kuyruk tetiği nopriv loopback değil: WP-Cron `flxi_run_queue` artı kimlikli `POST /queue/run`. Posta kutusu AI araçları `flxai_tools` üzerinde kalır.

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| WhatsApp Cloud webhook `GET`+`POST /wp-json/flxi/v1/wacloud/{id}/webhook` | flx-mail | Meta belirli URL'e doğrulama GET'i ve imzalı POST atar | `flxMailWacloudSetup` (verify token ve app secret yok), `flxMailWacloudTemplates` |
| Posta kutusu eki `GET /wp-json/flxi/v1/mailbox/item/{id}/attachment` | flx-mail | Ham dosya baytı; GraphQL JSON döner | `flxMailMailboxMessage` ek adı, boyutu ve türü |
| Toplu e-posta çıkışı `/?flxi_unsub=` ve eski `yti_unsub` (`GET`+`POST`) | flx-mail | E-posta istemcisi ve RFC 8058 List-Unsubscribe bu URL'e gider | `flxMailSettings.unsubscribedCount` |
| SMTP, IMAP ve bağlantı parolası / token yazma (`smtp_pass`, `smtp_nr_pass`, `imap_pass`, `api_key`, `password`, `token`, `app_secret`, verify token) | flx-mail | Gizli değer GraphQL'e girmez (KURALLAR §6). Okuma da yok | `*Set: Boolean`. Diğer alanlar `flxMailSettingsUpdate` ve `flxMailConnectionSave`. Yazma admin-post'ta kalır, boş alan eskisini korur |
| WP-Cron `flxi_run_queue`, `flxi_netgsm_poll`, `flxi_mailbox_sync`, `flxi_daily` | flx-mail | Zamanlayıcı kancası. Hostinger cron'u tetiklemez. Nopriv loopback yok; SEO `flxseo_audit_tick` ile aynı uç değil | `flxMailQueue` + `flxMailQueueRun`, `flxMailMailboxStatus` + `flxMailMailboxSync`, `flxMailNetgsmReport`. Günlük temizlik mutation değil |
