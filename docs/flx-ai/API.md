# Flux AI — Geliştirici API'si

**Sürüm 0.8.0.** Diğer FluxWP eklentileri yapay zekâyı kendi kodları yerine Flux AI üzerinden çağırır: anahtar, model, önbellek, harcama sınırı ve raporlar tek yerde. Flux AI yoksa eklenti eski koduna düşer.

## Geçiş kalıbı (her paket)
```php
function flxc_ai_request( $messages, $opts ) {
	if ( function_exists( 'flxai_chat' ) && flxai_ready( 'flx-chat' ) ) {
		$opts['source'] = 'flx-chat';
		return flxai_chat( $messages, $opts );     // aynı OpenAI biçimi
	}
	return flxc_ai_request_legacy( $messages, $opts ); // eski kod (Flux AI yoksa)
}
```
- Kendi model alanınızı koruyabilirsiniz: doluysa `model` olarak gönderin; boşsa Flux AI kaynak modeline (Ayarlar › Eklentiye özel model) ya da varsayılana düşer.
- Ayar sayfanızda "Anahtarlar Flux AI'den gelir" notu gösterip kendi anahtar alanınızı Flux AI varken gizleyebilirsiniz.

## Fonksiyonlar

### `flxai_chat( array $messages, array $opts = [] ) : array|WP_Error`
Mesajlar OpenAI biçiminde: `{role: system|user|assistant|tool, content, tool_calls?, tool_call_id?}`.
Sistem içeriği parça dizisi olabilir; `{type:'text', text:'…', cache:true}` parçası Claude'da önbellek kırılma noktasıdır (sabit talimatı başa koyun, değişen bilgiyi sonra).

| `$opts` | Açıklama |
|---|---|
| `source` | Çağıran eklenti slug'ı (`flx-chat`, `flx-tasks`, `flx-certificates`, `flx-donate`, `flx-seo`, `flx-mail`, `flx-forms`). Raporlar ve kaynak modeli için **mutlaka verin**. |
| `purpose` | Kısa amaç etiketi: `bot`, `suggest`, `title`, `receipt` … (raporda "Amaca göre"). |
| `model` | `claude:claude-haiku-5-5`, `claude-sonnet-5-5` (önek opsiyonel), `openrouter:google/gemini-2.5-flash` ya da `google/gemini-2.5-flash`. Boşsa kaynak modeli → varsayılan. |
| `tools` | OpenAI araç tanımları. Claude'a otomatik çevrilir. |
| `max_tokens` | Görünen cevap sınırı (varsayılan 1024). Düşünen Claude modellerinde düşünmeye ek pay bırakılır. |
| `temperature` | Destekleyen modellerde (Claude 4.7+/5 ailesinde gönderilmez). |
| `timeout` | Saniye, varsayılan 45. Hostinger ~60 sn'de keser. |
| `cache` | `off` / `5m` / `1h`; boşsa ayar. |
| `effort` | `low` / `medium` / `high` (Claude). |
| `thinking` | `true` → düşünme özeti `_thinking` alanında döner. |
| `user_id` | Varsayılan geçerli kullanıcı. |
| `ignore_limit` | Harcama sınırını yok say (yalnız yönetici testleri). |

Dönen asistan mesajı: `content`, `tool_calls?`, `_thinking?`, `_finish` (`stop|tool_calls|length`), `_usage {provider, model, in, out, cr, cw, cost, full, est, ms}`, `_log_id`.
Claude cevabında `_raw` ve `_raw_model` de vardır: araçlı turlarda mesajı geçmişe olduğu gibi geri eklerseniz düşünme blokları korunur.

Hatalar (`WP_Error`): `flxai_limit` (402, harcama sınırı), `flxai_key` (anahtar yok), `flxai_model`, `flxai_http` (ağ), `flxai_empty` (boş cevap), `flxai_api` (sağlayıcı; `data.http`, `data.msg`).

### `flxai_complete( string $prompt, array $opts = [] ) : string|WP_Error`
Tek istem → metin. `$opts['system']` sistem talimatıdır; diğer seçenekler `flxai_chat` ile aynı.

### `flxai_ready( string $source = '' ) : bool`
Seçilen modelin sağlayıcısının anahtarı var mı. Flux AI kurulu olsa bile anahtar yoksa eski koda düşmek için kullanın.

### `flxai_model_for( string $source = '', string $given = '' ) : string`
Kullanılacak model: verilen → kaynak modeli → varsayılan.

