# Değişiklikler

## 1.3.5
- Telefon numaraları tüm eklentilerde aynı kuralla okunur: `+90 0532…` yazımındaki fazla sıfır atılır, ülke kodu ve baştaki sıfır olmadan yazılan 10 haneli sabit hat (212…) 90 ile tamamlanır, hane sayısı ya da alan kodu tutmayan Türkiye numarası geçersiz sayılır.

## 1.3.4
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 1.3.3
- Telefon kimliği son 10 haneyle değil, ülke kodlu tam numarayla eşleşir. Ülke kodu yazılmayan yerel numara varsayılan 90 ile tamamlanır; iki farklı tam numara aynı kullanıcıya bağlanmaz.

## 1.3.2
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 1.3.1
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 1.3.0
- Uygulama menüsü rol matrisini web görünümünde açar.

## 1.2.2
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 1.2.1
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 1.2.0
- Roller ve erişim sayfasına Flux Studio yetkileri: Stüdyo'yu kullanır (`flxst_use`), Stüdyo'yu yönetir (`flxst_manage`).
- Yönetici rolü bu iki yetkiyi bir kez alır; Personel ve İdareci için sayfadan açılır.

## 1.1.0
- Yeni rol: Yönetici (bu sayfadaki her yetki; WordPress yöneticisi değil).
- Panelden yeni rol ekleme ve silme (ör. Gönüllü); yeni rol yalnız panele girme yetkisiyle başlar.
- Kullanıcıya WhatsApp numarası (yalnız yönetici yazar, bir numara tek kişide); asistan için numaradan kişi ve yetki bulma.
- Yeni yetkiler: başkası adına bağış (referans kendisi), kendi bağışçıları, referans bildirimi, WhatsApp asistanında personel araçları, raporlar (alır / görür / zamanlar), Görev/Sertifika/Çağrı yönetimi.
- Personel ve İdareci'ye yeni varsayılan yetkiler bir kez eklenir.

## 1.0.0
- Yeni roller: Personel ve İdareci.
- Kullanıcılar › Roller ve erişim: iki rolün Görev, Sertifika, Bağış ve Çağrı Merkezi erişimini aç/kapat; kişileri ekle/çıkar; girişte açılan ekran.
- Kişisel referans linki (?ref=): linkle gelen ziyaretçinin bağış siparişine referans kişi yazılır. Profil ve Roller sayfasında kopyalanabilir link.
- Görev yetkileri Flux Tasks'in rol ayarıyla eşitlenir; Personel görevlerde atanabilir kişi olur.
- Sistem kontrolü: roller ve henüz uygulanmayan yetkiler.
