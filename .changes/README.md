# Change notes

`@fluxwp/modules` and `@fluxwp/graphql` versions are **not bumped in PRs** (parallel PRs
would conflict on `package.json` and `CHANGELOG.md`). A PR adds one note file here instead;
the "JS package versions" workflow (`.github/workflows/js-version.yml`) computes the new
versions at release time, writes the CHANGELOGs, deletes the notes and tags the release.

## Note format

`.changes/<name>.md`, `<name>` = package slug or topic (`flx-backup.md`):

```md
---
"@fluxwp/modules": patch
"@fluxwp/graphql": minor
---
Backup: new `flxy-backup` screen ... (this text becomes the CHANGELOG bullet; several lines are fine)
```

- Keys: `@fluxwp/modules`, `@fluxwp/graphql`; values: `patch` or `minor` (`major` too).
- List only the packages the PR actually changes. The text is added to each listed package.
- The highest bump of all pending notes wins; one version per release per package.
- Never edit `version` or `CHANGELOG.md` of these two packages in a PR (CI fails).
  Package CHANGELOGs under `plugins/*` are unaffected.

## Release

Actions → "JS package versions" → Run workflow. It runs `node tools/release/version-packages.mjs`,
commits `@fluxwp/modules x.y.z, @fluxwp/graphql x.y.z` to main and creates the
`modules-v<version>` / `graphql-v<version>` tags and GitHub releases.

Local preview: `node tools/release/version-packages.mjs --dry-run`.
