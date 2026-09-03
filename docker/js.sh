#!/usr/bin/env bash
# Typechecks, builds and tests the npm client inside a throwaway container.
#
# The source is mounted read-only and copied inside, so node_modules and dist
# never appear on the host and Node does not have to be installed.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

docker run --rm -v "${ROOT}/js:/src:ro" node:22-alpine sh -c '
  cp -r /src /app && cd /app && rm -rf node_modules dist dist-test
  npm install --no-audit --no-fund --silent

  echo "==> typecheck"
  npx tsc -p tsconfig.json --noEmit

  echo "==> build"
  npx tsc -p tsconfig.build.json

  echo "==> test"
  npx tsc -p tsconfig.json
  node --test "dist-test/**/*.test.js"
'
