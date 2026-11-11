# Flux Tasks 0.8.16 — Kurulum

ClickUp benzeri görev takibi. Projeler, klasörler, listeler. Liste, Pano (kanban), Takvim görünümleri ve filtreli kayıtlı görünümler. Özel alanlar, yorumlar (sürükle-bırak dosya), bildirimler, liste yetkileri, yönetici panosu, yapay zekâ ve MCP araçları (Flux AI & MCP üzerinden).

## 1. Yükleme

1. WP panel → Eklentiler → Yeni ekle → Eklenti yükle → `flx-tasks.zip` → Şimdi kur → Etkinleştir.
2. Sol menüde **Görevler** çıkar. İlk açılışta hazır gelir:
   - Proje: site adı (ör. **Örnek Vakfı**), herkes düzenleyebilir.
   - Liste: **İşler**, içinde bir hoş geldin görevi.
3. Gereken: WordPress 6.5+, PHP 7.4+.

Tablolar `wp_flxg_*` adıyla açılır. Dosyalar `wp-content/uploads/flx-tasks/` içinde saklanır ve dışarıdan doğrudan açılamaz (`.htaccess` ile kapalı; dosyalar yalnız oturum açmış ve yetkili kullanıcıya eklenti üzerinden verilir).

> Nginx kullanan bir sunucuya taşınırsa `.htaccess` çalışmaz. O zaman sunucu ayarına `location ^~ /wp-content/uploads/flx-tasks/ { deny all; }` eklenmeli. Hostinger (LiteSpeed) için bir şey yapmaya gerek yok.

## 2. İlk ayarlar (Görevler → Ayarlar)

- **Görevleri kullanabilir**: hangi WP rolleri uygulamayı açar. Varsayılan Administrator, Editor, Author.
- **Proje, klasör ve liste oluşturabilir**: kendi listesini/projesini açabilenler. Varsayılan Administrator, Editor.
- **Yapay zekâ kullanabilir**: asistanı görecek roller.
- **Canlı güncelleme aralığı**: kullanıcı ekrandayken 10 sn'de bir tek hafif istek atılır (başkalarının yorumu, yeni proje vb. hızlı gelir). 2 dakika hareketsiz kalınca bu ayardaki aralığa (varsayılan 45 sn) düşer. Arka plandaki sekme hiç istek atmaz, hata olursa bekleme 5 dakikaya kadar uzar.
- **E-posta bildirimleri**: açılırsa atama, bahsetme ve yaklaşan bitiş tarihi e-postayla da gider. Herkes profilinden kapatabilir. Flux Mail kuruluysa bu üç e-posta **İletişim › E-postalar**'da "Flux Tasks" grubunda görünür; metni oradan düzenlenir, tek tek kapatılabilir ve kurumsal şablonla gider. Flux Mail yoksa düz e-posta gider.
- **Tek dosya sınırı (MB)**: sunucunun PHP yükleme sınırından büyük olamaz.

Kaydet'e basmayı unutmayın.

**Sistem kontrolü** (Ayarlar sayfasının en üstü): veritabanı tabloları, dosya klasörü (yazılabilir ve dışarıya kapalı mı), yükleme sınırı, günlük hatırlatma cron'u, yapay zekâ (Flux AI) durumu, MCP araçlarının Flux AI & MCP'de olup olmadığı ve e-posta durumu. Kırmızı = hata, turuncu = uyarı. Kontroller ortak `flx_system_checks` filtresine de kaydolur; tema ya da Flux Donate'ın toplu Sistem kontrolü ekranında da görünür.

## 3. Yetki sistemi

Roller: **Görüntüler** < **Yorum yapar** < **Düzenler** < **Yönetir**.

- **Proje** erişimi: *Herkes* (seçilen varsayılan rolle) ya da *Yalnız üyeler*. Proje menüsü (⋯) → Proje ayarları ve üyeler.
- **Liste** erişimi: projeden devralır ya da *Özel liste* (yalnız eklenen kişiler). Liste menüsü (⋯) → Yetkiler, ya da liste üstündeki **Paylaş**.
- Kişiye özel rol verilebilir. Proje yöneticisi projedeki tüm listeleri yönetir. WP yöneticileri her şeyi görür.
- Yetkili kullanıcılar kendi listesini açabilir. Özel liste açarsa yalnız eklediği kişiler görür.

