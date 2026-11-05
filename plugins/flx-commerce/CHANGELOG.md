# Değişiklikler

## 0.2.3
- Uygulama manifesti: satış özeti, müşteriler ve dekont onayı. Ayarlar web görünümünde açılır.
- Yeni uçlar `flx-commerce/v1`: `summary`, `customers`, `customers/{id}`, `receipts`, `receipts/{id}`, `receipts/{id}/approve`. Okuma rapor yetkisi, onay sipariş yetkisi.

## 0.2.2
- Güvenlik (CodeQL): ücretsiz kargo şeridi artık HTML ayrıştırmıyor; metin düz çeviri, tutar `<strong>` içinde `textContent` ile yazılır.

## 0.2.1
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 0.2.0
- **Müşteri sayfası** (Müşteriler listesinde ada tıklayın): siparişler, ödemeler, iadeler ve sipariş notları filtreli zaman tünelinde (Tümü / Siparişler / Ödemeler / Notlar), toplam harcama, sipariş, ortalama sepet, ilk/son sipariş ve sipariş sıklığı; bilgiler, mağaza notu, son siparişteki adresler, en çok aldığı ürünler.
- **Flux CRM ile ortak kişi**: CRM kuruluysa (ve kullanıcının CRM yetkisi varsa) müşteri sayfası CRM'in ortak kişi kartını gösterir; kişi yoksa oluşturulur, siparişleri bir kez CRM zaman tüneline eklenir. Karta Siparişler, Mağaza (özet + not), Adresler ve Sık aldığı ürünler bölümleri eklenir; aynı bölümler CRM'in kendi kişi sayfasında da görünür.
- `flx-commerce-customers&email=…` adresi e-postayla müşteri sayfasını açar.
- Tarih ve "… önce" yazımı kullanıcının dilinde (ortak `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi karışık metin yok; WordPress/WooCommerce dil paketi inmemiş olsa da TR ve EN doğru. Sipariş durumları (Hazırlanıyor, Tamamlandı…) eklentinin kendi çevirisinden.

## 0.1.1
- Satış raporu: telefonda tarih aralığı ve "Göster" düğmesi ekrana sığıyor.

## 0.1.0
- İlk sürüm. WooCommerce'e bağlı, her mağazanın kullanabileceği genel e-ticaret eklentisi (bağışa özgü bir şey yok).
- **Mağaza › Genel bakış**: bugün / 7 / 30 / 90 gün net ciro, sipariş, ortalama sepet ve müşteri; önceki döneme göre değişim, günlük ciro grafiği, en çok satanlar, ödeme bekleyenler, stok azalanlar, ödeme yöntemleri. Başlangıç'ta "Mağaza · bugün" kartı.
- **Müşteriler**: üye ve misafir müşteriler e-posta başına (sipariş sayısı, toplam harcama, ilk/son sipariş, şehir, telefon); Yeni, Tekrar alan, En çok harcayan %10, Uzaklaşan bölümleri; arama, sıralama, satır içi not, CSV. Eski siparişler 100'lük parçalarla taranır.
- **Satış raporu**: hazır dönemler ya da tarih aralığı; net ciro, sipariş, adet, ortalama sepet, iade, kargo geliri; ürün, kategori ve ödeme yöntemi kırılımı; CSV.
- **Havale/EFT** (`flxcm_bank`): siparişe özel açıklama kodu, teşekkür sayfasında ve Hesabım'da kopyalanabilir banka bilgileri + dekont yükleme (PDF/JPG/PNG/WEBP/HEIC, korumalı klasör), "Beklemede" e-postasına banka bilgisi, sipariş ekranında "Ödemeyi onayla" ve siparişler listesinde Havale sütunu. Ödenmeyen havaleyi N gün sonra iptal etme. Hesap bilgisi boşsa WooCommerce "Doğrudan banka havalesi"nden. Flux Donate'in havalesi açıksa varsayılan olarak gizlenir.
- **Ödeme sayfası**: şirket / adres 2 gizleme, yalnız sanal ürünlü sepette adres sormama (klasik), sipariş notu aç/kapa, mobilde sabit "Siparişi ver" (FluxWP teması dışında).
- **Ücretsiz kargo çubuğu**: sepet, ödeme ve mini sepette; blok sepette adet değişince canlı güncellenir. Eşik ayardan ya da WooCommerce "Ücretsiz kargo" yönteminden.
- **Ürün feed'i**: Google Merchant RSS (varyasyonlar, indirimli fiyat, stok, GTIN/MPN, kategori), anahtarlı adres, 1 saat önbellek.
- **Bildirimler**: Flux Mail WhatsApp bağlantısıyla müşteriye havale bilgisi ve ödeme onayı; dekont gelince yöneticiye e-posta + WhatsApp; isteğe bağlı her yeni siparişte yöneticiye WhatsApp.
- Flux Donate kuruluyken yalnız bağış içeren siparişler mağaza istatistiklerine girmez (ayarlanır).
- TR + EN, Flux Admin tasarım dili ve renkleri, Ticaret grubu ikonu, FluxWP imzası, ortak sistem kontrolü (`flx_system_checks`), HPOS ve sepet/ödeme blokları uyumlu.
