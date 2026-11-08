# FluxWP Panel preview metadata

The Packages screen opens a native, read-only dialog from a package name or icon. All descriptions, icons, gallery images and publisher links come from the configured updater catalog. There are no bundled client introductions, raw repository URLs, or inferred publisher links. An older server without metadata produces an empty introduction with the existing localized unavailable state.

`POST /v1/check` includes these authorized package fields:

```json
{
  "desc": { "en": "Short introduction", "pt-br": "Breve apresentação" },
  "website": { "en": "https://publisher.example/product/" },
  "gallery": { "en": ["https://updates.example/v1/media/package/image.webp?expires=...&site=...&sig=..."] },
  "shots": { "en": "https://updates.example/v1/media/package/image.webp?expires=...&site=...&sig=..." }
}
```

`shots` remains a first-image alias for older clients. Locale keys use lowercase BCP 47 forms, including `pt-br`. WordPress administrator locale determines exact locale, base language, English, then Turkish fallback; Flux Lang is not required. Up to twelve images are accepted. Gallery transport accepts HTTPS images on the configured updater host only; script URLs and embedded credentials are rejected. Publisher links must be HTTPS and are rendered only if explicitly supplied.

Images are loaded only when the dialog opens, without a referrer. Image failure leaves the introduction and publisher link available. Download keys and GitHub credentials never enter the dialog. Updater media URLs expire after seven days; disabling a site, removing its package permission or removing an image revokes access at the server. Refresh the package list to obtain new URLs.

Manage descriptions, publisher links, SVG/pastel icon references and galleries in `catalog/packages.json` in the owning GitHub repository. The updater reads that manifest from the default branch, mirrors verified Git blobs lazily and distributes every URL from its own host. There is no updater-side content editor or upload/import step. The authenticated updater panel exposes synchronization status and a refresh action. Private repository credentials and paths are never sent to sites.

## Developer workflow

Use `tools/readme-media/setup-docker.py`, `capture-package.py` and `export-updater-previews.py`; see the media guide. The exporter writes package-owned `docs/previews/` WebP derivatives and the repository's `catalog/packages.json`. Icon references point to the package's tracked SVG assets. Commit both catalog and media to the owning repository; updater synchronization handles distribution. `--private-root` writes a separate catalog only in that private repository. `--translate` fills missing short descriptions during development, never from WordPress.

The existing `flxu_package_preview` filter remains an explicit integration override with `(array $preview, string $slug, array $catalog_package)`. Its four display fields are sanitized: `name`, `description` (420 characters), `images` (twelve HTTPS URLs), `website` (HTTPS or empty). Other fields are discarded. No runtime fallback is installed through this filter.

## Maintainers

Ahmet Yılmaz ([@sharkpaw](https://github.com/sharkpaw)).
