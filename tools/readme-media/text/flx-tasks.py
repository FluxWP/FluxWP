SLUG='flx-tasks'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-tasks'; WP='6.5'; PHP='7.4'; DOCS='gorev-takip'; API=False
C={
'en':dict(name='Flux Tasks',
 tagline='Task and project tracking inside WordPress: lists, boards, calendars, meetings and docs, with an AI assistant.',
 desc='Flux Tasks gives the team one place to plan work without another subscription: projects, folders and lists; list, board and calendar views; subtasks, comments with files, notifications and permissions. Meeting lists produce a PDF report, docs are edited block by block, and an assistant on Flux AI can create, update and summarise tasks.',
 badges=['![Views](https://img.shields.io/badge/views-list%20%C2%B7%20board%20%C2%B7%20calendar-0a7cff)','![AI](https://img.shields.io/badge/AI-assistant%20%C2%B7%20MCP-7d64ff)'],
 gifalt='Flux Tasks tour: a list grouped by status, a task with subtasks and comments, the board, the calendar and the usage dashboard',
 why='''Volunteer projects live in WhatsApp groups, spreadsheets and memory. A paid project tool means another login and another bill for every volunteer. Flux Tasks runs where the team already signs in:

- ✅ **Everything in one list.** Who does what by when, with status, priority, tags and custom fields; switch between list, board and calendar.
- 💬 **Talk on the task.** Comments, mentions, files and an activity log sit on the task, not in a chat that scrolls away.
- 📝 **Meetings with minutes.** Agenda, decisions and follow-up subtasks in one place, and a branded PDF report at the end.
- 🤖 **Ask the assistant.** "What is overdue this week?", "Split this into subtasks", "Summarise the comments": through Flux AI, and through MCP for Claude.''',
 features='''### Work
- Projects, folders and lists with drag-and-drop ordering and moving; per-list statuses.
- Views: **List** (grouped, sortable, inline edit), **Board** (drag between statuses) and **Calendar** (month, week, agenda); saved, filtered views per person or shared.
- Tasks with assignees, dates, priority, tags, custom fields (text, number, date, select, person, link…), nested subtasks, checklist, cover image.
- **My tasks** across all projects; notifications for assignment, mention, status, due and overdue.

### Collaborate
- Comments with mentions and drag-and-drop files (stored outside public access); activity log; watchers.
- Project and list permissions (view, comment, edit, manage); roles chosen in settings.
- Optional e-mails for assignment, mention and due dates, editable in Flux Contact.
- **Meeting lists:** agenda, decisions and subtasks, PDF report with logo and status badges.
- **Docs:** block editor with `/` menu, markdown shortcuts, nested pages, tables, task links and an AI menu (continue, summarise, extract to-dos).

### Manage
- **Usage dashboard:** open, overdue and completed tasks, comments, structure, storage per list and per person.
- AI assistant and quick actions through Flux AI; all tools available to the shared Flux AI chat and over MCP.
- Native screen in the Flux App (`flx-tasks/v1/app/*`); system checks.''',
 shots=[('list','List view grouped by status'),('board','Board'),('task','Task with subtasks and comments'),('calendar','Calendar'),('home','My tasks'),('meeting','Meeting list'),('dashboard','Usage dashboard'),('dark','Dark mode with Flux Admin'),('mobile','Mobile list',True)],
 req='Optional: Flux AI (assistant), Flux Contact (e-mails), Flux Admin (app mode), Flux App.',
 first='''1. Activate Flux Tasks; **Tasks** appears in the admin menu with a starter project and list.
2. In **Tasks › Settings** choose which roles can use tasks, create projects and use AI.
3. Create a project and a list, add tasks, and switch to **Board** or **Calendar**.''',
 note='> ℹ️ Files are stored in `wp-content/uploads/flx-tasks/`, closed to direct access with `.htaccess`; on Nginx add a `deny all` rule for that folder.',
 roadmap=[(1,'Lists, boards and calendars with saved views'),(1,'Comments, files, notifications and permissions'),(1,'Docs with an AI menu'),(1,'Meeting lists with PDF reports'),(1,'Native screen in the Flux App'),(0,'Recurring tasks'),(0,'Time tracking')]),
'tr':dict(name='Flux Tasks',
 tagline='WordPress içinde görev ve proje takibi: liste, pano, takvim, toplantı ve doküman; yapay zekâ asistanıyla.',
 desc="Flux Tasks ekibe ayrı bir abonelik olmadan işi planlayacağı tek bir yer verir: projeler, klasörler ve listeler; liste, pano ve takvim görünümleri; alt görevler, dosyalı yorumlar, bildirimler ve yetkiler. Toplantı listeleri PDF rapor üretir, dokümanlar blok blok düzenlenir, Flux AI üzerindeki asistan görev açar, günceller ve özetler.",
 badges=['![Görünümler](https://img.shields.io/badge/görünüm-liste%20%C2%B7%20pano%20%C2%B7%20takvim-0a7cff)','![AI](https://img.shields.io/badge/yapay%20zekâ-asistan%20%C2%B7%20MCP-7d64ff)'],
 gifalt='Flux Tasks turu: duruma göre gruplu liste, alt görevli ve yorumlu bir görev, pano, takvim ve kullanım panosu',
 why='''Gönüllü projeleri WhatsApp gruplarında, tablolarda ve akıllarda yaşar. Ücretli bir proje aracı her gönüllü için bir giriş ve bir fatura daha demektir. Flux Tasks ekibin zaten girdiği yerde çalışır:

- ✅ **Her şey tek listede.** Kim neyi ne zamana yapıyor; durum, öncelik, etiket ve özel alanlarla. Liste, pano ve takvim arasında geçiş.
- 💬 **Görevin üstünde konuşun.** Yorumlar, bahsetmeler, dosyalar ve hareket kaydı görevde durur, kayıp giden bir sohbette değil.
- 📝 **Tutanaklı toplantılar.** Gündem, kararlar ve takip alt görevleri tek yerde; sonunda logolu PDF rapor.
- 🤖 **Asistana sorun.** "Bu hafta geciken ne var?", "Bunu alt görevlere böl", "Yorumları özetle": Flux AI üzerinden, Claude için MCP ile.''',
 features='''### İş
- Sürükle-bırak sıralama ve taşımalı projeler, klasörler ve listeler; liste başına durumlar.
- Görünümler: **Liste** (gruplu, sıralanabilir, satırda düzenleme), **Pano** (durumlar arası sürükle) ve **Takvim** (ay, hafta, ajanda); kişiye özel ya da ortak, filtreli kayıtlı görünümler.
- Atanan, tarih, öncelik, etiket, özel alan (metin, sayı, tarih, seçim, kişi, bağlantı…), iç içe alt görev, kontrol listesi ve kapak görselli görevler.
- Tüm projelerden **Görevlerim**; atama, bahsetme, durum, yaklaşan ve geciken tarih bildirimleri.

### Birlikte çalışma
- Bahsetmeli ve sürükle-bırak dosyalı yorumlar (dosyalar dışarıya kapalı); hareket kaydı; takipçiler.
- Proje ve liste yetkileri (görür, yorum yapar, düzenler, yönetir); roller ayarlardan.
- Atama, bahsetme ve son tarih için isteğe bağlı e-postalar; metinleri Flux Mail'de düzenlenir.
- **Toplantı listeleri:** gündem, kararlar ve alt görevler; logolu, durum rozetli PDF rapor.
- **Dokümanlar:** `/` menülü blok editörü, markdown kısayolları, iç içe sayfalar, tablolar, görev bağlantıları ve yapay zekâ menüsü (devam et, özetle, yapılacakları çıkar).

### Yönetim
- **Kullanım panosu:** açık, geciken ve tamamlanan görevler, yorumlar, yapı, liste ve kişi başına depolama.
- Flux AI üzerinden asistan ve hızlı işlemler; tüm araçlar ortak Flux AI sohbetinde ve MCP'de.
- Flux App'da yerel ekran (`flx-tasks/v1/app/*`); sistem kontrolleri.''',
 shots=[('list','Duruma göre gruplu liste'),('board','Pano'),('task','Alt görevli ve yorumlu görev'),('calendar','Takvim'),('home','Görevlerim'),('meeting','Toplantı listesi'),('dashboard','Kullanım panosu'),('dark','Flux Admin ile koyu tema'),('mobile','Mobil liste',True)],
 req="İsteğe bağlı: Flux AI (asistan), Flux Mail (e-postalar), Flux Admin (uygulama modu), Flux App.",
 first='''1. Flux Tasks'i etkinleştirin; panel menüsünde başlangıç projesi ve listesiyle **Görevler** çıkar.
2. **Görevler › Ayarlar**'da görevleri kullanabilecek, proje açabilecek ve yapay zekâ kullanabilecek rolleri seçin.
3. Bir proje ve liste açın, görev ekleyin, **Pano** ya da **Takvim**'e geçin.''',
 note="> ℹ️ Dosyalar `wp-content/uploads/flx-tasks/` içinde, `.htaccess` ile dışarıya kapalı saklanır; Nginx'te bu klasör için `deny all` kuralı ekleyin.",
 roadmap=[(1,'Kayıtlı görünümlü liste, pano ve takvim'),(1,'Yorum, dosya, bildirim ve yetkiler'),(1,'Yapay zekâ menülü dokümanlar'),(1,'PDF raporlu toplantı listeleri'),(1,"Flux App'da yerel ekran"),(0,'Tekrarlayan görevler'),(0,'Süre takibi')]),
}
