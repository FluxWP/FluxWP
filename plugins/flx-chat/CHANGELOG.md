# Changelog

## 1.36.1
- Personel bağlama: sohbet personele bağlansa da asistan önceki "personel olarak tanınmıyor" cevaplarına bakıp yine reddediyor, ekibe devrediyordu. Bağlama anı artık konuşma geçmişinde asistana not olarak görünür; personel talimatı önceki ret cevaplarının geçersiz olduğunu, yetki sorgulayıp devretmemesini ve yarım kalan isteğe (ör. başkası adına bağış) devam etmesini söyler.
- Sohbet ekranı: yapışkan tarih etiketi ("Bugün") altındaki balonun başlığını örtüyordu. Etiket artık yalnız kaydırırken görünür (WhatsApp gibi), durunca kaybolur.

## 1.36.0
- Personel tanıma: WhatsApp numarayı gizlediğinde (gizli numara, @lid) asistan personeli numaradan tanıyamıyordu; rol güncellense de "bu numara personel olarak tanınmıyor" deyip duruyordu. Kişi bilgisi panelinde yeni "Personel" alanı: yönetici sohbeti bir personele bağlar, asistan personel araçlarını (başkası adına bağış, bağışçılarım, rapor) açar. Numarası bilinen sohbette numara önceliklidir. Bağlama/kaldırma sohbete sistem satırı olarak yazılır.
- Gizli numaralı sohbette asistan sebebi söyler (WhatsApp numarayı gizliyor), "tekrar dene" denince aynı cümleyi tekrarlamaz; kişi mesajında numara yazarsa hemen o numaraya doğrulama kodu gönderir.
- Normal bağış akışı: ödeme linki ve havale bilgileri yalnız o sohbete gider; asistan artık "X'in numarasına gönderilecek" demez. Özette "Bağışçı" satırı korunur (kayıt ve havale açıklaması bu adla açılır).

## 1.35.10
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.


## 1.35.9
- Güvenlik (CodeQL): Asistan stüdyosunda sekme adı yalnız bilinen sekmelerden seçilir.

## 1.35.8
- Metinler: MCP sunucusu artık Flux AI & MCP eklentisinde; "Flux SEO & MCP" ve "Flux AI ›" adresleri "Flux AI & MCP" oldu.

## 1.35.7
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 1.35.6
- Ayarlar: etkin sekme açık zeminde okunur koyu WhatsApp yeşili.

## 1.35.5
- Ayarlar › Bağlantılar: Flux Mail veya Flux Certificates bağlantısı seçilince bağlantı kimliği yanlış okunuyordu (`flxi:`/`flxs:` önek uzunluğu), kaydetmede bağlantı boşa düşüyordu; düzeltildi.

## 1.35.4
- Koyu modda yenilemede açık tema yanıp sönmesi giderildi: kayıtlı tema, uygulama kabı çizilir çizilmez (app.js beklenmeden) uygulanır.

## 1.35.3
- Görevler modülünün varsayılan rengi yeşil yerine tema vurgusu.

## 1.35.2
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 1.35.1
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## Yayımlanmamış

- Benchmark'a başka eklentinin senaryoları için filtreler: `flxc_bench_groups`, `flxc_bench_default_scenarios`, `flxc_bench_turn` (senaryoda `runner`). Filtreler boşken davranış aynı; Qurban, ön ayar açık sitede kendi WhatsApp grubu senaryolarını buradan çalıştırır.

## 1.35.0

- **Yeni arayüz (deneysel):** Ayarlar › Genel'de seçilince Sohbetler sayfası eski app.js yerine masaüstü/mobil uygulamayla aynı React ekranını (`assets/flux/flux-modules.js` + `.css`, `tools/fluxapp/embed-sync.sh flx-chat` üretir) açar: `FluxModules.mount(el, { module: 'flxc-inbox', rest, nonce, locale })`. WP menüsü ve üst çubuk görünür kalır. Kapalıyken ya da dosyalar yoksa eski arayüz; filtre `flxc_flux_ui`.
- **Sohbeti dışa aktar REST'i:** `POST flx-chat/v1/chats/{id}/export` `{ format: md|json, mask, notes }` dosya döndürür (panel indirmesiyle aynı içerik; mask ve notes varsayılan açık; sohbeti görme yetkisi gerekir).

## 1.34.0

