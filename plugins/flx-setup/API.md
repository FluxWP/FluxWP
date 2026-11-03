# FluxWP Panel API

Biçim: [docs/flx-setup/FORMAT.md](../../docs/flx-setup/FORMAT.md). Bu belge paket geliştiricileri içindir.

## Kurum profili
```php
$wa   = function_exists( 'flx_profile' ) ? flx_profile( 'whatsapp' ) : '';
$name = function_exists( 'flx_profile' ) ? flx_profile( 'name' ) : get_bloginfo( 'name' );
$ig   = flx_profile( 'social.instagram' );
```
Boş alanlarda: `name` → site adı, `legal_name`/`signature` → `name`, `tagline` → site sloganı, `email` → yönetici e-postası. Kurum adı, imza, telefon, IBAN gibi değerleri koda ya da varsayılan ayara yazmayın; profilden okuyun.

Filtreler: `flx_profile_fields` (alan ekle: `key => etiket`), `flx_profile_value` (`$value, $key`).

## Ön ayar vermek
```php
// Özellik eklentisi kendi ön ayarını taşır (ör. Flux Kurban):
add_filter( 'flx_presets', function ( $p ) {
	$p['kurban'] = __DIR__ . '/preset/preset.json';
	return $p;
} );
```
İnce ön ayar eklentisi `flx-preset-<id>` adıyla `preset/preset.json` taşırsa filtre gerekmez. Etkinleşince `flxk_preset_activated( '<id>' )` çağırın: boş sitede kendiliğinden uygulanır.

## İçerik türü kaydetmek
```php
add_filter( 'flx_preset_importers', function ( $t ) {
	$t['flxb_type'] = array(
		'label'   => __( 'Bağış türü', 'flx-donate' ),
		'find'    => function ( array $item ) { return flxb_find_type_by_key( $item['key'] ); },        // int, yoksa 0
		'data'    => function ( array $item ) { return flxb_type_from_preset( $item ); },              // uygulanacak hal
		'read'    => function ( int $id ) { return flxb_type_for_preset( $id ); },                     // aynı biçimde mevcut hal
		'save'    => function ( array $item, int $id ) { return flxb_save_type_from_preset( $item, $id ); }, // int|WP_Error
		'disable' => function ( int $id ) { flxb_disable_type( $id ); },                                // kaldırınca pasif, silme
		'url'     => function ( int $id ) { return flxb_type_url( $id ); },                            // isteğe bağlı
		// 'same' => function ( array $new, array $cur ) { return …; },                               // isteğe bağlı karşılaştırma
	);
	return $t;
} );
```
- `$item` yer tutucuları çözülmüş gelir (`@page:x` → ID, `{profile.name}` → değer).
- `data()` ve `read()` aynı biçimde dönmeli; motor ikisinin hash'ini karşılaştırır (aynı / değişecek / elle değiştirilmiş).
- `find()` önce kendi anahtarınızla (ör. `_flxk_key` meta'sı ya da `code` sütunu), sonra doğal anahtarla (slug) bulsun: var olan sitedeki kayıt sahiplenilir, kopya açılmaz.
- Türün şemasını kendi belgenizde yazın (ör. docs/flx-donate/ON-AYAR.md).

## Dışa aktarım
- `flx_preset_export_options`: "Bu siteden şablon çıkar"a dahil edilecek option adları.
- `flx_preset_export_content` (`$content, $args`): kendi içerik türünüzü dışa aktarım listesine ekleyin.
- `flx_preset_export` (`$preset, $args`): son hal.

## Diğer kancalar
| Kanca | Ne zaman |
|---|---|
| `flxk_option_written` (`$option, $value`) | ön ayar bir option'ı yazdıktan sonra (önbellek temizliği için) |
| `flxk_applied` (`$preset_id, $summary`) | uygulama bitince |
| `flxk_patch_submitted` (`$id, $patch`) | onay bekleyen yama oluşunca (bildirim için) |
| `flxk_option_allowed` (`$allowed, $name`) | option'ın yazılabilirliği |
| `flxk_secret_key_regex` | gizli alan adı deseni |
| `flxk_cap` | yönetim yetkisi (varsayılan `manage_options`) |
| `flxk_legal_urls` (`$urls`) | sihirbaz ve Ayarlar'daki yasal metin linkleri (`terms`, `privacy`, `kvkk`) |
| `flxk_telemetry_changed` (`$on`) | kullanım verisi izni verilince ya da geri alınınca |

## PHP fonksiyonları
`flxk_presets()`, `flxk_get_preset( $id )`, `flxk_plan( $id, $data, $opts )`, `flxk_apply_preset( $id, 'apply'|'fill' )`, `flxk_apply_migration( $id, $mig )`, `flxk_remove_preset( $id )`, `flxk_revert_run( $run )`, `flxk_export( $args )`, `flxk_patch_preview( $patch )`, `flxk_patch_submit( $patch, $source, $note )`, `flxk_patch_apply( $id_or_patch )`.

## Yasal onay ve kullanım verisi
- Sihirbazın ilk ekranında **Kullanım Şartları + Gizlilik Politikası** kabulü ve KVKK Aydınlatma Metni bilgisi zorunlu (Başlayalım ancak işaretlenince açılır). Kayıt `flxk_terms` = `{version, at, user}`; sürüm `FLXK_LEGAL_VERSION`. Sihirbaz atlanırsa onay kaydı olmaz, güncellemeler yine çalışır.
- **Kullanım verisi** (isteğe bağlı, varsayılan kapalı): `flxk_telemetry` = `'1'` açık, `flxk_telemetry_at` son değişiklik zamanı. FluxWP Panel › Ayarlar'dan geri alınır. Okumak için `flxk_telemetry_enabled()`.
- Kapalıyken `/v1/check` yalnız `packages`, `updater`, `install` ve `"usage": false` gönderir; `site`, `wp`, `php`, `tz`, `locale` gitmez. FluxWP sunucularına giden isteklerde WordPress'in site adresli User-Agent'ı `FluxWP-Panel/<sürüm>` olur.
- Fonksiyonlar: `flxk_telemetry_enabled()`, `flxk_set_telemetry( bool )`, `flxk_accept_terms()`, `flxk_terms_accepted()`, `flxk_legal_urls()`.

## MCP / Flux AI araçları
`preset_schema`, `preset_list`, `preset_export`, `preset_patch_preview`, `preset_patch_apply`, `preset_revert`, `preset_history` (Flux SEO'nun `flxseo_mcp_tools` filtresiyle; Flux AI sohbeti otomatik alır). Yetki: `flxk_cap`.
