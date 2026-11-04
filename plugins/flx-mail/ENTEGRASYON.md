# Flux Mail – diğer paketler için entegrasyon notu

Flux Mail 1.1.0 (plugins/flx-mail) sitenin bütün e-posta, WhatsApp ve SMS gönderimini üstlenir.
Diğer paketler **değiştirilmeden de** çalışır: `wp_mail()` çağrıları yakalanır, gönderen düzeltilir,
düz metin e-postalar kurumsal şablona sarılır ve listeye eklenir. Aşağıdakiler daha iyi entegrasyon
için önerilen, sahibi olan thread'in yapacağı değişikliklerdir. API: `API.md`.

Hepsinde kural: `function_exists( 'flxi_send_email' )` / `flxi_send_message` yoksa mevcut davranış sürsün.

## Flux Certificates (sertifika sistemi thread'i)

Şu an köprü Flux Mail tarafında, Flux Certificates'da değişiklik gerekmeden çalışıyor:
- `flxs_provider_types` (öncelik 20): `wpmail`/`smtp` tiplerinin `send`'i Flux Mail'e devredilir
  (şablon `flx-certificates/certificate`, sertifika görseli + bilgiler + düğme). Yeni tipler `flxi_whatsapp`, `flxi_sms`.
- Flux Certificates 1.3.0 kendi Evolution/Netgsm kodunu kaldırdı; `flxi_*` köprü satırlarını `flxs_sync_flxi_providers()` ile kendisi tutar.
  Flux Mail 1.1.0'dan itibaren taşıma/geri alma yok.

Önerilen (sertifika thread'i):
1. Flux Mail etkinse Sertifikalar › Sağlayıcılar ekranında "WhatsApp/SMS/e-posta ayarları İletişim › Bağlantılar'da" notu ve link göster; ekranı gizlemek de olur.
   Kontrol: `function_exists( 'flxi_send_email' )`.
2. Sistem kontrolündeki `flxs_provider_email` / `flxs_provider_whatsapp` / `flxs_provider_sms` satırları Flux Mail etkinken
   `flxi_list_connections( $ch, true )`'ya bakıp "Flux Mail üzerinden" demeli (şu an "No active provider" uyarısı verebilir).
3. Sunucuda üretilen sertifikalar için görsel: Flux Mail önce `flxs_certificate_file_path( $cert, 'image'|'og' )` dosyasını,
   yoksa GD önizlemesini kullanır. Görsel dosyası gönderimden önce hazır olursa e-postada gerçek sertifika (QR'lı) görünür.

## Flux Chat (WhatsApp çağrı merkezi thread'i)

Flux Certificates 1.3.0 Evolution satırlarını artık tutmuyor; bağlantı Flux Mail'den okunmalı.
Önerilen: bağlantıyı `flxi_get_channel_connection( 'whatsapp', $id )` ile al (config aynı alan adlarıyla: `base_url`, `api_key`, `instance`, `version`),
ayarlarda kaynak seçimi `flxi_connection_choices( 'whatsapp' )`. Böylece anahtar tek yerde güncellenir.

## Flux Donate, Flux Forms, Flux WooCommerce Bağış, Flux Tasks

Kendi e-postalarını `flxi_email_types` ile kaydedip `flxi_send_email( array( 'template' => '<slug>/<anahtar>', 'vars' => … ) )` ile gönderin.
Kayıtlı şablonlar İletişim › E-postalar'da düzenlenebilir/kapatılabilir hale gelir ve aynı kurumsal tasarımı kullanır.
WhatsApp bildirimleri (ör. WC Bağış mini bildirimi) `flxi_send_message( 'whatsapp', $tel, $metin, array( 'source' => '<slug>' ) )`.

## Flux AI & MCP

Değişiklik gerekmez: `flxseo_mcp_tools` ve `flx_system_fixes` filtreleriyle 6 MCP aracı ve 9 düzeltilebilir ayar ekleniyor (şifre yok).

## Tema

Değişiklik gerekmez. Görünüm › Sistem Kontrolü `flxi.*` satırlarını ortak sözleşmeyle gösterir.
