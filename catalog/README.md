# Package previews

**English** · [Türkçe](README.tr.md)

`packages.json` is the source for descriptions, publisher links, icons and screenshots distributed by Updater. Edit localized `desc` and `website` fields here. Re-exporting preserves these edits.

Refresh package UI media with [`tools/readme-media`](../tools/readme-media/README.md), then run its `export-updater-previews.py` using the documented environment. Commit the manifest and referenced package assets together. Each asset reference contains its repository path, Git blob SHA and byte size; regenerate references after changing an image or SVG.

Updater checks the default branch, pins the catalog revision, verifies and caches media lazily, and serves its own signed URLs. GitHub credentials stay on the server. Private packages have their own catalog in their owning repository; never copy private content here. Internal maintenance tools are not advertised.

## Maintainers

Ahmet Yılmaz · [@sharkpaw](https://github.com/sharkpaw)
