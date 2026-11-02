# Flux QR Menu — Bağlantı API'si (v1)

Menü sitesi (`qr-menu`) ile eklenti arasındaki sözleşmenin WordPress tarafı. Ortak sözleşme belgesi: proje klasöründe
`qr-menu-sistem/SOZLESME.md`. Bu belge onunla aynıdır; eklentiye özgü ayrıntılar **Ek** başlıklarıyla işaretli.

## Anahtar

- Biçim `flxq_` + 40 karakter `[A-Za-z0-9]` (`random_int`). Panelde **Bağlantı › Yeni anahtar oluştur**; yalnız bir kez gösterilir.
- Sitede yalnız `sha256(anahtar)` (`_flxq_key_hash`, ayrıca `flxq_keys` seçeneğinde özet → menü kimliği eşlemesi) ve
  `hook_secret = hash_hmac('sha256', 'flxq-hook', anahtar)` (hex, `_flxq_hook_secret`) saklanır.
- Yeni anahtar eskisini hemen geçersiz kılar.

## Veri ucu

`GET {site}/wp-json/flx-qr-menu/v1/feed` (kalıcı bağlantı kapalıysa `{site}/?rest_route=/flx-qr-menu/v1/feed`)

| Başlık | Zorunlu | Değer |
|---|---|---|
| `X-FLXQ-Key` | evet | menü anahtarı |
| `X-FLXQ-Site` | hayır | menü sitesinin kök adresi |
| `X-FLXQ-Hook` | hayır | anlık bildirim adresi (http/https) |
| `X-FLXQ-Client` | hayır | ör. `qr-menu/1.0.0` |
| `If-None-Match` | hayır | son ETag |

Yanıtlar (hepsinde `Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private`, `X-LiteSpeed-Cache-Control: no-cache`,
`CDN-Cache-Control: no-store`, `Pragma: no-cache`; ayrıca `litespeed_control_set_nocache` ve `DONOTCACHEPAGE`):

| Durum | Gövde |
|---|---|
| `200` + `ETag: "flxq-<menuId>-<version>"` | `{"ok":true,"menu":{"id","name","version","updated"},"data":{…}}` (`updated` ISO 8601, site saat dilimi) |
| `304` | gövde yok (ETag eşleşti; `W/` ve virgüllü liste, `*` kabul) |
| `401` | `{"ok":false,"code":"invalid_key"}` (anahtar yok, biçim yanlış, menü çöpte ya da silinmiş) |
| `423` | `{"ok":false,"code":"menu_disabled"}` (menü yayından kaldırılmış) |
| `429` | `{"ok":false,"code":"too_many_attempts"}` — **Ek**: aynı IP'den 10 dakikada 30 hatalı anahtar denemesinden sonra 10 dakika |

