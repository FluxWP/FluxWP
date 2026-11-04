SLUG='flx-crm'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-crm'; WP='6.4'; PHP='7.4'; DOCS='flx-crm'; API=True
C={
'en':dict(name='Flux CRM',
 tagline='Donors, customers, volunteers and leads in one contact card, with a timeline, RFM scoring and segments.',
 desc='Flux CRM turns the people already on your site into contacts: every donation, order, form and WhatsApp chat lands on one card per person. It scores everyone by recency, frequency and value, shows who is drifting away, and keeps live segments you can send to from Flux Mail.',
 badges=['![RFM](https://img.shields.io/badge/RFM-scoring-db2777)','![Segments](https://img.shields.io/badge/segments-live-db2777)'],
 gifalt='Flux CRM tour: the overview with RFM segments and the reach-out list, the contact list, a contact timeline and the segment rule editor',
 why='''Donations live in Flux Donate, orders in WooCommerce, messages in Flux Chat and sign-ups in Flux Forms. The same person shows up four times and nobody sees the whole picture. Flux CRM ties them together:

- 👤 **One card per person:** matched by source, account, e-mail and phone; what you type by hand is never overwritten.
- 🕒 **The whole story:** donations, orders, forms, WhatsApp, notes and calls on one timeline.
- 📉 **Who is drifting away:** RFM segments and a churn risk based on each person's own rhythm.
- 🎯 **Segments you can send to:** rule-based live lists that appear as recipients in Flux Mail, consent checked at send time.''',
 features='''### Contacts
- Roles (Donor, Customer, Lead, Volunteer, Member, Partner, Beneficiary and your own), tags, owner, short note.
- Consents per channel (e-mail, SMS, WhatsApp, ad audiences); every change is written to the timeline.
- Search, filters by role, RFM segment, saved segment, tag and consent; bulk tag, role, archive and delete; CSV export.

### Timeline
- Donations, orders (minus refunds), form answers, WhatsApp (one line per day), notes, calls, meetings, role and segment changes.
- Filters for payments, messages, forms and notes, with links back to the original record.

### Sources
- Flux Donate, WooCommerce (donation-only orders skipped), Flux Chat, Flux Forms and site members.
- New records arrive instantly; "Sync everything" imports the past in chunks of 100. Every event has a unique key, so syncing twice never duplicates anything.

### RFM scoring
- Recency, frequency (distinct paid days) and value in quintiles: New, Champion, Loyal, Promising, At risk, Hibernating, Lost.
- Churn risk follows the person's own payment gap: a monthly donor is at risk after 45 days, a yearly one is not. Recalculated daily.

### Segments
- Rule editor with a live count and sample contacts: role, tag, RFM, value, paid days, last payment, last activity, churn, consent, source, city.
- Use a segment as recipients in Flux Mail; only people who allowed that channel receive it.''',
 shots=[('overview','Overview'),('contacts','Contacts'),('contact','Contact card and timeline'),('segments','Segments'),('segment-editor','Segment rule editor'),('settings','Settings'),('dark','Dark mode'),('dark-contact','Contact card in dark mode'),('mobile','Mobile',True),('mobile-contact','Contact on mobile',True)],
 req='Optional sources: Flux Donate, WooCommerce, Flux Chat, Flux Forms. Optional: Flux Mail (sending), Flux AI, Flux Admin.',
 first='''1. Open **CRM** in the admin menu.
2. **CRM › Settings › Sync everything** once to bring in past donations, orders, forms and chats.
3. Pick which role each source gives new contacts, and the churn threshold.
4. Build a segment under **CRM › Segments** and send to it from **Flux Mail › Send**.''',
 dev='''- `FLXCRM_Contacts::upsert()`, `FLXCRM_Events::add()` with a `uniq` key, `FLXCRM_Contacts::ids( array( 'rules' => … ) )`.
- Actions: `flxcrm_event`, `flxcrm_contact_created`, `flxcrm_role_added`, `flxcrm_segment_changed`, `flxcrm_consent_changed`; filters `flxcrm_roles`, `flxcrm_segment_fields`, `flxcrm_fx_rate`, `flxcrm_sync_sources`.
- REST `flx-crm/v1` and Flux AI tools. Full list: [API.md](../../docs/flx-crm/API.md).''',
 roadmap=[(1,'Contacts, roles, tags, consents, timeline'),(1,'Sources: Flux Donate, WooCommerce, Flux Chat, Flux Forms, site members'),(1,'RFM scoring, churn risk, segments for Flux Mail'),(0,'Pipelines (donation follow-up, partnership, volunteering)'),(0,'Automations and journeys: welcome, thank-you, win-back with frequency caps and quiet hours'),(0,'Flux Ads: ad audiences and conversions as a separate plugin')]),
'tr':dict(name='Flux CRM',
 tagline='Bağışçı, müşteri, gönüllü ve adaylar tek kişi kartında: zaman tüneli, RFM skorlama ve segmentler.',
 desc='Flux CRM sitede zaten olan insanları kişiye dönüştürür: her bağış, sipariş, form ve WhatsApp sohbeti kişi başına tek karta düşer. Herkesi yenilik, sıklık ve değere göre puanlar, kimin uzaklaştığını gösterir ve Flux Mail\'den gönderim yapabileceğiniz canlı segmentler tutar.',
 badges=['![RFM](https://img.shields.io/badge/RFM-skorlama-db2777)','![Segment](https://img.shields.io/badge/segment-canl%C4%B1-db2777)'],
 gifalt='Flux CRM turu: RFM segmentleri ve temas listesiyle genel bakış, kişi listesi, kişi zaman tüneli ve segment kural düzenleyici',
 why='''Bağışlar Flux Donate'te, siparişler WooCommerce'te, mesajlar Flux Chat'te, başvurular Flux Forms'ta. Aynı kişi dört yerde görünür, kimse resmin tamamını görmez. Flux CRM hepsini birleştirir:

- 👤 **Kişi başına tek kart:** kaynak, hesap, e-posta ve telefonla eşleşir; elle yazdığınız bilgi ezilmez.
- 🕒 **Hikâyenin tamamı:** bağış, sipariş, form, WhatsApp, not ve aramalar tek zaman tünelinde.
- 📉 **Kim uzaklaşıyor:** RFM segmentleri ve kişinin kendi ritmine göre kayıp riski.
- 🎯 **Gönderilebilir segmentler:** kurallı canlı listeler Flux Mail'de alıcı olarak görünür, izin gönderim anında kontrol edilir.''',
 features='''### Kişiler
- Roller (Bağışçı, Müşteri, Aday, Gönüllü, Üye, Kurumsal, Yararlanıcı ve kendi rolleriniz), etiketler, sorumlu, kısa not.
- Kanal başına izin (e-posta, SMS, WhatsApp, reklam kitleleri); her değişiklik zaman tünelinde.
- Arama; rol, RFM segmenti, kayıtlı segment, etiket ve izin filtreleri; toplu etiket, rol, arşiv ve silme; CSV.

### Zaman tüneli
- Bağış, sipariş (iadeler düşülür), form yanıtları, WhatsApp (günde bir satır), not, arama, görüşme, rol ve segment değişimleri.
- Ödemeler, mesajlar, formlar ve notlar filtresi; asıl kayda bağlantı.

### Kaynaklar
- Flux Donate, WooCommerce (yalnız bağış içeren siparişler hariç), Flux Chat, Flux Forms ve site üyeleri.
- Yeni kayıtlar anında gelir; "Tümünü eşitle" geçmişi 100'lük parçalarla alır. Her olayın tekil anahtarı var, iki kez eşitlemek hiçbir şeyi çiftlemez.

### RFM skorlama
- Yenilik, sıklık (ödeme yapılan gün) ve değer beşte birlik dilimlerle: Yeni, Şampiyon, Sadık, Umut vadeden, Risk altında, Uykuda, Kaybedilen.
- Kayıp riski kişinin kendi ödeme aralığına göre: ayda bir bağış yapan 45 gün gelmeyince riskli, yılda bir yapan değil. Her gün yeniden hesaplanır.

### Segmentler
- Canlı sayı ve örnek kişilerle kural düzenleyici: rol, etiket, RFM, değer, ödeme günü, son ödeme, son hareket, kayıp riski, izin, kaynak, şehir.
- Segmenti Flux Mail'de alıcı olarak seçin; yalnız o kanala izin verenler alır.''',
 shots=[('overview','Genel bakış'),('contacts','Kişiler'),('contact','Kişi kartı ve zaman tüneli'),('segments','Segmentler'),('segment-editor','Segment kural düzenleyici'),('settings','Ayarlar'),('dark','Koyu mod'),('dark-contact','Koyu modda kişi kartı'),('mobile','Mobil',True),('mobile-contact','Mobilde kişi',True)],
 req='İsteğe bağlı kaynaklar: Flux Donate, WooCommerce, Flux Chat, Flux Forms. İsteğe bağlı: Flux Mail (gönderim), Flux AI, Flux Admin.',
 first='''1. Yönetim menüsünde **CRM**'i açın.
2. Geçmiş bağış, sipariş, form ve sohbetler için bir kez **CRM › Ayarlar › Tümünü eşitle**.
3. Her kaynağın yeni kişiye vereceği rolü ve kayıp riski eşiğini seçin.
4. **CRM › Segmentler**'de segment kurun, **Flux Mail › Gönder**'den o segmente gönderin.''',
 dev='''- `FLXCRM_Contacts::upsert()`, `uniq` anahtarlı `FLXCRM_Events::add()`, `FLXCRM_Contacts::ids( array( 'rules' => … ) )`.
- Eylemler: `flxcrm_event`, `flxcrm_contact_created`, `flxcrm_role_added`, `flxcrm_segment_changed`, `flxcrm_consent_changed`; filtreler `flxcrm_roles`, `flxcrm_segment_fields`, `flxcrm_fx_rate`, `flxcrm_sync_sources`.
- REST `flx-crm/v1` ve Flux AI araçları. Tam liste: [API.md](../../docs/flx-crm/API.md).''',
 roadmap=[(1,'Kişiler, roller, etiketler, izinler, zaman tüneli'),(1,'Kaynaklar: Flux Donate, WooCommerce, Flux Chat, Flux Forms, site üyeleri'),(1,'RFM skorlama, kayıp riski, Flux Mail için segmentler'),(0,'Süreçler (bağış takibi, kurumsal ortaklık, gönüllülük)'),(0,'Otomasyonlar ve yolculuklar: hoş geldin, teşekkür, geri kazanım; sıklık sınırı ve sessiz saatler'),(0,'Flux Ads: reklam kitleleri ve dönüşümler, ayrı eklenti')]),
}