### `flxai_is_account_error( $e ) : bool`
Hesap düzeyinde hata mı (harcama sınırı, anahtar, kredi, 401/402/429, sistemin Flux AI'de kapatılması `flxai_source_off`). `true` ise toplu iş durmalı; sonraki istekler de reddedilir.

### `flxai_source_enabled( string $source ) : bool`
Flux AI & MCP › Bağlı sistemler'de bu kaynak açık mı. Kapalıysa `flxai_chat` `flxai_source_off` hatası döner (kayda "Kapalı" düşer). `flxai_ready()` kapalı sistem için de `true` kalır; böylece eklenti kendi eski AI koduna geçmez, AI gerçekten kapalı olur. Arayüzde "AI kapalı" göstermek isterseniz bu fonksiyona bakın.

### `flxai_log( array $row ) : int`
Kendi araç sürenizi rapora yazmak için: `flxai_log( [ 'kind' => 'tool', 'source' => 'flx-chat', 'tool' => 'donation_options', 'ms' => 120 ] )`. Model çağrıları `flxai_chat` içinde otomatik kaydedilir.

### `flxai_budget_status() : array`
`day|week|month => {label, limit, spent, pct, over, resets}` (USD, site saat dilimi).

### `flxai_enqueue_model_picker()`
Ayar sayfanızda aranabilir model listesi: fonksiyonu çağırın, alana `data-flxai-model` ekleyin (`<input name="model" data-flxai-model>`). JS: `FLXAIModelPicker.attach(input)`. Liste yöneticiye açıktır (REST `GET /flx-ai/v1/models`).

## Görseller (```widget / ```mermaid)
Model cevabında ```widget bloğu (HTML + inline CSS ya da SVG) ve ```mermaid bloğu yazabilir. Ortak sohbet bunları sandbox iframe'de çizer. Kendi kanalınızda kullanmak için:

### `flxai_widget_rules( string $mode = 'html' ) : string`
Sistem talimatınıza ekleyeceğiniz kurallar. `html`: tarayıcıda gösterilecekse (HTML/SVG + mermaid, CSS değişkenleri). `svg`: resme çevrilecekse (WhatsApp, e-posta): model yalnız tek `<svg>` yazar, renkleri doğrudan verir ve yanına metin açıklaması koyar.

### `flxai_extract_widgets( string $text ) : array`
`[ 'text' => blokları çıkarılmış metin, 'widgets' => [ [ 'type' => 'widget'|'mermaid', 'code' => '…' ] ] ]`.

### `flxai_render_widget( string $code, array $args = [] ) : array|WP_Error`
Widget'ı PNG'ye çevirir: `[ 'mime' => 'image/png', 'data' => ikili, 'via' => 'gotenberg'|'imagick'|… ]`. `$args`: `dark` (bool), `width` (px, varsayılan 720), `source` (rapor için).
Sıra: `flxai_render_widget` filtresi (kendi çeviriciniz; `null` dışında bir şey dönerse o kullanılır) → Gotenberg (Flux AI & MCP › Ayarlar › Görseller; HTML ve SVG) → Imagick (yalnız SVG; CSS değişkenleri renge çevrilir).
Hostinger paylaşımlı sunucuda tarayıcı yok: HTML widget'lar için Gotenberg (`docker run -p 3000:3000 gotenberg/gotenberg:8`, Evolution'ın Docker sunucusu kullanılabilir) gerekir. Çevirici yoksa `flxai_render_unavailable` döner; metinle devam edin.
Mermaid sunucuda çevrilmez (tarayıcı gerekir); WhatsApp için `svg` kurallarını kullanın.

WhatsApp örneği (Çağrı Merkezi):
```php
$sys .= "\n\n" . flxai_widget_rules( 'svg' );      // sistem talimatına
$r    = flxai_chat( $messages, $opts );
$x    = flxai_extract_widgets( $r['content'] );
foreach ( $x['widgets'] as $w ) {
	if ( 'widget' !== $w['type'] ) continue;
	$png = flxai_render_widget( $w['code'], array( 'source' => 'flx-chat' ) );
	if ( ! is_wp_error( $png ) ) {
		// $png['data'] → base64 → Evolution sendMedia (mediatype image, mimetype image/png)
	}
}
// $x['text'] metin mesajı olarak gönderilir (görsel gönderilemese de anlamlı olmalı)
```

