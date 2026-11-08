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

İlk verilen site JSON kataloğu; site, preset, tema, Wiki ve Flux Lang arayüz metinlerini kapsar. Eklentilerin bağımsız dil dosyaları için aşağıdaki WordPress araçları kullanılır.

## Maintainers

Ahmet Yılmaz ([@sharkpaw](https://github.com/sharkpaw)).

## WordPress eklentileri ve temalar

`package_translations.py` PHP gettext çağrılarını token'larla, JavaScript'teki sabit çağrıları da kaynak dosyalardan tarar. Düzenlenen JSON anahtarları ve kaynak gettext metinleri İngilizcedir. Eski çağrılar için uyumluluk eşlemesi, Türkçe çıktı için ayrı TR paketi bulunur.

Her paket kendi PO/MO/PHP/JSON dosyalarını taşır. **Flux Lang kurulması gerekmez.** WordPress ön yüzde site dilini, yönetimde kullanıcının dil tercihini izler. Eklenen sekiz locale: `de_DE`, `es_ES`, `fr_FR`, `pt_BR`, `ja`, `zh_CN`, `ar`, `ru_RU`. WordPress'e gömülen ortak React ekranları da on dili destekler.

`--translate --build` açıkça istenirse ağ üzerinden otomatik çeviri yapılıp derlenir. `--import-catalogs /klasör` dönen JSON'ları ağ kullanmadan doğrular ve yerel paketlere derler. Tam komutlar İngilizce belgede. Kaynaklar İngilizce yazılmalıdır; `--normalize-source` yalnız bilinen eski metinleri İngilizce referansla eşler. Çoğul sayısı ve yer tutucular doğrulanır; makine çevirisinin dil kalitesi ayrıca gözden geçirilebilir.

Flux Lang; site adreslerini, içerik çeviri ilişkilerini ve dil seçiciyi yönetir. Preset içindeki site aktarım aracı ayrı çalışır; içerik seti eksiksiz aktarılınca yeni dilleri açar. Araçlar canlıya yayınlama veya PR merge işlemi yapmaz.

[Dil ekleme ve kapsam rehberi](LANGUAGES.tr.md)
