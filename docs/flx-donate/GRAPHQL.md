# Flux Donate GraphQL (a)

Uç: `/graphql` (Flux GraphQL 0.3). Bu dilim bağış sayfasının herkese açık okuması, sepete ekleme, Bağışlarım ve bağışçı sorgulamasıdır. Ödeme Store API'de kalır (`POST /wc/store/v1/checkout`). Kart alanı yoktur.

Flux GraphQL yoksa şema kaydı atlanır; REST eskisi gibi çalışır.

## Herkese açık

`'public'` gerekçesi: bağış sayfası hesapsız açılır ve bugünkü REST uçları da açıktır.

| Alan | Neden public |
|---|---|
| `flxDonationTypes`, `flxDonationType` | Sayfa türün başlığını, açıklamasını, tutarlarını, görselini ve seçeneklerini gösterir. Arşiv, form kimliği ve tür metası yok |
| `flxDonationCartAdd` | Aynı sayfa hesapsız sepete ekler. Sepet ziyaretçinin Store API oturumudur |
| `flxDonationLookupStart`, `flxDonationLookupVerify`, `flxDonationLookupEnd` | Sorgulama ziyaretçi içindir. Start, bağış olsa da olmasa da aynı cevabı verir |
| `flxDonationBankAccount` | Sayfa bu IBAN'ı gösterir. Sorgu `flxb_bank_account()` çağırır: havale sınıfı yüklüyse Donate'in kendi havale ayarı, değilse WooCommerce BACS |

`flxMyDonations` public değildir. Yalnız giriş yapmış hesabın kendi bağışları, ya da `flxb_lk` çerezini doğrulamış oturum. Cevap `no-store`.

## Okuma

```graphql
query {
  flxDonationTypes(first: 20) {
    nodes { databaseId slug title state options { key label price { amount currency } } minAmount { amount currency } }
  }
  flxDonationType(slug: "fidan") { title state requiresProxy }
  flxDonationBankAccount { holder iban bank }
}
```

Giriş ya da doğrulanmış sorgulama oturumu:

```graphql
query {
  flxMyDonations(first: 20) {
    nodes { databaseId title status date { iso timestamp } amount { amount currency } }
  }
}
```

## Yazma

| Mutation | Girdi | Not |
|---|---|---|
| `flxDonationCartAdd` | `typeId`, `option?`, `units?`, `amount?`, `currency?`, `names?`, `contacts?`, `feeCover?`, `proxyConsent?` | Klasik form gibi: önce `FLXB_WC_Form::contact_missing` (sertifikada WhatsApp ya da e-posta, aynı ileti), sonra `build_line` ve `FLXGQL_Cart::add_item`; ardından `remember_contact` ve `FLXB_WC_Fee::remember`. `contacts` her adın iletişimi (`{ phone?, email? }`, aynı sıra). Para birimi seçilemezse `flxb_currency`. Birkaç ad bir satır. Çıkış `{ cart, itemKey, errors }` |
| `flxDonationLookupStart` | `login` | Aynı cevap; oran sınırı |
| `flxDonationLookupVerify` | `login`, `code` | Çerez `flxb_lk`. Yanlış kod ile bilinmeyen adres aynı hatayı verir |
| `flxDonationLookupEnd` | — | Çerezi siler |

Hatalı girdi payload `errors` içindedir. Oran aşımı `RATE_LIMITED`.

Önbellek: `flxDonationTypes` ve `flxDonationType` `flx-donate:types` etiketini taşır; tür kaydı, tür silme ve bağış kaydı/durumu değişince temizlenir. `flxMyDonations` reddi dahil asla saklanmaz (`cap` içinde `flx_gql_no_store`). Sorgulama kapalıysa (`flxb_lookup.on`) oturumla okuma ve `Verify` kapanır.

İşlem belgeleri `plugins/flx-donate/graphql/operations/`. Şema parçası `plugins/flx-donate/graphql/schema.graphql`.
