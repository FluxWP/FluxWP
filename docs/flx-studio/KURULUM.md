# Flux Studio: kurulum

Yapay zekâ ile görsel ve video stüdyosu (Banana Studio uyarlaması). Üretim Flux AI üzerinden yapılır.

1. Önce **Flux AI 0.6.0+** kurulu olmalı. Flux AI › Ayarlar › **Görsel ve video** bölümüne en az bir anahtar girin (fal.ai ve EvoLink önerilir; OpenAI, Gemini, OpenRouter da olur).
2. Güncellemeler'den **Flux Studio**'yu kurup etkinleştirin. Sol menüde **Stüdyo** çıkar.
3. Yetki: yöneticide hazır. Personele açmak için Flux Roles › Roller ve erişim'de **Stüdyo: görsel/video üret** (`flxst_use`) işaretlenir. **Stüdyo: tüm galeriler ve ayarlar** (`flxst_manage`) herkesin üretimini görür ve ayarları değiştirir.
4. İsteğe bağlı: Stüdyo › sol alt ⚙ ile varsayılan görsel/video modeli, bir seferde en çok görsel, Beyin fırtınası fikir sınırı.
5. Araçlar › Sistem kontrolü'nde "Stüdyo" satırları yeşil olmalı.

Kullanım kısaca
- Alta ne istediğinizi yazın, **Üret**. Ctrl/⌘+Enter da üretir. Düğmedeki fiyat tahminidir.
- Model düğmesi (ör. "fal.ai · Nano Banana 2.1"): önce sağlayıcıyı, sonra modeli seçin. En-boy, çözünürlük, kalite ve adet + satırının sağında.
- Referans: görseli kutuya sürükleyin, yapıştırın ya da + ile yükleyin. Referansın altındaki etikete basıp rol seçin (stil, ürün, arka plan…).
- Kişi: sol menü › Kişiler › kişi ekle, 1-4 net yüz fotoğrafı yükleyin. İstemde `@Ahmet` yazınca fotoğraflar kimlik referansı olur.
- **Geliştir** taslağı ayrıntılı isteme çevirir; "Taslağa dön" ile geri alınır. **Beyin fırtınası** bir fikirden birkaç farklı istem çıkarır, seçtikleriniz tek seferde üretilir.
- Görsele tıklayın: indir, ayarları tekrar kullan, **Alanı düzenle** (fırçayla boyanan yer değişir), önceki ile karşılaştır, Medya kitaplığına ekle.
- **Pano** (yalnız `flxst_manage`): sol menüde; üretim, harcama, model/sağlayıcı/kişi dağılımı ve son hatalar. Harcama Flux AI kayıtlarından gelir.
- Klasörü sağdaki ⋯ ile ekiple paylaşabilirsiniz; görselleri klasörün üstüne sürükleyerek taşırsınız.

Notlar
- Video üretimi dakikalar sürebilir. Sayfa kapansa da iş sürer; geri dönünce galeride tamamlanır.
- "İstek sağlayıcıya ulaşmış olabilir" hatasında aynı istemi hemen tekrar göndermeyin, önce sağlayıcı panelinde ücret alınıp alınmadığına bakın.
- Harcama Flux AI'nin harcama sınırına dahildir (sol altta günlük çubuk).
- Dosyalar `wp-content/uploads/flx-studio` ve Flux AI'nin medya klasöründe. Eklenti silinince tablolar ve bu klasör silinir; Medya kitaplığına eklenenler kalır.
- Masaüstü/mobil uygulama Stüdyo'yu Flux App girişiyle aynı API'den kullanır (API.md).
