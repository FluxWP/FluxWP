# flx-mail 1.24.0

- Faz: 3
- Durum: ✱
- PR: #332

Parça 1 uygulandı: Ayarlar, E-postalar (şablon düzenleyici dahil), Kayıtlar ve Sistem kontrolü GraphQL üzerinden (`FlxMailSettings`, şablon, kayıt ve sistem sorguları; ayar, şablon, önizleme, test, DNS, WooCommerce marka ve IMAP test mutasyonları). React ekran `flxi-mail` wp-admin’de varsayılan; klasik görünüm `flxi_ui` ile duruyor. Sözleşme testi `tools/tests/wp-graphql/flx-mail/contract-test.php`. Parça 2 uygulandı: Bağlantılar (Evolution, Flux WPAPI, Cloud API, Netgsm) ve WhatsApp Cloud kurulumu GraphQL üzerinden (`flxMailConnectionMeta`, `flxMailConnections`, `flxMailConnection`, `flxMailWa*`, `flxMailWacloudSetup`, `flxMailWacloudTemplates`; kaydet, sil, varsayılan yap, test, QR bağlan, yeniden başlat, çıkış, sunucu ayarı mutasyonları). Klasik sayfa, REST ve GraphQL `FLXI_Connection_Service` kullanır. React ekran Bağlantılar sekmesi tam eşdeğer. Parça 3 gönderim / kuyruk / kara liste, parça 4 posta kutusu.

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| WhatsApp Cloud webhook `GET`+`POST /wp-json/flxi/v1/wacloud/{id}/webhook` | flx-mail | Meta belirli URL'e doğrulama GET'i ve imzalı POST atar | `flxMailWacloudSetup.webhookUrl` ve `webhookVerifyValue` (yalnız yönetici). Webhook URL’si REST kalır |
| Posta kutusu eki `GET /wp-json/flxi/v1/mailbox/item/{id}/attachment` | flx-mail | Ham dosya baytı; GraphQL JSON döner | Bu parçada yok. Parça 4’te ad, boyut ve tür; bayt bu URL’de kalır |
| Toplu e-posta çıkışı `/?flxi_unsub=` ve eski `yti_unsub` (`GET`+`POST`) | flx-mail | E-posta istemcisi ve RFC 8058 List-Unsubscribe bu URL'e gider | `flxMailSettings.unsubscribedCount` (sayı). Çıkış URL’si kalır |
| WP-Cron `flxi_run_queue`, `flxi_netgsm_poll`, `flxi_mailbox_sync`, `flxi_daily` | flx-mail | Zamanlayıcı kancası. Hostinger cron'u tetiklemez | Bu parçada yok. Kuyruk parça 3, posta kutusu parça 4. Günlük temizlik mutation değil |
