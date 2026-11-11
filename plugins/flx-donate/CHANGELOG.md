# Değişiklikler

## 2.22.0
- Bağışçılar ortak FluxWP kişi tablosunda (`{prefix}flx_people`, flx-brand 1.3.0): bağışçı numarası = kişi numarası. Aynı kişi Flux CRM, Flux Certificates, Flux Mail ve Flux Chat'te tek kayıt.
- Eski `{prefix}flxb_donors` bir kez taşınır: Flux CRM'in zaten tanıdığı bağışçı (donate / donor:<id> bağı) ya da aynı telefon, sonra e-postalı kişi tekrar kullanılır, yalnız boş alanları dolar; yoksa numara boşsa korunur. Bağış ve düzenli bağış kayıtları yeni numaraları izler, toplamlar yeniden hesaplanır, tam kopyadan sonra eski tablo silinir.
- Yeni bağışçı `flx_person_ensure()` ile açılır (Flux CRM eşleştirmesi çalışır); telefon ortak biçimde (+ olmadan E.164) saklanır, ekranda yine +90 532 … görünür. Bağışçılar listesi yalnız Flux Donate kişilerini gösterir.
- Bağışçı notu ortak kişi notunda (`note`).
- Artık Flux CRM gerekli (`Requires Plugins: woocommerce, flx-crm`).
## 2.21.1
- Kayıtlı kart referansları bir sonraki kayıtta ortak mühürle saklanır. Eski kayıtlar açılır. WordPress tuzları değişirse kartı yeniden bağlayın.
- Telefon biçimi ortak yardımcıyı kullanır. Yazılan numara eskisi gibi +90… olur.
- İçe aktarmada telefon aynı ortak kuralla okunur (`0090…` ve `+90 0532…` yazımları düzelir); tanınmayan numara yazıldığı gibi kalır.
- Bağış sorgulama ve yönetim listeleri ortak önbelleksiz başlıkları kullanır. CDN işareti durur.
- Ödeme ve bağış sorgulama deneme sınırları ortak sayacı kullanır. Adres güvenilen vekilden okunur; e-posta ve telefon sınırları aynı kalır.
- Dekont klasörü doğrudan web erişimine kapalı kalır (Apache 2.2 ve 2.4).
- Bağış, mahsup ve rapor CSV'leri ortak dışa aktarmayı kullanır. Dosyanın içeriği aynı kalır.

