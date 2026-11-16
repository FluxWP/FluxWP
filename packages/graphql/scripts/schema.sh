#!/usr/bin/env bash
# Schema snapshot: a test site with every GraphQL package (no kit fixtures) prints its schema to
# packages/graphql/schema/schema.graphql. Environment: see tools/tests/wp-graphql/site.sh.
set -euo pipefail
pkg="$(cd "$(dirname "$0")/.." && pwd)"
root="$(cd "$pkg/../.." && pwd)"
site="${TEST_ROOT:-/tmp/flx-test}/gql-schema"
FIXTURES=0 SERVE=0 bash "$root/tools/tests/wp-graphql/site.sh" "$site" >/dev/null
${WP_CLI:-wp} --allow-root --path="$site" graphql generate-static-schema --output="$pkg/schema/schema.graphql" >/dev/null
echo "wrote packages/graphql/schema/schema.graphql ($(grep -c '' "$pkg/schema/schema.graphql") lines)"
