# Flux Roles — API ve paketler arası sözleşme

Flux Roles iki rol ekler ve bu rollerin erişimini tek sayfadan yönetir (Kullanıcılar › Roller ve erişim).
**Yetkiyi uygulayan, ilgili paketin kendisidir.** Flux Roles yalnız rolü, yetki adını ve referans bilgisini verir.

## Roller

| Rol anahtarı | Ad | Varsayılan yetkiler |
|---|---|---|
| `flx_personel` | Personel | `read`, `flx_referans`, `flxg_use`, `flxs_use`, `flxs_own_ref`, `flxb_view_own`, `flxb_donors_own`, `flxb_create_ref`, `flxb_ref_notify`, `flxc_staff_tools`, `flx_reports_receive` |
| `flx_idareci` | İdareci | Personel + `flxs_dashboard`, `flxb_dashboard`, `flx_reports_view`, `flx_reports_manage` |
| `flx_yonetici` | Yönetici (1.1.0) | Katalogdaki **her** yetki (`*_manage` dahil); WordPress `administrator` değildir (eklenti/site ayarı yok) |
| `flx_<ad>` | Panelden eklenen roller (1.1.0) | Yalnız `read`; yetkiler panelden açılır |

Roller kurulumda bir kez eklenir; sonra yetkiler panelden değişir (gerçek `add_cap`/`remove_cap`).
WordPress `administrator` her şeyi görür, ayrıca `flx_referans` alır.

**Kural: paketler rol adına bakmaz, yalnız yetkiye (`user_can( $u, 'cap' )`) bakar.** Yeni roller panelden eklenecek;
rol adı kontrol eden kod onları görmez. Rol listesi: `flxr_roles()` (filtre `flxr_roles`).

## Yetki adları

| Yetki | Anlamı | Uygulayan | Durum |
|---|---|---|---|
| `read` | Panele girer | WordPress | var |
| `flx_referans` | Referans olabilir: kişisel referans linki, sertifika/bağışta referans seçilebilir | Flux Roles + Sertifika + Bağış | **yeni** |
| `flxg_use` / `flxg_create` / `flxg_ai` | Görevleri kullanır / proje-liste oluşturur / AI | Flux Tasks | var |
| `flxs_use` | Sertifika oluşturur | Flux Certificates | var |
| `flxs_bulk` | Toplu gönderim | Flux Certificates | var |
| `flxs_own_ref` | Yalnız kendi oluşturduğu (`created_by`) **veya** referansı olduğu (`ref_user_id`) sertifikaları görür | Flux Certificates | **yeni** |
| `flxs_dashboard` | Sertifika Dashboard'u (genel istatistik) | Flux Certificates | **yeni** |
| `flxb_view_own` | Bağışlar listesinde yalnız referansı kendisi olan bağışlar | Flux Donate | **yeni** |
| `flxb_dashboard` | Bağış Dashboard'u (genel toplamlar) | Flux Donate | **yeni** |
| `flx_bagis_view` | Tüm bağışlar ve bağışçılar | Flux Donate | var |
| `flxc_use` | Çağrı Merkezi sohbetleri | Flux Chat | var |
| `flxg_manage` / `flxs_manage` / `flxc_manage` | İlgili paketi yönetir | Görev / Sertifika / Çağrı | var |
| `flxst_use` / `flxst_manage` | Stüdyo'yu kullanır / yönetir (herkesin galerisi, ayarlar) | Flux Studio | 1.2.0 (Stüdyo `flx_role_caps_supported` ile bildirir) |
| `flxb_donors_own` | Kendi bağışçılarını listeler (referansı olduğu bağışların bağışçıları) | Flux Donate (+ asistan aracı) | **yeni 1.1.0** |
| `flxb_create_ref` | Başkası adına bağış oluşturur, `ref_user_id` = kendisi (panel ya da WhatsApp asistanı) | Flux Donate + Flux Chat | **yeni 1.1.0** |
| `flxb_ref_notify` | Referansı olduğu bağış ödenince/iptal olunca haber alır (WhatsApp/e-posta) | Flux Donate veya Çağrı | **yeni 1.1.0** |
| `flxc_staff_tools` | WhatsApp asistanında personel araçları (numarası kayıtlıysa); hangi araçlar = kullanıcının diğer yetkileri | Flux Chat | **yeni 1.1.0** |
| `flx_reports_receive` | Kendisine günlük/dönemsel rapor gönderilebilir | Flux Chat (rapor) | **yeni 1.1.0** |
| `flx_reports_view` | Genel raporları görür (tüm personel, toplamlar) | Flux Chat (rapor) | **yeni 1.1.0** |
| `flx_reports_manage` | Personel/idareci rapor zamanlamalarını ve motivasyon metinlerini ayarlar | Flux Chat (rapor) | **yeni 1.1.0** |

### Uygulandığını bildirme: `flx_role_caps_supported`

