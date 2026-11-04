# Flux CRM kurulum

1. `flx-crm.zip`'i **Eklentiler › Yeni ekle › Yükle** ile kurun ya da FluxWP Panel'den seçin, etkinleştirin.
2. **CRM › Ayarlar**:
   - **Kaynaklar**: hangi eklentiden kişi alınacağını ve yeni kişiye verilecek rolü seçin (Bağış → Bağışçı, WooCommerce → Müşteri, Form/WhatsApp → Aday). Rol ödeme tamamlanınca verilir.
   - **Tümünü eşitle**: geçmiş bağış, sipariş, form ve sohbetleri bir kez alır. Tekrar çalıştırmak güvenlidir.
   - **Skorlama**: kayıp riski eşiği (varsayılan %70), aktif sayılma süresi, raporlama para birimi.
3. **CRM › Segmentler**: hazır üç segment gelir (geri kazanılacak bağışçılar, yeni gelenler, şampiyonlar). Yenilerini kurallarla kurun.
4. Gönderim: **Flux Mail › Gönder › Alıcılar**'da "CRM segmenti" ya da "CRM rolü" seçin. Yalnız o kanala izin veren kişiler alıcı olur.
5. Personel erişimi: **Flux Roles**'ta "CRM" grubunda `flxcrm_view` (görür) ve `flxcrm_manage` (düzenler).

Notlar
- Skorlama günlük WP-Cron ile çalışır. Hostinger'da cron tetiklenmiyorsa sunucu cron'u ekleyin ya da Ayarlar'dan "Skorları yeniden hesapla".
- Başka para birimindeki ödemeler kur verilmedikçe toplam değere sayılmaz (Sistem kontrolleri uyarır).
- Kaldırınca CRM tabloları silinir; bağış, sipariş, form ve sohbet kayıtları kendi eklentilerinde kalır.
