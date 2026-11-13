# FluxWP uygulamalarını yayımlama

GitHub **Actions → App distribution** ekranından iOS, Android ve macOS hedefleri aynı çalıştırmada seçilebilir. Bir platformu atlamak için `skip` seçin. Her platformun ayrıca bağımsız iş akışı vardır. Sırlar iş günlüklerine veya depoya yazılmaz; imzalama dosyaları geçici dizinde oluşturulur ve iş sonunda silinir.

| İş akışı | Hedef | Sonuç |
|---|---|---|
| iOS - IPA, TestFlight or App Store | `simulator` | İmzasız, gerçek yerel iOS simülatör uygulaması; mağazaya yüklenmez |
| Aynı iş akışı | `ipa` | Apple Distribution ile imzalı IPA; TestFlight'a yüklenmez |
| Aynı iş akışı | `testflight` | İmzalı IPA, App Store Connect'e yüklenir ve Apple işlemesi beklenir |
| Aynı iş akışı | `app-store` | Önceden test edilen build, açıklamalar ve gerçek ekran görüntüleriyle Apple incelemesine gönderilir |
| Android - signed APK or Google Play | `apk` | Yükleme anahtarıyla imzalı APK, indirilebilir Actions dosyası |
| Aynı iş akışı | `internal`, `alpha`, `beta`, `production` | İmzalı AAB, seçilen Play kanalına yüklenir; varsayılan yayın durumu `draft` |
| Flux Desktop (macOS + Windows) | `macos` | Universal DMG, notarization ve imzalı otomatik güncelleme paketi |
| App store assets - native iPhone and iPad | marka | Yerel iPhone/iPad simülatörlerinde Türkçe/İngilizce gerçek uygulama ekranları |

## İlk iOS yayını

1. `testflight` hedefini çalıştırın. İlk sayı otomatik belirlenir; sonraki build numarası App Store Connect'teki en son numaradan büyüktür. İstenirse daha büyük bir başlangıç numarası verilebilir.
2. App Store Connect → FluxWP → TestFlight altında `FluxWP Internal` grubuna kendi takım kullanıcınızı bir kez ekleyin. İş akışı sonraki build’leri `<marka adı> Internal` grubuna otomatik bağlar; diğer markalar için bu dahili grup önceden oluşturulmalıdır. Başkalarını davet etmek ayrı bir adımdır. Yüklemenin tamamlanması, gruplara dağıtıldığı veya App Store'da yayımlandığı anlamına gelmez.
3. Aynı commit'te **App store assets** çalıştırın. Yerleşik kurgusal demo verisiyle gerçek yerel ekranlar alınır; kişisel WordPress hesabı kullanılmaz. İndirilen görselleri inceleyin. `tools/fluxapp/screenshots.ts` içindeki web önizlemeleri bu iş için kullanılmaz.
4. App Store Connect'te uygulama gizliliği, yaş derecelendirmesi, içerik hakları, inceleme iletişim bilgileri, gerekirse demo giriş bilgileri, fiyat/ülkeler ve AB tacir durumu gibi gerçek işletme bilgilerini tamamlayın. Gizlilik metni `docs/fluxapp/APP-PRIVACY.md` içindedir; kullanılan sunucuların gerçek uygulamasıyla karşılaştırın. Mağaza formundaki veri güvenliği beyanını bu metinden otomatik tahmin etmeyin.
5. Mağazada hazırlanmış tanıtım görselleri varsayılan olarak korunur. Ham yerel çekimlerle değiştirmek isterseniz `replace_store_screenshots` seçeneğini açıkça etkinleştirin; bu seçim mevcut ekran görsellerini değiştirir. Yeni uygulamada henüz görsel yoksa önce hazırlanan tasarımları yükleyin veya bu seçeneği kullanın.

   Yalnız ekran görüntülerini değiştirmek için iOS iş akışında `store-screenshots` hedefini seçin ve aynı commit'teki başarılı **App store assets** çalışmasının run ID'sini girin. Düzenlenebilir App Store sürümünün (yoksa paket sürümüyle açılır) görselleri değişir; build, metadata ve Apple incelemesine gönderim yapılmaz.
6. `app-store` hedefini seçin. Test edilen **mevcut build numarasını**, başarılı ekran görüntüsü çalışmasının sayısal **run ID** değerini ve aynı mağaza sürümünü girin. Görsel çalışmasının commit'i gönderimin commit'iyle eşleşmek zorundadır. Başka markaların metadata'sı hazırlanmadıkça mağaza gönderimi yalnız `flux` için açıktır.
6. `disclosures_reviewed` ancak yukarıdaki beyanlar gerçekten tamamlanınca seçilmelidir. `release_on_approval=false` Apple onayı sonrasında elle yayımlamayı bekler; `true` otomatik yayımlamayı seçer. Apple inceleme sonucunu belirler.

Türkçe ve İngilizce mağaza açıklamaları `tools/release/store/ios/` içindedir. Bir sürümün ekran görüntüleri ve TestFlight build'i aynı koddan hazırlanmalıdır; yalnız commit eşleşmesi, kullanıcının doğru TestFlight build'ini seçtiğini kanıtlamaz.

## Android

`apk` hedefi Play hizmet hesabı gerektirmez. Play yüklemesi için Google Play Console geliştirici hesabının ve uygulama kaydının hazır olması gerekir. Google Play Android Developer API açık olan hizmet hesabına yalnız gerekli uygulama erişimini verin; JSON anahtarını `GOOGLE_PLAY_SERVICE_ACCOUNT_JSON` deposal sırrına kaydedin. Yeni uygulamanın ilk AAB yüklemesi Console üzerinden elle gerekebilir. İlk sürümü dahili/kapalı testte doğrulayın; yeni kişisel hesapların üretim erişimi için Console'da gösterilen test/doğrulama koşulları ayrıca tamamlanmalıdır.

