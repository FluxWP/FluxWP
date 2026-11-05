# Flux Tasks — Değişiklikler

## 0.8.11
- Güvenlik (CodeQL): `h()` çocukları yalnız DOM düğümü ya da düz metin olarak eklenir.

## 0.8.10
- Güvenlik (CodeQL): dosya adresi yalnız sayısal dosya kimliğiyle kurulur (`Y.fileUrl`); DOM'dan okunan `data-file` değeri adrese ham girmez.
- Güvenlik: `h()` yardımcısında HTML yalnız açık `html` anahtarından girer; diğer öznitelik değerleri HTML olarak yorumlanmaz. Açıklama var mı kontrolü regex yerine DOMParser ile.

## 0.8.9
- Metinler: MCP sunucusu artık Flux AI & MCP eklentisinde; "Flux SEO & MCP" ve "Flux AI ›" adresleri "Flux AI & MCP" oldu.

## 0.8.8
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 0.8.7
- Açık tema: soluk metinler (KPI alt yazıları, ayar grup başlıkları, yol ayırıcıları) ve uyarı rengi daha okunur. Koyu tema: koyu marka renginde etkin menü öğesi ve vurgu metinleri okunur. Site ikonu yoksa başlıkta Flux Tasks paket ikonu.

## 0.8.6
- Koyu modda yenilemede açık tema yanıp sönmesi giderildi: tema, uygulama kabı çizilir çizilmez (pages.js beklenmeden) uygulanır.

## 0.8.5
- Varsayılan vurgu rengi yeşil yerine Flux Admin / tema rengi (ayarlarda renk seçilmediyse).

## 0.8.4
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 0.8.3
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 0.8.2
- Toplantı raporu: kararların ve gündem maddelerinin altındaki maddeler (iç içe tüm kademeler) artık raporda. Kararlar tablosunda her kararın altında girintili, işaretli satırlar (sorumlu, son tarih, durum); gündem maddesinin altında işaretli liste.

## 0.8.1
- Toplantı PDF raporu yeniden tasarlandı: logolu başlık, durum rozeti, bilgi kartları (tarih, proje, katılımcılar, etiketler, özel alanlar), özet kutuları (görüşülen gündem, karar, tamamlanan alt görev), numaralı gündem, rozetli karar tablosu, alt bilgi. Renkler projenin rengini alır, yazdırırken arka planlar korunur, tablo başlığı yeni sayfada tekrar eder.
- Raporda **Alt görevler** bölümü: alt görevler ve onların alt görevleri girintili; atananlar, bitiş tarihi, durum ve tamamlandı işareti.

## 0.8.0
- Flux App yerel ekranı: `flxapp_modules` ile `flxg-tasks` modülü (menü `flx-tasks`, yetki `flxg_use`). Uygulama Görevler menüsünü kendi ekranıyla açar.
- Yeni uçlar `flx-tasks/v1/app/*`: projeler (listeler ve açık görev sayıları), görevler (benim/bugün/geciken/proje, arama, sayfalama), görev ayrıntısı (açıklama, kontrol listesi, yorumlar, durumlar), görev ekle/düzenle, yorum, kontrol listesi maddesi işaretle. Yetki her uçta görev/liste düzeyinde denetlenir. Sözleşme: `docs/fluxapp/MODUL-API.md`.
- Öncelik sözleşmede 0-3 (3 en yüksek); eklentinin kendi değeri `priority_key` (1 Acil … 4 Düşük) olarak da gelir. Durumda `status` open/done, ayrıca `status_key`/`status_label`/`status_color`.

## 0.7.0
- Sol menüde projeler varsayılan kapalı başlar; açık olan listenin/dokümanın projesi kendiliğinden açılır (klasörü de). Açılan/kapanan projeler hatırlanır. Uygulamaya dönünce son açılan liste açılır.
- Projeler sol menüde sürükle-bırak ile sıralanır (proje yöneticisi).
- Liste taşıma: listeyi başka projeye ya da klasöre sürükleyin veya liste menüsünden **Taşı…**. Aynı projede yalnız yeri değişir. Başka projeye taşırken pencere:
  - Durumlar: listenin kendi durumlarını koru ya da hedef projedeki bir listenin durumlarını kullan; her durumun karşılığı seçilir (önce ad, sonra tür eşleşir), görevler arka planda yeni duruma geçer.
  - Etiketler hedef projede aynı adla bağlanır, yoksa oluşturulur.
  - Hedef projeye erişimi olmayan atanmış kişiler/liste üyeleri gösterilir; işaretlenenler hedef projeye üye eklenir.
