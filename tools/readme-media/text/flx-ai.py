SLUG='flx-ai'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-ai'; WP='6.5'; PHP='7.4'; DOCS='flx-ai'; API=True
C={
'en':dict(name='Flux AI',
 tagline='One AI hub for every FluxWP plugin: keys, models, costs, limits and a shared chat.',
 desc='Flux AI is the shared AI layer of the FluxWP packages. OpenRouter and Anthropic keys, the model catalogue and prices, prompt caching, a tool registry, usage logs, billing and spending limits live here once; Flux Call Center, Flux Contact, Flux Social, Flux Language, Flux Studio and the rest call it instead of talking to AI providers themselves. Authorised users also get a streaming AI chat in the admin that can use those tools.',
 badges=['![Providers](https://img.shields.io/badge/providers-OpenRouter%20%C2%B7%20Anthropic-7d64ff)','![Media](https://img.shields.io/badge/media-fal.ai%20%C2%B7%20OpenAI%20%C2%B7%20Gemini-2fbf8f)'],
 gifalt='Flux AI tour: the overview with daily cost and time charts, connected systems, the request log, monthly billing and the tool registry',
 why='''When every plugin brings its own AI connection, you end up with keys in five settings pages, no idea what the month costs, and no way to stop a runaway loop at 3 a.m. Flux AI puts one layer in between:

- 🔑 **Keys in one place.** OpenRouter and Anthropic keys are entered once, stored encrypted and never sent to the browser.
- 💸 **Costs you can see and cap.** Daily, weekly and monthly limits with a warning threshold; every request logged with tokens, cache, time and cost.
- 🧩 **One switch per system.** See which plugin uses which model, how much it spent and how often it failed, and turn it off without touching its settings.
- 💬 **A chat that can act.** The shared chat sees the tools other plugins register, asks for approval before changing anything and only shows tools the user is allowed to use.''',
 features='''### Hub for other plugins
- `flxai_chat()` / `flxai_complete()` with tools, streaming, prompt caching (Claude) and model fallback; `flxai_ready()`, `flxai_model_for()`, `flxai_budget_status()`.
- **Connected systems:** each plugin with status, model, 30-day requests, tokens, cost, errors and last use; on/off per system; per-plugin model in Settings.
- **Tools:** every tool registered through `flxai_tools` or Flux SEO, with source, reads/writes, approval flag, capability, 30-day calls and average time; on/off per tool.
- Images in answers: ```widget / ```mermaid blocks rendered to PNG through Gotenberg or Imagick.
- Image and video generation for Flux Studio through fal.ai, EvoLink, OpenAI, Gemini and OpenRouter (`flxai_media_submit`).

### Costs and limits
- **Overview:** daily, weekly and monthly spend against limits, cost, requests, tokens (input, output, from cache), total time split into model vs tools, errors; daily charts for 7 to 180 days, per plugin.
- **Billing:** month by month with cache savings, per provider, per plugin and per model, CSV download.
- **Logs:** every model request and tool call (where, who, model or tool, tokens, cache, duration, cost); content stored masked (e-mail, phone, IBAN, keys) and kept 30 days by default.
- Spending limit action: block or only warn, with a warning percentage.

### Shared chat
- The AI button in the admin opens a docked or floating chat with chat history, search, model picker, suggestions for the current page and streaming answers.
- Changes need the user's approval in the chat; stop at any time; edit a message or go back to an earlier point.
- The chat keeps running across page loads.''',
 shots=[('overview','Overview: spend, limits and time'),('chat','Shared AI chat'),('systems','Connected systems'),('tools','Tool registry'),('log','Request log'),('billing','Monthly billing'),('settings','Keys and models'),('dark','Dark mode with Flux Admin'),('mobile','Mobile overview',True)],
 req='An OpenRouter or Anthropic API key. Optional: Gotenberg or Imagick for images in answers, fal.ai / EvoLink / OpenAI / Gemini keys for Flux Studio.',
 first='''1. Activate Flux AI and open **Flux AI & MCP › Settings**.
2. Enter an OpenRouter key (or an Anthropic key), choose the default model and press **Test**.
3. Set daily, weekly and monthly limits.
4. Open **Connected systems** to see which plugins use Flux AI and pick a model per plugin if you like.''',
 note='> ⚠️ Flux AI is required by every FluxWP package that uses AI; none of them keeps its own AI connection. Older keys saved in Flux Call Center are moved here automatically.',
 dev='''- `flxai_chat( $messages, [ 'source' => 'my-plugin', 'tools' => …, 'stream' => true ] )`, `flxai_complete( $prompt )`.
- Register a system with `flxai_systems` and chat tools with `flxai_tools`; log your own calls with `flxai_log()`.
- Media: `flxai_media_submit()`, `flxai_media_status()`, `flxai_media_cancel()`; REST under `/wp-json/flx-ai/v1`.''',
 roadmap=[(1,'One key store, model catalogue and prices'),(1,'Usage logs, billing and spending limits'),(1,'Connected systems and tool registry'),(1,'Streaming shared chat with approvals'),(1,'Image and video generation for Flux Studio'),(0,'Per-user and per-role budgets'),(0,'Local model support (Ollama)')]),
'tr':dict(name='Flux AI',
 tagline='Tüm FluxWP eklentileri için tek yapay zekâ merkezi: anahtarlar, modeller, maliyet, sınır ve ortak sohbet.',
 desc="Flux AI, FluxWP paketlerinin ortak yapay zekâ katmanıdır. OpenRouter ve Anthropic anahtarları, model kataloğu ve fiyatlar, prompt önbelleği, araç kaydı, kullanım kayıtları, faturalandırma ve harcama sınırları tek yerde durur; Flux Chat, Flux Mail, Flux Social, Flux Lang, Flux Studio ve diğerleri AI sağlayıcılarıyla kendisi konuşmak yerine bunu çağırır. Yetkili kullanıcılar panelde bu araçları kullanabilen akışlı bir AI sohbeti de alır.",
 badges=['![Sağlayıcılar](https://img.shields.io/badge/sağlayıcı-OpenRouter%20%C2%B7%20Anthropic-7d64ff)','![Medya](https://img.shields.io/badge/medya-fal.ai%20%C2%B7%20OpenAI%20%C2%B7%20Gemini-2fbf8f)'],
 gifalt='Flux AI turu: günlük maliyet ve süre grafikli genel bakış, bağlı sistemler, istek kayıtları, aylık faturalandırma ve araç kaydı',
 why='''Her eklenti kendi AI bağlantısını getirince anahtarlar beş ayar sayfasına dağılır, ayın neye mal olduğu bilinmez, gece 3'te dönen bir döngüyü durduracak bir şey olmaz. Flux AI araya tek bir katman koyar:

- 🔑 **Anahtarlar tek yerde.** OpenRouter ve Anthropic anahtarı bir kez girilir, şifreli saklanır, tarayıcıya hiç gitmez.
- 💸 **Görünen ve sınırlanan maliyet.** Uyarı eşikli günlük, haftalık ve aylık sınır; her istek token, önbellek, süre ve maliyetiyle kayıtlı.
- 🧩 **Sistem başına tek anahtar.** Hangi eklentinin hangi modeli kullandığını, ne harcadığını, ne sıklıkla hata aldığını görün; ayarlarına dokunmadan kapatın.
- 💬 **İş yapabilen sohbet.** Ortak sohbet diğer eklentilerin kaydettiği araçları görür, bir şeyi değiştirmeden önce onay ister, kullanıcıya yalnız yetkisi olan araçları gösterir.''',
 features='''### Diğer eklentiler için merkez
- Araçlı, akışlı, prompt önbellekli (Claude) ve yedek modelli `flxai_chat()` / `flxai_complete()`; `flxai_ready()`, `flxai_model_for()`, `flxai_budget_status()`.
- **Bağlı sistemler:** her eklentinin durumu, modeli, 30 günlük isteği, token'ı, maliyeti, hatası ve son kullanımı; sistem başına aç/kapa; Ayarlar'da eklenti başına model.
- **Araçlar:** `flxai_tools` ya da Flux SEO ile kaydedilen her araç; kaynak, okur/yazar, onay, yetki, 30 günlük çağrı ve ortalama süre; araç başına aç/kapa.
- Cevapta görsel: ```widget / ```mermaid blokları Gotenberg ya da Imagick ile PNG'ye çevrilir.
- Flux Studio için fal.ai, EvoLink, OpenAI, Gemini ve OpenRouter ile görsel ve video üretimi (`flxai_media_submit`).

### Maliyet ve sınırlar
- **Genel bakış:** sınırlara karşı günlük, haftalık ve aylık harcama; maliyet, istek, token (giriş, çıkış, önbellekten), model ve araç diye bölünmüş toplam süre, hatalar; 7 ile 180 gün arası günlük grafikler, eklenti süzgeci.
- **Faturalandırma:** önbellek tasarruflu aylık döküm; sağlayıcıya, eklentiye ve modele göre; CSV indirme.
- **Kayıtlar:** her model isteği ve araç çağrısı (nerede, kim, model ya da araç, token, önbellek, süre, maliyet); içerik maskeli saklanır (e-posta, telefon, IBAN, anahtar), varsayılan 30 gün.
- Harcama sınırında engelle ya da yalnız uyar; uyarı yüzdesi.

### Ortak sohbet
- Paneldeki AI düğmesi yanaşık ya da yüzen bir sohbet açar: sohbet geçmişi, arama, model seçici, sayfaya göre öneriler, akışlı cevaplar.
- Değişiklikler sohbette kullanıcı onayı ister; her an durdurulur; mesaj düzenlenir ya da önceki bir noktaya dönülür.
- Sohbet sayfa geçişlerinde kaldığı yerden sürer.''',
 shots=[('overview','Genel bakış: harcama, sınırlar ve süre'),('chat','Ortak AI sohbeti'),('systems','Bağlı sistemler'),('tools','Araç kaydı'),('log','İstek kayıtları'),('billing','Aylık faturalandırma'),('settings','Anahtarlar ve modeller'),('dark','Flux Admin ile koyu tema'),('mobile','Mobil genel bakış',True)],
 req="OpenRouter ya da Anthropic API anahtarı. İsteğe bağlı: cevapta görsel için Gotenberg ya da Imagick, Flux Studio için fal.ai / EvoLink / OpenAI / Gemini anahtarları.",
 first='''1. Flux AI'yi etkinleştirip **Flux AI & MCP › Ayarlar**'ı açın.
2. OpenRouter anahtarını (ya da Anthropic anahtarını) girin, varsayılan modeli seçip **Test**'e basın.
3. Günlük, haftalık ve aylık sınırları girin.
4. **Bağlı sistemler**'de hangi eklentilerin Flux AI'yi kullandığını görün; isterseniz eklenti başına model seçin.''',
 note="> ⚠️ AI kullanan her FluxWP paketi için Flux AI zorunludur; hiçbiri kendi AI bağlantısını tutmaz. Flux Chat'nde kayıtlı eski anahtarlar buraya kendiliğinden taşınır.",
 dev='''- `flxai_chat( $messages, [ 'source' => 'eklentim', 'tools' => …, 'stream' => true ] )`, `flxai_complete( $prompt )`.
- Sistemi `flxai_systems` ile, sohbet araçlarını `flxai_tools` ile kaydedin; kendi çağrılarınızı `flxai_log()` ile yazın.
- Medya: `flxai_media_submit()`, `flxai_media_status()`, `flxai_media_cancel()`; REST `/wp-json/flx-ai/v1` altında.''',
 roadmap=[(1,'Tek anahtar deposu, model kataloğu ve fiyatlar'),(1,'Kullanım kayıtları, faturalandırma ve harcama sınırları'),(1,'Bağlı sistemler ve araç kaydı'),(1,'Onaylı, akışlı ortak sohbet'),(1,'Flux Studio için görsel ve video üretimi'),(0,'Kullanıcı ve rol başına bütçe'),(0,'Yerel model desteği (Ollama)')]),
}
