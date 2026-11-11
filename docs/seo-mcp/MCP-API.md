# Flux SEO: MCP araçları ve sistem düzeltme API'si (v1.5.0)

> **Flux SEO 1.5.0 / Flux AI 0.7.0:** MCP sunucusu, anahtarlar ve kayıt **Flux AI**'ya taşındı (Flux AI & MCP › MCP). Sunucu ve filtre belgesi: [docs/flx-ai/API.md › MCP sunucusu](../flx-ai/API.md#mcp-sunucusu-070). Bu dosya Flux SEO'nun kendi araçlarını ve `flx_system_fixes` API'sini anlatır.

MCP sunucusu: `/wp-json/flx-mcp/v1/mcp` (Flux AI & MCP › MCP ekranında açılır, kullanıcı başına anahtar). Asistan, anahtarı oluşturan kullanıcının yetkileriyle çalışır.

**Anahtar kapsamı (1.1.0):** anahtar oluşturulurken "Araçları sınırla" altından araç seçilirse anahtar yalnız o araçları görür (`tools/list`) ve çalıştırır (`tools/call` başka araçta "Bu anahtar bu aracı kullanamaz" hatası döner). Seçim yoksa eski davranış: kullanıcının bütün yetkileri. Kapsam `flxai_mcp_keys` seçeneğinde anahtarın `tools` dizisidir (eski `flxseo_mcp_keys` ilk açılışta oraya kopyalanır). Ekran, TV gibi cihazlara konan anahtarlar için yalnız okuma araçlarıyla sınırlı anahtar açın.

## 1. Araç eklemek: `flx_mcp_tools` (eski ad `flxseo_mcp_tools` de çalışır)

```php
add_filter( 'flx_mcp_tools', function ( $tools ) {
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
- `site_audit` { refresh, pagespeed (none|mobile|desktop), url, category, min_priority } (`manage_options`): PageSpeed tarzı denetim. `findings[]` = { id (Lighthouse kimliği), category, priority (critical|high|medium|low), title, detail, fix, owner (theme|content|server|flx-seo|setting), fix_id, items, sources }, `counts`, `pagespeed` (puanlar ve metrikler), `agentic` ("3/4"). Web isteğinde PageSpeed en çok 40 sn bekler; zaman aşımı hata olarak döner, aracın geri kalanı çalışır.
- `site_audit_history` { limit (1–90, varsayılan 10), with_findings } (`manage_options`): geçmiş raporlar, en yeni başta. `reports[]` = { time, trigger (auto|manual|mcp|fix), counts, scores { mobile|desktop }, metrics { fcp, lcp, tbt, cls, si }, agentic, new [{ id, title }], fixed [{ id, title }], findings? }, `schedule` = { frequency (daily|weekly|off), day (1=Pazartesi), hour, pagespeed, email, next_run }. Her `site_audit` çalıştırması (refresh/pagespeed) geçmişe `mcp` olarak yazılır. Kanca `flxseo_audit_done` ( $trigger ).
- `site_build_guide` { topic (all|workflow|performance|accessibility|seo|best-practices|agentic|wordpress|checklist), lang (tr|en) }: site yapım rehberi Markdown. Kaynak olarak da: `flxseo://guide/site-build`, `?lang=tr|en`, `flxseo://llms.txt`. Filtreler `flxseo_guide`, `flxseo_mcp_resources`, `flxseo_audit_findings`, `flxseo_audit_local_checks`, `flxseo_psi_categories`, `flxseo_llms_txt`, `flxseo_csp`.
- `system_checks` (status, source, search filtreli): tüm satırlar + özet + `fixes`.
- `system_fixes`: izinli düzeltmeler, değer şeması, mevcut değer.
- `system_fix` { fix, value }: uygular, önceki/sonraki değeri döner.
- `system_fix_log` { limit }: son düzeltmeler.

Tema düzeltmeleri: `site.timezone`, `site.search_visible`, `site.permalinks`, `theme.purge_cache`, `theme.migration` (Site Kurulumu hazır güncellemeleri), `wc.currency`, `theme.setting` (ga4_id, ga_mode, cookie_enabled, animasyon, dark_mode, bank_holder, bank_iban). SEO & MCP: `seo.browser_cache`, `seo.output`, `seo.setting` (phone, email, street, locality, postal, same_as, verify_google, verify_bing).
