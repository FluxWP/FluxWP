# Değişiklikler

## 0.1.0
- İlk sürüm. Bağışçı, müşteri, gönüllü, aday ve ortakları tek kişi kartında toplayan CRM.
- **Kişiler**: her kişi tek kart; kaynaktan gelen bağlantı, kullanıcı, e-posta, telefon sırasıyla tekilleştirilir. Kaynak veri yalnız boş alanları doldurur, elle girilen bilgi ezilmez. Çoklu rol (Bağışçı, Müşteri, Aday, Gönüllü, Üye, Kurumsal, Yararlanıcı + kendi rolleriniz), etiketler, sorumlu, kısa not, iletişim izinleri (e-posta, SMS, WhatsApp, reklam kitleleri; her değişiklik zaman tünelinde). Arama, rol/segment/etiket/izin filtreleri, toplu etiket/rol/arşiv/silme, CSV (formül enjeksiyonuna karşı korumalı).
- **Zaman tüneli**: bağış, sipariş, form, WhatsApp (kişi başına günde bir satır), not, arama, görüşme, rol ve segment değişimleri; Ödemeler / Mesajlar / Formlar / Notlar filtresi, kaynak kayda bağlantı.
- **Kaynaklar**: Flux Donate, WooCommerce (yalnız bağış içeren siparişler hariç, iadeler düşülür, UTM kaynağı), Flux Chat, Flux Forms (e-posta/telefon alanı olan gönderimler), site üyeleri (müşteri/abone). Yeni kayıtlar anında gelir; geçmiş "Tümünü eşitle" ile 100'lük parçalarla alınır. Olaylar tekil anahtarla yazılır, eşitleme kaç kez çalışırsa çalışsın kayıt çiftlenmez.
- **RFM skorlama**: yenilik, sıklık (site saat diliminde ödeme yapılan gün), değer beşte birlik dilimlerle; Yeni, Şampiyon, Sadık, Umut vadeden, Risk altında, Uykuda, Kaybedilen. Kayıp riski kişinin kendi ödeme ritmine göre. Günlük WP-Cron + elle yeniden hesaplama.
- **Segmentler**: kural düzenleyici (rol, etiket, RFM, değer, ödeme günü, son ödeme/hareket, eklenme, kayıp riski, izin, kaynak, şehir, e-posta/telefon var), canlı sayı ve örnek kişiler, CSV. Flux Mail › Gönder'de alıcı kaynağı olarak görünür; yalnız o kanala izin verenler alıcı olur.
- **Genel bakış**: kişi, yeni, aktif, kayıp riski, toplam değer; RFM segmentleri, roller, "Temas kurulmalı" listesi, son hareketler, kaynaklar.
- Döviz: başka para birimindeki ödemeler `flxcrm_fx_rate` ile kur verilmedikçe değere sayılmaz; sistem kontrolü uyarır.
- REST `flx-crm/v1` (kişiler, olaylar, etiketler, segmentler, önizleme, skor), Flux AI araçları (kişi ara, kişi kartı, özet, not ekle), Flux App modülü, Flux Roles yetkileri (`flxcrm_view`, `flxcrm_manage`), sistem kontrolleri.
- TR + EN, Flux Admin tasarım dili, açık/koyu tema, mobil uyumlu, İçerik & büyüme grubu ikonu, FluxWP imzası.
