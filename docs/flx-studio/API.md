# Flux Studio API (`flx-studio/v1`)

Giriş: wp-admin çerezi + `X-WP-Nonce`, ya da Flux App belirteci (`Authorization: Bearer flxat_…/flxak_…`; Hostinger için `X-FluxWP-Token`). Yetki `flxst_use` (ya da `flxst_manage`). Yazma işlemleri POST. Cevaplar önbelleğe alınmaz. Dil için `?_locale=user`.

Flux App `/flxapp/v1/me` cevabında `modules.studio` = `{title, menu:'flx-studio', api:<bu kök>, icon:'image', version}`; uygulama bu modülü yerel ekranla açar.

## Kayıt (item)
`{id, kind:image|video, state:queued|running|done|failed|cancelled, job, batch, parent, folder, title, prompt, model, provider, params, url, thumb, mime, width, height, bytes, cost, error, favorite, attachment, owner, owner_name, created, updated}`
`params`: aspect, resolution, quality, n, refs[] (özet: type, id, role, thumb, name), edit, enhanced, draft, idea, duration, audio, mode, progress (sürerken 0-1).

## Uçlar
| Yöntem | Yol | Açıklama |
|---|---|---|
| GET | `/boot` | user, ai {media, text, setup}, models (Flux AI `flxai_media_providers`), settings, folders, people (photos ile), refs, templates, budget |
| GET | `/items` | `folder` (id, 0 = klasörsüz), `kind`, `fav=1`, `q`, `owner=all` (manage), `before` (id), `limit` ≤200 |
| GET/POST | `/items/{id}` | POST: `favorite`, `title`, `folder` |
| POST | `/items/delete` | `{ids:[]}`; süren iş iptal edilir |
| POST | `/items/move` | `{ids:[], folder}` |
| POST | `/items/{id}/library` | Medya kitaplığına kopya (`upload_files`) → `{attachment, edit_url, url}` |
| POST | `/generate` | aşağıda → `{items:[]}` |
| GET | `/poll?jobs=flxm_a,flxm_b` | en çok 8 iş; Flux AI'ye sorar, kayıtları günceller → `{items:[]}` |
| POST | `/jobs/{job}/cancel` | → `{items:[]}` |
| POST | `/enhance` | `{prompt, mode:image|video|edit, refs, model_label}` → `{prompt}` |
| POST | `/brainstorm` | `{brief, count 1-8, style:simple|youtube, refs, mode}` → `{ideas:[{title,prompt}], batch}` |
| POST | `/folders`, `/folders/{id}`, `/folders/{id}/delete` | `name`, `parent`, `shared`; silinen klasörün içeriği üst klasöre çıkar |
| POST | `/people`, `/people/{id}`, `/people/{id}/delete` | `name` |
| GET/POST | `/refs` | POST multipart `file` ya da JSON `data` (data URL) + `name`, `role`, `person`, `saved`, `kind` (video/audio referans için) |
| POST | `/refs/{id}`, `/refs/{id}/delete` | `name`, `role`, `saved` |
| POST | `/templates`, `/templates/{id}/delete` | `name`, `prompt`, `params`, `shared` |
| POST | `/settings` | manage: image_model, video_model, text_model, max_n 1-8, max_batch 1-40, shared_people |
| GET | `/stats?days=7..365` | manage: pano. totals {image, video, failed, cancelled, pending, users, bytes}, spend {total, media, text, est, from: flx-ai\|flx-studio}, daily [{date, image, video, failed, cost}], models, providers, users (en çok 12), errors (son 6), budget, log_url. Harcama Flux AI kayıtlarından (`source = flx-studio`); okunamazsa Stüdyo tutarları. |

### `/generate`
```json
{ "kind": "image", "model": "gemini-nano-banana-2.1", "provider": "auto", "prompt": "…",
  "n": 2, "aspect": "4:5", "resolution": "2K", "quality": "",
  "refs": [{"type": "item|ref|person", "id": 12, "role": "style"}],
  "folder": 3, "batch": "", "title": "", "enhanced": false, "draft": "",
  "mask": "data:image/png;base64,…", "parent": 41,
  "duration": 5, "audio": true, "video_refs": [ref_id], "audio_refs": [ref_id] }
```
- Her çıktı için önce bir kayıt açılır (galeride yer tutucu), iş Flux AI'ye gider (`flxai_media_submit`, `source: flx-studio`).
- Alan düzenleme: `mask` PNG (saydam = değişecek alan) + `parent` (kaynak kayıt, `refs[0]` olarak gider). Model `mask: true` olmalı.
- Hata: kayıtlar `failed` olur, cevapta `data.items`. `maybe_charged` ise mesajda uyarı vardır; otomatik tekrar gönderme.
- Kişi referansı en çok 4 fotoğraf; toplam referans modelin `max_refs` sınırını aşamaz.

## Filtre ve olaylar
- `flxai_media_done` (Flux AI) dinlenir: iş Flux AI tarafında biterse kayıtlar güncellenir.
- Kaydolduğu yerler: `flxapp_modules`, `flxa_apps`, `flxa_app_screens`, `flxai_systems`, `flx_system_checks`, `flx_role_caps_supported`, `flxa_cap_labels`.
