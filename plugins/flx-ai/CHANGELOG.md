# Flux AI — Değişiklikler

## 0.13.0
- **Çalışma alanı** (Codex benzeri): her sohbetin ve zamanlanmış görevin kendi klasörü var (`wp-content/flx-ai/chats/<id>`, `tasks/<id>-<ad>`). Tüm dosya işlemleri Flux Files üzerinden: sürüm, çöp kutusu ve işlem kaydı oradan gelir. Klasörler Flux Files'ta da görünür.
- Sohbet penceresinin sağında dosya paneli: **Dosyalar** (ağaç + dosya görüntüleme), **Değişiklikler** (mini git) ve **Ayarlar**. Yeni **Flux AI & MCP › Sohbet** sayfası sohbeti tam ekran açar; panelde tam ekran düğmesi.
- **Commit**: asistanın kendi klasörüne bir turda yazdıkları tek commit olur; hangi dosyada nerenin değiştiği, ne eklenip silindiği satır satır fark olarak görünür. Sohbette ilgili mesajın altında "Farkı gör" bağlantısı.
- **Değişiklik isteği (PR)**: kendi klasörü dışındaki dosyalara (izin verilen klasörler) yazmak dosyayı değiştirmez, değişiklik isteğine eklenir. Dosya dosya ya da hep birlikte Birleştir / Reddet; birleştirmeden önce dosya bu arada değiştiyse uygulanmaz. Sonuç asistana bildirilir. Onay bekleyenler ekranında ve menü sayısında da görünür; görev çalışmasında açılan istek görevi "Onay bekliyor" yapar ve bildirimi tetikler.
- **Skill'ler dosya oldu**: `.skills/<ad>/SKILL.md`. Eski tablodaki skill'ler kurulumda dosyaya taşınır, tablo kaldırılır.
- **Sert güvenlik kuralları** (PHP'de, model aşamaz): asistan varsayılan olarak yalnız kendi klasörünü görür; başka sohbet ve görevlerin klasörleri her zaman kapalı, `.skills` yalnız okunur (değişiklik yalnız skill araçlarıyla). Yol temizleme ve gerçek yol (symlink) kontrolü Flux Files'tan; wp-config, .env, .sql gibi hassas dosyalar ve WordPress çekirdeği hiçbir zaman açılmaz. Yazma başına 1 MB, tur ya da istek başına 50 dosya sınırı. Kendi klasörü dışındaki her değişiklik onaylı değişiklik isteği. Dışarıdan MCP bağlantısının dosya erişimi değişmedi.
- Görev formunda **Dosyalar** satırı: "Yalnız kendi klasörü ve aşağıdaki klasörler" ya da "Sitenin tüm dosyalarını okuyabilir" + izin verilen klasörler. Sohbette aynı ayar dosya panelinin Ayarlar sekmesinde. Görev araçlarında `access` ve `folders`.

## 0.12.0
- **Skill'ler** (Flux AI & MCP › Skill'ler): adlı, sürümlü talimat paketleri (ad, açıklama, Markdown talimat, önerilen araçlar, örnek çıktı). Hazır gelenler: Günlük brifing, Site sağlığı incelemesi; eklentiler `flxai_skills` filtresiyle kendi skill'lerini getirir. Hazır skill düzenlenince sitede kopyası tutulur, "Varsayılana dön" ile aslı gelir.
- Asistan uygun skill'i kendisi seçer (`flxai_skill_get` ile okur); sohbette mesajı `/ad` ile başlatarak doğrudan çağrılır. Sohbetten oluşturulur ve değiştirilir: `flxai_skill_list`, `flxai_skill_get`, `flxai_skill_save`, `flxai_skill_delete` (sohbet + MCP; değiştirenler onay kartı ister).
- SKILL.md dışa/içe aktarma (dosya yükle ya da yapıştır).
- Görevler bir skill'e bağlanır: talimat her çalışmada skill'in son haliyle birleşir; skill düzenlenince ona bağlı tüm görevler yeni hali kullanır.
- **Görev bildirimleri**: "Yalnız önemli bir şey olunca" (asistan karar verir, ya da işlem onay bekliyor / hata), "Her çalışmadan sonra" ya da "Hiçbir zaman". Kanallar: yönetim paneli (menüde rozet, listede "Yeni rapor"), e-posta (Flux Mail şablonu "AI görev raporu", yoksa wp_mail), WhatsApp (Flux Mail). Geçmişte hangi kanaldan bildirildiği görünür.
- **Onay bekleyenler** ekranı: tüm sohbet ve görevlerin onay bekleyen işlemleri tek listede. Onayla, Reddet ya da girdiyi (JSON) düzenleyip onayla; sohbetin son işlemi karara bağlanınca asistan kaldığı yerden devam eder. Menüde sayı rozeti.
- Görev sohbetinde onay verilince görevin "Onay bekliyor" durumu kalkar.
- **Genel bakışta sohbet kutusu**: sayfanın üstünde AI sohbetiyle aynı yazma alanı (dosya ekle, sesle yaz, gönder) ve hızlı başlangıç düğmeleri (skill'ler, Görev zamanla, Yeni skill). Gönderince sağdaki sohbet paneli yeni sohbetle açılır, konuşma orada sürer.
- **Sohbette dosya eki**: görsel (jpg, png, gif, webp), PDF ve metin dosyaları (txt, md, csv, tsv, json, xml, html, log); ataş düğmesi, yapıştır ya da sürükle bırak. En fazla 5 dosya, dosya başı 10 MB. Dosyalar yalnız sahibine açık klasörde durur, sohbet silinince silinir. Modele yalnız son iki ekli mesajın dosyaları tam gider (maliyet), öncekiler not olarak kalır.
- **Sesle yazma**: tarayıcı destekliyorsa mikrofon düğmesi konuşmayı yazıya çevirir (sohbet paneli ve Genel bakış).

## 0.11.0
- **Zamanlanmış görevler** (Flux AI & MCP › Zamanlanmış görevler): asistan belirlenen zamanda kendiliğinden çalışır. Her gün, hafta içi, haftanın seçili günleri, ayın günü, birkaç saatte bir ya da bir kez. Örnek: her sabah 9'da günlük YZ brifingi, her pazartesi bağışçı durumu, 6 saatte bir site performansı.
- Görevler sohbetten kurulur ve değiştirilir: "her sabah 9'da bana brifing ver", "brifingimi 8.30'a al". Yeni araçlar `flxai_task_list`, `flxai_task_create`, `flxai_task_update`, `flxai_task_delete`, `flxai_task_run` sohbette ve MCP'de; değiştirenler onay kartı ister.
- Görev, sahibinin yetkileri ve aynı araçlarla çalışır; raporu görevin kendi sohbetine yazılır (mesajda "Zamanlanmış görev" etiketi). Değişiklik yapan işlemler onay bekler; önceki çalışmanın onayı beklerken yeni çalışma atlanır, taslak kaybolmaz.
- Listede sonraki/son çalışma, son durum, kısa sonuç özeti ve "Raporu aç"; Şimdi çalıştır, Duraklat/Sürdür, Düzenle, Sil (ortak onay penceresi). Tüm görevleri tek düğmeyle kapatma.
- Tetikleme: WP-Cron (5 dk; Docker worker'da gerçek cron) ve cron gelmezse sitedeki herhangi bir istekten sonra kısa arka plan isteği. Bir istek en fazla ~45 sn çalışır; uzun görev sonraki istekte sürer. Sistem kontrolünde "Zamanlanmış AI görevleri" satırı (15 dk'dan fazla gecikmede uyarı).
- **Çalışma geçmişi**: her görevin "Geçmiş" sayfası, AI sohbetindeki gibi: talimat, çağrılan araçlar (girdi/sonuç), düşünme ve yanıt; durum, süre, maliyet, araç sayısı. Son 50 çalışma saklanır. "Raporu aç" buraya gelir, "AI sohbetinde devam et" ile sohbete geçilir.
- `?flxai_chat=ID` adresi ortak sohbette o sohbeti açar.

## 0.10.9
- Tarayıcının onay/uyarı/giriş pencereleri (confirm, alert, prompt) yerine ortak FluxWP modalı (flx-brand flxDialog): yeni tarz, telefonda alttan açılır, koyu modla uyumlu. Silme gibi geri alınamaz işlemlerde kırmızı onay düğmesi.

## 0.10.8
- Kayıtlı API anahtarları bir sonraki kayıtta ortak mühürle saklanır. Eski kayıtlar açılmaya devam eder. WordPress tuzları değişirse anahtarları yeniden girin.

## 0.10.7
- OAuth, API ve akış yanıtları ortak önbelleksiz başlıkları kullanır. Akışın tampon kapatması durur.

## 0.10.6
- MCP OAuth istek sınırı (dakikada 120, kayıt saatte 20) ortak sayacı kullanır. Adres, güvenilen vekilin ilettiği ziyaretçi adresidir; herkes tek kovada birleşmez.

## 0.10.5
- Kullanım CSV'si ortak dışa aktarmayı kullanır. Sütunlar, virgül ayırıcı ve Excel BOM aynı kalır.

## 0.10.4
- Bütçe mesajında ("Spent: %1$s / Limit: %2$s") boşluklar geri geldi.
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 0.10.3
- MCP bağlantısı `edit_posts` yerine `flxai_mcp` yetkisi ister (rota, OAuth onay/belirteç/yenileme). `edit_posts` olan roller ve WordPress yöneticisi bu yetkiyi varsayılan taşır; Flux Roles'tan başka rollere de verilir. Araçlar kapalı liste, anahtar kapsamı, aracın `cap` değeri (yoksa `edit_posts`) ve `flx_mcp_tool_allowed` filtresine göre süzülür. Yetkisi kalkan kullanıcının anahtarı MCP ekranında uyarılır.

## 0.10.2
- Kullanım CSV'sinde formül olarak açılacak hücrelerin başına tek tırnak konur. Gerçek sayılar, eksi tutarlar dahil, sayı kalır.

## 0.10.1
- MCP anahtarı artık yalnız MCP rotasının izin kontrolünde kullanıcı olur (`/wp-json/flx-mcp/v1/mcp` ve aynı sunucuya düşen kısa adres). Adresin başka bir yerine ad alanı metni eklemek o kullanıcıyı yapmaz; MCP kapalıysa ve araç kapsamı da burada uygulanır.
- Sorgu dizesindeki anahtar (`?key=`) kabul edilmez. Anahtar yalnız `Authorization: Bearer` veya `X-MCP-Key` başlığından okunur. Apache/LiteSpeed'de `REDIRECT_HTTP_AUTHORIZATION` okunmaya devam eder.

## 0.10.0
- **Kısa MCP adresi `site.com/mcp`.** Aynı sunucu `/wp-json/flx-mcp/v1/mcp` adresinde de çalışır; yeniden yazma kuralı yok. Sitede `mcp` adlı sayfa varsa `flx_mcp_short_path` filtresiyle değiştirilir ya da `''` ile kapatılır.
- **MCP için OAuth 2.1 girişi Flux AI & MCP'de** (Flux App gerekmez). claude.ai, Claude Desktop, ChatGPT, Cursor, VS Code yalnız adresle bağlanır: 401 + `WWW-Authenticate`, `/.well-known/oauth-protected-resource` (RFC 9728), `/.well-known/oauth-authorization-server` (RFC 8414), dinamik istemci kaydı (RFC 7591), `site.com/mcp/authorize` onay ekranı (WordPress girişi, PKCE S256 zorunlu), belirteç / yenileme (dönüşümlü; kullanılmış yenileme belirteci tekrar gelirse bağlantı kapanır) ve iptal (RFC 7009) uçları `/wp-json/flx-mcp/v1/oauth/*`.
- Onaylanan bağlantılar Flux AI & MCP › MCP'deki listede "OAuth" etiketiyle görünür ve oradan iptal edilir. Erişim belirteci 1 saat, kullanılmayan bağlantı 90 gün (filtreler `flx_mcp_oauth_access_ttl`, `flx_mcp_oauth_refresh_ttl`). Ekranda "OAuth ile giriş" aç/kapa (MCP açıkken varsayılan açık).
- Anahtarla bağlanma aynen çalışır (Bearer, `X-MCP-Key`, `?key=`, Uygulama Parolası); kısa adreste de.
- Flux App kuruluysa `.well-known` keşfine MCP için Flux AI yanıt verir; Flux App'in kendi uçları ve uygulama girişi değişmez, Flux App belirteçleri MCP'de çalışmaya devam eder.

## 0.9.4
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Preserve singular/plural message boundaries and printf arguments in English source IDs and all native language packs; prevent count labels from breaking populated screens.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.
- Repair the registered-tool count plural message on the MCP server screen.


## 0.9.3
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 0.9.2
- Kayıt saklama süresi varsayılan olarak **sınırsız**: Ayarlar › Kayıtlar'da kullanım kayıtları ve içerik kaydı (giden/gelen içerik, kısaltılmış ve maskeli) için gün alanı boş bırakılırsa hiçbir kayıt silinmez. Gün girilirse daha eski kayıtlar günde bir kez silinir (1–3650 gün). Uygulama ayarlarında da 0 = sınırsız; içerik saklama süresi uygulamadan da ayarlanabilir.

## 0.9.1
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.


## 0.9.0
- Uygulama ayarları ve kayıt listesi (`flx-ai/v1/log`, istek gövdesi dönmez). Anahtarlar write-only. Sohbet yerelde sonra açılır.

## 0.8.1
- Eklentinin görünen adı **Flux AI & MCP** (eklenti listesi, sol menü, sistem kontrolü, metinlerdeki "Flux AI & MCP › …" adresleri). Klasör/slug (flx-ai), fonksiyonlar ve ayarlar aynı.

## 0.8.0
- **Flux AI SDK** (`includes/flx-ai-sdk`, kaynak `tools/ai-sdk`): eklentilerin (FluxWP ya da üçüncü taraf) AI özelliklerini kolayca bağladığı sürümlü kütüphane. `flx_ai_plugin()`, `flx_ai_tool()` (ada eklenti öneki kendiliğinden eklenir), `flx_ai_settings()` / `flx_ai_feature()`, `flx_ai_get()`, `flx_ai_chat()` / `flx_ai_complete()`. Birden çok kopya varsa yalnız en yeni sürüm yüklenir; Flux AI yoksa hata vermez.
- **Bağlı sistemler › AI ayarları**: SDK ile tanımlanan ayarlar ve özellik anahtarları, eklentiye özel model, aç/kapa ve eklentinin araç sayısı tek ekranda.
- **Araçlar ekranı yenilendi**: arama, eklenti / tür (okur, değiştirir) / durum (açık, kapalı, çakışma, öneksiz) süzgeçleri, eklenti bazlı sayı çipleri (açık/toplam), toplu seçip aç/kapat, "Nerede" sütunu (MCP · Sohbet), Kaynak sütununda üstte eklenti adı, altta id. Süzgeç adres çubuğunda kalır (`#src=flx-seo`). Telefonda kart düzeni.
- **Ad çakışması**: aynı araç adını iki eklenti kaydederse ilk kaydeden açık kalır, diğeri üzerine yazamaz ve kapalı bekler; ekranda iki satır ve "Bunu kullan" ile seçim.
- **Eski adlar (alias)**: araçlar `aliases` ile yeniden adlandırılabilir; MCP çağrıları, anahtar kapsamları ve kapalı araç listesi eski adla da çalışır. Flux SEO, Flux Setup ve Flux TV Board araçları önek aldı.
- Araçlar ekranında kapatılan araç artık MCP sunucusunda da kapalı (önceden yalnız sohbette).

## 0.7.0
- **Ortak MCP sunucusu Flux AI'ya taşındı** (önceden Flux SEO içindeydi). Yeni ekran **Flux AI › MCP**: sunucuyu aç/kapa, kullanıcıya özel anahtarlar (araçlarla sınırlanabilir), Claude Desktop / Claude Code / claude.ai bağlantı örnekleri ve son işlemler. Adres aynı: `/wp-json/flx-mcp/v1/mcp`.
- Eklentiler araçlarını yeni `flx_mcp_tools` filtresiyle kaydeder; eski `flxseo_mcp_tools` aynen çalışır (CRM, Mail, Speed, Analytics, Social, Setup, Lang, TV Board, QR Menu değişmeden gelir). İstemler `flx_mcp_prompts`, kaynaklar `flx_mcp_resources` (eski `flxseo_mcp_resources` de), sunucu talimatları `flx_mcp_instructions`.
- Flux SEO'daki eski anahtarlar ve aç/kapa ayarı ilk açılışta kendiliğinden devralınır; bağlı asistanlar yeniden bağlanmadan çalışmaya devam eder.
- Araçlar ekranında "Kaynak" sütunu aracı kaydeden eklentiyi gösterir (önceden hepsi Flux SEO görünüyordu).
- Eski Flux SEO (1.4.x) kuruluysa sunucuyu o çalıştırır, Flux AI uyarı gösterir; çakışma ya da hata olmaz.

## 0.6.9
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 0.6.8
- Sohbet paneli ön yüzde sitenin Açık / Koyu / Sistem seçicisini izler (sistem temasına değil). Sayfa geçişinde önceki sayfanın görüntüsü güncel temayla çizilir (eski tema bir an görünmez).

## 0.6.7
- "AI'ya sor" düğmesinin sağ boşluğu `--flxai-edge` CSS değişkeniyle ayarlanabilir (Flux Admin telefon yatayda sağdaki menünün soluna alır).

## 0.6.6
- Düzeltme: telefonda AI sohbeti açıkken yukarı aşağı kaydırınca arkadaki site de kayıyordu (özellikle iOS Safari, boş sohbette). Panel ekranı kapladığında sayfa yerinde sabitlenir, kapanınca kaldığı yere döner; panelin kendi kaydırması (mesajlar, sohbet listesi, yazı alanı, tablolar) aynen çalışır.

## 0.6.5
- Araçlar ve Bağlı sistemler tabloları telefonda kart düzeninde; açıklamalar kesilmiyor, sayı sütunları gizli.

## 0.6.4
- Flux Admin kuruluysa menü logosunu Flux Admin verir: boyutu diğer paketlerle aynı, Degrade/Pastel seçimine uyar.

## 0.6.3
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 0.6.2
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 0.6.1
- Vurgu rengi Flux Admin'den (varsayılan olarak tema rengi; FluxWP'de indigo), sabit yeşil kaldırıldı.

