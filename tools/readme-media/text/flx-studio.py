SLUG='flx-studio'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-studio'; WP='6.4'; PHP='7.4'; DOCS='flx-studio'; API=True
C={
'en':dict(name='Flux Studio',
 tagline='An AI image and video studio inside WordPress, for the whole team, on one budget.',
 desc='Flux Studio lets your team create images and videos with AI from the admin: write what you want, pick a model, add reference images or people, and generate. Results land in a shared gallery with folders, favourites and templates, and can go straight to the Media Library. Generation runs through Flux AI, so keys, costs and the spending limit stay in one place.',
 badges=['![Models](https://img.shields.io/badge/models-Nano%20Banana%20%C2%B7%20Seedream%20%C2%B7%20Imagen%20%C2%B7%20GPT%20Image-7d64ff)','![Video](https://img.shields.io/badge/video-yes-2fbf8f)'],
 gifalt='Flux Studio tour: writing a prompt, the model picker, the image viewer and the dashboard with spend per model',
 why='''Posters for an event, a header for a news post, a thumbnail for a video: a small team needs images every week, and every person ends up on a different AI site with a different card. Flux Studio brings it into WordPress:

- 🎨 **One place for the team.** Everyone with access generates in the same studio; shared folders and templates keep the style consistent.
- 💸 **One budget.** Every generation goes through Flux AI and counts against its daily, weekly and monthly limit; the dashboard shows who spent what on which model.
- 🧠 **Better prompts without effort.** *Enhance* turns a rough idea into a detailed prompt for the chosen model; *Brainstorm* gives several directions to pick from.
- 🖼️ **Straight to the site.** Add a result to the Media Library in one click and use it in posts, pages and campaigns.''',
 features='''### Create
- Images and videos; model picker by provider and model (fal.ai, EvoLink, OpenAI, Gemini, OpenRouter), aspect ratio, resolution, quality, count, duration and audio; estimated price on the button.
- **References:** upload, paste, drag or pick from the gallery; give each a role (style, product, background, person, first/last frame); save favourites.
- **People:** 1–8 face photos per person; type `@name` in the prompt to use them as identity references.
- **Enhance** and **Brainstorm** (2–8 prompts from one idea, general or YouTube thumbnail), generated in one go.
- **Edit area:** brush, rectangle and eraser mask; only the masked part changes; before/after slider.
- Instant queue: a "Queued" card appears at once, long jobs keep running when the page is closed.

### Organise
- Gallery with a folder tree (subfolders, team sharing, drag to move), favourites, search, multi-select and infinite scroll.
- Viewer: download, reuse settings, use as reference, add to Media Library, move, delete.
- **Templates:** prompt, settings and references saved and shared with the team.

### Manage
- **Dashboard:** generations, spend (image/video and text), average per generation, error rate, people and storage; daily charts; tables per model, provider and person; Flux AI limit and recent errors.
- Capabilities `flxst_use` and `flxst_manage`, listed in Flux Roles; light and dark theme with Flux Admin; works on phones and as a native screen in the Flux App.''',
 shots=[('gallery','Gallery and composer'),('viewer','Image viewer'),('models','Model picker'),('brainstorm','Brainstorm'),('dashboard','Dashboard'),('dark','Dark mode with Flux Admin'),('mobile','Mobile gallery',True)],
 req='Flux AI 0.6.0+ with at least one image/video key (fal.ai or EvoLink recommended; OpenAI, Gemini or OpenRouter also work).',
 first='''1. In **Flux AI & MCP › Settings › Images and video** enter a provider key.
2. Activate Flux Studio; **Studio** appears in the admin menu.
3. Give staff the **Studio: generate** capability in Flux Roles if they should use it.
4. Write a prompt and press **Generate** (Ctrl/⌘+Enter).''',
 note='> ⚠️ Video can take minutes; the job keeps running if you close the page. Spend counts against the Flux AI limit. The demo gallery in these screenshots uses existing site photos.',
 dev='''- REST API under `/wp-json/flx-studio/v1` (boot, items, generate, poll, enhance, brainstorm, folders, people, refs, templates); works with Flux App tokens for the desktop and mobile app.
- Registered with Flux AI (`flxai_systems`), Flux Roles, Flux Admin app mode and the system checks.''',
 licextra='Workflow and prompt rules adapted from [Banana Studio](https://github.com/orkunisitmak/banana-studio) (MIT). See [NOTICE.md](NOTICE.md).',
 roadmap=[(1,'Image and video generation through Flux AI'),(1,'References, people, enhance and brainstorm'),(1,'Folders, templates and team sharing'),(1,'Dashboard with spend per model and person'),(1,'Native screen in the Flux App'),(0,'Brand kit (colours, logo, fonts) as a reference'),(0,'Batch resize for social media formats')]),
'tr':dict(name='Flux Studio',
 tagline='WordPress içinde tüm ekip için, tek bütçeyle yapay zekâ görsel ve video stüdyosu.',
 desc="Flux Studio ekibinizin panelden yapay zekâyla görsel ve video üretmesini sağlar: ne istediğinizi yazın, model seçin, referans görsel ya da kişi ekleyin, üretin. Sonuçlar klasörlü, favorili, şablonlu ortak bir galeriye düşer ve doğrudan Medya kitaplığına gidebilir. Üretim Flux AI üzerinden yapılır; anahtarlar, maliyet ve harcama sınırı tek yerde kalır.",
 badges=['![Modeller](https://img.shields.io/badge/model-Nano%20Banana%20%C2%B7%20Seedream%20%C2%B7%20Imagen%20%C2%B7%20GPT%20Image-7d64ff)','![Video](https://img.shields.io/badge/video-var-2fbf8f)'],
 gifalt='Flux Studio turu: istem yazma, model seçici, görsel görüntüleyici ve model başına harcamalı pano',
 why='''Etkinlik afişi, haber görseli, video küçük resmi: küçük bir ekibin her hafta görsele ihtiyacı olur ve herkes farklı bir AI sitesinde, farklı bir kartla çalışır. Flux Studio bunu WordPress'e taşır:

- 🎨 **Ekip için tek yer.** Yetkisi olan herkes aynı stüdyoda üretir; ortak klasörler ve şablonlar tarzı tutarlı tutar.
- 💸 **Tek bütçe.** Her üretim Flux AI'den geçer, günlük, haftalık ve aylık sınıra sayılır; pano kimin hangi modelde ne harcadığını gösterir.
- 🧠 **Zahmetsiz daha iyi istem.** *Geliştir* kaba bir fikri seçili modele uygun ayrıntılı isteme çevirir; *Beyin fırtınası* seçmek için birkaç yön verir.
- 🖼️ **Doğrudan siteye.** Sonucu tek tıkla Medya kitaplığına ekleyip yazıda, sayfada, kampanyada kullanın.''',
 features='''### Üret
- Görsel ve video; sağlayıcı ve modele göre model seçici (fal.ai, EvoLink, OpenAI, Gemini, OpenRouter), en-boy, çözünürlük, kalite, adet, süre ve ses; düğmede tahmini fiyat.
- **Referanslar:** yükle, yapıştır, sürükle ya da galeriden seç; her birine rol ver (stil, ürün, arka plan, kişi, ilk/son kare); sık kullanılanları kaydet.
- **Kişiler:** kişi başına 1–8 yüz fotoğrafı; istemde `@ad` yazınca kimlik referansı olur.
- **Geliştir** ve **Beyin fırtınası** (bir fikirden 2–8 istem, genel ya da YouTube küçük resmi), tek seferde üretim.
- **Alan düzenleme:** fırça, dikdörtgen ve silgiyle maske; yalnız maskeli yer değişir; önce/sonra kaydırıcısı.
- Anında kuyruk: "Sırada" kartı hemen görünür, uzun işler sayfa kapansa da sürer.

### Düzenle
- Klasör ağaçlı galeri (alt klasör, ekiple paylaşım, sürükleyerek taşıma), favoriler, arama, çoklu seçim, sonsuz kaydırma.
- Görüntüleyici: indir, ayarları tekrar kullan, referans yap, Medya kitaplığına ekle, taşı, sil.
- **Şablonlar:** istem, ayar ve referanslar kaydedilir, ekiple paylaşılır.

### Yönet
- **Pano:** üretim, harcama (görsel/video ve metin), üretim başına ortalama, hata oranı, kişi ve depolama; günlük grafikler; model, sağlayıcı ve kişi tabloları; Flux AI sınırı ve son hatalar.
- Flux Roles'de listelenen `flxst_use` ve `flxst_manage` yetkileri; Flux Admin ile açık ve koyu tema; telefonda ve Flux App'da yerel ekran olarak çalışır.''',
 shots=[('gallery','Galeri ve üretim kutusu'),('viewer','Görsel görüntüleyici'),('models','Model seçici'),('brainstorm','Beyin fırtınası'),('dashboard','Pano'),('dark','Flux Admin ile koyu tema'),('mobile','Mobil galeri',True)],
 req="Flux AI 0.6.0+ ve en az bir görsel/video anahtarı (fal.ai ya da EvoLink önerilir; OpenAI, Gemini ya da OpenRouter da olur).",
 first='''1. **Flux AI & MCP › Ayarlar › Görsel ve video**'ya bir sağlayıcı anahtarı girin.
2. Flux Studio'yu etkinleştirin; panel menüsünde **Stüdyo** çıkar.
3. Personel kullanacaksa Flux Roles'de **Stüdyo: görsel/video üret** yetkisini verin.
4. Bir istem yazıp **Üret**'e basın (Ctrl/⌘+Enter).''',
 note="> ⚠️ Video dakikalar sürebilir; sayfa kapansa da iş devam eder. Harcama Flux AI sınırına sayılır. Bu ekranlardaki demo galeride sitedeki mevcut fotoğraflar kullanıldı.",
 dev='''- `/wp-json/flx-studio/v1` altında REST API (boot, items, generate, poll, enhance, brainstorm, folders, people, refs, templates); masaüstü ve mobil uygulama için Flux App belirteçleriyle çalışır.
- Flux AI'ye (`flxai_systems`), Flux Roles'e, Flux Admin uygulama moduna ve sistem kontrollerine kayıtlı.''',
 licextra="İş akışı ve istem kuralları [Banana Studio](https://github.com/orkunisitmak/banana-studio)'dan (MIT) uyarlandı. Bkz. [NOTICE.md](NOTICE.md).",
 roadmap=[(1,'Flux AI üzerinden görsel ve video üretimi'),(1,'Referans, kişi, geliştir ve beyin fırtınası'),(1,'Klasör, şablon ve ekip paylaşımı'),(1,'Model ve kişi başına harcamalı pano'),(1,"Flux App'da yerel ekran"),(0,'Referans olarak marka kiti (renk, logo, font)'),(0,'Sosyal medya boyutlarına toplu kırpma')]),
}
