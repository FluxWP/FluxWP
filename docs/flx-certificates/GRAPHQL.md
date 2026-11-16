# Flux PDF & Certificates GraphQL API

Uç: `/graphql` (Flux GraphQL). Okuma ve oluşturma `flxs_use` veya `flxs_manage`. Silme `flxs_manage`. `flxs_own_ref` veya ayar `staff_own_only` açıksa liste, sayım ve tek kayıt yalnız o kullanıcının kayıtlarını döner; başkasının kaydı `null` olur (404 sızdırmaz).

Herkese açık: `flxCertificateVerify`, `flxCertificateLookup` (`cap: public`).

İşlemler `plugins/flx-certificates/graphql/operations/records.graphql` (20 kayıtlı işlem). Tipler `@fluxwp/graphql`.

## Okuma

```graphql
query {
  flxCertificates(first: 20, filter: { status: ACTIVE, eventId: 0, referrerId: 0 }) {
    nodes { databaseId number holderName status pdfUrl }
    totalCount
  }
  flxCertificate(id: 1) { databaseId code pageUrl }
  flxCertificateVerify(code: "abc") { status holderName pdfUrl imageUrl }
}
```

`filter`: `search`, `status` (`ACTIVE` / `REVOKED`), `templateId`, `eventId` (0 = etkinliksiz), `referrerId` (0 = referanssız), `createdBy` (kapsamlı kullanıcıda yok sayılır), `personId`.

Diğer sorgular: `flxCertificateRecordsMeta`, `flxCertificateCreateConfig`, `flxCertificatePeople`, `flxCertificateDirectory`, `flxCertificatePerson`, `flxCertificatePersonMessages`.

`flxCertificateVerify` sayfanın gösterdiği alanları döner. Telefon, e-posta, kişi no, iç no, not, referans ve oluşturan yoktur. Bilinmeyen kod `null`. İptal edilmiş sertifikanın `pdfUrl` ve `imageUrl` değeri `null`. Sorgu sayaç artırmaz ve eksik dosyayı çizmez.

Kişi alanı `FlxPerson`. CRM yokken `FlxCertificatePerson` bu arayüzü uygular. Not CRM’in notu değil, bu paketin notudur.

## Yazma

| Mutation | Yetki | Not |
|---|---|---|
| `flxCertificateCreate` / `Update` | `flxs_use` | Geçmiş tarih (`issued`) yalnız yönetici |
| `flxCertificateSetStatus` / `SetReferrer` | `flxs_use` | İptal PDF ve görsel adresini boşaltır; dosya diskte kalır |
| `flxCertificateBulk` | `flxs_use` | Toplu gönderim yetkisi varsa klasik toplu sayfanın adresi döner |
| `flxCertificateDelete` | `flxs_manage` | |
| `flxCertificateSend` / `SendTo` | `flxs_use` | `SendTo`: `provider`, `save` |
| `flxCertificateAttachFiles` | `flxs_use` | Kit yüklemesi `flx-certificates/file` |
| `flxCertificatePersonSave` / `PersonDelete` | use / `flxs_manage` | |
| `flxCertificateLookup` | public | Bal küpü `website`. Sertifika dönmez. IP sınırı `RATE_LIMITED` |

`INVALID_INPUT` ve `CONFLICT` payload `errors` içindedir. `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `RATE_LIMITED` üst hata olur.

Bu bölümde olmayanlar (sonraki): kuyruk adımı, şablon düzenleyici, etkinlik CRUD, ayarlar, pano, günlük, e-kart, yazı tipleri.
