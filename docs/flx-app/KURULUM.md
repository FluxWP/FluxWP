# Flux App: kurulum

Masaüstü uygulamasının (Flux Masaüstü) ve MCP istemcilerinin siteye "Giriş yap" ile bağlanmasını sağlar.

1. Güncellemeler'den **Flux App**'yı kurup etkinleştirin (ya da zip'i Eklentiler › Yeni ekle › Yükle).
2. **Kullanıcılar › Uygulama erişimi**'ni açın. Ayar gerekmez; varsayılanlar: erişim 60 dk, oturum 60 gün, MCP kendini kaydedebilir, panel oturumu açık.
3. Masaüstü uygulamasında site adresini (https://example.org) girip **Giriş yap**'a basın: tarayıcı açılır, WordPress'e girip **İzin ver** deyin, uygulamaya dönersiniz.
4. MCP (Claude vb.) için iki yol:
   - OAuth: istemciye `https://example.org/wp-json/flx-mcp/v1/mcp` adresini verin; giriş ve izin ekranı açılır.
   - Anahtar: Uygulama erişimi › API anahtarları › Anahtar oluştur; istemcide `Authorization: Bearer flxak_…`.
5. Bağışçılar mobil uygulamada **Bağışçı girişi** ile e-posta ya da telefonlarına gelen kodla girer (ayar: Kodla giriş, varsayılan açık). Telefona kod için Flux Chat (WhatsApp) bağlı olmalı; yoksa kod e-postaya gider.
6. Erişimleri aynı sayfadan görür, kaldırırsınız (yönetici: Tüm erişimler sekmesi).

Notlar
- Hostinger/LiteSpeed `Authorization` başlığını iletmezse uygulama `X-FluxWP-Token` başlığına düşer; ek ayar gerekmez.
- `.well-known/oauth-*` adresleri WordPress üzerinden yanıtlanır. LiteSpeed önbelleği bu adresleri önbelleğe alırsa Purge All yapın.
- Parola sıfırlanınca ya da kullanıcı silinince o kişinin tüm uygulama oturumları ve anahtarları kapanır.
- Ayrıntılı uçlar: API.md.
