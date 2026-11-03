# Değişiklikler

## 1.0.10
- Paketler (telefon): logosu olmayan pakette yer tutucu simge metnin üstüne binmiyordu; artık logo kutusunun yerinde.
- Ön ayar paketlerinin kendi logoları: Yeşil Türkiye Preset (ağaçlar, yeşil), Hayır Kapısı Preset (açık kapı, turuncu), FluxWP Site Preset (sayfa şablonu, mor).

## 1.0.8
- Kurulum sihirbazının ilk ekranında yasal onay: **Kullanım Şartları ve Gizlilik Politikası'nı kabul ediyorum; KVKK Aydınlatma Metni'ni okudum** (fluxwp.com linkleri, panel diline göre EN ya da /tr/). İşaretlenmeden "Başlayalım" açılmaz; "Kendim kurarım" yine çalışır.
- İsteğe bağlı **Ürünü geliştirmemize yardımcı olun (kullanım verisi gönder)** kutusu, varsayılan kapalı. FluxWP Panel › Ayarlar'dan açılır ya da geri alınır.
- İzin yokken güncelleme denetimi yalnız paketleri, FluxWP Panel sürümünü ve kurulum kimliğini gönderir: site adresi, WordPress/PHP sürümü, saat dilimi ve dil gitmez; FluxWP sunucularına giden isteklerde site adresli User-Agent kaldırılır. Sunucu tarafı: updater 1.9.6.

## 1.0.7
- Kurulum sihirbazı "Neyle başlayalım?": FluxWP teması kartı yerine **Yönetim teması** (Flux Admin) kartı. Mağaza/Dernek seçiminden bağımsız seçilir; Flux Admin artık Yönetim kategori ekranında tekrar listelenmez.
- FluxWP teması kart olmadan her kurulumda gelir (ön ayar, renk ve sayfalar ona bağlı).

## 1.0.6
- Görünen ad **FluxWP Panel** oldu (eklenti başlığı, yönetim menüsü, sihirbaz, bildirimler). Slug `flx-setup`, ayarlar ve güncelleme adresi aynı kalır.

## 1.0.5
- Paketler ekranı telefonda kart düzeninde: logo, ad, sürüm ve durum tek satırda; tablo başlığı, tür sütunu, slug ve çıkış tarihi gizli. Araç çubuğu düğmeleri yan yana, toplu işlem tam satır.
- Güncelleme ayarları › Diğer kaynaklar tablosu telefonda kart düzeninde.

## 1.0.4
- Düzeltme: sihirbaz ve paket ekranı eklentileri sessiz etkinleştiriyordu; paketlerin etkinleştirme kancaları (tablolar, varsayılan ayarlar) çalışmıyordu. Artık normal etkinleştirme yapılır.
- Bir kerelik onarım: güncellemeden sonra yönetim paneli ilk açılışta etkin Flux paketlerinin etkinleştirme kancalarını bir kez çalıştırır (ör. eksik Flux Forms tabloları oluşur). Filtre `flxk_repair_plugin`.

## 1.0.3
- Flux QR Menu (özel paket) kurulum sihirbazında listelenmez. Gizlenecek paketler için filtre `flxk_onboarding_hidden`.

## 1.0.2
- Kurulum ekranında sol liste ve sağdaki çark dikeyde ortada.

