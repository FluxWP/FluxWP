# Flux Donate 2.0.1 · API (api 2.0) (tema ve eklenti ekipleri için)

Var mı kontrolü: `function_exists( 'flxb_api_version' )`. 1.x'teki okuma fonksiyonları ve dönen yapılar 2.0'da aynen duruyor (yalnızca büyüdü). 2.0'ın WooCommerce katmanı aşağıda, "WooCommerce (2.0)" bölümünde.

## PHP

| Fonksiyon | Dönüş |
|---|---|
| `flxb_api_version()` | `'2.0'` |
| `flxb_get_types( array $args )` | `array[]`. Args: `status` = `'visible'` (varsayılan) \| `'open'` \| `'all'`; `category` (string veya dizi); `slugs`, `ids` (dizi); `progress` (bool, varsayılan true) |
| `flxb_get_type( int\|string $id_or_slug )` | `?array` |
| `flxb_type_state( array\|int\|string $t )` | `'open'` \| `'upcoming'` \| `'ended'` \| `'closed'` \| `'archived'` |
| `flxb_type_meta( $t, $key, $default = null )` | mixed |
| `flxb_form_element_for_option( int $type_id, int $form_id, string $key, int $qty = 1 )` | Flux Forms eleman id'si veya `''` |
| `flxb_split_names( string $raw )` | `string[]` |
| `flxb_proxy_text( int $version = 0 )` | `?array {version, text, created_at, user}` |
| `flxb_bank_accounts()` | `array` — 2.2.2: önce WooCommerce "Doğrudan banka havalesi" (BACS) hesapları (`id` `wc0`, `wc1`…, `source` `woocommerce`), sonra `flxb_bank_accounts` filtresiyle gelenler (tema); aynı IBAN bir kez |
| `flxb_bank_account()` | 2.2.2: `?array {holder, iban, iban_display, bank}` — her yerde gösterilecek **tek** havale hesabı. Sıra: Flux Donate Havale yöntemindeki IBAN → WooCommerce BACS ilk hesap → Tema Ayarları > Banka hesabı. Bağış sayfası, ödeme/teşekkür sayfası ve Çağrı Merkezi bunu kullanmalı |
| `flxb_create_ref_request( array $args )` | 2.3.0: personel adına bağış talebi (`flxb_create_ref`). Args: `type_id, option, units, amount` (serbest tutar), `names` (adına isimler), `cert`, `donor {name, phone, email}` (telefon ya da e-posta zorunlu), `ref_user_id` (varsayılan giriş yapan), `send_email`, `note`. Bekleyen WooCommerce siparişi açar (`created_via` `flxb-ref`, kaynak `staff`), sipariş meta `_flx_ref_user`. Dönüş `{order_id, donation_id, pay_url, amount, currency, emailed}` ya da `WP_Error`. Kanca: `flxb_ref_request_created( $out, $ref_user_id, $contact )` |

### Tür yapısı (filtre `flxb_type_public`)

- Kimlik ve içerik: `id, slug, title, category, category_label, description, image_id, image_url, currency`
- Durum ve tarih: `state, state_message, start_date, end_date`
- `options[]`: `{key, label, price (float; null = fiyat açıklanmadı), unit (null = belirtilmedi), unit_set (bool), unit_label, unit_label_plural, units_per, active}`. Pasif veya fiyatsız seçenek bağlı formda gizlenir ve seçilemez.
- Kurallar ve formlar: `allow_free, min_amount, allow_quantity, max_quantity, allow_recurring, form_id, form_ids, requires_proxy`
- `progress`: `{raised, gifts, donors, goal, percent, days_left, currency, other}`; hedef yoksa `null`
- `meta`: nesne

## REST

- `GET /wp-json/flxb/v1/types?status=visible|open&category=…`
- `GET /wp-json/flxb/v1/types/<id|slug>` (arşivlenmişse 404)

## JS (bağlı formun olduğu sayfada)

- `FLXB.preselect({ form?, option, qty?, beneficiaries?: string[], lock?: bool })` → `Promise<boolean>` (sayfa yenilenmez; `lock` seçim adımını "Seçiminiz … · Değiştir" özetiyle gizler)
- `FLXB.unlock()`, olay `flxb:unlock`
- `window.flxbForms[formId] = { form, type, slug, currency, opts, prices, qty, maxQty, beneficiaries }`
- Olaylar: `flxb:ready`, `flxb:change` → `detail { form, option, qty, total }`
- URL: `?flxb_opt=<seçenek anahtarı>&flxb_qty=<adet>&flxb_form=<form id>`

## Hook'lar

- Filtreler: `flxb_units`, `flxb_bank_accounts`, `flxb_type_meta_clean`, `flxb_kurban_enabled`, `flxb_type_public`
- Aksiyonlar (2.3.0): `flxb_ref_notify( string $kind, int $user_id, array $gift )` — referansı olan bağış ödenince (`paid`), iptal/başarısız olunca (`cancelled`), iade olunca (`refunded`); bağış (sipariş) ve olay başına bir kez, yalnız `flxb_ref_notify` yetkili referansa. `$gift`: `donation_id, order_id, amount, currency, donor_name, donor_phone, items[{title, option, quantity, amount}], admin_url`. (Çağrı Merkezi WhatsApp bildirimini kendi `flxb_donation_updated` dinleyicisiyle yapıyor; bu kanca e-posta vb. için.) `flxb_ref_request_created`.
- Aksiyonlar: `flxb_type_fields`, `flxb_type_saved( $id, $old )`, `flxb_form_prices_synced( $form_id, $type_id )`, `flxb_donation_updated`

## Ödeme: iyzico (1.2.0)

2.0'da kart ödemeleri WooCommerce yöntemleriyle alınır (yukarıda). Aşağıdaki iyzico adresleri, filtreler ve cron Flux Donate iyzico (`flxb_iyzico`) ve aylık yenilemeler için geçerli olmaya devam eder; 1.x'in WooCommerce'siz form ödemesi yalnızca "Flux Forms ödeme formları WooCommerce ödeme sayfasında devam eder" ayarı kapatılırsa kullanılır.

### REST

- `POST|GET /wp-json/flxb/v1/iyzico/callback` — iyzico Checkout Form dönüş adresi (`token`). Ödeme `checkoutform/auth/ecom/detail` ile doğrulanmadan bağış kaydedilmez; tekrar çağrı güvenlidir (idempotent).
- `GET|POST /wp-json/flxb/v1/iyzico/cancel?s=<id>&k=<imza>` — bağışçının düzenli bağış iptal bağlantısı (e-postada gelir; GET onay sorar, POST iptal eder).
- `POST /wp-json/flxb/v1/iyzico/notify` (1.2.4) — iyzico **İşyeri Bildirimleri Url** (işyeri başına tek adres). Gövde JSON `{iyziEventType, token, paymentConversationId, …}`, başlık `X-IYZ-SIGNATURE` = `base64(sha1(secretKey . iyziEventType . token, raw))`.
  - Flux Donate ödemesi (`paymentConversationId` `flxb_…` / `flxbr-…` ya da kayıtlı token): imza gelirse doğrulanır (yanlışsa **400** `{error: signature_not_valid}`), ödeme token ile iyzico'dan tekrar sorgulanır, bekleyen kayıt kesinleşir; yanıt 200 `{received, handled_by: "flxb", paid}` (yenileme: `flxb_renewal`). İdempotent.
  - Değilse ve iyzico WooCommerce eklentisinin `iyzicoWebhookUrlKey` rotası kayıtlıysa: `rest_do_request` ile `/iyzico/v1/webhook/{key}` adresine aynı gövde ve `X-IYZ-SIGNATURE` başlığıyla iletilir (çağrı süresince `$_SERVER['HTTP_X_IYZ_SIGNATURE']` da ayarlanır); onun durum kodu ve gövdesi döner.
  - iyzico Subscription API bildirimi (`subscriptionReferenceCode` ya da `iyziEventType` `subscription…`): 200 `{handled_by: "subscription_ignored"}`.
  - Diğerleri: 200 `{handled_by: "ignored"}`.
  - Son bildirim: seçenek `flxb_iyzico_notify_last` `{at, kind}`; aksiyon `flxb_iyzico_notification( $kind )`.
- `POST /wp-json/Emsfb/v1/forms/payment/webhook/iyzico` — eski adres, `flxb/v1/iyzico/notify` ile aynı davranır.

### Filtreler

