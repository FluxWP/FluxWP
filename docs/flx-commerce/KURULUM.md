# Flux Commerce — kurulum

WooCommerce mağazası için satış paneli, müşteriler, satış raporu, dekont yüklemeli Havale/EFT, sade ödeme sayfası,
ücretsiz kargo çubuğu, Google Merchant feed'i ve WhatsApp/e-posta bildirimleri. Bağışa özgü bir şey içermez;
Flux Donate ile aynı sitede çakışmadan çalışır.

## Gereken
- WordPress 6.4+, PHP 7.4+, **WooCommerce 8.0+** (etkin değilse yalnız uyarı gösterir).
- İsteğe bağlı: **Flux Mail** + WhatsApp bağlantısı (WhatsApp bildirimleri), **Flux Admin** (tasarım ve menü).

## Kurulum
1. `flx-commerce.zip`'i **Eklentiler › Yeni ekle › Eklenti yükle** ile kurun ya da FluxWP Panel'den **Kur**.
2. Sol menüde **Mağaza** açılır (Flux Admin'de "Bağış" grubunun başında). Ödeme bekleyen sipariş sayısı rozet olarak görünür.
3. **Mağaza › Müşteriler › Yeniden oluştur**: eski siparişler 100'lük parçalarla taranır (bir kez). Sonrası kendiliğinden.

## Havale/EFT
1. **WooCommerce › Ayarlar › Ödemeler › Havale/EFT (dekont yüklemeli)** → Açık.
2. IBAN boşsa WooCommerce "Doğrudan banka havalesi"nin ilk hesabı kullanılır. Açıklama biçimi `{kod} {ad}` (önek + sipariş no + ad); önek "Kod öneki"nden.
3. Akış: sipariş **Beklemede** açılır → müşteri teşekkür sayfasında banka bilgilerini kopyalar, dekontu yükler → yöneticiye e-posta (ve numara verildiyse WhatsApp) → sipariş ekranındaki **Havale/EFT** kutusunda **Ödemeyi onayla** → sipariş İşleniyor (sanal ürünlerde Tamamlandı).
4. Dekontlar `wp-content/uploads/flxcm-dekont/` altında, `.htaccess` ile kapalı; yalnız yönetici ve sipariş sahibi açar. **LiteSpeed/nginx**: bu klasörü doğrudan erişime kapatın (nginx `.htaccess` okumaz).
5. Flux Donate kuruluysa ve onun havalesi açıksa bu yöntem gizlenir (iki "Havale" görünmesin). **Mağaza › Ayarlar › Havale/EFT**'den değişir.
6. "Ödenmeyen havaleyi iptal et (gün)": günlük cron (`flxcm_daily`) dekontsuz bekleyenleri iptal eder. Hostinger'da WP-Cron tetiklenmiyorsa sunucu cron'u `wp-cron.php`'yi çağırmalı.

## Ödeme sayfası ve kargo çubuğu
- **Mağaza › Ayarlar › Sepet ve ödeme sayfası**. Şirket/adres 2 gizleme klasik ve blok ödemede, "yalnız sanal ürün varsa adres sorma" klasik ödemede çalışır.
- Ücretsiz kargo tutarı boşsa WooCommerce'in **Ücretsiz kargo** yönteminin "en az tutar"ı kullanılır.
- Mobil sabit "Siparişi ver" FluxWP temasında kapalı (tema zaten yapıyor).

## Ürün feed'i
**Mağaza › Ayarlar › Ürün feed'i** → Feed açık → adresi kopyalayıp Google Merchant Center'a (Ürünler › Feed'ler › Planlı getirme) verin. Adres anahtarlıdır; anahtar ayarlarda saklanır. Bağış ürünleri ve `_flxcm_no_feed` meta'sı olan ürünler girmez.

## Kaldırma
Ayarlar, müşteri tablosu ve önbellekler silinir; siparişlerdeki havale/dekont kayıtları ve dekont dosyaları kalır.
