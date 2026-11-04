---
title: MCP aracı yazmak
slug: mcp-araci-yazma
parent: ai-destekli-eklenti
order: 1
excerpt: flx_mcp_tools ile eklentinizin işlevlerini Flux AI sohbetine ve dış MCP istemcilerine (Claude, Cursor) açın; kaynak ve istem ekleyin.
---
Flux AI ortak bir **MCP sunucusu** çalıştırır: `/wp-json/flx-mcp/v1/mcp` (Streamable HTTP, JSON-RPC 2.0). Kaydettiğiniz araç hem dış MCP istemcilerinde (Claude, Cursor, Claude Code) hem de Flux AI'nin yönetim sohbetinde görünür.

## Araç kaydı

```php
add_filter( 'flx_mcp_tools', function ( $tools ) {
	$tools['flxex_list_items'] = array(
		'title'       => __( 'Öğeleri listele', 'flx-ornek' ), // arayüzde görünen ad
		'description' => 'Lists items with status and date. Use q to filter by title.', // modele gider, İngilizce
		'readonly'    => true,                 // false: değiştirir → sohbette onay kartı
		'cap'         => 'edit_posts',         // yetkisi olmayan kullanıcı aracı görmez
		'source'      => 'flx-ornek',
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'q'     => array( 'type' => 'string', 'description' => 'Search in titles.' ),
				'limit' => array( 'type' => 'integer', 'description' => 'Max 50.' ),
			),
		),
		'run'         => function ( $args ) {
			$items = flxex_find_items( (string) ( $args['q'] ?? '' ), min( 50, (int) ( $args['limit'] ?? 20 ) ) );
			return array( 'items' => $items ); // dizi → structuredContent
		},
	);
	return $tools;
} );
```

## Kurallar

1. **Önekli ad.** `flxex_list_items`, `list_items` değil. Ad `^[a-zA-Z0-9_-]{1,64}$`.
2. **İngilizce açıklama.** Model okur: ne yapar, ne döner, hangi durumda kullanılır. Kısa ve kesin.
3. **Yetki.** `cap` verin; `run` içinde nesne düzeyinde de kontrol edin (`current_user_can( 'edit_post', $id )`). Araç, MCP anahtarını oluşturan kullanıcının yetkisiyle çalışır.
4. **Değiştiren araçlar `readonly => false`.** Flux AI sohbetinde çalışmadan önce kullanıcıdan onay alınır. Silme gibi geri alınamayan işlemleri ayrı araç yapın ya da taslak / çöp kutusuna yönlendirin.
5. **Hata için Exception.** `throw new Exception( 'Item not found.' );` MCP'de `isError` olarak döner.
6. **Küçük çıktı.** Sonuç 30.000 karakterde kesilir. Liste araçlarında `limit` ve kısa alanlar döndürün; ayrıntı için ayrı "get" aracı yazın.
7. **Yoklama araçları** `log => false` ile Son işlemler'i doldurmaz.

> [!NOTE]
> Eski filtre `flxseo_mcp_tools` hâlâ çalışır, ama yeni kodda `flx_mcp_tools` kullanın.

## Yalnız sohbette görünen araç

Dış MCP'ye açılmaması gereken (ör. o anki ekrana bağlı) bir araç için `flxai_tools` filtresini kullanın. Biçim aynı; `run` ikinci parametre olarak `$ctx` (`user_id`, `chat_id`, `source`) alır.

## Kaynaklar ve istemler

```php
add_filter( 'flx_mcp_resources', function ( $res ) {
	$res['flxex://catalog'] = array(
		'name'        => 'catalog',
		'title'       => 'Item catalog',
		'description' => 'All items as Markdown.',
		'mimeType'    => 'text/markdown',
		'read'        => 'flxex_catalog_markdown', // string döndürür
		'cap'         => 'read',
	);
	return $res;
} );

add_filter( 'flx_mcp_prompts', function ( $p ) {
	$p['flxex_review'] = array(
		'title'       => 'Review items',
		'description' => 'Starts a review of open items.',
		'arguments'   => array( array( 'name' => 'status', 'required' => false ) ),
		'text'        => function ( $a ) { return 'Review the open items …'; },
	);
	return $p;
} );

add_filter( 'flx_mcp_instructions', function ( $lines ) {
	$lines[] = 'Items: use flxex_list_items before editing anything.';
	return $lines;
} );
```

## Test

1. Flux AI › MCP'de sunucuyu açın ve bir anahtar oluşturun.
2. Claude Code'a ekleyin:

```bash
claude mcp add --transport http fluxwp https://site.com/wp-json/flx-mcp/v1/mcp \
  --header "Authorization: Bearer <anahtar>"
```

3. Ya da yönetim panelindeki Flux AI sohbetinde aracı adıyla isteyin. Her çağrı Flux AI › MCP › Son işlemler'de görünür.

Örnek: bu wiki'nin araçları `flxwk_search`, `flxwk_get_page`, `flxwk_list_pages`, `flxwk_save_page` (`plugins/flx-wiki/includes/mcp.php`).