- Doğrulama `permission_callback` içinde yapılır (`__return_true` değil). Hatalı her deneme IP sayacını (`flxq_fail_<md5(ip)>` transient'i) artırır.
- Site kaydı: her başarılı (200/304) istekte `X-FLXQ-Site` (yoksa `ip:<IP>`) anahtarıyla `_flxq_sites` meta'sına
  `{url, hook, client, ip, first, last}`; aynı site için yazma en fazla 5 dakikada bir (hook/client/IP değişmedikçe).
  Menü başına en fazla 20 site; dolunca en uzun süredir görülmeyen çıkar. Panelden silinebilir.

### Menü verisi (**Ek** notlar)

- Şema SOZLESME.md §1. Kayıtta tipler düzeltilir (bool alanlar bool, `gunler[].tarih` `YYYY-MM-DD`, kategori `id` `[a-z0-9-]` ve tekil,
  `javascript:`/`data:` görsel/bağlantı adresleri boşaltılır); bilinmeyen alanlar ve `{}`/`[]` ayrımı korunur. En fazla 1 MB.
- `fiyat` / `eskiFiyat`: sayı, `null` **ya da metin** (mevcut menüde `"İkramımızdır"`, `"Hediyemizdir"` var). `"150"` / `"150,5"` sayıya çevrilir.
- `kapanis`: "Otomatik" seçilince alan silinir; `true` / `false` aynen.

## Anlık bildirim

Kayıt, geri alma, içe aktarma, MCP güncellemesi ve yayına alma/kaldırma sonrası, istek sonunda (`shutdown`; mümkünse
`fastcgi_finish_request` / `litespeed_finish_request` ile yanıt bittikten sonra) kayıtlı her farklı `hook` adresine:

```
POST <hook>
Content-Type: application/json
X-FLXQ-Signature: sha256=<hex hash_hmac('sha256', <ham gövde>, hook_secret)>
User-Agent: flx-qr-menu/1.0.0; https://example.org/

{"event":"menu.updated","menu":12,"version":8,"ts":1761300000}
```

`wp_remote_post` ile `blocking => false`, `timeout => 3`, yönlendirme yok. Yalnız http(s); `WP_DEBUG` kapalıyken `localhost`,
özel ve ayrılmış IP'lere çözülen adresler atlanır (`reject_unsafe_urls` de açık). Yayından kaldırmada da bildirim gider
(menü sitesi yeniden çekince `423` alır).

## Bu sitede yayınlanan menü (1.1.0)

Sayfalar (WordPress'in `do_parse_request` aşamasında, yalnız `flxq_routes` seçeneğine bakılarak; yeniden yazma kuralı yok):

| Adres | Sayfa |
|---|---|
| `/<adres>/` | menü (`public/menu.html` şablonu) |
| `/<adres>/tesekkur/` | teşekkür sayfası (`public/tesekkur.html`) |
| `/<adres>`, `/<adres>/tesekkur` | `301` → sonu `/` olan adres (sorgu korunur) |
| `https://<alt-alan-adı>/`, `/tesekkur/` | aynı sayfalar, `Host` başlığı eşleşince |
| `/` ve `/tesekkur/` | Ayarlar › Ana sayfa: `blank` WordPress'e kalır, `redirect` → `302` menünün adresi, `menu` → menü |

- Yalnız `GET`/`HEAD`. Ana sayfa ve alt alan adı kökünde `menu`, `qr`, `ref`, `fbclid`, `gclid`, `utm_*` dışında sorgu parametresi
  varsa (ör. `?rest_route=`, `?p=`) istek WordPress'e bırakılır.
- Menü verisi `<script type="application/json" id="menu-data">` içine `JSON_HEX_TAG|JSON_HEX_AMP` ile yazılır; `description`, `og:*`,
  `twitter:*`, `<title>` menüden; `og:url` sayfanın adresi; `og:image`, simgeler `wp-content/plugins/flx-qr-menu/public/`.
  Göreli görsel yolu (`img/x.jpg`) `public/img/x.jpg` ya da örnek görsel `assets/sample/img/x.jpg` (1.3.0) varsa oradan, yoksa boş;
  tam adresler olduğu gibi.
  Kapanışta yönlendirme `/<adres>/tesekkur/`'a; `?menu` ile atlanır.
- Yayında olmayan / çöpteki menü: `404`, "Menü yayında değil" sayfası.
- Başlıklar: `Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private`, `X-LiteSpeed-Cache-Control: no-cache`,
  `CDN-Cache-Control: no-store`, `do_action( 'litespeed_control_set_nocache' )`, `DONOTCACHEPAGE`.

### Sürüm ucu (canlı güncelleme)

```
GET /?rest_route=/flx-qr-menu/v1/rev/<id>        (ya da /wp-json/flx-qr-menu/v1/rev/<id>)
If-None-Match: "flxq-rev-<id>-<sürüm>"            (isteğe bağlı)
```

- Kimlik gerekmez. `permission_callback` yalnız yayındaki (etkin, çöpte olmayan) menüye izin verir;
  aksi halde `404 {"code":"flxq_menu_off"}`.
- `200 {"v": <sürüm>}` + `ETag: "flxq-rev-<id>-<sürüm>"`; ETag eşleşirse gövdesiz `304`. Önbelleksiz başlıklar feed ile aynı.
- Sayfadaki betik aynı kökenden (`?rest_route=` biçimi, alt alan adında da CORS gerekmez) sekme görünürken 20 sn'de bir sorar
  (`flxq_poll_seconds` filtresi, en az 5), hata olursa bekleme ikiye katlanır (en çok 5 dk). Sürüm değişirse kaydırma konumunu
  `sessionStorage`'a yazıp yeniler; `flxq_menu_off` gelirse "yayında değil" sayfasına, o sayfada `200` gelirse menüye döner.

## Yönetim REST uçları (panel için)

`flx-qr-menu/v1/menus/<id>` altında, `flxq_edit_menus` yetkisi + `X-WP-Nonce` ile; yazma hep `POST`. Menü yöneticisi yalnız açtığı
(`post_author`) ya da atandığı (`_flxq_editors`) menüye erişir, diğerleri `403 flxq_forbidden`:
`GET /` (düzenleyici verisi; yönetici olmayana `sites` boş, anahtarın son 4 hanesi yok), `POST /` (`name`, `data_json`, `base_version`,
`force` → sürüm değiştiyse `409 flxq_conflict`, `data.changes` araya giren kayıtlar), `POST /toggle` (`enabled`),
`GET /history?page=`, `GET /history/<hid>`, `POST /history/<hid>/restore` (`base_version`),
`POST /address` (`slug`, `host`; `flxq_set_address` yetkisi; `400 flxq_bad_slug|flxq_reserved_slug|flxq_bad_host`,
`409 flxq_slug_taken|flxq_host_taken`; yanıtta `warnings[]`), `POST /import-images` (`base_version`, `skip[]`; `upload_files`;
yanıt `{done, failed[{path,error}], remaining, saved, menu}`; 1.3.0: örnek görsele denk gelen yol yerel dosyadan eklenir, ekin `_flxq_src`
meta'sı `bundled:img/x.jpg`; menü sitesi yoksa ve örnek görsel de yoksa `400 flxq_no_base`), düzenleyici yanıtında `bundled {base, files[]}`,
`POST /preview` (1.2.0; `data_json` kaydedilmemiş veri, yoksa kayıtlı menü; hiçbir şey yazmaz, `flxq_normalize` ile doğrular → `400 flxq_invalid`;
yanıt `{html}`: yayındaki sayfayla aynı şablon, yoklama betiği ve teşekkür sayfasına geçiş yok, sandbox'lı iframe'de `srcdoc` için).
Yalnız `flxq_manage_menus`: `POST /key`, `POST /sites/remove` (`site`), `POST /editors` (`users[]`).

## Yetkiler

| Yetki | Kimde | Ne |
|---|---|---|
| `flxq_manage_menus` | yönetici (`manage_options` olan herkes) | tüm menüler, anahtar, siteler, düzenleyenler, ayarlar; aşağıdakilerin hepsi |
| `flxq_edit_menus` | Menü yöneticisi rolü (`flxq_menu_manager`: `read`, `upload_files`, `flxq_edit_menus`) | açtığı/atandığı menüler |
| `flxq_create_menus` | kullanıcı başına | menü açma, kopyalama |
| `flxq_set_address` | kullanıcı başına | adres ve alt alan adı |

Saklama: `flxq_routes` (autoload) `{slugs:{menu:12}, hosts:{"menu1.menu.example.org":12}}`, menü metaları `_flxq_slug`, `_flxq_host`,
`_flxq_editors` (kullanıcı kimlikleri); `flxq_settings` `{delete_on_uninstall, front_mode: blank|redirect|menu, front_menu}`.
Aktarılan görseller: ek metası `_flxq_src` (kaynak adres), `_flxq_menu`.

## Geçmiş tablosu

`{prefix}flxq_history`: `id, menu_id, version, user_id, created_gmt, action (create|save|restore|import|key|toggle|mcp|address|editors|images), summary, data`
(`data` menü JSON'u; `key`, `toggle`, `address`, `editors` kayıtlarında `NULL`). Menü başına son 300 kayıt tutulur. Şema sürümü `flxq_db_version`.

## PHP / MCP

- `flxq_save_menu( $id, $name|null, $data, array( 'action', 'base_version', 'force' ) )` → `{version, unchanged, summary}` ya da `WP_Error`.
- `flxq_menu_data( $id, $assoc = false )`, `flxq_menu_info( $id )`, `flxq_generate_key( $id )`, `flxq_set_enabled( $id, $bool )`.
- `flxq_set_address( $id, $slug, $host )`, `flxq_set_editors( $id, $user_ids )`, `flxq_menu_urls( $id )`, `flxq_can_edit_menu( $id, $uid = 0 )`,
  `flxq_import_images( $id, $base_version, $skip )`.
- MCP (`flxseo_mcp_tools`, yetki `flxq_edit_menus`; menü yöneticisi yalnız kendi menüleri, diğerleri istisna kodu 403):
  `flxq_list_menus` (`urls` dahil), `flxq_get_menu {menu_id}`, `flxq_update_item {menu_id, category, item, fiyat?, bitti?, gizli?}`.
- Sistem kontrolü (`flx_system_checks`): `flxq.history`, `flxq.menus`, `flxq.routes`, `flxq.keys`, `flxq.sites`.
- Filtreler: `flxq_reserved_slugs`, `flxq_poll_seconds`, `flxq_reject_unsafe_media_urls`.
