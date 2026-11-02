# Flux Admin: güvenli alan ve modal sözleşmesi (1.3.1+)

Panel kabuğu ekranın kenarlarını kaplıyor: üst çubuk, sol menü (geniş, daraltılmış ya da uygulama modu şeridi), sağda yerleşik Flux AI paneli ve mobilde alt bar. Tam ekran sayfalar ve modallar bunların altına girmesin diye Flux Admin bu boşlukları yayınlıyor.

## CSS değişkenleri (`html` üzerinde)

| Değişken | Ne |
|---|---|
| `--flxa-safe-top` | Üst çubuğun alt kenarı (yoksa 0) |
| `--flxa-safe-left` | Sol menünün sağ kenarı (783 px ve üstü; mobilde 0, menü çekmece) |
| `--flxa-safe-right` | Sağa yerleşik Flux AI paneli genişliği (1024 px ve üstü) |
| `--flxa-safe-bottom` | Mobil alt bar yüksekliği (klavye açıkken 0) |

Değerler piksel cinsinden. Menü daraltılınca ya da açılınca, pencere boyutu değişince ve Flux AI paneli açılıp kapanınca güncelleniyor. Flux Admin yoksa değişkenler tanımsız olur, bu yüzden her zaman yedek değer verin:

```css
.benim-app { position: fixed; top: var(--flxa-safe-top, 32px); left: var(--flxa-safe-left, 0px); right: var(--flxa-safe-right, 0px); bottom: var(--flxa-safe-bottom, 0px); }
```

## JS

- `window.FLXA.safeArea()` şunu döner: `{ top, right, bottom, left }` (sayı, px).
- Değer değişince `window`'a `flxa:safearea` olayı gider. `event.detail` aynı nesneyi taşır.

## Modallar

- Modalı `role="dialog" aria-modal="true"` ya da `data-flxa-modal` ile işaretleyin. Flux Admin modalı görünür olduğu sürece `html.flxa-modal-open` sınıfını koyar.
- Modalın perdesi sol menüyü ve üst çubuğu örtmüyorsa (ör. perde uygulama kutusunun içinde kalıyorsa) Flux Admin onları da karartır (`html.flxa-dim-side`, `html.flxa-dim-bar`) ve tıklanamaz yapar. Perde zaten tüm ekranı örtüyorsa ikinci kez karartmaz.
- İçerik alanını (güvenli alanın içini) opak arka planla tümüyle kaplayan modal tam sayfa sayılır, kabuk karartılmaz (1.3.2+).
- Karartma istemeyen modal kendine (ya da bir üst öğesine) `data-flxa-nodim` koyar.
- DOM'da görünür bir modal öğesi yoksa elle bildirin: açarken `FLXA.modal(true)`, kapatırken `FLXA.modal(false)` (çağrılar sayılır, her açışa bir kapatış).
- Mobilde modal açıkken alt bar arkaya alınır. Modalın Kaydet düğmeleri alt barın altında kalmaz.