## 1.0.1
- "Neyle başlayalım?" ekranına **Dernek ve bağış** kartı (Flux Donate + Flux Certificates) ve sade bir illüstrasyon. Mağaza ile Dernek tema gibi tek seçimli: biri seçilince diğeri kalkar.
- Eklentiler kategorilere ayrıldı (Yönetim, İletişim, Büyüme, Yapay zekâ, Sistem); her kategori ayrı ekran, kare kartlar, tam açıklama. Filtre `flxk_onboarding_groups`; `flxk_onboarding_core` artık kart listesi alır.
- Görünüm adımı sekmeli: Kimlik (site adı, slogan, logo yükleme), Ön ayar, Renk (her stil küçük site önizlemesiyle), Sayfalar (Blog, Hakkımızda, İletişim, SSS; filtre `flxk_onboarding_pages`). Sağdaki önizleme sabit; logo, menü, bağış/ürün kartları ve blog bölümü seçime göre değişir. Devam ve Geri önce sekmeler arasında gezer.
- Kurulumda logo temanın logosu ve site simgesi olur, seçilen sayfalar açılır (Blog yazılar sayfası olur), slogan kaydedilir.
- Kurulum ekranı: "Siteniz sizin için hazırlanıyor" başlığı görünür; koyu konsol yerine açık renkli, yarı boy dönen çark ve sade satırlar. Kurulum sırası yan yana küçük kartlar; kurulanlar açık yeşil.
- Atla penceresi artık tam opak.
## 1.0.0
- Flux Updater ile birleşti: tek eklenti **Flux Setup** (slug `flx-setup`). Paket listesi, güncellemeler, sürüm değiştirme, güncelleme öncesi yedek ve geri alma Flux Updater 1.7.2'den aynen geldi; sınıflar `FLXU_*`, ayarlar `flxu_*` aynı kaldı.
- Sol menüde tek **Setup** öğesi: Paketler, Kurulum sihirbazı, Ön ayarlar, Ayarlar. Eski `page=flx-updater` / `flx-updater-ayarlar` adresleri yeni sayfalara yönlenir.
- Kurulum sihirbazı: etkinleştirmeden sonra bir kez açılır, atlanabilir. Sunum düzeni: her adım tek yatay ekrana sığar, sayfa kaymaz, adımlar yana kayar; zemin yumuşak renkli, kartlar buzlu cam. Adımlar: tanıtım › tema ve mağaza › eklenti kartları (ekran görüntüsü + tek satır) › ön ayar, renk stili ve site adı (canlı önizleme) › kurulum (paket başına satır, konsol kaydı, ilerleme) › bitti ekranı ve sonraki adımlar. Hatalı adımlar sonda listelenir.
- "Neyle başlayalım?" adımı: FluxWP teması ve Flux Commerce ayrı ekranda, ikisi de önerilen; altta "Seçmeden devam et". Tema seçilmezse renk stilleri ve tema ön ayarı gösterilmez. Filtreler `flxk_onboarding_intro`, `flxk_onboarding_core`, `flxk_onboarding_presets`, `flxk_onboarding_cap`.
- Flux Updater kuruluysa Flux Setup etkinleşince eski eklenti kapatılır, ayarları korunur; ikisi aynı anda yüklenmez.
- Geist yazı tipi (OFL) eklentiyle gelir, dışarıdan yazı tipi yüklenmez.

## 0.2.4
- Ara bilgi metinleri Flux Admin tonunu izliyor.

## 0.2.3
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 0.2.2
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 0.2.1
- Kurum profili renk örneği FluxWP indigo.

## 0.2.0
- Tema `flx-setup-panel` desteği verirse Ön ayarlar ekranı Tema Ayarları içinde açılır; ayrı menü kalkar, eski adres oraya yönlenir.
- Kurum profiline site simgesi (`icon`).
- Şablon/şablon parçası öğeleri `requires.theme` etkin temayla uyuşmazsa atlanır.

## 0.1.0
- İlk sürüm: ön ayar motoru (flx-preset/1).
- Ön ayar şablonu (preset.json): kurum profili, WordPress ayarları, eklenti ayarları (derin birleşme, key'li listeler), global stil, şablonlar, şablon parçaları (header, footer), pattern'ler, sayfa/yazı/terim/menü/ürün içerikleri ve medya. Sayfa gövdesi blok markup'ı ya da JSON blok ağacı.
- Görünüm › Ön ayarlar: önizle (ne değişecek), uygula, yalnız boşları doldur, kaldır. Panelden elle değiştirilen değerlerin üzerine yazılmaz; istenirse satır satır zorla uygulanır.
- Migration'lar: ön ayar güncellemeleri tek tek Önizle / Uygula / Atla; `auto` olanlar kendiliğinden.
- Birden çok ön ayar aynı sitede (çakışmalar önizlemede).
- Geçmiş: her uygulama geri alınabilir; kaldırınca oluşturulan sayfalar taslağa alınır, silinmez.
- Kurum profili (`flx_profile()`): paketler ad, imza, WhatsApp, IBAN gibi bilgileri buradan okur.
- Bu siteden şablon çıkar (gizli alanlar hariç), JSON içe aktar.
- Flux AI sohbeti ve Flux MCP araçları: preset_schema, preset_list, preset_export, preset_patch_preview, preset_patch_apply, preset_revert, preset_history. Dış MCP'den gelen yamalar varsayılan olarak yönetici onayı bekler.
- Boş sitede ön ayar eklentisi etkinleşince kendiliğinden uygulanır.