- **WhatsApp hesapları Flux Mail'de (Ahmet):** Flux Mail etkinken hat yalnız oradaki hesaptan seçilir; "Ayrı Evolution API bilgisi gir" seçeneği yalnız zaten öyle kurulmuş hatta kalır. İletişim hesabı seçili hatta "QR ile bağla" yerine İletişim › Bağlantılar bağlantısı çıkar (QR, durum, yeniden başlat, çıkış ve çoklu hesap orada). Webhook kurma, test ve içe aktarma burada kalır.
- Hat seçiminde İletişim 1.9.0 hesabının türü (Evolution API / Flux WPAPI) numarası ve durumu (bağlı, bağlanıyor, bağlı değil, sunucuda yok, ulaşılamıyor) görünür.
- İletişim'deki `flxwpapi` türündeki hesap da Evolution gibi hat olarak kullanılır (`flxi_is_evo_type` varsa ona da sorulur; ek tür için `flxc_evo_compatible_types` filtresi).
- **Resmi WhatsApp Cloud API hattı:** İletişim'de `wacloud` türünde eklenen hesap Çağrı Merkezi'nde hat olarak seçilebilir. Gelen mesajlar İletişim'in Meta webhook'undan (`flxi_whatsapp_event`) gelir, ayrıca webhook kurulmaz. Metin, alıntı, belge/fotoğraf/video (Meta'nın indirdiği 1 saatlik geçici adresle), ses, tepki, okundu ve "yazıyor…" Graph'a çevrilir. 24 saat penceresi kapalıyken anlaşılır hata yazılır. Cloud'da olmayan QR, geçmişi içe aktarma, numara kontrolü, profil fotoğrafı ve grup yansıtma bu hatta kapalı; ekip grubu yansıtması varsa ilk Evolution/Flux WPAPI hattından gider.

## 1.33.0

- **Flux App'da yerel WhatsApp ekranı:** Çağrı Merkezi `flxapp_modules` ile `flxc-inbox` olarak kaydolur (menü flx-chat, yetki flxc_use); uygulama WhatsApp menüsünü panel görünümü yerine kendi ekranıyla açar. Yeni uçlar `flx-chat/v1/app/*`: sohbet listesi (open/mine/all/unread, arama, sayfalama), sohbet + not + bağışçı özeti, mesajlar (önceki sayfa ve poll için after), gönder, okundu, asistanı aç/kapat. Sohbet görme kuralı paneldekiyle aynı (temsilci kısıtı).
- Medya (fotoğraf, ses, belge) uygulamada başlık gönderemeyen <img>/<audio> ile açılabilsin diye 1-2 saatlik imzalı adresle gelir.

## 1.32.0

- **Gizli metin gönderimi (Flux App girişi için):** yeni `flxc_send_private_text( $telefon, $metin, array( 'source' => '...' ) )`. Giriş/doğrulama kodu doğrudan WhatsApp'a gider: panelde sohbet açılmaz, mesaj kaydedilmez, telefondan dönen yankısı da kaydedilmez, asistan susturulmaz. `flxc_send_text` ile giden kod panelde herkesin göreceği bir mesaj olurdu ve o sohbette asistanı "temsilci yazdı" diye susturuyordu.
- Asistanın kendi doğrulama kodlarının yankısı da artık panelde mesaj olarak görünmez.
- Yeni filtre `flxc_bot_auto_sources`: asistanı susturmayan giden mesaj kaynakları (varsayılan bot, auto, system, api).

## 1.31.0

- **Personel @lid sohbette tanınmıyordu (Ahmet, sohbet-31):** numarası WhatsApp gizliliğiyle saklanan sohbette personel araçları açılmıyordu; WhatsApp sonradan gerçek numarayı bildirmişse de kullanılmıyordu. Artık sohbetin öğrenilen gerçek numarası personel tanımada kullanılır. Numara hiç görünmüyorsa ve kişi "personelim / referansım" derse asistan normal akışla başkası adına kayıt açmaz: Flux Roles'deki numarasını ister, kodla doğrular (verify_send/verify_check); doğrulanınca personel araçları aynı turda açılır ve staff_create_donation ile devam eder.
- **Yanlış vaat yok:** personel araçları yokken asistan "ödeme mesajları bağışçının numarasına gidecek" demez (ödeme mesajları yalnız o sohbete gider), bağışçının adını yazan kişinin adı gibi kaydetmez.
- **"Tamamı X adına" dağılımı uydurulmuyordu (82 + 18):** sertifika isimlerinde isim başına adet (count) var. Önceden 100 fidanı tek isme yazmak için model ismi 100 kez tekrarlamak zorundaydı, listeyi yarıda kesince kalan adetler bağışçıya düşüyordu. Aynı isim tekrar ederse adetler toplanır; toplam adet bağış adedini aşarsa kayıt açılmaz, model düzeltir. Personelin başkası adına açtığı bağışta da aynı.
- Özet kişiye aynen gösterilir (Bağışçı satırı dahil satır çıkarılmaz, dağılım değiştirilmez).
- Benchmark: "Tamamı tek isme (firma)" ve "Tanınmayan numaradan personelim" senaryoları.

## 1.30.1

- **Hatırlatıcıda görsel (Ahmet):** hatırlatıcı mesajında asistanın çizdiği görsel (```widget) WhatsApp'a ham SVG kodu olarak gidiyordu; hatırlatıcı yolu görselleri ayırmıyordu. Artık sohbetteki gibi metin önce, görsel sonra PNG resim olarak gider (Flux AI › Ayarlar › Görseller'deki çevirici, ör. Gotenberg).
- **Ham kod asla gitmez:** asistanın gönderdiği her metinden ```widget / ```mermaid / ```svg / ```html blokları ve <svg> kodu son anda temizlenir; görsel çevrilemezse yalnız metin gider (Flux AI kurulu olmasa da).

## 1.30.0

- **Yapay zekâ altyapısı tamamen Flux AI (Ahmet):** Çağrı Merkezi'nin kendi OpenRouter ve Anthropic bağlantı kodu kaldırıldı (ai-claude.php silindi, ai.php'den doğrudan istek, model listesi ve fiyat kodu çıktı). Öneri, asistan, dekont okuma, sesli mesaj, özet, test sohbeti ve benchmark'ın tüm istekleri Flux AI üzerinden gider. Flux AI kurulu değilse yapay zekâ özellikleri kapalıdır ve panelde uyarı çıkar; mesajlaşma ve diğer özellikler çalışır.
- **Anahtar aktarımı:** Flux AI etkinken yönetici panele girince Çağrı Merkezi'ndeki OpenRouter/Anthropic anahtarı, Anthropic workspace ID ve öneri modeli Flux AI'ye aktarılır (Flux AI'de o alan boşsa; Flux AI 0.4.1'in flxai_import_config'i varsa onunla). Flux AI'den geri okunup doğrulanan anahtarın buradaki kopyası silinir. Flux AI'de farklı bir anahtar varsa onunki kullanılır, uyarı çıkar.
- Ayarlar › Yapay zekâ: anahtar, workspace ve Claude prompt önbelleği alanları kaldırıldı (Flux AI › Ayarlar'da). Model seçimleri, yaratıcılık, kurum bilgisi, asistan ve tüm raporlar/panolar burada kalır. Sistem kontrolü Flux AI'yi kontrol eder.

## 1.29.0

- **Sertifika referans personele de gider (Ahmet):** personelin açtığı (referansı olduğu) bağış ödenip sertifikalar oluşunca, bağışçıya gittiği gibi personelin WhatsApp'ına da gider (Flux Roles profilindeki numara; bir kez). Ayarlar › Personel: aç/kapa ve önce giden mesaj ({ad}, {bagisci}, {bagis}, {no}, {brand}).
- **Personel sertifikayı yeniden gönderebilir:** yeni araç staff_send_certificate. Personel "sertifikayı bana / bağışçıya / ikimize at" deyince asistan önce hangi sertifikanın kime (numarasıyla) gideceğini yazıp onay ister, onaylanınca gönderir. Bağış başına en fazla 3 kez (Ayarlar › Personel, 0 kapalı). Ödenmemiş bağışta "ödeme onaylanınca kendiliğinden gider" der; "böyle bir işlemim yok" demez.
- **Başkası adına sertifika doğru kişiye:** birden çok isimli bağışta (Flux Donate 2.4.1, her isme bir sertifika) her yakının numarasına yalnız kendi adındaki sertifika gider; önceden ilk sertifika herkese gidiyordu.
- **Dekont "daha önce kullanılmış" yanlış uyarısı:** kişi (ya da personel) az önce bu sohbetten gönderdiği dekontu sorunca asistan kendi yüklemesini kopya sanıyordu; aynı bağış için siteden/onay kartından yazılan kaydı da kopya sayıyordu. Artık bu sohbetteki ve aynı bağıştaki yükleme kopya sayılmaz; başka bir sohbetten ya da başka bir bağış için yüklenmişse yine uyarır.

## 1.28.0

- **Yakınlar hafızada (Ahmet):** bağışta adına sertifika verilen kişi, yakınlığı ve numarasıyla kişinin hafızasına yazılır ("annesi: Fatma Yılmaz · Tel: +90 532 …"); kişi kartıyla ya da yazarak gelen numara da o kişiye bağlanır. Kişi sonra "annem adına bir tane daha" derse asistan adı ve numarayı hazır getirir, özette gösterir, yine onay ister. Ad ya da numara değişirse kayıt güncellenir; anne, baba, eş tek kayıttır, kardeş/çocuk/arkadaş adla ayrı tutulur. Personelin başkası adına açtığı bağışta bu bilgiler bağışçının hafızasına yazılır. Kişinin kendi adına olan sertifika yazılmaz. Kişi hafızası açıksa çalışır (Ayarlar, varsayılan açık).
- Onay özetinde yakınlık da görünür ("Fatma Yılmaz (annesi, +90 532 …) · 1 fidan").

## 1.27.1

- **Ayrı sertifika = tek bağış, tek ödeme (Ahmet):** personel "3 fidan, Halit/Esra/Sevde adına ayrı sertifika" deyince asistan 3 ayrı bağış açıyordu. Artık tek kayıt açılır (adet 3, her isme ayrı sertifika), tek ödeme linki gider. Aynı turda aynı bağışçıya ikinci kayıt açılmaz; personel açıkça ayrı ayrı ödeme isterse açılır.
- Onay özeti Flux Donate 2.4.1'e uygun: her isim bir sertifika satırı ve fidan sayısı ("1. Esra ALTUN · 1 fidan"); aynı isim tekrar ederse tek satırda toplanır, isimsiz kalan fidanlar bağışçı adına tek sertifika.
- **Düzeltmede eski kayıt iptal:** "pardon 100 fidan olacak" gibi düzeltmede yeni kayıt açılınca eski ödenmemiş kayıt iptal edilir (yalnız o personelin açtığı kayıt). Bağışçının ödenmemiş kaydı varken yeni kayıt istenirse asistan önce düzeltme mi yeni bağış mı olduğunu ayırır.
- **Personele hitap:** Flux Roles'deki adıyla (ör. HALİT ALTUN → Halit); sertifika ya da bağış kayıtlarındaki başkasının adıyla hitap edilmez. Konuşma hafızası da sertifika/bildirim mesajlarındaki isimleri kişinin adı saymaz.

## 1.27.0

- **Her fidana ayrı sertifika adı (Ahmet):** birden çok fidan bağışında onay özeti her fidanı ayrı satırda, kimin adına sertifika çıkacağıyla gösterir ("1. fidan · Kader Subaşı", "2. fidan · Ferhat Subaşı"); ad verilmeyen fidanlar bağışçının adına yazılır. Ayrı sertifikaların oluşması Flux Donate tarafında.
- **"Ekibe not düşüyorum" deyip bırakmaz:** model ekibe ilettiğini/not düştüğünü söyleyip handoff aracını çağırmazsa metin gönderilmez; modele ya handoff çağırması ya da ekipten söz etmemesi söylenip bir kez daha denenir.
- handoff notunda kişi adıyla ya da "bağışçı" diye anılır; personel araçlarıyla konuşmayan kişi "personel" diye yazılmaz.

## 1.26.1

- Flux Donate 2.4.0 ödeme onayı kartı için: WhatsApp'tan eşleştirilen dekont bağış kaydına dosya türüyle (`mime`) yazılır; dekont daha önce kullanıldıysa uyarı (`warn`) da eklenir, onay kartında görünür.

## 1.26.0

- **Sesli mesaj (Ahmet):** kişi sesli mesaj gönderince asistan sesi metne çevirip yazılmış mesaj gibi cevap verir. Varsayılan en fazla 60 sn; daha uzun seslerde "ses çok uzun olduğu için dinleyemedik, kısaca yazar mısınız" der. Çevrilen metin panelde sistem satırı olarak da görünür. Çeviri ses okuyan bir OpenRouter modeliyle (varsayılan ucuz Gemini Flash Lite; Claude ses okumaz), Flux AI kuruluysa onun anahtarıyla. Ayarlar › Yapay zekâ › Sesli mesaj (aç/kapa, en uzun süre, model). Kendi çeviriciniz için `flxc_audio_transcribe` filtresi.
- **Ekip grubu bildirimi:** etiketlenecek numara yokken boş `mentioned` dizisi gidiyordu, Evolution 400 veriyordu ("mentioned does not meet minimum length of 1"); artık alan yalnız numara varsa gönderilir.
- **Ekip grubu aynası:** uzun konuşma metni 3500 karakterlik parçalara bölünür, her parça yeni mesaj (önceden tek mesajda gidiyordu); düzenleme de bu sınırı aşınca yeni mesaj açar.

## 1.25.0

- **IBAN istenince hep gider (Ahmet):** kişi "İban", "IBAN no", "hesap numaranız", "havale bilgisi" gibi bir şey yazınca alıcı adı ve IBAN (4'lü gruplu) sunucudan ayrı, kopyalanabilir mesajlarla gider; model payment_info çağırmayı unutsa da. Bilgi tek kaynaktan (Flux Donate / WooCommerce banka hesabı). Aynı turda kayıt sonrası ödeme mesajları IBAN'ı zaten taşıyorsa tekrar gitmez.
- **"Bakıyorum" deyip bırakmaz:** model "bakıyorum", "kontrol ediyorum", "bir saniye" gibi bir cümleyle hiç araç çağırmadan turu bitirirse o metin gönderilmez; modele aracı çağırması söylenip bir kez daha denenir.
- Senaryo: t8 (tek kelime "İban").

## 1.24.0

- **Asistan görsel gönderebilir (Ahmet):** Flux AI 0.2.0 ya da sonrası kuruluysa ve sunucuda görsel çevirici varsa (Flux AI › Ayarlar › Görseller: Gotenberg adresi ya da Imagick SVG), asistan gerektiğinde (personel raporu, karşılaştırma, süreç) ```widget bloğunda tek bir SVG çizer; görsel WhatsApp'a PNG resim olarak, metinden sonra gider. Metin her zaman ayrıca gider; görsel çevrilemezse yalnız metin gider ve sohbete sistem satırı düşer. Mermaid sunucuda çevrilmez, atılır.
- Çevirici yoksa kural talimata eklenmez (model boşuna çizmez); SVG bile çevrilemezse görsel 1 saat kapanır. Fiyat, IBAN, link ve kişisel bilgi görsele yazılmaz. Görsel bloğundaki adresler yine silinir, SVG'nin xmlns adresi korunur.
- Ayarlar › Yapay zekâ › Görseller: aç/kapa (varsayılan açık) ve çevirici durumu. Test sohbetinde görsel resim olarak görünür.

## 1.23.0

- **Kişi kartından numara (Ahmet):** WhatsApp'tan kişi kartı (vCard) gelince ad ve numara okunur (waid ya da TEL satırı, çoklu kartta hepsi). Asistan "Ömer Ali Bellikli · Tel: +90 …" diye görür, numarayı tekrar sormadan bağışı o kişi adına ve numarasıyla hazırlar; özette "Adına: Ömer Ali Bellikli (+90 …)" görünür.
- **Sertifika adına bağış yapılan kişiye de gider:** başkası adına bağışta numara verildiyse (kişi kartı ya da yazılı), ödeme onaylanıp sertifika oluşunca o numaraya önce kısa bir mesaj ("{bagisci} sizin adınıza bağış yaptı"), sonra Flux Certificates'daki WhatsApp sertifika mesajı gider. Her sertifika ve numara için bir kez; bağışçının kendi numarasına, kara listedekine gitmez; sipariş notuna yazılır. Ayarlar › Yapay zekâ › Başkası adına bağış (aç/kapa ve mesaj metni). Özette "Ödeme onaylanınca sertifika bu numaraya da WhatsApp'tan gönderilir." satırı çıkar. Personel asistanında başkası adına açılan bağışlar da aynı.
- **Selam:** "Sa", "Sa knk", "Slm" selam sayılır; "Rica ederim" yerine selam alınır, sorulmadan dekont/bağış durumu anlatılmaz.
- **Tutmayan dekont:** dekont bağışla uyuşmadıysa (tutar, alıcı, IBAN) asistan sonradan "dekontunuz ulaştı, onaylanacak" demez; doğru dekontu isteyebilir.
- Senaryolar: b8 (kişi kartıyla başkası adına), g8 (kısa selam).

## 1.22.0

- **Link modele hiç gitmez (Ahmet):** araç sonuçlarındaki her adres (bağış sayfası, ödeme, site araması, personel ödeme linki) modele `[[link1]]` gibi bir işaretle gider; model linki paylaşmak isterse yalnız işareti yazabilir, sistem gönderirken gerçek adrese çevirir. Modelin kendi yazdığı, araçtan gelmeyen adresler silinir. Geçmişte giden mesajlardaki adresler de modele "(link)" olarak gider. Talimata kural: kişi link istemediyse işaret de yazılmaz.
- **Benchmark hakemi sistem mesajlarını ayırır:** fiyat listesi, ödeme bilgisi, ödeme / sertifika linki ve devir mesajı "otomatik sistem mesajı (asistan yazmadı)" diye etiketlenir. Benchmark 7'de hakem bunları asistanın sözü sanıp "link paylaştı" diye kaldırıyordu (k2, k5, r2, i1, i3). Test sohbetinde, benchmark önizlemesinde ve raporda da "Sistem mesajı" etiketi var. Ölçüt "ya da" ile seçenek veriyorsa biri yeterli.
- Talimat: tek bağış türü ve seçenek varsa tür sorulmaz, adet gelince (yazım hatalı olsa da) doğrudan sipariş; bilinmeyen süre / tarih uydurulmaz, ekip kontrol eder denir; görülmeyen dekont için "aldık" denmez. Personel kendi açtığı bağışı sorarsa staff_my_donations.
- Senaryolar: b3 yerine b7 (eksik bilgiyi sormak da geçer), g1 yerine g7 (fiyat metinde de verilebilir). Eskileri kayıtlı setten kendiliğinden çıkar.

## 1.21.0

- **Ortak Flux AI eklentisine geçiş (Ahmet):** Flux AI kurulu ve anahtarı girilmişse Çağrı Merkezi'nin tüm yapay zekâ istekleri (bağış asistanı, cevap önerisi, test sohbeti, benchmark asistanı ve değerlendiricisi, dekont okuma, AI ile düzenle / senaryo yaz, kişi hafızası özeti) Flux AI üzerinden gider. Anahtarlar, harcama sınırı ve kullanım raporu tek yerde; raporda kaynak "flx-chat", amaç bot / suggest / receipt…
- Ayarlar › Yapay zekâ: Flux AI varken anahtar ve workspace alanları gizlenir, "Flux AI ayarlarını aç" linki çıkar (kayıtlı eski anahtarlar silinmez). Model alanları kalır; boşsa Flux AI'de bu eklentiye seçilen model ya da varsayılan kullanılır. Model listesi Flux AI kataloğundan.
- Flux AI harcama sınırı dolunca (402) benchmark ve arka plan özetleri durur, nedeni yazar; canlı asistan yedek mesaja düşer.
- Claude önbelleği (sabit talimat kırılma noktası), düşünme bloklarının araç turlarında korunması ve maliyet/önbellek tasarrufu gösterimi Flux AI üzerinden de aynı.
- Flux AI yoksa ya da anahtarı yoksa eski bağlantı (kendi anahtarları) aynen çalışır.

## 1.20.0

- **Benchmark değerlendiricisi araç sonuçlarını görür:** önceden yalnız araç adlarını görüyordu; "kayıtlara bakmadı", "onaysız iptal etti" gibi yanlış "kaldı" kararları veriyordu. Artık her araç çağrısının argümanı ve sonucu (kısaltılmış) ve araçların anlamı (set_order kayıt açmaz, cancel_donation confirmed=false yalnız onay sorar, otomatik mesajlar uzunluk kuralına girmez…) hakemin önünde.
- **Örnek geçmiş bağışlar (Ahmet):** test sohbetinde ve benchmark'ta ilk kayıt sorgusunda hiç bağış yoksa iki ödenmiş, sertifikalı örnek bağış eklenir; Arka plan panelinde "Örnek geçmiş bağış" diye görünür. Ödenmiş test bağışlarının artık sertifikası var (önceden sertifika listesi hep boştu, sertifika senaryoları boşuna kalıyordu).
- **E-posta onayı (Ahmet):** kişi e-posta verince asistan önce adresi aynen yazıp "doğru mu?" diye sorar, kişi onaylayınca kaydeder. Doğrulama kodu gönderilince de kodun gittiği numara/adres kişiye yazılır, yanlışsa hemen düzeltebilir.
- **Yanlış araç çağrılarına karşı:** iptal onay sorusu gittiği turda "onaylandı" sayılmaz; o turda modelin ayrıca yazdığı metin gitmez. "(Boş)", "Boş cevap" gibi talimatı yazıya döken cevaplar atılır (boş kalırsa bir kez daha denenir). Aynı turda e-posta onayı da kabul edilmez.
- **Talimat ve araç notları:** kızgın / "kimse dönmedi" diyen kişiye özür + devir; personel gibi konuşan müşteri normal akış; listede olmayan bağış türü tek mesajda; onaylı özet sonrası aynı turda set_order + create_donation; hatırlatıcıda adet sormadan özet; dekont numarasında "ödemeniz onaylandı" denmez; yanlış kodda yeni kod gönderilmez; personelde "bağışçılarım" ile "genel rapor" ayrımı; test bağışında modele "test" denmez.
- **Senaryolar:** 3 yeni (örnek kayıtları listeleme, e-posta doğrulatma, onaylanınca kaydetme); hatırlatıcı senaryosu onay turlu (t7), karşılama devam senaryosu fiyatı metinde de kabul eder (cm). Eskileri kayıtlı setten kendiliğinden çıkar.
- **Benchmark takılması:** site kendine attığı arka plan isteğini artık bekleyerek atar (eskiden 0,01 sn'lik istek HTTPS'te bazen hiç ulaşmıyordu, çalıştırma panel açılana kadar duruyordu). Karşı taraf hemen "kabul edildi" deyip işi arkada sürdürür. Ulaşamazsa tarayıcı 20 sn'de, panel kapalıysa Çağrı Merkezi'nin herhangi bir açık sekmesi devralır; Sistem kontrolü uyarır. Sıradaki cevap işleri de aynı yolla başlar, ulaşamayan iş sıraya geri döner.
- **Eş zamanlı sohbet:** "Aynı anda en fazla iş" sınırı 10'dan 30'a, varsayılan 20 (15-20 kişiyle aynı anda konuşmak için; eski 10 bir kez 20 olur).

## 1.19.0

- **Claude prompt önbelleği aç/kapa (Ahmet):** Ayarlar › Yapay zekâ › Claude prompt önbelleği: 5 dakika (varsayılan), 1 saat ya da kapalı. Araçlar ve talimatın sabit kısmı tüm sohbetlerde ortak okunur (talimatta kişiye özel "Bilinenler" bölümü sona alındı), konuşma da önbelleğe alınır. Maliyet doğru çarpanlarla: 1 saatlik yazma 2x, okuma Opus/Sonnet 5.5'te 0,05x, Fable 5.1'de 0,025x.
- Test sohbeti ve benchmark'ta ayrı önbellek seçimi; cevap altında okunan/yazılan token ve tasarruf, üstte toplam tasarruf. Benchmark çalıştırması seçimi kaydeder; çalıştırma ekranı ve raporda önbellek etkisi.
- **Test sohbetinde dosya (Ahmet):** 📎, sürükle-bırak ya da yapıştır; en çok 5 fotoğraf/PDF/belge, mesajla birlikte. Gerçek hattaki gibi dekont ön kontrolünden geçer (dekontsa ön kontrol cevabı, değilse asistan notu görür); dekont defterine yazılmaz, tekrar dekont yalnız okunarak bulunur.
- **Araç çağrıları logu:** test sohbetinin sağında çağrılan araçlar (özet + tur); tıklayınca yapay zekânın gönderdiği argümanlar ve sistemin sonucu. Cevap altındaki ⚙ etiketleri de tıklanır.

## 1.18.0

- **Benchmark durum paneli (Ahmet):** sonuç ekranında sohbetin sağında test sohbetindeki gibi **Asistanın bildikleri** (hitap, ad soyad, e-posta, seçili bağış, bağış kaydı, personel) ve **Arka plan** (test bağışları, hafıza, zamanlanmış hatırlatıcılar, doğrulananlar, gönderilen kod) görünür. Dar ekranda alta iner.
- **İşlem sonuçları kontrolü (izole):** senaryolar artık sonda durumu da ölçer: bağış açıldı / açılmadı, iptal edildi / edilmedi, sertifika gönderildi, doğrulama kodu gönderildi (WhatsApp / SMS / e-posta), doğrulandı / doğrulanmadı, hatırlatıcı kuruldu, hafızaya yazıldı. Hepsi test ortamında olur: gerçek mesaj, kod, kayıt ya da iptal yok. Senaryoya **başlangıç durumu** verilebilir (ödenmemiş bağış, ödenmiş bağış, doğrulanmış telefon/e-posta, kod kanalı WhatsApp/SMS). Mesajlarda `{kod}` test kodunu yazar. Senaryo düzenleyicide "Başlangıç durumu (test)" ve "Sonda durum" (Bakılmaz / Olmalı / Olmamalı). Sitede kapalı araç bakılmaz.
- **12 yeni senaryo**, yeni grup "İşlem sonuçları (izole)": bağış açma, bağışlarını listeleme, sertifika gönderimi, onaylı iptal, onaysız iptal etmeme, ödenmiş bağışı iptal etmeme, WhatsApp / SMS / e-posta doğrulaması, yanlış kod; personelde başkası adına kayıtlı bağış ve açtığı bağışın bilgisi. Eski p1 yenisiyle değişti.
- AI ile senaryo yaz/düzenle yeni alanları (başlangıç, sonda durum, araçlar, kim yazıyor) bilir.
- **Anthropic workspace ID:** workspace'e bağlı olmayan Anthropic anahtarları için Ayarlar › Yapay zekâ'ya isteğe bağlı alan (`anthropic-workspace-id` başlığı). Bu hata gelirse mesajda nereye girileceği yazar.

## 1.17.0

- **Anthropic (Claude) sağlayıcısı (Ahmet):** Ayarlar › Yapay zekâ'ya Anthropic API anahtarı alanı (panelde maskeli, OpenRouter'la aynı). Claude modelleri doğrudan Messages API ile çağrılır: araçlar, görsel/PDF dekont, düşünme bloklarının araç turlarında korunması, otomatik prompt önbelleği, düşük effort, Opus 5.5 / Opus 5 / Sonnet 5.5 / Fable 5.1'de sunucu tarafı yedek model (refusal fallback). Model listesi Anthropic Models API'den (yoksa bilinen modeller), fiyatlar bilinen tablodan; maliyet önbellek okuma/yazma dahil hesaplanır.
- **Yeni model seçici:** ayarlardaki üç model alanı, test sohbeti, AI ile düzenle ve benchmark aynı paneli kullanır. Birden fazla sağlayıcı varsa önce kısa sağlayıcı seçimi; panelde arama, Tümü / Ücretsiz / aile sekmeleri, giriş fiyatı en az–en çok, satırda "Aile · bağlam" ve "$giriş / $çıkış", altta "Fiyat: 1M token giriş / çıkış". Listede olmayan kimlik elle kullanılabilir. Açık/koyu tema, mobilde alttan açılır.
- Benchmark ücret tahmini seçilen sağlayıcının fiyatını kullanır. Sistem kontrolü iki sağlayıcıyı da bilir (seçili modelin anahtarı yoksa uyarır).

## 1.16.0

- **Benchmark güncel (Ahmet):** 13 yeni hazır senaryo ve 3 yeni grup. Güncel bilgi ve araçlar: IBAN/saat/projeler araçtan, hatırlatıcı, "bir daha yazmayın", hitap kaydı. Kayıt, iptal ve dekont: bağış iptali onayla, kartla ödeme linki, sertifikayı yeniden gönderme, dekont no ile tekrar kontrolü (`receipt_check`), personel gibi konuşan müşteri. Personel asistanı: başkası adına bağış, bağışçılarım, genel rapor (Flux Roles'de personel aracı açık ilk kullanıcı olarak yazar). Eski "Geçmiş bağış sorgusu" artık kayıt aracına bakmayı bekliyor (kayıtlı setteki eski sürümü kendiliğinden yenisiyle değişir).
- Yeni beklentiler: **araç çağrılmalı / çağrılmamalı** (a|b seçenekli; sitede kapalı araç bakılmaz) ve **kim yazıyor** (bağışçı / personel). Senaryo düzenleyicide de var.
- **Ücret tahmini:** Yeni çalıştırmada grup başına ve toplam tahmini ücret (asistan + değerlendirici), geçmiş çalıştırmaların senaryo başına ortalama token'ı × seçilen modellerin fiyatı; geçmiş yoksa varsayılan tahmin. Limit aşılacaksa uyarır.

## 1.15.0

- **Dekont defteri (Ahmet):** gelen her dekont gönderen + dekont no (küçük harf, Türkçe karakter katlama, boşluk/tire temizliği) ve dosya hash'iyle veritabanına kaydedilir (yeni tablo `flxc_dekonts`). Aynı dekont tekrar gelirse kişi baştan uyarılır, ekibe haber gider; aynı kişinin aynı bağış için tekrar göndermesi sahte sayılmaz. Asistan aracı `receipt_check` (dekont no ile bakar). Ortak sözleşme `flx_dekont_register` / `flx_dekont_seen` (Flux Donate'ın site yüklemeleri için).
- **Alıcı adı tutmazsa direkt uyarı:** dekonttaki alıcı adı ya da görünen IBAN derneğin değilse tek başına yeter (önceden ikisi birden gerekiyordu), bağış açılmamış olsa da uyarılır.
- Bilgiler tutsa bile cevap "Dekontunuzu aldık, teşekkür ederiz 🌱 Ödemenizle ilgili size en kısa zamanda dönüş yapacağız"; bağış yetkili onayı bekler. İleride AI onayı için `flxc_receipt_auto_approve` filtresi (varsayılan kapalı).
- Sohbet başlığında "Sertifika oluştur" yerine **Bağışlar ve sertifikalar** düğmesi: kişinin bağış ve sertifikaları (gizli numarada bu sohbette açılan bağış da). Menüdeki "Sertifika oluştur" da bu listeye döndü.
- Menüler pencerelerin üstünde açılır.

## 1.14.0

- **WhatsApp raporları (Ahmet, Flux Roles 1.1.0):** Çağrı Merkezi › Raporlar (yetki `flx_reports_manage`). Her rapor: kime (rapor alabilen herkes `flx_reports_receive`, genel raporu görebilenler `flx_reports_view`, seçilen kişiler), içerik (kişinin kendi referans bağışları ya da genel toplam + personel bazında), dönem (bugün, dün, son 7 gün, bu ay), sıklık (her gün / hafta / ay) ve saat, motivasyon metinleri (her satır bir metin, biri rastgele eklenir). Önizleme, "Bana test gönder", "Şimdi herkese gönder". Hazır iki rapor (kapalı): personel günlük, idareci haftalık genel.
- Gönderim seçilen saatte (WP-Cron tek seferlik tetik + saatlik tetik + panel/webhook dürtmesi arka planda), çalışma başına en fazla 25 kişi, aynı güne ikinci kez gitmez; saatinden 6 saat geçtiyse o gün atlanır.
- Personel numaralarıyla konuşmalar (araçlar, raporlar, bildirimler) ekip grubuna yansıtılmaz.

## 1.13.0

- **Personel asistanı (Ahmet, Flux Roles 1.1.0):** yazan numara Profil › WhatsApp numarası kayıtlı bir kullanıcınınsa asistan onu personel olarak tanır ve yetkisine göre ek araç açar (rol adına bakılmaz, yeni roller de çalışır; yetki her çağrıda yeniden okunur). Numara yalnız WhatsApp kimliğinden; gizli numarada kodla doğrulanmış numara.
  - `flxb_create_ref`: "Mehmet Kaya adına 2 fidan" → özet ve onaydan sonra bağış kaydı (referans = personel); bağışçıya WhatsApp'tan "talebiniz alındı" mesajı (Ayarlar'dan değişir) ve ödeme linki/havale bilgileri gider. Bağışçı cevap yazarsa asistan bağışı bilir. Link yeniden gönderme.
  - `flxb_donors_own` / `flxb_view_own`: "bağışçılarım": referansı olan bağışlar, durum ve toplam.
  - `flx_reports_view`: genel rapor (ödenen, bekleyen, iptal, personel bazında).
  - `flxb_ref_notify`: referansı olduğu bağış ödenince ya da iptal olunca personele WhatsApp bildirimi (sipariş başına tek).
