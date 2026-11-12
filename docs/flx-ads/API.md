# Flux Ads API (0.7.0)

Önek `flxads_` / `FLXADS_`. Yetkiler `flxads_view` (rapor), `flxads_manage` (ayar). Tablolar `{prefix}flxads_orders` (siparişin atfı, sipariş başına bir satır) ve `{prefix}flxads_conversions` (platform × sipariş gönderim kuyruğu, `UNIQUE(platform, event_id)`) ve `{prefix}flxads_spend` (günlük reklam harcaması: `platform`, `day`, `ad_key`, `campaign_id`, `campaign`, `adset`, `ad`, `spend`, `currency`, `impressions`, `clicks`, `manual`; `UNIQUE(platform, day, ad_key)`).

Ödenen her sipariş (bağış ya da mağaza) dönüşümdür; rapor ve MCP `kind` ile ayırır.

## Akış

1. Ön yüz `assets/front.js`: çerez onayı (FluxWP tema banner'ı `flx_cerez=kabul:<rev>`, WP Consent API `wp_has_consent('marketing')` ya da ayar "always") gelince `flxads_ok=1` ve URL'de UTM/tıklama kimliği varsa `flxads_t={f:ilk,l:son}` çerezi yazılır, açık pixel'ler yüklenir, sayfanın olayları gönderilir.
2. Ödeme adımı (`woocommerce_checkout_order_created`, `woocommerce_store_api_checkout_order_processed`, ön yüzde `woocommerce_new_order`): çerezler + `_fbp/_fbc/_ttp/_ga` siparişe `_flxads` meta olarak yazılır.
3. Ödendi (`woocommerce_payment_complete`, `processing`, `completed`): `flxads_orders` satırı, açık platformlara `Purchase` kuyruğu (onay yoksa `skipped/no_consent`), eylem `flxads_order_attributed`.
4. Gönderim: istek sonunda (`shutdown`, `fastcgi_finish_request` sonrası), saatlik `flxads_retry` ve Flux Ads ekranı açılınca. 6 deneme, artan bekleme.

Olay kimliği `order-<sipariş id>` (tarayıcı `eventID` / `event_id` ile aynı). GA4 sunucu olayı 30 dk bekler, teşekkür sayfası `purchase` gönderdiyse (`_flxads_browser`) atlanır.

## Harcama (0.2.0)

- Meta: `GET /v21.0/act_<hesap>/insights?level=ad&time_increment=1`, sayfalı (en çok 20 sayfa, 40 sn). İlk eşitleme son 30 gün, sonra son 7 gün; başarılı okumada aralıktaki eski API satırları silinip yazılır. Anahtar `meta_ads_token` (ads_read), boşsa `meta_token`; wp-config `FLXADS_META_ADS_TOKEN`.
- Ne zaman: saatlik `flxads_retry` ve Flux Ads ekranı açılınca (6 saatten eskiyse, yanıt gönderildikten sonra). Durum `flxads_spend_sync` seçeneğinde.
- Elle harcama: Harcama sekmesi, `manual = 1`, kanal + gün + kampanya + isteğe bağlı reklam. Tutar "1.200,50" / "1.200" / "1200.5" okunur.
- Eşleştirme: kampanya `FLXADS_Spend::norm()` ile (`Fidan Kasım 2026` = `fidan-kasim-2026`) ya da utm_campaign = Meta kampanya kimliği; reklam utm_content = reklam adı; kanal platform. Harcaması olup ödemesi olmayan kampanya da satır olur (ROAS 0).
- ROAS paydası: harcaması bilinen kanalların geliri.

## Bağışçı değeri (0.3.0)

- `FLXADS_Cohort::value( $by = 'campaign'|'channel', $f )`: ilk ödemesi `from`–`to` aralığında olan kişiler (`person_id > 0`), ilk ödemenin kanal/kampanyasına yazılır; tüm ödemeleri (reklamsız da) toplanır. Satır: `new, first, ltv30, ltv90, ltv180, ltv, repeat, rate, avg, spend, cac (spend/new), ratio (avg/cac), payback (gün)`. Harcama dönemin kampanya harcaması (`FLXADS_Spend::totals`, kampanya kimliğiyle de eşleşir). `kind` ilk ödemenin türüne uygulanır.
- `FLXADS_Cohort::matrix( $f, $months = 12 )`: edinme ayı × geçen 30 günlük dilim, kişi başı birikimli değer; henüz yaşanmamış hücre `null`.
- SQL taşınabilir: `HAVING` yok (SQLite çevirmeni desteklemiyor), türetilmiş tablo kullanılır.

## Meta kitleleri (0.3.0)

- Kayıtlar `flxads_audiences` seçeneğinde: `id, name, source (all | donors | rfm:<segment> | seg:<CRM segment id> | lookalike:<id>), purpose (target|exclude), meta_id, kind (custom|lookalike), count, synced, error, last{added,removed}`.
- Üyeler `{prefix}flxads_aud_members (aud, person_id, em, ph, sig)`: yalnız SHA-256 özetleri (çıkarmak için). Düz veri saklanmaz.
- Kişiler: `flx_people` içinde `status = active`, `consent_ads = 1`, e-posta ya da telefonu olan. Şema `EMAIL, PHONE, FN, LN, COUNTRY` (Meta normalizasyonu, ad soyad noktalama ve boşluksuz küçük harf).
- Eşitleme: fark; önce çıkar (`DELETE /<audience>/users`, `EMAIL, PHONE`), sonra ekle (`POST /<audience>/users`), 5000'lik parti. Bilgisi değişen kişinin eski özeti çıkar, yenisi girer. Günde bir (`flxads_retry` + ekran açılınca), 40 sn sınırı.
- `flxcrm_consent_changed` (ads = 0) ve `flxcrm_contact_deleting`: kişi istek sonunda tüm kitlelerden çıkarılır.
- Oluşturma `POST act_<hesap>/customaudiences` (`subtype=CUSTOM`, `customer_file_source=USER_PROVIDED_ONLY`); benzer kitle `subtype=LOOKALIKE`, `lookalike_spec {type: similarity, country, ratio}`. Anahtar gövdede gider. ads_management izni gerekir.
- PHP: `FLXADS_Audiences::create( $d )`, `::lookalike( $id, $country, $ratio )`, `::sync( $id )`, `::delete( $id )`, `::member_ids( $source )`, `::sources()`.

## Fonksiyonlar

- `flxads_track( $name, $data )`: bu sayfada tarayıcıdan olay (ör. `Lead`). `$data`: `id`, `value`, `currency`, `items[{id,name,price,qty}]`, `title`.
- `flxads_channel( $touch )`: `meta|google|tiktok|microsoft|other|''`.
- `FLXADS_Report::grouped( $by, $filters )`: `$by` = `channel|campaign|content|source|landing|first`; filtre `from`, `to` (Y-m-d), `kind` (`all|donations|orders`), `channel`, `campaign`. Harcama varsa dönüşte `spend` true, satırlarda `spend`, `roas`, `cpa`; toplamlarda `spend`, `spend_income`, `roas`, `cpa`, `cpn` (yeni bağışçı başı).
- `FLXADS_Spend::sync_meta( $days = 0 )`, `FLXADS_Spend::totals( $from, $to, $channel )`, `FLXADS_Spend::add_manual( $in )`.
- `FLXADS_Orders::attribution( $order )`: `[ son dokunuş, ilk dokunuş, _flxads ]`.

## Filtre / eylem

- `flxads_seasons( $list, $year )`: sezon planına dönem ekle/değiştir (`key => [ name, start, end, prep_days, approx ]`).
- `flxads_default_settings`, `flxads_channel`, `flxads_is_donation`, `flxads_browser_events`, `flxads_front_active`.
- `flxads_order_attributed( $order_id, $row, $order )`.
- `flxads_suggestions_new( $suggestions )`: optimizer yeni öneri bulunca (ör. Flux AI brifingi).

## Flux CRM

- Olay türü `ad_conversion` (tutar 0: ömür boyu değer iki kez artmasın), kaynak `ads`, `uniq` `ads:order:<id>`.
- Kişi kartı yan sütunu `flxcrm_person_card_side` → "Reklam kaynağı".
- Kişide `utm_source/utm_campaign` boşsa ilk dokunuşla doldurulur. `flx_person_merged` atıf satırlarını taşır.

## MCP

Okuma: `flxads_report` (by, from, to, kind, channel; harcama, ROAS, maliyet dahil), `flxads_status` (harcama eşitleme durumu dahil), `flxads_settings_get` (gizliler yalnız `*_set`), `flxads_send_log` (status, limit), `flxads_spend_list` (from, to; elle girilenler id'leriyle), `flxads_value_report` (from, to, by, kind, cohorts), `flxads_audiences_list`, `flxads_campaigns` (range 7|30|90), `flxads_campaign_detail` (id, range), `flxads_studio_context`, `flxads_drafts` (id), `flxads_suggestions` (status open|all, refresh), `flxads_season_plan` (days), `flxads_tests`.

Yazma (`flxads_manage`, `includes/ai-write.php`): `flxads_settings_update` (yalnız verilen alanlar, `flxads_clean_settings( $in, true )`), `flxads_test_connection` (platform), `flxads_resend` (ids), `flxads_spend_add` (channel, day, campaign, ad, spend, currency), `flxads_spend_delete` (id, yalnız elle girilen), `flxads_spend_sync` (days), `flxads_audience_create` (name, source, purpose, lookalike, country, ratio), `flxads_audience_lookalike` (id, country, ratio), `flxads_audience_sync` (id), `flxads_audience_delete` (id), `flxads_ad_status` (id, status PAUSED|ACTIVE), `flxads_ad_budget` (id, amount: hesap para biriminde günlük bütçe), `flxads_draft_save` (id?, name, objective, link, daily_budget, start, end, adsets[], ads[], notes; yalnız yerel taslak), `flxads_draft_send` (id), `flxads_draft_delete` (id), `flxads_suggestion_apply` (id), `flxads_suggestion_dismiss` (id, note), `flxads_season_add` (name, start, end, prep), `flxads_season_delete` (id), `flxads_test_create` (type ab|holdout, name, campaign, a, b, audience, pct, start, end), `flxads_test_end` (id), `flxads_test_delete` (id).

- Flux AI sohbeti (`flxai_tools`): yazan her araç `confirm` → onay kartı; kart onaydır.
- MCP (`flx_mcp_tools`): `destructive` olanlar (`flxads_settings_update`, `flxads_spend_delete`, `flxads_audience_create`, `flxads_audience_lookalike`, `flxads_audience_delete`, `flxads_ad_status`, `flxads_ad_budget`, `flxads_draft_send`, `flxads_draft_delete`, `flxads_suggestion_apply`, `flxads_test_create`, `flxads_test_end`, `flxads_test_delete`) ilk çağrıda önizleme + `confirm_token` (15 dk, tek kullanımlık, aynı argümanlara bağlı) döner.
- Gizli anahtarlar (`meta_token`, `meta_ads_token`, `tiktok_token`, `ga4_secret`) hiçbir araçla okunmaz/yazılmaz. Kişisel veri dönmez.
- Yazmalar `flxads_tool_log` seçeneğine (son 100: tool, channel, user, at, args).

## Reklam yöneticisi (0.4.0)

`FLXADS_Manager` (`includes/class-flxads-manager.php`), ekran Flux Ads › Kampanyalar (`flxads_admin_url( 'campaigns', [ 'campaign' => <id>, 'range' => 7|30|90 ] )`).

- `campaigns( $range )`: `[ currency, base, since, until, rows[], totals ]`; satırda Meta alanları (`status`, `effective`, `objective`, `daily`, `lifetime` tam birimde, `spend`, `impressions`, `reach`, `clicks`, `ctr`, `cpm`, `frequency`) + sitedeki `income`, `payments`, `new`, `roas`, `cpa`. Eşleşme: `utm_campaign` = Meta kampanya kimliği ya da `FLXADS_Spend::norm()` ile ad.
- `campaign( $id, $range )`: kampanya + `adsets[]` (`targeting` özeti, `ads[]` ve `creative`) + `breakdown[ age_gender | placement ]`.
- `set_status( $id, 'ACTIVE'|'PAUSED', $channel )`, `set_budget( $id, $amount, $channel )`: yalnız günlük bütçesi olan nesnede; Meta'ya en küçük birimde gider (ondalıksız para birimleri `ZERO_DECIMAL`). Silme/arşivleme yok.
- Okumalar 10 dk transient (`flxads_mgr_*`, anahtar listesi `flxads_mgr_keys`); değişiklik `flush()` eder. Değişiklik geçmişi `flxads_mgr_log` (son 100: at, user, channel panel|ai|mcp, id, name, field, from, to), `history( $limit )`.

## Stüdyo (0.5.0)

`FLXADS_Studio` (`includes/class-flxads-studio.php`), ekran Flux Ads › Stüdyo (`flxads_admin_url( 'studio', [ 'edit' => <id>|new ] )`).

- Taslaklar `flxads_drafts` seçeneğinde (en çok 50): `id, name, objective (OUTCOME_SALES|OUTCOME_TRAFFIC|OUTCOME_AWARENESS), link, daily_budget, start, end, adsets[{name, age_min, age_max, gender, countries[], audiences[], exclude[], share}], ads[{name, text, headline, description, cta, image}], notes, status (draft|sent|error), by (panel|ai|mcp), meta{campaign, adsets[], ads{}}, error`.
- `generate( $brief )`: `flxai_chat` (source `flx-ads`, purpose `campaign_draft`), bağlam `context()` (son 90 gün kampanyalar, kitleler, CRM sayıları; kişisel veri yok). Kullanıcının verdiği alanlar AI'ın üstüne yazar.
- `send( $id, $channel )`: kampanya → reklam setleri (bütçe payı, satışta `OFFSITE_CONVERSIONS` + pixel `PURCHASE`) → kreatif (`object_story_spec`, `meta_page`) → reklamlar, HEPSİ `PAUSED`. Link UTM: `utm_source=facebook&utm_medium=paid&utm_campaign=<kampanya id>&utm_content=<çeşit>`. Yarıda kalırsa sonraki deneme oluşturulanları yeniden kullanır. Gönderilen taslak düzenlenmez (kopyalanır).
- Diğer: `all()`, `get()`, `save( $in, $id, $by )`, `duplicate()`, `delete()`, `missing()`. Ayar `meta_page` (Facebook sayfa kimliği).

## Öneriler (0.6.0)

`FLXADS_Optimizer` (`includes/class-flxads-optimizer.php`), ekran Flux Ads › Öneriler (`flxads_admin_url( 'suggestions' )`). Kendiliğinden hiçbir şey uygulanmaz.

- Ne zaman: `flxads_retry` ile günde bir (20 saatten eskiyse), ekran açılınca (1 saatten eskiyse), "Şimdi kontrol et". Son durum `flxads_opt_run` (`at, ok, new, open, rules`).
- Kurallar (son 7 gün, yalnız ACTIVE): `pause` satış amaçlı kampanya `min_spend` harcayıp 0 ödeme; `budget_down` ROAS < hedef × 0,5 → günlük bütçe −%20; `budget_up` ROAS ≥ hedef × 1,5 ve ≥ 3 ödeme → +%20; `pause_ad` sette ≥ 2 etkin reklam, ≥ 1000 gösterim, CTR < set ortalaması × 0,5; `fatigue` set frekansı ≥ 4 (eylem yok, Stüdyo'ya yönlendirir). Harcamaya göre ilk 10 kampanyanın setlerine bakılır.
- Ayarlar `opt_roas` (2), `opt_min_spend` (0 = ödeme başı ortalama maliyetin 2 katı, ödeme yoksa 500).
- Kayıt `flxads_suggestions` (en çok 150): `id, sig (tür:nesne), type, level, object, name, data, action {field: status|budget, to}, status open|applied|dismissed|gone, created, seen, decided, user, channel, result`. Kapatılan 14 gün, uygulanan 7 gün yeniden önerilmez; artık geçerli olmayan açık öneri `gone` olur.
- PHP: `refresh()`, `detect()`, `open()`, `all()`, `get()`, `apply( $id, $channel )` (`FLXADS_Manager::set_status|set_budget`, değişiklik geçmişine yazılır), `dismiss( $id, $channel, $note )`, `reason( $s )`, `export( $s )`.

## Sezon planı ve testler (0.7.0)

- `FLXADS_Planner` (`includes/class-flxads-planner.php`), ekran Flux Ads › Sezon planı (`planner`). `year( $y )` yerleşik dönemler (hicri tarihler `HIJRI` sabitinde 2025–2028, yaklaşık), `upcoming( $days )` (state now|prep|later, `last` geçen yılın aynı dönemi: payments, income, new, spend), `last_year( $e )`, `brief( $e )`, `studio_url( $e )` (Stüdyo `pre_brief`, `pre_start`, `pre_end` ile dolu açılır). Kullanıcı tarihleri `flxads_seasons_custom` (`add_custom`, `delete_custom`). Öneriler hazırlık penceresinde `season` önerisi üretir.
- `FLXADS_Experiments` (`includes/class-flxads-experiments.php`), ekran Flux Ads › Testler (`experiments`), kayıt `flxads_experiments` (en çok 50).
  - A/B: `campaign`, `a`, `b` (Meta reklam kimlikleri). Ölçü sitedeki ödeme / tıklama (utm_content = reklam adı, `FLXADS_Spend::norm`); toplam ödeme < 10 ise CTR. Kazanan için p < 0,05 ve her reklamda ≥ 50 tıklama (CTR'de ≥ 1000 gösterim). Meta sayıları `FLXADS_Manager::campaign( $id, 'Y-m-d:Y-m-d', false )`.
  - Holdout: `audience` (hedef özel kitle), `pct` 5–50. Kişi `crc32( aud:person ) % 100 < pct` ise holdout; `FLXADS_Audiences::set_holdout()` kitleyi hemen eşitler (holdout Meta'dan çıkar, bitince geri girer). Sonuç: iki grubun dönemdeki ödeme oranı, `lift`, `extra_payers`, `extra_income`; holdout < 100 kişiyse karar yok.
  - `create( $d, $by )`, `finish( $id )` (bitiş tarihi geçince `flxads_retry` ile kendiliğinden), `delete( $id )`, `result( $e )`, `verdict( $e, $r )`, `ztest()`.
