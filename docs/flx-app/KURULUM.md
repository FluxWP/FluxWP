# Flux App: kurulum

FluxWP masaüstü ve mobil uygulamasının, MCP istemcilerinin siteye "Giriş yap" ile bağlanmasını; uygulamadaki menü, tema ve eklenti ekranlarını sağlar.

1. Güncellemeler'den **Flux App**'i kurup etkinleştirin (ya da zip'i Eklentiler › Yeni ekle › Yükle). 0.4.0 güncellemesi cihaz tablosunu kendiliğinden ekler.
2. Ayar gerekmez; varsayılanlar: erişim 60 dk, oturum 60 gün, ana FluxWP uygulaması açık, push açık, panel oturumu açık.
3. Masaüstü ya da mobil uygulamada site adresini (https://example.org) girip **Giriş yap**'a basın: tarayıcı açılır (Flux Speed özel giriş adresi `/fluxadmin` dahil), WordPress'e girip **İzin ver** deyin, uygulamaya dönersiniz.
4. **Uygulama › Görünüm:** renkler (boşsa tema ve Flux Admin renginden), mobil alt bar ve yan menü, masaüstü ⌘K kısayolları, ekranları aç/kapa. Sağda iki platform önizlemesi.
5. **Uygulama › Uygulamalar:** mağaza linkleri, bildirimler, markalı uygulama istemcileri (genelde preset doldurur), ana uygulamayı kapatma, geliştirici modu (yalnız test sitesinde).
6. MCP (Claude vb.): istemciye `https://example.org/wp-json/flx-mcp/v1/mcp` verin ya da Uygulama erişimi › API anahtarları › Anahtar oluştur.
7. Kişiler cihazlarını ve oturumlarını **Kullanıcılar › Uygulama erişimi**'nde görür, kapatır (yönetici: Tüm erişimler).

Notlar
- Push için ayrıca anahtar gerekmez (Expo). Expo hesabında gelişmiş güvenlik açıksa Uygulamalar sekmesine erişim belirteci girin. Masaüstü uygulaması bildirimleri açıkken eşitlemeyle gösterir.
- Hostinger/LiteSpeed `Authorization` başlığını iletmezse uygulama `X-FluxWP-Token`'a düşer. LiteSpeed `.well-known/oauth-*` adreslerini önbelleğe alırsa Purge All.
- Parola sıfırlanınca, kullanıcı silinince ya da cihaz kaldırılınca o oturumlar, webview oturumları ve push belirteçleri kapanır.
- Test kapsamı: yerel WordPress 7.1 + SQLite + WooCommerce; OAuth (mobil deep link, masaüstü loopback, `/fluxadmin` arkasında), uçlar ve şema doğrulaması gerçek isteklerle; push yalnız sahte Expo sunucusuyla denendi (gerçek cihazda Ahmet).
- Ayrıntılı uçlar: API.md.
