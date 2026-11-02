SLUG='flx-speed'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-speed'; WP='6.4'; PHP='7.4'; DOCS='flx-speed'; API=True
MCP_EN='''Flux Performance adds read-only tools to the site's MCP server (**Flux SEO & MCP**) and to the **Flux AI** chat:

| Tool | What it answers |
|---|---|
| `flxp_summary`, `flxp_requests`, `flxp_request` | Overview, recorded requests and one request in full |
| `flxp_slow_queries`, `flxp_php_errors`, `flxp_http_calls` | Grouped slow queries, PHP errors, outgoing HTTP calls |
| `flxp_environment`, `flxp_server`, `flxp_database`, `flxp_autoload`, `flxp_cron` | Server, PHP, database, autoload size and scheduled events |
| `flxp_profile_url` | Measure one page of this site with full detail |

With Flux SEO & MCP, the `system_fix` tool can also run `flxp_expired_transients` (delete expired transients).

Example prompts:
- *"Why was the site slow in the last 24 hours?"*
- *"Measure /bagis/ and tell me which plugin runs the slowest query."*
- *"Which plugin bloats autoload the most? Is cron running?"*'''
MCP_TR='''Flux Speed, sitenin MCP sunucusuna (**Flux SEO & MCP**) ve **Flux AI** sohbetine salt okunur araçlar ekler:

| Araç | Neyi yanıtlar |
|---|---|
| `flxp_summary`, `flxp_requests`, `flxp_request` | Özet, kayıtlı istekler ve tek bir isteğin tamamı |
| `flxp_slow_queries`, `flxp_php_errors`, `flxp_http_calls` | Gruplu yavaş sorgular, PHP hataları, dış HTTP istekleri |
| `flxp_environment`, `flxp_server`, `flxp_database`, `flxp_autoload`, `flxp_cron` | Sunucu, PHP, veritabanı, autoload boyutu ve zamanlanmış görevler |
| `flxp_profile_url` | Sitenin bir sayfasını tüm ayrıntısıyla ölçer |

Flux SEO & MCP ile `system_fix` aracı `flxp_expired_transients` düzeltmesini de çalıştırabilir (süresi dolmuş geçici verileri siler).

Örnek istekler:
- *"Son 24 saatte site neden yavaştı?"*
- *"/bagis/ sayfasını ölç, en yavaş sorgu hangi eklentiden?"*
- *"Autoload'ı en çok hangi eklenti şişiriyor? Cron çalışıyor mu?"*'''
C={
'en':dict(name='Flux Performance',
 tagline='Find out why your WordPress site is slow, even on shared hosting.',
 desc='A developer panel for every request (queries, PHP errors, HTTP calls, hooks), a log of slow and failing requests, live server health, database, autoload and cron views, and the shared System Check for all FluxWP packages. AI assistants can read all of it over MCP.',
 badges=['![Query Monitor style](https://img.shields.io/badge/dev%20panel-queries%20%C2%B7%20errors%20%C2%B7%20HTTP-2fbf8f)','![MCP](https://img.shields.io/badge/AI-MCP%20tools-7d64ff)'],
 gifalt='Flux Performance tour: live server boxes and suggestions, the request log and one recorded request, the server screen, System Check and the developer panel on a front-end page',
 why='''Query Monitor tells you what happened on the page you are looking at; it forgets everything else. On shared hosting the slow request is usually the one nobody was watching. Flux Performance keeps the useful part of both worlds:

- 📝 **It records.** Slow and failing requests are saved with their queries, errors and HTTP calls, so you can look at them later.
- 🔍 **A full developer panel** in the admin bar for logged-in staff: `266 ms · 49q · ⚠ 1`, one click away.
- 🩺 **Server health at a glance:** CPU load, RAM, disk, PHP memory and uptime, refreshed live, with suggestions.
- 🧹 **Safe clean-ups:** expired transients, old auto-drafts, spam and trash comments, table optimisation.
- ✅ **One System Check** that every FluxWP package reports into.
- 🤖 **AI ready:** "why was the site slow yesterday?" works in Flux AI and any MCP client.''',
 features='''### Developer panel
- Admin-bar summary (time, queries, warnings) and a resizable bottom panel: queries (duplicates, by component, slow), PHP errors, HTTP API calls, logs, template, request, hooks, timeline and capability checks.
- **Profile page:** open any address of this site like a visitor (no cache) and record it in full.

### Records and overview
- Request log with filters (time, flag, type, address) and "slowest first"; click a row to see its full panel.
- Overview: p95, average and longest time, slowest addresses, query time by plugin, grouped slow queries, PHP errors and failing outgoing requests for the last hour, day or week.
- Suggestions such as "autoload is large", "cron is not running", "disk is almost full", "OPcache is off".

### Server and site
- **Server:** OS, web server, PHP limits and disabled functions, extensions, OPcache, caches, WordPress constants and active plugins. Read from `/proc` and PHP, no `shell_exec` and no external services.
- **Database:** version, key settings, status, table sizes and a clean-up section (admin only, with confirmation).
- **Autoload:** total size and the 40 largest options with their owner. **Cron:** every scheduled event, overdue ones and when cron last ran.
- **System Check:** checks and one-click fixes from every FluxWP package (`flx_system_checks`, `flx_system_fixes`).
- Dashboard widget with live boxes, p95, failing and slow requests and the top suggestions.

### Light on shared hosting
- Visitors only trigger cheap hooks (errors, HTTP calls, timing). Query capture runs only on sampled requests, for logged-in staff and when profiling a page.
- One INSERT at the end of a request; old rows are pruned on the fly. Emails, phone numbers, IBANs, tokens and secrets are masked before anything is stored.''',
 shots=[('overview','Overview'),('records','Request log'),('dev-panel','Developer panel on a page'),('system-check','System Check'),('server','Server'),('dark','Dark mode with Flux Admin'),('mobile','Mobile',True)],
 first='''1. Activate it; the `wp_flxp_requests` table is created automatically.
2. **Performance › Settings** comes with values that suit Hostinger (2 % sampling, 1500 ms slow request, 100 ms slow query, 7 days).
3. Give staff the **flxp_view** capability in Flux Roles if they should see it. Administrators have it already.''',
 note='> ℹ️ On shared hosting CPU load and RAM belong to the whole machine, not just your site. To switch the plugin off completely, add `define( \'FLXP_DISABLED\', true );` to `wp-config.php`.',
 mcp=MCP_EN,
 roadmap=[(1,'Developer panel and request recording'),(1,'Server, database, autoload and cron screens with suggestions'),(1,'Shared System Check for all FluxWP packages'),(1,'MCP and Flux AI tools'),(0,'Block editor requests in the panel'),(0,'Multisite network panel'),(0,'Email alert when error or slow-request rates jump')]),
'tr':dict(name='Flux Speed',
 tagline='WordPress sitenizin neden yavaş olduğunu, paylaşımlı sunucuda bile bulun.',
 desc='Her istek için geliştirici paneli (sorgular, PHP hataları, HTTP istekleri, kancalar), yavaş ve hatalı isteklerin kaydı, canlı sunucu durumu, veritabanı, autoload ve cron ekranları, tüm FluxWP paketlerinin ortak Sistem Kontrolü. Yapay zekâ asistanları hepsini MCP ile okuyabilir.',
 badges=['![Query Monitor tarzı](https://img.shields.io/badge/geliştirici%20paneli-sorgu%20%C2%B7%20hata%20%C2%B7%20HTTP-2fbf8f)','![MCP](https://img.shields.io/badge/yapay%20zekâ-MCP%20araçları-7d64ff)'],
 gifalt='Flux Speed turu: canlı sunucu kutuları ve öneriler, istek kayıtları ve tek bir kayıt, sunucu ekranı, Sistem Kontrolü ve ön yüzde geliştirici paneli',
 why='''Query Monitor baktığınız sayfada ne olduğunu söyler, gerisini unutur. Paylaşımlı sunucuda yavaş istek genellikle kimsenin bakmadığı istektir. Flux Speed iki dünyanın işe yarayan tarafını birleştirir:

- 📝 **Kaydeder.** Yavaş ve hatalı istekler sorguları, hataları ve HTTP çağrılarıyla saklanır; sonradan bakabilirsiniz.
- 🔍 **Tam geliştirici paneli:** giriş yapmış personel için yönetim çubuğunda `266 ms · 49q · ⚠ 1`, bir tık uzakta.
- 🩺 **Bir bakışta sunucu:** işlemci yükü, RAM, disk, PHP belleği ve çalışma süresi canlı; öneriler eşliğinde.
- 🧹 **Güvenli temizlik:** süresi dolmuş transient'ler, eski otomatik taslaklar, spam ve çöp yorumlar, tablo iyileştirme.
- ✅ **Tek Sistem Kontrolü:** tüm FluxWP paketleri buraya bildirir.
- 🤖 **Yapay zekâya hazır:** "site dün neden yavaştı?" sorusu Flux AI'de ve her MCP istemcisinde çalışır.''',
 features='''### Geliştirici paneli
- Yönetim çubuğunda özet (süre, sorgu, uyarı) ve boyutlandırılabilir alt panel: sorgular (tekrarlar, bileşene göre, yavaş), PHP hataları, HTTP API çağrıları, loglar, şablon, istek, kancalar, zaman çizelgesi ve yetki kontrolleri.
- **Sayfayı ölç:** sitenin herhangi bir adresini ziyaretçi gibi (önbelleksiz) açar ve tüm ayrıntısıyla kaydeder.

### Kayıtlar ve özet
- Süzgeçli istek kaydı (zaman, işaret, tür, adres) ve "en yavaş" sıralaması; satıra tıklayınca o isteğin tüm paneli.
- Özet: son 1 saat, 24 saat ya da 7 gün için p95, ortalama ve en uzun süre, en yavaş adresler, eklentilere göre sorgu süresi, gruplu yavaş sorgular, PHP hataları ve başarısız dış istekler.
- "Autoload büyük", "cron çalışmıyor", "disk dolmak üzere", "OPcache kapalı" gibi öneriler.

### Sunucu ve site
- **Sunucu:** işletim sistemi, web sunucusu, PHP sınırları ve kapalı fonksiyonlar, uzantılar, OPcache, önbellekler, WordPress sabitleri ve etkin eklentiler. `/proc` ve PHP'den okunur; `shell_exec` ve dış servis yok.
- **Veritabanı:** sürüm, önemli ayarlar, durum, tablo boyutları ve temizlik bölümü (yalnız yönetici, onay sorar).
- **Autoload:** toplam boyut ve sahibiyle birlikte en büyük 40 seçenek. **Cron:** tüm zamanlanmış görevler, gecikenler ve cron'un son çalışması.
- **Sistem Kontrolü:** tüm FluxWP paketlerinin kontrolleri ve tek tık düzeltmeleri (`flx_system_checks`, `flx_system_fixes`).
- Canlı kutular, p95, hatalı ve yavaş istekler ve ilk önerilerle Başlangıç widget'ı.

### Paylaşımlı sunucuda hafif
- Ziyaretçide yalnız ucuz kancalar çalışır (hata, HTTP çağrısı, süre). Sorgu listesi yalnız örneklenen isteklerde, giriş yapmış personelde ve "Sayfayı ölç"te toplanır.
- İstek sonunda tek INSERT; eski kayıtlar ara sıra temizlenir. E-posta, telefon, IBAN, token ve gizli değerler kaydedilmeden önce maskelenir.''',
 shots=[('overview','Özet'),('records','İstek kayıtları'),('dev-panel','Sayfada geliştirici paneli'),('system-check','Sistem Kontrolü'),('server','Sunucu'),('dark','Flux Admin ile koyu tema'),('mobile','Mobil',True)],
 first='''1. Etkinleştirin; `wp_flxp_requests` tablosu kendiliğinden kurulur.
2. **Performans › Ayarlar** Hostinger'a uygun değerlerle gelir (%2 örnekleme, 1500 ms yavaş istek, 100 ms yavaş sorgu, 7 gün).
3. Personelin görmesi için Flux Roles'den **flxp_view** yetkisini verin. Yöneticide zaten var.''',
 note="> ℹ️ Paylaşımlı sunucuda işlemci yükü ve RAM yalnız sitenize değil tüm makineye aittir. Eklentiyi tamamen kapatmak için `wp-config.php`'ye `define( 'FLXP_DISABLED', true );` ekleyin.",
 mcp=MCP_TR,
 roadmap=[(1,'Geliştirici paneli ve istek kaydı'),(1,'Önerilerle sunucu, veritabanı, autoload ve cron ekranları'),(1,'Tüm FluxWP paketleri için ortak Sistem Kontrolü'),(1,'MCP ve Flux AI araçları'),(0,'Panelde blok editörü istekleri'),(0,'Multisite ağ paneli'),(0,'Hata ya da yavaş istek oranı sıçrayınca e-posta uyarısı')]),
}
