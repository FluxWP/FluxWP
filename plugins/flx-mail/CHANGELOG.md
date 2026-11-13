# Changelog

## 1.17.0 — 2026-11-12
- WhatsApp ban koruması (Evolution API / Flux WPAPI, yalnız kuyruktaki toplu gönderim): mesajlar arası rastgele bekleme (varsayılan 8–20 sn), her 25 mesajda 10 dk mola, saatlik (60) ve günlük (250) üst sınır, yeni numarada ısınma (günlük sınır 20'den başlar, her gün +20), sessiz saatler (21:00–09:00), göndermeden önce numaranın WhatsApp'ı var mı kontrolü (yoksa atlanır), art arda 5 hatada 30 dk durma + sistem kontrolü uyarısı, "yazıyor…" süresi mesaj uzunluğuna göre. Ayarlar Bağlantılar › düzenle › "Ban koruması". Gönder ekranında bekleyen gönderimin nedeni ve devam saati görünür.
- Metin çeşitlemesi: `{Merhaba|Selam|İyi günler}` her alıcıda rastgele biri.
- IPTAL: tanıtım WhatsApp mesajlarının altına "Bu tarz mesajlar istemiyorsanız IPTAL yazın." eklenir (SMS'te isteğe bağlı). Kişi yalnız IPTAL yazarsa o kanaldan tanıtım mesajı gitmez, ortak kişi kaydında kanal izni kapanır, bir kez onay mesajı gider; BAŞLA geri alır. Bilgilendirme mesajları ve kişinin kendi yazdığına verilen cevaplar gider. Gelen mesaj Flux Chat'ten okunur. Ayarlar › IPTAL: kelimeler, metinler, liste, elle ekle/kaldır.
- Gönder ekranında "Tanıtım mesajı" anahtarı (kapalıysa bilgilendirme) ve WhatsApp için resim/video/dosya (ortam kütüphanesinden). Önizleme çıkış satırını ve çeşitlemeyi gösterir.
- Kampanya API'si: `media`, `file_name`, `kind` (`marketing` | `info`), `ref`; `flxi_send_message( …, array( 'kind' => 'marketing' ) )`; özellik sorgusu `flxi_campaign_features`. Resim/video tek mesaj (metin altyazı), PDF önceki gibi ayrı belge.
- Flux Mail'in gönderdiği WhatsApp mesajları Flux Chat'te asistanı susturmaz (`flxc_external_source` → `flx-mail`); echo webhook'u yanıttan önce gelse bile: göndermeden önce numara + metin işareti bırakılır, `flxi_sent_pending()`.
- MCP: `flxi_optouts`, `flxi_wa_guard`.

## 1.16.0 — 2026-11-12
- WhatsApp gruplarına mesaj: `flxi_send_message( 'whatsapp', '120363…@g.us', … )` grup kimliğini telefon gibi rakama çevirip bozmuyor, Evolution API / Flux WPAPI'ye olduğu gibi gönderiyor (metin, belge, resim, ses). Zamanlanmış görev bildirimleri artık gruba da gidebilir.
- Yeni `flxi_whatsapp_groups( $conn_id )`: numaranın üye olduğu gruplar (kimlik, ad, üye sayısı), 10 dk önbellek; REST `GET flxi/v1/wa/{id}/groups`.
- Cloud API gruplara gönderemez: açık hata ("Grup mesajları için Evolution API ya da Flux WPAPI bağlantısı seçin"). Bağlantı seçilmemişse ve varsayılan Cloud API ise gruba gidebilen ilk bağlantı kullanılır.
- Kara liste kontrolü grup kimliğine uygulanmıyor.

## 1.15.1
- Tarayıcının onay/uyarı/giriş pencereleri (confirm, alert, prompt) yerine ortak FluxWP modalı (flx-brand flxDialog): yeni tarz, telefonda alttan açılır, koyu modla uyumlu. Silme gibi geri alınamaz işlemlerde kırmızı onay düğmesi.

## 1.15.0
- New recipient source "All people, who allowed this channel": the shared FluxWP people table (one record per person across Flux CRM, Certificates, Donate, Commerce). Flux CRM is not needed. People without consent for the channel are left out and counted under "no consent" in the recipient breakdown.
- "Flux Certificates contacts" counts and sends only to people Flux Certificates uses (the people table is shared since Certificates 2.0.0).

## 1.14.0 — 2026-11-11
- Gönder ekranında WhatsApp ve SMS önizlemesi: mesaj telefon ekranındaki gibi görünür, değişkenler örnek kişiyle doldurulur. SMS'te karakter ve SMS adedi Netgsm kurallarına göre sayılır (Türkçe karakter açıkken 155 / parça başına 146, kapalıyken 160 / 153; ç ğ ı ş Ğ İ Ş iki karakter), toplam SMS kredisi, desteklenmeyen karakterler (emoji) ve İYS filtresi gösterilir. WhatsApp'ta *kalın* / _italik_ biçimli görünür, 4.096 karakter sınırı ve Cloud API'nin 24 saat kuralı hatırlatılır.
- Alıcı listeleri hizalı iki sütun. Seçilen listeden kişi gelmiyorsa nedeni altında yazar: kaç kişinin e-posta adresi / telefonu yok, kaçı bu kanala izin vermemiş, kaçı zaten listede. Yeni filtre `flxi_recipient_source_info`.
- Zamanlanmış gönderim alanının yanında sitenin şu anki saati ve saat dilimi (saniye saniye ilerler).
- E-postaların üstündeki logo artık sitenin kendi logosu: Flux Mail ayarındaki logo, yoksa kurum profili / Flux SEO / temanın Site Logosu, o da yoksa site simgesi ve site adı. Eklentiyle gelen FluxWP logosu kullanılmaz. Ön ayarlar `flxi_site_logos` filtresiyle PNG logo verir. WooCommerce e-posta başlığına yazılmış FluxWP logosu bir kez düzeltilir.
- E-postalar sayfası yana kaymıyor: "Gönderen" kartındaki ad ve adres sığmazsa "…" ile kesilir.

## 1.13.22
- SMTP, IMAP ve bağlantı parolaları bir sonraki kayıtta ortak mühürle saklanır. Eski kayıtlar açılır. WordPress tuzları değişirse parolaları yeniden girin.

## 1.13.21
- Telefon numaraları tüm eklentilerde aynı kuralla okunur: `+90 0532…` yazımındaki fazla sıfır atılır, ülke kodu ve baştaki sıfır olmadan yazılan 10 haneli sabit hat (212…) 90 ile tamamlanır, hane sayısı ya da alan kodu tutmayan Türkiye numarası geçersiz sayılır.

## 1.13.20
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 1.13.19 — 2026-11-10
- Store the shared operational log in one table tagged by plugin and defer a background batch when fewer than 5 seconds remain before the 45-second request deadline.

## 1.13.18 — 2026-11-10
- Prime member meta (`billing_phone`, `first_name`, `last_name`) in pages of 500 while collecting campaign recipients, so a role list does not run three meta queries per user. The list is still built in that one request. A cron queue run budgets 25 s (20 s otherwise) and will not claim a row unless the remaining time covers the transport timeout (at least 5 s, at most 30 s) plus a 5 s margin. A row left in `processing` returns to the queue after 10 minutes while attempts remain.

## 1.13.17 — 2026-11-10
- Campaign progress and the delivery queue pause while the tab is hidden and poll immediately when it is visible again. The short interval (1.5 s on the campaign screen, 5 s on the queue) stays only while the result is changing; otherwise the wait steps up to 15 s. A quota pause still waits 5 min. Errors and HTTP 429 back off exponentially up to 60 s, and a loop never overlaps its previous request.

## 1.13.16 — 2026-11-10
- Security (WhatsApp Cloud API): require App Secret on `wacloud` connections and reject webhook `POST` requests with `403` (`no_secret`) when App Secret is empty, before parsing payloads or firing `flxi_wacloud_event` / `flxi_whatsapp_event`. Show webhook status in Connections, Setup and System Check when App Secret is missing.
- Security (Google sign-in): mark accounts as verified (`flxi_social`, `flxi_email_verified`, `flxi_verified_meta`) and suppress registration/password emails only when a supported social-login plugin has recorded a Google login for the user (or via `flxi_user_is_social`), never from request parameters or Referer alone.

## 1.13.15 — 2026-11-10
- Batch Netgsm recipients in REST v2 packets, persist provider job IDs, reconcile per-recipient delivery reports and avoid resending uncertain submissions.
- Add a live delivery queue, local stopping, future Netgsm scheduling/cancellation, protected REST actions and ten native locale catalogs.
- Add a bounded shared operational journal for correlated worker, delivery and error diagnostics without message content or credentials.

## 1.13.14 — 2026-11-10
- Verify a 1,000-recipient Netgsm campaign with mocked transport; preserve encoding, consent-filter selection, deduplication and provider error backoff. Retry WhatsApp HTTP 429 and resume ready worker batches promptly.
- Share the reusable background execution guard and recover malformed abandoned leases; verify standalone and mixed bundle loading in CI.
- Retain recovery events for interrupted delivery claims and enforce their attempt limits.
- Signal due work to compatible Docker workers for lightweight fast wake; keep native scheduling for older images and ordinary WordPress.
- Run independent worker cron jobs in a bounded subprocess pool (default 3 per plugin, configurable total 9), retaining resource locks, serialized scheduling, durable watchdog recovery and ordinary WordPress fallback.

## 1.13.13 — 2026-11-10
- Prefer the detected Docker WP-CLI worker for campaign queues; retain native WordPress execution when no worker is available.
- Use owner-bound queue leases and atomic message claims; release locks and retry temporary transport failures while preserving daily quotas.

## 1.13.12
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 1.13.11
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 1.13.10
- Güvenlik (CodeQL): logo ve posta kutusu adresleri `encodeURI` ile temizlenir.

## 1.13.9
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.


## 1.13.8
- Uygulama manifesti: posta kutusu (liste, oku, yanıtla) ve gönderim kaydı. Gövde HTML içerik olarak açılır. Şablonlar ve bağlantılar web görünümünde kalır.
- Posta listesi `page` / `per_page` kabul eder. Yeni uçlar: `POST /mailbox/item/{id}/reply` (yanıtı gönderir) ve `GET /log` (sayfalı kayıt). Yetki `flxi_manage`.

## 1.13.7
- Güvenlik (CodeQL): test ekranında senaryo adları kaçışlanır; logo önizlemesi yalnız http(s) veya site içi adresi gösterir (`javascript:` vb. reddedilir).

## 1.13.6
- Metinler: MCP sunucusu artık Flux AI & MCP eklentisinde; "Flux SEO & MCP" ve "Flux AI ›" adresleri "Flux AI & MCP" oldu.

## 1.13.5
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 1.13.4
- Yeniden adlandırma hataları: şifreli ayarlar (SMTP/IMAP parolası, bağlantı anahtarları) `flxi1:` önekinin uzunluğu yanlış okunduğu için açılamıyordu, düzeltildi. Ad değişiminden önce gönderilmiş e-postalardaki listeden çıkma bağlantıları (`yti_unsub`) yeniden çalışıyor.

## 1.13.3
- Koyu modda açık nane çip/rozetler ve WhatsApp durum rozeti düzeltildi; posta kutusundaki ara metinler tema tonunda; koyu zemin logosu "Varsayılan" bağlantısı okunur.

## 1.13.2
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 1.13.1
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 1.13.0
- E-posta renkleri (ana, koyu, arka plan, başlık) boşsa tema paletinden geliyor; temanın rengi seçilirse saklanmaz. Vurgu varsayılanı turuncu. Arayüz Flux Admin rengini kullanıyor.

## 1.12.0

- Bağlantılar: numara bağlıyken "Bağlan" düğmesi gizlenir (durum değişince kendiliğinden açılır/kapanır). QR ve durum ⋯ menüsünde "Durum / yeniden bağla (QR)".
- "Test et" artık pencere açar: alıcı numara, "Bağlantıyı kontrol et" (mesajsız), senaryolar (Metin, Resim, Sesli mesaj, Belge (PDF), Cloud API'de Şablon + değişkenler), WhatsApp görünümünde canlı önizleme ve her mesajın sonucu (✓✓ / hata) balonun altında. SMS'te yalnız metin, SMS görünümüyle.
- Cloud API'de 24 saat kuralı pencerede yazar; numara bağlı değilse uyarı çıkar. Son yazılan test numarası tarayıcıda hatırlanır.
- Yeni API: `flxi_wa_send_media()` (resim / sesli mesaj / belge; Evolution, Flux WPAPI, Cloud API), `flxi_test_scenario()`. Örnek görsel, ses ve PDF eklentiyle gelir.

## 1.11.0

- Flux Speed kuruluysa İletişim'deki Sistem kontrolü sekmesi ve menüsü gizlenir; eski adres Performans › Sistem Kontrolü'ne yönlenir. Kontroller (`flx_system_checks`) aynen orada görünür. Performans yoksa ekran eskisi gibi.

## 1.10.1

- Cloud API: okundu ve "yazıyor…" istekleri Meta'nın beklediği gibi alıcısız gider; numara yalnız rakama indirgenir.

## 1.10.0

- Üçüncü WhatsApp sağlayıcısı: **WhatsApp Cloud API (Meta, resmi)**. Phone Number ID + WABA ID + kalıcı token; ek sunucu yok.
- "Kurulum" penceresi: numara adı ve kalitesi, Meta'ya girilecek webhook adresi ve doğrulama anahtarı (kopyala), son doğrulama / son olay / imzası tutmayan istek, açık 24 saat pencereleri, onaylı şablon listesi.
- Gelen webhook App Secret (X-Hub-Signature-256) ile doğrulanır; mesajlar ve tikler Evolution biçimine çevrilip `flxi_whatsapp_event` eylemiyle yayılır (medya base64 eklenir).
- Gönderim: metin, PDF/belge, şablon (`wa_template`), 24 saat penceresi dışında anlaşılır hata. Cloud API'de olmayanlar (grup, düzenleme, geçmiş içe aktarma) formda ve kurulumda yazıyor.

## 1.9.0

- WhatsApp'ta iki sağlayıcı: **Evolution API** ve **Flux WPAPI** (Hostinger'da Node uygulaması olarak çalışan köprü). Bağlantılar'da ikisi için ayrı "+" düğmesi.
- Birden fazla numara: her bağlantı bir numara; aynı sunucuya yeni numara eklerken adres ve API key yeniden girilmez ("Sunucu: Aynı sunucu").
- WP'den yönetim: "Bağlan" penceresinde QR (kendiliğinden yenilenir) ya da telefon numarasıyla kod, bağlanınca numara ve profil adı görünür. Satırda canlı durum rozeti. ⋯ menüsünde yeniden başlat, oturumu kapat, sunucudan sil.
- Flux WPAPI sunucu ayarları WP'den: dış adres, uyanık tutma, MySQL (dene + kaydet), sürüm ve son uyanık tutma bilgisi. Parola yalnız köprüye gider, WP'de saklanmaz.
- Geliştirici: `flxi_whatsapp_state()`, `flxi_is_evo_type()`, `flxi_connection_choices('whatsapp')` satırlarında `state`/`me`; REST `flxi/v1/wa/{id}/…`.
- Bağlantılar listesi telefonda düzgün diziliyor.

## 1.8.2

- Kayıtlar listesi Flux Admin 1.2.0'ın ortak liste yapısına geçti: üstte arama, altında durum + kanal, seçince kendiliğinden süzer; altta "1–20 / 160", sayfa numaraları ve sayfa başı adet (10/20/50/100, kişi başına hatırlanır). Flux Admin eskiyse eski görünüm sürer.
- Kayıtlar telefonda kart görünümü: zaman/kanal + durum üstte, konu, alıcı altta; alıcı harf harf bölünmüyor.

## 1.8.1

- Posta kutusundan giden e-postalarda sabit "Saygılarımızla," kalktı. İmza ayarı boşsa hiçbir şey eklenmez; kapanışı yazan kişi yazar.

## 1.8.0

- Posta kutusu yeni tasarım: kendi çizgi ikonları (yönetim temasında kaymaz), kişi baş harfli liste, filtre düğmeleri, sade okuma başlığı. Kutu darsa sol menü yalnız ikon olur.
- Yazarken canlı önizleme: şablonlu gerçek görünüm (masaüstü/telefon/koyu) geniş ekranda yanda, dar ekranda "Önizle" ile. Yazarken liste gizlenir, yazma alanı genişler.
- Başka ekrandan taslak açma: `flxi_mailbox_new_draft()` ve `POST flxi/v1/mailbox/new-draft` (konu, HTML içerik, şablon). Taslak posta kutusunda açılır, alıcı seçilip gönderilir.
- Bildirimler ekranın altında ortada (sağ alttaki sohbet düğmesiyle çakışmaz).

## 1.7.1

- Sunucuda PHP mailparse uzantısı açıksa posta kutusu gelen e-postaların başlık ve adreslerini onunla okur; yoksa eklentinin kendi ayrıştırıcısı devam eder. Sistem kontrolünde "E-posta ayrıştırıcı" satırı.

## 1.7.0

- E-posta önizlemelerinde (Gönder, E-postalar) "PDF indir": Gotenberg (Flux AI › Ayarlar › Görseller) varsa tek sayfa PDF doğrudan iner; yoksa ya da çalışmazsa tarayıcının yazdırma penceresi açılır, "PDF olarak kaydet" seçilir. Koyu mod seçiliyse PDF de koyu.

## 1.6.1

- Posta kutusu sol menüde ayrı ana öğe (Yorumlar'ın altında), okunmamış + onay bekleyen sayısıyla. İletişim menüsünden çıktı.

## 1.6.0

- Posta kutusu (İletişim › Posta kutusu): bilgi@ adresine gelen ve web postadan gönderilen e-postalar IMAP ile sitede görünür. Arama, okunmamış/yıldız, yazışma görünümü, ekler (açınca sunucudan okunur), dış görseller önce gizli. Sitenin kendi gönderdiği sistem e-postaları da listede, "Sistem e-postaları" kutusuyla görünür.
- Cevapla, Tümünü cevapla, İlet; zengin metin düzenleyici, otomatik kayıtlı taslaklar, önizleme (masaüstü/telefon/koyu), gönderilen e-posta sunucunun Gönderilmiş klasörüne de yazılır.
- AI (Flux AI kuruluysa): "AI ile cevap yaz" ve yeni e-postada "AI ile yaz/düzelt"; AI'nın yazdığı taslak "Onay bekleyen"e düşer, siz göndermeden gitmez. Flux AI sohbetinden de e-posta listelenir, okunur, taslak yazılır; gönderme onayla.
- Şablonlar: Kurumsal (kart) ve Sade (mektup) hazır; "Örnek Vakfı × Firma" gibi alt şablonlar (firma logosu, vurgu rengi, hazır konu ve içerik).
- Hostinger dostu eşitleme: birkaç dakikada bir az sayıda e-posta, kilitli, 30 sn sınırlı. Ayarlar › Posta kutusu (IMAP şifresi boşsa SMTP şifresi kullanılır, şifreli saklanır). Sistem kontrolünde yeni satır.
- Alt kısım çizimi varsayılanı Tepeler oldu; kayıtlı Orman seçimi bir kez Tepeler'e çevrilir.

## 1.5.0

- E-postaların en altına ağaç silüeti: Orman, Tepeler, Çizgi ya da Yok (Ayarlar › Görünüm › Alt kısım çizimi, o zamanki varsayılan Orman). Retina PNG, e-postaya gömülür; koyu modda koyu çizim görünür.

## 1.4.0

- E-postadaki görseller her programda görünür: gönderimde WebP/AVIF görseller JPG'ye (saydamsa PNG'ye) çevrilir, göreli adresler tam adres olur, lazy load kalıntıları (data-src, srcset) temizlenir ve sitenin görselleri e-postaya gömülür (CID). Ayarlar › Görünüm › Görseller'den kapatılabilir.

## 1.3.1

- no-reply@ hesabına SMTP girişi yapılamazsa (şifre yok/yanlış) e-posta bilgi@ ile yeniden gönderilir ve sonraki no-reply e-postaları şifre düzelene kadar bilgi@ ile gider. Ayarlar › Gönderim yöntemi ve Sistem kontrolü hangi hesabın neden başarısız olduğunu gösterir; no-reply@ ile test e-postası o hesabı dener.

## 1.3.0

- Kara liste (Flux Chat 1.7.0, `flx_contact_blocked`): listedeki kişiye e-posta, WhatsApp ve SMS gitmez; kayıtta "Kara listede: gönderilmedi". Çok alıcılı e-postada yalnız o alıcı çıkarılır, toplu gönderimde satır "atlandı" olur. Parola sıfırlama, parola/adres değişikliği e-postaları hariç (`flxi_blocklist_exempt`).

## 1.2.0

- Posta kutusu sınırları (Ayarlar › Posta kutusu sınırları): Hostinger paket sınırları ayarlanabilir, son 24 saat gönderim sayacı (posta kutusu başına, Kime+Bilgi+Gizli).
- Toplu e-posta günlük sınırı aşmaz: tekil e-postalara pay bırakıp duraklar, hak açılınca devam eder; alıcı sayımında kaç gün süreceği gösterilir.
- Sistem kontrolü: sınıra yaklaşınca uyarı, dolunca hata, sık doluyorsa üst pakete geçme önerisi. MCP aracı `flxi_mail_quota`.

## 1.1.1

- E-posta şablonu varsayılan sloganı "Daha yeşil bir Türkiye mümkün" (eski İstanbul varsayılanı kayıtlıysa güncellenir, özel metne dokunulmaz).

## 1.1.0

- Flux Certificates 1.3.0 uyumu: "Flux Certificates'dan taşı" / "Geri al" ve devre dışı bırakınca otomatik geri dönüş kaldırıldı. WhatsApp/SMS bağlantıları yalnız İletişim › Bağlantılar'da.

## 1.0.2

- Sertifika e-postasında "Undefined array key image_alt / image_caption" uyarısı giderildi.

## 1.0.1

- Ortak FluxWP logo: admin menüsünde ve sayfa başlığında renkli ikon (assets/icon.svg).

## 1.0.0

- İlk sürüm: bütün e-postalar bilgi@ / no-reply@ adresinden, logolu mobil uyumlu kurumsal şablonla.
- E-postalar ekranı: WordPress, WooCommerce, Flux Certificates ve diğer eklentilerin e-postalarını aç/kapat, düzenle, önizle, test et.
- Toplu ve tekil gönderim (e-posta, WhatsApp, SMS), kayıtlar, SMTP (Hostinger hazır ayarı), DNS kontrolü.
- WhatsApp (Evolution) ve SMS (Netgsm) bağlantıları; Flux Certificates'dan taşıma ve geri alma.
- Sertifika e-postasında sertifika görseli.
- Google ile kayıt olana parola e-postası gitmez, e-posta doğrulanmış sayılır.
- Sistem kontrolü, MCP araçları, TR + EN.
