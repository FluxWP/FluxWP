# Flux Mail – geliştirici API'si

Diğer eklentiler e-posta, WhatsApp ve SMS'i bu eklenti üzerinden gönderir. Her çağrıdan önce
`function_exists()` ile kontrol edin; eklenti kapalıysa kendi yönteminize düşün.

## E-posta şablonu kaydetmek

```php
add_filter( 'flxi_email_types', function ( $types ) {
	$types['flx-donate/receipt'] = array(
		'label'       => __( 'Bağış makbuzu', 'flx-donate' ),
		'group'       => 'Flux Donate',             // listede grup başlığı
		'description' => __( 'Ödeme alınınca bağışçıya gider.', 'flx-donate' ),
		'recipient'   => 'user',                 // user | admin
		'vars'        => array( 'ad' => 'Ad', 'tutar' => 'Tutar', 'link' => 'Makbuz bağlantısı' ),
		'sample'      => array( 'ad' => 'Ayşe', 'tutar' => '1.000 ₺', 'link' => home_url( '/' ) ),
		'defaults'    => array(
			'subject'     => 'Bağışınız için teşekkürler',
			'heading'     => 'Teşekkürler {ad} 🌱',
			'body'        => '<p>{tutar} tutarındaki bağışınız ulaştı.</p>',
			'button_text' => 'Makbuzu görüntüle',
			'button_url'  => '{link}',
			'sender'      => 'info',             // info (bilgi@) | noreply (no-reply@)
			'enabled'     => 1,
		),
		'can_disable' => true,
	);
	return $types;
} );
```

Yönetici şablonu **İletişim › E-postalar**'da görür, metnini değiştirir, açıp kapatır.
Değişkenler `{ad}` biçiminde; `_html` ile biten değişkenler (`{mesaj_html}`) kaçışlanmadan eklenir.
Her şablonda ayrıca `{site}`, `{kurum}`, `{kurum_eposta}`, `{kurum_telefon}`, `{yil}`, `{tarih}` vardır.
Boş kalan değişken boşluk bırakmaz: `Merhaba {ad},` → `Merhaba,`.

## E-posta göndermek

```php
if ( function_exists( 'flxi_send_email' ) ) {
	$ok = flxi_send_email( array(
		'to'          => 'ayse@ornek.com',
		'template'    => 'flx-donate/receipt',   // kayıtlı şablon; kapalıysa gönderilmez (true döner, kayda "Kapalı" düşer)
		'vars'        => array( 'ad' => 'Ayşe', 'tutar' => '1.000 ₺', 'link' => $url ),
		'reply_to'    => '',                   // isteğe bağlı
		'attachments' => array(),              // dosya yolları
		// Şablonun üstüne yazılabilecek alanlar: subject, heading, body, button_text, button_url,
		// sender, image, image_link, image_caption, facts (etiket => değer), note, preheader
	) );
}
```

Şablon kaydetmeden tek seferlik içerik: `template` boş bırakıp `subject`, `heading`, `body`,
`button_text`, `button_url` verin. Aynı kurumsal tasarımla gider.

`flxi_is_email_enabled( $key )` – şablon açık mı (gönderimi kendiniz atlamak isterseniz).

Kendi `wp_mail()` çağrılarınız da çalışır: gönderen adresi düzeltilir, düz metin ise kurumsal şablona
sarılır ve e-posta **İletişim › E-postalar** listesine kendiliğinden eklenir (çağıran fonksiyona göre).

## WhatsApp / SMS

```php
$conn = flxi_get_channel_connection( 'whatsapp' ); // varsayılan bağlantı ya da null (gizli alanlar dahil config)
$r = flxi_send_message( 'whatsapp', '905321234567', 'Merhaba Ayşe 🌱', array(
	'connection_id' => 0,          // 0 = varsayılan
	'media'         => $pdf_url,    // isteğe bağlı: belge/görsel URL'si (WhatsApp)
	'file_name'     => 'sertifika.pdf',
	'source'        => 'flx-donate',  // kayıtta görünür
) );
// $r = array( 'ok' => bool, 'error' => string, 'retry' => bool, 'external_id' => string, 'connection_id' => int )
```

