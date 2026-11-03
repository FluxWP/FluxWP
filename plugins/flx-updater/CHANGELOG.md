# Changelog

## 1.7.1
- Güncellemeler sayfasında toplu işlemler: kurulu paketleri seçip topluca Etkinleştir, Devre dışı bırak ya da Kaldır (dosyaları silinir, önce onay sorulur). Kurulu ve güncel paketler de artık seçilebilir; "Seçilenleri kur / güncelle" yalnız kurulmamış ve güncellemesi olanları alır.
- Kurulu ama etkin olmayan eklentinin sürümünün altında "Devre dışı" yazar.
- Flux Updater kendini kapatmaz/silmez; etkin tema silinmez.

## 1.7.0
- Sunucu panelinden anahtar verilebilir: site her sunucuya özel rastgele bir kurulum kimliği gönderir; yönetici "Bağlı siteler"de "Anahtar ver" deyince anahtar bir sonraki kontrolde yalnız bu kimliği taşıyan siteye teslim edilir ve kendiliğinden kaydedilir. Elle anahtar girmek gerekmez.
- Anahtar sunucuda pasife alınırsa kart bunu yazar; o sürede yalnız public paketler gelir.

## 1.6.3
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 1.6.2
- Tüm FluxWP paketlerinin logoları (temalar ve FluxWP Extras dahil) yeni ikon setiyle; paket listesinde gri yer tutucu kalmadı.
- Flux Admin'de Pastel seçiliyse paket logoları `assets/icons/pastel/` altından gelir.

## 1.6.1
- Güncelleme kontrolünde sitenin saat dilimi (Ayarlar › Genel) ve dili de gönderilir; sunucu panelindeki ülke haritası buradan dolar. IP ya da kişisel veri gönderilmez.

## 1.6.0
- Varsayılan güncelleme sunucusu `https://updater.fluxwp.com`.
- Site anahtarı isteğe bağlı: anahtarsız site public paketleri (FluxWP Extras) alır, anahtar girilince ona tanımlı private paketler de gelir. Kart "Anahtarsız bağlı" yazar; "anahtar girilmemiş" uyarısı kalktı.
- FluxWP Extras artık ayrı GitHub kaynağı olarak eklenmez; ana sunucudan gelir. Diğer kaynaklar listesi boş başlar.
- Anahtar hatası mesajları netleşti (sunucu anahtarsız güncelleme vermiyorsa ayrı mesaj).

