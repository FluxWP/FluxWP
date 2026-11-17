# Flux Forms GraphQL (dilim 1)

Herkese açık okuma ve gönderim. Yönetici ekranları sonraki dilimlerde. Oluştur (fork form oluşturucu) klasik kalır.

Flux GraphQL yoksa bu kayıtlar yüklenmez; REST aynı çalışır.

## Sorgular ve mutation'lar

Hepsi `public`. Gerekçe: shortcode aynı çizim alanlarını zaten herkese basar; gönderim, takip ve yanıt bugün anonim REST çağrısıdır. Takip kodu sırdır.

```graphql
query FlxForm($id: ID!) {
  flxForm(id: $id) {
    id
    title
    formType
    buttonLabel
    fields { id type label required placeholder options { value label } }
  }
}
```

Gizli form (`stateForm` true veya `1`) anonim ziyaretçiye `null` döner. Kayıtlı kullanıcı görür. Form yoksa da `null`.

```graphql
mutation FlxFormSubmit($input: FlxFormSubmitInput!) {
  flxFormSubmit(input: $input) {
    submission { id trackCode }
    errors { code message field }
  }
}
```

Yalnız `form`, `survey`, `subscribe`. `login`, `register`, `logout`, parola kurtarma ve ödemenin son adımı REST'te kalır (`INVALID_INPUT`, alan `formId`).

`honeypot` doluysa kayıt yazılmaz (`INVALID_INPUT`, alan `honeypot`). Metin tuzağı ele vermez.

Captcha: `flx_captcha_gql_verify( 'flxf_submit' )`. Bağlam `flx_captcha_contexts` ile kayıtlıdır, panelde kapalı başlar (`default` 0). Açıkken token `captchaToken` veya `X-Flx-Captcha` başlığıdır. Fork'un kendi Google `siteverify` çağrısı REST'te durur.

Hız sınırı: `flxFormSubmit` ve `flxFormTrackReply` IP başına `flx_rate_limit` (`flxf_submit`, `flxf_track_reply`) uygular; `flxFormTrack` klasik takip sınırlarını (`efb_track_lookup_allowed` ve kısa/eski biçimli kodlar için `efb_track_legacy_lookup_allowed`) kullanır. Aşan istek `RATE_LIMITED` döner.

```graphql
query FlxFormTrack($code: String!) {
  flxFormTrack(code: $code) {
    trackCode
    formTitle
    submittedAt { iso timestamp }
    fields { label value }
    replies { body createdAt { iso timestamp } attachments }
  }
}
```

