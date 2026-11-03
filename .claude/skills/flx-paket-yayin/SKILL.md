---
name: flx-paket-yayin
description: FluxWP/fluxwp'deki bir FluxWP eklentisi ya da temasının yeni sürümünü test edip yayınlarken, test ortamı (WP + SQLite + Playwright) kurarken veya FluxWP Panel (updater) / güncelleme sunucusu sorunlarına bakarken kullan.
---

# FluxWP paket testi ve yayını

Akış: kod → sürüm artır → CHANGELOG → yerel test (`bin/test-update.sh`) → ekran görüntüleri → branch'e push →
**Ahmet onayı** → `main` → GitHub Actions release → güncelleme sunucusu → sitelerde FluxWP Panel.

## 1. Repo ve kurallar

- `FluxWP/fluxwp` (private): `plugins/<slug>/`, `themes/<slug>/`, `bin/`, `.github/workflows/release.yml`,
  `docs/` (paket belgeleri), `.claude/skills/`.
- Bir thread yalnız kendi paket klasörüne commit eder. Çalışma branch'i size verilen `claude/...` branch'i.
- **`main`'e push = yayın.** Kullanıcının açık onayı olmadan `main`'e push etme / PR merge etme. Branch'e push ve PR açmak serbest.
- Workflow yalnız `plugins/**` ve `themes/**` değişince çalışır; `docs/` veya `.claude/` değişikliği release açmaz.

## 2. Sürüm

1. Ana dosyadaki `Version:` başlığını artır (eklenti: `plugins/<slug>/<slug>.php`; tema: `style.css`).
2. Koddaki sürüm sabitini de aynı yap (`FLXB_VERSION`, `FLX_VERSION` …). Farklı olursa cache-busting ve migration şaşar.
3. `CHANGELOG.md`'ye en üste `## x.y.z` bölümü: kullanıcının göreceği değişiklikler, Türkçe, kısa maddeler. Bu bölüm release notu olur.
4. Semver: hata düzeltme patch, yeni özellik minor, uyumsuz API değişikliği major. `-` içeren sürüm (`1.3.0-beta.1`) pre-release,
   yalnız `beta` kanalındaki siteler görür. Sürüm asla geri gitmez (CI hata verir).

## 3. Yerel test: `bin/test-update.sh`

```bash
bin/build.sh plugins/flx-ornek                          # → dist/flx-ornek.zip
bin/test-update.sh plugins/flx-ornek dist/flx-ornek.zip [önceki.zip]
```
Ne yapar: `php -l`; önceki sürüm varsa temiz WP'ye kurar → sayfaları açar → yeni zip'i üstüne kurar (WP güncelleyicisi) →
ön yüz, giriş, `/wp-admin/`, `plugins.php` ve admin menüsündeki tüm `admin.php?page=` sayfalarını açar; sonra temiz kurulum.
HTTP 500, "critical error", PHP Fatal/Parse → **yayınlanmaz**. Paketten gelen Warning/Notice uyarı olarak raporlanır (düzelt).
`Requires Plugins` bağımlılığının (ör. WooCommerce) kendi fatal'ı yalnız uyarıdır.

Yerelde SQLite ile:
```bash
export DB_MODE=sqlite SQLITE_PLUGIN_DIR=/yol/sqlite-database-integration WP_CLI="php /yol/wp-cli.phar"
```
Önceki sürüm zip'i: GitHub release `<slug>-v<son>` asset'i `<slug>.zip` (GitHub MCP ile indir) ya da `git show <tag>:` ile build.

## 4. Test ortamı (cloud container)

- wordpress.org ve bazı hostlar proxy'de kapalı olabilir. Sırayla dene: `wp core download`; olmazsa npm
  (`@wp-playground/wordpress-builds`, `@wordpress/*`), GitHub'daki `WordPress/WordPress` tag'i; SQLite eklentisi
  GitHub `WordPress/sqlite-database-integration`. WooCommerce: GitHub release zip'i ya da npm; olmazsa mock'la ve "canlıda test edilmeli" diye raporla.
- Hızlı site: WP + SQLite drop-in (`wp-content/db.php`) + `php -S 127.0.0.1:8080 -t site/` (`PHP_CLI_SERVER_WORKERS=4`).
  `WP_HTTP_BLOCK_EXTERNAL` true, `DISABLE_WP_CRON` true; cron'u elle `wp cron event run --due-now`.
- Playwright (Chromium hazır; `playwright install` çalıştırma): giriş çerezini `wp eval` ile üret
  (`wp_generate_auth_cookie`) ya da login formunu doldur. Masaüstü 1440 px + mobil 390 px, light + dark
  (`colorScheme: 'dark'`) ekran görüntüsü al; konsol hatalarını topla.
- wp-admin stilleri: gerçek `wp-admin/css` ve WC CSS'i yüklü olsun (mock CSS özgüllük hatalarını gizler).
- Dış servisler (iyzico, Evolution WhatsApp, Netgsm, OpenRouter, GitHub) için yerel mock sunucu; raporda "yalnız mock ile test edildi" de.
- WP sürümü: en az `Requires at least` sürümü ve en güncel. `l10n.php` çevirisi WP 6.5+ yüklenir; daha eskide `.mo` gerekir.
- Playground wasm'da GD FreeType yok; GD çizimi yerel PHP ile doğrula.

