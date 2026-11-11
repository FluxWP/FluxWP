# Değişiklikler

## 0.4.0
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
