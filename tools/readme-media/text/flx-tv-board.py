SLUG='flx-tv-board'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-tv-board'; WP='6.4'; PHP='7.4'; DOCS=None; API=False
C={
'en':dict(name='Flux TV Board',
 tagline="A live donation and task board for the office TV, designed in WordPress and updated on screen within seconds.",
 desc="Flux TV Board manages the layout of the Google TV board in the office: glass cards on a grid, slides inside cards, rotating nature backgrounds and several pages. Cards show today's donations, the hourly chart, the latest donors with their referrer, the daily goal, open tasks from Flux Tasks, prayer times and the calendar. The layout is stored as JSON; the TV reads it through Flux SEO and refreshes a few seconds after you save.",
 badges=['![TV](https://img.shields.io/badge/Google%20TV-~55%20fps-2fbf8f)','![Offline](https://img.shields.io/badge/prayer%20times-no%20external%20requests-0a7cff)','![AI](https://img.shields.io/badge/AI-edit%20by%20chat%20%C2%B7%20MCP-7d64ff)'],
 gifalt='Flux TV Board tour: the visual editor with the live TV preview, adding a card, and the home, donations, tasks and calendar pages',
 why='''A team that raises money wants to see it happen. Spreadsheets on a laptop don't do that; a TV on the wall does, if someone can change what it shows without touching the TV:

- 📺 **Edit on the site, see it on the TV.** Drag, resize and add cards in an exact TV preview; save and the board updates in seconds.
- 🌱 **Celebrate every donation.** A full-screen announcement with a sprouting sapling, the donor, the amount and who brought it in.
- ✅ **The team's day at a glance.** Open, overdue and due-today tasks and tasks per person from Flux Tasks.
- 🤖 **Ask instead of dragging.** "Add a tasks page", "make the clock smaller": Flux AI and MCP edit the layout, with history and one-click restore.''',
 features='''### Cards
- **Donations:** today's total and count with an hourly curve, hourly chart, latest donations (item, referrer person or source site, bank-transfer pre-registration), daily goal bar.
- **Tasks** (with Flux Tasks): task counts, task list (upcoming, overdue, today, recently done), tasks per person; filter by project.
- **Calendar:** prayer times with the current time highlighted and a countdown, Hijri and Rumi date, moon phase, verse / hadith of the day. Data for 1,508 districts ships with the plugin for 2026 and 2027; no external requests.
- **Content:** clock and greeting, text, Markdown, image.

### Layout
- Grid-based JSON layout; several pages with tabs, a home page, remote ← → and optional auto-advance; slides inside each card.
- Glass cards over rotating nature backgrounds (five suggested 4K images in one click), night dimming and slight drift against burn-in.
- Visual editor: exact TV preview, drag to move, resize from the corner, undo/redo, JSON tab, last 15 saves with restore.
- Cards adapt to their size: shorter cards hide secondary lines and enlarge the number.

### TV and AI
- TV lite mode: pre-blurred background copies instead of live blur, idle frame time 65 → 17 ms.
- MCP tools `tv_layout`, `tv_data`, `tv_schema`, `tv_layout_edit`, `tv_layout_save`, `tv_layout_history`, `tv_layout_restore`; writes are admin-only and ask for confirmation in Flux AI.''',
 shots=[('editor','Visual editor with the live TV preview'),('tv-home','Home page'),('tv-feed','Donations page'),('tv-tasks','Tasks page'),('tv-calendar','Prayer times, calendar and verse of the day'),('dark','Editor in dark mode with Flux Admin')],
 req='WooCommerce (donations), Flux SEO (the TV reads the layout through it) and the Google TV board app. Optional: Flux Tasks, Flux Roles (referrers), Flux AI.',
 first='''1. Activate Flux TV Board; **TV Board** appears in the admin menu with a default layout.
2. Add, move and resize cards in the preview; use **Add card** for donation, task, calendar and content cards. **Save**.
3. On the TV app, connect to the site's Flux SEO endpoint; the board appears and follows every save.''',
 note='> ℹ️ Prayer time data covers 2026 and 2027. Data for 2028 has to be added before the end of 2027 (`tools/takvim-indir.py`, not shipped in the zip).',
 roadmap=[(1,'Grid editor with live TV preview and history'),(1,'Donation cards and full-screen announcement'),(1,'Task cards from Flux Tasks'),(1,'Offline prayer times and calendar'),(1,'Editing by AI chat and MCP'),(0,'Event countdown card'),(0,'Several TVs with different layouts')]),
'tr':dict(name='Flux TV Board',
 tagline="Ofis TV'si için canlı bağış ve görev panosu: WordPress'te tasarlanır, saniyeler içinde ekranda.",
 desc="Flux TV Board ofisteki Google TV panosunun düzenini yönetir: ızgaraya oturan cam kartlar, kart içi slaytlar, dönen doğa arka planları ve birden çok sayfa. Kartlar bugünkü bağışları, saat saat grafiği, referansıyla son bağışçıları, günlük hedefi, Flux Tasks'deki açık görevleri, namaz vakitlerini ve takvimi gösterir. Düzen JSON olarak saklanır; TV onu Flux SEO üzerinden okur, kaydettikten birkaç saniye sonra yenilenir.",
 badges=['![TV](https://img.shields.io/badge/Google%20TV-~55%20kare%2Fsn-2fbf8f)','![Çevrimdışı](https://img.shields.io/badge/namaz%20vakti-dış%20istek%20yok-0a7cff)','![AI](https://img.shields.io/badge/yapay%20zekâ-sohbetle%20düzen%20%C2%B7%20MCP-7d64ff)'],
 gifalt='Flux TV Board turu: canlı TV önizlemeli görsel düzenleyici, kart ekleme, ana sayfa, bağış, görev ve takvim sayfaları',
 why='''Bağış toplayan bir ekip bunun olduğunu görmek ister. Dizüstündeki tablo bunu yapmaz; duvardaki TV yapar, yeter ki gösterdiğini TV'ye dokunmadan değiştirebilelim:

- 📺 **Sitede düzenle, TV'de gör.** Kartları birebir TV önizlemesinde sürükleyin, boyutlandırın, ekleyin; kaydedin, pano saniyeler içinde güncellenir.
- 🌱 **Her bağışı kutlayın.** Filizlenen fidanla tam ekran duyuru: bağışçı, tutar ve bağışı kimin getirdiği.
- ✅ **Ekibin günü tek bakışta.** Flux Tasks'den açık, geciken ve bugün teslim görevler, kişi başı görevler.
- 🤖 **Sürüklemek yerine sorun.** "Görevler sayfası ekle", "saati küçült": Flux AI ve MCP düzeni değiştirir; geçmiş ve tek tıkla geri yükleme var.''',
 features='''### Kartlar
- **Bağış:** saatlik eğriyle bugünkü toplam ve sayı, saat saat grafik, son bağışlar (kalem, referans kişi ya da kaynak site, havale ön kaydı), günlük hedef çubuğu.
- **Görev** (Flux Tasks ile): görev sayıları, görev listesi (yaklaşan, geciken, bugün, son tamamlanan), kişi başı görev; projeye göre süzgeç.
- **Takvim:** içinde bulunulan vakit vurgulu ve geri sayımlı namaz vakitleri, hicri ve rumi tarih, ay evresi, günün ayet / hadisi. 1508 ilçenin 2026 ve 2027 verisi eklentiyle gelir; dış istek yok.
- **İçerik:** saat ve selam, metin, Markdown, görsel.

### Düzen
- Izgara tabanlı JSON düzen; sekmeli birden çok sayfa, ana sayfa, kumandada ← → ve isteğe bağlı otomatik geçiş; her kartta slaytlar.
- Dönen doğa arka planları üzerinde cam kartlar (önerilen beş 4K görsel tek tıkla), gece kısık ekran ve ekran izine karşı hafif kayma.
- Görsel düzenleyici: birebir TV önizlemesi, sürükleyerek taşıma, köşeden boyutlandırma, geri al/yinele, JSON sekmesi, geri yüklenebilir son 15 kayıt.
- Kartlar boyuna uyar: kısa kartta ikincil satırlar gizlenir, rakam büyür.

### TV ve AI
- TV hafif modu: canlı bulanıklık yerine önceden bulanıklaştırılmış arka plan kopyaları, boşta kare süresi 65 → 17 ms.
- MCP araçları `tv_layout`, `tv_data`, `tv_schema`, `tv_layout_edit`, `tv_layout_save`, `tv_layout_history`, `tv_layout_restore`; yazan araçlar yalnız yönetici, Flux AI'de onay ister.''',
 shots=[('editor','Canlı TV önizlemeli görsel düzenleyici'),('tv-home','Ana sayfa'),('tv-feed','Bağış sayfası'),('tv-tasks','Görevler sayfası'),('tv-calendar','Namaz vakitleri, takvim ve günün ayeti'),('dark','Flux Admin ile koyu temada düzenleyici')],
 req="WooCommerce (bağışlar), Flux SEO (TV düzeni bunun üzerinden okur) ve Google TV pano uygulaması. İsteğe bağlı: Flux Tasks, Flux Roles (referanslar), Flux AI.",
 first='''1. Flux TV Board'nu etkinleştirin; panel menüsünde varsayılan düzenle **TV Panosu** çıkar.
2. Önizlemede kartları ekleyin, taşıyın, boyutlandırın; bağış, görev, takvim ve içerik kartları için **Kart ekle**. **Kaydet**.
3. TV uygulamasında sitenin Flux SEO adresine bağlanın; pano açılır ve her kaydı izler.''',
 note="> ℹ️ Namaz vakti verisi 2026 ve 2027'yi kapsar. 2028 verisi 2027 bitmeden eklenmeli (`tools/takvim-indir.py`, zip'e girmez).",
 roadmap=[(1,'Canlı TV önizlemeli ve geçmişli ızgara düzenleyici'),(1,'Bağış kartları ve tam ekran duyuru'),(1,"Flux Tasks'den görev kartları"),(1,'Çevrimdışı namaz vakti ve takvim'),(1,'Yapay zekâ sohbeti ve MCP ile düzenleme'),(0,'Etkinlik geri sayım kartı'),(0,'Farklı düzenli birden çok TV')]),
}