- Test sohbetinde "Kim gibi yazıyorsunuz?": personel seçilerek denenir (kayıt açılmaz, mesaj gitmez).
- Ayarlar › Yapay zekâ › Personel (Flux Roles): aç/kapat, bildirim, bağışçıya ilk mesaj.

## 1.12.0

- **Günlük sınır dolunca kişiye cevap (Ahmet):** asistan o sohbetin günlük cevap sınırına ulaşınca kişiye günde bir kez "Mesajınızı aldık, size en kısa sürede dönüş yapacağız 🌱" gider, sonra sohbet ekibe devredilir (önceden kişi cevapsız kalıyordu). Metin Ayarlar › Yapay zekâ › "Günlük sınır dolunca gönderilen mesaj".
- **Sohbet ⋮ › Asistan kullanımı:** o sohbetin bugünkü ve toplam yapay zekâ isteği, giriş/çıkış token ve maliyeti (USD), son 7 gün tablosu, bugünkü cevap sayısı / sınır. "Günlük sınırı sıfırla" asistanı yeniden cevap verir hale getirir. Yöneticiler sohbet başına günlük cevap sınırını buradan değiştirebilir (tüm sohbetler için). Asistan elle açılınca dolmuş sınır da sıfırlanır.
- Telefonda açılan pencereler bazen yana kaymış görünüyordu; düzeldi.