## 4. Kullanım özeti

- **Görünümler**: Liste, Pano, Takvim sekmeleri. Filtre/Grup/Sırala/Alanlar ile değiştirip **Görünümü kaydet** → *Yeni görünüm olarak kaydet* ile filtreli alternatif (ör. "Yüksek öncelik") oluşur. Görünüm herkesle paylaşılabilir ya da kişiye özel kalır.
- **Sütunlar (liste)**: başlıktaki sütun adını sürükleyerek sırasını, sağ kenarını sürükleyerek genişliğini değiştirin (çift tık varsayılana döndürür). Görünümü düzenleme yetkiniz varsa hemen kaydedilir.
- **Alt görevler**: listede görevin altında bir kademe içeride görünür, oktan aç/kapat. Satırın sağındaki **+** ile alt görev eklenir. Araç çubuğundaki **Alt görevler** düğmesi: varsayılan açık / kapalı / gösterme ve **Filtreyi alt görevlere de uygula** (filtre menüsünde de var). Kademe sınırı proje ayarlarında (**Alt görev kademe sınırı**, varsayılan 3, en fazla 10).
- **Görev detayı**: ortada açılır, başlık + durum + Tamamla kaydırınca üstte sabit kalır. Alt görevler mini tablo: varsayılan Görev adı + Atananlar, tablonun sağındaki **+** ile başka sütun (durum, bitiş, öncelik, özel alan…) eklenir, hücreler yerinde düzenlenir. Sağdaki **Yorumlar ve etkinlik** sütunu ok ile kapatılır; kapalı bırakırsanız sonraki görevlerde de kapalı açılır.
- **Kenar menü**: sağ kenarından sürükleyerek genişletin, üstteki simgeyle gizleyin; gizliyken başlığın solundaki simgeyle geri açılır.
- **Projeler sol menüde**: varsayılan kapalı, açık olan listenin projesi kendiliğinden açılır; Görevler'e dönünce son açtığınız liste açılır. Proje yöneticisi projeleri sürükleyerek sıralar.
- **Liste taşıma**: listeyi sol menüde başka projenin ya da klasörün üstüne sürükleyin veya liste menüsünden **Taşı…**. Başka projeye taşırken: durumları koruyun ya da hedef projedeki bir listenin durumlarını seçip her durumun karşılığını eşleyin (görevler yeni duruma geçer); etiketler hedefte aynı adla bağlanır ya da oluşturulur; hedef projeye erişimi olmayan atanmış kişiler gösterilir, işaretlenenler üye eklenir.
- **Alanlar** düğmesi: her görünümde hangi alanların (sütun / kart alanı) görüneceği ayrı seçilir. Pano ve takvimde **Kapak görselini göster** buradan açılır.
- **Kapak görseli**: görev detayında Ekler'den bir görseli kapak yapın (ya da ilk eklenen görsel önerilir).
- **Özel alanlar**: Liste ayarları → Özel alanlar. Türler: metin, uzun metin, sayı, tutar, tarih, onay kutusu, açılır liste, etiketler, yıldız, kişi, dosya, bağlantı, e-posta, telefon.
- **Durumlar**: her listenin kendi durumları ve renkleri var (Liste ayarları → Durumlar).
- **Sürükle-bırak**: listede satırı, panoda kartı, takvimde görevi sürükleyin. Telefonda basılı tutup sürükleyin.
- **Görseller**: açıklamaya görsel yapıştırın ya da sürükleyin, olduğu yere yerleşir. Detay sütununun herhangi bir yerine bırakılan dosya eklere gider.
- **Arka plan işleri**: kaydetme ve yüklemeler arka planda sürer, sol altta küçük bir halka dolarak gösterir (ortada süren iş sayısı). Görevi kapatsanız da devam eder. Sunucu yoğun derse kendisi yeniden dener, başarısız olursa göstergeden **Tekrar dene**.
- **Yorumlar**: dosya/görseli yorum kutusuna sürükleyin ya da yapıştırın. `@` ile kişi etiketlenir, ona bildirim gider.
- **Bildirimler**: atama, durum değişikliği, yorum, bahsetme, yaklaşan ve geciken bitiş tarihleri. Sol menüde sayaç, WP üst çubuğunda da görünür. Profil → Masaüstü bildirimleri açılabilir.
- **Kısayollar**: `n` yeni görev, `/` arama, `Esc` kapat.
- **Kullanım panosu (yönetici)**: dosya depolama (MB), listelere ve kişilere göre kullanım, aylık yükleme, en büyük dosyalar, veritabanı tablo boyutları, AI kullanımı.

