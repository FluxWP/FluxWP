# Flux POS Entegrator · API

Sürüm 0.1.0. Önek `flxpos_`, metin alanı `flx-pos`.

## Kavramlar

- **Sağlayıcı** (`FLXPOS_Providers`): bir banka ya da kuruluş tanımı: sürücü, modeller, para birimleri, uç noktalar, test kartları.
- **Sürücü** (`FLXPOS_Driver_*`): bir protokol (Nestpay, GVP, Posnet, PayFlex, iyzico…). Aynı protokolü kullanan bankalar yalnız adres ve ayarla ayrılır.
- **Hesap** (`FLXPOS_Accounts`, option `flxpos_accounts`): sağlayıcı + anahtarlar (gizli alanlar şifreli) + model + test/canlı + para birimleri + taksit tablosu.
- **İşlem** (`FLXPOS_Txn`, tablo `{prefix}flxpos_txn`): her deneme bir satır; iade/iptal ayrı satır (`kind` refund|void, `parent_id`).

Durumlar: `pending`, `paid`, `failed`, `refunded`, `partial`, `voided`, `review` (bankada başarılı ama tutar ya da sipariş tutmuyor; sipariş "beklemede").

## Akış

1. Ödeme yöntemi (ya da `flxpos_charge()`) işlem açar, kartı kasaya koyar, ödeyeni köprüye gönderir: `/?flxpos=bridge&t=<id>&s=<imza>&v=<kasa>`.
2. Köprü sürücünün `start()` eylemini uygular: bankaya otomatik POST formu, yönlendirme, bankanın HTML'i ya da iFrame.
3. Banka `/?flxpos=return&t=…&s=…` adresine döner; `finish()` imzayı doğrular ya da sunucudan sorar. Sağlayıcı bildirimi: `/?flxpos=notify&a=<hesap>`.
4. İşlem bir kez sonuçlanır; siparişte `payment_complete()` çağrılır, `flxpos_txn_settled` tetiklenir.

## PHP API

```php
$r = flxpos_charge( array(
	'amount'      => 150,
	'currency'    => 'TRY',
	'account'     => '',               // boş: para birimine göre kart hesabı
	'description' => 'Bağış #12',
	'customer'    => array( 'name' => 'Ada Yılmaz', 'email' => 'ada@example.com', 'phone' => '5…', 'city' => 'İstanbul' ),
	'items'       => array( array( 'id' => '12', 'name' => 'Fidan', 'price' => 150, 'qty' => 1 ) ),
	'back'        => home_url( '/tesekkurler/' ), // sonuç: ?flxpos_status=paid&flxpos_ref=…
	'card'        => null,             // kartı sitede alan hesaplarda: pan, name, month, year, cvv
	'meta'        => array( 'donation' => 12 ),
) );
if ( ! is_wp_error( $r ) ) {
	wp_safe_redirect( $r['redirect'] );
	exit;
}

flxpos_refund( $txn_id, 50.0 );   // null = kalan tutarın tamamı; aynı gün tam tutarda önce iptal
flxpos_txn( $ref );               // işlem satırı
```

`back` adresinde `flxpos_status` yalnız bilgi içindir; sonucu `flxpos_txn( $_GET['flxpos_ref'] )['status']` ile okuyun.

## Kancalar