Kod yoksa `NOT_FOUND`. `submittedAt` ve `createdAt`, `FlxDateTime!` döner (site yerel MySQL saati `get_gmt_from_date()` ile UTC'ye çevrilir). Klasik `get_track_public_api` captcha doğrulamaz (`$not_captcha = true` sabit). Bu sorgu token almaz. Takip sayfası `scaptcha` açıksa widget basar; API yine bakmaz.

```graphql
mutation FlxFormTrackReply($input: FlxFormTrackReplyInput!) {
  flxFormTrackReply(input: $input) {
    submission { id trackCode }
    errors { code message field }
  }
}
```

`adminCode` klasik `sc`: `md5(track + email_key)`, anahtar 3 karakterden uzunsa. Boş kod ziyaretçi yanıtıdır. Yanlış kod `INVALID_INPUT` (alan `adminCode`); yetki yükselmez. `email_key` dönmez.

## Allowlist ve önbellek

`flxForm` ham `form_structer` dönmez. Alan: `id`, `type`, `label`, `required`, `placeholder`, seçenekte `value` ve `label`. `FlxFormField.type` bilerek `String!` tutulur; oluşturucu ve eklentiler alan türünü açıkça genişletir. `hidden` ve `disabled` alanlar ile `step`, `option` ve `r_matrix` satırları `fields` listesine sızmaz; üst düzey `type: 'option'` (`parent: <alan id>`) satırları kendi seçim alanının `options` listesine bağlanır (eski satır içi `options`/`choices` biçimi de okunur).

`flxForm` yanıtı `flx-forms:form:{id}` etiketiyle önbelleğe alınır; klasik form kaydetme ve silme yolları (`update_form_cache_efb`, `clear_form_cache_efb`, blok editörü `FLXF_Blocks::flush`) bu etiketi `FLXF_Form_Service::purge()` üzerinden düşürür.

Sunucuda kalan başlık anahtarları: `email`, `email_to`, `sendEmail`, `email_temp`, `notification_rules`, `webhook_rules`, `confirmation_rules`, `rePage`, SMS (`smsnoti`, `sms_msg_*`, `sms_admins_phone_no`), Telegram (`telegramnoti`, `telegram_msg_*`, `telegram_bot_token`, `telegram_admin_chat_ids`), `logic_rules`. Seçenekten `registered_count`, `mlen`, `dateExp` kopyalanmaz.

## Yükleme

Yükleme ucu REST'te kalır (`POST /Emsfb/v1/forms/file/upload`).

`flxFormSubmit` bir dosya adresini ancak şu koşullar sağlanırsa kabul eder: adres `wp_upload_dir()` altındadır, basename `emsfb_pending_uploads` içindedir (`Upload_Guard::PENDING_OPTION`) ve klasik `validate_submitted_upload_url_efb` (alanın izin verdiği uzantılar, boyut sınırı ve kaydedici kuralları) geçer. Defter `basename => zaman` tutar, form id tutmaz. Dosya satırı klasik `{ id_, name, type, value: '@file@', url }` biçiminde ve satır sonu `w_link` işaretiyle yazılır; okuma `@file@` yerine `url` değerini döndürür ve `w_link`/`closed`/`opened` işaretlerini atlar.

`flxFormTrackReply` ekleri yalnız `Upload_Guard::response_attachment_is_reserved` (bu `msg_id` + takip kodu) ile kabul eder ve `emsfb_rsp_` satırına klasik kaçışlı JSON dizisi (`id_: 'message'` + `id_: 'file', type: 'allformat', value: '@file@', url`) yazar.

## Paylaşılan yan etkiler

`get_form_public_efb` ve `set_rMessage_id_Emsfb_api` JSON basıp çıkar. GraphQL servisi aynı `emsfb_msg_` / `emsfb_rsp_` satırlarını yazıp `_Public::flxf_dispatch_submit_effects()` ve `flxf_dispatch_reply_effects()` ortak yöntemlerini çağırır; REST baytları değişmeden kalır.

Bu ortak yol sayesinde GraphQL gönderimi ve yanıtı klasik yolla aynı post-save etkilerini çalıştırır: bekleyen yükleme/ek rezervasyonlarını düşer, `flxf_submit_response` filtresini uygular, anket önbelleğini (`emsfb_survey_<id>`) temizler, bildirim ve otomatik yanıt e-postalarını (`send_email_Emsfb_`), koşullu bildirim/webhook kurallarını, SMS gönderimini ve `efb_after_form_integration` / Human Shield (`efb_shield_allow_side_effect`) kapılarını çalıştırır.

## Hatalar

`INVALID_INPUT` payload `errors` listesindedir. `NOT_FOUND`, `RATE_LIMITED` ve `FORBIDDEN` üst seviye `extensions.code`. Metinler İngilizce kaynak, Türkçe `languages/flx-forms-tr_TR.l10n.php`.

# Panel: yanıtlar ve ayarlar (dilim 2)

Hepsi `flx_gql_any_cap( 'Emsfb', 'manage_options' )`. Klasik panel AJAX işleyicileri, FluxApp REST yolları (`flx-forms/v1`) ve GraphQL aynı servisi çağırır: `FLXF_Response_Service` (liste sorgusu, okundu, silme, yönetici yanıtı) ve `FLXF_Settings_Service` (ayarları doğrulama ve kaydetme). Klasik çıktılar bayt bayt aynı kaldı.

```graphql
query FlxFormResponses($formId: ID, $unread: Boolean, $status: FlxFormResponseStatus, $search: String, $first: Int, $after: String) {
  flxFormResponses(formId: $formId, unread: $unread, status: $status, search: $search, first: $first, after: $after) {
    nodes { id formId formTitle trackCode status createdAt { iso label } preview fields { label value isFile } }
    pageInfo { hasNextPage endCursor }
    totalCount
  }
}
```

- Ödenmemiş ödeme taslakları (`read_ = 2`) hiçbir sorguda yoktur; `flxFormResponse` için `NOT_FOUND`.
- Okundu: `0 → 1`, `3 → 4` (FluxApp ile aynı); yanıtlar da okundu olur. Toplu işlemler en çok 200 id.
- Silme yanıtın cevaplarını da siler (klasik toplu silme gibi).
- `flxFormResponseReply`: `state` `REPLY`, `CLOSE`, `OPEN`. Metin düz yazıdır, HTML reddedilir. Ek yalnız yükleme ucunun (`POST /Emsfb/v1/forms/file/upload`, `pl=resp`) bu yanıt için ayırdığı dosyadır; klasik `update_file_Emsfb` nonce-only açığı miras alınmadı (cap şart). Kayıt klasik biçimdedir (`emsfb_rsp_.content` kaçışlı JSON), ziyaretçiye giden e-posta ve rezervasyon bırakma klasik yolla aynıdır ve bir kez çalışır.
- `flxFormsExportUrl`: CSV baytı pano dışa aktarımında (`admin_post_flxf_dash_csv`) kalır; bu alan nonce'lu bağlantıyı verir.
- Nesne düzeyi görünürlük: Flux Forms'ta forma veya yanıta bağlı "yalnız kendi" kapsamı yok (`form_access_by` kullanılmıyor); `Emsfb` sahibi tüm yanıtları görür. Listeler, tekil sorgu, arama ve dışa aktarım aynı kuralla.

```graphql
mutation FlxFormsSettingsUpdate($input: FlxFormsSettingsUpdateInput!) {
  flxFormsSettingsUpdate(input: $input) { settings { sessionDuration trackCodeStyle stripeSecretSaved } errors { code message field } }
}
```

Ayarlar: genel, yanıt kutusu (oturum, takip kodu biçimi, renkler), captcha, e-posta, e-posta şablonu, ödemeler (sağlayıcı, anahtarlar), SMS. Gizli alanlar (`secretKey`, `mapKey`, `stripeSecretKey`, `paypalSecretKey`, `payToken`, `smsPhoneNumbers`) yalnız yazılır; okuma `...Saved` bayrağı döner. Verilmeyen alan değişmez (`set_setting_Emsfb` yok sayılanı korur). Şablon doğrulaması ve temizliği klasik panelle aynı kod.

Bu dilimde yok (klasik panelde kalır): canlı e-posta sunucusu testi (`check_email_server_efb`), ödeme sağlayıcısına özel dinamik alanlar (Ödemeler ekranı, dilim 4), yerelleştirme sözlüğü (`text`), onboarding ve lisans/plan, Eklentiler.
