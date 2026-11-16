# flx-mail 1.22.0

- Faz: 3
- Durum: ✱
- PR: #332

Envanter güncellendi (`ESLEME/flx-mail.md`). Servis, şema ve ekran yok. 42 REST ucu (39 GraphQL, 3 istisna), `wp_ajax_` yok, 9 admin sayfası. Parola ve bağlantı gizli alanı yazma-yalnız GraphQL girdisi (`""` korur, `"__clear__"` siler); okuma `FlxMailSecret`. Verify token `flxMailWacloudSetup.webhookVerifyValue`. Kuyruk tetiği nopriv loopback değil: WP-Cron `flxi_run_queue` artı kimlikli `flxMailQueueRun`. Posta kutusu AI düğmesi `flxMailDraftAi`; araçlar `flxai_tools` üzerinde kalır. Kişi `FlxPerson` (CRM yoksa null).

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| WhatsApp Cloud webhook `GET`+`POST /wp-json/flxi/v1/wacloud/{id}/webhook` | flx-mail | Meta belirli URL'e doğrulama GET'i ve imzalı POST atar | `flxMailWacloudSetup` (`webhookVerifyValue`; app secret yok), `flxMailWacloudTemplates` |
| Posta kutusu eki `GET /wp-json/flxi/v1/mailbox/item/{id}/attachment` | flx-mail | Ham dosya baytı; GraphQL JSON döner | `flxMailMailboxMessage` ek adı, boyutu ve türü |
| Toplu e-posta çıkışı `/?flxi_unsub=` ve eski `yti_unsub` (`GET`+`POST`) | flx-mail | E-posta istemcisi ve RFC 8058 List-Unsubscribe bu URL'e gider | `flxMailSettings.unsubscribedCount` |
| WP-Cron `flxi_run_queue`, `flxi_netgsm_poll`, `flxi_mailbox_sync`, `flxi_daily` | flx-mail | Zamanlayıcı kancası. Hostinger cron'u tetiklemez. Nopriv loopback yok; SEO `flxseo_audit_tick` ile aynı uç değil | `flxMailQueue` + `flxMailQueueRun`, `flxMailMailboxStatus` + `flxMailMailboxSync`, `flxMailNetgsmReport`. Günlük temizlik mutation değil |
