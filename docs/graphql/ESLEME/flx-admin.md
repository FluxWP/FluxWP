# Flux Admin (flx-admin): REST → GraphQL eşlemesi

Paket sürümü: 1.8.0 (GraphQL) — önceki: 1.7.27 · Önek: `flxa_` · Cap: `manage_options` (tema ayarı), kullanıcı bazlı menü/widget · Sahip thread: envanter (Faz 1) · Durum: uygulandı (PR `gql/flx-admin-1`)

Not: Bu dosya yalnız envanterdir. "belirsiz" işaretli satırlar uygulayıcıdan önce karar ister.

## Özet

- REST: 0 · AJAX: 10 fonksiyon (15 `add_action` satırı, nopriv çiftleri dahil) · Admin menü kaydı: 0 (kabuk paketi) · Ayarlar sayfası: 1 (`add_options_page`, `flx-admin`, "Admin theme")
- MCP/AI aracı: yok.
- Ayar şeması: yok.
- İstisna önerisi: PWA protokol uçları (`flxa_manifest`, `flxa_sw`, `flxa_offline`, `flxa_splash`, `flxa_auth`) — tarayıcı ve service worker'ın beklediği dosya/yanıt formatı. ISTISNALAR.md'ye satır gerekli.
- Plan'daki "menü: 0" doğru ama ayarlar sayfası 1 kayıt; düzeltilmeli.
- Servis (önerilen): `includes/services/class-flxa-menu-service.php`, `class-flxa-mode-service.php`, `class-flxa-widget-service.php`. Mantık `chrome.php`, `menu.php`, `widgets.php` içinde.

## REST uçları

Yok.

## AJAX

