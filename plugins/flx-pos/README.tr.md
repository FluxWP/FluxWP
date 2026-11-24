# Flux POS Entegrator

WooCommerce için ortak sanal POS: Türkiye'deki bankalar ve ödeme kuruluşları tek eklentide. Kendi kart formu (klasik
ve blok ödeme), 3D Secure, test modu, taksit, siteden iade ve işlem kayıtları.

- **Bankalar:** Nestpay/EST (Akbank, İş Bankası, Halkbank, TEB, Ziraat, ING, Şekerbank, QNB, Anadolubank, Türkiye
  Finans, Alternatif Bank), Garanti BBVA + Garanti Pay, Akbank yeni sanal POS, Yapı Kredi (Posnet), Albaraka,
  Vakıfbank ve Ziraat Katılım (PayFlex), QNB (PayFor), Denizbank (InterPOS), Kuveyt Türk, Vakıf Katılım, İş Bankası GiroGate.
- **Ödeme kuruluşları:** iyzico, PayTR, Param, Craftgate, Moka United, Tosla, Tami, Paynkolay, EsnekPOS, Payten
  (Paratika, VakıfPayS, ZiraatPay), Sipay, QNBpay, Vepara, Paybull, Halk Ödeme, Shopier, Mollie, Papara.
- 3D Pay, 3D Model, ödeme sayfası, iFrame ve cüzdan modelleri. Banka dönüşüne güvenilmez: hash doğrulanır ya da ödeme
  sunucudan sorgulanır; tutar ve referans eşleşmeli.
- Tek "Kart ile öde" yöntemi hesabı para birimine göre seçer; cüzdanlar ayrı yöntem. Karta (BIN) göre taksit.
- Siparişteki İade düğmesi sağlayıcıda iade eder ya da (aynı gün, tam tutar) iptal eder.
- **Ödeme yöntemleri** sekmesi: sitedeki her WooCommerce ödeme eklentisinin neyi yapabildiği, tek kart yöntemi kuralı,
  yönlendirmeli eklentiler için blok ödeme yedeği, tema ödeme logoları. EsnekPOS resmi modülü Flux POS üzerinden
  güvenle çalışır.
- **Havale / EFT:** internet şubesi ekstresini (.xls, .xlsx, .csv) yükleyin; giren tutarlar bekleyen sipariş ve
  bağışlarla tutar, sipariş no / bağış kodu ve ad soyada göre eşleştirilir. Aynı ekstre iki kez yazılmaz.
- 3D sayfası yönlendirme, açılır pencere ya da site içinde çerçeve olarak gösterilebilir.
- Anahtarlar şifreli saklanır; kart bilgisi veritabanına yazılmaz.
- Diğer eklentiler için PHP API: `flxpos_charge()`, `flxpos_refund()`, `flxpos_txn_settled`.
  Ayrıntı: [docs/flx-pos/API.md](../../docs/flx-pos/API.md).

> Sürücüler sağlayıcıların yayımladığı protokol belgelerine göre yazıldı; hepsi henüz test ortamlarında denenmedi.
> Canlıya almadan önce her hesabı test kartıyla deneyin.

English: [README.md](README.md)
