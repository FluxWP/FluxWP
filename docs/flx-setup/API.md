# FluxWP Panel preview metadata

The Packages screen opens a read-only native dialog from a package name or icon. Installation/update controls keep their existing behavior. Short descriptions and up to three example images are selected by the WordPress administrator locale; missing languages fall back to English. No Flux Lang dependency, AI request, download token or package download URL is needed to open the preview.

The catalog's `desc` and `shots` fields take precedence. Bundled public-package metadata supplies concise README introductions and existing documentation image URLs if the catalog has no preview. Unknown/private packages do not inherit public repository URLs or marketing links. Images are requested by the browser only after the user opens a preview, without a referrer. Missing/failed images leave the description and publisher link usable.

## `flxu_package_preview`

Filter arguments: `(array $preview, string $slug, array $catalog_package)`.

Return `name` (plain text), `description` (plain text, limited to 420 characters), `images` (up to three HTTPS image URLs) and `website` (HTTPS publisher page URL or an empty string). Other keys are discarded. Script/HTTP URLs and URLs with embedded credentials are rejected. A private package can explicitly supply its own publisher link through this filter; no public link is inferred for it.

```php
add_filter( 'flxu_package_preview', function ( $preview, $slug ) {
    if ( 'my-package' === $slug ) {
        $preview['description'] = __( 'A short overview of this package.', 'my-package' );
        $preview['website'] = 'https://example.com/products/my-package/';
    }
    return $preview;
}, 10, 2 );
```

## Refresh public introductions and example images

```sh
python3 tools/readme-media/export-panel-previews.py
python3 tools/readme-media/export-panel-previews.py --translate
```

The first command is offline and retains translations whose English source did not change. The explicit `--translate` option uses the developer translation service to fill missing descriptions; it is never run by WordPress. Source files are the public package README files, tracked documentation images and existing media capture configurations. The generator excludes the internal migration package and never reads private preset files. Refresh native gettext packs separately with the translation workflow in `tools/site-translations/LANGUAGES.md`.

## Maintainers

Ahmet Yılmaz ([@sharkpaw](https://github.com/sharkpaw)).
