# Flux Backup API (1.1.0)

Diğer eklentiler (ör. Flux Setup) Flux Backup'i `function_exists` ile kontrol edip çağırır. Hepsi `includes/api.php` içinde, `plugins_loaded`'dan önce tanımlıdır (eklenti dosyası yüklenince).

```php
if ( function_exists( 'flxy_is_ready' ) && flxy_is_ready() ) {
	$backup = flxy_backup_create( array(
		'type'    => 'pre_update',
		'paths'   => array( 'wp-content/plugins/flx-forms' ),
		'context' => array( 'source' => 'flx-setup', 'packages' => array(
			array( 'type' => 'plugin', 'slug' => 'flx-forms', 'name' => 'Flux Forms', 'from_version' => '2.0.8', 'to_version' => '2.0.9' ),
		) ),
		'keep_days' => 7,
		'sync'      => true,
	) );
	if ( is_wp_error( $backup ) ) { /* yedeksiz devam et ya da dur */ }
}
```

## Kayıt (record)

Her fonksiyon yedeği bu diziyle döndürür:

| Alan | Tür | Açıklama |
|---|---|---|
| `id` | string | `20261016-143906-21ug` biçiminde (UTC tarih + 4 rastgele harf) |
| `type` | string | `full` \| `db` \| `files` \| `pre_update` \| `manual` |
| `label` | string | Görünen ad; boşsa türden/bağlamdan üretilir (ör. "Flux Forms 2.0.8 → 2.0.9 öncesi") |
| `status` | string | `running` \| `ok` \| `failed` |
| `created` | int | Unix zamanı (başlangıç) |
| `size` | int | Zip boyutu, bayt (`ok` değilse 0) |
| `db` | bool | Veritabanı dahil mi |
| `paths` | string[] | ABSPATH'e göre yedeklenen yollar (`wp-content`, `wp-content/plugins/flx-forms` …) |
| `context` | array | Serbest bağlam. Bilinen anahtarlar: `source` (`manual`, `schedule`, `update`, `auto`, `cli`, `safety`, `api`, `upload` veya sizin değeriniz), `packages` (`[{type, slug, file, name, from_version, to_version}]`), `user`, `action` |
| `keep_until` | int\|null | Bu zamandan sonra günlük görev siler; null = süre yok (yalnız kota silebilir) |
| `locked` | bool | true ise kota ve saklama süresi asla silmez (elle silinebilir) |
| `file` | string | Zip'in mutlak yolu |
| `error` | string | Başarısızsa hata metni |

Ek alanlar (bilgi amaçlı): `finished` (int), `items` (geri yüklemede seçilebilecek alt yollar: eklenti/tema klasörleri, wp-content alt klasörleri), `files` (dosya sayısı), `wp_version`, `breakdown` (1.1.0, aşağıda).

Yüklenen yedeklerde (`context.source = 'upload'`) bağlamda ayrıca `original_name`, `original_site_url`, `original_created`, `original_label`, `original_source`, `original_prefix`, `original_version` bulunur. Yüklenen yedeğin süresi yoktur (`keep_until = null`) ve kilitli değildir: saklama süresi temizliği silmez, kotaya sayılır.

### `breakdown` (1.1.0)

Yedek bitince zip'in dizininden hesaplanır; eski yedeklerde ayrıntı sayfası ilk açıldığında hesaplanıp kayda yazılır. `FLXY_Breakdown::for_record( $record )` her ikisini de yapar.

```
v: 1, size: zip boyutu, raw: açılmış toplam, zip: arşivdeki toplam,
cats: {
  db | core | plugins | themes | uploads | other: {
    raw, zip, files (db'de satır sayısı),
    items: [ { n: ad, raw, zip, c: dosya/satır } ]  // en büyük 15
    more:  { raw, zip, c, k: öğe sayısı } | null      // kalanlar
  }
}
```

`plugins`/`themes` öğeleri klasör adı, `uploads` öğeleri yıl klasörü (`2026`) veya diğer alt klasör, `db` öğeleri tablo adı (raw = SQL dökümündeki bayt), `core` öğeleri `wp-admin`, `wp-includes` ve `/` (kök dosyalar).

## Fonksiyonlar

### `flxy_is_ready(): bool`
ZipArchive ve zlib var, depolama klasörü yazılabilir ve şu anda veritabanı geri yüklemesi sürmüyorsa `true`.

