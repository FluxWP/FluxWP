# Flux AI — Kurulum ve kullanım

**Sürüm 0.5.2.** Tüm FluxWP eklentilerinin ortak yapay zekâ eklentisi: anahtarlar, modeller, prompt önbelleği, harcama sınırı, raporlar/faturalandırma ve herkese (yetkisine göre) açık ortak AI sohbeti.

## Kurulum
1. **Güncellemeler** (FluxWP Panel) › Flux AI › **Kur**, ya da Eklentiler › Yeni ekle › `flx-ai.zip` yükle, etkinleştir.
2. Sol menü **Flux AI › Ayarlar**:
   - **OpenRouter API anahtarı** ve/veya **Anthropic (Claude) API anahtarı** girin. Anahtar bir kez yazılır; sonra yalnız son 4 hanesi görünür. "Sil" kutusu anahtarı kaldırır.
   - Claude hata mesajı "anthropic-workspace-id" isterse **Anthropic workspace ID** (console.anthropic.com › Settings › Workspaces, `wrkspc_…`).
   - **Varsayılan model**: alana tıklayın, listeden seçin, **Test et**. Claude modelleri `claude:` önekiyle görünür (ör. `claude:claude-haiku-5-5`).
   - İsterseniz **Ortak sohbet modeli** (araç kullanabilen model) ve **Eklentiye özel model** (ör. Çağrı Merkezi için ayrı model).
3. **Harcama sınırı**: günlük / haftalık / aylık USD. 0 = sınırsız. Dolunca istekler durur (ya da yalnız uyarır); eşikte ve dolunca yöneticiye e-posta.
4. **Kaydet**, sonra LiteSpeed Cache › Purge All.

## Ekranlar
Sol menüde **Flux AI** (Flux Admin'de "Diğer" grubunda, İletişim'in yanında):
- **Genel bakış**: harcama sınırları, maliyet, istek, token, toplam süre, hata. Grafikler: günlük maliyet, **süre nereye gidiyor (model/düşünme ve araçlar, dikey bar)**, günlük token, araç başına ortalama süre. Eklentiye / modele / amaca göre tablolar.
- **Kayıtlar**: her model isteği ve araç çağrısı. Nerede (sistem · amaç · sohbet no), kim, model, token, süre, maliyet, hata. Süzgeç: 24 saat / 7 / 30 / 90 gün, tür, sistem, yalnız hatalar. **İçerik** düğmesi giden son mesajları ve gelen cevabı gösterir; e-posta, telefon, kart/TC, IBAN ve anahtarlar maskeli. İçerik 30 gün saklanır (Ayarlar › Kayıtlar › İçerik kaydı, kapatılabilir).
- **Bağlı sistemler**: Flux AI'yı kullanan eklentiler (sohbet, Çağrı Merkezi, Görev …). Durum, model, 30 günlük kullanım, son kullanım. **Açık** kutusunu kaldırıp Kaydet: o sistem yapay zekâ isteği gönderemez (kayıtlarda "Kapalı" görünür). Eklentinin kendi ayarları değişmez; tekrar açınca kaldığı yerden devam eder. Sohbet kapatılırsa "AI'ya sor" düğmesi gizlenir.
- **Faturalandırma**: ay listesi, ay toplamı, önbellek tasarrufu, sağlayıcıya göre döküm, CSV indir, OpenRouter kalan kredi (Yenile). Anthropic bakiye API'si sunmuyor; bakiye console.anthropic.com › Billing.
- **Araçlar**: ortak sohbetin kullandığı araçlar (Flux SEO & MCP dahil); aç/kapat, 30 günlük kullanım ve süre.
- **Ayarlar**: API anahtarları, modeller, harcama sınırı (gün/hafta/ay), sohbet, görseller, kayıtlar.

