# Flux POS Entegrator

## 0.2.0

- İlk sürüm: WooCommerce için ortak, isteğe bağlı sanal POS eklentisi (açık kaynak). Kendi temiz uygulamamız; bankaların ve kuruluşların yayımladığı protokol belgelerine göre, better-payment (MIT) sürücüleriyle karşılaştırıldı. Flux GraphQL gerekir, PHP 8.1+.
- Bankalar: Nestpay/EST altyapısı (Akbank, İş Bankası, Halkbank, TEB, Ziraat, ING, Şekerbank, QNB, Anadolubank, Türkiye Finans, Alternatif Bank), Garanti BBVA (GVP) ve Garanti Pay, Akbank yeni sanal POS, Yapı Kredi (Posnet) ve Albaraka, Vakıfbank ve Ziraat Katılım (PayFlex), QNB (PayFor), Denizbank (InterPOS), Kuveyt Türk, Vakıf Katılım, İş Bankası GiroGate.
- Ödeme kuruluşları: iyzico, PayTR, Param, Craftgate, Moka United, Tosla, Tami, Paynkolay, EsnekPOS, Payten (Paratika, VakıfPayS, ZiraatPay), ccpayment altyapısı (Sipay, QNBpay, Vepara, Paybull, Halk Ödeme), Shopier, Mollie, Papara.
- Belgesi beklenenler listede görünür (Lidio, Hepsipay, Paycell, Weepay…); belge gelince eklenecek.
- Ödeme modelleri: 3D Pay, 3D Model, bankanın/kuruluşun ödeme sayfası, iFrame, cüzdan. Banka dönüşüne güvenilmez: imza/hash doğrulanır ya da sunucudan provizyon/sorgu yapılır, tutar ve referans eşleşmeli; tutmazsa sipariş "beklemede" ve işlem "kontrol gerekli".
- WooCommerce: tek "Kart ile öde" yöntemi (para birimine göre hesap seçimi), cüzdanlar ayrı yöntem. Klasik ve blok ödeme kart formu, kart markası, BIN'e göre taksit, taksit farkı sipariş ücreti satırı. Siparişteki İade düğmesi: aynı gün tam tutarda önce iptal, değilse iade (kısmi iade destekleyenlerde kısmi).
- **React panel** (POS menüsü, FluxWP uygulamasında da aynı ekran, veri GraphQL): Ödeme yöntemleri (sitedeki tüm WooCommerce yöntemleri hangi eklentiden olursa olsun: aç/kapa, sürükleyerek sıralama, tek kart kuralı, yetenekler ve ödeme adımının önizlemesi), POS hesapları (sağlayıcı seç, anahtarları gir; gizli anahtarlar yalnız yazılır, bağlantı testi), İşlemler (süzme, iade), Ayarlar. Klasik PHP sayfası yok.
- **Siparişte ödeme detayları:** "Kart · EsnekPOS · ₺100,00 · tek çekim" satırı, maskeli kart, taksit, tutar, banka referansı, onay kodu, test/canlı. Flux Donate kuruluysa bilgi onun "Ödeme detayları" kutusuna `flxb_payment_details` ile gider.
- Flux Donate'ten taşındı: tüm yöntemlerin yetenek tablosu ve `flxpos_gateway_adapters`, tek kart yöntemi kuralı (`card_method`; Donate kuruluyken Donate'in kuralı kullanılır), blok ödeme yedeği, tema ödeme logoları.
- PHP API: `flxpos_charge()`, `flxpos_refund()`, `flxpos_txn()`; olay `flxpos_txn_settled`. REST `flxpos/v1/installments`.
- Kart bilgisi sitede saklanmaz: tek kullanımlık şifreli kasada en fazla 10 dakika kalır, köprü sayfasında bir kez açılır. Günlükte kart ve anahtarlar maskelenir.
- Sistem Kontrolü satırları (test modundaki hesaplar, HTTPS, şifreleme).
- Ödeme sayfası gösterimi (hesap başına ya da genel): yönlendirme, açılır pencere (ödeme sayfasından ayrılmadan), site içinde (sitenin üst/alt bölümüyle). Çerçeveden sonuç sayfasına üst pencerede çıkılır. Filtre `flxpos_display`.
- Taksit düzenleyici: taksit başına aç/kapa, vade farkı %, örnek tutara göre aylık ve toplam önizleme.
- Not: Sürücüler sağlayıcı test ortamlarında henüz denenmedi; canlıya almadan önce her hesabı test kartıyla deneyin.
