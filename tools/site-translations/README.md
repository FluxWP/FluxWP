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

The original site JSON contract covers the marketing site plus its exported preset/theme/Wiki/Lang UI strings. Independent plugin language packs use the WordPress workflow below. Keep site catalogs with the consuming preset; do not commit customer catalogs or caches into this shared tools folder.

## Maintainers

Ahmet Yılmaz ([@sharkpaw](https://github.com/sharkpaw)).

## Independent WordPress package catalogs

`package_translations.py` inventories native PHP gettext calls (with PHP tokens) and literal JavaScript calls across plugin/theme repositories. Canonical editable keys and PHP source msgids are English. Compatibility aliases keep older integrations working. Turkish output is preserved in a separate `tr_TR` pack. Every package ships its own MO/PHP/PO/JSON files; users do not need Flux Lang, a remote service or an API key. WordPress selects the site's locale on the frontend and the user's locale in the admin. Eight added primary locales are `de_DE`, `es_ES`, `fr_FR`, `pt_BR`, `ja`, `zh_CN`, `ar`, `ru_RU`; English/Turkish remain available.

```sh
python3 tools/site-translations/package_translations.py \
  --repo /path/to/FluxWP --repo /path/to/wp-extras --output /path/to/workspace
# Explicit network opt-in, resumable per-language cache, then compile:
python3 tools/site-translations/package_translations.py \
  --repo /path/to/FluxWP --repo /path/to/wp-extras --output /path/to/workspace --translate --build
# Validate returned files and compile locally without network/cache requirements:
python3 tools/site-translations/package_translations.py \
  --repo /path/to/FluxWP --repo /path/to/wp-extras --output /path/to/output --import-catalogs /path/to/returned-files
```

Docker with the local `php:8.3-cli` image reads existing PHP catalogs and statically inventories quoted gettext calls; application source is not executed. English normalization of older Turkish IDs requires the known English reference; `--normalize-source` rewrites only these mapped PHP literals. New source messages must be English. Keep JSON keys, contexts, plural slots, placeholders and code intact. GNU plural rules select the correct slot, but machine-generated forms still merit linguistic review. Gutenberg scripts receive native Jed JSON. Shared React WordPress embeds use the same ten locale codes and canonical English task keys.

The native catalog build does not depend on Flux Lang. Flux Lang handles site URL prefixes, content relationships and the site's language picker. Site records are generated separately by the preset's `tools/apply-site-catalogs.py`; complete content sets activate only after preset import succeeds. No tool in this folder deploys or merges a PR.

[Language addition and coverage guide](LANGUAGES.md)