| Action | cap / nonce | Önerilen GraphQL | Not |
|---|---|---|---|
| `flxa_mode` | kullanıcı (oturumlu) | `flxAdminModeSet(input: { mode: ENUM })` (mutation) | Koyu/açık admin modu; kullanıcı tercihi |
| `flxa_menu_user` | kullanıcı | `flxAdminMenuUserSet(input: ...)` (mutation) | Kullanıcıya özel menü düzeni (belirsiz: alan ayrıntısı kod okunmadan netleşmedi) |
| `flxa_widget` | kullanıcı | `flxAdminWidget(id:)` (query) | Pano widget'ı verisi (belirsiz: hangi widget'lar; veri kaynağı flx-tasks mı) |
| `flxa_task_form` | kullanıcı | belirsiz; önerilen: yönlendirme → `flx-tasks` içindeki `flxTaskCreate` formu, yeni alan açılmasın | Görev formu ayrı kopyalanmasın, flx-tasks'ı kullan |
| `flxa_task_create` | kullanıcı | `flxTaskCreate` (zaten flx-tasks'ta var) | Tekrar eden mutation; yeni kayıt açma, mevcut kullanılsın (belirsiz: flx-admin kendi görevi mi yazıyor) |
| `flxa_manifest` (priv + nopriv) | yok | **İSTİSNA**: PWA manifest | Tarayıcı dosyası; GraphQL'e taşınmaz |
| `flxa_sw` (priv + nopriv) | yok | **İSTİSNA**: service worker | Tarayıcı dosyası |
| `flxa_offline` (priv + nopriv) | yok | **İSTİSNA**: çevrimdışı sayfa | HTML yanıt |
| `flxa_splash` (priv + nopriv) | yok | **İSTİSNA**: açılış ekranı görseli | Görsel yanıt |
| `flxa_auth` (priv + nopriv) | belirsiz | **İSTİSNA** önerilir: PWA oturum köprüsü | Oturum/çerez akışı; OAuth benzeri, ISTISNALAR "OAuth" satırına bağlanabilir (belirsiz) |

Not: `flxa_pwa_login_watch` ve `flxa_pwa_keep_login` ayrı action değil, filtre/kanca; istemci tarafı yok.

## Admin sayfaları

Menü kaydı yok. Ayar sayfası:

| Sayfa (`page=`) | Ne gösterir | Kullandığı sorgular | İstemci ekranı |
|---|---|---|---|
| `flx-admin` (Ayarlar > Admin theme, `settings-page.php:23`) | Admin teması, logo, renk, koyu mod | `flxAdminSettings`, `flxAdminSettingsUpdate` (önerilen, elle tanımlanır) | `admin/settings` (belirsiz: kapsam) |

## Ayarlar

- `flx_settings_schema` yok. `flxAdminSettings` ve `flxAdminSettingsUpdate` elle tanımlanır, cap `manage_options`. Renk/koyu mod tokenları tema ile ortak (bkz. tema envanteri).

## Tipler

| Tip | Alanlar | Nullable alanlar ve nedeni | Loader |
|---|---|---|---|
| `FlxAdminSettings` | logo (`FlxFile`), accent renk, darkMode | `logo` nullable (logo yoksa) | — |
| `FlxAdminWidget` | id, title, kind (enum), data (belirsiz) | `data` belirsiz | — |
| `FlxAdminMenuLayout` (belirsiz) | items (kullanıcıya özel sıra) | yok | — |

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-admin:settings` | `flxAdminSettings` | `flxAdminSettingsUpdate` |
| `flx-admin:user:{id}` | `flxAdminMenuLayout`, `flxAdminWidget` | `flxAdminMenuUserSet`, `flxAdminModeSet` |

Not: kullanıcıya özel veri önbelleğe alınmaz (girişli istek, kural gereği).

## Açık sorular

- PWA uçlarının (`manifest`, `sw`, `offline`, `splash`, `auth`) ISTISNALAR.md'de ayrı satır mı, yoksa tek "PWA protokol" satırı mı olsun?
- `flxa_task_create`: flx-admin görev oluşturmayı kendisi mi yapıyor, yoksa flx-tasks'a yönlendiriyor mu? Çift kayıt yolu olmamalı.
- Admin widget veri kaynağı hangi paketler? Her biri kendi GraphQL tipine bağlanmalı.
- `flxa_menu_user` alan ayrıntısı (menü sırası, gizleme) kod okunarak netleşmeli.

## Uygulama sonucu (1.8.0)

- Ayarlar (`flxAdminSettings`, `flxAdminSettingsConfig`, `flxAdminSettingsUpdate`), menü düzenleyici (`flxAdminMenuEditor`, `flxAdminMenuLayoutsSave`), yetki tablosu (`flxAdminCaps`, `flxAdminCapsSave`, `flxAdminCapsRestore`), kullanıcı tarafı (`flxAdminMode`/`flxAdminModeSet`, `flxAdminUserMenu`/`flxAdminUserMenuSet`, `flxAdminWidgetPrefs`/`flxAdminWidgetPrefsSet`) GraphQL'de. Ayrıntı: docs/flx-admin/GRAPHQL.md.
- AJAX eşlemesi: `flxa_mode` → `flxAdminModeSet`; `flxa_menu_user` → `flxAdminUserMenuSet`; `flxa_widget` → `flxAdminWidgetPrefsSet` (filtre + modül HTML'i). Üçü de eski AJAX ile aynı servisi (`FLXA_*_Service`) kullanır; AJAX davranışı değişmedi.
- Kalan AJAX: 5 PWA protokol ucu (kalıcı istisna) ve `flxa_task_form` / `flxa_task_create` (geçici istisna; görev oluşturma `flxTaskCreate` ile zaten GraphQL'de, pano JS'i taşınınca kalkar). Görev formu için ayrı bir flx-admin mutation'ı açılmadı.
- Widget kararı: pano modülleri HTML olarak sunucuda üretilir; GraphQL yalnız kişi filtresini kaydeder ve modül gövdesini (`html`) döndürür. Veri kaynakları flx-tasks / flx-donate'in kendi servisleridir.
- Ekran: `flxa-admin` modülü (`packages/modules/src/admin`), Ayarlar › Yönetim teması'nın tüm sekmelerini kapsar; wp-admin'de varsayılan, klasik görünüm sayfadaki bağlantıyla.
