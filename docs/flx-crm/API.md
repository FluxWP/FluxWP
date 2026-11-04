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

Zaman tüneli kartın içindedir: sekmeler, 20'şer yükleme (aşağı kaydırınca `wp_ajax_flxcrm_timeline`), mobilde kendi içinde kayma. Olay türleri: donation, order, form, message_in/out (her WhatsApp mesajı ve Flux Mail WhatsApp bildirimi), email_sent, email_in, sms_sent, chat_started, note, call, meeting, signup, created, role_added, segment_entered, consent. Kendi olayınızı `FLXCRM_Events::add()` ile yazın, kartta kendiliğinden görünür.

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
- **Flux AI ve MCP**: aşağıda "Yapay zekâ ve MCP araçları".
- **Flux App**: `crm` modülü. **Flux Roles**: `crm` grubu. **Sistem**: `flxcrm.sources`, `flxcrm.sync`, `flxcrm.scoring`, `flxcrm.currency`.

## Yapay zekâ ve MCP araçları (0.2.0)

CRM'deki her şey Flux AI sohbetinden ve MCP'den (Flux AI & MCP, `/wp-json/flx-mcp/v1/mcp`) okunur ve düzenlenir.
Araçlar **tek kayıtta** (`flxcrm_tools()`, `includes/ai-tools.php`) tanımlanır, iki kanala da oradan gider. Kod
`flxai_tools` ve `flxseo_mcp_tools` filtrelerine kendisi ekler; Flux AI sohbetinde MCP'den gelen aynı adlı araçların
yerine doğrudan çalışan sürüm konur.

| Tür | Araçlar |
|---|---|
| Okur (`flxcrm_view`) | `flxcrm_overview`, `flxcrm_meta` (roller, etiketler, olay türleri, segment alanları, ayarlar), `flxcrm_find_contacts` (q, role, rfm, tag, consent, owner_id, risk, status, segment_id, rules, sıralama, sayfa), `flxcrm_get_contact`, `flxcrm_timeline`, `flxcrm_recent_events`, `flxcrm_find_duplicates`, `flxcrm_list_segments`, `flxcrm_preview_segment` |
| Okur (`flxcrm_manage`) | `flxcrm_bulk_preview` (sayı, örnek, `confirm_token`) |
| Düzenler (`flxcrm_manage`) | `flxcrm_create_contact`, `flxcrm_update_contact` (alanlar, roles / add_roles / remove_roles, consent, owner, status, note), `flxcrm_tag_contact`, `flxcrm_add_note`, `flxcrm_save_tag`, `flxcrm_save_segment`, `flxcrm_rescore`; `flxcrm_update_settings` yalnız `manage_options` |
| Onaylı (`flxcrm_manage`) | `flxcrm_delete_event`, `flxcrm_delete_tag`, `flxcrm_delete_segment`, `flxcrm_merge_contacts`, `flxcrm_delete_contact`, `flxcrm_bulk` (en çok 5000 kişi: add_tag, remove_tag, add_role, remove_role, archive, restore, set_owner, revoke_consent, delete), `flxcrm_send_message` (Flux Mail ile tek kişiye e-posta/WhatsApp/SMS, kanal izni şart) |

**Onay:**
- Flux AI sohbetinde değişiklik yapan her araç onay kartında bekler (kart onaydır). `flxcrm_bulk` ayrıca önizleme anahtarı ister: model önce `flxcrm_bulk_preview` ile kaç kişinin etkileneceğini göstermek zorunda.
- MCP'de onaylı araçlar ilk çağrıda hiçbir şey değiştirmez; `confirmation_required`, `preview`, `confirm_token` (15 dk, tek kullanımlık, kullanıcıya ve argümanlara bağlı) döner. Aynı argümanlar + `confirm_token` ile tekrar çağrılınca uygulanır. Argüman değişirse anahtar geçmez.
- İzni (consent) açmak yalnız kişi tek tek düzenlenirken mümkün; toplu işlemde yalnız kapatılır. AI/MCP izinleri araçla değiştirilemez.

**Ayarlar** (CRM › Ayarlar › Yapay zekâ ve MCP): `ai_tools` (hepsi), `ai_write` (düzenleme), `ai_destructive` (onaylı işlemler). Okuma dışındaki her çağrı `flxcrm_tool_log` seçeneğine yazılır (son 200) ve aynı ekranda görünür.

**Genişletme** (ileride otomasyonlar ve kendi kendini geliştiren akışlar):

```php
add_filter( 'flxcrm_tools', function ( $tools ) {
	$tools['flxcrm_my_tool'] = array(
		'title'       => __( 'Benim aracım', 'my-plugin' ),
		'description' => 'English text for the model.',
		'schema'      => array( 'type' => 'object', 'properties' => array( 'id' => array( 'type' => 'integer' ) ) ),
		'kind'        => 'write',              // read | write | destructive
		'cap'         => FLXCRM_CAP_MANAGE,
		'preview'     => null,                 // destructive: function ( $args, $ctx ) → onay özeti
		'run'         => function ( $args, $ctx ) { return array( 'ok' => true ); }, // hata: throw new Exception()
	);
	return $tools;
} );

// PHP'den (otomasyon): aynı yetki, aynı kayıt. Onaylı araçta 'confirmed' => true yalnız onay başka yerde alındıysa.
flxcrm_run_tool( 'flxcrm_tag_contact', array( 'id' => 5, 'add' => array( 'VIP' ) ), array( 'channel' => 'automation' ) );
```

`flxcrm_tool_ran` eylemi `( $name, $args, $result, $ctx )` her başarılı çağrıdan sonra çalışır. Kişi birleştirme `FLXCRM_Contacts::merge( $primary, $ids )` ve `flxcrm_contact_merged` eylemi `( $primary_id, $merged_id )`; zaman tünelinde `merged` olayı.
