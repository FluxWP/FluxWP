# Content-field safety (1.5.6)

`flxk_content_fields_supported()` returns true when the engine supports this contract. A preset depending on it must gate registration on this capability; older versions only check plugin presence, not version constraints.

## Identity and legacy state
Core page/post importers opt in with `post_fields: true`. Existing content requires both `_flxk_key` identity and preset body ownership. A matching slug (even identical text) is not enough. New state records `content_ids` to detect remapped keys. Duplicate keys remain manual even under force; repair ambiguity first.

The body `read()` shape/hash is unchanged. Legacy untouched bodies continue to upgrade; edited bodies remain manual. Legacy metadata/terms have no trustworthy recorded baseline: differing populated fields are preserved and reported as manual, not assumed to be stock. Empty fields can be added and identical fields can be adopted once the content identity is trusted. This may leave an older site's editorial migration incomplete until reviewed.

## Field targets and explicit review
Declared meta and taxonomy fields are separate targets:

- `content-field:page:<key>:meta:<rawurlencoded-meta-key>`
- `content-field:post:<key>:terms:<rawurlencoded-taxonomy>`

Only declared fields are read/hashed/written. No scan of unrelated SEO/plugin metadata is performed. Core body writes no longer silently write their embedded meta/terms; the planner writes them separately. Field-only `only` and target-specific `force` support reviewed corrections. Adopting an unrelated same-slug record requires explicitly forcing its body target; its differing fields require their own explicit force targets. A blanket force remains a privileged existing API, not the automatic default.

Meta snapshots retain all rows, their order, serialized values and absence (`[]` versus `['']`). Rollback restores field snapshots independently, preserving later edits. Newly added fields are removed on rollback; previous `_flxk_key` identity is restored after field rollback. Taxonomy snapshots retain term IDs; deleted taxonomy objects are not recreated. Body/field identity and values are rechecked before writing, but this is not a database-wide transaction against arbitrary concurrent third-party writes.

Old runs created before 1.5.6 never recorded old SEO and cannot acquire a retroactive SEO backup. Their existing rollback behavior is unchanged. Legacy body ownership without a stored record ID can only be checked against its key/hash, not an unknowable historic ID.

## Partial migrations and retry
Completion is based on a post-execution plan plus errors/unavailable importers, not merely having attempted the migration. Unresolved work stays out of `state.migrations` and remains in `flxk_pending_migrations()`. `state.migration_blocked` records its source signature, latest run and remaining count. Automatic handling pauses an unchanged blocked source to avoid repeated run logs; independent later migrations may continue. Explicit preview/apply (`flxk_plan`, `flxk_apply_migration`) retries immediately. A changed migration source signature also permits an automatic retry, though published migration definitions should remain immutable.

## Reproduce on a disposable site only

```sh
FLX_TEST_SITE=1 php plugins/flx-setup/tests/content-safety.php /path/to/wordpress
FLX_TEST_SITE=1 php plugins/flx-setup/tests/preset-selection.php /path/to/wordpress
```

The selection suite expects Flux Setup loaded. The content-safety suite can load its engine modules after WordPress. Both require a disposable install, not a production site. Integration coverage includes fresh installs, metadata-only updates, legacy hashes, manual body/SEO edits, same-slug adoption, absent/empty/multivalue rollback, taxonomy defaults/edits, save failures, post-save hooks, duplicate keys, ID remapping, partial retries and unchanged-auto-run suppression.
