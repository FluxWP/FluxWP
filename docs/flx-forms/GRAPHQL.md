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

Captcha: `flx_captcha_gql_verify( 'flxf_submit' )`. Bağlam `flx_captcha_contexts` ile kayıtlıdır, panelde kapalı başlar (`default` 0). Açıkken token `captchaToken` veya `X-Flx-Captcha` başlığıdır. Formun kendi captcha'sı ayrıca klasik kapıdan geçer: formun başlığında `captcha` açıksa (ödeme hariç) `captchaToken` zorunludur ve `settings->secretKey` ile Google `siteverify` doğrulanır; token yoksa, geçersizse `INVALID_INPUT` (alan `captchaToken`), gizli anahtar boşsa `INTERNAL` döner ve hiçbir şey yazılmaz. Aynı token iki kez doğrulanamaz; hem `flxf_submit` bağlamı hem form captcha'sı açıksa Google ikinci doğrulamayı reddeder, bu yüzden biri açık tutulur.

## Gönderim klasik hattan geçer

`FLXF_Submission_Service::submit` ziyaretçi cevaplarını form istemcisinin gönderdiği satırlara (`id_`, `name`, `type`, `value`, `id_ob`, `url`) çevirir ve klasik gönderimle aynı `_Public` yöntemlerini çalıştırır; REST de aynı yöntemleri kullanır (çıktı baytları değişmez):

- `flxf_apply_conditional_logic` (`efb_logic_prepare_submission`: gizli, isteğe bağlı, zorunlu alanlar, `block_submit` / `end_form`),
- `flxf_check_required` (`validate_required_fields_present_efb`), `flxf_strip_untouched_rows`, `flxf_dedupe_rows`,
- `flxf_validate_fields` (alan başına e-posta, tarih, URL, mobil, seçim, dosya, sayı, uzunluk, rezervasyon kotası kuralları),
- `flxf_submit_gates` (form captcha'sı ve Human Shield `efb_submit_bot_decision`; veto `FORBIDDEN`),
- `flxf_encode_items` ve `insert_message_db()` (`trackCodeStyle` dahil, `claim_uploaded_files_efb`),
- `flxf_head_recipients`, `flxf_admin_recipients`, `flxf_user_recipient` ve `flxf_dispatch_submit_effects`.

Görünmeyen (gizli, devre dışı) ve ekranda çizilmeyen alanlar zorunlu sayılmaz; GraphQL bu alanlar için satır üretmez. Boş gönderimde eksik zorunlu alanların hepsi `fields.<id>` ile listelenir (REST ilkini döner). Klasik `femail` gönderen adresi gönderimde atanmaz: klasik kod `$setttting` yazım hatası yüzünden bunu hiç çalıştırmaz ve GraphQL aynı davranır.

Hız sınırı: `flxFormSubmit` ve `flxFormTrackReply` IP başına `flx_rate_limit` (`flxf_submit`, `flxf_track_reply`) uygular; `flxFormTrack` klasik takip sınırlarını (`efb_track_lookup_allowed` ve kısa/eski biçimli kodlar için `efb_track_legacy_lookup_allowed`) kullanır. `flxFormTrackReply` de aynı kapıları ve başarısız arama sayacını (`efb_track_register_failure`, eski kodlar için `efb_track_register_legacy_failure`) kullanır; klasik yanıt hem `msg_id` hem takip kodu ister, GraphQL yalnız kodu alır ve `msg_id`'yi o kodun satırından okur. Aşan istek `RATE_LIMITED` döner.

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
