# Ekran görüntülerini ve tur videolarını yenileme

[English](README.md) · **Türkçe**

Eklenti veya tema değişince bu akışı kullanın. Gerçek güncel arayüzü çeker; README metinlerini ve çevrilmiş pazarlama içeriklerini yeniden yazmadan belgeleri, Updater kataloğunu ve site örneklerini yeniler.

**Varsayılan çekim dili İngilizcedir.** Türkçe görseller korunur; onları da yenilemek için `--language tr` ekleyin. Web sitesindeki ekranlar ve tur videoları tüm site dillerinde İngilizcedir. WordPress içindeki FluxWP Panel ise yönetici hesabının WordPress diline göre EN/TR galerisini seçer; uygun dil yoksa İngilizceye döner.

## 1. Yerel çekim ortamı

Gerekenler: Docker, Bun, Python 3, FFmpeg; dönüştürme için Pillow (`uv run --with pillow` sağlayabilir).

Depo kökünden:

```sh
bun install --frozen-lockfile
bunx playwright install chromium
python3 tools/readme-media/setup-docker.py --work /absolute/shared/capture-work
```

Oluşan `capture-env.json` yerel adresi, çalışma klasörünü ve container PHP sarmalayıcısını tutar. Kaynak depolar salt okunurdur; WordPress ve MariaDB ayrı Docker projesinde çalışır. macOS'ta çalışma klasörünü Docker Desktop ile paylaşın. Paralel ortamlar için ayrı proje adı/port kullanın.

