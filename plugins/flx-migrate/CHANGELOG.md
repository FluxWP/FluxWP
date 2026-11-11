# Değişiklikler

## 0.1.9
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 0.1.8
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Preserve singular/plural message boundaries and printf arguments in English source IDs and all native language packs; prevent count labels from breaking populated screens.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 0.1.7
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 0.1.6
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.


## 0.1.5
- Yeni eklenti aktarımdan önce etkinleştirilip kendi tablolarını doldurduysa (ör. Flux Tasks hoş geldin görevi) eski tablo artık atlanmıyor: yeni tablo `<ad>_bakAAGG` adıyla yedeklenir, eski veri onun yerine taşınır. Eski tablo boşsa yeni tabloya dokunulmaz. Aktarım yeniden çalıştırılınca önceden atlanan tablolar da taşınır.
- Yeni adım **Şifreli ayarlar**: eski önekten türetilen anahtarla şifrelenmiş değerler (Flux Mail SMTP/IMAP parolaları ve WhatsApp/SMS bağlantı anahtarları, Flux AI anahtarları, Flux Social hesap jetonları, iyzico kart referansları) yeni anahtarla yeniden şifrelenir. Önceden ad eşlemesi şifreli metnin içini de değiştirebiliyordu; artık şifreli değerlere dokunmaz.
- **Eski kalıntılar** bölümü (aktarım bittikten sonra Araçlar › Flux Migrate'te): eski adlı tablolar, `_bak` yedek tabloları, eski yükleme klasörleri, etkin olmayan eski eklentiler, eski temalar (varsayılan seçili değil) ve eski ayarlar listelenir; seçilenler onayla silinir.
- Ad eşlemesi YITH (`yith_*`) gibi başka eklentilerin adlarına dokunmaz.
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
