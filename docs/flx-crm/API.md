# Flux CRM API

Önek `flxcrm_`, metin alanı `flx-crm`, REST `flx-crm/v1`. Yetkiler: `flxcrm_view` (görür), `flxcrm_manage` (düzenler). `manage_options` her zaman geçer.

## Veri modeli

| Tablo | Ne |
|---|---|
| `{prefix}flxcrm_contacts` | Kişi kartı: ad, e-posta, telefon (yalnız rakam, `90…`), `roles` (`,donor,volunteer,`), izinler (`consent_email/sms/whatsapp/ads`), toplamlar (`ltv`, `donations`, `orders`, `paid_days`, `first_paid_at`, `last_paid_at`, `avg_gap_days`), RFM (`r/f/m_score`, `segment`, `churn`) |
| `{prefix}flxcrm_links` | Kaynak kaydı → kişi (`donor:12`, `email:x@y`, `user:5`, `chat:9`); `(source, source_id)` tekil |
| `{prefix}flxcrm_events` | Zaman tüneli. `uniq` tekil anahtar (`donate:donation:ID`, `wc:order:ID`, `forms:TRACK`…) ile upsert; `base_amount` NULL = kur yok |
| `{prefix}flxcrm_tags`, `{prefix}flxcrm_contact_tags` | Etiketler |
| `{prefix}flxcrm_segments` | Kayıtlı segment: `rules` JSON, `count` |

## PHP

```php
$id = FLXCRM_Contacts::upsert( array( 'name' => 'Ayşe', 'email' => 'a@b.c', 'phone' => '0532…', 'roles' => array( 'volunteer' ), 'source' => 'my-plugin', 'link' => array( 'my-plugin', 42 ) ) );
FLXCRM_Events::add( $id, 'note', array( 'title' => 'Not', 'uniq' => 'my-plugin:note:42', 'data' => array( 'text' => '…' ) ) );
FLXCRM_Contacts::add_roles( $id, array( 'partner' ) );
FLXCRM_Tags::attach( $id, FLXCRM_Tags::ensure( 'Kurban 2026' ) );
FLXCRM_Contacts::ids( array( 'rules' => $segment['rules'] ) ); // segmentteki kişi id'leri
```

Kaynak veri yalnız boş alanları doldurur. İzinler kaynaktan yalnız açılır (kapatma elle ya da REST'le).

## Ortak kişi kartı

Kişi tek yerde (CRM) tutulur. Flux Donate bağışçı sayfası, Flux Commerce müşteri sayfası, Flux Chat gibi kişi gösteren
her ekran CRM kartını gösterir; eklentinin kendi tablosu kişiye `link` ile bağlıdır. CRM yoksa ya da kullanıcının
`flxcrm_view` yetkisi yoksa fonksiyon false döner, eklenti kendi sayfasını gösterir.

```php
// Bağışçı sayfası (Flux Donate)
if ( function_exists( 'flxcrm_person_card' ) ) {
	$cid = flxcrm_contact_for( array( 'link' => array( 'donate', 'donor:' . $donor_id ), 'email' => $email, 'phone' => $phone ) );
	if ( ! $cid ) { // henüz eşitlenmemişse anında oluştur
		$cid = flxcrm_ensure_contact( array( 'name' => $name, 'email' => $email, 'phone' => $phone, 'source' => 'donate', 'link' => array( 'donate', 'donor:' . $donor_id ) ) );
	}
	if ( $cid && flxcrm_person_card( $cid, array(
		'context' => 'donate',                                   // kendi bölümlerinizi bu bağlamda ekleyin
		'base'    => admin_url( 'admin.php?page=flxb-donors&donor=' . $donor_id ), // sekmeler/formlar buraya döner
		'back'    => array( admin_url( 'admin.php?page=flxb-donors' ), __( 'Bağışçılar', 'flx-donate' ) ),
	) ) ) {
		return; // kart basıldı
	}
}
// … eski sayfa
```

| Kaynak | `link` |
|---|---|
| Flux Donate bağışçı | `array( 'donate', 'donor:ID' )` |
| WooCommerce müşteri | `array( 'commerce', 'email:adres' )` (yoksa `email` ile bulunur) |
| Flux Chat sohbet | `array( 'chat', 'chat:ID' )` |
| WP kullanıcı | `array( 'users', 'user:ID' )` ya da `user_id` |

Eklentiye özel bölümler (hepsi `( array $contact, string $context )`):

| Eylem | Yer |
|---|---|
| `flxcrm_person_card_actions` | Başlıktaki düğmeler (ör. "Bağış ekle") |
| `flxcrm_person_card_main_before` | Zaman tünelinden önce (ör. düzenli bağışlar) |
| `flxcrm_person_card_main` | Zaman tünelinden sonra |
| `flxcrm_person_card_side` | Yan sütun (ör. sertifikalar, KVKK onayı) |

Bölümlerde `.flxcrm-card`, `.flxcrm-card__head`, `.flxcrm-list` sınıflarını kullanın, ayrı CSS gerekmez. Stilin başta
yüklenmesi için sayfanızın `admin_enqueue_scripts`'inde `flxcrm_person_card_enqueue()` çağırın (çağırmazsanız kart
kendisi yükler). Kart dışarıda "CRM kartı" düğmesi gösterir; kişiyi silme yalnız CRM'de.

