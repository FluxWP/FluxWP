# Flux Files — API

Sunucu dosya yöneticisi. Yalnız `flxfm_manage` yetkisi (varsayılan: `manage_options`, multisite'ta süper yönetici). Kök: WordPress kökü (`ABSPATH`); dışına çıkılmaz, kök dışını gösteren sembolik bağlar reddedilir.

## REST

Tüm uçlar `POST`, `X-WP-Nonce` ister, yanıtlar önbelleğe alınmaz (LiteSpeed dahil).

| Uç | Ne |
|---|---|
| `POST /wp-json/flx-files/v1/op` | `{op, path, …}`. `op`: `list`, `tree`, `read`, `write`, `mkdir`, `rename`, `move`, `copy`, `duplicate`, `trash`, `chmod`, `size`, `search`, `zip`, `unzip`, `versions`, `version`, `revert`, `trash_list`, `restore`, `purge`, `log`, `settings`, `share`, `ai_explain`, `ai_edit`. Yollar köke göredir (`wp-content/uploads/a.txt`). |
| `POST /wp-json/flx-files/v1/upload` | Parçalı yükleme: `file`, `dir`, `name` (göreli yol olabilir), `id`, `offset`, `total`, `overwrite`. Çakışmada `409 flxfm_exists`. |
| `admin-post.php?action=flxfm_file` | İndirme/önizleme: `path` ya da `paths` (satır satır, ZIP), `inline`, nonce `flxfm_file`. CSP sandbox başlığıyla gönderilir. |

`write`: `hash` (beklenen MD5; uymazsa `409`), `create_only`. Önceki içerik sürüm olarak saklanır.

## Koruma kuralları

- Kilitli (taşınamaz, silinemez, adı değişmez): kök, `wp-admin`, `wp-includes`, `wp-content` ve ana alt klasörleri, `wp-config.php`, `index.php`, `wp-load.php`, `wp-settings.php`, `wp-blog-header.php`, `.htaccess`, veri klasörü, eklentinin kendisi.
- Gizli (AI/MCP asla görmez, panelde uyarı): `wp-config*`, `.env*`, `.htpasswd`, `.user.ini`, `php.ini`, anahtar/sertifika (`.key`, `.pem`), `.sql`, `.db`, `.wpress`.
- Silme = çöp kutusu (`wp-content/flx-files-<rastgele>/trash`, web'e kapalı). Kalıcı silme yalnız çöp kutusundan, panelden.
- `DISALLOW_FILE_MODS`: salt okunur. `DISALLOW_FILE_EDIT`: kod dosyası (`.php`, `.htaccess` …) yazılamaz/yüklenemez.

## AI ve MCP

Araçlar `flx_mcp_tools` ile Flux AI'ya kaydolur (cap `flxfm_manage`, kaynak `flx-files`):

| Araç | Yazar mı |
|---|---|
| `flxfm_list`, `flxfm_read` (satır aralığı), `flxfm_search`, `flxfm_info`, `flxfm_activity`, `flxfm_trash_list` | Hayır |
| `flxfm_write`, `flxfm_replace` (tekil bul/değiştir), `flxfm_mkdir`, `flxfm_move`, `flxfm_copy`, `flxfm_delete` (çöpe), `flxfm_restore` | Evet |

- Flux AI sohbetinde yazan araçlar her seferinde onay kartı ister (`readonly: false`).
- Dış MCP istemcileri (`flx-mcp/` rotası) varsayılan salt okunur; Ayarlar › "Dış MCP istemcileri dosya değiştirebilir" açılmadıkça yazan araçlar `flx_mcp_tool_allowed` ile gizlenir ve çalıştırılamaz.
- AI/MCP gizli dosyaları okuyamaz, WordPress çekirdeğine yazamaz; kod dosyalarına yalnız "AI kod dosyası yazabilir" açıksa yazar.
- Editördeki "AI ile açıkla / düzenle" `flxai_chat()` kullanır; düzenleme önerisi editöre fark olarak gelir, kaydetmek kullanıcıdadır.

## Etkinlik

Her değişiklik `flxfm_log` seçeneğine yazılır (son 1000): zaman, kullanıcı, kaynak (`panel` / `ai` / `mcp`), işlem, yol, hedef. `do_action( 'flxfm_changed', $action, $path, $to, $via )`.

## Filtreler

| Filtre | Ne |
|---|---|
| `flxfm_root` | Kök klasör (varsayılan `ABSPATH`). |
| `flxfm_can_manage` | `flxfm_manage` yetkisini kullanıcıya verip vermeme (`$allowed, $user`). |
| `flxfm_locked_paths` | Kilitli yollar listesi (köke göre). |
| `flxfm_is_sensitive` | Bir yolun gizli sayılması (`$sensitive, $rel`). |
| `flxfm_log_size` | Saklanan etkinlik satırı (varsayılan 1000). |

## Sitede belge kütüphanesi

`[flx_files folder="wp-content/uploads/belgeler" view="list|grid" search="1" title=""]`

Yalnız panelden "Sitede paylaş" ile açılan `uploads` altı klasörler gösterilir. Gizli ve çalıştırılabilir dosyalar listelenmez. Alt klasör ve arama sorgu parametreleriyle çalışır (JS yok).