Yeni yetkiyi uygulayan paket adını bildirir; bildirilene kadar Roller sayfasında "… güncellemesi bekleniyor" rozeti ve
sistem kontrolünde bilgi satırı görünür.

```php
add_filter( 'flx_role_caps_supported', function ( $caps ) {
	return array_merge( (array) $caps, array( 'flxb_view_own', 'flxb_dashboard' ) );
} );
```

### Flux Tasks eşitlemesi

Flux Tasks yetkiyi rol listesinden de verir (`flxg_settings` › `roles_use`, `roles_create`, `ai_roles`) ve atanabilir kişileri
`roles_use`'tan bulur. Flux Roles bir rolde `flxg_use`/`flxg_create`/`flxg_ai` açılıp kapanınca rolü bu listelere ekler/çıkarır
(`flxg_update_settings`). Görev tarafında değişiklik gerekmez.

## Referans

### Kişisel referans linki
- `flx_referans` yetkisi olan her kullanıcının rastgele kodu var: user meta `flxr_ref_code`.
- Link: `https://site/?ref=<kod>` (her sayfaya eklenebilir). Gelen ziyaretçiye `flx_ref` çerezi (HttpOnly, varsayılan 30 gün, ayardan), son link geçerli.
- `?ref=` olan istek önbelleğe alınmaz (`litespeed_control_set_nocache`).
- WooCommerce siparişi oluşurken (klasik `woocommerce_checkout_create_order`, blok `woocommerce_store_api_checkout_update_order_meta`)
  referans kişi siparişe yazılır: order meta **`_flx_ref_user`** (kullanıcı ID). Önceden dolu meta ezilmez.

### Fonksiyonlar (Flux Roles yoksa `function_exists` ile kontrol edin)

| Fonksiyon | Dönüş |
|---|---|
| `flxr_current_ref_user()` | Bu ziyaretçinin referans kişisi (int, 0 = yok). Filtre: `flx_current_ref_user` |
| `flxr_order_ref_user( $order_or_id )` | Siparişteki `_flx_ref_user` (int) |
| `flxr_ref_users()` | Referans olabilen kullanıcılar (`ID`, `display_name`) |
| `flxr_ref_link( $user_id, $url = '' )` | Kişisel link (varsayılan ana sayfa) |
| `flxr_ref_code( $user_id )` / `flxr_user_by_ref_code( $code )` | Kod ↔ kullanıcı |
| `flxr_user_role( $user = null )` | `flx_personel` / `flx_idareci` / `''` |

Sabitler: `FLXR_ROLE_STAFF`, `FLXR_ROLE_MANAGER`, `FLXR_CAP_REF` (`flx_referans`), `FLXR_ORDER_META` (`_flx_ref_user`).

Flux Roles kurulu değilse referans yalnız `user_can( $id, 'flx_referans' )` ve sipariş meta'sı `_flx_ref_user` ile okunabilir.

## Telefon ve numaradan kimlik (1.1.0)

WhatsApp asistanı yazan numarayı site kullanıcısıyla eşleştirir, açacağı araçları kullanıcının **yetkilerine** göre seçer.

- User meta `flx_phone` ve `flx_phone_key` aynı değerdir: ülke kodlu tam rakam (E.164, ör. `905301234567`). Eşleşme bu tam değere göredir. Ülke kodu yazılmayan yerel numara varsayılan `90` ile tamamlanır (`05xx…` ya da `5` ile başlayan 10 hane). `+` ve `00` öneki ülke kodunu korur. İki farklı tam numara asla aynı sayılmaz. Bir numara tek kullanıcıda; aynı tam numarada birden fazla kayıt varsa eşleşme yok sayılır.
- Numarayı yalnız `promote_users` yetkili kişi yazar (Profil › WhatsApp numarası); kişi kendi numarasını değiştiremez.

| Fonksiyon | Dönüş |
|---|---|
| `flxr_normalize_phone( $raw )` | Ülke kodlu rakamlar ya da `''`. Yerel `05xx` / `5xx` → `90…`; ülke koduyla yazılmış numara olduğu gibi kalır |
| `flxr_user_by_phone( $raw )` | `WP_User` ya da `null`. Yalnız kayıtlı tam numara eşitse. Filtre `flx_user_by_phone` farklı numaralı kullanıcı döndürürse yok sayılır |
| `flxr_phone_identity( $raw )` | `null` ya da `array( user_id, name, role, role_label, caps[], is_admin )`; `caps` = katalogda açık olan yetkiler |
| `flxr_phone_can( $raw, $cap )` | bool |
| `flxr_user_phone( $user_id )` / `flxr_set_user_phone( $user_id, $raw )` | okuma / yazma (çakışmada `WP_Error`) |

