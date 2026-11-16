#!/usr/bin/env bash
# Schema snapshot: a test site with every GraphQL package (no kit fixtures) prints its schema and the
# owner of every type and field (wp flx-gql schema); scripts/schema-parts.ts splits it into one part per
# package: plugins/<slug>/graphql/schema.graphql, themes/<slug>/graphql/schema.graphql and
# packages/graphql/schema/core.graphql (WordPress / WPGraphQL). Environment: see tools/tests/wp-graphql/site.sh.
#
#   bun run --filter @fluxwp/graphql schema                       every part
#   bun run --filter @fluxwp/graphql schema -- plugins/flx-tasks  that part only (a package pull request
#                                                                 commits only its own part)
set -euo pipefail
pkg="$(cd "$(dirname "$0")/.." && pwd)"
root="$(cd "$pkg/../.." && pwd)"
site="${TEST_ROOT:-/tmp/flx-test}/gql-schema"
out="$(mktemp -d)"
trap 'rm -rf "$out"' EXIT
FIXTURES=0 SERVE=0 bash "$root/tools/tests/wp-graphql/site.sh" "$site" >/dev/null
${WP_CLI:-wp} --allow-root --path="$site" flx-gql schema --output="$out/schema.graphql" --owners="$out/owners.json" >/dev/null
only=()
[ -n "${1:-}" ] && only=(--only "${1%/}")
bun "$pkg/scripts/schema-parts.ts" split "$out/schema.graphql" "$out/owners.json" "${only[@]}"
if [ -n "${SCHEMA_RAW_DIR:-}" ]; then
  mkdir -p "$SCHEMA_RAW_DIR" && cp "$out/schema.graphql" "$out/owners.json" "$SCHEMA_RAW_DIR/"
fi