## Ortak AI sohbeti
- Sağ altta **AI'ya sor** düğmesi. Yalnız giriş yapmış ve izinli kullanıcılar görür (Ayarlar › Ortak sohbet › Kimler kullanır; varsayılan yalnız Yönetici). Yönetim panelinde ve/veya sitede açılır.
- **Yüzen pencere** ya da **sağa yerleşik** (sayfayı hafifçe sola iter). Sol kenardan genişlik, köşeden boyut değişir. Sayfa değişince aynı yerde ve aynı sohbette açılır. Kısayol Ctrl/Cmd+J.
- Başlıktaki ok: sohbet listesi, arama (başlık ve içerik), arşivdekiler. Her sohbette ⋯: yeniden adlandır, arşive al, sil. Başlık ilk mesajdan otomatik verilir.
- Asistan düşünme adımlarını ve araç çağrılarını gösterir (tıklayınca girdi/sonuç).
- **Değişiklik yapan her işlem önce onay ister**: kartta işlem ve gönderilecek veri görünür, **Onayla / Reddet**, sonra **Devam et**. Onaylanmayan işlem çalışmaz.
- Yöneticiler sohbet içinden model seçebilir.
- **Sayfa geçişi (0.5.1):** panel yeni sayfada ilk karede aynı haliyle görünür (mesajlar, kaydırma), sonra sessizce tazelenir; yeniden yüklenmiş gibi belirmez.
- **Canlı akış (0.5.0):** düşünme, araç çağrıları ve cevap yazıldıkça görünür. LiteSpeed/Hostinger akışı tamponlarsa cevap yine gelir, yalnız sonda toplu görünür.
- **Arka planda devam (0.4.0):** panel kapansa, sayfa yenilense ya da başka sayfaya geçilse de sohbet sürer. Panel kapalıyken baloncuk genişleyip son durumu (düşünme, çalışan araç, cevap) 1-2 satır akıtır; onay gerekince turuncu olur. Yanındaki ■ düğmesi durdurur. Tüm sekmeler kapanırsa iş bir sonraki sayfa açılışında kaldığı yerden devam eder.
- **Düzenle / geri dön:** kendi mesajınızın yanındaki kalem mesajı düzenleyip yeniden gönderir (sonrası yeniden yazılır); geri sar düğmesi o noktadan sonrasını siler. Asistan çalışırken önce durdurun.

## Görseller (0.2.0)
- Asistan gerektiğinde tablo, kart, akış şeması, küçük grafik gibi görselleri sohbetin içinde çizer. Her modelle çalışır. "Bunu görsel olarak göster", "akış şeması çiz" diyebilirsiniz.
- Görseller izole bir çerçevede çizilir: dış bağlantı, form, siteye erişim yok. Çizilemezse kodu düz metin olarak görünür.
- Kapatmak için: Ayarlar › Görseller › Sohbette görsel.
- **WhatsApp/e-posta:** bu kanallar HTML gösteremez; görsel PNG'ye çevrilip gönderilir (Çağrı Merkezi güncellemesiyle). Sunucuda Imagick varsa yalnız SVG görseller çevrilir. Her tür görsel için Docker sunucusuna Gotenberg kurup adresini Ayarlar › Görseller'e girin: `docker run -d --restart unless-stopped -p 3000:3000 gotenberg/gotenberg:8`. Gotenberg'i herkese açık bırakmayın: önüne kullanıcı/şifreli reverse proxy koyun (bilgileri aynı yere girin) ya da portu yalnız sitenin sunucusuna açın. Çevirici yoksa metin gider.
- Şifreli ve HTTPS kurulum (docker-compose + Caddy, Gotenberg 8 basic auth) için `GOTENBERG.md` dosyasına bakın. Adres, kullanıcı ve şifre girildikten sonra **Test et** (0.2.1) kaydetmeden dener ve örnek PNG'yi gösterir.

## Diğer eklentiler
Flux AI kuruluyken Çağrı Merkezi, Görev, Sertifika vb. güncellendikçe anahtar ve model Flux AI'den gelir, harcamaları buradaki raporlara ve sınıra düşer. Flux AI yoksa ya da anahtar girilmemişse eklentiler kendi eski ayarlarıyla çalışır.

## Notlar
- Hostinger ~60 sn'de isteği kestiği için uzun sohbet turları 45 sn'lik adımlarla ilerler; ekranda kesinti olmaz.
- Kullanım kayıtları varsayılan 365 gün saklanır (Ayarlar › Kayıtlar).
- Kaldırırken veriler yalnız "Eklenti silinirken kayıtları ve sohbetleri de sil" açıksa silinir.
- Sistem kontrolünde Flux AI satırları: anahtarlar, model, harcama sınırı (oradan da girilebilir), son 24 saat hata oranı, sohbet araçları.
