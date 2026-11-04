# Değişiklikler

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