## Filtreler ve olaylar
| Ad | Açıklama |
|---|---|
| `flxai_tools` | Ortak sohbete araç ekler (aşağıda). |
| `flxai_sources` | `slug => ad` kaynak listesi (raporlar ve "Eklentiye özel model"). |
| `flxai_model_for` | `( $model, $source )` seçilen modeli değiştirir. |
| `flxai_request_messages` | `( $messages, $opts )` istek öncesi mesajlar. |
| `flxai_request_done` / `flxai_request_failed` | `( $res, $opts )` istek sonrası. |
| `flxai_chat_system` | `( $text, $user_id )` sohbetin sabit sistem talimatı. |
| `flxai_chat_suggestions` | Sohbet açılış önerileri. |
| `flxai_can_chat` | `( $bool, $user_id )` sohbet erişimi. |
| `flxai_dock_enabled` | Sohbetin bu sayfada yüklenip yüklenmeyeceği. |
| `flxai_dock_push_selectors` | Sağa yerleşik modda kayması gereken sabit (fixed) öğeler. |
| `flxai_anthropic_price` | `( [in, out, cacheReadMult], $id )` fiyat (USD / 1M token). |
| `flxai_models` | Model kataloğu. |
| `flxai_widget_rules` | `( $text, $mode )` görsel kuralları. |
| `flxai_widget_palette` | `( $colors, $dark )` görsel renkleri (sunucu çevirici). |
| `flxai_render_widget` | `( null, $code, $args )` kendi PNG çeviriciniz. |
| `flxai_systems` | Bağlı sistemler listesi: `kaynak => [label, plugin (klasor/dosya.php), page (admin.php?page=…)]`. Yeni AI kullanan paket kendini buraya ekler. |
| `flxai_purpose_labels` | `amaç => etiket` (Kayıtlar ve raporlarda "nerede" sütunu). |
| `flxai_log_mask` | `( $text )` içerik kaydı maskesine ek kural (e-posta, 10+ hane, IBAN, anahtarlar zaten maskeli). |
| `flxai_agent_user_text` | `( $text, $msg )` modele giden kullanıcı metni (kayıtlı mesaj değişmez). Zamanlanmış görev notu buradan eklenir. |
| `flxai_tasks_max_per_user` | Kullanıcı başına en fazla görev (varsayılan 50). |
| `flxai_task_ran` | Olay `( $task_id, $state )`: görevin bir adımı bitti (`ok`, `approval`, `running`, `error`, `waiting`). |
| `flxai_task_history_max` | Görev başına saklanan çalışma geçmişi sayısı (varsayılan 50). |

## Sohbet aracı kaydı (`flxai_tools`)
```php
add_filter( 'flxai_tools', function ( $tools ) {
	$tools['flxg_list_tasks'] = array(
		'title'       => __( 'Görevleri listele', 'flx-tasks' ),
		'description' => 'Kullanıcının erişebildiği açık görevleri listeler.', // modele gider
		'schema'      => array( 'type' => 'object', 'properties' => array( 'q' => array( 'type' => 'string' ) ) ),
		'readonly'    => true,             // false = değişiklik yapar → her çağrıda kullanıcı onayı
		'cap'         => 'read',           // yetkisi olmayan kullanıcı aracı görmez
		'source'      => 'flx-tasks',
		'run'         => function ( $args, $ctx ) { // $ctx: user_id, chat_id, source, purpose
			return array( 'tasks' => array() );    // dizi ya da metin; hata için Exception fırlatın
		},
	);
	return $tools;
} );
```
- Araç adı `^[a-zA-Z0-9_-]{1,64}$`. Sonuç 30.000 karakterde kesilir.
- `readonly => false` ya da `confirm => true` olan araç onay kartında bekler; kullanıcı onaylamadan `run` çağrılmaz.
- MCP araçları (`flx_mcp_tools`, eski adı `flxseo_mcp_tools`) sohbete otomatik gelir; `readonly` bilgisi aynen kullanılır. Hem MCP'de hem sohbette görünecek araç için `flx_mcp_tools`, yalnız sohbet için `flxai_tools` kullanın.
- Yönetici araçları Flux AI & MCP › Araçlar'dan kapatabilir.

## Flux AI SDK (0.8.0)

Eklentiler (FluxWP ya da üçüncü taraf) AI özelliklerini **Flux AI SDK** ile ekler: sürümlü küçük bir kütüphane, her eklenti `includes/flx-ai-sdk/` kopyasını taşır, yalnız en yeni sürüm yüklenir. Kaynak ve belge: `tools/ai-sdk/` (README). Kısaca:

```php
add_action( 'flx_ai_register', function () {
	flx_ai_plugin( 'flx-x', array( 'label' => 'Flux X', 'prefix' => 'flxx' ) );
	flx_ai_tool( 'flx-x', 'summary', array( 'title' => 'Özet', 'readonly' => true, 'run' => fn( $a, $ctx ) => array() ) ); // flxx_summary
	flx_ai_feature( 'flx-x', 'auto_summary', 'Otomatik özet' );
} );
if ( flx_ai_enabled( 'flx-x', 'auto_summary' ) ) { $r = flx_ai_complete( 'flx-x', $prompt ); }
```
- SDK araçları MCP sunucusunda ve sohbette görünür (`mcp` / `chat` => false ile biri kapatılır). Ad eklenti önekini kendiliğinden alır.
- Ayar alanları **Bağlı sistemler › AI ayarları** ekranında (`admin.php?page=flx-ai-systems&system=<slug>`) çizilir; değerler `flx_ai_plugin_settings` seçeneğinde, eklenti `flx_ai_get()` ile okur. SDK ile kaydolan eklenti Bağlı sistemler ve kaynak listesine kendiliğinden eklenir.
- Flux AI yoksa hata olmaz: `flx_ai_chat()` `WP_Error( 'flx_ai_missing' )` döner.

