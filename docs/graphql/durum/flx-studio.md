# flx-studio 0.5.0

- Faz: 1
- Durum: 👀
- PR: #324

`gql/flx-studio-1`: servis katmanı, 26 kayıtlı işlem (sorgu + mutation), sözleşme testi 90/90, React ekranı (`studio`) klasikle eşit, wp-admin varsayılanı; klasik bağlantıyla. Eşleme `ESLEME/flx-studio.md`, API `docs/flx-studio/GRAPHQL.md`

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Flux Studio mobil manifest (`flx-studio/v1/boot` mobil alanları) ve referans dosya yükleme `/flx/v1/upload` | flx-studio | FluxApp eski sürümleri; yükleme çok parçalı dosya akışı, GraphQL yalnız `uploadId` alır | Çalışma alanı `flxStudioWorkspace`, yükleme sonrası `flxStudioRef*` |