| Filtre | Ne için |
|---|---|
| `flxb_iyzico_credentials( array $c )` | `{api_key, secret_key, base, sandbox, source}`; kaynak `woocommerce` \| `woocommerce-subscription` \| `own` |
| `flxb_iyzico_api_client( FLXB_Iyzico_Api $api )` | HTTP istemcisini değiştirmek (test) |
| `flxb_iyzico_buyer( array $buyer, array $contact )` | iyzico `buyer` alanları (TC kimlik yer tutucu `11111111111`, şehir/ülke varsayılanları) |
| `flxb_iyzico_checkout_request( array $body, array $meta )` | Checkout Form başlatma isteği |
| `flxb_iyzico_renewal_request( array $body, array $sub )` | Yenileme ödemesi (`/payment/auth`, `paymentGroup` SUBSCRIPTION) |
| `flxb_iyzico_retry_delays( int[] $seconds )` | Başarısız yenilemede bekleme süreleri; varsayılan 1 gün, 2 gün (toplam 3 deneme) |
| `flxb_iyzico_donor_emails( bool $send, string $what, array $sub )` | `started` / `failed` e-postalarını kapatmak |
| `flxb_iyzico_timeout( int $seconds )` | HTTP zaman aşımı (30) |

### Aksiyonlar

- `flxb_iyzico_paid( object $pay_row, array $iyzico_response )` — tek seferlik veya ilk ödeme doğrulandı.
- `flxb_iyzico_renewal_paid( int $sub_id, int $donation_id, array $response )`
- `flxb_iyzico_renewal_failed( int $sub_id, string $error, bool $past_due )`
- `flxb_iyzico_subscription_canceled( int $sub_id, string $by )` — `admin` \| `donor`.

### Cron