## 0.6.0
- Görsel ve video üretim altyapısı: diğer eklentiler (Flux Studio) `flxai_media_submit` / `flxai_media_status` / `flxai_media_cancel` ile görsel ve video üretir (API.md). Sağlayıcılar fal.ai, EvoLink, OpenAI, Google Gemini, OpenRouter; modeller GPT Image 2.5, Nano Banana 2.1/Pro, Seedance 2.5/2.0, MiniMax H3.
- Ayarlar › Görsel ve video: sağlayıcı anahtarları (şifreli), varsayılan görsel/video modeli, model › sağlayıcı sırası.
- Sağlayıcı istekten önce seçilir; ücretlendirilmiş olabilecek istek başka sağlayıcıya tekrar gönderilmez. Tahmini tutar istekten önce harcama sınırına işlenir, gerçek tutar gelince düzeltilir. Her istek Kayıtlar'da.
- Alan düzenleme (maske): maske dışındaki pikseller özgün görselden geri yazılır.
- Çıktılar uploads/flxai-media altına indirilir. Cron yok; kuyruktaki işler tarayıcı sordukça ilerler (`GET flx-ai/v1/media/{job}`).

## 0.5.6
- Kayıtlar listesi Flux Admin 1.2.0+ ortak liste çubuğunu kullanır: dönem + tür görünür, sistem ve "Yalnız hatalar" Filtreler panelinde (telefonda derli toplu); sayfalama numaralı, Sayfa başı 10/20/50/100 (varsayılan 50, kullanıcıya göre hatırlanır). Flux Admin yoksa eski görünüm.

