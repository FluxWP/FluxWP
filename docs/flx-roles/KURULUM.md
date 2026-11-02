# Flux Roles — Kurulum ve kullanım

**Sürüm 1.2.0.** Personel, İdareci ve Yönetici rolleri (panelden yeni rol de eklenir), rollerin erişimini açıp kapatan sayfa, kişisel referans linki ve WhatsApp numarası.

## 1.2.0'da yeni
- **Stüdyo** grubu: "Stüdyo'yu kullanır" (`flxst_use`) ve "Stüdyo'yu yönetir" (`flxst_manage`). Yönetici rolü ikisini de bir kez alır; Personel ve İdareci için tablodan açın. Flux Studio kurulu değilse satırlar "güncellemesi bekleniyor" rozetiyle görünür.

## 1.1.0'da yeni
- **Yönetici** rolü: sayfadaki her yetki. WordPress yöneticisi değildir (eklenti kuramaz, site ayarını değiştiremez).
- **Yeni rol:** Roller ve erişim › Yeni rol kartından ad yazıp ekleyin; tablodan yetkilerini açın. Panelden eklenen rol silinebilir.
- **WhatsApp numarası:** kullanıcı profilinde (yalnız yönetici yazar). WhatsApp asistanı bu numaradan yazanı tanır ve rolünün izin verdiği işleri yapar (başkası adına bağış, kendi bağışçıları, raporlar). Asistan tarafı Flux Chat güncellemesiyle gelir; o zamana kadar bu satırlarda "güncellemesi bekleniyor" rozeti görünür.
- Yeni yetkiler: başkası adına bağış, kendi bağışçıları, referans bildirimi, asistanda personel araçları, raporlar.

## Kurulum
1. Sitede **Güncellemeler** (Flux Updater) › Flux Roles › **Kur**, ya da Eklentiler › Yeni ekle › `flx-roles.zip` yükle, etkinleştir.
2. **Kullanıcılar › Roller ve erişim** sayfası açılır. Roller varsayılan yetkilerle hazır gelir.
3. Kişi eklemek: kullanıcı yoksa Kullanıcılar › Yeni ekle'de oluşturup Rol'den Personel/İdareci seçin; varsa Roller ve erişim › Kişiler'de kullanıcı adı ya da e-postayı yazıp **Ekle**.

## Roller (varsayılan)
| | Personel | İdareci |
|---|---|---|
| Görevler: normal kullanıcı (erişimi olduğu proje/listeler) | ✓ | ✓ |
| Sertifika oluşturur | ✓ | ✓ |
| Yalnız kendi oluşturduğu/referansı olduğu sertifikaları görür | ✓ | ✓ |
| Referansı olduğu bağışları görür | ✓ | ✓ |
| Sertifika dashboard'u | | ✓ |
| Bağış dashboard'u (genel toplamlar) | | ✓ |

Her satır sayfadan açılıp kapanır (gerçek WordPress yetkisi; menüden kalkar ve adresle de girilemez). "Varsayılana döndür" düğmesi var.
Ek seçenekler: proje/liste oluşturma, Görevler AI, sertifika toplu gönderim, Çağrı Merkezi, tüm bağışları görme.

## Referans linki
- Personel/İdareci (ve yönetici) için kişisel link: `https://example.org/?ref=<kod>`. Profil sayfasında ve Roller ve erişim › Kişiler'de **Kopyala**.
- Başka sayfa paylaşılacaksa adresin sonuna `?ref=<kod>` eklenir (ör. `/bagis/?ref=<kod>`).
- Linkle gelen ziyaretçi 30 gün içinde (ayardan değişir) bağış yaparsa WooCommerce siparişine referans kişi yazılır. Son tıklanan link geçerli.

## Bilinen durum (1.0.0, güncel değil: Sertifika 1.7.0 ve Bağış 2.2.0 bu yetkileri uyguluyor)
- **Görevler** hemen çalışır (Flux Tasks'in rol ayarıyla eşitlenir; Personel görevlere atanabilir).
- **Sertifika**'daki "yalnız kendi/referans" ve "dashboard" yetkileri ile **Bağış**'taki "referansı olduğu bağışlar" ve "dashboard" yetkileri
  ilgili eklentilerin güncellemesini bekliyor. Sayfada "… güncellemesi bekleniyor" rozeti görünür; ayar kaydedilir, güncelleme gelince geçerli olur.
  O zamana kadar Personel/İdareci Bağışlar menüsünü görmez; Sertifikalar'ı mevcut kurala göre görür (Sertifika Ayarları › "personel yalnız kendi sertifikalarını görsün").
- Eski "Sertifika Personeli" rolü (Flux Certificates) duruyor; yeni kişiler için Personel kullanın.
- Bağış referansı: link çerezi + sipariş meta'sı test edildi; bağış kaydına bağlanması Flux Donate güncellemesiyle gelecek.

## Test kapsamı
WP 6.5 + SQLite, Flux Tasks, Flux Certificates, Flux Admin ile: kurulum, rol yetkileri, Görev eşitlemesi, kaydet, kişi ekle/çıkar,
Personel girişinde Görevler'e yönlenme, referans çerezi (geçersiz kodda çerez yok). WooCommerce siparişine yazma canlıda denenmeli (WC test ortamında yoktu).
`bin/test-update.sh` temiz kurulum geçti.

Paketler arası sözleşme: `API.md`.
