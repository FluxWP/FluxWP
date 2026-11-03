# Değişiklikler

## 0.1.5
- Yeni eklenti aktarımdan önce etkinleştirilip kendi tablolarını doldurduysa (ör. Flux Tasks hoş geldin görevi) eski tablo artık atlanmıyor: yeni tablo `<ad>_bakAAGG` adıyla yedeklenir, eski veri onun yerine taşınır. Eski tablo boşsa yeni tabloya dokunulmaz. Aktarım yeniden çalıştırılınca önceden atlanan tablolar da taşınır.
- Yeni adım **Şifreli ayarlar**: eski önekten türetilen anahtarla şifrelenmiş değerler (Flux Mail SMTP/IMAP parolaları ve WhatsApp/SMS bağlantı anahtarları, Flux AI anahtarları, Flux Social hesap jetonları, iyzico kart referansları) yeni anahtarla yeniden şifrelenir. Önceden ad eşlemesi şifreli metnin içini de değiştirebiliyordu; artık şifreli değerlere dokunmaz.
- Yükleme klasörleri: yeni klasör zaten varsa (eklenti önceden etkinleştirildiyse) eski dosyalar içine taşınır; yeni klasördeki aynı adlı dosyalar korunur, eski klasör boşalınca silinir.
- Yeni adımlar **WooCommerce siparişleri** (HPOS `wc_orders_meta` ve ödeme yöntemi, ör. `ytb_havale` → `flxb_havale`) ve **Sipariş kalemleri** (`woocommerce_order_itemmeta`).

## 0.1.4
- Bitiş adımı eski güncelleme sunucusunun (update.yesilturkiyeist.org) adresini ve site anahtarını Flux Setup ayarlarından siler; site varsayılan sunucuya (updater.fluxwp.com) döner, yeni anahtar FluxWP Panel'den girilir. Liste `flx_migrate_old_update_hosts` filtresiyle değişir.

## 0.1.3
- Eski YT Güncelleyici artık Flux Setup'a aktarılır (Flux Updater ayrı paket olarak kalmadı).

## 0.1.2
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 0.1.1
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 0.1.0
- İlk sürüm: eski paket adlarıyla kaydedilmiş verileri FluxWP adlarına taşır (tablolar, tablo içerikleri, ayarlar, tema ve tema ayarları, meta, yazı türleri ve içerikteki blok/kısa kod/class adları, taksonomiler, yükleme klasörleri).
- Araçlar › Flux Migrate: önce Tara (değişiklik yapmaz), sonra Aktar. Parça parça çalışır, yarıda kalırsa yeniden başlatılabilir.
- WP-CLI: `wp flx-migrate scan`, `wp flx-migrate run`.