## 2.21.0
- EsnekPOS: kart alanları ödeme sayfasında; 3D Secure aynı sayfada pencerede açılır, sayfadan ayrılmadan sonuç gelir. Hata olursa nedeni ve "kartınızdan para çekilmedi" açıkça yazar; hata Öde düğmesinin üstünde de görünür.
- EsnekPOS: kart numarası girilince taksit seçenekleri (kredi kartı, BIN'e göre; taksit farkını bağışçı ya da dernek öder, ayarlanır). Kart alanlarının altında 3D Secure bilgisi.
- EsnekPOS "Telefon Boş Bırakılamaz" hatası: telefon siparişten, hesaptan ya da bağışçı kaydından alınır; hiçbiri yoksa kart formunda telefon istenir.
- Sağ üstteki "Güvenli ödeme" rozeti seçili kart sağlayıcısını yazar (EsnekPOS seçiliyken iyzico yazmaz).
- Görünüm › Ödeme sayfası düzeni: yeni "İki sütun" (varsayılan): adımlar kart görünümünde, boşluklar az; sağda özet, şartlar onayı ve "{tutar} öde" düğmesi (taksit seçilince taksitli toplam). Telefonda tek sütun.
- Giriş yapmış bağışçının e-posta ve telefonu salt okunur gelir (Hesabım'dan değişir); ad boşsa hesaptaki ad yazılır. Misafir "Hesap oluştur" kutusunu işaretleyebilir (şifre bağlantısı e-postayla gider; yalnız e-posta yazılınca görünür).
- Bağış formu: adet seçeneğinin altında hızlı seçim düğmeleri (3, 5, 10, 25, 50; filtre flxb_quick_qty). A düzeni geniş yerleşir, telefonda tek sütun; tek bağış türü varken "Bağış türü" adımı gizlenir. "1Fidan" yazımı "1 fidan" oldu.
- A düzeninde formun üstünde fotoğraflı başlık bandı (bağış türünün görseli, sayfa başlığı; sayfa zaten kapak bloğuyla başlıyorsa çıkmaz). Görünüm'den kapatılabilir.
- Kart ile öde sekmesinin başlığında kart logoları yok; logolar kart alanlarının altında sağda.
- Ödeme sayfası: ad ve soyad en başta, yan yana; "misafir olarak ödüyorsunuz" yazısı kalktı; hesap kutusu "Girdiğim bilgilerle Yeşil Türkiye hesabımı oluştur" der; "Hesap oluştur" kutusunun altındaki boşluk azaldı.
- Taksitler kompakt: "515,00 × 3 = 1.545,00" biçiminde tutar seçeneğin hemen yanında, bir tık küçük yazı, geniş ekranda iki sütun. "Son kullanma" etiketinde AA/YY aynı satırda sağda, küçük. 3D Secure kutusundaki tekrar logolar kaldırıldı.
- "Siparişinize not ekleyin" boşlukları azaldı; Görünüm'den kapatılabilir (Görünüm › "Sipariş notu").
- "Bir sorun mu var? WhatsApp'tan yazın" ayrı, açık yeşil bir kutu; tıklayınca WhatsApp "Merhaba" yazılı açılır (filtre flxb_support_wa_text).
- Fidan ürün görseli artık gerçek fidan fotoğrafı; eski hazır ikon görseli kendiliğinden değişir (elle seçilen görsele dokunulmaz). Görseli olmayan kalemde özet gri yer tutucu yerine fidan ikonu gösterir.

## 2.20.2
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 2.20.1
- İngilizce metinlerde yer tutucuların yanındaki silinmiş boşluklar geri geldi ("of %s goal", "Thank you, {ad}", "Your {aralik} donation of {tutar} is set up" ve 189 metin daha); kaynak, .pot ve 10 dilin .po/.mo/.l10n.php/JSON paketleri güncellendi. Makine çevirilerindeki aynı boşluk hataları da düzeltildi.
- Toplu çeviride bozulan birkaç kelime geri alındı: "Basket" (basketball), "Kurban share", "Healing", "— not set", "%1$d · %2$d sent", "Brings gifts in", kart son kullanma alanının çeviri bağlamı ve "…".
- Türkçe tekil/çoğul metinler (28 adet: "0 ücretli", "1 bekliyor", "%s bağışçı", "%d alan" …) toplu çeviri öncesindeki elle yazılmış Türkçeye döndü.
- Şartlar onay cümlesinde (FR, ES, PT, DE, AR, RU) bağlantı yine "gizlilik politikası" ve "bağış sözleşmesi" kelimelerini sarıyor. Çevirisi eksik "%s more" eklendi.
- Telefon alanı (flx-brand 1.2.1): FluxWP temasında açık modda koyu görünmüyor; koyu renkler yalnız cihaz koyu moddayken (ya da sitenin/adminin koyu modu açıkken) gelir.

## 2.20.0
- Bağış detay sayfası yeniden tasarlandı: üstte tutar ve özet şeridi (durum, bağışçı, referans, ödeme, tür, ne zaman geldiği), solda sipariş, kalemler, ödeme ve kayıtlar, sağda bağışçı, referans ve işlemler kartları. Bağışçı kartında ödenmiş bağış sayısı, toplam, bekleyen ve "Sohbeti aç".
- Referans her yerde görünür: listede referans sütunu, bağışın referansı yoksa bağışçının önceki bağışlarındaki referansı soluk gösterir. Detayda referans kartı (ad, telefon, e-posta), yetkili için düzenleme.
- Yeni API: FLXB_Refs::donor_ref() / donor_refs(), flxb_donation_state().

## 2.19.0
- Bağışlar listesinde satıra tıklayınca bağış detayı açılır (toplu işlem formu tıklamayı yutuyordu). Bağışçı adı da detayı açar. Bağışçı kartına detaydaki addan gidilir.
- Listenin üstünde "Dekont onayı bekleyenler" kutusu: dekont gelen bağışlar sayısıyla, tek tıkla detayda dekont ve Onayla / Reddet.
- "Bekliyor" yerine net durum: dekont geldiyse "Dekont onayı bekliyor", gelmediyse "Ödeme bekleniyor". Durum filtresinde de bu ikisi ayrı seçilir. Detaydaki onay kartı başlığı da aynı ("Dekont onayı bekliyor").
- Varsayılan sıra: bekleyen bağışlar başta, sonra tarihe göre (yeni önce).
- Mobil kart görünümünde uzun durum etiketi tutarı ve Ayrıntılar düğmesini dışarı itmez.

## 2.18.0
- Yeni **Bağışlarım** sayfası (`[flx_bagislarim]`): hesabı olmayan bağışçı e-posta ya da telefonunu girer, tek kullanımlık 6 haneli kodla doğrulanır; bağışlarını (tutar, durum, kalemler) ve sertifikalarını görür, PDF indirir. Havale bekleyen bağışta ödeme bilgileri (IBAN + dekont) bağlantısı. Giriş yapmış kullanıcı kodsuz görür.
- Kod e-postayla (Flux Mail şablonu "Bağış sorgulama kodu"), telefona WhatsApp ya da SMS ile gider. Bağış olsa da olmasa da aynı cevap; kod 10 dk geçerli, 5 hatalı denemede düşer, istek ve günlük gönderim sınırları (e-posta günde 40). Doğrulanan ziyaretçi 30 dk açık kalır.
- Bağışlar › Ayarlar › **Bağış sorgulama** sekmesi: aç/kapa, kanallar, sayfa seçimi, "Bağışlarım sayfasını oluştur". Sistem kontrolü satırı. TR, EN ve 8 dilde çeviri.

## 2.17.4
- Partner settlement and report CSV cells are quoted when they would open as a spreadsheet formula. A real number, including a negative amount, stays a number. The donations export uses the same helper.

## 2.17.3
- Fix recurring preset changes: compare donation metadata through the same current sanitizer used when saving, including card icons, subtitles, form visibility and extension fields.
- Preserve runtime WooCommerce IDs outside the preset comparison; repeated apply retains prices and options without false changes.

## 2.17.2
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Preserve singular/plural message boundaries and printf arguments in English source IDs and all native language packs; prevent count labels from breaking populated screens.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.
- Repair the donation-product count plural message so WooCommerce system checks complete.


## 2.17.1
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 2.17.0
- Seçeneklerin görünümü (Ayarlar › Görünüm, `flxb_look.opt_view`): Liste (eski), Açılır menü (tek satır: seçenek açılır menüde + adet), Önce liste sonra tek satır (ilk seçimde liste animasyonla tek satıra toplanır, formun geri kalanı seçimden sonra açılır).
- "İşlem ücretini de ben karşılamak istiyorum" kutusu (`fee_on`, `fee_checked`, `fee_rates` yöntem başına % + sabit). Formda en yüksek ücret "en fazla" olarak gösterilir; ödeme sayfasında seçilen yönteme göre "İşlem ücreti" satırı eklenir (blok ödeme sayfasında yöntem değişince yeniden hesaplanır). Ücret bağış tutarına girmez; siparişte `_flxb_fee_cover`.
- Tek onay kutusu metni: "Gizlilik politikasını ve bağış sözleşmesini okudum, kabul ediyorum." (bağlantılı; filtre `flxb_terms_text`), blok ve klasik ödeme sayfasında aynı.
- Düzeltme: yeni WooCommerce ödeme yöntemi değişince toplamı ödeme rotasından yeniden hesaplatıyor (`__experimental_calc_totals`); şartlar kontrolü bu istekte hata verip "koşulları kabul edin" uyarısı gösteriyordu.

## 2.16.0
- Yeni Ayarlar › **Görünüm** sekmesi (seçenek `flxb_look`): bağış formu için 4 şablon (Klasik, A · Adım adım + özet, B · İki panel, C · Görselli kartlar), ödeme sayfasındaki ödeme seçenekleri için 4 şablon (Tema, A · Yan yana kartlar, B · Sade liste, C · Sekmeler), ana/vurgu rengi, köşe yuvarlaklığı, bölüm sırası, tür ve seçenek başlıkları. Ön ayarlar `flx_settings_schema` ile varsayılan yazar.
- "Kimler adına" bölümü (vekâletli türler): Kendim için / Başkası adına sekmesi; başkası adına her hisse için ad + telefon kartı, ilk kartta "Hepsine uygula". Hisse telefonları kalemlere (`beneficiary_phone`) kaydedilir.
- Telefon alanları ortak flx-brand telefon elementini kullanır (bayraklı ülke seçici, arama, numara doğrulama, E.164 kaydı).
- Tür kartı alt başlığı ve ikon (koyun, sığır, deve, kalp, hediye), tür meta `subtitle` / `icon`, `i18n.<dil>.subtitle`.
- Filtreler: `flxb_pay_icons` (ödeme yöntemi ikonları), `flxb_icons` (ek ikonlar), `flxb_look_label` (başlıkların ziyaretçi diline çevirisi).

## 2.15.2
- Güvenlik (CodeQL): yönetim adresleri `encodeURI` ile temizlenir; dekont önizlemesi yalnız sitenin kendi adresinden, göreli yolla açılır.

## 2.15.1
- Bağış türü çevirisi (Flux Lang): tür düzenlemede yeni "Çeviriler" kartı; her dil için başlık, kısa açıklama ve seçenek adları. Bağış sayfası, sepet, ödeme sayfası ve `flxb_type_public()` ziyaretçinin dilinde gösterir; kayıtlar varsayılan dildeki metni tutar. Tür meta `i18n` `{ "en": { "title", "description", "options": { "anahtar": "Ad" } } }`, ön ayarlarla taşınır. `FLXB_Types::localize( $t, $lang = null )`.
- Düzeltme: ön ayar yeniden uygulanınca "Array to string conversion" uyarısı (bağlı formlar). Ön ayar türü yeniden uygulanınca seçeneklerin "seçim başına birim" ve rozet değerleri artık korunuyor.

## 2.15.0
- Para birimi seçimi: bağışçı TRY / EUR / USD (ve GBP) arasında seçer. Ayarlar › WooCommerce › "Bağışçının seçebileceği para birimleri"; mağaza para birimi hep sunulur. Kur çevrimi yok: bağış türünde her seçeneğe para birimi başına fiyat girilir (boş = o para biriminde sunulmaz). Serbest tutar için para birimi başına en az tutar ve hazır tutarlar.
- Bağış formunun üstünde "Para birimi" seçici (TL / EUR / USD düğmeleri); birden fazla para birimi açıksa görünür. Kısa kod `[flx_bagis_para_birimi]` aynı seçicinin küçük hâli (tema üst satırı için).
- Seçim `flxb_currency` çerezinde (bir yıl); `?flxb_currency=EUR` ile değişir. Ön yüzde WooCommerce para birimi olur: sepet, ödeme sayfası ve sipariş o para biriminde. Yönetim, cron, webhook ve diğer REST istekleri mağaza para biriminde kalır. LiteSpeed önbelleği para birimine göre ayrı kopya tutar.
- Para birimi değişince seçenekli satırlar yeni fiyatla hesaplanır; serbest tutar ve form satırları kendi para biriminde kalır, değilse sepetten bildirimle çıkar. Mağaza ürünleri yalnız mağaza para biriminde ödenir. Basılı sertifika ücreti yalnız mağaza para biriminde.
- Ortak API (2.1): `flxb_currencies()`, `flxb_current_currency()`, `flxb_set_currency_url( $code, $url = '' )`; kısa kod `[flx_bagis_para_birimi]`; filtreler `flxb_currency_codes`, `flxb_current_currency`. Tür seçeneklerinde `prices` (`{ "EUR": 120 }`), tür meta `min_by_currency`, `presets_by_currency`; ön ayar türlerinde de taşınır.

## 2.14.0
- Bağış ekle: yeni "Durum" alanı. Varsayılan "Ödeme bekleniyor"; ödeme alındıysa "Tamamlandı" seçilir. Önceden eklenen her bağış doğrudan ödendi oluyordu. Bekleyen havale bağışı onay kartına düşer.
- Bağış detayı: yeni "İşlemler" kartı. Durumu değiştir (askıya al / ödendi / iptal / başarısız), çöpe taşı ya da geri al, kalıcı sil (yalnız yönetici). Sepet (tek sipariş) birlikte değişir; WooCommerce siparişi durumu izler (askıya alınca Beklemede, ödendi yapılınca sertifika oluşur). Değişiklik geçmişe yazılır.
- `FLXB_Donations::set_status( $id, $status )`: yöneticinin elle durum değişikliği (ödenmiş bağışı da askıya alabilir).

## 2.13.3
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.


## 2.13.2
- Uygulama manifesti: bağışçı ekranları (özet, bağışlarım, bağış detayı, sertifikalarım) `flxb-donations` kimliğiyle paketin kendi kaydı oldu. Personel için bağış, bağışçı ve abonelik listeleri ile onay ekranı (`flxb-review`) eklendi. Bağış türleri ve ayarlar web görünümünde açılır.
- Yeni okuma uçları `flxb/v1/admin/*` (özet, bağışlar, bağışçılar, abonelikler). Yetki `flx_bagis_view`.

## 2.13.1
- Güvenlik (CodeQL): yönetim ekranında satır bağlantısı, dekont önizlemesi ve CSV adresi yalnız http(s)/göreli adresse açılır. Ödeme sayfasında telefon yer tutucu e-posta kontrolü regex kurmadan yapılır.

## 2.13.0
- Sanal POS altyapısı: WooCommerce'e kurulan her ödeme eklentisi (EsnekPOS, Stripe, resmi iyzico, PayTR vb.) bağış ödeme sayfasında ek ayar olmadan çalışır. Bağışlar › Ayarlar › WooCommerce'te yeni **Ödeme yöntemleri** tablosu her yöntemin neyi desteklediğini gösterir: blok ödeme sayfası, sepetsiz ödeme linki, adressiz ödeme, sunucu doğrulaması, taksit, kart saklama, siteden iade, aylık bağış. Bilgi WooCommerce'in kendisinden (supports(), process_refund, blok kaydı) ve adaptörlerden gelir.
- Blok ödeme sayfası: kendi blok desteği olmayan ve kartı kendi sayfasında alan eklentiler (has_fields = false) ödeme bloğuna Flux Donate tarafından eklenir (ayar "Diğer ödeme eklentilerini blok ödeme sayfasında göster", varsayılan açık). Kendi kart alanları olan ama blok desteği olmayan eklentiler tabloda ve Sistem Kontrolü'nde uyarı olarak görünür.
- Adaptör filtresi `flxb_gateway_adapters` (yetenekler, not, logo) ve `flxb_gateway_caps`. Ayrıntı API.md "Sanal POS altyapısı".
- EsnekPOS adaptörü (resmi modül 1.0.0, gateway `esnekpospos`): ödemeyi Flux Donate yürütür. EsnekPOS ortak ödeme sayfasına sipariş satırlarıyla gider (sepetsiz ödeme linki de çalışır), dönüşte ödeme sunucudan ProcessQuery ile doğrulanmadan sipariş ödendi sayılmaz (modülün kendi dönüş kontrolü boş sorgu gönderiyor ve sahte POST ile kandırılabiliyordu). Modülün her istekte çalışan `init` kancası kaldırılır, Merchant Key alanı şifre alanı olur, test için TEST1234.
- Tek kart yöntemi: ödeme sayfasında yalnız bir kart ile ödeme yöntemi görünür (Havale/EFT yanında). Ayar "Kart ödeme yöntemi": Otomatik (başka bir POS etkinse o, yoksa iyzico), iyzico, belirli bir POS veya hepsi. Gizlenen yöntem tabloda "Ödeme sayfasında gizli" olarak görünür; aylık bağış iyzico ile sürer. Ödeme logoları da aynı kurala uyar (EsnekPOS seçiliyken iyzico logo bandı otomatik gelmez).
- Ödeme yöntemleri kartında "Sitedeki ödeme logoları" kısayolu (Görünüm › Tema Ayarları › Ödeme logoları).
- Sistem Kontrolü: "Diğer ödeme eklentileri" satırı; kart yöntemi olarak üçüncü taraf POS'lar da sayılır.
- Tema FluxWP 0.7.7 ödeme logoları için etkin adaptörlerin logolarını (ör. EsnekPOS) hazır logo olarak sunar.

## 2.12.1
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 2.12.0
- Bağışçı kartı Flux CRM ortak kişi kartını kullanır (Flux CRM kurulu ve kullanıcının CRM yetkisi varsa): başlık, özet kutuları, sekmeli zaman tüneli (Tümü/Ödemeler/Mesajlar/Formlar/Notlar), bilgiler, izinler, etiketler. Kişi tek yerde tutulur; bağışçı CRM kişisine `donate`/`donor:ID` bağlantısıyla bağlanır, yoksa açılırken oluşturulur.
- Karta Flux Donate bölümleri eklenir: başlıkta "Bağışlar" ve "Bağış ekle", ana sütunda düzenli bağışlar, yan sütunda bağış özeti, sertifikalar, KVKK onayı ve bağış notu. E-posta/SMS izni CRM kartında; bu form onlara dokunmaz.
- Flux CRM yoksa klasik bağışçı kartı aynen gösterilir. Kapatmak için `flxb_use_crm_card` filtresi.
- Bağış ekle: `?donor=ID` ile bağışçının adı, telefonu, e-postası ve izinleri hazır gelir.

## 2.11.6
- Adres defteri: yeni adres formunda girilen değerler hata sonrası yeniden doldurulmuyordu (`flxb_` önek uzunluğu), düzeltildi.

## 2.11.5
- Koyu modda açık nane rozetler ("Aktif" vb.) koyu vurgu tonuna döner; yeşilimsi sabit çizgi/zeminler tema tonunda.

## 2.11.4
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

