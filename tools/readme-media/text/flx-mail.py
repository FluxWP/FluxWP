SLUG='flx-mail'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-mail'; WP='6.2'; PHP='7.4'; DOCS='iletisim'; API=True
MCP_EN='''Flux Contact adds tools to the site's MCP server (**Flux SEO**) and to the **Flux AI** chat, so an assistant can check and fix e-mail without the panel:

- `flxi_email_log`, `flxi_templates`, `flxi_update_template`, `flxi_settings`, `flxi_send_test`, `flxi_mail_quota`, `flxi_campaigns`
- Mailbox (Flux AI): `flxi_mail_list`, `flxi_mail_read`, `flxi_mail_layouts`, `flxi_mail_draft`, and `flxi_mail_send_draft` (asks for approval)

> "Why didn't yesterday's certificate e-mails go out?" · "Write a reply to the school campaign e-mail and leave it for my approval."

Passwords and API keys are never readable or writable through any tool.'''
MCP_TR='''Flux Mail sitenin MCP sunucusuna (**Flux SEO**) ve **Flux AI** sohbetine araçlar ekler; asistan e-postayı panele girmeden kontrol eder ve düzeltir:

- `flxi_email_log`, `flxi_templates`, `flxi_update_template`, `flxi_settings`, `flxi_send_test`, `flxi_mail_quota`, `flxi_campaigns`
- Posta kutusu (Flux AI): `flxi_mail_list`, `flxi_mail_read`, `flxi_mail_layouts`, `flxi_mail_draft` ve `flxi_mail_send_draft` (onay ister)

> "Dünkü sertifika e-postaları neden gitmedi?" · "Okul kampanyası e-postasına bir cevap yaz, onayıma bırak."

Şifreler ve API anahtarları hiçbir araçla okunmaz ya da yazılmaz.'''
C={
'en':dict(name='Flux Contact',
 tagline="Every e-mail the site sends, its mailbox, and its WhatsApp and SMS lines in one place.",
 desc='Flux Contact replaces "WordPress <wordpress@…>" with your organisation: a sender name and address, SMTP, a branded mobile-friendly template for every WordPress, WooCommerce and FluxWP e-mail, a log of everything sent, single and bulk sending, and a mailbox for bilgi@ with AI replies. WhatsApp (Evolution API, Flux WPAPI, Cloud API) and SMS (Netgsm) connections live here too, and other plugins send through them.',
 badges=['![Channels](https://img.shields.io/badge/channels-e--mail%20%C2%B7%20WhatsApp%20%C2%B7%20SMS-2fbf8f)','![MCP](https://img.shields.io/badge/AI-MCP%20tools-7d64ff)'],
 gifalt='Flux Contact tour: the e-mail list with on/off switches, the mailbox with an open message, the send log, WhatsApp and SMS connections and the send screen with a live preview',
 why='''Out of the box, WordPress sends plain text from `wordpress@your-host`, every plugin has its own look, nothing is logged, and replies go nowhere. A charity notices this the day a donor says "your certificate went to spam". Flux Contact fixes the whole path:

- ✉️ **One sender, one look.** bilgi@ for e-mails that expect a reply, no-reply@ for passwords and notices, one branded template for WordPress, WooCommerce and every FluxWP package.
- 🗂️ **Every e-mail in a list.** Turn any of them on or off, edit the subject, heading, text and button, preview on desktop, phone and dark mode, send yourself a test.
- 📬 **A real mailbox.** bilgi@ inside the admin over IMAP: read, reply, forward, drafts, approval for AI-written replies.
- 💬 **WhatsApp and SMS for everyone.** Connect numbers once; Flux Certificate, Flux Call Center and the rest use them through one API.
- 🧾 **Proof.** Every e-mail, WhatsApp and SMS message is logged with its content, status and error.''',
 features='''### E-mail
- **Sender:** organisation name, bilgi@ and no-reply@ addresses, reply-to; other plugins' sender is changed too (for SPF/DKIM).
- **Sending method:** SMTP with a "Fill in Hostinger settings" button, a separate no-reply@ account, passwords stored encrypted or in `wp-config.php`; falls back to bilgi@ if no-reply@ fails.
- **E-mails:** every e-mail of WordPress, WooCommerce and FluxWP packages, grouped, with on/off, editor, sender choice, desktop/phone/dark preview and "Send me a test". Unknown e-mails join the list the first time they are sent.
- **Appearance:** logo for light and dark backgrounds, colours, tagline, address, social links, a tree silhouette footer; images (WebP too) are embedded so every mail program shows them; "Apply appearance to WooCommerce"; PDF download of any preview.
- **DNS check** for SPF, DKIM and DMARC; Google sign-up users don't get "set your password" mails.

### Mailbox, sending and log
- **Mailbox:** bilgi@ over IMAP with unread and system-mail filters, starred, attachments, reply/reply all/forward, drafts, layouts and **Write reply with AI** (lands in *Awaiting approval*).
- **Send:** e-mail, WhatsApp or SMS to one person or a list (members by role, Flux Certificate contacts), live preview, background queue that respects Hostinger's daily and per-message limits.
- **Log:** every message with time, recipient, subject, source plugin, channel and status; click for the content. Block list from Flux Call Center is honoured.

### WhatsApp and SMS
- **Connections:** WhatsApp via Evolution API, Flux WPAPI or Cloud API (Meta), SMS via Netgsm; several numbers, a default per channel, QR or pairing-code connect, status, restart, log out.
- **Test** a connection with text, image, voice message, document or (Cloud API) a template.''',
 shots=[('emails','E-mails with on/off and editor'),('mailbox','Mailbox with an open e-mail'),('send','Send with live preview'),('connections','WhatsApp and SMS connections'),('logs','Send log'),('settings','Settings: sender and SMTP'),('dark','Dark mode with Flux Admin'),(['mobile','mobile-log'],'Mobile: mailbox and log')],
 req='An e-mail account for bilgi@ (and optionally no-reply@). Optional: an Evolution API 2.3+ or Flux WPAPI server for WhatsApp, a Netgsm account for SMS, Flux AI for AI replies, Flux Call Center for the block list.',
 first='''1. Activate Flux Contact: WordPress e-mails switch to bilgi@ / no-reply@ and the branded template right away.
2. **Communication › Settings › Sending method › SMTP › Fill in Hostinger settings**, enter the password, save and **Send test email**.
3. **Settings › Test › Check DNS** and add any missing SPF, DKIM or DMARC record.
4. **Settings › Mailbox**: turn on the mailbox for bilgi@ (IMAP fills in from SMTP).
5. **Communication › Connections**: add a WhatsApp number (QR) and Netgsm for SMS.''',
 note='> ⚠️ Hostinger allows 100 e-mails a day and 100 recipients per message; bulk sends are queued to stay inside that. Run only one SMTP plugin: System Check warns if WP Mail SMTP or LiteSpeed SMTP is also active.',
 mcp=MCP_EN,
 dev='''- Templates: `flxi_email_types` filter; send with `flxi_send_email( $key, $to, $vars )`.
- WhatsApp / SMS: `flxi_send_message()`; new providers with `flxi_connection_types`.
- Mailbox: `flxi_mailbox_query()`, `flxi_mailbox_new_draft()` (also `POST /wp-json/flxi/v1/mailbox/new-draft`).
- Block list: `flx_contact_blocked`; quota: `flxi_quota_left()`.''',
 roadmap=[(1,'Branded template and editor for every e-mail'),(1,'SMTP, DNS check and send log'),(1,'WhatsApp and SMS connections shared by all FluxWP packages'),(1,'Mailbox with AI replies and approval'),(1,'Images, voice and documents on WhatsApp'),(0,'Open and click statistics for bulk sends'),(0,'Shared inbox with assignment between staff')]),
'tr':dict(name='Flux Mail',
 tagline='Sitenin gönderdiği her e-posta, posta kutusu, WhatsApp ve SMS hatları tek yerde.',
 desc='Flux Mail "WordPress <wordpress@…>" yerine kurumunuzu koyar: gönderen adı ve adresi, SMTP, WordPress, WooCommerce ve FluxWP e-postaları için logolu ve mobil uyumlu tek şablon, tüm gönderimlerin kaydı, tekil ve toplu gönderim, AI cevaplı bilgi@ posta kutusu. WhatsApp (Evolution API, Flux WPAPI, Cloud API) ve SMS (Netgsm) bağlantıları da burada; diğer eklentiler bunlar üzerinden gönderir.',
 badges=['![Kanallar](https://img.shields.io/badge/kanal-e--posta%20%C2%B7%20WhatsApp%20%C2%B7%20SMS-2fbf8f)','![MCP](https://img.shields.io/badge/yapay%20zekâ-MCP%20araçları-7d64ff)'],
 gifalt='Flux Mail turu: aç/kapa anahtarlı e-posta listesi, açık bir iletiyle posta kutusu, gönderim kayıtları, WhatsApp ve SMS bağlantıları, canlı önizlemeli gönderim ekranı',
 why='''WordPress kendi hâlinde `wordpress@sunucu` adresinden düz metin gönderir; her eklentinin görünüşü ayrıdır, hiçbir şey kayda geçmez, cevaplar boşa gider. Bir dernek bunu, bağışçı "sertifikanız spama düştü" dediği gün fark eder. Flux Mail tüm yolu düzeltir:

- ✉️ **Tek gönderen, tek görünüş.** Cevap beklenen e-postalar bilgi@'den, parola ve bildirimler no-reply@'den; WordPress, WooCommerce ve tüm FluxWP paketleri için tek kurumsal şablon.
- 🗂️ **Her e-posta bir listede.** İstediğinizi açıp kapatın; konu, başlık, metin ve düğmeyi değiştirin; masaüstü, telefon ve koyu modda önizleyin, kendinize test gönderin.
- 📬 **Gerçek bir posta kutusu.** IMAP ile bilgi@ panelin içinde: okuma, cevaplama, iletme, taslaklar, AI'ın yazdığı cevaplar için onay.
- 💬 **Herkes için WhatsApp ve SMS.** Numaraları bir kez bağlayın; Flux Certificates, Flux Chat ve diğerleri tek API ile kullanır.
- 🧾 **Kanıt.** Her e-posta, WhatsApp ve SMS mesajı içeriği, durumu ve hatasıyla kayıtlı.''',
 features='''### E-posta
- **Gönderen:** kurum adı, bilgi@ ve no-reply@ adresleri, cevap adresi; diğer eklentilerin göndereni de değişir (SPF/DKIM için).
- **Gönderim yöntemi:** "Hostinger ayarlarını doldur" düğmeli SMTP, no-reply@ için ayrı hesap, şifreli ya da `wp-config.php`'de saklanan şifre; no-reply@ girişi olmazsa bilgi@ ile gider.
- **E-postalar:** WordPress, WooCommerce ve FluxWP paketlerinin tüm e-postaları gruplu; aç/kapa, düzenleyici, gönderen seçimi, masaüstü/telefon/koyu önizleme ve "Bana test gönder". Listede olmayan e-posta ilk gönderildiğinde listeye eklenir.
- **Görünüm:** açık ve koyu zemin için logo, renkler, slogan, adres, sosyal medya, ağaç silüetli alt kısım; görseller (WebP dahil) gömülür, her programda görünür; "WooCommerce'e uygula"; her önizlemeyi PDF indirme.
- SPF, DKIM ve DMARC için **DNS kontrolü**; Google ile kayıt olana "parolanızı belirleyin" e-postası gitmez.

### Posta kutusu, gönderim ve kayıtlar
- **Posta kutusu:** IMAP ile bilgi@; okunmamış ve sistem e-postası süzgeçleri, yıldız, ekler, cevapla/tümünü cevapla/ilet, taslaklar, şablonlar ve **AI ile cevap yaz** (*Onay bekleyen*'e düşer).
- **Gönder:** bir kişiye ya da listeye (role göre üyeler, Flux Certificates kişileri) e-posta, WhatsApp ya da SMS; canlı önizleme; Hostinger'ın günlük ve ileti başı sınırlarına uyan arka plan kuyruğu.
- **Kayıtlar:** her mesaj zaman, alıcı, konu, kaynak eklenti, kanal ve durumla; tıklayınca içerik. Flux Chat'ndeki kara listeye uyulur.

### WhatsApp ve SMS
- **Bağlantılar:** WhatsApp için Evolution API, Flux WPAPI ya da Cloud API (Meta), SMS için Netgsm; birden çok numara, kanal başına varsayılan, QR ya da eşleştirme koduyla bağlanma, durum, yeniden başlatma, oturum kapatma.
- Bir bağlantıyı metin, resim, sesli mesaj, belge ya da (Cloud API) şablonla **test edin**.''',
 shots=[('emails','Aç/kapa ve düzenleyicili e-postalar'),('mailbox','Açık bir iletiyle posta kutusu'),('send','Canlı önizlemeli gönderim'),('connections','WhatsApp ve SMS bağlantıları'),('logs','Gönderim kayıtları'),('settings','Ayarlar: gönderen ve SMTP'),('dark','Flux Admin ile koyu tema'),(['mobile','mobile-log'],'Mobil: posta kutusu ve kayıtlar')],
 req="bilgi@ için bir e-posta hesabı (isteğe bağlı no-reply@). İsteğe bağlı: WhatsApp için Evolution API 2.3+ ya da Flux WPAPI sunucusu, SMS için Netgsm hesabı, AI cevaplar için Flux AI, kara liste için Flux Chat.",
 first='''1. Flux Mail'i etkinleştirin: WordPress e-postaları hemen bilgi@ / no-reply@ adresine ve kurumsal şablona geçer.
2. **İletişim › Ayarlar › Gönderim yöntemi › SMTP › Hostinger ayarlarını doldur**; şifreyi girip kaydedin ve **Test e-postası gönder**'e basın.
3. **Ayarlar › Test › DNS'i kontrol et**; eksik SPF, DKIM ya da DMARC kaydını ekleyin.
4. **Ayarlar › Posta kutusu**: bilgi@ için posta kutusunu açın (IMAP bilgileri SMTP'den gelir).
5. **İletişim › Bağlantılar**: WhatsApp numarası (QR) ve SMS için Netgsm ekleyin.''',
 note="> ⚠️ Hostinger günde 100 e-posta ve ileti başına 100 alıcıya izin verir; toplu gönderim bu sınırda kalacak şekilde kuyruğa alınır. Tek bir SMTP eklentisi kullanın: WP Mail SMTP ya da LiteSpeed SMTP de açıksa Sistem Kontrolü uyarır.",
 mcp=MCP_TR,
 dev='''- Şablonlar: `flxi_email_types` filtresi; gönderim `flxi_send_email( $key, $to, $vars )`.
- WhatsApp / SMS: `flxi_send_message()`; yeni sağlayıcı için `flxi_connection_types`.
- Posta kutusu: `flxi_mailbox_query()`, `flxi_mailbox_new_draft()` (ayrıca `POST /wp-json/flxi/v1/mailbox/new-draft`).
- Kara liste: `flx_contact_blocked`; kota: `flxi_quota_left()`.''',
 roadmap=[(1,'Her e-posta için kurumsal şablon ve düzenleyici'),(1,'SMTP, DNS kontrolü ve gönderim kayıtları'),(1,'Tüm FluxWP paketlerinin ortak WhatsApp ve SMS bağlantıları'),(1,'AI cevaplı ve onaylı posta kutusu'),(1,"WhatsApp'ta resim, ses ve belge"),(0,'Toplu gönderimde açılma ve tıklama istatistikleri'),(0,'Personel arasında atamalı ortak gelen kutusu')]),
}
