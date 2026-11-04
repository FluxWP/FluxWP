# Değişiklikler

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
