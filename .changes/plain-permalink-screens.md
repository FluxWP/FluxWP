---
"@fluxwp/modules": patch
---
wp-admin screens on plain permalinks: the site address is read from `/index.php?rest_route=/` and GraphQL calls use the server's own endpoint URL, so actions no longer fail with a foreign-URL error.
