#!/usr/bin/env bash
# Extracts the schema parts that the "GraphQL schema and types" CI job printed (FLX-SCHEMA-PARTS line)
# into this checkout. For sessions that cannot download the graphql-schema-ci artifact.
#
#   gh api repos/FluxWP/FluxWP/actions/jobs/<job id>/logs | bash tools/graphql/ci-parts.sh               every part
#   gh api repos/FluxWP/FluxWP/actions/jobs/<job id>/logs | bash tools/graphql/ci-parts.sh plugins/flx-x  only that part
#
# A package pull request commits only its own part (docs/graphql/KURALLAR.md §12).
set -euo pipefail
root="$(cd "$(dirname "$0")/../.." && pwd)"
line="$(grep -o 'FLX-SCHEMA-PARTS-BEGIN [A-Za-z0-9+/=]* FLX-SCHEMA-PARTS-END' | tail -n 1 || true)"
[ -n "$line" ] || { echo "no FLX-SCHEMA-PARTS line in the log" >&2; exit 1; }
data="${line#FLX-SCHEMA-PARTS-BEGIN }"
data="${data% FLX-SCHEMA-PARTS-END}"
if [ -n "${1:-}" ]; then
  echo "$data" | base64 -d | tar -xzvf - -C "$root" "${1%/}/graphql/schema.graphql"
else
  echo "$data" | base64 -d | tar -xzvf - -C "$root"
fi
