# Flux Tasks (flx-tasks): REST → GraphQL eşlemesi

Paket sürümü: 0.9.0 · Önek: `flxg_` · Sahip: pilot (#300) · Durum: uygulanıyor (pilot dilimi bitti, kalan uçlar Faz 1)

## Özet

- REST uçları: 70 (`flx-tasks/v1`) · AJAX: 0 · Admin sayfası: 3 (`flx-tasks`, `flx-tasks-pano`, `flx-tasks-ayarlar`) · MCP aracı: Flux AI üzerinden
- GraphQL'de (pilot): görev okuma/listeleme, oluşturma/düzenleme/silme, takip, yorum, bildirim okundu. 9 kayıtlı işlem (`graphql/persisted-queries.json`).
- İstemci: `packages/graphql` adaptörü (`flxgGraphQL`); GraphQL yoksa ya da işlem desteklenmiyorsa REST'e düşer (kalıcı yedek).
- İstisna: ISTISNALAR.md geçici satırı "Flux Tasks: pilot dışındaki uçlar".
- Servis: `includes/services/class-flxg-task-service.php` (`FLXG_Task_Service`); REST ve GraphQL aynı metotları çağırır.
- Node tipi: `FlxTask` (loader `FLXG_Task_Loader`, `flx_gql_loaders` aksiyonunda).

## REST uçları

| Yöntem | Yol | → GraphQL | Durum |
|---|---|---|---|
| GET | `/tasks/{id}` | `flxTask` (`FlxTaskDetail`) | ✓ |
| GET | `/tasks` | `flxTasks` (`FlxTaskList`) | ✓ (50'den büyük listeler REST) |
| POST | `/tasks` | `flxTaskCreate` | ✓ |
| POST/PATCH | `/tasks/{id}` | `flxTaskUpdate` (`FlxTaskUpdate`, `FlxTaskUpdateDetail`) | ✓ |
| DELETE | `/tasks/{id}` | `flxTaskDelete` | ✓ |
| POST | `/tasks/{id}/watch` | `flxTaskWatch` | ✓ |
| POST | `/tasks/{id}/comments` | `flxTaskCommentCreate` | ✓ |
| POST | `/notifications/read` | `flxTaskNotificationsRead` | ✓ |
| GET | `/boot`, `/home`, `/sync`, `/notifications` | — | Faz 1 (`sync` hafif yoklama; toplu okuma) |
| * | `/spaces*`, `/folders*`, `/lists*`, `/fields*`, `/tags*`, `/views*` | — | Faz 1 (yapı yönetimi) |
| * | `/docs*`, `/doc-pages*` | — | Faz 1 (belge düzenleyici `assets/js/docs.js`, iki ekranda ortak) |
| GET/POST | `/settings`, `/dashboard`, `/dashboard/scan` | — | Faz 1 |
| * | `/files*`, `/comments/{id}`, `/tasks/bulk`, `/me` | — | Faz 1 (yükleme ortak uca taşınır) |
| POST | `/ai/chat`, `/ai/task/{id}`, `/ai/thread`, `/doc-pages/{id}/ai` | — | AI akışı istisna; başlatma Faz 1 |
| * | `/app/*` | — | Eski FluxApp sürümleri (geçici istisna, Faz 7) |

## Admin sayfaları

| Sayfa (`page=`) | Ne gösterir | İstemci ekranı |
|---|---|---|
| `flx-tasks` | Görevler (varsayılan yeni görünüm, tam ekran) | `packages/modules/src/tasks` (wp-embed); klasik `assets/js/*.js` profil bağlantısıyla |
| `flx-tasks-pano` | Kullanım panosu | Aynı ekran, `startPage: 'dashboard'` |
| `flx-tasks-ayarlar` | Ayarlar | Aynı ekran, `startPage: 'settings'` |

## Ortak kaynaklar

- Stil: `assets/css/app.css` tek kaynak; React stili `tools/gen-react-css.py` ile üretilir.
- Metin: klasik Türkçe kaynak + `includes/js-strings.php`; React sözlükleri `tools/gen-react-i18n.py` ile `languages/messages.*.json`'dan üretilir.

## Açık sorular

- Klasik görünüm ne zaman kaldırılır (Ahmet kararı).