## Araç adları, çakışma ve eski adlar (0.8.0)
- Araç adı eklenti önekiyle başlamalı (`flxseo_`, `flxcrm_`, `flxp_` …). Öneksiz adlar Araçlar ekranında "öneksiz" rozetiyle görünür.
- **Çakışma**: aynı adı iki eklenti kaydederse ilk kaydeden açık kalır, ikincisi üzerine yazamaz ve kapalı bekler. Araçlar ekranında iki satır ve "Bunu kullan" düğmesi çıkar (ayar `tool_pick`: ad => eklenti). Aynı eklentinin aynı adı yeniden tanımlaması ya da başka eklentinin aracı `source` alanını koruyarak genişletmesi (ör. Flux Lang'ın `flxseo_list_content`'e dil eklemesi) çakışma sayılmaz. Filtrelerle gelen araçlarda `source` boşsa geri çağrının bulunduğu eklenti klasörü yazılır.
- **Eski adlar**: araç tanımına `'aliases' => array( 'eski_ad' )`. MCP çağrıları, anahtar kapsamları ve kapalı araç listesi eski adla da çalışır; `tools/list` yalnız yeni adı gösterir. Yeniden adlandırılanlar: Flux SEO 1.6.0 `site_info` … `order_summary` → `flxseo_*`, Flux Setup 1.0.21 `preset_*` → `flxk_preset_*`, Flux TV Board 0.7.0 `tv_*` → `flxtp_tv_*`.
- Flux AI & MCP › Araçlar'da kapatılan araç artık **hem sohbette hem MCP'de** kapalıdır.
- Fonksiyonlar: `flxai_tool_resolve( $name )` (eski ad → yeni ad), `flxai_tool_is_off( $name )`, `flxai_mcp_registry()` / `flxai_chat_registry()` (`tools`, `dupes`, `aliases`).

## MCP sunucusu (0.7.0)

Ortak MCP sunucusu Flux AI'dadır (önceden Flux SEO içindeydi). Adres `/wp-json/flx-mcp/v1/mcp` (Streamable HTTP, durumsuz, JSON-RPC 2.0). Yönetim: **Flux AI & MCP › MCP** (`admin.php?page=flx-ai-mcp`): aç/kapa, kullanıcıya özel anahtarlar (isteğe bağlı araç kapsamı), son işlemler. Kimlik: `Authorization: Bearer <anahtar>`, `X-MCP-Key` başlığı ya da Uygulama Parolası. Anahtar sorgu dizesinden (`?key=`) okunmaz; kullanıcı yalnız WordPress bu rotayı (`/flx-mcp/v1/mcp`, kısa adres dahil) çalıştırınca kurulur. Bağlanmak `flxai_mcp` ister (aşağıda). Araçlar anahtarı oluşturan kullanıcının yetkileriyle çalışır; Flux AI sohbeti aynı araçları kullanır (değişiklik yapanlar onay ister).

### Kim bağlanır: `flxai_mcp` (0.10.3)

Rota izni, OAuth onay ekranı, belirteç ve yenileme `flxai_mcp` bakar. Ayarlar, bütün anahtarlar ve sunucuyu açıp kapatmak `manage_options` olarak kalır. OAuth onayı kullanıcının kendi bağlantısını oluşturur.

Varsayılan: `edit_posts` olan her rolde ve WordPress yöneticisinde açıktır (`user_has_cap`; şema 4 kurulumunda bu rollere bir kez de yazılır). Yönetici her zaman bağlanır. Flux Roles kataloğuna `flxr_catalog` grubu `ai` ile eklenir ve `flx_role_caps_supported` ile uygulanır; Roller ve erişim tablosundan `edit_posts` olmayan bir role de verilebilir. MCP ekranı Flux Roles kuruluysa o sayfaya bağlanır ve yetkisi kalkmış kullanıcının anahtarını uyarır.

### Araç kimde görünür (0.10.3)

`tools/list` ve `tools/call` aynı fonksiyonu kullanır (`flxai_mcp_tool_for_user` → `flxai_tool_allowed_for_user`). Sıra, hepsi geçmeden araç yoktur:

1. Yönetici Flux AI & MCP › Araçlar'dan kapatmamış (`flxai_tool_is_off`).
2. Anahtarın araç kapsamı izin veriyor (kapsam boşsa hepsi, `flxai_mcp_allowed`).
3. Kullanıcı aracın `cap` değerine sahip. Kayıtta `cap` yoksa ya da boşsa `edit_posts` gerekir (eski araçlar, editör olmayanlar bağlanabildiği için açıkta kalmasın).
4. `flx_mcp_tool_allowed` true döner.

`tools/list` elenen aracı yazmaz. `tools/call` onu çalıştırmaz ve bilinmeyen araçla aynı hatayı döner (`Unknown tool`, `isError`). Filtre true dönerse kapalı aracı, kapsam dışını ya da yetkisiz kullanıcıyı açmaz. Ortak sohbet aynı fonksiyonu kullanır (`flxai_tools_for_user`); anahtar kapsamı yalnız MCP isteğindedir.

