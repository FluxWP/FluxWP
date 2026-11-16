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

Hız sınırı `flx_rate_limit` (`flxf_submit`, `flxf_track`, `flxf_track_reply`), IP başına. Aşan istek `RATE_LIMITED`.

```graphql
query FlxFormTrack($code: String!) {
  flxFormTrack(code: $code) {
    trackCode
    formTitle
    submittedAt
    fields { label value }
    replies { body createdAt }
  }
}
```

Kod yoksa `NOT_FOUND`. Klasik `get_track_public_api` captcha doğrulamaz (`$not_captcha = true` sabit). Bu sorgu token almaz. Takip sayfası `scaptcha` açıksa widget basar; API yine bakmaz.

```graphql
mutation FlxFormTrackReply($input: FlxFormTrackReplyInput!) {
  flxFormTrackReply(input: $input) {
    submission { id trackCode }
    errors { code message field }
  }
}
```

`adminCode` klasik `sc`: `md5(track + email_key)`, anahtar 3 karakterden uzunsa. Boş kod ziyaretçi yanıtıdır. Yanlış kod `INVALID_INPUT` (alan `adminCode`); yetki yükselmez. `email_key` dönmez.

## Allowlist

`flxForm` ham `form_structer` dönmez. Alan: `id`, `type`, `label`, `required`, `placeholder`, seçenekte `value` ve `label`. `hidden` ve `disabled` alanlar listede yoktur.

Sunucuda kalan başlık anahtarları: `email`, `email_to`, `sendEmail`, `email_temp`, `notification_rules`, `webhook_rules`, `confirmation_rules`, `rePage`, SMS (`smsnoti`, `sms_msg_*`, `sms_admins_phone_no`), Telegram (`telegramnoti`, `telegram_msg_*`, `telegram_bot_token`, `telegram_admin_chat_ids`), `logic_rules`. Seçenekten `registered_count`, `mlen`, `dateExp` kopyalanmaz.

## Yükleme

Yükleme ucu REST'te kalır (`POST /Emsfb/v1/forms/file/upload`).

`flxFormSubmit` bir dosya adresini ancak şu üçü birden sağlanırsa kabul eder: adres `wp_upload_dir()` altındadır, basename `emsfb_pending_uploads` içindedir (`Upload_Guard::PENDING_OPTION`), `Upload_Guard::validate_stored_file` tür ve boyutu geçer. Defter `basename => zaman` tutar, form id tutmaz. Rastgele bir uploads adresi reddedilir. Bekleyen başka bir formun dosyası hâlâ yapışabilir; klasik veri bunu ayırt etmez. Bu PR klasik `validate_submitted_upload_url_efb` yolunu değiştirmez (o yol deftere bakmaz).

`flxFormTrackReply` ekleri yalnız `Upload_Guard::response_attachment_is_reserved` (bu `msg_id` + takip kodu) ile kabul eder.

## REST ile ortak olmayan yan etkiler

`get_form_public_efb` JSON basıp çıkar. Servis onu çağırmaz; REST baytları aynı kalır.

GraphQL gönderimi `emsfb_msg_` satırını yazar, bekleyen dosyayı defterden düşer ve `flxf_submit_response` ile `efb_after_form_integration` kancalarını çalıştırır (otomasyonlar). Klasik yöntemin içindeki bildirim e-postası, webhook kuralları ve koşullu yönlendirme bu mutation'da tekrarlanmaz.

## Hatalar

`INVALID_INPUT` payload `errors` listesindedir. `NOT_FOUND`, `RATE_LIMITED` ve `FORBIDDEN` üst seviye `extensions.code`. Metinler İngilizce kaynak, Türkçe `languages/flx-forms-tr_TR.l10n.php`.