### `flxy_backup_create( array $args )` → array|WP_Error
| Argüman | Varsayılan | Açıklama |
|---|---|---|
| `type` | `manual` | `full`, `db`, `files`, `pre_update`, `manual` |
| `label` | `''` | Görünen ad |
| `db` | türe göre (`files` dışında true) | Veritabanı dahil mi (`db` türünde hep true) |
| `paths` | `full`: `wp-content` (+ çekirdek ayarı); `files`: `wp-content`; diğerleri: yok | ABSPATH'e göre yollar; yoksa `WP_Error flxy_path` |
| `context` | `array( 'source' => 'api' )` | Serbest dizi; `packages[].slug` `context_slug` aramasında kullanılır |
| `keep_days` | anahtar yoksa: `pre_update` için ayardaki gün (7), diğerleri süresiz | `null` = kilitli; `0` = süresiz, kilitsiz; `n` = n gün |
| `sync` | `false` | `true`: bu istekte biter, `status=ok` kayıt döner. `false`: arka planda başlar (AJAX/WP-Cron/loopback ile ~20 sn'lik adımlar), `status=running` kayıt döner; aynı anda yalnız bir arka plan işi olabilir (`WP_Error flxy_busy`) |
| `include_core` | ayar | Yalnız `full`: wp-admin, wp-includes ve kök dosyalar |

Önce tahmini boyut kadar kotada yer açılır (en eski kilitsiz yedekler silinir). Yer açılamazsa `WP_Error flxy_quota`; kayıt `failed` olarak listede kalır (7 gün). Hata kodları: `flxy_empty`, `flxy_path`, `flxy_zip`, `flxy_zlib`, `flxy_storage`, `flxy_busy`, `flxy_quota`, `flxy_failed` (eşzamanlı yedek yarıda kaldı; hata verisi kayıttır).

### `flxy_backup_get( string $id )` → array|null

### `flxy_backup_list( array $args = [] )` → array
Yeniden eskiye. Filtreler: `type` (string veya dizi), `status`, `context_slug` (bağlamdaki herhangi bir paketin slug'ı), `limit`.

```php
$last = flxy_backup_list( array( 'type' => 'pre_update', 'context_slug' => 'flx-forms', 'status' => 'ok', 'limit' => 1 ) );
```

### `flxy_backup_delete( string $id ): bool`
Dosyayı ve kaydı siler (kilitli olsa da). Çalışan arka plan işi silinmez.

### `flxy_backup_set_keep( string $id, $days ): bool`
`null` = kilitle (`keep_until` değişmez); `0` = kilidi aç, süresiz; `n` = kilidi aç, bugünden itibaren n gün.

### `flxy_pre_update_keep_days(): int` (1.2.0)
Güncelleme öncesi yedeklerin saklama süresi (gün, Ayarlar'daki değer).

### `flxy_set_pre_update_keep_days( int $days ): array` (1.2.0)
Süreyi kaydeder (1–365). Süreli ve kilitsiz mevcut `pre_update` yedeklerinin `keep_until` değeri `created + gün` olarak yeniden hesaplanır, süresi dolanlar hemen silinir. Döner: `['days' => int, 'deleted' => int]`. Ayarlar sayfasından süre değişince de aynısı uygulanır.

### `flxy_backup_restore( string $id, array $parts )` → true|WP_Error
Bu istekte, sonuna kadar çalışır (otomatik geri alma için uygun).

| Parça | Açıklama |
|---|---|
| `db` | bool. Veritabanını geri yükler (yedekte yoksa `flxy_nodb`). Önek farklıysa reddedilir. |
| `paths` | ABSPATH'e göre yollar; yedeğin `paths` değerlerinden biri veya altı olmalı (değilse `flxy_path`). |
| `safety` | bool, isteğe bağlı; yoksa ayar (varsayılan açık). Yalnız `db` için: önce mevcut veritabanının 7 gün saklanan yedeği alınır. |

- Eklenti/tema klasörü (`wp-content/plugins/<x>`, `wp-content/themes/<x>`, `wp-content/mu-plugins/<x>`) **değiştirilir**: mevcut klasör kenara alınır, yedekteki açılır, başarıyla bitince kenardaki silinir; hata olursa eski klasör geri konur. Güncellemeyle gelen yeni dosyalar kalmaz.
- Diğer yollarda dosyalar üzerine yazılır (fazla dosyalar silinmez).
- Korunanlar: `siteurl`, `home`, tüm `flxy_*` seçenekleri, Flux Backup'in etkin kalması, işlemi yapan kullanıcının oturumu. Flux Backup'in kendi klasörü ve depolama klasörü asla üzerine yazılmaz.
- Veritabanı geri yükleme sırasında başka bir arka plan işi varsa `flxy_busy`.

Otomatik geri alma örneği:

```php
$b = flxy_backup_list( array( 'type' => 'pre_update', 'context_slug' => 'flx-forms', 'status' => 'ok', 'limit' => 1 ) );
if ( $b ) {
	$ok = flxy_backup_restore( $b[0]['id'], array( 'paths' => array( 'wp-content/plugins/flx-forms' ) ) );
}
```

`FLXY_Pre_Update::current_backup_id()` bu istekte alınan güncelleme öncesi yedeğin kimliğini verir (yoksa null).

## Eylemler

| Eylem | Argümanlar | Ne zaman |
|---|---|---|
| `flxy_backup_created` | `$record` | Yeni yedek başarıyla bitti |
| `flxy_backup_appended` | `$record, $paths` | Aynı istekteki güncelleme öncesi yedeğe paket klasörü eklendi |
| `flxy_backup_failed` | `$record\|null, $message` | Yedek alınamadı |
| `flxy_backup_deleted` | `$record` | Yedek silindi |
| `flxy_backup_restored` | `$id, array( 'db' => bool, 'paths' => [] )` | Geri yükleme bitti |
| `flxy_backup_uploaded` | `$record` | Dışarıdan yüklenen yedek doğrulandı ve listeye eklendi (1.1.0) |

## Filtreler

| Filtre | Varsayılan | Açıklama |
|---|---|---|
| `flxy_pre_update_backup` | `true`, `$hook_extra` | false dönerse bu paket için güncelleme öncesi yedek alınmaz (Flux Setup kendi yedeğini alıyorsa kullanılır) |
| `flxy_pre_update_merge_window` | `180` | Eklentiler ekranındaki toplu güncelleme her paketi ayrı AJAX isteğinde kurar; bu kadar saniye içinde aynı kaynaktan alınmış güncelleme öncesi yedeğe eklenir. 0 = her istek ayrı yedek |
| `flxy_flx_themes` | `[]` | "Yalnız FluxWP paketleri" kapsamındaki temalar (eklentilerde `flx-` öneki yeter) |
| `flxy_storage_dir` | `wp-content/wp-snapshots/flx-backup` | Mutlak klasör yolu |
| `flxy_exclude_paths` | varsayılanlar + ayar | ABSPATH'e göre hariç yollar (`*` joker) |
| `flxy_db_tables` | önekli tablolar | Yedeklenecek tablolar |
| `flxy_db_batch_rows` | `500` (bellek <128 MB ise 200) | Bir SELECT'teki satır sayısı |
| `flxy_step_seconds` | `20` (tarayıcıdaki göstergenin sürdüğü adımda 8), `$interactive` | Bir adımın süresi; `max_execution_time` daha kısaysa ona göre kısalır |
| `flxy_step_bytes` | ölçülen hıza göre | Bir dosya alt adımında zip'e eklenecek en çok bayt (1.1.0) |
| `flxy_upload_chunk_size` | `min(8 MB, yükleme sınırı − 256 KB)` | Yedek yüklemede parça boyutu (1.1.0) |
| `flxy_upload_max_ratio` | `200` | Yüklenen zip'in açılmış boyutunun zip boyutuna en fazla oranı (1.1.0) |
| `flxy_use_loopback` | `true` | Arka plan işinin kendi kendine sürmesi (kapalıysa yalnız WP-Cron ve ekran) |
| `flxy_restore_keep_options` | `[]` | Veritabanı geri yüklenince güncel değeri korunacak ek seçenekler |

## Arka plan işi ve gösterge (1.1.0)

Yönetimden başlatılan yedek ve geri yükleme arka planda yürür; ekranda engelleyen pencere yoktur. Yönetici için yönetim çubuğunda (yönetim ekranları ve ön yüz) bir gösterge çıkar ve birkaç saniyede bir `admin-ajax.php?action=flxy_status` yoklar. Yoklama işin bir sonraki adımını da çalıştırır (loopback kapalı olsa da iş ilerler); aynı anda iki adımı depolama klasöründeki kilit önler.

Gösterge yalnız otomatik yüklenen küçük `flxy_indicator` seçeneğine bakar (`{kind, id, status: running|done|failed, at, seen}`); iş yoksa sayfaya hiçbir dosya eklenmez. Biten iş 30 dakika ya da göstergeye tıklanana kadar gösterilir.

AJAX (yönetici + `flxy` nonce): `flxy_status` (= `flxy_step`; işin belirteci `token` ile de kabul edilir), `flxy_seen`, `flxy_cancel` (adım sürüyorsa `pending`: adım bitince iptal edilir), `flxy_upload_start` / `flxy_upload_chunk` / `flxy_upload_finish` / `flxy_upload_abort`.

## Zip biçimi

```
manifest.json          id, type, label, created, site_url, home_url, wp_version, php_version,
                       db{included, prefix, engine, server, tables, rows}, paths, contents,
                       context, sizes{database_gz, files_raw, files_count},
                       checksums{"database.sql.gz": "sha256:…"}, excluded
database.sql.gz        (varsa) her INSERT tek satır, ifadeler ";\n" ile biter
files/<ABSPATH'e göre yol>
```

Dizin `flxy_backups` seçeneğindedir (autoload kapalı). Kaybolursa (ör. veritabanı başka yerden geri yüklendi) panel açılınca zip'lerdeki `manifest.json`'dan yeniden kurulur.

## Sistem kontrolü

`flx_system_checks` filtresine satırlar (ortak sözleşme): `flxy.storage`, `flxy.zip`, `flxy.quota` (>%80 uyarı, >%95 hata), `flxy.last`, `flxy.pre_update` (grup "Yedekleme") ve `flxy.cron` (grup `Cron`).
