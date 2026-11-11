# Flux AI Chatbot · Geliştirici notları

Önek `flxcb_`, REST `flx-chatbot/v1`, ayar `flxcb_settings` (şema `flx_settings_schema` ile), yetki `flxcb_reply`
(yönetici, ayrıca `flxc_use` olan Flux Chat temsilcileri cevap verebilir).

## Tablolar
- `{prefix}flxcb_conversations`: token (HMAC), status `bot|queued|agent|offline|closed`, ad/e-posta/telefon, user_id,
  assigned_to, flxc_chat (Flux Chat sohbet id), sayfa, puan, ai_turns, unread, lead, order_id, okundu işaretleri, meta.
- `{prefix}flxcb_messages`: conv_id, role `visitor|bot|agent|system`, type `text|image|file`, body, dosya alanları, ts (ms), meta (cards, event).

## Ön yüz
- `window.FluxChatbot`: `open(message?)`, `close()`, `toggle()`, `update(cfg)` (önizleme), `view('home'|'chat')`, `teaser()`.
- Açma: `[flx_chatbot label message]`, `#flxcb-open`, `?flxcb=open`, `data-flxcb-open`, `data-flxcb-message`.
- Ziyaretçi anahtarı `localStorage.flxcb_t` + `flxcb_t` çerezi (WooCommerce sipariş eşleştirme).

## Filtreler
| Filtre | Ne |
|---|---|
| `flxcb_show` ( bool ) | Pencere bu istekte gösterilsin mi. |
| `flxcb_widget_config` ( array ) | Ön yüze giden ayarlar. |
| `flxcb_show_branding` ( bool ) | "FluxWP ile çalışır" yazısı. |
| `flxcb_is_online` ( bool ) | Temsilci çevrim içi mi (mesai). |
| `flxcb_bot_tools` ( array $tools, $conv ) | Asistanın araçları (OpenAI biçimi). |
| `flxcb_bot_run_tool` ( null, $name, $args, $conv ) | Eklenen aracı çalıştırır (null kalırsa yerleşik araç). |
| `flxcb_bot_context` ( string, $conv ) | Sistem mesajındaki site bağlamı. |
| `flxcb_allowed_mimes` ( array ) | Gönderilebilir dosya türleri. Saklanan uzantı bu listenin MIME'inden gelir; istemci dosya adı kullanılmaz. |

## Eylemler
`flxcb_conversation_created`, `flxcb_message_added` ( $row ), `flxcb_contact_changed` ( $id, $fields ), `flxcb_handoff` ( $conv, $by, $target ),
`flxcb_closed`, `flxcb_rated`, `flxcb_lead` ( $conv, $note, $crm_id ), `flxcb_order_attributed` ( $conv, $order ),
`flxcb_visitor_typing`, `flxcb_visitor_read`, `flxcb_settings_saved`.

## Ziyaretçi mesajı
- `POST /flx-chatbot/v1/message`: asistan açıksa NDJSON. Aynı sohbette süren bir cevap varken ikinci istek `409` / `flxcb_busy` döner; mesaj kaydedilmez. Kilit `add_option` (`flxcb_fly_{id}`), 55 sn sonra bayat sayılır ve bırakılınca silinir.
- İstek bütçesi 45 sn (bütün turlar + akış). Kalan süre Flux AI `flxai_chat` `timeout` alanına gider; 3 sn'den az kaldıysa yeni tur veya araç çalışmaz, kısmi cevap yazılır.

## Flux AI & MCP
SDK ile `flx-chatbot` sistemi (model ayarı) ve yöneticiler için salt okunur araçlar: `flxcb_conversations`,
`flxcb_conversation`, `flxcb_stats`. Ziyaretçi asistanı bu araçları görmez; kendi araçları sohbete özel
(search_site, read_page, search_products, my_orders, save_contact, handoff_to_human). Amaç etiketi `chatbot`.

## Flux Chat sanal kanal (Flux Chat 1.37.0)
Flux Chat'e WhatsApp dışı kanal eklemek için (Flux AI Chatbot bu yolla bağlanır):

| Filtre | Ne |
|---|---|
| `flxc_virtual_conn` ( bool, $conn_id ) | Bu bağlantı id'si sanal mı. Chatbot: `flx-web`. |
| `flxc_virtual_request` ( $res, $conn_id, $method, $path, $body ) | `flxc_evo()` isteğini kanal cevaplar. Evolution v2 yolları: `message/sendText`, `message/sendMedia`, `message/sendWhatsAppAudio`, `chat/sendPresence`, `chat/markMessageAsRead`, `message/sendReaction`, `chat/fetchProfilePictureUrl`. Dönüş `array( 'ok' => bool, 'code' => int, 'json' => array( 'key' => array( 'id' => '…' ) ) )`. |
| `flxc_virtual_label` ( string, $chat_row ) | Listede numara yerine görünen etiket ("Site sohbeti · e-posta"). |
| `flxc_channels` ( array ) | Tek gelen kutusu kanal kaydı: `id => [ label, icon (24'lük viewBox SVG içi, çizgi), color, active, conns (sanal bağlantı id'leri) ]`. Varsayılan `whatsapp` ve `web`. Instagram, e-posta gibi yeni kanal buraya eklenir; listede avatar ikonu ve kanal süzgeci buradan gelir. |

`flxc_is_virtual_conn( $conn )`, `flxc_channels()`, `flxc_chat_channel( $chat )`. Chatbot tek gelen kutusunda (`flxcb_fc_unified()`) her sohbeti ilk ziyaretçi mesajında `flxcb_fc_attach()` ile açar. Sanal sohbetlerde ekip bildirimi ve grup aynası ilk WhatsApp hattını kullanır, çağrı merkezi
asistanı karışmaz. Chatbot jid biçimi `site-{id}@web`, mesaj wa_id `cb{mesaj id}`.
