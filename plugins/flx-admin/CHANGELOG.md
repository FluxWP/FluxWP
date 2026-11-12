# Changelog

## 1.7.22
- Tarayıcının onay/uyarı/giriş pencereleri (confirm, alert, prompt) yerine ortak FluxWP modalı (flx-brand flxDialog): yeni tarz, telefonda alttan açılır, koyu modla uyumlu. Silme gibi geri alınamaz işlemlerde kırmızı onay düğmesi.

## 1.7.21
- Tüm form alanları dropdown ile aynı görünür: `type` yazılmamış `<input>` (ör. Flux Ads "Harcamayı elle ekle") artık köşeli/koyu kenarlı kalmıyor; text, number, date, time, month, week, textarea ve select aynı kenar, köşe yuvarlaklığı, yükseklik, hover ve odak renginde.
- Yeni ortak tarih seçici (assets/js/datepicker.js): paneldeki her `<input type="date">` tarayıcının yerel alanı yerine dropdown görünümlü düğme + takvim penceresi olur. Asıl input yerinde kalır (name, value, required, min/max, form gönderimi, `change`/`input` olayları aynı). Hafta başlangıcı WordPress ayarından, ay/gün adları kullanıcı dilinden; klavye (oklar, PageUp/Down, Home/End, Esc), "Bugün" ve "Temizle"; telefonda alttan açılan pencere; açık/koyu tema.
- Yerel alanı korumak için input'a `data-flxa-native` ekleyin. React'in yönettiği alanlar ve uygulama ekranları dokunulmadan kalır. Sonradan eklenen alanlar otomatik dönüşür; elle `window.flxaDate.enhance(root)`.

## 1.7.20
- Logosu Flux Admin'de bulunmayan paketlerin sol menü ve sayfa başlığı logosu kırık görünmüyor: paket kendi logosunu yeni `flxa_package_logo_url` filtresiyle verir (stil `gradient` / `pastel` ile birlikte).

## 1.7.19
- Yönetim paneli ve giriş ekranı sekme simgesi sol menü logosuyla aynı sırayı izler: site simgesi, yoksa kurum profili simgesi (ön ayarlar), o da yoksa FluxWP. Önceden site simgesi seçilmemişse hep FluxWP görünüyordu.
- Pano sistem kontrolü listesinde `action_url` olmayan satırlar PHP uyarısı vermiyor.

## 1.7.18
- PWA manifestosu ve oturum kontrolü ortak önbelleksiz başlıkları kullanır. Service worker'ın bir günlük önbelleği durur.

## 1.7.17
- Sol menünün başındaki ve giriş ekranındaki logo artık sitenin kendi simgesi: önce Flux Admin ayarlarındaki logo, sonra kurum profili simgesi (ön ayarlar doldurur: Yeşil Türkiye, Qurban Global…), sonra WordPress site simgesi; hiçbiri yoksa FluxWP işareti. Yeni filtre `flxa_logo_src`.

## 1.7.16
- Koyu modda WordPress güncelleme çıktısı (Eklentileri/Temaları/Çevirileri güncelle) açık zeminde kalıyordu: iframe ekranları artık yönetim temasının rengini (açık/koyu) ve yazı tipini kullanıyor. Eklenti "Ayrıntılar" penceresi de koyu temaya uydu.
- Renk değişkenleri ayrı dosyada (assets/css/tokens.css); admin.css ve yeni iframe.css bunu kullanır.

## 1.7.15
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 1.7.14
- PWA sign-in checks pause while the tab is hidden and run immediately when it returns. The two-minute watch starts at 2 s and steps out to 15 s when the answer does not change. Errors and HTTP 429 back off exponentially up to 60 s, with one request in flight.

## 1.7.13
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Preserve singular/plural message boundaries and printf arguments in English source IDs and all native language packs; prevent count labels from breaking populated screens.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 1.7.12
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 1.7.11
- Qurban Global Preset logosu eklendi (degrade + pastel).

## 1.7.10
- Kullanılmayan bir paket logosu kaldırıldı.

## 1.7.9
- Çağrı merkezi artık tüm kanalların (WhatsApp, site sohbeti…) tek gelen kutusu: uygulama değiştiricide, mobil alt çubukta, hızlı işlemlerde ve PWA kısayolunda "WhatsApp" yerine "Mesajlar"; panel kutucuğu "Okunmamış mesaj".

## 1.7.8
- Sol menüyü daralt/genişlet düğmesi 783–960 piksel ekranlarda da görünür. Düğmenin adı ve erişilebilir durumu açık/kapalı menüyü izler; uygulama ekranlarında genişletme otomatik daralmaya takılmaz.
- Dar menüde grup ve eklenti alt bağlantıları tek bir ortak açılır panelde gösterilir. Simge değişince panelin başlığı, bağlantıları ve konumu güncellenir; WordPress’in ayrı alt menü kartları üst üste açılmaz.
- Açılır panel fare geçişini, klavyeyle dolaşmayı ve Escape ile simgeye dönmeyi destekler; uzun listeler panel içinde kayar.