| Kanca | Tür | Açıklama |
|---|---|---|
| `flxpos_txn_settled` | action `( $txn, $status, $result )` | İşlem sonuçlandı (bir kez). |
| `flxpos_refunded` | action `( $txn, $amount, $kind )` | İade/iptal bankada onaylandı. |
| `flxpos_account_saved` | action `( $id, $account )` | Hesap kaydedildi. |
| `flxpos_providers` | filter | Sağlayıcı ekle/değiştir (sürücü sınıfı `FLXPOS_Driver_<Ad>`, dosya `drivers/class-flxpos-driver-<ad>.php`). |
| `flxpos_endpoint` | filter `( $url, $key, $provider, $test )` | Uç nokta adresi. |
| `flxpos_installments` | filter `( $options, $account, $amount, $currency, $bin )` | Taksit seçenekleri. |
| `flxpos_payment` | filter `( FLXPOS_Payment, $txn )` | Sürücüye giden ödeme bilgisi. |
| `flxpos_leave_url` | filter `( $url, $txn )` | Akış sonunda gidilecek adres. |
| `flxpos_virtual_accounts` | filter | Panelde kayıtlı olmayan hesaplar (ör. EsnekPOS resmi modülü). Kart yöntemine girmez. |
| `flxpos_gateway_adapters` | filter | Başka bir ödeme eklentisinin yetenekleri, notu, logosu (Ödeme yöntemleri tablosu). |
| `flxpos_gateway_caps` | filter `( $caps, $gateway )` | Tablodaki yetenekler. Durum: yes, no, page, flux, unknown. |
| `flxpos_non_card_gateways` | filter | Tek kart kuralının dokunmadığı yöntemler (bacs, cheque, cod, flxb_havale, flxcm_bank, cüzdanlarımız). |
| `flxpos_one_card_skip` | filter `( $skip, $gateways )` | Tek kart kuralını bu istekte uygulama (Flux Donate aylık bağış). |
| `flxpos_card_icon` | filter | Kart yönteminin simgesi. |
| `flxpos_http_pre` | filter | Test için sahte sağlayıcı yanıtı. |
| `flxpos_sslverify` | filter | Yalnız yerel test. |
| `flxpos_display` | filter `( $mode, $account, $txn )` | 3D / ödeme sayfası gösterimi: `redirect`, `modal`, `inline`. |
| `flxpos_bank_methods` | filter | Havale eşleşmesine giren ödeme yöntemleri (varsayılan `bacs`; Flux Donate `flxb_havale` ekler). |
| `flxpos_bank_codes` | filter `( $codes, $order )` | Siparişin açıklamada aranacak referans kodları (Flux Donate `_flxb_code`). |
| `flxpos_bank_candidates` | filter `( $list, $row )` | Hareketin aday siparişleri (id, score, exact, code, why). |
| `flxpos_bank_confirm` | filter `( $done, $order, $row, $note )` | Eşleşme onayını devral; true dönerse Flux POS `payment_complete()` çağırmaz. |
| `flxpos_bank_layouts` | filter | Ekstre düzeni ekle: `id => array( name, sig, cols )`. |
| `flxpos_bank_sources` | filter | Canlı hareket kaynakları (`FLXPOS_Bank_Source` nesneleri). |
| `flxpos_bank_imported` | action `( $result, $source )` | Ekstre ya da canlı kaynak yazıldı. |
| `flxpos_bank_matched` | action `( $row, $order )` | Hareket siparişle eşleşti. |

Bağdaştırıcı örneği:

```php
add_filter( 'flxpos_gateway_adapters', function ( $a ) {
	$a['my_pos'] = array(
		'label' => 'My POS',
		'logo'  => 'https://…/logo.svg', // FluxWP tema ödeme logoları
		'note'  => 'Test modu',
		'caps'  => array( 'installments' => array( 'page', 'Sağlayıcının sayfasında' ) ),
	);
	return $a;
} );
```

## Sürücü yazmak

`FLXPOS_Driver` sınıfını genişletin:

- `start( FLXPOS_Payment $p )` → `array( 'form' => array( 'url' => …, 'fields' => … ) )` | `array( 'redirect' => url )` | `array( 'html' => … )` | `array( 'iframe' => array( 'url' => … ) )` | `array( 'result' => sonuç )`; isteğe bağlı `data` işleme yazılır.
- `finish( $txn, $req )` → sonuç: `status` (paid|failed|pending|review), `message`, `bank_ref`, `auth_code`, `amount`, `installment`, `card`, `data`. `$req` = post, get, raw, card.
- İsteğe bağlı: `notify( $req )`, `refund( $txn, $amount )`, `cancel( $txn )`, `status( $txn )`, `installments( $bin, $amount, $currency )`, `test_connection()`.
- Dönüş verisine güvenmeyin: imza doğrulayın ya da sunucudan sorun; tutarı ve referansı karşılaştırın.

## Gösterim

