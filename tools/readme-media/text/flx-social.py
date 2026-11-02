SLUG='flx-social'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-social'; WP='6.4'; PHP='7.4'; DOCS='sosyal-medya'; API=True
MCP_EN='''Flux Social adds tools to the site's MCP server (**Flux SEO**) and to the **Flux AI** chat:

- `flxsm_accounts`, `flxsm_posts`, `flxsm_post_get`, `flxsm_stats`, `flxsm_time`
- `flxsm_post_create`, `flxsm_post_update`, `flxsm_post_delete`, `flxsm_remote_edit`, `flxsm_ai_write`

> "Write a post for 11 November in Turkish for Instagram and Facebook, schedule it for 10:00." · "Which post got the most engagement this month?"

Posts created by an assistant follow the same approval rules as people.'''
MCP_TR='''Flux Social sitenin MCP sunucusuna (**Flux SEO**) ve **Flux AI** sohbetine araçlar ekler:

- `flxsm_accounts`, `flxsm_posts`, `flxsm_post_get`, `flxsm_stats`, `flxsm_time`
- `flxsm_post_create`, `flxsm_post_update`, `flxsm_post_delete`, `flxsm_remote_edit`, `flxsm_ai_write`

> "11 Kasım için Instagram ve Facebook'a bir gönderi yaz, saat 10:00'a zamanla." · "Bu ay en çok etkileşim alan gönderi hangisi?"

Asistanın hazırladığı gönderiler de insanlarla aynı onay kurallarına uyar.'''
C={
'en':dict(name='Flux Social',
 tagline='Plan, approve and publish social media posts from WordPress.',
 desc='Flux Social is a social media planner that runs inside the site: connect Facebook, Instagram, YouTube, X, LinkedIn, TikTok, Pinterest, Google Business and WhatsApp, write once with separate text per account, schedule on a calendar, send posts through approval, and see posts, engagement and followers on one dashboard. No Mixpost or Postiz server is needed.',
 badges=['![Networks](https://img.shields.io/badge/networks-9-1877f2)','![MCP](https://img.shields.io/badge/AI-MCP%20tools-7d64ff)','![Inspired by Mixpost](https://img.shields.io/badge/inspired%20by-Mixpost%20(MIT)-lightgrey)'],
 gifalt='Flux Social tour: the dashboard with daily posts, networks and followers, the post list, a post in the composer with live previews, the calendar and connected accounts',
 why='''Self-hosted planners like Mixpost and Postiz need their own server, Redis and queue workers; shared hosting has none of them. SaaS planners put the charity's accounts and drafts in someone else's tool and charge per seat. Flux Social does the useful part inside WordPress:

- 🗓️ **One composer, every network.** Common text with per-account overrides, character counters, media rules and a live preview for each network.
- ✅ **Approval built in.** Volunteers prepare, a manager approves; permissions come from Flux Roles.
- 📊 **Numbers in one place.** Daily posts per network, engagement, views, follower growth and the top posts.
- ⏱️ **Works on shared hosting.** An external trigger URL (cron-job.org) or the open panel sends scheduled posts; no workers needed.
- 🤖 **AI where you write.** Flux AI helps write and adapt text per network; assistants can plan posts through MCP.''',
 features='''### Writing and scheduling
- **New post:** pick accounts, write common text or separate text per account, add images or video from the media library or Flux Studio, signature, hashtags, link UTM.
- Live preview per network, character and media limits checked before publishing, **Write with AI** and **Adapt to networks** (Flux AI).
- Save draft, send for approval, schedule, share now; repeat a post after N days.
- **Calendar:** month and list view, drag and drop to move a post, filter by account.

### Team and approval
- **Awaiting approval** list: posts by people without publishing permission wait for an approver.
- Permissions `flxsm_post`, `flxsm_publish`, `flxsm_manage` through Flux Roles; *Only mine* filter in the post list.

### Dashboard and accounts
- **Dashboard** for 7, 30 or 90 days: published, engagement, views, followers, scheduled, errors; daily posts per network, per-network table, follower trends, top posts, upcoming posts.
- **Accounts:** Facebook pages, Instagram (with or without a Facebook page), YouTube, X, LinkedIn, TikTok, Pinterest, Google Business Profile, WhatsApp status and groups (via Flux Contact or Flux Call Center).
- Edit or delete a published post on the network where the API allows it; metrics are fetched daily.

### Settings
- App keys per network (stored encrypted), X tier and monthly limit, auto-sharing when a site post is published, external trigger URL, log.''',
 shots=[('dashboard','Dashboard'),('posts','Posts'),('compose','Composer with live preview'),('calendar','Calendar'),('approvals','Awaiting approval'),('accounts','Connected accounts'),('settings','Settings and external trigger'),('dark','Dark mode'),(['mobile','mobile-posts'],'Mobile: dashboard and posts')],
 req='PHP sodium and HTTPS. A developer app for each network you connect (Meta, Google, X, LinkedIn, TikTok, Pinterest). Optional: Flux AI (writing help), Flux Studio (images), Flux Contact or Flux Call Center (WhatsApp), Flux Roles (permissions).',
 first='''1. Activate Flux Social; **Social Media** appears in the menu.
2. **Settings › General**: copy the external trigger URL and add it to cron-job.org every minute.
3. **Settings › Apps**: enter the keys for each network and add the shown redirect address to the developer app.
4. **Accounts › Connect account**, pick the network, allow access and choose the page or channel.
5. **New post**, pick accounts, write, then **Schedule**.''',
 note="> ⚠️ Real network APIs need each network's developer app and sometimes an app review (LinkedIn company pages, TikTok direct posting, Google Business). X Free has a low write limit and no metrics. Flux Social's data model is inspired by [Mixpost](https://github.com/inovector/mixpost) (MIT); the code is written from scratch for WordPress, see [NOTICE.md](NOTICE.md).",
 mcp=MCP_EN,
 roadmap=[(1,'Composer with per-account text and live previews'),(1,'Calendar, approval and dashboard'),(1,'Nine networks including WhatsApp status'),(1,'Flux AI writing and MCP tools'),(0,'Comment and message inbox'),(0,'Best time to post suggestions'),(0,'First-comment and story scheduling')]),
'tr':dict(name='Flux Social',
 tagline="Sosyal medya gönderilerini WordPress'ten planlayın, onaylayın, paylaşın.",
 desc='Flux Social sitenin içinde çalışan bir sosyal medya planlayıcıdır: Facebook, Instagram, YouTube, X, LinkedIn, TikTok, Pinterest, Google İşletme ve WhatsApp hesaplarını bağlayın; bir kez yazıp hesap başına ayrı metin verin, takvimde zamanlayın, gönderileri onaydan geçirin, paylaşım, etkileşim ve takipçileri tek panoda görün. Mixpost ya da Postiz sunucusu gerekmez.',
 badges=['![Ağlar](https://img.shields.io/badge/ağ-9-1877f2)','![MCP](https://img.shields.io/badge/yapay%20zekâ-MCP%20araçları-7d64ff)','![Mixpost esinli](https://img.shields.io/badge/esin-Mixpost%20(MIT)-lightgrey)'],
 gifalt='Flux Social turu: günlük paylaşım, ağlar ve takipçili pano, gönderi listesi, canlı önizlemeli düzenleyicide bir gönderi, takvim ve bağlı hesaplar',
 why='''Mixpost ve Postiz gibi kendi sunucunuzda çalışan planlayıcılar ayrı sunucu, Redis ve kuyruk işçisi ister; paylaşımlı hostingde bunlar yok. Hazır servisler derneğin hesaplarını ve taslaklarını başkasının aracına koyar, kişi başı ücret alır. Flux Social işe yarayan kısmı WordPress içinde yapar:

- 🗓️ **Tek düzenleyici, her ağ.** Hesap başına değiştirilebilen ortak metin, karakter sayaçları, medya kuralları ve her ağ için canlı önizleme.
- ✅ **Onay hazır.** Gönüllüler hazırlar, yönetici onaylar; yetkiler Flux Roles'den gelir.
- 📊 **Rakamlar tek yerde.** Ağ başına günlük paylaşım, etkileşim, görüntülenme, takipçi artışı ve en iyi gönderiler.
- ⏱️ **Paylaşımlı hostingde çalışır.** Dış tetikleme adresi (cron-job.org) ya da açık panel zamanlanmış gönderileri yollar; işçi gerekmez.
- 🤖 **Yazdığınız yerde AI.** Flux AI metni yazmaya ve ağlara uyarlamaya yardım eder; asistanlar MCP ile gönderi planlar.''',
 features='''### Yazma ve zamanlama
- **Yeni gönderi:** hesapları seçin, ortak metin ya da hesap başına ayrı metin yazın; ortam kütüphanesinden ya da Flux Studio'dan görsel veya video ekleyin; imza, etiket, bağlantılara UTM.
- Ağ başına canlı önizleme, paylaşmadan önce karakter ve medya sınırı kontrolü, **AI ile yaz** ve **Ağlara uyarla** (Flux AI).
- Taslak kaydet, onaya gönder, zamanla, şimdi paylaş; gönderiyi N gün sonra yeniden paylaş.
- **Takvim:** ay ve liste görünümü, sürükle-bırak ile taşıma, hesaba göre süzme.

### Ekip ve onay
- **Onay bekleyenler** listesi: paylaşma yetkisi olmayanların gönderileri onaylayanı bekler.
- Flux Roles üzerinden `flxsm_post`, `flxsm_publish`, `flxsm_manage` yetkileri; gönderi listesinde *Yalnız benimkiler* süzgeci.

### Pano ve hesaplar
- 7, 30 ya da 90 günlük **Pano**: paylaşım, etkileşim, görüntülenme, takipçi, zamanlanmış, hata; ağ başına günlük paylaşım, ağ tablosu, takipçi eğrileri, en iyi gönderiler, yaklaşan gönderiler.
- **Hesaplar:** Facebook sayfaları, Instagram (Facebook sayfalı ya da sayfasız), YouTube, X, LinkedIn, TikTok, Pinterest, Google İşletme Profili, WhatsApp durum ve grupları (Flux Mail ya da Flux Chat üzerinden).
- API izin veriyorsa paylaşılmış gönderiyi ağda düzenleme ya da silme; metrikler günde bir çekilir.

### Ayarlar
- Ağ başına uygulama anahtarları (şifreli), X katmanı ve aylık sınır, sitede yazı yayınlanınca otomatik paylaşım, dış tetikleme adresi, günlük.''',
 shots=[('dashboard','Pano'),('posts','Gönderiler'),('compose','Canlı önizlemeli düzenleyici'),('calendar','Takvim'),('approvals','Onay bekleyenler'),('accounts','Bağlı hesaplar'),('settings','Ayarlar ve dış tetikleme'),('dark','Koyu tema'),(['mobile','mobile-posts'],'Mobil: pano ve gönderiler')],
 req="PHP sodium ve HTTPS. Bağlayacağınız her ağ için bir geliştirici uygulaması (Meta, Google, X, LinkedIn, TikTok, Pinterest). İsteğe bağlı: Flux AI (yazım yardımı), Flux Studio (görsel), Flux Mail ya da Flux Chat (WhatsApp), Flux Roles (yetkiler).",
 first='''1. Flux Social'i etkinleştirin; menüde **Sosyal Medya** çıkar.
2. **Ayarlar › Genel**: dış tetikleme adresini kopyalayıp cron-job.org'a dakikada bir ekleyin.
3. **Ayarlar › Uygulamalar**: her ağın anahtarlarını girin, ekrandaki yönlendirme adresini geliştirici uygulamasına ekleyin.
4. **Hesaplar › Hesap bağla**, ağı seçin, izin verin, sayfayı ya da kanalı seçin.
5. **Yeni gönderi**, hesapları seçin, yazın, **Zamanla**.''',
 note="> ⚠️ Gerçek ağ API'leri her ağın geliştirici uygulamasını, bazen de inceleme onayını ister (LinkedIn şirket sayfası, TikTok doğrudan paylaşım, Google İşletme). X Free'de yazma sınırı düşük, metrik yok. Flux Social'in veri modeli [Mixpost](https://github.com/inovector/mixpost)'tan (MIT) esinlenmiştir; kod WordPress için baştan yazıldı, bkz. [NOTICE.md](NOTICE.md).",
 mcp=MCP_TR,
 roadmap=[(1,'Hesap başına metinli ve canlı önizlemeli düzenleyici'),(1,'Takvim, onay ve pano'),(1,'WhatsApp durum dahil dokuz ağ'),(1,'Flux AI yazım yardımı ve MCP araçları'),(0,'Yorum ve mesaj gelen kutusu'),(0,'En iyi paylaşım saati önerisi'),(0,'İlk yorum ve hikâye zamanlama')]),
}