## 1.11.2

- **Telefonda yeni yazma alanı (Ahmet):** mesaj kutusu üstte tam satır; altında emoji, ekle, yapay zekâ ve mikrofon; gönder düğmesi sağ altta (boşken sönük). Flux Admin alt barı varken yazma alanının altındaki fazladan boşluk kalktı. Bilgisayarda düzen aynı.

## 1.11.1

- **IBAN 4'lü gruplu gider (Ahmet):** WhatsApp'taki ödeme mesajında IBAN "TR85 0004 6008 8588 8000 1346 83" biçiminde (önceden bitişikti).

## 1.11.0

- **Asistan ödenmemiş bağışı iptal eder (Ahmet):** kişi "siparişimi iptal edebilir misin" deyince ekibe aktarılmaz. Yeni araç cancel_donation önce onay sorusunu sistemden gönderir: üstte "Neden iptal etmek istediğinizi paylaşmak isterseniz biz buradayız…", altında "*Fidan bağışı · 3 adet* (450 ₺) kaydınızı iptal etmemi onaylıyor musunuz?" (Ayarlar › Yapay zekâ › Bağış iptali onay sorusu, {bagis} {tutar}). Kişi onaylayınca WooCommerce siparişi "İptal edildi" olur (Flux Donate kayıtları da iptale döner; siparişe not, sohbete sistem notu, söylediyse nedeniyle). Yalnız kişinin kendi kayıtları ve yalnız ödeme bekleyenler; ödenmiş bağışta asistan ekibe aktarır. Birden fazla bekleyen bağış varsa hangisi olduğunu sorar. Testte yalnız test bağışı iptal olur. İstatistikte "Asistanın iptal ettiği bağış".