Hesap başına (boşsa Ayarlar'daki genel değer) bankanın 3D sayfası ya da kuruluşun ödeme sayfası:

- `redirect`: köprü tam sayfa (varsayılan).
- `modal`: ödeme sayfasından ayrılmadan açılır pencerede çerçeve. Ödeme sonucu `flxpos_frame` ve `flxpos_cancel` döner;
  klasik ödeme `checkout_place_order_success`, blok ödeme `onCheckoutSuccess` ile pencere açılır. JS yoksa `redirect`
  site içi sayfaya gider.
- `inline`: sitenin üst ve alt bölümüyle bir sayfada çerçeve (`?flxpos=bridge…&w=1`).

Çerçevedeki köprü ve dönüş adresi `f=1` taşır; akış bitince sonuç sayfası üst pencerede açılır. Çerçeveye izin
vermeyen sağlayıcıda o hesabı `redirect` yapın. `flxpos_charge()` aynı alanları döner.

## Havale / EFT hareketleri

Ekstre yükleme (Havale / EFT sekmesi) ya da canlı kaynak → `wp_flxpos_bank` → arka planda eşleştirme.

- Okuyucu: `.xls` (BIFF8), `.xlsx`, `.csv` (`;` `,` sekme; UTF-8 ya da Windows-1254), HTML tablo. İlk sayfa.
- Düzenler: Ziraat, Yapı Kredi, Garanti BBVA, İş Bankası, Türkiye Finans, VakıfBank (sütunlar banka-ekstre projesinden,
  MIT); diğerlerinde başlıkta Tarih + Tutar (ya da Borç/Alacak) + Açıklama aranır.
- Tekrar engeli: her hareketin parmak izi (banka, tarih, saat, tutar, bakiye, referans, açıklama) tekildir; aynı ya da
  çakışan ekstre yeniden yüklenince kayıtlılar atlanır.
- Eşleştirme: yalnız giren tutar; `on-hold`/`pending`, `flxpos_bank_methods` yöntemli, son 45 günün siparişleri.
  Puan: tutar birebir 40, kod (sipariş no ya da `flxpos_bank_codes`) 50, ad soyad 30 (yalnız soyad 15), tarih 5.
  40+ öneri olur. `bank_auto` açıksa kod + tutar tutan, ikinci adaydan 30 puan önde olanlar kendiliğinden onaylanır.
- Durumlar: `new` (sırada), `nomatch`, `suggested`, `matched`, `ignored`, `out` (çıkan). Eşleşmeyenler her gün yeniden
  denenir. İş Action Scheduler ile (`flxpos_bank_match`), yoksa WP-Cron; panelde "Şimdi eşleştir".

PHP:

```php
FLXPOS_Bank::import_file( $path, $name, $bank = '', $account = '' ); // { bank, added, skipped, total, out }
FLXPOS_Bank::import( $items, 'kaynak', 'banka' );  // items: date (Y-m-d), time, amount (±), balance, currency, desc, ref
FLXPOS_Bank::confirm( $row_id, $order_id, get_current_user_id() );
```

Canlı kaynak:

```php
class My_Bank_Source extends FLXPOS_Bank_Source {
	public $id = 'mybank';
	public $name = 'Banka API';
	public function fetch( $since, array $creds ) { return array( /* items */ ); }
}
add_filter( 'flxpos_bank_sources', fn( $l ) => array_merge( $l, array( new My_Bank_Source() ) ) );
```

Ayarlar `flxpos_bank_sources` seçeneğinde (`enabled`, `account`, kaynak alanları); saatte bir son 7 gün çekilir.

## REST

- `GET flxpos/v1/installments?account=&amount=&bin=` (herkese açık; yalnız etkin hesap, TRY) → `{ options: [ { n, total, monthly, rate, label } ] }`.
- `POST flxpos/v1/test/<hesap>` (manage_woocommerce) → `{ ok, message }`.

## Flux Donate

Flux POS etkinse Flux Donate'in yöntem tablosu, tek kart kuralı ve EsnekPOS bağdaştırıcısı çalışmaz, Flux POS'unkiler çalışır. Aylık bağış sepeti `flxpos_one_card_skip` ile Flux Donate iyzico'da kalır.

Havale eşleşmesi: Flux Donate `flxb_havale` yöntemini ve bağışın referans kodunu (`_flxb_code`) ekler; eşleşme onayı
bağışın kendi onayından geçer (`FLXB_Approval::do_approve`: sertifika, bildirimler).