Tarifin `seed` dosyalarını `capture-work/php` ile çalıştırın; gerekli ortak örnek verileri (`seed/users.php`, `seed-site.php`, `seed-posts.php`, `seed-form.php` ve paketin seed'leri) hazırlayın. Tema için FluxWP demo ön ayarını uygulayın. `env/mock-update` ve `env/mock-evolution` yerel servis örnekleridir; `env/connect-mocks.php` bağlantıları ayarlar. Gerçek veritabanı, müşteri kaydı veya API anahtarı kullanmayın.

`demo-guard.php` gerçek e-posta gönderimini ve dış HTTP isteklerini engeller; tarayıcı istekleri de yerelde kalır. Public paketler yalnız public paketlerin kurulu olduğu ortamda çekilir. `--private-root` ile ayrı private ortam oluşturun. Ön ayar çekimleri arasında yerel veritabanını yedekten geri yükleyin.

## 2. Güncellenen paketi çekme

```sh
# Varsayılan: İngilizce ekranları, mobil/koyu görünümü ve tur GIF'ini yeniler.
python3 tools/readme-media/capture-package.py tools/readme-media/cfg/flx-forms.json --env /absolute/shared/capture-work/capture-env.json

# İki dili de yeniler; yeni pakette ilk kez bu şekilde çalıştırın.
python3 tools/readme-media/capture-package.py tools/readme-media/cfg/flx-forms.json --env /absolute/shared/capture-work/capture-env.json --language en --language tr
```

Diğer seçenekler: yalnız ekranlar için `--no-tour`, başka depo için `--repo /absolute/repository`, tema için `--area themes`. Private tarifler kendi depolarında tutulur. `--no-shots` yalnız aynı oturumda doğrulanmış çekimler için kullanılır.

| Çıktı | Amaç |
|---|---|
| `docs/images/<sahne>-en.jpg`, `*-tr.jpg` | Masaüstü, mobil ve koyu ekranlar |
| `docs/demo.gif`, `docs/demo-tr.gif` | Gerçek tarayıcı turu |
| `docs/thumb.gif` | README galerisi |
| `docs/previews/*.webp` | Sonraki adımda üretilen Updater görselleri |

Kritik hata, izinsiz sayfa, başarısız HTTP yanıtı veya eksik ekran varsa çekim durur. Commit öncesi görüntüleri kontrol edin. Yalnız İngilizce yenilenirken README metinleri ve mevcut Türkçe görseller korunur.

## 3. Dağıtım dosyaları

```sh
uv run --with pillow tools/readme-media/export-updater-previews.py --repo /absolute/FluxWP --site-preset /absolute/site-preset
uv run --with pillow tools/readme-media/refresh-site-media.py /absolute/site-preset --package flx-forms
NODE_PATH=/absolute/FluxWP/node_modules bun /absolute/site-preset/tools/plugin-pages-og.mjs flx-forms
```

Private medya için katalog dışa aktarıcısına `--private-root /absolute/private-repository` ekleyin. Ayrı katalog yalnız o depoya yazılır. `--translate` isteğe bağlıdır; eksik kısa açıklamaları tamamlar, mevcut metinleri ve site bağlantılarını korur.

Açıklamalar, bağlantılar, ikon ve galeri dosyalarının Git blob referansları `catalog/packages.json` içindedir. Katalog ve işaret ettiği dosyaları aynı commit'e ekleyin. Updater varsayılan dalı okuyup dosyaları doğrular, önbelleğe alır ve kendi imzalı adreslerinden dağıtır. Updater'da ikinci bir yükleme/düzenleme alanı; Panel'de gömülü tanıtım kataloğu yoktur.

Site dönüştürücüsü çevrilmiş sayfa metinlerini korur. Paylaşım kartlarının başlıkları/açıklamaları yerelleştirilir; içindeki örnek ekran İngilizcedir. Türkçe kaynak ekranlar ve WordPress Panel galerileri saklanır.

## 4. Kontrol ve yayın

- Masaüstü/mobil, açık/koyu ekranları kontrol edin. İngilizce çekimde arayüz İngilizce, Türkçe çekimde Türkçe olmalı. Yapay örnek veri kullanın.
- EN/TR/RTL site sayfalarında İngilizce örnekleri; WordPress Panel'de EN/TR galeri seçimini doğrulayın.
- Site preset'indeki `tests/english-media.php` dosyasını geçici WordPress ortamında `FLX_TEST_SITE=1` ile çalıştırın.
- Farkları inceleyin; değişen paketin sürümünü ve changelog'unu artırın, ZIP'i üretin ve PR açın. Tanıtım görselleri kurulum ZIP'ine girmemeli.
- Merge sonrası katalog Updater'a ulaşır. Dosya adı aynı kalsa bile değişen görsel için katalogdaki blob referanslarını yenileyin.

İleride şu şekilde istenebilir: “Flux Forms ekranlarını bu rehbere göre yenile. İngilizce varsayılan olsun, Türkçe kopyaları koru; katalog ve site türevlerini güncelle; masaüstü/mobil açık/koyu örnekleri göster.”

## Tarifler ve eski araçlar

`cfg/<slug>.json`, `shots`, `mobile`, `dark` ve gerektiğinde `seed`, CSS ve eylemleri tanımlar. Adreslerde yerel kayıt kimliği için `{post:Başlık}` veya dil bazlı `{ "en": "…", "tr": "…" }` kullanılabilir. Eylemler: `click`, `keys`, `type`, `hover`, `eval`, `sel`, `clip`, `full`. `shoot.js` ekran alır; `tour.js` değişiklik yapmayan gezinmeyi kaydeder. Yeni özellikte adres/seçici değiştiyse tarifi güncelleyin.

Eski `run.sh`, host PHP/SQLite kullanan `setup.sh`, `gen.py` ve `place.sh` korunur. **Normal yenilemede `run.sh` kullanmayın:** README'yi yeniden üretip elle eklenen bölümleri silebilir. Yukarıdaki `capture-package.py` akışını kullanın.

Kök README galerileri `bin/readme-table.sh README.md en` ve `bin/readme-table.sh README.tr.md tr` ile yenilenir; release akışı da bunları günceller.

## Maintainers

Ahmet Yılmaz · [@sharkpaw](https://github.com/sharkpaw)
