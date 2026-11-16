#!/usr/bin/env bash
# A test site with demo content for the Studio screen (screenshots, manual checks). No real generation.
#
# Usage: tools/tests/wp-graphql/flx-studio/demo.sh [port]   (environment: see ../site.sh)
# Opens http://127.0.0.1:<port>/wp-admin/admin.php?page=flx-studio (admin / admin); PID in <site>/.server.pid.
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
port="${1:-8891}"
site="${TEST_ROOT:-/tmp/flx-test}/studio-demo"
WP="${WP_CLI:-wp} --allow-root --quiet"
FIXTURES=0 SERVE=1 bash "$here/../site.sh" "$site" "$port"
mkdir -p "$site/wp-content/mu-plugins"
cp "$here/demo-mu.php" "$site/wp-content/mu-plugins/flxst-demo.php"
$WP eval-file "$here/demo-seed.php" --path="$site"
echo "http://127.0.0.1:$port/wp-admin/admin.php?page=flx-studio"
