# Flux Migrate

Eski paket adlarıyla kurulmuş bir siteyi FluxWP adlarına geçirir. Ad eşlemesi `map.json`'dadır, eklenti kodunda sabit ad yoktur.

## Kullanım

1. Tam yedek alın (veritabanı + `wp-content/uploads`).
2. Yeni FluxWP eklentilerini ve temasını yükleyin, **etkinleştirmeyin**.
3. Bu eklentiyi kurup etkinleştirin: **Araçlar › Flux Migrate**.
4. **Tara**: hiçbir şeyi değiştirmeden her adımda kaç kayıt değişeceğini gösterir.
5. **Aktar**: verileri taşır. Etkin eklenti listesi ve tema da yeni adlara geçer.
6. Eski eklentileri silin, önbelleği temizleyin (LiteSpeed › Purge All), bu eklentiyi silin.

WP-CLI: `wp flx-migrate scan` ve `wp flx-migrate run`.

## Neyi taşır

| Adım | Ne yapar |
|---|---|
| Tablolar | `{prefix}<eski>` tablolarını yeni adla yeniden adlandırır (hedef boşsa önce siler) |
| Tablo içerikleri | Bu tabloların metin sütunlarındaki eski tanımlayıcılar |
| Ayarlar | Option adları ve değerleri (serileştirilmiş/JSON değerler içinde de), etkin eklentiler, roller ve yetkiler, cron kancaları; eski geçici veriler (transient) silinir |
| Tema | `stylesheet`/`template` ve `theme_mods_*` |
| Meta | post/user/term meta anahtarları ve değerleri |
| Yazılar | Yazı türleri; içerikteki blok adları, kısa kodlar, class/id/data öznitelikleri, `wp-content/...` yolları |
| Taksonomiler | Taksonomi adları, blok şablonlarının tema terimi |
| Yükleme klasörleri | `uploads/<eski>` → `uploads/<yeni>` |

Her adım tekrar çalıştırılabilir; ikinci çalıştırmada değişecek bir şey kalmaz.
