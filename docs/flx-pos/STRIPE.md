# Flux POS · Stripe (0.3.0)

## Ne yapar

- Kart alanları ödeme sayfasında Stripe'ın **Payment Element**'i (klasik ve blok ödeme). Kart bilgisi siteye hiç gelmez
  (PCI SAQ A). Stripe hesabında açıksa Apple Pay, Google Pay, Link ve diğer yöntemler aynı alanda görünür.
- Ödeme: tarayıcı `pm_…` üretir → sipariş gönderilir → sunucu PaymentIntent'i oluşturup onaylar
  (`confirm=true`, `automatic_payment_methods`). 3D Secure gerekirse Stripe'ın doğrulama sayfasına gidilir, dönüşte
  PaymentIntent Stripe'tan okunur (dönüş verisine güvenilmez; `metadata.flxpos_ref` işlemle eşleşmeli).
- Belirteçsiz ödeme (`flxpos_charge()` belirteçsiz, JS çalışmadı): köprü sitenin kendi Stripe sayfasını açar
  (Payment Element + `confirmPayment`).
- Webhook: `payment_intent.succeeded | processing | payment_failed`, `Stripe-Signature` HMAC-SHA256 + 5 dk tolerans.
  Kart reddi işlemi kapatmaz (ödeyen yeniden dener). Olaydaki nesneye değil, Stripe'tan okunan güncel duruma bakılır.
- İade: siparişteki İade düğmesi ya da `flxpos_refund()`; tam ve kısmi. (Stripe panelinden yapılan iade Flux POS'a
  yansımaz; iadeyi siteden yapın.)
- Çift çekim koruması: her istek `Idempotency-Key` taşır; köprü yenilenirse aynı PaymentIntent sürdürülür.
- Para birimi: 40 birim, ondalıksız (JPY, KRW…) ve üç ondalıklı (KWD, BHD…) birimler doğru çevrilir. Para birimine göre
  hesap seçimi Flux POS'un mevcut "para birimi yönlendirmesi" ile.
- Test/canlı: anahtar öneki hesabın Test anahtarıyla aynı olmalı (test hesabında `sk_live_` ile ödeme başlamaz).
- API sürümü sabit: `2024-06-20` (Stripe hesabının varsayılan sürümü yanıtı değiştirmesin).

## Kurulum

1. Flux POS › Hesaplar › Ekle › Stripe. Test modu açık. Stripe panelinden (Test mode) yayımlanabilir anahtar
   (`pk_test_…`) ve gizli anahtar (`sk_test_…` ya da kısıtlı `rk_test_…`).
2. Stripe › Geliştiriciler › Webhook › Uç nokta ekle: hesabın **bildirim adresi** (`/?flxpos=notify&a=<hesap>`,
   hesap kartında yazar). Olaylar: `payment_intent.succeeded`, `payment_intent.processing`,
   `payment_intent.payment_failed`. İmza anahtarını (`whsec_…`) hesaba girin.
3. "Bağlantıyı test et": anahtarı doğrular ve alan adını Apple Pay / Google Pay için kaydeder.
4. Test kartları: `4242 4242 4242 4242` başarılı, `4000 0027 6000 3184` 3D Secure, `4000 0000 0000 0002` ret,
   `4000 0000 0000 9995` bakiye yetersiz (tarih ileri, CVC herhangi).

Kısıtlı anahtar (`rk_`) izinleri: PaymentIntents yaz, Customers yaz, Refunds yaz, PaymentMethods oku,
Payment method domains yaz, Balance oku.

## Aylık bağış (kayıtlı kart)

Flux POS zamanlama yapmaz; tekrarlayan bağışı Flux Donate (ya da abonelikte Flux Commerce) yönetir.