## Eylemler

| Kanca | Argümanlar |
|---|---|
| `flxcrm_contact_created` | `$id, $row` |
| `flxcrm_contact_updated` | `$id, $changed` |
| `flxcrm_contact_deleting` | `$id` |
| `flxcrm_role_added` | `$id, $role` |
| `flxcrm_tag_added` / `flxcrm_tag_removed` | `$id, $tag_id` |
| `flxcrm_consent_changed` | `$id, array( kanal => 0|1 )` |
| `flxcrm_segment_changed` | `$id, $new, $old` (RFM segmenti) |
| `flxcrm_event` | `$event_id, $contact_id, $type, $row` (yalnız yeni olay) |
| `flxcrm_loaded` | eklenti yüklendi |

Otomasyonlar (0.2) bu eylemleri tetikleyici olarak kullanacak.

## Filtreler

| Filtre | Ne |
|---|---|
| `flxcrm_roles` | Rol listesi `key => [label, color, builtin]` |
| `flxcrm_segment_fields` | Segment alanı ekle: `key => [label, type, ops]`; SQL için `flxcrm_segment_compile` (`null, $rule` → `[ 'sql', params ]`) |
| `flxcrm_fx_rate` | `null, $currency, $base, $date` → kur. Kur yoksa ödeme sıklığa sayılır, değere sayılmaz |
| `flxcrm_paid_order_statuses` | Ödenmiş sayılan WooCommerce durumları |
| `flxcrm_user_roles` | Kişi olarak alınan WP rolleri (varsayılan customer, subscriber) |
| `flxcrm_phone` | Telefon normalleştirme |
| `flxcrm_sync_sources` | Eşitlemeye kaynak ekle: `KEY => [label, class]`; sınıf `backfill( $page, $per )` → `[done, scanned, total]` |
| `flxcrm_mail_requires_consent` | Flux Mail alıcılarında izin şartı (varsayılan true) |

## REST `flx-crm/v1`

| Yöntem | Yol | Ne |
|---|---|---|
| GET | `/contacts` | `s, role, segment, tag, consent, risk, status, orderby, order, paged, per_page` |
| POST | `/contacts` | Kişi ekle (e-posta ya da telefon şart; varsa mevcut kişi döner) |
| GET / POST / DELETE | `/contacts/{id}` | Kart (son olaylarla), güncelle, sil |
| POST | `/contacts/{id}/events` | Elle olay: `type` (note, call, meeting, email_sent), `text` |
| POST / DELETE | `/contacts/{id}/tags`, `/contacts/{id}/tags/{tag}` | Etiket |
| DELETE | `/events/{id}` | Elle eklenen olayı sil |
| GET | `/stats`, `/meta` | Özet sayaçlar; roller, segmentler, etiketler |
| GET | `/segments`, `/segments/{id}/contacts` | Kayıtlı segmentler ve kişileri |
| POST | `/segments/preview` | `{ rules }` → `count, summary, sample` |
| POST | `/score` | RFM'i yeniden hesapla |

## Entegrasyonlar

- **Flux Mail**: `flxi_recipient_sources` altında "CRM segmenti" (`flxcrm_seg:ID`) ve "CRM rolü" (`flxcrm_role:KEY`); kanalın iznini veren kişiler alıcı olur.
- **Flux AI**: `flxcrm_find_contacts`, `flxcrm_get_contact`, `flxcrm_overview` (okur), `flxcrm_add_note` (yazar).
- **Flux App**: `crm` modülü. **Flux Roles**: `crm` grubu. **Sistem**: `flxcrm.sources`, `flxcrm.sync`, `flxcrm.scoring`, `flxcrm.currency`.
