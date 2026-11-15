# <Paket adı> (<slug>): REST → GraphQL eşlemesi

Paket sürümü: <x.y.z> · Önek: `<önek>_` · Sahip thread: <bağlantı> · Durum: taslak / uygulanıyor / bitti

## Özet

- REST uçları: <n> · AJAX: <n> · Admin sayfası: <n> · MCP/AI aracı: <n>
- İstisna: <yok / ISTISNALAR.md satırları>
- Servisler: `includes/services/…`
- Node tipleri: <FlxX, FlxY>

## REST uçları

| Yöntem | Yol | cap | → GraphQL | Girdi | Çıktı | Not |
|---|---|---|---|---|---|---|
| GET | `/<ns>/v1/items` | `<cap>` | `flxItems` (sorgu) | `first, after, filtre` | `FlxItemConnection!` | |
| POST | `/<ns>/v1/items/<id>` | `<cap>` | `flxItemUpdate` | `FlxItemUpdateInput` | `{ item, errors }` | |
| POST | `/<ns>/v1/hook` | — | İSTİSNA | | | ISTISNALAR.md: <neden> |

## AJAX

| Action | cap / nonce | → GraphQL | Not |
|---|---|---|---|

## Admin sayfaları

| Sayfa (`page=`) | Ne gösterir | Kullandığı sorgular | İstemci ekranı |
|---|---|---|---|

## MCP / AI araçları

| Araç | Servis metodu | GraphQL karşılığı |
|---|---|---|

## Ayarlar (`flx_settings_schema`)

| Grup | Alan | Tip | GraphQL'de | Not |
|---|---|---|---|---|

## Tipler

| Tip | Alanlar (kısa) | Nullable alanlar ve nedeni | Loader |
|---|---|---|---|

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|

## Açık sorular

- 