- **Dokümanlar**: kenar menüde projenin altındaki **+ Ekle → Doküman** (ya da klasör menüsü). Soldaki sayfa ağacından sayfa/alt sayfa ekleyin, sürükleyerek sıralayın, ok ile açıp kapatın (üstteki simgeyle panel gizlenir). Editörde araç çubuğu ya da `/` ile başlık, liste, kontrol listesi, tablo, görsel, dosya; `#` yazınca görev arayıp bağlantı eklenir (tıklayınca görev açılır, tamamlanınca üstü çizilir). Görsel yapıştırılabilir ya da sürüklenebilir, tıklayınca boyutu seçilir. Kaydetmek gerekmez, otomatik kaydedilir. **… → Yazdır / PDF**.
- **Birlikte düzenleme**: aynı sayfayı açan kişiler üst çubukta görünür; imleçleri kendi renginde adlarıyla, yaptıkları değişiklik renkli kenarla gösterilir. Hostinger'da websocket yok; sayfa başkası varken 2 saniyede bir, yalnızken daha seyrek kontrol eder, sekme gizliyken durur. Aynı paragrafı aynı anda iki kişi yazarsa son yazan kazanır.
- **Toplantılar**: **+ Ekle → Toplantı listesi**. Liste görev listesi gibi çalışır (Liste/Pano/Takvim, filtre, özel alan), sütunlar Tarih/Katılımcılar/Etiketler, durumlar Planlandı/Yapıldı/İptal (Liste ayarları'ndan değişir). Toplantıyı açınca: tarih ve saat, katılımcılar, etiketler, açıklama, **Gündem** (madde ekleyin; tıklayınca not ve görsel/dosya eklenir, görseller satırda küçük önizleme olarak görünür, daire ile "görüşüldü" işaretlenir, sürükleyerek sıralanır), **Kararlar** (görev gibi sorumlu, son tarih, etiket; kararlar sorumlunun Benim görevlerim sayfasına düşer), ekler ve yorumlar. Durum satırındaki **…** özelleştirme düğmelerini açar: bölümleri gizle/göster, karar sütunları, özel alan ve durum düzenleme. **PDF rapor** toplantıyı rapor olarak açar; yazdır penceresinde "PDF olarak kaydet" seçin.
- Var olan görev listesi Liste ayarları → Genel → **Liste türü** ile toplantı listesine çevrilebilir (durumlar değişmez).

Telefonda: sol üstteki menüden projeler, sağ alttaki yeşil düğmeden yeni görev. Ana ekrana eklenince uygulama gibi açılır.

## 5. Yapay zekâ (Flux AI)

Yapay zekâ için **Flux AI** eklentisi zorunludur; anahtarlar, harcama sınırı ve raporlar orada. Flux AI yoksa asistan ve ✦ düğmeleri görünmez, Ayarlar'da uyarı çıkar.

1. Flux AI'yi kurun, Flux AI & MCP › Ayarlar'dan anahtar girin. (0.4.0'dan önce Flux Tasks'e girilmiş OpenRouter anahtarı ve model, güncellemeden sonra ilk yönetici sayfasında Flux AI'ye otomatik aktarılır ve buradan silinir.)
2. Görevler → Ayarlar → **Yapay zekâ**: **Model** isteğe bağlı (Flux AI'nin aranabilir listesi). Boşsa Flux AI varsayılanı kullanılır; seçim Flux AI'de "Eklentiye özel model" olarak saklanır.
3. **Kurum notları**: asistanın her istekte bileceği kurallar (etiket kuralları, tarih biçimi vb.).

Kullanım:
- Görev detayında **Açıklama** başlığının yanındaki ✦ **Yapay zekâ** düğmesi: açıklama yaz/geliştir, kısalt, kontrol listesine çevir, yazımı düzelt, görev adını iyileştir, alt görevlere böl, yorumları özetle ya da kutuya kendi isteğinizi yazın. Sonuç panelde kalır; uygulayıp **Geri al**, yeniden üret ya da "şunu değiştir" diye devam edebilirsiniz.
- Sol menü / üst çubukta ✦ **Yapay zekâ asistanı**: "Ayşe'ye cuma günü bitecek afiş görevi aç", "Bu listede geciken görevleri listele" gibi. Asistan kullanıcının kendi yetkisiyle çalışır, göremediği listeye dokunamaz. Belgeleri de okur ve düzenler; belge açıkken "bu sayfa" o sayfadır.
- **Dokümanlarda**: üst çubuktaki ✦ **Yapay zekâ** düğmesi (Ctrl/⌘+J) ile serbest istekle yazdırın ya da sayfaya soru sorun; Devam et, Özetle, Yapılacakları çıkar, Taslak çıkar, Başlık öner. Metin seçince seçimin üstünde ✦ **Yapay zekâ** çıkar: iyileştir, yazımı düzelt, kısalt/uzat, sadeleştir, resmi/samimi, maddelere/tabloya çevir, İngilizce/Türkçe çevir, açıkla ya da kendi isteğiniz. Sonuç önce önizlenir; **Değiştir** / **Altına ekle** / **Sona ekle** / **Yeni sayfa olarak ekle**, yanlışsa bildirimdeki **Geri al**. Panel siz kapatana kadar açık kalır. **Yapılacakları çıkar** maddeleri düzenlenebilir liste olarak verir: düzeltin, silin, ekleyin, listeyi seçip **Görev olarak ekle**.
- **Editör**: satır başında ya da boşluktan sonra "/" yazın (Notion gibi): başlık, liste, kontrol listesi, tablo, görsel, görev bağlantısı ve yapay zekâ komutları; /h1, /todo, /table gibi kısayollar. Metin seçince kalın, italik, altı çizili, üstü çizili, kod, bağlantı, başlık ve liste düğmeleri de çıkar.
- **Ortak Flux AI sohbeti** (sağdaki baloncuk): Flux Tasks'in tüm araçları orada da var (adları "flxg_" ile başlar). Belgedeyken "bu sayfaya bir gündem ekle" ya da "ikinci paragrafı daha resmi yap" yazın; değiştiren araçlar önce onay kartı gösterir, onaylayınca açık sayfada değişiklik canlı görünür. Flux AI & MCP › Araçlar'dan tek tek kapatılabilir.

## 6. MCP (Claude, ChatGPT, Cursor vb.)

Flux Tasks kendi MCP sunucusunu çalıştırmaz. Aynı araçlar Flux AI & MCP sunucusuna kaydolur: `https://example.org/wp-json/flx-mcp/v1/mcp`. Anahtar ve aç/kapa **Flux AI & MCP › MCP** (`admin.php?page=flx-ai-mcp`). Flux AI kurulu değilse görev takibi çalışır; MCP aracı olmaz. Profil sayfası bunu yazar ve Flux AI açıksa o ekrana bağlar.

Araç adları `flxg_` öneklidir: `flxg_list_projects`, `flxg_list_users`, `flxg_search_tasks`, `flxg_get_task`, `flxg_create_task`, `flxg_update_task`, `flxg_add_comment`, `flxg_list_fields`, `flxg_create_list`, `flxg_delete_task`, `flxg_list_notifications`, `flxg_list_docs`, `flxg_get_doc_page`, `flxg_edit_doc_page`, `flxg_create_doc`, `flxg_create_doc_page`. Eski sunucunun öneksiz adları (ör. `list_projects`) da çalışır. Her araç `flxg_use` ve liste yetkisini kendi içinde denetler. Ortak Flux AI sohbeti aynı araçları kullanır.

Flux AI MCP rotası `edit_posts` ister. `flxg_use` olup `edit_posts` olmayan kullanıcı bu rotaya giremez; Flux AI'nin bu kontrolü gevşetilmedi.

## 7. Hostinger notları

- Arayüz arka planda sık istek atmaz (bkz. canlı güncelleme). Sekme gizliyken tamamen durur.
- Güncellemeler POST ile gider, silme `X-HTTP-Method-Override: DELETE` ile gider. Bazı güvenlik duvarlarının PATCH/DELETE engeline takılmaz.
- LiteSpeed Cache açıksa bir şey yapmaya gerek yok. Eklentinin API yanıtları `no-store` ve `X-LiteSpeed-Cache-Control: no-cache` ile gönderilir, hiçbir katmanda önbelleğe alınmaz.
- Günlük işler (geciken görev bildirimi, 24 saati geçen gönderilmemiş yorum eklerinin silinmesi) WP-Cron ile çalışır.

## 8. Dil

Kaynak metinler Türkçe. Site ya da kullanıcı dili İngilizce olunca arayüz İngilizce görünür (`languages/flx-tasks-en_US.l10n.php`). Tarih ve sayılar kullanıcının diline göre biçimlenir.

## 9. Kaldırma

Eklentiyi silmek verileri silmez. Tüm görev verisini ve dosyaları da silmek için önce Ayarlar → **Eklenti silinince tüm verileri sil**'i açın, sonra eklentiyi silin.

## 10. Güncelleme

Yeni sürüm zip'ini aynı yolla yükleyin ("Mevcut olanı yüklenenle değiştir"). Veriler korunur, tablo değişiklikleri otomatik yapılır.

## Sürüm notları

- **0.8.16**: Kendi MCP sunucusu, anahtar deposu ve Ayarlar'daki MCP anahtarı kalktı. Araçlar Flux AI & MCP'ye `flx_mcp_tools` ile kaydolur.
- **0.8.2**: Toplantı raporunda kararların ve gündem maddelerinin alt maddeleri de çıkar.
- **0.8.1**: Toplantı PDF raporu yeni tasarım (logo, bilgi kartları, özet, rozetli tablolar); raporda alt görevler girintili listelenir.
- **0.8.0**: Flux App (telefon uygulaması) Görevler menüsünü kendi yerel ekranıyla açar; uygulama için `flx-tasks/v1/app/*` uçları. Ek ayar gerekmez; Flux App kurulu değilse hiçbir şey değişmez.
- **0.7.0**: Sol menüde projeler kapalı başlar (aktif olan açık); projeleri sürükle-bırak sıralama; listeyi başka projeye/klasöre taşıma (durum eşleme, etiket ve üye kontrolü).
- **0.6.1**: Kompakt AI menüsü; alt sayfa sağdan yarım panelde (tam sayfa/kapat); açılmış sayfalar beklemeden açılır; tablolarda satır/sütun tutamacı (ekle, taşı, sürükle, genişlik, renk); AI önizlemesinde tablo; alt boşluk azaldı.
- **0.6.0**: Notion gibi satır tutamacı (+ ve sürükle, blok menüsü), sade "/" menüsü (markdown kısayolu, önizleme), satır başında markdown kısayolları (# , - , [] …), "/sayfa" ile sayfa içinde alt sayfa bloğu (iç içe sınırsız). Yapılacaklar düzenlenebilir maddeler + seçilen listeye görev; her sonuçta "Yeni sayfa olarak ekle"; panel tıklayınca kapanmaz; seçimde biçim düğmeleri; Notion gibi "/" menüsü.
- **0.5.0**: Dokümanlarda yapay zekâ: üst çubukta genel düğme (yaz, devam et, özetle, yapılacaklar, taslak, başlık, sayfaya sor) ve seçili metnin üstünde çıkan düğme (iyileştir, düzelt, kısalt, çevir, açıkla…). Belge araçları asistana, MCP'ye ve ortak Flux AI sohbetine eklendi; ortak sohbet artık belge düzenleyebilir.
- **0.4.1**: Belge sayfasında "E-posta taslağı" düğmesi: sayfa Flux Mail posta kutusunda taslak olur, alıcıyı orada seçip gönderirsiniz. Görseller alıcı görebilsin diye uploads/flx-tasks-mail altına herkese açık kopyalanır.
- **0.4.0**: Yapay zekâ yalnız Flux AI üzerinden (zorunlu). Eski OpenRouter anahtarı/model Flux AI'ye aktarılır, kendi AI bağlantı kodu ve anahtar alanı kaldırıldı.
- **0.3.5**: Flux AI kuruluysa yapay zekâ çağrıları oradan gider (anahtar, model, harcama sınırı, raporlar Flux AI'de); yoksa eski ayar çalışır.
- **0.3.4**: Çağrı Merkezi'nde seçilen Claude modelleri (Haiku vb.) Anthropic bağlantısıyla çalışır.
- **0.3.3**: Detayda bölümler katlanır, 4'ten fazla alan kısalır, toplantı tam sayfa açılır, kararlar ortak tabloda (sütun genişliği, göster/gizle), belirgin Geri butonu, değişiklikler anında görünür; ekran tercihleri hesapta saklanır.
- **0.3.2**: Doküman ortak düzenleme: yeni katılan 1–2 sn'de görünür, "Son düzenleme" gerçek yazanı gösterir, fazla isim etiketi kalktı, yoklama biri yazarken sıklaşır, sessizken seyrekleşir.
- **0.3.1**: Doküman sayfası kağıt görünümünde (yeşil çerçeve yok, sayfanın dışı hafif farklı renk).
- **0.3.0**: Dokümanlar (çok sayfalı, sayfa ağacı, Word benzeri editör, görsel/tablo/görev bağlantısı, yazdır/PDF) ve canlı birlikte düzenleme (renkli imleç ve isim). Toplantı listesi şablonu: gündem (mini önizlemeli), kararlar, özelleştirilebilir sade ekran, PDF rapor. Veritabanı şeması 3 (yeni tablolar güncellemede otomatik eklenir).
- **0.2.2**: Görev adının yanındaki ikonlar 2'li sütun halinde, en fazla 2 satır.
- **0.2.1**: Alt görevler listede sürükle-bırak ile taşınır (başka görevin altına veya üst seviyeye). Sürüklerken satır görünümü bozulmaz. Alanlar > "Metni sar" ile uzun metinler en fazla 3 satır görünür. Sütunlar ekrana sığdırılmaz, liste sağa sola kayar; görev adı solda sabit kalır.
- **0.2.0**: Liste sütunlarının genişliği ve sırası değiştirilebilir. Alt görevler listede görevin altında iç içe (aç/kapat, varsayılan açık/kapalı/gizli, filtreyi alt görevlere uygula). Alt görev kademe sınırı proje ayarı (varsayılan 3). Görev detayında alt görevler mini tablo (sütun seçilebilir). Görev detayı ortada, kenarlarda boşluklu; başlık/durum/Tamamla sabit; yorum/etkinlik sütunu kapatılabilir ve tercih hatırlanır; açıkken WP sol menüsü de kararır. Kenar menü genişletilebilir ve gizlenebilir. Benim görevlerim'de üst görevi bana atanmamış alt görevler de görünür. Veritabanı şeması 2 (projelere `subtask_depth` sütunu, güncellemede otomatik eklenir).

- **0.1.5**: Bildirim e-postaları Flux Mail'e kayıtlı şablon olarak (flx-tasks/assigned, mention, due); Flux Mail yoksa wp_mail. Menü ikonu ortak FluxWP logosu.

- **0.1.4**: Sistem kontrolü (Ayarlar'da ve ortak `flx_system_checks` ekranında).

- **0.1.3**: Koyu temada okunmayan giriş kutuları düzeldi (WP form stili eziliyordu), kutular yuvarlak. Yorum kutusunda tek odak çizgisi. Yükleme ekranları iskelet (skeleton), görev detayı başlığıyla hemen açılır. Yapay zekâ düğmesi Açıklama bölümüne taşındı (seçenekler + serbest istek, panel açık kalır). Sol alttaki gösterge sade halka.

- **0.1.2**: Yorum/alt görev gönderilirken görev kapatılıp açılsa da kaybolmaz. API yanıtları önbelleğe alınmaz (eski veri görünmez). Ekler yüklenirken önizlemeyle hemen görünür, açıklamaya görsel yapıştırılır/sürüklenir. Sol altta arka plan işleri göstergesi, sunucu yoğunsa otomatik yeniden deneme. Canlı güncelleme ekrandayken 10 sn.

- **0.1.1**: Yorum ve alt görev anında görünür (sunucu yanıtı beklenmez, gönderilirken "Gönderiliyor…" yazar). Daha önce açılmış görev detayı anında açılır. Başkasına atanan ya da filtreye artık uymayan görev ekrandan kaybolmaz; "Atandı: Kişi" / "Filtre dışı" etiketiyle sayfa değişene kadar kalır (yapay zekâ asistanıyla yapılan değişiklikler dahil).
