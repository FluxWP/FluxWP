# Site translation utility

[Turkish](README.tr.md)

Offline validation and an optional batch machine-translation tool for the `fluxwp-site-translations/v1` contract. Adapted from the script supplied by Ahmet Yılmaz. This is a developer CLI, not a WordPress plugin or a runtime AI connection.

Python 3.10+; standard library only. Paths are explicit so moving this folder does not change which files it reads. No network request or cache write occurs in validation mode:

```sh
python3 tools/site-translations/translate_all.py --workspace /path/to/catalogs
python3 tools/site-translations/translate_all.py --workspace /path/to/catalogs ar ja zh
python3 -m unittest discover -s tools/site-translations -p 'test_*.py' -v
```

The workspace must contain `en.json` and the selected target JSON files. Validation checks the source revision, key tree, immutable values, intentionally empty values, placeholders, URLs, markup and code. It does not judge translation accuracy, terminology, SEO quality or legal suitability. Product names may legitimately remain English.

For an explicit new machine-translation run:

```sh
python3 tools/site-translations/translate_all.py --workspace /path/to/catalogs --translate ar
```

This sends English source text to Google's public translation endpoints, writes a local cache and overwrites selected target files only after contract validation. Back up edited translations first: generation uses its cache and does not merge human edits in the target JSON. Endpoints are not a supported API contract and can change or reject requests; no availability guarantee. Do not use this command with private content you do not intend to send to that service. Use the site's established import/staging tool separately; this utility never activates languages, updates WordPress or deploys.

`TRANSLATION_REPORT.md` preserves the supplied report as provenance. Its claims are distinct from reproducible structural checks; its historical paths refer to the original artifacts directory. The original `validator.py` dependency was absent; this folder includes the contract validator. Placeholder restoration uses literal replacements so backslashes in protected code remain intact.

This contract covers the marketing site plus its exported preset/theme/Wiki/Lang UI strings. It is not a translation pack for every FluxWP plugin. Keep site catalogs with the consuming preset; do not commit customer catalogs or caches into this shared tools folder.

## Maintainers

Ahmet Yılmaz ([@sharkpaw](https://github.com/sharkpaw)).
