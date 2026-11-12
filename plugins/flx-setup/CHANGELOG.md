# Değişiklikler

## 1.2.8
- Ön ayarlar: profil değeri yer tutucu taşıyınca (ör. profil e-postası "bilgi@{domain}", ayar "{profile.email}") ikinci kat da çözülür. Önceden ayar "bilgi@{domain}" ile kıyaslandığı için sitedeki çözülmüş değer her uygulamadan sonra "Değişecek" görünüyordu (flxseo:email). Kendine dönen profil değerinde döngü yok (en çok 3 kat).

## 1.2.7
- Ortak onay/uyarı penceresinde (flx-brand 1.6.1) metinler bir kademe büyüdü: açıklama 16 px, başlık 17 px, bildirimler 14,5 px.

## 1.2.6
- Paketler: "Gerekli:" satırı olan paketlerde ad ile etiketler arasında oluşan boş satır kaldırıldı; satır yükseklikleri tutarlı.
- Çeviriler: güncellendikten sonra satır hep "Güncelleme var" kalıyordu. Kurulamayan çeviri artık "başarılı" sayılmaz, nedeni yazılır; güncellemeden hemen sonra yeniden kontrol edilir; sitede aynı ya da daha yeni tarihle kurulu çeviri (WordPress.org yine önerse de) gizlenir.
- İkonlar sitede önbelleğe alınır (uploads/flx-setup/icons): sunucunun imzalı adresi her kontrolde değiştiği için ikonlar her seferinde yeniden iniyor, yanıp sönüyor, bazen hiç gelmiyordu. Eldeki ikon kalır, yenisi gelince hemen değişir, inemezse eskisi gösterilir. "Güncellemeleri kontrol et" tabloyu yenilerken ikonlar yerinde kalır.

## 1.2.5
- Paket indirme hatası artık gerçek nedeni yazıyor: "Dosya indirilemedi." yerine HTTP kodu + sunucunun mesajı (ör. "HTTP 403: Invalid site key"), zaman aşımı, sunucuya ulaşılamadı, disk dolu, gelen dosya zip değil. Her durumda ne yapılacağı da yazar; ayrıntı debug.log'a da düşer.
- İndirmeden önce geçici klasör yazılabilir mi ve diskte en az 50 MB boş yer var mı bakılır.
- Geçici hatada (zaman aşımı, bağlantı, 408/425/429/5xx, yarım/bozuk dosya) 2 ve 5 sn bekleyip en fazla 3 kez denenir; PHP süre sınırına takılmamak için 25 sn'den sonra yeniden denenmez.
- Yalnız FluxWP kaynaklarından (güncelleme sunucusu / GitHub kaynağı) gelen indirmeler; WordPress.org ve diğerleri değişmedi.

## 1.2.4
- Tarayıcının onay/uyarı/giriş pencereleri (confirm, alert, prompt) yerine ortak FluxWP modalı (flx-brand flxDialog): yeni tarz, telefonda alttan açılır, koyu modla uyumlu. Silme gibi geri alınamaz işlemlerde kırmızı onay düğmesi.

## 1.2.3
- İçe aktarılan ön ayar klasörü Apache 2.2 ve 2.4'te web'den açılmaz.

## 1.2.2
- Paketler: devre dışı eklenti ve temalar artık hep listenin sonunda. Sıra: güncellemesi olanlar, etkinler, kurulu olmayanlar, devre dışılar.
- FluxWP dışındaki eklentilerin ikonları güncelleme olmasa da görünür (WordPress.org kaydı, yoksa eklentinin kendi klasöründeki ikon). İkonu bulunamayan eklentide adın baş harfiyle renkli kutu (filtre `flxu_wp_plugin_icon`).

## 1.2.1
- Paketler: ad altındaki kimlik (slug/dosya) kaldırıldı, yerine etiketler: FluxWP paketlerinde "FluxWP" + kategori (ör. İletişim), diğerlerinde gri "Genel" + Eklenti/Tema/Çeviri/Çekirdek.
- "Tür" sütunu kaldırıldı.
- Ada tıklayınca açılan pencerede teknik bilgiler (kimlik/dosya, tür, kategori, yazar, sürümler, kaynak); 3. parti eklenti ve temalarda da açıklama ve web sitesiyle.

