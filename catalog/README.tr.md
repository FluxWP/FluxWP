# Paket tanıtımları

[English](README.md) · **Türkçe**

`packages.json`, Updater’ın dağıttığı açıklamaların, site bağlantılarının, ikonların ve ekran görüntülerinin kaynağıdır. Dil bazlı `desc` ve `website` alanlarını burada düzenleyin. Yeniden dışa aktarma bu düzenlemeleri korur.

Görselleri [`tools/readme-media`](../tools/readme-media/README.md) ile yenileyin; rehberdeki ortamda `export-updater-previews.py` çalıştırın. Katalog ve işaret ettiği paket dosyalarını aynı commit’e ekleyin. Her görsel referansı depo yolu, Git blob özeti ve dosya boyutunu içerir; resim veya SVG değişince referansları yeniden üretin.

Updater varsayılan dalı kontrol eder, katalog sürümünü sabitler, medyayı ilk kullanımda doğrulayıp önbelleğe alır ve kendi imzalı adreslerinden dağıtır. GitHub bilgileri sunucuda kalır. Private paketler kendi depolarındaki ayrı katalogda tutulur; buraya özel içerik kopyalamayın. İç bakım araçları tanıtım listesine eklenmez.

## Maintainers

Ahmet Yılmaz · [@sharkpaw](https://github.com/sharkpaw)