- Yeni REST: `GET lists/{id}/move-check?space=`, `POST lists/{id}/move`.

## 0.6.1
- Belgede yapay zekâ menüsü kompakt: 300 px genişlik, küçük satır ve ikonlar; işlem listesi en fazla 260 px yükseklikte kendi içinde kayar. Sonuç ve madde ekranları eski genişlikte kalır.
- Alt sayfa bağlantısı önce sağda yarım panelde açılır (Notion gibi): solda üst sayfa görünür kalır, panelde **Tam sayfa** ve **Kapat** (ya da sol tarafa tıklama). Ağaçtan açılan sayfa tam sayfa açılır; 900 px altında hep tam sayfa.
- Sayfa açılınca en üstten başlar. Daha önce açılmış doküman ve sayfa yükleme animasyonu olmadan hemen görünür, arkada tazelenir; değişiklik varsa (yerelde yazılmamışsa) sessizce yenilenir.
- Doküman modunda sayfanın altındaki boşluk küçüldü (tam sayfa boşluk yerine kısa bir pay); boşluğa tıklayınca son satıra geçilir.
- Yapay zekâ sonucunu "İmlecin altına ekle" sayfayı en üste kaydırmıyor; editöre odak verilirken kaydırma olmuyor.
- Yapay zekâ önizlemesinde tablolar çizgili, başlık satırı belirgin görünür.
- Tablolar: üzerine gelince sütunun üstünde ve satırın solunda tutamaç çıkar. Tıklayınca menü (satır/sütun ekle, taşı, arka plan ve metin rengi, sil, tabloyu sil); sürükleyince satır/sütun taşınır. Sütun kenarından sürükleyerek genişlik ayarlanır. Renkler `data-bg`/`data-fg` (9 renk, koyu temada uyumlu), genişlik hücrede `width` olarak saklanır.

