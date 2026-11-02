# Flux SEO & MCP: diğer eklentiler için MCP ve sistem düzeltme API'si (v1.1.0)

MCP sunucusu: `/wp-json/flx-mcp/v1/mcp` (SEO & MCP › Yapay zekâ (MCP) sekmesinde açılır, kullanıcı başına anahtar). Asistan, anahtarı oluşturan kullanıcının yetkileriyle çalışır.

**Anahtar kapsamı (1.1.0):** anahtar oluşturulurken "Araçları sınırla" altından araç seçilirse anahtar yalnız o araçları görür (`tools/list`) ve çalıştırır (`tools/call` başka araçta "Bu anahtar bu aracı kullanamaz" hatası döner). Seçim yoksa eski davranış: kullanıcının bütün yetkileri. Kapsam `flxseo_mcp_keys` seçeneğinde anahtarın `tools` dizisidir. Ekran, TV gibi cihazlara konan anahtarlar için yalnız okuma araçlarıyla sınırlı anahtar açın.

## 1. Araç eklemek: `flxseo_mcp_tools`

```php
add_filter( 'flxseo_mcp_tools', function ( $tools ) {
	$tools['mail_templates'] = array(
		'title'       => 'E-posta şablonları',
		'description' => 'Şablonları listeler.',
		'readonly'    => true,             // yalnız okuma
		'cap'         => 'manage_options', // yoksa herkes (edit_posts) görür
		'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
		'run'         => function ( $args ) { return array( 'templates' => array() ); }, // dizi döner, hata için Exception
	);
	return $tools;
} );
```

Araç adları benzersiz olmalı; eklenti öneki kullanın (`flxm_…`, `flxb_…`). Her çağrı MCP günlüğüne yazılır; sık yoklanan okuma araçlarında `'log' => false` verilirse yalnız hatalı çağrılar yazılır.

## 2. Sistem Kontrolü düzeltmesi eklemek: `flx_system_fixes`

Yapay zekâ `system_checks` ile satırları okur, satırdaki `fixes` alanındaki kimliği `system_fix` ile uygular. Yalnızca bu filtreye kayıtlı düzeltmeler çalışır, yalnızca `manage_options` yetkili anahtarla. Her çalıştırma önceki/sonraki değerle `flxseo_system_fix_log` seçeneğine yazılır ve Görünüm › Sistem Kontrolü'nün altında görünür.

```php
add_filter( 'flx_system_fixes', function ( $fixes ) {
	$fixes['flxb.org_name'] = array(
		'label'       => 'Kurum adını ayarla',
		'description' => 'Makbuz ve e-postalarda görünen ad.',
		'source'      => 'Flux Donate',
		'checks'      => array( 'flxb.cfg.org' ),                  // çözdüğü kontrol satırı kimlikleri
		'value'       => array( 'type' => 'string' ),            // değer istemiyorsa null / yok; sabit seçenekler için 'enum'
		'current'     => function ( $value ) { return flxb_setting( 'org_name', '' ); },
		'run'         => function ( $value ) { /* doğrula + kaydet */ return 'Kurum adı kaydedildi.'; }, // hata: throw new Exception()
	);
	return $fixes;
} );
```

Kurallar: gizli anahtar/parola alanları düzeltme olarak açılmaz; `current` gizli değer döndürmez; `run` girdiyi kendisi doğrular. Kod ekleme alanları (script) ve MCP'yi kapatan ayarlar listeye konmaz.

## Hazır araçlar

- `order_summary` { recent (0–20, varsayılan 8), timezone (IANA, boşsa site) } (WooCommerce, `view_woocommerce_reports`): `today` = { orders, revenue, average (ödenmiş: `wc_get_is_paid_statuses()`), waiting, waiting_total (pending + on-hold), cancelled, truncated (>500 sipariş) }, `recent` = son siparişler { id, number, status, total (iade düşülmüş), currency, date_created_gmt, customer_name (ad + soyadın baş harfi), item_count }. Adres, e-posta, telefon dönmez. Başarılı çağrılar günlüğe yazılmaz (TV sipariş paneli dakikada bir çağırır).
- `system_checks` (status, source, search filtreli): tüm satırlar + özet + `fixes`.
- `system_fixes`: izinli düzeltmeler, değer şeması, mevcut değer.
- `system_fix` { fix, value }: uygular, önceki/sonraki değeri döner.
- `system_fix_log` { limit }: son düzeltmeler.

Tema düzeltmeleri: `site.timezone`, `site.search_visible`, `site.permalinks`, `theme.purge_cache`, `theme.migration` (Site Kurulumu hazır güncellemeleri), `wc.currency`, `theme.setting` (ga4_id, ga_mode, cookie_enabled, animasyon, dark_mode, bank_holder, bank_iban). SEO & MCP: `seo.browser_cache`, `seo.output`, `seo.setting` (phone, email, street, locality, postal, same_as, verify_google, verify_bing).
