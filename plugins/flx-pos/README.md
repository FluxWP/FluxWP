# Flux POS Entegrator

One virtual POS plugin for WooCommerce: Turkish banks and payment institutions in one place, with our own card
form (classic and block checkout), 3D Secure, test mode, installments, refunds from the site and transaction logs.

- **Banks:** Nestpay/EST (Akbank, İş Bankası, Halkbank, TEB, Ziraat, ING, Şekerbank, QNB, Anadolubank, Türkiye Finans,
  Alternatif Bank), Garanti BBVA + Garanti Pay, Akbank new POS, Yapı Kredi (Posnet), Albaraka, Vakıfbank and Ziraat
  Katılım (PayFlex), QNB (PayFor), Denizbank (InterPOS), Kuveyt Türk, Vakıf Katılım, İş Bankası GiroGate.
- **Payment institutions:** iyzico, PayTR, Param, Craftgate, Moka United, Tosla, Tami, Paynkolay, EsnekPOS, Payten
  (Paratika, VakıfPayS, ZiraatPay), Sipay, QNBpay, Vepara, Paybull, Halk Ödeme, Shopier, Mollie, Papara.
- 3D Pay, 3D Model, hosted payment page, iFrame and wallet models. The bank's return is never trusted: the hash is
  verified or the payment is queried from the server, amount and reference must match.
- One "Pay by card" method picks the account by currency; wallets are separate methods. Installments by card BIN.
- The order's Refund button refunds or (same day, full amount) cancels at the provider.
- **Payment methods** tab: what every WooCommerce payment plugin on the site can do, one card method rule, checkout
  block fallback for redirect plugins, theme payment logos. EsnekPOS official module is run safely through Flux POS.
- Keys are stored encrypted; card data never touches the database.
- PHP API for other plugins: `flxpos_charge()`, `flxpos_refund()`, `flxpos_txn_settled`.
  See [docs/flx-pos/API.md](../../docs/flx-pos/API.md).

> Drivers are written from the providers' published protocol documents and have not all been run against live
> sandboxes yet. Test each account with a test card before going live.

Türkçe: [README.tr.md](README.tr.md)
