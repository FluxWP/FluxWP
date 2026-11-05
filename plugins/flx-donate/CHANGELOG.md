# Değişiklikler

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

