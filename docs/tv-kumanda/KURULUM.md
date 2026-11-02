# Flux TV Remote: kurulum

Google/Android TV'yi sitenin panelinden ya da telefondan kumanda gibi kontrol eder. Hobi/test eklentisidir, istenince kaldırılır.

## Nasıl çalışır
- Site (Hostinger) evdeki TV'ye ulaşamaz, tarayıcı da TV protokolünü (Android TV Remote v2: TLS soketi, port 6466/6467) konuşamaz.
- Bu yüzden TV ile aynı Wi-Fi'da açık duran bir bilgisayarda (şimdilik Mac, sonra Raspberry Pi) küçük **köprü** programı çalışır.
- İki yol:
  1. **Hızlı yerel sayfa:** köprünün kendi adresi `http://<bilgisayar-ip>:8765/?k=…` (aynı Wi-Fi, en hızlı).
  2. **Site paneli:** *TV Kumandası* menüsü. Köprü siteye sorar, komutları alır (≈1 sn gecikme).
- **Yalnız aynı ağ** (varsayılan açık): site kumandası yalnız köprüyle aynı internet bağlantısından gelen isteği kabul eder. Mobil veriden ya da başka ağdan komut gitmez.

## 1. Eklentiyi kur
Güncellemeler'den ya da `flx-tv-remote.zip` ile kurun ve etkinleştirin. Menü: **TV Kumandası** (yalnız yöneticiler).

## 2. Köprüyü kur (evdeki bilgisayar)
1. Node.js 22+ kurun: https://nodejs.org (LTS).
2. **TV Kumandası › Ayarlar › 3. TV ve uygulamalar**: TV adı, IP (`192.168.1.104`), MAC (isteğe bağlı) yazıp kaydedin.
3. **1. Köprüyü kur › Köprüyü indir (zip)**. Kutucuk işaretliyse site adresi ve yeni köprü anahtarı pakete konur (eski anahtar geçersiz olur).
4. Zip'i açın. Mac: `baslat.command` dosyasına çift tıklayın (ilk seferde sağ tık › Aç). Diğer sistemler: klasörde `npm install`, sonra `node kopru.mjs`.
5. Terminalde yazan **Kumanda (aynı Wi-Fi)** adresini telefonda açın.
6. **Cihaz** (sol alt) › **Eşleştirmeyi başlat** › TV ekranındaki 6 haneli kodu girin › **Eşleştir**.

Köprü ayarları ve TV sertifikası: `~/.flx-tv-kopru/config.json`. Bilgisayar her açıldığında başlasın: Mac'te Sistem Ayarları › Genel › Giriş Öğeleri'ne `baslat.command` ekleyin.

## Özellikler
Güç (TV ağdan düşmüşse Wake-on-LAN), TV ayarları, kanal ±, arama, sessiz, ses ± (basılı tutunca tekrar), uygulamalar (listeden açma + TV'de tüm uygulamalar), 123 (rakamlar), klavye (TV'de metin alanı açıkken yazı), dokunmatik alan (kaydır = yön, dokun = Tamam, basılı tut = seçenekler), yön okları, geri, ana sayfa, medya (geri sar, durdur, duraklat, oynat, ileri sar), diğer tuşlar (giriş kaynağı, rehber, bilgi, menü, altyazı, ses dili, önceki/sonraki, oynat/duraklat, asistan, uyku, renk tuşları). Masaüstünde klavye kısayolları. Ses seviyesi ve açık uygulama TV'den okunur.

Tam ekran: Kumanda sayfasında sağ üstteki **Tam ekran** ya da Ayarlar'daki düğme. Telefonda "Ana ekrana ekle" ile uygulama gibi açılır.

## Kimler kullanır
Yönetici her zaman. Personel, İdareci ve Yönetici rolleri (Flux Roles) `flxtv_use` yetkisini varsayılan olarak alır; Kullanıcılar › Roller ve erişim › **TV Kumandası**'ndan açılıp kapatılır. Personel yalnız tuş, uygulama, yazı ve güç kullanır; TV adresi, eşleştirme ve Ayarlar yöneticide.

## Uygulama listesi
Ayarlar'da her satır: `Ad | paket adı ya da link | #renk`. Paket adı Google Play adresindeki `id=` kısmıdır (ör. `com.netflix.ninja`). Liste köprüye de gider.

## Sorun giderme
- **Köprü çevrimdışı:** bilgisayar uyuyor ya da köprü kapalı. Terminal penceresine bakın.
- **Aynı Wi-Fi'da değilsiniz:** telefon mobil veride. Wi-Fi'ı açın, birkaç saniyede kendiliğinden bağlanır. Gerekirse Ayarlar › Erişim'den kapatılabilir (önerilmez).
- **TV'ye ulaşılamıyor:** TV kapalı ya da IP değişti. Modemden TV'ye sabit IP verin.
- **TV eşleştirmeyi reddetti:** Cihaz › Yeniden eşleştir.
- Eski sayfa görünüyorsa LiteSpeed Cache › Purge All.

## Kaldırma
Eklentiyi silince ayarlar, kuyruk ve köprü durumu silinir. Köprünün `~/.flx-tv-kopru` klasörünü elle silin. TV'de eşleşmeyi Ayarlar › Uzaktan kumandalar'dan kaldırabilirsiniz.
