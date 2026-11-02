SLUG='flx-tv-remote'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-tv-remote'; WP='6.4'; PHP='7.4'; DOCS='tv-kumanda'; API=False
C={
'en':dict(name='Flux TV Remote',
 tagline='A Google / Android TV remote in the WordPress admin and on your phone, through a small bridge at home.',
 desc='Flux TV Remote controls a Google or Android TV like a remote: power, volume, channels, a touch pad, apps, keyboard and media keys. The site cannot reach a TV at home, so a small bridge program runs on a computer on the same Wi-Fi, pairs with the TV and asks the site for commands. On the same Wi-Fi the bridge also serves its own, faster remote page. A hobby plugin.',
 badges=['![Protocol](https://img.shields.io/badge/Android%20TV%20Remote-v2-2fbf8f)','![Bridge](https://img.shields.io/badge/bridge-Node%2022%2B-0a7cff)','![Hobby](https://img.shields.io/badge/status-hobby-lightgrey)'],
 gifalt='Flux TV Remote tour: the remote with touch pad and keys, then the settings with the bridge download',
 why='''The TV remote is never where you need it, and a phone remote app wants its own account. Flux TV Remote puts the remote where the team already signs in:

- 📱 **Any phone is the remote.** Open the site panel, or add it to the home screen; it fits the screen without scrolling.
- 👆 **A touch pad, not tiny arrows.** Swipe for direction, tap for OK, hold for options; volume repeats while held.
- 🔒 **Only from the same network.** By default commands are accepted only from the bridge's own internet connection.
- 👥 **For staff too.** A separate capability lets staff press keys and open apps; TV address, pairing and settings stay with admins.''',
 features='''### Remote
- Power (with Wake-on-LAN when the TV has dropped off the network), TV settings, channel ±, search, mute, volume ±.
- Touch pad, arrows, back, home; media keys; numbers; keyboard for text fields on the TV.
- Other keys: input source, guide, info, menu, subtitles, audio language, colour keys, sleep, assistant.
- App list (YouTube, Netflix, Prime Video, Disney+, Spotify, Twitch… with direct links), editable as `Name | package or link | #colour`.
- Volume and the current app are read from the TV; keyboard shortcuts on desktop; full screen.

### Bridge
- `bridge/kopru.mjs` (Node 22+) speaks Android TV Remote v2 over TLS, pairs with the 6-digit code on the TV.
- Downloaded from the settings as a ready zip with the site address and a fresh bridge key; `baslat.command` on a Mac.
- Fast local remote page on the same Wi-Fi (`http://<computer-ip>:8765/`), Turkish by default.
- The site never connects to the home network: the bridge polls the site for commands (≈1 s delay).

### Access
- Capability `flxtv_use` (TV Remote group in Flux Roles); Staff, Manager and Admin roles get it by default.
- Same-network check, re-checked every 3 s off Wi-Fi and immediately when the network changes; system checks.''',
 shots=[('remote','Remote'),('settings','Settings and bridge download'),('dark','Dark mode with Flux Admin'),('mobile','Remote on a phone',True)],
 req='A Google / Android TV and a computer on the same Wi-Fi with Node.js 22+ for the bridge. Optional: Flux Roles (staff access), Flux Admin.',
 first='''1. Activate Flux TV Remote; **TV Remote** appears in the admin menu (admins only).
2. **TV Remote › Settings**: enter the TV name and IP, save, then **Download bridge (zip)**.
3. On the computer at home unzip it and start `baslat.command` (Mac) or `npm install && node kopru.mjs`.
4. Open the remote, **Device › Start pairing** and type the code shown on the TV.''',
 note='> ℹ️ Give the TV a fixed IP on the router. Deleting the plugin removes its settings and queue; remove `~/.flx-tv-kopru` on the bridge computer by hand.',
 roadmap=[(1,'Remote with touch pad, keyboard and apps'),(1,'Bridge with pairing and local page'),(1,'Same-network check and staff access'),(0,'Bridge on a Raspberry Pi'),(0,'Several TVs')]),
'tr':dict(name='Flux TV Remote',
 tagline='WordPress panelinde ve telefonda Google / Android TV kumandası; evdeki küçük bir köprüyle.',
 desc="Flux TV Remote bir Google ya da Android TV'yi kumanda gibi yönetir: güç, ses, kanal, dokunmatik alan, uygulamalar, klavye ve medya tuşları. Site evdeki TV'ye ulaşamaz; bu yüzden aynı Wi-Fi'daki bir bilgisayarda küçük bir köprü programı çalışır, TV ile eşleşir ve komutları siteden alır. Aynı Wi-Fi'da köprü kendi, daha hızlı kumanda sayfasını da sunar. Hobi eklentisidir.",
 badges=['![Protokol](https://img.shields.io/badge/Android%20TV%20Remote-v2-2fbf8f)','![Köprü](https://img.shields.io/badge/köprü-Node%2022%2B-0a7cff)','![Hobi](https://img.shields.io/badge/durum-hobi-lightgrey)'],
 gifalt='Flux TV Remote turu: dokunmatik alanlı ve tuşlu kumanda, ardından köprü indirmeli ayarlar',
 why='''Kumanda hiç gereken yerde olmaz, telefon kumanda uygulamaları da ayrı hesap ister. Flux TV Remote kumandayı ekibin zaten girdiği yere koyar:

- 📱 **Her telefon kumanda.** Site panelini açın ya da ana ekrana ekleyin; ekrana kaydırmadan sığar.
- 👆 **Minik oklar değil, dokunmatik alan.** Kaydır = yön, dokun = Tamam, basılı tut = seçenekler; ses tuşu basılı tutunca tekrarlar.
- 🔒 **Yalnız aynı ağdan.** Varsayılan olarak komut yalnız köprünün kendi internet bağlantısından kabul edilir.
- 👥 **Personel için de.** Ayrı yetkiyle personel tuşlara basar, uygulama açar; TV adresi, eşleştirme ve ayarlar yöneticide kalır.''',
 features='''### Kumanda
- Güç (TV ağdan düşmüşse Wake-on-LAN), TV ayarları, kanal ±, arama, sessiz, ses ±.
- Dokunmatik alan, yön okları, geri, ana sayfa; medya tuşları; rakamlar; TV'deki metin alanına klavyeyle yazı.
- Diğer tuşlar: giriş kaynağı, rehber, bilgi, menü, altyazı, ses dili, renk tuşları, uyku, asistan.
- Uygulama listesi (YouTube, Netflix, Prime Video, Disney+, Spotify, Twitch… doğrudan bağlantıyla), `Ad | paket ya da link | #renk` olarak düzenlenir.
- Ses seviyesi ve açık uygulama TV'den okunur; masaüstünde klavye kısayolları; tam ekran.

### Köprü
- `bridge/kopru.mjs` (Node 22+) TLS üzerinden Android TV Remote v2 konuşur, TV'deki 6 haneli kodla eşleşir.
- Ayarlardan site adresi ve yeni köprü anahtarıyla hazır zip olarak indirilir; Mac'te `baslat.command`.
- Aynı Wi-Fi'da hızlı yerel kumanda sayfası (`http://<bilgisayar-ip>:8765/`), varsayılan Türkçe.
- Site ev ağına hiç bağlanmaz: köprü komutları siteye sorar (≈1 sn gecikme).

### Erişim
- `flxtv_use` yetkisi (Flux Roles'de TV Kumandası grubu); Personel, İdareci ve Yönetici rolleri varsayılan alır.
- Aynı ağ denetimi; Wi-Fi dışındayken 3 sn'de bir, ağ değişince hemen yeniden bakar; sistem kontrolleri.''',
 shots=[('remote','Kumanda'),('settings','Ayarlar ve köprü indirme'),('dark','Flux Admin ile koyu tema'),('mobile','Telefonda kumanda',True)],
 req="Bir Google / Android TV ve köprü için aynı Wi-Fi'da Node.js 22+ kurulu bir bilgisayar. İsteğe bağlı: Flux Roles (personel erişimi), Flux Admin.",
 first='''1. Flux TV Remote'nı etkinleştirin; panel menüsünde **TV Kumandası** çıkar (yalnız yöneticiler).
2. **TV Kumandası › Ayarlar**: TV adı ve IP'sini girip kaydedin, sonra **Köprüyü indir (zip)**.
3. Evdeki bilgisayarda zip'i açıp `baslat.command` (Mac) ya da `npm install && node kopru.mjs` ile başlatın.
4. Kumandayı açın, **Cihaz › Eşleştirmeyi başlat** deyip TV'deki kodu girin.''',
 note="> ℹ️ Modemden TV'ye sabit IP verin. Eklenti silinince ayarlar ve kuyruk silinir; köprü bilgisayarındaki `~/.flx-tv-kopru` klasörünü elle silin.",
 roadmap=[(1,'Dokunmatik alanlı, klavyeli ve uygulamalı kumanda'),(1,'Eşleştirmeli ve yerel sayfalı köprü'),(1,'Aynı ağ denetimi ve personel erişimi'),(0,"Raspberry Pi'de köprü"),(0,'Birden çok TV')]),
}
