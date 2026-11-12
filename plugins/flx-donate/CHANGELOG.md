# Değişiklikler

## 2.27.0
- Flux AI & MCP için salt okunur "Bağış özeti" aracı (`flxb_donation_summary`): seçilen gün ya da aralıkta ödenen bağış sayısı ve tutarı, fidan adedi, yeni bağışçı, türe göre kırılım (fidan, zekat, kurban …), tek seferlik / aylık, dekont onayı bekleyen ve ödeme bekleyen bağışlar (aralıkta ve şu an açık olanlar), iptal / başarısız / iade, aktif aylık bağışlar. `compare` ile bir önceki eş dönem ve fark. Günlük brifing artık bağış verisini okuyabilir.
- Araç kişisel veri döndürmez; diğer para birimleri ayrı listelenir, toplama karışmaz.

## 2.26.1
- Excel'den yükle: bir hücrede alt alta yazılmış adlar (ör. 6 fidana 6 ad) kişi başı bir fidan olarak dağıtılır, bağış formundaki "Başka bir kişi ekle (ayrı sertifika)" ile aynı: ilk ad kalan fidanları alır, diğer her ad 1 fidan. Adlar bağışçı sütunundaysa "Adına" sütunu bağışçı olur. Ad sayısı fidandan fazlaysa fazla adlar ilk sertifikaya yazılır (önizlemede uyarı). Önizlemede adlar satırın altında görünür.
- `flxb_create_order()` satırları `people` ( [ { name, phone, email } ] ) alır.

## 2.26.0
- "Excel'den içe aktar" her sitede açık (önceden yalnız kayıt takibi açıkken görünüyordu): eski fidan / bağış listeleri panelden yüklenir.
- Sütun tanıma: "Fidan Adedi", "Fidan Sayısı" (adet), "Dikim Bölgesi" (iş ortağı), "Ödeme Durumu" (ödeme). Ödeme değerlerinde "Ekimde ödenecek" ödenmedi, "Gönderildi" / "Dekont" ödendi sayılır (önizlemede değiştirilebilir).
- Tarihi boş satırlar için seçenek: üstteki satırın tarihi (varsayılan), satırdaki hedef tarih, seçilen bir gün ya da bugün. Önizleme tablosunda tarih sütunu.
- Takip edilmeyen türlerde (fidan gibi) "Adına" tek ad olarak yazılır (adet kadar tekrarlanmaz).
- Önizlemede takip edilmeyen türlerin "Durum" sütunu ödeme durumunu gösterir (Ödendi / Ödeme bekleniyor).
- Yüklemeden önce özet: kayıt, adet, ödenen / ödeme bekleyen tutar ve referans (personel) başına kayıt-adet-tutar. "Yükle" düğmesi aynı özeti onay penceresinde gösterir; eşleme değişip uygulanmadıysa düğme kapanır.
- Personel eşleştirme: görünen ad, ad + soyad ya da kullanıcı adıyla; tam eşleşme yoksa tek uyan kullanıcı ("Enes" ↔ "Enes Aktaş").
- Aktarım sessiz: aktarım sırasında hiçbir e-posta gitmez, içe aktarılan siparişler için referans personele, bağışçıya ya da adına bağış yapılan kişiye WhatsApp, reklam dönüşümü (Flux Ads) ve analitik olayı gönderilmez. Ortak filtre `flx_silent` ( $silent, $context ): aktarım sürerken ve `created_via = flxb-import` siparişler için true.

