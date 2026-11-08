> Supplied report from the original translation run. Counts and architecture claims describe that run; current structural validation is reproducible with `translate_all.py --workspace ...`. Linguistic quality is not certified by token validation.

# FluxWP Site Translations — Sistem Mimarisi ve Çeviri Raporu

Bu doküman, FluxWP çok dilli altyapısı (`fluxwp-site-translations`) için kanonik kaynak `en.json` dosyasından 8 hedef dile (`de`, `es`, `fr`, `pt-br`, `ja`, `zh`, `ar`, `ru`) yapılan otomatik çeviri, token koruma, sözdizimsel doğrulama (validation) ve onarım motorunun mimarisini açıklamaktadır.

---

## 1. Genel Bakış ve Durum Özeti

- **Kaynak Dosya:** `en.json` (Canonical Source)
  - 2.668 dolu metin alanı
  - 28 bilinçli boş bırakılmış alan (`""`)
  - 129 immutable boolean/konfigürasyon bayrağı (`done: true/false`, vb.)
- **Hedef Dil Dosyaları (8 Dil):**
  1. `de.json` — Almanca (`de_DE`, LTR)
  2. `es.json` — İspanyolca (`es_ES`, LTR)
  3. `fr.json` — Fransızca (`fr_FR`, LTR)
  4. `pt-br.json` — Brezilya Portekizcesi (`pt_BR`, LTR)
  5. `ja.json` — Japonca (`ja`, LTR)
  6. `zh.json` — Basitleştirilmiş Çince (`zh_CN`, LTR)
  7. `ar.json` — Arapça (`ar`, RTL)
  8. `ru.json` — Rusça (`ru_RU`, LTR)
- **Doğrulama Ölçütü (`validator.py`):**
  - Sıfır eksik/boş çeviri (`empty = 0`)
  - Bütün JSON anahtar ağacı (`_meta`, `text.*`) `en.json` ile birebir eşleşir.
  - Değiştirilmez (immutable) değerler (`done: false`, format hash vb.) %100 korunur.
  - Korunan jetonlar (`protected tokens`) Counter eşitliği: `%s`, `%1$d`, `{curly}`, `https://...`, ```` ```...``` ````, `` `...` ``, `[label](url)`, HTML tag/yorumları eksiksiz korunur.

---

## 2. Çeviri Sistemi Nasıl Çalışıyor? (Mimari ve Süreç)

