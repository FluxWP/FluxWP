# FluxWP app privacy

Last updated: 6 November 2026. Mobile usage reporting below requires a mobile build with the sharing switch and Flux App 0.4.1 or later on the connected site. Earlier mobile builds do not send this opt-in signal. This release does not integrate a third-party mobile crash-reporting service.

FluxWP is maintained by Ahmet Yılmaz. This notice covers the FluxWP iOS, Android and desktop companion apps. It does not replace the privacy policies of the WordPress sites you choose to connect.

FluxWP-operated website, update and optional telemetry services are provided by **HEYSAN MÜHENDİSLİK SANAYİ VE TİCARET ANONİM ŞİRKETİ**, Yenigün Mahallesi, Kurbanlar Sokak No: 21, İç Kapı No: 101, Adapazarı / Sakarya, Türkiye. Contact: [hello@fluxwp.com](mailto:hello@fluxwp.com). The sites you connect remain responsible for the accounts and content they host.

## Connecting a site

You choose which WordPress sites and accounts to connect. The app sends authentication requests, content requests and actions to those sites. Site operators control their accounts, permissions, stored content, server logs and retention. Review a site's privacy policy before connecting it.

Credentials and access tokens used by the app are stored using the operating system's secure storage (Apple Keychain or Android Keystore on mobile). Account descriptions, preferences and cached site data are stored on the device. Avoid connecting sites on a device shared with untrusted people.

## Content, files and permissions

Posts, media, messages and other site records are retrieved from the connected site according to your account's permissions. When you choose a file or take a photo for a site action, it is sent to the destination site as part of that action. Camera and photo-library access are requested for the corresponding feature; the app does not need microphone recording permission.

## Notifications and updates

When an installation has a configured Expo project and you enable notifications, Expo may process a push token and device-related information to deliver notifications. The connected site can receive a device registration with the token, installation ID, platform, app version, language and device description. Without a configured push backend, notification registration remains disabled.

The desktop app can contact the FluxWP update service to check for releases and obtain update files. This request includes the app/brand version and platform needed to select a compatible update. The destination server receives the network information normally associated with an HTTP request, such as its source IP address.

## Advertising and analytics

The app code does not integrate an advertising SDK or cross-app advertising identifier. The websites you open through the embedded web view may have their own analytics, cookies and third-party integrations, governed by those sites' policies.

### Optional aggregate mobile usage

Sharing is off by default. You can enable or disable **Share aggregate app usage** before sign-in or in Settings. Connecting a site, signing in, using features and pricing do not depend on this choice. Your choice is stored only on your device and is sent as a yes/no header to the WordPress site you connect. The mobile app does not send an analytics request directly to FluxWP.

Counting requires both your choice and a separate permission from the site administrator, in addition to the site's general FluxWP telemetry permission. A previous general telemetry opt-in does not enable the new mobile-usage scope. Only successful authenticated iOS/Android app requests contribute. On the connected site, a keyed digest of the account, UTC day and platform avoids counting the same account repeatedly that day. These temporary digests have a one-day expiry and are removed by scheduled cleanup. Daily totals are scheduled for deletion after 30 days; cleanup may be delayed when a site's WordPress scheduler is not running.

For completed UTC days, a site can send its daily active-account total per platform to its configured FluxWP telemetry hub (by default `https://fluxwp.com/wp-json/flxan/v1/telemetry`). A site/platform/day with fewer than five consenting accounts is omitted. This report contains counts, dates and platform names, not account IDs, names, email, posts, messages, files, site URLs or mobile device IDs. Site country is derived from the site's configured store country or time zone; it is not the user's physical location. The app does not request GPS access for this feature.

The hub groups these totals with a pseudonymous WordPress installation identifier and the existing site country. It does not receive the account-level digests. These are estimates of consenting active accounts, not a census of people: the same person may count again on another site or platform. Turning sharing off stops future contributions. Previously combined totals cannot be separated into a named person's record. The hub's installation records are removed after 90 days of inactivity by scheduled cleanup. Web hosting and the connected site's ordinary access/security logs are separate from this aggregate report.

### WordPress setup and update telemetry

The site's **Help us improve FluxWP** choice is a separate administrator setting, off by default. With it enabled, WordPress update checks may include the site URL, WordPress/PHP versions, language and time zone. The optional daily product-telemetry report includes a random installation identifier, versions of FluxWP packages, site country/language, setup-wizard progress and scrubbed FluxWP PHP diagnostics. The report does not include site URLs, account identities or content. Error-message scrubbing removes recognizable URLs, email/IP addresses and server paths; do not deliberately include personal data in diagnostic messages. These reports describe the WordPress installation and are not mobile crash recordings.