## 2.25.1
- Niyet seçimi (akıllı görünüm) düzeldi: seçimden sonra açılır menü + adet tek satırda, hızlı adet düğmeleri (3, 5, 10 …) yalnız seçilen niyetin altında. Önceden tüm niyetlerin düğmeleri yan yana basılıp sayfayı yatay taşırıyordu. Seçim yapılmadan liste düğmesiz, sade.
- Adım adım görünümde sağdaki sabit özet kartı yapışkan site başlığının altında durur (başlık yüksekliği otomatik ölçülür, `--flx-sticky-top`).
- Formun üstündeki fotoğraf bandı: türün görseli yoksa ürün görseli WooCommerce üzerinden alınır (ön ayarların görsel filtresi uygulanır, Qurban Global'de kurban fotoğrafı). Yedek fotoğraf yalnız fidan türünde fidan, diğerlerinde genel bağış görseli; rozet ikonu da yalnız fidanda filiz. Yeni filtre `flxb_hero_image_url`.

## 2.25.0
- Bağış detayı yenilendi. "Kalemler" artık sipariş ekranındaki gibi tek tablo: bağış türü görseli, ad ve altında ayrıntılar (fidan sayısı, sertifika, sertifikadaki ad…), birim fiyat, adet, toplam. Sütun başlığına tıklayınca sıralanır. Altta ara toplam / toplam ve "Ödendi" (yöntem + tarih), bekleyen bağışta "Ödeme bekleniyor", iadede "İade edildi" ve net tutar. Sepetteki bağışlarda tüm kalemler aynı tabloda.
- Ödeme kartı derli toplu: bilgiler ızgarada, sertifikalar ve notlar ayrı satırda. Tablodaki bilgiyi tekrar eden "Seçenek / Adet" satırları kalktı, ödeme sağlayıcısı olarak ödeme yöntemi (ör. Havale / EFT) yazılır, iç WooCommerce referansı gösterilmez.
- WooCommerce siparişi bağlantısı ve iade "⋯" menüsüne taşındı. İade tutarı ortak pencerede sorulur, sonra onay istenir.
- Adı geçen kişileri düzeltme "Adları düzenle" altında.
- İşlemler kartında durum listesi "Yeni durumu seçin" ile başlar (önceden ilk seçenek seçili geliyordu).

## 2.24.1
- Tarayıcının onay/uyarı/giriş pencereleri (confirm, alert, prompt) yerine ortak FluxWP modalı (flx-brand flxDialog): yeni tarz, telefonda alttan açılır, koyu modla uyumlu. Silme gibi geri alınamaz işlemlerde kırmızı onay düğmesi.

## 2.24.0
- Birden fazla fidan başkası adına bağışlanınca "Başka bir kişi ekle (ayrı sertifika)": her kişiye ad, WhatsApp ve e-posta girilir, her biri kendi sertifikasını kendi numarasına/e-postasına alır (kişi başı 1 fidan, kalanı ilk kişinin sertifikasında). İletişimi boş kişinin sertifikası bağışçıya gider. Sipariş ve bağış kaydında "Diğer kişiler".
- Geniş ekranda "Online sertifika" ve "Online + basılı sertifika" kartları yan yana.
- "Başkası adına" seçilince ad alanı "Sertifika nereye gönderilsin?" başlığının altında, WhatsApp ve e-postanın üstünde.
- Tutar düğmeleri görünümü: geniş ekranda "Diğer tutar" alanı düğmelerle aynı satırda, en sağda (10 fidanın yanında). Telefonda düğmelerin altında kalır.
- "Diğer tutar" kartı düğmelerle aynı hizada ve yükseklikte; başlığı normal renkte, yazılan tutarın altında ince çizgi.
- Geniş ekranda sertifika gönderimi: WhatsApp ve e-posta kartları yan yana; "Kendim için / Başkası adına" sekmeleri "Bu bağış kimin için?" başlığıyla aynı satırda, sağda.
- Kendin için bağışta hesabındaki telefon ve e-posta alanlarda salt okunur (kesik çizgili kart) gösterilir; "Başkası adına" seçilince boşalır, düzenlenebilir. "Boş alanlarda hesabınızdaki … kullanılır" açıklaması kaldırıldı.
- Form bölümleri arasındaki boşluklar biraz daraltıldı.

## 2.23.0
- Bağış formu "Tutar düğmeleri" görünümü: adet seçenekleri büyük düğmeler (1, 3, 5, 10 …), "En çok seçilen" rozeti, yanında "Diğer tutar" alanı. Görünüm sekmesinden açılır.
- Serbest tutar: bağışçı kendi tutarını yazar. En düşük tutar panelden girilir (para birimi başına ayrı). Sınır baştan yazmaz; daha düşük tutarla "Devam et" denince alanın altında uyarı çıkar. Yazılan tutar fidan adedine çevrilir (birim fiyatın altı 1 fidan sayılır).
- Para birimi açılır menü oldu, mobilde de "Bağış tutarını seçin" başlığıyla aynı satırda.
- "Tek seferlik bağış / Aylık düzenli bağış" sekmeleri (ayar; aylık ödeme alabilen ödeme yöntemi varsa görünür).
- Tüm form alanları ortak bileşeni kullanır (flx-brand 1.5.0 `flx_field` / `flx_check`): ikon, etiket, alan altı hata mesajı, biçimleme. Kart formu, iletişim alanı ve ödeme sayfası alanları aynı görünümde; yazı dikeyde ortalı ve daha büyük.
- Kartla ödeme ilk sırada, havale sonra (ayar).
- Telefonda alta sabit toplam / düğme çubuğu ayarla kapatılabilir (form ve ödeme sayfası).
- "Kendim için / Başkası adına" telefonda da yan yana.
- "Kendim için" seçiliyken ad alanı yok (ödeme sayfasındaki ad kullanılır); ad yalnız "Başkası adına"da sorulur.
- Sertifika gönderim kartlarında WhatsApp / E-posta ikonu başlığın solunda, alan tam genişlikte; e-posta kutusu telefon kutusu gibi tam çerçeveli; WhatsApp / e-posta ikonları zeminsiz, yalnız ikon.
- Yeni ayarlar: kupon kutusu (yalnız bağış olan sepette kapatılabilir), adres defteri (kapalıyken Hesabım'da "Adresler" de gizlenir).
- Adres düzenleme ekranında "Kapat" ve "Vazgeç", yeni adres formunda "Vazgeç".
- Geliştirici: `flxb_takes_free`, `flxb_min_free` filtreleri; `flxb_look` yeni alanları `flx_settings_schema`'da.

## 2.22.0
- Bağışı ya da düzenli bağışı olan kişi "kullanımda" sayılır (`flx_person_in_use`), Flux CRM silmez; Flux CRM iki kişiyi birleştirince bağışlar ve düzenli bağışlar kalan kişiye taşınır, toplamlar yeniden hesaplanır.
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

