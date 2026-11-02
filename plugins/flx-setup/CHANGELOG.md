# Değişiklikler

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
