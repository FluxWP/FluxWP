# Flux AI — Geliştirici API'si

**Sürüm 0.5.2.** Diğer FluxWP eklentileri yapay zekâyı kendi kodları yerine Flux AI üzerinden çağırır: anahtar, model, önbellek, harcama sınırı ve raporlar tek yerde. Flux AI yoksa eklenti eski koduna düşer.

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
Flux AI › Bağlı sistemler'de bu kaynak açık mı. Kapalıysa `flxai_chat` `flxai_source_off` hatası döner (kayda "Kapalı" düşer). `flxai_ready()` kapalı sistem için de `true` kalır; böylece eklenti kendi eski AI koduna geçmez, AI gerçekten kapalı olur. Arayüzde "AI kapalı" göstermek isterseniz bu fonksiyona bakın.

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
Sıra: `flxai_render_widget` filtresi (kendi çeviriciniz; `null` dışında bir şey dönerse o kullanılır) → Gotenberg (Flux AI › Ayarlar › Görseller; HTML ve SVG) → Imagick (yalnız SVG; CSS değişkenleri renge çevrilir).
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
- Flux SEO & MCP araçları (`flxseo_mcp_tools`) otomatik gelir; MCP'deki `readonly` bilgisi aynen kullanılır.
- Yönetici araçları Flux AI › Araçlar'dan kapatabilir.

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
Anahtarlar Flux AI › Ayarlar › Görsel ve video'da: fal.ai, EvoLink, OpenAI, Google Gemini (+ Sağlayıcılar'daki OpenRouter). Higgsfield yok. Harcama sınırı geçerli; her istek Kayıtlar'a `model` = `<sağlayıcı>:<model>` ile yazılır. Cron yok: kuyruktaki iş `flxai_media_status` çağrıldıkça ilerler (tarayıcı 3–5 sn'de bir sorar).

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

## Sistem kontrolü
`flx_system_checks`: `flxai.keys`, `flxai.model`, `flxai.limit`, `flxai.errors`, `flxai.mcp`, `flxai.render`. `flx_system_fixes`: `flxai.limit_day`, `flxai.limit_week`, `flxai.limit_month` (USD).
