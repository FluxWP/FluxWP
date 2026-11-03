# Flux Admin 1.0.0: kurulum

Yönetim panelini baştan tasarlayan eklenti. Kapatınca WordPress'in standart paneli geri gelir; içerik, yetki ve diğer eklentilerin verisi değişmez.

## Kurulum
1. **Eklentiler › Yeni ekle › Eklenti yükle** → `flx-admin.zip` → Etkinleştir.
   (Repo'ya `main` push'u sonrası FluxWP Panel'den de gelir.)
2. **Ayarlar › Yönetim teması** açılır. Varsayılanlar hazır; bir şey girmeniz gerekmez.

## İlk ayarlar (isteğe bağlı)
- **Genel**: ad, alt satır, logo, vurgu rengi; varsayılan tema (otomatik/açık/koyu); canlı sayaç aralığı (Hostinger için 60 sn önerilir).
- **Menü ve alt bar**: rol seçin → sürükleyip sıralayın, yeniden adlandırın, gizleyin, grup ekleyin; sağda önizleme. Altta mobil alt barın 4 kısayolu. Kaydet. Gizlemek yetkiyi değiştirmez, yalnız menüden kaldırır.
- **Başlangıç**: özet kutucukları ve ek hızlı işlemler (`Etiket|adres`). Modüllerin yerini herkes Başlangıç'ta "Modülleri düzenle" ile kendi ayarlar.
- **Uygulama (PWA)**: açık gelir. Site HTTPS olduğu için telefonda "Ana Ekrana Ekle", bilgisayarda adres çubuğundaki yükle simgesi çalışır.
- **Sistem kontrolü**: Flux Admin satırları; diğer FluxWP paketleriyle ortak sözleşme (`flx_system_checks` / `flx_system_fixes`).

## Neler var
- Gruplu sol menü (Genel, Uygulamalar, Bağış, İçerik, Site, Diğer), çizgi simgeler, daraltılınca simge şeridi.
- Menünün üstünde Panel / WhatsApp / Görevler geçişi (okunmamış rozetli); Çağrı Merkezi ve Görev ekranlarında sol kenarda küçük geçiş düğmesi (Alt+1..9). O ekranların kendi arayüzüne dokunulmaz.
- Mobilde alt bar + soldan açılan yan panel.
- Otomatik açık/koyu tema; herkes sol alttaki düğmeden kendi tercihini seçer.
- Komut paleti: Ctrl+K / ⌘K (menü, uygulamalar, işlemler, içerik araması).
- Canlı rozetler WordPress Heartbeat'e eklenir; ayrı istek açılmaz.

## Bilmeniz gerekenler
- Koyu tema blok editöründe ve WooCommerce Yönetim ekranlarında kapalıdır (onların kendi stilleri var). Liste Genel sekmesinde düzenlenir.
- LiteSpeed/Hostinger önbelleği admin'i etkilemez; PWA yalnız CSS/JS/font/görselleri önbelleğe alır, panel sayfalarını almaz.
- Eklentiyi kaldırmadan önce PWA'yı kapatın (Ayarlar › Yönetim teması › Uygulama). Böylece yüklü uygulamalardaki service worker kendini siler.
- Kaldırınca (Sil) ayarlar, menü düzenleri ve kişisel tema tercihleri silinir.

## Denendi / denenmedi
- Denendi (WP 6.4 test sitesi, Flux Çağrı + Flux Tasks açık): masaüstü açık/koyu, daraltılmış ve 783–960 px menü, telefon alt bar ve yan panel, editör rolü için özel menü kaydı, komut paleti, Başlangıç modülleri, Heartbeat sayaçları, manifest/service worker uçları.
- Canlıda denenmedi: Flux Donate verisiyle bağış kutucukları ve grafik, gerçek telefona PWA yükleme, WooCommerce ekranları.

Ekran görüntüleri: `ekran-goruntuleri/`.