## 0.5.5
- Sağ alttaki AI düğmesi masaüstünde de mobilde de yalnız simge; sağ kenara yapışık yarım hap. AI çalışırken simge döner; metin akmaz, durdur düğmesi panelde. Tıklayınca panel açılır.
- Düğme artık modalların (z-index 100000+) altında kalır, üstlerine çıkmaz.

## 0.5.4
- Düzeltme: sohbette harcama özeti aracı "Call to undefined function flxai_purpose_label()" hatası veriyordu (fonksiyon yalnız yönetim sayfalarında yükleniyordu). Ortak yardımcılara taşındı.

## 0.5.3
- Yazma kutusu küçüldü: tek satır, metin dikeyde ortada, gönder düğmesi orantılı; yazdıkça büyür (en çok 200 px), boşalınca küçülür.
- Kutunun herhangi bir yerine (metnin üstündeki boşluk dahil) tıklayınca yazma açılır.

## 0.5.2
- Düzeltme: sohbetteki model aramasında sonuçlar bir an görünüp "Sonuç yok." ile siliniyordu (arama kutusu sohbet listesi aramasını da tetikliyordu). Arama artık kelime kelime eşleşir ("deepseek v4.1" → deepseek/deepseek-v4.1); Enter ilk modeli seçer.

## 0.5.1
- **Akışta titreme giderildi:** sohbet gövdesi her parçada baştan kurulmuyor; yalnız değişen metin yamanıyor (mesaj animasyonları, açık ayrıntılar, görseller yerinde kalır). Kaydırma yalnız en alttayken takip eder, yukarı kaydırınca zıplamaz; yumuşak kaydırma kapatıldı.
- **Sayfa geçişinde panel sabit:** ayrılırken panelin görüntüsü ve sohbet durumu sekmeye (sessionStorage) yazılır; yeni sayfada ilk boyamada aynen çizilir (mesajlar, kaydırma, açık/kapalı), `<link rel="expect" blocking="render">` ile ilk kare bekletilir. chat.js aynı kabı devralır, sunucudan sessizce tazeler; süren tur kaldığı yerden akar. Flux Admin'in sayfa geçiş animasyonuyla birlikte panel yeniden belirmez.

