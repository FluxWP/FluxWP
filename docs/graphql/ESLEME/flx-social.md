# Flux Social (flx-social): REST → GraphQL eşlemesi

Paket sürümü: 0.3.0 · Önek: `flxsm_` · Sahip thread: Geçir Flux Social'ı GraphQL'e · Durum: bitti (PR `claude/project-thread-yhevwp`)

## Özet

- REST uçları: 36 kayıt (çoğu GraphQL'e taşındı, REST yedekli kalır; istisnalar aşağıda) · AJAX: 0 · Admin sayfası: 1 · MCP/AI aracı: 1 sunucu (araçlar değişmedi)
- İstisna: OAuth (`/oauth/*`), dış zamanlayıcı `/tick`, dosya yükleme `/flx/v1/upload` (ortak uç), eski `/calendar` (FluxApp eski sürümleri), `/media` multipart POST. Satırlar `ISTISNALAR.md`'de.
- Servisler: `includes/services/` (`FLXSM_Accounts_Service`, `FLXSM_Posts_Service`, `FLXSM_Admin_Service`), DTO'lar `class-flxsm-dto.php` (readonly, PHP 8.1). REST callback'leri değişiklik yapan işlerde servisleri çağırır; çıktı eski kurucularla üretilir, bayt bayt aynı (sözleşme testi karşılaştırır).
- Node tipleri: yok (gönderi/hesap `id` genel ID, `databaseId` sayı; düz sayı da kabul edilir).
- Gizli veri: belirteç, uygulama anahtarı ve hesap `meta` hiçbir alanda dönmez. Uygulama alanları yalnız `isSet`; dış zamanlayıcı adresi maskeli gelir, tam adres `flxSocialTickKeyReveal` ile (yalnız yönetici).

## REST uçları

| Yöntem | Yol | cap | → GraphQL | Not |
|---|---|---|---|---|
| GET | `/boot` | `flxsm_post` | `flxSocialConfig` + `flxSocialAccounts` + `flxSocialCounts` | `FlxSocialBoot` tek istek |
| GET | `/counts` | `flxsm_post` | `flxSocialCounts` | `due`: zamanlayıcının işi var mı |
| GET | `/accounts` | `flxsm_post` | `flxSocialAccounts` | |
| POST | `/accounts/{id}` | manage | `flxSocialAccountUpdate` | ad, aç/kapa, sıra |
| POST | `/accounts/order` | manage | `flxSocialAccountReorder` | |
| POST | `/accounts/{id}/delete` | manage | `flxSocialAccountDelete` | |
| POST | `/accounts/{id}/refresh` | manage | `flxSocialAccountRefresh` | |
| GET | `/whatsapp/targets` | manage | `flxSocialWhatsAppTargets` | |
| POST | `/whatsapp/add` | manage | `flxSocialWhatsAppAdd` | |
| GET | `/posts` | `flxsm_post` | `flxSocialPosts` (connection) | durum, hesap, ağ, arama, etiket, `mine`, zaman aralığı |
| GET | `/posts/{id}` | `flxsm_post` | `flxSocialPost` | |
| POST | `/posts`, `/posts/{id}` | `flxsm_post` | `flxSocialPostCreate`, `flxSocialPostUpdate` | `action`: DRAFT, SUBMIT, SCHEDULE, NOW |
| POST | `/posts/{id}/action` | `flxsm_post`/publish | `flxSocialPostAct` | onayla, geri gönder, iptal |
| POST | `/posts/{id}/delete` | `flxsm_post` | `flxSocialPostDelete` | `remote`: ağda da sil |
| POST | `/posts/bulk` | `flxsm_post` | `flxSocialPostBulkAct` | izin verilmeyenler atlanır, `issues` döner |
| POST | `/posts/{id}/duplicate` | `flxsm_post` | `flxSocialPostDuplicate` | |
| POST | `/targets/{id}/retry`, `remote-edit`, `remote-delete` | publish | `flxSocialTargetRetry`, `…RemoteEdit`, `…RemoteDelete` | |
| GET | `/stats` | `flxsm_post` | `flxSocialStats(days)` | |
| POST | `/stats/refresh` | manage | `flxSocialStatsRefresh` | |
| POST | `/ai` | `flxsm_post` | `flxSocialAiWrite` | Flux AI üzerinden |
| GET | `/media`, `/media/studio` | `upload_files` | `flxSocialMedia`, `flxSocialStudioMedia` | |
| POST | `/media` (adres) | `upload_files` | `flxSocialMediaImport` | |
| POST | `/media` (dosya) | `upload_files` | İSTİSNA → `/flx/v1/upload` + `flxSocialMediaAdd(uploadId)` | bağlam `flx-social/media` |
| GET/POST | `/settings` | manage | `flxSocialSettings`, `flxSocialSettingsUpdate` | |
| POST | `/settings/app/{app}` | manage | `flxSocialAppUpdate` | gizli alan boşsa eskisi kalır, `__clear__` siler |
| GET | `/log` | manage | `flxSocialLog` | son 200 satır |
| GET/POST | `/tick` | anahtar | `flxSocialTickRun` (açık panel) + İSTİSNA (dış cron) | adres `flxSocialTickKeyReveal` ile |
| GET | `/oauth/start`, `/oauth/callback/{app}`, `/oauth/pick/{key}` (GET/POST) | manage | İSTİSNA; başlangıç adresi `flxSocialConnectUrl` | ağ tarayıcıyı yönlendirir; dönüş `#/accounts?added=…&pick=…&error=…` |
| GET | `/calendar` | `flxsm_post` | İSTİSNA (geçici) | FluxApp eski sürümleri; kalkınca silinir |

## Admin sayfaları

| Sayfa (`page=`) | Ne gösterir | Kullandığı sorgular | İstemci ekranı |
|---|---|---|---|
| `admin.php?page=flx-social` | Pano, gönderiler, onaylar, takvim, hesaplar, ayarlar, oluşturucu, ayrıntı | `FlxSocialBoot`, `…Counts`, `…Stats`, `…Posts`, `…Post`, `…Accounts`, `…Settings`, `…Log`, `…Media` … (34 işlem) | `packages/modules/src/social/SocialScreen.tsx` (`flxsm-social`), wp-admin varsayılanı; "Klasik görünüm" bağlantısı kullanıcı başına (`flxsm_ui` meta). FluxApp modülü `flxsm-social` |

## MCP / AI araçları

`flxseo_mcp_tools` ile kayıtlı araçlar değişmedi; eski işlevleri çağırır (gönderi oluştur/zamanla/gönder, listele, düzenle, sil, ağda düzenle, pano). Hâlâ servis katmanına bağlanmadı: davranış aynı, ileride servise alınabilir.

## Tipler (özet)

| Tip | Not |
|---|---|
| `FlxSocialPost` + `FlxSocialPostConnection` | `scheduledAt` girdide Unix saniye (Int), çıktıda `FlxDateTime` |
| `FlxSocialTarget` | hesap başına metin/ortam/seçenek, `metrics`, `caps` |
| `FlxSocialAccount` | yalnız herkese açık profil; `status` ACTIVE / DISABLED / EXPIRED |
| `FlxSocialOptions` / `Input` | ağ seçenekleri; `caps.options` sunucudaki snake_case anahtarları taşır, alanlar camelCase |
| `FlxSocialStats` | toplamlar, günlük, ağ başına, takipçi, en iyi gönderi, hatalar |
| `FlxSocialSettings`, `FlxSocialApp` | gizli değer yok; `tickUrl` maskeli |

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-social:posts` | gönderi sorguları, sayaçlar, pano | gönderi ve hedef mutation'ları, zamanlayıcı |
| `flx-social:accounts` | hesap sorguları, `flxSocialConfig` | hesap mutation'ları, OAuth dönüşü |
| `flx-social:settings` | ayar, uygulama, günlük | ayar/uygulama mutation'ları |

Sorgular kullanıcıya özeldir ve değişkendir; yanıtlar `no-store` kalır (REST'teki gibi).

## Açık sorular

- Klasik ekran ne zaman kaldırılacak (pilotla aynı karar: bir süre erişilebilir).
- MCP araçları servis katmanına taşınsın mı (davranış değişmez).
- `/calendar` ve FluxApp'in eski `social` modül kimliği: en düşük desteklenen uygulama sürümü GraphQL'e geçince kalkar.
