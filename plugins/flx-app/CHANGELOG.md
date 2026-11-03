# Değişiklikler

## 0.3.7
- Koyu mod Flux Admin koyu temasında da çalışıyor; ara bilgi metinleri tema tonunda.

## 0.3.6
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 0.3.5
- Menü logoları Flux Admin'deki logo stiline (Degrade / Pastel) uyar.

## 0.3.4
- Vurgu rengi Flux Admin'den (varsayılan olarak tema rengi; FluxWP'de indigo), sabit yeşil kaldırıldı.

## 0.3.3

- /me `layout`: sol menü düzeni paneldeki gibi (Flux Admin): `{ pins: [slug], groups: [ { id, label, collapsed, items: [slug] } ] }`. Rolün menü düzeni (yoksa varsayılan gruplar), kişinin sabitledikleri ve "Diğer"e taşıdıkları. Flux Admin yoksa alan gönderilmez.
- /me `menu[].icon_url`: FluxWP paketlerinin renkli logosu (Flux Admin ortak logoları, yoksa paketin `assets/icon.svg`'si; filtre `flxapp_menu_icon_url`).

## 0.3.2

- Yeni `GET flxapp/v1/dashboard`: uygulamanın ana sayfası (Başlangıç karşılığı) için kartlar ve listeler. Çekirdek: yazılar, sayfalar, onay bekleyen yorum, ortam, kullanıcılar, güncellemeler, taslaklarım, son yorumlar. WooCommerce: bugünkü siparişler/ciro, ödeme bekleyen, son siparişler. Paketler `flxapp_dashboard` filtresiyle kendi kartlarını ekler (yetkiye göre). Uygulama 0.3.13 kullanır.
- Uygulama içinde sayfa geçişinde çift yükleme çizgisi: Flux Admin'in üstteki ilerleme çubuğu (`#flxa-progress`) gömülü görünümde gizlenir; yalnız uygulamanın kendi çubuğu görünür. Uygulama sürümü gerekmez.

## 0.3.1

- /me `bottom`: telefondaki alt gezinme kısayolları paneldeki mobil alt bar ayarından (Flux Admin, rol bazlı). Uygulama 0.3.8 kullanır.

## 0.3.0

- Sol menüde yeni **Uygulama** sayfası (yöneticiler): uygulamayla ilgili her şey tek yerde. Sekmeler: Genel (giriş, oturum, panel oturumu, kodla giriş, API anahtarı ayarları), Modüller, Menü, Bağlı uygulamalar, Tüm erişimler. Kullanıcılar › Uygulama erişimi artık yalnız kişinin kendi oturumları ve API anahtarları.
- **Modüller:** paketlerin uygulamadaki yerel ekranları listelenir, tek tek kapatılabilir (`modules_off`); kapatılan modülün menüsü uygulamada panel sayfası olarak açılır.
- **Platforma göre menü:** Flux Admin 1.3.0 menü düzenleyicisinde her öğe için Web / Mobil / Masaüstü seçimi (`flxa_menu_platforms` filtresine kaydolur). Uygulama `X-FluxWP-Platform: desktop | mobile` başlığı gönderir; /me menüsü ve modüller o platformda kapalı olanlar çıkarılarak döner. Başlık göndermeyen eski sürümlerde süzme yok. Menü sekmesi platforma göre gizlenen öğeleri rol rol gösterir.

## 0.2.2

- Uygulama içi panel görünümü Flux Admin uygulama modundaki sayfalarda da (Çağrı Merkezi, Görevler, Stüdyo) çalışır: üst çubuk, sol menü ve uygulama geçiş düğmesi gizlenir, sayfa tüm alanı kullanır. 0.2.1'de bu sayfalarda Flux Admin kuralları gizlemeyi eziyordu.

## 0.2.1

- Uygulama izin (OAuth onay) sayfası yeni tasarım: her şey tek kartta. Üstte site logosu (site simgesi; filtre `flxapp_brand_logo`), başlık, hesap satırı (avatar, ad, e-posta, Değiştir), madde madde açıklama, eşit iki düğme, altta Siteye dön · KVKK. WP giriş logosu ve kart dışı bağlantılar gizli.
- Google One Tap (Site Kit vb.) bu sayfada görünmez.
- Uygulama içi panel görünümü: uygulama sayfayı gömülü açtığında (user agent `FLXApp/`) panelin sol menüsü, üst çubuğu, alt barı ve altlığı gizlenir; sayfa tüm alanı kullanır.
- `flxapp_modules`: `url` verilmezse `menu`'den üretilir; yerel ekranı olmayan uygulama sürümü modülü panel sayfası olarak açar.

## 0.2.0

- Bağışçı girişi: e-posta ya da telefona tek kullanımlık kod (/flxapp/v1/otp/start, /otp/verify); telefonda WhatsApp (Flux Chat, gizli gönderim), yoksa e-posta; personel numarası Flux Roles'den.
- Hesabı olmayan ama bağışı olan kişiye müşteri hesabı açılır, eski siparişleri bağlanır (ayar).
- Personel varsayılan olarak kodla giremez; OAuth kullanır.

## 0.1.0

- İlk sürüm: masaüstü uygulaması ve diğer istemciler için giriş altyapısı.
- OAuth 2.0 (yetkilendirme kodu + PKCE S256, dönen yenileme belirteci, iptal), WordPress girişi üzerinden onay ekranı.
- Yerleşik istemci "Flux Masaüstü" (flxmasaustu:// ve 127.0.0.1 yönlendirme).
- MCP istemcileri için RFC 8414/9728 metadata ve dinamik istemci kaydı; Flux SEO & MCP uç noktası OAuth belirteciyle de çalışır.
- Kişisel API anahtarları (Bearer flxak_…), süreli ya da süresiz; Kullanıcılar › Uygulama erişimi.
- Uygulama uçları: /flxapp/v1/me (kullanıcı, rol bazlı menü, modüller, sayaçlar), /counts, /session (tek seferlik panel oturumu), /logout.
- Sistem kontrolü satırları.
