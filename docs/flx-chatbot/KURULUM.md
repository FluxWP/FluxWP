# Flux AI Chatbot 0.1.0 · Kurulum

Sitede yapay zekâ destekli sohbet penceresi. Asistan Flux AI & MCP üzerinden cevap verir, gerekince sohbeti Flux Chat'teki
canlı temsilciye (ya da eklentinin kendi gelen kutusuna) devreder.

## Gerekenler
- **Flux AI & MCP** (asistan için). Yoksa pencere yalnız canlı destek olarak çalışır.
- **Flux Chat 1.37.0+**: çağrı merkezi tek gelen kutusu olur; tüm site sohbetleri ilk mesajdan orada, kanal ikonuyla görünür, ayrı "Sohbetler" ekranı açılmaz. Yoksa Chatbot › Sohbetler + e-posta bildirimi.
- İsteğe bağlı: WooCommerce (ürün kartları, sipariş durumu, sohbet sonrası sipariş), Flux CRM (iletişim bırakan kişi), Flux Wiki.

## Kurulum
1. FluxWP Panel › Paketler › **Flux AI Chatbot › Kur** (ya da `flx-chatbot.zip` yükle, etkinleştir). Flux Chat'i 1.37.0'a güncelle.
2. Flux AI & MCP › Bağlı sistemler › **Flux AI Chatbot**: açık olduğunu gör; hızlı ve ucuz bir model seç (ör. Haiku / mini sınıfı).
3. Yönetim › **Chatbot › Ayarlar**:
   - Genel: **Sitede göster** aç, çalışma şekli "AI + canlı destek".
   - Görünüm: renk boş = tema rengi. Avatar, başlatıcı simgesi, konum. Sağdaki önizlemede anında görünür.
   - Metinler: hazır sorular, iletişim bağlantıları (`WhatsApp | https://wa.me/90530…`), tanıtım balonu.
   - Yapay zekâ: kişilik, kurallar, **bilgi bankası** (kargo, iade, saatler, SSS), kaynaklar, sınırlar.
   - Canlı destek: devir hedefi Otomatik (Flux Chat varsa orası), mesai saatleri, bildirim e-postası.
   - Görünürlük: sayfalar, cihaz, hariç adresler (`/odeme/*`), gizlilik notu, saklama süresi.
4. **Kaydet** → **Sitede dene** ile ön yüzde dene. LiteSpeed › Purge All.

## Notlar
- Önizleme ve "Sitede dene" gerçek asistanı kullanır; denemeler Flux Chat'te (yoksa Sohbetler'de) görünür, silinebilir.
- Herkese açık AI: Flux AI'daki günlük harcama sınırı ana koruma; ayrıca sohbet başına cevap, saatlik ve günlük toplam sınırları var.
- IP saklanmaz. Dosyalar `uploads/flx-chatbot/` altında, doğrudan erişime kapalı; yalnız sohbet sahibi ve temsilci açar.
- Hostinger: cevaplar akışlı (NDJSON) gelir, istek ~40 sn'de kesilir. LiteSpeed REST'i önbelleğe almaz (no-store başlıkları).
- Temsilci cevabı: ziyaretçi 60 sn'den uzun süredir sayfada değilse ve e-posta verdiyse cevap e-postayla da gider (en fazla 3 dk'da bir).
- Sayfadan açma: `[flx_chatbot label="Bize yazın"]`, `<a href="#flxcb-open">`, `data-flxcb-open`, `data-flxcb-message="…"`, `FluxChatbot.open()`.

## Test kapsamı
WP 6.8.3 + SQLite, FluxWP tema, Flux Admin + Flux Chat 1.37.0. Flux AI bu ortamda ağ olmadığından **sahte model** ile:
akışlı cevap + site araması aracı, temsilciye devir aracı, Flux Chat'e düşme, Flux Chat'ten temsilci cevabı (ziyaretçide görünür),
okundu bilgisi, iletişim formu, ayarlar + canlı önizleme, gelen kutusu, TR ve EN. WooCommerce bu ortamda yoktu.
