# Değişiklikler

## 0.9.3
- Telefon alanı (ülke kodlu ortak alan) yönetim panelinde diğer alanlarla aynı yükseklikte, köşede ve kenarlık renginde (flx-brand 1.6.2).

## 0.9.2
- The title over an event's bulk message (Flux Chat, from Flux Mail's `flxi_campaign_url`) opens that send's own page in the CRM, on its channel, instead of the Sent list.

## 0.9.1
- Send page: an Engagement column per recipient (reached, read, replied, turned off messages, with the time on hover) once Flux Mail returns these fields in `flxi_campaign_recipients()` (Flux Mail #260). Hidden with older Flux Mail.

## 0.9.0
- Event › Messages split into "New message", "Sent" and "Planned"; a send that is running shows at the top on every tab.
- Sends are cards you can click: state (sending, paused, planned, sent, cancelled), the text and its image, a progress bar per channel with failures, and the estimated finish as date and time.
- Clicking a send opens its page inside the CRM (no jump to the Flux Mail queue): the whole text, and who it went to per channel with Sent / Waiting / Failed filters, the error of a failed message and the time; it refreshes itself every minute while it runs.
- Stop a send from its card; Pause and Resume show once Flux Mail declares `pause` in `flxi_campaign_features` (`flxi_pause_campaign` / `flxi_resume_campaign`). Finished channels are left as they are.
- Estimated finish comes from Flux Mail's `flxi_campaign_eta()` when it declares `eta`; until then it is worked out from the pace so far. Recipients come from `flxi_campaign_recipients()` when Flux Mail declares `recipients`; until then from its queue, read only.
- Mini dashboard on every send card: reached, read, replied, turned off messages (STOP / unsubscribe after this send) and failed. Read and replied come from Flux Mail's `flxi_campaign_engagement()` once it declares `engagement`; until then reached = sent and opt-outs come from Flux Mail's list (by campaign).
- Overview › Last messages: rows open the send and show its state and estimated finish.
- Fixed: the broken image after the text in the send list (the 📎 emoji became an image that did not load); the send's image is shown instead.

## 0.8.0
- Events list redesigned: a grid of cards with the event image (or a date tile), a status badge (today, in N days, over, archived), people and package numbers, the goal bar, and "Open dashboard" / "Message" / ⋯ (package links, edit, site page, close, delete).
- The site settings that packages show under the list (Flux Certificates: the events section on the home page) now have their own "On the site" tab instead of sitting under the cards.
- Event dashboard: clicking an event opens Overview (numbers, event info with image, place, map and site page, where its people come from, last messages, package shortcuts), Contacts (with a quick filter) and Messages (compose and history).
- Back button at the top left of every detail and edit screen (event, event form, segment, journey, duplicates, new contact); the contact card's back link matches it.
- Dates on the event screens follow the site language (e.g. "11 Kasım 2026").

## 0.7.0
- Event messages get values: buttons under the text add `{ad}`, `{ad_soyad}`, event values (`{etkinlik}`, `{etkinlik_tarihi}`, `{etkinlik_saati}`, `{etkinlik_yeri}`, `{etkinlik_link}`), site values (`{site}`, `{kurum}`, `{kurum_telefon}`) and every package's personal values (Flux Certificates: `{fidan}`, `{sertifika_sayisi}`, `{sertifika_link}`, `{sertifika_no}`) where the cursor is.
- Live preview: pick a person of the event and see the WhatsApp/SMS bubble (with the photo) and the e-mail (subject, photo, paragraphs) as that person will read it; values empty for that person are named.
- Event values are written into the text before it goes to Flux Mail. Personal values travel with each recipient (`vars` on the recipient) and need a Flux Mail that declares `recipient_vars` in `flxi_campaign_features`; until then those buttons are off and a text using them is refused with a clear message.
- Site users with no WooCommerce billing phone now get the account phone from Flux Roles (`flx_phone`) on their CRM contact.
- New shared filters: `flx_event_vars` (packages add personal values: `[ key => [ label, sample, values => [ person_id => value ] ] ]`) and `flx_event_message_vars` (event-wide values).

## 0.6.0
- Event messages: CRM › Events › an event › "Message to the event". One extra message to everyone tied to the event (certificates, donations, every package's people) by WhatsApp, e-mail and/or SMS, now or planned for a date and time (site time). Typical use: a thank-you with a photo when the event is over.
- A photo, video or file from the media library can go with it: on e-mail a photo shows above the text and other files get a button; on WhatsApp it goes as an attachment once Flux Mail declares campaign media (`flxi_campaign_features` contains `media`), until then the file link is added to the text, as on SMS.
- Message type: "Event information or thank-you" goes to every person of the event with an address on that channel; "Promotion or campaign" only to people who allowed that channel (and adds the unsubscribe link on e-mail). The blocklist and e-mail unsubscribes always apply.
- Sending is a normal Flux Mail bulk send (`flxi_create_campaign`) per channel, so the queue, the wait between WhatsApp messages and the daily e-mail limit apply. Campaign data carries `kind` (info|marketing) and `ref` (`flxcrm_event:<id>`) for Flux Mail.
- Works with Flux Mail 1.17.0 campaign media, `kind` and `ref` (FluxWP#240): an info message is not blocked by the IPTAL ad opt-out and gets no opt-out line; the blocklist still applies. The planning field notes the connection's quiet hours (21:00–09:00 by default), when WhatsApp and SMS wait.
- "Sent and planned" list on the event with each channel's state and count (links to the Flux Mail queue); a planned send can be cancelled until it starts. The event card has a "Send message" button.
- Flux Mail › Send gets the events (open, or closed in the last 60 days) as recipient sources `flxcrm_event:<id>` (consent required there). New action `flxcrm_event_message_queued`.

## 0.5.0
- New CRM › Journeys: automatic contact flows. A journey starts when a contact enters a value group (RFM), gets a tag or a role, has a timeline event, or is added to the CRM. Steps run in order: wait (days/hours), send e-mail, WhatsApp or SMS through Flux Mail, add or remove a tag, and "continue only if" (value group, tag, paid / not paid since the journey started).
- Safe by default: a new journey (also from a template or from AI) is a draft and sends nothing until someone turns it on; saving an active journey pauses it. Messages only go to people who allowed that channel, never in the quiet hours (21–09 by default, site time), and at most 3 journey messages per person per 7 days across all journeys. Skipped steps are written on the timeline with the reason.
- Goal: the journey ends early when the goal event happens (e.g. a donation). Re-entry after N days (0 = never).
- Two templates: "Welcome new donors" and "Win back donors at risk".
- Processing: hourly cron plus a light run after requests (Hostinger cron does not fire), at most 100 people per run, stops before PHP's 60 s limit.
- AI and MCP: `flxcrm_journeys` (read), `flxcrm_journey_save` (write, always draft or paused), `flxcrm_journey_status` and `flxcrm_journey_delete` (need approval).

## 0.4.3
- "Delete contact" moved from under the contact card into a ⋯ (more actions) menu at the top right of the contact header. "Delete permanently" opens a dialog where the contact's name has to be typed (checked on the server too).
- Every confirmation in CRM (delete contact, timeline entry, segment, event actions, merge duplicates, bulk delete) uses the shared FluxWP dialog (flx-brand 1.6.0 `flxDialog`) instead of the browser's confirm box. Responsive: bottom sheet on phones, light/dark.
- Ships flx-brand 1.6.0: `flxDialog.confirm/alert/prompt/toast` and `data-flx-confirm` for every package.

## 0.4.2
- CRM screens no longer reload the whole page: links, the left-menu CRM items, list filters, timeline tabs and forms (note, tags, details, delete, bulk actions) run with fetch and only the CRM content is replaced. Address bar, back/forward, page title and menu highlight follow along; styles and scripts a new screen needs (e.g. the phone field) are loaded on the fly.
- Also works for the shared person card inside other plugins (Donate, Commerce), on that page only. Sync and scoring refresh the screen the same way.
- Falls back to a normal page load when the response is not a CRM screen; opt out with `data-flxcrm-hard` on a link or form.

## 0.4.1
- Contact card › Details is read-only by default: name, e-mail, phone, organisation, city, roles, consents, owner and note as text, with an Edit button that opens the form (Save / Cancel). If saving fails, the card comes back with the form open.
- The phone field uses the shared FluxWP phone field (flx-brand `flx_phone_field`): country picker with flags, live check, saved as E.164. The form is not sent while the number is invalid.

## 0.4.0
- New Contacts › Possible duplicates: cards with the same name or the same last 10 phone digits are listed with the packages that use them; pick the one to keep and merge. Grouping can be extended with `flxcrm_duplicate_groups`.
- A contact that still has records in another package (`flx_person_in_use`: certificates, donations…) is not deleted; the card says where and suggests merging. Merging moves those records through `flx_person_merged`.
- New CRM › Events: the one screen for the events of every FluxWP package, on the shared events table (`wp_flx_events`, flx-brand 1.4.0). Open / archive tabs, create, edit, close, reopen, delete, create or publish the site page, and an event page listing everyone tied to it with a link to their contact card.
- The screen hardcodes no package. Fields come from `flx_event_fields` (section, translated label, type), numbers from `flx_event_stats`, buttons from `flx_event_actions`, people from `flx_event_people`, extra form parts from `flx_event_form_after` / `flx_events_screen_after`. Flux Certificates adds saplings, its links, the location QR and its site settings this way; a new package adds itself without a CRM update.
- `flxcrm_events_url()` for packages that link or redirect here (Flux Certificates › Events redirects when CRM is active).

## 0.3.0
- Contacts now live in the shared FluxWP people table (`wp_flx_people`, links in `wp_flx_people_links`) instead of CRM's own table. Every FluxWP package reads and writes the same person through `flx_person_ensure()`, `flx_person_find()`, `flx_person_update()`, whether or not Flux CRM is installed; install order does not matter. With CRM active those calls run CRM's own rules (matching, roles, timeline, hooks), so that logic stays in one place.
- Upgrade moves the 0.2.x tables by itself: contact ids, links, timeline and tags are kept. If another package already created people, rows are matched by site user, e-mail or phone and merged.
- CRM adds only its own columns (owner, UTM, totals, RFM, segment, churn) to the shared table. Uninstalling CRM no longer deletes people.
- Extension points, so another plugin can plug in without a CRM update: `flxcrm_register_sources` (a source gets its on/off switch and role in Settings, a step in "Sync everything" and a name on contact links), `flxcrm_source_on()`, `flxcrm_source_role()`; contact changes are also announced as `flx_person_created`, `flx_person_updated`, `flx_person_merged`, `flx_person_deleting`.
## 0.2.10
- Flux Mail › Gönder ekranında CRM segmenti ya da rolü seçildiğinde kaç kişinin o kanala (e-posta, WhatsApp, SMS) izin vermediği için alınmadığı bildirilir (Flux Mail 1.14.0+, `flxi_recipient_source_info`).

## 0.2.9
- Telefon numaraları tüm eklentilerde aynı kuralla okunur: `+90 0532…` yazımındaki fazla sıfır atılır, ülke kodu ve baştaki sıfır olmadan yazılan 10 haneli sabit hat (212…) 90 ile tamamlanır, hane sayısı ya da alan kodu tutmayan Türkiye numarası geçersiz sayılır.

## 0.2.8
- Kişi listesi CSV'sinde formül gibi açılacak hücreler tek tırnakla korunur. Sütunlar, virgül ayırıcı ve Excel BOM aynı kalır.

## 0.2.7
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 0.2.6
- Daily RFM scoring reads contacts 500 at a time, skips a row whose score did not change, and writes the rest in batches of 100. A run stops after about 20 seconds and resumes from `flxcrm_score_cursor` on the next run. Quintiles still use every paying contact.

## 0.2.5
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 0.2.4
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 0.2.3
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.


## 0.2.2
- Uygulama manifesti (`crm`): genel bakış, kişiler, kişi kartı, kişi formu, segment listesi ve segmentteki kişiler. Segment kuralı düzenleyici web görünümünde açılır. Veri mevcut `flx-crm/v1` uçlarından gelir.

## 0.2.1
- Metinler: MCP sunucusu artık Flux AI & MCP eklentisinde; "Flux SEO & MCP" ve "Flux AI ›" adresleri "Flux AI & MCP" oldu.

## 0.2.0
- **Yapay zekâ ve MCP ile tam yönetim**: CRM'deki her şey Flux AI sohbetinden ve MCP'den (Claude, ChatGPT, Cursor…) okunur ve düzenlenir. 25 araç: kişi ekle/düzenle (alanlar, roller, izinler, sorumlu, arşiv), etiket, zaman tüneli kaydı, segment, ayarlar ve roller, skor, mükerrer bulma, kişi birleştirme, silme, 5000 kişiye kadar toplu işlem, Flux Mail ile tek kişiye mesaj (kanal izni şart). Önceden yalnız 4 okuma/not aracı vardı ve MCP'de hiç yoktu.
- **Yetki ve onay**: araçlar kullanıcının kendi CRM yetkisiyle çalışır (`flxcrm_view`, `flxcrm_manage`; ayarlar yalnız yönetici). Değişiklik yapan araçlar Flux AI onay kartında bekler. Silme, birleştirme, toplu işlem ve gönderim MCP'de ilk çağrıda yalnız önizleme döner; 15 dakikalık tek kullanımlık onay anahtarıyla uygulanır. Toplu işlemde sohbet de önce kaç kişinin etkileneceğini gösterir.
- **CRM › Ayarlar › Yapay zekâ ve MCP**: Flux AI ve MCP durumu, MCP adresi, araçlar / düzenleme / onaylı işlemler anahtarları, araç listesi ve yapay zekâ ile yapılan son değişiklikler (kim, hangi kanal, sonuç).
- **Kişi birleştirme**: zaman tüneli, kaynak bağlantıları ve etiketler birincil karta taşınır, boş alanlar dolar, roller birleşir; zaman tünelinde "Kişiler birleştirildi".
- Geliştirici: tek araç kaydı `flxcrm_tools` filtresi ve `flxcrm_run_tool()` (ileride otomasyonlar aynı kayıttan çalışacak), `flxcrm_tool_ran` eylemi, `FLXCRM_Tags::update()`.

## 0.1.1
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 0.1.0
- İlk sürüm. Bağışçı, müşteri, gönüllü, aday ve ortakları tek kişi kartında toplayan CRM.
- **Kişiler**: her kişi tek kart; kaynaktan gelen bağlantı, kullanıcı, e-posta, telefon sırasıyla tekilleştirilir. Kaynak veri yalnız boş alanları doldurur, elle girilen bilgi ezilmez. Çoklu rol (Bağışçı, Müşteri, Aday, Gönüllü, Üye, Kurumsal, Yararlanıcı + kendi rolleriniz), etiketler, sorumlu, kısa not, iletişim izinleri (e-posta, SMS, WhatsApp, reklam kitleleri; her değişiklik zaman tünelinde). Arama, rol/segment/etiket/izin filtreleri, toplu etiket/rol/arşiv/silme, CSV (formül enjeksiyonuna karşı korumalı).
- **Zaman tüneli**: her şey tek yerde. Bağış, sipariş, form, her WhatsApp mesajı (gelen/giden, metniyle), Flux Mail'den kişiye giden e-posta, SMS ve WhatsApp bildirimleri (sertifika, bağış, form…), posta kutusuna gelen e-postalar, not, arama, görüşme, rol ve segment değişimleri. Ödemeler / Mesajlar / Formlar / Notlar sekmeleri; 20'şer olay, aşağı kaydırdıkça devamı yüklenir; mobilde tünel sınırlı yükseklikte kendi içinde kayar.
- **Kaynaklar**: Flux Donate, WooCommerce (yalnız bağış içeren siparişler hariç, iadeler düşülür, UTM kaynağı), Flux Chat, Flux Forms (e-posta/telefon alanı olan gönderimler), site üyeleri (müşteri/abone), Flux Mail (yalnız zaman tüneli; kişi oluşturmaz, Flux Chat'in kendi mesajlarını tekrar yazmaz). Yeni kayıtlar anında gelir; geçmiş "Tümünü eşitle" ile 100'lük parçalarla alınır. Olaylar tekil anahtarla yazılır, eşitleme kaç kez çalışırsa çalışsın kayıt çiftlenmez.
- **RFM skorlama**: yenilik, sıklık (site saat diliminde ödeme yapılan gün), değer beşte birlik dilimlerle; Yeni, Şampiyon, Sadık, Umut vadeden, Risk altında, Uykuda, Kaybedilen. Kayıp riski kişinin kendi ödeme ritmine göre. Günlük WP-Cron + elle yeniden hesaplama.
- **Segmentler**: kural düzenleyici (rol, etiket, RFM, değer, ödeme günü, son ödeme/hareket, eklenme, kayıp riski, izin, kaynak, şehir, e-posta/telefon var), canlı sayı ve örnek kişiler, CSV. Flux Mail › Gönder'de alıcı kaynağı olarak görünür; yalnız o kanala izin verenler alıcı olur.
- **Genel bakış**: kişi, yeni, aktif, kayıp riski, toplam değer; RFM segmentleri, roller, "Temas kurulmalı" listesi, son hareketler, kaynaklar.
- Döviz: başka para birimindeki ödemeler `flxcrm_fx_rate` ile kur verilmedikçe değere sayılmaz; sistem kontrolü uyarır.
- REST `flx-crm/v1` (kişiler, olaylar, etiketler, segmentler, önizleme, skor), Flux AI araçları (kişi ara, kişi kartı, özet, not ekle), Flux App modülü, Flux Roles yetkileri (`flxcrm_view`, `flxcrm_manage`), sistem kontrolleri.
- **Ortak kişi kartı**: başka eklentiler kendi kişi sayfalarında aynı kartı gösterir (`flxcrm_contact_for()`, `flxcrm_ensure_contact()`, `flxcrm_person_card()`); kendi bölümlerini `flxcrm_person_card_*` eylemleriyle ekler, sekmeler ve formlar o sayfaya döner. CRM yoksa ya da yetki yoksa eklenti kendi sayfasını gösterir.
- TR + EN, Flux Admin tasarım dili, açık/koyu tema, mobil uyumlu, İçerik & büyüme grubu ikonu, FluxWP imzası.
