---
title: Sürüm ve yayın
slug: surum-ve-yayin
section: yayin
order: 1
excerpt: Sürüm numarası ve CHANGELOG, main'e birleşince otomatik zip ve GitHub release, güncelleme testi ve sitelere dağıtım.
---
FluxWP paketleri elle zip'lenip yüklenmez. Sürüm numarasını artırıp `main`'e birleştirmek yeterlidir.

## Akış

1. Değişikliği bir dalda yapın, PR açın.
2. Ana dosyadaki `Version:` başlığını ve sürüm sabitini (`FLXEX_VERSION`) birlikte artırın.
3. `CHANGELOG.md`'nin en üstüne yeni sürümü yazın (Türkçe, kullanıcının anlayacağı dille).
4. PR birleşince **Paket release** iş akışı (`.github/workflows/release.yml`) çalışır:
   - sürümü değişen her paket için `bin/build.sh` ile zip üretir (`.distignore` dışındakiler),
   - temiz bir WordPress'e önceki sürümü kurup yeni sürüme geçer, sayfaları açar (`bin/test-update.sh`); PHP fatal ya da HTTP 500 olursa yayınlamaz,
   - `<slug>-v<sürüm>` etiketi ve GitHub Release oluşturur,
   - güncelleme sunucusunun önbelleğini boşaltır.
5. Siteler güncellemeyi **Flux Setup** üzerinden `updater.fluxwp.com`'dan alır.

## Sürüm numarası

- `0.x` ilk geliştirme; `1.0.0` kararlı.
- Hata düzeltme: yama (`1.4.1`), yeni özellik: küçük (`1.5.0`), uyumu bozan değişiklik: büyük (`2.0.0`).
- Sürümde `-` varsa (`1.2.0-beta.1`) ön sürüm (pre-release) olur.
- Aynı numara iki kez yayınlanmaz; bir sürüm alındıysa bir sonrakini kullanın.

## Yerelde zip

```bash
bin/build.sh plugins/flx-ornek   # dist/flx-ornek.zip
```

## Commit ve PR

- Commit mesajları İngilizce ve kısa (`Add wiki search`, `Fix certificate preview`).
- Her PR tek pakete odaklansın; ortak bir filtre gerekiyorsa sahibi pakete ayrı PR açın ve API belgesine yazın.

## Güncelleme sunucusu

`Update URI: https://updater.fluxwp.com/<slug>` başlığı paketi FluxWP sunucusuna bağlar. Herkese açık depolar anahtarsız dağıtılır; özel depolar yalnız anahtara tanımlı paketlere açılır.