## 0.5.0
- **Canlı akış:** sohbette düşünme, araç çağrıları ve cevap metni oluştukça görünür (Claude ve OpenRouter akışı, sohbete satır satır NDJSON). Sunucu/LiteSpeed ara belleğe alırsa cevap eskisi gibi sonda gelir; istek sayısı artmaz.
- Durdur akışın ortasında da isteği keser; gelen kısım kalır.
- Düzeltme: "Invalid `signature` in `thinking` block" hatası. Önceki turların düşünme blokları modele gönderilmez; tur içinde sistem talimatındaki tarih sabit kalır.
- Onay sonrası model ayrı (akışlı) adımda çağrılır.
- Geliştirici: `flxai_chat` için `stream` ve `stop_check` seçenekleri.

## 0.4.1
- Geliştirici: `flxai_import_config( $source, $conf )` başka eklentinin anahtar ve modellerini Flux AI'ye aktarır (yalnız boş alanları doldurur, aktarılanları döner). `flxai_set_source_model( $source, $model )` eklentinin kendi ekranındaki model seçimini Flux AI'ye yazar.

## 0.4.0
- **Kapat düğmesi** çalışıyor (panel `[hidden]` iken gizlenmiyordu).
- **Baloncuk** masaüstünde hep köşede; alt bar boşluğu yalnız mobilde.
- **Canlı baloncuk:** panel kapalıyken süren sohbet baloncukta akar (başlık + 1-2 satır: düşünme, çalışan araç, cevap). Onay gerekince turuncu, bitince okunmadı noktası. Yanında küçük **Durdur** düğmesi. Tıklayınca açılır, devam eder.
- **Arka planda devam:** sayfa yenilense ya da başka sayfaya geçilse de sohbet kaldığı yerden sürer (sunucu süren adımı bitirir, `ignore_user_abort`). Tüm sekmeler kapanırsa bir sonraki sayfa açılışında devam eder.
- **Durdur** süren adımın ortasında da dinlenir; bekleyen araçlar "yapılmadı" diye kapanır.
- **Mesaj düzenle / geri dön:** kullanıcı mesajının üstünde Düzenle (mesaj ve sonrası yeniden yazılır) ve Bu noktaya geri dön (sonrası silinir). Uç: `POST flx-ai/v1/chats/{id}/rewind` (`msg`, `mode` = `edit`|`keep`).
- "Flux AI henüz ayarlanmadı" uyarısı tek sefer görünür.
- Yanaşık panel admin bar'ı ve tam genişlik sayfaları da iter, üstüne binmez.

