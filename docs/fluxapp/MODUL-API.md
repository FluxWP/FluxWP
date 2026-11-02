# Uygulama yerel modül sözleşmesi (WhatsApp, Görevler, Bağış onayı)

Uygulama (Flux App / mobil) bir menüyü **yerel ekranla** açabilmek için o paketin iki şey vermesi gerekir:

1. `flxapp_modules` filtresiyle kayıt (Flux App, docs/flx-app/API.md › Yerel modüller):
   `id => {title, cap, menu, api, icon, version, order}`. `menu` dolu ise uygulama o wp-admin menü öğesini yerel ekranla açar, boşsa ayrı sekme olur. `cap` görmeyen kullanıcıya modül gitmez.
2. Aşağıdaki REST uçları. Tüm uçlar giriş ister (Flux App belirteci `Authorization: Bearer flxat_…` / `X-FluxWP-Token`, ya da çerez + nonce), yetkiyi paket kendisi denetler (rol adına değil cap'e bakar). Hata: WP_Error + uygun HTTP kodu, mesaj kullanıcı diline göre (`?_locale=user` gelir).

Ortak kurallar
- Tarih alanları ISO 8601 (`at`) + gösterim için `*_label` (site saat diliminde, kullanıcı dilinde).
- Liste uçları `{items, total, page, pages}` döner, `?page=&per_page=` (≤50) alır.
- Uygulama 4/8/15 sn aralıkla poll eder, sekme gizliyken durur. Uçlar hafif olmalı (Hostinger).
- Rozet sayıları zaten `flxapp/v1/counts` / menü rozetlerinden gelir; modül ayrıca vermek zorunda değil.

---

## 1. WhatsApp (Flux Chat)

Kayıt: `flxc-inbox` → `{title: 'WhatsApp', cap: <sohbetleri görme yetkisi>, menu: 'flx-chat', api: rest_url('flx-chat/v1/app'), icon: 'chat', order: 20}`

| Uç | Cevap |
|---|---|
| `GET /flx-chat/v1/app/chats?filter=open\|mine\|all&q=&page=` | `{items:[chat], total, page, pages}` (son mesaja göre yeniden eskiye) |
| `GET /flx-chat/v1/app/chats/<id>` | `chat` + `notes`, `donor` (varsa bağışçı kartı özeti) |
| `GET /flx-chat/v1/app/chats/<id>/messages?before=<msg_id>&limit=40` | `{items:[message] (eskiden yeniye), has_more}` |
| `GET /flx-chat/v1/app/chats/<id>/messages?after=<msg_id>` | yeni gelenler (poll) |
| `POST /flx-chat/v1/app/chats/<id>/send` `{text}` | `message` (gönderim kuyruğa alındıysa `status: 'pending'`) |
| `POST /flx-chat/v1/app/chats/<id>/read` | `{ok}` |
| `POST /flx-chat/v1/app/chats/<id>/bot` `{on: bool}` | `chat` (asistanı bu sohbette aç/kapat) |

- `chat`: `{id, name, phone, avatar, unread, last:{text, at, at_label, dir:'in'|'out', by:'customer'|'bot'|'staff'}, bot:{on}, tags:[], assigned:{id,name}|null, blocked}`
- `message`: `{id, dir:'in'|'out', by:'customer'|'bot'|'staff', author, text, media:{type:'image'|'audio'|'video'|'document', url, name}|null, at, at_label, status:'pending'|'sent'|'delivered'|'read'|'failed'}`

## 2. Görevler (Flux Tasks)

Kayıt: `flxg-tasks` → `{title: 'Görevler', cap: 'flxg_use', menu: 'flx-tasks', api: rest_url('flx-tasks/v1/app'), icon: 'task', order: 30}`

| Uç | Cevap |
|---|---|
| `GET /flx-tasks/v1/app/projects` | `{items:[{id, title, color, lists:[{id, title, status:'open'\|'done'}], open}]}` (kullanıcının görebildikleri, sol menü sırası) |
| `GET /flx-tasks/v1/app/tasks?view=mine\|today\|overdue\|project&project=&list=&q=&page=` | `{items:[task], total, page, pages}` |
| `GET /flx-tasks/v1/app/tasks/<id>` | `task` + `description_html`, `checklist:[{id,text,done}]`, `comments:[{id, author:{id,name}, text, at, at_label}]` |
| `POST /flx-tasks/v1/app/tasks` `{title, project, list?, due?, assignees?:[id]}` | `task` (`flxg_create`) |
| `POST /flx-tasks/v1/app/tasks/<id>` `{title?, status?, due?, list?, assignees?, priority?}` | `task` |
| `POST /flx-tasks/v1/app/tasks/<id>/comments` `{text}` | `comment` |
| `POST /flx-tasks/v1/app/tasks/<id>/checklist/<item>` `{done}` | `{ok}` |

- `task`: `{id, title, status:'open'|'done', project:{id,title,color}, list:{id,title}, due, due_label, overdue, priority:0-3, assignees:[{id,name,avatar}], comments, checklist:{done,total}, url}` (`url`: paneldeki sayfası)

## 3. Bağış onayı (Flux Donate)

Havale/EFT dekontu gelmiş, onay bekleyen bağışlar. Personelin telefondan onaylaması için.

Kayıt: `flxb-review` → `{title: 'Onay bekleyenler', cap: <bağış onaylama yetkisi>, menu: '', api: rest_url('flxb/v1/app'), icon: 'heart', order: 5}`

| Uç | Cevap |
|---|---|
| `GET /flxb/v1/app/pending?page=` | `{items:[pending], total, page, pages}` (eskiden yeniye) |
| `POST /flxb/v1/app/donations/<id>/approve` | `{ok, status_label, certificates:N}` (sertifikalar mevcut akışla gider) |
| `POST /flxb/v1/app/donations/<id>/reject` `{reason}` | `{ok, status_label}` |

- `pending`: `{id, order_id, title, amount_label, date_label, waiting_label ("2 gündür"), donor:{name, phone, email}, ref:{name}|null, lines:[{title, quantity, amount_label}], receipt:{url, type:'image'|'pdf', at_label}|null, note, web_url}`
- `receipt.url` giriş yapmış kullanıcıya açık olmalı (belirteçle ya da kısa ömürlü imzalı adres).

---

Uygulama tarafı bu sözleşmeye göre yazıldı; modül kaydı gelmeyen sitede menü eskisi gibi panel görünümünde açılır.

## 4. Ana sayfa kartları (Flux App 0.3.2, `flxapp_dashboard`)

`GET flxapp/v1/dashboard` → `{ cards: [...], lists: [...] }`. Uygulama 0.3.13 ana sayfada gösterir.

- Kart: `{ id, title, value (metin), sub?, icon, tone? (accent|warn), open? ("mod:<modül>" ya da menü slug), url? (panel sayfası), order? }`
- Liste: `{ id, title, icon, open?, url?, order?, items: [ { title, sub?, meta?, url? } ] }` (en çok 6 öğe önerilir)
- Çekirdek kartlar Flux App'da (WP + WooCommerce). Paketler kendi kartını ekler, yetkiyi kendisi denetler:

```php
add_filter( 'flxapp_dashboard', function ( $d, $user ) {
    if ( user_can( $user, 'flxb_manage' ) ) {
        $d['cards'][] = array( 'id' => 'flxb-today', 'title' => __( 'Bugünkü bağış', 'flx-donate' ),
            'value' => '1.250 ₺', 'sub' => '8 bağış', 'icon' => 'heart', 'tone' => 'accent', 'open' => 'mod:flxb-review', 'order' => 5 );
    }
    return $d;
}, 10, 2 );
```

Önerilen kartlar (sahip thread ekler): Bağış (bugünkü bağış, onay bekleyen), Analitik (canlı ziyaretçi, bugünkü ziyaret), İletişim (okunmamış e-posta), Görev (bana atanan açık görev), Çağrı Merkezi (bekleyen sohbet).
Simge adları: post, page, comment, media, users, update, cart, clock, heart, chart, mail, task, chat, certificate (bilinmeyen → genel simge).