```php
add_filter( 'flx_mcp_tool_allowed', function ( $allowed, $name, $tool, $user_id ) {
	if ( 'flxg_create_task' === $name && ! user_can( $user_id, 'flxg_use' ) ) {
		return false;
	}
	return $allowed;
}, 10, 4 );
```

Kaynak ve istem (`resources/list`+`resources/read`, `prompts/list`+`prompts/get`) aynı cap kuralını kullanır: `cap` yoksa `edit_posts`. Liste gizler; okuma/get çalıştırmaz ve bilinmeyen kaynak/istem döner. Bu ikisinde `flx_mcp_tool_allowed` uygulanmaz.

### Araç kaydı: `flx_mcp_tools`
```php
add_filter( 'flx_mcp_tools', function ( $tools ) {
	$tools['flxm_list_templates'] = array(
		'title'       => 'E-posta şablonları',
		'description' => 'Lists templates.',          // modele gider; İngilizce önerilir
		'readonly'    => true,                         // false: değiştirir → sohbette onay
		'cap'         => 'manage_options',             // yoksa edit_posts (MCP ve sohbet)
		'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
		'log'         => true,                         // false: başarılı çağrı Son işlemler'e yazılmaz (yoklama araçları)
		'source'      => 'flx-mail',                   // isteğe bağlı; yoksa aracı tanımlayan eklentinin klasörü
		'run'         => function ( $args ) { return array( 'templates' => array() ); }, // hata: throw new Exception()
	);
	return $tools;
} );
```
- Eski filtre **`flxseo_mcp_tools` aynen çalışır** (yeni filtreden sonra uygulanır); mevcut eklentilerin değişmesi gerekmez.
- Araç adı benzersiz ve eklenti önekli olsun (`flxm_…`, `flxcrm_…`). Sonuç dizi ise `structuredContent` olarak da döner.

### Flux Tasks (0.8.16)

Flux Tasks kendi MCP rotasını çalıştırmaz. Araçlar `flx_mcp_tools` ile `flxg_` önekiyle kaydolur: `flxg_list_projects`, `flxg_list_users`, `flxg_search_tasks`, `flxg_get_task`, `flxg_create_task`, `flxg_update_task`, `flxg_add_comment`, `flxg_list_fields`, `flxg_create_list`, `flxg_delete_task`, `flxg_list_notifications`, `flxg_list_docs`, `flxg_get_doc_page`, `flxg_edit_doc_page`, `flxg_create_doc`, `flxg_create_doc_page`. Eski sunucunun öneksiz adları `aliases` ile çalışmaya devam eder. `cap` ve aracın kendi kontrolü `flxg_use`; liste ve görev yetkisi aracın içinde kalır. Aynı kayıt ortak sohbete de gider; ayrı bir `flxai_tools` kaydı yoktur. Rotaya girmek `flxai_mcp` ister. `edit_posts` olan roller ve yöneticiler bunu varsayılan taşır; yalnız `flxg_use` olan kullanıcıya Flux Roles'tan `flxai_mcp` verilince bağlanır ve yalnız bu araçları görür.

### Diğer filtreler
| Filtre | Ne |
|---|---|
| `flx_mcp_prompts` | `ad => [ title, description, arguments[], text( $args ) → string ]` (prompts/list, prompts/get). |
| `flx_mcp_resources` | `uri => [ name, title, description, mimeType, read() → string, cap? ]` (resources/list, read). Eski `flxseo_mcp_resources` de uygulanır. |
| `flx_mcp_instructions` | Sunucu talimat satırları (dizi); `initialize` yanıtındaki `instructions`. |

### Fonksiyonlar
| Fonksiyon | Ne |
|---|---|
| `flxai_mcp_tools()` | Kayıtlı tüm araçlar (yetki süzmeden). |
| `flxai_mcp_call( $name, $args )` | Aracı çalıştırır; MCP sonucu `{ content[], isError, structuredContent? }`. |
| `flxai_mcp_enabled()` | Sunucu açık mı. |
| `flxai_mcp_url()` / `flxai_mcp_admin_url()` | Sunucu adresi / ayar ekranı. |

Geriye uyum: Flux SEO kaldırıldı ya da güncellendiyse `flxseo_mcp_tools()`, `flxseo_mcp_call()`, `flxseo_mcp_url()` Flux AI'daki karşılıklarına yönlenir (`function_exists( 'flxseo_mcp_tools' )` denetimleri çalışmaya devam eder). Eski anahtarlar (`flxseo_mcp_keys`) ve aç/kapa (`flxseo[mcp_enabled]`) ilk okumada `flxai_mcp_keys` / `flxai_settings[mcp_enabled]`'a geçer. Flux SEO 1.4.x hâlâ kuruluysa sunucuyu o çalıştırır; Flux AI yalnız uyarı gösterir. Seçenekler: `flxai_mcp_keys`, `flxai_mcp_log` (son 100).

