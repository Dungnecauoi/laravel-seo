#!/usr/bin/env bash
# Typechecks, builds and tests every npm workspace inside a throwaway
# container: @duxbo/seo-core, @duxbo/seo-react, @duxbo/seo-vue.
#
# The source is mounted read-only and copied inside, so node_modules and dist
# never appear on the host and Node does not have to be installed.
#
# Build order matters: react and vue import "@duxbo/seo-core" as an ordinary
# package specifier. TypeScript resolves that at compile time through the
# "paths" mapping in their tsconfig (straight to core's source, so editing
# core needs no rebuild to typecheck against it) — but the emitted JS keeps
# the bare specifier, which Node can only resolve through the workspace
# symlink to core's *built* dist/. So core must be built before react or vue
# can run their tests.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

docker run --rm -v "${ROOT}/js:/src:ro" node:22-alpine sh -c '
  set -e
  cp -r /src /app && cd /app
  find . -name node_modules -prune -exec rm -rf {} + 2>/dev/null || true
  find . -name dist -prune -exec rm -rf {} + 2>/dev/null || true
  find . -name dist-test -prune -exec rm -rf {} + 2>/dev/null || true

  npm install --no-audit --no-fund --silent

  for pkg in core react vue; do
    echo "==> @duxbo/seo-${pkg}: typecheck"
    npm run typecheck --workspace="packages/${pkg}"

    echo "==> @duxbo/seo-${pkg}: build"
    npm run build --workspace="packages/${pkg}"

    echo "==> @duxbo/seo-${pkg}: test"
    npm run test --workspace="packages/${pkg}"
  done
'
