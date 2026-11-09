# Flux Migrate

Switches a site established with old package names to FluxWP names. The name mapping is in `map.json` , there is no fixed name in the plugin code.

## Usage

1. Take a full backup (database + `wp-content/uploads` ).
2. Install the new FluxWP plugins and theme, **do not activate**.
3. Install and activate this plugin: **Tools › Flux Migrate**.
4. **Scan**: shows how many records will be changed at each step without changing anything.
5. **Transfer**: moves data. The active plugin list and theme also switch to new names.
6. Enter the site key of the new update server in the FluxWP Panel › Settings (the address and key of the old server are deleted on transfer).
7. Check the site, then select and delete old tables, folders, plugins and settings from the **Old remnants** section on the same screen.
8. Delete old plugins, clear cache (LiteSpeed ​​› Purge All), delete this plugin.

WP-CLI: `wp flx-migrate scan` and `wp flx-migrate run` .

## What does it carry

| Step | What does |
|---|---|
| Tables |  Renames `{prefix}<eski>` tables to new name (backs up as `<ad>_bakAAGG` if target is full) |
| Table contents | Legacy identifiers in text columns of these tables |
| Settings | Option names and values ​​(also in serialized/JSON values), active plugins, roles and permissions, cron hooks; old transient data is deleted |
| Theme | `stylesheet` / `template` and `theme_mods_*` |
| Meta | post/user/term meta keys and values ​​|
| Articles | Post types; block names, shortcodes, class/id/data attributes, `wp-content/...` paths in the content |
| Taxonomies | Taxonomy names, theme term of block templates |
| WooCommerce | HPOS order metadata, payment method, order item metadata |
| Upload folders | `uploads/<eski>` → `uploads/<yeni>` |
| Encrypted settings | Passwords and tokens encrypted with the old key are re-encrypted with the new key (Mail, AI, Social, iyzico) |

Each step can be run again; There is nothing left to change on the second run.

## Maintainers

Ahmet Yılmaz ([@sharkpaw](https://github.com/sharkpaw)).