`flxi_connection_choices( 'sms' )` – ayar ekranınızda seçim kutusu için bağlantı listesi (gizli alan yok).
Kanallar: `email`, `whatsapp`, `sms`. Tipler: `evolution`, `flxwpapi`, `wacloud` (WhatsApp), `netgsm` (SMS);
yenisi `flxi_connection_types` filtresiyle eklenir (`send` / `test` callback'leri; Evolution v2 uçlarını konuşan tipte `'evo' => true`).

### WhatsApp grupları (1.16.0+)

Alıcı bir grup kimliği de olabilir (`120363…@g.us`; eski biçim `905321234567-1600000000@g.us`). Kimlik normalize edilmeden Evolution / Flux WPAPI'ye `number` olarak gider; metin ve `media` aynı.

```php
$gruplar = flxi_whatsapp_groups( $conn_id );   // 0 = varsayılan; WP_Error ya da [ [ 'id', 'name', 'size', 'announce' ] ], 10 dk önbellek
$r = flxi_send_message( 'whatsapp', $gruplar[0]['id'], 'Günlük brifing…', array( 'connection_id' => $conn_id, 'source' => 'flx-ai' ) );
```

- `flxi_is_wa_group( $to )` grup kimliği mi; `flxi_wa_number( $to )` Evolution alıcısı (grup olduğu gibi, telefon rakam).
- Cloud API (`wacloud`) grup göndermez: `ok => false, code => 'group_unsupported'`, açık hata metni. `connection_id` verilmez ve varsayılan Cloud API ise ilk etkin Evolution / Flux WPAPI bağlantısı seçilir.
- Kara liste gruplara uygulanmaz (telefon/e-posta içindir). SMS kanalında grup kimliği geçersiz telefon hatası verir.
- REST: `GET flxi/v1/wa/{id}/groups` (`fresh=1` önbelleği atlar), yönetici yetkisi.

### WhatsApp hesapları (1.9.0+)

WhatsApp hesapları (numaralar) yalnız İletişim › Bağlantılar'da eklenir ve yönetilir; diğer eklentiler (Flux Chat, Flux Certificates) buradan seçer.

- Her bağlantı **bir instance = bir numara**. Aynı sunucuya birden çok numara eklenebilir (yeni bağlantı formunda "Aynı sunucu").
- Sağlayıcı = bağlantı tipi: `evolution` (Evolution API) ya da `flxwpapi` (Flux WPAPI köprüsü). İkisi de Evolution v2 uçlarını konuşur; config anahtarları aynı: `base_url`, `api_key`, `instance` (+ `link_preview`, `delay`, `typing`; `version` yalnız evolution'da).
- QR / telefon numarasıyla kod, durum, yeniden başlatma, oturumu kapatma, sunucudan silme WP'den yapılır. Flux WPAPI'de sunucu ayarları (dış adres, uyanık tutma, MySQL) da "⋯ › Sunucu ayarları"ndadır.
- **Webhook'a İletişim dokunmaz.** Gelen mesajı isteyen eklenti (Çağrı Merkezi) kendi webhook'unu `/webhook/set/{instance}` ile kurar.

```php
flxi_is_evo_type( $type );                    // evolution | flxwpapi → true
flxi_whatsapp_state( $id, $fresh = false );   // ['state' => open|connecting|close|missing|error, 'me' => '+90…', 'name' => profil adı, 'at' => unix]; 60 sn önbellek
flxi_connection_choices( 'whatsapp' );        // satırlarda ek olarak 'state', 'me' (önbellekten)
flxi_get_channel_connection( 'whatsapp', $id ); // ['id','type','name','channel','config' => base_url, api_key, instance…]
```

Eylem: `flxi_connection_saved( $id )`, `flxi_connection_deleted( $id )`.

### WhatsApp Cloud API (`wacloud`, 1.10.0+)

Config: `phone_id`, `waba_id`, `token`, `app_secret` (zorunlu), `graph` (ör. `v23.0`), `link_preview`, `delay`. QR / instance yok; `flxi_whatsapp_state()` numara bilgisini okur (`me` = görünen numara, `name` = doğrulanmış ad).

- **Gelen:** Meta webhook'u `flxi/v1/wacloud/{id}/webhook` (GET doğrulama `hub.verify_token`, POST `X-Hub-Signature-256` zorunlu App Secret ile; `app_secret` boşsa POST `403` ile reddedilir, `no_secret` kaydedilir ve hiçbir olay yayılmaz). Her doğrulanmış olay Evolution v2 webhook gövdesine çevrilir ve yayılır:
  `do_action( 'flxi_whatsapp_event', $conn_id, $event )` — `event` = `messages.upsert` (`data.key.remoteJid`, `pushName`, `message.conversation | imageMessage… + message.base64`, `contextInfo.stanzaId`) ya da `messages.update` (`keyId`, `status` SERVER_ACK|DELIVERY_ACK|READ|ERROR). `instance` = `wacloud-{id}`. Ham gövde: `flxi_wacloud_event( $id, $payload )`.
- **Gönderim:** `flxi_send_message( 'whatsapp', $tel, $metin, [ 'connection_id' => $id, 'wa_template' => [ 'name' => 'bagis_tesekkur', 'lang' => 'tr', 'params' => [ 'Ayşe', '3' ] ] ] )`. Ham Graph gövdesi (alıntı, tepki, okundu, medya id): `flxi_wacloud_post( $id, $tel, [ 'type' => 'reaction', 'reaction' => [ 'message_id' => …, 'emoji' => '👍' ] ] )` → `ok, external_id, error, code`.
- **24 saat:** `flxi_wacloud_window( $id, $tel )` → `open, last_in, closes`. Kapalıyken serbest metin `#131047` hatası döner; yalnız şablon gider.
- **Şablonlar:** `flxi_wacloud_templates( $id )` → `name, lang, status, category, body, params` (WABA ID gerekir).
- Yok: grup mesajı (`group_unsupported`), mesaj düzenleme, findChats/findMessages.

### Resim, ses, belge gönderimi (1.12.0+)

`flxi_wa_send_media( $conn_id, $tel, 'image'|'audio'|'document', [ 'path' => yerel dosya | 'url' => adres, 'mime', 'file_name', 'caption' ] )` → `ok, error, external_id`. Üç sağlayıcıda da çalışır: Evolution / Flux WPAPI'de yerel dosya base64 gider (`/message/sendMedia`, ses `/message/sendWhatsAppAudio` = sesli mesaj); Cloud API'de dosya önce Meta'ya yüklenir (`flxi_wacloud_upload`, kimlik 20 gün önbellek), `url` verilirse link gider. Ses için OGG/Opus önerilir.

Test senaryoları: `flxi_test_scenario( $id, 'check'|'text'|'image'|'audio'|'document'|'template', $tel, [ 'text', 'caption', 'template' => [ 'name', 'lang', 'params' ] ] )`; desteklenenler `flxi_test_scenarios( $tip )`. Örnek dosyalar `assets/test/` (`flxi_test_samples()`).

## Filtreler ve eylemler

| Ad | Ne zaman |
|---|---|
| `flxi_email_types` | Şablon kaydı |
| `flxi_template_args` ($args, $key, $vars, $is_sample) | Şablon çizilmeden önce |
| `flxi_render_email_args`, `flxi_email_html` | Kurumsal düzen |
| `flxi_brand` | Logo, renkler, kurum bilgileri |
| `flxi_wrap_email` ($wrap, $key, $atts) | Başka eklentinin e-postası sarılsın mı |
| `flxi_mail_context` | Hangi şablon/kaynak olduğuna karar |
| `flxi_user_is_social` ($is_social, $user_id) | Kullanıcının Google ile giriş yaptığına dair güvenilir karar (doğrulama ve parola e-postası bastırma yalnız sağlayıcı kaydı veya bu filtre `true` döndüğünde çalışır) |
| `flxi_is_google_request` | İstek parametreleri / Referer'a dayalı sezgisel kontrol (doğrulama veya e-posta bastırma hakkı vermez) |
| `flxi_verified_meta` | `flxi_mark_email_verified( $user_id )` tarafından yazılacak doğrulama meta anahtarları |
| `flxi_connection_types` | Yeni WhatsApp/SMS sağlayıcısı |
| `flxi_recipient_sources`, `flxi_recipients_from_source` | Toplu gönderimde yeni alıcı kaynağı |
| `flxi_certificate_background` | Sertifika önizlemesi için temiz arka plan görseli |
| `flxi_mail_logged`, `flxi_message_sent`, `flxi_template_saved`, `flxi_email_verified` | Eylemler |
| `flxi_mailbox_ai_context` ($text, $mail) | AI cevabına eklenecek kurum bilgisi |
| `flxi_render_pdf` (null, $html) | Kendi PDF çeviriciniz (PDF ikili verisi ya da WP_Error döndürün); yoksa Flux AI'daki Gotenberg |
| `flxi_use_mailparse` (bool) | PHP mailparse uzantısı kullanılsın mı (varsa varsayılan evet) |
| `flxi_imap_verify_peer` | IMAP SSL sertifikası doğrulansın mı (varsayılan evet) |

## Sistem kontrolü ve MCP

- `flx_system_checks`: `flxi.*` satırları (gönderen, yöntem, test, hatalar, kuyruk, SPF/DMARC, Google, bağlantılar, Flux Certificates).
- `flx_system_fixes`: `flxi.from_email`, `flxi.from_name`, `flxi.noreply_email`, `flxi.reply_to`,
  `flxi.smtp_server` (`host:port:ssl|tls|yok:kullanıcı`), `flxi.mailer` (`php|smtp`), `flxi.social_block` (`on|off`),
  `flxi.send_test`, `flxi.dns_check`. Şifreler hiçbir yoldan okunmaz/yazılmaz.
- `flxseo_mcp_tools`: `flxi_email_log`, `flxi_templates`, `flxi_update_template`, `flxi_settings`, `flxi_send_test`, `flxi_mail_quota` (sınırlar ve son 24 saat kullanım), `flxi_campaigns`.

## Kara liste

Her gönderimden önce `apply_filters( 'flx_contact_blocked', false, $phone, $email, $channel )` sorulur (liste Flux Chat'nde).
Engellenen e-posta/mesaj gönderilmez, kayda `blocked` düşer; `flxi_send_message()` `blocked => true` döner.
Hariç şablonlar: `flxi_blocklist_exempt` filtresi (varsayılan `core/password_reset`, `core/password_changed`, `core/email_changed`).
Kişinin kendisiyle yazışma gibi istisnalarda `flxi_send_message( …, array( 'ignore_blocklist' => true ) )`.

## Posta kutusu sınırları

- `flxi_limits()`: ayarlardaki paket sınırları (`daily`, `recipients`, `size_mb`, `attach_mb`, `storage_gb`, `emails`, `aliases`, `forwarders`, `reserve`).
- `flxi_quota_summary()`: her posta kutusu için `mailbox`, `used` (son 24 saat alıcı), `limit`, `left`, `percent`, `next` (sonraki hak açılışı, unix).
- `flxi_quota_left( flxi_quota_mailbox( 'info'|'noreply' ) )`: kalan hak. Kendi toplu gönderiminiz varsa bunu kontrol edin; tekil e-postalar engellenmez.

## Posta kutusu

- Tablolar: `{prefix}flxi_mail` (gelen/giden/site kopyaları), `{prefix}flxi_drafts` (taslaklar; durum `draft|review|sent|failed`, kaynak `user|ai`).
- `flxi_mailbox_query( array( 'folder' => 'inbox|sent|all', 'search', 'unread', 'starred', 'system', 'limit', 'offset' ) )`, `flxi_mailbox_get( $id )`.
- `flxi_mailbox_new_draft( array( 'subject', 'body' (HTML), 'to', 'cc', 'layout', 'note' ) )` → `array( 'id', 'url' )` ya da WP_Error: başka eklentiden (ör. Flux Tasks belgesi) taslak açar, gönderilmez. JS'ten: `POST /wp-json/flxi/v1/mailbox/new-draft` (aynı alanlar, `X-WP-Nonce: wp_rest`) → `{ id, url }`; `url` taslağı posta kutusunda açar. Varlık kontrolü: `function_exists( 'flxi_mailbox_new_draft' )`.
- `flxi_draft_save( $data )`, `flxi_draft_send( $id )` (wp_mail ile; In-Reply-To/References, Gönderilmiş'e ekleme kuyruğu).
- `flxi_mailbox_ai_reply_draft( $mail_id, $instruction )`: Flux AI ile "Onay bekleyen" taslak.
- `flxi_layouts()`, `flxi_layout_save()`: Kurumsal/Sade ve alt şablonlar (`partner_name`, `partner_logo`, `partner_url`, `accent`, `heading`, `subject`, `body`).
- `flxi_render_email()` yeni argümanlar: `partner`, `accent`, `quote`, `signature`, `style` (`card|letter`).
- Flux AI araçları (`flxai_tools`): `flxi_mail_list`, `flxi_mail_read`, `flxi_mail_layouts` (okuma), `flxi_mail_draft`, `flxi_mail_send_draft` (onaylı).
- Cron: `flxi_mailbox_sync` (5 dk). Kilit: `flxi_mailbox_lock` transient.

## REST (yalnız `flxi_manage` yetkisi)

`flxi/v1/preview`, `test-email`, `queue/run`, `recipients`, `connection-test` (`{id, to}` ya da senaryo: `{id, to, scenario, text?, caption?, template?}`), `log/{id}`, `dns`, `preview-pdf` (önizleme alanları + `dark`; `{name, data}` base64 PDF).
WhatsApp: `flxi/v1/wa/{id}/state` (GET, `?fresh=1`), `wa/{id}/connect` (POST `{number?}` → `{state, qr (data URI), pairing}`; instance sunucuda yoksa oluşturur), `wa/{id}/restart`, `wa/{id}/logout`, `wa/{id}/instances` (GET, sunucudaki instance'lar), `wa/{id}/server` (GET/POST, yalnız flxwpapi; `{publicUrl, keepAlive, db, test}`), `wacloud/{id}/setup`, `wacloud/{id}/templates`. API key ve parolalar tarayıcıya gelmez.
Posta kutusu: `flxi/v1/mailbox/` altında `list`, `item/{id}`, `item/{id}/mark`, `item/{id}/attachment`, `sync`, `test`, `drafts`, `draft`, `draft/{id}`, `draft/{id}/send`, `draft/{id}/delete`, `new-draft`, `reply`, `preview`, `ai`, `layouts`, `layout`, `layout/{id}/delete`.


## Portable background execution

See the [shared worker contract](../../docs/background-workers/API.md) for automatic Docker worker detection, heartbeat expiry and ordinary WordPress fallback. Campaign queues prefer the worker; single-email APIs remain synchronous.