## 0.6.0
- Notion gibi düzenleme. Satırın üstüne gelince solda **+** ve **⋮⋮** tutamacı çıkar: + altına blok ekleyip "/" menüsünü açar (Alt+tık üstüne), tutamaç sürüklenerek blok taşınır, tıklanınca menü açılır (Metin/Başlık/Alıntı/Kod'a dönüştür, Yapay zekâ ile düzenle, Çoğalt, Yukarı/Aşağı taşı, Sil).
- "/" menüsü sadeleşti: gruplar, sağda markdown kısayolu, vurgulanan öğenin yanında küçük önizleme, altta "Menüyü kapat · esc".
- Markdown kısayolları satır başında: `# `, `## `, `### `, `- `, `1. `, `[] `, `> `, ` ``` `, `---`. Satır başındaki "#" artık görev seçiciyi açmaz (metin içinde açar; "/görev" de var).
- Alt sayfa bloğu: "/sayfa" (ya da ağaçta açık sayfaya "Alt sayfa ekle") bu sayfanın içinde alt sayfa açar, yerine tıklanabilir sayfa bağlantısı koyar; iç içe sınırsız. Bağlantı adı sayfa adıyla güncellenir, silinen sayfa soluk görünür. Bağlantısı olmayan alt sayfalar sayfanın en altında listelenir. E-posta taslağında bağlantı düz metin olur.
- Yapılacakları çıkar: madde her zaman üretilir (sorular "…belirle" gibi göreve çevrilir); maddeler düzenlenir, silinir, eklenir; kişi ve tarih seçilir; görevlerin ekleneceği liste seçilir; yazarak yeniden düzenletilir. "Görev olarak ekle" seçilen listeye görev açar.
- Yapay zekâ paneli sayfada başka yere tıklayınca kapanmaz (✕ ya da Esc). Her sonuçta **Yeni sayfa olarak ekle** (alt sayfa + bağlantı bloğu).
- Metin seçince çıkan çubukta biçim düğmeleri: kalın, italik, altı çizili, üstü çizili, kod, bağlantı, başlık, madde listesi.

## 0.5.0
- Dokümanlarda yapay zekâ (Flux AI üzerinden). Üst çubukta **Yapay zekâ** düğmesi: serbest istekle yaz ya da sayfaya soru sor; Devam et, Özetle, Yapılacakları çıkar (kontrol listesi), Taslak çıkar, Başlık öner. Sonuç önce önizlenir; imlecin altına, sona ya da başa eklenir, kopyalanır, yeniden üretilir ya da "daha kısa olsun" gibi bir yönergeyle düzeltilir. Eklenen metin bildirimdeki **Geri al** ile kaldırılır.
- Metin seçince seçimin üstünde **Yapay zekâ** düğmesi çıkar (mobilde altında): İyileştir, Yazım ve dil bilgisini düzelt, Kısalt, Uzat, Sadeleştir, Daha resmi/samimi, Maddelere/Tabloya çevir, İngilizceye/Türkçeye çevir, Açıkla ya da serbest istek. **Değiştir** seçimi sonuçla değiştirir (Ctrl+Z ve Geri al çalışır), **Altına ekle** ayrı blok ekler. Kısayol Ctrl/⌘+J; editörde "/" menüsünde "Yapay zekâ ile yaz". Yalnız görüntüleme yetkisi olanlar Açıkla/Çevir/Özetle/Sor kullanır, sonucu kopyalar. `POST doc-pages/{id}/ai`, Flux AI raporunda amaç `doc_*`.
- Belge araçları: `list_docs`, `get_doc_page`, `edit_doc_page` (blok kimliğiyle değiştir/ekle/sil, sayfa adı), `create_doc`, `create_doc_page`. Flux Tasks asistanı ve MCP bu araçları kullanır; asistan açık belge sayfasını bilir.
- Ortak Flux AI sohbeti: tüm Flux Tasks araçları `flxg_` önekiyle `flxai_tools`'a kaydedildi (değiştiren araçlar onay kartı ister, `delete_task` her zaman onaylı). Sohbet, adres `#/doc/{id}/p/{id}` ise "bu sayfa"yı bilir; sayfayı okuyup düzenler, açık editörde değişiklik canlı görünür. Belge ekranında sekme başlığı sayfa adı olur.

## 0.4.1
- Belge sayfasında **E-posta taslağı** düğmesi (Flux Mail 1.8.0+ ve posta kutusu yetkisi varsa): sayfa içeriği Flux Mail posta kutusunda "Kurumsal" şablonlu taslak olur (konu = sayfa başlığı, not = belge adı). Alıcıyı posta kutusunda seçip gönderirsiniz; bildirimdeki "Posta kutusunda aç" taslağı açar. Bekleyen düzenlemeler önce kaydedilir.
- E-postada görseller ve dosya bağlantıları alıcının açabilmesi için `uploads/flx-tasks-mail/` altına herkese açık kopyalanır (yalnız o sayfada kullanılanlar); görev bağlantıları düz metin, kontrol listesi ☑/☐ olur. `POST doc-pages/{id}/email-draft`.

## 0.4.0
- Yapay zekâ yalnız Flux AI üzerinden; Flux AI zorunlu. Kendi OpenRouter bağlantısı, Flux Chat'ne düşme, Claude yönlendirmesi, OpenRouter model listesi (`GET ai/models`) ve anahtar alanı kaldırıldı.
- Geçiş: Flux Tasks'e girilmiş OpenRouter anahtarı ve model ilk yönetici sayfasında `flxai_import_config( 'flx-tasks' )` ile Flux AI'ye aktarılır (Flux AI'de dolu alanlara dokunulmaz), buradaki kopya silinir.
- Ayarlar › Yapay zekâ: model isteğe bağlı (Flux AI model seçici), `flxai_set_source_model` ile Flux AI'de saklanır. Flux AI yoksa ya da hazır değilse uyarı çıkar, asistan ve ✦ düğmeleri gizlenir; sistem kontrolü de uyarır.

## 0.3.5
- Flux AI desteği: Flux AI kurulu ve anahtarı hazırsa tüm yapay zekâ çağrıları (asistan ve hızlı işlemler) Flux AI üzerinden gider (`source` flx-tasks, `purpose` assistant/title/description/subtasks/summary). Anahtar, harcama sınırı ve raporlar Flux AI'de; harcama sınırı hatası 402 olarak döner. Flux AI yoksa eski yol (kendi OpenRouter anahtarı / Çağrı Merkezi) aynen çalışır.
- Ayarlar: Flux AI varken anahtar alanı gizlenir, model alanı isteğe bağlıdır ve Flux AI'nin aranabilir model listesini kullanır (boşsa Flux AI'de bu eklentiye seçilen model). Sistem kontrolü "Flux AI üzerinden" gösterir.

## 0.3.4
- Yapay zekâ: Flux Chat'nde seçilen Claude modelleri ("claude:" önekli, ör. Haiku) artık OpenRouter'a gönderilmiyor; istek Çağrı Merkezi'nin Anthropic bağlantısıyla yapılıyor ("is not a valid model ID" hatası düzeldi). Yalnız Claude anahtarı olan kurulumda da asistan açılır; sistem kontrolü Claude modelini doğru gösterir.

## 0.3.3
- Görev ve toplantı detayında bölümler katlanır: Açıklama, Alt görevler, Gündem, Kararlar, Ekler (başlığa tıklayınca).
- Özellik/özel alan 4'ten fazlaysa ilk 4'ü görünür, gerisi "N alan daha" ile açılır.
- Toplantı (ve gündem/karar maddeleri) modal değil tam sayfa görünümünde açılır; arka plan karartılmaz.
- Kararlar ve alt görevler aynı ortak tabloyu kullanır: sütunları göster/gizle her zaman açık, sütun genişliği başlık kenarından sürüklenir (çift tık sıfırlar).
- Alt görev, gündem ya da karar içindeyken üstte belirgin "Üst göreve dön" / "Toplantıya dön" butonu.
- Anında kayıt: kişi, tarih, öncelik, etiket, durum, alan değişiklikleri hemen ekranda görünür, kaydedilene kadar hafif soluk durur, hata olursa geri alınır. Listede kaydedilen satır da soluk görünür.
- Ekran tercihleri (sütunlar, genişlikler, katlanan bölümler, toplantı bölümleri) artık hesapta saklanır, her cihazda aynı (`POST /me` `prefs`, kullanıcı meta `flxg_prefs`).

## 0.3.2
- Doküman ortak düzenleme: sayfayı açan kişi diğerlerinde 1–2 sn içinde görünür (açılışta hemen "burada" kaydı; önceden yalnızken 15 sn'ye kadar sürebiliyordu).
- "Son düzenleme" başkası yazınca onun adını gösterir; "Kaydedildi" 2,5 sn sonra kaybolur (başkası yazarken "ben yazıyorum" gibi görünmüyor).
- Değişen blokta isim etiketi kaldırıldı (ikinci imleç gibi görünüyordu); isim yalnız imleçte.
- Uyarlamalı yoklama: biri yazarken 1,5 sn, birlikte ama sessizken 4 sn, yalnızken 4–8 sn, 5 dk hareketsizlikte 15 sn, gizli sekmede 20 sn (varlık kaydı düşmesin).

## 0.3.1
- Doküman: yeşil odak çerçevesi kaldırıldı; sayfa Word gibi beyaz kağıt, dışı hafif farklı zemin (koyu temada da). Görev açıklamasında da yeşil odak halkası yok.

## 0.3.0
- Dokümanlar: projede/klasörde çok sayfalı belge (`#/doc/<id>/p/<sayfa>`). Solda aç/kapa sayfa ağacı (alt sayfa, sürükle-bırak, yeniden adlandır, sil), Word benzeri editör: başlıklar, kalın/italik/altı-üstü çizili, listeler, kontrol listesi, alıntı, kod, hizalama, bağlantı, görsel (yapıştır/sürükle, boyut), dosya, tablo (satır/sütun ekle-sil), ayırıcı. `/` blok menüsü, `#` ile görev bağlantısı (güncel ad ve tamamlandı durumu gösterilir, tıklayınca görev açılır). Yazdır / PDF.
- Ortak düzenleme: içerik bloklar halinde saklanır, yalnız değişen bloklar gönderilir (aynı blokta son yazan kazanır). Hafif yoklama: başkası varken 2 sn, yalnızken 6–15 sn, sekme gizliyken durur, hata/429'da geri çekilme. Diğer kişilerin imleci ve seçimi kendi rengi ve adıyla görünür, değiştirdikleri blok renkli kenar ve isimle işaretlenir. Üst çubukta sayfadaki kişiler.
- Toplantı listesi: liste oluştururken şablon (Görev listesi / Toplantı listesi), liste ayarlarından tür değiştirilebilir. Aynı görev altyapısı; durumlar Planlandı/Yapıldı/İptal, sütunlar Tarih/Katılımcılar/Etiketler.
- Toplantı ekranı: tarih (saatli), katılımcılar, etiketler, özel alanlar, açıklama, gündem (sade liste, tıklayınca not + mini önizlemeli görsel/dosya, görüşüldü işareti, sürükle-sırala), kararlar (görev gibi sorumlu, son tarih, etiket; sütunlar seçilebilir), ekler, yorumlar. "…" ile özelleştirme: bölümleri göster/gizle (tarayıcıda saklanır), özel alan ve durum düzenleme. PDF rapor (yazdır / PDF olarak kaydet).
- Gündem maddeleri ve kararlar görünümlerde ayrı satır olmaz; kararlar Benim görevlerim'de görünür. Toplantı satırında gündem/karar sayısı.
- Kenar menüde "+ Ekle": görev listesi, toplantı listesi, doküman.
- DB şeması 3 (docs, doc_pages, doc_blocks, doc_revs, doc_presence tabloları; lists/tasks `kind`, files `doc_id`).

## 0.2.2
- Liste: görev adının yanındaki ikonlar (alt görev, yorum, dosya, açıklama) yan yana 2'li, en fazla 2 satır.

## 0.2.1
- Liste: alt görevler sürükle-bırak (başka görevin altına, üst seviyeye veya araya; kendi alt ağacına bırakılamaz). Sürüklenen görevin alt görevleri onunla gider.
- Sürüklenen satırın görünümü bozulmuyor (sütun düzeni ve girinti korunur).
- Alanlar > "Metni sar": satırlarda metin en fazla 3 satır, fazlası … (görünüm config `wrap`).
- Sütunlar artık ekrana sığdırılmaz; liste yatay kayar, görev adı sütunu solda sabit, üstüne gelen sütunlar için yumuşak geçiş.

## 0.2.0
- Liste: sütun genişliği (kenardan sürükle, çift tık sıfırlar) ve sütun sırası (başlığı sürükle); görünüme kaydedilir (`widths`, `fields`).
- Alt görevler listede üst görevin altında iç içe; görev bazında aç/kapat (tarayıcıda hatırlanır), görünüm ayarı `subtasks` = open|closed|off, `subFilter` filtreyi alt görevlere uygular (uymayan üst görev soluk bağlam olarak).
- Satırdan alt görev ekleme. Alt görev kademe sınırı proje ayarı `subtask_depth` (1–10, varsayılan 3); sunucu da denetler (`flxg_check_parent`).
- Görev detayı: alt görevler mini tablo (sütun seçimi kullanıcıya özel), iç içe alt ağaç (`subtree`, `depth`, `depthLimit`).
- Görev detayı ortada modal, kenar boşluklu; başlık/durum/Tamamla sabit; yorum/etkinlik sütunu kapatılabilir (tercih saklanır); WP menüsü ve üst çubuk da kararır (Flux Admin uygulama modu).
- Kenar menü genişliği ayarlanabilir ve gizlenebilir.
- Benim görevlerim: üst görevi kapsamda olmayan alt görevler üst satır olarak görünür.
- DB şeması 2.

## 0.1.5
- Bildirim e-postaları Flux Mail şablonları (flx-tasks/assigned, mention, due), yoksa wp_mail. Ortak FluxWP menü logosu.

## 0.1.4
- Sistem kontrolü (`flx_system_checks`).
