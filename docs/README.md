# Paket belgeleri

Proje klasöründeki (`/mnt/project-files/<klasör>/`) kurulum ve API belgelerinin kopyası. Dosya adları aynı;
ekran görüntüleri ve zip'ler repoda yok (belgelerdeki görsel bağlantıları proje klasörüne göredir).
Bir paket güncellenince belgesi hem proje klasöründe hem burada güncellenir.

| Klasör | Paket | Belgeler |
|---|---|---|
| `bagis-eklentisi/` | Flux Donate (`plugins/flx-donate`) | KURULUM, **API** (sistem kontrolü ortak sözleşmesi dahil), birleşme planı, diğer sahipler için notlar |
| `form-builder/` | Flux Forms (`plugins/flx-forms`) | KURULUM |
| `sertifika-sistemi/` | Flux Certificates (`plugins/flx-certificates`) | KURULUM |
| `iletisim/` | Flux Mail (`plugins/flx-mail`) | KURULUM, **API**, ENTEGRASYON |
| `cagri-merkezi/` | Flux Chat (`plugins/flx-chat`) | KURULUM |
| `gorev-takip/` | Flux Tasks (`plugins/flx-tasks`) | KURULUM |
| `guncelleme-sistemi/` | Flux Updater + wp-update-server | KURULUM, logolar/KULLANIM |
| `yedek-eklentisi/` | Flux Backup (`plugins/flx-backup`) | KURULUM, API |
| `seo-mcp/` | Flux SEO & MCP (`plugins/flx-seo`) | KURULUM, **MCP-API** (`flxseo_mcp_tools`, `flx_system_fixes`), SEO raporu |
| `flx-admin/` | Flux Admin (`plugins/flx-admin`) | KURULUM |
| `flx-roles/` | Flux Roles (`plugins/flx-roles`) — Personel/İdareci rolleri, ortak yetki ve referans sözleşmesi | KURULUM, API |
| `flx-ai/` | Flux AI (`plugins/flx-ai`) — ortak yapay zekâ: anahtarlar, modeller, harcama sınırı, raporlar, ortak sohbet | KURULUM, **API** (`flxai_chat`, `flxai_tools`) |
| `flx-lang/` | Flux Lang (`plugins/flx-lang`) — çok dilli site: /en/ adresleri, içerik çevirileri, hreflang, dil önerisi, AI çeviri araçları | KURULUM, **API**, diğer sahipler için |
| `flx-speed/` | Flux Speed (`plugins/flx-speed`) — geliştirici paneli, yavaş/hatalı istek kaydı, sunucu/veritabanı/autoload/cron, Sistem Kontrolü ekranı; MCP ile okunur | KURULUM, **API** (`flx_log`, `flxp_*` araçları) |
| `flx-analytics/` | Flux Analytics (`plugins/flx-analytics`) — çerezsiz site istatistikleri (Koko Analytics yerine), olaylar, bağış dönüşümü; MCP ile okunur | KURULUM, **API** (`flxan_*` araçları, `flxan_track_event`) |

Yeni paket yazarken önce `.claude/skills/` altındaki skill'lere bakın: `flx-wp-eklenti`, `flx-wp-tema`, `flx-paket-yayin`.