## 1.7.7
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.


## 1.7.6
- Giriş ekranı: "Beni hatırla" işaretlenince artık görünüyor. WordPress 7.x işaretli kutuyu tema renginde zemin + beyaz tik olarak çiziyor; giriş stilimizdeki kutu zemini bu zemini eziyordu, beyaz tik beyaz zeminde kayboluyor ve kutu tıklanmıyor gibi görünüyordu. İşaretli kutu panel rengini alıyor; odak halkası ve yanındaki "?" yardım düğmesi de panel renklerine uyuyor.

## 1.7.5
- Metinler: MCP sunucusu artık Flux AI & MCP eklentisinde; "Flux SEO & MCP" ve "Flux AI ›" adresleri "Flux AI & MCP" oldu.

## 1.7.4
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 1.7.3
- Kaldırılan Flux Updater (flx-updater) için menü grubu, ikon, rozet ve sayfa başlığı kayıtları silindi; güncelleme menüsü birleştirmesi yalnız FluxWP Panel'e (flx-setup) bakar.

## 1.7.2
- Sol menü: üst (logo + arama) ve alt (kullanıcı) yerinde sabit, yalnız orta kayar. 1.7.0'daki geçiş efekti kaldırıldı; kayan kartlar arama kutusunun altında yarı saydam görünmüyor, net kesiliyor. Menünün sonuna gelince kaydırma sayfaya geçmiyor ve Mac/iOS esnemesi yok.

## 1.7.1
- Koyu modda sayfa yenilenince eklenti kutularının önce beyaz görünüp sonra koyulaşması giderildi: koyu tema koruması artık sayfa yüklendikten sonra değil, ilk boyamadan önce çalışır (`<head>` içeriği koruma bitene kadar gizler). JS ile sonradan çizilen veya açılan kutular da bir sonraki boyamadan önce koyulaşır.
- Uygulama ekranlarında (Görevler, Çağrı Merkezi, Stüdyo, Sosyal) JS yüklenene kadar zemin açık görünmez.

## 1.7.0
- Tema düğmesi: tıklama artık her seferinde açık ↔ koyu geçer. Eski otomatik → açık → koyu döngüsünde cihaz koyu temadayken üç tıklamanın ikisi görünürde hiçbir şey değiştirmiyordu. "Sistem (cihaza göre)" düğmenin menüsünden seçilir (Açık / Koyu / Sistem, seçili olan işaretli). Dokunmatik ekranda ilk dokunuş menüyü açar.
- Tema seçimi diğer açık sekmelere ve geri tuşuyla dönülen sayfaya da uygulanır; kaydedilemezse uyarı çıkar.
- Sol menü kaydırılınca öğeler arama kutusunun altında keskin kesilmiyor, yumuşak geçişle kayboluyor (alt kısımda da).
- Koyu tema koruması (üçüncü taraf ekranlar): açık gri/beyaz ayırıcı çizgiler koyu çizgiye, siyah radyo/onay kutusu/çerçeve kenarlıkları görünür renge, eklenti giriş alanlarındaki koyu yazı açığa çevrilir. SVG grafiklerde beyaz zemin şeffaf, ızgara çizgileri ve koyu yazılar okunur; koyu gri yazı biçimli logolar (ör. Site Kit) metin rengine çekilir.
- Google Site Kit: yazı tipi panelle aynı (Figtree); koyu temada Material radyo, onay kutusu, metin alanı, açılır liste ve menüler panel renklerinde.