1. İlk ödeme: siparişe `_flxpos_save = yes` meta'sı (ya da `flxpos_charge( array( 'save' => true, … ) )`, ya da
   `flxpos_stripe_save_card` filtresi). Ödeme sayfasında alanın "kart saklanacak" bilgisini göstermesi için
   `flxpos_stripe_checkout_save` filtresi aynı sepette `true` dönmeli. Stripe Customer oluşturulur (giriş yapmış
   kullanıcıda user meta'da tutulur), PaymentIntent `setup_future_usage=off_session`.
2. `flxpos_txn_settled` ile gelen işlemin `data.stripe_customer` ve `data.stripe_pm` değerlerini bağışa kaydedin.
3. Her ay (Donate cron):

```php
$txn = flxpos_charge_saved( array(
	'account'        => $ilk['account'],
	'customer'       => $ilk['data']['stripe_customer'],
	'payment_method' => $ilk['data']['stripe_pm'],
	'amount'         => 150,
	'currency'       => 'USD',
	'description'    => 'Aylık bağış · Aralık',
	'meta'           => array( 'donation' => 12 ),
) );
// $txn['status']: paid | failed | pending. failed + $txn['data']['needs_auth'] = banka 3DS istedi:
// bağışçıya ödeme bağlantısı gönderip normal flxpos_charge() ile yeni ödeme alın.
```

Stripe Billing (Subscriptions) yerine bu yol seçildi: fiyat/plan Stripe'ta değil Flux Donate türünde kalır, iyzico gibi
diğer sağlayıcılara da aynı arayüzle genişletilebilir.

## Qurban Global geçiş planı

Şu an: resmi WooCommerce Stripe eklentisi canlı. Migration yok kuralı: eski siparişler/kayıtlar Flux POS'a taşınmaz.

1. **Hazırlık (canlıya dokunmadan).** Coolify'da sitenin bir kopyası (staging). Flux POS 0.3.0 kur, Stripe hesabını
   aynı Stripe hesabının **test** anahtarlarıyla ekle, test webhook'u staging adresine. Resmi eklenti staging'de kapalı.
2. **Test (staging).** Klasik ve blok ödeme; masaüstü + mobil; 4 test kartı; Apple Pay (Safari) / Google Pay (Chrome);
   USD, EUR, GBP, TRY; tam ve kısmi iade; ödeme sonrası sekmeyi kapat → webhook siparişi işler; köprüyü yenile →
   çift çekim yok; açık/koyu tema. Qurban'ın "kart ilk sırada, alanlar hemen açık" kuralı: Flux POS › Yöntemler'de
   sıralama.
3. **Canlıda paralel kurulum.** Flux POS'u canlıda kur, Stripe hesabını **canlı** anahtarlarla ekle ama Yöntemler'de
   tek kart yöntemi = resmi Stripe kalsın (ödeyen bizimkini görmez). Canlı webhook'u ekle. İki eklenti aynı Stripe
   hesabında çakışmaz: bizim PaymentIntent'lerimiz `flxpos_ref`/`flxpos_order` taşır, `order_id` taşımaz; resmi
   eklentinin webhook'u onları sipariş bulamayıp atlar, bizimki de resmi eklentinin olaylarını atlar.
4. **Geçiş (Ahmet onayıyla).** Yöntemler › tek kart yöntemi = Flux POS. İlk gerçek ödemeyi küçük tutarla dene, iade et.
5. **İzleme (2–4 hafta).** Resmi eklentiyi silme, yalnız yöntemini kapalı tut: eski siparişlerin İade düğmesi çalışmaya
   devam eder. Sorun olursa geri dönüş tek ayar: tek kart yöntemi = resmi Stripe.
6. **Kapanış.** Eski siparişlerin iade süresi geçince resmi eklentiyi devre dışı bırak; eski iadeler Stripe panelinden.

## Sınırlar

- Bulut test ortamından Stripe'a ve js.stripe.com'a erişim yok: sürücü sahte Stripe yanıtlarıyla test edildi
  (`plugins/flx-pos/tests/stripe-driver-test.php`, 39 kontrol). Gerçek test anahtarlarıyla staging testi gerekir.
- Klasik ödemede WooCommerce sepeti güncelleyince (adres/kargo değişimi) ödeme alanları yeniden kurulur, yazılan kart
  silinir (WooCommerce ödeme bölümünü baştan basıyor; resmi eklentide de aynı).
- Stripe panelinden yapılan iade ve itiraz (dispute) Flux POS kaydına yansımaz.
- Ödeme yöntemi kaydetme ("kartlarım") ve "Express Checkout" düğmeleri (ürün/sepet sayfasında Apple Pay) yok.

## Kaynak

Webhook imza doğrulaması, para birimi ondalık listeleri ve kart hata kodu mesajları
[WooCommerce Stripe Payment Gateway](https://github.com/woocommerce/woocommerce-gateway-stripe)'den (GPLv3) uyarlandı.
