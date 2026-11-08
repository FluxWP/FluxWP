# Site çeviri aracı

[English](README.md)

Ahmet Yılmaz'ın paylaştığı Python aracının taşınabilir sürümü. Python 3.10+ ve standart kütüphane yeterlidir. Eksik `validator.py` eklendi. Bu bir geliştirici aracıdır; WordPress eklentisi veya çalışma anında AI bağlantısı değildir.

Varsayılan işlem yalnız çevrimdışı doğrulamadır; ağ isteği ve önbellek yazımı yapmaz:

```sh
python3 tools/site-translations/translate_all.py --workspace /dosyalarin/klasoru
python3 tools/site-translations/translate_all.py --workspace /dosyalarin/klasoru ar ja zh
```

Klasörde `en.json` ve hedef dil dosyaları bulunmalı. Kaynak sürümü, anahtarlar, sabit değerler, yer tutucular, adresler, HTML ve kod bütünlüğü doğrulanır. Bu kontrol dil kalitesini veya hukuki metinlerin doğruluğunu onaylamaz.

Yeni otomatik çeviri için açıkça `--translate` eklenir. Bu işlem İngilizce metinleri Google'ın genel çeviri uç noktalarına gönderir, önbellek yazar ve doğrulama sonrası hedef dosyayı değiştirir. Mevcut insan düzenlemelerini birleştirmez; önce yedek alın. Uç noktaların sürekliliği garanti değildir. Canlı siteye aktarım, dil etkinleştirme ve yayınlama bu aracın işi değildir.

`TRANSLATION_REPORT.md` ilk çalışmanın kullanıcı tarafından verilen raporudur; güncel kontrolün yerine geçmez. Eski dosya yolları özgün artifacts klasörüne aittir. Kataloglar bunları kullanan preset'te tutulur; ortak araç klasörüne müşteri metinleri veya önbellek eklenmez.

Paket; site, preset, tema, Wiki ve Flux Lang arayüz metinlerini kapsar. Bütün FluxWP eklentilerinin bağımsız dil dosyalarını kapsamaz.

## Maintainers

Ahmet Yılmaz ([@sharkpaw](https://github.com/sharkpaw)).
