# Değişiklikler

## 0.1.0
- İlk sürüm (temel): Expo SDK 58 + expo-router, `apps/mobile` monorepo içinde. Uygulama adı FluxWP, kimlik `com.fluxwp.app`, şema `fluxwp`.
- Çoklu site ve hesap: site adresi → keşif → uygulama içi tarayıcıda OAuth + PKCE (`flx-mobil`, aynı siteye ikinci hesap `prompt=login`), kodla giriş, API anahtarı, Flux App olmayan sitede uygulama şifresi (sınırlı mod). Belirteçler SecureStore'da, hesaplar ve önbellek MMKV'de. Hızlı hesap değiştirici, Hesaplar ve Ayarlar sayfaları.
- Siteden gelen kabuk (`flx-app-config/1`): alt sekmeler (5 yuva), yan menü, üst bar; rozetler `/sync` (yoksa `/counts`).
- Tema: token'lar + marka + site teması + beyaz etiket; açık/koyu (cihaz ya da kullanıcı seçimi).
- Manifest ekranları: liste, detay, form, pano; boş/hata/yükleniyor/çevrimdışı durumları; bilinmeyen tür web görünümüne düşer.
- Web görünümü yedeği: tek seferlik SSO, `FLXApp/` user agent, bağlantı yakalama, düşen oturumu (`auth.login_url`, Flux Speed `/fluxadmin/` dahil) bir kez yeniden açma, köprü mesajları.
- Başlangıç: Flux App `/dashboard` kartları ve listeleri.
- Demo hesabı: sözleşme örnekleriyle sunucusuz çalışır (Kişiler, Yazılar, Yorumlar, örnek panel sayfası).
