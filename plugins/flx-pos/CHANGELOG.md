# Flux POS Entegrator

## 0.1.0

- İlk sürüm: WooCommerce için ortak sanal POS. Kendi temiz uygulamamız; bankaların ve kuruluşların yayımladığı protokol belgelerine göre.
- Bankalar: Nestpay/EST altyapısı (Akbank, İş Bankası, Halkbank, TEB, Ziraat, ING, Şekerbank, QNB, Anadolubank, Türkiye Finans, Alternatif Bank), Garanti BBVA (GVP) ve Garanti Pay, Akbank yeni sanal POS, Yapı Kredi (Posnet) ve Albaraka, Vakıfbank ve Ziraat Katılım (PayFlex), QNB (PayFor), Denizbank (InterPOS), Kuveyt Türk, Vakıf Katılım, İş Bankası GiroGate.
- Ödeme kuruluşları: iyzico, PayTR, Param, Craftgate, Moka United, Tosla, Tami, Paynkolay, EsnekPOS, Payten (Paratika, VakıfPayS, ZiraatPay), ccpayment altyapısı (Sipay, QNBpay, Vepara, Paybull, Halk Ödeme), Shopier, Mollie, Papara.
- Belgesi beklenenler listede görünür (Lidio, Hepsipay, Paycell, Weepay…); belge gelince eklenecek.
- Ödeme modelleri: 3D Pay, 3D Model, bankanın/kuruluşun ödeme sayfası, iFrame, cüzdan. Banka dönüşüne güvenilmez: imza/hash doğrulanır ya da sunucudan provizyon/sorgu yapılır, tutar ve referans eşleşmeli; tutmazsa sipariş "beklemede" ve işlem "kontrol gerekli".
- WooCommerce: tek "Kart ile öde" yöntemi (para birimine göre hesap seçimi), cüzdanlar ayrı yöntem. Klasik ve blok ödeme kart formu, kart markası, BIN'e göre taksit, taksit farkı sipariş ücreti satırı. Siparişteki İade düğmesi: aynı gün tam tutarda önce iptal, değilse iade (kısmi iade destekleyenlerde kısmi).
- Panel (POS menüsü): Hesaplar (anahtarlar şifreli, test/canlı, gelişmiş banka adresleri, bildirim adresi, test kartları, bağlantı testi), Ödeme yöntemleri (sitedeki tüm yöntemlerin yetenek tablosu), İşlemler (süzme, siparişsiz işlemde iade), Ayarlar.
- Flux Donate'ten taşındı: tüm yöntemlerin yetenek tablosu ve `flxpos_gateway_adapters`, tek kart yöntemi kuralı (`card_method`), blok ödeme yedeği, tema ödeme logoları, EsnekPOS resmi modülü köprüsü (ödemeyi Flux POS yürütür, ProcessQuery ile doğrular).
- PHP API: `flxpos_charge()`, `flxpos_refund()`, `flxpos_txn()`; olay `flxpos_txn_settled`. REST `flxpos/v1/installments`.
- Kart bilgisi sitede saklanmaz: tek kullanımlık şifreli kasada en fazla 10 dakika kalır, köprü sayfasında bir kez açılır. Günlükte kart ve anahtarlar maskelenir.
- Sistem Kontrolü satırları (test modundaki hesaplar, HTTPS, şifreleme).
- Not: Sürücüler sağlayıcı test ortamlarında henüz denenmedi; canlıya almadan önce her hesabı test kartıyla deneyin.