## 1.5.0
- Birden çok güncelleme kaynağı. Ana sunucunun yanında Ayarlar › Diğer kaynaklar: public GitHub deposu (release'te `<slug>.zip`, notta `flx-meta`) ya da Flux Updater sunucusu (anahtar isteğe bağlı). Eklemeden önce test edilir; aç/kapat, Yukarı (öncelik), Sil. Varsayılan ek kaynak FluxWP Extras (github.com/FluxWP/wp-extras).
- Paketler tüm açık kaynaklardan toplanır; tabloda "Kaynak: …" ve "2 kaynakta var". Aynı paket birden çok kaynaktaysa kurulu olduğu kaynaktan güncellenir, kurulu değilse üstteki kaynak kazanır.
- Her anahtar yalnız kendi sunucusunun host'una gider; indirme adresi kaynağın host'unda değilse paket alınmaz.
- Ulaşılamayan kaynağın paketleri son bilinen veriden gösterilir; Güncellemeler'de ve sistem kontrolünde uyarı çıkar.

## 1.4.1
- Gerekli eklentiler temalarda da denetlenir (style.css "Requires Plugins", ör. fluxwp → Flux Setup): tema satırında "Gerekli: …", seçince "onu da ekle", kurulumda önce eklenti kurulup etkinleştirilir. Tema kendisi etkinleştirilmez.

## 1.4.0
- Bağlıyken üstteki "Bağlı" kartı gösterilmez; kart yalnız bağlantı hatası / anahtar yoksa çıkar.
- "Güncellemeleri kontrol et" düğmesi paket tablosunun başında; sayfa yenilenmeden sunucuya sorar, tabloyu ve menü sayısını tazeler. Yanında son kontrol tarihi ve sonuç ("3 güncelleme var" / "Hepsi güncel ✓"). Alttaki "Şimdi kontrol et" kalktı.
- "Her güncellemeden önce yedek alınır" şeridi Geçmiş'in hemen üstüne taşındı.

## 1.3.1
- Başarılı güncelleme/kurulum satırı yalnız "… güncellendi (1.1.0 → 1.2.1) · Başarılı ✓" yazar. Site kontrolü notu (ör. görünmez moddaki tema anonim isteğe 404 döner) artık gösterilmez; mesaj yalnız sorun olunca çıkar.

## 1.3.0
- Kurma ve güncelleme sayfadan çıkmadan: her paket satırında ilerleme ve sonuç, üstte canlı kayıt (saat, adım, sonuç; hata da burada). İşlem bitince tablo ve geçmiş kendiliğinden tazelenir. Sürüm değiştirme de aynı şekilde.
- Gerekli eklentiler (`Requires Plugins`): kurmadan önce denetlenir; eksikse "onu da ekle" uyarısı, gerekli eklenti seçimden çıkarılınca ona bağlı paketlerin seçimi de kalkar. Önce gerekli eklentiler kurulur ve etkinleştirilir (FluxWP paketi sunucudan, diğerleri wordpress.org'dan), sonra paket. Gerekli eklentisi etkin değilse paket etkinleştirilmez ve nedeni panelde yazar (WordPress'in "Hata: … gereklidir" sayfası çıkmaz).
- Toplu kurma: kurulu olmayan paketler de seçilebilir; "Seçilenleri kur / güncelle", "Kurulanları etkinleştir".
- Kurulumlar geçmişe yazılır. Güncelleme sunucusu 1.5.0 gerekli eklentileri bildirir; eski sunucuda yalnız kurulu paketlerin başlığı okunur.

## 1.2.1
- Flux AI ve Flux Admin logoları eklendi.
- Burada logosu olmayan bir FluxWP eklentisinin kendi `assets/icon.svg` dosyası kullanılır (yeni paketler için güncelleyici sürümü beklemeye gerek yok).

## 1.2.0
- Geçmiş sayfalı (sayfada 20 kayıt); 1000 kayda kadar saklanır.
- Geçmişte her güncellemenin yedeği: boyutu, ne zaman silineceği, Aç ve Sil. Seçerek veya tümünü silme (kilitli yedekler silinmez). Toplu güncellemede paketler tek yedeği paylaştığı için seçim kutusu ilk satırda.
- Güncelleme yedeklerinin otomatik silinme süresi (1–90 gün) Geçmiş'in üstünden seçilir; Flux Backup 1.2.0 ile mevcut yedeklere de uygulanır. Temizliği Flux Backup yapar.

## 1.1.1
- Düzeltme: bir eklenti kendi kaydını WordPress güncelleme verisinden silse bile (ör. Flux Forms 2.0.8) FluxWP güncellemesi kurulur; önceden "Güncelle" bir şey yapmıyor, paket listede kalıyordu.

## 1.1.0
- Sol menüde "Güncellemeler" (Başlangıç'ın altında), bekleyen güncelleme sayısı rozetiyle. Ayarlar ayrı alt menüde.
- Seçerek ve toplu güncelleme (WordPress'in kendi toplu güncelleyicisiyle).
- Sürüm değiştirme: sunucudaki son 20 sürümden birine geçiş (geri alma). Önce Flux Backup ile yedek alınır.
- Güncelleme sonrası site kontrolü: ön yüz veya admin HTTP 500 verirse paket otomatik olarak önceki sürüme döner.
- Geçmiş: kim, ne zaman, hangi sürümden hangisine, sonuç ve yedek bağlantısı.
- Flux Backup entegrasyonu (flxy_* API).

## 1.0.4
- Logolar yenilendi: derinlikli degrade, gölge ve daha net semboller.

## 1.0.3
- FluxWP eklentilerinin logoları (assets/icons/<slug>.svg): Güncellemeler ekranında ve Flux Updater paket tablosunda görünür. Filtre: `flxu_package_icons`.

## 1.0.2
- Varsayılan sunucu adresi düzeltildi (eski adres kayıtlıysa otomatik yenisine geçer).
- Bağlı değilken sunucu adresi ve site anahtarı formu sayfanın en üstünde; ikisi de panelden bir kez girilir, wp-config sabitleri isteğe bağlı.

## 1.0.1
- Ayarlar › Flux Updater üstünde bağlantı kartı: Bağlı / Bağlantı hatası / Bağlı değil, sunucudaki site adı, paket ve güncelleme sayısı
- "Bağlantıyı test et" düğmesi
- Eklentiler listesinde bağlantı durumu

## 1.0.0
- İlk sürüm
