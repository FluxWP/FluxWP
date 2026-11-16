#!/usr/bin/env bash
# Regenerates the React screen dictionaries (packages/modules/src/files/i18n/{en,tr}.json) from the
# plugin's own strings, so the classic screen and the React screen share one set of translations.
#   tools/gen-react-i18n.sh <wp site path>   (a test site with flx-files active; see tools/tests/wp-graphql/site.sh)
set -euo pipefail
site="${1:?usage: gen-react-i18n.sh <wp site path>}"
here="$(cd "$(dirname "$0")" && pwd)"
out="$here/../../../packages/modules/src/files/i18n"
mkdir -p "$out"
for pair in en:en_US tr:tr_TR; do
  LOC="${pair#*:}" OUT="$out/${pair%%:*}.json" ${WP_CLI:-wp} --allow-root --path="$site" eval-file "$here/dump-strings.php" >/dev/null
done
echo "wrote $out/{en,tr}.json"