Asistan için kurallar:
- Numara yalnız WhatsApp'ın gönderen kimliğinden (JID) alınır, mesaj metnindeki numaraya güvenilmez. Gizli numara (`@lid`) ise önce Çağrı Merkezi'nin kod doğrulaması.
- Araç açmadan önce her seferinde `flxr_phone_can()` ile yetki kontrolü (rol sonradan değişebilir); `is_admin` her şeye izinli.
- `flxb_create_ref` ile açılan bağışta `ref_user_id` = numaranın kullanıcısı; ödeme linki bağışçıya gider, ödeme/iptal haberi `flxb_ref_notify` olan referansa.

## Diğer filtreler

- `flxr_catalog` — Roller sayfasına grup/yetki eklemek: `array( 'grup' => array( 'label', 'plugin', 'active', 'caps' => array( 'cap' => array( 'label', 'new' => true ) ) ) )`.
- `flxa_cap_labels` — Flux Admin › Yetkiler tablosuna okunur adlar (Flux Roles doldurur).
- `flx_system_checks` — `flxr.roles`, `flxr.support` satırları.

## Paket sahiplerinden beklenen

1.0.0 işleri yapıldı (Flux Certificates 1.7.0, Flux Donate 2.2.0).

### 1.1.0: Flux Chat (asistan + raporlar)
1. Yazan numarayı `flxr_phone_identity()` ile tanı; `flxc_staff_tools` varsa rolüne göre personel araçlarını aç (yetki başına araç).
2. `flxb_create_ref`: "Ahmet Yılmaz adına 1 fidan, şu sertifikayla" → bağış/ödeme linki oluştur (`ref_user_id` = personel), linki bağışçıya gönder.
3. `flxb_donors_own`: kendi bağışçılarını listele; `flxb_view_own`: kendi bağışları.
4. `flxb_ref_notify`: referansın bağışı ödenince/iptal olunca referansa WhatsApp bildirimi (Flux Donate olayından).
5. Raporlar: `flx_reports_receive` olanlara zamanlanmış rapor, `flx_reports_manage` olanlar zamanlamayı ve motivasyon metnini ayarlar, `flx_reports_view` genel raporu görür.
6. `flx_role_caps_supported` → uyguladığı yetkiler.

### 1.1.0: Flux Donate
Panelde `flxb_create_ref` (Bağış ekle, referans = kendisi) ve `flxb_donors_own` (Bağışçılar yalnız kendi referanslarıyla); ödeme/iptal olayında `flxb_ref_notify` için kanca. `flx_role_caps_supported` → uyguladıkları.

### Önceki (1.0.0) sözleşme

### Flux Certificates
1. `flxs_own_ref`: `flxs_manage` yoksa ve kullanıcıda `flxs_own_ref` varsa sertifika listesi, sayımlar, dışa aktarma, kişi/kuyruk ekranları
   `created_by = ben OR ref_user_id = ben` ile sınırlanır (mevcut `staff_own_only` ayarından bağımsız). Düzenleme hakkı aynı kalır (`created_by`).
2. `flxs_dashboard`: Dashboard menüsü ve `/stats` REST ucu `flxs_manage || flxs_dashboard` ister. Mevcut davranış bozulmasın diye
   migration'da `flxs_personel` rolüne `flxs_dashboard` eklenir.
3. Referans seçilebilir kişiler: `flxs_users_with_cap()` / `flxs_valid_ref_user()` `flx_referans` yetkisini de kabul eder.
4. Bağıştan otomatik sertifika oluşurken `ref_user` bağışın referansıyla doldurulur (Flux Donate verir).
5. `flx_role_caps_supported` → `flxs_own_ref`, `flxs_dashboard`.

### Flux Donate
1. Bağış ve aylık bağışa `ref_user_id` (bigint, KEY). Kaynak sırası: siparişte `_flx_ref_user` (Flux Roles yazar) →
   Çağrı Merkezi ödeme linkini oluşturan temsilci (`flx_referans` yetkisi varsa) → yöneticinin "Bağış ekle" ekranında seçtiği referans.
   Aylık bağışın sonraki tahsilatları ilk bağışın referansını taşır.
2. Bağış detayında "Referans" gösterimi ve (yalnız yönetici) değiştirme; listede Referans sütunu ve filtresi.
3. `flxb_view_own`: Bağışlar menüsü görünür; liste/detay/dışa aktarma yalnız `ref_user_id = ben`. Bağışçılar, türler, ödemeler, ayarlar görünmez.
4. `flxb_dashboard`: Dashboard sayfası (genel toplamlar, tüm bağışlar) görünür; başka ekran açmaz.
5. Bağıştan sertifika oluşturulurken referans Flux Certificates'ya `ref_user` olarak geçer.
6. `flx_role_caps_supported` → `flxb_view_own`, `flxb_dashboard`.

### Flux Tasks, Flux Admin, Flux Chat
Değişiklik gerekmez. (Flux Admin › Yetkiler tablosu bu yetkileri `flxa_cap_labels` ile okunur adla gösterir.)
