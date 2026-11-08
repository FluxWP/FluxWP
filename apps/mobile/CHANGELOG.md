# Değişiklikler

## 0.3.3
- Keep shared locale selection type-safe across ten languages; use English mobile-only copy when a dedicated translation is unavailable.

## 0.3.2
- Alt pencereler (hesaplar, onay, seçim) iOS'ta düzgün: içerik yerel pencereye `RNHostView` ile bağlanır. Önceden satırlar tıklanmıyordu, metin dar alanda kesiliyordu ve başlık tutamacın altına giriyordu.
- Hesap listesinde boşluklar ve hizalama düzeltildi; uzun liste kayar.

## 0.3.1
- Oturum düşünce hesap silinmeden "Yeniden giriş" çıkar: hesap listesinde her zaman, ölü oturumda ekranın üstünde. Adres hazır gelir. API anahtarı da 401'de aynı yolu kullanır.
- Site simgesi yoksa tema logosu ya da Flux Admin logosu hesap işaretine yazılır. Simgesi olan siteler (Yeşil Türkiye) simgeyi kullanmaya devam eder.

## 0.3.0
- Sayfalar ve yazılar tam ekran düzenleyicide: kalın, liste, alıntı, bağlantı ve görsel. Görsel siteye yüklenir.
- Onay, menü ve hesap pencereleri sistem sayfasıdır. Karartma sayfayla birlikte gelir; tutamaç yarım ve tam yükseklik arasında sürüklenir.
- İlk açılış üç karşılama sayfası: İleri ile geçilir, sonra site adresi gelir. Atla doğrudan girişe iner.
- Diğer iki giriş düzeni duruyor: kart (`/add-site?giris=card`) ve indigo bant (`/add-site?giris=band`).

## 0.2.0
- Kabuk (üst çubuk, yan çekmece, alt sekmeler), listeler, ayrıntı, formlar ve boş durumlar Flux Admin dilinde: antrasit + bej zemin, indigo düğmeler, amber vurgu, Figtree. Hesap açılınca sitenin theme.json yazı tipi ve renkleri geçerli olur.
- Yerel ekranlar (WhatsApp, Görevler, bağış inceleme, yazı, ortam, Social, Studio, AI, TV kumandası) aynı yazı, aralık ve düğmeleri kullanır. Web görünümü çerçevesiz kalır.

## 0.1.1
- Beyaz etiket: `FLUX_BRAND` build anında `brands/<slug>/brand.json` okur (ad, bundle, şema, ikon, splash, tema, kabuk). Ana uygulama `com.fluxwp.app` / `fluxwp` olarak kalır; `allowedSites` boştur.
- Marka derlemesi yalnız listedeki siteleri ekler (`brandGate`). Liste doluysa demo girişi kapalıdır. `lockSite` tek siteyi kilitler; liste dolu ve kilit yoksa kullanıcı listeden seçer.
- EAS profilleri: `production` / `preview` (FluxWP), `prod-<slug>` / `preview-<slug>`. `bun run brand:new`, `brand:build`, `brand:pull`.

## 0.1.0
- İlk sürüm (temel): Expo SDK 58 + expo-router, `apps/mobile` monorepo içinde. Uygulama adı FluxWP, kimlik `com.fluxwp.app`, şema `fluxwp`.
- Çoklu site ve hesap: site adresi → keşif → uygulama içi tarayıcıda OAuth + PKCE (`flx-mobil`, aynı siteye ikinci hesap `prompt=login`), kodla giriş, API anahtarı, Flux App olmayan sitede uygulama şifresi (sınırlı mod). Belirteçler SecureStore'da, hesaplar ve önbellek MMKV'de. Hızlı hesap değiştirici, Hesaplar ve Ayarlar sayfaları.
- Siteden gelen kabuk (`flx-app-config/1`): alt sekmeler (5 yuva), yan menü, üst bar; rozetler `/sync` (yoksa `/counts`).
- Tema: token'lar + marka + site teması + beyaz etiket; açık/koyu (cihaz ya da kullanıcı seçimi).
- Manifest ekranları: liste, detay, form, pano; boş/hata/yükleniyor/çevrimdışı durumları; bilinmeyen tür web görünümüne düşer.
- Web görünümü yedeği: tek seferlik SSO, `FLXApp/` user agent, bağlantı yakalama, düşen oturumu (`auth.login_url`, Flux Speed `/fluxadmin/` dahil) bir kez yeniden açma, köprü mesajları.
- Başlangıç: Flux App `/dashboard` kartları ve listeleri.
- Demo hesabı: sözleşme örnekleriyle sunucusuz çalışır (Kişiler, Yazılar, Yorumlar, örnek panel sayfası).
