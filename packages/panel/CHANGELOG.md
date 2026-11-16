# @fluxwp/panel

## 0.3.6
- Panel açılışında FluxApp GraphQL taşıyıcısı kurulur (`installFlxAppGraphQL`); web ve masaüstü uygulaması bootstrap/me/sync/config/manifest çağrılarını GraphQL ile yapar, olmayan sitede REST'e döner.


## 0.3.5
- `react-i18next` and `i18next` are deduplicated, so module screens use the panel language.

## 0.3.4
- The open panel reads `/me` and the counters from the shared runtime queries, so the legacy hooks no longer start a second poll.

## 0.3.3
- Support ten shared locales, English translation keys and Arabic direction for WordPress embeds and shared screens.

## 0.3.2

- Her hesapta "Yeniden giriş" durur ve o sitenin adresini açar. Oturum düşünce hesabı silmeden giriş yapılır. Keşif, site simgesi yoksa marka logosunu gösterir.

## 0.3.1

- Site teması `--flux-font-heading` değişkenini de yazar; başlıklar Figtree ya da sitenin başlık yazı tipini kullanır.
