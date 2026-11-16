# Değişiklikler

## 0.6.0
- **GraphQL API.** Satış özeti, müşteriler, abonelikler, raporlar, ayarlar, havale kuyruğu ve Hesabım abonelikleri Flux GraphQL üzerinden okunur ve yazılır. Yetkiler mevcut WooCommerce yetkileridir. `flx-graphql` yokken şema kaydı atlanır; REST cevapları aynı kalır.
- **React yönetim ekranı** (`flxcm-store`): Genel bakış, Müşteriler, Abonelikler, Satış raporu, Ayarlar. wp-admin varsayılanı budur; klasik sayfalar kullanıcı tercihiyle durur.
- Ürün feed adresi yalnız mağazayı yönetene, feed açıkken, klasik ekrandaki tam adres olarak verilir.
- Dekont dosya adresi GraphQL'de yalnız dosyayı açabilene (`edit_shop_orders`) dolu döner.
- Kart sağlayıcısının mühürlü `d` bloğu GraphQL, REST ve dışa aktarmada yoktur. Depolama değişmedi.
- `flxCommerceRenewalsRun` yalnız saatlik yenileme turunu (`tick`) çalıştırır. Günlük müşteri özeti ve süresi geçen havale iptali cron'da kalır.
- Misafir dekont yükleme (multipart) ve feed XML'i yerinde durur.

## 0.5.0
- **Kartla otomatik yenileme (Faz 2).** Ortak kart sağlayıcı katmanı: ödeme eklentileri `flxcm_card_providers` ile sağlayıcı kaydeder (Flux Donate 2.31.0 iyzico'yu ekler). İlk sipariş ya da elle ödenen yenileme kart sağlayıcısıyla ödenirse kart (sağlayıcının mühürlü token'ı) aboneliğe yazılır.
- Kartlı abonelikte yenileme siparişi yine dönem bitmeden 7 gün önce açılır ama müşteriye "kartınızdan şu tarihte çekilecek" e-postası gider (yeni şablon `flx-commerce/sub-card-notice`). Dönem bitince kart çekilir, sipariş ödenir, dönem uzar. Çekilemezse ertesi gün ve bir sonraki gün yeniden denenir (`flxcm_card_retry_days`); son denemede de olmazsa ödeme bağlantılı e-posta gider (`flx-commerce/sub-card-failed`) ve ek süre işler. Sağlayıcı cevabı gelmezse deneme sayılmaz, bir saat sonra durum sorulur.
- Hesabım › Aboneliklerim: "… kartınızdan otomatik yenilenir", yaklaşan çekim tarihi, **Kartı kaldır** (sonraki yenilemeler ödeme bağlantısıyla). Yönetim listesinde "Otomatik: kart". Abonelik bitince kart silinir.
- Kart verisi dışarı verilmez: `flxcm_get_subscription()` yalnız `card` (etiket) ve `auto_charge` döner.
- Yeni olay `flxcm_subscription_charge_failed( $sub, $order, $error )`; yardımcılar `flxcm_order_has_subscription()`, `flxcm_cart_has_subscription()`.

## 0.4.1
- **Gizli ürün** (ürün › Genel › Gizli ürün): mağazada, aramada, ilgili ürünlerde, ürün akışında ve site haritasında görünmez; müşteri ürün sayfasını açarsa Mağaza › Ayarlar › Abonelikler'deki adrese (boşsa ana sayfaya) yönlenir. Sepete ekleme bağlantısı (`?add-to-cart=ID&flxcm_period=year`) ve `flxcm_add_subscription_to_cart()` çalışmaya devam eder; ödeme, fatura adresi ve Hesabım normal akışta. Ürün sayfasını (görsel, açıklama) yalnız ürünü düzenleyebilenler görür, üstte "Gizli ürün" notuyla. Sepet ve sipariş satırında ürün bağlantısı çıkmaz.
- Filtre `flxcm_hidden_product_redirect( $url, $product_id )`.

## 0.4.0
- **Abonelikler**: basit ürüne aylık ve/veya yıllık fiyat verilir (ürün › Genel › Abonelik). Ürün sayfasında Aylık / Yıllık seçimi (yıllıkta tasarruf yüzdesi), düğme "Abone ol"; sepette ve siparişte "Ödeme dönemi" satırı. Fatura bilgileri normal ödeme akışından gelir; abonelikte hesap açmak zorunlu.
- İlk sipariş ödenince abonelik başlar (Havale/EFT onayı dahil her yöntem). Dönem bitmeden 7 gün önce yenileme siparişi açılır, müşteriye banka bilgileri ya da ödeme bağlantısıyla e-posta gider; ödenmezse son gün bir hatırlatma daha. Dönem bitince 3 gün ek süre, sonra abonelik biter. Gün sayıları Mağaza › Ayarlar › Abonelikler'de.
- **Hesabım › Abonelikler**: durum, sonraki yenileme, bekleyen yenileme için "Banka bilgileri ve dekont" / "Şimdi öde", iptal (dönem sonuna kadar geçerli), yenilemeyi yeniden aç, "Şimdi yenile", bitmişse "Yeniden başlat".
- **Mağaza › Abonelikler**: etkin sayısı, aylık yinelenen gelir, 30 günde yenilenecek, ödemesi geciken; durum filtreleri, arama; yenileme siparişi aç, dönem sonunda iptal, şimdi bitir, yeniden aç.
- Başka eklentiler için olaylar: `flxcm_subscription_started`, `_renewed`, `_ended`, `_cancelled`, `_past_due`, `_renewal_created`, `_status_changed`; `flxcm_add_subscription_to_cart()` ile sepete ek bilgiyle abonelik. Ayrıntı docs/flx-commerce/API.md.
- Flux Mail şablonları: "Abonelik yenileme hatırlatması", "Abonelik bitti". Sistem kontrolünde "Abonelik yenilemeleri" (son çalışma).

## 0.3.1
- İçe aktarılan eski siparişler (Flux Donate Excel aktarımı) için yöneticiye "yeni sipariş" WhatsApp'ı gitmez (`flx_silent`).

## 0.3.0
- Her müşteri ortak FluxWP kişi tablosunda bir kişi (flx-brand): `commerce / email:` bağı (Flux CRM'in WooCommerce kaynağıyla aynı anahtar), kullanıcı, e-posta ya da telefonla bulunur, yoksa açılır. Müşteri tablosu yalnız sipariş özetini tutar.
## 0.2.12
- Telefon numaraları tüm eklentilerde aynı kuralla okunur: `+90 0532…` yazımındaki fazla sıfır atılır, ülke kodu ve baştaki sıfır olmadan yazılan 10 haneli sabit hat (212…) 90 ile tamamlanır, hane sayısı ya da alan kodu tutmayan Türkiye numarası geçersiz sayılır. Geçersiz numaralı siparişe WhatsApp bildirimi denenmez.

## 0.2.11
- Ticaret listeleri ortak önbelleksiz başlıkları kullanır. Dekont ucunun kendi başlığı durur.

## 0.2.10
- Dekont klasörü doğrudan web erişimine kapalı kalır (Apache 2.2 ve 2.4).

## 0.2.9
- Müşteri ve satış CSV'lerinde formül gibi açılacak hücreler tek tırnakla korunur. Sütunlar, virgül ayırıcı ve Excel BOM aynı kalır.

## 0.2.8
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 0.2.7
- An order status change or refund marks that customer dirty instead of rebuilding their whole history inside the payment request. The row is rebuilt after the response is sent, or by the daily job if the request was cut off. Opening that customer recomputes first.

## 0.2.6
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 0.2.5
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 0.2.4
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.


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