## Akış (0.5.0)
`flxai_chat( $messages, array( 'stream' => function ( $ev ) { … } ) )`: `$ev['t']` = `text` | `think` (parça `$ev['d']`) ya da `tool` (`id`, `name`). Dönüş değeri akışsızla aynıdır. `stop_check` callable true dönerse istek kesilir: `WP_Error( 'flxai_stopped' )`, `get_error_data()['partial']` gelen metin. cURL yoksa ya da sunucu SSE dönmezse normal isteğe düşülür. Sohbetin `POST chats/{id}/step` ucu `stream: 1` ile NDJSON satırları döner: `{t:text|think|tool|model}`, kayıt değişince `{t:chat, chat}`, en sonda `{t:result, state, chat}`.

## Ayar aktarımı ve kaynak modeli (0.4.1)
Eklentinin kendi AI kurulumundan Flux AI'ye geçiş (bir kerelik, ör. güncellemede):

```php
if ( function_exists( 'flxai_import_config' ) ) {
	$done = flxai_import_config( 'flx-chat', array(
		'key_anthropic'       => $eski_claude_key,
		'key_openrouter'      => $eski_or_key,
		'anthropic_workspace' => $eski_workspace,
		'source_model'        => $eski_model, // bu eklentiye özel model
		// 'model'            => varsayılan model (Flux AI'de boşsa)
	) );
	// $done: aktarılan alanlar, ör. array( 'key_openrouter', 'source_models' ). Dolu alanlara dokunulmaz.
}
```
Anahtarlar Flux AI'de şifreli saklanır; aktarımdan sonra eklenti kendi kopyasını silmelidir. Model biçimi: OpenRouter kimliği (`google/gemini-2.5-flash`) ya da Claude (`claude-haiku-5-5` veya `claude:claude-haiku-5-5`).

Eklentinin kendi ayar ekranında model seçimi kalabilir: okuma `flxai_model_for( 'flx-chat' )`, yazma `flxai_set_source_model( 'flx-chat', $model )` (boş = Flux AI varsayılanı). Seçim listesi için `flxai_enqueue_model_picker()`.

## Görsel ve video üretimi (0.6.0)
Anahtarlar Flux AI & MCP › Ayarlar › Görsel ve video'da: fal.ai, EvoLink, OpenAI, Google Gemini (+ Sağlayıcılar'daki OpenRouter). Higgsfield yok. Harcama sınırı geçerli; her istek Kayıtlar'a `model` = `<sağlayıcı>:<model>` ile yazılır. Cron yok: kuyruktaki iş `flxai_media_status` çağrıldıkça ilerler (tarayıcı 3–5 sn'de bir sorar).

### `flxai_media_providers() : array`
Arayüz için, anahtar içermez: `providers{id: {label, ready}}`, `models{id: {label, kind image|video, routes[], providers[] (anahtarı olanlar), auto (otomatik seçilecek), aspects[], resolutions[], qualities[], duration [min,max,varsayılan]|null, max_refs, max_n, mask, price{çözünürlük: USD}, price_unit image|second}}`, `default_image`, `default_video`.

Modeller ve otomatik sıra (anahtarı girilmiş ilk sağlayıcı):

| model | tür | sıra |
|---|---|---|
| `gpt-image-2.5-sunburst` | görsel, maske | fal → openai → openrouter |
| `gemini-nano-banana-2.1` | görsel, maske | fal → gemini → openrouter |
| `gemini-3-pro-image-preview` (Nano Banana Pro) | görsel, maske | fal → gemini → openrouter |
| `seedance-2.5` (4–30 sn) | video | evolink → fal → openrouter |
| `seedance-2.0` (4–15 sn) | video | evolink → fal → openrouter |
| `minimax-h3` (2–15 sn) | video | fal |

Liste `flxai_media_models` filtresiyle genişletilebilir (yeni modelin gönderimi için uyarlayıcı da gerekir).

### `flxai_media_submit( array $req ) : array|WP_Error`
~45 sn içinde döner. Eşzamanlı sağlayıcıda (OpenAI, Gemini, OpenRouter görsel) iş `done` gelir; kuyruklularda (fal, EvoLink, OpenRouter video) `queued`.

