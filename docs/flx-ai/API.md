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
| `flxai_skills` | Hazır skill'ler (bkz. Skill'ler). |
| `flxai_skills_prompt_max` | Sistem metnine eklenen en fazla skill sayısı (40). |
| `flxai_task_notified` | Olay: görev bildirimi gönderildi `( $task, $state, $text, $sent )`. |
| `flxai_agent_decided` | Olay: onay kararları uygulandı `( $chat )`. |

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

## MCP istemcisi (0.19.0)
Bağlantılar `flxai_mcpc_servers` seçeneğinde (id => kayıt: `name, url, auth (none|bearer|header|oauth), header, secret (şifreli), oauth{client_id, client_secret, authorize, token, register, resource, scope, access, refresh, expires}, scope (personal|shared), owner, roles[], enabled, perms{araç: allow|ask|off}, tools{önbellek}, caps, info, checked, error`). Ekran `admin.php?page=flx-ai-connections`.
- Transport: Streamable HTTP, protokol `2025-06-18`; yanıt JSON ya da SSE. `initialize` + `notifications/initialized`, `Mcp-Session-Id` 1 saat transient'te, 404'te oturum yenilenir. stdio ve eski HTTP+SSE yok.
- OAuth 2.1: 401 `WWW-Authenticate resource_metadata` → RFC 9728 korunan kaynak → RFC 8414 / OIDC metadata → RFC 7591 dinamik kayıt (`token_endpoint_auth_method: none`) → PKCE S256 + `resource` → dönüş `admin-post.php?action=flxai_mcpc_oauth` → belirteç; süre dolunca ya da 401'de `refresh_token` ile yenilenir.
- Araçlar `flxai_tools` (öncelik 30) ile: `mcp_<id>_<araç>` (64 karakter), `source = mcp:<id>`, `mcp_client = <id>`, `readonly` = izinli + sunucunun `readOnlyHint`'i. `flx_mcp_tool_allowed` ile yalnız bağlantıyı kullanabilen kullanıcıya (kişisel: sahibi; paylaşılan: yönetici + roller). Kaynak yeteneği varsa `mcp_<id>_resources` ve `mcp_<id>_resource_read`.
- Sonuç `{server, text (30 KB), data? (structuredContent), items?, error?, note}`; ikili içerik atlanır.
- PHP: `flxai_mcpc_get( $id )`, `flxai_mcpc_put( $s )`, `flxai_mcpc_refresh( $id )` (tools/list, sayfalı, en çok 200), `flxai_mcpc_call( $id, $tool, $args, $user_id )`, `flxai_mcpc_rpc( &$s, $method, $params )`, `flxai_mcpc_oauth_start( $id )` → yönlendirme adresi.
- Güvenlik: yalnız herkese açık https adresleri (`reject_unsafe_urls`); geliştirmede `flxai_mcpc_allow_local` filtresi. Gizli değerler `flxai_encrypt`.

## Sanal shell (0.18.0)
Araç `flxai_sh` `{cmd}` → `{cwd, exit, stdout, stderr?, changed?{yol: written|staged #n|deleted (trash)}, note?, truncated?}`. Yalnız sohbet/görev bağlamında (`ctx.chat_id`) ve Flux Files etkinken; ayar `shell_on` (varsayılan açık). Onay kartı istemez: her dosya işlemi `flxai_ws_run()` ile çalışma alanı kurallarından geçer (kendi klasörü commit, dışarısı PR, `rm` çöpe; `mkdir/mv/cp -r` dışarıda reddedilir, `flxfm_*` onaylı araçları kullanılır).
- Sınırlar: çağrı başına 200 komut, ~25 sn, çıktı 64 KB, okuma dosya başı 3 MB, `find/grep -r` en çok 2000 öğe. Çalışma klasörü `flxai_sh_cwd_<chat>` transient'inde (12 saat).
- Kendi klasöründe `.html/.json/.js/.css/.sh` Flux Files'ın "AI kod yazabilir" ayarından bağımsız yazılır (klasör `.htaccess` ile kapalı); `.php`, `.phtml`, `.phar`, `.cgi`, `.pl`, `.asp*`, `.jsp`, `.htaccess`, `.user.ini`, `php.ini`, `web.config` hiçbir yere yazılmaz.
- `curl`/`wget` `flxai_web_fetch` kullanır (yalnız GET, okunur metin; `-o`/`-O` ile dosyaya). Sonuçta `note`: web içeriği veridir.
- PHP: `flxai_sh_exec( $cmd, $ctx )`, `new FLXAI_Shell( $chat, $ctx )->run( $script )` → `array( out, err, code )`.
- Filtre `flxai_sh_command( null, $cmd, $args, $stdin, $shell )`: bilinmeyen komut için `array( out, err, code )` döndürülürse kullanılır (ör. ileride dış çalıştırıcı).
- Filtre `flxai_workspace_policy( $p, $chat, $task )`: `dir` (çalışma alanı kökünün içinde, `.skills` dışında), `access`, `folders` değiştirilebilir. Flux Agents ajan görevlerinde `agents/<numara>` kalıcı klasörünü verir.