## 5. Yayın (CI)

`main`'e push → `.github/workflows/release.yml` her paket için: `Version` değiştiyse ve `<slug>-v<sürüm>` tag'i yoksa →
`bin/build.sh` → önceki release zip'iyle `bin/test-update.sh` (MySQL) → geçerse `gh release create <slug>-v<sürüm> dist/<slug>.zip`
(not: `bin/release-notes.sh` = CHANGELOG bölümü + `<!-- flx-meta {type,name,requires,tested,requires_php} -->`) →
`UPDATE_WEBHOOK_SECRET` varsa sunucu önbelleği boşaltılır (yoksa ≤5 dk).
Test geçmeyen paket yayınlanmaz, iş kırmızı biter. Acil durumda Actions › Run workflow › `skip_tests` (yalnız kullanıcı isterse).
Pakete girmemesi gerekenler: paket kökünde `.distignore` (varsayılan hariç: `.git*`, `node_modules`, `tests`, `CHANGELOG.md`).

Push sonrası Actions sonucunu kontrol et (GitHub MCP `actions_list`/`get_job_logs`); kırmızıysa düzelt, yeniden push.

## 6. Güncelleme sunucusu ve FluxWP Panel

- Sunucu `FluxWP/updater`, `https://updater.fluxwp.com` (`/admin` panel, `/v1/check`,
  `/v1/download/<slug>/<sürüm>.zip`, `/v1/webhook`). GitHub token yalnız sunucuda; siteler `X-FluxWP-Site-Key` ile bağlanır,
  sunucuda anahtarın sha256'sı durur. Site başına paket listesi ve kanal (stable/beta).
- Sitede FluxWP Panel (`plugins/flx-setup`, updater modülü prefix `flxu_`): katalogu `update_plugins`/`update_themes` transient'ine ekler,
  FluxWP paketlerini wordpress.org isteğinden çıkarır, sol menü "Güncellemeler", sürüm değiştir (geri al), güncelleme sonrası
  self-check (ön yüz + admin-ajax ping) HTTP 500 → otomatik eski sürüme dönüş (sunucudaki eski zip ya da Flux Backup klasör yedeği).
  Flux Backup kuruluysa her güncellemeden önce DB + paket klasörü yedeği.
- **Tuzak**: başka bir eklenti `site_transient_update_plugins` filtresinde `response`'tan kayıt silerse FluxWP güncellemesi kaybolur
  (Flux Forms fork'u kendi anahtarını sildi). Güncelleyici 1.1.1 `PHP_INT_MAX` öncelikle geri koyar; yine de paketlerde bunu yapma.
- Yeni paket: klasörü açıp `Version:` ile push → sunucu release'den tanır; sitede FluxWP Panel › Paketler › **Kur**.
  Logo: `plugins/flx-setup/assets/icons/<slug>.svg` (ortak tarz) ya da `flxu_package_icons` filtresi.
- "Güncelleme görünmüyor" teşhisi: release var mı (tag + `<slug>.zip` asset) → sunucu önbelleği (webhook/5 dk) →
  site anahtarının paket listesi/kanalı → sitede Güncellemeler › "Şimdi kontrol et" → başka eklenti `response`'u siliyor mu.

## 7. Teslim ve raporlama

- Zip + `KURULUM.md` (+ `API.md`, ekran görüntüleri) `/mnt/project-files/<paket-klasörü>/` altına; aynı belgeler repoda `docs/<klasör>/`.
  Ekran görüntüleri ve zip'ler repoya girmez.
- KURULUM.md: ne değişti, kurulum/güncelleme adımları, canlıda yapılacak tek tık işlemler (hazır güncellemeler, ayarlar),
  bilinen sınırlar ve "yalnız mock ile test edildi" gibi test kapsamı.
- Başka paketin yapması gereken değişiklik varsa kendi klasörüne dokunma; `diger-sahipler-icin.md` benzeri not + coordinator'a mesaj.
- Proje hafızasına sürüm, thread ve bekleyen onayı yaz (paket başına tek dosya).
- Kullanıcıya: sürüm, ne değişti (1–3 satır), test sonucu, push için onay sorusu. Uzun rapor dosyada.

## 8. Canlı ortam bilgisi (Hostinger)

- Cloud'dan siteye ağ erişimi yok; teşhis kullanıcının yüklediği log/dosyalarla. Şifre chat'e yazdırılmaz.
- Yoğun istekte IP rate-limit; `DISABLE_WP_CRON` açık ama gerçek cron tetiklenmeyebiliyor; saat dilimi +00:00 olabiliyor;
  LiteSpeed REST/sayfa önbelleği ve lazy load açık. Belirtiyi bunlarla karşılaştırmadan kodu suçlama.

## Kontrol listesi

- [ ] `Version:` + sürüm sabiti aynı, geri gitmiyor
- [ ] CHANGELOG `## x.y.z` bölümü
- [ ] `php -l`, `bin/test-update.sh` önceki→yeni ve temiz kurulum geçti, paketten uyarı yok
- [ ] Ekran görüntüleri (desktop/mobil, light/dark)
- [ ] Branch'e push, kullanıcı onayı → main; Actions yeşil, release açıldı
- [ ] Zip + KURULUM.md `/mnt/project-files`'ta, `docs/` güncel, hafıza güncel
