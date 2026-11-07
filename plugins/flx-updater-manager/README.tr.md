# Flux Updater Manager

FluxWP güncelleme sunucusunu ([Flux Update Server](https://github.com/FluxWP/updater) 1.12.0+) bir WordPress panelinden, sunucunun /admin paneliyle aynı verilerle yönetir.

- **Genel bakış:** aktif ve anahtarlı siteler, kontrol isteği, indirme, aktarılan veri, günlük grafik, paketler, ülke haritası ve bağlanan siteler (7/30/90 gün). Anahtarsız bağlanan siteye "Anahtar ver".
- **Ülkeler:** ülke bazında trafik, engellenen istekler, ülkeye göre paketler.
- **Siteler:** site anahtarı üret (bir kez gösterilir), erişimi düzenle (tüm private paketler ya da seçilen depo/paketler), kanal, pasif yap, sil, anahtar testi.
- **Paketler:** sürüm, erişim ve yayın tarihiyle katalog; GitHub'dan yeniden oku.
- **GitHub:** private/public depolar, herkese açık paketler, uygulama ve masaüstü depoları, token (yalnız yazılır), anında yayın webhook secret'ı.
- **Güvenlik:** acil durdurma, anahtarsız dağıtım, ülke erişimi kuralı, ülke veritabanı (indir, sil, IP sorgula).
- **Kaynaklar:** disk, data/ dağılımı, GitHub API kotası, PHP; zip saklama ve temizlik, erişim günlüğü.
- **Bağlantı:** sunucu adresi ve yönetici anahtarı (şifreli saklanır), diğer yönetici anahtarları, sunucu paneli parolası.

## Bağlanma

1. Sunucuda: /admin › **Updater Manager** › ad yazın › *Yönetici anahtarı üret*. `flxm_…` anahtarını kopyalayın (bir kez gösterilir).
2. Burada: **Updater › Bağlantı**, sunucu adresi (varsayılan bu sitedeki FluxWP Panel'in sunucusu, yoksa `https://updater.fluxwp.com`) + anahtar › *Kaydet ve bağlan*.

wp-config.php'deki `FLXUM_SERVER` ve `FLXUM_KEY` alanları ezer. Yönetici anahtarı üretme ve ülke veritabanı dosyası yükleme sunucu panelinde kalır. Sunucuda anahtar silinince erişim hemen kesilir; her değişiklik sunucuda `manager:<anahtar adı>` olarak günlüğe yazılır.

WordPress 6.4+, PHP 7.4+. Yalnız `manage_options` yetkisi olan kullanıcılar.

English: [README.md](README.md)

## Yapay zekâ araçları

**Flux AI & MCP** etkinse eklenti ortak sohbete ve MCP sunucusuna `flxum_*` araçlarını ekler (Flux AI SDK). Okuma: `overview`, `connected_sites`, `keyed_sites`, `packages`, `security`, `resources`, `test_key`. Değiştirme (her seferinde onaylı): `add_site`, `update_site`, `toggle_site`, `delete_site`, `issue_key`, `set_switch`, `set_country_access`, `set_public_packages`, `refresh_packages`, `set_zip_retention`, `update_country_db`. Yeni site anahtarı modele gönderilmez; Updater › Siteler ekranında bir kez gösterilir. Yönetici anahtarları, panel parolası, GitHub token'ı ve webhook secret'ı araçlarda yok.
