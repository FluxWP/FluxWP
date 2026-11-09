# Refresh package screenshots and walkthroughs

**English** · [Türkçe](README.tr.md)

Use this pipeline whenever a plugin or theme changes. It captures the actual current UI and refreshes existing documentation, the Updater catalog and website examples without rewriting README prose or translated marketing copy.

**English is the default capture language.** Turkish screenshots are retained. Request `--language tr` to refresh them too. Website examples always use English screenshots and walkthroughs; the native FluxWP Panel selects English/Turkish previews from the WordPress administrator's locale, with English fallback.

## 1. Prepare the local capture site

Requirements: Docker, Bun, Python 3, FFmpeg, and Pillow for image conversion (`uv run --with pillow` can supply it).

From the repository root:

```sh
bun install --frozen-lockfile
bunx playwright install chromium
python3 tools/readme-media/setup-docker.py --work /absolute/shared/capture-work
```

The generated `capture-env.json` records the local URL, working directory and container PHP wrapper. Source repositories are mounted read-only; WordPress and MariaDB live in a separate Docker project. On macOS the work directory must be shared with Docker Desktop. Use a separate project/port for parallel fixtures.

Prepare the recipe's `seed` files using `capture-work/php`, then populate related fixtures as needed (`seed/users.php`, `seed-site.php`, `seed-posts.php`, `seed-form.php`, and the package-specific seeds). Activate the FluxWP demo preset for theme captures. The scripts in `env/mock-update` and `env/mock-evolution` provide local service fixtures; `env/connect-mocks.php` configures those local connections. Never copy production databases, keys or customer records into screenshots.

`demo-guard.php` blocks real email and external WordPress HTTP requests. Browser requests also stay local. Public package captures must use a public-only fixture; use `--private-root` only in a separate private fixture. Snapshot/restore the local database between preset captures so one preset cannot change another's examples.

## 2. Recapture an updated package

```sh
# Default: refresh English screenshots, mobile/dark views and walkthrough GIF.
python3 tools/readme-media/capture-package.py tools/readme-media/cfg/flx-forms.json --env /absolute/shared/capture-work/capture-env.json

# Explicitly refresh both languages (required for a brand-new package).
python3 tools/readme-media/capture-package.py tools/readme-media/cfg/flx-forms.json --env /absolute/shared/capture-work/capture-env.json --language en --language tr
```

Other options: `--no-tour` for screenshots only, `--repo /absolute/repository` for another owning repository, and `--area themes` for a theme. Private recipes belong in their private repository. `--no-shots` is only for screenshots already verified in the same session.

Outputs belong to the package:

| Output | Purpose |
|---|---|
| `docs/images/<scene>-en.jpg`, `*-tr.jpg` | Full desktop, mobile and dark screenshots |
| `docs/demo.gif`, `docs/demo-tr.gif` | Actual browser walkthroughs |
| `docs/thumb.gif` | README gallery thumbnail |
| `docs/previews/*.webp` | Updater derivatives generated in the next step |

Critical errors, denied pages, failed HTTP responses or missing required screenshots fail the capture. Review the images before committing. The pipeline preserves README prose and existing Turkish media during an English-only refresh.

## 3. Refresh distribution files

```sh
uv run --with pillow tools/readme-media/export-updater-previews.py --repo /absolute/FluxWP --site-preset /absolute/site-preset
uv run --with pillow tools/readme-media/refresh-site-media.py /absolute/site-preset --package flx-forms
NODE_PATH=/absolute/FluxWP/node_modules bun /absolute/site-preset/tools/plugin-pages-og.mjs flx-forms
```

For private media, add `--private-root /absolute/private-repository` to the catalog exporter. It writes a separate catalog only into that repository. `--translate` is optional and fills missing short descriptions; existing copy and publisher links are preserved.

`catalog/packages.json` owns localized descriptions, publisher links and Git blob references to the icons/gallery images. Commit the catalog and referenced assets together. Updater reads the default branch, verifies and caches those blobs, and distributes signed URLs from its own host. There is no second upload/editor in Updater and no bundled preview fallback in the Panel.

The website converter preserves all translated page text. Localized share cards retain their translated titles/captions while using the English screenshot. Turkish source screenshots and native Panel galleries remain available.

## 4. Verify and publish

- Check desktop/mobile and light/dark screenshots. English capture UI must be English; Turkish capture UI must be Turkish. Use fictional sample data.
- Verify English website examples under EN/TR/RTL page languages and native Panel EN/TR gallery selection.
- Run `tests/english-media.php` in the site preset against a disposable WordPress fixture (`FLX_TEST_SITE=1`).
- Check `git diff`, bump the changed package version/changelog, build its ZIP, and open a PR. Documentation/preview images must stay out of installation ZIPs.
- After review/merge, the owning repository's catalog reaches Updater. A changed screenshot needs new catalog blob references even when its filename stays the same.

A future request can be: “Refresh screenshots for Flux Forms using this guide. Use English by default, keep Turkish copies, update the catalog and website derivatives, and show desktop/mobile light/dark previews.”

## Recipes and legacy tools

`cfg/<slug>.json` defines `shots`, `mobile`, `dark`, and optional `seed`/CSS/actions. URLs may have `{post:Title}` placeholders (resolved from the local database) or `{ "en": "…", "tr": "…" }` language-specific values. Actions include `click`, `keys`, `type`, `hover`, `eval`, `sel`, `clip`, and `full`. `shoot.js` takes screenshots; `tour.js` records non-destructive navigation. Update the recipe when a feature's route or selector changes.

The old `run.sh`, `setup.sh` (host PHP/SQLite), `gen.py` and `place.sh` are legacy tools. **Do not use `run.sh` for a normal media refresh:** it can regenerate README prose and overwrite manual additions. Use `capture-package.py` above.

Root README galleries can be refreshed with `bin/readme-table.sh README.md en` and `bin/readme-table.sh README.tr.md tr`; release automation also maintains them.

## Maintainers

Ahmet Yılmaz · [@sharkpaw](https://github.com/sharkpaw)