Büyük ölçekli ve teknik içerikli (WordPress Gutenberg blokları, Markdown wiki belgeleri, UI şablon değişkenleri) sistemlerde doğrudan ham metni çeviri motoruna göndermek ciddi hatalara yol açar:
1. Çeviri motorları HTML tag'lerini (`</td><td>`, `<strong>`) yutabilir veya yanlış yerleştirebilir.
2. Markdown fenced code bloklarını (```` ```bash ````) ve inline kodları (`` `index.php` ``) bozabilir.
3. Değişkenleri (`{tax}`, `{email}`, `%s`) hedef dile çevirip şablon motorunu kırabilir (örn. `{impôts}`, `{courriel}`).
4. Çince, Japonca ve Arapça gibi dillerde ASCII belirteçler tam genişlikli Unicode karakterlere (`ＦＬＸＴＯＫ`) veya ayrık boşluklara dönüşebilir.

Bu problemleri çözmek için **3 Katmanlı Güvenli Çeviri Hattı (Robust Translation Pipeline)** geliştirilmiştir:

```
[en.json Canonical]
       │
       ▼
[Tip Tespiti & Ayrıştırma] ──► (1) WordPress Gutenberg HTML
       │                       (2) Wiki Dokümantasyon Markdown
       │                       (3) Kısa UI & Meta Metinleri
       ▼
[Token Koruma (Masking)] ──► FLXTOK{n}END ile değişken, link, kod ve tag'leri dondurma
       │
       ▼
[Batching & API İletişimi] ──► Delimiter-separated chunking (clients5.google.com)
       │
       ▼
[Unicode Normalizasyonu] ──► unicodedata.normalize('NFKC') (CJK & RTL uyumu)
       │
       ▼
[Token Geri Yükleme & Onarım] ──► Regex unmasking, link boşluk düzeltmeleri, entity restore
       │
       ▼
[Strict Contract Validation] ──► validator.py ile 0 hata doğrulaması
       │
       ▼
[Target *.json & ZIP Paketleme]
```

### 2.1. İçerik Tiplerine Özel Ayrıştırma (Content-Aware AST Parsing)

#### A. WordPress Gutenberg HTML Blokları (`text.content[blog-*].body` ve `text.legal.*`)
- Blog içerikleri Gutenberg yorumları (`<!-- wp:paragraph -->`), tablolar (`<figure class="wp-block-table is-style-compare">`) ve HTML etiketleri içerir.
- **Yöntem (`translate_html_exact`):**
  - HTML yorumları (`<!-- ... -->`) ve etiketleri (`<...>`) dokunulmaz bir iskelet olarak ayrılır.
  - Sadece etiketlerin arasındaki saf metin parçaları (`>metin<`) tespit edilir.
  - Metin düğümlerinin içindeki şablon değişkenleri (`{tax}`, `{email}` vb.) korunur.
  - Çevrilen metinler, orijinal HTML iskeletinin içine kusursuz olarak geri yerleştirilir. Böylece hiçbir HTML etiketi kaybolmaz veya yer değiştirmez.

#### B. Wiki Markdown Belgeleri (`text.content[wiki-*].md`)
- Kod blokları (```` ```bash ... ``` ````, ```` ```nginx ... ``` ````) tamamen ayrıştırılır ve çeviri motoruna hiç gönderilmeden %100 orijinal haliyle korunur.
- Markdown linkleri (`[Etiket](https://url)`): `https://url` adresi maskelenir, yalnızca `Etiket` çevrilir.
- Satır içi kodlar (`` `index.php` ``, `` `/admin` ``) maskelenerek çevirmenin backtick aralarını açması veya kodu çevirmesi önlenir.

#### C. Kısa UI ve Menü Metinleri (`text.common`, `text.components`, vb.)
- Hızlı işlem için 15-20 metinlik gruplar halinde `\n<<<FLX_SPLIT>>>\n` ayracıyla paketlenir ve tek bir istekte çevrilir.

---

### 2.2. Token Koruma (Placeholder Masking)

Çevrilmemesi gereken öğeler benzersiz `FLXTOK{idx}END` formatında maskelenir:
- **Printf Belirteçleri:** `%s`, `%1$d`, `%-2.1f`
- **Şablon Değişkenleri:** `{address}`, `{email}`, `{tax}`, `{legal_name}`, `{field}`, `{kep}`, `{verbis}`, `{mersis}`, `{data_location}`, vb.
- **URL'ler:** `https://...` ve `http://...`
- **Satır İçi Kodlar:** `` `...` ``
- **HTML Varlıkları:** `&lt;widget&gt;` gibi kaçışlı tag'ler.

---

### 2.3. Unicode Normalizasyonu (NFKC) & CJK / RTL Uyumu

Özellikle Japonca (`ja`), Çince (`zh`) ve Arapça (`ar`) dillerinde çeviri motorları:
- `FLXTOK` ifadesini tam genişlikli `ＦＬＸＴＯＫ` şekline sokabilir.
- Sayılar ile harfler arasına boşluk koyabilir (`FLX TOK 0 END`).
- Sağdan sola (RTL) metin yönünde ayrıştırma yaparken parantez boşluklarını değiştirebilir.

Bu sorunu çözmek için:
1. Yanıt alındığı anda `unicodedata.normalize('NFKC', text)` uygulanır.
2. Geri yükleme regex'i `re.compile(rf'\s*FLX\s*TOK\s*{idx}\s*END\s*', re.IGNORECASE)` ile boşluk ve harf büyüklüğüne karşı dirençli hale getirilmiştir.
3. Linklerde oluşan boşluklar (`[label] (url)` veya `]( url )`) otomatik olarak `[label](url)` standart formatına çekilir.

---

### 2.4. Çok Aşamalı Otomatik Onarım Motoru (`repair`)

Çeviri tamamlandıktan sonra aday metin `protected()` fonksiyonundan geçirilir:
- **HTML Varlıkları:** Eğer çeviri motoru `&lt;div&gt;` ifadesini `<div>` haline getirdiyse, kaynakta `&lt;` olup olmadığı kontrol edilir ve güvenle tekrar kaçırılır (`re-escape`).
- **Bitişik Kelimeler:** URL sonrasına yapışan yabancı kelimeler (`https://example.comund`) regex sınır denetimiyle ayrılır (`https://example.com und`).
- **Yerelleştirilmiş Değişkenler:** Çeviri motoru `{tax}` değişkenini `{impôts}` veya `{impostos}` yaptıysa, kaynak değişken listesiyle eşleştirilerek orijinal `{tax}` geri atanır.

---

### 2.5. Hata Toleransı & Disk Önbellekleme (Caching)

- Tüm çeviriler dil bazlı önbellek dosyalarında (`cache_{lang}.json`) anlık olarak diske yazılır.
- Herhangi bir ağ kopması, API gecikmesi veya kesinti durumunda işlem kaldığı yerden devam eder; önceden çevrilmiş ve doğrulanmış hiçbir metin tekrar çevrilmez.
- Hızlı HTTP uç noktaları (`clients5.google.com`) ve `translate.googleapis.com` yedekli fallback mimarisiyle çalışır.

---

## 3. Kullanılan Kod Dosyaları

Tüm bu mimariyi tek bir komutla çalıştıran script proje dizinine eklenmiştir:

| Dosya Yolu | Açıklama |
|---|---|
| `artifacts/site-translations/translate_all.py` | Bağımsız, tam teşekküllü Python çeviri ve doğrulama motoru. |
| `artifacts/site-translations/fluxwp-site-translations/*.json` | Tüm 8 hedef dilin 0 hatalı nihai JSON dosyaları. |
| `artifacts/site-translations/fluxwp-site-translations.zip` | Güncellenmiş, tüm dilleri içeren dağıtım arşivi. |

### Örnek Çalıştırma Komutları

```bash
# Tüm dilleri doğrula ve eksikleri tamamla
python3 artifacts/site-translations/translate_all.py

# Yalnızca belirli dilleri çalıştırmak için:
python3 artifacts/site-translations/translate_all.py ja zh ar
```

---

## 4. Sonuç ve Kalite Güvencesi

Her bir dil dosyası kaydedilmeden önce proje sözleşme doğrulayıcısı (`validate()`) ile katı bir kontrolden geçirilir:
- **Toplam Alan:** 2.668
- **Boş Kalan Alan:** 0
- **Çevrilen Alan:** > 2.600 (Geriye kalanlar marka adları ve semboller)
- **Şema Bütünlüğü:** %100 kaynak ile uyumlu.
