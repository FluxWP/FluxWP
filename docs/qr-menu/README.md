# QR Menü sistemi

Üç parça:

- **Flux QR Menu** eklentisi (`plugins/flx-qr-menu`, prefix `flxq_`): menüler WordPress panelinden (sol menü **QR Menüler**)
  yönetilir; birden çok menü, menü başına bağlantı anahtarı, bağlı menü siteleri, tüm değişikliklerin okunur özetli geçmişi ve geri alma.
  1.1.0'dan itibaren menüler aynı sitede de yayınlanır (`/menu/`, `menu1.alanadi`, isteğe bağlı ana sayfa), Paylaş sekmesinde QR kod,
  canlı güncelleme ve yalnız kendi menülerini düzenleyen **Menü yöneticisi** rolü var.
- **QR Menü sitesi** (ayrı repo): istenen her alan adına kurulur, `/admin/` panelinde bağlantı adresi +
  menü anahtarı girilir; veriyi eklentinin veri ucundan çeker, anlık bildirimle günceller.
- **Flux Menu Theme** teması (`themes/flx-menu-theme`): menu.example.org için boş beyaz ana sayfalı en sade block theme; menüleri eklenti yayınlar.

Belgeler:

| Dosya | İçerik |
|---|---|
| [KURULUM.md](KURULUM.md) | Eklentiyi kurma, menü oluşturma / içe aktarma, düzenleme, geçmiş, bu sitede yayınlama ve QR, menü yöneticisi, menü sitesini bağlama, **menu.example.org kurulumu** (Hostinger joker alt alan adı, SSL, kalıcı bağlantılar) |
| [API.md](API.md) | Veri ucu (`/wp-json/flx-qr-menu/v1/feed`), anlık bildirim imzası, bu sitedeki menü sayfaları ve sürüm ucu (`/rev/<id>`), yönetim REST uçları, yetkiler, geçmiş tablosu, MCP |

Ortak bağlantı sözleşmesi proje klasöründe: `/mnt/project-files/qr-menu-sistem/SOZLESME.md`. Ekran görüntüleri ve zip'ler aynı klasörde
(`ekran-goruntuleri/wp-*.png`, `ekran-goruntuleri/yf-*.png`, `flx-qr-menu.zip`, `flx-qr-menu-1.1.0.zip`, `flx-qr-menu-1.2.0.zip`, `flx-qr-menu-1.3.0.zip`, `ekran-goruntuleri/onizleme-*.png`, `ekran-goruntuleri/ornek-gorseller.png`, `flx-menu-theme.zip`).

Çeviri: kaynak Türkçe. Yeni metin eklenince `tools/en.json`'a İngilizcesini yazıp `python3 plugins/flx-qr-menu/tools/genstr.py`
çalıştırın (`includes/js-strings.php`, `.pot`, `en_US` `.po`/`.mo`/`.l10n.php` üretilir).