## 1.10.3

- **Site ile asistan aynı IBAN'ı verir:** Flux Donate 2.2.2+ varsa hesap ortak `flxb_bank_account()` fonksiyonundan alınır (havale yöntemi → WooCommerce BACS → tema; bağış ve ödeme sayfası da bunu kullanır). Çağrı Merkezi › Ayarlar › Ödeme doluysa o önce gelir; eski Flux Donate'ta 1.10.2 sırası yedek kalır.

## 1.10.2

- **IBAN WooCommerce havale ayarından (Ahmet):** asistanın alıcı adı, IBAN ve banka bilgisi sırayla şuradan gelir: Çağrı Merkezi › Ayarlar › Ödeme (doluysa, yalnız asistan için ezer) → WooCommerce › Ödemeler › Havale / EFT (Flux Donate havale yöntemi; siparişlerde görünen hesap) → WooCommerce'in kendi Banka havalesi (BACS) hesapları → tema banka hesabı. payment_info, ödeme mesajları, {bank} metni ve dekont kontrolü aynı kaynağı kullanır.

## 1.10.1

- **Test sohbetinde kod gönderilmez, panelde görünür (Ahmet):** test sohbetinde ve benchmark'ta doğrulama kodu hiçbir kanala gitmez (önceden de gitmiyordu ama sabit 123456 idi ve model "gönderdim" diyordu). Artık her seferinde rastgele 6 haneli kod üretilir ve sağdaki Arka plan panelinde (numara, kanal, geçerlilik saati) görünür; kodu sohbete yazarak doğrulamayı deneyin.
- **Test bağışları:** testte açılan her bağış (birden fazla olabilir) Arka plan › Test bağışları listesinde numarası, tutarı, ödeme yolu ve durumuyla görünür. "Ödendi say" / "Beklemeye al" / "İptal et" yalnız test durumunu değiştirir; asistan sonraki mesajda bağışı o durumda görür ("bağışım ne durumda" testi). Kayıt sorgusu ve doğrulama sonrası kayıtlar testte yalnız bu test bağışlarını döndürür; gerçek kayıtlara bakılmaz, gerçek sisteme hiçbir şey yazılmaz.
- Test sohbeti, AI ile düzenle'nin kendi testi ve Benchmark aynı test altyapısını (flxc_bot_sandbox_turn) kullanır; AI ile düzenle testinde kod yapay zekâya da verilir ki doğrulamayı deneyebilsin.

## 1.10.0

- **Güncel bilgiler tool'dan (Ahmet):** bağış türleri/projeler, paketler ve fiyatlar (`donation_options`), havale/IBAN ve bağış sayfası (`payment_info`), tarih/saat/mesai (`current_time`) artık talimata gömülmüyor; asistan gerektiğinde aracı çağırıp o anki değeri alır. Talimatın başında hangi bilginin hangi araçta olduğunu anlatan kısa "Sistem" bölümü var. Birden fazla tür/proje olduğunda asistan hangisine katılmak istendiğini sorar. Başka eklentiler `flxc_bot_info_tools` / `flxc_bot_info_tool` ile araç ekleyebilir.
- **AI ile düzenle yenilendi (Ahmet):** yapay zekâ sohbeti adla/numarayla bulur, izninizle son 10 (gerekirse 20) mesajı okur, talimatı günceller, sağdaki panelde taslağı kendisi test eder ve onayınıza sunar. "Bu oturumda hep izin ver" seçeneği var. Oturumlar ayrı saklanır: yeniden açma, ad değiştirme, arşiv, silme, arama ve filtre. Sayfa açılışta boş başlar. Hostinger süre sınırı için her istek tek iş yapar, tarayıcı adımları sırayla ister.
- **Test sohbetinde arka plan:** sağ panelde asistanın hafızası, kurulan/onay bekleyen hatırlatıcılar ve doğrulanan numara/e-postalar görünür.

## 1.9.2

