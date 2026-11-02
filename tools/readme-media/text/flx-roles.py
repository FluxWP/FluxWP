SLUG='flx-roles'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-roles'; WP='6.4'; PHP='7.4'; DOCS='flx-roles'; API=True
C={
'en':dict(name='Flux Roles',
 tagline='Give staff and volunteers exactly the access they need, from one page.',
 desc='Flux Roles adds Staff, Manager and Director roles (and any role you create in the panel), and lets you switch each role\'s access to tasks, certificates, donations, the call center, the studio and reports on or off with a toggle. It also manages personal referral links and the phone numbers the WhatsApp assistant uses to recognise staff.',
 badges=['![Roles](https://img.shields.io/badge/roles-Staff%20%C2%B7%20Manager%20%C2%B7%20Director-2fbf8f)'],
 gifalt='Flux Roles tour: role cards, the access table with toggles for each FluxWP package, and the people section with referral links',
 why='''WordPress roles are built for blogs: Editor, Author, Contributor. A foundation needs "this volunteer can create certificates and see the donations they referred, but nothing else". Flux Roles turns that into toggles:

- 🎚️ **One table, real permissions.** Each row is a real WordPress capability. Switching it off removes the menu *and* blocks the address.
- 👥 **Roles that match the organisation:** Staff, Manager and Director, plus any role you add (Volunteer, Accounting…).
- 🔗 **Personal referral links** for everyone who may refer donors; donations through the link are credited to them.
- 📱 **WhatsApp-aware:** a staff member's phone number lets the call-center assistant recognise them and act within their role.
- 🧩 **Grows with the other packages:** new FluxWP packages register their capabilities here automatically.''',
 features='''### Roles
- **Staff** (everyday work), **Manager** (Staff plus the donation and certificate dashboards and reports) and **Director** (every permission on the page, but not a WordPress administrator: cannot install plugins or change site settings).
- Add a new role from the panel; it starts with admin access only and you switch on what it needs. Roles created here can be deleted.
- "Reset to defaults" per role.

### Access table
- Grouped by package: General, Tasks, Certificates, Donations, Call Center, Studio, Reports.
- Packages that aren't installed show a "not installed" badge; settings are kept and apply as soon as the package arrives.
- Choose the screen each role lands on after login (or let it pick the first available).

### People and referrals
- Add or remove people per role by username or e-mail.
- Personal referral link (`?ref=code`) with a copy button on the profile and in the people list; the last clicked link wins and lasts 30 days (configurable). The referrer is written to the WooCommerce order.
- Phone number per user (admins only) for the WhatsApp assistant in Flux Call Center.''',
 shots=[('roles','Roles and the access table'),('capabilities','Access per package'),('people','People and referral links'),('mobile','Mobile',True)],
 first='''1. **Users › Roles and access** opens with the roles ready and default permissions set.
2. Add people: create the user under **Users › Add New** and choose Staff/Manager, or type an existing username in **People › Add**.
3. Switch rows on or off and press **Save**.''',
 dev='''- Packages check **capabilities, never role names**: `user_can( $user, 'flxb_dashboard' )`.
- Register a package's capabilities for the table with the `flx_role_caps_supported` filter; list roles with `flxr_roles()` (filter `flxr_roles`).
- Full capability list and contract: [API.md](../../docs/flx-roles/API.md).''',
 roadmap=[(1,'Staff and Manager roles with a single access page'),(1,'Director role, custom roles, WhatsApp numbers'),(1,'Studio capabilities'),(0,'Per-project task permissions from the same page'),(0,'Audit log of permission changes')]),
'tr':dict(name='Flux Roles',
 tagline='Personele ve gönüllülere tam ihtiyaç duydukları erişimi tek sayfadan verin.',
 desc="Flux Roles Personel, İdareci ve Yönetici rollerini (ve panelden ekleyeceğiniz her rolü) ekler; her rolün görevlere, sertifikalara, bağışlara, çağrı merkezine, stüdyoya ve raporlara erişimini tek düğmeyle açıp kapatırsınız. Kişisel referans bağlantılarını ve WhatsApp asistanının personeli tanıdığı telefon numaralarını da burada yönetirsiniz.",
 badges=['![Roller](https://img.shields.io/badge/roller-Personel%20%C2%B7%20İdareci%20%C2%B7%20Yönetici-2fbf8f)'],
 gifalt='Flux Roles turu: rol kartları, her FluxWP paketi için düğmeli erişim tablosu ve referans bağlantılı kişiler bölümü',
 why='''WordPress rolleri blog için düşünülmüş: Editör, Yazar, Katılımcı. Bir derneğin ihtiyacı ise "bu gönüllü sertifika oluşturabilsin ve getirdiği bağışları görsün, başka bir şey değil". Flux Roles bunu düğmelere çevirir:

- 🎚️ **Tek tablo, gerçek yetki.** Her satır gerçek bir WordPress yetkisidir. Kapatınca menüden kalkar *ve* adresle de girilemez.
- 👥 **Kuruma uygun roller:** Personel, İdareci ve Yönetici; artı sizin ekleyeceğiniz roller (Gönüllü, Muhasebe…).
- 🔗 **Kişisel referans bağlantıları:** bağlantıyla gelen bağış o kişiye yazılır.
- 📱 **WhatsApp'la uyumlu:** personelin telefon numarası sayesinde çağrı merkezi asistanı onu tanır ve rolünün izin verdiği işleri yapar.
- 🧩 **Diğer paketlerle büyür:** yeni FluxWP paketleri yetkilerini buraya kendiliğinden kaydeder.''',
 features='''### Roller
- **Personel** (günlük iş), **İdareci** (Personel + bağış ve sertifika dashboard'ları, raporlar) ve **Yönetici** (sayfadaki her yetki; ama WordPress yöneticisi değil, eklenti kuramaz, site ayarını değiştiremez).
- Panelden yeni rol ekleyin; yalnız panele girişle başlar, gereken yetkileri açarsınız. Burada eklenen roller silinebilir.
- Her rol için "Varsayılana döndür".

### Erişim tablosu
- Pakete göre gruplu: Genel, Görevler, Sertifikalar, Bağışlar, Çağrı Merkezi, Stüdyo, Raporlar.
- Kurulu olmayan paketler "kurulu değil" rozetiyle görünür; ayar saklanır, paket gelince geçerli olur.
- Her rolün girişten sonra açılacak ekranı (ya da ilk erişilebilir ekran).

### Kişiler ve referanslar
- Kullanıcı adı ya da e-postayla role kişi ekleme/çıkarma.
- Profilde ve kişiler listesinde kopyala düğmeli kişisel referans bağlantısı (`?ref=kod`); son tıklanan bağlantı geçerli, 30 gün (ayarlanabilir). Referans WooCommerce siparişine yazılır.
- Kullanıcı başına telefon numarası (yalnız yönetici yazar): Flux Chat'ndeki WhatsApp asistanı için.''',
 shots=[('roles','Roller ve erişim tablosu'),('capabilities','Pakete göre erişim'),('people','Kişiler ve referans bağlantıları'),('mobile','Mobil',True)],
 first='''1. **Kullanıcılar › Roller ve erişim** roller hazır ve varsayılan yetkilerle açılır.
2. Kişi ekleyin: **Kullanıcılar › Yeni ekle**'de oluşturup Personel/İdareci seçin ya da var olan kullanıcıyı **Kişiler › Ekle**'ye yazın.
3. Satırları açıp kapatın ve **Kaydet**'e basın.''',
 dev='''- Paketler **rol adına değil, yetkiye** bakar: `user_can( $user, 'flxb_dashboard' )`.
- Paketin yetkilerini tabloya `flx_role_caps_supported` filtresiyle kaydedin; roller `flxr_roles()` ile (filtre `flxr_roles`).
- Tam yetki listesi ve sözleşme: [API.md](../../docs/flx-roles/API.md).''',
 roadmap=[(1,'Tek erişim sayfasıyla Personel ve İdareci rolleri'),(1,'Yönetici rolü, özel roller, WhatsApp numaraları'),(1,'Stüdyo yetkileri'),(0,'Aynı sayfadan proje bazlı görev yetkileri'),(0,'Yetki değişikliklerinin kaydı')]),
}