Bağımsız Android iş akışında `release_status=draft` kaydedilmiş taslak oluşturur; `completed` seçilen kanalda yayımlamayı talep eder. `production` seçimi tek başına Google'ın incelemesini veya hesap gerekliliklerini aşmaz. Sürüm kodu UTC zamandan artan bir sayı olarak üretilir. Üretim için önceki koddan büyük olmalıdır. Mevcut uygulamanın başka imza anahtarı varsa yeni anahtarı kullanmayın; mevcut yükleme anahtarını geri yükleyin. Play App Signing etkinse burada kullanılan anahtar yükleme anahtarıdır.

## macOS

Artifact denemesi için release tag'ini boş bırakın. Halka açık yayın için `apps/desktop/package.json` sürümüyle aynı `fluxapp-v<version>` tag'ini girin. Tag oluşturulması da masaüstü iş akışını tetikler. Normal sayısal sürümler kararlı release, `-alpha`, `-beta`, `-rc` etiketleri ön sürüm olarak açılır.

Halka açık macOS paketi Apple Developer ID imzası, notarization bileti, Gatekeeper kontrolü ve Tauri güncelleme imzası olmadan yayımlanmaz. İmzasız geliştirme paketi ancak `require_signing=false` ve release tag'i boşken üretilebilir. Bu hedef doğrudan DMG dağıtımıdır; Mac App Store gönderimi ayrı ürün/entitlement değerlendirmesi gerektirir.

Yayın sonrası mevcut güncelleme sunucusunun önbelleği imzalı webhook ile yenilenir (`UPDATE_SERVER`, `UPDATE_WEBHOOK_SECRET`). Gerekirse **Refresh desktop update feed** elle çalıştırılabilir. Sürüm GitHub üzerinde hazır olsa bile sunucunun marka kaydı yapılandırılmamışsa otomatik güncelleme sunulmaz; `/v1/fluxapp/<brand>/latest` ayrıca kontrol edilmelidir.

## Erişim ayarları

| GitHub Secrets | Kullanım |
|---|---|
| `APPLE_API_KEY`, `APPLE_API_ISSUER`, `APPLE_API_KEY_P8` | App Store Connect API ve macOS notarization |
| `IOS_DISTRIBUTION_CERTIFICATE`, `IOS_DISTRIBUTION_CERTIFICATE_PASSWORD`, `IOS_PROVISIONING_PROFILE` | iOS Distribution P12/base64 ve App Store profili/base64 |
| `APPLE_CERTIFICATE`, `APPLE_CERTIFICATE_PASSWORD`, `APPLE_SIGNING_IDENTITY` | macOS Developer ID Application P12/base64 ve tam kimlik adı |
| `TAURI_SIGNING_PRIVATE_KEY` | Masaüstü güncelleme imzası; `apps/desktop/updater.pub` ile aynı anahtar çifti |
| `ANDROID_KEYSTORE`, `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD` | PKCS12/base64 Android yükleme anahtarı |
| `GOOGLE_PLAY_SERVICE_ACCOUNT_JSON` | Play kanallarına yükleme; APK için gerekmez |

GitHub Variables: `APPLE_TEAM_ID`; kayıt referansı için `ASC_APP_ID`. Expo bildirim altyapısı ayrıca hazırlanırsa `EXPO_PUBLIC_EAS_PROJECT_ID` verilebilir. EAS projesi yokken uygulama Expo push kaydını başlatmaz. Apple hesabı, provisioning profile, P12 ve takım kimliği birlikte değişmelidir. Bireysel hesapla yayımlanan sürümde mağazanın satıcı adı bireysel hesaba aittir; şirket üyeliği ayrıca tamamlanmalıdır.

Özel anahtarlar ve parolaları şifreli parola yöneticisinde yedekleyin. P12/JSON/P8 dosyalarını, gerçek site token'larını veya `.env` sırlarını bu depoya koymayın. Mevcut sertifikaları iptal etmek bu akışın parçası değildir. App Manager API anahtarı takım düzeyinde yetkilidir; yalnız FluxWP'ye özel olduğu varsayılmamalıdır.

## Doğrulama

Yerelde `node --test tools/release/*.test.mjs`, mobil/shared typecheck ve mevcut shared testler çalıştırılır. İş akışları derlemeyi tamamlamadan dosya yüklemez. Android APK/AAB imzası, macOS imza/notarization ve iOS profilinin takım/bundle/son kullanma tarihi doğrulanır. Apple/Google yüklemesinin başarılı olması mağazada herkese açık yayın anlamına gelmez; gerçek durum ilgili Console üzerinden kontrol edilmelidir.

API doğrulaması ve derlemeler için [Fastlane belgeleri](https://docs.fastlane.tools/), [App Store Connect yardımı](https://developer.apple.com/help/app-store-connect/) ve [Google Play Console yardımı](https://support.google.com/googleplay/android-developer/) esas alınır.

Android hesabı doğrulanmayı beklerken `aab` hedefiyle imzalı Play paketini hazırlayabilirsiniz. Bu hedef mağazaya yükleme yapmaz ve Google Play erişim anahtarı istemez; oluşturulan `.aab` ve SHA-256 dosyası çalışma çıktısından indirilir.

Tamamlanmış yerel çekimleri yeniden paketlemek için **App store assets** içindeki `capture_run_id` alanına başarılı çekim çalışmasının numarasını girin. Bu seçenek cihazları yeniden başlatmaz. Her görselin özeti, cihaz/dil paketi ve yerel uygulama girdilerinin özeti tekrar kontrol edilir; uygulama kodu, bağımlılıklar veya marka değişmişse eski çekimler kullanılamaz.