## 1.2.0
- Paketler ekranı artık sitedeki her şeyi gösteriyor: FluxWP paketlerinin yanında 3. parti eklentiler, temalar, çeviriler ve WordPress çekirdeği. Güncellemesi olanlar en üstte.
- Sekmeler (Tümü / FluxWP / Eklentiler / Temalar / WordPress ve çeviriler) her sekmede satır ve güncelleme sayısıyla; canlı arama kutusu ve "Yalnız güncellemeler" süzgeci.
- 3. parti eklenti, tema ve çeviri güncellemeleri sayfadan çıkmadan tek tek, seçerek ya da "Tümünü güncelle" ile yapılır (WordPress'in kendi yükleyicisi). Çekirdek güncellemesi WordPress'in kendi ekranında.
- "Güncellemeleri kontrol et" WordPress çekirdeği ve çevirileri de yeniler.
- 3. parti eklenti ve temalarda da seçim kutusu: toplu etkinleştir, devre dışı bırak, kaldır.
- Yeni "Otomatik güncelleme" sütunu: her eklenti/temada aç/kapa düğmesi (WordPress'in kendi ayarı) ve toplu "Otomatik güncellemeyi aç/kapat". FluxWP paketlerinde genel ayar açıksa kilitli "Açık".
- 3. parti güncellemeler de FluxWP paketleriyle aynı korumadan geçer: Flux Backup yedeği, geçmiş kaydı, güncelleme sonrası site kontrolü ve bozulursa yedekten geri dönüş (filtre flxu_guard_third_party).
- Hata satırda ve en üstteki kayıtta görünür; satırda "Yeniden dene", kayıt başlığında "Hatalıları tekrar dene".

## 1.1.12
- Paketler ekranında "18güncellemeleri mevcut" yerine "18 güncelleme mevcut"; yedek/paket sayısı metinleri doğal Türkçe tekil ("%d paket etkinleştirildi", "%d yedek silindi").
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 1.1.11
- Add per-row selection and top/bottom apply controls to preset, migration and patch previews. Select normal changes or explicitly overwrite protected values in separate groups.
- Execute only submitted targets, reject empty/stale selections and missing unchecked dependencies, and keep partial migrations/patches pending. Existing API callers retain their full-apply behaviour.

## 1.1.10
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Preserve singular/plural message boundaries and printf arguments in English source IDs and all native language packs; prevent count labels from breaking populated screens.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.
- Use GitHub catalog descriptions, SVG icons, galleries and publisher links through the updater mirror exclusively; preserve regional locales and remove bundled preview fallbacks.
- Repair the connected-package count plural message to keep source settings available.


## 1.1.9
- Open a simple accessible package preview from the package name or icon, with a short localized description, example screenshots and a publisher website link. Preserve installation/update controls.
- Reuse catalog metadata, with bundled public-package introductions and existing documentation images as fallback; private packages do not inherit public marketing links.

## 1.1.8
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 1.1.7
- Kurulum sihirbazı "Başlayalım"dan sonra önce yüklü uygulamaların bekleyen güncellemelerini yapar; güncellemeler bitmeden kuruluma geçilmez. FluxWP Panel'in kendisi güncellenecekse önce o güncellenir, sihirbaz yeni sürümle yeniden açılır ve kalan uygulamalar onunla güncellenir. Hata olursa nedeni yazılır; "Tekrar dene" ve Paketler ekranına gitme seçeneği çıkar.

## 1.1.6
- Paketler: güncelleme sunucusundaki ikon açılmazsa kırık görsel yerine standart eklenti/tema ikonu görünür.

## 1.1.5
- Kurulum sihirbazının eklenti ekranları güncelleme sunucusundan gelir (updater 1.13.0+): kategoriler ve sırası (TR/EN ad ve açıklama), her paketin kategorisi, sırası, "önerilen" işareti ve kısa açıklaması. Sunucu kategori vermezse tek bir "Eklentiler" ekranı gösterilir.
- Sunucunun private dediği paketler sihirbazda hiç görünmez (QR Menü, TV Remote, Staff gibi adlar artık elle yazılmaz).
- Paket ikonları ve sihirbaz önizlemeleri FluxWP Panel'e gömülü değil: ikon paketin kendi zip'inden (assets/icon.svg, icon-pastel.svg), önizleme paketin deposundaki docs/wizard-tr.jpg / wizard-en.jpg dosyasından; sunucu bunları sürüm başına önbelleğe alıp imzalı adresten verir. Sunucu vermezse kurulu paketin kendi ikonu, o da yoksa FluxWP Panel logosu. Açılmayan önizleme yerine ikonlu yer tutucu çıkar.
- Yerleşik ikonlar (assets/icons), önizlemeler (assets/onboarding/thumbs), tanıtım metinleri ve `flxk_onboarding_intro` filtresi kaldırıldı. Eklenti 1.8 MB küçüldü.

## 1.1.4
- Kurulum sihirbazından yalnız özel depoda dağıtılan bir paketin tanıtım metni, ikonu ve ekran görüntüsü kaldırıldı. Böyle paketler katalogda varsa adlarıyla ve kendi ikonlarıyla listelenmeye devam eder.

## 1.1.3
- Kurulum sihirbazı, Diğer ekranı: Flux AI Chatbot kendi ikonuyla görünür (ikonlar pakete eklenmişti ama sürüm artmadığı için sitelere gitmemişti; genel FluxWP Panel ikonu çıkıyordu).
- Flux CRM, Flux AI Chatbot, Flux Wiki ve Flux reCAPTCHA kartlarına ekran görüntüsü ve kısa açıklama (TR + EN) eklendi; önizleme alanı artık boş gri değil.
- Ekran görüntüsü olmayan kartta ikon önizleme alanının sol üst köşesine yapışıyordu; artık ortada.
- Kategori ekranında 8'den çok kart olunca alt satırlar üsttekilerin üstüne biniyordu; artık satırlar tam boy, alan kendi içinde kayar.

## 1.1.2
- Güncelleme denetimi site adresini kullanım verisi izninden bağımsız gönderir (zorunlu kayıt, zorunlu çerez gibi). Sunucu (updater 1.10.2) yalnız adresi ve IP'den bulunan ülkeyi tutar, IP saklamaz. WordPress/PHP sürümü, saat dilimi ve dil yine yalnız izinle. Ayarlar ve sihirbaz metinleri buna göre.

## 1.1.1
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.

## 1.1.0
- Uygulama: paketler, kurulum sihirbazı, ön ayarlar ve güncelleme ayarları web görünümünde açılır.

## 1.0.24
- Paketler: üstteki "Bitene kadar bu sayfadan ayrılmayın" şeridinde kapatma (X) düğmesi. İşlem bitince şerit artık 4 saniyede kaybolmaz; en az bir paket değiştiyse "Son değişiklikleri görmek için sayfayı yeniden başlatın." yazısı ve **Yeniden başlat** düğmesi çıkar (sayfayı yeniler), X'e basılana kadar kalır. Hiçbir şey değişmediyse şerit 6 saniyede kapanır.
- Paketler sayfası açılınca güncellemeler kendiliğinden kontrol edilir (butona basmaya gerek yok, buton yine duruyor). Son kontrol 2 dakikadan yeniyse ya da sunucu bekleme süresi bildirdiyse istek atılmaz; süre `flxk_auto_check_after` filtresiyle değişir, 0 kapatır.

## 1.0.23
- Ön ayar migration'ı `"requires": {"plugins": [...]}` bildirebilir. Eksik eklenti FluxWP Panel'in paket kurucusuyla kurulur ve etkinleştirilir; migration o zamana kadar bekler, "uygulandı" sayılmaz. Önceden eklenti yoksa içerik sessizce atlanıp migration bitmiş sayılıyordu (ör. Flux Wiki kurulu olmayan sitede wiki sayfaları hiç gelmiyordu). Kurma denemesi saatte bir.
- Ön ayar kartında "Eksik: …" uyarısının yanında **Eksik eklentileri kur ve etkinleştir** düğmesi.

## 1.0.22
- Metinler: MCP sunucusu artık Flux AI & MCP eklentisinde; "Flux SEO & MCP" ve "Flux AI ›" adresleri "Flux AI & MCP" oldu.

## 1.0.21
- MCP araç adları önek aldı: `preset_*` → `flxk_preset_*`. Eski adlar alias olarak çalışır (Flux AI 0.8.0+).

## 1.0.20
- Anonim kullanım verisine ülke kodu eklendi (`cc`, ör. `TR`): WooCommerce mağaza adresi girilmişse mağaza ülkesi, yoksa Ayarlar › Genel saat dilimi. IP'ye bakılmaz, IP gönderilmez. Telemetri merkezi siteleri ülkeye göre gruplar.

## 1.0.19
- Paketler: kurulum/güncelleme başlayınca ekranın üstünde, sayfa kaydırılsa da yerinde duran koyu bir şerit çıkar: "Bitene kadar bu sayfadan ayrılmayın", o anki adım (ör. "Flux CRM kuruluyor…"), sayı (3 / 6) ve ilerleme çubuğu. İşlem bitince sonucu ("Bitti: 6 başarılı, 0 hatalı.") 4 saniye gösterip kapanır. Önceden uyarı yalnız kayıt kutusunun başlığındaydı, aşağı kaydırınca görünmüyordu.
- Eski Flux Updater (`flx-updater`) paket listesinde, sihirbazda ve güncellemelerde artık görünmez: FluxWP Panel'e katıldığı için güncelleme sunucusu eski release'lerini listelese de katalogdan çıkarılır. Liste `flxk_retired_slugs` filtresiyle genişletilir.

## 1.0.18
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 1.0.17
- Güncelleme sunucusu isteği geri çevirdiğinde nedeni artık açıkça yazılıyor (sunucu 1.10+ hata gövdesindeki `code`): **Dağıtım durduruldu** (`paused`, 503), **Bölge engeli** (`region_blocked`, 403), **Site anahtarı gerekli** (`public_disabled`, 401), **Site anahtarı pasif** (`key_disabled`, 403). Önceden bunlar "Site anahtarı geçersiz" ya da "beklenmeyen yanıt (HTTP 503)" görünüyordu.
- Durdurma ve diğer engellerde site sunucuyu sıkıştırmaz: 503/429'da `Retry-After` süresine (1 dk–1 gün), diğer engellerde 1 saate kadar otomatik kontrol yapılmaz, son iyi katalog kullanılır. Sonraki deneme zamanı mesajda yazar. "Bağlantıyı test et" / "Güncellemeleri kontrol et" beklemeden sorar.
- Dağıtım durdurulduğunda kart, eklenti satırı ve Sistem Kontrolü sarı (uyarı) gösterir; diğer engeller kırmızı kalır.
- Resmi sunucu (updater.fluxwp.com) engel mesajlarının sonunda "Destek ve öneri: info@fluxwp.com" yazar. Başka sunucularda adres gösterilmez; `flxk_support_email` filtresiyle değiştirilir.

## 1.0.16
- Açık tema: cihaz koyu moddayken admin açık olsa bile ön ayar kartları koyu görünmüyor; Flux Setup renkleri (kart, rozet, uyarı, fark durumları, paket listesi) Flux Admin değişkenlerinden. Koyu tema: paket listesinde seçili satır artık beyazlaşmıyor. Kurulum sihirbazı: soluk metinler daha okunur.

## 1.0.15
- Eski Flux Updater adı arayüzden kalktı: kaynak etiketi, atlananlar notu ve sunucu adresi açıklaması artık "FluxWP Panel" / "FluxWP güncelleme sunucusu" der. Sihirbaz gizli listesinden `flx-updater` çıktı.
- Eski flx-updater hâlâ etkin olan sitelerde davranış aynı: FluxWP Panel onu otomatik kapatır, ayarları (`flxu_*`) korur, "Eklentiler ekranından silebilirsiniz" notu gösterir.

## 1.0.13
- Ön ayar güncellenince ayarlar da güncellenir: `version` değişince profil, WordPress ve eklenti ayarları siteyle eşitlenir. Ön ayarın yazdığı ve sizin değiştirmediğiniz değerler yenilenir, yeni alanlar eklenir, elle değiştirdikleriniz korunur. Geçmişte "Eşitleme" kaydı, geri alınabilir. Kapatmak: `flxk_auto_sync` filtresi.
- Ön ayar bir ayarın yalnız bildirdiği alanlarını değiştirir. Önceden tema ayarına `site_mod` yazmak, temanın sanitize'ı yüzünden çerez banner'ını kapatıyordu ("İlk ziyarette çerez banner'ını göster" kapalı kalıyordu). Motor artık bildirilmeyen alanları yazdıktan sonra eski haline getirir (`flxk_option_guard`).
- Ortak ayar şeması: eklentiler ve tema `flx_settings_schema` filtresiyle ayar alanlarını (tür, varsayılan, açıklama) tanımlar. AI `preset_schema` çıktısında `settings` olarak görür, önizleme şemada olmayan alanı uyarır.
- Ön ayar sürümü durumda metin olarak saklanır (önceden "0.1.10" sayıya çevrilip 0 oluyordu).

## 1.0.12
- Google önerileri (isteğe bağlı, Site Kit tarzı kart): Başlangıç, Sistem Kontrolü ve Paketler ekranının üstünde **Google'da bulunun** kartı Google Site Kit'i önerir. **Gerekli değil** kartı site genelinde kapatır; **Google Site Kit'i kur** wordpress.org'dan kurar, etkinleştirir ve Site Kit'in kendi kurulum ekranına yönlendirir. Google hesabını kullanıcı orada bağlar.
- Site Kit Google hesabına bağlanınca ve WooCommerce etkinse ikinci kart: **Ürünlerinizi Google'da gösterin** (Google for WooCommerce, `google-listings-and-ads`). Kurulunca WooCommerce › Google ekranına gider.
- Kapatılan kartlar `flxk_google_cards` seçeneğinde; kartlar aynı adlı filtreyle değiştirilir ya da yenileri eklenir.

## 1.0.11
- Anonim kullanım verisi (telemetri). Yalnız "Ürünü geliştirmemize yardımcı olun" kutusu işaretliyse (`flxk_telemetry` = `'1'`, varsayılan kapalı) çalışır. Günde en fazla bir kez telemetri adresine gönderilir (varsayılan `https://fluxwp.com/wp-json/flxan/v1/telemetry`, Flux Analytics telemetri merkezi): rastgele kurulum kimliği (güncelleme anahtarıyla ilişkisiz), WordPress sürümü, PHP ana sürümü, site dili, etkin FluxWP eklentileri ve teması (sürümleriyle), FluxWP dosyalarından gelen PHP hata/uyarıları (tür, temizlenmiş mesaj, göreli dosya, satır, tekrar sayısı), kurulum sihirbazında hangi adıma kadar gelindiği, seçilen ve bırakılan kartlar, ön ayar ve renk stili, bitirildi/atlandı ve yaklaşık süre (sihirbaz verisi tarayıcıda tutulur, siteye yalnız kutu işaretliyse kaydedilir).
- Gönderilmez: site adresi, IP, kullanıcı, e-posta, içerik, FluxWP dışı eklentiler, güncelleme anahtarı. İstek WordPress'in site adresini içeren User-Agent'ını ve anahtar başlığını taşımaz. Mesajlardan sunucu yolları, alan adı, URL, e-posta ve IP temizlenir.
- FluxWP Panel › Ayarlar'da **Anonim kullanım verisi** kartı: durum, son gönderim, telemetri adresi (boşsa FluxWP; başka bir Flux Analytics sitesini merkez yapmak için), gönderilecek verinin önizlemesi ve Şimdi gönder.
- Kutu kapatılınca zamanlama, bekleyen hatalar ve rastgele kimlik silinir. Filtreler `flxk_telemetry_enabled`, `flxk_telemetry_url`, `flxk_telemetry_payload`; önizleme `FLXK_Telemetry::payload( false )`.

## 1.0.10
- Paketler (telefon): logosu olmayan pakette yer tutucu simge metnin üstüne binmiyordu; artık logo kutusunun yerinde.
- Ön ayar paketlerinin kendi logoları: Yeşil Türkiye Preset (ağaçlar, yeşil), Hayır Kapısı Preset (açık kapı, turuncu), FluxWP Site Preset (sayfa şablonu, mor).

## 1.0.8
- Kurulum sihirbazının ilk ekranında yasal onay: **Kullanım Şartları ve Gizlilik Politikası'nı kabul ediyorum; KVKK Aydınlatma Metni'ni okudum** (fluxwp.com linkleri, panel diline göre EN ya da /tr/). İşaretlenmeden "Başlayalım" açılmaz; "Kendim kurarım" yine çalışır.
- İsteğe bağlı **Ürünü geliştirmemize yardımcı olun (kullanım verisi gönder)** kutusu, varsayılan kapalı. FluxWP Panel › Ayarlar'dan açılır ya da geri alınır.
- İzin yokken güncelleme denetimi yalnız paketleri, FluxWP Panel sürümünü ve kurulum kimliğini gönderir: site adresi, WordPress/PHP sürümü, saat dilimi ve dil gitmez; FluxWP sunucularına giden isteklerde site adresli User-Agent kaldırılır. Sunucu tarafı: updater 1.9.6.

## 1.0.7
- Kurulum sihirbazı "Neyle başlayalım?": FluxWP teması kartı yerine **Yönetim teması** (Flux Admin) kartı. Mağaza/Dernek seçiminden bağımsız seçilir; Flux Admin artık Yönetim kategori ekranında tekrar listelenmez.
- FluxWP teması kart olmadan her kurulumda gelir (ön ayar, renk ve sayfalar ona bağlı).

## 1.0.6
- Görünen ad **FluxWP Panel** oldu (eklenti başlığı, yönetim menüsü, sihirbaz, bildirimler). Slug `flx-setup`, ayarlar ve güncelleme adresi aynı kalır.

## 1.0.5
- Paketler ekranı telefonda kart düzeninde: logo, ad, sürüm ve durum tek satırda; tablo başlığı, tür sütunu, slug ve çıkış tarihi gizli. Araç çubuğu düğmeleri yan yana, toplu işlem tam satır.
- Güncelleme ayarları › Diğer kaynaklar tablosu telefonda kart düzeninde.

## 1.0.4
- Düzeltme: sihirbaz ve paket ekranı eklentileri sessiz etkinleştiriyordu; paketlerin etkinleştirme kancaları (tablolar, varsayılan ayarlar) çalışmıyordu. Artık normal etkinleştirme yapılır.
- Bir kerelik onarım: güncellemeden sonra yönetim paneli ilk açılışta etkin Flux paketlerinin etkinleştirme kancalarını bir kez çalıştırır (ör. eksik Flux Forms tabloları oluşur). Filtre `flxk_repair_plugin`.

## 1.0.3
- Flux QR Menu (özel paket) kurulum sihirbazında listelenmez. Gizlenecek paketler için filtre `flxk_onboarding_hidden`.

## 1.0.2
- Kurulum ekranında sol liste ve sağdaki çark dikeyde ortada.

## 1.0.1
- "Neyle başlayalım?" ekranına **Dernek ve bağış** kartı (Flux Donate + Flux Certificates) ve sade bir illüstrasyon. Mağaza ile Dernek tema gibi tek seçimli: biri seçilince diğeri kalkar.
- Eklentiler kategorilere ayrıldı (Yönetim, İletişim, Büyüme, Yapay zekâ, Sistem); her kategori ayrı ekran, kare kartlar, tam açıklama. Filtre `flxk_onboarding_groups`; `flxk_onboarding_core` artık kart listesi alır.
- Görünüm adımı sekmeli: Kimlik (site adı, slogan, logo yükleme), Ön ayar, Renk (her stil küçük site önizlemesiyle), Sayfalar (Blog, Hakkımızda, İletişim, SSS; filtre `flxk_onboarding_pages`). Sağdaki önizleme sabit; logo, menü, bağış/ürün kartları ve blog bölümü seçime göre değişir. Devam ve Geri önce sekmeler arasında gezer.
- Kurulumda logo temanın logosu ve site simgesi olur, seçilen sayfalar açılır (Blog yazılar sayfası olur), slogan kaydedilir.
- Kurulum ekranı: "Siteniz sizin için hazırlanıyor" başlığı görünür; koyu konsol yerine açık renkli, yarı boy dönen çark ve sade satırlar. Kurulum sırası yan yana küçük kartlar; kurulanlar açık yeşil.
- Atla penceresi artık tam opak.
## 1.0.0
- Flux Updater ile birleşti: tek eklenti **Flux Setup** (slug `flx-setup`). Paket listesi, güncellemeler, sürüm değiştirme, güncelleme öncesi yedek ve geri alma Flux Updater 1.7.2'den aynen geldi; sınıflar `FLXU_*`, ayarlar `flxu_*` aynı kaldı.
- Sol menüde tek **Setup** öğesi: Paketler, Kurulum sihirbazı, Ön ayarlar, Ayarlar. Eski `page=flx-updater` / `flx-updater-ayarlar` adresleri yeni sayfalara yönlenir.
- Kurulum sihirbazı: etkinleştirmeden sonra bir kez açılır, atlanabilir. Sunum düzeni: her adım tek yatay ekrana sığar, sayfa kaymaz, adımlar yana kayar; zemin yumuşak renkli, kartlar buzlu cam. Adımlar: tanıtım › tema ve mağaza › eklenti kartları (ekran görüntüsü + tek satır) › ön ayar, renk stili ve site adı (canlı önizleme) › kurulum (paket başına satır, konsol kaydı, ilerleme) › bitti ekranı ve sonraki adımlar. Hatalı adımlar sonda listelenir.
- "Neyle başlayalım?" adımı: FluxWP teması ve Flux Commerce ayrı ekranda, ikisi de önerilen; altta "Seçmeden devam et". Tema seçilmezse renk stilleri ve tema ön ayarı gösterilmez. Filtreler `flxk_onboarding_intro`, `flxk_onboarding_core`, `flxk_onboarding_presets`, `flxk_onboarding_cap`.
- Flux Updater kuruluysa Flux Setup etkinleşince eski eklenti kapatılır, ayarları korunur; ikisi aynı anda yüklenmez.
- Geist yazı tipi (OFL) eklentiyle gelir, dışarıdan yazı tipi yüklenmez.

## 0.2.4
- Ara bilgi metinleri Flux Admin tonunu izliyor.

## 0.2.3
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 0.2.2
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 0.2.1
- Kurum profili renk örneği FluxWP indigo.

## 0.2.0
- Tema `flx-setup-panel` desteği verirse Ön ayarlar ekranı Tema Ayarları içinde açılır; ayrı menü kalkar, eski adres oraya yönlenir.
- Kurum profiline site simgesi (`icon`).
- Şablon/şablon parçası öğeleri `requires.theme` etkin temayla uyuşmazsa atlanır.

## 0.1.0
- İlk sürüm: ön ayar motoru (flx-preset/1).
- Ön ayar şablonu (preset.json): kurum profili, WordPress ayarları, eklenti ayarları (derin birleşme, key'li listeler), global stil, şablonlar, şablon parçaları (header, footer), pattern'ler, sayfa/yazı/terim/menü/ürün içerikleri ve medya. Sayfa gövdesi blok markup'ı ya da JSON blok ağacı.
- Görünüm › Ön ayarlar: önizle (ne değişecek), uygula, yalnız boşları doldur, kaldır. Panelden elle değiştirilen değerlerin üzerine yazılmaz; istenirse satır satır zorla uygulanır.
- Migration'lar: ön ayar güncellemeleri tek tek Önizle / Uygula / Atla; `auto` olanlar kendiliğinden.
- Birden çok ön ayar aynı sitede (çakışmalar önizlemede).
- Geçmiş: her uygulama geri alınabilir; kaldırınca oluşturulan sayfalar taslağa alınır, silinmez.
- Kurum profili (`flx_profile()`): paketler ad, imza, WhatsApp, IBAN gibi bilgileri buradan okur.
- Bu siteden şablon çıkar (gizli alanlar hariç), JSON içe aktar.
- Flux AI sohbeti ve Flux MCP araçları: preset_schema, preset_list, preset_export, preset_patch_preview, preset_patch_apply, preset_revert, preset_history. Dış MCP'den gelen yamalar varsayılan olarak yönetici onayı bekler.
- Boş sitede ön ayar eklentisi etkinleşince kendiliğinden uygulanır.
