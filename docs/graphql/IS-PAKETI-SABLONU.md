# İş paketi şablonu

Her paketin GraphQL geçişi bu şablonla verilir (plan §6). Koordinatör doldurur, uygulayıcı adım adım ilerler.
Kurallar: [KURALLAR.md](KURALLAR.md). İstisnalar: [ISTISNALAR.md](ISTISNALAR.md). Durum: [DURUM.md](DURUM.md).

```
Paket: <slug>  ·  Model: <Sonnet/Opus>  ·  Dal: gql/<slug>-<n>  ·  Bağımlılık: <paket/kit sürümü>
Örnek uygulama: Flux Tasks (pilot, ESLEME/flx-tasks.md)

0. Başlamadan
   - KURALLAR.md'yi oku. Kit ya da @fluxwp/graphql'de eksik varsa yazma: koordinatöre iş olarak bildir.
   - Başka paketin klasörüne yazma. FluxWP/quiz'e dokunma.

1. Envanter: docs/graphql/ESLEME/<slug>.md (ESLEME/_SABLON.md'den)
   - Tüm REST uçları, AJAX handler'ları, admin sayfaları, MCP/AI araçları, ayarlar.
   - Her satır: → GraphQL adı, cap, girdi/çıktı tipi, ya da İSTİSNA (docs/graphql/durum/<slug>.md "## İstisnalar" satırı).

2. Servis katmanı: includes/services/class-<önek>-<alan>-service.php
   - Mantığı REST/AJAX/MCP callback'lerinden çıkar; callback'ler servise delege etsin.
   - Davranış değişmez: mevcut testler + elle duman testi. REST cevapları bayt bayt aynı.
   - Tipli girdi/çıktı (DTO ya da @phpstan-type).

3. GraphQL: includes/graphql/ (flx_gql_register action'ında)
   - Tipler, enum'lar, connection'lar, sorgular, mutation'lar. Her kök alan cap'li.
   - Node tipi için FLXGQL_Loader alt sınıfı (can_see).
   - Okuyan resolver flx_gql_cache_tag, yazan servis flx_gql_purge.
   - Ayarlar flx_settings_schema ile (elle tip yok).

4. PHP sürümü: Requires PHP: 8.1 ve Requires Plugins: flx-graphql (+ en düşük sürüm notu CHANGELOG'da).
   - Ana dosyada flx-graphql yoksa GraphQL kaydı atlanır, REST çalışmaya devam eder.

5. Sözleşme testleri: tools/tests/wp-graphql/<slug>/
   - Yetkili / yetkisiz / girişsiz; boş / dolu; hata kodları; mutation errors listesi.

6. İstemci
   - plugins/<slug>/graphql/operations/*.graphql (dosya başına bir operasyon, Flx… adı).
   - bun run --filter @fluxwp/graphql generate → persisted-queries.json (commit) + types/operations (commit dışı).
   - Şema parçası: bun run --filter @fluxwp/graphql schema -- plugins/<slug> → plugins/<slug>/graphql/schema.graphql
     (yerelde WP yoksa CI'nın graphql-schema-ci artifact'ından yalnız kendi dosyan). core.graphql commit edilmez.
   - Paket manifesti kaydeder: add_filter( 'flx_gql_persisted_manifests', fn( $m ) => $m + array( '<slug>' => __DIR__ . '/graphql/persisted-queries.json' ) );
   - packages/modules/src/<modül>/ ekranları useFlxQuery/useFlxMutation'a geçer; elle API tipi silinir.
   - Ekran kaydı packages/modules/src/<modül>/screens.ts (index.ts'e dokunulmaz, KURALLAR §12).
   - wp-admin'de wp-embed ile açılır; PHP ekranı bir sürüm "Klasik görünüm" olarak kalır.

7. Kontroller (hepsi yeşil olmadan PR yok)
   - bun run --filter @fluxwp/graphql generate:check, bunx turbo run typecheck, testler
   - bun tools/ts-strict/no-any.ts (paket strict.json'a eklenir)
   - PHP lint 8.1–8.5, PHPStan: services/ ve graphql/ kendiliğinden strict'e girer (phpstan.neon'a satır yok), level max 0 hata;
     özel ayar tools/phpstan/packages/<slug>.php, sabit packages/<slug>.bootstrap.php
   - legacy PHPStan: yeni hata yok
   - tools/tests/wp-graphql/run.sh (pakete özel kurulum <slug>/site.sh, <slug>/wp-args; ortak betik değişmez)

8. i18n: yeni metinler EN kaynak + TR (paketin çeviri akışı).
9. Ekran görüntüleri: açık/koyu × masaüstü/mobil → /mnt/project-files/<slug>/ekranlar-<sürüm>/.
10. Paketin sürümü + kendi CHANGELOG'u, docs/<slug>/GRAPHQL.md (sorgu örnekleri), docs/graphql/durum/<slug>.md.
    @fluxwp/graphql ve @fluxwp/modules sürümü/CHANGELOG'u, DURUM.md, ISTISNALAR.md değişmez (KURALLAR §12).
11. PR: başlıklar, ekranlar, ESLEME bağlantısı. Opus şema incelemesi. Ahmet "al" der, merge.

Durdur ve yükselt: aynı CI hatası 2 kez / ürün kararı / başka paketin koduna dokunmak gerekiyor / kit eksik.
```