- `flxb_iyzico_renewals` (saatlik, WP-Cron). iyzico Subscription eklentisinin `iyzico_subscription_renewal_check` / `iyzico_subscription_retry_failed` kancalarıyla ve meta alanlarıyla ortak hiçbir şey kullanmaz.
- Kart referansları (`cardUserKey`, `cardToken`) `wp_flxb_subscriptions.meta` içinde şifreli tutulur (anahtar `wp_salt('auth')`'tan türetilir; salt değişirse kayıtlı kartlar kullanılamaz). Hiçbir API'de dönmez.

## WooCommerce (2.0)

Bağışlar WooCommerce sepeti ve ödeme sayfasıyla alınır. WooCommerce katmanı çalışıyor mu: `flxb_wc_active()` (aksiyon `flxb_wc_loaded` de bu anda tetiklenir).

### PHP fonksiyonları

| Fonksiyon | Dönüş |
|---|---|
| `flxb_wc_active()` | `bool` |
| `flxb_wc_form_available()` | `bool`: "Bağış seçenekleri" bloğunun gösterecek türü var mı (temadaki "kart ile bağış" bloklarını açıp kapatmak için) |
| `flxb_create_order( array $args )` | `WC_Order\|WP_Error`. Çağrı merkezi, içe aktarma ve diğer eklentiler için sipariş açar; aşağıya bakın |
| `flxb_donation_order_id( int $donation_id )` | Bağış kaydının WooCommerce siparişi (`0` = WooCommerce'le ödenmemiş) |
| `flxb_order_donation_ids( WC_Order\|int $order )` | `array( order_item_id => donation_id )` |
| `flxb_donate_url( int\|string $type, string $option = '' )` | Bağış sayfası adresi, tür sekmesi ve seçenek seçili: `…/bagis/?sekme=<slug>&flxb_opt=<anahtar>` |

`flxb_create_order( $args )`:

- `lines` (zorunlu): `[ { type_id, option, units, amount, cert ('' | 'online' | 'print'), name, for_other, ben_name, ben_phone, ben_email, donation_id, beneficiaries, proxy_consent } ]`. 2.0.1: vekâlet isteyen türde `beneficiaries` (adlar dizisi ya da satır satır metin; hisse başına bir ad, fazlası atılır) ve `proxy_consent` (`{ version, accepted_at, source }`) verilir; kayıt defterinde her hisse kendi adıyla ayrı kalem olur, onay `meta.proxy_consent`'e yazılır. Fiyat türden hesaplanır; serbest tutarlı satırda `amount`. `donation_id` verilirse yeni kayıt açılmaz, o kayda bağlanır. `cert = 'print'` basılı sertifika ayarı "Seçilebilir" değilse hata döner.
- `billing`: `{ first_name, last_name, email, phone }`; `customer_id` (boşsa e-postadan bulunur).
- `payment_method`: `'flxb_havale'` (açıklama kodu üretilir) ya da herhangi bir yöntem kimliği; `payment_title`; `status`: `pending` (varsayılan) \| `on-hold` \| `completed`; `created_via` (varsayılan `flxb-api`); `note`; `emails` (WooCommerce e-postaları, varsayılan `false`).
- Her satır bağış kaydına yazılır (kayıt defteri).

### Blok, kısa kod, ön seçim

- Blok `flx-wc-bagis/secenekler` (sunucuda çizilir). Öznitelikler: `types` (slug/id, virgülle; boş = "Bağış seçenekleri bloğunda göster" işaretli türler), `tab` (ilk açılacak türün slug'ı ya da id'si), `align`. `general` özniteliği 2.0'da kullanılmıyor, kayıtlı bloklar bozulmasın diye kabul ediliyor.
- Kısa kod `[flx_bagis_secenekleri tur="slug1,slug2" sekme="slug"]`.
- Adres parametreleri: `?sekme=<tür slug>`, `?flxb_opt=<seçenek>` (`?paket=<seçenek>` da kabul edilir), `?flxb_qty=<adet>`.
- Ön yüz sınıfları `.flxwb`, `.flxwb-*` (temanın CSS'i bunlara dayanıyor, değişmedi). − / + adetli kart: `.flxwb-pkg--qty`, canlı tutar `[data-flxwb-line]`, adet `[data-flxwb-count]`.
- 2.0.1, vekâlet isteyen tür (`requires_proxy`): panelde `.flxwb-proxy` alanı çıkar. Alanlar: adlar `owners[<tür id>]` (her satıra bir ad, en fazla hisse sayısı kadar), vekâlet metni (`flxb_proxy_text()`, sürüm numarasıyla) ve onay kutusu `proxy[<tür id>]` (değeri metnin sürümü). "Bu bağış kimin için?" bölümü bu türde gizlenir. Sunucu onaysız, eski sürümle ya da hisseden fazla adla gelen isteği reddeder (`FLXB_WC_Cart::build_line`, girdi `beneficiaries`, `proxy_consent`).
- 2.0.1, sertifikası olmayan tür: sertifika seçimi, WhatsApp/e-posta teslim kartları ve "Bana da gönder" gösterilmez, sunucu bu türde teslim telefonu ve e-postasını saklamaz. "Başkası adına" isteğe bağlı kalır (ad yalnız bağış kaydına yazılır).

### Tür meta anahtarları (tür düzenleme › "Online bağış sayfası")

| Anahtar | Değer |
|---|---|
| `in_form` | bool: blokta ve ürün XML'inde gösterilir |
| `certificate` | bool: ödenen satır için Flux Certificates sertifikası |
| `cert_template` | int: şablon (0 = WooCommerce ayarındaki) |
| `custom_option` | seçenek anahtarı: kartında − / + olan seçenek; fiyat = adet × seçenek fiyatı |
| `presets` | float[]: serbest tutarın hazır tutarları (en çok 8) |
| `page_url` | Ürün sayfası ve XML bağlantısının gideceği yer. 2.0.1: sitedeki adres göreli yol olarak saklanır (`/kurban/`), başka site mutlak kalır. Okumak için `FLXB_WC::type_page_url( $type )` (filtre `flxb_type_page_url`) |
| `page_id` | int: `page_url` kaydedilirken WordPress sayfayı tanırsa yazılır; okurken önce bu sayfanın kalıcı bağlantısı kullanılır (site taşınsa da doğru adres) |
| `wc_product_id` | int: gizli ürün (Flux Donate yazar) |

Seçeneklerde `badge`: `''` \| `populer` \| `avantajli` \| `aile` (filtre `flxb_option_badges`).

### Sipariş ve satır meta'sı

- Sipariş: `_flxb_donation_ids` (satır → bağış), `_flxb_ref` (havale açıklaması), `_flxb_code` (kod), `_flxb_state` (`awaiting` \| `uploaded` \| `approved`), `_flxb_receipts` (dekontlar), `_flxb_notified`, `_flxb_pay_id` (Flux Forms ödeme kaydı), `_flxb_subscription_id` ve `_flxb_renewal_period` (yenileme siparişi), `_flxb_callcenter_ref`.
- Satır (gizli): `_flxb` (satırın tamamı), `_flxb_type_id`, `_flxb_donation_id`, `_flxb_cert_id`. Görünen: `flxb_fidan`, `flxb_units` (`adet|birim`), `flxb_monthly`, `flxb_on_behalf`, `flxb_names` (2.0.1, vekâlet türü: "1. Ali · 2. Ayşe"), `flxb_proxy` (2.0.1, kabul edilen vekâlet metni sürümü), `flxb_cert`, `flxb_cert_name`, `flxb_cert_address`, `flxb_cert_no`. `_flxb` içinde 2.0.1'den itibaren `beneficiaries` (hisse sırasıyla adlar) ve `proxy_consent` (`{ version, accepted_at, source: cart | form | … }`) de bulunur; kayıt defteri satırının `meta.proxy_consent` alanına aynen yazılır.
- Bağış kaydı: `uniq = wc:<sipariş>:<satır>`, `provider = woocommerce`, `wc_order_id` sütunu; yenilemelerde `source = renewal`.

### Ödeme yöntemleri

- `flxb_havale`: Havale (bağış). Ayarlar `woocommerce_flxb_havale_settings`. Sipariş "Beklemede" açılır; yönetici "Ödemeyi onayla" deyince tamamlanır.
- `flxb_iyzico`: Kart (iyzico) - Flux Donate. Aylık bağışların tek yöntemi; resmî `iyzico` eklentisi yoksa tek seferlik kart ödemesi de. İkisi birlikte gösterilmez (ayar `card_gateway` = `auto` \| `flxb`).
- Resmî iyzico WooCommerce eklentisi (`iyzico`): tek seferlik kart.
- Stripe: WooCommerce yöntemi olarak taşınmadı (gerekirse resmî "WooCommerce Stripe" eklentisi; aylık bağış onunla olmaz). 1.x'in Flux Forms Stripe sağlayıcısı (`providers/`) kodda duruyor, WooCommerce akışında kullanılmaz.

### REST ve adresler

- `POST /wp-json/flxb-wc/v1/receipt`: dekont yükleme, alanlar `order`, `key` (sipariş anahtarı = yetki; önbellekli sayfalarda çalışır), dosya `dekont` (PDF/JPG/PNG/WEBP/HEIC, en büyük boyut ayarda). Yanlış anahtar **404**. Siparişte en çok 5 dekont.
- Dekontu açma: `/?flxb_dekont=<sipariş>&i=<sıra>&key=<sipariş anahtarı>` (ya da yönetici nonce'u). Dosyalar `uploads/flxb-dekont/` altında, rastgele adla ve doğrudan erişime kapalı (`.htaccess`; Nginx'te bu klasörü kapatın).
- Hesabım uç noktası: `bekleyen-bagislar`.
- Ürün XML'i: `/bagis-urunleri.xml` (kalıcı bağlantı yoksa `/?flxb_feed=google`); ürün kimliği `flxb-<tür>-<seçenek>`.
- Flux Forms el değiştirme: `/?flxb_wc_handoff=<token>` (tek kullanımlık; satırları sepete koyup ödeme sayfasına yönlendirir).

### Filtreler

| Filtre | Ne için |
|---|---|
| `flxb_wc_line( array $line, array $in, array $type )` | Sepet satırı oluşturulurken |
| `flxb_type_page_url( string $url, array $type )` | 2.0.1: türün sayfa adresi (çözümlenmiş) |
| `flxb_havale_account( array\|null $account, string $iban )` | 2.0.1: Havale yönteminde IBAN boşken kullanılacak hesap `{ holder, iban, bank }` (varsayılan: `flxb_bank_accounts` içinde mağaza para birimindeki ilk hesap; para birimi yazılmamış hesap TRY sayılır) |
| `flxb_wc_simple_checkout( bool )` | Yalnız bağışlı sepette adres alanlarını kısaltmak |
| `flxb_wc_ledger_row( array $row, WC_Order $order, $item )` | Kayıt defterine yazılan satır |
| `flxb_wc_certificate_values( array $values, $item, WC_Order $order, array $template, array $line )` | Sertifika değişkenleri |
| `flxb_wc_feed_type( bool $listed, array $type )`, `flxb_wc_feed_items( array $items )` | Ürün XML'i |
| `flxb_wc_forms_checkout( bool )` | Flux Forms ödemelerini WooCommerce'e yönlendirmek |
| `flxb_wc_form_fallback_type( int $type_id )` | Türe bağlı olmayan formun yazılacağı tür |
| `flxb_wc_iyzico_checkout_request( array $body, WC_Order $order )` | Flux Donate iyzico Checkout Form isteği |
| `flxb_create_order()` 2.3.0 | `ref_user_id` argümanı: siparişe `_flx_ref_user` yazar |
| `flxb_wc_mirror_callcenter( bool, int $donation_id, array $data )` | Çağrı merkezi kaydına sipariş açılsın mı |
| `flxb_wc_callcenter_emails( bool, int $donation_id )` | O siparişte WooCommerce e-postaları |
| `flxb_option_badges( array )` | Rozet listesi |
| `flxb_email_types( array )`, `flxb_use_iletisim( bool )` | E-posta şablonları / Flux Mail kullanımı |
| `flxb_menu_icon_url( string )` | Yönetim menüsü simgesi |

### Aksiyonlar

- `flxb_wc_loaded`
- `flxb_wc_preregistered( WC_Order $order )`: havale ön kaydı açıldı.
- `flxb_wc_receipt_uploaded( WC_Order $order, array $receipt )`. 2.3.2: yükleme önce `flx_dekont_register` (Flux Chat dekont defteri) ile kaydedilir; `$receipt['ledger']` defter kaydı, tekrar ise `$receipt['dup']` (count, source, donation_id, payer, created_at) ve sipariş meta `_flxb_dekont_dup` = 1.
- `flxb_wc_certificates_issued( WC_Order $order, array $made )`
- `flxb_wc_iyzico_paid( WC_Order $order, array $iyzico_response )`
- `flxb_wc_recurring_started( int $subscription_id, WC_Order $order, $item )`
- `flxb_wc_form_paid( string $track, WC_Order $order, object $pay_row )`

### Flux Forms'a ödeme bildirimi: `flxf_external_payment`

Ödeme formundan gelen sipariş ödenince Flux Donate `do_action( 'flxf_external_payment', $ctx )` çağırır; Flux Forms 2.1.0 bununla "Ödeme tamamlandı" otomasyonlarını çalıştırır. `$ctx`:

`event_type` (`payment`), `track_code`, `form_id`, `form_fields`, `submitted_values`, `enriched_submitted_values`, `page_url`, `wc_order_id`, `verified_payment { amount (o form satırlarının toplamı), currency, reference (sipariş no), source ('woocommerce') }`.

Formun sertifika akışı varsa (`flxf_automations_form_issues_certificate( $form_id )`) Flux Donate o satırlar için ayrıca sertifika üretmez.

### E-posta şablonları (Flux Mail)

`flxi_email_types` filtresine "Flux Donate" grubuyla kaydedilir, `flxi_send_email()` ile gönderilir; Flux Mail yoksa `wp_mail` (düz metin). Gönderme: `FLXB_Mail::send( 'flx-donate/<anahtar>', $to, $vars )`.

| Şablon | Kime | Değişkenler |
|---|---|---|
| `flx-donate/recurring-started` | bağışçı | `ad, tutar, aralik, sonraki, iptal_link` |
| `flx-donate/recurring-failed` | bağışçı | `ad, tutar, aralik, deneme, bagis_link, iptal_link` |
| `flx-donate/receipt-uploaded` | bağış sorumlusu | `siparis, ad, tutar, aciklama, dosya, link` (yönetim sipariş linki) |

Kısa WhatsApp/SMS: `FLXB_Mail::message( $channel, $phone, $text )`: önce Flux Mail (`flxi_send_message`, kanal bağlıysa), sonra Flux Certificates kuyruğu (`flxs_enqueue`).

### Ayarlar (seçenek `flxb_wc`)

`template_id, event_id, cert_channels, print_mode ('soon' varsayılan | 'on' | 'off'), print_fee, auto_complete, simple_address, after_add ('cart' | 'checkout' | 'stay'), page_id, notify_channels, notify_wa, notify_sms, receipt_max_mb, receipt_notify, forms_checkout, card_gateway, currencies (2.15: ek para birimi kodları)`.

### Para birimi seçimi (2.15.0)

Kur çevrimi yok; fiyatlar para birimi başına elle girilir. Seçim çerezde (`flxb_currency`), yalnız ön yüzde WooCommerce para birimi olur (`woocommerce_currency`, öncelik 50). Yönetim, cron, WP-CLI, admin-ajax ve `wc/store` / `flxb-wc` dışındaki REST istekleri mağaza para biriminde kalır.

| Fonksiyon | Dönen |
|---|---|
| `flxb_currencies()` | `[ CODE => { code, symbol, label, current, url } ]`, mağaza para birimi ilk. WooCommerce yoksa yalnız varsayılan |
| `flxb_current_currency()` | Bu istekteki para birimi (`EUR`) |
| `flxb_set_currency_url( $code, $url = '' )` | Para birimini değiştirip aynı sayfaya dönen bağlantı (`?flxb_currency=EUR`; çerez yazılır, parametresiz adrese yönlenir) |
| `FLXB_Types::option_price( $t, $o, $code )` | Seçeneğin o para birimindeki fiyatı (0 = sunulmuyor) |
| `FLXB_Types::min_amount_in( $t, $code )` | Serbest tutarın o para birimindeki en azı |

Kısa kod `[flx_bagis_para_birimi]`: küçük seçici (select). Filtreler: `flxb_currency_codes` (sunulan kodlar), `flxb_current_currency` (seçilen kod, `$codes`). Tema örneği:

```php
if ( function_exists( 'flxb_currencies' ) && count( flxb_currencies() ) > 1 ) {
	foreach ( flxb_currencies() as $c ) {
		printf( '<option value="%s"%s>%s %s</option>', esc_url( $c['url'] ), selected( $c['current'], true, false ), esc_html( $c['symbol'] ), esc_html( $c['code'] ) );
	}
}
```

Çeviri (2.15.1, Flux Lang): tür meta `i18n` `{ "en": { "title": "…", "description": "…", "options": { "fidan": "Sapling" } } }`. `FLXB_Types::localize( $t, $lang = null )` (varsayılan: ziyaretçinin dili) gösterim için çevirir; form, sepet, ödeme ve `flxb_type_public()` bunu ön yüzde kullanır. Kayıtlar varsayılan dildeki metni tutar.

Seçenek: `prices` `{ "EUR": 120, "USD": 130 }` (türün kendi para birimi `price`). Tür meta: `min_by_currency` `{ "EUR": 10 }`, `presets_by_currency` `{ "EUR": [10, 25, 50] }`. `flxb_type_public()` seçeneklerde `prices` döner. Sepet satırı `currency` taşır; serbest tutar ve form satırı yalnız kendi para biriminde ödenir.

### Görünüm (2.16.0, seçenek `flxb_look`)
- Alanlar: `form_style` classic|steps|panel|cards, `pay_style` theme|cards|list|tabs, `primary`, `accent` (hex, boş = tema), `radius` (0–28 px, boş = tema), `order` (options, who, cert, design, monthly), `type_label`, `option_label`, `share_phone` (0/1). Şemada (`flx_settings_schema`), ön ayar `settings.flxb_look` ile yazar.
- `FLXB_WC_Look::get( $key )`, `FLXB_WC_Look::label( 'type_label'|'option_label' )`.
- Filtreler: `flxb_look_label( $text, $key, $locale )`, `flxb_pay_icons( array( 'bank' => [gateway id], 'paypal' => [...], 'cash' => [...] ) )`, `flxb_icons( $paths )`.
- Ödeme sayfasında body sınıfı `flxb-pay flxb-pay-<stil>`; CSS değişkenleri `--flxb-l-p`, `--flxb-l-a`, `--flxb-l-r`.
- Form alanları: `owner_mode[<tür>]` self|other, `owners[<tür>][]`, `owner_phones[<tür>][]` (E.164). Satır meta `beneficiary_phones` (adlarla aynı sırada).
- Tür meta: `subtitle` (80), `icon` (sheep, cow, camel, heart, gift; boş = otomatik).
- 2.17.0: `opt_view` list|select|smart; `fee_on`, `fee_checked`, `fee_rates` `{ "<gateway id>": { "pct": 2.9, "fixed": 0.25 } }`. Form alanı `fee_cover=1` (WC oturumu `flxb_fee_cover`), Store API güncelleme ad alanı `flxb-fee` (`{ method, cover }`), sipariş meta `_flxb_fee_cover`. `FLXB_WC_Fee::amount( $tutar, $gateway )`. Onay metni filtresi `flxb_terms_text`.

### Sürüme duyarlı yerler (WooCommerce güncellemesinden sonra deneyin)

Hepsi herkese açık API, ama blok tarafı WooCommerce'te en hızlı değişen kısım:

- Blok ödeme yöntemleri: `Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType`, `woocommerce_blocks_payment_method_type_registration`, ön yüzde `wc.wcBlocksRegistry.registerPaymentMethod` ve `wc.wcSettings.getSetting` (Havale ve Flux Donate iyzico).
- Uyumluluk bildirimi: `FeaturesUtil::declare_compatibility( 'custom_order_tables' | 'cart_checkout_blocks' )`.
- Alan kısaltma: `woocommerce_get_country_locale` / `_default` (klasik ve blok ödeme sayfasında ortak).
- Teşekkür sayfası kartları `woocommerce_thankyou` aksiyonuyla eklenir (blok temalı "Sipariş onayı" şablonunda da çalışıyor; şablon özelleştirilirse kontrol edin).
- Tutar her hesaplamada `woocommerce_before_calculate_totals` ile türden yazılır.
- Sipariş işlemleri yalnızca `wc_get_order()`, `$order->get_meta()/update_meta_data()`, `wc_get_orders()`, `wc_create_refund()` ile (HPOS açık/kapalı fark etmez).

## Sanal POS altyapısı (2.13.0)

WooCommerce'e kayıtlı her ödeme eklentisi bağış ödeme sayfasında görünür (whitelist yok). `FLXB_WC_Gateways`:

- `FLXB_WC_Gateways::caps( $gateway )` → `[ cap => [ state, note ] ]`. cap: `blocks`, `no_cart`, `no_address`, `verified`, `installments`, `tokenization`, `refunds`, `monthly`. state: `yes` | `flux` (Flux Donate yapıyor) | `page` (sağlayıcının sayfasında) | `no` | `unknown`.
- Otomatik tespit: `supports('tokenization')`, `supports('refunds')` + `process_refund` gerçekten yazılmış mı, blok kaydı (`PaymentMethodRegistry`), `has_fields`.
- Blok fallback: kendi blok entegrasyonu olmayan, kartı kendi sayfasında alan (`has_fields = false`) etkin gateway'ler ödeme bloğuna eklenir (`FLXB_WC_Generic_Blocks`, ayar `block_fallback`). Sipariş Store API'den geçer, WooCommerce eklentinin kendi `process_payment()` yönlendirmesini izler.
- Adaptör: bir eklentiyi tarif eder (ve gerekiyorsa düzeltir).

```php
add_filter( 'flxb_gateway_adapters', function ( $a ) {
	$a['my_pos'] = array(
		'label' => 'My POS',
		'logo'  => 'https://…/logo.svg',                // Tema › Ödeme logoları'nda hazır logo (anahtar gw-my_pos)
		'note'  => 'Test modu',                          // tabloda adın altında
		'caps'  => array( 'installments' => array( 'page', 'POS sayfasında' ), 'verified' => array( 'yes', '' ) ),
	);
	return $a;
} );
add_filter( 'flxb_gateway_caps', function ( $caps, $gateway ) { return $caps; }, 10, 2 );
```

- EsnekPOS adaptörü (`FLXB_WC_Esnekpos`, gateway `esnekpospos`): başlatma order-pay sayfasında (`template_redirect`), dönüş `wc-api/flxb_esnekpos?order=&key=` (eski `?wc-api=WC_Gateway_Esnekpospos` de karşılanır, sipariş anahtarı verilmez). Ödendi sayılması için ProcessQuery `STATUS=SUCCESS`, `RETURN_CODE=0`, referans eşleşmesi, `AMOUNT >= toplam`, varsa `TRANSACTIONS` içinde `STATUS_ID=3`. Referans `<sipariş>-<zaman36>` (meta `_flxb_esnek_ref`), ödeme bilgisi meta `_flxb_esnekpos`. Filtreler: `flxb_esnekpos_base_url`, `flxb_esnekpos_request`, `flxb_esnekpos_amount`; aksiyon `flxb_wc_esnekpos_paid( $order, $query )`.
- Tek kart yöntemi: ayar `card_method` (`auto` | `iyzico` | gateway id | `all`). `auto` = ilk etkin üçüncü taraf kart gateway'i, yoksa `iyzico`. `woocommerce_available_payment_gateways` (60) diğer kart yöntemlerini çıkarır; admin, aylık bağış bağlamı ve order-pay'de siparişin kendi yöntemi hariç. Yardımcılar `FLXB_WC_Gateways::card_method()`, `is_card( $gw )`, `hidden_by_card_method( $id )`.
- Tema logoları: `flx_pay_logos_builtin` filtresine adaptör logoları `gw-<id>` anahtarıyla eklenir (`default` = gateway etkin ve seçili kart yöntemi; iyzico bandı yalnız kart yöntemi iyzico/hepsi iken varsayılan).

## Referans ve roller (2.2.0, Flux Roles)

- Sütun `ref_user_id` (bağışlar ve düzenli bağışlar). Kaynak sırası: siparişte `_flx_ref_user` (Flux Roles) → düzenli bağışın referansı (aylık tahsilatlar) → Çağrı Merkezi'nde bağışı kaydeden temsilci (`meta.created_by`, `flx_referans` yetkisi varsa) → "Bağış ekle"de seçilen kişi. `FLXB_Donations::record()` `ref_user_id` alır; dolu referans ezilmez.
- `FLXB_Refs::set( $donation_id, $user_id )`: siparişin tüm kalemleri, düzenli bağış, sipariş meta'sı ve bu bağışlardan yapılmış sertifikalar (`flxs_set_certificate_ref`) birlikte değişir; geçmişe yazılır. Aksiyon `flxb_donation_ref_changed( $id, $new, $old )`.
- `FLXB_Donations::query( array( 'ref_user_id' => '5' ) )` filtreler (`'0'` = referanssız).
- Yetkiler: `flxb_view_own` (Bağışlar menüsü; liste, ayrıntı, CSV ve makbuz yalnız `ref_user_id = ben`; bağışçılar, türler, düzenli bağışlar, ayarlar kapalı), `flxb_dashboard` (Dashboard, genel toplamlar; en çok bağış yapanlarda iletişim ve bağlantı yok). `flx_role_caps_supported` ile Flux Roles'e bildirilir.
- Bağıştan sertifika oluşurken `flxs_create_certificate()`'e referans olarak bağışın `ref_user_id`'si geçer (2.2.0 öncesi müşteri ID'si geçiyordu).

## Sistem kontrolü: ortak sözleşme (1.2.1)

FluxWP eklentileri ve tema aynı ekrana satır ekler. Kayıt filtresi `flx_system_checks` ( array $checks ), her eleman:

| Alan | Değer |
|---|---|
| `id` | benzersiz, `<eklenti>.<konu>` (ör. `flxb.cron.heartbeat`, `flxf.recaptcha`, `flxs_…`) |
| `source` | görünen kaynak adı (`Flux Donate`, `Flux Forms`, `Flux Certificates`, tema adı) |
| `group` | `Cron` \| `Payments` \| `Plugins` \| `Config` \| `Email` \| `Security` \| `Forms` \| başka bir metin (bilinen gruplar çevrilmiş başlıkla, diğerleri olduğu gibi gösterilir; boşsa `Other`) |
| `label` | ne kontrol edildiği (boşsa satır atlanır) |
| `status` | `ok` \| `warning` \| `error` \| `info` (başka değer → `info`) |
| `message` | kişiye yönelik düz metin: ne bulundu, ne yapmalı |
| `action_url` | isteğe bağlı yönetim bağlantısı (ayar sayfası veya nonce'lu "Test" adresi) |
| `action_label` | isteğe bağlı bağlantı metni |

Kurallar: kontroller ucuz olmalı (seçenek, sabit, birkaç sorgu). Dış HTTP isteği yalnızca yönetici bir "Test" bağlantısına tıklayınca yapılır; sonuç saklanıp ilgili satırda gösterilir. Gizli anahtar hiçbir satırda yazılmaz.

```php
add_filter( 'flx_system_checks', function ( $checks ) {
	$checks[] = array( 'id' => 'tema.logo', 'source' => 'Tema', 'group' => 'Config', 'label' => 'Logo',
		'status' => 'ok', 'message' => 'Logo yüklü.' );
	return $checks;
} );
```

Fonksiyonlar (Flux Donate ve Flux Forms'da aynı dosya, `includes/flx-system-checks.php` / `includes/flx/system-checks/flx-system-checks.php`; her biri `function_exists` korumalı, hangisi önce yüklenirse sonuç aynı):

- `flx_system_checks_run()`: argümansız. `apply_filters( 'flx_system_checks', array() )` sonucundaki her diziyi `wp_parse_args` ile varsayılanlara (`id, source, group, label, status='info', message, action_url, action_label` hepsi `''`) tamamlar; dizi olmayan veya `label`'ı boş olanı atlar; geçersiz `status` → `'info'`. Sıralama yapmaz, dizi döner. Flux Certificates 1.2.4'teki tanımla birebir aynıdır.
- `flx_system_checks_sorted()`: `flx_system_checks_run()` sonucunu gösterim için sıralar (grup sırası Cron, Payments, Plugins, Config, Email, Security, Forms, sonra diğerleri A–Z; grup içinde önce hatalar). Bir filtre geri çağrısı hata fırlatırsa tek bir `error` satırı döner. Hata kimliklerini `flx_system_checks_last` seçeneğine yazar (üst çubuk için).
- `flx_system_checks_summary( array $checks )`: `array( 'ok' => n, 'warning' => n, 'error' => n, 'info' => n )`.
- `flx_system_checks_render( array $args = array() )`: ekranı yazdırır (özet, gruplu tablo, durum simgesi + yazı, işlem bağlantıları, "Yeniden kontrol et"). `$args`: `recheck_url` (düğme adresi, varsayılan geçerli sayfa), `checks` (hazır liste; yoksa `flx_system_checks_sorted()`), `heading` (bool, başlık yazdır). Stil kendi içindedir (`.flxsc-*`), 782 px altında kart görünümüne geçer. 1.2.2: özet rozetleri `button.flxsc-chip[data-filter][aria-pressed]`; tıklayınca yalnız o durumdaki satırlar (`tr[data-status]`) görünür, boş grup kartları gizlenir, tekrar tıklayınca hepsi; `#ok|#warning|#error|#info` ile filtreli açılır (temanın ekranıyla aynı davranış). Saat Türkçe yerelde `H:i`.
- `flx_system_checks_boot()`: hata varken Başlangıç ekranında uyarı ve üst çubukta "Sistem: N hata" (yalnız `manage_options`). Kullanıcı gizleyebilir; gizleme, hata listesi ve `flx_system_checks_versions` değişince sıfırlanır.
- `flx_system_checks_labels()`: ekran metinleri.

Yardımcı filtreler: `flx_system_checks_labels` (ekran metinlerinin çevirisi), `flx_system_checks_page_url` (uyarının bağlandığı ekran; Flux Donate varsa `Bağışlar › Ayarlar › Sistem kontrolü`, yoksa Flux Forms `Formlar › Sistem kontrolü`), `flx_system_checks_versions` (`slug => sürüm`; sürüm değişince gizlenen uyarı tekrar çıkar).

Flux Donate'ın satırları `flxb.*`, Flux Forms'unkiler `flxf.*` kimlikli. Flux Donate saatlik görevin her çalışmasında `flxb_cron_heartbeat` seçeneğine zaman yazar; test sonuçları `flxb_syscheck_tests` seçeneğindedir (anahtar değil, yalnızca parmak izi saklanır).

## Bağış meta anahtarları (ayrıntı ekranında gösterilir)

| Anahtar | Yapı | Yazan |
|---|---|---|
| `iyzico` | `{paymentId, paidPrice, currency, items[{item, tx, paid, refunded}], card, at}` | iyzico sağlayıcısı |
| `source_detail` | `'flx-chat-bot'` → "Çağrı merkezi · WhatsApp asistanı", `'flx-chat'` → "Çağrı merkezi" | Flux Chat |
| `beneficiary_contacts` | `[{name, phone, email}]` (telefon/e-posta tıklanabilir) | Flux Chat |
| `receipts` | `[{url, file?, at?, user?, source?}]` (`source` `whatsapp` → "WhatsApp"; yalnızca http/https bağlantılar link olur) | Flux Chat |
| `audit` | `[{at, user, what, changes?}]`; `what`: `receipt`, `status` (`changes.status=[eski, yeni]`), `refund` (`changes.amount`), `cancel`, `beneficiaries`; `changes.via` = kaynak (`flx-chat` → "çağrı merkezi"). "Geçmiş" kutusunda yeniden eskiye listelenir; iade/iptal kendi kaydını ekler | herkes |

## Koyu mod

Filtreler `flxb_front_dark_mode` ve `flxf_front_dark_mode`: `'class'` (varsayılan; tema `body.flx-dark` + cihaz koyu), `'auto'` (cihaz ayarı; renkler `--flxb-dark-*` / `--flxf-dark-*`), `'off'`. Renkler `--flx-*` yoksa `--wp--preset--color--*` değişkenlerine düşer.

## Sağlayıcı yoksa

Ödeme kutusu "Online ödeme şu anda kullanılamıyor…" gösterir, düğme kapalı kalır; yönetim sayfalarında uyarı çıkar.

## Bilinen sınırlar

- Bir sayfada yalnızca bir Flux Forms ödeme formu çalışır (iki form JS çakışmasıyla takılır). Hub sayfaları tür sayfalarına link vermeli.
- Flux Forms'un eski `iefb/sefb/vefb` URL ön-doldurması çalışmıyor; `flxb_opt/flxb_qty` kullanın.
- Kurban modülü varsayılan kapalı (Bağışlar › Ayarlar › Kurban).

## Sistem düzeltmeleri (`flx_system_fixes`, 1.2.3)

Flux SEO'nun `system_fix` aracı için kayıtlı düzeltmeler (sözleşme: /mnt/project-files/seo-mcp/MCP-API.md). Hepsi girdiyi kendi doğrular; tür değişiklikleri `FLXB_Types::save()` üzerinden gider (form fiyat senkronu ve form çakışma kontrolü aynen çalışır).

| Kimlik | Değer | Çözdüğü satır |
|---|---|---|
| `flxb.org_name` | metin (≤190) | `flxb.cfg.org` |
| `flxb.currency` | `TRY` \| `USD` \| `EUR` \| `GBP` | `flxb.cfg.currency` |
| `flxb.type_open` | kapalı ve fiyatlı (veya serbest tutarlı) türün slug'ı (enum; yoksa düzeltme listelenmez) | `flxb.cfg.types` |
| `flxb.type_unit_price` | `slug=fiyat` (etkin her seçenek: fiyat × units_per) | `flxb.cfg.types` |
| `flxb.type_link_form` | `slug=form_id` (başka açık türe bağlı form reddedilir) | `flxb.cfg.type_forms` |

## Ödeme onayı (2.4.0)
- `flxb_payment_approved( int $donation_id, WC_Order|null $order )` (action): yönetici havaleyi onayladı.
- `flxb_payment_rejected( int $donation_id, string $reason, WC_Order|null $order, string[] $sent )` (action): reddetti; `$sent` bildirimin gittiği kanallar.
- `flxb_payment_rejected_text( $text, $donation, $reason, $order )` (filter): bağışçıya giden WhatsApp metni.
- E-posta şablonu `flx-donate/payment-rejected` (ad, tutar, aciklama, siparis).
- Bağış meta `receipts[]` (Çağrı Merkezi yazar): `url, file, at, user, source`; isteğe bağlı `mime` (önizleme türü) ve `warn` (kartta gösterilecek uyarı, ör. dekont defteri tekrarı). Reddedilen bağışta meta `rejected` = { reason, by, at }, siparişte `_flxb_reject_reason`.

## Kişi başına sertifika (2.4.1)
- Satırdaki `beneficiaries` (ya da virgülle ayrılmış `ben_name`) her isim için ayrı sertifika üretir; tek sipariş, tek ödeme. Sipariş satırı meta `_flxb_cert_ids` (dizi; `_flxb_cert_id` ilki), bağış meta `certificates` (kodlar), `certificate_code` ilki.
- `FLXB_WC_Certificates::recipients( $line, $item, $billing, $total )` → [ { name, count } ]; `remake( WC_Order )` iptal edip yeniden üretir (göndermez); `send_all( WC_Order )`.
- Çağrı Merkezi: flxb_create_order satırında `units` = adet, `beneficiaries` = isimler (en fazla adet kadar). Ayrı bağış açmaya gerek yok.

## Çöp ve silme (2.5.0)
- Yeni durum `trash` (listede varsayılan gizli; `status=trash` ile görülür). Önceki durum meta `pre_trash`.
- `FLXB_Donations::trash_rows( $ids )`, `restore_rows( $ids )`, `delete_rows( $ids )`, `ids_of_order( $order_id )`, `trash_count()`.
- Eylemler: `flxb_donation_trashed( $id, $row )`, `flxb_donation_restored( $id, $status )`, `flxb_donation_deleted( $id, $row )`. Çağrı Merkezi gibi bağış id'si tutan eklentiler `flxb_donation_deleted` ile bağlantıyı temizleyebilir.
- WooCommerce: `woocommerce_trash_order`, `woocommerce_untrash_order`, `woocommerce_delete_order` ve eski ekran için `trashed_post` / `untrashed_post` / `deleted_post` (shop_order). `FLXB_WC_Ledger::reconcile( $force )` yetim kayıtları temizler.

## Mobil uygulama: Bağışlarım / Sertifikalarım (2.7.0)
Flux App `flxapp_modules` ile iki yerel modül kaydolur: `flxb-donations` ("Bağışlarım", ikon `heart`) ve `flxb-certificates` ("Sertifikalarım", ikon `award`); `cap: read`, `menu: ''` (müşterinin wp-admin menüsü boş). Uçlar yalnız giriş yapan kullanıcının verisini döner (giriş yoksa 401):

| Uç | Cevap |
|---|---|
| `GET /wp-json/flxb/v1/me` | `{name, donations, paid, pending, total, total_label, certificates, donate_url}` |
| `GET /wp-json/flxb/v1/me/donations?page=1&per_page=20` (≤50) | `{items: [bağış], total, page, pages}`; başlık `X-WP-Total` |
| `GET /wp-json/flxb/v1/me/donations/<id>` | bağış + `certificates: [sertifika]`; başkasınınki 404 |
| `GET /wp-json/flxb/v1/me/certificates` | `{items: [sertifika]}` (iptal edilenler hariç) |

- Bağış (bir sipariş = bir bağış): `id, order_id, date (ISO), date_label, title, amount, currency, amount_label, type, type_label, status, status_label, status_tone (ok|pending|failed|muted), lines[{id,title,quantity,amount,amount_label}], certificates (adet), web_url`; havale bekliyorsa `pay_url` (dekont yükleme sayfası, web görünümünde açılır). Filtre `flxb_app_gift( $out, $rows )`.
- Sertifika: `id, code, number, name, title, date, date_label, donation_id, url, pdf_url, image_url`.
- Hangi bağışlar kullanıcının: kendi WooCommerce siparişleri + hesaba bağlı bağışçı kartları (usermeta `flxb_donor_ids`) + hesabın e-postasıyla eşleşen bağışçı kartı. Kart bağlama: `flxapp_otp_customer_created` (siparişin bağışçısı) ve telefonla kodla girişte `flxapp_otp_login` (hesapta tek telefon varsa o numaranın kartı). Fatura bilgisine yazılmış telefon tek başına yetmez. Filtre `flxb_app_donor_ids( $ids, $user )`; PHP `FLXB_App::link( $user_id, $donor_ids )`.

## Bağışlarım sayfası: kodla bağış ve sertifika görüntüleme (2.18.0)
Hesabı olmayan bağışçı için sitedeki sayfa. Kısa kod `[flx_bagislarim]` (eş ad `[flx_my_donations]`, isteğe bağlı `baslik="..."`). Bağışçı e-posta ya da telefon girer, 6 haneli tek kullanımlık kod alır, girer; bağışlarını (tutar, durum, kalemler) ve sertifikalarını (görüntüle, PDF indir) görür. Giriş yapmış kullanıcı kodsuz kendi bağışlarını görür (`FLXB_App::gifts`). Ayarlar: Bağışlar › Ayarlar › **Bağış sorgulama** (seçenek `flxb_lookup`: `on`, `page_id`, `channels` email|whatsapp|sms), "Bağışlarım sayfasını oluştur" düğmesi.

| Uç (hepsi POST, `no-store`) | Gövde → cevap |
|---|---|
| `/wp-json/flxb/v1/lookup/start` | `{login}` → `{ok, via, ttl, message}`; bağış olsa da olmasa da aynı cevap (kim bağış yaptı sızmaz), kod yalnız eşleşme varsa gider |
| `/wp-json/flxb/v1/lookup/verify` | `{login, code}` → `{ok, data}` + HttpOnly çerez `flxb_lk` (30 dk) |
| `/wp-json/flxb/v1/lookup/data` | → `{ok, data}` (çerez ya da giriş yapmış kullanıcı); oturum yoksa `{ok:false}` |
| `/wp-json/flxb/v1/lookup/logout` | çerezi ve oturumu siler |

- `data`: `who` (maskeli e-posta/telefon ya da hesap adı), `account`, `count`, `total_label` (tamamlanan bağışlar; para birimleri karışıksa boş), `items: [bağış + certificate_list: [sertifika]]` (havale bekliyorsa `pay_url` = siparişin teşekkür sayfası: IBAN + dekont yükleme), `certificates`, `donate_url`. Filtre `flxb_lookup_data( $out, $gifts )`.
- Eşleşme: bağışçı kartlarında aynı e-posta/telefon + WooCommerce siparişlerinde aynı fatura e-postası/telefonu + o e-postalı hesabın siparişleri. Filtre `flxb_lookup_match( {donors, orders}, $type, $value )`. PHP: `FLXB_App::rows_for( $donor_ids, $order_ids )`, `FLXB_App::group( $rows )`.
- Gönderim: e-posta şablonu `flx-donate/lookup-code` (Flux Mail'de düzenlenir; yoksa wp_mail, değişkenler `kod`, `dakika`, `link`); telefon önce WhatsApp (Flux Chat `flxc_send_private_text`, panelde görünmez), sonra Flux Mail WhatsApp/SMS (`log => false`). Kendi kanalın: `flxb_lookup_send( null, $type, $value, $code, $text )` → kanal adı döndür.
- Sınırlar: kod 10 dk, 5 hatalı denemede düşer; IP başına 10 dk'da 8 istek, aynı adrese 10 dk'da 3 kod, doğrulama IP başına 10 dk'da 20; günlük üst sınır e-posta 40 / telefon 200 (`flxb_lookup_daily_cap( $cap, $via )`, Hostinger günde 100 e-posta). Kod transient'te hash'li, oturum belirteci hash'li saklanır.
- Sistem kontrolü `flxb.lookup_page`: sayfa yoksa bilgi, telefon kanalı yoksa uyarı.

## Mobil uygulama: Onay bekleyenler (2.8.0)
Personelin havale/EFT dekontlarını telefondan onaylaması için. Sözleşme: docs/fluxapp/MODUL-API.md › Bağış onayı.

Modül `flxb-review` → `{title: "Onay bekleyenler", cap: edit_shop_orders (filtre flxb_app_review_capability), menu: '', api: rest_url('flxb/v1/app'), icon: heart, order: 5}`.

| Uç | Cevap |
|---|---|
| `GET /wp-json/flxb/v1/app/pending?page=&per_page=` (≤50) | `{items:[pending], total, page, pages}`, eskiden yeniye |
| `POST /wp-json/flxb/v1/app/donations/<id>/approve` | `{ok, status_label, certificates}`; artık beklemiyorsa 409 |
| `POST /wp-json/flxb/v1/app/donations/<id>/reject` `{reason, notify?=true}` | `{ok, status_label, sent:[kanallar]}`; boş gerekçe 400 |

- `pending`: `id, order_id, title, amount, amount_label, at, date_label, waiting_label ("2 gün bekliyor"), donor{name,phone,email}, ref{name}|null, lines[{title,quantity,amount_label}], receipt{url,type(image|pdf),name,at_label,warn,count}|null, note, web_url`. Filtre `flxb_app_review_item`.
- Kurallar paneldeki "Ödeme onayı bekliyor" kartıyla aynı (`FLXB_Approval::pending/can/do_approve/do_reject`): onay `payment_complete` → sertifikalar mevcut akışla gider; ret siparişi iptal eder, `notify` ise bağışçıya WhatsApp + e-posta (kara liste uyulur).
- `receipt.url`: sitede yüklenen dekont için 15 dk geçerli imzalı adres (`?flxb_dekont=&i=&exp=&sig=`, giriş gerekmez); WhatsApp'tan gelen dekont kendi adresiyle.
- Yetki: WooCommerce bağışı `edit_shop_orders`, sipariş dışı bağış yönetici yetkisi (`flxb_manage_capability`).

## Kurban hisse takibi (2.10.0, kurban modülü)
Modül kapalıysa hiçbir şey görünmez/değişmez (`module_kurban`, filtre `flxb_kurban_enabled`). Ekran: Bağışlar › Kurban hisseleri (`page=flxb-kurban`, hisse sayfası `&item=<id>`).

- Her vekâletli hisse (`flxb_items` satırı, `FLXB_Types::requires_proxy`) oluşunca 7 haneli `code` alır; eski hisselere 2.3.0 DB geçişinde verilir.
- Yeni sütunlar: `code, place_ref, place_name, approved_at, sent_at` (+ var olan `status, done_at, notified_at`). Geçmiş tablosu `flxb_item_log` (`item_id, donation_id, event, note, data JSON, user_id, created_at`).
- Durumlar: `pending` (Bekliyor) → `approved` → `sent` (Vekâlet verildi) → `done` (Kesildi) → `delivered` (Sahibine iletildi); `canceled`. Vekâlet yalnız onaylı ve **ödemesi alınmış** (`FLXB_Donations::PAID`) hisseye verilir. Gönderilmiş hisse iptal edilmez, önce vekâlet geri alınır.
- Olaylar (`event`): `created, approved, assigned (data: place, place_name, from), revoked, done, delivered, canceled, reset, video, video_del, video_sent (data: att, to), edited, note`.
- Kesim yerleri: `flxb_kurban_places` filtresi `ref => ad`. Varsayılan Ayarlar › Kurban'daki liste (`flxb_kurban_places` option). Qurban kendi kesim yerlerini bu filtreyle verir.
- Videolar: ortam kütüphanesi eki, hisse `meta.videos[{att, at, by, sent[{at,to,by}]}]`. Vekâleti verilmiş hisse ilk videoyla `done` olur; sahibine gönderilince `delivered`.
- Gönderim: Flux Chat varsayılan hattından `flxc_send( type video )`, `source = flxb-kurban`. Numara: hissenin telefonu, yoksa bağışçınınki. Metin filtresi `flxb_kurban_video_caption( $text, $item )`.
- Yetki: `flxb_can_manage()` ya da `flxb_kurban` yetkisi (Flux Roles'de seçilebilir; filtre `flxb_kurban_can`). Görüntüleme `flxb_can_view()`.
- PHP: `flxb_kurban_by_code( $code )`, `flxb_kurban_apply( $ids, approve|assign|revoke|done|delivered|cancel|reset, [place, note] )` → `{done:[], skipped:{id: neden}}`, `flxb_kurban_add_video_file( $id, $path, $filename, $note )`, `flxb_kurban_send_video( $id, $attachment_id, $phone='' )`, `flxb_kurban_log( $id, $event, $note, $data )`; liste `FLXB_Kurban::query( [status, place, paid, q, type_id, donation_id, ids, per_page, page] )`, `FLXB_Kurban::counts()`, `FLXB_Kurban::history( $id )`.
- Hook: `do_action( 'flxb_kurban_item_changed', $item_id, $event, $data )` her geçmiş satırında. Sayfa başı düğmeleri `flxb_kurban_page_actions`.
- 2.10.0 eklenti noktaları (Qurban kullanır): `flxb_kurban_assign_block( '', $item, $place_ref )` boş değilse vekâlet verilmez, metin atlama nedeni olur; liste sütunları `flxb_kurban_columns` (key => başlık) + `flxb_kurban_cell( '', $key, $item )`; hisse sayfası satırları `flxb_kurban_detail_rows( [], $item )` (başlık => HTML). `FLXB_Kurban::edit( $id, $name, $phone, $log = true )`.
- `flxb_create_order( [..., 'currency' => 'USD'] )`: sipariş (ve bağış/hisse) başka para biriminde açılır; kur çevrimi yapılmaz.

## Genel takip ve iş ortakları (2.11.0)
Kurban hisse takibi genelleşti: her bağış türü kendi kayıtlarını takip edebilir (fidan, kurban, su kuyusu...). Ana arayüzde "kurban/fidan" sözcüğü geçmez; sözcükler türden gelir. Varsayılan kapalı (`module_track`, ya da eski `module_kurban`); `flxb_track_enabled()`.

- Ekran: Bağışlar › Takip (`page=flxb-track`, kayıt `&item=<id>`); eski `page=flxb-kurban` buraya yönlenir. İş ortakları `page=flxb-partners` (`&tab=settle` mutabakat, `&edit=<id>`).
- Tür meta: `track_items` (her birimi ayrı kayıt + kod), `file_completes` (ilk dosya kaydı tamamlar), `labels` (türe özel sözcükler). `requires_proxy` takibi de açar. `FLXB_Types::tracks_items( $type )`.
- Sözcükler: `FLXB_Track::labels( $type_id )` = varsayılan → `flxb_track_labels` option (site) → tür `labels` → filtre `flxb_track_labels( $labels, $type_id )`; tek anahtar `flxb_track_label( $text, $key, $type_id )`. Anahtarlar `FLXB_Track::label_fields()`: `menu, title, item, partner, files, st_pending, st_approved, st_sent, st_done, st_delivered, st_canceled, do_assign, do_revoke, do_done, caption`. Durum kodları değişmedi.
- DB 2.4.0: `flxb_items` yeni sütunlar `partner_id, source_id, cost, cost_currency, target_date, import_key`. Tablolar `flxb_partners` (`pkey, name, group_name, is_source, is_service, type_ids, contact_name, phone, email, currency, active, sort_order, note`), `flxb_partner_prices` (`partner_id, type_id, option_key, price, currency, valid_from, active`), `flxb_partner_payments` (`partner_id, direction in|out, amount, currency, paid_at, note`).
- İş ortağı iki rolde olabilir: **hizmet** (işi yapan, ör. kesim yeri/dikim alanı; `partner_id`) ve **kaynak** (bağışı getiren; `source_id`). Yer referansı `pt:<id>`; eski `flxb_kurban_places` ve yeni `flxb_track_places( $places, $type_id )` filtreleri hâlâ eklenebilir.
- Anlaşma: `FLXB_Partners::cost( $pid, $type_id, $option_key, $date )` tarihte geçerli en özel aktif fiyatı verir (seçenek > tür geneli, en yeni `valid_from`). Atamada kayda `cost` anlık görüntüsü yazılır; geri almada silinir. `FLXB_Partners::reprice( $ids )` yeniden hesaplar. `price_state( $pid, $type_id )` → `none|on|off`; tüm anlaşmaları kapalı ortak seçimde görünmez.
- Mutabakat: `FLXB_Partners::settlement( 'service'|'source', $period, $pid = 0, $with_rows = false )` dönem (`YYYY-MM`, `YYYY`, `from..to`) için kayıt sayısı, tutar, ödemeler, bakiye; `month_stats()` son N ay. Ödeme `add_payment( [partner_id, direction, amount, currency, paid_at, note] )`. CSV indirme ekranda.
- Hedef tarih: `FLXB_Track::set_target( $id, 'Y-m-d' )`, `target_state( $it )` → `''|early|window|late|ok` (erken gün sayısı `flxb_track_early_days()`, option `flxb_track_early_days`, varsayılan 1). Listede "Şimdi yapılacaklar" süzgeci (`due`).
- Dosyalar (video yanında resim/ses/PDF/bağlantı): `add_file( $id, $att, $note )`, `add_file_path(...)`, `remove_file()`, `files( $it )`, `send_file( $id, $att, $phone )` (WhatsApp türü mime'a göre). Metin `flxb_track_file_caption( $text, $item )`.
- PHP: `flxb_track_by_code`, `flxb_track_apply( $ids, approve|assign|revoke|done|delivered|cancel|reset, [place, note, at, force_paid] )`, `flxb_track_add_file`, `flxb_track_send_file`, `flxb_track_log`; `FLXB_Track::query( [status, place, partner_id, source_id, type_id, donation_id, ids, ref_user_id, due, target, date_field, from, to, paid, q, order] )`, `counts()`, `history()`.
- Hook'lar: `flxb_track_item_changed`, `flxb_track_assign_block`, `flxb_track_can`, `flxb_track_page_actions`, `flxb_track_columns`, `flxb_track_cell`, `flxb_track_detail_rows`, `flxb_partner_saved( $id, $data )`, `flxb_partner_currencies`. Yetki `flxb_track`.
- Geriye uyum: `FLXB_Kurban`/`FLXB_Page_Kurban` sınıf takma adları, `flxb_kurban_*` fonksiyonları, filtreleri ve `flxb_kurban_item_changed` aynen çalışır.

## JSON raporlar ve ek detay sekmeleri (2.11.0)
**Raporlar** (Bağışlar › Raporlar, `page=flxb-reports`; Ayarlar › Genel › Raporlar ya da takip modülü açıkken görünür). Şema `flxb-report/1`:
```json
{ "id": "donations-by-month", "title": {"tr": "Aylara göre bağışlar", "en": "Donations by month"},
  "source": "donations", "filter": {"paid": true, "type_slugs": ["fidan"]},
  "group": ["month"], "metrics": ["count", "amount", "donors"],
  "date": "created", "period": "last_12_months", "sort": "-amount", "limit": 200, "chart": "bar", "order": 20 }
```
- `source`: `donations` | `items` (takip kayıtları; yalnız takip açıkken).
- `group` (en çok 2): donations `type, option, status, kind, payment, channel, ref_user, currency, day, month, year`; items `type, option, status, partner, partner_group, source, region, payment, ref_user, currency, day, month, year`.
- `metrics`: donations `count, quantity, donors, amount, average`; items `count, quantity, done, open, donors, amount, cost, margin`. Para metrikleri varsa satırlar para birimine göre ayrılır (kur çevrimi yok); `cost/margin` yalnız `cost_currency` kaydın para birimiyle aynıysa sayılır.
- `filter`: `paid, type_ids, type_slugs` (siteler arası taşınabilir), `status[], currency, partner_ids, source_ids, tracked` (items, varsayılan true).
- `date` (items): `created | done | target`. `period`: `this_month, last_month, last_30_days, this_year, last_year, last_12_months, all`.
- Kaynaklar (aynı id'de sonraki kazanır): `assets/reports/*.json` → filtre `flxb_reports` (id => dizi ya da .json yolu) → option `flxb_reports` (ekranda düzenlenen). "Aslına döndür" site kopyasını siler.
- PHP: `FLXB_Reports::all()`, `get($id)`, `save($json_or_array, $old_id)` → id | WP_Error, `delete($id)`, `run($def, [from, to])` → `{cols, rows, total, totals[cur], from, to, cut}`, `text($v)` (tr/en metin). Yalnız beyaz listedeki alanlar SQL'e girer.

**Ek detay sekmeleri** (Ayarlar › Ek sekmeler, JSON liste). Şema `flxb-detail-tab/1`:
```json
[{ "id": "teslim", "title": {"tr": "Teslim", "en": "Delivery"}, "on": "item", "type_slugs": ["kurban"],
   "fields": [ {"key": "kisi", "label": "Teslim alan", "type": "text"},
               {"key": "durum", "label": "Durum", "type": "select", "options": {"ok": "Sağlam", "bad": "Hasarlı"}} ] }]
```
- `on`: `item` (takip kaydı sayfası) | `donation` (bağış sayfası). `type_ids/type_slugs` boşsa her tür. Alan türleri `text, textarea, number, date, select, checkbox, url, phone, email`.
- Değerler satırın `meta.ext.<sekme>.<alan>`'ında. Kayıtta değişiklik geçmişe `edited` olarak düşer. Hook `flxb_detail_tab_saved( $on, $id, $tab, $values, $old )`.
- Eklentiler filtre `flxb_detail_tabs` (id => dizi ya da .json yolu) ile sekme ekler; site ayarı aynı id'yi ezer.
- PHP: `FLXB_Detail_Tabs::all()`, `for_row($on, $type_id)`, `values($on, $row, $tab)`, `save_values($on, $id, $tab, $in)`, `save_json($json)`.
- Yetki: kayıt sekmesi `flxb_track`, bağış sekmesi `flxb_can_manage()`; diğerleri salt okur.

## Excel / CSV içe aktarma (2.11.0)
Bağışlar › Excel'den yükle (`page=flxb-import`; 2.26.0'dan beri her sitede görünür, filtre `flxb_import_enabled`). Qurban'daki kurban yüklemesinin genel hali.
- Akış: dosya (`.xlsx`, `.csv`) → başlık satırı ilk 10 satırdan bulunur, sütunlar tanınır (`FLXB_Import::fields()`, filtre `flxb_import_fields`: alan => [etiket, katlanmış takma adlar]) → tekrar eden değerler bir kez eşlenir (tür/seçenek, iş ortağı, getiren, durum, referans, ödeme; iş ortağı yoksa "Yeni olarak ekle") → önizleme (hazır / uyarılı / zaten yüklü / hata) → AJAX ile 12'şer satır (`wp_ajax_flxb_import_step`).
- Her satır bir `flxb_create_order()` siparişi: ödendi → `completed` (`payment_method flxb-import`), değilse `on-hold` havale. Takipli türde birimler kayıt olur; durum yolu `approve → assign (pt:<id>) → done → delivered`, ödenmemiş ya da ortaksız satır `approved` açılır. Hedef tarih, getiren (`source_id`), adına telefon yazılır.
- `flxb_create_order( [..., 'created_at' => 'Y-m-d H:i:s'] )` (2.11.0): sipariş ve bağış satırı eski tarihle açılır.
- Tekrar yok: `items.import_key` = eşlenen hücrelerin özeti (birden çok birimde `#n` ekli).
- 2.26.0 tarihsiz satır: `opts.nodate` = `above` (üstteki satır) | `target` (hedef tarih) | `day` (`opts.nodate_day`) | `today`.
- 2.26.0 sessiz aktarım: adım sürerken `pre_wp_mail` false döner ve `FLXB_Import::$running` true. Ortak filtre **`flx_silent`** ( bool $silent, mixed $context ): aktarım sürerken ya da `$context` içe aktarılmış bir sipariş (`created_via = flxb-import`), onun bağış id'si veya bağış satırıysa true. WhatsApp / e-posta / reklam / analitik gönderen her paket önce sorar: `if ( apply_filters( 'flx_silent', false, $order ) ) return;` (Flux Chat referans + sertifika bildirimi, Flux Commerce yönetici bildirimi, Flux Ads dönüşümü, Flux Analytics bağış olayı, Flux Donate `flxb_ref_notify`).
- Yükleme `flxb_import_<id>` option'ında (son 10). PHP: `FLXB_Import::from_file($path, $name)`, `analyze($imp)`, `prepare($imp)`, `step($id)`, `write_row($imp, $row)`. Hook'lar: `flxb_import_order_args( $args, $row, $imp )`, `flxb_import_row_done( $order, $items, $row, $imp )`, `flxb_import_status_words` (durum metni → durum).

## Ön ayar içerik türleri (2.11.0)
Flux Setup için `flxb_type`, `flxb_partner`, `flxb_report`, `flxb_detail_tab` (`flx_preset_importers`) ve dışa aktarım (`flx_preset_export_content`, `flx_preset_export_options`). Şemalar: [docs/flx-donate/ON-AYAR.md](../../docs/flx-donate/ON-AYAR.md). Sınıf `FLXB_Preset`.

## Flux CRM kişi kartı (2.12.0)
Bağışçı sayfası (`flxb-donors&donor=ID`) Flux CRM varsa `flxcrm_person_card()` ile ortak kişi kartını basar (bağlam `donate`, bağlantı `donate`/`donor:ID`); CRM yoksa ya da kullanıcının `flxcrm_view` yetkisi yoksa klasik kart. Referans kullanıcısının kendi bağışçıları ("Bağışçılarım") hep klasik kartta.
- Karta eklenenler: `flxcrm_person_card_actions` (Bağışlar, Bağış ekle), `flxcrm_person_card_main_before` (düzenli bağışlar, varsa), `flxcrm_person_card_side` (bağış özeti, sertifikalar, KVKK + bağış notu).
- `flxb_use_crm_card` (bool, `$donor`): false → hep klasik kart.
- `flxb-add&donor=ID`: bağış ekleme formunda bağışçı bilgileri hazır gelir.