## 1.6.13
- Telefon yatayken dar menü sağda (iPhone'da çentik/kamera çoğu zaman solda kalır). Çentik hangi taraftaysa safe-area boşluğu bırakılır; alt menü kartları ve ipuçları sola açılır; "AI'ya sor" düğmesi menünün soluna kayar.

## 1.6.12
- Favicon yenilendi: FluxWP işareti beyaz kontur ve ince gölge çizgisiyle, tam kare zemin yok (SVG, favicon.ico, 32 px PNG). Uygulama simgeleri değişmedi.

## 1.6.11
- Ortak sayfa başlığı (telefon): tek düğmeli sayfalarda başlık solda, düğme sağda aynı satırda (%50 / %50); düğme yazısı ve altındaki durum metni sağa hizalı, gerekirse alt satıra kayar.
- Ön ayar paketlerinin yeni logoları (menü ve sayfa başlığı için).

## 1.6.10
- Uygulama simgesi (iOS ana ekran, PWA): içteki FluxWP işareti büyütüldü. Simge adreslerine sürüm eklendi, cihazlar yenisini alır.

## 1.6.9
- Flux Setup'ın sayfa başlığındaki adı "FluxWP Panel".
- Ortak sayfa başlığı telefonda: logo başlığın solunda kalır (ayrı satıra düşmez), açıklama en çok 2 satır. Başlığa taşınan uyarılar başlığın altına alınır.
- Menü düzenleyici telefonda taşmıyor: ad ilk satırda, düğmeler alt satırda; "Görünür" düğmesi yalnız simge.
- Telefonda yan menü: dokunulan öğede gri hover/odak rengi takılı kalmıyor (yalnız geçerli sayfa vurgulu); menü açıkken "AI'ya sor" düğmesi menünün üstüne binmiyor.

## 1.6.8
- Favicon: site simgesi seçilmemişse yönetim paneli, giriş ekranı ve (Flux SEO yoksa) ön yüz yeni FluxWP simgesini gösterir (SVG, açık/koyu uyumlu + PNG). Site simgesi (Görünüm › Özelleştir › Site kimliği) seçildiyse o kullanılır. PWA simgeleri FluxWP simgesiyle yenilendi. Filtre `flxa_favicon`.

## 1.6.7
- Flux Setup 1.0 (Flux Updater ile birleşti) için: `flx-setup` menüsü Güncellemeler'in yerini alır (tek başına kart, rozet, WordPress güncellemeleri alt menüsü, logo, sayfa başlığı). Eski `flx-updater` menüsü de tanınmaya devam eder. `flxa_standalone_items` varsayılanı `flx-setup`, `flx-updater`.

## 1.6.6
- Güncellemeler (Flux Updater) sol menüde artık hızlı erişim kutucuklarının hemen altında, grupsuz ve başlıksız kendi kartında duruyor (Sistem grubundan çıktı). Daraltılmış menüde de kutucukların altında tek simge. Kişi öğeyi sabitler ya da "Diğer"e taşırsa o tercih geçerli. Filtre `flxa_standalone_items` (varsayılan `flx-updater`).

## 1.6.5
- Düzeltme: genel bağlantı rengi artık eklentilerin bağlantı-düğmelerini ("Yeni menü" gibi `<a>` düğmeler, sekmeler) ezmiyor; koyu düğmede koyu yazı sorunu giderildi. Düğme yazı rengi (`--flxa-on-accent`) vurgu rengine göre otomatik (açık vurguda koyu yazı), `flxa_on_color()`. Koyu tema koruması açık rozet/düğme üstündeki koyu yazıyı artık açık renge çevirmiyor.

## 1.6.4
- Ortak sayfa başlığı düzeni: logo + başlık + açıklama solda, aksiyon butonları sağda sabit; butonun yanındaki durum metni (ör. "Son kontrol") butonun altına alınır. Dar ekranda aksiyonlar alta iner.

## 1.6.3
- FluxWP paket sayfalarında ortak başlık: başlıkta logo yoksa paketin logosu eklenir, açıklama satırı yoksa paketin kısa açıklaması yazılır (Flux Backup'taki gibi). Uygulama ekranları (WhatsApp, Görevler, Stüdyo, Sosyal, TV Kumandası) dokunulmaz. Filtreler `flxa_page_heads`, `flxa_package_heads`, `flxa_page_head_skip`.
- Üçüncü parti eklentilerin beyaz SVG menü simgeleri (Hostinger, Ödemeler vb.) açık temada görünmüyordu; artık maske olarak çizgi simgeler gibi boyanır, iki temada da okunur.

## 1.6.2
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 1.6.1
- Düzeltme: koyu mod rengi tanımlamayan temalarda (theme.json `custom.dark.primary` yok) "Array to string conversion" uyarısı (includes/helpers.php). WordPress bu durumda tüm ayar dizisini döndürüyordu; artık yalnız metin değeri kullanılıyor.

## 1.6.0
- Yeni paket logoları (FluxWP ikon seti): tutarlı çizgi ikonlar, 5 renk grubu (yönetim, bakım, iletişim, içerik, ticaret). Eksik olan Analytics, App, Lang, Migrate, QR Menu, Roles, Setup, Social, Speed, Studio, Staff, TV logoları eklendi.
- Görünüm › Paket logoları: Degrade (varsayılan) ya da Pastel. Sol menü, Flux Updater ve FluxWP App bu seçimi izler. Filtre `flx_logo_style`, yardımcılar `flxa_logo_style()` ve `flxa_logo_file_url()`.

## 1.5.0
- Vurgu rengi varsayılan olarak sitenin tema rengini alıyor (FluxWP temasında indigo; ön ayar ya da Görünüm › Düzenleyici › Stiller değiştirdiyse o). Ayarlarda temanın rengi seçilirse saklanmaz, tema değişince panel de değişir. Filtre `flxa_default_accent`.
- Arka plan, kart, çizgi ve yazı tonları vurgu renginden türetiliyor (yeşilimsi sabit tonlar kaldırıldı); giriş ekranı, uygulama değiştirici ve PWA sayfaları da.

## 1.4.1
- Daraltılmış menüde her grubun kendi simgesi var (İletişim zarf, Bağış kalp, İçerik sayfa, Büyüme grafik, Ekranlar kutu, Sistem dişli). Kayıtlı rol düzenlerinde de grup adından tahmin ediliyor, olmazsa ilk öğenin simgesi kullanılıyor (filtre `flxa_group_icon`). Önceden özel düzenlerde hepsi "•••" görünüyordu.
- Açılan grup listesi, fare listenin ya da simgenin üstündeyken artık kapanmıyor.
- Üzerine gelme rengi koyu gri yerine çok açık yeşil ve yumuşak geçişli. WordPress'in soldaki koyu çizgisi kaldırıldı.
- Seçili alt menü öğesi açık zeminli ve sağında › işareti var.
- Grup başlıkları kalın değil.
- "Diğer" grubunda (elle düzenlenmemiş menüde) önce bizim eklentilerimiz, sonra diğerleri alfabetik sırada.
- "Menüyü düzenle" artık altta sabit değil, menü listesinin sonunda duruyor.
- Sol alttaki tema düğmesi kaldırıldı (üst çubukta duruyor), yerinde çıkış yap simgesi var.
- Sayfa geçişinde göz kırpma giderildi: sol menü ilk karede son hâliyle (kartlar, gruplar, sabitlenenler) boyanıyor, üst çubuk JS çalışmadan önce iki satıra taşmıyor. Yalnız içerik alanı değişiyor.
- Açık sayfanın alt menüsü ve o sayfanın grubu da elle kapatılabiliyor; tercih hatırlanıyor.
- Ekran daraltılınca (783–960 px ya da daraltılmış menü) sabitlenenlerin alt menüleri üst üste açılmıyor; yalnız üstüne gelinen öğenin listesi açılıyor.
- Sol üstte yalnız kurumun yaprak ikonu görünüyor. Ayarlar › Yönetim teması › Logo seç ile değiştirilebilir (filtre `flxa_logo_url`). Site simgesi artık kullanılmıyor.

## 1.4.0
- Sol menü yeniden tasarlandı (C tasarımı). En sık kullanılan sayfalar üstte kutucuk: varsayılan Başlangıç, Çağrı Merkezi, Görevler ve Bağışlar. Kişi sağ tıkla sabitleyip kaldırabilir, hiç dokunmadıysa varsayılanlar gelir (filtre `flxa_default_pins`). Açık sayfanın alt menüsü varsa o kutucuk tam satıra geçer ve alt menüsünü gösterir.
- Gruplar beyaz kartlar, her öğenin renkli simge kutusu var. Kapalı gruplar tek satır (ad + öğe sayısı). Yeni varsayılan gruplar: İletişim, Bağış, İçerik açık; Büyüme, Ekranlar ve Sistem kapalı (filtre `flxa_default_groups`). Açık sayfa kapalı bir grubun içindeyse grup açılır, menü o öğeye kayar.
- Tekrarlar birleşti: Flux Updater varsa Başlangıç altındaki "Güncellemeler" onun altına "WordPress güncellemeleri" olarak taşınır. Site Analitik varsa WooCommerce "Analiz" ana menüsü kalkar, raporları Site Analitik altına "Mağaza: …" olarak girer (filtre `flxa_merge_duplicates`).
- Altta kişi kartı (fotoğraf, ad, rol) ve tek tema düğmesi: tıkladıkça otomatik → açık → koyu.
- Daralt düğmesi sağ üstte, `[` tuşu da daraltır/genişletir. Daraltılmış menüde sabitlenenler simge, her grup tek simge; üstüne gelince öğeleri yanda açılır. Açılan liste simgesine okla bağlanır, simge vurgulanır.
- Kayıtlı rol menü düzeni olan sitelerde gruplar o düzende kalır; yeni gruplar için Menüyü düzenle'de varsayılana dönülebilir.

## 1.3.3
- Flux Speed kuruluysa Sistem kontrolü orada açılıyor (Performans › Sistem Kontrolü). Ayarlar › Yönetim teması'ndaki "Sistem kontrolü" sekmesi gizleniyor, eski adres oraya yönlendiriliyor. Başlangıç kutusu ve aracındaki bağlantılar zaten `flx_system_checks_page_url` filtresiyle oraya gidiyor. Flux Speed yoksa her şey eskisi gibi çalışıyor.

## 1.3.2
- Tam sayfa açılan detay ekranlarında (ör. Görev'de toplantı detayı) sol menü ve üst çubuk artık kararmıyor. İçerik alanını opak biçimde tümüyle kaplayan bir modalın perdesi yoktur, bu yüzden sayfa gibi sayılıyor. Perdeli modallarda (ör. Stüdyo "Alanı düzenle") karartma sürüyor.

## 1.3.1
- Modallar: bir eklentinin modalı açıkken onun perdesi sol menüyü ve üst çubuğu örtmüyorsa Flux Admin onları da karartıyor ve tıklanamaz yapıyor. Modal artık menüye yapışık durmuyor, sol menünün kenarındaki ince açık çizgi de kayboluyor. Modallar `aria-modal="true"` ya da `data-flxa-modal` ile tanınıyor, `data-flxa-nodim` ile çıkılabiliyor. Elle bildirmek için `FLXA.modal(true|false)`.
- Güvenli alan: `--flxa-safe-top/right/bottom/left` CSS değişkenleri, `FLXA.safeArea()` ve `flxa:safearea` olayı. Üst çubuk, sol menü, Flux AI paneli ve mobil alt bar bu boşluklarla bildiriliyor, eklentiler tam ekran sayfa ve modallarını bunlara göre yerleştiriyor. Sözleşme: docs/flx-admin/GUVENLI-ALAN.md.

## 1.3.0
- Menü düzenleyici: her menü öğesinde "Nerede görünsün" düğmeleri (Web / Mobil / Masaüstü). Web kapalı öğe panelin sol menüsünde görünmez ama uygulamada görünür; Mobil/Masaüstü kapalı öğe o uygulamada görünmez. Düğmeler yalnız Flux App kuruluysa çıkar (filtre `flxa_menu_platforms`; okumak için `flxa_menu_platform_off( $user )`).

## 1.2.1
- Daraltılmış sol menü (Görevler / WhatsApp ekranındaki ince şerit ve dar ekran): alt menüler bazen hepsi birden açık kalıp üst üste binen kartlar gibi görünüyor, alt başlıklar bir sonraki kartın altında kesiliyordu. Artık aynı anda yalnız üzerine gelinen öğenin alt menüsü açılır; fare menüden çıkınca hepsi kapanır.

## 1.2.0
- Ortak liste kütüphanesi (paketler için): filtre çubuğu ve sayfalama tek yerden. Arama tek satırda, yanında "Filtreler" düğmesi; altında 2 temel filtre, diğerleri düğmeye basınca açılan panelde (seçili ek filtre sayısı düğmede rozet, panel açık/kapalı durumu hatırlanır). Filtre seçince liste kendiliğinden yenilenir, "Filtrele" düğmesi gerekmez; boş alanlar adrese yazılmaz; filtre varken "Temizle".
- Sayfalama: "21–40 / 160", telefonda büyük dokunma alanlı sayfa düğmeleri ve sayfa başı kayıt seçimi (10 / 20 / 50 / 100, filtre `flxa_list_per_page_options`); seçim liste başına kullanıcıya kaydedilir.
- Paketler `flxa_list_form_attrs()`, `flxa_list_per_page()`, `flxa_list_pagination()` ile kullanır; Flux Admin yoksa kendi eski çıktılarına düşer. Sözleşme: docs/flx-admin/LISTE-API.md. Paketlerin geçişi kendi sürümlerinde yapılır (ilk Flux Certificates).

## 1.1.11
- Sayfa geçişinde beyaz ekran kalktı: panel sayfası sunucuda tamamlanınca tek seferde gönderilir. Yeni sayfa gelene kadar eski sayfa, sol menü, üst çubuk ve ilerleme çubuğu ekranda kalır; sonra içerik yumuşakça değişir, menüler yerinden oynamaz. Önceden yarım gelen sayfanın boş içerik alanı bir an beyaz görünüyordu. Güncelleme ve içe aktarma ekranları hariç (ilerlemeyi satır satır gösterirler); filtre `flxa_hold_paint`.

## 1.1.10
- Mobil yan menü: alt menüsü olan bir öğenin adına (ör. Güncellemeler, Yazılar) dokununca doğrudan o sayfaya gidilir. Önceden WordPress ilk dokunuşu yutup alt menüyü açmaya çalışıyordu; sayfa açılmıyordu. Alt menüyü sağdaki ok açar, dokunma alanı büyütüldü (48×44).
- Alt menülerde "Diğer" yalnız en az iki az kullanılan öğe varsa oluşur; tek öğe olduğu yerde görünür.
- Geniş tablolar (ör. Güncellemeler'deki paket listesi): sığmayan tablo kendi kutusunda yatay kayar, sayfa telefonda yana kaymaz. WordPress'in liste tabloları ve ayar tabloları olduğu gibi kalır.
- Flux AI menüsünde paket logosu görünür (assets/logos/flx-ai.svg).
- Kalıcı oturum (1.1.6) artık yalnız uygulamadan (PWA) yapılan girişlerde verilir. Tarayıcıdaki girişler eskisi gibi tarayıcı kapanınca düşer. Önceden yönetici hep oturumlu kaldığı için site LiteSpeed önbelleği olmadan, yavaş açılıyordu.

## 1.1.9
- Açılır listeler (select): başka bir eklentinin CSS'i `background` kısayoluyla arka planı sıfırladığında ok simgesi kutunun içini dolduruyordu (ör. Çağrı Merkezi > Ayarlar > Bağlantı bilgisi). Ok artık her zaman tek ve sağda; çoklu seçim kutularında ok yok.
- Flux Donate (ortak sistem kontrolü kütüphanesi) kurulu değilken Başlangıç ekranı hata veriyordu (`flx_system_checks_summary()` tanımsız). Özet artık kütüphane olmadan da hesaplanır.
- Mobil: klavye açıkken alt bar gizlenir (yazı alanına odaklanınca ya da görünür alan küçülünce), kapanınca geri gelir. WhatsApp ekranında klavye açıkken uygulamanın kendi görünür alan ayarı geçerli; mesaj kutusu doğrudan klavyenin üstünde.

## 1.1.8
- Bağış sayıları: bir WooCommerce siparişi (sepette birden çok bağış satırı) tek bağış sayılır. "Onay bekleyen" sayacı (Flux Donate 2.0.9+ varsa flxb_pending_count()), Panel'deki bugün / bu ay adetleri ve Bağışlar modülünün son bağışlar listesi (sipariş tek satır, toplam tutar, "N kalem"). Tutar toplamları değişmedi.

## 1.1.7
- Uygulama (PWA) açılışı: simgeye basınca önbellekteki hafif açılış ekranı (logo + ilerleme çubuğu) anında görünür, panel arkada yüklenir. Service worker navigation preload ile sayfa isteği beklemeden yola çıkar; açılış adresine gelen boş preload isteği sunucuda paneli ikinci kez oluşturmaz.

## 1.1.6
- Mobil yan menü: WhatsApp ekranında menü uygulamanın arkasında açılıyordu (görünmüyordu); artık her ekranda önde. "Daha" ve ☰ ilk dokunuşta açılır/kapanır, Esc ve sola kaydırma kapatır. Açılış efekti yenilendi (yumuşak kayma, öğeler sırayla belirir, arka plan bulanıklaşır).
- Uygulama (PWA): Google ile giriş sonrası tekrar giriş sayfasına düşme döngüsü düzeltildi. Uygulama kapsamı sitenin köküne alındı (giriş sayfası uygulama içinde açılır); uygulamadaki giriş sayfası oturum açılınca kendiliğinden panele geçer. Girişler kalıcı çerezle yapılır (uygulama kapanınca oturum düşmez).
- Sayfa yüklenirken en üstte ince ilerleme çubuğu.
- WhatsApp / Görevler ekranında (masaüstü) sol menüyü genişletme / daraltma düğmesi; tercih bu ekranlar için hatırlanır.
- Alt bar ve sol menü WhatsApp / Görevler ekranında da panel yazı tipiyle; alt bar her ekranda birebir aynı.

## 1.1.5
- Masaüstü WhatsApp / Görevler ekranı: uygulama üst barın altından başlayıp ekranın altında biter. Önceden üst bar kadar (32 px) aşağı taşıyordu; mesaj kutusu ve modalların alt kısmı (Kaydet) kesiliyordu.

## 1.1.4
- Mobil: bir modal / alt sayfa açıkken (Flux Tasks "Yeni görev", WhatsApp pencereleri, WordPress ortam penceresi vb.) alt bar arkaya alınır; modal ve Kaydet / Oluştur düğmeleri alt barın önünde görünür.

## 1.1.3
- Sayfa geçişi: üst bar, sol menü ve mobil alt bar geçişte yerinde kalır, yalnız içerik değişir. Sol menü kaydırma yerini korur (aşağıdaki bir sayfaya geçince menü başa dönmez), kapalı gruplar ilk boyamada kapalı gelir (açılıp kapanma zıplaması yok).

## 1.1.2
- Daraltılmış sol menü: kapalı gruplar ("Diğer" vb.) artık tüm öğelerini listelemez, tek "•••" simgesi olarak durur. Üzerine gelince (veya tıklayınca) grubun öğeleri simge ve adlarıyla sağda liste olarak açılır.
- Sol menüde üstte sabit kalan logo/arama alanının arka planı artık saydam değil; kaydırılan öğeler altından görünmüyor (alttaki sabit alanla aynı).
- Mobil: panel artık iPhone alt çizgi / çentik alanını hesaba katıyor (viewport-fit=cover). Alttaki sahte boşluk gitti, alt bar her ekranda WhatsApp ekranındaki gibi alttan hafif boşluklu; sol menünün altındaki sabit alanın altında saydam şerit kalmıyor.
- Mobil: yanlışlıkla yakınlaştırma kapalı (iki parmak ve çift dokunma).
- Arama: yazarken her tuşta değil, yazma durunca aranıyor (debounce); Enter beklemeden sonucu açar. Arama penceresinde kapat (✕) düğmesi var, mobilde de.

## 1.1.1
- Sol menüde her eklentinin her zaman simgesi var: FluxWP paketleri kendi renkli logolarıyla (Bağış, WhatsApp, Görev, Sertifika, Form, İletişim, SEO, Güncelleyici, Yedek), simgesi olmayan eklentilere adından tahmin edilen çizgi simge, o da yoksa baş harf rozeti.
- Sol menü: üstte logo + arama, altta "Menüyü düzenle" + kullanıcı sabit; aradaki liste kayar (daraltılmışken de). Daraltılmış menüde simgenin üzerine gelince adı sağda ipucu olarak çıkar, alt menüler kaydırılmış listede de doğru yerde açılır.
- Açık alt menünün arka planı WordPress renk şemasından (Modern, Mavi…) bağımsız, hep temanın renginde.
- Üst bar: sığmayan öğeler "…" düğmesinin altına toplanır; sağdaki arama, tema ve hesap hep görünür, alt satıra kaymaz.
- Koyu tema: eklentilerin sabit beyaz kutuları ve koyu yazıları otomatik koyulaştırılır / açılır (Flux Updater kartları, bildirimler vb.). WooCommerce ekranları artık koyu temadan hariç değil (eski varsayılan otomatik güncellenir); yalnız blok düzenleyici hariç.
- WhatsApp ve Görevler: üst bar görünür kalır; sol menü normal daralma animasyonuyla simgelere iner (özel sayfa animasyonu yok). Daralt düğmesi de aynı animasyonu kullanır.
- WhatsApp ve Görevler panelin açık/koyu temasını izler; ikisi hep aynı temada.
- Alt menülerde az kullanılanlar (Ayarlar, Şablonlar, Kuyruk, Kayıtlar, Sağlayıcılar, Sistem kontrolü…) sonda kapalı "Diğer" altında. Sağ tıkla herhangi bir alt menü öğesi "Diğer"e taşınır ya da yerine alınır (kişiye özel). Açık sayfa hep görünür.
- Logo, menü ve hesap bağlantılarında WordPress renk şemasının mavi/kırmızı hover rengi çıkmaz (Modern, Mavi, Gece yarısı… hepsinde denendi).
- Ekran ayarları / Yardım başlık satırının sağında normal düğme; Başlangıç'ta Modülleri düzenle'nin yanında.
- Başlangıç kutuları kapalıyken başlık dikeyde ortada.
- Telefon: hızlı işlemler kaydırmasız, 3 sütun kart (yazı iki satıra iner); sağ ve sol boşluk eşit 12 px; üst bar simgeleri okunur, sığmayanlar "…" altında yazıyla; Ekran ayarları / Yardım gizli.
- Telefon: WhatsApp ve Görevler'de panelin alt barı hep görünür, "Daha" yan paneli açar (önceden uygulama ekranlarında açılmıyordu).
- Form alanlarında kalın odak halkası kaldırıldı: odakta yalnız kenar çizgisi vurgu rengine döner. Komut paleti arama kutusunda çerçeve yok. Bağlantı ve düğmelerde halka yalnız klavyeyle gezinirken.

## 1.1.0
- Sol üst: logo ve site adı yan yana; yanında menüyü simgelere daraltma düğmesi.
- Sol menü genişliği kenarından sürüklenerek ayarlanır (200–420 px, kişiye özel; çift tık varsayılan). 150 px altına çekilince simgelere daralır.
- Üstteki Panel / WhatsApp / Görevler sekmeleri kaldırıldı; hepsi normal menü öğesi.
- Grup başlıkları tıklayınca açılır/kapanır (kişiye özel hatırlanır, açık sayfa olan grup hep açık). Az kullanılanlar "Diğer" altında kapalı başlar; rol düzeninde her grup için "Kapalı başlasın".
- Sağ tık (telefonda uzun basma): Üste sabitle / Sabitlemeyi kaldır, Diğer'e taşı / Yerine geri al, sabitlenenlerde yukarı/aşağı, yeni sekmede aç, rol menülerini düzenle. Sabitlenenler sürükle-bırakla sıralanır. Kişiye özel, sayfa yenilenmeden uygulanır.
- Rol menü düzenleyicisinde "Erişimi engelle": o menü rolden gerçekten kapanır, adresle açılırsa 403. Yönetici rolünde ve Başlangıç/Profil'de engellenemez.
- Yeni Ayarlar › Yetkiler: rol × yetki tablosu (İçerik, Site, WooCommerce, FluxWP eklentileri otomatik), aç/kapat ile gerçek WordPress yetkisi değişir; ilk değişiklikten önce yedek alınır, "Yedeği geri yükle". Yönetici sütunu salt okunur.
- Yeni Ayarlar › Görünüm: vurgu rengi, açık ve koyu tema için arka plan / sol menü / kart / yazı renkleri, yazı tipi (Figtree, sistem, özel), yazı boyutu, köşe yuvarlaklığı, yoğunluk.
- Form alanları modernleşti (odak ve üzerine gelince vurgu, yuvarlak köşe, vurgu renkli onay kutuları); WordPress sınıfları değişmedi.
- Başlangıç: üstte hızlı işlem çubuğu (Görev oluştur, WhatsApp, Sayfa ekle, Yazı yaz, Medya yükle + ayardan ekler). Görevler modülü: kapsam (bana atanan / oluşturduğum / tümü), liste ve grafik (durum, öncelik, liste, kişi) filtreleri, sayaçlar (açık, geciken, bugün, son 7 günde biten), halka grafik. Bağışlar modülü: dönem (7/14/30 gün) ve dağılım (tür / ödeme yöntemi) filtreleri, halka grafik. Filtreler kişiye özel, sayfa yenilenmeden değişir.
- Hızlı görev penceresi (Başlangıç, Görevler modülü, komut paleti): ad, liste, son tarih, öncelik, bana ata. Flux Tasks yoksa görünmez.
- WhatsApp ve Görevler artık tam sayfa değil: sol menü simgelere daralır, uygulama hemen sağında açılır (geniş ekran; Ayarlar › Genel'den kapatılabilir). Telefonda eski geçiş düğmesi kalır.
- Hızlı geçiş: menüde tıklanan sayfa önden hazırlanır (Speculation Rules, yalnız tıklanan bağlantı), sayfa geçişi yumuşak (View Transitions). Kapatılabilir.
- Mobil alt bar varsayılanı: Panel, WhatsApp, Görevler, Bağışlar (Yazılar yok).
- Flux Tasks menüsünde renkli logo.

## 1.0.0
- İlk sürüm: yönetim paneli için yeni tasarım (Figtree yazı tipi, yeşil vurgu, yuvarlak kartlar). Kapatınca WordPress'in standart paneli döner.
- Sol menü: gruplu (Genel, Uygulamalar, Bağış, İçerik, Site, Diğer), çizgi simgeler, akordeon alt menüler; daraltılınca simge şeridi + açılır alt menüler. Marka, uygulama sekmeleri (Panel / WhatsApp / Görevler, okunmamış rozetli), arama, kullanıcı ve tema düğmesi menünün içinde.
- Rol bazlı menü düzenleyici (Ayarlar › Yönetim teması › Menü ve alt bar): her rol için sürükle-bırak sıralama, yeniden adlandırma, gizleme, alt menü gizleme, grup ekleme/silme, başka rolden kopyalama, canlı önizleme. Gizlemek yetkiyi değiştirmez. Yeni eklentilerin menüleri "Diğer"e düşer.
- Mobil: alt bar (rol başına seçilebilen 4 kısayol + "Daha"), soldan açılan yan panel, sabit üst bar, güvenli alan (çentik) desteği.
- Flux Chat ve Flux Tasks tam ekran ekranlarında yalnız küçük uygulama geçiş düğmesi (Alt+1..9); o ekranların kendi arayüzüne dokunulmaz.
- Otomatik açık/koyu tema (cihaza göre), kişi başı tercih; koyu temanın kapalı kalacağı ekranlar ayarlanabilir (varsayılan: blok editörü, WooCommerce Yönetim).
- Başlangıç: karşılama + tarih, rol yetkisine göre özet kutucukları (bugünkü/aylık bağış, onay bekleyen, okunmamış WhatsApp, açık görevler, sistem kontrolü), modüller (son 14 gün bağış grafiği + son bağışlar, Görevlerim, Sistem kontrolü, Hızlı işlemler), "Modülleri düzenle".
- Komut paleti (Ctrl/⌘ K): menü, uygulamalar, işlemler ve içerik araması.
- Canlı rozetler WordPress Heartbeat ile (ek istek yok; varsayılan 60 sn; editör ekranlarında WP'nin aralığına dokunulmaz). Uygulama simgesinde okunmamış sayısı (Badging API).
- PWA: manifest, service worker (panel sayfalarını önbelleğe almaz, yalnız statik dosyalar), çevrimdışı ekranı, kısayollar, yükle kartı (iOS için ipucu). Sunucuya dosya koymadan admin-ajax üzerinden.
- Giriş ekranı tasarımı (isteğe bağlı).
- `flx_system_checks` / `flx_system_fixes` kaydı; Flux Updater simgesi.
- Filtreler: `flxa_apps`, `flxa_app_screens`, `flxa_counts`, `flxa_dashboard_stats`, `flxa_quick_links`, `flxa_menu_icons`, `flxa_pwa_manifest`, `flxa_pwa_shortcuts`.
- TR + EN çeviri.
