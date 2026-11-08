# Dil ekleme ve çevirileri güncel tutma

[English](LANGUAGES.md)

Eklentiler ve temalar kendi WordPress gettext dosyalarını taşır. **Flux Lang zorunlu değildir.** Flux Lang site içeriğini, dil adreslerini ve site dil seçicisini yönetir. Eklenti ön yüzde WordPress site dilini, yönetimde kullanıcı profilinin dil tercihini izler. Bölgesel dil dosyası yoksa uygun ana dil paketine döner; kullanıcının/WordPress'in mevcut çevirisini ezmez. Çalışma anında çeviri servisine bağlanılmaz.

## Yeni özellik geldiğinde

Kaynak gettext metinlerini ve dil anahtarlarını İngilizce yazın:

```php
esc_html_e( 'Save changes', 'flx-example' );
```

Seçenek/API anahtarlarını çevirmeyin; yer tutucuları koruyun. Eski Türkçe çağrılar için uyumluluk eşlemesi vardır. Yeni çağrılar İngilizce olmalıdır. Editör JavaScript'i için `wp.i18n` ve `wp_set_script_translations` kullanılır.

Ana README tablolarını tek komutla güncelleyin:

```sh
python3 tools/site-translations/coverage.py --repo . --scan-source --write-readme --report /tmp/translation-coverage.json
```

Komut iki ana README'deki üretilen bölümü günceller. `--scan-source` yeni kod çağrılarını da tarar; yerel Docker/PHP gerekir. Bu seçenek olmadan yalnız İngilizce JSON referansı okunur ve Python yeterlidir. Ağ isteği yapılmaz. Birden fazla `--repo` verilebilir; her README yalnız kendi paketlerini listeler.

Yüzde, İngilizceye göre boş olmayan ve biçimi geçerli kayıtların oranıdır. İngilizce/Türkçe yeni metin eklenip diğer diller tamamlanmadıysa onların oranı düşer. Eksik, bozuk, artık kullanılmayan ve kaynakla aynı kalan değerler JSON raporunda ayrıdır. Marka adları aynı kalabilir; yüzde dil kalitesinin onayı değildir. İsteğe bağlı `--check` eksik/stale sonuçta hata kodu verir; normal raporlama sürüm çıkarmayı engellemez. Eksik msgid WordPress'te İngilizce kaynak metne döner.

## Çeviri ve derleme

```sh
python3 tools/site-translations/package_translations.py --repo . --output /tmp/package-catalogs
python3 tools/site-translations/package_translations.py --repo . --output /tmp/package-catalogs --translate --build
python3 tools/site-translations/package_translations.py --repo . --output /tmp/validated-catalogs --import-catalogs /ceviriden/donen/dosyalar
```

İlk komut İngilizce kaynağı çıkarır. `--translate` açıkça verilirse geliştirici tarafında otomatik çeviri yapılır; önbellekten devam edilebilir. İçe aktarma ağ kullanmadan doğrulayıp derler. JSON anahtarlarını, context/çoğul biçimlerini, kodları ve yer tutucuları değiştirmeyin. Makine çevirisinin dilini ayrıca kontrol edin. Paketlerde düzenlenebilir JSON, PO/MO/PHP ve gerektiğinde editör Jed JSON dosyaları bulunur. Aynı önbellek klasöründeki üretim komutlarını sırayla çalıştırın.

## Yeni dil eklemek

1. `package_translations.py` içindeki `LANGS` listesine dil kodu, WordPress locale'i, çeviri servisi kodu ve GNU çoğul kuralını ekleyin. Örnek: İtalyanca `it`, `it_IT`, `it`, iki çoğul biçimi, `(n != 1)`.
2. Kaynağı çıkarıp yeni dili çevirin, doğrulayın ve derleyin. Bölgesel fallback yükleyicisi yeni ana dili de içerecektir. WordPress çekirdek dil paketi normal ayarlardan kurulur; bu araç onu kurmaz.
3. WordPress'e gömülen ortak React ekranları için core/runtime/task JSON dosyalarını, importları ve locale normalizasyonunu ekleyin. `react_translations.py --translate` eksikleri native önbellekten yararlanarak tamamlayabilir; bu bayrak yokken ağ kullanmaz. `{{name}}` gibi değişkenleri koruyun. Embed'i yeniden derleyip eklentinin kullandığı bundle'ı eşitleyin.
4. Çok dilli site için ayrıca Flux Lang kataloğuna/ayarlarına dili ekleyin. FluxWP site preset'inde `translations/targets.json` ve site çeviri sözleşmesini güncelleyin; içerik kayıtlarını üretip preset'i uygulayın. Eksiksiz kayıt seti bağlandıktan sonra dil açılır. Yalnız eklenti çevirisi isteyen kullanıcının Flux Lang kurmasına gerek yoktur.
5. Flux Lang kapalıyken WordPress site/kullanıcı dili, editör, yer tutucu ve çoğul testlerini yapın. Masaüstü/mobil, açık/koyu ve gerekirse RTL kontrolünü tamamlayın. README kapsamını güncelleyin, sürüm/CHANGELOG artırıp normal PR akışını izleyin.

## Maintainers

Ahmet Yılmaz ([@sharkpaw](https://github.com/sharkpaw)).
