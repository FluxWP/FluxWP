# FluxWP app privacy

FluxWP is maintained by Ahmet Yılmaz. This notice covers the FluxWP iOS, Android and desktop companion apps. It does not replace the privacy policies of the WordPress sites you choose to connect.

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

## Your choices

You can disconnect accounts through the app and revoke credentials from the connected WordPress site. Contact the relevant site operator for access to, correction of or deletion of data held by that site. OS backup and secure-storage behavior are controlled by your device and account settings.

The built-in demo uses fictitious sample data and does not require a real WordPress account.

## Contact

For questions about the FluxWP app, use [the project's support page](https://github.com/FluxWP/FluxWP/issues). Do not include passwords, access tokens or private site data in a public issue.

---

## Türkçe

FluxWP, sizin seçtiğiniz WordPress sitelerine bağlanır. Giriş ve içerik işlemleri ilgili siteye gönderilir; sitelerdeki veriler ve saklama süreleri site işletmecisinin kontrolündedir. Mobil uygulamada kimlik bilgileri işletim sisteminin güvenli deposunda, tercihler ve önbellek verileri cihazda tutulur.

Seçtiğiniz dosyalar ve çektiğiniz fotoğraflar ilgili site işleminde kullanılır. Mikrofon kaydı izni gerekmez. Bildirim altyapısı yapılandırılmışsa ve izin verirseniz Expo ile bağlı site, bildirim iletimi için cihaz kaydı ve bildirim belirteci işleyebilir. Masaüstü uygulaması sürüm kontrolü için FluxWP güncelleme hizmetine bağlanabilir.

Hesapları uygulamadan ayırabilir, kimlik bilgilerini WordPress sitenizden iptal edebilirsiniz. Sunucuda tutulan verilerle ilgili talepler için ilgili site işletmecisine başvurun. Uygulama soruları için yukarıdaki destek sayfasını kullanın; herkese açık bildirimlere parola veya özel veri eklemeyin.
