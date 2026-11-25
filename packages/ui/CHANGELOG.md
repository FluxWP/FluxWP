# @fluxwp/ui

## 0.5.0
- `@fluxwp/ui/forms`: Flux Forms formları React'te (headless siteler için). `FluxForm` flxForm verisini çizer, adımları, doğrulamayı (zorunlu, e-posta, telefon, URL, uzunluk, sayı/tarih sınırı, seçim sayısı), alan genişliklerini, toplam tutarı ve sunucu hatalarını yönetir; flxFormSubmit girdisini (`formId`, `fields`, `honeypot`, `captchaToken`) verir. Her alan türünün kendi bileşeni var (metin, seçim, çoklu seçim, görselli seçim, evet/hayır, anahtar, dosya, yıldız, 1-5, 0-10, başlık, bağlantı, HTML, toplam); `components` ile değiştirilebilir. Metinler EN + TR. Stil `@fluxwp/ui/forms.css`, açık ve koyu.

## 0.3.4
- `Screen` and `Tabs` (shared screen shell: title, tabs, padding, max width, safe area) are exported, so external plugin screens look like the Flux screens.

## 0.3.3
- npm: README with install and usage, description and keywords.

## 0.3.2
- Modal penceresi ve bildirim (toast) wp-admin'de sol menünün ve üst çubuğun üstünde açılır (z-index 100100). Önceden sol menü pencerenin üstünde kalıyordu.

## 0.3.1
- `sanitizeHtml` drops scripts, event handlers, forms and unsafe URLs. Image `data:` URLs are limited to png, jpeg, gif and webp. Links get `rel="noopener noreferrer"` and open outside the app.

## 0.3.0

- Figtree, Nunito ve Nunito Sans gömülü. Başlıklar heading ailesi ve 800 ağırlık. Birincil düğme 40px, ağırlık 700.