See the [FluxWP website privacy policy](https://fluxwp.com/privacy/) for update-service records, security logs and site-owner choices. The site URL you enter is needed to contact that site; it is not automatically consent to send that URL to FluxWP's central analytics service.

## Your choices

You can disconnect accounts through the app and revoke credentials from the connected WordPress site. Contact the relevant site operator for access to, correction of or deletion of data held by that site. OS backup and secure-storage behavior are controlled by your device and account settings.

The built-in demo uses fictitious sample data and does not require a real WordPress account.

## Contact

For questions about the FluxWP app, use [the project's support page](https://github.com/FluxWP/FluxWP/issues). Do not include passwords, access tokens or private site data in a public issue.

---

## Türkçe

Son güncelleme: 6 Kasım 2026. Aşağıdaki mobil kullanım paylaşımı, paylaşım anahtarı bulunan mobil sürümü ve bağlı sitede Flux App 0.4.1 veya üstünü gerektirir. Önceki mobil sürümler bu izin işaretini göndermez. Bu sürümde üçüncü taraf mobil hata raporlama hizmeti entegre değildir.

FluxWP, sizin seçtiğiniz WordPress sitelerine bağlanır. Giriş ve içerik işlemleri ilgili siteye gönderilir; sitelerdeki veriler ve saklama süreleri site işletmecisinin kontrolündedir. Mobil uygulamada kimlik bilgileri işletim sisteminin güvenli deposunda, tercihler ve önbellek verileri cihazda tutulur.

Seçtiğiniz dosyalar ve çektiğiniz fotoğraflar ilgili site işleminde kullanılır. Mikrofon kaydı izni gerekmez. Bildirim altyapısı yapılandırılmışsa ve izin verirseniz Expo ile bağlı site, bildirim iletimi için cihaz kaydı ve bildirim belirteci işleyebilir. Masaüstü uygulaması sürüm kontrolü için FluxWP güncelleme hizmetine bağlanabilir.

Hesapları uygulamadan ayırabilir, kimlik bilgilerini WordPress sitenizden iptal edebilirsiniz. Sunucuda tutulan verilerle ilgili talepler için ilgili site işletmecisine başvurun. Uygulama soruları için yukarıdaki destek sayfasını kullanın; herkese açık bildirimlere parola veya özel veri eklemeyin.

### İsteğe bağlı mobil kullanım toplamları

Paylaşım varsayılan kapalıdır; girişten önce veya Ayarlar'dan açıp kapatabilirsiniz. Giriş, özellikler ve ücretler bu tercihe bağlı değildir. Tercih cihazda tutulur ve yalnız bağlandığınız siteye evet/hayır başlığı olarak iletilir; mobil uygulama FluxWP'ye doğrudan analiz isteği göndermez.

Sayım için sizin izniniz, site yöneticisinin yeni mobil kullanım izni ve sitenin genel telemetri izni birlikte gerekir. Eski genel izin, yeni mobil kullanım izni yerine geçmez. Yalnız başarılı, oturum açılmış iOS/Android istekleri sayılır. Aynı hesabı aynı UTC günü ve platformda tekrar saymamak için sitenizde anahtarlı, günlük bir özet tutulur. Bu geçici özetlerin süresi bir gündür; zamanlanmış temizlikle silinir. Yerel günlük toplamlar 30 gün sonra silinmek üzere temizlenir. Sitenin WordPress zamanlayıcısı çalışmıyorsa silme gecikebilir.

Her tamamlanmış gün için yalnız en az beş izinli hesap bulunan site/platform gruplarının toplamı sitenin ayarlı telemetri merkezine gönderilir; varsayılan merkez fluxwp.com'dur. Hesap kimlikleri, adlar, e-postalar, içerik, mesaj, dosya, site adresi ve mobil cihaz kimliği bu rapora eklenmez. Ülke sitenin mağaza veya saat dilimi ayarıdır; kullanıcının fiziksel konumu değildir ve GPS izni istenmez.

Merkez toplamları mevcut rastgele WordPress kurulum kimliğinin özeti ve site ülkesiyle gruplar; hesap bazlı günlük özetleri almaz. Aynı kişi farklı site/platformlarda tekrar sayılabilir; bunlar tüm kullanıcıların kesin sayısı değildir. Kapatma gelecekteki katkıları durdurur. Birleşmiş toplamlar içinden belirli bir kişiye ait kayıt ayırt edilemez. Merkez kurulum kayıtları 90 gün hareketsizlikten sonra zamanlanmış temizlikle silinir. Site ve hosting erişim/güvenlik kayıtları bu rapordan ayrıdır.

### Site kurulum ve güncelleme verisi

Site yöneticisinin “Ürünü geliştirmemize yardımcı olun” tercihi ayrı ve varsayılan kapalıdır. Açılırsa güncelleme denetimine site adresi, WordPress/PHP sürümü, dil ve saat dilimi eklenebilir. Ayrı ürün telemetrisi rastgele kurulum kimliği, FluxWP paket sürümleri, site ülkesi/dili, sihirbaz ilerlemesi ve temizlenmiş PHP hata bilgileri içerebilir; kişi kimliği veya içerik göndermez. Bu PHP kayıtları mobil uygulamanın çökme kaydı değildir. Site adresini uygulamaya girmek, adresin merkezi analiz hizmetine gönderilmesine otomatik izin vermez. Ayrıntılar [FluxWP gizlilik politikasındadır](https://fluxwp.com/tr/privacy/).
