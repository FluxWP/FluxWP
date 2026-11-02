# Flux Social API (0.1.0)

Önek `flxsm_`, REST `flx-social/v1`, metin alanı `flx-social`.

## Yetkiler
- `flxsm_post`: gönderi hazırla, onaya gönder.
- `flxsm_publish`: onayla, zamanla, şimdi paylaş, ağda düzenle/sil.
- `flxsm_manage`: hesaplar, uygulama anahtarları, ayarlar.
Hepsi `flx_role_caps_supported` ile Flux Roles'e kayıtlı.

## REST (nonce ile, panel)
`boot`, `accounts`, `accounts/{id}`, `accounts/order`, `accounts/{id}/delete|refresh`, `whatsapp/targets|add`, `posts` (GET liste, POST oluştur), `posts/{id}` (GET/POST), `posts/{id}/action` (submit|approve|reject|schedule|now|cancel|draft), `posts/{id}/delete|duplicate`, `posts/bulk`, `targets/{id}/retry|remote-edit|remote-delete`, `stats`, `stats/refresh`, `counts`, `ai`, `media`, `media/studio`, `settings`, `settings/app/{app}`, `log`, `oauth/start|callback/{app}|pick/{key}`.
`tick?key=...` herkese açık; anahtar Ayarlar › Genel'de (yenilenebilir).

## MCP araçları (filtre `flxseo_mcp_tools`)
| Araç | İş |
|---|---|
| `flxsm_accounts` | Bağlı hesaplar (id, ağ, ad, sınırlar). |
| `flxsm_posts` | Liste (status, account, search, from/to). |
| `flxsm_post_get` | Tek gönderi + hedef durumları. |
| `flxsm_post_create` | content, accounts[], per_account{id:{content,media_ids,options}}, media_ids, media_urls, tags, action draft/submit/schedule/now, schedule_at (site saati "Y-m-d H:i" ya da ISO), repeat_days. |
| `flxsm_post_update` | Alanları değiştir, ya da action cancel/approve/submit/schedule/now. |
| `flxsm_post_delete` | remote=true ise ağdan da siler. |
| `flxsm_remote_edit` | Yayınlanmış hedefin metnini/seçeneklerini ağda düzenle ya da sil. |
| `flxsm_stats` | days aralığında özet. |
| `flxsm_ai_write` | Flux AI ile metin üret (ağa göre). |
Yetkiler REST ile aynı; yayın araçları `flxsm_publish` ister.

## Kancalar
- Filtre `flxsm_apps`: uygulama/ağ ekle.
- Filtre `flxsm_autopost_skip( false, $post )`: otomatik paylaşımı atla.
- Eylemler: `flxsm_post_submitted`, `flxsm_post_rejected`, `flxsm_post_status`, `flxsm_target_published`, `flxsm_target_failed`, `flxsm_autopost_created( $id, $post )`.

## Entegrasyonlar
`flx_system_checks`, `flxapp_modules` (social), `flxa_app_screens`, `flxa_apps`, `flxai_systems` (flx-social), Flux Studio medya, WhatsApp için Flux Mail/Çağrı Merkezi Evolution ayarı.

## Yeni ağ eklemek
`includes/providers/` altında `FLXSM_Provider`'dan türet (caps, auth_url, exchange, accounts, publish adımları, metrics, remote_edit/delete), `flxsm_providers()` ve `flxsm_apps` kaydı.
