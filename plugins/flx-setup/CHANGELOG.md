# Değişiklikler

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
