# Tanıtım medyası ve README üretici

Her paketin `README.md` (EN) + `README.tr.md`, `docs/demo.gif`, `docs/demo-tr.gif`, `docs/thumb.gif` ve
`docs/images/<ad>-en|tr.jpg` dosyaları buradaki betiklerle üretilir. Paket güncellenince ekranları ve GIF'i
tek komutla yeniden çekmek için.

## Hızlı kullanım

```bash
# 1) Bir kez: yerel test sitesi (WP 6.8.3 + SQLite + WooCommerce + repodaki tüm paketler symlink)
bash tools/readme-media/setup.sh            # WPENV=/tmp/wpenv varsayılan

# 2) Her oturumda (setup zaten çağırır): site 8080, sahte güncelleme sunucusu 8091, sahte Evolution 8092
bash tools/readme-media/serve.sh

# 3) Bir paketi baştan üret: seed → EN/TR ekranlar → EN/TR GIF → docs/ → README'ler → zip kontrolü → kök tablo
bash tools/readme-media/run.sh flx-ai                 # plugins/flx-ai
bash tools/readme-media/run.sh fluxwp themes   # tema
bash tools/readme-media/run.sh flx-seo plugins --no-gif   # yalnız ekranlar + README
```

Sonra `git diff --stat plugins/<slug>` ile bakın, görselleri kontrol edin, commit + PR.
Yalnız metin değiştiyse: `python3 tools/readme-media/gen.py tools/readme-media/text/<slug>.py`.

Gereken: php (sqlite3, gd/imagick), node + Playwright (Chromium), ffmpeg, python3. Giriş `admin / admin12345` (yalnız yerel).

## Dosyalar

| Yol | Ne |
|---|---|
| `cfg/<slug>.json` | Çekim tarifi: `seed` (çalıştırılacak seed'ler), `css` (gizlenecekler), `shots` (masaüstü), `dark`, `mobile`, `gif` (adımlar) |
| `text/<slug>.py` | README metni EN + TR: tagline, desc, badges, why, features, shots, req, first, note, mcp, dev, roadmap |
| `seed/*.php` | Demo verisi (bağışlar, sohbetler, AI kayıtları, Stüdyo galerisi…). Gerçek kişi/anahtar yok |
| `shoot.js` | `node shoot.js cfg/x.json en|tr <çıktı>`: ekran görüntüleri (2x, JPEG) |
| `gif.js` + `mkgif.sh` | Fare imleçli video kaydı → GIF (800 px, 7 fps) |
| `place.sh` | Logo (c2pa metadata silinir), görseller ve GIF'leri `docs/` altına koyar, `thumb.gif` üretir, `.distignore`'a ekler |
| `gen.py` | `text/<slug>.py` → `README.md` + `README.tr.md` |
| `sheet.py` | Önizleme için görselleri tek sayfada toplar |
| `env/` | wp-config, mu-plugin'ler (SQLite uyumu, ön yüz dili, yapay yük), sahte sunucular, `connect-mocks.php` |
| `legacy-flx-analytics/` | Flux Analytics'in ilk (eski) çekim betikleri |

## cfg alanları

```jsonc
{
 "seed": ["seed-ai.php"],
 "slug": "flx-ai",
 "css": "#flxai-host{display:none!important}",        // tüm çekimlerde
 "shots": [
  {"name":"overview","url":"admin.php?page=flx-ai","vh":1000,"after":1500},
  {"name":"chat","url":"admin.php?page=flx-ai","click":["#flxai-host .bubble"],"css":"html body #flxai-host{display:block!important}"},
  {"name":"editor","url":"post.php?post=12&action=edit","wait":5000}
 ],
 "dark":   [{"name":"dark","url":"...","vh":900}],    // Flux Admin koyu tema
 "mobile": [{"name":"mobile","url":"..."}],           // 390 px
 "gif": [{"goto":"admin.php?page=flx-ai","wait":2500},{"move":[700,420],"wait":700},
         {"click":"text=90","css":true,"wait":1500},{"scroll":350,"wait":1200}]
}
```

Diğer çekim anahtarları: `eval` (sayfada JS), `keys`, `type` (`{"en":…, "tr":…}`), `hover`, `sel` (yalnız seçilen öğeler, `pad`), `clip`, `full` (tam sayfa).

## Bilinen tuzaklar

- 120 sn'den uzun işleri arka planda çalıştırın (GIF kaydı 2–4 dk).
- SPA'larda (Sosyal) hash değişimi sayfayı yenilemez: URL'ye `&r=1` gibi sahte parametre ekleyin.
- Flux AI sohbet düğmesi `#flxai-host` açık kalırsa sonraki çekimlere taşar: genelde gizli, yalnız sohbet çekiminde açık.
- Seed sonrası kayıt kimlikleri değişebilir; cfg'deki `post=`/`id` değerlerini kontrol edin.
- WP çekirdeğinin TR dil paketi yok: WP menüleri TR ekranlarda da İngilizce kalır.
- `.distignore` yorumunda kesme işareti olmasın (`build.sh` xargs ile bozulur). Zip'te `docs/` ve README olmamalı.
- Çekim sonrası ekran görüntülerini Ahmet'e göstermek için `/mnt/project-files/<paket>/` altına koyun.

## Kök README (FluxWP Suite)

- `README.md` (EN) ve `README.tr.md` (TR) elle yazılır; aradaki galeri + sürüm tablosu `bin/readme-table.sh README.md` / `README.tr.md` ile üretilir (EN ad ve açıklamalar `bin/readme-desc.en.tsv`, TR `bin/readme-desc.tsv`). Release workflow'u ikisini de günceller.
- Hero kolajı: `node tools/readme-media/suite/hero.js en docs/suite/hero.jpg` ve `... tr docs/suite/hero-tr.jpg` (paket ekranlarından üretilir; ekranlar değişince yeniden çalıştırın).
- Yeni paket: iki TSV'ye satır ekleyin, kök README'lerde uygun çözüm grubuna bir satır ekleyin.