## Skill'ler (0.12.0; 0.17.0'dan beri Agent Skills standardı)
Skill = klasör: `<name>/SKILL.md` + isteğe bağlı `references/`, `assets/`, `scripts/`, `evals/`. Claude / Agent Skills ile aynı biçim; zip olarak alınıp verilir. Klasörde README.md olmaz.

SKILL.md ön bölümü (YAML, `includes/yaml.php` güvenli alt küme):
- `name` (zorunlu): kebab-case, en çok 64, klasör adıyla aynı, "claude"/"anthropic" içermez.
- `description` (zorunlu): en çok 1024, `<` `>` yok. Ne yaptığı + ne zaman kullanılacağı (modele yalnız bu gösterilir).
- `license`, `compatibility` (≤500), `allowed-tools` (boşlukla ayrık araç adları, sonda `*` olabilir), `metadata` (eşleme: `title`, `version`, …).
- Eski anahtarlar (`title`, `tools`, `status`, `version`) okunmaya devam eder.

Açık/kapalı durumu dosyada değil, `flxai_skills_off` seçeneğinde (hazır skill kapatılınca kopyası çıkmaz). Eski `status: off` dosyaları kapalı sayılır.

Kaynaklar: `flxai_skills` filtresi + çalışma alanındaki `.skills/<slug>/` klasörleri (sitede oluşturulanlar ve hazır skill'lerin değiştirilmiş kopyaları).
```php
// Klasörle (önerilen): SKILL.md + references/ assets/ scripts/ evals/
add_filter( 'flxai_skills', function ( $skills ) {
	$skills['bagisci-incelemesi'] = array( 'dir' => FLXB_DIR . 'skills/bagisci-incelemesi', 'source' => 'Flux Donate' );
	return $skills;
} );
// Dizi olarak (eski biçim, çalışır)
$skills['bagisci-incelemesi'] = array( 'name' => __( '…' ), 'description' => __( '…' ), 'body' => "# …", 'tools' => array( 'flxb_donations_search' ), 'example' => '…', 'source' => 'Flux Donate' );
```

Üç seviyede yükleme: (1) etkin skill'lerin adı + açıklaması sistem metninde (`flxai_skills_prompt_max`, varsayılan 40); (2) model `flxai_skill_get` ile gövdeyi ve dosya listesini okur; (3) `flxai_skill_file` ile tek dosyayı okur (metin sayfalı, ikili dosyada yalnız bilgi). `scripts/` çalıştırılmaz, yalnız okunur.

Mesaj `/slug` ile başlarsa (baştaki en çok 5 `/a /b`) ya da görev skill'e bağlıysa talimatlar `flxai_agent_user_text` ile mesaja eklenir.

`allowed-tools` kısıtı: Skill'ler › Ayarlar'da "Skill araçlarıyla sınırla" (`flxai_skills_restrict`) açıksa, mesajda skill kullanıldığında modele yalnız skill'lerin izin verdiği araçlar (+ skill okuma araçları) sunulur (`flxai_chat_tools`). Kapalıyken alan yalnız ipucudur.

Fonksiyonlar: `flxai_skills_all( $active_only )`, `flxai_skill_get( $slug )`, `flxai_skill_validate( $s, $folder )`, `flxai_skill_save( $data, $old_slug )`, `flxai_skill_toggle( $slug, $on )`, `flxai_skill_delete( $slug )`, `flxai_skill_files( $s )`, `flxai_skill_file_read( $s, $path, $offset )`, `flxai_skill_file_write( $slug, $path, $content|null )` (hazır skill'de önce kopya), `flxai_skill_zip( $s )`, `flxai_skill_import_file( $file, $name )` (zip'te birden çok skill, `__MACOSX` atlanır, sınır 500 dosya / 30 MB / dosya başı 5 MB; .php/.phar/.exe vb. engelli), `flxai_skill_to_md()` / `flxai_skill_from_md()`, `flxai_yaml_parse()` / `flxai_yaml_dump()`.

Araçlar: `flxai_skill_list`, `flxai_skill_get`, `flxai_skill_file` (okur), `flxai_skill_save`, `flxai_skill_file_write`, `flxai_skill_test`, `flxai_skill_delete` (onaylı, `manage_options`). Hazır skill `skill-olusturucu` yeni skill yazarken standart ve kontrol listesiyle yönlendirir.

### Skill testleri (0.17.0)
`evals/evals.json` (skill-creator ile uyumlu):
```json
{ "skill_name": "ornek",
  "trigger_evals": [ { "query": "…", "should_trigger": true } ],
  "evals": [ { "id": 1, "prompt": "…", "expected_output": "…", "expectations": [ "…" ] } ] }
```
Testler ve sonuçlar (`evals/results.json`) çalışma alanında `.skills/<slug>/evals/` altında; hazır skill kopyalanmaz. Sınır 40 tetikleme, 10 senaryo.
- Tetikleme testi: her istem için model yalnız skill ad/açıklamalarını görüp seçer (en çok 20 istem, ~38 sn; kalan için `partial`). Sonuç `rate`, `hits`, `total`, `rows[{query, should_trigger, picked, triggered}]`, `cost`.
- Fonksiyonel test: istem skill'li ve skill'siz çalışır (araçsız), sonra yargıç model `expectations`'ı iki çıktıda işaretler. Sonuç `checks[{text, with, without}]`, `with`/`without` `{output, passed, total, in, out, cost, ms}`, `note`.
- REST `POST flx-ai/v1/skills/<slug>/test` `{kind: trigger|functional, id}` (`manage_options`). Kullanım kaydı `purpose = skill_test`.

## Görev bildirimleri ve onay kuyruğu (0.12.0)
Görev `meta.skill` (slug) ve `meta.notify` (`mode` none|important|always, `email` bool, `whatsapp` numara, `panel` bool) taşır; `flxai_task_save()` `skill` ve `notify` alanlarını alır, görev araçlarında `skill`, `notify`, `notify_channels`, `notify_whatsapp`.

"important": çalışmada model `flxai_task_notify` (yalnız sohbet aracı, `flxai_tools`) çağırdıysa, durum `approval` ya da `error` ise. Gönderim `flxai_task_ran` (öncelik 20): e-posta `flxi_send_email` şablonu `flx-ai/task_report` (değişkenler `gorev`, `ozet`, `ozet_html`, `durum`, `link`; yoksa `wp_mail`), WhatsApp `flxi_send_message( 'whatsapp', … , [ 'source' => 'flx-ai' ] )`, panel kullanıcı meta `flxai_task_unread` (task_id => zaman; rapor açılınca silinir, `flxai_tasks_unread()`). Kanallar geçmiş kaydına `notified` olarak yazılır. Olay: `flxai_task_notified( $task, $state, $text, $sent )`.

Onay kuyruğu: `flxai_approvals_count( $user_id )`, `flxai_approvals_list( $user_id )` (kullanıcının `approval` durumundaki sohbetlerinin bekleyen araç çağrıları), `flxai_approval_decide( $chat_id, $call_id, $approve, $args = null )` (tek işlem; `$args` verilirse girdi değişir; sohbette başka bekleyen kalmadıysa model bir adım devam eder). Olay `flxai_agent_decided( $chat )` (sohbetten ya da kuyruktan karar sonrası). Ekran `admin.php?page=flx-ai-approvals`.

### Ek alıcılar (0.14.0)
`meta.notify.to`: en çok 20 öğe `{ kind: contact|user|email|phone|group, id, name, email, phone, jid, channels: [email|sms|whatsapp] }` (`flxai_notify_to_clean()`). `contact` = Flux CRM kişisi (e-posta/telefon her gönderimde `FLXCRM_Contacts::get()` ile yeniden okunur), `user` = WP kullanıcısı (telefon `billing_phone`), `group` = WhatsApp grubu (`jid` `…@g.us`, yalnız whatsapp; Flux Mail'in grup JID'ini kabul etmesi gerekir). Gönderim: e-posta `flx-ai/task_report` şablonu, SMS/WhatsApp `flxi_send_message()`; yönetim bağlantısı yalnız `user` alıcılarına gider. Geçmişteki `notified` listesine `kanal:ad` yazılır.

- `flxai_notify_find( $q, $limit = 12 )` → kişi/kullanıcı/grup adayları (yetkiye göre: `flxcrm_view`, `list_users`, `flxc_use`). REST `GET flx-ai/v1/notify/recipients?q=` → `items`, `channels` (`flxai_notify_channels()`: Flux Mail bağlantılarına göre kullanılabilir kanallar).
- Araçlar: `flxai_notify_find_recipients` (q), görev araçlarında `notify_to` (tam liste, verilince eskisinin yerine geçer), `flxai_task_report` (id, limit → `runs`, `last_report`, `chat_url`).
- Görev raporu bağlantısı: `admin.php?page=flx-ai-tasks&open=<id>` (`flxai_task_open_url()`; okundu işaretler, `admin.php?page=flx-ai-chat&flxai_chat=<chat_id>`'ye gider). `flxai_task_last_output( $task )` son çalışmanın son asistan metni.

## Sohbet "/" menüsü (0.14.0)
`flxai_slash_commands()` boot yanıtında `commands` olarak gelir. Öğe: `id`, `group` (`actions` | `skills` | `apps` | özel), `title`, `desc`, `kind` (`action` | `insert` | `skill`), `insert` (kutuya yazılacak metin), `action` (`new`, `side`, `search`, `ws`, `attach`, `model`; bilinmeyen eylem `flxai:command` window olayıyla yayılır, `detail` = öğe), `icon` (chat.js simge adı), özel grupta `groupLabel`. Eklentiler `flxai_slash_commands` filtresiyle ekler:

```php
add_filter( 'flxai_slash_commands', function ( $c ) {
	$c[] = array( 'id' => 'myapp:report', 'group' => 'apps', 'kind' => 'insert', 'insert' => 'Aylık raporu hazırla: ', 'title' => 'Aylık rapor', 'desc' => 'My App verisinden rapor.' );
	return $c;
} );
```

Sohbet listesi (`GET chats`) her öğede `task` (görev adı ya da boş) döner.

## Sohbet araçları filtresi ve görünüm (0.15.2)
- Filtre `flxai_chat_tools( $tools, $chat )`: bir sohbet turunda modele sunulan araçlar. Flux AI bunu `flxai_task_notify`'ı görev dışı sohbetlerden çıkarmak için kullanır.
- Ayar `chat_mode`: `bubble` (varsayılan) | `dock` | `page`. Kullanıcının seçimi tarayıcıda (`flxai-ui`) saklanır. Model listesi önbelleği `localStorage['flxai-models']` (6 saat).

## Bildirim türü (0.15.1)
- Görev meta `notify.promo` (bool, varsayılan false; sohbet aracında `notify_promo`). true ise `notify.to` içindeki personel olmayan alıcılara (CRM kişisi, e-posta, telefon, WhatsApp grubu) `flxi_send_message` / `flxi_send_email` çağrısı `kind => 'marketing'` ile gider (Flux Mail 1.17.0+ IPTAL ve abonelik çıkışına uyar, çıkış satırı ekler). Diğer her durumda `kind => 'info'`. Eski Flux Mail `kind`'i yok sayar.

## Görsel rapor (0.15.0)
- Sohbet aracı `flxai_visual_report` `{title, html, file?}`: `html` `<main>` içine konacak Tailwind gövdesi ya da tam `<html>` belgesi. Gövde şablona sarılır (Tailwind CDN 3.4.16, `darkMode:'media'`; gövdede `new Chart` varsa Chart.js 4.4.1, `class="mermaid"` varsa Mermaid 11.4.1). Dosya `<sohbet klasörü>/raporlar/<slug>.html`; aynı `file` ile güncellenir. Dönüş `{path, title, saved}`. Flux Files (çalışma alanı) gerekir; yalnız sohbette çalışır (MCP'de yok).
- PHP: `flxai_report_save( $chat, $title, $html, $file = '' )` → `{path,title}` | `WP_Error`; `flxai_report_wrap( $title, $html )`.
- Sunum: `GET /flx-ai/v1/chats/<id>/ws/view?path=…&_wpnonce=…` (giriş + `flxai_can_chat`, yalnız sohbetin göz atabildiği `.html`). Başlıklar: `Content-Security-Policy: sandbox allow-scripts allow-popups …; connect-src 'none'; …` (opak köken: çerez, oturum, REST erişimi yok), `nosniff`, `no-referrer`, `no-store`, LiteSpeed no-cache. HTML hiçbir WP sayfasına basılmaz; sohbette `<iframe sandbox>` ile açılır.
- Filtre `flxai_report_cdns` (dizi): betik/stil izinli CDN'ler. Varsayılan cdn.tailwindcss.com, cdn.jsdelivr.net, unpkg.com, cdnjs.cloudflare.com (+ Google Fonts).
- Hazır skill `gorsel-rapor`. Tasarım ilkeleri [nicobailon/visual-explainer](https://github.com/nicobailon/visual-explainer) (MIT) skill'inden uyarlandı; kod kopyalanmadı.

## Sohbet ekleri ve sohbeti başlatma (0.12.0)

- REST `POST /flx-ai/v1/files` (multipart, alan `file`) → `{ key, name, type, size }`. `GET /flx-ai/v1/files/<key>` dosyayı yalnız sahibine verir (görsel/PDF inline, diğerleri indirme, `nosniff`).
- `POST /flx-ai/v1/chats/<id>/send` ek parametreler: `files` (key dizisi), `names` (görünen adlar). Metin boş olabilir, en az bir dosya varsa.
- PHP: `flxai_agent_send( $chat, $text, $page = array(), $files = array() )`; `$files` = `flxai_attach_for_message( $keys, $names, $user_id )` çıktısı. Mesajda `_files` olarak saklanır.
- Modele: görsel `image_url` (data URL), PDF `file`, metin dosyası içerik olarak (60.000 karaktere kadar). Yalnız son iki ekli kullanıcı mesajı tam gider.
- Filtreler: `flxai_attach_types` (uzantı => MIME), `flxai_attach_max_bytes` (varsayılan 10 MB), `flxai_attach_max_files` (varsayılan 5).
- Dosyalar `uploads/flxai-chat/<kullanıcı>/` altında (.htaccess ile kapalı); `flxai_chat_delete` ekleri de siler.
- JS: `window.flxaiChat.start( text, files )` paneli açar, yeni sohbet başlatır ve gönderir (`files` = `File` dizisi). `window.flxaiChat.dictate( textarea, button, onChange )` Web Speech API ile sesle yazma; `window.flxaiChat.canDictate` tarayıcı desteği.

## Çalışma alanı, commit ve değişiklik istekleri (0.13.0)
Kök `wp-content/flx-ai` (filtre `flxai_workspace_root`, site köküne göre; .htaccess + index.php ile kapalı). Sohbet `chats/<uid>`, görev `tasks/<uid>` (görev `meta.ws.dir`); `<uid>` = `flxai_ws_uid( 'chat'|'task', $id )`, sitedeki gizli anahtardan (`flxai_ws_key`) türetilen 13 haneli benzersiz numara (0.13.2; eski sıra numaralı klasörler taşınır), skill'ler `.skills/`. Flux Files (flx-files) gerekir; yoksa çalışma alanı kapalıdır, sohbet çalışır.

Erişim politikası (sohbet `state.ws`, görev `meta.ws`): `access` = `workspace` (varsayılan) | `site`, `folders` = izin verilen klasörler (en fazla 20). `flxai_ws_kind( $rel, $policy, $write )` → `own` | `pr` | `read` | `deny`:
- kendi klasörü → `own` (doğrudan yazar, turun commit'ine eklenir);
- çalışma alanının geri kalanı → `deny` (`.skills` yalnız okuma: `read`);
- `folders` içi ya da `access=site` → yazma `pr`, okuma `read`; diğer her yer `deny`.
Bunun üstüne Flux Files'ın AI kuralları her zaman geçerli: `flxfm_clean` (.. ve symlink), `flxfm_is_sensitive`, `flxfm_is_core`, kod dosyaları `ai_code` ayarına bağlı. Sınırlar: `flxai_workspace_max_write` (bayt, varsayılan 1 MB), `flxai_workspace_max_files` (tur/istek başına dosya, varsayılan 50). `flxai_workspace_default_access` yalnız `'site'` döndürürse etkilidir.

Uygulama: sohbet/görev içindeki `flxfm_*` araç çağrıları `flxai_pre_run_tool` filtresinde `flxai_ws_run()` üzerinden geçer (listeleme/arama sonuçlarından yasak yollar ayıklanır). `flxfm_write|replace|delete` kendi klasörü dışında dosyayı değiştirmez, açık değişiklik isteğine eklenir (aynı dosya üst üste yazılınca birleşir; okuma araçları bekleyen içeriği döner). Bu yazmalar `flxai_call_needs_approval` ile onay kartı istemez; kart yerine değişiklik isteği onaylanır. Dışarıdan MCP istemcisi bu kuralların dışındadır (Flux Files'ın kendi kuralları).

Tablo `{prefix}flxai_changes` (DB 7): `kind` commit|pr, `status` done|open|merged|rejected|partial, `files` JSON (path, op add|edit|delete, before/after, base md5, add/del, state). Fonksiyonlar: `flxai_changes_list( $args )`, `flxai_change_get( $id )`, `flxai_change_open_pr( $chat_id )`, `flxai_changes_open_count( $user_id )`, `flxai_change_decide( $pr, $merge, $paths = null )` (base md5 farklıysa dosya uygulanmaz; uygulama `flxfm_tool_call` ile, Flux Files sürüm/çöp kaydı tutulur).

Olaylar: `flxai_ws_committed( $commit, $chat )`, `flxai_ws_staged( $pr, $chat )`, `flxai_change_decided( $pr, $merge, $applied )`. Filtreler: `flxai_pre_run_tool( null, $name, $args, $ctx )` (null dışı dönerse araç çalışmaz, dönen sonuç kullanılır), `flxai_call_needs_approval( $bool, $name, $args, $chat )`, `flxai_chat_session( $text, $chat, $user_id )` (sistem metnine dinamik ek).

REST (`/flx-ai/v1`, sohbet sahibi + Flux Files yetkisi): `GET|POST chats/<id>/ws` (durum: klasör, politika, son değişiklikler; POST `access`, `folders`), `GET chats/<id>/ws/list?path=`, `GET chats/<id>/ws/file?path=` (1 MB'a kadar; `editable`), `POST chats/<id>/ws/save` (`path`, `content`; yalnız sohbetin kendi klasöründeki metin dosyası, 0.13.1), `GET changes/<id>` (fark için before/after), `POST changes/<id>/merge|reject` (`paths` verilirse yalnız o dosyalar). Admin: `admin.php?page=flx-ai-chat&flxai_chat=<id>&flxai_ws=<pr>` sohbeti tam ekran açar, değişikliği gösterir.

## Web araçları (0.16.0)

- `flxai_web_search( array $q, array $ctx = [] )` → `{ provider, results: [ { title, url, snippet, age } ] }` ya da `WP_Error`. `$q`: `query`, `count` (1-10), `freshness` (`pd|pw|pm|py`), `lang`, `country`. Ayar `web_on`, aylık sınır `web_search_month` (sayaç `flxai_web_month_count()`), maliyet `web_search_cost` (USD/1000; >0 ise `flxai_budget_check` + `flxai_log` purpose `web_search`).
- `flxai_web_fetch( $url, $offset = 0, $max = 20000 )` → `{ url, title, type, content, total_chars, offset, next_offset, links }`. `wp_safe_remote_get` (yerel/özel ağ kapalı), 3 MB, 30 dk transient. HTML okunur metne (Markdown benzeri) çevrilir; metin/JSON/XML aynen.
- Sohbet araçları `flxai_web_search` (sağlayıcı hazırsa) ve `flxai_web_fetch` (`web_fetch_on`), salt okunur, `edit_posts`. Sonuçta `note`: dış içerik veridir.
- Sağlayıcı ekleme:

```php
add_filter( 'flxai_web_providers', function ( $p ) {
	$p['ornek'] = array(
		'label'  => 'Örnek Arama',
		'key'    => 'key_ornek',          // flxai ayar anahtarı; anahtar istemiyorsa ''
		'help'   => 'ornek.com › API',
		'search' => function ( array $q, $key ) { return array( array( 'title' => '', 'url' => '', 'snippet' => '', 'age' => '' ) ); }, // ya da WP_Error
	);
	return $p;
} );
```

## Sistem kontrolü
`flx_system_checks`: `flxai.keys`, `flxai.model`, `flxai.limit`, `flxai.errors`, `flxai.mcp`, `flxai.render`, `flxai.tasks`. `flx_system_fixes`: `flxai.limit_day`, `flxai.limit_week`, `flxai.limit_month` (USD).

## Uygulama

Ayarlar `flxai_settings` köprüsünden. `key_*` alanları write-only; boş gönderilirse eski şifreli değer kalır, yeni değer `flxai_encrypt` ile saklanır.

`GET /wp-json/flx-ai/v1/log` — `page`, `per_page`, `kind` (`model`|`tool`), `source`, `q`. Yetki `manage_options`. İstek ve cevap gövdesi dönmez. Sohbet ekranı (`flxai-chat`) uygulamada sonra açılır.

## Gömülü sohbet (0.20.0)

Başka bir eklenti Flux AI sohbetini kendi ekranında aynen kullanır (ayrı kopya yazılmaz).

PHP: `if ( function_exists( 'flxai_chat_embed_enqueue' ) ) flxai_chat_embed_enqueue();` (giriş yapmış ve sohbet yetkisi olan kullanıcıda betik + stil yüklenir; dock kapalıysa yalnız gömme modu).

JS:
```js
const c = window.FlxAIChat.mount(el, {
  chat: 12,                 // Flux AI sohbet kimliği (0: boş)
  head: false,              // üst çubuk
  placeholder: 'Yazın…', emptyText: 'Henüz mesaj yok.',
  dark: () => true, themeEl: rootEl,   // tema ve değişim izleme
  busy: () => false,        // dışarıda çalışan iş var mı (izleme sıklığı)
  onSend: m => fetch(...)   // m = { text, files:[{key,name}] }; verilirse gönderimi eklenti yürütür, bileşen sohbeti izler
});
c.load(id); c.refresh(); c.toggleWs(); c.focus(); c.destroy();
```
`onSend` modunda durdur ve geri sarma kapalıdır; onay kartları Flux AI'ın `/decide` ucunu kullanır (`flxai_agent_decided` eylemi tetiklenir).

Mesaj alanı `_show`: modele giden metinden farklı görünen metin (kullanıcı ya da asistan mesajı).

## Araç sonucu filtresi ve yeni araçlar (0.20.0)

- `flxai_tool_result( $data, $name, $args, $ctx )`: başarılı araç sonucunu değiştirir. Flux AI kendisi `flxseo_*_content` sonuçlarına `preview_url` + `edit_url` ekler.
- `flxai_image_generate` (prompt, aspect, title, alt): varsayılan görsel modeliyle üretir, medya kütüphanesine ekler, `images[].attachment_id/url` döner. Ayar `image_tool_on`.
- `flxai_page_screenshot` (post_id | url, width): Gotenberg (`render_url`) ile PNG. Taslaklar `flxai_preview_link( $post_id, $ttl )` imzalı süreli bağlantısıyla çekilir.