## 0.3.1
- Logo: sol menüde Flux Admin düzeni açıkken de görünür; Güncellemeler listesinde Flux AI logosu (`flxu_package_icons`).

## 0.3.0
- **Bağlı sistemler** (yeni sayfa): Flux AI'yı kullanan eklentiler (sohbet, Çağrı Merkezi, Görev ve kayıt bırakan diğerleri). Her birinin durumu, modeli, 30 günlük istek/token/maliyet/hata sayısı ve son kullanımı görünür. Aç/kapat buradan yapılır: kapalı sistemin istekleri `flxai_source_off` ile reddedilir (`flxai_is_account_error` true döner, toplu işler durur). Eklentinin kendi ayarları değişmez. Sohbet kapatılırsa "AI'ya sor" düğmesi gizlenir. Yeni fonksiyon `flxai_source_enabled()`, filtre `flxai_systems`.
- **Kayıtlar** artık ayrı menü sayfası. Sütunlar: nerede (sistem · amaç · sohbet no), kim, model/araç, token, süre, maliyet. Süzgeçler: 24 saat / 7 / 30 / 90 gün, tür, sistem, yalnız hatalar. 100'lük sayfalama.
- **İçerik kaydı:** her isteğin giden son mesajları ve gelen cevap kısaltılarak saklanır (varsayılan 30 gün; Ayarlar › Kayıtlar'dan kapatılabilir). E-posta, telefon/kart/TC (10+ hane), IBAN ve API anahtarları maskelenir (filtre `flxai_log_mask`). Kayıtlar'da "İçerik" düğmesiyle açılır.
- Flux Admin alt bar/palet simgesi.

## 0.2.1
- Ayarlar › Görseller: **Test et** düğmesi. Formdaki Gotenberg adresi, kullanıcı ve şifreyle (kaydetmeden) örnek bir görseli PNG'ye çevirir, önizlemeyi gösterir. Şifre boşsa kayıtlı şifre kullanılır.
- Gotenberg 401/403 cevabında "kullanıcı adı veya şifre kabul edilmedi" hatası.

## 0.2.0
- Sohbette görseller: model cevabındaki ```widget (HTML + inline CSS ya da SVG) ve ```mermaid blokları sohbetin içinde çizilir. Her modelle çalışır (kurallar sistem talimatında).
- Güvenlik: her görsel `sandbox="allow-scripts"` (allow-same-origin yok) iframe'de, CSP `default-src 'none'` ile; dış istek, form, bağlantı, sayfa DOM'una erişim yok. Yükseklik yalnız o iframe'den gelen sayısal mesajla (üst sınır 2400 px) ayarlanır.
- Tema renkleri (--bg, --text, --accent, --border …) iframe'e aktarılır; açık/koyu tema değişince görseller yeniden çizilir.
- Blok kapanmadan "Görsel hazırlanıyor…" gösterilir; çizilemeyen görsel düz kod olarak görünür, sohbet bozulmaz.
- Mermaid 12.2.0 pakete dahil (yalnız şema olan sohbette yüklenir, securityLevel strict).
- WhatsApp/e-posta için `flxai_render_widget()` PNG çevirici: Gotenberg (HTML + SVG, Docker) ya da Imagick (yalnız SVG). `flxai_widget_rules()`, `flxai_extract_widgets()`. Ayarlar › Görseller, sistem kontrolünde `flxai.render`.

## 0.1.0
- İlk sürüm: tüm FluxWP eklentilerinin ortak yapay zekâ katmanı.
- Anahtarlar tek yerde (OpenRouter, Anthropic, workspace ID); şifreli saklanır, panelde/REST'te yalnız son 4 hane görünür.
- Model kimlikleri: `claude:<id>` doğrudan Anthropic'e (önek API'ye gönderilmez; Görev'deki "claude:claude-haiku-5-5 is not a valid model ID" hatasının kalıcı çözümü), `vendor/model` OpenRouter'a.
- Claude: prompt önbelleği (5 dk / 1 saat), adaptive thinking özeti, effort, Opus/Sonnet 5.x ve Fable 5.1 için sunucu tarafı yedek model.
- Harcama sınırı: günlük (00:00), haftalık (Pazartesi), aylık (ayın 1'i) yenilenir; dolunca istekleri durdurur ya da yalnız uyarır; eşikte ve dolunca e-posta (Flux Mail varsa onunla).
- Raporlar: maliyet, token, önbellek tasarrufu; model/düşünme ve araç sürelerini dikey bar grafiklerle; eklenti/model/amaç/araç dökümü; kayıtlar.
- Faturalandırma: aylık döküm, sağlayıcıya göre, CSV, OpenRouter kalan kredi.
- Ortak sohbet: yönetim panelinde ve sitede (giriş yapmış, yetkili kullanıcıya). Yüzen pencere ya da sağa yerleşik (sayfayı iter), boyutlandırılır, sayfa geçişinde yerinde kalır. Çoklu sohbet, otomatik başlık, yeniden adlandırma, arşiv, silme, arama. Düşünme adımları ve araç çağrıları görünür. Değişiklik yapan her araç (Flux SEO & MCP dahil) çalışmadan önce onay ister.
- Hostinger uyumu: sohbet turu ≤45 sn'lik adımlarla ilerler, SSE yok, REST yanıtları önbelleğe alınmaz.
- Sistem kontrolü satırları (`flxai.keys`, `flxai.model`, `flxai.limit`, `flxai.errors`, `flxai.mcp`) ve sınır düzeltmeleri.
- TR + EN.