| alan | açıklama |
|---|---|
| `source` | Zorunlu, eklenti kimliği (`flx-studio`). Bağlı sistemler'de kapalıysa `flxai_source_off`. |
| `purpose` | Kısa etiket (`generate`, `edit` …), Kayıtlar'da görünür. |
| `model` | Yukarıdaki kimlik; boşsa `kind`'a göre ayardaki varsayılan. |
| `kind` | `image` \| `video` (yalnız model boşsa). |
| `provider` | `auto` (varsayılan) ya da sağlayıcı. Sağlayıcı modeli sunmuyorsa `flxai_media_provider`, anahtar yoksa `flxai_key`. |
| `prompt` | Zorunlu, en çok 20.000 karakter. |
| `refs` | Referans görseller: ek (attachment) kimliği, https URL ya da `data:image/...` URL. |
| `video_refs`, `audio_refs` | Yalnız video; https URL ya da ek kimliği (EvoLink ve OpenRouter data URL almaz). |
| `mask` | Alan düzenleme; PNG, **saydam = düzenlenecek alan**, opak = korunur. `refs[0]` düzenlenen görsel. OpenAI maskeyi kendisi uygular; diğerlerinde sonuç indirildikten sonra maske dışı pikseller özgün görselden geri yazılır (Imagick, yoksa GD ≤16 MP; çıktıda `protected: true`). |
| `aspect` | Modelin `aspects` listesinden (görselde `auto` da olur). Varsayılan görsel `1:1`, video `16:9`. |
| `resolution` | Görsel `1K`/`2K`/`4K`, video `480p`/`720p`/`1080p` (MiniMax `768p`). |
| `size`, `quality` | Yalnız GPT Image: `"1536x1024"`, `auto`/`low`/`medium`/`high`. |
| `n` | Görsel adedi, 1–`max_n`. |
| `duration`, `audio`, `mode` | Video: saniye; ses (varsayılan açık); `auto`/`text`/`frames` (1–2 görsel: ilk/son kare)/`reference`. |
| `user_id` | Varsayılan geçerli kullanıcı (işin sahibi). |
| `ignore_limit` | Yalnız yönetici testleri. |

Kurallar:
- Sağlayıcı istekten **önce** seçilir. Ücretlendirilmiş olabilecek istek (zaman aşımı, 5xx, boş cevap) başka sağlayıcıya tekrar **gönderilmez**: `WP_Error`, `get_error_data()['maybe_charged'] = true`. Arayüz kullanıcıya "tekrar göndermeden önce sağlayıcı geçmişine bakın" demeli.
- Tahmini tutar istekten önce Kayıtlar'a yazılır (`est`), böylece aynı anda gelen istekler sınırı aşamaz. Sağlayıcı gerçek tutarı bildirirse (OpenAI kullanım, OpenRouter `usage.cost`, EvoLink) onunla değiştirilir; başarısız işte 0'a iner.
- Sınır doluysa `flxai_limit` (402).

### `flxai_media_status( string $job ) : array|WP_Error`
Dönüş (submit ile aynı biçim): `job` (`flxm_` + 24 hex), `state` `queued|running|done|failed|cancelled`, `progress` 0–1, `kind`, `model`, `provider`, `outputs[]`, `cost` (USD), `est` (tahmini mi), `error`, `source`, `user_id`, `created`, `updated`.
Çıktı: `{url, path, mime, bytes, width, height, remote, stored, protected?}`. Dosyalar `uploads/flxai-media/YYYY/MM/` altına indirilir (görsel ≤50 MB, video ≤256 MB). İndirilemezse `url` = sağlayıcı adresi, `stored: false` (geçicidir; çağıran eklenti medya kitaplığına almak isterse hemen almalı).
Sağlayıcıya en çok 3 sn'de bir sorulur; arada son durum döner. Geçici ağ hatasında iş bozulmaz, `error` dolu gelir ve sonraki sorguda tekrar bakılır.

### `flxai_media_cancel( string $job ) : array|WP_Error`
fal.ai kuyruğunda sağlayıcıdan da iptal istenir (onaylanırsa ücret 0). EvoLink/OpenRouter/eşzamanlılarda yalnız yerel kayıt kapanır, `error` bunu söyler.

### REST (tarayıcı polling)
Gönderim çağıran eklentinin kendi uç noktasından (kendi yetki kontrolüyle) yapılır. Polling için: `GET /wp-json/flx-ai/v1/media/{job}`, iptal `POST /wp-json/flx-ai/v1/media/{job}/cancel` (nonce `wp_rest`). Yalnız işin sahibi ya da yönetici görür (başkasına 404); `flxai_media_can_view` filtresiyle genişletilir.

### Olaylar
- `flxai_media_done( array $job )`: iş bittiğinde bir kez (eşzamanlı ya da polling sırasında).
- `flxai_media_failed( string $job, WP_Error $e, array $req )`: gönderim başarısız.

### Örnek
```php
$r = flxai_media_submit( array(
	'source' => 'flx-studio',
	'model'  => 'gemini-nano-banana-2.1',
	'prompt' => 'Ormanda fidan diken gönüllüler, sabah ışığı',
	'aspect' => '16:9',
	'refs'   => array( 123 ), // ek kimliği
) );
if ( is_wp_error( $r ) ) { /* $r->get_error_data()['maybe_charged'] */ }
// $r['state'] === 'done' ise $r['outputs'][0]['url']; değilse tarayıcı GET flx-ai/v1/media/{job} sorar.
```

