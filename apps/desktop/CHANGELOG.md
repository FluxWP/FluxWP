# Değişiklikler

## 0.3.1
- Content-Security-Policy is set for the shell (`script-src 'self'`, no `unsafe-eval`). The dev server keeps a separate policy so Vite HMR can connect.
- The OS credential store is granted only to the main window. Separate wp-admin windows do not get those commands.
- The embedded webview may navigate only on the origin it was opened for (`about:blank` included). Other http(s) links open in the system browser; every other scheme is blocked.

## 0.3.0
- Panel artık mobil uygulamayla aynı `@fluxwp/runtime` çekirdeğini kullanır. Kenar çubuğu ve üst çubuk `flxapp/v1/config` cevabından gelir (`X-FluxWP-Platform: desktop`); config yoksa eskisi gibi `/me` menüsü durur.
- Tema hesap başına sitenin WordPress temasından (ve override'lardan) gelir. Hesap değiştirince kabuk ve tema birlikte değişir.
- Giriş: masaüstü loopback OAuth istemcisi `flx-masaustu`. Aynı siteye ikinci hesapta `prompt=login`. Kodla giriş ve uygulama şifresi de açılır.
- Yeni `@fluxwp/render-web`: manifest listeleri 1100 px ve üzerinde tablo + master-detail, sanal kaydırma; form, detay ve aksiyonlar.
- ⌘K komut paleti kabuk komutlarını ve manifest aramasını tarar. `/sync` olayları masaüstü bildirimine düşer. Menü tercihleri `/prefs` ile senkron olur.
- Gömülü wp-admin: SSO, gezinti yakalama ve köprü mesajı runtime kararını kullanır. Tauri çocuk webview `FLXApp/… (desktop; …)` user agent'ı ile açılır.
- wp-admin içine manifest ekranı: `FluxModules.mountManifest`.

## 0.2.5
- Windows sürümü: GitHub Actions macOS'la birlikte Windows'ta da derler; release'te `…-windows-x64-setup.exe` (NSIS) ve `.msi`. İmzalı güncelleme paketi varsa Windows'ta da arka planda kurulur, yoksa setup.exe açılır.
- Windows'ta yerel başlık çubuğu: macOS trafik ışıkları için bırakılan sol boşluk yalnız macOS'ta. Arama kısayolu Windows'ta "Ctrl K" yazar.
- Uygulama ikonu: FluxWP#31'deki büyük iç işaret oranı (%72,5). Windows .ico ayrı üretilir, kare neredeyse tam dolar (görev çubuğunda küçük görünmez).
- İmza secret'ları (macOS Developer ID + notarize, Windows Azure Trusted Signing ya da .pfx) varsa kullanılır, yoksa imzasız derlenir. Liste: `docs/fluxapp/README.md`.

## 0.2.4
- Sol menüdeki sabit kutucuklar WordPress'teki Flux Admin menüsü gibi davranır: açık sayfanın alt menüsü varsa o kutucuk tam genişliğe çıkıp en alta kayar, alt menüsü aşağı doğru açılır. Başka kutucuğa geçince eskisi yerine döner, yenisi aşağı kayar (animasyonlu).
- Yerel ekranların alt sayfaları (ör. Görevler › Ayarlar) web panelinde açılır.
- Flux markasının geçici logosu yerine FluxWP logosu (B · Modül): uygulama ikonu, giriş ve sol menü. Koyu temada ayrı logo (`brand.logoDark`).
- Marka rengi FluxWP indigo (#4338CA); Görevler ekranı da marka rengini alır.
- brand.mjs SVG meta verisini (c2pa) temizler; aksi halde `tauri icon` FluxWP ikonunu ayrıştıramıyordu.

## 0.2.3
- Görevler ekranı baştan yazıldı: WordPress'teki Flux Tasks arayüzünün aynısı (aynı CSS, aynı REST `flx-tasks/v1`). Ortak React ekranı (`packages/modules/src/tasks`), WP gömmesinde de kullanılabilir.
  - Sol menü: Benim görevlerim, Bildirimler, proje / klasör / liste ağacı (genişlik ayarı, gizle).
  - Görünüm sekmeleri (Liste, Pano, Takvim; ekle, adlandır, paylaş, sil), filtre, grup, sıralama, alt görevler, alanlar, toplu işlem.
  - Sürükle-bırak (@dnd-kit/core): liste sırası ve alt göreve taşıma, pano sütunları, takvimde tarih değiştirme.
  - Görev penceresi: başlık, durum, atananlar, tarihler, öncelik, etiketler, özel alanlar, açıklama düzenleyici (kontrol listesi, görsel yapıştır), alt görev tablosu, ekler, yorumlar (@bahsetme, dosya), etkinlik, yapay zekâ.
  - Toplantı listeleri: gündem (sürükle sırala), kararlar, PDF rapor.
  - Metinler WP eklentisinin çevirilerinden (TR/EN, `tools/fluxapp/flxg-i18n.mjs`).

## 0.2.2
- Otomatik güncelleme: açılışta ve 6 saatte bir güncelleme sunucusuna (`brand.updateUrl`, wp-update-server 1.4.0 `/v1/fluxapp/<marka>/…`) bakılır. İmzalı paket varsa arka planda indirilir, sol altta "Yeniden başlat ve güncelle" çıkar (Tauri updater). İmzalı paket yoksa yeni sürümün dmg'si açılır. Ayarlar › Hakkında'da elle denetleme.
- Release dosyaları marka adıyla: `fluxapp-<marka>-<sürüm>-macos-universal.dmg` (+ `.app.tar.gz`, `.sig`); sürüm notu CHANGELOG'dan.

## 0.2.1
- Sol menü: sağ kenardan sürüklenerek genişlik (200–420 px, çift tık sıfırlar), menüyü gizle/göster (⌘\\), geri/ileri (⌘[ / ⌘]) trafik ışıklarının yanında.
- Sol üstte markanın tam logosu (brand logo).
- Alt menülü öğelerde açılır/kapanır ok + hafif animasyon; etkin öğe kendiliğinden açılır.
- Sol altta hesap kartı (avatar, ad, rol · site) ve yukarı açılan hesap menüsü (profil, siteyi aç, ayarlar, hesap değiştir/ekle, çıkış). "Menüyü düzenle" düğmesi kaldırıldı, altyapısı duruyor.
- İkonlar: dashicons eşlemesi genişletildi; ikonu CSS ile çizilen ("none") FluxWP ve WP menüleri slug'dan ikon alır; http/data: ikonlar resim olarak çizilir.
- Native kaydırma: belge kaymaz, lastik efekti yok, yalnız iç paneller kayar.
- Panel görünümü: üst bar kaldırıldı, sayfa alanı tam doldurur (⌘R yeniler).
- Uygulama ikonu: macOS ızgarasında beyaz yuvarlak kare + marka ikonu (`brand.appIcon`, `tools/fluxapp/brand.mjs` üretir).

## 0.2.0 (taslak)
- Yerel modül ekranları (sunucuda `flxapp_modules` kaydı varsa menü bu ekranla açılır):
  - **Onay bekleyenler** (Flux Donate `flxb/v1/app`): liste, dekont önizleme/büyütme, onayla (sertifika sayısı), iptal (neden + bağışçıya bildir).
  - **Görevler** (Flux Tasks `flx-tasks/v1/app`): Bana atanan / Bugün / Gecikmiş, projeler, arama, hızlı ekle, tamamla, detay paneli (açıklama, kontrol listesi, yorum).
  - **WhatsApp** (Flux Chat tam REST `flx-chat/v1`, WP admin'deki ekranla aynı veri): bootstrap + sync (rev penceresi 15 sn, uyarlamalı aralık: gizli sekmede 60 sn, asistan çalışırken 3 sn, hatada geri çekilme), filtre sekmeleri + etiket çipleri + arama + sonsuz kaydırma, sabitlenen üstte, yazıyor/asistan yazıyor; tüm mesaj türleri (metin WhatsApp biçimi, görsel + büyütme, video, ses çalar 1-1.5-2×, belge, konum, kişi kartı, silinmiş), gün ayracı, 5 dk gruplama, sistem satırı, dahili not, alıntı (tıkla → orijinale atla), tepkiler, link kartı, dekont kontrol rozeti, tikler, başarısız + tekrar dene; yazma alanı (ek seç/yapıştır/sürükle, not modu, "/" hazır cevap, AI öneri, sohbet başına taslak, yazıyor sinyali); başlıkta asistan, ata, sabitle, kapat/aç; ses kaydı (ogg/opus > webm > mp4, 0.7 sn altı atılır), emoji seçici, kişi paneli (ad, etiket, atama, 900 ms otomatik kaydedilen not, eklenti bölümleri + metne ekle), yeni sohbet (canlı numara kontrolü), gelen mesajda ses + masaüstü bildirimi; mobil liste/sohbet görünümü.
- Ortak katman: `@fluxwp/ui` (bileşenler + CSS), `@fluxwp/modules` (modül ekranları + host sözleşmesi), `apps/wp-embed` (aynı ekranları WP admin'de açan bundle, çerez + nonce, `.flux-root` önekli CSS), `tools/fluxapp/embed-sync.sh`.
- Menüsüz modüller (ör. Onay bekleyenler) Başlangıç'ın hemen altına yerleşir.
- `apps/mobile` çıkarıldı: Expo uygulaması ayrı repoda olacak.

## 0.1.0 (taslak)
- Monorepo (Bun workspaces + turbo): `packages/core`, `packages/tokens`, `packages/panel`, `apps/desktop`.
- Marka sistemi: `brands/<slug>/brand.json` (ad, logo, renk, bundle id, şema, `defaultSite` + `lockSite`, `loginMethods`). Örnek Vakfı markasında yalnız tarayıcı girişi. `flux` (genel) ve white-label markalar (siteye kilitli).
- core: REST istemcisi (`_locale=user`, 401'de belirteç yenileme, `X-FluxWP-Token` yedeği), Flux App OAuth 2.0 + PKCE, API anahtarı, site keşfi, çoklu site/hesap deposu, menü/kart düzeni birleştirme, demo hesabı, i18n (TR + EN).
- panel (React): site ekleme ve giriş, hesap değiştirici, rol bazlı menü, menü düzenleme (sırala/gizle), ⌘K komut paleti, Başlangıç dashboard'u (kartları sırala/gizle), yerel ekranı olmayan menüler için panel görünümü, ayarlar (tema, dil, hesaplar).
- desktop (Tauri 2, macOS): Keychain'de belirteç, 127.0.0.1 loopback OAuth, CORS'suz HTTP, panel sayfaları ayrı pencerede (`FLXApp/` user agent ile menüsüz görünüm).
