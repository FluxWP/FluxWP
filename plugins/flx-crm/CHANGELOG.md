# Değişiklikler

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
