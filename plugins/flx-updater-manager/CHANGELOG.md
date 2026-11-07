# Flux Updater Manager

## 0.2.0
- Flux AI & MCP araçları (Flux AI SDK): sohbetten ve MCP'den sunucu verisi okunur, ayarlar değiştirilir. Okuma: genel bakış, bağlanan siteler, anahtarlı siteler, paketler, güvenlik, kaynaklar, anahtar testi. Değiştirme (her seferinde onaylı): site ekle/düzenle/aktif-pasif/sil, anahtar ver, acil durdurma, anahtarsız dağıtım, ülke erişimi, herkese açık paketler, paketleri yeniden oku, zip saklama, ülke veritabanı.
- Yeni site anahtarı yapay zekâya gönderilmez; Siteler ekranında bir kez gösterilir. Yönetici anahtarları, panel parolası, GitHub token'ı ve webhook secret'ı araçlarda yok.

## 0.1.0
- İlk sürüm: güncelleme sunucusu (Flux Update Server 1.12.0+) WordPress panelinden yönetilir. Sekmeler: Genel bakış, Ülkeler, Siteler, Paketler, GitHub, Güvenlik, Kaynaklar, Bağlantı.
- Sunucunun yönetici anahtarıyla bağlanır; anahtar bu sitede şifreli saklanır. Yeni site anahtarı bir kez gösterilir.
- Grafik ve harita sunucu paneliyle aynı; ülke ve depo seçicileri, TR (kaynak) + EN.