## REST (`/wp-json/flx-ai/v1`)
Sohbet arayüzü içindir (nonce `wp_rest`): `GET boot`, `GET chats`, `GET chats/{id}`, `POST chats/{id}` (title/archived), `POST chats/{id}/delete`, `POST chats/{id}/send` (id 0 yeni sohbet), `POST chats/{id}/step`, `POST chats/{id}/decide`, `POST chats/{id}/stop`, `POST chats/{id}/rewind` (`msg` = kullanıcı mesajının `_id`'si, `mode` = `edit`: o mesaj ve sonrası silinir / `keep`: o mesaj kalır, sonrası silinir; çalışırken 409 `flxai_busy`). Bir tur adım adım ilerler (`send`, sonra `step` ~45 sn bütçeyle); adım sunucuda istemci kopsa da biter (`ignore_user_abort`), durum veritabanında. Sayfa yenilenince istemci `GET chats/{id}` ile `running` görür ve `step` çağırmaya devam eder. `stop` süren adımın ortasında da dinlenir. Yönetici: `GET models`, `POST test`, `POST render-test`, `GET log/{id}` (içerik kaydı), `GET report`, `GET billing`, `POST credits`. Yanıtlar `no-store`.

## Zamanlanmış görevler (0.11.0)
Tablo `{prefix}flxai_tasks`. Görev bir istemdir: zamanı gelince ortak sohbet motoru (`flxai_agent_send` + `flxai_agent_step`) onu **görev sahibinin** kullanıcısıyla çalıştırır, sonuç görevin sohbetine (`chat_id`) yazılır. Kullanıcı mesajında `_task => [id, title]` durur; modele "kullanıcı başında değil, rapor yaz, değişiklikler onaya düşer" notu eklenir. Değişiklik yapan araç onay kartında bekler (sohbet `approval`); sonraki zaman geldiğinde onay hâlâ bekliyorsa çalışma `waiting` olarak atlanır.

Zamanlama (`schedule`): `freq` `hourly|daily|weekdays|weekly|monthly|once`, `time` `HH:MM` (site saat dilimi), `days` `[1..7]` (ISO, weekly), `day` `1..31` (monthly, kısa ayda son gün), `every` `1..24` (hourly, `time` çapa), `at` `Y-m-d H:i` (once).

Fonksiyonlar: `flxai_task_save( $data, $id = 0, $user_id = 0 )` (title, prompt, schedule, status `active|paused`, model, bot) → görev | WP_Error; `flxai_task_get( $id, $user_id = 0 )`; `flxai_task_list( [user_id, status, limit] )`; `flxai_task_delete( $id )`; `flxai_task_queue_now( $id )` (duraklatılmışsa bir kez çalışıp yine durur); `flxai_task_run( $id, $force )`; `flxai_task_next_run( $schedule, $from )`; `flxai_task_schedule_label( $schedule )`; `flxai_task_public( $task )`.

Tetikleme: `flxai_tasks_tick` (WP-Cron, `flxai_5min`) + `shutdown`'da zamanı geçen görev varsa (`flxai_tasks_next`, autoload) `admin-ajax.php?action=flxai_tasks_run` loopback'i (gizli `flxai_tasks_token` ile, `blocking => false`). Bir istek bir sohbet adımı (~45 sn) çalıştırır; sürüyorsa `last_state = running` ve sonraki istek devam eder. Ayar `tasks_enabled` (varsayılan 1; ekrandan kapatılır).

Araçlar (`flx_mcp_tools` ile, sohbet + MCP): `flxai_task_list` (okur), `flxai_task_create`, `flxai_task_update`, `flxai_task_delete`, `flxai_task_run` (onay ister). Yetki `read` + AI sohbeti erişimi; kullanıcı yalnız kendi görevini, yönetici hepsini yönetir. Başka eklentiler görev açmak için `flxai_task_save()` çağırır; bot/ajan katmanı (Faz 3) `bot` sütununu kullanacak.

Çalışma geçmişi: `meta.history` (en yeni başta, `flxai_task_history_max` kadar): `ts`, `state`, `ms`, `cost`, `chat`, `from`/`to` (sohbet mesaj aralığı), `error`. Ekran `admin.php?page=flx-ai-tasks&history=ID` bu aralıktaki mesajları (araç girdi/sonuç, düşünme, yanıt) gösterir; onaydan sonra gelen devam mesajları da aynı çalışmaya dahildir.

## Sistem kontrolü
`flx_system_checks`: `flxai.keys`, `flxai.model`, `flxai.limit`, `flxai.errors`, `flxai.mcp`, `flxai.render`, `flxai.tasks`. `flx_system_fixes`: `flxai.limit_day`, `flxai.limit_week`, `flxai.limit_month` (USD).

## Uygulama

Ayarlar `flxai_settings` köprüsünden. `key_*` alanları write-only; boş gönderilirse eski şifreli değer kalır, yeni değer `flxai_encrypt` ile saklanır.

`GET /wp-json/flx-ai/v1/log` — `page`, `per_page`, `kind` (`model`|`tool`), `source`, `q`. Yetki `manage_options`. İstek ve cevap gövdesi dönmez. Sohbet ekranı (`flxai-chat`) uygulamada sonra açılır.
