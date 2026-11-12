<p align="center"><img src="docs/logo.svg" width="96" alt=""></p>

# Flux Ads

[English](README.md)

Hangi reklam hangi bağışı getirdi. Flux Ads bağış ve sipariş dönüşümlerini **Meta (Pixel + Conversions API)**, **TikTok (Pixel + Events API)** ve **Google Analytics 4 (Measurement Protocol)**'e gönderir; her kanalın, kampanyanın ve reklamın ne getirdiğini kendi sitenizde gösterir.

- **Sunucu olayları** tarayıcıyla aynı olay kimliğiyle gider: reklam engelleyici ve günler sonra onaylanan havaleler de sayılır, iki kez sayılmaz.
- **Havale/EFT'ye uygun**: dönüşüm teşekkür sayfasında değil, havale onaylanınca gider.
- **Önce onay**: pixel'ler ziyaretçi çerez banner'ını kabul edince başlar. E-posta ve telefon yalnız hash'li ve varsayılan olarak yalnız reklam izni olan kişide gider.
- **Rapor**: kanal, kampanya, reklam (utm_content), kaynak/ortam, açılış sayfası, ilk reklam teması, yeni bağışçı. Reklam bağlantısı (UTM) oluşturucu.
- **Harcama ve ROAS**: Meta harcaması kampanya ve reklam bazında reklam hesabından okunur; diğer harcamalar elle girilir. Rapor harcamayı, ROAS'ı, ödeme başı ve yeni bağışçı başı maliyeti gösterir.
- **Bağışçı değeri (LTV)**: kişi ilk ödemesini getiren kampanyaya yazılır, sonraki ödemeleri de o kampanyaya sayılır. Kampanya başına yeni bağışçı, 90 günlük ve bugüne kadarki değer, tekrar oranı, bir kişiyi kazanma maliyeti (CAC), değer / maliyet ve geri dönüş süresi; aylık kohort tablosu.
- **Meta kitleleri**: Flux CRM'deki bir grubu (reklam izni veren herkes, bağışçılar, değer grubu ya da kayıtlı segment) Meta'ya özel hedef kitle olarak gönderir; yalnız reklam izni olanlar, hash'li. Her gün güncellenir; izni geri alan hemen çıkarılır. Benzer kitle ve hariç tutma listesi (ör. yeni bağışçılar) dahil.
- **Flux CRM**: kişinin zaman tünelinde reklam dönüşümü ve "Reklam kaynağı" kartı.
- **Flux AI / MCP**: rapor, bağışçı değeri, harcama, kitleler ve gönderim kaydını okur; ayar değiştirir, harcama ekler/siler, Meta harcamasını günceller, kitle oluşturur/günceller/siler, bağlantıyı test eder, dönüşümü yeniden gönderir. Her değişiklik onayınla yapılır; gizli anahtarlar asla açılmaz.

WooCommerce gerekir. Flux Donate ile çalışır; Flux CRM isteğe bağlıdır.

## Bakımcılar

Ahmet Yılmaz ([@sharkpaw](https://github.com/sharkpaw)).
