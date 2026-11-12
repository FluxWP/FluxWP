# Flux Chat 1.9.2 – Kurulum

Bu eklenti, WordPress panelinin içinde çalışan bir WhatsApp ekranıdır. Bağlı hattaki tüm sohbetleri canlı gösterir: gelen mesajlar, sizin gönderdikleriniz, telefondan yazılanlar ve sertifika mesajları. Buradan yanıt verebilir, yeni sohbet başlatabilir ve hazır cevap gönderebilirsiniz. Mobilde de çalışır.

## Gereksinimler
- WordPress 6.4 veya üstü, PHP 7.4 veya üstü.
- Evolution API v2 ve bu API'ye bağlı bir WhatsApp instance'ı (Flux Certificates'da kullanılan hat da olabilir).
- Flux Certificates zorunlu değil. Kuruluysa (1.2.4 ile test edildi) oradaki Evolution ayarı tekrar girilmeden kullanılır, kişi adları ve sertifikalar da sohbet ekranında görünür, sohbetten sertifika oluşturulup gönderilebilir.
- Flux Donate zorunlu değil. Kuruluysa (1.1.1 ile test edildi) sohbetten bağış kaydı açılır ve gelen dekont kayıtla eşleştirilir.

## Önceki sürümden güncelleme
Eklentiyi silmeden yeni zip'i yükleyin (**Eklenti Yükle › Mevcut olanı yüklenenle değiştir**). Veriler korunur, yeni tablo (dekont eşleşmeleri) kendiliğinden eklenir. Canlı güncelleme aralığı 3 sn ise 10 sn'ye çekilir (Hostinger hız sınırı yüzünden). 1.0'da gizli numaralı (@lid) kişilerin sohbetine yanlış numara yazılıyordu; güncellemede düzeltilir. 1.5.0 güncellemesinde asistan kapsamı "temsilciye atanmamış" ise bir kez "tüm sohbetler" yapılır; istenirse Ayarlar › Yapay zekâ'dan geri alınır.

## 1. Kurulum
1. **Eklentiler › Yeni Ekle › Eklenti Yükle** sayfasından `flx-chat.zip` dosyasını yükleyin ve etkinleştirin.
2. Sol menüde **Çağrı Merkezi** öğesi görünür. Okunmamış mesaj sayısı bu öğenin yanındaki rozette gösterilir.

## 2. Hattı bağlama

**Flux Mail varsa (önerilen):** WhatsApp bağlantıları İletişim › Bağlantılar'da tutulur. Burada "Bağlantı bilgisi" olarak *Flux Mail bağlantısı: …* seçin; adres ve API key orada güncellenir. Flux Mail kurulunca Flux Certificates'ya bağlı hatlar taşınan bağlantıya kendiliğinden geçer. Flux Mail kapatılırsa hat eski Flux Certificates sağlayıcısından çalışmaya devam eder.

**Çağrı Merkezi › Ayarlar › Bağlantılar**
1. Flux Certificates kuruluysa, oradaki ilk Evolution sağlayıcısı otomatik olarak eklenir. Kaynak olarak *Flux Certificates sağlayıcısını kullan: …* seçili gelir.
2. Flux Certificates yoksa kaynak olarak **Ayrı Evolution API bilgisi gir** seçin. Ardından API adresini (ör. `https://evo.alanadi.com`), API key'i ve instance adını girip **Kaydet**'e basın.
3. Sırasıyla şu butonlara basın:
   - **Bağlantıyı test et**: "Bağlı ✓" görünmeli.
   - **Webhook'u kur**: Bu adım zorunludur. Gelen mesajlar ve okundu bilgisi bu adres üzerinden gelir. Webhook adresi kart üzerinde de yazar; Evolution paneline elle de girilebilir. Gerekli event'ler: `MESSAGES_UPSERT`, `MESSAGES_UPDATE`, `MESSAGES_DELETE`, `SEND_MESSAGE`, `CONNECTION_UPDATE`, `PRESENCE_UPDATE`, `CONTACTS_UPSERT`, `CONTACTS_UPDATE`.
   - **QR ile bağla**: Yalnız hat kopmuşsa kullanılır. Telefonda WhatsApp › Bağlı cihazlar › Cihaz bağla adımlarıyla QR'ı okutun.
   - **Son sohbetleri içe aktar**: İsteğe bağlı. Son 30 sohbetin mesajlarını Evolution'dan çeker.

### Eski mesajlar (geçmiş)
Bir sohbet açılınca eski mesajlar Evolution'dan sayfa sayfa çekilir; yukarı kaydırdıkça daha eskileri gelir. Bunun için Evolution'un mesajları kendi veritabanında saklaması gerekir:
- Evolution sunucusunun ortam değişkenlerinde `DATABASE_SAVE_DATA_NEW_MESSAGE=true` ve `DATABASE_SAVE_DATA_HISTORIC=true` olmalı.
- Hat bağlanmadan önceki konuşmalar için instance ayarında **Sync full history** açık olmalı ve hat QR ile yeniden bağlanmalı. WhatsApp geçmişi telefondan yalnız bağlanma anında gönderir.
- **Bağlantıyı test et** sonucunda "Evolution'da kayıtlı mesaj: N" yazar. Burada 0 veya uyarı görünüyorsa Evolution mesaj saklamıyordur; sohbetlerin boş gelmesinin sebebi budur.
- WhatsApp'ın yeni gizli numara biçimiyle (`@lid`) gelen mesajlar telefon numaralı sohbetle birleştirilir.
4. Birden fazla hat eklenebilir (ör. her şube için ayrı bir hat). Her hattın ayrı bir webhook adresi ve gizli anahtarı vardır.

> Güvenlik: Webhook adresindeki `t=` anahtarı gizlidir; başkası bu adresle sahte mesaj gönderemez. Adres sızarsa karttaki **Gizli anahtarı yenile** seçeneğini işaretleyip kaydedin, sonra **Webhook'u kur**'a tekrar basın. API key panelde gösterilmez ve loglara yazılmaz.

## 3. Kullanıcılar ve yetkiler
- **Yöneticiler** her şeyi görür ve ayarları değiştirebilir.
- **Çağrı Merkezi Temsilcisi** rolü (`flxc_agent`) yalnızca sohbet ekranını görür. Personel için **Kullanıcılar › Yeni Ekle** sayfasından bu rolü seçin. WooCommerce bu rolü panelden atmaz.
- **Ayarlar › Genel › Temsilciler yalnız kendine atanan ve atanmamış sohbetleri görsün** seçeneği açılırsa, temsilci başkasına atanmış sohbetleri görmez.
- Bir sohbete ilk yanıtı veren temsilci, o sohbete otomatik olarak atanır. Atama, kişi bilgisi panelinden değiştirilebilir.

## 4. Sohbet ekranı
- **Sohbet listesi**: Tümü, Okunmamış, Bana atanan, Atanmamış ve Kapalı filtreleri, etiket filtresi ve arama vardır. Uzun basınca veya sağ tıklayınca sabitle, okunmadı işaretle, kapat ve etiket seçenekleri çıkar.
- **Mesajlar**: Tek, çift ve mavi tik gösterilir. Fotoğraf, video, ses, belge, konum ve kişi kartı desteklenir. Yanıtla (alıntı), emoji tepkisi, kopyala, silinen ve düzenlenen mesaj işaretleri vardır.
- **Gönderme**: Metin, emoji, fotoğraf/video ve belge gönderilebilir (sürükle-bırak ve yapıştır desteklenir). Mikrofon butonuyla sesli mesaj kaydedilir.
- **Hazır cevaplar**: Mesaj kutusuna `/` yazınca liste açılır. Ok tuşları ve Enter ile seçilir. Değişkenler: `{first_name}`, `{name}`, `{phone}`, `{agent}`, `{brand}`, `{site}`, `{date}`. Yönetimi sohbet listesinin üstündeki ⋮ menüsünde **Hazır cevaplar** altındadır.
- **Dahili not**: Mesaj kutusundaki + menüsünde **Dahili not** seçeneğiyle yazılır. Yalnız ekipte görünür, müşteriye gitmez.
- **Yeni sohbet**: Sol üstteki "Yeni sohbet" butonuna basıp numarayı yazın. Numaranın WhatsApp'ta olup olmadığı anında kontrol edilir.
- **Kişi bilgisi**: Sohbet başlığına tıklayınca açılır. Ad, etiketler, atama ve notlar burada düzenlenir. Flux Certificates kuruluysa kişinin sertifikaları da listelenir. Mesaja ekle düğmesinde iki seçenek vardır: **Sertifika mesajını ekle** (Flux Certificates'daki WhatsApp şablonu, kutuya taslak olarak gelir) ve **Yalnız linki ekle** (`Ad · Sertifika adı` ve altında link). Kayıtlar panelindeki sertifika menüsünde de aynı iki seçenek vardır.
- **Gizli numara (@lid)**: WhatsApp bazı kişileri telefon numarası yerine gizli kimlikle iletir. Bu sohbetlerde numara yerine "Gizli numara" yazar ve cevap bu kimliğe gönderilir. Kişinin gerçek numarası webhook'ta gelirse (Evolution sürümüne göre) sohbete yazılır ve cevap o numaraya gider. Gelmezse cevap gizli kimliğe gönderilir; Evolution API 2.3'ten eskiyse bu başarısız olur ve mesajın altında sürümle birlikte "Evolution API sunucusunu 2.3 veya üstüne güncelleyin" yazar.
- **Evolution sürümünü görmek**: Ayarlar › Bağlantılar › "Bağlantıyı test et" sonucunda "Evolution API sürümü" satırı çıkar (ya da tarayıcıda Evolution API adresinizi açın; dönen JSON'da `"version"` yazar). 2.3'ten eskiyse sistem kontrolü de uyarır. Güncellemek Evolution'ı kurduğunuz sunucuda yapılır (WordPress güncellemesi değil): Docker kullanıyorsanız imajı son sürüme çekip (`docker compose pull` ve `docker compose up -d`) yeniden başlatın; hazır panel (EasyPanel, Coolify vb.) kullanıyorsanız oradan imaj sürümünü güncelleyin. Instance ve oturum korunur.
- **Kaynak etiketi**: Telefondan yazılan mesajlarda "Telefondan", sertifika sisteminin gönderdiklerinde "Sertifika", kayıt ekranlarından gidenlerde "Kayıt" etiketi görünür.
- **Hazır cevap baloncukları**: Mesaj kutusunun üstünde yatay sırada durur; tıklayınca metin değişkenleri doldurulmuş olarak mesaj kutusuna gelir, temsilci kontrol edip gönderir. ⋮ menüsünden gizlenebilir, Ayarlar › Kayıtlar'dan tamamen kapatılabilir.
- **Link önizlemesi**: Linkli mesajın balonu, WhatsApp'taki gibi önizleme kartı genişliğindedir. Mesaj kutusuna link yazınca kısa bir süre sonra WhatsApp'taki gibi kart (görsel, başlık, açıklama, alan adı) çıkar. ✕ ile kaldırılırsa mesaj önizlemesiz gider. Gönderilen, gelen ve eski mesajlardaki linkler de kart olarak görünür. Önizleme bilgisi sunucuda önbelleğe alınır (görselli sayfa 1 hafta, görselsiz 30 dk); sitenin kendi adresleri ve dış siteler desteklenir, yerel ağ adreslerine istek atılmaz.
- **Tepkiler**: Mesaj menüsünde 👍 ❤️ 😂 😮 😢 🤲; **+** ile istediğiniz emoji seçilir. Emoji seçicide "son kullanılanlar" sekmesi vardır.
- **Bildirim**: Yeni mesajda ses çalar ve tarayıcı bildirimi gösterilir (izin bir kez istenir).
- **Görünüm**: Koyu mod cihaz ayarını izler; ⋮ menüsündeki Tema seçeneğiyle elle de değiştirilebilir.
- **Dışa aktarma**: Sohbet başlığındaki ⋮ › **Sohbeti dışa aktar** tek sohbeti indirir. Yöneticiler sohbet listesinin üstündeki ⋮ › **Sohbetleri dışa aktar** ile toplu indirir: son 7 / 30 / 90 gün ya da tümü; tüm sohbetler, asistanın yazdıkları ya da temsilcinin yazdıkları (en yeni 1000 sohbet, gruplar hariç). Biçim .md (yapay zekâya vermek için okunur metin) ya da JSON. Her mesaj saat ve yazanla iner (Kişi, Asistan, Temsilci (ad), Otomatik yanıt, Telefondan, Sistem, Dahili not); silinen/düzenlenen işaretleri ve dekont kontrol sonucu da eklenir. Telefon ve e-postalar varsayılan olarak maskelenir (90532•••4567), istenirse kapatılır; dahili notlar isteğe bağlı.
- **Sohbet geçmişini temizle** (yalnız yönetici): Sohbet başlığındaki ⋮ › **Sohbet geçmişini temizle**. Uyarı gösterilir, onay için **TEMİZLE** yazmak gerekir. Bu sohbetin bizdeki tüm mesajları ve dosyaları kalıcı silinir; sohbet, kişi adı, etiketler ve notlar kalır, asistan bu sohbette sıfırdan başlar. "Bağış kayıtlarına eklenmiş dekontları sakla" işaretliyse (varsayılan) bağışa bağlı dekontlar silinmez. Kişinin telefonundaki ve bağlı WhatsApp telefonundaki mesajlar silinmez. Evolution API'de sohbet geçmişini silen bir uç yok (yalnız "herkesten sil" var, o da mesajı kişinin telefonundan da siler); bu yüzden Evolution sunucusundaki kopya yerinde kalır ama temizlenen ana kadarki mesajlar bir daha içe aktarılmaz. Evolution'daki kopyayı da silmek isterseniz Evolution sunucusunda (Manager ya da veritabanı) yapılır.

### Mobil
Telefonda `alanadi.com/wp-admin/admin.php?page=flx-chat` adresini açın. Tarayıcı menüsünden **Ana ekrana ekle** seçeneğiyle uygulama gibi açılan bir kısayol oluşturabilirsiniz. Geri tuşu sohbetten listeye döner. Telefonda mesaj kutusu üstte tam satırdır; emoji, ekle, yapay zekâ ve mikrofon altındadır, gönder düğmesi sağ alttadır (1.11.2).

## 5. Bağış ve sertifika kayıtları
Flux Donate ve/veya Flux Certificates kuruluysa çağrı merkezi ekranından ayrılmadan kayıt açılır.

- **Bağış kaydı** (sohbet başlığındaki 🎁 düğmesi): Bağış türü, seçenek, adet (toplam otomatik hesaplanır), ödeme durumu (*Ödeme bekleniyor* / *Ödendi*), ödeme yolu, bağışçı bilgileri ve gerekirse kimin adına yapıldığı girilir. Kayıt Flux Donate'a `callcenter` kaynağıyla düşer. "Kişiye WhatsApp'tan bilgi gönder" açıksa hazır metin (havale bilgisi dahil) düzenlenip gönderilir. Ödendi seçilirse aynı anda sertifika da oluşturulabilir. Başlıktaki rozet bekleyen bağış sayısını gösterir.
- **Sertifika** (🛡 düğmesi): Şablon, etkinlik, ad soyad ve adet seçilir. Sertifika oluşturulur; gönderilmeden önce sertifika sayfası arka planda bir kez açılıp PDF/görsel/önizleme dosyaları hazırlanır, böylece WhatsApp'ta görselli kart çıkar. Mesaj metni Flux Certificates'daki WhatsApp şablonundan gelir.
- **Dekont eşleştirme**: Gelen fotoğraf veya belge dekont gibi görünüyorsa (ya da kişinin bekleyen bağışı varsa) altında "Dekont olarak eşleştir" çıkar; mesaj menüsünde de vardır. Açılan pencerede kişinin bekleyen bağışları, tüm bağışları ve sertifikaları listelenir, başka kayıtta da aranabilir. Eşleştirilen dekont kayda eklenir (Flux Donate'taki bağışın notlarına/geçmişine dekont linki yazılır), istenirse bağış "Ödendi" yapılır ve kişiye onay mesajı gider. Kayıt yoksa dekonttan yeni bağış kaydı açılabilir.
- **Dekont ön kontrolü (yapay zekâ)**: Asistan açık bir sohbete fotoğraf ya da PDF gelince görsel okuyabilen model dekontu okur: tutar, alıcı, IBAN (maskeliyse görünen son haneler), tarih, gönderen. Bunları sohbetin bağış kaydıyla karşılaştırır ve kişiye sonucu yazar: tutar uyumluysa "Ekibimiz son kontrolü yapıp onaylayacak", tutar ya da alıcı tutmuyorsa kontrol etmesini ister, dekont değilse dekontu göndermesini ister. Dekont bağış kaydına eklenir ama **ödendi işaretlenmez**; son onay her zaman ekiptedir. Uyumlu ya da eşleşecek bağışı olmayan dekontta sohbet ekibe devredilir; tutmuyorsa kişi düzeltebilsin diye asistan açık kalır. Sonuç mesajın altında "AI · …" etiketi ve sohbette sistem notu olarak görünür. Ayarlar › Yapay zekâ'da aç/kapat ve **Dekont okuma modeli** (boşsa asistan modeli; görsel okuyabilen bir model seçin, ör. `google/gemini-2.5-flash`). Model görsel okuyamıyorsa sistem kontrolü uyarır.
- **Silinen mesajlar**: Kişi bir mesajı "herkesten sil" ile silerse bizde silinmez; mesaj "Kişi bu mesajı sildi" etiketiyle ve kesik çerçeveyle görünmeye devam eder (telefondan silinen kendi mesajlarımız "Telefondan silindi"). Asistan ve AI önerisi silinen mesajları okumaz; sohbet listesinde "Bu mesaj silindi" yazar.
- **Kayıtlar paneli** (sohbet listesinin üstündeki 📋 düğmesi): Bağış ve sertifika kayıtlarında ad, telefon, sertifika no ile arama; durum filtresi; kayıttan sohbete geçiş, sertifika linkini mesaja ekleme, ödendi işaretleme.
- **Ayarlar › Kayıtlar**: Temsilcilerin bağış ve sertifika oluşturma yetkisi, hazır cevap baloncukları, bağış mesaj metinleri (boşsa varsayılan) ve banka bilgisi (boşsa Flux Donate'taki banka hesapları). Değişkenler: `{first_name}`, `{name}`, `{donation}`, `{amount}`, `{quantity}`, `{bank}`, `{certificate_link}`, `{agent}`, `{brand}`, `{date}`.

## 6. Otomatik yanıtlar
**Ayarlar › Otomatik yanıtlar**
- **Karşılama mesajı**: Kişinin ilk mesajına bir kez gönderilir.
- **Mesai dışı mesajı**: Seçtiğiniz gün ve saatlerin dışında gönderilir. Aynı kişiye varsayılan olarak en fazla 6 saatte bir gider (ayarlanabilir).
- Varsayılan olarak ikisi de kapalıdır.

## 7. Etiketler ve görünüm
- **Ayarlar › Etiketler**: Etiket adı ve rengi düzenlenir.
- **Ayarlar › Genel**:
  - panel başlığı (`{brand}` değişkeni de bunu kullanır),
  - vurgu rengi,
  - temsilci imzası (mesajın başına *Ad:* eklenir),
  - okundu bilgisi gönderme,
  - grup sohbetlerini gösterme,
  - yenileme aralığı.

Başka bir sitede marka adı ve renk ayarlardan değiştirilir; kodda kuruma özel bir şey yoktur.

## 8. Hosting notu (Hostinger)
Canlı güncelleme uzun süre açık kalan bağlantı kullanmaz. Açık sekme varsayılan olarak 10 saniyede bir hafif bir istek gönderir; asistan cevap yazarken ve yanıt değişirken 3 saniyeye iner, değişiklik yoksa 15 saniyeye kadar seyrekleşir. Aynı anda tek istek uçuşta olur, 20 sn'de yanıt gelmezse bırakılır. Sekme arka plandayken dakikada bir bakılır (bildirim için), sekmeye dönülünce hemen eşitlenir. Hata veya HTTP 429 olursa aralık katlanarak 60 saniyeye kadar uzar. Hostinger hız sınırına takılırsanız **Ayarlar › Genel › Canlı güncelleme sıklığı (sn)** değerini 15–20 yapın ve aynı kişide çok sayıda açık sekme bırakmayın.

LiteSpeed Cache kullanıyorsanız `/wp-json/flx-chat/` yolu önbelleğe alınmamalıdır. REST istekleri oturum açmış kullanıcıya ait olduğu için varsayılan ayarda zaten önbelleğe alınmaz.

## 9. Medya ve gizlilik
- Gelen ve giden medya dosyaları `wp-content/uploads/flx-chat/` klasöründe tutulur. Bu klasöre dışarıdan doğrudan erişim kapalıdır (.htaccess); dosyalar yalnız oturum açmış yetkili kullanıcıya sunulur. Nginx kullanıyorsanız bu klasöre `deny all` kuralı ekleyin.
- Bir mesajın medyası henüz indirilmemişse, ilk açılışta Evolution'dan çekilir.
- Eklenti silindiğinde veriler kalır. Silinince verilerin de kaldırılmasını istiyorsanız **Ayarlar › Genel**'deki "Eklenti silinirken tüm sohbetleri, mesajları ve medya dosyalarını da sil" seçeneğini açın.

## 10. Yapay zekâ (Flux AI üzerinden)
**Ayarlar › Yapay zekâ ve asistan**

**1.30.0'dan itibaren Flux AI zorunlu.** Çağrı Merkezi'nin kendi OpenRouter/Anthropic bağlantısı kaldırıldı; öneri, asistan, dekont okuma, sesli mesaj, özet, test sohbeti ve benchmark'ın tüm istekleri Flux AI & MCP eklentisinden geçer. Flux AI kurulu değilse bu özellikler kapalıdır (panelde uyarı çıkar), mesajlaşma ve diğer her şey çalışır.

1. Flux AI'yi kurun (Güncellemeler). Anahtarlar, Anthropic workspace ID, Claude prompt önbelleği, harcama sınırı (gün/hafta/ay) ve kullanım kayıtları **Flux AI & MCP › Ayarlar**'dadır (raporda kaynak "flx-chat").
   **Aktarım:** Çağrı Merkezi'nde eskiden girilmiş anahtarlar, workspace ID ve öneri modeli, Flux AI etkinken yönetici panele ilk girdiğinde Flux AI'ye kendiliğinden aktarılır (Flux AI'de o alan boşsa) ve Çağrı Merkezi'ndeki kopyası silinir. Flux AI'de aynı sağlayıcı için farklı bir anahtar zaten varsa Flux AI'deki kullanılır, panelde uyarı çıkar.
2. Model seçimleri burada kalır: **Model (öneriler için)**, **Asistan modeli**, **Dekont okuma modeli**, **Sesli mesaj modeli**. Seçici Flux AI'nin model kataloğunu gösterir: arama, **Tümü / Ücretsiz / aile** sekmeleri, fiyat kutuları, her satırda "$giriş / $çıkış" (1 milyon token başına USD). Alan boşsa Flux AI'de bu eklentiye seçilen model (ya da varsayılan) kullanılır. Asistan için araç (tools) destekli model gerekir. Önerilen başlangıç: `google/gemini-2.5-flash` ya da Claude Haiku / Sonnet.
   Test sohbeti ve benchmark kendi önbellek seçimini yapabilir; boşsa Flux AI'deki ayar kullanılır.
3. **Bağlantıyı test et** ile cevap geldiğini görün. Bu ayki istek/token/maliyet aynı kartta yazar.
4. **Kurum bilgisi ve üslup** alanına kurumu, dikim yerini/zamanını, sık sorulan bilgileri ve konuşma tonunu yazın. Bağış türleri ve fiyatlar (Flux Donate), banka bilgisi ve hazır cevaplar ayrıca otomatik eklenir.

### Cevap önerisi
- Son mesaj kişiden geldiyse mesaj kutusunun üstünde **Önerilen cevap** kartı çıkar (ayardan kapatılabilir; o zaman mesaj kutusundaki ✨ düğmesiyle istenir).
- **Gönder**: öneri olduğu gibi gider. **Düzenle**: kutuya gelir, değiştirip gönderirsiniz. **Yenile**: başka öneri. Yanındaki kutuya "daha kısa", "fiyatı da söyle" gibi istek yazıp Enter'a basınca ona göre yeniden yazar.
- Öneri hiçbir zaman kendiliğinden gönderilmez. Aynı son mesaj için hazırlanan öneri 6 saat saklanır; sohbeti tekrar açınca yeniden ücretlendirilmez.

### Bağış asistanı (otomatik cevap)
Ayarlar › Yapay zekâ'daki **Bağış asistanını aç** işaretiyle tümüyle açılıp kapanır. Açılınca gelen mesajlara kendisi cevap verir:
1. Karşılar ve nasıl hitap edilmesini istediğini sorar. Kişi ne derse (ör. "Ayşe Hanım", "patron") öyle hitap eder; WhatsApp profil adını ad saymaz. Vermek istemezse "Tabii, nasıl isterseniz" der ve devam eder. Kayıt ve sertifika için ad soyadı ayrıca ister; hitap kayda yazılmaz.
2. Bağış seçeneklerini ve fiyatları hazır bir WhatsApp mesajı olarak gönderir (ör. *1 fidan – 150 ₺, 3 fidan – 450 ₺, 10 fidan – 1.500 ₺*). Fiyatlar Flux Donate'tan gelir; listedeki adetler ayardan değişir. Yeni bir bağış türü Flux Donate'ta açılınca asistan onu da sunar.
3. İsteğe bağlı e-posta ister; birden fazla adette "her biri başka biri adına mı" diye sorar, o kişilerin adını, isterse telefon ve e-postasını alır.
4. Her değişiklikten sonra özet gösterir: "Bu şekilde uygunsa bağış işlemini başlatabiliriz."
5. Onaydan sonra Flux Donate'ta **ödeme bekleniyor** durumunda bağış kaydı açar (kaynak: çağrı merkezi, not: WhatsApp asistanı). Ardından ayrı mesajlar gönderir: kartla ödeme yakında + bağış sayfası linki (seçilen tür ve adetle), **alıcı adı**, **IBAN** (boşluksuz), **açıklama**, son teşekkür mesajı. Her biri ayrı mesaj olduğu için kişi uzun basıp kolayca kopyalar.
6. Dekont gelirse yukarıdaki dekont ön kontrolünü yapar (açıksa).
7. Bağış dışı konu, şikâyet, ısrarlı kaba mesaj, "yetkiliyle görüşmek istiyorum" gibi durumlarda sohbeti ekibe devreder: kişiye ayardaki devir mesajı gider, sohbete sistem satırı düşer ve okunmamış olur. Listede olmayan bir bağış türü (kurban, zekât vb.) sorulursa hemen devretmez: "Şu an farklı bağış türlerinde yardımcı olamıyoruz, isterseniz fidan bağışı konusunda yardımcı oluruz" gibi kısa bir cevapla listedeki türleri önerir; kişi ısrar ederse devreder.
8. **Ekibe WhatsApp bildirimi**: Ayarlar › Yapay zekâ › **Devirde bildirim gidecek numaralar** alanına numara yazılırsa, asistan her devirde bu numaralara aynı hattan WhatsApp mesajı gönderir: kişinin adı (biliniyorsa), telefonu, devir nedeni, son mesajları ve sohbetin panel linki. Gönderildiği ya da gönderilemediği sohbete sistem satırı olarak düşer. Aynı sohbet için 5 dakikada en fazla bir bildirim gider. Bildirimler panelde ayrı sohbet açmaz; bu numaralarla asistan konuşmaz.

Fiyatlar, IBAN ve linkler sunucuda üretilir; model bunları kendisi yazmaz, böylece yanlış fiyat ya da IBAN uydurması önlenir.

**IBAN isteği (1.25.0):** Kişi "İban", "hesap numaranız", "havale bilgisi" gibi bir şey yazınca alıcı adı ve IBAN (4'lü gruplu) her zaman ayrı mesajlarla gider. Model "bakıyorum" deyip araç çağırmadan biterse o cevap gitmez, model bir kez daha denenir.

**Sesli mesaj (1.26.0):** Kişi sesli mesaj gönderince asistan sesi metne çevirip cevap verir; metin panelde sistem satırı olarak da görünür. Varsayılan en fazla 60 sn dinlenir, daha uzun seste asistan "ses çok uzun olduğu için dinleyemedik" der. Çeviri ses okuyan bir OpenRouter modeliyle yapılır (varsayılan Gemini Flash Lite, dakikası çok ucuz); Claude ses okumadığı için OpenRouter anahtarı gerekir (Flux AI kuruluysa oradaki anahtar). Ayar: Ayarlar › Yapay zekâ › **Sesli mesaj** (aç/kapa, en uzun süre, model).

**Ayrı sertifika adları (1.27.0):** Birden çok fidan bağışında asistanın onay özeti her fidanı ayrı satırda, sertifikanın kimin adına çıkacağıyla gösterir; ad verilmeyen fidanlar bağışçı adına yazılır. Havale bilgisindeki IBAN eklentide yazılı değildir, WooCommerce › Ayarlar › Ödemeler › Doğrudan banka havalesi'nden gelir; yanlış IBAN görürseniz orayı düzeltin. Personel farklı kişiler adına ayrı sertifika isterse de tek kayıt ve tek ödeme açılır (1.27.1); personel az önce açtığı bağışı düzeltirse eski ödenmemiş kayıt iptal edilir.

**Yakınlar hafızada (1.28.0):** Kişi hafızası açıksa (varsayılan açık), bağışta adına sertifika verilen kişi yakınlığı ve numarasıyla kişinin hafızasına yazılır (kişi kartında Hafıza bölümünde görünür). "Annem adına bir tane daha" denince asistan adı ve numarayı hazır getirir, yine onay ister.

**Sertifika personele de (1.29.0):** Personelin referansı olduğu bağış ödenince sertifikalar personelin WhatsApp'ına da gider (Flux Roles profilindeki numara). Personel isterse asistan sertifikayı ona ve/veya bağışçıya onay alarak yeniden gönderir, bağış başına en fazla 3 kez. Ayarlar › Personel bölümünden açılıp kapanır, mesaj ve sınır değiştirilebilir.

**Görseller (1.24.0):** Flux AI 0.2.0 ya da sonrası kuruluysa asistan gerektiğinde (personel raporu, karşılaştırma, süreç) bir SVG görsel çizer; WhatsApp'a PNG resim olarak metinden sonra gider, metin her zaman ayrıca gider. Sunucuda bir çevirici gerekir: Flux AI & MCP › Ayarlar › Görseller'e Gotenberg adresi (`docker run -p 3000:3000 gotenberg/gotenberg:8`, Evolution'ın Docker sunucusu kullanılabilir) ya da sunucuda Imagick (SVG desteğiyle). Hostinger paylaşımlı sunucuda tarayıcı yoktur. Çevirici yoksa asistan görsel çizmez. Ayar: Ayarlar › Yapay zekâ › **Görseller**.

**Başkası adına bağış ve kişi kartı (1.23.0):** Kişi "1 fidan Ömer beye" yazıp WhatsApp kişi kartı gönderirse asistan karttaki adı ve numarayı alır, numarayı tekrar sormaz; özette "Adına: Ömer Ali Bellikli (+90 …)" görünür. Ödeme onaylanıp sertifika oluşunca sertifika bu numaraya da WhatsApp'tan gider (önce kısa bir tanıtım mesajı, sonra Flux Certificates'daki sertifika mesajı). Her sertifika ve numara için bir kez gider, bağışçının kendi numarasına ve kara listedekine gitmez, sipariş notuna yazılır. Ayar: Ayarlar › Yapay zekâ › **Başkası adına bağış** (aç/kapa, mesaj metni; değişkenler `{ad}`, `{bagisci}`, `{bagis}`, `{brand}`). Flux Certificates ve WooCommerce gerekir.

**Linkler modele gitmez (1.22.0):** araç sonuçlarındaki adresler modele `[[link1]]` gibi işaretle gider; model linki paylaşmak isterse işareti yazar, sistem gönderirken gerçek adrese çevirir. Modelin araçtan gelmeyen bir adres yazması mümkün değildir (silinir); geçmişteki adresler de modele "(link)" olarak gider. Kişi link istemediğini söylerse asistan işareti de yazmaz.

**Ne zaman cevap verir**: Ayardaki kapsama göre (önerilen ve 1.5.0'dan beri varsayılan: tüm sohbetler, eski sohbetler dahil; ya da yalnız temsilciye atanmamış; ya da yalnız mesai dışı). **Test modu** açıksa yalnız "Test numaraları" alanındaki numaralara cevap verir. Cevaptan önce kişinin ve bizim son 24 saatteki (ayarlanabilir) tüm mesajlarını okur. Kişi "temsilci", "yetkiliyle görüşmek istiyorum", "yapay zekayı kapat" gibi bir ifade yazarsa asistan modeli beklemeden "Sizi ekibimizden bir arkadaşımıza aktarıyorum, en kısa sürede buradan dönüş yapacak 🌱" mesajını gönderir ve o sohbette kapanır (ifadeler ve mesaj ayardan değişir). Asistan cevap hazırlarken panelde sohbet satırında ve başlıkta "Asistan yazıyor…" görünür. Bir temsilci sohbete panelden veya telefondan yazınca asistan o sohbette ayardaki süre kadar (varsayılan 12 saat) susar. Sohbet başlığındaki **Asistan açık / beklemede / kapalı** düğmesiyle sohbet bazında açılıp kapatılır. Sohbet başına günlük cevap sınırı vardır (varsayılan 40); aşılırsa ekibe devreder. Asistan açıkken karşılama ve mesai dışı otomatik yanıtları o sohbete gönderilmez. Hazır cevap, öneri, kayıt/sertifika ya da Flux Certificates panelinden giden mesaj da "temsilci yazdı" sayılır. Model düşünürken temsilci yazarsa asistanın hazırladığı cevap gönderilmez (sohbete not düşer). Kişi bu sırada yeni mesaj yazarsa asistan iki mesajı birlikte cevaplar.

**Bağış türü yoksa**: Flux Donate etkin değilse ya da açık ve fiyatlı bağış türü yoksa asistan hiç konuşmaz; nedeni Ayarlar'da asistan kartının altında yazar.

**Hosting**: Asistan, Evolution'ın webhook isteğine cevap verildikten sonra aynı PHP sürecinde çalışır (LiteSpeed/PHP-FPM'de `litespeed_finish_request` / `fastcgi_finish_request`); yeni bir istek ya da cron açmaz. Model cevabı genelde 2–8 saniye sürer; kişi bu süre boyunca, cevap gidene kadar "yazıyor…" görür (gösterge 15 sn'de bir tazelenir). Arka arkaya giden mesajlar arasında kısa bir "yazıyor…" olur.

**Ödeme bilgileri**: Alıcı adı ve IBAN boşsa Flux Donate'taki ilk banka hesabı kullanılır. Havale açıklaması şablonu varsayılan `{type} {name}` (ör. "Fidan bağışı Ahmet Yılmaz"). Bağış sayfası adresi boşsa sitedeki `/bagis/` sayfası kullanılır.

### AI asistan sayfası
Çağrı Merkezi › **AI asistan** (yalnız yöneticiler):
- **Asistanın çalışma özeti** (Sohbetler sekmesinin üstü): seçilen aralıkta (Bugün, 7 gün, 30 gün, Bu ay, Tümü) harcama (USD), cevaplanan soru, cevap verilen kişi, bağışa yönlendirilen kişi (fiyat listesi gönderilen), açılan bağış kaydı, onaylanan (ödendi işaretlenen) bağış ve tutarı, okunan dekont (✓ uyumlu, ✕ uyuşmayan), ekibe devir ve token. 30 saniyede bir kendiliğinden yenilenir. **Detay** model başına istek/token/maliyeti ve gün gün tabloyu açar. Yalnız gerçek sohbetler sayılır (test sohbeti ve benchmark hariç). Harcama ve sayaçlar 1.6.3 ile başlar; gönderilen mesaj ve kişi sayıları eski sohbetleri de kapsar.
- **Sohbetler**: asistanın yazdığı tüm sohbetler; durumu (açık / beklemede / kapalı), hitap, seçilen bağış, açılan bağış kaydı, son not. Karta basınca sohbet açılır. Sohbet listesindeki **Asistan** filtresi de aynı sohbetleri gösterir.
- **Talimat**: asistanın ana talimatı (system prompt). Düzenleyip yeni sürüm olarak kaydedin; her sürüm saklanır, **Farkı gör** ile yayındakiyle karşılaştırılır, **Yayına al** ile geri dönülür. **Modele giden tam metni gör**, başa eklenen Sistem (araç haritası) ve bilinenlerle birlikte hâlini gösterir. Altta sunucunun gönderdiği hazır mesajlar (fiyat listesi, özet, ödeme mesajları, devir mesajı) görünür. `{brand}` kurum adına dönüşür.
- **Belgeler**: SSS, kampanya, dikim yerleri gibi ek belgeler. "Her cevapta talimata eklenir" ya da "Asistan gerektiğinde okur" seçilir. Talimatta `{{belge:kisa-ad}}` yazılan yere belgenin tamamı konur.
- **AI ile düzenle** (1.10.0): ne değişsin yazın (ör. "Ayşe'nin sohbetine bak, fiyat sorusuna daha kısa cevap versin"). Yapay zekâ sohbeti adla ya da numarayla bulur, **okumak için izin ister** (İzin ver / Bu oturumda hep izin ver / İzin verme), önce son 10, gerekirse 20 mesajı okur, talimatı günceller ve sağdaki **Yapay zekânın testi** panelinde taslağı kendisi dener (kişi gibi mesaj yazar, cevabı görür, gerekirse düzeltir). Bitince taslak satır satır fark olarak onayınıza gelir: **Onayla ve yayına al**, **Taslak sürüm kaydet**, **Test sohbetinde aç**, **Vazgeç**. Oturumlar soldaki listede saklanır: yeniden açıp devam edin, adını değiştirin, arşive alın, silin; Aktif / Arşiv / Tümü filtresi ve arama var. Sayfa her açılışta boş yeni oturumla başlar. Araç desteklemeyen modelde eski tek seferlik düzenleme çalışır.
- **Test sohbeti**: bağışçı gibi yazın, asistan gerçek modelle ve seçilen sürümle cevap verir. WhatsApp'a mesaj gitmez, bağış kaydı açılmaz; sağda asistanın o ana kadar bildikleri ve **Arka plan** (1.10.0): test doğrulama kodu (1.10.1: hiçbir yere gönderilmez, burada görünür; sohbete yazarak doğrulayın), test bağışları (1.10.1: birden fazla; "Ödendi say", "Beklemeye al", "İptal et" yalnız testi değiştirir), hafızası, kurulan ya da onay bekleyen hatırlatıcılar (sıradaki zamanıyla) ve doğrulanan numara/e-postalar görünür. Sağ üstte canlı giriş/çıkış token, maliyet (USD) ve istek sayısı görünür; yanındaki listeden yalnız bu test için başka model seçilebilir (ana ayar değişmez). Her cevabın altında o turun token ve fiyatı yazar (araç çağrıları dahil). Aynı panel ve model seçimi **AI ile düzenle** sekmesinde de var. İki sekmede de "Bu ay toplam (tüm AI)" satırı ay içindeki tüm yapay zekâ kullanımını (öneri, asistan, düzenleyici, test) gösterir.
  - **Dosya ekleme (1.19.0)**: 📎 ile, sohbet kutusuna sürükleyip bırakarak ya da yapıştırarak en çok 5 dosya (fotoğraf, PDF, belge) eklenir, mesajla birlikte gider. Gerçek hattaki gibi işlenir: dekont ön kontrolü açıksa son iki dosya okunur; dekontsa model çağrılmadan ön kontrol cevabı gelir, değilse asistan "[Fotoğraf] (otomatik dekont kontrolü: Dekont değil · …)" notunu görüp cevap verir. Dekont defterine yazılmaz; aynı dekontu bu testte ya da daha önce gerçekte yüklenmişse "daha önce yüklenmiş" uyarısı görünür. Fotoğraflar tarayıcıda 1600 px'e küçültülür.
  - **Araç çağrıları (1.19.0)**: sağda bu testte çağrılan tüm araçlar (adet özeti ve tur numarasıyla). Satıra ya da cevabın altındaki ⚙ etiketine tıklayınca yapay zekânın gönderdiği argümanlar ve sistemin döndürdüğü sonuç açılır. Dekont ön kontrolü de listede.
  - **Önbellek (1.19.0)**: model seçicinin yanında Claude önbelleği seçimi (ayardaki / 5 dk / 1 saat / kapalı). Cevabın altında "⚡ önbellek X okundu, Y yazıldı · tasarruf $…", üstte toplam **Önbellek tasarrufu**.

### Güncel bilgiler tool'dan (1.10.0)
Fiyat, bağış türleri/projeler, havale bilgisi ve saat artık talimata gömülmez. Talimatın en başında kısa bir **Sistem** bölümü hangi bilginin hangi araçta olduğunu söyler; asistan gerektiği an aracı çağırıp güncel veriyi alır:
- `donation_options`: açık bağış türleri (projeler), paketler, fiyatlar, adet kuralları, her seçeneğin bağış linki. Birden fazla tür/proje varsa asistan kişiye hangisine katılmak istediğini sorar. Kaynak: Flux Donate › Bağış türleri (Çağrı Merkezi ayarında seçilenlerle sınırlı).
- `payment_info`: alıcı adı, IBAN, banka, açıklama, ödeme yolları, bağış sayfası. Kaynak (1.10.3): sitenin gösterdiği tek hesap (Flux Donate 2.2.2+ `flxb_bank_account()`): **WooCommerce › Ödemeler › Havale / EFT** (siparişlerde görünen hesap); orası boşsa WooCommerce Banka havalesi (BACS) hesapları, o da yoksa tema banka hesabı. Çağrı Merkezi › Ayarlar › Ödeme › Alıcı adı / IBAN doluysa yalnız asistan için bunları ezer; aynı hesabı kullanmak için boş bırakın.
- `current_time`: tarih, gün, saat, mesai içi mi (Ayarlar › Otomatik cevap mesai saatleri, WordPress saat dilimi).
Başka eklenti araç eklemek için `flxc_bot_info_tools` (tanım) ve `flxc_bot_info_tool` (cevap) filtreleri. Statik metin artık yalnız "Kurum hakkında / talimatlar" alanıdır.

### Bağış iptali (1.11.0)
Kişi ödenmemiş bağışını iptal etmek isterse asistan `cancel_donation` ile önce onay sorusunu gönderir (üstte "neden iptal etmek istediğinizi paylaşmak isterseniz biz buradayız" notu; metin Ayarlar › Yapay zekâ › **Bağış iptali onay sorusu**, değişkenler {bagis}, {tutar}). Onaylanınca WooCommerce siparişi İptal edildi olur, siparişe ve sohbete not düşülür. Ödenmiş bağışlar asistanın dışında kalır (ekibe aktarılır).

### Günlük sınır ve kullanım (1.12.0)
Asistan her sohbette günde en fazla **Sohbet başına günlük cevap sınırı** kadar cevap yazar (varsayılan 40). Sınır dolunca kişiye günde bir kez "Mesajınızı aldık, size en kısa sürede dönüş yapacağız 🌱" gider (Ayarlar › Yapay zekâ'dan değişir) ve sohbet ekibe devredilir. Sohbetin ⋮ menüsü › **Asistan kullanımı**: bugünkü ve toplam istek, giriş/çıkış token, maliyet (USD) ve son 7 gün; **Günlük sınırı sıfırla** ile asistan yeniden cevap verir; yönetici sınırı buradan da değiştirebilir.

### Personel asistanı (1.13.0)
Flux Roles 1.1.0 gerekir. Kullanıcılar › profil › **WhatsApp numarası** alanına numarası yazılan kullanıcı WhatsApp'tan yazınca asistan onu tanır ve yetkisine göre ek araçlar açar (Kullanıcılar › Roller ve erişim'den yönetilir):
- **Başkası adına bağış** (`flxb_create_ref`): "Mehmet Kaya adına 2 fidan, numarası 0555…" → asistan özeti gösterir, personel onaylayınca kayıt açılır (referans = personel), bağışçıya "talebiniz alındı" ve ödeme bilgileri gider.
- **Bağışçılarım** (`flxb_donors_own`): referansı olan bağışlar, durumları ve toplam.
- **Genel rapor** (`flx_reports_view`): ödenen, bekleyen, iptal ve personel bazında özet.
- **Bildirim** (`flxb_ref_notify`): referansı olduğu bağış ödenince ya da iptal olunca personele WhatsApp mesajı.
Gizli numaradan (@lid) yazan personel önce numarasını kodla doğrular. Ayarlar › Yapay zekâ › **Personel (Flux Roles)** bölümünden kapatılabilir; AI asistan › Test sohbeti'nde "Kim gibi yazıyorsunuz?" ile denenir.

### WhatsApp raporları (1.14.0)
Çağrı Merkezi › **Raporlar** (yönetici ya da "raporları yönetir" yetkisi): personele ve idarecilere zamanlanmış bağış raporu. Her raporda kime, içerik (kendi bağışları / genel + personel bazında), dönem, sıklık ve saat seçilir; motivasyon metinlerinden biri rastgele sona eklenir. Alıcının numarası profildeki WhatsApp numarasıdır. "Bana test gönder" ile denenir. Sunucu cron'u yoksa rapor sitenin ya da sohbet panelinin ilk kullanımında arka planda gider.

### Kişi hafızası (1.7.0)
Asistan kişinin kalıcı bilgilerini hatırlar ve her cevapta okur: nasıl hitap edilmek istediği ("bana artık şöyle deyin"), eşinin/yakınlarının adı, numaralar, tercih ettiği bağış türü, özel günler. Kişi bilgisini değiştirirse güncellenir, "bilgilerimi silin" derse asistan siler. Ödeme ve kart bilgisi yazılmaz.
- **Görmek ve düzenlemek** (yalnız yönetici): sohbet ⋮ › **Kişi hafızası**. Kayıtlar tarih, saat ve kaynağıyla (asistan, özet, ekip) listelenir; düzenle, sil, ekle, **Tümünü sil**, **Şimdi özetle**. Sohbet geçmişini temizlerken "Kişi hafızasını da sil" seçilebilir.
- **Özet**: sohbet Ayarlar › Yapay zekâ › "Sohbet kaç saat sessiz kalınca özetlensin" (varsayılan 24) kadar sessiz kalınca yapay zekâ konuşmayı özetler, önemli bilgileri (alışkanlıklar vb.) tarih ve saatle hafızaya yazar. Kişi başına son 3 özet kalır. İlk açılışta yalnız son 7 günün sohbetleri özetlenir. Özetler asistan modeliyle yapılır, maliyeti çalışma özetine eklenir.
- **Arka plan** (AI asistan › Arka plan): çalışan işçi, cevap yazılan ve özetlenen sohbet, sırada bekleyen, bugünkü özet maliyeti ve son 50 iş (süre, token, maliyet, not). 5 sn'de bir yenilenir; **Şimdi tara** süresi dolan sohbetleri hemen sıraya alır. Aynı anda en fazla 5 iş çalışır (Ayarlar'dan 1-5; benchmark bir iş sayılır). Öncelik yazan kişiye cevaptır: bir sohbete cevap yazılırken özet başlamaz. Özetler site kendine istek atarak, panel açıkken ve saatlik WP-Cron ile ilerler.
- **Hafıza** (AI asistan › Hafıza): hafızası olan kişi, kalıcı bilgi, özet sayısı, toplam token (yaklaşık, 3,5 karakter = 1 token) ve en çok hafızası olan kişiler.

### Kara liste (1.7.0)
Kişi "bana mesaj atmayın", "rahatsız etmeyin" gibi bir şey yazınca asistan kısa bir onay gönderir (Ayarlar › Yapay zekâ › "Kişi mesaj istemeyince gönderilen son mesaj") ve kişiyi kara listeye alır. Kara listedeki kişiye panelden, asistandan ve otomasyonlardan mesaj gitmez, asistan onunla konuşmaz; kişinin yazdıkları yine panele düşer. Sohbet başlığında "Kara listede" yazar.
- Liste: ☰ › **Kara liste** (yalnız yönetici): arama, elle telefon/e-posta ekleme, **Listeden çıkar**. Sohbet ⋮ › Kara listeye al / Kara listeden çıkar. Çıkarınca her şey yeniden çalışır.
- E-posta ve SMS de gitmesin diye diğer FluxWP eklentileri ortak kontrolü kullanır: `flx_contact_blocked( $phone, $email, $channel )` ya da `apply_filters( 'flx_contact_blocked', false, $phone, $email, $channel )`; değişince `flx_contact_block_changed( $phone, $email, $blocked )`.

### Kendi kayıtları ve sitedeki içerikler (1.7.0)
- Kişi "bağışlarım", "sertifikam" diye sorarsa asistan kayıtlarını söyler. Kayıtlar yalnız yazan WhatsApp numarasına bağlı bağışçı ve sertifika kişisinden kodla bulunur; model numara ya da e-posta veremez, başkasının kaydı dönmez, gizli numarada kayıt gösterilmez.
- Kişi bir proje, etkinlik, haber ya da gönderi sorarsa asistan sitede arar. Ayarlar › Yapay zekâ › Sitedeki içerikler: aç/kapat, aranacak türler (boşsa yazılar ve arşivi olan herkese açık türler, ör. Projeler, Etkinlikler) ve hiç paylaşılmayacak kategori/etiketler. Yalnız yayında ve şifresiz içerik döner; taslak, özel ve şifreli içerik asla. Tek içeriği gizlemek için düzenleme ekranında "Asistan bu içeriği kişilerle paylaşmasın".

### Ekip grubu (1.7.0)
Ayarlar › Yapay zekâ › **Ekip grubu**: hattın üye olduğu bir WhatsApp grubu seçin (yeni grup görünmüyorsa "listeyi yenileyin").
- "Kişilerden gelen mesajları ve verilen cevapları bu gruba yaz": her mesaj kişinin adı, numarası ve panel linkiyle "*Müşteri:* …", "*AI Chat:* …", "*Ekip (Ad):* …" biçiminde gruba gider. Kişinin mesajı gruptaki en son mesajsa ve 12 dk içinde gönderildiyse yeni satırlar o mesaja eklenir (düzenlenir); değilse yeni mesaj açılır. Metni kod hazırlar. Mesaj düzenleme Evolution API 2.2+ ister; olmazsa yeni mesaj gider.
- "Devir bildirimini bu gruba gönder": devir bildirimi numaralara tek tek değil gruba gider, "Devirde bildirim gidecek numaralar" mesajda @etiketlenir.

### Cevap gecikirse (1.8.0)
- Bir asistan cevabı en fazla **Bir cevabın en uzun süresi** (varsayılan 50 sn) sürer. Yapay zekâ hata verir, süre dolar, boş döner ya da işlem sunucuda kesilirse kişiye **Asistan cevap veremezse giden mesaj** ("Beklettiğimiz için üzgünüz…") gider ve ekibe haber verilir; asistan kapanmaz, kişi yeniden yazınca yine dener. Kişi hiçbir durumda cevapsız kalmaz.
- 1.9.0: geçici yapay zekâ hatasında (ağ, 429, 5xx, boş cevap) asistan önce bir kez daha dener. Bekleme mesajı kişinin mesajından **Bekleme mesajından önce en az** (varsayılan 30 sn) geçmeden gitmez; anlık hata olsa da o süre beklenir, bu arada cevap giderse bekleme mesajı hiç gitmez.
- İşçiler (1.20.0): aynı anda en fazla 30 iş, varsayılan 20 (15-20 kişiyle aynı anda konuşmak için). Her iş bir PHP işçisi kullanır; Hostinger'da hesabın toplam sınırı (ör. 60) tüm sitelerde ortaktır, yarısını geçmeyin. "Yalnız cevaplara ayrılan işçi" (varsayılan 5) hiç özet/hatırlatıcı almaz. Cevap yazılan ya da sırada bekleyen kişi varken özet başlamaz. Özetler yalnız **Özet saatleri** (varsayılan 03:00–05:00) arasında yapılır; elle "Şimdi özetle" her zaman çalışır.
- Yoğunlukta aynı anda en fazla "Aynı anda en fazla iş" kadar cevap yazılır, diğer kişiler sıraya alınır; sırada 3 dk'dan çok bekleyen kişiye de yedek mesaj gider.

### Ödeme linki (1.8.0)
Asistanın açtığı bağış Flux Donate'ta bir WooCommerce siparişine yansır; kişiye genel bağış sayfası yerine **kendi siparişinin linki** gider:
- Havale/EFT: siparişin "sipariş alındı" sayfası (banka bilgileri ve siparişin açıklama kodu); alıcı adı, IBAN ve açıklama ayrıca kopyalanabilir mesajlarla.
- Kart: doğrudan kart ödeme sayfası (sipariş ödeme sayfası, kart seçili açılır).
- Kişi söylemediyse: kart ya da havaleyi seçebileceği ödeme sayfası, altında havale bilgileri.
- Kişi sonradan "kartla ödeyeyim", "havale yapacağım", "linki tekrar atar mısın" derse asistan yöntemi değiştirip linki yeniden gönderir.
- Linkler siparişin anahtarıyla korunur; bu eklentinin açtığı siparişlerde giriş ya da e-posta doğrulaması istenmez. Kart seçenekleri Flux Donate'taki iyzico ayarından gelir (kart kapalıysa yalnız havale). WooCommerce yoksa eski mesaj (bağış sayfası + havale bilgileri) gider.
- Birden fazla fidanda her fidan ayrı kişi adına olabilir; asistan isimleri ayrı ayrı kaydeder.

### Dekont (1.9.1)
- Kişi dekont (fotoğraf, ekran görüntüsü ya da PDF) gönderince yapay zekâ tutarı, alıcıyı ve IBAN'ı bağışla karşılaştırır; sonuç kişiye söylenir, ekibe haber gider. **Asistan kapanmaz**, kişinin sonraki sorularına cevap verir. Son onay her zaman ekiptedir.
- Asistanı sohbette açınca son 30 dk içinde cevapsız kalan mesaj cevaplanır (1.9.2).
- Ekip ödemeyi onaylayınca asistan bunu görür; kişi sorarsa "bağışınız onaylandı" der, yetkiliye aktarmaz.
- Dekont okuyan model: Ayarlar › Yapay zekâ › Görsel modeli. Seçilen model görsel okuyamıyorsa görsel okuyan ucuz bir modele (google/gemini-2.5-flash-lite) kendiliğinden geçilir. PDF'ler her modelde okunur.
- Dekont olmayan bir fotoğraf gelirse asistan ne olduğuna göre kısa ve esprili bir cevapla konuyu fidana bağlar.
- 1.9.0 ve öncesinde dekont sonrası susturulmuş sohbetlerde asistan, süre dolunca ya da sohbetteki asistan düğmesiyle yeniden açılır.

### Dekont kaydı ve tekrar dekont (1.15.0)
- Gelen her dekont **Dekont defterine** kaydedilir: gönderen + dekont no (karşılaştırmada küçük harf, Türkçe karakterler katlanır, boşluk/tire/nokta atılır) ve dosyanın kendisi (hash). Aynı dekont başka bir sohbetten ya da siteden ikinci kez gelirse kişi baştan uyarılır ("aynı dekont ikinci bir bağış için kullanılamaz"), ekibe haber gider; sohbette "Daha önce yüklenmiş dekont" rozeti çıkar. Aynı kişi aynı bağış için tekrar gönderdiyse yalnız "bu dekontu daha önce almıştık" denir.
- Dekonttaki **alıcı adı ya da görünen IBAN** derneğin değilse (Havale hesabı tek kaynak `flxb_bank_account()`), tutar tutsa bile kişi hemen uyarılır.
- Bilgiler doğru olsa bile kişiye "Dekontunuzu aldık, teşekkür ederiz 🌱 Ödemenizle ilgili size en kısa zamanda dönüş yapacağız" denir; bağış **yetkili onayı bekliyor** olarak kalır, konuşma normal sürer.
- Asistan aracı `receipt_check`: kişi dekont/işlem numarasını yazınca daha önce yüklenip yüklenmediğine bakar.

### Kişinin bağış ve sertifikaları (1.15.0)
Sohbet başlığındaki **Bağışlar ve sertifikalar** düğmesi (sayılı) kişinin bağış ve sertifikalarını listeler; satıra dokununca sertifikayı mesaja ekle, aç, bekleyen bağışı ödendi işaretle. Sertifika oluşturma bağış kaydından yapılır (başlıktaki ayrı "Sertifika oluştur" düğmesi kalktı).

### Kayıt sorgusunda kod doğrulama (1.9.0)
- Numarası görünen kişi kendi bağışlarını sorarsa kayıtlar doğrudan numarasından gelir, ek doğrulama istenmez.
- Numarası WhatsApp gizliliği nedeniyle görünmeyen (@lid) kişiye asistan önce şunu önerir: "Telefon numaranızı paylaşırsanız kısa bir doğrulamayla kayıtlarınızı görebilirsiniz." Kişi numarayı yazınca o numaraya **WhatsApp'tan** 6 haneli kod gider (gitmezse ve Flux Mail'de SMS bağlantısı varsa **SMS**). Kişi kodu sohbete yazınca numara bu kişiye bağlanır, o numaranın bağış ve sertifikaları da gösterilir. Telefon vermek istemezse e-postası sorulur, kod e-postaya gider (Flux Mail şablonu "WhatsApp asistanı: e-posta doğrulama kodu").
- Başka numara ya da e-postayla bağış yapan (numarası görünen) kişi de aynı yolla doğrulayabilir.
- Kod doğrulanınca kayıtlar (bağış, durum, sertifika ve linki) hemen gelir; asistan ekibe aktarmadan cevaplar (1.9.2). Kişi sertifikasını yeniden isterse asistan bu sohbete linkini ya da doğrulanmış/kayıtlı e-postasına gönderir (saatte en fazla 3).
- Numara sitedeki kayıtlarla her yazılışta (+90…, 0…, boşluklu) ve WooCommerce sipariş telefonuyla eşleşir. Bu sohbette asistanın açtığı bağış numara gizli olsa da kişinindir.
- **Başarısız deneme sınırı (1.9.2):** gönderilemeyen, yanlış ya da süresi dolan kodlar sayılır. Sınırda (varsayılan 3) kişiye "Bir sıkıntı oldu, ekibimiz en kısa sürede sizinle iletişime geçecek" gider, ekibe haber verilir; asistan diğer sorulara cevap verir ama 24 saat kayıt bilgisi vermez. Her deneme sohbette sistem notu olarak nedeniyle görünür (ör. "Bu numarada WhatsApp yok", e-postada SMTP).
- Ortak kurallar: kod **5 dk** geçerli (ayar), sohbet başına saatte en fazla 3 kod, 5 yanlış denemede kod geçersiz. Kod kaydedilmez, yalnız özeti tutulur. Kara listedeki numara/e-postaya kod gitmez.
- E-posta doğrulanınca asistan bilgilendirmelerin bu e-postaya gelmesini isteyip istemediğini sorar; cevap kişi hafızasına "e-posta ile bilgilendirme: evet/hayır" olarak yazılır. "Hayır" diyenin e-postası bağış kaydına yazılmaz. Doğrulanan numara/e-posta hafızaya "doğrulanmış telefon/e-posta" olarak yazılır.
- Ayarlar › Yapay zekâ › Kayıt sorgusunda kod doğrulama: aç/kapat, kod süresi, telefona kod (WhatsApp, gitmezse SMS / yalnız WhatsApp / yalnız SMS), WhatsApp/SMS mesaj metni ({kod}, {dk}, {marka}).
- İstatistik: AI asistan › Özet'te gönderilen kod (e-posta/WhatsApp/SMS), doğrulanan (telefon/e-posta) ve kod sorunları (yanlış, süresi doldu, gitmedi).
- **E-posta ve numara teyidi (1.20.0, Ahmet):** kişi e-posta verince asistan adresi aynen yazıp "E-posta adresiniz …, doğru mu?" diye sorar; kişi onaylayınca kaydeder, düzeltirse yenisini sorar. Kod gönderilince kodun gittiği numara/adres de yazılır; yanlışsa kişi hemen düzeltir.

### Hatırlatıcılar (1.8.0)
Kişi "her ay bana fidan dikimini hatırlat", "her ay son projelerinizi gönderin", "her ay benden bir fidan iste" gibi bir şey yazınca asistan hatırlatıcının özetini gösterir, kişi değiştirdikçe düzeltir, onay gelince kaydeder. Kişi konuyu değiştirirse asistan üstelemez.
- Saat söylenmezse ayardaki saat (varsayılan 09:00), "her ay" deyip gün söylemezse ayın 1'i. Sıklık: her gün, her hafta, her ay, her yıl, bir kez.
- Zamanı gelince arka plan işi asistanı bu görevle çalıştırır, asistan mesajı yazıp gönderir (gerekirse sitedeki içerikleri arar). Bağış hatırlatıcısında seçim hazır özetle sorulur, kişi onaylarsa ödeme linki gider.
- Kişi "hatırlatıcıyı durdur" yazarak kapatır. Panelde ☰ › Hatırlatıcılar (tümü) ve sohbet ⋮ › Hatırlatıcılar: durdur, yeniden başlat, sil. Kara listedeki kişiye gönderilmez.
- Zamanlama WP-Cron ile; Hostinger'da sunucu cron'u (5 dk, wp-cron.php) kurulu değilse ilk ziyarette gönderilir. 2 günden eski kaçan hatırlatma gönderilmez.

### Kapanış, tepki ve sormadan hatırlatıcı (1.42.0)
- **Tamamlandı durumu**: vedalaşma bitince asistan sohbeti arka planda "tamamlandı" işaretler. Sonraki teşekkür/veda/dua mesajlarına yeniden veda yazmaz; ilk ikisine emoji tepkisi bırakır (👍 "tamam", 🤲 teşekkür ve dua), sonrasında sessiz kalır ve model çağrılmaz. Kişi soru ya da yeni bir konu yazarsa ya da sohbete asistan dışı bir mesaj giderse (sertifika, bağış onayı, temsilci mesajı) durum kalkar. Ek süre sınırı 12 saat (`flxc_bot_closed_ttl`), kapanış emojisi `flxc_bot_closer_emoji`, izinli emojiler `flxc_bot_react_emojis`.
- **Emoji tepkisi** Ayarlar › AI asistan'dan kapatılabilir; kapalıyken kapanış mesajlarında asistan yalnız sessiz kalır.
- **Hatırlatıcı**: asistan onay ve gün sormadan kurar, günü kişiye söyler; gün yoksa ayın 1'i. Sonradan söylenen gün aynı hatırlatıcıyı günceller.
- **Etkinlik konumu**: site aramasındaki etkinlik sonuçları Flux Certificates etkinlik kaydındaki tarih, saat ve konumu taşır (`flxc_site_item` filtresiyle genişletilebilir).

### Benchmark (AI asistan › Benchmark)
Asistanı hazır senaryolarla toplu sınamak için. Senaryolar test sohbetindeki gibi oynatılır: WhatsApp'a mesaj gitmez, kayıt açılmaz. Model gerçekten çağrılır, ücret seçilen sağlayıcının (OpenRouter ya da Anthropic) hesabından düşer.
- **Senaryolar**: 11 grupta 82 hazır senaryo var: kapanış, tepki ve hatırlatıcı (1.42.0), karşılama, bağış süreci, temsilciye devir, gereksiz devir yapmama, bilgi uydurmama, cevap kalitesi, güncel bilgi ve araçlar (IBAN, saat, projeler, hatırlatıcı, kara liste, hitap kaydı), kayıt/iptal/dekont (bağış iptali, kartla ödeme linki, sertifikayı yeniden gönderme, dekont no ile tekrar kontrolü, personel gibi konuşan müşteri) ve personel asistanı (başkası adına bağış, bağışçılarım, genel rapor; Flux Roles'de personel aracı açık ilk kullanıcı olarak yazar, "Personel yazar" etiketi). Her senaryoda sırayla yazılan mesajlar ve beklentiler bulunur. Her mesajın yazanı müşteri ya da asistan olabilir. Yalnız müşteri mesajı varsa asistan her birine sırayla cevap verir. Asistan mesajı da varsa senaryo **devam senaryosu**dur ("Devam" etiketi): yazılı geçmiş hazır verilir (ör. müşteri, asistan, iki müşteri mesajı) ve yalnız asistanın bundan sonra ne yazdığı ölçülür; son mesaj müşterinin olmalı. Her grupta bir devam senaryosu hazır gelir. Senaryo düzenleyici WhatsApp görünümündedir: **+ Müşteri mesajı** / **+ Asistan mesajı** ile eklenir, **−** ile silinir, soldaki ⋮⋮ tutamağıyla sürükleyip sıralanır, balonun üstündeki "Müşteri ⇄ / Asistan ⇄" ile yazan değiştirilir. Sonraki sürümlerde eklenen hazır senaryolar kayıtlı sete kendiliğinden eklenir (sildiğiniz hazır senaryo geri gelmez). Beklentiler şunlar: ekibe devretmeli mi, bağış kaydı açılmalı mı, fiyat listesi gitmeli mi, beklenen adet, içermesi ve içermemesi gereken ifadeler, **çağrılması / çağrılmaması gereken araçlar** (ör. `payment_info`, `cancel_donation|handoff`; sitede kapalı bir araç beklenmez, "kapalı, bakılmadı" yazar), kim yazıyor (bağışçı / personel) ve değerlendirici modelin kalite ölçütü. Mesajlarda `{tur}`, `{birim}` ve `{brand}` kullanılabilir. **Yeni senaryo**, **Düzenle**, **AI ile senaryo yaz** ve **AI ile düzenle** kullanılabilir. **Hazır senaryoları geri yükle** hazır olanları ilk hâline döndürür, eklediğiniz senaryolar kalır.
- **İşlem sonuçları ve durum paneli (1.18.0)**: "İşlem sonuçları (izole)" grubu (12 senaryo) asistanın gerçekten iş yapıp yapmadığını ölçer: bağış açma, bağışlarını listeleme, sertifika gönderimi, ödenmemiş bağışı onayla iptal (onaysız ve ödenmişte iptal etmemeli), WhatsApp / SMS / e-posta doğrulaması, yanlış kod, personelde başkası adına bağış. Hepsi test ortamındadır: kod, mesaj, kayıt, iptal gerçekte olmaz. Senaryoda **Başlangıç durumu (test)** (ödenmemiş / ödenmiş bağış, doğrulanmış telefon / e-posta, kod kanalı) ve **Sonda durum** (Olmalı / Olmamalı) seçilir; mesajda `{kod}` gönderilen test kodunu yazar. Doğrulama senaryoları için Ayarlar'da doğrulama açık olmalı, kapalıysa "kapalı, bakılmadı" yazar. Sonuçta sohbetin sağında **Asistanın bildikleri** ve **Arka plan** (test bağışları, hafıza, zamanlanmış, doğrulananlar) görünür.
- **Claude önbelleği (1.19.0)**: Yeni çalıştırmada **Claude önbelleği** seçilir (ayardaki / 5 dk / 1 saat / kapalı); aynı senaryoları önbellekli ve önbelleksiz çalıştırıp maliyeti karşılaştırın. Çalıştırma ekranında okunan/yazılan token ve **Önbellek tasarrufu**, geçmiş tablosunda önbellekli çalıştırmada ⚡, raporda da önbellek satırı görünür. (Ücret tahmini önbelleksiz hesaplanır.)
- **Ücret tahmini (1.16.0)**: Yeni çalıştırma kartında her grubun yanında ve altta tüm çalıştırma için tahmini ücret görünür (asistan + değerlendirici ayrı). Geçmiş çalıştırmalardaki senaryo başına ortalama token kullanımı seçilen modellerin fiyatıyla (OpenRouter ya da Anthropic) çarpılır; geçmişi olmayan senaryoda müşteri mesajı başına ortalama kullanılır, hiç çalıştırma yoksa varsayılan tahmin (mesaj başına ~7000 giriş / 250 çıkış token) yapıldığı yazar. Model, grup ya da limit değişince hemen güncellenir; tahmin limiti aşarsa uyarır.
- **Çalıştırma**: asistan modelini, talimatı (yayındaki, yerleşik ya da herhangi bir sürüm), değerlendirici modeli, ücret limitini (USD, varsayılan 0,50) ve grupları seçip başlatın. Çalıştırma sunucuda arka planda adım adım ilerler, sayfayı kapatsanız da sürer. Sayfa açıkken ilerleme, geçen/kalan ve maliyet canlı görünür; yenileyince izlemeye devam edersiniz. Limit dolunca çalıştırma durur. **Durdur** ile kesilen çalıştırma **Devam et** ile kaldığı yerden sürer. (Site kendine istek atarak ilerler; bu engelliyse sayfa açıkken tarayıcı devralır.)
- **Sonuçlar**: her çalıştırma sorular ve cevaplarla birlikte kaydedilir. Geçmiş çalıştırmalar tablosunda model, talimat, başarı oranı, grup başına başarı ve maliyet yan yana görünür; böylece model ve talimat karşılaştırılır. Bir çalıştırmayı açınca solda gruplu senaryo listesi (✓/✕), sağda seçilen senaryonun sohbeti (soldan seçince sayfa sohbete kayar; devam senaryosunda yazılı geçmiş soluk ve "Yazılı geçmiş" etiketli), beklenti sonuçları ve değerlendirici gerekçesi görünür. Üstte ekibe devir doğruluğu da var: doğru devir, doğru devretmeme, gereksiz devir ve kaçan devir.
- **Toplamlar**: sekmenin en üstünde tüm benchmark'ların toplam harcaması, giriş/çıkış token, çalıştırma sayısı, senaryo başarısı ve kullanılan model sayısı görünür; çalıştırma sürerken canlı güncellenir. **Detay** model ve rol (asistan / değerlendirici) başına çalıştırma, başarı, istek, token, maliyet ve senaryo başına maliyeti gösterir.
- **Rapor indir**: geçmiş tablosunda "Rapor", çalıştırma ekranında "Rapor indir (.md)" ve "JSON". Raporda ayarlar, model kırılımı, grup sonuçları, tam talimat, her senaryonun sohbeti, araç çağrıları (argüman ve sonuç), tur başına token/maliyet, kontroller, son durum ve değerlendirici modelin tam isteği ile ham cevabı bulunur. Talimatı ya da senaryoları iyileştirmek için yapay zekâya verilebilir. (Araç ayrıntıları ve değerlendirici logu 1.6.3 ve sonraki çalıştırmalarda vardır.)
- Temsilci ifadeleri (Ayarlar › Yapay zekâ) gerçek hatta olduğu gibi burada ve test sohbetinde de modeli beklemeden devreder.

**Takılmaz**: Her adım en çok ~45 sn sürer; sunucu bir adımı keserse çalıştırma ~1 dk içinde kendiliğinden o adımdan devam eder, aynı adım üç kez kesilirse senaryo hatalı sayılıp geçilir. Adımlar sitenin kendine attığı arka plan isteğiyle zincirlenir (1.20.0: istek bekleyerek atılır, karşı taraf hemen "kabul edildi" der). İstek ulaşmazsa benchmark sekmesi 20 sn'de, sekme kapalıysa Çağrı Merkezi'nin herhangi bir açık sekmesi devralır; Sistem kontrolünde "Arka plan istekleri" uyarısı çıkar (güvenlik eklentisi ya da güvenlik duvarı `/wp-json/flx-chat/` isteklerini engelliyor olabilir).

- **Değerlendirici ve örnek kayıtlar (1.20.0)**: Değerlendirici her araç çağrısının argümanını ve sonucunu, araçların anlamını da görür (yanlış "kaldı" azalır). Test sohbetinde ve benchmark'ta ilk kayıt sorgusunda hiç bağış yoksa iki ödenmiş, sertifikalı **örnek geçmiş bağış** eklenir (Arka plan panelinde görünür); başlangıç durumu verilen senaryoya eklenmez. Ödenmiş test bağışlarının sertifikası vardır.
- **Sistem mesajları (1.22.0)**: Fiyat listesi, ödeme bilgisi, ödeme / sertifika linki ve devir mesajı asistanın yazdığı metin değil, sistemin kendi gönderdiği mesajdır. Test sohbetinde, benchmark sohbetinde ve raporda "Sistem mesajı" etiketiyle görünür; değerlendirici bunları asistanın sözü saymaz.

**Durursa**: OpenRouter istekleri reddederse (ör. "Workspace daily budget exceeded": OpenRouter hesabındaki günlük bütçe; o günkü tüm kullanım, canlı asistan dahil sayılır) benchmark durur, nedeni çalıştırmanın üstünde yazar. OpenRouter › Workspaces / API key ayarlarından bütçeyi artırıp ya da sıfırlanmasını bekleyip **Devam et** ile kaldığı yerden sürdürün. Bu, çalıştırmanın kendi ücret limitinden ayrıdır.

## 11. Yapay zekâ bağlama (geliştirici)
OpenRouter yerleşik sağlayıcıdır. Başka bir servis de bağlanabilir; tanımlanınca **Öneri sağlayıcısı** listesinde çıkar.

```php
add_filter( 'flxc_ai_providers', function ( $list ) {
	$list['openai'] = array(
		'label'   => 'OpenAI',
		// $chat: sohbet satırı, $messages: son 30 mesaj [dir, type, body, ts],
		// $context: replies (hazır cevaplar), agent, brand, locale
		'suggest' => function ( array $chat, array $messages, array $context ) {
			// API'ye gönder, önerilen cevabı string olarak döndür (ya da WP_Error).
			return 'Merhaba, size nasıl yardımcı olabilirim?';
		},
	);
	return $list;
} );
```

Diğer hook'lar:
- `flxc_message_received( $message_id, $chat )`: Yeni gelen mesaj. Otomatik AI cevabı için `flxc_send_text( $chat, $text, array( 'source' => 'ai' ) )` kullanılabilir.
- `flxc_message_sent`, `flxc_chat_created`, `flxc_webhook_event`: Diğer olaylar.
- `flxc_contact_sections( $sections, $chat )`: Kişi paneline yeni bölüm ekler (ör. bağış geçmişi).
- `flxc_reply_vars`: Hazır cevaplara yeni değişken ekler.
- `flxc_auto_reply_text`: Otomatik yanıt metnini değiştirir.
- `flxc_contact_name`: Kişi adını başka bir kaynaktan getirir.
- `flxc_max_upload`: Gönderilebilecek en büyük dosya boyutunu belirler (varsayılan 16 MB).
- `flxc_link_preview( $preview, $url )`: Link önizleme bilgisini değiştirir.
- `flxc_can_record_donation`, `flxc_can_record_certificate`: Kayıt oluşturma yetkisini değiştirir.
- `flx_system_checks` (filtre): Çağrı Merkezi kontrollerini ekler (source `flx-chat`).
- `flxc_bot_handoff( $chat, $reason )`: Asistan sohbeti ekibe devrettiğinde.
- `flxc_message_from_phone( $id, $chat )`: Telefondan elle yazılan mesaj.
- `flx_contact_blocked( $blocked, $phone, $email, $channel )` (filtre) ve `flx_contact_blocked( $phone, $email, $channel )` (fonksiyon): kişi kara listede mi; e-posta/SMS gönderen eklentiler göndermeden önce sorar. `flx_contact_block_changed( $phone, $email, $blocked )`: liste değişti.
- `flxc_site_types`, `flxc_site_allowed( $ok, $post )`: asistanın arayabileceği içerik türleri ve tek içerik kontrolü.
- `flxc_pay_link( $url, $order, $method )`: kişiye giden ödeme linki; `flxc_pay_card_gateway`: kart için ödeme yöntemi. `flxc_email_verified( $chat, $email )`, `flxc_verified( $chat, $type, $value )`, `flxc_reminder_saved( $id, $chat )` eylemleri. Ortak kod doğrulama: `flxc_verify_send( $chat, 'phone'|'email', $value, &$state )`, `flxc_verify_check( $chat, $code, &$state )`; filtreler `flxc_verify_message`, `flxc_verify_phone_channels`.
- `flxc_mirror_chat( $ok, $chat )`, `flxc_mirror_head( $head, $chat )`: ekip grubuna hangi sohbetin ve hangi başlıkla yazılacağı.
- Benchmark'a başka eklentinin senaryoları (filtreler boşken benchmark aynıdır):
  - `flxc_bench_groups( $groups )`: senaryo grubu ekler.
  - `flxc_bench_default_scenarios( $list )`: hazır senaryo ekler (`flxc_bench_normalize` biçiminde, `builtin` 1).
  - Senaryoda `runner` doluysa mesaj turunu Çağrı asistanı değil `flxc_bench_turn( $r, $runner, $history, $state, $model, $sc )` filtresi oynatır.
  - Dönüş `flxc_bot_sandbox_turn` ile aynı biçimdedir: `replies`, `state`, `log [tool, args, result]`, `handoff`, `usage`.
  - Böyle senaryoda `tools`/`no_tools` beklentisi eklentinin kendi araç adlarıyla bakılır.
  - Kullanan: Qurban (yalnız ön ayar açık sitede).

- **Dekont defteri (1.15.0, ortak):** başka eklentiler (ör. Flux Donate'ın sitedeki dekont yükleme alanı) Çağrı Merkezi kurulu olmasa da güvenle çağırır, yoksa `null` döner:
  - `$r = apply_filters( 'flx_dekont_register', null, array( 'payer' => 'Ahmet Yılmaz', 'dekont_no' => 'DK-2026-1019', 'amount' => 150, 'currency' => 'TRY', 'date' => '19.10.2026', 'recipient' => '…', 'iban' => '…', 'source' => 'site', 'donation_id' => 12, 'file' => '/yol/dekont.pdf' ) );` → `array( 'id' => 5, 'duplicates' => array( … ) )`. `duplicates` boş değilse aynı dekont daha önce yüklenmiş (en yeni önce; her biri `payer, dekont_no, amount, date, source, chat_id, donation_id, verdict, created_at`).
  - `$dups = apply_filters( 'flx_dekont_seen', null, array( 'payer' => …, 'dekont_no' => …, 'hash' => sha1_file( $yol ) ) );` yalnız bakar, kaydetmez.
  - Eşleşme: aynı dosya (sha1) ya da aynı dekont no + aynı gönderen (gönderen okunamadıysa yalnız no). Normalize: `flxc_dekont_key()`.
  - `flxc_dekont_register_data` (filtre), `flxc_dekont_registered( $id, $row, $duplicates )` (eylem).
- `flxc_receipt_auto_approve( false, $result, $chat, $state )` (filtre): `true` dönerse bilgileri tutan dekontta bağış yapay zekâ onayıyla ödendi işaretlenir (varsayılan kapalı; ileride AI onayı için).

`flxc_send_text( '905321234567', 'Metin' )` çağrısı sohbet yoksa yenisini açar ve mesajı gönderir.

Flux WPAPI ve hesaplar (1.34.0): WhatsApp hesapları (Evolution API ya da Flux WPAPI) Flux Mail › Bağlantılar'da eklenir; QR, durum ve çoklu hesap orada. Çağrı Merkezi › Ayarlar › Bağlantılar'da hat için İletişim hesabı seçilir, webhook buradan kurulur. Resmi WhatsApp Cloud API hesabı (İletişim 1.10.0+) da seçilebilir; onda webhook İletişim'dedir, QR/geçmiş/grup yoktur ve kişi son 24 saatte yazmadıysa yalnız onaylı şablon gider.

Flux App (1.33.0): Çağrı Merkezi `flxapp_modules` filtresiyle `flxc-inbox` modülü olarak kaydolur; uygulama WhatsApp menüsünü yerel ekranla açar. Uçlar `flx-chat/v1/app/chats`, `/app/chats/<id>`, `/app/chats/<id>/messages?before=|after=`, `/app/chats/<id>/send` `{text}`, `/app/chats/<id>/read`, `/app/chats/<id>/bot` `{on}`; biçim docs/fluxapp/MODUL-API.md. Medya adresleri imzalı ve süreli (`/app/media/<id>?u=&e=&s=`).

Giriş ya da doğrulama kodu gibi panelde görünmemesi gereken metin için `flxc_send_private_text( '905321234567', 'Giriş kodunuz: 123456', array( 'source' => 'flxapp-otp' ) )` kullanın (1.32.0): sohbet açılmaz, mesaj kaydedilmez, asistan susturulmaz. `true` ya da `WP_Error` döner. Asistanı susturmayan kaynaklar `flxc_bot_auto_sources` filtresiyle genişletilebilir. 1.40.0'dan beri asistanı yalnız temsilcinin yazdığı mesaj susturur (kaynaklar `flxc_bot_pause_sources`, varsayılan `agent`, `quick`, `ai`, `record`) ve telefondan yazılan mesaj; sertifika, bağış onayı ve diğer eklenti gönderimleri susturmaz.

**IPTAL (1.44.0, Flux Mail 1.17.0+):** mesajın tamamı IPTAL / BAŞLA ise (`flxi_optout_match()`) asistan ve otomatik yanıt cevap yazmaz, onayı Flux Mail gönderir. `flx-mail` kaynaklı gönderimler asistanı susturmaz. IPTAL yazmış kişiye (`flxi_optout_has( 'whatsapp', $tel )`) hatırlatıcı gitmez; kişi yazarsa cevap verilir. Yardımcılar: `flxc_optout_chat( $chat )`, `flxc_optout_command( $metin )`. Flux Chat'in kendisi tanıtım mesajı göndermez; tanıtım gönderen paketler `flxi_send_message( …, array( 'kind' => 'marketing' ) )` kullanır (docs/iletisim/API.md).

## 12. Dil
Arayüz kaynak dili Türkçedir, İngilizce çevirisi pakettedir. Dil, kullanıcının WordPress dil ayarını izler (Profil › Dil).

## Sorun giderme
- **Gelen mesaj görünmüyor**: Bağlantılar sekmesinde **Bağlantıyı test et**'e basın. "Webhook kurulu değil" yazıyorsa **Webhook'u kur**'a basın. Sitenin dışarıdan erişilebilir olması gerekir; localhost'a webhook gelmez.
- **Gönderilen mesaj ⚠ gösteriyor**: Mesajın menüsünden **Tekrar gönder** seçin. Hata nedeni mesajın altında yazar (ör. instance bağlı değil).
- **Sohbet açılıyor ama eski mesajlar boş**: Yukarıdaki "Eski mesajlar" bölümüne bakın. Mesaj listesinin başında sarı uyarı satırı Evolution'dan dönen hatayı gösterir.
- **Emojiler kırık resim gibi görünüyor**: 1.1'de düzeltildi (WordPress'in emoji betiği bu ekranda kapatıldı). Tarayıcı önbelleğini yenileyin.
- **Link önizlemesi çıkmıyor**: Site o adrese ulaşamıyor ya da sayfada `og:` etiketi yok. Sertifika sayfasında görsel ilk açılışta üretilir; görselsiz önizleme 30 dk sonra yeniden denenir.
- **"WhatsApp bağlı değil" şeridi**: Hat telefondan çıkarılmış olabilir. **QR ile bağla** adımıyla yeniden bağlayın.

## 13. Sistem kontrolü
**Ayarlar › Sistem kontrolü** sekmesi kurulumun yerli yerinde olup olmadığını tek listede gösterir (yeşil tamam, sarı uyarı, kırmızı hata, gri bilgi). Her satırda ilgili ayara giden düğme vardır.
- Kurulum: veritabanı tabloları, medya klasörü, PHP cURL, temsilci rolü, panelin son canlı güncelleme zamanı.
- WhatsApp (hat başına): bağlantı bilgisi tam mı, telefon bağlı mı (son test), webhook'tan son olay ne zaman geldi; Flux Certificates ve Flux Donate etkin mi.
- Yapay zekâ: OpenRouter / Anthropic anahtarı ve modeli (seçili modelin sağlayıcısının anahtarı var mı), bağış asistanı açıksa konuşabiliyor mu (bağış türü, araç destekli model, alıcı adı/IBAN, bağış sayfası).

Kontroller yalnız kayıtlı durumları okur, dışarıya istek atmaz; canlı deneme için Bağlantılar ve Yapay zekâ sekmelerindeki "test et" düğmeleri kullanılır. Aynı kontroller ortak `flx_system_checks` filtresiyle verilir; temanın sistem kontrolü ekranı diğer FluxWP eklentileriyle birlikte bunları da gösterir.

Yeni arayüz (1.35.0, deneysel): Ayarlar › Genel › "Yeni arayüz" kutusu Sohbetler sayfasını uygulamadaki ekranla açar. Sorun olursa kutuyu kaldırın, eski arayüz döner.