- **Sertifika sorusunda devretmez (Ahmet):** kayıt sorgusu bağışın durumunu ve sertifikasını da söyler (hazır / ödeme onaylanınca oluşturulur / henüz oluşturulmadı) ve linkini verir. Bu sohbette açılan bağış numara gizli olsa da kişinin sayılır. Ekibe aktarma yalnız kişi isterse ya da araçlarla çözülemeyen bir sorunda.
- **Sertifikayı yeniden gönderme:** yeni araç certificate_resend: kişinin kendi sertifikası bu WhatsApp sohbetine linkiyle ya da doğrulanmış/kayıtlı e-postasına (Flux Certificates gönderimi; kara liste ve kotalar Flux Mail'de) gider. Saatte en fazla 3. İstatistikte "Yeniden gönderilen sertifika".
- **Doğrulamadan sonra kayıtlar hemen döner:** kod doğrulanınca kayıtlar aynı cevapta gelir, asistan ekibe aktarmadan cevaplar.
- **Başarısız deneme sınırı:** gönderilemeyen, yanlış ya da süresi dolan kodlar sayılır; 3'te (ayar) kişiye "Bir sıkıntı oldu, ekibimiz en kısa sürede sizinle iletişime geçecek" gider, ekibe haber verilir. Asistan diğer sorulara cevap vermeye devam eder ama 24 saat kayıt bilgisi vermez, yeni kod göndermez. Her deneme sohbette sistem notu olarak (neden gitmediğiyle: "Bu numarada WhatsApp yok", SMTP vb.) görünür.
- **Numara eşleştirme:** doğrulanan ya da yazan numara sitedeki kayıtlarla her yazılışta (+90…, 0…, boşluklu) ve WooCommerce sipariş telefonuyla eşleşir; bağışların sertifikaları da kişinin sayılır.
- **Asistanı açınca bekleyen mesaj cevaplanır:** sohbette asistan açıldığında son 30 dk içinde cevapsız kalan mesaj varsa asistan onu cevaplar (kişinin mesajı tekrar yazması gerekmez).

## 1.9.1

- **Dekonttan sonra asistan açık kalır (Ahmet):** dekont gelince sohbet artık ekibe devredilip asistan susturulmuyor. Kişiye ön kontrol sonucu söylenir, ekibe haber gider (sohbet okunmadı olur), asistan sonraki sorulara cevap vermeye devam eder; onay bekleniyor diye yetkiliye aktarmaz.
- **Asistan bağışın güncel durumunu görür:** ekip ödemeyi onaylayınca (Flux Donate'ta "tamamlandı") asistan kişiye bağışının onaylandığını söyler, yetkiliye aktarmaz. Dekont geldi ve onay bekleniyorsa bunu söyler.
- **Dekont okuma düzeldi:** seçili model (ya da asistan modeli) görsel okuyamıyorsa görsel okuyan ucuz bir modele (google/gemini-2.5-flash-lite) kendiliğinden geçilir; "404 No endpoints found that support image input" ve "Model dosyayı okuyamadı" durumunda yedek modelle bir kez daha okunur. PDF'ler OpenRouter'ın metin çıkarıcısıyla her modelde okunur; ekran görüntüsü de dekont olarak okunur.
- **Dekont olmayan dosya:** kişi alakasız bir fotoğraf/dosya gönderirse sabit metin yerine asistan ne olduğuna göre kısa, esprili bir cevapla konuyu fidana bağlar.

## 1.9.0

- **Gizli numarada telefonla doğrulama (Ahmet):** numarası görünmeyen kişi kayıtlarını sorunca asistan önce telefon numarasını ister; o numaraya WhatsApp'tan (gitmezse SMS, Flux Mail) 6 haneli kod gider, kişi kodu yazınca o numaranın kayıtları gösterilir. İstemezse e-posta ile doğrulama. Numarası görünen kişiye ek doğrulama sorulmaz. "Telefon numaram yetmiyor mu" diyene aynı cümle tekrarlanmaz, neden açıklanır.
- **Ortak kod doğrulama:** e-posta, WhatsApp ve SMS aynı akış (flxc_verify_send / flxc_verify_check): kod 5 dk geçerli (ayar), saatte 3 kod, 5 deneme. Araçlar verify_send / verify_check (eski email_verify_* yerine). Ayarlar › Yapay zekâ › Kayıt sorgusunda kod doğrulama: süre, telefon kanalı, mesaj metni.
- **İstatistik:** gönderilen kod (e-posta/WhatsApp/SMS), doğrulanan (telefon/e-posta), yanlış, süresi dolan, gitmeyen kodlar AI asistan özetinde.
- **Bekleme mesajı erken gitmiyor (Ahmet):** kişinin mesajından 30 sn geçmeden (ayar) "Beklettiğimiz için üzgünüz…" gitmez. Geçici yapay zekâ hatasında (ağ, 429, 5xx, boş cevap) önce bir kez daha denenir; bu arada cevap giderse bekleme mesajı hiç gitmez.

## 1.8.2

- **Gereksiz bekleme mesajı ve sonrasında susma düzeldi:** eski sürümde sunucunun yarıda kestiği bir cevap işi kayıtta "çalışıyor" kalmıştı; kişi saatler sonra yazınca bekçi o eski iş için bekleme mesajı gönderip sohbeti ekibe devrediyor, asistan da yeni mesajları cevaplamıyordu. Artık eski yarım iş sessizce kapatılır ve asistan cevaplanmamış soruyla birlikte yeni mesajı normal cevaplar; bekçi yalnız son 15 dk içinde ölen işler için yedek mesaj gönderir.
- Yedek mesaj artık asistanı kapatmıyor: ekibe haber verilir (30 dk'da bir), sohbet okunmadı olur, kişi yeniden yazınca asistan tekrar dener. Kişinin son mesajından sonra zaten cevap gittiyse yedek mesaj gitmez.

## 1.8.1

- **İşçi kuralları (Ahmet):** aynı anda en fazla 10 iş (eski kurulumda 5 kayıtlıysa bir kez 10 yapılır). 5 işçi her zaman yalnız cevaplara ayrılır, özet ya da hatırlatıcı almaz (ayar: "Yalnız cevaplara ayrılan işçi"). Cevap yazılan ya da sırada bekleyen kişi varken hiçbir işçi özet başlatmaz.
- **Özetler gece:** sohbet özetleri yalnız 03:00–05:00 arasında yapılır (site saat dilimi; Ayarlar › Yapay zekâ › Özet saatleri). Elle "Şimdi özetle" her zaman çalışır; hatırlatıcılar kendi saatinde gider.

## 1.8.0

- **Asistan artık sessiz kalmıyor (canlı hata):** bağış onayından sonra ("Başlat") asistan "yazıyor…" gösterip cevap vermiyordu. Neden: bir cevap turunun toplam süre sınırı yoktu; yavaş/düşünen modelde araç çağrısı + ikinci istek paylaşımlı hostingin ~60 sn sınırını aşıyor, sunucu işlemi sessizce kesiyordu; sonraki "Ne oldu", "?" mesajları aynı adımı yeniden deneyip yine kesiliyordu. Artık bir tur en fazla ayardaki süre (varsayılan 50 sn) sürer.
- **Yedek mesaj:** yapay zekâ hata verirse, süre dolarsa, boş cevap dönerse, beklenmeyen bir PHP hatası olursa ya da işlem sunucuda yarıda kesilirse kişiye "Beklettiğimiz için üzgünüz. En kısa sürede size dönüş yapacağız. Anlayışınız için teşekkürler." gider ve sohbet ekibe devredilir (bildirim gruba/numaralara). Metin Ayarlar › Yapay zekâ'dan değişir. Yarıda ölen turları bekçi bulur; aynı adım bir daha denenmez.
- **Sıra:** aynı anda en fazla "Aynı anda en fazla iş" kadar cevap yazılır; fazlası sıraya alınır, boşalan işçi hemen alır. Sırada 3 dk'dan çok bekleyen kişiye de yedek mesaj gider.
- Sınıra takılıp yarıda kesilen cevap son tam cümlede/satırda biter ("başlat" gibi yarım kelime gitmez); cevap sınırı 800'den 1500 token'a çıktı. Asistan özeti satır sonlarını koruyarak yazar.
- **Kendi ödeme linki:** asistan bağışı açınca kişiye genel bağış sayfası yerine kendi siparişinin linki gider. Havalede banka bilgilerini ve siparişin açıklama kodunu gösteren sipariş sayfası, kartta doğrudan kart ödeme sayfası, kişi seçmediyse ikisini de sunan ödeme sayfası. "Kartla ödemek istiyorum / havale yapacağım / linki tekrar atar mısın" denince yeni `payment_link` aracı yöntemi değiştirip linki yeniden yollar. Linkler sipariş anahtarıyla açılır, bu eklentinin siparişlerinde giriş istenmez. "Kartla ödeme çok yakında" metni kalktı.
- **Fidan başına ayrı isim:** asistan birden fazla fidanda her fidanın farklı kişi adına olabileceğini bilir, isimleri ayrı ayrı kaydeder.
- **E-postayla eski kayıtlar:** kişi başka e-postayla yaptığı bağışları sorarsa o adrese 6 haneli doğrulama kodu gider (Flux Mail şablonu); kod doğrulanınca e-posta kişiye bağlanır ve kayıt sorgusuna eklenir. Bilgilendirme izni sorulur, hafızaya "e-posta ile bilgilendirme: evet/hayır" yazılır; "hayır" denirse e-posta bağış kaydına yazılmaz, "evet" ise bağış özetinde belirtilir.
- **Hatırlatıcılar:** kişi "her ay hatırlat", "her ay son projelerinizi gönderin", "her ay benden bir fidan iste" diyebilir. Asistan özet → revize → onayla kurar, konu değişirse üstelemez. Saat yoksa 09:00, gün yoksa ayın 1'i. Zamanı gelince arka plan işi asistanı çalıştırır, mesaj gider; bağış hatırlatıcısında seçim hazır özetle sorulur. Panelde ☰ ve sohbet ⋮ › Hatırlatıcılar; kişi "hatırlatıcıyı durdur" diyebilir. Arka plan sekmesinde ve çalışma özetinde görünür.
- Ayarlar › Yapay zekâ › Hatırlatıcılar ve e-posta doğrulama (aç/kapat, varsayılan saat, kişi başına sınır).

## 1.7.0

- **Kişi hafızası:** asistan kişinin kalıcı bilgilerini anahtar/değer olarak hatırlar (nasıl hitap edilmek istediği, eşinin/yakınlarının adı, numaralar, tercihleri) ve her cevapta okur. Kişi değiştirirse günceller, isterse siler ("bilgilerimi silin"). Hitap, ad soyad ve e-posta da hafızaya yazılır; sohbet geçmişi temizlense de hatırlanır. Yalnız yöneticiler sohbet ⋮ › Kişi hafızası'ndan görür, düzenler, ekler, siler, "Şimdi özetle" der.
- **24 saat sonra özet:** sohbet ayardaki süre (varsayılan 24 saat) sessiz kalınca konuşma kodla değil yapay zekâyla özetlenir; önemli bilgiler (alışkanlıklar vb.) tarih ve saatle hafızaya yazılır, kişi başına son 3 özet kalır. İlk açılışta yalnız son 7 günün sohbetleri özetlenir.
- **Arka plan işleri:** AI asistan › Arka plan sekmesi çalışan/sırada işleri, süreyi, token ve maliyeti canlı gösterir. Aynı anda en fazla 5 iş (ayar); öncelik her zaman yazan kişiye cevap, özetler yalnız cevap yazılan sohbet yokken başlar. Yarıda kalan özet kendiliğinden yeniden denenir; OpenRouter bütçe hatasında özetler 30 dk durur.
- **Hafıza raporu:** AI asistan › Hafıza sekmesi: hafızası olan kişi, kalıcı bilgi, özet sayısı, toplam token ve en çok hafızası olan kişiler. Çalışma özetinde hafıza token'ı ve özetlenen sohbet sayısı.
- **Kara liste:** kişi "bana mesaj atmayın" gibi bir şey yazınca asistan tek bir onay mesajı gönderip kişiyi kara listeye alır. Kara listedeki kişiye panelden, asistandan, otomasyondan mesaj gitmez; asistan onunla hiç konuşmaz. Diğer FluxWP eklentileri için ortak kontrol: `flx_contact_blocked( $phone, $email, $channel )` / filtre `flx_contact_blocked`. Liste ☰ › Kara liste'de (yalnız yönetici); sohbet ⋮ ile eklenir/çıkarılır, çıkarınca her şey yeniden çalışır.
- **Kendi kayıtları:** asistan kişi sorunca bağışlarını, fidanlarını ve sertifikalarını söyler. Kayıtlar yalnız yazan WhatsApp numarasına göre kodla bulunur; model başka numara ya da e-posta veremez, gizli numarada kayıt gösterilmez.
- **Sitedeki içerikler:** asistan "fidan dikimi geçen bir gönderiniz var mı" gibi sorularda sitede arar. Yalnız yayında, şifresiz, seçili türlerdeki (varsayılan yazılar ve projeler/etkinlikler gibi arşivli türler) içerik döner; hariç kategori/etiketler ve "Asistan bu içeriği paylaşmasın" işaretli içerikler hiç dönmez. Filtreyi kod uygular.
- **Ekip grubu:** Ayarlar › Yapay zekâ › Ekip grubu'nda seçilen WhatsApp grubuna gelen mesajlar ve cevaplar (asistan, ekip, otomatik) "*Müşteri:* / *AI Chat:*" biçiminde akar. Kişinin son grup mesajı en son mesajsa ve 12 dk içindeyse yeni satırlar ona eklenerek düzenlenir, değilse yeni mesaj gider. Devir bildirimi de gruba gider, bildirim numaraları @etiketlenir.

## 1.6.6

- Benchmark'ta ölü bekleme kalktı: bir adım sunucu tarafından yarıda kesilirse (paylaşımlı hostingde ~60 sn sınırı) eskiden kilidin düşmesi için ~3 dk bekleniyordu. Artık her adım en çok ~45 sn sürüyor (PHP süre sınırı daha kısaysa ona göre), yapay zekâ geç kalırsa adım temiz biçimde bitip hemen yeniden deneniyor; kesilen adım ~1 dk içinde kendiliğinden devam ediyor. Aynı adım üç kez yarıda kalırsa senaryo hatalı sayılıp sıradakine geçiliyor, çalıştırma asılı kalmıyor.
- Sayfa açıkken sunucu ilerlemezse tarayıcı 90 sn yerine 30 sn sonra devralıyor.
- AI asistan ve ayar sayfalarında emojiler kırık resim yerine sistem emojisi olarak görünüyor.

## 1.6.5

- Benchmark, yapay zekâ hesabı istekleri reddedince (OpenRouter günlük bütçesi/kredisi doldu, anahtar geçersiz, hız sınırı) artık kalan senaryoları hatalı sayıp bitirmiyor: duruyor, nedenini çalıştırmanın üstünde ve raporda gösteriyor, "Devam et" ile kaldığı senaryodan sürüyor.
- Temsilci isteği anahtar kelimeleri daha akıllı: "temsilciniz çok ilgiliydi" gibi övgü artık anında devir yapmıyor. Tek kelime ("temsilci", "yetkili") yalnız kısa mesajda ya da istek bildiren bir sözle ("bağlayın", "görüşmek istiyorum", "var mı?") aynı cümlede sayılır; çok kelimeli ifadeler ("canlı destek", "bir insanla") her zaman sayılır.
- Değerlendirici model boş cevap verirse (düşünen modeller sınırı düşünmeye harcayabiliyor) bir kez daha geniş sınırla soruluyor.

## 1.6.4

- **Sohbet dışa aktarma:** sohbet ⋮ › Sohbeti dışa aktar; yöneticiler liste ⋮ › Sohbetleri dışa aktar ile toplu (7/30/90 gün ya da tümü; tümü, asistanın ya da temsilcinin yazdıkları). .md ya da JSON; her mesaj saat ve yazanla, dekont kontrol sonucuyla. Telefon ve e-postalar varsayılan maskeli.
- **Devirde ekibe WhatsApp bildirimi:** Ayarlar › Yapay zekâ › Devirde bildirim gidecek numaralar. Kişinin adı, telefonu, nedeni, son mesajları ve panel linki gider; sonuç sohbete sistem satırı olarak düşer. Bildirim yankısı sohbet açmaz, bu numaralarla asistan konuşmaz.
- **Sohbet geçmişini temizle** (yalnız yönetici, "TEMİZLE" yazarak onay): bizdeki mesajlar ve dosyalar silinir, bağışa bağlı dekontlar istenirse kalır; Evolution'daki eski mesajlar bir daha içe aktarılmaz.
- Asistan listede olmayan bağış türünde (kurban, zekât vb.) hemen devretmiyor: o türde yardımcı olamadığını söyleyip listedeki türleri öneriyor, ısrar edilirse devrediyor. Benchmark'a iki senaryo eklendi (toplam 38).

## 1.6.3

- AI asistan › Sohbetler'in üstünde **asistanın çalışma özeti**: harcama (USD), cevaplanan soru, cevap verilen kişi, bağışa yönlendirilen kişi (fiyat listesi giden), açılan ve onaylanan (ödendi) bağış + tutar, okunan dekont (uyumlu/uyuşmayan), ekibe devir, token. Bugün / 7 gün / 30 gün / Bu ay / Tümü; 30 sn'de bir canlı yenilenir. **Detay**: model başına istek, token ve maliyet, gün gün tablo. Test sohbeti ve benchmark sayılmaz.
- Benchmark'ın üstünde **tüm benchmark'ların toplamı**: toplam harcama, giriş/çıkış token, çalıştırma, senaryo başarısı, model sayısı; çalışırken canlı. **Detay**: model ve rol (asistan / değerlendirici) başına çalıştırma, başarı, istek, token, maliyet ve senaryo başına maliyet.
- Her çalıştırmada **Rapor indir** (.md, ayrıca JSON): ayarlar, model kırılımı, grup sonuçları, tam talimat, her senaryonun sohbeti, araç çağrıları (argüman ve sonuç), tur başına kullanım, kontroller, son durum ve değerlendirici modelin tam isteği ile ham cevabı. Yapay zekâya verip talimatı/benchmark'ı iyileştirmek için.

## 1.6.2

- **Dekont ön kontrolü (yapay zekâ):** kişi fotoğraf ya da PDF dekont gönderince görsel okuyabilen model tutarı, alıcıyı, IBAN'ı, tarihi ve göndereni okur, sohbetin bağış kaydıyla karşılaştırır. Kişiye sonucu söyler (uyumlu, tutar tutmuyor, alıcı farklı, dekont değil), dosyayı bağış kaydına ekler, ödendi işaretlemez. Uyumluysa son onay için sohbeti ekibe devreder; tutmuyorsa kişi düzeltebilsin diye asistan açık kalır. Sonuç sohbette mesajın altında "AI · …" etiketi ve sistem notu olarak görünür. Ayarlar › Yapay zekâ: aç/kapat ve "Dekont okuma modeli".
- **Silinen mesajlar:** kişinin "herkesten sildiği" mesaj bizde silinmez; içeriği "Kişi bu mesajı sildi" etiketiyle ekibe görünür. Asistan ve AI önerileri silinen mesajları okumaz.
- Benchmark **devam senaryoları:** her mesajın yazanı (müşteri ya da asistan) seçilebilir. Asistan mesajı olan senaryoda yazılı geçmiş hazır verilir, asistan yalnız en sondaki müşteri mesajına cevap verir. Her gruba bir devam senaryosu eklendi (toplam 36); kayıtlı senaryo setlerine de kendiliğinden eklenir.
- Senaryo düzenleyici WhatsApp görünümünde: balon balon mesaj, + ile müşteri/asistan mesajı ekleme, − ile silme, sürükle-bırak sıralama, yazanı tek tıkla değiştirme (dokunmatikte de çalışır).
- Benchmark sonucunda soldan senaryo seçince sayfa en üste atmıyor, WhatsApp önizlemesine kayıyor; canlı izlerken yenilemeler kaydırma yerini korur.

## 1.6.1

- Düzeltme: sayfa yenilenince benchmark çalıştırmaları (ve sürümler gibi diğer veriler) görünmüyordu. Sunucu önbelleği (LiteSpeed) eklentinin REST cevaplarını saklıyordu. Artık hiçbir cevap önbelleğe alınmıyor, istekler de önbelleği atlıyor. Kayıtlar silinmemişti; güncellemeden sonra yeniden görünür.
- Benchmark artık sunucuda arka planda çalışıyor: sayfa kapalıyken de sürüyor. Sayfa yalnız izliyor; yenileyince çalışan benchmark canlı izlenmeye devam ediyor. Sunucu kendi kendine ilerleyemezse sayfa açıkken tarayıcı devralıyor.
- Çalıştırma kaydı açılamazsa hata gösteriliyor; sistem kontrolü benchmark tablolarını da denetliyor.

## 1.6.0

- AI asistan › **Benchmark**: 6 grupta 30 hazır çok mesajlı senaryo (karşılama, bağış süreci, temsilciye devir, gereksiz devir yapmama, bilgi uydurmama, cevap kalitesi). Beklentiler devir, bağış kaydı, fiyat listesi, adet, içermeli/içermemeli ve değerlendirici model ölçütüyle tanımlanır. Senaryolar eklenebilir, düzenlenebilir, AI ile yazdırılabilir.
- Çalıştırma: asistan modeli, talimat sürümü, değerlendirici model, USD ücret limiti ve grup seçimiyle başlar. Adım adım ilerler (Hostinger'da uzun istek yok), durdurulup devam ettirilebilir. Sonuçlar sorular ve cevaplarla kaydedilir; grup başına başarı ve ekibe devir doğruluğu (doğru/gereksiz/kaçan devir) görünür.
- Test sohbeti ve benchmark, temsilci ifadelerinde gerçek hat gibi modeli beklemeden devreder.

## 1.5.3

- Kritik düzeltme (1.5.0'dan beri): gerçek sohbetlerde asistan hitap, bağış seçimi ya da bağış kaydı kaydederken hata verip duruyordu (test sohbeti etkilenmiyordu).
- Yerleşik talimat: asistan artık ilk mesajda isim sormuyor; "{marka} olarak size yardımcı olmaktan mutluluk duyarız, size nasıl yardımcı olabiliriz? Örneğin "Fidan bağışı yapmak istiyorum"" gibi karşılıyor. Ad soyad bağış adımında isteniyor.

## 1.5.2

- AI asistan › AI ile düzenle: yalnız burada geçerli model seçimi, her cevabın altında token ve fiyat, sağ üstte konuşma toplamı.
- Düzenleyici ve test sohbetinde "Bu ay toplam (tüm AI)" satırı: ay içindeki tüm yapay zekâ isteklerinin giriş/çıkış token, maliyet ve istek sayısı (giriş/çıkış ayrımı 1.5.2'den itibaren sayılır).

## 1.5.1

- Düzeltme: bazı gizli numaralı (@lid) sohbetlerde telefon alanında gizli kimliğin rakamları duruyordu; cevaplar o "numaraya" gidip "Evolution API'yi güncelleyin" hatasıyla düşüyordu. Kayıtlar bir kez onarılır, gönderim doğru adrese yapılır.
- Gizli numaralı kişinin gerçek numarası webhook'ta (remoteJidAlt, senderPn, participantAlt/Pn; key içinde ya da mesaj kökünde) gelirse sohbete yazılır ve cevaplar o numaraya gider; ayrı sohbet açılmaz.
- Evolution API sunucu sürümü algılanır: "Bağlantıyı test et" sonucunda görünür, 2.3'ten eskiyse sistem kontrolü uyarır, @lid hata mesajında sürüm yazar.
- AI asistan › Test sohbeti: sağ üstte canlı giriş/çıkış token, maliyet ve istek sayısı; yalnız test için model seçimi (ana ayar değişmez); her cevabın altında o turun token ve fiyatı.

## 1.5.0

- Düzeltme: asistanın gönderdiği mesajlar panelde geç görünüyordu. Sohbet listesi her satır için bağış türlerini yeniden sorguluyordu (yavaş eşitleme); artık istek başına bir kez. Eşitleme penceresi genişledi, açık sohbette en son mesaj id'sine göre de bakılır.
- Asistan cevap hazırlarken listede ve sohbet başlığında "Asistan yazıyor…"; asistan çalışırken panel 3 sn'de bir yenilenir.
- Düzeltme: gönderilen mesaj kısa süre iki kez görünüyordu (gönderim yanıtı ile eşitleme yarışı).
- Düzeltme: panel teması açıkken alt mesaj kutusu ekranın altına kayıyordu (iOS klavye uyumu yalnız klavye açıkken çalışır).
- Düzeltme: bağış kaydı formunda onay kutuları görünmüyordu, adet −/+ düğmeleri kaymıştı.
- Okunmamış sohbet listede hafif çerçeve ve sayı ile öne çıkar; yeni mesaj gelen sohbet kısa bir vurguyla en üste gelir.
- Asistan cevaptan önce son 24 saatteki (ayarlanabilir) tüm mesajları okur.
- "Temsilciye bağla" gibi ifadelerde asistan modeli beklemeden devir mesajını gönderir ve o sohbette kapanır; ifadeler ayardan değişir.
- Asistan varsayılan olarak tüm sohbetlerde açık (eski sohbetler dahil); test modu: yalnız belirli numaralara cevap.
- Sohbet listesinde "Asistan" filtresi.
- Yeni "AI asistan" sayfası: asistanın sohbetleri, sürümlü talimat (görüntüle, düzenle, fark, yayına al), hazır mesaj şablonları, ek belgeler ({{belge:kisa-ad}} ve read_doc), talimatı yapay zekâ ile düzenleme (fark + onay) ve WhatsApp'a gitmeyen test sohbeti.

## 1.4.2

- Yenilenen FluxWP logosu.

## 1.4.1

- Yönetim menüsünde ortak FluxWP logosu (assets/icon.svg, renkli).

## 1.4.0

- WhatsApp hattı artık Flux Mail'deki bağlantıyı kullanabilir (Ayarlar › Bağlantılar › "Flux Mail bağlantısı"). Adres ve API key tek yerde, İletişim › Bağlantılar'da.
- Flux Mail kurulunca Flux Certificates'ya bağlı hatlar taşınmış bağlantıya kendiliğinden geçer; Flux Mail kapatılırsa eski Flux Certificates sağlayıcısından çalışmaya devam eder.
- Sistem kontrolü: bağlantının kaynağı (Flux Mail / Flux Certificates / ayrı bilgi) ve Flux Mail entegrasyonu gösterilir.

## 1.3.0

- Ayarlar › Sistem kontrolü sekmesi ve ortak `flx_system_checks` kontrolleri.

## 1.2.x

- OpenRouter ile mesaj önerisi ve bağış asistanı; yazıyor göstergesi, hitap ve devir düzeltmeleri.
