<p align="center"><img src="docs/logo.svg" width="96" alt=""></p>

# Flux Ads

[English](README.md)

Hangi reklam hangi bağışı getirdi. Flux Ads bağış ve sipariş dönüşümlerini **Meta (Pixel + Conversions API)**, **TikTok (Pixel + Events API)** ve **Google Analytics 4 (Measurement Protocol)**'e gönderir; her kanalın, kampanyanın ve reklamın ne getirdiğini kendi sitenizde gösterir.

- **Sunucu olayları** tarayıcıyla aynı olay kimliğiyle gider: reklam engelleyici ve günler sonra onaylanan havaleler de sayılır, iki kez sayılmaz.
- **Havale/EFT'ye uygun**: dönüşüm teşekkür sayfasında değil, havale onaylanınca gider.
- **Önce onay**: pixel'ler ziyaretçi çerez banner'ını kabul edince başlar. E-posta ve telefon yalnız hash'li ve varsayılan olarak yalnız reklam izni olan kişide gider.
- **Rapor**: kanal, kampanya, reklam (utm_content), kaynak/ortam, açılış sayfası, ilk reklam teması, yeni bağışçı. Reklam bağlantısı (UTM) oluşturucu.
- **Harcama ve ROAS**: Meta harcaması kampanya ve reklam bazında reklam hesabından okunur; diğer harcamalar elle girilir. Rapor harcamayı, ROAS'ı, ödeme başı ve yeni bağışçı başı maliyeti gösterir.
- **Flux CRM**: kişinin zaman tünelinde reklam dönüşümü ve "Reklam kaynağı" kartı.
- **Flux AI / MCP**: `flxads_report`, `flxads_status`.

WooCommerce gerekir. Flux Donate ile çalışır; Flux CRM isteğe bağlıdır.

## Bakımcılar

Ahmet Yılmaz ([@sharkpaw](https://github.com/sharkpaw)).
